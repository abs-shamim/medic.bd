<?php
$medic_load_doctor_card_css = true;
$extra_head_html = ($extra_head_html ?? '') . '<link rel="stylesheet" href="' . e(site_url('assets/css/doctor-directory.css')) . '?v=' . e(front_asset_version()) . '">';

include DP_ROOT_PATH . '/includes/header.php';

echo dp_render_directory_schema($directory_schema);

/*
|--------------------------------------------------------------------------
| Directory Article Display Data
|--------------------------------------------------------------------------
| A saved directory article changes only the article section on the
| doctor-list page. Doctor cards, filters and pagination stay untouched.
|
| When Content HTML exists, only the automatic "If you are looking for
| experienced..." paragraph inside the article card is hidden. The SEO
| Meta Title and Meta Description remain visible below the breadcrumb.
*/
$directory_article = [];
$auto_article_title = '';
$auto_article_intro = '';
$auto_article_doctor_names = [];
$show_auto_article_doctor_names = true;
$article_custom_title = '';
$article_title = '';
$article_content = '';

if ($page_step === 'doctor_list') {
    $directory_article = dp_get_dynamic_directory_article($district, $thana, $specialty, $lang);

    $auto_article_title = dp_auto_article_title($district, $thana, $specialty, $lang);
    $auto_article_intro = dp_auto_article_intro($district, $thana, $specialty, $lang);

    /*
     * The directory-article admin can hide only the automatic 10-name list.
     * Missing data defaults to On, so every existing article preserves its
     * previous visible behaviour after this update.
     */
    $show_auto_article_doctor_names = !array_key_exists('show_doctor_list', $directory_article)
        || (int)$directory_article['show_doctor_list'] === 1;

    $auto_article_doctor_names = $show_auto_article_doctor_names
        ? dp_auto_article_doctor_names($doctors, 10, $lang)
        : [];

    $article_custom_title = trim((string)($directory_article['title'] ?? ''));
    $article_content = trim((string)($directory_article['content'] ?? ''));
    $article_title = $article_custom_title !== '' ? $article_custom_title : $auto_article_title;

    /*
     * A custom article body hides only the automatic article intro below.
     * It must never hide the SEO Meta Title or Meta Description displayed
     * immediately below the breadcrumb.
     */
}
?>



<main class="medic-doctors-page">
  <div class="container">

    <nav class="medic-breadcrumb">
      <a href="<?= e(front_url()) ?>"><?= e(__t('home', 'Home')) ?></a>
      <span>/</span>
      <a href="<?= e(front_url('doctors')) ?>"><?= e(__t('doctors', 'Doctors')) ?></a>

      <?php if (!empty($division) && empty($district)): ?>
        <span>/</span>
        <a href="<?= e(dp_doctors_url([
          'division_slug' => dp_row_slug($division),
          'specialty_slug' => !empty($specialty) ? dp_row_slug($specialty) : '',
        ])) ?>">
          <?= e($division['name']) ?>
        </a>
      <?php endif; ?>

      <?php if (!empty($district)): ?>
        <span>/</span>
        <a href="<?= e(dp_doctors_url(['district_slug' => dp_row_slug($district)])) ?>">
          <?= e($district['name']) ?>
        </a>
      <?php endif; ?>

      <?php if (!empty($thana)): ?>
        <span>/</span>
        <a href="<?= e(dp_doctors_url([
          'district_slug' => dp_row_slug($district),
          'thana_slug' => dp_row_slug($thana),
          'specialty_slug' => !empty($specialty) ? dp_row_slug($specialty) : '',
        ])) ?>">
          <?= e($thana['name']) ?>
        </a>
      <?php endif; ?>

      <?php if (!empty($specialty)): ?>
        <span>/</span>
        <?php
          if (!empty($district)) {
              $specialty_crumb_params = [
                  'district_slug' => dp_row_slug($district),
                  'thana_slug' => !empty($thana) ? dp_row_slug($thana) : '',
                  'specialty_slug' => dp_row_slug($specialty),
              ];
          } elseif (!empty($division)) {
              $specialty_crumb_params = [
                  'division_slug' => dp_row_slug($division),
                  'specialty_slug' => dp_row_slug($specialty),
              ];
          } else {
              $specialty_crumb_params = ['specialty_slug' => dp_row_slug($specialty)];
          }
        ?>
        <a href="<?= e(dp_doctors_url($specialty_crumb_params)) ?>">
          <?= e($specialty['name']) ?>
        </a>
      <?php endif; ?>
    </nav>

    <section class="medic-page-head">
      <h1><?= e(str_replace(" | {$site_name}", '', $page_title)) ?></h1>
      <?php if (trim((string)$meta_description) !== ''): ?>
        <p><?= e($meta_description) ?></p>
      <?php endif; ?>
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
              placeholder="<?= e(__t('search_doctor_placeholder', 'Search doctor name...')) ?>"
              aria-label="<?= e(__t('search_doctor_placeholder', 'Search doctor name...')) ?>"
            >
          </div>
          <button class="medic-btn" type="submit">
            <svg viewBox="0 0 16 16" aria-hidden="true"><circle cx="6.8" cy="6.8" r="4.5"></circle><path d="m10.2 10.2 3.1 3.1"></path></svg>
            <span><?= e(__t('search_button', 'Search')) ?></span>
          </button>
        </form>
      </div>

      <?php if ($page_step === 'doctor_list' && !empty($available_thanas)): ?>
        <details class="medic-filters">
          <summary class="medic-filters-toggle">
            <svg viewBox="0 0 16 16" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M2 4h12M4.5 8h7M7 12h2"></path></svg>
            <span><?= e(__t('filters', 'Filters')) ?></span>
            <?php if (!empty($thana)): ?>
              <span class="medic-filters-badge"><?= e($thana['name']) ?></span>
            <?php endif; ?>
            <svg class="medic-filters-chevron" viewBox="0 0 16 16" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.7"><path d="m4 6 4 4 4-4"></path></svg>
          </summary>

          <div class="medic-area-tags">
            <a class="medic-area-tag <?= empty($thana) ? 'active' : '' ?>" href="<?= e(dp_doctors_url([
              'district_slug' => dp_row_slug($district),
              'specialty_slug' => dp_row_slug($specialty),
              'search' => $filters['search'],
            ])) ?>">
              <span class="medic-area-tag-label"><?= e(__t('all_areas', 'All Areas')) ?></span>
            </a>

            <?php foreach ($available_thanas as $item): ?>
              <a class="medic-area-tag<?= !empty($item['image']) ? ' has-image' : '' ?> <?= (!empty($thana) && (int)$thana['id'] === (int)$item['id']) ? 'active' : '' ?>" href="<?= e(dp_doctors_url([
                'district_slug' => dp_row_slug($district),
                'thana_slug' => dp_row_slug($item),
                'specialty_slug' => dp_row_slug($specialty),
                'search' => $filters['search'],
              ])) ?>">
                <?php if (!empty($item['image'])): ?>
                  <span class="medic-area-tag-media">
                    <img src="<?= e(dp_image_url((string)$item['image'])) ?>" alt="<?= e($item['name']) ?>" loading="lazy">
                  </span>
                <?php endif; ?>
                <span class="medic-area-tag-label"><?= e($item['name']) ?></span>
              </a>
            <?php endforeach; ?>
          </div>
        </details>
      <?php endif; ?>
    <?php endif; ?>

    <?php if ($page_step === 'doctor_list'): ?>
      <?php
        /*
         * Display rule:
         * - Saved Title and Content HTML are used when available.
         * - Content HTML hides only the automatic intro sentence.
         * - The notable-doctor names can be turned On/Off per article language.
         * - Normal doctor cards, filters and pagination always remain.
         */
        $page_heading_title = trim(str_replace(" | {$site_name}", '', $page_title));
        $has_custom_article_title = $article_custom_title !== '';
        $show_article_title = $article_title !== ''
            && ($has_custom_article_title || $article_title !== $page_heading_title);
        $show_auto_article_intro = $article_content === ''
            && $auto_article_intro !== ''
            && $auto_article_intro !== trim((string)$meta_description);

        $show_article_card = $show_article_title
            || $show_auto_article_intro
            || $article_content !== ''
            || !empty($auto_article_doctor_names);
      ?>

      <?php if ($show_article_card): ?>
        <article class="medic-auto-article">
          <?php if ($show_article_title): ?>
            <h2><?= e($article_title) ?></h2>
          <?php endif; ?>

          <?php if ($show_auto_article_intro): ?>
            <p><?= e($auto_article_intro) ?></p>
          <?php endif; ?>

          <?php if ($article_content !== ''): ?>
            <div class="medic-auto-article-content">
              <?= dp_render_directory_article_content($article_content) ?>
            </div>
          <?php endif; ?>

          <?php if (!empty($auto_article_doctor_names)): ?>
            <h3><?= e(dp_auto_article_list_title($lang)) ?></h3>
            <ul>
              <?php foreach ($auto_article_doctor_names as $doctor_name): ?>
                <li><?= e($doctor_name) ?></li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </article>
      <?php endif; ?>
    <?php endif; ?>

    <?php if ($page_step === 'division'): ?>
      <section class="medic-step-grid">
        <?php foreach ($divisions as $item): ?>
          <a class="medic-step-card<?= !empty($item['image']) ? ' has-image' : '' ?>" href="<?= e(dp_doctors_url([
            'division_slug' => dp_row_slug($item),
            'specialty_slug' => !empty($specialty) ? dp_row_slug($specialty) : '',
          ])) ?>">
            <?php if (!empty($item['image'])): ?>
              <span class="medic-step-card-media">
                <img src="<?= e(dp_image_url((string)$item['image'])) ?>" alt="<?= e($item['name']) ?>" loading="lazy">
              </span>
            <?php endif; ?>
            <span class="medic-step-card-label"><?= e($item['name']) ?></span>
          </a>
        <?php endforeach; ?>
      </section>

      <?php if (empty($divisions)): ?>
        <div class="medic-empty-card">
          <h2><?= e(__t('no_division_found', 'No division found')) ?></h2>
          <p><?= e(__t('add_divisions_first', 'Please add divisions in the database first.')) ?></p>
        </div>
      <?php endif; ?>
    <?php endif; ?>

    <?php if ($page_step === 'district'): ?>
      <section class="medic-step-grid">
        <?php foreach ($districts as $item): ?>
          <a class="medic-step-card<?= !empty($item['image']) ? ' has-image' : '' ?>" href="<?= e(dp_doctors_url([
            'district_slug' => dp_row_slug($item),
            'specialty_slug' => !empty($specialty) ? dp_row_slug($specialty) : '',
          ])) ?>">
            <?php if (!empty($item['image'])): ?>
              <span class="medic-step-card-media">
                <img src="<?= e(dp_image_url((string)$item['image'])) ?>" alt="<?= e($item['name']) ?>" loading="lazy">
              </span>
            <?php endif; ?>
            <span class="medic-step-card-label"><?= e($item['name']) ?></span>
          </a>
        <?php endforeach; ?>
      </section>

      <?php if (empty($districts)): ?>
        <div class="medic-empty-card">
          <h2><?= e(__t('no_district_found', 'No district found')) ?></h2>
          <p><?= e(__t('add_districts_first', 'Please add districts under this division.')) ?></p>
        </div>
      <?php endif; ?>
    <?php endif; ?>

    <?php if ($page_step === 'specialty'): ?>
      <section class="medic-step-grid">
        <?php foreach ($specialties as $item): ?>
          <a class="medic-step-card<?= !empty($item['image']) ? ' has-image' : '' ?>" href="<?= e(dp_doctors_url([
            'district_slug' => dp_row_slug($district),
            'specialty_slug' => dp_row_slug($item),
          ])) ?>">
            <?php if (!empty($item['image'])): ?>
              <span class="medic-step-card-media">
                <img src="<?= e(dp_image_url((string)$item['image'])) ?>" alt="<?= e($item['name']) ?>" loading="lazy">
              </span>
            <?php endif; ?>
            <span class="medic-step-card-label"><?= e($item['name']) ?></span>
          </a>
        <?php endforeach; ?>
      </section>

      <?php if (empty($specialties)): ?>
        <div class="medic-empty-card">
          <h2><?= e(__t('no_specialty_found', 'No specialty found')) ?></h2>
          <p><?= e(__t('add_specialties_first', 'Please add specialties in the database first.')) ?></p>
        </div>
      <?php endif; ?>
    <?php endif; ?>

    <?php if ($page_step === 'thana_specialty'): ?>
      <section class="medic-step-grid">
        <?php foreach ($specialties as $item): ?>
          <a class="medic-step-card<?= !empty($item['image']) ? ' has-image' : '' ?>" href="<?= e(dp_doctors_url([
            'district_slug' => dp_row_slug($district),
            'thana_slug' => dp_row_slug($thana),
            'specialty_slug' => dp_row_slug($item),
          ])) ?>">
            <?php if (!empty($item['image'])): ?>
              <span class="medic-step-card-media">
                <img src="<?= e(dp_image_url((string)$item['image'])) ?>" alt="<?= e($item['name']) ?>" loading="lazy">
              </span>
            <?php endif; ?>
            <span class="medic-step-card-label"><?= e($item['name']) ?></span>
          </a>
        <?php endforeach; ?>
      </section>

      <?php if (empty($specialties)): ?>
        <div class="medic-empty-card">
          <h2><?= e(__t('no_specialty_found', 'No specialty found')) ?></h2>
          <p><?= e(__t('no_area_specialty_found', 'No specialty has active doctors in this area yet.')) ?></p>
        </div>
      <?php endif; ?>
    <?php endif; ?>

    <?php if ($page_step !== 'not_found'): ?>
      <section class="medic-doctor-list">
        <h2 class="medic-list-title"><?= e($doctor_list_heading) ?></h2>

        <?php if ($doctors): ?>
          <?php
            if (function_exists('medic_dc_preload_chambers')) {
                medic_dc_preload_chambers(array_column($doctors, 'id'));
            }
          ?>
          <?php foreach ($doctors as $doctor): ?>
            <?php
              $show_featured_style = $page_step === 'doctor_list'
                && !empty($district)
                && !empty($specialty)
                && !empty($doctor['context_is_featured']);
            ?>
            <?php front_line_break(); ?>
            <div class="medic-doctor-card-shell <?= $show_featured_style ? 'is-featured-doctor' : '' ?>">
              <?php if ($show_featured_style): ?>
                <div class="medic-featured-ribbon">
                  <span><?= e(__t('featured_doctor', 'Featured Doctor')) ?></span>
                </div>
              <?php endif; ?>

              <?php include DP_ROOT_PATH . '/includes/doctor-card.php'; ?>
            </div>
          <?php endforeach; ?>

          <?php if ($total_pages > 1): ?>
            <nav class="medic-pagination" aria-label="Doctor pagination">
              <div class="medic-pagination-inner medic-pagination-desktop">
                <?php if ($has_previous_page): ?>
                  <a class="nav-link" rel="prev" href="<?= e($prev_page_url) ?>"><?= e(__t('previous', 'Previous')) ?></a>
                <?php else: ?>
                  <span class="nav-link disabled" aria-disabled="true"><?= e(__t('previous', 'Previous')) ?></span>
                <?php endif; ?>

                <?php foreach ($pagination_items_desktop as $item): ?>
                  <?php if (is_string($item)): ?>
                    <span class="dots" aria-hidden="true">...</span>
                  <?php else: ?>
                    <?php $page_number = (int)$item; ?>

                    <?php if ($page_number === $current_page): ?>
                      <span class="active" aria-current="page"><?= e((string)$page_number) ?></span>
                    <?php else: ?>
                      <a href="<?= e(dp_pagination_url($current_action_url, $page_number, $filters['search'])) ?>" aria-label="<?= e(__t('go_to_page', 'Go to page')) ?> <?= e((string)$page_number) ?>">
                        <?= e((string)$page_number) ?>
                      </a>
                    <?php endif; ?>
                  <?php endif; ?>
                <?php endforeach; ?>

                <?php if ($has_next_page): ?>
                  <a class="nav-link" rel="next" href="<?= e($next_page_url) ?>"><?= e(__t('next', 'Next')) ?></a>
                <?php else: ?>
                  <span class="nav-link disabled" aria-disabled="true"><?= e(__t('next', 'Next')) ?></span>
                <?php endif; ?>
              </div>

              <div class="medic-pagination-inner medic-pagination-mobile">
                <?php if ($has_previous_page): ?>
                  <a class="nav-link nav-link-icon" rel="prev" href="<?= e($prev_page_url) ?>" aria-label="<?= e(__t('previous', 'Previous')) ?>">
                    <svg viewBox="0 0 16 16" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M10 3.5 5 8l5 4.5"></path></svg>
                  </a>
                <?php else: ?>
                  <span class="nav-link nav-link-icon disabled" aria-disabled="true" aria-label="<?= e(__t('previous', 'Previous')) ?>">
                    <svg viewBox="0 0 16 16" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M10 3.5 5 8l5 4.5"></path></svg>
                  </span>
                <?php endif; ?>

                <?php foreach ($pagination_items_mobile as $item): ?>
                  <?php if (is_string($item)): ?>
                    <span class="dots" aria-hidden="true">...</span>
                  <?php else: ?>
                    <?php $page_number = (int)$item; ?>

                    <?php if ($page_number === $current_page): ?>
                      <span class="active" aria-current="page"><?= e((string)$page_number) ?></span>
                    <?php else: ?>
                      <a href="<?= e(dp_pagination_url($current_action_url, $page_number, $filters['search'])) ?>" aria-label="<?= e(__t('go_to_page', 'Go to page')) ?> <?= e((string)$page_number) ?>">
                        <?= e((string)$page_number) ?>
                      </a>
                    <?php endif; ?>
                  <?php endif; ?>
                <?php endforeach; ?>

                <?php if ($has_next_page): ?>
                  <a class="nav-link nav-link-icon" rel="next" href="<?= e($next_page_url) ?>" aria-label="<?= e(__t('next', 'Next')) ?>">
                    <svg viewBox="0 0 16 16" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m6 3.5 5 4.5-5 4.5"></path></svg>
                  </a>
                <?php else: ?>
                  <span class="nav-link nav-link-icon disabled" aria-disabled="true" aria-label="<?= e(__t('next', 'Next')) ?>">
                    <svg viewBox="0 0 16 16" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m6 3.5 5 4.5-5 4.5"></path></svg>
                  </span>
                <?php endif; ?>
              </div>
            </nav>

            <p class="medic-pagination-summary">
              <?= e(__t('showing', 'Showing')) ?> <?= e((string)min($total_doctors, $doctor_offset + 1)) ?>-<?= e((string)min($total_doctors, $doctor_offset + count($doctors))) ?> <?= e(__t('of', 'of')) ?> <?= e((string)$total_doctors) ?> <?= e(__t('doctors', 'doctors')) ?>
            </p>
          <?php endif; ?>
        <?php else: ?>
          <div class="medic-empty-card">
            <h2><?= e(__t('no_doctors_found', 'No doctor found')) ?></h2>
            <p><?= e(__t('no_doctors_found_text', 'Please try another search or check whether this doctor has an active chamber in this location.')) ?></p>
          </div>
        <?php endif; ?>
      </section>
    <?php endif; ?>

    <?php if ($page_step === 'not_found'): ?>
      <div class="medic-empty-card">
        <h2><?= e(__t('page_not_found_title', 'Page not found')) ?></h2>
        <p><?= e(__t('page_not_found_text', 'The selected division, district, or specialty was not found.')) ?></p>
        <a class="medic-btn" href="<?= e(front_url('doctors')) ?>"><?= e(__t('back_to_doctors', 'Back to Doctors')) ?></a>
      </div>
    <?php endif; ?>

  </div>
</main>

<?php include DP_ROOT_PATH . '/includes/footer.php'; ?>
