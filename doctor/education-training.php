<?php
/*
|--------------------------------------------------------------------------
| Doctor Education, Training & Fellowship Section
|--------------------------------------------------------------------------
| Simple light design with bilingual fallback.
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
| Bilingual Field Helper
|--------------------------------------------------------------------------
| Bangla page: Bangla -> English
| English page: English -> Bangla
|--------------------------------------------------------------------------
*/

if (!function_exists('doctor_education_bilingual_value')) {
    function doctor_education_bilingual_value(array $doctor, string $key): string
    {
        $english = trim((string)($doctor[$key] ?? ''));
        $bangla = trim((string)($doctor[$key . '_bn'] ?? ''));

        if (defined('CURRENT_LANG') && CURRENT_LANG === 'bn') {
            return $bangla !== '' ? $bangla : $english;
        }

        return $english !== '' ? $english : $bangla;
    }
}

if (!function_exists('doctor_education_has_any')) {
    function doctor_education_has_any(array $doctor, array $keys): bool
    {
        foreach ($keys as $key) {
            if (doctor_education_bilingual_value($doctor, $key) !== '') {
                return true;
            }
        }

        return false;
    }
}

/*
|--------------------------------------------------------------------------
| Section Data
|--------------------------------------------------------------------------
*/

$doctor_education_fields = [
    'education' => __t('education', 'Education'),
    'training' => __t('training', 'Training'),
    'fellowship' => __t('fellowship', 'Fellowship'),
];

if (!doctor_education_has_any($doctor, array_keys($doctor_education_fields))) {
    return;
}

?>

<link rel="stylesheet" href="<?= e(site_url('assets/css/doctor-education-training.css')) ?>">

<section class="medic-education-section" aria-labelledby="doctor-education-heading">
    <h2 id="doctor-education-heading" class="medic-education-heading">
        <?= e(__t('education_training', 'Education, Training & Fellowship')) ?>
    </h2>

    <div class="medic-education-list">
        <?php foreach ($doctor_education_fields as $field => $label): ?>
            <?php $field_value = doctor_education_bilingual_value($doctor, $field); ?>

            <?php if ($field_value !== ''): ?>
                <article class="medic-education-item">
                    <h3><?= e($label) ?></h3>
                    <p><?= nl2br(e($field_value)) ?></p>
                </article>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
</section>
