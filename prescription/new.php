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

<link rel="stylesheet" href="<?= prescription_e(prescription_url('assets/css/new-prescription.css')) ?>">

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
                    <label for="patientName">Patient</label>
                    <input type="text" id="patientName" name="patient_name" value="<?= prescription_e(rx_form_value('patient_name')) ?>" required placeholder="Type name to auto-find" autocomplete="off">
                </div>
                <div class="rx-paper-inline">
                    <label for="patientAge">Age</label>
                    <input type="text" id="patientAge" name="patient_age" value="<?= prescription_e(rx_form_value('patient_age')) ?>" placeholder="35 years">
                </div>
                <div class="rx-paper-inline">
                    <label for="patientGender">Gender</label>
                    <?php $gender = rx_form_value('patient_gender'); ?>
                    <select id="patientGender" name="patient_gender">
                        <option value="">—</option>
                        <option value="Male" <?= $gender === 'Male' ? 'selected' : '' ?>>Male</option>
                        <option value="Female" <?= $gender === 'Female' ? 'selected' : '' ?>>Female</option>
                        <option value="Other" <?= $gender === 'Other' ? 'selected' : '' ?>>Other</option>
                    </select>
                </div>
                <div class="rx-paper-inline">
                    <label for="patientWeight">Weight</label>
                    <input type="text" id="patientWeight" name="weight" value="<?= prescription_e(rx_form_value('weight')) ?>" placeholder="70 kg">
                </div>
                <div class="rx-paper-inline rx-paper-inline-address">
                    <label for="patientAddress">Address</label>
                    <input type="text" id="patientAddress" name="patient_address" value="<?= prescription_e(rx_form_value('patient_address')) ?>" placeholder="Patient address">
                </div>
            </div>

            <div class="rx-editor-patient-meta">
                <label>
                    <span>Date</span>
                    <input type="date" name="visit_date" value="<?= prescription_e(rx_form_value('visit_date', date('Y-m-d'))) ?>" required>
                </label>
                <label>
                    <span>Phone</span>
                    <input type="tel" id="patientPhone" name="patient_phone" value="<?= prescription_e(rx_form_value('patient_phone')) ?>" placeholder="Type mobile to auto-find" autocomplete="off">
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
                                        <label for="medForm-<?= prescription_e((string)$index) ?>">Medicine Form</label>
                                        <div class="rx-library-input-control">
                                            <input
                                                type="text"
                                                id="medForm-<?= prescription_e((string)$index) ?>"
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
                                        <label for="medGeneric-<?= prescription_e((string)$index) ?>">Generic Name</label>
                                        <div class="rx-library-input-control">
                                            <input
                                                type="text"
                                                id="medGeneric-<?= prescription_e((string)$index) ?>"
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
                                        <label for="medBrand-<?= prescription_e((string)$index) ?>">Brand Name <small>(optional)</small></label>
                                        <div class="rx-library-input-control">
                                            <input
                                                type="text"
                                                id="medBrand-<?= prescription_e((string)$index) ?>"
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
                                        <label for="medStrength-<?= prescription_e((string)$index) ?>">Strength</label>
                                        <div class="rx-library-input-control">
                                            <input type="text" id="medStrength-<?= prescription_e((string)$index) ?>" name="medicine_strength[]" class="js-library-input" data-library-type="strength" value="<?= prescription_e($medicine['strength'] ?? '') ?>" placeholder="Strength — e.g. 500 mg" autocomplete="off">
                                            <button type="button" class="rx-library-open js-open-library" aria-label="Open strength library"></button>
                                        </div>
                                        <div class="rx-field-library-dropdown"></div>
                                    </div>
                                </div>

                                <div class="rx-editor-medicine-secondary">
                                    <div class="rx-library-field">
                                        <label for="medDosage-<?= prescription_e((string)$index) ?>">Dosage</label>
                                        <div class="rx-library-input-control">
                                            <input type="text" id="medDosage-<?= prescription_e((string)$index) ?>" name="medicine_dosage[]" class="js-library-input" data-library-type="dosage" value="<?= prescription_e($medicine['dosage'] ?? '') ?>" placeholder="Dosage — e.g. 1 tablet" autocomplete="off">
                                            <button type="button" class="rx-library-open js-open-library" aria-label="Open dosage library"></button>
                                        </div>
                                        <div class="rx-field-library-dropdown"></div>
                                    </div>
                                    <div class="rx-library-field">
                                        <label for="medFrequency-<?= prescription_e((string)$index) ?>">Frequency</label>
                                        <div class="rx-library-input-control">
                                            <input type="text" id="medFrequency-<?= prescription_e((string)$index) ?>" name="medicine_frequency[]" class="js-library-input" data-library-type="frequency" value="<?= prescription_e($medicine['frequency'] ?? '') ?>" placeholder="Frequency — e.g. 1+0+1" autocomplete="off">
                                            <button type="button" class="rx-library-open js-open-library" aria-label="Open frequency library"></button>
                                        </div>
                                        <div class="rx-field-library-dropdown"></div>
                                    </div>
                                    <div class="rx-library-field">
                                        <label for="medDuration-<?= prescription_e((string)$index) ?>">Duration</label>
                                        <div class="rx-library-input-control">
                                            <input type="text" id="medDuration-<?= prescription_e((string)$index) ?>" name="medicine_duration[]" class="js-library-input" data-library-type="duration" value="<?= prescription_e($medicine['duration'] ?? '') ?>" placeholder="Duration — e.g. 5 days" autocomplete="off">
                                            <button type="button" class="rx-library-open js-open-library" aria-label="Open duration library"></button>
                                        </div>
                                        <div class="rx-field-library-dropdown"></div>
                                    </div>
                                    <div class="rx-library-field">
                                        <label for="medInstruction-<?= prescription_e((string)$index) ?>">Instruction</label>
                                        <div class="rx-library-input-control">
                                            <input type="text" id="medInstruction-<?= prescription_e((string)$index) ?>" name="medicine_instruction[]" class="js-library-input" data-library-type="instruction" value="<?= prescription_e($medicine['instruction'] ?? '') ?>" placeholder="Instruction — e.g. after meal" autocomplete="off">
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

<script src="<?= prescription_e(prescription_url('assets/js/new-prescription.js')) ?>"></script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
