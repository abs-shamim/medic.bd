<?php
require_once __DIR__ . '/../includes/auth.php';

$user = require_user_login();

$page_title = 'User Dashboard';

$user_id = (int)($user['id'] ?? 0);

if ($user_id <= 0) {
    redirect('logout.php');
}

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

if (!function_exists('dashboard_table_exists')) {
    function dashboard_table_exists(string $table): bool
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
}

if (!function_exists('dashboard_column_exists')) {
    function dashboard_column_exists(string $table, string $column): bool
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
}

if (!function_exists('dashboard_select_column')) {
    function dashboard_select_column(string $table, string $alias, string $column, string $as): string
    {
        return dashboard_column_exists($table, $column)
            ? "{$alias}.{$column} AS {$as}"
            : "NULL AS {$as}";
    }
}

if (!function_exists('dashboard_file_url')) {
    function dashboard_file_url(string $path): string
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

        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? '';

        return $host !== '' ? $scheme . '://' . $host . '/' . $path : '/' . $path;
    }
}

if (!function_exists('dashboard_initial')) {
    function dashboard_initial(string $text): string
    {
        $text = trim($text);

        if ($text === '') {
            return 'U';
        }

        return strtoupper(function_exists('mb_substr') ? mb_substr($text, 0, 1, 'UTF-8') : substr($text, 0, 1));
    }
}

if (!function_exists('dashboard_value')) {
    function dashboard_value(array $row, array $keys, string $default = ''): string
    {
        foreach ($keys as $key) {
            if (isset($row[$key]) && trim((string)$row[$key]) !== '') {
                return trim((string)$row[$key]);
            }
        }

        return $default;
    }
}

/*
|--------------------------------------------------------------------------
| Fresh User Data
|--------------------------------------------------------------------------
| Important:
| Auth session user may contain old claimed profile data.
| So we reload user from database first.
|--------------------------------------------------------------------------
*/

try {
    if (dashboard_table_exists('users')) {
        $stmt = $pdo->prepare("
            SELECT *
            FROM users
            WHERE id = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $user_id]);
        $fresh_user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($fresh_user) {
            $user = $fresh_user;
        }
    }
} catch (Throwable $e) {
    // Keep auth user fallback
}

/*
|--------------------------------------------------------------------------
| Existing Project Functions Fallback
|--------------------------------------------------------------------------
*/

$claim = function_exists('get_user_claim_status') ? get_user_claim_status($user_id) : null;
$pending = function_exists('get_pending_count') ? get_pending_count($user_id) : 0;
$approved_profile = function_exists('get_user_profile') ? get_user_profile($user) : null;
$has_claim = function_exists('user_has_claim') ? user_has_claim($user) : false;

/*
|--------------------------------------------------------------------------
| User Info
|--------------------------------------------------------------------------
*/

$user_name = trim((string)($user['name'] ?? 'User'));
$user_email = trim((string)($user['email'] ?? ''));
$user_phone = trim((string)($user['phone'] ?? ''));
$user_type = trim((string)($user['user_type'] ?? $user['role'] ?? 'user'));
$user_status = trim((string)($user['status'] ?? 'active'));
$user_image = trim((string)($user['image'] ?? ''));

$bmdc_number = trim((string)($user['bmdc_number'] ?? ''));
$doctor_specialty = trim((string)($user['doctor_specialty'] ?? ''));

$license_number = trim((string)($user['license_number'] ?? ''));
$authorized_person_name = trim((string)($user['authorized_person_name'] ?? ''));
$authorized_person_position = trim((string)($user['authorized_person_position'] ?? ''));
$authorized_person_phone = trim((string)($user['authorized_person_phone'] ?? ''));
$authorized_person_email = trim((string)($user['authorized_person_email'] ?? ''));
$hospital_type = trim((string)($user['hospital_type'] ?? ''));
$hospital_address = trim((string)($user['hospital_address'] ?? ''));

$division_name = trim((string)($user['division_name'] ?? ''));
$district_name = trim((string)($user['district_name'] ?? ''));
$thana_name = trim((string)($user['thana_name'] ?? ''));
$address = trim((string)($user['address'] ?? ''));

if ($user_type === 'hospital_owner') {
    $user_type_label = 'Hospital Owner';
} elseif ($user_type === 'doctor') {
    $user_type_label = 'Doctor';
} else {
    $user_type_label = ucfirst(str_replace('_', ' ', $user_type));
}

$user_avatar_text = dashboard_initial($user_name);
$user_image_url = $user_image !== '' ? dashboard_file_url($user_image) : '';

/*
|--------------------------------------------------------------------------
| Latest Claim Request
|--------------------------------------------------------------------------
*/

$latest_claim = [];

try {
    if (dashboard_table_exists('profile_claims')) {
        $stmt = $pdo->prepare("
            SELECT *
            FROM profile_claims
            WHERE user_id = :user_id
            ORDER BY id DESC
            LIMIT 1
        ");
        $stmt->execute([':user_id' => $user_id]);
        $latest_claim = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }
} catch (Throwable $e) {
    $latest_claim = is_array($claim) ? $claim : [];
}

if (!$latest_claim && is_array($claim)) {
    $latest_claim = $claim;
}

$claim_status = strtolower(trim((string)($latest_claim['status'] ?? ($claim['status'] ?? ''))));

$user_claimed_doctor_id = (int)($user['claimed_doctor_id'] ?? 0);
$user_claimed_hospital_id = (int)($user['claimed_hospital_id'] ?? 0);

$user_has_connected_profile = $user_claimed_doctor_id > 0 || $user_claimed_hospital_id > 0;

$claim_is_disconnected = in_array($claim_status, ['disconnected', 'disconnect', 'declined'], true);
$claim_is_rejected = in_array($claim_status, ['rejected'], true);
$claim_is_pending = $claim_status === 'pending';

$claim_is_approved = false;

if ($claim_status === 'approved' && $user_has_connected_profile && !$claim_is_disconnected && !$claim_is_rejected) {
    $claim_is_approved = true;
}

/*
|--------------------------------------------------------------------------
| Connected / Pending Profile Fetch
|--------------------------------------------------------------------------
*/

$display_profile = [];
$display_profile_type = '';
$display_profile_status = '';

/*
| Approved connected profile
| Only show approved profile if user table still has claimed_doctor_id / claimed_hospital_id.
*/

if ($claim_is_approved && $user_has_connected_profile) {
    if ($user_claimed_doctor_id > 0 && dashboard_table_exists('doctors')) {
        try {
            $specialty_join = '';

            if (dashboard_table_exists('specialties') && dashboard_column_exists('doctors', 'specialty_id')) {
                $specialty_join = "LEFT JOIN specialties s ON s.id = d.specialty_id";
            }

            $doctor_select = implode(",\n                    ", [
                "d.*",
                dashboard_select_column('doctors', 'd', 'image', 'profile_image_main'),
                dashboard_select_column('doctors', 'd', 'photo', 'profile_photo'),
                dashboard_select_column('doctors', 'd', 'avatar', 'profile_avatar'),
                dashboard_select_column('doctors', 'd', 'profile_image', 'profile_image_alt'),
                dashboard_select_column('doctors', 'd', 'specialty', 'profile_specialty'),
                dashboard_select_column('doctors', 'd', 'department', 'profile_department'),
                dashboard_select_column('doctors', 'd', 'designation', 'profile_designation'),
                dashboard_select_column('doctors', 'd', 'degree', 'profile_degree'),
                dashboard_select_column('doctors', 'd', 'qualification', 'profile_qualification'),
                $specialty_join !== '' ? "s.name AS profile_specialty_name" : "NULL AS profile_specialty_name",
            ]);

            $stmt = $pdo->prepare("
                SELECT {$doctor_select}
                FROM doctors d
                {$specialty_join}
                WHERE d.id = :id
                LIMIT 1
            ");
            $stmt->execute([':id' => $user_claimed_doctor_id]);

            $display_profile = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
            $display_profile_type = 'doctor';
            $display_profile_status = 'approved';
        } catch (Throwable $e) {
            $display_profile = [];
        }
    } elseif ($user_claimed_hospital_id > 0 && dashboard_table_exists('hospitals')) {
        try {
            $hospital_select = implode(",\n                    ", [
                "h.*",
                dashboard_select_column('hospitals', 'h', 'image', 'profile_image_main'),
                dashboard_select_column('hospitals', 'h', 'logo', 'profile_logo'),
                dashboard_select_column('hospitals', 'h', 'photo', 'profile_photo'),
                dashboard_select_column('hospitals', 'h', 'profile_image', 'profile_image_alt'),
                dashboard_select_column('hospitals', 'h', 'type', 'profile_type_name'),
                dashboard_select_column('hospitals', 'h', 'hospital_type', 'profile_hospital_type'),
                dashboard_select_column('hospitals', 'h', 'license_number', 'profile_license_number'),
                dashboard_select_column('hospitals', 'h', 'registration_number', 'profile_registration_number'),
                dashboard_select_column('hospitals', 'h', 'address', 'profile_address'),
                dashboard_select_column('hospitals', 'h', 'district', 'profile_district'),
                dashboard_select_column('hospitals', 'h', 'district_name', 'profile_district_name'),
            ]);

            $stmt = $pdo->prepare("
                SELECT {$hospital_select}
                FROM hospitals h
                WHERE h.id = :id
                LIMIT 1
            ");
            $stmt->execute([':id' => $user_claimed_hospital_id]);

            $display_profile = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
            $display_profile_type = 'hospital';
            $display_profile_status = 'approved';
        } catch (Throwable $e) {
            $display_profile = [];
        }
    }
}

/*
| Pending claim profile
| Only show pending profile while claim status is pending.
*/

if (!$display_profile && $claim_is_pending && $latest_claim) {
    $claim_type = trim((string)($latest_claim['claim_type'] ?? ''));

    if ($claim_type === 'doctor') {
        $doctor_id = (int)($latest_claim['doctor_id'] ?? 0);

        if ($doctor_id > 0 && dashboard_table_exists('doctors')) {
            try {
                $specialty_join = '';

                if (dashboard_table_exists('specialties') && dashboard_column_exists('doctors', 'specialty_id')) {
                    $specialty_join = "LEFT JOIN specialties s ON s.id = d.specialty_id";
                }

                $doctor_select = implode(",\n                    ", [
                    "d.*",
                    dashboard_select_column('doctors', 'd', 'image', 'profile_image_main'),
                    dashboard_select_column('doctors', 'd', 'photo', 'profile_photo'),
                    dashboard_select_column('doctors', 'd', 'avatar', 'profile_avatar'),
                    dashboard_select_column('doctors', 'd', 'profile_image', 'profile_image_alt'),
                    dashboard_select_column('doctors', 'd', 'specialty', 'profile_specialty'),
                    dashboard_select_column('doctors', 'd', 'department', 'profile_department'),
                    dashboard_select_column('doctors', 'd', 'designation', 'profile_designation'),
                    dashboard_select_column('doctors', 'd', 'degree', 'profile_degree'),
                    dashboard_select_column('doctors', 'd', 'qualification', 'profile_qualification'),
                    $specialty_join !== '' ? "s.name AS profile_specialty_name" : "NULL AS profile_specialty_name",
                ]);

                $stmt = $pdo->prepare("
                    SELECT {$doctor_select}
                    FROM doctors d
                    {$specialty_join}
                    WHERE d.id = :id
                    LIMIT 1
                ");
                $stmt->execute([':id' => $doctor_id]);

                $display_profile = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
                $display_profile_type = 'doctor';
                $display_profile_status = 'pending';
            } catch (Throwable $e) {
                $display_profile = [];
            }
        }
    }

    if ($claim_type === 'hospital') {
        $hospital_id = (int)($latest_claim['hospital_id'] ?? 0);

        if ($hospital_id > 0 && dashboard_table_exists('hospitals')) {
            try {
                $hospital_select = implode(",\n                    ", [
                    "h.*",
                    dashboard_select_column('hospitals', 'h', 'image', 'profile_image_main'),
                    dashboard_select_column('hospitals', 'h', 'logo', 'profile_logo'),
                    dashboard_select_column('hospitals', 'h', 'photo', 'profile_photo'),
                    dashboard_select_column('hospitals', 'h', 'profile_image', 'profile_image_alt'),
                    dashboard_select_column('hospitals', 'h', 'type', 'profile_type_name'),
                    dashboard_select_column('hospitals', 'h', 'hospital_type', 'profile_hospital_type'),
                    dashboard_select_column('hospitals', 'h', 'license_number', 'profile_license_number'),
                    dashboard_select_column('hospitals', 'h', 'registration_number', 'profile_registration_number'),
                    dashboard_select_column('hospitals', 'h', 'address', 'profile_address'),
                    dashboard_select_column('hospitals', 'h', 'district', 'profile_district'),
                    dashboard_select_column('hospitals', 'h', 'district_name', 'profile_district_name'),
                ]);

                $stmt = $pdo->prepare("
                    SELECT {$hospital_select}
                    FROM hospitals h
                    WHERE h.id = :id
                    LIMIT 1
                ");
                $stmt->execute([':id' => $hospital_id]);

                $display_profile = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
                $display_profile_type = 'hospital';
                $display_profile_status = 'pending';
            } catch (Throwable $e) {
                $display_profile = [];
            }
        }
    }
}

/*
|--------------------------------------------------------------------------
| Profile Card Data
|--------------------------------------------------------------------------
*/

$profile_name = '';

if ($display_profile) {
    $profile_name = dashboard_value($display_profile, ['name', 'title'], 'Connected Profile');
}

$profile_type_label = $display_profile_type === 'hospital' ? 'Hospital Profile' : 'Doctor Profile';

$profile_image_raw = '';

if ($display_profile) {
    $profile_image_raw = dashboard_value($display_profile, [
        'profile_image_main',
        'profile_photo',
        'profile_avatar',
        'profile_image_alt',
        'profile_logo',
        'image',
        'photo',
        'profile_image',
        'avatar',
        'logo',
    ]);
}

$profile_image_url = $profile_image_raw !== '' ? dashboard_file_url($profile_image_raw) : '';

$profile_first_line = '';
$profile_second_line = '';

if ($display_profile) {
    if ($display_profile_type === 'hospital') {
        $profile_first_line = dashboard_value($display_profile, [
            'profile_hospital_type',
            'profile_type_name',
            'hospital_type',
            'type',
            'category',
        ]);

        $profile_license = dashboard_value($display_profile, [
            'profile_license_number',
            'profile_registration_number',
            'license_number',
            'registration_number',
        ]);

        $profile_location = dashboard_value($display_profile, [
            'profile_address',
            'address',
            'location',
            'profile_district_name',
            'district_name',
            'profile_district',
            'district',
        ]);

        $profile_second_line = trim(
            ($profile_license !== '' ? 'License: ' . $profile_license : '') .
            ($profile_license !== '' && $profile_location !== '' ? ' | ' : '') .
            $profile_location
        );
    } else {
        $profile_first_line = dashboard_value($display_profile, [
            'profile_specialty_name',
            'profile_specialty',
            'specialty_name',
            'specialty',
            'profile_department',
            'department',
            'profile_designation',
            'designation',
        ]);

        $profile_second_line = dashboard_value($display_profile, [
            'profile_degree',
            'degree',
            'profile_qualification',
            'qualification',
            'education',
        ]);
    }
}

$profile_meta = trim($profile_first_line . ($profile_second_line !== '' ? ' | ' . $profile_second_line : ''));

if ($profile_meta === '') {
    $profile_meta = $display_profile ? $profile_type_label : 'No connected profile yet';
}

if ($claim_is_pending) {
    $connected_title = 'Pending Profile';
} elseif ($claim_is_approved) {
    $connected_title = 'Connected Profile';
} elseif ($claim_is_disconnected) {
    $connected_title = 'Disconnected Profile';
} else {
    $connected_title = 'Connected Profile';
}

require_once __DIR__ . '/../includes/header.php';
?>

<style>
    :root {
        --dash-bg: #ffffff;
        --dash-soft: #f7f6f3;
        --dash-border: #d6d1c8;
        --dash-text: #191919;
        --dash-muted: #565656;
        --dash-blue: #3157d6;
        --dash-green: #00b67a;
        --dash-green-dark: #009e6a;
        --dash-red: #cf222e;
        --dash-red-soft: #ffebe9;
        --dash-yellow: #9a6700;
        --dash-yellow-soft: #fff8c5;
    }

    body {
        background: var(--dash-bg) !important;
        color: var(--dash-text);
    }

    .dashboard-page,
    .dashboard-page * {
        box-sizing: border-box;
    }

    .dashboard-page {
        max-width: 560px;
        margin: 0 auto;
        padding: 26px 18px 60px;
        color: var(--dash-text);
    }

    .dashboard-user-card,
    .dashboard-profile-card {
        display: grid;
        gap: 18px;
        margin: 10px auto 28px;
        padding: 16px 18px;
        border-radius: 16px;
        background: var(--dash-soft);
        max-width: 520px;
    }

    .dashboard-user-main,
    .dashboard-profile-mini {
        display: grid;
        grid-template-columns: 56px minmax(0, 1fr);
        gap: 16px;
        align-items: center;
    }

    .dashboard-avatar,
    .dashboard-profile-photo {
        width: 56px;
        height: 56px;
        border-radius: 7px;
        overflow: hidden;
        border: 1px solid #e4e1da;
        background: #ffffff;
        color: var(--dash-text);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 22px;
        font-weight: 800;
    }

    .dashboard-avatar img,
    .dashboard-profile-photo img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
    }

    .dashboard-user-main h1,
    .dashboard-profile-mini h3 {
        margin: 0;
        color: var(--dash-text);
        font-size: 18px;
        line-height: 1.25;
        font-weight: 700;
    }

    .dashboard-profile-mini p {
        margin: 5px 0 0;
        color: var(--dash-text);
        font-size: 14px;
        line-height: 1.45;
    }

    .dashboard-user-tags {
        display: flex;
        flex-wrap: wrap;
        gap: 7px;
        margin-top: 5px;
    }

    .dashboard-user-tags span {
        display: inline-flex;
        align-items: center;
        min-height: 24px;
        padding: 3px 9px;
        border-radius: 999px;
        background: #ffffff;
        border: 1px solid #e4e1da;
        color: var(--dash-muted);
        font-size: 12px;
        font-weight: 600;
    }

    .dashboard-user-tags span.status {
        background: rgba(0,182,122,.10);
        border-color: rgba(0,182,122,.22);
        color: var(--dash-green-dark);
    }

    .dashboard-info-list {
        display: grid;
        grid-template-columns: 1fr;
        gap: 0;
        margin-top: 4px;
        border-top: 1px solid #e4e1da;
    }

    .dashboard-info-item {
        display: grid;
        grid-template-columns: 155px minmax(0, 1fr);
        gap: 10px;
        padding: 9px 0;
        border-bottom: 1px solid #e4e1da;
        background: transparent;
    }

    .dashboard-info-item:last-child {
        border-bottom: 0;
    }

    .dashboard-info-item span {
        color: var(--dash-muted);
        font-size: 13px;
        font-weight: 500;
    }

    .dashboard-info-item strong {
        color: var(--dash-text);
        font-size: 13px;
        line-height: 1.55;
        font-weight: 600;
        word-break: break-word;
    }

    .dashboard-profile-card-title {
        margin: 0 0 -4px;
        color: var(--dash-muted);
        font-size: 13px;
        font-weight: 600;
    }

    .dashboard-profile-empty {
        color: var(--dash-muted);
        font-size: 14px;
        line-height: 1.6;
    }

    .dashboard-status-card {
        margin-bottom: 28px;
        display: grid;
        gap: 12px;
    }

    .dashboard-mini-card {
        padding: 14px 16px;
        border-radius: 14px;
        background: var(--dash-soft);
        border: 1px solid #e4e1da;
    }

    .dashboard-mini-card span {
        display: block;
        margin-bottom: 5px;
        color: var(--dash-muted);
        font-size: 12px;
        font-weight: 600;
    }

    .dashboard-mini-card strong {
        display: block;
        color: var(--dash-text);
        font-size: 15px;
        line-height: 1.5;
        font-weight: 700;
    }

    .dash-status {
        display: inline-flex;
        align-items: center;
        width: fit-content;
        min-height: 25px;
        padding: 4px 10px;
        border-radius: 999px;
        font-size: 12px;
        line-height: 1;
        font-weight: 700;
        border: 1px solid transparent;
    }

    .dash-status.approved,
    .dash-status.active,
    .dash-status.completed {
        background: rgba(0,182,122,.10);
        color: var(--dash-green-dark);
        border-color: rgba(0,182,122,.22);
    }

    .dash-status.pending,
    .dash-status.pending_verification {
        background: var(--dash-yellow-soft);
        color: var(--dash-yellow);
        border-color: rgba(154,103,0,.22);
    }

    .dash-status.rejected,
    .dash-status.declined,
    .dash-status.disconnected,
    .dash-status.blocked {
        background: var(--dash-red-soft);
        color: var(--dash-red);
        border-color: rgba(207,34,46,.22);
    }

    .dash-status.not-approved {
        background: #ffffff;
        color: var(--dash-muted);
        border-color: #e4e1da;
    }

    .dashboard-section {
        margin-bottom: 38px;
    }

    .dashboard-section-head {
        margin-bottom: 20px;
    }

    .dashboard-section-head h2 {
        margin: 0 0 10px;
        color: #1f2328;
        font-size: 32px;
        line-height: 1.2;
        font-weight: 700;
        letter-spacing: -0.03em;
    }

    .dashboard-section-head p {
        margin: 0;
        color: var(--dash-muted);
        font-size: 15px;
        line-height: 1.65;
    }

    .dashboard-actions {
        display: grid;
        gap: 12px;
    }

    .dashboard-action {
        display: grid;
        grid-template-columns: 38px minmax(0, 1fr);
        gap: 13px;
        align-items: center;
        min-height: 58px;
        padding: 11px 13px;
        border-radius: 12px;
        border: 1px solid #e4e1da;
        background: #ffffff;
        color: var(--dash-text);
        text-decoration: none;
        transition: .16s ease;
    }

    .dashboard-action:hover {
        background: var(--dash-soft);
        color: var(--dash-text);
        text-decoration: none;
    }

    .dashboard-action.primary {
        background: var(--dash-green);
        border-color: var(--dash-green);
        color: #ffffff;
    }

    .dashboard-action.primary:hover {
        background: var(--dash-green-dark);
        border-color: var(--dash-green-dark);
        color: #ffffff;
    }

    .dashboard-action-icon {
        width: 38px;
        height: 38px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 10px;
        background: var(--dash-soft);
        border: 1px solid #e4e1da;
        color: var(--dash-blue);
        font-size: 15px;
        font-weight: 800;
    }

    .dashboard-action.primary .dashboard-action-icon {
        background: rgba(255,255,255,.16);
        border-color: rgba(255,255,255,.28);
        color: #ffffff;
    }

    .dashboard-action strong {
        display: block;
        margin-bottom: 3px;
        font-size: 14px;
        line-height: 1.35;
        font-weight: 700;
    }

    .dashboard-action small {
        display: block;
        color: var(--dash-muted);
        font-size: 12px;
        line-height: 1.45;
    }

    .dashboard-action.primary small {
        color: rgba(255,255,255,.86);
    }

    .dashboard-note {
        margin-top: 18px;
        padding: 14px 16px;
        border: 1px solid #e4e1da;
        border-radius: 12px;
        background: var(--dash-soft);
        color: var(--dash-muted);
        font-size: 14px;
        line-height: 1.6;
    }

    .dashboard-note strong {
        color: var(--dash-text);
    }

    @media (max-width: 640px) {
        .dashboard-page {
            padding-top: 18px;
        }

        .dashboard-info-item {
            grid-template-columns: 1fr;
            gap: 4px;
        }

        .dashboard-section-head h2 {
            font-size: 28px;
        }

        .dashboard-action {
            grid-template-columns: 34px minmax(0, 1fr);
        }

        .dashboard-action-icon {
            width: 34px;
            height: 34px;
        }
    }
</style>

<div class="dashboard-page">

    <section class="dashboard-user-card">
        <div class="dashboard-user-main">
            <div class="dashboard-avatar">
                <?php if ($user_image_url !== ''): ?>
                    <img src="<?= e($user_image_url) ?>" alt="<?= e($user_name) ?>">
                <?php else: ?>
                    <?= e($user_avatar_text ?: 'U') ?>
                <?php endif; ?>
            </div>

            <div>
                <h1><?= e($user_name !== '' ? $user_name : 'User') ?></h1>

                <div class="dashboard-user-tags">
                    <?php if ($user_type === 'doctor'): ?>
                        <span><?= e($doctor_specialty !== '' ? $doctor_specialty : 'Specialty not set') ?></span>
                        <span>BMDC: <?= e($bmdc_number !== '' ? $bmdc_number : 'Not set') ?></span>
                    <?php elseif ($user_type === 'hospital_owner'): ?>
                        <span><?= e($hospital_type !== '' ? $hospital_type : 'Hospital type not set') ?></span>
                        <span>License: <?= e($license_number !== '' ? $license_number : 'Not set') ?></span>
                    <?php else: ?>
                        <span><?= e($user_type_label) ?></span>
                    <?php endif; ?>

                    <span class="status"><?= e(ucfirst(str_replace('_', ' ', $user_status))) ?></span>
                </div>
            </div>
        </div>

        <div class="dashboard-info-list">
            <?php if ($user_type === 'doctor'): ?>
                <div class="dashboard-info-item">
                    <span>Specialty</span>
                    <strong><?= e($doctor_specialty !== '' ? $doctor_specialty : 'Not set') ?></strong>
                </div>

                <div class="dashboard-info-item">
                    <span>BMDC Number</span>
                    <strong><?= e($bmdc_number !== '' ? $bmdc_number : 'Not set') ?></strong>
                </div>
            <?php endif; ?>

            <?php if ($user_type === 'hospital_owner'): ?>
                <div class="dashboard-info-item">
                    <span>Hospital Type</span>
                    <strong><?= e($hospital_type !== '' ? $hospital_type : 'Not set') ?></strong>
                </div>

                <div class="dashboard-info-item">
                    <span>License Number</span>
                    <strong><?= e($license_number !== '' ? $license_number : 'Not set') ?></strong>
                </div>

                <div class="dashboard-info-item">
                    <span>Authorized Person</span>
                    <strong><?= e($authorized_person_name !== '' ? $authorized_person_name : 'Not set') ?></strong>
                </div>

                <div class="dashboard-info-item">
                    <span>Position</span>
                    <strong><?= e($authorized_person_position !== '' ? $authorized_person_position : 'Not set') ?></strong>
                </div>

                <div class="dashboard-info-item">
                    <span>Authorized Phone</span>
                    <strong><?= e($authorized_person_phone !== '' ? $authorized_person_phone : 'Not set') ?></strong>
                </div>

                <div class="dashboard-info-item">
                    <span>Authorized Email</span>
                    <strong><?= e($authorized_person_email !== '' ? $authorized_person_email : 'Not set') ?></strong>
                </div>
            <?php endif; ?>

            <div class="dashboard-info-item">
                <span>Division</span>
                <strong><?= e($division_name !== '' ? $division_name : 'Not set') ?></strong>
            </div>

            <div class="dashboard-info-item">
                <span>District</span>
                <strong><?= e($district_name !== '' ? $district_name : 'Not set') ?></strong>
            </div>

            <div class="dashboard-info-item">
                <span>Thana / Upazila</span>
                <strong><?= e($thana_name !== '' ? $thana_name : 'Not set') ?></strong>
            </div>

            <div class="dashboard-info-item">
                <span>Full Address</span>
                <strong><?= e($address !== '' ? $address : 'Not set') ?></strong>
            </div>
        </div>
    </section>

    <section class="dashboard-status-card">
        <div class="dashboard-mini-card">
            <span>Claim Status</span>

            <?php if ($claim_is_approved): ?>
                <strong><span class="dash-status approved">Approved</span></strong>
            <?php elseif ($claim_is_pending): ?>
                <strong><span class="dash-status pending">Pending</span></strong>
            <?php elseif ($claim_is_disconnected): ?>
                <strong><span class="dash-status disconnected">Disconnected</span></strong>
            <?php elseif ($claim_is_rejected): ?>
                <strong><span class="dash-status rejected">Rejected</span></strong>
            <?php else: ?>
                <strong><span class="dash-status not-approved">Not Approved Yet</span></strong>
            <?php endif; ?>
        </div>

        <div class="dashboard-mini-card">
            <span>Pending Requests</span>
            <strong><?= e(number_format((int)$pending)) ?></strong>
        </div>
    </section>

    <section class="dashboard-profile-card">
        <p class="dashboard-profile-card-title"><?= e($connected_title) ?></p>

        <?php if ($display_profile && ($claim_is_approved || $claim_is_pending)): ?>
            <div class="dashboard-profile-mini">
                <div class="dashboard-profile-photo">
                    <?php if ($profile_image_url !== ''): ?>
                        <img src="<?= e($profile_image_url) ?>" alt="<?= e($profile_name) ?>">
                    <?php else: ?>
                        <?= e(dashboard_initial($profile_name)) ?>
                    <?php endif; ?>
                </div>

                <div>
                    <h3><?= e($profile_name) ?></h3>
                    <p><?= e($profile_meta) ?></p>
                </div>
            </div>
        <?php else: ?>
            <div class="dashboard-profile-empty">
                No doctor or hospital profile is connected with your account yet.
            </div>
        <?php endif; ?>
    </section>

    <section class="dashboard-section">
        <div class="dashboard-section-head">
            <h2>Quick Actions</h2>
            <p>Manage your profile, account settings, chambers, and update requests from here.</p>
        </div>

        <div class="dashboard-actions">

            <?php if ($claim_is_approved): ?>

                <a class="dashboard-action primary" href="profile-update.php">
                    <span class="dashboard-action-icon">↗</span>
                    <span>
                        <strong>Profile Update</strong>
                        <small>Submit profile change requests for admin review.</small>
                    </span>
                </a>

                <a class="dashboard-action" href="chambers.php">
                    <span class="dashboard-action-icon">⌂</span>
                    <span>
                        <strong>Chamber Update</strong>
                        <small>Manage chamber and visiting information.</small>
                    </span>
                </a>

                <a class="dashboard-action" href="settings.php">
                    <span class="dashboard-action-icon">⚙</span>
                    <span>
                        <strong>Settings</strong>
                        <small>Update profile, password, email, and account details.</small>
                    </span>
                </a>

                <?php if ($user_type === 'hospital_owner'): ?>
                    <a class="dashboard-action" href="hospital-doctors.php">
                        <span class="dashboard-action-icon">D</span>
                        <span>
                            <strong>Hospital Doctors</strong>
                            <small>Add and manage doctors under your hospital.</small>
                        </span>
                    </a>
                <?php endif; ?>

            <?php elseif ($claim_is_pending): ?>

                <a class="dashboard-action primary" href="claim.php">
                    <span class="dashboard-action-icon">…</span>
                    <span>
                        <strong>Claim Pending</strong>
                        <small>Your profile claim request is waiting for admin approval.</small>
                    </span>
                </a>

                <a class="dashboard-action" href="settings.php">
                    <span class="dashboard-action-icon">⚙</span>
                    <span>
                        <strong>Settings</strong>
                        <small>Update your account information before admin review.</small>
                    </span>
                </a>

            <?php else: ?>

                <a class="dashboard-action primary" href="claim.php">
                    <span class="dashboard-action-icon">✓</span>
                    <span>
                        <strong>Claim Profile</strong>
                        <small>Request ownership of your public profile.</small>
                    </span>
                </a>

                <a class="dashboard-action" href="new-profile.php">
                    <span class="dashboard-action-icon">＋</span>
                    <span>
                        <strong>Create New Profile</strong>
                        <small>Add a new doctor or hospital profile.</small>
                    </span>
                </a>

                <a class="dashboard-action" href="settings.php">
                    <span class="dashboard-action-icon">⚙</span>
                    <span>
                        <strong>Settings</strong>
                        <small>Update profile, password, email, and account details.</small>
                    </span>
                </a>

                <?php if ($claim_is_rejected || $claim_is_disconnected): ?>
                    <a class="dashboard-action" href="claim.php">
                        <span class="dashboard-action-icon">!</span>
                        <span>
                            <strong>Claim Again</strong>
                            <small>Your previous profile connection is not active. Submit a new request.</small>
                        </span>
                    </a>
                <?php endif; ?>

            <?php endif; ?>

        </div>

        <div class="dashboard-note">
            <strong>Note:</strong>
            Keep your verification details, address, and contact information updated before submitting a profile claim.
        </div>
    </section>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>