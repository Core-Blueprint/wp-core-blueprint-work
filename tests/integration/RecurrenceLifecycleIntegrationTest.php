<?php
declare(strict_types=1);

use CB\Work\Database\Schema;
use CB\Work\Repository\RecurrenceRules;

/** Real WP/MariaDB lifecycle regression, disposable Work test database only. */
final class RecurrenceLifecycleIntegrationTest extends WP_UnitTestCase {
	/** @var list<string> */
	private array $tables = [];
	private int $actor = 0;

	public function set_up(): void {
		if ( ! defined( 'DB_NAME' ) || 'core_blueprint_work_test' !== (string) DB_NAME
			|| ! defined( 'DB_HOST' ) || '127.0.0.1:3307' !== (string) DB_HOST ) {
			self::fail( 'Recurrence Lifecycle Golden requires the disposable Work test database.' );
		}
		parent::set_up();
		$this->actor = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $this->actor );
		update_option( Schema::OPTION, CB_WORK_SCHEMA_VERSION );
		global $wpdb;
		$rules = Schema::recurrence_rules_table();
		$assignments = Schema::recurrence_assignments_table();
		$occurrences = Schema::recurrence_occurrences_table();
		self::assertNotFalse( $wpdb->query( "CREATE TEMPORARY TABLE {$rules} (
			id bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
			title varchar(190) NOT NULL,
			description longtext NOT NULL,
			work_context varchar(16) NOT NULL DEFAULT '',
			customer_provider varchar(64) NOT NULL DEFAULT '',
			customer_type varchar(64) NOT NULL DEFAULT '',
			customer_id varchar(191) NOT NULL DEFAULT '',
			project_id bigint unsigned NOT NULL DEFAULT 0,
			service_id bigint unsigned NOT NULL DEFAULT 0,
			work_type_id bigint unsigned NOT NULL DEFAULT 0,
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
			trashed_at datetime NULL,
			trashed_by bigint unsigned NOT NULL DEFAULT 0,
			created_by bigint unsigned NOT NULL DEFAULT 0,
			updated_by bigint unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			UNIQUE KEY next_rule (id)
		) ENGINE=InnoDB" ) );
		$this->tables[] = $rules;
		self::assertNotFalse( $wpdb->query( "CREATE TEMPORARY TABLE {$assignments} (
			rule_id bigint unsigned NOT NULL,
			user_id bigint unsigned NOT NULL,
			assigned_at datetime NOT NULL,
			PRIMARY KEY (rule_id,user_id)
		) ENGINE=InnoDB" ) );
		$this->tables[] = $assignments;
		self::assertNotFalse( $wpdb->query( "CREATE TEMPORARY TABLE {$occurrences} (
			id bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
			rule_id bigint unsigned NOT NULL,
			occurrence_on date NOT NULL,
			work_item_id bigint unsigned NOT NULL DEFAULT 0,
			claim_token varchar(64) NOT NULL DEFAULT '',
			claimed_at datetime NULL,
			attempt_count int unsigned NOT NULL DEFAULT 0,
			last_error varchar(190) NOT NULL DEFAULT '',
			created_at datetime NOT NULL,
			generated_at datetime NULL,
			UNIQUE KEY rule_occurrence (rule_id, occurrence_on)
		) ENGINE=InnoDB" ) );
		$this->tables[] = $occurrences;
	}

	public function tear_down(): void {
		global $wpdb;
		foreach ( array_reverse( $this->tables ) as $table ) {
			$wpdb->query( 'DROP TEMPORARY TABLE IF EXISTS ' . $table );
		}
		$this->tables = [];
		parent::tear_down();
	}

	private function seed( bool $active = true, bool $assigned = false ): int {
		$id = RecurrenceRules::create( [
			'title' => 'B4 Recurring lifecycle',
			'work_context' => 'internal',
			'frequency' => 'weekly',
			'interval_count' => 1,
			'start_on' => '2026-10-14',
			'is_active' => $active,
			'created_by' => $this->actor,
			'updated_by' => $this->actor,
			'assigned_user_ids' => $assigned ? [ $this->actor ] : [],
		] );
		self::assertGreaterThan( 0, $id );
		return $id;
	}

	public function test_trash_excludes_generator_restore_stays_inactive(): void {
		$id = $this->seed();
		self::assertNotEmpty( RecurrenceRules::due_for_generation( '2026-10-14' ) );
		self::assertTrue( RecurrenceRules::trash( $id, $this->actor ) );
		$trashed = RecurrenceRules::get( $id );
		self::assertIsArray( $trashed );
		self::assertTrue( $trashed['is_trashed'] );
		self::assertFalse( $trashed['is_active'] );
		self::assertSame( $this->actor, $trashed['trashed_by'] );
		self::assertCount( 0, RecurrenceRules::all() );
		self::assertSame( 0, RecurrenceRules::search_page()['total'] );
		self::assertSame( 1, RecurrenceRules::search_page( [ 'trash' => true ] )['total'] );
		self::assertSame( [], RecurrenceRules::due_for_generation( '2026-10-14' ) );
		self::assertSame( 0, RecurrenceRules::reserve_next_occurrence( $id ) );
		self::assertFalse( RecurrenceRules::update( $id, [ 'is_active' => true ] ) );
		self::assertTrue( RecurrenceRules::restore( $id, $this->actor ) );
		$restored = RecurrenceRules::get( $id );
		self::assertIsArray( $restored );
		self::assertFalse( $restored['is_trashed'] );
		self::assertFalse( $restored['is_active'], 'Restore must not trigger a background generator.' );
		self::assertSame( [], RecurrenceRules::due_for_generation( '2026-10-14' ) );
		self::assertSame( 1, (int) $GLOBALS['wpdb']->get_var( 'SELECT @@in_transaction' ) );
	}

	public function test_delete_permanently_removes_empty_trashed_rule_and_assignments_only(): void {
		$id = $this->seed( false, true );
		global $wpdb;
		self::assertCount( 1, RecurrenceRules::assignments( $id ) );
		self::assertFalse( RecurrenceRules::delete_permanently( $id ), 'Rule must first be moved to Trash.' );
		self::assertTrue( RecurrenceRules::trash( $id, $this->actor ) );
		self::assertTrue( RecurrenceRules::delete_permanently( $id ) );
		self::assertNull( RecurrenceRules::get( $id ) );
		self::assertSame( 0, (int) $wpdb->get_var( $wpdb->prepare(
			'SELECT COUNT(*) FROM ' . Schema::recurrence_assignments_table() . ' WHERE rule_id = %d', $id
		) ) );
		self::assertSame( 1, (int) $wpdb->get_var( 'SELECT @@in_transaction' ) );
	}

	public function test_occurrence_ledger_always_blocks_permanent_delete(): void {
		$id = $this->seed();
		$occurrence_id = RecurrenceRules::reserve_next_occurrence( $id );
		self::assertGreaterThan( 0, $occurrence_id );
		self::assertTrue( RecurrenceRules::trash( $id, $this->actor ) );
		self::assertFalse( RecurrenceRules::delete_permanently( $id ) );
		self::assertNotNull( RecurrenceRules::get( $id ) );
		self::assertNotNull( RecurrenceRules::occurrence( $occurrence_id ) );
		self::assertTrue( RecurrenceRules::restore( $id, $this->actor ) );
		self::assertFalse( RecurrenceRules::get( $id )['is_active'] );
	}

	public function test_broken_assignment_table_rolls_back_permanent_deletion(): void {
		$id = $this->seed( false );
		self::assertTrue( RecurrenceRules::trash( $id, $this->actor ) );
		global $wpdb;
		// Inject a single SQL failure without exposing the persistent schema
		// from underneath a dropped temporary table.
		$reject = static function ( string $sql ): string {
			if ( str_starts_with( ltrim( $sql ), 'DELETE' ) && str_contains( $sql, Schema::recurrence_assignments_table() ) ) {
				return 'SELECT * FROM cb_work_missing_recurrence_lifecycle_fixture';
			}
			return $sql;
		};
		add_filter( 'query', $reject );
		$wpdb->suppress_errors( true );
		try {
			self::assertFalse( RecurrenceRules::delete_permanently( $id ) );
		} finally {
			$wpdb->suppress_errors( false );
			remove_filter( 'query', $reject );
		}
		self::assertNotNull( RecurrenceRules::get( $id ) );
		self::assertSame( 1, (int) $wpdb->get_var( 'SELECT @@in_transaction' ) );
	}
}
