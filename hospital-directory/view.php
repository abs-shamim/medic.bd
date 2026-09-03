<?php
/**
 * Hospital directory HTML view.
 *
 * The directory language is synchronized before and after the shared header.
 * This prevents shared layout code from changing /bn/hospitals UI text to English.
 */

$hp_directory_locale = function_exists('hp_sync_directory_lang')
    ? hp_sync_directory_lang()
    : ((isset($lang) && $lang === 'bn') ? 'bn' : 'en');

include HP_ROOT_PATH . '/includes/header.php';

/* Print validated directory JSON-LD once after the shared layout starts. */
if (function_exists('hp_render_directory_schema')) {
    echo hp_render_directory_schema($hospital_directory_schema ?? []);
}

/*
 * A shared header may update $lang for its own layout. Restore the hospital
 * directory locale before rendering headings, form labels and pagination.
 */
$hp_directory_locale = function_exists('hp_sync_directory_lang')
    ? hp_sync_directory_lang()
    : $hp_directory_locale;

$hp_primary_color = hp_setting_color('primary_color', '#0969da');
$hp_accent_color = hp_setting_color('accent_color', '#2da44e');
$hp_body_background = hp_setting_color('body_background_color', '#f6f8fa');

$hp_is_bn = $hp_directory_locale === 'bn';

/*
|--------------------------------------------------------------------------
| Visible Page Heading
|--------------------------------------------------------------------------
| The SEO/helper file creates $hospital_page_heading with the correct
| English or Bangla language pattern. Keeping the heading logic there
| prevents mixed output such as "Hospitals in খুলনা".
*/
$hp_clean_page_heading = '';

if (isset($hospital_page_heading) && trim((string) $hospital_page_heading) !== '') {
    $hp_clean_page_heading = (string) $hospital_page_heading;
} else {
    /*
     * Fallback for cases where the SEO/helper file has not been updated.
     * This removes the site name from the browser title before display.
     */
    $hp_clean_page_heading = preg_replace(
        '/\s\|\s' . preg_quote(hp_site_name(), '/') . '$/',
        '',
        (string) $page_title
    );
}
?>

<style>
  :root {
    --hp-primary: <?= e($hp_primary_color) ?>;
    --hp-accent: <?= e($hp_accent_color) ?>;
    --hp-bg: <?= e($hp_body_background) ?>;
    --hp-surface: #ffffff;
    --hp-surface-soft: #f6f8fa;
    --hp-border: #d8dee4;
    --hp-border-strong: #c9d1d9;
    --hp-text: #1f2328;
    --hp-muted: #656d76;
    --hp-radius: 8px;
    --hp-radius-card: 12px;
    --hp-shadow: 0 1px 2px rgba(31, 35, 40, .06);
    --hp-shadow-hover: 0 5px 14px rgba(31, 35, 40, .12);
  }

  * { box-sizing: border-box; }

  /* Keep Hospital Directory typography consistently medium-weight. */
  .medic-hospitals-page,
  .medic-hospitals-page * {
    font-weight: 500 !important;
  }

  .medic-hospitals-page {
    min-height: 100vh;
    padding: 22px 0 44px;
    background: var(--hp-bg);
    color: var(--hp-text);
  }

  .medic-hospitals-page .container {
    width: min(100% - 32px, 1220px);
    margin: 0 auto;
  }

  .medic-breadcrumb {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 6px;
    margin: 0 0 13px;
    color: var(--hp-muted);
    font-size: 13px;
    line-height: 1.45;
  }

  .medic-breadcrumb span { color: #8c959f; }
  .medic-breadcrumb a {
    color: var(--hp-primary);
    text-decoration: none;
  }
  .medic-breadcrumb a:hover { text-decoration: underline; }

  /* Plain premium heading: intentionally no box, border, shadow or accent. */
  .medic-page-head {
    margin: 2px 0 18px;
    padding: 0;
    border: 0;
    border-radius: 0;
    background: transparent;
    box-shadow: none;
  }

  [lang="bn"] .medic-hospitals-page,
  .medic-hospitals-page.is-bn { letter-spacing: 0; }

  .medic-page-head h1 {
    max-width: 900px;
    margin: 0 0 8px;
    color: var(--hp-text);
    font-size: clamp(26px, 4vw, 39px);
    line-height: 1.17;
    letter-spacing: -.028em;
  }

  .medic-page-head p {
    max-width: 790px;
    margin: 0;
    color: var(--hp-muted);
    font-size: 15px;
    line-height: 1.65;
  }

  /* Light premium search panel. */
  .medic-search-box {
    margin-bottom: 20px;
    padding: 9px;
    border: 1px solid var(--hp-border);
    border-radius: 12px;
    background: var(--hp-surface);
    box-shadow: 0 2px 5px rgba(31, 35, 40, .045);
  }

  .medic-search-row {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 8px;
    align-items: center;
  }

  .medic-search-input-wrap {
    position: relative;
    min-width: 0;
  }

  .medic-search-input-wrap svg {
    position: absolute;
    top: 50%;
    left: 13px;
    width: 16px;
    height: 16px;
    color: var(--hp-muted);
    pointer-events: none;
    transform: translateY(-50%);
  }

  .medic-search-row input[type="search"],
  .medic-search-row input:not([type="hidden"]) {
    width: 100%;
    min-height: 42px;
    padding: 9px 12px 9px 39px;
    border: 1px solid var(--hp-border-strong);
    border-radius: 9px;
    outline: none;
    background: #ffffff;
    color: var(--hp-text);
    font: inherit;
    font-size: 14px;
    line-height: 20px;
    transition: border-color .16s ease, box-shadow .16s ease, background-color .16s ease;
  }

  .medic-search-row input::placeholder { color: #8c959f; }
  .medic-search-row input:hover { border-color: #8c959f; }
  .medic-search-row input:focus {
    border-color: var(--hp-primary);
    background: #ffffff;
    box-shadow: 0 0 0 3px rgba(9, 105, 218, .13);
  }

  .medic-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    min-height: 42px;
    padding: 9px 17px;
    border: 1px solid rgba(31, 35, 40, .14);
    border-radius: 9px;
    background: var(--hp-accent);
    color: #ffffff;
    cursor: pointer;
    font-size: 14px;
    line-height: 20px;
    text-decoration: none;
    transition: filter .16s ease, transform .16s ease, box-shadow .16s ease;
  }

  .medic-btn svg {
    width: 15px;
    height: 15px;
    fill: none;
    stroke: currentColor;
    stroke-width: 2;
  }

  .medic-btn:hover {
    color: #ffffff;
    filter: brightness(.97);
    box-shadow: 0 3px 8px rgba(31, 35, 40, .12);
    transform: translateY(-1px);
  }

  /* Same directory card language as Doctors: image above on desktop, overlay on mobile. */
  .medic-step-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(132px, 1fr));
    gap: 12px;
    margin: 16px 0 26px;
  }

  .medic-step-card {
    position: relative;
    display: flex;
    flex-direction: column;
    min-width: 0;
    min-height: 68px;
    overflow: hidden;
    padding: 3px;
    border: 1px solid var(--hp-border);
    border-radius: 11px;
    background: var(--hp-surface);
    box-shadow: var(--hp-shadow);
    color: var(--hp-text);
    font-size: 15px;
    line-height: 1.35;
    text-decoration: none;
    transition: border-color .18s ease, box-shadow .18s ease, transform .18s ease;
  }

  .medic-step-card::after { display: none; }

  .medic-step-card-media {
    display: block;
    width: 100%;
    height: 112px;
    overflow: hidden;
    border-radius: 8px;
    background: var(--hp-surface-soft);
  }

  .medic-step-card-media img {
    display: block;
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform .22s ease;
  }

  .medic-step-card-label {
    display: block;
    min-width: 0;
    padding: 9px 6px 8px;
    overflow-wrap: anywhere;
    color: var(--hp-text);
    text-align: center;
  }

  .medic-step-card:not(.has-image) {
    align-items: center;
    justify-content: center;
    min-height: 68px;
    padding: 12px;
    color: var(--hp-primary);
    text-align: center;
  }
  .medic-step-card:not(.has-image) .medic-step-card-label { padding: 0; color: inherit; }

  .medic-step-card:hover,
  .medic-step-card:focus-visible {
    border-color: var(--hp-primary);
    box-shadow: var(--hp-shadow-hover);
    outline: none;
    transform: translateY(-2px);
  }

  .medic-step-card:hover .medic-step-card-media img,
  .medic-step-card:focus-visible .medic-step-card-media img { transform: scale(1.035); }

  .medic-hospital-category-section { margin: 2px 0 22px; }

  .medic-category-title {
    position: relative;
    margin: 0 0 12px;
    padding: 0 0 10px;
    border-bottom: 1px solid var(--hp-border);
    color: var(--hp-text);
    font-size: clamp(19px, 3vw, 22px);
    line-height: 1.3;
    letter-spacing: -.015em;
  }

  .medic-category-title::after {
    position: absolute;
    bottom: -1px;
    left: 0;
    width: 42px;
    height: 3px;
    border-radius: 99px;
    background: var(--hp-primary);
    content: '';
  }

  .medic-type-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin: 0;
  }

  .medic-type-tag {
    display: inline-flex;
    align-items: center;
    min-height: 36px;
    padding: 7px 12px;
    border: 1px solid var(--hp-border);
    border-radius: 8px;
    background: var(--hp-surface);
    box-shadow: var(--hp-shadow);
    color: var(--hp-primary);
    font-size: 13px;
    line-height: 1.35;
    text-decoration: none;
    transition: border-color .16s ease, background-color .16s ease, box-shadow .16s ease, transform .16s ease;
  }

  .medic-type-tag:hover,
  .medic-type-tag.active {
    border-color: var(--hp-primary);
    background: #f5faff;
    color: var(--hp-primary);
    box-shadow: 0 4px 12px rgba(31, 35, 40, .10);
    transform: translateY(-1px);
  }

  .medic-hospital-list {
    display: grid;
    gap: 12px;
    margin-top: 22px;
  }

  .medic-list-title {
    position: relative;
    margin: 0;
    padding: 0 0 12px;
    border-bottom: 1px solid var(--hp-border);
    color: var(--hp-text);
    font-size: clamp(21px, 3vw, 27px);
    line-height: 1.3;
    letter-spacing: -.02em;
  }

  .medic-list-title::after {
    position: absolute;
    bottom: -1px;
    left: 0;
    width: 54px;
    height: 3px;
    border-radius: 99px;
    background: var(--hp-primary);
    content: '';
  }

  .medic-hospital-card-shell {
    overflow: hidden;
    border: 1px solid var(--hp-border);
    border-radius: 12px;
    background: var(--hp-surface);
    box-shadow: var(--hp-shadow);
    transition: border-color .18s ease, box-shadow .18s ease, transform .18s ease;
  }

  .medic-hospital-card-shell:hover {
    border-color: #8c959f;
    box-shadow: var(--hp-shadow-hover);
    transform: translateY(-1px);
  }

  .medic-empty-card {
    margin-top: 16px;
    padding: 28px;
    border: 1px solid var(--hp-border);
    border-radius: var(--hp-radius-card);
    background: var(--hp-surface);
    box-shadow: var(--hp-shadow);
    color: var(--hp-muted);
    text-align: center;
  }
  .medic-empty-card h2 { margin: 0 0 8px; color: var(--hp-text); font-size: 21px; }
  .medic-empty-card p { max-width: 560px; margin: 0 auto 16px; line-height: 1.65; }

  .medic-pagination {
    display: flex;
    justify-content: center;
    margin: 24px 0 8px;
  }

  .medic-pagination-inner {
    display: inline-flex;
    overflow: hidden;
    max-width: 100%;
    border: 1px solid var(--hp-border);
    border-radius: var(--hp-radius);
    background: var(--hp-surface);
    box-shadow: var(--hp-shadow);
  }

  .medic-pagination.mobile-pagination { display: none; }
  .medic-pagination a,
  .medic-pagination span {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 36px;
    height: 36px;
    padding: 0 10px;
    border-right: 1px solid var(--hp-border);
    background: var(--hp-surface);
    color: var(--hp-primary);
    font-size: 14px;
    text-decoration: none;
    white-space: nowrap;
  }
  .medic-pagination a:last-child,
  .medic-pagination span:last-child { border-right: 0; }
  .medic-pagination a:hover { background: var(--hp-surface-soft); }
  .medic-pagination .active { background: var(--hp-primary); color: #ffffff; }
  .medic-pagination .disabled { background: var(--hp-surface-soft); color: #8c959f; }
  .medic-pagination .dots { color: #8c959f; pointer-events: none; }
  .medic-pagination .nav-link { min-width: 78px; }

  .medic-pagination-summary {
    margin: 9px 0 0;
    color: var(--hp-muted);
    font-size: 13px;
    text-align: center;
  }

  @media (min-width: 1000px) {
    .medic-step-grid { grid-template-columns: repeat(auto-fit, minmax(132px, 1fr)); }
  }

  @media (max-width: 840px) {
    .medic-hospitals-page { padding-top: 18px; }
    .medic-hospitals-page .container { width: min(100% - 24px, 1220px); }
    .medic-step-grid { grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 10px; }
    .medic-step-card-media { height: 98px; }
  }

  /* Mobile screenshot layout: 2 photo tiles per row with names over the image. */
  @media (max-width: 620px) {
    .medic-hospitals-page { padding: 14px 0 36px; }
    .medic-hospitals-page .container { width: min(100% - 20px, 1220px); }
    .medic-breadcrumb { margin-bottom: 10px; font-size: 12px; }
    .medic-page-head { margin-bottom: 16px; padding: 0; }
    .medic-page-head h1 { font-size: 27px; }
    .medic-page-head p { font-size: 14px; line-height: 1.58; }
    .medic-search-box { padding: 8px; border-radius: 11px; }
    .medic-search-row { grid-template-columns: 1fr; }
    .medic-search-row input { min-height: 44px; }
    .medic-btn { width: 100%; min-height: 43px; }

    .medic-step-grid {
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 10px;
    }

    .medic-step-grid { margin-top: 14px; margin-bottom: 22px; }

    .medic-step-card {
      min-height: 0;
      padding: 0;
      border-radius: 12px;
    }

    .medic-step-card.has-image { aspect-ratio: 1.26 / 1; }

    .medic-step-card.has-image .medic-step-card-media {
      width: 100%;
      height: 100%;
      border-radius: inherit;
      background: #eaeef2;
    }

    .medic-step-card.has-image .medic-step-card-label {
      position: absolute;
      right: 0;
      bottom: 0;
      left: 0;
      z-index: 1;
      min-height: 43%;
      display: flex;
      align-items: flex-end;
      padding: 25px 9px 8px;
      background: linear-gradient(180deg, rgba(0, 0, 0, 0) 0%, rgba(0, 0, 0, .78) 100%);
      color: #ffffff;
      font-size: 14px;
      line-height: 1.25;
      text-align: left;
      text-shadow: 0 1px 2px rgba(0, 0, 0, .55);
    }

    .medic-step-card:not(.has-image) {
      min-height: 74px;
      padding: 10px;
      border-radius: 12px;
    }

    .medic-hospital-category-section { margin-bottom: 20px; }
    .medic-category-title { font-size: 19px; }
    .medic-type-tags { gap: 7px; }
    .medic-type-tag { min-height: 34px; padding: 6px 10px; font-size: 12px; }
    .medic-hospital-card-shell { border-radius: 10px; }
    .medic-pagination { justify-content: flex-start; overflow-x: auto; padding-bottom: 6px; }
    .medic-pagination.desktop-pagination { display: none; }
    .medic-pagination.mobile-pagination { display: flex; justify-content: center; }
  }

  @media (max-width: 370px) {
    .medic-step-card.has-image { aspect-ratio: 1.17 / 1; }
    .medic-step-card.has-image .medic-step-card-label { font-size: 13px; padding: 23px 8px 7px; }
  }

  @media (prefers-reduced-motion: reduce) {
    *, *::before, *::after {
      scroll-behavior: auto !important;
      transition-duration: .01ms !important;
      animation-duration: .01ms !important;
    }
  }
</style>

<main class="medic-hospitals-page<?= $hp_is_bn ? ' is-bn' : '' ?>">
  <div class="container">

    <nav
      class="medic-breadcrumb"
      aria-label="<?= e(hp_display(hp_t('hospitals_page_breadcrumb_aria', 'Hospital navigation'))) ?>"
    >
      <a href="<?= e(hp_front_url()) ?>">
        <?= e(hp_display(hp_t('home', 'Home'))) ?>
      </a>

      <span>/</span>

      <a href="<?= e(hp_front_url('hospitals')) ?>">
        <?= e(hp_display(hp_t('hospitals', 'Hospitals'))) ?>
      </a>

      <?php if (!empty($division) && empty($district)): ?>
        <span>/</span>

        <a href="<?= e(hp_hospitals_url(['division_slug' => hp_row_slug($division)])) ?>">
          <?= e(hp_display($division['name'])) ?>
        </a>
      <?php endif; ?>

      <?php if (!empty($district)): ?>
        <span>/</span>

        <a href="<?= e(hp_hospitals_url(['district_slug' => hp_row_slug($district)])) ?>">
          <?= e(hp_display($district['name'])) ?>
        </a>
      <?php endif; ?>

      <?php if (!empty($thana)): ?>
        <span>/</span>

        <a href="<?= e(hp_hospitals_url([
            'district_slug' => hp_row_slug($district),
            'thana_slug' => hp_row_slug($thana),
        ])) ?>">
          <?= e(hp_display($thana['name'])) ?>
        </a>
      <?php endif; ?>

      <?php if ($hospital_type !== ''): ?>
        <span>/</span>

        <a href="<?= e(hp_hospitals_url([
            'district_slug' => !empty($district) ? hp_row_slug($district) : '',
            'thana_slug' => !empty($thana) ? hp_row_slug($thana) : '',
            'type_slug' => hp_url_slug($hospital_type),
        ])) ?>">
          <?= e(hp_display(hp_hospital_type_label($hospital_type))) ?>
        </a>
      <?php endif; ?>
    </nav>

    <section class="medic-page-head">
      <h1><?= e(hp_display($hp_clean_page_heading)) ?></h1>
      <p><?= e(hp_display($meta_description)) ?></p>
    </section>

    <?php if ($page_step !== 'not_found'): ?>
      <div class="medic-search-box">
        <form class="medic-search-row" method="GET" action="<?= e($current_action_url) ?>">
          <div class="medic-search-input-wrap">
            <svg viewBox="0 0 16 16" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="6.8" cy="6.8" r="4.5"></circle><path d="m10.2 10.2 3.1 3.1"></path></svg>
            <input
              type="search"
              name="search"
              value="<?= e($filters['search']) ?>"
              aria-label="<?= e(hp_display(hp_t('hospitals_page_search_aria', 'Search hospitals'))) ?>"
              placeholder="<?= e(hp_display(hp_t('hospitals_page_search_placeholder', 'Search hospital name...'))) ?>"
            >
          </div>

          <?php if ($filters['service'] !== ''): ?>
            <input type="hidden" name="service" value="<?= e($filters['service']) ?>">
          <?php endif; ?>

          <button class="medic-btn" type="submit">
            <svg viewBox="0 0 16 16" aria-hidden="true"><circle cx="6.8" cy="6.8" r="4.5"></circle><path d="m10.2 10.2 3.1 3.1"></path></svg>
            <span><?= e(hp_display(hp_t('search_button', 'Search'))) ?></span>
          </button>
        </form>
      </div>
    <?php endif; ?>

    <?php if ($page_step === 'division'): ?>
      <section class="medic-step-grid">
        <?php foreach ($divisions as $item): ?>
          <a
            class="medic-step-card<?= !empty($item['image']) ? ' has-image' : '' ?>"
            href="<?= e(hp_hospitals_url(['division_slug' => hp_row_slug($item)])) ?>"
          >
            <?php if (!empty($item['image'])): ?>
              <span class="medic-step-card-media">
                <img src="<?= e(hp_image_url((string)$item['image'])) ?>" alt="<?= e(hp_display($item['name'])) ?>" loading="lazy">
              </span>
            <?php endif; ?>
            <span class="medic-step-card-label"><?= e(hp_display($item['name'])) ?></span>
          </a>
        <?php endforeach; ?>
      </section>

      <?php if (empty($divisions)): ?>
        <div class="medic-empty-card">
          <h2><?= e(hp_display(hp_t('hospitals_page_no_division_found', 'No division found'))) ?></h2>
          <p><?= e(hp_display(hp_t('hospitals_page_no_division_text', 'Please add divisions in the database first.'))) ?></p>
        </div>
      <?php endif; ?>
    <?php endif; ?>

    <?php if ($page_step === 'district'): ?>
      <section class="medic-step-grid">
        <?php foreach ($districts as $item): ?>
          <a
            class="medic-step-card<?= !empty($item['image']) ? ' has-image' : '' ?>"
            href="<?= e(hp_hospitals_url(['district_slug' => hp_row_slug($item)])) ?>"
          >
            <?php if (!empty($item['image'])): ?>
              <span class="medic-step-card-media">
                <img src="<?= e(hp_image_url((string)$item['image'])) ?>" alt="<?= e(hp_display($item['name'])) ?>" loading="lazy">
              </span>
            <?php endif; ?>
            <span class="medic-step-card-label"><?= e(hp_display($item['name'])) ?></span>
          </a>
        <?php endforeach; ?>
      </section>

      <?php if (empty($districts)): ?>
        <div class="medic-empty-card">
          <h2><?= e(hp_display(hp_t('hospitals_page_no_district_found', 'No district found'))) ?></h2>
          <p><?= e(hp_display(hp_t('hospitals_page_no_district_text', 'Please add districts under this division.'))) ?></p>
        </div>
      <?php endif; ?>
    <?php endif; ?>

    <?php if ($page_step === 'thana_type'): ?>

      <?php if (empty($thana)): ?>
        <section class="medic-step-grid">
          <?php foreach ($thanas as $item): ?>
            <a
              class="medic-step-card<?= !empty($item['image']) ? ' has-image' : '' ?>"
              href="<?= e(hp_hospitals_url([
                  'district_slug' => hp_row_slug($district),
                  'thana_slug' => hp_row_slug($item),
              ])) ?>"
            >
              <?php if (!empty($item['image'])): ?>
                <span class="medic-step-card-media">
                  <img src="<?= e(hp_image_url((string)$item['image'])) ?>" alt="<?= e(hp_display($item['name'])) ?>" loading="lazy">
                </span>
              <?php endif; ?>
              <span class="medic-step-card-label"><?= e(hp_display($item['name'])) ?></span>
            </a>
          <?php endforeach; ?>
        </section>
      <?php endif; ?>

      <?php if (!empty($hospital_types)): ?>
        <section class="medic-hospital-category-section">
          <h2 class="medic-category-title">
            <?= e(hp_display($hospital_category_heading)) ?>
          </h2>

          <div class="medic-type-tags">
            <?php foreach ($hospital_types as $type): ?>
              <a
                class="medic-type-tag <?= ($hospital_type === $type) ? 'active' : '' ?>"
                href="<?= e(hp_hospitals_url([
                    'district_slug' => hp_row_slug($district),
                    'thana_slug' => !empty($thana) ? hp_row_slug($thana) : '',
                    'type_slug' => hp_url_slug($type),
                    'search' => $filters['search'],
                ])) ?>"
              >
                <?= e(hp_display(hp_hospital_type_label($type))) ?>
              </a>
            <?php endforeach; ?>
          </div>
        </section>
      <?php endif; ?>

      <?php if (empty($thanas) && empty($hospital_types) && empty($thana)): ?>
        <div class="medic-empty-card">
          <h2><?= e(hp_display(hp_t('hospitals_page_no_area_type_found', 'No area or hospital type found'))) ?></h2>
          <p><?= e(hp_display(hp_t('hospitals_page_no_area_type_text', 'Please add hospital location and type data first.'))) ?></p>
        </div>
      <?php endif; ?>
    <?php endif; ?>

    <?php if ($page_step !== 'not_found'): ?>
      <section class="medic-hospital-list">
        <h2 class="medic-list-title"><?= e(hp_display($hospital_list_heading)) ?></h2>

        <?php if ($hospitals): ?>
          <?php foreach ($hospitals as $hospital): ?>
            <div class="medic-hospital-card-shell">
              <?php include HP_ROOT_PATH . '/includes/hospital-card.php'; ?>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="medic-empty-card">
            <h2><?= e(hp_display(hp_t('hospitals_page_no_hospital_found', 'No hospital found'))) ?></h2>
            <p><?= e(hp_display(hp_t('hospitals_page_no_hospital_text', 'Please try another search or check whether this hospital is active in this location.'))) ?></p>
          </div>
        <?php endif; ?>

        <?php if ($hospitals && $total_pages > 1): ?>
          <?php
            $desktop_items = hp_pagination_items($current_page, $total_pages, false);
            $mobile_items = hp_pagination_items($current_page, $total_pages, true);
          ?>

          <nav
            class="medic-pagination desktop-pagination"
            aria-label="<?= e(hp_display(hp_t('hospitals_page_pagination_aria', 'Hospital pagination'))) ?>"
          >
            <div class="medic-pagination-inner">
              <?php if ($current_page > 1): ?>
                <a
                  class="nav-link"
                  rel="prev"
                  href="<?= e(hp_pagination_url($canonical_args, $current_page - 1)) ?>"
                >
                  ← <?= e(hp_display(hp_t('hospitals_page_previous', 'Prev'))) ?>
                </a>
              <?php else: ?>
                <span class="nav-link disabled">
                  ← <?= e(hp_display(hp_t('hospitals_page_previous', 'Prev'))) ?>
                </span>
              <?php endif; ?>

              <?php foreach ($desktop_items as $item): ?>
                <?php if (is_string($item)): ?>
                  <span class="dots">...</span>
                <?php else: ?>
                  <?php $page_number = (int) $item; ?>

                  <?php if ($page_number === $current_page): ?>
                    <span class="active"><?= e(hp_display((string) $page_number)) ?></span>
                  <?php else: ?>
                    <a href="<?= e(hp_pagination_url($canonical_args, $page_number)) ?>">
                      <?= e(hp_display((string) $page_number)) ?>
                    </a>
                  <?php endif; ?>
                <?php endif; ?>
              <?php endforeach; ?>

              <?php if ($current_page < $total_pages): ?>
                <a
                  class="nav-link"
                  rel="next"
                  href="<?= e(hp_pagination_url($canonical_args, $current_page + 1)) ?>"
                >
                  <?= e(hp_display(hp_t('hospitals_page_next', 'Next'))) ?> →
                </a>
              <?php else: ?>
                <span class="nav-link disabled">
                  <?= e(hp_display(hp_t('hospitals_page_next', 'Next'))) ?> →
                </span>
              <?php endif; ?>
            </div>
          </nav>

          <nav
            class="medic-pagination mobile-pagination"
            aria-label="<?= e(hp_display(hp_t('hospitals_page_mobile_pagination_aria', 'Hospital pagination mobile'))) ?>"
          >
            <div class="medic-pagination-inner">
              <?php if ($current_page > 1): ?>
                <a
                  class="nav-link"
                  rel="prev"
                  href="<?= e(hp_pagination_url($canonical_args, $current_page - 1)) ?>"
                >
                  ← <?= e(hp_display(hp_t('hospitals_page_previous', 'Prev'))) ?>
                </a>
              <?php else: ?>
                <span class="nav-link disabled">
                  ← <?= e(hp_display(hp_t('hospitals_page_previous', 'Prev'))) ?>
                </span>
              <?php endif; ?>

              <?php foreach ($mobile_items as $item): ?>
                <?php if (is_string($item)): ?>
                  <span class="dots">...</span>
                <?php else: ?>
                  <?php $page_number = (int) $item; ?>

                  <?php if ($page_number === $current_page): ?>
                    <span class="active"><?= e(hp_display((string) $page_number)) ?></span>
                  <?php else: ?>
                    <a href="<?= e(hp_pagination_url($canonical_args, $page_number)) ?>">
                      <?= e(hp_display((string) $page_number)) ?>
                    </a>
                  <?php endif; ?>
                <?php endif; ?>
              <?php endforeach; ?>

              <?php if ($current_page < $total_pages): ?>
                <a
                  class="nav-link"
                  rel="next"
                  href="<?= e(hp_pagination_url($canonical_args, $current_page + 1)) ?>"
                >
                  <?= e(hp_display(hp_t('hospitals_page_next', 'Next'))) ?> →
                </a>
              <?php else: ?>
                <span class="nav-link disabled">
                  <?= e(hp_display(hp_t('hospitals_page_next', 'Next'))) ?> →
                </span>
              <?php endif; ?>
            </div>
          </nav>

          <p class="medic-pagination-summary">
            <?= e(hp_display(hp_t(
                'hospitals_page_showing_summary',
                'Showing :from-:to of :total hospitals',
                [
                    ':from' => hp_display((string) min($total_hospitals, $offset + 1)),
                    ':to' => hp_display((string) min($total_hospitals, $offset + count($hospitals))),
                    ':total' => hp_display((string) $total_hospitals),
                ]
            ))) ?>
          </p>
        <?php endif; ?>
      </section>
    <?php endif; ?>

    <?php if ($page_step === 'not_found'): ?>
      <div class="medic-empty-card">
        <h2><?= e(hp_display(hp_t('hospitals_page_not_found_heading', 'Page not found'))) ?></h2>

        <p>
          <?= e(hp_display(hp_t(
              'hospitals_page_not_found_description',
              'The selected division, district, area, or hospital type was not found.'
          ))) ?>
        </p>

        <a class="medic-btn" href="<?= e(hp_front_url('hospitals')) ?>">
          <?= e(hp_display(hp_t('hospitals_page_back_to_hospitals', 'Back to Hospitals'))) ?>
        </a>
      </div>
    <?php endif; ?>

  </div>
</main>

<?php include HP_ROOT_PATH . '/includes/footer.php'; ?>