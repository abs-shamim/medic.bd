<?php 
require_once __DIR__ . '/../../includes/functions.php';
require_admin();

$current_admin_page = basename($_SERVER['PHP_SELF']);

$admin_page_titles = [
    'dashboard.php'             => 'Dashboard',
    'doctors.php'               => 'Doctors',
    'doctor-form.php'           => 'Doctor Form',
    'featured-doctors.php'      => 'Featured Doctors',
    'featured-doctors-form.php' => 'Featured Doctor Form',
    'hospitals.php'             => 'Hospitals',
    'hospital-form.php'         => 'Hospital Form',
	'directory-articles.php'    => 'Directory Articles',
 	'directory-article-form.php'=> 'Directory Article Form',
    'specialties.php'           => 'Specialties',
    'locations.php'             => 'Locations',
    'reviews.php'               => 'Reviews',
    'contact-messages.php'      => 'Contact Messages',
    'contact-message-view.php'  => 'Contact Message',
    'users.php'                 => 'Users',
    'user-view.php'             => 'User Details',
    'user-edit.php'             => 'Edit User',
    'user-requests.php'         => 'User Requests',
    'user-request-view.php'     => 'User Request Details',
    'profile-claims.php'        => 'Profile Claims',
    'my-panel.php'              => 'My Panel',
    'site-settings.php'         => 'Site Settings',
    'static-pages.php'          => 'Static Pages',
    'static-page-form.php'      => 'Static Page Form',
    'blog-posts.php'            => 'Blog Posts',
    'blog-post-form.php'        => 'Blog Post Form',
    'blog-categories.php'       => 'Blog Categories',
    'blog-category-form.php'    => 'Blog Category Form',
    'moderators.php'            => 'Admin Moderators',
];

$admin_title = $admin_page_titles[$current_admin_page] ?? 'Admin Panel';

$admin_app_name = defined('APP_NAME') ? (string)APP_NAME : 'Admin Panel';

if (function_exists('get_site_setting')) {
    $saved_site_name = trim((string)get_site_setting('site_name', $admin_app_name));

    if ($saved_site_name !== '') {
        $admin_app_name = $saved_site_name;
    }
}


/*
|--------------------------------------------------------------------------
| Admin Brand Assets
|--------------------------------------------------------------------------
| Uses values saved on the Site Settings page. Asset URLs are intentionally
| limited to public site paths or HTTP(S) URLs before output in the header.
*/
if (!function_exists('admin_header_setting_asset')) {
    function admin_header_setting_asset(string $setting_key, string $fallback = ''): string
    {
        $value = '';

        if (function_exists('get_site_setting')) {
            $value = trim((string)get_site_setting($setting_key, ''));
        }

        if ($value === '') {
            $value = trim($fallback);
        }

        if ($value === '') {
            return '';
        }

        if (preg_match('#^(?:https?://|/|\.\./assets/)#i', $value)) {
            return $value;
        }

        return trim($fallback);
    }
}

if (!function_exists('admin_header_asset_version')) {
    function admin_header_asset_version(string $url): string
    {
        $url = trim($url);

        if ($url === '') {
            return '';
        }

        $version = '';

        // Only map the known local assets directory to the file system.
        if (strpos($url, '../assets/images/') === 0) {
            $file_name = basename((string)parse_url($url, PHP_URL_PATH));
            $asset_path = dirname(__DIR__, 2) . '/assets/images/' . $file_name;

            if ($file_name !== '' && is_file($asset_path)) {
                $version = (string)filemtime($asset_path);
            }
        }

        if ($version === '') {
            return $url;
        }

        return $url . (strpos($url, '?') === false ? '?v=' : '&v=') . rawurlencode($version);
    }
}

if (!function_exists('admin_header_asset_mime')) {
    function admin_header_asset_mime(string $url): string
    {
        $extension = strtolower(pathinfo((string)parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));

        $mime_types = [
            'ico'  => 'image/x-icon',
            'png'  => 'image/png',
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'svg'  => 'image/svg+xml',
            'webp' => 'image/webp',
        ];

        return $mime_types[$extension] ?? 'image/x-icon';
    }
}

$admin_site_logo = admin_header_setting_asset('site_logo', '../assets/images/site-logo.webp');
$admin_site_dark_logo = admin_header_setting_asset('site_dark_logo');
$admin_site_favicon = admin_header_setting_asset('site_favicon', '../assets/images/favicon.ico');
$admin_apple_touch_icon = admin_header_setting_asset('site_apple_touch_icon', $admin_site_favicon);

$admin_site_logo_url = admin_header_asset_version($admin_site_logo);
$admin_site_dark_logo_url = admin_header_asset_version($admin_site_dark_logo);
$admin_site_favicon_url = admin_header_asset_version($admin_site_favicon);
$admin_apple_touch_icon_url = admin_header_asset_version($admin_apple_touch_icon);
$admin_site_favicon_mime = admin_header_asset_mime($admin_site_favicon);

$logged_admin_name = trim((string)($_SESSION['admin_name'] ?? 'Administrator'));
$logged_admin_role = trim((string)($_SESSION['admin_role'] ?? 'super_admin'));

$admin_role_labels = [
    'super_admin' => 'Super Admin',
    'admin'       => 'Admin',
    'moderator'   => 'Moderator',
    'editor'      => 'Editor',
    'support'     => 'Support',
];

$logged_admin_role_label = $admin_role_labels[$logged_admin_role] ?? ucwords(str_replace('_', ' ', $logged_admin_role));

if (!function_exists('admin_header_can')) {
    function admin_header_can(string $permission): bool
    {
        if (function_exists('admin_can')) {
            return admin_can($permission);
        }

        return true;
    }
}

if (!function_exists('admin_header_table_exists')) {
    function admin_header_table_exists(string $table): bool
    {
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

if (!function_exists('admin_header_column_exists')) {
    function admin_header_column_exists(string $table, string $column): bool
    {
        global $pdo;

        try {
            if (!admin_header_table_exists($table)) {
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

if (!function_exists('admin_header_count_rows')) {
    function admin_header_count_rows(string $table, string $where = '', array $params = []): int
    {
        global $pdo;

        try {
            if (!admin_header_table_exists($table)) {
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

$pending_user_requests = 0;
$pending_profile_claims = 0;
$total_users_count = 0;
$total_doctors_count = 0;
$total_featured_doctors_count = 0;
$total_directory_articles_count = 0;
$total_pending_directory_articles_count = 0;
$total_blog_posts_count = 0;
$total_draft_blog_posts_count = 0;
$total_hospitals_count = 0;
$total_appointments_count = 0;
$total_reviews_count = 0;
$total_moderators_count = 0;

if (admin_header_column_exists('profile_update_requests', 'status')) {
    $pending_user_requests = admin_header_count_rows('profile_update_requests', 'status = :status', [':status' => 'pending']);
}

if (admin_header_column_exists('profile_claims', 'status')) {
    $pending_profile_claims = admin_header_count_rows('profile_claims', 'status = :status', [':status' => 'pending']);
}

$total_users_count = admin_header_count_rows('users');
$total_doctors_count = admin_header_count_rows('doctors');
if (admin_header_table_exists('doctor_featured_contexts')) {
    $total_featured_doctors_count = admin_header_count_rows('doctor_featured_contexts', "status IN ('active', 'hold')");
} elseif (admin_header_column_exists('doctors', 'is_featured')) {
    $featured_where = 'is_featured = 1';

    if (admin_header_column_exists('doctors', 'featured_on_hold')) {
        $featured_where = '(is_featured = 1 OR featured_on_hold = 1)';
    }

    $total_featured_doctors_count = admin_header_count_rows('doctors', $featured_where);
}
if (admin_header_table_exists('doctor_directory_articles')) {
    $total_directory_articles_count = admin_header_count_rows('doctor_directory_articles');

    if (admin_header_column_exists('doctor_directory_articles', 'status')) {
        $total_pending_directory_articles_count = admin_header_count_rows(
            'doctor_directory_articles',
            'status = :status',
            [':status' => 'pending']
        );
    }
}
if (admin_header_table_exists('blog_posts')) {
    $total_blog_posts_count = admin_header_count_rows('blog_posts');

    if (admin_header_column_exists('blog_posts', 'status')) {
        $total_draft_blog_posts_count = admin_header_count_rows(
            'blog_posts',
            'status = :status',
            [':status' => 'draft']
        );
    }
}
$total_hospitals_count = admin_header_count_rows('hospitals');
$total_reviews_count = admin_header_count_rows('reviews');
$total_unread_messages_count = admin_header_count_rows('contacts', "status = 'unread'");
$total_moderators_count = admin_header_count_rows('admin_users');

$total_pending_badge = $pending_user_requests + $pending_profile_claims;

$admin_nav_sections = [
    'Overview' => [
        [
            'file' => 'dashboard.php',
            'label' => 'Dashboard',
            'icon' => 'fa fa-dashboard',
            'permission' => 'dashboard.view',
        ],
    ],

    'Directory' => [
        [
            'file' => 'doctors.php',
            'label' => 'Doctors',
            'icon' => 'fa fa-user-md',
            'count' => $total_doctors_count,
            'permission' => 'doctors.view',
            'active_pages' => ['doctors.php', 'doctor-form.php'],
        ],
        [
            'file' => 'featured-doctors.php',
            'label' => 'Featured Doctors',
            'icon' => 'fa fa-star',
            'count' => $total_featured_doctors_count,
            'permission' => 'doctors.view',
            'active_pages' => ['featured-doctors.php', 'featured-doctors-form.php'],
        ],
        [
            'file' => 'directory-articles.php',
            'label' => 'Article Page',
            'icon' => 'fa fa-newspaper-o',
            'count' => $total_directory_articles_count,
            'permission' => 'doctors.view',
            'active_pages' => ['directory-articles.php', 'directory-article-form.php'],
            'children' => [
                [
                    'file' => 'directory-article-form.php',
                    'label' => 'Write Article',
                    'icon' => 'fa fa-pencil-square-o',
                    'permission' => 'doctors.view',
                    'active_pages' => ['directory-article-form.php'],
                ],
                [
                    'file' => 'directory-articles.php',
                    'label' => 'All Article',
                    'icon' => 'fa fa-list-ul',
                    'count' => $total_directory_articles_count,
                    'permission' => 'doctors.view',
                    'active_pages' => ['directory-articles.php'],
                    'active_not_query' => ['status' => 'pending'],
                ],
                [
                    'file' => 'directory-articles.php?status=pending',
                    'label' => 'Pending',
                    'icon' => 'fa fa-clock-o',
                    'badge' => $total_pending_directory_articles_count,
                    'permission' => 'doctors.view',
                    'active_pages' => ['directory-articles.php'],
                    'active_query' => ['status' => 'pending'],
                ],
            ],
        ],
        [
            'file' => 'hospitals.php',
            'label' => 'Hospitals',
            'icon' => 'fa fa-hospital-o',
            'count' => $total_hospitals_count,
            'permission' => 'hospitals.view',
            'active_pages' => ['hospitals.php', 'hospital-form.php'],
        ],
        [
            'file' => 'specialties.php',
            'label' => 'Specialties',
            'icon' => 'fa fa-stethoscope',
            'permission' => 'specialties.manage',
        ],
        [
            'file' => 'locations.php',
            'label' => 'Locations',
            'icon' => 'fa fa-map-marker',
            'permission' => 'locations.manage',
        ],
    ],

    'Content' => [
        [
            'file' => 'blog-posts.php',
            'label' => 'Blog Posts',
            'icon' => 'fa fa-book',
            'count' => $total_blog_posts_count,
            'permission' => 'doctors.view',
            'active_pages' => ['blog-posts.php', 'blog-post-form.php', 'blog-categories.php', 'blog-category-form.php'],
            'children' => [
                [
                    'file' => 'blog-post-form.php',
                    'label' => 'Add New Post',
                    'icon' => 'fa fa-pencil-square-o',
                    'permission' => 'doctors.create',
                    'active_pages' => ['blog-post-form.php'],
                ],
                [
                    'file' => 'blog-posts.php',
                    'label' => 'All Posts',
                    'icon' => 'fa fa-list-ul',
                    'count' => $total_blog_posts_count,
                    'permission' => 'doctors.view',
                    'active_pages' => ['blog-posts.php'],
                    'active_not_query' => ['status' => 'draft'],
                ],
                [
                    'file' => 'blog-posts.php?status=draft',
                    'label' => 'Drafts',
                    'icon' => 'fa fa-file-o',
                    'badge' => $total_draft_blog_posts_count,
                    'permission' => 'doctors.view',
                    'active_pages' => ['blog-posts.php'],
                    'active_query' => ['status' => 'draft'],
                ],
                [
                    'file' => 'blog-categories.php',
                    'label' => 'Categories',
                    'icon' => 'fa fa-folder-open-o',
                    'permission' => 'specialties.manage',
                    'active_pages' => ['blog-categories.php', 'blog-category-form.php'],
                ],
            ],
        ],
    ],

    'Activity' => [
        [
            'file' => 'reviews.php',
            'label' => 'Reviews',
            'icon' => 'fa fa-comments-o',
            'count' => $total_reviews_count,
            'permission' => 'reviews.view',
        ],
        [
            'file' => 'contact-messages.php',
            'label' => 'Contact Messages',
            'icon' => 'fa fa-envelope-o',
            'count' => $total_unread_messages_count,
            'permission' => 'contacts.view',
        ],
    ],

    'Users & Claims' => [
        [
            'file' => 'users.php',
            'label' => 'Users',
            'icon' => 'fa fa-users',
            'count' => $total_users_count,
            'permission' => 'users.view',
            'active_pages' => ['users.php', 'user-view.php', 'user-edit.php'],
        ],
        [
            'file' => 'user-requests.php',
            'label' => 'User Requests',
            'icon' => 'fa fa-inbox',
            'badge' => $pending_user_requests,
            'permission' => 'claims.view',
            'active_pages' => ['user-requests.php', 'user-request-view.php'],
        ],
        [
            'file' => 'profile-claims.php',
            'label' => 'Profile Claims',
            'icon' => 'fa fa-check-circle-o',
            'badge' => $pending_profile_claims,
            'permission' => 'claims.view',
        ],
    ],

    'System' => [
        [
            'file' => 'my-panel.php',
            'label' => 'My Panel',
            'icon' => 'fa fa-th-large',
            'permission' => 'dashboard.view',
        ],
        [
            'file' => 'site-settings.php',
            'label' => 'Site Settings',
            'icon' => 'fa fa-cog',
            'permission' => 'settings.view',
        ],
        [
            'file' => 'static-pages.php',
            'label' => 'Static Pages',
            'icon' => 'fa fa-file-text-o',
            'permission' => 'settings.view',
            'active_pages' => ['static-pages.php', 'static-page-form.php'],
        ],
        [
            'file' => 'moderators.php',
            'label' => 'Moderators',
            'icon' => 'fa fa-shield',
            'count' => $total_moderators_count,
            'permission' => 'moderators.view',
        ],
    ],
];

$visible_nav_sections = [];

foreach ($admin_nav_sections as $section_title => $items) {
    foreach ($items as $item) {
        $permission = (string)($item['permission'] ?? '');

        if ($permission !== '' && !admin_header_can($permission)) {
            continue;
        }

        $visible_nav_sections[$section_title][] = $item;
    }
}

/*
|--------------------------------------------------------------------------
| Sidebar Navigation Helpers
|--------------------------------------------------------------------------
| Supports parent menu items with nested submenus and query-aware active
| states, for example the Pending Article filter.
*/
if (!function_exists('admin_header_item_is_active')) {
    function admin_header_item_is_active(array $item, string $current_page): bool
    {
        $active_pages = $item['active_pages'] ?? [$item['file'] ?? ''];

        if (!in_array($current_page, $active_pages, true)) {
            return false;
        }

        foreach ((array)($item['active_query'] ?? []) as $key => $value) {
            if ((string)($_GET[$key] ?? '') !== (string)$value) {
                return false;
            }
        }

        foreach ((array)($item['active_not_query'] ?? []) as $key => $value) {
            if ((string)($_GET[$key] ?? '') === (string)$value) {
                return false;
            }
        }

        return true;
    }
}
?>
<!DOCTYPE html>
<html lang="bn">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow">

  <title><?= e($admin_title) ?> | <?= e($admin_app_name) ?></title>

  <?php if ($admin_site_favicon_url !== ''): ?>
    <link rel="icon" type="<?= e($admin_site_favicon_mime) ?>" href="<?= e($admin_site_favicon_url) ?>">
    <link rel="shortcut icon" href="<?= e($admin_site_favicon_url) ?>">
  <?php endif; ?>

  <?php if ($admin_apple_touch_icon_url !== ''): ?>
    <link rel="apple-touch-icon" href="<?= e($admin_apple_touch_icon_url) ?>">
  <?php endif; ?>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Noto+Serif+Bengali:wght@400;500;600;700&display=swap" rel="stylesheet">

  <link rel="stylesheet" href="../assets/css/style.css?v=<?= time(); ?>">

  <style>
    :root {
      --admin-bg: #f6f8fa;
      --admin-canvas: #ffffff;
      --admin-canvas-subtle: #f6f8fa;
      --admin-border: #d0d7de;
      --admin-border-muted: #d8dee4;
      --admin-text: #24292f;
      --admin-muted: #57606a;
      --admin-accent: #0969da;
      --admin-success: #1a7f37;
      --admin-success-bg: #dafbe1;
      --admin-danger: #cf222e;
      --admin-danger-bg: #ffebe9;
      --admin-warning: #9a6700;
      --admin-warning-bg: #fff8c5;
      --admin-shadow-sm: 0 1px 0 rgba(27, 31, 36, 0.04);
      --admin-shadow-md: 0 8px 24px rgba(140, 149, 159, 0.20);
      --admin-radius: 8px;
      --admin-sidebar-width: 286px;
      --admin-topbar-height: 72px;
    }

    * {
      box-sizing: border-box;
    }

    body.premium-admin-body {
      margin: 0;
      min-height: 100vh;
      background: var(--admin-bg);
      color: var(--admin-text);
      font-family: "Inter", "Noto Serif Bengali", system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
      font-weight: 500;
      text-rendering: optimizeLegibility;
    }

    a {
      color: var(--admin-accent);
    }

    .admin-menu-toggle {
      display: none;
    }

    .admin-shell {
      display: flex;
      width: 100%;
      min-height: 100vh;
    }

    .admin-sidebar {
      position: fixed;
      inset: 0 auto 0 0;
      z-index: 60;
      width: var(--admin-sidebar-width);
      min-height: 100vh;
      padding: 16px;
      background: var(--admin-canvas);
      border-right: 1px solid var(--admin-border);
      overflow-y: auto;
    }

    .admin-sidebar::-webkit-scrollbar {
      width: 8px;
    }

    .admin-sidebar::-webkit-scrollbar-thumb {
      background: #afb8c1;
      border-radius: 999px;
    }

    .admin-brand {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
      padding-bottom: 14px;
      margin-bottom: 14px;
      border-bottom: 1px solid var(--admin-border-muted);
    }

    .admin-brand-link {
      min-width: 0;
      display: flex;
      align-items: center;
      gap: 11px;
      color: var(--admin-text);
      text-decoration: none;
    }

    .admin-brand-mark {
      width: 46px;
      height: 46px;
      flex: 0 0 46px;
      position: relative;
      overflow: hidden;
      border-radius: 9px;
      display: grid;
      place-items: center;
      color: #ffffff;
      background: linear-gradient(135deg, #2da44e, #0969da);
      font-size: 17px;
      font-weight: 800;
      letter-spacing: -0.03em;
      box-shadow: var(--admin-shadow-sm);
    }

    .admin-brand-initial {
      width: 100%;
      height: 100%;
      display: grid;
      place-items: center;
    }

    .admin-brand-mark.has-logo .admin-brand-initial {
      display: none;
    }

    .admin-brand-mark img {
      width: 100%;
      height: 100%;
      display: block;
      object-fit: contain;
      padding: 3px;
      background: #ffffff;
    }

    .admin-brand strong {
      display: block;
      color: var(--admin-text);
      font-size: 16px;
      font-weight: 700;
      line-height: 1.2;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
      max-width: 172px;
    }

    .admin-brand small {
      display: block;
      margin-top: 3px;
      color: var(--admin-muted);
      font-size: 12px;
      font-weight: 500;
    }

    .admin-close-btn {
      display: none;
      width: 34px;
      height: 34px;
      align-items: center;
      justify-content: center;
      border: 1px solid var(--admin-border);
      border-radius: 6px;
      color: var(--admin-muted);
      background: var(--admin-canvas-subtle);
      cursor: pointer;
      font-size: 22px;
      line-height: 1;
    }

    .admin-profile-mini {
      display: flex;
      align-items: center;
      gap: 11px;
      padding: 12px;
      margin-bottom: 16px;
      border: 1px solid var(--admin-border);
      border-radius: var(--admin-radius);
      background: var(--admin-canvas-subtle);
    }

    .admin-profile-link {
      color: var(--admin-text);
      text-decoration: none;
      transition: .12s ease;
    }

    .admin-profile-link:hover {
      background: #eef1f4;
      border-color: #8c959f;
      text-decoration: none;
    }

    .admin-avatar {
      width: 38px;
      height: 38px;
      flex: 0 0 38px;
      border-radius: 50%;
      display: grid;
      place-items: center;
      color: #ffffff;
      background: #57606a;
      font-size: 14px;
      font-weight: 700;
    }

    .admin-profile-mini strong {
      display: block;
      color: var(--admin-text);
      font-size: 13px;
      font-weight: 700;
    }

    .admin-profile-mini span {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      margin-top: 3px;
      color: var(--admin-muted);
      font-size: 12px;
    }

    .admin-profile-mini span::before {
      content: "";
      width: 7px;
      height: 7px;
      border-radius: 50%;
      background: var(--admin-success);
    }

    .admin-sidebar-section {
      margin-top: 18px;
    }

    .admin-sidebar-section-title {
      margin: 0 0 8px;
      padding: 0 8px;
      color: var(--admin-muted);
      font-size: 11px;
      font-weight: 700;
      letter-spacing: .08em;
      text-transform: uppercase;
    }

    .admin-nav {
      display: grid;
      gap: 2px;
    }

    .admin-nav a {
      position: relative;
      min-height: 38px;
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 8px 8px;
      border-radius: 6px;
      color: var(--admin-text);
      text-decoration: none;
      font-size: 14px;
      font-weight: 600;
      transition: .12s ease;
    }

    .admin-nav a:hover {
      background: var(--admin-canvas-subtle);
      text-decoration: none;
    }

    .admin-nav a.active {
      background: #ddf4ff;
      color: #0969da;
    }

    .admin-nav a.active::before {
      content: "";
      position: absolute;
      left: -16px;
      top: 7px;
      bottom: 7px;
      width: 4px;
      border-radius: 0 999px 999px 0;
      background: #fd8c73;
    }

    .admin-nav-icon {
      width: 28px;
      height: 28px;
      flex: 0 0 28px;
      border-radius: 6px;
      display: grid;
      place-items: center;
      color: var(--admin-muted);
      background: transparent;
      border: 1px solid transparent;
      font-size: 15px;
      font-weight: 400;
    }

    .admin-nav-icon .fa {
      line-height: 1;
    }

    .admin-nav a.active .admin-nav-icon {
      color: #0969da;
      background: #ffffff;
      border-color: rgba(9, 105, 218, 0.18);
    }

    .admin-nav-label {
      width: 100%;
      min-width: 0;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 8px;
    }

    .admin-nav-label > span:first-child {
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }

    .admin-nav-badge,
    .admin-nav-count {
      min-width: 22px;
      height: 22px;
      padding: 0 7px;
      border-radius: 999px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      font-size: 12px;
      font-weight: 700;
      white-space: nowrap;
    }

    .admin-nav-badge {
      color: var(--admin-warning);
      background: var(--admin-warning-bg);
      border: 1px solid rgba(154, 103, 0, 0.22);
    }

    .admin-nav-count {
      color: var(--admin-muted);
      background: var(--admin-canvas-subtle);
      border: 1px solid var(--admin-border-muted);
    }

    /*
    |------------------------------------------------------------------
    | Article Page submenu
    |------------------------------------------------------------------
    */
    .admin-nav-group {
      overflow: hidden;
      border-radius: 6px;
    }

    .admin-nav-group > summary {
      position: relative;
      min-height: 38px;
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 8px;
      border-radius: 6px;
      color: var(--admin-text);
      cursor: pointer;
      font-size: 14px;
      font-weight: 600;
      list-style: none;
      transition: .12s ease;
    }

    .admin-nav-group > summary::-webkit-details-marker {
      display: none;
    }

    .admin-nav-group > summary:hover {
      background: var(--admin-canvas-subtle);
    }

    .admin-nav-group.is-active > summary {
      background: #ddf4ff;
      color: #0969da;
    }

    .admin-nav-group.is-active > summary::before {
      content: "";
      position: absolute;
      left: -16px;
      top: 7px;
      bottom: 7px;
      width: 4px;
      border-radius: 0 999px 999px 0;
      background: #fd8c73;
    }

    .admin-nav-group.is-active .admin-nav-icon {
      color: #0969da;
      background: #ffffff;
      border-color: rgba(9, 105, 218, 0.18);
    }

    .admin-nav-caret {
      width: 16px;
      flex: 0 0 16px;
      color: var(--admin-muted);
      text-align: center;
      font-size: 16px;
      transition: transform .16s ease;
    }

    .admin-nav-group[open] .admin-nav-caret {
      transform: rotate(180deg);
    }

    .admin-nav-submenu {
      display: grid;
      gap: 2px;
      margin: 2px 0 6px 38px;
      padding-left: 12px;
      border-left: 1px solid var(--admin-border-muted);
    }

    .admin-nav-submenu a {
      min-height: 34px;
      padding: 7px 8px;
      font-size: 13px;
      font-weight: 600;
    }

    .admin-nav-submenu .admin-nav-icon {
      width: 24px;
      height: 24px;
      flex-basis: 24px;
      font-size: 13px;
    }

    .admin-nav-submenu a.active::before {
      display: none;
    }

    .admin-sidebar-footer {
      margin-top: 18px;
      padding-top: 14px;
      border-top: 1px solid var(--admin-border-muted);
      display: grid;
      gap: 8px;
    }

    .admin-sidebar-footer a {
      display: flex;
      align-items: center;
      justify-content: center;
      min-height: 36px;
      padding: 8px 10px;
      border-radius: 6px;
      border: 1px solid var(--admin-border);
      color: var(--admin-text);
      background: var(--admin-canvas);
      text-decoration: none;
      font-size: 13px;
      font-weight: 700;
      transition: .12s ease;
    }

    .admin-sidebar-footer a:hover {
      background: var(--admin-canvas-subtle);
      text-decoration: none;
    }

    .admin-sidebar-footer .admin-logout-link {
      color: var(--admin-danger);
      background: var(--admin-danger-bg);
      border-color: rgba(207, 34, 46, 0.22);
    }

    .admin-panel {
      width: 100%;
      min-height: 100vh;
      margin-left: var(--admin-sidebar-width);
    }

    .admin-topbar {
      position: sticky;
      top: 0;
      z-index: 40;
      min-height: var(--admin-topbar-height);
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 16px;
      padding: 14px 24px;
      background: rgba(255, 255, 255, 0.92);
      border-bottom: 1px solid var(--admin-border);
      backdrop-filter: blur(12px);
    }

    .admin-topbar-left {
      min-width: 0;
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .admin-menu-btn {
      display: none;
      width: 38px;
      height: 38px;
      align-items: center;
      justify-content: center;
      border: 1px solid var(--admin-border);
      border-radius: 6px;
      color: var(--admin-text);
      background: var(--admin-canvas-subtle);
      cursor: pointer;
      font-size: 20px;
      line-height: 1;
    }

    .admin-eyebrow {
      margin: 0 0 3px;
      color: var(--admin-muted);
      font-size: 12px;
      font-weight: 600;
    }

    .admin-topbar h1 {
      margin: 0;
      color: var(--admin-text);
      font-size: clamp(20px, 2vw, 26px);
      line-height: 1.18;
      font-weight: 700;
      letter-spacing: -0.03em;
    }

    .admin-topbar-actions {
      display: flex;
      align-items: center;
      justify-content: flex-end;
      gap: 8px;
      flex-wrap: wrap;
    }

    .admin-top-action,
    .admin-view-site {
      min-height: 34px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 7px;
      padding: 7px 12px;
      border-radius: 6px;
      border: 1px solid rgba(27, 31, 36, .15);
      background: var(--admin-canvas-subtle);
      color: var(--admin-text);
      font-size: 13px;
      font-weight: 700;
      line-height: 1;
      text-decoration: none;
      box-shadow: var(--admin-shadow-sm);
      white-space: nowrap;
      transition: .12s ease;
    }

    .admin-top-action {
      background: #2da44e;
      color: #ffffff;
    }

    .admin-top-action:hover {
      background: #1f883d;
      color: #ffffff;
      text-decoration: none;
    }

    .admin-top-action.light,
    .admin-view-site {
      background: var(--admin-canvas-subtle);
      color: var(--admin-text);
    }

    .admin-top-action.light:hover,
    .admin-view-site:hover {
      background: #eef1f4;
      color: var(--admin-text);
      text-decoration: none;
    }

    .admin-pending-pill {
      color: var(--admin-warning);
      background: var(--admin-warning-bg);
      border-color: rgba(154, 103, 0, 0.22);
    }

    .admin-main {
      padding: 24px;
    }

    .admin-main > .card,
    .admin-main .admin-card {
      background: var(--admin-canvas);
      border: 1px solid var(--admin-border);
      border-radius: var(--admin-radius);
      box-shadow: var(--admin-shadow-sm);
    }

    .admin-main table {
      width: 100%;
      border-collapse: separate;
      border-spacing: 0;
      overflow: hidden;
      background: var(--admin-canvas);
      border: 1px solid var(--admin-border);
      border-radius: var(--admin-radius);
      box-shadow: var(--admin-shadow-sm);
    }

    .admin-main table th {
      background: var(--admin-canvas-subtle);
      color: var(--admin-muted);
      font-size: 12px;
      font-weight: 700;
      text-align: left;
      text-transform: none;
      letter-spacing: 0;
    }

    .admin-main table th,
    .admin-main table td {
      padding: 12px 14px;
      border-bottom: 1px solid var(--admin-border-muted);
      vertical-align: middle;
    }

    .admin-main table tr:last-child td {
      border-bottom: 0;
    }

    .admin-main table tbody tr:hover {
      background: #f6f8fa;
    }

    .admin-main input,
    .admin-main select,
    .admin-main textarea {
      width: 100%;
      min-height: 36px;
      border: 1px solid var(--admin-border);
      border-radius: 6px;
      padding: 7px 10px;
      outline: none;
      color: var(--admin-text);
      background: var(--admin-canvas);
      font-family: inherit;
      font-size: 14px;
      transition: .12s ease;
    }

    .admin-main textarea {
      min-height: 110px;
      resize: vertical;
    }

    .admin-main input:focus,
    .admin-main select:focus,
    .admin-main textarea:focus {
      border-color: #0969da;
      box-shadow: 0 0 0 3px rgba(9, 105, 218, 0.16);
    }

    .admin-main button,
    .admin-main .btn,
    .admin-main input[type="submit"] {
      min-height: 34px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 7px;
      border: 1px solid rgba(27, 31, 36, .15);
      border-radius: 6px;
      padding: 7px 12px;
      color: #ffffff;
      background: #2da44e;
      font-family: inherit;
      font-size: 13px;
      font-weight: 700;
      line-height: 1;
      text-decoration: none;
      cursor: pointer;
      box-shadow: var(--admin-shadow-sm);
      transition: .12s ease;
    }

    .admin-main button:hover,
    .admin-main .btn:hover,
    .admin-main input[type="submit"]:hover {
      background: #1f883d;
      color: #ffffff;
      text-decoration: none;
    }

    .admin-main .btn-outline,
    .admin-main a.btn:not(.btn-primary) {
      color: var(--admin-text);
      background: var(--admin-canvas-subtle);
    }

    .admin-main .btn-outline:hover,
    .admin-main a.btn:not(.btn-primary):hover {
      color: var(--admin-text);
      background: #eef1f4;
    }

    .admin-main .btn-primary {
      color: #ffffff;
      background: #2da44e;
    }

    .admin-overlay {
      display: none;
    }

    @media (max-width: 1100px) {
      .admin-sidebar {
        transform: translateX(-105%);
        transition: .22s ease;
      }

      .admin-panel {
        margin-left: 0;
      }

      .admin-menu-btn,
      .admin-close-btn {
        display: flex;
      }

      .admin-menu-toggle:checked ~ .admin-shell .admin-sidebar {
        transform: translateX(0);
      }

      .admin-menu-toggle:checked ~ .admin-shell .admin-overlay {
        display: block;
        position: fixed;
        inset: 0;
        z-index: 50;
        background: rgba(31, 35, 40, .48);
      }
    }

    @media (max-width: 760px) {
      .admin-sidebar {
        width: min(90vw, 320px);
      }

      .admin-topbar {
        align-items: flex-start;
        flex-direction: column;
        padding: 12px 14px;
      }

      .admin-topbar-left {
        width: 100%;
      }

      .admin-topbar-actions {
        width: 100%;
        justify-content: stretch;
      }

      .admin-top-action,
      .admin-view-site {
        flex: 1;
        min-width: 132px;
      }

      .admin-main {
        padding: 16px 14px;
      }

      .admin-main table {
        display: block;
        overflow-x: auto;
        white-space: nowrap;
      }
    }
  </style>
</head>

<body class="premium-admin-body">

<input type="checkbox" id="admin-menu-toggle" class="admin-menu-toggle">

<div class="admin-shell">

  <aside class="admin-sidebar">
    <div class="admin-brand">
      <a href="dashboard.php" class="admin-brand-link">
        <span class="admin-brand-mark<?= $admin_site_logo_url !== '' ? ' has-logo' : '' ?>">
          <span class="admin-brand-initial"><?= e(strtoupper(substr($admin_app_name, 0, 1))) ?></span>
          <?php if ($admin_site_logo_url !== ''): ?>
            <?php if ($admin_site_dark_logo_url !== ''): ?>
              <picture>
                <source media="(prefers-color-scheme: dark)" srcset="<?= e($admin_site_dark_logo_url) ?>">
                <img src="<?= e($admin_site_logo_url) ?>" alt="<?= e($admin_app_name) ?> logo" onerror="this.style.display='none';this.parentNode.parentNode.querySelector('.admin-brand-initial').style.display='grid';">
              </picture>
            <?php else: ?>
              <img src="<?= e($admin_site_logo_url) ?>" alt="<?= e($admin_app_name) ?> logo" onerror="this.style.display='none';this.previousElementSibling.style.display='grid';">
            <?php endif; ?>
          <?php endif; ?>
        </span>
        <span>
          <strong><?= e($admin_app_name) ?></strong>
          <small>Admin Workspace</small>
        </span>
      </a>

      <label for="admin-menu-toggle" class="admin-close-btn" aria-label="Close menu">×</label>
    </div>

    <a href="my-panel.php" class="admin-profile-mini admin-profile-link">
      <div class="admin-avatar"><?= e(strtoupper(substr($logged_admin_name, 0, 1))) ?></div>
      <div>
        <strong><?= e($logged_admin_name) ?></strong>
        <span><?= e($logged_admin_role_label) ?></span>
      </div>
    </a>

    <?php foreach ($visible_nav_sections as $section_title => $items): ?>
      <?php if (empty($items)) continue; ?>

      <div class="admin-sidebar-section">
        <p class="admin-sidebar-section-title"><?= e($section_title) ?></p>

        <nav class="admin-nav" aria-label="<?= e($section_title) ?> navigation">
          <?php foreach ($items as $item): ?>
            <?php
              $children = [];

              foreach ((array)($item['children'] ?? []) as $child) {
                  $child_permission = (string)($child['permission'] ?? '');

                  if ($child_permission !== '' && !admin_header_can($child_permission)) {
                      continue;
                  }

                  $children[] = $child;
              }

              $is_active = admin_header_item_is_active($item, $current_admin_page);

              foreach ($children as $child) {
                  if (admin_header_item_is_active($child, $current_admin_page)) {
                      $is_active = true;
                      break;
                  }
              }
            ?>

            <?php if (!empty($children)): ?>
              <details class="admin-nav-group<?= $is_active ? ' is-active' : '' ?>" <?= $is_active ? 'open' : '' ?>>
                <summary>
                  <span class="admin-nav-icon"><i class="<?= e($item['icon']) ?>" aria-hidden="true"></i></span>

                  <span class="admin-nav-label">
                    <span><?= e($item['label']) ?></span>

                    <?php if (!empty($item['badge'])): ?>
                      <span class="admin-nav-badge"><?= e((string)$item['badge']) ?></span>
                    <?php elseif (isset($item['count']) && (int)$item['count'] > 0): ?>
                      <span class="admin-nav-count"><?= e(number_format((int)$item['count'])) ?></span>
                    <?php endif; ?>
                  </span>

                  <span class="admin-nav-caret"><i class="fa fa-angle-down" aria-hidden="true"></i></span>
                </summary>

                <div class="admin-nav-submenu">
                  <?php foreach ($children as $child): ?>
                    <?php $child_is_active = admin_header_item_is_active($child, $current_admin_page); ?>
                    <a href="<?= e($child['file']) ?>" class="<?= $child_is_active ? 'active' : '' ?>">
                      <span class="admin-nav-icon"><i class="<?= e($child['icon']) ?>" aria-hidden="true"></i></span>
                      <span class="admin-nav-label">
                        <span><?= e($child['label']) ?></span>
                        <?php if (!empty($child['badge'])): ?>
                          <span class="admin-nav-badge"><?= e((string)$child['badge']) ?></span>
                        <?php elseif (isset($child['count']) && (int)$child['count'] > 0): ?>
                          <span class="admin-nav-count"><?= e(number_format((int)$child['count'])) ?></span>
                        <?php endif; ?>
                      </span>
                    </a>
                  <?php endforeach; ?>
                </div>
              </details>
            <?php else: ?>
              <a href="<?= e($item['file']) ?>" class="<?= $is_active ? 'active' : '' ?>">
                <span class="admin-nav-icon"><i class="<?= e($item['icon']) ?>" aria-hidden="true"></i></span>

                <span class="admin-nav-label">
                  <span><?= e($item['label']) ?></span>

                  <?php if (!empty($item['badge'])): ?>
                    <span class="admin-nav-badge"><?= e((string)$item['badge']) ?></span>
                  <?php elseif (isset($item['count']) && (int)$item['count'] > 0): ?>
                    <span class="admin-nav-count"><?= e(number_format((int)$item['count'])) ?></span>
                  <?php endif; ?>
                </span>
              </a>
            <?php endif; ?>
          <?php endforeach; ?>
        </nav>
      </div>
    <?php endforeach; ?>

    <div class="admin-sidebar-footer">
      <a href="../index.php" target="_blank" rel="noopener">View Website</a>
      <a href="logout.php" class="admin-logout-link">Logout</a>
    </div>
  </aside>

  <label for="admin-menu-toggle" class="admin-overlay"></label>

  <section class="admin-panel">

    <header class="admin-topbar">
      <div class="admin-topbar-left">
        <label for="admin-menu-toggle" class="admin-menu-btn" aria-label="Open menu">☰</label>

        <div>
          <p class="admin-eyebrow"><?= e($admin_app_name) ?> Admin</p>
          <h1><?= e($admin_title) ?></h1>
        </div>
      </div>

      <div class="admin-topbar-actions">
        <?php if (admin_header_can('doctors.create')): ?>
          <a href="doctor-form.php" class="admin-top-action">+ Doctor</a>
        <?php endif; ?>

        <?php if (admin_header_can('doctors.view')): ?>
          <a href="featured-doctors.php" class="admin-top-action light">Featured Doctors</a>
          <a href="featured-doctors-form.php" class="admin-top-action light">+ Featured Context</a>
        <?php endif; ?>

        <?php if (admin_header_can('doctors.view')): ?>
          <a href="directory-article-form.php" class="admin-top-action light">+ Write Article</a>
          <a href="directory-articles.php" class="admin-top-action light">All Articles</a>
        <?php endif; ?>

        <?php if (admin_header_can('doctors.create')): ?>
          <a href="blog-post-form.php" class="admin-top-action light">+ Blog Post</a>
        <?php endif; ?>

        <?php if (admin_header_can('hospitals.create')): ?>
          <a href="hospital-form.php" class="admin-top-action light">+ Hospital</a>
        <?php endif; ?>

        <a href="my-panel.php" class="admin-top-action light">My Panel</a>

        <?php if (admin_header_can('users.view')): ?>
          <a href="users.php" class="admin-top-action light">Users</a>
        <?php endif; ?>

        <?php if (admin_header_can('settings.view')): ?>
          <a href="site-settings.php" class="admin-top-action light">Settings</a>
          <a href="static-pages.php" class="admin-top-action light">Static Pages</a>
        <?php endif; ?>

        <?php if (admin_header_can('moderators.view')): ?>
          <a href="moderators.php" class="admin-top-action light">Moderators</a>
        <?php endif; ?>

        <?php if (admin_header_can('claims.view')): ?>
          <?php if ($total_pending_badge > 0): ?>
            <a href="user-requests.php" class="admin-top-action light admin-pending-pill">
              Pending <?= e((string)$total_pending_badge) ?>
            </a>
          <?php else: ?>
            <a href="user-requests.php" class="admin-top-action light">Requests</a>
          <?php endif; ?>
        <?php endif; ?>

        <a href="../index.php" target="_blank" rel="noopener" class="admin-view-site">View Site</a>
      </div>
    </header>

    <main class="admin-main">