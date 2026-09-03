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

<style>
.rx-appointment-page{--ap-border:#d0d7de;--ap-soft:#f6f8fa;--ap-muted:#656d76;--ap-text:#1f2328;--ap-blue:#0969da;--ap-blue-soft:#ddf4ff}.rx-appointment-hero,.rx-appointment-card,.rx-appointment-side{border:1px solid var(--ap-border);border-radius:8px;background:#fff;box-shadow:0 1px 0 rgba(31,35,40,.04)}.rx-appointment-hero{display:flex;justify-content:space-between;gap:18px;margin-bottom:16px;padding:20px}.rx-appointment-kicker{color:var(--ap-muted);font-size:11px;font-weight:700;letter-spacing:.06em;text-transform:uppercase}.rx-appointment-hero h1{margin:4px 0 0;font-size:23px;line-height:1.25}.rx-appointment-hero p{max-width:760px;margin:7px 0 0;color:var(--ap-muted);font-size:13px;line-height:1.55}.rx-appointment-hero-actions{display:flex;gap:8px;align-items:flex-start;flex-wrap:wrap}.rx-appointment-note{display:flex;gap:10px;margin-bottom:16px;padding:12px 14px;border:1px solid #b6ddf5;border-radius:8px;background:var(--ap-blue-soft);color:#0550ae;font-size:12px;line-height:1.5}.rx-appointment-note strong{color:var(--ap-blue)}.rx-appointment-layout{display:grid;grid-template-columns:minmax(0,1fr) 300px;gap:16px;align-items:start}.rx-appointment-head{padding:15px 16px;border-bottom:1px solid var(--ap-border);border-radius:8px 8px 0 0;background:var(--ap-soft)}.rx-appointment-head h2{margin:0;font-size:16px}.rx-appointment-head p{margin:5px 0 0;color:var(--ap-muted);font-size:11px;line-height:1.45}.rx-appointment-body{padding:16px}.rx-appointment-search{position:relative;margin-bottom:18px;padding-bottom:18px;border-bottom:1px solid #eaeef2}.rx-appointment-search label,.rx-appointment-field label{display:block;margin-bottom:6px;font-size:12px;font-weight:600}.rx-appointment-search input,.rx-appointment-field input,.rx-appointment-field select,.rx-appointment-field textarea{width:100%;min-height:38px;padding:8px 10px;border:1px solid var(--ap-border);border-radius:6px;background:#fff;color:var(--ap-text);font:inherit;font-size:13px;outline:none}.rx-appointment-field textarea{min-height:96px;resize:vertical}.rx-appointment-search input:focus,.rx-appointment-field input:focus,.rx-appointment-field select:focus,.rx-appointment-field textarea:focus{border-color:#54aeff;box-shadow:0 0 0 3px rgba(84,174,255,.2)}.rx-appointment-search small,.rx-appointment-field small{display:block;margin-top:5px;color:var(--ap-muted);font-size:10px;line-height:1.4}.rx-appointment-results{position:absolute;z-index:30;top:calc(100% - 14px);right:0;left:0;display:none;max-height:300px;overflow:auto;border:1px solid var(--ap-border);border-radius:6px;background:#fff;box-shadow:0 8px 24px rgba(140,149,159,.2)}.rx-appointment-results.is-open{display:block}.rx-appointment-result{width:100%;display:block;padding:10px 12px;border:0;border-bottom:1px solid #eaeef2;background:#fff;text-align:left;cursor:pointer}.rx-appointment-result:hover{background:var(--ap-soft)}.rx-appointment-result strong,.rx-appointment-result span{display:block}.rx-appointment-result strong{font-size:12px}.rx-appointment-result span{margin-top:3px;color:var(--ap-muted);font-size:10px}.rx-appointment-result-empty{padding:12px;color:var(--ap-muted);font-size:11px}.rx-appointment-selected{display:none;align-items:center;justify-content:space-between;gap:12px;margin-bottom:16px;padding:10px 12px;border:1px solid #b6ddf5;border-radius:6px;background:var(--ap-blue-soft)}.rx-appointment-selected.is-visible{display:flex}.rx-appointment-selected strong,.rx-appointment-selected span{display:block}.rx-appointment-selected strong{color:var(--ap-blue);font-size:12px}.rx-appointment-selected span{margin-top:2px;color:#0550ae;font-size:10px}.rx-appointment-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}.rx-appointment-field.full{grid-column:1/-1}.rx-appointment-required{color:#cf222e}.rx-appointment-submit{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-top:18px;padding-top:16px;border-top:1px solid #eaeef2}.rx-appointment-submit p{margin:0;color:var(--ap-muted);font-size:11px;line-height:1.45}.rx-appointment-submit-actions{display:flex;gap:8px}.rx-appointment-side{position:sticky;top:80px}.rx-appointment-side-body{padding:16px}.rx-appointment-avatar{display:grid;place-items:center;width:52px;height:52px;margin-bottom:12px;border:1px solid var(--ap-border);border-radius:50%;background:var(--ap-soft);font-size:16px;font-weight:650;text-transform:uppercase}.rx-appointment-side h3{margin:0;font-size:14px}.rx-appointment-side p{margin:5px 0 14px;color:var(--ap-muted);font-size:11px;line-height:1.45}.rx-appointment-details{margin:0;padding:0;list-style:none}.rx-appointment-details li{padding:9px 0;border-top:1px solid #eaeef2}.rx-appointment-details span,.rx-appointment-details strong{display:block}.rx-appointment-details span{color:var(--ap-muted);font-size:9px;font-weight:700;letter-spacing:.04em;text-transform:uppercase}.rx-appointment-details strong{margin-top:3px;font-size:11px;word-break:break-word}@media(max-width:980px){.rx-appointment-layout{grid-template-columns:1fr}.rx-appointment-side{position:static}}@media(max-width:650px){.rx-appointment-hero{flex-direction:column;padding:16px}.rx-appointment-grid{grid-template-columns:1fr}.rx-appointment-field.full{grid-column:auto}.rx-appointment-submit{align-items:stretch;flex-direction:column}.rx-appointment-submit-actions{width:100%}.rx-appointment-submit-actions .rx-btn{flex:1 1 0}}
</style>

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

                <div class="rx-appointment-search" id="appointmentPatientSearchWrap">
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

<script>
document.addEventListener('DOMContentLoaded', function () {
    const searchInput = document.getElementById('appointmentPatientLookup');
    const results = document.getElementById('appointmentPatientResults');
    const wrapper = document.getElementById('appointmentPatientSearchWrap');
    const patientId = document.getElementById('appointmentPatientId');
    const nameField = document.getElementById('appointmentPatientName');
    const phoneField = document.getElementById('appointmentPatientPhone');
    const ageField = document.getElementById('appointmentPatientAge');
    const genderField = document.getElementById('appointmentPatientGender');
    const bloodGroupField = document.getElementById('appointmentPatientBloodGroup');
    const addressField = document.getElementById('appointmentPatientAddress');
    const selectedCard = document.getElementById('appointmentSelectedPatient');
    const selectedName = document.getElementById('appointmentSelectedName');
    const selectedMeta = document.getElementById('appointmentSelectedMeta');
    const clearButton = document.getElementById('appointmentClearPatient');

    if (!searchInput || !results || !wrapper || !clearButton) return;

    const searchUrl = <?= json_encode(prescription_url('ajax/patient-search.php'), JSON_UNESCAPED_SLASHES) ?>;
    let timer = null;
    let controller = null;

    const closeResults = function () {
        results.classList.remove('is-open');
        results.innerHTML = '';
    };

    const fillPatient = function (patient) {
        patientId.value = String(patient.id || '');
        nameField.value = patient.name || '';
        phoneField.value = patient.phone || '';
        ageField.value = patient.age || '';
        genderField.value = patient.gender || '';
        bloodGroupField.value = patient.blood_group || '';
        addressField.value = patient.address || '';
        selectedName.textContent = patient.name || 'Selected Patient';
        selectedMeta.textContent = [patient.patient_code || '', patient.phone || ''].filter(Boolean).join(' · ');
        selectedCard.classList.add('is-visible');
        searchInput.value = '';
        closeResults();
    };

    const clearPatient = function () {
        patientId.value = '';
        nameField.value = '';
        phoneField.value = '';
        ageField.value = '';
        genderField.value = '';
        bloodGroupField.value = '';
        addressField.value = '';
        selectedName.textContent = '';
        selectedMeta.textContent = '';
        selectedCard.classList.remove('is-visible');
        searchInput.focus();
    };

    const renderResults = function (items) {
        results.innerHTML = '';

        if (!Array.isArray(items) || items.length === 0) {
            const empty = document.createElement('div');
            empty.className = 'rx-appointment-result-empty';
            empty.textContent = 'No matching patient found. Enter the details below to add a new patient.';
            results.appendChild(empty);
            results.classList.add('is-open');
            return;
        }

        items.forEach(function (patient) {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'rx-appointment-result';

            const title = document.createElement('strong');
            title.textContent = patient.name || 'Patient';

            const meta = document.createElement('span');
            meta.textContent = [patient.phone || 'No mobile', patient.patient_code || '', patient.age || '', patient.gender || ''].filter(Boolean).join(' · ');

            button.appendChild(title);
            button.appendChild(meta);
            button.addEventListener('click', function () { fillPatient(patient); });
            results.appendChild(button);
        });

        results.classList.add('is-open');
    };

    const runSearch = function () {
        const query = searchInput.value.trim();
        if (query.length < 2) {
            closeResults();
            return;
        }

        if (controller) controller.abort();
        controller = new AbortController();

        fetch(searchUrl + '?q=' + encodeURIComponent(query), {
            headers: { 'Accept': 'application/json' },
            signal: controller.signal
        })
            .then(function (response) {
                if (!response.ok) throw new Error('Search failed');
                return response.json();
            })
            .then(function (payload) { renderResults(payload && payload.items ? payload.items : []); })
            .catch(function (error) {
                if (error.name !== 'AbortError') closeResults();
            });
    };

    searchInput.addEventListener('input', function () {
        window.clearTimeout(timer);
        timer = window.setTimeout(runSearch, 220);
    });
    searchInput.addEventListener('focus', function () {
        if (searchInput.value.trim().length >= 2) runSearch();
    });
    clearButton.addEventListener('click', clearPatient);
    document.addEventListener('click', function (event) {
        if (!wrapper.contains(event.target)) closeResults();
    });
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') closeResults();
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
