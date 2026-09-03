<?php
require_once __DIR__ . '/../includes/functions.php';

require_admin();

/*
|--------------------------------------------------------------------------
| User Requests Page
|--------------------------------------------------------------------------
| Shows all profile/hospital requests submitted from user panel.
|--------------------------------------------------------------------------
*/

function admin_user_request_table_exists(string $table): bool
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

function admin_user_request_column_exists(string $table, string $column): bool
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
            ':column' => $column
        ]);

        return (int)$stmt->fetchColumn() > 0;
    } catch (Throwable $e) {
        return false;
    }
}

function admin_user_request_decode(?string $json): array
{
    if (!$json) {
        return [];
    }

    $data = json_decode($json, true);

    return is_array($data) ? $data : [];
}

function admin_user_request_label(string $type): string
{
    $labels = [
        'doctor_profile_create' => 'Doctor Profile Create',
        'hospital_profile_create' => 'Hospital Profile Create',
        'doctor_profile_update' => 'Doctor Profile Update',
        'hospital_profile_update' => 'Hospital Profile Update',
        'chamber_add' => 'Hospital Add',
        'chamber_update' => 'Hospital Update',
        'hospital_doctor_add' => 'Hospital Doctor Add',
    ];

    return $labels[$type] ?? ucwords(str_replace('_', ' ', $type));
}

function admin_request_status_class(string $status): string
{
    if ($status === 'approved') {
        return 'approved';
    }

    if ($status === 'rejected') {
        return 'rejected';
    }

    return 'pending';
}

function admin_request_short_text(string $text, int $limit = 80): string
{
    $text = trim($text);

    if ($text === '') {
        return '';
    }

    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        if (mb_strlen($text) <= $limit) {
            return $text;
        }

        return mb_substr($text, 0, $limit) . '...';
    }

    if (strlen($text) <= $limit) {
        return $text;
    }

    return substr($text, 0, $limit) . '...';
}

function admin_request_value(array $data, array $keys): string
{
    foreach ($keys as $key) {
        if (isset($data[$key]) && trim((string)$data[$key]) !== '') {
            return trim((string)$data[$key]);
        }
    }

    return '';
}

function admin_request_find_name_by_id(string $table, int $id, array $name_columns = ['name', 'title']): string
{
    global $pdo;

    if ($id <= 0 || !admin_user_request_table_exists($table)) {
        return '';
    }

    $name_column = '';

    foreach ($name_columns as $column) {
        if (admin_user_request_column_exists($table, $column)) {
            $name_column = $column;
            break;
        }
    }

    if ($name_column === '') {
        return '';
    }

    try {
        $stmt = $pdo->prepare("SELECT `{$name_column}` FROM `{$table}` WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);

        $name = $stmt->fetchColumn();

        return $name !== false ? trim((string)$name) : '';
    } catch (Throwable $e) {
        return '';
    }
}

function admin_request_specialty_name(array $request_data): string
{
    $direct_name = admin_request_value($request_data, [
        'specialty',
        'specialty_name',
        'doctor_specialty',
        'speciality',
        'speciality_name',
    ]);

    if ($direct_name !== '') {
        return $direct_name;
    }

    $specialty_id = (int)admin_request_value($request_data, [
        'specialty_id',
        'speciality_id',
        'doctor_specialty_id',
    ]);

    if ($specialty_id <= 0) {
        return '';
    }

    $tables = ['specialties', 'specialities', 'doctor_specialties'];

    foreach ($tables as $table) {
        $name = admin_request_find_name_by_id($table, $specialty_id, ['name', 'title', 'specialty_name']);

        if ($name !== '') {
            return $name;
        }
    }

    return '';
}

if (!admin_user_request_table_exists('profile_update_requests')) {
    require_once __DIR__ . '/includes/header.php';
    ?>

    <style>
        .admin-empty-box {
            padding: 22px;
            border-radius: 10px;
            background: #ffebe9;
            border: 1px solid rgba(207, 34, 46, .25);
            color: #cf222e;
            margin: 20px 0;
        }

        .admin-empty-box code {
            background: rgba(255,255,255,.7);
            padding: 3px 6px;
            border-radius: 6px;
        }
    </style>

    <div class="admin-empty-box">
        <h2>profile_update_requests table not found</h2>
        <p>You need to create the user panel request table first.</p>
        <p>Required table: <code>profile_update_requests</code></p>
    </div>

    <?php
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

/*
|--------------------------------------------------------------------------
| Filters
|--------------------------------------------------------------------------
*/

$status_filter = trim((string)($_GET['status'] ?? 'pending'));
$type_filter = trim((string)($_GET['type'] ?? ''));
$search = trim((string)($_GET['search'] ?? ''));

$allowed_statuses = ['all', 'pending', 'approved', 'rejected'];
$allowed_types = [
    '',
    'doctor_profile_create',
    'hospital_profile_create',
    'doctor_profile_update',
    'hospital_profile_update',
    'chamber_add',
    'chamber_update',
    'hospital_doctor_add',
];

if (!in_array($status_filter, $allowed_statuses, true)) {
    $status_filter = 'pending';
}

if (!in_array($type_filter, $allowed_types, true)) {
    $type_filter = '';
}

$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

$where = [];
$params = [];

if ($status_filter !== 'all') {
    $where[] = "pur.status = :status";
    $params[':status'] = $status_filter;
}

if ($type_filter !== '') {
    $where[] = "pur.request_type = :request_type";
    $params[':request_type'] = $type_filter;
}

if ($search !== '') {
    $where[] = "(
        u.name LIKE :search
        OR u.email LIKE :search
        OR pur.request_type LIKE :search
        OR pur.request_data LIKE :search
        OR CAST(pur.id AS CHAR) LIKE :search
    )";
    $params[':search'] = '%' . $search . '%';
}

$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

/*
|--------------------------------------------------------------------------
| Stats
|--------------------------------------------------------------------------
*/

$stats = [
    'total' => 0,
    'pending' => 0,
    'approved' => 0,
    'rejected' => 0,
];

try {
    $stmt = $pdo->query("
        SELECT status, COUNT(*) AS total
        FROM profile_update_requests
        GROUP BY status
    ");

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $key = (string)($row['status'] ?? '');
        $count = (int)($row['total'] ?? 0);

        if (isset($stats[$key])) {
            $stats[$key] = $count;
        }

        $stats['total'] += $count;
    }
} catch (Throwable $e) {
    // Keep default stats.
}

/*
|--------------------------------------------------------------------------
| Count Rows
|--------------------------------------------------------------------------
*/

$total_rows = 0;

try {
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM profile_update_requests pur
        LEFT JOIN users u ON u.id = pur.user_id
        {$where_sql}
    ");

    $stmt->execute($params);
    $total_rows = (int)$stmt->fetchColumn();
} catch (Throwable $e) {
    $total_rows = 0;
}

$total_pages = max(1, (int)ceil($total_rows / $per_page));
$showing_from = $total_rows > 0 ? ($offset + 1) : 0;
$showing_to = min($offset + $per_page, $total_rows);

/*
|--------------------------------------------------------------------------
| Fetch Requests
|--------------------------------------------------------------------------
*/

$requests = [];

try {
    $stmt = $pdo->prepare("
        SELECT
            pur.*,
            u.name AS user_name,
            u.email AS user_email,
            u.user_type AS user_type
        FROM profile_update_requests pur
        LEFT JOIN users u ON u.id = pur.user_id
        {$where_sql}
        ORDER BY
            CASE pur.status
                WHEN 'pending' THEN 1
                WHEN 'rejected' THEN 2
                WHEN 'approved' THEN 3
                ELSE 4
            END ASC,
            pur.id DESC
        LIMIT {$per_page} OFFSET {$offset}
    ");

    $stmt->execute($params);
    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $requests = [];
}

$request_type_counts = [];

try {
    $stmt = $pdo->query("
        SELECT request_type, COUNT(*) AS total
        FROM profile_update_requests
        GROUP BY request_type
        ORDER BY total DESC
    ");

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $request_type_counts[(string)$row['request_type']] = (int)$row['total'];
    }
} catch (Throwable $e) {
    $request_type_counts = [];
}

require_once __DIR__ . '/includes/header.php';
?>

<style>
    .ur-page {
        color: #24292f;
    }

    .ur-hero {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 16px;
        flex-wrap: wrap;
        padding: 18px;
        margin-bottom: 16px;
        border: 1px solid #d0d7de;
        border-radius: 10px;
        background: #ffffff;
        box-shadow: 0 1px 0 rgba(27, 31, 36, 0.04);
    }

    .ur-hero h1 {
        margin: 0 0 6px;
        font-size: 24px;
        line-height: 1.2;
        letter-spacing: -0.03em;
        color: #24292f;
    }

    .ur-hero p {
        max-width: 820px;
        margin: 0;
        color: #57606a;
        line-height: 1.6;
        font-size: 14px;
    }

    .ur-hero-actions {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }

    .ur-btn {
        min-height: 34px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 7px;
        padding: 7px 12px;
        border-radius: 6px;
        border: 1px solid rgba(27, 31, 36, 0.15);
        background: #f6f8fa;
        color: #24292f;
        font-size: 13px;
        font-weight: 700;
        line-height: 1;
        text-decoration: none;
        cursor: pointer;
        box-shadow: 0 1px 0 rgba(27, 31, 36, 0.04);
        font-family: inherit;
    }

    .ur-btn:hover {
        background: #eef1f4;
        color: #24292f;
        text-decoration: none;
    }

    .ur-btn-primary {
        color: #ffffff;
        background: #2da44e;
    }

    .ur-btn-primary:hover {
        color: #ffffff;
        background: #1f883d;
    }

    .ur-btn-blue {
        color: #ffffff;
        background: #0969da;
    }

    .ur-btn-blue:hover {
        color: #ffffff;
        background: #0757b8;
    }

    .ur-stats {
        display: grid;
        grid-template-columns: repeat(4, minmax(150px, 1fr));
        gap: 12px;
        margin-bottom: 16px;
    }

    .ur-stat {
        padding: 15px;
        border-radius: 10px;
        background: #ffffff;
        border: 1px solid #d0d7de;
        box-shadow: 0 1px 0 rgba(27, 31, 36, 0.04);
    }

    .ur-stat span {
        display: block;
        color: #57606a;
        font-size: 13px;
        margin-bottom: 7px;
        font-weight: 600;
    }

    .ur-stat strong {
        display: block;
        font-size: 26px;
        line-height: 1;
        color: #24292f;
    }

    .ur-layout {
        display: grid;
        grid-template-columns: 1fr 330px;
        gap: 16px;
        align-items: start;
    }

    .ur-stack {
        display: grid;
        gap: 16px;
    }

    .ur-card {
        overflow: hidden;
        border-radius: 10px;
        background: #ffffff;
        border: 1px solid #d0d7de;
        box-shadow: 0 1px 0 rgba(27, 31, 36, 0.04);
    }

    .ur-card-header {
        padding: 14px 16px;
        border-bottom: 1px solid #d0d7de;
        background: #f6f8fa;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        flex-wrap: wrap;
    }

    .ur-card-header h2 {
        margin: 0;
        font-size: 15px;
        color: #24292f;
    }

    .ur-card-header p,
    .ur-card-header span {
        margin: 0;
        color: #57606a;
        line-height: 1.5;
        font-size: 13px;
    }

    .ur-card-body {
        padding: 16px;
    }

    .ur-tabs {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
        margin-bottom: 16px;
    }

    .ur-tab {
        min-height: 34px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 7px;
        padding: 7px 12px;
        border-radius: 6px;
        border: 1px solid #d0d7de;
        background: #ffffff;
        color: #24292f;
        font-size: 13px;
        font-weight: 700;
        text-decoration: none;
    }

    .ur-tab:hover {
        background: #f6f8fa;
        text-decoration: none;
    }

    .ur-tab.active {
        color: #0969da;
        background: #ddf4ff;
        border-color: rgba(9, 105, 218, 0.25);
    }

    .ur-filters {
        display: grid;
        grid-template-columns: 1.3fr 1fr auto;
        gap: 10px;
        align-items: end;
    }

    .ur-field {
        display: grid;
        gap: 7px;
    }

    .ur-field label {
        color: #24292f;
        font-size: 13px;
        font-weight: 700;
    }

    .ur-field input,
    .ur-field select {
        width: 100%;
        min-height: 38px;
        border: 1px solid #d0d7de;
        border-radius: 6px;
        padding: 8px 10px;
        outline: none;
        background: #ffffff;
        color: #24292f;
        font-family: inherit;
    }

    .ur-field input:focus,
    .ur-field select:focus {
        border-color: #0969da;
        box-shadow: 0 0 0 3px rgba(9, 105, 218, 0.15);
    }

    .ur-actions-row {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }

    .ur-table-wrap {
        overflow-x: auto;
    }

    .ur-table {
        width: 100%;
        min-width: 1050px;
        border-collapse: separate;
        border-spacing: 0;
    }

    .ur-table th,
    .ur-table td {
        padding: 12px;
        border-bottom: 1px solid #d8dee4;
        text-align: left;
        vertical-align: top;
        font-size: 13px;
    }

    .ur-table th {
        color: #57606a;
        background: #f6f8fa;
        font-weight: 700;
    }

    .ur-table tr:last-child td {
        border-bottom: 0;
    }

    .ur-table tbody tr:hover {
        background: #f6f8fa;
    }

    .ur-user strong {
        display: block;
        color: #24292f;
        margin-bottom: 3px;
    }

    .ur-user span {
        display: block;
        color: #57606a;
        font-size: 12px;
        line-height: 1.5;
    }

    .ur-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 24px;
        padding: 4px 9px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 700;
        white-space: nowrap;
        border: 1px solid #d8dee4;
        background: #f6f8fa;
        color: #57606a;
    }

    .ur-badge.pending {
        color: #9a6700;
        background: #fff8c5;
        border-color: rgba(154, 103, 0, 0.25);
    }

    .ur-badge.approved {
        color: #1a7f37;
        background: #dafbe1;
        border-color: rgba(26, 127, 55, 0.25);
    }

    .ur-badge.rejected {
        color: #cf222e;
        background: #ffebe9;
        border-color: rgba(207, 34, 46, 0.25);
    }

    .ur-data-title {
        font-weight: 700;
        color: #24292f;
        margin-bottom: 5px;
    }

    .ur-data-sub {
        color: #57606a;
        line-height: 1.5;
        max-width: 280px;
        font-size: 12px;
    }

    .ur-info-list {
        display: grid;
        gap: 9px;
    }

    .ur-info-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 10px 11px;
        border: 1px solid #d8dee4;
        border-radius: 8px;
        background: #ffffff;
    }

    .ur-info-row span {
        color: #57606a;
        font-size: 13px;
    }

    .ur-info-row strong {
        color: #24292f;
        font-size: 13px;
        text-align: right;
    }

    .ur-guide {
        padding: 12px;
        border-radius: 8px;
        border: 1px solid #d8dee4;
        background: #f6f8fa;
        color: #57606a;
        font-size: 13px;
        line-height: 1.6;
    }

    .ur-pagination {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
        margin-top: 16px;
        color: #57606a;
        font-size: 13px;
    }

    .ur-pages {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }

    @media (max-width: 1100px) {
        .ur-layout {
            grid-template-columns: 1fr;
        }

        .ur-stats {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .ur-filters {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 600px) {
        .ur-stats {
            grid-template-columns: 1fr;
        }

        .ur-card-body,
        .ur-hero {
            padding: 14px;
        }

        .ur-btn,
        .ur-tab {
            width: 100%;
        }
    }
</style>

<div class="ur-page">

    <div class="ur-hero">
        <div>
            <h1>User Requests</h1>
            <p>
                Review profile create/update requests and approve valid changes submitted from the user panel.
            </p>
        </div>

        <div class="ur-hero-actions">
            <a href="dashboard.php" class="ur-btn">Dashboard</a>
            <a href="user-requests.php?status=pending" class="ur-btn ur-btn-primary">Pending Queue</a>
        </div>
    </div>

    <div class="ur-stats">
        <div class="ur-stat">
            <span>Total Requests</span>
            <strong><?= e((string)$stats['total']) ?></strong>
        </div>

        <div class="ur-stat">
            <span>Pending</span>
            <strong><?= e((string)$stats['pending']) ?></strong>
        </div>

        <div class="ur-stat">
            <span>Approved</span>
            <strong><?= e((string)$stats['approved']) ?></strong>
        </div>

        <div class="ur-stat">
            <span>Rejected</span>
            <strong><?= e((string)$stats['rejected']) ?></strong>
        </div>
    </div>

    <div class="ur-tabs">
        <?php
        $tab_base = [
            'type' => $type_filter,
            'search' => $search,
        ];

        $tabs = [
            'all' => 'All',
            'pending' => 'Pending',
            'approved' => 'Approved',
            'rejected' => 'Rejected',
        ];
        ?>

        <?php foreach ($tabs as $status_key => $status_label): ?>
            <?php $tab_query = http_build_query(array_merge($tab_base, ['status' => $status_key])); ?>
            <a href="user-requests.php?<?= e($tab_query) ?>" class="ur-tab <?= $status_filter === $status_key ? 'active' : '' ?>">
                <?= e($status_label) ?>
                <?php if (isset($stats[$status_key])): ?>
                    <span><?= e((string)$stats[$status_key]) ?></span>
                <?php elseif ($status_key === 'all'): ?>
                    <span><?= e((string)$stats['total']) ?></span>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>
    </div>

    <div class="ur-layout">
        <main class="ur-stack">

            <div class="ur-card">
                <div class="ur-card-header">
                    <div>
                        <h2>Filter Requests</h2>
                        <p>Find requests by user, email, type or request ID.</p>
                    </div>
                </div>

                <div class="ur-card-body">
                    <form method="get" class="ur-filters">
                        <input type="hidden" name="status" value="<?= e($status_filter) ?>">

                        <div class="ur-field">
                            <label>Search</label>
                            <input type="text" name="search" value="<?= e($search) ?>" placeholder="Search by user, email, type, ID">
                        </div>

                        <div class="ur-field">
                            <label>Request Type</label>
                            <select name="type">
                                <option value="">All Types</option>
                                <?php foreach (array_filter($allowed_types) as $type): ?>
                                    <option value="<?= e($type) ?>" <?= $type_filter === $type ? 'selected' : '' ?>>
                                        <?= e(admin_user_request_label($type)) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="ur-actions-row">
                            <button type="submit" class="ur-btn ur-btn-primary">Filter</button>
                            <a href="user-requests.php" class="ur-btn">Reset</a>
                        </div>
                    </form>
                </div>
            </div>

            <div class="ur-card">
                <div class="ur-card-header">
                    <div>
                        <h2>Request List</h2>
                        <p>Showing <?= e((string)$showing_from) ?>-<?= e((string)$showing_to) ?> of <?= e((string)$total_rows) ?> requests.</p>
                    </div>
                </div>

                <div class="ur-card-body">
                    <div class="ur-table-wrap">
                        <table class="ur-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>User</th>
                                    <th>Type</th>
                                    <th>Submitted Data</th>
                                    <th>Target</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                    <th style="width:160px;">Actions</th>
                                </tr>
                            </thead>

                            <tbody>
                                <?php if (!$requests): ?>
                                    <tr>
                                        <td colspan="8" style="text-align:center;color:#57606a;padding:24px;">No request found.</td>
                                    </tr>
                                <?php endif; ?>

                                <?php foreach ($requests as $request): ?>
                                    <?php
                                        $request_data = admin_user_request_decode($request['request_data'] ?? '');

                                        $display_name = admin_request_value($request_data, [
                                            'name',
                                            'doctor_name',
                                            'hospital_name',
                                            'chamber_name',
                                            'primary_hospital',
                                            'title',
                                        ]);

                                        $display_phone = admin_request_value($request_data, [
                                            'phone',
                                            'mobile',
                                            'contact_phone',
                                            'appointment_phone',
                                            'serial_phone',
                                        ]);

                                        $display_email = admin_request_value($request_data, [
                                            'email',
                                            'contact_email',
                                        ]);

                                        $specialty_name = admin_request_specialty_name($request_data);

                                        $thana = admin_request_value($request_data, [
                                            'thana',
                                            'upazila',
                                            'area',
                                            'police_station',
                                            'location',
                                        ]);

                                        $zilla = admin_request_value($request_data, [
                                            'zilla',
                                            'district',
                                            'zila',
                                            'city',
                                        ]);

                                        $status = (string)($request['status'] ?? 'pending');
                                        $status_class = admin_request_status_class($status);

                                        $user_display_name = trim((string)($request['user_name'] ?? ''));
                                        $user_display_email = trim((string)($request['user_email'] ?? ''));

                                        $doctor_name = '';
                                        $hospital_name = '';
                                        $chamber_name = '';

                                        if (!empty($request['doctor_id'])) {
                                            $doctor_name = admin_request_find_name_by_id('doctors', (int)$request['doctor_id'], ['name', 'doctor_name', 'title']);
                                        }

                                        if (!empty($request['hospital_id'])) {
                                            $hospital_name = admin_request_find_name_by_id('hospitals', (int)$request['hospital_id'], ['name', 'hospital_name', 'title']);
                                        }

                                        if (!empty($request['chamber_id'])) {
                                            $chamber_name = admin_request_find_name_by_id('hospitals', (int)$request['chamber_id'], ['name', 'hospital_name', 'title']);

                                            if ($chamber_name === '') {
                                                $chamber_name = admin_request_find_name_by_id('chambers', (int)$request['chamber_id'], ['name', 'chamber_name', 'title']);
                                            }
                                        }

                                        if ($doctor_name === '') {
                                            $doctor_name = admin_request_value($request_data, ['doctor_name', 'name']);
                                        }

                                        if ($hospital_name === '') {
                                            $hospital_name = admin_request_value($request_data, ['hospital_name', 'primary_hospital']);
                                        }

                                        if ($chamber_name === '') {
                                            $chamber_name = admin_request_value($request_data, ['chamber_name']);
                                        }
                                    ?>

                                    <tr>
                                        <td>
                                            <strong>#<?= e((string)$request['id']) ?></strong>
                                        </td>

                                        <td>
                                            <div class="ur-user">
                                                <strong><?= e($user_display_name !== '' ? $user_display_name : 'Unknown User') ?></strong>
                                                <?php if ($user_display_email !== ''): ?>
                                                    <span><?= e($user_display_email) ?></span>
                                                <?php endif; ?>
                                                <?php if (!empty($request['user_type'])): ?>
                                                    <span><?= e((string)$request['user_type']) ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </td>

                                        <td>
                                            <div class="ur-data-title">
                                                <?= e(admin_user_request_label((string)$request['request_type'])) ?>
                                            </div>
                                            <div class="ur-data-sub">
                                                <?= e($request['request_type'] ?? '') ?>
                                            </div>
                                        </td>

                                        <td>
                                            <div class="ur-data-title">
                                                <?= e($display_name !== '' ? $display_name : 'No title') ?>
                                            </div>

                                            <?php if ($display_phone !== ''): ?>
                                                <div class="ur-data-sub">Phone: <?= e($display_phone) ?></div>
                                            <?php endif; ?>

                                            <?php if ($display_email !== ''): ?>
                                                <div class="ur-data-sub">Email: <?= e($display_email) ?></div>
                                            <?php endif; ?>

                                            <?php if ($specialty_name !== ''): ?>
                                                <div class="ur-data-sub">Specialty: <?= e($specialty_name) ?></div>
                                            <?php endif; ?>

                                            <?php if ($thana !== ''): ?>
                                                <div class="ur-data-sub">Thana: <?= e($thana) ?></div>
                                            <?php endif; ?>

                                            <?php if ($zilla !== ''): ?>
                                                <div class="ur-data-sub">Zilla: <?= e($zilla) ?></div>
                                            <?php endif; ?>
                                        </td>

                                        <td>
                                            <?php if ($user_display_name !== ''): ?>
                                                <div class="ur-data-sub">User: <?= e($user_display_name) ?></div>
                                            <?php endif; ?>

                                            <?php if ($doctor_name !== ''): ?>
                                                <div class="ur-data-sub">Doctor: <?= e($doctor_name) ?></div>
                                            <?php endif; ?>

                                            <?php if ($hospital_name !== ''): ?>
                                                <div class="ur-data-sub">Hospital: <?= e($hospital_name) ?></div>
                                            <?php endif; ?>

                                            <?php if ($chamber_name !== ''): ?>
                                                <div class="ur-data-sub">Hospital: <?= e($chamber_name) ?></div>
                                            <?php endif; ?>

                                            <?php if ($user_display_name === '' && $doctor_name === '' && $hospital_name === '' && $chamber_name === ''): ?>
                                                <div class="ur-data-sub">No target found</div>
                                            <?php endif; ?>
                                        </td>

                                        <td>
                                            <span class="ur-badge <?= e($status_class) ?>">
                                                <?= e(ucfirst($status)) ?>
                                            </span>
                                        </td>

                                        <td>
                                            <div class="ur-data-sub">
                                                <?= !empty($request['created_at']) ? e(date('d M Y h:i A', strtotime((string)$request['created_at']))) : '—' ?>
                                            </div>
                                        </td>

                                        <td>
                                            <div class="ur-actions-row">
                                                <a
                                                    class="ur-btn ur-btn-blue"
                                                    href="user-request-view.php?id=<?= e((string)$request['id']) ?>"
                                                >
                                                    View
                                                </a>

                                                <?php if ($status === 'pending'): ?>
                                                    <form method="post" action="user-request-action.php" onsubmit="return confirm('Approve this request?');">
                                                        <input type="hidden" name="id" value="<?= e((string)$request['id']) ?>">
                                                        <input type="hidden" name="action" value="approve">
                                                        <button type="submit" class="ur-btn ur-btn-primary">
                                                            Approve
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="ur-pagination">
                        <div>
                            Page <?= e((string)$page) ?> of <?= e((string)$total_pages) ?>
                        </div>

                        <div class="ur-pages">
                            <?php
                                $query_base = [
                                    'status' => $status_filter,
                                    'type' => $type_filter,
                                    'search' => $search,
                                ];
                            ?>

                            <?php if ($page > 1): ?>
                                <?php $prev_query = http_build_query(array_merge($query_base, ['page' => $page - 1])); ?>
                                <a class="ur-btn" href="user-requests.php?<?= e($prev_query) ?>">Previous</a>
                            <?php endif; ?>

                            <?php if ($page < $total_pages): ?>
                                <?php $next_query = http_build_query(array_merge($query_base, ['page' => $page + 1])); ?>
                                <a class="ur-btn" href="user-requests.php?<?= e($next_query) ?>">Next</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

        </main>

        <aside class="ur-stack">
            <div class="ur-card">
                <div class="ur-card-header">
                    <h2>Request Types</h2>
                    <span>Summary</span>
                </div>

                <div class="ur-card-body">
                    <?php if (!$request_type_counts): ?>
                        <div class="ur-guide">No request type summary found.</div>
                    <?php else: ?>
                        <div class="ur-info-list">
                            <?php foreach ($request_type_counts as $type_key => $type_count): ?>
                                <div class="ur-info-row">
                                    <span><?= e(admin_user_request_label($type_key)) ?></span>
                                    <strong><?= e((string)$type_count) ?></strong>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="ur-card">
                <div class="ur-card-header">
                    <h2>Review Guide</h2>
                    <span>Before approval</span>
                </div>

                <div class="ur-card-body">
                    <div class="ur-guide">
                        Check submitted name, phone, email, specialty, thana and zilla before approving. Use View for full request details.
                    </div>
                </div>
            </div>
        </aside>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>