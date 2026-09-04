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

include __DIR__ . '/includes/header.php';

$cf_divisions = function_exists('get_divisions') ? get_divisions() : [];
?>

<style>
  .medic-contact-page {
    background: #f6f8fa;
    padding: 24px 0 56px;
    color: #24292f;
  }

  .medic-breadcrumb {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 7px;
    margin: 0 0 16px;
    color: #57606a;
    font-size: 14px;
  }

  .medic-breadcrumb a {
    color: #0969da;
    text-decoration: none;
    font-weight: 600;
  }

  .medic-breadcrumb a:hover {
    text-decoration: underline;
  }

  .medic-breadcrumb span {
    color: #8c959f;
  }

  .medic-contact-head {
    overflow: hidden;
    margin-bottom: 16px;
    border: 1px solid #d0d7de;
    border-radius: 6px;
    background: #ffffff;
  }

  .medic-contact-head-top {
    padding: 20px;
    border-bottom: 1px solid #d8dee4;
    background: linear-gradient(180deg, #ffffff, #f6f8fa);
  }

  .medic-contact-head-row {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 18px;
  }

  .medic-contact-head h1 {
    margin: 0 0 6px;
    color: #24292f;
    font-size: 28px;
    line-height: 1.2;
    letter-spacing: -0.03em;
    font-weight: 800;
  }

  .medic-contact-head p {
    margin: 0;
    color: #57606a;
    font-size: 14px;
    line-height: 1.6;
  }

  .medic-support-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 30px;
    padding: 5px 10px;
    border-radius: 999px;
    background: #dafbe1;
    color: #1a7f37;
    border: 1px solid rgba(26, 127, 55, 0.20);
    font-size: 12px;
    font-weight: 700;
    white-space: nowrap;
  }

  .medic-contact-head-bottom {
    display: flex;
    flex-wrap: wrap;
    gap: 8px 14px;
    padding: 12px 20px;
    background: #ffffff;
    color: #57606a;
    font-size: 13px;
  }

  .medic-contact-head-bottom strong {
    color: #24292f;
    font-weight: 700;
  }

  .medic-contact-grid {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 340px;
    gap: 16px;
    align-items: start;
  }

  .medic-panel {
    overflow: hidden;
    border: 1px solid #d0d7de;
    border-radius: 6px;
    background: #ffffff;
  }

  .medic-panel-head {
    padding: 14px 16px;
    border-bottom: 1px solid #d8dee4;
    background: #f6f8fa;
  }

  .medic-panel-head h2 {
    margin: 0;
    color: #24292f;
    font-size: 16px;
    line-height: 1.35;
    font-weight: 800;
  }

  .medic-panel-head p {
    margin: 4px 0 0;
    color: #57606a;
    font-size: 13px;
    line-height: 1.5;
  }

  /*
   * Scoped, Bootstrap-flavored form styles. We reuse Bootstrap's exact
   * class names (form-control, form-select, form-label, btn, form-check,
   * spinner-border, invalid-feedback, alert) so the markup is a drop-in
   * match for real Bootstrap, but the rules are scoped under
   * .cf-bootstrap so nothing here can leak into the shared site header
   * or footer -- loading Bootstrap's global CSS on this page would have
   * reset those (typography, link colors, etc.) since they share the
   * same document.
   */
  .cf-bootstrap {
    padding: 18px;
  }

  .cf-bootstrap * {
    box-sizing: border-box;
  }

  .cf-bootstrap .form-label {
    display: inline-block;
    margin-bottom: 6px;
    font-size: 14px;
    font-weight: 500;
    color: #212529;
  }

  .cf-bootstrap .text-danger {
    color: #dc3545;
  }

  .cf-bootstrap .form-control,
  .cf-bootstrap .form-select {
    display: block;
    width: 100%;
    padding: 8px 12px;
    font-size: 14px;
    font-weight: 400;
    line-height: 1.5;
    color: #212529;
    background-color: #fff;
    border: 1px solid #ced4da;
    border-radius: 6px;
    transition: border-color .15s ease-in-out, box-shadow .15s ease-in-out;
  }

  .cf-bootstrap textarea.form-control {
    min-height: 110px;
    resize: vertical;
  }

  .cf-bootstrap .form-select {
    cursor: pointer;
  }

  .cf-bootstrap .form-control:focus,
  .cf-bootstrap .form-select:focus {
    border-color: #86b7fe;
    outline: 0;
    box-shadow: 0 0 0 .25rem rgba(13, 110, 253, .18);
  }

  .cf-bootstrap .form-control.is-invalid,
  .cf-bootstrap .form-select.is-invalid {
    border-color: #dc3545;
  }

  .cf-bootstrap .invalid-feedback {
    display: none;
    margin-top: 4px;
    font-size: 12.5px;
    color: #dc3545;
  }

  .cf-bootstrap .is-invalid ~ .invalid-feedback,
  .cf-bootstrap .invalid-feedback.d-show {
    display: block;
  }

  .cf-bootstrap .form-text {
    margin-top: 4px;
    font-size: 12.5px;
    color: #6c757d;
  }

  .cf-bootstrap .mb-3 {
    margin-bottom: 16px;
  }

  .cf-bootstrap .row {
    display: flex;
    flex-wrap: wrap;
    margin: 0 -8px;
  }

  .cf-bootstrap .row > [class*="col-"] {
    padding: 0 8px;
    margin-bottom: 16px;
  }

  .cf-bootstrap .col-12 {
    width: 100%;
  }

  .cf-bootstrap .col-md-6 {
    width: 100%;
  }

  @media (min-width: 640px) {
    .cf-bootstrap .col-md-6 {
      width: 50%;
    }
  }

  .cf-bootstrap .form-check {
    display: block;
    min-height: 1.4rem;
    padding-left: 1.6em;
    margin-bottom: 6px;
  }

  .cf-bootstrap .form-check-input {
    width: 1em;
    height: 1em;
    margin-top: 0.28em;
    margin-left: -1.6em;
    vertical-align: top;
    cursor: pointer;
  }

  .cf-bootstrap .form-check-label {
    font-size: 13.5px;
    color: #212529;
    cursor: pointer;
  }

  .cf-bootstrap .form-check-group {
    padding: 10px 12px;
    border: 1px solid #ced4da;
    border-radius: 6px;
    background: #f8f9fa;
  }

  .cf-bootstrap .form-check-group.is-invalid {
    border-color: #dc3545;
  }

  .cf-bootstrap .cf-section-heading {
    margin: 6px 0 4px;
    padding-top: 10px;
    border-top: 1px solid #e9ecef;
    font-size: 13px;
    font-weight: 700;
    color: #495057;
    text-transform: uppercase;
    letter-spacing: 0.03em;
  }

  .cf-bootstrap .cf-section-heading:first-child {
    padding-top: 0;
    border-top: 0;
  }

  .cf-bootstrap .cf-highlight {
    padding: 10px;
    border-radius: 6px;
    border: 1px solid #ffe69c;
    background: #fff8e6;
    transition: background-color .2s ease, border-color .2s ease;
  }

  .cf-bootstrap .cf-highlight .form-text {
    color: #8a6d1f;
  }

  .cf-bootstrap .btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    min-height: 44px;
    padding: 0 20px;
    border-radius: 6px;
    border: 1px solid transparent;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    width: 100%;
  }

  .cf-bootstrap .btn-primary {
    background: #2da44e;
    border-color: #2da44e;
    color: #ffffff;
  }

  .cf-bootstrap .btn-primary:hover:not(:disabled) {
    background: #1f883d;
  }

  .cf-bootstrap .btn:disabled {
    opacity: 0.75;
    cursor: not-allowed;
  }

  .cf-bootstrap .spinner-border {
    display: none;
    width: 1rem;
    height: 1rem;
    border: 0.18em solid currentColor;
    border-right-color: transparent;
    border-radius: 50%;
    animation: cf-spin 0.75s linear infinite;
  }

  .cf-bootstrap .btn.cf-loading .spinner-border {
    display: inline-block;
  }

  @keyframes cf-spin {
    to { transform: rotate(360deg); }
  }

  .cf-bootstrap .alert {
    padding: 12px 14px;
    border-radius: 6px;
    margin-bottom: 16px;
    font-size: 13.5px;
    border: 1px solid transparent;
  }

  .cf-bootstrap .alert-danger {
    background: #f8d7da;
    border-color: #f5c2c7;
    color: #842029;
  }

  .cf-bootstrap .alert-danger ul {
    margin: 4px 0 0;
    padding-left: 18px;
  }

  .cf-section {
    display: none;
    animation: cf-fade-in .18s ease;
  }

  .cf-section.active {
    display: block;
  }

  @keyframes cf-fade-in {
    from { opacity: 0; transform: translateY(-4px); }
    to { opacity: 1; transform: translateY(0); }
  }

  .medic-info-list {
    display: grid;
    gap: 0;
  }

  .medic-info-item {
    display: grid;
    grid-template-columns: 36px minmax(0, 1fr);
    gap: 12px;
    padding: 14px 16px;
    border-bottom: 1px solid #d8dee4;
  }

  .medic-info-item:last-child {
    border-bottom: 0;
  }

  .medic-info-icon {
    width: 36px;
    height: 36px;
    border-radius: 6px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: #ddf4ff;
    color: #0969da;
    border: 1px solid rgba(9, 105, 218, 0.14);
    font-size: 16px;
    font-weight: 800;
  }

  .medic-info-item h3 {
    margin: 0 0 4px;
    color: #24292f;
    font-size: 14px;
    line-height: 1.35;
    font-weight: 800;
  }

  .medic-info-item p,
  .medic-info-item a {
    margin: 0;
    color: #57606a;
    font-size: 13px;
    line-height: 1.5;
    text-decoration: none;
  }

  .medic-info-item a:hover {
    color: #0969da;
    text-decoration: underline;
  }

  .medic-map-box {
    margin: 16px;
    min-height: 170px;
    border-radius: 6px;
    border: 1px dashed #d0d7de;
    background:
      linear-gradient(135deg, rgba(9, 105, 218, 0.08), rgba(45, 164, 78, 0.08)),
      #f6f8fa;
    display: grid;
    place-items: center;
    color: #57606a;
    font-size: 13px;
    font-weight: 700;
  }

  @media (max-width: 900px) {
    .medic-contact-grid {
      grid-template-columns: 1fr;
    }
  }

  @media (max-width: 760px) {
    .medic-contact-page {
      padding-top: 18px;
    }

    .medic-contact-head-row {
      flex-direction: column;
    }

    .medic-contact-head-top {
      padding: 16px;
    }

    .medic-contact-head-bottom {
      padding: 12px 16px;
    }

    .medic-contact-head h1 {
      font-size: 26px;
    }

    .medic-support-badge {
      width: 100%;
    }

    .cf-bootstrap {
      padding: 14px;
    }
  }

  @media (max-width: 420px) {
    .medic-info-item {
      grid-template-columns: 1fr;
    }
  }
</style>

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

          <form id="cfForm" method="POST" action="<?= e(site_url('contact')) ?>" enctype="multipart/form-data" novalidate>
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

<script>
(function () {
  var form = document.getElementById('cfForm');
  var subjectSelect = document.getElementById('cfSubjectType');
  var submitBtn = document.getElementById('cfSubmitBtn');

  function setSectionRequired(section, isActive) {
    // Several field keys (common_name, common_email, doctor_name, division_id,
    // district_id, etc.) repeat across multiple sections' schemas. All 9
    // sections stay in the DOM at once (only CSS-hidden), so an inactive
    // section's inputs must be disabled -- otherwise the browser still submits
    // them, and since they share the same "name" as the active section's
    // fields, PHP's $_POST keeps only the last one in document order and
    // silently wipes out the real answers.
    var fields = section.querySelectorAll('.cf-field');

    fields.forEach(function (field) {
      var isRequired = field.getAttribute('data-required') === '1';

      field.disabled = !isActive;

      if (isActive && isRequired) {
        field.setAttribute('required', 'required');
      } else {
        field.removeAttribute('required');
      }

      field.classList.remove('is-invalid');
    });

    var groups = section.querySelectorAll('.cf-checkbox-group');
    groups.forEach(function (group) {
      group.classList.remove('is-invalid');
      group.querySelectorAll('input[type="checkbox"]').forEach(function (checkbox) {
        checkbox.disabled = !isActive;
      });
    });
  }

  function showSection(type) {
    document.querySelectorAll('.cf-section').forEach(function (section) {
      var isActive = section.getAttribute('data-section') === type;
      section.classList.toggle('active', isActive);
      setSectionRequired(section, isActive);
    });
  }

  if (subjectSelect) {
    subjectSelect.addEventListener('change', function () {
      showSection(this.value);
    });

    showSection(subjectSelect.value);
  }

  // Division -> District cascading selects (reuses the existing
  // ajax/get-districts.php endpoint already used elsewhere on the site).
  document.querySelectorAll('.cf-division-select').forEach(function (select) {
    select.addEventListener('change', function () {
      var targetId = this.getAttribute('data-district-target');
      var target = document.getElementById(targetId);

      if (!target) {
        return;
      }

      target.innerHTML = '<option value="">Loading...</option>';

      if (!this.value) {
        target.innerHTML = '<option value="">-- Select Division First --</option>';
        return;
      }

      fetch('<?= e(site_url('ajax/get-districts.php')) ?>?division_id=' + encodeURIComponent(this.value))
        .then(function (res) { return res.json(); })
        .then(function (districts) {
          var html = '<option value="">-- Select District --</option>';
          (districts || []).forEach(function (district) {
            html += '<option value="' + district.id + '">' + district.name + '</option>';
          });
          target.innerHTML = html;
        })
        .catch(function () {
          target.innerHTML = '<option value="">-- Could not load districts --</option>';
        });
    });
  });

  // Claim Doctor: highlight the BMDC field when "I am the Doctor" is selected.
  var relationshipField = document.getElementById('cf_claim_doctor_relationship');
  var bmdcWrap = document.getElementById('bmdcNumberField_wrap');

  if (relationshipField && bmdcWrap) {
    var toggleBmdcHighlight = function () {
      bmdcWrap.classList.toggle('cf-highlight', relationshipField.value === 'I am the Doctor');
    };

    relationshipField.addEventListener('change', toggleBmdcHighlight);
    toggleBmdcHighlight();
  }

  // Client-side validation + prevent duplicate submissions.
  if (form) {
    form.addEventListener('submit', function (event) {
      var activeSection = document.querySelector('.cf-section.active');
      var valid = true;

      if (!subjectSelect.value) {
        subjectSelect.classList.add('is-invalid');
        valid = false;
      } else {
        subjectSelect.classList.remove('is-invalid');
      }

      if (activeSection) {
        activeSection.querySelectorAll('.cf-field[required]').forEach(function (field) {
          if (!field.value || (field.type === 'file' && field.files.length === 0)) {
            field.classList.add('is-invalid');
            valid = false;
          } else if (field.type === 'email' && field.validity && !field.validity.valid) {
            field.classList.add('is-invalid');
            valid = false;
          } else if (field.type === 'url' && field.validity && !field.validity.valid) {
            field.classList.add('is-invalid');
            valid = false;
          } else {
            field.classList.remove('is-invalid');
          }
        });

        activeSection.querySelectorAll('.cf-checkbox-group[data-required="1"]').forEach(function (group) {
          var checked = group.querySelectorAll('input[type="checkbox"]:checked').length;

          if (checked === 0) {
            group.classList.add('is-invalid');
            valid = false;
          } else {
            group.classList.remove('is-invalid');
          }
        });
      }

      if (!valid) {
        event.preventDefault();
        return;
      }

      if (submitBtn.classList.contains('cf-loading')) {
        event.preventDefault();
        return;
      }

      submitBtn.classList.add('cf-loading');
      submitBtn.disabled = true;
      submitBtn.querySelector('.cf-btn-label').textContent = 'Sending...';
    });
  }
})();
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
