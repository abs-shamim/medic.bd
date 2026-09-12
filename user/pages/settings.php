<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/auth.php';

$user = require_user_login();

$page_title = 'Account Settings';

$error = '';
$success = '';

$user_id = (int)($user['id'] ?? 0);

if ($user_id <= 0) {
    redirect('logout.php');
}

if (!function_exists('settings_table_exists')) {
    function settings_table_exists(PDO $pdo, string $table): bool
    {
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

if (!function_exists('settings_get_user_columns')) {
    function settings_get_user_columns(PDO $pdo, bool $refresh = false): array
    {
        static $columns = null;

        if ($columns !== null && !$refresh) {
            return $columns;
        }

        $columns = [];

        try {
            $stmt = $pdo->query("SHOW COLUMNS FROM `users`");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($rows as $row) {
                if (!empty($row['Field'])) {
                    $columns[] = (string)$row['Field'];
                }
            }
        } catch (Throwable $e) {
            $columns = [];
        }

        return $columns;
    }
}

if (!function_exists('settings_column_exists')) {
    function settings_column_exists(PDO $pdo, string $column): bool
    {
        return in_array($column, settings_get_user_columns($pdo), true);
    }
}

if (!function_exists('settings_add_column_if_missing')) {
    function settings_add_column_if_missing(PDO $pdo, string $column, string $definition): void
    {
        try {
            if (!settings_column_exists($pdo, $column)) {
                $pdo->exec("ALTER TABLE `users` ADD COLUMN `{$column}` {$definition}");
                settings_get_user_columns($pdo, true);
            }
        } catch (Throwable $e) {
            // Keep page working if ALTER permission is not available.
        }
    }
}

if (!function_exists('settings_ensure_columns')) {
    function settings_ensure_columns(PDO $pdo): void
    {
        settings_add_column_if_missing($pdo, 'phone', "VARCHAR(50) NULL");
        settings_add_column_if_missing($pdo, 'image', "VARCHAR(255) NULL");

        settings_add_column_if_missing($pdo, 'division_id', "INT UNSIGNED DEFAULT NULL");
        settings_add_column_if_missing($pdo, 'district_id', "INT UNSIGNED DEFAULT NULL");
        settings_add_column_if_missing($pdo, 'thana_id', "INT UNSIGNED DEFAULT NULL");

        settings_add_column_if_missing($pdo, 'division_name', "VARCHAR(150) NULL");
        settings_add_column_if_missing($pdo, 'district_name', "VARCHAR(150) NULL");
        settings_add_column_if_missing($pdo, 'thana_name', "VARCHAR(150) NULL");

        settings_add_column_if_missing($pdo, 'area', "VARCHAR(190) NULL");
        settings_add_column_if_missing($pdo, 'road_no', "VARCHAR(120) NULL");
        settings_add_column_if_missing($pdo, 'house_no', "VARCHAR(120) NULL");
        settings_add_column_if_missing($pdo, 'post_code', "VARCHAR(30) NULL");
        settings_add_column_if_missing($pdo, 'address', "TEXT NULL");

        settings_add_column_if_missing($pdo, 'bmdc_number', "VARCHAR(100) NULL");
        settings_add_column_if_missing($pdo, 'doctor_specialty', "VARCHAR(190) NULL");

        settings_add_column_if_missing($pdo, 'license_number', "VARCHAR(150) NULL");
        settings_add_column_if_missing($pdo, 'authorized_person_name', "VARCHAR(190) NULL");
        settings_add_column_if_missing($pdo, 'authorized_person_position', "VARCHAR(190) NULL");
        settings_add_column_if_missing($pdo, 'authorized_person_phone', "VARCHAR(80) NULL");
        settings_add_column_if_missing($pdo, 'authorized_person_email', "VARCHAR(190) NULL");
        settings_add_column_if_missing($pdo, 'hospital_type', "VARCHAR(150) NULL");
        settings_add_column_if_missing($pdo, 'hospital_address', "TEXT NULL");

        settings_add_column_if_missing($pdo, 'updated_at', "DATETIME NULL");
    }
}

if (!function_exists('settings_e')) {
    function settings_e($value): string
    {
        if (function_exists('e')) {
            return e($value);
        }

        return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('settings_url')) {
    function settings_url(string $path): string
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

if (!function_exists('settings_csrf_token')) {
    function settings_csrf_token(): string
    {
        if (empty($_SESSION['user_settings_csrf'])) {
            $_SESSION['user_settings_csrf'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['user_settings_csrf'];
    }
}

if (!function_exists('settings_check_csrf')) {
    function settings_check_csrf(): bool
    {
        $posted = (string)($_POST['csrf_token'] ?? '');
        $saved = (string)($_SESSION['user_settings_csrf'] ?? '');

        return $posted !== '' && $saved !== '' && hash_equals($saved, $posted);
    }
}

if (!function_exists('settings_get_account')) {
    function settings_get_account(PDO $pdo, int $user_id): array
    {
        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => $user_id]);

            return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }
}

if (!function_exists('settings_update_account')) {
    function settings_update_account(PDO $pdo, int $user_id, array $fields, ?string &$db_error = null): bool
    {
        $db_error = null;

        try {
            $columns = settings_get_user_columns($pdo, true);
            $sets = [];
            $params = [':id' => $user_id];

            foreach ($fields as $column => $value) {
                if (!in_array($column, $columns, true)) {
                    continue;
                }

                $key = ':' . $column;
                $sets[] = "`{$column}` = {$key}";
                $params[$key] = $value;
            }

            if (in_array('updated_at', $columns, true)) {
                $sets[] = "`updated_at` = NOW()";
            }

            if (!$sets) {
                $db_error = 'No matching column found.';
                return false;
            }

            $stmt = $pdo->prepare("
                UPDATE users
                SET " . implode(', ', $sets) . "
                WHERE id = :id
                LIMIT 1
            ");

            return $stmt->execute($params);
        } catch (Throwable $e) {
            $db_error = $e->getMessage();
            return false;
        }
    }
}

if (!function_exists('settings_address_table_columns')) {
    function settings_address_table_columns(PDO $pdo, string $table): array
    {
        try {
            $stmt = $pdo->query("SHOW COLUMNS FROM `{$table}`");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $columns = [];

            foreach ($rows as $row) {
                if (!empty($row['Field'])) {
                    $columns[] = (string)$row['Field'];
                }
            }

            return $columns;
        } catch (Throwable $e) {
            return [];
        }
    }
}

if (!function_exists('settings_address_name_column')) {
    function settings_address_name_column(PDO $pdo, string $table, string $lang = 'en'): string
    {
        $columns = settings_address_table_columns($pdo, $table);

        if ($lang === 'bn' && in_array('name_bn', $columns, true)) {
            return 'name_bn';
        }

        if (in_array('name_en', $columns, true)) {
            return 'name_en';
        }

        if (in_array('name', $columns, true)) {
            return 'name';
        }

        if (in_array('title', $columns, true)) {
            return 'title';
        }

        return 'id';
    }
}

if (!function_exists('settings_get_address_options')) {
    function settings_get_address_options(PDO $pdo, string $table, array $where = [], string $lang = 'en'): array
    {
        $allowed = ['divisions', 'districts', 'thanas'];

        if (!in_array($table, $allowed, true) || !settings_table_exists($pdo, $table)) {
            return [];
        }

        $name_col = settings_address_name_column($pdo, $table, $lang);
        $sql = "SELECT id, {$name_col} AS name FROM {$table}";
        $params = [];

        if ($where) {
            $parts = [];

            foreach ($where as $column => $value) {
                $parts[] = "{$column} = :{$column}";
                $params[':' . $column] = $value;
            }

            $sql .= " WHERE " . implode(' AND ', $parts);
        }

        $sql .= " ORDER BY {$name_col} ASC";

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }
}

if (!function_exists('settings_address_name_by_id')) {
    function settings_address_name_by_id(PDO $pdo, string $table, int $id, string $lang = 'en'): string
    {
        if ($id <= 0) {
            return '';
        }

        $allowed = ['divisions', 'districts', 'thanas'];

        if (!in_array($table, $allowed, true) || !settings_table_exists($pdo, $table)) {
            return '';
        }

        $name_col = settings_address_name_column($pdo, $table, $lang);

        try {
            $stmt = $pdo->prepare("
                SELECT {$name_col} AS name
                FROM {$table}
                WHERE id = :id
                LIMIT 1
            ");
            $stmt->execute([':id' => $id]);

            return trim((string)($stmt->fetchColumn() ?: ''));
        } catch (Throwable $e) {
            return '';
        }
    }
}

if (!function_exists('settings_image_slug')) {
    function settings_image_slug(string $text): string
    {
        $text = trim($text);

        if ($text === '') {
            return 'profile';
        }

        if (function_exists('iconv')) {
            $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);

            if ($converted !== false && trim($converted) !== '') {
                $text = $converted;
            }
        }

        $text = strtolower($text);
        $text = preg_replace('/[^a-z0-9]+/i', '-', $text);
        $text = trim((string)$text, '-');

        return $text !== '' ? $text : 'profile';
    }
}

if (!function_exists('settings_upload_user_image')) {
    function settings_upload_user_image(string $field, string $old_image = '', string &$upload_error = '', string $profile_name = 'profile'): string
    {
        $upload_error = '';

        if (empty($_FILES[$field]['name'])) {
            return $old_image;
        }

        if (!isset($_FILES[$field]) || (int)$_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
            $upload_error = 'Image upload failed.';
            return $old_image;
        }

        $tmp = (string)($_FILES[$field]['tmp_name'] ?? '');
        $size = (int)($_FILES[$field]['size'] ?? 0);

        if (!is_uploaded_file($tmp)) {
            $upload_error = 'Invalid image upload.';
            return $old_image;
        }

        if ($size > 4 * 1024 * 1024) {
            $upload_error = 'Image size must be 4MB or less.';
            return $old_image;
        }

        $mime = '';

        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);

            if ($finfo) {
                $mime = (string)finfo_file($finfo, $tmp);
                finfo_close($finfo);
            }
        }

        if ($mime === '' && function_exists('mime_content_type')) {
            $mime = (string)mime_content_type($tmp);
        }

        $allowed_mimes = [
            'image/jpeg',
            'image/png',
            'image/webp',
            'image/gif',
        ];

        if (!in_array($mime, $allowed_mimes, true)) {
            $upload_error = 'Only valid JPG, PNG, WEBP or GIF image is allowed.';
            return $old_image;
        }

        if (!function_exists('imagecreatefromstring') || !function_exists('imagewebp')) {
            $upload_error = 'Server image conversion support is not enabled. Please enable PHP GD with WEBP support.';
            return $old_image;
        }

        $image_data = @file_get_contents($tmp);

        if ($image_data === false || $image_data === '') {
            $upload_error = 'Image could not be read.';
            return $old_image;
        }

        $source_image = @imagecreatefromstring($image_data);

        if (!$source_image) {
            $upload_error = 'Invalid or unsupported image file.';
            return $old_image;
        }

        $width = imagesx($source_image);
        $height = imagesy($source_image);

        if ($width <= 0 || $height <= 0) {
            imagedestroy($source_image);
            $upload_error = 'Invalid image size.';
            return $old_image;
        }

        $webp_image = imagecreatetruecolor($width, $height);

        if (!$webp_image) {
            imagedestroy($source_image);
            $upload_error = 'Image conversion failed.';
            return $old_image;
        }

        imagealphablending($webp_image, false);
        imagesavealpha($webp_image, true);

        $transparent = imagecolorallocatealpha($webp_image, 0, 0, 0, 127);
        imagefilledrectangle($webp_image, 0, 0, $width, $height, $transparent);

        imagecopy($webp_image, $source_image, 0, 0, 0, 0, $width, $height);

        $upload_dir = dirname(__DIR__, 2) . '/uploads/users/';

        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        if (!is_dir($upload_dir) || !is_writable($upload_dir)) {
            imagedestroy($source_image);
            imagedestroy($webp_image);
            $upload_error = 'Upload folder is not writable.';
            return $old_image;
        }

        $base_name = settings_image_slug($profile_name);
        $new_name = $base_name . '-' . date('ymdHis') . '.webp';
        $target = $upload_dir . $new_name;

        $counter = 1;

        while (file_exists($target)) {
            $new_name = $base_name . '-' . date('ymdHis') . '-' . $counter . '.webp';
            $target = $upload_dir . $new_name;
            $counter++;
        }

        $saved = imagewebp($webp_image, $target, 85);

        imagedestroy($source_image);
        imagedestroy($webp_image);

        if (!$saved || !file_exists($target)) {
            $upload_error = 'Image could not be converted to WEBP.';
            return $old_image;
        }

        return 'uploads/users/' . $new_name;
    }
}

settings_ensure_columns($pdo);
settings_get_user_columns($pdo, true);

$lang = trim((string)($_GET['lang'] ?? 'en'));

if (isset($_GET['ajax'])) {
    header('Content-Type: application/json; charset=utf-8');

    $ajax = trim((string)($_GET['ajax'] ?? ''));

    if ($ajax === 'districts') {
        $division_id = (int)($_GET['division_id'] ?? 0);
        echo json_encode(settings_get_address_options($pdo, 'districts', ['division_id' => $division_id], $lang));
        exit;
    }

    if ($ajax === 'thanas') {
        $district_id = (int)($_GET['district_id'] ?? 0);
        echo json_encode(settings_get_address_options($pdo, 'thanas', ['district_id' => $district_id], $lang));
        exit;
    }

    echo json_encode([]);
    exit;
}

$account = settings_get_account($pdo, $user_id);

if (!$account) {
    redirect('logout.php');
}

$csrf_token = settings_csrf_token();

$tab = trim((string)($_GET['tab'] ?? 'home'));
$allowed_tabs = ['home', 'profile', 'password', 'email'];

if (!in_array($tab, $allowed_tabs, true)) {
    $tab = 'home';
}

$account_type = trim((string)($account['user_type'] ?? $account['role'] ?? 'user'));

if ($account_type === 'hospital_owner') {
    $account_type_label = 'Hospital Owner';
} elseif ($account_type === 'doctor') {
    $account_type_label = 'Doctor';
} else {
    $account_type_label = ucfirst($account_type);
}

$specialty_options = [];

try {
    if (settings_table_exists($pdo, 'specialties')) {
        $stmt = $pdo->query("
            SELECT id, name, name_bn
            FROM specialties
            WHERE status = 'active'
            ORDER BY name ASC
        ");
        $specialty_options = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Throwable $e) {
    $specialty_options = [];
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

$authorized_position_options = [
    'Owner',
    'Managing Director',
    'Chairman',
    'Director',
    'Hospital Administrator',
    'Hospital Manager',
    'Medical Director',
    'Doctor In-Charge',
    'Authorized Representative',
    'Other',
];

$divisions = settings_get_address_options($pdo, 'divisions', [], $lang);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!settings_check_csrf()) {
        $error = 'Security token expired. Please refresh the page and try again.';
    } else {
        $action = trim((string)($_POST['action'] ?? ''));

        if ($action === 'update_profile') {
            $name = trim((string)($_POST['name'] ?? ''));
            $phone = trim((string)($_POST['phone'] ?? ''));

            $division_id = (int)($_POST['division_id'] ?? 0);
            $district_id = (int)($_POST['district_id'] ?? 0);
            $thana_id = (int)($_POST['thana_id'] ?? 0);

            $area = trim((string)($_POST['area'] ?? ''));
            $road_no = trim((string)($_POST['road_no'] ?? ''));
            $house_no = trim((string)($_POST['house_no'] ?? ''));
            $post_code = trim((string)($_POST['post_code'] ?? ''));

            $bmdc_number = trim((string)($_POST['bmdc_number'] ?? ''));
            $doctor_specialty = trim((string)($_POST['doctor_specialty'] ?? ''));

            $license_number = trim((string)($_POST['license_number'] ?? ''));
            $authorized_person_name = trim((string)($_POST['authorized_person_name'] ?? ''));
            $authorized_person_position = trim((string)($_POST['authorized_person_position'] ?? ''));
            $authorized_person_phone = trim((string)($_POST['authorized_person_phone'] ?? ''));
            $authorized_person_email = strtolower(trim((string)($_POST['authorized_person_email'] ?? '')));
            $hospital_type = trim((string)($_POST['hospital_type'] ?? ''));
            $hospital_address = trim((string)($_POST['hospital_address'] ?? ''));

            $division_name = settings_address_name_by_id($pdo, 'divisions', $division_id, $lang);
            $district_name = settings_address_name_by_id($pdo, 'districts', $district_id, $lang);
            $thana_name = settings_address_name_by_id($pdo, 'thanas', $thana_id, $lang);

            $thana_with_post_code = $thana_name;

            if ($post_code !== '') {
                $thana_with_post_code = $thana_name !== '' ? $thana_name . '-' . $post_code : $post_code;
            }

            $address_parts = array_filter([
                $house_no,
                $road_no,
                $area,
                $thana_with_post_code,
                $district_name,
                $division_name,
            ]);

            $address = implode(', ', $address_parts);

            if ($name === '' || $phone === '') {
                $error = 'Name and phone number are required.';
            } elseif ($division_id <= 0 || $district_id <= 0) {
                $error = 'Please select division and district.';
            } elseif ($account_type === 'doctor' && ($bmdc_number === '' || $doctor_specialty === '')) {
                $error = 'BMDC registration number and specialty are required.';
            } elseif (
                $account_type === 'hospital_owner'
                && (
                    $hospital_type === ''
                    || $license_number === ''
                    || $authorized_person_name === ''
                    || $authorized_person_position === ''
                    || $authorized_person_phone === ''
                    || $authorized_person_email === ''
                )
            ) {
                $error = 'Hospital type, license number, authorized person name, position, phone, and email are required.';
            } elseif ($account_type === 'hospital_owner' && !in_array($authorized_person_position, $authorized_position_options, true)) {
                $error = 'Please select a valid authorized person position.';
            } elseif ($account_type === 'hospital_owner' && !filter_var($authorized_person_email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Please enter a valid authorized person email.';
            } elseif ($account_type === 'hospital_owner' && !in_array($hospital_type, $hospital_type_options, true)) {
                $error = 'Please select a valid hospital type.';
            } else {
                $upload_error = '';
                $old_image = trim((string)($account['image'] ?? ''));
                $image = settings_upload_user_image('image', $old_image, $upload_error, $name);

                if ($upload_error !== '') {
                    $error = $upload_error;
                } else {
                    $fields = [
                        'name' => $name,
                        'phone' => $phone,
                        'image' => $image,
                        'division_id' => $division_id ?: null,
                        'district_id' => $district_id ?: null,
                        'thana_id' => $thana_id ?: null,
                        'division_name' => $division_name,
                        'district_name' => $district_name,
                        'thana_name' => $thana_name,
                        'area' => $area,
                        'road_no' => $road_no,
                        'house_no' => $house_no,
                        'post_code' => $post_code,
                        'address' => $address,
                    ];

                    if ($account_type === 'doctor') {
                        $fields['bmdc_number'] = $bmdc_number;
                        $fields['doctor_specialty'] = $doctor_specialty;

                        $fields['license_number'] = null;
                        $fields['authorized_person_name'] = null;
                        $fields['authorized_person_position'] = null;
                        $fields['authorized_person_phone'] = null;
                        $fields['authorized_person_email'] = null;
                        $fields['hospital_type'] = null;
                        $fields['hospital_address'] = null;
                    }

                    if ($account_type === 'hospital_owner') {
                        $fields['bmdc_number'] = null;
                        $fields['doctor_specialty'] = null;

                        $fields['license_number'] = $license_number;
                        $fields['authorized_person_name'] = $authorized_person_name;
                        $fields['authorized_person_position'] = $authorized_person_position;
                        $fields['authorized_person_phone'] = $authorized_person_phone;
                        $fields['authorized_person_email'] = $authorized_person_email;
                        $fields['hospital_type'] = $hospital_type;
                        $fields['hospital_address'] = $hospital_address !== '' ? $hospital_address : $address;
                    }

                    $db_error = null;

                    if (settings_update_account($pdo, $user_id, $fields, $db_error)) {
                        $_SESSION['user_name'] = $name;
                        $success = 'Profile information updated successfully.';
                        $account = settings_get_account($pdo, $user_id);
                    } else {
                        $error = 'Profile could not be updated. ' . ($db_error ?: '');
                    }
                }
            }
        }

        if ($action === 'change_password') {
            $current_password = (string)($_POST['current_password'] ?? '');
            $new_password = (string)($_POST['new_password'] ?? '');
            $confirm_password = (string)($_POST['confirm_password'] ?? '');

            if ($current_password === '' || $new_password === '' || $confirm_password === '') {
                $error = 'All password fields are required.';
            } elseif (!password_verify($current_password, (string)($account['password'] ?? ''))) {
                $error = 'Current password is incorrect.';
            } elseif ($new_password !== $confirm_password) {
                $error = 'New password and confirm password do not match.';
            } elseif (strlen($new_password) < 6) {
                $error = 'New password must be at least 6 characters.';
            } else {
                $db_error = null;

                if (settings_update_account($pdo, $user_id, [
                    'password' => password_hash($new_password, PASSWORD_DEFAULT),
                ], $db_error)) {
                    $success = 'Password changed successfully.';
                    $account = settings_get_account($pdo, $user_id);
                } else {
                    $error = 'Password could not be changed. ' . ($db_error ?: '');
                }
            }
        }

        if ($action === 'update_email') {
            $new_email = strtolower(trim((string)($_POST['email'] ?? '')));
            $current_password = (string)($_POST['current_password'] ?? '');

            if ($new_email === '' || $current_password === '') {
                $error = 'New email and current password are required.';
            } elseif (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Please enter a valid email address.';
            } elseif (!password_verify($current_password, (string)($account['password'] ?? ''))) {
                $error = 'Current password is incorrect.';
            } else {
                try {
                    $stmt = $pdo->prepare("
                        SELECT id
                        FROM users
                        WHERE email = :email
                        AND id != :id
                        LIMIT 1
                    ");
                    $stmt->execute([
                        ':email' => $new_email,
                        ':id' => $user_id,
                    ]);

                    if ($stmt->fetch(PDO::FETCH_ASSOC)) {
                        $error = 'This email address is already used by another account.';
                    }
                } catch (Throwable $e) {
                    $error = 'Could not check email address.';
                }

                if ($error === '') {
                    $db_error = null;

                    if (settings_update_account($pdo, $user_id, [
                        'email' => $new_email,
                    ], $db_error)) {
                        $_SESSION['user_email'] = $new_email;
                        $success = 'Email updated successfully.';
                        $account = settings_get_account($pdo, $user_id);
                    } else {
                        $error = 'Email could not be updated. ' . ($db_error ?: '');
                    }
                }
            }
        }
    }
}

$account_name = trim((string)($account['name'] ?? ''));
$account_email = trim((string)($account['email'] ?? ''));
$account_phone = trim((string)($account['phone'] ?? ''));
$account_status = trim((string)($account['status'] ?? 'active'));
$account_image = trim((string)($account['image'] ?? ''));

$saved_division_id = (int)($account['division_id'] ?? 0);
$saved_district_id = (int)($account['district_id'] ?? 0);
$saved_thana_id = (int)($account['thana_id'] ?? 0);

$saved_division_name = settings_address_name_by_id($pdo, 'divisions', $saved_division_id, $lang);
$saved_district_name = settings_address_name_by_id($pdo, 'districts', $saved_district_id, $lang);
$saved_thana_name = settings_address_name_by_id($pdo, 'thanas', $saved_thana_id, $lang);

$area = trim((string)($account['area'] ?? ''));
$road_no = trim((string)($account['road_no'] ?? ''));
$house_no = trim((string)($account['house_no'] ?? ''));
$post_code = trim((string)($account['post_code'] ?? ''));
$address = trim((string)($account['address'] ?? ''));

$bmdc_number = trim((string)($account['bmdc_number'] ?? ''));
$doctor_specialty = trim((string)($account['doctor_specialty'] ?? ''));

$license_number = trim((string)($account['license_number'] ?? ''));
$authorized_person_name = trim((string)($account['authorized_person_name'] ?? ''));
$authorized_person_position = trim((string)($account['authorized_person_position'] ?? ''));
$authorized_person_phone = trim((string)($account['authorized_person_phone'] ?? ''));
$authorized_person_email = trim((string)($account['authorized_person_email'] ?? ''));
$hospital_type = trim((string)($account['hospital_type'] ?? ''));
$hospital_address = trim((string)($account['hospital_address'] ?? ''));

$avatar_text = strtoupper(substr($account_name !== '' ? $account_name : 'U', 0, 1));
$image_url = $account_image !== '' ? settings_url($account_image) : '';

require_once __DIR__ . '/../includes/header.php';
?>

<link rel="stylesheet" href="<?php echo BASE_URL; ?>/user/assets/css/user-settings.css">

<div class="settings-page">

    <section class="settings-user-card">
        <div class="settings-user-main">
            <div class="settings-avatar">
                <?php if ($image_url !== ''): ?>
                    <img src="<?= settings_e($image_url) ?>" alt="<?= settings_e($account_name) ?>">
                <?php else: ?>
                    <?= settings_e($avatar_text ?: 'U') ?>
                <?php endif; ?>
            </div>

            <div>
                <h1><?= settings_e($account_name !== '' ? $account_name : 'User') ?></h1>

                <div class="settings-user-tags">
                    <?php if ($account_type === 'doctor'): ?>
                        <span><?= settings_e($doctor_specialty !== '' ? $doctor_specialty : 'Specialty not set') ?></span>
                        <span>BMDC: <?= settings_e($bmdc_number !== '' ? $bmdc_number : 'Not set') ?></span>
                    <?php elseif ($account_type === 'hospital_owner'): ?>
                        <span><?= settings_e($hospital_type !== '' ? $hospital_type : 'Hospital type not set') ?></span>
                        <span>License: <?= settings_e($license_number !== '' ? $license_number : 'Not set') ?></span>
                    <?php else: ?>
                        <span><?= settings_e($account_type_label) ?></span>
                    <?php endif; ?>

                    <span class="status"><?= settings_e(ucfirst(str_replace('_', ' ', $account_status))) ?></span>
                </div>
            </div>
        </div>

        <div class="settings-user-info-grid">
            <?php if ($account_type === 'doctor'): ?>
                <div class="settings-info-item">
                    <span>Specialty</span>
                    <strong><?= settings_e($doctor_specialty !== '' ? $doctor_specialty : 'Not set') ?></strong>
                </div>

                <div class="settings-info-item">
                    <span>BMDC Registration Number</span>
                    <strong><?= settings_e($bmdc_number !== '' ? $bmdc_number : 'Not set') ?></strong>
                </div>
            <?php endif; ?>

            <?php if ($account_type === 'hospital_owner'): ?>
                <div class="settings-info-item">
                    <span>Hospital Type</span>
                    <strong><?= settings_e($hospital_type !== '' ? $hospital_type : 'Not set') ?></strong>
                </div>

                <div class="settings-info-item">
                    <span>License Number</span>
                    <strong><?= settings_e($license_number !== '' ? $license_number : 'Not set') ?></strong>
                </div>

                <div class="settings-info-item">
                    <span>Authorized Person</span>
                    <strong><?= settings_e($authorized_person_name !== '' ? $authorized_person_name : 'Not set') ?></strong>
                </div>

                <div class="settings-info-item">
                    <span>Position</span>
                    <strong><?= settings_e($authorized_person_position !== '' ? $authorized_person_position : 'Not set') ?></strong>
                </div>

                <div class="settings-info-item">
                    <span>Authorized Phone</span>
                    <strong><?= settings_e($authorized_person_phone !== '' ? $authorized_person_phone : 'Not set') ?></strong>
                </div>

                <div class="settings-info-item">
                    <span>Authorized Email</span>
                    <strong><?= settings_e($authorized_person_email !== '' ? $authorized_person_email : 'Not set') ?></strong>
                </div>
            <?php endif; ?>

            <div class="settings-info-item">
                <span>Division</span>
                <strong><?= settings_e($saved_division_name !== '' ? $saved_division_name : 'Not set') ?></strong>
            </div>

            <div class="settings-info-item">
                <span>District</span>
                <strong><?= settings_e($saved_district_name !== '' ? $saved_district_name : 'Not set') ?></strong>
            </div>

            <div class="settings-info-item">
                <span>Thana / Upazila</span>
                <strong><?= settings_e($saved_thana_name !== '' ? $saved_thana_name : 'Not set') ?></strong>
            </div>

            <div class="settings-info-item">
                <span>Full Address</span>
                <strong><?= settings_e($address !== '' ? $address : 'Not set') ?></strong>
            </div>
        </div>
    </section>

    <?php if ($success): ?>
        <div class="settings-alert success"><?= settings_e($success) ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="settings-alert error"><?= settings_e($error) ?></div>
    <?php endif; ?>

    <?php if ($tab === 'home'): ?>
        <div class="settings-home-actions">
            <a href="settings.php?tab=profile" class="settings-action-btn">Profile Update</a>
            <a href="settings.php?tab=password" class="settings-action-btn secondary">Password Change</a>
            <a href="settings.php?tab=email" class="settings-action-btn secondary">Email Update</a>
        </div>

        <div class="settings-back">
            <a href="dashboard.php">Back to Dashboard</a>
        </div>
    <?php endif; ?>

    <?php if ($tab === 'profile'): ?>
        <section>
            <div class="settings-form-head">
                <h2>Profile Update</h2>
                <p>Update your profile image, phone, verification information, and address.</p>
            </div>

            <form method="post" enctype="multipart/form-data" class="settings-form" id="profileUpdateForm" data-saved-division-id="<?= settings_e((string) $saved_division_id) ?>" data-saved-district-id="<?= settings_e((string) $saved_district_id) ?>" data-saved-thana-id="<?= settings_e((string) $saved_thana_id) ?>" data-lang="<?= settings_e($lang) ?>">
                <input type="hidden" name="csrf_token" value="<?= settings_e($csrf_token) ?>">
                <input type="hidden" name="action" value="update_profile">

                <div class="settings-field">
                    <label for="name">Name <span>*</span></label>
                    <input type="text" id="name" name="name" value="<?= settings_e($account_name) ?>" required>
                </div>

                <div class="settings-field">
                    <label for="phone">Phone <span>*</span></label>
                    <input type="tel" id="phone" name="phone" value="<?= settings_e($account_phone) ?>" autocomplete="tel" required>
                </div>

                <div class="settings-field">
                    <label for="image">Profile Image</label>
                    <input type="file" id="image" name="image" accept=".jpg,.jpeg,.png,.webp,.gif">
                    <div class="settings-help">Upload JPG, PNG, WEBP or GIF. Max size 4MB. The image will always be converted and saved as WEBP.</div>
                </div>

                <?php if ($account_type === 'doctor'): ?>
                    <div class="settings-note-box">
                        <strong>Doctor Information</strong><br>
                        These details will be used for profile claim verification.
                    </div>

                    <div class="settings-field">
                        <label for="doctor_specialty">Specialty <span>*</span></label>
                        <select id="doctor_specialty" name="doctor_specialty" required>
                            <option value="">Select Specialty</option>
                            <?php foreach ($specialty_options as $specialty): ?>
                                <?php
                                    $sp_name = trim((string)($specialty['name'] ?? ''));
                                    $sp_bn = trim((string)($specialty['name_bn'] ?? ''));
                                    $label = $sp_bn !== '' ? $sp_name . ' / ' . $sp_bn : $sp_name;
                                ?>
                                <option value="<?= settings_e($sp_name) ?>" <?= $doctor_specialty === $sp_name ? 'selected' : '' ?>>
                                    <?= settings_e($label) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="settings-field">
                        <label for="bmdc_number">BMDC Registration Number <span>*</span></label>
                        <input type="text" id="bmdc_number" name="bmdc_number" value="<?= settings_e($bmdc_number) ?>" required>
                    </div>
                <?php endif; ?>

                <?php if ($account_type === 'hospital_owner'): ?>
                    <div class="settings-note-box">
                        <strong>Hospital Owner Information</strong><br>
                        These details will be used for hospital profile claim verification.
                    </div>

                    <div class="settings-field">
                        <label for="hospital_type">Hospital Type <span>*</span></label>
                        <select id="hospital_type" name="hospital_type" required>
                            <option value="">Select Hospital Type</option>
                            <?php foreach ($hospital_type_options as $type): ?>
                                <option value="<?= settings_e($type) ?>" <?= $hospital_type === $type ? 'selected' : '' ?>>
                                    <?= settings_e($type) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="settings-field">
                        <label for="license_number">License Number <span>*</span></label>
                        <input type="text" id="license_number" name="license_number" value="<?= settings_e($license_number) ?>" required>
                    </div>

                    <div class="settings-field">
                        <label for="authorized_person_name">Authorized Person Name <span>*</span></label>
                        <input type="text" id="authorized_person_name" name="authorized_person_name" value="<?= settings_e($authorized_person_name) ?>" required>
                    </div>

                    <div class="settings-field">
                        <label for="authorized_person_position">Authorized Person Position <span>*</span></label>
                        <select id="authorized_person_position" name="authorized_person_position" required>
                            <option value="">Select Position</option>
                            <?php foreach ($authorized_position_options as $position): ?>
                                <option value="<?= settings_e($position) ?>" <?= $authorized_person_position === $position ? 'selected' : '' ?>>
                                    <?= settings_e($position) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="settings-field">
                        <label for="authorized_person_phone">Authorized Person Phone Number <span>*</span></label>
                        <input type="text" id="authorized_person_phone" name="authorized_person_phone" value="<?= settings_e($authorized_person_phone) ?>" required>
                    </div>

                    <div class="settings-field">
                        <label for="authorized_person_email">Authorized Person Email <span>*</span></label>
                        <input type="email" id="authorized_person_email" name="authorized_person_email" value="<?= settings_e($authorized_person_email) ?>" required>
                    </div>
                <?php endif; ?>

                <div class="settings-note-box">
                    <strong>Address Information</strong><br>
                    District and thana will change automatically based on selected division.
                </div>

                <div class="settings-field">
                    <label for="division_id">Division <span>*</span></label>
                    <select id="division_id" name="division_id" required>
                        <option value="">Select Division</option>
                        <?php foreach ($divisions as $division): ?>
                            <option value="<?= settings_e((string)$division['id']) ?>" <?= $saved_division_id === (int)$division['id'] ? 'selected' : '' ?>>
                                <?= settings_e($division['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="settings-field">
                    <label for="district_id">District <span>*</span></label>
                    <select id="district_id" name="district_id" required>
                        <option value="">Select District</option>
                    </select>
                </div>

                <div class="settings-field">
                    <label for="thana_id">Thana / Upazila</label>
                    <select id="thana_id" name="thana_id">
                        <option value="">Select Thana</option>
                    </select>
                </div>

                <div class="settings-field">
                    <label for="area">Area / Locality</label>
                    <input type="text" id="area" name="area" value="<?= settings_e($area) ?>">
                </div>

                <div class="settings-field">
                    <label for="road_no">Road / Street</label>
                    <input type="text" id="road_no" name="road_no" value="<?= settings_e($road_no) ?>">
                </div>

                <div class="settings-field">
                    <label for="house_no">House / Building</label>
                    <input type="text" id="house_no" name="house_no" value="<?= settings_e($house_no) ?>">
                </div>

                <div class="settings-field">
                    <label for="post_code">Post Code</label>
                    <input type="text" id="post_code" name="post_code" value="<?= settings_e($post_code) ?>">
                </div>

                <div class="settings-field">
                    <label>Address Preview</label>
                    <div class="settings-location-preview" id="locationPreview">
                        <?= settings_e($address !== '' ? $address : 'Selected address will appear here.') ?>
                    </div>
                </div>

                <?php if ($account_type === 'hospital_owner'): ?>
                    <div class="settings-field">
                        <label for="hospital_address">Hospital Address</label>
                        <textarea id="hospital_address" name="hospital_address"><?= settings_e($hospital_address) ?></textarea>
                        <div class="settings-help">Keep empty to use the address preview above.</div>
                    </div>
                <?php endif; ?>

                <button type="submit" class="settings-btn">Save Profile</button>
            </form>

            <div class="settings-back">
                <a href="settings.php">Back to Settings</a>
            </div>
        </section>
    <?php endif; ?>

    <?php if ($tab === 'password'): ?>
        <section>
            <div class="settings-form-head">
                <h2>Password Change</h2>
                <p>Use a strong password and keep your account secure.</p>
            </div>

            <form method="post" class="settings-form">
                <input type="hidden" name="csrf_token" value="<?= settings_e($csrf_token) ?>">
                <input type="hidden" name="action" value="change_password">

                <div class="settings-field">
                    <label for="current_password">Current Password</label>
                    <div class="password-wrap">
                        <input type="password" id="current_password" name="current_password" autocomplete="current-password" required>
                        <button type="button" class="password-toggle" data-target="current_password">Show</button>
                    </div>
                </div>

                <div class="settings-field">
                    <label for="new_password">New Password</label>
                    <div class="password-wrap">
                        <input type="password" id="new_password" name="new_password" autocomplete="new-password" required>
                        <button type="button" class="password-toggle" data-target="new_password">Show</button>
                    </div>
                </div>

                <div class="settings-field">
                    <label for="confirm_password">Confirm Password</label>
                    <div class="password-wrap">
                        <input type="password" id="confirm_password" name="confirm_password" autocomplete="new-password" required>
                        <button type="button" class="password-toggle" data-target="confirm_password">Show</button>
                    </div>
                </div>

                <button type="submit" class="settings-btn">Change Password</button>
            </form>

            <div class="settings-back">
                <a href="settings.php">Back to Settings</a>
            </div>
        </section>
    <?php endif; ?>

    <?php if ($tab === 'email'): ?>
        <section>
            <div class="settings-form-head">
                <h2>Email Update</h2>
                <p>Enter your new email address and confirm using your current password.</p>
            </div>

            <form method="post" class="settings-form">
                <input type="hidden" name="csrf_token" value="<?= settings_e($csrf_token) ?>">
                <input type="hidden" name="action" value="update_email">

                <div class="settings-field">
                    <label for="current_email_display">Current Email</label>
                    <input type="email" id="current_email_display" value="<?= settings_e($account_email) ?>" disabled>
                </div>

                <div class="settings-field">
                    <label for="email">New Email <span>*</span></label>
                    <input type="email" id="email" name="email" value="<?= settings_e($account_email) ?>" required>
                </div>

                <div class="settings-field">
                    <label for="email_current_password">Current Password <span>*</span></label>
                    <div class="password-wrap">
                        <input type="password" id="email_current_password" name="current_password" autocomplete="current-password" required>
                        <button type="button" class="password-toggle" data-target="email_current_password">Show</button>
                    </div>
                </div>

                <button type="submit" class="settings-btn">Update Email</button>
            </form>

            <div class="settings-back">
                <a href="settings.php">Back to Settings</a>
            </div>
        </section>
    <?php endif; ?>

</div>

<script src="<?php echo BASE_URL; ?>/user/assets/js/settings.js" defer></script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>