<?php
/*
|--------------------------------------------------------------------------
| Doctor Medical Focus Section
|--------------------------------------------------------------------------
| Simple light design with full English/Bangla fallback.
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/../includes/functions.php';

$slug = $_GET['slug'] ?? '';

if (!isset($doctor) || !is_array($doctor)) {
    $doctor = function_exists('get_doctor_by_slug') ? get_doctor_by_slug($slug) : null;
}

if (!$doctor || !is_array($doctor)) {
    return;
}

/*
|--------------------------------------------------------------------------
| Basic Language Fallbacks
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

    define(
        'CURRENT_LANG',
        ($current_request_path === 'bn' || str_starts_with($current_request_path, 'bn/')) ? 'bn' : 'en'
    );
}

if (!function_exists('__t')) {
    function __t(string $key, string $fallback = ''): string
    {
        $lang = defined('CURRENT_LANG') ? CURRENT_LANG : 'en';
        $base_dir = dirname(__DIR__);
        $default_file = $base_dir . '/languages/en.php';
        $lang_file = $base_dir . '/languages/' . $lang . '.php';

        static $translations = null;

        if ($translations === null) {
            $translations = [];

            if (is_file($default_file)) {
                $default_translations = require $default_file;

                if (is_array($default_translations)) {
                    $translations = $default_translations;
                }
            }

            if ($lang !== 'en' && is_file($lang_file)) {
                $current_translations = require $lang_file;

                if (is_array($current_translations)) {
                    $translations = array_merge($translations, $current_translations);
                }
            }
        }

        $value = trim((string)($translations[$key] ?? ''));

        return $value !== '' ? $value : ($fallback !== '' ? $fallback : $key);
    }
}

/*
|--------------------------------------------------------------------------
| Bilingual Fallback
|--------------------------------------------------------------------------
| Bangla page: Bangla -> English
| English page: English -> Bangla
|--------------------------------------------------------------------------
*/

if (!function_exists('doctor_clinical_bilingual_value')) {
    function doctor_clinical_bilingual_value(array $doctor, string $key): string
    {
        $english = trim((string)($doctor[$key] ?? ''));
        $bangla = trim((string)($doctor[$key . '_bn'] ?? ''));

        if (defined('CURRENT_LANG') && CURRENT_LANG === 'bn') {
            return $bangla !== '' ? $bangla : $english;
        }

        return $english !== '' ? $english : $bangla;
    }
}

if (!function_exists('doctor_clinical_split_items')) {
    function doctor_clinical_split_items(string $text): array
    {
        $text = trim($text);

        if ($text === '') {
            return [];
        }

        $items = preg_split('/[\n,|]+/u', $text);
        $unique = [];

        foreach ($items as $item) {
            $item = trim(strip_tags((string)$item));

            if ($item === '') {
                continue;
            }

            $key = function_exists('mb_strtolower')
                ? mb_strtolower($item, 'UTF-8')
                : strtolower($item);

            $unique[$key] = $item;
        }

        return array_values($unique);
    }
}

/*
|--------------------------------------------------------------------------
| Medical Focus Data
|--------------------------------------------------------------------------
*/

$expertise_text = doctor_clinical_bilingual_value($doctor, 'expertise');

if ($expertise_text === '') {
    return;
}

$expertise_items = doctor_clinical_split_items($expertise_text);

?>

<link rel="stylesheet" href="<?= e(site_url('assets/css/doctor-clinical-expertise.css')) ?>">

<section class="medic-medical-focus-section" aria-labelledby="doctor-medical-focus-heading">
    <h2 id="doctor-medical-focus-heading" class="medic-medical-focus-heading">
        <?= e(__t('medical_focus', 'Medical Focus')) ?>
    </h2>

    <div class="medic-medical-focus-content">
        <?php if (count($expertise_items) > 1): ?>
            <ul class="medic-medical-focus-list">
                <?php foreach ($expertise_items as $item): ?>
                    <li><?= e($item) ?></li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p class="medic-medical-focus-text"><?= nl2br(e($expertise_text)) ?></p>
        <?php endif; ?>
    </div>
</section>
