<?php
declare(strict_types=1);

namespace CB\Work\Recurrence;

use CoreBlueprint\Core\Governance\Audit;
use CB\Work\Domain\RecurrenceSchedule;
use CB\Work\Governance\Events;
use CB\Work\Database\Schema;
use CB\Work\Repository\RecurrenceOccurrences;
use CB\Work\Repository\RecurrenceRules;
use CB\Work\Repository\WorkItems;
use CB\Work\Repository\WorkSqlTransaction;

defined( 'ABSPATH' ) || exit;

final class Scheduler {
	public const HOOK = 'cb_work_generate_recurring_work';
	private const CLAIM_STALE_SECONDS      = 900;
	private const RULE_LIMIT               = 100;
	private const MAX_OCCURRENCES_PER_RULE = 250;

	public static function init(): void {
		add_action( self::HOOK, [ self::class, 'run_cron' ] );
		add_action( 'init', [ self::class, 'ensure_scheduled' ], 20 );
	}

	public static function ensure_scheduled(): void {
		if ( false === wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( time() + MINUTE_IN_SECONDS, 'hourly', self::HOOK );
		}
	}

	public static function deactivate(): void {
		wp_clear_scheduled_hook( self::HOOK );
	}

	public static function run_cron(): void {
		self::run( 0, 'cron' );
	}

	/**
	 * Runs one bounded generation pass.
	 *
	 * Catch-up is intentional: each rule advances occurrence-by-occurrence until
	 * its own create-ahead horizon is satisfied. A hard per-rule cap prevents a
	 * long-dormant site from monopolising one request; later runs continue from
	 * the still-canonical next occurrence.
	 *
	 * @return array{rules:int,generated:int,recovered:int,advanced:int,failed:int,busy:int,limited:int}
	 */
	public static function run( int $actor_user_id = 0, string $source = 'manual' ): array {
		$today  = current_time( 'Y-m-d' );
		$source = sanitize_key( $source );
		$stats  = [
			'rules'     => 0,
			'generated' => 0,
			'recovered' => 0,
			'advanced'  => 0,
			'failed'    => 0,
			'busy'      => 0,
			'limited'   => 0,
		];

		$rules = RecurrenceRules::due_for_generation( $today, self::RULE_LIMIT );
		foreach ( $rules as $rule ) {
			$rule_id = (int) ( $rule['id'] ?? 0 );
			if ( $rule_id <= 0 ) {
				continue;
			}
			$stats['rules']++;
			self::process_rule( $rule_id, $today, $actor_user_id, $source, $stats );
		}

		Audit::record( Events::RECURRENCE_GENERATOR_RUN, 'notice', [
			'source' => $source,
			'actor_user_id' => max( 0, $actor_user_id ),
			...$stats,
		] );
		return $stats;
	}

	/** @param array{rules:int,generated:int,recovered:int,advanced:int,failed:int,busy:int,limited:int} $stats */
	private static function process_rule( int $rule_id, string $today, int $actor_user_id, string $source, array &$stats ): void {
		for ( $processed = 0; $processed < self::MAX_OCCURRENCES_PER_RULE; $processed++ ) {
			$rule = RecurrenceRules::get( $rule_id );
			if ( null === $rule || empty( $rule['is_active'] ) || ! empty( $rule['is_trashed'] ) || null === $rule['next_occurrence_on'] ) {
				return;
			}

			$horizon = RecurrenceSchedule::add_days( $today, (int) $rule['create_ahead_days'] );
			$occurrence_on = (string) $rule['next_occurrence_on'];
			if ( null === $horizon ) {
				self::failure( $rule_id, 0, 'Invalid create-ahead horizon.', $source, $stats );
				return;
			}
			if ( $occurrence_on > $horizon ) {
				return;
			}

			$occurrence = RecurrenceOccurrences::find( $rule_id, $occurrence_on );
			if ( null === $occurrence ) {
				$reserved_id = RecurrenceRules::reserve_next_occurrence( $rule_id );
				$occurrence  = $reserved_id > 0
					? RecurrenceOccurrences::get( $reserved_id )
					: RecurrenceOccurrences::find( $rule_id, $occurrence_on );
			}
			if ( null === $occurrence ) {
				self::failure( $rule_id, 0, 'Occurrence reservation could not be resolved.', $source, $stats );
				return;
			}

			$occurrence_id = (int) $occurrence['id'];
			if ( (int) $occurrence['work_item_id'] > 0 ) {
				if ( self::advance_or_observe( $rule_id, $occurrence_on, $actor_user_id, $stats ) ) {
					continue;
				}
				self::failure( $rule_id, $occurrence_id, 'Generated occurrence could not advance its rule.', $source, $stats );
				return;
			}

			$token = str_replace( '-', '', wp_generate_uuid4() );
			if ( ! RecurrenceOccurrences::claim( $occurrence_id, $token, self::CLAIM_STALE_SECONDS ) ) {
				$stats['busy']++;
				return;
			}

			$linked_work_item_id = RecurrenceOccurrences::find_work_item( $occurrence_id );
			if ( $linked_work_item_id < 0 ) {
				RecurrenceOccurrences::release( $occurrence_id, $token, 'Multiple Work Items reference this occurrence.' );
				self::failure( $rule_id, $occurrence_id, 'Multiple Work Items reference this occurrence.', $source, $stats );
				return;
			}

			// Revalidate after claim: trash/restore must not silently resurrect generation.
			$current_rule = RecurrenceRules::get( $rule_id );
			if ( null === $current_rule || empty( $current_rule['is_active'] ) || ! empty( $current_rule['is_trashed'] ) ) {
				RecurrenceOccurrences::release( $occurrence_id, $token );
				return;
			}
			$recovered = $linked_work_item_id > 0;
			if ( ! $recovered ) {
				// Serialize with RecurrenceRules::trash() on the SAME rule row.
				// Claiming an occurrence is not sufficient: Trash can otherwise
				// commit between the active-state check and the canonical post insertion.
				// This scoped transaction also atomically attaches the provenance.
				$linked_work_item_id = WorkSqlTransaction::run(
					static function () use ( $rule_id, $occurrence_id, $token ): int {
						global $wpdb;
						$table = Schema::recurrence_rules_table();
						// Use the locking read as the authoritative status: a
						// subsequent ordinary SELECT can see an old REPEATABLE READ
						// snapshot when the caller already owns a transaction.
						$locked = $wpdb->get_row( $wpdb->prepare(
							'SELECT id, is_active, trashed_at, created_by FROM ' . $table . ' WHERE id = %d FOR UPDATE',
							$rule_id
						), ARRAY_A );
						if ( ! is_array( $locked ) || (int) $locked['id'] !== $rule_id
							|| 1 !== (int) $locked['is_active'] || null !== $locked['trashed_at']
							|| '' !== (string) $wpdb->last_error ) {
							return 0;
						}
						$claimed = RecurrenceOccurrences::get( $occurrence_id );
						if ( null === $claimed || (int) $claimed['rule_id'] !== $rule_id
							|| (int) $claimed['work_item_id'] > 0 || (string) $claimed['claim_token'] !== $token ) {
							return 0;
						}
						$input = RecurrenceRules::occurrence_work_item_input( $occurrence_id );
						if ( null === $input ) {
							return 0;
						}
						$input['created_by'] = max( 0, (int) $locked['created_by'] );
						$created_id = WorkItems::create( $input );
						if ( $created_id <= 0 || ! RecurrenceOccurrences::attach_work_item( $occurrence_id, $created_id, $token ) ) {
							return 0;
						}
						return $created_id;
					},
					0
				);
				if ( $linked_work_item_id <= 0 ) {
					RecurrenceOccurrences::release( $occurrence_id, $token );
					$after = RecurrenceRules::get( $rule_id );
					if ( null === $after || empty( $after['is_active'] ) || ! empty( $after['is_trashed'] ) ) {
						return;
					}
					self::failure( $rule_id, $occurrence_id, 'Canonical Work Item creation or atomic attachment failed.', $source, $stats );
					return;
				}
			} elseif ( ! RecurrenceOccurrences::attach_work_item( $occurrence_id, $linked_work_item_id, $token ) ) {
				RecurrenceOccurrences::release( $occurrence_id, $token, 'Recovered Work Item could not be attached to the ledger.' );
				self::failure( $rule_id, $occurrence_id, 'Recovered Work Item could not be attached to the ledger.', $source, $stats );
				return;
			}

			$recovered ? $stats['recovered']++ : $stats['generated']++;
			Audit::record( Events::RECURRENCE_ITEM_GENERATED, 'notice', [
				'rule_id'        => $rule_id,
				'occurrence_id'  => $occurrence_id,
				'occurrence_on'  => $occurrence_on,
				'work_item_id'   => $linked_work_item_id,
				'recovered'      => $recovered,
				'source'         => $source,
			] );

			if ( ! self::advance_or_observe( $rule_id, $occurrence_on, $actor_user_id, $stats ) ) {
				self::failure( $rule_id, $occurrence_id, 'Generated occurrence could not advance its rule.', $source, $stats );
				return;
			}
		}

		$rule = RecurrenceRules::get( $rule_id );
		if ( null !== $rule && ! empty( $rule['is_active'] ) && null !== $rule['next_occurrence_on'] ) {
			$horizon = RecurrenceSchedule::add_days( $today, (int) $rule['create_ahead_days'] );
			if ( null !== $horizon && (string) $rule['next_occurrence_on'] <= $horizon ) {
				$stats['limited']++;
			}
		}
	}

	/** @param array{rules:int,generated:int,recovered:int,advanced:int,failed:int,busy:int,limited:int} $stats */
	private static function advance_or_observe( int $rule_id, string $occurrence_on, int $actor_user_id, array &$stats ): bool {
		if ( RecurrenceRules::advance_after( $rule_id, $occurrence_on, $actor_user_id ) ) {
			$stats['advanced']++;
			return true;
		}

		$fresh_rule = RecurrenceRules::get( $rule_id );
		if ( null !== $fresh_rule && array_key_exists( 'next_occurrence_on', $fresh_rule ) ) {
			$fresh_next = $fresh_rule['next_occurrence_on'];
			if ( null === $fresh_next || (string) $fresh_next !== $occurrence_on ) {
				$stats['advanced']++;
				return true;
			}
		}

		return false;
	}

	/** @param array{rules:int,generated:int,recovered:int,advanced:int,failed:int,busy:int,limited:int} $stats */
	private static function failure( int $rule_id, int $occurrence_id, string $message, string $source, array &$stats ): void {
		$stats['failed']++;
		Audit::record( Events::RECURRENCE_GENERATION_FAILED, 'warning', [
			'rule_id'       => $rule_id,
			'occurrence_id' => $occurrence_id,
			'message'       => $message,
			'source'        => $source,
		] );
	}
}
