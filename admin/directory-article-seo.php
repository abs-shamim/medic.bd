<?php
require_once __DIR__ . '/../includes/functions.php';

require_admin();

/*
|--------------------------------------------------------------------------
| Directory Article SEO Settings
|--------------------------------------------------------------------------
| This dedicated page saves only SEO metadata. Article context, title,
| content and doctor-name-list controls remain on directory-article-form.php.
*/

if (!function_exists('dir_article_seo_table_exists')) {
    function dir_article_seo_table_exists(string $table): bool
    {
        global $pdo;

        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table");
            $stmt->execute([':table' => $table]);

            return (int)$stmt->fetchColumn() > 0;
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('dir_article_seo_column_exists')) {
    function dir_article_seo_column_exists(string $table, string $column): bool
    {
        global $pdo;

        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :column");
            $stmt->execute([':table' => $table, ':column' => $column]);

            return (int)$stmt->fetchColumn() > 0;
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('dir_article_seo_add_column_if_missing')) {
    function dir_article_seo_add_column_if_missing(string $table, string $column, string $definition): void
    {
        global $pdo;

        if (!dir_article_seo_table_exists($table) || dir_article_seo_column_exists($table, $column)) {
            return;
        }

        try {
            $pdo->exec("ALTER TABLE {$table} ADD COLUMN {$column} {$definition}");
        } catch (Throwable $e) {
            error_log('Directory article SEO column update failed: ' . $e->getMessage());
        }
    }
}

if (!function_exists('dir_article_seo_boot')) {
    function dir_article_seo_boot(): void
    {
        global $pdo;

        if (!dir_article_seo_table_exists('doctor_directory_articles')) {
            $pdo->exec("
                CREATE TABLE doctor_directory_articles (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    district_id INT DEFAULT 0,
                    thana_id INT DEFAULT 0,
                    specialty_id INT DEFAULT 0,
                    lang VARCHAR(10) DEFAULT 'en',
                    title VARCHAR(255) NULL,
                    intro TEXT NULL,
                    meta_title VARCHAR(255) NULL,
                    meta_description TEXT NULL,
                    meta_keywords TEXT NULL,
                    content LONGTEXT NULL,
                    show_doctor_list TINYINT(1) NOT NULL DEFAULT 1,
                    status VARCHAR(30) DEFAULT 'active',
                    created_at DATETIME NULL,
                    updated_at DATETIME NULL,
                    UNIQUE KEY unique_article_context (district_id, thana_id, specialty_id, lang),
                    INDEX article_context_idx (district_id, thana_id, specialty_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
        }

        dir_article_seo_add_column_if_missing('doctor_directory_articles', 'intro', "TEXT NULL");
        dir_article_seo_add_column_if_missing('doctor_directory_articles', 'meta_title', "VARCHAR(255) NULL");
        dir_article_seo_add_column_if_missing('doctor_directory_articles', 'meta_description', "TEXT NULL");
        dir_article_seo_add_column_if_missing('doctor_directory_articles', 'meta_keywords', "TEXT NULL");
        dir_article_seo_add_column_if_missing('doctor_directory_articles', 'show_doctor_list', "TINYINT(1) NOT NULL DEFAULT 1");
    }
}

if (!function_exists('dir_article_seo_context_by_id')) {
    function dir_article_seo_context_by_id(int $article_id): array
    {
        global $pdo;

        if ($article_id <= 0) {
            return [];
        }

        try {
            $stmt = $pdo->prepare("SELECT district_id, thana_id, specialty_id FROM doctor_directory_articles WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => $article_id]);

            return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }
}

if (!function_exists('dir_article_seo_load')) {
    function dir_article_seo_load(array $context): array
    {
        global $pdo;

        $default = [
            'en' => ['meta_title' => '', 'meta_description' => '', 'meta_keywords' => ''],
            'bn' => ['meta_title' => '', 'meta_description' => '', 'meta_keywords' => ''],
        ];

        if (empty($context)) {
            return $default;
        }

        try {
            $stmt = $pdo->prepare("
                SELECT lang, meta_title, meta_description, meta_keywords
                FROM doctor_directory_articles
                WHERE district_id = :district_id
                  AND thana_id = :thana_id
                  AND specialty_id = :specialty_id
                  AND lang IN ('en', 'bn')
            ");
            $stmt->execute([
                ':district_id' => (int)$context['district_id'],
                ':thana_id' => (int)$context['thana_id'],
                ':specialty_id' => (int)$context['specialty_id'],
            ]);

            foreach (($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) as $row) {
                $lang = (string)($row['lang'] ?? '');
                if (isset($default[$lang])) {
                    $default[$lang] = [
                        'meta_title' => (string)($row['meta_title'] ?? ''),
                        'meta_description' => (string)($row['meta_description'] ?? ''),
                        'meta_keywords' => (string)($row['meta_keywords'] ?? ''),
                    ];
                }
            }
        } catch (Throwable $e) {
            error_log('Directory article SEO read failed: ' . $e->getMessage());
        }

        return $default;
    }
}

if (!function_exists('dir_article_seo_name_column')) {
    function dir_article_seo_name_column(string $table): string
    {
        if (dir_article_seo_column_exists($table, 'name_en')) {
            return 'name_en';
        }
        if (dir_article_seo_column_exists($table, 'name')) {
            return 'name';
        }
        if (dir_article_seo_column_exists($table, 'title')) {
            return 'title';
        }

        return '';
    }
}

if (!function_exists('dir_article_seo_context_name')) {
    function dir_article_seo_context_name(string $table, int $id, string $fallback): string
    {
        global $pdo;

        $nameColumn = dir_article_seo_name_column($table);
        if ($id <= 0 || $nameColumn === '' || !dir_article_seo_table_exists($table)) {
            return $fallback;
        }

        try {
            $bnColumn = dir_article_seo_column_exists($table, 'name_bn') ? ', name_bn' : '';
            $stmt = $pdo->prepare("SELECT {$nameColumn} AS name {$bnColumn} FROM {$table} WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => $id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

            $name = trim((string)($row['name'] ?? ''));
            $bn = trim((string)($row['name_bn'] ?? ''));

            if ($name !== '' && $bn !== '') {
                return $name . ' / ' . $bn;
            }

            return $name !== '' ? $name : ($bn !== '' ? $bn : $fallback);
        } catch (Throwable $e) {
            return $fallback;
        }
    }
}

if (!function_exists('dir_article_seo_save_language')) {
    function dir_article_seo_save_language(array $context, string $lang, array $data): void
    {
        global $pdo;

        $metaTitle = trim((string)($data['meta_title'] ?? ''));
        $metaDescription = trim((string)($data['meta_description'] ?? ''));
        $metaKeywords = trim((string)($data['meta_keywords'] ?? ''));

        $stmt = $pdo->prepare("
            INSERT INTO doctor_directory_articles
            (
                district_id,
                thana_id,
                specialty_id,
                lang,
                title,
                intro,
                meta_title,
                meta_description,
                meta_keywords,
                content,
                show_doctor_list,
                status,
                created_at,
                updated_at
            )
            VALUES
            (
                :district_id,
                :thana_id,
                :specialty_id,
                :lang,
                NULL,
                NULL,
                :meta_title,
                :meta_description,
                :meta_keywords,
                NULL,
                1,
                'active',
                NOW(),
                NOW()
            )
            ON DUPLICATE KEY UPDATE
                meta_title = VALUES(meta_title),
                meta_description = VALUES(meta_description),
                meta_keywords = VALUES(meta_keywords),
                updated_at = NOW()
        ");

        $stmt->execute([
            ':district_id' => (int)$context['district_id'],
            ':thana_id' => (int)$context['thana_id'],
            ':specialty_id' => (int)$context['specialty_id'],
            ':lang' => $lang,
            ':meta_title' => $metaTitle === '' ? null : $metaTitle,
            ':meta_description' => $metaDescription === '' ? null : $metaDescription,
            ':meta_keywords' => $metaKeywords === '' ? null : $metaKeywords,
        ]);
    }
}

dir_article_seo_boot();

if (empty($_SESSION['directory_article_seo_csrf'])) {
    $_SESSION['directory_article_seo_csrf'] = bin2hex(random_bytes(32));
}

$articleId = max(0, (int)($_GET['edit'] ?? $_POST['article_id'] ?? 0));
$context = dir_article_seo_context_by_id($articleId);

if (empty($context)) {
    $_SESSION['flash_error'] = 'Please save the main article details before opening SEO Settings.';
    header('Location: directory-article-form.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = (string)($_POST['csrf_token'] ?? '');

    if (!hash_equals($_SESSION['directory_article_seo_csrf'], $csrf)) {
        $_SESSION['flash_error'] = 'Invalid security token. Please try again.';
        header('Location: directory-article-seo.php?edit=' . $articleId);
        exit;
    }

    $en = [
        'meta_title' => trim((string)($_POST['meta_title_en'] ?? '')),
        'meta_description' => trim((string)($_POST['meta_description_en'] ?? '')),
        'meta_keywords' => trim((string)($_POST['meta_keywords_en'] ?? '')),
    ];
    $bn = [
        'meta_title' => trim((string)($_POST['meta_title_bn'] ?? '')),
        'meta_description' => trim((string)($_POST['meta_description_bn'] ?? '')),
        'meta_keywords' => trim((string)($_POST['meta_keywords_bn'] ?? '')),
    ];

    $enLength = function_exists('mb_strlen') ? mb_strlen($en['meta_title'], 'UTF-8') : strlen($en['meta_title']);
    $bnLength = function_exists('mb_strlen') ? mb_strlen($bn['meta_title'], 'UTF-8') : strlen($bn['meta_title']);

    if ($enLength > 255 || $bnLength > 255) {
        $_SESSION['flash_error'] = 'Meta titles must be 255 characters or fewer.';
        header('Location: directory-article-seo.php?edit=' . $articleId);
        exit;
    }

    try {
        dir_article_seo_save_language($context, 'en', $en);
        dir_article_seo_save_language($context, 'bn', $bn);

        header('Location: directory-article-form.php?edit=' . $articleId . '&seo_saved=1');
        exit;
    } catch (Throwable $e) {
        error_log('Directory article SEO save failed: ' . $e->getMessage());
        $_SESSION['flash_error'] = 'SEO settings could not be saved. Please try again.';
        header('Location: directory-article-seo.php?edit=' . $articleId);
        exit;
    }
}

$seo = dir_article_seo_load($context);
$districtLabel = dir_article_seo_context_name('districts', (int)$context['district_id'], 'Selected District');
$thanaLabel = (int)$context['thana_id'] > 0
    ? dir_article_seo_context_name('thanas', (int)$context['thana_id'], 'Selected Area')
    : 'District-level article';
$specialtyLabel = dir_article_seo_context_name('specialties', (int)$context['specialty_id'], 'Selected Specialty');

require_once __DIR__ . '/includes/header.php';
?>

<style>
  :root {
    --dseo-canvas: #f6f8fa;
    --dseo-surface: #ffffff;
    --dseo-subtle: #f6f8fa;
    --dseo-text: #1f2328;
    --dseo-muted: #57606a;
    --dseo-border: #d0d7de;
    --dseo-blue: #0969da;
    --dseo-blue-strong: #0550ae;
    --dseo-blue-soft: #ddf4ff;
    --dseo-green: #1f883d;
    --dseo-green-soft: #dafbe1;
    --dseo-red: #cf222e;
    --dseo-red-soft: #ffebe9;
    --dseo-radius: 8px;
  }

  body { background: var(--dseo-canvas); }

  .dseo-page,
  .dseo-page * { box-sizing: border-box; }

  .dseo-page {
    max-width: 1120px;
    margin: 0 auto;
    padding: 20px 16px 34px;
    color: var(--dseo-text);
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Helvetica, Arial, sans-serif;
  }

  .dseo-top,
  .dseo-card,
  .dseo-context {
    border: 1px solid var(--dseo-border);
    border-radius: var(--dseo-radius);
    background: var(--dseo-surface);
    box-shadow: 0 1px 0 rgba(31, 35, 40, .04);
  }

  .dseo-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    margin-bottom: 16px;
    padding: 16px 18px;
  }

  .dseo-title h1 {
    display: flex;
    align-items: center;
    gap: 9px;
    margin: 0;
    color: var(--dseo-text);
    font-size: 20px;
    line-height: 1.25;
    font-weight: 650;
    letter-spacing: -.01em;
  }

  .dseo-title h1::before {
    content: "⌕";
    display: grid;
    width: 26px;
    height: 26px;
    place-items: center;
    border: 1px solid #b6e3ff;
    border-radius: 6px;
    color: var(--dseo-blue);
    background: var(--dseo-blue-soft);
    font-size: 16px;
    font-weight: 700;
  }

  .dseo-title p {
    margin: 5px 0 0 35px;
    color: var(--dseo-muted);
    font-size: 13px;
    line-height: 1.5;
  }

  .dseo-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 32px;
    padding: 5px 12px;
    border: 1px solid rgba(31, 35, 40, .15);
    border-radius: 6px;
    background: #f6f8fa;
    color: var(--dseo-text);
    box-shadow: 0 1px 0 rgba(31, 35, 40, .04);
    cursor: pointer;
    font-size: 13px;
    font-weight: 600;
    line-height: 20px;
    text-decoration: none;
  }

  .dseo-btn:hover { background: #f3f4f6; border-color: rgba(31, 35, 40, .25); }
  .dseo-btn:focus-visible,
  .dseo-input:focus-visible { outline: 2px solid var(--dseo-blue); outline-offset: 2px; }

  .dseo-btn-primary {
    border-color: rgba(31, 35, 40, .15);
    color: #ffffff;
    background: var(--dseo-green);
  }

  .dseo-btn-primary:hover { color: #ffffff; background: #1a7f37; }

  .dseo-alert {
    margin-bottom: 16px;
    padding: 11px 13px;
    border: 1px solid #ff8182;
    border-radius: 6px;
    color: #cf222e;
    background: var(--dseo-red-soft);
    font-size: 13px;
    line-height: 1.5;
  }

  .dseo-context {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 0;
    overflow: hidden;
    margin-bottom: 16px;
  }

  .dseo-context-item {
    min-width: 0;
    padding: 13px 15px;
    border-right: 1px solid var(--dseo-border);
  }

  .dseo-context-item:last-child { border-right: 0; }
  .dseo-context-item span { display: block; margin-bottom: 4px; color: var(--dseo-muted); font-size: 11px; font-weight: 650; letter-spacing: .04em; text-transform: uppercase; }
  .dseo-context-item strong { display: block; overflow: hidden; color: var(--dseo-text); font-size: 13px; font-weight: 600; text-overflow: ellipsis; white-space: nowrap; }

  .dseo-card { overflow: hidden; }

  .dseo-hero {
    padding: 19px 20px;
    border-bottom: 1px solid #1b1f24;
    color: #f0f6fc;
    background: #24292f;
  }

  .dseo-hero span {
    display: inline-flex;
    min-height: 24px;
    align-items: center;
    margin-bottom: 7px;
    padding: 2px 8px;
    border: 1px solid rgba(255, 255, 255, .2);
    border-radius: 999px;
    color: #f0f6fc;
    background: rgba(255, 255, 255, .08);
    font-size: 11px;
    font-weight: 650;
  }

  .dseo-hero h2 { margin: 0; color: #ffffff; font-size: 20px; line-height: 1.3; font-weight: 650; }
  .dseo-hero p { margin: 6px 0 0; color: #8b949e; font-size: 13px; line-height: 1.55; }

  .dseo-body { padding: 16px; }

  .dseo-section {
    margin-bottom: 16px;
    overflow: hidden;
    border: 1px solid var(--dseo-border);
    border-radius: 6px;
    background: var(--dseo-surface);
  }

  .dseo-section:last-of-type { margin-bottom: 0; }

  .dseo-section-head {
    display: flex;
    align-items: center;
    gap: 9px;
    padding: 11px 13px;
    border-bottom: 1px solid var(--dseo-border);
    background: var(--dseo-subtle);
  }

  .dseo-section-number {
    display: grid;
    width: 24px;
    height: 24px;
    place-items: center;
    border: 1px solid #b6e3ff;
    border-radius: 50%;
    color: var(--dseo-blue);
    background: var(--dseo-blue-soft);
    font-size: 11px;
    font-weight: 650;
  }

  .dseo-section-head h3 { margin: 0; color: var(--dseo-text); font-size: 14px; line-height: 1.4; font-weight: 650; }
  .dseo-section-head p { margin: 1px 0 0; color: var(--dseo-muted); font-size: 12px; line-height: 1.45; }

  .dseo-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; padding: 16px; }

  .dseo-language {
    overflow: hidden;
    border: 1px solid var(--dseo-border);
    border-radius: 6px;
    background: #ffffff;
  }

  .dseo-language-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 10px 12px;
    border-bottom: 1px solid var(--dseo-border);
    background: #f6f8fa;
  }

  .dseo-language-head strong { color: var(--dseo-text); font-size: 14px; font-weight: 650; }
  .dseo-language-head span { padding: 2px 7px; border: 1px solid #b6e3ff; border-radius: 999px; color: var(--dseo-blue); background: var(--dseo-blue-soft); font-size: 11px; font-weight: 650; }

  .dseo-fields { display: grid; gap: 14px; padding: 14px; }
  .dseo-field { display: grid; gap: 6px; }
  .dseo-field label { color: var(--dseo-text); font-size: 13px; font-weight: 650; }
  .dseo-field small { color: var(--dseo-muted); font-size: 11px; line-height: 1.45; }

  .dseo-input {
    width: 100%;
    min-height: 32px;
    padding: 5px 10px;
    border: 1px solid var(--dseo-border);
    border-radius: 6px;
    color: var(--dseo-text);
    background: #ffffff;
    box-shadow: inset 0 1px 0 rgba(208, 215, 222, .2);
    font: inherit;
    font-size: 13px;
    line-height: 20px;
    outline: none;
  }

  textarea.dseo-input { min-height: 132px; resize: vertical; line-height: 1.55; }
  .dseo-input:focus { border-color: var(--dseo-blue); box-shadow: 0 0 0 3px rgba(9, 105, 218, .12); }

  .dseo-metrics { display: flex; align-items: center; justify-content: space-between; gap: 8px; color: var(--dseo-muted); font-size: 11px; }
  .dseo-metrics b { padding: 2px 6px; border: 1px solid var(--dseo-border); border-radius: 999px; color: var(--dseo-muted); background: var(--dseo-subtle); font-weight: 650; }
  .dseo-metrics b.good { border-color: #a5d6b5; color: #1a7f37; background: var(--dseo-green-soft); }
  .dseo-metrics b.attention { border-color: #d4a72c; color: #9a6700; background: #fff8c5; }

  .dseo-actions { display: flex; flex-wrap: wrap; gap: 8px; padding-top: 16px; }

  @media (max-width: 760px) {
    .dseo-top { align-items: flex-start; flex-direction: column; }
    .dseo-top .dseo-btn { width: 100%; }
    .dseo-context { grid-template-columns: 1fr; }
    .dseo-context-item { border-right: 0; border-bottom: 1px solid var(--dseo-border); }
    .dseo-context-item:last-child { border-bottom: 0; }
    .dseo-grid { grid-template-columns: 1fr; }
    .dseo-actions, .dseo-actions .dseo-btn { width: 100%; }
  }
</style>

<div class="dseo-page">
    <div class="dseo-top">
        <div class="dseo-title">
            <h1>Directory Article SEO Settings</h1>
            <p>Save SEO information here, then return directly to the main article form to complete any remaining changes.</p>
        </div>
        <a href="directory-article-form.php?edit=<?= e((string)$articleId) ?>" class="dseo-btn">← Back to Article</a>
    </div>

    <?php if (!empty($_SESSION['flash_error'])): ?>
        <div class="dseo-alert"><?= e($_SESSION['flash_error']) ?></div>
        <?php unset($_SESSION['flash_error']); ?>
    <?php endif; ?>

    <div class="dseo-context">
        <div class="dseo-context-item"><span>District</span><strong title="<?= e($districtLabel) ?>"><?= e($districtLabel) ?></strong></div>
        <div class="dseo-context-item"><span>Thana / Area</span><strong title="<?= e($thanaLabel) ?>"><?= e($thanaLabel) ?></strong></div>
        <div class="dseo-context-item"><span>Specialty</span><strong title="<?= e($specialtyLabel) ?>"><?= e($specialtyLabel) ?></strong></div>
    </div>

    <main class="dseo-card">
        <div class="dseo-hero">
            <span>Separate SEO Workspace</span>
            <h2>Search metadata for English and Bangla</h2>
            <p>These fields affect the public page’s metadata. They do not change the visible article title or Content HTML.</p>
        </div>

        <div class="dseo-body">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= e($_SESSION['directory_article_seo_csrf']) ?>">
                <input type="hidden" name="article_id" value="<?= e((string)$articleId) ?>">

                <section class="dseo-section">
                    <div class="dseo-section-head">
                        <span class="dseo-section-number">01</span>
                        <div>
                            <h3>SEO Metadata</h3>
                            <p>Use language-specific metadata for the matching public English and Bangla directory pages.</p>
                        </div>
                    </div>

                    <div class="dseo-grid">
                        <div class="dseo-language">
                            <div class="dseo-language-head"><strong>English SEO</strong><span>EN</span></div>
                            <div class="dseo-fields">
                                <div class="dseo-field">
                                    <label for="metaTitleEn">Meta Title</label>
                                    <input class="dseo-input" id="metaTitleEn" type="text" name="meta_title_en" maxlength="255" value="<?= e($seo['en']['meta_title']) ?>" placeholder="Example: Best Cardiology Doctors in Dhaka">
                                    <div class="dseo-metrics"><span>Recommended: 50–60 characters.</span><b id="metaTitleEnStatus">0 / 60</b></div>
                                </div>
                                <div class="dseo-field">
                                    <label for="metaDescriptionEn">Meta Description</label>
                                    <textarea class="dseo-input" id="metaDescriptionEn" name="meta_description_en" placeholder="Write a concise, useful English search description."><?= e($seo['en']['meta_description']) ?></textarea>
                                    <div class="dseo-metrics"><span>Recommended: 140–160 characters.</span><b id="metaDescriptionEnStatus">0 / 160</b></div>
                                </div>
                                <div class="dseo-field">
                                    <label for="metaKeywordsEn">Meta Keywords</label>
                                    <input class="dseo-input" id="metaKeywordsEn" type="text" name="meta_keywords_en" value="<?= e($seo['en']['meta_keywords']) ?>" placeholder="cardiologist in dhaka, heart specialist">
                                    <small>Separate focused phrases with commas.</small>
                                </div>
                            </div>
                        </div>

                        <div class="dseo-language">
                            <div class="dseo-language-head"><strong>Bangla SEO</strong><span>BN</span></div>
                            <div class="dseo-fields">
                                <div class="dseo-field">
                                    <label for="metaTitleBn">Meta Title Bangla</label>
                                    <input class="dseo-input" id="metaTitleBn" type="text" name="meta_title_bn" maxlength="255" value="<?= e($seo['bn']['meta_title']) ?>" placeholder="বাংলা SEO title লিখুন">
                                    <div class="dseo-metrics"><span>Recommended: concise and descriptive.</span><b id="metaTitleBnStatus">0 / 60</b></div>
                                </div>
                                <div class="dseo-field">
                                    <label for="metaDescriptionBn">Meta Description Bangla</label>
                                    <textarea class="dseo-input" id="metaDescriptionBn" name="meta_description_bn" placeholder="বাংলা search description লিখুন।"><?= e($seo['bn']['meta_description']) ?></textarea>
                                    <div class="dseo-metrics"><span>Recommended: 140–160 characters.</span><b id="metaDescriptionBnStatus">0 / 160</b></div>
                                </div>
                                <div class="dseo-field">
                                    <label for="metaKeywordsBn">Meta Keywords Bangla</label>
                                    <input class="dseo-input" id="metaKeywordsBn" type="text" name="meta_keywords_bn" value="<?= e($seo['bn']['meta_keywords']) ?>" placeholder="ঢাকার কার্ডিওলজিস্ট, হৃদরোগ বিশেষজ্ঞ">
                                    <small>প্রাসঙ্গিক keyword কমা দিয়ে আলাদা করুন।</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <div class="dseo-actions">
                    <button type="submit" class="dseo-btn dseo-btn-primary">Save SEO &amp; Return to Article</button>
                    <a href="directory-article-form.php?edit=<?= e((string)$articleId) ?>" class="dseo-btn">Cancel</a>
                </div>
            </form>
        </div>
    </main>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const counters = [
        ['metaTitleEn', 'metaTitleEnStatus', 60, 45, 60],
        ['metaDescriptionEn', 'metaDescriptionEnStatus', 160, 130, 160],
        ['metaTitleBn', 'metaTitleBnStatus', 60, 45, 60],
        ['metaDescriptionBn', 'metaDescriptionBnStatus', 160, 130, 160]
    ];

    counters.forEach(function (item) {
        const field = document.getElementById(item[0]);
        const status = document.getElementById(item[1]);
        if (!field || !status) return;

        function update() {
            const count = Array.from(field.value || '').length;
            status.textContent = count + ' / ' + item[2];
            status.classList.toggle('good', count >= item[3] && count <= item[4]);
            status.classList.toggle('attention', count > 0 && (count < item[3] || count > item[4]));
        }

        field.addEventListener('input', update);
        update();
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
