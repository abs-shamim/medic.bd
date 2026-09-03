<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$prescription_id = defined('PRESCRIPTION_EDIT_ID')
    ? (int)PRESCRIPTION_EDIT_ID
    : (int)($_POST['prescription_id'] ?? 0);

$editing = $prescription_id > 0;
$record = $editing ? prescription_get_record($pdo, $prescription_id, $doctor_id) : null;

if ($editing && !$record && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    prescription_flash('error', 'Prescription was not found or access was denied.');
    prescription_redirect();
}


if (!function_exists('rx_editor_assign_new_patient_code')) {
    /**
     * Assign a daily patient code in PTYYMMDDSS format.
     * Example: PT26071101
     */
    function rx_editor_assign_new_patient_code(
        PDO $pdo,
        int $doctor_id,
        int $patient_id,
        string $visit_date
    ): ?string {
        if ($doctor_id <= 0 || $patient_id <= 0) {
            return null;
        }

        $current_stmt = $pdo->prepare("
            SELECT patient_code
            FROM prescription_patients
            WHERE id = :patient_id
              AND doctor_id = :doctor_id
            LIMIT 1
        ");
        $current_stmt->execute([
            ':patient_id' => $patient_id,
            ':doctor_id' => $doctor_id,
        ]);

        $current_code = trim((string)$current_stmt->fetchColumn());

        if (preg_match('/^PT[0-9]{8,}$/', $current_code) === 1) {
            return $current_code;
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $visit_date);

        if (!$date) {
            $date = new DateTimeImmutable('today');
        }

        $prefix = 'PT' . $date->format('ymd');
        $pattern = $prefix . '%';

        for ($attempt = 0; $attempt < 12; $attempt++) {
            $max_stmt = $pdo->prepare("
                SELECT COALESCE(
                    MAX(CAST(SUBSTRING(patient_code, 9) AS UNSIGNED)),
                    0
                )
                FROM prescription_patients
                WHERE doctor_id = :doctor_id
                  AND patient_code LIKE :pattern
                  AND patient_code REGEXP '^PT[0-9]{8,}$'
            ");
            $max_stmt->execute([
                ':doctor_id' => $doctor_id,
                ':pattern' => $pattern,
            ]);

            $next_number = (int)$max_stmt->fetchColumn() + 1 + $attempt;
            $patient_code = $prefix . str_pad(
                (string)$next_number,
                2,
                '0',
                STR_PAD_LEFT
            );

            try {
                $update_stmt = $pdo->prepare("
                    UPDATE prescription_patients
                    SET patient_code = :patient_code,
                        updated_at = NOW()
                    WHERE id = :patient_id
                      AND doctor_id = :doctor_id
                    LIMIT 1
                ");
                $update_stmt->execute([
                    ':patient_code' => $patient_code,
                    ':patient_id' => $patient_id,
                    ':doctor_id' => $doctor_id,
                ]);

                if ($update_stmt->rowCount() > 0) {
                    return $patient_code;
                }
            } catch (PDOException $e) {
                if ((string)$e->getCode() === '23000') {
                    continue;
                }

                throw $e;
            }
        }

        throw new RuntimeException(
            'A unique daily patient code could not be generated.'
        );
    }
}

$rx_new_patient_candidate = (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && !$editing
    && (int)($_POST['patient_id'] ?? 0) <= 0
);

$rx_patient_max_id_before_save = 0;
$rx_patient_baseline_ready = false;

if ($rx_new_patient_candidate) {
    try {
        $rx_patient_max_stmt = $pdo->prepare("
            SELECT COALESCE(MAX(id), 0)
            FROM prescription_patients
            WHERE doctor_id = :doctor_id
        ");
        $rx_patient_max_stmt->execute([':doctor_id' => $doctor_id]);

        $rx_patient_max_id_before_save = (int)$rx_patient_max_stmt->fetchColumn();
        $rx_patient_baseline_ready = true;
    } catch (Throwable $e) {
        error_log('[Prescription New Patient Baseline] ' . $e->getMessage());
    }
}


if (!function_exists('rx_editor_normalize_medicine_name')) {
    function rx_editor_normalize_medicine_name(string $value): string
    {
        $value = trim((string)preg_replace('/\s+/u', ' ', $value));

        if ($value === '') {
            return '';
        }

        return function_exists('mb_strtolower')
            ? mb_strtolower($value, 'UTF-8')
            : strtolower($value);
    }
}

if (!function_exists('rx_editor_validate_unique_medicine_names')) {
    function rx_editor_validate_unique_medicine_names(array $request): void
    {
        $generic_names = $request['medicine_generic_name'] ?? [];
        $brand_names = $request['medicine_brand_name'] ?? [];

        $generic_names = is_array($generic_names) ? $generic_names : [];
        $brand_names = is_array($brand_names) ? $brand_names : [];
        $row_count = max(count($generic_names), count($brand_names));
        $seen = [];

        for ($index = 0; $index < $row_count; $index++) {
            foreach ([
                (string)($generic_names[$index] ?? ''),
                (string)($brand_names[$index] ?? ''),
            ] as $value) {
                $normalized = rx_editor_normalize_medicine_name($value);

                if ($normalized === '') {
                    continue;
                }

                if (
                    isset($seen[$normalized])
                    && $seen[$normalized] !== $index
                ) {
                    throw new InvalidArgumentException(
                        'Duplicate Generic Name or Brand Name is not allowed.'
                    );
                }

                $seen[$normalized] = $index;
            }
        }
    }
}

$form_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!prescription_verify_csrf($_POST['csrf_token'] ?? null)) {
        $form_error = 'Security verification failed. Please refresh the page and try again.';
    } else {
        try {
            rx_editor_validate_unique_medicine_names($_POST);

            $saved_id = prescription_save_from_request(
                $pdo,
                $doctor_id,
                $_POST,
                $prescription_id,
                (string)($_POST['save_status'] ?? 'active')
            );

            $new_patient_added = false;
            $new_patient_code = '';

            if ($rx_new_patient_candidate && $rx_patient_baseline_ready) {
                try {
                    $saved_patient_stmt = $pdo->prepare("
                        SELECT patient_id, visit_date
                        FROM prescriptions
                        WHERE id = :prescription_id
                          AND doctor_id = :doctor_id
                        LIMIT 1
                    ");
                    $saved_patient_stmt->execute([
                        ':prescription_id' => $saved_id,
                        ':doctor_id' => $doctor_id,
                    ]);

                    $saved_patient = $saved_patient_stmt->fetch(PDO::FETCH_ASSOC) ?: [];
                    $saved_patient_id = (int)($saved_patient['patient_id'] ?? 0);

                    if (
                        $saved_patient_id > 0
                        && $saved_patient_id > $rx_patient_max_id_before_save
                    ) {
                        $new_patient_code = (string)(
                            rx_editor_assign_new_patient_code(
                                $pdo,
                                $doctor_id,
                                $saved_patient_id,
                                (string)(
                                    $saved_patient['visit_date']
                                    ?? $_POST['visit_date']
                                    ?? date('Y-m-d')
                                )
                            )
                            ?? ''
                        );

                        $new_patient_added = $new_patient_code !== '';
                    }
                } catch (Throwable $patient_code_error) {
                    error_log(
                        '[Prescription Patient Code] '
                        . $patient_code_error->getMessage()
                    );
                }
            }

            if (($_POST['save_status'] ?? 'active') === 'draft') {
                prescription_flash(
                    'success',
                    $new_patient_added
                        ? 'Prescription draft saved and new patient added successfully. Patient ID: ' . $new_patient_code
                        : 'Prescription draft saved successfully.'
                );
                prescription_redirect('edit.php?id=' . $saved_id);
            }

            prescription_flash(
                'success',
                $editing
                    ? 'Prescription updated successfully.'
                    : (
                        $new_patient_added
                            ? 'Prescription created and new patient added successfully. Patient ID: ' . $new_patient_code
                            : 'Prescription created successfully.'
                    )
            );
            prescription_redirect('view.php?id=' . $saved_id);
        } catch (InvalidArgumentException $e) {
            $form_error = $e->getMessage();
        } catch (Throwable $e) {
            try {
                $error_reference = strtoupper(substr(
                    hash('sha256', microtime(true) . random_bytes(8)),
                    0,
                    10
                ));
            } catch (Throwable $reference_error) {
                $error_reference = strtoupper(substr(md5((string)microtime(true)), 0, 10));
            }

            $technical_message = $e->getMessage();
            $technical_message_lower = strtolower($technical_message);

            error_log(
                '[Prescription Save][' . $error_reference . '] '
                . get_class($e)
                . ' in ' . $e->getFile()
                . ':' . $e->getLine()
                . ' - ' . $technical_message
            );

            if (
                str_contains($technical_message_lower, 'unknown column')
                || str_contains($technical_message_lower, 'doesn\'t exist')
                || str_contains($technical_message_lower, 'base table or view not found')
            ) {
                $form_error = 'The prescription database structure is incomplete. Upload the latest module files and try again. Error reference: ' . $error_reference;
            } elseif (str_contains($technical_message_lower, 'duplicate entry')) {
                $form_error = 'A duplicate database value prevented the prescription from being saved. Error reference: ' . $error_reference;
            } else {
                $form_error = 'Prescription could not be saved. Please check the information and try again. Error reference: ' . $error_reference;
            }
        }
    }
}

$selected_patient = null;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $selected_patient_id = $editing && $record
        ? (int)($record['patient_id'] ?? 0)
        : (int)($_GET['patient_id'] ?? 0);

    if ($selected_patient_id > 0) {
        $selected_patient = prescription_get_patient($pdo, $selected_patient_id, $doctor_id);

        if ($selected_patient) {
            $patient_stats_stmt = $pdo->prepare("
                SELECT COUNT(*) AS prescription_count, MAX(visit_date) AS last_visit
                FROM prescriptions
                WHERE doctor_id = :doctor_id
                  AND patient_id = :patient_id
            ");
            $patient_stats_stmt->execute([
                ':doctor_id' => $doctor_id,
                ':patient_id' => $selected_patient_id,
            ]);
            $patient_stats = $patient_stats_stmt->fetch(PDO::FETCH_ASSOC) ?: [];
            $selected_patient = array_merge($selected_patient, $patient_stats);
        }
    }
}

if (!function_exists('rx_form_value')) {
    function rx_form_value(string $field, $default = ''): string
    {
        global $record, $selected_patient;

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && array_key_exists($field, $_POST)) {
            return is_array($_POST[$field]) ? (string)$default : (string)$_POST[$field];
        }

        if ($record && array_key_exists($field, $record)) {
            return (string)($record[$field] ?? $default);
        }

        $patient_map = [
            'patient_id' => 'id',
            'patient_name' => 'name',
            'patient_phone' => 'phone',
            'patient_age' => 'age',
            'patient_gender' => 'gender',
            'patient_blood_group' => 'blood_group',
            'patient_address' => 'address',
            'weight' => 'last_weight',
            'height' => 'last_height',
            'blood_pressure' => 'last_blood_pressure',
            'temperature' => 'last_temperature',
            'pulse' => 'last_pulse',
            'spo2' => 'last_spo2',
            'medical_history' => 'medical_history',
        ];

        if ($selected_patient && isset($patient_map[$field])) {
            return (string)($selected_patient[$patient_map[$field]] ?? $default);
        }

        return (string)$default;
    }
}

if (!function_exists('rx_post_rows')) {
    function rx_post_rows(string $primary, array $map): array
    {
        $primary_values = $_POST[$primary] ?? [];

        if (!is_array($primary_values)) {
            return [];
        }

        $rows = [];

        foreach ($primary_values as $index => $value) {
            $row = [$primary => (string)$value];

            foreach ($map as $post_key => $row_key) {
                $values = $_POST[$post_key] ?? [];
                $row[$row_key] = is_array($values) ? (string)($values[$index] ?? '') : '';
            }

            $rows[] = $row;
        }

        return $rows;
    }
}

if (!function_exists('rx_editor_doctor_value')) {
    function rx_editor_doctor_value(array $doctor, array $keys, string $default = ''): string
    {
        foreach ($keys as $key) {
            $value = trim((string)($doctor[$key] ?? ''));

            if ($value !== '') {
                return $value;
            }
        }

        return $default;
    }
}

if (!function_exists('rx_editor_lines')) {
    function rx_editor_lines(string $value): array
    {
        $value = trim(str_replace(["\r\n", "\r"], "\n", $value));

        if ($value === '') {
            return [''];
        }

        $lines = array_values(array_filter(
            array_map('trim', explode("\n", $value)),
            static fn(string $line): bool => $line !== ''
        ));

        return $lines ?: [''];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $medicines = rx_post_rows('medicine_generic_name', [
        'medicine_library_id' => 'library_id',
        'medicine_brand_name' => 'brand_name',
        'medicine_form' => 'medicine_form',
        'medicine_strength' => 'strength',
        'medicine_dosage' => 'dosage',
        'medicine_frequency' => 'frequency',
        'medicine_duration' => 'duration',
        'medicine_instruction' => 'instruction',
    ]);
    $tests = rx_post_rows('test_name', [
        'test_instruction' => 'instruction',
    ]);
} elseif ($editing && $record) {
    $medicines = prescription_get_medicines($pdo, $prescription_id);
    $tests = prescription_get_tests($pdo, $prescription_id);
} else {
    $medicines = [];
    $tests = [];
}

if (!$medicines) {
    $medicines[] = [
        'library_id' => '',
        'generic_name' => '',
        'brand_name' => '',
        'medicine_name' => '',
        'medicine_form' => '',
        'strength' => '',
        'dosage' => '',
        'frequency' => '',
        'duration' => '',
        'instruction' => '',
    ];
}

if (!$tests) {
    $tests[] = [
        'test_name' => '',
        'instruction' => '',
    ];
}

$doctor_profile = prescription_get_doctor($pdo, $doctor_id);
$doctor_name = rx_editor_doctor_value($doctor_profile, ['name', 'name_en'], 'Doctor');
$doctor_name_bn = rx_editor_doctor_value($doctor_profile, ['name_bn']);
$doctor_degree = rx_editor_doctor_value($doctor_profile, ['degree', 'degree_en']);
$doctor_degree_bn = rx_editor_doctor_value($doctor_profile, ['degree_bn']);
$doctor_designation = rx_editor_doctor_value($doctor_profile, ['designation', 'designation_en']);
$doctor_designation_bn = rx_editor_doctor_value($doctor_profile, ['designation_bn']);
$doctor_training = rx_editor_doctor_value($doctor_profile, ['training', 'training_en']);
$doctor_training_bn = rx_editor_doctor_value($doctor_profile, ['training_bn']);
$doctor_fellowship = rx_editor_doctor_value($doctor_profile, ['fellowship', 'fellowship_en']);
$doctor_fellowship_bn = rx_editor_doctor_value($doctor_profile, ['fellowship_bn']);
$doctor_hospital = rx_editor_doctor_value($doctor_profile, ['primary_hospital', 'hospital_name']);
$doctor_hospital_bn = rx_editor_doctor_value($doctor_profile, ['primary_hospital_bn']);
$doctor_bmdc = rx_editor_doctor_value($doctor_profile, ['bmdc_number']);
$doctor_phone = rx_editor_doctor_value($doctor_profile, ['serial_no', 'phone']);

$clinical_sections = [
    'chief_complaints' => [
        'title' => 'C/C',
        'subtitle' => 'Chief Complaints',
        'placeholder' => 'High fever, cough, pain...',
        'library_type' => 'complaint',
    ],
    'diagnosis' => [
        'title' => 'Dx',
        'subtitle' => 'Diagnosis',
        'placeholder' => 'Diagnosis or provisional diagnosis...',
        'library_type' => 'diagnosis',
    ],
    'medical_history' => [
        'title' => 'D/H',
        'subtitle' => 'Disease / Medical History',
        'placeholder' => 'HTN, diabetes, previous illness...',
        'library_type' => 'history',
    ],
    'examination' => [
        'title' => 'O/E',
        'subtitle' => 'On Examination',
        'placeholder' => 'Clinical examination findings...',
        'library_type' => 'examination',
    ],
];

$clinical_values = [];

foreach ($clinical_sections as $field => $meta) {
    $clinical_values[$field] = rx_editor_lines(rx_form_value($field));
}

$advice_lines = rx_editor_lines(rx_form_value('advice'));

$page_title = $editing ? 'Edit Prescription' : 'New Prescription';
require_once __DIR__ . '/includes/header.php';
?>


<style>
:root {
    --rxe-bg: #f4f7fb;
    --rxe-panel: #ffffff;
    --rxe-panel-soft: #f8fafc;
    --rxe-border: #d8e0ea;
    --rxe-border-soft: #e8edf3;
    --rxe-text: #172033;
    --rxe-muted: #687386;
    --rxe-primary: #1769e0;
    --rxe-primary-hover: #0e57c7;
    --rxe-primary-soft: #eaf3ff;
    --rxe-success: #16803c;
    --rxe-success-soft: #e8f8ee;
    --rxe-danger: #c4323f;
    --rxe-danger-soft: #fff0f1;
    --rxe-gold: #b7791f;
    --rxe-shadow-sm: 0 1px 2px rgba(15, 23, 42, .05);
    --rxe-shadow-md: 0 10px 30px rgba(15, 23, 42, .08);
    --rxe-shadow-lg: 0 22px 55px rgba(15, 23, 42, .12);
    --rxe-radius-sm: 8px;
    --rxe-radius: 12px;
    --rxe-radius-lg: 18px;
}

body.rx-dashboard-body {
    background:
        radial-gradient(circle at top right, rgba(23, 105, 224, .07), transparent 30%),
        var(--rxe-bg);
}

.rx-main {
    width: 100%;
    max-width: 1720px;
    margin: 0 auto;
    padding: 24px;
}

.rx-editor-hero {
    position: relative;
    overflow: hidden;
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 24px;
    margin-bottom: 18px;
    padding: 24px 26px;
    border: 1px solid rgba(23, 105, 224, .18);
    border-radius: var(--rxe-radius-lg);
    background:
        linear-gradient(135deg, rgba(255, 255, 255, .98), rgba(241, 247, 255, .96));
    box-shadow: var(--rxe-shadow-md);
}

.rx-editor-hero::after {
    content: "";
    position: absolute;
    top: -80px;
    right: -55px;
    width: 210px;
    height: 210px;
    border-radius: 50%;
    background: rgba(23, 105, 224, .08);
    pointer-events: none;
}

.rx-editor-hero > * {
    position: relative;
    z-index: 1;
}

.rx-editor-hero h1 {
    margin: 8px 0 7px;
    color: var(--rxe-text);
    font-size: clamp(24px, 2vw, 32px);
    font-weight: 750;
    line-height: 1.15;
    letter-spacing: -.035em;
}

.rx-editor-hero p {
    max-width: 760px;
    margin: 0;
    color: var(--rxe-muted);
    font-size: 13px;
    line-height: 1.65;
}

.rx-editor-hero .rx-breadcrumb {
    display: flex;
    align-items: center;
    gap: 7px;
    color: var(--rxe-muted);
    font-size: 11px;
    font-weight: 600;
}

.rx-editor-hero .rx-breadcrumb a {
    color: var(--rxe-primary);
    text-decoration: none;
}

.rx-editor-hero .rx-breadcrumb a:hover {
    text-decoration: underline;
}

.rx-editor-hero .rx-hero-actions,
.rx-prescription-editor .rx-actions,
.rx-selected-patient-actions {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

.rx-editor-hero .rx-hero-actions {
    justify-content: flex-end;
}

.rx-editor-hero .rx-btn,
.rx-prescription-editor .rx-btn {
    min-height: 36px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    padding: 7px 13px;
    border: 1px solid var(--rxe-border);
    border-radius: var(--rxe-radius-sm);
    background: var(--rxe-panel);
    color: var(--rxe-text);
    font: inherit;
    font-size: 12px;
    font-weight: 700;
    line-height: 1.35;
    text-decoration: none;
    white-space: nowrap;
    cursor: pointer;
    box-shadow: var(--rxe-shadow-sm);
    transition:
        transform .15s ease,
        border-color .15s ease,
        background-color .15s ease,
        box-shadow .15s ease;
}

.rx-editor-hero .rx-btn:hover,
.rx-prescription-editor .rx-btn:hover {
    border-color: #b8c4d2;
    background: #f7f9fc;
    color: var(--rxe-text);
    text-decoration: none;
    transform: translateY(-1px);
}

.rx-prescription-editor .rx-btn-primary {
    border-color: var(--rxe-primary);
    background: linear-gradient(180deg, #2578ea, var(--rxe-primary));
    color: #fff;
    box-shadow: 0 8px 18px rgba(23, 105, 224, .2);
}

.rx-prescription-editor .rx-btn-primary:hover {
    border-color: var(--rxe-primary-hover);
    background: var(--rxe-primary-hover);
    color: #fff;
}

.rx-prescription-editor .rx-btn-soft {
    background: #f7f9fc;
}

.rx-prescription-editor .rx-btn-sm {
    min-height: 31px;
    padding: 5px 10px;
    font-size: 11px;
}

.rx-alert.error {
    margin-bottom: 16px;
    display: flex;
    align-items: flex-start;
    gap: 11px;
    padding: 14px 16px;
    border: 1px solid rgba(196, 50, 63, .3);
    border-radius: var(--rxe-radius);
    background: var(--rxe-danger-soft);
    color: var(--rxe-danger);
    box-shadow: var(--rxe-shadow-sm);
}

.rx-alert.error .rx-alert-icon {
    width: 24px;
    height: 24px;
    flex: 0 0 24px;
    display: grid;
    place-items: center;
    border: 1px solid currentColor;
    border-radius: 50%;
    font-size: 12px;
    font-weight: 800;
}

.rx-alert.error strong {
    display: block;
    margin-bottom: 2px;
    font-size: 13px;
}

.rx-alert.error p {
    margin: 0;
    font-size: 12px;
    line-height: 1.5;
}

.rx-prescription-editor {
    position: relative;
    display: flex;
    flex-direction: column;
    gap: 16px;
    color: var(--rxe-text);
}

.rx-prescription-editor *,
.rx-prescription-editor *::before,
.rx-prescription-editor *::after {
    box-sizing: border-box;
}

.rx-editor-toolbar {
    display: grid;
    grid-template-columns: minmax(190px, 260px) minmax(0, 1fr);
    align-items: center;
    gap: 18px;
    padding: 15px 16px;
    border: 1px solid var(--rxe-border);
    border-radius: var(--rxe-radius);
    background: rgba(255, 255, 255, .92);
    box-shadow: var(--rxe-shadow-sm);
    backdrop-filter: blur(12px);
}

.rx-editor-toolbar-copy strong,
.rx-editor-toolbar-copy span {
    display: block;
}

.rx-editor-toolbar-copy strong {
    font-size: 13px;
    font-weight: 750;
}

.rx-editor-toolbar-copy span {
    margin-top: 3px;
    color: var(--rxe-muted);
    font-size: 10px;
    line-height: 1.45;
}

.rx-editor-toolbar-actions {
    min-width: 0;
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 7px;
    flex-wrap: wrap;
}

.rx-quick-add {
    min-height: 32px;
    padding: 5px 10px;
    border: 1px solid #c9d4e1;
    border-radius: 999px;
    background: var(--rxe-panel);
    color: #334155;
    font: inherit;
    font-size: 11px;
    font-weight: 700;
    white-space: nowrap;
    cursor: pointer;
    box-shadow: var(--rxe-shadow-sm);
    transition: all .15s ease;
}

.rx-quick-add:hover {
    border-color: var(--rxe-primary);
    background: var(--rxe-primary-soft);
    color: var(--rxe-primary);
    transform: translateY(-1px);
}

.rx-patient-search-panel {
    position: relative;
    display: grid;
    grid-template-columns: minmax(190px, 280px) minmax(280px, 1fr);
    align-items: center;
    gap: 16px;
    padding: 15px 16px;
    border: 1px solid rgba(23, 105, 224, .2);
    border-radius: var(--rxe-radius);
    background: linear-gradient(135deg, #f8fbff, #ffffff);
    box-shadow: var(--rxe-shadow-sm);
}

.rx-patient-search-copy label,
.rx-patient-search-copy small {
    display: block;
}

.rx-patient-search-copy label {
    color: var(--rxe-text);
    font-size: 13px;
    font-weight: 750;
}

.rx-patient-search-copy small {
    margin-top: 4px;
    color: var(--rxe-muted);
    font-size: 10px;
    line-height: 1.45;
}

.rx-patient-search-control {
    position: relative;
}

.rx-patient-search-control input {
    width: 100%;
    min-height: 42px;
    padding: 9px 96px 9px 13px;
    border: 1px solid #bdc9d8;
    border-radius: 10px;
    background: #fff;
    color: var(--rxe-text);
    font: inherit;
    font-size: 13px;
    outline: none;
    transition: border-color .15s ease, box-shadow .15s ease;
}

.rx-patient-search-control input:focus {
    border-color: var(--rxe-primary);
    box-shadow: 0 0 0 4px rgba(23, 105, 224, .12);
}

.rx-patient-search-control > span {
    position: absolute;
    top: 50%;
    right: 7px;
    transform: translateY(-50%);
    padding: 5px 9px;
    border-radius: 7px;
    background: var(--rxe-primary-soft);
    color: var(--rxe-primary);
    font-size: 10px;
    font-weight: 750;
    pointer-events: none;
}

.rx-search-results {
    position: absolute;
    z-index: 80;
    top: calc(100% - 8px);
    right: 16px;
    width: min(680px, calc(100% - 32px));
    overflow: auto;
    max-height: 320px;
    border: 1px solid var(--rxe-border);
    border-radius: 12px;
    background: #fff;
    box-shadow: var(--rxe-shadow-lg);
}

.rx-search-results:empty {
    display: none;
}

.rx-selected-patient-card {
    display: none;
    grid-template-columns: 40px minmax(0, 1fr) auto;
    align-items: center;
    gap: 12px;
    padding: 12px 14px;
    border: 1px solid rgba(22, 128, 60, .25);
    border-radius: var(--rxe-radius);
    background: var(--rxe-success-soft);
    box-shadow: var(--rxe-shadow-sm);
}

.rx-selected-patient-card.show {
    display: grid;
}

.rx-selected-patient-icon {
    width: 40px;
    height: 40px;
    display: grid;
    place-items: center;
    border: 1px solid rgba(22, 128, 60, .25);
    border-radius: 50%;
    background: #fff;
    color: var(--rxe-success);
    font-size: 11px;
    font-weight: 800;
}

.rx-selected-patient-copy {
    min-width: 0;
}

.rx-selected-patient-copy strong,
.rx-selected-patient-copy span {
    display: block;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.rx-selected-patient-copy strong {
    font-size: 13px;
    font-weight: 750;
}

.rx-selected-patient-copy span {
    margin-top: 3px;
    color: #3f6f50;
    font-size: 10px;
}

.rx-editor-sheet {
    position: relative;
    border: 1px solid var(--rxe-border);
    border-radius: var(--rxe-radius-lg);
    background: var(--rxe-panel);
    box-shadow: var(--rxe-shadow-lg);
}

.rx-editor-sheet::before {
    content: "";
    position: absolute;
    top: 0;
    left: 28px;
    right: 28px;
    height: 4px;
    border-radius: 0 0 6px 6px;
    background: linear-gradient(90deg, #1769e0, #36a3ff, #16a05d);
}

.rx-editor-letterhead {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
    gap: 30px;
    padding: 30px 34px 23px;
    border-bottom: 1px solid var(--rxe-border);
    background:
        linear-gradient(180deg, rgba(242, 247, 255, .75), rgba(255, 255, 255, 0));
    border-radius: var(--rxe-radius-lg) var(--rxe-radius-lg) 0 0;
}

.rx-editor-doctor-en,
.rx-editor-doctor-bn {
    min-width: 0;
}

.rx-editor-doctor-bn {
    padding-left: 30px;
    border-left: 1px solid var(--rxe-border-soft);
    text-align: right;
}

.rx-editor-letterhead h2 {
    margin: 0 0 7px;
    color: #132a4f;
    font-size: clamp(21px, 1.6vw, 27px);
    font-weight: 800;
    line-height: 1.15;
    letter-spacing: -.025em;
}

.rx-editor-letterhead p {
    margin: 2px 0;
    color: #566276;
    font-size: 11px;
    line-height: 1.45;
}

.rx-editor-highlight,
.rx-editor-contact {
    margin-top: 6px !important;
    color: var(--rxe-primary) !important;
    font-weight: 750;
}

.rx-editor-patient-strip {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 310px;
    gap: 14px;
    padding: 18px 22px;
    border-bottom: 1px solid var(--rxe-border);
    background: #fbfcfe;
}

.rx-editor-patient-main {
    display: grid;
    grid-template-columns: minmax(180px, 1.65fr) .65fr .75fr .75fr minmax(150px, 1.25fr);
    gap: 10px;
}

.rx-editor-patient-meta {
    display: grid;
    grid-template-columns: 1fr;
    gap: 8px;
}

.rx-paper-inline,
.rx-editor-patient-meta label,
.rx-editor-vitals label,
.rx-editor-followup label {
    min-width: 0;
}

.rx-paper-inline label,
.rx-editor-patient-meta label > span,
.rx-editor-vitals label > span,
.rx-editor-followup label > span,
.rx-editor-medicine-fields label,
.rx-library-field > label {
    display: block;
    margin-bottom: 5px;
    color: #596579;
    font-size: 9px;
    font-weight: 800;
    letter-spacing: .04em;
    text-transform: uppercase;
}

.rx-paper-inline input,
.rx-paper-inline select,
.rx-editor-patient-meta input,
.rx-editor-patient-meta select,
.rx-editor-vitals input,
.rx-editor-followup input,
.rx-editor-private-panel textarea,
.rx-library-input-control input,
.rx-library-input-control textarea {
    width: 100%;
    border: 1px solid #c8d2df;
    border-radius: 8px;
    background: #fff;
    color: var(--rxe-text);
    font: inherit;
    font-size: 12px;
    outline: none;
    transition: border-color .15s ease, box-shadow .15s ease, background-color .15s ease;
}

.rx-paper-inline input,
.rx-paper-inline select,
.rx-editor-patient-meta input,
.rx-editor-patient-meta select,
.rx-editor-vitals input,
.rx-editor-followup input,
.rx-library-input-control input {
    min-height: 38px;
    padding: 7px 10px;
}

.rx-paper-inline input:focus,
.rx-paper-inline select:focus,
.rx-editor-patient-meta input:focus,
.rx-editor-patient-meta select:focus,
.rx-editor-vitals input:focus,
.rx-editor-followup input:focus,
.rx-editor-private-panel textarea:focus,
.rx-library-input-control input:focus,
.rx-library-input-control textarea:focus {
    border-color: var(--rxe-primary);
    background: #fff;
    box-shadow: 0 0 0 3px rgba(23, 105, 224, .11);
}

.rx-editor-vitals {
    display: grid;
    grid-template-columns: repeat(5, minmax(0, 1fr));
    gap: 10px;
    padding: 13px 22px;
    border-bottom: 1px solid var(--rxe-border);
    background: linear-gradient(180deg, #f4f8fd, #f8fbff);
}

.rx-editor-vitals label {
    padding: 9px;
    border: 1px solid var(--rxe-border-soft);
    border-radius: 10px;
    background: rgba(255, 255, 255, .88);
}

.rx-editor-body {
    display: grid;
    grid-template-columns: minmax(320px, .72fr) minmax(0, 1.45fr);
    align-items: stretch;
}

.rx-editor-clinical-column {
    min-width: 0;
    padding: 20px;
    border-right: 1px solid var(--rxe-border);
    background: #fbfcfe;
}

.rx-editor-medicine-column {
    min-width: 0;
    padding: 20px;
    background: #fff;
}

.rx-paper-section {
    margin-bottom: 12px;
    padding: 13px;
    border: 1px solid var(--rxe-border-soft);
    border-radius: 11px;
    background: #fff;
    box-shadow: var(--rxe-shadow-sm);
}

.rx-paper-section:last-child {
    margin-bottom: 0;
}

.rx-paper-section-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 10px;
    margin-bottom: 10px;
}

.rx-paper-section-head strong,
.rx-paper-section-head small {
    display: block;
}

.rx-paper-section-head strong {
    color: #21314e;
    font-size: 13px;
    font-weight: 800;
}

.rx-paper-section-head small {
    margin-top: 2px;
    color: var(--rxe-muted);
    font-size: 9px;
}

.rx-paper-add {
    min-width: 29px;
    height: 29px;
    display: inline-grid;
    place-items: center;
    padding: 0 9px;
    border: 1px solid rgba(23, 105, 224, .28);
    border-radius: 8px;
    background: var(--rxe-primary-soft);
    color: var(--rxe-primary);
    font: inherit;
    font-size: 15px;
    font-weight: 800;
    cursor: pointer;
    transition: all .15s ease;
}

.rx-paper-add:hover {
    border-color: var(--rxe-primary);
    background: var(--rxe-primary);
    color: #fff;
    transform: translateY(-1px);
}

.rx-paper-lines,
.rx-editor-test-list,
.rx-editor-medicine-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.rx-paper-line {
    position: relative;
    display: grid;
    grid-template-columns: minmax(0, 1fr) 28px;
    align-items: start;
    gap: 7px;
}

.rx-library-field {
    position: relative;
    min-width: 0;
}

.rx-library-input-control {
    position: relative;
    display: flex;
    align-items: stretch;
}

.rx-library-input-control input,
.rx-library-input-control textarea {
    padding-right: 38px;
}

.rx-library-input-control textarea {
    min-height: 40px;
    padding: 9px 38px 8px 10px;
    line-height: 1.45;
    resize: vertical;
}

.rx-library-open {
    position: absolute;
    top: 4px;
    right: 4px;
    bottom: 4px;
    width: 29px;
    display: grid;
    place-items: center;
    border: 0;
    border-radius: 6px;
    background: #edf3fa;
    color: #526173;
    font: inherit;
    font-size: 14px;
    font-weight: 800;
    cursor: pointer;
    transition: all .15s ease;
}

.rx-library-open:hover {
    background: var(--rxe-primary-soft);
    color: var(--rxe-primary);
}

.rx-paper-line-remove {
    width: 28px;
    height: 28px;
    display: grid;
    place-items: center;
    padding: 0;
    border: 1px solid transparent;
    border-radius: 7px;
    background: transparent;
    color: #9a6a70;
    font: inherit;
    font-size: 16px;
    cursor: pointer;
    transition: all .15s ease;
}

.rx-paper-line-remove:hover {
    border-color: rgba(196, 50, 63, .22);
    background: var(--rxe-danger-soft);
    color: var(--rxe-danger);
}

.rx-field-library-dropdown {
    position: absolute;
    z-index: 120;
    top: calc(100% + 6px);
    left: 0;
    right: 0;
    overflow: auto;
    max-height: 280px;
    border: 1px solid var(--rxe-border);
    border-radius: 10px;
    background: #fff;
    box-shadow: var(--rxe-shadow-lg);
}

.rx-field-library-dropdown:empty {
    display: none;
}

.rx-editor-test-row {
    position: relative;
    display: grid;
    grid-template-columns: 16px minmax(0, 1fr) minmax(0, .85fr) 28px;
    align-items: start;
    gap: 7px;
}

.rx-editor-test-bullet {
    padding-top: 10px;
    color: var(--rxe-primary);
    font-size: 17px;
    font-weight: 800;
}

.rx-editor-rx-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 12px;
    padding-bottom: 12px;
    border-bottom: 1px solid var(--rxe-border-soft);
}

.rx-editor-rx-symbol {
    color: #0e4f9e;
    font-family: Georgia, "Times New Roman", serif;
    font-size: 42px;
    font-weight: 700;
    line-height: 1;
}

.rx-paper-add-medicine {
    width: auto;
    min-height: 34px;
    padding: 6px 12px;
    font-size: 11px;
}

.rx-editor-medicine-row {
    position: relative;
    display: grid;
    grid-template-columns: 28px minmax(0, 1fr) 30px;
    align-items: start;
    gap: 9px;
    padding: 14px;
    border: 1px solid var(--rxe-border);
    border-radius: 12px;
    background: linear-gradient(180deg, #ffffff, #fbfdff);
    box-shadow: var(--rxe-shadow-sm);
}

.rx-editor-medicine-row:hover {
    border-color: #bdcada;
    box-shadow: 0 8px 24px rgba(15, 23, 42, .07);
}

.rx-editor-medicine-number {
    width: 28px;
    height: 28px;
    display: grid;
    place-items: center;
    border-radius: 8px;
    background: #eaf3ff;
    color: var(--rxe-primary);
    font-size: 11px;
    font-weight: 800;
}

.rx-editor-medicine-fields {
    min-width: 0;
}

.rx-editor-medicine-search-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 11px;
    color: var(--rxe-muted);
    font-size: 9px;
    line-height: 1.4;
}

.rx-library-browse-btn {
    flex: 0 0 auto;
    color: var(--rxe-primary);
    font-size: 10px;
    font-weight: 750;
    text-decoration: none;
}

.rx-library-browse-btn:hover {
    text-decoration: underline;
}

.rx-editor-medicine-primary {
    display: grid;
    grid-template-columns: minmax(105px, .52fr) minmax(180px, 1.15fr) minmax(150px, 1fr) minmax(110px, .65fr);
    gap: 9px;
}

.rx-editor-medicine-secondary {
    display: grid;
    grid-template-columns: repeat(4, minmax(110px, 1fr));
    gap: 9px;
    margin-top: 9px;
}

.rx-medicine-manual-note {
    margin-top: 9px;
    padding: 7px 9px;
    border-radius: 7px;
    background: #f7f9fc;
    color: var(--rxe-muted);
    font-size: 9px;
    line-height: 1.4;
}

.rx-editor-bottom {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 300px;
    gap: 22px;
    padding: 22px;
    border-top: 1px solid var(--rxe-border);
    background: linear-gradient(180deg, #fff, #fbfcfe);
    border-radius: 0 0 var(--rxe-radius-lg) var(--rxe-radius-lg);
}

.rx-editor-advice {
    min-width: 0;
}

.rx-editor-followup {
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    gap: 38px;
    padding-left: 22px;
    border-left: 1px solid var(--rxe-border-soft);
}

.rx-editor-signature-line {
    padding-top: 10px;
    border-top: 1px solid #9da9b8;
    color: var(--rxe-muted);
    font-size: 10px;
    text-align: center;
}

.rx-editor-private-panel {
    display: grid;
    grid-template-columns: minmax(220px, .45fr) minmax(0, 1fr);
    gap: 18px;
    padding: 17px;
    border: 1px solid #e5d8b8;
    border-radius: var(--rxe-radius);
    background: linear-gradient(135deg, #fffdf6, #fffaf0);
    box-shadow: var(--rxe-shadow-sm);
}

.rx-editor-private-panel h3 {
    margin: 0;
    color: #704d13;
    font-size: 14px;
    font-weight: 800;
}

.rx-editor-private-panel p {
    margin: 5px 0 0;
    color: #886b3b;
    font-size: 10px;
    line-height: 1.5;
}

.rx-editor-private-panel textarea {
    min-height: 88px;
    padding: 10px 12px;
    resize: vertical;
}

.rx-editor-submit-bar {
    position: sticky;
    z-index: 50;
    bottom: 12px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 18px;
    padding: 14px 16px;
    border: 1px solid rgba(183, 195, 210, .9);
    border-radius: var(--rxe-radius);
    background: rgba(255, 255, 255, .94);
    box-shadow: 0 16px 36px rgba(15, 23, 42, .16);
    backdrop-filter: blur(16px);
}

.rx-editor-submit-bar p {
    margin: 0;
    color: var(--rxe-muted);
    font-size: 11px;
    line-height: 1.45;
}

.rx-draft-message {
    display: block;
    min-height: 14px;
    margin-top: 3px;
    color: var(--rxe-success);
    font-size: 10px;
    font-weight: 700;
}

@media (max-width: 1280px) {
    .rx-editor-body {
        grid-template-columns: minmax(300px, .75fr) minmax(0, 1.25fr);
    }

    .rx-editor-patient-strip {
        grid-template-columns: 1fr;
    }

    .rx-editor-patient-meta {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }

    .rx-editor-medicine-secondary {
        grid-template-columns: repeat(2, minmax(130px, 1fr));
    }
}

@media (max-width: 1050px) {
    .rx-main {
        padding: 18px;
    }

    .rx-editor-body {
        grid-template-columns: 1fr;
    }

    .rx-editor-clinical-column {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 12px;
        border-right: 0;
        border-bottom: 1px solid var(--rxe-border);
    }

    .rx-paper-section {
        margin-bottom: 0;
    }

    .rx-editor-medicine-primary {
        grid-template-columns: repeat(2, minmax(150px, 1fr));
    }
}

@media (max-width: 860px) {
    .rx-editor-hero {
        flex-direction: column;
        padding: 20px;
    }

    .rx-editor-hero .rx-hero-actions {
        width: 100%;
        justify-content: flex-start;
    }

    .rx-editor-toolbar {
        grid-template-columns: 1fr;
    }

    .rx-editor-toolbar-actions {
        justify-content: flex-start;
        overflow-x: auto;
        flex-wrap: nowrap;
        padding-bottom: 3px;
    }

    .rx-patient-search-panel {
        grid-template-columns: 1fr;
    }

    .rx-search-results {
        left: 16px;
        right: 16px;
        width: auto;
    }

    .rx-editor-letterhead {
        grid-template-columns: 1fr;
        gap: 18px;
        padding: 26px 24px 20px;
    }

    .rx-editor-doctor-bn {
        padding-top: 18px;
        padding-left: 0;
        border-top: 1px solid var(--rxe-border-soft);
        border-left: 0;
        text-align: left;
    }

    .rx-editor-patient-main {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .rx-paper-inline-name,
    .rx-paper-inline-address {
        grid-column: 1 / -1;
    }

    .rx-editor-vitals {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }

    .rx-editor-bottom {
        grid-template-columns: 1fr;
    }

    .rx-editor-followup {
        display: grid;
        grid-template-columns: 1fr 240px;
        align-items: end;
        gap: 20px;
        padding-top: 18px;
        padding-left: 0;
        border-top: 1px solid var(--rxe-border-soft);
        border-left: 0;
    }

    .rx-editor-private-panel {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 680px) {
    .rx-main {
        padding: 12px;
    }

    .rx-editor-hero {
        padding: 17px;
        border-radius: 14px;
    }

    .rx-editor-hero h1 {
        font-size: 23px;
    }

    .rx-editor-hero .rx-hero-actions .rx-btn {
        flex: 1 1 auto;
    }

    .rx-selected-patient-card {
        grid-template-columns: 36px minmax(0, 1fr);
    }

    .rx-selected-patient-icon {
        width: 36px;
        height: 36px;
    }

    .rx-selected-patient-actions {
        grid-column: 1 / -1;
    }

    .rx-selected-patient-actions .rx-btn {
        flex: 1 1 0;
    }

    .rx-editor-sheet {
        border-radius: 14px;
    }

    .rx-editor-letterhead,
    .rx-editor-patient-strip,
    .rx-editor-vitals,
    .rx-editor-clinical-column,
    .rx-editor-medicine-column,
    .rx-editor-bottom {
        padding-left: 14px;
        padding-right: 14px;
    }

    .rx-editor-patient-main,
    .rx-editor-patient-meta,
    .rx-editor-vitals,
    .rx-editor-clinical-column,
    .rx-editor-medicine-primary,
    .rx-editor-medicine-secondary {
        grid-template-columns: 1fr;
    }

    .rx-paper-inline-name,
    .rx-paper-inline-address,
    .rx-editor-medicine-primary > :last-child {
        grid-column: auto;
    }

    .rx-editor-test-row {
        grid-template-columns: 15px minmax(0, 1fr) 28px;
    }

    .rx-editor-test-row > .rx-library-field:nth-of-type(2) {
        grid-column: 2 / 3;
    }

    .rx-editor-test-row > .rx-paper-line-remove {
        grid-column: 3;
        grid-row: 1;
    }

    .rx-editor-medicine-row {
        grid-template-columns: 28px minmax(0, 1fr);
        padding: 12px;
    }

    .rx-editor-medicine-row > .rx-paper-line-remove {
        position: absolute;
        top: 10px;
        right: 10px;
    }

    .rx-editor-medicine-search-head {
        align-items: flex-start;
        flex-direction: column;
        padding-right: 28px;
    }

    .rx-editor-followup {
        grid-template-columns: 1fr;
    }

    .rx-editor-submit-bar {
        position: static;
        align-items: stretch;
        flex-direction: column;
    }

    .rx-editor-submit-bar .rx-actions {
        width: 100%;
    }

    .rx-editor-submit-bar .rx-btn {
        flex: 1 1 0;
    }
}

@media (prefers-reduced-motion: reduce) {
    .rx-prescription-editor *,
    .rx-editor-hero * {
        scroll-behavior: auto !important;
        transition: none !important;
    }
}

.rx-prescription-editor .rx-editor-rx-head {
    justify-content: flex-start;
}

.rx-prescription-editor .rx-add-medicine-bottom {
    width: 100%;
    margin-top: 14px;
}

.rx-prescription-editor .rx-add-medicine-full-button {
    width: 100%;
    min-height: 56px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 11px;
    padding: 10px 16px;
    border: 1px dashed #94b8e7;
    border-radius: 12px;
    background: linear-gradient(180deg, #f5f9ff, #eaf3ff);
    color: #1769e0;
    font: inherit;
    text-align: left;
    cursor: pointer;
    box-shadow: 0 4px 14px rgba(23, 105, 224, .08);
    transition:
        border-color .15s ease,
        background-color .15s ease,
        color .15s ease,
        transform .15s ease,
        box-shadow .15s ease;
}

.rx-prescription-editor .rx-add-medicine-full-button:hover {
    border-color: #1769e0;
    background: #1769e0;
    color: #ffffff;
    transform: translateY(-1px);
    box-shadow: 0 10px 24px rgba(23, 105, 224, .2);
}

.rx-prescription-editor .rx-add-medicine-full-button:focus-visible {
    outline: 0;
    box-shadow:
        0 0 0 4px rgba(23, 105, 224, .14),
        0 10px 24px rgba(23, 105, 224, .16);
}

.rx-prescription-editor .rx-add-medicine-full-icon {
    width: 32px;
    height: 32px;
    flex: 0 0 32px;
    display: grid;
    place-items: center;
    border: 1px solid currentColor;
    border-radius: 50%;
    font-size: 20px;
    font-weight: 700;
    line-height: 1;
}

.rx-prescription-editor .rx-add-medicine-full-button strong,
.rx-prescription-editor .rx-add-medicine-full-button small {
    display: block;
}

.rx-prescription-editor .rx-add-medicine-full-button strong {
    font-size: 13px;
    font-weight: 800;
    line-height: 1.25;
}

.rx-prescription-editor .rx-add-medicine-full-button small {
    margin-top: 2px;
    color: #55749d;
    font-size: 9px;
    font-weight: 600;
    line-height: 1.35;
}

.rx-prescription-editor .rx-add-medicine-full-button:hover small {
    color: rgba(255, 255, 255, .82);
}

@media (max-width: 680px) {
    .rx-prescription-editor .rx-add-medicine-full-button {
        min-height: 52px;
        padding: 9px 12px;
    }
}

.rx-prescription-editor .rx-advice-after-medicine {
    margin-top: 14px;
    padding: 14px;
    border: 1px solid var(--rxe-border);
    border-radius: 12px;
    background: linear-gradient(180deg, #ffffff, #fbfdff);
    box-shadow: var(--rxe-shadow-sm);
}

.rx-prescription-editor .rx-advice-after-medicine .rx-paper-section-head {
    margin-bottom: 10px;
}

.rx-prescription-editor .rx-editor-bottom {
    grid-template-columns: 1fr;
}

.rx-prescription-editor .rx-editor-followup {
    display: grid;
    grid-template-columns: minmax(180px, 300px) minmax(220px, 1fr);
    align-items: end;
    gap: 30px;
    padding-left: 0;
    border-left: 0;
}

@media (max-width: 680px) {
    .rx-prescription-editor .rx-editor-followup {
        grid-template-columns: 1fr;
        gap: 28px;
    }
}
</style>



<style>
/* Final medicine editor rules */
.rx-editor-hero,
.rx-editor-hero *,
.rx-prescription-editor,
.rx-prescription-editor *,
.rx-alert,
.rx-alert * {
    font-weight: 500 !important;
}

.rx-prescription-editor .rx-editor-medicine-fields label {
    display: none !important;
}

.rx-prescription-editor .rx-editor-medicine-primary,
.rx-prescription-editor .rx-editor-medicine-secondary {
    margin-top: 0;
}

.rx-prescription-editor .rx-library-input-control input::placeholder,
.rx-prescription-editor .rx-library-input-control textarea::placeholder {
    color: #aab3bf;
    opacity: 1;
    font-weight: 500;
}

.rx-prescription-editor .rx-editor-medicine-list {
    overflow: visible;
    max-height: none !important;
}

.rx-prescription-editor .rx-editor-medicine-row {
    transition:
        transform .34s ease,
        opacity .34s ease,
        border-color .2s ease,
        box-shadow .2s ease;
}

.rx-prescription-editor .rx-editor-medicine-row.rx-medicine-row-enter {
    opacity: 0;
    transform: translateY(28px);
}

.rx-prescription-editor .rx-editor-medicine-row.rx-medicine-row-invalid {
    border-color: #d64d59;
    box-shadow: 0 0 0 3px rgba(196, 50, 63, .10);
}

.rx-prescription-editor .rx-editor-medicine-row.rx-medicine-row-duplicate {
    border-color: #c98216;
    box-shadow: 0 0 0 3px rgba(201, 130, 22, .12);
}

.rx-prescription-editor .rx-medicine-editor-message {
    min-height: 0;
    margin-top: 10px;
    color: #c4323f;
    font-size: 11px;
    line-height: 1.4;
    text-align: center;
}

.rx-prescription-editor .rx-medicine-editor-message:empty {
    display: none;
}

.rx-prescription-editor .rx-add-medicine-bottom {
    margin-top: 12px;
}

.rx-prescription-editor .rx-add-medicine-full-button {
    min-height: 52px;
}

.rx-prescription-editor .rx-add-medicine-full-button small {
    display: none !important;
}
</style>


<style>
/* Clean full-width library dropdowns for this editor only */
.rx-prescription-editor .rx-library-open {
    display: none !important;
}

.rx-prescription-editor .rx-library-input-control input,
.rx-prescription-editor .rx-library-input-control textarea {
    padding-right: 11px !important;
    cursor: text;
}

.rx-prescription-editor .rx-field-library-dropdown {
    top: calc(100% + 7px);
    left: 0;
    right: 0;
    width: 100%;
    min-width: 100%;
    max-height: 360px;
    overflow-x: hidden;
    overflow-y: auto;
    border: 1px solid #c8d3e1;
    border-radius: 10px;
    background: #ffffff;
    box-shadow: 0 18px 42px rgba(15, 23, 42, .16);
}

.rx-prescription-editor .rx-field-library-dropdown > * {
    max-width: 100%;
}

.rx-prescription-editor .rx-field-library-dropdown a,
.rx-prescription-editor .rx-field-library-dropdown button,
.rx-prescription-editor .rx-field-library-dropdown [role="button"] {
    width: 100%;
    min-height: 38px;
    display: flex;
    align-items: center;
    padding: 8px 11px;
    border: 0;
    border-bottom: 1px solid #edf1f5;
    border-radius: 0;
    background: #ffffff;
    color: #172033;
    font: inherit;
    font-size: 12px;
    line-height: 1.4;
    text-align: left;
    text-decoration: none;
    white-space: normal;
    cursor: pointer;
}

.rx-prescription-editor .rx-field-library-dropdown a:hover,
.rx-prescription-editor .rx-field-library-dropdown button:hover,
.rx-prescription-editor .rx-field-library-dropdown [role="button"]:hover {
    background: #edf5ff;
    color: #0e57c7;
}

.rx-prescription-editor .rx-field-library-dropdown > :last-child {
    border-bottom: 0;
}

.rx-prescription-editor .rx-editor-medicine-primary {
    grid-template-columns:
        minmax(112px, .58fr)
        minmax(190px, 1.2fr)
        minmax(155px, 1fr)
        minmax(115px, .68fr);
}

@media (max-width: 1050px) {
    .rx-prescription-editor .rx-editor-medicine-primary {
        grid-template-columns: repeat(2, minmax(150px, 1fr));
    }
}

@media (max-width: 680px) {
    .rx-prescription-editor .rx-editor-medicine-primary {
        grid-template-columns: 1fr;
    }
}
</style>



<style>
/* Collapsible prescription-style medicine cards */
.rx-prescription-editor .rx-editor-medicine-row {
    grid-template-columns: 28px minmax(0, 1fr) 68px;
}

.rx-prescription-editor .rx-medicine-prescription-preview {
    display: none;
    min-width: 0;
    padding: 1px 0 2px;
}

.rx-prescription-editor .rx-medicine-preview-name {
    margin: 0;
    color: #172033;
    font-size: 13px;
    font-weight: 700 !important;
    line-height: 1.45;
    overflow-wrap: anywhere;
}

.rx-prescription-editor .rx-medicine-preview-direction {
    margin: 5px 0 0;
    padding-left: 14px;
    color: #556174;
    font-size: 11px;
    line-height: 1.5;
    overflow-wrap: anywhere;
}

.rx-prescription-editor .rx-medicine-preview-direction::before {
    content: "↳ ";
    margin-left: -14px;
    color: #1769e0;
}

.rx-prescription-editor .rx-medicine-preview-direction:empty {
    display: none;
}

.rx-prescription-editor .rx-medicine-row-actions {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 5px;
}

.rx-prescription-editor .rx-medicine-edit-button {
    width: 30px;
    height: 30px;
    display: inline-grid;
    place-items: center;
    padding: 0;
    border: 1px solid #bcd0ea;
    border-radius: 8px;
    background: #edf5ff;
    color: #1769e0;
    cursor: pointer;
    transition: background-color .15s ease, border-color .15s ease, color .15s ease, transform .15s ease;
}

.rx-prescription-editor .rx-medicine-edit-button:hover {
    border-color: #1769e0;
    background: #1769e0;
    color: #fff;
    transform: translateY(-1px);
}

.rx-prescription-editor .rx-medicine-edit-button svg {
    width: 14px;
    height: 14px;
    pointer-events: none;
}

.rx-prescription-editor .rx-medicine-card-footer {
    display: flex;
    justify-content: flex-end;
    margin-top: 12px;
    padding-top: 12px;
    border-top: 1px solid #e5ebf2;
}

.rx-prescription-editor .rx-medicine-save-button {
    min-height: 36px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    padding: 7px 15px;
    border: 1px solid #16803c;
    border-radius: 8px;
    background: #16803c;
    color: #ffffff;
    font: inherit;
    font-size: 11px;
    font-weight: 700 !important;
    cursor: pointer;
    box-shadow: 0 6px 14px rgba(22, 128, 60, .16);
    transition: background-color .15s ease, border-color .15s ease, transform .15s ease, box-shadow .15s ease;
}

.rx-prescription-editor .rx-medicine-save-button:hover {
    border-color: #0f6b31;
    background: #0f6b31;
    transform: translateY(-1px);
    box-shadow: 0 9px 18px rgba(22, 128, 60, .2);
}

.rx-prescription-editor .rx-medicine-save-button:focus-visible {
    outline: 0;
    box-shadow: 0 0 0 4px rgba(22, 128, 60, .14);
}

.rx-prescription-editor .rx-editor-medicine-row.is-preview {
    align-items: start;
    padding: 10px 5px 11px;
    border: 0;
    border-bottom: 1px dashed #d8e0ea;
    border-radius: 0;
    background: transparent;
    box-shadow: none;
}

.rx-prescription-editor .rx-editor-medicine-row.is-preview:hover {
    border-color: #b9c8da;
    background: #fbfdff;
    box-shadow: none;
}

.rx-prescription-editor .rx-editor-medicine-row.is-preview .rx-medicine-prescription-preview {
    display: block;
}

.rx-prescription-editor .rx-editor-medicine-row.is-preview .rx-editor-medicine-primary,
.rx-prescription-editor .rx-editor-medicine-row.is-preview .rx-editor-medicine-secondary,
.rx-prescription-editor .rx-editor-medicine-row.is-preview .rx-medicine-manual-note,
.rx-prescription-editor .rx-editor-medicine-row.is-preview .rx-medicine-card-footer,
.rx-prescription-editor .rx-editor-medicine-row.is-preview .rx-field-library-dropdown {
    display: none !important;
}

.rx-prescription-editor .rx-editor-medicine-row.is-editing {
    border-color: #91b9ed;
    box-shadow: 0 0 0 3px rgba(23, 105, 224, .08), var(--rxe-shadow-sm);
}

.rx-prescription-editor .rx-editor-medicine-row.is-editing .rx-medicine-edit-button {
    display: none;
}

.rx-prescription-editor .rx-editor-medicine-row.is-preview .rx-editor-medicine-number {
    margin-top: 1px;
    background: transparent;
    color: #172033;
    font-size: 12px;
}

@media (max-width: 680px) {
    .rx-prescription-editor .rx-editor-medicine-row {
        grid-template-columns: 28px minmax(0, 1fr);
        padding-right: 48px;
    }

    .rx-prescription-editor .rx-medicine-row-actions {
        position: absolute;
        top: 9px;
        right: 8px;
        flex-direction: column;
    }

    .rx-prescription-editor .rx-editor-medicine-row.is-preview {
        padding-right: 46px;
    }
}
</style>

<?php if ($form_error !== ''): ?>
    <div class="rx-alert error" role="alert">
        <span class="rx-alert-icon">!</span>
        <div>
            <strong>Prescription was not saved</strong>
            <p><?= prescription_e($form_error) ?></p>
        </div>
    </div>
<?php endif; ?>

<section class="rx-page-hero rx-editor-hero">
    <div>
        <div class="rx-breadcrumb">
            <a href="<?= prescription_e(prescription_url()) ?>">Prescriptions</a>
            <span>/</span>
            <span><?= $editing ? 'Edit' : 'New' ?></span>
        </div>
        <h1><?= $editing ? 'Edit Prescription' : 'Create Prescription' ?></h1>
        <p>The editor below follows the printed prescription layout. Use any + button to add a new line exactly in that section.</p>
    </div>
    <div class="rx-hero-actions">
        <a class="rx-btn rx-btn-white" href="<?= prescription_e(prescription_library_list_url('medicine')) ?>">Prescription Libraries</a>
        <?php if ($editing): ?>
            <a class="rx-btn rx-btn-white" href="<?= prescription_e(prescription_url('view.php?id=' . $prescription_id)) ?>">View</a>
        <?php endif; ?>
        <a class="rx-btn rx-btn-white" href="<?= prescription_e(prescription_url()) ?>">Back to List</a>
    </div>
</section>

<form method="POST" id="prescriptionForm" class="rx-prescription-editor" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= prescription_e(prescription_csrf_token()) ?>">
    <input type="hidden" name="prescription_id" value="<?= prescription_e((string)$prescription_id) ?>">
    <input type="hidden" name="patient_id" value="<?= prescription_e(rx_form_value('patient_id')) ?>">

    <div class="rx-editor-toolbar" aria-label="Quick add prescription content">
        <div class="rx-editor-toolbar-copy">
            <strong>Quick Add</strong>
            <span>Click an option to add a new entry in its exact prescription area.</span>
        </div>
        <div class="rx-editor-toolbar-actions">
            <button type="button" class="rx-quick-add js-quick-add" data-add-type="line" data-target="chief_complaints">+ C/C</button>
            <button type="button" class="rx-quick-add js-quick-add" data-add-type="line" data-target="diagnosis">+ Dx</button>
            <button type="button" class="rx-quick-add js-quick-add" data-add-type="line" data-target="medical_history">+ D/H</button>
            <button type="button" class="rx-quick-add js-quick-add" data-add-type="line" data-target="examination">+ O/E</button>
            <button type="button" class="rx-quick-add js-quick-add" data-add-type="test">+ Investigation</button>
            <button type="button" class="rx-quick-add js-quick-add" data-add-type="medicine">+ Medicine</button>
            <button type="button" class="rx-quick-add js-quick-add" data-add-type="line" data-target="advice">+ Advice</button>
        </div>
    </div>

    <div class="rx-patient-search-panel rx-patient-search-wrap">
        <div class="rx-patient-search-copy">
            <label for="patientSearch">Find Existing Patient</label>
            <small>Type a name or mobile number here, or directly in the Patient and Phone fields below.</small>
        </div>
        <div class="rx-patient-search-control">
            <input type="search" id="patientSearch" placeholder="Name, mobile number or patient code" autocomplete="off">
            <span>Auto Find</span>
        </div>
        <div id="patientSearchResults" class="rx-search-results"></div>
    </div>

    <div id="selectedPatientCard" class="rx-selected-patient-card<?= $selected_patient ? ' show' : '' ?>">
        <div class="rx-selected-patient-icon">PT</div>
        <div class="rx-selected-patient-copy">
            <strong id="selectedPatientName"><?= prescription_e($selected_patient['name'] ?? '') ?></strong>
            <span id="selectedPatientMeta"><?php if ($selected_patient): ?><?= prescription_e(($selected_patient['patient_code'] ?? '') . (($selected_patient['phone'] ?? '') !== '' ? ' · ' . $selected_patient['phone'] : '')) ?><?php endif; ?></span>
        </div>
        <div class="rx-selected-patient-actions">
            <a id="selectedPatientHistoryLink" class="rx-btn rx-btn-soft rx-btn-sm" href="<?= $selected_patient ? prescription_e(prescription_url('patient-view.php?id=' . (int)$selected_patient['id'])) : '#' ?>">View History</a>
            <button type="button" id="clearSelectedPatient" class="rx-btn rx-btn-soft rx-btn-sm">Use as New Patient</button>
        </div>
    </div>

    <article class="rx-editor-sheet">
        <header class="rx-editor-letterhead">
            <section class="rx-editor-doctor-en">
                <h2><?= prescription_e($doctor_name) ?></h2>
                <?php foreach (array_filter([$doctor_degree, $doctor_training, $doctor_fellowship, $doctor_designation, $doctor_hospital]) as $line): ?>
                    <p><?= prescription_e((string)$line) ?></p>
                <?php endforeach; ?>
                <?php if ($doctor_bmdc !== ''): ?>
                    <p class="rx-editor-highlight">BMDC: <?= prescription_e($doctor_bmdc) ?></p>
                <?php endif; ?>
                <?php if ($doctor_phone !== ''): ?>
                    <p class="rx-editor-contact">Cell: <?= prescription_e($doctor_phone) ?></p>
                <?php endif; ?>
            </section>

            <section class="rx-editor-doctor-bn">
                <?php if ($doctor_name_bn !== ''): ?>
                    <h2><?= prescription_e($doctor_name_bn) ?></h2>
                <?php endif; ?>
                <?php foreach (array_filter([$doctor_degree_bn, $doctor_training_bn, $doctor_fellowship_bn, $doctor_designation_bn, $doctor_hospital_bn]) as $line): ?>
                    <p><?= prescription_e((string)$line) ?></p>
                <?php endforeach; ?>
                <?php if ($doctor_phone !== ''): ?>
                    <p class="rx-editor-contact">যোগাযোগ: <?= prescription_e($doctor_phone) ?></p>
                <?php endif; ?>
            </section>
        </header>

        <section class="rx-editor-patient-strip">
            <div class="rx-editor-patient-main">
                <div class="rx-paper-inline rx-paper-inline-name">
                    <label>Patient</label>
                    <input type="text" id="patientName" name="patient_name" value="<?= prescription_e(rx_form_value('patient_name')) ?>" required placeholder="Type name to auto-find" autocomplete="off">
                </div>
                <div class="rx-paper-inline">
                    <label>Age</label>
                    <input type="text" name="patient_age" value="<?= prescription_e(rx_form_value('patient_age')) ?>" placeholder="35 years">
                </div>
                <div class="rx-paper-inline">
                    <label>Gender</label>
                    <?php $gender = rx_form_value('patient_gender'); ?>
                    <select name="patient_gender">
                        <option value="">—</option>
                        <option value="Male" <?= $gender === 'Male' ? 'selected' : '' ?>>Male</option>
                        <option value="Female" <?= $gender === 'Female' ? 'selected' : '' ?>>Female</option>
                        <option value="Other" <?= $gender === 'Other' ? 'selected' : '' ?>>Other</option>
                    </select>
                </div>
                <div class="rx-paper-inline">
                    <label>Weight</label>
                    <input type="text" name="weight" value="<?= prescription_e(rx_form_value('weight')) ?>" placeholder="70 kg">
                </div>
                <div class="rx-paper-inline rx-paper-inline-address">
                    <label>Address</label>
                    <input type="text" name="patient_address" value="<?= prescription_e(rx_form_value('patient_address')) ?>" placeholder="Patient address">
                </div>
            </div>

            <div class="rx-editor-patient-meta">
                <label>
                    <span>Date</span>
                    <input type="date" name="visit_date" value="<?= prescription_e(rx_form_value('visit_date', date('Y-m-d'))) ?>" required>
                </label>
                <label>
                    <span>Phone</span>
                    <input type="text" id="patientPhone" name="patient_phone" value="<?= prescription_e(rx_form_value('patient_phone')) ?>" placeholder="Type mobile to auto-find" autocomplete="off">
                </label>
                <label>
                    <span>Blood Group</span>
                    <?php $blood_group = rx_form_value('patient_blood_group'); ?>
                    <select name="patient_blood_group">
                        <option value="">—</option>
                        <?php foreach (['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'] as $group): ?>
                            <option value="<?= prescription_e($group) ?>" <?= $blood_group === $group ? 'selected' : '' ?>><?= prescription_e($group) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>
        </section>

        <section class="rx-editor-vitals">
            <?php
            $vitals = [
                'height' => ['Height', '170 cm'],
                'blood_pressure' => ['BP', '120/80'],
                'temperature' => ['Temp', '98.6°F'],
                'pulse' => ['Pulse', '72 bpm'],
                'spo2' => ['SpO₂', '98%'],
            ];
            ?>
            <?php foreach ($vitals as $field => [$label, $placeholder]): ?>
                <label>
                    <span><?= prescription_e($label) ?></span>
                    <input type="text" name="<?= prescription_e($field) ?>" value="<?= prescription_e(rx_form_value($field)) ?>" placeholder="<?= prescription_e($placeholder) ?>">
                </label>
            <?php endforeach; ?>
        </section>

        <section class="rx-editor-body">
            <div class="rx-editor-clinical-column">
                <?php foreach ($clinical_sections as $field => $meta): ?>
                    <section class="rx-paper-section" id="section-<?= prescription_e($field) ?>">
                        <div class="rx-paper-section-head">
                            <div>
                                <strong><?= prescription_e($meta['title']) ?>:</strong>
                                <small><?= prescription_e($meta['subtitle']) ?></small>
                            </div>
                            <button type="button" class="rx-paper-add js-add-line" data-target="<?= prescription_e($field) ?>" title="Add <?= prescription_e($meta['subtitle']) ?> line">+</button>
                        </div>
                        <textarea class="js-line-hidden" name="<?= prescription_e($field) ?>" data-line-hidden="<?= prescription_e($field) ?>" hidden><?= prescription_e(rx_form_value($field)) ?></textarea>
                        <div class="rx-paper-lines" data-line-list="<?= prescription_e($field) ?>" data-placeholder="<?= prescription_e($meta['placeholder']) ?>">
                            <?php foreach ($clinical_values[$field] as $line): ?>
                                <div class="rx-paper-line js-paper-line rx-library-field">
                                    <div class="rx-library-input-control rx-line-library-control">
                                        <textarea
                                            rows="1"
                                            class="js-line-input js-library-input"
                                            data-library-type="<?= prescription_e($meta['library_type']) ?>"
                                            placeholder="<?= prescription_e($meta['placeholder']) ?>"
                                            autocomplete="off"
                                        ><?= prescription_e($line) ?></textarea>
                                        <button type="button" class="rx-library-open js-open-library" aria-label="Open <?= prescription_e($meta['subtitle']) ?> library"></button>
                                    </div>
                                    <div class="rx-field-library-dropdown"></div>
                                    <button type="button" class="rx-paper-line-remove js-remove-line" aria-label="Remove line">×</button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endforeach; ?>

                <section class="rx-paper-section rx-paper-investigation" id="section-investigation">
                    <div class="rx-paper-section-head">
                        <div>
                            <strong>Investigations:</strong>
                            <small>Laboratory and imaging tests</small>
                        </div>
                        <button type="button" class="rx-paper-add" id="addTestRow" title="Add investigation">+</button>
                    </div>
                    <div class="rx-editor-test-list" id="testRows">
                        <?php foreach ($tests as $test): ?>
                            <div class="rx-editor-test-row js-test-row">
                                <span class="rx-editor-test-bullet">•</span>

                                <div class="rx-library-field">
                                    <div class="rx-library-input-control">
                                        <input
                                            type="text"
                                            name="test_name[]"
                                            class="js-library-input"
                                            data-library-type="test"
                                            value="<?= prescription_e($test['test_name'] ?? '') ?>"
                                            placeholder="Type or select investigation"
                                            autocomplete="off"
                                        >
                                        <button type="button" class="rx-library-open js-open-library" aria-label="Open tests library"></button>
                                    </div>
                                    <div class="rx-field-library-dropdown"></div>
                                </div>

                                <div class="rx-library-field">
                                    <div class="rx-library-input-control">
                                        <input
                                            type="text"
                                            name="test_instruction[]"
                                            class="js-library-input"
                                            data-library-type="test_instruction"
                                            value="<?= prescription_e($test['instruction'] ?? '') ?>"
                                            placeholder="Type or select test instruction"
                                            autocomplete="off"
                                        >
                                        <button type="button" class="rx-library-open js-open-library" aria-label="Open test instructions library"></button>
                                    </div>
                                    <div class="rx-field-library-dropdown"></div>
                                </div>

                                <button type="button" class="rx-paper-line-remove js-remove-row" aria-label="Remove test">×</button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            </div>

            <div class="rx-editor-medicine-column">
                <div class="rx-editor-rx-head">
                    <span class="rx-editor-rx-symbol">℞</span>
                </div>

                <div class="rx-editor-medicine-list" id="medicineRows">
                    <?php foreach ($medicines as $index => $medicine): ?>
                        <?php
                        $generic_name = trim((string)(
                            $medicine['generic_name']
                            ?? $medicine['medicine_generic_name']
                            ?? $medicine['medicine_name']
                            ?? ''
                        ));
                        $brand_name = trim((string)(
                            $medicine['brand_name']
                            ?? $medicine['medicine_brand_name']
                            ?? ''
                        ));
                        $medicine_form = trim((string)(
                            $medicine['medicine_form']
                            ?? ''
                        ));
                        ?>
                        <div class="rx-editor-medicine-row js-medicine-row">
                            <span class="rx-editor-medicine-number"><?= prescription_e((string)($index + 1)) ?>.</span>
                            <div class="rx-editor-medicine-fields">
                                <input type="hidden" name="medicine_library_id[]" value="<?= prescription_e((string)($medicine['library_id'] ?? '')) ?>" class="js-medicine-library-id">
<div class="rx-editor-medicine-primary rx-field">
                                    <div class="rx-medicine-input-wrap rx-library-field">
                                        <label>Medicine Form</label>
                                        <div class="rx-library-input-control">
                                            <input
                                                type="text"
                                                name="medicine_form[]"
                                                class="js-library-input"
                                                data-library-type="medicine_form"
                                                value="<?= prescription_e($medicine_form) ?>"
                                                placeholder="Medicine form — Tab., Cap., Syr..."
                                                autocomplete="off"
                                            >
                                            <button type="button" class="rx-library-open js-open-library" aria-label="Open medicine forms library"></button>
                                        </div>
                                        <div class="rx-field-library-dropdown"></div>
                                    </div>

                                    <div class="rx-medicine-input-wrap rx-library-field">
                                        <label>Generic Name</label>
                                        <div class="rx-library-input-control">
                                            <input
                                                type="text"
                                                name="medicine_generic_name[]"
                                                class="js-library-input js-medicine-generic"
                                                data-library-type="medicine"
                                                data-library-role="both"
                                                data-search-fields="generic_name,brand_name"
                                                value="<?= prescription_e($generic_name) ?>"
                                                placeholder="Generic name — search generic or brand"
                                                autocomplete="off"
                                            >
                                            <button type="button" class="rx-library-open js-open-library" aria-label="Open medicine names library"></button>
                                        </div>
                                        <div class="rx-field-library-dropdown"></div>
                                    </div>

                                    <div class="rx-medicine-input-wrap rx-library-field">
                                        <label>Brand Name <small>(optional)</small></label>
                                        <div class="rx-library-input-control">
                                            <input
                                                type="text"
                                                name="medicine_brand_name[]"
                                                class="js-library-input js-medicine-brand"
                                                data-library-type="medicine"
                                                data-library-role="both"
                                                data-search-fields="generic_name,brand_name"
                                                value="<?= prescription_e($brand_name) ?>"
                                                placeholder="Brand name (optional) — search generic or brand"
                                                autocomplete="off"
                                            >
                                            <button type="button" class="rx-library-open js-open-library" aria-label="Open medicine names library"></button>
                                        </div>
                                        <div class="rx-field-library-dropdown"></div>
                                    </div>

                                    <div class="rx-medicine-input-wrap rx-library-field">
                                        <label>Strength</label>
                                        <div class="rx-library-input-control">
                                            <input type="text" name="medicine_strength[]" class="js-library-input" data-library-type="strength" value="<?= prescription_e($medicine['strength'] ?? '') ?>" placeholder="Strength — e.g. 500 mg" autocomplete="off">
                                            <button type="button" class="rx-library-open js-open-library" aria-label="Open strength library"></button>
                                        </div>
                                        <div class="rx-field-library-dropdown"></div>
                                    </div>
                                </div>

                                <div class="rx-editor-medicine-secondary">
                                    <div class="rx-library-field">
                                        <label>Dosage</label>
                                        <div class="rx-library-input-control">
                                            <input type="text" name="medicine_dosage[]" class="js-library-input" data-library-type="dosage" value="<?= prescription_e($medicine['dosage'] ?? '') ?>" placeholder="Dosage — e.g. 1 tablet" autocomplete="off">
                                            <button type="button" class="rx-library-open js-open-library" aria-label="Open dosage library"></button>
                                        </div>
                                        <div class="rx-field-library-dropdown"></div>
                                    </div>
                                    <div class="rx-library-field">
                                        <label>Frequency</label>
                                        <div class="rx-library-input-control">
                                            <input type="text" name="medicine_frequency[]" class="js-library-input" data-library-type="frequency" value="<?= prescription_e($medicine['frequency'] ?? '') ?>" placeholder="Frequency — e.g. 1+0+1" autocomplete="off">
                                            <button type="button" class="rx-library-open js-open-library" aria-label="Open frequency library"></button>
                                        </div>
                                        <div class="rx-field-library-dropdown"></div>
                                    </div>
                                    <div class="rx-library-field">
                                        <label>Duration</label>
                                        <div class="rx-library-input-control">
                                            <input type="text" name="medicine_duration[]" class="js-library-input" data-library-type="duration" value="<?= prescription_e($medicine['duration'] ?? '') ?>" placeholder="Duration — e.g. 5 days" autocomplete="off">
                                            <button type="button" class="rx-library-open js-open-library" aria-label="Open duration library"></button>
                                        </div>
                                        <div class="rx-field-library-dropdown"></div>
                                    </div>
                                    <div class="rx-library-field">
                                        <label>Instruction</label>
                                        <div class="rx-library-input-control">
                                            <input type="text" name="medicine_instruction[]" class="js-library-input" data-library-type="instruction" value="<?= prescription_e($medicine['instruction'] ?? '') ?>" placeholder="Instruction — e.g. after meal" autocomplete="off">
                                            <button type="button" class="rx-library-open js-open-library" aria-label="Open instruction library"></button>
                                        </div>
                                        <div class="rx-field-library-dropdown"></div>
                                    </div>
                                </div>
                            </div>
                            <button type="button" class="rx-paper-line-remove js-remove-row" aria-label="Remove medicine">×</button>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div
                    id="medicineEditorMessage"
                    class="rx-medicine-editor-message"
                    role="status"
                    aria-live="polite"
                ></div>

                <div class="rx-add-medicine-bottom">
                    <button
                        type="button"
                        class="rx-add-medicine-full-button"
                        id="addMedicineRow"
                    >
                        <span class="rx-add-medicine-full-icon">+</span>
                        <span>
                            <strong>Add Medicine</strong>
                        </span>
                    </button>
                </div>

                <div class="rx-advice-after-medicine">
            <div class="rx-editor-advice" id="section-advice">
                <div class="rx-paper-section-head">
                    <div>
                        <strong>Advice:</strong>
                        <small>উপদেশ</small>
                    </div>
                    <button type="button" class="rx-paper-add js-add-line" data-target="advice" title="Add advice line">+</button>
                </div>
                <textarea class="js-line-hidden" name="advice" data-line-hidden="advice" hidden><?= prescription_e(rx_form_value('advice')) ?></textarea>
                <div class="rx-paper-lines" data-line-list="advice" data-placeholder="Write advice or instruction">
                    <?php foreach ($advice_lines as $line): ?>
                        <div class="rx-paper-line js-paper-line rx-library-field">
                            <div class="rx-library-input-control rx-line-library-control">
                                <textarea
                                    rows="1"
                                    class="js-line-input js-library-input"
                                    data-library-type="advice"
                                    placeholder="Write or select advice"
                                    autocomplete="off"
                                ><?= prescription_e($line) ?></textarea>
                                <button type="button" class="rx-library-open js-open-library" aria-label="Open advice library"></button>
                            </div>
                            <div class="rx-field-library-dropdown"></div>
                            <button type="button" class="rx-paper-line-remove js-remove-line" aria-label="Remove line">×</button>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
                </div>
            </div>
        </section>

        <section class="rx-editor-bottom">
            <div class="rx-editor-followup">
                <label>
                    <span>Follow-up Date</span>
                    <input type="date" name="follow_up_date" value="<?= prescription_e(rx_form_value('follow_up_date')) ?>">
                </label>
                <div class="rx-editor-signature-line">Doctor Signature</div>
            </div>
        </section>
    </article>






    <section class="rx-editor-private-panel">
        <div>
            <h3>Private Doctor Notes</h3>
            <p>This information remains in your account and is never shown on the printed prescription.</p>
        </div>
        <textarea name="private_notes" placeholder="Private notes for your own reference"><?= prescription_e(rx_form_value('private_notes')) ?></textarea>
    </section>

    <div class="rx-submit-bar rx-editor-submit-bar">
        <div>
            <p>Use Save Draft to continue later, or complete the prescription to view and print it.</p>
            <span id="draftMessage" class="rx-draft-message"></span>
        </div>
        <div class="rx-actions">
            <button type="button" id="saveDraftButton" class="rx-btn rx-btn-soft">Save Draft</button>
            <button type="submit" name="save_status" value="active" class="rx-btn rx-btn-primary">
                <?= $editing ? 'Update Prescription' : 'Complete Prescription' ?>
            </button>
        </div>
    </div>
</form>


<script>
document.addEventListener('DOMContentLoaded', function () {
    const editor = document.getElementById('prescriptionForm');

    if (!editor) {
        return;
    }

    const normalizeText = function (value) {
        return String(value || '').replace(/\s+/g, ' ').trim();
    };

    const unwantedMedicineHelperText = function (value) {
        const text = normalizeText(value).toLowerCase();

        return text === 'manage libraries'
            || text === 'each field has its own library, and manual entry remains available.'
            || text === 'each field has its own library, and manual entry remains available'
            || text === 'select from each independent dropdown or continue typing manually in any field.'
            || text === 'select from each independent dropdown or continue typing manually in any field';
    };

    const cleanMedicineHelperMessages = function (root) {
        const scope = root instanceof Element || root instanceof Document
            ? root
            : document;

        scope.querySelectorAll(
            '.rx-editor-medicine-column a, '
            + '.rx-editor-medicine-column button, '
            + '.rx-editor-medicine-column p, '
            + '.rx-editor-medicine-column small, '
            + '.rx-editor-medicine-column span, '
            + '.rx-editor-medicine-column div'
        ).forEach(function (element) {
            if (element.children.length > 0) {
                return;
            }

            if (unwantedMedicineHelperText(element.textContent)) {
                element.remove();
            }
        });
    };

    const cleanDropdownMessages = function (root) {
        const scope = root instanceof Element || root instanceof Document
            ? root
            : document;

        scope.querySelectorAll('.rx-field-library-dropdown').forEach(function (dropdown) {
            dropdown.querySelectorAll('.js-field-library-manual').forEach(function (element) {
                element.remove();
            });

            dropdown.querySelectorAll('a, button, p, small, span, div, li').forEach(function (element) {
                const text = normalizeText(element.textContent);
                const lowerText = text.toLowerCase();

                if (
                    lowerText.startsWith('keep manual value')
                    || lowerText === 'continue with manual entry'
                    || lowerText === 'use the text currently written in this field.'
                    || lowerText === 'open this library form'
                    || unwantedMedicineHelperText(text)
                ) {
                    element.remove();
                    return;
                }

                if (lowerText === 'my library') {
                    element.remove();
                    return;
                }

                if (
                    lowerText.includes('my library')
                    && element.children.length === 0
                ) {
                    const cleanedText = text
                        .replace(/\s*[·•|-]?\s*my library/ig, '')
                        .replace(/\s{2,}/g, ' ')
                        .trim();

                    if (cleanedText === '') {
                        element.remove();
                    } else {
                        element.textContent = cleanedText;
                    }
                }
            });
        });

        cleanMedicineHelperMessages(scope);
    };

    const ensureMedicineNameSearch = function (root) {
        const scope = root instanceof Element || root instanceof Document ? root : document;

        scope.querySelectorAll('.js-medicine-row').forEach(function (row) {
            const generic = row.querySelector('input[name="medicine_generic_name[]"]');
            const brand = row.querySelector('input[name="medicine_brand_name[]"]');

            [generic, brand].forEach(function (input) {
                if (!(input instanceof HTMLInputElement)) {
                    return;
                }

                input.classList.add('js-library-input');
                input.dataset.libraryType = 'medicine';
                input.placeholder = 'Search generic or brand name';
                input.setAttribute('autocomplete', 'off');
            });

            if (generic) {
                generic.dataset.libraryRole = 'both';
                generic.dataset.searchFields = 'generic_name,brand_name';
            }

            if (brand) {
                brand.dataset.libraryRole = 'both';
                brand.dataset.searchFields = 'generic_name,brand_name';
            }
        });
    };

    const createMedicineFormField = function () {
        const field = document.createElement('div');
        field.className = 'rx-medicine-input-wrap rx-library-field';
        field.innerHTML = `
            <label>Medicine Form</label>
            <div class="rx-library-input-control">
                <input
                    type="text"
                    name="medicine_form[]"
                    class="js-library-input"
                    data-library-type="medicine_form"
                    value=""
                    placeholder="Medicine form — Tab., Cap., Syr..."
                    autocomplete="off"
                >
                <button
                    type="button"
                    class="rx-library-open js-open-library"
                    aria-label="Open medicine forms library"
                    tabindex="-1"
                    aria-hidden="true"
                ></button>
            </div>
            <div class="rx-field-library-dropdown"></div>
        `;
        return field;
    };

    const ensureMedicineFormOnEveryRow = function (root) {
        const scope = root instanceof Element || root instanceof Document ? root : document;

        scope.querySelectorAll('.js-medicine-row').forEach(function (row) {
            const primaryFields = row.querySelector('.rx-editor-medicine-primary');

            if (!primaryFields) {
                return;
            }

            if (!primaryFields.querySelector('input[name="medicine_form[]"]')) {
                primaryFields.insertBefore(
                    createMedicineFormField(),
                    primaryFields.firstElementChild
                );
            }
        });
    };

    const openLibraryForInput = function (input) {
        if (!(input instanceof HTMLInputElement || input instanceof HTMLTextAreaElement)) {
            return;
        }

        if (!input.classList.contains('js-library-input')) {
            return;
        }

        const libraryField = input.closest('.rx-library-field');

        if (!libraryField) {
            return;
        }

        const dropdown = libraryField.querySelector('.rx-field-library-dropdown');
        const openButton = libraryField.querySelector('.js-open-library');

        if (
            openButton instanceof HTMLButtonElement
            && (!dropdown || dropdown.childElementCount === 0)
        ) {
            openButton.click();
        }
    };

    ensureMedicineFormOnEveryRow(editor);
    ensureMedicineNameSearch(editor);
    cleanDropdownMessages(editor);

    editor.addEventListener('focusin', function (event) {
        openLibraryForInput(event.target);

        window.setTimeout(function () {
            cleanDropdownMessages(editor);
        }, 0);
    });

    editor.addEventListener('input', function (event) {
        if (
            event.target instanceof HTMLInputElement
            && (
                event.target.name === 'medicine_generic_name[]'
                || event.target.name === 'medicine_brand_name[]'
            )
        ) {
            event.target.dataset.libraryType = 'medicine';
            event.target.dataset.libraryRole = 'both';
            event.target.dataset.searchFields = 'generic_name,brand_name';
        }

        window.setTimeout(function () {
            cleanDropdownMessages(editor);
        }, 260);
    });

    editor.addEventListener('click', function (event) {
        openLibraryForInput(event.target);

        window.setTimeout(function () {
            cleanDropdownMessages(editor);
        }, 0);
    });

    const medicineRowsContainer = document.getElementById('medicineRows');
    const addMedicineButton = document.getElementById('addMedicineRow');
    const medicineEditorMessage = document.getElementById('medicineEditorMessage');
    let medicineRowCountBeforeAdd = 0;
    let medicineAddWasApproved = false;
    let addMedicineButtonTopBeforeAdd = null;

    const medicineValueSelectors = [
        'input[name="medicine_form[]"]',
        'input[name="medicine_generic_name[]"]',
        'input[name="medicine_brand_name[]"]',
        'input[name="medicine_strength[]"]',
        'input[name="medicine_dosage[]"]',
        'input[name="medicine_frequency[]"]',
        'input[name="medicine_duration[]"]',
        'input[name="medicine_instruction[]"]'
    ];

    const getMedicineValue = function (row, fieldName) {
        const input = row.querySelector('input[name="' + fieldName + '[]"]');

        return input instanceof HTMLInputElement
            ? input.value.replace(/\s+/g, ' ').trim()
            : '';
    };

    const setTextIfChanged = function (element, value) {
        if (element && element.textContent !== value) {
            element.textContent = value;
        }
    };

    const ensureMedicineRowChrome = function (row) {
        if (!(row instanceof Element)) {
            return;
        }

        const fields = row.querySelector('.rx-editor-medicine-fields');

        if (!fields) {
            return;
        }

        let preview = fields.querySelector('.rx-medicine-prescription-preview');

        if (!preview) {
            preview = document.createElement('div');
            preview.className = 'rx-medicine-prescription-preview';
            preview.setAttribute('aria-live', 'polite');
            preview.innerHTML = `
                <p class="rx-medicine-preview-name"></p>
                <p class="rx-medicine-preview-direction"></p>
            `;
            fields.insertBefore(preview, fields.firstElementChild);
        }

        let cardFooter = fields.querySelector('.rx-medicine-card-footer');

        if (!cardFooter) {
            cardFooter = document.createElement('div');
            cardFooter.className = 'rx-medicine-card-footer';
            cardFooter.innerHTML = `
                <button
                    type="button"
                    class="rx-medicine-save-button js-save-medicine"
                >Save Medicine</button>
            `;
            fields.appendChild(cardFooter);
        }

        let actions = row.querySelector('.rx-medicine-row-actions');
        const directRemoveButton = Array.from(row.children).find(function (child) {
            return child.classList && child.classList.contains('js-remove-row');
        });

        if (!actions) {
            actions = document.createElement('div');
            actions.className = 'rx-medicine-row-actions';
            row.appendChild(actions);
        }

        let editButton = actions.querySelector('.js-edit-medicine');

        if (!editButton) {
            editButton = document.createElement('button');
            editButton.type = 'button';
            editButton.className = 'rx-medicine-edit-button js-edit-medicine';
            editButton.setAttribute('aria-label', 'Edit medicine');
            editButton.setAttribute('title', 'Edit medicine');
            editButton.setAttribute('aria-expanded', 'false');
            editButton.innerHTML = `
                <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <path fill="currentColor" d="M4 16.5V20h3.5L18.1 9.4l-3.5-3.5L4 16.5Zm16.7-9.9a1 1 0 0 0 0-1.4l-1.9-1.9a1 1 0 0 0-1.4 0l-1.5 1.5 3.5 3.5 1.3-1.7Z"/>
                </svg>
            `;
            actions.appendChild(editButton);
        }

        if (directRemoveButton) {
            directRemoveButton.setAttribute('title', 'Remove medicine');
            actions.appendChild(directRemoveButton);
        }
    };

    const syncMedicinePreview = function (row) {
        if (!(row instanceof Element)) {
            return;
        }

        ensureMedicineRowChrome(row);

        const form = getMedicineValue(row, 'medicine_form');
        const generic = getMedicineValue(row, 'medicine_generic_name');
        const brand = getMedicineValue(row, 'medicine_brand_name');
        const strength = getMedicineValue(row, 'medicine_strength');
        const dosage = getMedicineValue(row, 'medicine_dosage');
        const frequency = getMedicineValue(row, 'medicine_frequency');
        const duration = getMedicineValue(row, 'medicine_duration');
        const instruction = getMedicineValue(row, 'medicine_instruction');

        const displayName = generic || brand;
        const nameParts = [];

        if (form !== '') {
            nameParts.push(form);
        }

        if (displayName !== '') {
            nameParts.push(displayName);
        }

        if (generic !== '' && brand !== '') {
            nameParts.push('(' + brand + ')');
        }

        if (strength !== '') {
            nameParts.push(strength);
        }

        const direction = [dosage, frequency, duration, instruction]
            .filter(function (value) {
                return value !== '';
            })
            .join('  •  ');

        const nameElement = row.querySelector('.rx-medicine-preview-name');
        const directionElement = row.querySelector('.rx-medicine-preview-direction');

        setTextIfChanged(
            nameElement,
            nameParts.join(' ') || 'Medicine details not entered'
        );
        setTextIfChanged(directionElement, direction);
    };

    const collapseMedicineRow = function (row) {
        if (!(row instanceof Element)) {
            return;
        }

        syncMedicinePreview(row);
        row.classList.remove('is-editing');
        row.classList.add('is-preview');

        const editButton = row.querySelector('.js-edit-medicine');

        if (editButton) {
            editButton.setAttribute('aria-expanded', 'false');
        }

        row.querySelectorAll('.rx-field-library-dropdown').forEach(function (dropdown) {
            if (dropdown.childElementCount > 0) {
                dropdown.replaceChildren();
            }
        });
    };

    const collapseAllMedicineRows = function (exceptRow) {
        editor.querySelectorAll('.js-medicine-row').forEach(function (row) {
            if (row !== exceptRow) {
                collapseMedicineRow(row);
            }
        });
    };

    const openMedicineRow = function (row, shouldFocus) {
        if (!(row instanceof Element)) {
            return;
        }

        collapseAllMedicineRows(row);
        ensureMedicineRowChrome(row);
        syncMedicinePreview(row);
        row.classList.remove('is-preview');
        row.classList.add('is-editing');

        const editButton = row.querySelector('.js-edit-medicine');

        if (editButton) {
            editButton.setAttribute('aria-expanded', 'true');
        }

        if (shouldFocus) {
            const firstInput = row.querySelector(
                'input[name="medicine_form[]"], '
                + 'input[name="medicine_generic_name[]"], '
                + 'input[name="medicine_brand_name[]"]'
            );

            if (firstInput instanceof HTMLInputElement) {
                window.setTimeout(function () {
                    firstInput.focus({preventScroll: true});
                    row.scrollIntoView({behavior: 'smooth', block: 'nearest'});
                }, 40);
            }
        }
    };

    const renumberMedicineRows = function () {
        editor.querySelectorAll('.js-medicine-row').forEach(function (row, index) {
            const number = row.querySelector('.rx-editor-medicine-number');
            setTextIfChanged(number, String(index + 1) + '.');
        });
    };

    const prepareMedicineCardUI = function (root) {
        const scope = root instanceof Element || root instanceof Document
            ? root
            : document;

        scope.querySelectorAll('.js-medicine-row').forEach(function (row) {
            ensureMedicineRowChrome(row);
            syncMedicinePreview(row);

            if (
                !row.classList.contains('is-preview')
                && !row.classList.contains('is-editing')
            ) {
                collapseMedicineRow(row);
            }
        });

        renumberMedicineRows();
    };

    const initializeMedicineCardUI = function () {
        const rows = Array.from(editor.querySelectorAll('.js-medicine-row'));

        prepareMedicineCardUI(editor);

        if (rows.length === 0) {
            return;
        }

        const lastRow = rows[rows.length - 1];

        if (rows.length === 1 || !rowHasAnyMedicineValue(lastRow)) {
            openMedicineRow(lastRow, false);
            return;
        }

        collapseAllMedicineRows();
    };


    const normalizeMedicineName = function (value) {
        return String(value || '')
            .replace(/\s+/g, ' ')
            .trim()
            .toLocaleLowerCase();
    };

    const setMedicineMessage = function (message) {
        if (medicineEditorMessage) {
            medicineEditorMessage.textContent = message || '';
        }
    };

    const clearMedicineRowStates = function () {
        editor.querySelectorAll('.js-medicine-row').forEach(function (row) {
            row.classList.remove(
                'rx-medicine-row-invalid',
                'rx-medicine-row-duplicate'
            );
        });
    };

    const rowHasAnyMedicineValue = function (row) {
        if (!(row instanceof Element)) {
            return false;
        }

        return medicineValueSelectors.some(function (selector) {
            const input = row.querySelector(selector);

            return input instanceof HTMLInputElement
                && input.value.trim() !== '';
        });
    };

    const findDuplicateMedicineRows = function () {
        const rows = Array.from(editor.querySelectorAll('.js-medicine-row'));
        const seen = new Map();
        const duplicateIndexes = new Set();

        rows.forEach(function (row, rowIndex) {
            const generic = row.querySelector(
                'input[name="medicine_generic_name[]"]'
            );
            const brand = row.querySelector(
                'input[name="medicine_brand_name[]"]'
            );

            [generic, brand].forEach(function (input) {
                if (!(input instanceof HTMLInputElement)) {
                    return;
                }

                const normalized = normalizeMedicineName(input.value);

                if (normalized === '') {
                    return;
                }

                if (
                    seen.has(normalized)
                    && seen.get(normalized) !== rowIndex
                ) {
                    duplicateIndexes.add(rowIndex);
                    duplicateIndexes.add(seen.get(normalized));
                    return;
                }

                seen.set(normalized, rowIndex);
            });
        });

        return {
            rows: rows,
            indexes: Array.from(duplicateIndexes)
        };
    };

    const validateMedicineDuplicates = function () {
        clearMedicineRowStates();

        const duplicateResult = findDuplicateMedicineRows();

        if (duplicateResult.indexes.length === 0) {
            return true;
        }

        duplicateResult.indexes.forEach(function (index) {
            const row = duplicateResult.rows[index];

            if (row) {
                row.classList.add('rx-medicine-row-duplicate');
            }
        });

        setMedicineMessage(
            'The same Generic Name or Brand Name cannot be added twice.'
        );

        const firstDuplicate = duplicateResult.rows[
            duplicateResult.indexes[0]
        ];

        if (firstDuplicate) {
            firstDuplicate.scrollIntoView({
                behavior: 'smooth',
                block: 'nearest'
            });
        }

        return false;
    };

    const keepAddMedicineButtonInView = function (previousTop) {
        if (!addMedicineButton || typeof previousTop !== 'number') {
            return;
        }

        const currentTop = addMedicineButton.getBoundingClientRect().top;
        const movement = currentTop - previousTop;

        if (Math.abs(movement) > 1) {
            window.scrollBy({
                top: movement,
                left: 0,
                behavior: 'smooth'
            });
        }
    };

    const prepareMedicineRows = function (root) {
        ensureMedicineFormOnEveryRow(root);
        ensureMedicineNameSearch(root);

        const scope = root instanceof Element || root instanceof Document
            ? root
            : document;

        const fieldSettings = [
            ['medicine_form[]', 'Medicine form — Tab., Cap., Syr...'],
            ['medicine_generic_name[]', 'Generic name — search generic or brand'],
            ['medicine_brand_name[]', 'Brand name (optional) — search generic or brand'],
            ['medicine_strength[]', 'Strength — e.g. 500 mg'],
            ['medicine_dosage[]', 'Dosage — e.g. 1 tablet'],
            ['medicine_frequency[]', 'Frequency — e.g. 1+0+1'],
            ['medicine_duration[]', 'Duration — e.g. 5 days'],
            ['medicine_instruction[]', 'Instruction — e.g. after meal']
        ];

        scope.querySelectorAll('.js-medicine-row').forEach(function (row) {
            fieldSettings.forEach(function (setting) {
                const input = row.querySelector(
                    'input[name="' + setting[0] + '"]'
                );

                if (!(input instanceof HTMLInputElement)) {
                    return;
                }

                input.placeholder = setting[1];
                input.setAttribute(
                    'aria-label',
                    setting[1].split('—')[0].trim()
                );
            });
        });
    };

    prepareMedicineRows(editor);
    initializeMedicineCardUI();
    cleanMedicineHelperMessages(editor);

    if (addMedicineButton) {
        addMedicineButton.addEventListener('click', function (event) {
            clearMedicineRowStates();
            setMedicineMessage('');

            const rows = Array.from(
                editor.querySelectorAll('.js-medicine-row')
            );
            const editingRow = editor.querySelector(
                '.js-medicine-row.is-editing'
            );
            const validationRow = editingRow || rows[rows.length - 1];

            if (validationRow && !rowHasAnyMedicineValue(validationRow)) {
                event.preventDefault();
                event.stopImmediatePropagation();

                validationRow.classList.add('rx-medicine-row-invalid');
                setMedicineMessage(
                    'Enter a value in at least one medicine field before adding another medicine.'
                );

                const firstInput = validationRow.querySelector(
                    'input[name="medicine_form[]"], '
                    + 'input[name="medicine_generic_name[]"], '
                    + 'input[name="medicine_brand_name[]"]'
                );

                if (firstInput instanceof HTMLInputElement) {
                    firstInput.focus({preventScroll: true});
                }

                return;
            }

            if (!validateMedicineDuplicates()) {
                event.preventDefault();
                event.stopImmediatePropagation();
                return;
            }

            collapseAllMedicineRows();
            medicineRowCountBeforeAdd = rows.length;
            addMedicineButtonTopBeforeAdd = addMedicineButton.getBoundingClientRect().top;
            medicineAddWasApproved = true;
        }, true);
    }

    editor.addEventListener('input', function (event) {
        const input = event.target;

        if (!(input instanceof HTMLInputElement)) {
            return;
        }

        const row = input.closest('.js-medicine-row');

        if (row) {
            row.classList.remove(
                'rx-medicine-row-invalid',
                'rx-medicine-row-duplicate'
            );
            syncMedicinePreview(row);
        }

        setMedicineMessage('');
    });


    editor.addEventListener('click', function (event) {
        const saveButton = event.target.closest('.js-save-medicine');

        if (!saveButton) {
            return;
        }

        const row = saveButton.closest('.js-medicine-row');

        if (!row) {
            return;
        }

        event.preventDefault();
        event.stopPropagation();
        clearMedicineRowStates();
        setMedicineMessage('');

        if (!rowHasAnyMedicineValue(row)) {
            row.classList.add('rx-medicine-row-invalid');
            setMedicineMessage(
                'Enter a value in at least one medicine field before saving.'
            );

            const firstInput = row.querySelector(
                'input[name="medicine_form[]"], '
                + 'input[name="medicine_generic_name[]"], '
                + 'input[name="medicine_brand_name[]"]'
            );

            if (firstInput instanceof HTMLInputElement) {
                firstInput.focus({preventScroll: true});
            }

            return;
        }

        if (!validateMedicineDuplicates()) {
            return;
        }

        syncMedicinePreview(row);
        collapseMedicineRow(row);
        setMedicineMessage('Medicine saved.');

        if (addMedicineButton instanceof HTMLButtonElement) {
            addMedicineButton.focus({preventScroll: true});
        }

        window.setTimeout(function () {
            if (
                medicineEditorMessage
                && medicineEditorMessage.textContent === 'Medicine saved.'
            ) {
                setMedicineMessage('');
            }
        }, 1800);
    });

    editor.addEventListener('click', function (event) {
        const editButton = event.target.closest('.js-edit-medicine');

        if (!editButton) {
            return;
        }

        const row = editButton.closest('.js-medicine-row');

        if (!row) {
            return;
        }

        event.preventDefault();
        event.stopPropagation();
        clearMedicineRowStates();
        setMedicineMessage('');
        openMedicineRow(row, true);
    });

    editor.addEventListener('submit', function (event) {
        if (!validateMedicineDuplicates()) {
            event.preventDefault();
            event.stopImmediatePropagation();
        }
    }, true);

    const observer = new MutationObserver(function () {
        prepareMedicineRows(editor);
        prepareMedicineCardUI(editor);
        cleanDropdownMessages(editor);
        cleanMedicineHelperMessages(editor);

        const rows = Array.from(
            editor.querySelectorAll('.js-medicine-row')
        );

        if (
            medicineAddWasApproved
            && rows.length > medicineRowCountBeforeAdd
        ) {
            const newestRow = rows[rows.length - 1];

            if (newestRow) {
                openMedicineRow(newestRow, false);
                newestRow.classList.add('rx-medicine-row-enter');

                window.requestAnimationFrame(function () {
                    newestRow.classList.remove('rx-medicine-row-enter');
                });
            }

            window.requestAnimationFrame(function () {
                window.requestAnimationFrame(function () {
                    keepAddMedicineButtonInView(
                        addMedicineButtonTopBeforeAdd
                    );
                });
            });

            medicineAddWasApproved = false;
            medicineRowCountBeforeAdd = rows.length;
            addMedicineButtonTopBeforeAdd = null;

            if (newestRow) {
                const firstInput = newestRow.querySelector(
                    'input[name="medicine_form[]"], '
                    + 'input[name="medicine_generic_name[]"]'
                );

                if (firstInput instanceof HTMLInputElement) {
                    window.setTimeout(function () {
                        firstInput.focus({preventScroll: true});
                    }, 260);
                }
            }
        }
    });

    observer.observe(editor, {
        childList: true,
        subtree: true
    });
});
</script>


<?php require_once __DIR__ . '/includes/footer.php'; ?>
