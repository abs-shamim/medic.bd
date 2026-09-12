<?php
require_once __DIR__ . '/includes/functions.php';

global $pdo;

/*
|--------------------------------------------------------------------------
| Schema Migration
|--------------------------------------------------------------------------
| Self-healing: adds the columns this redesigned form needs without
| touching any existing data. Matches the pattern used across the rest of
| this project (see admin/site-settings.php, includes/functions.php, etc.)
|--------------------------------------------------------------------------
*/
function ensure_contacts_table(): void
{
    global $pdo;

    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `contacts` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `name` VARCHAR(190) NOT NULL,
                `email` VARCHAR(190) NOT NULL,
                `subject` VARCHAR(190) NULL,
                `message` TEXT NULL,
                `status` ENUM('unread','read') NOT NULL DEFAULT 'unread',
                `created_at` DATETIME NULL,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        if (!column_exists('contacts', 'status')) {
            $pdo->exec("ALTER TABLE `contacts` ADD COLUMN `status` ENUM('unread','read') NOT NULL DEFAULT 'unread' AFTER `message`");
        }

        if (!column_exists('contacts', 'notes')) {
            $pdo->exec("ALTER TABLE `contacts` ADD COLUMN `notes` TEXT NULL AFTER `status`");
        }

        if (!column_exists('contacts', 'starred')) {
            $pdo->exec("ALTER TABLE `contacts` ADD COLUMN `starred` TINYINT(1) NOT NULL DEFAULT 0 AFTER `notes`");
        }

        if (!column_exists('contacts', 'category')) {
            $pdo->exec("ALTER TABLE `contacts` ADD COLUMN `category` VARCHAR(20) NOT NULL DEFAULT 'primary' AFTER `starred`");
        }

        if (!column_exists('contacts', 'phone')) {
            $pdo->exec("ALTER TABLE `contacts` ADD COLUMN `phone` VARCHAR(30) NOT NULL DEFAULT '' AFTER `email`");
        }

        if (!column_exists('contacts', 'request_type')) {
            $pdo->exec("ALTER TABLE `contacts` ADD COLUMN `request_type` VARCHAR(60) NOT NULL DEFAULT '' AFTER `subject`");
        }

        if (!column_exists('contacts', 'form_data')) {
            $pdo->exec("ALTER TABLE `contacts` ADD COLUMN `form_data` LONGTEXT NULL AFTER `message`");
        }

        if (!column_exists('contacts', 'attachments')) {
            $pdo->exec("ALTER TABLE `contacts` ADD COLUMN `attachments` LONGTEXT NULL AFTER `form_data`");
        }

        if (!column_exists('contacts', 'ip_address')) {
            $pdo->exec("ALTER TABLE `contacts` ADD COLUMN `ip_address` VARCHAR(45) NULL AFTER `attachments`");
        }

        if (!column_exists('contacts', 'review_status')) {
            $pdo->exec("ALTER TABLE `contacts` ADD COLUMN `review_status` VARCHAR(20) NOT NULL DEFAULT 'new' AFTER `ip_address`");
        }
    } catch (Throwable $e) {
        // If the hosting database user has no CREATE/ALTER permission, the
        // page still loads and the insert below is attempted as-is.
    }
}

ensure_contacts_table();

/*
|--------------------------------------------------------------------------
| CSRF
|--------------------------------------------------------------------------
*/
if (empty($_SESSION['contact_form_csrf'])) {
    $_SESSION['contact_form_csrf'] = bin2hex(random_bytes(32));
}

$contact_csrf_token = $_SESSION['contact_form_csrf'];

require_once __DIR__ . '/includes/contact-form-schema.php';

/*
|--------------------------------------------------------------------------
| Validators
|--------------------------------------------------------------------------
*/
function cf_is_valid_email(string $v): bool
{
    return $v === '' || filter_var($v, FILTER_VALIDATE_EMAIL) !== false;
}

function cf_is_valid_url(string $v): bool
{
    return $v === '' || filter_var($v, FILTER_VALIDATE_URL) !== false;
}

function cf_is_valid_phone(string $v): bool
{
    return $v === '' || (bool)preg_match('/^[0-9+\-\s()]{7,20}$/', $v);
}

/*
|--------------------------------------------------------------------------
| File Upload Handling
|--------------------------------------------------------------------------
*/

/*
 * Re-encodes an uploaded JPG/PNG/WEBP image to WEBP (auto-orienting JPEGs
 * from their EXIF data first, matching admin/specialty-form.php's existing
 * conversion approach) so every stored photo ends up in one smaller, modern
 * format regardless of what the visitor uploaded. Returns null -- so the
 * caller falls back to storing the original file untouched -- when GD or
 * WEBP support isn't available on the server.
 */
function cf_convert_image_to_webp(string $source_path, string $mime)
{
    $creators = [
        'image/jpeg' => 'imagecreatefromjpeg',
        'image/png'  => 'imagecreatefrompng',
        'image/webp' => 'imagecreatefromwebp',
    ];

    if (!isset($creators[$mime]) || !function_exists($creators[$mime]) || !function_exists('imagewebp')) {
        return null;
    }

    $create_function = $creators[$mime];
    $image = @$create_function($source_path);

    if ($image === false) {
        return null;
    }

    if ($mime === 'image/jpeg' && function_exists('exif_read_data') && function_exists('imagerotate')) {
        $exif = @exif_read_data($source_path);

        if (is_array($exif) && !empty($exif['Orientation'])) {
            $angle_map = [3 => 180, 6 => -90, 8 => 90];
            $angle = $angle_map[(int)$exif['Orientation']] ?? 0;

            if ($angle !== 0) {
                $rotated = @imagerotate($image, $angle, 0);

                if ($rotated !== false) {
                    imagedestroy($image);
                    $image = $rotated;
                }
            }
        }
    }

    if (function_exists('imagepalettetotruecolor')) {
        @imagepalettetotruecolor($image);
    }

    imagealphablending($image, true);
    imagesavealpha($image, true);

    return $image;
}

function cf_validate_uploaded_file(array $file, string $accept, string $label, array &$errors): ?array
{
    $error_code = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);

    if ($error_code === UPLOAD_ERR_NO_FILE || empty($file['tmp_name'])) {
        return null;
    }

    if ($error_code !== UPLOAD_ERR_OK) {
        $errors[] = $label . ' could not be uploaded. Please try again.';
        return null;
    }

    if (!is_uploaded_file($file['tmp_name'])) {
        $errors[] = $label . ' upload failed validation.';
        return null;
    }

    $max_bytes = 5 * 1024 * 1024;
    $size = (int)($file['size'] ?? 0);

    if ($size <= 0 || $size > $max_bytes) {
        $errors[] = $label . ' must be smaller than 5 MB.';
        return null;
    }

    $extension = strtolower((string)pathinfo((string)$file['name'], PATHINFO_EXTENSION));

    $allowed = $accept === 'doc'
        ? ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'pdf' => 'application/pdf']
        : ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];

    if (!isset($allowed[$extension])) {
        $errors[] = $label . ' must be a ' . ($accept === 'doc' ? 'JPG, PNG, WEBP or PDF' : 'JPG, PNG or WEBP') . ' file.';
        return null;
    }

    if ($extension === 'pdf') {
        $handle = @fopen($file['tmp_name'], 'rb');
        $header = $handle ? fread($handle, 5) : '';
        if ($handle) {
            fclose($handle);
        }

        if ($header !== '%PDF-') {
            $errors[] = $label . ' does not look like a valid PDF file.';
            return null;
        }

        $mime = 'application/pdf';
    } else {
        $image_info = @getimagesize($file['tmp_name']);

        if ($image_info === false || empty($image_info['mime']) || $image_info['mime'] !== $allowed[$extension]) {
            $errors[] = $label . ' is not a valid image file.';
            return null;
        }

        $mime = $image_info['mime'];
    }

    if (!defined('UPLOAD_PATH')) {
        $errors[] = 'Server upload configuration is missing.';
        return null;
    }

    $upload_dir = rtrim(UPLOAD_PATH, '/') . '/contact-requests/';

    if (!is_dir($upload_dir) && !mkdir($upload_dir, 0755, true) && !is_dir($upload_dir)) {
        $errors[] = 'The server could not store the uploaded file. Please try again later.';
        return null;
    }

    // Images are re-encoded to WEBP to shrink storage size; PDFs (and any
    // image GD/WEBP can't handle) are stored exactly as uploaded.
    $converted_image = $extension !== 'pdf' ? cf_convert_image_to_webp($file['tmp_name'], $mime) : null;
    $final_extension = $converted_image !== null ? 'webp' : $extension;
    $final_mime = $converted_image !== null ? 'image/webp' : $mime;

    try {
        $stored_name = 'cf_' . bin2hex(random_bytes(16)) . '.' . $final_extension;
    } catch (Throwable $e) {
        $stored_name = 'cf_' . str_replace('.', '', uniqid('', true)) . '.' . $final_extension;
    }

    $destination = $upload_dir . $stored_name;

    if ($converted_image !== null) {
        $pixel_count = imagesx($converted_image) * imagesy($converted_image);
        $quality = 88;

        if ($pixel_count >= 12000000) {
            $quality = 80;
        } elseif ($pixel_count >= 6000000) {
            $quality = 82;
        } elseif ($pixel_count >= 2000000) {
            $quality = 85;
        }

        $saved = @imagewebp($converted_image, $destination, $quality);
        imagedestroy($converted_image);

        if (!$saved) {
            $errors[] = $label . ' could not be processed. Please try again.';
            return null;
        }
    } elseif (!move_uploaded_file($file['tmp_name'], $destination)) {
        $errors[] = $label . ' could not be saved. Please try again.';
        return null;
    }

    @chmod($destination, 0644);

    return [
        'field' => $label,
        'path' => 'assets/uploads/contact-requests/' . $stored_name,
        'mime' => $final_mime,
        'size' => (int)(@filesize($destination) ?: $size),
    ];
}

/*
|--------------------------------------------------------------------------
| POST Handling
|--------------------------------------------------------------------------
*/
$errors = [];
$old = $_POST;
$sections = cf_subject_sections();
$selected_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $posted_token = (string)($_POST['csrf_token'] ?? '');

    if (!hash_equals($contact_csrf_token, $posted_token)) {
        $errors[] = 'Your session has expired. Please try submitting the form again.';
    }

    $selected_type = trim((string)($_POST['subject_type'] ?? ''));

    if (!array_key_exists($selected_type, $sections)) {
        $errors[] = 'Please select a valid subject.';
        $selected_type = '';
    }

    $form_data = [];
    $attachments = [];

    if ($selected_type !== '') {
        $section = $sections[$selected_type];

        foreach ($section['fields'] as $key => $field) {
            $type = $field['type'];

            if ($type === 'heading') {
                continue;
            }

            $required = !empty($field['required']);
            $label = (string)$field['label'];

            if ($type === 'file') {
                $file = $_FILES[$key] ?? ['error' => UPLOAD_ERR_NO_FILE];
                $uploaded = cf_validate_uploaded_file($file, (string)($field['accept'] ?? 'doc'), $label, $errors);

                if ($uploaded) {
                    $attachments[$key] = $uploaded;
                } elseif ($required && (int)($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                    $errors[] = $label . ' is required.';
                }

                continue;
            }

            if ($type === 'checkbox_group') {
                $selected = array_values(array_filter(array_map('trim', (array)($_POST[$key] ?? []))));
                $allowed_options = (array)($field['options'] ?? []);
                $selected = array_values(array_intersect($selected, $allowed_options));

                if ($required && !$selected) {
                    $errors[] = 'Please select at least one option for "' . $label . '".';
                }

                $form_data[$key] = $selected;
                continue;
            }

            $value = trim((string)($_POST[$key] ?? ''));

            if ($type === 'division') {
                $division_id = (int)($_POST[$key . '_id'] ?? 0);
                $value = '';

                if ($division_id > 0) {
                    foreach (get_divisions() as $division) {
                        if ((int)$division['id'] === $division_id) {
                            $value = (string)$division['name'];
                            break;
                        }
                    }
                }

                if ($required && $value === '') {
                    $errors[] = $label . ' is required.';
                }

                $form_data[$key] = $value;
                continue;
            }

            if ($type === 'district') {
                $district_id = (int)($_POST[$key . '_id'] ?? 0);
                $division_id = (int)($_POST['division_id'] ?? 0);
                $value = '';

                if ($district_id > 0 && $division_id > 0) {
                    foreach (get_districts_by_division($division_id) as $district) {
                        if ((int)$district['id'] === $district_id) {
                            $value = (string)$district['name'];
                            break;
                        }
                    }
                }

                if ($required && $value === '') {
                    $errors[] = $label . ' is required.';
                }

                $form_data[$key] = $value;
                continue;
            }

            if ($required && $value === '') {
                $errors[] = $label . ' is required.';
            }

            if ($type === 'email' && !cf_is_valid_email($value)) {
                $errors[] = 'Please enter a valid email address for "' . $label . '".';
            }

            if ($type === 'url' && !cf_is_valid_url($value)) {
                $errors[] = 'Please enter a valid URL for "' . $label . '".';
            }

            if ($type === 'tel' && !cf_is_valid_phone($value)) {
                $errors[] = 'Please enter a valid phone number for "' . $label . '".';
            }

            if ($type === 'select' && $value !== '' && !in_array($value, (array)($field['options'] ?? []), true)) {
                $errors[] = 'Please choose a valid option for "' . $label . '".';
            }

            $form_data[$key] = $value;
        }

        // "Other" subject's combined contact field validated as email OR phone.
        if ($selected_type === 'other') {
            $contact_value = (string)($form_data['email_or_mobile'] ?? '');

            if ($contact_value !== '' && !cf_is_valid_email($contact_value) && !cf_is_valid_phone($contact_value)) {
                $errors[] = 'Please enter a valid email address or mobile number.';
            }
        }
    }

    if (!$errors && $selected_type !== '') {
        $section = $sections[$selected_type];
        $identity = $section['identity'];

        $name = trim((string)($form_data[$identity['name']] ?? ''));

        if ($name === '' && !empty($identity['name_fallback'])) {
            $name = trim((string)($form_data[$identity['name_fallback']] ?? ''));
        }

        $email = trim((string)($form_data[$identity['email']] ?? ''));
        $phone = trim((string)($form_data[$identity['phone']] ?? ''));

        // "Other" combined field: route into whichever column actually fits.
        if ($selected_type === 'other') {
            $email = cf_is_valid_email($phone_or_email = (string)($form_data['email_or_mobile'] ?? '')) ? $phone_or_email : '';
            $phone = !cf_is_valid_email($phone_or_email) ? $phone_or_email : '';
        }

        $subject_label = cf_subject_labels()[$selected_type];

        $summary_lines = [];
        foreach ($section['fields'] as $key => $field) {
            if ($field['type'] === 'heading' || $field['type'] === 'file') {
                continue;
            }

            $val = $form_data[$key] ?? null;

            if (is_array($val)) {
                $val = implode(', ', $val);
            }

            if ((string)$val === '') {
                continue;
            }

            $summary_lines[] = $field['label'] . ': ' . $val;
        }

        $message_summary = implode("\n", $summary_lines);

        $client_ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? null;

        if (is_string($client_ip) && strpos($client_ip, ',') !== false) {
            $client_ip = trim(explode(',', $client_ip)[0]);
        }

        $stmt = $pdo->prepare("
            INSERT INTO contacts
                (name, email, phone, subject, request_type, message, form_data, attachments, status, review_status, ip_address, created_at)
            VALUES
                (:name, :email, :phone, :subject, :request_type, :message, :form_data, :attachments, 'unread', 'new', :ip_address, NOW())
        ");

        $stmt->execute([
            ':name' => $name,
            ':email' => $email,
            ':phone' => $phone,
            ':subject' => $subject_label,
            ':request_type' => $selected_type,
            ':message' => $message_summary,
            ':form_data' => json_encode($form_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ':attachments' => $attachments ? json_encode($attachments, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            ':ip_address' => $client_ip,
        ]);

        unset($_SESSION['contact_form_csrf']);
        flash('success', 'Thank you. Your request has been submitted successfully. Our team will review the information and contact you if additional details are required.');
        redirect(site_url('contact'));
    }
}

$page_title = 'Contact | MediCare';
$meta_description = 'Contact MediCare healthcare directory.';
$extra_head_html = ($extra_head_html ?? '') . '<link rel="stylesheet" href="' . e(site_url('assets/css/contact.css')) . '?v=' . e(front_asset_version()) . '">';

include __DIR__ . '/includes/header.php';

$cf_divisions = function_exists('get_divisions') ? get_divisions() : [];
?>

<main class="medic-contact-page">
  <div class="container">
    <?php show_flash(); ?>

    <nav class="medic-breadcrumb">
      <a href="<?= e(site_url()) ?>">Home</a>
      <span>/</span>
      <a href="<?= e(site_url('contact')) ?>">Contact</a>
    </nav>

    <section class="medic-contact-head">
      <div class="medic-contact-head-top">
        <div class="medic-contact-head-row">
          <div>
            <h1>Contact Us</h1>
            <p>Contact MediCare for doctor, hospital and appointment support.</p>
          </div>

          <div class="medic-support-badge">
            24/7 Support
          </div>
        </div>
      </div>

      <div class="medic-contact-head-bottom">
        <span><strong>Status:</strong> Support available</span>
        <span><strong>Response:</strong> As soon as possible</span>
      </div>
    </section>

    <section class="medic-contact-grid">
      <div class="medic-panel">
        <div class="medic-panel-head">
          <h2>Send a Request</h2>
          <p>Choose a subject below. The form will show only the fields that are relevant to your request.</p>
        </div>

        <div class="cf-bootstrap">
          <?php if ($errors): ?>
            <div class="alert alert-danger">
              <strong>Please fix the following before submitting:</strong>
              <ul>
                <?php foreach ($errors as $error): ?>
                  <li><?= e($error) ?></li>
                <?php endforeach; ?>
              </ul>
            </div>
          <?php endif; ?>

          <form id="cfForm" method="POST" action="<?= e(site_url('contact')) ?>" enctype="multipart/form-data" data-districts-url="<?= e(site_url('ajax/get-districts.php')) ?>" novalidate>
            <input type="hidden" name="csrf_token" value="<?= e($contact_csrf_token) ?>">

            <div class="mb-3">
              <label class="form-label" for="cfSubjectType">Subject <span class="text-danger">*</span></label>
              <select class="form-select" id="cfSubjectType" name="subject_type" required>
                <option value="" selected disabled>Select Subject</option>
                <?php foreach (cf_subject_labels() as $type_key => $type_label): ?>
                  <option value="<?= e($type_key) ?>" <?= $selected_type === $type_key ? 'selected' : '' ?>><?= e($type_label) ?></option>
                <?php endforeach; ?>
              </select>
              <div class="invalid-feedback">Please select a subject.</div>
            </div>

            <?php foreach ($sections as $type_key => $section): ?>
              <div class="cf-section <?= $selected_type === $type_key ? 'active' : '' ?>" id="cfSection_<?= e($type_key) ?>" data-section="<?= e($type_key) ?>">
                <?php foreach ($section['fields'] as $field_key => $field): ?>
                  <?php
                    $field_type = $field['type'];
                    $field_required = !empty($field['required']);
                    $field_id = 'cf_' . $type_key . '_' . $field_key;
                    $old_value = (string)($old[$field_key] ?? '');
                  ?>

                  <?php if ($field_type === 'heading'): ?>
                    <div class="cf-section-heading"><?= e($field['label']) ?></div>

                  <?php elseif ($field_type === 'textarea'): ?>
                    <div class="mb-3">
                      <label class="form-label" for="<?= e($field_id) ?>"><?= e($field['label']) ?><?= $field_required ? ' <span class="text-danger">*</span>' : '' ?></label>
                      <textarea
                        class="form-control cf-field"
                        id="<?= e($field_id) ?>"
                        name="<?= e($field_key) ?>"
                        data-required="<?= $field_required ? '1' : '0' ?>"
                        <?= $selected_type === $type_key && $field_required ? 'required' : '' ?>
                      ><?= e($old_value) ?></textarea>
                      <div class="invalid-feedback">This field is required.</div>
                    </div>

                  <?php elseif ($field_type === 'select'): ?>
                    <div class="mb-3">
                      <label class="form-label" for="<?= e($field_id) ?>"><?= e($field['label']) ?><?= $field_required ? ' <span class="text-danger">*</span>' : '' ?></label>
                      <select
                        class="form-select cf-field"
                        id="<?= e($field_id) ?>"
                        name="<?= e($field_key) ?>"
                        data-required="<?= $field_required ? '1' : '0' ?>"
                        <?= $selected_type === $type_key && $field_required ? 'required' : '' ?>
                      >
                        <option value="">-- Select --</option>
                        <?php foreach ($field['options'] as $option): ?>
                          <option value="<?= e($option) ?>" <?= $old_value === $option ? 'selected' : '' ?>><?= e($option) ?></option>
                        <?php endforeach; ?>
                      </select>
                      <div class="invalid-feedback">Please choose an option.</div>
                    </div>

                  <?php elseif ($field_type === 'checkbox_group'): ?>
                    <div class="mb-3">
                      <label class="form-label"><?= e($field['label']) ?><?= $field_required ? ' <span class="text-danger">*</span>' : '' ?></label>
                      <div class="form-check-group cf-checkbox-group" data-required="<?= $field_required ? '1' : '0' ?>" id="<?= e($field_id) ?>">
                        <?php foreach ($field['options'] as $i => $option): ?>
                          <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="<?= e($field_key) ?>[]" value="<?= e($option) ?>" id="<?= e($field_id . '_' . $i) ?>">
                            <label class="form-check-label" for="<?= e($field_id . '_' . $i) ?>"><?= e($option) ?></label>
                          </div>
                        <?php endforeach; ?>
                      </div>
                      <div class="invalid-feedback">Please select at least one option.</div>
                    </div>

                  <?php elseif ($field_type === 'file'): ?>
                    <div class="mb-3">
                      <label class="form-label" for="<?= e($field_id) ?>"><?= e($field['label']) ?><?= $field_required ? ' <span class="text-danger">*</span>' : '' ?></label>
                      <input
                        class="form-control cf-field"
                        type="file"
                        id="<?= e($field_id) ?>"
                        name="<?= e($field_key) ?>"
                        data-required="<?= $field_required ? '1' : '0' ?>"
                        accept="<?= $field['accept'] === 'doc' ? '.jpg,.jpeg,.png,.webp,.pdf' : '.jpg,.jpeg,.png,.webp' ?>"
                      >
                      <?php if (!empty($field['help'])): ?>
                        <div class="form-text"><?= e($field['help']) ?></div>
                      <?php endif; ?>
                      <div class="invalid-feedback">This file is required.</div>
                    </div>

                  <?php elseif ($field_type === 'division'): ?>
                    <div class="mb-3">
                      <label class="form-label" for="<?= e($field_id) ?>"><?= e($field['label']) ?><?= $field_required ? ' <span class="text-danger">*</span>' : '' ?></label>
                      <select
                        class="form-select cf-field cf-division-select"
                        id="<?= e($field_id) ?>"
                        name="division_id"
                        data-required="<?= $field_required ? '1' : '0' ?>"
                        data-district-target="cf_<?= e($type_key) ?>_district"
                      >
                        <option value="">-- Select Division --</option>
                        <?php foreach ($cf_divisions as $division): ?>
                          <option value="<?= e((string)$division['id']) ?>"><?= e($division['name']) ?></option>
                        <?php endforeach; ?>
                      </select>
                      <div class="invalid-feedback">Please choose a division.</div>
                    </div>

                  <?php elseif ($field_type === 'district'): ?>
                    <div class="mb-3">
                      <label class="form-label" for="cf_<?= e($type_key) ?>_district"><?= e($field['label']) ?><?= $field_required ? ' <span class="text-danger">*</span>' : '' ?></label>
                      <select
                        class="form-select cf-field"
                        id="cf_<?= e($type_key) ?>_district"
                        name="district_id"
                        data-required="<?= $field_required ? '1' : '0' ?>"
                      >
                        <option value="">-- Select Division First --</option>
                      </select>
                      <div class="invalid-feedback">Please choose a district.</div>
                    </div>

                  <?php else: ?>
                    <div class="mb-3 <?= ($field_key === 'bmdc_number') ? '' : '' ?>" <?= !empty($field['id']) ? 'id="' . e($field['id']) . '_wrap"' : '' ?>>
                      <label class="form-label" for="<?= e($field_id) ?>"><?= e($field['label']) ?><?= $field_required ? ' <span class="text-danger">*</span>' : '' ?></label>
                      <input
                        class="form-control cf-field"
                        type="<?= e($field_type) ?>"
                        id="<?= e($field_id) ?>"
                        name="<?= e($field_key) ?>"
                        value="<?= e($old_value) ?>"
                        data-required="<?= $field_required ? '1' : '0' ?>"
                        <?= $selected_type === $type_key && $field_required ? 'required' : '' ?>
                      >
                      <?php if (!empty($field['help'])): ?>
                        <div class="form-text"><?= e($field['help']) ?></div>
                      <?php endif; ?>
                      <div class="invalid-feedback">This field is required.</div>
                    </div>
                  <?php endif; ?>
                <?php endforeach; ?>
              </div>
            <?php endforeach; ?>

            <button class="btn btn-primary" type="submit" id="cfSubmitBtn">
              <span class="spinner-border" role="status" aria-hidden="true"></span>
              <span class="cf-btn-label">Send Message</span>
            </button>
          </form>
        </div>
      </div>

      <aside class="medic-panel">
        <div class="medic-panel-head">
          <h2>Contact Info</h2>
          <p>Use the details below to reach the MediCare team.</p>
        </div>

        <div class="medic-info-list">
          <div class="medic-info-item">
            <div class="medic-info-icon">⌂</div>
            <div>
              <h3>Location</h3>
              <p>Dhaka, Bangladesh</p>
            </div>
          </div>

          <div class="medic-info-item">
            <div class="medic-info-icon">☎</div>
            <div>
              <h3>Phone</h3>
              <a href="tel:+8801234567890">+880 1234 567 890</a>
            </div>
          </div>

          <div class="medic-info-item">
            <div class="medic-info-icon">@</div>
            <div>
              <h3>Email</h3>
              <a href="mailto:info@example.com">info@example.com</a>
            </div>
          </div>
        </div>

        <div class="medic-map-box">
          Map Area
        </div>
      </aside>
    </section>
  </div>
</main>

<script src="<?= e(site_url('assets/js/contact.js')) ?>" defer></script>

<?php include __DIR__ . '/includes/footer.php'; ?>
