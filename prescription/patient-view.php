<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$patient_id = (int)($_GET['id'] ?? 0);
$patient = prescription_get_patient($pdo, $patient_id, $doctor_id);

if (!$patient) {
    prescription_flash('error', 'Patient was not found or access was denied.');
    prescription_redirect('patients.php');
}

$stmt = $pdo->prepare("
    SELECT *
    FROM prescriptions
    WHERE doctor_id = :doctor_id
      AND patient_id = :patient_id
    ORDER BY visit_date DESC, id DESC
");
$stmt->execute([
    ':doctor_id' => $doctor_id,
    ':patient_id' => $patient_id,
]);
$prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
$latest_prescription = $prescriptions[0] ?? null;

$latest_vitals = [
    'Weight' => $patient['last_weight'] ?? ($latest_prescription['weight'] ?? ''),
    'Height' => $patient['last_height'] ?? ($latest_prescription['height'] ?? ''),
    'Blood Pressure' => $patient['last_blood_pressure'] ?? ($latest_prescription['blood_pressure'] ?? ''),
    'Temperature' => $patient['last_temperature'] ?? ($latest_prescription['temperature'] ?? ''),
    'Pulse' => $patient['last_pulse'] ?? ($latest_prescription['pulse'] ?? ''),
    'SpO₂' => $patient['last_spo2'] ?? ($latest_prescription['spo2'] ?? ''),
];

$page_title = 'Patient: ' . ($patient['name'] ?? '');
require_once __DIR__ . '/includes/header.php';
?>

<section class="rx-page-hero">
    <div>
        <div class="rx-breadcrumb">
            <a href="<?= prescription_e(prescription_url('patients.php')) ?>">Patients</a>
            <span>/</span>
            <span><?= prescription_e($patient['patient_code'] ?? '') ?></span>
        </div>
        <h1><?= prescription_e($patient['name'] ?? '') ?></h1>
        <p>Saved patient profile, latest clinical information and complete prescription history.</p>
    </div>
    <div class="rx-hero-actions">
        <a class="rx-btn rx-btn-white" href="<?= prescription_e(prescription_url('patients.php')) ?>">Back to Patients</a>
        <a class="rx-btn rx-btn-primary" href="<?= prescription_e(prescription_url('new.php?patient_id=' . $patient_id)) ?>">+ New Prescription</a>
    </div>
</section>

<section class="rx-patient-profile-summary">
    <div class="rx-patient-profile-primary">
        <span class="rx-patient-profile-avatar"><?= prescription_e(prescription_initial((string)($patient['name'] ?? 'P'))) ?></span>
        <div>
            <span class="rx-patient-profile-code"><?= prescription_e($patient['patient_code'] ?? '') ?></span>
            <h2><?= prescription_e($patient['name'] ?? '') ?></h2>
            <p><?= prescription_e($patient['phone'] ?: 'No mobile number') ?> · <?= prescription_e((string)count($prescriptions)) ?> prescription(s)</p>
        </div>
    </div>
    <div class="rx-patient-profile-last-visit">
        <span>Last Visit</span>
        <strong><?= prescription_e(prescription_date($patient['last_visit_date'] ?? ($latest_prescription['visit_date'] ?? ''))) ?></strong>
    </div>
</section>

<section class="rx-card">
    <div class="rx-card-head">
        <div>
            <h2>Saved Patient Information</h2>
            <p>This information will auto-fill when the same name or mobile number is used.</p>
        </div>
    </div>
    <div class="rx-card-body">
        <div class="rx-detail-grid">
            <div class="rx-detail-card">
                <h3>Basic Details</h3>
                <div class="rx-kv-list">
                    <div class="rx-kv"><span>Name</span><strong><?= prescription_e($patient['name'] ?? '—') ?></strong></div>
                    <div class="rx-kv"><span>Age</span><strong><?= prescription_e($patient['age'] ?: '—') ?></strong></div>
                    <div class="rx-kv"><span>Gender</span><strong><?= prescription_e($patient['gender'] ?: '—') ?></strong></div>
                    <div class="rx-kv"><span>Blood Group</span><strong><?= prescription_e($patient['blood_group'] ?: '—') ?></strong></div>
                </div>
            </div>
            <div class="rx-detail-card">
                <h3>Contact Details</h3>
                <div class="rx-kv-list">
                    <div class="rx-kv"><span>Phone</span><strong><?= prescription_e($patient['phone'] ?: '—') ?></strong></div>
                    <div class="rx-kv"><span>Address</span><strong><?= prescription_e($patient['address'] ?: '—') ?></strong></div>
                    <div class="rx-kv"><span>Patient Since</span><strong><?= prescription_e(prescription_date($patient['created_at'] ?? '', 'd M Y, h:i A')) ?></strong></div>
                </div>
            </div>
        </div>

        <div class="rx-patient-vitals-grid">
            <?php foreach ($latest_vitals as $label => $value): ?>
                <div class="rx-patient-vital-card">
                    <span><?= prescription_e($label) ?></span>
                    <strong><?= prescription_e(trim((string)$value) !== '' ? (string)$value : '—') ?></strong>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="rx-patient-history-note">
            <span>Saved Medical History</span>
            <p><?= nl2br(prescription_e($patient['medical_history'] ?: ($latest_prescription['medical_history'] ?? 'No medical history has been saved yet.'))) ?></p>
        </div>
    </div>
</section>

<section class="rx-card">
    <div class="rx-card-head">
        <div>
            <h2>Prescription History</h2>
            <p><?= prescription_e((string)count($prescriptions)) ?> prescription(s) are permanently linked to this patient.</p>
        </div>
        <a class="rx-btn rx-btn-primary" href="<?= prescription_e(prescription_url('new.php?patient_id=' . $patient_id)) ?>">Create New Rx</a>
    </div>
    <div class="rx-card-body">
        <?php if (!$prescriptions): ?>
            <div class="rx-empty">
                <strong>No prescription found</strong>
                <span>Create the first prescription for this patient.</span>
            </div>
        <?php else: ?>
            <div class="rx-table-wrap">
                <table class="rx-table">
                    <thead>
                    <tr>
                        <th>Prescription</th>
                        <th>Visit Date</th>
                        <th>Diagnosis</th>
                        <th>Vitals</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($prescriptions as $row): ?>
                        <tr>
                            <td><?= prescription_e($row['prescription_no'] ?? '') ?></td>
                            <td><?= prescription_e(prescription_date($row['visit_date'] ?? '')) ?></td>
                            <td><?= prescription_e(prescription_excerpt($row['diagnosis'] ?? '—', 80)) ?></td>
                            <td>
                                <span class="rx-subtext">
                                    <?= prescription_e(trim(implode(' · ', array_filter([
                                        ($row['weight'] ?? '') !== '' ? 'Wt ' . $row['weight'] : '',
                                        ($row['blood_pressure'] ?? '') !== '' ? 'BP ' . $row['blood_pressure'] : '',
                                        ($row['spo2'] ?? '') !== '' ? 'SpO₂ ' . $row['spo2'] : '',
                                    ]))) ?: '—') ?>
                                </span>
                            </td>
                            <td><span class="rx-status <?= prescription_e($row['status'] ?? 'draft') ?>"><?= prescription_e(prescription_status_label((string)($row['status'] ?? 'draft'))) ?></span></td>
                            <td>
                                <div class="rx-inline-actions">
                                    <a class="rx-btn rx-btn-soft rx-btn-sm" href="<?= prescription_e(prescription_url('view.php?id=' . (int)$row['id'])) ?>">View</a>
                                    <a class="rx-btn rx-btn-soft rx-btn-sm" href="<?= prescription_e(prescription_url('edit.php?id=' . (int)$row['id'])) ?>">Edit</a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
