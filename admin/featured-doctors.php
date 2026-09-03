<?php
require_once __DIR__ . '/../includes/functions.php';

require_admin();

/*
|--------------------------------------------------------------------------
| Featured Doctors List Only With Filters
|--------------------------------------------------------------------------
| File: admin/featured-doctors.php
|
| This page only displays context-based featured doctor records.
| Add goes to featured-doctors-form.php Step 1.
| Edit goes to featured-doctors-context.php Step 2.
| Filters:
| - Keyword
| - Division
| - District
| - Thana / Area
| - Specialty
| - Featured Status
|--------------------------------------------------------------------------
*/

if (!function_exists('fd_list_table_exists')) {
    function fd_list_table_exists(string $table): bool
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

if (!function_exists('fd_list_column_exists')) {
    function fd_list_column_exists(string $table, string $column): bool
    {
        global $pdo;

        try {
            if (!fd_list_table_exists($table)) {
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

if (!function_exists('fd_list_add_column_if_missing')) {
    function fd_list_add_column_if_missing(string $table, string $column, string $definition): void
    {
        global $pdo;

        if (!fd_list_table_exists($table)) {
            return;
        }

        if (!fd_list_column_exists($table, $column)) {
            try {
                $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
            } catch (Throwable $e) {
                error_log('Featured doctors list column add failed: ' . $table . '.' . $column . ' - ' . $e->getMessage());
            }
        }
    }
}

if (!function_exists('fd_list_name_column')) {
    function fd_list_name_column(string $table): string
    {
        if (fd_list_column_exists($table, 'name_en')) {
            return 'name_en';
        }

        if (fd_list_column_exists($table, 'name')) {
            return 'name';
        }

        if (fd_list_column_exists($table, 'title')) {
            return 'title';
        }

        return 'id';
    }
}

if (!function_exists('fd_list_slug_expr')) {
    function fd_list_slug_expr(string $alias, string $table, string $name_column): string
    {
        if (fd_list_column_exists($table, 'slug')) {
            return "{$alias}.`slug`";
        }

        return "LOWER(REPLACE(REPLACE(TRIM({$alias}.`{$name_column}`), ' ', '-'), '/', '-'))";
    }
}

if (!function_exists('fd_list_boot')) {
    function fd_list_boot(): void
    {
        global $pdo;

        if (!fd_list_table_exists('doctor_featured_contexts')) {
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
            fd_list_add_column_if_missing('doctor_featured_contexts', $column, $definition);
        }
    }
}

if (!function_exists('fd_list_get_items')) {
    function fd_list_get_items(string $table): array
    {
        global $pdo;

        if (!fd_list_table_exists($table) || !fd_list_column_exists($table, 'id')) {
            return [];
        }

        $name_column = fd_list_name_column($table);

        try {
            $where = '';

            if (fd_list_column_exists($table, 'status')) {
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

if (!function_exists('fd_list_get_divisions')) {
    function fd_list_get_divisions(): array
    {
        return fd_list_get_items('divisions');
    }
}

if (!function_exists('fd_list_get_districts')) {
    function fd_list_get_districts(): array
    {
        global $pdo;

        if (!fd_list_table_exists('districts')) {
            return [];
        }

        $name_column = fd_list_name_column('districts');
        $select = "id, `{$name_column}` AS name";
        $select .= fd_list_column_exists('districts', 'division_id') ? ', division_id' : ', 0 AS division_id';

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

if (!function_exists('fd_list_get_thanas')) {
    function fd_list_get_thanas(): array
    {
        global $pdo;

        if (!fd_list_table_exists('thanas')) {
            return [];
        }

        $name_column = fd_list_name_column('thanas');
        $select = "id, `{$name_column}` AS name";
        $select .= fd_list_column_exists('thanas', 'district_id') ? ', district_id' : ', 0 AS district_id';

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

if (!function_exists('fd_list_get_specialties')) {
    function fd_list_get_specialties(): array
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
                // Continue with fallback.
            }
        }

        return fd_list_get_items('specialties');
    }
}

if (!function_exists('fd_list_remaining_days')) {
    function fd_list_remaining_days(?string $featured_until): int
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

if (!function_exists('fd_list_auto_expire')) {
    function fd_list_auto_expire(): void
    {
        global $pdo;

        if (!fd_list_table_exists('doctor_featured_contexts')) {
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
        } catch (Throwable $e) {
            // Do not block list page.
        }
    }
}

if (!function_exists('fd_list_count_records')) {
    function fd_list_count_records(string $status = ''): int
    {
        global $pdo;

        if (!fd_list_table_exists('doctor_featured_contexts')) {
            return 0;
        }

        try {
            if ($status !== '') {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM doctor_featured_contexts WHERE status = :status");
                $stmt->execute([':status' => $status]);
            } else {
                $stmt = $pdo->query("SELECT COUNT(*) FROM doctor_featured_contexts");
            }

            return (int)$stmt->fetchColumn();
        } catch (Throwable $e) {
            return 0;
        }
    }
}

if (!function_exists('fd_list_build_join_parts')) {
    function fd_list_build_join_parts(): array
    {
        $doctor_select = "'' AS doctor_name, '' AS doctor_slug";
        $doctor_join = '';

        if (fd_list_table_exists('doctors')) {
            $doctor_name_col = fd_list_column_exists('doctors', 'name') ? 'name' : fd_list_name_column('doctors');
            $doctor_slug_col = fd_list_column_exists('doctors', 'slug') ? 'slug' : "''";
            $doctor_select = "d.`{$doctor_name_col}` AS doctor_name, " . ($doctor_slug_col === "''" ? "'' AS doctor_slug" : "d.`{$doctor_slug_col}` AS doctor_slug");
            $doctor_join = 'LEFT JOIN doctors d ON d.id = fc.doctor_id';
        }

        $division_name_select = "'' AS division_name";
        $division_join = '';

        if (fd_list_table_exists('divisions') && fd_list_table_exists('districts') && fd_list_column_exists('districts', 'division_id')) {
            $division_name_col = fd_list_name_column('divisions');
            $division_name_select = "dv.`{$division_name_col}` AS division_name";
            $division_join = 'LEFT JOIN divisions dv ON dv.id = dis.division_id';
        }

        $district_name_select = "'' AS district_name, '' AS district_slug";
        $district_join = '';

        if (fd_list_table_exists('districts')) {
            $district_name_col = fd_list_name_column('districts');
            $district_slug_expr = fd_list_slug_expr('dis', 'districts', $district_name_col);
            $district_name_select = "dis.`{$district_name_col}` AS district_name, {$district_slug_expr} AS district_slug";
            $district_join = 'LEFT JOIN districts dis ON dis.id = fc.district_id';
        }

        $thana_name_select = "'' AS thana_name, '' AS thana_slug";
        $thana_join = '';

        if (fd_list_table_exists('thanas')) {
            $thana_name_col = fd_list_name_column('thanas');
            $thana_slug_expr = fd_list_slug_expr('th', 'thanas', $thana_name_col);
            $thana_name_select = "th.`{$thana_name_col}` AS thana_name, {$thana_slug_expr} AS thana_slug";
            $thana_join = 'LEFT JOIN thanas th ON th.id = fc.thana_id';
        }

        $specialty_select = "'' AS specialty_name, '' AS specialty_slug";
        $specialty_join = '';

        if (fd_list_table_exists('specialties')) {
            $specialty_name_col = fd_list_name_column('specialties');
            $specialty_slug_expr = fd_list_slug_expr('sp', 'specialties', $specialty_name_col);
            $specialty_select = "sp.`{$specialty_name_col}` AS specialty_name, {$specialty_slug_expr} AS specialty_slug";
            $specialty_join = 'LEFT JOIN specialties sp ON sp.id = fc.specialty_id';
        }

        return [
            'selects' => [
                $doctor_select,
                $division_name_select,
                $district_name_select,
                $thana_name_select,
                $specialty_select,
            ],
            'joins' => [
                $doctor_join,
                $district_join,
                $division_join,
                $thana_join,
                $specialty_join,
            ],
        ];
    }
}

if (!function_exists('fd_list_get_records')) {
    function fd_list_get_records(array $filters): array
    {
        global $pdo;

        if (!fd_list_table_exists('doctor_featured_contexts')) {
            return [];
        }

        $status_filter = (string)($filters['status'] ?? 'active');
        $search = trim((string)($filters['search'] ?? ''));
        $division_id = max(0, (int)($filters['division_id'] ?? 0));
        $district_id = max(0, (int)($filters['district_id'] ?? 0));
        $thana_id = max(0, (int)($filters['thana_id'] ?? 0));
        $specialty_id = max(0, (int)($filters['specialty_id'] ?? 0));

        $where = [];
        $params = [];

        if ($status_filter !== 'all') {
            $where[] = 'fc.status = :status';
            $params[':status'] = $status_filter;
        }

        if ($division_id > 0 && fd_list_table_exists('districts') && fd_list_column_exists('districts', 'division_id')) {
            $where[] = 'dis.division_id = :division_id';
            $params[':division_id'] = $division_id;
        }

        if ($district_id > 0) {
            $where[] = 'fc.district_id = :district_id';
            $params[':district_id'] = $district_id;
        }

        if ($thana_id > 0) {
            $where[] = 'fc.thana_id = :thana_id';
            $params[':thana_id'] = $thana_id;
        }

        if ($specialty_id > 0) {
            $where[] = 'fc.specialty_id = :specialty_id';
            $params[':specialty_id'] = $specialty_id;
        }

        if ($search !== '') {
            $search_parts = [];

            if (fd_list_table_exists('doctors') && fd_list_column_exists('doctors', 'name')) {
                $search_parts[] = 'd.name LIKE :search';
            }

            if (fd_list_table_exists('specialties') && fd_list_column_exists('specialties', 'name')) {
                $search_parts[] = 'sp.name LIKE :search';
            }

            if (fd_list_table_exists('districts')) {
                if (fd_list_column_exists('districts', 'name_en')) {
                    $search_parts[] = 'dis.name_en LIKE :search';
                }

                if (fd_list_column_exists('districts', 'name')) {
                    $search_parts[] = 'dis.name LIKE :search';
                }
            }

            if (fd_list_table_exists('thanas')) {
                if (fd_list_column_exists('thanas', 'name_en')) {
                    $search_parts[] = 'th.name_en LIKE :search';
                }

                if (fd_list_column_exists('thanas', 'name')) {
                    $search_parts[] = 'th.name LIKE :search';
                }
            }

            if (!empty($search_parts)) {
                $where[] = '(' . implode(' OR ', $search_parts) . ')';
                $params[':search'] = '%' . $search . '%';
            }
        }

        $where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $parts = fd_list_build_join_parts();

        try {
            $stmt = $pdo->prepare("
                SELECT
                    fc.*,
                    " . implode(",\n                    ", $parts['selects']) . "
                FROM doctor_featured_contexts fc
                " . implode("\n                ", array_filter($parts['joins'])) . "
                {$where_sql}
                ORDER BY
                    CASE
                        WHEN fc.status = 'active' THEN 0
                        WHEN fc.status = 'hold' THEN 1
                        ELSE 2
                    END ASC,
                    CASE
                        WHEN COALESCE(fc.featured_position, 0) > 0 THEN fc.featured_position
                        ELSE 999999
                    END ASC,
                    fc.id DESC
                LIMIT 1000
            ");
            $stmt->execute($params);

            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }
}

fd_list_boot();
fd_list_auto_expire();

$status_filter = trim((string)($_GET['status'] ?? 'active'));
$status_filter = in_array($status_filter, ['active', 'hold', 'inactive', 'all'], true) ? $status_filter : 'active';

$search = trim((string)($_GET['search'] ?? ''));
$division_filter = max(0, (int)($_GET['division_id'] ?? 0));
$district_filter = max(0, (int)($_GET['district_id'] ?? 0));
$thana_filter = max(0, (int)($_GET['thana_id'] ?? 0));
$specialty_filter = max(0, (int)($_GET['specialty_id'] ?? 0));

$filters = [
    'status' => $status_filter,
    'search' => $search,
    'division_id' => $division_filter,
    'district_id' => $district_filter,
    'thana_id' => $thana_filter,
    'specialty_id' => $specialty_filter,
];

$records = fd_list_get_records($filters);

$total_all = fd_list_count_records();
$total_active = fd_list_count_records('active');
$total_hold = fd_list_count_records('hold');
$total_inactive = fd_list_count_records('inactive');

$divisions = fd_list_get_divisions();
$districts = fd_list_get_districts();
$thanas = fd_list_get_thanas();
$specialties = fd_list_get_specialties();

require_once __DIR__ . '/includes/header.php';
?>

<style>
.fd-page{display:grid;gap:18px}.fd-hero,.fd-card{background:#fff;border:1px solid #d0d7de;border-radius:12px;box-shadow:0 1px 0 rgba(27,31,36,.04);overflow:hidden}.fd-hero{padding:18px}.fd-hero-flex{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;flex-wrap:wrap}.fd-hero h1{margin:0;color:#24292f;font-size:28px;letter-spacing:-.03em}.fd-hero p{margin:8px 0 0;color:#57606a;line-height:1.6}.fd-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px}.fd-stat{padding:14px;border:1px solid #d0d7de;border-radius:10px;background:#fff}.fd-stat strong{display:block;color:#24292f;font-size:24px;line-height:1.1}.fd-stat span{display:block;margin-top:5px;color:#57606a;font-size:12px}.fd-card-head{padding:14px 16px;background:#f6f8fa;border-bottom:1px solid #d0d7de}.fd-card-head h2{margin:0;font-size:16px;color:#24292f}.fd-card-head p{margin:4px 0 0;color:#57606a;font-size:12px}.fd-filter{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:10px;margin-top:12px}.fd-filter input,.fd-filter select{width:100%;min-height:42px;border:1px solid #d0d7de;border-radius:8px;padding:8px 12px;background:#fff;color:#24292f;font-family:inherit;font-size:14px}.fd-filter-actions{display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-top:12px}.fd-card-body{padding:16px}.fd-table-wrap{overflow-x:auto}.fd-table{width:100%;border-collapse:separate;border-spacing:0}.fd-table th,.fd-table td{padding:12px;border-bottom:1px solid #d8dee4;text-align:left;vertical-align:middle;font-size:13px}.fd-table th{background:#f6f8fa;color:#57606a;font-size:12px;font-weight:800}.fd-table tr:last-child td{border-bottom:0}.fd-name{font-weight:800;color:#24292f}.fd-muted{display:block;margin-top:4px;color:#57606a;font-size:12px}.fd-link{color:#0969da;text-decoration:none;font-weight:800}.fd-link:hover{text-decoration:underline}.fd-pill{display:inline-flex;align-items:center;padding:5px 8px;border-radius:999px;border:1px solid #d0d7de;background:#f6f8fa;color:#57606a;font-size:12px;font-weight:800;white-space:nowrap}.fd-pill.green{background:#dafbe1;border-color:#aceebb;color:#116329}.fd-pill.blue{background:#ddf4ff;border-color:#b6e3ff;color:#0969da}.fd-pill.red{background:#ffebe9;border-color:#ff818266;color:#cf222e}.fd-pill.orange{background:#fff8c5;border-color:#f0d98c;color:#7d4e00}.fd-btn{min-height:36px;display:inline-flex;align-items:center;justify-content:center;gap:7px;padding:8px 12px;border-radius:6px;border:1px solid rgba(27,31,36,.15);background:#f6f8fa;color:#24292f;text-decoration:none;font-size:13px;font-weight:800;cursor:pointer}.fd-btn.green{background:#2da44e;color:#fff}.fd-count-text{color:#57606a;font-size:13px;font-weight:700}.fd-empty{padding:18px;color:#57606a;text-align:center}@media(max-width:1200px){.fd-filter{grid-template-columns:repeat(3,minmax(0,1fr))}}@media(max-width:760px){.fd-hero-flex{flex-direction:column}.fd-stats{grid-template-columns:1fr 1fr}.fd-filter{grid-template-columns:1fr}.fd-filter-actions,.fd-filter-actions .fd-btn{width:100%}}@media(max-width:560px){.fd-stats{grid-template-columns:1fr}}
</style>

<div class="fd-page">
    <div class="fd-hero fd-hero-flex">
        <div>
            <h1>Featured Doctors</h1>
            <p>Only featured doctor records are displayed here by location, area and specialty.</p>
        </div>

        <a href="featured-doctors-form.php" class="fd-btn green">+ Add Featured Context</a>
    </div>

    <div class="fd-stats">
        <div class="fd-stat">
            <strong><?= e((string)$total_all) ?></strong>
            <span>Total Featured Records</span>
        </div>

        <div class="fd-stat">
            <strong><?= e((string)$total_active) ?></strong>
            <span>Active</span>
        </div>

        <div class="fd-stat">
            <strong><?= e((string)$total_hold) ?></strong>
            <span>On Hold</span>
        </div>

        <div class="fd-stat">
            <strong><?= e((string)$total_inactive) ?></strong>
            <span>Inactive</span>
        </div>
    </div>

    <div class="fd-card">
        <div class="fd-card-head">
            <h2>Featured Doctor List</h2>
            <p>Use filters to find featured doctors by location, area, specialty, keyword or status.</p>

            <form method="GET" id="fdFilterForm">
                <div class="fd-filter">
                    <input type="search" name="search" value="<?= e($search) ?>" placeholder="Keyword search...">

                    <select name="division_id" id="fdDivisionFilter">
                        <option value="0">All Divisions</option>
                        <?php foreach ($divisions as $division): ?>
                            <option value="<?= e((string)$division['id']) ?>" <?= $division_filter === (int)$division['id'] ? 'selected' : '' ?>>
                                <?= e((string)$division['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <select name="district_id" id="fdDistrictFilter">
                        <option value="0">All Districts</option>
                        <?php foreach ($districts as $district): ?>
                            <option value="<?= e((string)$district['id']) ?>" data-division-id="<?= e((string)($district['division_id'] ?? 0)) ?>" <?= $district_filter === (int)$district['id'] ? 'selected' : '' ?>>
                                <?= e((string)$district['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <select name="thana_id" id="fdThanaFilter">
                        <option value="0">All Thanas</option>
                        <?php foreach ($thanas as $thana): ?>
                            <option value="<?= e((string)$thana['id']) ?>" data-district-id="<?= e((string)($thana['district_id'] ?? 0)) ?>" <?= $thana_filter === (int)$thana['id'] ? 'selected' : '' ?>>
                                <?= e((string)$thana['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <select name="specialty_id">
                        <option value="0">All Specialties</option>
                        <?php foreach ($specialties as $specialty): ?>
                            <option value="<?= e((string)($specialty['id'] ?? 0)) ?>" <?= $specialty_filter === (int)($specialty['id'] ?? 0) ? 'selected' : '' ?>>
                                <?= e((string)($specialty['name'] ?? '')) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <select name="status">
                        <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="hold" <?= $status_filter === 'hold' ? 'selected' : '' ?>>On Hold</option>
                        <option value="inactive" <?= $status_filter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                        <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>All Featured</option>
                    </select>
                </div>

                <div class="fd-filter-actions">
                    <button type="submit" class="fd-btn green">Filter Search</button>
                    <a href="featured-doctors.php" class="fd-btn">Reset</a>
                    <span class="fd-count-text">Showing <?= e((string)count($records)) ?> of <?= e((string)$total_all) ?> featured records</span>
                </div>
            </form>
        </div>

        <div class="fd-card-body">
            <div class="fd-table-wrap">
                <table class="fd-table">
                    <thead>
                        <tr>
                            <th>SL</th>
                            <th>Doctor</th>
                            <th>Division</th>
                            <th>District</th>
                            <th>Thana / Area</th>
                            <th>Specialty</th>
                            <th>Status</th>
                            <th>Position</th>
                            <th>Duration</th>
                            <th>Start</th>
                            <th>End</th>
                            <th>Remaining</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($records)): ?>
                            <tr>
                                <td colspan="13" class="fd-empty">No featured doctor records found.</td>
                            </tr>
                        <?php endif; ?>

                        <?php foreach ($records as $index => $record): ?>
                            <?php
                                $doctor_name = trim((string)($record['doctor_name'] ?? 'Unknown Doctor'));
                                $doctor_slug = trim((string)($record['doctor_slug'] ?? ''));

                                $division_name = trim((string)($record['division_name'] ?? ''));
                                $district_name = trim((string)($record['district_name'] ?? ''));
                                $thana_name = trim((string)($record['thana_name'] ?? ''));
                                $specialty_name = trim((string)($record['specialty_name'] ?? ''));

                                $district_slug = trim((string)($record['district_slug'] ?? ''));
                                $thana_slug = trim((string)($record['thana_slug'] ?? ''));
                                $specialty_slug = trim((string)($record['specialty_slug'] ?? ''));

                                $directory_path = '';

                                if ($district_slug !== '' && $specialty_slug !== '') {
                                    if ($thana_slug !== '') {
                                        $directory_path = 'doctors/' . rawurlencode($district_slug) . '/' . rawurlencode($thana_slug) . '/' . rawurlencode($specialty_slug);
                                    } else {
                                        $directory_path = 'doctors/' . rawurlencode($district_slug) . '/' . rawurlencode($specialty_slug);
                                    }
                                }

                                $directory_url = $directory_path !== ''
                                    ? (function_exists('site_url') ? site_url($directory_path) : '../' . $directory_path)
                                    : '';

                                $status = (string)($record['status'] ?? 'inactive');
                                $state_class = $status === 'active' ? 'green' : ($status === 'hold' ? 'blue' : 'red');
                                $state_label = $status === 'active' ? 'Active' : ($status === 'hold' ? 'On Hold' : 'Inactive');

                                $position = (int)($record['featured_position'] ?? 0);
                                $position_label = $position > 0 ? 'Position ' . $position : 'Auto';

                                $days = (int)($record['featured_days'] ?? 0);
                                $remaining = $status === 'hold'
                                    ? (int)($record['remaining_days'] ?? 0)
                                    : fd_list_remaining_days($record['featured_until'] ?? '');

                                $started = trim((string)($record['featured_started_at'] ?? ''));
                                $until = trim((string)($record['featured_until'] ?? ''));
                            ?>
                            <tr>
                                <td><?= e((string)($index + 1)) ?></td>

                                <td>
                                    <span class="fd-name"><?= e($doctor_name) ?></span>

                                    <?php if ($doctor_slug !== ''): ?>
                                        <a href="<?= e(site_url('doctor/' . $doctor_slug)) ?>" target="_blank" rel="noopener" class="fd-muted fd-link">View public profile</a>
                                    <?php endif; ?>
                                </td>

                                <td><?= e($division_name !== '' ? $division_name : 'Not set') ?></td>

                                <td><?= e($district_name !== '' ? $district_name : 'District not set') ?></td>

                                <td><?= e($thana_name !== '' ? $thana_name : 'District Level') ?></td>

                                <td><?= e($specialty_name !== '' ? $specialty_name : 'Specialty not set') ?></td>

                                <td><span class="fd-pill <?= e($state_class) ?>"><?= e($state_label) ?></span></td>

                                <td><span class="fd-pill orange"><?= e($position_label) ?></span></td>

                                <td><?= $days > 0 ? e((string)$days) . ' days' : '<span class="fd-muted">Not set</span>' ?></td>

                                <td><?= e($started !== '' ? $started : 'Not set') ?></td>

                                <td><?= e($until !== '' ? $until : 'Not set') ?></td>

                                <td><?= $remaining > 0 ? e((string)$remaining) . ' days' : '<span class="fd-muted">0 days</span>' ?></td>

                                <td>
                                    <?php if ($directory_url !== ''): ?>
                                        <a href="<?= e($directory_url) ?>" target="_blank" rel="noopener" class="fd-btn">View</a>
                                    <?php endif; ?>

                                    <a href="featured-doctors-context.php?edit=<?= e((string)($record['id'] ?? 0)) ?>" class="fd-btn">Edit</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const divisionFilter = document.getElementById('fdDivisionFilter');
    const districtFilter = document.getElementById('fdDistrictFilter');
    const thanaFilter = document.getElementById('fdThanaFilter');

    if (!divisionFilter || !districtFilter || !thanaFilter) {
        return;
    }

    const districtOptions = Array.from(districtFilter.options);
    const thanaOptions = Array.from(thanaFilter.options);

    function filterDistricts() {
        const divisionId = divisionFilter.value || '0';
        const currentDistrict = districtFilter.value || '0';

        districtFilter.innerHTML = '';

        districtOptions.forEach(function (option) {
            const optionDivisionId = option.getAttribute('data-division-id') || '0';

            if (option.value === '0' || divisionId === '0' || optionDivisionId === divisionId) {
                districtFilter.appendChild(option);
            }
        });

        const stillExists = Array.from(districtFilter.options).some(function (option) {
            return option.value === currentDistrict;
        });

        districtFilter.value = stillExists ? currentDistrict : '0';
    }

    function filterThanas() {
        const districtId = districtFilter.value || '0';
        const currentThana = thanaFilter.value || '0';

        thanaFilter.innerHTML = '';

        thanaOptions.forEach(function (option) {
            const optionDistrictId = option.getAttribute('data-district-id') || '0';

            if (option.value === '0' || districtId === '0' || optionDistrictId === districtId) {
                thanaFilter.appendChild(option);
            }
        });

        const stillExists = Array.from(thanaFilter.options).some(function (option) {
            return option.value === currentThana;
        });

        thanaFilter.value = stillExists ? currentThana : '0';
    }

    filterDistricts();
    filterThanas();

    divisionFilter.addEventListener('change', function () {
        districtFilter.value = '0';
        thanaFilter.value = '0';
        filterDistricts();
        filterThanas();
    });

    districtFilter.addEventListener('change', function () {
        thanaFilter.value = '0';
        filterThanas();
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
