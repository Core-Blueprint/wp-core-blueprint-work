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
			'tables'     => [ [ self::class, 'tax_rates_table' ] ],
			'install'    => [ self::class, 'install' ],
		] );
	}

	public static function tax_rates_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'cb_work_tax_rates';
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

		return true;
	}
}
