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
        $extra_head_html = '<link rel="stylesheet" href="' . e(site_url('assets/css/blog.css')) . '?v=' . e(front_asset_version()) . '">';
        include __DIR__ . '/includes/header.php';
        echo '<main class="blog-notfound-main"><div class="blog-notfound-card"><h1 class="blog-notfound-title">' . e($page_title) . '</h1><p class="blog-notfound-text">' . e($meta_description) . '</p><a href="' . e(blog_list_url($lang)) . '" class="blog-notfound-link">' . e($lang === 'bn' ? 'ব্লগে ফিরে যান' : 'Back to Blog') . '</a></div></main>';
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
$extra_head_html = '<link rel="stylesheet" href="' . e(site_url('assets/css/blog.css')) . '?v=' . e(front_asset_version()) . '">'
    . '<script type="application/ld+json">' . json_encode([
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
            <select class="blog-category-select" aria-label="<?= e($lang === 'bn' ? 'ক্যাটাগরি নির্বাচন করুন' : 'Choose category') ?>">
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

<script src="<?= e(site_url('assets/js/blog.js')) ?>" defer></script>

<?php include __DIR__ . '/includes/footer.php'; ?>
