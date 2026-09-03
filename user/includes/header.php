<?php 
require_once __DIR__ . '/auth.php';

$current_user = function_exists('current_user_data') ? current_user_data() : null;

if (!$current_user && !empty($_SESSION['user']) && is_array($_SESSION['user'])) {
    $current_user = $_SESSION['user'];
}

if (!is_array($current_user)) {
    $current_user = [];
}

$page_title = $page_title ?? 'User Panel';

$current_path = $_SERVER['REQUEST_URI'] ?? '';

/*
|--------------------------------------------------------------------------
| User Header Site Settings Helpers
|--------------------------------------------------------------------------
*/

if (!function_exists('user_header_table_exists')) {
    function user_header_table_exists(string $table): bool
    {
        global $pdo;

        try {
            if (!isset($pdo)) {
                return false;
            }

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

if (!function_exists('user_header_setting')) {
    function user_header_setting(string $key, string $default = ''): string
    {
        global $pdo;

        static $settings_cache = null;

        if ($settings_cache === null) {
            $settings_cache = [];

            try {
                if (!isset($pdo) || !user_header_table_exists('site_settings')) {
                    return $default;
                }

                $stmt = $pdo->query("SELECT setting_key, setting_value FROM site_settings");
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

                foreach ($rows as $row) {
                    $settings_cache[(string)$row['setting_key']] = (string)$row['setting_value'];
                }
            } catch (Throwable $e) {
                $settings_cache = [];
            }
        }

        $value = trim((string)($settings_cache[$key] ?? ''));

        return $value !== '' ? $value : $default;
    }
}

if (!function_exists('user_header_color')) {
    function user_header_color(string $key, string $default): string
    {
        $value = user_header_setting($key, $default);

        if (preg_match('/^#[0-9a-fA-F]{6}$/', $value)) {
            return $value;
        }

        return $default;
    }
}

if (!function_exists('user_header_asset_url')) {
    function user_header_asset_url(string $path, string $fallback = ''): string
    {
        $path = trim($path);

        if ($path === '') {
            $path = trim($fallback);
        }

        if ($path === '') {
            return '';
        }

        if (preg_match('/^https?:\/\//i', $path)) {
            return $path;
        }

        $path = ltrim($path, './');

        if (defined('BASE_URL')) {
            return rtrim(BASE_URL, '/') . '/' . $path;
        }

        return '/' . $path;
    }
}

if (!function_exists('user_header_phone_href')) {
    function user_header_phone_href(string $phone): string
    {
        $phone = trim($phone);

        if ($phone === '') {
            return '';
        }

        return preg_replace('/[^\d+]/', '', $phone);
    }
}

if (!function_exists('user_nav_active')) {
    function user_nav_active(string $path, string $current_path): string
    {
        return strpos($current_path, $path) !== false ? 'active' : '';
    }
}

/*
|--------------------------------------------------------------------------
| Dynamic Values
|--------------------------------------------------------------------------
*/

$site_name = user_header_setting('site_name', defined('APP_NAME') ? APP_NAME : 'User Panel');
$site_short_name = user_header_setting('site_short_name', $site_name);
$site_tagline = user_header_setting('site_tagline', 'Profile management portal');

$site_email = user_header_setting(
    'contact_email',
    user_header_setting('support_email', '')
);

$site_emergency_phone = user_header_setting(
    'emergency_number',
    user_header_setting('contact_phone', '')
);

$site_logo_raw = user_header_setting('site_logo', '');
$site_logo = $site_logo_raw !== '' ? user_header_asset_url($site_logo_raw) : '';

$site_dark_logo_raw = user_header_setting('site_dark_logo', '');
$site_dark_logo = $site_dark_logo_raw !== '' ? user_header_asset_url($site_dark_logo_raw) : $site_logo;

$site_favicon = user_header_asset_url(
    user_header_setting('site_favicon', ''),
    'assets/images/favicon.ico'
);

$primary_color = user_header_color('primary_color', '#0969da');
$accent_color = user_header_color('accent_color', '#2da44e');
$body_background_color = user_header_color('body_background_color', '#f6f8fa');

$current_user_type_raw = '';

if (function_exists('current_user_type')) {
    $current_user_type_raw = current_user_type();
}

if ($current_user_type_raw === '') {
    $current_user_type_raw = trim((string)(
        $current_user['user_type']
        ?? $current_user['role']
        ?? $_SESSION['user_type']
        ?? 'user'
    ));
}

if (function_exists('normalize_user_type')) {
    $current_user_type = normalize_user_type($current_user_type_raw);
} else {
    $current_user_type = strtolower(trim((string)$current_user_type_raw));
    $current_user_type = str_replace(['-', ' '], '_', $current_user_type);

    if (in_array($current_user_type, ['hospital', 'hospital_owner', 'clinic', 'clinic_owner'], true)) {
        $current_user_type = 'hospital_owner';
    } elseif ($current_user_type === '') {
        $current_user_type = 'user';
    }
}

$is_doctor_user = $current_user_type === 'doctor';

$is_hospital_user = function_exists('current_user_is_hospital')
    ? current_user_is_hospital()
    : $current_user_type === 'hospital_owner';

/*
|--------------------------------------------------------------------------
| Prescription Access
|--------------------------------------------------------------------------
*/

$claimed_doctor_id = (int)($current_user['claimed_doctor_id'] ?? 0);
$has_approved_doctor_claim = false;

if ($is_doctor_user && $claimed_doctor_id > 0) {
    if (function_exists('user_has_claim')) {
        $has_approved_doctor_claim = user_has_claim($current_user);
    } else {
        $claim_status = strtolower(trim((string)(
            $current_user['claim_status']
            ?? $current_user['profile_claim_status']
            ?? ''
        )));

        $has_approved_doctor_claim = in_array(
            $claim_status,
            ['approved', 'active', 'verified'],
            true
        );
    }
}

$can_use_prescription = function_exists('user_is_logged_in')
    && user_is_logged_in()
    && $is_doctor_user
    && $has_approved_doctor_claim
    && $claimed_doctor_id > 0;

if ($is_hospital_user) {
    $user_type_label = 'Hospital Owner';
} elseif ($is_doctor_user) {
    $user_type_label = 'Doctor';
} else {
    $user_type_label = ucfirst(str_replace('_', ' ', $current_user_type));
}

$claim_profile_label = 'Claim Profile';
$profile_update_label = 'Profile Update';
$manage_profile_label = 'Manage Profile';

$profile_update_path = '/user/profile-update.php';
$profile_update_href = BASE_URL . $profile_update_path;
$manage_profile_href = $profile_update_href;

if ($is_doctor_user) {
    $claim_profile_label = 'Claim Doctor Profile';
    $profile_update_label = 'Doctor Profile Update';
    $manage_profile_label = 'Manage Doctor Profile';
} elseif ($is_hospital_user) {
    $claim_profile_label = 'Claim Hospital Profile';
    $profile_update_label = 'Hospital Profile Update';
    $manage_profile_label = 'Manage Hospital Profile';
    $profile_update_path = '/user/hospital-profile-update.php';
    $profile_update_href = BASE_URL . $profile_update_path;
    $manage_profile_href = $profile_update_href;
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <title><?php echo e($page_title); ?> - <?php echo e($site_short_name); ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <?php if ($site_favicon !== ''): ?>
        <link rel="icon" href="<?php echo e($site_favicon); ?>">
    <?php endif; ?>

    <script>
        (function () {
            const savedTheme = localStorage.getItem('user_panel_theme') || 'light';
            document.documentElement.setAttribute('data-theme', savedTheme);
        })();
    </script>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- User Panel CSS -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/user/assets/css/user-panel.css">

    <style>
        :root {
            --user-header-dark: #24292f;
            --user-header-muted: #57606a;
            --user-header-border: #d0d7de;
            --user-header-soft: <?php echo e($body_background_color); ?>;
            --user-header-card: #ffffff;
            --user-header-blue: <?php echo e($primary_color); ?>;
            --user-header-green: <?php echo e($accent_color); ?>;
            --user-header-green-dark: <?php echo e($accent_color); ?>;
            --user-header-shadow: 0 1px 0 rgba(27, 31, 36, 0.04);
            --user-header-bg: rgba(255, 255, 255, 0.96);
            --user-header-dropdown: #ffffff;
            --user-header-input: #ffffff;
        }

        html[data-theme="dark"] {
            --user-header-dark: #f0f6fc;
            --user-header-muted: #8b949e;
            --user-header-border: #30363d;
            --user-header-soft: #0d1117;
            --user-header-card: #161b22;
            --user-header-blue: #58a6ff;
            --user-header-green: #3fb950;
            --user-header-green-dark: #2ea043;
            --user-header-shadow: 0 1px 0 rgba(240, 246, 252, 0.04);
            --user-header-bg: rgba(13, 17, 23, 0.96);
            --user-header-dropdown: #161b22;
            --user-header-input: #0d1117;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            color: var(--user-header-dark);
            background:
                radial-gradient(circle at top left, rgba(88, 166, 255, 0.10), transparent 30%),
                radial-gradient(circle at bottom right, rgba(63, 185, 80, 0.08), transparent 28%),
                var(--user-header-soft);
            transition: background 0.2s ease, color 0.2s ease;
        }

        .container {
            max-width: 1180px;
            margin: 0 auto;
            padding-left: 16px;
            padding-right: 16px;
        }

        .user-topbar {
            background: var(--user-header-soft);
            border-bottom: 1px solid var(--user-header-border);
            font-size: 13px;
            color: var(--user-header-muted);
        }

        .user-topbar-inner {
            min-height: 36px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
        }

        .user-topbar-left,
        .user-topbar-right {
            display: flex;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
        }

        .user-topbar a {
            color: var(--user-header-blue);
            text-decoration: none;
            font-weight: 600;
        }

        .user-topbar a:hover {
            text-decoration: underline;
        }

        .user-header {
            position: sticky;
            top: 0;
            z-index: 9999;
            background: var(--user-header-bg);
            border-bottom: 1px solid var(--user-header-border);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            box-shadow: var(--user-header-shadow);
        }

        .user-header input[type="checkbox"] {
            display: none;
        }

        .user-header-inner {
            min-height: 64px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
        }

        .user-logo {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            color: var(--user-header-dark);
            text-decoration: none;
            font-size: 20px;
            font-weight: 800;
            letter-spacing: -0.02em;
            white-space: nowrap;
            min-width: 0;
        }

        .user-logo:hover {
            color: var(--user-header-dark);
            text-decoration: none;
        }

        .user-logo-icon {
            width: 34px;
            height: 34px;
            border-radius: 8px;
            background: var(--user-header-green);
            color: #ffffff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            font-weight: 800;
            line-height: 1;
            box-shadow: inset 0 -1px 0 rgba(27, 31, 36, 0.15);
            overflow: hidden;
            flex-shrink: 0;
        }

        .user-logo-icon img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            display: block;
            background: #ffffff;
        }

        .logo-light {
            display: block;
        }

        .logo-dark {
            display: none;
        }

        html[data-theme="dark"] .logo-light {
            display: none;
        }

        html[data-theme="dark"] .logo-dark {
            display: block;
        }

        .user-logo-text {
            display: inline-flex;
            flex-direction: column;
            min-width: 0;
            line-height: 1.08;
        }

        .user-logo-title {
            color: var(--user-header-dark);
            font-size: 20px;
            font-weight: 800;
            letter-spacing: -0.02em;
            max-width: 210px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .user-logo-subtitle {
            display: none;
            margin-top: 3px;
            color: var(--user-header-muted);
            font-size: 11px;
            font-weight: 600;
            max-width: 230px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .user-menu {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 4px;
            flex: 1;
        }

        .user-menu a {
            display: inline-flex;
            align-items: center;
            min-height: 36px;
            padding: 0 10px;
            border-radius: 6px;
            color: var(--user-header-dark);
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            transition: 0.18s ease;
            white-space: nowrap;
        }

        .user-menu a:hover {
            background: var(--user-header-soft);
            color: var(--user-header-blue);
        }

        .user-menu a.active,
        .user-menu .active {
            background: rgba(88, 166, 255, 0.16);
            color: var(--user-header-blue);
        }

        html[data-theme="light"] .user-menu a.active,
        html[data-theme="light"] .user-menu .active {
            background: #ddf4ff;
            color: var(--user-header-blue);
        }

        .user-menu .user-add-link {
            margin-left: 8px;
            background: var(--user-header-green);
            color: #ffffff;
            border: 1px solid rgba(27, 31, 36, 0.15);
            font-weight: 700;
        }

        .user-menu .user-add-link:hover {
            background: var(--user-header-green-dark);
            color: #ffffff;
        }

        .theme-toggle-btn {
            min-height: 36px;
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 0 10px;
            border-radius: 999px;
            border: 1px solid var(--user-header-border);
            background: var(--user-header-card);
            color: var(--user-header-dark);
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            transition: 0.18s ease;
            margin-left: 8px;
        }

        .theme-toggle-btn:hover {
            background: var(--user-header-soft);
            color: var(--user-header-blue);
        }

        .user-account-menu {
            position: relative;
            margin-left: 8px;
        }

        .user-account-btn {
            display: inline-flex;
            align-items: center;
            min-height: 36px;
            gap: 8px;
            padding: 0 10px 0 6px;
            border-radius: 999px;
            border: 1px solid var(--user-header-border);
            color: var(--user-header-dark);
            text-decoration: none;
            font-size: 14px;
            font-weight: 700;
            background: var(--user-header-card);
            cursor: pointer;
        }

        .user-account-avatar {
            width: 26px;
            height: 26px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: var(--user-header-green);
            color: #ffffff;
            font-size: 12px;
            font-weight: 800;
            text-transform: uppercase;
        }

        .user-account-name {
            max-width: 120px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .user-account-dropdown {
            display: none;
            position: absolute;
            right: 0;
            top: calc(100% + 10px);
            width: 240px;
            padding: 8px;
            border: 1px solid var(--user-header-border);
            border-radius: 10px;
            background: var(--user-header-dropdown);
            box-shadow: 0 12px 30px rgba(27, 31, 36, 0.14);
        }

        .user-account-menu:hover .user-account-dropdown {
            display: block;
        }

        .user-account-card {
            padding: 10px;
            border-radius: 8px;
            background: var(--user-header-soft);
            margin-bottom: 6px;
        }

        .user-account-card strong {
            display: block;
            color: var(--user-header-dark);
            font-size: 14px;
            margin-bottom: 3px;
        }

        .user-account-card small {
            color: var(--user-header-muted);
            font-size: 12px;
            font-weight: 600;
        }

        .user-account-dropdown a {
            display: flex;
            min-height: 38px;
            align-items: center;
            padding: 0 10px;
            border-radius: 8px;
            color: var(--user-header-dark);
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
        }

        .user-account-dropdown a:hover {
            background: var(--user-header-soft);
            color: var(--user-header-blue);
        }

        .user-account-dropdown a.logout-link {
            color: #cf222e;
        }

        html[data-theme="dark"] .user-account-dropdown a.logout-link {
            color: #ff7b72;
        }

        .user-mobile-btn {
            display: none;
            width: 40px;
            height: 40px;
            border: 1px solid var(--user-header-border);
            border-radius: 8px;
            background: var(--user-header-card);
            color: var(--user-header-dark);
            align-items: center;
            justify-content: center;
            font-size: 22px;
            cursor: pointer;
            user-select: none;
        }

        .user-mobile-menu {
            display: none;
            padding: 10px 0 14px;
            border-top: 1px solid var(--user-header-border);
        }

        .user-mobile-menu a,
        .user-mobile-menu span,
        .user-mobile-theme-btn {
            display: flex;
            align-items: center;
            min-height: 42px;
            padding: 0 12px;
            border-radius: 8px;
            color: var(--user-header-dark);
            text-decoration: none;
            font-size: 15px;
            font-weight: 600;
        }

        .user-mobile-menu a:hover,
        .user-mobile-theme-btn:hover {
            background: var(--user-header-soft);
            color: var(--user-header-blue);
        }

        .user-mobile-menu a.active,
        .user-mobile-menu .active {
            background: rgba(88, 166, 255, 0.16);
            color: var(--user-header-blue);
        }

        html[data-theme="light"] .user-mobile-menu a.active,
        html[data-theme="light"] .user-mobile-menu .active {
            background: #ddf4ff;
            color: var(--user-header-blue);
        }

        .user-mobile-menu .mobile-account-info {
            background: var(--user-header-soft);
            color: var(--user-header-muted);
            margin-bottom: 4px;
            font-size: 13px;
        }

        .user-mobile-theme-btn {
            width: 100%;
            border: 0;
            background: transparent;
            text-align: left;
            cursor: pointer;
        }

        .user-panel-wrapper {
            background: transparent;
            min-height: calc(100vh - 74px);
        }

        .user-card,
        .card,
        .dashboard-card,
        .panel-card {
            background: var(--user-header-card) !important;
            color: var(--user-header-dark) !important;
            border: 1px solid var(--user-header-border) !important;
            box-shadow: 0 1px 0 rgba(27, 31, 36, 0.04);
            border-radius: 10px;
        }

        .text-muted {
            color: var(--user-header-muted) !important;
        }

        .form-control,
        .form-select,
        input,
        select,
        textarea {
            background-color: var(--user-header-input) !important;
            color: var(--user-header-dark) !important;
            border-color: var(--user-header-border) !important;
        }

        .form-control:focus,
        .form-select:focus,
        input:focus,
        select:focus,
        textarea:focus {
            border-color: var(--user-header-blue) !important;
            box-shadow: 0 0 0 3px rgba(9, 105, 218, 0.15) !important;
        }

        @media (max-width: 980px) {
            .user-topbar {
                display: none;
            }

            .user-header-inner {
                min-height: 60px;
            }

            .user-menu {
                display: none;
            }

            .user-mobile-btn {
                display: inline-flex;
            }

            #user-menu-toggle:checked ~ .user-header-inner .user-mobile-btn {
                background: var(--user-header-soft);
            }

            #user-menu-toggle:checked ~ .user-mobile-menu {
                display: grid;
                gap: 4px;
            }
        }

        @media (max-width: 480px) {
            .container {
                padding-left: 12px;
                padding-right: 12px;
            }

            .user-logo-title {
                font-size: 18px;
                max-width: 170px;
            }

            .user-logo-icon {
                width: 32px;
                height: 32px;
            }
        }
    </style>
</head>
<body>

<?php if ($site_emergency_phone !== '' || $site_email !== ''): ?>
    <div class="user-topbar">
        <div class="container user-topbar-inner">
            <div class="user-topbar-left">
                <?php if ($site_emergency_phone !== ''): ?>
                    <?php $emergency_href = user_header_phone_href($site_emergency_phone); ?>
                    <span>
                        Emergency:
                        <?php if ($emergency_href !== ''): ?>
                            <a href="tel:<?php echo e($emergency_href); ?>">
                                <?php echo e($site_emergency_phone); ?>
                            </a>
                        <?php else: ?>
                            <?php echo e($site_emergency_phone); ?>
                        <?php endif; ?>
                    </span>
                <?php endif; ?>

                <?php if ($site_email !== ''): ?>
                    <span>
                        Email:
                        <a href="mailto:<?php echo e($site_email); ?>">
                            <?php echo e($site_email); ?>
                        </a>
                    </span>
                <?php endif; ?>
            </div>

            <div class="user-topbar-right">
                <span><?php echo e($site_tagline); ?></span>
            </div>
        </div>
    </div>
<?php endif; ?>

<header class="user-header">
    <div class="container">
        <input type="checkbox" id="user-menu-toggle">

        <div class="user-header-inner">
            <a href="<?php echo BASE_URL; ?>/user/dashboard.php" class="user-logo">
                <span class="user-logo-icon">
                    <?php if ($site_logo !== ''): ?>
                        <img class="logo-light" src="<?php echo e($site_logo); ?>?v=<?php echo time(); ?>" alt="<?php echo e($site_name); ?>">
                        <img class="logo-dark" src="<?php echo e($site_dark_logo); ?>?v=<?php echo time(); ?>" alt="<?php echo e($site_name); ?>">
                    <?php else: ?>
                        +
                    <?php endif; ?>
                </span>

                <span class="user-logo-text">
                    <span class="user-logo-title"><?php echo e($site_short_name); ?></span>
                    <span class="user-logo-subtitle"><?php echo e($site_tagline); ?></span>
                </span>
            </a>

            <nav class="user-menu">
                <?php if (user_is_logged_in()): ?>
                    <a href="<?php echo BASE_URL; ?>/user/dashboard.php" class="<?php echo user_nav_active('/user/dashboard.php', $current_path); ?>">
                        Dashboard
                    </a>

                    <a href="<?php echo BASE_URL; ?>/user/claim.php" class="<?php echo user_nav_active('/user/claim.php', $current_path); ?>">
                        <?php echo e($claim_profile_label); ?>
                    </a>

                    <a href="<?php echo e($profile_update_href); ?>" class="<?php echo user_nav_active($profile_update_path, $current_path); ?>">
                        <?php echo e($profile_update_label); ?>
                    </a>

                    <a href="<?php echo BASE_URL; ?>/user/chambers.php" class="<?php echo user_nav_active('/user/chambers.php', $current_path); ?>">
                        Chambers
                    </a>

                    <?php if ($can_use_prescription): ?>
                        <a href="<?php echo BASE_URL; ?>/prescription/" class="<?php echo user_nav_active('/prescription/', $current_path); ?>">
                            Prescription
                        </a>
                    <?php endif; ?>

                    <?php if ($is_hospital_user): ?>
                        <a href="<?php echo BASE_URL; ?>/user/hospital-doctors.php" class="user-add-link <?php echo user_nav_active('/user/hospital-doctors.php', $current_path); ?>">
                            Add Doctors
                        </a>
                    <?php endif; ?>

                    <?php
                        $display_name = $current_user['name'] ?? 'Account';
                        $avatar_text = strtoupper(substr(trim((string)$display_name), 0, 1));

                        if ($avatar_text === '') {
                            $avatar_text = 'U';
                        }
                    ?>

                    <button type="button" class="theme-toggle-btn" id="themeToggleBtn">
                        <span id="themeToggleIcon">🌙</span>
                        <span id="themeToggleText">Dark</span>
                    </button>

                    <div class="user-account-menu">
                        <button type="button" class="user-account-btn">
                            <span class="user-account-avatar"><?php echo e($avatar_text); ?></span>
                            <span class="user-account-name"><?php echo e($display_name); ?></span>
                            <span>▾</span>
                        </button>

                        <div class="user-account-dropdown">
                            <div class="user-account-card">
                                <strong><?php echo e($display_name); ?></strong>
                                <small><?php echo e($user_type_label); ?></small>
                            </div>

                            <a href="<?php echo BASE_URL; ?>/user/settings.php">Settings</a>
                            <a href="<?php echo e($manage_profile_href); ?>"><?php echo e($manage_profile_label); ?></a>
                            <a href="<?php echo BASE_URL; ?>/user/logout.php" class="logout-link">Logout</a>
                        </div>
                    </div>

                <?php else: ?>
                    <a href="<?php echo BASE_URL; ?>/user/login.php" class="<?php echo user_nav_active('/user/login.php', $current_path); ?>">
                        Login
                    </a>

                    <a href="<?php echo BASE_URL; ?>/user/register.php" class="user-add-link <?php echo user_nav_active('/user/register.php', $current_path); ?>">
                        Create Account
                    </a>

                    <button type="button" class="theme-toggle-btn" id="themeToggleBtn">
                        <span id="themeToggleIcon">🌙</span>
                        <span id="themeToggleText">Dark</span>
                    </button>
                <?php endif; ?>
            </nav>

            <label for="user-menu-toggle" class="user-mobile-btn" aria-label="Toggle mobile menu">☰</label>
        </div>

        <nav class="user-mobile-menu">
            <?php if (user_is_logged_in()): ?>
                <span class="mobile-account-info">
                    <?php echo e($current_user['name'] ?? 'Account'); ?> · <?php echo e($user_type_label); ?>
                </span>

                <a href="<?php echo BASE_URL; ?>/user/dashboard.php" class="<?php echo user_nav_active('/user/dashboard.php', $current_path); ?>">
                    Dashboard
                </a>

                <a href="<?php echo BASE_URL; ?>/user/claim.php" class="<?php echo user_nav_active('/user/claim.php', $current_path); ?>">
                    <?php echo e($claim_profile_label); ?>
                </a>

                <a href="<?php echo e($profile_update_href); ?>" class="<?php echo user_nav_active($profile_update_path, $current_path); ?>">
                    <?php echo e($profile_update_label); ?>
                </a>

                <a href="<?php echo BASE_URL; ?>/user/chambers.php" class="<?php echo user_nav_active('/user/chambers.php', $current_path); ?>">
                    Chambers
                </a>

                <?php if ($can_use_prescription): ?>
                    <a href="<?php echo BASE_URL; ?>/prescription/" class="<?php echo user_nav_active('/prescription/', $current_path); ?>">
                        Prescription
                    </a>
                <?php endif; ?>

                <a href="<?php echo BASE_URL; ?>/user/settings.php" class="<?php echo user_nav_active('/user/settings.php', $current_path); ?>">
                    Settings
                </a>
          
                <?php if ($is_hospital_user): ?>
                    <a href="<?php echo BASE_URL; ?>/user/hospital-doctors.php" class="<?php echo user_nav_active('/user/hospital-doctors.php', $current_path); ?>">
                        Add Doctors
                    </a>
                <?php endif; ?>

                <button type="button" class="user-mobile-theme-btn" id="mobileThemeToggleBtn">
                    <span id="mobileThemeToggleIcon">🌙</span>
                    &nbsp;
                    <span id="mobileThemeToggleText">Dark Mode</span>
                </button>

                <a href="<?php echo BASE_URL; ?>/user/logout.php">
                    Logout
                </a>
            <?php else: ?>
                <a href="<?php echo BASE_URL; ?>/user/login.php" class="<?php echo user_nav_active('/user/login.php', $current_path); ?>">
                    Login
                </a>

                <a href="<?php echo BASE_URL; ?>/user/register.php" class="<?php echo user_nav_active('/user/register.php', $current_path); ?>">
                    Create Account
                </a>

                <button type="button" class="user-mobile-theme-btn" id="mobileThemeToggleBtn">
                    <span id="mobileThemeToggleIcon">🌙</span>
                    &nbsp;
                    <span id="mobileThemeToggleText">Dark Mode</span>
                </button>
            <?php endif; ?>
        </nav>
    </div>
</header>

<div class="user-panel-wrapper py-4">
    <div class="container">
        <?php show_flash(); ?>

<script>
    function applyUserTheme(theme) {
        document.documentElement.setAttribute('data-theme', theme);
        localStorage.setItem('user_panel_theme', theme);

        const desktopIcon = document.getElementById('themeToggleIcon');
        const desktopText = document.getElementById('themeToggleText');
        const mobileIcon = document.getElementById('mobileThemeToggleIcon');
        const mobileText = document.getElementById('mobileThemeToggleText');

        if (theme === 'dark') {
            if (desktopIcon) desktopIcon.textContent = '☀️';
            if (desktopText) desktopText.textContent = 'Light';
            if (mobileIcon) mobileIcon.textContent = '☀️';
            if (mobileText) mobileText.textContent = 'Light Mode';
        } else {
            if (desktopIcon) desktopIcon.textContent = '🌙';
            if (desktopText) desktopText.textContent = 'Dark';
            if (mobileIcon) mobileIcon.textContent = '🌙';
            if (mobileText) mobileText.textContent = 'Dark Mode';
        }
    }

    function toggleUserTheme() {
        const currentTheme = document.documentElement.getAttribute('data-theme') || 'light';
        const nextTheme = currentTheme === 'dark' ? 'light' : 'dark';
        applyUserTheme(nextTheme);
    }

    document.addEventListener('DOMContentLoaded', function () {
        const savedTheme = localStorage.getItem('user_panel_theme') || 'light';

        applyUserTheme(savedTheme);

        const desktopBtn = document.getElementById('themeToggleBtn');
        const mobileBtn = document.getElementById('mobileThemeToggleBtn');

        if (desktopBtn) {
            desktopBtn.addEventListener('click', toggleUserTheme);
        }

        if (mobileBtn) {
            mobileBtn.addEventListener('click', toggleUserTheme);
        }
    });
</script>