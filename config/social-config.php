<?php
declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Social Publishing Configuration
|--------------------------------------------------------------------------
| Keep this file outside public downloads and never expose the API key in
| JavaScript, HTML, logs, screenshots, or public doctor pages.
*/

require_once __DIR__ . '/config.php';

if (!defined('MEDIC_BUFFER_ENDPOINT')) {
    define('MEDIC_BUFFER_ENDPOINT', 'https://api.buffer.com');
}

if (!defined('MEDIC_BUFFER_API_KEY')) {
    /*
    |--------------------------------------------------------------------------
    | First choice: Server environment variable
    |--------------------------------------------------------------------------
    | In cPanel/server environment, create:
    | BUFFER_API_KEY=your_new_private_buffer_key
    */
    $env_key = getenv('BUFFER_API_KEY');

    $resolved_key = '';

    if (is_string($env_key) && trim($env_key) !== '') {
        $resolved_key = trim($env_key);
    } elseif (defined('BUFFER_API_KEY') && trim((string) BUFFER_API_KEY) !== '') {
        $resolved_key = trim((string) BUFFER_API_KEY);
    }

    define('MEDIC_BUFFER_API_KEY', $resolved_key);
}

if (!defined('MEDIC_SOCIAL_TIMEZONE')) {
    define('MEDIC_SOCIAL_TIMEZONE', 'Asia/Dhaka');
}

if (!defined('MEDIC_SOCIAL_MAX_IMAGE_BYTES')) {
    define('MEDIC_SOCIAL_MAX_IMAGE_BYTES', 8 * 1024 * 1024);
}

if (!defined('MEDIC_SOCIAL_MAX_RETRY_ATTEMPTS')) {
    define('MEDIC_SOCIAL_MAX_RETRY_ATTEMPTS', 2);
}

if (!defined('MEDIC_SOCIAL_LOG_RESPONSE_LIMIT')) {
    define('MEDIC_SOCIAL_LOG_RESPONSE_LIMIT', 15000);
}
