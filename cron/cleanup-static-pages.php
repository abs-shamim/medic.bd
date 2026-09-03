<?php
declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Static page deletion cleanup
|--------------------------------------------------------------------------
| Run once per minute from server cron so one-hour deletion requests are
| completed even when no administrator visits the Static Pages screen.
|
| Example:
| * * * * * /usr/bin/php /home/USERNAME/public_html/cron/cleanup-static-pages.php >/dev/null 2>&1
*/

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only.' . PHP_EOL);
}

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../admin/includes/static-pages-bootstrap.php';

try {
    admin_static_pages_create_table();
    admin_static_pages_ensure_delete_schedule_column();

    $deletedCount = admin_static_pages_cleanup_scheduled_deletions();

    echo 'Static page cleanup completed. Deleted: ' . $deletedCount . PHP_EOL;
} catch (Throwable $exception) {
    fwrite(STDERR, 'Static page cleanup failed: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
