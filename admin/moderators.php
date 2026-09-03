<?php
require_once __DIR__ . '/includes/header.php';

if (function_exists('require_admin_permission')) {
    require_admin_permission('moderators.manage');
}

/*
|--------------------------------------------------------------------------
| Auto Table Create
|--------------------------------------------------------------------------
*/
if (!function_exists('admin_moderators_ensure_tables')) {
    function admin_moderators_ensure_tables(): void
    {
        global $pdo;

        try {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `admin_users` (
                    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `name` VARCHAR(120) NOT NULL,
                    `email` VARCHAR(160) NOT NULL,
                    `password` VARCHAR(255) NOT NULL,
                    `role` ENUM('super_admin','admin','moderator','editor','support') NOT NULL DEFAULT 'moderator',
                    `permissions` LONGTEXT NULL,
                    `status` ENUM('active','blocked') NOT NULL DEFAULT 'active',
                    `last_login_at` DATETIME NULL,
                    `created_at` DATETIME NULL,
                    `updated_at` DATETIME NULL,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `admin_users_email_unique` (`email`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `admin_activity_logs` (
                    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `admin_id` INT UNSIGNED NULL,
                    `action` VARCHAR(120) NOT NULL,
                    `description` TEXT NULL,
                    `ip_address` VARCHAR(80) NULL,
                    `user_agent` TEXT NULL,
                    `created_at` DATETIME NULL,
                    PRIMARY KEY (`id`),
                    KEY `admin_activity_logs_admin_id_index` (`admin_id`),
                    KEY `admin_activity_logs_action_index` (`action`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        } catch (Throwable $e) {
            // If table creation fails, normal table check below will show message.
        }
    }
}

admin_moderators_ensure_tables();

if (!table_exists('admin_users')) {
    echo '<div class="card" style="padding:20px;">
            <h2>Admin users table not found</h2>
            <p>Please create the <strong>admin_users</strong> table first.</p>
          </div>';

    require_once __DIR__ . '/includes/footer.php';
    exit;
}

/*
|--------------------------------------------------------------------------
| CSRF
|--------------------------------------------------------------------------
*/
if (empty($_SESSION['admin_moderators_csrf'])) {
    $_SESSION['admin_moderators_csrf'] = bin2hex(random_bytes(32));
}

$csrf_token = $_SESSION['admin_moderators_csrf'];
$errors = [];
$success = '';

/*
|--------------------------------------------------------------------------
| Roles & Permissions
|--------------------------------------------------------------------------
*/
$roles = [
    'super_admin' => 'Super Admin',
    'admin'       => 'Admin',
    'moderator'   => 'Moderator',
    'editor'      => 'Editor',
    'support'     => 'Support',
];

$permissions_list = function_exists('admin_permissions_list') ? admin_permissions_list() : [];

if (!$permissions_list) {
    $permissions_list = [
        'dashboard.view'       => 'View Dashboard',

        'doctors.view'         => 'View Doctors',
        'doctors.create'       => 'Add Doctor',
        'doctors.edit'         => 'Edit Doctor',
        'doctors.delete'       => 'Delete Doctor',

        'hospitals.view'       => 'View Hospitals',
        'hospitals.create'     => 'Add Hospital',
        'hospitals.edit'       => 'Edit Hospital',
        'hospitals.delete'     => 'Delete Hospital',

        'specialties.manage'   => 'Manage Specialties',
        'locations.manage'     => 'Manage Locations',

        'reviews.view'         => 'View Reviews',
        'reviews.manage'       => 'Manage Reviews',

        'contacts.view'        => 'View Contact Messages',
        'contacts.manage'      => 'Manage Contact Messages',

        'users.view'           => 'View Users',
        'users.manage'         => 'Manage Users',

        'claims.view'          => 'View Profile Claims',
        'claims.manage'        => 'Manage Profile Claims',

        'settings.view'        => 'View Site Settings',
        'settings.manage'      => 'Manage Site Settings',

        'moderators.view'      => 'View Moderators',
        'moderators.manage'    => 'Manage Moderators',
    ];
}

if (!function_exists('admin_moderator_default_permissions')) {
    function admin_moderator_default_permissions(string $role, array $permissions_list): array
    {
        if (function_exists('admin_default_role_permissions')) {
            return admin_default_role_permissions($role);
        }

        $all = array_keys($permissions_list);

        if ($role === 'super_admin') {
            return $all;
        }

        if ($role === 'admin') {
            return array_values(array_filter($all, function ($permission) {
                return $permission !== 'moderators.manage';
            }));
        }

        if ($role === 'moderator') {
            return [
                'dashboard.view',
                'doctors.view',
                'doctors.edit',
                'hospitals.view',
                'hospitals.edit',
                'reviews.view',
                'reviews.manage',
                'contacts.view',
                'contacts.manage',
                'users.view',
                'claims.view',
                'claims.manage',
            ];
        }

        if ($role === 'editor') {
            return [
                'dashboard.view',
                'doctors.view',
                'doctors.create',
                'doctors.edit',
                'hospitals.view',
                'hospitals.create',
                'hospitals.edit',
                'specialties.manage',
                'locations.manage',
            ];
        }

        if ($role === 'support') {
            return [
                'dashboard.view',
                'users.view',
                'claims.view',
                'reviews.view',
                'contacts.view',
            ];
        }

        return ['dashboard.view'];
    }
}

if (!function_exists('admin_moderator_role_label')) {
    function admin_moderator_role_label(string $role, array $roles): string
    {
        return $roles[$role] ?? ucwords(str_replace('_', ' ', $role));
    }
}

if (!function_exists('admin_moderator_get_permissions')) {
    function admin_moderator_get_permissions(?array $admin, array $permissions_list): array
    {
        if (!$admin) {
            return [];
        }

        $permissions = [];

        if (!empty($admin['permissions'])) {
            $decoded = json_decode((string)$admin['permissions'], true);
            $permissions = is_array($decoded) ? $decoded : [];
        }

        if (!$permissions) {
            $permissions = admin_moderator_default_permissions((string)($admin['role'] ?? 'moderator'), $permissions_list);
        }

        return array_values(array_intersect(array_map('strval', $permissions), array_keys($permissions_list)));
    }
}

if (!function_exists('admin_moderator_log')) {
    function admin_moderator_log(string $action, string $description = ''): void
    {
        if (function_exists('admin_log_activity')) {
            admin_log_activity($action, $description);
        }
    }
}

/*
|--------------------------------------------------------------------------
| Page Mode
|--------------------------------------------------------------------------
*/
$mode = trim((string)($_GET['mode'] ?? 'list'));
$edit_id = (int)($_GET['edit'] ?? 0);

if ($edit_id > 0) {
    $mode = 'edit';
}

if (!in_array($mode, ['list', 'add', 'edit'], true)) {
    $mode = 'list';
}

/*
|--------------------------------------------------------------------------
| Flash From Query
|--------------------------------------------------------------------------
*/
if (isset($_GET['created'])) {
    $success = 'Moderator created successfully.';
}

if (isset($_GET['updated'])) {
    $success = 'Moderator updated successfully.';
}

if (isset($_GET['deleted'])) {
    $success = 'Moderator deleted successfully.';
}

if (isset($_GET['status_updated'])) {
    $success = 'Moderator status updated successfully.';
}

/*
|--------------------------------------------------------------------------
| Actions
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $posted_token = (string)($_POST['csrf_token'] ?? '');

    if (!hash_equals($csrf_token, $posted_token)) {
        $errors[] = 'Security token mismatch. Please try again.';
    } else {
        $action = (string)($_POST['action'] ?? '');
        $admin_id = (int)($_POST['admin_id'] ?? 0);

        /*
        |--------------------------------------------------------------------------
        | Create / Update
        |--------------------------------------------------------------------------
        */
        if ($action === 'create_moderator' || $action === 'update_moderator') {
            $name = trim((string)($_POST['name'] ?? ''));
            $email = trim((string)($_POST['email'] ?? ''));
            $password = (string)($_POST['password'] ?? '');
            $role = (string)($_POST['role'] ?? 'moderator');
            $status = (string)($_POST['status'] ?? 'active');
            $permissions = $_POST['permissions'] ?? [];

            if ($name === '') {
                $errors[] = 'Name is required.';
            }

            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Valid email is required.';
            }

            if (!array_key_exists($role, $roles)) {
                $errors[] = 'Invalid role selected.';
            }

            if (!in_array($status, ['active', 'blocked'], true)) {
                $errors[] = 'Invalid status selected.';
            }

            if (!is_array($permissions)) {
                $permissions = [];
            }

            $permissions = array_values(array_intersect(array_map('strval', $permissions), array_keys($permissions_list)));

            if ($role === 'super_admin') {
                $permissions = array_keys($permissions_list);
            }

            if (!$permissions) {
                $permissions = admin_moderator_default_permissions($role, $permissions_list);
            }

            try {
                if ($action === 'create_moderator') {
                    if ($password === '') {
                        $errors[] = 'Password is required for new moderator.';
                    }

                    if (!$errors) {
                        $check = $pdo->prepare("SELECT id FROM admin_users WHERE email = :email LIMIT 1");
                        $check->execute([':email' => $email]);

                        if ($check->fetch()) {
                            $errors[] = 'This email is already used.';
                        }
                    }

                    if (!$errors) {
                        $stmt = $pdo->prepare("
                            INSERT INTO admin_users 
                                (name, email, password, role, permissions, status, created_at, updated_at)
                            VALUES
                                (:name, :email, :password, :role, :permissions, :status, NOW(), NOW())
                        ");

                        $stmt->execute([
                            ':name' => $name,
                            ':email' => $email,
                            ':password' => password_hash($password, PASSWORD_DEFAULT),
                            ':role' => $role,
                            ':permissions' => json_encode($permissions),
                            ':status' => $status,
                        ]);

                        admin_moderator_log('moderator.created', 'Created moderator: ' . $email);

                        redirect('moderators.php?created=1');
                    }
                }

                if ($action === 'update_moderator') {
                    if ($admin_id <= 0) {
                        $errors[] = 'Invalid moderator selected.';
                    }

                    if (!$errors) {
                        $check = $pdo->prepare("SELECT id FROM admin_users WHERE email = :email AND id != :id LIMIT 1");
                        $check->execute([
                            ':email' => $email,
                            ':id' => $admin_id,
                        ]);

                        if ($check->fetch()) {
                            $errors[] = 'This email is already used by another moderator.';
                        }
                    }

                    if (!$errors) {
                        $sql = "
                            UPDATE admin_users
                            SET name = :name,
                                email = :email,
                                role = :role,
                                permissions = :permissions,
                                status = :status,
                                updated_at = NOW()
                        ";

                        $params = [
                            ':name' => $name,
                            ':email' => $email,
                            ':role' => $role,
                            ':permissions' => json_encode($permissions),
                            ':status' => $status,
                            ':id' => $admin_id,
                        ];

                        if ($password !== '') {
                            $sql .= ", password = :password";
                            $params[':password'] = password_hash($password, PASSWORD_DEFAULT);
                        }

                        $sql .= " WHERE id = :id LIMIT 1";

                        $stmt = $pdo->prepare($sql);
                        $stmt->execute($params);

                        admin_moderator_log('moderator.updated', 'Updated moderator ID: ' . $admin_id);

                        redirect('moderators.php?updated=1');
                    }
                }
            } catch (Throwable $e) {
                $errors[] = 'Moderator could not be saved.';
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Delete
        |--------------------------------------------------------------------------
        */
        if ($action === 'delete_moderator' && $admin_id > 0) {
            if ($admin_id === (int)($_SESSION['admin_id'] ?? 0)) {
                $errors[] = 'You cannot delete your own account.';
            } else {
                try {
                    $stmt = $pdo->prepare("DELETE FROM admin_users WHERE id = :id LIMIT 1");
                    $stmt->execute([':id' => $admin_id]);

                    admin_moderator_log('moderator.deleted', 'Deleted moderator ID: ' . $admin_id);

                    redirect('moderators.php?deleted=1');
                } catch (Throwable $e) {
                    $errors[] = 'Moderator could not be deleted.';
                }
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Status Change
        |--------------------------------------------------------------------------
        */
        if ($action === 'change_status' && $admin_id > 0) {
            $new_status = (string)($_POST['status'] ?? '');

            if ($admin_id === (int)($_SESSION['admin_id'] ?? 0) && $new_status === 'blocked') {
                $errors[] = 'You cannot block your own account.';
            } elseif (!in_array($new_status, ['active', 'blocked'], true)) {
                $errors[] = 'Invalid status.';
            } else {
                try {
                    $stmt = $pdo->prepare("
                        UPDATE admin_users
                        SET status = :status,
                            updated_at = NOW()
                        WHERE id = :id
                        LIMIT 1
                    ");

                    $stmt->execute([
                        ':status' => $new_status,
                        ':id' => $admin_id,
                    ]);

                    admin_moderator_log('moderator.status_changed', 'Changed moderator status ID: ' . $admin_id);

                    redirect('moderators.php?status_updated=1');
                } catch (Throwable $e) {
                    $errors[] = 'Status could not be updated.';
                }
            }
        }
    }
}

/*
|--------------------------------------------------------------------------
| Edit Data
|--------------------------------------------------------------------------
*/
$edit_admin = null;

if ($mode === 'edit' && $edit_id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM admin_users WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $edit_id]);
    $edit_admin = $stmt->fetch() ?: null;

    if (!$edit_admin) {
        $errors[] = 'Moderator not found.';
        $mode = 'list';
    }
}

$edit_permissions = admin_moderator_get_permissions($edit_admin, $permissions_list);

/*
|--------------------------------------------------------------------------
| List Data
|--------------------------------------------------------------------------
*/
$search = trim((string)($_GET['search'] ?? ''));
$role_filter = trim((string)($_GET['role'] ?? ''));
$status_filter = trim((string)($_GET['status'] ?? ''));

$where = [];
$params = [];

if ($search !== '') {
    $where[] = "(name LIKE :search OR email LIKE :search)";
    $params[':search'] = '%' . $search . '%';
}

if ($role_filter !== '' && array_key_exists($role_filter, $roles)) {
    $where[] = "role = :role";
    $params[':role'] = $role_filter;
}

if ($status_filter !== '' && in_array($status_filter, ['active', 'blocked'], true)) {
    $where[] = "status = :status";
    $params[':status'] = $status_filter;
}

$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = $pdo->prepare("SELECT * FROM admin_users {$where_sql} ORDER BY id DESC");
$stmt->execute($params);
$admins = $stmt->fetchAll();

$total_admins = 0;
$active_admins = 0;
$blocked_admins = 0;

try {
    $total_admins = (int)$pdo->query("SELECT COUNT(*) FROM admin_users")->fetchColumn();
    $active_admins = (int)$pdo->query("SELECT COUNT(*) FROM admin_users WHERE status = 'active'")->fetchColumn();
    $blocked_admins = (int)$pdo->query("SELECT COUNT(*) FROM admin_users WHERE status = 'blocked'")->fetchColumn();
} catch (Throwable $e) {
    $total_admins = count($admins);
}

$form_action = $mode === 'edit' ? 'update_moderator' : 'create_moderator';
$form_title = $mode === 'edit' ? 'Edit Moderator' : 'Add New Moderator';
$form_button = $mode === 'edit' ? 'Update Moderator' : 'Create Moderator';
$form_admin_id = (int)($edit_admin['id'] ?? 0);
$form_name = (string)($edit_admin['name'] ?? '');
$form_email = (string)($edit_admin['email'] ?? '');
$form_role = (string)($edit_admin['role'] ?? 'moderator');
$form_status = (string)($edit_admin['status'] ?? 'active');

if ($mode === 'add') {
    $edit_permissions = admin_moderator_default_permissions('moderator', $permissions_list);
}
?>

<style>
    .mod-page {
        color: #24292f;
    }

    .mod-hero {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 16px;
        flex-wrap: wrap;
        background: #ffffff;
        border: 1px solid #d0d7de;
        border-radius: 10px;
        padding: 18px;
        margin-bottom: 16px;
        box-shadow: 0 1px 0 rgba(27, 31, 36, .04);
    }

    .mod-hero h2 {
        margin: 0 0 6px;
        font-size: 24px;
        letter-spacing: -.03em;
        color: #24292f;
    }

    .mod-hero p {
        margin: 0;
        color: #57606a;
        font-size: 14px;
        line-height: 1.6;
    }

    .mod-hero-actions {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }

    .mod-alert {
        padding: 12px 14px;
        border-radius: 8px;
        margin-bottom: 16px;
        font-size: 14px;
        border: 1px solid transparent;
    }

    .mod-alert.success {
        background: #dafbe1;
        color: #1a7f37;
        border-color: rgba(26, 127, 55, .25);
    }

    .mod-alert.error {
        background: #ffebe9;
        color: #cf222e;
        border-color: rgba(207, 34, 46, .25);
    }

    .mod-stats {
        display: grid;
        grid-template-columns: repeat(3, minmax(160px, 1fr));
        gap: 12px;
        margin-bottom: 16px;
    }

    .mod-stat {
        background: #ffffff;
        border: 1px solid #d0d7de;
        border-radius: 10px;
        padding: 15px;
        box-shadow: 0 1px 0 rgba(27, 31, 36, .04);
    }

    .mod-stat strong {
        display: block;
        font-size: 26px;
        line-height: 1;
        color: #24292f;
        margin-bottom: 6px;
    }

    .mod-stat span {
        color: #57606a;
        font-size: 13px;
        font-weight: 600;
    }

    .mod-card {
        background: #ffffff;
        border: 1px solid #d0d7de;
        border-radius: 10px;
        overflow: hidden;
        box-shadow: 0 1px 0 rgba(27, 31, 36, .04);
        margin-bottom: 16px;
    }

    .mod-card h3 {
        margin: 0;
        padding: 14px 16px;
        background: #f6f8fa;
        border-bottom: 1px solid #d0d7de;
        font-size: 15px;
        color: #24292f;
    }

    .mod-card-body {
        padding: 16px;
    }

    .mod-form-layout {
        display: grid;
        grid-template-columns: 1fr 1.3fr;
        gap: 16px;
        align-items: start;
    }

    .mod-grid {
        display: grid;
        gap: 12px;
    }

    .mod-field {
        display: grid;
        gap: 7px;
    }

    .mod-field label {
        font-size: 13px;
        font-weight: 700;
        color: #24292f;
    }

    .mod-field input,
    .mod-field select {
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

    .mod-field input:focus,
    .mod-field select:focus {
        border-color: #0969da;
        box-shadow: 0 0 0 3px rgba(9, 105, 218, .15);
    }

    .mod-permissions-toolbar {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        margin-bottom: 10px;
    }

    .mod-permissions {
        display: grid;
        grid-template-columns: repeat(2, minmax(220px, 1fr));
        gap: 7px;
        max-height: 520px;
        overflow: auto;
        padding: 8px;
        border: 1px solid #d8dee4;
        border-radius: 8px;
        background: #f6f8fa;
    }

    .mod-permission-item {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 8px;
        border: 1px solid #d8dee4;
        border-radius: 6px;
        background: #ffffff;
        font-size: 13px;
        color: #24292f;
    }

    .mod-permission-item input {
        width: 16px;
        height: 16px;
        min-height: auto;
    }

    .mod-actions {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        margin-top: 14px;
    }

    .mod-btn {
        min-height: 34px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 7px;
        padding: 7px 12px;
        border-radius: 6px;
        border: 1px solid rgba(27, 31, 36, .15);
        background: #f6f8fa;
        color: #24292f;
        font-size: 13px;
        font-weight: 700;
        text-decoration: none;
        cursor: pointer;
        box-shadow: 0 1px 0 rgba(27, 31, 36, .04);
    }

    .mod-btn:hover {
        background: #eef1f4;
        text-decoration: none;
        color: #24292f;
    }

    .mod-btn-primary {
        background: #2da44e;
        color: #ffffff;
    }

    .mod-btn-primary:hover {
        background: #1f883d;
        color: #ffffff;
    }

    .mod-btn-danger {
        background: #ffebe9;
        color: #cf222e;
        border-color: rgba(207, 34, 46, .25);
    }

    .mod-btn-danger:hover {
        background: #ffd8d3;
        color: #a40e26;
    }

    .mod-filter {
        display: grid;
        grid-template-columns: 1.5fr 1fr 1fr auto;
        gap: 10px;
        margin-bottom: 12px;
    }

    .mod-badge {
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

    .mod-badge.active {
        background: #dafbe1;
        color: #1a7f37;
        border-color: rgba(26, 127, 55, .25);
    }

    .mod-badge.blocked {
        background: #ffebe9;
        color: #cf222e;
        border-color: rgba(207, 34, 46, .25);
    }

    .mod-table-actions {
        display: flex;
        gap: 6px;
        flex-wrap: wrap;
    }

    .mod-table-actions form {
        margin: 0;
        padding: 0;
        border: 0;
        background: transparent;
        box-shadow: none;
    }

    .mod-muted {
        color: #57606a;
        font-size: 13px;
        line-height: 1.6;
    }

    @media(max-width: 1100px) {
        .mod-form-layout {
            grid-template-columns: 1fr;
        }

        .mod-stats {
            grid-template-columns: repeat(2, 1fr);
        }

        .mod-filter {
            grid-template-columns: 1fr;
        }

        .mod-permissions {
            grid-template-columns: 1fr;
        }
    }

    @media(max-width: 700px) {
        .mod-stats {
            grid-template-columns: 1fr;
        }

        .mod-card-body {
            padding: 14px;
        }

        .mod-hero-actions,
        .mod-actions {
            width: 100%;
        }

        .mod-btn {
            width: 100%;
        }
    }
</style>

<div class="mod-page">
    <div class="mod-hero">
        <div>
            <h2>Admin Moderators</h2>
            <p>Create, edit, update, block, activate and delete admin users from this same page.</p>
        </div>

        <div class="mod-hero-actions">
            <a href="moderators.php" class="mod-btn">All Moderators</a>
            <a href="moderators.php?mode=add" class="mod-btn mod-btn-primary">Add New</a>
            <a href="dashboard.php" class="mod-btn">Dashboard</a>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="mod-alert success"><?= e($success) ?></div>
    <?php endif; ?>

    <?php if ($errors): ?>
        <div class="mod-alert error">
            <?php foreach ($errors as $error): ?>
                <div><?= e($error) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="mod-stats">
        <div class="mod-stat">
            <strong><?= number_format($total_admins) ?></strong>
            <span>Total Moderators</span>
        </div>

        <div class="mod-stat">
            <strong><?= number_format($active_admins) ?></strong>
            <span>Active Accounts</span>
        </div>

        <div class="mod-stat">
            <strong><?= number_format($blocked_admins) ?></strong>
            <span>Blocked Accounts</span>
        </div>
    </div>

    <?php if ($mode === 'add' || $mode === 'edit'): ?>
        <div class="mod-card">
            <h3><?= e($form_title) ?></h3>

            <div class="mod-card-body">
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">
                    <input type="hidden" name="action" value="<?= e($form_action) ?>">
                    <input type="hidden" name="admin_id" value="<?= e((string)$form_admin_id) ?>">

                    <div class="mod-form-layout">
                        <div class="mod-grid">
                            <div class="mod-field">
                                <label>Name</label>
                                <input type="text" name="name" value="<?= e($form_name) ?>" required>
                            </div>

                            <div class="mod-field">
                                <label>Email</label>
                                <input type="email" name="email" value="<?= e($form_email) ?>" required>
                            </div>

                            <div class="mod-field">
                                <label>Password <?= $mode === 'edit' ? '(leave empty to keep same)' : '' ?></label>
                                <input type="password" name="password" <?= $mode === 'add' ? 'required' : '' ?>>
                            </div>

                            <div class="mod-field">
                                <label>Role</label>
                                <select name="role" id="moderatorRole">
                                    <?php foreach ($roles as $role_key => $role_label): ?>
                                        <option value="<?= e($role_key) ?>" <?= $form_role === $role_key ? 'selected' : '' ?>>
                                            <?= e($role_label) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mod-field">
                                <label>Status</label>
                                <select name="status">
                                    <option value="active" <?= $form_status === 'active' ? 'selected' : '' ?>>Active</option>
                                    <option value="blocked" <?= $form_status === 'blocked' ? 'selected' : '' ?>>Blocked</option>
                                </select>
                            </div>

                            <p class="mod-muted">
                                Super Admin gets all permissions automatically. For other roles, you can select custom permissions.
                            </p>
                        </div>

                        <div>
                            <div class="mod-field">
                                <label>Permissions</label>

                                <div class="mod-permissions-toolbar">
                                    <button type="button" class="mod-btn" id="selectAllPermissions">Select All</button>
                                    <button type="button" class="mod-btn" id="clearAllPermissions">Clear All</button>
                                    <button type="button" class="mod-btn" id="applyRolePermissions">Apply Role Default</button>
                                </div>

                                <div class="mod-permissions">
                                    <?php foreach ($permissions_list as $permission_key => $permission_label): ?>
                                        <label class="mod-permission-item">
                                            <input
                                                type="checkbox"
                                                class="permission-checkbox"
                                                name="permissions[]"
                                                value="<?= e($permission_key) ?>"
                                                <?= in_array($permission_key, $edit_permissions, true) ? 'checked' : '' ?>
                                            >
                                            <span><?= e($permission_label) ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mod-actions">
                        <button type="submit" class="mod-btn mod-btn-primary"><?= e($form_button) ?></button>
                        <a href="moderators.php" class="mod-btn">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($mode === 'list'): ?>
        <div class="mod-card">
            <h3>Moderator List</h3>

            <div class="mod-card-body">
                <form method="get" class="mod-filter">
                    <input type="text" name="search" value="<?= e($search) ?>" placeholder="Search by name or email">

                    <select name="role">
                        <option value="">All Roles</option>
                        <?php foreach ($roles as $role_key => $role_label): ?>
                            <option value="<?= e($role_key) ?>" <?= $role_filter === $role_key ? 'selected' : '' ?>>
                                <?= e($role_label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <select name="status">
                        <option value="">All Status</option>
                        <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="blocked" <?= $status_filter === 'blocked' ? 'selected' : '' ?>>Blocked</option>
                    </select>

                    <button type="submit" class="mod-btn mod-btn-primary">Filter</button>
                </form>

                <table>
                    <thead>
                        <tr>
                            <th>Admin</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Last Login</th>
                            <th>Created</th>
                            <th>Action</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php if (!$admins): ?>
                            <tr>
                                <td colspan="6" style="text-align:center;color:#57606a;padding:24px;">No moderator found.</td>
                            </tr>
                        <?php endif; ?>

                        <?php foreach ($admins as $admin): ?>
                            <?php
                            $next_status = ($admin['status'] ?? '') === 'active' ? 'blocked' : 'active';
                            $next_status_label = $next_status === 'blocked' ? 'Block' : 'Activate';
                            ?>

                            <tr>
                                <td>
                                    <strong><?= e($admin['name']) ?></strong><br>
                                    <small><?= e($admin['email']) ?></small>
                                </td>

                                <td><?= e(admin_moderator_role_label((string)$admin['role'], $roles)) ?></td>

                                <td>
                                    <span class="mod-badge <?= e($admin['status']) ?>">
                                        <?= e(ucfirst((string)$admin['status'])) ?>
                                    </span>
                                </td>

                                <td>
                                    <?= !empty($admin['last_login_at']) ? e(date('d M Y h:i A', strtotime((string)$admin['last_login_at']))) : '—' ?>
                                </td>

                                <td>
                                    <?= !empty($admin['created_at']) ? e(date('d M Y', strtotime((string)$admin['created_at']))) : '—' ?>
                                </td>

                                <td>
                                    <div class="mod-table-actions">
                                        <a href="moderators.php?edit=<?= e((string)$admin['id']) ?>" class="mod-btn">Edit</a>

                                        <?php if ((int)$admin['id'] !== (int)($_SESSION['admin_id'] ?? 0)): ?>
                                            <form method="post" onsubmit="return confirm('Are you sure you want to <?= e(strtolower($next_status_label)) ?> this moderator?');">
                                                <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">
                                                <input type="hidden" name="action" value="change_status">
                                                <input type="hidden" name="admin_id" value="<?= e((string)$admin['id']) ?>">
                                                <input type="hidden" name="status" value="<?= e($next_status) ?>">
                                                <button type="submit" class="mod-btn"><?= e($next_status_label) ?></button>
                                            </form>

                                            <form method="post" onsubmit="return confirm('Delete this moderator permanently?');">
                                                <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">
                                                <input type="hidden" name="action" value="delete_moderator">
                                                <input type="hidden" name="admin_id" value="<?= e((string)$admin['id']) ?>">
                                                <button type="submit" class="mod-btn mod-btn-danger">Delete</button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

            </div>
        </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const selectAllBtn = document.getElementById('selectAllPermissions');
    const clearAllBtn = document.getElementById('clearAllPermissions');
    const applyRoleBtn = document.getElementById('applyRolePermissions');
    const roleSelect = document.getElementById('moderatorRole');

    const checkboxes = Array.from(document.querySelectorAll('.permission-checkbox'));

    const rolePermissions = <?= json_encode([
        'super_admin' => admin_moderator_default_permissions('super_admin', $permissions_list),
        'admin' => admin_moderator_default_permissions('admin', $permissions_list),
        'moderator' => admin_moderator_default_permissions('moderator', $permissions_list),
        'editor' => admin_moderator_default_permissions('editor', $permissions_list),
        'support' => admin_moderator_default_permissions('support', $permissions_list),
    ]) ?>;

    function setPermissions(permissions) {
        checkboxes.forEach(function (checkbox) {
            checkbox.checked = permissions.includes(checkbox.value);
        });
    }

    if (selectAllBtn) {
        selectAllBtn.addEventListener('click', function () {
            checkboxes.forEach(function (checkbox) {
                checkbox.checked = true;
            });
        });
    }

    if (clearAllBtn) {
        clearAllBtn.addEventListener('click', function () {
            checkboxes.forEach(function (checkbox) {
                checkbox.checked = false;
            });
        });
    }

    if (applyRoleBtn && roleSelect) {
        applyRoleBtn.addEventListener('click', function () {
            const role = roleSelect.value;
            setPermissions(rolePermissions[role] || []);
        });
    }

    if (roleSelect) {
        roleSelect.addEventListener('change', function () {
            if (roleSelect.value === 'super_admin') {
                setPermissions(rolePermissions.super_admin || []);
            }
        });
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>