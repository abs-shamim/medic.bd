<?php
declare(strict_types=1);

define('APP_NAME', 'Medicbd');
define('APP_URL', 'http://localhost/medicbd');
define('BASE_URL', rtrim(APP_URL, '/'));

/*
|--------------------------------------------------------------------------
| Database
|--------------------------------------------------------------------------
*/
define('DB_HOST', 'localhost');
define('DB_NAME', 'medicbd');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

/*
|--------------------------------------------------------------------------
| Admin Login
|--------------------------------------------------------------------------
*/
$admin_email = getenv('ADMIN_EMAIL');
$admin_password = getenv('ADMIN_PASSWORD');

define('ADMIN_EMAIL', is_string($admin_email) ? trim($admin_email) : '');
define('ADMIN_PASSWORD', is_string($admin_password) ? $admin_password : '');

/*
|--------------------------------------------------------------------------
| Upload path
|--------------------------------------------------------------------------
*/
define('UPLOAD_PATH', __DIR__ . '/../assets/uploads/');
define('UPLOAD_URL', BASE_URL . '/assets/uploads/');
