<?php
require_once __DIR__ . '/../../includes/html-normalizer.php';

if (function_exists('front_normalize_html_output')) {
    ob_start('front_normalize_html_output');
}

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

// BASE_URL includes the site's subfolder (e.g. /medicbd), so "Home" is only
// active when the request path matches that base path exactly -- a plain
// strpos($current_path, '') check (as user_nav_active() does) would always
// match and mark Home active on every page.
$current_request_path = rtrim((string)parse_url($current_path, PHP_URL_PATH), '/');
$site_base_path = rtrim((string)parse_url(BASE_URL, PHP_URL_PATH), '/');
$is_home_active = $current_request_path === $site_base_path;

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

    <script src="<?php echo BASE_URL; ?>/user/assets/js/theme-init.js"></script>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- User Panel CSS -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/user/assets/css/user-panel.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/theme-vars.php">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/user/assets/css/user-header.css">
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
                    <a href="<?php echo BASE_URL; ?>/" class="<?php echo $is_home_active ? 'active' : ''; ?>">
                        Home
                    </a>

                    <a href="<?php echo BASE_URL; ?>/doctors" class="<?php echo user_nav_active('/doctors', $current_path); ?>">
                        Doctors
                    </a>

                    <a href="<?php echo BASE_URL; ?>/hospitals" class="<?php echo user_nav_active('/hospitals', $current_path); ?>">
                        Hospitals
                    </a>

                    <a href="<?php echo BASE_URL; ?>/specialties" class="<?php echo user_nav_active('/specialties', $current_path); ?>">
                        Specialties
                    </a>

                    <a href="<?php echo BASE_URL; ?>/blog" class="<?php echo user_nav_active('/blog', $current_path); ?>">
                        Blog
                    </a>

                    <a href="<?php echo BASE_URL; ?>/contact" class="<?php echo user_nav_active('/contact', $current_path); ?>">
                        Contact
                    </a>

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
                <a href="<?php echo BASE_URL; ?>/" class="<?php echo $is_home_active ? 'active' : ''; ?>">
                    Home
                </a>

                <a href="<?php echo BASE_URL; ?>/doctors" class="<?php echo user_nav_active('/doctors', $current_path); ?>">
                    Doctors
                </a>

                <a href="<?php echo BASE_URL; ?>/hospitals" class="<?php echo user_nav_active('/hospitals', $current_path); ?>">
                    Hospitals
                </a>

                <a href="<?php echo BASE_URL; ?>/specialties" class="<?php echo user_nav_active('/specialties', $current_path); ?>">
                    Specialties
                </a>

                <a href="<?php echo BASE_URL; ?>/blog" class="<?php echo user_nav_active('/blog', $current_path); ?>">
                    Blog
                </a>

                <a href="<?php echo BASE_URL; ?>/contact" class="<?php echo user_nav_active('/contact', $current_path); ?>">
                    Contact
                </a>

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

<script src="<?php echo BASE_URL; ?>/user/assets/js/user-header-theme.js" defer></script>