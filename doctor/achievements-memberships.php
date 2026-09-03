<?php
/*
|--------------------------------------------------------------------------
| Doctor Achievements & Memberships Section
|--------------------------------------------------------------------------
| Simple light design with English/Bangla fallback.
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
| Bilingual Field Fallback
|--------------------------------------------------------------------------
| Bangla page: Bangla -> English
| English page: English -> Bangla
|--------------------------------------------------------------------------
*/

if (!function_exists('doctor_achievements_bilingual_value')) {
    function doctor_achievements_bilingual_value(array $doctor, string $key): string
    {
        $english = trim((string)($doctor[$key] ?? ''));
        $bangla = trim((string)($doctor[$key . '_bn'] ?? ''));

        if (defined('CURRENT_LANG') && CURRENT_LANG === 'bn') {
            return $bangla !== '' ? $bangla : $english;
        }

        return $english !== '' ? $english : $bangla;
    }
}

if (!function_exists('doctor_achievements_has_any')) {
    function doctor_achievements_has_any(array $doctor, array $keys): bool
    {
        foreach ($keys as $key) {
            if (doctor_achievements_bilingual_value($doctor, $key) !== '') {
                return true;
            }
        }

        return false;
    }
}

/*
|--------------------------------------------------------------------------
| Achievements Data
|--------------------------------------------------------------------------
*/

$doctor_achievements_fields = [
    'awards' => __t('awards', 'Awards'),
    'membership' => __t('membership', 'Membership'),
    'research_publications' => __t('research_publications', 'Research & Publications'),
];

if (!doctor_achievements_has_any($doctor, array_keys($doctor_achievements_fields))) {
    return;
}

?>

<style>
/*
|--------------------------------------------------------------------------
| Simple Light Design
|--------------------------------------------------------------------------
| Fully namespaced to avoid affecting other doctor profile sections.
|--------------------------------------------------------------------------
*/

.medic-achievements-section {
    margin: 0 0 20px;
    padding: 22px;
    background: #ffffff;
    border: 1px solid #e5ebf1;
    border-radius: 14px;
    color: #415166;
    box-shadow: 0 3px 12px rgba(15, 51, 89, 0.035);
}

.medic-achievements-section,
.medic-achievements-section * {
    box-sizing: border-box;
    font-weight: 500;
}

.medic-achievements-heading {
    margin: 0 0 15px;
    color: #1e6ddb;
    font-size: 23px;
    line-height: 1.35;
}

.medic-achievements-list {
    border-top: 1px solid #edf1f5;
}

.medic-achievements-item {
    padding: 15px 0;
    border-bottom: 1px solid #edf1f5;
}

.medic-achievements-item:last-child {
    padding-bottom: 0;
    border-bottom: 0;
}

.medic-achievements-item h3 {
    margin: 0 0 6px;
    color: #273b52;
    font-size: 16px;
    line-height: 1.45;
}

.medic-achievements-item p {
    margin: 0;
    color: #5d6d7e;
    font-size: 15px;
    line-height: 1.8;
    overflow-wrap: anywhere;
}

@media (max-width: 576px) {
    .medic-achievements-section {
        margin-bottom: 16px;
        padding: 16px;
        border-radius: 12px;
    }

    .medic-achievements-heading {
        margin-bottom: 12px;
        font-size: 20px;
    }

    .medic-achievements-item {
        padding: 13px 0;
    }

    .medic-achievements-item h3 {
        font-size: 15px;
    }

    .medic-achievements-item p {
        font-size: 14px;
        line-height: 1.75;
    }
}
</style>

<section class="medic-achievements-section" aria-labelledby="doctor-achievements-heading">
    <h2 id="doctor-achievements-heading" class="medic-achievements-heading">
        <?= e(__t('achievements_memberships', 'Achievements & Memberships')) ?>
    </h2>

    <div class="medic-achievements-list">
        <?php foreach ($doctor_achievements_fields as $field => $label): ?>
            <?php $field_value = doctor_achievements_bilingual_value($doctor, $field); ?>

            <?php if ($field_value !== ''): ?>
                <article class="medic-achievements-item">
                    <h3><?= e($label) ?></h3>
                    <p><?= nl2br(e($field_value)) ?></p>
                </article>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
</section>
