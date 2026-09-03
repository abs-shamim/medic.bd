<?php
/**
 * Hospital Directory Bootstrap
 *
 * Shared helpers for hospital listing, localization, URL generation
 * and translation loading.
 */

$hospitals_page_version = 'hospital-directory-bangla-heading-20260629';

/*
|--------------------------------------------------------------------------
| PHP Compatibility Helper
|--------------------------------------------------------------------------
*/
if (!function_exists('hp_starts_with')) {
    function hp_starts_with(string $haystack, string $needle): bool
    {
        return $needle === '' || strpos($haystack, $needle) === 0;
    }
}

/*
|--------------------------------------------------------------------------
| Immutable Directory Language Resolver
|--------------------------------------------------------------------------
| The hospital directory always trusts the request path first.
|
| /bn/hospitals/...           => bn
| /project/bn/hospitals/...   => bn
| /hospitals/...              => en
|
| This does not depend on APP_URL and does not depend on a mutable global
| language variable that can be changed later by header/footer code.
|--------------------------------------------------------------------------
*/
function hp_directory_lang(): string
{
    $request_uri = $_SERVER['REQUEST_URI'] ?? '/';
    $request_path = parse_url((string) $request_uri, PHP_URL_PATH);

    if (!is_string($request_path) || $request_path === '') {
        $request_path = '/';
    }

    $segments = array_values(array_filter(
        explode('/', trim($request_path, '/')),
        static function ($segment): bool {
            return trim((string) $segment) !== '';
        }
    ));

    $segments = array_map(
        static function ($segment): string {
            return strtolower(rawurldecode((string) $segment));
        },
        $segments
    );

    $hospitals_index = array_search('hospitals', $segments, true);

    /*
     * The segment directly before hospitals is the only route prefix
     * that controls hospital directory language.
     */
    if ($hospitals_index !== false && $hospitals_index > 0) {
        if (($segments[$hospitals_index - 1] ?? '') === 'bn') {
            return 'bn';
        }
    }

    /*
     * Supports /bn when a route has not reached /hospitals yet.
     */
    if (($segments[0] ?? '') === 'bn') {
        return 'bn';
    }

    /*
     * Optional application-level fallback. URL routing above always wins.
     */
    if (
        defined('CURRENT_LANG')
        && in_array((string) CURRENT_LANG, ['en', 'bn'], true)
    ) {
        return (string) CURRENT_LANG;
    }

    if (
        isset($_GET['lang'])
        && is_string($_GET['lang'])
        && $_GET['lang'] === 'bn'
    ) {
        return 'bn';
    }

    return 'en';
}

/*
|--------------------------------------------------------------------------
| Legacy Global Sync
|--------------------------------------------------------------------------
| Existing header, view and third-party code may still read $lang.
|--------------------------------------------------------------------------
*/
function hp_sync_directory_lang(): string
{
    global $lang;

    $lang = hp_directory_lang();

    return $lang;
}

hp_sync_directory_lang();

/*
|--------------------------------------------------------------------------
| Translation Loader
|--------------------------------------------------------------------------
*/
function hp_load_translations(string $locale): array
{
    static $loaded = [];

    $locale = $locale === 'bn' ? 'bn' : 'en';

    if (array_key_exists($locale, $loaded)) {
        return $loaded[$locale];
    }

    $candidates = [
        HP_ROOT_PATH . '/languages/' . $locale . '.php',
        HP_ROOT_PATH . '/hospital-directory/languages/' . $locale . '.php',
    ];

    foreach ($candidates as $file) {
        if (!is_file($file)) {
            continue;
        }

        $translations = require $file;

        if (is_array($translations)) {
            $loaded[$locale] = $translations;
            return $loaded[$locale];
        }
    }

    $loaded[$locale] = [];

    return $loaded[$locale];
}

/*
|--------------------------------------------------------------------------
| Translation Helper
|--------------------------------------------------------------------------
| Always resolves locale from the request route. This is the key change:
| header/footer code cannot switch hospital text back to English on /bn/.
|--------------------------------------------------------------------------
*/
function hp_t(string $key, string $fallback = '', array $replace = []): string
{
    $locale = hp_directory_lang();
    $translations = hp_load_translations($locale);

    $text = isset($translations[$key]) && is_string($translations[$key])
        ? $translations[$key]
        : '';

    /*
     * Keep compatibility with the main project's existing translator.
     */
    if ($text === '' && function_exists('__t')) {
        try {
            $translated = __t($key, $fallback);

            if (
                is_string($translated)
                && trim($translated) !== ''
                && $translated !== $key
            ) {
                $text = $translated;
            }
        } catch (Throwable $e) {
            $text = '';
        }
    }

    if ($text === '') {
        $text = $fallback !== '' ? $fallback : $key;
    }

    if (!empty($replace)) {
        $normalized = [];

        foreach ($replace as $name => $value) {
            $normalized[':' . ltrim((string) $name, ':')] = (string) $value;
        }

        $text = strtr($text, $normalized);
    }

    return (string) $text;
}

/*
|--------------------------------------------------------------------------
| Visible Bangla Digit Helper
|--------------------------------------------------------------------------
*/
function hp_localize_digits($value): string
{
    $value = (string) $value;

    if (hp_directory_lang() !== 'bn') {
        return $value;
    }

    return strtr($value, [
        '0' => '০',
        '1' => '১',
        '2' => '২',
        '3' => '৩',
        '4' => '৪',
        '5' => '৫',
        '6' => '৬',
        '7' => '৭',
        '8' => '৮',
        '9' => '৯',
    ]);
}

function hp_display($value): string
{
    return hp_localize_digits((string) $value);
}

/*
|--------------------------------------------------------------------------
| Image URL Helper
|--------------------------------------------------------------------------
*/
function hp_image_url(string $image): string
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

/*
|--------------------------------------------------------------------------
| Frontend URL Helper
|--------------------------------------------------------------------------
*/
function hp_front_url(string $path = ''): string
{
    $locale = hp_directory_lang();

    if (function_exists('front_url')) {
        return front_url($path, $locale);
    }

    $path = trim($path, '/');

    if ($locale === 'bn') {
        return site_url($path !== '' ? 'bn/' . $path : 'bn');
    }

    return site_url($path);
}

/*
|--------------------------------------------------------------------------
| Localized Row Values
|--------------------------------------------------------------------------
| Uses name_bn, address_bn, description_bn and similar fields on /bn/.
|--------------------------------------------------------------------------
*/
function hp_value(array $row, string $key, string $default = ''): string
{
    if (hp_directory_lang() === 'bn') {
        $bangla_key = $key . '_bn';
        $bangla = trim((string) ($row[$bangla_key] ?? ''));

        if ($bangla !== '') {
            return $bangla;
        }
    }

    $value = trim((string) ($row[$key] ?? ''));

    return $value !== '' ? $value : $default;
}

/*
|--------------------------------------------------------------------------
| Slug Helpers
|--------------------------------------------------------------------------
*/
function hp_url_slug(string $text): string
{
    $text = trim($text);
    $text = preg_replace('/[^\p{L}\p{N}\s-]+/u', '', $text);
    $text = preg_replace('/[\s_]+/u', '-', (string) $text);
    $text = preg_replace('/-+/u', '-', (string) $text);

    if (function_exists('mb_strtolower')) {
        $text = mb_strtolower($text, 'UTF-8');
    } else {
        $text = strtolower($text);
    }

    return trim((string) $text, '-');
}

function hp_slug_to_name(string $slug): string
{
    $slug = rawurldecode(trim($slug));
    $slug = str_replace('-', ' ', $slug);
    $slug = preg_replace('/\s+/u', ' ', (string) $slug);

    return trim(ucwords((string) $slug));
}

function hp_current_hospitals_segments(): array
{
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
    $path = trim((string) $path, '/');

    $parts = explode('/', $path);
    $hospitals_index = array_search('hospitals', $parts, true);

    if ($hospitals_index === false) {
        return [];
    }

    $segments = array_slice($parts, $hospitals_index + 1);

    return array_values(array_filter($segments, static function ($segment) {
        return trim((string) $segment) !== '';
    }));
}

function hp_clean_query(array $params): string
{
    $clean = [];

    foreach ($params as $key => $value) {
        $value = trim((string) $value);

        if ($value !== '') {
            $clean[$key] = $value;
        }
    }

    return http_build_query($clean);
}

/*
|--------------------------------------------------------------------------
| Hospital Type Helpers
|--------------------------------------------------------------------------
*/
function hp_hospital_types(): array
{
    return [
        'Private Hospital',
        'Government Hospital',
        'Medical College Hospital',
        'Specialized Hospital',
        'General Hospital',
        'Diagnostic Center',
        'Clinic',
        'Eye Hospital',
        'Dental Hospital',
        'Cardiac Hospital',
        'Cancer Hospital',
        'Mother and Child Hospital',
        'Rehabilitation Center',
    ];
}

function hp_hospital_type_from_slug(string $slug): string
{
    $slug = hp_url_slug($slug);

    if ($slug === '') {
        return '';
    }

    foreach (hp_hospital_types() as $type) {
        if (hp_url_slug($type) === $slug) {
            return $type;
        }
    }

    return '';
}

function hp_hospital_type_label(string $type): string
{
    $type = trim($type);

    $keys = [
        'Private Hospital' => 'hospitals_page_type_private_hospital',
        'Government Hospital' => 'hospitals_page_type_government_hospital',
        'Medical College Hospital' => 'hospitals_page_type_medical_college_hospital',
        'Specialized Hospital' => 'hospitals_page_type_specialized_hospital',
        'General Hospital' => 'hospitals_page_type_general_hospital',
        'Diagnostic Center' => 'hospitals_page_type_diagnostic_center',
        'Clinic' => 'hospitals_page_type_clinic',
        'Eye Hospital' => 'hospitals_page_type_eye_hospital',
        'Dental Hospital' => 'hospitals_page_type_dental_hospital',
        'Cardiac Hospital' => 'hospitals_page_type_cardiac_hospital',
        'Cancer Hospital' => 'hospitals_page_type_cancer_hospital',
        'Mother and Child Hospital' => 'hospitals_page_type_mother_child_hospital',
        'Rehabilitation Center' => 'hospitals_page_type_rehabilitation_center',
    ];

    if ($type !== '' && isset($keys[$type])) {
        return hp_t($keys[$type], $type);
    }

    return $type !== ''
        ? $type
        : hp_t('hospitals_page_default_hospital_type', 'Hospital');
}

/*
|--------------------------------------------------------------------------
| Localize Hospital Card Rows
|--------------------------------------------------------------------------
*/
function hp_localize_hospital_row(array $hospital): array
{
    if (hp_directory_lang() !== 'bn') {
        return $hospital;
    }

    foreach ([
        'name',
        'type',
        'address',
        'area',
        'road_no',
        'house_no',
        'description',
        'services',
        'facilities',
    ] as $key) {
        $bangla = trim((string) ($hospital[$key . '_bn'] ?? ''));

        if ($bangla !== '') {
            $hospital[$key] = $bangla;
        }
    }

    return $hospital;
}
