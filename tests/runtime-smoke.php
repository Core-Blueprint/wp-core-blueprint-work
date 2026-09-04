<?php
declare(strict_types=1);

namespace {
    define('ABSPATH', '/tmp/wp/');
    define('CB_CORE_API_VERSION', '1.0');

    $GLOBALS['cb_test_hooks'] = [];
    $GLOBALS['cb_test_did'] = [];
    $GLOBALS['cb_test_current_hook'] = null;
    $GLOBALS['cb_test_activation'] = null;
    $GLOBALS['cb_test_roles'] = [
        'administrator' => new class { public array $caps = []; public function add_cap(string $cap): void { $this->caps[] = $cap; } },
        'cb_operator' => new class { public array $caps = []; public function add_cap(string $cap): void { $this->caps[] = $cap; } },
    ];

    function add_action(string $hook, callable $callback, int $priority = 10, int $accepted_args = 1): bool {
        $GLOBALS['cb_test_hooks'][$hook][$priority][] = [$callback, $accepted_args, false];
        return true;
    }
    function add_filter(string $hook, callable $callback, int $priority = 10, int $accepted_args = 1): bool {
        $GLOBALS['cb_test_hooks'][$hook][$priority][] = [$callback, $accepted_args, true];
        return true;
    }
    function do_action(string $hook, mixed ...$args): void {
        $GLOBALS['cb_test_did'][$hook] = ($GLOBALS['cb_test_did'][$hook] ?? 0) + 1;
        $previous = $GLOBALS['cb_test_current_hook'];
        $GLOBALS['cb_test_current_hook'] = $hook;
        $priorities = $GLOBALS['cb_test_hooks'][$hook] ?? [];
        ksort($priorities);
        foreach ($priorities as $callbacks) {
            foreach ($callbacks as [$callback, $accepted_args]) {
                $callback(...array_slice($args, 0, $accepted_args));
            }
        }
        $GLOBALS['cb_test_current_hook'] = $previous;
    }
    function apply_filters(string $hook, mixed $value, mixed ...$args): mixed {
        $previous = $GLOBALS['cb_test_current_hook'];
        $GLOBALS['cb_test_current_hook'] = $hook;
        $priorities = $GLOBALS['cb_test_hooks'][$hook] ?? [];
        ksort($priorities);
        foreach ($priorities as $callbacks) {
            foreach ($callbacks as [$callback, $accepted_args]) {
                $call_args = array_slice([$value, ...$args], 0, $accepted_args);
                $value = $callback(...$call_args);
            }
        }
        $GLOBALS['cb_test_current_hook'] = $previous;
        return $value;
    }
    function did_action(string $hook): int { return (int) ($GLOBALS['cb_test_did'][$hook] ?? 0); }
    function doing_action(?string $hook = null): bool {
        return null === $hook ? null !== $GLOBALS['cb_test_current_hook'] : $GLOBALS['cb_test_current_hook'] === $hook;
    }
    function plugin_dir_path(string $file): string { return dirname($file) . '/'; }
    function plugin_dir_url(string $file): string { return 'https://example.test/wp-content/plugins/core-blueprint-work/'; }
    function plugin_basename(string $file): string { return 'core-blueprint-work/' . basename($file); }
    function register_activation_hook(string $file, callable $callback): void { $GLOBALS['cb_test_activation'] = $callback; }
    function register_deactivation_hook(string $file, callable $callback): void {}
    function load_plugin_textdomain(string $domain, bool $deprecated = false, string $path = ''): bool { return true; }
    function is_admin(): bool { return true; }
    function current_user_can(string $capability): bool { return true; }
    function admin_url(string $path = ''): string { return 'https://example.test/wp-admin/' . ltrim($path, '/'); }
    function sanitize_key(string $key): string { return strtolower(preg_replace('/[^a-z0-9_\-]/', '', $key) ?? ''); }
    function __(string $text, string $domain = 'default'): string { return $text; }
    function esc_html__(string $text, string $domain = 'default'): string { return $text; }
    function esc_html(string $text): string { return $text; }
    function esc_html_e(string $text, string $domain = 'default'): void { echo $text; }
    function wp_die(string $message = '', string $title = '', array $args = []): never { throw new \RuntimeException($title . ': ' . $message); }
    function get_role(string $role): ?object { return $GLOBALS['cb_test_roles'][$role] ?? null; }
    function deactivate_plugins(string $plugin): void { throw new \RuntimeException('Unexpected deactivation: ' . $plugin); }

    function assert_true(bool $condition, string $message): void {
        if (!$condition) {
            fwrite(STDERR, "FAIL: {$message}\n");
            exit(1);
        }
    }
}

namespace CB\Core\Admin {
    interface Page {
        public function slug(): string;
        public function title(): string;
        public function menu_title(): string;
        public function capability(): string;
        public function position(): ?int;
        public function render(): void;
    }
    final class PageRegistry {
        public static array $registrations = [];
        public static function register(Page $page, array $requirements = []): bool {
            self::$registrations[$page->slug()] = [$page, $requirements];
            return true;
        }
    }
}

namespace CB\Core\Dashboard {
    final class CardRegistry {
        public static array $shortcuts = [];
        public static function register_shortcut(string $cardId, array $shortcut): bool {
            self::$shortcuts[$cardId][$shortcut['id']] = $shortcut;
            return true;
        }
    }
}

namespace CB\Core {
    final class ExtensionRegistry {
        public static array $registrations = [];
        public static function register(array $definition): bool {
            self::$registrations[$definition['id']] = $definition;
            return true;
        }
    }
}

namespace CB\Core\Governance {
    final class Audit {}
    final class EventRegistry {}
}

namespace {
    require dirname( __DIR__ ) . '/core-blueprint-work.php';

    assert_true(class_exists('CB\\Work\\Integration\\Suite'), 'Suite autoloads during lightweight registration.');
    assert_true(!CB\Work\Plugin::is_booted(), 'Product runtime is not booted before Base signal.');

    $prebootDefinitions = apply_filters('cb_core_module_status_definitions', []);
    $prebootProvider = $prebootDefinitions['work']['provider'] ?? null;
    assert_true(is_callable($prebootProvider), 'Health provider is callable before product runtime boots.');
    $prebootStatus = $prebootProvider();
    assert_true(($prebootStatus['state'] ?? '') === 'warn', 'Pre-boot health is intentional warn state.');

    do_action('cb_core_booted');
    assert_true(CB\Work\Plugin::is_booted(), 'Product runtime boots on cb_core_booted.');

    do_action('init');
    do_action('cb_core_register_extensions');
    $extension = CB\Core\ExtensionRegistry::$registrations['core-blueprint-work'] ?? null;
    assert_true(is_array($extension), 'Extension registers through public Base registry hook.');
    assert_true(($extension['plugin_file'] ?? '') === 'core-blueprint-work/core-blueprint-work.php', 'Extension declares canonical WordPress plugin basename.');
    assert_true(($extension['requires_api'] ?? '') === '1.0', 'Extension declares Core API 1.0.');
    assert_true(!array_key_exists('requires_base', $extension), 'Extension does not pin an internal Base RC.');
    assert_true(($extension['status_id'] ?? '') === 'work', 'Extension declares Work status provider.');

    $statusDefinitions = apply_filters('cb_core_module_status_definitions', []);
    $provider = $statusDefinitions['work']['provider'] ?? null;
    assert_true(is_callable($provider), 'Work status provider is registered.');
    $status = $provider();
    assert_true(($status['state'] ?? '') === 'ok', 'Work reports ok after runtime boot.');
    assert_true(in_array($status['state'] ?? '', ['ok', 'warn', 'err', 'off'], true), 'Health state uses the canonical enum.');
    assert_true(is_string($status['detail'] ?? null) && ($status['detail'] ?? '') !== '', 'Health detail is a non-empty string.');
    assert_true(!str_contains($status['detail'], 'Status unavailable'), 'Healthy path does not rely on Base fallback detail.');
    assert_true(($status['url'] ?? '') === 'https://example.test/wp-admin/admin.php?page=core-blueprint-work', 'Health URL points to the Work operator surface.');

    do_action('cb_core_register_pages');
    $pageRegistration = CB\Core\Admin\PageRegistry::$registrations['core-blueprint-work'] ?? null;
    assert_true(is_array($pageRegistration), 'Work Core Admin page registers through PageRegistry.');
    $requirements = $pageRegistration[1] ?? [];
    $components = $requirements['components'] ?? [];
    assert_true(!in_array('tables', $components, true), 'Work page does not request unsupported tables component.');
    assert_true($components === ['panels', 'notices', 'fields', 'form-controls'], 'Work page requests only intended semantic components.');

    do_action('cb_core_dashboard_register_cards');
    $shortcut = CB\Core\Dashboard\CardRegistry::$shortcuts['core-blueprint-work']['workspace'] ?? null;
    assert_true(is_array($shortcut), 'Work dashboard shortcut registers.');
    assert_true(($shortcut['capability'] ?? '') === 'cb_manage_work', 'Dashboard shortcut uses Work capability boundary.');

    $catalog = apply_filters('cb_core_capability_catalog', []);
    assert_true(isset($catalog['cb_manage_work']), 'Work capability is present in Base capability catalog.');

    $activation = $GLOBALS['cb_test_activation'];
    assert_true(is_callable($activation), 'Activation hook is registered.');
    $activation();
    foreach (['administrator', 'cb_operator'] as $roleName) {
        assert_true(in_array('cb_manage_work', $GLOBALS['cb_test_roles'][$roleName]->caps, true), "{$roleName} receives cb_manage_work on activation.");
    }

    echo "Runtime smoke passed.\n";
}
