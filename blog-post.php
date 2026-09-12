<?php
require_once __DIR__ . '/includes/blog-functions.php';

$lang = blog_lang();
$slug = blog_normalize_slug((string) ($_GET['slug'] ?? ''));
$post = $slug !== '' ? blog_find_post_by_slug($slug, true) : null;

if ($post === null) {
    http_response_code(404);
    $page_title = $lang === 'bn' ? 'পোস্টটি পাওয়া যায়নি' : 'Post not found';
    $meta_description = $lang === 'bn' ? 'আপনি যে ব্লগ পোস্টটি খুঁজছেন সেটি পাওয়া যায়নি।' : 'The blog post you are looking for was not found.';
    $robots_meta = 'noindex,follow';
    $extra_head_html = '<link rel="stylesheet" href="' . e(site_url('assets/css/blog-post.css')) . '?v=' . e(front_asset_version()) . '">';
    include __DIR__ . '/includes/header.php';
    echo '<main class="bp-notfound-main"><div class="bp-notfound-card"><h1 class="bp-notfound-title">' . e($page_title) . '</h1><p class="bp-notfound-text">' . e($meta_description) . '</p><a href="' . e(blog_list_url($lang)) . '" class="bp-notfound-link">' . e($lang === 'bn' ? 'ব্লগে ফিরে যান' : 'Back to Blog') . '</a></div></main>';
    include __DIR__ . '/includes/footer.php';
    exit;
}

blog_increment_views((int) $post['id']);

$siteName = function_exists('front_site_name') ? front_site_name() : (defined('APP_NAME') ? APP_NAME : 'Medic');
$title = blog_text($post, 'title', $lang);
$excerpt = blog_text($post, 'excerpt', $lang);
$content = blog_sanitize_html(blog_text($post, 'content', $lang));
if ($excerpt === '') $excerpt = blog_excerpt_from_html($content, 180);
$categoryName = blog_text($post, 'category_name', $lang);
$category = trim((string) ($post['category_slug'] ?? '')) !== '' ? ['slug' => $post['category_slug'], 'name_en' => $post['category_name_en'] ?? '', 'name_bn' => $post['category_name_bn'] ?? ''] : null;
$image = blog_image_url((string) $post['featured_image']);
$imageAlt = blog_text($post, 'image_alt', $lang, $title);
$canonical_url = blog_url($post, $lang);
$page_title = blog_text($post, 'meta_title', $lang, $title) . ' | ' . $siteName;
$meta_description = blog_text($post, 'meta_description', $lang, $excerpt);
$meta_keywords = blog_text($post, 'meta_keywords', $lang, 'health blog, medical article, ' . $siteName);
$meta_robots = 'index,follow';
$robots_meta = $meta_robots;
$og_title = $page_title;
$og_description = $meta_description;
$og_type = 'article';
$og_image = $image;
$og_image_alt = $imageAlt;
$publishedAt = trim((string) ($post['published_at'] ?? $post['created_at'] ?? ''));
$modifiedAt = trim((string) ($post['updated_at'] ?? $publishedAt));
$schema = [
    '@context' => 'https://schema.org',
    '@type' => 'Article',
    'headline' => $title,
    'description' => $meta_description,
    'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => $canonical_url],
    'datePublished' => $publishedAt !== '' ? date(DATE_ATOM, strtotime($publishedAt)) : null,
    'dateModified' => $modifiedAt !== '' ? date(DATE_ATOM, strtotime($modifiedAt)) : null,
    'author' => ['@type' => 'Person', 'name' => trim((string) ($post['author_name'] ?? '')) ?: $siteName],
    'publisher' => ['@type' => 'Organization', 'name' => $siteName],
];
if ($image !== '') $schema['image'] = [$image];
$extra_head_html = '<link rel="stylesheet" href="' . e(site_url('assets/css/blog-post.css')) . '?v=' . e(front_asset_version()) . '">'
    . '<script type="application/ld+json">' . json_encode(array_filter($schema, static function ($value) { return $value !== null && $value !== ''; }), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>';
$relatedPosts = blog_related_posts($post, 8);


include __DIR__ . '/includes/header.php';
?>

<main class="bp-page">
  <div class="bp-container">
    <nav class="bp-crumb">
      <a href="<?= e(front_url()) ?>"><?= e($lang === 'bn' ? 'হোম' : 'Home') ?></a>
      <span>/</span>
      <a href="<?= e(blog_list_url($lang)) ?>"><?= e($lang === 'bn' ? 'ব্লগ' : 'Blog') ?></a>
      <?php if ($category): ?>
        <span>/</span>
        <a href="<?= e(blog_category_url($category, $lang)) ?>"><?= e($categoryName) ?></a>
      <?php endif; ?>
    </nav>

    <div class="bp-layout">
      <article class="bp-article">
        <?php if ($image !== ''): ?>
          <img class="bp-featured-image" src="<?= e($image) ?>" alt="<?= e($imageAlt) ?>">
        <?php endif; ?>

        <header class="bp-header">
          <?php if ($category): ?>
            <a class="bp-category" href="<?= e(blog_category_url($category, $lang)) ?>"><?= e($categoryName) ?></a>
          <?php endif; ?>

          <h1><?= e($title) ?></h1>

          <div class="bp-meta">
            <?php if (trim((string) $post['author_name']) !== ''): ?>
              <span>
                <svg class="bp-meta-icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="4"></circle><path d="M4 20c.8-4.1 3.5-6 8-6s7.2 1.9 8 6"></path></svg>
                <?= e($post['author_name']) ?>
              </span>
              <span class="bp-meta-separator">•</span>
            <?php endif; ?>

            <?php if ($publishedAt !== ''): ?>
              <span>
                <svg class="bp-meta-icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="8"></circle><path d="M12 7v5l3 2"></path></svg>
                <?= e(date($lang === 'bn' ? 'd M, Y' : 'F j, Y', strtotime($publishedAt))) ?>
              </span>
              <span class="bp-meta-separator">•</span>
            <?php endif; ?>

            <?php if ($category): ?>
              <span>
                <svg class="bp-meta-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6.5h6l1.8 2H21v10H3z"></path></svg>
                <?= e($categoryName) ?>
              </span>
            <?php endif; ?>
          </div>
        </header>

        <div class="bp-content"><?= $content ?></div>

        <div class="bp-share">
          <strong><?= e($lang === 'bn' ? 'এই পোস্টটি শেয়ার করুন' : 'Share this post') ?></strong>
          <a target="_blank" rel="noopener noreferrer" href="https://www.facebook.com/sharer/sharer.php?u=<?= rawurlencode($canonical_url) ?>">Facebook</a>
        </div>

        <?php if ($relatedPosts): ?>
          <section class="bp-related">
            <h2><?= e($lang === 'bn' ? 'সম্পর্কিত পোস্ট' : 'Related posts') ?></h2>
            <div class="bp-related-grid">
              <?php foreach (array_slice($relatedPosts, 0, 3) as $item): ?>
                <?php $relatedTitle = blog_text($item, 'title', $lang); ?>
                <a class="bp-related-card" href="<?= e(blog_url($item, $lang)) ?>">
                  <?php if (trim((string) $item['featured_image']) !== ''): ?>
                    <img src="<?= e(blog_image_url($item['featured_image'])) ?>" alt="<?= e(blog_text($item, 'image_alt', $lang, $relatedTitle)) ?>" loading="lazy">
                  <?php else: ?>
                    <span class="bp-related-placeholder" aria-hidden="true">+</span>
                  <?php endif; ?>
                  <span><?= e($relatedTitle) ?></span>
                </a>
              <?php endforeach; ?>
            </div>
          </section>
        <?php endif; ?>
      </article>

      <aside class="bp-side">
        <h2 class="bp-side-heading"><?= e($lang === 'bn' ? 'সাম্প্রতিক পোস্ট' : 'Recent posts') ?></h2>

        <?php if ($relatedPosts): ?>
          <div class="bp-recent-list">
            <?php foreach ($relatedPosts as $item): ?>
              <?php
                $recentTitle = blog_text($item, 'title', $lang);
                $recentDate = trim((string) ($item['published_at'] ?? ''));
              ?>
              <a class="bp-recent-item" href="<?= e(blog_url($item, $lang)) ?>">
                <?php if (trim((string) $item['featured_image']) !== ''): ?>
                  <img class="bp-recent-thumb" src="<?= e(blog_image_url($item['featured_image'])) ?>" alt="<?= e(blog_text($item, 'image_alt', $lang, $recentTitle)) ?>" loading="lazy">
                <?php else: ?>
                  <span class="bp-recent-placeholder" aria-hidden="true">+</span>
                <?php endif; ?>

                <span class="bp-recent-copy">
                  <span class="bp-recent-title"><?= e($recentTitle) ?></span>
                  <?php if ($recentDate !== ''): ?>
                    <span class="bp-recent-meta"><?= e(date($lang === 'bn' ? 'd M, Y' : 'F j, Y', strtotime($recentDate))) ?></span>
                  <?php endif; ?>
                </span>
              </a>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <p class="bp-side-empty"><?= e($lang === 'bn' ? 'এখনও কোনো সম্পর্কিত পোস্ট পাওয়া যায়নি।' : 'No related posts are available yet.') ?></p>
        <?php endif; ?>

        <section class="bp-side-note">
          <h2><?= e($lang === 'bn' ? 'স্বাস্থ্য তথ্য' : 'Health information') ?></h2>
          <p><?= e($lang === 'bn' ? 'এই লেখা সাধারণ স্বাস্থ্য তথ্যের জন্য। ব্যক্তিগত চিকিৎসা পরামর্শের জন্য একজন যোগ্য চিকিৎসকের সঙ্গে কথা বলুন।' : 'This article provides general health information. Speak with a qualified clinician for personal medical advice.') ?></p>
          <a class="bp-back" href="<?= e(blog_list_url($lang)) ?>">← <?= e($lang === 'bn' ? 'সব পোস্ট' : 'All posts') ?></a>
        </section>
      </aside>
    </div>
  </div>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
