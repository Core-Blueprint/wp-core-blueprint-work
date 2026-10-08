<?php
declare(strict_types=1);

namespace CB\Work\Admin;

use CB\Work\Domain\TimeRange;

defined( 'ABSPATH' ) || exit;

/**
 * URL-backed, validated read-only Time Entries list state.
 * Dates are site-local inclusive calendar dates, converted to UTC bounds.
 */
final class TimeEntryListState {
    public const SORT_NEWEST = 'newest';
    public const SORT_OLDEST = 'oldest';
    public const SORT_LONGEST = 'longest';
    public const SORT_SHORTEST = 'shortest';
    public const SORT_SOURCE = 'source';
    private const SORTS = [ self::SORT_NEWEST, self::SORT_OLDEST, self::SORT_LONGEST, self::SORT_SHORTEST, self::SORT_SOURCE ];
    private const SOURCES = [ 'manual', 'timer' ];

    /** @param array<string,mixed> $request
     * @return array{search:string,from:string,to:string,source:string,user_id:int,is_manager:bool,sort:string,page:int,per_page:int,from_utc:string,to_utc:string}
     */
    public static function from_request( array $request, bool $manager ): array {
        $source = self::key( $request['te_source'] ?? '' );
        $sort = self::key( $request['te_sort'] ?? self::SORT_NEWEST );
        $from = self::date( $request['te_from'] ?? '' );
        $to = self::date( $request['te_to'] ?? '' );

        $from_utc = '' !== $from ? (string) TimeRange::local_to_utc( $from, '00:00' ) : '';
        $to_utc = '';
        if ( '' !== $to ) {
            $next = \DateTimeImmutable::createFromFormat( '!Y-m-d', $to, wp_timezone() );
            if ( false !== $next ) {
                $to_utc = (string) TimeRange::local_to_utc( $next->modify( '+1 day' )->format( 'Y-m-d' ), '00:00' );
            }
        }

        return [
            'search'   => self::search_text( $request['te_search'] ?? '' ),
            'from'     => $from,
            'to'       => $to,
            'source'   => in_array( $source, self::SOURCES, true ) ? $source : '',
            'user_id'  => $manager ? absint( self::scalar( $request['te_user'] ?? '' ) ) : get_current_user_id(),
            'is_manager' => $manager,
            'sort'     => in_array( $sort, self::SORTS, true ) ? $sort : self::SORT_NEWEST,
            'page'     => max( 1, min( 1000000, absint( self::scalar( $request['te_page'] ?? 1 ) ) ) ),
            'per_page' => 25,
            'from_utc' => $from_utc,
            'to_utc'   => $to_utc,
        ];
    }

    /** @param array<string,mixed> $state @return array<string,string|int> */
    public static function url_args( array $state, int $page = 0 ): array {
        $args = [ 'view' => Time::VIEW_ENTRIES ];
        foreach ( [ 'search' => 'te_search', 'from' => 'te_from', 'to' => 'te_to', 'source' => 'te_source', 'sort' => 'te_sort' ] as $field => $key ) {
            if ( ! empty( $state[ $field ] ) && ! ( 'sort' === $field && self::SORT_NEWEST === $state[ $field ] ) ) {
                $args[ $key ] = (string) $state[ $field ];
            }
        }
        if ( ! empty( $state['user_id'] ) && ! empty( $state['is_manager'] ) ) {
            $args['te_user'] = (int) $state['user_id'];
        }
        $page = $page > 0 ? $page : (int) ( $state['page'] ?? 1 );
        if ( $page > 1 ) {
            $args['te_page'] = $page;
        }
        return $args;
    }

    private static function scalar( mixed $value ): string {
        return is_scalar( $value ) ? (string) $value : '';
    }

    private static function key( mixed $value ): string {
        return sanitize_key( self::scalar( $value ) );
    }

    private static function search_text( mixed $value ): string {
        $text = sanitize_text_field( wp_unslash( self::scalar( $value ) ) );
        return function_exists( 'mb_substr' ) ? mb_substr( $text, 0, 120 ) : substr( $text, 0, 120 );
    }

    private static function date( mixed $value ): string {
        $value = self::scalar( $value );
        if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m ) ) {
            return '';
        }
        return checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ? $value : '';
    }

    private function __construct() {}
}
