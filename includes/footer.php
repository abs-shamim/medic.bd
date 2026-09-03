<?php
$footer_version = 'frontend-language-footer-20260609';

/*
|--------------------------------------------------------------------------
| Site Settings Helpers
|--------------------------------------------------------------------------
| Footer uses values from admin/site-settings.php / site_settings table.
|--------------------------------------------------------------------------
*/

if (!function_exists('medic_footer_table_exists')) {
    function medic_footer_table_exists(string $table): bool
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

if (!function_exists('medic_footer_setting')) {
    function medic_footer_setting(string $key, string $default = ''): string
    {
        global $pdo;

        static $settings_cache = null;

        if ($settings_cache === null) {
            $settings_cache = [];

            try {
                if (!isset($pdo) || !medic_footer_table_exists('site_settings')) {
                    return $default;
                }

                $stmt = $pdo->query("SELECT setting_key, setting_value FROM site_settings");
                $rows = $stmt->fetchAll();

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

if (!function_exists('medic_footer_site_name')) {
    function medic_footer_site_name(): string
    {
        return medic_footer_setting('site_name', defined('APP_NAME') ? APP_NAME : 'Deluti');
    }
}

if (!function_exists('medic_footer_color')) {
    function medic_footer_color(string $key, string $default): string
    {
        $value = medic_footer_setting($key, $default);

        if (preg_match('/^#[0-9a-fA-F]{6}$/', $value)) {
            return $value;
        }

        return $default;
    }
}

if (!function_exists('medic_footer_asset_url')) {
    function medic_footer_asset_url(string $path, string $fallback = ''): string
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

if (!function_exists('medic_footer_phone_href')) {
    function medic_footer_phone_href(string $phone): string
    {
        return preg_replace('/[^\d+]/', '', trim($phone));
    }
}

/*
|--------------------------------------------------------------------------
| Translation Fallback Helper
|--------------------------------------------------------------------------
| This fallback keeps the footer safe if __t() is not loaded yet.
|--------------------------------------------------------------------------
*/

if (!function_exists('__t')) {
    function __t(string $key, string $fallback = ''): string
    {
        return $fallback !== '' ? $fallback : $key;
    }
}

/*
|--------------------------------------------------------------------------
| Frontend URL Fallback Helper
|--------------------------------------------------------------------------
| This fallback keeps the footer safe if front_url() is not loaded yet.
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
| Dynamic Footer Values
|--------------------------------------------------------------------------
*/

$footer_site_name = medic_footer_site_name();

$footer_site_logo = medic_footer_asset_url(
    medic_footer_setting('site_logo', ''),
    ''
);

$footer_about = medic_footer_setting(
    'footer_about_text',
    medic_footer_setting(
        'site_description',
        __t(
            'footer_about_text',
            'A lightweight doctor and hospital directory platform for finding trusted healthcare information, specialties and appointment details.'
        )
    )
);

$footer_address = medic_footer_setting('contact_address', medic_footer_setting('address', ''));
$footer_email = medic_footer_setting('contact_email', medic_footer_setting('support_email', ''));
$footer_phone = medic_footer_setting('contact_phone', medic_footer_setting('emergency_phone', ''));

$footer_newsletter_status = medic_footer_setting('newsletter_status', 'active');

$footer_newsletter_title = medic_footer_setting(
    'newsletter_title',
    __t('newsletter_title', 'Need help finding the right doctor?')
);

$footer_newsletter_text = medic_footer_setting(
    'newsletter_description',
    __t('newsletter_description', 'Get updates about doctors, hospitals and appointment services.')
);

$footer_newsletter_action = medic_footer_setting('newsletter_action_url', '#');

$footer_copyright = medic_footer_setting(
    'footer_copyright',
    '© ' . date('Y') . ' ' . $footer_site_name . '. ' . __t('all_rights_reserved', 'All rights reserved.')
);

$footer_privacy_url = medic_footer_setting('privacy_policy_url', 'privacy-policy');
$footer_terms_url = medic_footer_setting('terms_url', medic_footer_setting('terms_conditions_url', 'terms'));
$footer_support_url = medic_footer_setting('support_url', 'support');

$footer_primary_color = medic_footer_color('primary_color', '#0969da');
$footer_accent_color = medic_footer_color('accent_color', '#2da44e');
$footer_body_background = medic_footer_color('body_background_color', '#f6f8fa');
?>

<footer class="medic-footer">

<style>
  :root {
    --footer-primary: <?= e($footer_primary_color) ?>;
    --footer-accent: <?= e($footer_accent_color) ?>;
    --footer-bg: <?= e($footer_body_background) ?>;
    --footer-dark: #24292f;
    --footer-muted: #57606a;
    --footer-border: #d0d7de;
    --footer-card: #ffffff;
  }
</style>

<footer class="medic-footer">

  <?php if ($footer_newsletter_status !== 'inactive' && $footer_newsletter_status !== 'disabled'): ?>
    <section class="medic-footer-newsletter">
      <div class="container medic-newsletter-inner">
        <div>
          <h2><?= e($footer_newsletter_title) ?></h2>
          <p><?= e($footer_newsletter_text) ?></p>
        </div>

        <form class="medic-newsletter-form" action="<?= e($footer_newsletter_action) ?>" method="POST">
          <input
            type="email"
            name="email"
            placeholder="<?= e(__t('enter_your_email', 'Enter your email')) ?>"
            required
          >
          <button type="submit">
            <?= e(__t('subscribe', 'Subscribe')) ?>
          </button>
        </form>
      </div>
    </section>
  <?php endif; ?>

  <div class="container medic-footer-grid">
    <div>
      <a href="<?= e(front_url()) ?>" class="medic-footer-logo">
        <span>
          <?php if ($footer_site_logo !== ''): ?>
            <img src="<?= e($footer_site_logo) ?>" alt="<?= e($footer_site_name) ?>">
          <?php else: ?>
            +
          <?php endif; ?>
        </span>
        <?= e($footer_site_name) ?>
      </a>

      <p class="medic-footer-about">
        <?= e($footer_about) ?>
      </p>
    </div>

    <div>
      <h3><?= e(__t('menu', 'Menu')) ?></h3>

      <div class="medic-footer-links">
        <a href="<?= e(front_url()) ?>"><?= e(__t('home', 'Home')) ?></a>
        <a href="<?= e(front_url('doctors')) ?>"><?= e(__t('doctors', 'Doctors')) ?></a>
        <a href="<?= e(front_url('hospitals')) ?>"><?= e(__t('hospitals', 'Hospitals')) ?></a>
        <a href="<?= e(front_url('specialties')) ?>"><?= e(__t('specialties', 'Specialties')) ?></a>
      </div>
    </div>

    <div>
      <h3><?= e(__t('services', 'Services')) ?></h3>

      <div class="medic-footer-links">
        <a href="<?= e(front_url('doctors')) ?>"><?= e(__t('find_doctor', 'Find Doctor')) ?></a>
        <a href="<?= e(front_url('hospitals')) ?>"><?= e(__t('hospital_directory', 'Hospital Directory')) ?></a>
        <a href="<?= e(front_url('contact')) ?>"><?= e(__t('support', 'Support')) ?></a>
      </div>
    </div>

    <div>
      <h3><?= e(__t('contact', 'Contact')) ?></h3>

      <div class="medic-footer-contact-box">
        <?php if ($footer_address !== ''): ?>
          <p><?= e($footer_address) ?></p>
        <?php endif; ?>

        <?php if ($footer_email !== ''): ?>
          <p>
            <?= e(__t('email', 'Email')) ?>:
            <a href="mailto:<?= e($footer_email) ?>"><?= e($footer_email) ?></a>
          </p>
        <?php endif; ?>

        <?php if ($footer_phone !== ''): ?>
          <?php $footer_phone_href = medic_footer_phone_href($footer_phone); ?>
          <p>
            <?= e(__t('phone', 'Phone')) ?>:
            <?php if ($footer_phone_href !== ''): ?>
              <a href="tel:<?= e($footer_phone_href) ?>"><?= e($footer_phone) ?></a>
            <?php else: ?>
              <?= e($footer_phone) ?>
            <?php endif; ?>
          </p>
        <?php endif; ?>

        <?php if ($footer_address === '' && $footer_email === '' && $footer_phone === ''): ?>
          <p><?= e(__t('contact_information_empty', 'Contact information will appear here after setup.')) ?></p>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="medic-copyright">
    <div class="container medic-copyright-inner">
      <p><?= e($footer_copyright) ?></p>

      <div class="medic-footer-mini-links">
        <a href="<?= e(front_url($footer_privacy_url)) ?>"><?= e(__t('privacy_policy', 'Privacy Policy')) ?></a>
        <a href="<?= e(front_url($footer_terms_url)) ?>"><?= e(__t('terms', 'Terms')) ?></a>
        <a href="<?= e(front_url($footer_support_url)) ?>"><?= e(__t('support', 'Support')) ?></a>
      </div>
    </div>
  </div>
</footer>

</body>
</html>