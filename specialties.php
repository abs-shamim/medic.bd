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

<style>
  :root {
    --medic-primary: #0969da;
    --medic-accent: #2da44e;
    --medic-bg: #f6f8fa;
    --medic-surface: #ffffff;
    --medic-surface-soft: #f6f8fa;
    --medic-border: #d8dee4;
    --medic-border-strong: #c9d1d9;
    --medic-text: #1f2328;
    --medic-muted: #656d76;
    --medic-radius: 12px;
    --medic-radius-card: 16px;
    --medic-shadow: 0 1px 2px rgba(15, 23, 32, .04);
    --medic-shadow-hover: 0 16px 32px -14px rgba(15, 23, 32, .22);
    --medic-gradient: linear-gradient(135deg, var(--medic-primary), var(--medic-accent));
  }

  .medic-specialties-page,
  .medic-specialties-page * {
    box-sizing: border-box;
    font-weight: 500 !important;
  }

  .medic-specialties-page {
    min-height: 100vh;
    padding: 22px 0 44px;
    background: var(--medic-bg);
    color: var(--medic-text);
  }

  .medic-specialties-page .container {
    width: min(100% - 32px, 1220px);
    margin: 0 auto;
  }

  [lang="bn"] .medic-specialties-page,
  .medic-specialties-page.is-bn {
    letter-spacing: 0;
  }

  .medic-breadcrumb {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 6px;
    margin: 0 0 13px;
    color: var(--medic-muted);
    font-size: 13px;
    line-height: 1.45;
  }

  .medic-breadcrumb span { color: #8c959f; }

  .medic-breadcrumb a {
    color: var(--medic-primary);
    text-decoration: none;
  }

  .medic-breadcrumb a:hover { text-decoration: underline; }

  /* Same clean heading treatment used by view.php. */
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
    color: var(--medic-text);
    font-size: clamp(18px, 4vw, 20px);
    font-weight: 500;
    line-height: 1.17;
    letter-spacing: -.028em;
  }

  .medic-page-head p {
    max-width: 790px;
    margin: 0;
    color: var(--medic-muted);
    font-size: 15px;
    line-height: 1.65;
  }

  /* Same premium search panel used by view.php. */
  .medic-search-box {
    margin-bottom: 22px;
    padding: 10px;
    border: 1px solid var(--medic-border);
    border-radius: var(--medic-radius-card);
    background: var(--medic-surface);
    box-shadow: var(--medic-shadow-hover);
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
    color: var(--medic-muted);
    pointer-events: none;
    transform: translateY(-50%);
  }

  .medic-search-row input[type="search"] {
    width: 100%;
    min-height: 42px;
    padding: 9px 12px 9px 39px;
    border: 1px solid var(--medic-border-strong);
    border-radius: 9px;
    outline: none;
    background: #ffffff;
    color: var(--medic-text);
    font: inherit;
    font-size: 14px;
    line-height: 20px;
    transition: border-color .16s ease, box-shadow .16s ease, background-color .16s ease;
  }

  .medic-search-row input::placeholder { color: #8c959f; }
  .medic-search-row input:hover { border-color: #8c959f; }

  .medic-search-row input:focus {
    border-color: var(--medic-primary);
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
    background: var(--medic-gradient);
    color: #ffffff;
    cursor: pointer;
    font-size: 14px;
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

  .medic-specialty-section { margin-top: 2px; }

  .medic-list-title {
    position: relative;
    margin: 0 0 12px;
    padding: 0 0 12px;
    border-bottom: 1px solid var(--medic-border);
    color: var(--medic-text);
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
    background: var(--medic-primary);
    content: '';
  }

  .medic-list-subtitle {
    margin: -5px 0 16px;
    color: var(--medic-muted);
    font-size: 14px;
    line-height: 1.58;
  }

  /*
  |---------------------------------------------------------------------------
  | Specialty Tiles
  |---------------------------------------------------------------------------
  | This is intentionally the same tile structure used by view.php:
  | image at the top, centered label at the bottom, and the same border,
  | radius, spacing, image height, shadow and hover interaction.
  */
  .medic-specialty-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(132px, 1fr));
    gap: 12px;
    margin: 16px 0 26px;
  }

  .medic-specialty-card {
    position: relative;
    display: flex;
    flex-direction: column;
    min-width: 0;
    min-height: 68px;
    overflow: hidden;
    padding: 3px;
    border: 1px solid var(--medic-border);
    border-radius: var(--medic-radius-card);
    background: var(--medic-surface);
    box-shadow: var(--medic-shadow);
    color: var(--medic-text);
    font-size: 15px;
    line-height: 1.35;
    text-decoration: none;
    transition: border-color .18s ease, box-shadow .18s ease, transform .18s ease;
  }

  .medic-specialty-media {
    display: block;
    width: 100%;
    height: 112px;
    overflow: hidden;
    border-radius: 8px;
    background: var(--medic-surface-soft);
  }

  .medic-specialty-media img {
    display: block;
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform .22s ease;
  }

  .medic-specialty-label {
    display: block;
    min-width: 0;
    padding: 9px 6px 8px;
    color: var(--medic-text);
    font-size: 14px;
    line-height: 1.38;
    letter-spacing: -.012em;
    text-align: center;
    word-break: normal;
    overflow-wrap: normal;
    hyphens: none;
  }

  .medic-specialty-card:not(.has-image) {
    align-items: center;
    justify-content: center;
    min-height: 68px;
    padding: 12px;
    color: var(--medic-primary);
    text-align: center;
  }

  .medic-specialty-card:not(.has-image) .medic-specialty-label {
    padding: 0;
    color: inherit;
  }

  .medic-specialty-card:hover,
  .medic-specialty-card:focus-visible {
    border-color: var(--medic-primary);
    box-shadow: var(--medic-shadow-hover);
    outline: none;
    transform: translateY(-2px);
  }

  .medic-specialty-card:hover .medic-specialty-media img,
  .medic-specialty-card:focus-visible .medic-specialty-media img {
    transform: scale(1.035);
  }

  .medic-empty-card {
    margin-top: 16px;
    padding: 28px;
    border: 1px solid var(--medic-border);
    border-radius: var(--medic-radius-card);
    background: var(--medic-surface);
    box-shadow: var(--medic-shadow);
    color: var(--medic-muted);
    text-align: center;
  }

  .medic-empty-card h2 {
    margin: 0 0 8px;
    color: var(--medic-text);
    font-size: 21px;
  }

  .medic-empty-card p {
    max-width: 560px;
    margin: 0 auto;
    line-height: 1.65;
  }

  @media (min-width: 1000px) {
    .medic-specialty-grid { grid-template-columns: repeat(auto-fill, minmax(132px, 1fr)); }
  }

  @media (max-width: 840px) {
    .medic-specialties-page { padding-top: 18px; }
    .medic-specialties-page .container { width: min(100% - 24px, 1220px); }
    .medic-specialty-grid { grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 10px; }
    .medic-specialty-media { height: 98px; }
  }

  /* On mobile, use the exact overlay tile treatment from view.php. */
  @media (max-width: 620px) {
    .medic-specialties-page { padding: 14px 0 36px; }
    .medic-specialties-page .container { width: min(100% - 20px, 1220px); }
    .medic-breadcrumb { margin-bottom: 10px; font-size: 12px; }
    .medic-page-head { margin-bottom: 16px; }
    .medic-page-head h1 { font-size: 20px; }
    .medic-page-head p { font-size: 14px; line-height: 1.58; }
    .medic-search-box { padding: 8px; border-radius: 11px; }
    .medic-search-row { grid-template-columns: 1fr; }
    .medic-search-row input[type="search"] { min-height: 44px; }
    .medic-btn { width: 100%; min-height: 43px; }
    .medic-list-title { margin-bottom: 12px; }
    .medic-list-subtitle { margin-bottom: 14px; font-size: 13px; }

    .medic-specialty-grid {
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 10px;
      margin-top: 14px;
      margin-bottom: 22px;
    }

    .medic-specialty-card {
      min-height: 0;
      padding: 0;
      border-radius: 12px;
    }

    .medic-specialty-card.has-image { aspect-ratio: 1.26 / 1; }

    .medic-specialty-card.has-image .medic-specialty-media {
      width: 100%;
      height: 100%;
      border-radius: inherit;
      background: #eaeef2;
    }

    .medic-specialty-card.has-image .medic-specialty-label {
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
      line-height: 1.25;
      text-align: left;
      text-shadow: 0 1px 2px rgba(0, 0, 0, .55);
    }

    .medic-specialty-card:not(.has-image) {
      min-height: 74px;
      padding: 10px;
      border-radius: 12px;
    }
  }

  @media (max-width: 370px) {
    .medic-specialty-card.has-image { aspect-ratio: 1.17 / 1; }
    .medic-specialty-card.has-image .medic-specialty-label {
      font-size: 13px;
      padding: 23px 8px 7px;
    }
  }

  @media (prefers-reduced-motion: reduce) {
    .medic-specialties-page *,
    .medic-specialties-page *::before,
    .medic-specialties-page *::after {
      scroll-behavior: auto !important;
      transition-duration: .01ms !important;
      animation-duration: .01ms !important;
    }
  }
</style>

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
