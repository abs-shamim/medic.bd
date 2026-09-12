<?php
require_once __DIR__ . '/includes/functions.php';

/*
|--------------------------------------------------------------------------
| Translation Fallback Helper
|--------------------------------------------------------------------------
| Keeps this page safe when the frontend translation system is unavailable.
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
| Returns Bangla database text for Bangla pages and falls back to English.
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
| Keeps specialty and doctor links language-aware.
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
| Specialty Collection SEO
|--------------------------------------------------------------------------
| The page prepares all document metadata before the shared header loads.
| The header then outputs the title, canonical URL, language alternatives,
| Open Graph, Twitter and robots tags from these variables.
*/
if (!function_exists('specialties_seo_setting')) {
    function specialties_seo_setting(string $key, string $default = ''): string
    {
        $key = trim($key);

        if ($key === '') {
            return $default;
        }

        if (function_exists('get_site_setting')) {
            try {
                $value = trim((string) get_site_setting($key, $default));

                if ($value !== '') {
                    return $value;
                }
            } catch (Throwable $e) {
                // Continue to the database fallback below.
            }
        }

        global $pdo;

        if (!isset($pdo) || !$pdo instanceof PDO) {
            return $default;
        }

        try {
            $statement = $pdo->prepare(
                'SELECT setting_value FROM site_settings WHERE setting_key = :setting_key LIMIT 1'
            );
            $statement->execute([':setting_key' => $key]);
            $value = trim((string) $statement->fetchColumn());

            return $value !== '' ? $value : $default;
        } catch (Throwable $e) {
            return $default;
        }
    }
}

if (!function_exists('specialties_seo_site_name')) {
    function specialties_seo_site_name(): string
    {
        foreach (['site_name', 'website_name', 'app_name'] as $setting_key) {
            $value = specialties_seo_setting($setting_key, '');

            if ($value !== '') {
                return $value;
            }
        }

        return defined('APP_NAME') && trim((string) APP_NAME) !== ''
            ? trim((string) APP_NAME)
            : 'MedicBD';
    }
}

if (!function_exists('specialties_seo_absolute_url')) {
    function specialties_seo_absolute_url(string $path): string
    {
        $path = trim($path);

        if ($path === '') {
            return '';
        }

        if (preg_match('/^https:\/\//i', $path)) {
            return $path;
        }

        if (preg_match('/^http:\/\//i', $path)) {
            return 'https://' . preg_replace('/^http:\/\//i', '', $path);
        }

        $path = preg_replace('#^(?:\.\./)+#', '', $path);
        $path = ltrim((string) $path, '/');

        return function_exists('site_url') ? site_url($path) : '/' . $path;
    }
}

if (!function_exists('specialties_seo_image_type')) {
    function specialties_seo_image_type(string $url): string
    {
        $path = (string) parse_url($url, PHP_URL_PATH);
        $extension = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));

        $types = [
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png'  => 'image/png',
            'webp' => 'image/webp',
            'gif'  => 'image/gif',
        ];

        return $types[$extension] ?? '';
    }
}

if (!function_exists('specialties_seo_slug')) {
    function specialties_seo_slug(string $text): string
    {
        $text = trim($text);
        $text = preg_replace('/[^\p{L}\p{N}\s-]+/u', '', $text);
        $text = preg_replace('/[\s_]+/u', '-', (string) $text);
        $text = preg_replace('/-+/u', '-', (string) $text);

        if (function_exists('mb_strtolower')) {
            $text = mb_strtolower((string) $text, 'UTF-8');
        } else {
            $text = strtolower((string) $text);
        }

        return trim((string) $text, '-');
    }
}

if (!function_exists('specialties_seo_text')) {
    function specialties_seo_text($value): string
    {
        $value = strip_tags((string) $value);
        $value = preg_replace('/\s+/u', ' ', $value);

        return trim((string) $value);
    }
}

$specialties = get_specialties();
$is_bn = defined('CURRENT_LANG') && CURRENT_LANG === 'bn';
$specialties_lang = $is_bn ? 'bn' : 'en';
$specialties_locale = $is_bn ? 'bn_BD' : 'en_US';
$site_name = specialties_seo_site_name();
$page_heading = __t('specialties_page_title', 'Specialties');
$meta_description = __t(
    'specialties_meta_description',
    'Browse doctors by medical specialties and find the right specialist faster.'
);
$page_title = $page_heading . ' | ' . $site_name;
$canonical_url = front_url('specialties', $specialties_lang);
$robots_meta = !empty($_GET)
    ? 'noindex, follow'
    : 'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1';
$meta_keywords = $is_bn
    ? 'বাংলাদেশের ডাক্তার বিশেষজ্ঞতা, বিশেষজ্ঞ ডাক্তার, মেডিকেল স্পেশালিটি, ' . $site_name
    : 'medical specialties in Bangladesh, specialist doctors, doctor specialties, ' . $site_name;

$og_image = '';
$og_image_alt = '';

foreach ((array) $specialties as $specialty_for_image) {
    $image_path = trim((string) ($specialty_for_image['image'] ?? ''));

    if ($image_path === '') {
        continue;
    }

    $og_image = specialties_seo_absolute_url($image_path);
    $og_image_alt = lang_text(
        (string) ($specialty_for_image['name'] ?? ''),
        (string) ($specialty_for_image['name_bn'] ?? '')
    );
    break;
}

if ($og_image === '') {
    $og_image = specialties_seo_absolute_url(
        specialties_seo_setting('default_og_image', 'assets/images/default-og-image.webp')
    );
}

$og_title = $page_title;
$og_description = $meta_description;
$og_type = 'website';
$og_locale = $specialties_locale;
$og_image_alt = $og_image_alt !== ''
    ? $og_image_alt . ' | ' . $page_heading
    : $page_heading . ' | ' . $site_name;
$og_image_type = specialties_seo_image_type($og_image);
$og_image_width = 0;
$og_image_height = 0;
$twitter_card = 'summary_large_image';

$specialties_schema_items = [];
$schema_position = 1;

foreach ((array) $specialties as $specialty_for_schema) {
    $schema_name = lang_text(
        (string) ($specialty_for_schema['name'] ?? ''),
        (string) ($specialty_for_schema['name_bn'] ?? '')
    );
    $schema_name = specialties_seo_text($schema_name);

    if ($schema_name === '') {
        continue;
    }

    $schema_slug = trim((string) ($specialty_for_schema['slug'] ?? ''));

    if ($schema_slug === '') {
        $schema_slug = specialties_seo_slug((string) ($specialty_for_schema['name'] ?? $schema_name));
    }

    $schema_url = $schema_slug !== ''
        ? front_url('specialty/' . rawurlencode($schema_slug), $specialties_lang)
        : $canonical_url;
    $schema_description = lang_text(
        (string) ($specialty_for_schema['description'] ?? ''),
        (string) ($specialty_for_schema['description_bn'] ?? '')
    );
    $schema_image = specialties_seo_absolute_url((string) ($specialty_for_schema['image'] ?? ''));

    $schema_item = [
        '@type' => 'MedicalSpecialty',
        'name'  => $schema_name,
        'url'   => $schema_url,
    ];

    if ($schema_description !== '') {
        $schema_item['description'] = specialties_seo_text($schema_description);
    }

    if ($schema_image !== '') {
        $schema_item['image'] = $schema_image;
    }

    $specialties_schema_items[] = [
        '@type'    => 'ListItem',
        'position' => $schema_position++,
        'url'      => $schema_url,
        'name'     => $schema_name,
        'item'     => $schema_item,
    ];
}

$website_home_url = front_url('', $specialties_lang);
$specialties_schema = [
    '@context' => 'https://schema.org',
    '@graph'   => [
        [
            '@type' => 'WebSite',
            '@id'   => rtrim($website_home_url, '/') . '/#website',
            'url'   => $website_home_url,
            'name'  => $site_name,
            'inLanguage' => $specialties_lang,
        ],
        [
            '@type' => 'CollectionPage',
            '@id'   => $canonical_url . '#webpage',
            'url'   => $canonical_url,
            'name'  => $page_heading,
            'description' => $meta_description,
            'inLanguage'  => $specialties_lang,
            'isPartOf'    => ['@id' => rtrim($website_home_url, '/') . '/#website'],
            'mainEntity'  => ['@id' => $canonical_url . '#specialty-list'],
        ],
        [
            '@type' => 'BreadcrumbList',
            '@id'   => $canonical_url . '#breadcrumb',
            'itemListElement' => [
                [
                    '@type'    => 'ListItem',
                    'position' => 1,
                    'name'     => __t('home', 'Home'),
                    'item'     => $website_home_url,
                ],
                [
                    '@type'    => 'ListItem',
                    'position' => 2,
                    'name'     => $page_heading,
                    'item'     => $canonical_url,
                ],
            ],
        ],
        [
            '@type' => 'ItemList',
            '@id'   => $canonical_url . '#specialty-list',
            'name'  => $page_heading,
            'numberOfItems' => count($specialties_schema_items),
            'itemListOrder' => 'https://schema.org/ItemListUnordered',
            'itemListElement' => $specialties_schema_items,
        ],
    ],
];

$specialties_schema_json = json_encode(
    $specialties_schema,
    JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
);

if (!empty($_GET) && !headers_sent()) {
    header('X-Robots-Tag: noindex, follow', true);
}

$extra_head_html = ($extra_head_html ?? '') . '<link rel="stylesheet" href="' . e(site_url('assets/css/specialties.css')) . '?v=' . e(front_asset_version()) . '">';

include __DIR__ . '/includes/header.php';

if (is_string($specialties_schema_json) && $specialties_schema_json !== '') {
    echo "\n<script type=\"application/ld+json\">{$specialties_schema_json}</script>\n";
}

function specialty_page_slug(string $text): string
{
    $text = trim($text);
    $text = preg_replace('/[^\p{L}\p{N}\s-]+/u', '', $text);
    $text = preg_replace('/[\s_]+/u', '-', (string) $text);
    $text = preg_replace('/-+/u', '-', (string) $text);

    if (function_exists('mb_strtolower')) {
        $text = mb_strtolower($text, 'UTF-8');
    } else {
        $text = strtolower($text);
    }

    return trim((string) $text, '-');
}
?>

<main class="medic-specialties-page<?= $is_bn ? ' is-bn' : '' ?>">
  <div class="container">

    <nav class="medic-breadcrumb" aria-label="<?= e(__t('specialties_page_breadcrumb_aria', 'Specialty navigation')) ?>">
      <a href="<?= e(front_url()) ?>"><?= e(__t('home', 'Home')) ?></a>
      <span>/</span>
      <a href="<?= e(front_url('specialties')) ?>"><?= e(__t('specialties', 'Specialties')) ?></a>
    </nav>

    <section class="medic-page-head">
      <h1><?= e(__t('specialties', 'Specialties')) ?></h1>
      <p><?= e(__t('specialties_page_intro', 'Browse doctors by medical specialty and find the right specialist faster.')) ?></p>
    </section>

    <div class="medic-search-box">
      <form class="medic-search-row" action="<?= e(front_url('doctors')) ?>" method="GET">
        <div class="medic-search-input-wrap">
          <svg viewBox="0 0 16 16" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="6.8" cy="6.8" r="4.5"></circle><path d="m10.2 10.2 3.1 3.1"></path></svg>
          <input
            type="search"
            name="search"
            value="<?= e($_GET['search'] ?? '') ?>"
            aria-label="<?= e(__t('search_doctor_name_placeholder', 'Search doctor name...')) ?>"
            placeholder="<?= e(__t('search_doctor_name_placeholder', 'Search doctor name...')) ?>"
          >
        </div>

        <button class="medic-btn" type="submit">
          <svg viewBox="0 0 16 16" aria-hidden="true"><circle cx="6.8" cy="6.8" r="4.5"></circle><path d="m10.2 10.2 3.1 3.1"></path></svg>
          <span><?= e(__t('search_button', 'Search')) ?></span>
        </button>
      </form>
    </div>

    <section class="medic-specialty-section">
      <h2 class="medic-list-title"><?= e(__t('all_specialties', 'All Specialties')) ?></h2>
      <p class="medic-list-subtitle"><?= e(__t('select_specialty_text', 'Select a specialty to view available doctors.')) ?></p>

      <?php if (!empty($specialties)): ?>
        <div class="medic-specialty-grid">
          <?php foreach ($specialties as $specialty): ?>
            <?php
              $specialty_name = lang_text(
                  (string) ($specialty['name'] ?? ''),
                  (string) ($specialty['name_bn'] ?? '')
              );

              $specialty_description = lang_text(
                  (string) ($specialty['description'] ?? ''),
                  (string) ($specialty['description_bn'] ?? '')
              );

              $specialty_slug = trim((string) ($specialty['slug'] ?? ''));

              if ($specialty_slug === '') {
                  $specialty_slug = specialty_page_slug((string) ($specialty['name'] ?? $specialty_name));
              }

              $specialty_url = $specialty_slug !== ''
                  ? front_url('specialty/' . rawurlencode($specialty_slug))
                  : front_url('specialties');

              $specialty_image = trim((string) ($specialty['image'] ?? ''));

              if ($specialty_image !== '' && !preg_match('#^https?://#i', $specialty_image)) {
                  $specialty_image = site_url(ltrim($specialty_image, '/'));
              }
            ?>

            <a class="medic-specialty-card<?= $specialty_image !== '' ? ' has-image' : '' ?>" href="<?= e($specialty_url) ?>">
              <?php if ($specialty_image !== ''): ?>
                <span class="medic-specialty-media">
                  <img src="<?= e($specialty_image) ?>" alt="<?= e($specialty_name) ?>" loading="lazy">
                </span>
              <?php endif; ?>

              <span class="medic-specialty-label"><?= e($specialty_name) ?></span>
            </a>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div class="medic-empty-card">
          <h2><?= e(__t('no_specialty_found', 'No specialty found')) ?></h2>
          <p><?= e(__t('add_specialties_first', 'Please add specialties from the admin panel first.')) ?></p>
        </div>
      <?php endif; ?>
    </section>

  </div>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
