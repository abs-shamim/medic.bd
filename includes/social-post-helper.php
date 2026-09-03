<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/buffer-client.php';
require_once __DIR__ . '/social-caption-generator.php';

if (!function_exists('medic_social_safe_identifier')) {
    function medic_social_safe_identifier(string $name): string
    {
        return preg_match('/^[A-Za-z0-9_]+$/', $name) ? $name : '';
    }
}

if (!function_exists('medic_social_required_tables')) {
    function medic_social_required_tables(): array
    {
        return [
            'social_posts',
            'social_post_targets',
            'social_publish_logs',
            'social_schedules',
        ];
    }
}

if (!function_exists('medic_social_table_exists')) {
    function medic_social_table_exists(string $table): bool
    {
        global $pdo;

        /*
         * Do not use SHOW TABLES LIKE :table here. This project uses PDO with
         * native prepared statements, and SHOW statements can fail silently on
         * some MySQL/MariaDB server configurations. INFORMATION_SCHEMA works
         * reliably with bound parameters.
         */
        if (
            medic_social_safe_identifier($table) === '' ||
            !isset($pdo) ||
            !($pdo instanceof PDO)
        ) {
            return false;
        }

        try {
            $stmt = $pdo->prepare(
                'SELECT 1
                 FROM INFORMATION_SCHEMA.TABLES
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = :table
                 LIMIT 1'
            );
            $stmt->execute([':table' => $table]);

            return (bool) $stmt->fetchColumn();
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('medic_social_database_status')) {
    function medic_social_database_status(): array
    {
        global $pdo;

        $missing = [];

        foreach (medic_social_required_tables() as $table) {
            if (!medic_social_table_exists($table)) {
                $missing[] = $table;
            }
        }

        $database_name = '';

        if (isset($pdo) && $pdo instanceof PDO) {
            try {
                $database_name = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
            } catch (Throwable $e) {
                $database_name = '';
            }
        }

        return [
            'ready' => empty($missing),
            'missing_tables' => $missing,
            'database_name' => $database_name,
            'has_pdo' => isset($pdo) && $pdo instanceof PDO,
        ];
    }
}

if (!function_exists('medic_social_database_ready')) {
    function medic_social_database_ready(): bool
    {
        $status = medic_social_database_status();
        return (bool) $status['ready'];
    }
}

if (!function_exists('medic_social_platforms')) {
    function medic_social_platforms(): array
    {
        return [
            'facebook' => [
                'label' => 'Facebook Page',
                'setting_id' => 'social_buffer_channel_facebook_id',
                'setting_name' => 'social_buffer_channel_facebook_name',
                'requires_image' => false,
            ],
            'instagram' => [
                'label' => 'Instagram Professional',
                'setting_id' => 'social_buffer_channel_instagram_id',
                'setting_name' => 'social_buffer_channel_instagram_name',
                'requires_image' => true,
            ],
            'linkedin' => [
                'label' => 'LinkedIn Page',
                'setting_id' => 'social_buffer_channel_linkedin_id',
                'setting_name' => 'social_buffer_channel_linkedin_name',
                'requires_image' => false,
            ],
            'x' => [
                'label' => 'X',
                'setting_id' => 'social_buffer_channel_x_id',
                'setting_name' => 'social_buffer_channel_x_name',
                'requires_image' => false,
            ],
        ];
    }
}

if (!function_exists('medic_social_csrf_token')) {
    function medic_social_csrf_token(): string
    {
        if (empty($_SESSION['medic_social_csrf'])) {
            try {
                $_SESSION['medic_social_csrf'] = bin2hex(random_bytes(32));
            } catch (Throwable $e) {
                $_SESSION['medic_social_csrf'] = hash('sha256', uniqid('medic-social', true));
            }
        }

        return (string) $_SESSION['medic_social_csrf'];
    }
}

if (!function_exists('medic_social_verify_csrf')) {
    function medic_social_verify_csrf($token): bool
    {
        return is_string($token)
            && !empty($_SESSION['medic_social_csrf'])
            && hash_equals((string) $_SESSION['medic_social_csrf'], $token);
    }
}

if (!function_exists('medic_social_flash')) {
    function medic_social_flash(string $type, string $message): void
    {
        $_SESSION['medic_social_flash'] = [
            'type' => $type === 'error' ? 'error' : 'success',
            'message' => $message,
        ];
    }
}

if (!function_exists('medic_social_show_flash')) {
    function medic_social_show_flash(): void
    {
        $flash = $_SESSION['medic_social_flash'] ?? null;
        unset($_SESSION['medic_social_flash']);

        if (!is_array($flash) || trim((string) ($flash['message'] ?? '')) === '') {
            return;
        }

        $class = ($flash['type'] ?? '') === 'error' ? 'medic-social-alert-error' : 'medic-social-alert-success';
        echo '<div class="medic-social-alert ' . e($class) . '">' . e((string) $flash['message']) . '</div>';
    }
}

if (!function_exists('medic_social_site_setting')) {
    function medic_social_site_setting(string $key, string $default = ''): string
    {
        return function_exists('get_site_setting') ? get_site_setting($key, $default) : $default;
    }
}

if (!function_exists('medic_social_save_settings')) {
    function medic_social_save_settings(array $settings): bool
    {
        return function_exists('update_site_settings') && update_site_settings($settings);
    }
}

if (!function_exists('medic_social_configured_channels')) {
    function medic_social_configured_channels(): array
    {
        $items = [];

        foreach (medic_social_platforms() as $key => $platform) {
            $channel_id = medic_social_site_setting($platform['setting_id']);
            $channel_name = medic_social_site_setting($platform['setting_name']);

            $items[$key] = [
                'platform' => $key,
                'label' => $platform['label'],
                'channel_id' => $channel_id,
                'channel_name' => $channel_name,
                'configured' => $channel_id !== '',
                'requires_image' => $platform['requires_image'],
            ];
        }

        return $items;
    }
}

if (!function_exists('medic_social_absolute_url')) {
    function medic_social_absolute_url(string $url): string
    {
        $url = trim($url);

        if ($url === '') {
            return '';
        }

        if (!preg_match('#^https?://#i', $url)) {
            $url = site_url(ltrim($url, '/'));
        }

        $validated = filter_var($url, FILTER_VALIDATE_URL);

        if ($validated === false) {
            return '';
        }

        $scheme = strtolower((string) parse_url($validated, PHP_URL_SCHEME));
        return in_array($scheme, ['http', 'https'], true) ? $validated : '';
    }
}

if (!function_exists('medic_social_upload_image')) {
    function medic_social_upload_image(string $field): array
    {
        if (empty($_FILES[$field]) || !isset($_FILES[$field]['error'])) {
            return ['ok' => true, 'url' => '', 'message' => ''];
        }

        $file = $_FILES[$field];

        if ((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return ['ok' => true, 'url' => '', 'message' => ''];
        }

        if ((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'url' => '', 'message' => 'Image upload failed.'];
        }

        $tmp = (string) ($file['tmp_name'] ?? '');
        $size = (int) ($file['size'] ?? 0);

        if ($tmp === '' || !is_uploaded_file($tmp) || $size <= 0 || $size > MEDIC_SOCIAL_MAX_IMAGE_BYTES) {
            return ['ok' => false, 'url' => '', 'message' => 'Use a valid image up to 8 MB.'];
        }

        $finfo = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : false;
        $mime = $finfo ? (string) finfo_file($finfo, $tmp) : '';
        if ($finfo) {
            finfo_close($finfo);
        }

        $extensions = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        ];

        if (!isset($extensions[$mime])) {
            return ['ok' => false, 'url' => '', 'message' => 'Only JPG, PNG, and WEBP images are supported.'];
        }

        $directory = rtrim((string) UPLOAD_PATH, '/\\') . '/social-posts';
        if (!is_dir($directory) && !@mkdir($directory, 0755, true)) {
            return ['ok' => false, 'url' => '', 'message' => 'Could not create the social image upload directory.'];
        }

        try {
            $nonce = bin2hex(random_bytes(6));
        } catch (Throwable $e) {
            $nonce = substr(hash('sha256', uniqid('social', true)), 0, 12);
        }
        $filename = 'social-' . date('Ymd-His') . '-' . $nonce . '.' . $extensions[$mime];
        $path = $directory . '/' . $filename;

        if (!move_uploaded_file($tmp, $path)) {
            return ['ok' => false, 'url' => '', 'message' => 'Could not save the uploaded image.'];
        }

        @chmod($path, 0644);

        return [
            'ok' => true,
            'url' => medic_social_absolute_url(rtrim((string) UPLOAD_URL, '/') . '/social-posts/' . $filename),
            'message' => '',
        ];
    }
}

if (!function_exists('medic_social_local_to_utc')) {
    function medic_social_local_to_utc(string $local_datetime): ?string
    {
        $local_datetime = trim($local_datetime);

        if ($local_datetime === '') {
            return null;
        }

        try {
            $local = new DateTimeImmutable($local_datetime, new DateTimeZone(MEDIC_SOCIAL_TIMEZONE));
            return $local->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\\TH:i:s\\Z');
        } catch (Throwable $e) {
            return null;
        }
    }
}

if (!function_exists('medic_social_utc_db_datetime')) {
    function medic_social_utc_db_datetime(?string $utc): ?string
    {
        $utc = trim((string) $utc);

        if ($utc === '') {
            return null;
        }

        try {
            return (new DateTimeImmutable($utc, new DateTimeZone('UTC')))
                ->setTimezone(new DateTimeZone('UTC'))
                ->format('Y-m-d H:i:s');
        } catch (Throwable $e) {
            return null;
        }
    }
}

if (!function_exists('medic_social_utc_to_local')) {
    function medic_social_utc_to_local(?string $utc): string
    {
        $utc = trim((string) $utc);
        if ($utc === '') {
            return '';
        }

        try {
            $date = new DateTimeImmutable($utc, new DateTimeZone('UTC'));
            return $date->setTimezone(new DateTimeZone(MEDIC_SOCIAL_TIMEZONE))->format('d M Y, h:i A');
        } catch (Throwable $e) {
            return $utc;
        }
    }
}

if (!function_exists('medic_social_get_doctor')) {
    function medic_social_get_doctor(int $doctor_id): ?array
    {
        global $pdo;

        if ($doctor_id <= 0) {
            return null;
        }

        try {
            $stmt = $pdo->prepare("\n                SELECT\n                    d.*,\n                    s.name AS specialty_name,\n                    h.name AS hospital_name,\n                    di.name AS district_name\n                FROM doctors d\n                LEFT JOIN specialties s ON s.id = d.specialty_id\n                LEFT JOIN hospitals h ON h.id = d.hospital_id\n                LEFT JOIN districts di ON di.id = d.doctor_district_id\n                WHERE d.id = :id\n                LIMIT 1\n            ");
            $stmt->execute([':id' => $doctor_id]);
            $doctor = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            /* Fallback for installations without location tables. */
            $stmt = $pdo->prepare("\n                SELECT d.*, s.name AS specialty_name, h.name AS hospital_name\n                FROM doctors d\n                LEFT JOIN specialties s ON s.id = d.specialty_id\n                LEFT JOIN hospitals h ON h.id = d.hospital_id\n                WHERE d.id = :id\n                LIMIT 1\n            ");
            $stmt->execute([':id' => $doctor_id]);
            $doctor = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        if (!is_array($doctor)) {
            return null;
        }

        $doctor['profile_url'] = !empty($doctor['slug'])
            ? site_url('doctor/' . rawurlencode((string) $doctor['slug']))
            : '';

        $image = medic_social_absolute_url((string) ($doctor['og_image'] ?? ''));
        if ($image === '') {
            $image = medic_social_absolute_url((string) ($doctor['image'] ?? ''));
        }
        $doctor['social_image_url'] = $image;

        return $doctor;
    }
}

if (!function_exists('medic_social_create_post')) {
    function medic_social_create_post(array $data): int
    {
        global $pdo;

        $stmt = $pdo->prepare("\n            INSERT INTO social_posts\n            (source_type, source_id, title, link_url, image_url, publish_mode, scheduled_at_utc, overall_status, created_by, created_at, updated_at)\n            VALUES\n            (:source_type, :source_id, :title, :link_url, :image_url, :publish_mode, :scheduled_at_utc, 'processing', :created_by, NOW(), NOW())\n        ");
        $stmt->execute([
            ':source_type' => (string) ($data['source_type'] ?? 'manual'),
            ':source_id' => (int) ($data['source_id'] ?? 0),
            ':title' => (string) ($data['title'] ?? ''),
            ':link_url' => (string) ($data['link_url'] ?? ''),
            ':image_url' => (string) ($data['image_url'] ?? ''),
            ':publish_mode' => (string) ($data['publish_mode'] ?? 'addToQueue'),
            ':scheduled_at_utc' => medic_social_utc_db_datetime($data['scheduled_at_utc'] ?? null),
            ':created_by' => function_exists('current_admin_id') ? current_admin_id() : 0,
        ]);

        return (int) $pdo->lastInsertId();
    }
}

if (!function_exists('medic_social_create_target')) {
    function medic_social_create_target(int $social_post_id, array $data): int
    {
        global $pdo;

        $stmt = $pdo->prepare("\n            INSERT INTO social_post_targets\n            (social_post_id, platform, channel_id, channel_name, caption, delivery_status, attempt_count, retry_allowed, created_at, updated_at)\n            VALUES\n            (:social_post_id, :platform, :channel_id, :channel_name, :caption, 'processing', 0, 0, NOW(), NOW())\n        ");
        $stmt->execute([
            ':social_post_id' => $social_post_id,
            ':platform' => (string) $data['platform'],
            ':channel_id' => (string) $data['channel_id'],
            ':channel_name' => (string) $data['channel_name'],
            ':caption' => (string) $data['caption'],
        ]);

        return (int) $pdo->lastInsertId();
    }
}

if (!function_exists('medic_social_log')) {
    function medic_social_log(int $social_post_id, int $target_id, string $event, array $result): void
    {
        global $pdo;

        $raw = (string) ($result['raw'] ?? '');
        $raw = medic_social_limit($raw, MEDIC_SOCIAL_LOG_RESPONSE_LIMIT);

        $stmt = $pdo->prepare("\n            INSERT INTO social_publish_logs\n            (social_post_id, social_post_target_id, event_type, http_code, response_body, error_message, created_at)\n            VALUES\n            (:social_post_id, :target_id, :event_type, :http_code, :response_body, :error_message, NOW())\n        ");
        $stmt->execute([
            ':social_post_id' => $social_post_id,
            ':target_id' => $target_id,
            ':event_type' => $event,
            ':http_code' => (int) ($result['http_code'] ?? 0),
            ':response_body' => $raw,
            ':error_message' => trim((string) ($result['message'] ?? '')),
        ]);
    }
}

if (!function_exists('medic_social_update_target_result')) {
    function medic_social_update_target_result(int $target_id, array $result, string $mode): void
    {
        global $pdo;

        $is_ok = !empty($result['ok']);
        $post = (array) ($result['post'] ?? []);
        $buffer_status = strtolower(trim((string) ($post['status'] ?? '')));

        $status = 'error';
        if ($is_ok) {
            if ($buffer_status === 'sent' || $mode === 'shareNow') {
                $status = 'sent';
            } elseif ($mode === 'customScheduled') {
                $status = 'scheduled';
            } else {
                $status = 'queued';
            }
        }

        $stmt = $pdo->prepare("\n            UPDATE social_post_targets\n            SET\n                buffer_post_id = :buffer_post_id,\n                buffer_due_at = :buffer_due_at,\n                buffer_status = :buffer_status,\n                delivery_status = :delivery_status,\n                external_link = :external_link,\n                error_message = :error_message,\n                retry_allowed = :retry_allowed,\n                attempt_count = attempt_count + 1,\n                last_attempt_at = NOW(),\n                published_at = CASE WHEN :published_status = 'sent' THEN NOW() ELSE published_at END,\n                updated_at = NOW()\n            WHERE id = :id\n        ");
        $stmt->execute([
            ':buffer_post_id' => (string) ($post['id'] ?? ''),
            ':buffer_due_at' => (string) ($post['dueAt'] ?? ''),
            ':buffer_status' => (string) ($post['status'] ?? ''),
            ':delivery_status' => $status,
            ':published_status' => $status,
            ':external_link' => (string) ($post['externalLink'] ?? ''),
            ':error_message' => $is_ok ? '' : trim((string) ($result['message'] ?? '')),
            ':retry_allowed' => !$is_ok && !empty($result['retryable']) ? 1 : 0,
            ':id' => $target_id,
        ]);
    }
}

if (!function_exists('medic_social_update_overall_status')) {
    function medic_social_update_overall_status(int $social_post_id): string
    {
        global $pdo;

        $stmt = $pdo->prepare("\n            SELECT delivery_status, COUNT(*) AS total\n            FROM social_post_targets\n            WHERE social_post_id = :id\n            GROUP BY delivery_status\n        ");
        $stmt->execute([':id' => $social_post_id]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $counts = [];
        foreach ($rows as $row) {
            $counts[(string) $row['delivery_status']] = (int) $row['total'];
        }

        $success = ($counts['sent'] ?? 0) + ($counts['scheduled'] ?? 0) + ($counts['queued'] ?? 0);
        $errors = $counts['error'] ?? 0;
        $status = $errors > 0 && $success > 0 ? 'partial' : ($errors > 0 ? 'error' : (($counts['sent'] ?? 0) > 0 ? 'sent' : 'scheduled'));

        $update = $pdo->prepare('UPDATE social_posts SET overall_status = :status, updated_at = NOW() WHERE id = :id');
        $update->execute([':status' => $status, ':id' => $social_post_id]);

        return $status;
    }
}

if (!function_exists('medic_social_create_schedule_audit')) {
    function medic_social_create_schedule_audit(int $social_post_id, ?string $scheduled_at_utc): void
    {
        if ($scheduled_at_utc === null || trim($scheduled_at_utc) === '') {
            return;
        }

        global $pdo;
        $stmt = $pdo->prepare("\n            INSERT INTO social_schedules\n            (social_post_id, scheduled_at_utc, schedule_status, created_at, updated_at)\n            VALUES (:social_post_id, :scheduled_at_utc, 'sent_to_buffer', NOW(), NOW())\n        ");
        $stmt->execute([
            ':social_post_id' => $social_post_id,
            ':scheduled_at_utc' => medic_social_utc_db_datetime($scheduled_at_utc),
        ]);
    }
}

if (!function_exists('medic_social_recent_posts')) {
    function medic_social_recent_posts(int $limit = 25, int $post_id = 0): array
    {
        global $pdo;
        $limit = max(1, min(100, $limit));

        $sql = "\n            SELECT sp.*,\n                   COUNT(st.id) AS target_total,\n                   SUM(st.delivery_status IN ('sent', 'scheduled', 'queued')) AS target_success,\n                   SUM(st.delivery_status = 'error') AS target_errors\n            FROM social_posts sp\n            LEFT JOIN social_post_targets st ON st.social_post_id = sp.id\n        ";
        $params = [];

        if ($post_id > 0) {
            $sql .= ' WHERE sp.id = :post_id ';
            $params[':post_id'] = $post_id;
        }

        $sql .= ' GROUP BY sp.id ORDER BY sp.id DESC LIMIT ' . $limit;
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

if (!function_exists('medic_social_post_targets')) {
    function medic_social_post_targets(int $social_post_id): array
    {
        global $pdo;
        $stmt = $pdo->prepare('SELECT * FROM social_post_targets WHERE social_post_id = :id ORDER BY id ASC');
        $stmt->execute([':id' => $social_post_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

if (!function_exists('medic_social_format_status')) {
    function medic_social_format_status(string $status): string
    {
        return ucwords(str_replace('_', ' ', $status));
    }
}
