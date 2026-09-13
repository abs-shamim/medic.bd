<?php
require_once __DIR__ . '/functions.php';

if (function_exists('front_normalize_html_output')) {
    ob_start('front_normalize_html_output');
}

if (!function_exists('e')) {
    function e($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('str_starts_with')) {
    function str_starts_with(string $haystack, string $needle): bool
    {
        return $needle === '' || strpos($haystack, $needle) === 0;
    }
}

$header_version = function_exists('front_asset_version') ? front_asset_version() : 'home-mobile-search-stack-20260909d';

/*
|--------------------------------------------------------------------------
| Site Settings Helpers
|--------------------------------------------------------------------------
| Header uses values from admin/site-settings.php / site_settings table.
|--------------------------------------------------------------------------
*/

if (!function_exists('medic_header_setting')) {
    function medic_header_setting(string $key, string $default = ''): string
    {
        $settings = function_exists('medic_site_settings_all') ? medic_site_settings_all() : [];
        $value = trim((string)($settings[$key] ?? ''));

        return $value !== '' ? $value : $default;
    }
}

if (!function_exists('medic_header_site_name')) {
    function medic_header_site_name(): string
    {
        return medic_header_setting('site_name', defined('APP_NAME') ? APP_NAME : 'Deluti');
    }
}

if (!function_exists('medic_header_color')) {
    function medic_header_color(string $key, string $default): string
    {
        $value = medic_header_setting($key, $default);

        if (preg_match('/^#[0-9a-fA-F]{6}$/', $value)) {
            return $value;
        }

        return $default;
    }
}

if (!function_exists('medic_header_asset_url')) {
    function medic_header_asset_url(string $path, string $fallback = ''): string
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

        if (function_exists('site_url')) {
            return site_url($path);
        }

        return '/' . $path;
    }
}

if (!function_exists('medic_header_phone_href')) {
    function medic_header_phone_href(string $phone): string
    {
        $phone = trim($phone);

        if ($phone === '') {
            return '';
        }

        return preg_replace('/[^\d+]/', '', $phone);
    }
}

/*
|--------------------------------------------------------------------------
| Doctor Social Card Helper
|--------------------------------------------------------------------------
| A doctor page can set $doctor_card_og_image before including header.php.
| Example public URL:
| /uploads/doctor-cards/dr-ahmed-hasan-cardiology-dhaka.jpg
|--------------------------------------------------------------------------
*/

if (!function_exists('medic_header_doctor_card_image')) {
    function medic_header_doctor_card_image($doctor = null, string $providedImage = ''): string
    {
        $providedImage = trim($providedImage);

        if ($providedImage !== '') {
            return medic_header_asset_url($providedImage);
        }

        if (!is_array($doctor)) {
            return '';
        }

        foreach ([
            'doctor_card_og_image',
            'social_card_image',
            'photo_card_image',
            'share_card_image',
            'og_image',
        ] as $key) {
            $value = trim((string)($doctor[$key] ?? ''));

            if ($value !== '') {
                return medic_header_asset_url($value);
            }
        }

        return '';
    }
}

/*
|--------------------------------------------------------------------------
| Translation Fallback Helper
|--------------------------------------------------------------------------
| This fallback keeps the header safe if __t() is not loaded yet.
|--------------------------------------------------------------------------
*/

if (!function_exists('__t')) {
    function __t(string $key, string $fallback = ''): string
    {
        return $fallback !== '' ? $fallback : $key;
    }
}


if (!function_exists('site_url')) {
    function site_url(string $path = ''): string
    {
        $base = defined('APP_URL') ? (string)APP_URL : '';

        if ($base === '') {
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $base = $scheme . '://' . $host;
        }

        return rtrim($base, '/') . '/' . ltrim($path, '/');
    }
}

/*
|--------------------------------------------------------------------------
| Frontend URL Fallback Helper
|--------------------------------------------------------------------------
| This fallback keeps the header safe if front_url() is not loaded yet.
|--------------------------------------------------------------------------
*/

if (!function_exists('front_url')) {
    function front_url(string $path = '', ?string $lang = null): string
    {
        $lang = $lang ?: (defined('CURRENT_LANG') ? CURRENT_LANG : 'en');
        $path = trim($path, '/');

        if ($lang === 'bn') {
            return site_url($path !== '' ? 'bn/' . $path : 'bn');
        }

        return site_url($path);
    }
}

/*
|--------------------------------------------------------------------------
| Current Route Helper
|--------------------------------------------------------------------------
| Removes project folder and language prefix from the current route.
|--------------------------------------------------------------------------
*/

if (!function_exists('medic_header_current_clean_route')) {
    function medic_header_current_clean_route(): string
    {
        if (function_exists('current_route')) {
            $route = trim((string)current_route(), '/');
        } else {
            $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
            $route = trim((string)$path, '/');

            if (defined('APP_URL')) {
                $app_path = trim((string)(parse_url(APP_URL, PHP_URL_PATH) ?? ''), '/');

                if ($app_path !== '' && str_starts_with($route, $app_path)) {
                    $route = trim(substr($route, strlen($app_path)), '/');
                }
            }
        }

        $route = preg_replace('/\.php$/', '', $route);

        if ($route === 'bn') {
            return '';
        }

        if (str_starts_with($route, 'bn/')) {
            $route = trim(substr($route, 3), '/');
        }

        return trim((string)$route, '/');
    }
}

if (!function_exists('medic_header_current_lang')) {
    function medic_header_current_lang(): string
    {
        if (defined('CURRENT_LANG') && in_array(CURRENT_LANG, ['en', 'bn'], true)) {
            return CURRENT_LANG;
        }

        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $path = trim((string)$path, '/');

        if (defined('APP_URL')) {
            $app_path = trim((string)(parse_url(APP_URL, PHP_URL_PATH) ?? ''), '/');

            if ($app_path !== '' && str_starts_with($path, $app_path)) {
                $path = trim(substr($path, strlen($app_path)), '/');
            }
        }

        if ($path === 'bn' || str_starts_with($path, 'bn/')) {
            return 'bn';
        }

        return 'en';
    }
}

if (!function_exists('medic_header_active')) {
    function medic_header_active(string $route): string
    {
        $current = medic_header_current_clean_route();
        $route = trim($route, '/');

        if ($route === '') {
            return $current === '' || $current === 'index' ? 'active' : '';
        }

        return ($current === $route || str_starts_with($current, $route . '/')) ? 'active' : '';
    }
}

if (!function_exists('medic_header_language_url')) {
    function medic_header_language_url(string $lang): string
    {
        $lang = in_array($lang, ['en', 'bn'], true) ? $lang : 'en';
        $route = medic_header_current_clean_route();

        if ($route === 'index') {
            $route = '';
        }

        return front_url($route, $lang);
    }
}

/*
|--------------------------------------------------------------------------
| Dynamic Header Values
|--------------------------------------------------------------------------
*/

$current_lang = medic_header_current_lang();

$site_name = medic_header_site_name();
$site_tagline = medic_header_setting('site_tagline', __t('site_tagline', 'Trusted healthcare directory'));
$site_email = medic_header_setting('contact_email', medic_header_setting('support_email', ''));
$site_emergency_phone = medic_header_setting('emergency_number', medic_header_setting('hotline_number', medic_header_setting('contact_phone', '')));
$admin_login_url = medic_header_setting('admin_login_url', 'admin/login.php');

$show_topbar = medic_header_setting('show_topbar', '1') !== '0';
$show_login_button = medic_header_setting('show_login_button', '1') !== '0';
$show_register_button = medic_header_setting('show_register_button', '1') !== '0';
$header_button_text = medic_header_setting('header_button_text', __t('add_doctor', 'Add Doctor'));
$header_button_url = medic_header_setting('header_button_url', 'user/new-profile.php');

$site_logo = medic_header_asset_url(
    medic_header_setting('site_logo', ''),
    ''
);

$site_favicon = medic_header_asset_url(
    medic_header_setting('site_favicon', ''),
    'assets/images/favicon.ico'
);

$apple_touch_icon = medic_header_asset_url(
    medic_header_setting('site_apple_touch_icon', ''),
    'assets/images/apple-touch-icon.webp'
);

$default_og_image = medic_header_asset_url(
    medic_header_setting('default_og_image', ''),
    'assets/images/default-og-image.webp'
);

$primary_color = medic_header_color('primary_color', '#0969da');
$accent_color = medic_header_color('accent_color', '#2da44e');
$body_background_color = medic_header_color('body_background_color', '#f6f8fa');

$page_title = trim((string)($page_title ?? ''));

if ($page_title === '') {
    $page_title = $site_name;
}

$meta_description = trim((string)($meta_description ?? ''));

if ($meta_description === '') {
    $meta_description = medic_header_setting(
        'meta_description',
        __t('default_meta_description', 'Find trusted doctors, hospitals, specialties and appointment information.')
    );
}

$meta_keywords = trim((string)($meta_keywords ?? ''));

$robots_meta = trim((string)($robots_meta ?? ''));

if ($robots_meta === '') {
    $robots_meta = medic_header_setting(
        'robots_meta',
        'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1'
    );
}

$google_site_verification = medic_header_setting('google_site_verification', '');
$bing_site_verification = medic_header_setting('bing_site_verification', '');
$yandex_site_verification = medic_header_setting('yandex_site_verification', '');
$custom_head_code = medic_header_setting('custom_head_code', '');

/*
|--------------------------------------------------------------------------
| Canonical And Alternate URLs
|--------------------------------------------------------------------------
*/

$current_route = medic_header_current_clean_route();

if ($current_route === 'index') {
    $current_route = '';
}

$canonical_url = trim((string)($canonical_url ?? ''));

if ($canonical_url === '') {
    $canonical_url = front_url($current_route, $current_lang);
}

$alternate_en_url = front_url($current_route, 'en');
$alternate_bn_url = front_url($current_route, 'bn');

/*
|--------------------------------------------------------------------------
| Doctor-specific Social Image
|--------------------------------------------------------------------------
| The doctor details page should set $doctor_card_og_image before including
| header.php after the JPG card exists in /uploads/doctor-cards/.
|--------------------------------------------------------------------------
*/

$doctor_card_og_image = medic_header_doctor_card_image(
    $doctor ?? null,
    (string)($doctor_card_og_image ?? '')
);

$og_image = trim((string)($og_image ?? ''));

if ($og_image === '' && $doctor_card_og_image !== '') {
    $og_image = $doctor_card_og_image;
}

if ($og_image === '') {
    $og_image = $default_og_image;
}

$og_title = trim((string)($og_title ?? $page_title));
$og_description = trim((string)($og_description ?? $meta_description));
$og_type = trim((string)($og_type ?? 'website'));
$og_locale = trim((string)($og_locale ?? ($current_lang === 'bn' ? 'bn_BD' : 'en_US')));

$og_image_alt = trim((string)($og_image_alt ?? ''));

if ($og_image_alt === '' && $doctor_card_og_image !== '') {
    $og_image_alt = $og_title;
}

$og_image_width = (int)($og_image_width ?? ($doctor_card_og_image !== '' ? 1200 : 0));
$og_image_height = (int)($og_image_height ?? ($doctor_card_og_image !== '' ? 630 : 0));
$og_image_type = trim((string)($og_image_type ?? ($doctor_card_og_image !== '' ? 'image/jpeg' : '')));

$twitter_card = trim((string)($twitter_card ?? 'summary_large_image'));

if (!in_array($og_type, ['website', 'article', 'profile'], true)) {
    $og_type = 'website';
}

if (!in_array($twitter_card, ['summary', 'summary_large_image'], true)) {
    $twitter_card = 'summary_large_image';
}

/*
|--------------------------------------------------------------------------
| Language Switch URLs
|--------------------------------------------------------------------------
*/

$language_en_url = medic_header_language_url('en');
$language_bn_url = medic_header_language_url('bn');
?>
<!DOCTYPE html>
<html lang="<?= e($current_lang) ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <title><?= e($page_title) ?></title>
  <meta name="description" content="<?= e($meta_description) ?>">
  <?php if ($meta_keywords !== ''): ?>
    <meta name="keywords" content="<?= e($meta_keywords) ?>">
  <?php endif; ?>
  <meta name="robots" content="<?= e($robots_meta) ?>">

  <?php if ($google_site_verification !== ''): ?>
    <meta name="google-site-verification" content="<?= e($google_site_verification) ?>">
  <?php endif; ?>

  <?php if ($bing_site_verification !== ''): ?>
    <meta name="msvalidate.01" content="<?= e($bing_site_verification) ?>">
  <?php endif; ?>

  <?php if ($yandex_site_verification !== ''): ?>
    <meta name="yandex-verification" content="<?= e($yandex_site_verification) ?>">
  <?php endif; ?>
  <link rel="canonical" href="<?= e($canonical_url) ?>">
  <link rel="alternate" hreflang="en" href="<?= e($alternate_en_url) ?>">
  <link rel="alternate" hreflang="bn" href="<?= e($alternate_bn_url) ?>">
  <link rel="alternate" hreflang="x-default" href="<?= e($alternate_en_url) ?>">

  <?php if ($site_favicon !== ''): ?>
    <link rel="icon" href="<?= e($site_favicon) ?>">
  <?php endif; ?>

  <?php if ($apple_touch_icon !== ''): ?>
    <link rel="apple-touch-icon" href="<?= e($apple_touch_icon) ?>">
  <?php endif; ?>

  <meta property="og:locale" content="<?= e($og_locale) ?>">
  <meta property="og:title" content="<?= e($og_title) ?>">
  <meta property="og:description" content="<?= e($og_description) ?>">
  <meta property="og:type" content="<?= e($og_type) ?>">
  <meta property="og:url" content="<?= e($canonical_url) ?>">
  <meta property="og:site_name" content="<?= e($site_name) ?>">

  <?php if ($og_image !== ''): ?>
    <meta property="og:image" content="<?= e($og_image) ?>">
    <meta property="og:image:secure_url" content="<?= e($og_image) ?>">
    <?php if ($og_image_width > 0): ?>
      <meta property="og:image:width" content="<?= e((string)$og_image_width) ?>">
    <?php endif; ?>
    <?php if ($og_image_height > 0): ?>
      <meta property="og:image:height" content="<?= e((string)$og_image_height) ?>">
    <?php endif; ?>
    <?php if ($og_image_type !== ''): ?>
      <meta property="og:image:type" content="<?= e($og_image_type) ?>">
    <?php endif; ?>
    <?php if ($og_image_alt !== ''): ?>
      <meta property="og:image:alt" content="<?= e($og_image_alt) ?>">
    <?php endif; ?>
  <?php endif; ?>

  <meta name="twitter:card" content="<?= e($twitter_card) ?>">
  <meta name="twitter:title" content="<?= e($og_title) ?>">
  <meta name="twitter:description" content="<?= e($og_description) ?>">

  <?php if ($og_image !== ''): ?>
    <meta name="twitter:image" content="<?= e($og_image) ?>">
    <?php if ($og_image_alt !== ''): ?>
      <meta name="twitter:image:alt" content="<?= e($og_image_alt) ?>">
    <?php endif; ?>
  <?php endif; ?>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <?php
    /*
     * Loaded non-blocking (preload + swap-on-load) instead of a plain
     * <link rel="stylesheet">. A synchronous Google Fonts stylesheet holds
     * up the browser's first paint of ALL text until it (and the two
     * preconnect round-trips before it) finish - on a text-heavy page like
     * a doctor/hospital listing, that stylesheet was sitting directly in
     * front of the largest visible element's paint (its LCP). style.css
     * already lists Arial/sans-serif as a fallback, so text still paints
     * immediately in that fallback and swaps to the web font once it
     * loads - no invisible-text flash, just an earlier first paint.
     */
    $google_fonts_href = 'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Noto+Serif+Bengali:wght@400;500;600;700;800&display=swap';
  ?>
  <link rel="preload" as="style" href="<?= e($google_fonts_href) ?>">
  <link rel="stylesheet" href="<?= e($google_fonts_href) ?>" media="print" onload="this.media='all'">
  <noscript><link rel="stylesheet" href="<?= e($google_fonts_href) ?>"></noscript>

<link rel="stylesheet" href="<?= e(site_url('assets/css/style.css')) ?>?v=<?= e($header_version) ?>">
<link rel="stylesheet" href="<?= e(site_url('assets/css/header.css')) ?>?v=<?= e($header_version) ?>">
<link rel="stylesheet" href="<?= e(site_url('assets/css/footer.css')) ?>?v=<?= e($header_version) ?>">
<?php if (!empty($medic_load_home_css)): ?>
<link rel="stylesheet" href="<?= e(site_url('assets/css/home.css')) ?>?v=<?= e($header_version) ?>">
<?php endif; ?>
<?php if (!empty($medic_load_doctor_card_css)): ?>
<link rel="stylesheet" href="<?= e(site_url('assets/css/doctor-card.css')) ?>?v=<?= e($header_version) ?>">
<?php endif; ?>
<?php if (!empty($medic_load_hospital_card_css)): ?>
<link rel="stylesheet" href="<?= e(site_url('assets/css/hospital-card.css')) ?>?v=<?= e($header_version) ?>">
<?php endif; ?>
<link rel="stylesheet" href="<?= e(site_url('assets/css/theme-vars.php')) ?>?v=<?= e($header_version) ?>">

  <?php if (!empty($extra_head_html)): ?>
    <?= $extra_head_html . "\n" ?>
  <?php endif; ?>

  <?php if ($custom_head_code !== ''): ?>
    <?= $custom_head_code . "\n" ?>
  <?php endif; ?>
</head>
<body>

<?php if ($show_topbar && ($site_emergency_phone !== '' || $site_email !== '')): ?>
  <div class="medic-topbar">
    <div class="container medic-topbar-inner">
      <div class="medic-topbar-left">
        <?php if ($site_emergency_phone !== ''): ?>
          <?php $emergency_href = medic_header_phone_href($site_emergency_phone); ?>
          <span>
            <?= e(__t('emergency', 'Emergency')) ?>:
            <?php if ($emergency_href !== ''): ?>
              <a href="tel:<?= e($emergency_href) ?>"><?= e($site_emergency_phone) ?></a>
            <?php else: ?>
              <?= e($site_emergency_phone) ?>
            <?php endif; ?>
          </span>
        <?php endif; ?>

        <?php if ($site_email !== ''): ?>
          <span><?= e(__t('email', 'Email')) ?>: <a href="mailto:<?= e($site_email) ?>"><?= e($site_email) ?></a></span>
        <?php endif; ?>
      </div>

      <div class="medic-topbar-right">
        <a href="<?= e(site_url($admin_login_url)) ?>"><?= e(__t('admin_login', 'Admin Login')) ?></a>
      </div>
    </div>
  </div>
<?php endif; ?>

<header class="medic-header">
  <div class="container">
    <input type="checkbox" id="menu-toggle" aria-hidden="true">

    <div class="medic-header-inner">
      <a href="<?= e(front_url()) ?>" class="medic-logo">
        <span class="medic-logo-icon">
          <?php if ($site_logo !== ''): ?>
            <img src="<?= e($site_logo) ?>" alt="<?= e($site_name) ?>">
          <?php else: ?>
            +
          <?php endif; ?>
        </span>
        <?= e($site_name) ?>
      </a>

      <nav class="medic-menu">
        <a href="<?= e(front_url()) ?>" class="<?= medic_header_active('') ?>">
          <?= e(__t('home', 'Home')) ?>
        </a>

        <a href="<?= e(front_url('doctors')) ?>" class="<?= medic_header_active('doctors') ?>">
          <?= e(__t('doctors', 'Doctors')) ?>
        </a>

        <a href="<?= e(front_url('hospitals')) ?>" class="<?= medic_header_active('hospitals') ?>">
          <?= e(__t('hospitals', 'Hospitals')) ?>
        </a>

        <a href="<?= e(front_url('specialties')) ?>" class="<?= medic_header_active('specialties') ?>">
          <?= e(__t('specialties', 'Specialties')) ?>
        </a>

        <a href="<?= e(front_url('blog')) ?>" class="<?= medic_header_active('blog') ?>">
          <?= e(__t('blog', $current_lang === 'bn' ? 'ব্লগ' : 'Blog')) ?>
        </a>

        <a href="<?= e(front_url('contact')) ?>" class="<?= medic_header_active('contact') ?>">
          <?= e(__t('contact', 'Contact')) ?>
        </a>

        <a href="<?= e(site_url($header_button_url)) ?>" class="medic-add-link">
          <?= e($header_button_text) ?>
        </a>

        <?php if ($show_login_button): ?>
          <a href="<?= e(site_url('user/login.php')) ?>" class="medic-login-link">
            <?= e(__t('login', 'Login')) ?>
          </a>
        <?php endif; ?>

        <?php if ($show_register_button): ?>
          <a href="<?= e(site_url('user/register.php')) ?>" class="medic-register-link">
            <?= e(__t('register', 'Register')) ?>
          </a>
        <?php endif; ?>

        <span class="medic-lang-switch" aria-label="Language switch">
          <a href="<?= e($language_en_url) ?>" class="<?= $current_lang === 'en' ? 'active' : '' ?>">EN</a>
          <a href="<?= e($language_bn_url) ?>" class="<?= $current_lang === 'bn' ? 'active' : '' ?>">BN</a>
        </span>
      </nav>

      <label for="menu-toggle" class="medic-mobile-btn" aria-label="<?= e(__t('toggle_mobile_menu', 'Toggle mobile menu')) ?>">☰</label>
    </div>

    <nav class="medic-mobile-menu">
      <a href="<?= e(front_url()) ?>" class="<?= medic_header_active('') ?>">
        <?= e(__t('home', 'Home')) ?>
      </a>

      <a href="<?= e(front_url('doctors')) ?>" class="<?= medic_header_active('doctors') ?>">
        <?= e(__t('doctors', 'Doctors')) ?>
      </a>

      <a href="<?= e(front_url('hospitals')) ?>" class="<?= medic_header_active('hospitals') ?>">
        <?= e(__t('hospitals', 'Hospitals')) ?>
      </a>

      <a href="<?= e(front_url('specialties')) ?>" class="<?= medic_header_active('specialties') ?>">
        <?= e(__t('specialties', 'Specialties')) ?>
      </a>

      <a href="<?= e(front_url('blog')) ?>" class="<?= medic_header_active('blog') ?>">
        <?= e(__t('blog', $current_lang === 'bn' ? 'ব্লগ' : 'Blog')) ?>
      </a>

      <a href="<?= e(front_url('contact')) ?>" class="<?= medic_header_active('contact') ?>">
        <?= e(__t('contact', 'Contact')) ?>
      </a>

      <a href="<?= e(site_url($header_button_url)) ?>">
        <?= e($header_button_text) ?>
      </a>

      <?php if ($show_login_button): ?>
        <a href="<?= e(site_url('user/login.php')) ?>">
          <?= e(__t('login', 'Login')) ?>
        </a>
      <?php endif; ?>

      <?php if ($show_register_button): ?>
        <a href="<?= e(site_url('user/register.php')) ?>">
          <?= e(__t('register', 'Register')) ?>
        </a>
      <?php endif; ?>

      <div class="medic-mobile-lang-switch" aria-label="Language switch">
        <a href="<?= e($language_en_url) ?>" class="<?= $current_lang === 'en' ? 'active' : '' ?>">EN</a>
        <a href="<?= e($language_bn_url) ?>" class="<?= $current_lang === 'bn' ? 'active' : '' ?>">BN</a>
      </div>
    </nav>
  </div>
</header>