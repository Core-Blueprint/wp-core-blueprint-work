<?php
declare(strict_types=1);

namespace CB\Work\Recurrence;

use CoreBlueprint\Core\Governance\Audit;
use CB\Work\Domain\RecurrenceSchedule;
use CB\Work\Governance\Events;
use CB\Work\Repository\RecurrenceOccurrences;
use CB\Work\Repository\RecurrenceRules;
use CB\Work\Repository\WorkItems;

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
			if ( null === $rule || empty( $rule['is_active'] ) || null === $rule['next_occurrence_on'] ) {
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

			$recovered = $linked_work_item_id > 0;
			if ( ! $recovered ) {
				$input = RecurrenceRules::occurrence_work_item_input( $occurrence_id );
				if ( null === $input ) {
					RecurrenceOccurrences::release( $occurrence_id, $token, 'Occurrence could not be projected to a Work Item.' );
					self::failure( $rule_id, $occurrence_id, 'Occurrence could not be projected to a Work Item.', $source, $stats );
					return;
				}
				$input['created_by'] = max( 0, (int) ( $rule['created_by'] ?? 0 ) );
				$linked_work_item_id = WorkItems::create( $input );
				if ( $linked_work_item_id <= 0 ) {
					RecurrenceOccurrences::release( $occurrence_id, $token, 'Canonical Work Item creation failed.' );
					self::failure( $rule_id, $occurrence_id, 'Canonical Work Item creation failed.', $source, $stats );
					return;
				}
			}

			if ( ! RecurrenceOccurrences::attach_work_item( $occurrence_id, $linked_work_item_id, $token ) ) {
				RecurrenceOccurrences::release( $occurrence_id, $token, 'Work Item could not be attached to the occurrence ledger.' );
				self::failure( $rule_id, $occurrence_id, 'Work Item could not be attached to the occurrence ledger.', $source, $stats );
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
