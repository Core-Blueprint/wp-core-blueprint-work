<?php
declare(strict_types=1);

namespace CB\Work\Admin;

use CB\Work\Capabilities;
use CB\Work\Domain\TimeRange;
use CB\Work\Repository\TimeEntries;
use CB\Work\Repository\WorkItems;
use CB\Work\Time\Access;

defined( 'ABSPATH' ) || exit;

final class TimeEntryList {
    /** Read-only table and filters; mutations are introduced separately in T2-B/C. */
    public static function render( bool $manager ): void {
        $state = TimeEntryListState::from_request( $_GET, $manager );
        $result = TimeEntries::query_completed( $state );
        $state['page'] = $result['page'];
        $current = (int) $result['page'];
        $pages = (int) $result['pages'];
        $total = (int) $result['total'];
        $from = $total > 0 ? ( $current - 1 ) * (int) $result['per_page'] + 1 : 0;
        $to = min( $total, $current * (int) $result['per_page'] );
        $has_filters = '' !== $state['search'] || '' !== $state['from'] || '' !== $state['to']
            || '' !== $state['source'] || ( $manager && $state['user_id'] > 0 )
            || TimeEntryListState::SORT_NEWEST !== $state['sort'];
        ?>
        <div class="cb-work-time-entries">
            <header class="cb-work-time-entries-head">
                <div>
                    <h2><?php esc_html_e( 'Time Entries', 'core-blueprint-work' ); ?></h2>
                    <p class="description"><?php esc_html_e( 'Review and correct recorded work time.', 'core-blueprint-work' ); ?></p>
                </div>
                <a class="button button-primary" href="<?php echo esc_url( Menu::time_url( [ 'view' => Time::VIEW_MANUAL ] ) ); ?>"><?php esc_html_e( 'Add Time Entry', 'core-blueprint-work' ); ?></a>
            </header>

            <form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="cb-work-time-entries-filters" role="search">
                <input type="hidden" name="page" value="<?php echo esc_attr( $manager ? Menu::TIME_SLUG : Menu::TOP_LEVEL_SLUG ); ?>">
                <input type="hidden" name="view" value="<?php echo esc_attr( Time::VIEW_ENTRIES ); ?>">
                <div class="cb-work-time-filter">
                    <label for="cb-work-te-search"><?php esc_html_e( 'Search Work Items', 'core-blueprint-work' ); ?></label>
                    <input type="search" id="cb-work-te-search" name="te_search" value="<?php echo esc_attr( $state['search'] ); ?>" placeholder="<?php esc_attr_e( 'Work Item title…', 'core-blueprint-work' ); ?>">
                </div>
                <div class="cb-work-time-filter">
                    <label for="cb-work-te-from"><?php esc_html_e( 'From', 'core-blueprint-work' ); ?></label>
                    <input type="date" id="cb-work-te-from" name="te_from" value="<?php echo esc_attr( $state['from'] ); ?>">
                </div>
                <div class="cb-work-time-filter">
                    <label for="cb-work-te-to"><?php esc_html_e( 'To', 'core-blueprint-work' ); ?></label>
                    <input type="date" id="cb-work-te-to" name="te_to" value="<?php echo esc_attr( $state['to'] ); ?>">
                </div>
                <div class="cb-work-time-filter">
                    <label for="cb-work-te-source"><?php esc_html_e( 'Source', 'core-blueprint-work' ); ?></label>
                    <select id="cb-work-te-source" name="te_source">
                        <option value=""><?php esc_html_e( 'All sources', 'core-blueprint-work' ); ?></option>
                        <option value="timer" <?php selected( $state['source'], 'timer' ); ?>><?php esc_html_e( 'Timer', 'core-blueprint-work' ); ?></option>
                        <option value="manual" <?php selected( $state['source'], 'manual' ); ?>><?php esc_html_e( 'Manual', 'core-blueprint-work' ); ?></option>
                    </select>
                </div>
                <?php if ( $manager ) : ?>
                <div class="cb-work-time-filter cb-work-time-filter--user">
                    <label for="cb-work-te-user"><?php esc_html_e( 'User', 'core-blueprint-work' ); ?></label>
                    <?php Pickers::assignee( 'te_user', 'cb-work-te-user', $state['user_id'] ); ?>
                </div>
                <?php endif; ?>
                <div class="cb-work-time-filter">
                    <label for="cb-work-te-sort"><?php esc_html_e( 'Sort by', 'core-blueprint-work' ); ?></label>
                    <select id="cb-work-te-sort" name="te_sort">
                        <?php foreach ( self::sort_labels() as $value => $label ) : ?>
                            <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $state['sort'], $value ); ?>><?php echo esc_html( $label ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="cb-work-time-filter-actions">
                    <button type="submit" class="button button-primary"><?php esc_html_e( 'Filter', 'core-blueprint-work' ); ?></button>
                    <?php if ( $has_filters ) : ?>
                        <a class="button" href="<?php echo esc_url( Menu::time_url( [ 'view' => Time::VIEW_ENTRIES ] ) ); ?>"><?php esc_html_e( 'Clear filters', 'core-blueprint-work' ); ?></a>
                    <?php endif; ?>
                </div>
            </form>

            <div class="cb-work-time-entries-summary" role="status">
                <span><?php
                    /* translators: 1: first visible entry, 2: last visible entry, 3: total matching entries. */
                    echo esc_html( sprintf( __( '%1$d–%2$d of %3$d Time entries', 'core-blueprint-work' ), $from, $to, $total ) );
                ?></span>
                <strong><?php
                    /* translators: %s: total recorded duration within all active filters. */
                    echo esc_html( sprintf( __( 'Total: %s', 'core-blueprint-work' ), self::duration( (int) $result['total_seconds'] ) ) );
                ?></strong>
            </div>

            <?php if ( [] === $result['items'] ) : ?>
                <div class="cb-work-time-entries-empty">
                    <span class="dashicons dashicons-clock" aria-hidden="true"></span>
                    <h3><?php esc_html_e( 'No matching Time entries', 'core-blueprint-work' ); ?></h3>
                    <p><?php echo esc_html( $has_filters ? __( 'Try adjusting your filters.', 'core-blueprint-work' ) : __( 'Start a timer or add your first Time entry.', 'core-blueprint-work' ) ); ?></p>
                    <div class="cb-work-time-entries-empty-actions">
                        <?php if ( $has_filters ) : ?><a class="button" href="<?php echo esc_url( Menu::time_url( [ 'view' => Time::VIEW_ENTRIES ] ) ); ?>"><?php esc_html_e( 'Clear filters', 'core-blueprint-work' ); ?></a><?php endif; ?>
                        <a class="button" href="<?php echo esc_url( Menu::time_url( [ 'view' => Time::VIEW_TIMER ] ) ); ?>"><?php esc_html_e( 'Timer', 'core-blueprint-work' ); ?></a>
                        <a class="button button-primary" href="<?php echo esc_url( Menu::time_url( [ 'view' => Time::VIEW_MANUAL ] ) ); ?>"><?php esc_html_e( 'Add Time Entry', 'core-blueprint-work' ); ?></a>
                    </div>
                </div>
            <?php else : ?>
                <div class="cb-work-time-entries-scroll">
                    <table class="widefat striped">
                        <thead>
                            <tr>
                                <th scope="col"><?php esc_html_e( 'When', 'core-blueprint-work' ); ?></th>
                                <th scope="col"><?php esc_html_e( 'Work Item', 'core-blueprint-work' ); ?></th>
                                <?php if ( $manager ) : ?><th scope="col"><?php esc_html_e( 'User', 'core-blueprint-work' ); ?></th><?php endif; ?>
                                <th scope="col"><?php esc_html_e( 'Duration', 'core-blueprint-work' ); ?></th>
                                <th scope="col"><?php esc_html_e( 'Source', 'core-blueprint-work' ); ?></th>
                                <th scope="col"><?php esc_html_e( 'Note', 'core-blueprint-work' ); ?></th>
                                <th scope="col"><?php esc_html_e( 'Actions', 'core-blueprint-work' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $result['items'] as $entry ) : ?>
                                <?php
                                $item = WorkItems::get( (int) $entry['work_item_id'] );
                                $user = $manager ? get_userdata( (int) $entry['user_id'] ) : null;
                                $parts = TimeRange::utc_to_local_parts( (string) $entry['started_at'] );
                                $when = trim( (string) ( $parts['date'] ?? '' ) . ' ' . (string) ( $parts['time'] ?? '' ) );
                                $item_title = (string) ( $item['title'] ?? sprintf( __( 'Work Item #%d', 'core-blueprint-work' ), (int) $entry['work_item_id'] ) );
                                ?>
                                <tr>
                                    <td class="cb-work-time-when"><?php echo esc_html( $when ); ?></td>
                                    <td class="cb-work-time-entry-title"><?php echo esc_html( $item_title ); ?></td>
                                    <?php if ( $manager ) : ?><td><?php echo esc_html( $user ? (string) $user->display_name : (string) $entry['user_id'] ); ?></td><?php endif; ?>
                                    <td class="cb-work-time-duration"><?php echo esc_html( self::duration( (int) $entry['duration_seconds'] ) ); ?></td>
                                    <td><?php echo esc_html( TimeEntries::SOURCE_TIMER === $entry['entry_source'] ? __( 'Timer', 'core-blueprint-work' ) : __( 'Manual', 'core-blueprint-work' ) ); ?></td>
                                    <td class="cb-work-time-note"><?php echo esc_html( (string) $entry['note'] ); ?></td>
                                    <td><?php if ( Access::can_edit_entry( $entry, (int) $entry['work_item_id'] ) ) : ?>
                                        <a class="button button-small" href="<?php echo esc_url( Menu::time_url( [ 'view' => Time::VIEW_MANUAL, 'entry_id' => (int) $entry['id'] ] ) ); ?>"><?php esc_html_e( 'Edit', 'core-blueprint-work' ); ?></a>
                                    <?php endif; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <?php if ( $pages > 1 ) : ?>
                <nav class="cb-work-time-entries-pagination" aria-label="<?php esc_attr_e( 'Time Entries pagination', 'core-blueprint-work' ); ?>">
                    <span><?php echo esc_html( sprintf( __( 'Page %1$d of %2$d', 'core-blueprint-work' ), $current, $pages ) ); ?></span>
                    <div>
                        <?php if ( $current > 1 ) : ?>
                            <a class="button" href="<?php echo esc_url( Menu::time_url( TimeEntryListState::url_args( $state, $current - 1 ) ) ); ?>"><?php esc_html_e( 'Previous', 'core-blueprint-work' ); ?></a>
                        <?php endif; ?>
                        <?php if ( $current < $pages ) : ?>
                            <a class="button" href="<?php echo esc_url( Menu::time_url( TimeEntryListState::url_args( $state, $current + 1 ) ) ); ?>"><?php esc_html_e( 'Next', 'core-blueprint-work' ); ?></a>
                        <?php endif; ?>
                    </div>
                </nav>
            <?php endif; ?>
        </div>
        <?php
    }

    /** @return array<string,string> */
    private static function sort_labels(): array {
        return [
            TimeEntryListState::SORT_NEWEST => __( 'Newest first', 'core-blueprint-work' ),
            TimeEntryListState::SORT_OLDEST => __( 'Oldest first', 'core-blueprint-work' ),
            TimeEntryListState::SORT_LONGEST => __( 'Longest first', 'core-blueprint-work' ),
            TimeEntryListState::SORT_SHORTEST => __( 'Shortest first', 'core-blueprint-work' ),
            TimeEntryListState::SORT_SOURCE => __( 'Source', 'core-blueprint-work' ),
        ];
    }

    public static function duration( int $seconds ): string {
        $seconds = max( 0, $seconds );
        return sprintf( '%02d:%02d:%02d', intdiv( $seconds, 3600 ), intdiv( $seconds % 3600, 60 ), $seconds % 60 );
    }

    private function __construct() {}
}
