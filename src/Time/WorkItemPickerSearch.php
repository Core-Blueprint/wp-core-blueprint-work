<?php
declare(strict_types=1);

namespace CB\Work\Time;

use CB\Work\Capabilities;
use CB\Work\Content\PostTypes;
use CB\Work\Database\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Read-only, scoped, bounded Work Item title search for Time's async picker.
 *
 * Deliberately independent from the operational WorkItems::search() query:
 * that path materializes all Work Item IDs to sort large workspaces, while
 * this lookup needs at most 20 id/title pairs.
 */
final class WorkItemPickerSearch {
    private const LIMIT = 20;
    private const MAX_SEARCH_BYTES = 80;

    /** @return array<int,array{id:int,label:string,meta:string}> */
    public static function results( string $term, int $actor_id, bool $manager ): array {
        if ( $actor_id <= 0
            || ! defined( 'CB_WORK_SCHEMA_VERSION' )
            || CB_WORK_SCHEMA_VERSION !== (string) get_option( Schema::OPTION, '0' )
            || ( ! $manager && ( ! current_user_can( Capabilities::TRACK_TIME ) || get_current_user_id() !== $actor_id ) )
        ) {
            return [];
        }

        $term = trim( $term );
        if ( strlen( $term ) < 2 ) {
            return [];
        }
        $term = substr( $term, 0, self::MAX_SEARCH_BYTES );

        global $wpdb;
        $statuses = [ 'publish', 'draft', 'pending', 'private', 'future' ];
        $placeholders = implode( ', ', array_fill( 0, count( $statuses ), '%s' ) );
        $join = $manager
            ? ''
            : ' INNER JOIN ' . Schema::assignments_table() . ' AS wa ON wa.work_item_id = p.ID AND wa.user_id = %d';
        $args = $manager ? [] : [ $actor_id ];
        $args[] = PostTypes::WORK_ITEM;
        array_push( $args, ...$statuses );
        $args[] = '%' . $wpdb->esc_like( $term ) . '%';
        $args[] = self::LIMIT;

        $sql = 'SELECT p.ID, p.post_title FROM ' . $wpdb->posts . ' AS p'
            . $join
            . ' WHERE p.post_type = %s AND p.post_status IN (' . $placeholders . ')'
            . ' AND p.post_title LIKE %s'
            . ' ORDER BY p.post_title ASC, p.ID ASC LIMIT %d';
        $rows = $wpdb->get_results( $wpdb->prepare( $sql, ...$args ), ARRAY_A );

        if ( ! is_array( $rows ) ) {
            return [];
        }
        return array_map(
            static fn( array $row ): array => [
                'id' => (int) $row['ID'],
                'label' => (string) $row['post_title'],
                'meta' => '#' . (int) $row['ID'],
            ],
            $rows
        );
    }

    private function __construct() {}
}
