<?php
require_once __DIR__ . '/includes/header.php';

if (!table_exists('users')) {
    echo '<div class="gh-card" style="padding:20px;"><h2>Users table not found</h2><p>Please import the updated database first.</p></div>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

if (empty($_SESSION['admin_users_csrf'])) {
    $_SESSION['admin_users_csrf'] = bin2hex(random_bytes(32));
}

$csrf_token = $_SESSION['admin_users_csrf'];
$errors = [];
$success = '';

$search = trim((string)($_GET['search'] ?? ''));
$type_filter = trim((string)($_GET['user_type'] ?? ''));
$status_filter = trim((string)($_GET['status'] ?? ''));
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 20;

function admin_users_current_query(array $extra = []): string
{
    $query = [
        'search' => trim((string)($_GET['search'] ?? '')),
        'user_type' => trim((string)($_GET['user_type'] ?? '')),
        'status' => trim((string)($_GET['status'] ?? '')),
        'page' => max(1, (int)($_GET['page'] ?? 1)),
    ];

    foreach ($extra as $key => $value) {
        if ($value === null) {
            unset($query[$key]);
        } else {
            $query[$key] = $value;
        }
    }

    $query = array_filter($query, static function ($value) {
        return $value !== '' && $value !== null;
    });

    return http_build_query($query);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $posted_token = (string)($_POST['csrf_token'] ?? '');

    if (!hash_equals($csrf_token, $posted_token)) {
        $errors[] = 'Security token mismatch. Please try again.';
    } else {
        $action = (string)($_POST['action'] ?? '');
        $user_id = (int)($_POST['user_id'] ?? 0);

        if ($action === 'delete_user' && $user_id > 0) {
            try {
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = :id LIMIT 1");
                $stmt->execute([':id' => $user_id]);
                $success = 'User deleted successfully.';
            } catch (Throwable $e) {
                $errors[] = 'User could not be deleted.';
            }
        }

        if ($action === 'change_status' && $user_id > 0) {
            $status = (string)($_POST['status'] ?? '');

            if (in_array($status, ['active', 'blocked'], true)) {
                try {
                    $stmt = $pdo->prepare("UPDATE users SET status = :status, updated_at = NOW() WHERE id = :id");
                    $stmt->execute([
                        ':status' => $status,
                        ':id' => $user_id,
                    ]);

                    $success = $status === 'active'
                        ? 'User activated successfully.'
                        : 'User blocked successfully.';
                } catch (Throwable $e) {
                    $errors[] = 'User status could not be updated.';
                }
            } else {
                $errors[] = 'Invalid status.';
            }
        }
    }
}

if (isset($_GET['deleted'])) {
    $success = 'User deleted successfully.';
} elseif (isset($_GET['status_updated'])) {
    $success = 'User status updated successfully.';
} elseif (isset($_GET['updated'])) {
    $success = 'User updated successfully.';
}

$where = [];
$params = [];

if ($search !== '') {
    $where[] = "(u.name LIKE :search OR u.email LIKE :search OR u.phone LIKE :search)";
    $params[':search'] = '%' . $search . '%';
}

if (in_array($type_filter, ['doctor', 'hospital_owner'], true)) {
    $where[] = "u.user_type = :user_type";
    $params[':user_type'] = $type_filter;
}

if (in_array($status_filter, ['active', 'blocked'], true)) {
    $where[] = "u.status = :status";
    $params[':status'] = $status_filter;
}

$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$count_stmt = $pdo->prepare("SELECT COUNT(*) FROM users u {$where_sql}");
$count_stmt->execute($params);
$total_users = (int)$count_stmt->fetchColumn();

$total_pages = max(1, (int)ceil($total_users / $per_page));
$page = min($page, $total_pages);
$offset = ($page - 1) * $per_page;

$sql = "
    SELECT 
        u.*,
        d.name AS claimed_doctor_name,
        h.name AS claimed_hospital_name
    FROM users u
    LEFT JOIN doctors d ON d.id = u.claimed_doctor_id
    LEFT JOIN hospitals h ON h.id = u.claimed_hospital_id
    {$where_sql}
    ORDER BY u.id DESC
    LIMIT :limit OFFSET :offset
";

$stmt = $pdo->prepare($sql);

foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}

$stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();

$users = $stmt->fetchAll();

$total_all_users = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$total_active = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE status = 'active'")->fetchColumn();
$total_blocked = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE status = 'blocked'")->fetchColumn();
$total_doctors = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE user_type = 'doctor'")->fetchColumn();
$total_hospitals = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE user_type = 'hospital_owner'")->fetchColumn();
$total_connected = 0;

try {
    $total_connected = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE claimed_doctor_id IS NOT NULL OR claimed_hospital_id IS NOT NULL")->fetchColumn();
} catch (Throwable $e) {
    $total_connected = 0;
}

$active_filter_count = 0;
if ($search !== '') {
    $active_filter_count++;
}
if ($type_filter !== '') {
    $active_filter_count++;
}
if ($status_filter !== '') {
    $active_filter_count++;
}
?>

<style>
    :root {
        --uv-bg: #f6f8fa;
        --uv-card: #ffffff;
        --uv-border: #d0d7de;
        --uv-border-soft: #d8dee4;
        --uv-text: #24292f;
        --uv-muted: #57606a;
        --uv-blue: #0969da;
        --uv-green: #1a7f37;
        --uv-green-bg: #dafbe1;
        --uv-red: #cf222e;
        --uv-red-bg: #ffebe9;
        --uv-yellow: #9a6700;
        --uv-yellow-bg: #fff8c5;
        --uv-shadow: 0 1px 0 rgba(27, 31, 36, 0.04);
    }

    .users-page-shell {
        color: var(--uv-text);
    }

    .users-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 16px;
        flex-wrap: wrap;
        margin-bottom: 16px;
        padding-bottom: 16px;
        border-bottom: 1px solid var(--uv-border);
    }

    .users-header h2 {
        margin: 0 0 6px;
        color: var(--uv-text);
        font-size: 24px;
        line-height: 1.25;
        font-weight: 700;
        letter-spacing: -0.02em;
    }

    .users-header p {
        margin: 0;
        color: var(--uv-muted);
        font-size: 14px;
    }

    .users-header-actions {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        align-items: center;
    }

    .users-btn,
    .users-filter button,
    .users-actions button,
    .users-actions a {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        min-height: 32px;
        padding: 5px 12px;
        border-radius: 6px;
        border: 1px solid rgba(27, 31, 36, 0.15);
        background: #f6f8fa;
        color: var(--uv-text);
        font-size: 13px;
        font-weight: 600;
        line-height: 20px;
        text-decoration: none;
        cursor: pointer;
        box-shadow: var(--uv-shadow);
    }

    .users-btn:hover,
    .users-actions a:hover,
    .users-actions button:hover,
    .users-filter button:hover {
        background: #eef1f4;
        text-decoration: none;
    }

    .users-btn.primary,
    .users-filter button.primary {
        background: #2da44e;
        color: #ffffff;
        border-color: rgba(27, 31, 36, 0.15);
    }

    .users-btn.primary:hover,
    .users-filter button.primary:hover {
        background: #1f883d;
    }

    .users-overview {
        display: grid;
        grid-template-columns: repeat(5, minmax(130px, 1fr));
        gap: 8px;
        margin-bottom: 16px;
    }

    .users-stat {
        background: var(--uv-card);
        border: 1px solid var(--uv-border);
        border-radius: 6px;
        padding: 14px;
        box-shadow: var(--uv-shadow);
    }

    .users-stat span {
        display: block;
        color: var(--uv-muted);
        font-size: 12px;
        font-weight: 600;
        margin-bottom: 8px;
    }

    .users-stat strong {
        display: block;
        color: var(--uv-text);
        font-size: 24px;
        line-height: 1;
        font-weight: 700;
    }

    .users-tabs {
        display: flex;
        gap: 6px;
        flex-wrap: wrap;
        margin-bottom: 12px;
        border-bottom: 1px solid var(--uv-border);
    }

    .users-tab {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        padding: 8px 12px;
        margin-bottom: -1px;
        color: var(--uv-muted);
        text-decoration: none;
        font-size: 14px;
        font-weight: 600;
        border: 1px solid transparent;
        border-radius: 6px 6px 0 0;
    }

    .users-tab:hover {
        color: var(--uv-text);
        text-decoration: none;
    }

    .users-tab.active {
        color: var(--uv-text);
        background: var(--uv-card);
        border-color: var(--uv-border) var(--uv-border) var(--uv-card);
    }

    .users-tab .count {
        min-width: 20px;
        padding: 1px 6px;
        border-radius: 999px;
        background: #eaeef2;
        color: var(--uv-muted);
        font-size: 12px;
        text-align: center;
    }

    .users-filter {
        display: grid;
        grid-template-columns: 2fr 1fr 1fr auto auto;
        gap: 8px;
        align-items: center;
        margin-bottom: 16px;
        padding: 12px;
        background: var(--uv-card);
        border: 1px solid var(--uv-border);
        border-radius: 6px;
        box-shadow: var(--uv-shadow);
    }

    .users-filter input,
    .users-filter select {
        width: 100%;
        min-height: 34px;
        padding: 6px 10px;
        border: 1px solid var(--uv-border);
        border-radius: 6px;
        background: var(--uv-card);
        color: var(--uv-text);
        font-size: 14px;
        outline: none;
    }

    .users-filter input:focus,
    .users-filter select:focus {
        border-color: var(--uv-blue);
        box-shadow: 0 0 0 3px rgba(9, 105, 218, 0.15);
    }

    .users-alert {
        padding: 12px 14px;
        border-radius: 6px;
        margin-bottom: 16px;
        font-size: 14px;
        border: 1px solid transparent;
    }

    .users-alert.success {
        background: var(--uv-green-bg);
        color: var(--uv-green);
        border-color: rgba(26, 127, 55, 0.25);
    }

    .users-alert.error {
        background: var(--uv-red-bg);
        color: var(--uv-red);
        border-color: rgba(207, 34, 46, 0.25);
    }

    .users-table-card {
        background: var(--uv-card);
        border: 1px solid var(--uv-border);
        border-radius: 6px;
        overflow: hidden;
        box-shadow: var(--uv-shadow);
    }

    .users-table-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        flex-wrap: wrap;
        padding: 12px 16px;
        background: #f6f8fa;
        border-bottom: 1px solid var(--uv-border);
    }

    .users-table-title {
        margin: 0;
        font-size: 14px;
        font-weight: 700;
        color: var(--uv-text);
    }

    .users-table-note {
        margin: 0;
        font-size: 12px;
        color: var(--uv-muted);
    }

    .users-table-scroll {
        overflow-x: auto;
    }

    .users-table-card table {
        width: 100%;
        margin: 0;
        border-collapse: collapse;
        border: 0;
        box-shadow: none;
        border-radius: 0;
        background: var(--uv-card);
    }

    .users-table-card th,
    .users-table-card td {
        padding: 12px 16px;
        border-bottom: 1px solid var(--uv-border-soft);
        vertical-align: middle;
        text-align: left;
        font-size: 13px;
    }

    .users-table-card th {
        background: #f6f8fa;
        color: var(--uv-muted);
        font-size: 12px;
        font-weight: 600;
        text-transform: none;
        letter-spacing: 0;
        white-space: nowrap;
    }

    .users-table-card tr:hover td {
        background: #f6f8fa;
    }

    .users-table-card tr:last-child td {
        border-bottom: 0;
    }

    .user-identity {
        display: flex;
        align-items: center;
        gap: 10px;
        min-width: 230px;
    }

    .user-avatar {
        width: 36px;
        height: 36px;
        flex: 0 0 36px;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: linear-gradient(135deg, #ddf4ff, #dbeafe);
        color: #0969da;
        border: 1px solid #b6e3ff;
        font-weight: 700;
        font-size: 14px;
        text-transform: uppercase;
    }

    .user-identity strong {
        display: block;
        color: var(--uv-text);
        font-size: 14px;
        line-height: 1.3;
    }

    .user-identity small,
    .users-muted {
        display: block;
        color: var(--uv-muted);
        font-size: 12px;
        line-height: 1.5;
    }

    .users-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        min-height: 22px;
        padding: 1px 8px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 600;
        background: #f6f8fa;
        color: var(--uv-muted);
        border: 1px solid var(--uv-border);
        white-space: nowrap;
    }

    .users-badge.active {
        background: var(--uv-green-bg);
        color: var(--uv-green);
        border-color: rgba(26, 127, 55, 0.25);
    }

    .users-badge.blocked {
        background: var(--uv-red-bg);
        color: var(--uv-red);
        border-color: rgba(207, 34, 46, 0.25);
    }

    .users-badge.connected {
        background: #ddf4ff;
        color: #0969da;
        border-color: #b6e3ff;
    }

    .users-badge.empty {
        background: var(--uv-yellow-bg);
        color: var(--uv-yellow);
        border-color: rgba(154, 103, 0, 0.25);
    }

    .users-actions {
        display: flex;
        gap: 6px;
        flex-wrap: wrap;
        align-items: center;
        min-width: 220px;
    }

    .users-actions form {
        padding: 0;
        margin: 0;
        border: 0;
        background: transparent;
        box-shadow: none;
    }

    .users-actions .danger {
        background: var(--uv-red-bg);
        color: var(--uv-red);
        border-color: rgba(207, 34, 46, 0.25);
    }

    .users-actions .danger:hover {
        background: #ffd7d5;
    }

    .users-actions .warning {
        background: var(--uv-yellow-bg);
        color: var(--uv-yellow);
        border-color: rgba(154, 103, 0, 0.25);
    }

    .users-actions .success {
        background: var(--uv-green-bg);
        color: var(--uv-green);
        border-color: rgba(26, 127, 55, 0.25);
    }

    .users-empty {
        padding: 34px 20px;
        text-align: center;
        color: var(--uv-muted);
    }

    .users-empty strong {
        display: block;
        margin-bottom: 6px;
        color: var(--uv-text);
        font-size: 16px;
    }

    .users-pagination {
        display: flex;
        gap: 6px;
        align-items: center;
        justify-content: flex-end;
        margin-top: 16px;
        flex-wrap: wrap;
    }

    .users-pagination a,
    .users-pagination span {
        min-width: 32px;
        min-height: 32px;
        padding: 5px 10px;
        border-radius: 6px;
        background: var(--uv-card);
        border: 1px solid var(--uv-border);
        text-decoration: none;
        color: var(--uv-text);
        font-size: 13px;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }

    .users-pagination a:hover {
        background: #f6f8fa;
        text-decoration: none;
    }

    .users-pagination span {
        background: var(--uv-blue);
        color: #ffffff;
        border-color: var(--uv-blue);
    }

    @media(max-width: 1100px) {
        .users-overview {
            grid-template-columns: repeat(3, 1fr);
        }

        .users-filter {
            grid-template-columns: 1fr 1fr;
        }
    }

    @media(max-width: 700px) {
        .users-overview,
        .users-filter {
            grid-template-columns: 1fr;
        }

        .users-header {
            align-items: stretch;
        }

        .users-header-actions,
        .users-header-actions .users-btn,
        .users-filter .users-btn,
        .users-filter button {
            width: 100%;
        }

        .users-tabs {
            overflow-x: auto;
            flex-wrap: nowrap;
        }

        .users-tab {
            white-space: nowrap;
        }
    }
</style>

<div class="users-page-shell">
    <div class="users-header">
        <div>
            <h2>Manage Users</h2>
            <p>Search, review, connect, block, activate, edit and delete registered users.</p>
        </div>

        <div class="users-header-actions">
            <a href="users.php" class="users-btn">Refresh</a>
            <a href="users.php?status=active" class="users-btn">Active</a>
            <a href="users.php?status=blocked" class="users-btn">Blocked</a>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="users-alert success"><?= e($success) ?></div>
    <?php endif; ?>

    <?php if ($errors): ?>
        <div class="users-alert error">
            <?php foreach ($errors as $error): ?>
                <div><?= e($error) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="users-overview">
        <div class="users-stat">
            <span>Total Users</span>
            <strong><?= number_format($total_all_users) ?></strong>
        </div>

        <div class="users-stat">
            <span>Active Users</span>
            <strong><?= number_format($total_active) ?></strong>
        </div>

        <div class="users-stat">
            <span>Blocked Users</span>
            <strong><?= number_format($total_blocked) ?></strong>
        </div>

        <div class="users-stat">
            <span>Doctor Users</span>
            <strong><?= number_format($total_doctors) ?></strong>
        </div>

        <div class="users-stat">
            <span>Connected Profiles</span>
            <strong><?= number_format($total_connected) ?></strong>
        </div>
    </div>

    <div class="users-tabs">
        <a class="users-tab <?= $status_filter === '' ? 'active' : '' ?>" href="users.php">
            All <span class="count"><?= number_format($total_all_users) ?></span>
        </a>
        <a class="users-tab <?= $status_filter === 'active' ? 'active' : '' ?>" href="users.php?status=active">
            Active <span class="count"><?= number_format($total_active) ?></span>
        </a>
        <a class="users-tab <?= $status_filter === 'blocked' ? 'active' : '' ?>" href="users.php?status=blocked">
            Blocked <span class="count"><?= number_format($total_blocked) ?></span>
        </a>
        <a class="users-tab <?= $type_filter === 'doctor' ? 'active' : '' ?>" href="users.php?user_type=doctor">
            Doctors <span class="count"><?= number_format($total_doctors) ?></span>
        </a>
        <a class="users-tab <?= $type_filter === 'hospital_owner' ? 'active' : '' ?>" href="users.php?user_type=hospital_owner">
            Hospital Owners <span class="count"><?= number_format($total_hospitals) ?></span>
        </a>
    </div>

    <form method="get" class="users-filter">
        <input type="text" name="search" value="<?= e($search) ?>" placeholder="Search by name, email or phone">

        <select name="user_type">
            <option value="">All Types</option>
            <option value="doctor" <?= $type_filter === 'doctor' ? 'selected' : '' ?>>Doctor</option>
            <option value="hospital_owner" <?= $type_filter === 'hospital_owner' ? 'selected' : '' ?>>Hospital Owner</option>
        </select>

        <select name="status">
            <option value="">All Status</option>
            <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>Active</option>
            <option value="blocked" <?= $status_filter === 'blocked' ? 'selected' : '' ?>>Blocked</option>
        </select>

        <button type="submit" class="primary">Filter</button>
        <a href="users.php" class="users-btn">Clear</a>
    </form>

    <div class="users-table-card">
        <div class="users-table-header">
            <div>
                <h3 class="users-table-title">User List</h3>
                <p class="users-table-note">
                    Showing <?= number_format(count($users)) ?> of <?= number_format($total_users) ?> matched users<?= $active_filter_count ? ' with ' . e((string)$active_filter_count) . ' active filter(s)' : '' ?>.
                </p>
            </div>
        </div>

        <div class="users-table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>User</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Claimed Profile</th>
                        <th>Joined</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody>
                    <?php if (!$users): ?>
                        <tr>
                            <td colspan="7">
                                <div class="users-empty">
                                    <strong>No users found</strong>
                                    Try changing the search keyword or clearing the selected filters.
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>

                    <?php foreach ($users as $user): ?>
                        <?php
                        $claimed_profile = 'Not connected';
                        $claimed_profile_class = 'empty';

                        if (!empty($user['claimed_doctor_name'])) {
                            $claimed_profile = 'Doctor: ' . $user['claimed_doctor_name'];
                            $claimed_profile_class = 'connected';
                        } elseif (!empty($user['claimed_hospital_name'])) {
                            $claimed_profile = 'Hospital: ' . $user['claimed_hospital_name'];
                            $claimed_profile_class = 'connected';
                        }

                        $name = trim((string)($user['name'] ?? 'User'));
                        $initial = mb_substr($name !== '' ? $name : 'U', 0, 1);
                        ?>

                        <tr>
                            <td>#<?= e((string)$user['id']) ?></td>

                            <td>
                                <div class="user-identity">
                                    <div class="user-avatar"><?= e($initial) ?></div>
                                    <div>
                                        <strong><?= e($name) ?></strong>
                                        <small><?= e($user['email']) ?></small>

                                        <?php if (!empty($user['phone'])): ?>
                                            <small><?= e($user['phone']) ?></small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>

                            <td>
                                <span class="users-badge">
                                    <?= e($user['user_type'] === 'hospital_owner' ? 'Hospital Owner' : 'Doctor') ?>
                                </span>
                            </td>

                            <td>
                                <span class="users-badge <?= e($user['status']) ?>">
                                    <?= e(ucfirst((string)$user['status'])) ?>
                                </span>
                            </td>

                            <td>
                                <span class="users-badge <?= e($claimed_profile_class) ?>">
                                    <?= e($claimed_profile) ?>
                                </span>
                            </td>

                            <td>
                                <span class="users-muted">
                                    <?= !empty($user['created_at']) ? e(date('d M Y', strtotime((string)$user['created_at']))) : '—' ?>
                                </span>
                            </td>

                            <td>
                                <div class="users-actions">
                                    <a href="user-view.php?id=<?= e((string)$user['id']) ?>">View</a>
                                    <a href="user-edit.php?id=<?= e((string)$user['id']) ?>">Edit</a>

                                    <form method="post">
                                        <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">
                                        <input type="hidden" name="action" value="change_status">
                                        <input type="hidden" name="user_id" value="<?= e((string)$user['id']) ?>">
                                        <input type="hidden" name="status" value="<?= $user['status'] === 'active' ? 'blocked' : 'active' ?>">

                                        <button type="submit" class="<?= $user['status'] === 'active' ? 'warning' : 'success' ?>">
                                            <?= $user['status'] === 'active' ? 'Block' : 'Activate' ?>
                                        </button>
                                    </form>

                                    <form method="post" onsubmit="return confirm('Are you sure you want to delete this user?');">
                                        <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">
                                        <input type="hidden" name="action" value="delete_user">
                                        <input type="hidden" name="user_id" value="<?= e((string)$user['id']) ?>">

                                        <button type="submit" class="danger">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if ($total_pages > 1): ?>
        <div class="users-pagination">
            <?php if ($page > 1): ?>
                <a href="users.php?<?= e(admin_users_current_query(['page' => $page - 1])) ?>">Previous</a>
            <?php endif; ?>

            <?php
            $start_page = max(1, $page - 2);
            $end_page = min($total_pages, $page + 2);
            ?>

            <?php if ($start_page > 1): ?>
                <a href="users.php?<?= e(admin_users_current_query(['page' => 1])) ?>">1</a>
                <?php if ($start_page > 2): ?>
                    <span>...</span>
                <?php endif; ?>
            <?php endif; ?>

            <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                <?php if ($i === $page): ?>
                    <span><?= e((string)$i) ?></span>
                <?php else: ?>
                    <a href="users.php?<?= e(admin_users_current_query(['page' => $i])) ?>">
                        <?= e((string)$i) ?>
                    </a>
                <?php endif; ?>
            <?php endfor; ?>

            <?php if ($end_page < $total_pages): ?>
                <?php if ($end_page < $total_pages - 1): ?>
                    <span>...</span>
                <?php endif; ?>
                <a href="users.php?<?= e(admin_users_current_query(['page' => $total_pages])) ?>"><?= e((string)$total_pages) ?></a>
            <?php endif; ?>

            <?php if ($page < $total_pages): ?>
                <a href="users.php?<?= e(admin_users_current_query(['page' => $page + 1])) ?>">Next</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
