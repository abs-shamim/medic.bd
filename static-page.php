<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/static-page-helper.php';

$slug = strtolower(trim((string) ($_GET['slug'] ?? ($GLOBALS['front_route'] ?? ''))));
$page = medic_static_page_get_by_slug($slug, true);

if ($page === null) {
    http_response_code(404);
    exit('Page not found.');
}

$siteName = medic_static_page_site_name();
$title = medic_static_page_replace_tokens(medic_static_page_localized_value($page, 'title'));
$kicker = medic_static_page_replace_tokens(medic_static_page_localized_value($page, 'kicker'));
$intro = medic_static_page_replace_tokens(medic_static_page_localized_value($page, 'intro'));
$content = medic_static_page_localized_value($page, 'content');
$metaTitle = medic_static_page_replace_tokens(medic_static_page_localized_value($page, 'meta_title'));
$metaDescription = medic_static_page_replace_tokens(medic_static_page_localized_value($page, 'meta_description'));

$page_title = trim($metaTitle) !== '' ? $metaTitle : trim($title . ' | ' . $siteName);
$meta_description = trim($metaDescription) !== ''
    ? $metaDescription
    : trim($intro);
$meta_keywords = $meta_keywords ?? '';
$meta_robots = 'index,follow';
$canonical_url = medic_static_page_url($slug);

include __DIR__ . '/includes/header.php';
?>
<link rel="stylesheet" href="<?= e(site_url('assets/css/legal-pages.css')) ?>">

<main class="medic-static-page">
  <div class="container medic-static-container">
    <nav class="medic-static-breadcrumb" aria-label="Breadcrumb">
      <a href="<?= e(medic_static_page_url()) ?>"><?= e(medic_static_page_text('Home', 'হোম')) ?></a>
      <span>/</span>
      <span><?= e($title) ?></span>
    </nav>

    <section class="medic-static-hero">
      <?php if ($kicker !== ''): ?>
        <div class="medic-static-kicker"><?= e($kicker) ?></div>
      <?php endif; ?>
      <h1><?= e($title) ?></h1>
      <?php if ($intro !== ''): ?>
        <p><?= e($intro) ?></p>
      <?php endif; ?>
      <?php if (!empty($page['updated_at'])): ?>
        <div class="medic-static-meta">
          <?= e(medic_static_page_text('Last updated: ', 'সর্বশেষ হালনাগাদ: ')) ?><?= e((string) $page['updated_at']) ?>
        </div>
      <?php endif; ?>
    </section>

    <?php if (trim($content) !== ''): ?>
      <?= medic_static_page_render_content($content) ?>
    <?php else: ?>
      <section class="medic-static-card">
        <p><?= e(medic_static_page_text('Page content will be added soon.', 'পেজের কনটেন্ট শিগগিরই যোগ করা হবে।')) ?></p>
      </section>
    <?php endif; ?>

    <?php if ($slug === 'support' && is_file(__DIR__ . '/includes/support-form.php')): ?>
      <?php include __DIR__ . '/includes/support-form.php'; ?>
    <?php endif; ?>
  </div>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
