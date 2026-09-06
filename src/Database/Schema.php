<?php
declare(strict_types=1);

namespace CB\Work\Database;

use CB\Core\Database\SchemaRegistry;
use CB\Work\Capabilities;

defined( 'ABSPATH' ) || exit;

final class Schema {
	public const OPTION = 'cb_work_db_version';

	public static function register(): void {
		SchemaRegistry::register( [
			'id'         => 'core-blueprint-work',
			'version'    => CB_WORK_SCHEMA_VERSION,
			'option_key' => self::OPTION,
			'tables'     => [
				[ self::class, 'tax_rates_table' ],
				[ self::class, 'work_types_table' ],
				[ self::class, 'assignments_table' ],
				[ self::class, 'relations_table' ],
				[ self::class, 'recurrence_rules_table' ],
				[ self::class, 'recurrence_assignments_table' ],
				[ self::class, 'recurrence_occurrences_table' ],
				[ self::class, 'time_entries_table' ],
				[ self::class, 'active_timers_table' ],
			],
			'install'    => [ self::class, 'install' ],
		] );
	}

	public static function tax_rates_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'cb_work_tax_rates';
	}

	public static function work_types_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'cb_work_types';
	}

	public static function assignments_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'cb_work_item_assignments';
	}

	public static function relations_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'cb_work_item_relations';
	}

	public static function recurrence_rules_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'cb_work_recurrence_rules';
	}

	public static function recurrence_assignments_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'cb_work_recurrence_rule_assignments';
	}

	public static function recurrence_occurrences_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'cb_work_recurrence_occurrences';
	}

	public static function time_entries_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'cb_work_time_entries';
	}

	public static function active_timers_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'cb_work_active_timers';
	}

	public static function install(): bool {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();
		$previous_version = (string) get_option( self::OPTION, '0' );

		dbDelta( 'CREATE TABLE ' . self::tax_rates_table() . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			code varchar(64) NOT NULL,
			label varchar(190) NOT NULL,
			country_code varchar(2) NOT NULL DEFAULT '',
			rate_bp int unsigned NOT NULL DEFAULT 0,
			is_active tinyint(1) unsigned NOT NULL DEFAULT 1,
			valid_from date NULL,
			valid_until date NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY code (code),
			KEY active (is_active),
			KEY country (country_code)
		) {$charset};" );

		dbDelta( 'CREATE TABLE ' . self::work_types_table() . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			code varchar(64) NOT NULL,
			label varchar(190) NOT NULL,
			is_active tinyint(1) unsigned NOT NULL DEFAULT 1,
			sort_order int unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY code (code),
			KEY active_sort (is_active,sort_order)
		) {$charset};" );

		dbDelta( 'CREATE TABLE ' . self::assignments_table() . " (
			work_item_id bigint(20) unsigned NOT NULL,
			user_id bigint(20) unsigned NOT NULL,
			assigned_at datetime NOT NULL,
			PRIMARY KEY  (work_item_id,user_id),
			KEY user_id (user_id)
		) {$charset};" );

		dbDelta( 'CREATE TABLE ' . self::relations_table() . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			work_item_id bigint(20) unsigned NOT NULL,
			provider varchar(64) NOT NULL,
			relation_type varchar(64) NOT NULL,
			external_id varchar(191) NOT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY relation (work_item_id,provider,relation_type,external_id),
			KEY work_item_id (work_item_id),
			KEY external_ref (provider,relation_type,external_id)
		) {$charset};" );

		dbDelta( 'CREATE TABLE ' . self::recurrence_rules_table() . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			title varchar(190) NOT NULL,
			description longtext NOT NULL,
			customer_provider varchar(64) NOT NULL DEFAULT '',
			customer_type varchar(64) NOT NULL DEFAULT '',
			customer_id varchar(191) NOT NULL DEFAULT '',
			project_id bigint(20) unsigned NOT NULL DEFAULT 0,
			service_id bigint(20) unsigned NOT NULL DEFAULT 0,
			work_type_id bigint(20) unsigned NOT NULL DEFAULT 0,
			priority varchar(32) NOT NULL DEFAULT 'normal',
			estimated_minutes int unsigned NOT NULL DEFAULT 0,
			billing_disposition varchar(32) NOT NULL DEFAULT '',
			frequency varchar(16) NOT NULL,
			interval_count smallint unsigned NOT NULL DEFAULT 1,
			start_on date NOT NULL,
			end_on date NULL,
			next_occurrence_on date NULL,
			create_ahead_days smallint unsigned NOT NULL DEFAULT 14,
			due_offset_days smallint unsigned NOT NULL DEFAULT 0,
			is_active tinyint(1) unsigned NOT NULL DEFAULT 1,
			created_by bigint(20) unsigned NOT NULL DEFAULT 0,
			updated_by bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY active_next (is_active,next_occurrence_on),
			KEY project_id (project_id),
			KEY service_id (service_id),
			KEY work_type_id (work_type_id),
			KEY customer (customer_provider,customer_type,customer_id)
		) {$charset};" );

		dbDelta( 'CREATE TABLE ' . self::recurrence_assignments_table() . " (
			rule_id bigint(20) unsigned NOT NULL,
			user_id bigint(20) unsigned NOT NULL,
			assigned_at datetime NOT NULL,
			PRIMARY KEY  (rule_id,user_id),
			KEY user_id (user_id)
		) {$charset};" );

		dbDelta( 'CREATE TABLE ' . self::recurrence_occurrences_table() . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			rule_id bigint(20) unsigned NOT NULL,
			occurrence_on date NOT NULL,
			work_item_id bigint(20) unsigned NOT NULL DEFAULT 0,
			claim_token varchar(64) NOT NULL DEFAULT '',
			claimed_at datetime NULL,
			attempt_count int unsigned NOT NULL DEFAULT 0,
			last_error varchar(190) NOT NULL DEFAULT '',
			created_at datetime NOT NULL,
			generated_at datetime NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY rule_occurrence (rule_id,occurrence_on),
			KEY work_item_id (work_item_id),
			KEY claim_state (work_item_id,claimed_at)
		) {$charset};" );

		dbDelta( 'CREATE TABLE ' . self::time_entries_table() . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			work_item_id bigint(20) unsigned NOT NULL,
			user_id bigint(20) unsigned NOT NULL,
			entry_source varchar(16) NOT NULL DEFAULT 'manual',
			started_at datetime NOT NULL,
			ended_at datetime NULL,
			duration_seconds int unsigned NOT NULL DEFAULT 0,
			note text NOT NULL,
			revision int unsigned NOT NULL DEFAULT 1,
			created_by bigint(20) unsigned NOT NULL DEFAULT 0,
			updated_by bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY work_item_time (work_item_id,started_at),
			KEY user_time (user_id,started_at),
			KEY open_entry (user_id,ended_at)
		) {$charset};" );

		dbDelta( 'CREATE TABLE ' . self::active_timers_table() . " (
			user_id bigint(20) unsigned NOT NULL,
			time_entry_id bigint(20) unsigned NOT NULL,
			started_at datetime NOT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (user_id),
			UNIQUE KEY time_entry_id (time_entry_id)
		) {$charset};" );

		/*
		 * D1.1/D1.2 are deliberate pre-v1 architecture corrections. Projects and
		 * Work Items are canonical WordPress content. Transitional Project/Work
		 * Item tables are destroyed instead of preserved through migration,
		 * fallback or dual-read compatibility. Old assignment/relation rows point
		 * at disposable relational Work Item IDs and are cleared exactly once
		 * when crossing into schema 1.3.
		 */
		if ( false === $wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'cb_work_projects' ) ) {
			return false;
		}
		if ( false === $wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'cb_work_items' ) ) {
			return false;
		}
		if ( version_compare( $previous_version, '1.3', '<' ) ) {
			if ( false === $wpdb->query( 'DELETE FROM ' . self::assignments_table() ) ) {
				return false;
			}
			if ( false === $wpdb->query( 'DELETE FROM ' . self::relations_table() ) ) {
				return false;
			}
		}

		/* Existing rc1 installs receive the E3 capability during explicit schema upgrade. */
		if ( version_compare( $previous_version, '1.6', '<' ) ) {
			Capabilities::install();
		}

		self::seed_default_work_types();
		return true;
	}

	private static function seed_default_work_types(): void {
		global $wpdb;
		$defaults = [
			'support'        => 'Support',
			'design'         => 'Design',
			'development'    => 'Development',
			'consultancy'    => 'Consultancy',
			'maintenance'    => 'Maintenance',
			'content'        => 'Content',
			'administration' => 'Administration',
		];
		$now = current_time( 'mysql', true );
		$order = 10;
		foreach ( $defaults as $code => $label ) {
			$exists = $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . self::work_types_table() . ' WHERE code = %s LIMIT 1', $code ) );
			if ( ! $exists ) {
				$wpdb->insert(
					self::work_types_table(),
					[
						'code'       => $code,
						'label'      => $label,
						'is_active'  => 1,
						'sort_order' => $order,
						'created_at' => $now,
						'updated_at' => $now,
					],
					[ '%s', '%s', '%d', '%d', '%s', '%s' ]
				);
			}
			$order += 10;
		}
	}
}
