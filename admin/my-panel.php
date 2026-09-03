<?php
require_once __DIR__ . '/includes/header.php';

/*
|--------------------------------------------------------------------------
| Admin My Panel
|--------------------------------------------------------------------------
| File path: admin/my-panel.php
|--------------------------------------------------------------------------
*/

if (!function_exists('admin_my_table_exists')) {
    function admin_my_table_exists(string $table): bool
    {
        if (function_exists('table_exists')) {
            return table_exists($table);
        }

        global $pdo;

        try {
            $stmt = $pdo->prepare("SHOW TABLES LIKE :table");
            $stmt->execute([':table' => $table]);
            return (bool)$stmt->fetchColumn();
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('admin_my_column_exists')) {
    function admin_my_column_exists(string $table, string $column): bool
    {
        if (function_exists('column_exists')) {
            return column_exists($table, $column);
        }

        global $pdo;

        try {
            if (!admin_my_table_exists($table)) {
                return false;
            }

            $stmt = $pdo->prepare("SHOW COLUMNS FROM `{$table}` LIKE :column");
            $stmt->execute([':column' => $column]);

            return (bool)$stmt->fetchColumn();
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('admin_my_count')) {
    function admin_my_count(string $table, string $where = '', array $params = []): int
    {
        global $pdo;

        try {
            if (!admin_my_table_exists($table)) {
                return 0;
            }

            $sql = "SELECT COUNT(*) FROM `{$table}`";

            if ($where !== '') {
                $sql .= " WHERE {$where}";
            }

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            return (int)$stmt->fetchColumn();
        } catch (Throwable $e) {
            return 0;
        }
    }
}

if (!function_exists('admin_my_log')) {
    function admin_my_log(string $action, string $description = ''): void
    {
        if (function_exists('admin_log_activity')) {
            admin_log_activity($action, $description);
        }
    }
}

/*
|--------------------------------------------------------------------------
| Current Admin Data
|--------------------------------------------------------------------------
*/

$admin_id = (int)($_SESSION['admin_id'] ?? 0);
$admin_name = trim((string)($_SESSION['admin_name'] ?? 'Administrator'));
$admin_email = trim((string)($_SESSION['admin_email'] ?? ''));
$admin_role = trim((string)($_SESSION['admin_role'] ?? 'super_admin'));
$admin_permissions = $_SESSION['admin_permissions'] ?? [];

if (is_string($admin_permissions)) {
    $decoded_permissions = json_decode($admin_permissions, true);
    $admin_permissions = is_array($decoded_permissions) ? $decoded_permissions : [];
}

if (!is_array($admin_permissions)) {
    $admin_permissions = [];
}

$errors = [];
$success = '';

if (empty($_SESSION['admin_my_panel_csrf'])) {
    $_SESSION['admin_my_panel_csrf'] = bin2hex(random_bytes(32));
}

$csrf_token = $_SESSION['admin_my_panel_csrf'];

$admin_users_available = admin_my_table_exists('admin_users');

$admin = null;

try {
    if ($admin_id > 0 && $admin_users_available) {
        $stmt = $pdo->prepare("SELECT * FROM admin_users WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $admin_id]);
        $admin = $stmt->fetch() ?: null;
    }
} catch (Throwable $e) {
    $admin = null;
}

if (!$admin) {
    $admin = [
        'id' => $admin_id,
        'name' => $admin_name,
        'email' => $admin_email,
        'role' => $admin_role,
        'status' => 'active',
        'permissions' => json_encode($admin_permissions),
        'last_login_at' => '',
        'created_at' => '',
    ];
}

if ($admin) {
    $admin_name = trim((string)($admin['name'] ?? $admin_name));
    $admin_email = trim((string)($admin['email'] ?? $admin_email));
    $admin_role = trim((string)($admin['role'] ?? $admin_role));

    if (!empty($admin['permissions'])) {
        $decoded = json_decode((string)$admin['permissions'], true);
        $admin_permissions = is_array($decoded) ? $decoded : $admin_permissions;
    }
}

/*
|--------------------------------------------------------------------------
| Section Routing
|--------------------------------------------------------------------------
*/

$section = trim((string)($_GET['section'] ?? 'overview'));

if (!in_array($section, ['overview', 'profile', 'password', 'permissions', 'activity'], true)) {
    $section = 'overview';
}

/*
|--------------------------------------------------------------------------
| Handle Update Profile / Password
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $posted_token = (string)($_POST['csrf_token'] ?? '');

    if (!hash_equals($csrf_token, $posted_token)) {
        $errors[] = 'Security token mismatch. Please try again.';
    } elseif (!$admin_users_available || $admin_id <= 0) {
        $errors[] = 'Profile update requires admin_users table login system.';
    } else {
        $action = (string)($_POST['action'] ?? '');

        if ($action === 'update_profile') {
            $section = 'profile';

            $new_name = trim((string)($_POST['name'] ?? ''));
            $new_email = trim((string)($_POST['email'] ?? ''));

            if ($new_name === '') {
                $errors[] = 'Name is required.';
            }

            if ($new_email === '' || !filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Valid email is required.';
            }

            if (!$errors) {
                try {
                    $check = $pdo->prepare("SELECT id FROM admin_users WHERE email = :email AND id != :id LIMIT 1");
                    $check->execute([
                        ':email' => $new_email,
                        ':id' => $admin_id,
                    ]);

                    if ($check->fetch()) {
                        $errors[] = 'This email is already used by another admin.';
                    }
                } catch (Throwable $e) {
                    $errors[] = 'Could not verify email.';
                }
            }

            if (!$errors) {
                try {
                    $stmt = $pdo->prepare("
                        UPDATE admin_users
                        SET name = :name,
                            email = :email,
                            updated_at = NOW()
                        WHERE id = :id
                        LIMIT 1
                    ");

                    $stmt->execute([
                        ':name' => $new_name,
                        ':email' => $new_email,
                        ':id' => $admin_id,
                    ]);

                    $_SESSION['admin_name'] = $new_name;
                    $_SESSION['admin_email'] = $new_email;

                    admin_my_log('admin.profile_updated', 'Admin updated own profile.');

                    redirect('my-panel.php?section=profile&updated=1');
                } catch (Throwable $e) {
                    $errors[] = 'Profile could not be updated.';
                }
            }
        }

        if ($action === 'change_password') {
            $section = 'password';

            $current_password = (string)($_POST['current_password'] ?? '');
            $new_password = (string)($_POST['new_password'] ?? '');
            $confirm_password = (string)($_POST['confirm_password'] ?? '');

            if ($current_password === '') {
                $errors[] = 'Current password is required.';
            }

            if (strlen($new_password) < 8) {
                $errors[] = 'New password must be at least 8 characters.';
            }

            if ($new_password !== $confirm_password) {
                $errors[] = 'Confirm password does not match.';
            }

            if (!$errors) {
                try {
                    $stmt = $pdo->prepare("SELECT password FROM admin_users WHERE id = :id LIMIT 1");
                    $stmt->execute([':id' => $admin_id]);
                    $current_hash = (string)$stmt->fetchColumn();

                    if (!$current_hash || !password_verify($current_password, $current_hash)) {
                        $errors[] = 'Current password is incorrect.';
                    }
                } catch (Throwable $e) {
                    $errors[] = 'Could not verify current password.';
                }
            }

            if (!$errors) {
                try {
                    $stmt = $pdo->prepare("
                        UPDATE admin_users
                        SET password = :password,
                            updated_at = NOW()
                        WHERE id = :id
                        LIMIT 1
                    ");

                    $stmt->execute([
                        ':password' => password_hash($new_password, PASSWORD_DEFAULT),
                        ':id' => $admin_id,
                    ]);

                    admin_my_log('admin.password_changed', 'Admin changed own password.');

                    redirect('my-panel.php?section=password&password_updated=1');
                } catch (Throwable $e) {
                    $errors[] = 'Password could not be changed.';
                }
            }
        }
    }
}

if (isset($_GET['updated'])) {
    $success = 'Profile updated successfully.';
}

if (isset($_GET['password_updated'])) {
    $success = 'Password changed successfully.';
}

/*
|--------------------------------------------------------------------------
| Refresh Admin Data
|--------------------------------------------------------------------------
*/

try {
    if ($admin_id > 0 && $admin_users_available) {
        $stmt = $pdo->prepare("SELECT * FROM admin_users WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $admin_id]);
        $fresh_admin = $stmt->fetch();

        if ($fresh_admin) {
            $admin = $fresh_admin;
            $admin_name = trim((string)($admin['name'] ?? $admin_name));
            $admin_email = trim((string)($admin['email'] ?? $admin_email));
            $admin_role = trim((string)($admin['role'] ?? $admin_role));
        }
    }
} catch (Throwable $e) {
    // Ignore refresh error.
}

/*
|--------------------------------------------------------------------------
| Summary Data
|--------------------------------------------------------------------------
*/

$role_labels = [
    'super_admin' => 'Super Admin',
    'admin'       => 'Admin',
    'moderator'   => 'Moderator',
    'editor'      => 'Editor',
    'support'     => 'Support',
];

$role_label = $role_labels[$admin_role] ?? ucwords(str_replace('_', ' ', $admin_role));

$total_doctors = admin_my_count('doctors');
$total_hospitals = admin_my_count('hospitals');
$total_users = admin_my_count('users');
$total_reviews = admin_my_count('reviews');
$total_moderators = admin_my_count('admin_users');

$pending_requests = 0;
$pending_claims = 0;

if (admin_my_column_exists('profile_update_requests', 'status')) {
    $pending_requests = admin_my_count('profile_update_requests', 'status = :status', [':status' => 'pending']);
}

if (admin_my_column_exists('profile_claims', 'status')) {
    $pending_claims = admin_my_count('profile_claims', 'status = :status', [':status' => 'pending']);
}

$activity_logs = [];

try {
    if ($admin_id > 0 && admin_my_table_exists('admin_activity_logs')) {
        $stmt = $pdo->prepare("
            SELECT *
            FROM admin_activity_logs
            WHERE admin_id = :admin_id
            ORDER BY id DESC
            LIMIT 10
        ");

        $stmt->execute([':admin_id' => $admin_id]);
        $activity_logs = $stmt->fetchAll();
    }
} catch (Throwable $e) {
    $activity_logs = [];
}

$permission_count = count($admin_permissions);

if ($admin_role === 'super_admin' && function_exists('admin_permissions_list')) {
    $permission_count = count(admin_permissions_list());
}

$last_login = $admin['last_login_at'] ?? '';
$created_at = $admin['created_at'] ?? '';
?>

<style>
    .my-admin-panel {
        color: #24292f;
    }

    .map-hero {
        display: grid;
        grid-template-columns: 1fr 340px;
        gap: 16px;
        margin-bottom: 16px;
    }

    .map-card {
        background: #ffffff;
        border: 1px solid #d0d7de;
        border-radius: 10px;
        box-shadow: 0 1px 0 rgba(27, 31, 36, 0.04);
        overflow: hidden;
    }

    .map-welcome {
        padding: 22px;
    }

    .map-user-row {
        display: flex;
        align-items: center;
        gap: 14px;
    }

    .map-avatar {
        width: 66px;
        height: 66px;
        border-radius: 50%;
        background: linear-gradient(135deg, #2da44e, #0969da);
        color: #ffffff;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 25px;
        font-weight: 800;
        flex: 0 0 66px;
    }

    .map-welcome h1 {
        margin: 0 0 6px;
        font-size: 28px;
        line-height: 1.15;
        letter-spacing: -0.04em;
        color: #24292f;
    }

    .map-welcome p {
        margin: 0;
        color: #57606a;
        font-size: 14px;
        line-height: 1.6;
    }

    .map-actions,
    .map-tabs {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        margin-top: 18px;
    }

    .map-btn,
    .map-tab {
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
        text-decoration: none;
        cursor: pointer;
        box-shadow: 0 1px 0 rgba(27, 31, 36, 0.04);
    }

    .map-btn:hover,
    .map-tab:hover {
        background: #eef1f4;
        color: #24292f;
        text-decoration: none;
    }

    .map-btn-primary,
    .map-tab.active {
        background: #2da44e;
        color: #ffffff;
    }

    .map-btn-primary:hover,
    .map-tab.active:hover {
        background: #1f883d;
        color: #ffffff;
    }

    .map-alert {
        padding: 12px 14px;
        border-radius: 8px;
        margin-bottom: 16px;
        font-size: 14px;
        border: 1px solid transparent;
    }

    .map-alert.success {
        background: #dafbe1;
        color: #1a7f37;
        border-color: rgba(26, 127, 55, 0.25);
    }

    .map-alert.error {
        background: #ffebe9;
        color: #cf222e;
        border-color: rgba(207, 34, 46, 0.25);
    }

    .map-profile-card {
        padding: 16px;
    }

    .map-profile-card h3 {
        margin: 0 0 12px;
        font-size: 15px;
        color: #24292f;
    }

    .map-list {
        display: grid;
        gap: 10px;
    }

    .map-list-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 11px 12px;
        border: 1px solid #d8dee4;
        border-radius: 8px;
        background: #f6f8fa;
    }

    .map-list-row span {
        color: #57606a;
        font-size: 13px;
    }

    .map-list-row strong {
        color: #24292f;
        font-size: 13px;
        text-align: right;
    }

    .map-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 22px;
        padding: 3px 8px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 700;
        border: 1px solid #d8dee4;
        background: #f6f8fa;
        color: #57606a;
        white-space: nowrap;
    }

    .map-badge.green {
        background: #dafbe1;
        color: #1a7f37;
        border-color: rgba(26, 127, 55, 0.25);
    }

    .map-badge.yellow {
        background: #fff8c5;
        color: #9a6700;
        border-color: rgba(154, 103, 0, 0.25);
    }

    .map-stats {
        display: grid;
        grid-template-columns: repeat(4, minmax(150px, 1fr));
        gap: 12px;
        margin-bottom: 16px;
    }

    .map-stat {
        padding: 15px;
        background: #ffffff;
        border: 1px solid #d0d7de;
        border-radius: 10px;
    }

    .map-stat strong {
        display: block;
        font-size: 26px;
        line-height: 1;
        margin-bottom: 6px;
        color: #24292f;
    }

    .map-stat span {
        color: #57606a;
        font-size: 13px;
        font-weight: 600;
    }

    .map-layout {
        display: grid;
        grid-template-columns: 1fr 360px;
        gap: 16px;
        align-items: start;
    }

    .map-stack {
        display: grid;
        gap: 16px;
    }

    .map-card h2,
    .map-section-head h2 {
        margin: 0;
        font-size: 15px;
        color: #24292f;
    }

    .map-section-head {
        padding: 14px 16px;
        background: #f6f8fa;
        border-bottom: 1px solid #d0d7de;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        flex-wrap: wrap;
    }

    .map-section-head span {
        color: #57606a;
        font-size: 13px;
    }

    .map-section-body {
        padding: 16px;
    }

    .map-form-grid {
        display: grid;
        gap: 12px;
    }

    .map-field {
        display: grid;
        gap: 7px;
    }

    .map-field label {
        font-size: 13px;
        font-weight: 700;
        color: #24292f;
    }

    .map-field input {
        width: 100%;
        min-height: 38px;
        border: 1px solid #d0d7de;
        border-radius: 6px;
        padding: 8px 10px;
        color: #24292f;
        background: #ffffff;
        outline: none;
        font-family: inherit;
    }

    .map-field input:focus {
        border-color: #0969da;
        box-shadow: 0 0 0 3px rgba(9, 105, 218, 0.15);
    }

    .map-shortcuts {
        display: grid;
        grid-template-columns: repeat(2, minmax(180px, 1fr));
        gap: 10px;
    }

    .map-shortcut {
        display: block;
        padding: 14px;
        border: 1px solid #d8dee4;
        border-radius: 8px;
        background: #ffffff;
        color: #24292f;
        text-decoration: none;
    }

    .map-shortcut:hover {
        background: #f6f8fa;
        text-decoration: none;
    }

    .map-shortcut strong {
        display: block;
        font-size: 14px;
        margin-bottom: 5px;
        color: #24292f;
    }

    .map-shortcut small {
        display: block;
        color: #57606a;
        font-size: 12px;
        line-height: 1.5;
    }

    .map-log {
        display: grid;
        gap: 10px;
    }

    .map-log-item {
        padding: 12px;
        border: 1px solid #d8dee4;
        border-radius: 8px;
        background: #ffffff;
    }

    .map-log-item strong {
        display: block;
        color: #24292f;
        font-size: 13px;
        margin-bottom: 4px;
    }

    .map-log-item small {
        display: block;
        color: #57606a;
        font-size: 12px;
        line-height: 1.5;
    }

    .map-empty {
        padding: 22px;
        text-align: center;
        color: #57606a;
        font-size: 14px;
        background: #f6f8fa;
        border: 1px dashed #d0d7de;
        border-radius: 8px;
    }

    @media(max-width: 1100px) {
        .map-hero,
        .map-layout {
            grid-template-columns: 1fr;
        }

        .map-stats {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    @media(max-width: 700px) {
        .map-welcome,
        .map-section-body,
        .map-profile-card {
            padding: 14px;
        }

        .map-user-row {
            align-items: flex-start;
        }

        .map-stats,
        .map-shortcuts {
            grid-template-columns: 1fr;
        }

        .map-actions .map-btn,
        .map-tabs .map-tab,
        .map-section-body .map-btn {
            width: 100%;
        }
    }
</style>

<div class="my-admin-panel">

    <?php if ($success): ?>
        <div class="map-alert success"><?= e($success) ?></div>
    <?php endif; ?>

    <?php if ($errors): ?>
        <div class="map-alert error">
            <?php foreach ($errors as $error): ?>
                <div><?= e($error) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <section class="map-hero">
        <div class="map-card map-welcome">
            <div class="map-user-row">
                <div class="map-avatar"><?= e(strtoupper(substr($admin_name, 0, 1))) ?></div>

                <div>
                    <h1>Welcome, <?= e($admin_name) ?></h1>
                    <p>
                        Manage your own admin profile, password, permissions and activity from one secure personal panel.
                    </p>
                </div>
            </div>

            <div class="map-tabs">
                <a href="my-panel.php" class="map-tab <?= $section === 'overview' ? 'active' : '' ?>">Overview</a>
                <a href="my-panel.php?section=profile" class="map-tab <?= $section === 'profile' ? 'active' : '' ?>">Update Profile</a>
                <a href="my-panel.php?section=password" class="map-tab <?= $section === 'password' ? 'active' : '' ?>">Change Password</a>
                <a href="my-panel.php?section=permissions" class="map-tab <?= $section === 'permissions' ? 'active' : '' ?>">Permissions</a>
                <a href="my-panel.php?section=activity" class="map-tab <?= $section === 'activity' ? 'active' : '' ?>">Activity</a>
            </div>
        </div>

        <div class="map-card map-profile-card">
            <h3>My Account</h3>

            <div class="map-list">
                <div class="map-list-row">
                    <span>Name</span>
                    <strong><?= e($admin_name) ?></strong>
                </div>

                <div class="map-list-row">
                    <span>Email</span>
                    <strong><?= e($admin_email ?: '—') ?></strong>
                </div>

                <div class="map-list-row">
                    <span>Role</span>
                    <strong><span class="map-badge green"><?= e($role_label) ?></span></strong>
                </div>

                <div class="map-list-row">
                    <span>Permissions</span>
                    <strong><?= number_format($permission_count) ?></strong>
                </div>
            </div>
        </div>
    </section>

    <?php if ($section === 'overview'): ?>
        <section class="map-stats">
            <div class="map-stat">
                <strong><?= number_format($total_doctors) ?></strong>
                <span>Doctors</span>
            </div>

            <div class="map-stat">
                <strong><?= number_format($total_hospitals) ?></strong>
                <span>Hospitals</span>
            </div>

            <div class="map-stat">
                <strong><?= number_format($total_users) ?></strong>
                <span>Users</span>
            </div>
        </section>

        <section class="map-layout">
            <div class="map-stack">
                <div class="map-card">
                    <div class="map-section-head">
                        <h2>Quick Actions</h2>
                        <span>Common admin shortcuts</span>
                    </div>

                    <div class="map-section-body">
                        <div class="map-shortcuts">
                            <a href="doctor-form.php" class="map-shortcut">
                                <strong>Add Doctor</strong>
                                <small>Create a new doctor profile.</small>
                            </a>

                            <a href="hospital-form.php" class="map-shortcut">
                                <strong>Add Hospital</strong>
                                <small>Create a new hospital profile.</small>
                            </a>

                            <a href="users.php" class="map-shortcut">
                                <strong>Users</strong>
                                <small>Manage registered users.</small>
                            </a>

                            <a href="user-requests.php" class="map-shortcut">
                                <strong>User Requests</strong>
                                <small><?= number_format($pending_requests) ?> pending update requests.</small>
                            </a>

                            <a href="profile-claims.php" class="map-shortcut">
                                <strong>Profile Claims</strong>
                                <small><?= number_format($pending_claims) ?> pending profile claims.</small>
                            </a>

                            <a href="reviews.php" class="map-shortcut">
                                <strong>Reviews</strong>
                                <small><?= number_format($total_reviews) ?> total reviews.</small>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <aside class="map-stack">
                <div class="map-card">
                    <div class="map-section-head">
                        <h2>System Summary</h2>
                        <span>Current data</span>
                    </div>

                    <div class="map-section-body">
                        <div class="map-list">
                            <div class="map-list-row">
                                <span>Reviews</span>
                                <strong><?= number_format($total_reviews) ?></strong>
                            </div>

                            <div class="map-list-row">
                                <span>Moderators</span>
                                <strong><?= number_format($total_moderators) ?></strong>
                            </div>

                            <div class="map-list-row">
                                <span>Pending Requests</span>
                                <strong><span class="map-badge yellow"><?= number_format($pending_requests) ?></span></strong>
                            </div>

                            <div class="map-list-row">
                                <span>Pending Claims</span>
                                <strong><span class="map-badge yellow"><?= number_format($pending_claims) ?></span></strong>
                            </div>

                            <div class="map-list-row">
                                <span>Last Login</span>
                                <strong>
                                    <?= $last_login ? e(date('d M Y h:i A', strtotime((string)$last_login))) : '—' ?>
                                </strong>
                            </div>

                            <div class="map-list-row">
                                <span>Joined</span>
                                <strong>
                                    <?= $created_at ? e(date('d M Y', strtotime((string)$created_at))) : '—' ?>
                                </strong>
                            </div>
                        </div>
                    </div>
                </div>
            </aside>
        </section>
    <?php endif; ?>

    <?php if ($section === 'profile'): ?>
        <div class="map-card">
            <div class="map-section-head">
                <h2>Update Profile</h2>
                <span>Change your name and email</span>
            </div>

            <div class="map-section-body">
                <?php if (!$admin_users_available): ?>
                    <div class="map-empty">
                        Profile editing requires admin_users table login system.
                    </div>
                <?php else: ?>
                    <form method="post" class="map-form-grid">
                        <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">
                        <input type="hidden" name="action" value="update_profile">

                        <div class="map-field">
                            <label>Name</label>
                            <input type="text" name="name" value="<?= e($admin_name) ?>" required>
                        </div>

                        <div class="map-field">
                            <label>Email</label>
                            <input type="email" name="email" value="<?= e($admin_email) ?>" required>
                        </div>

                        <div>
                            <button type="submit" class="map-btn map-btn-primary">Update Profile</button>
                            <a href="my-panel.php" class="map-btn">Back</a>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($section === 'password'): ?>
        <div class="map-card">
            <div class="map-section-head">
                <h2>Change Password</h2>
                <span>Update your login password</span>
            </div>

            <div class="map-section-body">
                <?php if (!$admin_users_available): ?>
                    <div class="map-empty">
                        Password change requires admin_users table login system.
                    </div>
                <?php else: ?>
                    <form method="post" class="map-form-grid">
                        <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">
                        <input type="hidden" name="action" value="change_password">

                        <div class="map-field">
                            <label>Current Password</label>
                            <input type="password" name="current_password" required>
                        </div>

                        <div class="map-field">
                            <label>New Password</label>
                            <input type="password" name="new_password" required>
                        </div>

                        <div class="map-field">
                            <label>Confirm New Password</label>
                            <input type="password" name="confirm_password" required>
                        </div>

                        <div>
                            <button type="submit" class="map-btn map-btn-primary">Change Password</button>
                            <a href="my-panel.php" class="map-btn">Back</a>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($section === 'permissions'): ?>
        <div class="map-card">
            <div class="map-section-head">
                <h2>My Permissions</h2>
                <span>Your access list</span>
            </div>

            <div class="map-section-body">
                <?php if ($admin_role === 'super_admin'): ?>
                    <div class="map-empty">You have full super admin access.</div>
                <?php elseif (!$admin_permissions): ?>
                    <div class="map-empty">No custom permission found.</div>
                <?php else: ?>
                    <div class="map-list">
                        <?php foreach ($admin_permissions as $permission): ?>
                            <div class="map-list-row">
                                <span><?= e((string)$permission) ?></span>
                                <strong><span class="map-badge green">Allowed</span></strong>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($section === 'activity'): ?>
        <div class="map-card">
            <div class="map-section-head">
                <h2>Recent Activity</h2>
                <span>Your latest admin actions</span>
            </div>

            <div class="map-section-body">
                <?php if (!$activity_logs): ?>
                    <div class="map-empty">No activity log found.</div>
                <?php else: ?>
                    <div class="map-log">
                        <?php foreach ($activity_logs as $log): ?>
                            <div class="map-log-item">
                                <strong><?= e($log['action'] ?? 'Activity') ?></strong>
                                <small><?= e($log['description'] ?? '') ?></small>
                                <small>
                                    <?= !empty($log['created_at']) ? e(date('d M Y h:i A', strtotime((string)$log['created_at']))) : '—' ?>
                                </small>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>