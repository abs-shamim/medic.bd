<?php
/**
 * Doctor Form Component: Status Display Options
 * Self-working safe version.
 * Can be placed at: admin/doctors/status-display-options.php
 */

if (!function_exists('e')) {
    function e($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('doctor_component_identifier_safe')) {
    function doctor_component_identifier_safe(string $identifier): bool
    {
        return (bool)preg_match('/^[a-zA-Z0-9_]+$/', $identifier);
    }
}

if (!function_exists('doctor_component_pdo_available')) {
    function doctor_component_pdo_available(): bool
    {
        global $pdo;
        return isset($pdo) && $pdo instanceof PDO;
    }
}

if (!function_exists('doctor_component_table_exists')) {
    function doctor_component_table_exists(string $table): bool
    {
        global $pdo;

        if (!doctor_component_pdo_available() || !doctor_component_identifier_safe($table)) {
            return false;
        }

        try {
            $stmt = $pdo->prepare("\n                SELECT COUNT(*)\n                FROM INFORMATION_SCHEMA.TABLES\n                WHERE TABLE_SCHEMA = DATABASE()\n                  AND TABLE_NAME = :table\n            ");
            $stmt->execute([':table' => $table]);

            return (int)$stmt->fetchColumn() > 0;
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('doctor_component_column_exists')) {
    function doctor_component_column_exists(string $table, string $column): bool
    {
        global $pdo;

        if (
            !doctor_component_pdo_available() ||
            !doctor_component_identifier_safe($table) ||
            !doctor_component_identifier_safe($column)
        ) {
            return false;
        }

        try {
            $stmt = $pdo->prepare("\n                SELECT COUNT(*)\n                FROM INFORMATION_SCHEMA.COLUMNS\n                WHERE TABLE_SCHEMA = DATABASE()\n                  AND TABLE_NAME = :table\n                  AND COLUMN_NAME = :column\n            ");
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

if (!function_exists('doctor_component_add_column_if_missing')) {
    function doctor_component_add_column_if_missing(string $table, string $column, string $definition): void
    {
        global $pdo;

        if (
            !doctor_component_pdo_available() ||
            !doctor_component_identifier_safe($table) ||
            !doctor_component_identifier_safe($column) ||
            !doctor_component_table_exists($table)
        ) {
            return;
        }

        try {
            if (!doctor_component_column_exists($table, $column)) {
                $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
            }
        } catch (Throwable $e) {
            // A section component should never break the form because of an optional schema update.
        }
    }
}

/**
 * Doctor Form Component: Status Display Options
 * Lightweight GitHub-style UI.
 * Featured Profile can only be controlled from popup.
 *
 * Rules:
 * - Remaining 0 day auto expires: Featured off + history inactive.
 * - Manual Hold / Off:
 *   - If remaining > 0: hold with saved remaining days.
 *   - If remaining = 0: off + history inactive.
 */

if (!function_exists('doctor_component_table_exists')) {
    function doctor_component_table_exists(string $table): bool
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

if (!function_exists('doctor_component_column_exists')) {
    function doctor_component_column_exists(string $table, string $column): bool
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

if (!function_exists('doctor_component_add_column_if_missing')) {
    function doctor_component_add_column_if_missing(string $table, string $column, string $definition): void
    {
        global $pdo;

        if (!doctor_component_table_exists($table)) {
            return;
        }

        if (!doctor_component_column_exists($table, $column)) {
            $pdo->exec("ALTER TABLE {$table} ADD COLUMN {$column} {$definition}");
        }
    }
}

if (!function_exists('doctor_status_display_options_boot')) {
    function doctor_status_display_options_boot(): void
    {
        global $pdo;

        $columns = [
            'is_verified' => "TINYINT(1) DEFAULT 0",
            'is_featured' => "TINYINT(1) DEFAULT 0",
            'featured_days' => "INT DEFAULT 0",
            'featured_started_at' => "DATE NULL",
            'featured_until' => "DATE NULL",
            'featured_position' => "INT DEFAULT 0",
            'featured_on_hold' => "TINYINT(1) DEFAULT 0",
            'featured_hold_remaining_days' => "INT DEFAULT 0",
            'featured_hold_started_at' => "DATE NULL",
            'emergency_available' => "TINYINT(1) DEFAULT 0",
            'online_consultation' => "TINYINT(1) DEFAULT 0",
            'home_visit' => "TINYINT(1) DEFAULT 0",
            'rating' => "DECIMAL(3,1) DEFAULT 0",
            'reviews_count' => "INT DEFAULT 0",
            'status' => "VARCHAR(30) DEFAULT 'active'",
            'updated_at' => "DATETIME NULL",
        ];

        foreach ($columns as $column => $definition) {
            doctor_component_add_column_if_missing('doctors', $column, $definition);
        }

        if (!doctor_component_table_exists('doctor_featured_history')) {
            $pdo->exec("
                CREATE TABLE doctor_featured_history (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    doctor_id INT NOT NULL DEFAULT 0,
                    featured_days INT DEFAULT 0,
                    featured_started_at DATE NULL,
                    featured_until DATE NULL,
                    featured_position INT DEFAULT 0,
                    remaining_days INT DEFAULT 0,
                    status VARCHAR(30) DEFAULT 'active',
                    created_at DATETIME NULL,
                    INDEX doctor_id_idx (doctor_id),
                    INDEX status_idx (status),
                    INDEX featured_until_idx (featured_until)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
        }

        doctor_component_add_column_if_missing('doctor_featured_history', 'doctor_id', "INT NOT NULL DEFAULT 0");
        doctor_component_add_column_if_missing('doctor_featured_history', 'featured_days', "INT DEFAULT 0");
        doctor_component_add_column_if_missing('doctor_featured_history', 'featured_started_at', "DATE NULL");
        doctor_component_add_column_if_missing('doctor_featured_history', 'featured_until', "DATE NULL");
        doctor_component_add_column_if_missing('doctor_featured_history', 'featured_position', "INT DEFAULT 0");
        doctor_component_add_column_if_missing('doctor_featured_history', 'remaining_days', "INT DEFAULT 0");
        doctor_component_add_column_if_missing('doctor_featured_history', 'status', "VARCHAR(30) DEFAULT 'active'");
        doctor_component_add_column_if_missing('doctor_featured_history', 'created_at', "DATETIME NULL");
    }
}

if (!function_exists('doctor_status_display_options_redirect_back')) {
    function doctor_status_display_options_redirect_back(int $doctor_id, string $saved = 'featured_updated'): void
    {
        $self = basename($_SERVER['PHP_SELF']);
        $url = $self . '?id=' . $doctor_id . '&saved=' . urlencode($saved);

        if (function_exists('redirect')) {
            redirect($url);
            exit;
        }

        header('Location: ' . $url);
        exit;
    }
}

if (!function_exists('doctor_status_display_options_calculate_featured_until')) {
    function doctor_status_display_options_calculate_featured_until(int $days, ?string $started_at = null): ?string
    {
        if ($days <= 0) {
            return null;
        }

        $start = $started_at ?: date('Y-m-d');
        $timestamp = strtotime($start . ' +' . $days . ' days');

        return $timestamp ? date('Y-m-d', $timestamp) : null;
    }
}

if (!function_exists('doctor_status_display_options_remaining_days')) {
    function doctor_status_display_options_remaining_days(string $featured_until): int
    {
        $featured_until = trim($featured_until);

        if ($featured_until === '') {
            return 0;
        }

        try {
            $today = new DateTime(date('Y-m-d'));
            $end_date = new DateTime($featured_until);

            if ($end_date < $today) {
                return 0;
            }

            return (int)$today->diff($end_date)->days;
        } catch (Throwable $e) {
            return 0;
        }
    }
}

if (!function_exists('doctor_status_display_options_get_featured_history')) {
    function doctor_status_display_options_get_featured_history(int $doctor_id, int $limit = 8): array
    {
        global $pdo;

        if ($doctor_id <= 0 || !doctor_component_table_exists('doctor_featured_history')) {
            return [];
        }

        try {
            $limit = max(1, min(20, $limit));

            $stmt = $pdo->prepare("
                SELECT *
                FROM doctor_featured_history
                WHERE doctor_id = :doctor_id
                ORDER BY id DESC
                LIMIT {$limit}
            ");
            $stmt->execute([':doctor_id' => $doctor_id]);

            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }
}

if (!function_exists('doctor_status_display_options_auto_expire_featured')) {
    function doctor_status_display_options_auto_expire_featured(int $doctor_id, array $doctor): array
    {
        global $pdo;

        if ($doctor_id <= 0 || empty($doctor['is_featured'])) {
            return $doctor;
        }

        $featured_until = trim((string)($doctor['featured_until'] ?? ''));

        if ($featured_until === '') {
            return $doctor;
        }

        if ($featured_until > date('Y-m-d')) {
            return $doctor;
        }

        try {
            $stmt = $pdo->prepare("
                UPDATE doctors
                SET
                    is_featured = 0,
                    featured_on_hold = 0,
                    featured_hold_remaining_days = 0,
                    featured_hold_started_at = NULL,
                    updated_at = NOW()
                WHERE id = :id
            ");
            $stmt->execute([':id' => $doctor_id]);

            if (doctor_component_table_exists('doctor_featured_history')) {
                $stmt = $pdo->prepare("
                    UPDATE doctor_featured_history
                    SET
                        status = 'inactive',
                        remaining_days = 0
                    WHERE doctor_id = :doctor_id
                    AND status IN ('active', 'hold')
                ");
                $stmt->execute([':doctor_id' => $doctor_id]);
            }

            $doctor['is_featured'] = 0;
            $doctor['featured_on_hold'] = 0;
            $doctor['featured_hold_remaining_days'] = 0;
            $doctor['featured_hold_started_at'] = '';
        } catch (Throwable $e) {
            // Auto-expire should not break page loading.
        }

        return $doctor;
    }
}

if (!function_exists('doctor_status_display_options_insert_history')) {
    function doctor_status_display_options_insert_history(
        int $doctor_id,
        int $featured_days,
        string $started_at,
        string $featured_until,
        int $featured_position,
        int $remaining_days,
        string $status
    ): void {
        global $pdo;

        if ($doctor_id <= 0 || !doctor_component_table_exists('doctor_featured_history')) {
            return;
        }

        try {
            $stmt = $pdo->prepare("
                INSERT INTO doctor_featured_history
                (
                    doctor_id,
                    featured_days,
                    featured_started_at,
                    featured_until,
                    featured_position,
                    remaining_days,
                    status,
                    created_at
                )
                VALUES
                (
                    :doctor_id,
                    :featured_days,
                    :featured_started_at,
                    :featured_until,
                    :featured_position,
                    :remaining_days,
                    :status,
                    NOW()
                )
            ");

            $stmt->execute([
                ':doctor_id' => $doctor_id,
                ':featured_days' => $featured_days,
                ':featured_started_at' => $started_at,
                ':featured_until' => $featured_until,
                ':featured_position' => $featured_position,
                ':remaining_days' => $remaining_days,
                ':status' => $status,
            ]);
        } catch (Throwable $e) {
            // History insert should not break saving.
        }
    }
}

if (!function_exists('doctor_status_display_options_handle_featured_popup_post')) {
    function doctor_status_display_options_handle_featured_popup_post(int $doctor_id, array $doctor): void
    {
        global $pdo;

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        $action = trim((string)($_POST['featured_popup_action'] ?? ''));

        if ($doctor_id <= 0 || $action === '') {
            return;
        }

        $today = date('Y-m-d');

        $old_days = (int)($doctor['featured_days'] ?? 0);
        $old_position = (int)($doctor['featured_position'] ?? 0);
        $old_until = trim((string)($doctor['featured_until'] ?? ''));
        $old_hold_remaining = (int)($doctor['featured_hold_remaining_days'] ?? 0);

        $posted_days = (int)($_POST['featured_days'] ?? 0);
        $posted_position = (int)($_POST['featured_position'] ?? 0);

        if ($action === 'activate') {
            if ($posted_days <= 0) {
                doctor_status_display_options_redirect_back($doctor_id, 'featured_duration_required');
            }

            $started_at = $today;
            $featured_until = doctor_status_display_options_calculate_featured_until($posted_days, $started_at);

            if (!$featured_until) {
                doctor_status_display_options_redirect_back($doctor_id, 'featured_invalid');
            }

            try {
                $stmt = $pdo->prepare("
                    UPDATE doctors
                    SET
                        is_featured = 1,
                        featured_days = :featured_days,
                        featured_started_at = :featured_started_at,
                        featured_until = :featured_until,
                        featured_position = :featured_position,
                        featured_on_hold = 0,
                        featured_hold_remaining_days = 0,
                        featured_hold_started_at = NULL,
                        updated_at = NOW()
                    WHERE id = :id
                ");

                $stmt->execute([
                    ':featured_days' => $posted_days,
                    ':featured_started_at' => $started_at,
                    ':featured_until' => $featured_until,
                    ':featured_position' => $posted_position,
                    ':id' => $doctor_id,
                ]);

                if (doctor_component_table_exists('doctor_featured_history')) {
                    $stmt = $pdo->prepare("
                        UPDATE doctor_featured_history
                        SET status = 'inactive'
                        WHERE doctor_id = :doctor_id
                        AND status IN ('active', 'hold')
                    ");
                    $stmt->execute([':doctor_id' => $doctor_id]);
                }

                doctor_status_display_options_insert_history(
                    $doctor_id,
                    $posted_days,
                    $started_at,
                    $featured_until,
                    $posted_position,
                    $posted_days,
                    'active'
                );

                doctor_status_display_options_redirect_back($doctor_id, 'featured_saved');
            } catch (Throwable $e) {
                doctor_status_display_options_redirect_back($doctor_id, 'featured_error');
            }
        }

        if ($action === 'hold' || $action === 'stop') {
            $remaining_days = doctor_status_display_options_remaining_days($old_until);

            if ($remaining_days <= 0) {
                $remaining_days = max(0, $old_hold_remaining);
            }

            try {
                if ($remaining_days > 0) {
                    $stmt = $pdo->prepare("
                        UPDATE doctors
                        SET
                            is_featured = 0,
                            featured_on_hold = 1,
                            featured_hold_remaining_days = :remaining_days,
                            featured_hold_started_at = :hold_started_at,
                            updated_at = NOW()
                        WHERE id = :id
                    ");

                    $stmt->execute([
                        ':remaining_days' => $remaining_days,
                        ':hold_started_at' => $today,
                        ':id' => $doctor_id,
                    ]);

                    if (doctor_component_table_exists('doctor_featured_history')) {
                        $stmt = $pdo->prepare("
                            UPDATE doctor_featured_history
                            SET
                                status = 'hold',
                                remaining_days = :remaining_days
                            WHERE doctor_id = :doctor_id
                            AND status IN ('active', 'hold')
                        ");

                        $stmt->execute([
                            ':remaining_days' => $remaining_days,
                            ':doctor_id' => $doctor_id,
                        ]);
                    }

                    doctor_status_display_options_redirect_back($doctor_id, 'featured_hold');
                }

                $stmt = $pdo->prepare("
                    UPDATE doctors
                    SET
                        is_featured = 0,
                        featured_on_hold = 0,
                        featured_hold_remaining_days = 0,
                        featured_hold_started_at = NULL,
                        updated_at = NOW()
                    WHERE id = :id
                ");

                $stmt->execute([':id' => $doctor_id]);

                if (doctor_component_table_exists('doctor_featured_history')) {
                    $stmt = $pdo->prepare("
                        UPDATE doctor_featured_history
                        SET
                            status = 'inactive',
                            remaining_days = 0
                        WHERE doctor_id = :doctor_id
                        AND status IN ('active', 'hold')
                    ");

                    $stmt->execute([':doctor_id' => $doctor_id]);
                }

                doctor_status_display_options_redirect_back($doctor_id, 'featured_stopped');
            } catch (Throwable $e) {
                doctor_status_display_options_redirect_back($doctor_id, 'featured_error');
            }
        }

        if ($action === 'resume') {
            $remaining_days = max(0, $old_hold_remaining);

            if ($remaining_days <= 0) {
                doctor_status_display_options_redirect_back($doctor_id, 'featured_no_remaining');
            }

            $days_for_db = $old_days > 0 ? $old_days : $remaining_days;
            $position_for_db = $posted_position >= 0 ? $posted_position : $old_position;
            $started_at = $today;
            $featured_until = doctor_status_display_options_calculate_featured_until($remaining_days, $started_at);

            if (!$featured_until) {
                doctor_status_display_options_redirect_back($doctor_id, 'featured_invalid');
            }

            try {
                $stmt = $pdo->prepare("
                    UPDATE doctors
                    SET
                        is_featured = 1,
                        featured_days = :featured_days,
                        featured_started_at = :featured_started_at,
                        featured_until = :featured_until,
                        featured_position = :featured_position,
                        featured_on_hold = 0,
                        featured_hold_remaining_days = 0,
                        featured_hold_started_at = NULL,
                        updated_at = NOW()
                    WHERE id = :id
                ");

                $stmt->execute([
                    ':featured_days' => $days_for_db,
                    ':featured_started_at' => $started_at,
                    ':featured_until' => $featured_until,
                    ':featured_position' => $position_for_db,
                    ':id' => $doctor_id,
                ]);

                if (doctor_component_table_exists('doctor_featured_history')) {
                    $stmt = $pdo->prepare("
                        UPDATE doctor_featured_history
                        SET status = 'inactive'
                        WHERE doctor_id = :doctor_id
                        AND status = 'hold'
                    ");
                    $stmt->execute([':doctor_id' => $doctor_id]);
                }

                doctor_status_display_options_insert_history(
                    $doctor_id,
                    $days_for_db,
                    $started_at,
                    $featured_until,
                    $position_for_db,
                    $remaining_days,
                    'active'
                );

                doctor_status_display_options_redirect_back($doctor_id, 'featured_resumed');
            } catch (Throwable $e) {
                doctor_status_display_options_redirect_back($doctor_id, 'featured_error');
            }
        }
    }
}

if (!function_exists('doctor_status_display_options_get_auto_rating_stats')) {
    function doctor_status_display_options_get_auto_rating_stats(int $doctor_id): array
    {
        global $pdo;

        if (
            $doctor_id <= 0 ||
            !doctor_component_table_exists('reviews') ||
            !doctor_component_column_exists('reviews', 'doctor_id') ||
            !doctor_component_column_exists('reviews', 'rating')
        ) {
            return [
                'rating' => 0,
                'reviews_count' => 0,
                'available' => false,
            ];
        }

        $where = 'doctor_id = :doctor_id';

        if (doctor_component_column_exists('reviews', 'status')) {
            $where .= " AND status IN ('approved', 'active', 'published')";
        }

        try {
            $stmt = $pdo->prepare("
                SELECT
                    COUNT(*) AS total_reviews,
                    AVG(rating) AS average_rating
                FROM reviews
                WHERE {$where}
            ");
            $stmt->execute([':doctor_id' => $doctor_id]);

            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
            $total = (int)($row['total_reviews'] ?? 0);
            $average = $total > 0 ? round((float)$row['average_rating'], 1) : 0;

            return [
                'rating' => $average,
                'reviews_count' => $total,
                'available' => $total > 0,
            ];
        } catch (Throwable $e) {
            return [
                'rating' => 0,
                'reviews_count' => 0,
                'available' => false,
            ];
        }
    }
}

if (!defined('DOCTOR_FORM_MAIN_CONTEXT') && !function_exists('doctor_form_get_auto_rating_stats')) {
    function doctor_form_get_auto_rating_stats(int $doctor_id): array
    {
        return doctor_status_display_options_get_auto_rating_stats($doctor_id);
    }
}

if (!function_exists('doctor_status_display_options_css')) {
    function doctor_status_display_options_css(): void
    {
        static $printed = false;

        if ($printed) {
            return;
        }

        $printed = true;
        ?>
        <style>
            :root {
                --gh-card:#fff;
                --gh-text:#24292f;
                --gh-muted:#57606a;
                --gh-border:#d0d7de;
                --gh-blue:#0969da;
                --gh-blue-soft:#ddf4ff;
                --gh-green:#1a7f37;
                --gh-green-soft:#dafbe1;
                --gh-red:#cf222e;
                --gh-red-soft:#ffebe9;
                --gh-orange-soft:#fff8c5;
                --gh-soft:#f6f8fa;
            }

            .gh-status-card,
            .gh-status-card * {
                box-sizing:border-box;
            }

            .gh-status-card {
                width:100%;
                margin-bottom:20px;
                border:1px solid var(--gh-border);
                border-radius:12px;
                background:var(--gh-card);
                overflow:hidden;
                color:var(--gh-text);
                font-family:inherit;
            }

            .gh-status-head {
                display:flex;
                align-items:flex-start;
                justify-content:space-between;
                gap:18px;
                padding:18px 20px;
                background:linear-gradient(180deg,#fff 0%,#f6f8fa 100%);
                border-bottom:1px solid var(--gh-border);
            }

            .gh-status-title {
                display:flex;
                align-items:flex-start;
                gap:12px;
                min-width:0;
            }

            .gh-status-icon {
                width:36px;
                height:36px;
                border-radius:10px;
                display:inline-flex;
                align-items:center;
                justify-content:center;
                color:var(--gh-blue);
                background:var(--gh-blue-soft);
                border:1px solid rgba(84,174,255,.45);
                font-size:13px;
                font-weight:800!important;
                flex:0 0 auto;
            }

            .gh-status-title h3 {
                margin:0;
                color:var(--gh-text);
                font-size:18px;
                line-height:1.35;
                font-weight:700!important;
                letter-spacing:-.02em;
            }

            .gh-status-title p {
                margin:4px 0 0;
                color:var(--gh-muted);
                font-size:13px;
                line-height:1.55;
            }

            .gh-status-top-badge,
            .gh-pill {
                display:inline-flex;
                align-items:center;
                gap:6px;
                padding:6px 9px;
                border-radius:999px;
                border:1px solid var(--gh-border);
                background:var(--gh-soft);
                color:var(--gh-text);
                font-size:11.8px;
                font-weight:700!important;
                line-height:1;
                white-space:nowrap;
            }

            .gh-status-top-badge {
                font-size:12px;
                background:#fff;
                color:var(--gh-muted);
            }

            .gh-status-top-badge-dot {
                width:8px;
                height:8px;
                border-radius:50%;
                background:var(--gh-green);
            }

            .gh-pill.green {
                background:var(--gh-green-soft);
                border-color:#aceebb;
                color:#116329;
            }

            .gh-pill.red {
                background:var(--gh-red-soft);
                border-color:#ff818266;
                color:var(--gh-red);
            }

            .gh-pill.blue {
                background:var(--gh-blue-soft);
                border-color:#b6e3ff;
                color:var(--gh-blue);
            }

            .gh-pill.orange {
                background:var(--gh-orange-soft);
                border-color:#f0d98c;
                color:#7d4e00;
            }

            .gh-status-body {
                padding:20px;
            }

            .gh-status-layout {
                display:grid;
                grid-template-columns:minmax(0,1fr) 340px;
                gap:18px;
                align-items:start;
            }

            .gh-status-main,
            .gh-status-side,
            .gh-status-bottom {
                display:grid;
                gap:16px;
                min-width:0;
            }

            .gh-status-bottom {
                margin-top:18px;
            }

            .gh-panel {
                border:1px solid var(--gh-border);
                border-radius:12px;
                background:#fff;
                overflow:hidden;
                min-width:0;
            }

            .gh-panel-head {
                display:flex;
                align-items:center;
                justify-content:space-between;
                gap:12px;
                padding:13px 15px;
                border-bottom:1px solid var(--gh-border);
                background:var(--gh-soft);
            }

            .gh-panel-head h4 {
                margin:0;
                color:var(--gh-text);
                font-size:14px;
                font-weight:700!important;
                line-height:1.35;
            }

            .gh-panel-body {
                padding:15px;
            }

            .gh-publish-row {
                display:grid;
                grid-template-columns:auto minmax(0,1fr);
                gap:12px;
                align-items:center;
            }

            .gh-live-dot {
                width:12px;
                height:12px;
                border-radius:999px;
                background:var(--gh-red);
                box-shadow:0 0 0 5px rgba(207,34,46,.1);
            }

            .gh-live-dot.is-active {
                background:var(--gh-green);
                box-shadow:0 0 0 5px rgba(26,127,55,.12);
            }

            .gh-select {
                width:100%;
                min-height:42px;
                border:1px solid var(--gh-border);
                border-radius:6px;
                padding:10px 12px;
                background:#fff;
                color:var(--gh-text);
                font-family:inherit;
                font-size:14px;
                outline:none;
            }

            .gh-select:focus {
                border-color:var(--gh-blue);
                box-shadow:0 0 0 3px rgba(9,105,218,.12);
            }

            .gh-help {
                margin:10px 0 0;
                color:var(--gh-muted);
                font-size:12.5px;
                line-height:1.6;
            }

            .gh-option-grid {
                display:grid;
                grid-template-columns:repeat(2,minmax(0,1fr));
                gap:10px;
            }

            .gh-toggle-card {
                position:relative;
                display:grid;
                grid-template-columns:minmax(0,1fr) auto;
                gap:10px;
                align-items:center;
                min-height:58px;
                padding:12px 13px;
                border:1px solid var(--gh-border);
                border-radius:10px;
                background:#fff;
                cursor:pointer;
                transition:.12s ease;
                user-select:none;
                min-width:0;
            }

            .gh-toggle-card:hover {
                background:var(--gh-soft);
                border-color:#8c959f;
            }

            .gh-toggle-card input[type="checkbox"] {
                position:absolute;
                opacity:0;
                pointer-events:none;
            }

            .gh-toggle-title {
                display:block;
                color:var(--gh-text);
                font-size:13.5px;
                font-weight:700!important;
                line-height:1.35;
            }

            .gh-toggle-sub {
                display:block;
                margin-top:3px;
                color:var(--gh-muted);
                font-size:11.8px;
                line-height:1.35;
            }

            .gh-toggle-switch {
                position:relative;
                width:38px;
                height:22px;
                border-radius:999px;
                background:#d8dee4;
                border:1px solid #c9d1d9;
                transition:.12s ease;
                flex:0 0 auto;
            }

            .gh-toggle-switch:after {
                content:"";
                position:absolute;
                top:2px;
                left:2px;
                width:16px;
                height:16px;
                border-radius:50%;
                background:#fff;
                box-shadow:0 1px 2px rgba(27,31,36,.18);
                transition:.12s ease;
            }

            .gh-toggle-card.is-on {
                border-color:#2da44e66;
                background:#f0fff4;
            }

            .gh-toggle-card.is-on .gh-toggle-switch {
                background:#2da44e;
                border-color:#2da44e;
            }

            .gh-toggle-card.is-on .gh-toggle-switch:after {
                left:18px;
            }

            .gh-toggle-card.is-featured-control .gh-toggle-switch {
                display:none;
            }

            .gh-featured-status-stack {
                display:flex;
                align-items:center;
                gap:7px;
                flex-wrap:wrap;
                justify-content:flex-end;
            }

            .gh-note {
                display:flex;
                align-items:flex-start;
                gap:10px;
                padding:12px 14px;
                border:1px solid var(--gh-border);
                border-radius:10px;
                background:var(--gh-soft);
                color:var(--gh-muted);
                font-size:12.5px;
                line-height:1.6;
            }

            .gh-note-icon {
                width:18px;
                height:18px;
                border-radius:50%;
                display:inline-flex;
                align-items:center;
                justify-content:center;
                background:var(--gh-blue-soft);
                color:var(--gh-blue);
                font-size:12px;
                font-weight:800!important;
                flex:0 0 auto;
                margin-top:1px;
            }

            .gh-date-row {
                display:grid;
                grid-template-columns:repeat(2,minmax(0,1fr));
                gap:10px;
            }

            .gh-date-row.three {
                grid-template-columns:repeat(3,minmax(0,1fr));
            }

            .gh-date-box {
                padding:11px 12px;
                border:1px solid var(--gh-border);
                border-radius:9px;
                background:#fff;
                min-width:0;
            }

            .gh-date-box strong {
                display:block;
                color:var(--gh-text);
                font-size:13px;
                line-height:1.4;
                margin-bottom:4px;
            }

            .gh-date-box span {
                color:var(--gh-blue);
                font-size:12px;
                font-weight:700!important;
                word-break:break-word;
            }

            .gh-rating-box {
                display:grid;
                gap:12px;
            }

            .gh-rating-hero {
                padding:15px;
                border:1px solid var(--gh-border);
                border-radius:10px;
                background:var(--gh-soft);
            }

            .gh-rating-line {
                display:flex;
                align-items:flex-end;
                justify-content:space-between;
                gap:12px;
            }

            .gh-rating-number {
                color:var(--gh-text);
                font-size:34px;
                line-height:.95;
                font-weight:800!important;
                letter-spacing:-.04em;
            }

            .gh-rating-stars {
                color:#bf8700;
                font-size:18px;
                letter-spacing:1px;
                white-space:nowrap;
            }

            .gh-rating-label {
                color:var(--gh-muted);
                font-size:12px;
                line-height:1.45;
            }

            .gh-mini-stats {
                display:grid;
                grid-template-columns:repeat(2,minmax(0,1fr));
                gap:10px;
            }

            .gh-mini-stat {
                padding:11px 12px;
                border:1px solid var(--gh-border);
                border-radius:10px;
                background:#fff;
            }

            .gh-mini-stat strong {
                display:block;
                color:var(--gh-text);
                font-size:18px;
                line-height:1.2;
                font-weight:800!important;
            }

            .gh-mini-stat span {
                display:block;
                margin-top:4px;
                color:var(--gh-muted);
                font-size:11.8px;
                line-height:1.4;
            }

            .gh-preview-list {
                display:flex;
                flex-wrap:wrap;
                gap:8px;
            }

            .gh-history {
                border:1px solid var(--gh-border);
                border-radius:10px;
                background:#fff;
                overflow:hidden;
            }

            .gh-history-title {
                padding:10px 12px;
                border-bottom:1px solid var(--gh-border);
                background:var(--gh-soft);
                color:var(--gh-text);
                font-size:13px;
                font-weight:800!important;
            }

            .gh-history-item {
                display:grid;
                grid-template-columns:minmax(0,1fr) auto;
                gap:10px;
                align-items:center;
                padding:10px 12px;
                border-bottom:1px solid #d8dee4;
            }

            .gh-history-item:last-child {
                border-bottom:0;
            }

            .gh-history-item strong {
                display:block;
                color:var(--gh-text);
                font-size:12.8px;
                line-height:1.4;
            }

            .gh-history-item small {
                display:block;
                margin-top:3px;
                color:var(--gh-muted);
                font-size:11.8px;
                line-height:1.45;
            }

            .gh-modal-backdrop {
                position:fixed;
                inset:0;
                z-index:9999;
                display:none;
                align-items:center;
                justify-content:center;
                padding:18px;
                background:rgba(27,31,36,.45);
            }

            .gh-modal-backdrop.is-open {
                display:flex;
            }

            .gh-modal {
                width:min(560px,100%);
                border:1px solid var(--gh-border);
                border-radius:12px;
                background:#fff;
                box-shadow:0 16px 48px rgba(27,31,36,.22);
                overflow:hidden;
            }

            .gh-modal-head {
                display:flex;
                align-items:flex-start;
                justify-content:space-between;
                gap:14px;
                padding:16px 18px;
                border-bottom:1px solid var(--gh-border);
                background:var(--gh-soft);
            }

            .gh-modal-head h4 {
                margin:0;
                color:var(--gh-text);
                font-size:16px;
                font-weight:800!important;
            }

            .gh-modal-head p {
                margin:4px 0 0;
                color:var(--gh-muted);
                font-size:12.5px;
                line-height:1.5;
            }

            .gh-modal-close {
                width:32px;
                height:32px;
                border:1px solid var(--gh-border);
                border-radius:6px;
                background:#fff;
                color:var(--gh-muted);
                cursor:pointer;
                font-size:18px;
                line-height:1;
            }

            .gh-modal-body {
                padding:18px;
                display:grid;
                gap:14px;
            }

            .gh-modal-grid {
                display:grid;
                grid-template-columns:repeat(2,minmax(0,1fr));
                gap:12px;
            }

            .gh-field {
                display:grid;
                gap:7px;
                min-width:0;
            }

            .gh-field label {
                color:#344054;
                font-size:13px;
                font-weight:700!important;
                line-height:1.35;
            }

            .gh-modal-actions {
                display:grid;
                grid-template-columns:repeat(2,minmax(0,1fr));
                gap:10px;
                padding-top:4px;
            }

            .gh-btn {
                min-height:38px;
                padding:8px 13px;
                border:1px solid rgba(27,31,36,.15);
                border-radius:6px;
                cursor:pointer;
                font-family:inherit;
                font-size:13px;
                font-weight:700!important;
            }

            .gh-btn-light {
                background:var(--gh-soft);
                color:var(--gh-text);
            }

            .gh-btn-green {
                background:#2da44e;
                color:#fff;
            }

            .gh-btn-orange {
                background:var(--gh-orange-soft);
                color:#7d4e00;
                border-color:#f0d98c;
            }

            @media(max-width:1100px) {
                .gh-status-layout {
                    grid-template-columns:1fr;
                }

                .gh-status-side {
                    grid-template-columns:repeat(2,minmax(0,1fr));
                }
            }

            @media(max-width:760px) {
                .gh-status-head {
                    flex-direction:column;
                    align-items:stretch;
                }

                .gh-status-top-badge {
                    width:fit-content;
                }

                .gh-status-side,
                .gh-option-grid,
                .gh-date-row,
                .gh-date-row.three,
                .gh-mini-stats,
                .gh-modal-grid,
                .gh-modal-actions {
                    grid-template-columns:1fr;
                }

                .gh-history-item {
                    grid-template-columns:1fr;
                }

                .gh-btn {
                    width:100%;
                }

                .gh-featured-status-stack {
                    justify-content:flex-start;
                }
            }
        </style>
        <?php
    }
}

if (!function_exists('doctor_status_display_options_js')) {
    function doctor_status_display_options_js(): void
    {
        static $printed = false;

        if ($printed) {
            return;
        }

        $printed = true;
        ?>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const statusSelect = document.querySelector('[data-doctor-status-select]');
                const statusDot = document.querySelector('[data-doctor-status-dot]');
                const statusPreview = document.querySelector('[data-doctor-status-preview]');

                const optionCards = document.querySelectorAll('[data-option-card]');
                const featuredModal = document.querySelector('[data-featured-modal]');
                const featuredModalClose = document.querySelectorAll('[data-featured-modal-close]');
                const featuredActionInput = document.querySelector('[data-featured-action-input]');
                const featuredDaysSelect = document.querySelector('[data-featured-days]');
                const featuredModalEndDate = document.querySelector('[data-featured-modal-end-date]');

                function formatDate(date) {
                    const year = date.getFullYear();
                    const month = String(date.getMonth() + 1).padStart(2, '0');
                    const day = String(date.getDate()).padStart(2, '0');

                    return `${year}-${month}-${day}`;
                }

                function updateToggleCard(checkbox) {
                    if (!checkbox) return;

                    const card = checkbox.closest('[data-option-card]');

                    if (card) {
                        card.classList.toggle('is-on', checkbox.checked);
                    }
                }

                function openFeaturedModal() {
                    if (!featuredModal) return;

                    featuredModal.classList.add('is-open');
                    featuredModal.setAttribute('aria-hidden', 'false');
                    updateFeaturedEndDatePreview();
                }

                function closeFeaturedModal() {
                    if (!featuredModal) return;

                    featuredModal.classList.remove('is-open');
                    featuredModal.setAttribute('aria-hidden', 'true');
                }

                function updateStatusPreview() {
                    if (!statusSelect || !statusDot || !statusPreview) return;

                    const isActive = statusSelect.value === 'active';

                    statusDot.classList.toggle('is-active', isActive);
                    statusPreview.textContent = isActive ? '✓ Published' : 'Hidden';
                    statusPreview.classList.toggle('green', isActive);
                    statusPreview.classList.toggle('red', !isActive);
                }

                function updateFeaturedEndDatePreview() {
                    if (!featuredModalEndDate || !featuredDaysSelect) return;

                    const days = parseInt(featuredDaysSelect.value || '0', 10);

                    if (!days || days <= 0) {
                        featuredModalEndDate.textContent = 'Not set yet';
                        return;
                    }

                    const endDate = new Date();
                    endDate.setDate(endDate.getDate() + days);
                    featuredModalEndDate.textContent = formatDate(endDate);
                }

                function submitClosestForm(element) {
                    const form = element ? element.closest('form') : document.querySelector('form');

                    if (!form) return;

                    if (typeof form.requestSubmit === 'function') {
                        form.requestSubmit();
                    } else {
                        form.submit();
                    }
                }

                optionCards.forEach(function(card) {
                    const checkbox = card.querySelector('input[type="checkbox"]');

                    if (checkbox) {
                        updateToggleCard(checkbox);
                    }

                    card.addEventListener('click', function(event) {
                        event.preventDefault();

                        if (card.hasAttribute('data-featured-card')) {
                            openFeaturedModal();
                            return;
                        }

                        if (!checkbox) return;

                        if (checkbox.checked) {
                            const ok = window.confirm('Are you sure you want to turn this option off?');

                            if (!ok) {
                                checkbox.checked = true;
                                updateToggleCard(checkbox);
                                return;
                            }

                            checkbox.checked = false;
                            updateToggleCard(checkbox);
                            return;
                        }

                        checkbox.checked = true;
                        updateToggleCard(checkbox);
                    });
                });

                featuredModalClose.forEach(function(button) {
                    button.addEventListener('click', closeFeaturedModal);
                });

                if (featuredModal) {
                    featuredModal.addEventListener('click', function(event) {
                        if (event.target === featuredModal) {
                            closeFeaturedModal();
                        }
                    });
                }

                document.querySelectorAll('[data-featured-action]').forEach(function(button) {
                    button.addEventListener('click', function() {
                        const action = button.getAttribute('data-featured-action');

                        if (action === 'activate') {
                            const days = featuredDaysSelect ? parseInt(featuredDaysSelect.value || '0', 10) : 0;

                            if (!days || days <= 0) {
                                alert('Please select Featured Active Duration.');
                                return;
                            }
                        }

                        if (action === 'hold') {
                            if (!window.confirm('If remaining days exist, Featured will be held. If remaining is 0, it will be marked inactive.')) {
                                return;
                            }
                        }

                        if (action === 'resume') {
                            if (!window.confirm('Resume Featured Profile from remaining days?')) {
                                return;
                            }
                        }

                        if (featuredActionInput) {
                            featuredActionInput.value = action;
                        }

                        closeFeaturedModal();
                        submitClosestForm(button);
                    });
                });

                if (featuredDaysSelect) {
                    featuredDaysSelect.addEventListener('change', updateFeaturedEndDatePreview);
                    updateFeaturedEndDatePreview();
                }

                if (statusSelect) {
                    statusSelect.addEventListener('change', updateStatusPreview);
                    updateStatusPreview();
                }
            });
        </script>
        <?php
    }
}

if (!function_exists('doctor_status_display_options_render')) {
    function doctor_status_display_options_render(array $context = []): void
    {
        extract($context, EXTR_SKIP);

        $doctor = is_array($doctor ?? null) ? $doctor : [];
        $id = (int)($id ?? ($doctor['id'] ?? 0));

        doctor_status_display_options_handle_featured_popup_post($id, $doctor);
        $doctor = doctor_status_display_options_auto_expire_featured($id, $doctor);

        $doctor_auto_rating_stats = is_array($doctor_auto_rating_stats ?? null)
            ? $doctor_auto_rating_stats
            : ['rating' => 0, 'reviews_count' => 0, 'available' => false];

        $status = (string)($doctor['status'] ?? 'active');
        $is_active = $status === 'active';

        $auto_rating = (float)($doctor_auto_rating_stats['rating'] ?? 0);
        $auto_reviews = (int)($doctor_auto_rating_stats['reviews_count'] ?? 0);
        $rating_available = !empty($doctor_auto_rating_stats['available']);

        $is_featured = !empty($doctor['is_featured']);
        $featured_on_hold = !empty($doctor['featured_on_hold']);
        $featured_hold_remaining_days = (int)($doctor['featured_hold_remaining_days'] ?? 0);

        $featured_days = (int)($doctor['featured_days'] ?? 0);
        $featured_started_at = trim((string)($doctor['featured_started_at'] ?? ''));
        $featured_until = trim((string)($doctor['featured_until'] ?? ''));
        $featured_position = (int)($doctor['featured_position'] ?? 0);

        $featured_day_options = [
            180 => '6 Months',
            365 => '1 Year',
            730 => '2 Years',
            1095 => '3 Years',
            1825 => '5 Years',
        ];

        $featured_remaining_days = $featured_on_hold
            ? $featured_hold_remaining_days
            : doctor_status_display_options_remaining_days($featured_until);

        $featured_duration_label = $featured_day_options[$featured_days] ?? 'Not selected';
        $featured_position_label = $featured_position > 0 ? 'Position ' . $featured_position : 'Auto Position';

        if ($is_featured) {
            $featured_state_label = 'Active';
            $featured_state_class = 'green';
        } elseif ($featured_on_hold) {
            $featured_state_label = 'On Hold';
            $featured_state_class = 'blue';
        } else {
            $featured_state_label = 'Inactive';
            $featured_state_class = 'red';
        }

        $featured_history = doctor_status_display_options_get_featured_history($id, 8);

        doctor_status_display_options_css();
        ?>

        <div class="gh-status-card">
            <input type="hidden" name="featured_popup_action" value="" data-featured-action-input>

            <div class="gh-status-head">
                <div class="gh-status-title">
                    <div class="gh-status-icon">STA</div>
                    <div>
                        <h3>Status Display Options</h3>
                        <p>Control publishing status, display badges, featured hold/resume and public profile preview.</p>
                    </div>
                </div>

                <div class="gh-status-top-badge">
                    <span class="gh-status-top-badge-dot"></span>
                    <span><?= $is_active ? 'Profile published' : 'Profile hidden' ?></span>
                </div>
            </div>

            <div class="gh-status-body">
                <div class="gh-status-layout">
                    <div class="gh-status-main">
                        <div class="gh-panel">
                            <div class="gh-panel-head">
                                <h4>Publishing Status</h4>
                                <span class="gh-pill <?= $is_active ? 'green' : 'red' ?>" data-doctor-status-preview>
                                    <?= $is_active ? '✓ Published' : 'Hidden' ?>
                                </span>
                            </div>

                            <div class="gh-panel-body">
                                <div class="gh-publish-row">
                                    <span class="gh-live-dot <?= $is_active ? 'is-active' : '' ?>" data-doctor-status-dot></span>

                                    <select name="status" class="gh-select" data-doctor-status-select>
                                        <option value="active" <?= $is_active ? 'selected' : '' ?>>Active / Published</option>
                                        <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inactive / Hidden</option>
                                    </select>
                                </div>

                                <p class="gh-help">Active profiles show publicly. Inactive profiles stay saved but hidden from public listing.</p>
                            </div>
                        </div>

                        <div class="gh-panel">
                            <div class="gh-panel-head">
                                <h4>Display Options</h4>
                            </div>

                            <div class="gh-panel-body">
                                <div class="gh-option-grid">
                                    <label class="gh-toggle-card" data-option-card>
                                        <input type="checkbox" name="is_verified" <?= !empty($doctor['is_verified']) ? 'checked' : '' ?>>
                                        <span>
                                            <span class="gh-toggle-title">Verified Doctor</span>
                                            <span class="gh-toggle-sub">Show verified badge on profile</span>
                                        </span>
                                        <span class="gh-toggle-switch"></span>
                                    </label>

                                    <label class="gh-toggle-card is-featured-control <?= ($is_featured || $featured_on_hold) ? 'is-on' : '' ?>" data-option-card data-featured-card>
                                        <input type="checkbox" name="is_featured" <?= $is_featured ? 'checked' : '' ?>>
                                        <span>
                                            <span class="gh-toggle-title">Featured Profile</span>
                                            <span class="gh-toggle-sub">Click to open featured control popup</span>
                                        </span>

                                        <span class="gh-featured-status-stack">
                                            <span class="gh-pill <?= e($featured_state_class) ?>"><?= e($featured_state_label) ?></span>
                                        </span>
                                    </label>

                                    <label class="gh-toggle-card" data-option-card>
                                        <input type="checkbox" name="online_consultation" <?= !empty($doctor['online_consultation']) ? 'checked' : '' ?>>
                                        <span>
                                            <span class="gh-toggle-title">Online Consultation</span>
                                            <span class="gh-toggle-sub">Show online consultation option</span>
                                        </span>
                                        <span class="gh-toggle-switch"></span>
                                    </label>

                                    <label class="gh-toggle-card" data-option-card>
                                        <input type="checkbox" name="emergency_available" <?= !empty($doctor['emergency_available']) ? 'checked' : '' ?>>
                                        <span>
                                            <span class="gh-toggle-title">Emergency Available</span>
                                            <span class="gh-toggle-sub">Highlight emergency availability</span>
                                        </span>
                                        <span class="gh-toggle-switch"></span>
                                    </label>

                                    <label class="gh-toggle-card" data-option-card>
                                        <input type="checkbox" name="home_visit" <?= !empty($doctor['home_visit']) ? 'checked' : '' ?>>
                                        <span>
                                            <span class="gh-toggle-title">Home Visit</span>
                                            <span class="gh-toggle-sub">Show home visit badge</span>
                                        </span>
                                        <span class="gh-toggle-switch"></span>
                                    </label>
                                </div>

                                <div class="gh-note" style="margin-top:14px;">
                                    <span class="gh-note-icon">i</span>
                                    <span>Checked options need confirmation before turning off. Featured Profile is controlled from popup only.</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="gh-status-side">
                        <div class="gh-panel">
                            <div class="gh-panel-head">
                                <h4>Review Rating</h4>
                                <span class="gh-pill <?= $rating_available ? 'green' : 'red' ?>">
                                    <?= $rating_available ? 'Live' : 'Empty' ?>
                                </span>
                            </div>

                            <div class="gh-panel-body">
                                <div class="gh-rating-box">
                                    <div class="gh-rating-hero">
                                        <div class="gh-rating-line">
                                            <div>
                                                <div class="gh-rating-number"><?= e(number_format($auto_rating, 1)) ?></div>
                                                <div class="gh-rating-label">Average rating out of 5</div>
                                            </div>

                                            <div class="gh-rating-stars" aria-label="Rating stars">★★★★★</div>
                                        </div>
                                    </div>

                                    <div class="gh-mini-stats">
                                        <div class="gh-mini-stat">
                                            <strong><?= e((string)$auto_reviews) ?></strong>
                                            <span>Total approved reviews</span>
                                        </div>

                                        <div class="gh-mini-stat">
                                            <strong><?= $rating_available ? '✓ Live' : 'Empty' ?></strong>
                                            <span>Review data status</span>
                                        </div>
                                    </div>

                                    <div class="gh-note">
                                        <span class="gh-note-icon">i</span>
                                        <span>Rating and reviews count are calculated automatically from approved doctor reviews.</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="gh-panel">
                            <div class="gh-panel-head">
                                <h4>Public Preview</h4>
                            </div>

                            <div class="gh-panel-body">
                                <div class="gh-preview-list">
                                    <span class="gh-pill <?= $is_active ? 'green' : 'red' ?>">
                                        <?= $is_active ? '✓ Published' : 'Hidden' ?>
                                    </span>

                                    <?php if (!empty($doctor['is_verified'])): ?>
                                        <span class="gh-pill green">✓ Verified</span>
                                    <?php endif; ?>

                                    <?php if ($is_featured): ?>
                                        <span class="gh-pill green">✓ Featured</span>
                                        <span class="gh-pill orange"><?= e($featured_duration_label) ?></span>
                                        <span class="gh-pill orange"><?= e($featured_position_label) ?></span>

                                        <?php if ($featured_until !== ''): ?>
                                            <span class="gh-pill orange">Ends <?= e($featured_until) ?></span>
                                            <span class="gh-pill orange"><?= e((string)$featured_remaining_days) ?> days left</span>
                                        <?php endif; ?>
                                    <?php endif; ?>

                                    <?php if ($featured_on_hold): ?>
                                        <span class="gh-pill blue">Featured On Hold</span>
                                        <span class="gh-pill blue"><?= e((string)$featured_remaining_days) ?> days saved</span>
                                    <?php endif; ?>

                                    <?php if (!empty($doctor['online_consultation'])): ?>
                                        <span class="gh-pill blue">✓ Online</span>
                                    <?php endif; ?>

                                    <?php if (!empty($doctor['emergency_available'])): ?>
                                        <span class="gh-pill red">✓ Emergency</span>
                                    <?php endif; ?>

                                    <?php if (!empty($doctor['home_visit'])): ?>
                                        <span class="gh-pill blue">✓ Home Visit</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="gh-status-bottom">
                    <div class="gh-panel">
                        <div class="gh-panel-head">
                            <h4>Featured Summary</h4>
                            <span class="gh-pill <?= e($featured_state_class) ?>"><?= e($featured_state_label) ?></span>
                        </div>

                        <div class="gh-panel-body">
                            <div class="gh-date-row">
                                <div class="gh-date-box">
                                    <strong>Duration</strong>
                                    <span><?= e($featured_duration_label) ?></span>
                                </div>

                                <div class="gh-date-box">
                                    <strong>Position</strong>
                                    <span><?= e($featured_position_label) ?></span>
                                </div>
                            </div>

                            <div class="gh-date-row three" style="margin-top:10px;">
                                <div class="gh-date-box">
                                    <strong>Started</strong>
                                    <span><?= $featured_started_at !== '' ? e($featured_started_at) : 'Not started yet' ?></span>
                                </div>

                                <div class="gh-date-box">
                                    <strong>End Date</strong>
                                    <span><?= $featured_until !== '' ? e($featured_until) : 'Not set yet' ?></span>
                                </div>

                                <div class="gh-date-box">
                                    <strong>Remaining</strong>
                                    <span><?= ($is_featured || $featured_on_hold) ? e((string)$featured_remaining_days) . ' days' : 'Not active' ?></span>
                                </div>
                            </div>

                            <p class="gh-help">
                                <?php if ($is_featured): ?>
                                    If you use Hold / Off while remaining days exist, it will be saved as hold.
                                <?php elseif ($featured_on_hold): ?>
                                    Featured is on hold. Resume will start again from <?= e((string)$featured_remaining_days) ?> remaining days.
                                <?php else: ?>
                                    Featured controls are available only from the popup.
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>

                    <?php if (!empty($featured_history)): ?>
                        <div class="gh-panel">
                            <div class="gh-panel-head">
                                <h4>Featured History</h4>
                            </div>

                            <div class="gh-panel-body">
                                <div class="gh-history">
                                    <div class="gh-history-title">Recent Featured Records</div>

                                    <?php foreach ($featured_history as $history): ?>
                                        <?php
                                            $history_status = (string)($history['status'] ?? 'inactive');
                                            $history_class = $history_status === 'active'
                                                ? 'green'
                                                : ($history_status === 'hold' ? 'blue' : 'red');
                                        ?>

                                        <div class="gh-history-item">
                                            <div>
                                                <strong>
                                                    <?= e((string)($history['featured_started_at'] ?? '')) ?>
                                                    →
                                                    <?= e((string)($history['featured_until'] ?? '')) ?>
                                                </strong>

                                                <small>
                                                    <?= e((string)($history['featured_days'] ?? 0)) ?> days,
                                                    <?= ((int)($history['featured_position'] ?? 0)) > 0
                                                        ? 'Position ' . e((string)$history['featured_position'])
                                                        : 'Auto Position' ?>,
                                                    Remaining: <?= e((string)($history['remaining_days'] ?? 0)) ?> days
                                                </small>
                                            </div>

                                            <span class="gh-pill <?= e($history_class) ?>">
                                                <?= e($history_status) ?>
                                            </span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="gh-modal-backdrop" data-featured-modal aria-hidden="true">
            <div class="gh-modal" role="dialog" aria-modal="true">
                <div class="gh-modal-head">
                    <div>
                        <h4>Featured Profile Control</h4>
                        <p>Activate, hold, resume, or mark inactive based on remaining days.</p>
                    </div>

                    <button type="button" class="gh-modal-close" data-featured-modal-close>×</button>
                </div>

                <div class="gh-modal-body">
                    <div class="gh-modal-grid">
                        <div class="gh-field">
                            <label>Active Duration</label>
                            <select name="featured_days" class="gh-select" data-featured-days>
                                <option value="0" <?= $featured_days <= 0 ? 'selected' : '' ?>>Select Active Duration</option>

                                <?php foreach ($featured_day_options as $days => $label): ?>
                                    <option value="<?= e((string)$days) ?>" <?= $featured_days === (int)$days ? 'selected' : '' ?>>
                                        <?= e($label) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="gh-field">
                            <label>Position</label>
                            <select name="featured_position" class="gh-select">
                                <option value="0" <?= $featured_position <= 0 ? 'selected' : '' ?>>Auto Position</option>

                                <?php for ($position = 1; $position <= 5; $position++): ?>
                                    <option value="<?= e((string)$position) ?>" <?= $featured_position === $position ? 'selected' : '' ?>>
                                        Position <?= e((string)$position) ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>

                    <div class="gh-date-row">
                        <div class="gh-date-box">
                            <strong>New End Date Preview</strong>
                            <span data-featured-modal-end-date><?= $featured_until !== '' ? e($featured_until) : 'Not set yet' ?></span>
                        </div>

                        <div class="gh-date-box">
                            <strong>Saved Remaining</strong>
                            <span><?= e((string)$featured_remaining_days) ?> days</span>
                        </div>
                    </div>

                    <div class="gh-note">
                        <span class="gh-note-icon">i</span>
                        <span>
                            Hold / Off Featured will hold the profile if remaining days exist. If remaining is 0, it will be marked inactive.
                        </span>
                    </div>

                    <div class="gh-modal-actions">
                        <button type="button" class="gh-btn gh-btn-green" data-featured-action="activate">
                            Apply Featured Options
                        </button>

                        <?php if ($is_featured || $featured_on_hold): ?>
                            <button type="button" class="gh-btn gh-btn-orange" data-featured-action="hold">
                                Hold / Off Featured
                            </button>
                        <?php endif; ?>

                        <?php if ($featured_on_hold && $featured_remaining_days > 0): ?>
                            <button type="button" class="gh-btn gh-btn-green" data-featured-action="resume">
                                Resume From Remaining Days
                            </button>
                        <?php endif; ?>

                        <button type="button" class="gh-btn gh-btn-light" data-featured-modal-close>
                            Cancel
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <?php
        doctor_status_display_options_js();
    }
}

doctor_status_display_options_boot();


if (!function_exists('doctor_status_render')) {
    function doctor_status_render(array $context = []): void
    {
        doctor_status_display_options_render($context);
    }
}

if (!function_exists('doctor_status_display_options_section_render')) {
    function doctor_status_display_options_section_render(array $context = []): void
    {
        doctor_status_display_options_render($context);
    }
}

if (!empty($GLOBALS['doctor_form_auto_render_sections'])) {
    doctor_status_display_options_render($GLOBALS['doctor_form_context'] ?? []);
}