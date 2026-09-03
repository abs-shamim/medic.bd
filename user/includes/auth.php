<?php
declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| User Panel Auth Helper
|--------------------------------------------------------------------------
| File path:
| user/includes/auth.php
|--------------------------------------------------------------------------
*/

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/*
|--------------------------------------------------------------------------
| Load main config and database connection
|--------------------------------------------------------------------------
*/
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';

/*
|--------------------------------------------------------------------------
| BASE_URL fallback
|--------------------------------------------------------------------------
*/
if (!defined('BASE_URL')) {
    if (defined('APP_URL')) {
        define('BASE_URL', rtrim((string)APP_URL, '/'));
    } else {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? '';
        define('BASE_URL', $host !== '' ? $scheme . '://' . $host : '');
    }
}

/**
 * Escape output safely.
 */
if (!function_exists('e')) {
    function e($value): string
    {
        return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
    }
}

/**
 * Get safe request value from POST/GET.
 */
if (!function_exists('safe_request_value')) {
    function safe_request_value(string $key, string $default = ''): string
    {
        if (isset($_POST[$key])) {
            return trim((string)$_POST[$key]);
        }

        if (isset($_GET[$key])) {
            return trim((string)$_GET[$key]);
        }

        return $default;
    }
}

/**
 * Redirect inside user panel.
 */
if (!function_exists('redirect')) {
    function redirect(string $path): void
    {
        $path = trim($path);

        if ($path === '') {
            $path = 'dashboard.php';
        }

        if (preg_match('#^https?://#i', $path)) {
            $url = $path;
        } else {
            $path = ltrim($path, '/');

            if (strpos($path, 'user/') === 0) {
                $url = BASE_URL . '/' . $path;
            } else {
                $url = BASE_URL . '/user/' . $path;
            }
        }

        if (!headers_sent()) {
            header('Location: ' . $url);
            exit;
        }

        echo '<script>window.location.href="' . e($url) . '";</script>';
        echo '<noscript><meta http-equiv="refresh" content="0;url=' . e($url) . '"></noscript>';
        exit;
    }
}

/**
 * Check if table exists.
 * Fixed version: INFORMATION_SCHEMA works better than SHOW TABLES LIKE with prepared params.
 */
if (!function_exists('table_exists')) {
    function table_exists(string $table): bool
    {
        global $pdo;

        try {
            $stmt = $pdo->prepare("
                SELECT COUNT(*)
                FROM INFORMATION_SCHEMA.TABLES
                WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = :table
            ");

            $stmt->execute([
                ':table' => $table,
            ]);

            return (int)$stmt->fetchColumn() > 0;
        } catch (Throwable $e) {
            return false;
        }
    }
}

/**
 * Check if column exists.
 */
if (!function_exists('column_exists')) {
    function column_exists(string $table, string $column): bool
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

/**
 * Check user login status.
 */
if (!function_exists('user_is_logged_in')) {
    function user_is_logged_in(): bool
    {
        return isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] > 0;
    }
}

/**
 * Logout user.
 */
if (!function_exists('user_logout')) {
    function user_logout(): void
    {
        unset($_SESSION['user_id']);
        unset($_SESSION['user_type']);
        unset($_SESSION['user_name']);
    }
}

/**
 * Get current logged-in user data.
 */
if (!function_exists('current_user_data')) {
    function current_user_data(): ?array
    {
        global $pdo;

        if (!user_is_logged_in()) {
            return null;
        }

        if (!table_exists('users')) {
            return null;
        }

        try {
            $stmt = $pdo->prepare("
                SELECT *
                FROM users
                WHERE id = :id
                LIMIT 1
            ");

            $stmt->execute([
                ':id' => (int)$_SESSION['user_id'],
            ]);

            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                user_logout();
                return null;
            }

            if (($user['status'] ?? '') === 'blocked') {
                user_logout();
                return null;
            }

            return $user;
        } catch (Throwable $e) {
            return null;
        }
    }
}

/**
 * Require login and return current user data.
 */
if (!function_exists('require_user_login')) {
    function require_user_login(): array
    {
        if (!user_is_logged_in()) {
            redirect('login.php');
        }

        $user = current_user_data();

        if (!$user) {
            user_logout();
            redirect('login.php');
        }

        return $user;
    }
}

/**
 * Redirect logged-in user away from login/register pages.
 */
if (!function_exists('redirect_logged_in_user')) {
    function redirect_logged_in_user(): void
    {
        if (user_is_logged_in()) {
            redirect('dashboard.php');
        }
    }
}

/**
 * Get current user ID.
 */
if (!function_exists('current_user_id')) {
    function current_user_id(): int
    {
        return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
    }
}

/**
 * Get current user type.
 */
if (!function_exists('current_user_type')) {
    function current_user_type(): string
    {
        /*
        |--------------------------------------------------------------------------
        | Important
        |--------------------------------------------------------------------------
        | Database value gets priority over session value.
        | This prevents old session user_type from blocking hospital/doctor pages.
        */
        $user = current_user_data();

        if ($user) {
            $type = trim((string)($user['user_type'] ?? $user['role'] ?? ''));

            if ($type !== '') {
                $_SESSION['user_type'] = $type;
                return $type;
            }
        }

        if (isset($_SESSION['user_type']) && trim((string)$_SESSION['user_type']) !== '') {
            return trim((string)$_SESSION['user_type']);
        }

        return '';
    }
}

/**
 * Normalize user type for consistent permission checks.
 */
if (!function_exists('normalize_user_type')) {
    function normalize_user_type(string $type): string
    {
        $type = trim($type);
        $type = strtolower($type);
        $type = str_replace(['-', ' '], '_', $type);
        $type = preg_replace('/_+/', '_', $type) ?: '';
        $type = trim($type, '_');

        if (in_array($type, ['hospital', 'hospital_owner', 'clinic', 'clinic_owner'], true)) {
            return 'hospital_owner';
        }

        if ($type === 'doctor') {
            return 'doctor';
        }

        return $type !== '' ? $type : 'user';
    }
}

/**
 * Check if current account is hospital type.
 */
if (!function_exists('current_user_is_hospital')) {
    function current_user_is_hospital(): bool
    {
        return normalize_user_type(current_user_type()) === 'hospital_owner';
    }
}

/**
 * Check if current account is doctor type.
 */
if (!function_exists('current_user_is_doctor')) {
    function current_user_is_doctor(): bool
    {
        return normalize_user_type(current_user_type()) === 'doctor';
    }
}

/**
 * Check user role.
 */
if (!function_exists('user_has_role')) {
    function user_has_role(string $role): bool
    {
        return normalize_user_type(current_user_type()) === normalize_user_type($role);
    }
}

/**
 * Require specific user role.
 */
if (!function_exists('require_user_role')) {
    function require_user_role(string $role): array
    {
        $user = require_user_login();

        if (!user_has_role($role)) {
            redirect('dashboard.php');
        }

        return $user;
    }
}

/**
 * Set flash message.
 */
if (!function_exists('set_flash')) {
    function set_flash(string $type, string $message): void
    {
        $_SESSION['flash'] = [
            'type' => $type,
            'message' => $message,
        ];
    }
}

/**
 * Get and remove flash message.
 */
if (!function_exists('get_flash')) {
    function get_flash(): ?array
    {
        if (!isset($_SESSION['flash'])) {
            return null;
        }

        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);

        return $flash;
    }
}

/**
 * Show flash message HTML.
 */
if (!function_exists('show_flash')) {
    function show_flash(): void
    {
        $flash = get_flash();

        if (!$flash) {
            return;
        }

        $type = $flash['type'] ?? 'info';
        $message = $flash['message'] ?? '';

        $class = 'user-alert info';

        if ($type === 'success') {
            $class = 'user-alert success';
        } elseif ($type === 'error') {
            $class = 'user-alert error';
        } elseif ($type === 'warning') {
            $class = 'user-alert warning';
        }

        echo '<div class="' . e($class) . '">' . e($message) . '</div>';
    }
}

/**
 * Generate CSRF token.
 */
if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return (string)$_SESSION['csrf_token'];
    }
}

/**
 * CSRF hidden input.
 */
if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
    }
}

/**
 * Verify CSRF token.
 */
if (!function_exists('verify_csrf_token')) {
    function verify_csrf_token(): bool
    {
        $token = $_POST['csrf_token'] ?? '';

        return is_string($token)
            && isset($_SESSION['csrf_token'])
            && hash_equals((string)$_SESSION['csrf_token'], $token);
    }
}

/**
 * Convert array data to JSON for request_data column.
 */
if (!function_exists('request_data_json')) {
    function request_data_json(array $data): string
    {
        return (string)json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}

/**
 * Decode request_data JSON.
 */
if (!function_exists('request_data_decode')) {
    function request_data_decode(?string $json): array
    {
        if (!$json) {
            return [];
        }

        $data = json_decode($json, true);

        return is_array($data) ? $data : [];
    }
}

/*
|--------------------------------------------------------------------------
| Dashboard / Claim Helper Functions
|--------------------------------------------------------------------------
*/

/**
 * Get last claim status for user.
 */
if (!function_exists('get_user_claim_status')) {
    function get_user_claim_status(int $user_id): ?array
    {
        global $pdo;

        if ($user_id <= 0 || !table_exists('profile_claims')) {
            return null;
        }

        try {
            $stmt = $pdo->prepare("
                SELECT *
                FROM profile_claims
                WHERE user_id = :user_id
                ORDER BY id DESC
                LIMIT 1
            ");

            $stmt->execute([
                ':user_id' => $user_id,
            ]);

            $claim = $stmt->fetch(PDO::FETCH_ASSOC);

            return $claim ?: null;
        } catch (Throwable $e) {
            return null;
        }
    }
}

/**
 * Get pending request count for user.
 */
if (!function_exists('get_pending_count')) {
    function get_pending_count(int $user_id): int
    {
        global $pdo;

        if ($user_id <= 0 || !table_exists('profile_update_requests')) {
            return 0;
        }

        try {
            $stmt = $pdo->prepare("
                SELECT COUNT(*)
                FROM profile_update_requests
                WHERE user_id = :user_id
                AND status = 'pending'
            ");

            $stmt->execute([
                ':user_id' => $user_id,
            ]);

            return (int)$stmt->fetchColumn();
        } catch (Throwable $e) {
            return 0;
        }
    }
}

/**
 * Check if user already has approved claimed profile.
 */
if (!function_exists('user_has_claim')) {
    function user_has_claim(array $user): bool
    {
        $doctor_id = (int)($user['claimed_doctor_id'] ?? 0);
        $hospital_id = (int)($user['claimed_hospital_id'] ?? 0);

        return $doctor_id > 0 || $hospital_id > 0;
    }
}

/**
 * Get user profile type.
 */
if (!function_exists('user_profile_type')) {
    function user_profile_type(array $user): string
    {
        if ((int)($user['claimed_hospital_id'] ?? 0) > 0) {
            return 'hospital';
        }

        if ((int)($user['claimed_doctor_id'] ?? 0) > 0) {
            return 'doctor';
        }

        $type = normalize_user_type((string)($user['user_type'] ?? $user['role'] ?? ''));

        if ($type === 'hospital_owner') {
            return 'hospital';
        }

        if ($type === 'doctor') {
            return 'doctor';
        }

        return 'user';
    }
}

/**
 * Get connected doctor/hospital profile.
 */
if (!function_exists('get_user_profile')) {
    function get_user_profile(array $user): ?array
    {
        global $pdo;

        try {
            $doctor_id = (int)($user['claimed_doctor_id'] ?? 0);
            $hospital_id = (int)($user['claimed_hospital_id'] ?? 0);

            if ($doctor_id > 0 && table_exists('doctors')) {
                $stmt = $pdo->prepare("
                    SELECT *
                    FROM doctors
                    WHERE id = :id
                    LIMIT 1
                ");

                $stmt->execute([
                    ':id' => $doctor_id,
                ]);

                $profile = $stmt->fetch(PDO::FETCH_ASSOC);

                return $profile ?: null;
            }

            if ($hospital_id > 0 && table_exists('hospitals')) {
                $stmt = $pdo->prepare("
                    SELECT *
                    FROM hospitals
                    WHERE id = :id
                    LIMIT 1
                ");

                $stmt->execute([
                    ':id' => $hospital_id,
                ]);

                $profile = $stmt->fetch(PDO::FETCH_ASSOC);

                return $profile ?: null;
            }

            return null;
        } catch (Throwable $e) {
            return null;
        }
    }
}

/**
 * Get readable account type label.
 */
if (!function_exists('user_type_label')) {
    function user_type_label(array $user): string
    {
        $type = normalize_user_type((string)($user['user_type'] ?? $user['role'] ?? ''));

        if ($type === 'hospital_owner') {
            return 'Hospital Owner';
        }

        if ($type === 'doctor') {
            return 'Doctor';
        }

        return ucfirst(str_replace('_', ' ', $type));
    }
}
