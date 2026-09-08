<?php
require_once __DIR__ . '/includes/header.php';

/*
|--------------------------------------------------------------------------
| Specialty Form - Clean EN/BN Design
|--------------------------------------------------------------------------
| - English left, Bangla right
| - Auto column support
| - SEO EN/BN fields
| - Full specialty article EN/BN fields
| - Live preview
| - Clean responsive admin UI
*/

$id = (int)($_GET['id'] ?? 0);
$edit = null;


/*
|--------------------------------------------------------------------------
| Safe Text Helper
|--------------------------------------------------------------------------
| Works even when the server does not have the PHP mbstring extension.
*/
if (!function_exists('specialty_form_excerpt')) {
    function specialty_form_excerpt(string $text, int $limit = 90): string
    {
        $text = trim((string)preg_replace('/\s+/', ' ', $text));

        if ($text === '') {
            return '';
        }

        if (function_exists('mb_strimwidth')) {
            return mb_strimwidth($text, 0, $limit, '...');
        }

        if (strlen($text) <= $limit) {
            return $text;
        }

        return rtrim(substr($text, 0, max(0, $limit - 3))) . '...';
    }
}


/*
|--------------------------------------------------------------------------
| Article HTML / CSS Safety Helper
|--------------------------------------------------------------------------
| Article content accepts trusted HTML and CSS for the public specialty page.
| Scripts, embedded forms, event handlers and javascript: URLs are removed
| before content is stored. Keep CSS scoped to .specialty-article-content.
|--------------------------------------------------------------------------
*/
if (!function_exists('specialty_form_clean_article_html')) {
    function specialty_form_clean_article_html(string $html): string
    {
        $html = trim($html);

        if ($html === '') {
            return '';
        }

        // Remove executable or interactive tags that are not needed for articles.
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

        // Remove inline JavaScript event handlers and javascript: URLs.
        $html = preg_replace('/\son[a-z0-9_-]+\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)/iu', '', $html);
        $html = preg_replace('/\s(href|src)\s*=\s*(["\'])\s*javascript:[^"\']*\2/iu', '', $html);

        // Allow CSS, but do not permit remote imports or legacy CSS expressions.
        $html = preg_replace('/@import\s+(?:url\()?[^;]+;?/iu', '', $html);
        $html = preg_replace('/expression\s*\(/iu', '', $html);

        return trim((string)$html);
    }
}


/*
|--------------------------------------------------------------------------
| Specialty Image Upload Helper
|--------------------------------------------------------------------------
| Stores each validated image as a high-quality WebP file without resizing.
| URL format: specialty-name-yymmddtime.webp
|--------------------------------------------------------------------------
*/
if (!function_exists('specialty_form_normalize_image_orientation')) {
    function specialty_form_normalize_image_orientation($image, string $source_path, string $mime)
    {
        if (
            $mime !== 'image/jpeg'
            || !function_exists('exif_read_data')
            || !function_exists('imagerotate')
        ) {
            return $image;
        }

        $exif = @exif_read_data($source_path);

        if (!is_array($exif) || empty($exif['Orientation'])) {
            return $image;
        }

        $orientation = (int)$exif['Orientation'];
        $angle = 0;

        if ($orientation === 3) {
            $angle = 180;
        } elseif ($orientation === 6) {
            $angle = -90;
        } elseif ($orientation === 8) {
            $angle = 90;
        }

        if ($angle === 0) {
            return $image;
        }

        $rotated = @imagerotate($image, $angle, 0);

        if ($rotated !== false) {
            imagedestroy($image);
            return $rotated;
        }

        return $image;
    }
}

if (!function_exists('specialty_form_store_image')) {
    function specialty_form_store_image(array $file, string $slug): string
    {
        $upload_error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);

        if ($upload_error === UPLOAD_ERR_NO_FILE) {
            return '';
        }

        if ($upload_error !== UPLOAD_ERR_OK || empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            throw new RuntimeException('The specialty image could not be uploaded. Please try again.');
        }

        $max_bytes = 3 * 1024 * 1024;

        if ((int)($file['size'] ?? 0) <= 0 || (int)$file['size'] > $max_bytes) {
            throw new RuntimeException('Please upload an image smaller than 3 MB.');
        }

        $image_info = @getimagesize($file['tmp_name']);

        if ($image_info === false) {
            throw new RuntimeException('Please upload a valid image file.');
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = (string)$finfo->file($file['tmp_name']);

        $allowed_types = [
            'image/jpeg' => 'imagecreatefromjpeg',
            'image/png'  => 'imagecreatefrompng',
            'image/webp' => 'imagecreatefromwebp',
        ];

        if (!isset($allowed_types[$mime])) {
            throw new RuntimeException('Only JPG, PNG and WebP images are allowed.');
        }

        if (!function_exists($allowed_types[$mime]) || !function_exists('imagewebp')) {
            throw new RuntimeException('Server image processing is unavailable. Enable PHP GD with WebP support, then try again.');
        }

        $upload_dir = dirname(__DIR__) . '/assets/uploads/specialties';

        if (!is_dir($upload_dir) && !mkdir($upload_dir, 0755, true) && !is_dir($upload_dir)) {
            throw new RuntimeException('The specialty image folder could not be created.');
        }

        $safe_slug = trim((string)preg_replace('/[^a-z0-9-]+/i', '-', $slug), '-');
        $safe_slug = $safe_slug !== '' ? $safe_slug : 'specialty';

        /*
         * Keep the original pixel dimensions. The image is never cropped or
         * resized; only the WebP encoder quality adapts to source resolution.
         * Re-encoding also removes unnecessary image metadata, reducing size.
         */
        $create_function = $allowed_types[$mime];
        $image = @$create_function($file['tmp_name']);

        if ($image === false) {
            throw new RuntimeException('The uploaded image could not be processed.');
        }

        $image = specialty_form_normalize_image_orientation($image, $file['tmp_name'], $mime);

        if (function_exists('imagepalettetotruecolor')) {
            @imagepalettetotruecolor($image);
        }

        imagealphablending($image, true);
        imagesavealpha($image, true);

        /*
         * Keep only the requested name-yymmddtime format. A collision is
         * handled by waiting for the next second instead of adding a random suffix.
         */
        $attempts = 0;

        do {
            $timestamp = date('ymdHis');
            $filename = $safe_slug . '-' . $timestamp . '.webp';
            $destination = $upload_dir . '/' . $filename;

            if (!is_file($destination)) {
                break;
            }

            usleep(250000);
            $attempts++;
        } while ($attempts < 8);

        if (is_file($destination)) {
            imagedestroy($image);
            throw new RuntimeException('Please upload the image again in a few seconds.');
        }

        /*
         * Balanced adaptive WebP compression. Smaller images retain more
         * detail, while large camera images are compressed more efficiently.
         * No crop or resize is performed, so the original dimensions remain.
         */
        $pixel_count = imagesx($image) * imagesy($image);

        if ($pixel_count >= 12000000) {
            $quality = 80;
        } elseif ($pixel_count >= 6000000) {
            $quality = 82;
        } elseif ($pixel_count >= 2000000) {
            $quality = 85;
        } else {
            $quality = 88;
        }

        $saved = @imagewebp($image, $destination, $quality);
        imagedestroy($image);

        if (!$saved) {
            throw new RuntimeException('The specialty image could not be converted to WebP.');
        }

        @chmod($destination, 0644);

        return '/assets/uploads/specialties/' . $filename;
    }
}

if (!function_exists('specialty_form_delete_image')) {
    function specialty_form_delete_image(string $public_path): void
    {
        $prefix = '/assets/uploads/specialties/';

        if (strpos($public_path, $prefix) !== 0) {
            return;
        }

        $file_path = dirname(__DIR__) . $public_path;

        if (is_file($file_path)) {
            @unlink($file_path);
        }
    }
}

/*
|--------------------------------------------------------------------------
| Column Helpers
|--------------------------------------------------------------------------
*/
if (!function_exists('specialty_form_column_exists')) {
    function specialty_form_column_exists(string $table, string $column): bool
    {
        global $pdo;

        try {
            $stmt = $pdo->prepare("
                SELECT COUNT(*)
                FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = :table
                  AND COLUMN_NAME = :column
            ");
            $stmt->execute([
                ':table'  => $table,
                ':column' => $column,
            ]);

            return (int)$stmt->fetchColumn() > 0;
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('specialty_form_table_exists')) {
    function specialty_form_table_exists(string $table): bool
    {
        global $pdo;

        try {
            $stmt = $pdo->prepare("
                SELECT COUNT(*)
                FROM INFORMATION_SCHEMA.TABLES
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = :table
            ");
            $stmt->execute([':table' => $table]);

            return (int)$stmt->fetchColumn() > 0;
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('specialty_form_add_column_if_missing')) {
    function specialty_form_add_column_if_missing(string $table, string $column, string $definition): void
    {
        global $pdo;

        if (!specialty_form_table_exists($table) || specialty_form_column_exists($table, $column)) {
            return;
        }

        try {
            $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
        } catch (Throwable $e) {
            // Keep the form usable if database permissions do not allow ALTER TABLE.
        }
    }
}

if (!function_exists('specialty_form_boot_columns')) {
    function specialty_form_boot_columns(): void
    {
        specialty_form_add_column_if_missing('specialties', 'name_bn', "VARCHAR(255) NULL AFTER `name`");
        specialty_form_add_column_if_missing('specialties', 'title_name', "VARCHAR(255) NULL AFTER `name_bn`");
        specialty_form_add_column_if_missing('specialties', 'title_name_bn', "VARCHAR(255) NULL AFTER `title_name`");
        specialty_form_add_column_if_missing('specialties', 'alternate_names', "TEXT NULL AFTER `title_name_bn`");
        specialty_form_add_column_if_missing('specialties', 'alternate_names_bn', "TEXT NULL AFTER `alternate_names`");
        specialty_form_add_column_if_missing('specialties', 'image', "VARCHAR(500) NULL AFTER `slug`");
        specialty_form_add_column_if_missing('specialties', 'description_bn', "TEXT NULL");
        specialty_form_add_column_if_missing('specialties', 'article_content', "LONGTEXT NULL AFTER `description_bn`");
        specialty_form_add_column_if_missing('specialties', 'article_content_bn', "LONGTEXT NULL AFTER `article_content`");
        specialty_form_add_column_if_missing('specialties', 'seo_title', "VARCHAR(255) NULL");
        specialty_form_add_column_if_missing('specialties', 'seo_title_bn', "VARCHAR(255) NULL");
        specialty_form_add_column_if_missing('specialties', 'seo_description', "TEXT NULL");
        specialty_form_add_column_if_missing('specialties', 'seo_description_bn', "TEXT NULL");
        specialty_form_add_column_if_missing('specialties', 'meta_keywords', "VARCHAR(255) NULL");
        specialty_form_add_column_if_missing('specialties', 'meta_keywords_bn', "VARCHAR(255) NULL");
    }
}

specialty_form_boot_columns();

$has_name_bn = specialty_form_column_exists('specialties', 'name_bn');
$has_title_name = specialty_form_column_exists('specialties', 'title_name');
$has_title_name_bn = specialty_form_column_exists('specialties', 'title_name_bn');
$has_alternate_names = specialty_form_column_exists('specialties', 'alternate_names');
$has_alternate_names_bn = specialty_form_column_exists('specialties', 'alternate_names_bn');
$has_image = specialty_form_column_exists('specialties', 'image');
$has_description_bn = specialty_form_column_exists('specialties', 'description_bn');
$has_article_content = specialty_form_column_exists('specialties', 'article_content');
$has_article_content_bn = specialty_form_column_exists('specialties', 'article_content_bn');
$has_seo_title = specialty_form_column_exists('specialties', 'seo_title');
$has_seo_title_bn = specialty_form_column_exists('specialties', 'seo_title_bn');
$has_seo_description = specialty_form_column_exists('specialties', 'seo_description');
$has_seo_description_bn = specialty_form_column_exists('specialties', 'seo_description_bn');
$has_meta_keywords = specialty_form_column_exists('specialties', 'meta_keywords');
$has_meta_keywords_bn = specialty_form_column_exists('specialties', 'meta_keywords_bn');

/*
|--------------------------------------------------------------------------
| Load Existing Specialty
|--------------------------------------------------------------------------
*/
if ($id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM specialties WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $id]);
    $edit = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$edit) {
        flash('error', 'Specialty not found.');
        redirect('specialties.php');
    }
}

/*
|--------------------------------------------------------------------------
| Save Specialty
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $post_id = (int)($_POST['id'] ?? 0);

    $name = trim((string)($_POST['name'] ?? ''));
    $name_bn = trim((string)($_POST['name_bn'] ?? ''));
    $title_name = trim((string)($_POST['title_name'] ?? ''));
    $title_name_bn = trim((string)($_POST['title_name_bn'] ?? ''));
    $alternate_names = trim((string)($_POST['alternate_names'] ?? ''));
    $alternate_names_bn = trim((string)($_POST['alternate_names_bn'] ?? ''));
    $slug = trim((string)($_POST['slug'] ?? ''));

    if ($name === '') {
        flash('error', 'Specialty name is required.');
        redirect($post_id > 0 ? 'specialty-form.php?id=' . $post_id : 'specialty-form.php');
    }

    $slug = $slug === '' ? slugify($name) : slugify($slug);

    $existing_image = '';

    if ($post_id > 0 && $has_image) {
        $image_stmt = $pdo->prepare("SELECT image FROM specialties WHERE id = :id LIMIT 1");
        $image_stmt->execute([':id' => $post_id]);
        $existing_image = (string)($image_stmt->fetchColumn() ?: '');
    }

    $image_path = $existing_image;
    $uploaded_new_image = false;
    $has_uploaded_file = isset($_FILES['image'])
        && (int)($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;

    if ($has_uploaded_file && !$has_image) {
        flash('error', 'The image column is unavailable. Please add the specialties.image database column first.');
        redirect($post_id > 0 ? 'specialty-form.php?id=' . $post_id : 'specialty-form.php');
    }

    if ($has_uploaded_file) {
        try {
            $image_path = specialty_form_store_image($_FILES['image'], $slug);
            $uploaded_new_image = $image_path !== '';
        } catch (Throwable $e) {
            flash('error', $e->getMessage());
            redirect($post_id > 0 ? 'specialty-form.php?id=' . $post_id : 'specialty-form.php');
        }
    }

    $data = [
        ':name' => $name,
        ':slug' => $slug,
        ':description' => trim((string)($_POST['description'] ?? '')),
        ':status' => $_POST['status'] ?? 'active',
    ];

    if ($has_image) {
        $data[':image'] = $image_path;
    }

    if ($has_name_bn) {
        $data[':name_bn'] = $name_bn;
    }

    if ($has_title_name) {
        $data[':title_name'] = $title_name;
    }

    if ($has_title_name_bn) {
        $data[':title_name_bn'] = $title_name_bn;
    }

    if ($has_alternate_names) {
        $data[':alternate_names'] = $alternate_names;
    }

    if ($has_alternate_names_bn) {
        $data[':alternate_names_bn'] = $alternate_names_bn;
    }

    if ($has_description_bn) {
        $data[':description_bn'] = trim((string)($_POST['description_bn'] ?? ''));
    }

    if ($has_article_content) {
        $data[':article_content'] = specialty_form_clean_article_html((string)($_POST['article_content'] ?? ''));
    }

    if ($has_article_content_bn) {
        $data[':article_content_bn'] = specialty_form_clean_article_html((string)($_POST['article_content_bn'] ?? ''));
    }

    if ($has_seo_title) {
        $data[':seo_title'] = trim((string)($_POST['seo_title'] ?? ''));
    }

    if ($has_seo_title_bn) {
        $data[':seo_title_bn'] = trim((string)($_POST['seo_title_bn'] ?? ''));
    }

    if ($has_seo_description) {
        $data[':seo_description'] = trim((string)($_POST['seo_description'] ?? ''));
    }

    if ($has_seo_description_bn) {
        $data[':seo_description_bn'] = trim((string)($_POST['seo_description_bn'] ?? ''));
    }

    if ($has_meta_keywords) {
        $data[':meta_keywords'] = trim((string)($_POST['meta_keywords'] ?? ''));
    }

    if ($has_meta_keywords_bn) {
        $data[':meta_keywords_bn'] = trim((string)($_POST['meta_keywords_bn'] ?? ''));
    }

    if ($post_id > 0) {
        $data[':id'] = $post_id;

        $sets = [
            'name = :name',
            'slug = :slug',
            'description = :description',
            'status = :status',
        ];

        if ($has_image) {
            $sets[] = 'image = :image';
        }

        if ($has_name_bn) {
            $sets[] = 'name_bn = :name_bn';
        }

        if ($has_title_name) {
            $sets[] = 'title_name = :title_name';
        }

        if ($has_title_name_bn) {
            $sets[] = 'title_name_bn = :title_name_bn';
        }

        if ($has_alternate_names) {
            $sets[] = 'alternate_names = :alternate_names';
        }

        if ($has_alternate_names_bn) {
            $sets[] = 'alternate_names_bn = :alternate_names_bn';
        }

        if ($has_description_bn) {
            $sets[] = 'description_bn = :description_bn';
        }

        if ($has_article_content) {
            $sets[] = 'article_content = :article_content';
        }

        if ($has_article_content_bn) {
            $sets[] = 'article_content_bn = :article_content_bn';
        }

        if ($has_seo_title) {
            $sets[] = 'seo_title = :seo_title';
        }

        if ($has_seo_title_bn) {
            $sets[] = 'seo_title_bn = :seo_title_bn';
        }

        if ($has_seo_description) {
            $sets[] = 'seo_description = :seo_description';
        }

        if ($has_seo_description_bn) {
            $sets[] = 'seo_description_bn = :seo_description_bn';
        }

        if ($has_meta_keywords) {
            $sets[] = 'meta_keywords = :meta_keywords';
        }

        if ($has_meta_keywords_bn) {
            $sets[] = 'meta_keywords_bn = :meta_keywords_bn';
        }

        $sql = "UPDATE specialties SET " . implode(', ', $sets) . " WHERE id = :id";
    } else {
        $columns = ['name', 'slug', 'description', 'status'];
        $values = [':name', ':slug', ':description', ':status'];

        if ($has_image) {
            $columns[] = 'image';
            $values[] = ':image';
        }

        if ($has_name_bn) {
            $columns[] = 'name_bn';
            $values[] = ':name_bn';
        }

        if ($has_title_name) {
            $columns[] = 'title_name';
            $values[] = ':title_name';
        }

        if ($has_title_name_bn) {
            $columns[] = 'title_name_bn';
            $values[] = ':title_name_bn';
        }

        if ($has_alternate_names) {
            $columns[] = 'alternate_names';
            $values[] = ':alternate_names';
        }

        if ($has_alternate_names_bn) {
            $columns[] = 'alternate_names_bn';
            $values[] = ':alternate_names_bn';
        }

        if ($has_description_bn) {
            $columns[] = 'description_bn';
            $values[] = ':description_bn';
        }

        if ($has_article_content) {
            $columns[] = 'article_content';
            $values[] = ':article_content';
        }

        if ($has_article_content_bn) {
            $columns[] = 'article_content_bn';
            $values[] = ':article_content_bn';
        }

        if ($has_seo_title) {
            $columns[] = 'seo_title';
            $values[] = ':seo_title';
        }

        if ($has_seo_title_bn) {
            $columns[] = 'seo_title_bn';
            $values[] = ':seo_title_bn';
        }

        if ($has_seo_description) {
            $columns[] = 'seo_description';
            $values[] = ':seo_description';
        }

        if ($has_seo_description_bn) {
            $columns[] = 'seo_description_bn';
            $values[] = ':seo_description_bn';
        }

        if ($has_meta_keywords) {
            $columns[] = 'meta_keywords';
            $values[] = ':meta_keywords';
        }

        if ($has_meta_keywords_bn) {
            $columns[] = 'meta_keywords_bn';
            $values[] = ':meta_keywords_bn';
        }

        $columns[] = 'created_at';
        $values[] = 'NOW()';

        $sql = "INSERT INTO specialties (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $values) . ")";
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($data);

    if ($uploaded_new_image && $existing_image !== '' && $existing_image !== $image_path) {
        specialty_form_delete_image($existing_image);
    }

    // Keep the user on this form after saving and prevent duplicate form submissions on refresh.
    // Image uploads use their own success state so the page confirms the upload clearly.
    if ($uploaded_new_image) {
        $saved_action = $post_id > 0 ? 'image-updated' : 'image-uploaded';
    } else {
        $saved_action = $post_id > 0 ? 'updated' : 'created';
    }

    $return_id = $post_id > 0 ? $post_id : (int)$pdo->lastInsertId();

    $return_url = 'specialty-form.php';
    if ($return_id > 0) {
        $return_url .= '?id=' . $return_id . '&saved=' . $saved_action;
    } else {
        $return_url .= '?saved=' . $saved_action;
    }

    /*
     * header.php may already send HTML before this POST handler runs.
     * Use a normal HTTP redirect when possible, otherwise use a small
     * browser redirect so the user still returns to this form page.
     */
    if (!headers_sent()) {
        header('Location: ' . $return_url, true, 303);
        exit;
    }

    echo '<script>window.location.replace(' . json_encode($return_url) . ');</script>';
    echo '<noscript><meta http-equiv="refresh" content="0;url=' . e($return_url) . '"></noscript>';
    exit;
}

/*
|--------------------------------------------------------------------------
| Page Data
|--------------------------------------------------------------------------
*/
$save_state = (string)($_GET['saved'] ?? '');
$save_notice = '';

if ($save_state === 'image-uploaded') {
    $save_notice = 'Specialty image uploaded and saved successfully.';
} elseif ($save_state === 'image-updated') {
    $save_notice = 'Specialty image replaced and saved successfully.';
} elseif ($save_state === 'updated') {
    $save_notice = 'Specialty updated successfully. Your changes are now saved.';
} elseif ($save_state === 'created') {
    $save_notice = 'New specialty added successfully. You can continue editing it from this page.';
}

$form_title = $edit ? 'Edit Specialty' : 'Add New Specialty';
$form_subtitle = $edit
    ? 'Update specialty English/Bangla name, image, article content, SEO content and visibility.'
    : 'Create a new doctor specialty with Bangla support, image upload, article content and SEO-ready information.';

$current_status = $edit['status'] ?? 'active';
$preview_name = $edit['name'] ?? 'Specialty Name';
$preview_name_bn = $edit['name_bn'] ?? 'বাংলা নাম';
$preview_slug = $edit['slug'] ?? 'auto-generated-slug';
$preview_description = $edit['description'] ?? 'Specialty description will appear here after you add details.';
$preview_article_content = $edit['article_content'] ?? '';
$preview_alternate_names = $edit['alternate_names'] ?? '';
$preview_image = $edit['image'] ?? '';
?>

<style>
  :root {
    --gh-canvas-default: #ffffff;
    --gh-canvas-subtle: #f6f8fa;
    --gh-border: #d0d7de;
    --gh-border-muted: #d8dee4;
    --gh-fg-default: #24292f;
    --gh-fg-muted: #57606a;
    --gh-accent: #0969da;
    --gh-accent-soft: #ddf4ff;
    --gh-success: #1a7f37;
    --gh-success-emphasis: #2da44e;
    --gh-success-soft: #dafbe1;
    --gh-danger: #cf222e;
    --gh-danger-soft: #ffebe9;
    --gh-attention: #9a6700;
    --gh-attention-soft: #fff8c5;
    --gh-radius: 6px;
  }

  body {
    background: var(--gh-canvas-subtle);
  }

  .sp-page,
  .sp-page * {
    box-sizing: border-box;
  }

  .sp-page {
    max-width: 1240px;
    margin: 0 auto;
    color: var(--gh-fg-default);
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Helvetica, Arial, sans-serif;
  }

  .sp-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 18px;
    margin-bottom: 16px;
    padding: 20px 0 16px;
    border-bottom: 1px solid var(--gh-border);
  }

  .sp-title h1 {
    margin: 0;
    color: var(--gh-fg-default);
    font-size: 24px;
    line-height: 1.25;
    font-weight: 600;
    letter-spacing: -.01em;
  }

  .sp-title p {
    margin: 6px 0 0;
    color: var(--gh-fg-muted);
    font-size: 14px;
    line-height: 1.5;
  }

  .sp-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 32px;
    padding: 5px 12px;
    border: 1px solid rgba(27, 31, 36, .15);
    border-radius: var(--gh-radius);
    background: #f6f8fa;
    color: var(--gh-fg-default);
    text-decoration: none;
    cursor: pointer;
    font-size: 14px;
    font-weight: 500;
    line-height: 20px;
    white-space: nowrap;
    transition: background-color .16s ease, border-color .16s ease, box-shadow .16s ease;
  }

  .sp-btn:hover {
    background: #f3f4f6;
    border-color: rgba(27, 31, 36, .25);
  }

  .sp-btn:focus-visible {
    outline: 2px solid var(--gh-accent);
    outline-offset: 2px;
  }

  .sp-btn-primary {
    border-color: rgba(27, 31, 36, .15);
    background: var(--gh-success-emphasis);
    color: #fff;
    font-weight: 600;
  }

  .sp-btn-primary:hover {
    background: #1f883d;
  }

  .sp-notice {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    margin: 0 0 16px;
    padding: 12px 14px;
    border: 1px solid #1a7f37;
    border-radius: var(--gh-radius);
    background: var(--gh-success-soft);
    color: #1a7f37;
    font-size: 14px;
    line-height: 1.45;
  }

  .sp-notice-mark {
    display: grid;
    flex: 0 0 20px;
    width: 20px;
    height: 20px;
    place-items: center;
    border-radius: 50%;
    background: #1a7f37;
    color: #fff;
    font-size: 12px;
    font-weight: 700;
  }

  .sp-notice strong {
    display: block;
    margin-bottom: 2px;
    color: #1a7f37;
    font-weight: 600;
  }

  .sp-notice p {
    margin: 0;
    color: #1a7f37;
  }

  .sp-layout {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 320px;
    gap: 16px;
    align-items: start;
  }

  .sp-card,
  .sp-side {
    overflow: hidden;
    border: 1px solid var(--gh-border);
    border-radius: var(--gh-radius);
    background: var(--gh-canvas-default);
    box-shadow: 0 1px 0 rgba(27, 31, 36, .04);
  }

  .sp-hero {
    padding: 20px 22px;
    color: var(--gh-fg-default);
    background: var(--gh-canvas-default);
    border-bottom: 1px solid var(--gh-border);
  }

  .sp-hero-badge {
    display: inline-flex;
    align-items: center;
    min-height: 24px;
    margin-bottom: 10px;
    padding: 2px 8px;
    border: 1px solid rgba(27, 31, 36, .15);
    border-radius: 2em;
    color: var(--gh-accent);
    background: var(--gh-accent-soft);
    font-size: 12px;
    font-weight: 600;
  }

  .sp-hero h2 {
    margin: 0;
    color: var(--gh-fg-default);
    font-size: 20px;
    line-height: 1.25;
    font-weight: 600;
    letter-spacing: -.01em;
  }

  .sp-hero p {
    max-width: 760px;
    margin: 7px 0 0;
    color: var(--gh-fg-muted);
    font-size: 14px;
    line-height: 1.55;
  }

  .sp-form-body {
    padding: 16px;
  }

  .sp-section {
    margin-bottom: 16px;
    border: 1px solid var(--gh-border);
    border-radius: var(--gh-radius);
    overflow: hidden;
    background: var(--gh-canvas-default);
  }

  .sp-section:last-child {
    margin-bottom: 0;
  }

  .sp-section-head {
    display: flex;
    gap: 10px;
    align-items: flex-start;
    padding: 14px 16px;
    border-bottom: 1px solid var(--gh-border-muted);
    background: var(--gh-canvas-subtle);
  }

  .sp-section-icon {
    flex: 0 0 auto;
    display: grid;
    width: 26px;
    height: 26px;
    place-items: center;
    border: 1px solid #b6e3ff;
    border-radius: 50%;
    color: var(--gh-accent);
    background: var(--gh-accent-soft);
    font-size: 11px;
    font-weight: 600;
  }

  .sp-section-head h3 {
    margin: 0;
    color: var(--gh-fg-default);
    font-size: 14px;
    line-height: 1.4;
    font-weight: 600;
  }

  .sp-section-head p {
    margin: 2px 0 0;
    color: var(--gh-fg-muted);
    font-size: 12px;
    line-height: 1.45;
  }

  .sp-grid,
  .sp-en-bn-row {
    display: grid;
    gap: 16px;
    padding: 16px;
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .sp-field {
    display: grid;
    gap: 6px;
    min-width: 0;
  }

  .sp-field.full {
    grid-column: 1 / -1;
  }

  .sp-field label {
    color: var(--gh-fg-default);
    font-size: 14px;
    line-height: 1.4;
    font-weight: 600;
  }

  .sp-label-bn {
    color: var(--gh-fg-muted) !important;
  }

  .sp-field small {
    color: var(--gh-fg-muted);
    font-size: 12px;
    line-height: 1.45;
  }

  .sp-field:focus-within label {
    color: var(--gh-accent);
  }

  .sp-field:focus-within .sp-label-bn {
    color: var(--gh-accent) !important;
  }

  .sp-input,
  .sp-select,
  .sp-textarea {
    width: 100%;
    min-height: 32px;
    padding: 5px 12px;
    border: 1px solid var(--gh-border);
    border-radius: var(--gh-radius);
    color: var(--gh-fg-default);
    background: var(--gh-canvas-default);
    outline: none;
    font: inherit;
    font-size: 14px;
    line-height: 20px;
    box-shadow: inset 0 1px 0 rgba(208, 215, 222, .2);
  }

  .sp-select {
    min-height: 34px;
    cursor: pointer;
  }

  .sp-textarea {
    min-height: 116px;
    resize: vertical;
    line-height: 1.55;
  }

  .sp-input::placeholder,
  .sp-textarea::placeholder {
    color: #8c959f;
  }

  .sp-input:focus,
  .sp-select:focus,
  .sp-textarea:focus {
    border-color: var(--gh-accent);
    box-shadow: 0 0 0 3px rgba(9, 105, 218, .18);
  }

  /* Premium form field treatment */
  .sp-input,
  .sp-select,
  .sp-textarea {
    border-color: #c7d1db;
    background: linear-gradient(180deg, #ffffff 0%, #fbfcfe 100%);
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, .92), 0 1px 2px rgba(27, 31, 36, .04);
    transition: border-color .16s ease, box-shadow .16s ease, background .16s ease, transform .16s ease;
  }

  .sp-input:hover,
  .sp-select:hover,
  .sp-textarea:hover {
    border-color: #8c959f;
    background: #ffffff;
  }

  .sp-input:focus,
  .sp-select:focus,
  .sp-textarea:focus {
    border-color: var(--gh-accent);
    background: #ffffff;
    box-shadow: 0 0 0 4px rgba(9, 105, 218, .13), 0 3px 8px rgba(9, 105, 218, .08);
  }

  .sp-textarea-short {
    min-height: 138px;
  }

  .sp-textarea-article {
    min-height: 300px;
    padding: 14px;
    font-size: 14px;
    line-height: 1.75;
    background-image: linear-gradient(180deg, rgba(246, 248, 250, .85), #ffffff 18%);
  }


  /* Premium HTML / CSS article editor */
  .sp-article-stack {
    display: grid;
    grid-template-columns: minmax(0, 1fr);
    gap: 18px;
    padding: 16px;
  }

  .sp-article-editor-card {
    overflow: hidden;
    border: 1px solid #c7d1db;
    border-radius: 10px;
    background: #ffffff;
    box-shadow: 0 1px 2px rgba(27, 31, 36, .04);
  }

  .sp-article-editor-card + .sp-article-editor-card {
    margin-top: 0;
  }

  .sp-article-editor-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 14px;
    padding: 14px 16px;
    border-bottom: 1px solid var(--gh-border-muted);
    background: linear-gradient(180deg, #ffffff, #f6f8fa);
  }

  .sp-article-editor-head h4 {
    margin: 0;
    color: var(--gh-fg-default);
    font-size: 14px;
    line-height: 1.35;
    font-weight: 700;
  }

  .sp-article-editor-head p {
    margin: 3px 0 0;
    color: var(--gh-fg-muted);
    font-size: 12px;
    line-height: 1.5;
  }

  .sp-html-badge {
    display: inline-flex;
    align-items: center;
    flex: 0 0 auto;
    min-height: 24px;
    padding: 2px 8px;
    border: 1px solid #b6e3ff;
    border-radius: 999px;
    background: var(--gh-accent-soft);
    color: var(--gh-accent);
    font-size: 11px;
    font-weight: 700;
    letter-spacing: .02em;
  }

  .sp-rich-editor {
    overflow: hidden;
    background: #ffffff;
  }

  .sp-rich-toolbar {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 6px;
    padding: 10px 12px;
    border-bottom: 1px solid var(--gh-border-muted);
    background: #f6f8fa;
  }

  .sp-rich-tool,
  .sp-rich-select {
    min-height: 30px;
    border: 1px solid rgba(27, 31, 36, .15);
    border-radius: 6px;
    background: #ffffff;
    color: var(--gh-fg-default);
    font: inherit;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
  }

  .sp-rich-tool {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 30px;
    padding: 4px 8px;
  }

  .sp-rich-tool.sp-rich-tool-wide {
    min-width: auto;
  }

  .sp-rich-select {
    padding: 4px 8px;
    outline: none;
  }

  .sp-rich-tool:hover,
  .sp-rich-select:hover {
    border-color: #8c959f;
    background: #ffffff;
  }

  .sp-rich-tool:focus-visible,
  .sp-rich-select:focus-visible {
    outline: 2px solid var(--gh-accent);
    outline-offset: 2px;
  }

  .sp-rich-tool.active {
    border-color: rgba(9, 105, 218, .35);
    background: var(--gh-accent-soft);
    color: var(--gh-accent);
  }

  .sp-rich-divider {
    width: 1px;
    align-self: stretch;
    min-height: 24px;
    background: var(--gh-border-muted);
  }

  .sp-rich-surface,
  .sp-rich-source {
    width: 100%;
    min-height: 360px;
    padding: 18px;
    color: var(--gh-fg-default);
    background: #ffffff;
    font-size: 15px;
    line-height: 1.8;
  }

  .sp-rich-surface {
    outline: none;
  }

  .sp-rich-surface:empty:before {
    content: attr(data-placeholder);
    color: #8c959f;
    pointer-events: none;
  }

  .sp-rich-surface h2,
  .sp-rich-surface h3,
  .sp-rich-surface h4 {
    margin: 1.15em 0 .5em;
    color: #1f2937;
    line-height: 1.35;
  }

  .sp-rich-surface p,
  .sp-rich-surface ul,
  .sp-rich-surface ol,
  .sp-rich-surface blockquote {
    margin: 0 0 1em;
  }

  .sp-rich-surface ul,
  .sp-rich-surface ol {
    padding-left: 1.45em;
  }

  .sp-rich-surface blockquote {
    padding: 10px 14px;
    border-left: 4px solid #0969da;
    background: #f6f8fa;
    color: #57606a;
  }

  .sp-rich-source {
    display: none;
    resize: vertical;
    border: 0;
    border-radius: 0;
    outline: none;
    font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
    font-size: 13px;
    line-height: 1.65;
    box-shadow: none;
  }

  .sp-rich-editor.is-source-mode .sp-rich-surface {
    display: none;
  }

  .sp-rich-editor.is-source-mode .sp-rich-source {
    display: block;
  }

  .sp-rich-editor.is-source-mode .sp-rich-toolbar [data-action="toggle-source"] {
    border-color: rgba(9, 105, 218, .35);
    background: var(--gh-accent-soft);
    color: var(--gh-accent);
  }

  .sp-rich-editor-footer {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 8px 14px;
    padding: 10px 14px;
    border-top: 1px solid var(--gh-border-muted);
    background: #f6f8fa;
    color: var(--gh-fg-muted);
    font-size: 11px;
    line-height: 1.45;
  }

  .sp-rich-editor-footer code {
    padding: 1px 4px;
    border-radius: 4px;
    background: #ffffff;
    color: #57606a;
    font-size: 11px;
  }

  .sp-rich-editor-footer strong {
    color: var(--gh-accent);
    font-weight: 700;
  }

  .sp-rich-editor .sp-field-metrics {
    padding: 0 14px 11px;
    background: #f6f8fa;
  }

  @media (max-width: 640px) {
    .sp-article-editor-head {
      align-items: flex-start;
      flex-direction: column;
    }

    .sp-rich-surface,
    .sp-rich-source {
      min-height: 300px;
      padding: 14px;
    }

    .sp-rich-toolbar {
      gap: 5px;
      padding: 8px;
    }

    .sp-rich-select {
      max-width: 136px;
    }

    .sp-rich-divider {
      display: none;
    }
  }

  .sp-textarea-seo {
    min-height: 142px;
  }

  .sp-field-metrics {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    min-height: 20px;
    color: var(--gh-fg-muted);
    font-size: 11px;
    line-height: 1.35;
  }

  .sp-field-metrics span {
    display: inline-flex;
    align-items: center;
    gap: 5px;
  }

  .sp-field-metrics .sp-metric-status {
    padding: 2px 6px;
    border: 1px solid var(--gh-border-muted);
    border-radius: 999px;
    background: var(--gh-canvas-subtle);
    color: var(--gh-fg-muted);
    font-weight: 600;
  }

  .sp-field-metrics .sp-metric-status.good {
    border-color: #a5d6b5;
    background: var(--gh-success-soft);
    color: var(--gh-success);
  }

  .sp-field-metrics .sp-metric-status.attention {
    border-color: #e8c878;
    background: var(--gh-attention-soft);
    color: var(--gh-attention);
  }

  .sp-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    padding: 16px 0 0;
  }

  .sp-side {
    position: sticky;
    top: 16px;
  }

  .sp-side-head {
    padding: 12px 16px;
    border-bottom: 1px solid var(--gh-border);
    background: var(--gh-canvas-subtle);
  }

  .sp-side-head span {
    color: var(--gh-fg-default);
    font-size: 14px;
    font-weight: 600;
  }

  .sp-preview {
    padding: 16px;
  }

  .sp-preview-card {
    padding: 16px;
    border: 1px solid var(--gh-border);
    border-radius: var(--gh-radius);
    background: var(--gh-canvas-default);
  }

  .sp-preview-image {
    position: relative;
    display: grid;
    width: 72px;
    height: 72px;
    place-items: center;
    overflow: hidden;
    margin-bottom: 12px;
    border: 1px solid #b6e3ff;
    border-radius: var(--gh-radius);
    color: var(--gh-fg-muted);
    background: var(--gh-canvas-subtle);
    font-size: 11px;
    font-weight: 600;
    text-align: center;
  }

  .sp-preview-image img {
    display: block;
    width: 100%;
    height: 100%;
    object-fit: cover;
  }

  .sp-preview-image.is-empty img {
    display: none;
  }

  .sp-preview-card h3 {
    margin: 0;
    color: var(--gh-fg-default);
    font-size: 18px;
    line-height: 1.35;
    font-weight: 600;
  }

  .sp-preview-card p {
    margin: 4px 0 0;
    color: var(--gh-fg-muted);
    font-size: 13px;
    line-height: 1.5;
  }

  .sp-status {
    display: inline-flex;
    margin-top: 12px;
    padding: 3px 8px;
    border-radius: 2em;
    font-size: 12px;
    font-weight: 600;
  }

  .sp-status.active {
    color: var(--gh-success);
    background: var(--gh-success-soft);
  }

  .sp-status.inactive {
    color: var(--gh-danger);
    background: var(--gh-danger-soft);
  }

  .sp-preview-aliases {
    margin-top: 14px;
  }

  .sp-preview-aliases > span {
    display: block;
    margin-bottom: 7px;
    color: var(--gh-fg-muted);
    font-size: 11px;
    font-weight: 600;
    letter-spacing: .04em;
    text-transform: uppercase;
  }

  .sp-alias-list {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
  }

  .sp-alias-list em {
    padding: 3px 7px;
    border: 1px solid var(--gh-border);
    border-radius: 2em;
    color: var(--gh-fg-muted);
    background: var(--gh-canvas-subtle);
    font-size: 11px;
    font-style: normal;
    line-height: 1.35;
  }

  .sp-meta {
    display: grid;
    gap: 8px;
    margin-top: 14px;
  }

  .sp-meta-item {
    padding: 10px;
    border: 1px solid var(--gh-border-muted);
    border-radius: var(--gh-radius);
    background: var(--gh-canvas-default);
  }

  .sp-meta-item span {
    display: block;
    margin-bottom: 3px;
    color: var(--gh-fg-muted);
    font-size: 11px;
    font-weight: 600;
    letter-spacing: .04em;
    text-transform: uppercase;
  }

  .sp-meta-item strong {
    color: var(--gh-fg-default);
    font-size: 13px;
    font-weight: 500;
    word-break: break-word;
  }

  .sp-help {
    margin: 0;
    padding: 0 16px 8px;
    list-style: none;
  }

  .sp-help li {
    display: flex;
    gap: 8px;
    padding: 10px 0;
    border-bottom: 1px solid var(--gh-border-muted);
    color: var(--gh-fg-muted);
    font-size: 12.5px;
    line-height: 1.5;
  }

  .sp-help li:last-child {
    border-bottom: 0;
  }

  .sp-help strong {
    color: var(--gh-accent);
    font-weight: 600;
  }

  .sp-warning {
    margin-bottom: 16px;
    padding: 12px 14px;
    border: 1px solid #d4a72c;
    border-radius: var(--gh-radius);
    color: #633c01;
    background: var(--gh-attention-soft);
    font-size: 13px;
    line-height: 1.5;
  }

  @media (max-width: 1040px) {
    .sp-layout {
      grid-template-columns: 1fr;
    }

    .sp-side {
      position: static;
    }
  }

  @media (max-width: 720px) {
    .sp-top {
      align-items: flex-start;
      flex-direction: column;
    }

    .sp-grid,
    .sp-en-bn-row {
      grid-template-columns: 1fr;
    }

    .sp-top .sp-btn {
      width: 100%;
    }

    .sp-actions {
      flex-direction: column;
    }

    .sp-actions .sp-btn {
      width: 100%;
    }
  }
</style>

<div class="sp-page">
    <div class="sp-top">
        <div class="sp-title">
            <h1><?= e($form_title) ?></h1>
            <p><?= e($form_subtitle) ?></p>
        </div>

        <a href="specialties.php" class="sp-btn">← Back to Specialties</a>
    </div>

    <?php if ($save_notice !== ''): ?>
        <div class="sp-notice" role="status" aria-live="polite">
            <span class="sp-notice-mark">✓</span>
            <div>
                <strong>Saved successfully</strong>
                <p><?= e($save_notice) ?></p>
            </div>
        </div>
    <?php endif; ?>

    <?php show_flash(); ?>

    <?php if (
        !$has_name_bn ||
        !$has_title_name ||
        !$has_title_name_bn ||
        !$has_alternate_names ||
        !$has_alternate_names_bn ||
        !$has_image ||
        !$has_description_bn ||
        !$has_article_content ||
        !$has_article_content_bn ||
        !$has_seo_title_bn ||
        !$has_seo_description_bn
    ): ?>
        <div class="sp-warning">
            Some English, Bangla or SEO fields are unavailable because their database columns could not be created automatically.
            Run the required ALTER TABLE query or check the database user's ALTER permission.
        </div>
    <?php endif; ?>

    <div class="sp-layout">
        <main class="sp-card">
            <div class="sp-hero">
                <span class="sp-hero-badge"><?= $edit ? 'Update Mode' : 'Create Mode' ?></span>
                <h2><?= $edit ? 'Update Specialty Details' : 'Create New Specialty' ?></h2>
                <p>Manage specialty English and Bangla information, specialty image, SEO fields, URL slug and frontend visibility from one clean page.</p>
            </div>

            <div class="sp-form-body">
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="id" value="<?= e((string)($edit['id'] ?? '')) ?>">

                    <section class="sp-section">
                        <div class="sp-section-head">
                            <span class="sp-section-icon">01</span>
                            <div>
                                <h3>Specialty Identity</h3>
                                <p>English name on the left, Bangla name on the right.</p>
                            </div>
                        </div>

                        <div class="sp-en-bn-row">
                            <div class="sp-field">
                                <label for="name">English Name *</label>
                                <input
                                    id="name"
                                    class="sp-input"
                                    type="text"
                                    name="name"
                                    placeholder="Example: Medicine Specialist"
                                    value="<?= e($edit['name'] ?? '') ?>"
                                    required
                                >
                                <small>Use the public specialty name that patients will understand.</small>
                            </div>

                            <div class="sp-field">
                                <label class="sp-label-bn" for="name_bn">Bangla Name</label>
                                <input
                                    id="name_bn"
                                    class="sp-input"
                                    type="text"
                                    name="name_bn"
                                    placeholder="উদাহরণ: মেডিসিন বিশেষজ্ঞ"
                                    value="<?= e($edit['name_bn'] ?? '') ?>"
                                >
                                <small>Use Bangla name for Bangla frontend and local SEO.</small>
                            </div>
                        </div>

                        <div class="sp-en-bn-row">
                            <div class="sp-field">
                                <label for="title_name">Title Name</label>
                                <input
                                    id="title_name"
                                    class="sp-input"
                                    type="text"
                                    name="title_name"
                                    placeholder="Example: Cardiologists"
                                    value="<?= e($edit['title_name'] ?? '') ?>"
                                >
                                <small>Overrides the doctor-list page title/heading (e.g. "Cardiologists in Dhaka"). Leave blank to auto-generate from the English Name.</small>
                            </div>

                            <div class="sp-field">
                                <label class="sp-label-bn" for="title_name_bn">Title Name (Bangla)</label>
                                <input
                                    id="title_name_bn"
                                    class="sp-input"
                                    type="text"
                                    name="title_name_bn"
                                    placeholder="উদাহরণ: কার্ডিওলজিস্ট"
                                    value="<?= e($edit['title_name_bn'] ?? '') ?>"
                                >
                                <small>ডাক্তার লিস্ট পেজের টাইটেল/হেডিং override করে। খালি রাখলে Bangla Name থেকে auto-generate হবে।</small>
                            </div>
                        </div>
                    </section>


                    <section class="sp-section">
                        <div class="sp-section-head">
                            <span class="sp-section-icon">02</span>
                            <div>
                                <h3>Search Names & Aliases</h3>
                                <p>Add other names patients may use when searching for this specialty.</p>
                            </div>
                        </div>

                        <div class="sp-en-bn-row">
                            <div class="sp-field">
                                <label for="alternate_names">Alternate Names</label>
                                <input
                                    id="alternate_names"
                                    class="sp-input"
                                    type="text"
                                    name="alternate_names"
                                    placeholder="Example: Heart Specialist, Heart Doctor"
                                    value="<?= e($edit['alternate_names'] ?? '') ?>"
                                >
                                <small>Separate each English search name with a comma.</small>
                            </div>

                            <div class="sp-field">
                                <label class="sp-label-bn" for="alternate_names_bn">Alternate Names Bangla</label>
                                <input
                                    id="alternate_names_bn"
                                    class="sp-input"
                                    type="text"
                                    name="alternate_names_bn"
                                    placeholder="উদাহরণ: হৃদরোগ বিশেষজ্ঞ, হার্ট ডাক্তার"
                                    value="<?= e($edit['alternate_names_bn'] ?? '') ?>"
                                >
                                <small>Use Bangla names that local patients commonly search for.</small>
                            </div>
                        </div>
                    </section>

                    <section class="sp-section">
                        <div class="sp-section-head">
                            <span class="sp-section-icon">03</span>
                            <div>
                                <h3>URL & Image</h3>
                                <p>Control SEO-friendly slug and specialty image upload.</p>
                            </div>
                        </div>

                        <div class="sp-grid">
                            <div class="sp-field">
                                <label for="slug">Slug</label>
                                <input
                                    id="slug"
                                    class="sp-input"
                                    type="text"
                                    name="slug"
                                    placeholder="Example: medicine-specialist"
                                    value="<?= e($edit['slug'] ?? '') ?>"
                                >
                                <small>Leave empty to generate automatically from English name.</small>
                            </div>

                            <div class="sp-field">
                                <label for="image">Specialty Image</label>
                                <input
                                    id="image"
                                    class="sp-input"
                                    type="file"
                                    name="image"
                                    accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                                >
                                <small>Upload JPG, PNG or WebP. It will be saved as an optimized high-quality WebP image without resizing. Maximum file size: 3 MB.</small>

                                <?php if (!empty($edit['image'])): ?>
                                    <small>Current image: <?= e((string)$edit['image']) ?></small>
                                <?php endif; ?>
                            </div>
                        </div>
                    </section>

                    <section class="sp-section">
                        <div class="sp-section-head">
                            <span class="sp-section-icon">04</span>
                            <div>
                                <h3>Short Description</h3>
                                <p>Write a concise, patient-friendly summary for specialty cards and quick previews.</p>
                            </div>
                        </div>

                        <div class="sp-en-bn-row">
                            <div class="sp-field">
                                <label for="description">Short Description</label>
                                <textarea
                                    id="description"
                                    class="sp-textarea sp-textarea-short"
                                    name="description"
                                    placeholder="Write a short patient-friendly summary for this specialty."
                                ><?= e($edit['description'] ?? '') ?></textarea>
                                <small>Recommended: 1–2 clear sentences for specialty list cards and quick page summaries.</small>
                            </div>

                            <div class="sp-field">
                                <label class="sp-label-bn" for="description_bn">Short Description Bangla</label>
                                <textarea
                                    id="description_bn"
                                    class="sp-textarea sp-textarea-short"
                                    name="description_bn"
                                    placeholder="এই specialty সম্পর্কে সংক্ষিপ্ত, রোগীবান্ধব বাংলা বিবরণ লিখুন।"
                                ><?= e($edit['description_bn'] ?? '') ?></textarea>
                                <small>Bangla short description can be used on Bangla specialty cards and quick previews.</small>
                            </div>
                        </div>
                    </section>

                    <section class="sp-section">
                        <div class="sp-section-head">
                            <span class="sp-section-icon">05</span>
                            <div>
                                <h3>Premium Article Content</h3>
                                <p>Use the visual editor for rich content, or switch to HTML/CSS mode for custom layouts and styles.</p>
                            </div>
                        </div>

                        <div class="sp-article-stack">
                            <div class="sp-article-editor-card">
                                <div class="sp-article-editor-head">
                                    <div>
                                        <h4>Article Content</h4>
                                        <p>Full English article shown on <code>/specialty/your-specialty-slug</code>.</p>
                                    </div>
                                    <span class="sp-html-badge">HTML + CSS</span>
                                </div>

                                <div class="sp-rich-editor" data-rich-editor="article_content">
                                    <div class="sp-rich-toolbar" role="toolbar" aria-label="Article Content Editor">
                                        <select class="sp-rich-select" data-action="format-block" aria-label="Text style">
                                            <option value="P">Paragraph</option>
                                            <option value="H2">Heading 2</option>
                                            <option value="H3">Heading 3</option>
                                            <option value="H4">Heading 4</option>
                                        </select>
                                        <span class="sp-rich-divider"></span>
                                        <button class="sp-rich-tool" type="button" data-command="bold" title="Bold"><strong>B</strong></button>
                                        <button class="sp-rich-tool" type="button" data-command="italic" title="Italic"><em>I</em></button>
                                        <button class="sp-rich-tool" type="button" data-command="underline" title="Underline"><u>U</u></button>
                                        <span class="sp-rich-divider"></span>
                                        <button class="sp-rich-tool" type="button" data-command="insertUnorderedList" title="Bullet list">• List</button>
                                        <button class="sp-rich-tool" type="button" data-command="insertOrderedList" title="Numbered list">1. List</button>
                                        <button class="sp-rich-tool" type="button" data-command="formatBlock" data-value="BLOCKQUOTE" title="Quote">❝</button>
                                        <span class="sp-rich-divider"></span>
                                        <button class="sp-rich-tool sp-rich-tool-wide" type="button" data-action="link">Link</button>
                                        <button class="sp-rich-tool sp-rich-tool-wide" type="button" data-action="insert-callout">Callout</button>
                                        <button class="sp-rich-tool sp-rich-tool-wide" type="button" data-action="toggle-source">&lt;/&gt; HTML/CSS</button>
                                    </div>

                                    <div
                                        id="article_content_visual"
                                        class="sp-rich-surface"
                                        contenteditable="true"
                                        data-placeholder="Write the full English article here. Use the toolbar for headings, lists, links and highlighted sections."
                                    ></div>

                                    <textarea
                                        id="article_content"
                                        class="sp-rich-source"
                                        name="article_content"
                                        placeholder="Write HTML here. CSS is supported inside a &lt;style&gt; block. Keep styles scoped to .specialty-article-content."
                                    ><?= e($edit['article_content'] ?? '') ?></textarea>

                                    <div class="sp-rich-editor-footer">
                                        <span><strong>HTML/CSS mode:</strong> supports <code>&lt;h2&gt;</code>, <code>&lt;p&gt;</code>, <code>&lt;ul&gt;</code>, <code>&lt;table&gt;</code>, images and scoped <code>&lt;style&gt;</code> blocks.</span>
                                        <span>Scripts, forms and JavaScript events are removed on save.</span>
                                    </div>
                                </div>
                            </div>

                            <div class="sp-article-editor-card">
                                <div class="sp-article-editor-head">
                                    <div>
                                        <h4>Article Content Bangla</h4>
                                        <p>Full Bangla article shown on <code>/bn/specialty/your-specialty-slug</code>.</p>
                                    </div>
                                    <span class="sp-html-badge">HTML + CSS</span>
                                </div>

                                <div class="sp-rich-editor" data-rich-editor="article_content_bn">
                                    <div class="sp-rich-toolbar" role="toolbar" aria-label="Article Content Bangla Editor">
                                        <select class="sp-rich-select" data-action="format-block" aria-label="Text style">
                                            <option value="P">Paragraph</option>
                                            <option value="H2">Heading 2</option>
                                            <option value="H3">Heading 3</option>
                                            <option value="H4">Heading 4</option>
                                        </select>
                                        <span class="sp-rich-divider"></span>
                                        <button class="sp-rich-tool" type="button" data-command="bold" title="Bold"><strong>B</strong></button>
                                        <button class="sp-rich-tool" type="button" data-command="italic" title="Italic"><em>I</em></button>
                                        <button class="sp-rich-tool" type="button" data-command="underline" title="Underline"><u>U</u></button>
                                        <span class="sp-rich-divider"></span>
                                        <button class="sp-rich-tool" type="button" data-command="insertUnorderedList" title="Bullet list">• List</button>
                                        <button class="sp-rich-tool" type="button" data-command="insertOrderedList" title="Numbered list">1. List</button>
                                        <button class="sp-rich-tool" type="button" data-command="formatBlock" data-value="BLOCKQUOTE" title="Quote">❝</button>
                                        <span class="sp-rich-divider"></span>
                                        <button class="sp-rich-tool sp-rich-tool-wide" type="button" data-action="link">Link</button>
                                        <button class="sp-rich-tool sp-rich-tool-wide" type="button" data-action="insert-callout">Callout</button>
                                        <button class="sp-rich-tool sp-rich-tool-wide" type="button" data-action="toggle-source">&lt;/&gt; HTML/CSS</button>
                                    </div>

                                    <div
                                        id="article_content_bn_visual"
                                        class="sp-rich-surface"
                                        contenteditable="true"
                                        data-placeholder="এখানে সম্পূর্ণ বাংলা article লিখুন। Heading, list, link এবং highlight section যোগ করতে toolbar ব্যবহার করুন।"
                                    ></div>

                                    <textarea
                                        id="article_content_bn"
                                        class="sp-rich-source"
                                        name="article_content_bn"
                                        placeholder="এখানে HTML লিখুন। &lt;style&gt; block-এর ভিতরে CSS ব্যবহার করা যাবে। CSS .specialty-article-content-এর মধ্যে সীমাবদ্ধ রাখুন।"
                                    ><?= e($edit['article_content_bn'] ?? '') ?></textarea>

                                    <div class="sp-rich-editor-footer">
                                        <span><strong>HTML/CSS mode:</strong> <code>&lt;h2&gt;</code>, <code>&lt;p&gt;</code>, <code>&lt;ul&gt;</code>, <code>&lt;table&gt;</code>, image ও scoped <code>&lt;style&gt;</code> block ব্যবহার করা যাবে।</span>
                                        <span>Save করার সময় script, form ও JavaScript event বাদ যাবে।</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="sp-section">
                        <div class="sp-section-head">
                            <span class="sp-section-icon">06</span>
                            <div>
                                <h3>SEO Information</h3>
                                <p>Search result title, meta description and internal keyword notes.</p>
                            </div>
                        </div>

                        <div class="sp-en-bn-row">
                            <div class="sp-field">
                                <label for="seo_title">SEO Title</label>
                                <input
                                    id="seo_title"
                                    class="sp-input"
                                    type="text"
                                    name="seo_title"
                                    placeholder="Example: Best Medicine Specialist Doctors in Bangladesh"
                                    value="<?= e($edit['seo_title'] ?? '') ?>"
                                >
                                <small>Keep it clear and focused for Google result titles.</small>
                            </div>

                            <div class="sp-field">
                                <label class="sp-label-bn" for="seo_title_bn">SEO Title Bangla</label>
                                <input
                                    id="seo_title_bn"
                                    class="sp-input"
                                    type="text"
                                    name="seo_title_bn"
                                    placeholder="বাংলা SEO title লিখুন"
                                    value="<?= e($edit['seo_title_bn'] ?? '') ?>"
                                >
                                <small>Use Bangla SEO title for Bangla pages.</small>
                            </div>
                        </div>

                        <div class="sp-en-bn-row">
                            <div class="sp-field">
                                <label for="seo_description">SEO Description</label>
                                <textarea
                                    id="seo_description"
                                    class="sp-textarea sp-textarea-seo"
                                    name="seo_description"
                                    placeholder="Write an SEO-friendly meta description."
                                ><?= e($edit['seo_description'] ?? '') ?></textarea>
                                <small>Recommended: 140–165 characters. Keep it useful, direct and location-independent.</small>
                            </div>

                            <div class="sp-field">
                                <label class="sp-label-bn" for="seo_description_bn">SEO Description Bangla</label>
                                <textarea
                                    id="seo_description_bn"
                                    class="sp-textarea sp-textarea-seo"
                                    name="seo_description_bn"
                                    placeholder="বাংলা SEO description লিখুন।"
                                ><?= e($edit['seo_description_bn'] ?? '') ?></textarea>
                                <small>Recommended: 140–165 characters for Bangla search result previews.</small>
                            </div>
                        </div>

                        <div class="sp-en-bn-row">
                            <div class="sp-field">
                                <label for="meta_keywords">Meta Keywords</label>
                                <input
                                    id="meta_keywords"
                                    class="sp-input"
                                    type="text"
                                    name="meta_keywords"
                                    placeholder="medicine specialist, doctor, Bangladesh"
                                    value="<?= e($edit['meta_keywords'] ?? '') ?>"
                                >
                                <small>Comma separated keywords.</small>
                            </div>

                            <div class="sp-field">
                                <label class="sp-label-bn" for="meta_keywords_bn">Meta Keywords Bangla</label>
                                <input
                                    id="meta_keywords_bn"
                                    class="sp-input"
                                    type="text"
                                    name="meta_keywords_bn"
                                    placeholder="মেডিসিন বিশেষজ্ঞ, ডাক্তার, বাংলাদেশ"
                                    value="<?= e($edit['meta_keywords_bn'] ?? '') ?>"
                                >
                                <small>Bangla comma separated keywords.</small>
                            </div>
                        </div>
                    </section>

                    <section class="sp-section">
                        <div class="sp-section-head">
                            <span class="sp-section-icon">07</span>
                            <div>
                                <h3>Visibility</h3>
                                <p>Control whether this specialty should be active or inactive.</p>
                            </div>
                        </div>

                        <div class="sp-grid">
                            <div class="sp-field">
                                <label for="status">Status</label>
                                <select id="status" class="sp-select" name="status">
                                    <option value="active" <?= ($current_status === 'active') ? 'selected' : '' ?>>Active</option>
                                    <option value="inactive" <?= ($current_status === 'inactive') ? 'selected' : '' ?>>Inactive</option>
                                </select>
                                <small>Inactive specialties stay saved but should not show publicly.</small>
                            </div>
                        </div>
                    </section>

                    <div class="sp-actions">
                        <button class="sp-btn sp-btn-primary" type="submit">
                            <?= $edit ? 'Update Specialty' : 'Save Specialty' ?>
                        </button>

                        <a href="specialties.php" class="sp-btn">Cancel</a>
                    </div>
                </form>
            </div>
        </main>

        <aside class="sp-side">
            <div class="sp-side-head">
                <span>Live Preview</span>
            </div>

            <div class="sp-preview">
                <div class="sp-preview-card">
                    <div
                        id="previewImageWrap"
                        class="sp-preview-image <?= $preview_image === '' ? 'is-empty' : '' ?>"
                    >
                        <img
                            id="previewImage"
                            src="<?= $preview_image !== '' ? e($preview_image) : '' ?>"
                            alt=""
                        >
                        <span id="previewImagePlaceholder">No image</span>
                    </div>

                    <h3 id="previewName"><?= e($preview_name) ?></h3>
                    <p id="previewNameBn"><?= e($preview_name_bn) ?></p>

                    <span
                        id="previewStatus"
                        class="sp-status <?= e($current_status === 'inactive' ? 'inactive' : 'active') ?>"
                    >
                        <?= e(ucfirst($current_status)) ?>
                    </span>

                    <div class="sp-preview-aliases" id="previewAliasesWrap">
                        <span>Also known as</span>
                        <div class="sp-alias-list" id="previewAliases">
                            <?php foreach (array_filter(array_map('trim', explode(',', (string)$preview_alternate_names))) as $alias): ?>
                                <em><?= e($alias) ?></em>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="sp-meta">
                        <div class="sp-meta-item">
                            <span>Slug</span>
                            <strong id="previewSlug"><?= e($preview_slug) ?></strong>
                        </div>

                        <div class="sp-meta-item">
                            <span>Article URL</span>
                            <strong id="previewUrl">/specialty/<?= e($preview_slug) ?></strong>
                        </div>

                        <div class="sp-meta-item">
                            <span>Doctor List URL</span>
                            <strong id="previewDoctorUrl">/doctors/<?= e($preview_slug) ?></strong>
                        </div>

                        <div class="sp-meta-item">
                            <span>Short Description</span>
                            <strong id="previewDescription"><?= e(specialty_form_excerpt((string)$preview_description, 90)) ?></strong>
                        </div>
                    </div>
                </div>
            </div>

            <ul class="sp-help">
                <li><strong>01</strong> Use a clear public specialty name patients will understand.</li>
                <li><strong>02</strong> Add English and Bangla aliases for common patient search terms.</li>
                <li><strong>03</strong> Keep the slug lowercase, readable and URL-friendly.</li>
                <li><strong>04</strong> Use the Article Content editor for the full specialty page. Choose HTML/CSS mode for custom blocks and scoped styles.</li>
                <li><strong>05</strong> Add SEO content only when it improves the page for real users.</li>
            </ul>
        </aside>
    </div>
</div>


<script>
  document.addEventListener('DOMContentLoaded', function () {
    const get = function (id) {
      return document.getElementById(id);
    };

    const fields = {
      name: get('name'),
      nameBn: get('name_bn'),
      aliases: get('alternate_names'),
      slug: get('slug'),
      image: get('image'),
      description: get('description'),
      articleContent: get('article_content'),
      articleContentBn: get('article_content_bn'),
      status: get('status')
    };

    const preview = {
      name: get('previewName'),
      nameBn: get('previewNameBn'),
      aliases: get('previewAliases'),
      aliasesWrap: get('previewAliasesWrap'),
      slug: get('previewSlug'),
      url: get('previewUrl'),
      doctorUrl: get('previewDoctorUrl'),
      image: get('previewImage'),
      imageWrap: get('previewImageWrap'),
      imagePlaceholder: get('previewImagePlaceholder'),
      description: get('previewDescription'),
      status: get('previewStatus')
    };

    const textFieldOptions = {
      description: { label: 'Short summary', min: 40, max: 260 },
      description_bn: { label: 'সংক্ষিপ্ত বিবরণ', min: 40, max: 260 },
      article_content: { label: 'Article content', min: 300, max: 0 },
      article_content_bn: { label: 'Article content', min: 300, max: 0 },
      seo_title: { label: 'SEO title', min: 35, max: 65 },
      seo_title_bn: { label: 'SEO title', min: 35, max: 75 },
      seo_description: { label: 'Meta description', min: 140, max: 165 },
      seo_description_bn: { label: 'Meta description', min: 140, max: 180 },
      meta_keywords: { label: 'Keyword notes', min: 0, max: 255 },
      meta_keywords_bn: { label: 'Keyword notes', min: 0, max: 255 }
    };

    const createFieldMetrics = function (field) {
      const options = textFieldOptions[field.id];

      if (!options || field.parentNode.querySelector('[data-metrics-for="' + field.id + '"]')) {
        return;
      }

      const metrics = document.createElement('div');
      metrics.className = 'sp-field-metrics';
      metrics.setAttribute('data-metrics-for', field.id);
      metrics.innerHTML = '<span class="sp-metric-count"></span><span class="sp-metric-status"></span>';
      field.parentNode.appendChild(metrics);

      const updateMetrics = function () {
        const count = field.value.trim().length;
        const words = field.value.trim() ? field.value.trim().split(/\s+/).length : 0;
        const countNode = metrics.querySelector('.sp-metric-count');
        const statusNode = metrics.querySelector('.sp-metric-status');
        let status = 'Optional';
        let statusClass = '';

        countNode.textContent = count + ' characters · ' + words + ' words';

        if (count === 0) {
          status = 'Optional';
        } else if (options.max > 0 && count > options.max) {
          status = 'Too long';
          statusClass = 'attention';
        } else if (options.min > 0 && count < options.min) {
          status = 'Needs more detail';
          statusClass = 'attention';
        } else {
          status = 'Looks good';
          statusClass = 'good';
        }

        statusNode.textContent = status;
        statusNode.className = 'sp-metric-status ' + statusClass;
      };

      field.addEventListener('input', updateMetrics);
      updateMetrics();
    };

    Object.keys(textFieldOptions).forEach(function (fieldId) {
      const field = get(fieldId);

      if (field) {
        createFieldMetrics(field);
      }
    });

    const richEditorConfigs = [
      { sourceId: 'article_content', surfaceId: 'article_content_visual' },
      { sourceId: 'article_content_bn', surfaceId: 'article_content_bn_visual' }
    ];

    const plainTextToHtml = function (value) {
      const text = String(value || '').trim();

      if (text === '') {
        return '';
      }

      if (/<\/?[a-z][\s\S]*>/i.test(text)) {
        return text;
      }

      return text
        .split(/(?:\r\n|\r|\n){2,}/)
        .map(function (paragraph) {
          return '<p>' + paragraph
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/\r?\n/g, '<br>') + '</p>';
        })
        .join('');
    };

    const setupRichEditor = function (config) {
      const source = get(config.sourceId);
      const surface = get(config.surfaceId);
      const editor = source ? source.closest('.sp-rich-editor') : null;

      if (!source || !surface || !editor) {
        return;
      }

      surface.innerHTML = plainTextToHtml(source.value);

      const syncFromVisual = function () {
        source.value = surface.innerHTML.trim();
        source.dispatchEvent(new Event('input', { bubbles: true }));
      };

      const syncFromSource = function () {
        surface.innerHTML = plainTextToHtml(source.value);
        source.dispatchEvent(new Event('input', { bubbles: true }));
      };

      const runCommand = function (command, value) {
        surface.focus();
        document.execCommand(command, false, value || null);
        syncFromVisual();
      };

      editor.querySelectorAll('[data-command]').forEach(function (button) {
        button.addEventListener('mousedown', function (event) {
          event.preventDefault();
        });

        button.addEventListener('click', function () {
          runCommand(button.getAttribute('data-command'), button.getAttribute('data-value'));
        });
      });

      const formatSelect = editor.querySelector('[data-action="format-block"]');
      if (formatSelect) {
        formatSelect.addEventListener('change', function () {
          runCommand('formatBlock', '<' + formatSelect.value + '>');
          formatSelect.value = 'P';
        });
      }

      editor.querySelectorAll('[data-action="link"]').forEach(function (button) {
        button.addEventListener('mousedown', function (event) {
          event.preventDefault();
        });

        button.addEventListener('click', function () {
          const url = window.prompt('Enter the full link URL, including https://');

          if (url && /^https?:\/\//i.test(url.trim())) {
            runCommand('createLink', url.trim());
          }
        });
      });

      editor.querySelectorAll('[data-action="insert-callout"]').forEach(function (button) {
        button.addEventListener('mousedown', function (event) {
          event.preventDefault();
        });

        button.addEventListener('click', function () {
          surface.focus();
          const block = '<div class="specialty-callout"><strong>Important:</strong> Write your helpful patient information here.</div><p><br></p>';
          document.execCommand('insertHTML', false, block);
          syncFromVisual();
        });
      });

      editor.querySelectorAll('[data-action="toggle-source"]').forEach(function (button) {
        button.addEventListener('click', function () {
          const sourceMode = editor.classList.toggle('is-source-mode');

          if (sourceMode) {
            syncFromVisual();
            source.focus();
          } else {
            syncFromSource();
            surface.focus();
          }
        });
      });

      surface.addEventListener('input', syncFromVisual);
      source.addEventListener('input', function () {
        if (editor.classList.contains('is-source-mode')) {
          syncFromSource();
        }
      });

      const form = source.closest('form');
      if (form && !form.dataset.richEditorBound) {
        form.dataset.richEditorBound = '1';
        form.addEventListener('submit', function () {
          document.querySelectorAll('.sp-rich-editor').forEach(function (currentEditor) {
            if (!currentEditor.classList.contains('is-source-mode')) {
              const currentSurface = currentEditor.querySelector('.sp-rich-surface');
              const currentSource = currentEditor.querySelector('.sp-rich-source');

              if (currentSurface && currentSource) {
                currentSource.value = currentSurface.innerHTML.trim();
              }
            }
          });
        });
      }
    };

    richEditorConfigs.forEach(setupRichEditor);

    const makeSlug = function (value) {
      return value
        .toLowerCase()
        .trim()
        .replace(/[^a-z0-9\s-]/g, '')
        .replace(/\s+/g, '-')
        .replace(/-+/g, '-')
        .replace(/^-|-$/g, '');
    };

    const updateAliases = function (value) {
      const aliases = value
        .split(',')
        .map(function (item) {
          return item.trim();
        })
        .filter(Boolean);

      preview.aliases.innerHTML = '';

      aliases.forEach(function (alias) {
        const tag = document.createElement('em');
        tag.textContent = alias;
        preview.aliases.appendChild(tag);
      });

      preview.aliasesWrap.style.display = aliases.length ? '' : 'none';
    };

    const updatePreview = function () {
      const name = fields.name.value.trim() || 'Specialty Name';
      const nameBn = fields.nameBn.value.trim() || 'বাংলা নাম';
      const typedSlug = fields.slug.value.trim();
      const slug = typedSlug || makeSlug(name) || 'auto-generated-slug';
      const description = fields.description.value.trim()
        || 'Short specialty summary will appear here after you add details.';
      const status = fields.status.value === 'inactive' ? 'inactive' : 'active';

      preview.name.textContent = name;
      preview.nameBn.textContent = nameBn;
      preview.slug.textContent = slug;
      preview.url.textContent = '/specialty/' + slug;
      preview.doctorUrl.textContent = '/doctors/' + slug;
      preview.description.textContent = description.length > 90
        ? description.slice(0, 87).trim() + '...'
        : description;
      preview.status.textContent = status.charAt(0).toUpperCase() + status.slice(1);
      preview.status.className = 'sp-status ' + status;

      updateAliases(fields.aliases.value);
    };

    if (fields.image) {
      fields.image.addEventListener('change', function () {
        const file = fields.image.files && fields.image.files[0];

        if (!file) {
          return;
        }

        if (!/^image\/(jpeg|png|webp)$/i.test(file.type)) {
          window.alert('Please select a JPG, PNG or WebP image.');
          fields.image.value = '';
          return;
        }

        if (file.size > (3 * 1024 * 1024)) {
          window.alert('Please select an image smaller than 3 MB.');
          fields.image.value = '';
          return;
        }

        const reader = new FileReader();

        reader.onload = function (event) {
          preview.image.src = event.target.result;
          preview.imageWrap.classList.remove('is-empty');
          preview.imagePlaceholder.style.display = 'none';
        };

        reader.readAsDataURL(file);
      });
    }

    Object.keys(fields).forEach(function (key) {
      const field = fields[key];

      if (!field || key === 'image') {
        return;
      }

      field.addEventListener('input', updatePreview);
      field.addEventListener('change', updatePreview);
    });

    updatePreview();
  });
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
