<?php
require_once __DIR__ . '/../includes/auth.php';

require_user_login();

$auth_user = function_exists('current_user_data') ? current_user_data() : null;

if (!$auth_user) {
    $auth_user = $_SESSION['user'] ?? null;
}

if (!$auth_user || empty($auth_user['id'])) {
    redirect('login.php');
}

$current_user_id = (int)$auth_user['id'];
$page_title = 'Claim Profile';

$errors = [];
$success = '';

if (!empty($_GET['success'])) {
    $success = trim((string)$_GET['success']);
}

$searched_profiles = [];
$searched_profile_type = '';
$searched_profile_input = '';

$search_result_limit = 1000;
$initial_visible_results = 10;

if (!function_exists('uc_claim_add_column_if_missing')) {
    function uc_claim_add_column_if_missing(string $table, string $column, string $definition): void
    {
        global $pdo;

        if (!table_exists($table) || column_exists($table, $column)) {
            return;
        }

        try {
            $pdo->exec("ALTER TABLE {$table} ADD COLUMN {$column} {$definition}");
        } catch (Throwable $e) {
            // Keep page working if ALTER permission is not available.
        }
    }
}

if (!function_exists('uc_claim_ensure_columns')) {
    function uc_claim_ensure_columns(): void
    {
        uc_claim_add_column_if_missing('users', 'claimed_doctor_id', "INT UNSIGNED DEFAULT NULL");
        uc_claim_add_column_if_missing('users', 'claimed_hospital_id', "INT UNSIGNED DEFAULT NULL");

        uc_claim_add_column_if_missing('profile_claims', 'doctor_id', "INT UNSIGNED DEFAULT NULL");
        uc_claim_add_column_if_missing('profile_claims', 'hospital_id', "INT UNSIGNED DEFAULT NULL");
        uc_claim_add_column_if_missing('profile_claims', 'name', "VARCHAR(190) NULL");
        uc_claim_add_column_if_missing('profile_claims', 'specialty', "VARCHAR(190) NULL");
        uc_claim_add_column_if_missing('profile_claims', 'phone', "VARCHAR(50) NULL");
        uc_claim_add_column_if_missing('profile_claims', 'email', "VARCHAR(190) NULL");
        uc_claim_add_column_if_missing('profile_claims', 'message', "TEXT NULL");
        uc_claim_add_column_if_missing('profile_claims', 'license_number', "VARCHAR(190) NULL");
        uc_claim_add_column_if_missing('profile_claims', 'proof_text', "TEXT NULL");
        uc_claim_add_column_if_missing('profile_claims', 'admin_note', "TEXT NULL");
        uc_claim_add_column_if_missing('profile_claims', 'created_at', "DATETIME NULL");
        uc_claim_add_column_if_missing('profile_claims', 'updated_at', "DATETIME NULL");
    }
}

if (!function_exists('uc_claim_render_profile_card')) {
    function uc_claim_render_profile_card(string $profile_type, array $profile): void
    {
        if ($profile_type === 'doctor') {
            $doctor = $profile;
            $doctor_card = __DIR__ . '/../includes/doctor-card.php';

            if (file_exists($doctor_card)) {
                include $doctor_card;
                return;
            }

            echo '<div class="profile-empty-box">Doctor card file not found.</div>';
            return;
        }

        if ($profile_type === 'hospital') {
            $hospital = $profile;
            $hospital_card = __DIR__ . '/../includes/hospital-card.php';

            if (file_exists($hospital_card)) {
                include $hospital_card;
                return;
            }

            echo '<div class="profile-empty-box">Hospital card file not found.</div>';
            return;
        }
    }
}

if (!function_exists('uc_claim_extract_profile_slug')) {
    function uc_claim_extract_profile_slug(string $input, string $profile_type): string
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

if (!function_exists('uc_claim_search_profiles')) {
    function uc_claim_search_profiles(string $profile_type, string $profile_ref, int $limit = 1000): array
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

            $specialty_join = table_exists('specialties') ? "LEFT JOIN specialties s ON s.id = d.specialty_id" : "";
            $hospital_join = table_exists('hospitals') ? "LEFT JOIN hospitals h ON h.id = d.hospital_id" : "";

            $specialty_select = table_exists('specialties')
                ? "s.name AS specialty_name, s.slug AS specialty_slug,"
                : "NULL AS specialty_name, NULL AS specialty_slug,";

            $hospital_select = table_exists('hospitals')
                ? "h.name AS hospital_name,"
                : "NULL AS hospital_name,";

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

            $sql = "
                SELECT
                    d.*,
                    {$specialty_select}
                    {$hospital_select}
                    'doctor' AS profile_type
                FROM doctors d
                {$specialty_join}
                {$hospital_join}
                WHERE " . implode(' OR ', $where) . "
                ORDER BY d.id DESC
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

                return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
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

            $sql = "
                SELECT *, 'hospital' AS profile_type
                FROM hospitals
                WHERE " . implode(' OR ', $where) . "
                ORDER BY id DESC
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

                return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            } catch (Throwable $e) {
                return [];
            }
        }

        return [];
    }
}

if (!function_exists('uc_claim_get_profile_by_id')) {
    function uc_claim_get_profile_by_id(string $profile_type, int $profile_id): ?array
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
                $specialty_join = table_exists('specialties') ? "LEFT JOIN specialties s ON s.id = d.specialty_id" : "";
                $hospital_join = table_exists('hospitals') ? "LEFT JOIN hospitals h ON h.id = d.hospital_id" : "";

                $specialty_select = table_exists('specialties')
                    ? "s.name AS specialty_name, s.slug AS specialty_slug,"
                    : "NULL AS specialty_name, NULL AS specialty_slug,";

                $hospital_select = table_exists('hospitals')
                    ? "h.name AS hospital_name,"
                    : "NULL AS hospital_name,";

                $stmt = $pdo->prepare("
                    SELECT
                        d.*,
                        {$specialty_select}
                        {$hospital_select}
                        'doctor' AS profile_type
                    FROM doctors d
                    {$specialty_join}
                    {$hospital_join}
                    WHERE d.id = :id
                    LIMIT 1
                ");

                $stmt->execute([':id' => $profile_id]);
                $profile = $stmt->fetch(PDO::FETCH_ASSOC);

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
                    SELECT *, 'hospital' AS profile_type
                    FROM hospitals
                    WHERE id = :id
                    LIMIT 1
                ");

                $stmt->execute([':id' => $profile_id]);
                $profile = $stmt->fetch(PDO::FETCH_ASSOC);

                return $profile ?: null;
            } catch (Throwable $e) {
                return null;
            }
        }

        return null;
    }
}

if (!function_exists('uc_claim_get_connected_user')) {
    function uc_claim_get_connected_user(string $profile_type, int $profile_id): ?array
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
            $connected_user = $stmt->fetch(PDO::FETCH_ASSOC);

            return $connected_user ?: null;
        } catch (Throwable $e) {
            return null;
        }
    }
}

if (!function_exists('uc_claim_has_pending_claim')) {
    function uc_claim_has_pending_claim(int $user_id, string $profile_type, int $profile_id): bool
    {
        global $pdo;

        if ($user_id <= 0 || $profile_id <= 0 || !table_exists('profile_claims')) {
            return false;
        }

        try {
            if ($profile_type === 'doctor') {
                $stmt = $pdo->prepare("
                    SELECT id
                    FROM profile_claims
                    WHERE user_id = :user_id
                    AND claim_type = 'doctor'
                    AND doctor_id = :profile_id
                    AND status = 'pending'
                    LIMIT 1
                ");
            } else {
                $stmt = $pdo->prepare("
                    SELECT id
                    FROM profile_claims
                    WHERE user_id = :user_id
                    AND claim_type = 'hospital'
                    AND hospital_id = :profile_id
                    AND status = 'pending'
                    LIMIT 1
                ");
            }

            $stmt->execute([
                ':user_id' => $user_id,
                ':profile_id' => $profile_id,
            ]);

            return (bool)$stmt->fetch();
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('uc_claim_get_latest_pending_claim')) {
    function uc_claim_get_latest_pending_claim(int $user_id): ?array
    {
        global $pdo;

        if ($user_id <= 0 || !table_exists('profile_claims')) {
            return null;
        }

        try {
            $stmt = $pdo->prepare("
                SELECT *
                FROM profile_claims
                WHERE user_id = :user_id
                AND status = 'pending'
                ORDER BY id DESC
                LIMIT 1
            ");

            $stmt->execute([':user_id' => $user_id]);
            $claim = $stmt->fetch(PDO::FETCH_ASSOC);

            return $claim ?: null;
        } catch (Throwable $e) {
            return null;
        }
    }
}

if (!table_exists('users')) {
    require_once __DIR__ . '/../includes/header.php';
    echo '<div class="card card-padded">
            <h2>Users table not found</h2>
            <p>Please import the updated database first.</p>
          </div>';
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

if (!table_exists('profile_claims')) {
    require_once __DIR__ . '/../includes/header.php';
    echo '<div class="card card-padded">
            <h2>Profile claims table not found</h2>
            <p>Please import the updated database first.</p>
          </div>';
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

uc_claim_ensure_columns();

try {
    $stmt = $pdo->prepare("
        SELECT
            u.*,
            d.name AS claimed_doctor_name,
            d.slug AS claimed_doctor_slug,
            h.name AS claimed_hospital_name,
            h.slug AS claimed_hospital_slug
        FROM users u
        LEFT JOIN doctors d ON d.id = u.claimed_doctor_id
        LEFT JOIN hospitals h ON h.id = u.claimed_hospital_id
        WHERE u.id = :id
        LIMIT 1
    ");

    $stmt->execute([':id' => $current_user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $user = null;
}

if (!$user) {
    require_once __DIR__ . '/../includes/header.php';
    echo '<div class="card card-padded">
            <h2>User not found</h2>
            <p>Your account was not found.</p>
          </div>';
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

$user_type = trim((string)($user['user_type'] ?? $user['role'] ?? 'user'));
$user_status = trim((string)($user['status'] ?? 'active'));

if (function_exists('user_type_label')) {
    $user_type_label = user_type_label($user);
} elseif ($user_type === 'hospital_owner') {
    $user_type_label = 'Hospital Owner';
} elseif ($user_type === 'doctor') {
    $user_type_label = 'Doctor';
} else {
    $user_type_label = ucfirst(str_replace('_', ' ', $user_type));
}

$basic_name = trim((string)($user['name'] ?? ''));
$basic_email = trim((string)($user['email'] ?? ''));
$basic_phone = trim((string)($user['phone'] ?? ''));

$basic_bmdc = trim((string)(
    $user['bmdc_number']
    ?? $user['bmdc_no']
    ?? $user['bmdc']
    ?? $user['registration_number']
    ?? $user['registration_no']
    ?? $user['license_number']
    ?? ''
));

$basic_specialty = trim((string)(
    $user['doctor_specialty']
    ?? $user['specialty']
    ?? $user['department']
    ?? ''
));

$basic_authorized_person = trim((string)(
    $user['authorized_person_name']
    ?? $user['contact_person']
    ?? $user['name']
    ?? ''
));

$basic_hospital_type = trim((string)(
    $user['hospital_type']
    ?? $user['type']
    ?? ''
));

$claimed_doctor = null;
$claimed_hospital = null;
$connected_profile = null;
$connected_profile_type = '';
$connection_status = '';

if (!empty($user['claimed_doctor_id'])) {
    $claimed_doctor = uc_claim_get_profile_by_id('doctor', (int)$user['claimed_doctor_id']);

    if ($claimed_doctor) {
        $connected_profile = $claimed_doctor;
        $connected_profile_type = 'doctor';
        $connection_status = 'approved';
    }
}

if (!$connected_profile && !empty($user['claimed_hospital_id'])) {
    $claimed_hospital = uc_claim_get_profile_by_id('hospital', (int)$user['claimed_hospital_id']);

    if ($claimed_hospital) {
        $connected_profile = $claimed_hospital;
        $connected_profile_type = 'hospital';
        $connection_status = 'approved';
    }
}

$pending_claim = null;
$pending_profile = null;
$pending_profile_type = '';

if (!$connected_profile) {
    $pending_claim = uc_claim_get_latest_pending_claim($current_user_id);

    if ($pending_claim) {
        $pending_profile_type = (string)($pending_claim['claim_type'] ?? '');

        if ($pending_profile_type === 'doctor') {
            $pending_doctor_id = (int)($pending_claim['doctor_id'] ?? $pending_claim['profile_id'] ?? 0);
            $pending_profile = uc_claim_get_profile_by_id('doctor', $pending_doctor_id);
        }

        if ($pending_profile_type === 'hospital') {
            $pending_hospital_id = (int)($pending_claim['hospital_id'] ?? $pending_claim['profile_id'] ?? 0);
            $pending_profile = uc_claim_get_profile_by_id('hospital', $pending_hospital_id);
        }

        if ($pending_profile) {
            $connected_profile = $pending_profile;
            $connected_profile_type = $pending_profile_type;
            $connection_status = 'pending';
        }
    }
}

$allowed_claim_type = '';

if ($user_type === 'doctor') {
    $allowed_claim_type = 'doctor';
} elseif (in_array($user_type, ['hospital', 'hospital_owner'], true)) {
    $allowed_claim_type = 'hospital';
}

$show_search = !$connected_profile && $allowed_claim_type !== '';

$default_search_input = '';

if ($allowed_claim_type === 'doctor') {
    $default_search_input = $basic_name;
} elseif ($allowed_claim_type === 'hospital') {
    $default_search_input = trim((string)(
        $user['hospital_name']
        ?? $user['clinic_name']
        ?? $user['organization_name']
        ?? $user['company_name']
        ?? $user['business_name']
        ?? $basic_name
    ));
}

$searched_profile_input = isset($_GET['profile_input'])
    ? trim((string)($_GET['profile_input'] ?? ''))
    : $default_search_input;

$searched_profile_type = $allowed_claim_type;
$has_manual_search = isset($_GET['profile_input']);
$should_run_profile_search = $show_search && $searched_profile_input !== '';

if ($show_search && $has_manual_search && $searched_profile_input === '') {
    $errors[] = 'Please enter name, BMDC / registration number, email, phone, WhatsApp, full profile link, slug, or ID.';
}

if ($should_run_profile_search) {
    if (!in_array($searched_profile_type, ['doctor', 'hospital'], true)) {
        $errors[] = 'Your account type is not allowed to claim a doctor or hospital profile.';
    }

    if (!$errors) {
        $profile_ref = uc_claim_extract_profile_slug($searched_profile_input, $searched_profile_type);

        $searched_profiles = uc_claim_search_profiles(
            $searched_profile_type,
            $profile_ref,
            $search_result_limit
        );

        if (!empty($searched_profiles)) {
            $success = count($searched_profiles) . ' profile result loaded automatically from your search name.';
        } elseif ($has_manual_search) {
            $errors[] = 'No matching profile found. Please check name, BMDC / registration number, email, phone, WhatsApp, link, slug, or ID.';
        }
    }
}

$claims = [];

try {
    if (
        column_exists('profile_claims', 'doctor_id') &&
        column_exists('profile_claims', 'hospital_id')
    ) {
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

    $claim_stmt->execute([':user_id' => $current_user_id]);
    $claims = $claim_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $claims = [];
}

require_once __DIR__ . '/../includes/header.php';
?>

<link rel="stylesheet" href="<?php echo BASE_URL; ?>/user/assets/css/user-claim.css">

<div class="user-view-header">
    <div>
        <h2>Claim Your Profile</h2>
        <p>Search your doctor or hospital profile and submit a request for admin approval.</p>
    </div>

    <div class="user-actions">
        <a href="dashboard.php" class="btn">Back</a>
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
                <span>Name</span>
                <strong><?= e($basic_name !== '' ? $basic_name : 'Not specified') ?></strong>
            </div>

            <div class="user-info-row">
                <span>Email</span>
                <strong><?= e($basic_email !== '' ? $basic_email : 'Not specified') ?></strong>
            </div>

            <div class="user-info-row">
                <span>Phone</span>
                <strong><?= e($basic_phone !== '' ? $basic_phone : 'Not specified') ?></strong>
            </div>

            <div class="user-info-row">
                <span>User Type</span>
                <strong><?= e($user_type_label) ?></strong>
            </div>

            <?php if ($user_type === 'doctor'): ?>
                <div class="user-info-row">
                    <span>Specialty</span>
                    <strong><?= e($basic_specialty !== '' ? $basic_specialty : 'Not specified') ?></strong>
                </div>

                <div class="user-info-row">
                    <span>BMDC Number</span>
                    <strong><?= e($basic_bmdc !== '' ? $basic_bmdc : 'Not specified') ?></strong>
                </div>
            <?php endif; ?>

            <?php if ($user_type === 'hospital_owner'): ?>
                <div class="user-info-row">
                    <span>Authorized Person</span>
                    <strong><?= e($basic_authorized_person !== '' ? $basic_authorized_person : 'Not specified') ?></strong>
                </div>

                <div class="user-info-row">
                    <span>Hospital Type</span>
                    <strong><?= e($basic_hospital_type !== '' ? $basic_hospital_type : 'Not specified') ?></strong>
                </div>

                <div class="user-info-row">
                    <span>Registration Number</span>
                    <strong><?= e($basic_bmdc !== '' ? $basic_bmdc : 'Not specified') ?></strong>
                </div>
            <?php endif; ?>

            <div class="user-info-row">
                <span>Status</span>
                <strong>
                    <span class="user-badge <?= e($user_status) ?>">
                        <?= e(ucfirst(str_replace('_', ' ', $user_status))) ?>
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

    <div class="user-card" id="connected-profile">
        <div class="connected-profile-head">
            <h3>Connected Profile</h3>

            <?php if ($connection_status === 'approved'): ?>
                <span class="user-badge approved">Approved</span>
            <?php elseif ($connection_status === 'pending'): ?>
                <span class="user-badge pending">Pending</span>
            <?php endif; ?>
        </div>

        <div class="user-card-body">
            <?php if ($connected_profile && $connected_profile_type): ?>
                <div class="medic-list-card">
                    <?php uc_claim_render_profile_card($connected_profile_type, $connected_profile); ?>
                </div>

                <?php if ($connection_status === 'pending'): ?>
                    <div class="connected-note connected-note-spaced">
                        Your claim request has been submitted and is waiting for admin approval.
                    </div>
                <?php endif; ?>

            <?php else: ?>
                <div class="profile-empty-box">
                    No doctor or hospital profile is connected with your account yet.
                </div>
            <?php endif; ?>

            <?php if ($show_search): ?>
                <div class="claim-search-box" id="claim-search">
                    <h4>
                        Search <?= $allowed_claim_type === 'hospital' ? 'Hospital' : 'Doctor' ?> Profile Before Claim
                    </h4>

                    <form method="get" action="claim.php#claim-search">
                        <div class="claim-search-grid">
                            <input type="hidden" name="profile_type" value="<?= e($allowed_claim_type) ?>">

                            <div class="claim-type-fixed">
                                <?= $allowed_claim_type === 'hospital' ? 'Hospital' : 'Doctor' ?>
                            </div>

                            <input
                                type="text"
                                name="profile_input"
                                id="profile_input"
                                aria-label="Search profile by name, profile link, or slug"
                                placeholder="Name, Profile link, slug"
                                value="<?= e($searched_profile_input) ?>"
                                required
                            >

                            <button type="submit">Search</button>
                        </div>
                    </form>

                    <p class="profile-action-note">
                        Search by <strong>Name, full profile link, slug</strong>
                    </p>

                    <?php if (!empty($searched_profiles)): ?>


                        <div class="search-result-preview">
                            <h4>Search Result Preview</h4>

                            <?php foreach ($searched_profiles as $index => $searched_profile): ?>
                                <?php
                                $profile_id = (int)($searched_profile['id'] ?? 0);
                                $connected_user = uc_claim_get_connected_user($searched_profile_type, $profile_id);
                                $is_connected_to_other_user = $connected_user && (int)$connected_user['id'] !== $current_user_id;
                                $is_connected_to_current_user = $connected_user && (int)$connected_user['id'] === $current_user_id;
                                $has_pending_claim = uc_claim_has_pending_claim($current_user_id, $searched_profile_type, $profile_id);
                                $is_hidden_result = $index >= $initial_visible_results;
                                ?>

                                <div class="claim-result-item <?= $is_hidden_result ? 'is-hidden-result' : '' ?>">
                                    <div class="medic-list-card">
                                        <?php uc_claim_render_profile_card($searched_profile_type, $searched_profile); ?>
                                    </div>

                                    <div class="claim-preview-actions">
                                        <?php if ($is_connected_to_other_user): ?>
                                            <button type="button" class="btn btn-warning">Already Connected</button>
                                            <div class="connected-note">This profile is already connected with another user.</div>

                                        <?php elseif ($is_connected_to_current_user): ?>
                                            <button type="button" class="btn btn-warning">Already Connected With Your Account</button>

                                        <?php elseif ($has_pending_claim): ?>
                                            <button type="button" class="btn btn-warning">Pending Claim Already Submitted</button>
                                            <div class="connected-note">Your request is waiting for admin approval.</div>

                                        <?php else: ?>
                                            <a
                                                class="btn submit-claim-link"
                                                href="claim-submit.php?type=<?= e($searched_profile_type) ?>&id=<?= e((string)$profile_id) ?>"
                                            >
                                                Submit Claim
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>

                            <?php if (count($searched_profiles) > $initial_visible_results): ?>
                                <div class="show-more-wrap">
                                    <button type="button" id="claimShowMoreBtn" data-step="10">Show More</button>

                                    <p id="claimResultCounter" class="profile-action-note">
                                        Showing <?= e((string)$initial_visible_results) ?> of <?= e((string)count($searched_profiles)) ?> results.
                                    </p>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="user-card" id="profile-claims">
        <h3>Recent Profile Claims</h3>

        <div class="user-card-body user-card-body-flush">
            <div class="mini-table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Claim Type</th>
                            <th>Profile</th>
                            <th>Status</th>
                            <th>Admin Note</th>
                            <th>Created</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php if (!$claims): ?>
                            <tr>
                                <td colspan="6" class="mini-table-empty-cell">No claims found.</td>
                            </tr>
                        <?php endif; ?>

                        <?php foreach ($claims as $claim): ?>
                            <?php
                            $claim_profile = '—';

                            if (!empty($claim['doctor_name'])) {
                                $claim_profile = 'Doctor: ' . $claim['doctor_name'];
                            } elseif (!empty($claim['hospital_name'])) {
                                $claim_profile = 'Hospital: ' . $claim['hospital_name'];
                            } elseif (!empty($claim['profile_name'])) {
                                $claim_profile = $claim['profile_name'];
                            } elseif (!empty($claim['name'])) {
                                $claim_profile = $claim['name'];
                            }
                            ?>

                            <tr>
                                <td>#<?= e((string)$claim['id']) ?></td>
                                <td><?= e(ucfirst((string)($claim['claim_type'] ?? '—'))) ?></td>
                                <td><?= e($claim_profile) ?></td>
                                <td>
                                    <span class="user-badge <?= e($claim['status'] ?? '') ?>">
                                        <?= e(ucfirst((string)($claim['status'] ?? '—'))) ?>
                                    </span>
                                </td>
                                <td><?= e($claim['admin_note'] ?? '—') ?></td>
                                <td><?= !empty($claim['created_at']) ? e(date('d M Y', strtotime((string)$claim['created_at']))) : '—' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<script src="<?php echo BASE_URL; ?>/user/assets/js/claim.js" defer></script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>