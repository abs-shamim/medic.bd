<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/auth.php';

require_user_login();

$auth_user = function_exists('current_user_data') ? current_user_data() : null;

if (!$auth_user) {
    $auth_user = $_SESSION['user'] ?? null;
}

if (!$auth_user || empty($auth_user['id'])) {
    redirect('login.php');
}

$current_user_id = (int)$auth_user['id'];

$type = trim((string)($_GET['type'] ?? $_POST['profile_type'] ?? 'doctor'));
$profile_id = (int)($_GET['id'] ?? $_POST['profile_id'] ?? 0);

$errors = [];
$success = '';

if (!in_array($type, ['doctor', 'hospital'], true)) {
    http_response_code(404);
    exit('Invalid claim request.');
}

if ($profile_id <= 0) {
    http_response_code(404);
    exit('Invalid profile ID.');
}

if (!function_exists('ucs_table_exists')) {
    function ucs_table_exists(string $table): bool
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

if (!function_exists('ucs_column_exists')) {
    function ucs_column_exists(string $table, string $column): bool
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

if (!function_exists('ucs_add_column_if_missing')) {
    function ucs_add_column_if_missing(string $table, string $column, string $definition): void
    {
        global $pdo;

        if (!ucs_table_exists($table) || ucs_column_exists($table, $column)) {
            return;
        }

        try {
            $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
        } catch (Throwable $e) {
            // Keep page working if ALTER permission is not available.
        }
    }
}

if (!function_exists('ucs_ensure_columns')) {
    function ucs_ensure_columns(): void
    {
        ucs_add_column_if_missing('users', 'claimed_doctor_id', "INT UNSIGNED DEFAULT NULL");
        ucs_add_column_if_missing('users', 'claimed_hospital_id', "INT UNSIGNED DEFAULT NULL");

        ucs_add_column_if_missing('profile_claims', 'doctor_id', "INT UNSIGNED DEFAULT NULL");
        ucs_add_column_if_missing('profile_claims', 'hospital_id', "INT UNSIGNED DEFAULT NULL");
        ucs_add_column_if_missing('profile_claims', 'profile_id', "INT UNSIGNED DEFAULT NULL");
        ucs_add_column_if_missing('profile_claims', 'profile_name', "VARCHAR(190) NULL");
        ucs_add_column_if_missing('profile_claims', 'name', "VARCHAR(190) NULL");
        ucs_add_column_if_missing('profile_claims', 'specialty', "VARCHAR(190) NULL");
        ucs_add_column_if_missing('profile_claims', 'phone', "VARCHAR(50) NULL");
        ucs_add_column_if_missing('profile_claims', 'email', "VARCHAR(190) NULL");
        ucs_add_column_if_missing('profile_claims', 'message', "TEXT NULL");
        ucs_add_column_if_missing('profile_claims', 'license_number', "VARCHAR(190) NULL");
        ucs_add_column_if_missing('profile_claims', 'proof_text', "TEXT NULL");
        ucs_add_column_if_missing('profile_claims', 'proof_file', "VARCHAR(255) NULL");
        ucs_add_column_if_missing('profile_claims', 'admin_note', "TEXT NULL");
        ucs_add_column_if_missing('profile_claims', 'created_at', "DATETIME NULL");
        ucs_add_column_if_missing('profile_claims', 'updated_at', "DATETIME NULL");
    }
}

if (!function_exists('ucs_e')) {
    function ucs_e($value): string
    {
        if (function_exists('e')) {
            return e($value);
        }

        return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('ucs_clean_text')) {
    function ucs_clean_text(string $value, int $max_length = 500): string
    {
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = strip_tags($value);
        $value = preg_replace('/https?:\/\/[^\s]+/iu', '', (string)$value);
        $value = preg_replace('/www\.[^\s]+/iu', '', (string)$value);
        $value = preg_replace('/\s+/u', ' ', (string)$value);
        $value = trim((string)$value);

        if (function_exists('mb_substr')) {
            return mb_substr($value, 0, $max_length, 'UTF-8');
        }

        return substr($value, 0, $max_length);
    }
}

if (!function_exists('ucs_url')) {
    function ucs_url(string $path): string
    {
        $path = trim($path);

        if ($path === '') {
            return '';
        }

        if (preg_match('/^https?:\/\//i', $path)) {
            return $path;
        }

        $path = str_replace('\\', '/', $path);
        $path = preg_replace('#^(\.\./)+#', '', $path);
        $path = ltrim($path, '/');

        if (function_exists('site_url')) {
            return site_url($path);
        }

        if (defined('BASE_URL') && BASE_URL !== '') {
            return rtrim((string)BASE_URL, '/') . '/' . $path;
        }

        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? '';

        return $host !== '' ? $scheme . '://' . $host . '/' . $path : '/' . $path;
    }
}

if (!function_exists('ucs_asset_url')) {
    function ucs_asset_url(string $path, string $fallback = ''): string
    {
        $path = trim($path);

        if ($path === '') {
            $path = trim($fallback);
        }

        if ($path === '') {
            return '';
        }

        if (preg_match('/^https?:\/\//i', $path)) {
            return $path;
        }

        $path = str_replace('\\', '/', $path);
        $path = preg_replace('#^(\.\./)+#', '', $path);
        $path = ltrim($path, '/');

        return ucs_url($path);
    }
}

if (!function_exists('ucs_site_setting')) {
    function ucs_site_setting(string $key, string $default = ''): string
    {
        global $pdo;

        $key = trim($key);

        if ($key === '') {
            return $default;
        }

        if (function_exists('get_site_setting')) {
            $value = trim((string)get_site_setting($key, $default));
            return $value !== '' ? $value : $default;
        }

        if (!ucs_table_exists('site_settings')) {
            return $default;
        }

        try {
            $stmt = $pdo->prepare("
                SELECT setting_value
                FROM site_settings
                WHERE setting_key = :setting_key
                LIMIT 1
            ");

            $stmt->execute([
                ':setting_key' => $key,
            ]);

            $value = trim((string)$stmt->fetchColumn());

            return $value !== '' ? $value : $default;
        } catch (Throwable $e) {
            return $default;
        }
    }
}

if (!function_exists('ucs_svg_fallback')) {
    function ucs_svg_fallback(string $type, string $name = ''): string
    {
        $name = trim($name);
        $letter = $type === 'hospital' ? 'H' : 'D';

        if ($name !== '') {
            $letter = function_exists('mb_substr')
                ? mb_substr($name, 0, 1, 'UTF-8')
                : substr($name, 0, 1);
        }

        $bg1 = $type === 'hospital' ? '#0969da' : '#2da44e';
        $bg2 = $type === 'hospital' ? '#8250df' : '#0969da';
        $label = htmlspecialchars(strtoupper($letter), ENT_QUOTES, 'UTF-8');

        $svg = '
        <svg xmlns="http://www.w3.org/2000/svg" width="240" height="240" viewBox="0 0 240 240">
            <defs>
                <linearGradient id="g" x1="0" y1="0" x2="1" y2="1">
                    <stop offset="0%" stop-color="' . $bg1 . '"/>
                    <stop offset="100%" stop-color="' . $bg2 . '"/>
                </linearGradient>
            </defs>
            <rect width="240" height="240" rx="34" fill="url(#g)"/>
            <circle cx="120" cy="88" r="38" fill="rgba(255,255,255,.9)"/>
            <rect x="54" y="140" width="132" height="46" rx="23" fill="rgba(255,255,255,.9)"/>
            <text x="120" y="126" text-anchor="middle" font-family="Arial, sans-serif" font-size="64" font-weight="800" fill="#ffffff">' . $label . '</text>
        </svg>';

        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }
}

if (!function_exists('ucs_profile_image_url')) {
    function ucs_profile_image_url(string $type, array $profile): string
    {
        if ($type === 'doctor') {
            $default_image = ucs_site_setting(
                'default_doctor_image',
                '../assets/images/default-doctor.webp'
            );

            $image = trim((string)(
                $profile['image']
                ?? $profile['photo']
                ?? $profile['profile_image']
                ?? $profile['doctor_image']
                ?? $profile['avatar']
                ?? ''
            ));

            return ucs_asset_url($image, $default_image);
        }

        $default_image = ucs_site_setting(
            'default_hospital_image',
            '../assets/images/default-hospital.webp'
        );

        $image = trim((string)(
            $profile['image']
            ?? $profile['photo']
            ?? $profile['logo']
            ?? $profile['hospital_logo']
            ?? $profile['profile_image']
            ?? ''
        ));

        return ucs_asset_url($image, $default_image);
    }
}

if (!function_exists('ucs_profile_url')) {
    function ucs_profile_url(string $type, array $profile): string
    {
        $slug = trim((string)($profile['slug'] ?? ''));

        if ($type === 'doctor' && $slug !== '') {
            return ucs_url('doctor/' . $slug);
        }

        if ($type === 'hospital' && $slug !== '') {
            return ucs_url('hospital/' . $slug);
        }

        return ucs_url($type === 'doctor' ? 'doctors' : 'hospitals');
    }
}

if (!function_exists('ucs_get_profile')) {
    function ucs_get_profile(string $type, int $profile_id): array
    {
        global $pdo;

        $table = $type === 'doctor' ? 'doctors' : 'hospitals';

        if (!ucs_table_exists($table)) {
            return [];
        }

        try {
            if ($type === 'doctor') {
                $specialty_join = (ucs_table_exists('specialties') && ucs_column_exists('doctors', 'specialty_id'))
                    ? "LEFT JOIN specialties s ON s.id = d.specialty_id"
                    : "";
                $specialty_select = $specialty_join !== ''
                    ? "s.name AS specialty_name, s.slug AS specialty_slug,"
                    : "NULL AS specialty_name, NULL AS specialty_slug,";

                $stmt = $pdo->prepare("
                    SELECT
                        d.*,
                        {$specialty_select}
                        'doctor' AS profile_type
                    FROM doctors d
                    {$specialty_join}
                    WHERE d.id = :id
                    LIMIT 1
                ");
            } else {
                $stmt = $pdo->prepare("
                    SELECT *, 'hospital' AS profile_type
                    FROM hospitals
                    WHERE id = :id
                    LIMIT 1
                ");
            }

            $stmt->execute([':id' => $profile_id]);

            return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }
}

if (!function_exists('ucs_get_user')) {
    function ucs_get_user(int $user_id): array
    {
        global $pdo;

        if (!ucs_table_exists('users')) {
            return [];
        }

        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => $user_id]);

            return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }
}

if (!function_exists('ucs_get_connected_user')) {
    function ucs_get_connected_user(string $type, int $profile_id): ?array
    {
        global $pdo;

        if ($profile_id <= 0 || !ucs_table_exists('users')) {
            return null;
        }

        try {
            if ($type === 'doctor') {
                $stmt = $pdo->prepare("
                    SELECT id, name, email
                    FROM users
                    WHERE claimed_doctor_id = :profile_id
                    LIMIT 1
                ");
            } else {
                $stmt = $pdo->prepare("
                    SELECT id, name, email
                    FROM users
                    WHERE claimed_hospital_id = :profile_id
                    LIMIT 1
                ");
            }

            $stmt->execute([':profile_id' => $profile_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            return $user ?: null;
        } catch (Throwable $e) {
            return null;
        }
    }
}

if (!function_exists('ucs_has_pending_claim')) {
    function ucs_has_pending_claim(int $user_id, string $type, int $profile_id): bool
    {
        global $pdo;

        if (!ucs_table_exists('profile_claims')) {
            return false;
        }

        try {
            if ($type === 'doctor') {
                $stmt = $pdo->prepare("
                    SELECT id
                    FROM profile_claims
                    WHERE user_id = :user_id
                    AND claim_type = 'doctor'
                    AND doctor_id = :profile_id
                    AND status = 'pending'
                    LIMIT 1
                ");
            } else {
                $stmt = $pdo->prepare("
                    SELECT id
                    FROM profile_claims
                    WHERE user_id = :user_id
                    AND claim_type = 'hospital'
                    AND hospital_id = :profile_id
                    AND status = 'pending'
                    LIMIT 1
                ");
            }

            $stmt->execute([
                ':user_id' => $user_id,
                ':profile_id' => $profile_id,
            ]);

            return (bool)$stmt->fetch();
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('ucs_slugify')) {
    function ucs_slugify(string $text, string $fallback = 'profile'): string
    {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = strip_tags($text);
        $text = strtolower(trim($text));

        if (function_exists('iconv')) {
            $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
            if ($converted !== false) {
                $text = $converted;
            }
        }

        $text = preg_replace('/[^a-z0-9]+/i', '-', (string)$text);
        $text = trim((string)$text, '-');

        if ($text === '') {
            $text = $fallback;
        }

        return substr($text, 0, 80);
    }
}

if (!function_exists('ucs_convert_image_to_webp')) {
    function ucs_convert_image_to_webp(string $tmp_name, string $mime, string $target_path, string &$error = ''): bool
    {
        $error = '';

        if (!function_exists('imagewebp')) {
            $error = 'WEBP conversion is not supported on this server. Please enable PHP GD WebP support.';
            return false;
        }

        try {
            if ($mime === 'image/jpeg') {
                $image = @imagecreatefromjpeg($tmp_name);
            } elseif ($mime === 'image/png') {
                $image = @imagecreatefrompng($tmp_name);

                if ($image) {
                    imagepalettetotruecolor($image);
                    imagealphablending($image, true);
                    imagesavealpha($image, true);
                }
            } elseif ($mime === 'image/webp') {
                $image = @imagecreatefromwebp($tmp_name);
            } elseif ($mime === 'image/gif') {
                $image = @imagecreatefromgif($tmp_name);
            } else {
                $error = 'Unsupported image type.';
                return false;
            }

            if (!$image) {
                $error = 'Image could not be processed.';
                return false;
            }

            $saved = imagewebp($image, $target_path, 85);
            imagedestroy($image);

            if (!$saved) {
                $error = 'Image could not be converted to WEBP.';
                return false;
            }

            return true;
        } catch (Throwable $e) {
            $error = 'Image conversion failed.';
            return false;
        }
    }
}

if (!function_exists('ucs_upload_proof_file')) {
    function ucs_upload_proof_file(string $field_name, string $profile_name = 'profile', string &$error = ''): string
    {
        $error = '';

        if (empty($_FILES[$field_name]['name'])) {
            return '';
        }

        if (!isset($_FILES[$field_name]) || (int)$_FILES[$field_name]['error'] !== UPLOAD_ERR_OK) {
            $error = 'Proof file upload failed.';
            return '';
        }

        $max_size = 5 * 1024 * 1024;

        $original_name = (string)($_FILES[$field_name]['name'] ?? '');
        $tmp_name = (string)($_FILES[$field_name]['tmp_name'] ?? '');
        $file_size = (int)($_FILES[$field_name]['size'] ?? 0);
        $extension = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));

        if ($file_size > $max_size) {
            $error = 'Proof file size must be 5MB or less.';
            return '';
        }

        if (!is_uploaded_file($tmp_name)) {
            $error = 'Invalid proof file upload.';
            return '';
        }

        $mime = '';

        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);

            if ($finfo) {
                $mime = (string)finfo_file($finfo, $tmp_name);
                finfo_close($finfo);
            }
        }

        $image_mimes = [
            'image/jpeg',
            'image/png',
            'image/webp',
            'image/gif',
        ];

        $is_pdf = $mime === 'application/pdf' || $extension === 'pdf';
        $is_image = in_array($mime, $image_mimes, true);

        if (!$is_image && !$is_pdf) {
            $error = 'Only JPG, PNG, WEBP, GIF or PDF proof file is allowed.';
            return '';
        }

        if ($is_pdf && $extension !== 'pdf') {
            $error = 'Invalid PDF file.';
            return '';
        }

        $upload_dir = dirname(__DIR__, 2) . '/uploads/profile-claims/';

        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $base_name = ucs_slugify($profile_name, 'profile');
        $date_part = date('ymdHis');

        if ($is_image) {
            $new_name = $base_name . '-' . $date_part . '.webp';
            $target_path = $upload_dir . $new_name;

            if (file_exists($target_path)) {
                $new_name = $base_name . '-' . $date_part . '-' . bin2hex(random_bytes(3)) . '.webp';
                $target_path = $upload_dir . $new_name;
            }

            $convert_error = '';

            if (!ucs_convert_image_to_webp($tmp_name, $mime, $target_path, $convert_error)) {
                $error = $convert_error ?: 'Proof image could not be converted to WEBP.';
                return '';
            }

            return 'uploads/profile-claims/' . $new_name;
        }

        $new_name = $base_name . '-' . $date_part . '.pdf';
        $target_path = $upload_dir . $new_name;

        if (file_exists($target_path)) {
            $new_name = $base_name . '-' . $date_part . '-' . bin2hex(random_bytes(3)) . '.pdf';
            $target_path = $upload_dir . $new_name;
        }

        if (!move_uploaded_file($tmp_name, $target_path)) {
            $error = 'Proof file upload failed.';
            return '';
        }

        return 'uploads/profile-claims/' . $new_name;
    }
}

if (!ucs_table_exists('users')) {
    require_once __DIR__ . '/../includes/header.php';
    echo '<div class="card-padded"><div class="cp-alert error">Users table not found.</div></div>';
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

if (!ucs_table_exists('profile_claims')) {
    require_once __DIR__ . '/../includes/header.php';
    echo '<div class="card-padded"><div class="cp-alert error">Profile claim system is not ready. Please create the profile_claims table first.</div></div>';
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

ucs_ensure_columns();

$user = ucs_get_user($current_user_id);
$profile = ucs_get_profile($type, $profile_id);

if (!$user) {
    require_once __DIR__ . '/../includes/header.php';
    echo '<div class="card-padded"><div class="cp-alert error">User not found.</div></div>';
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

if (!$profile) {
    require_once __DIR__ . '/../includes/header.php';
    echo '<div class="card-padded"><div class="cp-alert error">Profile not found.</div><a href="claim.php" class="cp-btn cp-btn-outline">Back to Claim Page</a></div>';
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

$profile_name = trim((string)($profile['name'] ?? 'Profile'));
$profile_type_label = $type === 'doctor' ? 'Doctor Profile' : 'Hospital Profile';
$profile_url = ucs_profile_url($type, $profile);
$profile_image = ucs_profile_image_url($type, $profile);
$profile_svg_fallback = ucs_svg_fallback($type, $profile_name);

if ($type === 'doctor') {
    $profile_subtitle = trim(implode(' | ', array_filter([
        trim((string)($profile['designation'] ?? '')),
        trim((string)($profile['degree'] ?? '')),
        trim((string)($profile['specialty_name'] ?? $profile['specialty'] ?? '')),
    ])));
} else {
    $profile_subtitle = trim(implode(', ', array_filter([
        trim((string)($profile['type'] ?? $profile['hospital_type'] ?? '')),
        trim((string)($profile['address'] ?? '')),
        trim((string)($profile['city'] ?? $profile['district'] ?? '')),
    ])));
}

if ($profile_subtitle === '') {
    $profile_subtitle = $profile_type_label;
}

$claim_config = [
    'doctor' => [
        'page_title' => 'Claim Doctor Profile',
        'subtitle_type' => 'doctor profile',
        'subtitle_text' => 'Submit your ownership request for this doctor profile. Admin will verify your BMDC number, specialty, contact information, and proof before approving access.',
        'name_label' => 'Doctor / Authorized Person Name',
        'specialty_label' => 'Doctor Specialty',
        'specialty_placeholder' => 'Select Doctor Specialty',
        'license_label' => 'BMDC Registration Number',
        'phone_label' => 'Phone Number',
        'email_label' => 'Email Address',
        'proof_text_label' => 'Doctor Proof Details',
        'proof_text_help' => 'Write BMDC registration details, official contact information, chamber/hospital connection, or any valid doctor ownership proof.',
        'message_label' => 'Doctor Verification Message',
        'proof_file_label' => 'Doctor Proof File *',
        'proof_help' => 'Upload BMDC card, visiting card, appointment proof, chamber document, or any valid doctor proof. Image files will be converted to WEBP. PDF will stay PDF. Max size: 5MB.',
        'submit_button' => 'Submit Doctor Claim Request',
        'message' => 'Hello,

I am requesting verification to claim and manage this doctor profile. I confirm that the submitted BMDC number, specialty, phone number, email address, and proof details are correct.

Please review my information and approve this doctor profile claim.

Thank you.',
    ],
    'hospital' => [
        'page_title' => 'Claim Hospital Profile',
        'subtitle_type' => 'hospital profile',
        'subtitle_text' => 'Submit your ownership request for this hospital profile. Admin will verify your authorization, hospital type, registration/license details, contact information, and proof before approving access.',
        'name_label' => 'Authorized Person Name',
        'specialty_label' => 'Hospital Type',
        'specialty_placeholder' => 'Select Hospital Type',
        'license_label' => 'Hospital Registration / License Number',
        'phone_label' => 'Authorized Person Phone Number',
        'email_label' => 'Authorized Person Email Address',
        'proof_text_label' => 'Hospital Proof Details',
        'proof_text_help' => 'Write hospital license details, authorized representative information, official contact information, or any valid hospital ownership proof.',
        'message_label' => 'Hospital Verification Message',
        'proof_file_label' => 'Hospital Proof File *',
        'proof_help' => 'Upload hospital license, authorization document, visiting card, official letterhead, or any valid hospital proof. Image files will be converted to WEBP. PDF will stay PDF. Max size: 5MB.',
        'submit_button' => 'Submit Hospital Claim Request',
        'message' => 'Hello,

I am requesting verification to claim and manage this hospital profile. I confirm that I am authorized to update the hospital information, including contact details, address, departments, services, doctors, schedule, and profile content.

Please review the submitted information and approve this hospital profile claim.

Thank you.',
    ],
];

$current_claim_config = $claim_config[$type] ?? $claim_config['doctor'];


$specialty_options = [];

if ($type === 'doctor' && ucs_table_exists('specialties')) {
    try {
        $specialty_name_column = ucs_column_exists('specialties', 'name') ? 'name' : (ucs_column_exists('specialties', 'title') ? 'title' : 'id');
        $specialty_bn_column = ucs_column_exists('specialties', 'name_bn') ? 'name_bn' : '';
        $specialty_status_where = ucs_column_exists('specialties', 'status') ? "WHERE status = 'active'" : '';

        $specialty_select = "id, {$specialty_name_column} AS name";

        if ($specialty_bn_column !== '') {
            $specialty_select .= ", {$specialty_bn_column} AS name_bn";
        } else {
            $specialty_select .= ", NULL AS name_bn";
        }

        $stmt = $pdo->query("
            SELECT {$specialty_select}
            FROM specialties
            {$specialty_status_where}
            ORDER BY {$specialty_name_column} ASC
        ");

        $specialty_options = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        $specialty_options = [];
    }
}

$hospital_type_options = [
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

$connected_user = ucs_get_connected_user($type, $profile_id);

if ($connected_user && (int)$connected_user['id'] !== $current_user_id) {
    require_once __DIR__ . '/../includes/header.php';
    echo '<main class="cp-page"><div class="cp-shell">
            <div class="cp-alert error">This profile is already connected with another user.</div>
            <a href="claim.php" class="cp-btn cp-btn-outline">Back to Claim Page</a>
          </div></main>';
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

if (ucs_has_pending_claim($current_user_id, $type, $profile_id)) {
    require_once __DIR__ . '/../includes/header.php';
    echo '<main class="cp-page"><div class="cp-shell">
            <div class="cp-alert error">You already have a pending claim request for this profile.</div>
            <a href="claim.php" class="cp-btn cp-btn-outline">Back to Claim Page</a>
          </div></main>';
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

$basic_name = trim((string)($user['name'] ?? ''));
$basic_email = trim((string)($user['email'] ?? ''));
$basic_phone = trim((string)($user['phone'] ?? ''));

$basic_authorized_person_name = trim((string)($user['authorized_person_name'] ?? ''));
$basic_authorized_person_position = trim((string)($user['authorized_person_position'] ?? ''));
$basic_authorized_person_phone = trim((string)($user['authorized_person_phone'] ?? ''));
$basic_authorized_person_email = trim((string)($user['authorized_person_email'] ?? ''));
$basic_hospital_address = trim((string)($user['hospital_address'] ?? $user['address'] ?? ''));
$basic_hospital_type = trim((string)($user['hospital_type'] ?? $user['type'] ?? ''));
$basic_hospital_license = trim((string)(
    $user['license_number']
    ?? $user['registration_number']
    ?? $user['registration_no']
    ?? ''
));

$basic_bmdc = trim((string)(
    $user['bmdc_number']
    ?? $user['bmdc_no']
    ?? $user['bmdc']
    ?? $user['registration_number']
    ?? $user['registration_no']
    ?? ''
));

$basic_specialty = trim((string)(
    $user['doctor_specialty']
    ?? $user['specialty']
    ?? $user['department']
    ?? ''
));

if ($type === 'hospital') {
    $auto_name = $basic_authorized_person_name !== '' ? $basic_authorized_person_name : ($basic_name !== '' ? $basic_name : $profile_name);
    $auto_phone = $basic_authorized_person_phone !== '' ? $basic_authorized_person_phone : ($basic_phone !== '' ? $basic_phone : trim((string)(
        $profile['phone']
        ?? $profile['mobile']
        ?? $profile['whatsapp']
        ?? $profile['whatsapp_number']
        ?? ''
    )));
    $auto_email = $basic_authorized_person_email !== '' ? $basic_authorized_person_email : ($basic_email !== '' ? $basic_email : trim((string)($profile['email'] ?? '')));
    $auto_specialty = $basic_hospital_type !== '' ? $basic_hospital_type : trim((string)(
        $profile['type']
        ?? $profile['hospital_type']
        ?? 'Hospital'
    ));
    $auto_bmdc = $basic_hospital_license !== '' ? $basic_hospital_license : trim((string)(
        $profile['registration_number']
        ?? $profile['registration_no']
        ?? $profile['license_number']
        ?? ''
    ));
} else {
    $auto_name = $basic_name !== '' ? $basic_name : $profile_name;
    $auto_phone = $basic_phone !== '' ? $basic_phone : trim((string)(
        $profile['phone']
        ?? $profile['mobile']
        ?? $profile['whatsapp']
        ?? $profile['whatsapp_number']
        ?? ''
    ));
    $auto_email = $basic_email !== '' ? $basic_email : trim((string)($profile['email'] ?? ''));
    $auto_specialty = $basic_specialty !== '' ? $basic_specialty : trim((string)(
        $profile['specialty_name']
        ?? $profile['specialty']
        ?? $profile['department']
        ?? ''
    ));
    $auto_bmdc = $basic_bmdc !== '' ? $basic_bmdc : trim((string)(
        $profile['bmdc_number']
        ?? $profile['bmdc_no']
        ?? $profile['bmdc']
        ?? $profile['registration_number']
        ?? $profile['registration_no']
        ?? $profile['reg_no']
        ?? ''
    ));
}

if ($type === 'hospital') {
    $auto_proof_text = trim(
        'I am claiming this hospital profile as the authorized owner/representative. Please verify and approve my access to manage this profile.' . "\n\n" .
        'Hospital Name: ' . $profile_name . "\n" .
        'Authorized Person Name: ' . ($auto_name !== '' ? $auto_name : 'Not specified') . "\n" .
        'Authorized Person Position: ' . ($basic_authorized_person_position !== '' ? $basic_authorized_person_position : 'Not specified') . "\n" .
        'Hospital Type: ' . ($auto_specialty !== '' ? $auto_specialty : 'Not specified') . "\n" .
        'Registration / License Number: ' . ($auto_bmdc !== '' ? $auto_bmdc : 'Not specified') . "\n" .
        'Authorized Person Phone: ' . ($auto_phone !== '' ? $auto_phone : 'Not specified') . "\n" .
        'Authorized Person Email: ' . ($auto_email !== '' ? $auto_email : 'Not specified') . "\n" .
        'Hospital Address: ' . ($basic_hospital_address !== '' ? $basic_hospital_address : 'Not specified') . "\n" .
        'Profile Link: ' . $profile_url
    );
} else {
    $auto_proof_text = trim(
        'I am claiming this doctor profile as the authorized representative/owner. Please verify and approve my access to manage this profile.' . "\n\n" .
        'Doctor Name: ' . $profile_name . "\n" .
        'Authorized Person Name: ' . ($auto_name !== '' ? $auto_name : 'Not specified') . "\n" .
        'Specialty: ' . ($auto_specialty !== '' ? $auto_specialty : 'Not specified') . "\n" .
        'BMDC Number: ' . ($auto_bmdc !== '' ? $auto_bmdc : 'Not specified') . "\n" .
        'Phone: ' . ($auto_phone !== '' ? $auto_phone : 'Not specified') . "\n" .
        'Email: ' . ($auto_email !== '' ? $auto_email : 'Not specified') . "\n" .
        'Profile Link: ' . $profile_url
    );
}

$auto_message = $current_claim_config['message'];

if (empty($_SESSION['user_claim_csrf'])) {
    $_SESSION['user_claim_csrf'] = bin2hex(random_bytes(32));
}

$csrf_token = $_SESSION['user_claim_csrf'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $posted_token = (string)($_POST['csrf_token'] ?? '');

    if (!hash_equals($csrf_token, $posted_token)) {
        $errors[] = 'Invalid security token. Please refresh the page and try again.';
    } else {
        $claim_name = ucs_clean_text((string)($_POST['claim_name'] ?? ''), 150);
        $claim_specialty = ucs_clean_text((string)($_POST['specialty'] ?? ''), 190);
        $claim_phone = ucs_clean_text((string)($_POST['claim_phone'] ?? ''), 80);
        $claim_email = trim((string)($_POST['claim_email'] ?? ''));
        $license_number = ucs_clean_text((string)($_POST['license_number'] ?? ''), 190);
        $proof_text = trim((string)($_POST['proof_text'] ?? ''));
        $message = trim((string)($_POST['message'] ?? ''));

        if ($claim_name === '') {
            $errors[] = 'Name is required.';
        }

        if ($claim_specialty === '') {
            $errors[] = $type === 'doctor' ? 'Specialty is required.' : 'Hospital type is required.';
        }

        if ($claim_phone === '') {
            $errors[] = 'Phone is required.';
        }

        if ($claim_email === '') {
            $errors[] = 'Email is required.';
        }

        if ($claim_email !== '' && !filter_var($claim_email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address.';
        }

        if ($proof_text === '') {
            $errors[] = 'Proof Text is required.';
        }

        if ($message === '') {
            $errors[] = 'Verification Message is required.';
        }

        if (empty($_FILES['proof_file']['name'])) {
            $errors[] = $type === 'doctor' ? 'Doctor Proof File is required.' : 'Hospital Proof File is required.';
        }

        $proof_file = '';

        if (!$errors) {
            $upload_error = '';
            $proof_file = ucs_upload_proof_file('proof_file', $profile_name, $upload_error);

            if ($upload_error !== '') {
                $errors[] = $upload_error;
            }
        }

        if (!$errors) {
            $doctor_id = $type === 'doctor' ? $profile_id : null;
            $hospital_id = $type === 'hospital' ? $profile_id : null;

            $columns = [
                'user_id',
                'claim_type',
                'doctor_id',
                'hospital_id',
                'name',
                'phone',
                'email',
                'message',
                'status',
            ];

            $values = [
                ':user_id',
                ':claim_type',
                ':doctor_id',
                ':hospital_id',
                ':name',
                ':phone',
                ':email',
                ':message',
                ':status',
            ];

            $params = [
                ':user_id' => $current_user_id,
                ':claim_type' => $type,
                ':doctor_id' => $doctor_id,
                ':hospital_id' => $hospital_id,
                ':name' => $claim_name,
                ':phone' => $claim_phone,
                ':email' => $claim_email,
                ':message' => $message,
                ':status' => 'pending',
            ];

            if (ucs_column_exists('profile_claims', 'specialty')) {
                $columns[] = 'specialty';
                $values[] = ':specialty';
                $params[':specialty'] = $claim_specialty;
            }

            if (ucs_column_exists('profile_claims', 'license_number')) {
                $columns[] = 'license_number';
                $values[] = ':license_number';
                $params[':license_number'] = $license_number;
            }

            if (ucs_column_exists('profile_claims', 'proof_text')) {
                $columns[] = 'proof_text';
                $values[] = ':proof_text';
                $params[':proof_text'] = $proof_text;
            }

            if ($proof_file !== '' && ucs_column_exists('profile_claims', 'proof_file')) {
                $columns[] = 'proof_file';
                $values[] = ':proof_file';
                $params[':proof_file'] = $proof_file;
            }

            if (ucs_column_exists('profile_claims', 'profile_id')) {
                $columns[] = 'profile_id';
                $values[] = ':profile_id';
                $params[':profile_id'] = $profile_id;
            }

            if (ucs_column_exists('profile_claims', 'profile_name')) {
                $columns[] = 'profile_name';
                $values[] = ':profile_name';
                $params[':profile_name'] = $profile_name;
            }

            if (ucs_column_exists('profile_claims', 'created_at')) {
                $columns[] = 'created_at';
                $values[] = 'NOW()';
            }

            if (ucs_column_exists('profile_claims', 'updated_at')) {
                $columns[] = 'updated_at';
                $values[] = 'NOW()';
            }

            try {
                $stmt = $pdo->prepare("
                    INSERT INTO profile_claims (" . implode(', ', $columns) . ")
                    VALUES (" . implode(', ', $values) . ")
                ");

                $stmt->execute($params);

                $_SESSION['user_claim_csrf'] = bin2hex(random_bytes(32));

                redirect('claim.php?success=' . urlencode('Claim request submitted successfully. Please wait for admin approval.'));
            } catch (Throwable $e) {
                $errors[] = 'Claim request could not be submitted right now. Please try again later.';
            }
        }
    }
}

$page_title = $current_claim_config['page_title'] . ' - ' . $profile_name;
$meta_description = 'Claim this ' . $current_claim_config['subtitle_type'] . ' and request ownership verification for ' . $profile_name . '.';

require_once __DIR__ . '/../includes/header.php';
?>

<link rel="stylesheet" href="<?php echo BASE_URL; ?>/user/assets/css/user-claim-submit.css">

<main class="cp-page">
  <div class="cp-shell">

    <div class="cp-profile-box">
      <a href="<?= ucs_e($profile_url) ?>" target="_blank" rel="noopener">
        <img
          src="<?= ucs_e($profile_image) ?>"
          alt="<?= ucs_e($profile_name) ?>"
          loading="lazy"
          data-fallback-src="<?= ucs_e($profile_svg_fallback) ?>"
        >
      </a>

      <div>
        <strong>
          <a href="<?= ucs_e($profile_url) ?>" target="_blank" rel="noopener">
            <?= ucs_e($profile_name) ?>
          </a>
        </strong>
        <span><?= ucs_e($profile_subtitle) ?></span>
      </div>
    </div>

    <section class="cp-card">
      <h1 class="cp-title"><?= ucs_e($current_claim_config['page_title']) ?></h1>

      <p class="cp-subtitle">
        <strong><?= ucs_e($profile_name) ?></strong><br>
        <?= ucs_e($current_claim_config['subtitle_text']) ?>
      </p>

      <?php if ($success): ?>
        <div class="cp-alert success"><?= ucs_e($success) ?></div>
      <?php endif; ?>

      <?php if ($errors): ?>
        <div class="cp-alert error">
          <?php foreach ($errors as $error): ?>
            <div><?= ucs_e($error) ?></div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <form method="post" enctype="multipart/form-data" class="cp-form">
        <input type="hidden" name="csrf_token" value="<?= ucs_e($csrf_token) ?>">
        <input type="hidden" name="profile_type" value="<?= ucs_e($type) ?>">
        <input type="hidden" name="profile_id" value="<?= ucs_e((string)$profile_id) ?>">

        <div class="cp-field">
          <label for="claim_name"><?= ucs_e($current_claim_config['name_label']) ?> *</label>
          <input
            class="cp-input"
            type="text"
            id="claim_name"
            name="claim_name"
            value="<?= ucs_e($auto_name) ?>"
            required
            maxlength="150"
            autocomplete="name"
          >
        </div>

        <div class="cp-field">
          <label for="claim_specialty">
            <?= ucs_e($current_claim_config['specialty_label']) ?> *
          </label>

          <?php if ($type === 'doctor'): ?>
            <select
              class="cp-input"
              id="claim_specialty"
              name="specialty"
              required
            >
              <option value=""><?= ucs_e($current_claim_config['specialty_placeholder']) ?></option>
              <?php foreach ($specialty_options as $specialty): ?>
                <?php
                  $sp_name = trim((string)($specialty['name'] ?? ''));
                  $sp_bn = trim((string)($specialty['name_bn'] ?? ''));
                  $sp_label = $sp_bn !== '' ? $sp_name . ' / ' . $sp_bn : $sp_name;
                ?>

                <?php if ($sp_name !== ''): ?>
                  <option value="<?= ucs_e($sp_name) ?>" <?= $auto_specialty === $sp_name ? 'selected' : '' ?>>
                    <?= ucs_e($sp_label) ?>
                  </option>
                <?php endif; ?>
              <?php endforeach; ?>

              <?php if ($auto_specialty !== '' && !in_array($auto_specialty, array_map(static function ($item) { return trim((string)($item['name'] ?? '')); }, $specialty_options), true)): ?>
                <option value="<?= ucs_e($auto_specialty) ?>" selected>
                  <?= ucs_e($auto_specialty) ?>
                </option>
              <?php endif; ?>
            </select>

            <?php if (!$specialty_options): ?>
              <small>No specialty was found in the database. Please add specialties from admin first.</small>
            <?php endif; ?>
          <?php else: ?>
            <select
              class="cp-input"
              id="claim_specialty"
              name="specialty"
              required
            >
              <option value=""><?= ucs_e($current_claim_config['specialty_placeholder']) ?></option>
              <?php foreach ($hospital_type_options as $hospital_type_option): ?>
                <option value="<?= ucs_e($hospital_type_option) ?>" <?= $auto_specialty === $hospital_type_option ? 'selected' : '' ?>>
                  <?= ucs_e($hospital_type_option) ?>
                </option>
              <?php endforeach; ?>

              <?php if ($auto_specialty !== '' && !in_array($auto_specialty, $hospital_type_options, true)): ?>
                <option value="<?= ucs_e($auto_specialty) ?>" selected>
                  <?= ucs_e($auto_specialty) ?>
                </option>
              <?php endif; ?>
            </select>
          <?php endif; ?>
        </div>

        <div class="cp-field">
          <label for="claim_license">
            <?= ucs_e($current_claim_config['license_label']) ?>
          </label>
          <input
            class="cp-input"
            type="text"
            id="claim_license"
            name="license_number"
            value="<?= ucs_e($auto_bmdc) ?>"
            maxlength="190"
          >
        </div>

        <div class="cp-field">
          <label for="claim_phone"><?= ucs_e($current_claim_config['phone_label']) ?> *</label>
          <input
            class="cp-input"
            type="text"
            id="claim_phone"
            name="claim_phone"
            value="<?= ucs_e($auto_phone) ?>"
            required
            maxlength="80"
            autocomplete="tel"
          >
        </div>

        <div class="cp-field">
          <label for="claim_email"><?= ucs_e($current_claim_config['email_label']) ?> *</label>
          <input
            class="cp-input"
            type="email"
            id="claim_email"
            name="claim_email"
            value="<?= ucs_e($auto_email) ?>"
            required
            maxlength="180"
            autocomplete="email"
          >
        </div>

        <div class="cp-field">
          <label for="claim_proof_text"><?= ucs_e($current_claim_config['proof_text_label']) ?> *</label>
          <textarea
            class="cp-textarea"
            id="claim_proof_text"
            name="proof_text"
            rows="5"
            required
          ><?= ucs_e($auto_proof_text) ?></textarea>
          <small><?= ucs_e($current_claim_config['proof_text_help']) ?></small>
        </div>

        <div class="cp-field">
          <label for="claim_message"><?= ucs_e($current_claim_config['message_label']) ?> *</label>
          <textarea
            class="cp-textarea"
            id="claim_message"
            name="message"
            rows="5"
            required
            maxlength="1000"
          ><?= ucs_e($auto_message) ?></textarea>
        </div>

        <div class="cp-field">
          <label for="claim_proof_file"><?= ucs_e($current_claim_config['proof_file_label']) ?></label>
          <input
            class="cp-input"
            type="file"
            id="claim_proof_file"
            name="proof_file"
            accept=".jpg,.jpeg,.png,.webp,.gif,.pdf"
            required
          >
          <small><?= ucs_e($current_claim_config['proof_help']) ?></small>
        </div>

        <div class="cp-actions">
          <button type="submit" class="cp-btn"><?= ucs_e($current_claim_config['submit_button']) ?></button>
          <a href="claim.php" class="cp-btn cp-btn-outline">Back to Claim Page</a>
        </div>
      </form>
    </section>

  </div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>