<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$prescription_id = (int)($_GET['id'] ?? 0);
$record = prescription_get_record($pdo, $prescription_id, $doctor_id);

if (!$record) {
    http_response_code(404);
    exit('Prescription was not found or access was denied.');
}

$doctor = prescription_get_doctor($pdo, $doctor_id);
$patient = !empty($record['patient_id'])
    ? prescription_get_patient($pdo, (int)$record['patient_id'], $doctor_id)
    : null;
$medicines = prescription_get_medicines($pdo, $prescription_id);
$tests = prescription_get_tests($pdo, $prescription_id);

/**
 * Return the first non-empty value from a list of possible doctor fields.
 */
function prescription_print_value(array $source, array $keys, string $default = ''): string
{
    foreach ($keys as $key) {
        $value = trim((string)($source[$key] ?? ''));

        if ($value !== '') {
            return $value;
        }
    }

    return $default;
}

/**
 * Split a multiline or comma-separated value into clean display lines.
 */
function prescription_print_lines(string $value): array
{
    $value = trim($value);

    if ($value === '') {
        return [];
    }

    $value = str_replace(["\r\n", "\r"], "\n", $value);
    $parts = preg_split('/\n+|\s*;\s*/u', $value) ?: [];
    $lines = [];

    foreach ($parts as $part) {
        $part = trim((string)$part);

        if ($part !== '') {
            $lines[] = $part;
        }
    }

    return $lines;
}

/**
 * Format a stored date for the printed prescription.
 */
function prescription_print_date($date): string
{
    $date = trim((string)$date);

    if ($date === '') {
        return '—';
    }

    try {
        return (new DateTimeImmutable($date))->format('d/m/Y');
    } catch (Throwable $e) {
        return $date;
    }
}

$doctor_name = prescription_print_value($doctor, ['name', 'name_en'], 'Doctor');
$doctor_name_bn = prescription_print_value($doctor, ['name_bn']);
$doctor_degree = prescription_print_value($doctor, ['degree', 'degree_en']);
$doctor_degree_bn = prescription_print_value($doctor, ['degree_bn']);
$doctor_designation = prescription_print_value($doctor, ['designation', 'designation_en']);
$doctor_designation_bn = prescription_print_value($doctor, ['designation_bn']);
$doctor_training = prescription_print_value($doctor, ['training', 'training_en']);
$doctor_training_bn = prescription_print_value($doctor, ['training_bn']);
$doctor_fellowship = prescription_print_value($doctor, ['fellowship', 'fellowship_en']);
$doctor_fellowship_bn = prescription_print_value($doctor, ['fellowship_bn']);
$doctor_hospital = prescription_print_value($doctor, ['primary_hospital', 'hospital_name']);
$doctor_hospital_bn = prescription_print_value($doctor, ['primary_hospital_bn']);
$doctor_bmdc = prescription_print_value($doctor, ['bmdc_number']);
$doctor_phone = prescription_print_value($doctor, ['phone', 'serial_no']);
$doctor_serial = prescription_print_value($doctor, ['serial_no', 'phone']);
$appointment_note = prescription_print_value($doctor, ['appointment_note']);
$appointment_note_bn = prescription_print_value($doctor, ['appointment_note_bn']);

$patient_code = trim((string)($patient['patient_code'] ?? ''));
$visit_number = str_pad((string)$prescription_id, 4, '0', STR_PAD_LEFT);
$patient_name = trim((string)($record['patient_name'] ?? ''));
$patient_age = trim((string)($record['patient_age'] ?? ''));
$patient_gender = trim((string)($record['patient_gender'] ?? ''));
$patient_weight = trim((string)($record['weight'] ?? ''));
$patient_address = trim((string)($record['patient_address'] ?? ''));

$english_header_lines = array_values(array_filter([
    $doctor_degree,
    $doctor_training,
    $doctor_fellowship,
    $doctor_designation,
    $doctor_hospital,
]));

$bangla_header_lines = array_values(array_filter([
    $doctor_degree_bn,
    $doctor_training_bn,
    $doctor_fellowship_bn,
    $doctor_designation_bn,
    $doctor_hospital_bn,
]));

$clinical_blocks = [
    'C/C:' => trim((string)($record['chief_complaints'] ?? '')),
    'Dx:' => trim((string)($record['diagnosis'] ?? '')),
    'D/H:' => trim((string)($record['medical_history'] ?? '')),
    'O/E:' => trim((string)($record['examination'] ?? '')),
];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= prescription_e($record['prescription_no'] ?? 'Prescription') ?></title>
    <link rel="stylesheet" href="<?= prescription_e(prescription_url('assets/css/prescription-print.css')) ?>">
    <script src="<?= prescription_e(prescription_url('assets/js/prescription-print.js')) ?>" defer></script>
</head>
<body>
<div class="print-toolbar">
    <a href="<?= prescription_e(prescription_url('view.php?id=' . $prescription_id)) ?>">Back</a>
    <button type="button" data-print-trigger>Print Prescription</button>
</div>

<article class="rx-sheet">
    <header class="letterhead">
        <section class="doctor-en">
            <h1 class="doctor-name"><?= prescription_e($doctor_name) ?></h1>

            <?php foreach ($english_header_lines as $index => $line): ?>
                <p class="doctor-line <?= $index === 1 ? 'highlight' : '' ?>"><?= prescription_e($line) ?></p>
            <?php endforeach; ?>

            <?php if ($doctor_bmdc !== ''): ?>
                <p class="doctor-line">BMDC: <?= prescription_e($doctor_bmdc) ?></p>
            <?php endif; ?>

            <?php if ($doctor_phone !== ''): ?>
                <p class="doctor-contact">Cell: <?= prescription_e($doctor_phone) ?></p>
            <?php endif; ?>
        </section>

        <section class="doctor-bn" lang="bn">
            <?php if ($doctor_name_bn !== ''): ?>
                <h2 class="doctor-name-bn"><?= prescription_e($doctor_name_bn) ?></h2>
            <?php else: ?>
                <h2 class="doctor-name-bn"><?= prescription_e($doctor_name) ?></h2>
            <?php endif; ?>

            <?php foreach ($bangla_header_lines as $index => $line): ?>
                <p class="doctor-line <?= $index === 1 ? 'bn-highlight' : '' ?>"><?= prescription_e($line) ?></p>
            <?php endforeach; ?>

            <?php if ($doctor_bmdc !== ''): ?>
                <p class="doctor-line">বিএমডিসি: <?= prescription_e($doctor_bmdc) ?></p>
            <?php endif; ?>

            <?php if ($doctor_phone !== ''): ?>
                <p class="doctor-contact">মোবাইল: <?= prescription_e($doctor_phone) ?></p>
            <?php endif; ?>
        </section>
    </header>

    <section class="patient-strip">
        <div class="patient-main">
            <div>
                <strong><?= prescription_e($patient_name !== '' ? $patient_name : 'Patient') ?></strong>
                <?php if ($patient_age !== ''): ?>, <?= prescription_e($patient_age) ?><?php endif; ?>
                <?php if ($patient_gender !== ''): ?>, <?= prescription_e($patient_gender) ?><?php endif; ?>
                <?php if ($patient_weight !== ''): ?>, <?= prescription_e($patient_weight) ?><?php endif; ?>
            </div>

            <?php if ($patient_address !== ''): ?>
                <div>Address: <?= prescription_e($patient_address) ?></div>
            <?php endif; ?>
        </div>

        <div class="patient-meta">
            <span>Date</span><span>: <?= prescription_e(prescription_print_date($record['visit_date'] ?? '')) ?></span>
            <span>Patient ID</span><span>: <?= prescription_e($patient_code !== '' ? $patient_code : (!empty($record['patient_id']) ? 'PT-' . $record['patient_id'] : '—')) ?></span>
            <span>Visit No</span><span>: <?= prescription_e($visit_number) ?></span>
            <span>Rx No</span><span>: <?= prescription_e($record['prescription_no'] ?? '') ?></span>
        </div>
    </section>

    <section class="main-body">
        <aside class="clinical-column">
            <?php
            $vitals = [
                'BP' => trim((string)($record['blood_pressure'] ?? '')),
                'Pulse' => trim((string)($record['pulse'] ?? '')),
                'Temp.' => trim((string)($record['temperature'] ?? '')),
                'SpO₂' => trim((string)($record['spo2'] ?? '')),
                'Height' => trim((string)($record['height'] ?? '')),
                'Weight' => trim((string)($record['weight'] ?? '')),
            ];
            $visible_vitals = array_filter($vitals, static fn($value) => $value !== '');
            ?>

            <?php if ($visible_vitals): ?>
                <div class="vitals-mini">
                    <?php foreach ($visible_vitals as $label => $value): ?>
                        <div><span><?= prescription_e($label) ?></span><strong><?= prescription_e($value) ?></strong></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php foreach ($clinical_blocks as $label => $value): ?>
                <?php if ($value !== ''): ?>
                    <div class="clinical-block">
                        <h3 class="clinical-title"><?= prescription_e($label) ?></h3>
                        <p class="clinical-text"><?= prescription_e($value) ?></p>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>

            <?php if ($tests): ?>
                <div class="clinical-block">
                    <h3 class="clinical-title">Investigations:</h3>
                    <?php foreach ($tests as $test): ?>
                        <p class="test-line">
                            <?= prescription_e($test['test_name'] ?? '') ?>
                            <?= !empty($test['instruction']) ? ' — ' . prescription_e($test['instruction']) : '' ?>
                        </p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </aside>

        <section class="medicine-column">
            <div class="rx-symbol">℞</div>

            <?php if (!$medicines): ?>
                <p class="empty-value">No medicine prescribed.</p>
            <?php else: ?>
                <ol class="medicine-list">
                    <?php foreach ($medicines as $index => $medicine): ?>
                        <?php
                        $medicine_name = trim((string)(
                            $medicine['generic_name']
                            ?? $medicine['medicine_name']
                            ?? ''
                        ));
                        $brand_name = trim((string)($medicine['brand_name'] ?? ''));
                        $medicine_form = trim((string)($medicine['medicine_form'] ?? ''));
                        $strength = trim((string)($medicine['strength'] ?? ''));
                        $direction = implode('  •  ', array_filter([
                            trim((string)($medicine['dosage'] ?? '')),
                            trim((string)($medicine['frequency'] ?? '')),
                            trim((string)($medicine['duration'] ?? '')),
                            trim((string)($medicine['instruction'] ?? '')),
                        ]));
                        ?>
                        <li class="medicine-item">
                            <span class="medicine-number"><?= $index + 1 ?>.</span>
                            <div>
                                <p class="medicine-name">
                                    <?= $medicine_form !== '' ? prescription_e($medicine_form) . ' ' : '' ?><?= prescription_e($medicine_name) ?>
                                    <?= $brand_name !== '' ? ' (' . prescription_e($brand_name) . ')' : '' ?>
                                    <?= $strength !== '' ? ' ' . prescription_e($strength) : '' ?>
                                </p>
                                <?php if ($direction !== ''): ?>
                                    <p class="medicine-direction"><?= prescription_e($direction) ?></p>
                                <?php endif; ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ol>
            <?php endif; ?>
        </section>
    </section>

    <section class="bottom-area">
        <div class="advice-box">
            <h3 class="advice-title">Advice / উপদেশ:</h3>
            <p class="advice-text"><?= prescription_e(trim((string)($record['advice'] ?? '')) ?: '—') ?></p>

            <?php if (!empty($record['follow_up_date'])): ?>
                <div class="follow-up">Next follow-up: <?= prescription_e(prescription_print_date($record['follow_up_date'])) ?></div>
            <?php endif; ?>
        </div>

        <div class="signature-box">
            <div class="signature-space"></div>
            <div class="signature-name"><?= prescription_e($doctor_name) ?></div>
            <div>Doctor Signature</div>

            <?php if ($doctor_serial !== ''): ?>
                <div class="serial-note">For serial: <?= prescription_e($doctor_serial) ?></div>
            <?php endif; ?>

            <?php if ($appointment_note !== '' || $appointment_note_bn !== ''): ?>
                <div class="appointment-note">
                    <?= prescription_e($appointment_note_bn !== '' ? $appointment_note_bn : $appointment_note) ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <footer class="clinic-footer">
        <div>
            <?php if ($doctor_hospital !== ''): ?>
                <strong><?= prescription_e($doctor_hospital) ?></strong><br>
            <?php endif; ?>
            <?= $doctor_phone !== '' ? 'Contact: ' . prescription_e($doctor_phone) : '' ?>
        </div>

        <div class="right" lang="bn">
            <?php if ($doctor_hospital_bn !== ''): ?>
                <strong><?= prescription_e($doctor_hospital_bn) ?></strong><br>
            <?php endif; ?>
            <?= $doctor_phone !== '' ? 'যোগাযোগ: ' . prescription_e($doctor_phone) : '' ?>
        </div>
    </footer>
</article>
</body>
</html>
