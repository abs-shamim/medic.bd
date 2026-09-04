<?php
declare(strict_types=1);

/**
 * MedicBD Premium Dynamic Robots.txt Generator
 *
 * File location: /public_html/robots.php
 * Public URL:    https://medic.bd/robots.txt
 *
 * Required .htaccess rule:
 * RewriteRule ^robots\.txt$ robots.php [L,QSA]
 *
 * This file reads Admin > Site Settings > Robots.txt Control values
 * from the site_settings table.
 */

header('Content-Type: text/plain; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: public, max-age=300, s-maxage=300');
header('Vary: Accept-Encoding');

/*
 * Loaded here (not just inside medic_robots_get_settings()) so APP_URL is
 * defined before medic_robots_site_url() runs below. Without this, the
 * site URL fallback used HTTP_HOST alone and silently dropped a subfolder
 * install's path (e.g. /medicbd), producing a broken sitemap URL.
 */
if (is_file(__DIR__ . '/config/config.php')) {
    require_once __DIR__ . '/config/config.php';
}

function medic_robots_clean_paths(string $value): array
{
    $paths = [];
    $lines = preg_split('/\R/u', $value) ?: [];

    foreach ($lines as $line) {
        $line = trim((string)$line);

        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        $line = preg_replace('/[\r\n\x00-\x1F\x7F]/u', '', $line);

        if ($line === '') {
            continue;
        }

        if ($line[0] !== '/') {
            $line = '/' . $line;
        }

        if (!in_array($line, $paths, true)) {
            $paths[] = $line;
        }
    }

    return $paths;
}

function medic_robots_site_url(): string
{
    if (defined('APP_URL')) {
        $appUrl = rtrim((string)APP_URL, '/');

        if (filter_var($appUrl, FILTER_VALIDATE_URL)) {
            return $appUrl;
        }
    }

    $isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $scheme = $isHttps ? 'https' : 'http';
    $host = preg_replace('/[^A-Za-z0-9.:-]/', '', (string)($_SERVER['HTTP_HOST'] ?? 'medic.bd'));

    return $scheme . '://' . ($host !== '' ? $host : 'medic.bd');
}

function medic_robots_crawler_catalog(): array
{
    return [
        ['key' => 'robots_crawler_googlebot', 'agent' => 'Googlebot', 'default' => 'allow'],
        ['key' => 'robots_crawler_bingbot', 'agent' => 'bingbot', 'default' => 'allow'],
        ['key' => 'robots_crawler_yandexbot', 'agent' => 'YandexBot', 'default' => 'allow'],
        ['key' => 'robots_crawler_baiduspider', 'agent' => 'Baiduspider', 'default' => 'allow'],
        ['key' => 'robots_crawler_duckduckbot', 'agent' => 'DuckDuckBot', 'default' => 'allow'],
        ['key' => 'robots_crawler_applebot', 'agent' => 'Applebot', 'default' => 'allow'],
        ['key' => 'robots_crawler_oai_searchbot', 'agent' => 'OAI-SearchBot', 'default' => 'allow'],
        ['key' => 'robots_crawler_perplexitybot', 'agent' => 'PerplexityBot', 'default' => 'allow'],
        ['key' => 'robots_crawler_gptbot', 'agent' => 'GPTBot', 'default' => 'block'],
        ['key' => 'robots_crawler_claudebot', 'agent' => 'ClaudeBot', 'default' => 'block'],
        ['key' => 'robots_crawler_google_extended', 'agent' => 'Google-Extended', 'default' => 'block'],
        ['key' => 'robots_crawler_ccbot', 'agent' => 'CCBot', 'default' => 'block'],
        ['key' => 'robots_crawler_bytespider', 'agent' => 'Bytespider', 'default' => 'block'],
        ['key' => 'robots_crawler_amazonbot', 'agent' => 'Amazonbot', 'default' => 'block'],
        ['key' => 'robots_crawler_applebot_extended', 'agent' => 'Applebot-Extended', 'default' => 'block'],
        ['key' => 'robots_crawler_meta_externalagent', 'agent' => 'meta-externalagent', 'default' => 'block'],
        ['key' => 'robots_crawler_cloudflare_rendering', 'agent' => 'CloudflareBrowserRenderingCrawler', 'default' => 'block'],
    ];
}

function medic_robots_normalize_mode(string $mode, string $default = 'follow'): string
{
    $mode = strtolower(trim($mode));

    if (in_array($mode, ['follow', 'allow', 'block'], true)) {
        return $mode;
    }

    return in_array($default, ['follow', 'allow', 'block'], true) ? $default : 'follow';
}

function medic_robots_get_settings(array $defaults): array
{
    $configFile = __DIR__ . '/config/config.php';

    if (!is_file($configFile)) {
        return $defaults;
    }

    try {
        require_once $configFile;

        foreach (['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_CHARSET'] as $constant) {
            if (!defined($constant)) {
                return $defaults;
            }
        }

        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;

        $pdo = new PDO(
            $dsn,
            DB_USER,
            defined('DB_PASS') ? DB_PASS : '',
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );

        $keys = array_keys($defaults);
        $placeholders = implode(',', array_fill(0, count($keys), '?'));

        $stmt = $pdo->prepare(
            "SELECT setting_key, setting_value
             FROM site_settings
             WHERE setting_key IN ({$placeholders})"
        );

        $stmt->execute($keys);

        foreach ($stmt->fetchAll() as $row) {
            $key = (string)($row['setting_key'] ?? '');

            if (array_key_exists($key, $defaults)) {
                $defaults[$key] = (string)($row['setting_value'] ?? '');
            }
        }
    } catch (Throwable $e) {
        // Serve safe defaults without exposing configuration details.
    }

    return $defaults;
}

function medic_robots_append_public_group(array &$lines, string $userAgent, array $allowPaths, array $disallowPaths): void
{
    $lines[] = '';
    $lines[] = 'User-agent: ' . $userAgent;
    $lines[] = 'Allow: /';

    foreach ($allowPaths as $path) {
        $lines[] = 'Allow: ' . $path;
    }

    foreach ($disallowPaths as $path) {
        $lines[] = 'Disallow: ' . $path;
    }
}

$siteUrl = medic_robots_site_url();

$defaults = [
    'robots_default_access' => 'allow',
    'robots_disallow_paths' => "/admin/\n/user/\n/ajax/\n/config/\n/includes/\n/.ssh/\n/README.md",
    'robots_allow_paths' => '',
    'robots_sitemap_url' => $siteUrl . '/sitemap.xml',
    'robots_content_signal' => 'search=yes,ai-train=no',
];

foreach (medic_robots_crawler_catalog() as $crawler) {
    $defaults[(string)$crawler['key']] = (string)$crawler['default'];
}

$settings = medic_robots_get_settings($defaults);

$defaultAccess = (string)($settings['robots_default_access'] ?? 'allow') === 'block' ? 'block' : 'allow';

$allowedSignals = [
    '',
    'search=yes,ai-train=no',
    'search=yes,ai-input=no,ai-train=no',
    'search=yes,ai-input=yes,ai-train=no',
    'search=yes,ai-input=yes,ai-train=yes',
];

$contentSignal = trim((string)($settings['robots_content_signal'] ?? ''));

if (!in_array($contentSignal, $allowedSignals, true)) {
    $contentSignal = 'search=yes,ai-train=no';
}

$disallowPaths = medic_robots_clean_paths((string)($settings['robots_disallow_paths'] ?? ''));
$allowPaths = medic_robots_clean_paths((string)($settings['robots_allow_paths'] ?? ''));

$sitemapUrl = trim((string)($settings['robots_sitemap_url'] ?? ''));

if (!filter_var($sitemapUrl, FILTER_VALIDATE_URL)) {
    $sitemapUrl = $siteUrl . '/sitemap.xml';
}

$lines = [
    '# MedicBD Robots.txt',
    '# Search Engine, AI Search and Crawler Access Rules',
    '',
    'User-agent: *',
];

if ($contentSignal !== '') {
    $lines[] = 'Content-Signal: ' . $contentSignal;
}

if ($defaultAccess === 'block') {
    foreach ($allowPaths as $path) {
        $lines[] = 'Allow: ' . $path;
    }

    $lines[] = 'Disallow: /';
} else {
    $lines[] = 'Allow: /';

    foreach ($allowPaths as $path) {
        $lines[] = 'Allow: ' . $path;
    }

    foreach ($disallowPaths as $path) {
        $lines[] = 'Disallow: ' . $path;
    }
}

foreach (medic_robots_crawler_catalog() as $crawler) {
    $key = (string)$crawler['key'];
    $agent = (string)$crawler['agent'];
    $mode = medic_robots_normalize_mode(
        (string)($settings[$key] ?? ''),
        (string)$crawler['default']
    );

    if ($mode === 'follow') {
        continue;
    }

    if ($mode === 'block') {
        $lines[] = '';
        $lines[] = 'User-agent: ' . $agent;
        $lines[] = 'Disallow: /';
        continue;
    }

    medic_robots_append_public_group($lines, $agent, $allowPaths, $disallowPaths);
}

$lines[] = '';
$lines[] = 'Sitemap: ' . $sitemapUrl;

$output = implode("\n", $lines) . "\n";

$etag = '"' . sha1($output) . '"';
header('ETag: ' . $etag);

$ifNoneMatch = trim((string)($_SERVER['HTTP_IF_NONE_MATCH'] ?? ''));

if ($ifNoneMatch !== '' && $ifNoneMatch === $etag) {
    http_response_code(304);
    exit;
}

echo $output;
