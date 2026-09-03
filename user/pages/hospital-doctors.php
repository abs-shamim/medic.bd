<?php
require_once __DIR__ . '/../includes/auth.php';
$user = require_user_login();
if (($user['role'] ?? '') !== 'hospital_owner') {
    die('Only hospital owners can access this page.');
}

$hospital_id = user_profile_id($user);
$message = '';
$error = '';

if (!user_has_claim($user)) {
    $error = 'Your hospital claim must be approved before adding doctors.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error) {
    $payload = [
        'doctor_id' => (int)($_POST['doctor_id'] ?? 0),
        'name' => safe_request_value('name'),
        'degree' => safe_request_value('degree'),
        'designation' => safe_request_value('designation'),
        'specialty_id' => safe_request_value('specialty_id'),
        'experience_years' => safe_request_value('experience_years'),
        'consultation_fee' => safe_request_value('consultation_fee'),
        'phone' => safe_request_value('phone'),
        'email' => safe_request_value('email'),
        'bmdc_number' => safe_request_value('bmdc_number'),
        'gender' => safe_request_value('gender'),
        'bio' => safe_request_value('bio'),
        'hospital_id' => $hospital_id,
        'chamber_name' => safe_request_value('chamber_name'),
        'chamber_address' => safe_request_value('chamber_address'),
        'visiting_days' => safe_request_value('visiting_days'),
        'visiting_time' => safe_request_value('visiting_time'),
        'appointment_phone' => safe_request_value('appointment_phone'),
    ];

    if ((int)$payload['doctor_id'] <= 0 && $payload['name'] === '') {
        $error = 'Select existing doctor or write new doctor name.';
    } else {
        $stmt = $pdo->prepare("INSERT INTO profile_update_requests (user_id,profile_type,profile_id,request_type,payload,status,created_at) VALUES (:user_id,'hospital',:profile_id,'doctor_add',:payload,'pending',NOW())");
        $stmt->execute([
            ':user_id' => $user['id'],
            ':profile_id' => $hospital_id,
            ':payload' => user_panel_json_encode($payload),
        ]);
        $message = 'Doctor add request submitted for admin approval.';
    }
}

$specialties = get_options('specialties', 'name');
$all_doctors = $pdo->query("SELECT id,name FROM doctors WHERE status='active' ORDER BY name ASC LIMIT 2000")->fetchAll();
$stmt = $pdo->prepare("SELECT DISTINCT d.id,d.name,d.phone,d.designation FROM doctors d LEFT JOIN chambers c ON c.doctor_id=d.id WHERE d.hospital_id=:hid OR c.hospital_id=:hid ORDER BY d.name ASC LIMIT 1000");
$stmt->execute([':hid' => $hospital_id]);
$current_doctors = $stmt->fetchAll();
$requests = $pdo->prepare("SELECT * FROM profile_update_requests WHERE user_id=:user_id AND request_type='doctor_add' ORDER BY id DESC LIMIT 20");
$requests->execute([':user_id' => $user['id']]);
$page_title = 'Hospital Doctors';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="user-card">
  <h1>Add Doctor to Hospital</h1>
  <p class="muted">Hospital owner can request to add many doctors under own hospital.</p>
  <?php if ($message): ?><div class="user-alert success"><?= e($message) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="user-alert error"><?= e($error) ?></div><?php endif; ?>
  <?php if (!$error): ?>
  <form method="post" class="user-grid">
    <div class="user-field full"><label>Select Existing Doctor</label><select name="doctor_id"><option value="0">New doctor / not listed</option><?php foreach ($all_doctors as $d): ?><option value="<?= e((string)$d['id']) ?>"><?= e($d['name']) ?></option><?php endforeach; ?></select></div>
    <div class="section-title"><h3>Doctor Details</h3></div>
    <div class="user-field"><label>New Doctor Name</label><input name="name" placeholder="Use if doctor is not listed"></div>
    <div class="user-field"><label>Specialty</label><select name="specialty_id"><option value="">Select specialty</option><?php foreach ($specialties as $s): ?><option value="<?= e((string)$s['id']) ?>"><?= e($s['label']) ?></option><?php endforeach; ?></select></div>
    <div class="user-field"><label>Degree</label><input name="degree"></div>
    <div class="user-field"><label>Designation</label><input name="designation"></div>
    <div class="user-field"><label>Experience Years</label><input type="number" name="experience_years"></div>
    <div class="user-field"><label>Consultation Fee</label><input type="number" step="0.01" name="consultation_fee"></div>
    <div class="user-field"><label>Phone</label><input name="phone"></div>
    <div class="user-field"><label>Email</label><input type="email" name="email"></div>
    <div class="user-field"><label>BMDC Number</label><input name="bmdc_number"></div>
    <div class="user-field"><label>Gender</label><input name="gender"></div>
    <div class="user-field full"><label>Bio</label><textarea name="bio"></textarea></div>
    <div class="section-title"><h3>Hospital Chamber Details</h3></div>
    <div class="user-field"><label>Chamber Name</label><input name="chamber_name"></div>
    <div class="user-field"><label>Appointment Phone</label><input name="appointment_phone"></div>
    <div class="user-field"><label>Visiting Days</label><input name="visiting_days"></div>
    <div class="user-field"><label>Visiting Time</label><input name="visiting_time"></div>
    <div class="user-field full"><label>Chamber Address</label><textarea name="chamber_address"></textarea></div>
    <div class="full"><button class="user-btn user-btn-primary">Submit Doctor Add Request</button></div>
  </form>
  <?php endif; ?>
</div>
<div class="user-card"><h2>Current Hospital Doctors</h2><div class="user-table-wrap"><table class="user-table"><tr><th>ID</th><th>Name</th><th>Designation</th><th>Phone</th></tr><?php foreach ($current_doctors as $d): ?><tr><td><?= e((string)$d['id']) ?></td><td><?= e($d['name']) ?></td><td><?= e($d['designation'] ?? '') ?></td><td><?= e($d['phone'] ?? '') ?></td></tr><?php endforeach; ?></table></div></div>
<div class="user-card"><h2>Doctor Add Requests</h2><div class="user-table-wrap"><table class="user-table"><tr><th>ID</th><th>Status</th><th>Date</th></tr><?php foreach ($requests as $req): ?><tr><td>#<?= e((string)$req['id']) ?></td><td><span class="badge <?= e($req['status']) ?>"><?= e($req['status']) ?></span></td><td><?= e($req['created_at']) ?></td></tr><?php endforeach; ?></table></div></div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
