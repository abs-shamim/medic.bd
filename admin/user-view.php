<?php
require_once __DIR__ . '/includes/header.php';

if (!table_exists('users')) {
    echo '<div class="card" style="padding:20px;">
            <h2>Users table not found</h2>
            <p>Please import the updated database first.</p>
          </div>';

    require_once __DIR__ . '/includes/footer.php';
    exit;
}

$user_id = (int)($_GET['id'] ?? 0);

if ($user_id <= 0) {
    echo '<div class="card" style="padding:20px;">
            <h2>Invalid User</h2>
            <p>User ID is missing.</p>
            <a href="users.php" class="btn">Back to Users</a>
          </div>';

    require_once __DIR__ . '/includes/footer.php';
    exit;
}

if (empty($_SESSION['admin_user_view_csrf'])) {
    $_SESSION['admin_user_view_csrf'] = bin2hex(random_bytes(32));
}

$csrf_token = $_SESSION['admin_user_view_csrf'];

$errors = [];
$success = '';

$searched_profiles = [];
$searched_profile_type = '';
$searched_profile_input = '';

$search_result_limit = 100;
$initial_visible_results = 10;
$admin_user_focus_anchor = '';

if (isset($_GET['disconnected'])) {
    $success = 'Profile disconnected successfully.';
}

if (isset($_GET['connected'])) {
    $success = 'Profile connected successfully.';
}

if (isset($_GET['claim_rejected'])) {
    $success = 'Claim rejected successfully.';
}

if (!function_exists('admin_user_extract_profile_slug')) {
    function admin_user_extract_profile_slug(string $input, string $profile_type): string
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

if (!function_exists('admin_user_search_profiles')) {
    function admin_user_search_profiles(string $profile_type, string $profile_ref, int $limit = 1000): array
    {
        global $pdo;

        $profile_ref = trim($profile_ref);
        $limit = max(1, min(5000, $limit));

        if ($profile_ref === '') {
            return [];
        }

        if ($profile_type === 'doctor') {
            if (!table_exists('doctors')) {
                return [];
            }

            $select_sql = "
                SELECT 
                    d.*,
                    s.name AS specialty_name,
                    s.slug AS specialty_slug,
                    h.name AS hospital_name
                FROM doctors d
                LEFT JOIN specialties s ON s.id = d.specialty_id
                LEFT JOIN hospitals h ON h.id = d.hospital_id
            ";

            $where = [];
            $params = [];

            if (ctype_digit($profile_ref)) {
                $where[] = "d.id = :doctor_id";
                $params[':doctor_id'] = (int)$profile_ref;
            }

            if (column_exists('doctors', 'slug')) {
                $where[] = "d.slug = :doctor_slug";
                $params[':doctor_slug'] = $profile_ref;
            }

            $searchable_columns = [
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

            foreach ($searchable_columns as $column) {
                if (column_exists('doctors', $column)) {
                    $param_key = ':doctor_search_' . $i;
                    $where[] = "d.{$column} LIKE {$param_key}";
                    $params[$param_key] = '%' . $profile_ref . '%';
                    $i++;
                }
            }

            if (empty($where)) {
                return [];
            }

            $order_case_parts = [];

            if (column_exists('doctors', 'slug')) {
                $order_case_parts[] = "WHEN d.slug = :doctor_order_slug THEN 1";
                $params[':doctor_order_slug'] = $profile_ref;
            }

            if (column_exists('doctors', 'name')) {
                $order_case_parts[] = "WHEN d.name = :doctor_order_name THEN 2";
                $params[':doctor_order_name'] = $profile_ref;
            }

            $order_case = $order_case_parts
                ? "CASE " . implode(' ', $order_case_parts) . " ELSE 3 END,"
                : "";

            $sql = $select_sql . "
                WHERE " . implode(' OR ', $where) . "
                ORDER BY {$order_case} d.id DESC
                LIMIT :limit
            ";

            try {
                $stmt = $pdo->prepare($sql);

                foreach ($params as $key => $value) {
                    if ($key === ':doctor_id') {
                        $stmt->bindValue($key, $value, PDO::PARAM_INT);
                    } else {
                        $stmt->bindValue($key, $value);
                    }
                }

                $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
                $stmt->execute();

                return $stmt->fetchAll() ?: [];
            } catch (Throwable $e) {
                return [];
            }
        }

        if ($profile_type === 'hospital') {
            if (!table_exists('hospitals')) {
                return [];
            }

            $where = [];
            $params = [];

            if (ctype_digit($profile_ref)) {
                $where[] = "id = :hospital_id";
                $params[':hospital_id'] = (int)$profile_ref;
            }

            if (column_exists('hospitals', 'slug')) {
                $where[] = "slug = :hospital_slug";
                $params[':hospital_slug'] = $profile_ref;
            }

            $searchable_columns = [
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

            foreach ($searchable_columns as $column) {
                if (column_exists('hospitals', $column)) {
                    $param_key = ':hospital_search_' . $i;
                    $where[] = "{$column} LIKE {$param_key}";
                    $params[$param_key] = '%' . $profile_ref . '%';
                    $i++;
                }
            }

            if (empty($where)) {
                return [];
            }

            $order_case_parts = [];

            if (column_exists('hospitals', 'slug')) {
                $order_case_parts[] = "WHEN slug = :hospital_order_slug THEN 1";
                $params[':hospital_order_slug'] = $profile_ref;
            }

            if (column_exists('hospitals', 'name')) {
                $order_case_parts[] = "WHEN name = :hospital_order_name THEN 2";
                $params[':hospital_order_name'] = $profile_ref;
            }

            $order_case = $order_case_parts
                ? "CASE " . implode(' ', $order_case_parts) . " ELSE 3 END,"
                : "";

            $sql = "
                SELECT *
                FROM hospitals
                WHERE " . implode(' OR ', $where) . "
                ORDER BY {$order_case} id DESC
                LIMIT :limit
            ";

            try {
                $stmt = $pdo->prepare($sql);

                foreach ($params as $key => $value) {
                    if ($key === ':hospital_id') {
                        $stmt->bindValue($key, $value, PDO::PARAM_INT);
                    } else {
                        $stmt->bindValue($key, $value);
                    }
                }

                $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
                $stmt->execute();

                return $stmt->fetchAll() ?: [];
            } catch (Throwable $e) {
                return [];
            }
        }

        return [];
    }
}

if (!function_exists('admin_user_get_profile_by_id')) {
    function admin_user_get_profile_by_id(string $profile_type, int $profile_id): ?array
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
                $joins = [];
                $selects = ["d.*"];

                if (table_exists('specialties') && column_exists('doctors', 'specialty_id')) {
                    $joins[] = "LEFT JOIN specialties s ON s.id = d.specialty_id";
                    $selects[] = "s.name AS specialty_name";
                    $selects[] = "s.slug AS specialty_slug";
                } else {
                    $selects[] = "NULL AS specialty_name";
                    $selects[] = "NULL AS specialty_slug";
                }

                if (table_exists('hospitals') && column_exists('doctors', 'hospital_id')) {
                    $joins[] = "LEFT JOIN hospitals h ON h.id = d.hospital_id";
                    $selects[] = "h.name AS hospital_name";
                } else {
                    $selects[] = "NULL AS hospital_name";
                }

                $stmt = $pdo->prepare("
                    SELECT " . implode(', ', $selects) . "
                    FROM doctors d
                    " . implode(' ', $joins) . "
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
                $stmt = $pdo->prepare("
                    SELECT *
                    FROM hospitals
                    WHERE id = :id
                    LIMIT 1
                ");

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

if (!function_exists('admin_user_get_connected_user')) {
    function admin_user_get_connected_user(string $profile_type, int $profile_id): ?array
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
                    LIMIT 1
                ");
            } elseif ($profile_type === 'hospital') {
                $stmt = $pdo->prepare("
                    SELECT id, name, email
                    FROM users
                    WHERE claimed_hospital_id = :profile_id
                    LIMIT 1
                ");
            } else {
                return null;
            }

            $stmt->execute([':profile_id' => $profile_id]);
            $user = $stmt->fetch();

            return $user ?: null;
        } catch (Throwable $e) {
            return null;
        }
    }
}

if (!function_exists('admin_user_reject_pending_claims')) {
    function admin_user_reject_pending_claims(int $user_id): bool
    {
        global $pdo;

        if ($user_id <= 0 || !table_exists('profile_claims')) {
            return true;
        }

        $status_values = ['rejected', 'declined'];

        foreach ($status_values as $status_value) {
            try {
                $sql = "UPDATE profile_claims SET status = :status";

                $params = [
                    ':status' => $status_value,
                    ':user_id' => $user_id,
                ];

                if (column_exists('profile_claims', 'admin_note')) {
                    $sql .= ", admin_note = :admin_note";
                    $params[':admin_note'] = 'Rejected / disconnected from admin user view page.';
                }

                if (column_exists('profile_claims', 'updated_at')) {
                    $sql .= ", updated_at = NOW()";
                }

                $sql .= " WHERE user_id = :user_id AND status = 'pending'";

                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);

                return true;
            } catch (Throwable $e) {
                continue;
            }
        }

        return false;
    }
}

if (!function_exists('admin_user_reject_single_claim')) {
    function admin_user_reject_single_claim(int $claim_id, int $user_id): bool
    {
        global $pdo;

        if ($claim_id <= 0 || $user_id <= 0 || !table_exists('profile_claims')) {
            return false;
        }

        $status_values = ['rejected', 'declined'];

        foreach ($status_values as $status_value) {
            try {
                $sql = "UPDATE profile_claims SET status = :status";

                $params = [
                    ':status' => $status_value,
                    ':claim_id' => $claim_id,
                    ':user_id' => $user_id,
                ];

                if (column_exists('profile_claims', 'admin_note')) {
                    $sql .= ", admin_note = :admin_note";
                    $params[':admin_note'] = 'Rejected from admin user view page.';
                }

                if (column_exists('profile_claims', 'updated_at')) {
                    $sql .= ", updated_at = NOW()";
                }

                $sql .= " WHERE id = :claim_id AND user_id = :user_id LIMIT 1";

                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);

                return true;
            } catch (Throwable $e) {
                continue;
            }
        }

        return false;
    }
}

if (!function_exists('admin_user_mark_matching_claim_approved')) {
    function admin_user_mark_matching_claim_approved(int $user_id, string $profile_type, int $profile_id): bool
    {
        global $pdo;

        if ($user_id <= 0 || $profile_id <= 0 || !table_exists('profile_claims')) {
            return true;
        }

        try {
            $where = "user_id = :user_id";
            $params = [
                ':user_id' => $user_id,
                ':profile_id' => $profile_id,
                ':status' => 'approved',
            ];

            if ($profile_type === 'doctor' && column_exists('profile_claims', 'doctor_id')) {
                $where .= " AND doctor_id = :profile_id";
            } elseif ($profile_type === 'hospital' && column_exists('profile_claims', 'hospital_id')) {
                $where .= " AND hospital_id = :profile_id";
            } elseif (column_exists('profile_claims', 'claim_type')) {
                $where .= " AND claim_type = :claim_type";
                $params[':claim_type'] = $profile_type;
                unset($params[':profile_id']);
            } else {
                unset($params[':profile_id']);
            }

            $sql = "UPDATE profile_claims SET status = :status";

            if (column_exists('profile_claims', 'admin_note')) {
                $sql .= ", admin_note = :admin_note";
                $params[':admin_note'] = 'Profile connected and approved by admin.';
            }

            if (column_exists('profile_claims', 'updated_at')) {
                $sql .= ", updated_at = NOW()";
            }

            $sql .= " WHERE {$where}";

            if (column_exists('profile_claims', 'status')) {
                $sql .= " AND status IN ('pending', 'rejected', 'declined', 'disconnected')";
            }

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            return true;
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('admin_user_disconnect_claims')) {
    function admin_user_disconnect_claims(int $user_id): bool
    {
        global $pdo;

        if ($user_id <= 0 || !table_exists('profile_claims')) {
            return true;
        }

        $status_values = ['disconnected', 'declined', 'rejected'];

        foreach ($status_values as $status_value) {
            try {
                $sql = "UPDATE profile_claims SET status = :status";

                $params = [
                    ':status' => $status_value,
                    ':user_id' => $user_id,
                ];

                if (column_exists('profile_claims', 'admin_note')) {
                    $sql .= ", admin_note = :admin_note";
                    $params[':admin_note'] = 'Profile disconnected by admin.';
                }

                if (column_exists('profile_claims', 'updated_at')) {
                    $sql .= ", updated_at = NOW()";
                }

                $sql .= " WHERE user_id = :user_id AND status IN ('pending', 'approved')";

                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);

                return true;
            } catch (Throwable $e) {
                continue;
            }
        }

        return false;
    }
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $posted_token = (string)($_POST['csrf_token'] ?? '');

    if (!hash_equals($csrf_token, $posted_token)) {
        $errors[] = 'Security token mismatch. Please try again.';
    } else {
        $action = (string)($_POST['action'] ?? '');

        if ($action === 'connect_profile') {
            $profile_type = trim((string)($_POST['profile_type'] ?? ''));
            $profile_id = (int)($_POST['profile_id'] ?? 0);

            if (!in_array($profile_type, ['doctor', 'hospital'], true)) {
                $errors[] = 'Invalid profile type.';
            }

            if ($profile_id <= 0) {
                $errors[] = 'Invalid profile ID.';
            }

            if (!$errors) {
                $already_connected_user = admin_user_get_connected_user($profile_type, $profile_id);

                if ($already_connected_user && (int)$already_connected_user['id'] !== $user_id) {
                    $errors[] = 'This profile is already connected with another user.';
                } else {
                    $profile = admin_user_get_profile_by_id($profile_type, $profile_id);

                    if (!$profile) {
                        $errors[] = 'Selected profile was not found.';
                    } else {
                        try {
                            if ($profile_type === 'doctor') {
                                $user_update_parts = [
                                    "claimed_doctor_id = :profile_id",
                                    "claimed_hospital_id = NULL",
                                    "user_type = 'doctor'",
                                    "status = 'active'",
                                ];
                            } else {
                                $user_update_parts = [
                                    "claimed_hospital_id = :profile_id",
                                    "claimed_doctor_id = NULL",
                                    "user_type = 'hospital_owner'",
                                    "status = 'active'",
                                ];
                            }

                            if (column_exists('users', 'updated_at')) {
                                $user_update_parts[] = "updated_at = NOW()";
                            }

                            $stmt = $pdo->prepare("
                                UPDATE users
                                SET " . implode(', ', $user_update_parts) . "
                                WHERE id = :user_id
                            ");

                            $stmt->execute([
                                ':profile_id' => $profile_id,
                                ':user_id' => $user_id,
                            ]);

                            admin_user_mark_matching_claim_approved($user_id, $profile_type, $profile_id);

                            $success = 'Profile connected successfully.';
                            $admin_user_focus_anchor = 'claimed-profile';
                        } catch (Throwable $e) {
                            $errors[] = 'Profile could not be connected. Please check users table columns.';
                        }
                    }
                }
            }
        }

        if ($action === 'disconnect_profile') {
            try {
                $user_update_parts = [
                    "claimed_doctor_id = NULL",
                    "claimed_hospital_id = NULL",
                ];

                if (column_exists('users', 'updated_at')) {
                    $user_update_parts[] = "updated_at = NOW()";
                }

                $stmt = $pdo->prepare("
                    UPDATE users
                    SET " . implode(', ', $user_update_parts) . "
                    WHERE id = :user_id
                ");

                $stmt->execute([':user_id' => $user_id]);

                admin_user_disconnect_claims($user_id);

                $success = 'Profile disconnected successfully.';
                $admin_user_focus_anchor = 'claimed-profile';
            } catch (Throwable $e) {
                $errors[] = 'Claimed profile could not be disconnected.';
            }
        }

        if ($action === 'reject_claim') {
            $claim_id = (int)($_POST['claim_id'] ?? 0);

            if (admin_user_reject_single_claim($claim_id, $user_id)) {
                $success = 'Claim rejected successfully.';
                $admin_user_focus_anchor = 'profile-claims';
            } else {
                $errors[] = 'Claim could not be rejected.';
            }
        }
    }
}

if (isset($_GET['profile_input']) && trim((string)$_GET['profile_input']) !== '') {
    $searched_profile_type = trim((string)($_GET['profile_type'] ?? ''));
    $searched_profile_input = trim((string)($_GET['profile_input'] ?? ''));

    if (!in_array($searched_profile_type, ['doctor', 'hospital'], true)) {
        $errors[] = 'Please select Doctor or Hospital.';
    }

    if ($searched_profile_input === '') {
        $errors[] = 'Please enter name, BMDC / registration number, email, phone, WhatsApp, full profile link, slug, or ID.';
    }

    if (!$errors) {
        $profile_ref = admin_user_extract_profile_slug($searched_profile_input, $searched_profile_type);

        $searched_profiles = admin_user_search_profiles(
            $searched_profile_type,
            $profile_ref,
            $search_result_limit
        );

        if (!empty($searched_profiles)) {
            $success = count($searched_profiles) . ' profile result loaded.';
        } else {
            $errors[] = 'No matching profile found. Please check name, BMDC / registration number, email, phone, WhatsApp, link, slug, or ID.';
        }
    }
}

$stmt = $pdo->prepare("
    SELECT *
    FROM users
    WHERE id = :id
    LIMIT 1
");

$stmt->execute([':id' => $user_id]);
$user = $stmt->fetch();

if (!$user) {
    echo '<div class="card" style="padding:20px;">
            <h2>User not found</h2>
            <p>The selected user does not exist.</p>
            <a href="users.php" class="btn">Back to Users</a>
          </div>';

    require_once __DIR__ . '/includes/footer.php';
    exit;
}

$claimed_doctor = null;
$claimed_hospital = null;

if (!empty($user['claimed_doctor_id'])) {
    $claimed_doctor = admin_user_get_profile_by_id('doctor', (int)$user['claimed_doctor_id']);
}

if (!empty($user['claimed_hospital_id'])) {
    $claimed_hospital = admin_user_get_profile_by_id('hospital', (int)$user['claimed_hospital_id']);
}

$claims = [];
$requests = [];

if (table_exists('profile_claims')) {
    try {
        if (column_exists('profile_claims', 'doctor_id') && column_exists('profile_claims', 'hospital_id')) {
            $claim_stmt = $pdo->prepare("
                SELECT 
                    pc.*,
                    d.name AS doctor_name,
                    d.slug AS doctor_slug,
                    h.name AS hospital_name,
                    h.slug AS hospital_slug
                FROM profile_claims pc
                LEFT JOIN doctors d ON d.id = pc.doctor_id
                LEFT JOIN hospitals h ON h.id = pc.hospital_id
                WHERE pc.user_id = :user_id
                ORDER BY pc.id DESC
                LIMIT 10
            ");
        } else {
            $claim_stmt = $pdo->prepare("
                SELECT *
                FROM profile_claims
                WHERE user_id = :user_id
                ORDER BY id DESC
                LIMIT 10
            ");
        }

        $claim_stmt->execute([':user_id' => $user_id]);
        $claims = $claim_stmt->fetchAll();
    } catch (Throwable $e) {
        $claims = [];
    }
}

if (table_exists('profile_update_requests')) {
    try {
        $request_stmt = $pdo->prepare("
            SELECT *
            FROM profile_update_requests
            WHERE user_id = :user_id
            ORDER BY id DESC
            LIMIT 10
        ");

        $request_stmt->execute([':user_id' => $user_id]);
        $requests = $request_stmt->fetchAll();
    } catch (Throwable $e) {
        $requests = [];
    }
}
?>

<style>
    .user-view-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 14px;
        flex-wrap: wrap;
        margin-bottom: 18px;
    }

    .user-view-header h2 {
        margin: 0 0 6px;
        color: #24292f;
        font-size: 24px;
        font-weight: 700;
        letter-spacing: -0.02em;
    }

    .user-view-header p {
        margin: 0;
        color: #57606a;
        font-size: 14px;
    }

    .user-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 16px;
    }

    .user-card {
        background: #ffffff;
        border: 1px solid #d0d7de;
        border-radius: 6px;
        padding: 0;
        box-shadow: 0 1px 0 rgba(27, 31, 36, 0.04);
        overflow: hidden;
    }

    .user-card h3,
    .user-card h4 {
        margin: 0;
        padding: 14px 16px;
        color: #24292f;
        font-size: 15px;
        font-weight: 700;
        background: #f6f8fa;
        border-bottom: 1px solid #d0d7de;
    }

    .user-card-body {
        padding: 16px;
    }

    .user-info-row {
        display: grid;
        grid-template-columns: 170px 1fr;
        gap: 12px;
        padding: 10px 0;
        border-bottom: 1px solid #d8dee4;
    }

    .user-info-row:last-child {
        border-bottom: 0;
    }

    .user-info-row span {
        color: #57606a;
        font-size: 13px;
    }

    .user-info-row strong {
        color: #24292f;
        font-size: 13px;
        font-weight: 600;
    }

    .user-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 22px;
        padding: 2px 8px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 600;
        background: #f6f8fa;
        color: #57606a;
        border: 1px solid #d0d7de;
        white-space: nowrap;
    }

    .user-badge.active,
    .user-badge.approved {
        background: #dafbe1;
        color: #1a7f37;
        border-color: rgba(26, 127, 55, 0.25);
    }

    .user-badge.blocked,
    .user-badge.rejected,
    .user-badge.declined,
    .user-badge.disconnected {
        background: #ffebe9;
        color: #cf222e;
        border-color: rgba(207, 34, 46, 0.25);
    }

    .user-badge.pending {
        background: #fff8c5;
        color: #9a6700;
        border-color: rgba(154, 103, 0, 0.25);
    }

    .user-actions,
    .claim-preview-actions {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        margin-top: 14px;
    }

    .user-actions form,
    .claim-preview-actions form,
    .claim-table-action-form {
        margin: 0;
        padding: 0;
        border: 0;
        background: transparent;
        box-shadow: none;
    }

    .user-card .btn,
    .user-actions .btn,
    .claim-preview-actions .btn,
    .user-card button,
    .claim-preview-actions button {
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

    .user-card button[type="submit"],
    .claim-preview-actions button[type="submit"] {
        background: #2da44e;
        color: #ffffff;
        border-color: rgba(27, 31, 36, 0.15);
    }

    .user-card button[type="submit"]:hover {
        background: #1f883d;
    }

    .btn-danger {
        background: #cf222e !important;
        color: #ffffff !important;
        border-color: rgba(27, 31, 36, 0.15) !important;
    }

    .btn-warning {
        background: #fb8500 !important;
        color: #ffffff !important;
        border-color: rgba(27, 31, 36, 0.15) !important;
    }

    .profile-empty-box {
        padding: 14px;
        border-radius: 6px;
        background: #f6f8fa;
        border: 1px dashed #d0d7de;
        color: #57606a;
        font-size: 14px;
    }

    .profile-action-note {
        margin: 12px 0 0;
        color: #57606a;
        font-size: 13px;
        line-height: 1.6;
    }

    .claim-search-box {
        margin-top: 16px;
        padding: 16px;
        border-radius: 6px;
        background: #f6f8fa;
        border: 1px solid #d0d7de;
    }

    .claim-search-box h4 {
        padding: 0;
        margin: 0 0 12px;
        background: transparent;
        border: 0;
        font-size: 14px;
    }

    .claim-search-grid {
        display: grid;
        grid-template-columns: 150px 1fr auto;
        gap: 10px;
        align-items: center;
    }

    .claim-search-grid input,
    .claim-search-grid select {
        min-height: 34px;
        border: 1px solid #d0d7de;
        border-radius: 6px;
        background: #ffffff;
        color: #24292f;
        font-size: 13px;
        padding: 6px 10px;
    }

    .user-alert {
        padding: 12px 14px;
        border-radius: 6px;
        margin-bottom: 16px;
        font-size: 14px;
        border: 1px solid transparent;
    }

    .user-alert.success {
        background: #dafbe1;
        color: #1a7f37;
        border-color: rgba(26, 127, 55, 0.25);
    }

    .user-alert.error {
        background: #ffebe9;
        color: #cf222e;
        border-color: rgba(207, 34, 46, 0.25);
    }

    .mini-table-wrap table {
        border: 0;
        box-shadow: none;
        border-radius: 0;
    }

    .mini-table-wrap table th {
        background: #f6f8fa;
        color: #57606a;
        font-size: 12px;
        text-transform: none;
        letter-spacing: 0;
    }

    .mini-table-wrap table th,
    .mini-table-wrap table td {
        padding: 10px 12px;
        border-bottom: 1px solid #d8dee4;
        font-size: 13px;
    }

    .medic-list-card {
        background: #ffffff;
        border: 1px solid #d0d7de;
        border-radius: 6px;
        padding: 12px;
        margin-top: 14px;
        overflow: hidden;
        box-shadow: 0 1px 0 rgba(27, 31, 36, 0.04);
    }

    /*
     * doctor-card.php renders a complete card with its own border, radius,
     * shadow and hover animation, so avoid drawing a second border/shadow
     * (and clipping its hover shadow via overflow:hidden) behind it here.
     */
    .medic-list-card:has(.medic-doctor-list-item) {
        background: transparent;
        border: 0;
        padding: 0;
        overflow: visible;
        box-shadow: none;
    }

    .search-result-count {
        margin: 12px 0 0;
        color: #0969da;
        font-size: 13px;
        font-weight: 600;
    }

    .connected-note {
        width: 100%;
        font-size: 13px;
        color: #9a6700;
        background: #fff8c5;
        border: 1px solid rgba(154, 103, 0, 0.25);
        border-radius: 6px;
        padding: 10px 12px;
    }

    .claim-result-item.is-hidden-result {
        display: none;
    }

    .show-more-wrap {
        margin-top: 18px;
        padding-top: 16px;
        border-top: 1px solid #d8dee4;
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
    }

    .show-more-wrap button {
        min-height: 34px;
        padding: 6px 14px;
        border-radius: 6px;
        border: 1px solid rgba(27, 31, 36, 0.15);
        background: #2da44e;
        color: #ffffff;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
    }

    .show-more-wrap button:hover {
        background: #1f883d;
    }

    @media(max-width: 900px) {
        .user-info-row {
            grid-template-columns: 1fr;
            gap: 4px;
        }

        .claim-search-grid {
            grid-template-columns: 1fr;
        }

        .admin-main table {
            display: block;
            overflow-x: auto;
            white-space: nowrap;
        }
    }
</style>

<div class="user-view-header">
    <div>
        <h2>User Details</h2>
        <p>Full information for <?= e($user['name']) ?>.</p>
    </div>

    <div class="user-actions">
        <a href="users.php" class="btn">Back</a>
        <a href="user-edit.php?id=<?= e((string)$user['id']) ?>" class="btn">Edit User</a>
    </div>
</div>

<?php if ($success): ?>
    <div class="user-alert success"><?= e($success) ?></div>
<?php endif; ?>

<?php if ($errors): ?>
    <div class="user-alert error">
        <?php foreach ($errors as $error): ?>
            <div><?= e($error) ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="user-grid">

    <div class="user-card">
        <h3>Basic Information</h3>

        <div class="user-card-body">
            <div class="user-info-row">
                <span>User ID</span>
                <strong>#<?= e((string)$user['id']) ?></strong>
            </div>

            <div class="user-info-row">
                <span>Name</span>
                <strong><?= e($user['name']) ?></strong>
            </div>

            <div class="user-info-row">
                <span>Email</span>
                <strong><?= e($user['email']) ?></strong>
            </div>

            <div class="user-info-row">
                <span>Phone</span>
                <strong><?= e($user['phone'] ?? '—') ?></strong>
            </div>

            <div class="user-info-row">
                <span>User Type</span>
                <strong><?= e($user['user_type'] === 'hospital_owner' ? 'Hospital Owner' : 'Doctor') ?></strong>
            </div>

            <div class="user-info-row">
                <span>Status</span>
                <strong>
                    <span class="user-badge <?= e($user['status']) ?>">
                        <?= e(ucfirst((string)$user['status'])) ?>
                    </span>
                </strong>
            </div>

            <div class="user-info-row">
                <span>Created At</span>
                <strong><?= !empty($user['created_at']) ? e(date('d M Y h:i A', strtotime((string)$user['created_at']))) : '—' ?></strong>
            </div>

            <div class="user-info-row">
                <span>Updated At</span>
                <strong><?= !empty($user['updated_at']) ? e(date('d M Y h:i A', strtotime((string)$user['updated_at']))) : '—' ?></strong>
            </div>
        </div>
    </div>

    <div class="user-card" id="claimed-profile">
        <h3>Claimed Profile</h3>

        <div class="user-card-body">
            <?php if ($claimed_doctor): ?>
                <div class="medic-list-card">
                    <?php
                    $doctor = $claimed_doctor;
                    include __DIR__ . '/../includes/doctor-card.php';
                    ?>
                </div>

                <div class="claim-preview-actions">
                    <a href="doctor-form.php?id=<?= e((string)$claimed_doctor['id']) ?>" class="btn">Edit Doctor</a>

                    <form method="post" onsubmit="return confirm('Are you sure you want to disconnect this profile?');">
                        <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">
                        <input type="hidden" name="action" value="disconnect_profile">
                        <button type="submit" class="btn-danger">Disconnect</button>
                    </form>
                </div>

            <?php elseif ($claimed_hospital): ?>
                <div class="medic-list-card">
                    <?php
                    $hospital = $claimed_hospital;
                    include __DIR__ . '/../includes/hospital-card.php';
                    ?>
                </div>

                <div class="claim-preview-actions">
                    <a href="hospital-form.php?id=<?= e((string)$claimed_hospital['id']) ?>" class="btn">Edit Hospital</a>

                    <form method="post" onsubmit="return confirm('Are you sure you want to disconnect this profile?');">
                        <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">
                        <input type="hidden" name="action" value="disconnect_profile">
                        <button type="submit" class="btn-danger">Disconnect</button>
                    </form>
                </div>

            <?php else: ?>
                <div class="profile-empty-box">
                    No doctor or hospital profile is connected with this user yet.
                </div>
            <?php endif; ?>

            <div class="claim-search-box" id="claim-search">
                <h4>Search Profile Before Connect</h4>

                <form method="get" action="user-view.php#claim-search">
                    <input type="hidden" name="id" value="<?= e((string)$user_id) ?>">

                    <div class="claim-search-grid">
                        <select name="profile_type" required>
                            <option value="doctor" <?= $searched_profile_type === 'doctor' ? 'selected' : '' ?>>Doctor</option>
                            <option value="hospital" <?= $searched_profile_type === 'hospital' ? 'selected' : '' ?>>Hospital</option>
                        </select>

                        <input
                            type="text"
                            name="profile_input"
                            placeholder="Name, BMDC / Registration No, Email, Phone, WhatsApp, full link, slug, or ID"
                            value="<?= e($searched_profile_input) ?>"
                            required
                        >

                        <button type="submit">Search</button>
                    </div>
                </form>

                <p class="profile-action-note">
                    Search by <strong>Name</strong>, <strong>BMDC / Registration Number</strong>, <strong>Email</strong>, <strong>Phone</strong>, <strong>WhatsApp</strong>, <strong>full profile link</strong>, <strong>slug</strong>, or <strong>ID</strong>.
                </p>

                <?php if (!empty($searched_profiles)): ?>
                    <p class="search-result-count">
                        Showing search results. First <?= e((string)$initial_visible_results) ?> are visible.
                    </p>

                    <div style="margin-top:16px;">
                        <h4>Search Result Preview</h4>

                        <?php foreach ($searched_profiles as $index => $searched_profile): ?>
                            <?php
                            $profile_id = (int)($searched_profile['id'] ?? 0);
                            $connected_user = admin_user_get_connected_user($searched_profile_type, $profile_id);
                            $is_connected_to_other_user = $connected_user && (int)$connected_user['id'] !== $user_id;
                            $is_connected_to_current_user = $connected_user && (int)$connected_user['id'] === $user_id;
                            $is_hidden_result = $index >= $initial_visible_results;
                            ?>

                            <div class="claim-result-item <?= $is_hidden_result ? 'is-hidden-result' : '' ?>">
                                <?php if ($searched_profile_type === 'doctor'): ?>
                                    <?php $doctor = $searched_profile; ?>

                                    <div class="medic-list-card">
                                        <?php include __DIR__ . '/../includes/doctor-card.php'; ?>
                                    </div>
                                <?php endif; ?>

                                <?php if ($searched_profile_type === 'hospital'): ?>
                                    <?php $hospital = $searched_profile; ?>

                                    <div class="medic-list-card">
                                        <?php include __DIR__ . '/../includes/hospital-card.php'; ?>
                                    </div>
                                <?php endif; ?>

                                <div class="claim-preview-actions">
                                    <?php if ($is_connected_to_other_user): ?>
                                        <a
                                            href="user-view.php?id=<?= e((string)$connected_user['id']) ?>"
                                            target="_blank"
                                            rel="noopener"
                                            class="btn btn-warning"
                                        >
                                            Already Connected
                                        </a>

                                        <div class="connected-note">
                                            Connected with: <?= e($connected_user['name'] ?? 'User') ?><?= !empty($connected_user['email']) ? ' (' . e($connected_user['email']) . ')' : '' ?>
                                        </div>
                                    <?php elseif ($is_connected_to_current_user): ?>
                                        <button type="button" class="btn btn-warning">Already Connected With This User</button>
                                    <?php else: ?>
                                        <form method="post" onsubmit="return confirm('Connect this profile with this user?');">
                                            <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">
                                            <input type="hidden" name="action" value="connect_profile">
                                            <input type="hidden" name="profile_type" value="<?= e($searched_profile_type) ?>">
                                            <input type="hidden" name="profile_id" value="<?= e((string)$profile_id) ?>">
                                            <button type="submit">Confirm Connect</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>

                        <?php if (count($searched_profiles) > $initial_visible_results): ?>
                            <div class="show-more-wrap">
                                <button type="button" id="claimShowMoreBtn" data-step="10">
                                    Show More
                                </button>

                                <p id="claimResultCounter" class="profile-action-note">
                                    Showing <?= e((string)$initial_visible_results) ?> of <?= e((string)count($searched_profiles)) ?> results.
                                </p>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="user-card" id="profile-claims">
        <h3>Recent Profile Claims</h3>

        <div class="user-card-body" style="padding:0;">
            <div class="mini-table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Claim Type</th>
                            <th>Profile</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Action</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php if (!$claims): ?>
                            <tr>
                                <td colspan="6" style="text-align:center;color:#57606a;">No claims found.</td>
                            </tr>
                        <?php endif; ?>

                        <?php foreach ($claims as $claim): ?>
                            <?php
                            $claim_profile = '—';

                            if (!empty($claim['doctor_name'])) {
                                $claim_profile = 'Doctor: ' . $claim['doctor_name'];
                            } elseif (!empty($claim['hospital_name'])) {
                                $claim_profile = 'Hospital: ' . $claim['hospital_name'];
                            } elseif (!empty($claim['name'])) {
                                $claim_profile = $claim['name'];
                            }
                            ?>

                            <tr>
                                <td>#<?= e((string)$claim['id']) ?></td>
                                <td><?= e($claim['claim_type'] ?? '—') ?></td>
                                <td><?= e($claim_profile) ?></td>
                                <td>
                                    <span class="user-badge <?= e($claim['status'] ?? '') ?>">
                                        <?= e(ucfirst((string)($claim['status'] ?? '—'))) ?>
                                    </span>
                                </td>
                                <td><?= !empty($claim['created_at']) ? e(date('d M Y', strtotime((string)$claim['created_at']))) : '—' ?></td>
                                <td>
                                    <?php if (($claim['status'] ?? '') === 'pending'): ?>
                                        <form method="post" class="claim-table-action-form" onsubmit="return confirm('Are you sure you want to reject this claim?');">
                                            <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">
                                            <input type="hidden" name="action" value="reject_claim">
                                            <input type="hidden" name="claim_id" value="<?= e((string)$claim['id']) ?>">
                                            <button type="submit" class="btn-danger">Reject</button>
                                        </form>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="user-card">
        <h3>Recent Update Requests</h3>

        <div class="user-card-body" style="padding:0;">
            <div class="mini-table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Request Type</th>
                            <th>Status</th>
                            <th>Created</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php if (!$requests): ?>
                            <tr>
                                <td colspan="4" style="text-align:center;color:#57606a;">No update requests found.</td>
                            </tr>
                        <?php endif; ?>

                        <?php foreach ($requests as $request): ?>
                            <tr>
                                <td>#<?= e((string)$request['id']) ?></td>
                                <td><?= e($request['request_type'] ?? '—') ?></td>
                                <td>
                                    <span class="user-badge <?= e($request['status'] ?? '') ?>">
                                        <?= e(ucfirst((string)($request['status'] ?? '—'))) ?>
                                    </span>
                                </td>
                                <td><?= !empty($request['created_at']) ? e(date('d M Y', strtotime((string)$request['created_at']))) : '—' ?></td>
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
    const showMoreBtn = document.getElementById('claimShowMoreBtn');
    const counter = document.getElementById('claimResultCounter');

    if (!showMoreBtn) {
        return;
    }

    const step = parseInt(showMoreBtn.dataset.step || '10', 10);

    function hiddenItems() {
        return Array.from(document.querySelectorAll('.claim-result-item.is-hidden-result'));
    }

    const allItems = Array.from(document.querySelectorAll('.claim-result-item'));

    function updateCounter() {
        if (!counter) {
            return;
        }

        const visibleCount = allItems.filter(function (item) {
            return !item.classList.contains('is-hidden-result');
        }).length;

        counter.textContent = 'Showing ' + visibleCount + ' of ' + allItems.length + ' results.';
    }

    showMoreBtn.addEventListener('click', function () {
        const itemsToShow = hiddenItems().slice(0, step);

        itemsToShow.forEach(function (item) {
            item.classList.remove('is-hidden-result');
        });

        updateCounter();

        if (hiddenItems().length === 0) {
            showMoreBtn.style.display = 'none';
        }
    });

    updateCounter();

    const focusAnchor = <?= json_encode($admin_user_focus_anchor) ?>;

    if (focusAnchor) {
        const focusElement = document.getElementById(focusAnchor);

        if (focusElement) {
            setTimeout(function () {
                focusElement.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }, 120);
        }
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>