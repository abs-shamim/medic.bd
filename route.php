<?php
/**
 * Frontend Route Controller
 *
 * Keep this file focused on routing, language detection, SEO variables,
 * sitemap dispatching and clean URL handling.
 *
 * index.php should include this file first. For the homepage this file returns
 * control to index.php. For every other valid route it loads the correct page
 * and exits.
 */

require_once __DIR__ . '/includes/functions.php';

/*
|--------------------------------------------------------------------------
| Original Query Parameters
|--------------------------------------------------------------------------
| Keep the visitor's real query parameters before the router adds internal
| values such as `slug` or `category` for clean URLs. This prevents those
| internal values from incorrectly changing robots directives.
*/
$frontOriginalQuery = is_array($_GET) ? $_GET : [];
$GLOBALS['front_original_query'] = $frontOriginalQuery;

/*
|--------------------------------------------------------------------------
| Optional Blog Module
|--------------------------------------------------------------------------
| Keep the core directory website available while the blog files are being
| installed. Blog routes are registered only when all required public files
| exist. The blog pages load their own helpers as well.
*/
$blogModuleAvailable = is_file(__DIR__ . '/includes/blog-functions.php')
    && is_file(__DIR__ . '/blog.php')
    && is_file(__DIR__ . '/blog-post.php');

if ($blogModuleAvailable) {
    require_once __DIR__ . '/includes/blog-functions.php';
}

/*
|--------------------------------------------------------------------------
| PHP 7 Compatibility Helpers
|--------------------------------------------------------------------------
*/
if (!function_exists('str_starts_with')) {
    function str_starts_with(string $haystack, string $needle): bool
    {
        return $needle === '' || strpos($haystack, $needle) === 0;
    }
}

if (!function_exists('str_ends_with')) {
    function str_ends_with(string $haystack, string $needle): bool
    {
        return $needle === '' || substr($haystack, -strlen($needle)) === $needle;
    }
}

/*
|--------------------------------------------------------------------------
| Request Path Helper
|--------------------------------------------------------------------------
| Returns the URL path relative to APP_URL when the project is installed in
| a subfolder, for example https://example.com/medic.
*/
if (!function_exists('front_request_path')) {
    function front_request_path(): string
    {
        $requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $requestPath = trim((string) $requestPath, '/');

        $appPath = '';

        if (defined('APP_URL')) {
            $parsedAppPath = parse_url(APP_URL, PHP_URL_PATH);
            $appPath = trim((string) ($parsedAppPath ?? ''), '/');
        }

        if (
            $appPath !== ''
            && (
                $requestPath === $appPath
                || str_starts_with($requestPath, $appPath . '/')
            )
        ) {
            $requestPath = trim(substr($requestPath, strlen($appPath)), '/');
        }

        return $requestPath;
    }
}

/*
|--------------------------------------------------------------------------
| XML Sitemap Fallback Router
|--------------------------------------------------------------------------
| Apache should route sitemap URLs to sitemap.php where possible. This block
| keeps sitemap URLs working even when all requests land on index.php.
*/
$sitemapRequestPath = front_request_path();

$sitemapRoutes = [
    'sitemap.xml'                    => ['type' => 'index'],
    'sitemap-main.xml'               => ['type' => 'main'],
    'sitemap-doctors.xml'            => ['type' => 'doctors', 'page' => 1],
    'sitemap-hospitals.xml'          => ['type' => 'hospitals', 'page' => 1],
    'sitemap-specialties.xml'        => ['type' => 'specialties', 'page' => 1],
    'sitemap-doctor-directory.xml'   => ['type' => 'doctor-directory', 'page' => 1],
    'sitemap-hospital-directory.xml' => ['type' => 'hospital-directory', 'page' => 1],
    // Blog sitemap is registered only after the blog module files are installed.
    'sitemap-blogs.xml'             => ['type' => 'blogs', 'page' => 1],
    // Legacy sitemap URL. Keep this until Search Console no longer reports it.
    'sitemap-locations.xml'          => ['type' => 'locations', 'page' => 1],
];

if (!$blogModuleAvailable) {
    unset($sitemapRoutes['sitemap-blogs.xml']);
}

if (isset($sitemapRoutes[$sitemapRequestPath])) {
    $_GET['type'] = $sitemapRoutes[$sitemapRequestPath]['type'];

    if (isset($sitemapRoutes[$sitemapRequestPath]['page'])) {
        $_GET['page'] = (int) $sitemapRoutes[$sitemapRequestPath]['page'];
    }

    require __DIR__ . '/sitemap.php';
    exit;
}

if (
    preg_match(
        $blogModuleAvailable
            ? '#^sitemap-(doctors|hospitals|specialties|doctor-directory|hospital-directory|blogs|locations)-([1-9][0-9]*)\\.xml$#'
            : '#^sitemap-(doctors|hospitals|specialties|doctor-directory|hospital-directory|locations)-([1-9][0-9]*)\\.xml$#',
        $sitemapRequestPath,
        $sitemapMatches
    )
) {
    $_GET['type'] = $sitemapMatches[1];
    $_GET['page'] = (int) $sitemapMatches[2];

    require __DIR__ . '/sitemap.php';
    exit;
}

/*
|--------------------------------------------------------------------------
| Route And Language Detection
|--------------------------------------------------------------------------
| English is default. Bangla frontend routes begin with /bn/.
*/
$requestPath = front_request_path();
$route = preg_replace('/\.php$/i', '', trim((string) $requestPath, '/'));
$currentLang = 'en';

if ($route === 'bn') {
    $currentLang = 'bn';
    $route = '';
} elseif (str_starts_with($route, 'bn/')) {
    $currentLang = 'bn';
    $route = trim(substr($route, 3), '/');
}

if (!in_array($currentLang, ['en', 'bn'], true)) {
    $currentLang = 'en';
}

if (!defined('CURRENT_LANG')) {
    define('CURRENT_LANG', $currentLang);
}

$GLOBALS['front_route'] = $route;

/*
|--------------------------------------------------------------------------
| Translation Loading
|--------------------------------------------------------------------------
*/
$languageDirectory = __DIR__ . '/languages';
$defaultLanguageFile = $languageDirectory . '/en.php';
$currentLanguageFile = $languageDirectory . '/' . CURRENT_LANG . '.php';

$defaultTranslations = [];
$currentLanguageTranslations = [];
$translations = [];

if (is_file($defaultLanguageFile)) {
    $loadedDefaultTranslations = require $defaultLanguageFile;

    if (is_array($loadedDefaultTranslations)) {
        $defaultTranslations = $loadedDefaultTranslations;
        $translations = $defaultTranslations;
    }
}

if (CURRENT_LANG !== 'en' && is_file($currentLanguageFile)) {
    $loadedCurrentTranslations = require $currentLanguageFile;

    if (is_array($loadedCurrentTranslations)) {
        $currentLanguageTranslations = $loadedCurrentTranslations;
        $translations = array_merge($translations, $currentLanguageTranslations);
    }
} else {
    $currentLanguageTranslations = $defaultTranslations;
}

$GLOBALS['translations'] = $translations;

// Bangla SEO text should use only bn.php, never English fallback translations.
$GLOBALS['meta_translations'] = CURRENT_LANG === 'bn'
    ? $currentLanguageTranslations
    : $defaultTranslations;

/*
|--------------------------------------------------------------------------
| General Frontend Helpers
|--------------------------------------------------------------------------
*/
if (!function_exists('seo_slug_to_name')) {
    function seo_slug_to_name(string $slug): string
    {
        $slug = rawurldecode(trim($slug));
        $slug = str_replace('-', ' ', $slug);
        $slug = preg_replace('/\s+/u', ' ', $slug);

        return trim(ucwords((string) $slug));
    }
}

if (!function_exists('__t')) {
    function __t(string $key, string $fallback = ''): string
    {
        $translations = $GLOBALS['translations'] ?? [];
        $value = trim((string) ($translations[$key] ?? ''));

        return $value !== '' ? $value : ($fallback !== '' ? $fallback : $key);
    }
}

if (!function_exists('lang_text')) {
    function lang_text(string $english = '', string $bangla = ''): string
    {
        if (defined('CURRENT_LANG') && CURRENT_LANG === 'bn' && trim($bangla) !== '') {
            return $bangla;
        }

        return $english;
    }
}

if (!function_exists('front_url')) {
    function front_url(string $path = '', ?string $lang = null): string
    {
        $path = trim($path);

        if ($path !== '' && preg_match('/^https?:\/\//i', $path)) {
            return $path;
        }

        $lang = $lang ?: (defined('CURRENT_LANG') ? CURRENT_LANG : 'en');
        $path = trim($path, '/');

        if ($lang === 'bn') {
            return site_url($path === '' ? 'bn' : 'bn/' . $path);
        }

        return site_url($path);
    }
}

if (!function_exists('front_current_route_url')) {
    function front_current_route_url(?string $lang = null): string
    {
        $currentRoute = trim((string) ($GLOBALS['front_route'] ?? ''), '/');

        if ($currentRoute === 'index') {
            $currentRoute = '';
        }

        return front_url($currentRoute, $lang);
    }
}

/*
|--------------------------------------------------------------------------
| Site Settings And Homepage Helpers
|--------------------------------------------------------------------------
*/
if (!function_exists('front_table_exists')) {
    function front_table_exists(string $table): bool
    {
        global $pdo;

        if (!isset($pdo) || !$pdo instanceof PDO) {
            return false;
        }

        try {
            $statement = $pdo->prepare(
                'SELECT COUNT(*)
                 FROM INFORMATION_SCHEMA.TABLES
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table'
            );
            $statement->execute([':table' => $table]);

            return (int) $statement->fetchColumn() > 0;
        } catch (Throwable $exception) {
            return false;
        }
    }
}

if (!function_exists('front_column_exists')) {
    function front_column_exists(string $table, string $column): bool
    {
        global $pdo;

        if (!isset($pdo) || !$pdo instanceof PDO || !front_table_exists($table)) {
            return false;
        }

        try {
            $statement = $pdo->prepare(
                'SELECT COUNT(*)
                 FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = :table
                   AND COLUMN_NAME = :column'
            );
            $statement->execute([
                ':table' => $table,
                ':column' => $column,
            ]);

            return (int) $statement->fetchColumn() > 0;
        } catch (Throwable $exception) {
            return false;
        }
    }
}

if (!function_exists('front_site_setting')) {
    function front_site_setting(string $key, string $default = ''): string
    {
        global $pdo;

        static $settingsCache = null;

        if ($settingsCache === null) {
            $settingsCache = [];

            if (!isset($pdo) || !$pdo instanceof PDO || !front_table_exists('site_settings')) {
                return $default;
            }

            try {
                $rows = $pdo->query(
                    'SELECT setting_key, setting_value FROM site_settings'
                )->fetchAll(PDO::FETCH_ASSOC);

                foreach ($rows as $row) {
                    $settingsCache[(string) $row['setting_key']] = (string) $row['setting_value'];
                }
            } catch (Throwable $exception) {
                $settingsCache = [];
            }
        }

        $value = trim((string) ($settingsCache[$key] ?? ''));

        return $value !== '' ? $value : $default;
    }
}

if (!function_exists('front_site_name')) {
    function front_site_name(): string
    {
        return front_site_setting('site_name', defined('APP_NAME') ? APP_NAME : 'Medic');
    }
}

if (!function_exists('front_setting_int')) {
    function front_setting_int(string $key, int $default = 3, int $min = 1, int $max = 100): int
    {
        $value = (int) front_site_setting($key, (string) $default);

        if ($value < $min || $value > $max) {
            return $default;
        }

        return $value;
    }
}

if (!function_exists('front_setting_color')) {
    function front_setting_color(string $key, string $default): string
    {
        $value = front_site_setting($key, $default);

        return preg_match('/^#[0-9a-fA-F]{6}$/', $value) ? $value : $default;
    }
}

if (!function_exists('front_setting_url')) {
    function front_setting_url(string $key, string $default = ''): string
    {
        $value = front_site_setting($key, $default);

        if ($value === '') {
            return '';
        }

        if (preg_match('/^https?:\/\//i', $value)) {
            return $value;
        }

        return site_url(ltrim($value, './'));
    }
}

if (!function_exists('front_location_name_column')) {
    function front_location_name_column(string $table): string
    {
        if (defined('CURRENT_LANG') && CURRENT_LANG === 'bn' && front_column_exists($table, 'name_bn')) {
            return 'name_bn';
        }

        if (front_column_exists($table, 'name_en')) {
            return 'name_en';
        }

        if (front_column_exists($table, 'name')) {
            return 'name';
        }

        if (front_column_exists($table, 'name_bn')) {
            return 'name_bn';
        }

        return '';
    }
}

if (!function_exists('front_get_home_search_locations')) {
    function front_get_home_search_locations(int $limit = 100): array
    {
        global $pdo;

        $locations = [];
        $limit = max(1, min(300, $limit));

        if (!isset($pdo) || !$pdo instanceof PDO) {
            return [];
        }

        foreach (['districts', 'divisions'] as $table) {
            if (!front_table_exists($table)) {
                continue;
            }

            $nameColumn = front_location_name_column($table);

            if ($nameColumn === '') {
                continue;
            }

            try {
                $statement = $pdo->query(
                    "SELECT `{$nameColumn}` AS name
                     FROM `{$table}`
                     ORDER BY `{$nameColumn}` ASC
                     LIMIT {$limit}"
                );

                foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $name = trim((string) ($row['name'] ?? ''));

                    if ($name !== '') {
                        $locations[$name] = $name;
                    }
                }
            } catch (Throwable $exception) {
                // Keep homepage rendering even when an optional table is unavailable.
            }

            if (!empty($locations)) {
                break;
            }
        }

        return array_values($locations);
    }
}

if (!function_exists('front_home_search_action')) {
    function front_home_search_action(string $searchType): string
    {
        return in_array(strtolower(trim($searchType)), ['hospital', 'hospitals'], true)
            ? front_url('hospitals')
            : front_url('doctors');
    }
}

if (!function_exists('front_url_slug')) {
    function front_url_slug(string $text): string
    {
        $text = rawurldecode(trim($text));
        $text = preg_replace('/[^\p{L}\p{N}\s-]+/u', '', $text);
        $text = preg_replace('/[\s_]+/u', '-', (string) $text);
        $text = preg_replace('/-+/u', '-', (string) $text);

        $text = function_exists('mb_strtolower')
            ? mb_strtolower((string) $text, 'UTF-8')
            : strtolower((string) $text);

        return trim((string) $text, '-');
    }
}

if (!function_exists('front_clean_search_url')) {
    function front_clean_search_url(
        string $searchType,
        string $search = '',
        string $specialty = '',
        string $city = ''
    ): string {
        $searchType = strtolower(trim($searchType));
        $search = trim($search);
        $specialty = trim($specialty);
        $city = trim($city);
        $query = [];

        if ($search !== '') {
            $query['search'] = $search;
        }

        if (in_array($searchType, ['hospital', 'hospitals'], true)) {
            $path = 'hospitals';

            if ($city !== '') {
                $path .= '/' . front_url_slug($city);
            }
        } else {
            $path = 'doctors';

            if ($city !== '') {
                $path .= '/' . front_url_slug($city);
            }

            if ($specialty !== '') {
                $path .= '/' . front_url_slug($specialty);
            }
        }

        $url = front_url($path);

        return $query === [] ? $url : $url . '?' . http_build_query($query);
    }
}

/*
|--------------------------------------------------------------------------
| Redirect Old Homepage Search Query URLs
|--------------------------------------------------------------------------
*/
if (($route === 'doctors' || $route === 'hospitals') && !empty($_GET['search_type'])) {
    $cleanSearchUrl = front_clean_search_url(
        (string) ($_GET['search_type'] ?? ($route === 'hospitals' ? 'hospitals' : 'doctors')),
        (string) ($_GET['search'] ?? ''),
        (string) ($_GET['specialty'] ?? ''),
        (string) ($_GET['city'] ?? '')
    );

    header('Location: ' . $cleanSearchUrl, true, 301);
    exit;
}

/*
|--------------------------------------------------------------------------
| SEO Meta Helpers
|--------------------------------------------------------------------------
*/
if (!function_exists('front_title_case')) {
    function front_title_case(string $text): string
    {
        $text = rawurldecode(trim($text));
        $text = str_replace(['-', '_'], ' ', $text);
        $text = trim((string) preg_replace('/\s+/u', ' ', $text));

        if ($text === '') {
            return '';
        }

        return function_exists('mb_convert_case')
            ? mb_convert_case($text, MB_CASE_TITLE, 'UTF-8')
            : ucwords($text);
    }
}

if (!function_exists('front_meta_join')) {
    function front_meta_join(array $parts, string $separator = ' | '): string
    {
        $cleanParts = [];

        foreach ($parts as $part) {
            $part = trim((string) $part);

            if ($part !== '') {
                $cleanParts[] = $part;
            }
        }

        return implode($separator, $cleanParts);
    }
}

if (!function_exists('front_meta_fallback')) {
    function front_meta_fallback(string $english, string $bangla): string
    {
        return defined('CURRENT_LANG') && CURRENT_LANG === 'bn' ? $bangla : $english;
    }
}

if (!function_exists('front_meta_translate')) {
    function front_meta_translate(string $key, array $replace = [], string $fallback = ''): string
    {
        $metaTranslations = $GLOBALS['meta_translations'] ?? [];
        $value = trim((string) ($metaTranslations[$key] ?? ''));

        if ($value === '') {
            $value = $fallback;
        }

        foreach ($replace as $name => $replacement) {
            $value = str_replace(
                ['{' . $name . '}', ':' . $name, '%' . $name . '%'],
                (string) $replacement,
                $value
            );
        }

        return trim((string) $value);
    }
}

if (!function_exists('front_meta_route_filters')) {
    function front_meta_route_filters(string $routeValue, string $prefix): string
    {
        $routeValue = trim($routeValue, '/');
        $prefix = trim($prefix, '/');
        $remaining = preg_replace(
            '#^' . preg_quote($prefix, '#') . '/?#u',
            '',
            $routeValue
        );

        $segments = array_filter(explode('/', (string) $remaining));
        $segments = array_map('front_title_case', $segments);

        return implode(', ', $segments);
    }
}


if (!function_exists('front_static_page_meta')) {
    function front_static_page_meta(string $routeValue, array $baseMeta): ?array
    {
        $routeValue = strtolower(trim($routeValue, '/'));

        if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $routeValue)) {
            return null;
        }

        require_once __DIR__ . '/includes/static-page-helper.php';

        $page = medic_static_page_get_by_slug($routeValue, true);

        if ($page === null) {
            return null;
        }

        $title = medic_static_page_replace_tokens(
            medic_static_page_localized_value($page, 'meta_title')
        );
        $pageTitle = medic_static_page_replace_tokens(
            medic_static_page_localized_value($page, 'title')
        );
        $description = medic_static_page_replace_tokens(
            medic_static_page_localized_value($page, 'meta_description')
        );
        $intro = medic_static_page_replace_tokens(
            medic_static_page_localized_value($page, 'intro')
        );

        $baseMeta['title'] = trim($title) !== ''
            ? $title
            : front_meta_join([$pageTitle, front_site_name()]);
        $baseMeta['description'] = trim($description) !== '' ? $description : $intro;
        $baseMeta['canonical'] = front_current_route_url(CURRENT_LANG);
        $baseMeta['robots'] = 'index,follow';

        return $baseMeta;
    }
}

if (!function_exists('front_meta_data')) {
    function front_meta_data(string $routeValue): array
    {
        $routeValue = trim($routeValue, '/');
        $siteName = front_site_name();
        $siteTagline = front_meta_translate(
            'site_tagline',
            [],
            front_meta_fallback('Doctor and Hospital Directory', 'ডাক্তার ও হাসপাতাল ডিরেক্টরি')
        );

        $meta = [
            'title' => front_meta_translate(
                'meta_default_title',
                ['site' => $siteName],
                front_meta_join([$siteName, $siteTagline])
            ),
            'description' => front_meta_translate(
                'meta_default_description',
                ['site' => $siteName],
                front_meta_fallback(
                    'Find trusted doctors, hospitals, specialties and appointment information on {site}.',
                    '{site}-এ বিশ্বস্ত ডাক্তার, হাসপাতাল, চিকিৎসা বিশেষজ্ঞতা ও অ্যাপয়েন্টমেন্ট তথ্য খুঁজুন।'
                )
            ),
            'keywords' => front_meta_translate(
                'meta_default_keywords',
                ['site' => $siteName],
                front_meta_fallback(
                    'doctors, hospitals, specialists, medical directory, appointments, healthcare, {site}',
                    'ডাক্তার, হাসপাতাল, বিশেষজ্ঞ চিকিৎসক, মেডিকেল ডিরেক্টরি, অ্যাপয়েন্টমেন্ট, স্বাস্থ্যসেবা, {site}'
                )
            ),
            'robots' => 'index,follow',
            'canonical' => front_current_route_url(CURRENT_LANG),
            'image' => front_setting_url('default_og_image', 'assets/images/default-og-image.webp'),
            'locale' => CURRENT_LANG === 'bn' ? 'bn_BD' : 'en_US',
        ];

        if ($routeValue === '' || $routeValue === 'index') {
            $meta['title'] = front_meta_translate(
                'meta_home_title',
                ['site' => $siteName],
                front_meta_join([$siteName, $siteTagline])
            );
            $meta['description'] = front_meta_translate(
                'meta_home_description',
                ['site' => $siteName],
                front_meta_fallback(
                    'Find doctors, hospitals, medical specialties and appointment information on {site}.',
                    '{site}-এ ডাক্তার, হাসপাতাল, চিকিৎসা বিশেষজ্ঞতা এবং অ্যাপয়েন্টমেন্ট তথ্য খুঁজুন।'
                )
            );

            return $meta;
        }

        $dynamicStaticMeta = front_static_page_meta($routeValue, $meta);

        if ($dynamicStaticMeta !== null) {
            return $dynamicStaticMeta;
        }

        if ($routeValue === 'blog') {
            $meta['title'] = front_meta_fallback(
                front_meta_join(['Health Blog', $siteName]),
                front_meta_join(['স্বাস্থ্য ব্লগ', $siteName])
            );
            $meta['description'] = front_meta_fallback(
                'Read trusted health articles, treatment information and healthier-living guides on ' . $siteName . '.',
                $siteName . '-এ স্বাস্থ্য, চিকিৎসা ও সুস্থ জীবনযাপন বিষয়ে তথ্যবহুল লেখা পড়ুন।'
            );

            return $meta;
        }

        $staticPages = [
            'doctors' => [
                'label' => front_meta_fallback('Doctors', 'ডাক্তার তালিকা'),
                'description' => front_meta_fallback(
                    'Find trusted doctors, specialist doctors, hospitals and appointment information on {site}.',
                    '{site}-এ বিশ্বস্ত ডাক্তার, বিশেষজ্ঞ চিকিৎসক, হাসপাতাল ও অ্যাপয়েন্টমেন্ট তথ্য খুঁজুন।'
                ),
            ],
            'hospitals' => [
                'label' => front_meta_fallback('Hospitals', 'হাসপাতাল তালিকা'),
                'description' => front_meta_fallback(
                    'Find hospitals, departments, services, emergency contacts and appointment information on {site}.',
                    '{site}-এ হাসপাতাল, বিভাগ, চিকিৎসাসেবা, জরুরি যোগাযোগ ও অ্যাপয়েন্টমেন্ট তথ্য খুঁজুন।'
                ),
            ],
            'specialties' => [
                'label' => front_meta_fallback('Medical Specialties', 'চিকিৎসা বিশেষজ্ঞতা'),
                'description' => front_meta_fallback(
                    'Browse medical specialties and find relevant doctors and hospitals on {site}.',
                    '{site}-এ চিকিৎসা বিশেষজ্ঞতা অনুযায়ী ডাক্তার ও হাসপাতাল খুঁজুন।'
                ),
            ],
            'contact' => [
                'label' => front_meta_fallback('Contact Us', 'যোগাযোগ করুন'),
                'description' => front_meta_fallback(
                    'Contact {site} for doctor, hospital and appointment information.',
                    'ডাক্তার, হাসপাতাল ও অ্যাপয়েন্টমেন্ট সংক্রান্ত তথ্যের জন্য {site}-এর সাথে যোগাযোগ করুন।'
                ),
            ],
        ];

        if (isset($staticPages[$routeValue])) {
            $meta['title'] = front_meta_join([$staticPages[$routeValue]['label'], $siteName]);
            $meta['description'] = str_replace('{site}', $siteName, $staticPages[$routeValue]['description']);

            return $meta;
        }

        if (preg_match('#^doctor/([^/]+)/write-review/?$#u', $routeValue, $matches)) {
            $doctorName = front_title_case($matches[1]);
            $meta['title'] = front_meta_fallback(
                front_meta_join(['Write a Review for ' . $doctorName, $siteName]),
                front_meta_join([$doctorName . '-এর জন্য রিভিউ লিখুন', $siteName])
            );
            $meta['description'] = front_meta_fallback(
                'Share your experience and write a review for ' . $doctorName . ' on ' . $siteName . '.',
                $siteName . '-এ ' . $doctorName . '-এর সেবা সম্পর্কে আপনার অভিজ্ঞতা ও রিভিউ শেয়ার করুন।'
            );
            $meta['robots'] = 'noindex,follow';

            return $meta;
        }

        if (preg_match('#^doctor/([^/]+)/?$#u', $routeValue, $matches)) {
            $doctorName = front_title_case($matches[1]);
            $meta['title'] = front_meta_join([$doctorName, $siteName]);
            $meta['description'] = front_meta_fallback(
                'View profile, specialty, hospital, chamber and appointment information for ' . $doctorName . ' on ' . $siteName . '.',
                $siteName . '-এ ' . $doctorName . '-এর প্রোফাইল, বিশেষজ্ঞতা, হাসপাতাল, চেম্বার ও অ্যাপয়েন্টমেন্ট তথ্য দেখুন।'
            );

            return $meta;
        }

        if (preg_match('#^hospital/([^/]+)/?$#u', $routeValue, $matches)) {
            $hospitalName = front_title_case($matches[1]);
            $meta['title'] = front_meta_join([$hospitalName, $siteName]);
            $meta['description'] = front_meta_fallback(
                'View departments, services, location, emergency contacts and appointment information for ' . $hospitalName . ' on ' . $siteName . '.',
                $siteName . '-এ ' . $hospitalName . '-এর বিভাগ, চিকিৎসাসেবা, অবস্থান, জরুরি যোগাযোগ ও অ্যাপয়েন্টমেন্ট তথ্য দেখুন।'
            );

            return $meta;
        }

        if (preg_match('#^specialty/([^/]+)/?$#u', $routeValue, $matches)) {
            $specialtyName = front_title_case($matches[1]);
            $meta['title'] = front_meta_fallback(
                front_meta_join([$specialtyName . ' Doctors and Hospitals', $siteName]),
                front_meta_join([$specialtyName . ' বিশেষজ্ঞ ডাক্তার ও হাসপাতাল', $siteName])
            );
            $meta['description'] = front_meta_fallback(
                'Find ' . $specialtyName . ' doctors, hospitals, chambers and appointment information on ' . $siteName . '.',
                $siteName . '-এ ' . $specialtyName . ' বিশেষজ্ঞ ডাক্তার, হাসপাতাল, চেম্বার ও অ্যাপয়েন্টমেন্ট তথ্য খুঁজুন।'
            );

            return $meta;
        }

        if (str_starts_with($routeValue, 'doctors/')) {
            $filters = front_meta_route_filters($routeValue, 'doctors');
            $meta['title'] = front_meta_fallback(
                front_meta_join(['Doctors in ' . $filters, $siteName]),
                front_meta_join([$filters . '-এর ডাক্তার', $siteName])
            );
            $meta['description'] = front_meta_fallback(
                'Find doctors, specialist doctors and appointment information in ' . $filters . ' on ' . $siteName . '.',
                $siteName . '-এ ' . $filters . ' এলাকার ডাক্তার, বিশেষজ্ঞ চিকিৎসক ও অ্যাপয়েন্টমেন্ট তথ্য খুঁজুন।'
            );

            return $meta;
        }

        if (str_starts_with($routeValue, 'hospitals/')) {
            $filters = front_meta_route_filters($routeValue, 'hospitals');
            $meta['title'] = front_meta_fallback(
                front_meta_join(['Hospitals in ' . $filters, $siteName]),
                front_meta_join([$filters . '-এর হাসপাতাল', $siteName])
            );
            $meta['description'] = front_meta_fallback(
                'Find hospitals, services, emergency contacts and appointment information in ' . $filters . ' on ' . $siteName . '.',
                $siteName . '-এ ' . $filters . ' এলাকার হাসপাতাল, চিকিৎসাসেবা, জরুরি যোগাযোগ ও অ্যাপয়েন্টমেন্ট তথ্য খুঁজুন।'
            );
        }

        return $meta;
    }
}

$routeMeta = front_meta_data($route);

$meta_title = $meta_title ?? $routeMeta['title'];
$page_title = $page_title ?? $meta_title;
$meta_description = $meta_description ?? $routeMeta['description'];
$meta_keywords = $meta_keywords ?? $routeMeta['keywords'];
$meta_robots = $meta_robots ?? $routeMeta['robots'];
$robots_meta = $robots_meta ?? $meta_robots;
$canonical_url = $canonical_url ?? $routeMeta['canonical'];
$meta_image = $meta_image ?? $routeMeta['image'];
$meta_locale = $meta_locale ?? $routeMeta['locale'];

/*
|--------------------------------------------------------------------------
| Query URL Robots Policy
|--------------------------------------------------------------------------
| The shared frontend header reads `$robots_meta`. Use the original request
| query only, because clean blog routes add `slug` and `category` internally
| after this point.
*/
if (!empty($frontOriginalQuery)) {
    $meta_robots = 'noindex,follow';
    $robots_meta = 'noindex,follow';
}

/*
|--------------------------------------------------------------------------
| Internal Page Loader
|--------------------------------------------------------------------------
*/
if (!function_exists('front_load_page')) {
    function front_load_page(string $fileName): void
    {
        $file = __DIR__ . '/' . ltrim($fileName, '/');

        if (is_file($file)) {
            require $file;
            exit;
        }

        http_response_code(404);
        exit('Required page file was not found.');
    }
}

/*
|--------------------------------------------------------------------------
| Exact Static Routes
|--------------------------------------------------------------------------
*/
$staticRoutes = [
    'doctors' => 'doctors.php',
    'hospitals' => 'hospitals.php',
    'specialties' => 'specialties.php',
    'contact' => 'contact.php',
];

if ($blogModuleAvailable) {
    $staticRoutes['blog'] = 'blog.php';
}

if ($route !== '' && isset($staticRoutes[$route])) {
    front_load_page($staticRoutes[$route]);
}

/*
|--------------------------------------------------------------------------
| Blog Routes
| Examples:
| /blog/category/health-tips
| /blog/healthy-heart-habits
| /bn/blog/category/health-tips
| /bn/blog/healthy-heart-habits
|--------------------------------------------------------------------------
*/
if ($blogModuleAvailable && preg_match('#^blog/category/([^/]+)/?$#u', $route, $matches)) {
    $_GET['category'] = rawurldecode((string) $matches[1]);
    front_load_page('blog.php');
}

if ($blogModuleAvailable && preg_match('#^blog/([^/]+)/?$#u', $route, $matches)) {
    $_GET['slug'] = rawurldecode((string) $matches[1]);
    front_load_page('blog-post.php');
}

/*
|--------------------------------------------------------------------------
| Dynamic Static Page Routes
| Example: /about-us or /bn/about-us
|--------------------------------------------------------------------------
*/
if (preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $route)) {
    require_once __DIR__ . '/includes/static-page-helper.php';

    if (medic_static_page_get_by_slug($route, true) !== null) {
        $_GET['slug'] = $route;
        front_load_page('static-page.php');
    }
}

/*
|--------------------------------------------------------------------------
| Specialty Article Page
| Example: /specialty/cardiology
|--------------------------------------------------------------------------
*/
if (preg_match('#^specialty/([^/]+)/?$#u', $route, $matches)) {
    $_GET['slug'] = rawurldecode((string) $matches[1]);
    front_load_page('specialty.php');
}

/*
|--------------------------------------------------------------------------
| Doctor Review Page
| Example: /doctor/dr-farid-uddin/write-review
|--------------------------------------------------------------------------
*/
if (preg_match('#^doctor/([a-zA-Z0-9-]+)/write-review/?$#', $route, $matches)) {
    $_GET['slug'] = $matches[1];
    front_load_page('write-review.php');
}

/*
|--------------------------------------------------------------------------
| Legacy Doctor Profile URL
| Example: /doctors/dhaka/dhaka/cardiology/dr-farid-uddin
|
| This rule must remain before the general doctors directory rule.
|--------------------------------------------------------------------------
*/
if (
    preg_match(
        '#^doctors/[^/]+/[^/]+/[^/]+/([a-zA-Z0-9-]+)/?$#',
        $route,
        $matches
    )
) {
    $_GET['slug'] = $matches[1];
    front_load_page('doctor.php');
}

/*
|--------------------------------------------------------------------------
| Doctors Directory Routes
| Examples:
| /doctors/dhaka
| /doctors/dhaka/cardiology
| /doctors/dhaka/mirpur/cardiology
|--------------------------------------------------------------------------
*/
if ($route === 'doctors' || str_starts_with($route, 'doctors/')) {
    front_load_page('doctors.php');
}

/*
|--------------------------------------------------------------------------
| Hospitals Directory Routes
| Examples:
| /hospitals/dhaka
| /hospitals/dhaka/mirpur
| /hospitals/dhaka/private-hospital
|--------------------------------------------------------------------------
*/
if ($route === 'hospitals' || str_starts_with($route, 'hospitals/')) {
    front_load_page('hospitals.php');
}

/*
|--------------------------------------------------------------------------
| Single Doctor And Hospital Pages
|--------------------------------------------------------------------------
*/
if (preg_match('#^doctor/([a-zA-Z0-9-]+)/?$#', $route, $matches)) {
    $_GET['slug'] = $matches[1];
    front_load_page('doctor.php');
}

if (preg_match('#^hospital/([a-zA-Z0-9-]+)/?$#', $route, $matches)) {
    $_GET['slug'] = $matches[1];
    front_load_page('hospital.php');
}

/*
|--------------------------------------------------------------------------
| Homepage
|--------------------------------------------------------------------------
| index.php continues rendering when the URL is /, /index or /bn.
*/
if ($route === '' || $route === 'index') {
    return;
}

/*
|--------------------------------------------------------------------------
| 404 Page
|--------------------------------------------------------------------------
*/
http_response_code(404);

$meta_title = front_meta_translate(
    'meta_404_title',
    ['site' => front_site_name()],
    front_meta_fallback(
        '404 - Page Not Found | ' . front_site_name(),
        '৪০৪ - পেজটি পাওয়া যায়নি | ' . front_site_name()
    )
);
$page_title = $meta_title;
$meta_description = front_meta_translate(
    'meta_404_description',
    ['site' => front_site_name()],
    front_meta_fallback(
        'The page you are looking for does not exist.',
        'আপনি যে পেজটি খুঁজছেন সেটি পাওয়া যায়নি।'
    )
);
$meta_keywords = front_meta_translate(
    'meta_404_keywords',
    ['site' => front_site_name()],
    front_meta_fallback(
        '404 page not found, ' . front_site_name(),
        '৪০৪ পেজ পাওয়া যায়নি, ' . front_site_name()
    )
);
$meta_robots = 'noindex,follow';
$robots_meta = 'noindex,follow';
$canonical_url = front_current_route_url(CURRENT_LANG);

include __DIR__ . '/includes/header.php';
?>
<style>
    body { background: #f6f8fa; }
    .medic-error-page { padding: 80px 16px; }
    .medic-container { max-width: 1180px; margin: 0 auto; padding: 0 16px; }
    .medic-error-card {
        max-width: 720px; margin: 0 auto; padding: 40px; text-align: center;
        background: #fff; border: 1px solid #d0d7de; border-radius: 12px;
        box-shadow: 0 8px 24px rgba(140, 149, 159, .15);
    }
    .medic-error-card h1 { margin: 0 0 12px; color: #24292f; font-size: 34px; }
    .medic-error-card p { margin: 0 0 24px; color: #57606a; font-size: 16px; }
    .medic-btn {
        display: inline-flex; align-items: center; justify-content: center;
        min-height: 40px; padding: 0 18px; border: 1px solid rgba(27,31,36,.15);
        border-radius: 6px; background: #2da44e; color: #fff; text-decoration: none;
        font-size: 14px; font-weight: 600;
    }
</style>
<main class="medic-error-page">
    <div class="medic-container">
        <div class="medic-error-card">
            <h1><?= e(__t('page_not_found_title', '404 - Page Not Found')) ?></h1>
            <p><?= e(__t('page_not_found_text', 'The page you are looking for does not exist.')) ?></p>
            <a href="<?= e(front_url()) ?>" class="medic-btn">
                <?= e(__t('back_to_home', 'Back to Home')) ?>
            </a>
        </div>
    </div>
</main>
<?php
include __DIR__ . '/includes/footer.php';
exit;
