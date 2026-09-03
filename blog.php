<?php
require_once __DIR__ . '/includes/blog-functions.php';

$lang = blog_lang();
$categorySlug = blog_normalize_slug((string) ($_GET['category'] ?? ''));
$search = blog_clean_text($_GET['q'] ?? '', 120);
$page = max(1, (int) ($_GET['page'] ?? 1));
$category = null;

if ($categorySlug !== '') {
    $category = blog_category_by_slug($categorySlug, true);
    if ($category === null) {
        http_response_code(404);
        $page_title = $lang === 'bn' ? 'ক্যাটাগরি পাওয়া যায়নি' : 'Category not found';
        $meta_description = $lang === 'bn' ? 'আপনি যে ব্লগ ক্যাটাগরিটি খুঁজছেন সেটি পাওয়া যায়নি।' : 'The blog category you are looking for was not found.';
        $robots_meta = 'noindex,follow';
        include __DIR__ . '/includes/header.php';
        echo '<main style="padding:72px 16px;background:#f6f8fa"><div style="max-width:760px;margin:0 auto;padding:34px;border:1px solid #d0d7de;border-radius:12px;background:#fff;text-align:center"><h1 style="margin:0 0 10px;color:#24292f">' . e($page_title) . '</h1><p style="margin:0 0 20px;color:#57606a">' . e($meta_description) . '</p><a href="' . e(blog_list_url($lang)) . '" style="display:inline-flex;padding:10px 16px;border-radius:7px;background:#2da44e;color:#fff;text-decoration:none;font-weight:700">' . e($lang === 'bn' ? 'ব্লগে ফিরে যান' : 'Back to Blog') . '</a></div></main>';
        include __DIR__ . '/includes/footer.php';
        exit;
    }
}

$list = blog_list_posts([
    'public' => true,
    'category_id' => (int) ($category['id'] ?? 0),
    'search' => $search,
    'page' => $page,
    'per_page' => 9,
]);
$categories = blog_categories(true);
$siteName = function_exists('front_site_name') ? front_site_name() : (defined('APP_NAME') ? APP_NAME : 'Medic');
$blogLabel = $lang === 'bn' ? 'স্বাস্থ্য ব্লগ' : 'Health Blog';
$categoryName = $category ? blog_text($category, 'name', $lang) : '';
$categoryDescription = $category ? blog_text($category, 'description', $lang) : '';
$defaultDescription = $lang === 'bn'
    ? 'স্বাস্থ্য, চিকিৎসা, ডাক্তার এবং সুস্থ জীবনযাপন নিয়ে নির্ভরযোগ্য লেখা পড়ুন।'
    : 'Read trusted articles about health, doctors, treatment and healthier living.';

$page_title = $category
    ? (blog_text($category, 'meta_title', $lang, $categoryName) . ' | ' . $siteName)
    : ($blogLabel . ' | ' . $siteName);
$meta_description = $category
    ? blog_text($category, 'meta_description', $lang, $categoryDescription !== '' ? $categoryDescription : $defaultDescription)
    : $defaultDescription;
$meta_keywords = $lang === 'bn' ? 'স্বাস্থ্য ব্লগ, চিকিৎসা পরামর্শ, ডাক্তার, হাসপাতাল' : 'health blog, medical articles, doctors, hospitals, health tips';
$meta_robots = $search !== '' || $page > 1 ? 'noindex,follow' : 'index,follow';
$robots_meta = $meta_robots;
$canonical_url = $category ? blog_category_url($category, $lang) : blog_list_url($lang);
$og_title = $page_title;
$og_description = $meta_description;
$og_type = 'website';
$extra_head_html = '<script type="application/ld+json">' . json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'CollectionPage',
    'name' => $category ? $categoryName : $blogLabel,
    'url' => $canonical_url,
    'description' => $meta_description,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>';

$baseUrl = $category ? blog_category_url($category, $lang) : blog_list_url($lang);
$makeUrl = static function (array $extra = []) use ($category, $lang, $search, $baseUrl): string {
    $params = array_merge($search !== '' ? ['q' => $search] : [], $extra);
    if (isset($params['page']) && (int) $params['page'] <= 1) unset($params['page']);
    return $baseUrl . ($params ? '?' . http_build_query($params) : '');
};


include __DIR__ . '/includes/header.php';
?>
<style>
  :root {
    --blog-ink: #22272b;
    --blog-muted: #8a949e;
    --blog-line: #e7eaed;
    --blog-soft: #f8f9fa;
    --blog-link: #30363d;
    --blog-accent: #2da44e;
  }

  .blog-page,
  .blog-page * {
    box-sizing: border-box;
  }

  .blog-page {
    min-height: 58vh;
    padding: 38px 0 56px;
    background: #ffffff;
    color: var(--blog-ink);
  }

  .blog-container {
    width: min(100% - 32px, 1040px);
    margin: 0 auto;
  }

  .blog-crumb {
    display: flex;
    flex-wrap: wrap;
    gap: 7px;
    margin: 0 0 28px;
    color: var(--blog-muted);
    font-size: 12px;
    line-height: 1.4;
  }

  .blog-crumb a {
    color: var(--blog-muted);
    text-decoration: none;
  }

  .blog-crumb a:hover {
    color: var(--blog-ink);
    text-decoration: underline;
  }

  .blog-heading {
    display: flex;
    align-items: end;
    justify-content: space-between;
    gap: 22px;
    margin: 0 0 18px;
    padding: 0 0 16px;
    border-bottom: 1px solid var(--blog-line);
  }

  .blog-heading-copy {
    min-width: 0;
  }

  .blog-heading-kicker {
    display: block;
    margin: 0 0 7px;
    color: var(--blog-muted);
    font-size: 11px;
    font-weight: 700;
    letter-spacing: .08em;
    text-transform: uppercase;
  }

  .blog-heading h1 {
    margin: 0;
    color: var(--blog-ink);
    font-size: clamp(24px, 3vw, 31px);
    font-weight: 800;
    letter-spacing: -.035em;
    line-height: 1.15;
  }

  .blog-heading p {
    max-width: 680px;
    margin: 8px 0 0;
    color: #656d76;
    font-size: 13px;
    line-height: 1.65;
  }

  .blog-tools {
    flex: 0 0 auto;
    position: relative;
  }

  .blog-tools > summary {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 34px;
    padding: 7px 11px;
    border: 1px solid #d0d7de;
    border-radius: 6px;
    color: #57606a;
    background: #ffffff;
    cursor: pointer;
    font-size: 12px;
    font-weight: 700;
    list-style: none;
    user-select: none;
  }

  .blog-tools > summary::-webkit-details-marker {
    display: none;
  }

  .blog-tools[open] > summary {
    border-color: #8c959f;
    color: #24292f;
    background: var(--blog-soft);
  }

  .blog-tools-panel {
    position: absolute;
    top: calc(100% + 8px);
    right: 0;
    z-index: 10;
    width: min(92vw, 390px);
    padding: 11px;
    border: 1px solid #d0d7de;
    border-radius: 8px;
    background: #ffffff;
    box-shadow: 0 10px 26px rgba(31, 35, 40, .14);
  }

  .blog-tools-form {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 122px auto;
    gap: 7px;
  }

  .blog-tools-form input,
  .blog-tools-form select,
  .blog-tools-form button {
    min-height: 36px;
    border-radius: 6px;
    font: inherit;
    font-size: 12px;
  }

  .blog-tools-form input,
  .blog-tools-form select {
    width: 100%;
    padding: 7px 9px;
    border: 1px solid #d0d7de;
    color: #24292f;
    background: #ffffff;
  }

  .blog-tools-form button {
    padding: 7px 11px;
    border: 1px solid rgba(31, 35, 40, .15);
    color: #ffffff;
    background: var(--blog-accent);
    font-weight: 700;
    cursor: pointer;
  }

  .blog-list {
    border-top: 1px solid var(--blog-line);
  }

  .blog-list-row {
    display: grid;
    grid-template-columns: 126px minmax(0, 1fr) 104px;
    align-items: center;
    gap: 18px;
    min-height: 59px;
    padding: 10px 0;
    border-bottom: 1px solid var(--blog-line);
    color: inherit;
    text-decoration: none;
    transition: background-color .16s ease, padding .16s ease;
  }

  .blog-list-row:hover {
    padding-right: 10px;
    padding-left: 10px;
    background: #fafbfc;
  }

  .blog-list-date {
    color: var(--blog-muted);
    font-size: 10px;
    font-weight: 500;
    line-height: 1.4;
    white-space: nowrap;
  }

  .blog-list-main {
    min-width: 0;
  }

  .blog-list-category {
    display: block;
    margin: 0 0 3px;
    color: #8a949e;
    font-size: 10px;
    font-weight: 700;
    letter-spacing: .06em;
    text-transform: uppercase;
  }

  .blog-list-title {
    display: block;
    overflow: hidden;
    color: var(--blog-link);
    font-size: clamp(15px, 1.85vw, 18px);
    font-weight: 800;
    letter-spacing: -.027em;
    line-height: 1.27;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  .blog-list-row:hover .blog-list-title {
    color: #0969da;
  }

  .blog-list-read {
    justify-self: end;
    display: inline-flex;
    align-items: center;
    justify-content: flex-end;
    gap: 5px;
    color: #57606a;
    font-size: 10px;
    font-weight: 700;
    line-height: 1.3;
    white-space: nowrap;
  }

  .blog-list-row:hover .blog-list-read {
    color: #0969da;
  }

  .blog-empty {
    padding: 50px 18px;
    border-bottom: 1px solid var(--blog-line);
    color: #656d76;
    text-align: center;
  }

  .blog-empty h2 {
    margin: 0 0 8px;
    color: var(--blog-ink);
    font-size: 20px;
  }

  .blog-empty p {
    margin: 0;
    font-size: 13px;
    line-height: 1.6;
  }

  .blog-pagination {
    display: flex;
    justify-content: center;
    gap: 6px;
    margin: 28px 0 0;
  }

  .blog-pagination a,
  .blog-pagination span {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 35px;
    height: 35px;
    padding: 0 10px;
    border: 1px solid #d0d7de;
    border-radius: 6px;
    color: #0969da;
    background: #ffffff;
    font-size: 12px;
    font-weight: 700;
    text-decoration: none;
  }

  .blog-pagination span {
    border-color: #0969da;
    color: #ffffff;
    background: #0969da;
  }

  @media (max-width: 720px) {
    .blog-page {
      padding: 22px 0 40px;
    }

    .blog-container {
      width: min(100% - 24px, 1040px);
    }

    .blog-crumb {
      margin-bottom: 19px;
    }

    .blog-heading {
      align-items: flex-start;
      flex-direction: column;
      gap: 13px;
      margin-bottom: 13px;
      padding-bottom: 13px;
    }

    .blog-tools,
    .blog-tools > summary {
      width: 100%;
    }

    .blog-tools-panel {
      right: auto;
      left: 0;
      width: 100%;
    }

    .blog-tools-form {
      grid-template-columns: 1fr;
    }

    .blog-list-row {
      grid-template-columns: 1fr auto;
      gap: 5px 12px;
      min-height: 0;
      padding: 14px 0;
    }

    .blog-list-row:hover {
      padding-right: 6px;
      padding-left: 6px;
    }

    .blog-list-date {
      grid-column: 1 / -1;
      font-size: 10px;
    }

    .blog-list-title {
      display: -webkit-box;
      overflow: hidden;
      white-space: normal;
      -webkit-box-orient: vertical;
      -webkit-line-clamp: 2;
    }

    .blog-list-read {
      align-self: end;
      font-size: 10px;
    }
  }
</style>

<main class="blog-page">
  <div class="blog-container">
    <nav class="blog-crumb">
      <a href="<?= e(front_url()) ?>"><?= e($lang === 'bn' ? 'হোম' : 'Home') ?></a>
      <span>/</span>
      <a href="<?= e(blog_list_url($lang)) ?>"><?= e($lang === 'bn' ? 'ব্লগ' : 'Blog') ?></a>
      <?php if ($category): ?>
        <span>/</span>
        <span><?= e($categoryName) ?></span>
      <?php endif; ?>
    </nav>

    <header class="blog-heading">
      <div class="blog-heading-copy">
        <span class="blog-heading-kicker"><?= e($category ? ($lang === 'bn' ? 'ক্যাটাগরি' : 'Category') : ($lang === 'bn' ? 'স্বাস্থ্য ব্লগ' : 'Health Blog')) ?></span>
        <h1><?= e($category ? $categoryName : $blogLabel) ?></h1>
        <?php if (($categoryDescription !== '' && $category) || (!$category && $defaultDescription !== '')): ?>
          <p><?= e($category && $categoryDescription !== '' ? $categoryDescription : $defaultDescription) ?></p>
        <?php endif; ?>
      </div>

      <details class="blog-tools">
        <summary><?= e($lang === 'bn' ? 'খুঁজুন ও ফিল্টার' : 'Search & filter') ?></summary>
        <div class="blog-tools-panel">
          <form class="blog-tools-form" method="get" action="<?= e($baseUrl) ?>">
            <input
              type="search"
              name="q"
              value="<?= e($search) ?>"
              placeholder="<?= e($lang === 'bn' ? 'ব্লগ পোস্ট খুঁজুন...' : 'Search blog posts...') ?>"
            >
            <select aria-label="<?= e($lang === 'bn' ? 'ক্যাটাগরি নির্বাচন করুন' : 'Choose category') ?>" onchange="if(this.value){window.location.href=this.value;}">
              <option value="<?= e(blog_list_url($lang)) ?>"><?= e($lang === 'bn' ? 'সব ক্যাটাগরি' : 'All categories') ?></option>
              <?php foreach ($categories as $item): ?>
                <option value="<?= e(blog_category_url($item, $lang)) ?>" <?= $category && (int) $category['id'] === (int) $item['id'] ? 'selected' : '' ?>>
                  <?= e(blog_text($item, 'name', $lang)) ?>
                </option>
              <?php endforeach; ?>
            </select>
            <button type="submit"><?= e($lang === 'bn' ? 'খুঁজুন' : 'Search') ?></button>
          </form>
        </div>
      </details>
    </header>

    <?php if ($list['items']): ?>
      <section class="blog-list" aria-label="<?= e($lang === 'bn' ? 'ব্লগ পোস্ট তালিকা' : 'Blog post list') ?>">
        <?php foreach ($list['items'] as $item): ?>
          <?php
            $itemTitle = blog_text($item, 'title', $lang);
            $itemCategory = blog_text($item, 'category_name', $lang);
            $publishedAt = trim((string) ($item['published_at'] ?? ''));
            $publishedDate = $publishedAt !== ''
                ? date($lang === 'bn' ? 'd M, Y' : 'F j, Y', strtotime($publishedAt))
                : '';
          ?>
          <a class="blog-list-row" href="<?= e(blog_url($item, $lang)) ?>">
            <time class="blog-list-date" datetime="<?= e($publishedAt) ?>"><?= e($publishedDate) ?></time>
            <span class="blog-list-main">
              <?php if ($itemCategory !== ''): ?>
                <span class="blog-list-category"><?= e($itemCategory) ?></span>
              <?php endif; ?>
              <span class="blog-list-title"><?= e($itemTitle) ?></span>
            </span>
            <span class="blog-list-read">
              <?= e($lang === 'bn' ? 'আরও পড়ুন' : 'Read more') ?>
              <span aria-hidden="true">→</span>
            </span>
          </a>
        <?php endforeach; ?>
      </section>

      <?php if ($list['total_pages'] > 1): ?>
        <nav class="blog-pagination" aria-label="<?= e($lang === 'bn' ? 'ব্লগ পেজ নম্বর' : 'Blog pagination') ?>">
          <?php for ($i = 1; $i <= $list['total_pages']; $i++): ?>
            <?php if ($i === (int) $list['page']): ?>
              <span aria-current="page"><?= e((string) $i) ?></span>
            <?php else: ?>
              <a href="<?= e($makeUrl(['page' => $i])) ?>"><?= e((string) $i) ?></a>
            <?php endif; ?>
          <?php endfor; ?>
        </nav>
      <?php endif; ?>
    <?php else: ?>
      <section class="blog-empty">
        <h2><?= e($lang === 'bn' ? 'কোনো পোস্ট পাওয়া যায়নি' : 'No posts found') ?></h2>
        <p><?= e(
          $search !== ''
            ? ($lang === 'bn' ? 'অন্য শব্দ দিয়ে আবার খুঁজুন।' : 'Try another search term.')
            : ($lang === 'bn' ? 'এই ক্যাটাগরিতে এখনও কোনো পোস্ট প্রকাশিত হয়নি।' : 'No posts have been published in this category yet.')
        ) ?></p>
      </section>
    <?php endif; ?>
  </div>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
