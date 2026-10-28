<?php
declare(strict_types=1);

/** SO-UX1: deterministic first-run presentation without a WordPress database. */
define('ABSPATH', __DIR__);

final class SOUX1FakeCRMCustomers {
    public static function available(): bool { return false; }
}
class_alias(SOUX1FakeCRMCustomers::class, 'CB\\Work\\Integration\\CRMCustomers');

function esc_html_e(string $text, string $domain): void {
    echo htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}
function esc_url(string $url): string {
    return htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
}
function admin_url(string $path): string {
    return '/wp-admin/' . $path;
}

require_once __DIR__ . '/../src/Admin/ServiceOperations.php';
$class = CB\Work\Admin\ServiceOperations::class;
$state = new ReflectionMethod($class, 'display_state');
$cases = [
    'setup: no prerequisites' => [0, false, false, 'setup'],
    'ready: configured, no engagement' => [0, false, true, 'ready'],
    'operational: first engagement' => [1, false, true, 'operational'],
    'operational: engagements despite unavailable prerequisites' => [1, false, false, 'operational'],
    'filtered zero is not onboarding' => [0, true, true, 'operational'],
    'filtered zero without prerequisites is not onboarding' => [0, true, false, 'operational'],
];
foreach ($cases as $name => $args) {
    $expected = array_pop($args);
    if ($state->invoke(null, ...$args) !== $expected) {
        fwrite(STDERR, "SO-UX1 FAIL: $name\n");
        exit(1);
    }
}

$ready = new ReflectionMethod($class, 'ready_state');
ob_start();
$ready->invoke(null);
$html = (string)ob_get_clean();
if (!str_contains($html, 'Ready to start')
    || !str_contains($html, 'Your Project and Work Service are ready.')
    || 1 !== substr_count($html, 'data-cb-so-open')
    || !str_contains($html, 'href="#cb-work-so-add"')
    || str_contains($html, 'cb-work-so-steps')) {
    fwrite(STDERR, "SO-UX1 FAIL: ready-state CTA or content\n");
    exit(1);
}

$onboarding = new ReflectionMethod($class, 'onboarding');
foreach ([
    'missing service' => [[1 => 'Project'], [], 'Create a Work Service', 'Create a Project'],
    'missing project' => [[], [1 => 'Service'], 'Create a Project', 'Create a Work Service'],
] as $name => [$projects, $services, $expected, $unexpected]) {
    ob_start();
    $onboarding->invoke(null, $projects, $services);
    $html = (string)ob_get_clean();
    if (!str_contains($html, $expected)
        || str_contains($html, $unexpected)
        || str_contains($html, 'cb-work-so-ready-state')
        || str_contains($html, 'cb-work-so-ready')) {
        fwrite(STDERR, "SO-UX1 FAIL: $name guidance\n");
        exit(1);
    }
}
ob_start();
$onboarding->invoke(null, [], []);
$html = (string)ob_get_clean();
if (!str_contains($html, 'Create a Work Service')
    || !str_contains($html, 'Create a Project')
    || !str_contains($html, 'Customer Projects require a connected customer provider.')) {
    fwrite(STDERR, "SO-UX1 FAIL: completely unconfigured guidance\n");
    exit(1);
}
echo "Service Operations SO-UX1 first-run presentation: PASS (10 cases)\n";
