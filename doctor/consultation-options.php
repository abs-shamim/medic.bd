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

<link rel="stylesheet" href="<?= e(site_url('assets/css/doctor-consultation-options.css')) ?>">

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
