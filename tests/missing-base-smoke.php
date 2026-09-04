<?php
declare(strict_types=1);

namespace {
    define('ABSPATH', '/tmp/wp/');

    $GLOBALS['cb_missing_base_hooks'] = [];
    $GLOBALS['cb_missing_base_activation'] = null;

    function add_action(string $hook, callable $callback, int $priority = 10, int $accepted_args = 1): bool {
        $GLOBALS['cb_missing_base_hooks'][$hook][$priority][] = [$callback, $accepted_args];
        return true;
    }
    function add_filter(string $hook, callable $callback, int $priority = 10, int $accepted_args = 1): bool {
        return add_action($hook, $callback, $priority, $accepted_args);
    }
    function do_action(string $hook, mixed ...$args): void {
        $priorities = $GLOBALS['cb_missing_base_hooks'][$hook] ?? [];
        ksort($priorities);
        foreach ($priorities as $callbacks) {
            foreach ($callbacks as [$callback, $acceptedArgs]) {
                $callback(...array_slice($args, 0, $acceptedArgs));
            }
        }
    }
    function plugin_dir_path(string $file): string { return dirname($file) . '/'; }
    function plugin_dir_url(string $file): string { return 'https://example.test/wp-content/plugins/core-blueprint-work/'; }
    function plugin_basename(string $file): string { return 'core-blueprint-work/' . basename($file); }
    function register_activation_hook(string $file, callable $callback): void { $GLOBALS['cb_missing_base_activation'] = $callback; }
    function register_deactivation_hook(string $file, callable $callback): void {}
    function load_plugin_textdomain(string $domain, bool $deprecated = false, string $path = ''): bool { return true; }
    function is_admin(): bool { return true; }
    function current_user_can(string $capability): bool { return true; }
    function esc_html__(string $text, string $domain = 'default'): string { return $text; }
    function esc_html(string $text): string { return $text; }
    function __(string $text, string $domain = 'default'): string { return $text; }
    function did_action(string $hook): int { return 0; }
    function doing_action(?string $hook = null): bool { return false; }

    function assert_true(bool $condition, string $message): void {
        if (!$condition) {
            fwrite(STDERR, "FAIL: {$message}\n");
            exit(1);
        }
    }

    require dirname(__DIR__) . '/core-blueprint-work.php';

    assert_true(isset($GLOBALS['cb_missing_base_hooks']['cb_core_register_extensions']), 'Lightweight suite registration remains attached without Base.');
    assert_true(isset($GLOBALS['cb_missing_base_hooks']['cb_core_booted']), 'Runtime waits for Base boot signal without forcing Base classes.');

    do_action('plugins_loaded');
    assert_true(isset($GLOBALS['cb_missing_base_hooks']['admin_notices']), 'Missing Base produces a deferred operator notice instead of booting product runtime.');
    assert_true(\CB\Work\Support\Requirements::issues() === ['base-missing'], 'Missing Base is classified deterministically.');

    echo "Missing-Base smoke passed.\n";
}
