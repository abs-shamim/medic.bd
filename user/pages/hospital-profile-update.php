<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

$user = require_user_login();

/*
|--------------------------------------------------------------------------
| Hospital Profile Update Page
|--------------------------------------------------------------------------
| File path: user/pages/hospital-profile-update.php
|--------------------------------------------------------------------------
| Section based hospital profile update, same style as profile-update.php.
| Only changed fields are stored in profile_update_requests.request_data.
| Admin approval will publish the changes.
|--------------------------------------------------------------------------
*/

$form_message = '';
$form_message_type = '';
$error = '';

if (!empty($_SESSION['hospital_update_flash_message'])) {
    $form_message_type = $_SESSION['hospital_update_flash_type'] ?? 'success';
    $form_message = $_SESSION['hospital_update_flash_message'];

    unset($_SESSION['hospital_update_flash_type'], $_SESSION['hospital_update_flash_message']);
}

/*
|--------------------------------------------------------------------------
| Flash / Response Helpers
|--------------------------------------------------------------------------
*/

function hpu_flash_redirect(string $type, string $message, string $section = 'basic'): void
{
    $_SESSION['hospital_update_flash_type'] = $type;
    $_SESSION['hospital_update_flash_message'] = $message;

    redirect('hospital-profile-update.php?section=' . urlencode($section));
}

function hpu_json_response(array $payload): void
{
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload);
    exit;
}

/*
|--------------------------------------------------------------------------
| DB Helpers
|--------------------------------------------------------------------------
*/

function hpu_table_exists(string $table): bool
{
    global $pdo;

    try {
        if (function_exists('table_exists')) {
            return (bool)table_exists($table);
        }

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

function hpu_column_exists(string $table, string $column): bool
{
    global $pdo;

    try {
        if (function_exists('column_exists')) {
            return (bool)column_exists($table, $column);
        }

        if (!hpu_table_exists($table)) {
            return false;
        }

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

function hpu_add_column_if_missing(string $table, string $column, string $definition): void
{
    global $pdo;

    try {
        if (!hpu_table_exists($table)) {
            return;
        }

        if (!hpu_column_exists($table, $column)) {
            $pdo->exec("ALTER TABLE {$table} ADD COLUMN {$column} {$definition}");
        }
    } catch (Throwable $e) {
        // Keep page working if ALTER permission is unavailable.
    }
}

function hpu_ensure_request_columns(): void
{
    if (!hpu_table_exists('profile_update_requests')) {
        return;
    }

    hpu_add_column_if_missing('profile_update_requests', 'doctor_id', 'INT UNSIGNED NULL');
    hpu_add_column_if_missing('profile_update_requests', 'hospital_id', 'INT UNSIGNED NULL');
    hpu_add_column_if_missing('profile_update_requests', 'chamber_id', 'INT UNSIGNED NULL');
    hpu_add_column_if_missing('profile_update_requests', 'request_type', "VARCHAR(100) NULL");
    hpu_add_column_if_missing('profile_update_requests', 'request_data', 'LONGTEXT NULL');
    hpu_add_column_if_missing('profile_update_requests', 'status', "VARCHAR(50) DEFAULT 'pending'");
    hpu_add_column_if_missing('profile_update_requests', 'admin_note', 'TEXT NULL');
    hpu_add_column_if_missing('profile_update_requests', 'created_at', 'DATETIME NULL');
    hpu_add_column_if_missing('profile_update_requests', 'updated_at', 'DATETIME NULL');
}

function hpu_label_column(string $table): string
{
    foreach (['name', 'name_en', 'title'] as $column) {
        if (hpu_column_exists($table, $column)) {
            return $column;
        }
    }

    return 'id';
}

function hpu_options(string $table): array
{
    global $pdo;

    $allowed = ['divisions', 'districts', 'thanas'];

    if (!in_array($table, $allowed, true) || !hpu_table_exists($table)) {
        return [];
    }

    try {
        $label = hpu_label_column($table);
        $extra = '';

        if ($table === 'districts' && hpu_column_exists('districts', 'division_id')) {
            $extra = ', division_id';
        }

        if ($table === 'thanas' && hpu_column_exists('thanas', 'district_id')) {
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

/*
|--------------------------------------------------------------------------
| User / Hospital Helpers
|--------------------------------------------------------------------------
*/

function hpu_normalize_user_type($type): string
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

function hpu_current_user_type(array $user): string
{
    if (function_exists('current_user_type')) {
        $type = current_user_type();

        if ($type !== '') {
            if (function_exists('normalize_user_type')) {
                return normalize_user_type((string)$type);
            }

            return hpu_normalize_user_type($type);
        }
    }

    return hpu_normalize_user_type(
        $user['user_type']
        ?? $user['role']
        ?? $_SESSION['user_type']
        ?? $_SESSION['role']
        ?? 'user'
    );
}

function hpu_is_hospital_user(array $user): bool
{
    if (function_exists('current_user_is_hospital') && current_user_is_hospital()) {
        return true;
    }

    return hpu_current_user_type($user) === 'hospital_owner';
}

function hpu_claimed_hospital_id(array $user): int
{
    foreach (['claimed_hospital_id', 'hospital_id'] as $key) {
        if (!empty($user[$key]) && (int)$user[$key] > 0) {
            return (int)$user[$key];
        }
    }

    return 0;
}

function hpu_get_hospital(int $hospital_id): ?array
{
    global $pdo;

    if ($hospital_id <= 0 || !hpu_table_exists('hospitals')) {
        return null;
    }

    try {
        $stmt = $pdo->prepare("
            SELECT *
            FROM hospitals
            WHERE id = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $hospital_id]);
        $hospital = $stmt->fetch(PDO::FETCH_ASSOC);

        return $hospital ?: null;
    } catch (Throwable $e) {
        return null;
    }
}

/*
|--------------------------------------------------------------------------
| Request Helpers
|--------------------------------------------------------------------------
*/

function hpu_decode_request_data(?string $json): array
{
    if (!$json) {
        return [];
    }

    $data = json_decode($json, true);

    return is_array($data) ? $data : [];
}

function hpu_request_json(array $data): string
{
    if (function_exists('request_data_json')) {
        return request_data_json($data);
    }

    return (string)json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function hpu_get_pending_request(int $user_id, int $hospital_id): ?array
{
    global $pdo;

    if (!hpu_table_exists('profile_update_requests')) {
        return null;
    }

    try {
        $stmt = $pdo->prepare("
            SELECT *
            FROM profile_update_requests
            WHERE user_id = :user_id
            AND hospital_id = :hospital_id
            AND request_type = 'hospital_profile_update'
            AND status = 'pending'
            ORDER BY id DESC
            LIMIT 1
        ");
        $stmt->execute([
            ':user_id' => $user_id,
            ':hospital_id' => $hospital_id,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    } catch (Throwable $e) {
        return null;
    }
}

function hpu_get_requests(int $user_id, int $hospital_id): array
{
    global $pdo;

    if (!hpu_table_exists('profile_update_requests')) {
        return [];
    }

    try {
        $stmt = $pdo->prepare("
            SELECT *
            FROM profile_update_requests
            WHERE user_id = :user_id
            AND hospital_id = :hospital_id
            AND request_type = 'hospital_profile_update'
            ORDER BY id DESC
            LIMIT 30
        ");
        $stmt->execute([
            ':user_id' => $user_id,
            ':hospital_id' => $hospital_id,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

function hpu_save_pending_request(int $user_id, int $hospital_id, array $data, ?array $existing_request): void
{
    global $pdo;

    if (!hpu_table_exists('profile_update_requests')) {
        throw new RuntimeException('profile_update_requests table not found.');
    }

    hpu_ensure_request_columns();

    if ($existing_request) {
        $stmt = $pdo->prepare("
            UPDATE profile_update_requests
            SET request_data = :request_data,
                updated_at = NOW()
            WHERE id = :id
            LIMIT 1
        ");
        $stmt->execute([
            ':request_data' => hpu_request_json($data),
            ':id' => (int)$existing_request['id'],
        ]);

        return;
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
            'hospital_profile_update',
            NULL,
            :hospital_id,
            NULL,
            :request_data,
            'pending',
            NOW()
        )
    ");

    $stmt->execute([
        ':user_id' => $user_id,
        ':hospital_id' => $hospital_id,
        ':request_data' => hpu_request_json($data),
    ]);
}

/*
|--------------------------------------------------------------------------
| Value / UI Helpers
|--------------------------------------------------------------------------
*/

function hpu_compare_value($value): string
{
    if (is_array($value)) {
        return (string)json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    return trim((string)$value);
}

function hpu_display_value(string $field, array $hospital, array $pending_data = [], string $default = ''): string
{
    if (array_key_exists($field, $pending_data)) {
        return trim((string)$pending_data[$field]);
    }

    return trim((string)($hospital[$field] ?? $default));
}

function hpu_bool_value(string $field, array $hospital, array $pending_data = []): bool
{
    if (array_key_exists($field, $pending_data)) {
        return (int)$pending_data[$field] === 1;
    }

    return (int)($hospital[$field] ?? 0) === 1;
}

function hpu_is_updated(string $field, array $hospital, array $pending_data): bool
{
    if (!array_key_exists($field, $pending_data)) {
        return false;
    }

    return hpu_compare_value($hospital[$field] ?? '') !== hpu_compare_value($pending_data[$field] ?? '');
}

function hpu_field_class(string $field, array $hospital, array $pending_data): string
{
    return hpu_is_updated($field, $hospital, $pending_data) ? 'hpu-field is-updated' : 'hpu-field';
}

function hpu_badge(string $field, array $hospital, array $pending_data): string
{
    if (!hpu_is_updated($field, $hospital, $pending_data)) {
        return '';
    }

    return '<span class="hpu-updated-badge">Pending</span>';
}

function hpu_section_updated_count(array $fields, array $hospital, array $pending_data): int
{
    $count = 0;

    foreach ($fields as $field) {
        if (hpu_is_updated($field, $hospital, $pending_data)) {
            $count++;
        }
    }

    return $count;
}

function hpu_clean_section_payload(array $payload, array $hospital, array $pending_data): array
{
    $merged = $pending_data;

    foreach ($payload as $field => $value) {
        $new_compare = hpu_compare_value($value);
        $old_compare = hpu_compare_value($hospital[$field] ?? '');

        if ($new_compare === $old_compare) {
            unset($merged[$field]);
            continue;
        }

        $merged[$field] = $value;
    }

    foreach ($merged as $field => $value) {
        if (in_array($field, ['id', 'user_id', 'doctor_id', 'hospital_id', 'chamber_id', 'created_at', 'updated_at'], true)) {
            unset($merged[$field]);
        }
    }

    return $merged;
}

function hpu_upload_image(string $field, string $folder = 'hospitals'): string
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

    $upload_dir = __DIR__ . '/../../assets/uploads/' . trim($folder, '/') . '/';

    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    if (!is_writable($upload_dir)) {
        return '';
    }

    try {
        $file_name = 'hospital_update_' . date('YmdHis') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
    } catch (Throwable $e) {
        $file_name = 'hospital_update_' . date('YmdHis') . '_' . mt_rand(100000, 999999) . '.' . $ext;
    }

    $target = $upload_dir . $file_name;

    if (move_uploaded_file($_FILES[$field]['tmp_name'], $target)) {
        return 'assets/uploads/' . trim($folder, '/') . '/' . $file_name;
    }

    return '';
}

function hpu_file_url(string $path): string
{
    $path = trim($path);

    if ($path === '') {
        return '';
    }

    if (preg_match('/^https?:\/\//i', $path)) {
        return $path;
    }

    $path = ltrim($path, '/');

    if (defined('BASE_URL')) {
        return rtrim(BASE_URL, '/') . '/' . $path;
    }

    return '/' . $path;
}

/*
|--------------------------------------------------------------------------
| Slug Helpers
|--------------------------------------------------------------------------
*/

function hpu_normalize_slug(string $slug): string
{
    $slug = strtolower(trim($slug));
    $slug = preg_replace('/[^a-z0-9-]+/', '-', $slug);
    $slug = preg_replace('/-+/', '-', (string)$slug);

    return trim((string)$slug, '-');
}

function hpu_validate_slug(string $slug): array
{
    $slug = trim($slug);

    if ($slug === '') {
        return [
            'valid' => false,
            'message' => 'Slug cannot be empty.',
        ];
    }

    if (strlen($slug) < 3 || strlen($slug) > 100) {
        return [
            'valid' => false,
            'message' => 'Slug must be between 3 and 100 characters.',
        ];
    }

    if (!preg_match('/^[a-z0-9-]+$/', $slug)) {
        return [
            'valid' => false,
            'message' => 'Slug can contain only lowercase a-z, 0-9 and hyphen.',
        ];
    }

    if (str_starts_with($slug, '-') || str_ends_with($slug, '-')) {
        return [
            'valid' => false,
            'message' => 'Slug cannot start or end with hyphen.',
        ];
    }

    return [
        'valid' => true,
        'message' => 'Slug format is valid.',
    ];
}

function hpu_slug_exists(string $slug, int $ignore_hospital_id = 0): bool
{
    global $pdo;

    if (!hpu_table_exists('hospitals') || !hpu_column_exists('hospitals', 'slug')) {
        return false;
    }

    try {
        $sql = "SELECT id FROM hospitals WHERE slug = :slug";
        $params = [':slug' => $slug];

        if ($ignore_hospital_id > 0) {
            $sql .= " AND id != :id";
            $params[':id'] = $ignore_hospital_id;
        }

        $sql .= " LIMIT 1";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return (bool)$stmt->fetchColumn();
    } catch (Throwable $e) {
        return true;
    }
}

function hpu_slug_check_payload(string $slug, int $ignore_hospital_id = 0): array
{
    $slug = hpu_normalize_slug($slug);
    $validation = hpu_validate_slug($slug);

    if (!$validation['valid']) {
        return [
            'ok' => false,
            'available' => false,
            'slug' => $slug,
            'message' => $validation['message'],
        ];
    }

    if (hpu_slug_exists($slug, $ignore_hospital_id)) {
        return [
            'ok' => false,
            'available' => false,
            'slug' => $slug,
            'message' => 'This slug already exists. Please choose another one.',
        ];
    }

    return [
        'ok' => true,
        'available' => true,
        'slug' => $slug,
        'message' => 'This slug is available.',
    ];
}

/*
|--------------------------------------------------------------------------
| Hospital Options
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

$facility_toggles = [
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
    'dental_unit_available' => 'Dental Unit',
    'eye_unit_available' => 'Eye Unit',
    'mother_child_unit_available' => 'Mother & Child Unit',
    'cancer_unit_available' => 'Cancer Unit',
    'cardiac_unit_available' => 'Cardiac Unit',
    'rehab_unit_available' => 'Rehabilitation Unit',
];

/*
|--------------------------------------------------------------------------
| Resolve Hospital / Sections
|--------------------------------------------------------------------------
*/

$is_hospital_user = hpu_is_hospital_user($user);
$hospital_id = hpu_claimed_hospital_id($user);

if (!$is_hospital_user) {
    $error = 'This update page is available for Hospital or Hospital Owner accounts only.';
}

if ($hospital_id <= 0 && $error === '') {
    $error = 'No approved hospital profile found for this account. Please claim or create a hospital profile first.';
}

$hospital = hpu_get_hospital($hospital_id);

if (!$hospital && $error === '') {
    $error = 'Connected hospital profile data not found.';
}

$sections = [
    'basic' => 'Basic',
    'contact' => 'Contact',
    'address' => 'Address',
    'details' => 'Details',
    'image' => 'Image & Description',
    'seo' => 'SEO',
    'requests' => 'Requests',
];

$section_fields = [
    'basic' => [
        'name',
        'slug',
        'type',
        'license_number',
        'website_url',
        'opening_hours',
        'visiting_hours',
        'bed_count',
        'established_year',
    ],
    'contact' => [
        'phone',
        'email',
        'whatsapp',
        'emergency_phone',
        'ambulance_phone',
    ],
    'address' => [
        'division_id',
        'district_id',
        'thana_id',
        'area',
        'road_no',
        'house_no',
        'post_code',
        'map_url',
        'city',
        'address',
    ],
    'details' => array_merge([
        'services',
        'facilities',
        'departments',
        'appointment_note',
        'video_url',
    ], array_keys($facility_toggles)),
    'image' => [
        'image',
        'cover_image',
        'description',
    ],
    'seo' => [
        'seo_title',
        'seo_description',
        'meta_keywords',
    ],
];

$section = trim((string)($_GET['section'] ?? 'basic'));

if (!array_key_exists($section, $sections)) {
    $section = 'basic';
}

if (($_GET['ajax'] ?? '') === 'check_slug') {
    hpu_json_response(hpu_slug_check_payload((string)($_GET['slug'] ?? ''), $hospital_id));
}

$pending_request = hpu_get_pending_request((int)$user['id'], $hospital_id);
$pending_data = $pending_request ? hpu_decode_request_data($pending_request['request_data'] ?? '') : [];

/*
|--------------------------------------------------------------------------
| Submit Section
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $error === '' && $hospital) {
    $form_section = trim((string)($_POST['section'] ?? $section));

    if (!array_key_exists($form_section, $sections)) {
        $form_section = 'basic';
    }

    if ($form_section === 'requests') {
        hpu_flash_redirect('error', 'Requests section cannot be submitted.', 'requests');
    }

    $payload = [];

    if ($form_section === 'basic') {
        $name = trim((string)($_POST['name'] ?? ''));
        $slug = hpu_normalize_slug((string)($_POST['slug'] ?? ''));

        if ($name === '') {
            hpu_flash_redirect('error', 'Hospital name is required.', $form_section);
        }

        if ($slug !== '') {
            $slug_check = hpu_slug_check_payload($slug, $hospital_id);

            if (!$slug_check['ok']) {
                hpu_flash_redirect('error', $slug_check['message'], $form_section);
            }

            $slug = $slug_check['slug'];
        }

        $type = trim((string)($_POST['type'] ?? 'Private Hospital'));

        if (!in_array($type, $hospital_types, true)) {
            $type = 'Private Hospital';
        }

        $payload = [
            'name' => $name,
            'slug' => $slug,
            'type' => $type,
            'license_number' => trim((string)($_POST['license_number'] ?? '')),
            'website_url' => trim((string)($_POST['website_url'] ?? '')),
            'opening_hours' => trim((string)($_POST['opening_hours'] ?? '')),
            'visiting_hours' => trim((string)($_POST['visiting_hours'] ?? '')),
            'bed_count' => (int)($_POST['bed_count'] ?? 0),
            'established_year' => trim((string)($_POST['established_year'] ?? '')),
        ];
    }

    if ($form_section === 'contact') {
        $payload = [
            'phone' => trim((string)($_POST['phone'] ?? '')),
            'email' => trim((string)($_POST['email'] ?? '')),
            'whatsapp' => trim((string)($_POST['whatsapp'] ?? '')),
            'emergency_phone' => trim((string)($_POST['emergency_phone'] ?? '')),
            'ambulance_phone' => trim((string)($_POST['ambulance_phone'] ?? '')),
        ];
    }

    if ($form_section === 'address') {
        $division_id = (int)($_POST['division_id'] ?? 0);
        $district_id = (int)($_POST['district_id'] ?? 0);
        $thana_id = (int)($_POST['thana_id'] ?? 0);

        $area = trim((string)($_POST['area'] ?? ''));
        $road_no = trim((string)($_POST['road_no'] ?? ''));
        $house_no = trim((string)($_POST['house_no'] ?? ''));
        $post_code = trim((string)($_POST['post_code'] ?? ''));

        $address_parts = array_filter([
            $house_no,
            $road_no,
            $area,
            trim((string)($_POST['thana_name'] ?? '')),
            trim((string)($_POST['district_name'] ?? '')),
        ]);

        $payload = [
            'division_id' => $division_id,
            'district_id' => $district_id,
            'thana_id' => $thana_id,
            'area' => $area,
            'road_no' => $road_no,
            'house_no' => $house_no,
            'post_code' => $post_code,
            'map_url' => trim((string)($_POST['map_url'] ?? '')),
            'city' => trim((string)($_POST['district_name'] ?? '')),
            'address' => implode(', ', $address_parts),
        ];
    }

    if ($form_section === 'details') {
        $payload = [
            'services' => trim((string)($_POST['services'] ?? '')),
            'facilities' => trim((string)($_POST['facilities'] ?? '')),
            'departments' => trim((string)($_POST['departments'] ?? '')),
            'appointment_note' => trim((string)($_POST['appointment_note'] ?? '')),
            'video_url' => trim((string)($_POST['video_url'] ?? '')),
        ];

        foreach ($facility_toggles as $field => $label) {
            $payload[$field] = isset($_POST[$field]) ? 1 : 0;
        }
    }

    if ($form_section === 'image') {
        $uploaded_image = hpu_upload_image('image', 'hospitals');
        $uploaded_cover = hpu_upload_image('cover_image', 'hospitals');

        $payload = [
            'description' => trim((string)($_POST['description'] ?? '')),
        ];

        if ($uploaded_image !== '') {
            $payload['image'] = $uploaded_image;
        }

        if ($uploaded_cover !== '') {
            $payload['cover_image'] = $uploaded_cover;
        }
    }

    if ($form_section === 'seo') {
        $payload = [
            'seo_title' => trim((string)($_POST['seo_title'] ?? '')),
            'seo_description' => trim((string)($_POST['seo_description'] ?? '')),
            'meta_keywords' => trim((string)($_POST['meta_keywords'] ?? '')),
        ];
    }

    $existing_pending = hpu_get_pending_request((int)$user['id'], $hospital_id);
    $existing_pending_data = $existing_pending ? hpu_decode_request_data($existing_pending['request_data'] ?? '') : [];

    $merged_data = hpu_clean_section_payload($payload, $hospital, $existing_pending_data);

    if (!$merged_data) {
        hpu_flash_redirect('error', 'No value changed. Please change at least one value before submitting.', $form_section);
    }

    try {
        hpu_save_pending_request((int)$user['id'], $hospital_id, $merged_data, $existing_pending);
        hpu_flash_redirect('success', 'Update saved. Changed fields will stay pending until admin approval.', $form_section);
    } catch (Throwable $e) {
        hpu_flash_redirect('error', 'Request could not be saved. Please check profile_update_requests table.', $form_section);
    }
}

/*
|--------------------------------------------------------------------------
| Reload Pending Data / Options
|--------------------------------------------------------------------------
*/

$pending_request = hpu_get_pending_request((int)$user['id'], $hospital_id);
$pending_data = $pending_request ? hpu_decode_request_data($pending_request['request_data'] ?? '') : [];
$requests = hpu_get_requests((int)$user['id'], $hospital_id);

$divisions = hpu_options('divisions');
$districts = hpu_options('districts');
$thanas = hpu_options('thanas');

$hospital_name_for_title = trim((string)($hospital['name'] ?? ''));
$hospital_slug_for_view = hpu_display_value('slug', $hospital ?: [], $pending_data);

$page_title = 'Hospital Profile Update';

require_once __DIR__ . '/../includes/header.php';
?>

<link rel="stylesheet" href="<?php echo BASE_URL; ?>/user/assets/css/hospital-profile-update.css">

<div class="hpu-page">

  <?php if (!empty($form_message)): ?>
    <div class="hpu-alert <?= e($form_message_type) ?>">
      <strong><?= $form_message_type === 'success' ? 'Success:' : 'Error:' ?></strong>
      <span><?= e($form_message) ?></span>
    </div>
  <?php endif; ?>

  <?php if (!empty($error)): ?>
    <div class="hpu-alert error">
      <strong>Error:</strong>
      <span><?= e($error) ?></span>
    </div>
  <?php endif; ?>

  <div class="hpu-hero">
    <div>
      <h1><?= e($hospital_name_for_title !== '' ? 'Update ' . $hospital_name_for_title : 'Update Hospital Profile') ?></h1>
      <p>
        Update your hospital profile section by section. Changed fields stay highlighted as Pending while the request is waiting for admin approval.
      </p>
    </div>

    <div class="hpu-hero-actions">
      <a href="dashboard.php" class="hpu-btn">Dashboard</a>

      <?php if ($hospital_slug_for_view !== ''): ?>
        <a href="<?= e(BASE_URL . '/hospital/' . $hospital_slug_for_view) ?>" target="_blank" rel="noopener" class="hpu-btn">View Profile</a>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($error === '' && $hospital): ?>
    <div class="hpu-layout">

      <aside class="hpu-sidebar">
        <div class="hpu-card">
          <div class="hpu-card-head">
            <h3>Edit Sections</h3>
            <p>Choose one section and update only that part.</p>
          </div>

          <div class="hpu-tabs">
            <?php foreach ($sections as $key => $label): ?>
              <?php if ($key === 'requests'): ?>
                <a href="hospital-profile-update.php?section=<?= e($key) ?>" class="hpu-tab <?= $section === $key ? 'active' : '' ?>">
                  <span><?= e($label) ?></span>
                  <span class="hpu-pill"><?= e((string)count($requests)) ?></span>
                </a>
              <?php else: ?>
                <?php $updated_in_section = hpu_section_updated_count($section_fields[$key] ?? [], $hospital, $pending_data); ?>

                <a href="hospital-profile-update.php?section=<?= e($key) ?>" class="hpu-tab <?= $section === $key ? 'active' : '' ?>">
                  <span><?= e($label) ?></span>

                  <?php if ($updated_in_section > 0): ?>
                    <span class="hpu-pill updated"><?= e((string)$updated_in_section) ?></span>
                  <?php endif; ?>
                </a>
              <?php endif; ?>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="hpu-card">
          <div class="hpu-card-head">
            <h3>Pending Summary</h3>
          </div>

          <div class="hpu-card-body">
            <div class="hpu-summary">
              <div class="hpu-summary-row">
                <span>Pending Request</span>
                <strong><?= $pending_request ? '#' . e((string)$pending_request['id']) : 'No' ?></strong>
              </div>

              <div class="hpu-summary-row">
                <span>Pending Fields</span>
                <strong><?= e((string)count($pending_data)) ?></strong>
              </div>

              <div class="hpu-summary-row">
                <span>Status</span>
                <strong><?= $pending_request ? 'Pending' : 'Ready' ?></strong>
              </div>
            </div>
          </div>
        </div>
      </aside>

      <main>
        <?php if ($section !== 'requests'): ?>
          <form class="hpu-card" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="section" value="<?= e($section) ?>">

            <div class="hpu-card-head">
              <h2><?= e($sections[$section]) ?> Information</h2>
              <p>Only changed values will be sent for admin approval.</p>
            </div>

            <div class="hpu-card-body">

              <?php if ($section === 'basic'): ?>
                <div class="hpu-form-grid">
                  <div class="<?= hpu_field_class('name', $hospital, $pending_data) ?>">
                    <label>Hospital Name <?= hpu_badge('name', $hospital, $pending_data) ?></label>
                    <input type="text" name="name" value="<?= e(hpu_display_value('name', $hospital, $pending_data)) ?>" required>
                  </div>

                  <div class="<?= hpu_field_class('slug', $hospital, $pending_data) ?>">
                    <label>Slug <?= hpu_badge('slug', $hospital, $pending_data) ?></label>
                    <input type="text" name="slug" id="slugInput" value="<?= e(hpu_display_value('slug', $hospital, $pending_data)) ?>">
                    <div id="slugStatus"></div>
                  </div>

                  <div class="<?= hpu_field_class('type', $hospital, $pending_data) ?>">
                    <label>Hospital Type <?= hpu_badge('type', $hospital, $pending_data) ?></label>
                    <select name="type">
                      <?php foreach ($hospital_types as $type): ?>
                        <option value="<?= e($type) ?>" <?= hpu_display_value('type', $hospital, $pending_data, 'Private Hospital') === $type ? 'selected' : '' ?>>
                          <?= e($type) ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </div>

                  <div class="<?= hpu_field_class('license_number', $hospital, $pending_data) ?>">
                    <label>License Number <?= hpu_badge('license_number', $hospital, $pending_data) ?></label>
                    <input type="text" name="license_number" value="<?= e(hpu_display_value('license_number', $hospital, $pending_data)) ?>">
                  </div>

                  <div class="<?= hpu_field_class('website_url', $hospital, $pending_data) ?>">
                    <label>Website URL <?= hpu_badge('website_url', $hospital, $pending_data) ?></label>
                    <input type="url" name="website_url" value="<?= e(hpu_display_value('website_url', $hospital, $pending_data)) ?>">
                  </div>

                  <div class="<?= hpu_field_class('opening_hours', $hospital, $pending_data) ?>">
                    <label>Opening Hours <?= hpu_badge('opening_hours', $hospital, $pending_data) ?></label>
                    <input type="text" name="opening_hours" value="<?= e(hpu_display_value('opening_hours', $hospital, $pending_data)) ?>">
                  </div>

                  <div class="<?= hpu_field_class('visiting_hours', $hospital, $pending_data) ?>">
                    <label>Visiting Hours <?= hpu_badge('visiting_hours', $hospital, $pending_data) ?></label>
                    <input type="text" name="visiting_hours" value="<?= e(hpu_display_value('visiting_hours', $hospital, $pending_data)) ?>">
                  </div>

                  <div class="<?= hpu_field_class('bed_count', $hospital, $pending_data) ?>">
                    <label>Bed Count <?= hpu_badge('bed_count', $hospital, $pending_data) ?></label>
                    <input type="number" name="bed_count" value="<?= e(hpu_display_value('bed_count', $hospital, $pending_data, '0')) ?>">
                  </div>

                  <div class="<?= hpu_field_class('established_year', $hospital, $pending_data) ?>">
                    <label>Established Year <?= hpu_badge('established_year', $hospital, $pending_data) ?></label>
                    <input type="number" name="established_year" value="<?= e(hpu_display_value('established_year', $hospital, $pending_data)) ?>">
                  </div>
                </div>
              <?php endif; ?>

              <?php if ($section === 'contact'): ?>
                <div class="hpu-form-grid">
                  <div class="<?= hpu_field_class('phone', $hospital, $pending_data) ?>">
                    <label>Phone <?= hpu_badge('phone', $hospital, $pending_data) ?></label>
                    <input type="text" name="phone" value="<?= e(hpu_display_value('phone', $hospital, $pending_data)) ?>">
                  </div>

                  <div class="<?= hpu_field_class('email', $hospital, $pending_data) ?>">
                    <label>Email <?= hpu_badge('email', $hospital, $pending_data) ?></label>
                    <input type="email" name="email" value="<?= e(hpu_display_value('email', $hospital, $pending_data)) ?>">
                  </div>

                  <div class="<?= hpu_field_class('whatsapp', $hospital, $pending_data) ?>">
                    <label>WhatsApp <?= hpu_badge('whatsapp', $hospital, $pending_data) ?></label>
                    <input type="text" name="whatsapp" value="<?= e(hpu_display_value('whatsapp', $hospital, $pending_data)) ?>">
                  </div>

                  <div class="<?= hpu_field_class('emergency_phone', $hospital, $pending_data) ?>">
                    <label>Emergency Phone <?= hpu_badge('emergency_phone', $hospital, $pending_data) ?></label>
                    <input type="text" name="emergency_phone" value="<?= e(hpu_display_value('emergency_phone', $hospital, $pending_data)) ?>">
                  </div>

                  <div class="<?= hpu_field_class('ambulance_phone', $hospital, $pending_data) ?>">
                    <label>Ambulance Phone <?= hpu_badge('ambulance_phone', $hospital, $pending_data) ?></label>
                    <input type="text" name="ambulance_phone" value="<?= e(hpu_display_value('ambulance_phone', $hospital, $pending_data)) ?>">
                  </div>
                </div>
              <?php endif; ?>

              <?php if ($section === 'address'): ?>
                <div class="hpu-form-grid">
                  <div class="<?= hpu_field_class('division_id', $hospital, $pending_data) ?>">
                    <label>Division <?= hpu_badge('division_id', $hospital, $pending_data) ?></label>
                    <select name="division_id" id="division_id">
                      <option value="">Select Division</option>
                      <?php foreach ($divisions as $division): ?>
                        <option value="<?= e((string)$division['id']) ?>" <?= (int)hpu_display_value('division_id', $hospital, $pending_data) === (int)$division['id'] ? 'selected' : '' ?>>
                          <?= e($division['label'] ?? '') ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                    <input type="hidden" name="division_name" id="division_name" value="">
                  </div>

                  <div class="<?= hpu_field_class('district_id', $hospital, $pending_data) ?>">
                    <label>District <?= hpu_badge('district_id', $hospital, $pending_data) ?></label>
                    <select name="district_id" id="district_id">
                      <option value="">Select District</option>
                      <?php foreach ($districts as $district): ?>
                        <option value="<?= e((string)$district['id']) ?>" data-division="<?= e((string)($district['division_id'] ?? '')) ?>" <?= (int)hpu_display_value('district_id', $hospital, $pending_data) === (int)$district['id'] ? 'selected' : '' ?>>
                          <?= e($district['label'] ?? '') ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                    <input type="hidden" name="district_name" id="district_name" value="">
                  </div>

                  <div class="<?= hpu_field_class('thana_id', $hospital, $pending_data) ?>">
                    <label>Thana / Upazila <?= hpu_badge('thana_id', $hospital, $pending_data) ?></label>
                    <select name="thana_id" id="thana_id">
                      <option value="">Select Thana</option>
                      <?php foreach ($thanas as $thana): ?>
                        <option value="<?= e((string)$thana['id']) ?>" data-district="<?= e((string)($thana['district_id'] ?? '')) ?>" <?= (int)hpu_display_value('thana_id', $hospital, $pending_data) === (int)$thana['id'] ? 'selected' : '' ?>>
                          <?= e($thana['label'] ?? '') ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                    <input type="hidden" name="thana_name" id="thana_name" value="">
                  </div>

                  <div class="<?= hpu_field_class('area', $hospital, $pending_data) ?>">
                    <label>Area / Locality <?= hpu_badge('area', $hospital, $pending_data) ?></label>
                    <input type="text" name="area" value="<?= e(hpu_display_value('area', $hospital, $pending_data)) ?>">
                  </div>

                  <div class="<?= hpu_field_class('road_no', $hospital, $pending_data) ?>">
                    <label>Road / Street <?= hpu_badge('road_no', $hospital, $pending_data) ?></label>
                    <input type="text" name="road_no" value="<?= e(hpu_display_value('road_no', $hospital, $pending_data)) ?>">
                  </div>

                  <div class="<?= hpu_field_class('house_no', $hospital, $pending_data) ?>">
                    <label>House / Building <?= hpu_badge('house_no', $hospital, $pending_data) ?></label>
                    <input type="text" name="house_no" value="<?= e(hpu_display_value('house_no', $hospital, $pending_data)) ?>">
                  </div>

                  <div class="<?= hpu_field_class('post_code', $hospital, $pending_data) ?>">
                    <label>Post Code <?= hpu_badge('post_code', $hospital, $pending_data) ?></label>
                    <input type="text" name="post_code" value="<?= e(hpu_display_value('post_code', $hospital, $pending_data)) ?>">
                  </div>

                  <div class="<?= hpu_field_class('map_url', $hospital, $pending_data) ?> full">
                    <label>Google Map URL <?= hpu_badge('map_url', $hospital, $pending_data) ?></label>
                    <input type="url" name="map_url" value="<?= e(hpu_display_value('map_url', $hospital, $pending_data)) ?>">
                  </div>

                  <div class="<?= hpu_field_class('address', $hospital, $pending_data) ?> full">
                    <label>Current Address <?= hpu_badge('address', $hospital, $pending_data) ?></label>
                    <textarea readonly><?= e(hpu_display_value('address', $hospital, $pending_data)) ?></textarea>
                    <div class="hpu-small">Address will be rebuilt from house, road, area, thana and district after approval.</div>
                  </div>
                </div>
              <?php endif; ?>

              <?php if ($section === 'details'): ?>
                <div class="hpu-form-grid">
                  <div class="<?= hpu_field_class('services', $hospital, $pending_data) ?> full">
                    <label>Services <?= hpu_badge('services', $hospital, $pending_data) ?></label>
                    <textarea name="services"><?= e(hpu_display_value('services', $hospital, $pending_data)) ?></textarea>
                  </div>

                  <div class="<?= hpu_field_class('facilities', $hospital, $pending_data) ?> full">
                    <label>Facilities <?= hpu_badge('facilities', $hospital, $pending_data) ?></label>
                    <textarea name="facilities"><?= e(hpu_display_value('facilities', $hospital, $pending_data)) ?></textarea>
                  </div>

                  <div class="<?= hpu_field_class('departments', $hospital, $pending_data) ?> full">
                    <label>Departments <?= hpu_badge('departments', $hospital, $pending_data) ?></label>
                    <textarea name="departments"><?= e(hpu_display_value('departments', $hospital, $pending_data)) ?></textarea>
                  </div>

                  <div class="<?= hpu_field_class('appointment_note', $hospital, $pending_data) ?> full">
                    <label>Appointment Note <?= hpu_badge('appointment_note', $hospital, $pending_data) ?></label>
                    <textarea name="appointment_note"><?= e(hpu_display_value('appointment_note', $hospital, $pending_data)) ?></textarea>
                  </div>

                  <div class="<?= hpu_field_class('video_url', $hospital, $pending_data) ?> full">
                    <label>Video URL <?= hpu_badge('video_url', $hospital, $pending_data) ?></label>
                    <input type="url" name="video_url" value="<?= e(hpu_display_value('video_url', $hospital, $pending_data)) ?>">
                  </div>

                  <div class="hpu-field full">
                    <label>Available Facilities</label>

                    <div class="hpu-checks">
                      <?php foreach ($facility_toggles as $field => $label): ?>
                        <label class="hpu-check">
                          <input type="checkbox" name="<?= e($field) ?>" <?= hpu_bool_value($field, $hospital, $pending_data) ? 'checked' : '' ?>>
                          <span><?= e($label) ?></span>
                          <?= hpu_badge($field, $hospital, $pending_data) ?>
                        </label>
                      <?php endforeach; ?>
                    </div>
                  </div>
                </div>
              <?php endif; ?>

              <?php if ($section === 'image'): ?>
                <div class="hpu-form-grid">
                  <div class="<?= hpu_field_class('image', $hospital, $pending_data) ?>">
                    <label>Hospital Image / Logo <?= hpu_badge('image', $hospital, $pending_data) ?></label>
                    <input type="file" name="image" accept="image/jpeg,image/png,image/webp">
                    <?php if (hpu_display_value('image', $hospital, $pending_data) !== ''): ?>
                      <a class="hpu-small" href="<?= e(hpu_file_url(hpu_display_value('image', $hospital, $pending_data))) ?>" target="_blank">View current image</a>
                    <?php endif; ?>
                  </div>

                  <div class="<?= hpu_field_class('cover_image', $hospital, $pending_data) ?>">
                    <label>Cover Image <?= hpu_badge('cover_image', $hospital, $pending_data) ?></label>
                    <input type="file" name="cover_image" accept="image/jpeg,image/png,image/webp">
                    <?php if (hpu_display_value('cover_image', $hospital, $pending_data) !== ''): ?>
                      <a class="hpu-small" href="<?= e(hpu_file_url(hpu_display_value('cover_image', $hospital, $pending_data))) ?>" target="_blank">View current cover image</a>
                    <?php endif; ?>
                  </div>

                  <div class="<?= hpu_field_class('description', $hospital, $pending_data) ?> full">
                    <label>Description <?= hpu_badge('description', $hospital, $pending_data) ?></label>
                    <textarea name="description"><?= e(hpu_display_value('description', $hospital, $pending_data)) ?></textarea>
                  </div>
                </div>
              <?php endif; ?>

              <?php if ($section === 'seo'): ?>
                <div class="hpu-form-grid">
                  <div class="<?= hpu_field_class('seo_title', $hospital, $pending_data) ?> full">
                    <label>SEO Title <?= hpu_badge('seo_title', $hospital, $pending_data) ?></label>
                    <input type="text" name="seo_title" value="<?= e(hpu_display_value('seo_title', $hospital, $pending_data)) ?>">
                  </div>

                  <div class="<?= hpu_field_class('seo_description', $hospital, $pending_data) ?> full">
                    <label>SEO Description <?= hpu_badge('seo_description', $hospital, $pending_data) ?></label>
                    <textarea name="seo_description"><?= e(hpu_display_value('seo_description', $hospital, $pending_data)) ?></textarea>
                  </div>

                  <div class="<?= hpu_field_class('meta_keywords', $hospital, $pending_data) ?> full">
                    <label>Meta Keywords <?= hpu_badge('meta_keywords', $hospital, $pending_data) ?></label>
                    <textarea name="meta_keywords"><?= e(hpu_display_value('meta_keywords', $hospital, $pending_data)) ?></textarea>
                  </div>
                </div>
              <?php endif; ?>

              <div class="hpu-submit">
                <span class="hpu-small">Only changed values will be saved as pending.</span>
                <button type="submit" class="hpu-btn hpu-btn-primary">Save This Section</button>
              </div>
            </div>
          </form>
        <?php else: ?>
          <div class="hpu-card">
            <div class="hpu-card-head">
              <h2>Update Requests</h2>
              <p>Recent hospital profile update requests.</p>
            </div>

            <div class="hpu-table-wrap">
              <table class="hpu-table">
                <thead>
                  <tr>
                    <th>ID</th>
                    <th>Status</th>
                    <th>Fields</th>
                    <th>Admin Note</th>
                    <th>Created</th>
                  </tr>
                </thead>

                <tbody>
                  <?php if (!$requests): ?>
                    <tr>
                      <td colspan="5">No request found.</td>
                    </tr>
                  <?php endif; ?>

                  <?php foreach ($requests as $request): ?>
                    <?php
                      $request_data = hpu_decode_request_data($request['request_data'] ?? '');
                      $status = strtolower((string)($request['status'] ?? 'pending'));
                    ?>
                    <tr>
                      <td>#<?= e((string)$request['id']) ?></td>
                      <td><span class="hpu-status <?= e($status) ?>"><?= e(ucfirst($status)) ?></span></td>
                      <td><?= e((string)count($request_data)) ?></td>
                      <td><?= e((string)($request['admin_note'] ?? '—')) ?></td>
                      <td><?= !empty($request['created_at']) ? e(date('d M Y h:i A', strtotime((string)$request['created_at']))) : '—' ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        <?php endif; ?>
      </main>
    </div>
  <?php endif; ?>
</div>

<script src="<?php echo BASE_URL; ?>/user/assets/js/hospital-profile-update.js" defer></script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>