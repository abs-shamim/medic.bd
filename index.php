<?php
/**
 * Homepage Front Controller
 *
 * route.php handles sitemap requests, language selection, clean URLs,
 * SEO metadata, page routing, and 404 output. It returns control here only
 * when the requested URL is the English or Bangla homepage.
 */
require_once __DIR__ . '/route.php';

$home_hero_title = __t('home_hero_title', front_site_setting('home_hero_title', 'Find doctors and hospitals with confidence.'));
$home_hero_subtitle = __t('home_hero_subtitle', front_site_setting('home_hero_subtitle', 'Search verified doctors, hospitals, specialties and appointment information from one simple healthcare platform built for fast discovery.'));
$home_hero_button_text = __t('home_hero_button_text', front_site_setting('home_hero_button_text', 'Find Doctor'));
$home_hero_button_url = front_site_setting('home_hero_button_url', 'doctors');
$home_hero_image = front_setting_url('home_hero_image', '');
$site_tagline = __t('site_tagline', front_site_setting('site_tagline', 'Doctor and Hospital Directory'));
$site_description = __t('site_description', front_site_setting('site_description', 'A clean healthcare directory for finding doctors, hospitals, specialties and appointment information quickly.'));
$default_og_image = front_setting_url('default_og_image', 'assets/images/default-og-image.webp');

$home_featured_specialties_limit = front_setting_int('home_featured_specialties_limit', 8, 1, 50);
$home_featured_doctors_limit = front_setting_int('home_featured_doctors_limit', 3, 1, 50);
$home_featured_hospitals_limit = front_setting_int('home_featured_hospitals_limit', 3, 1, 50);

$home_search_status = front_site_setting('home_search_status', 'active');
$home_search_default_type = front_site_setting('home_search_default_type', 'doctors');
$home_search_placeholder = __t('search_placeholder', front_site_setting('home_search_placeholder', 'Search doctor, hospital or specialty'));
$home_search_button_text = __t('search_button', front_site_setting('home_search_button_text', 'Search'));
$home_search_location_label = __t('all_locations', front_site_setting('home_search_location_label', 'All Locations'));
$home_search_specialty_label = __t('all_specialties', front_site_setting('home_search_specialty_label', 'All Specialties'));
$home_search_locations_limit = front_setting_int('home_search_locations_limit', 100, 1, 300);
$home_search_locations = front_get_home_search_locations($home_search_locations_limit);
$home_search_action = front_home_search_action($home_search_default_type);

$front_primary_color = front_setting_color('primary_color', '#0969da');
$front_accent_color = front_setting_color('accent_color', '#2da44e');
$front_secondary_color = front_setting_color('secondary_color', '#14b8a6');
$front_body_background = front_setting_color('body_background_color', '#f6f8fa');

$counts = get_setting_counts();
$specialties = get_specialties();
$featured_doctors = get_featured_doctors($home_featured_doctors_limit);
$featured_hospitals = get_featured_hospitals($home_featured_hospitals_limit);

include __DIR__ . '/includes/header.php';
?>

<style>
  :root {
    --medic-bg: <?= e($front_body_background) ?>;
    --medic-dark: #24292f;
    --medic-muted: #57606a;
    --medic-border: #d0d7de;
    --medic-border-soft: #d8dee4;
    --medic-card: #ffffff;
    --medic-green: <?= e($front_accent_color) ?>;
    --medic-green-dark: <?= e($front_accent_color) ?>;
    --medic-blue: <?= e($front_primary_color) ?>;
    --medic-blue-light: #ddf4ff;
    --medic-purple: <?= e($front_secondary_color) ?>;
    --medic-yellow-bg: #fff8c5;
    --medic-yellow-border: #d4a72c;
    --medic-shadow: 0 8px 24px rgba(140, 149, 159, 0.15);
  }

</style>

<main class="medic-home">
  <div class="medic-container">

    <!-- Hero -->
    <section class="medic-hero">
      <div class="medic-hero-content">
        <div class="medic-badge">
          <span></span>
          <?= e($site_tagline) ?>
        </div>

        <h1><?= e($home_hero_title) ?></h1>

        <p>
          <?= e($home_hero_subtitle) ?>
        </p>

        <div class="medic-actions">
          <a href="<?= e(front_url($home_hero_button_url)) ?>" class="medic-btn medic-btn-primary">
            <?= e($home_hero_button_text) ?>
          </a>

          <a href="<?= e(front_url('hospitals')) ?>" class="medic-btn medic-btn-secondary">
            <?= e(__t('browse_hospitals', 'Browse Hospitals')) ?>
          </a>
        </div>
      </div>

      <div class="medic-preview-card">
        <div class="medic-preview-header">
          <span class="medic-dot"></span>
          <span class="medic-dot"></span>
          <span class="medic-dot"></span>
        </div>

        <div class="medic-preview-body">

          <?php if ($home_hero_image !== ''): ?>
            <div class="medic-home-hero-image">
              <img src="<?= e($home_hero_image) ?>" alt="<?= e(front_site_name()) ?>">
            </div>
          <?php endif; ?>
          <div class="medic-profile-row">
            <div class="medic-avatar">M</div>

            <div>
              <h3><?= e(front_site_name()) ?> <?= e(__t('directory', 'Directory')) ?></h3>
              <p><?= e($site_tagline) ?></p>
            </div>
          </div>

          <div class="medic-mini-list">
            <div class="medic-mini-item">
              <strong><?= e(__t('doctors', 'Doctors')) ?></strong>
              <span><?= number_format((int)($counts['doctors'] ?? 0)) ?> <?= e(__t('listed', 'listed')) ?></span>
            </div>

            <div class="medic-mini-item">
              <strong><?= e(__t('hospitals', 'Hospitals')) ?></strong>
              <span><?= number_format((int)($counts['hospitals'] ?? 0)) ?> <?= e(__t('listed', 'listed')) ?></span>
            </div>

            <div class="medic-mini-item">
              <strong><?= e(__t('specialties', 'Specialties')) ?></strong>
              <span><?= number_format((int)($counts['specialties'] ?? 0)) ?> <?= e(__t('available', 'available')) ?></span>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Search -->
    <?php if ($home_search_status !== 'inactive' && $home_search_status !== 'disabled'): ?>
      <div class="medic-search-panel">
        <form class="medic-search-form" action="<?= e($home_search_action) ?>" method="GET" id="homeDynamicSearchForm">
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

          <button type="submit" class="medic-btn medic-btn-primary">
            <?= e($home_search_button_text) ?>
          </button>
        </form>
      </div>

      <script>
        document.addEventListener('DOMContentLoaded', function () {
          const form = document.getElementById('homeDynamicSearchForm');
          const typeSelect = document.getElementById('homeSearchType');
          const specialtySelect = document.getElementById('homeSpecialtySelect');

          if (!form || !typeSelect) {
            return;
          }

          function slugify(value) {
            return String(value || '')
              .trim()
              .toLowerCase()
              .replace(/[^\p{L}\p{N}\s-]+/gu, '')
              .replace(/[\s_]+/gu, '-')
              .replace(/-+/g, '-')
              .replace(/^-|-$/g, '');
          }

          function updateSearchAction() {
            if (typeSelect.value === 'hospitals') {
              form.action = '<?= e(front_url('hospitals')) ?>';

              if (specialtySelect) {
                specialtySelect.disabled = true;
              }
            } else {
              form.action = '<?= e(front_url('doctors')) ?>';

              if (specialtySelect) {
                specialtySelect.disabled = false;
              }
            }
          }

          typeSelect.addEventListener('change', updateSearchAction);
          updateSearchAction();

          form.addEventListener('submit', function (event) {
            event.preventDefault();

            const searchInput = form.querySelector('input[name="search"]');
            const citySelect = form.querySelector('select[name="city"]');

            const type = typeSelect.value || 'doctors';
            const search = searchInput ? searchInput.value.trim() : '';
            const city = citySelect ? citySelect.value.trim() : '';
            const specialty = specialtySelect && !specialtySelect.disabled ? specialtySelect.value.trim() : '';

            let path = type === 'hospitals'
              ? '<?= e(front_url('hospitals')) ?>'
              : '<?= e(front_url('doctors')) ?>';

            if (city !== '') {
              path += '/' + slugify(city);
            }

            if (type !== 'hospitals' && specialty !== '') {
              path += '/' + slugify(specialty);
            }

            const params = new URLSearchParams();

            if (search !== '') {
              params.set('search', search);
            }

            const queryString = params.toString();

            window.location.href = path + (queryString ? '?' + queryString : '');
          });
        });
      </script>
    <?php endif; ?>

    <div class="medic-layout">

      <!-- Main Content -->
      <div class="medic-main">

        <!-- Tabs -->
        <nav class="medic-nav-tabs">
          <a href="<?= e(front_url()) ?>" class="active"><?= e(__t('overview', 'Overview')) ?></a>
          <a href="<?= e(front_url('doctors')) ?>"><?= e(__t('doctors', 'Doctors')) ?></a>
          <a href="<?= e(front_url('hospitals')) ?>"><?= e(__t('hospitals', 'Hospitals')) ?></a>
          <a href="<?= e(front_url('specialties')) ?>"><?= e(__t('specialties', 'Specialties')) ?></a>
        </nav>

        <!-- Stats -->
        <section class="medic-stats">
          <div class="medic-stat-card">
            <h3><?= number_format((int)($counts['doctors'] ?? 0)) ?>+</h3>
            <p><?= e(__t('verified_doctors', 'Verified Doctors')) ?></p>
          </div>

          <div class="medic-stat-card">
            <h3><?= number_format((int)($counts['hospitals'] ?? 0)) ?>+</h3>
            <p><?= e(__t('listed_hospitals', 'Listed Hospitals')) ?></p>
          </div>

          <div class="medic-stat-card">
            <h3><?= number_format((int)($counts['specialties'] ?? 0)) ?>+</h3>
            <p><?= e(__t('medical_specialties', 'Medical Specialties')) ?></p>
          </div>

          <div class="medic-stat-card">
            <h3>24/7</h3>
            <p><?= e(__t('appointment_support', 'Appointment Support')) ?></p>
          </div>
        </section>

        <!-- Popular Specialties -->
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
                      ? front_url('specialty/' . rawurlencode($specialty_slug))
                      : front_url('specialties');
                ?>

                <a class="medic-specialty-card" href="<?= e($specialty_url) ?>">
                  <div class="medic-specialty-icon" aria-hidden="true">
                    <?php if (!empty($specialty['image'])): ?>
                      <?php
                        $specialty_image_src = (string)$specialty['image'];
                        $specialty_image_src = preg_match('#^https?://#i', $specialty_image_src)
                            ? $specialty_image_src
                            : site_url(ltrim($specialty_image_src, '/'));
                      ?>
                      <img
                        src="<?= e($specialty_image_src) ?>"
                        alt=""
                        loading="lazy"
                      >
                    <?php else: ?>
                      <span><?= e(!empty($specialty['icon']) ? $specialty['icon'] : '⚕') ?></span>
                    <?php endif; ?>
                  </div>

                  <div>
                    <h3><?= e(lang_text((string)($specialty['name'] ?? ''), (string)($specialty['name_bn'] ?? ''))) ?></h3>
                    <p><?= e(lang_text((string)($specialty['description'] ?? ''), (string)($specialty['description_bn'] ?? ''))) ?></p>
                  </div>
                </a>
              <?php endforeach; ?>
            <?php else: ?>
              <div class="medic-empty">
                <?= e(__t('no_specialties_found', 'No specialties found.')) ?>
              </div>
            <?php endif; ?>
          </div>
        </section>

        <!-- Featured Doctors -->
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

        <!-- Featured Hospitals -->
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

      </div>

      <!-- Sidebar -->
      <aside class="medic-sidebar">

        <div class="medic-widget">
          <div class="medic-widget-header">
            <?= e(__t('about', 'About')) ?> <?= e(front_site_name()) ?>
          </div>

          <div class="medic-widget-body">
            <p>
              <?= e($site_description) ?>
            </p>
          </div>
        </div>

        <div class="medic-widget">
          <div class="medic-widget-header">
            <?= e(__t('directory_summary', 'Directory Summary')) ?>
          </div>

          <div class="medic-widget-body">
            <ul class="medic-side-list">
              <li>
                <a href="<?= e(front_url('doctors')) ?>"><?= e(__t('doctors', 'Doctors')) ?></a>
                <span><?= number_format((int)($counts['doctors'] ?? 0)) ?></span>
              </li>

              <li>
                <a href="<?= e(front_url('hospitals')) ?>"><?= e(__t('hospitals', 'Hospitals')) ?></a>
                <span><?= number_format((int)($counts['hospitals'] ?? 0)) ?></span>
              </li>

              <li>
                <a href="<?= e(front_url('specialties')) ?>"><?= e(__t('specialties', 'Specialties')) ?></a>
                <span><?= number_format((int)($counts['specialties'] ?? 0)) ?></span>
              </li>

              <li>
                <a href="<?= e(front_url('contact')) ?>"><?= e(__t('contact', 'Contact')) ?></a>
                <span><?= e(__t('help', 'Help')) ?></span>
              </li>
            </ul>
          </div>
        </div>

        <div class="medic-note">
          <?= e(__t('search_tip', 'Tip: Use the search box to filter doctors by name, specialty or location. You can also browse hospitals and appointment details from the navigation.')) ?>
        </div>

      </aside>

    </div>

  </div>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>