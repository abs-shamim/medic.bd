<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$patient_id = max(0, (int)($_POST['patient_id'] ?? $_GET['patient_id'] ?? 0));
$patient = $patient_id > 0
    ? prescription_get_patient($pdo, $patient_id, $doctor_id)
    : null;

if ($patient_id > 0 && !$patient && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    prescription_flash('error', 'The selected patient record was not found.');
    prescription_redirect('appointment.php');
}

$form_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!prescription_verify_csrf($_POST['csrf_token'] ?? null)) {
        $form_error = 'Security verification failed. Please refresh and try again.';
    } else {
        $patient_name = prescription_clean_text($_POST['patient_name'] ?? '', 150);
        $patient_phone = prescription_clean_text($_POST['patient_phone'] ?? '', 50);
        $phone_normalized = prescription_normalize_phone($patient_phone);

        if ($patient_name === '') {
            $form_error = 'Patient name is required.';
        } elseif ($patient_phone === '') {
            $form_error = 'Mobile number is required for appointment contact.';
        } elseif (prescription_strlen($phone_normalized) < 6) {
            $form_error = 'Please enter a valid mobile number.';
        } else {
            try {
                $saved_patient_id = prescription_upsert_patient(
                    $pdo,
                    $doctor_id,
                    [
                        'patient_id' => $patient_id,
                        'patient_name' => $patient_name,
                        'patient_phone' => $patient_phone,
                        'patient_age' => $_POST['patient_age'] ?? '',
                        'patient_gender' => $_POST['patient_gender'] ?? '',
                        'patient_blood_group' => $_POST['patient_blood_group'] ?? '',
                        'patient_address' => $_POST['patient_address'] ?? '',
                    ]
                );

                prescription_flash('success', 'Patient details saved successfully for the appointment.');
                prescription_redirect('appointment.php?patient_id=' . $saved_patient_id);
            } catch (InvalidArgumentException $e) {
                $form_error = $e->getMessage();
            } catch (Throwable $e) {
                error_log('[Appointment Patient Save] ' . get_class($e) . ': ' . $e->getMessage());
                $form_error = 'Patient details could not be saved. Please try again.';
            }
        }
    }
}

function appointment_patient_value(?array $patient, string $field, string $post_field = ''): string
{
    $post_field = $post_field !== '' ? $post_field : $field;

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && array_key_exists($post_field, $_POST)) {
        return trim((string)$_POST[$post_field]);
    }

    return trim((string)($patient[$field] ?? ''));
}

$page_title = $patient ? 'Update Patient' : 'Appointment';
$hide_new_prescription_nav = true;
$topbar_action_label = '+ Add Patient';
$topbar_action_url = prescription_url('appointment.php');
require_once __DIR__ . '/includes/header.php';
?>

<link rel="stylesheet" href="<?= prescription_e(prescription_url('assets/css/appointment.css')) ?>">

<div class="rx-appointment-page">
    <?php if ($form_error !== ''): ?>
        <div class="rx-alert error" role="alert">
            <span class="rx-alert-icon">!</span>
            <div>
                <strong>Patient details were not saved</strong>
                <p><?= prescription_e($form_error) ?></p>
            </div>
        </div>
    <?php endif; ?>

    <section class="rx-appointment-hero">
        <div>
            <span class="rx-appointment-kicker">Appointment</span>
            <h1><?= $patient ? 'Update Patient Details' : 'Add Patient Details' ?></h1>
            <p>Save the patient's basic information before the appointment. Search by name, mobile number or patient code to fill a saved patient automatically.</p>
        </div>
        <div class="rx-appointment-hero-actions">
            <a class="rx-btn rx-btn-soft" href="<?= prescription_e(prescription_url('patients.php')) ?>">Patient Directory</a>
            <?php if ($patient): ?>
                <a class="rx-btn rx-btn-primary" href="<?= prescription_e(prescription_url('appointment.php')) ?>">+ Add Another Patient</a>
            <?php endif; ?>
        </div>
    </section>

    <div class="rx-appointment-note">
        <span aria-hidden="true">ⓘ</span>
        <div><strong>Patient details only.</strong> This appointment page does not create or include a prescription.</div>
    </div>

    <div class="rx-appointment-layout">
        <section class="rx-appointment-card">
            <div class="rx-appointment-head">
                <h2>Patient Information</h2>
                <p>Search an existing patient first, or enter a new patient's details.</p>
            </div>

            <form method="POST" class="rx-appointment-body" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?= prescription_e(prescription_csrf_token()) ?>">
                <input type="hidden" name="patient_id" id="appointmentPatientId" value="<?= prescription_e((string)$patient_id) ?>">

                <div class="rx-appointment-search" id="appointmentPatientSearchWrap" data-search-url="<?= prescription_e(prescription_url('ajax/patient-search.php')) ?>">
                    <label for="appointmentPatientLookup">Find Existing Patient</label>
                    <input type="search" id="appointmentPatientLookup" placeholder="Name, mobile number or patient code" autocomplete="off">
                    <small>Selecting a patient fills the saved details below.</small>
                    <div class="rx-appointment-results" id="appointmentPatientResults"></div>
                </div>

                <div class="rx-appointment-selected <?= $patient ? 'is-visible' : '' ?>" id="appointmentSelectedPatient">
                    <div>
                        <strong id="appointmentSelectedName"><?= prescription_e($patient['name'] ?? '') ?></strong>
                        <span id="appointmentSelectedMeta"><?= prescription_e(trim((string)($patient['patient_code'] ?? '') . ' · ' . (string)($patient['phone'] ?? ''), ' ·')) ?></span>
                    </div>
                    <button type="button" class="rx-btn rx-btn-soft rx-btn-sm" id="appointmentClearPatient">Use New Patient</button>
                </div>

                <div class="rx-appointment-grid">
                    <div class="rx-appointment-field">
                        <label for="appointmentPatientName">Patient Name <span class="rx-appointment-required">*</span></label>
                        <input type="text" id="appointmentPatientName" name="patient_name" value="<?= prescription_e(appointment_patient_value($patient, 'name', 'patient_name')) ?>" maxlength="150" required placeholder="Full patient name">
                    </div>

                    <div class="rx-appointment-field">
                        <label for="appointmentPatientPhone">Mobile Number <span class="rx-appointment-required">*</span></label>
                        <input type="tel" id="appointmentPatientPhone" name="patient_phone" value="<?= prescription_e(appointment_patient_value($patient, 'phone', 'patient_phone')) ?>" maxlength="50" required placeholder="Patient mobile number">
                        <small>The mobile number helps prevent duplicate records.</small>
                    </div>

                    <div class="rx-appointment-field">
                        <label for="appointmentPatientAge">Age</label>
                        <input type="text" id="appointmentPatientAge" name="patient_age" value="<?= prescription_e(appointment_patient_value($patient, 'age', 'patient_age')) ?>" maxlength="30" placeholder="Example: 35">
                    </div>

                    <div class="rx-appointment-field">
                        <label for="appointmentPatientGender">Gender</label>
                        <?php $selected_gender = appointment_patient_value($patient, 'gender', 'patient_gender'); ?>
                        <select id="appointmentPatientGender" name="patient_gender">
                            <option value="">Select gender</option>
                            <option value="Male" <?= strcasecmp($selected_gender, 'Male') === 0 ? 'selected' : '' ?>>Male</option>
                            <option value="Female" <?= strcasecmp($selected_gender, 'Female') === 0 ? 'selected' : '' ?>>Female</option>
                            <option value="Other" <?= strcasecmp($selected_gender, 'Other') === 0 ? 'selected' : '' ?>>Other</option>
                        </select>
                    </div>

                    <div class="rx-appointment-field">
                        <label for="appointmentPatientBloodGroup">Blood Group</label>
                        <?php $selected_blood_group = appointment_patient_value($patient, 'blood_group', 'patient_blood_group'); ?>
                        <select id="appointmentPatientBloodGroup" name="patient_blood_group">
                            <option value="">Select blood group</option>
                            <?php foreach (['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'] as $blood_group): ?>
                                <option value="<?= prescription_e($blood_group) ?>" <?= $selected_blood_group === $blood_group ? 'selected' : '' ?>><?= prescription_e($blood_group) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="rx-appointment-field full">
                        <label for="appointmentPatientAddress">Address</label>
                        <textarea id="appointmentPatientAddress" name="patient_address" maxlength="1000" placeholder="Patient address"><?= prescription_e(appointment_patient_value($patient, 'address', 'patient_address')) ?></textarea>
                    </div>
                </div>

                <div class="rx-appointment-submit">
                    <p>Saving adds or updates the patient directory only.</p>
                    <div class="rx-appointment-submit-actions">
                        <a class="rx-btn rx-btn-soft" href="<?= prescription_e(prescription_url('appointment.php')) ?>">Clear</a>
                        <button type="submit" class="rx-btn rx-btn-primary"><?= $patient ? 'Update Patient Details' : 'Save Patient Details' ?></button>
                    </div>
                </div>
            </form>
        </section>

        <aside class="rx-appointment-side">
            <div class="rx-appointment-head">
                <h2>Saved Patient</h2>
                <p>Current patient record.</p>
            </div>
            <div class="rx-appointment-side-body">
                <span class="rx-appointment-avatar"><?= prescription_e(prescription_initial((string)($patient['name'] ?? 'Patient'))) ?></span>
                <h3><?= prescription_e($patient['name'] ?? 'No patient selected') ?></h3>
                <p><?= $patient ? 'This patient is saved in your directory.' : 'Search or enter details to create a patient record.' ?></p>
                <ul class="rx-appointment-details">
                    <li><span>Patient Code</span><strong><?= prescription_e($patient['patient_code'] ?? 'Generated after save') ?></strong></li>
                    <li><span>Mobile</span><strong><?= prescription_e($patient['phone'] ?? 'Not saved') ?></strong></li>
                    <li><span>Age / Gender</span><strong><?= prescription_e(trim((string)($patient['age'] ?? '') . ' / ' . (string)($patient['gender'] ?? ''), ' /') ?: 'Not saved') ?></strong></li>
                    <li><span>Address</span><strong><?= prescription_e($patient['address'] ?? 'Not saved') ?></strong></li>
                </ul>
            </div>
        </aside>
    </div>
</div>

<script src="<?= prescription_e(prescription_url('assets/js/appointment.js')) ?>"></script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
