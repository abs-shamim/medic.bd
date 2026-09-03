<?php
require_once __DIR__ . '/../includes/functions.php';

require_admin();

if (!function_exists('fd_table_exists')) {
    function fd_table_exists(string $table): bool
    {
        global $pdo;
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table");
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

            $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :column");
            $stmt->execute([':table' => $table, ':column' => $column]);
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

            $stmt = $pdo->query("SELECT id, `{$name_column}` AS name FROM `{$table}` {$where} ORDER BY `{$name_column}` ASC");
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
        $select .= fd_column_exists('districts', 'division_id') ? ', division_id' : ', 0 AS division_id';

        try {
            $stmt = $pdo->query("SELECT {$select} FROM districts ORDER BY `{$name_column}` ASC");
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
        $select .= fd_column_exists('thanas', 'district_id') ? ', district_id' : ', 0 AS district_id';

        try {
            $stmt = $pdo->query("SELECT {$select} FROM thanas ORDER BY `{$name_column}` ASC");
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
            } catch (Throwable $e) {}
        }

        return fd_get_items('specialties');
    }
}

if (!function_exists('fd_image_url')) {
    function fd_image_url(?string $image): string
    {
        $image = trim((string)$image);

        if ($image === '') {
            return '';
        }

        if (preg_match('#^(https?:)?//#i', $image) || str_starts_with($image, 'data:')) {
            return $image;
        }

        if (str_starts_with($image, '../') || str_starts_with($image, './')) {
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
                $initials .= strtoupper(mb_substr($part, 0, 1));
            }

            if (mb_strlen($initials) >= 2) {
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

fd_boot_context_table();

if (!function_exists('fd_get_filtered_doctors')) {
    function fd_get_filtered_doctors(array $filters, int $limit = 60): array
    {
        global $pdo;

        if (!fd_table_exists('doctors')) {
            return [];
        }

        $where = [];
        $params = [];

        $search = trim((string)($filters['search'] ?? ''));
        $division_id = max(0, (int)($filters['division_id'] ?? 0));
        $district_id = max(0, (int)($filters['district_id'] ?? 0));
        $thana_id = max(0, (int)($filters['thana_id'] ?? 0));
        $specialty_id = max(0, (int)($filters['specialty_id'] ?? 0));
        $verified = trim((string)($filters['verified'] ?? ''));

        $doctor_name_col = fd_column_exists('doctors', 'name') ? 'name' : fd_name_column('doctors');

        $select = [
            'd.id',
            "d.`{$doctor_name_col}` AS name",
            fd_column_exists('doctors', 'slug') ? 'd.slug' : "'' AS slug",
            fd_column_exists('doctors', 'image') ? 'd.image' : "'' AS image",
            fd_column_exists('doctors', 'specialty_id') ? 'd.specialty_id' : '0 AS specialty_id',
            fd_column_exists('doctors', 'doctor_division_id') ? 'd.doctor_division_id' : '0 AS doctor_division_id',
            fd_column_exists('doctors', 'doctor_district_id') ? 'd.doctor_district_id' : '0 AS doctor_district_id',
            fd_column_exists('doctors', 'doctor_thana_id') ? 'd.doctor_thana_id' : '0 AS doctor_thana_id',
            fd_column_exists('doctors', 'is_verified') ? 'd.is_verified' : '0 AS is_verified',
        ];

        $join = [];

        if (fd_table_exists('specialties') && fd_column_exists('doctors', 'specialty_id')) {
            $specialty_name_col = fd_name_column('specialties');
            $select[] = "sp.`{$specialty_name_col}` AS specialty_name";
            $join[] = 'LEFT JOIN specialties sp ON sp.id = d.specialty_id';
        } else {
            $select[] = "'' AS specialty_name";
        }

        if (fd_table_exists('divisions') && fd_column_exists('doctors', 'doctor_division_id')) {
            $division_name_col = fd_name_column('divisions');
            $select[] = "dv.`{$division_name_col}` AS division_name";
            $join[] = 'LEFT JOIN divisions dv ON dv.id = d.doctor_division_id';
        } else {
            $select[] = "'' AS division_name";
        }

        if (fd_table_exists('districts') && fd_column_exists('doctors', 'doctor_district_id')) {
            $district_name_col = fd_name_column('districts');
            $select[] = "ds.`{$district_name_col}` AS district_name";
            $join[] = 'LEFT JOIN districts ds ON ds.id = d.doctor_district_id';
        } else {
            $select[] = "'' AS district_name";
        }

        if (fd_table_exists('thanas') && fd_column_exists('doctors', 'doctor_thana_id')) {
            $thana_name_col = fd_name_column('thanas');
            $select[] = "th.`{$thana_name_col}` AS thana_name";
            $join[] = 'LEFT JOIN thanas th ON th.id = d.doctor_thana_id';
        } else {
            $select[] = "'' AS thana_name";
        }

        $select[] = "CASE WHEN fc.id IS NULL THEN 0 ELSE 1 END AS context_featured";
        $join[] = "LEFT JOIN doctor_featured_contexts fc
                   ON fc.doctor_id = d.id
                  AND fc.district_id = COALESCE(d.doctor_district_id, 0)
                  AND fc.thana_id = COALESCE(d.doctor_thana_id, 0)
                  AND fc.specialty_id = COALESCE(d.specialty_id, 0)
                  AND fc.status = 'active'";

        if (fd_column_exists('doctors', 'status')) {
            $where[] = "(d.status IN ('active', 'published', '') OR d.status IS NULL)";
        }

        if ($search !== '') {
            $search_parts = [];
            foreach (['name', 'phone', 'email', 'bmdc_number', 'designation', 'degree', 'primary_hospital', 'address', 'area_locality', 'city'] as $column) {
                if (fd_column_exists('doctors', $column)) {
                    $search_parts[] = "d.`{$column}` LIKE :search";
                }
            }

            if (!empty($search_parts)) {
                $where[] = '(' . implode(' OR ', $search_parts) . ')';
                $params[':search'] = '%' . $search . '%';
            }
        }

        if ($division_id > 0 && fd_column_exists('doctors', 'doctor_division_id')) {
            $where[] = 'd.doctor_division_id = :division_id';
            $params[':division_id'] = $division_id;
        }

        if ($district_id > 0 && fd_column_exists('doctors', 'doctor_district_id')) {
            $where[] = 'd.doctor_district_id = :district_id';
            $params[':district_id'] = $district_id;
        }

        if ($thana_id > 0 && fd_column_exists('doctors', 'doctor_thana_id')) {
            $where[] = 'd.doctor_thana_id = :thana_id';
            $params[':thana_id'] = $thana_id;
        }

        if ($specialty_id > 0 && fd_column_exists('doctors', 'specialty_id')) {
            $where[] = 'd.specialty_id = :specialty_id';
            $params[':specialty_id'] = $specialty_id;
        }

        if ($verified !== '' && fd_column_exists('doctors', 'is_verified')) {
            $where[] = 'd.is_verified = :verified';
            $params[':verified'] = (int)$verified;
        }

        $where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        try {
            $stmt = $pdo->prepare("
                SELECT " . implode(', ', $select) . "
                FROM doctors d
                " . implode("\n", $join) . "
                {$where_sql}
                ORDER BY d.`{$doctor_name_col}` ASC
                LIMIT :limit
            ");

            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }

            $stmt->bindValue(':limit', max(1, min(200, $limit)), PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }
}

$search = trim((string)($_GET['search'] ?? ''));
$division_id = max(0, (int)($_GET['division_id'] ?? 0));
$district_id = max(0, (int)($_GET['district_id'] ?? 0));
$thana_id = max(0, (int)($_GET['thana_id'] ?? 0));
$specialty_id = max(0, (int)($_GET['specialty_id'] ?? 0));
$verified = trim((string)($_GET['verified'] ?? ''));

$divisions = fd_get_items('divisions');
$districts = fd_get_districts();
$thanas = fd_get_thanas();
$specialties = fd_get_specialties();

$doctors = fd_get_filtered_doctors([
    'search' => $search,
    'division_id' => $division_id,
    'district_id' => $district_id,
    'thana_id' => $thana_id,
    'specialty_id' => $specialty_id,
    'verified' => $verified,
]);

require_once __DIR__ . '/includes/header.php';
?>

<style>
.fd-page{max-width:1280px}.fd-header{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;margin-bottom:18px}.fd-header h1{margin:0;color:#24292f;font-size:26px;line-height:1.25}.fd-header p{margin:6px 0 0;color:#57606a;font-size:14px;line-height:1.6}.fd-card{background:#fff;border:1px solid #d0d7de;border-radius:12px;overflow:hidden;box-shadow:0 1px 0 rgba(27,31,36,.04);margin-bottom:16px}.fd-card-head{padding:14px 16px;background:#f6f8fa;border-bottom:1px solid #d0d7de}.fd-card-head h2{margin:0;color:#24292f;font-size:15px}.fd-card-head p{margin:4px 0 0;color:#57606a;font-size:12.5px;line-height:1.5}.fd-card-body{padding:16px}.fd-filter-grid{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:10px}.fd-filter-grid input,.fd-filter-grid select{width:100%;min-height:40px;border:1px solid #d0d7de;border-radius:8px;background:#fff;color:#24292f;padding:9px 11px;font-family:inherit;font-size:14px}.fd-filter-actions{display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin-top:14px}.fd-btn{display:inline-flex;align-items:center;justify-content:center;min-height:38px;padding:8px 14px;border-radius:6px;border:1px solid rgba(27,31,36,.15);cursor:pointer;font-size:13.5px;font-weight:700;text-decoration:none}.fd-btn-green{background:#2da44e;color:#fff}.fd-btn-green:hover{background:#1f883d;color:#fff}.fd-btn-light{background:#f6f8fa;color:#24292f}.fd-count-text{color:#57606a;font-size:13px;font-weight:700}.fd-table-wrap{overflow:auto}.fd-table{width:100%;border-collapse:separate;border-spacing:0}.fd-table th,.fd-table td{padding:11px 12px;border-bottom:1px solid #d8dee4;text-align:left;vertical-align:middle;font-size:13px}.fd-table th{background:#f6f8fa;color:#57606a;font-weight:800}.fd-name{font-weight:800;color:#24292f}.fd-avatar{width:42px;height:42px;display:inline-flex;align-items:center;justify-content:center;border-radius:50%;overflow:hidden;border:1px solid #d0d7de;background:#f6f8fa;color:#57606a;font-size:12px;font-weight:800}.fd-avatar img{width:100%;height:100%;object-fit:cover;display:block}.fd-pill{display:inline-flex;align-items:center;gap:6px;padding:5px 8px;border-radius:999px;border:1px solid #d0d7de;background:#f6f8fa;color:#57606a;font-size:12px;font-weight:800}.fd-pill.green{background:#dafbe1;border-color:#aceebb;color:#116329}.fd-pill.red{background:#ffebe9;border-color:#ff818266;color:#cf222e}.fd-empty{padding:18px;color:#57606a;text-align:center}@media(max-width:1150px){.fd-filter-grid{grid-template-columns:repeat(3,minmax(0,1fr))}}@media(max-width:760px){.fd-filter-grid{grid-template-columns:1fr}.fd-header{flex-direction:column}.fd-filter-actions,.fd-btn{width:100%}}
</style>

<div class="fd-page">
    <div class="fd-header">
        <div>
            <h1>Step 1: Select Doctor</h1>
            <p>Filter doctors first. Then click Add Featured to set location, specialty, duration and position on the next page.</p>
        </div>

        <a href="featured-doctors.php" class="fd-btn fd-btn-light">Back to Featured List</a>
    </div>

    <div class="fd-card">
        <div class="fd-card-head">
            <h2>Filter Doctors</h2>
            <p>Search by keyword, division, district, thana, specialty or verified status.</p>
        </div>

        <div class="fd-card-body">
            <form method="GET" action="featured-doctors-form.php" id="fdDoctorFilterForm">
                <div class="fd-filter-grid">
                    <input type="search" name="search" value="<?= e($search) ?>" placeholder="Keyword search...">

                    <select name="division_id" id="fdFilterDivision">
                        <option value="0">All Divisions</option>
                        <?php foreach ($divisions as $division): ?>
                            <option value="<?= e((string)$division['id']) ?>" <?= $division_id === (int)$division['id'] ? 'selected' : '' ?>>
                                <?= e((string)$division['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <select name="district_id" id="fdFilterDistrict">
                        <option value="0">All Districts</option>
                        <?php foreach ($districts as $district): ?>
                            <option value="<?= e((string)$district['id']) ?>" data-division-id="<?= e((string)($district['division_id'] ?? 0)) ?>" <?= $district_id === (int)$district['id'] ? 'selected' : '' ?>>
                                <?= e((string)$district['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <select name="thana_id" id="fdFilterThana">
                        <option value="0">All Thanas</option>
                        <?php foreach ($thanas as $thana): ?>
                            <option value="<?= e((string)$thana['id']) ?>" data-district-id="<?= e((string)($thana['district_id'] ?? 0)) ?>" <?= $thana_id === (int)$thana['id'] ? 'selected' : '' ?>>
                                <?= e((string)$thana['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <select name="specialty_id">
                        <option value="0">All Specialties</option>
                        <?php foreach ($specialties as $specialty): ?>
                            <option value="<?= e((string)($specialty['id'] ?? 0)) ?>" <?= $specialty_id === (int)($specialty['id'] ?? 0) ? 'selected' : '' ?>>
                                <?= e((string)($specialty['name'] ?? '')) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <select name="verified">
                        <option value="">All Verified</option>
                        <option value="1" <?= $verified === '1' ? 'selected' : '' ?>>Verified</option>
                        <option value="0" <?= $verified === '0' ? 'selected' : '' ?>>Not Verified</option>
                    </select>
                </div>

                <div class="fd-filter-actions">
                    <button type="submit" class="fd-btn fd-btn-green">Filter Search</button>
                    <a href="featured-doctors-form.php" class="fd-btn fd-btn-light">Reset</a>
                    <span class="fd-count-text">Showing <?= e((string)count($doctors)) ?> matching doctors</span>
                </div>
            </form>

            <div class="fd-table-wrap" style="margin-top:16px;">
                <table class="fd-table">
                    <thead>
                        <tr>
                            <th>SL</th>
                            <th>Image</th>
                            <th>Name</th>
                            <th>Specialty</th>
                            <th>Division</th>
                            <th>District</th>
                            <th>Thana</th>
                            <th>Verified</th>
                            <th>Featured</th>
                            <th>Action</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php if (empty($doctors)): ?>
                            <tr>
                                <td colspan="10" class="fd-empty">No doctors found. Please change filter.</td>
                            </tr>
                        <?php endif; ?>

                        <?php foreach ($doctors as $index => $doctor): ?>
                            <?php
                                $doctor_id = (int)($doctor['id'] ?? 0);
                                $step2_params = [
                                    'doctor_id' => $doctor_id,
                                    'division_id' => (int)($doctor['doctor_division_id'] ?? 0),
                                    'district_id' => (int)($doctor['doctor_district_id'] ?? 0),
                                    'thana_id' => (int)($doctor['doctor_thana_id'] ?? 0),
                                    'specialty_id' => (int)($doctor['specialty_id'] ?? 0),
                                ];
                                $step2_url = 'featured-doctors-context.php?' . http_build_query($step2_params);
                            ?>
                            <tr>
                                <td><?= e((string)($index + 1)) ?></td>
                                <td><?= fd_doctor_avatar($doctor) ?></td>
                                <td><span class="fd-name"><?= e((string)($doctor['name'] ?? 'Doctor')) ?></span></td>
                                <td><?= e((string)($doctor['specialty_name'] ?? '')) ?></td>
                                <td><?= e((string)($doctor['division_name'] ?? '')) ?></td>
                                <td><?= e((string)($doctor['district_name'] ?? '')) ?></td>
                                <td><?= e((string)($doctor['thana_name'] ?? '')) ?></td>
                                <td><?= !empty($doctor['is_verified']) ? '<span class="fd-pill green">✓</span>' : '<span class="fd-pill red">×</span>' ?></td>
                                <td><?= !empty($doctor['context_featured']) ? '<span class="fd-pill green">Featured</span>' : '<span class="fd-pill">Off</span>' ?></td>
                                <td><a href="<?= e($step2_url) ?>" class="fd-btn fd-btn-green">Add Featured</a></td>
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
    const divisionFilter = document.getElementById('fdFilterDivision');
    const districtFilter = document.getElementById('fdFilterDistrict');
    const thanaFilter = document.getElementById('fdFilterThana');

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
