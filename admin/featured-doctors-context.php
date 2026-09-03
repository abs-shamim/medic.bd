<?php
require_once __DIR__ . '/../includes/functions.php';

require_admin();

/*
|--------------------------------------------------------------------------
| Featured Doctors Context Page
|--------------------------------------------------------------------------
| Features:
| - Context based featured doctor
| - District + Thana + Specialty + Doctor
| - Same doctor context duplicate হলে existing history page-এ redirect
| - Position 1-10 only
| - Booked positions hidden
| - Remaining 0 হলে auto inactive
| - Inactive হলে position free
| - Active Duration supports: 300, +300, -300, 500+300, 500-300
| - Featured Summary
| - Featured Context Settings
| - Featured History
| - PHP 7.4 compatible
|--------------------------------------------------------------------------
*/

if (!function_exists('fd_table_exists')) {
    function fd_table_exists(string $table): bool
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

if (!function_exists('fd_column_exists')) {
    function fd_column_exists(string $table, string $column): bool
    {
        global $pdo;

        try {
            if (!fd_table_exists($table)) {
                return false;
            }

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

if (!function_exists('fd_name_column')) {
    function fd_name_column(string $table): string
    {
        if (fd_column_exists($table, 'name_en')) {
            return 'name_en';
        }

        if (fd_column_exists($table, 'name')) {
            return 'name';
        }

        if (fd_column_exists($table, 'title')) {
            return 'title';
        }

        return 'id';
    }
}

if (!function_exists('fd_starts_with')) {
    function fd_starts_with(string $haystack, string $needle): bool
    {
        if ($needle === '') {
            return true;
        }

        return substr($haystack, 0, strlen($needle)) === $needle;
    }
}

if (!function_exists('fd_first_char')) {
    function fd_first_char(string $text): string
    {
        if (function_exists('mb_substr')) {
            return mb_substr($text, 0, 1);
        }

        return substr($text, 0, 1);
    }
}

if (!function_exists('fd_text_length')) {
    function fd_text_length(string $text): int
    {
        if (function_exists('mb_strlen')) {
            return mb_strlen($text);
        }

        return strlen($text);
    }
}

if (!function_exists('fd_add_column_if_missing')) {
    function fd_add_column_if_missing(string $table, string $column, string $definition): void
    {
        global $pdo;

        if (!fd_table_exists($table)) {
            return;
        }

        if (!fd_column_exists($table, $column)) {
            try {
                $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
            } catch (Throwable $e) {
                error_log('Featured column add failed: ' . $table . '.' . $column . ' - ' . $e->getMessage());
            }
        }
    }
}

if (!function_exists('fd_boot_context_table')) {
    function fd_boot_context_table(): void
    {
        global $pdo;

        if (!fd_table_exists('doctor_featured_contexts')) {
            try {
                $pdo->exec("
                    CREATE TABLE doctor_featured_contexts (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        doctor_id INT NOT NULL DEFAULT 0,
                        district_id INT DEFAULT 0,
                        thana_id INT DEFAULT 0,
                        specialty_id INT DEFAULT 0,
                        featured_days INT DEFAULT 0,
                        featured_started_at DATE NULL,
                        featured_until DATE NULL,
                        featured_position INT DEFAULT 0,
                        remaining_days INT DEFAULT 0,
                        status VARCHAR(30) DEFAULT 'active',
                        created_at DATETIME NULL,
                        updated_at DATETIME NULL,
                        UNIQUE KEY unique_featured_context (doctor_id, district_id, thana_id, specialty_id),
                        INDEX doctor_id_idx (doctor_id),
                        INDEX district_id_idx (district_id),
                        INDEX thana_id_idx (thana_id),
                        INDEX specialty_id_idx (specialty_id),
                        INDEX status_idx (status),
                        INDEX position_idx (featured_position)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                ");
            } catch (Throwable $e) {
                error_log('doctor_featured_contexts create failed: ' . $e->getMessage());
            }
        }

        $columns = [
            'doctor_id' => "INT NOT NULL DEFAULT 0",
            'district_id' => "INT DEFAULT 0",
            'thana_id' => "INT DEFAULT 0",
            'specialty_id' => "INT DEFAULT 0",
            'featured_days' => "INT DEFAULT 0",
            'featured_started_at' => "DATE NULL",
            'featured_until' => "DATE NULL",
            'featured_position' => "INT DEFAULT 0",
            'remaining_days' => "INT DEFAULT 0",
            'status' => "VARCHAR(30) DEFAULT 'active'",
            'created_at' => "DATETIME NULL",
            'updated_at' => "DATETIME NULL",
        ];

        foreach ($columns as $column => $definition) {
            fd_add_column_if_missing('doctor_featured_contexts', $column, $definition);
        }
    }
}

if (!function_exists('fd_boot_history_table')) {
    function fd_boot_history_table(): void
    {
        global $pdo;

        if (!fd_table_exists('doctor_featured_context_history')) {
            try {
                $pdo->exec("
                    CREATE TABLE doctor_featured_context_history (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        context_id INT NOT NULL DEFAULT 0,
                        doctor_id INT NOT NULL DEFAULT 0,
                        district_id INT DEFAULT 0,
                        thana_id INT DEFAULT 0,
                        specialty_id INT DEFAULT 0,
                        featured_days INT DEFAULT 0,
                        featured_started_at DATE NULL,
                        featured_until DATE NULL,
                        featured_position INT DEFAULT 0,
                        remaining_days INT DEFAULT 0,
                        action_type VARCHAR(30) DEFAULT 'active',
                        status VARCHAR(30) DEFAULT 'active',
                        note VARCHAR(255) NULL,
                        created_at DATETIME NULL,
                        INDEX context_id_idx (context_id),
                        INDEX doctor_id_idx (doctor_id),
                        INDEX action_type_idx (action_type),
                        INDEX status_idx (status)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                ");
            } catch (Throwable $e) {
                error_log('doctor_featured_context_history create failed: ' . $e->getMessage());
            }
        }

        $columns = [
            'context_id' => "INT NOT NULL DEFAULT 0",
            'doctor_id' => "INT NOT NULL DEFAULT 0",
            'district_id' => "INT DEFAULT 0",
            'thana_id' => "INT DEFAULT 0",
            'specialty_id' => "INT DEFAULT 0",
            'featured_days' => "INT DEFAULT 0",
            'featured_started_at' => "DATE NULL",
            'featured_until' => "DATE NULL",
            'featured_position' => "INT DEFAULT 0",
            'remaining_days' => "INT DEFAULT 0",
            'action_type' => "VARCHAR(30) DEFAULT 'active'",
            'status' => "VARCHAR(30) DEFAULT 'active'",
            'note' => "VARCHAR(255) NULL",
            'created_at' => "DATETIME NULL",
        ];

        foreach ($columns as $column => $definition) {
            fd_add_column_if_missing('doctor_featured_context_history', $column, $definition);
        }
    }
}

if (!function_exists('fd_redirect')) {
    function fd_redirect(array $params = [], string $file = 'featured-doctors-context.php'): void
    {
        $url = $file;

        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }

        header('Location: ' . $url);
        exit;
    }
}

if (!function_exists('fd_get_items')) {
    function fd_get_items(string $table): array
    {
        global $pdo;

        if (!fd_table_exists($table) || !fd_column_exists($table, 'id')) {
            return [];
        }

        $name_column = fd_name_column($table);

        try {
            $where = '';

            if (fd_column_exists($table, 'status')) {
                $where = "WHERE status IN ('active', 'published', '') OR status IS NULL";
            }

            $stmt = $pdo->query("
                SELECT id, `{$name_column}` AS name
                FROM `{$table}`
                {$where}
                ORDER BY `{$name_column}` ASC
            ");

            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }
}

if (!function_exists('fd_get_districts')) {
    function fd_get_districts(): array
    {
        global $pdo;

        if (!fd_table_exists('districts')) {
            return [];
        }

        $name_column = fd_name_column('districts');
        $select = "id, `{$name_column}` AS name";
        $select .= fd_column_exists('districts', 'division_id') ? ", division_id" : ", 0 AS division_id";

        try {
            $stmt = $pdo->query("
                SELECT {$select}
                FROM districts
                ORDER BY `{$name_column}` ASC
            ");

            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }
}

if (!function_exists('fd_get_thanas')) {
    function fd_get_thanas(): array
    {
        global $pdo;

        if (!fd_table_exists('thanas')) {
            return [];
        }

        $name_column = fd_name_column('thanas');
        $select = "id, `{$name_column}` AS name";
        $select .= fd_column_exists('thanas', 'district_id') ? ", district_id" : ", 0 AS district_id";

        try {
            $stmt = $pdo->query("
                SELECT {$select}
                FROM thanas
                ORDER BY `{$name_column}` ASC
            ");

            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }
}

if (!function_exists('fd_get_specialties')) {
    function fd_get_specialties(): array
    {
        if (function_exists('get_specialties')) {
            try {
                $items = get_specialties();

                if (is_array($items)) {
                    return array_values(array_filter($items, static function ($item) {
                        return !empty($item['id']) && !empty($item['name']);
                    }));
                }
            } catch (Throwable $e) {
                // Fallback below.
            }
        }

        return fd_get_items('specialties');
    }
}

if (!function_exists('fd_get_item_name_by_id')) {
    function fd_get_item_name_by_id(string $table, int $id): string
    {
        global $pdo;

        if ($id <= 0 || !fd_table_exists($table) || !fd_column_exists($table, 'id')) {
            return '';
        }

        $name_column = fd_name_column($table);

        try {
            $stmt = $pdo->prepare("
                SELECT `{$name_column}`
                FROM `{$table}`
                WHERE id = :id
                LIMIT 1
            ");
            $stmt->execute([':id' => $id]);

            return trim((string)$stmt->fetchColumn());
        } catch (Throwable $e) {
            return '';
        }
    }
}

if (!function_exists('fd_image_url')) {
    function fd_image_url(?string $image): string
    {
        $image = trim((string)$image);

        if ($image === '') {
            return '';
        }

        if (preg_match('#^(https?:)?//#i', $image) || fd_starts_with($image, 'data:')) {
            return $image;
        }

        if (fd_starts_with($image, '../') || fd_starts_with($image, './')) {
            return $image;
        }

        if (function_exists('site_url')) {
            return site_url(ltrim($image, '/'));
        }

        return '../' . ltrim($image, '/');
    }
}

if (!function_exists('fd_initials')) {
    function fd_initials(string $name): string
    {
        $name = trim($name);

        if ($name === '') {
            return 'DR';
        }

        $parts = preg_split('/\s+/', $name);
        $initials = '';

        foreach ($parts as $part) {
            if ($part !== '') {
                $initials .= strtoupper(fd_first_char($part));
            }

            if (fd_text_length($initials) >= 2) {
                break;
            }
        }

        return $initials !== '' ? $initials : 'DR';
    }
}

if (!function_exists('fd_doctor_avatar')) {
    function fd_doctor_avatar(array $doctor): string
    {
        $name = trim((string)($doctor['name'] ?? ''));
        $image = fd_image_url($doctor['image'] ?? '');

        if ($image !== '') {
            return '<span class="fd-avatar"><img src="' . e($image) . '" alt="' . e($name !== '' ? $name : 'Doctor') . '" loading="lazy"></span>';
        }

        return '<span class="fd-avatar placeholder">' . e(fd_initials($name)) . '</span>';
    }
}

if (!function_exists('fd_get_record')) {
    function fd_get_record(int $id): array
    {
        global $pdo;

        if ($id <= 0 || !fd_table_exists('doctor_featured_contexts')) {
            return [];
        }

        try {
            $stmt = $pdo->prepare("
                SELECT *
                FROM doctor_featured_contexts
                WHERE id = :id
                LIMIT 1
            ");
            $stmt->execute([':id' => $id]);

            return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }
}

if (!function_exists('fd_get_doctor_by_id')) {
    function fd_get_doctor_by_id(int $doctor_id): array
    {
        global $pdo;

        if ($doctor_id <= 0 || !fd_table_exists('doctors')) {
            return [];
        }

        try {
            $stmt = $pdo->prepare("
                SELECT *
                FROM doctors
                WHERE id = :id
                LIMIT 1
            ");
            $stmt->execute([':id' => $doctor_id]);

            return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }
}

if (!function_exists('fd_remaining_days')) {
    function fd_remaining_days(?string $featured_until): int
    {
        $featured_until = trim((string)$featured_until);

        if ($featured_until === '') {
            return 0;
        }

        try {
            $today = new DateTime(date('Y-m-d'));
            $end = new DateTime($featured_until);

            if ($end < $today) {
                return 0;
            }

            return (int)$today->diff($end)->days;
        } catch (Throwable $e) {
            return 0;
        }
    }
}

if (!function_exists('fd_current_remaining_days')) {
    function fd_current_remaining_days(array $record): int
    {
        $status = (string)($record['status'] ?? '');

        if ($status === 'hold') {
            return max(0, (int)($record['remaining_days'] ?? 0));
        }

        return fd_remaining_days($record['featured_until'] ?? '');
    }
}

if (!function_exists('fd_calculate_until')) {
    function fd_calculate_until(int $days, ?string $started_at = null): ?string
    {
        if ($days <= 0) {
            return null;
        }

        $start = $started_at ?: date('Y-m-d');
        $timestamp = strtotime($start . ' +' . $days . ' days');

        return $timestamp ? date('Y-m-d', $timestamp) : null;
    }
}

if (!function_exists('fd_parse_duration_input')) {
    function fd_parse_duration_input(string $input, int $current_remaining = 0): int
    {
        $input = trim($input);
        $input = str_replace(' ', '', $input);

        if ($input === '') {
            return 0;
        }

        // +300 means current remaining + 300
        if (preg_match('/^\+(\d+)$/', $input, $match)) {
            return max(0, $current_remaining + (int)$match[1]);
        }

        // -300 means current remaining - 300
        if (preg_match('/^-(\d+)$/', $input, $match)) {
            return max(0, $current_remaining - (int)$match[1]);
        }

        // 500+300 means 800
        if (preg_match('/^(\d+)\+(\d+)$/', $input, $match)) {
            return max(0, (int)$match[1] + (int)$match[2]);
        }

        // 500-300 means 200
        if (preg_match('/^(\d+)-(\d+)$/', $input, $match)) {
            return max(0, (int)$match[1] - (int)$match[2]);
        }

        // 300 means set to 300
        if (preg_match('/^\d+$/', $input)) {
            return max(0, (int)$input);
        }

        return 0;
    }
}

if (!function_exists('fd_history_run_days')) {
    function fd_history_run_days(?string $started_at, ?string $action_at, int $total_days = 0, int $remaining_days = 0): int
    {
        $started_at = trim((string)$started_at);
        $action_at = trim((string)$action_at);

        if ($total_days > 0 && $remaining_days >= 0) {
            $calculated = $total_days - $remaining_days;

            if ($calculated >= 0) {
                return $calculated;
            }
        }

        if ($started_at === '' || $action_at === '') {
            return 0;
        }

        try {
            $start = new DateTime(substr($started_at, 0, 10));
            $end = new DateTime(substr($action_at, 0, 10));

            if ($end < $start) {
                return 0;
            }

            return (int)$start->diff($end)->days;
        } catch (Throwable $e) {
            return 0;
        }
    }
}

if (!function_exists('fd_auto_inactive_expired_records')) {
    function fd_auto_inactive_expired_records(): void
    {
        global $pdo;

        if (!fd_table_exists('doctor_featured_contexts')) {
            return;
        }

        try {
            $stmt = $pdo->prepare("
                UPDATE doctor_featured_contexts
                SET status = 'inactive',
                    remaining_days = 0,
                    updated_at = NOW()
                WHERE status = 'active'
                AND featured_until IS NOT NULL
                AND featured_until <= CURDATE()
            ");
            $stmt->execute();

            $stmt = $pdo->prepare("
                UPDATE doctor_featured_contexts
                SET status = 'inactive',
                    remaining_days = 0,
                    updated_at = NOW()
                WHERE status = 'hold'
                AND remaining_days <= 0
            ");
            $stmt->execute();
        } catch (Throwable $e) {
            error_log('Featured auto inactive failed: ' . $e->getMessage());
        }
    }
}

if (!function_exists('fd_find_existing_context_id')) {
    function fd_find_existing_context_id(int $doctor_id, int $district_id, int $thana_id, int $specialty_id, int $ignore_id = 0): int
    {
        global $pdo;

        if (!fd_table_exists('doctor_featured_contexts')) {
            return 0;
        }

        try {
            $sql = "
                SELECT id
                FROM doctor_featured_contexts
                WHERE doctor_id = :doctor_id
                AND district_id = :district_id
                AND thana_id = :thana_id
                AND specialty_id = :specialty_id
            ";

            $params = [
                ':doctor_id' => $doctor_id,
                ':district_id' => $district_id,
                ':thana_id' => $thana_id,
                ':specialty_id' => $specialty_id,
            ];

            if ($ignore_id > 0) {
                $sql .= " AND id <> :ignore_id";
                $params[':ignore_id'] = $ignore_id;
            }

            $sql .= " ORDER BY id DESC LIMIT 1";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            return (int)$stmt->fetchColumn();
        } catch (Throwable $e) {
            return 0;
        }
    }
}

if (!function_exists('fd_context_exists')) {
    function fd_context_exists(int $doctor_id, int $district_id, int $thana_id, int $specialty_id, int $ignore_id = 0): bool
    {
        return fd_find_existing_context_id($doctor_id, $district_id, $thana_id, $specialty_id, $ignore_id) > 0;
    }
}

if (!function_exists('fd_position_exists')) {
    function fd_position_exists(int $district_id, int $thana_id, int $specialty_id, int $position, int $ignore_id = 0): bool
    {
        global $pdo;

        if ($position <= 0 || !fd_table_exists('doctor_featured_contexts')) {
            return false;
        }

        try {
            $sql = "
                SELECT id
                FROM doctor_featured_contexts
                WHERE district_id = :district_id
                AND thana_id = :thana_id
                AND specialty_id = :specialty_id
                AND featured_position = :featured_position
                AND status IN ('active', 'hold')
                AND (
                    status = 'active'
                    OR (status = 'hold' AND remaining_days > 0)
                )
            ";

            $params = [
                ':district_id' => $district_id,
                ':thana_id' => $thana_id,
                ':specialty_id' => $specialty_id,
                ':featured_position' => $position,
            ];

            if ($ignore_id > 0) {
                $sql .= " AND id <> :ignore_id";
                $params[':ignore_id'] = $ignore_id;
            }

            $sql .= " LIMIT 1";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            return (int)$stmt->fetchColumn() > 0;
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('fd_get_booked_positions')) {
    function fd_get_booked_positions(int $district_id, int $thana_id, int $specialty_id, int $ignore_id = 0): array
    {
        global $pdo;

        if (!fd_table_exists('doctor_featured_contexts')) {
            return [];
        }

        try {
            $sql = "
                SELECT featured_position
                FROM doctor_featured_contexts
                WHERE district_id = :district_id
                AND thana_id = :thana_id
                AND specialty_id = :specialty_id
                AND featured_position > 0
                AND status IN ('active', 'hold')
                AND (
                    status = 'active'
                    OR (status = 'hold' AND remaining_days > 0)
                )
            ";

            $params = [
                ':district_id' => $district_id,
                ':thana_id' => $thana_id,
                ':specialty_id' => $specialty_id,
            ];

            if ($ignore_id > 0) {
                $sql .= " AND id <> :ignore_id";
                $params[':ignore_id'] = $ignore_id;
            }

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
        } catch (Throwable $e) {
            return [];
        }
    }
}

if (!function_exists('fd_add_featured_history')) {
    function fd_add_featured_history(int $context_id, string $action_type, string $status, string $note = ''): void
    {
        global $pdo;

        if ($context_id <= 0 || !fd_table_exists('doctor_featured_contexts') || !fd_table_exists('doctor_featured_context_history')) {
            return;
        }

        $record = fd_get_record($context_id);

        if (empty($record)) {
            return;
        }

        try {
            $stmt = $pdo->prepare("
                INSERT INTO doctor_featured_context_history
                (
                    context_id,
                    doctor_id,
                    district_id,
                    thana_id,
                    specialty_id,
                    featured_days,
                    featured_started_at,
                    featured_until,
                    featured_position,
                    remaining_days,
                    action_type,
                    status,
                    note,
                    created_at
                )
                VALUES
                (
                    :context_id,
                    :doctor_id,
                    :district_id,
                    :thana_id,
                    :specialty_id,
                    :featured_days,
                    :featured_started_at,
                    :featured_until,
                    :featured_position,
                    :remaining_days,
                    :action_type,
                    :status,
                    :note,
                    NOW()
                )
            ");

            $stmt->execute([
                ':context_id' => $context_id,
                ':doctor_id' => (int)($record['doctor_id'] ?? 0),
                ':district_id' => (int)($record['district_id'] ?? 0),
                ':thana_id' => (int)($record['thana_id'] ?? 0),
                ':specialty_id' => (int)($record['specialty_id'] ?? 0),
                ':featured_days' => (int)($record['featured_days'] ?? 0),
                ':featured_started_at' => $record['featured_started_at'] ?: null,
                ':featured_until' => $record['featured_until'] ?: null,
                ':featured_position' => (int)($record['featured_position'] ?? 0),
                ':remaining_days' => (int)($record['remaining_days'] ?? 0),
                ':action_type' => $action_type,
                ':status' => $status,
                ':note' => $note,
            ]);
        } catch (Throwable $e) {
            error_log('Featured context history insert failed: ' . $e->getMessage());
        }
    }
}

if (!function_exists('fd_get_featured_history')) {
    function fd_get_featured_history(int $context_id, int $limit = 30): array
    {
        global $pdo;

        if ($context_id <= 0 || !fd_table_exists('doctor_featured_context_history')) {
            return [];
        }

        try {
            $stmt = $pdo->prepare("
                SELECT *
                FROM doctor_featured_context_history
                WHERE context_id = :context_id
                ORDER BY id DESC
                LIMIT :limit_count
            ");
            $stmt->bindValue(':context_id', $context_id, PDO::PARAM_INT);
            $stmt->bindValue(':limit_count', max(1, min(100, $limit)), PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }
}

if (!function_exists('fd_save_active')) {
    function fd_save_active(
        int $id,
        int $doctor_id,
        int $district_id,
        int $thana_id,
        int $specialty_id,
        int $days,
        int $position
    ): int {
        global $pdo;

        $today = date('Y-m-d');
        $until = fd_calculate_until($days, $today);

        if (!$until) {
            throw new RuntimeException('Featured end date could not be generated.');
        }

        if ($id > 0) {
            $stmt = $pdo->prepare("
                UPDATE doctor_featured_contexts
                SET doctor_id = :doctor_id,
                    district_id = :district_id,
                    thana_id = :thana_id,
                    specialty_id = :specialty_id,
                    featured_days = :featured_days,
                    featured_started_at = :featured_started_at,
                    featured_until = :featured_until,
                    featured_position = :featured_position,
                    remaining_days = :remaining_days,
                    status = 'active',
                    updated_at = NOW()
                WHERE id = :id
            ");

            $stmt->execute([
                ':doctor_id' => $doctor_id,
                ':district_id' => $district_id,
                ':thana_id' => $thana_id,
                ':specialty_id' => $specialty_id,
                ':featured_days' => $days,
                ':featured_started_at' => $today,
                ':featured_until' => $until,
                ':featured_position' => $position,
                ':remaining_days' => $days,
                ':id' => $id,
            ]);

            return $id;
        }

        $stmt = $pdo->prepare("
            INSERT INTO doctor_featured_contexts
            (
                doctor_id,
                district_id,
                thana_id,
                specialty_id,
                featured_days,
                featured_started_at,
                featured_until,
                featured_position,
                remaining_days,
                status,
                created_at,
                updated_at
            )
            VALUES
            (
                :doctor_id,
                :district_id,
                :thana_id,
                :specialty_id,
                :featured_days,
                :featured_started_at,
                :featured_until,
                :featured_position,
                :remaining_days,
                'active',
                NOW(),
                NOW()
            )
        ");

        $stmt->execute([
            ':doctor_id' => $doctor_id,
            ':district_id' => $district_id,
            ':thana_id' => $thana_id,
            ':specialty_id' => $specialty_id,
            ':featured_days' => $days,
            ':featured_started_at' => $today,
            ':featured_until' => $until,
            ':featured_position' => $position,
            ':remaining_days' => $days,
        ]);

        return (int)$pdo->lastInsertId();
    }
}

if (!function_exists('fd_update_status')) {
    function fd_update_status(int $id, string $action): void
    {
        global $pdo;

        $record = fd_get_record($id);

        if (empty($record)) {
            $_SESSION['flash_error'] = 'Featured record was not found.';
            fd_redirect();
        }

        $today = date('Y-m-d');

        if ($action === 'hold') {
            $remaining = fd_remaining_days($record['featured_until'] ?? '');

            if ($remaining <= 0) {
                $remaining = max(0, (int)($record['remaining_days'] ?? 0));
            }

            if ($remaining <= 0) {
                $stmt = $pdo->prepare("
                    UPDATE doctor_featured_contexts
                    SET status = 'inactive',
                        remaining_days = 0,
                        updated_at = NOW()
                    WHERE id = :id
                ");
                $stmt->execute([':id' => $id]);

                fd_add_featured_history($id, 'inactive', 'inactive', 'No remaining days. Featured profile was marked inactive.');

                $_SESSION['flash_success'] = 'Remaining days are 0, so featured record is inactive now.';
                fd_redirect(['edit' => $id]);
            }

            $stmt = $pdo->prepare("
                UPDATE doctor_featured_contexts
                SET status = 'hold',
                    remaining_days = :remaining_days,
                    updated_at = NOW()
                WHERE id = :id
            ");
            $stmt->execute([
                ':remaining_days' => $remaining,
                ':id' => $id,
            ]);

            fd_add_featured_history($id, 'hold', 'hold', 'Featured profile was held with remaining days.');

            $_SESSION['flash_success'] = 'Featured record is now on hold.';
            fd_redirect(['edit' => $id]);
        }

        if ($action === 'resume') {
            $remaining = max(0, (int)($record['remaining_days'] ?? 0));

            if ($remaining <= 0) {
                $stmt = $pdo->prepare("
                    UPDATE doctor_featured_contexts
                    SET status = 'inactive',
                        remaining_days = 0,
                        updated_at = NOW()
                    WHERE id = :id
                ");
                $stmt->execute([':id' => $id]);

                fd_add_featured_history($id, 'inactive', 'inactive', 'No remaining days to resume.');

                $_SESSION['flash_error'] = 'No remaining days found. This record is inactive now.';
                fd_redirect(['edit' => $id]);
            }

            $until = fd_calculate_until($remaining, $today);

            $stmt = $pdo->prepare("
                UPDATE doctor_featured_contexts
                SET status = 'active',
                    featured_started_at = :started_at,
                    featured_until = :featured_until,
                    remaining_days = :remaining_days,
                    updated_at = NOW()
                WHERE id = :id
            ");
            $stmt->execute([
                ':started_at' => $today,
                ':featured_until' => $until,
                ':remaining_days' => $remaining,
                ':id' => $id,
            ]);

            fd_add_featured_history($id, 'resume', 'active', 'Featured profile was resumed from remaining days.');

            $_SESSION['flash_success'] = 'Featured record resumed successfully.';
            fd_redirect(['edit' => $id]);
        }

        if ($action === 'stop') {
            $stmt = $pdo->prepare("
                UPDATE doctor_featured_contexts
                SET status = 'inactive',
                    remaining_days = 0,
                    updated_at = NOW()
                WHERE id = :id
            ");
            $stmt->execute([':id' => $id]);

            fd_add_featured_history($id, 'stop', 'inactive', 'Featured profile was marked inactive.');

            $_SESSION['flash_success'] = 'Featured record marked inactive.';
            fd_redirect(['edit' => $id]);
        }

        $_SESSION['flash_error'] = 'Invalid action.';
        fd_redirect(['edit' => $id]);
    }
}

fd_boot_context_table();
fd_boot_history_table();
fd_auto_inactive_expired_records();

if (empty($_SESSION['featured_context_csrf'])) {
    $_SESSION['featured_context_csrf'] = bin2hex(random_bytes(32));
}

$edit_id = max(0, (int)($_GET['edit'] ?? 0));
$record = fd_get_record($edit_id);

$doctor_id = max(0, (int)($_GET['doctor_id'] ?? ($record['doctor_id'] ?? 0)));
$district_id = max(0, (int)($_GET['district_id'] ?? ($record['district_id'] ?? 0)));
$thana_id = max(0, (int)($_GET['thana_id'] ?? ($record['thana_id'] ?? 0)));
$specialty_id = max(0, (int)($_GET['specialty_id'] ?? ($record['specialty_id'] ?? 0)));

$doctor = fd_get_doctor_by_id($doctor_id);

$doctor_specialty_name = '';
$doctor_degree_text = '';

if (!empty($doctor)) {
    $doctor_specialty_id = (int)($doctor['specialty_id'] ?? $specialty_id ?? 0);
    $doctor_specialty_name = fd_get_item_name_by_id('specialties', $doctor_specialty_id);

    foreach (['degree', 'degrees', 'qualification', 'qualifications', 'education'] as $degree_column) {
        if (array_key_exists($degree_column, $doctor) && trim((string)$doctor[$degree_column]) !== '') {
            $doctor_degree_text = trim((string)$doctor[$degree_column]);
            break;
        }
    }
}

if ($doctor_id > 0 && empty($record) && !empty($doctor)) {
    if ($district_id <= 0 && fd_column_exists('doctors', 'doctor_district_id')) {
        $district_id = (int)($doctor['doctor_district_id'] ?? 0);
    }

    if ($thana_id <= 0 && fd_column_exists('doctors', 'doctor_thana_id')) {
        $thana_id = (int)($doctor['doctor_thana_id'] ?? 0);
    }

    if ($specialty_id <= 0 && fd_column_exists('doctors', 'specialty_id')) {
        $specialty_id = (int)($doctor['specialty_id'] ?? 0);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = (string)($_POST['csrf_token'] ?? '');

    if (!hash_equals($_SESSION['featured_context_csrf'], $csrf_token)) {
        $_SESSION['flash_error'] = 'Invalid security token. Please try again.';
        fd_redirect($edit_id > 0 ? ['edit' => $edit_id] : []);
    }

    $action = trim((string)($_POST['featured_action'] ?? 'save'));
    $record_id = max(0, (int)($_POST['record_id'] ?? 0));

    if (in_array($action, ['hold', 'resume', 'stop'], true)) {
        fd_update_status($record_id, $action);
    }

    $posted_doctor_id = max(0, (int)($_POST['doctor_id'] ?? 0));
    $posted_district_id = max(0, (int)($_POST['district_id'] ?? 0));
    $posted_thana_id = max(0, (int)($_POST['thana_id'] ?? 0));
    $posted_specialty_id = max(0, (int)($_POST['specialty_id'] ?? 0));
    $featured_days_input = trim((string)($_POST['featured_days'] ?? ''));
    $featured_position = max(0, (int)($_POST['featured_position'] ?? 0));

    $current_remaining_for_adjust = 0;

    if ($record_id > 0) {
        $current_record_for_adjust = fd_get_record($record_id);
        $current_remaining_for_adjust = !empty($current_record_for_adjust)
            ? fd_current_remaining_days($current_record_for_adjust)
            : 0;
    }

    $featured_days = fd_parse_duration_input($featured_days_input, $current_remaining_for_adjust);

    if ($posted_doctor_id <= 0 || $posted_district_id <= 0 || $posted_specialty_id <= 0 || $featured_days <= 0) {
        $_SESSION['flash_error'] = 'Please select doctor, district, specialty and active duration.';

        fd_redirect($record_id > 0 ? ['edit' => $record_id] : [
            'doctor_id' => $posted_doctor_id,
            'district_id' => $posted_district_id,
            'thana_id' => $posted_thana_id,
            'specialty_id' => $posted_specialty_id,
        ]);
    }

    if ($featured_position > 0 && fd_position_exists($posted_district_id, $posted_thana_id, $posted_specialty_id, $featured_position, $record_id)) {
        $_SESSION['flash_error'] = 'This featured position is already used for the selected district, thana and specialty. Please choose another position or Auto Position.';

        fd_redirect($record_id > 0 ? ['edit' => $record_id] : [
            'doctor_id' => $posted_doctor_id,
            'district_id' => $posted_district_id,
            'thana_id' => $posted_thana_id,
            'specialty_id' => $posted_specialty_id,
        ]);
    }

    $existing_context_id = fd_find_existing_context_id(
        $posted_doctor_id,
        $posted_district_id,
        $posted_thana_id,
        $posted_specialty_id,
        $record_id
    );

    if ($existing_context_id > 0) {
        $_SESSION['flash_error'] = 'This doctor is already featured for selected district, thana and specialty. Showing existing featured history.';
        fd_redirect(['edit' => $existing_context_id]);
    }

    try {
        $saved_id = fd_save_active(
            $record_id,
            $posted_doctor_id,
            $posted_district_id,
            $posted_thana_id,
            $posted_specialty_id,
            $featured_days,
            $featured_position
        );

        fd_add_featured_history(
            $saved_id,
            $record_id > 0 ? 'update' : 'active',
            'active',
            $record_id > 0 ? 'Featured profile was updated.' : 'Featured profile was activated.'
        );

        $_SESSION['flash_success'] = $record_id > 0 ? 'Featured record updated successfully.' : 'Featured record added successfully.';
        fd_redirect(['edit' => $saved_id]);
    } catch (Throwable $e) {
        $_SESSION['flash_error'] = 'Featured record could not be saved.';
        fd_redirect($record_id > 0 ? ['edit' => $record_id] : []);
    }
}

$divisions = fd_get_items('divisions');
$districts = fd_get_districts();
$thanas = fd_get_thanas();
$specialties = fd_get_specialties();

$form = [
    'id' => (int)($record['id'] ?? 0),
    'doctor_id' => (int)($record['doctor_id'] ?? $doctor_id),
    'district_id' => (int)($record['district_id'] ?? $district_id),
    'thana_id' => (int)($record['thana_id'] ?? $thana_id),
    'specialty_id' => (int)($record['specialty_id'] ?? $specialty_id),
    'featured_days' => (int)($record['featured_days'] ?? 0),
    'featured_position' => (int)($record['featured_position'] ?? 0),
    'featured_started_at' => (string)($record['featured_started_at'] ?? ''),
    'featured_until' => (string)($record['featured_until'] ?? ''),
    'remaining_days' => (int)($record['remaining_days'] ?? 0),
    'status' => (string)($record['status'] ?? 'active'),
];

$booked_positions = fd_get_booked_positions(
    (int)$form['district_id'],
    (int)$form['thana_id'],
    (int)$form['specialty_id'],
    (int)$form['id']
);

$current_remaining_for_input = !empty($record)
    ? fd_current_remaining_days($record)
    : 0;

$duration_input_value = $current_remaining_for_input > 0
    ? (string)$current_remaining_for_input
    : (!empty($form['featured_days']) ? (string)$form['featured_days'] : '365');

if ((int)$duration_input_value <= 0) {
    $duration_input_value = '365';
}

$featured_history = !empty($form['id']) ? fd_get_featured_history((int)$form['id'], 30) : [];

$featured_day_options = [
    180 => '6 Months',
    365 => '1 Year',
    730 => '2 Years',
    1095 => '3 Years',
    1825 => '5 Years',
];

require_once __DIR__ . '/includes/header.php';
?>

<style>
.fd-page{max-width:1120px;margin:0 auto;color:#24292f}
.fd-header{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;margin-bottom:16px;padding-bottom:16px;border-bottom:1px solid #d0d7de}
.fd-header h1{margin:0;color:#24292f;font-size:24px;line-height:1.25;font-weight:600}
.fd-header p{margin:6px 0 0;color:#57606a;font-size:14px;line-height:1.5}
.fd-card,.fd-summary-card{background:#fff;border:1px solid #d0d7de;border-radius:6px;box-shadow:0 1px 0 rgba(27,31,36,.04);margin-bottom:16px;overflow:hidden}
.fd-card-head,.fd-summary-head{padding:12px 16px;background:#f6f8fa;border-bottom:1px solid #d0d7de}
.fd-card-head h2,.fd-summary-head span:first-child{margin:0;color:#24292f;font-size:15px;line-height:1.4;font-weight:600}
.fd-card-head p{margin:4px 0 0;color:#57606a;font-size:13px;line-height:1.5}
.fd-card-body,.fd-summary-body{padding:16px}
.fd-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px 16px}
.fd-field{display:grid;gap:6px;margin-bottom:12px}
.fd-field label{color:#24292f;font-size:13px;font-weight:600}
.fd-field input,.fd-field select{width:100%;min-height:34px;border:1px solid #d0d7de;border-radius:6px;background:#fff;color:#24292f;padding:6px 12px;font-family:inherit;font-size:14px;line-height:20px;outline:none}
.fd-field input:focus,.fd-field select:focus{border-color:#0969da;box-shadow:0 0 0 3px rgba(9,105,218,.12)}
.fd-help{display:block;margin-top:4px;color:#57606a;font-size:12px;line-height:1.45}
.fd-actions{display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-top:14px;padding-top:14px;border-top:1px solid #d8dee4}
.fd-btn{display:inline-flex;align-items:center;justify-content:center;min-height:32px;padding:5px 12px;border-radius:6px;border:1px solid rgba(27,31,36,.15);background:#f6f8fa;color:#24292f;cursor:pointer;font-size:13px;font-weight:600;text-decoration:none;line-height:20px;box-shadow:0 1px 0 rgba(27,31,36,.04)}
.fd-btn:hover{background:#eef1f4;text-decoration:none}
.fd-btn-primary{background:#2da44e;color:#fff;border-color:rgba(27,31,36,.15)}
.fd-btn-primary:hover{background:#2c974b;color:#fff}
.fd-btn-light{background:#f6f8fa;color:#24292f}
.fd-btn-orange{background:#fff8c5;color:#9a6700;border-color:#f0d98c}
.fd-btn-red{background:#ffebe9;color:#cf222e;border-color:#ff818266}
.fd-alert{margin-bottom:14px;padding:12px 14px;border-radius:6px;font-size:14px;line-height:1.5;border:1px solid #d0d7de}
.fd-alert.success{background:#dafbe1;border-color:#aceebb;color:#116329}
.fd-alert.error{background:#ffebe9;border-color:#ff818266;color:#cf222e}
.fd-pill{display:inline-flex;align-items:center;gap:6px;padding:3px 8px;border-radius:999px;border:1px solid #d0d7de;background:#f6f8fa;color:#57606a;font-size:12px;font-weight:600;line-height:18px}
.fd-pill.green{background:#dafbe1;border-color:#aceebb;color:#116329}
.fd-pill.blue{background:#ddf4ff;border-color:#b6e3ff;color:#0969da}
.fd-pill.red{background:#ffebe9;border-color:#ff818266;color:#cf222e}
.fd-doctor-box{display:flex;gap:14px;align-items:center;padding:16px;border:1px solid #d0d7de;border-radius:6px;background:#f6f8fa;margin-bottom:16px}
.fd-doctor-box strong{display:block;color:#24292f;font-size:18px;line-height:1.3;font-weight:600}
.fd-doctor-box span{display:block;color:#57606a;font-size:13px;line-height:1.45;margin-top:4px}
.fd-avatar{width:56px;height:56px;display:inline-flex;align-items:center;justify-content:center;border-radius:6px;overflow:hidden;border:1px solid #d0d7de;background:#fff;color:#57606a;font-size:12px;font-weight:600;flex:0 0 auto}
.fd-avatar img{width:100%;height:100%;object-fit:cover}
.fd-popup-style{border:1px solid #d0d7de;border-radius:6px;background:#fff;overflow:hidden}
.fd-popup-head{display:flex;align-items:flex-start;gap:12px;padding:14px 16px;background:#f6f8fa;border-bottom:1px solid #d0d7de}
.fd-popup-icon{width:32px;height:32px;border-radius:6px;display:inline-flex;align-items:center;justify-content:center;color:#0969da;background:#ddf4ff;border:1px solid #b6e3ff;font-size:13px;font-weight:700;flex:0 0 auto}
.fd-popup-head h3{margin:0;color:#24292f;font-size:16px;line-height:1.35;font-weight:600}
.fd-popup-head p{margin:4px 0 0;color:#57606a;font-size:13px;line-height:1.45}
.fd-popup-body{padding:16px}
.fd-summary-head{display:flex;align-items:center;justify-content:space-between;gap:12px;font-weight:600}
.fd-summary-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}
.fd-summary-grid.three{grid-template-columns:repeat(3,minmax(0,1fr));margin-top:10px}
.fd-summary-item{padding:10px 12px;border:1px solid #d0d7de;border-radius:6px;background:#fff}
.fd-summary-item span{display:block;color:#57606a;font-size:12px;margin-bottom:6px}
.fd-summary-item strong{display:block;color:#24292f;font-size:13px;font-weight:600}
.fd-history-list{border:1px solid #d0d7de;border-radius:6px;overflow:hidden;background:#fff}
.fd-history-row{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:12px;border-bottom:1px solid #d8dee4}
.fd-history-row:last-child{border-bottom:0}
.fd-history-main strong{display:block;color:#24292f;font-size:14px;font-weight:600}
.fd-history-main span{display:block;color:#57606a;font-size:12px;margin-top:4px}
.fd-history-action{display:inline-flex;align-items:center;padding:3px 8px;border-radius:999px;border:1px solid #d0d7de;background:#f6f8fa;color:#57606a;font-size:12px;font-weight:600;text-transform:lowercase}
.fd-options-box{margin-top:12px;padding:12px;border:1px solid #d0d7de;border-radius:6px;background:#f6f8fa}
.fd-options-box strong{display:block;color:#24292f;font-size:13px;margin-bottom:6px}
.fd-options-list{margin:0;padding-left:18px;color:#57606a;font-size:12.5px;line-height:1.7}
.fd-note{margin:12px 0 0;padding:10px 12px;border:1px solid #d0d7de;border-radius:6px;background:#f6f8fa;color:#57606a;font-size:13px;line-height:1.5}
@media(max-width:760px){
    .fd-grid,.fd-summary-grid,.fd-summary-grid.three{grid-template-columns:1fr}
    .fd-header{flex-direction:column}
    .fd-actions,.fd-btn{width:100%}
    .fd-history-row{flex-direction:column;align-items:flex-start}
}
</style>

<div class="fd-page">
    <div class="fd-header">
        <div>
            <h1><?= !empty($form['id']) ? 'Step 2: Edit Featured' : 'Step 2: Add Featured' ?></h1>
            <p>This page saves the exact context: location, specialty and doctor.</p>
        </div>

        <a href="featured-doctors-form.php" class="fd-btn fd-btn-light">Back to Step 1</a>
    </div>

    <?php if (!empty($_SESSION['flash_success'])): ?>
        <div class="fd-alert success"><?= e($_SESSION['flash_success']) ?></div>
        <?php unset($_SESSION['flash_success']); ?>
    <?php endif; ?>

    <?php if (!empty($_SESSION['flash_error'])): ?>
        <div class="fd-alert error"><?= e($_SESSION['flash_error']) ?></div>
        <?php unset($_SESSION['flash_error']); ?>
    <?php endif; ?>

    <?php if (!empty($doctor)): ?>
        <?php
            $doctor_subtitle_parts = [];

            if ($doctor_specialty_name !== '') {
                $doctor_subtitle_parts[] = $doctor_specialty_name;
            }

            if ($doctor_degree_text !== '') {
                $doctor_subtitle_parts[] = $doctor_degree_text;
            }

            $doctor_subtitle = implode(' | ', $doctor_subtitle_parts);
        ?>
        <div class="fd-doctor-box">
            <?= fd_doctor_avatar($doctor) ?>
            <div>
                <strong><?= e((string)($doctor['name'] ?? 'Doctor')) ?></strong>

                <?php if ($doctor_subtitle !== ''): ?>
                    <span><?= e($doctor_subtitle) ?></span>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if (!empty($form['id'])): ?>
        <?php
            $status_class = $form['status'] === 'active' ? 'green' : ($form['status'] === 'hold' ? 'blue' : 'red');
            $remaining_days = $form['status'] === 'hold'
                ? $form['remaining_days']
                : fd_remaining_days($form['featured_until']);

            $duration_label = !empty($form['featured_days']) ? (string)$form['featured_days'] . ' days' : 'Not set';
            $position_label = !empty($form['featured_position']) ? 'Position ' . (int)$form['featured_position'] : 'Auto Position';
        ?>

        <div class="fd-summary-card">
            <div class="fd-summary-head">
                <span>Featured Summary</span>
                <span class="fd-pill <?= e($status_class) ?>"><?= e(ucwords($form['status'])) ?></span>
            </div>

            <div class="fd-summary-body">
                <div class="fd-summary-grid">
                    <div class="fd-summary-item">
                        <span>Duration</span>
                        <strong><?= e($duration_label) ?></strong>
                    </div>

                    <div class="fd-summary-item">
                        <span>Position</span>
                        <strong><?= e($position_label) ?></strong>
                    </div>
                </div>

                <div class="fd-summary-grid three">
                    <div class="fd-summary-item">
                        <span>Started</span>
                        <strong><?= e($form['featured_started_at'] ?: 'Not set') ?></strong>
                    </div>

                    <div class="fd-summary-item">
                        <span>End Date</span>
                        <strong><?= e($form['featured_until'] ?: 'Not set') ?></strong>
                    </div>

                    <div class="fd-summary-item">
                        <span>Remaining</span>
                        <strong><?= e((string)$remaining_days) ?> days</strong>
                    </div>
                </div>

                <p class="fd-note">
                    If remaining days become 0, this featured record will be inactive automatically and the position will become available again.
                </p>
            </div>
        </div>
    <?php endif; ?>

    <div class="fd-card">
        <div class="fd-card-head">
            <h2>Featured Context Settings</h2>
            <p>Same location, specialty and position cannot be duplicated while active or hold.</p>
        </div>

        <div class="fd-card-body">
            <form method="POST" action="featured-doctors-context.php<?= !empty($form['id']) ? '?edit=' . e((string)$form['id']) : '' ?>">
                <input type="hidden" name="csrf_token" value="<?= e($_SESSION['featured_context_csrf']) ?>">
                <input type="hidden" name="record_id" value="<?= e((string)$form['id']) ?>">
                <input type="hidden" name="doctor_id" value="<?= e((string)$form['doctor_id']) ?>">

                <div class="fd-popup-style">
                    <div class="fd-popup-head">
                        <span class="fd-popup-icon">F</span>
                        <div>
                            <h3>Featured Profile</h3>
                            <p>Choose exact context, active duration and featured position.</p>
                        </div>
                    </div>

                    <div class="fd-popup-body">
                        <div class="fd-grid">
                            <div class="fd-field">
                                <label>District <span style="color:#cf222e;">*</span></label>
                                <select name="district_id" id="fdSaveDistrict" required>
                                    <option value="0">Select District</option>
                                    <?php foreach ($districts as $district): ?>
                                        <option
                                            value="<?= e((string)$district['id']) ?>"
                                            data-division-id="<?= e((string)($district['division_id'] ?? 0)) ?>"
                                            <?= (int)$form['district_id'] === (int)$district['id'] ? 'selected' : '' ?>
                                        >
                                            <?= e((string)$district['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="fd-field">
                                <label>Thana / Area Optional</label>
                                <select name="thana_id" id="fdSaveThana">
                                    <option value="0">No Thana / District Level Featured</option>
                                    <?php foreach ($thanas as $thana): ?>
                                        <option
                                            value="<?= e((string)$thana['id']) ?>"
                                            data-district-id="<?= e((string)($thana['district_id'] ?? 0)) ?>"
                                            <?= (int)$form['thana_id'] === (int)$thana['id'] ? 'selected' : '' ?>
                                        >
                                            <?= e((string)$thana['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="fd-field">
                                <label>Specialty <span style="color:#cf222e;">*</span></label>
                                <select name="specialty_id" required>
                                    <option value="0">Select Specialty</option>
                                    <?php foreach ($specialties as $specialty): ?>
                                        <option
                                            value="<?= e((string)($specialty['id'] ?? 0)) ?>"
                                            <?= (int)$form['specialty_id'] === (int)($specialty['id'] ?? 0) ? 'selected' : '' ?>
                                        >
                                            <?= e((string)($specialty['name'] ?? '')) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="fd-field">
                                <label>Active Duration / Remaining Adjust <span style="color:#cf222e;">*</span></label>
                                <input
                                    type="text"
                                    name="featured_days"
                                    list="featuredDaysPresets"
                                    value="<?= e($duration_input_value) ?>"
                                    placeholder="300, +300, -300, 500+300, 500-300"
                                    required
                                >

                                <datalist id="featuredDaysPresets">
                                    <?php foreach ($featured_day_options as $days => $label): ?>
                                        <option value="<?= e((string)$days) ?>"><?= e($label) ?></option>
                                    <?php endforeach; ?>
                                </datalist>

                                <span class="fd-help">
                                    Use 300 to set, +300 to add with current remaining, -300 to reduce, or write 500+300 / 500-300.
                                </span>
                            </div>

                            <div class="fd-field">
                                <label>Featured Position (1–10)</label>
                                <select name="featured_position">
                                    <option value="0">Auto Position</option>

                                    <?php for ($position = 1; $position <= 10; $position++): ?>
                                        <?php
                                            $is_current_position = (int)$form['featured_position'] === $position;
                                            $is_position_booked = in_array($position, $booked_positions, true);

                                            if ($is_position_booked && !$is_current_position) {
                                                continue;
                                            }
                                        ?>

                                        <option value="<?= e((string)$position) ?>" <?= $is_current_position ? 'selected' : '' ?>>
                                            Position <?= e((string)$position) ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>

                                <?php if (!empty($booked_positions)): ?>
                                    <span class="fd-help">Booked positions are hidden for this location and specialty.</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="fd-options-box">
                            <strong>Optional behavior</strong>
                            <ul class="fd-options-list">
                                <li>Auto Position can be used when you do not want to manually book a position.</li>
                                <li>Booked positions are hidden for the selected location and specialty.</li>
                                <li>When remaining days become 0, the record becomes inactive and the position becomes available again.</li>
                                <li>Active Duration supports 300, +300, -300, 500+300, and 500-300 format.</li>
                                <li>Position list shows only available slots from Position 1 to Position 10.</li>
                            </ul>
                        </div>

                        <div class="fd-actions">
                            <button type="submit" name="featured_action" value="save" class="fd-btn fd-btn-primary">
                                <?= !empty($form['id']) ? 'Update Featured' : 'Add Featured' ?>
                            </button>

                            <?php if (!empty($form['id'])): ?>
                                <?php if ($form['status'] === 'active'): ?>
                                    <button
                                        type="submit"
                                        name="featured_action"
                                        value="hold"
                                        class="fd-btn fd-btn-orange"
                                        onclick="return confirm('Hold this featured record?');"
                                    >
                                        Hold
                                    </button>
                                <?php endif; ?>

                                <?php if ($form['status'] === 'hold'): ?>
                                    <button
                                        type="submit"
                                        name="featured_action"
                                        value="resume"
                                        class="fd-btn fd-btn-primary"
                                        onclick="return confirm('Resume this featured record?');"
                                    >
                                        Resume
                                    </button>
                                <?php endif; ?>

                                <button
                                    type="submit"
                                    name="featured_action"
                                    value="stop"
                                    class="fd-btn fd-btn-red"
                                    onclick="return confirm('Stop this featured record?');"
                                >
                                    Stop
                                </button>
                            <?php endif; ?>

                            <a href="featured-doctors-form.php" class="fd-btn fd-btn-light">Back to Step 1</a>
                            <a href="featured-doctors.php" class="fd-btn fd-btn-light">Back to List</a>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <?php if (!empty($form['id'])): ?>
        <div class="fd-summary-card">
            <div class="fd-summary-head">
                <span>Featured History</span>
            </div>

            <div class="fd-summary-body">
                <div class="fd-history-list">
                    <?php if (empty($featured_history)): ?>
                        <div class="fd-history-row">
                            <div class="fd-history-main">
                                <strong>No history found yet.</strong>
                                <span>History will appear after add, update, hold, resume or stop.</span>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php foreach ($featured_history as $history): ?>
                        <?php
                            $history_position = (int)($history['featured_position'] ?? 0);
                            $history_position_label = $history_position > 0 ? 'Position ' . $history_position : 'Auto Position';
                            $history_total_days = (int)($history['featured_days'] ?? 0);
                            $history_remaining_days = (int)($history['remaining_days'] ?? 0);
                            $history_run_days = fd_history_run_days(
                                $history['featured_started_at'] ?? '',
                                $history['created_at'] ?? '',
                                $history_total_days,
                                $history_remaining_days
                            );
                            $history_range = trim((string)($history['featured_started_at'] ?? '')) . ' → ' . trim((string)($history['featured_until'] ?? ''));
                        ?>
                        <div class="fd-history-row">
                            <div class="fd-history-main">
                                <strong><?= e($history_range) ?></strong>
                                <span>
                                    Run: <?= e((string)$history_run_days) ?> days,
                                    Remaining then: <?= e((string)$history_remaining_days) ?> days,
                                    Total: <?= e((string)$history_total_days) ?> days,
                                    <?= e($history_position_label) ?>
                                    <?php if (!empty($history['note'])): ?>
                                        | <?= e((string)$history['note']) ?>
                                    <?php endif; ?>
                                </span>
                            </div>

                            <span class="fd-history-action"><?= e((string)($history['action_type'] ?? $history['status'] ?? 'active')) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const districtSelect = document.getElementById('fdSaveDistrict');
    const thanaSelect = document.getElementById('fdSaveThana');

    if (!districtSelect || !thanaSelect) {
        return;
    }

    const thanaOptions = Array.from(thanaSelect.options);

    function filterThanas() {
        const districtId = districtSelect.value || '0';
        const currentThana = thanaSelect.value || '0';

        thanaSelect.innerHTML = '';

        thanaOptions.forEach(function (option) {
            const optionDistrictId = option.getAttribute('data-district-id') || '0';

            if (option.value === '0' || districtId === '0' || optionDistrictId === districtId) {
                thanaSelect.appendChild(option);
            }
        });

        const stillExists = Array.from(thanaSelect.options).some(function (option) {
            return option.value === currentThana;
        });

        thanaSelect.value = stillExists ? currentThana : '0';
    }

    filterThanas();

    districtSelect.addEventListener('change', function () {
        thanaSelect.value = '0';
        filterThanas();
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>