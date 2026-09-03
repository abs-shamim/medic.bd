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

    include __DIR__ . '/includes/header.php';
    ?>
    <style>
      :root{--sp-primary:#0969da;--sp-accent:#2da44e;--sp-bg:#f6f8fa;--sp-surface:#fff;--sp-border:#d8dee4;--sp-text:#1f2328;--sp-muted:#656d76;--sp-radius:12px}
      *{box-sizing:border-box}.specialty-clean-page{min-height:100vh;padding:30px 0 56px;background:var(--sp-bg);color:var(--sp-text)}.specialty-clean-page .container{width:min(100% - 32px,760px);margin:0 auto}.specialty-not-found{padding:30px;border:1px solid var(--sp-border);border-radius:var(--sp-radius);background:var(--sp-surface);box-shadow:0 1px 2px rgba(31,35,40,.06);text-align:center}.specialty-not-found h1{margin:0 0 10px;font-size:28px;line-height:1.25}.specialty-not-found p{margin:0 0 20px;color:var(--sp-muted);line-height:1.6}.specialty-button{display:inline-flex;align-items:center;justify-content:center;min-height:42px;padding:9px 17px;border:1px solid rgba(31,35,40,.14);border-radius:9px;background:var(--sp-accent);color:#fff;font-size:14px;font-weight:600;line-height:20px;text-decoration:none}.specialty-button:hover{background:#248a43;color:#fff}@media(max-width:620px){.specialty-clean-page{padding:20px 0 42px}.specialty-clean-page .container{width:min(100% - 20px,760px)}.specialty-not-found{padding:24px 18px}}
    </style>
    <main class="specialty-clean-page">
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

include __DIR__ . '/includes/header.php';
echo specialty_render_schema($specialty_page_schema);
?>

<style>
  :root {
    --sp-primary: #0969da;
    --sp-accent: #2da44e;
    --sp-bg: #f6f8fa;
    --sp-surface: #ffffff;
    --sp-surface-soft: #f6f8fa;
    --sp-border: #d8dee4;
    --sp-border-strong: #c9d1d9;
    --sp-text: #1f2328;
    --sp-muted: #656d76;
    --sp-radius: 8px;
    --sp-radius-card: 12px;
    --sp-shadow: 0 1px 2px rgba(31, 35, 40, .06);
    --sp-shadow-hover: 0 5px 14px rgba(31, 35, 40, .12);
  }

  * { box-sizing: border-box; }

  .specialty-clean-page,
  .specialty-clean-page * {
    font-weight: 500 !important;
  }

  .specialty-clean-page {
    min-height: 100vh;
    padding: 22px 0 52px;
    background: var(--sp-bg);
    color: var(--sp-text);
  }

  .specialty-clean-page .container {
    width: min(100% - 32px, 1220px);
    margin: 0 auto;
  }

  .specialty-breadcrumb {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 6px;
    margin: 0 0 13px;
    color: var(--sp-muted);
    font-size: 13px;
    line-height: 1.45;
  }

  .specialty-breadcrumb a {
    color: var(--sp-primary);
    text-decoration: none;
  }

  .specialty-breadcrumb a:hover { text-decoration: underline; }

  .specialty-breadcrumb > span:not([aria-current="page"]) { color: #8c959f; }

  .specialty-breadcrumb span[aria-current="page"] {
    overflow: hidden;
    max-width: 440px;
    color: var(--sp-muted);
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  /* Matches the clean heading treatment used on the directory page. */
  .specialty-header {
    margin: 2px 0 20px;
    padding: 0;
    border: 0;
    border-radius: 0;
    background: transparent;
    box-shadow: none;
  }

  .specialty-header-row {
    display: grid;
    grid-template-columns: 112px minmax(0, 1fr);
    gap: 16px;
    align-items: start;
  }

  .specialty-icon {
    display: grid;
    width: 112px;
    height: 112px;
    place-items: center;
    overflow: hidden;
    border: 1px solid var(--sp-border);
    border-radius: 11px;
    background: var(--sp-surface);
    box-shadow: var(--sp-shadow);
    color: var(--sp-primary);
    font-size: 38px;
  }

  .specialty-icon i { font-size: 34px; }

  .specialty-icon img {
    display: block;
    width: 100%;
    height: 100%;
    object-fit: cover;
  }

  .specialty-header h1 {
    max-width: 920px;
    margin: 0 0 8px;
    color: var(--sp-text);
    font-size: clamp(27px, 4vw, 39px);
    line-height: 1.17;
    letter-spacing: -.028em;
  }

  .specialty-short-description {
    max-width: 790px;
    margin: 0;
    color: var(--sp-muted);
    font-size: 15px;
    line-height: 1.65;
  }

  .specialty-alternate-names {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    align-items: center;
    max-width: 850px;
    margin: 16px 0 0;
    padding-top: 15px;
    border-top: 1px solid var(--sp-border);
  }

  .specialty-alternate-label {
    color: var(--sp-muted);
    font-size: 13px;
    white-space: nowrap;
  }

  .specialty-alternate-list {
    display: flex;
    flex: 1 1 260px;
    flex-wrap: wrap;
    gap: 7px;
    margin: 0;
    padding: 0;
    list-style: none;
  }

  .specialty-alternate-list li {
    display: inline-flex;
    align-items: center;
    min-height: 32px;
    padding: 6px 10px;
    border: 1px solid var(--sp-border);
    border-radius: 8px;
    background: var(--sp-surface);
    box-shadow: var(--sp-shadow);
    color: var(--sp-primary);
    font-size: 12px;
    line-height: 1.35;
  }

  .specialty-layout {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 286px;
    gap: 16px;
    align-items: start;
  }

  .specialty-article-card,
  .specialty-doctors-side {
    overflow: hidden;
    border: 1px solid var(--sp-border);
    border-radius: var(--sp-radius-card);
    background: var(--sp-surface);
    box-shadow: var(--sp-shadow);
  }

  .specialty-article-content {
    padding: 28px;
    color: var(--sp-text);
    font-size: 16px;
    line-height: 1.72;
    overflow-wrap: anywhere;
  }

  .specialty-article-content > :first-child { margin-top: 0; }
  .specialty-article-content p { margin: 0 0 17px; }
  .specialty-article-content p:last-child { margin-bottom: 0; }

  .specialty-article-content h2,
  .specialty-article-content h3,
  .specialty-article-content h4 {
    margin: 1.55em 0 .72em;
    color: var(--sp-text);
    font-weight: 600 !important;
    line-height: 1.25;
    scroll-margin-top: 24px;
  }

  .specialty-article-content h2 {
    position: relative;
    padding: 0 0 11px;
    border-bottom: 1px solid var(--sp-border);
    font-size: 1.5em;
  }

  .specialty-article-content h2::after {
    position: absolute;
    bottom: -1px;
    left: 0;
    width: 44px;
    height: 3px;
    border-radius: 99px;
    background: var(--sp-primary);
    content: '';
  }

  .specialty-article-content h3 { font-size: 1.22em; }
  .specialty-article-content h4 { font-size: 1em; }
  .specialty-article-content strong { font-weight: 600 !important; }

  .specialty-article-content a {
    color: var(--sp-primary);
    text-decoration: none;
  }

  .specialty-article-content a:hover { text-decoration: underline; }

  .specialty-article-content ul,
  .specialty-article-content ol {
    margin: 0 0 17px;
    padding-left: 1.8em;
  }

  .specialty-article-content li + li { margin-top: 5px; }

  .specialty-article-content blockquote {
    margin: 18px 0;
    padding: 2px 0 2px 16px;
    border-left: 4px solid var(--sp-primary);
    color: var(--sp-muted);
  }

  .specialty-article-content blockquote p:last-child { margin-bottom: 0; }

  .specialty-article-content img {
    display: block;
    max-width: 100%;
    height: auto;
    margin: 18px auto;
    border-radius: 10px;
  }

  .specialty-article-content figure { margin: 18px 0; }

  .specialty-article-content figcaption {
    margin-top: 8px;
    color: var(--sp-muted);
    font-size: 12px;
    line-height: 1.5;
    text-align: center;
  }

  .specialty-article-content hr {
    height: 1px;
    margin: 26px 0;
    border: 0;
    background: var(--sp-border);
  }

  .specialty-article-content table {
    display: block;
    width: max-content;
    min-width: 100%;
    max-width: 100%;
    margin: 18px 0;
    overflow: auto;
    border-spacing: 0;
    border-collapse: collapse;
    font-size: 14px;
  }

  .specialty-article-content th,
  .specialty-article-content td {
    padding: 8px 13px;
    border: 1px solid var(--sp-border);
    text-align: left;
    vertical-align: top;
  }

  .specialty-article-content th {
    background: var(--sp-surface-soft);
    font-weight: 600 !important;
  }

  .specialty-article-content tr:nth-child(2n) { background: #fafbfc; }

  .specialty-article-content code {
    padding: .2em .4em;
    border-radius: 5px;
    background: rgba(175, 184, 193, .2);
    font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
    font-size: 85%;
  }

  .specialty-article-content pre {
    margin: 18px 0;
    padding: 16px;
    overflow: auto;
    border-radius: 9px;
    background: var(--sp-surface-soft);
    font-size: 85%;
    line-height: 1.45;
  }

  .specialty-article-content pre code { padding: 0; background: transparent; }

  .specialty-article-content .specialty-callout,
  .specialty-article-content .specialty-highlight {
    margin: 20px 0;
    padding: 15px 16px;
    border: 1px solid var(--sp-border);
    border-left: 4px solid var(--sp-primary);
    border-radius: 9px;
    background: var(--sp-surface-soft);
  }

  .specialty-article-content .specialty-callout > :last-child,
  .specialty-article-content .specialty-highlight > :last-child { margin-bottom: 0; }

  .specialty-empty-article {
    margin: 22px;
    padding: 24px;
    border: 1px dashed var(--sp-border-strong);
    border-radius: 10px;
    background: var(--sp-surface-soft);
    color: var(--sp-muted);
    line-height: 1.65;
    text-align: center;
  }

  .specialty-doctors-side {
    position: sticky;
    top: 16px;
  }

  .specialty-doctors-side-head {
    padding: 13px 16px;
    border-bottom: 1px solid var(--sp-border);
    background: var(--sp-surface-soft);
    color: var(--sp-text);
    font-size: 15px;
  }

  .specialty-doctors-side-body { padding: 18px; }

  .specialty-doctors-side-body h2 {
    margin: 0 0 8px;
    color: var(--sp-text);
    font-size: 20px;
    line-height: 1.32;
  }

  .specialty-doctors-side-body p {
    margin: 0 0 17px;
    color: var(--sp-muted);
    font-size: 14px;
    line-height: 1.65;
  }

  .specialty-doctors-side .specialty-button { width: 100%; }

  .specialty-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    min-height: 42px;
    padding: 9px 17px;
    border: 1px solid rgba(31, 35, 40, .14);
    border-radius: 9px;
    background: var(--sp-accent);
    box-shadow: 0 1px 0 rgba(31, 35, 40, .08);
    color: #ffffff;
    cursor: pointer;
    font-size: 14px;
    line-height: 20px;
    text-decoration: none;
    transition: filter .16s ease, transform .16s ease, box-shadow .16s ease;
  }

  .specialty-button:hover {
    color: #ffffff;
    filter: brightness(.97);
    box-shadow: 0 3px 8px rgba(31, 35, 40, .12);
    transform: translateY(-1px);
  }

  @media (max-width: 930px) {
    .specialty-layout { grid-template-columns: minmax(0, 1fr); }
    .specialty-doctors-side { position: static; }
  }

  @media (max-width: 700px) {
    .specialty-clean-page { padding: 16px 0 42px; }
    .specialty-clean-page .container { width: min(100% - 20px, 1220px); }
    .specialty-breadcrumb { margin-bottom: 10px; font-size: 12px; }
    .specialty-breadcrumb span[aria-current="page"] { max-width: 190px; }

    .specialty-header { margin-bottom: 18px; }
    .specialty-header-row {
      grid-template-columns: 84px minmax(0, 1fr);
      gap: 12px;
    }

    .specialty-icon {
      width: 84px;
      height: 84px;
      border-radius: 10px;
      font-size: 29px;
    }

    .specialty-icon i { font-size: 27px; }
    .specialty-header h1 { font-size: 28px; }
    .specialty-short-description { font-size: 14px; line-height: 1.58; }
    .specialty-alternate-names { margin-top: 14px; padding-top: 13px; }
    .specialty-alternate-list { flex-basis: 100%; }
    .specialty-article-content { padding: 22px 18px; font-size: 15px; }
    .specialty-empty-article { margin: 16px; padding: 20px; }
  }

  @media (max-width: 460px) {
    .specialty-header-row { grid-template-columns: 1fr; }
    .specialty-icon {
      width: 100%;
      height: 155px;
      border-radius: 12px;
    }
    .specialty-icon:not(:has(img)) { width: 84px; height: 84px; }
  }

  @media (prefers-reduced-motion: reduce) {
    *, *::before, *::after {
      scroll-behavior: auto !important;
      transition-duration: .01ms !important;
      animation-duration: .01ms !important;
    }
  }
</style>

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
