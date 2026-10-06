<?php
declare(strict_types=1);

namespace CB\Work\Database;

use CoreBlueprint\Core\Database\SchemaRegistry;
use CB\Work\Content\PostTypes;
use CB\Work\Content\ProjectMeta;
use CB\Work\Content\WorkItemMeta;
use CB\Work\Domain\WorkContext;

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
				[ self::class, 'sources_table' ],
				[ self::class, 'billing_units_table' ],
				[ self::class, 'billing_snapshots_table' ],
				[ self::class, 'billing_external_refs_table' ],
				[ self::class, 'recurrence_rules_table' ],
				[ self::class, 'recurrence_assignments_table' ],
				[ self::class, 'recurrence_occurrences_table' ],
				[ self::class, 'time_entries_table' ],
				[ self::class, 'active_timers_table' ],
				[ self::class, 'portable_identities_table' ],
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

	public static function sources_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'cb_work_item_sources';
	}

	public static function billing_units_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'cb_work_billing_units';
	}

	public static function billing_snapshots_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'cb_work_billing_snapshots';
	}

	public static function billing_external_refs_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'cb_work_billing_external_refs';
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

	public static function portable_identities_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'cb_work_portable_identities';
	}

	public static function install(): bool {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();

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

		dbDelta( 'CREATE TABLE ' . self::sources_table() . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			work_item_id bigint(20) unsigned NULL,
			provider varchar(64) NOT NULL,
			source_type varchar(64) NOT NULL,
			external_id varchar(191) NOT NULL,
			claim_token varchar(64) NOT NULL DEFAULT '',
			claimed_at datetime NULL,
			created_at datetime NOT NULL,
			linked_at datetime NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY source_identity (provider,source_type,external_id),
			UNIQUE KEY work_item_source (work_item_id),
			KEY claim_state (work_item_id,claimed_at)
		) {$charset};" );

		dbDelta( 'CREATE TABLE ' . self::billing_units_table() . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			unit_type varchar(32) NOT NULL,
			unit_id bigint(20) unsigned NOT NULL,
			work_item_id bigint(20) unsigned NOT NULL,
			status varchar(32) NOT NULL DEFAULT 'ready',
			current_snapshot_id bigint(20) unsigned NOT NULL DEFAULT 0,
			ready_at datetime NOT NULL,
			linked_at datetime NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY source_unit (unit_type,unit_id),
			KEY work_item_status (work_item_id,status),
			KEY current_snapshot_id (current_snapshot_id)
		) {$charset};" );

		dbDelta( 'CREATE TABLE ' . self::billing_snapshots_table() . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			billing_unit_id bigint(20) unsigned NOT NULL,
			snapshot_version int unsigned NOT NULL DEFAULT 1,
			unit_type varchar(32) NOT NULL,
			unit_id bigint(20) unsigned NOT NULL,
			work_item_id bigint(20) unsigned NOT NULL,
			source_revision int unsigned NOT NULL DEFAULT 0,
			fingerprint char(64) NOT NULL,
			payload longtext NOT NULL,
			created_by bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY unit_version (billing_unit_id,snapshot_version),
			KEY source_unit (unit_type,unit_id),
			KEY work_item_id (work_item_id)
		) {$charset};" );

		dbDelta( 'CREATE TABLE ' . self::billing_external_refs_table() . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			billing_unit_id bigint(20) unsigned NOT NULL,
			snapshot_id bigint(20) unsigned NOT NULL,
			provider varchar(64) NOT NULL,
			resource_type varchar(64) NOT NULL,
			resource_id varchar(191) NOT NULL,
			display_reference varchar(190) NOT NULL DEFAULT '',
			status_projection varchar(64) NOT NULL DEFAULT '',
			linked_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY unit_resource (billing_unit_id,provider,resource_type,resource_id),
			KEY external_ref (provider,resource_type,resource_id),
			KEY snapshot_id (snapshot_id)
		) {$charset};" );

		dbDelta( 'CREATE TABLE ' . self::recurrence_rules_table() . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			title varchar(190) NOT NULL,
			description longtext NOT NULL,
			work_context varchar(16) NOT NULL DEFAULT '',
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
			KEY customer (customer_provider,customer_type,customer_id),
			KEY work_context (work_context)
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

		dbDelta( 'CREATE TABLE ' . self::portable_identities_table() . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			entity_type varchar(32) NOT NULL,
			local_id bigint(20) unsigned NOT NULL,
			portable_key char(36) NOT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY entity_local (entity_type,local_id),
			UNIQUE KEY portable_key (portable_key),
			KEY entity_type (entity_type)
		) {$charset};" );

		self::backfill_work_contexts();
		self::seed_default_work_types();
		return true;
	}

	private static function backfill_work_contexts(): void {
		global $wpdb;

		$wpdb->query(
			$wpdb->prepare(
				'UPDATE ' . self::recurrence_rules_table() . ' SET work_context = %s WHERE work_context = %s AND customer_provider <> %s AND customer_type <> %s AND customer_id <> %s',
				WorkContext::CUSTOMER,
				'',
				'',
				'',
				''
			)
		);

		self::backfill_post_context(
			PostTypes::PROJECT,
			ProjectMeta::WORK_CONTEXT,
			ProjectMeta::CUSTOMER_PROVIDER,
			ProjectMeta::CUSTOMER_TYPE,
			ProjectMeta::CUSTOMER_ID
		);
		self::backfill_post_context(
			PostTypes::WORK_ITEM,
			WorkItemMeta::WORK_CONTEXT,
			WorkItemMeta::CUSTOMER_PROVIDER,
			WorkItemMeta::CUSTOMER_TYPE,
			WorkItemMeta::CUSTOMER_ID
		);
	}

	private static function backfill_post_context( string $post_type, string $context_key, string $provider_key, string $type_key, string $id_key ): void {
		global $wpdb;
		$sql = $wpdb->prepare(
			"SELECT DISTINCT p.ID
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} cp ON cp.post_id = p.ID AND cp.meta_key = %s AND cp.meta_value <> ''
			INNER JOIN {$wpdb->postmeta} ct ON ct.post_id = p.ID AND ct.meta_key = %s AND ct.meta_value <> ''
			INNER JOIN {$wpdb->postmeta} ci ON ci.post_id = p.ID AND ci.meta_key = %s AND ci.meta_value <> ''
			LEFT JOIN {$wpdb->postmeta} wc ON wc.post_id = p.ID AND wc.meta_key = %s
			WHERE p.post_type = %s AND p.post_status <> 'trash' AND wc.post_id IS NULL",
			$provider_key,
			$type_key,
			$id_key,
			$context_key,
			$post_type
		);
		$ids = $wpdb->get_col( $sql );
		if ( ! is_array( $ids ) ) {
			return;
		}
		foreach ( array_map( 'absint', $ids ) as $post_id ) {
			if ( $post_id > 0 ) {
				update_post_meta( $post_id, $context_key, WorkContext::CUSTOMER );
			}
		}
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
