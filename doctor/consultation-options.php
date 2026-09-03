<?php
/*
|--------------------------------------------------------------------------
| Doctor Consultation Options Section
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

if (!function_exists('doctor_consultation_field')) {
    function doctor_consultation_field(array $doctor, string $key): string
    {
        $english = trim((string)($doctor[$key] ?? ''));
        $bangla = trim((string)($doctor[$key . '_bn'] ?? ''));

        if (defined('CURRENT_LANG') && CURRENT_LANG === 'bn') {
            return $bangla !== '' ? $bangla : $english;
        }

        return $english !== '' ? $english : $bangla;
    }
}

if (!function_exists('doctor_consultation_display_number')) {
    function doctor_consultation_display_number(string $value): string
    {
        if (!defined('CURRENT_LANG') || CURRENT_LANG !== 'bn') {
            return $value;
        }

        return str_replace(
            ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'],
            ['০', '১', '২', '৩', '৪', '৫', '৬', '৭', '৮', '৯'],
            $value
        );
    }
}

if (!function_exists('doctor_consultation_money_text')) {
    function doctor_consultation_money_text($value): string
    {
        $amount = (float)($value ?? 0);

        if ($amount <= 0) {
            return '';
        }

        return '৳' . doctor_consultation_display_number(number_format($amount, 0));
    }
}

/*
|--------------------------------------------------------------------------
| Consultation Data
|--------------------------------------------------------------------------
*/

$consultation_rows = [];

if ((float)($doctor['consultation_fee'] ?? 0) > 0) {
    $consultation_rows[] = [
        'label' => __t('consultation_fee', 'Consultation Fee'),
        'value' => doctor_consultation_money_text($doctor['consultation_fee'] ?? 0),
        'type' => 'fee',
    ];
}

if ((float)($doctor['follow_up_fee'] ?? 0) > 0) {
    $consultation_rows[] = [
        'label' => __t('follow_up_fee', 'Follow-up Fee'),
        'value' => doctor_consultation_money_text($doctor['follow_up_fee'] ?? 0),
        'type' => 'fee',
    ];
}

if (!empty($doctor['online_consultation']) && (float)($doctor['video_consultation_fee'] ?? 0) > 0) {
    $consultation_rows[] = [
        'label' => __t('video_consultation_fee', 'Video Consultation Fee'),
        'value' => doctor_consultation_money_text($doctor['video_consultation_fee'] ?? 0),
        'type' => 'fee',
    ];
}

$serial_number = doctor_consultation_field($doctor, 'serial_no');

if ($serial_number !== '') {
    $consultation_rows[] = [
        'label' => __t('serial_number', 'Serial Number'),
        'value' => doctor_consultation_display_number($serial_number),
        'type' => 'normal',
    ];
}

if (!empty($doctor['online_consultation'])) {
    $consultation_rows[] = [
        'label' => __t('online_consultation', 'Online Consultation'),
        'value' => __t('available', 'Available'),
        'type' => 'available',
    ];
}

if (!empty($doctor['emergency_available'])) {
    $consultation_rows[] = [
        'label' => __t('emergency_consultation', 'Emergency Consultation'),
        'value' => __t('available', 'Available'),
        'type' => 'available',
    ];
}

if (!empty($doctor['home_visit'])) {
    $consultation_rows[] = [
        'label' => __t('home_visit', 'Home Visit'),
        'value' => __t('available', 'Available'),
        'type' => 'available',
    ];
}

$appointment_note = doctor_consultation_field($doctor, 'appointment_note');

if (empty($consultation_rows) && $appointment_note === '') {
    return;
}

?>

<style>
/*
|--------------------------------------------------------------------------
| Simple Light Design
|--------------------------------------------------------------------------
| Fully namespaced to prevent style conflicts with other doctor sections.
|--------------------------------------------------------------------------
*/

.medic-consultation-section {
    margin: 0 0 20px;
    padding: 22px;
    background: #ffffff;
    border: 1px solid #e5ebf1;
    border-radius: 14px;
    color: #415166;
    box-shadow: 0 3px 12px rgba(15, 51, 89, 0.035);
}

.medic-consultation-section,
.medic-consultation-section * {
    box-sizing: border-box;
    font-weight: 500;
}

.medic-consultation-heading {
    margin: 0 0 15px;
    color: #1e6ddb;
    font-size: 23px;
    line-height: 1.35;
}

.medic-consultation-list {
    display: grid;
    gap: 0;
    border-top: 1px solid #edf1f5;
}

.medic-consultation-row {
    display: grid;
    grid-template-columns: minmax(130px, 38%) minmax(0, 1fr);
    gap: 16px;
    padding: 13px 0;
    border-bottom: 1px solid #edf1f5;
}

.medic-consultation-label {
    color: #728398;
    font-size: 14px;
    line-height: 1.55;
}

.medic-consultation-value {
    color: #31465e;
    font-size: 15px;
    line-height: 1.55;
    text-align: right;
    overflow-wrap: anywhere;
}

.medic-consultation-value.is-fee {
    color: #2674e9;
}

.medic-consultation-value.is-available {
    color: #278152;
}

.medic-consultation-note {
    margin-top: 16px;
    padding-top: 15px;
    border-top: 1px solid #edf1f5;
}

.medic-consultation-note h3 {
    margin: 0 0 7px;
    color: #34485f;
    font-size: 16px;
    line-height: 1.45;
}

.medic-consultation-note p {
    margin: 0;
    color: #5d6d7e;
    font-size: 15px;
    line-height: 1.8;
}

@media (max-width: 576px) {
    .medic-consultation-section {
        margin-bottom: 16px;
        padding: 16px;
        border-radius: 12px;
    }

    .medic-consultation-heading {
        margin-bottom: 12px;
        font-size: 20px;
    }

    .medic-consultation-row {
        grid-template-columns: 1fr;
        gap: 3px;
        padding: 11px 0;
    }

    .medic-consultation-label {
        font-size: 13px;
    }

    .medic-consultation-value {
        font-size: 14px;
        text-align: left;
    }

    .medic-consultation-note {
        margin-top: 13px;
        padding-top: 12px;
    }

    .medic-consultation-note h3 {
        font-size: 15px;
    }

    .medic-consultation-note p {
        font-size: 14px;
        line-height: 1.75;
    }
}
</style>

<section class="medic-consultation-section" aria-labelledby="doctor-consultation-heading">
    <h2 id="doctor-consultation-heading" class="medic-consultation-heading">
        <?= e(__t('consultation_options', 'Consultation Options')) ?>
    </h2>

    <?php if (!empty($consultation_rows)): ?>
        <div class="medic-consultation-list">
            <?php foreach ($consultation_rows as $row): ?>
                <div class="medic-consultation-row">
                    <span class="medic-consultation-label"><?= e($row['label']) ?></span>
                    <span class="medic-consultation-value<?= $row['type'] === 'fee' ? ' is-fee' : ($row['type'] === 'available' ? ' is-available' : '') ?>">
                        <?= e($row['value']) ?>
                    </span>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($appointment_note !== ''): ?>
        <div class="medic-consultation-note">
            <h3><?= e(__t('appointment_note', 'Appointment Note')) ?></h3>
            <p><?= nl2br(e($appointment_note)) ?></p>
        </div>
    <?php endif; ?>
</section>
