<?php
declare(strict_types=1);

/** Runtime guard tests for recurrence deletion without a WordPress database. */
defined( 'ABSPATH' ) || define( 'ABSPATH', '/tmp/wp/' );
defined( 'CB_WORK_SCHEMA_VERSION' ) || define( 'CB_WORK_SCHEMA_VERSION', '2.0' );
defined( 'ARRAY_A' ) || define( 'ARRAY_A', 'ARRAY_A' );

function get_option( string $name, mixed $default = null ): mixed {
    return 'cb_work_db_version' === $name ? CB_WORK_SCHEMA_VERSION : $default;
}

require dirname( __DIR__ ) . '/src/Database/Schema.php';
require dirname( __DIR__ ) . '/src/Repository/RecurrenceRules.php';

final class FakeRecurrenceDeleteDatabase {
    public string $prefix = 'wp_';
    public ?array $rule = [ 'id' => 11, 'is_active' => 0 ];
    public bool $history = false;
    public bool $fail_assignments = false;
    public bool $fail_rule = false;
    public bool $fail_commit = false;
    public array $queries = [];
    public array $deletes = [];

    public function prepare( string $query, mixed ...$args ): string {
        return vsprintf( $query, $args );
    }

    public function query( string $query ): int|false {
        $this->queries[] = $query;
        return 'COMMIT' === $query && $this->fail_commit ? false : 0;
    }

    public function get_row( string $query, string $format ): ?array {
        if ( ! str_contains( $query, 'FOR UPDATE' ) ) {
            throw new RuntimeException( 'Rule deletion must lock the row.' );
        }
        return $this->rule;
    }

    public function get_var( string $query ): ?int {
        if ( ! str_contains( $query, 'cb_work_recurrence_occurrences' ) ) {
            throw new RuntimeException( 'Rule deletion must check occurrence history.' );
        }
        return $this->history ? 1 : null;
    }

    public function delete( string $table, array $where, array $formats ): int|false {
        $this->deletes[] = [ $table, $where ];
        if ( str_ends_with( $table, 'cb_work_recurrence_rule_assignments' ) ) {
            return $this->fail_assignments ? false : 1;
        }
        if ( str_ends_with( $table, 'cb_work_recurrence_rules' ) ) {
            return $this->fail_rule ? false : 1;
        }
        throw new RuntimeException( 'Unexpected deletion table: ' . $table );
    }
}

function verify_delete( string $label, FakeRecurrenceDeleteDatabase $db, bool $expected, bool $expect_mutation ): void {
    $GLOBALS['wpdb'] = $db;
    $actual = \CB\Work\Repository\RecurrenceRules::delete_draft( 11 );
    if ( $actual !== $expected ) {
        throw new RuntimeException( $label . ': unexpected deletion result.' );
    }
    $mutated = [] !== $db->deletes;
    if ( $mutated !== $expect_mutation ) {
        throw new RuntimeException( $label . ': unexpected database mutation.' );
    }
    if ( ! in_array( 'START TRANSACTION', $db->queries, true ) ) {
        throw new RuntimeException( $label . ': missing transaction.' );
    }
    $ending = end( $db->queries );
    if ( $ending !== ( $expected ? 'COMMIT' : 'ROLLBACK' ) ) {
        throw new RuntimeException( $label . ': incorrect transaction outcome.' );
    }
}

$active = new FakeRecurrenceDeleteDatabase();
$active->rule['is_active'] = 1;
verify_delete( 'active rule', $active, false, false );

$historical = new FakeRecurrenceDeleteDatabase();
$historical->history = true;
verify_delete( 'historical rule', $historical, false, false );

$missing = new FakeRecurrenceDeleteDatabase();
$missing->rule = null;
verify_delete( 'missing rule', $missing, false, false );

$failed_assignments = new FakeRecurrenceDeleteDatabase();
$failed_assignments->fail_assignments = true;
verify_delete( 'assignment delete failure', $failed_assignments, false, true );

$failed_rule = new FakeRecurrenceDeleteDatabase();
$failed_rule->fail_rule = true;
verify_delete( 'rule delete failure', $failed_rule, false, true );

$unused = new FakeRecurrenceDeleteDatabase();
verify_delete( 'unused inactive rule', $unused, true, true );
if ( 2 !== count( $unused->deletes )
    || ! str_ends_with( $unused->deletes[0][0], 'cb_work_recurrence_rule_assignments' )
    || ! str_ends_with( $unused->deletes[1][0], 'cb_work_recurrence_rules' )
    || 0 !== $unused->deletes[1][1]['is_active'] ) {
    throw new RuntimeException( 'Unused rule: unsafe delete order or missing inactive guard.' );
}

echo "Recurring Work deletion guard runtime passed.\n";
