<?php
require_once __DIR__ . '/../includes/functions.php';

require_admin();

function admin_claim_table_exists(string $table): bool
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

function admin_claim_column_exists(string $table, string $column): bool
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

function admin_claim_status_class(string $status): string
{
    if ($status === 'approved') {
        return 'approved';
    }

    if ($status === 'rejected') {
        return 'rejected';
    }

    return 'pending';
}

function admin_claim_type_label(string $type): string
{
    if ($type === 'doctor') {
        return 'Doctor Profile Claim';
    }

    if ($type === 'hospital') {
        return 'Hospital Profile Claim';
    }

    return ucwords(str_replace('_', ' ', $type));
}

function admin_claim_user_type_label(string $type): string
{
    $type = trim($type);

    if ($type === '') {
        return 'User';
    }

    if ($type === 'hospital_owner') {
        return 'Hospital Owner';
    }

    return ucwords(str_replace('_', ' ', $type));
}

function admin_claim_site_setting(string $key, string $default = ''): string
{
    global $pdo;

    $key = trim($key);

    if ($key === '') {
        return $default;
    }

    if (function_exists('get_site_setting')) {
        $value = trim((string)get_site_setting($key, $default));
        return $value !== '' ? $value : $default;
    }

    if (!admin_claim_table_exists('site_settings')) {
        return $default;
    }

    try {
        $stmt = $pdo->prepare("
            SELECT setting_value
            FROM site_settings
            WHERE setting_key = :setting_key
            LIMIT 1
        ");
        $stmt->execute([':setting_key' => $key]);

        $value = trim((string)$stmt->fetchColumn());

        return $value !== '' ? $value : $default;
    } catch (Throwable $e) {
        return $default;
    }
}

function admin_claim_url(string $path): string
{
    $path = trim($path);

    if ($path === '') {
        return '';
    }

    if (preg_match('/^https?:\/\//i', $path)) {
        return $path;
    }

    $path = str_replace('\\', '/', $path);
    $path = preg_replace('#^(\.\./)+#', '', $path);
    $path = ltrim($path, '/');

    if (function_exists('site_url')) {
        return site_url($path);
    }

    if (defined('BASE_URL') && BASE_URL !== '') {
        return rtrim((string)BASE_URL, '/') . '/' . $path;
    }

    return '../' . $path;
}

function admin_claim_svg_fallback(string $type, string $name = ''): string
{
    $name = trim($name);
    $letter = $type === 'hospital' ? 'H' : 'D';

    if ($name !== '') {
        $letter = function_exists('mb_substr')
            ? mb_substr($name, 0, 1, 'UTF-8')
            : substr($name, 0, 1);
    }

    $bg1 = $type === 'hospital' ? '#0969da' : '#2da44e';
    $bg2 = $type === 'hospital' ? '#8250df' : '#0969da';
    $label = htmlspecialchars(strtoupper($letter), ENT_QUOTES, 'UTF-8');

    $svg = '
    <svg xmlns="http://www.w3.org/2000/svg" width="240" height="240" viewBox="0 0 240 240">
        <defs>
            <linearGradient id="g" x1="0" y1="0" x2="1" y2="1">
                <stop offset="0%" stop-color="' . $bg1 . '"/>
                <stop offset="100%" stop-color="' . $bg2 . '"/>
            </linearGradient>
        </defs>
        <rect width="240" height="240" rx="34" fill="url(#g)"/>
        <circle cx="120" cy="88" r="38" fill="rgba(255,255,255,.9)"/>
        <rect x="54" y="140" width="132" height="46" rx="23" fill="rgba(255,255,255,.9)"/>
        <text x="120" y="126" text-anchor="middle" font-family="Arial, sans-serif" font-size="64" font-weight="800" fill="#ffffff">' . $label . '</text>
    </svg>';

    return 'data:image/svg+xml;base64,' . base64_encode($svg);
}

function admin_claim_profile_image(string $type, array $claim): string
{
    if ($type === 'doctor') {
        $default = admin_claim_site_setting(
            'default_doctor_image',
            '../assets/images/default-doctor.webp'
        );

        $image = trim((string)(
            $claim['doctor_image']
            ?? $claim['doctor_photo']
            ?? $claim['doctor_profile_image']
            ?? $claim['doctor_avatar']
            ?? ''
        ));

        return admin_claim_url($image !== '' ? $image : $default);
    }

    if ($type === 'hospital') {
        $default = admin_claim_site_setting(
            'default_hospital_image',
            '../assets/images/default-hospital.webp'
        );

        $image = trim((string)(
            $claim['hospital_image']
            ?? $claim['hospital_logo']
            ?? $claim['hospital_photo']
            ?? $claim['hospital_profile_image']
            ?? ''
        ));

        return admin_claim_url($image !== '' ? $image : $default);
    }

    return admin_claim_url('../assets/images/default-doctor.webp');
}

if (!admin_claim_table_exists('profile_claims')) {
    require_once __DIR__ . '/includes/header.php';
    ?>

    <style>
        .gh-empty-box {
            margin: 20px 0;
            padding: 24px;
            border-radius: 12px;
            background: #ffebe9;
            border: 1px solid rgba(207,34,46,.22);
            color: #cf222e;
        }

        .gh-empty-box code {
            background: rgba(255,255,255,.75);
            padding: 3px 7px;
            border-radius: 6px;
        }
    </style>

    <div class="gh-empty-box">
        <h2>profile_claims table not found</h2>
        <p>You need to create the user panel claim table first.</p>
        <p>Required table: <code>profile_claims</code></p>
    </div>

    <?php
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

$status_filter = trim((string)($_GET['status'] ?? 'pending'));
$type_filter = trim((string)($_GET['type'] ?? ''));
$search = trim((string)($_GET['search'] ?? ''));

$allowed_statuses = ['all', 'pending', 'approved', 'rejected'];
$allowed_types = ['', 'doctor', 'hospital'];

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
    $where[] = "pc.status = :status";
    $params[':status'] = $status_filter;
}

if ($type_filter !== '') {
    $where[] = "pc.claim_type = :claim_type";
    $params[':claim_type'] = $type_filter;
}

if ($search !== '') {
    $where[] = "(
        u.name LIKE :search
        OR u.email LIKE :search
        OR u.phone LIKE :search
        OR u.user_type LIKE :search
        OR pc.name LIKE :search
        OR pc.email LIKE :search
        OR pc.phone LIKE :search
        OR pc.message LIKE :search
        OR pc.proof_text LIKE :search
        OR pc.profile_name LIKE :search
        OR CAST(pc.id AS CHAR) LIKE :search
    )";
    $params[':search'] = '%' . $search . '%';
}

$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$stats = [
    'total' => 0,
    'pending' => 0,
    'approved' => 0,
    'rejected' => 0,
];

try {
    $stmt = $pdo->query("
        SELECT status, COUNT(*) AS total
        FROM profile_claims
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

$total_rows = 0;

try {
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM profile_claims pc
        LEFT JOIN users u ON u.id = pc.user_id
        {$where_sql}
    ");
    $stmt->execute($params);

    $total_rows = (int)$stmt->fetchColumn();
} catch (Throwable $e) {
    $total_rows = 0;
}

$total_pages = max(1, (int)ceil($total_rows / $per_page));

$claims = [];

try {
    $doctor_join = admin_claim_table_exists('doctors') ? "LEFT JOIN doctors d ON d.id = pc.doctor_id" : "";
    $hospital_join = admin_claim_table_exists('hospitals') ? "LEFT JOIN hospitals h ON h.id = pc.hospital_id" : "";

    $doctor_image_column = 'NULL';
    $doctor_photo_column = 'NULL';
    $doctor_profile_image_column = 'NULL';
    $doctor_avatar_column = 'NULL';

    if (admin_claim_table_exists('doctors')) {
        if (admin_claim_column_exists('doctors', 'image')) {
            $doctor_image_column = 'd.image';
        }

        if (admin_claim_column_exists('doctors', 'photo')) {
            $doctor_photo_column = 'd.photo';
        }

        if (admin_claim_column_exists('doctors', 'profile_image')) {
            $doctor_profile_image_column = 'd.profile_image';
        }

        if (admin_claim_column_exists('doctors', 'avatar')) {
            $doctor_avatar_column = 'd.avatar';
        }
    }

    $hospital_image_column = 'NULL';
    $hospital_logo_column = 'NULL';
    $hospital_photo_column = 'NULL';
    $hospital_profile_image_column = 'NULL';

    if (admin_claim_table_exists('hospitals')) {
        if (admin_claim_column_exists('hospitals', 'image')) {
            $hospital_image_column = 'h.image';
        }

        if (admin_claim_column_exists('hospitals', 'logo')) {
            $hospital_logo_column = 'h.logo';
        }

        if (admin_claim_column_exists('hospitals', 'photo')) {
            $hospital_photo_column = 'h.photo';
        }

        if (admin_claim_column_exists('hospitals', 'profile_image')) {
            $hospital_profile_image_column = 'h.profile_image';
        }
    }

    $doctor_select = admin_claim_table_exists('doctors')
        ? "d.name AS doctor_name,
           {$doctor_image_column} AS doctor_image,
           {$doctor_photo_column} AS doctor_photo,
           {$doctor_profile_image_column} AS doctor_profile_image,
           {$doctor_avatar_column} AS doctor_avatar,"
        : "NULL AS doctor_name,
           NULL AS doctor_image,
           NULL AS doctor_photo,
           NULL AS doctor_profile_image,
           NULL AS doctor_avatar,";

    $hospital_select = admin_claim_table_exists('hospitals')
        ? "h.name AS hospital_name,
           {$hospital_image_column} AS hospital_image,
           {$hospital_logo_column} AS hospital_logo,
           {$hospital_photo_column} AS hospital_photo,
           {$hospital_profile_image_column} AS hospital_profile_image,"
        : "NULL AS hospital_name,
           NULL AS hospital_image,
           NULL AS hospital_logo,
           NULL AS hospital_photo,
           NULL AS hospital_profile_image,";

    $stmt = $pdo->prepare("
        SELECT
            pc.*,
            u.name AS user_name,
            u.user_type AS user_type,
            {$doctor_select}
            {$hospital_select}
            u.claimed_doctor_id,
            u.claimed_hospital_id
        FROM profile_claims pc
        LEFT JOIN users u ON u.id = pc.user_id
        {$doctor_join}
        {$hospital_join}
        {$where_sql}
        ORDER BY
            pc.created_at DESC,
            pc.id DESC
        LIMIT {$per_page} OFFSET {$offset}
    ");

    $stmt->execute($params);
    $claims = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $claims = [];
}

require_once __DIR__ . '/includes/header.php';
?>

<style>
    :root {
        --gh-bg: #f6f8fa;
        --gh-canvas: #ffffff;
        --gh-subtle: #f6f8fa;
        --gh-border: #d0d7de;
        --gh-border-muted: #d8dee4;
        --gh-text: #24292f;
        --gh-muted: #57606a;
        --gh-blue: #0969da;
        --gh-green: #1a7f37;
        --gh-green-bg: #dafbe1;
        --gh-red: #cf222e;
        --gh-red-bg: #ffebe9;
        --gh-yellow: #9a6700;
        --gh-yellow-bg: #fff8c5;
        --gh-shadow: 0 8px 24px rgba(140,149,159,.18);
    }

    body {
        background: var(--gh-bg);
    }

    .gh-claims-page,
    .gh-claims-page * {
        box-sizing: border-box;
    }

    .gh-claims-page {
        max-width: 1240px;
        margin: 0 auto;
        padding: 12px 0 42px;
        color: var(--gh-text);
    }

    .gh-page-head {
        margin-bottom: 16px;
        padding: 18px 20px;
        border: 1px solid var(--gh-border);
        border-radius: 12px;
        background:
            radial-gradient(circle at top right, rgba(9,105,218,.12), transparent 34%),
            linear-gradient(135deg, #ffffff, #f6f8fa);
        box-shadow: var(--gh-shadow);
    }

    .gh-head-row {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 16px;
        flex-wrap: wrap;
    }

    .gh-breadcrumb {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        margin-bottom: 10px;
        color: var(--gh-muted);
        font-size: 13px;
    }

    .gh-breadcrumb a {
        color: var(--gh-blue);
        text-decoration: none;
        font-weight: 600;
    }

    .gh-breadcrumb a:hover {
        text-decoration: underline;
    }

    .gh-page-head h1 {
        margin: 0;
        color: var(--gh-text);
        font-size: 28px;
        line-height: 1.25;
        font-weight: 800;
        letter-spacing: -.035em;
    }

    .gh-page-head p {
        max-width: 760px;
        margin: 7px 0 0;
        color: var(--gh-muted);
        font-size: 14px;
        line-height: 1.65;
    }

    .gh-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 34px;
        padding: 6px 12px;
        border-radius: 8px;
        border: 1px solid rgba(27,31,36,.15);
        background: var(--gh-canvas);
        color: var(--gh-blue);
        font-family: inherit;
        font-size: 13px;
        font-weight: 700;
        text-decoration: none;
        cursor: pointer;
        white-space: nowrap;
        transition: .15s ease;
    }

    .gh-btn:hover {
        background: var(--gh-subtle);
        text-decoration: none;
    }

    .gh-btn-muted {
        color: var(--gh-text);
    }

    .gh-stats {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 12px;
        margin-bottom: 16px;
    }

    .gh-stat {
        padding: 14px 15px;
        border: 1px solid var(--gh-border);
        border-radius: 12px;
        background: var(--gh-canvas);
        box-shadow: 0 6px 18px rgba(140,149,159,.12);
    }

    .gh-stat span {
        display: block;
        color: var(--gh-muted);
        font-size: 12px;
        font-weight: 700;
        margin-bottom: 7px;
    }

    .gh-stat strong {
        display: block;
        color: var(--gh-text);
        font-size: 26px;
        line-height: 1;
        font-weight: 800;
    }

    .gh-panel {
        margin-bottom: 16px;
        border: 1px solid var(--gh-border);
        border-radius: 12px;
        background: var(--gh-canvas);
        box-shadow: var(--gh-shadow);
        overflow: hidden;
    }

    .gh-panel-head {
        padding: 14px 16px;
        border-bottom: 1px solid var(--gh-border);
        background: var(--gh-subtle);
    }

    .gh-panel-head h2 {
        margin: 0;
        color: var(--gh-text);
        font-size: 15px;
        font-weight: 800;
    }

    .gh-panel-head p {
        margin: 5px 0 0;
        color: var(--gh-muted);
        font-size: 13px;
        line-height: 1.55;
    }

    .gh-panel-body {
        padding: 16px;
    }

    .gh-filters {
        display: grid;
        grid-template-columns: 1.3fr .8fr .8fr auto;
        gap: 10px;
        align-items: end;
    }

    .gh-field {
        display: grid;
        gap: 6px;
    }

    .gh-field label {
        color: var(--gh-text);
        font-size: 12px;
        font-weight: 700;
    }

    .gh-field input,
    .gh-field select {
        width: 100%;
        min-height: 36px;
        border: 1px solid var(--gh-border);
        border-radius: 8px;
        padding: 7px 10px;
        background: var(--gh-canvas);
        color: var(--gh-text);
        font-size: 13px;
        outline: none;
    }

    .gh-field input:focus,
    .gh-field select:focus {
        border-color: var(--gh-blue);
        box-shadow: 0 0 0 3px rgba(9,105,218,.12);
    }

    .gh-filter-actions,
    .gh-actions {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
    }

    .gh-table-wrap {
        overflow-x: auto;
    }

    .gh-table {
        width: 100%;
        min-width: 980px;
        border-collapse: collapse;
    }

    .gh-table th,
    .gh-table td {
        padding: 13px 12px;
        border-bottom: 1px solid var(--gh-border-muted);
        text-align: left;
        vertical-align: middle;
        font-size: 13px;
    }

    .gh-table th {
        background: var(--gh-subtle);
        color: var(--gh-muted);
        font-size: 12px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: .02em;
    }

    .gh-table tr:hover td {
        background: #fbfcfd;
    }

    .gh-sl {
        width: 60px;
        color: var(--gh-muted);
        font-weight: 800;
    }

    .gh-user strong,
    .gh-title-text {
        display: block;
        color: var(--gh-text);
        font-weight: 800;
        line-height: 1.45;
    }

    .gh-user span,
    .gh-sub-text {
        display: block;
        margin-top: 3px;
        color: var(--gh-muted);
        font-size: 12px;
        line-height: 1.45;
    }

    .gh-profile-mini {
        display: flex;
        align-items: center;
        gap: 10px;
        min-width: 240px;
    }

    .gh-profile-mini-img {
        width: 44px;
        height: 44px;
        border-radius: 10px;
        object-fit: cover;
        border: 1px solid var(--gh-border);
        background: var(--gh-subtle);
        flex: 0 0 44px;
    }

    .gh-profile-mini strong {
        display: block;
        color: var(--gh-text);
        font-size: 13px;
        font-weight: 800;
        line-height: 1.35;
    }

    .gh-profile-mini span {
        display: block;
        margin-top: 3px;
        color: var(--gh-muted);
        font-size: 12px;
        line-height: 1.35;
    }

    .gh-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 26px;
        padding: 4px 9px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 800;
        border: 1px solid var(--gh-border);
    }

    .gh-badge.pending {
        color: var(--gh-yellow);
        background: var(--gh-yellow-bg);
        border-color: rgba(154,103,0,.22);
    }

    .gh-badge.approved {
        color: var(--gh-green);
        background: var(--gh-green-bg);
        border-color: rgba(26,127,55,.22);
    }

    .gh-badge.rejected {
        color: var(--gh-red);
        background: var(--gh-red-bg);
        border-color: rgba(207,34,46,.22);
    }

    .gh-empty {
        padding: 24px;
        color: var(--gh-muted);
        text-align: center;
    }

    .gh-pagination {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
        padding-top: 16px;
        color: var(--gh-muted);
        font-size: 13px;
    }

    .gh-pages {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
    }

    @media (max-width: 980px) {
        .gh-filters {
            grid-template-columns: 1fr;
        }

        .gh-stats {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 560px) {
        .gh-stats {
            grid-template-columns: 1fr;
        }

        .gh-page-head,
        .gh-panel-body,
        .gh-panel-head {
            padding: 14px;
        }

        .gh-filter-actions,
        .gh-actions {
            display: grid;
            grid-template-columns: 1fr;
        }

        .gh-btn {
            width: 100%;
        }
    }
</style>

<div class="gh-claims-page">

    <div class="gh-page-head">
        <div class="gh-head-row">
            <div>
                <div class="gh-breadcrumb">
                    <a href="dashboard.php">Dashboard</a>
                    <span>/</span>
                    <span>Profile Claims</span>
                </div>

                <h1>Profile Claims</h1>
                <p>
                    Review doctor and hospital ownership claim requests. Use View to check full submitted details.
                </p>
            </div>

            <div>
                <a href="dashboard.php" class="gh-btn gh-btn-muted">Back to Dashboard</a>
            </div>
        </div>
    </div>

    <div class="gh-stats">
        <div class="gh-stat">
            <span>Total Claims</span>
            <strong><?= e((string)$stats['total']) ?></strong>
        </div>

        <div class="gh-stat">
            <span>Pending</span>
            <strong><?= e((string)$stats['pending']) ?></strong>
        </div>

        <div class="gh-stat">
            <span>Approved</span>
            <strong><?= e((string)$stats['approved']) ?></strong>
        </div>

        <div class="gh-stat">
            <span>Rejected</span>
            <strong><?= e((string)$stats['rejected']) ?></strong>
        </div>
    </div>

    <div class="gh-panel">
        <div class="gh-panel-head">
            <h2>Filter Claims</h2>
            <p>Search by user, profile, phone, email, message, or claim ID.</p>
        </div>

        <div class="gh-panel-body">
            <form method="get" class="gh-filters">
                <div class="gh-field">
                    <label>Search</label>
                    <input
                        type="text"
                        name="search"
                        value="<?= e($search) ?>"
                        placeholder="Search user, profile, phone, email, ID"
                    >
                </div>

                <div class="gh-field">
                    <label>Status</label>
                    <select name="status">
                        <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>All Status</option>
                        <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="approved" <?= $status_filter === 'approved' ? 'selected' : '' ?>>Approved</option>
                        <option value="rejected" <?= $status_filter === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                    </select>
                </div>

                <div class="gh-field">
                    <label>Claim Type</label>
                    <select name="type">
                        <option value="">All Types</option>
                        <option value="doctor" <?= $type_filter === 'doctor' ? 'selected' : '' ?>>Doctor</option>
                        <option value="hospital" <?= $type_filter === 'hospital' ? 'selected' : '' ?>>Hospital</option>
                    </select>
                </div>

                <div class="gh-filter-actions">
                    <button type="submit" class="gh-btn">Filter</button>
                    <a href="profile-claims.php" class="gh-btn gh-btn-muted">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="gh-panel">
        <div class="gh-panel-head">
            <h2>Claim List</h2>
            <p>Total matched claims: <?= e((string)$total_rows) ?></p>
        </div>

        <div class="gh-panel-body">
            <div class="gh-table-wrap">
                <table class="gh-table">
                    <thead>
                        <tr>
                            <th>SL</th>
                            <th>User</th>
                            <th>Claim Type</th>
                            <th>Claimed Profile</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th style="width:110px;">Actions</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php if (!$claims): ?>
                            <tr>
                                <td colspan="7">
                                    <div class="gh-empty">No profile claim found.</div>
                                </td>
                            </tr>
                        <?php endif; ?>

                        <?php foreach ($claims as $index => $claim): ?>
                            <?php
                                $sl = $offset + $index + 1;
                                $claim_id = (int)($claim['id'] ?? 0);
                                $status = (string)($claim['status'] ?? 'pending');
                                $status_class = admin_claim_status_class($status);
                                $claim_type = (string)($claim['claim_type'] ?? '');

                                $user_name = trim((string)($claim['user_name'] ?? 'Unknown User'));
                                $user_type = admin_claim_user_type_label((string)($claim['user_type'] ?? 'User'));

                                $profile_name = '';

                                if ($claim_type === 'doctor') {
                                    $profile_name = trim((string)($claim['doctor_name'] ?? ''));
                                }

                                if ($claim_type === 'hospital') {
                                    $profile_name = trim((string)($claim['hospital_name'] ?? ''));
                                }

                                if ($profile_name === '') {
                                    $profile_name = trim((string)($claim['profile_name'] ?? ''));
                                }

                                if ($profile_name === '') {
                                    $profile_name = 'Profile ID not found';
                                }

                                $profile_type_text = $claim_type === 'doctor'
                                    ? 'Doctor'
                                    : ($claim_type === 'hospital' ? 'Hospital' : 'Profile');

                                $profile_image = admin_claim_profile_image($claim_type, $claim);
                                $svg_fallback = admin_claim_svg_fallback($claim_type, $profile_name);

                                $date_text = !empty($claim['created_at'])
                                    ? date('d M Y h:i A', strtotime((string)$claim['created_at']))
                                    : '—';
                            ?>

                            <tr>
                                <td class="gh-sl">
                                    #<?= e((string)$sl) ?>
                                </td>

                                <td>
                                    <div class="gh-user">
                                        <strong><?= e($user_name) ?></strong>
                                        <span><?= e($user_type) ?></span>
                                    </div>
                                </td>

                                <td>
                                    <span class="gh-title-text">
                                        <?= e(admin_claim_type_label($claim_type)) ?>
                                    </span>
                                </td>

                                <td>
                                    <div class="gh-profile-mini">
                                        <img
                                            class="gh-profile-mini-img"
                                            src="<?= e($profile_image) ?>"
                                            alt="<?= e($profile_name) ?>"
                                            loading="lazy"
                                            onerror="this.onerror=null;this.src='<?= e($svg_fallback) ?>';"
                                        >

                                        <div>
                                            <strong><?= e($profile_name) ?></strong>
                                            <span><?= e($profile_type_text) ?></span>
                                        </div>
                                    </div>
                                </td>

                                <td>
                                    <span class="gh-badge <?= e($status_class) ?>">
                                        <?= e(ucfirst($status)) ?>
                                    </span>
                                </td>

                                <td>
                                    <span class="gh-sub-text"><?= e($date_text) ?></span>
                                </td>

                                <td>
                                    <div class="gh-actions">
                                        <a class="gh-btn" href="profile-claim-view.php?id=<?= e((string)$claim_id) ?>">
                                            View
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="gh-pagination">
                <div>
                    Page <?= e((string)$page) ?> of <?= e((string)$total_pages) ?>
                </div>

                <div class="gh-pages">
                    <?php
                        $query_base = [
                            'status' => $status_filter,
                            'type' => $type_filter,
                            'search' => $search,
                        ];
                    ?>

                    <?php if ($page > 1): ?>
                        <?php $prev_query = http_build_query(array_merge($query_base, ['page' => $page - 1])); ?>
                        <a class="gh-btn gh-btn-muted" href="profile-claims.php?<?= e($prev_query) ?>">Previous</a>
                    <?php endif; ?>

                    <?php if ($page < $total_pages): ?>
                        <?php $next_query = http_build_query(array_merge($query_base, ['page' => $page + 1])); ?>
                        <a class="gh-btn gh-btn-muted" href="profile-claims.php?<?= e($next_query) ?>">Next</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>