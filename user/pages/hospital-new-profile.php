<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

$user = require_user_login();

$user_id = (int)($user['id'] ?? 0);

/*
|--------------------------------------------------------------------------
| Resolve Current User Type Safely
|--------------------------------------------------------------------------
| Some projects store hospital account type as hospital_owner, hospital,
| Hospital Owner, Hospital, hospital-owner, or in session only.
| So we normalize before checking permission.
|--------------------------------------------------------------------------
*/

if (!function_exists('hp_normalize_user_type')) {
    function hp_normalize_user_type($type): string
    {
        $type = trim((string)$type);
        $type = strtolower($type);
        $type = str_replace(['-', ' '], '_', $type);
        $type = preg_replace('/_+/', '_', $type);

        if (in_array($type, ['hospital_owner', 'hospital', 'clinic_owner', 'clinic'], true)) {
            return 'hospital_owner';
        }

        if (in_array($type, ['doctor', 'physician'], true)) {
            return 'doctor';
        }

        return $type !== '' ? $type : 'user';
    }
}

$raw_user_type = '';

if (function_exists('current_user_type')) {
    $raw_user_type = (string)current_user_type();
}

if ($raw_user_type === '') {
    $raw_user_type = (string)(
        $user['user_type']
        ?? $user['role']
        ?? $_SESSION['user_type']
        ?? $_SESSION['role']
        ?? 'user'
    );
}

$user_type = hp_normalize_user_type($raw_user_type);

$is_hospital_user = $user_type === 'hospital_owner';

if (!$is_hospital_user) {
    $page_title = 'Create Hospital Profile';
    require_once __DIR__ . '/../includes/header.php';
    ?>
    <div class="user-card card-padded">
        <h1>Create Hospital Profile</h1>
        <div class="user-alert error">Only Hospital or Hospital Owner accounts can create a hospital profile.</div>
        <a class="btn" href="dashboard.php">Back to Dashboard</a>
    </div>
    <?php
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

if (function_exists('user_has_claim') && user_has_claim($user)) {
    redirect('hospital-profile-update.php');
}

$page_title = 'Create New Hospital Profile';
$form_message = '';
$form_message_type = '';
$errors = [];

/*
|--------------------------------------------------------------------------
| Local Helpers
|--------------------------------------------------------------------------
*/

if (!function_exists('hp_table_exists')) {
    function hp_table_exists(string $table): bool
    {
        global $pdo;

        try {
            $stmt = $pdo->prepare(" 
                SELECT COUNT(*)
                FROM INFORMATION_SCHEMA.TABLES
                WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = :table
            ");
            $stmt->execute([':table' => $table]);
            return (int)$stmt->fetchColumn() > 0;
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('hp_column_exists')) {
    function hp_column_exists(string $table, string $column): bool
    {
        global $pdo;

        try {
            $stmt = $pdo->prepare(" 
                SELECT COUNT(*)
                FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = :table
                AND COLUMN_NAME = :column
            ");
            $stmt->execute([
                ':table' => $table,
                ':column' => $column,
            ]);
            return (int)$stmt->fetchColumn() > 0;
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('hp_label_column')) {
    function hp_label_column(string $table): string
    {
        foreach (['name', 'name_en', 'title'] as $column) {
            if (hp_column_exists($table, $column)) {
                return $column;
            }
        }

        return 'id';
    }
}

if (!function_exists('hp_options')) {
    function hp_options(string $table): array
    {
        global $pdo;

        $allowed = ['divisions', 'districts', 'thanas'];

        if (!in_array($table, $allowed, true) || !hp_table_exists($table)) {
            return [];
        }

        try {
            $label = hp_label_column($table);
            $extra = '';

            if ($table === 'districts' && hp_column_exists('districts', 'division_id')) {
                $extra = ', division_id';
            }

            if ($table === 'thanas' && hp_column_exists('thanas', 'district_id')) {
                $extra = ', district_id';
            }

            $stmt = $pdo->query(" 
                SELECT id, {$label} AS label {$extra}
                FROM {$table}
                ORDER BY {$label} ASC
                LIMIT 5000
            ");

            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }
}

if (!function_exists('hp_address_name_by_id')) {
    function hp_address_name_by_id(string $table, int $id): string
    {
        global $pdo;

        if ($id <= 0 || !in_array($table, ['divisions', 'districts', 'thanas'], true) || !hp_table_exists($table)) {
            return '';
        }

        $label = hp_label_column($table);

        try {
            $stmt = $pdo->prepare("SELECT {$label} AS name FROM {$table} WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => $id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return trim((string)($row['name'] ?? ''));
        } catch (Throwable $e) {
            return '';
        }
    }
}

if (!function_exists('hp_old')) {
    function hp_old(string $field, string $default = ''): string
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            return trim((string)($_POST[$field] ?? $default));
        }

        return $default;
    }
}

if (!function_exists('hp_checked')) {
    function hp_checked(string $field): int
    {
        return isset($_POST[$field]) ? 1 : 0;
    }
}

if (!function_exists('hp_post_array')) {
    function hp_post_array(string $key): array
    {
        $items = $_POST[$key] ?? [];

        if (!is_array($items)) {
            return [];
        }

        $clean = [];

        foreach ($items as $item) {
            $item = trim((string)$item);
            if ($item !== '') {
                $clean[] = $item;
            }
        }

        return array_values(array_unique($clean));
    }
}

if (!function_exists('hp_merge_text_options')) {
    function hp_merge_text_options(string $text, array $options): string
    {
        $lines = preg_split('/\r\n|\r|\n/', trim($text));
        $merged = [];
        $seen = [];

        foreach (($lines ?: []) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $key = function_exists('mb_strtolower') ? mb_strtolower($line) : strtolower($line);
            if (!isset($seen[$key])) {
                $merged[] = $line;
                $seen[$key] = true;
            }
        }

        foreach ($options as $option) {
            $option = trim((string)$option);
            if ($option === '') {
                continue;
            }

            $key = function_exists('mb_strtolower') ? mb_strtolower($option) : strtolower($option);
            if (!isset($seen[$key])) {
                $merged[] = $option;
                $seen[$key] = true;
            }
        }

        return implode("\n", $merged);
    }
}

if (!function_exists('hp_upload_image')) {
    function hp_upload_image(string $field, string $folder = 'hospitals'): string
    {
        if (empty($_FILES[$field]['name']) || empty($_FILES[$field]['tmp_name'])) {
            return '';
        }

        if (!is_uploaded_file($_FILES[$field]['tmp_name'])) {
            return '';
        }

        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        $ext = strtolower(pathinfo((string)$_FILES[$field]['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, $allowed, true)) {
            return '';
        }

        $base_dir = __DIR__ . '/../../assets/uploads/' . trim($folder, '/') . '/';

        if (!is_dir($base_dir)) {
            mkdir($base_dir, 0755, true);
        }

        $file_name = 'pending_hospital_' . date('YmdHis') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
        $target = $base_dir . $file_name;

        if (move_uploaded_file($_FILES[$field]['tmp_name'], $target)) {
            return 'assets/uploads/' . trim($folder, '/') . '/' . $file_name;
        }

        return '';
    }
}

if (!function_exists('hp_payload_json')) {
    function hp_payload_json(array $data): string
    {
        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
    }
}

if (!function_exists('hp_render_checkboxes')) {
    function hp_render_checkboxes(string $name, array $options): void
    {
        $posted = hp_post_array($name);

        foreach ($options as $option) {
            $checked = in_array($option, $posted, true) ? 'checked' : '';
            ?>
            <label class="lite-option-check">
                <input type="checkbox" name="<?= e($name) ?>[]" value="<?= e($option) ?>" <?= $checked ?>>
                <span><?= e($option) ?></span>
            </label>
            <?php
        }
    }
}

/*
|--------------------------------------------------------------------------
| Options From Hospital Admin Form Structure
|--------------------------------------------------------------------------
*/

$hospital_types = [
    'Private Hospital',
    'Government Hospital',
    'Medical College Hospital',
    'Specialized Hospital',
    'General Hospital',
    'Diagnostic Center',
    'Clinic',
    'Eye Hospital',
    'Dental Hospital',
    'Cardiac Hospital',
    'Cancer Hospital',
    'Mother and Child Hospital',
    'Rehabilitation Center',
];

$hospital_services = [
    '24/7 Emergency Service',
    'Outdoor Patient Department (OPD)',
    'Indoor Admission',
    'Doctor Consultation',
    'Specialist Consultation',
    'Surgery Service',
    'Normal Delivery',
    'C-Section Delivery',
    'Child Care',
    'Vaccination',
    'Dental Care',
    'Eye Care',
    'Gynecology Service',
    'Medicine Service',
    'Pediatrics Service',
    'Cardiology Service',
    'Neurology Service',
    'Health Checkup Package',
    'Telemedicine Service',
    'Ambulance Service',
    'Pharmacy Service',
    'Blood Bank Service',
];

$hospital_facilities = [
    'Reception Desk',
    'Waiting Area',
    'Patient Cabin',
    'General Ward',
    'Private Cabin',
    'VIP Cabin',
    'Nurse Station',
    'Operation Theater',
    'Labor Room',
    'Pharmacy',
    'Canteen',
    'Prayer Room',
    'Parking Area',
    'Wheelchair Access',
    'Lift Facility',
    'Generator Backup',
    'Online Appointment',
    'Billing Counter',
    'Information Desk',
    'Medical Records Section',
];

$diagnostic_lab_options = [
    'Pathology Lab',
    'Biochemistry Lab',
    'Microbiology Lab',
    'Hematology Lab',
    'Blood Test',
    'Urine Test',
    'CBC Test',
    'Liver Function Test',
    'Kidney Function Test',
    'Diabetes Test',
    'ECG',
    'Echocardiogram',
    'X-Ray',
    'Digital X-Ray',
    'Ultrasonography',
    'CT Scan',
    'MRI',
    'Endoscopy',
];

$special_unit_options = [
    'Cardiology Unit',
    'Neurology Unit',
    'Nephrology Unit',
    'Urology Unit',
    'Oncology Unit',
    'Orthopedic Unit',
    'Gynecology and Obstetrics Unit',
    'Pediatrics Unit',
    'Neonatal Unit',
    'Dental Unit',
    'Eye Unit',
    'ENT Unit',
    'Physiotherapy Unit',
    'Dialysis Unit',
];

$critical_care_options = [
    'ICU',
    'CCU',
    'NICU',
    'PICU',
    'HDU',
    'Emergency ICU',
    'Cardiac ICU',
    'Ventilator Support',
    'Life Support',
    'Oxygen Support',
    'Critical Care Ambulance',
    '24/7 Critical Care Doctor',
    '24/7 Nursing Support',
];

$divisions = hp_options('divisions');
$districts = hp_options('districts');
$thanas = hp_options('thanas');

/*
|--------------------------------------------------------------------------
| Submit Request
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $required_errors = [];

    $name = trim((string)($_POST['name'] ?? ''));
    $type = trim((string)($_POST['type'] ?? 'Private Hospital'));
    $division_id = (int)($_POST['division_id'] ?? 0);
    $district_id = (int)($_POST['district_id'] ?? 0);

    if ($name === '') {
        $required_errors[] = 'Hospital Name';
    }

    if (!in_array($type, $hospital_types, true)) {
        $type = 'Private Hospital';
    }

    if ($division_id <= 0) {
        $required_errors[] = 'Division';
    }

    if ($district_id <= 0) {
        $required_errors[] = 'District';
    }

    if (!empty($required_errors)) {
        $errors[] = 'Please fill required fields: ' . implode(', ', $required_errors) . '.';
    }

    $thana_id = (int)($_POST['thana_id'] ?? 0);
    $division_name = hp_address_name_by_id('divisions', $division_id);
    $district_name = hp_address_name_by_id('districts', $district_id);
    $thana_name = hp_address_name_by_id('thanas', $thana_id);

    $area = trim((string)($_POST['area'] ?? ''));
    $road_no = trim((string)($_POST['road_no'] ?? ''));
    $house_no = trim((string)($_POST['house_no'] ?? ''));
    $post_code = trim((string)($_POST['post_code'] ?? ''));

    $thana_with_post_code = $thana_name;
    if ($post_code !== '') {
        $thana_with_post_code = $thana_name !== '' ? $thana_name . '-' . $post_code : $post_code;
    }

    $full_address = implode(', ', array_filter([
        $house_no,
        $road_no,
        $area,
        $thana_with_post_code,
        $district_name,
    ]));

    $image = hp_upload_image('image', 'hospitals');
    $cover_image = hp_upload_image('cover_image', 'hospitals');

    $services = hp_merge_text_options(
        trim((string)($_POST['services'] ?? '')),
        hp_post_array('service_options')
    );

    $facilities = hp_merge_text_options(
        trim((string)($_POST['facilities'] ?? '')),
        array_merge(
            hp_post_array('facility_options'),
            hp_post_array('diagnostic_lab_options'),
            hp_post_array('special_unit_options'),
            hp_post_array('critical_care_options')
        )
    );

    $payload = [
        'name' => $name,
        'slug' => trim((string)($_POST['slug'] ?? '')),
        'type' => $type,
        'license_number' => trim((string)($_POST['license_number'] ?? '')),
        'phone' => trim((string)($_POST['phone'] ?? '')),
        'email' => trim((string)($_POST['email'] ?? '')),
        'website_url' => trim((string)($_POST['website_url'] ?? '')),
        'whatsapp' => trim((string)($_POST['whatsapp'] ?? '')),
        'emergency_phone' => trim((string)($_POST['emergency_phone'] ?? '')),
        'ambulance_phone' => trim((string)($_POST['ambulance_phone'] ?? '')),
        'city' => $district_name,
        'address' => $full_address,
        'division_id' => $division_id,
        'district_id' => $district_id,
        'thana_id' => $thana_id,
        'division_name' => $division_name,
        'district_name' => $district_name,
        'thana_name' => $thana_name,
        'area' => $area,
        'road_no' => $road_no,
        'house_no' => $house_no,
        'post_code' => $post_code,
        'map_url' => trim((string)($_POST['map_url'] ?? '')),
        'image' => $image,
        'cover_image' => $cover_image,
        'description' => trim((string)($_POST['description'] ?? '')),
        'services' => $services,
        'facilities' => $facilities,
        'bed_count' => (int)($_POST['bed_count'] ?? 0),
        'established_year' => trim((string)($_POST['established_year'] ?? '')),
        'opening_hours' => trim((string)($_POST['opening_hours'] ?? '')),
        'visiting_hours' => trim((string)($_POST['visiting_hours'] ?? '')),
        'appointment_note' => trim((string)($_POST['appointment_note'] ?? '')),
        'video_url' => trim((string)($_POST['video_url'] ?? '')),
        'emergency_available' => hp_checked('emergency_available'),
        'ambulance_available' => hp_checked('ambulance_available'),
        'icu_available' => hp_checked('icu_available'),
        'ccu_available' => hp_checked('ccu_available'),
        'nicu_available' => hp_checked('nicu_available'),
        'picu_available' => hp_checked('picu_available'),
        'operation_theater_available' => hp_checked('operation_theater_available'),
        'diagnostic_available' => hp_checked('diagnostic_available'),
        'pharmacy_available' => hp_checked('pharmacy_available'),
        'blood_bank_available' => hp_checked('blood_bank_available'),
        'parking_available' => hp_checked('parking_available'),
        'cafeteria_available' => hp_checked('cafeteria_available'),
        'wheelchair_available' => hp_checked('wheelchair_available'),
        'oxygen_available' => hp_checked('oxygen_available'),
        'dialysis_available' => hp_checked('dialysis_available'),
        'mri_available' => hp_checked('mri_available'),
        'ct_scan_available' => hp_checked('ct_scan_available'),
        'xray_available' => hp_checked('xray_available'),
        'lab_available' => hp_checked('lab_available'),
        'seo_title' => trim((string)($_POST['seo_title'] ?? '')),
        'seo_description' => trim((string)($_POST['seo_description'] ?? '')),
        'status' => 'pending',
        'created_by_user_id' => $user_id,
    ];

    if (empty($errors)) {
        try {
            if (!hp_table_exists('profile_update_requests')) {
                throw new RuntimeException('profile_update_requests table not found.');
            }

            $stmt = $pdo->prepare(" 
                INSERT INTO profile_update_requests
                (
                    user_id,
                    request_type,
                    doctor_id,
                    hospital_id,
                    chamber_id,
                    request_data,
                    status,
                    created_at
                )
                VALUES
                (
                    :user_id,
                    'hospital_profile_create',
                    NULL,
                    NULL,
                    NULL,
                    :request_data,
                    'pending',
                    NOW()
                )
            ");

            $stmt->execute([
                ':user_id' => $user_id,
                ':request_data' => hp_payload_json($payload),
            ]);

            $form_message_type = 'success';
            $form_message = 'Hospital profile request submitted successfully. Admin will review and approve it.';

            $_POST = [];
        } catch (Throwable $e) {
            $form_message_type = 'error';
            $form_message = 'Request could not be saved. Please check profile_update_requests table and request_type enum. Error: ' . $e->getMessage();
        }
    } else {
        $form_message_type = 'error';
        $form_message = implode(' ', $errors);
    }
}

$requests = [];

try {
    if (hp_table_exists('profile_update_requests')) {
        $stmt = $pdo->prepare(" 
            SELECT *
            FROM profile_update_requests
            WHERE user_id = :user_id
            AND request_type = 'hospital_profile_create'
            ORDER BY id DESC
            LIMIT 20
        ");
        $stmt->execute([':user_id' => $user_id]);
        $requests = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
} catch (Throwable $e) {
    $requests = [];
}

require_once __DIR__ . '/../includes/header.php';
?>

<link rel="stylesheet" href="<?php echo BASE_URL; ?>/user/assets/css/hospital-new-profile.css">

<div class="hospital-profile-page">

    <?php if ($form_message !== ''): ?>
        <div class="hp-alert <?= e($form_message_type) ?>">
            <div class="hp-alert-icon"><?= $form_message_type === 'success' ? '✓' : '!' ?></div>
            <div>
                <h3><?= $form_message_type === 'success' ? 'Success' : 'Error' ?></h3>
                <p><?= e($form_message) ?></p>
            </div>
        </div>
    <?php endif; ?>

    <div class="hp-hero">
        <div class="hp-hero-inner">
            <div>
                <div class="hp-breadcrumb">
                    <a href="dashboard.php">Dashboard</a>
                    <span>/</span>
                    <span>Create Hospital Profile</span>
                </div>
                <h1>Create New Hospital Profile</h1>
                <p>Submit your hospital information for admin review. After approval, the hospital profile will be connected with your account.</p>
            </div>
            <a href="dashboard.php" class="hp-btn">Back to Dashboard</a>
        </div>
    </div>

    <div class="hp-card">
        <div class="hp-card-header">
            <h2>Hospital Information</h2>
            <p>Fields marked with * are required. Images and detailed services are optional but recommended.</p>
        </div>

        <form class="hp-form" method="POST" enctype="multipart/form-data">
            <div class="hp-section">
                <div class="hp-section-title">
                    <h3>Basic Information</h3>
                    <span>Hospital identity</span>
                </div>

                <div class="hp-grid">
                    <div class="hp-field">
                        <label>Hospital Name <span class="required-star">*</span></label>
                        <input type="text" name="name" placeholder="Example: Square Hospital" value="<?= e(hp_old('name', $user['name'] ?? '')) ?>" required>
                    </div>

                    <div class="hp-field">
                        <label>Hospital Type</label>
                        <select name="type">
                            <?php foreach ($hospital_types as $type): ?>
                                <option value="<?= e($type) ?>" <?= hp_old('type', $user['hospital_type'] ?? 'Private Hospital') === $type ? 'selected' : '' ?>>
                                    <?= e($type) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="hp-field">
                        <label>License Number</label>
                        <input type="text" name="license_number" placeholder="Example: DGHS-123456" value="<?= e(hp_old('license_number', $user['license_number'] ?? '')) ?>">
                    </div>

                    <div class="hp-field">
                        <label>Established Year</label>
                        <input type="number" name="established_year" placeholder="Example: 2005" value="<?= e(hp_old('established_year')) ?>">
                    </div>

                    <div class="hp-field">
                        <label>Bed Count</label>
                        <input type="number" name="bed_count" placeholder="Example: 250" value="<?= e(hp_old('bed_count')) ?>">
                    </div>

                    <div class="hp-field">
                        <label>Website URL</label>
                        <input type="url" name="website_url" placeholder="https://example.com" value="<?= e(hp_old('website_url')) ?>">
                    </div>
                </div>
            </div>

            <div class="hp-section">
                <div class="hp-section-title">
                    <h3>Contact Information</h3>
                    <span>Phone, email and emergency contact</span>
                </div>

                <div class="hp-grid">
                    <div class="hp-field">
                        <label>Phone</label>
                        <input type="text" name="phone" placeholder="+880..." value="<?= e(hp_old('phone', $user['phone'] ?? '')) ?>">
                    </div>

                    <div class="hp-field">
                        <label>Email</label>
                        <input type="email" name="email" placeholder="hospital@example.com" value="<?= e(hp_old('email', $user['email'] ?? '')) ?>">
                    </div>

                    <div class="hp-field">
                        <label>WhatsApp</label>
                        <input type="text" name="whatsapp" placeholder="+880..." value="<?= e(hp_old('whatsapp')) ?>">
                    </div>

                    <div class="hp-field">
                        <label>Emergency Phone</label>
                        <input type="text" name="emergency_phone" placeholder="Emergency number" value="<?= e(hp_old('emergency_phone')) ?>">
                    </div>

                    <div class="hp-field">
                        <label>Ambulance Phone</label>
                        <input type="text" name="ambulance_phone" placeholder="Ambulance number" value="<?= e(hp_old('ambulance_phone')) ?>">
                    </div>

                    <div class="hp-field">
                        <label>Opening Hours</label>
                        <input type="text" name="opening_hours" placeholder="24/7 or 9 AM - 10 PM" value="<?= e(hp_old('opening_hours')) ?>">
                    </div>

                    <div class="hp-field full">
                        <label>Visiting Hours</label>
                        <input type="text" name="visiting_hours" placeholder="Example: 10 AM - 8 PM" value="<?= e(hp_old('visiting_hours')) ?>">
                    </div>
                </div>
            </div>

            <div class="hp-section">
                <div class="hp-section-title">
                    <h3>Address Information</h3>
                    <span>Location and map</span>
                </div>

                <div class="hp-grid three">
                    <div class="hp-field">
                        <label>Division <span class="required-star">*</span></label>
                        <select name="division_id" id="division_id" required>
                            <option value="">Select Division</option>
                            <?php foreach ($divisions as $division): ?>
                                <option value="<?= e((string)$division['id']) ?>" <?= hp_old('division_id') == $division['id'] ? 'selected' : '' ?>>
                                    <?= e($division['label'] ?? '') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="hp-field">
                        <label>District <span class="required-star">*</span></label>
                        <select name="district_id" id="district_id" required>
                            <option value="">Select District</option>
                            <?php foreach ($districts as $district): ?>
                                <option value="<?= e((string)$district['id']) ?>" data-division="<?= e((string)($district['division_id'] ?? '')) ?>" <?= hp_old('district_id') == $district['id'] ? 'selected' : '' ?>>
                                    <?= e($district['label'] ?? '') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="hp-field">
                        <label>Thana / Upazila</label>
                        <select name="thana_id" id="thana_id">
                            <option value="">Select Thana</option>
                            <?php foreach ($thanas as $thana): ?>
                                <option value="<?= e((string)$thana['id']) ?>" data-district="<?= e((string)($thana['district_id'] ?? '')) ?>" <?= hp_old('thana_id') == $thana['id'] ? 'selected' : '' ?>>
                                    <?= e($thana['label'] ?? '') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="hp-field">
                        <label>Area / Locality</label>
                        <input type="text" name="area" placeholder="Example: Panthapath" value="<?= e(hp_old('area')) ?>">
                    </div>

                    <div class="hp-field">
                        <label>Road / Street</label>
                        <input type="text" name="road_no" placeholder="Example: Road 5" value="<?= e(hp_old('road_no')) ?>">
                    </div>

                    <div class="hp-field">
                        <label>House / Building</label>
                        <input type="text" name="house_no" placeholder="Example: House 12" value="<?= e(hp_old('house_no')) ?>">
                    </div>

                    <div class="hp-field">
                        <label>Post Code</label>
                        <input type="text" name="post_code" placeholder="Example: 1215" value="<?= e(hp_old('post_code')) ?>">
                    </div>

                    <div class="hp-field full">
                        <label>Google Map URL</label>
                        <input type="url" name="map_url" placeholder="https://maps.google.com/..." value="<?= e(hp_old('map_url')) ?>">
                    </div>
                </div>
            </div>

            <div class="hp-section">
                <div class="hp-section-title">
                    <h3>Media</h3>
                    <span>Hospital logo and cover image</span>
                </div>

                <div class="hp-grid">
                    <div class="hp-field">
                        <label>Hospital Image / Logo</label>
                        <input type="file" name="image" accept="image/jpeg,image/png,image/webp">
                        <span class="hp-help">Allowed: JPG, PNG, WebP.</span>
                    </div>

                    <div class="hp-field">
                        <label>Cover Image</label>
                        <input type="file" name="cover_image" accept="image/jpeg,image/png,image/webp">
                        <span class="hp-help">Recommended wide image for hospital page header.</span>
                    </div>

                    <div class="hp-field full">
                        <label>Video URL</label>
                        <input type="url" name="video_url" placeholder="YouTube or video link" value="<?= e(hp_old('video_url')) ?>">
                    </div>
                </div>
            </div>

            <div class="hp-section">
                <div class="hp-section-title">
                    <h3>Services and Facilities</h3>
                    <span>Details for hospital profile</span>
                </div>

                <div class="hp-grid">
                    <div class="hp-field full">
                        <label>Description</label>
                        <textarea name="description" placeholder="Write hospital description"><?= e(hp_old('description')) ?></textarea>
                    </div>

                    <div class="hp-field full">
                        <label>Services</label>
                        <textarea name="services" placeholder="Write custom services, one service per line"><?= e(hp_old('services')) ?></textarea>
                    </div>

                    <div class="hp-field full">
                        <div class="lite-option-panel">
                            <h4>Common Service Options</h4>
                            <div class="lite-option-grid">
                                <?php hp_render_checkboxes('service_options', $hospital_services); ?>
                            </div>
                        </div>
                    </div>

                    <div class="hp-field full">
                        <label>Facilities</label>
                        <textarea name="facilities" placeholder="Write custom facilities, one facility per line"><?= e(hp_old('facilities')) ?></textarea>
                    </div>

                    <div class="hp-field full">
                        <div class="lite-option-panel">
                            <h4>Facility Options</h4>
                            <div class="lite-option-grid">
                                <?php hp_render_checkboxes('facility_options', $hospital_facilities); ?>
                            </div>
                        </div>
                    </div>

                    <div class="hp-field full">
                        <div class="lite-option-panel">
                            <h4>Diagnostic & Lab Options</h4>
                            <div class="lite-option-grid">
                                <?php hp_render_checkboxes('diagnostic_lab_options', $diagnostic_lab_options); ?>
                            </div>
                        </div>
                    </div>

                    <div class="hp-field full">
                        <div class="lite-option-panel">
                            <h4>Special Unit Options</h4>
                            <div class="lite-option-grid">
                                <?php hp_render_checkboxes('special_unit_options', $special_unit_options); ?>
                            </div>
                        </div>
                    </div>

                    <div class="hp-field full">
                        <div class="lite-option-panel">
                            <h4>Critical Care Options</h4>
                            <div class="lite-option-grid">
                                <?php hp_render_checkboxes('critical_care_options', $critical_care_options); ?>
                            </div>
                        </div>
                    </div>

                    <div class="hp-field full">
                        <label>Appointment Note</label>
                        <textarea name="appointment_note" placeholder="Write appointment instructions or note"><?= e(hp_old('appointment_note')) ?></textarea>
                    </div>
                </div>
            </div>

            <div class="hp-section">
                <div class="hp-section-title">
                    <h3>Available Facilities</h3>
                    <span>Quick yes/no features</span>
                </div>

                <div class="hp-toggle-row">
                    <?php
                    $toggles = [
                        'emergency_available' => 'Emergency',
                        'ambulance_available' => 'Ambulance',
                        'icu_available' => 'ICU',
                        'ccu_available' => 'CCU',
                        'nicu_available' => 'NICU',
                        'picu_available' => 'PICU',
                        'operation_theater_available' => 'Operation Theater',
                        'diagnostic_available' => 'Diagnostic',
                        'pharmacy_available' => 'Pharmacy',
                        'blood_bank_available' => 'Blood Bank',
                        'parking_available' => 'Parking',
                        'cafeteria_available' => 'Cafeteria',
                        'wheelchair_available' => 'Wheelchair',
                        'oxygen_available' => 'Oxygen',
                        'dialysis_available' => 'Dialysis',
                        'mri_available' => 'MRI',
                        'ct_scan_available' => 'CT Scan',
                        'xray_available' => 'X-Ray',
                        'lab_available' => 'Lab',
                    ];
                    ?>

                    <?php foreach ($toggles as $field => $label): ?>
                        <label class="hp-toggle-label">
                            <input type="checkbox" name="<?= e($field) ?>" <?= isset($_POST[$field]) ? 'checked' : '' ?>>
                            <span><?= e($label) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="hp-section">
                <div class="hp-section-title">
                    <h3>SEO Information</h3>
                    <span>Optional search engine information</span>
                </div>

                <div class="hp-grid">
                    <div class="hp-field full">
                        <label>SEO Title</label>
                        <input type="text" name="seo_title" placeholder="SEO title" value="<?= e(hp_old('seo_title')) ?>">
                    </div>

                    <div class="hp-field full">
                        <label>SEO Description</label>
                        <textarea name="seo_description" placeholder="SEO description"><?= e(hp_old('seo_description')) ?></textarea>
                    </div>
                </div>
            </div>

            <div class="hp-submit-bar">
                <p>Your request will stay pending until admin approval.</p>
                <button type="submit" class="hp-btn hp-btn-primary">Submit Hospital Profile Request</button>
            </div>
        </form>
    </div>

    <div class="hp-card hp-card-spaced">
        <div class="hp-card-header">
            <h2>Recent Hospital Profile Requests</h2>
            <p>Your latest submitted hospital profile creation requests.</p>
        </div>

        <div class="hp-table-scroll">
            <table class="hp-request-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Status</th>
                        <th>Created</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$requests): ?>
                        <tr>
                            <td colspan="3" class="hp-table-empty-cell">No request found.</td>
                        </tr>
                    <?php endif; ?>

                    <?php foreach ($requests as $request): ?>
                        <tr>
                            <td>#<?= e((string)$request['id']) ?></td>
                            <td><span class="hp-badge"><?= e(ucfirst((string)($request['status'] ?? 'pending'))) ?></span></td>
                            <td><?= !empty($request['created_at']) ? e(date('d M Y h:i A', strtotime((string)$request['created_at']))) : '—' ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="<?php echo BASE_URL; ?>/user/assets/js/hospital-new-profile.js" defer></script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
