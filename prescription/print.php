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
    <style>
        @page {
            size: A4 portrait;
            margin: 0;
        }

        :root {
            --rx-purple: #32178d;
            --rx-teal: #008b8b;
            --rx-green: #118133;
            --rx-red: #d92828;
            --rx-ink: #101010;
            --rx-muted: #4b5563;
            --rx-line: #d7dde4;
            --rx-soft: #f7fafb;
        }

        * {
            box-sizing: border-box;
        }

        html,
        body {
            margin: 0;
            padding: 0;
        }

        body {
            color: var(--rx-ink);
            background: #e8edf1;
            font-family: Arial, "Noto Sans Bengali", "SolaimanLipi", sans-serif;
            font-size: 13px;
        }

        .print-toolbar {
            width: 210mm;
            max-width: calc(100% - 24px);
            display: flex;
            justify-content: flex-end;
            gap: 9px;
            margin: 14px auto;
        }

        .print-toolbar a,
        .print-toolbar button {
            min-height: 40px;
            padding: 9px 16px;
            border: 0;
            border-radius: 8px;
            color: #fff;
            background: #0f766e;
            text-decoration: none;
            font: inherit;
            font-weight: 700;
            cursor: pointer;
        }

        .print-toolbar a {
            background: #475467;
        }

        .rx-sheet {
            position: relative;
            width: 210mm;
            min-height: 297mm;
            margin: 0 auto 24px;
            padding: 7mm 9mm 6mm;
            overflow: hidden;
            background: #fff;
            box-shadow: 0 24px 70px rgba(15, 23, 42, .16);
        }

        .rx-sheet::after {
            content: "";
            position: absolute;
            left: 0;
            right: 0;
            bottom: 0;
            height: 3mm;
            background: linear-gradient(90deg, var(--rx-purple), var(--rx-teal));
        }

        .letterhead {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
            gap: 14mm;
            min-height: 49mm;
            padding-bottom: 4mm;
        }

        .doctor-en,
        .doctor-bn {
            min-width: 0;
        }

        .doctor-bn {
            text-align: right;
            direction: ltr;
        }

        .doctor-name {
            margin: 0 0 2mm;
            color: var(--rx-purple);
            font-size: 18px;
            font-weight: 800;
            line-height: 1.1;
        }

        .doctor-name-bn {
            margin: 0 0 2mm;
            color: var(--rx-teal);
            font-size: 17px;
            font-weight: 800;
            line-height: 1.35;
        }

        .doctor-line {
            margin: 0 0 1.2mm;
            font-size: 12.2px;
            line-height: 1.38;
        }

        .doctor-line.highlight {
            color: var(--rx-red);
        }

        .doctor-line.bn-highlight {
            color: var(--rx-teal);
            font-weight: 700;
        }

        .doctor-contact {
            margin-top: 2mm;
            color: var(--rx-green);
            font-size: 12px;
            font-weight: 800;
        }

        .patient-strip {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 42mm;
            gap: 7mm;
            align-items: start;
            margin-top: 1mm;
            padding: 3.2mm 1mm 3mm;
            border-top: 1px solid transparent;
            border-bottom: 1px dotted #d4d4d4;
        }

        .patient-main {
            font-size: 13px;
            line-height: 1.7;
        }

        .patient-main strong {
            font-size: 14px;
        }

        .patient-meta {
            display: grid;
            grid-template-columns: auto 1fr;
            gap: 1.2mm 2.3mm;
            font-size: 10.5px;
            line-height: 1.4;
        }

        .patient-meta span:nth-child(odd) {
            font-weight: 700;
        }

        .main-body {
            position: relative;
            display: grid;
            grid-template-columns: 34% 66%;
            min-height: 174mm;
            padding-top: 4mm;
        }

        .main-body::before {
            content: "Rx";
            position: absolute;
            left: 45%;
            top: 55mm;
            color: rgba(50, 23, 141, .025);
            font-family: Georgia, serif;
            font-size: 92px;
            font-style: italic;
            transform: rotate(-10deg);
            pointer-events: none;
        }

        .clinical-column {
            min-width: 0;
            padding: 0 6mm 4mm 1mm;
            border-right: 1px solid var(--rx-line);
        }

        .medicine-column {
            min-width: 0;
            padding: 0 1mm 4mm 7mm;
        }

        .clinical-block {
            margin-bottom: 4mm;
            page-break-inside: avoid;
        }

        .clinical-title {
            display: inline-block;
            margin: 0 0 1.2mm;
            padding-bottom: .4mm;
            border-bottom: 1px solid currentColor;
            font-size: 12px;
            font-weight: 800;
        }

        .clinical-text,
        .test-line,
        .advice-text {
            margin: 0;
            white-space: pre-wrap;
            overflow-wrap: anywhere;
            font-size: 11.3px;
            line-height: 1.58;
        }

        .vitals-mini {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 1.8mm 3mm;
            margin-bottom: 4mm;
            padding: 2.5mm;
            border: 1px solid #e4e8ec;
            border-radius: 2mm;
            background: var(--rx-soft);
            font-size: 9.5px;
        }

        .vitals-mini strong {
            display: block;
            margin-top: .4mm;
            font-size: 10px;
        }

        .rx-symbol {
            margin: -1mm 0 2mm -3mm;
            color: #111;
            font-family: Georgia, "Times New Roman", serif;
            font-size: 33px;
            font-style: italic;
            line-height: 1;
        }

        .medicine-list {
            margin: 0;
            padding: 0;
            list-style: none;
        }

        .medicine-item {
            display: grid;
            grid-template-columns: 7mm minmax(0, 1fr);
            gap: 1mm;
            margin-bottom: 3.6mm;
            page-break-inside: avoid;
        }

        .medicine-number {
            padding-top: .2mm;
            text-align: right;
            font-size: 12px;
        }

        .medicine-name {
            margin: 0;
            font-size: 12.2px;
            font-weight: 700;
            line-height: 1.35;
        }

        .medicine-direction {
            margin: 1mm 0 0;
            padding-left: 4mm;
            white-space: pre-wrap;
            overflow-wrap: anywhere;
            font-size: 10.8px;
            line-height: 1.5;
        }

        .medicine-direction::before {
            content: "↳ ";
        }

        .bottom-area {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 72mm;
            gap: 8mm;
            align-items: end;
            min-height: 42mm;
            padding: 3mm 1mm 5mm;
            border-top: 1px solid transparent;
        }

        .advice-box {
            align-self: stretch;
        }

        .advice-title {
            display: inline-block;
            margin: 0 0 1.4mm;
            padding-bottom: .4mm;
            border-bottom: 1px solid currentColor;
            font-size: 11.5px;
            font-weight: 800;
        }

        .follow-up {
            margin-top: 3mm;
            color: var(--rx-red);
            font-size: 11px;
            font-weight: 700;
        }

        .signature-box {
            text-align: center;
            font-size: 10.5px;
        }

        .signature-space {
            height: 18mm;
            margin-bottom: 1mm;
            border-bottom: 1px dotted #333;
        }

        .signature-name {
            font-weight: 800;
        }

        .serial-note {
            margin-top: 4mm;
            color: var(--rx-red);
            font-size: 11px;
            font-weight: 800;
            line-height: 1.5;
        }

        .appointment-note {
            margin-top: 1.2mm;
            color: #374151;
            font-size: 10px;
            line-height: 1.45;
        }

        .clinic-footer {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8mm;
            padding: 2mm 1mm 1mm;
            color: var(--rx-green);
            font-size: 10px;
            line-height: 1.5;
        }

        .clinic-footer .right {
            text-align: right;
        }

        .empty-value {
            color: #9ca3af;
        }

        @media print {
            body {
                background: #fff;
            }

            .print-toolbar {
                display: none !important;
            }

            .rx-sheet {
                width: 210mm;
                min-height: 297mm;
                margin: 0;
                box-shadow: none;
                print-color-adjust: exact;
                -webkit-print-color-adjust: exact;
            }
        }

        @media screen and (max-width: 900px) {
            .rx-sheet {
                width: calc(100% - 16px);
                min-height: auto;
                padding: 18px;
            }

            .letterhead,
            .patient-strip,
            .bottom-area,
            .clinic-footer {
                grid-template-columns: 1fr;
                gap: 14px;
            }

            .doctor-bn,
            .clinic-footer .right {
                text-align: left;
                direction: ltr;
            }

            .main-body {
                grid-template-columns: 1fr;
                min-height: auto;
            }

            .clinical-column {
                padding-right: 0;
                border-right: 0;
                border-bottom: 1px solid var(--rx-line);
            }

            .medicine-column {
                padding: 18px 0 0;
            }
        }
    </style>
</head>
<body>
<div class="print-toolbar">
    <a href="<?= prescription_e(prescription_url('view.php?id=' . $prescription_id)) ?>">Back</a>
    <button type="button" onclick="window.print()">Print Prescription</button>
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
