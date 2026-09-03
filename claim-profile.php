<?php
require_once __DIR__ . '/includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/*
|--------------------------------------------------------------------------
| Claim Profile Helpers
|--------------------------------------------------------------------------
*/
function cp_table_exists(string $table): bool
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

function cp_column_exists(string $table, string $column): bool
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
            ':table' => $table,
            ':column' => $column,
        ]);

        return (int)$stmt->fetchColumn() > 0;
    } catch (Throwable $e) {
        return false;
    }
}

function cp_asset_url(string $path, string $fallback = ''): string
{
    $path = trim($path);

    if ($path === '') {
        $path = $fallback;
    }

    if ($path === '') {
        return '';
    }

    if (preg_match('/^https?:\/\//i', $path)) {
        return $path;
    }

    return site_url(ltrim($path, '/'));
}

function cp_clean_text(string $value, int $max_length = 500): string
{
    $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $value = strip_tags($value);
    $value = preg_replace('/https?:\/\/[^\s]+/iu', '', (string)$value);
    $value = preg_replace('/www\.[^\s]+/iu', '', (string)$value);
    $value = preg_replace('/[a-z0-9._%+\-]+@[a-z0-9.\-]+\.[a-z]{2,}/iu', '', (string)$value);
    $value = preg_replace('/\s+/u', ' ', (string)$value);
    $value = trim((string)$value);

    if (function_exists('mb_substr')) {
        return mb_substr($value, 0, $max_length, 'UTF-8');
    }

    return substr($value, 0, $max_length);
}

function cp_profile_url(string $type, array $profile): string
{
    $slug = trim((string)($profile['slug'] ?? ''));

    if ($type === 'doctor' && $slug !== '') {
        return site_url('doctor/' . $slug);
    }

    if ($type === 'hospital' && $slug !== '') {
        return site_url('hospital/' . $slug);
    }

    return site_url($type === 'doctor' ? 'doctors' : 'hospitals');
}

function cp_get_profile(string $type, int $id = 0, string $name = '', string $slug = ''): array
{
    global $pdo;

    $table = $type === 'doctor' ? 'doctors' : 'hospitals';

    if (!cp_table_exists($table)) {
        return [];
    }

    $where = [];
    $params = [];

    if ($slug !== '' && cp_column_exists($table, 'slug')) {
        $where[] = 'slug = :slug';
        $params[':slug'] = $slug;
    }

    if ($name !== '') {
        if (cp_column_exists($table, 'slug')) {
            $where[] = 'slug = :name_as_slug';
            $params[':name_as_slug'] = $name;
        }

        if (cp_column_exists($table, 'name')) {
            $where[] = 'name = :name';
            $params[':name'] = $name;
        }
    }

    if ($id > 0) {
        $where[] = 'id = :id';
        $params[':id'] = $id;
    }

    if (empty($where)) {
        return [];
    }

    try {
        $stmt = $pdo->prepare("SELECT * FROM {$table} WHERE " . implode(' OR ', $where) . " LIMIT 1");
        $stmt->execute($params);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        error_log('Claim profile fetch failed: ' . $e->getMessage());
        return [];
    }
}

$type = trim((string)($_GET['type'] ?? 'doctor'));
$id = (int)($_GET['id'] ?? 0);
$name = trim((string)($_GET['name'] ?? ''));
$slug = trim((string)($_GET['slug'] ?? ''));

if (!in_array($type, ['doctor', 'hospital'], true)) {
    http_response_code(404);
    exit('Invalid claim request.');
}

$profile = cp_get_profile($type, $id, $name, $slug);

if (!$profile) {
    http_response_code(404);
    exit('Profile not found.');
}

$profile_id = (int)($profile['id'] ?? 0);
$profile_name = trim((string)($profile['name'] ?? 'Profile'));
$profile_slug = trim((string)($profile['slug'] ?? ''));
$profile_type_label = $type === 'doctor' ? 'Doctor Profile' : 'Hospital Profile';
$profile_url = cp_profile_url($type, $profile);

if ($type === 'doctor') {
    $profile_subtitle = trim(implode(' | ', array_filter([
        trim((string)($profile['designation'] ?? '')),
        trim((string)($profile['degree'] ?? '')),
    ])));
    $profile_image = cp_asset_url((string)($profile['image'] ?? ''), 'assets/images/default-doctor.png');
} else {
    $profile_subtitle = trim(implode(', ', array_filter([
        trim((string)($profile['address'] ?? '')),
        trim((string)($profile['city'] ?? '')),
    ])));
    $profile_image = cp_asset_url((string)($profile['image'] ?? ($profile['logo'] ?? '')), 'assets/images/default-hospital.png');
}

if ($profile_subtitle === '') {
    $profile_subtitle = $profile_type_label;
}

if (empty($_SESSION['claim_profile_token'])) {
    $_SESSION['claim_profile_token'] = bin2hex(random_bytes(32));
}

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $posted_token = (string)($_POST['claim_profile_token'] ?? '');

    if (!hash_equals((string)($_SESSION['claim_profile_token'] ?? ''), $posted_token)) {
        $error = 'Invalid security token. Please refresh the page and try again.';
    } else {
        $name_input = cp_clean_text((string)($_POST['name'] ?? ''), 150);
        $phone = cp_clean_text((string)($_POST['phone'] ?? ''), 80);
        $email = trim((string)($_POST['email'] ?? ''));
        $message = cp_clean_text((string)($_POST['message'] ?? ''), 1000);

        if ($name_input === '' || $phone === '' || $email === '') {
            $error = 'Name, phone and email are required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } elseif (!cp_table_exists('profile_claims')) {
            $error = 'Profile claim system is not ready. Please create the profile_claims table first.';
        } else {
            $proof_file = null;

            if (!empty($_FILES['proof_file']['name'])) {
                $allowed_ext = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];
                $max_size = 5 * 1024 * 1024;
                $upload_dir = __DIR__ . '/uploads/profile-claims/';

                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }

                $original_name = (string)($_FILES['proof_file']['name'] ?? '');
                $tmp_name = (string)($_FILES['proof_file']['tmp_name'] ?? '');
                $file_size = (int)($_FILES['proof_file']['size'] ?? 0);
                $ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));

                if (!in_array($ext, $allowed_ext, true)) {
                    $error = 'Only JPG, PNG, WEBP or PDF proof file is allowed.';
                } elseif ($file_size > $max_size) {
                    $error = 'Proof file size must be 5MB or less.';
                } elseif (!is_uploaded_file($tmp_name)) {
                    $error = 'Invalid proof file upload.';
                } else {
                    $new_name = 'claim_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
                    $target_path = $upload_dir . $new_name;

                    if (move_uploaded_file($tmp_name, $target_path)) {
                        $proof_file = 'uploads/profile-claims/' . $new_name;
                    } else {
                        $error = 'Proof file upload failed.';
                    }
                }
            }

            if ($error === '') {
                $user_id = (int)($_SESSION['user_id'] ?? 0);
                $doctor_id = $type === 'doctor' ? $profile_id : null;
                $hospital_id = $type === 'hospital' ? $profile_id : null;

                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO profile_claims
                        (
                            user_id,
                            claim_type,
                            doctor_id,
                            hospital_id,
                            name,
                            phone,
                            email,
                            message,
                            proof_file,
                            status,
                            created_at
                        )
                        VALUES
                        (
                            :user_id,
                            :claim_type,
                            :doctor_id,
                            :hospital_id,
                            :name,
                            :phone,
                            :email,
                            :message,
                            :proof_file,
                            'pending',
                            NOW()
                        )
                    ");

                    $stmt->execute([
                        ':user_id' => $user_id,
                        ':claim_type' => $type,
                        ':doctor_id' => $doctor_id,
                        ':hospital_id' => $hospital_id,
                        ':name' => $name_input,
                        ':phone' => $phone,
                        ':email' => $email,
                        ':message' => $message,
                        ':proof_file' => $proof_file,
                    ]);

                    $_SESSION['claim_profile_token'] = bin2hex(random_bytes(32));
                    $success = 'Your profile claim request has been submitted successfully. Admin will review it soon.';
                } catch (Throwable $e) {
                    error_log('Claim profile submit failed: ' . $e->getMessage());
                    $error = 'Claim request could not be submitted right now. Please try again later.';
                }
            }
        }
    }
}

$page_title = 'Claim ' . $profile_name;
$meta_description = 'Claim this profile and request ownership verification for ' . $profile_name . '.';

include __DIR__ . '/includes/header.php';
?>

<style>
  .cp-page {
    background: #ffffff;
    padding: 26px 0 60px;
    color: #252525;
  }

  .cp-shell {
    max-width: 560px;
    margin: 0 auto;
    padding: 0 18px;
  }

  .cp-profile-box {
    display: grid;
    grid-template-columns: 56px minmax(0, 1fr);
    gap: 16px;
    align-items: center;
    margin: 10px auto 42px;
    padding: 16px 18px;
    border-radius: 16px;
    background: #f7f6f3;
    max-width: 520px;
  }

  .cp-profile-box img {
    width: 56px;
    height: 56px;
    border-radius: 7px;
    object-fit: cover;
    border: 1px solid #e4e1da;
    background: #ffffff;
  }

  .cp-profile-box strong {
    display: block;
    color: #191919;
    font-size: 18px;
    line-height: 1.25;
    font-weight: 700;
  }

  .cp-profile-box span {
    display: block;
    margin-top: 3px;
    color: #191919;
    font-size: 16px;
    line-height: 1.35;
    font-weight: 400;
  }

  .cp-profile-box a {
    color: inherit;
    text-decoration: none;
  }

  .cp-card {
    background: transparent;
    border: 0;
    box-shadow: none;
  }

  .cp-title {
    margin: 0 0 10px;
    color: #1f2328;
    font-size: 32px;
    line-height: 1.2;
    font-weight: 700;
    letter-spacing: -.03em;
  }

  .cp-subtitle {
    margin: 0 0 26px;
    color: #565656;
    font-size: 15px;
    line-height: 1.65;
  }

  .cp-alert {
    margin: 0 0 18px;
    padding: 12px 14px;
    border-radius: 8px;
    border: 1px solid #d8dee4;
    background: #f6f8fa;
    color: #57606a;
    line-height: 1.6;
    font-size: 14px;
  }

  .cp-alert.success {
    background: #dafbe1;
    border-color: rgba(26,127,55,.25);
    color: #116329;
  }

  .cp-alert.error {
    background: #ffebe9;
    border-color: rgba(207,34,46,.25);
    color: #cf222e;
  }

  .cp-form {
    display: grid;
    gap: 22px;
  }

  .cp-field {
    display: grid;
    gap: 8px;
  }

  .cp-field label {
    color: #252525;
    font-size: 16px;
    line-height: 1.45;
    font-weight: 500;
  }

  .cp-field small {
    color: #565656;
    font-size: 13px;
    line-height: 1.55;
  }

  .cp-input,
  .cp-textarea {
    width: 100%;
    min-height: 47px;
    padding: 0 16px;
    border: 1px solid #555555;
    border-radius: 4px;
    background: #ffffff;
    color: #252525;
    font-family: inherit;
    font-size: 15px;
    outline: none;
    box-shadow: none;
  }

  .cp-textarea {
    min-height: 145px;
    padding: 12px 16px;
    resize: vertical;
    line-height: 1.6;
  }

  .cp-input:focus,
  .cp-textarea:focus {
    border-color: #3157d6;
    box-shadow: 0 0 0 3px rgba(49,87,214,.12);
  }

  .cp-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    align-items: center;
  }

  .cp-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 46px;
    padding: 0 18px;
    border-radius: 5px;
    border: 1px solid #00b67a;
    background: #00b67a;
    color: #ffffff;
    text-decoration: none;
    font-size: 15px;
    font-weight: 700;
    cursor: pointer;
    font-family: inherit;
  }

  .cp-btn:hover {
    background: #009e6a;
    border-color: #009e6a;
    color: #ffffff;
    text-decoration: none;
  }

  .cp-btn-outline {
    background: #ffffff;
    border-color: #d6d1c8;
    color: #191919;
  }

  .cp-btn-outline:hover {
    background: #f6f6f3;
    border-color: #d6d1c8;
    color: #191919;
  }

  @media (max-width: 640px) {
    .cp-page {
      padding-top: 18px;
    }

    .cp-profile-box {
      margin-bottom: 28px;
    }

    .cp-title {
      font-size: 28px;
    }

    .cp-actions {
      display: grid;
      grid-template-columns: 1fr;
    }

    .cp-btn {
      width: 100%;
    }
  }
</style>

<main class="cp-page">
  <div class="cp-shell">
    <div class="cp-profile-box">
      <a href="<?= e($profile_url) ?>">
        <img src="<?= e($profile_image) ?>" alt="<?= e($profile_name) ?>" loading="lazy">
      </a>

      <div>
        <strong><a href="<?= e($profile_url) ?>"><?= e($profile_name) ?></a></strong>
        <span><?= e($profile_subtitle) ?></span>
      </div>
    </div>

    <section class="cp-card">
      <h1 class="cp-title">Claim Profile</h1>
      <p class="cp-subtitle">Submit your ownership request for <strong><?= e($profile_name) ?></strong>. Admin will review your information before approving access.</p>

      <?php if ($success): ?>
        <div class="cp-alert success"><?= e($success) ?></div>
      <?php endif; ?>

      <?php if ($error): ?>
        <div class="cp-alert error"><?= e($error) ?></div>
      <?php endif; ?>

      <form method="post" enctype="multipart/form-data" class="cp-form">
        <input type="hidden" name="claim_profile_token" value="<?= e((string)($_SESSION['claim_profile_token'] ?? '')) ?>">

        <div class="cp-field">
          <label for="claim_name">Your Name *</label>
          <input class="cp-input" type="text" id="claim_name" name="name" required maxlength="150" autocomplete="name">
        </div>

        <div class="cp-field">
          <label for="claim_phone">Phone *</label>
          <input class="cp-input" type="text" id="claim_phone" name="phone" required maxlength="80" autocomplete="tel">
        </div>

        <div class="cp-field">
          <label for="claim_email">Email *</label>
          <input class="cp-input" type="email" id="claim_email" name="email" required maxlength="180" autocomplete="email">
        </div>

        <div class="cp-field">
          <label for="claim_message">Message</label>
          <textarea class="cp-textarea" id="claim_message" name="message" rows="5" maxlength="1000" placeholder="Write a short note about why you are claiming this profile."></textarea>
        </div>

        <div class="cp-field">
          <label for="claim_proof_file">Proof File</label>
          <input class="cp-input" type="file" id="claim_proof_file" name="proof_file" accept=".jpg,.jpeg,.png,.webp,.pdf">
          <small>Upload BMDC card, hospital document, visiting card, or any valid proof. Max size: 5MB.</small>
        </div>

        <div class="cp-actions">
          <button type="submit" class="cp-btn">Submit Claim Request</button>
          <a href="<?= e($profile_url) ?>" class="cp-btn cp-btn-outline">Back to <?= e($profile_type_label) ?></a>
        </div>
      </form>
    </section>
  </div>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
