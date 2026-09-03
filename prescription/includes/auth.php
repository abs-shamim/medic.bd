<?php
declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Prescription Module Authentication
|--------------------------------------------------------------------------
| Uses the existing website user authentication system. Only an approved
| doctor account with a connected doctor profile can access this module.
|--------------------------------------------------------------------------
*/

$project_root = dirname(__DIR__, 2);
$user_auth_file = $project_root . '/user/includes/auth.php';
$core_config_file = $project_root . '/includes/config.php';

if (!is_file($user_auth_file)) {
    http_response_code(500);
    exit('User authentication file was not found.');
}

require_once $user_auth_file;

if ((!isset($pdo) || !$pdo instanceof PDO) && is_file($core_config_file)) {
    require_once $core_config_file;
}

if (!isset($pdo) || !$pdo instanceof PDO) {
    http_response_code(500);
    exit('Database connection is not available.');
}

if (!function_exists('prescription_auth_url')) {
    function prescription_auth_url(string $path = ''): string
    {
        $path = ltrim($path, '/');

        if (function_exists('site_url')) {
            return site_url($path);
        }

        return '/' . $path;
    }
}

if (!function_exists('prescription_auth_redirect')) {
    function prescription_auth_redirect(string $path): void
    {
        $url = preg_match('#^https?://#i', $path)
            ? $path
            : prescription_auth_url($path);

        if (!headers_sent()) {
            header('Location: ' . $url);
            exit;
        }

        echo '<script>window.location.href=' . json_encode($url) . ';</script>';
        exit;
    }
}

$user = require_user_login();
$user_type = function_exists('user_profile_type')
    ? user_profile_type($user)
    : (string)($user['user_type'] ?? '');

$doctor_id = (int)($user['claimed_doctor_id'] ?? 0);
$has_claim = function_exists('user_has_claim')
    ? user_has_claim($user)
    : $doctor_id > 0;

if ($user_type !== 'doctor' || !$has_claim || $doctor_id <= 0) {
    prescription_auth_redirect('user/dashboard.php');
}

$user_id = (int)($user['id'] ?? 0);

if ($user_id <= 0) {
    prescription_auth_redirect('user/logout.php');
}
