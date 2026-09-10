<?php
/*
|--------------------------------------------------------------------------
| Dynamic Doctor Directory Step Flow
|--------------------------------------------------------------------------
| Step 1: /doctors/
|         Shows all divisions dynamically from `divisions` table.
|
| Step 2: /doctors/{division-slug}/
|         Shows all districts dynamically from `districts` table.
|
| Step 3: /doctors/{district-slug}/
|         Shows all specialties dynamically from `specialties` table.
|
| Step 4: /doctors/{district-slug}/{specialty-slug}/
|         Shows doctor list using the existing doctor card design.
|
| Optional thana URL:
| /doctors/{district-slug}/{thana-slug}/{specialty-slug}/
|
| Important:
| - The doctor card design is not changed.
| - Doctor cards still use: includes/doctor-card.php
| - Everything is dynamic from database tables.
| - UI text is English.
|--------------------------------------------------------------------------
*/

/*
|--------------------------------------------------------------------------
| Frontend Language Detection
|--------------------------------------------------------------------------
| English is the default language.
| Bangla pages use the /bn/ URL prefix.
|--------------------------------------------------------------------------
*/

if (!defined('CURRENT_LANG')) {
    $current_request_path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    $current_request_path = trim((string)$current_request_path, '/');

    if (defined('APP_URL')) {
        $app_path = trim((string)(parse_url(APP_URL, PHP_URL_PATH) ?? ''), '/');

        if ($app_path !== '' && str_starts_with($current_request_path, $app_path)) {
            $current_request_path = trim(substr($current_request_path, strlen($app_path)), '/');
        }
    }

    define('CURRENT_LANG', ($current_request_path === 'bn' || str_starts_with($current_request_path, 'bn/')) ? 'bn' : 'en');
}

$lang = defined('CURRENT_LANG') && CURRENT_LANG === 'bn' ? 'bn' : 'en';

/*
|--------------------------------------------------------------------------
| Frontend Translation Fallbacks
|--------------------------------------------------------------------------
| These fallbacks keep this page safe when it is opened directly.
|--------------------------------------------------------------------------
*/

if (!function_exists('__t')) {
    function __t(string $key, string $fallback = ''): string
    {
        $lang = defined('CURRENT_LANG') && CURRENT_LANG === 'bn' ? 'bn' : 'en';
        $default_file = DP_ROOT_PATH . '/languages/en.php';
        $lang_file = DP_ROOT_PATH . '/languages/' . $lang . '.php';

        static $translations = null;

        if ($translations === null) {
            $translations = [];

            if (file_exists($default_file)) {
                $default_translations = require $default_file;

                if (is_array($default_translations)) {
                    $translations = $default_translations;
                }
            }

            if ($lang !== 'en' && file_exists($lang_file)) {
                $current_translations = require $lang_file;

                if (is_array($current_translations)) {
                    $translations = array_merge($translations, $current_translations);
                }
            }
        }

        $value = $translations[$key] ?? '';

        if (trim((string)$value) !== '') {
            return (string)$value;
        }

        return $fallback !== '' ? $fallback : $key;
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
            return site_url($path !== '' ? 'bn/' . $path : 'bn');
        }

        return site_url($path);
    }
}


/*
|--------------------------------------------------------------------------
| Site Settings Helpers
|--------------------------------------------------------------------------
| This page now follows admin/site-settings.php values.
|--------------------------------------------------------------------------
*/

if (!function_exists('dp_site_setting')) {
    function dp_site_setting(string $key, string $default = ''): string
    {
        global $pdo;

        static $settings_cache = null;

        if ($settings_cache === null) {
            $settings_cache = [];

            try {
                if (function_exists('dp_table_exists') && !dp_table_exists('site_settings')) {
                    return $default;
                }

                $stmt = $pdo->query("SELECT setting_key, setting_value FROM site_settings");
                $rows = $stmt->fetchAll();

                foreach ($rows as $row) {
                    $settings_cache[(string)$row['setting_key']] = (string)$row['setting_value'];
                }
            } catch (Throwable $e) {
                $settings_cache = [];
            }
        }

        $value = trim((string)($settings_cache[$key] ?? ''));

        return $value !== '' ? $value : $default;
    }
}

if (!function_exists('dp_site_name')) {
    function dp_site_name(): string
    {
        return dp_site_setting('site_name', defined('APP_NAME') ? APP_NAME : 'Deluti');
    }
}

if (!function_exists('dp_setting_int')) {
    function dp_setting_int(string $key, int $default = 10, int $min = 1, int $max = 100): int
    {
        $value = (int)dp_site_setting($key, (string)$default);

        if ($value < $min) {
            return $default;
        }

        if ($value > $max) {
            return $max;
        }

        return $value;
    }
}

if (!function_exists('dp_setting_url')) {
    function dp_setting_url(string $key, string $default = ''): string
    {
        $value = dp_site_setting($key, $default);

        if ($value === '') {
            return '';
        }

        if (preg_match('/^https?:\/\//i', $value)) {
            return $value;
        }

        $value = ltrim($value, './');

        if (function_exists('site_url')) {
            return site_url($value);
        }

        return '/' . $value;
    }
}

if (!function_exists('dp_image_url')) {
    function dp_image_url(string $image): string
    {
        $image = trim($image);

        if ($image === '') {
            return '';
        }

        if (preg_match('/^https?:\/\//i', $image)) {
            return $image;
        }

        $image = ltrim($image, '/');

        return function_exists('site_url') ? site_url($image) : '/' . $image;
    }
}


/*
|--------------------------------------------------------------------------
| Basic Helpers
|--------------------------------------------------------------------------
*/

function dp_url_slug(string $text): string
{
    $text = trim($text);
    $text = preg_replace('/[^\p{L}\p{N}\s-]+/u', '', $text);
    $text = preg_replace('/[\s_]+/u', '-', (string)$text);
    $text = preg_replace('/-+/u', '-', (string)$text);

    if (function_exists('mb_strtolower')) {
        $text = mb_strtolower($text, 'UTF-8');
    } else {
        $text = strtolower($text);
    }

    return trim((string)$text, '-');
}

function dp_slug_to_name(string $slug): string
{
    $slug = rawurldecode(trim($slug));
    $slug = str_replace('-', ' ', $slug);
    $slug = preg_replace('/\s+/u', ' ', (string)$slug);

    return trim(ucwords((string)$slug));
}

function dp_current_doctors_segments(): array
{
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
    $path = trim((string)$path, '/');

    if (defined('APP_URL')) {
        $app_path = trim((string)(parse_url(APP_URL, PHP_URL_PATH) ?? ''), '/');

        if ($app_path !== '' && str_starts_with($path, $app_path)) {
            $path = trim(substr($path, strlen($app_path)), '/');
        }
    }

    if ($path === 'bn') {
        $path = '';
    } elseif (str_starts_with($path, 'bn/')) {
        $path = trim(substr($path, 3), '/');
    }

    $parts = explode('/', $path);
    $doctors_index = array_search('doctors', $parts, true);

    if ($doctors_index === false) {
        return [];
    }

    $segments = array_slice($parts, $doctors_index + 1);

    return array_values(array_filter($segments, static function ($segment) {
        return trim((string)$segment) !== '';
    }));
}

function dp_clean_query(array $params): string
{
    $clean = [];

    foreach ($params as $key => $value) {
        $value = trim((string)$value);

        if ($value !== '') {
            $clean[$key] = $value;
        }
    }

    return http_build_query($clean);
}

function dp_doctors_url(array $params = []): string
{
    $division = trim((string)($params['division_slug'] ?? ''));
    $district = trim((string)($params['district_slug'] ?? ''));
    $thana = trim((string)($params['thana_slug'] ?? ''));
    $specialty = trim((string)($params['specialty_slug'] ?? ''));

    $path = 'doctors';

    /*
     * URL rule:
     * /doctors/{division-slug}/ is used only for the division step.
     * After district selection, the division slug is removed:
     * /doctors/{district-slug}/
     * /doctors/{district-slug}/{thana-slug}/
     * /doctors/{district-slug}/{specialty-slug}/
     * /doctors/{district-slug}/{thana-slug}/{specialty-slug}/
     */
    if ($district !== '') {
        $path .= '/' . dp_url_slug($district);

        if ($thana !== '' && $specialty !== '') {
            $path .= '/' . dp_url_slug($thana) . '/' . dp_url_slug($specialty);
        } elseif ($thana !== '') {
            $path .= '/' . dp_url_slug($thana);
        } elseif ($specialty !== '') {
            $path .= '/' . dp_url_slug($specialty);
        }
    } elseif ($division !== '' && $specialty !== '') {
        /*
         * Specialty-first flow, division step:
         * /doctors/{division-slug}/{specialty-slug}/
         */
        $path .= '/' . dp_url_slug($division) . '/' . dp_url_slug($specialty);
    } elseif ($specialty !== '') {
        /*
         * Specialty-only URL support:
         * /doctors/cardiology
         * /doctors/medicine-specialist
         */
        $path .= '/' . dp_url_slug($specialty);
    } elseif ($division !== '') {
        $path .= '/' . dp_url_slug($division);
    }

    $page = (int)($params['page'] ?? 1);

    $query = dp_clean_query([
        'search' => $params['search'] ?? '',
        'page' => $page > 1 ? (string)$page : '',
    ]);

    return front_url($path) . ($query ? '?' . $query : '');
}

function dp_safe_int($value): int
{
    return max(0, (int)$value);
}

function dp_current_page(): int
{
    $page = (int)($_GET['page'] ?? 1);

    return max(1, $page);
}

function dp_pagination_url(string $base_url, int $page, string $search = ''): string
{
    $page = max(1, $page);

    $query = dp_clean_query([
        'search' => $search,
        'page' => $page > 1 ? (string)$page : '',
    ]);

    return $base_url . ($query ? '?' . $query : '');
}

