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
        $user_filter_active = $manager && $state['user_id'] > 0;
        $advanced_active = $user_filter_active || TimeEntryListState::SORT_NEWEST !== $state['sort'];
        $quick_edit_raw = $_GET['te_edit'] ?? '';
        $quick_edit_id = is_scalar( $quick_edit_raw ) && ctype_digit( (string) $quick_edit_raw )
            ? (int) $quick_edit_raw
            : 0;
        ?>
        <div class="cb-work-time-entries" data-cb-work-time-async-error="<?php echo esc_attr( __( 'An error occurred. Please try again.' ) ); ?>">
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
                <div class="cb-work-time-entries-primary-filters">
                    <div class="cb-work-time-filter cb-work-time-filter--search">
                        <label for="cb-work-te-search"><?php esc_html_e( 'Search Work Items', 'core-blueprint-work' ); ?></label>
                        <input type="search" id="cb-work-te-search" name="te_search" value="<?php echo esc_attr( $state['search'] ); ?>" placeholder="<?php esc_attr_e( 'Work Item title…', 'core-blueprint-work' ); ?>">
                    </div>
                    <div class="cb-work-time-filter cb-work-time-filter--from">
                        <label for="cb-work-te-from"><?php esc_html_e( 'From', 'core-blueprint-work' ); ?></label>
                        <input type="date" id="cb-work-te-from" name="te_from" value="<?php echo esc_attr( $state['from'] ); ?>">
                    </div>
                    <div class="cb-work-time-filter cb-work-time-filter--to">
                        <label for="cb-work-te-to"><?php esc_html_e( 'To', 'core-blueprint-work' ); ?></label>
                        <input type="date" id="cb-work-te-to" name="te_to" value="<?php echo esc_attr( $state['to'] ); ?>">
                    </div>
                    <div class="cb-work-time-filter cb-work-time-filter--source">
                        <label for="cb-work-te-source"><?php esc_html_e( 'Source', 'core-blueprint-work' ); ?></label>
                        <select id="cb-work-te-source" name="te_source">
                            <option value=""><?php esc_html_e( 'All sources', 'core-blueprint-work' ); ?></option>
                            <option value="timer" <?php selected( $state['source'], 'timer' ); ?>><?php esc_html_e( 'Timer', 'core-blueprint-work' ); ?></option>
                            <option value="manual" <?php selected( $state['source'], 'manual' ); ?>><?php esc_html_e( 'Manual', 'core-blueprint-work' ); ?></option>
                        </select>
                    </div>
                    <div class="cb-work-time-filter-actions">
                        <button type="submit" class="button button-primary"><?php esc_html_e( 'Filter', 'core-blueprint-work' ); ?></button>
                        <?php if ( $has_filters ) : ?>
                            <a class="button" href="<?php echo esc_url( Menu::time_url( [ 'view' => Time::VIEW_ENTRIES ] ) ); ?>"><?php esc_html_e( 'Clear filters', 'core-blueprint-work' ); ?></a>
                        <?php endif; ?>
                    </div>
                </div>
                <details class="cb-work-time-entries-more-filters" <?php if ( $user_filter_active ) : ?>open<?php endif; ?>>
                    <summary>
                        <?php esc_html_e( 'More filters', 'core-blueprint-work' ); ?>
                        <?php if ( $advanced_active ) : ?><span class="cb-work-time-entries-active-filters"><?php esc_html_e( 'Active filters', 'core-blueprint-work' ); ?></span><?php endif; ?>
                    </summary>
                    <div class="cb-work-time-entries-secondary-filters">
                        <?php if ( $manager ) : ?>
                            <div class="cb-work-time-filter cb-work-time-filter--user">
                                <label for="cb-work-te-user"><?php esc_html_e( 'User', 'core-blueprint-work' ); ?></label>
                                <?php Pickers::assignee( 'te_user', 'cb-work-te-user', $state['user_id'], false ); ?>
                            </div>
                        <?php endif; ?>
                        <div class="cb-work-time-filter cb-work-time-filter--sort">
                            <label for="cb-work-te-sort"><?php esc_html_e( 'Sort by', 'core-blueprint-work' ); ?></label>
                            <select id="cb-work-te-sort" name="te_sort">
                                <?php foreach ( self::sort_labels() as $value => $label ) : ?>
                                    <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $state['sort'], $value ); ?>><?php echo esc_html( $label ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </details>
            </form>

            <?php if ( [] !== $result['items'] ) : ?>
                <!-- Without JavaScript the bulk form remains available as a regular POST form. -->
                <noscript><style>#cb-work-time-bulk-form[hidden] { display: block !important; }</style></noscript>
                <form id="cb-work-time-bulk-form" class="cb-work-time-bulk-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-cb-work-time-bulk-form hidden>
                    <input type="hidden" name="action" value="<?php echo esc_attr( TimeEntryBulkEdit::ACTION ); ?>">
                    <?php wp_nonce_field( TimeEntryBulkEdit::ACTION ); ?>
                    <?php foreach ( TimeEntryListState::url_args( $state ) as $key => $value ) : ?>
                        <input type="hidden" name="time_list[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( (string) $value ); ?>">
                    <?php endforeach; ?>
                    <details class="cb-work-time-bulk-editor" data-cb-work-time-bulk-editor>
                        <summary><?php esc_html_e( 'Bulk Edit', 'core-blueprint-work' ); ?> <span class="cb-work-time-bulk-count" role="status"><output data-cb-work-time-bulk-count>0</output> <?php esc_html_e( 'Selected', 'core-blueprint-work' ); ?></span></summary>
                        <div class="cb-work-time-bulk-fields">
                            <?php if ( $manager ) : ?>
                                <div class="cb-work-time-bulk-field">
                                    <label for="cb-work-time-bulk-item"><?php esc_html_e( 'Work Item', 'core-blueprint-work' ); ?></label>
                                    <select id="cb-work-time-bulk-item" name="bulk_work_item_id" data-cb-work-time-bulk-target>
                                        <option value="0"><?php esc_html_e( 'No change', 'core-blueprint-work' ); ?></option>
                                        <?php foreach ( WorkItems::all( 500 ) as $work_item ) : ?>
                                            <option value="<?php echo esc_attr( (string) $work_item['id'] ); ?>"><?php echo esc_html( (string) $work_item['title'] ); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            <?php else : ?>
                                <input type="hidden" name="bulk_work_item_id" value="0">
                            <?php endif; ?>
                            <div class="cb-work-time-bulk-field">
                                <label for="cb-work-time-bulk-mode"><?php esc_html_e( 'Note', 'core-blueprint-work' ); ?></label>
                                <select id="cb-work-time-bulk-mode" name="note_mode" data-cb-work-time-bulk-mode>
                                    <option value="keep"><?php esc_html_e( 'No change', 'core-blueprint-work' ); ?></option>
                                    <option value="append"><?php esc_html_e( 'Append to note', 'core-blueprint-work' ); ?></option>
                                    <option value="replace"><?php esc_html_e( 'Replace note', 'core-blueprint-work' ); ?></option>
                                    <option value="clear"><?php esc_html_e( 'Clear note', 'core-blueprint-work' ); ?></option>
                                </select>
                            </div>
                            <div class="cb-work-time-bulk-field cb-work-time-bulk-field--note">
                                <label for="cb-work-time-bulk-note"><?php esc_html_e( 'Note', 'core-blueprint-work' ); ?></label>
                                <textarea id="cb-work-time-bulk-note" name="bulk_note" rows="2" maxlength="4000" data-cb-work-time-bulk-note></textarea>
                            </div>
                        </div>
                        <div class="cb-work-time-bulk-actions">
                            <button type="submit" class="button button-primary" data-cb-work-time-bulk-submit><?php esc_html_e( 'Update selected', 'core-blueprint-work' ); ?></button>
                        </div>
                    </details>
                </form>
            <?php endif; ?>

            <div class="cb-work-time-entries-summary" role="status">
                <span><?php
                    /* translators: 1: first visible entry, 2: last visible entry, 3: total matching entries. */
                    echo esc_html( sprintf( __( 'Entries: %1$d–%2$d of %3$d', 'core-blueprint-work' ), $from, $to, $total ) );
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
                                <th scope="col" class="cb-work-time-bulk-select-column">
                                    <label class="screen-reader-text" for="cb-work-time-bulk-all"><?php esc_html_e( 'Select all' ); ?></label>
                                    <input id="cb-work-time-bulk-all" type="checkbox" data-cb-work-time-bulk-select-all aria-label="<?php esc_attr_e( 'Select all' ); ?>">
                                </th>
                                <th scope="col" <?php if ( in_array( $state['sort'], [ 'newest', 'oldest' ], true ) ) : ?>aria-sort="<?php echo esc_attr( 'newest' === $state['sort'] ? 'descending' : 'ascending' ); ?>"<?php endif; ?>>
                                    <a href="<?php echo esc_url( Menu::time_url( TimeEntryListState::url_args( [ ...$state, 'sort' => 'newest' === $state['sort'] ? 'oldest' : 'newest' ], 1 ) ) ); ?>"><?php esc_html_e( 'When', 'core-blueprint-work' ); ?><?php if ( in_array( $state['sort'], [ 'newest', 'oldest' ], true ) ) : ?><span aria-hidden="true" class="cb-work-time-sort-indicator"><?php echo 'newest' === $state['sort'] ? ' ↓' : ' ↑'; ?></span><?php endif; ?></a>
                                </th>
                                <th scope="col"><?php esc_html_e( 'Work Item', 'core-blueprint-work' ); ?></th>
                                <?php if ( $manager ) : ?><th scope="col"><?php esc_html_e( 'User', 'core-blueprint-work' ); ?></th><?php endif; ?>
                                <th scope="col" <?php if ( in_array( $state['sort'], [ 'longest', 'shortest' ], true ) ) : ?>aria-sort="<?php echo esc_attr( 'longest' === $state['sort'] ? 'descending' : 'ascending' ); ?>"<?php endif; ?>>
                                    <a href="<?php echo esc_url( Menu::time_url( TimeEntryListState::url_args( [ ...$state, 'sort' => 'longest' === $state['sort'] ? 'shortest' : 'longest' ], 1 ) ) ); ?>"><?php esc_html_e( 'Duration', 'core-blueprint-work' ); ?><?php if ( in_array( $state['sort'], [ 'longest', 'shortest' ], true ) ) : ?><span aria-hidden="true" class="cb-work-time-sort-indicator"><?php echo 'longest' === $state['sort'] ? ' ↓' : ' ↑'; ?></span><?php endif; ?></a>
                                </th>
                                <th scope="col" <?php if ( 'source' === $state['sort'] ) : ?>aria-sort="ascending"<?php endif; ?>>
                                    <a href="<?php echo esc_url( Menu::time_url( TimeEntryListState::url_args( [ ...$state, 'sort' => 'source' ], 1 ) ) ); ?>"><?php esc_html_e( 'Source', 'core-blueprint-work' ); ?><?php if ( 'source' === $state['sort'] ) : ?><span aria-hidden="true" class="cb-work-time-sort-indicator"> ↑</span><?php endif; ?></a>
                                </th>
                                <th scope="col"><?php esc_html_e( 'Note', 'core-blueprint-work' ); ?></th>
                                <th scope="col"><?php esc_html_e( 'Actions', 'core-blueprint-work' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $result['items'] as $entry ) : ?>
                                <?php
                                $item = WorkItems::get( (int) $entry['work_item_id'] );
                                $user = $manager ? get_userdata( (int) $entry['user_id'] ) : null;
                                $started_at = (string) $entry['started_at'];
                                $timestamp = TimeRange::valid_utc( $started_at )
                                    ? ( new \DateTimeImmutable( $started_at, new \DateTimeZone( 'UTC' ) ) )->getTimestamp()
                                    : null;
                                $when = null !== $timestamp
                                    ? wp_date( (string) get_option( 'date_format', 'Y-m-d' ) . ' ' . (string) get_option( 'time_format', 'H:i' ), $timestamp )
                                    : '';
                                $item_title = (string) ( $item['title'] ?? sprintf( __( 'Work Item #%d', 'core-blueprint-work' ), (int) $entry['work_item_id'] ) );
                                ?>
                                <tr>
                                    <td class="cb-work-time-bulk-select-column">
                                        <?php if ( null !== $entry['ended_at'] && Access::can_edit_entry( $entry, (int) $entry['work_item_id'] ) ) : ?>
                                            <label class="screen-reader-text" for="cb-work-time-bulk-select-<?php echo esc_attr( (string) $entry['id'] ); ?>"><?php esc_html_e( 'Select' ); ?></label>
                                            <input type="checkbox" id="cb-work-time-bulk-select-<?php echo esc_attr( (string) $entry['id'] ); ?>" name="entry_ids[]" value="<?php echo esc_attr( (string) $entry['id'] ); ?>" form="cb-work-time-bulk-form" data-cb-work-time-bulk-select>
                                            <input type="hidden" name="entry_revisions[<?php echo esc_attr( (string) $entry['id'] ); ?>]" value="<?php echo esc_attr( (string) $entry['revision'] ); ?>" form="cb-work-time-bulk-form">
                                        <?php endif; ?>
                                    </td>
                                    <td class="cb-work-time-when"><time datetime="<?php echo esc_attr( str_replace( ' ', 'T', $started_at ) . 'Z' ); ?>"><?php echo esc_html( $when ); ?></time></td>
                                    <td class="cb-work-time-entry-title"><?php echo esc_html( $item_title ); ?></td>
                                    <?php if ( $manager ) : ?><td><?php echo esc_html( $user ? (string) $user->display_name : (string) $entry['user_id'] ); ?></td><?php endif; ?>
                                    <td class="cb-work-time-duration"><?php echo esc_html( self::duration( (int) $entry['duration_seconds'] ) ); ?></td>
                                    <td><?php echo esc_html( TimeEntries::SOURCE_TIMER === $entry['entry_source'] ? __( 'Timer', 'core-blueprint-work' ) : __( 'Manual', 'core-blueprint-work' ) ); ?></td>
                                    <td class="cb-work-time-note"><?php echo esc_html( wp_html_excerpt( (string) $entry['note'], 120, '…' ) ); ?></td>
                                    <td class="cb-work-time-entries-row-actions"><?php if ( Access::can_edit_entry( $entry, (int) $entry['work_item_id'] ) ) : ?>
                                        <a class="button button-small" href="<?php echo esc_url( Menu::time_url( [ 'view' => Time::VIEW_MANUAL, 'entry_id' => (int) $entry['id'] ] ) ); ?>"><?php esc_html_e( 'Edit', 'core-blueprint-work' ); ?></a>
                                        <a class="button button-small" href="<?php echo esc_url( Menu::time_url( [ ...TimeEntryListState::url_args( $state ), 'te_edit' => (int) $entry['id'] ] ) ); ?>" data-cb-work-time-quick-edit-toggle aria-expanded="<?php echo $quick_edit_id === (int) $entry['id'] ? 'true' : 'false'; ?>" <?php if ( $quick_edit_id === (int) $entry['id'] ) : ?> aria-controls="cb-work-time-quick-edit-<?php echo esc_attr( (string) $entry['id'] ); ?>"<?php endif; ?>><?php esc_html_e( 'Quick Edit', 'core-blueprint-work' ); ?></a>
                                    <?php endif; ?></td>
                                </tr>
                                <?php if ( $quick_edit_id === (int) $entry['id'] && null !== $entry['ended_at'] && Access::can_edit_entry( $entry, (int) $entry['work_item_id'] ) ) : ?>
                                    <tr class="cb-work-time-quick-edit-row">
                                        <td colspan="<?php echo esc_attr( (string) ( $manager ? 8 : 7 ) ); ?>"><?php TimeEntryQuickEdit::render( $entry, $state ); ?></td>
                                    </tr>
                                <?php endif; ?>
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
