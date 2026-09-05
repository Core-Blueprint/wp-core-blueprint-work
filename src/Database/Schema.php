<?php
declare(strict_types=1);

namespace CB\Work\Database;

use CB\Core\Database\SchemaRegistry;

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
				[ self::class, 'projects_table' ],
				[ self::class, 'work_types_table' ],
				[ self::class, 'work_items_table' ],
				[ self::class, 'assignments_table' ],
				[ self::class, 'relations_table' ],
			],
			'install'    => [ self::class, 'install' ],
		] );
	}

	public static function tax_rates_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'cb_work_tax_rates';
	}

	public static function projects_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'cb_work_projects';
	}

	public static function work_types_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'cb_work_types';
	}

	public static function work_items_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'cb_work_items';
	}

	public static function assignments_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'cb_work_item_assignments';
	}

	public static function relations_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'cb_work_item_relations';
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

		dbDelta( 'CREATE TABLE ' . self::projects_table() . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			title varchar(190) NOT NULL,
			description longtext NOT NULL,
			customer_provider varchar(64) NOT NULL DEFAULT '',
			customer_type varchar(64) NOT NULL DEFAULT '',
			customer_id varchar(191) NOT NULL DEFAULT '',
			starts_on date NULL,
			due_on date NULL,
			created_by bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY customer (customer_provider,customer_type,customer_id),
			KEY due_on (due_on)
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

		dbDelta( 'CREATE TABLE ' . self::work_items_table() . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			title varchar(190) NOT NULL,
			description longtext NOT NULL,
			customer_provider varchar(64) NOT NULL DEFAULT '',
			customer_type varchar(64) NOT NULL DEFAULT '',
			customer_id varchar(191) NOT NULL DEFAULT '',
			project_id bigint(20) unsigned NULL,
			service_id bigint(20) unsigned NULL,
			work_type_id bigint(20) unsigned NULL,
			priority varchar(16) NOT NULL DEFAULT 'normal',
			scheduled_on date NULL,
			due_on date NULL,
			status varchar(32) NOT NULL DEFAULT 'planned',
			billing_disposition varchar(32) NOT NULL DEFAULT '',
			completed_at datetime NULL,
			completed_by bigint(20) unsigned NULL,
			created_by bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY status (status),
			KEY priority (priority),
			KEY scheduled_on (scheduled_on),
			KEY due_on (due_on),
			KEY project_id (project_id),
			KEY service_id (service_id),
			KEY work_type_id (work_type_id),
			KEY customer (customer_provider,customer_type,customer_id)
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
