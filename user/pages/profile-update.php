<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

$user = require_user_login();

$type = user_profile_type($user);
$is_doctor = $type === 'doctor';

$doctor_id = (int)($user['claimed_doctor_id'] ?? 0);

$form_message = '';
$form_message_type = '';
$error = '';

if (!empty($_SESSION['profile_update_flash_message'])) {
    $form_message_type = $_SESSION['profile_update_flash_type'] ?? 'success';
    $form_message = $_SESSION['profile_update_flash_message'];

    unset($_SESSION['profile_update_flash_type'], $_SESSION['profile_update_flash_message']);
}

function pu_flash_redirect(string $type, string $message, string $section = 'basic'): void
{
    $_SESSION['profile_update_flash_type'] = $type;
    $_SESSION['profile_update_flash_message'] = $message;

    redirect('profile-update.php?section=' . urlencode($section));
}

function pu_json_response(array $payload): void
{
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload);
    exit;
}

function pu_table_exists(string $table): bool
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

function pu_column_exists(string $table, string $column): bool
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

function pu_label_column(string $table): string
{
    foreach (['name', 'name_en', 'title'] as $column) {
        if (pu_column_exists($table, $column)) {
            return $column;
        }
    }

    return 'id';
}

function pu_options(string $table): array
{
    global $pdo;

    $allowed = ['specialties', 'hospitals', 'divisions', 'districts', 'thanas'];

    if (!in_array($table, $allowed, true) || !pu_table_exists($table)) {
        return [];
    }

    try {
        $label = ($table === 'specialties' && pu_column_exists('specialties', 'name_en'))
            ? 'name_en'
            : pu_label_column($table);
        $where = '';
        $extra = '';

        if ($table === 'hospitals' && pu_column_exists('hospitals', 'status')) {
            $where = "WHERE status = 'active'";
        }

        if ($table === 'districts' && pu_column_exists('districts', 'division_id')) {
            $extra = ', division_id';
        }

        if ($table === 'thanas' && pu_column_exists('thanas', 'district_id')) {
            $extra = ', district_id';
        }

        $stmt = $pdo->query("
            SELECT id, {$label} AS label {$extra}
            FROM {$table}
            {$where}
            ORDER BY {$label} ASC
            LIMIT 2000
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return [];
    }
}

function pu_get_doctor(int $doctor_id): ?array
{
    global $pdo;

    if ($doctor_id <= 0 || !pu_table_exists('doctors')) {
        return null;
    }

    try {
        $stmt = $pdo->prepare("SELECT * FROM doctors WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $doctor_id]);
        $doctor = $stmt->fetch(PDO::FETCH_ASSOC);

        return $doctor ?: null;
    } catch (Throwable $e) {
        return null;
    }
}

function pu_decode_request_data(?string $json): array
{
    if (!$json) {
        return [];
    }

    $data = json_decode($json, true);

    return is_array($data) ? $data : [];
}

function pu_request_json(array $data): string
{
    if (function_exists('request_data_json')) {
        return request_data_json($data);
    }

    return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function pu_get_pending_request(int $user_id, int $doctor_id): ?array
{
    global $pdo;

    if (!pu_table_exists('profile_update_requests')) {
        return null;
    }

    try {
        $stmt = $pdo->prepare("
            SELECT *
            FROM profile_update_requests
            WHERE user_id = :user_id
            AND doctor_id = :doctor_id
            AND request_type = 'doctor_profile_update'
            AND status = 'pending'
            ORDER BY id DESC
            LIMIT 1
        ");

        $stmt->execute([
            ':user_id' => $user_id,
            ':doctor_id' => $doctor_id,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    } catch (Throwable $e) {
        return null;
    }
}

function pu_get_requests(int $user_id): array
{
    global $pdo;

    if (!pu_table_exists('profile_update_requests')) {
        return [];
    }

    try {
        $stmt = $pdo->prepare("
            SELECT *
            FROM profile_update_requests
            WHERE user_id = :user_id
            AND request_type = 'doctor_profile_update'
            ORDER BY id DESC
            LIMIT 20
        ");

        $stmt->execute([':user_id' => $user_id]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return [];
    }
}

function pu_safe_file_name(string $text): string
{
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9]+/i', '-', $text);
    $text = trim((string)$text, '-');

    return $text !== '' ? $text : 'doctor';
}

function pu_create_image_resource(string $file, string $mime)
{
    if ($mime === 'image/jpeg') {
        return function_exists('imagecreatefromjpeg') ? @imagecreatefromjpeg($file) : false;
    }

    if ($mime === 'image/png') {
        return function_exists('imagecreatefrompng') ? @imagecreatefrompng($file) : false;
    }

    if ($mime === 'image/webp') {
        return function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($file) : false;
    }

    return false;
}

function pu_file_was_selected(string $field): bool
{
    return isset($_FILES[$field]) && trim((string)($_FILES[$field]['name'] ?? '')) !== '';
}

function pu_upload_fail(string $message): string
{
    $_SESSION['profile_update_upload_error'] = $message;
    error_log('[Profile Update Upload] ' . $message);

    return '';
}

function pu_public_upload_dir(string $folder = 'doctors'): array
{
    $folder = trim($folder, '/');
    $relative_path = 'assets/uploads/' . $folder . '/';
    $candidates = [];

    /*
     * Best live-server path:
     * /htdocs/deluti.com/assets/uploads/doctors/
     */
    $document_root = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/\\');

    if ($document_root !== '') {
        $candidates[] = $document_root . '/' . $relative_path;
    }

    /*
     * If this file is /user/profile-update.php, dirname(__DIR__) is project root.
     */
    $candidates[] = dirname(__DIR__) . '/' . $relative_path;

    /*
     * If this file is /user/pages/profile-update.php, dirname(__DIR__, 2) is project root.
     */
    $candidates[] = dirname(__DIR__, 2) . '/' . $relative_path;

    $candidates = array_values(array_unique($candidates));

    foreach ($candidates as $dir) {
        $dir = rtrim($dir, '/\\') . '/';

        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        if (is_dir($dir) && is_writable($dir)) {
            return [$dir, $relative_path];
        }
    }

    return ['', $relative_path];
}

function pu_upload_image(string $field, string $folder = 'doctors', string $base_name = 'doctor'): string
{
    if (!isset($_FILES[$field])) {
        return '';
    }

    $file = $_FILES[$field];
    $original_name = trim((string)($file['name'] ?? ''));
    $tmp_name = (string)($file['tmp_name'] ?? '');
    $error_code = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);

    if ($original_name === '' || $error_code === UPLOAD_ERR_NO_FILE) {
        return '';
    }

    if ($error_code !== UPLOAD_ERR_OK) {
        $messages = [
            UPLOAD_ERR_INI_SIZE => 'Uploaded image is larger than the server upload_max_filesize limit.',
            UPLOAD_ERR_FORM_SIZE => 'Uploaded image is larger than the form limit.',
            UPLOAD_ERR_PARTIAL => 'Image upload was incomplete. Please try again.',
            UPLOAD_ERR_NO_TMP_DIR => 'Server temporary upload folder is missing.',
            UPLOAD_ERR_CANT_WRITE => 'Server could not write the uploaded image.',
            UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the image upload.',
        ];

        return pu_upload_fail($messages[$error_code] ?? 'Image upload failed. Error code: ' . $error_code);
    }

    if ($tmp_name === '' || !is_uploaded_file($tmp_name)) {
        return pu_upload_fail('Uploaded image temporary file was not found. Check upload_tmp_dir and PHP file upload settings.');
    }

    $max_size = 8 * 1024 * 1024;
    $size = (int)($file['size'] ?? 0);

    if ($size > $max_size) {
        return pu_upload_fail('Maximum image size is 8MB. Please upload a smaller image.');
    }

    $info = @getimagesize($tmp_name);

    if (!$info || empty($info['mime'])) {
        return pu_upload_fail('Invalid image file. Please upload JPG, PNG, or WebP.');
    }

    $mime = strtolower((string)$info['mime']);
    $allowed_mimes = ['image/jpeg', 'image/png', 'image/webp'];

    if (!in_array($mime, $allowed_mimes, true)) {
        return pu_upload_fail('Only JPG, PNG, and WebP images are allowed.');
    }

    [$upload_dir, $relative_path] = pu_public_upload_dir($folder);

    if ($upload_dir === '') {
        return pu_upload_fail('Upload folder is not writable. Please check /assets/uploads/doctors/ permission.');
    }

    $name_part = pu_safe_file_name($base_name);
    $suffix_part = $field === 'og_image' ? '-og' : '';
    $time_part = date('ymdHis');
    $random_part = substr(bin2hex(random_bytes(3)), 0, 6);

    /*
     * Same condition as admin doctor-form.php:
     * Try optimized WebP first. If GD/WebP is not supported, save the original image safely.
     */
    $source = pu_create_image_resource($tmp_name, $mime);
    $can_make_webp = $source && function_exists('imagewebp') && function_exists('imagecreatetruecolor');

    if ($can_make_webp) {
        $source_width = (int)$info[0];
        $source_height = (int)$info[1];

        $max_width = $field === 'og_image' ? 1200 : 900;
        $max_height = $field === 'og_image' ? 630 : 900;

        $ratio = min(
            $max_width / max(1, $source_width),
            $max_height / max(1, $source_height),
            1
        );

        $target_width = max(1, (int)round($source_width * $ratio));
        $target_height = max(1, (int)round($source_height * $ratio));

        $canvas = imagecreatetruecolor($target_width, $target_height);

        if ($canvas) {
            imagealphablending($canvas, false);
            imagesavealpha($canvas, true);

            $transparent = imagecolorallocatealpha($canvas, 255, 255, 255, 127);
            imagefilledrectangle($canvas, 0, 0, $target_width, $target_height, $transparent);

            imagecopyresampled(
                $canvas,
                $source,
                0,
                0,
                0,
                0,
                $target_width,
                $target_height,
                $source_width,
                $source_height
            );

            $file_name = $name_part . $suffix_part . '-' . $time_part . '-' . $random_part . '.webp';
            $target = $upload_dir . $file_name;

            if (@imagewebp($canvas, $target, 82) && file_exists($target)) {
                imagedestroy($canvas);
                imagedestroy($source);
                @chmod($target, 0644);

                return $relative_path . $file_name;
            }

            imagedestroy($canvas);
        }

        imagedestroy($source);
    }

    $ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
    $ext = in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true) ? $ext : 'jpg';
    $file_name = $name_part . $suffix_part . '-' . $time_part . '-' . $random_part . '.' . $ext;
    $target = $upload_dir . $file_name;

    if (@move_uploaded_file($tmp_name, $target)) {
        @chmod($target, 0644);

        return $relative_path . $file_name;
    }

    return pu_upload_fail('Image could not be moved to the upload folder. Check folder permission and server open_basedir settings.');
}

function pu_normalize_slug(string $slug): string
{
    $slug = strtolower(trim($slug));
    $slug = preg_replace('/[^a-z0-9-]+/', '-', $slug);
    $slug = preg_replace('/-+/', '-', (string)$slug);

    return trim((string)$slug, '-');
}

function pu_validate_slug(string $slug): array
{
    $slug = trim($slug);

    if ($slug === '') {
        return [
            'valid' => false,
            'message' => 'Slug cannot be empty.',
        ];
    }

    if (strlen($slug) < 6 || strlen($slug) > 50) {
        return [
            'valid' => false,
            'message' => 'Slug must be minimum 6 and maximum 50 characters.',
        ];
    }

    if (!preg_match('/^[a-z0-9-]+$/', $slug)) {
        return [
            'valid' => false,
            'message' => 'Slug can contain only lowercase a-z, 0-9 and hyphen (-).',
        ];
    }

    if (str_starts_with($slug, '-') || str_ends_with($slug, '-')) {
        return [
            'valid' => false,
            'message' => 'Slug cannot start or end with hyphen (-).',
        ];
    }

    if (str_contains($slug, '--')) {
        return [
            'valid' => false,
            'message' => 'Slug cannot contain double hyphen (--).',
        ];
    }

    return [
        'valid' => true,
        'message' => 'Slug format is valid.',
    ];
}

function pu_slug_exists(string $slug, int $ignore_doctor_id = 0): bool
{
    global $pdo;

    if (!pu_table_exists('doctors') || !pu_column_exists('doctors', 'slug')) {
        return false;
    }

    try {
        $sql = "SELECT id FROM doctors WHERE slug = :slug";
        $params = [':slug' => $slug];

        if ($ignore_doctor_id > 0) {
            $sql .= " AND id != :id";
            $params[':id'] = $ignore_doctor_id;
        }

        $sql .= " LIMIT 1";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return (bool)$stmt->fetchColumn();
    } catch (Throwable $e) {
        return true;
    }
}

function pu_slug_check_payload(string $slug, int $ignore_doctor_id = 0): array
{
    $slug = pu_normalize_slug($slug);
    $validation = pu_validate_slug($slug);

    if (!$validation['valid']) {
        return [
            'ok' => false,
            'available' => false,
            'slug' => $slug,
            'message' => $validation['message'],
        ];
    }

    if (pu_slug_exists($slug, $ignore_doctor_id)) {
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

function pu_is_money_field(string $field): bool
{
    return in_array($field, [
        'consultation_fee',
        'follow_up_fee',
        'video_consultation_fee',
        'visiting_fee',
    ], true);
}

function pu_normalize_money($value): string
{
    $value = trim((string)$value);

    if ($value === '') {
        return '0.00';
    }

    $value = str_replace([',', '৳', 'BDT', 'Tk', 'tk', 'TK'], '', $value);
    $value = trim($value);

    if (!is_numeric($value)) {
        return '0.00';
    }

    return number_format((float)$value, 2, '.', '');
}

function pu_compare_value(string $field, $value): string
{
    $value = trim((string)$value);

    if (pu_is_money_field($field)) {
        return pu_normalize_money($value);
    }

    return $value;
}

function pu_display_value(string $field, array $doctor, array $pending_data = [], string $default = ''): string
{
    if (array_key_exists($field, $pending_data)) {
        $value = $pending_data[$field];

        if (pu_is_money_field($field)) {
            return pu_normalize_money($value);
        }

        return trim((string)$value);
    }

    $value = $doctor[$field] ?? $default;

    if (pu_is_money_field($field)) {
        return pu_normalize_money($value);
    }

    return trim((string)$value);
}

function pu_is_updated(string $field, array $doctor, array $pending_data): bool
{
    if (!array_key_exists($field, $pending_data)) {
        return false;
    }

    $old = pu_compare_value($field, $doctor[$field] ?? '');
    $new = pu_compare_value($field, $pending_data[$field] ?? '');

    return $old !== $new;
}

function pu_field_class(string $field, array $doctor, array $pending_data): string
{
    return pu_is_updated($field, $doctor, $pending_data) ? 'pu-field is-updated' : 'pu-field';
}

function pu_badge(string $field, array $doctor, array $pending_data): string
{
    if (!pu_is_updated($field, $doctor, $pending_data)) {
        return '';
    }

    return '<span class="pu-updated-badge">Pending</span>';
}

function pu_clean_section_payload(array $payload, array $doctor, array $pending_data): array
{
    $merged = $pending_data;

    foreach ($payload as $field => $value) {
        $new_compare = pu_compare_value($field, $value);
        $old_compare = pu_compare_value($field, $doctor[$field] ?? '');

        if ($new_compare === $old_compare) {
            unset($merged[$field]);
            continue;
        }

        $merged[$field] = $value;
    }

    foreach ($merged as $field => $value) {
        if (in_array($field, ['id', 'user_id', 'doctor_id', 'created_at', 'updated_at'], true)) {
            unset($merged[$field]);
        }
    }

    return $merged;
}

function pu_save_pending_request(int $user_id, int $doctor_id, array $data, ?array $existing_request): void
{
    global $pdo;

    if (!pu_table_exists('profile_update_requests')) {
        throw new RuntimeException('profile_update_requests table not found.');
    }

    if ($existing_request) {
        $stmt = $pdo->prepare("
            UPDATE profile_update_requests
            SET request_data = :request_data,
                updated_at = NOW()
            WHERE id = :id
            LIMIT 1
        ");

        $stmt->execute([
            ':request_data' => pu_request_json($data),
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
            'doctor_profile_update',
            :doctor_id,
            NULL,
            NULL,
            :request_data,
            'pending',
            NOW()
        )
    ");

    $stmt->execute([
        ':user_id' => $user_id,
        ':doctor_id' => $doctor_id,
        ':request_data' => pu_request_json($data),
    ]);
}


function pu_csrf_token(): string
{
    if (function_exists('csrf_token')) {
        return (string)csrf_token();
    }

    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return (string)$_SESSION['csrf_token'];
}

function pu_verify_csrf_submission(): bool
{
    if (function_exists('verify_csrf_token')) {
        return (bool)verify_csrf_token();
    }

    $session_token = (string)($_SESSION['csrf_token'] ?? '');
    $posted_token = (string)($_POST['csrf_token'] ?? '');

    return $session_token !== '' && $posted_token !== '' && hash_equals($session_token, $posted_token);
}

function pu_normalize_gender($value): string
{
    $value = strtolower(trim((string)$value));

    return match ($value) {
        'male' => 'Male',
        'female' => 'Female',
        'other' => 'Other',
        default => '',
    };
}

function pu_gender_bn_from_en(string $gender): string
{
    return match (pu_normalize_gender($gender)) {
        'Male' => 'পুরুষ',
        'Female' => 'নারী',
        'Other' => 'অন্যান্য',
        default => '',
    };
}

function pu_languages_bn_from_en(string $languages): string
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

        $key = function_exists('mb_strtolower') ? mb_strtolower($part, 'UTF-8') : strtolower($part);
        $translated[] = $map[$key] ?? $part;
    }

    return implode(', ', array_values(array_unique($translated)));
}

function pu_normalize_seo_mode($value): string
{
    return trim((string)$value) === 'manual' ? 'manual' : 'auto';
}

function pu_db_name_pair(string $table, int $id): array
{
    global $pdo;

    $result = ['en' => '', 'bn' => ''];

    if ($id <= 0 || !in_array($table, ['specialties', 'districts', 'hospitals'], true) || !pu_table_exists($table)) {
        return $result;
    }

    $select = ['id'];

    foreach (['name', 'name_en', 'name_bn'] as $column) {
        if (pu_column_exists($table, $column)) {
            $select[] = $column;
        }
    }

    if (count($select) === 1) {
        return $result;
    }

    try {
        $stmt = $pdo->prepare("SELECT " . implode(', ', $select) . " FROM `{$table}` WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $result['en'] = trim((string)($row['name_en'] ?? $row['name'] ?? ''));
        $result['bn'] = trim((string)($row['name_bn'] ?? ''));
    } catch (Throwable $e) {
        return $result;
    }

    return $result;
}

function pu_join_unique(array $values, string $separator = ', '): string
{
    $clean = [];

    foreach ($values as $value) {
        $value = trim(preg_replace('/\\s+/', ' ', (string)$value));

        if ($value === '') {
            continue;
        }

        $key = function_exists('mb_strtolower') ? mb_strtolower($value) : strtolower($value);
        $clean[$key] = $value;
    }

    return implode($separator, array_values($clean));
}

function pu_limit_text(string $value, int $limit): string
{
    $value = trim(preg_replace('/\\s+/', ' ', $value));
    $length = function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);

    if ($length <= $limit) {
        return $value;
    }

    $cut = function_exists('mb_substr') ? mb_substr($value, 0, max(0, $limit - 1)) : substr($value, 0, max(0, $limit - 1));
    return rtrim($cut) . '…';
}

function pu_generate_auto_seo(array $source): array
{
    $specialty = pu_db_name_pair('specialties', (int)($source['specialty_id'] ?? 0));
    $district = pu_db_name_pair('districts', (int)($source['doctor_district_id'] ?? 0));

    $name = trim((string)($source['name'] ?? ''));
    $name_bn = trim((string)($source['name_bn'] ?? '')) ?: $name;
    $specialty_en = $specialty['en'];
    $specialty_bn = $specialty['bn'] ?: $specialty_en;
    $district_en = $district['en'];
    $district_bn = $district['bn'] ?: $district_en;

    $title = pu_join_unique([$name, $specialty_en, $district_en], ' - ');
    $title_bn = pu_join_unique([$name_bn, $specialty_bn, $district_bn], ' - ');

    $description = $title !== ''
        ? pu_join_unique([$name, $specialty_en, $district_en], ', ') . '. View doctor profile, hospital availability, appointment information, consultation fee and schedule.'
        : '';

    $description_bn = $title_bn !== ''
        ? pu_join_unique([$name_bn, $specialty_bn, $district_bn], ', ') . ' এর ডাক্তার প্রোফাইল, হাসপাতাল, অ্যাপয়েন্টমেন্ট তথ্য, কনসালটেশন ফি ও সময়সূচি দেখুন।'
        : '';

    $keywords = pu_join_unique([
        $name,
        $specialty_en,
        $district_en,
        $name !== '' && $specialty_en !== '' ? $name . ' ' . $specialty_en : '',
        $specialty_en !== '' && $district_en !== '' ? $specialty_en . ' doctor in ' . $district_en : '',
        $name !== '' && $district_en !== '' ? $name . ' doctor in ' . $district_en : '',
    ]);

    $keywords_bn = pu_join_unique([
        $name_bn,
        $specialty_bn,
        $district_bn,
        $specialty_bn !== '' && $district_bn !== '' ? $district_bn . ' এর ' . $specialty_bn . ' ডাক্তার' : '',
        $name_bn !== '' && $district_bn !== '' ? $district_bn . ' এর ' . $name_bn : '',
    ]);

    return [
        'seo_title' => pu_limit_text($title, 160),
        'seo_title_bn' => pu_limit_text($title_bn, 160),
        'seo_description' => pu_limit_text($description, 300),
        'seo_description_bn' => pu_limit_text($description_bn, 300),
        'meta_keywords' => pu_limit_text($keywords, 255),
        'meta_keywords_bn' => pu_limit_text($keywords_bn, 255),
    ];
}

function pu_seo_value_by_mode($posted_value, $mode, $auto_value): string
{
    $posted_value = trim((string)$posted_value);

    if (pu_normalize_seo_mode($mode) === 'manual' && $posted_value !== '') {
        return $posted_value;
    }

    return trim((string)$auto_value);
}

function pu_public_asset_url(string $path): string
{
    $path = trim($path);

    if ($path === '') {
        return '';
    }

    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }

    $path = ltrim($path, '/');

    if (function_exists('site_url')) {
        return site_url($path);
    }

    return '/' . $path;
}

if (!user_has_claim($user)) {
    redirect('new-profile.php');
}

if (!$is_doctor) {
    $error = 'This update page is currently available for doctor profiles only.';
}

if ($is_doctor && $doctor_id <= 0) {
    $error = 'No approved doctor profile found for this account.';
}

$doctor = pu_get_doctor($doctor_id);

if ($is_doctor && !$doctor && $error === '') {
    $error = 'Connected doctor profile data not found.';
}

$sections = [
    'basic' => 'Basic Information',
    'contact' => 'Contact & Location',
    'professional' => 'Professional Details',
    'image' => 'Image & Biography',
    'clinical' => 'Clinical Profile',
    'status' => 'Status & Display Options',
    'seo' => 'SEO Information',
    'requests' => 'Requests',
];

$section = trim((string)($_GET['section'] ?? 'basic'));

if (!array_key_exists($section, $sections)) {
    $section = 'basic';
}

if (($_GET['ajax'] ?? '') === 'check_slug') {
    $slug_to_check = (string)($_GET['slug'] ?? '');
    pu_json_response(pu_slug_check_payload($slug_to_check, $doctor_id));
}

$pending_request = pu_get_pending_request((int)$user['id'], $doctor_id);
$pending_data = $pending_request ? pu_decode_request_data($pending_request['request_data'] ?? '') : [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $error === '' && $doctor) {
    if (!pu_verify_csrf_submission()) {
        pu_flash_redirect('error', 'Security token expired. Please refresh the page and try again.', $section);
    }

    $form_section = trim((string)($_POST['section'] ?? $section));

    if (!array_key_exists($form_section, $sections)) {
        $form_section = 'basic';
    }

    $payload = [];

    if ($form_section === 'basic') {
        $name = trim((string)($_POST['name'] ?? ''));
        $degree = trim((string)($_POST['degree'] ?? ''));
        $designation = trim((string)($_POST['designation'] ?? ''));
        $specialty_id = (int)($_POST['specialty_id'] ?? 0);
        $gender = pu_normalize_gender($_POST['gender'] ?? '');
        $primary_hospital = trim((string)($_POST['primary_hospital'] ?? ''));
        $slug = pu_normalize_slug((string)($_POST['slug'] ?? ''));

        $missing = [];
        if ($name === '') $missing[] = 'Doctor Name';
        if ($degree === '') $missing[] = 'Degree';
        if ($designation === '') $missing[] = 'Designation';
        if ($specialty_id <= 0) $missing[] = 'Specialty';
        if ($gender === '') $missing[] = 'Gender';
        if ($primary_hospital === '') $missing[] = 'Primary Hospital';

        if ($missing) {
            pu_flash_redirect('error', 'Please fill required fields: ' . implode(', ', $missing) . '.', $form_section);
        }

        $slug_check = pu_slug_check_payload($slug, $doctor_id);
        if (!$slug_check['ok']) {
            pu_flash_redirect('error', $slug_check['message'], $form_section);
        }

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
            'gender_bn' => pu_gender_bn_from_en($gender),
            'languages' => trim((string)($_POST['languages'] ?? '')),
            'languages_bn' => pu_languages_bn_from_en(trim((string)($_POST['languages'] ?? ''))),
            'specialty_id' => $specialty_id,
            'primary_hospital' => $primary_hospital,
            'primary_hospital_bn' => trim((string)($_POST['primary_hospital_bn'] ?? '')),
        ];
    }

    if ($form_section === 'contact') {
        $email = trim((string)($_POST['email'] ?? ''));

        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            pu_flash_redirect('error', 'Please enter a valid email address.', $form_section);
        }

        $location_source = trim((string)($_POST['doctor_location_source'] ?? 'auto')) === 'manual' ? 'manual' : 'auto';

        $location_ids = $location_source === 'manual'
            ? [
                'doctor_division_id' => (int)($_POST['doctor_division_id'] ?? 0),
                'doctor_district_id' => (int)($_POST['doctor_district_id'] ?? 0),
                'doctor_thana_id' => (int)($_POST['doctor_thana_id'] ?? 0),
            ]
            : [
                'doctor_division_id' => (int)($doctor['doctor_division_id'] ?? 0),
                'doctor_district_id' => (int)($doctor['doctor_district_id'] ?? 0),
                'doctor_thana_id' => (int)($doctor['doctor_thana_id'] ?? 0),
            ];

        $payload = array_merge([
            'phone' => trim((string)($_POST['phone'] ?? '')),
            'whatsapp' => trim((string)($_POST['whatsapp'] ?? '')),
            'email' => $email,
            'doctor_location_source' => $location_source,
        ], $location_ids);
    }

    if ($form_section === 'professional') {
        $starting_year = trim((string)($_POST['experience_years'] ?? ''));
        $current_year = (int)date('Y');

        if ($starting_year !== '' && ((int)$starting_year < 1900 || (int)$starting_year > $current_year)) {
            pu_flash_redirect('error', 'Starting year must be between 1900 and ' . $current_year . '.', $form_section);
        }

        $payload = [
            'experience_years' => $starting_year === '' ? '' : (int)$starting_year,
            'consultation_fee' => pu_normalize_money($_POST['consultation_fee'] ?? 0),
            'follow_up_fee' => pu_normalize_money($_POST['follow_up_fee'] ?? 0),
            'video_consultation_fee' => pu_normalize_money($_POST['video_consultation_fee'] ?? 0),
        ];
    }

    if ($form_section === 'image') {
        unset($_SESSION['profile_update_upload_error']);

        $doctor_file_name = trim((string)($doctor['name'] ?? 'doctor'));
        $uploaded_image = pu_upload_image('image', 'doctors', $doctor_file_name);
        $image_upload_error = $_SESSION['profile_update_upload_error'] ?? '';
        unset($_SESSION['profile_update_upload_error']);

        $uploaded_og_image = pu_upload_image('og_image', 'doctors', $doctor_file_name);
        $og_upload_error = $_SESSION['profile_update_upload_error'] ?? '';
        unset($_SESSION['profile_update_upload_error']);

        if (pu_file_was_selected('image') && $uploaded_image === '') {
            pu_flash_redirect('error', $image_upload_error !== '' ? $image_upload_error : 'Doctor image could not be uploaded.', $form_section);
        }

        if (pu_file_was_selected('og_image') && $uploaded_og_image === '') {
            pu_flash_redirect('error', $og_upload_error !== '' ? $og_upload_error : 'OG image could not be uploaded.', $form_section);
        }

        $payload = [
            'bio' => trim((string)($_POST['bio'] ?? '')),
            'bio_bn' => trim((string)($_POST['bio_bn'] ?? '')),
        ];

        if ($uploaded_image !== '') $payload['image'] = $uploaded_image;
        if ($uploaded_og_image !== '') $payload['og_image'] = $uploaded_og_image;
    }

    if ($form_section === 'clinical') {
        $payload = [
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
        ];
    }

    if ($form_section === 'status') {
        $payload = [
            'emergency_available' => isset($_POST['emergency_available']) ? 1 : 0,
            'online_consultation' => isset($_POST['online_consultation']) ? 1 : 0,
            'home_visit' => isset($_POST['home_visit']) ? 1 : 0,
        ];
    }

    if ($form_section === 'seo') {
        $seo_source = array_merge($doctor, $pending_data, $_POST);
        $auto_values = pu_generate_auto_seo($seo_source);

        $seo_title_mode = pu_normalize_seo_mode($_POST['seo_title_mode'] ?? 'auto');
        $seo_title_bn_mode = pu_normalize_seo_mode($_POST['seo_title_bn_mode'] ?? 'auto');
        $seo_description_mode = pu_normalize_seo_mode($_POST['seo_description_mode'] ?? 'auto');
        $seo_description_bn_mode = pu_normalize_seo_mode($_POST['seo_description_bn_mode'] ?? 'auto');
        $meta_keywords_mode = pu_normalize_seo_mode($_POST['meta_keywords_mode'] ?? 'auto');
        $meta_keywords_bn_mode = pu_normalize_seo_mode($_POST['meta_keywords_bn_mode'] ?? 'auto');

        $payload = [
            'seo_title' => pu_seo_value_by_mode($_POST['seo_title'] ?? '', $seo_title_mode, $auto_values['seo_title']),
            'seo_title_bn' => pu_seo_value_by_mode($_POST['seo_title_bn'] ?? '', $seo_title_bn_mode, $auto_values['seo_title_bn']),
            'seo_description' => pu_seo_value_by_mode($_POST['seo_description'] ?? '', $seo_description_mode, $auto_values['seo_description']),
            'seo_description_bn' => pu_seo_value_by_mode($_POST['seo_description_bn'] ?? '', $seo_description_bn_mode, $auto_values['seo_description_bn']),
            'meta_keywords' => pu_seo_value_by_mode($_POST['meta_keywords'] ?? '', $meta_keywords_mode, $auto_values['meta_keywords']),
            'meta_keywords_bn' => pu_seo_value_by_mode($_POST['meta_keywords_bn'] ?? '', $meta_keywords_bn_mode, $auto_values['meta_keywords_bn']),
            'seo_title_mode' => $seo_title_mode,
            'seo_title_bn_mode' => $seo_title_bn_mode,
            'seo_description_mode' => $seo_description_mode,
            'seo_description_bn_mode' => $seo_description_bn_mode,
            'meta_keywords_mode' => $meta_keywords_mode,
            'meta_keywords_bn_mode' => $meta_keywords_bn_mode,
        ];
    }

    $existing_pending = pu_get_pending_request((int)$user['id'], $doctor_id);
    $existing_pending_data = $existing_pending ? pu_decode_request_data($existing_pending['request_data'] ?? '') : [];

    $merged_data = pu_clean_section_payload($payload, $doctor, $existing_pending_data);

    if (!$merged_data) {
        pu_flash_redirect('error', 'No value changed. Please change at least one value before submitting.', $form_section);
    }

    try {
        pu_save_pending_request((int)$user['id'], $doctor_id, $merged_data, $existing_pending);
        pu_flash_redirect('success', 'Update saved. Your changed fields are marked as Pending until admin approval.', $form_section);
    } catch (Throwable $e) {
        pu_flash_redirect('error', 'Request could not be saved. Please check profile_update_requests table.', $form_section);
    }
}

$pending_request = pu_get_pending_request((int)$user['id'], $doctor_id);
$pending_data = $pending_request ? pu_decode_request_data($pending_request['request_data'] ?? '') : [];

$requests = pu_get_requests((int)$user['id']);

$specialties = pu_options('specialties');
$divisions = pu_options('divisions');
$districts = pu_options('districts');
$thanas = pu_options('thanas');
$csrf_token = pu_csrf_token();
$auto_seo_preview = pu_generate_auto_seo(array_merge(is_array($doctor) ? $doctor : [], $pending_data));
$current_image_url = pu_public_asset_url(pu_display_value('image', $doctor ?: [], $pending_data));
$current_og_image_url = pu_public_asset_url(pu_display_value('og_image', $doctor ?: [], $pending_data));

$doctor_name_for_title = trim((string)($doctor['name'] ?? ''));
$doctor_slug_for_view = trim((string)($doctor['slug'] ?? ''));
$doctor_profile_link = '';

if ($doctor_slug_for_view !== '') {
    if (function_exists('site_url')) {
        $doctor_profile_link = site_url('doctor/' . $doctor_slug_for_view);
    } else {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? '';
        $doctor_profile_link = $host !== '' ? $scheme . '://' . $host . '/doctor/' . $doctor_slug_for_view : '/doctor/' . $doctor_slug_for_view;
    }
}

$page_title = 'Profile Update';

require_once __DIR__ . '/../includes/header.php';
?>

<style>
  :root {
    --gh-bg: #f6f8fa;
    --gh-canvas: #ffffff;
    --gh-border: #d0d7de;
    --gh-border-soft: #d8dee4;
    --gh-text: #24292f;
    --gh-muted: #57606a;
    --gh-blue: #0969da;
    --gh-green: #1f883d;
    --gh-green-bg: #dafbe1;
    --gh-yellow: #9a6700;
    --gh-yellow-bg: #fff8c5;
    --gh-red: #cf222e;
    --gh-red-bg: #ffebe9;
  }

  body {
    background: var(--gh-bg);
  }

  .pu-page,
  .pu-page * {
    box-sizing: border-box;
  }

  .pu-page {
    max-width: 1180px;
    margin: 0 auto;
    padding: 18px 14px 34px;
    color: var(--gh-text);
    font-family: Inter, "Noto Sans Bengali", system-ui, sans-serif;
  }

  .pu-alert {
    display: flex;
    gap: 12px;
    align-items: flex-start;
    padding: 14px 16px;
    margin-bottom: 16px;
    border-radius: 10px;
    border: 1px solid var(--gh-border);
    background: var(--gh-canvas);
  }

  .pu-alert.success {
    background: var(--gh-green-bg);
    border-color: rgba(31, 136, 61, .25);
    color: #116329;
  }

  .pu-alert.error {
    background: var(--gh-red-bg);
    border-color: rgba(207, 34, 46, .25);
    color: var(--gh-red);
  }

  .pu-hero {
    background: var(--gh-canvas);
    border: 1px solid var(--gh-border);
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 16px;
    display: flex;
    justify-content: space-between;
    gap: 16px;
    flex-wrap: wrap;
    box-shadow: 0 1px 0 rgba(27,31,36,.04);
  }

  .pu-hero h1 {
    margin: 0 0 6px;
    font-size: 26px;
    line-height: 1.2;
    letter-spacing: -.03em;
  }

  .pu-hero p {
    max-width: 760px;
    margin: 0;
    color: var(--gh-muted);
    font-size: 14px;
    line-height: 1.7;
  }

  .pu-hero-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
  }

  .pu-btn {
    min-height: 34px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 7px 12px;
    border-radius: 6px;
    border: 1px solid rgba(27,31,36,.15);
    background: #f6f8fa;
    color: var(--gh-text);
    text-decoration: none;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
    box-shadow: 0 1px 0 rgba(27,31,36,.04);
    font-family: inherit;
  }

  .pu-btn:hover {
    background: #eef1f4;
    text-decoration: none;
  }

  .pu-btn-primary {
    color: #fff;
    background: linear-gradient(135deg, #2da44e, #1f883d);
    border-color: #1a7f37;
    box-shadow: 0 8px 18px rgba(31,136,61,.18);
  }

  .pu-btn-primary:hover {
    color: #fff;
    background: linear-gradient(135deg, #1f883d, #1a7f37);
    transform: translateY(-1px);
  }

  .pu-layout {
    display: grid;
    grid-template-columns: 280px 1fr;
    gap: 16px;
    align-items: start;
  }

  .pu-sidebar {
    position: sticky;
    top: 16px;
    display: grid;
    gap: 12px;
  }

  .pu-card {
    background: var(--gh-canvas);
    border: 1px solid #d8e0ea;
    border-radius: 18px;
    overflow: hidden;
    box-shadow: 0 14px 38px rgba(31, 35, 40, .07);
  }

  .pu-card-head {
    position: relative;
    padding: 18px 20px;
    background:
      radial-gradient(circle at top right, rgba(9,105,218,.10), transparent 34%),
      linear-gradient(135deg, #ffffff, #f6f8fa);
    border-bottom: 1px solid #d8e0ea;
  }

  .pu-card-head::after {
    content: "";
    position: absolute;
    left: 20px;
    bottom: -1px;
    width: 92px;
    height: 3px;
    border-radius: 999px;
    background: linear-gradient(90deg, #0969da, #2da44e);
  }

  .pu-card-head h2,
  .pu-card-head h3 {
    margin: 0;
    font-size: 15px;
  }

  .pu-card-head p {
    margin: 5px 0 0;
    color: var(--gh-muted);
    font-size: 13px;
    line-height: 1.5;
  }

  .pu-card-body {
    padding: 20px;
  }

  .pu-tabs {
    display: grid;
    gap: 4px;
    padding: 8px;
  }

  .pu-tab {
    display: flex;
    justify-content: space-between;
    gap: 8px;
    min-height: 38px;
    align-items: center;
    padding: 8px 10px;
    border-radius: 6px;
    color: var(--gh-text);
    text-decoration: none;
    font-size: 13px;
    font-weight: 700;
  }

  .pu-tab:hover {
    background: #f6f8fa;
    text-decoration: none;
  }

  .pu-tab.active {
    background: #ddf4ff;
    color: var(--gh-blue);
  }

  .pu-pill {
    min-height: 22px;
    padding: 3px 8px;
    border-radius: 999px;
    background: #f6f8fa;
    border: 1px solid var(--gh-border-soft);
    color: var(--gh-muted);
    font-size: 12px;
    font-weight: 700;
  }

  .pu-pill.updated {
    color: var(--gh-yellow);
    background: var(--gh-yellow-bg);
    border-color: rgba(154, 103, 0, .25);
  }

  .pu-summary {
    display: grid;
    gap: 8px;
  }

  .pu-summary-row {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    padding: 9px 10px;
    border: 1px solid var(--gh-border-soft);
    border-radius: 8px;
    background: #fff;
    font-size: 13px;
  }

  .pu-summary-row span {
    color: var(--gh-muted);
  }

  .pu-summary-row strong {
    text-align: right;
  }

  .pu-form-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 16px;
  }

  .pu-language-pair {
    grid-column: 1 / -1;
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 16px;
    padding: 16px;
    border: 1px solid #d8e0ea;
    border-radius: 14px;
    background:
      linear-gradient(90deg, rgba(9,105,218,.035) 0 50%, rgba(45,164,78,.035) 50% 100%),
      #ffffff;
  }

  .pu-language-pair .pu-field {
    min-width: 0;
  }

  .pu-language-pair .pu-field + .pu-field {
    padding-left: 16px;
    border-left: 1px dashed #cbd5e1;
  }

  .pu-language-heading {
    grid-column: 1 / -1;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin: -2px 0 0;
    color: var(--gh-muted);
    font-size: 12px;
    line-height: 1.5;
  }

  .pu-language-heading strong {
    color: var(--gh-text);
    font-size: 13px;
  }

  .pu-lang-tag {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 34px;
    min-height: 22px;
    padding: 3px 8px;
    border: 1px solid #b6d7ff;
    border-radius: 999px;
    background: #ddf4ff;
    color: #0550ae;
    font-size: 10px;
    font-weight: 800;
    letter-spacing: .04em;
  }

  .pu-lang-tag.bn {
    border-color: #a7f3d0;
    background: #dafbe1;
    color: #116329;
    letter-spacing: 0;
  }

  .pu-field {
    display: grid;
    gap: 7px;
  }

  .pu-field.full {
    grid-column: 1 / -1;
  }

  .pu-field label {
    display: flex;
    align-items: center;
    gap: 8px;
    color: var(--gh-text);
    font-size: 13px;
    font-weight: 700;
  }

  .pu-field input,
  .pu-field select,
  .pu-field textarea {
    width: 100%;
    min-height: 44px;
    border: 1px solid #cbd5e1;
    border-radius: 9px;
    padding: 10px 12px;
    background: #fff;
    color: var(--gh-text);
    outline: none;
    font-family: inherit;
    font-size: 14px;
    transition: border-color .16s ease, box-shadow .16s ease, background .16s ease;
  }

  .pu-field textarea {
    min-height: 145px;
    resize: vertical;
    line-height: 1.7;
  }

  .pu-field input:focus,
  .pu-field select:focus,
  .pu-field textarea:focus {
    border-color: var(--gh-blue);
    box-shadow: 0 0 0 3px rgba(9,105,218,.15);
  }

  .pu-field.is-updated input,
  .pu-field.is-updated select,
  .pu-field.is-updated textarea {
    border-color: rgba(154, 103, 0, .35);
    background: #fffdf0;
  }

  .pu-updated-badge {
    display: inline-flex;
    align-items: center;
    min-height: 21px;
    padding: 2px 7px;
    border-radius: 999px;
    background: var(--gh-yellow-bg);
    border: 1px solid rgba(154, 103, 0, .25);
    color: var(--gh-yellow);
    font-size: 11px;
    font-weight: 800;
  }

  .pu-small {
    color: var(--gh-muted);
    font-size: 12px;
    line-height: 1.5;
  }

  .pu-inline {
    display: grid;
    grid-template-columns: 1fr auto;
    gap: 8px;
    align-items: end;
  }

  .pu-checks {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 10px;
  }

  .pu-check {
    display: flex;
    align-items: center;
    gap: 8px;
    min-height: 42px;
    padding: 10px;
    border: 1px solid var(--gh-border);
    border-radius: 8px;
    background: #fff;
    font-size: 13px;
    font-weight: 700;
  }

  .pu-check input {
    width: 16px;
    height: 16px;
    accent-color: #2da44e;
  }

  .pu-submit {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
    align-items: center;
    margin-top: 16px;
    padding-top: 16px;
    border-top: 1px solid var(--gh-border-soft);
  }

  .pu-table-wrap {
    overflow-x: auto;
  }

  .pu-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
  }

  .pu-table th,
  .pu-table td {
    padding: 11px 12px;
    border-bottom: 1px solid var(--gh-border-soft);
    text-align: left;
    font-size: 13px;
  }

  .pu-table th {
    background: #f6f8fa;
    color: var(--gh-muted);
    font-weight: 700;
  }

  .pu-status {
    display: inline-flex;
    padding: 3px 8px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 800;
    border: 1px solid var(--gh-border-soft);
    background: #f6f8fa;
  }

  .pu-status.pending {
    color: var(--gh-yellow);
    background: var(--gh-yellow-bg);
    border-color: rgba(154, 103, 0, .25);
  }

  .pu-status.approved {
    color: #116329;
    background: var(--gh-green-bg);
    border-color: rgba(31, 136, 61, .25);
  }

  .pu-status.rejected {
    color: var(--gh-red);
    background: var(--gh-red-bg);
    border-color: rgba(207, 34, 46, .25);
  }

  #slugStatus {
    display: none;
    padding: 8px 10px;
    border-radius: 6px;
    font-size: 12px;
  }

  #slugStatus.ok {
    display: block;
    color: #116329;
    background: var(--gh-green-bg);
    border: 1px solid rgba(31, 136, 61, .25);
  }

  #slugStatus.error {
    display: block;
    color: var(--gh-red);
    background: var(--gh-red-bg);
    border: 1px solid rgba(207, 34, 46, .25);
  }


  .pu-required { color: var(--gh-red); }

  .pu-info-box {
    padding: 12px 14px;
    border: 1px solid #b6d7ff;
    border-radius: 8px;
    background: #ddf4ff;
    color: #0550ae;
    font-size: 12.5px;
    line-height: 1.6;
  }

  .pu-label-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
  }

  .pu-label-row label { margin: 0; }

  .pu-seo-mode {
    width: auto !important;
    min-width: 110px !important;
    min-height: 32px !important;
    padding: 5px 8px !important;
  }

  .pu-seo-field input[readonly],
  .pu-seo-field textarea[readonly] {
    background: #f6f8fa;
    color: var(--gh-muted);
  }

  .pu-image-preview {
    width: 150px;
    height: 150px;
    object-fit: cover;
    border: 1px solid var(--gh-border);
    border-radius: 10px;
    background: #f6f8fa;
  }

  .pu-image-preview-wide {
    width: 240px;
    max-width: 100%;
    height: 126px;
  }

  .pu-check.is-updated {
    border-color: rgba(154, 103, 0, .35);
    background: #fffdf0;
  }

  @media (max-width: 960px) {
    .pu-layout {
      grid-template-columns: 1fr;
    }

    .pu-sidebar {
      position: static;
    }

    .pu-tabs {
      grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .pu-form-grid,
    .pu-checks,
    .pu-language-pair {
      grid-template-columns: 1fr;
    }

    .pu-language-pair {
      padding: 14px;
      background: #ffffff;
    }

    .pu-language-pair .pu-field + .pu-field {
      padding-left: 0;
      padding-top: 14px;
      border-left: 0;
      border-top: 1px dashed #cbd5e1;
    }

    .pu-inline {
      grid-template-columns: 1fr;
    }
  }

  @media (max-width: 560px) {
    .pu-hero,
    .pu-card-body {
      padding: 14px;
    }

    .pu-tabs {
      grid-template-columns: 1fr;
    }

    .pu-btn {
      width: 100%;
    }
  }
</style>

<div class="pu-page">

  <?php if (!empty($form_message)): ?>
    <div class="pu-alert <?= e($form_message_type) ?>">
      <strong><?= $form_message_type === 'success' ? 'Success' : 'Error' ?>:</strong>
      <span><?= e($form_message) ?></span>
    </div>
  <?php endif; ?>

  <?php if (!empty($error)): ?>
    <div class="pu-alert error">
      <strong>Error:</strong>
      <span><?= e($error) ?></span>
    </div>
  <?php endif; ?>

  <div class="pu-hero">
    <div>
      <h1><?= e($doctor_name_for_title !== '' ? 'Update ' . $doctor_name_for_title : 'Update Doctor Profile') ?></h1>
      <p>
        Update your doctor profile section by section. Changed fields stay highlighted as Pending while the request is waiting for admin approval.
      </p>
    </div>

    <div class="pu-hero-actions">
      <a href="dashboard.php" class="pu-btn">Dashboard</a>

      <?php if ($doctor_profile_link !== ''): ?>
        <a href="<?= e($doctor_profile_link) ?>" target="_blank" rel="noopener" class="pu-btn">View Profile</a>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($error === '' && $doctor): ?>
    <div class="pu-layout">

      <aside class="pu-sidebar">
        <div class="pu-card">
          <div class="pu-card-head">
            <h3>Edit Sections</h3>
            <p>Choose one section and update only that part.</p>
          </div>

          <div class="pu-tabs">
            <?php foreach ($sections as $key => $label): ?>
              <?php if ($key === 'requests'): ?>
                <a href="profile-update.php?section=<?= e($key) ?>" class="pu-tab <?= $section === $key ? 'active' : '' ?>">
                  <span><?= e($label) ?></span>
                  <span class="pu-pill"><?= e((string)count($requests)) ?></span>
                </a>
              <?php else: ?>
                <?php
                  $section_fields = [
                    'basic' => ['name', 'name_bn', 'slug', 'degree', 'degree_bn', 'designation', 'designation_bn', 'bmdc_number', 'gender', 'languages', 'specialty_id', 'primary_hospital', 'primary_hospital_bn'],
                    'contact' => ['phone', 'whatsapp', 'email', 'doctor_location_source', 'doctor_division_id', 'doctor_district_id', 'doctor_thana_id'],
                    'professional' => ['experience_years', 'consultation_fee', 'follow_up_fee', 'video_consultation_fee'],
                    'image' => ['image', 'og_image', 'bio', 'bio_bn'],
                    'clinical' => ['education', 'education_bn', 'training', 'training_bn', 'fellowship', 'fellowship_bn', 'expertise', 'expertise_bn', 'appointment_note', 'appointment_note_bn'],
                    'status' => ['emergency_available', 'online_consultation', 'home_visit'],
                    'seo' => ['seo_title', 'seo_title_bn', 'seo_description', 'seo_description_bn', 'meta_keywords', 'meta_keywords_bn'],
                  ];

                  $updated_in_section = 0;

                  foreach ($section_fields[$key] ?? [] as $field_name) {
                      if (pu_is_updated($field_name, $doctor, $pending_data)) {
                          $updated_in_section++;
                      }
                  }
                ?>

                <a href="profile-update.php?section=<?= e($key) ?>" class="pu-tab <?= $section === $key ? 'active' : '' ?>">
                  <span><?= e($label) ?></span>
                  <?php if ($updated_in_section > 0): ?>
                    <span class="pu-pill updated"><?= e((string)$updated_in_section) ?></span>
                  <?php endif; ?>
                </a>
              <?php endif; ?>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="pu-card">
          <div class="pu-card-head">
            <h3>Pending Summary</h3>
          </div>

          <div class="pu-card-body">
            <div class="pu-summary">
              <div class="pu-summary-row">
                <span>Pending Request</span>
                <strong><?= $pending_request ? '#' . e((string)$pending_request['id']) : 'No' ?></strong>
              </div>

              <div class="pu-summary-row">
                <span>Pending Fields</span>
                <strong><?= e((string)count($pending_data)) ?></strong>
              </div>

              <div class="pu-summary-row">
                <span>Status</span>
                <strong><?= $pending_request ? 'Pending' : 'Ready' ?></strong>
              </div>
            </div>
          </div>
        </div>
      </aside>

      <main>
        <?php if ($section !== 'requests'): ?>
          <form class="pu-card" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="section" value="<?= e($section) ?>">
            <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">

            <div class="pu-card-head">
              <h2><?= e($sections[$section]) ?></h2>
              <p>No value change will be saved. Changed fields will stay highlighted while they are pending.</p>
            </div>

            <div class="pu-card-body">

              <?php if ($section === 'basic'): ?>
                <div class="pu-form-grid">
                  <div class="pu-language-pair">
                    <div class="pu-language-heading">
                      <strong>Doctor Name</strong>
                      <span>English left · বাংলা right</span>
                    </div>

                    <div class="<?= e(pu_field_class('name', $doctor, $pending_data)) ?>">
                      <label><span class="pu-lang-tag">EN</span> Doctor Name <span class="pu-required">*</span> <?= pu_badge('name', $doctor, $pending_data) ?></label>
                      <input type="text" name="name" value="<?= e(pu_display_value('name', $doctor, $pending_data)) ?>" required>
                    </div>

                    <div class="<?= e(pu_field_class('name_bn', $doctor, $pending_data)) ?>">
                      <label><span class="pu-lang-tag bn">বাংলা</span> ডাক্তারের নাম <?= pu_badge('name_bn', $doctor, $pending_data) ?></label>
                      <input type="text" name="name_bn" value="<?= e(pu_display_value('name_bn', $doctor, $pending_data)) ?>" lang="bn">
                    </div>
                  </div>

                  <div class="<?= e(pu_field_class('slug', $doctor, $pending_data)) ?> full">
                    <label>Profile Slug <span class="pu-required">*</span> <?= pu_badge('slug', $doctor, $pending_data) ?></label>
                    <div class="pu-inline">
                      <input type="text" id="slugInput" name="slug" minlength="6" maxlength="50" value="<?= e(pu_display_value('slug', $doctor, $pending_data)) ?>" required>
                      <button type="button" class="pu-btn" id="checkSlugBtn">Check Availability</button>
                    </div>
                    <div id="slugStatus"></div>
                    <div class="pu-small">Use lowercase letters, numbers and hyphens only. The change becomes active after admin approval.</div>
                  </div>

                  <?php foreach ([
                    ['degree', 'Degree', 'degree_bn', 'ডিগ্রি'],
                    ['designation', 'Designation', 'designation_bn', 'পদবি'],
                    ['primary_hospital', 'Primary Hospital', 'primary_hospital_bn', 'প্রধান হাসপাতাল'],
                  ] as [$en_field, $en_label, $bn_field, $bn_label]): ?>
                    <div class="pu-language-pair">
                      <div class="pu-language-heading">
                        <strong><?= e($en_label) ?></strong>
                        <span>English left · বাংলা right</span>
                      </div>

                      <div class="<?= e(pu_field_class($en_field, $doctor, $pending_data)) ?>">
                        <label><span class="pu-lang-tag">EN</span> <?= e($en_label) ?><?= in_array($en_field, ['degree', 'designation', 'primary_hospital'], true) ? ' <span class="pu-required">*</span>' : '' ?> <?= pu_badge($en_field, $doctor, $pending_data) ?></label>
                        <input type="text" name="<?= e($en_field) ?>" value="<?= e(pu_display_value($en_field, $doctor, $pending_data)) ?>" <?= in_array($en_field, ['degree', 'designation', 'primary_hospital'], true) ? 'required' : '' ?>>
                      </div>

                      <div class="<?= e(pu_field_class($bn_field, $doctor, $pending_data)) ?>">
                        <label><span class="pu-lang-tag bn">বাংলা</span> <?= e($bn_label) ?> <?= pu_badge($bn_field, $doctor, $pending_data) ?></label>
                        <input type="text" name="<?= e($bn_field) ?>" value="<?= e(pu_display_value($bn_field, $doctor, $pending_data)) ?>" lang="bn">
                      </div>
                    </div>
                  <?php endforeach; ?>

                  <div class="<?= e(pu_field_class('bmdc_number', $doctor, $pending_data)) ?>">
                    <label>BMDC Number <?= pu_badge('bmdc_number', $doctor, $pending_data) ?></label>
                    <input type="text" name="bmdc_number" value="<?= e(pu_display_value('bmdc_number', $doctor, $pending_data)) ?>">
                  </div>

                  <div class="<?= e(pu_field_class('languages', $doctor, $pending_data)) ?>">
                    <label>Languages <?= pu_badge('languages', $doctor, $pending_data) ?></label>
                    <input type="text" name="languages" value="<?= e(pu_display_value('languages', $doctor, $pending_data)) ?>" placeholder="Bangla, English, Hindi">
                  </div>

                  <div class="<?= e(pu_field_class('specialty_id', $doctor, $pending_data)) ?>">
                    <label>Specialty <span class="pu-required">*</span> <?= pu_badge('specialty_id', $doctor, $pending_data) ?></label>
                    <?php $selected_specialty = (int)pu_display_value('specialty_id', $doctor, $pending_data); ?>
                    <select name="specialty_id" required>
                      <option value="">Select Specialty</option>
                      <?php foreach ($specialties as $specialty): ?>
                        <option value="<?= e((string)$specialty['id']) ?>" <?= $selected_specialty === (int)$specialty['id'] ? 'selected' : '' ?>><?= e($specialty['label'] ?? '') ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>

                  <div class="<?= e(pu_field_class('gender', $doctor, $pending_data)) ?>">
                    <label>Gender <span class="pu-required">*</span> <?= pu_badge('gender', $doctor, $pending_data) ?></label>
                    <?php $gender = pu_display_value('gender', $doctor, $pending_data); ?>
                    <select name="gender" required>
                      <option value="">Select Gender</option>
                      <option value="Male" <?= $gender === 'Male' ? 'selected' : '' ?>>Male</option>
                      <option value="Female" <?= $gender === 'Female' ? 'selected' : '' ?>>Female</option>
                      <option value="Other" <?= $gender === 'Other' ? 'selected' : '' ?>>Other</option>
                    </select>
                  </div>

                </div>
              <?php endif; ?>

              <?php if ($section === 'contact'): ?>
                <div class="pu-form-grid">
                  <?php foreach ([['phone', 'Phone'], ['whatsapp', 'WhatsApp'], ['email', 'Email']] as [$field, $label]): ?>
                    <div class="<?= e(pu_field_class($field, $doctor, $pending_data)) ?>">
                      <label><?= e($label) ?> <?= pu_badge($field, $doctor, $pending_data) ?></label>
                      <input type="<?= $field === 'email' ? 'email' : 'text' ?>" name="<?= e($field) ?>" value="<?= e(pu_display_value($field, $doctor, $pending_data)) ?>">
                    </div>
                  <?php endforeach; ?>

                  <div class="<?= e(pu_field_class('doctor_location_source', $doctor, $pending_data)) ?>">
                    <label>Location Source <?= pu_badge('doctor_location_source', $doctor, $pending_data) ?></label>
                    <?php $location_source = pu_display_value('doctor_location_source', $doctor, $pending_data, 'auto'); ?>
                    <select name="doctor_location_source" id="doctor_location_source">
                      <option value="auto" <?= $location_source !== 'manual' ? 'selected' : '' ?>>Auto from first chamber</option>
                      <option value="manual" <?= $location_source === 'manual' ? 'selected' : '' ?>>Select manually</option>
                    </select>
                  </div>

                  <div class="pu-info-box full">Auto mode keeps the doctor location connected to the first chamber hospital. Select Manual only when you need a different profile location.</div>

                  <div class="<?= e(pu_field_class('doctor_division_id', $doctor, $pending_data)) ?> pu-location-field">
                    <label>Division <?= pu_badge('doctor_division_id', $doctor, $pending_data) ?></label>
                    <?php $selected_division = (int)pu_display_value('doctor_division_id', $doctor, $pending_data); ?>
                    <select name="doctor_division_id" id="doctor_division">
                      <option value="">Select Division</option>
                      <?php foreach ($divisions as $division): ?>
                        <option value="<?= e((string)$division['id']) ?>" <?= $selected_division === (int)$division['id'] ? 'selected' : '' ?>><?= e($division['label'] ?? '') ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>

                  <div class="<?= e(pu_field_class('doctor_district_id', $doctor, $pending_data)) ?> pu-location-field">
                    <label>District <?= pu_badge('doctor_district_id', $doctor, $pending_data) ?></label>
                    <select name="doctor_district_id" id="doctor_district" data-current="<?= e(pu_display_value('doctor_district_id', $doctor, $pending_data)) ?>"><option value="">Select District</option></select>
                  </div>

                  <div class="<?= e(pu_field_class('doctor_thana_id', $doctor, $pending_data)) ?> pu-location-field">
                    <label>Thana / Upazila <?= pu_badge('doctor_thana_id', $doctor, $pending_data) ?></label>
                    <select name="doctor_thana_id" id="doctor_thana" data-current="<?= e(pu_display_value('doctor_thana_id', $doctor, $pending_data)) ?>"><option value="">Select Thana / Upazila</option></select>
                  </div>
                </div>
              <?php endif; ?>

              <?php if ($section === 'professional'): ?>
                <div class="pu-form-grid">
                  <div class="<?= e(pu_field_class('experience_years', $doctor, $pending_data)) ?>">
                    <label>Starting Year <?= pu_badge('experience_years', $doctor, $pending_data) ?></label>
                    <input type="number" name="experience_years" min="1900" max="<?= e(date('Y')) ?>" value="<?= e(pu_display_value('experience_years', $doctor, $pending_data)) ?>" placeholder="2010">
                    <div class="pu-small">Enter the year you started professional practice.</div>
                  </div>

                  <?php foreach ([
                    ['consultation_fee', 'Consultation Fee'],
                    ['follow_up_fee', 'Follow-up Fee'],
                    ['video_consultation_fee', 'Video Consultation Fee'],
                  ] as [$field, $label]): ?>
                    <div class="<?= e(pu_field_class($field, $doctor, $pending_data)) ?>">
                      <label><?= e($label) ?> <?= pu_badge($field, $doctor, $pending_data) ?></label>
                      <input type="number" min="0" step="0.01" name="<?= e($field) ?>" value="<?= e(pu_display_value($field, $doctor, $pending_data)) ?>">
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>

              <?php if ($section === 'image'): ?>
                <div class="pu-form-grid">
                  <div class="<?= e(pu_field_class('image', $doctor, $pending_data)) ?>">
                    <label>Doctor Image <?= pu_badge('image', $doctor, $pending_data) ?></label>
                    <?php if ($current_image_url !== ''): ?><img class="pu-image-preview" src="<?= e($current_image_url) ?>" alt="Doctor image preview"><?php endif; ?>
                    <input type="file" name="image" accept="image/jpeg,image/png,image/webp">
                    <div class="pu-small">JPG, PNG or WebP. Maximum 8MB. The uploaded image stays pending until approval.</div>
                  </div>

                  <div class="<?= e(pu_field_class('og_image', $doctor, $pending_data)) ?>">
                    <label>Open Graph Image <?= pu_badge('og_image', $doctor, $pending_data) ?></label>
                    <?php if ($current_og_image_url !== ''): ?><img class="pu-image-preview pu-image-preview-wide" src="<?= e($current_og_image_url) ?>" alt="OG image preview"><?php endif; ?>
                    <input type="file" name="og_image" accept="image/jpeg,image/png,image/webp">
                    <div class="pu-small">Used when the doctor profile is shared on social platforms.</div>
                  </div>

                  <div class="pu-language-pair">
                    <div class="pu-language-heading">
                      <strong>Biography</strong>
                      <span>English left · বাংলা right</span>
                    </div>

                    <div class="<?= e(pu_field_class('bio', $doctor, $pending_data)) ?>">
                      <label><span class="pu-lang-tag">EN</span> Biography <?= pu_badge('bio', $doctor, $pending_data) ?></label>
                      <textarea name="bio"><?= e(pu_display_value('bio', $doctor, $pending_data)) ?></textarea>
                    </div>

                    <div class="<?= e(pu_field_class('bio_bn', $doctor, $pending_data)) ?>">
                      <label><span class="pu-lang-tag bn">বাংলা</span> জীবনী <?= pu_badge('bio_bn', $doctor, $pending_data) ?></label>
                      <textarea name="bio_bn" lang="bn"><?= e(pu_display_value('bio_bn', $doctor, $pending_data)) ?></textarea>
                    </div>
                  </div>
                </div>
              <?php endif; ?>

              <?php if ($section === 'clinical'): ?>
                <div class="pu-form-grid">
                  <?php foreach ([
                    ['education', 'Education', 'education_bn', 'শিক্ষাগত যোগ্যতা'],
                    ['training', 'Training', 'training_bn', 'প্রশিক্ষণ'],
                    ['fellowship', 'Fellowship', 'fellowship_bn', 'ফেলোশিপ'],
                    ['expertise', 'Expertise', 'expertise_bn', 'দক্ষতা'],
                    ['appointment_note', 'Appointment Note', 'appointment_note_bn', 'অ্যাপয়েন্টমেন্ট নির্দেশনা'],
                  ] as [$en_field, $en_label, $bn_field, $bn_label]): ?>
                    <div class="pu-language-pair">
                      <div class="pu-language-heading">
                        <strong><?= e($en_label) ?></strong>
                        <span>English left · বাংলা right</span>
                      </div>

                      <div class="<?= e(pu_field_class($en_field, $doctor, $pending_data)) ?>">
                        <label><span class="pu-lang-tag">EN</span> <?= e($en_label) ?> <?= pu_badge($en_field, $doctor, $pending_data) ?></label>
                        <textarea name="<?= e($en_field) ?>"><?= e(pu_display_value($en_field, $doctor, $pending_data)) ?></textarea>
                      </div>

                      <div class="<?= e(pu_field_class($bn_field, $doctor, $pending_data)) ?>">
                        <label><span class="pu-lang-tag bn">বাংলা</span> <?= e($bn_label) ?> <?= pu_badge($bn_field, $doctor, $pending_data) ?></label>
                        <textarea name="<?= e($bn_field) ?>" lang="bn"><?= e(pu_display_value($bn_field, $doctor, $pending_data)) ?></textarea>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>

              <?php if ($section === 'status'): ?>
                <div class="pu-form-grid">
                  <div class="pu-field full">
                    <label>Patient Service Options</label>
                    <div class="pu-checks">
                      <?php foreach (['emergency_available' => 'Emergency Available', 'online_consultation' => 'Online Consultation', 'home_visit' => 'Home Visit'] as $field => $label): ?>
                        <?php $checked = (int)pu_display_value($field, $doctor, $pending_data) === 1; ?>
                        <label class="pu-check <?= pu_is_updated($field, $doctor, $pending_data) ? 'is-updated' : '' ?>">
                          <input type="checkbox" name="<?= e($field) ?>" value="1" <?= $checked ? 'checked' : '' ?>>
                          <span><?= e($label) ?></span>
                          <?= pu_badge($field, $doctor, $pending_data) ?>
                        </label>
                      <?php endforeach; ?>
                    </div>
                  </div>
                  <div class="pu-info-box full">Verified status, Featured Profile, publishing status and featured duration remain admin-controlled.</div>
                </div>
              <?php endif; ?>

              <?php if ($section === 'seo'): ?>
                <div class="pu-form-grid">
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

                    $en_mode = pu_display_value($en_mode_field, $doctor, $pending_data, 'auto') === 'manual' ? 'manual' : 'auto';
                    $bn_mode = pu_display_value($bn_mode_field, $doctor, $pending_data, 'auto') === 'manual' ? 'manual' : 'auto';

                    $en_value = $en_mode === 'auto' ? ($auto_seo_preview[$en_field] ?? '') : pu_display_value($en_field, $doctor, $pending_data);
                    $bn_value = $bn_mode === 'auto' ? ($auto_seo_preview[$bn_field] ?? '') : pu_display_value($bn_field, $doctor, $pending_data);
                    ?>
                    <div class="pu-language-pair">
                      <div class="pu-language-heading">
                        <strong><?= e($en_label) ?></strong>
                        <span>English left · বাংলা right</span>
                      </div>

                      <div class="<?= e(pu_field_class($en_field, $doctor, $pending_data)) ?> pu-seo-field">
                        <div class="pu-label-row">
                          <label><span class="pu-lang-tag">EN</span> <?= e($en_label) ?> <?= pu_badge($en_field, $doctor, $pending_data) ?></label>
                          <select class="pu-seo-mode" name="<?= e($en_mode_field) ?>" data-target="<?= e($en_field) ?>">
                            <option value="auto" <?= $en_mode === 'auto' ? 'selected' : '' ?>>Auto</option>
                            <option value="manual" <?= $en_mode === 'manual' ? 'selected' : '' ?>>Manual</option>
                          </select>
                        </div>
                        <?php if ($en_tag === 'textarea'): ?>
                          <textarea name="<?= e($en_field) ?>" id="<?= e($en_field) ?>" maxlength="<?= e((string)$en_max) ?>" data-auto-value="<?= e($auto_seo_preview[$en_field] ?? '') ?>"><?= e($en_value) ?></textarea>
                        <?php else: ?>
                          <input type="text" name="<?= e($en_field) ?>" id="<?= e($en_field) ?>" maxlength="<?= e((string)$en_max) ?>" data-auto-value="<?= e($auto_seo_preview[$en_field] ?? '') ?>" value="<?= e($en_value) ?>">
                        <?php endif; ?>
                        <div class="pu-small"><span data-counter-for="<?= e($en_field) ?>">0</span> / <?= e((string)$en_max) ?> characters</div>
                      </div>

                      <div class="<?= e(pu_field_class($bn_field, $doctor, $pending_data)) ?> pu-seo-field">
                        <div class="pu-label-row">
                          <label><span class="pu-lang-tag bn">বাংলা</span> <?= e($bn_label) ?> <?= pu_badge($bn_field, $doctor, $pending_data) ?></label>
                          <select class="pu-seo-mode" name="<?= e($bn_mode_field) ?>" data-target="<?= e($bn_field) ?>">
                            <option value="auto" <?= $bn_mode === 'auto' ? 'selected' : '' ?>>Auto</option>
                            <option value="manual" <?= $bn_mode === 'manual' ? 'selected' : '' ?>>Manual</option>
                          </select>
                        </div>
                        <?php if ($bn_tag === 'textarea'): ?>
                          <textarea name="<?= e($bn_field) ?>" id="<?= e($bn_field) ?>" maxlength="<?= e((string)$bn_max) ?>" data-auto-value="<?= e($auto_seo_preview[$bn_field] ?? '') ?>" lang="bn"><?= e($bn_value) ?></textarea>
                        <?php else: ?>
                          <input type="text" name="<?= e($bn_field) ?>" id="<?= e($bn_field) ?>" maxlength="<?= e((string)$bn_max) ?>" data-auto-value="<?= e($auto_seo_preview[$bn_field] ?? '') ?>" value="<?= e($bn_value) ?>" lang="bn">
                        <?php endif; ?>
                        <div class="pu-small"><span data-counter-for="<?= e($bn_field) ?>">0</span> / <?= e((string)$bn_max) ?> characters</div>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>

              <div class="pu-submit">
                <span class="pu-small">Only changed values will be saved into pending update request.</span>

                <div class="pu-hero-actions">
                  <button type="submit" class="pu-btn pu-btn-primary">Save <?= e($sections[$section]) ?> Update</button>
                  <a href="profile-update.php?section=requests" class="pu-btn">View Requests</a>
                </div>
              </div>
            </div>
          </form>
        <?php endif; ?>

        <?php if ($section === 'requests'): ?>
          <div class="pu-card">
            <div class="pu-card-head">
              <h2>Recent Profile Update Requests</h2>
              <p>Your submitted doctor profile update requests.</p>
            </div>

            <div class="pu-card-body">
              <div class="pu-table-wrap">
                <table class="pu-table">
                  <thead>
                    <tr>
                      <th>ID</th>
                      <th>Type</th>
                      <th>Status</th>
                      <th>Pending Fields</th>
                      <th>Date</th>
                    </tr>
                  </thead>

                  <tbody>
                    <?php if (!$requests): ?>
                      <tr>
                        <td colspan="5">No update request found.</td>
                      </tr>
                    <?php endif; ?>

                    <?php foreach ($requests as $req): ?>
                      <?php $req_data = pu_decode_request_data($req['request_data'] ?? ''); ?>
                      <tr>
                        <td>#<?= e((string)($req['id'] ?? '')) ?></td>
                        <td><?= e($req['request_type'] ?? '') ?></td>
                        <td>
                          <span class="pu-status <?= e($req['status'] ?? 'pending') ?>">
                            <?= e(ucfirst((string)($req['status'] ?? 'pending'))) ?>
                          </span>
                        </td>
                        <td><?= e((string)count($req_data)) ?></td>
                        <td><?= e($req['created_at'] ?? '') ?></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        <?php endif; ?>
      </main>

    </div>
  <?php endif; ?>

</div>

<script>
(function () {
  const slugInput = document.getElementById('slugInput');
  const checkSlugBtn = document.getElementById('checkSlugBtn');
  const slugStatus = document.getElementById('slugStatus');

  function normalizeSlug(value) {
    return String(value || '')
      .toLowerCase()
      .replace(/[^a-z0-9-]+/g, '-')
      .replace(/-+/g, '-')
      .replace(/^-+|-+$/g, '')
      .slice(0, 50);
  }

  function setSlugStatus(type, message) {
    if (!slugStatus) return;
    slugStatus.className = type === 'ok' ? 'ok' : 'error';
    slugStatus.textContent = message;
    slugStatus.style.display = 'block';
  }

  async function checkSlugAvailability() {
    if (!slugInput) return false;

    const slug = normalizeSlug(slugInput.value);
    slugInput.value = slug;

    if (slug.length < 6 || slug.length > 50) {
      setSlugStatus('error', 'Slug must be minimum 6 and maximum 50 characters.');
      return false;
    }

    try {
      setSlugStatus('error', 'Checking slug...');
      const response = await fetch('profile-update.php?ajax=check_slug&slug=' + encodeURIComponent(slug), {
        headers: { 'Accept': 'application/json' }
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
        slugStatus.style.display = 'none';
        slugStatus.textContent = '';
        slugStatus.className = '';
      }
    });
  }

  if (checkSlugBtn) checkSlugBtn.addEventListener('click', checkSlugAvailability);

  const districts = <?= json_encode($districts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
  const thanas = <?= json_encode($thanas, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
  const locationSource = document.getElementById('doctor_location_source');
  const divisionSelect = document.getElementById('doctor_division');
  const districtSelect = document.getElementById('doctor_district');
  const thanaSelect = document.getElementById('doctor_thana');

  function resetSelect(select, label) {
    if (!select) return;
    select.innerHTML = '<option value="">' + label + '</option>';
  }

  function fillDistricts() {
    if (!divisionSelect || !districtSelect) return;
    const divisionId = String(divisionSelect.value || '');
    const current = String(districtSelect.dataset.current || '');
    resetSelect(districtSelect, 'Select District');
    resetSelect(thanaSelect, 'Select Thana / Upazila');

    districts.filter(row => String(row.division_id || '') === divisionId).forEach(row => {
      const option = document.createElement('option');
      option.value = row.id;
      option.textContent = row.label || row.name || row.id;
      if (String(row.id) === current) option.selected = true;
      districtSelect.appendChild(option);
    });

    fillThanas();
  }

  function fillThanas() {
    if (!districtSelect || !thanaSelect) return;
    const districtId = String(districtSelect.value || '');
    const current = String(thanaSelect.dataset.current || '');
    resetSelect(thanaSelect, 'Select Thana / Upazila');

    thanas.filter(row => String(row.district_id || '') === districtId).forEach(row => {
      const option = document.createElement('option');
      option.value = row.id;
      option.textContent = row.label || row.name || row.id;
      if (String(row.id) === current) option.selected = true;
      thanaSelect.appendChild(option);
    });
  }

  function toggleLocationFields() {
    const manual = !locationSource || locationSource.value === 'manual';
    document.querySelectorAll('.pu-location-field select').forEach(select => {
      select.disabled = !manual;
    });
  }

  if (divisionSelect) {
    divisionSelect.addEventListener('change', function () {
      if (districtSelect) districtSelect.dataset.current = '';
      if (thanaSelect) thanaSelect.dataset.current = '';
      fillDistricts();
    });
    fillDistricts();
  }

  if (districtSelect) {
    districtSelect.addEventListener('change', function () {
      if (thanaSelect) thanaSelect.dataset.current = '';
      fillThanas();
    });
  }

  if (locationSource) {
    locationSource.addEventListener('change', toggleLocationFields);
    toggleLocationFields();
  }

  function updateCounter(field) {
    const counter = document.querySelector('[data-counter-for="' + field.id + '"]');
    if (counter) counter.textContent = String(field.value.length);
  }

  function applySeoMode(select) {
    const target = document.getElementById(select.dataset.target || '');
    if (!target) return;
    const auto = select.value !== 'manual';
    target.readOnly = auto;
    if (auto) target.value = target.dataset.autoValue || '';
    updateCounter(target);
  }

  document.querySelectorAll('.pu-seo-mode').forEach(select => {
    select.addEventListener('change', () => applySeoMode(select));
    applySeoMode(select);
  });

  document.querySelectorAll('[data-counter-for]').forEach(counter => {
    const target = document.getElementById(counter.dataset.counterFor || '');
    if (!target) return;
    target.addEventListener('input', () => updateCounter(target));
    updateCounter(target);
  });
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>