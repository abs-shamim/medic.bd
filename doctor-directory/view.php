<?php
include DP_ROOT_PATH . '/includes/header.php';

echo dp_render_directory_schema($directory_schema);

$dp_primary_color = dp_site_setting('primary_color', '#0969da');
$dp_accent_color = dp_site_setting('accent_color', '#2da44e');
$dp_body_background = dp_site_setting('body_background_color', '#f6f8fa');

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


<style>
  :root {
    --dp-primary: <?= e($dp_primary_color) ?>;
    --dp-accent: <?= e($dp_accent_color) ?>;
    --dp-bg: <?= e($dp_body_background) ?>;
    --dp-surface: #ffffff;
    --dp-surface-soft: #f6f8fa;
    --dp-border: #d8dee4;
    --dp-border-strong: #c9d1d9;
    --dp-text: #1f2328;
    --dp-muted: #656d76;
    --dp-radius: 12px;
    --dp-radius-card: 16px;
    --dp-shadow: 0 1px 2px rgba(15, 23, 32, .04);
    --dp-shadow-hover: 0 16px 32px -14px rgba(15, 23, 32, .22);
    --dp-gradient: linear-gradient(135deg, var(--dp-primary), var(--dp-accent));
  }

  * { box-sizing: border-box; }

  /* Keep the directory typography consistently medium-weight. */
  .medic-doctors-page,
  .medic-doctors-page * {
    font-weight: 500 !important;
  }

  .medic-doctors-page {
    min-height: 100vh;
    padding: 22px 0 44px;
    background: var(--dp-bg);
    color: var(--dp-text);
  }

  .medic-doctors-page .container {
    width: min(100% - 32px, 1220px);
    margin: 0 auto;
  }

  .medic-breadcrumb {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 6px;
    margin: 0 0 13px;
    color: var(--dp-muted);
    font-size: 13px;
    line-height: 1.45;
  }

  .medic-breadcrumb span { color: #8c959f; }
  .medic-breadcrumb a {
    color: var(--dp-primary);
    font-weight: 500;
    text-decoration: none;
  }
  .medic-breadcrumb a:hover { text-decoration: underline; }

  .medic-search-box,
  .medic-empty-card,
  .medic-auto-article {
    border: 1px solid var(--dp-border);
    background: var(--dp-surface);
    box-shadow: var(--dp-shadow);
  }

  /* Plain page heading: intentionally no box, border, shadow or decorative accent. */
  .medic-page-head {
    margin: 2px 0 18px;
    padding: 0;
    border: 0;
    border-radius: 0;
    background: transparent;
    box-shadow: none;
  }

  .medic-page-head h1 {
    max-width: 900px;
    margin: 0 0 8px;
    color: var(--dp-text);
    font-size: clamp(18px, 4vw, 20px);
    font-weight: 500;
    line-height: 1.17;
    letter-spacing: -.028em;
  }

  .medic-page-head p {
    max-width: 790px;
    margin: 0;
    color: var(--dp-muted);
    font-size: 15px;
    line-height: 1.65;
  }

  /* Compact search panel with an inline search icon and a light premium finish. */
  .medic-search-box {
    margin-bottom: 22px;
    padding: 10px;
    border: 1px solid var(--dp-border);
    border-radius: var(--dp-radius-card);
    background: #ffffff;
    box-shadow: var(--dp-shadow-hover);
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
    color: var(--dp-muted);
    pointer-events: none;
    transform: translateY(-50%);
  }

  .medic-search-row input {
    width: 100%;
    min-height: 42px;
    padding: 9px 12px 9px 39px;
    border: 1px solid var(--dp-border-strong);
    border-radius: 9px;
    outline: none;
    background: #ffffff;
    color: var(--dp-text);
    font: inherit;
    font-size: 14px;
    line-height: 20px;
    transition: border-color .16s ease, box-shadow .16s ease, background-color .16s ease;
  }

  .medic-search-row input::placeholder { color: #8c959f; }
  .medic-search-row input:hover { border-color: #8c959f; }
  .medic-search-row input:focus {
    border-color: var(--dp-primary);
    background: #ffffff;
    box-shadow: 0 0 0 3px rgba(9, 105, 218, .13);
  }

  .medic-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    min-height: 42px;
    padding: 9px 19px;
    border: 1px solid transparent;
    border-radius: 999px;
    background: var(--dp-gradient);
    color: #ffffff;
    cursor: pointer;
    font-size: 14px;
    font-weight: 500;
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

  /* Desktop image cards: picture on top, clear centered name below. */
  .medic-step-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(132px, 1fr));
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
    border: 1px solid var(--dp-border);
    border-radius: var(--dp-radius-card);
    background: var(--dp-surface);
    box-shadow: var(--dp-shadow);
    color: var(--dp-text);
    font-size: 15px;
    font-weight: 500;
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
    background: var(--dp-surface-soft);
  }

  .medic-step-card-media img,
  .medic-area-tag-media img {
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
    color: var(--dp-text);
    text-align: center;
  }

  .medic-step-card:not(.has-image) {
    align-items: center;
    justify-content: center;
    min-height: 68px;
    padding: 12px;
    color: var(--dp-primary);
    text-align: center;
  }
  .medic-step-card:not(.has-image) .medic-step-card-label { padding: 0; color: inherit; }

  .medic-step-card:hover,
  .medic-step-card:focus-visible {
    border-color: var(--dp-primary);
    box-shadow: var(--dp-shadow-hover);
    outline: none;
    transform: translateY(-2px);
  }

  .medic-step-card:hover .medic-step-card-media img,
  .medic-step-card:focus-visible .medic-step-card-media img { transform: scale(1.035); }
  .medic-step-card small { display: none; }

  /* Area filters stay collapsed behind a toggle until opened. */
  .medic-filters {
    margin: 0 0 20px;
  }

  .medic-filters-toggle {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 8px 14px;
    border: 1px solid var(--dp-border-strong);
    border-radius: 999px;
    background: var(--dp-surface);
    color: var(--dp-primary);
    cursor: pointer;
    font-size: 13px;
    font-weight: 600;
    line-height: 20px;
    list-style: none;
    user-select: none;
  }

  .medic-filters-toggle::-webkit-details-marker { display: none; }
  .medic-filters-toggle::marker { content: ''; }

  .medic-filters-toggle svg {
    width: 15px;
    height: 15px;
    flex: 0 0 auto;
  }

  .medic-filters-toggle:hover { border-color: var(--dp-primary); }

  .medic-filters-chevron { transition: transform .16s ease; }
  .medic-filters[open] .medic-filters-chevron { transform: rotate(180deg); }

  .medic-filters-badge {
    padding: 2px 8px;
    border-radius: 999px;
    background: #f5faff;
    color: var(--dp-primary);
    font-size: 12px;
    font-weight: 600;
  }

  /* Thana filter cards follow the same visual language. */
  .medic-area-tags {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(132px, 1fr));
    gap: 12px;
    margin: 14px 0 0;
  }

  .medic-area-tag {
    display: flex;
    flex-direction: column;
    min-width: 0;
    min-height: 54px;
    overflow: hidden;
    padding: 3px;
    border: 1px solid var(--dp-border);
    border-radius: var(--dp-radius-card);
    background: var(--dp-surface);
    box-shadow: var(--dp-shadow);
    color: var(--dp-text);
    font-size: 13px;
    font-weight: 500;
    line-height: 1.35;
    text-align: center;
    text-decoration: none;
    transition: border-color .18s ease, box-shadow .18s ease, transform .18s ease, background-color .18s ease;
  }

  .medic-area-tag-media {
    display: block;
    width: 100%;
    height: 88px;
    overflow: hidden;
    border-radius: 8px;
    background: var(--dp-surface-soft);
  }

  .medic-area-tag-label {
    display: block;
    padding: 8px 5px 7px;
    overflow-wrap: anywhere;
  }

  .medic-area-tag:not(.has-image) {
    align-items: center;
    justify-content: center;
    padding: 10px;
    color: var(--dp-primary);
  }
  .medic-area-tag:not(.has-image) .medic-area-tag-label { padding: 0; }

  .medic-area-tag:hover,
  .medic-area-tag.active {
    border-color: var(--dp-primary);
    background: #f5faff;
    color: var(--dp-primary);
    box-shadow: 0 4px 12px rgba(31, 35, 40, .10);
    transform: translateY(-1px);
  }
  .medic-area-tag:hover .medic-area-tag-media img { transform: scale(1.035); }

  .medic-auto-article {
    margin: 0 0 22px;
    padding: 23px;
    border-radius: var(--dp-radius-card);
  }

  .medic-auto-article::before {
    display: block;
    width: 38px;
    height: 3px;
    margin-bottom: 14px;
    border-radius: 99px;
    background: var(--dp-primary);
    content: '';
  }

  .medic-auto-article h2 {
    margin: 0 0 10px;
    color: var(--dp-text);
    font-size: 20px;
    font-weight: 500;
    line-height: 1.35;
  }

  .medic-auto-article h3 {
    margin: 19px 0 8px;
    color: var(--dp-text);
    font-size: 17px;
    font-weight: 500;
  }

  .medic-auto-article p,
  .medic-auto-article-content {
    color: #39414a;
    font-size: 15px;
    line-height: 1.7;
  }

  .medic-auto-article p { margin: 0; }
  .medic-auto-article ul,
  .medic-auto-article ol,
  .medic-auto-article-content ul,
  .medic-auto-article-content ol { margin: 10px 0 0 22px; padding: 0; }
  .medic-auto-article li,
  .medic-auto-article-content li { margin: 7px 0; }
  .medic-auto-article a { color: var(--dp-primary); font-weight: 500; text-decoration: none; }
  .medic-auto-article a:hover { text-decoration: underline; }
  .medic-auto-article-content { margin-top: 14px; }
  .medic-auto-article-content p { margin: 0 0 13px; }
  /* Keep all public article headings at or below 20px. */
  .medic-auto-article-content h1,
  .medic-auto-article-content h2 {
    color: var(--dp-text);
    font-size: 20px;
    font-weight: 500;
    line-height: 1.35;
  }

  .medic-auto-article-content h3 {
    color: var(--dp-text);
    font-size: 18px;
    font-weight: 500;
    line-height: 1.4;
  }

  .medic-auto-article-content h4,
  .medic-auto-article-content h5,
  .medic-auto-article-content h6 {
    color: var(--dp-text);
    font-size: 16px;
    font-weight: 500;
    line-height: 1.45;
  }

  .medic-doctor-list {
    display: grid;
    gap: 12px;
    margin-top: 22px;
  }

  .medic-list-title {
    position: relative;
    margin: 0;
    padding: 0 0 12px;
    border-bottom: 1px solid var(--dp-border);
    color: var(--dp-text);
    font-size: clamp(18px, 3vw, 20px);
    font-weight: 500;
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
    background: var(--dp-primary);
    content: '';
  }

  /*
   * doctor-card.php now owns its own border, radius, shadow and hover
   * animation, so the shell stays a plain pass-through by default and no
   * longer duplicates a second border/shadow behind the card on hover.
   */
  .medic-doctor-card-shell {
    border-radius: var(--dp-radius-card);
  }

  .medic-doctor-card-shell.is-featured-doctor {
    overflow: hidden;
    border: 1px solid #d4a72c;
    background: #fffdf3;
  }

  .medic-doctor-card-shell.is-featured-doctor .medic-doctor-list-item {
    border: 0;
    border-radius: 0;
  }

  .medic-doctor-card-shell.is-featured-doctor .medic-doctor-list-item:hover {
    box-shadow: none;
    transform: none;
    background-color: transparent;
  }

  .medic-featured-ribbon {
    display: flex;
    align-items: center;
    padding: 8px 12px;
    border-bottom: 1px solid #eac54f;
    background: #fff8c5;
    color: #7d4e00;
    font-size: 12px;
    font-weight: 500;
  }
  .medic-featured-ribbon span::before { content: '★'; margin-right: 6px; }

  .medic-empty-card {
    margin-top: 16px;
    padding: 28px;
    border-radius: var(--dp-radius-card);
    color: var(--dp-muted);
    text-align: center;
  }
  .medic-empty-card h2 { margin: 0 0 8px; color: var(--dp-text); font-size: 20px; font-weight: 500; }
  .medic-empty-card p { max-width: 560px; margin: 0 auto 16px; line-height: 1.65; }

  .medic-pagination {
    display: flex;
    justify-content: center;
    margin: 24px 0 8px;
  }

  .medic-pagination-inner {
    display: inline-flex;
    gap: 3px;
    overflow-x: auto;
    max-width: 100%;
    padding: 4px;
    border: 1px solid var(--dp-border);
    border-radius: 999px;
    background: var(--dp-surface);
    scrollbar-width: none;
  }

  .medic-pagination-inner::-webkit-scrollbar { display: none; }

  .medic-pagination-desktop { display: inline-flex; }
  .medic-pagination-mobile { display: none; }
  .medic-pagination a,
  .medic-pagination span {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 34px;
    height: 34px;
    padding: 0 10px;
    border-radius: 999px;
    background: transparent;
    color: var(--dp-text);
    font-size: 14px;
    font-weight: 500;
    text-decoration: none;
    transition: background-color .16s ease, color .16s ease;
  }
  .medic-pagination a:hover { background: var(--dp-surface-soft); }
  .medic-pagination .active { background: var(--dp-gradient); color: #ffffff; }
  .medic-pagination .disabled { color: #8c959f; }
  .medic-pagination .dots { color: #8c959f; pointer-events: none; }
  .medic-pagination .nav-link { min-width: 78px; }
  .medic-pagination .nav-link-icon { min-width: 34px; padding: 0; flex-shrink: 0; }
  .medic-pagination .nav-link-icon svg { width: 16px; height: 16px; }

  .medic-pagination-summary {
    margin: 9px 0 0;
    color: var(--dp-muted);
    font-size: 13px;
    text-align: center;
  }

  @media (min-width: 1000px) {
    .medic-step-grid { grid-template-columns: repeat(auto-fill, minmax(132px, 1fr)); }
    .medic-area-tags { grid-template-columns: repeat(auto-fill, minmax(132px, 1fr)); }
  }

  @media (max-width: 840px) {
    .medic-doctors-page { padding-top: 18px; }
    .medic-doctors-page .container { width: min(100% - 24px, 1220px); }
    .medic-step-grid { grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 10px; }
    .medic-area-tags { grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 10px; }
    .medic-step-card-media { height: 98px; }
    .medic-area-tag-media { height: 78px; }
  }

  /* Mobile screenshot layout: 2 photo tiles per row with names over the image. */
  @media (max-width: 620px) {
    .medic-doctors-page { padding: 14px 0 36px; }
    .medic-doctors-page .container { width: min(100% - 20px, 1220px); }
    .medic-breadcrumb { margin-bottom: 10px; font-size: 12px; }
    .medic-page-head { margin-bottom: 16px; padding: 0; }
    .medic-page-head h1 { font-size: 20px; }
    .medic-page-head p { font-size: 14px; line-height: 1.58; }
    .medic-search-box { padding: 8px; border-radius: 11px; }
    .medic-search-row { grid-template-columns: 1fr; }
    .medic-search-row input { min-height: 44px; }
    .medic-btn { width: 100%; min-height: 43px; }

    .medic-step-grid,
    .medic-area-tags {
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 10px;
    }

    .medic-step-grid { margin-top: 14px; margin-bottom: 22px; }

    .medic-step-card,
    .medic-area-tag {
      min-height: 0;
      padding: 0;
      border-radius: 12px;
    }

    .medic-step-card.has-image,
    .medic-area-tag.has-image {
      aspect-ratio: 1.26 / 1;
    }

    .medic-step-card.has-image .medic-step-card-media,
    .medic-area-tag.has-image .medic-area-tag-media {
      width: 100%;
      height: 100%;
      border-radius: inherit;
      background: #eaeef2;
    }

    .medic-step-card.has-image .medic-step-card-label,
    .medic-area-tag.has-image .medic-area-tag-label {
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
      font-weight: 500;
      line-height: 1.25;
      text-align: left;
      text-shadow: 0 1px 2px rgba(0, 0, 0, .55);
    }

    .medic-step-card:not(.has-image),
    .medic-area-tag:not(.has-image) {
      min-height: 74px;
      padding: 10px;
      border-radius: 12px;
    }

    .medic-auto-article { padding: 20px 17px; border-radius: 11px; }
    .medic-auto-article h2 { font-size: 20px; }
    .medic-doctor-card-shell { border-radius: 10px; }
    .medic-pagination { justify-content: flex-start; overflow-x: auto; padding-bottom: 6px; }
    .medic-pagination-desktop { display: none; }
    .medic-pagination-mobile { display: inline-flex; margin: 0 auto; }
  }

  @media (max-width: 370px) {
    .medic-step-card.has-image,
    .medic-area-tag.has-image { aspect-ratio: 1.17 / 1; }
    .medic-step-card.has-image .medic-step-card-label,
    .medic-area-tag.has-image .medic-area-tag-label { font-size: 13px; padding: 23px 8px 7px; }
  }

  @media (prefers-reduced-motion: reduce) {
    *, *::before, *::after { scroll-behavior: auto !important; transition-duration: .01ms !important; animation-duration: .01ms !important; }
  }
</style>




<main class="medic-doctors-page">
  <div class="container">

    <nav class="medic-breadcrumb">
      <a href="<?= e(front_url()) ?>"><?= e(__t('home', 'Home')) ?></a>
      <span>/</span>
      <a href="<?= e(front_url('doctors')) ?>"><?= e(__t('doctors', 'Doctors')) ?></a>

      <?php if (!empty($division) && empty($district)): ?>
        <span>/</span>
        <a href="<?= e(dp_doctors_url(['division_slug' => dp_row_slug($division)])) ?>">
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
        <a href="<?= e(dp_doctors_url(!empty($district) ? [
          'district_slug' => dp_row_slug($district),
          'thana_slug' => !empty($thana) ? dp_row_slug($thana) : '',
          'specialty_slug' => dp_row_slug($specialty),
        ] : ['specialty_slug' => dp_row_slug($specialty)])) ?>">
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
          <a class="medic-step-card<?= !empty($item['image']) ? ' has-image' : '' ?>" href="<?= e(dp_doctors_url(['division_slug' => dp_row_slug($item)])) ?>">
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
          <a class="medic-step-card<?= !empty($item['image']) ? ' has-image' : '' ?>" href="<?= e(dp_doctors_url(['district_slug' => dp_row_slug($item)])) ?>">
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
          <?php foreach ($doctors as $doctor): ?>
            <?php
              $show_featured_style = $page_step === 'doctor_list'
                && !empty($district)
                && !empty($specialty)
                && !empty($doctor['context_is_featured']);
            ?>
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
