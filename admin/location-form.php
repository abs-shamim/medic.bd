<?php
require_once __DIR__ . '/includes/header.php';

/*
|--------------------------------------------------------------------------
| Location Form - Clean EN/BN Design
|--------------------------------------------------------------------------
| Supports:
| - Division
| - District
| - Thana / Upazila
|
| Layout:
| - English left, Bangla right
| - Parent relation where needed
| - Slug, image + status
| - Live preview
*/

$allowed_location_types = ['division', 'district', 'thana'];
$type = $_GET['type'] ?? ($_POST['type'] ?? 'division');
$type = in_array($type, $allowed_location_types, true) ? $type : 'division';

$tables = [
    'division' => 'divisions',
    'district' => 'districts',
    'thana' => 'thanas',
];

$labels = [
    'division' => 'Division',
    'district' => 'District',
    'thana' => 'Thana / Upazila',
];

$labels_bn = [
    'division' => 'বিভাগ',
    'district' => 'জেলা',
    'thana' => 'থানা / উপজেলা',
];

function admin_location_slug(string $name, string $manual_slug = ''): string
{
    $manual_slug = trim($manual_slug);

    return $manual_slug !== '' ? slugify($manual_slug) : slugify($name);
}


/*
|--------------------------------------------------------------------------
| Location Image Helpers
|--------------------------------------------------------------------------
| - Stores division, district and thana images in WebP format.
| - Keeps the original image width and height. No crop or resize is applied.
| - File format: location-name-yymmddtime.webp
| - PNG and WebP transparency is preserved.
|--------------------------------------------------------------------------
*/
if (!function_exists('location_form_table_exists')) {
    function location_form_table_exists(string $table): bool
    {
        global $pdo;

        try {
            $stmt = $pdo->prepare("\n                SELECT COUNT(*)\n                FROM INFORMATION_SCHEMA.TABLES\n                WHERE TABLE_SCHEMA = DATABASE()\n                  AND TABLE_NAME = :table\n            ");
            $stmt->execute([':table' => $table]);

            return (int)$stmt->fetchColumn() > 0;
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('location_form_column_exists')) {
    function location_form_column_exists(string $table, string $column): bool
    {
        global $pdo;

        try {
            $stmt = $pdo->prepare("\n                SELECT COUNT(*)\n                FROM INFORMATION_SCHEMA.COLUMNS\n                WHERE TABLE_SCHEMA = DATABASE()\n                  AND TABLE_NAME = :table\n                  AND COLUMN_NAME = :column\n            ");
            $stmt->execute([
                ':table' => $table,
                ':column' => $column,
            ]);

            return (int)$stmt->fetchColumn() > 0;
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('location_form_add_column_if_missing')) {
    function location_form_add_column_if_missing(string $table, string $column, string $definition): void
    {
        global $pdo;

        if (!location_form_table_exists($table) || location_form_column_exists($table, $column)) {
            return;
        }

        try {
            $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
        } catch (Throwable $e) {
            // The form remains usable even when the database user cannot alter the table.
        }
    }
}

if (!function_exists('location_form_boot_image_columns')) {
    function location_form_boot_image_columns(): void
    {
        foreach (['divisions', 'districts', 'thanas'] as $table) {
            location_form_add_column_if_missing($table, 'image', "VARCHAR(500) NULL AFTER `slug`");
        }
    }
}

if (!function_exists('location_form_create_image_resource')) {
    function location_form_create_image_resource(string $tmp_name, string $mime)
    {
        return match ($mime) {
            'image/jpeg' => function_exists('imagecreatefromjpeg') ? @imagecreatefromjpeg($tmp_name) : false,
            'image/png'  => function_exists('imagecreatefrompng') ? @imagecreatefrompng($tmp_name) : false,
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($tmp_name) : false,
            default      => false,
        };
    }
}

if (!function_exists('location_form_apply_jpeg_orientation')) {
    function location_form_apply_jpeg_orientation($image, string $tmp_name)
    {
        if (!function_exists('exif_read_data') || !function_exists('imagerotate')) {
            return $image;
        }

        $exif = @exif_read_data($tmp_name);
        $orientation = (int)($exif['Orientation'] ?? 1);

        if ($orientation === 3) {
            return imagerotate($image, 180, 0);
        }

        if ($orientation === 6) {
            return imagerotate($image, -90, 0);
        }

        if ($orientation === 8) {
            return imagerotate($image, 90, 0);
        }

        return $image;
    }
}

if (!function_exists('location_form_store_webp_image')) {
    function location_form_store_webp_image(array $file, string $slug): string
    {
        $upload_error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);

        if ($upload_error === UPLOAD_ERR_NO_FILE) {
            return '';
        }

        if ($upload_error !== UPLOAD_ERR_OK || empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            throw new RuntimeException('The location image could not be uploaded. Please try again.');
        }

        $max_bytes = 5 * 1024 * 1024;

        if ((int)($file['size'] ?? 0) <= 0 || (int)$file['size'] > $max_bytes) {
            throw new RuntimeException('Please upload an image smaller than 5 MB.');
        }

        if (!function_exists('imagewebp')) {
            throw new RuntimeException('WebP conversion is unavailable. Enable the PHP GD extension with WebP support on this server.');
        }

        $image_info = @getimagesize($file['tmp_name']);

        if ($image_info === false) {
            throw new RuntimeException('Please upload a valid image file.');
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = (string)$finfo->file($file['tmp_name']);
        $allowed_types = ['image/jpeg', 'image/png', 'image/webp'];

        if (!in_array($mime, $allowed_types, true)) {
            throw new RuntimeException('Only JPG, PNG and WebP images are allowed.');
        }

        $source = location_form_create_image_resource($file['tmp_name'], $mime);

        if ($source === false) {
            throw new RuntimeException('This image could not be processed. Confirm that PHP GD supports JPEG, PNG and WebP.');
        }

        if ($mime === 'image/jpeg') {
            $oriented = location_form_apply_jpeg_orientation($source, $file['tmp_name']);

            if (
                $oriented !== $source
                && (
                    is_resource($source)
                    || (is_object($source) && get_class($source) === 'GdImage')
                )
            ) {
                imagedestroy($source);
            }

            $source = $oriented;
        }

        // Preserve PNG/WebP alpha without changing image dimensions.
        imagepalettetotruecolor($source);
        imagealphablending($source, true);
        imagesavealpha($source, true);

        $upload_dir = dirname(__DIR__) . '/assets/uploads/locations';

        if (!is_dir($upload_dir) && !mkdir($upload_dir, 0755, true) && !is_dir($upload_dir)) {
            imagedestroy($source);
            throw new RuntimeException('The location image folder could not be created.');
        }

        $safe_slug = trim((string)preg_replace('/[^a-z0-9-]+/i', '-', $slug), '-');
        $safe_slug = $safe_slug !== '' ? $safe_slug : 'location';

        // No random suffix: location-name-yymmddtime.webp. Increment seconds only if a file collision occurs.
        $timestamp = time();
        do {
            $filename = $safe_slug . '-' . date('ymdHis', $timestamp) . '.webp';
            $destination = $upload_dir . '/' . $filename;
            $timestamp++;
        } while (is_file($destination));

        /*
         * Adaptive WebP compression:
         * - Small images keep a little more detail.
         * - Larger images use a slightly lower quality to reduce file size.
         * - Width and height remain unchanged; no crop or resize is applied.
         */
        $pixels = max(1, (int)$image_info[0] * (int)$image_info[1]);

        if ($pixels <= 1200000) {
            $webp_quality = 88;
        } elseif ($pixels <= 4000000) {
            $webp_quality = 84;
        } else {
            $webp_quality = 82;
        }

        $saved = @imagewebp($source, $destination, $webp_quality);
        imagedestroy($source);

        if (!$saved) {
            throw new RuntimeException('The location image could not be converted to WebP.');
        }

        @chmod($destination, 0644);

        return '/assets/uploads/locations/' . $filename;
    }
}

if (!function_exists('location_form_delete_image')) {
    function location_form_delete_image(string $public_path): void
    {
        $prefix = '/assets/uploads/locations/';

        if (strpos($public_path, $prefix) !== 0) {
            return;
        }

        $file_path = dirname(__DIR__) . $public_path;

        if (is_file($file_path)) {
            @unlink($file_path);
        }
    }
}

location_form_boot_image_columns();

$id = (int)($_GET['id'] ?? 0);
$edit = null;
$table = $tables[$type];

/*
|--------------------------------------------------------------------------
| Load Existing Location
|--------------------------------------------------------------------------
*/
if ($id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM {$table} WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $id]);
    $edit = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$edit) {
        flash('error', $labels[$type] . ' not found.');
        redirect('locations.php?type=' . $type);
    }
}

/*
|--------------------------------------------------------------------------
| Parent Data
|--------------------------------------------------------------------------
*/
$divisions = $pdo->query("SELECT * FROM divisions ORDER BY name_en ASC")->fetchAll(PDO::FETCH_ASSOC);

$districts = $pdo->query("
    SELECT d.*, v.name_en AS division_name, v.name_bn AS division_name_bn
    FROM districts d
    LEFT JOIN divisions v ON v.id = d.division_id
    ORDER BY d.name_en ASC
")->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Save Location
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $post_id = (int)($_POST['id'] ?? 0);

    $name_en = trim((string)($_POST['name_en'] ?? ''));
    $name_bn = trim((string)($_POST['name_bn'] ?? ''));
    $slug = admin_location_slug($name_en ?: $name_bn, (string)($_POST['slug'] ?? ''));
    $status = $_POST['status'] ?? 'active';
    $has_image_column = location_form_column_exists($table, 'image');

    if (!$has_image_column) {
        flash('error', 'The image column is unavailable. Please import the location image SQL file or allow ALTER TABLE permission.');
        redirect($post_id > 0 ? 'location-form.php?type=' . $type . '&id=' . $post_id : 'location-form.php?type=' . $type);
    }

    if ($name_en === '') {
        flash('error', 'English name is required.');
        redirect($post_id > 0 ? 'location-form.php?type=' . $type . '&id=' . $post_id : 'location-form.php?type=' . $type);
    }

    if ($name_bn === '') {
        flash('error', 'Bangla name is required.');
        redirect($post_id > 0 ? 'location-form.php?type=' . $type . '&id=' . $post_id : 'location-form.php?type=' . $type);
    }

    $existing_image = '';

    if ($post_id > 0 && $has_image_column) {
        $image_stmt = $pdo->prepare("SELECT image FROM {$table} WHERE id = :id LIMIT 1");
        $image_stmt->execute([':id' => $post_id]);
        $existing_image = (string)($image_stmt->fetchColumn() ?: '');
    }

    $image_path = $existing_image;
    $uploaded_new_image = false;
    $has_uploaded_file = isset($_FILES['image'])
        && (int)($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;

    if ($has_uploaded_file && !$has_image_column) {
        flash('error', 'The image column is unavailable. Please import the location image SQL file or allow ALTER TABLE permission.');
        redirect($post_id > 0 ? 'location-form.php?type=' . $type . '&id=' . $post_id : 'location-form.php?type=' . $type);
    }

    if ($has_uploaded_file) {
        try {
            $image_path = location_form_store_webp_image($_FILES['image'], $slug);
            $uploaded_new_image = $image_path !== '';
        } catch (Throwable $e) {
            flash('error', $e->getMessage());
            redirect($post_id > 0 ? 'location-form.php?type=' . $type . '&id=' . $post_id : 'location-form.php?type=' . $type);
        }
    }

    $data = [
        ':name_en' => $name_en,
        ':name_bn' => $name_bn,
        ':slug' => $slug,
        ':status' => $status,
    ];

    if ($has_image_column) {
        $data[':image'] = $image_path;
    }

    if ($type === 'division') {
        if ($post_id > 0) {
            $data[':id'] = $post_id;

            $sql = "
                UPDATE divisions
                SET name_en = :name_en,
                    name_bn = :name_bn,
                    slug = :slug,
                    image = :image,
                    status = :status
                WHERE id = :id
            ";
        } else {
            $sql = "
                INSERT INTO divisions (name_en, name_bn, slug, image, status, created_at)
                VALUES (:name_en, :name_bn, :slug, :image, :status, NOW())
            ";
        }
    } elseif ($type === 'district') {
        $division_id = (int)($_POST['division_id'] ?? 0);

        if ($division_id <= 0) {
            flash('error', 'Please select a division.');
            redirect($post_id > 0 ? 'location-form.php?type=district&id=' . $post_id : 'location-form.php?type=district');
        }

        $data[':division_id'] = $division_id;

        if ($post_id > 0) {
            $data[':id'] = $post_id;

            $sql = "
                UPDATE districts
                SET division_id = :division_id,
                    name_en = :name_en,
                    name_bn = :name_bn,
                    slug = :slug,
                    image = :image,
                    status = :status
                WHERE id = :id
            ";
        } else {
            $sql = "
                INSERT INTO districts (division_id, name_en, name_bn, slug, image, status, created_at)
                VALUES (:division_id, :name_en, :name_bn, :slug, :image, :status, NOW())
            ";
        }
    } else {
        $district_id = (int)($_POST['district_id'] ?? 0);

        if ($district_id <= 0) {
            flash('error', 'Please select a district.');
            redirect($post_id > 0 ? 'location-form.php?type=thana&id=' . $post_id : 'location-form.php?type=thana');
        }

        $data[':district_id'] = $district_id;

        if ($post_id > 0) {
            $data[':id'] = $post_id;

            $sql = "
                UPDATE thanas
                SET district_id = :district_id,
                    name_en = :name_en,
                    name_bn = :name_bn,
                    slug = :slug,
                    image = :image,
                    status = :status
                WHERE id = :id
            ";
        } else {
            $sql = "
                INSERT INTO thanas (district_id, name_en, name_bn, slug, image, status, created_at)
                VALUES (:district_id, :name_en, :name_bn, :slug, :image, :status, NOW())
            ";
        }
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($data);

    if ($uploaded_new_image && $existing_image !== '' && $existing_image !== $image_path) {
        location_form_delete_image($existing_image);
    }

    /*
     * Stay on the same page after saving so the administrator can immediately
     * see a clear success message and confirm the uploaded image in preview.
     */
    $return_id = $post_id > 0 ? $post_id : (int)$pdo->lastInsertId();

    if ($uploaded_new_image) {
        $saved_action = $post_id > 0 ? 'image-updated' : 'image-uploaded';
    } else {
        $saved_action = $post_id > 0 ? 'updated' : 'created';
    }

    $return_url = 'location-form.php?type=' . rawurlencode($type)
        . '&id=' . $return_id
        . '&saved=' . rawurlencode($saved_action);

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
    $save_notice = $labels[$type] . ' image uploaded and saved successfully.';
} elseif ($save_state === 'image-updated') {
    $save_notice = $labels[$type] . ' image replaced and saved successfully.';
} elseif ($save_state === 'updated') {
    $save_notice = $labels[$type] . ' updated successfully. Your changes are now saved.';
} elseif ($save_state === 'created') {
    $save_notice = 'New ' . $labels[$type] . ' added successfully. You can continue editing it from this page.';
}

$form_title = $edit ? 'Edit ' . $labels[$type] : 'Add ' . $labels[$type];
$form_subtitle = $edit
    ? 'Update location name, image, parent relation, slug and publishing status.'
    : 'Create a clean location entry with image support for filtering, doctor directory URLs and SEO.';

$current_status = $edit['status'] ?? 'active';
$preview_name = $edit['name_en'] ?? $labels[$type];
$preview_bn = $edit['name_bn'] ?? $labels_bn[$type];
$preview_slug = $edit['slug'] ?? 'auto-generated-slug';
$preview_image = $edit['image'] ?? '';

$parent_label = '';
$parent_value = '';

if ($type === 'district' && !empty($edit['division_id'])) {
    foreach ($divisions as $division) {
        if ((int)$division['id'] === (int)$edit['division_id']) {
            $parent_label = 'Division';
            $parent_value = trim(($division['name_en'] ?? '') . (!empty($division['name_bn']) ? ' / ' . $division['name_bn'] : ''));
            break;
        }
    }
}

if ($type === 'thana' && !empty($edit['district_id'])) {
    foreach ($districts as $district) {
        if ((int)$district['id'] === (int)$edit['district_id']) {
            $parent_label = 'District';
            $parent_value = trim(($district['name_en'] ?? '') . (!empty($district['name_bn']) ? ' / ' . $district['name_bn'] : ''));
            break;
        }
    }
}
?>

<style>
  :root {
    --gh-canvas-default: #ffffff;
    --gh-canvas-subtle: #f6f8fa;
    --gh-canvas-inset: #f0f3f6;
    --gh-border-default: #d0d7de;
    --gh-border-muted: #d8dee4;
    --gh-fg-default: #1f2328;
    --gh-fg-muted: #656d76;
    --gh-accent-fg: #0969da;
    --gh-accent-emphasis: #0969da;
    --gh-accent-subtle: #ddf4ff;
    --gh-success-fg: #1a7f37;
    --gh-success-emphasis: #1f883d;
    --gh-success-subtle: #dafbe1;
    --gh-danger-fg: #cf222e;
    --gh-danger-subtle: #ffebe9;
    --gh-attention-fg: #9a6700;
    --gh-attention-subtle: #fff8c5;
    --gh-radius: 6px;
    --gh-radius-large: 10px;
    --gh-shadow-small: 0 1px 0 rgba(31, 35, 40, .04);
    --gh-shadow-medium: 0 3px 6px rgba(140, 149, 159, .15);
  }

  body {
    background: var(--gh-canvas-subtle);
  }

  .loc-page,
  .loc-page * {
    box-sizing: border-box;
  }

  .loc-page {
    max-width: 1240px;
    margin: 0 auto;
    padding: 24px 20px 48px;
    color: var(--gh-fg-default);
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Helvetica, Arial, sans-serif;
  }

  .loc-page code {
    padding: 2px 5px;
    border-radius: 4px;
    background: rgba(175, 184, 193, .2);
    color: var(--gh-fg-default);
    font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
    font-size: 12px;
  }

  .loc-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 20px;
    padding: 0 0 20px;
    border-bottom: 1px solid var(--gh-border-default);
  }

  .loc-breadcrumb {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 7px;
    margin: 0 0 10px;
    color: var(--gh-fg-muted);
    font-size: 13px;
    line-height: 1.4;
  }

  .loc-breadcrumb a {
    color: var(--gh-accent-fg);
    text-decoration: none;
  }

  .loc-breadcrumb a:hover {
    text-decoration: underline;
  }

  .loc-breadcrumb .loc-slash {
    color: #8c959f;
  }

  .loc-title {
    margin: 0;
    color: var(--gh-fg-default);
    font-size: clamp(24px, 3vw, 32px);
    font-weight: 600;
    line-height: 1.25;
    letter-spacing: -.02em;
  }

  .loc-subtitle {
    max-width: 760px;
    margin: 7px 0 0;
    color: var(--gh-fg-muted);
    font-size: 14px;
    line-height: 1.55;
  }

  .loc-header-actions,
  .loc-actions-row {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
  }

  .loc-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    min-height: 32px;
    padding: 5px 12px;
    border: 1px solid rgba(31, 35, 40, .15);
    border-radius: var(--gh-radius);
    background: #f6f8fa;
    box-shadow: var(--gh-shadow-small);
    color: var(--gh-fg-default);
    cursor: pointer;
    font-family: inherit;
    font-size: 14px;
    font-weight: 500;
    line-height: 20px;
    text-decoration: none;
    white-space: nowrap;
    transition: background-color .15s ease, border-color .15s ease, box-shadow .15s ease, transform .15s ease;
  }

  .loc-btn:hover {
    border-color: rgba(31, 35, 40, .25);
    background: #f3f4f6;
  }

  .loc-btn:active {
    transform: translateY(1px);
  }

  .loc-btn:focus-visible,
  .loc-input:focus-visible,
  .loc-select:focus-visible,
  .loc-file-input:focus-visible {
    outline: 2px solid var(--gh-accent-fg);
    outline-offset: 2px;
  }

  .loc-btn-primary {
    border-color: rgba(31, 35, 40, .15);
    background: var(--gh-success-emphasis);
    color: #ffffff;
    font-weight: 600;
  }

  .loc-btn-primary:hover {
    background: #1a7f37;
  }

  .loc-btn-primary[disabled] {
    cursor: wait;
    opacity: .72;
  }

  .loc-btn svg,
  .loc-section-mark svg,
  .loc-notice-mark svg,
  .loc-preview-fallback svg,
  .loc-breadcrumb svg {
    width: 16px;
    height: 16px;
    fill: currentColor;
  }

  .loc-notice {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    margin: 20px 0 0;
    padding: 12px 14px;
    border: 1px solid #a5d6b5;
    border-radius: var(--gh-radius);
    background: var(--gh-success-subtle);
    color: var(--gh-success-fg);
    box-shadow: var(--gh-shadow-small);
    font-size: 14px;
    line-height: 1.45;
  }

  .loc-notice-mark {
    display: grid;
    flex: 0 0 20px;
    width: 20px;
    height: 20px;
    place-items: center;
    border-radius: 50%;
    background: var(--gh-success-emphasis);
    color: #ffffff;
  }

  .loc-notice strong {
    display: block;
    margin-bottom: 2px;
    color: var(--gh-success-fg);
    font-weight: 600;
  }

  .loc-notice p {
    margin: 0;
  }

  .loc-layout {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 330px;
    gap: 20px;
    align-items: start;
    margin-top: 20px;
  }

  .loc-panel,
  .loc-side-card {
    overflow: hidden;
    border: 1px solid var(--gh-border-default);
    border-radius: var(--gh-radius-large);
    background: var(--gh-canvas-default);
    box-shadow: var(--gh-shadow-small);
  }

  .loc-panel-intro {
    display: flex;
    gap: 13px;
    align-items: flex-start;
    padding: 18px 20px;
    border-bottom: 1px solid var(--gh-border-default);
    background: linear-gradient(180deg, #ffffff, #f6f8fa);
  }

  .loc-intro-icon {
    display: grid;
    flex: 0 0 34px;
    width: 34px;
    height: 34px;
    place-items: center;
    border: 1px solid #b6e3ff;
    border-radius: 50%;
    background: var(--gh-accent-subtle);
    color: var(--gh-accent-fg);
  }

  .loc-intro-icon svg {
    width: 18px;
    height: 18px;
    fill: currentColor;
  }

  .loc-panel-intro h2 {
    margin: 0;
    color: var(--gh-fg-default);
    font-size: 16px;
    font-weight: 600;
    line-height: 1.4;
  }

  .loc-panel-intro p {
    margin: 3px 0 0;
    color: var(--gh-fg-muted);
    font-size: 13px;
    line-height: 1.5;
  }

  .loc-form-body {
    padding: 20px;
  }

  .loc-section {
    margin: 0 0 18px;
    border: 1px solid var(--gh-border-default);
    border-radius: var(--gh-radius-large);
    background: var(--gh-canvas-default);
    overflow: hidden;
  }

  .loc-section:last-of-type {
    margin-bottom: 0;
  }

  .loc-section-head {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    padding: 13px 16px;
    border-bottom: 1px solid var(--gh-border-muted);
    background: var(--gh-canvas-subtle);
  }

  .loc-section-mark {
    display: grid;
    flex: 0 0 26px;
    width: 26px;
    height: 26px;
    place-items: center;
    border: 1px solid #b6e3ff;
    border-radius: 50%;
    background: var(--gh-accent-subtle);
    color: var(--gh-accent-fg);
    font-size: 11px;
    font-weight: 600;
  }

  .loc-section-head h3 {
    margin: 0;
    color: var(--gh-fg-default);
    font-size: 14px;
    font-weight: 600;
    line-height: 1.4;
  }

  .loc-section-head p {
    margin: 2px 0 0;
    color: var(--gh-fg-muted);
    font-size: 12px;
    line-height: 1.45;
  }

  .loc-grid,
  .loc-en-bn-row {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 16px;
    padding: 16px;
  }

  .loc-field {
    display: grid;
    gap: 6px;
    min-width: 0;
  }

  .loc-field.full {
    grid-column: 1 / -1;
  }

  .loc-field label {
    color: var(--gh-fg-default);
    font-size: 14px;
    font-weight: 600;
    line-height: 1.4;
  }

  .loc-field .loc-label-bn {
    color: var(--gh-fg-muted);
  }

  .loc-field small {
    color: var(--gh-fg-muted);
    font-size: 12px;
    line-height: 1.45;
  }

  .loc-input,
  .loc-select {
    width: 100%;
    min-height: 32px;
    padding: 5px 12px;
    border: 1px solid var(--gh-border-default);
    border-radius: var(--gh-radius);
    outline: none;
    background: var(--gh-canvas-default);
    color: var(--gh-fg-default);
    box-shadow: inset 0 1px 0 rgba(208, 215, 222, .2);
    font-family: inherit;
    font-size: 14px;
    line-height: 20px;
    transition: border-color .15s ease, box-shadow .15s ease;
  }

  .loc-select {
    min-height: 34px;
    cursor: pointer;
  }

  .loc-input:hover,
  .loc-select:hover {
    border-color: #8c959f;
  }

  .loc-input:focus,
  .loc-select:focus {
    border-color: var(--gh-accent-fg);
    box-shadow: 0 0 0 3px rgba(9, 105, 218, .15);
  }

  .loc-input::placeholder {
    color: #8c959f;
  }

  /* Deliberately simple native Choose File input. */
  .loc-file-input {
    width: 100%;
    min-height: 38px;
    padding: 6px 8px;
    border: 1px solid var(--gh-border-default);
    border-radius: var(--gh-radius);
    background: var(--gh-canvas-default);
    color: var(--gh-fg-default);
    font: inherit;
    font-size: 13px;
    line-height: 20px;
    cursor: pointer;
  }

  .loc-file-input:hover {
    border-color: #8c959f;
  }

  .loc-file-input:focus {
    outline: none;
    border-color: var(--gh-accent-fg);
    box-shadow: 0 0 0 3px rgba(9, 105, 218, .15);
  }

  .loc-file-help,
  .loc-upload-feedback {
    display: block;
    margin-top: 6px;
    color: var(--gh-fg-muted);
    font-size: 12px;
    line-height: 1.45;
  }

  .loc-upload-feedback {
    min-height: 17px;
  }

  .loc-upload-feedback.is-error {
    color: var(--gh-danger-fg);
  }

  .loc-upload-feedback.is-ready {
    color: var(--gh-success-fg);
  }

  .loc-current-image {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    width: fit-content;
    margin-top: 7px;
    padding: 3px 7px;
    border: 1px solid var(--gh-border-default);
    border-radius: 999px;
    background: var(--gh-canvas-subtle);
    color: var(--gh-fg-muted);
    font-size: 11px;
    line-height: 1.35;
    word-break: break-all;
  }

  .loc-actions-row {
    justify-content: flex-end;
    margin-top: 18px;
    padding-top: 18px;
    border-top: 1px solid var(--gh-border-default);
  }

  .loc-side-stack {
    display: grid;
    gap: 16px;
    position: sticky;
    top: 16px;
  }

  .loc-side-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    padding: 12px 16px;
    border-bottom: 1px solid var(--gh-border-default);
    background: var(--gh-canvas-subtle);
  }

  .loc-side-head h2 {
    margin: 0;
    color: var(--gh-fg-default);
    font-size: 14px;
    font-weight: 600;
  }

  .loc-side-pill {
    padding: 2px 8px;
    border: 1px solid #b6e3ff;
    border-radius: 999px;
    background: var(--gh-accent-subtle);
    color: var(--gh-accent-fg);
    font-size: 11px;
    font-weight: 600;
  }

  .loc-preview {
    padding: 16px;
  }

  .loc-preview-card {
    overflow: hidden;
    border: 1px solid var(--gh-border-default);
    border-radius: var(--gh-radius-large);
    background: var(--gh-canvas-default);
  }

  .loc-preview-cover {
    height: 88px;
    border-bottom: 1px solid var(--gh-border-muted);
    background: linear-gradient(135deg, #ddf4ff 0%, #f6f8fa 60%, #dafbe1 100%);
  }

  .loc-preview-body {
    padding: 0 16px 16px;
  }

  .loc-preview-media {
    position: relative;
    display: grid;
    width: 72px;
    height: 72px;
    place-items: center;
    overflow: hidden;
    margin-top: -36px;
    margin-bottom: 12px;
    border: 3px solid #ffffff;
    border-radius: var(--gh-radius-large);
    background: var(--gh-accent-emphasis);
    color: #ffffff;
    box-shadow: var(--gh-shadow-medium);
  }

  .loc-preview-media img {
    display: none;
    width: 100%;
    height: 100%;
    object-fit: cover;
  }

  .loc-preview-media.has-image img {
    display: block;
  }

  .loc-preview-media.has-image .loc-preview-fallback {
    display: none;
  }

  .loc-preview-fallback {
    display: grid;
    place-items: center;
    width: 100%;
    height: 100%;
  }

  .loc-preview-fallback span {
    font-size: 25px;
    font-weight: 600;
    line-height: 1;
  }

  .loc-preview-card h3 {
    margin: 0;
    color: var(--gh-fg-default);
    font-size: 18px;
    font-weight: 600;
    line-height: 1.35;
  }

  .loc-preview-card p {
    margin: 4px 0 0;
    color: var(--gh-fg-muted);
    font-size: 13px;
    line-height: 1.45;
  }

  .loc-status {
    display: inline-flex;
    margin-top: 12px;
    padding: 3px 8px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 600;
    line-height: 1.35;
  }

  .loc-status.active {
    background: var(--gh-success-subtle);
    color: var(--gh-success-fg);
  }

  .loc-status.inactive {
    background: var(--gh-danger-subtle);
    color: var(--gh-danger-fg);
  }

  .loc-meta {
    display: grid;
    gap: 8px;
    margin-top: 15px;
  }

  .loc-meta-item {
    padding: 10px;
    border: 1px solid var(--gh-border-muted);
    border-radius: var(--gh-radius);
    background: var(--gh-canvas-subtle);
  }

  .loc-meta-item span {
    display: block;
    margin-bottom: 3px;
    color: var(--gh-fg-muted);
    font-size: 11px;
    font-weight: 600;
    letter-spacing: .04em;
    line-height: 1.35;
    text-transform: uppercase;
  }

  .loc-meta-item strong {
    display: block;
    color: var(--gh-fg-default);
    font-size: 13px;
    font-weight: 500;
    line-height: 1.45;
    overflow-wrap: anywhere;
  }

  .loc-help {
    margin: 0;
    padding: 4px 16px 10px;
    list-style: none;
  }

  .loc-help li {
    display: grid;
    grid-template-columns: 18px minmax(0, 1fr);
    gap: 8px;
    padding: 11px 0;
    border-bottom: 1px solid var(--gh-border-muted);
    color: var(--gh-fg-muted);
    font-size: 12.5px;
    line-height: 1.5;
  }

  .loc-help li:last-child {
    border-bottom: 0;
  }

  .loc-help-mark {
    display: grid;
    width: 18px;
    height: 18px;
    place-items: center;
    border: 1px solid #b6e3ff;
    border-radius: 50%;
    background: var(--gh-accent-subtle);
    color: var(--gh-accent-fg);
    font-size: 10px;
    font-weight: 600;
  }

  @media (max-width: 1000px) {
    .loc-layout {
      grid-template-columns: 1fr;
    }

    .loc-side-stack {
      position: static;
      grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
      align-items: start;
    }
  }

  @media (max-width: 720px) {
    .loc-page {
      padding: 18px 14px 32px;
    }

    .loc-header {
      flex-direction: column;
      gap: 14px;
    }

    .loc-header-actions,
    .loc-header-actions .loc-btn {
      width: 100%;
    }

    .loc-grid,
    .loc-en-bn-row,
    .loc-side-stack {
      grid-template-columns: 1fr;
    }

    .loc-form-body {
      padding: 14px;
    }

    .loc-section-head,
    .loc-grid,
    .loc-en-bn-row {
      padding-left: 14px;
      padding-right: 14px;
    }

    .loc-actions-row {
      flex-direction: column-reverse;
    }

    .loc-actions-row .loc-btn {
      width: 100%;
    }

  }
</style>

<div class="loc-page">
    <header class="loc-header">
        <div>
            <nav class="loc-breadcrumb" aria-label="Breadcrumb">
                <a href="locations.php?type=<?= e($type) ?>">Locations</a>
                <span class="loc-slash">/</span>
                <span><?= e($labels[$type]) ?></span>
                <span class="loc-slash">/</span>
                <span><?= $edit ? 'Edit' : 'New' ?></span>
            </nav>
            <h1 class="loc-title"><?= e($form_title) ?></h1>
            <p class="loc-subtitle"><?= e($form_subtitle) ?></p>
        </div>

        <div class="loc-header-actions">
            <a href="locations.php?type=<?= e($type) ?>" class="loc-btn">
                <svg viewBox="0 0 16 16" aria-hidden="true"><path d="M7.78 1.22a.75.75 0 0 1 0 1.06L2.81 7.25H14a.75.75 0 0 1 0 1.5H2.81l4.97 4.97a.75.75 0 0 1-1.06 1.06l-6.25-6.25a.75.75 0 0 1 0-1.06l6.25-6.25a.75.75 0 0 1 1.06 0Z"/></svg>
                Back to Locations
            </a>
        </div>
    </header>

    <?php show_flash(); ?>

    <?php if ($save_notice !== ''): ?>
        <div class="loc-notice" role="status" aria-live="polite">
            <span class="loc-notice-mark"><svg viewBox="0 0 16 16" aria-hidden="true"><path d="M13.78 3.72a.75.75 0 0 1 0 1.06l-6.5 6.5a.75.75 0 0 1-1.06 0l-3-3a.75.75 0 1 1 1.06-1.06l2.47 2.47 5.97-5.97a.75.75 0 0 1 1.06 0Z"/></svg></span>
            <div>
                <strong>Saved successfully</strong>
                <p><?= e($save_notice) ?></p>
            </div>
        </div>
    <?php endif; ?>

    <div class="loc-layout">
        <main class="loc-panel">
            <div class="loc-panel-intro">
                <span class="loc-intro-icon" aria-hidden="true"><svg viewBox="0 0 16 16"><path d="M8 0a5.5 5.5 0 0 0-5.5 5.5c0 4.09 4.68 9.52 4.88 9.75a.82.82 0 0 0 1.24 0c.2-.23 4.88-5.66 4.88-9.75A5.5 5.5 0 0 0 8 0Zm0 8A2.5 2.5 0 1 1 8 3a2.5 2.5 0 0 1 0 5Z"/></svg></span>
                <div>
                    <h2><?= $edit ? 'Update location details' : 'Create a new location' ?></h2>
                    <p>Provide accurate names, choose the right parent area, upload an image and control public visibility.</p>
                </div>
            </div>

            <div class="loc-form-body">
                <form method="POST" id="locationForm" enctype="multipart/form-data">
                    <input type="hidden" name="type" value="<?= e($type) ?>">
                    <input type="hidden" name="id" value="<?= e((string)($edit['id'] ?? '')) ?>">

                    <?php if ($type === 'district' || $type === 'thana'): ?>
                        <section class="loc-section">
                            <div class="loc-section-head">
                                <span class="loc-section-mark">01</span>
                                <div>
                                    <h3>Parent location</h3>
                                    <p>Connect this <?= strtolower($labels[$type]) ?> to its correct parent area.</p>
                                </div>
                            </div>

                            <div class="loc-grid">
                                <?php if ($type === 'district'): ?>
                                    <div class="loc-field full">
                                        <label for="division_id">Select Division <span aria-hidden="true">*</span></label>
                                        <select id="division_id" class="loc-select" name="division_id" required>
                                            <option value="">Select a division</option>
                                            <?php foreach ($divisions as $division): ?>
                                                <option value="<?= e((string)$division['id']) ?>" <?= (($edit['division_id'] ?? '') == $division['id']) ? 'selected' : '' ?>>
                                                    <?= e($division['name_en']) ?><?= !empty($division['name_bn']) ? ' / ' . e($division['name_bn']) : '' ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <small>This district will appear under the selected division.</small>
                                    </div>
                                <?php endif; ?>

                                <?php if ($type === 'thana'): ?>
                                    <div class="loc-field full">
                                        <label for="district_id">Select District <span aria-hidden="true">*</span></label>
                                        <select id="district_id" class="loc-select" name="district_id" required>
                                            <option value="">Select a district</option>
                                            <?php foreach ($districts as $district): ?>
                                                <option value="<?= e((string)$district['id']) ?>" <?= (($edit['district_id'] ?? '') == $district['id']) ? 'selected' : '' ?>>
                                                    <?= e($district['name_en']) ?><?= !empty($district['name_bn']) ? ' / ' . e($district['name_bn']) : '' ?><?= !empty($district['division_name']) ? ' · ' . e($district['division_name']) : '' ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <small>This thana or upazila will appear under the selected district.</small>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </section>
                    <?php endif; ?>

                    <section class="loc-section">
                        <div class="loc-section-head">
                            <span class="loc-section-mark"><?= ($type === 'district' || $type === 'thana') ? '02' : '01' ?></span>
                            <div>
                                <h3>Location identity</h3>
                                <p>Use the official English and Bangla names shown to visitors.</p>
                            </div>
                        </div>

                        <div class="loc-en-bn-row">
                            <div class="loc-field">
                                <label for="name_en">English Name <span aria-hidden="true">*</span></label>
                                <input id="name_en" class="loc-input" type="text" name="name_en" placeholder="Example: Dhaka" value="<?= e($edit['name_en'] ?? '') ?>" required>
                                <small>Used for public labels, URLs and filtering.</small>
                            </div>

                            <div class="loc-field">
                                <label class="loc-label-bn" for="name_bn">Bangla Name <span aria-hidden="true">*</span></label>
                                <input id="name_bn" class="loc-input" type="text" name="name_bn" placeholder="উদাহরণ: ঢাকা" value="<?= e($edit['name_bn'] ?? '') ?>" required>
                                <small>Used on Bangla pages and for local search users.</small>
                            </div>
                        </div>
                    </section>

                    <section class="loc-section">
                        <div class="loc-section-head">
                            <span class="loc-section-mark"><?= ($type === 'district' || $type === 'thana') ? '03' : '02' ?></span>
                            <div>
                                <h3>URL, image and visibility</h3>
                                <p>Set a clean URL, upload a clear image and choose the publishing status.</p>
                            </div>
                        </div>

                        <div class="loc-grid">
                            <div class="loc-field">
                                <label for="slug">Slug</label>
                                <input id="slug" class="loc-input" type="text" name="slug" placeholder="Example: dhaka" value="<?= e($edit['slug'] ?? '') ?>">
                                <small>Leave empty to generate it automatically from the English name.</small>
                            </div>

                            <div class="loc-field">
                                <label for="status">Status</label>
                                <select id="status" class="loc-select" name="status">
                                    <option value="active" <?= ($current_status === 'active') ? 'selected' : '' ?>>Active</option>
                                    <option value="inactive" <?= ($current_status === 'inactive') ? 'selected' : '' ?>>Inactive</option>
                                </select>
                                <small>Inactive entries remain saved but can stay hidden from public pages.</small>
                            </div>

                            <div class="loc-field full">
                                <label for="image">Location Image</label>
                                <input
                                    id="image"
                                    class="loc-file-input"
                                    type="file"
                                    name="image"
                                    accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                                >
                                <small class="loc-file-help">Choose a JPG, PNG or WebP image up to 5 MB. It will be compressed and saved as optimized high-quality WebP without resizing.</small>
                                <div class="loc-upload-feedback" id="uploadFeedback" aria-live="polite"></div>
                                <?php if (!empty($preview_image)): ?>
                                    <span class="loc-current-image">Current image: <?= e((string)$preview_image) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </section>

                    <div class="loc-actions-row">
                        <a href="locations.php?type=<?= e($type) ?>" class="loc-btn">Cancel</a>
                        <button class="loc-btn loc-btn-primary" id="saveButton" type="submit">
                            <svg viewBox="0 0 16 16" aria-hidden="true"><path d="M1.75 1.5A1.25 1.25 0 0 1 3 .25h8.69c.33 0 .65.13.88.37l2.81 2.81c.23.23.37.55.37.88V14A1.75 1.75 0 0 1 14 15.75H2A1.75 1.75 0 0 1 .25 14V1.75h1.5ZM3 1.75a.25.25 0 0 0-.25.25V14c0 .14.11.25.25.25h11a.25.25 0 0 0 .25-.25V4.37L11.38 1.5H3v.25Zm1 7.5c0-.55.45-1 1-1h6c.55 0 1 .45 1 1V14H4V9.25ZM5.5 2.5v3h4v-3h-4Z"/></svg>
                            <span id="saveButtonText"><?= $edit ? 'Update ' . e($labels[$type]) : 'Save ' . e($labels[$type]) ?></span>
                        </button>
                    </div>
                </form>
            </div>
        </main>

        <aside class="loc-side-stack">
            <section class="loc-side-card">
                <div class="loc-side-head">
                    <h2>Live Preview</h2>
                    <span class="loc-side-pill">Public card</span>
                </div>

                <div class="loc-preview">
                    <div class="loc-preview-card">
                        <div class="loc-preview-cover"></div>
                        <div class="loc-preview-body">
                            <div id="previewMedia" class="loc-preview-media <?= $preview_image !== '' ? 'has-image' : '' ?>">
                                <img id="previewImage" src="<?= $preview_image !== '' ? e($preview_image) : '' ?>" alt="">
                                <span class="loc-preview-fallback"><span id="previewIcon"><?= e(function_exists('mb_substr') ? mb_substr((string)$preview_name, 0, 1) : substr((string)$preview_name, 0, 1)) ?></span></span>
                            </div>

                            <h3 id="previewName"><?= e($preview_name) ?></h3>
                            <p id="previewNameBn"><?= e($preview_bn) ?></p>

                            <span class="loc-status <?= e($current_status === 'inactive' ? 'inactive' : 'active') ?>" id="previewStatus">
                                <?= e(ucfirst($current_status)) ?>
                            </span>

                            <div class="loc-meta">
                                <div class="loc-meta-item">
                                    <span>Type</span>
                                    <strong><?= e($labels[$type]) ?> / <?= e($labels_bn[$type]) ?></strong>
                                </div>

                                <?php if ($parent_label !== '' || $type !== 'division'): ?>
                                    <div class="loc-meta-item">
                                        <span>Parent</span>
                                        <strong id="previewParent"><?= e($parent_value ?: 'Select parent location') ?></strong>
                                    </div>
                                <?php endif; ?>

                                <div class="loc-meta-item">
                                    <span>Slug</span>
                                    <strong id="previewSlug"><?= e($preview_slug) ?></strong>
                                </div>

                                <div class="loc-meta-item">
                                    <span>Example URL</span>
                                    <strong id="previewUrl">/doctors/<?= e($preview_slug) ?></strong>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="loc-side-card">
                <div class="loc-side-head">
                    <h2>Publishing checklist</h2>
                    <span class="loc-side-pill">Helpful</span>
                </div>
                <ul class="loc-help">
                    <li><span class="loc-help-mark">1</span><span>Keep the English name clear so it works well in public filters and URLs.</span></li>
                    <li><span class="loc-help-mark">2</span><span>Add the official Bangla name for Bangla pages and local visitors.</span></li>
                    <?php if ($type === 'district'): ?>
                        <li><span class="loc-help-mark">3</span><span>Make sure the district is connected to the correct division.</span></li>
                    <?php elseif ($type === 'thana'): ?>
                        <li><span class="loc-help-mark">3</span><span>Make sure the thana or upazila is connected to the correct district.</span></li>
                    <?php else: ?>
                        <li><span class="loc-help-mark">3</span><span>Use an image that clearly represents the division for better visual recognition.</span></li>
                    <?php endif; ?>
                    <li><span class="loc-help-mark">4</span><span>Uploaded images are compressed and saved as optimized high-quality WebP without cropping or resizing.</span></li>
                </ul>
            </section>
        </aside>
    </div>
</div>

<script>
(function () {
    const get = function (id) {
        return document.getElementById(id);
    };

    const form = get('locationForm');
    const nameInput = get('name_en');
    const nameBnInput = get('name_bn');
    const slugInput = get('slug');
    const statusInput = get('status');
    const divisionInput = get('division_id');
    const districtInput = get('district_id');
    const imageInput = get('image');
    const uploadFeedback = get('uploadFeedback');
    const saveButton = get('saveButton');
    const saveButtonText = get('saveButtonText');

    const previewIcon = get('previewIcon');
    const previewMedia = get('previewMedia');
    const previewImage = get('previewImage');
    const previewName = get('previewName');
    const previewNameBn = get('previewNameBn');
    const previewSlug = get('previewSlug');
    const previewUrl = get('previewUrl');
    const previewStatus = get('previewStatus');
    const previewParent = get('previewParent');

    const slugify = function (value) {
        return String(value || '')
            .toLowerCase()
            .trim()
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '') || 'auto-generated-slug';
    };

    const selectedText = function (select) {
        if (!select || !select.value || select.selectedIndex < 0) {
            return '';
        }

        return select.options[select.selectedIndex].textContent.trim();
    };

    const updatePreview = function () {
        const enName = nameInput ? nameInput.value.trim() : '';
        const bnName = nameBnInput ? nameBnInput.value.trim() : '';
        const slug = slugInput && slugInput.value.trim() ? slugInput.value.trim() : slugify(enName);
        const status = statusInput ? statusInput.value : 'active';
        const fallbackName = '<?= e($labels[$type]) ?>';

        if (previewName) {
            previewName.textContent = enName || fallbackName;
        }

        if (previewNameBn) {
            previewNameBn.textContent = bnName || '<?= e($labels_bn[$type]) ?>';
        }

        if (previewIcon) {
            previewIcon.textContent = (enName || fallbackName).charAt(0).toUpperCase();
        }

        if (previewSlug) {
            previewSlug.textContent = slug;
        }

        if (previewUrl) {
            previewUrl.textContent = '/doctors/' + slug;
        }

        if (previewStatus) {
            previewStatus.textContent = status.charAt(0).toUpperCase() + status.slice(1);
            previewStatus.classList.toggle('active', status === 'active');
            previewStatus.classList.toggle('inactive', status !== 'active');
        }

        if (previewParent) {
            previewParent.textContent = selectedText(divisionInput) || selectedText(districtInput) || 'Select parent location';
        }
    };

    let previewObjectUrl = '';

    const clearObjectUrl = function () {
        if (previewObjectUrl) {
            URL.revokeObjectURL(previewObjectUrl);
            previewObjectUrl = '';
        }
    };

    const setImagePreview = function (file) {
        if (!file || !previewMedia || !previewImage) {
            return;
        }

        const allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
        const maxBytes = 5 * 1024 * 1024;

        uploadFeedback.classList.remove('is-error', 'is-ready');

        if (!allowedTypes.includes(file.type)) {
            uploadFeedback.classList.add('is-error');
            uploadFeedback.textContent = 'Choose a JPG, PNG or WebP image.';
            imageInput.value = '';
            return;
        }

        if (file.size > maxBytes) {
            uploadFeedback.classList.add('is-error');
            uploadFeedback.textContent = 'This file is larger than 5 MB. Please choose a smaller image.';
            imageInput.value = '';
            return;
        }

        clearObjectUrl();
        previewObjectUrl = URL.createObjectURL(file);
        previewImage.src = previewObjectUrl;
        previewMedia.classList.add('has-image');
        uploadFeedback.classList.add('is-ready');
        uploadFeedback.textContent = 'Selected: ' + file.name + '. It will be compressed and saved as optimized high-quality WebP without resizing.';
    };

    [nameInput, nameBnInput, slugInput, statusInput, divisionInput, districtInput].forEach(function (input) {
        if (input) {
            input.addEventListener('input', updatePreview);
            input.addEventListener('change', updatePreview);
        }
    });

    if (imageInput) {
        imageInput.addEventListener('change', function () {
            setImagePreview(imageInput.files && imageInput.files[0] ? imageInput.files[0] : null);
        });
    }

    if (form) {
        form.addEventListener('submit', function () {
            if (!saveButton || !saveButtonText) {
                return;
            }

            saveButton.disabled = true;
            saveButtonText.textContent = 'Saving…';
        });
    }

    window.addEventListener('beforeunload', clearObjectUrl);
    updatePreview();
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
