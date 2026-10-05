<?php
declare(strict_types=1);

namespace CB\Work\Integration\DataExchange;

use CoreBlueprint\Core\DataExchange\EntityInterface;
use CoreBlueprint\Core\DataExchange\Foundation;
use CB\Work\Capabilities;
use CB\Work\Database\Schema;
use CB\Work\Domain\WorkContext;
use CB\Work\Domain\WorkItemPriority;
use CB\Work\Domain\WorkItemStatus;
use CB\Work\PublicApi\ProjectActions;
use CB\Work\PublicApi\Projects;
use CB\Work\PublicApi\WorkItemActions;
use CB\Work\PublicApi\WorkItems;
use CB\Work\Query\WorkItemQuery;
use CB\Work\Repository\PortableIdentities;
use CB\Work\Repository\WorkItems as WorkItemRepository;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Portable Project planning bundle.
 *
 * Schema v1 deliberately transports one internal Project and its active Work
 * Items only. Local assignments, Services, Work Types, recurrence, Time and
 * terminal history stay outside the portable planning contract.
 */
final class ProjectBundleEntity implements EntityInterface {
	private const SCHEMA_VERSION = 1;
	private const MAX_WORK_ITEMS = 500;
	private const PROJECT_KEYS = [ 'portable_key', 'title', 'description', 'work_context', 'starts_on', 'due_on', 'work_items' ];
	private const ITEM_KEYS = [ 'portable_key', 'title', 'description', 'priority', 'estimated_minutes', 'scheduled_on', 'due_on', 'status' ];

	public function is_available(): bool {
		return defined( 'CB_WORK_SCHEMA_VERSION' )
			&& CB_WORK_SCHEMA_VERSION === (string) get_option( Schema::OPTION, '0' );
	}

	public function schema_version(): int {
		return self::SCHEMA_VERSION;
	}

	public function supports_schema_version( int $schema_version ): bool {
		return self::SCHEMA_VERSION === $schema_version;
	}

	public function can_export( array $context = [] ): bool {
		unset( $context );
		return current_user_can( Capabilities::MANAGE );
	}

	public function can_import( array $context = [] ): bool {
		unset( $context );
		return current_user_can( Capabilities::MANAGE );
	}

	public function export_records( array $context = [] ): iterable|WP_Error {
		$project_id = max( 0, (int) ( $context['project_id'] ?? 0 ) );
		$project = Projects::get( $project_id );
		if ( null === $project ) {
			return new WP_Error( 'work_project_bundle_project_unavailable' );
		}
		if ( WorkContext::INTERNAL !== (string) $project['work_context'] ) {
			return new WP_Error( 'work_project_bundle_customer_context_unsupported' );
		}

		$project_key = PortableIdentities::get_or_create( PortableIdentities::PROJECT, $project_id );
		if ( is_wp_error( $project_key ) ) {
			return $project_key;
		}

		$result = WorkItemRepository::search( [
			'statuses'   => WorkItemStatus::active(),
			'project_id' => $project_id,
			'page'       => 1,
			'per_page'   => self::MAX_WORK_ITEMS,
			'sort'       => WorkItemQuery::SORT_WORKLOAD,
		] );
		if ( (int) $result['total'] > self::MAX_WORK_ITEMS ) {
			return new WP_Error( 'work_project_bundle_too_many_items' );
		}

		$items = [];
		foreach ( $result['items'] as $item ) {
			$item_key = PortableIdentities::get_or_create( PortableIdentities::WORK_ITEM, (int) $item['id'] );
			if ( is_wp_error( $item_key ) ) {
				return $item_key;
			}
			$items[] = self::export_item( $item_key, $item );
		}

		return [ [
			'portable_key' => $project_key,
			'title'        => (string) $project['title'],
			'description'  => (string) $project['description'],
			'work_context' => WorkContext::INTERNAL,
			'starts_on'    => (string) $project['starts_on'],
			'due_on'       => (string) $project['due_on'],
			'work_items'   => $items,
		] ];
	}

	public function plan_import( array $record, string $mode, int $source_schema_version, array $context = [] ): array|WP_Error {
		unset( $context );
		if ( ! $this->supports_schema_version( $source_schema_version ) ) {
			return new WP_Error( 'work_project_bundle_schema_unsupported' );
		}

		$record = self::normalize_record( $record );
		if ( is_wp_error( $record ) ) {
			return $record;
		}

		$project_id = PortableIdentities::local_id( PortableIdentities::PROJECT, $record['portable_key'] );
		$current = $project_id > 0 ? Projects::get( $project_id ) : null;
		if ( $project_id > 0 && null === $current ) {
			return new WP_Error( 'work_project_bundle_project_identity_corrupt' );
		}
		if ( null !== $current && WorkContext::INTERNAL !== (string) $current['work_context'] ) {
			return new WP_Error( 'work_project_bundle_customer_context_unsupported' );
		}

		foreach ( $record['work_items'] as $item ) {
			$work_item_id = PortableIdentities::local_id( PortableIdentities::WORK_ITEM, $item['portable_key'] );
			if ( $work_item_id <= 0 ) {
				continue;
			}
			$current_item = WorkItems::get( $work_item_id );
			if ( null === $current_item ) {
				return new WP_Error( 'work_project_bundle_item_identity_corrupt' );
			}
			if ( ! in_array( (string) $current_item['status'], WorkItemStatus::active(), true ) ) {
				return new WP_Error( 'work_project_bundle_terminal_item_collision' );
			}
			if ( null === $current || (int) $current_item['project_id'] !== $project_id ) {
				return new WP_Error( 'work_project_bundle_item_identity_collision' );
			}
		}

		if ( null === $current ) {
			$operation = Foundation::MODE_UPDATE_EXISTING === $mode
				? Foundation::OP_SKIP
				: Foundation::OP_CREATE;
		} elseif ( Foundation::MODE_CREATE_ONLY === $mode || self::same_bundle( $current, $record ) ) {
			$operation = Foundation::OP_SKIP;
		} else {
			$operation = Foundation::OP_UPDATE;
		}

		return [
			'operation' => $operation,
			'reference' => self::reference( $record['portable_key'] ),
			'payload'   => [
				'project_id' => $project_id,
				'record'     => $record,
			],
			'warnings'  => Foundation::OP_UPDATE === $operation
				? [ __( 'Existing active Work Items that are not present in this bundle are preserved.', 'core-blueprint-work' ) ]
				: [],
		];
	}

	public function apply_import( array $plan, array $context = [] ): array|WP_Error {
		unset( $context );
		$operation = (string) ( $plan['operation'] ?? '' );
		$payload   = $plan['payload'] ?? null;
		if ( ! is_array( $payload ) || ! isset( $payload['record'] ) || ! is_array( $payload['record'] ) ) {
			return new WP_Error( 'work_project_bundle_apply_contract' );
		}
		$record = self::normalize_record( $payload['record'] );
		if ( is_wp_error( $record ) ) {
			return $record;
		}
		if ( Foundation::OP_SKIP === $operation ) {
			return [ 'reference' => self::reference( $record['portable_key'] ) ];
		}

		$project_id = PortableIdentities::local_id( PortableIdentities::PROJECT, $record['portable_key'] );
		$created_project = false;
		if ( $project_id <= 0 ) {
			if ( Foundation::OP_CREATE !== $operation ) {
				return new WP_Error( 'work_project_bundle_apply_contract' );
			}
			$project = ProjectActions::create( self::project_input( $record ) );
			if ( is_wp_error( $project ) ) {
				return $project;
			}
			$project_id = (int) $project['id'];
			$created_project = true;
			$claimed = PortableIdentities::claim( PortableIdentities::PROJECT, $project_id, $record['portable_key'] );
			if ( is_wp_error( $claimed ) ) {
				wp_delete_post( $project_id, true );
				return $claimed;
			}
		} else {
			if ( Foundation::OP_UPDATE !== $operation ) {
				return new WP_Error( 'work_project_bundle_apply_contract' );
			}
			$project = ProjectActions::update( $project_id, self::project_input( $record ) );
			if ( is_wp_error( $project ) ) {
				return $project;
			}
		}

		foreach ( $record['work_items'] as $item_record ) {
			$result = self::apply_item( $project_id, $item_record );
			if ( is_wp_error( $result ) ) {
				if ( $created_project && 0 === WorkItemRepository::count_for_project( $project_id ) ) {
					wp_delete_post( $project_id, true );
				}
				return $result;
			}
		}

		return [ 'reference' => self::reference( $record['portable_key'] ) ];
	}

	/** @param array<string,mixed> $record @return array<string,mixed>|WP_Error */
	private static function normalize_record( array $record ): array|WP_Error {
		if ( ! self::exact_keys( $record, self::PROJECT_KEYS ) ) {
			return new WP_Error( 'work_project_bundle_invalid_shape' );
		}
		$portable_key = PortableIdentities::normalize_key( $record['portable_key'] ?? null );
		$title = isset( $record['title'] ) && is_string( $record['title'] ) ? sanitize_text_field( $record['title'] ) : '';
		$description = isset( $record['description'] ) && is_string( $record['description'] ) ? wp_kses_post( $record['description'] ) : null;
		$context = isset( $record['work_context'] ) && is_string( $record['work_context'] ) ? sanitize_key( $record['work_context'] ) : '';
		$starts = self::date( $record['starts_on'] ?? null );
		$due    = self::date( $record['due_on'] ?? null );
		if (
			'' === $portable_key || '' === $title || null === $description
			|| WorkContext::INTERNAL !== $context
			|| is_wp_error( $starts ) || is_wp_error( $due )
			|| ( '' !== $starts && '' !== $due && $due < $starts )
			|| ! is_array( $record['work_items'] ) || ! array_is_list( $record['work_items'] )
			|| count( $record['work_items'] ) > self::MAX_WORK_ITEMS
		) {
			return new WP_Error( 'work_project_bundle_invalid_project' );
		}

		$items = [];
		$keys  = [ $portable_key => true ];
		foreach ( $record['work_items'] as $item ) {
			if ( ! is_array( $item ) || array_is_list( $item ) ) {
				return new WP_Error( 'work_project_bundle_invalid_item' );
			}
			$item = self::normalize_item( $item );
			if ( is_wp_error( $item ) ) {
				return $item;
			}
			if ( isset( $keys[ $item['portable_key'] ] ) ) {
				return new WP_Error( 'work_project_bundle_duplicate_identity' );
			}
			$keys[ $item['portable_key'] ] = true;
			$items[] = $item;
		}

		return [
			'portable_key' => $portable_key,
			'title'        => $title,
			'description'  => $description,
			'work_context' => WorkContext::INTERNAL,
			'starts_on'    => $starts,
			'due_on'       => $due,
			'work_items'   => $items,
		];
	}

	/** @param array<string,mixed> $record @return array<string,mixed>|WP_Error */
	private static function normalize_item( array $record ): array|WP_Error {
		if ( ! self::exact_keys( $record, self::ITEM_KEYS ) ) {
			return new WP_Error( 'work_project_bundle_invalid_item_shape' );
		}
		$portable_key = PortableIdentities::normalize_key( $record['portable_key'] ?? null );
		$title = isset( $record['title'] ) && is_string( $record['title'] ) ? sanitize_text_field( $record['title'] ) : '';
		$description = isset( $record['description'] ) && is_string( $record['description'] ) ? wp_kses_post( $record['description'] ) : null;
		$priority = isset( $record['priority'] ) && is_string( $record['priority'] ) ? sanitize_key( $record['priority'] ) : '';
		$status = isset( $record['status'] ) && is_string( $record['status'] ) ? sanitize_key( $record['status'] ) : '';
		$estimated = $record['estimated_minutes'] ?? null;
		$scheduled = self::date( $record['scheduled_on'] ?? null );
		$due = self::date( $record['due_on'] ?? null );

		if (
			'' === $portable_key || '' === $title || null === $description
			|| ! WorkItemPriority::is_valid( $priority )
			|| ! is_int( $estimated ) || $estimated < 0 || $estimated > 525600
			|| ! in_array( $status, WorkItemStatus::active(), true )
			|| is_wp_error( $scheduled ) || is_wp_error( $due )
			|| ( '' !== $scheduled && '' !== $due && $due < $scheduled )
		) {
			return new WP_Error( 'work_project_bundle_invalid_item' );
		}

		return [
			'portable_key'      => $portable_key,
			'title'             => $title,
			'description'       => $description,
			'priority'          => $priority,
			'estimated_minutes' => $estimated,
			'scheduled_on'      => $scheduled,
			'due_on'            => $due,
			'status'            => $status,
		];
	}

	/** @param array<string,mixed> $record @return array<string,mixed> */
	private static function project_input( array $record ): array {
		return [
			'title'         => $record['title'],
			'description'   => $record['description'],
			'work_context'  => WorkContext::INTERNAL,
			'starts_on'     => $record['starts_on'],
			'due_on'        => $record['due_on'],
		];
	}

	/** @param array<string,mixed> $record */
	private static function apply_item( int $project_id, array $record ): array|WP_Error {
		$work_item_id = PortableIdentities::local_id( PortableIdentities::WORK_ITEM, $record['portable_key'] );
		$input = [
			'title'             => $record['title'],
			'description'       => $record['description'],
			'project_id'        => $project_id,
			'priority'          => $record['priority'],
			'estimated_minutes' => $record['estimated_minutes'],
			'scheduled_on'      => $record['scheduled_on'],
			'due_on'            => $record['due_on'],
		];

		if ( $work_item_id <= 0 ) {
			$item = WorkItemActions::create( $input );
			if ( is_wp_error( $item ) ) {
				return $item;
			}
			$work_item_id = (int) $item['id'];
			$claimed = PortableIdentities::claim( PortableIdentities::WORK_ITEM, $work_item_id, $record['portable_key'] );
			if ( is_wp_error( $claimed ) ) {
				wp_delete_post( $work_item_id, true );
				return $claimed;
			}
		} else {
			$current = WorkItems::get( $work_item_id );
			if ( null === $current || (int) $current['project_id'] !== $project_id || ! in_array( (string) $current['status'], WorkItemStatus::active(), true ) ) {
				return new WP_Error( 'work_project_bundle_item_identity_collision' );
			}
			$item = WorkItemActions::update( $work_item_id, $input );
			if ( is_wp_error( $item ) ) {
				return $item;
			}
		}

		$current = WorkItems::get( $work_item_id );
		if ( null === $current ) {
			return new WP_Error( 'work_project_bundle_item_unavailable' );
		}
		if ( (string) $current['status'] !== $record['status'] ) {
			$current = WorkItemActions::transition_status( $work_item_id, $record['status'] );
			if ( is_wp_error( $current ) ) {
				return $current;
			}
		}
		return $current;
	}

	/** @param array<string,mixed> $current @param array<string,mixed> $record */
	private static function same_bundle( array $current, array $record ): bool {
		if (
			(string) $current['title'] !== $record['title']
			|| (string) $current['description'] !== $record['description']
			|| (string) $current['work_context'] !== WorkContext::INTERNAL
			|| (string) $current['starts_on'] !== $record['starts_on']
			|| (string) $current['due_on'] !== $record['due_on']
		) {
			return false;
		}
		foreach ( $record['work_items'] as $item_record ) {
			$work_item_id = PortableIdentities::local_id( PortableIdentities::WORK_ITEM, $item_record['portable_key'] );
			$current_item = $work_item_id > 0 ? WorkItems::get( $work_item_id ) : null;
			if ( null === $current_item || (int) $current_item['project_id'] !== (int) $current['id'] || ! self::same_item( $current_item, $item_record ) ) {
				return false;
			}
		}
		return true;
	}

	/** @param array<string,mixed> $current @param array<string,mixed> $record */
	private static function same_item( array $current, array $record ): bool {
		return (string) $current['title'] === $record['title']
			&& (string) $current['description'] === $record['description']
			&& (string) $current['priority'] === $record['priority']
			&& (int) $current['estimated_minutes'] === $record['estimated_minutes']
			&& (string) ( $current['scheduled_on'] ?? '' ) === $record['scheduled_on']
			&& (string) ( $current['due_on'] ?? '' ) === $record['due_on']
			&& (string) $current['status'] === $record['status'];
	}

	/** @param array<string,mixed> $item @return array<string,mixed> */
	private static function export_item( string $portable_key, array $item ): array {
		return [
			'portable_key'      => $portable_key,
			'title'             => (string) $item['title'],
			'description'       => (string) $item['description'],
			'priority'          => (string) $item['priority'],
			'estimated_minutes' => (int) $item['estimated_minutes'],
			'scheduled_on'      => (string) ( $item['scheduled_on'] ?? '' ),
			'due_on'            => (string) ( $item['due_on'] ?? '' ),
			'status'            => (string) $item['status'],
		];
	}

	/** @param array<string,mixed> $record @param string[] $expected */
	private static function exact_keys( array $record, array $expected ): bool {
		$keys = array_keys( $record );
		sort( $keys, SORT_STRING );
		$expected = array_values( $expected );
		sort( $expected, SORT_STRING );
		return $keys === $expected;
	}

	private static function date( mixed $value ): string|WP_Error {
		if ( ! is_string( $value ) ) {
			return new WP_Error( 'work_project_bundle_invalid_date' );
		}
		if ( '' === $value ) {
			return '';
		}
		$date = \DateTimeImmutable::createFromFormat( '!Y-m-d', $value );
		return $date && $date->format( 'Y-m-d' ) === $value
			? $value
			: new WP_Error( 'work_project_bundle_invalid_date' );
	}

	private static function reference( string $portable_key ): string {
		return 'project:' . $portable_key;
	}

	private function __construct() {}
}
