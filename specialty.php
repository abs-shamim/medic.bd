<?php
require_once __DIR__ . '/includes/functions.php';

/*
|--------------------------------------------------------------------------
| Translation Fallback Helper
|--------------------------------------------------------------------------
*/
if (!function_exists('__t')) {
    function __t(string $key, string $fallback = ''): string
    {
        return $fallback !== '' ? $fallback : $key;
    }
}

/*
|--------------------------------------------------------------------------
| Dynamic Language Text Helper
|--------------------------------------------------------------------------
*/
if (!function_exists('lang_text')) {
    function lang_text(string $english = '', string $bangla = ''): string
    {
        if (defined('CURRENT_LANG') && CURRENT_LANG === 'bn' && trim($bangla) !== '') {
            return $bangla;
        }

        return $english;
    }
}

/*
|--------------------------------------------------------------------------
| Frontend URL Fallback Helper
|--------------------------------------------------------------------------
*/
if (!function_exists('front_url')) {
    function front_url(string $path = '', ?string $lang = null): string
    {
        $lang = $lang ?: (defined('CURRENT_LANG') ? CURRENT_LANG : 'en');
        $path = trim($path, '/');

        if ($lang === 'bn') {
            return site_url($path !== '' ? 'bn/' . $path : 'bn');
        }

        return site_url($path);
    }
}


/*
|--------------------------------------------------------------------------
| Dynamic Site Settings And SEO Helpers
|--------------------------------------------------------------------------
| These helpers keep the specialty page independent from a hard-coded brand.
| The current Site Settings value is used whenever it is available, with the
| application constant kept only as a safe fallback.
|--------------------------------------------------------------------------
*/
if (!function_exists('specialty_site_setting')) {
    function specialty_site_setting(string $key, string $fallback = ''): string
    {
        static $cache = [];

        $key = trim($key);

        if ($key === '') {
            return $fallback;
        }

        if (array_key_exists($key, $cache)) {
            return $cache[$key] !== '' ? $cache[$key] : $fallback;
        }

        $value = '';

        if (function_exists('get_site_setting')) {
            try {
                $value = trim((string)get_site_setting($key, ''));
            } catch (Throwable $e) {
                $value = '';
            }
        }

        if ($value === '') {
            global $pdo;

            if (isset($pdo) && $pdo instanceof PDO) {
                try {
                    $stmt = $pdo->prepare(
                        'SELECT setting_value FROM site_settings WHERE setting_key = :setting_key LIMIT 1'
                    );
                    $stmt->execute([':setting_key' => $key]);
                    $value = trim((string)$stmt->fetchColumn());
                } catch (Throwable $e) {
                    $value = '';
                }
            }
        }

        $cache[$key] = $value;

        return $value !== '' ? $value : $fallback;
    }
}

if (!function_exists('specialty_dynamic_site_name')) {
    function specialty_dynamic_site_name(): string
    {
        $fallback = defined('APP_NAME') && trim((string)APP_NAME) !== ''
            ? trim((string)APP_NAME)
            : 'MediCare';

        foreach (['site_name', 'website_name', 'app_name'] as $setting_key) {
            $value = specialty_site_setting($setting_key, '');

            if ($value !== '') {
                return $value;
            }
        }

        return $fallback;
    }
}

if (!function_exists('specialty_absolute_url')) {
    function specialty_absolute_url(string $path): string
    {
        $path = trim($path);

        if ($path === '') {
            return '';
        }

        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }

        $path = str_replace('\\', '/', $path);
        $path = preg_replace('#^(?:\./|\.\./)+#', '', $path);
        $path = ltrim((string)$path, '/');

        if (function_exists('site_url')) {
            return site_url($path);
        }

        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = trim((string)($_SERVER['HTTP_HOST'] ?? ''));

        return $host !== '' ? $scheme . '://' . $host . '/' . $path : '/' . $path;
    }
}

if (!function_exists('specialty_page_title_with_site')) {
    function specialty_page_title_with_site(string $title, string $site_name): string
    {
        $title = trim($title);
        $site_name = trim($site_name);

        if ($title === '') {
            return $site_name;
        }

        if ($site_name === '') {
            return $title;
        }

        $suffix = ' | ' . $site_name;

        if (function_exists('mb_substr') && function_exists('mb_strlen')) {
            $title_length = mb_strlen($title, 'UTF-8');
            $suffix_length = mb_strlen($suffix, 'UTF-8');

            if ($title_length >= $suffix_length && mb_substr($title, -$suffix_length, null, 'UTF-8') === $suffix) {
                return $title;
            }
        } elseif (str_ends_with($title, $suffix)) {
            return $title;
        }

        return $title . $suffix;
    }
}

if (!function_exists('specialty_image_mime_type')) {
    function specialty_image_mime_type(string $url): string
    {
        $path = (string)parse_url($url, PHP_URL_PATH);
        $extension = strtolower((string)pathinfo($path, PATHINFO_EXTENSION));

        return match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            'avif' => 'image/avif',
            default => '',
        };
    }
}

if (!function_exists('specialty_schema_text')) {
    function specialty_schema_text(string $value): string
    {
        $value = strip_tags($value);
        $value = preg_replace('/\s+/u', ' ', $value);

        return trim((string)$value);
    }
}

if (!function_exists('specialty_render_schema')) {
    function specialty_render_schema(array $schema): string
    {
        if (empty($schema)) {
            return '';
        }

        $json = json_encode(
            $schema,
            JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
            | JSON_HEX_TAG
            | JSON_HEX_AMP
            | JSON_HEX_APOS
            | JSON_HEX_QUOT
        );

        if ($json === false) {
            return '';
        }

        return "<script type=\"application/ld+json\">\n" . $json . "\n</script>";
    }
}

/*
|--------------------------------------------------------------------------
| Safe Article Helpers
|--------------------------------------------------------------------------
*/
if (!function_exists('specialty_article_slug')) {
    function specialty_article_slug(string $slug): string
    {
        $slug = rawurldecode(trim($slug));

        if (function_exists('seo_url_slug')) {
            return seo_url_slug($slug);
        }

        $slug = preg_replace('/[^\p{L}\p{N}\s-]+/u', '', $slug);
        $slug = preg_replace('/[\s_]+/u', '-', (string)$slug);
        $slug = preg_replace('/-+/u', '-', (string)$slug);

        if (function_exists('mb_strtolower')) {
            $slug = mb_strtolower((string)$slug, 'UTF-8');
        } else {
            $slug = strtolower((string)$slug);
        }

        return trim((string)$slug, '-');
    }
}

if (!function_exists('specialty_article_excerpt')) {
    function specialty_article_excerpt(string $text, int $length = 155): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', strip_tags($text)));

        if ($text === '') {
            return '';
        }

        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            if (mb_strlen($text, 'UTF-8') <= $length) {
                return $text;
            }

            return rtrim(mb_substr($text, 0, $length - 1, 'UTF-8')) . '…';
        }

        if (strlen($text) <= $length) {
            return $text;
        }

        return rtrim(substr($text, 0, $length - 1)) . '…';
    }
}

if (!function_exists('specialty_article_sanitize_html')) {
    function specialty_article_sanitize_html(string $html): string
    {
        $html = trim($html);

        if ($html === '') {
            return '';
        }

        $html = preg_replace(
            '#<\s*(script|iframe|object|embed|form|input|button|textarea|select|meta|link|base)\b[^>]*>.*?<\s*/\s*\1\s*>#is',
            '',
            $html
        );

        $html = preg_replace(
            '#<\s*(script|iframe|object|embed|form|input|button|textarea|select|meta|link|base)\b[^>]*?/?>#is',
            '',
            $html
        );

        $html = preg_replace('/\son[a-z0-9_-]+\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)/iu', '', $html);
        $html = preg_replace('/\s(href|src)\s*=\s*(["\'])\s*javascript:[^"\']*\2/iu', '', $html);
        $html = preg_replace('/@import\s+(?:url\()?[^;]+;?/iu', '', $html);
        $html = preg_replace('/expression\s*\(/iu', '', $html);

        return trim((string)$html);
    }
}

if (!function_exists('specialty_article_content_html')) {
    function specialty_article_content_html(string $content): string
    {
        $content = trim($content);

        if ($content === '') {
            return '';
        }

        // Preserve article HTML and scoped CSS created through the admin editor.
        if (preg_match('/<\/?[a-z][^>]*>/i', $content)) {
            return specialty_article_sanitize_html($content);
        }

        // Keep legacy plain-text content readable as paragraphs.
        $paragraphs = preg_split('/(?:\r\n|\r|\n){2,}/u', $content, -1, PREG_SPLIT_NO_EMPTY);

        if (!is_array($paragraphs) || empty($paragraphs)) {
            return '<p>' . nl2br(e($content)) . '</p>';
        }

        $html = '';

        foreach ($paragraphs as $paragraph) {
            $paragraph = trim($paragraph);

            if ($paragraph !== '') {
                $html .= '<p>' . nl2br(e($paragraph)) . '</p>';
            }
        }

        return $html;
    }
}

$slug = specialty_article_slug((string)($_GET['slug'] ?? ''));
$specialty = function_exists('get_specialty_by_slug') ? get_specialty_by_slug($slug) : null;

if (!$specialty) {
    http_response_code(404);

    $site_name = specialty_dynamic_site_name();
    $page_title = specialty_page_title_with_site(
        __t('specialty_not_found_title', 'Specialty Not Found'),
        $site_name
    );
    $meta_description = __t('specialty_not_found_description', 'The requested medical specialty could not be found.');
    $canonical_url = front_url('specialties');
    $robots_meta = 'noindex, follow';
    $og_title = $page_title;
    $og_description = $meta_description;
    $og_type = 'website';
    $og_image = specialty_absolute_url(
        specialty_site_setting('default_og_image', 'assets/images/default-og-image.webp')
    );
    $og_image_alt = $site_name;
    $og_image_type = specialty_image_mime_type($og_image);
    $twitter_card = 'summary_large_image';
    $extra_head_html = ($extra_head_html ?? '') . '<link rel="stylesheet" href="' . e(site_url('assets/css/specialty.css')) . '?v=' . e(front_asset_version()) . '">';

    include __DIR__ . '/includes/header.php';
    ?>
    <main class="specialty-not-found-page">
      <div class="container">
        <section class="specialty-not-found">
          <h1><?= e(__t('specialty_not_found_title', 'Specialty Not Found')) ?></h1>
          <p><?= e(__t('specialty_not_found_description', 'The requested medical specialty could not be found.')) ?></p>
          <a class="specialty-button" href="<?= e(front_url('specialties')) ?>">
            <?= e(__t('back_to_specialties', 'Back to Specialties')) ?>
          </a>
        </section>
      </div>
    </main>
    <?php
    include __DIR__ . '/includes/footer.php';
    exit;
}

$is_bangla = defined('CURRENT_LANG') && CURRENT_LANG === 'bn';
$site_name = specialty_dynamic_site_name();

$specialty_name = lang_text(
    (string)($specialty['name'] ?? ''),
    (string)($specialty['name_bn'] ?? '')
);

// The short description is displayed directly under the title without a label.
$short_description = lang_text(
    (string)($specialty['description'] ?? ''),
    (string)($specialty['description_bn'] ?? '')
);

$alternate_names_raw = lang_text(
    (string)($specialty['alternate_names'] ?? ''),
    (string)($specialty['alternate_names_bn'] ?? '')
);

$alternate_names = [];
$alternate_names_seen = [];

foreach (preg_split('/[\r\n,;|]+/u', $alternate_names_raw, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $alternate_name) {
    $alternate_name = trim((string)$alternate_name);

    if ($alternate_name === '') {
        continue;
    }

    $alternate_key = function_exists('mb_strtolower')
        ? mb_strtolower($alternate_name, 'UTF-8')
        : strtolower($alternate_name);

    if (isset($alternate_names_seen[$alternate_key])) {
        continue;
    }

    $alternate_names_seen[$alternate_key] = true;
    $alternate_names[] = $alternate_name;
}

$article_content = lang_text(
    (string)($specialty['article_content'] ?? ''),
    (string)($specialty['article_content_bn'] ?? '')
);

$seo_title = lang_text(
    (string)($specialty['seo_title'] ?? ''),
    (string)($specialty['seo_title_bn'] ?? '')
);

if (trim($seo_title) === '') {
    $seo_title = $is_bangla
        ? $specialty_name . ' ডাক্তার ও চিকিৎসা তথ্য'
        : $specialty_name . ' Doctors and Medical Information';
}

$seo_description = lang_text(
    (string)($specialty['seo_description'] ?? ''),
    (string)($specialty['seo_description_bn'] ?? '')
);

if (trim($seo_description) === '') {
    $seo_description = specialty_article_excerpt($short_description);
}

if (trim($seo_description) === '') {
    $seo_description = specialty_article_excerpt($article_content);
}

if (trim($seo_description) === '') {
    $seo_description = $is_bangla
        ? $specialty_name . ' বিশেষজ্ঞ ডাক্তার, চেম্বার, হাসপাতাল, ভিজিটিং সময় ও অ্যাপয়েন্টমেন্ট তথ্য দেখুন।'
        : 'Find ' . $specialty_name . ' doctors, chamber locations, hospitals and appointment details.';
}

$page_title = specialty_page_title_with_site($seo_title, $site_name);
$meta_description = specialty_article_excerpt($seo_description, 170);

$specialty_slug = trim((string)($specialty['slug'] ?? $slug));
$specialty_path = $specialty_slug !== ''
    ? 'specialty/' . rawurlencode($specialty_slug)
    : 'specialties';
$canonical_url = front_url($specialty_path);
$alternate_en_url = front_url($specialty_path, 'en');
$alternate_bn_url = front_url($specialty_path, 'bn');
$doctors_url = $specialty_slug !== ''
    ? front_url('doctors/' . rawurlencode($specialty_slug))
    : front_url('doctors');

$article_html = specialty_article_content_html($article_content);
$specialty_image = trim((string)($specialty['image'] ?? ''));
$specialty_image_url = specialty_absolute_url($specialty_image);
$default_og_image_url = specialty_absolute_url(
    specialty_site_setting('default_og_image', 'assets/images/default-og-image.webp')
);

$og_image = $specialty_image_url !== '' ? $specialty_image_url : $default_og_image_url;
$og_title = $page_title;
$og_description = $meta_description;
$og_type = 'article';
$og_locale = $is_bangla ? 'bn_BD' : 'en_BD';
$og_image_alt = $specialty_name . ' | ' . $site_name;
$og_image_type = specialty_image_mime_type($og_image);
$twitter_card = 'summary_large_image';
$robots_meta = specialty_site_setting(
    'robots_meta',
    'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1'
);

$specialty_keywords_raw = lang_text(
    (string)($specialty['seo_keywords'] ?? ''),
    (string)($specialty['seo_keywords_bn'] ?? '')
);

if (trim($specialty_keywords_raw) === '') {
    $specialty_keywords_raw = implode(', ', array_filter(array_merge(
        [$specialty_name],
        $alternate_names
    )));
}

$meta_keywords = specialty_article_excerpt($specialty_keywords_raw, 255);
$specialty_icon = trim((string)($specialty['icon'] ?? ''));
$specialty_icon_is_class = $specialty_icon !== '' && preg_match('/\bfa(?:-[a-z0-9-]+)?\b/i', $specialty_icon);

$alternate_names_label = $is_bangla ? 'অন্যান্য পরিচিত নাম' : 'Also known as';
$empty_article_text = $is_bangla
    ? 'এই বিশেষজ্ঞতা সম্পর্কে বিস্তারিত তথ্য শিগগিরই যোগ করা হবে।'
    : 'Detailed information about this specialty will be added soon.';

/*
|--------------------------------------------------------------------------
| Doctors Sidebar Text
|--------------------------------------------------------------------------
| Keep the message simple and specialty-specific.
| Bangla example: কার্ডিওলজি বিশেষজ্ঞ ডাক্তারদের চেম্বার, হাসপাতাল ও
| অ্যাপয়েন্টমেন্ট তথ্য দেখুন।
*/
$doctors_side_title = $is_bangla
    ? 'এই বিভাগের ডাক্তার'
    : 'Doctors in This Specialty';

$doctors_side_description = $is_bangla
    ? $specialty_name . ' বিশেষজ্ঞ ডাক্তারদের চেম্বার, হাসপাতাল ও অ্যাপয়েন্টমেন্ট তথ্য দেখুন।'
    : 'Find doctors specializing in ' . $specialty_name . ', along with chamber, hospital and appointment information.';

/*
|--------------------------------------------------------------------------
| Specialty Page Structured Data
|--------------------------------------------------------------------------
| The graph mirrors visible information only. It intentionally does not add
| ratings, review counts, prices, credentials, or medical claims that are not
| stored on the page.
|--------------------------------------------------------------------------
*/
$website_url = rtrim(specialty_absolute_url(''), '/');

if ($website_url === '') {
    $website_url = rtrim(front_url('', 'en'), '/');
}

$website_id = $website_url . '/#website';
$webpage_id = $canonical_url . '#webpage';
$breadcrumb_id = $canonical_url . '#breadcrumb';
$specialty_entity_id = $canonical_url . '#medical-specialty';
$schema_language = $is_bangla ? 'bn-BD' : 'en-BD';
$schema_page_name = specialty_schema_text($seo_title);
$schema_description = specialty_schema_text($meta_description);

$specialty_schema_graph = [
    [
        '@type' => 'WebSite',
        '@id' => $website_id,
        'url' => $website_url . '/',
        'name' => specialty_schema_text($site_name),
        'inLanguage' => $schema_language,
    ],
    [
        '@type' => 'Thing',
        '@id' => $specialty_entity_id,
        'name' => specialty_schema_text($specialty_name),
        'url' => $canonical_url,
    ],
    [
        '@type' => ['WebPage', 'MedicalWebPage'],
        '@id' => $webpage_id,
        'url' => $canonical_url,
        'name' => $schema_page_name,
        'description' => $schema_description,
        'inLanguage' => $schema_language,
        'isPartOf' => [
            '@id' => $website_id,
        ],
        'about' => [
            '@id' => $specialty_entity_id,
        ],
        'breadcrumb' => [
            '@id' => $breadcrumb_id,
        ],
    ],
    [
        '@type' => 'BreadcrumbList',
        '@id' => $breadcrumb_id,
        'itemListElement' => [
            [
                '@type' => 'ListItem',
                'position' => 1,
                'name' => specialty_schema_text(__t('home', 'Home')),
                'item' => front_url('', $is_bangla ? 'bn' : 'en'),
            ],
            [
                '@type' => 'ListItem',
                'position' => 2,
                'name' => specialty_schema_text(__t('specialties', 'Specialties')),
                'item' => front_url('specialties', $is_bangla ? 'bn' : 'en'),
            ],
            [
                '@type' => 'ListItem',
                'position' => 3,
                'name' => specialty_schema_text($specialty_name),
                'item' => $canonical_url,
            ],
        ],
    ],
];

if ($schema_description !== '') {
    $specialty_schema_graph[1]['description'] = $schema_description;
}

if ($og_image !== '') {
    $image_object = [
        '@type' => 'ImageObject',
        'url' => $og_image,
        'caption' => specialty_schema_text($og_image_alt),
    ];

    $specialty_schema_graph[1]['image'] = $image_object;
    $specialty_schema_graph[2]['primaryImageOfPage'] = $image_object;
}

$created_at = trim((string)($specialty['created_at'] ?? ''));
$updated_at = trim((string)($specialty['updated_at'] ?? ''));

if (trim($article_content) !== '' && $created_at !== '') {
    $article_schema = [
        '@type' => 'Article',
        '@id' => $canonical_url . '#article',
        'mainEntityOfPage' => [
            '@id' => $webpage_id,
        ],
        'headline' => $schema_page_name,
        'description' => $schema_description,
        'inLanguage' => $schema_language,
        'isPartOf' => [
            '@id' => $website_id,
        ],
        'about' => [
            '@id' => $specialty_entity_id,
        ],
        'author' => [
            '@type' => 'Organization',
            'name' => specialty_schema_text($site_name),
            'url' => $website_url . '/',
        ],
        'publisher' => [
            '@type' => 'Organization',
            'name' => specialty_schema_text($site_name),
            'url' => $website_url . '/',
        ],
        'datePublished' => $created_at,
    ];

    if ($updated_at !== '') {
        $article_schema['dateModified'] = $updated_at;
    }

    if ($og_image !== '') {
        $article_schema['image'] = $og_image;
    }

    $specialty_schema_graph[] = $article_schema;
}

$specialty_page_schema = [
    '@context' => 'https://schema.org',
    '@graph' => $specialty_schema_graph,
];

$extra_head_html = ($extra_head_html ?? '') . '<link rel="stylesheet" href="' . e(site_url('assets/css/specialty.css')) . '?v=' . e(front_asset_version()) . '">';

include __DIR__ . '/includes/header.php';
echo specialty_render_schema($specialty_page_schema);
?>

<main class="specialty-clean-page">
  <div class="container">
    <nav class="specialty-breadcrumb" aria-label="Breadcrumb">
      <a href="<?= e(front_url()) ?>"><?= e(__t('home', 'Home')) ?></a>
      <span>/</span>
      <a href="<?= e(front_url('specialties')) ?>"><?= e(__t('specialties', 'Specialties')) ?></a>
      <span>/</span>
      <span aria-current="page"><?= e($specialty_name) ?></span>
    </nav>

    <header class="specialty-header">
      <div class="specialty-header-row">
        <div class="specialty-icon" aria-hidden="true">
          <?php if ($specialty_image_url !== ''): ?>
            <img src="<?= e($specialty_image_url) ?>" alt="">
          <?php elseif ($specialty_icon !== '' && $specialty_icon_is_class): ?>
            <i class="<?= e($specialty_icon) ?>"></i>
          <?php else: ?>
            <span><?= e($specialty_icon !== '' ? $specialty_icon : '⚕') ?></span>
          <?php endif; ?>
        </div>

        <div>
          <h1><?= e($specialty_name) ?></h1>

          <?php if (trim($short_description) !== ''): ?>
            <p class="specialty-short-description"><?= e($short_description) ?></p>
          <?php endif; ?>

          <?php if (!empty($alternate_names)): ?>
            <section class="specialty-alternate-names" aria-label="<?= e($alternate_names_label) ?>">
              <span class="specialty-alternate-label"><?= e($alternate_names_label) ?>:</span>
              <ul class="specialty-alternate-list">
                <?php foreach ($alternate_names as $alternate_name): ?>
                  <li><?= e($alternate_name) ?></li>
                <?php endforeach; ?>
              </ul>
            </section>
          <?php endif; ?>
        </div>
      </div>
    </header>

    <div class="specialty-layout">
      <article class="specialty-article-card">
        <?php if (trim($article_html) !== ''): ?>
          <div class="specialty-article-content">
            <?= $article_html ?>
          </div>
        <?php else: ?>
          <div class="specialty-empty-article"><?= e($empty_article_text) ?></div>
        <?php endif; ?>
      </article>

      <aside class="specialty-doctors-side">
        <div class="specialty-doctors-side-head">
          <?= e($doctors_side_title) ?>
        </div>

        <div class="specialty-doctors-side-body">
          <h2><?= e($specialty_name) ?></h2>
          <p><?= e($doctors_side_description) ?></p>

          <a class="specialty-button" href="<?= e($doctors_url) ?>">
            <span><?= e(__t('view_doctors', 'View Doctors')) ?></span>
            <span aria-hidden="true">→</span>
          </a>
        </div>
      </aside>
    </div>
  </div>
</main>


<?php include __DIR__ . '/includes/footer.php'; ?>
