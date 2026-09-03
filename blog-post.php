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
    include __DIR__ . '/includes/header.php';
    echo '<main style="padding:72px 16px;background:#f6f8fa"><div style="max-width:760px;margin:0 auto;padding:34px;border:1px solid #d0d7de;border-radius:12px;background:#fff;text-align:center"><h1 style="margin:0 0 10px;color:#24292f">' . e($page_title) . '</h1><p style="margin:0 0 20px;color:#57606a">' . e($meta_description) . '</p><a href="' . e(blog_list_url($lang)) . '" style="display:inline-flex;padding:10px 16px;border-radius:7px;background:#2da44e;color:#fff;text-decoration:none;font-weight:700">' . e($lang === 'bn' ? 'ব্লগে ফিরে যান' : 'Back to Blog') . '</a></div></main>';
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
$extra_head_html = '<script type="application/ld+json">' . json_encode(array_filter($schema, static function ($value) { return $value !== null && $value !== ''; }), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>';
$relatedPosts = blog_related_posts($post, 8);


include __DIR__ . '/includes/header.php';
?>
<style>
  :root {
    --bp-title: #0a5d3c;
    --bp-ink: #1f2933;
    --bp-copy: #2f3a44;
    --bp-muted: #74808b;
    --bp-line: #edf0f2;
    --bp-link: #1b4f80;
    --bp-soft: #fafbfb;
  }

  .bp-page,
  .bp-page * {
    box-sizing: border-box;
    font-weight: 500 !important;
  }

  .bp-page {
    padding: 34px 0 70px;
    background: #ffffff;
    color: var(--bp-copy);
  }

  .bp-container {
    width: min(100% - 36px, 1160px);
    margin: 0 auto;
  }

  .bp-crumb {
    display: flex;
    flex-wrap: wrap;
    gap: 7px;
    margin: 0 0 24px;
    color: var(--bp-muted);
    font-size: 12px;
    line-height: 1.45;
  }

  .bp-crumb a {
    color: var(--bp-link);
    text-decoration: none;
  }

  .bp-crumb a:hover {
    text-decoration: underline;
  }

  .bp-layout {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 318px;
    gap: 46px;
    align-items: start;
  }

  .bp-article {
    min-width: 0;
  }

  .bp-featured-image {
    display: block;
    width: 100%;
    max-height: 515px;
    object-fit: cover;
    background: #e9edef;
  }

  .bp-header {
    padding: 24px 0 0;
  }

  .bp-category {
    display: inline-flex;
    margin: 0 0 9px;
    color: var(--bp-link);
    font-size: 11px;
    letter-spacing: .08em;
    text-decoration: none;
    text-transform: uppercase;
  }

  .bp-category:hover {
    text-decoration: underline;
  }

  /* Site-wide rule for this blog page: no text may exceed 20px. */
  .bp-header h1 {
    max-width: 920px;
    margin: 0;
    color: var(--bp-title);
    font-size: 20px;
    letter-spacing: -.015em;
    line-height: 1.3;
  }

  .bp-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 8px 13px;
    margin: 23px 0 0;
    padding: 16px 0;
    border-top: 1px solid var(--bp-line);
    border-bottom: 1px solid var(--bp-line);
    color: #52606d;
    font-size: 13px;
    line-height: 1.4;
  }

  .bp-meta span {
    display: inline-flex;
    align-items: center;
    gap: 7px;
  }

  .bp-meta .bp-meta-icon {
    width: 16px;
    height: 16px;
    color: #54738f;
    fill: none;
    stroke: currentColor;
    stroke-linecap: round;
    stroke-linejoin: round;
    stroke-width: 1.6;
  }

  .bp-meta-separator {
    color: #c1c8ce;
  }

  .bp-content {
    padding: 25px 0 0;
    color: var(--bp-copy);
    font-size: 16px;
    line-height: 1.82;
    overflow-wrap: anywhere;
  }

  .bp-content > *:first-child {
    margin-top: 0;
  }

  .bp-content p,
  .bp-content li,
  .bp-content td,
  .bp-content th,
  .bp-content figcaption,
  .bp-content span,
  .bp-content strong,
  .bp-content b,
  .bp-content em,
  .bp-content i,
  .bp-content u,
  .bp-content a {
    font-weight: 500 !important;
  }

  .bp-content p {
    margin: 0 0 20px;
  }

  .bp-content h1,
  .bp-content h2,
  .bp-content h3,
  .bp-content h4,
  .bp-content h5,
  .bp-content h6 {
    margin: 27px 0 11px;
    color: #0c4778;
    font-size: 20px !important;
    line-height: 1.4;
  }

  .bp-content ul,
  .bp-content ol {
    margin: 0 0 20px;
    padding-left: 25px;
  }

  .bp-content li {
    margin: 8px 0;
  }

  .bp-content a {
    color: var(--bp-link);
    text-decoration: none;
  }

  .bp-content a:hover {
    text-decoration: underline;
  }

  .bp-content blockquote {
    margin: 28px 0;
    padding: 15px 20px;
    border-left: 4px solid var(--bp-title);
    background: var(--bp-soft);
    color: #52606d;
    font-size: 16px;
  }

  .bp-content img {
    display: block;
    max-width: 100%;
    height: auto;
    margin: 26px auto;
  }

  /*
   * Summernote can align an image left or right with an inline float style.
   * Keep clear breathing room between that image and the text flowing beside it.
   */
  .bp-content img[style*="float:left"],
  .bp-content img[style*="float: left"] {
    margin: 8px 24px 18px 0 !important;
  }

  .bp-content img[style*="float:right"],
  .bp-content img[style*="float: right"] {
    margin: 8px 0 18px 24px !important;
  }

  .bp-content::after {
    content: "";
    display: block;
    clear: both;
  }

  .bp-content figure {
    margin: 26px 0;
  }

  .bp-content figcaption {
    margin-top: 9px;
    color: var(--bp-muted);
    font-size: 12px;
    line-height: 1.55;
    text-align: center;
  }

  .bp-content table {
    width: 100%;
    margin: 24px 0;
    border-collapse: collapse;
    font-size: 14px;
  }

  .bp-content th,
  .bp-content td {
    padding: 10px 12px;
    border: 1px solid #dce2e6;
    text-align: left;
  }

  .bp-content th {
    background: #f7f9fa;
  }

  .bp-share {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
    margin-top: 34px;
    padding: 17px 0;
    border-top: 1px solid var(--bp-line);
    border-bottom: 1px solid var(--bp-line);
  }

  .bp-share strong {
    color: #334155;
    font-size: 13px;
  }

  .bp-share a {
    display: inline-flex;
    align-items: center;
    min-height: 32px;
    padding: 7px 11px;
    border: 1px solid #d5dde3;
    color: #4267a7;
    background: #ffffff;
    font-size: 12px;
    text-decoration: none;
  }

  .bp-share a:hover {
    border-color: #8ea6c6;
    background: #f7faff;
  }

  .bp-side {
    border-left: 1px solid var(--bp-line);
    padding-left: 28px;
  }

  .bp-side-heading {
    margin: 0 0 13px;
    padding: 0 0 10px;
    border-bottom: 1px solid var(--bp-line);
    color: #253542;
    font-size: 15px;
    letter-spacing: .01em;
  }

  .bp-recent-list {
    display: grid;
    gap: 0;
  }

  .bp-recent-item {
    display: grid;
    grid-template-columns: 68px minmax(0, 1fr);
    gap: 12px;
    padding: 14px 0;
    border-bottom: 1px solid var(--bp-line);
    color: inherit;
    text-decoration: none;
  }

  .bp-recent-thumb,
  .bp-recent-placeholder {
    display: block;
    width: 68px;
    height: 68px;
    object-fit: cover;
    background: #e9edef;
  }

  .bp-recent-placeholder {
    display: grid;
    place-items: center;
    color: #7c8790;
    font-size: 20px;
  }

  .bp-recent-copy {
    min-width: 0;
  }

  .bp-recent-title {
    display: -webkit-box;
    overflow: hidden;
    margin: 1px 0 7px;
    color: #194a7b;
    font-size: 14px;
    line-height: 1.42;
    text-decoration: none;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 3;
  }

  .bp-recent-item:hover .bp-recent-title {
    color: #0a5d3c;
    text-decoration: underline;
  }

  .bp-recent-meta {
    color: #a0a8af;
    font-size: 10px;
    letter-spacing: .04em;
    line-height: 1.4;
    text-transform: uppercase;
  }

  .bp-side-note {
    margin-top: 24px;
    padding: 16px 0 0;
    border-top: 1px solid var(--bp-line);
  }

  .bp-side-note h2 {
    margin: 0 0 8px;
    color: #253542;
    font-size: 15px;
  }

  .bp-side-note p {
    margin: 0;
    color: #71808c;
    font-size: 12px;
    line-height: 1.65;
  }

  .bp-back {
    display: inline-flex;
    margin-top: 12px;
    color: #194a7b;
    font-size: 12px;
    text-decoration: none;
  }

  .bp-back:hover {
    color: var(--bp-title);
    text-decoration: underline;
  }

  .bp-related {
    margin-top: 48px;
    padding-top: 22px;
    border-top: 1px solid var(--bp-line);
  }

  .bp-related h2 {
    margin: 0 0 15px;
    color: #253542;
    font-size: 20px;
  }

  .bp-related-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 18px;
  }

  .bp-related-card {
    color: inherit;
    text-decoration: none;
  }

  .bp-related-card img,
  .bp-related-card .bp-related-placeholder {
    display: block;
    width: 100%;
    aspect-ratio: 1.65 / 1;
    object-fit: cover;
    background: #e9edef;
  }

  .bp-related-placeholder {
    display: grid;
    place-items: center;
    color: #7c8790;
    font-size: 20px;
  }

  .bp-related-card span {
    display: block;
    margin-top: 10px;
    color: #194a7b;
    font-size: 14px;
    line-height: 1.45;
  }

  .bp-related-card:hover span {
    color: var(--bp-title);
    text-decoration: underline;
  }

  @media (max-width: 960px) {
    .bp-layout {
      grid-template-columns: 1fr;
      gap: 35px;
    }

    .bp-side {
      border-top: 1px solid var(--bp-line);
      border-left: 0;
      padding-top: 24px;
      padding-left: 0;
    }

    .bp-recent-list {
      grid-template-columns: repeat(2, minmax(0, 1fr));
      column-gap: 24px;
    }
  }

  @media (max-width: 650px) {
    .bp-page {
      padding: 20px 0 45px;
    }

    .bp-container {
      width: min(100% - 24px, 1160px);
    }

    .bp-crumb {
      margin-bottom: 17px;
      font-size: 11px;
    }

    .bp-header {
      padding-top: 19px;
    }

    .bp-header h1,
    .bp-content h1,
    .bp-content h2,
    .bp-content h3,
    .bp-content h4,
    .bp-content h5,
    .bp-content h6,
    .bp-related h2 {
      font-size: 20px !important;
      line-height: 1.35;
    }

    .bp-meta {
      gap: 7px 10px;
      margin-top: 18px;
      padding: 12px 0;
      font-size: 12px;
    }

    .bp-meta-separator {
      display: none;
    }

    .bp-content {
      padding-top: 21px;
      font-size: 15px;
      line-height: 1.76;
    }

    .bp-content img[style*="float:left"],
    .bp-content img[style*="float: left"] {
      margin-right: 16px !important;
    }

    .bp-content img[style*="float:right"],
    .bp-content img[style*="float: right"] {
      margin-left: 16px !important;
    }

    .bp-recent-list,
    .bp-related-grid {
      grid-template-columns: 1fr;
    }
  }
</style>

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
          <p style="margin:0;color:#71808c;font-size:13px;line-height:1.6;"><?= e($lang === 'bn' ? 'এখনও কোনো সম্পর্কিত পোস্ট পাওয়া যায়নি।' : 'No related posts are available yet.') ?></p>
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
