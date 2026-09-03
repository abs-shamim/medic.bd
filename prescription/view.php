<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$prescription_id = (int)($_GET['id'] ?? 0);
$record = prescription_get_record($pdo, $prescription_id, $doctor_id);

if (!$record) {
    prescription_flash('error', 'Prescription was not found or access was denied.');
    prescription_redirect();
}

$medicines = prescription_get_medicines($pdo, $prescription_id);
$tests = prescription_get_tests($pdo, $prescription_id);
$page_title = 'View Prescription';
require_once __DIR__ . '/includes/header.php';
?>

<section class="rx-page-hero">
    <div>
        <div class="rx-breadcrumb">
            <a href="<?= prescription_e(prescription_url()) ?>">Prescriptions</a>
            <span>/</span>
            <span><?= prescription_e($record['prescription_no'] ?? '') ?></span>
        </div>
        <h1><?= prescription_e($record['prescription_no'] ?? 'Prescription') ?></h1>
        <p>Prescription for <?= prescription_e($record['patient_name'] ?? '') ?> on <?= prescription_e(prescription_date($record['visit_date'] ?? '')) ?>.</p>
    </div>
    <div class="rx-hero-actions">
        <a class="rx-btn rx-btn-white" href="<?= prescription_e(prescription_url('edit.php?id=' . $prescription_id)) ?>">Edit</a>
        <a class="rx-btn rx-btn-white" target="_blank" href="<?= prescription_e(prescription_url('print.php?id=' . $prescription_id)) ?>">Print</a>
        <a class="rx-btn rx-btn-primary" href="<?= prescription_e(prescription_url('new.php?patient_id=' . (int)($record['patient_id'] ?? 0))) ?>">New for Patient</a>
    </div>
</section>

<section class="rx-card">
    <div class="rx-card-head">
        <div>
            <h2>Prescription Overview</h2>
            <p>Created <?= prescription_e(prescription_date($record['created_at'] ?? '', 'd M Y, h:i A')) ?></p>
        </div>
        <span class="rx-status <?= prescription_e($record['status'] ?? 'draft') ?>">
            <?= prescription_e(prescription_status_label((string)($record['status'] ?? 'draft'))) ?>
        </span>
    </div>

    <div class="rx-card-body">
        <div class="rx-detail-grid">
            <div class="rx-detail-card">
                <h3>Patient Information</h3>
                <div class="rx-kv-list">
                    <div class="rx-kv"><span>Name</span><strong><?= prescription_e($record['patient_name'] ?? '—') ?></strong></div>
                    <div class="rx-kv"><span>Age</span><strong><?= prescription_e($record['patient_age'] ?: '—') ?></strong></div>
                    <div class="rx-kv"><span>Gender</span><strong><?= prescription_e($record['patient_gender'] ?: '—') ?></strong></div>
                    <div class="rx-kv"><span>Phone</span><strong><?= prescription_e($record['patient_phone'] ?: '—') ?></strong></div>
                    <div class="rx-kv"><span>Blood Group</span><strong><?= prescription_e($record['patient_blood_group'] ?: '—') ?></strong></div>
                    <div class="rx-kv"><span>Address</span><strong><?= prescription_e($record['patient_address'] ?: '—') ?></strong></div>
                </div>
            </div>

            <div class="rx-detail-card">
                <h3>Visit & Vitals</h3>
                <div class="rx-kv-list">
                    <div class="rx-kv"><span>Visit Date</span><strong><?= prescription_e(prescription_date($record['visit_date'] ?? '')) ?></strong></div>
                    <div class="rx-kv"><span>Weight</span><strong><?= prescription_e($record['weight'] ?: '—') ?></strong></div>
                    <div class="rx-kv"><span>Height</span><strong><?= prescription_e($record['height'] ?: '—') ?></strong></div>
                    <div class="rx-kv"><span>Blood Pressure</span><strong><?= prescription_e($record['blood_pressure'] ?: '—') ?></strong></div>
                    <div class="rx-kv"><span>Temperature</span><strong><?= prescription_e($record['temperature'] ?: '—') ?></strong></div>
                    <div class="rx-kv"><span>Pulse / SpO₂</span><strong><?= prescription_e(trim(($record['pulse'] ?: '—') . ' / ' . ($record['spo2'] ?: '—'))) ?></strong></div>
                </div>
            </div>

            <?php foreach ([
                'chief_complaints' => 'Chief Complaints',
                'medical_history' => 'Medical History',
                'examination' => 'Examination',
                'diagnosis' => 'Diagnosis',
            ] as $field => $label): ?>
                <div class="rx-detail-card">
                    <h3><?= prescription_e($label) ?></h3>
                    <div class="rx-content-block"><?= prescription_e($record[$field] ?: '—') ?></div>
                </div>
            <?php endforeach; ?>

            <div class="rx-detail-card full">
                <h3>Rx — Medicines</h3>
                <?php if (!$medicines): ?>
                    <div class="rx-content-block">No medicine added.</div>
                <?php else: ?>
                    <div class="rx-rx-list">
                        <?php foreach ($medicines as $index => $medicine): ?>
                            <div class="rx-rx-item">
                                <span class="rx-rx-number"><?= $index + 1 ?></span>
                                <div>
                                    <?php
                                    $generic_name = trim((string)(
                                        $medicine['generic_name']
                                        ?? $medicine['medicine_name']
                                        ?? ''
                                    ));
                                    $brand_name = trim((string)($medicine['brand_name'] ?? ''));
                                    $medicine_form = trim((string)($medicine['medicine_form'] ?? ''));
                                    ?>
                                    <strong>
                                        <?= $medicine_form !== '' ? prescription_e($medicine_form) . ' ' : '' ?><?= prescription_e($generic_name) ?>
                                        <?= $brand_name !== '' ? ' (' . prescription_e($brand_name) . ')' : '' ?>
                                        <?= !empty($medicine['strength']) ? ' — ' . prescription_e($medicine['strength']) : '' ?>
                                    </strong>
                                    <small>
                                        <?= prescription_e(implode(' · ', array_filter([
                                            $medicine['dosage'] ?? '',
                                            $medicine['frequency'] ?? '',
                                            $medicine['duration'] ?? '',
                                            $medicine['instruction'] ?? '',
                                        ]))) ?>
                                    </small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="rx-detail-card full">
                <h3>Investigations / Tests</h3>
                <?php if (!$tests): ?>
                    <div class="rx-content-block">No test added.</div>
                <?php else: ?>
                    <div class="rx-rx-list">
                        <?php foreach ($tests as $index => $test): ?>
                            <div class="rx-rx-item">
                                <span class="rx-rx-number"><?= $index + 1 ?></span>
                                <div>
                                    <strong><?= prescription_e($test['test_name'] ?? '') ?></strong>
                                    <small><?= prescription_e($test['instruction'] ?? '') ?></small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="rx-detail-card full">
                <h3>Advice</h3>
                <div class="rx-content-block"><?= prescription_e($record['advice'] ?: '—') ?></div>
            </div>

            <div class="rx-detail-card">
                <h3>Follow-up</h3>
                <div class="rx-content-block"><?= prescription_e(prescription_date($record['follow_up_date'] ?? '')) ?></div>
            </div>

            <div class="rx-detail-card">
                <h3>Private Doctor Notes</h3>
                <div class="rx-content-block"><?= prescription_e($record['private_notes'] ?: '—') ?></div>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
