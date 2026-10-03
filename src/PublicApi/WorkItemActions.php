<?php
declare(strict_types=1);

namespace CB\Work\PublicApi;

use CoreBlueprint\Core\Governance\Audit;
use CB\Work\Capabilities;
use CB\Work\Governance\Events;
use CB\Work\Repository\WorkItemRelations;
use CB\Work\Repository\WorkItemSources;
use CB\Work\Repository\WorkItems as WorkItemRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Supported governed mutation contract for sibling integrations.
 *
 * This is a server-side PHP contract, not an HTTP endpoint. Transport layers
 * still own nonce/CSRF protection. Mutations require the current actor to hold
 * the canonical Work management capability.
 */
final class WorkItemActions {
	private const SOURCE_CLAIM_STALE_SECONDS = 900;

	/** @param array<string,mixed> $input @return array<string,mixed>|\WP_Error */
	public static function create( array $input ): array|\WP_Error {
		$forbidden = self::authorize_manage();
		if ( null !== $forbidden ) {
			return $forbidden;
		}
		if ( self::contains_source_fields( $input ) ) {
			return new \WP_Error( 'work_source_contract_required' );
		}

		$input['created_by'] = get_current_user_id();
		$work_item_id = WorkItemRepository::create( $input );
		if ( $work_item_id <= 0 ) {
			return new \WP_Error( 'work_create_failed' );
		}

		$item = WorkItems::get( $work_item_id );
		if ( null === $item ) {
			return new \WP_Error( 'work_resource_unavailable' );
		}

		Audit::record( Events::WORK_ITEM_CREATED, 'notice', [
			'work_item_id' => $work_item_id,
			'actor_user_id' => get_current_user_id(),
			'channel' => 'public_api',
		] );
		return $item;
	}

	/**
	 * Creates exactly one canonical Work Item for one external source identity.
	 * Replays return the already-linked Work Item instead of creating a duplicate.
	 *
	 * @param array<string,mixed> $input
	 * @return array{outcome:string,work_item:array<string,mixed>,source:array<string,mixed>}|\WP_Error
	 */
	public static function create_from_source( string $provider, string $source_type, string $external_id, array $input ): array|\WP_Error {
		$forbidden = self::authorize_manage();
		if ( null !== $forbidden ) {
			return $forbidden;
		}
		if ( self::contains_source_fields( $input ) ) {
			return new \WP_Error( 'work_source_contract_required' );
		}
		if ( self::is_reserved_reference( $provider, $source_type ) ) {
			return new \WP_Error( 'work_source_reserved' );
		}

		$claim = WorkItemSources::claim( $provider, $source_type, $external_id, self::SOURCE_CLAIM_STALE_SECONDS );
		if ( false === $claim ) {
			return new \WP_Error( 'work_source_invalid' );
		}

		if ( 'existing' === $claim['status'] ) {
			$item = WorkItems::get( $claim['work_item_id'] );
			$source = WorkItemSources::projection( WorkItemSources::get( $claim['source_id'] ) );
			if ( null === $item || null === $source ) {
				return new \WP_Error( 'work_source_corrupt' );
			}
			return [ 'outcome' => 'reused', 'work_item' => $item, 'source' => $source ];
		}

		if ( 'owned' !== $claim['status'] ) {
			return new \WP_Error( 'work_source_busy' );
		}

		$source_id   = $claim['source_id'];
		$claim_token = $claim['claim_token'];
		$recovered   = WorkItemSources::recovery_work_item_ids( $source_id );
		if ( count( $recovered ) > 1 ) {
			return new \WP_Error( 'work_source_corrupt' );
		}

		$outcome = 'recovered';
		if ( 1 === count( $recovered ) ) {
			$work_item_id = $recovered[0];
		} else {
			$outcome = 'created';
			$input['created_by']      = get_current_user_id();
			$input['source_provider'] = WorkItemSources::RECOVERY_PROVIDER;
			$input['source_type']     = WorkItemSources::RECOVERY_TYPE;
			$input['source_id']       = (string) $source_id;
			$work_item_id = WorkItemRepository::create( $input );
			if ( $work_item_id <= 0 ) {
				WorkItemSources::release( $source_id, $claim_token );
				return new \WP_Error( 'work_create_failed' );
			}
		}

		if ( ! WorkItemSources::attach( $source_id, $work_item_id, $claim_token ) ) {
			return new \WP_Error( 'work_source_attach_failed' );
		}

		$item   = WorkItems::get( $work_item_id );
		$source = WorkItemSources::projection( WorkItemSources::get( $source_id ) );
		if ( null === $item || null === $source ) {
			return new \WP_Error( 'work_resource_unavailable' );
		}

		if ( 'created' === $outcome ) {
			Audit::record( Events::WORK_ITEM_CREATED, 'notice', [
				'work_item_id' => $work_item_id,
				'actor_user_id' => get_current_user_id(),
				'channel' => 'public_api_source',
			] );
		}
		Audit::record( Events::WORK_ITEM_SOURCE_ATTACHED, 'notice', [
			'work_item_id' => $work_item_id,
			'source_identity_id' => $source_id,
			'provider' => $source['provider'],
			'source_type' => $source['source_type'],
			'actor_user_id' => get_current_user_id(),
		] );
		do_action( 'cb_work_work_item_source_attached', $work_item_id, $source, $item );

		return [ 'outcome' => $outcome, 'work_item' => $item, 'source' => $source ];
	}

	/** @param array<string,mixed> $input @return array<string,mixed>|\WP_Error */
	public static function update( int $work_item_id, array $input ): array|\WP_Error {
		$forbidden = self::authorize_manage();
		if ( null !== $forbidden ) {
			return $forbidden;
		}
		if ( $work_item_id <= 0 || self::contains_source_fields( $input ) ) {
			return new \WP_Error( 'work_update_invalid' );
		}
		if ( ! WorkItemRepository::update( $work_item_id, $input ) ) {
			return new \WP_Error( 'work_update_failed' );
		}
		$item = WorkItems::get( $work_item_id );
		if ( null === $item ) {
			return new \WP_Error( 'work_resource_unavailable' );
		}
		Audit::record( Events::WORK_ITEM_UPDATED, 'notice', [
			'work_item_id' => $work_item_id,
			'actor_user_id' => get_current_user_id(),
			'channel' => 'public_api',
		] );
		return $item;
	}

	/** @return array<string,mixed>|\WP_Error */
	public static function transition_status( int $work_item_id, string $to ): array|\WP_Error {
		$forbidden = self::authorize_manage();
		if ( null !== $forbidden ) {
			return $forbidden;
		}
		$current = WorkItemRepository::get( $work_item_id );
		if ( null === $current ) {
			return new \WP_Error( 'work_resource_unavailable' );
		}
		$from = (string) $current['status'];
		if ( ! WorkItemRepository::transition_status( $work_item_id, $to, get_current_user_id() ) ) {
			return new \WP_Error( 'work_transition_invalid' );
		}
		$item = WorkItems::get( $work_item_id );
		if ( null === $item ) {
			return new \WP_Error( 'work_resource_unavailable' );
		}
		Audit::record( Events::WORK_ITEM_STATUS_CHANGED, 'notice', [
			'work_item_id' => $work_item_id,
			'from' => $from,
			'to' => (string) $item['status'],
			'actor_user_id' => get_current_user_id(),
			'channel' => 'public_api',
		] );
		return $item;
	}

	/** @return array<string,mixed>|\WP_Error */
	public static function add_relation( int $work_item_id, string $provider, string $relation_type, string $external_id ): array|\WP_Error {
		$forbidden = self::authorize_manage();
		if ( null !== $forbidden ) {
			return $forbidden;
		}
		$relation = WorkItemRelations::normalize( $provider, $relation_type, $external_id );
		$item     = WorkItemRepository::get( $work_item_id );
		if ( false === $relation || null === $item ) {
			return new \WP_Error( 'work_relation_invalid' );
		}
		if ( self::is_reserved_reference( $relation['provider'], $relation['type'] ) ) {
			return new \WP_Error( 'work_relation_reserved' );
		}

		if ( self::has_relation( $item, $relation ) ) {
			$public = WorkItems::get( $work_item_id );
			return null === $public ? new \WP_Error( 'work_resource_unavailable' ) : $public;
		}
		if ( ! WorkItemRepository::add_relation( $work_item_id, $relation['provider'], $relation['type'], $relation['id'] ) ) {
			$fresh = WorkItemRepository::get( $work_item_id );
			if ( null === $fresh || ! self::has_relation( $fresh, $relation ) ) {
				return new \WP_Error( 'work_relation_failed' );
			}
			$public = WorkItems::get( $work_item_id );
			return null === $public ? new \WP_Error( 'work_resource_unavailable' ) : $public;
		}

		$item = WorkItems::get( $work_item_id );
		if ( null === $item ) {
			return new \WP_Error( 'work_resource_unavailable' );
		}
		Audit::record( Events::WORK_ITEM_RELATION_ADDED, 'notice', [
			'work_item_id' => $work_item_id,
			'provider' => $relation['provider'],
			'relation_type' => $relation['type'],
			'actor_user_id' => get_current_user_id(),
		] );
		do_action( 'cb_work_work_item_relation_added', $work_item_id, $relation, $item );
		return $item;
	}

	private static function authorize_manage(): ?\WP_Error {
		return current_user_can( Capabilities::MANAGE ) ? null : new \WP_Error( 'work_action_forbidden' );
	}

	/** @param array<string,mixed> $input */
	private static function contains_source_fields( array $input ): bool {
		return array_key_exists( 'source_provider', $input )
			|| array_key_exists( 'source_type', $input )
			|| array_key_exists( 'source_id', $input );
	}

	private static function is_reserved_reference( string $provider, string $relation_type ): bool {
		return WorkItemSources::RECOVERY_PROVIDER === sanitize_key( $provider )
			&& WorkItemSources::RECOVERY_TYPE === sanitize_key( $relation_type );
	}

	/** @param array<string,mixed> $item @param array{provider:string,type:string,id:string} $relation */
	private static function has_relation( array $item, array $relation ): bool {
		foreach ( (array) ( $item['relations'] ?? [] ) as $current ) {
			if (
				(string) ( $current['provider'] ?? '' ) === $relation['provider']
				&& (string) ( $current['relation_type'] ?? '' ) === $relation['type']
				&& (string) ( $current['external_id'] ?? '' ) === $relation['id']
			) {
				return true;
			}
		}
		return false;
	}
}
