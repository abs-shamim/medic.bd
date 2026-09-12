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

$medic_load_hospital_card_css = true;
$extra_head_html = ($extra_head_html ?? '') . '<link rel="stylesheet" href="' . e(site_url('assets/css/hospital-directory.css')) . '?v=' . e(front_asset_version()) . '">';

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

      <?php if (empty($thana) && !empty($thanas)): ?>
        <details class="medic-filters">
          <summary class="medic-filters-toggle">
            <svg viewBox="0 0 16 16" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M2 4h12M4.5 8h7M7 12h2"></path></svg>
            <span><?= e(hp_display(hp_t('filters', 'Filters'))) ?></span>
            <svg class="medic-filters-chevron" viewBox="0 0 16 16" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.7"><path d="m4 6 4 4 4-4"></path></svg>
          </summary>

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
        </details>
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
            <?php front_line_break(); ?>
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
                  class="nav-link nav-link-icon"
                  rel="prev"
                  href="<?= e(hp_pagination_url($canonical_args, $current_page - 1)) ?>"
                  aria-label="<?= e(hp_display(hp_t('hospitals_page_previous', 'Prev'))) ?>"
                >
                  <svg viewBox="0 0 16 16" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M10 3.5 5 8l5 4.5"></path></svg>
                </a>
              <?php else: ?>
                <span class="nav-link nav-link-icon disabled" aria-label="<?= e(hp_display(hp_t('hospitals_page_previous', 'Prev'))) ?>">
                  <svg viewBox="0 0 16 16" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M10 3.5 5 8l5 4.5"></path></svg>
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
                  class="nav-link nav-link-icon"
                  rel="next"
                  href="<?= e(hp_pagination_url($canonical_args, $current_page + 1)) ?>"
                  aria-label="<?= e(hp_display(hp_t('hospitals_page_next', 'Next'))) ?>"
                >
                  <svg viewBox="0 0 16 16" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m6 3.5 5 4.5-5 4.5"></path></svg>
                </a>
              <?php else: ?>
                <span class="nav-link nav-link-icon disabled" aria-label="<?= e(hp_display(hp_t('hospitals_page_next', 'Next'))) ?>">
                  <svg viewBox="0 0 16 16" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m6 3.5 5 4.5-5 4.5"></path></svg>
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