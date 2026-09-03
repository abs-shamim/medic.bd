<?php
require_once __DIR__ . '/includes/header.php';

if (!table_exists('users')) {
    echo '<div class="card" style="padding:20px;"><h2>Users table not found</h2><p>Please import the updated database first.</p></div>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

$user_id = (int)($_GET['id'] ?? 0);

if ($user_id <= 0) {
    echo '<div class="card" style="padding:20px;"><h2>Invalid User</h2><p>User ID is missing.</p><a href="users.php" class="btn">Back to Users</a></div>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

if (empty($_SESSION['admin_user_edit_csrf'])) {
    $_SESSION['admin_user_edit_csrf'] = bin2hex(random_bytes(32));
}

$csrf_token = $_SESSION['admin_user_edit_csrf'];
$errors = [];
$success_messages = [];

if (!function_exists('admin_user_edit_extract_profile_ref')) {
    function admin_user_edit_extract_profile_ref(string $input, string $profile_type): string
    {
        $input = trim($input);

        if ($input === '') {
            return '';
        }

        $input = rawurldecode($input);
        $path = parse_url($input, PHP_URL_PATH);

        if ($path !== null && $path !== false && trim((string)$path) !== '') {
            $input = (string)$path;
        }

        $input = preg_replace('/\?.*$/', '', $input);
        $input = trim((string)$input, '/');

        if ($input === '') {
            return '';
        }

        $segments = array_values(array_filter(explode('/', $input)));
        $target = $profile_type === 'hospital' ? 'hospital' : 'doctor';

        foreach ($segments as $index => $segment) {
            if ($segment === $target && !empty($segments[$index + 1])) {
                return trim((string)$segments[$index + 1]);
            }
        }

        return trim((string)end($segments));
    }
}

if (!function_exists('admin_user_edit_get_profile')) {
    function admin_user_edit_get_profile(string $profile_type, int $profile_id): ?array
    {
        global $pdo;

        if ($profile_id <= 0) {
            return null;
        }

        if ($profile_type === 'doctor') {
            if (!table_exists('doctors')) {
                return null;
            }

            try {
                $stmt = $pdo->prepare("
                    SELECT 
                        d.*,
                        s.name AS specialty_name,
                        s.slug AS specialty_slug,
                        h.name AS hospital_name
                    FROM doctors d
                    LEFT JOIN specialties s ON s.id = d.specialty_id
                    LEFT JOIN hospitals h ON h.id = d.hospital_id
                    WHERE d.id = :id
                    LIMIT 1
                ");
                $stmt->execute([':id' => $profile_id]);
                $profile = $stmt->fetch();

                return $profile ?: null;
            } catch (Throwable $e) {
                return null;
            }
        }

        if ($profile_type === 'hospital') {
            if (!table_exists('hospitals')) {
                return null;
            }

            try {
                $stmt = $pdo->prepare("SELECT * FROM hospitals WHERE id = :id LIMIT 1");
                $stmt->execute([':id' => $profile_id]);
                $profile = $stmt->fetch();

                return $profile ?: null;
            } catch (Throwable $e) {
                return null;
            }
        }

        return null;
    }
}

if (!function_exists('admin_user_edit_find_profile_by_ref')) {
    function admin_user_edit_find_profile_by_ref(string $profile_type, string $profile_ref): ?array
    {
        global $pdo;

        $profile_ref = trim($profile_ref);

        if ($profile_ref === '') {
            return null;
        }

        if ($profile_type === 'doctor') {
            if (!table_exists('doctors')) {
                return null;
            }

            try {
                if (ctype_digit($profile_ref)) {
                    $stmt = $pdo->prepare("
                        SELECT 
                            d.*,
                            s.name AS specialty_name,
                            s.slug AS specialty_slug,
                            h.name AS hospital_name
                        FROM doctors d
                        LEFT JOIN specialties s ON s.id = d.specialty_id
                        LEFT JOIN hospitals h ON h.id = d.hospital_id
                        WHERE d.id = :id
                        LIMIT 1
                    ");
                    $stmt->execute([':id' => (int)$profile_ref]);
                } else {
                    $where = [];
                    $params = [];

                    if (column_exists('doctors', 'slug')) {
                        $where[] = "d.slug = :slug";
                        $params[':slug'] = $profile_ref;
                    }

                    $search_columns = [
                        'name',
                        'email',
                        'phone',
                        'mobile',
                        'whatsapp',
                        'whatsapp_number',
                        'bmdc_number',
                        'bmdc_no',
                        'bmdc',
                        'registration_number',
                        'registration_no',
                        'reg_no',
                        'license_number',
                    ];

                    $i = 1;

                    foreach ($search_columns as $column) {
                        if (column_exists('doctors', $column)) {
                            $key = ':doctor_search_' . $i;
                            $where[] = "d.{$column} LIKE {$key}";
                            $params[$key] = '%' . $profile_ref . '%';
                            $i++;
                        }
                    }

                    if (!$where) {
                        return null;
                    }

                    $stmt = $pdo->prepare("
                        SELECT 
                            d.*,
                            s.name AS specialty_name,
                            s.slug AS specialty_slug,
                            h.name AS hospital_name
                        FROM doctors d
                        LEFT JOIN specialties s ON s.id = d.specialty_id
                        LEFT JOIN hospitals h ON h.id = d.hospital_id
                        WHERE " . implode(' OR ', $where) . "
                        ORDER BY d.id DESC
                        LIMIT 1
                    ");

                    $stmt->execute($params);
                }

                $profile = $stmt->fetch();

                return $profile ?: null;
            } catch (Throwable $e) {
                return null;
            }
        }

        if ($profile_type === 'hospital') {
            if (!table_exists('hospitals')) {
                return null;
            }

            try {
                if (ctype_digit($profile_ref)) {
                    $stmt = $pdo->prepare("SELECT * FROM hospitals WHERE id = :id LIMIT 1");
                    $stmt->execute([':id' => (int)$profile_ref]);
                } else {
                    $where = [];
                    $params = [];

                    if (column_exists('hospitals', 'slug')) {
                        $where[] = "slug = :slug";
                        $params[':slug'] = $profile_ref;
                    }

                    $search_columns = [
                        'name',
                        'email',
                        'phone',
                        'mobile',
                        'whatsapp',
                        'whatsapp_number',
                        'registration_number',
                        'registration_no',
                        'license_number',
                        'address',
                    ];

                    $i = 1;

                    foreach ($search_columns as $column) {
                        if (column_exists('hospitals', $column)) {
                            $key = ':hospital_search_' . $i;
                            $where[] = "{$column} LIKE {$key}";
                            $params[$key] = '%' . $profile_ref . '%';
                            $i++;
                        }
                    }

                    if (!$where) {
                        return null;
                    }

                    $stmt = $pdo->prepare("
                        SELECT *
                        FROM hospitals
                        WHERE " . implode(' OR ', $where) . "
                        ORDER BY id DESC
                        LIMIT 1
                    ");

                    $stmt->execute($params);
                }

                $profile = $stmt->fetch();

                return $profile ?: null;
            } catch (Throwable $e) {
                return null;
            }
        }

        return null;
    }
}

if (!function_exists('admin_user_edit_connected_user')) {
    function admin_user_edit_connected_user(string $profile_type, int $profile_id, int $current_user_id): ?array
    {
        global $pdo;

        if ($profile_id <= 0 || !table_exists('users')) {
            return null;
        }

        try {
            if ($profile_type === 'doctor') {
                $stmt = $pdo->prepare("
                    SELECT id, name, email
                    FROM users
                    WHERE claimed_doctor_id = :profile_id
                    AND id != :user_id
                    LIMIT 1
                ");
            } elseif ($profile_type === 'hospital') {
                $stmt = $pdo->prepare("
                    SELECT id, name, email
                    FROM users
                    WHERE claimed_hospital_id = :profile_id
                    AND id != :user_id
                    LIMIT 1
                ");
            } else {
                return null;
            }

            $stmt->execute([
                ':profile_id' => $profile_id,
                ':user_id' => $current_user_id
            ]);

            $connected = $stmt->fetch();

            return $connected ?: null;
        } catch (Throwable $e) {
            return null;
        }
    }
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $user_id]);
$user = $stmt->fetch();

if (!$user) {
    echo '<div class="ue-card">
            <div class="ue-card-header"><h3>User not found</h3></div>
            <div class="ue-card-body">
                <p>The selected user does not exist.</p>
                <a href="users.php" class="ui-btn">Back to Users</a>
            </div>
          </div>';

    require_once __DIR__ . '/includes/footer.php';
    exit;
}

$has_claimed_doctor = column_exists('users', 'claimed_doctor_id');
$has_claimed_hospital = column_exists('users', 'claimed_hospital_id');
$has_updated_at = column_exists('users', 'updated_at');
$has_created_at = column_exists('users', 'created_at');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $posted_token = (string)($_POST['csrf_token'] ?? '');

    if (!hash_equals($csrf_token, $posted_token)) {
        $errors[] = 'Security token mismatch. Please try again.';
    } else {
        $old_user = $user;
        $update_messages = [];

        $name = trim((string)($_POST['name'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $phone = trim((string)($_POST['phone'] ?? ''));
        $user_type = trim((string)($_POST['user_type'] ?? ''));
        $status = trim((string)($_POST['status'] ?? ''));
        $password = (string)($_POST['password'] ?? '');

        $profile_action = trim((string)($_POST['profile_action'] ?? 'keep'));
        $profile_type = trim((string)($_POST['profile_type'] ?? ''));
        $profile_link = trim((string)($_POST['profile_link'] ?? ''));

        $new_claimed_doctor_id = $has_claimed_doctor ? (int)($user['claimed_doctor_id'] ?? 0) : 0;
        $new_claimed_hospital_id = $has_claimed_hospital ? (int)($user['claimed_hospital_id'] ?? 0) : 0;

        if ($name === '') {
            $errors[] = 'Name is required.';
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Valid email is required.';
        }

        if (!in_array($user_type, ['doctor', 'hospital_owner'], true)) {
            $errors[] = 'Invalid user type.';
        }

        if (!in_array($status, ['active', 'blocked'], true)) {
            $errors[] = 'Invalid status.';
        }

        if ($password !== '' && strlen($password) < 6) {
            $errors[] = 'Password must be at least 6 characters.';
        }

        if (!in_array($profile_action, ['keep', 'connect', 'disconnect'], true)) {
            $errors[] = 'Invalid connected profile action.';
        }

        if ($profile_action === 'disconnect') {
            if ($new_claimed_doctor_id > 0 || $new_claimed_hospital_id > 0) {
                $update_messages[] = 'Connected profile disconnected.';
            }

            $new_claimed_doctor_id = 0;
            $new_claimed_hospital_id = 0;
        }

        if ($profile_action === 'connect') {
            if (!in_array($profile_type, ['doctor', 'hospital'], true)) {
                $errors[] = 'Please select Doctor or Hospital for connected profile.';
            }

            if ($profile_link === '') {
                $errors[] = 'Please enter profile link, slug, ID, name, phone, email, or registration number.';
            }

            if (!$errors) {
                $profile_ref = admin_user_edit_extract_profile_ref($profile_link, $profile_type);
                $profile = admin_user_edit_find_profile_by_ref($profile_type, $profile_ref);

                if (!$profile) {
                    $errors[] = 'No matching profile found from the given link or text.';
                } else {
                    $profile_id = (int)$profile['id'];
                    $connected_user = admin_user_edit_connected_user($profile_type, $profile_id, $user_id);

                    if ($connected_user) {
                        $errors[] = 'This profile is already connected with another user: ' . ($connected_user['name'] ?? 'User') . ' (#' . $connected_user['id'] . ').';
                    } else {
                        if ($profile_type === 'doctor') {
                            $new_claimed_doctor_id = $profile_id;
                            $new_claimed_hospital_id = 0;
                            $user_type = 'doctor';
                            $update_messages[] = 'Connected doctor profile updated to: ' . ($profile['name'] ?? ('Doctor #' . $profile_id)) . '.';
                        } else {
                            $new_claimed_hospital_id = $profile_id;
                            $new_claimed_doctor_id = 0;
                            $user_type = 'hospital_owner';
                            $update_messages[] = 'Connected hospital profile updated to: ' . ($profile['name'] ?? ('Hospital #' . $profile_id)) . '.';
                        }
                    }
                }
            }
        }

        if (!$errors) {
            $check_stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email AND id != :id LIMIT 1");
            $check_stmt->execute([
                ':email' => $email,
                ':id' => $user_id
            ]);

            if ($check_stmt->fetch()) {
                $errors[] = 'This email is already used by another user.';
            }
        }

        if (!$errors) {
            if ((string)$old_user['name'] !== $name) {
                $update_messages[] = 'Name updated from "' . $old_user['name'] . '" to "' . $name . '".';
            }

            if ((string)$old_user['email'] !== $email) {
                $update_messages[] = 'Email updated from "' . $old_user['email'] . '" to "' . $email . '".';
            }

            if ((string)($old_user['phone'] ?? '') !== $phone) {
                $update_messages[] = 'Phone updated.';
            }

            if ((string)$old_user['status'] !== $status) {
                $update_messages[] = 'Status changed to ' . ucfirst($status) . '.';
            }

            if ((string)$old_user['user_type'] !== $user_type) {
                $update_messages[] = 'User type changed to ' . ($user_type === 'hospital_owner' ? 'Hospital Owner' : 'Doctor') . '.';
            }

            if ($password !== '') {
                $update_messages[] = 'Password reset successfully.';
            }

            $params = [
                ':id' => $user_id,
                ':name' => $name,
                ':email' => $email,
                ':phone' => $phone !== '' ? $phone : null,
                ':user_type' => $user_type,
                ':status' => $status,
            ];

            $set_parts = [
                "name = :name",
                "email = :email",
                "phone = :phone",
                "user_type = :user_type",
                "status = :status",
            ];

            if ($password !== '') {
                $set_parts[] = "password = :password";
                $params[':password'] = password_hash($password, PASSWORD_DEFAULT);
            }

            if ($has_claimed_doctor) {
                $set_parts[] = "claimed_doctor_id = :claimed_doctor_id";
                $params[':claimed_doctor_id'] = $new_claimed_doctor_id > 0 ? $new_claimed_doctor_id : null;
            }

            if ($has_claimed_hospital) {
                $set_parts[] = "claimed_hospital_id = :claimed_hospital_id";
                $params[':claimed_hospital_id'] = $new_claimed_hospital_id > 0 ? $new_claimed_hospital_id : null;
            }

            if ($has_updated_at) {
                $set_parts[] = "updated_at = NOW()";
            }

            $sql = "UPDATE users SET " . implode(", ", $set_parts) . " WHERE id = :id";

            try {
                $update_stmt = $pdo->prepare($sql);
                $update_stmt->execute($params);

                if (!$update_messages) {
                    $update_messages[] = 'No major field changed, but the user record was checked successfully.';
                }

                $success_messages = $update_messages;

                $stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id LIMIT 1");
                $stmt->execute([':id' => $user_id]);
                $user = $stmt->fetch();
            } catch (Throwable $e) {
                $errors[] = 'User could not be updated. Error: ' . $e->getMessage();
            }
        }
    }
}

$claimed_doctor_id = $has_claimed_doctor ? (int)($user['claimed_doctor_id'] ?? 0) : 0;
$claimed_hospital_id = $has_claimed_hospital ? (int)($user['claimed_hospital_id'] ?? 0) : 0;

$claimed_doctor = $claimed_doctor_id > 0 ? admin_user_edit_get_profile('doctor', $claimed_doctor_id) : null;
$claimed_hospital = $claimed_hospital_id > 0 ? admin_user_edit_get_profile('hospital', $claimed_hospital_id) : null;
?>

<style>
    .ue-header {
        display: flex;
        justify-content: space-between;
        gap: 12px;
        align-items: flex-start;
        margin-bottom: 16px;
    }

    .ue-title h2 {
        margin: 0 0 6px;
        color: #24292f;
        font-size: 24px;
        font-weight: 700;
        letter-spacing: -0.02em;
    }

    .ue-title p {
        margin: 0;
        color: #57606a;
        font-size: 14px;
    }

    .ue-actions {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        justify-content: flex-end;
    }

    .ue-shell {
        display: grid;
        grid-template-columns: 340px minmax(0, 1fr);
        gap: 16px;
        align-items: start;
    }

    .ue-card {
        background: #ffffff;
        border: 1px solid #d0d7de;
        border-radius: 6px;
        overflow: hidden;
        box-shadow: 0 1px 0 rgba(27, 31, 36, 0.04);
        margin-bottom: 16px;
    }

    .ue-card-header {
        padding: 12px 14px;
        background: #f6f8fa;
        border-bottom: 1px solid #d0d7de;
    }

    .ue-card-header h3 {
        margin: 0;
        color: #24292f;
        font-size: 14px;
        font-weight: 700;
    }

    .ue-card-body {
        padding: 14px;
    }

    .ue-profile-box {
        text-align: center;
    }

    .ue-avatar {
        width: 84px;
        height: 84px;
        border-radius: 50%;
        background: #0969da;
        color: #ffffff;
        display: grid;
        place-items: center;
        font-size: 34px;
        font-weight: 700;
        margin: 4px auto 12px;
    }

    .ue-profile-box h3 {
        margin: 0 0 4px;
        color: #24292f;
        font-size: 18px;
    }

    .ue-profile-box p {
        margin: 0;
        color: #57606a;
        font-size: 13px;
        overflow-wrap: anywhere;
    }

    .ue-meta-row {
        display: flex;
        justify-content: space-between;
        gap: 12px;
        padding: 10px 0;
        border-bottom: 1px solid #d8dee4;
        font-size: 13px;
    }

    .ue-meta-row:last-child {
        border-bottom: 0;
    }

    .ue-meta-row span {
        color: #57606a;
    }

    .ue-meta-row strong {
        color: #24292f;
        text-align: right;
        overflow-wrap: anywhere;
    }

    .ue-badge {
        display: inline-flex;
        align-items: center;
        min-height: 22px;
        padding: 2px 8px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 600;
        border: 1px solid #d0d7de;
        background: #f6f8fa;
        color: #57606a;
    }

    .ue-badge.active {
        background: #dafbe1;
        color: #1a7f37;
        border-color: rgba(26, 127, 55, 0.25);
    }

    .ue-badge.blocked {
        background: #ffebe9;
        color: #cf222e;
        border-color: rgba(207, 34, 46, 0.25);
    }

    .ue-form-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 14px;
    }

    .ue-field {
        display: grid;
        gap: 6px;
    }

    .ue-field.full {
        grid-column: 1 / -1;
    }

    .ue-field label {
        color: #24292f;
        font-size: 13px;
        font-weight: 600;
    }

    .ue-help {
        color: #57606a;
        font-size: 12px;
        line-height: 1.5;
    }

    .ue-field input,
    .ue-field select {
        min-height: 36px;
        border: 1px solid #d0d7de;
        border-radius: 6px;
        background: #ffffff;
        color: #24292f;
        padding: 7px 10px;
        font-size: 13px;
        outline: none;
    }

    .ue-field input:focus,
    .ue-field select:focus {
        border-color: #0969da;
        box-shadow: 0 0 0 3px rgba(9, 105, 218, 0.12);
    }

    .ue-password-wrap {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto auto;
        gap: 8px;
    }

    .ui-btn,
    .ue-card button,
    .ue-actions .btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 32px;
        padding: 6px 12px;
        border-radius: 6px;
        border: 1px solid rgba(27, 31, 36, 0.15);
        background: #f6f8fa;
        color: #24292f;
        font-size: 13px;
        font-weight: 600;
        text-decoration: none;
        cursor: pointer;
        box-shadow: 0 1px 0 rgba(27, 31, 36, 0.04);
    }

    .ui-btn:hover,
    .ue-card button:hover {
        background: #f3f4f6;
    }

    .ui-btn.primary,
    .ue-card button.primary {
        background: #2da44e;
        color: #ffffff;
    }

    .ui-btn.primary:hover,
    .ue-card button.primary:hover {
        background: #1f883d;
    }

    .ue-alert {
        padding: 12px 14px;
        border-radius: 6px;
        margin-bottom: 16px;
        font-size: 14px;
        border: 1px solid transparent;
    }

    .ue-alert.success {
        background: #dafbe1;
        color: #1a7f37;
        border-color: rgba(26, 127, 55, 0.25);
    }

    .ue-alert.error {
        background: #ffebe9;
        color: #cf222e;
        border-color: rgba(207, 34, 46, 0.25);
    }

    .ue-alert ul {
        margin: 8px 0 0 18px;
        padding: 0;
    }

    .ue-alert li {
        margin: 4px 0;
    }

    .ue-form-footer {
        display: flex;
        justify-content: flex-end;
        gap: 8px;
        flex-wrap: wrap;
        margin-top: 16px;
        padding-top: 14px;
        border-top: 1px solid #d8dee4;
    }

    .ue-connected-empty {
        padding: 12px;
        border: 1px dashed #d0d7de;
        border-radius: 6px;
        background: #f6f8fa;
        color: #57606a;
        font-size: 13px;
        line-height: 1.6;
    }

    .ue-connected-card-wrap {
        border: 1px solid #d0d7de;
        border-radius: 6px;
        padding: 10px;
        background: #ffffff;
        overflow: hidden;
    }

    .ue-connected-desktop-scroll {
        overflow-x: auto;
        padding-bottom: 4px;
    }

    .ue-connected-desktop-scroll > * {
        min-width: 680px;
    }

    .ue-connected-card-wrap .medic-mobile-info,
    .ue-connected-card-wrap .mobile-only,
    .ue-connected-card-wrap .medic-mobile-card {
        display: none !important;
    }

    .ue-connected-card-wrap .medic-desktop-info,
    .ue-connected-card-wrap .desktop-only {
        display: grid !important;
    }

    .ue-connected-actions {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        margin-top: 10px;
        padding-top: 10px;
        border-top: 1px solid #d8dee4;
    }

    .ue-profile-link-box {
        padding: 12px;
        border-radius: 6px;
        background: #f6f8fa;
        border: 1px solid #d0d7de;
        color: #57606a;
        font-size: 13px;
        line-height: 1.6;
    }

    @media(max-width: 980px) {
        .ue-shell {
            grid-template-columns: 1fr;
        }

        .ue-form-grid {
            grid-template-columns: 1fr;
        }

        .ue-header {
            flex-direction: column;
        }

        .ue-actions {
            justify-content: flex-start;
        }

        .ue-password-wrap {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="ue-header">
    <div class="ue-title">
        <h2>Edit User</h2>
        <p>Update account details, role, status, password, and connected profile.</p>
    </div>

    <div class="ue-actions">
        <a href="users.php" class="ui-btn">Back</a>
        <a href="user-view.php?id=<?= e((string)$user['id']) ?>" class="ui-btn">View User</a>
    </div>
</div>

<?php if ($success_messages): ?>
    <div class="ue-alert success">
        <strong>User updated successfully.</strong>
        <ul>
            <?php foreach ($success_messages as $message): ?>
                <li><?= e($message) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php if ($errors): ?>
    <div class="ue-alert error">
        <strong>Update failed.</strong>
        <ul>
            <?php foreach ($errors as $error): ?>
                <li><?= e($error) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="ue-shell">
    <aside>
        <div class="ue-card">
            <div class="ue-card-body ue-profile-box">
                <div class="ue-avatar"><?= e(strtoupper(substr((string)$user['name'], 0, 1))) ?></div>
                <h3><?= e($user['name']) ?></h3>
                <p><?= e($user['email']) ?></p>

                <div style="margin-top:12px;">
                    <span class="ue-badge <?= e($user['status']) ?>"><?= e(ucfirst((string)$user['status'])) ?></span>
                </div>
            </div>
        </div>

        <div class="ue-card">
            <div class="ue-card-header">
                <h3>Account Summary</h3>
            </div>

            <div class="ue-card-body">
                <div class="ue-meta-row">
                    <span>User ID</span>
                    <strong>#<?= e((string)$user['id']) ?></strong>
                </div>

                <div class="ue-meta-row">
                    <span>User Type</span>
                    <strong><?= e($user['user_type'] === 'hospital_owner' ? 'Hospital Owner' : 'Doctor') ?></strong>
                </div>

                <div class="ue-meta-row">
                    <span>Phone</span>
                    <strong><?= e($user['phone'] ?? '—') ?></strong>
                </div>

                <div class="ue-meta-row">
                    <span>Created</span>
                    <strong><?= $has_created_at && !empty($user['created_at']) ? e(date('d M Y', strtotime((string)$user['created_at']))) : '—' ?></strong>
                </div>

                <div class="ue-meta-row">
                    <span>Updated</span>
                    <strong><?= $has_updated_at && !empty($user['updated_at']) ? e(date('d M Y', strtotime((string)$user['updated_at']))) : '—' ?></strong>
                </div>
            </div>
        </div>
    </aside>

    <main>
        <div class="ue-card">
            <div class="ue-card-header">
                <h3>Connected Profile</h3>
            </div>

            <div class="ue-card-body">
                <?php if ($claimed_doctor): ?>
                    <div class="ue-connected-card-wrap">
                        <div class="ue-connected-desktop-scroll">
                            <?php
                            $doctor = $claimed_doctor;
                            include __DIR__ . '/../includes/doctor-card.php';
                            ?>
                        </div>

                        <div class="ue-connected-actions">
                            <a href="doctor-form.php?id=<?= e((string)$claimed_doctor['id']) ?>" class="ui-btn">Edit Doctor</a>
                            <a href="user-view.php?id=<?= e((string)$user['id']) ?>#claimed-profile" class="ui-btn">Manage Claim</a>
                        </div>
                    </div>
                <?php elseif ($claimed_hospital): ?>
                    <div class="ue-connected-card-wrap">
                        <div class="ue-connected-desktop-scroll">
                            <?php
                            $hospital = $claimed_hospital;
                            include __DIR__ . '/../includes/hospital-card.php';
                            ?>
                        </div>

                        <div class="ue-connected-actions">
                            <a href="hospital-form.php?id=<?= e((string)$claimed_hospital['id']) ?>" class="ui-btn">Edit Hospital</a>
                            <a href="user-view.php?id=<?= e((string)$user['id']) ?>#claimed-profile" class="ui-btn">Manage Claim</a>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="ue-connected-empty">
                        No doctor or hospital profile connected.
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <form method="post" id="userEditForm">
            <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">

            <div class="ue-card">
                <div class="ue-card-header">
                    <h3>Profile Information</h3>
                </div>

                <div class="ue-card-body">
                    <div class="ue-form-grid">
                        <div class="ue-field">
                            <label>Name</label>
                            <input type="text" name="name" value="<?= e($user['name']) ?>" required>
                        </div>

                        <div class="ue-field">
                            <label>Email</label>
                            <input type="email" name="email" value="<?= e($user['email']) ?>" required>
                        </div>

                        <div class="ue-field">
                            <label>Phone</label>
                            <input type="text" name="phone" value="<?= e($user['phone'] ?? '') ?>">
                        </div>

                        <div class="ue-field">
                            <label>Status</label>
                            <select name="status" required>
                                <option value="active" <?= $user['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                                <option value="blocked" <?= $user['status'] === 'blocked' ? 'selected' : '' ?>>Blocked</option>
                            </select>
                        </div>

                        <div class="ue-field">
                            <label>User Type</label>
                            <select name="user_type" required>
                                <option value="doctor" <?= $user['user_type'] === 'doctor' ? 'selected' : '' ?>>Doctor</option>
                                <option value="hospital_owner" <?= $user['user_type'] === 'hospital_owner' ? 'selected' : '' ?>>Hospital Owner</option>
                            </select>
                        </div>

                        <div class="ue-field">
                            <label>New Password</label>

                            <div class="ue-password-wrap">
                                <input type="password" name="password" id="newPassword" placeholder="Leave blank to keep current password">
                                <button type="button" id="togglePassword">Show</button>
                                <button type="button" id="generatePassword">Generate</button>
                            </div>

                            <div class="ue-help">Only fill this field if you want to reset the user password.</div>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($has_claimed_doctor || $has_claimed_hospital): ?>
                <div class="ue-card">
                    <div class="ue-card-header">
                        <h3>Connected Profile Options</h3>
                    </div>

                    <div class="ue-card-body">
                        <div class="ue-form-grid">
                            <div class="ue-field">
                                <label>Profile Action</label>
                                <select name="profile_action" id="profileAction">
                                    <option value="keep">Keep Current Profile</option>
                                    <option value="connect">Connect / Replace Profile</option>
                                    <option value="disconnect">Disconnect Profile</option>
                                </select>
                            </div>

                            <div class="ue-field">
                                <label>Profile Type</label>
                                <select name="profile_type" id="profileType">
                                    <option value="doctor">Doctor</option>
                                    <option value="hospital">Hospital</option>
                                </select>
                            </div>

                            <div class="ue-field full">
                                <label>Profile Link / Slug / ID / Name / Phone / Email / Registration Number</label>
                                <input 
                                    type="text" 
                                    name="profile_link" 
                                    id="profileLink"
                                    placeholder="Example: https://domain.com/doctor/doctor-slug or doctor-slug or 15"
                                >

                                <div class="ue-help">
                                    Use this field to connect or replace profile. The system will find the profile and connect it safely.
                                </div>
                            </div>

                            <div class="ue-field full">
                                <div class="ue-profile-link-box">
                                    <strong>Current connected profile:</strong><br>
                                    <?php if ($claimed_doctor): ?>
                                        Doctor: <?= e($claimed_doctor['name'] ?? 'Doctor') ?>
                                        <?php if (!empty($claimed_doctor['slug'])): ?>
                                            <br>Link: <a href="../doctor/<?= e($claimed_doctor['slug']) ?>" target="_blank" rel="noopener">../doctor/<?= e($claimed_doctor['slug']) ?></a>
                                        <?php endif; ?>
                                    <?php elseif ($claimed_hospital): ?>
                                        Hospital: <?= e($claimed_hospital['name'] ?? 'Hospital') ?>
                                        <?php if (!empty($claimed_hospital['slug'])): ?>
                                            <br>Link: <a href="../hospital/<?= e($claimed_hospital['slug']) ?>" target="_blank" rel="noopener">../hospital/<?= e($claimed_hospital['slug']) ?></a>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        No connected profile.
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="ue-form-footer">
                <a href="users.php" class="ui-btn">Cancel</a>
                <a href="user-view.php?id=<?= e((string)$user['id']) ?>" class="ui-btn">View User</a>
                <button type="submit" class="primary">Update User</button>
            </div>
        </form>
    </main>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const passwordInput = document.getElementById('newPassword');
    const toggleBtn = document.getElementById('togglePassword');
    const generateBtn = document.getElementById('generatePassword');
    const profileAction = document.getElementById('profileAction');
    const profileType = document.getElementById('profileType');
    const profileLink = document.getElementById('profileLink');

    if (window.history && window.history.replaceState) {
        window.history.replaceState(null, '', window.location.href);
    }

    if (toggleBtn && passwordInput) {
        toggleBtn.addEventListener('click', function () {
            const isPassword = passwordInput.type === 'password';
            passwordInput.type = isPassword ? 'text' : 'password';
            toggleBtn.textContent = isPassword ? 'Hide' : 'Show';
        });
    }

    if (generateBtn && passwordInput) {
        generateBtn.addEventListener('click', function () {
            const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789@#$%';
            let password = '';

            for (let i = 0; i < 12; i++) {
                password += chars.charAt(Math.floor(Math.random() * chars.length));
            }

            passwordInput.type = 'text';
            passwordInput.value = password;

            if (toggleBtn) {
                toggleBtn.textContent = 'Hide';
            }
        });
    }

    function updateProfileFields() {
        if (!profileAction || !profileType || !profileLink) {
            return;
        }

        const isConnect = profileAction.value === 'connect';

        profileType.disabled = !isConnect;
        profileLink.disabled = !isConnect;

        if (!isConnect) {
            profileLink.value = '';
        }
    }

    if (profileAction) {
        profileAction.addEventListener('change', updateProfileFields);
        updateProfileFields();
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>