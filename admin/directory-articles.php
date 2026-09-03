<?php
require_once __DIR__ . '/../includes/functions.php';

require_admin();

if (!function_exists('dir_article_table_exists')) {
    function dir_article_table_exists(string $table): bool
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

if (!function_exists('dir_article_column_exists')) {
    function dir_article_column_exists(string $table, string $column): bool
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

if (!function_exists('dir_article_add_column_if_missing')) {
    function dir_article_add_column_if_missing(string $table, string $column, string $definition): void
    {
        global $pdo;

        if (!dir_article_table_exists($table)) {
            return;
        }

        if (!dir_article_column_exists($table, $column)) {
            $pdo->exec("ALTER TABLE {$table} ADD COLUMN {$column} {$definition}");
        }
    }
}

if (!function_exists('dir_article_boot')) {
    function dir_article_boot(): void
    {
        global $pdo;

        if (!dir_article_table_exists('doctor_directory_articles')) {
            $pdo->exec("
                CREATE TABLE doctor_directory_articles (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    district_id INT DEFAULT 0,
                    thana_id INT DEFAULT 0,
                    specialty_id INT DEFAULT 0,
                    lang VARCHAR(10) DEFAULT 'en',
                    title VARCHAR(255) NULL,
                    intro TEXT NULL,
                    content LONGTEXT NULL,
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

        dir_article_add_column_if_missing('doctor_directory_articles', 'district_id', "INT DEFAULT 0");
        dir_article_add_column_if_missing('doctor_directory_articles', 'thana_id', "INT DEFAULT 0");
        dir_article_add_column_if_missing('doctor_directory_articles', 'specialty_id', "INT DEFAULT 0");
        dir_article_add_column_if_missing('doctor_directory_articles', 'lang', "VARCHAR(10) DEFAULT 'en'");
        dir_article_add_column_if_missing('doctor_directory_articles', 'title', "VARCHAR(255) NULL");
        dir_article_add_column_if_missing('doctor_directory_articles', 'intro', "TEXT NULL");
        dir_article_add_column_if_missing('doctor_directory_articles', 'content', "LONGTEXT NULL");
        dir_article_add_column_if_missing('doctor_directory_articles', 'status', "VARCHAR(30) DEFAULT 'active'");
        dir_article_add_column_if_missing('doctor_directory_articles', 'created_at', "DATETIME NULL");
        dir_article_add_column_if_missing('doctor_directory_articles', 'updated_at', "DATETIME NULL");
    }
}

if (!function_exists('dir_article_name_column')) {
    function dir_article_name_column(string $table): string
    {
        if (dir_article_column_exists($table, 'name_en')) {
            return 'name_en';
        }

        if (dir_article_column_exists($table, 'name')) {
            return 'name';
        }

        if (dir_article_column_exists($table, 'title')) {
            return 'title';
        }

        return 'name_en';
    }
}

if (!function_exists('dir_article_slug_column_select')) {
    function dir_article_slug_column_select(string $table, string $alias): string
    {
        if (dir_article_column_exists($table, 'slug')) {
            return "{$alias}.slug";
        }

        return "''";
    }
}

if (!function_exists('dir_article_url_slug')) {
    function dir_article_url_slug(string $text): string
    {
        $text = trim($text);
        $text = preg_replace('/[^\p{L}\p{N}\s-]+/u', '', $text);
        $text = preg_replace('/[\s_]+/u', '-', (string)$text);
        $text = preg_replace('/-+/u', '-', (string)$text);

        if (function_exists('mb_strtolower')) {
            $text = mb_strtolower($text, 'UTF-8');
        } else {
            $text = strtolower($text);
        }

        return trim((string)$text, '-');
    }
}

if (!function_exists('dir_article_row_slug')) {
    function dir_article_row_slug(array $row, string $slug_key, string $name_key): string
    {
        $slug = trim((string)($row[$slug_key] ?? ''));

        if ($slug !== '') {
            return dir_article_url_slug($slug);
        }

        return dir_article_url_slug((string)($row[$name_key] ?? ''));
    }
}

if (!function_exists('dir_article_front_url')) {
    function dir_article_front_url(array $context): string
    {
        $district_slug = dir_article_row_slug($context, 'district_slug', 'district_name');
        $thana_slug = dir_article_row_slug($context, 'thana_slug', 'thana_name');
        $specialty_slug = dir_article_row_slug($context, 'specialty_slug', 'specialty_name');

        if ($district_slug === '' || $specialty_slug === '') {
            return function_exists('site_url') ? site_url('doctors') : '../doctors';
        }

        $path = 'doctors/' . $district_slug . '/';

        if ((int)($context['thana_id'] ?? 0) > 0 && $thana_slug !== '') {
            $path .= $thana_slug . '/';
        }

        $path .= $specialty_slug;

        return function_exists('site_url') ? site_url($path) : '../' . $path;
    }
}


if (!function_exists('dir_article_safe_return_url')) {
    function dir_article_safe_return_url(string $return_url): string
    {
        $return_url = trim($return_url);

        if ($return_url === '') {
            return 'directory-articles.php';
        }

        $parts = parse_url($return_url);
        $path = $parts['path'] ?? '';

        if (!empty($parts['scheme']) || !empty($parts['host'])) {
            return 'directory-articles.php';
        }

        if ($path !== 'directory-articles.php' && basename($path) !== 'directory-articles.php') {
            return 'directory-articles.php';
        }

        return $return_url;
    }
}

if (!function_exists('dir_article_get_contexts')) {
    function dir_article_get_contexts(string $status_filter = ''): array
    {
        global $pdo;

        if (!dir_article_table_exists('doctor_directory_articles')) {
            return [];
        }

        $select = "
            MIN(a.id) AS context_id,
            a.district_id,
            a.thana_id,
            a.specialty_id,
            MAX(CASE WHEN a.lang = 'en' THEN a.id ELSE 0 END) AS en_id,
            MAX(CASE WHEN a.lang = 'bn' THEN a.id ELSE 0 END) AS bn_id,
            MAX(CASE WHEN a.lang = 'en' THEN a.title ELSE '' END) AS title_en,
            MAX(CASE WHEN a.lang = 'bn' THEN a.title ELSE '' END) AS title_bn,
            MAX(CASE WHEN a.lang = 'en' THEN a.intro ELSE '' END) AS intro_en,
            MAX(CASE WHEN a.lang = 'bn' THEN a.intro ELSE '' END) AS intro_bn,
            MAX(CASE WHEN a.lang = 'en' THEN a.status ELSE '' END) AS status_en,
            MAX(CASE WHEN a.lang = 'bn' THEN a.status ELSE '' END) AS status_bn,
            MAX(a.updated_at) AS updated_at,
            MAX(a.created_at) AS created_at
        ";

        $joins = '';

        if (dir_article_table_exists('districts')) {
            $district_name_col = dir_article_name_column('districts');
            $district_slug_col = dir_article_slug_column_select('districts', 'd');
            $select .= ", d.{$district_name_col} AS district_name, {$district_slug_col} AS district_slug";
            $joins .= " LEFT JOIN districts d ON d.id = a.district_id ";
        } else {
            $select .= ", '' AS district_name, '' AS district_slug";
        }

        if (dir_article_table_exists('thanas')) {
            $thana_name_col = dir_article_name_column('thanas');
            $thana_slug_col = dir_article_slug_column_select('thanas', 't');
            $select .= ", t.{$thana_name_col} AS thana_name, {$thana_slug_col} AS thana_slug";
            $joins .= " LEFT JOIN thanas t ON t.id = a.thana_id ";
        } else {
            $select .= ", '' AS thana_name, '' AS thana_slug";
        }

        if (dir_article_table_exists('specialties')) {
            $specialty_name_col = dir_article_name_column('specialties');
            $specialty_slug_col = dir_article_slug_column_select('specialties', 's');
            $select .= ", s.{$specialty_name_col} AS specialty_name, {$specialty_slug_col} AS specialty_slug";
            $joins .= " LEFT JOIN specialties s ON s.id = a.specialty_id ";
        } else {
            $select .= ", '' AS specialty_name, '' AS specialty_slug";
        }

        $status_filter = strtolower(trim($status_filter));
        $allowed_statuses = ['pending'];

        if (!in_array($status_filter, $allowed_statuses, true)) {
            $status_filter = '';
        }

        try {
            $sql = "
                SELECT {$select}
                FROM doctor_directory_articles a
                {$joins}
                GROUP BY a.district_id, a.thana_id, a.specialty_id
            ";
            $params = [];

            if ($status_filter !== '') {
                $sql .= " HAVING status_en = :status OR status_bn = :status ";
                $params[':status'] = $status_filter;
            }

            $sql .= " ORDER BY MAX(a.id) DESC LIMIT 300 ";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }
}

if (!function_exists('dir_article_context_by_id')) {
    function dir_article_context_by_id(int $article_id): array
    {
        global $pdo;

        if ($article_id <= 0) {
            return [];
        }

        try {
            $stmt = $pdo->prepare("
                SELECT district_id, thana_id, specialty_id
                FROM doctor_directory_articles
                WHERE id = :id
                LIMIT 1
            ");
            $stmt->execute([':id' => $article_id]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }
}

dir_article_boot();

if (empty($_SESSION['directory_article_csrf'])) {
    $_SESSION['directory_article_csrf'] = bin2hex(random_bytes(32));
}

$current_return_url = 'directory-articles.php' . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = (string)($_POST['csrf_token'] ?? '');

    if (!hash_equals($_SESSION['directory_article_csrf'], $csrf_token)) {
        $_SESSION['flash_error'] = 'Invalid security token. Please try again.';
        header('Location: directory-articles.php');
        exit;
    }

    $action = trim((string)($_POST['action'] ?? ''));

    if ($action === 'delete') {
        $article_id = max(0, (int)($_POST['article_id'] ?? 0));
        $return_url = dir_article_safe_return_url((string)($_POST['return_url'] ?? 'directory-articles.php'));
        $context = dir_article_context_by_id($article_id);

        if (empty($context)) {
            $_SESSION['flash_error'] = 'Invalid article context.';
            header('Location: ' . $return_url);
            exit;
        }

        try {
            $stmt = $pdo->prepare("
                DELETE FROM doctor_directory_articles
                WHERE district_id = :district_id
                  AND thana_id = :thana_id
                  AND specialty_id = :specialty_id
            ");
            $stmt->execute([
                ':district_id' => (int)$context['district_id'],
                ':thana_id' => (int)$context['thana_id'],
                ':specialty_id' => (int)$context['specialty_id'],
            ]);

            $_SESSION['flash_success'] = 'Directory article deleted successfully.';
        } catch (Throwable $e) {
            $_SESSION['flash_error'] = 'Directory article could not be deleted.';
        }

        header('Location: ' . $return_url);
        exit;
    }
}

$article_status_filter = strtolower(trim((string)($_GET['status'] ?? '')));

if (!in_array($article_status_filter, ['pending'], true)) {
    $article_status_filter = '';
}

$contexts = dir_article_get_contexts($article_status_filter);
$article_list_title = $article_status_filter === 'pending' ? 'Pending Articles' : 'All Articles';
$article_list_description = $article_status_filter === 'pending'
    ? 'Review directory article content that is waiting for publication.'
    : 'Only saved Step 4 dynamic articles are listed here. Add and edit articles from the separate form page.';

require_once __DIR__ . '/includes/header.php';
?>

<style>
.dir-page{max-width:1280px}.dir-header{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;margin-bottom:18px}.dir-header h1{margin:0;color:#24292f;font-size:26px;line-height:1.25;letter-spacing:-.03em}.dir-header p{margin:6px 0 0;color:#57606a;font-size:14px;line-height:1.6}.dir-btn{display:inline-flex;align-items:center;justify-content:center;min-height:38px;padding:8px 14px;border-radius:6px;border:1px solid rgba(27,31,36,.15);cursor:pointer;font-size:13.5px;font-weight:700;text-decoration:none;white-space:nowrap}.dir-btn-primary{background:#2da44e;color:#fff}.dir-btn-primary:hover{background:#1f883d;color:#fff}.dir-btn-light{background:#f6f8fa;color:#24292f}.dir-btn-danger{background:#ffebe9;color:#cf222e;border-color:#ff818266}.dir-card{background:#fff;border:1px solid #d0d7de;border-radius:12px;overflow:hidden;box-shadow:0 1px 0 rgba(27,31,36,.04)}.dir-card-head{padding:14px 16px;background:#f6f8fa;border-bottom:1px solid #d0d7de}.dir-card-head h2{margin:0;color:#24292f;font-size:15px;line-height:1.35}.dir-card-head p{margin:4px 0 0;color:#57606a;font-size:12.5px;line-height:1.5}.dir-table-wrap{overflow-x:auto}.dir-table{width:100%;border-collapse:collapse;min-width:980px}.dir-table th,.dir-table td{padding:11px 12px;border-bottom:1px solid #d8dee4;text-align:left;vertical-align:top;font-size:13px}.dir-table th{background:#f6f8fa;color:#57606a;font-weight:800;white-space:nowrap}.dir-table td{color:#24292f}.dir-table tr:hover td{background:#f6f8fa}.dir-pill{display:inline-flex;align-items:center;min-height:24px;padding:4px 8px;border-radius:999px;border:1px solid #d0d7de;background:#f6f8fa;color:#57606a;font-size:12px;font-weight:800;white-space:nowrap}.dir-pill.green{background:#dafbe1;border-color:#aceebb;color:#116329}.dir-pill.red{background:#ffebe9;border-color:#ff818266;color:#cf222e}.dir-pill.yellow{background:#fff8c5;border-color:#eac54f;color:#9a6700}.dir-title-cell strong{display:block;margin-bottom:4px;color:#24292f;font-size:13.5px}.dir-title-cell small{display:block;color:#57606a;line-height:1.5}.dir-actions{display:flex;gap:8px;flex-wrap:wrap;align-items:center}.dir-empty{padding:22px;color:#57606a;text-align:center}.dir-alert{margin-bottom:14px;padding:12px 14px;border-radius:10px;font-size:14px;line-height:1.5}.dir-alert.success{background:#dcfce7;border:1px solid #86efac;color:#166534}.dir-alert.error{background:#fee2e2;border:1px solid #fecaca;color:#991b1b}.dir-filter-actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:12px}.dir-filter-actions .is-active{background:#0969da;color:#fff;border-color:#0969da}@media(max-width:640px){.dir-header{flex-direction:column}.dir-btn{width:100%}.dir-actions{display:grid}}
</style>

<div class="dir-page">
    <div class="dir-header">
        <div>
            <h1><?= e($article_list_title) ?></h1>
            <p><?= e($article_list_description) ?></p>
            <div class="dir-filter-actions">
                <a href="directory-articles.php" class="dir-btn dir-btn-light <?= $article_status_filter === '' ? 'is-active' : '' ?>">All Article</a>
                <a href="directory-articles.php?status=pending" class="dir-btn dir-btn-light <?= $article_status_filter === 'pending' ? 'is-active' : '' ?>">Pending</a>
            </div>
        </div>

        <a href="directory-article-form.php" class="dir-btn dir-btn-primary">Write Article</a>
    </div>

    <?php if (!empty($_SESSION['flash_success'])): ?>
        <div class="dir-alert success"><?= e($_SESSION['flash_success']) ?></div>
        <?php unset($_SESSION['flash_success']); ?>
    <?php endif; ?>

    <?php if (!empty($_SESSION['flash_error'])): ?>
        <div class="dir-alert error"><?= e($_SESSION['flash_error']) ?></div>
        <?php unset($_SESSION['flash_error']); ?>
    <?php endif; ?>

    <div class="dir-card">
        <div class="dir-card-head">
            <h2><?= e($article_list_title) ?></h2>
            <p><?= $article_status_filter === 'pending' ? 'Only articles with pending English or Bangla content are shown.' : 'Each row represents one location + specialty article. English and Bangla are managed together.' ?></p>
        </div>

        <div class="dir-table-wrap">
            <?php if (!empty($contexts)): ?>
                <table class="dir-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Article</th>
                            <th>Location</th>
                            <th>Specialty</th>
                            <th>Language</th>
                            <th>Status</th>
                            <th>Updated</th>
                            <th>Actions</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php foreach ($contexts as $context): ?>
                            <?php
                                $context_id = (int)($context['context_id'] ?? 0);
                                $title_en = trim((string)($context['title_en'] ?? ''));
                                $title_bn = trim((string)($context['title_bn'] ?? ''));
                                $intro_en = trim((string)($context['intro_en'] ?? ''));
                                $intro_bn = trim((string)($context['intro_bn'] ?? ''));
                                $status_en = trim((string)($context['status_en'] ?? ''));
                                $status_bn = trim((string)($context['status_bn'] ?? ''));
                                $status_en_class = $status_en === 'active' ? 'green' : ($status_en === 'pending' ? 'yellow' : 'red');
                                $status_bn_class = $status_bn === 'active' ? 'green' : ($status_bn === 'pending' ? 'yellow' : 'red');
                            ?>
                            <tr>
                                <td>#<?= e((string)$context_id) ?></td>

                                <td class="dir-title-cell">
                                    <strong><?= e($title_en !== '' ? $title_en : ($title_bn !== '' ? $title_bn : 'Default title will be used')) ?></strong>
                                    <small>
                                        <?= e(mb_substr(strip_tags($intro_en !== '' ? $intro_en : $intro_bn), 0, 110)) ?>
                                        <?= mb_strlen(strip_tags($intro_en !== '' ? $intro_en : $intro_bn)) > 110 ? '...' : '' ?>
                                    </small>
                                </td>

                                <td>
                                    <strong><?= e((string)($context['district_name'] ?? '')) ?></strong>
                                    <?php if (!empty($context['thana_name'])): ?>
                                        <br><small><?= e((string)$context['thana_name']) ?></small>
                                    <?php else: ?>
                                        <br><small>District level</small>
                                    <?php endif; ?>
                                </td>

                                <td><?= e((string)($context['specialty_name'] ?? '')) ?></td>

                                <td>
                                    <span class="dir-pill <?= (int)($context['en_id'] ?? 0) > 0 ? 'green' : 'red' ?>">EN</span>
                                    <span class="dir-pill <?= (int)($context['bn_id'] ?? 0) > 0 ? 'green' : 'red' ?>">BN</span>
                                </td>

                                <td>
                                    <span class="dir-pill <?= e($status_en_class) ?>">EN <?= e($status_en !== '' ? $status_en : 'empty') ?></span>
                                    <span class="dir-pill <?= e($status_bn_class) ?>">BN <?= e($status_bn !== '' ? $status_bn : 'empty') ?></span>
                                </td>

                                <td><?= e((string)($context['updated_at'] ?: $context['created_at'] ?: '')) ?></td>

                                <td>
                                    <div class="dir-actions">
                                        <a href="<?= e(dir_article_front_url($context)) ?>" target="_blank" class="dir-btn dir-btn-light">View</a>
                                        <a href="directory-article-form.php?edit=<?= e((string)$context_id) ?>" class="dir-btn dir-btn-light">Edit</a>

                                        <form method="POST" action="directory-articles.php" onsubmit="return confirm('Delete this article context? Both English and Bangla versions will be deleted.');">
                                            <input type="hidden" name="csrf_token" value="<?= e($_SESSION['directory_article_csrf']) ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="article_id" value="<?= e((string)$context_id) ?>">
                                            <input type="hidden" name="return_url" value="<?= e($current_return_url) ?>">
                                            <button type="submit" class="dir-btn dir-btn-danger">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="dir-empty">
                    <?= $article_status_filter === 'pending' ? 'No pending article found.' : 'No custom directory article yet. The frontend will show the default auto article.' ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
