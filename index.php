<?php
/**
 * Homepage Front Controller
 *
 * route.php handles sitemap requests, language selection, clean URLs,
 * SEO metadata, page routing, and 404 output. It returns control here only
 * when the requested URL is the English or Bangla homepage.
 */
require_once __DIR__ . '/route.php';

/*
 * languages/en.php and languages/bn.php both define default translations for
 * these same keys (home_hero_title, etc.), so __t($key, $fallback) always
 * finds a value in the translation table and never reaches $fallback -
 * meaning an admin-entered Site Settings value was silently ignored.
 * English pages prefer the Site Settings value (home_hero_title) when set;
 * Bangla pages prefer the Bangla Site Settings value (home_hero_title_bn)
 * when set, otherwise fall back to the bn.php translation.
 */
if (!function_exists('front_home_setting_text')) {
    function front_home_setting_text(string $key, string $default, string $bn_key = ''): string
    {
        $is_bn = defined('CURRENT_LANG') && CURRENT_LANG === 'bn';
        $setting_key = $is_bn && $bn_key !== '' ? $bn_key : $key;
        $setting_value = trim((string) front_site_setting($setting_key, ''));

        return $setting_value !== '' ? $setting_value : __t($key, $default);
    }
}

$home_hero_title = front_home_setting_text('home_hero_title', 'Find doctors and hospitals with confidence.', 'home_hero_title_bn');
$home_hero_subtitle = front_home_setting_text('home_hero_subtitle', 'Search verified doctors, hospitals, specialties and appointment information from one simple healthcare platform built for fast discovery.', 'home_hero_subtitle_bn');
$home_hero_button_text = front_home_setting_text('home_hero_button_text', 'Find Doctor');
$home_hero_button_url = front_site_setting('home_hero_button_url', 'doctors');
$site_tagline = __t('site_tagline', front_site_setting('site_tagline', 'Doctor and Hospital Directory'));
$site_description = __t('site_description', front_site_setting('site_description', 'A clean healthcare directory for finding doctors, hospitals, specialties and appointment information quickly.'));
$default_og_image = front_setting_url('default_og_image', 'assets/images/default-og-image.webp');

$home_featured_specialties_limit = front_setting_int('home_featured_specialties_limit', 8, 0, 50);
$home_featured_doctors_limit = front_setting_int('home_featured_doctors_limit', 3, 0, 50);
$home_featured_hospitals_limit = front_setting_int('home_featured_hospitals_limit', 3, 0, 50);

$home_search_status = front_site_setting('home_search_status', 'active');
$home_search_default_type = front_site_setting('home_search_default_type', 'doctors');
$home_search_placeholder = __t('search_placeholder', front_site_setting('home_search_placeholder', 'Search doctor, hospital or specialty'));
$home_search_button_text = __t('search_button', front_site_setting('home_search_button_text', 'Search'));
$home_search_location_label = __t('all_locations', front_site_setting('home_search_location_label', 'All Locations'));
$home_search_specialty_label = __t('all_specialties', front_site_setting('home_search_specialty_label', 'All Specialties'));
$home_search_locations_limit = front_setting_int('home_search_locations_limit', 100, 1, 300);
$home_search_locations = front_get_home_search_locations($home_search_locations_limit);
$home_search_action = front_home_search_action($home_search_default_type);

/*
 * get_setting_counts() computes 10 counts (doctors, hospitals, specialties,
 * locations, reviews, users, active_users, blocked_users, pending claims,
 * pending update requests) for the admin dashboard, which costs up to ~19
 * queries per call. The homepage stat strip only ever displays 3 of those
 * numbers, so it fetches just those 3 directly instead of paying for the
 * other 7 on every homepage load.
 */
if (!function_exists('front_home_stat_counts')) {
    function front_home_stat_counts(): array
    {
        return [
            'doctors' => safe_table_count('doctors', "status='active'"),
            'hospitals' => safe_table_count('hospitals', "status='active'"),
            'specialties' => safe_table_count('specialties', "status='active'"),
        ];
    }
}

$counts = front_home_stat_counts();
$specialties = get_specialties();
$featured_doctors = $home_featured_doctors_limit > 0 ? get_featured_doctors($home_featured_doctors_limit) : [];
$featured_hospitals = $home_featured_hospitals_limit > 0 ? get_featured_hospitals($home_featured_hospitals_limit) : [];

$medic_load_home_css = true;
$medic_load_doctor_card_css = true;
$medic_load_hospital_card_css = true;

include __DIR__ . '/includes/header.php';
?>

<main class="medic-home">
  <div class="medic-container">

    <!-- Hero -->
    <section class="medic-hero">
      <div class="medic-badge">
        <span></span>
        <?= e($site_tagline) ?>
      </div>

      <h1><?= e($home_hero_title) ?></h1>

      <p><?= e($home_hero_subtitle) ?></p>

      <!-- Search -->
      <?php if ($home_search_status !== 'inactive' && $home_search_status !== 'disabled'): ?>
        <div class="medic-search-panel">
          <form class="medic-search-form" action="<?= e($home_search_action) ?>" method="GET" id="homeDynamicSearchForm" data-doctors-url="<?= e(front_url('doctors')) ?>" data-hospitals-url="<?= e(front_url('hospitals')) ?>">
            <select name="search_type" id="homeSearchType" aria-label="Search type">
              <option value="doctors" <?= $home_search_default_type === 'doctors' ? 'selected' : '' ?>><?= e(__t('doctors', 'Doctors')) ?></option>
              <option value="hospitals" <?= $home_search_default_type === 'hospitals' ? 'selected' : '' ?>><?= e(__t('hospitals', 'Hospitals')) ?></option>
            </select>

            <input
              type="search"
              name="search"
              placeholder="<?= e($home_search_placeholder) ?>"
              value="<?= e($_GET['search'] ?? '') ?>"
            >

            <select name="specialty" id="homeSpecialtySelect">
              <option value=""><?= e($home_search_specialty_label) ?></option>

              <?php if (!empty($specialties)): ?>
                <?php foreach ($specialties as $specialty): ?>
                  <?php $specialty_slug = trim((string)($specialty['slug'] ?? '')); ?>
                  <option value="<?= e($specialty_slug) ?>" <?= (($_GET['specialty'] ?? '') === $specialty_slug) ? 'selected' : '' ?>>
                    <?= e(lang_text((string)($specialty['name'] ?? ''), (string)($specialty['name_bn'] ?? ''))) ?>
                  </option>
                <?php endforeach; ?>
              <?php endif; ?>
            </select>

            <select name="city">
              <option value=""><?= e($home_search_location_label) ?></option>

              <?php foreach ($home_search_locations as $location_name): ?>
                <option value="<?= e($location_name) ?>" <?= (($_GET['city'] ?? '') === $location_name) ? 'selected' : '' ?>>
                  <?= e($location_name) ?>
                </option>
              <?php endforeach; ?>
            </select>

            <button type="submit" class="medic-btn medic-btn-primary medic-search-submit">
              <svg viewBox="0 0 16 16" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="6.8" cy="6.8" r="4.6"></circle><path d="m10.4 10.4 3.3 3.3"></path></svg>
              <span><?= e($home_search_button_text) ?></span>
            </button>
          </form>
        </div>
      <?php endif; ?>

      <div class="medic-actions">
        <a href="<?= e(front_url($home_hero_button_url)) ?>" class="medic-quick-link">
          <?= e($home_hero_button_text) ?>
        </a>

        <span class="medic-actions-divider" aria-hidden="true"></span>

        <a href="<?= e(front_url('hospitals')) ?>" class="medic-quick-link">
          <?= e(__t('browse_hospitals', 'Browse Hospitals')) ?>
        </a>
      </div>

      <!-- Stats -->
      <div class="medic-stat-strip">
        <div class="medic-stat-item">
          <strong><?= number_format((int)($counts['doctors'] ?? 0)) ?>+</strong>
          <span><?= e(__t('verified_doctors', 'Verified Doctors')) ?></span>
        </div>

        <div class="medic-stat-item">
          <strong><?= number_format((int)($counts['hospitals'] ?? 0)) ?>+</strong>
          <span><?= e(__t('listed_hospitals', 'Listed Hospitals')) ?></span>
        </div>

        <div class="medic-stat-item">
          <strong><?= number_format((int)($counts['specialties'] ?? 0)) ?>+</strong>
          <span><?= e(__t('medical_specialties', 'Medical Specialties')) ?></span>
        </div>

        <div class="medic-stat-item">
          <strong>24/7</strong>
          <span><?= e(__t('appointment_support', 'Appointment Support')) ?></span>
        </div>
      </div>
    </section>

    <?php if ($home_search_status !== 'inactive' && $home_search_status !== 'disabled'): ?>
      <script src="<?= e(site_url('assets/js/home.js')) ?>" defer></script>
    <?php endif; ?>

    <!-- Popular Specialties -->
        <?php if ($home_featured_specialties_limit > 0): ?>
        <section class="medic-section">
          <div class="medic-section-title">
            <div>
              <h2><?= e(__t('popular_specialties', 'Popular Specialties')) ?></h2>
              <p><?= e(__t('popular_specialties_text', 'Browse doctors by the most searched medical specialties.')) ?></p>
            </div>

            <a href="<?= e(front_url('specialties')) ?>" class="medic-view-link">
              <?= e(__t('view_all_specialties', 'View all specialties')) ?>
            </a>
          </div>

          <div class="medic-specialty-grid">
            <?php if (!empty($specialties)): ?>
              <?php foreach (array_slice($specialties, 0, $home_featured_specialties_limit) as $specialty): ?>
                <?php
                  $specialty_slug = trim((string)($specialty['slug'] ?? ''));

                  $specialty_url = $specialty_slug !== ''
                      ? front_url('doctors/' . rawurlencode($specialty_slug))
                      : front_url('specialties');
                ?>

                <a class="medic-specialty-card" href="<?= e($specialty_url) ?>">
                  <div class="medic-specialty-icon" aria-hidden="true">
                    <?php if (!empty($specialty['image'])): ?>
                      <?php
                        $specialty_image_src = front_icon_thumb_url((string)$specialty['image'], 200);
                      ?>
                      <img
                        src="<?= e($specialty_image_src) ?>"
                        alt=""
                        width="200"
                        height="200"
                        loading="lazy"
                      >
                    <?php else: ?>
                      <span><?= e(!empty($specialty['icon']) ? $specialty['icon'] : '⚕') ?></span>
                    <?php endif; ?>
                  </div>

                  <h3><?= e(lang_text((string)($specialty['name'] ?? ''), (string)($specialty['name_bn'] ?? ''))) ?></h3>
                </a>
              <?php endforeach; ?>
            <?php else: ?>
              <div class="medic-empty">
                <?= e(__t('no_specialties_found', 'No specialties found.')) ?>
              </div>
            <?php endif; ?>
          </div>
        </section>
        <?php endif; ?>

        <!-- Featured Doctors -->
        <?php if ($home_featured_doctors_limit > 0): ?>
        <section class="medic-section">
          <div class="medic-section-title">
            <div>
              <h2><?= e(__t('featured_doctors', 'Featured Doctors')) ?></h2>
              <p><?= e(__t('featured_doctors_text', 'Browse trusted doctors with specialty, hospital and appointment details.')) ?></p>
            </div>

            <a href="<?= e(front_url('doctors')) ?>" class="medic-view-link">
              <?= e(__t('view_all_doctors', 'View all doctors')) ?>
            </a>
          </div>

          <div class="medic-list">
            <?php if (!empty($featured_doctors)): ?>
              <?php foreach ($featured_doctors as $doctor): ?>
                <?php front_line_break(); ?>
                <div class="medic-list-card">
                  <?php include __DIR__ . '/includes/doctor-card.php'; ?>
                </div>
              <?php endforeach; ?>
            <?php else: ?>
              <div class="medic-empty">
                <?= e(__t('no_featured_doctors_found', 'No featured doctors found.')) ?>
              </div>
            <?php endif; ?>
          </div>
        </section>
        <?php endif; ?>

        <!-- Featured Hospitals -->
        <?php if ($home_featured_hospitals_limit > 0): ?>
        <section class="medic-section">
          <div class="medic-section-title">
            <div>
              <h2><?= e(__t('featured_hospitals', 'Featured Hospitals')) ?></h2>
              <p><?= e(__t('featured_hospitals_text', 'Browse hospitals with departments, emergency service and appointment support.')) ?></p>
            </div>

            <a href="<?= e(front_url('hospitals')) ?>" class="medic-view-link">
              <?= e(__t('view_all_hospitals', 'View all hospitals')) ?>
            </a>
          </div>

          <div class="medic-list">
            <?php if (!empty($featured_hospitals)): ?>
              <?php foreach ($featured_hospitals as $hospital): ?>
                <?php front_line_break(); ?>
                <div class="medic-list-card">
                  <?php include __DIR__ . '/includes/hospital-card.php'; ?>
                </div>
              <?php endforeach; ?>
            <?php else: ?>
              <div class="medic-empty">
                <?= e(__t('no_featured_hospitals_found', 'No featured hospitals found.')) ?>
              </div>
            <?php endif; ?>
          </div>
        </section>
        <?php endif; ?>

        <!-- About -->
        <section class="medic-about">
          <p><?= e($site_description) ?></p>
          <p class="medic-about-tip"><?= e(__t('search_tip', 'Tip: Use the search box to filter doctors by name, specialty or location. You can also browse hospitals and appointment details from the navigation.')) ?></p>
        </section>

  </div>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>