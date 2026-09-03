<?php
require_once __DIR__ . '/includes/header.php';

if (!table_exists('reviews')) {
    echo '<h1 style="margin-bottom:18px;color:#24292f;">Reviews</h1>';
    echo '<div class="card"><p>The reviews table was not found. Please import the latest database SQL first.</p></div>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

$has_title = column_exists('reviews', 'title');
$has_treatment_type = column_exists('reviews', 'treatment_type');
$has_visit_date = column_exists('reviews', 'visit_date');
$has_is_anonymous = column_exists('reviews', 'is_anonymous');
$has_is_verified_patient = column_exists('reviews', 'is_verified_patient');
$has_hospital_id = column_exists('reviews', 'hospital_id');

$doctor_id_nullable = false;

try {
    $stmt = $pdo->prepare("
        SELECT IS_NULLABLE
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'reviews'
        AND COLUMN_NAME = 'doctor_id'
        LIMIT 1
    ");
    $stmt->execute();
    $doctor_id_nullable = strtoupper((string)$stmt->fetchColumn()) === 'YES';
} catch (Throwable $e) {
    $doctor_id_nullable = false;
}

function admin_review_clean_text(string $value, int $limit = 1000): string
{
    $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $value = strip_tags($value);
    $value = preg_replace('/https?:\/\/[^\s]+/iu', '', (string)$value);
    $value = preg_replace('/www\.[^\s]+/iu', '', (string)$value);
    $value = preg_replace('/[a-z0-9._%+\-]+@[a-z0-9.\-]+\.[a-z]{2,}/iu', '', (string)$value);
    $value = preg_replace('/[#*_~|>{}\[\]\\\\]+/u', ' ', (string)$value);
    $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', (string)$value);
    $value = preg_replace('/\s+/u', ' ', (string)$value);
    $value = trim((string)$value);

    if (function_exists('mb_substr')) {
        return mb_substr($value, 0, $limit, 'UTF-8');
    }

    return substr($value, 0, $limit);
}

function admin_review_auto_title(string $comment): string
{
    $comment = admin_review_clean_text($comment, 1200);
    $comment = preg_replace('/\s+/u', ' ', $comment);
    $comment = trim((string)$comment);

    if ($comment === '') {
        return 'Patient Experience';
    }

    $period_position = function_exists('mb_strpos')
        ? mb_strpos($comment, '.', 0, 'UTF-8')
        : strpos($comment, '.');

    if ($period_position !== false) {
        $title = function_exists('mb_substr')
            ? mb_substr($comment, 0, $period_position + 1, 'UTF-8')
            : substr($comment, 0, $period_position + 1);

        $title = trim((string)$title);

        if ($title !== '') {
            return $title;
        }
    }

    $limit = 42;
    $length = function_exists('mb_strlen') ? mb_strlen($comment, 'UTF-8') : strlen($comment);

    if ($length <= $limit) {
        return $comment;
    }

    $title = function_exists('mb_substr')
        ? mb_substr($comment, 0, $limit, 'UTF-8')
        : substr($comment, 0, $limit);

    $title = preg_replace('/[,.!?;:\-–—\s]+$/u', '', (string)$title);
    $title = trim((string)$title);

    return $title !== '' ? $title . '…' : 'Patient Experience';
}

function admin_review_initial(string $value): string
{
    $value = trim($value);

    if ($value === '') {
        return 'R';
    }

    if (function_exists('mb_substr')) {
        return strtoupper((string)mb_substr($value, 0, 1, 'UTF-8'));
    }

    return strtoupper((string)substr($value, 0, 1));
}

function admin_review_preview_text(string $value, int $limit = 140): string
{
    $value = trim(preg_replace('/\s+/u', ' ', $value));

    if ($value === '') {
        return 'Review comment will appear here after you add details.';
    }

    if (function_exists('mb_strimwidth')) {
        return mb_strimwidth($value, 0, $limit, '...', 'UTF-8');
    }

    return strlen($value) > $limit ? substr($value, 0, $limit) . '...' : $value;
}

function admin_review_star_text(int $rating): string
{
    $rating = max(1, min(5, $rating));

    return str_repeat('★', $rating) . str_repeat('☆', 5 - $rating);
}

function admin_refresh_doctor_review_stats(int $doctor_id): void
{
    global $pdo;

    if ($doctor_id <= 0 || !table_exists('doctors') || !table_exists('reviews')) {
        return;
    }

    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) AS total_reviews, COALESCE(AVG(rating), 0) AS avg_rating
            FROM reviews
            WHERE doctor_id = :doctor_id
            AND status = 'approved'
        ");
        $stmt->execute([':doctor_id' => $doctor_id]);
        $stats = $stmt->fetch() ?: ['total_reviews' => 0, 'avg_rating' => 0];

        if (column_exists('doctors', 'reviews_count') && column_exists('doctors', 'rating')) {
            $update = $pdo->prepare("
                UPDATE doctors
                SET reviews_count = :reviews_count, rating = :rating
                WHERE id = :doctor_id
            ");
            $update->execute([
                ':reviews_count' => (int)$stats['total_reviews'],
                ':rating' => round((float)$stats['avg_rating'], 1),
                ':doctor_id' => $doctor_id,
            ]);
        }
    } catch (Throwable $e) {
        error_log('Doctor review stats refresh failed: ' . $e->getMessage());
    }
}

function admin_refresh_hospital_review_stats(int $hospital_id): void
{
    global $pdo;

    if (
        $hospital_id <= 0 ||
        !table_exists('hospitals') ||
        !table_exists('reviews') ||
        !column_exists('reviews', 'hospital_id') ||
        !column_exists('hospitals', 'rating')
    ) {
        return;
    }

    try {
        $stmt = $pdo->prepare("
            SELECT COALESCE(AVG(rating), 0) AS avg_rating
            FROM reviews
            WHERE hospital_id = :hospital_id
            AND status = 'approved'
        ");
        $stmt->execute([':hospital_id' => $hospital_id]);
        $avg_rating = round((float)$stmt->fetchColumn(), 1);

        $update = $pdo->prepare("
            UPDATE hospitals
            SET rating = :rating
            WHERE id = :hospital_id
        ");
        $update->execute([
            ':rating' => $avg_rating,
            ':hospital_id' => $hospital_id,
        ]);
    } catch (Throwable $e) {
        error_log('Hospital review stats refresh failed: ' . $e->getMessage());
    }
}

$id = (int)($_GET['id'] ?? 0);
$edit = null;

if ($id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM reviews WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $id]);
    $edit = $stmt->fetch();

    if (!$edit) {
        flash('error', 'Review not found.');
        redirect('reviews.php');
    }
}

$doctors = function_exists('get_doctors') ? get_doctors([], 1000) : [];
$hospitals = ($has_hospital_id && function_exists('get_hospitals')) ? get_hospitals([], 1000) : [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $post_id = (int)($_POST['id'] ?? 0);
    $doctor_id = (int)($_POST['doctor_id'] ?? 0);
    $hospital_id = $has_hospital_id ? (int)($_POST['hospital_id'] ?? 0) : 0;

    if ($doctor_id <= 0 && (!$has_hospital_id || $hospital_id <= 0)) {
        flash('error', 'Please select at least one doctor or hospital.');
        redirect($post_id > 0 ? 'review-form.php?id=' . $post_id : 'review-form.php');
    }

    if (!$doctor_id_nullable && $doctor_id <= 0) {
        flash('error', 'This database requires a doctor for every review. Run admin_reviews_update.sql to allow hospital-only reviews.');
        redirect($post_id > 0 ? 'review-form.php?id=' . $post_id : 'review-form.php');
    }

    $old = null;

    if ($post_id > 0) {
        $old_columns = 'doctor_id' . ($has_hospital_id ? ', hospital_id' : '');
        $stmt = $pdo->prepare("SELECT {$old_columns} FROM reviews WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $post_id]);
        $old = $stmt->fetch();
    }

    $name = admin_review_clean_text((string)($_POST['name'] ?? ''), 150);
    $is_anonymous = $has_is_anonymous ? (isset($_POST['is_anonymous']) ? 1 : 0) : 0;

    if ($is_anonymous || $name === '') {
        $name = 'Anonymous';
        $is_anonymous = 1;
    }

    $comment = admin_review_clean_text((string)($_POST['comment'] ?? ''), 1200);
    $title = admin_review_clean_text((string)($_POST['title'] ?? ''), 180);

    if ($has_title && $title === '' && $comment !== '') {
        $title = admin_review_auto_title($comment);
    }

    if ($comment === '') {
        flash('error', 'Review comment is required.');
        redirect($post_id > 0 ? 'review-form.php?id=' . $post_id : 'review-form.php');
    }

    $rating = max(1, min(5, (int)($_POST['rating'] ?? 5)));
    $status = isset($_POST['approve_now'])
        ? 'approved'
        : (string)($_POST['status'] ?? 'approved');

    if (!in_array($status, ['approved', 'pending', 'rejected'], true)) {
        $status = 'pending';
    }

    $visit_date = trim((string)($_POST['visit_date'] ?? ''));

    if ($visit_date !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $visit_date)) {
        $visit_date = '';
    }

    if ($visit_date !== '') {
        $visit_timestamp = strtotime($visit_date);
        $today_timestamp = strtotime(date('Y-m-d'));

        if (!$visit_timestamp || $visit_timestamp > $today_timestamp) {
            flash('error', 'Visit date cannot be a future date.');
            redirect($post_id > 0 ? 'review-form.php?id=' . $post_id : 'review-form.php');
        }
    }

    $data = [
        ':doctor_id' => $doctor_id > 0 ? $doctor_id : null,
        ':name' => $name,
        ':rating' => $rating,
        ':comment' => $comment,
        ':status' => $status,
    ];

    $columns = ['doctor_id', 'name', 'rating', 'comment', 'status'];
    $values = [':doctor_id', ':name', ':rating', ':comment', ':status'];
    $sets = ['doctor_id = :doctor_id', 'name = :name', 'rating = :rating', 'comment = :comment', 'status = :status'];

    if ($has_hospital_id) {
        $data[':hospital_id'] = $hospital_id > 0 ? $hospital_id : null;
        $columns[] = 'hospital_id';
        $values[] = ':hospital_id';
        $sets[] = 'hospital_id = :hospital_id';
    }

    if ($has_title) {
        $data[':title'] = $title !== '' ? $title : null;
        $columns[] = 'title';
        $values[] = ':title';
        $sets[] = 'title = :title';
    }

    if ($has_treatment_type) {
        $data[':treatment_type'] = admin_review_clean_text((string)($_POST['treatment_type'] ?? ''), 180);
        $columns[] = 'treatment_type';
        $values[] = ':treatment_type';
        $sets[] = 'treatment_type = :treatment_type';
    }

    if ($has_visit_date) {
        $data[':visit_date'] = $visit_date !== '' ? $visit_date : null;
        $columns[] = 'visit_date';
        $values[] = ':visit_date';
        $sets[] = 'visit_date = :visit_date';
    }

    if ($has_is_anonymous) {
        $data[':is_anonymous'] = $is_anonymous;
        $columns[] = 'is_anonymous';
        $values[] = ':is_anonymous';
        $sets[] = 'is_anonymous = :is_anonymous';
    }

    if ($has_is_verified_patient) {
        $data[':is_verified_patient'] = isset($_POST['is_verified_patient']) ? 1 : 0;
        $columns[] = 'is_verified_patient';
        $values[] = ':is_verified_patient';
        $sets[] = 'is_verified_patient = :is_verified_patient';
    }

    if ($post_id > 0) {
        $data[':id'] = $post_id;
        $sql = "UPDATE reviews SET " . implode(', ', $sets) . " WHERE id = :id";
    } else {
        $columns[] = 'created_at';
        $values[] = 'NOW()';
        $sql = "INSERT INTO reviews (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $values) . ")";
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($data);

    admin_refresh_doctor_review_stats($doctor_id);

    if ($old) {
        admin_refresh_doctor_review_stats((int)($old['doctor_id'] ?? 0));
    }

    if ($has_hospital_id) {
        admin_refresh_hospital_review_stats($hospital_id);

        if ($old) {
            admin_refresh_hospital_review_stats((int)($old['hospital_id'] ?? 0));
        }
    }

    flash('success', $post_id > 0 ? 'Review updated successfully.' : 'Review added successfully.');
    redirect('reviews.php');
}

$form_title = $edit ? 'Edit Review' : 'Add New Review';
$form_subtitle = $edit
    ? 'Approve quickly, or edit the review details before saving.'
    : 'Create a new doctor or hospital review and control its approval status.';

$current_status = $edit['status'] ?? 'approved';
$current_rating = (int)($edit['rating'] ?? 5);
$preview_name = $edit['name'] ?? 'Reviewer Name';
$preview_comment = $edit['comment'] ?? 'Review comment will appear here after you add details.';
$current_is_anonymous = !empty($edit['is_anonymous']);
$current_is_verified_patient = !empty($edit['is_verified_patient']);
$current_doctor_name = '';
$current_hospital_name = '';

foreach ($doctors as $doctor_item) {
    if ((int)($doctor_item['id'] ?? 0) === (int)($edit['doctor_id'] ?? 0)) {
        $current_doctor_name = (string)($doctor_item['name'] ?? '');
        break;
    }
}

if ($has_hospital_id) {
    foreach ($hospitals as $hospital_item) {
        if ((int)($hospital_item['id'] ?? 0) === (int)($edit['hospital_id'] ?? 0)) {
            $current_hospital_name = (string)($hospital_item['name'] ?? '');
            break;
        }
    }
}

$edit_review_title = $has_title ? trim((string)($edit['title'] ?? '')) : '';
if ($edit_review_title === '') {
    $edit_review_title = admin_review_auto_title((string)($edit['comment'] ?? ''));
}

$edit_review_created = '';
if (!empty($edit['created_at'])) {
    $edit_review_created_time = strtotime((string)$edit['created_at']);
    if ($edit_review_created_time) {
        $edit_review_created = date('M d, Y', $edit_review_created_time);
    }
}
?>

<style>
  .gh-review-wrap {
    max-width: 1180px;
    margin: 0 auto;
  }

  .gh-review-top {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
    margin-bottom: 18px;
  }

  .gh-review-title h1 {
    margin: 0;
    color: #24292f;
    font-size: 30px;
    line-height: 1.15;
    letter-spacing: -0.04em;
    font-weight: 800;
  }

  .gh-review-title p {
    margin: 7px 0 0;
    color: #57606a;
    font-size: 14px;
    line-height: 1.6;
  }

  .gh-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 38px;
    padding: 0 14px;
    border-radius: 8px;
    border: 1px solid #d0d7de;
    background: #f6f8fa;
    color: #24292f;
    text-decoration: none;
    font-size: 14px;
    font-weight: 700;
    cursor: pointer;
    font-family: inherit;
  }

  .gh-btn:hover {
    background: #eef1f4;
    color: #24292f;
    text-decoration: none;
  }

  .gh-btn-primary {
    background: #2da44e;
    border-color: rgba(27,31,36,.15);
    color: #ffffff;
  }

  .gh-btn-primary:hover {
    background: #1f883d;
    color: #ffffff;
  }


  .gh-approval-panel {
    margin-bottom: 16px;
    border: 1px solid #d0d7de;
    border-radius: 14px;
    background: #ffffff;
    box-shadow: 0 1px 0 rgba(27,31,36,.04);
    overflow: hidden;
  }

  .gh-approval-head {
    display: flex;
    justify-content: space-between;
    gap: 14px;
    padding: 16px 18px;
    border-bottom: 1px solid #d8dee4;
    background: #f6f8fa;
  }

  .gh-approval-head h2 {
    margin: 0;
    color: #24292f;
    font-size: 19px;
    font-weight: 900;
    letter-spacing: -0.02em;
  }

  .gh-approval-head p {
    margin: 4px 0 0;
    color: #57606a;
    font-size: 13px;
    line-height: 1.55;
  }

  .gh-approval-status {
    display: inline-flex;
    align-items: center;
    min-height: 28px;
    padding: 4px 10px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 900;
    white-space: nowrap;
  }

  .gh-approval-status.approved {
    background: #dafbe1;
    border: 1px solid rgba(26,127,55,.22);
    color: #1a7f37;
  }

  .gh-approval-status.pending {
    background: #fff8c5;
    border: 1px solid #d4a72c;
    color: #7d4e00;
  }

  .gh-approval-status.rejected {
    background: #ffebe9;
    border: 1px solid rgba(207,34,46,.20);
    color: #cf222e;
  }

  .gh-approval-body {
    padding: 16px 18px 18px;
  }

  .gh-approval-review {
    padding: 14px;
    border: 1px solid #d8dee4;
    border-radius: 12px;
    background: #f6f8fa;
  }

  .gh-approval-stars {
    color: #bf8700;
    font-size: 14px;
    font-weight: 900;
    margin-bottom: 8px;
  }

  .gh-approval-meta {
    margin: 0 0 8px;
    color: #57606a;
    font-size: 13px;
    line-height: 1.6;
    font-weight: 700;
  }

  .gh-approval-meta strong {
    color: #24292f;
    font-weight: 900;
  }

  .gh-approval-review h3 {
    margin: 0 0 8px;
    color: #24292f;
    font-size: 17px;
    line-height: 1.4;
    font-weight: 900;
  }

  .gh-approval-review p {
    margin: 0;
    color: #57606a;
    font-size: 14px;
    line-height: 1.7;
  }

  .gh-approval-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 14px;
  }

  .gh-edit-note {
    margin: 0 0 16px;
    padding: 12px 14px;
    border: 1px solid #d8dee4;
    border-radius: 10px;
    background: #f6f8fa;
    color: #57606a;
    font-size: 13px;
    line-height: 1.6;
  }


  .gh-alert-note {
    margin-bottom: 16px;
    padding: 12px 14px;
    border-radius: 10px;
    background: #fff8c5;
    border: 1px solid #d4a72c;
    color: #7d4e00;
    font-size: 14px;
    line-height: 1.6;
  }

  .gh-review-shell {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 340px;
    gap: 18px;
    align-items: start;
  }

  .gh-card,
  .gh-side-card {
    background: #ffffff;
    border: 1px solid #d0d7de;
    border-radius: 14px;
    box-shadow: 0 1px 0 rgba(27,31,36,.04);
    overflow: hidden;
  }

  .gh-card-head {
    padding: 18px 20px;
    border-bottom: 1px solid #d8dee4;
    background: #f6f8fa;
  }

  .gh-card-head .gh-badge {
    display: inline-flex;
    align-items: center;
    min-height: 24px;
    padding: 3px 9px;
    border-radius: 999px;
    background: #ddf4ff;
    border: 1px solid rgba(9,105,218,.20);
    color: #0969da;
    font-size: 12px;
    font-weight: 800;
    margin-bottom: 10px;
  }

  .gh-card-head h2 {
    margin: 0;
    color: #24292f;
    font-size: 22px;
    line-height: 1.25;
    letter-spacing: -0.03em;
    font-weight: 800;
  }

  .gh-card-head p {
    margin: 7px 0 0;
    color: #57606a;
    font-size: 14px;
    line-height: 1.65;
  }

  .gh-form-body {
    padding: 20px;
  }

  .gh-section {
    margin-bottom: 22px;
  }

  .gh-section:last-child {
    margin-bottom: 0;
  }

  .gh-section-head {
    display: flex;
    gap: 10px;
    align-items: flex-start;
    margin-bottom: 14px;
  }

  .gh-section-number {
    width: 30px;
    height: 30px;
    border-radius: 8px;
    background: #ddf4ff;
    border: 1px solid rgba(9,105,218,.20);
    color: #0969da;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex: 0 0 30px;
    font-size: 12px;
    font-weight: 900;
  }

  .gh-section-head h3 {
    margin: 0;
    color: #24292f;
    font-size: 16px;
    line-height: 1.35;
    font-weight: 800;
  }

  .gh-section-head p {
    margin: 2px 0 0;
    color: #57606a;
    font-size: 13px;
    line-height: 1.5;
  }

  .gh-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 14px;
  }

  .gh-field {
    display: grid;
    gap: 7px;
  }

  .gh-field.full {
    grid-column: 1 / -1;
  }

  .gh-label-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
  }

  .gh-field label {
    color: #24292f;
    font-size: 13px;
    line-height: 1.4;
    font-weight: 700;
  }

  .gh-tag {
    display: inline-flex;
    align-items: center;
    min-height: 22px;
    padding: 2px 8px;
    border-radius: 999px;
    background: #f6f8fa;
    border: 1px solid #d0d7de;
    color: #57606a;
    font-size: 11px;
    font-weight: 800;
    white-space: nowrap;
  }

  .gh-tag.required {
    background: #ffebe9;
    border-color: rgba(207,34,46,.20);
    color: #cf222e;
  }

  .gh-tag.optional {
    background: #ddf4ff;
    border-color: rgba(9,105,218,.20);
    color: #0969da;
  }

  .gh-field small {
    color: #6e7781;
    font-size: 12px;
    line-height: 1.5;
    font-weight: 600;
  }

  .gh-input,
  .gh-select,
  .gh-textarea {
    width: 100%;
    min-height: 42px;
    border: 1px solid #d0d7de;
    border-radius: 8px;
    background: #ffffff;
    color: #24292f;
    padding: 10px 12px;
    font-family: inherit;
    font-size: 14px;
    outline: none;
    box-shadow: inset 0 1px 0 rgba(208,215,222,.20);
  }

  .gh-textarea {
    min-height: 150px;
    resize: vertical;
    line-height: 1.7;
  }

  .gh-input:focus,
  .gh-select:focus,
  .gh-textarea:focus {
    border-color: #0969da;
    box-shadow: 0 0 0 3px rgba(9,105,218,.14);
  }

  .gh-check-row {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
  }

  .gh-check {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    min-height: 38px;
    padding: 0 12px;
    border: 1px solid #d0d7de;
    border-radius: 8px;
    background: #f6f8fa;
    color: #24292f;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
  }

  .gh-check input {
    width: 16px;
    height: 16px;
    accent-color: #2da44e;
  }


  .gh-readonly-box {
    min-height: 42px;
    display: flex;
    align-items: center;
    padding: 10px 12px;
    border: 1px solid #d0d7de;
    border-radius: 8px;
    background: #f6f8fa;
    color: #24292f;
    font-size: 14px;
    font-weight: 800;
  }

  .gh-readonly-box span {
    color: #6e7781;
    font-weight: 700;
  }

  .gh-btn-approve {
    background: #1a7f37;
    border-color: rgba(27,31,36,.15);
    color: #ffffff;
  }

  .gh-btn-approve:hover {
    background: #116329;
    color: #ffffff;
  }


  .gh-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 20px;
    padding-top: 18px;
    border-top: 1px solid #d8dee4;
  }

  .gh-side-card {
    padding: 18px;
  }

  .gh-preview-label {
    display: inline-flex;
    align-items: center;
    min-height: 26px;
    padding: 4px 10px;
    border-radius: 999px;
    background: #dafbe1;
    border: 1px solid rgba(26,127,55,.22);
    color: #1a7f37;
    font-size: 12px;
    font-weight: 900;
    margin-bottom: 14px;
  }

  .gh-preview-box {
    padding: 15px;
    border: 1px solid #d8dee4;
    border-radius: 12px;
    background: #f6f8fa;
  }

  .gh-preview-avatar {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    background: #0969da;
    color: #ffffff;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    font-weight: 900;
    margin-bottom: 10px;
  }

  .gh-preview-box h3 {
    margin: 0 0 5px;
    color: #24292f;
    font-size: 17px;
    line-height: 1.35;
    font-weight: 900;
  }

  .gh-preview-stars {
    color: #bf8700;
    font-size: 14px;
    font-weight: 900;
    margin-bottom: 8px;
  }

  .gh-preview-box p {
    margin: 0;
    color: #57606a;
    font-size: 13px;
    line-height: 1.65;
  }

  .gh-status-pill {
    display: inline-flex;
    align-items: center;
    min-height: 26px;
    margin-top: 12px;
    padding: 4px 9px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 900;
  }

  .gh-status-pill.approved {
    background: #dafbe1;
    color: #1a7f37;
    border: 1px solid rgba(26,127,55,.22);
  }

  .gh-status-pill.pending {
    background: #fff8c5;
    color: #7d4e00;
    border: 1px solid #d4a72c;
  }

  .gh-status-pill.rejected {
    background: #ffebe9;
    color: #cf222e;
    border: 1px solid rgba(207,34,46,.20);
  }

  .gh-help {
    margin: 14px 0 0;
    padding: 0;
    list-style: none;
  }

  .gh-help li {
    padding: 10px 0;
    border-bottom: 1px solid #d8dee4;
    color: #57606a;
    font-size: 13px;
    line-height: 1.55;
  }

  .gh-help li:last-child {
    border-bottom: 0;
  }

  .gh-help strong {
    color: #0969da;
  }

  @media (max-width: 1040px) {
    .gh-review-shell {
      grid-template-columns: 1fr;
    }
  }

  @media (max-width: 720px) {
    .gh-review-top {
      flex-direction: column;
      align-items: stretch;
    }

    .gh-grid {
      grid-template-columns: 1fr;
    }

    .gh-form-body,
    .gh-card-head {
      padding: 16px;
    }

    .gh-review-title h1 {
      font-size: 26px;
    }

    .gh-actions .gh-btn {
      width: 100%;
    }
  }
</style>

<div class="gh-review-wrap">
  <div class="gh-review-top">
    <div class="gh-review-title">
      <h1><?= e($form_title) ?></h1>
      <p><?= e($form_subtitle) ?></p>
    </div>

    <a href="reviews.php" class="gh-btn">← Back to Reviews</a>
  </div>

  <?php show_flash(); ?>

  <?php if (!$has_hospital_id): ?>
    <div class="gh-alert-note">
      <strong>Hospital review support is not active yet.</strong>
      Run <code>admin_reviews_update.sql</code> if you want to attach reviews directly to hospitals too.
    </div>
  <?php endif; ?>

  <?php if ($edit): ?>
    <section class="gh-approval-panel">
      <div class="gh-approval-head">
        <div>
          <h2>Review Approval</h2>
          <p>Check the review quickly. Approve directly, or edit the details below.</p>
        </div>

        <span class="gh-approval-status <?= e($current_status) ?>">
          <?= e(ucfirst($current_status)) ?>
        </span>
      </div>

      <div class="gh-approval-body">
        <article class="gh-approval-review">
          <div class="gh-approval-stars">
            <?= e(admin_review_star_text($current_rating)) ?>
          </div>

          <p class="gh-approval-meta">
            <strong><?= e($preview_name ?: 'Anonymous') ?></strong>
            <?php if ($edit_review_created !== ''): ?>
              · <?= e($edit_review_created) ?>
            <?php endif; ?>
            <?php if ($current_doctor_name !== ''): ?>
              · Doctor: <?= e($current_doctor_name) ?>
            <?php endif; ?>
          </p>

          <h3><?= e($edit_review_title) ?></h3>
          <p><?= nl2br(e($edit['comment'] ?? '')) ?></p>
        </article>

        <div class="gh-approval-actions">
          <?php if ($current_status !== 'approved'): ?>
            <form method="POST" style="display:inline;">
              <input type="hidden" name="id" value="<?= e((string)($edit['id'] ?? '')) ?>">
              <input type="hidden" name="doctor_id" value="<?= e((string)($edit['doctor_id'] ?? '')) ?>">
              <?php if ($has_hospital_id): ?>
                <input type="hidden" name="hospital_id" value="<?= e((string)($edit['hospital_id'] ?? '')) ?>">
              <?php endif; ?>
              <input type="hidden" name="name" value="<?= e((string)($edit['name'] ?? '')) ?>">
              <input type="hidden" name="rating" value="<?= e((string)$current_rating) ?>">
              <?php if ($has_title): ?>
                <input type="hidden" name="title" value="<?= e((string)($edit['title'] ?? '')) ?>">
              <?php endif; ?>
              <input type="hidden" name="comment" value="<?= e((string)($edit['comment'] ?? '')) ?>">
              <?php if ($has_treatment_type): ?>
                <input type="hidden" name="treatment_type" value="<?= e((string)($edit['treatment_type'] ?? '')) ?>">
              <?php endif; ?>
              <?php if ($has_visit_date): ?>
                <input type="hidden" name="visit_date" value="<?= e((string)($edit['visit_date'] ?? '')) ?>">
              <?php endif; ?>
              <?php if ($has_is_anonymous && !empty($edit['is_anonymous'])): ?>
                <input type="hidden" name="is_anonymous" value="1">
              <?php endif; ?>
              <?php if ($has_is_verified_patient && !empty($edit['is_verified_patient'])): ?>
                <input type="hidden" name="is_verified_patient" value="1">
              <?php endif; ?>

              <button class="gh-btn gh-btn-approve" type="submit" name="approve_now" value="1">Approve Review</button>
            </form>
          <?php endif; ?>

          <a href="#edit-review-details" class="gh-btn">Edit Details</a>
          <a href="reviews.php" class="gh-btn">Back to Reviews</a>
        </div>
      </div>
    </section>
  <?php endif; ?>

  <div class="gh-review-shell">
    <div class="gh-card" id="edit-review-details">
      <div class="gh-card-head">
        <span class="gh-badge"><?= $edit ? 'Edit Mode' : 'Create Mode' ?></span>
        <h2><?= $edit ? 'Edit Review Details' : 'Create New Review' ?></h2>
        <p><?= $edit ? 'Only change what is needed, then update the review.' : 'Add a new review and set its approval status.' ?></p>
      </div>

      <div class="gh-form-body">
        <?php if ($edit): ?>
          <p class="gh-edit-note">
            This section is for corrections only. Doctor is locked, but reviewer info, rating, comment, optional fields, and status can still be updated.
          </p>
        <?php endif; ?>

        <form method="POST">
          <input type="hidden" name="id" value="<?= e((string)($edit['id'] ?? '')) ?>">

          <div class="gh-section">
            <div class="gh-section-head">
              <span class="gh-section-number">01</span>
              <div>
                <h3>Review Target</h3>
                <p>Select where this review belongs.</p>
              </div>
            </div>

            <div class="gh-grid">
              <div class="gh-field <?= $has_hospital_id ? '' : 'full' ?>">
                <div class="gh-label-row">
                  <label for="doctor_id">Doctor</label>
                  <?php if ($edit): ?>
                    <span class="gh-tag required">Locked</span>
                  <?php elseif (!$has_hospital_id || !$doctor_id_nullable): ?>
                    <span class="gh-tag required">Required</span>
                  <?php else: ?>
                    <span class="gh-tag optional">Optional</span>
                  <?php endif; ?>
                </div>

                <?php if ($edit): ?>
                  <input type="hidden" name="doctor_id" value="<?= e((string)($edit['doctor_id'] ?? '')) ?>">

                  <div class="gh-readonly-box">
                    <?= e($current_doctor_name !== '' ? $current_doctor_name : 'Doctor not selected') ?>
                  </div>

                  <small>Doctor cannot be changed after the review is created.</small>
                <?php else: ?>
                  <select id="doctor_id" class="gh-select" name="doctor_id" <?= (!$has_hospital_id || !$doctor_id_nullable) ? 'required' : '' ?>>
                    <option value="">Select Doctor</option>

                    <?php foreach ($doctors as $doctor): ?>
                      <option value="<?= e((string)$doctor['id']) ?>" <?= (($edit['doctor_id'] ?? '') == $doctor['id']) ? 'selected' : '' ?>>
                        <?= e($doctor['name']) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>

                  <small>Select a doctor if this is a doctor review.</small>
                <?php endif; ?>
              </div>

              <?php if ($has_hospital_id): ?>
                <div class="gh-field">
                  <div class="gh-label-row">
                    <label for="hospital_id">Hospital</label>
                    <span class="gh-tag optional">Optional</span>
                  </div>

                  <select id="hospital_id" class="gh-select" name="hospital_id">
                    <option value="">Select Hospital</option>

                    <?php foreach ($hospitals as $hospital): ?>
                      <option value="<?= e((string)$hospital['id']) ?>" <?= (($edit['hospital_id'] ?? '') == $hospital['id']) ? 'selected' : '' ?>>
                        <?= e($hospital['name']) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>

                  <small>Select a hospital if this is a hospital review.</small>
                </div>
              <?php endif; ?>
            </div>
          </div>

          <div class="gh-section">
            <div class="gh-section-head">
              <span class="gh-section-number">02</span>
              <div>
                <h3>Reviewer Information</h3>
                <p>Reviewer name, rating, and patient flags.</p>
              </div>
            </div>

            <div class="gh-grid">
              <div class="gh-field">
                <div class="gh-label-row">
                  <label for="name">Reviewer Name</label>
                  <span class="gh-tag optional">Optional</span>
                </div>

                <input
                  id="name"
                  class="gh-input"
                  type="text"
                  name="name"
                  placeholder="Leave empty for Anonymous"
                  value="<?= e($edit['name'] ?? '') ?>"
                >

                <small>Empty name will be saved as Anonymous.</small>
              </div>

              <div class="gh-field">
                <div class="gh-label-row">
                  <label for="rating">Rating</label>
                  <span class="gh-tag required">Required</span>
                </div>

                <select id="rating" class="gh-select" name="rating" required>
                  <?php for ($i = 5; $i >= 1; $i--): ?>
                    <option value="<?= $i ?>" <?= ($current_rating === $i) ? 'selected' : '' ?>>
                      <?= $i ?> Star<?= $i > 1 ? 's' : '' ?>
                    </option>
                  <?php endfor; ?>
                </select>

                <small>Approved ratings update doctor/hospital average rating.</small>
              </div>

              <?php if ($has_is_anonymous || $has_is_verified_patient): ?>
                <div class="gh-field full">
                  <div class="gh-label-row">
                    <label>Review Options</label>
                    <span class="gh-tag optional">Optional</span>
                  </div>

                  <div class="gh-check-row">
                    <?php if ($has_is_anonymous): ?>
                      <label class="gh-check">
                        <input type="checkbox" name="is_anonymous" value="1" <?= $current_is_anonymous ? 'checked' : '' ?>>
                        Anonymous
                      </label>
                    <?php endif; ?>

                    <?php if ($has_is_verified_patient): ?>
                      <label class="gh-check">
                        <input type="checkbox" name="is_verified_patient" value="1" <?= $current_is_verified_patient ? 'checked' : '' ?>>
                        Verified Patient
                      </label>
                    <?php endif; ?>
                  </div>

                  <small>Use these options when the review came from a verified appointment or anonymous patient.</small>
                </div>
              <?php endif; ?>
            </div>
          </div>

          <div class="gh-section">
            <div class="gh-section-head">
              <span class="gh-section-number">03</span>
              <div>
                <h3>Review Content</h3>
                <p>Review title, comment, treatment, and visit date.</p>
              </div>
            </div>

            <div class="gh-grid">
              <?php if ($has_title): ?>
                <div class="gh-field full">
                  <div class="gh-label-row">
                    <label for="title">Review Title</label>
                    <span class="gh-tag optional">Optional</span>
                  </div>

                  <input
                    id="title"
                    class="gh-input"
                    type="text"
                    name="title"
                    placeholder="Leave empty to auto-generate from comment"
                    value="<?= e($edit['title'] ?? '') ?>"
                  >

                  <small>If empty, a short title will be created from the review comment.</small>
                </div>
              <?php endif; ?>

              <div class="gh-field full">
                <div class="gh-label-row">
                  <label for="comment">Comment</label>
                  <span class="gh-tag required">Required</span>
                </div>

                <textarea
                  id="comment"
                  class="gh-textarea"
                  name="comment"
                  placeholder="Write review comment"
                  required
                ><?= e($edit['comment'] ?? '') ?></textarea>

                <small>Keep the review natural and useful for patients.</small>
              </div>

              <?php if ($has_treatment_type): ?>
                <div class="gh-field">
                  <div class="gh-label-row">
                    <label for="treatment_type">Treatment / Service Type</label>
                    <span class="gh-tag optional">Optional</span>
                  </div>

                  <input
                    id="treatment_type"
                    class="gh-input"
                    type="text"
                    name="treatment_type"
                    placeholder="Consultation, surgery, follow-up"
                    value="<?= e($edit['treatment_type'] ?? '') ?>"
                  >
                </div>
              <?php endif; ?>

              <?php if ($has_visit_date): ?>
                <div class="gh-field">
                  <div class="gh-label-row">
                    <label for="visit_date">Visit Date</label>
                    <span class="gh-tag optional">Optional</span>
                  </div>

                  <input
                    id="visit_date"
                    class="gh-input"
                    type="date"
                    name="visit_date"
                    max="<?= e(date('Y-m-d')) ?>"
                    value="<?= e($edit['visit_date'] ?? '') ?>"
                  >

                  <small>Future date is not allowed.</small>
                </div>
              <?php endif; ?>
            </div>
          </div>

          <div class="gh-section">
            <div class="gh-section-head">
              <span class="gh-section-number">04</span>
              <div>
                <h3>Moderation Status</h3>
                <p>Control whether this review appears publicly.</p>
              </div>
            </div>

            <div class="gh-grid">
              <div class="gh-field">
                <div class="gh-label-row">
                  <label for="status">Status</label>
                  <span class="gh-tag optional">Optional</span>
                </div>

                <select id="status" class="gh-select" name="status">
                  <option value="approved" <?= ($current_status === 'approved') ? 'selected' : '' ?>>Approved</option>
                  <option value="pending" <?= ($current_status === 'pending') ? 'selected' : '' ?>>Pending</option>
                  <option value="rejected" <?= ($current_status === 'rejected') ? 'selected' : '' ?>>Rejected</option>
                </select>

                <small>Only approved reviews are counted publicly.</small>
              </div>
            </div>
          </div>

          <div class="gh-actions">
            <?php if ($edit && $current_status !== 'approved'): ?>
              <button class="gh-btn gh-btn-approve" type="submit" name="approve_now" value="1">
                Approve Review
              </button>
            <?php endif; ?>

            <button class="gh-btn gh-btn-primary" type="submit">
              <?= $edit ? 'Update Review' : 'Save Review' ?>
            </button>

            <a href="reviews.php" class="gh-btn">Cancel</a>
          </div>
        </form>
      </div>
    </div>

    <aside class="gh-side-card">
      <span class="gh-preview-label">Live Preview</span>

      <div class="gh-preview-box">
        <div class="gh-preview-avatar">
          <?= e(admin_review_initial((string)$preview_name)) ?>
        </div>

        <h3><?= e($preview_name ?: 'Anonymous') ?></h3>

        <div class="gh-preview-stars">
          <?= e(admin_review_star_text($current_rating)) ?>
        </div>

        <p><?= e(admin_review_preview_text((string)$preview_comment, 140)) ?></p>

        <span class="gh-status-pill <?= e($current_status) ?>">
          <?= e(ucfirst($current_status)) ?>
        </span>
      </div>

      <ul class="gh-help">
        <li><strong>01</strong> Select a doctor or hospital before saving the review.</li>
        <li><strong>02</strong> Approved reviews affect rating calculation.</li>
        <li><strong>03</strong> Optional fields are saved only if your database has those columns.</li>
        <li><strong>04</strong> Pending reviews stay saved but should not show publicly.</li>
      </ul>
    </aside>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
