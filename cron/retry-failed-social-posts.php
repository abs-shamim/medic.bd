<?php
declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Retry safe Buffer failures
|--------------------------------------------------------------------------
| Run from server cron only:
|   /usr/bin/php /home/USER/public_html/cron/retry-failed-social-posts.php
|
| The script retries only rows marked retry_allowed=1. Network timeouts are
| deliberately not marked retryable because Buffer may have created the post
| even if the connection closed before PHP received the response.
*/

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only.' . PHP_EOL);
}

require_once __DIR__ . '/../includes/social-post-helper.php';

if (!medic_social_database_ready()) {
    exit("Social tables are missing. Import database/social-posts.sql first.\n");
}

if (!medic_buffer_is_configured()) {
    exit("BUFFER_API_KEY is not configured.\n");
}

$limit = 20;
$sql = "
    SELECT st.*, sp.image_url, sp.publish_mode, sp.scheduled_at_utc
    FROM social_post_targets st
    INNER JOIN social_posts sp ON sp.id = st.social_post_id
    WHERE st.delivery_status = 'error'
      AND st.retry_allowed = 1
      AND st.attempt_count < :max_attempts
    ORDER BY st.id ASC
    LIMIT {$limit}
";

$stmt = $pdo->prepare($sql);
$stmt->execute([':max_attempts' => MEDIC_SOCIAL_MAX_RETRY_ATTEMPTS]);
$targets = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($targets)) {
    exit("No retryable social delivery failures found.\n");
}

$success = 0;
$failed = 0;

foreach ($targets as $target) {
    $mode = (string) $target['publish_mode'];
    $scheduled = trim((string) $target['scheduled_at_utc']);
    $scheduled_utc = $scheduled !== '' ? str_replace(' ', 'T', $scheduled) . 'Z' : null;

    /* A past scheduled time is moved to the next Buffer queue slot. */
    if ($mode === 'customScheduled') {
        try {
            if ($scheduled_utc === null || new DateTimeImmutable($scheduled_utc) <= new DateTimeImmutable('now', new DateTimeZone('UTC'))) {
                $mode = 'addToQueue';
                $scheduled_utc = null;
            }
        } catch (Throwable $e) {
            $mode = 'addToQueue';
            $scheduled_utc = null;
        }
    }

    /*
     * Preserve platform-specific metadata during retry. Facebook and Instagram
     * need an explicit normal feed post type.
     */
    $platform_metadata = function_exists('medic_buffer_metadata_for_platform')
        ? medic_buffer_metadata_for_platform((string) ($target['platform'] ?? ''))
        : [];

    $result = medic_buffer_create_post(
        (string) $target['channel_id'],
        (string) $target['caption'],
        $mode,
        $scheduled_utc,
        (string) $target['image_url'],
        $platform_metadata
    );

    medic_social_update_target_result((int) $target['id'], $result, $mode);
    medic_social_log((int) $target['social_post_id'], (int) $target['id'], 'retry_create_post', $result);
    medic_social_update_overall_status((int) $target['social_post_id']);

    if (!empty($result['ok'])) {
        $success++;
        echo 'Retried target #' . $target['id'] . ' successfully.' . PHP_EOL;
    } else {
        $failed++;
        echo 'Target #' . $target['id'] . ' failed: ' . (string) ($result['message'] ?? 'Unknown error') . PHP_EOL;
    }
}

echo 'Finished. Success: ' . $success . ', failed: ' . $failed . PHP_EOL;
