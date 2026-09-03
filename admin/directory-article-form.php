<?php
require_once __DIR__ . '/../includes/functions.php';

require_admin();

/*
|--------------------------------------------------------------------------
| Directory Article Form - Clean EN/BN Design
|--------------------------------------------------------------------------
| - English left, Bangla right
| - District + Specialty required
| - Thana optional
| - Division is only used to filter district
| - Saves EN and BN article together
| - Main page manages context, visible article content and doctor-name list
| - SEO fields are managed on a dedicated page and return here after saving
*/

if (!function_exists('dir_article_form_table_exists')) {
    function dir_article_form_table_exists(string $table): bool
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

if (!function_exists('dir_article_form_column_exists')) {
    function dir_article_form_column_exists(string $table, string $column): bool
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

if (!function_exists('dir_article_form_add_column_if_missing')) {
    function dir_article_form_add_column_if_missing(string $table, string $column, string $definition): void
    {
        global $pdo;

        if (!dir_article_form_table_exists($table)) {
            return;
        }

        if (!dir_article_form_column_exists($table, $column)) {
            $pdo->exec("ALTER TABLE {$table} ADD COLUMN {$column} {$definition}");
        }
    }
}

if (!function_exists('dir_article_form_boot')) {
    function dir_article_form_boot(): void
    {
        global $pdo;

        if (!dir_article_form_table_exists('doctor_directory_articles')) {
            $pdo->exec("
                CREATE TABLE doctor_directory_articles (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    district_id INT DEFAULT 0,
                    thana_id INT DEFAULT 0,
                    specialty_id INT DEFAULT 0,
                    lang VARCHAR(10) DEFAULT 'en',
                    title VARCHAR(255) NULL,
                    meta_title VARCHAR(255) NULL,
                    meta_description TEXT NULL,
                    meta_keywords TEXT NULL,
                    content LONGTEXT NULL,
                    show_doctor_list TINYINT(1) NOT NULL DEFAULT 1,
                    status VARCHAR(30) DEFAULT 'active',
                    created_at DATETIME NULL,
                    updated_at DATETIME NULL,
                    UNIQUE KEY unique_article_context (district_id, thana_id, specialty_id, lang),
                    INDEX district_id_idx (district_id),
                    INDEX thana_id_idx (thana_id),
                    INDEX specialty_id_idx (specialty_id),
                    INDEX status_idx (status)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
        }

        dir_article_form_add_column_if_missing('doctor_directory_articles', 'district_id', "INT DEFAULT 0");
        dir_article_form_add_column_if_missing('doctor_directory_articles', 'thana_id', "INT DEFAULT 0");
        dir_article_form_add_column_if_missing('doctor_directory_articles', 'specialty_id', "INT DEFAULT 0");
        dir_article_form_add_column_if_missing('doctor_directory_articles', 'lang', "VARCHAR(10) DEFAULT 'en'");
        dir_article_form_add_column_if_missing('doctor_directory_articles', 'title', "VARCHAR(255) NULL");
        // Keep the old intro column for legacy frontend compatibility only.
        dir_article_form_add_column_if_missing('doctor_directory_articles', 'intro', "TEXT NULL");
        dir_article_form_add_column_if_missing('doctor_directory_articles', 'meta_title', "VARCHAR(255) NULL");
        dir_article_form_add_column_if_missing('doctor_directory_articles', 'meta_description', "TEXT NULL");
        dir_article_form_add_column_if_missing('doctor_directory_articles', 'meta_keywords', "TEXT NULL");
        dir_article_form_add_column_if_missing('doctor_directory_articles', 'content', "LONGTEXT NULL");
        dir_article_form_add_column_if_missing('doctor_directory_articles', 'show_doctor_list', "TINYINT(1) NOT NULL DEFAULT 1");
        dir_article_form_add_column_if_missing('doctor_directory_articles', 'status', "VARCHAR(30) DEFAULT 'active'");
        dir_article_form_add_column_if_missing('doctor_directory_articles', 'created_at', "DATETIME NULL");
        dir_article_form_add_column_if_missing('doctor_directory_articles', 'updated_at', "DATETIME NULL");
    }
}

if (!function_exists('dir_article_form_redirect')) {
    function dir_article_form_redirect(array $params = []): void
    {
        $url = 'directory-article-form.php';

        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }

        header('Location: ' . $url);
        exit;
    }
}

if (!function_exists('dir_article_form_name_column')) {
    function dir_article_form_name_column(string $table, string $lang = 'en'): string
    {
        if ($lang === 'bn' && dir_article_form_column_exists($table, 'name_bn')) {
            return 'name_bn';
        }

        if (dir_article_form_column_exists($table, 'name_en')) {
            return 'name_en';
        }

        if (dir_article_form_column_exists($table, 'name')) {
            return 'name';
        }

        if (dir_article_form_column_exists($table, 'title')) {
            return 'title';
        }

        return 'name_en';
    }
}

if (!function_exists('dir_article_form_get_items')) {
    function dir_article_form_get_items(string $table): array
    {
        global $pdo;

        if (!dir_article_form_table_exists($table) || !dir_article_form_column_exists($table, 'id')) {
            return [];
        }

        $name_column = dir_article_form_name_column($table, 'en');
        $bn_column = dir_article_form_column_exists($table, 'name_bn') ? 'name_bn' : "''";

        if (!dir_article_form_column_exists($table, $name_column)) {
            return [];
        }

        try {
            $status_sql = dir_article_form_column_exists($table, 'status') ? "WHERE status = 'active'" : '';
            $stmt = $pdo->query("SELECT id, {$name_column} AS name, {$bn_column} AS name_bn FROM {$table} {$status_sql} ORDER BY {$name_column} ASC");

            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }
}

if (!function_exists('dir_article_form_get_divisions')) {
    function dir_article_form_get_divisions(): array
    {
        return dir_article_form_get_items('divisions');
    }
}

if (!function_exists('dir_article_form_get_districts')) {
    function dir_article_form_get_districts(): array
    {
        global $pdo;

        if (!dir_article_form_table_exists('districts')) {
            return [];
        }

        $name_column = dir_article_form_name_column('districts', 'en');
        $bn_column = dir_article_form_column_exists('districts', 'name_bn') ? 'name_bn' : "''";
        $select = "id, {$name_column} AS name, {$bn_column} AS name_bn";
        $select .= dir_article_form_column_exists('districts', 'division_id') ? ', division_id' : ', 0 AS division_id';

        try {
            $stmt = $pdo->query("SELECT {$select} FROM districts ORDER BY {$name_column} ASC");

            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }
}

if (!function_exists('dir_article_form_get_thanas')) {
    function dir_article_form_get_thanas(): array
    {
        global $pdo;

        if (!dir_article_form_table_exists('thanas')) {
            return [];
        }

        $name_column = dir_article_form_name_column('thanas', 'en');
        $bn_column = dir_article_form_column_exists('thanas', 'name_bn') ? 'name_bn' : "''";
        $select = "id, {$name_column} AS name, {$bn_column} AS name_bn";
        $select .= dir_article_form_column_exists('thanas', 'district_id') ? ', district_id' : ', 0 AS district_id';

        try {
            $stmt = $pdo->query("SELECT {$select} FROM thanas ORDER BY {$name_column} ASC");

            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }
}

if (!function_exists('dir_article_form_get_specialties')) {
    function dir_article_form_get_specialties(): array
    {
        global $pdo;

        if (!dir_article_form_table_exists('specialties')) {
            return [];
        }

        $name_column = dir_article_form_name_column('specialties', 'en');
        $bn_column = dir_article_form_column_exists('specialties', 'name_bn') ? 'name_bn' : "''";

        try {
            $status_sql = dir_article_form_column_exists('specialties', 'status') ? "WHERE status = 'active'" : '';
            $stmt = $pdo->query("SELECT id, {$name_column} AS name, {$bn_column} AS name_bn FROM specialties {$status_sql} ORDER BY {$name_column} ASC");

            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            if (function_exists('get_specialties')) {
                try {
                    $items = get_specialties();

                    if (is_array($items)) {
                        return array_values(array_filter($items, static function ($item) {
                            return !empty($item['id']) && !empty($item['name']);
                        }));
                    }
                } catch (Throwable $e) {
                    return [];
                }
            }

            return [];
        }
    }
}

if (!function_exists('dir_article_form_context_by_id')) {
    function dir_article_form_context_by_id(int $article_id): array
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

if (!function_exists('dir_article_form_load_context_articles')) {
    function dir_article_form_load_context_articles(array $context): array
    {
        global $pdo;

        $articles = [
            'en' => [
                'id' => 0,
                'title' => '',
                'meta_title' => '',
                'meta_description' => '',
                'meta_keywords' => '',
                'content' => '',
                'show_doctor_list' => 1,
                'status' => 'active',
            ],
            'bn' => [
                'id' => 0,
                'title' => '',
                'meta_title' => '',
                'meta_description' => '',
                'meta_keywords' => '',
                'content' => '',
                'show_doctor_list' => 1,
                'status' => 'active',
            ],
        ];

        if (empty($context)) {
            return $articles;
        }

        try {
            $stmt = $pdo->prepare("
                SELECT *
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

                if (isset($articles[$lang])) {
                    $articles[$lang] = [
                        'id' => (int)($row['id'] ?? 0),
                        'title' => (string)($row['title'] ?? ''),
                        'meta_title' => (string)($row['meta_title'] ?? ''),
                        'meta_description' => (string)($row['meta_description'] ?? ''),
                        'meta_keywords' => (string)($row['meta_keywords'] ?? ''),
                        'content' => (string)($row['content'] ?? ''),
                        // Existing articles keep the original visible behaviour when this column is absent or NULL.
                        'show_doctor_list' => array_key_exists('show_doctor_list', $row) ? (int)$row['show_doctor_list'] : 1,
                        'status' => (string)($row['status'] ?? 'active'),
                    ];
                }
            }
        } catch (Throwable $e) {
            return $articles;
        }

        return $articles;
    }
}

/*
|--------------------------------------------------------------------------
| Duplicate Context Finder
|--------------------------------------------------------------------------
| Each district + thana + specialty is one bilingual article context.
| This returns the existing article ID so the admin can open it directly.
*/
if (!function_exists('dir_article_form_find_existing_context_id')) {
    function dir_article_form_find_existing_context_id(
        int $district_id,
        int $thana_id,
        int $specialty_id,
        int $current_context_id = 0
    ): int {
        global $pdo;

        try {
            $sql = "
                SELECT MIN(id)
                FROM doctor_directory_articles
                WHERE district_id = :district_id
                  AND thana_id = :thana_id
                  AND specialty_id = :specialty_id
            ";

            $params = [
                ':district_id' => $district_id,
                ':thana_id' => $thana_id,
                ':specialty_id' => $specialty_id,
            ];

            /*
             * When editing the same context, it is not a duplicate. When the
             * admin changes the location/specialty, find any other context.
             */
            if ($current_context_id > 0) {
                $currentContext = dir_article_form_context_by_id($current_context_id);

                if (!empty($currentContext)) {
                    $sameContext =
                        (int)$currentContext['district_id'] === $district_id
                        && (int)$currentContext['thana_id'] === $thana_id
                        && (int)$currentContext['specialty_id'] === $specialty_id;

                    if ($sameContext) {
                        return 0;
                    }
                }
            }

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            return (int)$stmt->fetchColumn();
        } catch (Throwable $e) {
            error_log('Directory article duplicate lookup error: ' . $e->getMessage());
            return 0;
        }
    }
}

if (!function_exists('dir_article_form_save_lang')) {
    function dir_article_form_save_lang(int $district_id, int $thana_id, int $specialty_id, string $lang, array $data): void
    {
        global $pdo;

        /*
        |--------------------------------------------------------------------------
        | Convert Empty Fields To Database NULL
        |--------------------------------------------------------------------------
        | The old intro field is no longer editable. SEO title, description and
        | keywords are stored in dedicated columns, while title stays available
        | for the visible article heading.
        */
        $title_input = trim((string)($data['title'] ?? ''));
        $meta_title_input = trim((string)($data['meta_title'] ?? ''));
        $meta_description_input = trim((string)($data['meta_description'] ?? ''));
        $meta_keywords_input = trim((string)($data['meta_keywords'] ?? ''));
        $content_input = trim((string)($data['content'] ?? ''));

        $title = $title_input === '' ? null : $title_input;
        $meta_title = $meta_title_input === '' ? null : $meta_title_input;
        $meta_description = $meta_description_input === '' ? null : $meta_description_input;
        $meta_keywords = $meta_keywords_input === '' ? null : $meta_keywords_input;
        $content = $content_input === '' ? null : $content_input;

        /*
         * The main article page does not overwrite SEO metadata. The separate
         * SEO page explicitly sends save_seo = 1, while this page preserves
         * existing SEO values on update and saves NULL for a new context.
         */
        $save_seo = !empty($data['save_seo']);
        $show_doctor_list = (int)($data['show_doctor_list'] ?? 1) === 1 ? 1 : 0;
        $status = trim((string)($data['status'] ?? 'active'));
        $status = in_array($status, ['active', 'inactive', 'pending'], true) ? $status : 'active';

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
                :title,
                NULL,
                :meta_title,
                :meta_description,
                :meta_keywords,
                :content,
                :show_doctor_list,
                :status,
                NOW(),
                NOW()
            )
            ON DUPLICATE KEY UPDATE
                title = VALUES(title),
                intro = NULL,
                meta_title = IF(:update_meta_title = 1, VALUES(meta_title), meta_title),
                meta_description = IF(:update_meta_description = 1, VALUES(meta_description), meta_description),
                meta_keywords = IF(:update_meta_keywords = 1, VALUES(meta_keywords), meta_keywords),
                content = VALUES(content),
                show_doctor_list = VALUES(show_doctor_list),
                status = VALUES(status),
                updated_at = NOW()
        ");

        $stmt->execute([
            ':district_id' => $district_id,
            ':thana_id' => $thana_id,
            ':specialty_id' => $specialty_id,
            ':lang' => $lang,
            ':title' => $title,
            ':meta_title' => $meta_title,
            ':meta_description' => $meta_description,
            ':meta_keywords' => $meta_keywords,
            ':content' => $content,
            ':update_meta_title' => $save_seo ? 1 : 0,
            ':update_meta_description' => $save_seo ? 1 : 0,
            ':update_meta_keywords' => $save_seo ? 1 : 0,
            ':show_doctor_list' => $show_doctor_list,
            ':status' => $status,
        ]);
    }
}

dir_article_form_boot();

if (empty($_SESSION['directory_article_csrf'])) {
    $_SESSION['directory_article_csrf'] = bin2hex(random_bytes(32));
}

$edit_id = max(0, (int)($_GET['edit'] ?? 0));
$view_id = max(0, (int)($_GET['view'] ?? 0));
$context_id = $edit_id > 0 ? $edit_id : $view_id;
$is_view = $view_id > 0 && $edit_id <= 0;

$context = dir_article_form_context_by_id($context_id);
$context_articles = dir_article_form_load_context_articles($context);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = (string)($_POST['csrf_token'] ?? '');

    if (!hash_equals($_SESSION['directory_article_csrf'], $csrf_token)) {
        $_SESSION['flash_error'] = 'Invalid security token. Please try again.';
        dir_article_form_redirect($edit_id > 0 ? ['edit' => $edit_id] : []);
    }

    $article_id = max(0, (int)($_POST['article_id'] ?? 0));
    $submit_action = (string)($_POST['submit_action'] ?? 'save');
    $district_id = max(0, (int)($_POST['district_id'] ?? 0));
    $thana_id = max(0, (int)($_POST['thana_id'] ?? 0));
    $specialty_id = max(0, (int)($_POST['specialty_id'] ?? 0));

    $en = [
        'title' => trim((string)($_POST['title_en'] ?? '')),
        'meta_title' => '',
        'meta_description' => '',
        'meta_keywords' => '',
        'save_seo' => false,
        'content' => trim((string)($_POST['content_en'] ?? '')),
        'show_doctor_list' => (int)($_POST['show_doctor_list_en'] ?? 1),
        'status' => trim((string)($_POST['status_en'] ?? 'active')),
    ];

    $bn = [
        'title' => trim((string)($_POST['title_bn'] ?? '')),
        'meta_title' => '',
        'meta_description' => '',
        'meta_keywords' => '',
        'save_seo' => false,
        'content' => trim((string)($_POST['content_bn'] ?? '')),
        'show_doctor_list' => (int)($_POST['show_doctor_list_bn'] ?? 1),
        'status' => trim((string)($_POST['status_bn'] ?? 'active')),
    ];

    if ($district_id <= 0) {
        $_SESSION['flash_error'] = 'Please select a district.';
        dir_article_form_redirect($article_id > 0 ? ['edit' => $article_id] : []);
    }

    if ($specialty_id <= 0) {
        $_SESSION['flash_error'] = 'Please select a specialty.';
        dir_article_form_redirect($article_id > 0 ? ['edit' => $article_id] : []);
    }

    $existingContextId = dir_article_form_find_existing_context_id(
        $district_id,
        $thana_id,
        $specialty_id,
        $article_id
    );

    if ($existingContextId > 0) {
        $_SESSION['flash_error'] = 'An article already exists for this district, thana and specialty.';
        $_SESSION['flash_duplicate_article_id'] = $existingContextId;
        dir_article_form_redirect($article_id > 0 ? ['edit' => $article_id] : []);
    }

    try {
        dir_article_form_save_lang($district_id, $thana_id, $specialty_id, 'en', $en);
        dir_article_form_save_lang($district_id, $thana_id, $specialty_id, 'bn', $bn);

        $stmt = $pdo->prepare("
            SELECT MIN(id)
            FROM doctor_directory_articles
            WHERE district_id = :district_id
              AND thana_id = :thana_id
              AND specialty_id = :specialty_id
        ");
        $stmt->execute([
            ':district_id' => $district_id,
            ':thana_id' => $thana_id,
            ':specialty_id' => $specialty_id,
        ]);
        $redirect_id = (int)$stmt->fetchColumn();

        if ($submit_action === 'seo') {
            $_SESSION['flash_success'] = 'Article details saved. You can now manage SEO settings.';
            header('Location: directory-article-seo.php?edit=' . $redirect_id);
            exit;
        }

        $_SESSION['flash_success'] = $article_id > 0 ? 'Directory article updated successfully.' : 'Directory article created successfully.';
        dir_article_form_redirect(['edit' => $redirect_id]);
    } catch (Throwable $e) {
        $_SESSION['flash_error'] = 'Directory article could not be saved.';
        dir_article_form_redirect($article_id > 0 ? ['edit' => $article_id] : []);
    }
}

$divisions = dir_article_form_get_divisions();
$districts = dir_article_form_get_districts();
$thanas = dir_article_form_get_thanas();
$specialties = dir_article_form_get_specialties();

$form = [
    'context_id' => $context_id,
    'district_id' => (int)($context['district_id'] ?? 0),
    'thana_id' => (int)($context['thana_id'] ?? 0),
    'specialty_id' => (int)($context['specialty_id'] ?? 0),
    'en' => $context_articles['en'],
    'bn' => $context_articles['bn'],
];

$page_title = $is_view ? 'View Directory Article' : (!empty($form['context_id']) ? 'Edit Directory Article' : 'Add Directory Article');

require_once __DIR__ . '/includes/header.php';
?>

<style>
  :root {
    --da-canvas: #f6f8fa;
    --da-surface: #ffffff;
    --da-surface-subtle: #f6f8fa;
    --da-surface-inset: #f0f3f6;
    --da-text: #1f2328;
    --da-muted: #57606a;
    --da-faint: #6e7781;
    --da-border: #d0d7de;
    --da-border-muted: #d8dee4;
    --da-blue: #0969da;
    --da-blue-strong: #0550ae;
    --da-blue-soft: #ddf4ff;
    --da-green: #1a7f37;
    --da-green-strong: #1f883d;
    --da-green-soft: #dafbe1;
    --da-red: #cf222e;
    --da-red-soft: #ffebe9;
    --da-yellow: #9a6700;
    --da-yellow-soft: #fff8c5;
    --da-radius: 8px;
    --da-shadow: 0 1px 0 rgba(31, 35, 40, .04), 0 8px 24px rgba(140, 149, 159, .14);
  }

  body {
    background: var(--da-canvas);
  }

  .da-page,
  .da-page * {
    box-sizing: border-box;
  }

  .da-page {
    max-width: 1320px;
    margin: 0 auto;
    padding: 20px 16px 34px;
    color: var(--da-text);
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Helvetica, Arial, sans-serif;
  }

  .da-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 18px;
    margin-bottom: 16px;
    padding: 16px 18px;
    border: 1px solid var(--da-border);
    border-radius: var(--da-radius);
    background: var(--da-surface);
    box-shadow: 0 1px 0 rgba(31, 35, 40, .04);
  }

  .da-title h1 {
    display: flex;
    align-items: center;
    gap: 9px;
    margin: 0;
    color: var(--da-text);
    font-size: 20px;
    line-height: 1.25;
    font-weight: 650;
    letter-spacing: -.01em;
  }

  .da-title h1::before {
    content: "▣";
    display: grid;
    width: 26px;
    height: 26px;
    place-items: center;
    border: 1px solid #b6e3ff;
    border-radius: 6px;
    color: var(--da-blue);
    background: var(--da-blue-soft);
    font-size: 14px;
    line-height: 1;
  }

  .da-title p {
    margin: 5px 0 0 35px;
    color: var(--da-muted);
    font-size: 13px;
    line-height: 1.5;
  }

  .da-actions-top,
  .da-actions-row {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
  }


  .da-seo-shortcut {
    border-color: #b6e3ff;
  }

  .da-seo-shortcut .da-section-head {
    background: linear-gradient(180deg, #f6fbff 0%, #ffffff 100%);
  }

  .da-seo-shortcut-body {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    padding: 16px;
  }

  .da-seo-shortcut-copy {
    display: grid;
    gap: 4px;
    max-width: 650px;
  }

  .da-seo-shortcut-copy strong {
    color: var(--da-text);
    font-size: 14px;
    font-weight: 650;
  }

  .da-seo-shortcut-copy span {
    color: var(--da-muted);
    font-size: 12px;
    line-height: 1.5;
  }

  .da-btn-seo {
    flex: 0 0 auto;
    border-color: rgba(9, 105, 218, .35);
    background: var(--da-blue);
    color: #ffffff;
  }

  .da-btn-seo:hover {
    border-color: rgba(9, 105, 218, .35);
    background: var(--da-blue-strong);
    color: #ffffff;
  }

  .da-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 32px;
    padding: 5px 12px;
    border: 1px solid rgba(31, 35, 40, .15);
    border-radius: 6px;
    background: #f6f8fa;
    color: var(--da-text);
    box-shadow: 0 1px 0 rgba(31, 35, 40, .04);
    cursor: pointer;
    font-size: 13px;
    font-weight: 600;
    line-height: 20px;
    text-decoration: none;
    transition: background-color .16s ease, border-color .16s ease, box-shadow .16s ease, transform .16s ease;
  }

  .da-btn:hover {
    border-color: rgba(31, 35, 40, .25);
    background: #f3f4f6;
  }

  .da-btn:active {
    transform: translateY(1px);
  }

  .da-btn:focus-visible,
  .da-field input:focus-visible,
  .da-field select:focus-visible,
  .da-field textarea:focus-visible {
    outline: 2px solid var(--da-blue);
    outline-offset: 2px;
  }

  .da-btn-primary {
    border-color: rgba(31, 35, 40, .15);
    background: var(--da-green-strong);
    color: #ffffff;
  }

  .da-btn-primary:hover {
    border-color: rgba(31, 35, 40, .15);
    background: var(--da-green);
    color: #ffffff;
  }

  .da-layout {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 330px;
    gap: 16px;
    align-items: start;
  }

  .da-card,
  .da-side {
    overflow: hidden;
    border: 1px solid var(--da-border);
    border-radius: var(--da-radius);
    background: var(--da-surface);
    box-shadow: var(--da-shadow);
  }

  .da-hero {
    position: relative;
    overflow: hidden;
    padding: 20px 22px;
    color: #ffffff;
    background: #24292f;
    border-bottom: 1px solid #1b1f24;
  }

  .da-hero::after {
    content: "";
    position: absolute;
    right: -52px;
    bottom: -80px;
    width: 240px;
    height: 240px;
    border: 1px solid rgba(255, 255, 255, .09);
    border-radius: 50%;
    box-shadow: 0 0 0 36px rgba(255, 255, 255, .025), 0 0 0 72px rgba(255, 255, 255, .02);
    pointer-events: none;
  }

  .da-hero-badge {
    position: relative;
    z-index: 1;
    display: inline-flex;
    align-items: center;
    min-height: 24px;
    margin-bottom: 8px;
    padding: 2px 8px;
    border: 1px solid rgba(255, 255, 255, .2);
    border-radius: 999px;
    color: #f0f6fc;
    background: rgba(255, 255, 255, .08);
    font-size: 11px;
    font-weight: 650;
    letter-spacing: .02em;
  }

  .da-hero h2 {
    position: relative;
    z-index: 1;
    margin: 0;
    color: #ffffff;
    font-size: 20px;
    line-height: 1.3;
    font-weight: 650;
    letter-spacing: -.01em;
  }

  .da-hero p {
    position: relative;
    z-index: 1;
    max-width: 760px;
    margin: 6px 0 0;
    color: #c9d1d9;
    font-size: 13px;
    line-height: 1.6;
  }

  .da-form-body {
    padding: 16px;
    background: var(--da-surface-subtle);
  }

  .da-section {
    overflow: hidden;
    margin-bottom: 16px;
    border: 1px solid var(--da-border);
    border-radius: var(--da-radius);
    background: var(--da-surface);
  }

  .da-section:last-child {
    margin-bottom: 0;
  }

  .da-section-head {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 14px;
    border-bottom: 1px solid var(--da-border-muted);
    background: var(--da-surface-subtle);
  }

  .da-section-icon {
    display: grid;
    flex: 0 0 28px;
    width: 28px;
    height: 28px;
    place-items: center;
    border: 1px solid #b6e3ff;
    border-radius: 6px;
    color: var(--da-blue);
    background: var(--da-blue-soft);
    font-size: 11px;
    font-weight: 700;
  }

  .da-section-head h3 {
    margin: 0;
    color: var(--da-text);
    font-size: 14px;
    line-height: 1.4;
    font-weight: 650;
  }

  .da-section-head p {
    margin: 2px 0 0;
    color: var(--da-muted);
    font-size: 12px;
    line-height: 1.45;
  }

  .da-grid,
  .da-en-bn-row {
    display: grid;
    gap: 14px;
    padding: 14px;
  }

  .da-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .da-en-bn-row {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .da-en-bn-row > .da-field {
    position: relative;
    padding: 30px 12px 12px;
    border: 1px solid var(--da-border-muted);
    border-radius: 7px;
    background: #ffffff;
  }

  .da-en-bn-row > .da-field::before {
    position: absolute;
    top: 9px;
    left: 10px;
    display: inline-flex;
    align-items: center;
    min-height: 17px;
    padding: 1px 6px;
    border: 1px solid #b6e3ff;
    border-radius: 999px;
    color: var(--da-blue-strong);
    background: var(--da-blue-soft);
    content: "ENGLISH";
    font-size: 9px;
    font-weight: 700;
    letter-spacing: .05em;
    line-height: 1.2;
  }

  .da-en-bn-row > .da-field:nth-child(2)::before {
    border-color: #a5d6b5;
    color: var(--da-green);
    background: var(--da-green-soft);
    content: "বাংলা";
  }

  .da-field {
    display: grid;
    gap: 6px;
    min-width: 0;
  }

  .da-field.full {
    grid-column: 1 / -1;
  }

  .da-field label {
    color: var(--da-text);
    font-size: 13px;
    line-height: 1.4;
    font-weight: 650;
  }

  .da-label-bn {
    color: var(--da-text) !important;
  }

  .da-field small,
  .da-help {
    color: var(--da-muted);
    font-size: 11px;
    line-height: 1.45;
  }

  .da-field input,
  .da-field select,
  .da-field textarea {
    width: 100%;
    min-height: 32px;
    padding: 5px 11px;
    border: 1px solid var(--da-border);
    border-radius: 6px;
    color: var(--da-text);
    background: #ffffff;
    box-shadow: inset 0 1px 0 rgba(208, 215, 222, .2);
    font: inherit;
    font-size: 13px;
    line-height: 20px;
    transition: border-color .16s ease, box-shadow .16s ease, background .16s ease;
  }

  .da-field select {
    min-height: 34px;
    cursor: pointer;
  }

  .da-field textarea {
    min-height: 108px;
    resize: vertical;
    line-height: 1.6;
  }

  .da-field textarea.da-content {
    min-height: 250px;
    padding: 12px;
    background: #fbfcfe;
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", monospace;
    font-size: 12px;
    line-height: 1.65;
  }

  .da-field input::placeholder,
  .da-field textarea::placeholder {
    color: #8c959f;
  }

  .da-field input:hover,
  .da-field select:hover,
  .da-field textarea:hover {
    border-color: #8c959f;
  }

  .da-field input:focus,
  .da-field select:focus,
  .da-field textarea:focus {
    border-color: var(--da-blue);
    background: #ffffff;
    box-shadow: 0 0 0 3px rgba(9, 105, 218, .14);
  }

  .da-alert {
    margin-bottom: 14px;
    padding: 11px 12px;
    border-radius: var(--da-radius);
    font-size: 13px;
    line-height: 1.5;
  }

  .da-alert.success {
    border: 1px solid #a5d6b5;
    color: #116329;
    background: var(--da-green-soft);
  }

  .da-alert.error {
    border: 1px solid #ff8182;
    color: #cf222e;
    background: var(--da-red-soft);
  }

  .da-alert-inner {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    flex-wrap: wrap;
  }

  .da-alert-action {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 30px;
    padding: 4px 10px;
    border: 1px solid #ff8182;
    border-radius: 6px;
    color: #cf222e;
    background: #ffffff;
    font-size: 12px;
    font-weight: 650;
    line-height: 18px;
    text-decoration: none;
  }

  .da-alert-action:hover,
  .da-alert-action:focus {
    color: #ffffff;
    background: var(--da-red);
  }

  .da-actions-row {
    align-items: center;
    margin-top: 2px;
    padding: 14px 0 0;
    border-top: 1px solid var(--da-border);
  }

  .da-side {
    position: sticky;
    top: 16px;
  }

  .da-side-head {
    display: flex;
    align-items: center;
    min-height: 48px;
    padding: 10px 14px;
    border-bottom: 1px solid var(--da-border);
    background: var(--da-surface-subtle);
  }

  .da-side-head span {
    display: inline-flex;
    align-items: center;
    min-height: 24px;
    padding: 2px 8px;
    border: 1px solid #b6e3ff;
    border-radius: 999px;
    color: var(--da-blue-strong);
    background: var(--da-blue-soft);
    font-size: 11px;
    font-weight: 650;
  }

  .da-preview {
    padding: 14px;
  }

  .da-preview-card {
    padding: 14px;
    border: 1px solid var(--da-border);
    border-radius: 7px;
    background: #ffffff;
  }

  .da-preview-icon {
    display: grid;
    width: 42px;
    height: 42px;
    place-items: center;
    margin-bottom: 12px;
    border: 1px solid #b6e3ff;
    border-radius: 7px;
    color: var(--da-blue-strong);
    background: var(--da-blue-soft);
    font-size: 18px;
    font-weight: 700;
  }

  .da-preview-card h3 {
    margin: 0 0 4px;
    color: var(--da-text);
    font-size: 16px;
    line-height: 1.35;
    font-weight: 650;
    letter-spacing: -.01em;
  }

  .da-preview-card p {
    margin: 0;
    color: var(--da-muted);
    font-size: 12px;
    line-height: 1.5;
  }

  .da-status {
    display: inline-flex;
    align-items: center;
    min-height: 22px;
    margin-top: 10px;
    padding: 2px 8px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 650;
  }

  .da-status.active {
    color: var(--da-green);
    background: var(--da-green-soft);
  }

  .da-status.inactive {
    color: var(--da-red);
    background: var(--da-red-soft);
  }

  .da-status.pending {
    color: var(--da-yellow);
    background: var(--da-yellow-soft);
  }

  .da-meta {
    display: grid;
    gap: 8px;
    margin-top: 12px;
  }

  .da-meta-item {
    padding: 9px 10px;
    border: 1px solid var(--da-border-muted);
    border-radius: 6px;
    background: var(--da-surface-subtle);
  }

  .da-meta-item span {
    display: block;
    margin-bottom: 3px;
    color: var(--da-muted);
    font-size: 10px;
    font-weight: 650;
    letter-spacing: .04em;
    text-transform: uppercase;
  }

  .da-meta-item strong {
    display: block;
    color: var(--da-text);
    font-size: 12px;
    font-weight: 600;
    line-height: 1.45;
    overflow-wrap: anywhere;
  }

  .da-help-list {
    margin: 0;
    padding: 0 14px 14px;
    list-style: none;
  }

  .da-help-list li {
    display: flex;
    gap: 8px;
    padding: 10px 0;
    border-bottom: 1px solid var(--da-border-muted);
    color: var(--da-muted);
    font-size: 12px;
    line-height: 1.5;
  }

  .da-help-list li:last-child {
    border-bottom: 0;
  }

  .da-help-list strong {
    color: var(--da-blue);
    font-weight: 700;
  }

  @media (max-width: 1040px) {
    .da-layout {
      grid-template-columns: 1fr;
    }

    .da-side {
      position: static;
    }
  }

  @media (max-width: 760px) {
    .da-page {
      padding: 12px 10px 24px;
    }

    .da-top {
      align-items: flex-start;
      flex-direction: column;
      padding: 14px;
    }

    .da-title p {
      margin-left: 0;
    }

    .da-actions-top,
    .da-actions-top .da-btn,
    .da-actions-row,
    .da-actions-row .da-btn {
      width: 100%;
    }

    .da-seo-shortcut-body {
      align-items: stretch;
      flex-direction: column;
    }

    .da-seo-shortcut-body .da-btn {
      width: 100%;
    }

    .da-grid,
    .da-en-bn-row {
      grid-template-columns: 1fr;
    }

    .da-en-bn-row > .da-field:nth-child(2)::before {
      content: "বাংলা";
    }

    .da-hero,
    .da-form-body {
      padding: 14px;
    }

    .da-section-head {
      align-items: flex-start;
    }
  }
</style>


<div class="da-page">
    <div class="da-top">
        <div class="da-title">
            <h1><?= e($page_title) ?></h1>
            <p>Manage the article context, visible title, HTML content and doctor-name list here. SEO settings are opened from the final section.</p>
        </div>

        <div class="da-actions-top">
            <a href="directory-articles.php" class="da-btn">← Back to List</a>
        </div>
    </div>

    <?php if (!empty($_SESSION['flash_success'])): ?>
        <div class="da-alert success"><?= e($_SESSION['flash_success']) ?></div>
        <?php unset($_SESSION['flash_success']); ?>
    <?php endif; ?>

    <?php if ((string)($_GET['seo_saved'] ?? '') === '1'): ?>
        <div class="da-alert success">SEO settings saved successfully. You are back on the main article page.</div>
    <?php endif; ?>

    <?php if (!empty($_SESSION['flash_error'])): ?>
        <?php $duplicateArticleId = max(0, (int)($_SESSION['flash_duplicate_article_id'] ?? 0)); ?>
        <div class="da-alert error">
            <div class="da-alert-inner">
                <span><?= e($_SESSION['flash_error']) ?></span>
                <?php if ($duplicateArticleId > 0): ?>
                    <a class="da-alert-action" href="directory-article-form.php?edit=<?= $duplicateArticleId ?>">
                        View Existing Article
                    </a>
                <?php endif; ?>
            </div>
        </div>
        <?php unset($_SESSION['flash_error'], $_SESSION['flash_duplicate_article_id']); ?>
    <?php endif; ?>

    <div class="da-layout">
        <main class="da-card">
            <div class="da-hero">
                <span class="da-hero-badge"><?= $is_view ? 'View Mode' : (!empty($form['context_id']) ? 'Update Mode' : 'Create Mode') ?></span>
                <h2><?= $is_view ? 'View Directory Article' : (!empty($form['context_id']) ? 'Update Directory Article' : 'Create Directory Article') ?></h2>
                <p>Choose the directory context, manage visible content in both languages, then open the dedicated SEO page when you are ready.</p>
            </div>

            <div class="da-form-body">
                <form method="POST" action="directory-article-form.php<?= !empty($form['context_id']) ? '?edit=' . e((string)$form['context_id']) : '' ?>" id="directoryArticleForm">
                    <input type="hidden" name="csrf_token" value="<?= e($_SESSION['directory_article_csrf']) ?>">
                    <input type="hidden" name="article_id" value="<?= e((string)$form['context_id']) ?>">

                    <section class="da-section">
                        <div class="da-section-head">
                            <span class="da-section-icon">01</span>
                            <div>
                                <h3>Article Context</h3>
                                <p>District and Specialty are required. Thana is optional for area-specific content.</p>
                            </div>
                        </div>

                        <div class="da-grid">
                            <div class="da-field">
                                <label>Division</label>
                                <select id="directoryDivisionSelect" <?= $is_view ? 'disabled' : '' ?>>
                                    <option value="0">All Divisions</option>
                                    <?php foreach ($divisions as $division): ?>
                                        <option
                                            value="<?= e((string)$division['id']) ?>"
                                            data-name-bn="<?= e((string)($division['name_bn'] ?? '')) ?>"
                                        >
                                            <?= e((string)$division['name']) ?><?= !empty($division['name_bn']) ? ' / ' . e((string)$division['name_bn']) : '' ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small>Used only to filter districts in this form.</small>
                            </div>

                            <div class="da-field">
                                <label>District <span style="color:#cf222e;">*</span></label>
                                <select name="district_id" id="directoryDistrictSelect" required <?= $is_view ? 'disabled' : '' ?>>
                                    <option value="0">Select District</option>
                                    <?php foreach ($districts as $district): ?>
                                        <option
                                            value="<?= e((string)$district['id']) ?>"
                                            data-division-id="<?= e((string)($district['division_id'] ?? 0)) ?>"
                                            data-name-bn="<?= e((string)($district['name_bn'] ?? '')) ?>"
                                            <?= (int)$form['district_id'] === (int)$district['id'] ? 'selected' : '' ?>
                                        >
                                            <?= e((string)$district['name']) ?><?= !empty($district['name_bn']) ? ' / ' . e((string)$district['name_bn']) : '' ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="da-field">
                                <label>Thana / Area Optional</label>
                                <select name="thana_id" id="directoryThanaSelect" <?= $is_view ? 'disabled' : '' ?>>
                                    <option value="0">No Thana / District Level Article</option>
                                    <?php foreach ($thanas as $thana): ?>
                                        <option
                                            value="<?= e((string)$thana['id']) ?>"
                                            data-district-id="<?= e((string)($thana['district_id'] ?? 0)) ?>"
                                            data-name-bn="<?= e((string)($thana['name_bn'] ?? '')) ?>"
                                            <?= (int)$form['thana_id'] === (int)$thana['id'] ? 'selected' : '' ?>
                                        >
                                            <?= e((string)$thana['name']) ?><?= !empty($thana['name_bn']) ? ' / ' . e((string)$thana['name_bn']) : '' ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="da-field">
                                <label>Specialty <span style="color:#cf222e;">*</span></label>
                                <select name="specialty_id" id="directorySpecialtySelect" required <?= $is_view ? 'disabled' : '' ?>>
                                    <option value="0">Select Specialty</option>
                                    <?php foreach ($specialties as $specialty): ?>
                                        <option
                                            value="<?= e((string)($specialty['id'] ?? 0)) ?>"
                                            data-name-bn="<?= e((string)($specialty['name_bn'] ?? '')) ?>"
                                            <?= (int)$form['specialty_id'] === (int)($specialty['id'] ?? 0) ? 'selected' : '' ?>
                                        >
                                            <?= e((string)($specialty['name'] ?? '')) ?><?= !empty($specialty['name_bn']) ? ' / ' . e((string)$specialty['name_bn']) : '' ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </section>

                    <section class="da-section">
                        <div class="da-section-head">
                            <span class="da-section-icon">02</span>
                            <div>
                                <h3>Article Status</h3>
                                <p>Control English and Bangla content visibility separately.</p>
                            </div>
                        </div>

                        <div class="da-en-bn-row">
                            <div class="da-field">
                                <label>English Status</label>
                                <select name="status_en" id="directoryStatusEn" <?= $is_view ? 'disabled' : '' ?>>
                                    <option value="active" <?= $form['en']['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                                    <option value="pending" <?= $form['en']['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                                    <option value="inactive" <?= $form['en']['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                                </select>
                            </div>

                            <div class="da-field">
                                <label class="da-label-bn">Bangla Status</label>
                                <select name="status_bn" id="directoryStatusBn" <?= $is_view ? 'disabled' : '' ?>>
                                    <option value="active" <?= $form['bn']['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                                    <option value="pending" <?= $form['bn']['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                                    <option value="inactive" <?= $form['bn']['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                                </select>
                            </div>
                        </div>
                    </section>

                    <section class="da-section">
                        <div class="da-section-head">
                            <span class="da-section-icon">03</span>
                            <div>
                                <h3>Title</h3>
                                <p>Write custom directory article titles. Empty fields are saved as NULL and can use frontend auto title.</p>
                            </div>
                        </div>

                        <div class="da-en-bn-row">
                            <div class="da-field">
                                <label>English Title</label>
                                <input type="text" id="directoryTitleEn" name="title_en" value="<?= e($form['en']['title']) ?>" placeholder="Example: Cardiology Doctors in Dhaka" <?= $is_view ? 'readonly' : '' ?>>
                                <small>Empty হলে database-এ NULL save হবে এবং frontend default auto title ব্যবহার করবে।</small>
                            </div>

                            <div class="da-field">
                                <label class="da-label-bn">Bangla Title</label>
                                <input type="text" id="directoryTitleBn" name="title_bn" value="<?= e($form['bn']['title']) ?>" placeholder="উদাহরণ: ঢাকায় কার্ডিওলজি ডাক্তার" <?= $is_view ? 'readonly' : '' ?>>
                                <small>Empty হলে database-এ NULL save হবে এবং frontend default auto title ব্যবহার করবে।</small>
                            </div>
                        </div>
                    </section>

                    <section class="da-section">
                        <div class="da-section-head">
                            <span class="da-section-icon">04</span>
                            <div>
                                <h3>Content HTML</h3>
                                <p>HTML allowed. Keep layout simple and readable for frontend pages.</p>
                            </div>
                        </div>

                        <div class="da-en-bn-row">
                            <div class="da-field">
                                <label>English Content HTML</label>
                                <textarea id="directoryContentEn" name="content_en" class="da-content" placeholder="<h3>Why choose a specialist?</h3><p>Write your custom article content here.</p>" <?= $is_view ? 'readonly' : '' ?>><?= e($form['en']['content']) ?></textarea>
                                <small>HTML allowed. Empty হলে database-এ NULL save হবে এবং frontend default doctor names list দেখাবে।</small>
                            </div>

                            <div class="da-field">
                                <label class="da-label-bn">Bangla Content HTML</label>
                                <textarea id="directoryContentBn" name="content_bn" class="da-content" placeholder="<h3>কেন বিশেষজ্ঞ ডাক্তার দেখাবেন?</h3><p>এখানে আপনার কাস্টম কনটেন্ট লিখুন।</p>" <?= $is_view ? 'readonly' : '' ?>><?= e($form['bn']['content']) ?></textarea>
                                <small>HTML allowed. Empty হলে database-এ NULL save হবে এবং frontend default doctor names list দেখাবে।</small>
                            </div>
                        </div>

                        <div class="da-en-bn-row" style="margin-top:16px;">
                            <div class="da-field">
                                <label for="directoryShowDoctorListEn">English 10 Doctor Name List</label>
                                <select name="show_doctor_list_en" id="directoryShowDoctorListEn" <?= $is_view ? 'disabled' : '' ?>>
                                    <option value="1" <?= (int)$form['en']['show_doctor_list'] === 1 ? 'selected' : '' ?>>On — Show notable doctor names</option>
                                    <option value="0" <?= (int)$form['en']['show_doctor_list'] !== 1 ? 'selected' : '' ?>>Off — Hide notable doctor names</option>
                                </select>
                                <small>Controls only the automatic “Notable doctors in this category” list on the English page. Doctor cards stay unchanged.</small>
                            </div>

                            <div class="da-field">
                                <label class="da-label-bn" for="directoryShowDoctorListBn">Bangla 10 Doctor Name List</label>
                                <select name="show_doctor_list_bn" id="directoryShowDoctorListBn" <?= $is_view ? 'disabled' : '' ?>>
                                    <option value="1" <?= (int)$form['bn']['show_doctor_list'] === 1 ? 'selected' : '' ?>>On — ডাক্তারদের নামের তালিকা দেখান</option>
                                    <option value="0" <?= (int)$form['bn']['show_doctor_list'] !== 1 ? 'selected' : '' ?>>Off — ডাক্তারদের নামের তালিকা লুকান</option>
                                </select>
                                <small>শুধু বাংলা পেজের automatic ১০ জন ডাক্তার নামের তালিকা নিয়ন্ত্রণ করবে। নিচের মূল doctor card অপরিবর্তিত থাকবে।</small>
                            </div>
                        </div>
                    </section>

                    <section class="da-section da-seo-shortcut">
                        <div class="da-section-head">
                            <span class="da-section-icon">05</span>
                            <div>
                                <h3>SEO Settings</h3>
                                <p>Manage English and Bangla Meta Title, Meta Description and Meta Keywords on a dedicated SEO page.</p>
                            </div>
                        </div>

                        <div class="da-seo-shortcut-body">
                            <div class="da-seo-shortcut-copy">
                                <strong>SEO is kept separate from article writing.</strong>
                                <span>Save this main article first, then continue to the SEO page. After saving SEO, you will return here to finish or change the remaining article options.</span>
                            </div>

                            <?php if (!$is_view): ?>
                                <button type="submit" class="da-btn da-btn-seo" name="submit_action" value="seo">
                                    Save &amp; Open SEO Settings →
                                </button>
                            <?php elseif (!empty($form['context_id'])): ?>
                                <a href="directory-article-seo.php?edit=<?= e((string)$form['context_id']) ?>" class="da-btn da-btn-seo">Open SEO Settings →</a>
                            <?php endif; ?>
                        </div>
                    </section>

                    <div class="da-actions-row">
                        <?php if (!$is_view): ?>
                            <button type="submit" class="da-btn da-btn-primary" name="submit_action" value="save">
                                <?= !empty($form['context_id']) ? 'Update Article' : 'Save Article' ?>
                            </button>
                        <?php endif; ?>

                        <?php if ($is_view && !empty($form['context_id'])): ?>
                            <a href="directory-article-form.php?edit=<?= e((string)$form['context_id']) ?>" class="da-btn da-btn-primary">Edit Article</a>
                        <?php endif; ?>

                        <a href="directory-article-form.php" class="da-btn">Add New</a>
                        <a href="directory-articles.php" class="da-btn">Back to List</a>
                    </div>
                </form>
            </div>
        </main>

        <aside class="da-side">
            <div class="da-side-head">
                <span>Live Preview</span>
            </div>

            <div class="da-preview">
                <div class="da-preview-card">
                    <div class="da-preview-icon" id="directoryPreviewIcon">D</div>

                    <h3 id="directoryPreviewTitle">Directory Article</h3>
                    <p id="directoryPreviewSubtitle">Select district and specialty to preview context.</p>

                    <span class="da-status active" id="directoryPreviewStatus">Active</span>

                    <div class="da-meta">
                        <div class="da-meta-item">
                            <span>District</span>
                            <strong id="directoryPreviewDistrict">Select District</strong>
                        </div>

                        <div class="da-meta-item">
                            <span>Thana</span>
                            <strong id="directoryPreviewThana">District level article</strong>
                        </div>

                        <div class="da-meta-item">
                            <span>Specialty</span>
                            <strong id="directoryPreviewSpecialty">Select Specialty</strong>
                        </div>

                        <div class="da-meta-item">
                            <span>Content</span>
                            <strong id="directoryPreviewContent">English and Bangla managed together</strong>
                        </div>
                    </div>
                </div>
            </div>

            <ul class="da-help-list">
                <li><strong>01</strong> District and specialty are required for every directory article.</li>
                <li><strong>02</strong> Thana is optional. Select thana only for area-level pages.</li>
                <li><strong>03</strong> Empty title and content fields use frontend fallbacks. SEO metadata is managed separately from the final SEO Settings section.</li>
                <li><strong>04</strong> English and Bangla article versions are saved together, including separate On/Off control for the automatic 10 doctor-name list.</li>
            </ul>
        </aside>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const divisionSelect = document.getElementById('directoryDivisionSelect');
    const districtSelect = document.getElementById('directoryDistrictSelect');
    const thanaSelect = document.getElementById('directoryThanaSelect');
    const specialtySelect = document.getElementById('directorySpecialtySelect');

    const titleEn = document.getElementById('directoryTitleEn');
    const titleBn = document.getElementById('directoryTitleBn');
    const contentEn = document.getElementById('directoryContentEn');
    const contentBn = document.getElementById('directoryContentBn');
    const showDoctorListEn = document.getElementById('directoryShowDoctorListEn');
    const showDoctorListBn = document.getElementById('directoryShowDoctorListBn');
    const statusEn = document.getElementById('directoryStatusEn');
    const statusBn = document.getElementById('directoryStatusBn');

    const previewIcon = document.getElementById('directoryPreviewIcon');
    const previewTitle = document.getElementById('directoryPreviewTitle');
    const previewSubtitle = document.getElementById('directoryPreviewSubtitle');
    const previewStatus = document.getElementById('directoryPreviewStatus');
    const previewDistrict = document.getElementById('directoryPreviewDistrict');
    const previewThana = document.getElementById('directoryPreviewThana');
    const previewSpecialty = document.getElementById('directoryPreviewSpecialty');
    const previewContent = document.getElementById('directoryPreviewContent');

    if (!divisionSelect || !districtSelect || !thanaSelect) {
        return;
    }

    const districtOptions = Array.from(districtSelect.options);
    const thanaOptions = Array.from(thanaSelect.options);

    function selectedOption(select) {
        if (!select || !select.value || select.selectedIndex < 0) {
            return null;
        }

        return select.options[select.selectedIndex] || null;
    }

    function optionText(select, fallback) {
        const option = selectedOption(select);

        if (!option || option.value === '0') {
            return fallback;
        }

        return option.textContent.trim();
    }

    function guessDivisionFromSelectedDistrict() {
        const selectedDistrict = selectedOption(districtSelect);

        if (!selectedDistrict) {
            return;
        }

        const divisionId = selectedDistrict.getAttribute('data-division-id') || '0';

        if (divisionId !== '0') {
            divisionSelect.value = divisionId;
        }
    }

    function filterDistricts() {
        const divisionId = divisionSelect.value || '0';
        const currentDistrict = districtSelect.value || '0';

        districtSelect.innerHTML = '';

        districtOptions.forEach(function (option) {
            const optionDivisionId = option.getAttribute('data-division-id') || '0';

            if (option.value === '0' || divisionId === '0' || optionDivisionId === divisionId) {
                districtSelect.appendChild(option);
            }
        });

        const stillExists = Array.from(districtSelect.options).some(function (option) {
            return option.value === currentDistrict;
        });

        districtSelect.value = stillExists ? currentDistrict : '0';
    }

    function filterThanas() {
        const districtId = districtSelect.value || '0';
        const currentThana = thanaSelect.value || '0';

        thanaSelect.innerHTML = '';

        thanaOptions.forEach(function (option) {
            const optionDistrictId = option.getAttribute('data-district-id') || '0';

            if (option.value === '0' || districtId === '0' || optionDistrictId === districtId) {
                thanaSelect.appendChild(option);
            }
        });

        const stillExists = Array.from(thanaSelect.options).some(function (option) {
            return option.value === currentThana;
        });

        thanaSelect.value = stillExists ? currentThana : '0';
    }

    function updatePreview() {
        const district = optionText(districtSelect, 'Select District');
        const thana = optionText(thanaSelect, 'District level article');
        const specialty = optionText(specialtySelect, 'Select Specialty');

        const customTitle = titleEn && titleEn.value.trim()
            ? titleEn.value.trim()
            : (titleBn && titleBn.value.trim() ? titleBn.value.trim() : '');

        const generatedTitle = district !== 'Select District' && specialty !== 'Select Specialty'
            ? specialty + ' in ' + district
            : 'Directory Article';

        const finalTitle = customTitle || generatedTitle;

        const statusValues = [
            statusEn ? statusEn.value : 'active',
            statusBn ? statusBn.value : 'active'
        ];
        const status = statusValues.indexOf('pending') !== -1
            ? 'pending'
            : (statusValues.every(function (value) { return value === 'inactive'; }) ? 'inactive' : 'active');

        const hasContent = [
            titleEn, titleBn,
            contentEn, contentBn
        ].some(function (field) {
            return field && field.value.trim() !== '';
        });

        if (previewTitle) {
            previewTitle.textContent = finalTitle;
        }

        if (previewSubtitle) {
            previewSubtitle.textContent = thana !== 'District level article'
                ? district + ' / ' + thana
                : district;
        }

        if (previewIcon) {
            previewIcon.textContent = finalTitle.charAt(0).toUpperCase();
        }

        if (previewStatus) {
            previewStatus.textContent = status.charAt(0).toUpperCase() + status.slice(1);
            previewStatus.classList.toggle('active', status === 'active');
            previewStatus.classList.toggle('pending', status === 'pending');
            previewStatus.classList.toggle('inactive', status === 'inactive');
        }

        if (previewDistrict) {
            previewDistrict.textContent = district;
        }

        if (previewThana) {
            previewThana.textContent = thana;
        }

        if (previewSpecialty) {
            previewSpecialty.textContent = specialty;
        }

        if (previewContent) {
            previewContent.textContent = hasContent
                ? 'Custom article content added'
                : 'Will use frontend default content';
        }
    }

    guessDivisionFromSelectedDistrict();
    filterDistricts();
    filterThanas();
    updatePreview();

    divisionSelect.addEventListener('change', function () {
        districtSelect.value = '0';
        thanaSelect.value = '0';
        filterDistricts();
        filterThanas();
        updatePreview();
    });

    districtSelect.addEventListener('change', function () {
        thanaSelect.value = '0';
        filterThanas();
        updatePreview();
    });

    [
        thanaSelect,
        specialtySelect,
        titleEn,
        titleBn,
        contentEn,
        contentBn,
        showDoctorListEn,
        showDoctorListBn,
        statusEn,
        statusBn
    ].forEach(function (field) {
        if (field) {
            field.addEventListener('input', updatePreview);
            field.addEventListener('change', updatePreview);
        }
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
