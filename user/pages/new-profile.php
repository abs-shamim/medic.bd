<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

$user = require_user_login();

if (user_has_claim($user)) {
    redirect('profile-update.php');
}

$user_type = current_user_type();

if ($user_type !== 'doctor') {
    $page_title = 'Create New Profile';
    require_once __DIR__ . '/../includes/header.php';
    ?>
    <div class="user-card">
        <h1>Create New Profile</h1>
        <div class="user-alert error">
            This page is currently available for doctor profile creation only.
        </div>
        <a class="user-btn user-btn-light" href="dashboard.php">Back to Dashboard</a>
    </div>
    <?php
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

$form_message = '';
$form_message_type = '';
$errors = [];

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

function np_table_exists(string $table): bool
{
    global $pdo;

    if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) {
        return false;
    }

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

function np_column_exists(string $table, string $column): bool
{
    global $pdo;

    if (
        !preg_match('/^[A-Za-z0-9_]+$/', $table) ||
        !preg_match('/^[A-Za-z0-9_]+$/', $column)
    ) {
        return false;
    }

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

function np_label_column(string $table): string
{
    foreach (['name', 'name_en', 'title'] as $column) {
        if (np_column_exists($table, $column)) {
            return $column;
        }
    }

    return 'id';
}

function np_options(string $table): array
{
    global $pdo;

    $allowed = ['specialties', 'divisions', 'districts', 'thanas'];

    if (!in_array($table, $allowed, true) || !np_table_exists($table)) {
        return [];
    }

    try {
        $label = np_label_column($table);
        $select = [
            'id',
            "`{$label}` AS label",
        ];

        if (np_column_exists($table, 'name_bn')) {
            $select[] = 'name_bn AS label_bn';
        } else {
            $select[] = "'' AS label_bn";
        }

        if ($table === 'districts' && np_column_exists('districts', 'division_id')) {
            $select[] = 'division_id';
        }

        if ($table === 'thanas' && np_column_exists('thanas', 'district_id')) {
            $select[] = 'district_id';
        }

        $where = '';

        if (np_column_exists($table, 'status')) {
            $where = "WHERE status IS NULL OR status = '' OR status = 'active'";
        }

        $stmt = $pdo->query("
            SELECT " . implode(', ', $select) . "
            FROM `{$table}`
            {$where}
            ORDER BY `{$label}` ASC
            LIMIT 3000
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

function np_old(string $field, string $default = ''): string
{
    global $pending_create_data;

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        return trim((string)($_POST[$field] ?? $default));
    }

    if (isset($pending_create_data) && is_array($pending_create_data) && array_key_exists($field, $pending_create_data)) {
        return trim((string)$pending_create_data[$field]);
    }

    return $default;
}

function np_checked(string $field): bool
{
    return isset($_POST[$field]);
}

function np_csrf_token(): string
{
    if (function_exists('csrf_token')) {
        return (string)csrf_token();
    }

    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return (string)$_SESSION['csrf_token'];
}

function np_verify_csrf(): bool
{
    if (function_exists('verify_csrf_token')) {
        return (bool)verify_csrf_token();
    }

    $session_token = (string)($_SESSION['csrf_token'] ?? '');
    $posted_token = (string)($_POST['csrf_token'] ?? '');

    return $session_token !== '' &&
        $posted_token !== '' &&
        hash_equals($session_token, $posted_token);
}

function np_normalize_gender($value): string
{
    return match (strtolower(trim((string)$value))) {
        'male' => 'Male',
        'female' => 'Female',
        'other' => 'Other',
        default => '',
    };
}

function np_gender_bn_from_en(string $gender): string
{
    return match (np_normalize_gender($gender)) {
        'Male' => 'পুরুষ',
        'Female' => 'নারী',
        'Other' => 'অন্যান্য',
        default => '',
    };
}

function np_languages_bn_from_en(string $languages): string
{
    $map = [
        'bangla' => 'বাংলা',
        'bengali' => 'বাংলা',
        'english' => 'ইংরেজি',
        'hindi' => 'হিন্দি',
        'arabic' => 'আরবি',
        'urdu' => 'উর্দু',
    ];

    $parts = preg_split('/\s*[,;|\/]\s*/u', trim($languages)) ?: [];
    $translated = [];

    foreach ($parts as $part) {
        $part = trim((string)$part);

        if ($part === '') {
            continue;
        }

        $key = function_exists('mb_strtolower')
            ? mb_strtolower($part, 'UTF-8')
            : strtolower($part);

        $translated[] = $map[$key] ?? $part;
    }

    return implode(', ', array_values(array_unique($translated)));
}

function np_normalize_slug(string $slug): string
{
    $slug = strtolower(trim($slug));
    $slug = preg_replace('/[^a-z0-9-]+/', '-', $slug);
    $slug = preg_replace('/-+/', '-', (string)$slug);

    return trim((string)$slug, '-');
}

function np_validate_slug(string $slug): array
{
    if ($slug === '') {
        return ['valid' => false, 'message' => 'Slug cannot be empty.'];
    }

    if (strlen($slug) < 6 || strlen($slug) > 70) {
        return ['valid' => false, 'message' => 'Slug must be between 6 and 70 characters.'];
    }

    if (!preg_match('/^[a-z0-9-]+$/', $slug)) {
        return ['valid' => false, 'message' => 'Slug can contain lowercase letters, numbers and hyphens only.'];
    }

    if (str_starts_with($slug, '-') || str_ends_with($slug, '-') || str_contains($slug, '--')) {
        return ['valid' => false, 'message' => 'Slug has an invalid hyphen format.'];
    }

    return ['valid' => true, 'message' => 'Slug format is valid.'];
}

function np_slug_exists(string $slug): bool
{
    global $pdo;

    if (!np_table_exists('doctors') || !np_column_exists('doctors', 'slug')) {
        return false;
    }

    try {
        $stmt = $pdo->prepare("SELECT id FROM doctors WHERE slug = :slug LIMIT 1");
        $stmt->execute([':slug' => $slug]);

        return (bool)$stmt->fetchColumn();
    } catch (Throwable $e) {
        return true;
    }
}

function np_slug_check_payload(string $slug): array
{
    $slug = np_normalize_slug($slug);
    $validation = np_validate_slug($slug);

    if (!$validation['valid']) {
        return [
            'ok' => false,
            'available' => false,
            'slug' => $slug,
            'message' => $validation['message'],
        ];
    }

    if (np_slug_exists($slug)) {
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

function np_json_response(array $payload): void
{
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }

    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function np_safe_file_name(string $text): string
{
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9]+/i', '-', $text);
    $text = trim((string)$text, '-');

    return $text !== '' ? $text : 'doctor';
}

function np_public_upload_dir(string $folder = 'doctors'): array
{
    $folder = trim($folder, '/');
    $relative = 'assets/uploads/' . $folder . '/';
    $document_root = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/\\');

    $candidates = [];

    if ($document_root !== '') {
        $candidates[] = $document_root . '/' . $relative;
    }

    $candidates[] = dirname(__DIR__) . '/' . $relative;
    $candidates[] = dirname(__DIR__, 2) . '/' . $relative;

    foreach (array_values(array_unique($candidates)) as $dir) {
        $dir = rtrim($dir, '/\\') . '/';

        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        if (is_dir($dir) && is_writable($dir)) {
            return [$dir, $relative];
        }
    }

    return ['', $relative];
}

function np_upload_image(string $field, string $base_name = 'doctor', string $suffix = ''): array
{
    if (
        !isset($_FILES[$field]) ||
        trim((string)($_FILES[$field]['name'] ?? '')) === '' ||
        (int)($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE
    ) {
        return ['path' => '', 'error' => ''];
    }

    $file = $_FILES[$field];
    $error_code = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
    $tmp_name = (string)($file['tmp_name'] ?? '');
    $size = (int)($file['size'] ?? 0);

    if ($error_code !== UPLOAD_ERR_OK) {
        return ['path' => '', 'error' => 'Image upload failed. Please try again.'];
    }

    if ($tmp_name === '' || !is_uploaded_file($tmp_name)) {
        return ['path' => '', 'error' => 'Uploaded image temporary file was not found.'];
    }

    if ($size <= 0 || $size > 8 * 1024 * 1024) {
        return ['path' => '', 'error' => 'Image must be smaller than 8MB.'];
    }

    $info = @getimagesize($tmp_name);
    $mime = strtolower((string)($info['mime'] ?? ''));

    $extension_map = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    if (!$info || !isset($extension_map[$mime])) {
        return ['path' => '', 'error' => 'Only valid JPG, PNG and WebP images are allowed.'];
    }

    [$upload_dir, $relative] = np_public_upload_dir('doctors');

    if ($upload_dir === '') {
        return ['path' => '', 'error' => 'Doctor image upload folder is not writable.'];
    }

    $file_name = 'pending-' .
        np_safe_file_name($base_name) .
        ($suffix !== '' ? '-' . np_safe_file_name($suffix) : '') .
        '-' . date('ymdHis') .
        '-' . substr(bin2hex(random_bytes(4)), 0, 8) .
        '.' . $extension_map[$mime];

    $target = $upload_dir . $file_name;

    if (!@move_uploaded_file($tmp_name, $target)) {
        return ['path' => '', 'error' => 'Image could not be saved to the upload folder.'];
    }

    @chmod($target, 0644);

    return ['path' => $relative . $file_name, 'error' => ''];
}

function np_payload_json(array $data): string
{
    return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
}

function np_decode_json(?string $json): array
{
    $data = json_decode((string)$json, true);

    return is_array($data) ? $data : [];
}

function np_get_pending_create_request(int $user_id): ?array
{
    global $pdo;

    if (!np_table_exists('profile_update_requests')) {
        return null;
    }

    try {
        $stmt = $pdo->prepare("
            SELECT *
            FROM profile_update_requests
            WHERE user_id = :user_id
              AND request_type = 'doctor_profile_create'
              AND status = 'pending'
            ORDER BY id DESC
            LIMIT 1
        ");
        $stmt->execute([':user_id' => $user_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    } catch (Throwable $e) {
        return null;
    }
}

function np_find_option(array $rows, int $id): array
{
    foreach ($rows as $row) {
        if ((int)($row['id'] ?? 0) === $id) {
            return $row;
        }
    }

    return [];
}

function np_text(string $value): string
{
    return trim(preg_replace('/\s+/u', ' ', $value));
}

function np_join_unique(array $values, string $separator = ', '): string
{
    $clean = [];

    foreach ($values as $value) {
        $value = np_text((string)$value);

        if ($value === '') {
            continue;
        }

        $key = function_exists('mb_strtolower')
            ? mb_strtolower($value, 'UTF-8')
            : strtolower($value);

        $clean[$key] = $value;
    }

    return implode($separator, array_values($clean));
}

function np_limit_text(string $text, int $limit): string
{
    $text = np_text($text);

    if ($limit <= 0) {
        return $text;
    }

    $length = function_exists('mb_strlen') ? mb_strlen($text, 'UTF-8') : strlen($text);

    if ($length <= $limit) {
        return $text;
    }

    return function_exists('mb_substr')
        ? rtrim(mb_substr($text, 0, $limit - 1, 'UTF-8')) . '…'
        : rtrim(substr($text, 0, $limit - 1)) . '…';
}

function np_normalize_seo_mode($mode): string
{
    return trim((string)$mode) === 'manual' ? 'manual' : 'auto';
}

function np_generate_auto_seo(
    array $source,
    array $specialties,
    array $districts
): array {
    $specialty = np_find_option($specialties, (int)($source['specialty_id'] ?? 0));
    $district = np_find_option($districts, (int)($source['doctor_district_id'] ?? 0));

    $name = np_text((string)($source['name'] ?? ''));
    $name_bn = np_text((string)($source['name_bn'] ?? ''));
    $specialty_en = np_text((string)($specialty['label'] ?? ''));
    $specialty_bn = np_text((string)($specialty['label_bn'] ?? ''));
    $district_en = np_text((string)($district['label'] ?? ''));
    $district_bn = np_text((string)($district['label_bn'] ?? ''));

    if ($name_bn === '') {
        $name_bn = $name;
    }

    if ($specialty_bn === '') {
        $specialty_bn = $specialty_en;
    }

    if ($district_bn === '') {
        $district_bn = $district_en;
    }

    $title = np_join_unique([$name, $specialty_en, $district_en], ' - ');
    $title_bn = np_join_unique([$name_bn, $specialty_bn, $district_bn], ' - ');

    $description = $title !== ''
        ? np_join_unique([$name, $specialty_en, $district_en], ', ') .
            '. View doctor profile, specialty, location, appointment information, consultation fee and schedule.'
        : '';

    $description_bn = $title_bn !== ''
        ? np_join_unique([$name_bn, $specialty_bn, $district_bn], ', ') .
            ' এর ডাক্তার প্রোফাইল, বিশেষজ্ঞতা, অবস্থান, অ্যাপয়েন্টমেন্ট তথ্য, কনসালটেশন ফি ও সময়সূচি দেখুন।'
        : '';

    $keywords = np_join_unique([
        $name,
        $specialty_en,
        $district_en,
        $name !== '' && $specialty_en !== '' ? $name . ' ' . $specialty_en : '',
        $specialty_en !== '' && $district_en !== '' ? $specialty_en . ' doctor in ' . $district_en : '',
    ]);

    $keywords_bn = np_join_unique([
        $name_bn,
        $specialty_bn,
        $district_bn,
        $specialty_bn !== '' && $district_bn !== '' ? $district_bn . ' এর ' . $specialty_bn . ' ডাক্তার' : '',
    ]);

    return [
        'seo_title' => np_limit_text($title, 160),
        'seo_title_bn' => np_limit_text($title_bn, 160),
        'seo_description' => np_limit_text($description, 300),
        'seo_description_bn' => np_limit_text($description_bn, 300),
        'meta_keywords' => np_limit_text($keywords, 255),
        'meta_keywords_bn' => np_limit_text($keywords_bn, 255),
    ];
}

/*
|--------------------------------------------------------------------------
| Data
|--------------------------------------------------------------------------
*/

$specialties = np_options('specialties');
$divisions = np_options('divisions');
$districts = np_options('districts');
$thanas = np_options('thanas');

if (($_GET['ajax'] ?? '') === 'check_slug') {
    np_json_response(np_slug_check_payload((string)($_GET['slug'] ?? '')));
}

$user_id = (int)($user['id'] ?? 0);
$pending_create_request = np_get_pending_create_request($user_id);
$pending_create_data = $pending_create_request
    ? np_decode_json($pending_create_request['request_data'] ?? '')
    : [];


/*
|--------------------------------------------------------------------------
| Submit
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!np_verify_csrf()) {
        $errors[] = 'Security token expired. Please refresh the page and try again.';
    }

    $name = trim((string)($_POST['name'] ?? ''));
    $degree = trim((string)($_POST['degree'] ?? ''));
    $designation = trim((string)($_POST['designation'] ?? ''));
    $specialty_id = (int)($_POST['specialty_id'] ?? 0);
    $gender = np_normalize_gender($_POST['gender'] ?? '');
    $primary_hospital = trim((string)($_POST['primary_hospital'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));

    $required = [];

    if ($name === '') $required[] = 'Doctor Name';
    if ($degree === '') $required[] = 'Degree';
    if ($designation === '') $required[] = 'Designation';
    if ($specialty_id <= 0) $required[] = 'Specialty';
    if ($gender === '') $required[] = 'Gender';
    if ($primary_hospital === '') $required[] = 'Primary Hospital';

    if ($required) {
        $errors[] = 'Please fill required fields: ' . implode(', ', $required) . '.';
    }

    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }

    $slug_source = trim((string)($_POST['slug'] ?? ''));

    if ($slug_source === '') {
        $slug_source = $name;
    }

    $slug = np_normalize_slug($slug_source);

    if (strlen($slug) < 6) {
        $slug = np_normalize_slug('doctor-' . $slug);
    }

    $slug_check = np_slug_check_payload($slug);

    if (!$slug_check['ok']) {
        $errors[] = $slug_check['message'];
    }

    $image_result = np_upload_image('image', $name);
    $og_image_result = np_upload_image('og_image', $name, 'og');

    if ($image_result['error'] !== '') {
        $errors[] = $image_result['error'];
    }

    if ($og_image_result['error'] !== '') {
        $errors[] = $og_image_result['error'];
    }

    $image = $image_result['path'] !== ''
        ? $image_result['path']
        : trim((string)($pending_create_data['image'] ?? ''));

    $og_image = $og_image_result['path'] !== ''
        ? $og_image_result['path']
        : trim((string)($pending_create_data['og_image'] ?? ''));

    $auto_seo = np_generate_auto_seo($_POST, $specialties, $districts);

    $seo_title_mode = np_normalize_seo_mode($_POST['seo_title_mode'] ?? 'auto');
    $seo_title_bn_mode = np_normalize_seo_mode($_POST['seo_title_bn_mode'] ?? 'auto');
    $seo_description_mode = np_normalize_seo_mode($_POST['seo_description_mode'] ?? 'auto');
    $seo_description_bn_mode = np_normalize_seo_mode($_POST['seo_description_bn_mode'] ?? 'auto');
    $meta_keywords_mode = np_normalize_seo_mode($_POST['meta_keywords_mode'] ?? 'auto');
    $meta_keywords_bn_mode = np_normalize_seo_mode($_POST['meta_keywords_bn_mode'] ?? 'auto');

    $seo_title = $seo_title_mode === 'manual'
        ? trim((string)($_POST['seo_title'] ?? ''))
        : $auto_seo['seo_title'];

    $seo_title_bn = $seo_title_bn_mode === 'manual'
        ? trim((string)($_POST['seo_title_bn'] ?? ''))
        : $auto_seo['seo_title_bn'];

    $seo_description = $seo_description_mode === 'manual'
        ? trim((string)($_POST['seo_description'] ?? ''))
        : $auto_seo['seo_description'];

    $seo_description_bn = $seo_description_bn_mode === 'manual'
        ? trim((string)($_POST['seo_description_bn'] ?? ''))
        : $auto_seo['seo_description_bn'];

    $meta_keywords = $meta_keywords_mode === 'manual'
        ? trim((string)($_POST['meta_keywords'] ?? ''))
        : $auto_seo['meta_keywords'];

    $meta_keywords_bn = $meta_keywords_bn_mode === 'manual'
        ? trim((string)($_POST['meta_keywords_bn'] ?? ''))
        : $auto_seo['meta_keywords_bn'];

    $payload = [
        'name' => $name,
        'name_bn' => trim((string)($_POST['name_bn'] ?? '')),
        'slug' => $slug,

        'degree' => $degree,
        'degree_bn' => trim((string)($_POST['degree_bn'] ?? '')),
        'designation' => $designation,
        'designation_bn' => trim((string)($_POST['designation_bn'] ?? '')),
        'bmdc_number' => trim((string)($_POST['bmdc_number'] ?? '')),

        'gender' => $gender,
        'gender_bn' => np_gender_bn_from_en($gender),
        'languages' => trim((string)($_POST['languages'] ?? '')),
        'languages_bn' => np_languages_bn_from_en(trim((string)($_POST['languages'] ?? ''))),

        'specialty_id' => $specialty_id,
        'primary_hospital' => $primary_hospital,
        'primary_hospital_bn' => trim((string)($_POST['primary_hospital_bn'] ?? '')),

        'experience_years' => trim((string)($_POST['experience_years'] ?? '')) === ''
            ? null
            : (int)$_POST['experience_years'],

        'consultation_fee' => (float)($_POST['consultation_fee'] ?? 0),
        'follow_up_fee' => (float)($_POST['follow_up_fee'] ?? 0),
        'video_consultation_fee' => (float)($_POST['video_consultation_fee'] ?? 0),
        'serial_no' => trim((string)($_POST['serial_no'] ?? '')),

        'phone' => trim((string)($_POST['phone'] ?? '')),
        'whatsapp' => trim((string)($_POST['whatsapp'] ?? '')),
        'email' => $email,

        'doctor_location_source' => 'manual',
        'doctor_division_id' => (int)($_POST['doctor_division_id'] ?? 0),
        'doctor_district_id' => (int)($_POST['doctor_district_id'] ?? 0),
        'doctor_thana_id' => (int)($_POST['doctor_thana_id'] ?? 0),

        'doctor_division' => trim((string)($_POST['doctor_division_name'] ?? '')),
        'doctor_district' => trim((string)($_POST['doctor_district_name'] ?? '')),
        'doctor_thana' => trim((string)($_POST['doctor_thana_name'] ?? '')),

        'image' => $image,
        'og_image' => $og_image,
        'bio' => trim((string)($_POST['bio'] ?? '')),
        'bio_bn' => trim((string)($_POST['bio_bn'] ?? '')),

        'education' => trim((string)($_POST['education'] ?? '')),
        'education_bn' => trim((string)($_POST['education_bn'] ?? '')),
        'training' => trim((string)($_POST['training'] ?? '')),
        'training_bn' => trim((string)($_POST['training_bn'] ?? '')),
        'fellowship' => trim((string)($_POST['fellowship'] ?? '')),
        'fellowship_bn' => trim((string)($_POST['fellowship_bn'] ?? '')),
        'expertise' => trim((string)($_POST['expertise'] ?? '')),
        'expertise_bn' => trim((string)($_POST['expertise_bn'] ?? '')),

        'appointment_note' => trim((string)($_POST['appointment_note'] ?? '')),
        'appointment_note_bn' => trim((string)($_POST['appointment_note_bn'] ?? '')),

        'rating' => 0,
        'reviews_count' => 0,
        'is_verified' => 0,
        'is_featured' => 0,
        'emergency_available' => np_checked('emergency_available') ? 1 : 0,
        'online_consultation' => np_checked('online_consultation') ? 1 : 0,
        'home_visit' => np_checked('home_visit') ? 1 : 0,
        'status' => 'active',

        'seo_title' => np_limit_text($seo_title, 160),
        'seo_title_bn' => np_limit_text($seo_title_bn, 160),
        'seo_description' => np_limit_text($seo_description, 300),
        'seo_description_bn' => np_limit_text($seo_description_bn, 300),
        'meta_keywords' => np_limit_text($meta_keywords, 255),
        'meta_keywords_bn' => np_limit_text($meta_keywords_bn, 255),

        'seo_title_mode' => $seo_title_mode,
        'seo_title_bn_mode' => $seo_title_bn_mode,
        'seo_description_mode' => $seo_description_mode,
        'seo_description_bn_mode' => $seo_description_bn_mode,
        'meta_keywords_mode' => $meta_keywords_mode,
        'meta_keywords_bn_mode' => $meta_keywords_bn_mode,
    ];

    if (!$errors) {
        try {
            if ($pending_create_request) {
                $updated_at_sql = np_column_exists('profile_update_requests', 'updated_at')
                    ? ', updated_at = NOW()'
                    : '';

                $stmt = $pdo->prepare("
                    UPDATE profile_update_requests
                    SET request_data = :request_data
                        {$updated_at_sql}
                    WHERE id = :id
                      AND user_id = :user_id
                      AND status = 'pending'
                    LIMIT 1
                ");

                $stmt->execute([
                    ':request_data' => np_payload_json($payload),
                    ':id' => (int)$pending_create_request['id'],
                    ':user_id' => $user_id,
                ]);

                $form_message = 'Your pending doctor profile request was updated successfully.';
            } else {
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
                        'doctor_profile_create',
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
                    ':request_data' => np_payload_json($payload),
                ]);

                $form_message = 'Doctor profile request submitted successfully. Admin approval is required before publishing.';
            }

            $form_message_type = 'success';
            $pending_create_request = np_get_pending_create_request($user_id);
            $pending_create_data = $pending_create_request
                ? np_decode_json($pending_create_request['request_data'] ?? '')
                : [];
        } catch (Throwable $e) {
            $form_message_type = 'error';
            $form_message = 'Request could not be saved. Please try again or contact the admin.';
        }
    } else {
        $form_message_type = 'error';
        $form_message = implode(' ', array_values(array_unique($errors)));
    }
}

$requests = [];

try {
    if (np_table_exists('profile_update_requests')) {
        $stmt = $pdo->prepare("
            SELECT *
            FROM profile_update_requests
            WHERE user_id = :user_id
              AND request_type = 'doctor_profile_create'
            ORDER BY id DESC
            LIMIT 20
        ");
        $stmt->execute([':user_id' => $user_id]);
        $requests = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
} catch (Throwable $e) {
    $requests = [];
}

$csrf_token = np_csrf_token();

$auto_seo_preview = np_generate_auto_seo([
    'name' => np_old('name'),
    'name_bn' => np_old('name_bn'),
    'specialty_id' => (int)np_old('specialty_id'),
    'doctor_district_id' => (int)np_old('doctor_district_id'),
], $specialties, $districts);

$page_title = 'Create New Doctor Profile';
require_once __DIR__ . '/../includes/header.php';
?>

<style>
  :root {
    --np-bg:#f5f7fb;
    --np-card:#ffffff;
    --np-text:#172033;
    --np-muted:#667085;
    --np-border:#d9e1ec;
    --np-border-strong:#c8d3e0;
    --np-blue:#0969da;
    --np-green:#1f883d;
    --np-green-soft:#dafbe1;
    --np-yellow:#9a6700;
    --np-yellow-soft:#fff8c5;
    --np-red:#cf222e;
    --np-red-soft:#ffebe9;
    --np-shadow:0 18px 48px rgba(15,23,42,.08);
  }

  body { background:var(--np-bg); }

  .np-page,
  .np-page * { box-sizing:border-box; }

  .np-page {
    max-width:1240px;
    margin:0 auto;
    padding:18px 14px 40px;
    color:var(--np-text);
    font-family:Inter,"Noto Sans Bengali",system-ui,sans-serif;
  }

  .np-alert {
    display:flex;
    align-items:flex-start;
    gap:13px;
    margin-bottom:16px;
    padding:14px 16px;
    border:1px solid var(--np-border);
    border-radius:12px;
    box-shadow:0 1px 0 rgba(27,31,36,.04);
  }

  .np-alert.success {
    color:#116329;
    border-color:rgba(31,136,61,.25);
    background:var(--np-green-soft);
  }

  .np-alert.error {
    color:var(--np-red);
    border-color:rgba(207,34,46,.25);
    background:var(--np-red-soft);
  }

  .np-hero {
    display:flex;
    justify-content:space-between;
    align-items:flex-start;
    gap:22px;
    margin-bottom:18px;
    padding:24px;
    border:1px solid var(--np-border);
    border-radius:16px;
    background:
      radial-gradient(circle at top right,rgba(9,105,218,.10),transparent 32%),
      linear-gradient(135deg,#ffffff,#f8fbff);
    box-shadow:var(--np-shadow);
  }

  .np-breadcrumb {
    display:inline-flex;
    align-items:center;
    gap:7px;
    margin-bottom:12px;
    padding:6px 9px;
    border:1px solid var(--np-border);
    border-radius:999px;
    background:#f6f8fa;
    color:var(--np-muted);
    font-size:12px;
  }

  .np-breadcrumb a { color:var(--np-blue); text-decoration:none; }

  .np-hero h1 {
    margin:0;
    font-size:clamp(27px,3vw,40px);
    line-height:1.12;
    letter-spacing:-.04em;
  }

  .np-hero p {
    max-width:780px;
    margin:10px 0 0;
    color:var(--np-muted);
    font-size:14px;
    line-height:1.75;
  }

  .np-actions {
    display:flex;
    align-items:center;
    justify-content:flex-end;
    gap:9px;
    flex-wrap:wrap;
  }

  .np-btn {
    display:inline-flex;
    align-items:center;
    justify-content:center;
    min-height:40px;
    padding:9px 14px;
    border:1px solid rgba(27,31,36,.15);
    border-radius:7px;
    background:#f6f8fa;
    color:var(--np-text);
    text-decoration:none;
    font-family:inherit;
    font-size:13px;
    font-weight:700;
    cursor:pointer;
  }

  .np-btn:hover { background:#eef1f4; }

  .np-btn-primary {
    color:#fff;
    border-color:#2da44e;
    background:#2da44e;
  }

  .np-btn-primary:hover { color:#fff; background:var(--np-green); }

  .np-card {
    margin-bottom:18px;
    overflow:hidden;
    border:1px solid var(--np-border);
    border-radius:16px;
    background:var(--np-card);
    box-shadow:var(--np-shadow);
  }

  .np-card-head {
    padding:18px 20px;
    border-bottom:1px solid var(--np-border);
    background:#f6f8fa;
  }

  .np-card-head h2 {
    margin:0;
    font-size:19px;
    letter-spacing:-.02em;
  }

  .np-card-head p {
    margin:7px 0 0;
    color:var(--np-muted);
    font-size:13px;
    line-height:1.6;
  }

  .np-form { padding:20px; }

  .np-section {
    margin-bottom:18px;
    padding:18px;
    border:1px solid var(--np-border);
    border-radius:14px;
    background:#fff;
  }

  .np-section:last-child { margin-bottom:0; }

  .np-section-title {
    display:flex;
    align-items:flex-start;
    justify-content:space-between;
    gap:12px;
    margin-bottom:16px;
    padding-bottom:12px;
    border-bottom:1px dashed var(--np-border-strong);
  }

  .np-section-title h3 {
    position:relative;
    margin:0;
    padding-left:13px;
    font-size:16px;
  }

  .np-section-title h3::before {
    content:"";
    position:absolute;
    left:0;
    top:3px;
    width:4px;
    height:17px;
    border-radius:999px;
    background:var(--np-blue);
  }

  .np-section-title span {
    color:var(--np-muted);
    font-size:12.5px;
    line-height:1.5;
    text-align:right;
  }

  .np-grid {
    display:grid;
    grid-template-columns:repeat(2,minmax(0,1fr));
    gap:15px;
  }

  .np-grid.three { grid-template-columns:repeat(3,minmax(0,1fr)); }

  .np-field {
    display:grid;
    gap:7px;
    min-width:0;
  }

  .np-field.full { grid-column:1 / -1; }

  .np-field label,
  .np-check {
    color:#344054;
    font-size:13px;
    font-weight:700;
  }

  .np-field input,
  .np-field select,
  .np-field textarea {
    width:100%;
    min-height:42px;
    border:1px solid var(--np-border-strong);
    border-radius:8px;
    padding:9px 11px;
    background:#fff;
    color:var(--np-text);
    outline:none;
    font-family:inherit;
    font-size:14px;
  }

  .np-field textarea {
    min-height:130px;
    resize:vertical;
    line-height:1.65;
  }

  .np-field input:focus,
  .np-field select:focus,
  .np-field textarea:focus {
    border-color:var(--np-blue);
    box-shadow:0 0 0 3px rgba(9,105,218,.14);
  }

  .np-required { color:var(--np-red); }

  .np-small {
    color:var(--np-muted);
    font-size:12px;
    line-height:1.55;
  }

  .np-language-pair {
    grid-column:1 / -1;
    display:grid;
    grid-template-columns:repeat(2,minmax(0,1fr));
    gap:16px;
    padding:16px;
    border:1px solid #d8e0ea;
    border-radius:14px;
    background:
      linear-gradient(90deg,rgba(9,105,218,.035) 0 50%,rgba(45,164,78,.035) 50% 100%),
      #fff;
  }

  .np-language-heading {
    grid-column:1 / -1;
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:10px;
    color:var(--np-muted);
    font-size:12px;
  }

  .np-language-heading strong {
    color:var(--np-text);
    font-size:13px;
  }

  .np-language-pair .np-field + .np-field {
    padding-left:16px;
    border-left:1px dashed #cbd5e1;
  }

  .np-lang-tag {
    display:inline-flex;
    align-items:center;
    justify-content:center;
    min-width:34px;
    min-height:21px;
    padding:2px 7px;
    border:1px solid #b6d7ff;
    border-radius:999px;
    background:#ddf4ff;
    color:#0550ae;
    font-size:10px;
    font-weight:800;
    letter-spacing:.04em;
  }

  .np-lang-tag.bn {
    border-color:#a7f3d0;
    background:#dafbe1;
    color:#116329;
    letter-spacing:0;
  }

  .np-info {
    grid-column:1 / -1;
    padding:12px 14px;
    border:1px solid #b6d7ff;
    border-radius:10px;
    background:#ddf4ff;
    color:#0550ae;
    font-size:12.5px;
    line-height:1.6;
  }

  .np-inline {
    display:grid;
    grid-template-columns:1fr auto;
    gap:8px;
  }

  #slugStatus {
    display:none;
    padding:8px 10px;
    border-radius:7px;
    font-size:12px;
  }

  #slugStatus.ok {
    display:block;
    color:#116329;
    border:1px solid rgba(31,136,61,.25);
    background:var(--np-green-soft);
  }

  #slugStatus.error {
    display:block;
    color:var(--np-red);
    border:1px solid rgba(207,34,46,.25);
    background:var(--np-red-soft);
  }

  .np-file-box {
    padding:14px;
    border:1px dashed var(--np-border-strong);
    border-radius:10px;
    background:#f8fafc;
  }

  .np-file-box input[type=file] {
    min-height:auto;
    padding:0;
    border:0;
    background:transparent;
  }

  .np-checks {
    display:grid;
    grid-template-columns:repeat(3,minmax(0,1fr));
    gap:10px;
  }

  .np-check {
    display:flex;
    align-items:center;
    gap:8px;
    min-height:42px;
    padding:10px 12px;
    border:1px solid var(--np-border);
    border-radius:9px;
    background:#fff;
  }

  .np-check input {
    width:16px;
    height:16px;
    accent-color:#2da44e;
  }

  .np-label-row {
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:10px;
  }

  .np-seo-mode {
    width:auto!important;
    min-width:88px!important;
    min-height:32px!important;
    padding:5px 8px!important;
    font-size:12px!important;
  }

  .np-submit {
    position:sticky;
    bottom:14px;
    z-index:5;
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:12px;
    margin-top:18px;
    padding:13px;
    border:1px solid var(--np-border);
    border-radius:12px;
    background:rgba(255,255,255,.94);
    box-shadow:0 12px 30px rgba(15,23,42,.10);
    backdrop-filter:blur(12px);
  }

  .np-submit p {
    margin:0;
    color:var(--np-muted);
    font-size:12.5px;
  }

  .np-table-wrap { overflow-x:auto; }

  .np-table {
    width:100%;
    border-collapse:separate;
    border-spacing:0;
  }

  .np-table th,
  .np-table td {
    padding:11px 12px;
    border-bottom:1px solid var(--np-border);
    text-align:left;
    font-size:13px;
  }

  .np-table th {
    background:#f6f8fa;
    color:var(--np-muted);
  }

  .np-status {
    display:inline-flex;
    padding:3px 8px;
    border:1px solid var(--np-border);
    border-radius:999px;
    background:#f6f8fa;
    font-size:12px;
    font-weight:800;
  }

  .np-status.pending { color:var(--np-yellow); background:var(--np-yellow-soft); }
  .np-status.approved { color:#116329; background:var(--np-green-soft); }
  .np-status.rejected { color:var(--np-red); background:var(--np-red-soft); }

  @media(max-width:900px) {
    .np-hero,
    .np-submit,
    .np-section-title {
      flex-direction:column;
      align-items:stretch;
    }

    .np-actions { justify-content:flex-start; }

    .np-grid,
    .np-grid.three,
    .np-checks,
    .np-language-pair {
      grid-template-columns:1fr;
    }

    .np-language-pair {
      background:linear-gradient(180deg,rgba(9,105,218,.035),rgba(45,164,78,.035)),#fff;
    }

    .np-language-pair .np-field + .np-field {
      padding-left:0;
      padding-top:14px;
      border-left:0;
      border-top:1px dashed #cbd5e1;
    }

    .np-inline { grid-template-columns:1fr; }

    .np-btn { width:100%; }
  }
</style>

<div class="np-page">
  <?php if ($form_message !== ''): ?>
    <div class="np-alert <?= e($form_message_type) ?>">
      <strong><?= $form_message_type === 'success' ? 'Success:' : 'Error:' ?></strong>
      <span><?= e($form_message) ?></span>
    </div>
  <?php endif; ?>

  <div class="np-hero">
    <div>
      <div class="np-breadcrumb">
        <a href="dashboard.php">Dashboard</a>
        <span>/</span>
        <span>Create Doctor Profile</span>
      </div>

      <h1>Create New Doctor Profile</h1>
      <p>
        Submit a complete doctor profile for admin approval. English and Bangla fields stay side by side on desktop.
        Hospital linking is handled later from Chambers or by the admin.
      </p>
    </div>

    <div class="np-actions">
      <a href="dashboard.php" class="np-btn">Back to Dashboard</a>
    </div>
  </div>

  <div class="np-card">
    <div class="np-card-head">
      <h2><?= $pending_create_request ? 'Update Pending Profile Request' : 'New Doctor Profile Request' ?></h2>
      <p>
        <?= $pending_create_request
            ? 'A pending request already exists. Saving this form updates that request instead of creating a duplicate.'
            : 'The profile will be published only after admin approval.' ?>
      </p>
    </div>

    <form class="np-form" method="POST" enctype="multipart/form-data">
      <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">
      <input type="hidden" name="doctor_location_source" value="manual">

      <section class="np-section">
        <div class="np-section-title">
          <h3>Basic Information</h3>
          <span>English left · বাংলা right</span>
        </div>

        <div class="np-grid">
          <div class="np-language-pair">
            <div class="np-language-heading">
              <strong>Doctor Name</strong>
              <span>English left · বাংলা right</span>
            </div>

            <div class="np-field">
              <label><span class="np-lang-tag">EN</span> <span class="np-required">*</span></label>
              <input type="text" id="doctorName" name="name" value="<?= e(np_old('name')) ?>" placeholder="Dr. Ahmed Hasan" required>
            </div>

            <div class="np-field">
              <label><span class="np-lang-tag bn">বাংলা</span></label>
              <input type="text" id="doctorNameBn" name="name_bn" value="<?= e(np_old('name_bn')) ?>" placeholder="ডা. আহমেদ হাসান" lang="bn">
            </div>
          </div>

          <div class="np-field full">
            <label>Profile Slug <span class="np-required">*</span></label>
            <div class="np-inline">
              <input type="text" id="slugInput" name="slug" minlength="6" maxlength="70" value="<?= e(np_old('slug')) ?>" placeholder="dr-ahmed-hasan" required>
              <button type="button" class="np-btn" id="checkSlugBtn">Check Availability</button>
            </div>
            <div id="slugStatus"></div>
            <div class="np-small">Lowercase letters, numbers and hyphens only. It is checked again during admin approval.</div>
          </div>

          <?php foreach ([
            ['degree', 'Degree', 'degree_bn', 'ডিগ্রি', true],
            ['designation', 'Designation', 'designation_bn', 'পদবি', true],
            ['primary_hospital', 'Primary Hospital', 'primary_hospital_bn', 'প্রধান হাসপাতাল', true],
          ] as [$en_field, $en_label, $bn_field, $bn_label, $required]): ?>
            <div class="np-language-pair">
              <div class="np-language-heading">
                <strong><?= e($en_label) ?></strong>
                <span>English left · বাংলা right</span>
              </div>

              <div class="np-field">
                <label><span class="np-lang-tag">EN</span> <?= $required ? ' <span class="np-required">*</span>' : '' ?></label>
                <input type="text" name="<?= e($en_field) ?>" value="<?= e(np_old($en_field)) ?>" <?= $required ? 'required' : '' ?>>
              </div>

              <div class="np-field">
                <label><span class="np-lang-tag bn">বাংলা</span> </label>
                <input type="text" name="<?= e($bn_field) ?>" value="<?= e(np_old($bn_field)) ?>" lang="bn">
              </div>
            </div>
          <?php endforeach; ?>

          <div class="np-field">
            <label>BMDC Number</label>
            <input type="text" name="bmdc_number" value="<?= e(np_old('bmdc_number')) ?>" placeholder="BMDC Registration Number">
          </div>

          <div class="np-field">
            <label>Gender <span class="np-required">*</span></label>
            <select name="gender" required>
              <option value="">Select Gender</option>
              <option value="Male" <?= np_old('gender') === 'Male' ? 'selected' : '' ?>>Male</option>
              <option value="Female" <?= np_old('gender') === 'Female' ? 'selected' : '' ?>>Female</option>
              <option value="Other" <?= np_old('gender') === 'Other' ? 'selected' : '' ?>>Other</option>
            </select>
          </div>

          <div class="np-field">
            <label>Languages</label>
            <input type="text" name="languages" value="<?= e(np_old('languages')) ?>" placeholder="Bangla, English, Hindi">
          </div>

          <div class="np-field">
            <label>Specialty <span class="np-required">*</span></label>
            <select name="specialty_id" id="specialtyId" required>
              <option value="">Select Specialty</option>
              <?php foreach ($specialties as $specialty): ?>
                <option
                  value="<?= e((string)$specialty['id']) ?>"
                  data-bn="<?= e((string)($specialty['label_bn'] ?? '')) ?>"
                  <?= np_old('specialty_id') == $specialty['id'] ? 'selected' : '' ?>
                ><?= e($specialty['label'] ?? '') ?></option>
              <?php endforeach; ?>
            </select>
          </div>

        </div>
      </section>

      <section class="np-section">
        <div class="np-section-title">
          <h3>Contact Information</h3>
          <span>Phone, WhatsApp and email</span>
        </div>

        <div class="np-grid">
          <div class="np-field">
            <label>Phone</label>
            <input type="text" name="phone" value="<?= e(np_old('phone')) ?>" placeholder="+880...">
          </div>

          <div class="np-field">
            <label>WhatsApp</label>
            <input type="text" name="whatsapp" value="<?= e(np_old('whatsapp')) ?>" placeholder="+880...">
          </div>

          <div class="np-field full">
            <label>Email</label>
            <input type="email" name="email" value="<?= e(np_old('email')) ?>" placeholder="doctor@example.com">
          </div>
        </div>
      </section>

      <section class="np-section">
        <div class="np-section-title">
          <h3>Location</h3>
          <span>Division, District and Thana / Upazila only</span>
        </div>

        <div class="np-grid three">
          <input type="hidden" name="doctor_division_name" id="doctor_division_name" value="<?= e(np_old('doctor_division_name', np_old('doctor_division'))) ?>">
          <input type="hidden" name="doctor_district_name" id="doctor_district_name" value="<?= e(np_old('doctor_district_name', np_old('doctor_district'))) ?>">
          <input type="hidden" name="doctor_thana_name" id="doctor_thana_name" value="<?= e(np_old('doctor_thana_name', np_old('doctor_thana'))) ?>">

          <div class="np-field">
            <label>Division</label>
            <select name="doctor_division_id" id="doctor_division_id">
              <option value="">Select Division</option>
              <?php foreach ($divisions as $division): ?>
                <option
                  value="<?= e((string)$division['id']) ?>"
                  data-bn="<?= e((string)($division['label_bn'] ?? '')) ?>"
                  <?= np_old('doctor_division_id') == $division['id'] ? 'selected' : '' ?>
                ><?= e($division['label'] ?? '') ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="np-field">
            <label>District</label>
            <select name="doctor_district_id" id="doctor_district_id" data-current="<?= e(np_old('doctor_district_id')) ?>" disabled>
              <option value="">Select District</option>
            </select>
          </div>

          <div class="np-field">
            <label>Thana / Upazila</label>
            <select name="doctor_thana_id" id="doctor_thana_id" data-current="<?= e(np_old('doctor_thana_id')) ?>" disabled>
              <option value="">Select Thana / Upazila</option>
            </select>
          </div>
        </div>
      </section>

      <section class="np-section">
        <div class="np-section-title">
          <h3>Professional Details</h3>
          <span>Experience, fees, education, training, fellowship and expertise</span>
        </div>

        <div class="np-grid three">
          <div class="np-field">
            <label>Starting Year</label>
            <input type="number" name="experience_years" min="1900" max="<?= e(date('Y')) ?>" value="<?= e(np_old('experience_years')) ?>" placeholder="2010">
          </div>

          <div class="np-field">
            <label>Consultation Fee</label>
            <input type="number" step="0.01" min="0" name="consultation_fee" value="<?= e(np_old('consultation_fee')) ?>" placeholder="1000">
          </div>

          <div class="np-field">
            <label>Follow-up Fee</label>
            <input type="number" step="0.01" min="0" name="follow_up_fee" value="<?= e(np_old('follow_up_fee')) ?>" placeholder="500">
          </div>

          <div class="np-field">
            <label>Video Consultation Fee</label>
            <input type="number" step="0.01" min="0" name="video_consultation_fee" value="<?= e(np_old('video_consultation_fee')) ?>" placeholder="700">
          </div>

          <div class="np-field">
            <label>Serial Number / Note</label>
            <input type="text" name="serial_no" value="<?= e(np_old('serial_no')) ?>" placeholder="Optional serial information">
          </div>
        </div>

        <div class="np-grid" style="margin-top:16px;">
          <?php foreach ([
            ['education', 'Education', 'education_bn', 'শিক্ষাগত যোগ্যতা'],
            ['training', 'Training', 'training_bn', 'প্রশিক্ষণ'],
            ['fellowship', 'Fellowship', 'fellowship_bn', 'ফেলোশিপ'],
            ['expertise', 'Expertise', 'expertise_bn', 'দক্ষতা'],
          ] as [$en_field, $en_label, $bn_field, $bn_label]): ?>
            <div class="np-language-pair">
              <div class="np-language-heading">
                <strong><?= e($en_label) ?></strong>
                <span>English left · বাংলা right</span>
              </div>

              <div class="np-field">
                <label><span class="np-lang-tag">EN</span> </label>
                <textarea name="<?= e($en_field) ?>"><?= e(np_old($en_field)) ?></textarea>
              </div>

              <div class="np-field">
                <label><span class="np-lang-tag bn">বাংলা</span> </label>
                <textarea name="<?= e($bn_field) ?>" lang="bn"><?= e(np_old($bn_field)) ?></textarea>
              </div>
            </div>
          <?php endforeach; ?>

        </div>
      </section>

      <section class="np-section">
        <div class="np-section-title">
          <h3>Image & Biography</h3>
          <span>Doctor image, social image and bilingual biography</span>
        </div>

        <div class="np-grid">
          <div class="np-field">
            <label>Doctor Image</label>
            <div class="np-file-box">
              <input type="file" name="image" accept="image/jpeg,image/png,image/webp">
              <div class="np-small">JPG, PNG or WebP. Maximum 8MB.</div>
            </div>
          </div>

          <div class="np-field">
            <label>Open Graph Image</label>
            <div class="np-file-box">
              <input type="file" name="og_image" accept="image/jpeg,image/png,image/webp">
              <div class="np-small">Used when the profile is shared on social platforms.</div>
            </div>
          </div>

          <div class="np-language-pair">
            <div class="np-language-heading">
              <strong>Biography</strong>
              <span>English left · বাংলা right</span>
            </div>

            <div class="np-field">
              <label><span class="np-lang-tag">EN</span></label>
              <textarea name="bio"><?= e(np_old('bio')) ?></textarea>
            </div>

            <div class="np-field">
              <label><span class="np-lang-tag bn">বাংলা</span> </label>
              <textarea name="bio_bn" lang="bn"><?= e(np_old('bio_bn')) ?></textarea>
            </div>
          </div>
        </div>
      </section>

      <section class="np-section">
        <div class="np-section-title">
          <h3>Clinical & Availability</h3>
          <span>Appointment instructions and patient service options</span>
        </div>

        <div class="np-grid">
          <div class="np-language-pair">
            <div class="np-language-heading">
              <strong>Appointment Note</strong>
              <span>English left · বাংলা right</span>
            </div>

            <div class="np-field">
              <label><span class="np-lang-tag">EN</span> Appointment Note</label>
              <textarea name="appointment_note"><?= e(np_old('appointment_note')) ?></textarea>
            </div>

            <div class="np-field">
              <label><span class="np-lang-tag bn">বাংলা</span> অ্যাপয়েন্টমেন্ট নির্দেশনা</label>
              <textarea name="appointment_note_bn" lang="bn"><?= e(np_old('appointment_note_bn')) ?></textarea>
            </div>
          </div>

          <div class="np-field full">
            <label>Patient Service Options</label>
            <div class="np-checks">
              <label class="np-check">
                <input type="checkbox" name="emergency_available" value="1" <?= np_checked('emergency_available') ? 'checked' : '' ?>>
                Emergency Available
              </label>

              <label class="np-check">
                <input type="checkbox" name="online_consultation" value="1" <?= np_checked('online_consultation') ? 'checked' : '' ?>>
                Online Consultation
              </label>

              <label class="np-check">
                <input type="checkbox" name="home_visit" value="1" <?= np_checked('home_visit') ? 'checked' : '' ?>>
                Home Visit
              </label>
            </div>
          </div>

          <div class="np-info">
            Verified status, Featured Profile, featured duration and publishing controls remain admin-managed.
          </div>
        </div>
      </section>

      <section class="np-section">
        <div class="np-section-title">
          <h3>SEO Information</h3>
          <span>Auto or manual English and Bangla SEO</span>
        </div>

        <div class="np-grid">
          <?php
          $seo_pairs = [
              [
                  ['seo_title', 'seo_title_mode', 'SEO Title', 'input', 160],
                  ['seo_title_bn', 'seo_title_bn_mode', 'এসইও শিরোনাম', 'input', 160],
              ],
              [
                  ['seo_description', 'seo_description_mode', 'SEO Description', 'textarea', 300],
                  ['seo_description_bn', 'seo_description_bn_mode', 'এসইও বিবরণ', 'textarea', 300],
              ],
              [
                  ['meta_keywords', 'meta_keywords_mode', 'Meta Keywords', 'input', 255],
                  ['meta_keywords_bn', 'meta_keywords_bn_mode', 'মেটা কীওয়ার্ড', 'input', 255],
              ],
          ];
          ?>

          <?php foreach ($seo_pairs as [$en_config, $bn_config]): ?>
            <?php
            [$en_field, $en_mode_field, $en_label, $en_tag, $en_max] = $en_config;
            [$bn_field, $bn_mode_field, $bn_label, $bn_tag, $bn_max] = $bn_config;

            $en_mode = np_old($en_mode_field, 'auto') === 'manual' ? 'manual' : 'auto';
            $bn_mode = np_old($bn_mode_field, 'auto') === 'manual' ? 'manual' : 'auto';

            $en_value = $en_mode === 'auto'
                ? ($auto_seo_preview[$en_field] ?? '')
                : np_old($en_field);

            $bn_value = $bn_mode === 'auto'
                ? ($auto_seo_preview[$bn_field] ?? '')
                : np_old($bn_field);
            ?>

            <div class="np-language-pair">
              <div class="np-language-heading">
                <strong><?= e($en_label) ?></strong>
                <span>English left · বাংলা right</span>
              </div>

              <div class="np-field np-seo-field">
                <div class="np-label-row">
                  <label><span class="np-lang-tag">EN</span> <?= e($en_label) ?></label>
                  <select class="np-seo-mode" name="<?= e($en_mode_field) ?>" data-target="<?= e($en_field) ?>">
                    <option value="auto" <?= $en_mode === 'auto' ? 'selected' : '' ?>>Auto</option>
                    <option value="manual" <?= $en_mode === 'manual' ? 'selected' : '' ?>>Manual</option>
                  </select>
                </div>

                <?php if ($en_tag === 'textarea'): ?>
                  <textarea
                    name="<?= e($en_field) ?>"
                    id="<?= e($en_field) ?>"
                    maxlength="<?= e((string)$en_max) ?>"
                    data-auto-value="<?= e($auto_seo_preview[$en_field] ?? '') ?>"
                  ><?= e($en_value) ?></textarea>
                <?php else: ?>
                  <input
                    type="text"
                    name="<?= e($en_field) ?>"
                    id="<?= e($en_field) ?>"
                    maxlength="<?= e((string)$en_max) ?>"
                    data-auto-value="<?= e($auto_seo_preview[$en_field] ?? '') ?>"
                    value="<?= e($en_value) ?>"
                  >
                <?php endif; ?>

                <div class="np-small"><span data-counter-for="<?= e($en_field) ?>">0</span> / <?= e((string)$en_max) ?></div>
              </div>

              <div class="np-field np-seo-field">
                <div class="np-label-row">
                  <label><span class="np-lang-tag bn">বাংলা</span> <?= e($bn_label) ?></label>
                  <select class="np-seo-mode" name="<?= e($bn_mode_field) ?>" data-target="<?= e($bn_field) ?>">
                    <option value="auto" <?= $bn_mode === 'auto' ? 'selected' : '' ?>>Auto</option>
                    <option value="manual" <?= $bn_mode === 'manual' ? 'selected' : '' ?>>Manual</option>
                  </select>
                </div>

                <?php if ($bn_tag === 'textarea'): ?>
                  <textarea
                    name="<?= e($bn_field) ?>"
                    id="<?= e($bn_field) ?>"
                    maxlength="<?= e((string)$bn_max) ?>"
                    data-auto-value="<?= e($auto_seo_preview[$bn_field] ?? '') ?>"
                    lang="bn"
                  ><?= e($bn_value) ?></textarea>
                <?php else: ?>
                  <input
                    type="text"
                    name="<?= e($bn_field) ?>"
                    id="<?= e($bn_field) ?>"
                    maxlength="<?= e((string)$bn_max) ?>"
                    data-auto-value="<?= e($auto_seo_preview[$bn_field] ?? '') ?>"
                    value="<?= e($bn_value) ?>"
                    lang="bn"
                  >
                <?php endif; ?>

                <div class="np-small"><span data-counter-for="<?= e($bn_field) ?>">0</span> / <?= e((string)$bn_max) ?></div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </section>

      <div class="np-submit">
        <p>
          <?= $pending_create_request
              ? 'Saving will replace the data inside your existing pending request.'
              : 'Admin approval is required before the doctor profile is published.' ?>
        </p>

        <div class="np-actions">
          <a href="dashboard.php" class="np-btn">Cancel</a>
          <button type="submit" class="np-btn np-btn-primary">
            <?= $pending_create_request ? 'Update Pending Request' : 'Submit Profile Request' ?>
          </button>
        </div>
      </div>
    </form>
  </div>

  <div class="np-card">
    <div class="np-card-head">
      <h2>Recent Profile Requests</h2>
      <p>Your doctor profile creation request history.</p>
    </div>

    <div class="np-table-wrap">
      <table class="np-table">
        <thead>
          <tr>
            <th>ID</th>
            <th>Status</th>
            <th>Submitted</th>
            <th>Updated</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$requests): ?>
            <tr><td colspan="4">No request found.</td></tr>
          <?php endif; ?>

          <?php foreach ($requests as $request): ?>
            <?php $status = strtolower(trim((string)($request['status'] ?? 'pending'))); ?>
            <tr>
              <td>#<?= e((string)($request['id'] ?? '')) ?></td>
              <td><span class="np-status <?= e($status) ?>"><?= e(ucfirst($status)) ?></span></td>
              <td><?= e((string)($request['created_at'] ?? '')) ?></td>
              <td><?= e((string)($request['updated_at'] ?? '')) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
(function () {
  const specialties = <?= json_encode($specialties, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  const districts = <?= json_encode($districts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  const thanas = <?= json_encode($thanas, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

  const doctorName = document.getElementById('doctorName');
  const doctorNameBn = document.getElementById('doctorNameBn');
  const slugInput = document.getElementById('slugInput');
  const checkSlugBtn = document.getElementById('checkSlugBtn');
  const slugStatus = document.getElementById('slugStatus');
  const specialtySelect = document.getElementById('specialtyId');

  const divisionSelect = document.getElementById('doctor_division_id');
  const districtSelect = document.getElementById('doctor_district_id');
  const thanaSelect = document.getElementById('doctor_thana_id');

  const divisionNameInput = document.getElementById('doctor_division_name');
  const districtNameInput = document.getElementById('doctor_district_name');
  const thanaNameInput = document.getElementById('doctor_thana_name');

  function normalizeSlug(value) {
    return String(value || '')
      .toLowerCase()
      .replace(/[^a-z0-9-]+/g, '-')
      .replace(/-+/g, '-')
      .replace(/^-+|-+$/g, '')
      .slice(0, 70);
  }

  function setSlugStatus(type, message) {
    if (!slugStatus) return;
    slugStatus.className = type === 'ok' ? 'ok' : 'error';
    slugStatus.textContent = message;
  }

  async function checkSlugAvailability() {
    if (!slugInput) return false;

    const slug = normalizeSlug(slugInput.value);
    slugInput.value = slug;

    if (slug.length < 6) {
      setSlugStatus('error', 'Slug must contain at least 6 characters.');
      return false;
    }

    try {
      setSlugStatus('error', 'Checking slug...');
      const response = await fetch('new-profile.php?ajax=check_slug&slug=' + encodeURIComponent(slug), {
        headers: { Accept: 'application/json' }
      });
      const data = await response.json();
      setSlugStatus(data.ok ? 'ok' : 'error', data.message || 'Unable to check slug.');

      return !!data.ok;
    } catch (error) {
      setSlugStatus('error', 'Unable to check slug right now.');
      return false;
    }
  }

  if (slugInput) {
    slugInput.addEventListener('input', function () {
      slugInput.value = normalizeSlug(slugInput.value);
      if (slugStatus) {
        slugStatus.className = '';
        slugStatus.textContent = '';
      }
    });
  }

  if (doctorName && slugInput) {
    doctorName.addEventListener('input', function () {
      if (!slugInput.dataset.userEdited && slugInput.value.trim() === '') {
        slugInput.value = normalizeSlug(doctorName.value);
      }
      updateAutoSeo();
    });

    slugInput.addEventListener('input', function () {
      slugInput.dataset.userEdited = '1';
    });
  }

  if (doctorNameBn) {
    doctorNameBn.addEventListener('input', updateAutoSeo);
  }

  if (checkSlugBtn) {
    checkSlugBtn.addEventListener('click', checkSlugAvailability);
  }

  function selectedText(select) {
    if (!select || !select.value || select.selectedIndex < 0) return '';
    return select.options[select.selectedIndex].textContent.trim();
  }

  function selectedBn(select) {
    if (!select || !select.value || select.selectedIndex < 0) return '';
    return select.options[select.selectedIndex].dataset.bn || '';
  }

  function resetSelect(select, label) {
    if (!select) return;
    select.innerHTML = '<option value="">' + label + '</option>';
    select.disabled = true;
  }

  function fillSelect(select, rows, label, currentId) {
    if (!select) return;

    select.innerHTML = '<option value="">' + label + '</option>';

    rows.forEach(function (row) {
      const option = document.createElement('option');
      option.value = row.id;
      option.textContent = row.label || row.name || row.id;
      option.dataset.bn = row.label_bn || '';

      if (String(row.id) === String(currentId || '')) {
        option.selected = true;
      }

      select.appendChild(option);
    });

    select.disabled = false;
  }

  function updateLocationNames() {
    if (divisionNameInput) divisionNameInput.value = selectedText(divisionSelect);
    if (districtNameInput) districtNameInput.value = selectedText(districtSelect);
    if (thanaNameInput) thanaNameInput.value = selectedText(thanaSelect);
    updateAutoSeo();
  }

  function loadDistricts(divisionId, currentDistrictId) {
    resetSelect(districtSelect, 'Select District');
    resetSelect(thanaSelect, 'Select Thana / Upazila');

    if (!divisionId) {
      updateLocationNames();
      return;
    }

    const rows = districts.filter(function (row) {
      return String(row.division_id || '') === String(divisionId);
    });

    fillSelect(districtSelect, rows, 'Select District', currentDistrictId);
    updateLocationNames();
  }

  function loadThanas(districtId, currentThanaId) {
    resetSelect(thanaSelect, 'Select Thana / Upazila');

    if (!districtId) {
      updateLocationNames();
      return;
    }

    const rows = thanas.filter(function (row) {
      return String(row.district_id || '') === String(districtId);
    });

    fillSelect(thanaSelect, rows, 'Select Thana / Upazila', currentThanaId);
    updateLocationNames();
  }

  if (divisionSelect) {
    divisionSelect.addEventListener('change', function () {
      if (districtSelect) districtSelect.dataset.current = '';
      if (thanaSelect) thanaSelect.dataset.current = '';
      loadDistricts(this.value, '');
    });
  }

  if (districtSelect) {
    districtSelect.addEventListener('change', function () {
      if (thanaSelect) thanaSelect.dataset.current = '';
      loadThanas(this.value, '');
    });
  }

  if (thanaSelect) {
    thanaSelect.addEventListener('change', updateLocationNames);
  }

  if (divisionSelect && divisionSelect.value) {
    const savedDistrictId = districtSelect ? districtSelect.dataset.current : '';
    const savedThanaId = thanaSelect ? thanaSelect.dataset.current : '';

    loadDistricts(divisionSelect.value, savedDistrictId);

    if (savedDistrictId) {
      loadThanas(savedDistrictId, savedThanaId);
    }
  }

  function joinUnique(values, separator) {
    const seen = new Set();
    const clean = [];

    values.forEach(function (value) {
      value = String(value || '').trim().replace(/\s+/g, ' ');
      if (!value) return;

      const key = value.toLocaleLowerCase();
      if (!seen.has(key)) {
        seen.add(key);
        clean.push(value);
      }
    });

    return clean.join(separator || ', ');
  }

  function limitText(value, max) {
    value = String(value || '').trim().replace(/\s+/g, ' ');
    return value.length <= max ? value : value.slice(0, max - 1).trimEnd() + '…';
  }

  function autoSeoValues() {
    const name = doctorName ? doctorName.value.trim() : '';
    const nameBn = doctorNameBn && doctorNameBn.value.trim() ? doctorNameBn.value.trim() : name;
    const specialty = selectedText(specialtySelect);
    const specialtyBn = selectedBn(specialtySelect) || specialty;
    const district = selectedText(districtSelect);
    const districtBn = selectedBn(districtSelect) || district;

    const title = joinUnique([name, specialty, district], ' - ');
    const titleBn = joinUnique([nameBn, specialtyBn, districtBn], ' - ');

    return {
      seo_title: limitText(title, 160),
      seo_title_bn: limitText(titleBn, 160),
      seo_description: limitText(
        title ? joinUnique([name, specialty, district], ', ') + '. View doctor profile, specialty, location, appointment information, consultation fee and schedule.' : '',
        300
      ),
      seo_description_bn: limitText(
        titleBn ? joinUnique([nameBn, specialtyBn, districtBn], ', ') + ' এর ডাক্তার প্রোফাইল, বিশেষজ্ঞতা, অবস্থান, অ্যাপয়েন্টমেন্ট তথ্য, কনসালটেশন ফি ও সময়সূচি দেখুন।' : '',
        300
      ),
      meta_keywords: limitText(joinUnique([
        name,
        specialty,
        district,
        name && specialty ? name + ' ' + specialty : '',
        specialty && district ? specialty + ' doctor in ' + district : ''
      ], ', '), 255),
      meta_keywords_bn: limitText(joinUnique([
        nameBn,
        specialtyBn,
        districtBn,
        specialtyBn && districtBn ? districtBn + ' এর ' + specialtyBn + ' ডাক্তার' : ''
      ], ', '), 255)
    };
  }

  function updateCounter(field) {
    const counter = document.querySelector('[data-counter-for="' + field.id + '"]');
    if (counter) counter.textContent = String(field.value.length);
  }

  function updateAutoSeo() {
    const values = autoSeoValues();

    document.querySelectorAll('.np-seo-mode').forEach(function (modeSelect) {
      const target = document.getElementById(modeSelect.dataset.target || '');
      if (!target) return;

      target.dataset.autoValue = values[target.id] || '';

      if (modeSelect.value === 'auto') {
        target.value = target.dataset.autoValue;
        target.readOnly = true;
      } else {
        target.readOnly = false;
      }

      updateCounter(target);
    });
  }

  document.querySelectorAll('.np-seo-mode').forEach(function (select) {
    select.addEventListener('change', updateAutoSeo);
  });

  document.querySelectorAll('.np-seo-field input, .np-seo-field textarea').forEach(function (field) {
    field.addEventListener('input', function () {
      updateCounter(field);
    });
    updateCounter(field);
  });

  if (specialtySelect) {
    specialtySelect.addEventListener('change', updateAutoSeo);
  }

  updateAutoSeo();
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
