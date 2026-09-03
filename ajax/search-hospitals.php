<?php
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

function search_hospitals_table_exists(string $table): bool
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

function search_hospitals_column_exists(string $table, string $column): bool
{
    global $pdo;

    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :column");
        $stmt->execute([':table' => $table, ':column' => $column]);
        return (int)$stmt->fetchColumn() > 0;
    } catch (Throwable $e) {
        return false;
    }
}

function search_hospitals_name_column(string $table): string
{
    if (search_hospitals_column_exists($table, 'name_en')) {
        return 'name_en';
    }

    if (search_hospitals_column_exists($table, 'name')) {
        return 'name';
    }

    return '';
}

function search_hospitals_view_url(array $hospital): string
{
    $id = (int)($hospital['id'] ?? 0);
    $slug = trim((string)($hospital['slug'] ?? ''));

    if ($slug !== '') {
        return '../hospital/' . rawurlencode($slug);
    }

    return '../admin/hospitals.php?id=' . $id;
}

try {
    if (!search_hospitals_table_exists('hospitals')) {
        echo json_encode([
            'success' => false,
            'message' => 'Hospitals table not found.',
            'items' => [],
        ]);
        exit;
    }

    $q = trim((string)($_GET['q'] ?? ''));
    $division_id = (int)($_GET['division_id'] ?? 0);
    $district_id = (int)($_GET['district_id'] ?? 0);
    $thana_id = (int)($_GET['thana_id'] ?? 0);
    $limit = (int)($_GET['limit'] ?? 50);

    if ($limit <= 0 || $limit > 100) {
        $limit = 50;
    }

    $has_status = search_hospitals_column_exists('hospitals', 'status');
    $has_slug = search_hospitals_column_exists('hospitals', 'slug');
    $has_division_id = search_hospitals_column_exists('hospitals', 'division_id');
    $has_district_id = search_hospitals_column_exists('hospitals', 'district_id');
    $has_thana_id = search_hospitals_column_exists('hospitals', 'thana_id');

    $select_slug = $has_slug ? 'h.slug' : "'' AS slug";
    $select_division_id = $has_division_id ? 'COALESCE(h.division_id, 0) AS division_id' : '0 AS division_id';
    $select_district_id = $has_district_id ? 'COALESCE(h.district_id, 0) AS district_id' : '0 AS district_id';
    $select_thana_id = $has_thana_id ? 'COALESCE(h.thana_id, 0) AS thana_id' : '0 AS thana_id';

    $joins = [];
    $select_location = [];

    if (!$has_division_id && search_hospitals_table_exists('districts') && search_hospitals_column_exists('districts', 'division_id') && $has_district_id) {
        $select_division_id = 'COALESCE(d.division_id, 0) AS division_id';
    }

    if (search_hospitals_table_exists('districts') && $has_district_id) {
        $district_name_column = search_hospitals_name_column('districts');
        $joins[] = 'LEFT JOIN districts d ON d.id = h.district_id';

        if ($district_name_column !== '') {
            $select_location[] = "COALESCE(d.{$district_name_column}, '') AS district_name";
        } else {
            $select_location[] = "'' AS district_name";
        }
    } else {
        $select_location[] = "'' AS district_name";
    }

    if (search_hospitals_table_exists('thanas') && $has_thana_id) {
        $thana_name_column = search_hospitals_name_column('thanas');
        $joins[] = 'LEFT JOIN thanas t ON t.id = h.thana_id';

        if ($thana_name_column !== '') {
            $select_location[] = "COALESCE(t.{$thana_name_column}, '') AS thana_name";
        } else {
            $select_location[] = "'' AS thana_name";
        }
    } else {
        $select_location[] = "'' AS thana_name";
    }

    if (search_hospitals_table_exists('divisions')) {
        $division_name_column = search_hospitals_name_column('divisions');

        if ($division_name_column !== '') {
            if ($has_division_id) {
                $joins[] = 'LEFT JOIN divisions dv ON dv.id = h.division_id';
            } elseif ($has_district_id && search_hospitals_table_exists('districts') && search_hospitals_column_exists('districts', 'division_id')) {
                $joins[] = 'LEFT JOIN divisions dv ON dv.id = d.division_id';
            }

            $select_location[] = "COALESCE(dv.{$division_name_column}, '') AS division_name";
        } else {
            $select_location[] = "'' AS division_name";
        }
    } else {
        $select_location[] = "'' AS division_name";
    }

    $where = [];
    $params = [];

    if ($has_status) {
        $where[] = "h.status = 'active'";
    }

    if ($q !== '') {
        $where[] = "h.name LIKE :q";
        $params[':q'] = '%' . $q . '%';
    }

    if ($division_id > 0) {
        if ($has_division_id) {
            $where[] = "h.division_id = :division_id";
        } elseif (search_hospitals_table_exists('districts') && search_hospitals_column_exists('districts', 'division_id') && $has_district_id) {
            $where[] = "d.division_id = :division_id";
        }
        $params[':division_id'] = $division_id;
    }

    if ($district_id > 0 && $has_district_id) {
        $where[] = "h.district_id = :district_id";
        $params[':district_id'] = $district_id;
    }

    if ($thana_id > 0 && $has_thana_id) {
        $where[] = "h.thana_id = :thana_id";
        $params[':thana_id'] = $thana_id;
    }

    $where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $sql = "
        SELECT
            h.id,
            h.name,
            {$select_slug},
            {$select_division_id},
            {$select_district_id},
            {$select_thana_id},
            " . implode(",\n            ", $select_location) . "
        FROM hospitals h
        " . implode("\n        ", array_unique($joins)) . "
        {$where_sql}
        ORDER BY
            CASE WHEN h.name LIKE :starts_with THEN 0 ELSE 1 END,
            h.name ASC
        LIMIT {$limit}
    ";

    $stmt = $pdo->prepare($sql);

    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }

    $stmt->bindValue(':starts_with', $q !== '' ? $q . '%' : '%');

    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $items = [];

    foreach ($rows as $row) {
        $location_parts = array_filter([
            trim((string)($row['thana_name'] ?? '')),
            trim((string)($row['district_name'] ?? '')),
            trim((string)($row['division_name'] ?? '')),
        ]);

        $items[] = [
            'id' => (int)$row['id'],
            'name' => (string)$row['name'],
            'division_id' => (int)($row['division_id'] ?? 0),
            'district_id' => (int)($row['district_id'] ?? 0),
            'thana_id' => (int)($row['thana_id'] ?? 0),
            'location' => implode(', ', $location_parts),
            'view_url' => search_hospitals_view_url($row),
        ];
    }

    echo json_encode([
        'success' => true,
        'items' => $items,
    ]);
} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Hospital search failed.',
        'items' => [],
    ]);
}