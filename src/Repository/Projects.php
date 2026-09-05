<?php
declare(strict_types=1);

namespace CB\Work\Repository;

use CB\Work\Database\Schema;

defined( 'ABSPATH' ) || exit;

final class Projects {
	/** @return array<int,array<string,mixed>> */
	public static function all( int $limit = 250 ): array {
		if ( ! self::schema_ready() ) {
			return [];
		}
		global $wpdb;
		$limit = max( 1, min( 500, $limit ) );
		$rows = $wpdb->get_results(
			$wpdb->prepare( 'SELECT * FROM ' . Schema::projects_table() . ' ORDER BY COALESCE(due_on, %s) ASC, title ASC, id ASC LIMIT %d', '9999-12-31', $limit ),
			ARRAY_A
		);
		return is_array( $rows ) ? $rows : [];
	}

	/** @return array<string,mixed>|null */
	public static function get( int $id ): ?array {
		if ( ! self::schema_ready() || $id <= 0 ) {
			return null;
		}
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . Schema::projects_table() . ' WHERE id = %d LIMIT 1', $id ),
			ARRAY_A
		);
		return is_array( $row ) ? $row : null;
	}

	public static function count(): int {
		if ( ! self::schema_ready() ) {
			return 0;
		}
		global $wpdb;
		return max( 0, (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . Schema::projects_table() ) );
	}

	/** @param array<string,mixed> $input */
	public static function create( array $input ): int {
		if ( ! self::schema_ready() ) {
			return 0;
		}
		global $wpdb;

		$title       = sanitize_text_field( (string) ( $input['title'] ?? '' ) );
		$description = sanitize_textarea_field( (string) ( $input['description'] ?? '' ) );
		$reference   = self::reference( $input );
		$starts_on   = self::date( (string) ( $input['starts_on'] ?? '' ) );
		$due_on      = self::date( (string) ( $input['due_on'] ?? '' ) );
		$created_by  = max( 0, (int) ( $input['created_by'] ?? 0 ) );

		if ( '' === $title || false === $reference ) {
			return 0;
		}
		if ( null !== $starts_on && null !== $due_on && $due_on < $starts_on ) {
			return 0;
		}

		$now = current_time( 'mysql', true );
		$ok = $wpdb->insert(
			Schema::projects_table(),
			[
				'title'             => $title,
				'description'       => $description,
				'customer_provider' => $reference['provider'],
				'customer_type'     => $reference['type'],
				'customer_id'       => $reference['id'],
				'starts_on'         => $starts_on,
				'due_on'            => $due_on,
				'created_by'        => $created_by,
				'created_at'        => $now,
				'updated_at'        => $now,
			],
			[ '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s' ]
		);
		if ( false === $ok ) {
			return 0;
		}
		$id = (int) $wpdb->insert_id;
		do_action( 'cb_work_project_created', $id, self::get( $id ) );
		return $id;
	}

	/** @param array<string,mixed> $input @return array{provider:string,type:string,id:string}|false */
	private static function reference( array $input ): array|false {
		$provider = substr( sanitize_key( (string) ( $input['customer_provider'] ?? '' ) ), 0, 64 );
		$type     = substr( sanitize_key( (string) ( $input['customer_type'] ?? '' ) ), 0, 64 );
		$id       = substr( sanitize_text_field( (string) ( $input['customer_id'] ?? '' ) ), 0, 191 );
		if ( '' === $provider && '' === $type && '' === $id ) {
			return [ 'provider' => '', 'type' => '', 'id' => '' ];
		}
		if ( '' === $provider || '' === $type || '' === $id ) {
			return false;
		}
		return [ 'provider' => $provider, 'type' => $type, 'id' => $id ];
	}

	private static function date( string $value ): ?string {
		$value = trim( $value );
		if ( '' === $value ) {
			return null;
		}
		$date = \DateTimeImmutable::createFromFormat( '!Y-m-d', $value );
		return $date && $date->format( 'Y-m-d' ) === $value ? $value : null;
	}

	private static function schema_ready(): bool {
		return defined( 'CB_WORK_SCHEMA_VERSION' )
			&& CB_WORK_SCHEMA_VERSION === (string) get_option( Schema::OPTION, '0' );
	}
}
