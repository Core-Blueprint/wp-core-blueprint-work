<?php
declare(strict_types=1);
namespace CB\Work\Admin;
use CB\Work\Capabilities;
use CB\Work\Domain\WorkContext;
use CB\Work\Integration\CRMCustomers;
use CB\Work\PublicApi\Services;
use CB\Work\Repository\Projects;
use CB\Work\Repository\RecurrenceRules;
use CB\Work\ServiceOperations\Engagements;
use CB\Work\ServiceOperations\Periods;
use CB\Work\ServiceOperations\ServiceBilling;
defined('ABSPATH') || exit;

final class ServiceOperations {
    private const SCAN_LIMIT = 500;

    /** Filtering should not turn an empty search result into first-run onboarding. */
    private static function has_filters(array $filters): bool {
        return (int)$filters['project_id'] > 0 || (int)$filters['service_id'] > 0
            || '' !== $filters['status'] || '' !== $filters['billing']
            || '' !== $filters['customer'] || '' !== $filters['search'];
    }
    public static function init(): void {
        add_action('admin_enqueue_scripts', [self::class, 'assets']);
    }
    public static function assets(): void {
        if (!current_user_can(Capabilities::MANAGE) || (string)($_GET['page'] ?? '') !== Menu::SERVICE_OPERATIONS_SLUG) { return; }
        foreach (['service-operations.css' => 'style', 'service-operations.js' => 'script'] as $file => $type) {
            $path = CB_WORK_DIR . 'assets/' . $file;
            $version = is_file($path) ? substr((string)hash_file('sha256', $path), 0, 12) : CB_WORK_VERSION;
            if ($type === 'style') {
                wp_enqueue_style('cb-work-service-operations', CB_WORK_URL . 'assets/' . $file, [], $version);
            } else {
                wp_enqueue_script('cb-work-service-operations', CB_WORK_URL . 'assets/' . $file, [], $version, true);
                wp_localize_script('cb-work-service-operations', 'cbWorkServiceOperations', [
                    'ajaxUrl' => admin_url('admin-ajax.php'),
                    'saved' => __('Saved.', 'core-blueprint-work'),
                    'error' => __('The change could not be saved.', 'core-blueprint-work'),
                    'invoiced' => __('Invoiced externally', 'core-blueprint-work'),
                    'notRecorded' => __('Not recorded', 'core-blueprint-work'),
                ]);
            }
        }
    }
    public static function statuses(): array {
        return [
            'completed' => __('Completed','core-blueprint-work'),
            'planned' => __('Planned','core-blueprint-work'),
            'in_progress' => __('In progress','core-blueprint-work'),
            'overdue' => __('Overdue','core-blueprint-work'),
            'blocked' => __('Blocked','core-blueprint-work'),
            'attention' => __('Needs attention','core-blueprint-work'),
            'not_delivered' => __('Not delivered','core-blueprint-work'),
            'not_scheduled' => __('Not scheduled','core-blueprint-work'),
        ];
    }
    public static function billing_statuses(): array {
        return [
            'invoiced' => __('Invoiced externally','core-blueprint-work'),
            'not_recorded' => __('Not recorded','core-blueprint-work'),
            'not_applicable' => __('Not applicable','core-blueprint-work'),
        ];
    }
    private static function query(array $filters, array $projects, array $services): array|\WP_Error {
        $raw = [];
        $chunk_page = 1;
        do {
            $chunk = Engagements::search(['project_id' => $filters['project_id'],
                'service_id' => $filters['service_id'], 'active' => 1, 'page' => $chunk_page, 'per_page' => 50]);
            if (is_wp_error($chunk)) { return $chunk; }
            if ($chunk['total'] > self::SCAN_LIMIT) { return new \WP_Error('work_service_scan_limit'); }
            array_push($raw, ...$chunk['items']);
            $chunk_page++;
        } while ($chunk_page <= $chunk['pages']);
        $matches = [];
        $billing_counts = ['invoiced' => 0, 'not_recorded' => 0];
        $counts = array_fill_keys(array_keys(self::statuses()), 0);
        foreach ($raw as $engagement) {
            $customer_ref = $engagement['customer_provider'] . ':' . $engagement['customer_type'] . ':' . $engagement['customer_id'];
            if ($filters['customer'] !== '' && stripos($customer_ref, $filters['customer']) === false) { continue; }
            $project = $projects[$engagement['project_id']] ?? '';
            $service = $services[$engagement['service_id']] ?? '';
            $customer = CRMCustomers::label($engagement['customer_provider'], $engagement['customer_type'], $engagement['customer_id']);
            $haystack = implode(' ', [$project, $service, $customer, $customer_ref, $engagement['label']]);
            if ($filters['search'] !== '' && stripos($haystack, $filters['search']) === false) { continue; }
            $execution = Periods::resolve($engagement, $filters['month']);
            if (is_wp_error($execution)) { return $execution; }
            $coverage = ServiceBilling::for_month($engagement['id'], $filters['month']);
            if (is_wp_error($coverage)) { return $coverage; }
            if ($filters['status'] !== '' && $execution['status'] !== $filters['status']) { continue; }
            if ($filters['billing'] !== '' && $coverage['state'] !== $filters['billing']) { continue; }
            $counts[$execution['status']]++;
            if (isset($billing_counts[$coverage['state']])) { $billing_counts[$coverage['state']]++; }
            $matches[] = compact('engagement','project','service','customer','execution','coverage');
        }
        $total = count($matches);
        $pages = max(1, (int)ceil($total / 25));
        $page = min($pages, max(1, $filters['page']));
        return ['rows' => array_slice($matches, ($page - 1) * 25, 25),
            'counts' => $counts, 'billing_counts' => $billing_counts, 'total' => $total, 'pages' => $pages, 'page' => $page];
    }
    public static function render(): void {
        if (!current_user_can(Capabilities::MANAGE)) { wp_die(esc_html__('You do not have permission to manage Work.','core-blueprint-work')); }
        $month = isset($_GET['cb_month']) ? sanitize_text_field((string)wp_unslash($_GET['cb_month'])) : wp_date('Y-m');
        if (!Periods::bounds($month)) { $month = wp_date('Y-m'); }
        $filters = [
            'month' => $month, 'project_id' => absint($_GET['cb_project'] ?? 0), 'service_id' => absint($_GET['cb_service'] ?? 0),
            'status' => sanitize_key((string)wp_unslash($_GET['cb_status'] ?? '')),
            'billing' => sanitize_key((string)wp_unslash($_GET['cb_billing'] ?? '')),
            'customer' => sanitize_text_field((string)wp_unslash($_GET['cb_customer'] ?? '')),
            'search' => sanitize_text_field((string)wp_unslash($_GET['cb_search'] ?? '')),
            'page' => max(1, absint($_GET['cb_page'] ?? 1)),
        ];
        $projects = []; $customer_projects = []; $services = [];
        foreach (Projects::all(500) as $p) {
            $id = (int)$p['id'];
            $projects[$id] = (string)$p['title'];
            if (WorkContext::CUSTOMER === ($p['work_context'] ?? '')
                && '' !== (string)($p['customer_provider'] ?? '')
                && '' !== (string)($p['customer_type'] ?? '')
                && '' !== (string)($p['customer_id'] ?? '')) {
                $customer_projects[$id] = (string)$p['title'];
            }
        }
        foreach (Services::all(250) as $s) { $services[(int)$s['id']] = (string)$s['title']; }
        $can_create = [] !== $customer_projects && [] !== $services;
        $report = self::query($filters, $projects, $services);
        $notice = sanitize_key((string)wp_unslash($_GET['cb_notice'] ?? ''));
        ?><div class="wrap cb-work-service-operations">
        <header class="cb-work-so-header"><div><h1><?php esc_html_e('Service Operations','core-blueprint-work'); ?></h1>
        <p class="description"><?php esc_html_e('Recurring customer services, execution and external invoicing in one place.','core-blueprint-work'); ?></p></div>
        <?php if ($can_create && (!is_array($report) || $report['total'] > 0 || self::has_filters($filters))): ?>
        <a class="button button-primary" href="#cb-work-so-add" data-cb-so-open><?php esc_html_e('Add service','core-blueprint-work'); ?></a>
        <?php endif; ?></header>
        <?php if ($notice): ?><div class="notice <?php echo in_array($notice,['created','bound','saved'],true) ? 'notice-success':'notice-error'; ?>">
        <p><?php echo esc_html(in_array($notice,['created','bound','saved'],true) ? __('Saved.','core-blueprint-work'):__('The change could not be saved.','core-blueprint-work')); ?></p></div><?php endif; ?>
        <?php if (is_wp_error($report)): ?>
            <div class="notice notice-error"><p><?php esc_html_e('Service Operations could not load. No status has been assumed.','core-blueprint-work'); ?>
            <code><?php echo esc_html($report->get_error_code()); ?></code></p></div>
        <?php elseif (0 === $report['total'] && !self::has_filters($filters)): ?>
            <?php self::onboarding($customer_projects,$services); ?>
        <?php else: ?>
        <form class="cb-work-so-filters" method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>">
        <input type="hidden" name="page" value="<?php echo esc_attr(Menu::SERVICE_OPERATIONS_SLUG); ?>">
        <div class="cb-work-so-filters-main">
          <label><?php esc_html_e('Period','core-blueprint-work'); ?><input type="month" name="cb_month" value="<?php echo esc_attr($month); ?>" required></label>
          <label><?php esc_html_e('Search','core-blueprint-work'); ?><input name="cb_search" type="search" value="<?php echo esc_attr($filters['search']); ?>"></label>
          <button type="submit" class="button"><?php esc_html_e('Filter','core-blueprint-work'); ?></button>
          <?php if (self::has_filters($filters)): ?>
          <a class="button" href="<?php echo esc_url(add_query_arg(['page'=>Menu::SERVICE_OPERATIONS_SLUG,'cb_month'=>$month],admin_url('admin.php'))); ?>"><?php esc_html_e('Clear filters','core-blueprint-work'); ?></a>
          <?php endif; ?>
        </div>
        <details class="cb-work-so-filters-more" <?php if ((int)$filters['project_id']>0 || (int)$filters['service_id']>0 || ''!==$filters['status'] || ''!==$filters['billing'] || ''!==$filters['customer']): ?>open<?php endif; ?>>
          <summary><?php esc_html_e('More filters','core-blueprint-work'); ?></summary>
          <div class="cb-work-so-filters-extra">
        <?php foreach (['cb_project' => [__('Projects','core-blueprint-work'),__('All Projects','core-blueprint-work'),$projects,$filters['project_id']],
                        'cb_service' => [__('Services','core-blueprint-work'),__('All Services','core-blueprint-work'),$services,$filters['service_id']]] as $name => $data): ?>
            <label><?php echo esc_html($data[0]); ?><select name="<?php echo esc_attr($name); ?>">
            <option value="0"><?php echo esc_html($data[1]); ?></option>
            <?php foreach ($data[2] as $id => $title): ?><option value="<?php echo esc_attr((string)$id); ?>" <?php selected($data[3],$id); ?>><?php echo esc_html($title); ?></option><?php endforeach; ?>
            </select></label>
        <?php endforeach; ?>
        <?php foreach (['cb_status' => [__('Execution status','core-blueprint-work'),self::statuses(),$filters['status']],
                        'cb_billing' => [__('Billing registration','core-blueprint-work'),self::billing_statuses(),$filters['billing']]] as $name => $data): ?>
            <label><?php echo esc_html($data[0]); ?><select name="<?php echo esc_attr($name); ?>">
            <option value=""><?php esc_html_e('All statuses','core-blueprint-work'); ?></option>
            <?php foreach ($data[1] as $id => $title): ?><option value="<?php echo esc_attr($id); ?>" <?php selected($data[2],$id); ?>><?php echo esc_html($title); ?></option><?php endforeach; ?>
            </select></label>
        <?php endforeach; ?>
        <label><?php esc_html_e('Customer','core-blueprint-work'); ?><input name="cb_customer" type="search" value="<?php echo esc_attr($filters['customer']); ?>"></label>
          </div>
        </details>
        </form>
            <p class="description"><?php esc_html_e('Summary for the selected period and filters.','core-blueprint-work'); ?></p>
            <section class="cb-work-so-summary" aria-label="<?php esc_attr_e('Summary for the selected period and filters.','core-blueprint-work'); ?>">
              <div class="cb-work-so-cards">
                <div><strong><?php echo esc_html((string)$report['total']); ?></strong><?php esc_html_e('Services','core-blueprint-work'); ?></div>
                <?php foreach (['completed','overdue','attention'] as $key): ?>
                  <div><strong><?php echo esc_html((string)$report['counts'][$key]); ?></strong><?php echo esc_html(self::statuses()[$key]); ?></div>
                <?php endforeach; ?>
                <div><strong data-cb-so-billing-count="not_recorded"><?php echo esc_html((string)$report['billing_counts']['not_recorded']); ?></strong><?php esc_html_e('Not recorded','core-blueprint-work'); ?></div>
              </div>
              <details class="cb-work-so-summary-extra">
                <summary><?php esc_html_e('More statuses','core-blueprint-work'); ?></summary>
                <div class="cb-work-so-cards cb-work-so-cards-extra">
                  <?php foreach (['planned','in_progress','blocked','not_delivered','not_scheduled'] as $key): ?>
                    <div><strong><?php echo esc_html((string)$report['counts'][$key]); ?></strong><?php echo esc_html(self::statuses()[$key]); ?></div>
                  <?php endforeach; ?>
                  <div><strong data-cb-so-billing-count="invoiced"><?php echo esc_html((string)$report['billing_counts']['invoiced']); ?></strong><?php esc_html_e('Invoiced externally','core-blueprint-work'); ?></div>
                </div>
              </details>
            </section>
            <div class="cb-work-so-table-wrap"><table class="widefat striped cb-work-so-table">
            <caption class="screen-reader-text"><?php esc_html_e('Service Operations','core-blueprint-work'); ?></caption>
            <thead><tr>
            <?php foreach ([__('Customer / Project','core-blueprint-work'),__('Service','core-blueprint-work'),__('Execution','core-blueprint-work'),
                __('Last execution','core-blueprint-work'),__('Next execution','core-blueprint-work'),
                __('Billing registration','core-blueprint-work'),__('Actions','core-blueprint-work')] as $heading): ?>
                <th scope="col"><?php echo esc_html($heading); ?></th><?php endforeach; ?>
            </tr></thead><tbody>
            <?php foreach ($report['rows'] as $row): $e=$row['engagement'];$x=$row['execution'];$b=$row['coverage'];$id=$e['id']; ?>
                <tr><td><?php if ($e['project_id'] && get_edit_post_link($e['project_id'])): ?><a href="<?php echo esc_url(get_edit_post_link($e['project_id'])); ?>"><?php echo esc_html($row['project']); ?></a>
                    <?php else: echo esc_html($row['project']); endif; ?>
                    <small><?php echo esc_html($row['customer']); ?></small></td>
                <td><strong><?php echo esc_html($row['service']); ?></strong><small><?php echo esc_html($e['label']); ?></small></td>
                <td><span class="cb-work-so-badge cb-work-so-<?php echo esc_attr($x['status']); ?>"><?php echo esc_html(self::statuses()[$x['status']]); ?></span>
                    <small><?php echo esc_html($x['completed'].' / '.$x['scheduled']); ?></small></td>
                <td><?php echo esc_html($x['last_execution'] ? substr($x['last_execution'],0,10):'—'); ?></td>
                <td><?php echo esc_html($x['next_execution'] ?? '—'); ?></td>
                <td><span class="cb-work-so-badge" data-cb-so-billing-label><?php echo esc_html(self::billing_statuses()[$b['state']] ?? '—'); ?></span>
                    <?php if (isset($b['period_start'],$b['period_end_exclusive'])): ?>
                        <small><?php echo esc_html($b['period_start'].' → '.$b['period_end_exclusive']); ?></small>
                        <?php self::invoice_form($id,$month,$b); ?>
                    <?php endif; ?></td>
                <td><?php if ($x['work_item_ids']): ?><a class="button button-small" href="<?php echo esc_url(Menu::edit_work_item_url((int)$x['work_item_ids'][0])); ?>"><?php esc_html_e('Open Work Item','core-blueprint-work'); ?></a><?php endif; ?>
                <?php self::bind_form($id,$e,$month); ?></td></tr>
            <?php endforeach; ?>
            <?php if (!$report['rows']): ?><tr><td colspan="7"><?php esc_html_e('No matching services. Adjust your filters or add a service.','core-blueprint-work'); ?>
            <?php if (self::has_filters($filters)): ?><a href="<?php echo esc_url(add_query_arg(['page'=>Menu::SERVICE_OPERATIONS_SLUG,'cb_month'=>$month],admin_url('admin.php'))); ?>"><?php esc_html_e('Clear filters','core-blueprint-work'); ?></a><?php endif; ?></td></tr><?php endif; ?>
            </tbody></table></div>
            <?php if ($report['pages']>1): ?><nav class="cb-work-so-pages" aria-label="<?php esc_attr_e('Pagination','core-blueprint-work'); ?>">
            <?php for ($p=1;$p<=$report['pages'];$p++): $args = array_filter([
                'page'=>Menu::SERVICE_OPERATIONS_SLUG, 'cb_month'=>$month,'cb_project'=>$filters['project_id'],
                'cb_service'=>$filters['service_id'],'cb_status'=>$filters['status'],'cb_billing'=>$filters['billing'],
                'cb_customer'=>$filters['customer'],'cb_search'=>$filters['search'],'cb_page'=>$p
            ],static fn(mixed $v):bool=>''!==$v && 0!==$v); ?>
                <a class="button" href="<?php echo esc_url(add_query_arg($args,admin_url('admin.php'))); ?>" <?php if ($p===$report['page']): ?>aria-current="page"<?php endif; ?>><?php echo esc_html((string)$p); ?></a>
            <?php endfor; ?></nav><?php endif; ?>
        <?php endif; ?>
        <?php if ($can_create): self::create_form($customer_projects,$services); endif; ?></div><?php
    }
    /** First-run guidance deliberately avoids invented customer identities. */
    private static function onboarding(array $customer_projects,array $services):void {
        $service_ready = [] !== $services;
        $project_ready = [] !== $customer_projects;
        ?>
        <section class="cb-work-so-onboarding" aria-labelledby="cb-work-so-getting-started">
          <h2 id="cb-work-so-getting-started"><?php esc_html_e('Getting started','core-blueprint-work'); ?></h2>
          <p><?php esc_html_e('A customer-linked Project and a Work Service are required.','core-blueprint-work'); ?></p>
          <ol class="cb-work-so-steps">
            <li>
              <strong><?php esc_html_e('Create a Work Service','core-blueprint-work'); ?></strong>
              <?php if ($service_ready): ?><span class="cb-work-so-ready"><?php esc_html_e('Ready','core-blueprint-work'); ?></span>
              <?php else: ?><a class="button" href="<?php echo esc_url(admin_url('post-new.php?post_type=cb_work_service')); ?>"><?php esc_html_e('Create a Work Service','core-blueprint-work'); ?></a><?php endif; ?>
            </li>
            <li>
              <strong><?php esc_html_e('Create a customer Project','core-blueprint-work'); ?></strong>
              <p><?php esc_html_e('Link the Project to a customer in the Project editor.','core-blueprint-work'); ?></p>
              <?php if ($project_ready): ?><span class="cb-work-so-ready"><?php esc_html_e('Ready','core-blueprint-work'); ?></span>
              <?php elseif (CRMCustomers::available()): ?><a class="button" href="<?php echo esc_url(admin_url('post-new.php?post_type=cb_work_project')); ?>"><?php esc_html_e('Create a customer Project','core-blueprint-work'); ?></a><?php endif; ?>
            </li>
          </ol>
          <?php if (!$project_ready && !CRMCustomers::available()): ?>
            <p class="cb-work-so-guidance"><?php esc_html_e('Customer selection is unavailable. Connect a customer provider before creating customer Projects.','core-blueprint-work'); ?></p>
          <?php endif; ?>
          <?php if ($service_ready && $project_ready): ?>
            <a class="button button-primary" href="#cb-work-so-add" data-cb-so-open><?php esc_html_e('Add service','core-blueprint-work'); ?></a>
          <?php endif; ?>
        </section><?php
    }

    private static function invoice_form(int $id,string $month,array $coverage):void {
        $r=$coverage['registration'] ?? null; ?>
        <details class="cb-work-so-editor"><summary><?php esc_html_e('Edit billing registration','core-blueprint-work'); ?></summary>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" data-cb-so-invoice>
        <input type="hidden" name="action" value="cb_work_service_invoice_save">
        <input type="hidden" name="engagement_id" value="<?php echo esc_attr((string)$id); ?>">
        <input type="hidden" name="month" value="<?php echo esc_attr($month); ?>">
        <input type="hidden" name="revision" value="<?php echo esc_attr((string)($r['revision'] ?? 0)); ?>">
        <?php wp_nonce_field('cb_work_service_invoice_'.$id); ?>
        <label><?php esc_html_e('Registration','core-blueprint-work'); ?><select name="state">
        <?php foreach (['not_recorded','invoiced'] as $state): ?><option value="<?php echo esc_attr($state); ?>" <?php selected($coverage['state'],$state); ?>><?php echo esc_html(self::billing_statuses()[$state]); ?></option><?php endforeach; ?>
        </select></label>
        <label><?php esc_html_e('Invoice date','core-blueprint-work'); ?><input type="date" name="invoiced_on" value="<?php echo esc_attr($r['invoiced_on'] ?? ''); ?>"></label>
        <label><?php esc_html_e('External reference','core-blueprint-work'); ?><input name="reference" type="text" maxlength="190" value="<?php echo esc_attr($r['external_reference'] ?? ''); ?>"></label>
        <button class="button button-primary" type="submit"><?php esc_html_e('Save','core-blueprint-work'); ?></button>
        <span role="status" aria-live="polite" data-cb-so-feedback></span></form></details><?php
    }
    private static function bind_form(int $id,array $eng,string $month):void {
        $links = Engagements::rules($id);
        if (is_wp_error($links)) { return; }
        $bound = array_map(static fn(array $v):int=>(int)$v['rule_id'],$links);
        $available = [];
        foreach (RecurrenceRules::all(false,500) as $rule) {
            if ((int)($rule['project_id'] ?? 0)===$eng['project_id']
                && (int)($rule['service_id'] ?? 0)===$eng['service_id']
                && !in_array((int)$rule['id'],$bound,true)) { $available[] = $rule; }
        }
        if (!$available) { return; } ?>
        <details class="cb-work-so-editor"><summary><?php esc_html_e('Link recurring work','core-blueprint-work'); ?></summary>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <input type="hidden" name="action" value="cb_work_service_bind">
        <input type="hidden" name="engagement_id" value="<?php echo esc_attr((string)$id); ?>">
        <?php wp_nonce_field('cb_work_service_bind_'.$id); ?>
        <label><?php esc_html_e('Recurring Work','core-blueprint-work'); ?><select name="rule_id">
            <?php foreach ($available as $r): ?><option value="<?php echo esc_attr((string)$r['id']); ?>"><?php echo esc_html($r['title']); ?></option><?php endforeach; ?>
        </select></label>
        <label><?php esc_html_e('Effective from','core-blueprint-work'); ?><input type="date" name="effective_from" value="<?php echo esc_attr(max($eng['starts_on'],$month.'-01')); ?>" required></label>
        <p class="description"><?php esc_html_e('Earlier work is not included before this date.','core-blueprint-work'); ?></p>
        <button class="button" type="submit"><?php esc_html_e('Link','core-blueprint-work'); ?></button></form></details><?php
    }
    private static function create_form(array $projects,array $services):void { ?>
        <details id="cb-work-so-add" class="cb-work-so-add"><summary><?php esc_html_e('Service details','core-blueprint-work'); ?></summary>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <input type="hidden" name="action" value="cb_work_service_create"><?php wp_nonce_field('cb_work_service_create'); ?>
        <?php foreach (['project_id'=>[__('Project','core-blueprint-work'),$projects],
                        'service_id'=>[__('Service','core-blueprint-work'),$services]] as $key=>$data): ?>
            <label><?php echo esc_html($data[0]); ?><select name="<?php echo esc_attr($key); ?>" required><option value="">—</option>
                <?php foreach ($data[1] as $id=>$title): ?><option value="<?php echo esc_attr((string)$id); ?>"><?php echo esc_html($title); ?></option><?php endforeach; ?>
            </select></label>
        <?php endforeach; ?>
        <label><?php esc_html_e('Label (optional)','core-blueprint-work'); ?><input name="label" maxlength="190"></label>
        <label><?php esc_html_e('Starts on','core-blueprint-work'); ?><input name="starts_on" type="date" value="<?php echo esc_attr(wp_date('Y-m-d')); ?>" required></label>
        <label><?php esc_html_e('Billing frequency','core-blueprint-work'); ?><select name="frequency">
        <?php foreach (['monthly'=>__('Monthly','core-blueprint-work'),'weekly'=>__('Weekly','core-blueprint-work'),
            'quarterly'=>__('Quarterly','core-blueprint-work'),'yearly'=>__('Yearly','core-blueprint-work')] as $key=>$label): ?>
            <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option><?php endforeach; ?>
        </select></label>
        <label><?php esc_html_e('Invoice timing','core-blueprint-work'); ?><select name="timing">
            <option value="arrears"><?php esc_html_e('After service','core-blueprint-work'); ?></option>
            <option value="advance"><?php esc_html_e('In advance','core-blueprint-work'); ?></option>
        </select></label>
        <label><?php esc_html_e('Existing Recurring Work (optional)','core-blueprint-work'); ?><select name="rule_id">
            <option value="0"><?php esc_html_e('None','core-blueprint-work'); ?></option>
            <?php foreach (RecurrenceRules::all(false,500) as $rule): ?>
                <option value="<?php echo esc_attr((string)$rule['id']); ?>"><?php echo esc_html($rule['title']); ?></option>
            <?php endforeach; ?>
        </select></label>
        <p class="description"><?php esc_html_e('The selected rule must belong to the same customer, Project and Service.','core-blueprint-work'); ?></p>
        <button class="button button-primary" type="submit"><?php esc_html_e('Add service','core-blueprint-work'); ?></button>
        </form></details><?php
    }
}
