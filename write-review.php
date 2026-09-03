<?php
require_once __DIR__ . '/includes/functions.php';

$slug = trim((string)($_GET['slug'] ?? ''));

if ($slug === '') {
    http_response_code(404);

    $page_title = 'Doctor Not Found';
    $meta_description = 'The doctor profile you are looking for could not be found.';

    include __DIR__ . '/includes/header.php';
    echo '<main class="page"><div class="container"><div class="card"><h2>Doctor not found</h2><p>Please go back to doctor list.</p><a href="' . e(site_url('doctors')) . '" class="btn btn-primary">Doctors</a></div></div></main>';
    include __DIR__ . '/includes/footer.php';
    exit;
}

$doctor = get_doctor_by_slug($slug);

if (!$doctor) {
    http_response_code(404);

    $page_title = 'Doctor Not Found';
    $meta_description = 'The doctor profile you are looking for could not be found.';

    include __DIR__ . '/includes/header.php';
    echo '<main class="page"><div class="container"><div class="card"><h2>Doctor not found</h2><p>Please go back to doctor list.</p><a href="' . e(site_url('doctors')) . '" class="btn btn-primary">Doctors</a></div></div></main>';
    include __DIR__ . '/includes/footer.php';
    exit;
}

function wr_table_exists(string $table): bool
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

function wr_column_exists(string $table, string $column): bool
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

function wr_add_review_column_if_missing(string $column, string $definition): void
{
    global $pdo;

    try {
        if (wr_table_exists('reviews') && !wr_column_exists('reviews', $column)) {
            $pdo->exec("ALTER TABLE reviews ADD COLUMN {$column} {$definition}");
        }
    } catch (Throwable $e) {
        error_log('Review column add failed: ' . $column . ' - ' . $e->getMessage());
    }
}

function wr_ensure_reviews_table(): bool
{
    global $pdo;

    try {
        if (!wr_table_exists('reviews')) {
            $pdo->exec(<<<SQL
                CREATE TABLE reviews (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    doctor_id INT NOT NULL DEFAULT 0,
                    name VARCHAR(150) NOT NULL DEFAULT '',
                    rating INT DEFAULT 5,
                    title VARCHAR(180) NULL,
                    comment TEXT NULL,
                    treatment_type VARCHAR(180) NULL,
                    visit_date DATE NULL,
                    is_anonymous TINYINT(1) DEFAULT 0,
                    is_verified_patient TINYINT(1) DEFAULT 0,
                    status VARCHAR(30) DEFAULT 'pending',
                    created_at DATETIME NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            SQL);
        }

        wr_add_review_column_if_missing('doctor_id', 'INT NOT NULL DEFAULT 0');
        wr_add_review_column_if_missing('name', "VARCHAR(150) NOT NULL DEFAULT ''");
        wr_add_review_column_if_missing('rating', 'INT DEFAULT 5');
        wr_add_review_column_if_missing('title', 'VARCHAR(180) NULL');
        wr_add_review_column_if_missing('comment', 'TEXT NULL');
        wr_add_review_column_if_missing('treatment_type', 'VARCHAR(180) NULL');
        wr_add_review_column_if_missing('visit_date', 'DATE NULL');
        wr_add_review_column_if_missing('is_anonymous', 'TINYINT(1) DEFAULT 0');
        wr_add_review_column_if_missing('is_verified_patient', 'TINYINT(1) DEFAULT 0');
        wr_add_review_column_if_missing('status', "VARCHAR(30) DEFAULT 'pending'");
        wr_add_review_column_if_missing('created_at', 'DATETIME NULL');

        return true;
    } catch (Throwable $e) {
        error_log('Review table create/update failed: ' . $e->getMessage());
        return false;
    }
}

function wr_clean_text(string $value, int $max_length = 500): string
{
    $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $value = strip_tags($value);

    // Remove URLs.
    $value = preg_replace('/https?:\/\/[^\s]+/iu', '', (string)$value);
    $value = preg_replace('/www\.[^\s]+/iu', '', (string)$value);

    // Remove email addresses.
    $value = preg_replace('/[a-z0-9._%+\-]+@[a-z0-9.\-]+\.[a-z]{2,}/iu', '', (string)$value);

    // Remove encoded or broken HTML/script-like text.
    $value = preg_replace('/<[^>]*>?/u', '', (string)$value);
    $value = preg_replace('/&lt;.*?&gt;/iu', '', (string)$value);

    // Remove markdown/code/image syntax and brackets often used for links.
    $value = str_replace(['```', '`'], '', (string)$value);
    $value = preg_replace('/!\[[^\]]*\]\([^)]+\)/u', '', (string)$value);
    $value = preg_replace('/\[[^\]]*\]\([^)]+\)/u', '', (string)$value);
    $value = preg_replace('/[#*_~|>{}\[\]\\\\]+/u', ' ', (string)$value);

    // Remove control characters.
    $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', (string)$value);

    // Keep clean plain text spacing.
    $value = preg_replace('/\s+/u', ' ', (string)$value);
    $value = trim((string)$value);

    if (function_exists('mb_substr')) {
        return mb_substr($value, 0, $max_length, 'UTF-8');
    }

    return substr($value, 0, $max_length);
}

function wr_is_plain_text_only(string $value): bool
{
    $raw = trim((string)$value);

    if ($raw === '') {
        return true;
    }

    $decoded = html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    $blocked_patterns = [
        '/<[^>]+>/u',
        '/&lt;[^&]*&gt;/iu',
        '/https?:\/\/[^\s]+/iu',
        '/www\.[^\s]+/iu',
        '/[a-z0-9._%+\-]+@[a-z0-9.\-]+\.[a-z]{2,}/iu',
        '/!\[[^\]]*\]\([^)]+\)/u',
        '/\[[^\]]*\]\([^)]+\)/u',
        '/```/u',
        '/<\?php/iu',
        '/javascript:/iu',
        '/data:/iu',
        '/onerror\s*=/iu',
        '/onclick\s*=/iu',
        '/<script/iu',
    ];

    foreach ($blocked_patterns as $pattern) {
        if (preg_match($pattern, $decoded)) {
            return false;
        }
    }

    return true;
}

function wr_review_redirect(string $slug, string $status): void
{
    redirect(site_url('doctor/' . $slug . '/write-review/?review=' . urlencode($status)));
}

function wr_get_submitted_review(int $review_id, int $doctor_id): array
{
    global $pdo;

    if ($review_id <= 0 || $doctor_id <= 0 || !wr_table_exists('reviews')) {
        return [];
    }

    try {
        $stmt = $pdo->prepare("
            SELECT *
            FROM reviews
            WHERE id = :id
            AND doctor_id = :doctor_id
            LIMIT 1
        ");
        $stmt->execute([
            ':id' => $review_id,
            ':doctor_id' => $doctor_id,
        ]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        error_log('Submitted review fetch failed: ' . $e->getMessage());
        return [];
    }
}

function wr_star_text(int $rating): string
{
    $rating = max(1, min(5, $rating));

    return str_repeat('★', $rating) . str_repeat('☆', 5 - $rating);
}

$form_status = trim((string)($_GET['review'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $doctor_id = (int)($doctor['id'] ?? 0);
    $is_anonymous = isset($_POST['is_anonymous']) ? 1 : 0;
    $name = wr_clean_text((string)($_POST['review_name'] ?? ''), 150);

    if ($is_anonymous || $name === '') {
        $is_anonymous = 1;
        $name = 'Anonymous';
    }
    $raw_rating = isset($_POST['review_rating']) ? (int)$_POST['review_rating'] : 0;
    $rating = max(1, min(5, $raw_rating));
    $title = wr_clean_text((string)($_POST['review_title'] ?? ''), 180);
    $comment = wr_clean_text((string)($_POST['review_comment'] ?? ''), 1200);

    if ($title === '' && $comment !== '') {
        $title_source = preg_replace('/\s+/u', ' ', $comment);
        $title_source = trim((string)$title_source);
        $title_limit = 42;
        $title_min_length = 15;

        $period_position = function_exists('mb_strpos')
            ? mb_strpos($title_source, '.', 0, 'UTF-8')
            : strpos($title_source, '.');

        if ($period_position !== false) {
            $title = function_exists('mb_substr')
                ? trim(mb_substr($title_source, 0, $period_position + 1, 'UTF-8'))
                : trim(substr($title_source, 0, $period_position + 1));
        } elseif (function_exists('mb_strlen') && function_exists('mb_substr')) {
            if (mb_strlen($title_source, 'UTF-8') > $title_limit) {
                $title = trim(mb_substr($title_source, 0, $title_limit, 'UTF-8'));
                $title = preg_replace('/[,.!?;:\-–—\s]+$/u', '', (string)$title);
                $title .= '…';
            } else {
                $title = $title_source;
            }
        } else {
            if (strlen($title_source) > $title_limit) {
                $title = trim(substr($title_source, 0, $title_limit));
                $title = preg_replace('/[,.!?;:\-–—\s]+$/', '', (string)$title);
                $title .= '…';
            } else {
                $title = $title_source;
            }
        }

        $title_length = function_exists('mb_strlen') ? mb_strlen($title, 'UTF-8') : strlen($title);

        if ($title === '' || $title_length < $title_min_length) {
            wr_review_redirect($slug, 'short_title');
        }
    }

    $final_title_length = function_exists('mb_strlen') ? mb_strlen($title, 'UTF-8') : strlen($title);

    if ($final_title_length < 15) {
        wr_review_redirect($slug, 'short_title');
    }
    $treatment_type = wr_clean_text((string)($_POST['treatment_type'] ?? ''), 180);
    $visit_date = trim((string)($_POST['visit_date'] ?? ''));
    $honey = trim((string)($_POST['review_website'] ?? ''));
    $math_a = (int)($_POST['math_a'] ?? 0);
    $math_b = (int)($_POST['math_b'] ?? 0);
    $math_op = trim((string)($_POST['math_op'] ?? ''));
    $math_answer = trim((string)($_POST['math_answer'] ?? ''));

    $plain_text_fields = [
        (string)($_POST['review_name'] ?? ''),
        (string)($_POST['review_title'] ?? ''),
        (string)($_POST['review_comment'] ?? ''),
        (string)($_POST['treatment_type'] ?? ''),
    ];

    foreach ($plain_text_fields as $plain_text_field) {
        if (!wr_is_plain_text_only($plain_text_field)) {
            wr_review_redirect($slug, 'plain');
        }
    }

    if ($honey !== '') {
        wr_review_redirect($slug, 'submitted');
    }

    if ($math_op === '+') {
        $correct_math_answer = $math_a + $math_b;
    } elseif ($math_op === '-') {
        $correct_math_answer = $math_a - $math_b;
    } else {
        $correct_math_answer = null;
    }

    if ($correct_math_answer === null || $math_answer === '' || (int)$math_answer !== (int)$correct_math_answer) {
        wr_review_redirect($slug, 'math');
    }

    if ($doctor_id <= 0 || $raw_rating < 1 || $raw_rating > 5 || $comment === '') {
        wr_review_redirect($slug, 'missing');
    }

    if ($visit_date !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $visit_date)) {
        $visit_date = '';
    }

    if ($visit_date !== '') {
        $visit_timestamp = strtotime($visit_date);
        $today_timestamp = strtotime(date('Y-m-d'));

        if (!$visit_timestamp || $visit_timestamp > $today_timestamp) {
            wr_review_redirect($slug, 'future_date');
        }
    }

    if (!wr_ensure_reviews_table()) {
        wr_review_redirect($slug, 'failed');
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO reviews
            (doctor_id, name, rating, title, comment, treatment_type, visit_date, is_anonymous, is_verified_patient, status, created_at)
            VALUES
            (:doctor_id, :name, :rating, :title, :comment, :treatment_type, :visit_date, :is_anonymous, 0, 'pending', NOW())
        ");

        $stmt->execute([
            ':doctor_id' => $doctor_id,
            ':name' => $name,
            ':rating' => $rating,
            ':title' => $title,
            ':comment' => $comment,
            ':treatment_type' => $treatment_type,
            ':visit_date' => $visit_date !== '' ? $visit_date : null,
            ':is_anonymous' => $is_anonymous,
        ]);

        $review_id = (int)$pdo->lastInsertId();
        redirect(site_url('doctor/' . $slug . '/write-review/?review=submitted&rid=' . $review_id));
    } catch (Throwable $e) {
        error_log('Doctor write review failed: ' . $e->getMessage());
        wr_review_redirect($slug, 'failed');
    }
}

$page_title = 'Write a Review for ' . ($doctor['name'] ?? 'Doctor');
$meta_description = 'Share your experience and write a patient review for ' . ($doctor['name'] ?? 'this doctor') . '.';

$doctor_name = trim((string)($doctor['name'] ?? 'Doctor'));
$doctor_subtitle = trim((string)(
    ($doctor['designation'] ?? '') .
    (!empty($doctor['degree']) ? ' | ' . $doctor['degree'] : '')
));
$doctor_image = !empty($doctor['image'])
    ? site_url(ltrim((string)$doctor['image'], '/'))
    : site_url('assets/images/default-doctor.png');

$math_questions = [
    ['a' => 5, 'op' => '-', 'b' => 1],
    ['a' => 8, 'op' => '+', 'b' => 9],
    ['a' => 7, 'op' => '-', 'b' => 6],
    ['a' => 6, 'op' => '+', 'b' => 4],
    ['a' => 9, 'op' => '-', 'b' => 3],
    ['a' => 3, 'op' => '+', 'b' => 5],
    ['a' => 10, 'op' => '-', 'b' => 4],
    ['a' => 12, 'op' => '+', 'b' => 6],
    ['a' => 14, 'op' => '-', 'b' => 8],
    ['a' => 2, 'op' => '+', 'b' => 7],
    ['a' => 15, 'op' => '-', 'b' => 9],
    ['a' => 4, 'op' => '+', 'b' => 11],
    ['a' => 18, 'op' => '-', 'b' => 7],
    ['a' => 13, 'op' => '+', 'b' => 4],
    ['a' => 20, 'op' => '-', 'b' => 6],
];

$math_question = $math_questions[array_rand($math_questions)];
$math_answer_value = $math_question['op'] === '+'
    ? ((int)$math_question['a'] + (int)$math_question['b'])
    : ((int)$math_question['a'] - (int)$math_question['b']);

$submitted_review_id = (int)($_GET['rid'] ?? 0);
$submitted_review = ($form_status === 'submitted' && $submitted_review_id > 0)
    ? wr_get_submitted_review($submitted_review_id, (int)($doctor['id'] ?? 0))
    : [];

include __DIR__ . '/includes/header.php';

if ($form_status === 'submitted') {
    $success_title = trim((string)($submitted_review['title'] ?? 'Patient Experience'));
    $success_comment = trim((string)($submitted_review['comment'] ?? ''));
    $success_rating = max(1, min(5, (int)($submitted_review['rating'] ?? 5)));
    $success_rating_number = number_format((float)$success_rating, 1);
    $success_name = !empty($submitted_review['is_anonymous'])
        ? 'Anonymous'
        : trim((string)($submitted_review['name'] ?? 'Patient'));

    if ($success_name === '') {
        $success_name = 'Patient';
    }

    $success_created_at = '';
    if (!empty($submitted_review['created_at'])) {
        $success_created_at_time = strtotime((string)$submitted_review['created_at']);

        if ($success_created_at_time) {
            $success_created_at = date('M d, Y', $success_created_at_time);
        }
    }

    $success_treatment = trim((string)($submitted_review['treatment_type'] ?? ''));
    $success_visit_date = '';

    if (!empty($submitted_review['visit_date'])) {
        $success_visit_time = strtotime((string)$submitted_review['visit_date']);

        if ($success_visit_time) {
            $success_visit_date = date('M d, Y', $success_visit_time);
        }
    }
    ?>
    <style>
      .wr-success-page {
        background: #ffffff;
        min-height: 70vh;
        padding: 48px 0 70px;
        color: #24292f;
      }

      .wr-success-shell {
        max-width: 620px;
        margin: 0 auto;
        padding: 0 16px;
      }


      .wr-success-doctor-box {
        display: grid;
        grid-template-columns: 56px minmax(0, 1fr);
        gap: 16px;
        align-items: center;
        margin: 0 auto 72px;
        padding: 16px 18px;
        border-radius: 16px;
        background: #f7f6f3;
        max-width: 480px;
      }

      .wr-success-doctor-box img {
        width: 56px;
        height: 56px;
        border-radius: 7px;
        object-fit: cover;
        border: 1px solid #e4e1da;
        background: #ffffff;
      }

      .wr-success-doctor-box strong {
        display: block;
        color: #191919;
        font-size: 18px;
        line-height: 1.25;
        font-weight: 700;
      }

      .wr-success-doctor-box span {
        display: block;
        margin-top: 3px;
        color: #191919;
        font-size: 17px;
        line-height: 1.3;
        font-weight: 400;
      }


      .wr-success-message {
        margin-bottom: 18px;
        padding: 18px;
        border: 1px solid rgba(26,127,55,.25);
        border-radius: 14px;
        background: #dafbe1;
        color: #116329;
      }

      .wr-success-message h1 {
        margin: 0 0 8px;
        color: #116329;
        font-size: 22px;
        line-height: 1.3;
        font-weight: 900;
      }

      .wr-success-message p {
        margin: 0;
        color: #116329;
        font-size: 15px;
        line-height: 1.65;
      }

      .wr-success-review {
        padding: 18px;
        border: 1px solid #d0d7de;
        border-radius: 14px;
        background: #ffffff;
        box-shadow: 0 1px 0 rgba(27,31,36,.04);
      }

      .wr-success-rating {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 10px;
        color: #bf8700;
        font-size: 16px;
        line-height: 1;
        font-weight: 900;
      }

      .wr-success-rating strong {
        color: #24292f;
        font-size: 14px;
        font-weight: 900;
      }

      .wr-success-person {
        margin: 0 0 10px;
        color: #57606a;
        font-size: 14px;
        line-height: 1.5;
        font-weight: 700;
      }

      .wr-success-person strong {
        color: #24292f;
        font-weight: 900;
      }

      .wr-success-title {
        margin: 0 0 8px;
        color: #24292f;
        font-size: 18px;
        line-height: 1.38;
        font-weight: 900;
      }

      .wr-success-service {
        margin: 0 0 8px;
        color: #57606a;
        font-size: 13px;
        line-height: 1.6;
        font-weight: 700;
      }

      .wr-success-service span {
        color: #24292f;
        font-weight: 900;
      }

      .wr-success-comment {
        margin: 0;
        color: #57606a;
        font-size: 15px;
        line-height: 1.75;
      }

      .wr-success-actions {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        flex-wrap: wrap;
        margin-top: 18px;
      }

      .wr-success-note {
        color: #57606a;
        font-size: 13px;
        line-height: 1.6;
      }

      .wr-success-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 40px;
        padding: 0 16px;
        border-radius: 8px;
        border: 1px solid rgba(27,31,36,.15);
        background: #2da44e;
        color: #ffffff;
        text-decoration: none;
        font-size: 14px;
        font-weight: 800;
      }

      .wr-success-btn:hover {
        background: #1f883d;
        color: #ffffff;
        text-decoration: none;
      }

      @media (max-width: 640px) {
        .wr-success-page {
          padding-top: 28px;
        }

        .wr-success-actions {
          align-items: stretch;
          flex-direction: column;
        }

        .wr-success-btn {
          width: 100%;
        }
      }
    </style>

    <main class="wr-success-page">
      <div class="wr-success-shell">
        <div class="wr-success-doctor-box">
          <img src="<?= e($doctor_image) ?>" alt="<?= e($doctor_name) ?>" loading="lazy">

          <div>
            <strong><?= e($doctor_name) ?></strong>

            <?php if ($doctor_subtitle !== ''): ?>
              <span><?= e($doctor_subtitle) ?></span>
            <?php else: ?>
              <span>Doctor Profile</span>
            <?php endif; ?>
          </div>
        </div>

        <section class="wr-success-message">
          <h1>Review Submitted Successfully</h1>
          <p>Your review is pending admin approval. You will be redirected to the doctor profile in <strong id="redirectCountdown">60</strong> seconds.</p>
        </section>

        <article class="wr-success-review">
          <div class="wr-success-rating">
            <span><?= e(wr_star_text($success_rating)) ?></span>
            <strong><?= e($success_rating_number) ?></strong>
          </div>

          <p class="wr-success-person">
            <strong><?= e($success_name) ?></strong>
            <?php if ($success_created_at !== ''): ?>
              · <?= e($success_created_at) ?>
            <?php endif; ?>
          </p>

          <?php if ($success_title !== ''): ?>
            <h2 class="wr-success-title"><?= e($success_title) ?></h2>
          <?php endif; ?>

          <?php if ($success_treatment !== '' || $success_visit_date !== ''): ?>
            <p class="wr-success-service">
              <?php if ($success_treatment !== ''): ?>
                <span>Treatment / Service:</span> <?= e($success_treatment) ?>
              <?php endif; ?>

              <?php if ($success_treatment !== '' && $success_visit_date !== ''): ?>
                ·
              <?php endif; ?>

              <?php if ($success_visit_date !== ''): ?>
                <span>Visit Date:</span> <?= e($success_visit_date) ?>
              <?php endif; ?>
            </p>
          <?php endif; ?>

          <?php if ($success_comment !== ''): ?>
            <p class="wr-success-comment"><?= nl2br(e($success_comment)) ?></p>
          <?php endif; ?>
        </article>

        <div class="wr-success-actions">
          <div class="wr-success-note">Auto redirect to doctor page in 1 minute.</div>
          <a href="<?= e(site_url('doctor/' . $slug . '#patient-reviews')) ?>" class="wr-success-btn">Back to Doctor</a>
        </div>
      </div>
    </main>

    <script>
      (function () {
        const redirectUrl = <?= json_encode(site_url('doctor/' . $slug . '#patient-reviews')) ?>;
        const countdown = document.getElementById('redirectCountdown');
        let secondsLeft = 60;

        const timer = window.setInterval(function () {
          secondsLeft -= 1;

          if (countdown) {
            countdown.textContent = String(Math.max(secondsLeft, 0));
          }

          if (secondsLeft <= 0) {
            window.clearInterval(timer);
            window.location.href = redirectUrl;
          }
        }, 1000);
      })();
    </script>
    <?php
    include __DIR__ . '/includes/footer.php';
    exit;
}
?>

<style>
  .wr-page {
    background: #ffffff;
    padding: 0 0 56px;
    color: #252525;
    font-family: inherit;
  }

  .wr-top-space {
    height: 18px;
  }

  .wr-shell {
    max-width: 520px;
    margin: 0 auto;
    padding: 0 18px;
  }

  .wr-breadcrumb,
  .wr-badge,
  .wr-head h1,
  .wr-head p,
  .wr-info-box-icon {
    display: none !important;
  }

  .wr-card {
    background: transparent;
    border: 0;
    box-shadow: none;
    overflow: visible;
  }

  .wr-head {
    padding: 0;
    border: 0;
    background: transparent;
  }

  .wr-doctor-box {
    display: grid;
    grid-template-columns: 56px minmax(0, 1fr);
    gap: 16px;
    align-items: center;
    margin: 10px auto 72px;
    padding: 16px 18px;
    border: 0;
    border-radius: 16px;
    background: #f7f6f3;
    max-width: 480px;
  }

  .wr-doctor-box img {
    width: 56px;
    height: 56px;
    border-radius: 7px;
    object-fit: cover;
    border: 1px solid #e4e1da;
    background: #ffffff;
  }

  .wr-doctor-box strong {
    display: block;
    color: #191919;
    font-size: 18px;
    line-height: 1.25;
    font-weight: 700;
  }

  .wr-doctor-box span {
    display: block;
    margin-top: 3px;
    color: #191919;
    font-size: 17px;
    line-height: 1.3;
    font-weight: 400;
  }

  .wr-alert {
    margin: 0 0 18px;
    padding: 12px 14px;
    border-radius: 8px;
    border: 1px solid #d8dee4;
    background: #f6f8fa;
    color: #57606a;
    line-height: 1.6;
    font-size: 14px;
  }

  .wr-alert.success {
    background: #dafbe1;
    border-color: rgba(26,127,55,.25);
    color: #116329;
  }

  .wr-alert.error {
    background: #ffebe9;
    border-color: rgba(207,34,46,.25);
    color: #cf222e;
  }

  .wr-form {
    padding: 0;
    display: grid;
    gap: 26px;
  }

  .wr-rating-stage {
    margin-top: 0;
  }

  .wr-section {
    display: grid;
    gap: 10px;
  }

  .wr-section h3 {
    margin: 0;
    color: #1f2328;
    font-size: 26px;
    line-height: 1.25;
    font-weight: 600;
    letter-spacing: -.02em;
  }

  .wr-section-hint {
    display: none;
  }

  .wr-rating-wrap {
    max-width: 245px;
    margin-top: 12px;
  }

  .wr-rating-strip {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 2px;
    width: 100%;
    overflow: hidden;
    border: 0;
    border-radius: 0;
    background: transparent;
    box-shadow: none;
  }

  .wr-rating-input {
    position: absolute;
    opacity: 0;
    pointer-events: none;
  }

  .wr-rating-card {
    position: relative;
    min-height: 46px;
    border: 0;
    border-radius: 0;
    background: #d9dbe5;
    color: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: .16s ease;
    overflow: hidden;
    user-select: none;
  }

  .wr-rating-card:hover {
    filter: brightness(.97);
  }

  .wr-rating-star {
    position: relative;
    z-index: 1;
    font-size: 30px;
    line-height: 1;
    color: #ffffff;
  }

  .wr-rating-title,
  .wr-rating-note {
    display: none;
  }


  .wr-rating-strip.hover-1 .wr-rating-card.is-hover-filled {
    background: #ff4b3e;
  }

  .wr-rating-strip.hover-2 .wr-rating-card.is-hover-filled {
    background: #ff7a21;
  }

  .wr-rating-strip.hover-3 .wr-rating-card.is-hover-filled {
    background: #ffbf00;
  }

  .wr-rating-strip.hover-4 .wr-rating-card.is-hover-filled {
    background: #63c914;
  }

  .wr-rating-strip.hover-5 .wr-rating-card.is-hover-filled {
    background: #00b67a;
  }

  .wr-rating-strip.rating-1 .wr-rating-card.is-filled {
    background: #ff4b3e;
  }

  .wr-rating-strip.rating-2 .wr-rating-card.is-filled {
    background: #ff7a21;
  }

  .wr-rating-strip.rating-3 .wr-rating-card.is-filled {
    background: #ffbf00;
  }

  .wr-rating-strip.rating-4 .wr-rating-card.is-filled {
    background: #63c914;
  }

  .wr-rating-strip.rating-5 .wr-rating-card.is-filled {
    background: #00b67a;
  }

  .wr-rating-summary {
    display: none;
  }

  .wr-grid-line {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 18px;
    margin-bottom: 2px;
  }

  .wr-tip {
    color: #3157d6;
    font-size: 14px;
    text-decoration: underline;
    white-space: nowrap;
  }

  .wr-field {
    display: grid;
    gap: 8px;
  }

  .wr-field label,
  .wr-section-label {
    color: #252525;
    font-size: 17px;
    line-height: 1.45;
    font-weight: 500;
  }

  .wr-field small {
    color: #565656;
    font-size: 13px;
    line-height: 1.55;
  }

  .wr-input,
  .wr-textarea,
  .wr-date {
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

  .wr-textarea {
    min-height: 185px;
    padding: 12px 16px;
    resize: vertical;
    line-height: 1.6;
  }

  .wr-input:focus,
  .wr-textarea:focus,
  .wr-date:focus {
    border-color: #3157d6;
    box-shadow: 0 0 0 3px rgba(49,87,214,.12);
  }

  .wr-chip-box {
    margin-top: 10px;
    padding: 10px 16px 12px;
    border: 0;
    border-radius: 0 0 14px 14px;
    background: #f7f6f3;
  }

  .wr-chip-box small {
    display: block;
    margin-bottom: 7px;
    color: #626262;
    font-size: 12px;
    font-weight: 500;
  }

  .wr-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 5px;
  }

  .wr-chip {
    display: inline-flex;
    align-items: center;
    min-height: 24px;
    padding: 0 9px;
    border-radius: 3px;
    background: #ffffff;
    border: 1px solid #d8d4ce;
    color: #444444;
    font-size: 12px;
    font-weight: 500;
  }

  .wr-link {
    display: inline-flex;
    color: #3157d6;
    font-size: 15px;
    text-decoration: underline;
    font-weight: 400;
    width: fit-content;
  }

  .wr-row {
    display: grid;
    grid-template-columns: 1fr;
    gap: 26px;
  }

  .wr-info-box {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 14px 16px;
    border: 1px solid #ded8cc;
    border-radius: 14px;
    background: #fffdf6;
    color: #191919;
    font-size: 14px;
    line-height: 1.55;
  }

  .wr-info-box::before {
    content: "★";
    width: 28px;
    height: 28px;
    border-radius: 8px;
    background: #bcd3ff;
    color: #3157d6;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex: 0 0 28px;
    font-size: 16px;
    font-weight: 800;
  }

  .wr-check {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    color: #474747;
    font-size: 14px;
    line-height: 1.5;
    font-weight: 500;
    margin-top: 2px;
    cursor: pointer;
  }

  .wr-check input {
    width: 17px;
    height: 17px;
    accent-color: #00b67a;
  }

  .wr-confirm {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    color: #3d3d3d;
    font-size: 14px;
    line-height: 1.65;
  }

  .wr-confirm input {
    width: 22px;
    height: 22px;
    margin-top: 1px;
    accent-color: #00b67a;
    flex: 0 0 22px;
  }


  .wr-math-box {
    display: grid;
    gap: 12px;
    padding: 14px;
    border: 1px solid #d0d7de;
    border-radius: 12px;
    background: #ffffff;
    box-shadow: 0 1px 0 rgba(27,31,36,.04);
  }

  .wr-math-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    padding-bottom: 12px;
    border-bottom: 1px solid #d8dee4;
  }

  .wr-math-title {
    display: flex;
    align-items: center;
    gap: 9px;
    color: #24292f;
    font-size: 14px;
    font-weight: 800;
  }

  .wr-math-title::before {
    content: "✓";
    width: 22px;
    height: 22px;
    border-radius: 999px;
    background: #ddf4ff;
    border: 1px solid rgba(9,105,218,.20);
    color: #0969da;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    font-weight: 900;
  }

  .wr-math-badge {
    display: inline-flex;
    align-items: center;
    min-height: 24px;
    padding: 3px 9px;
    border-radius: 999px;
    background: #f6f8fa;
    border: 1px solid #d0d7de;
    color: #57606a;
    font-size: 11px;
    font-weight: 800;
    white-space: nowrap;
  }

  .wr-math-body {
    display: grid;
    grid-template-columns: 120px minmax(0, 1fr);
    gap: 12px;
    align-items: center;
  }

  .wr-math-question-card {
    min-height: 44px;
    padding: 0 14px;
    border: 1px solid rgba(26,127,55,.22);
    border-radius: 8px;
    background: #dafbe1;
    color: #116329;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    line-height: 1;
    font-weight: 900;
    white-space: nowrap;
  }

  .wr-math-input {
    width: 100%;
    min-height: 44px;
    padding: 0 12px;
    border: 1px solid #d0d7de;
    border-radius: 8px;
    background: #ffffff;
    color: #24292f;
    font-family: inherit;
    font-size: 14px;
    outline: none;
    box-shadow: inset 0 1px 0 rgba(208,215,222,.20);
  }

  .wr-math-input:focus {
    border-color: #0969da;
    box-shadow: 0 0 0 3px rgba(9,105,218,.14);
  }

  .wr-math-status {
    display: flex;
    align-items: center;
    gap: 7px;
    color: #9a3412;
    font-size: 13px;
    line-height: 1.5;
    font-weight: 700;
  }

  .wr-math-status::before {
    content: "!";
    width: 16px;
    height: 16px;
    border-radius: 999px;
    background: #9a3412;
    color: #ffffff;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 11px;
    flex: 0 0 16px;
  }

  .wr-math-status.ok {
    color: #1a7f37;
  }

  .wr-math-status.ok::before {
    content: "✓";
    background: #1a7f37;
  }

  .wr-submit-disabled {
    background: #aeb8c6 !important;
    border-color: #aeb8c6 !important;
    color: #ffffff !important;
    cursor: not-allowed !important;
    opacity: 1 !important;
  }



  .wr-success-preview {
    margin: 18px 22px 0;
    padding: 16px;
    border: 1px solid rgba(26,127,55,.25);
    border-radius: 14px;
    background: #dafbe1;
    color: #116329;
  }

  .wr-success-preview h3 {
    margin: 0 0 8px;
    color: #116329;
    font-size: 18px;
    font-weight: 900;
  }

  .wr-success-preview p {
    margin: 0;
    line-height: 1.65;
    color: #116329;
  }

  .wr-success-review {
    margin-top: 12px;
    padding: 14px;
    border: 1px solid rgba(26,127,55,.22);
    border-radius: 12px;
    background: #ffffff;
    color: #24292f;
  }

  .wr-success-review strong {
    display: block;
    margin-bottom: 5px;
    color: #24292f;
    font-size: 15px;
    font-weight: 900;
  }

  .wr-success-review .stars {
    color: #bf8700;
    font-size: 15px;
    font-weight: 900;
    margin-bottom: 8px;
  }

  .wr-success-review p {
    color: #57606a;
    margin: 0;
  }

  .wr-auto-title-alert {
    display: none;
    padding: 12px 14px;
    border: 1px solid #d4a72c;
    border-radius: 10px;
    background: #fff8c5;
    color: #7d4e00;
    font-size: 14px;
    line-height: 1.6;
    font-weight: 700;
  }

  .wr-auto-title-alert.is-visible {
    display: block;
  }



  .wr-plain-warning {
    display: none;
    margin-top: 8px;
    color: #cf222e;
    font-size: 13px;
    line-height: 1.5;
    font-weight: 700;
  }

  .wr-plain-warning.is-visible {
    display: block;
  }

  .wr-honey {
    display: none !important;
  }

  .wr-actions {
    display: flex;
    justify-content: flex-start;
    gap: 12px;
    align-items: center;
    flex-wrap: wrap;
    padding-top: 0;
    border-top: 0;
  }

  .wr-actions-note {
    display: none;
  }

  .wr-btn-row {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
  }

  .wr-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 46px;
    padding: 0 18px;
    border-radius: 5px;
    border: 1px solid #d6d1c8;
    background: #ffffff;
    color: #191919;
    text-decoration: none;
    font-size: 15px;
    font-weight: 700;
    cursor: pointer;
    font-family: inherit;
  }

  .wr-btn:hover {
    background: #f6f6f3;
    color: #191919;
    text-decoration: none;
  }

  .wr-btn-primary {
    background: #00b67a;
    border-color: #00b67a;
    color: #ffffff;
  }

  .wr-btn-primary:hover {
    background: #009e6a;
    border-color: #009e6a;
    color: #ffffff;
  }

  @media (max-width: 700px) {
    .wr-shell {
      max-width: 100%;
      padding: 0 16px;
    }

    .wr-doctor-box {
      margin-bottom: 28px;
    }

    .wr-rating-wrap {
      max-width: 220px;
    }

    .wr-rating-card {
      min-height: 38px;
    }

    .wr-rating-star {
      font-size: 23px;
    }

    .wr-grid-line {
      align-items: flex-start;
      flex-direction: column;
      gap: 6px;
    }


    .wr-math-body {
      grid-template-columns: 1fr;
    }

    .wr-math-question-card {
      justify-content: flex-start;
    }

    .wr-btn-row {
      display: grid;
      grid-template-columns: 1fr;
      width: 100%;
    }

    .wr-btn {
      width: 100%;
    }
  }

  .wr-first-step {
    min-height: 360px;
  }

  .wr-first-recaptcha {
    max-width: 520px;
    margin-top: 38px;
    color: #333333;
    font-size: 14px;
    line-height: 1.55;
  }

  .wr-first-recaptcha a {
    color: #3157d6;
    text-decoration: underline;
  }

  .wr-form-details {
    display: none;
    opacity: 0;
    transform: translateY(18px);
  }

  .wr-form-details.is-open {
    display: grid;
    gap: 26px;
    animation: wrSlideOpen .32s ease forwards;
  }

  @keyframes wrSlideOpen {
    from {
      opacity: 0;
      transform: translateY(18px);
    }

    to {
      opacity: 1;
      transform: translateY(0);
    }
  }

  .wr-rating-animate .wr-rating-card.is-filled .wr-rating-star {
    animation: wrStarPop .22s ease;
  }

  @keyframes wrStarPop {
    0% {
      transform: scale(.84);
    }

    70% {
      transform: scale(1.16);
    }

    100% {
      transform: scale(1);
    }
  }

</style>

<main class="wr-page">
  <div class="wr-top-space"></div>
  <div class="wr-shell">
    <section class="wr-card">
      <div class="wr-head">
        <span class="wr-badge">Patient feedback</span>
        <h1>Write a Review</h1>
        <p>Share your experience with this doctor. Your review will be checked by admin before it appears publicly.</p>

        <div class="wr-doctor-box">
            <img src="<?= e($doctor_image) ?>" alt="<?= e($doctor_name) ?>" loading="lazy">

            <div>
              <strong><?= e($doctor_name) ?></strong>

              <?php if ($doctor_subtitle !== ''): ?>
                <span><?= e($doctor_subtitle) ?></span>
              <?php else: ?>
                <span>Doctor Profile</span>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <?php if ($form_status === 'missing'): ?>
          <div class="wr-alert error">Please fill the required fields: Rating and Review Description. Review title and patient name can be left empty.</div>
        <?php elseif ($form_status === 'plain'): ?>
          <div class="wr-alert error">Only plain text is allowed. Please remove links, HTML, code, markdown, emails, or special formatting.</div>
        <?php elseif ($form_status === 'math'): ?>
          <div class="wr-alert error">Math answer was incorrect. Please solve the simple math question before submitting.</div>
        <?php elseif ($form_status === 'future_date'): ?>
          <div class="wr-alert error">Date of experience cannot be a future date. Please select today or a previous date.</div>
        <?php elseif ($form_status === 'short_title'): ?>
          <div class="wr-alert error">Review title is too short. Please write a longer title with at least 15 characters.</div>
        <?php elseif ($form_status === 'failed'): ?>
          <div class="wr-alert error">Review could not be submitted right now. Please try again later.</div>
        <?php elseif ($form_status === 'submitted'): ?>
          <div class="wr-success-preview" id="reviewSubmittedPreview">
            <h3>Review Submitted Successfully</h3>
            <p>Your review is now pending admin approval. You will be redirected to the doctor profile in 1 minute.</p>

            <?php if (!empty($submitted_review)): ?>
              <div class="wr-success-review">
                <div class="stars"><?= e(wr_star_text((int)($submitted_review['rating'] ?? 5))) ?></div>
                <strong><?= e($submitted_review['title'] ?? 'Patient Experience') ?></strong>

                <?php if (!empty($submitted_review['comment'])): ?>
                  <p><?= nl2br(e($submitted_review['comment'])) ?></p>
                <?php endif; ?>
              </div>
            <?php endif; ?>
          </div>

          <script>
            window.setTimeout(function () {
              window.location.href = <?= json_encode(site_url('doctor/' . $slug . '#patient-reviews')) ?>;
            }, 60000);
          </script>
        <?php endif; ?>

        <form method="post" class="wr-form">
          <input type="text" name="review_website" class="wr-honey" tabindex="-1" autocomplete="off">

          <section class="wr-section wr-rating-stage">
            <h3>How would you rate your experience?</h3>
            <p class="wr-section-hint">Pick the option that best matches your overall experience.</p>

            <div class="wr-rating-wrap">
              <div class="wr-rating-strip" id="reviewRatingGrid">
                <input class="wr-rating-input" type="radio" name="review_rating" id="rating1" value="1" required>
                <label class="wr-rating-card" for="rating1" data-rating="1" title="1 - Poor">
                  <span class="wr-rating-star">★</span>
                </label>

                <input class="wr-rating-input" type="radio" name="review_rating" id="rating2" value="2">
                <label class="wr-rating-card" for="rating2" data-rating="2" title="2 - Fair">
                  <span class="wr-rating-star">★</span>
                </label>

                <input class="wr-rating-input" type="radio" name="review_rating" id="rating3" value="3">
                <label class="wr-rating-card" for="rating3" data-rating="3" title="3 - Good">
                  <span class="wr-rating-star">★</span>
                </label>

                <input class="wr-rating-input" type="radio" name="review_rating" id="rating4" value="4">
                <label class="wr-rating-card" for="rating4" data-rating="4" title="4 - Very Good">
                  <span class="wr-rating-star">★</span>
                </label>

                <input class="wr-rating-input" type="radio" name="review_rating" id="rating5" value="5">
                <label class="wr-rating-card" for="rating5" data-rating="5" title="5 - Excellent">
                  <span class="wr-rating-star">★</span>
                </label>
              </div>

              <div class="wr-rating-summary" id="reviewRatingSummary">
                Selected rating: <strong>5 - Excellent</strong>
              </div>
            </div>
          </section>

          <div class="wr-first-recaptcha" id="wrFirstRecaptcha">
            This site is protected by reCAPTCHA. We collect device and interaction
            signals for security purposes as described in our <a href="#" onclick="return false;">Privacy Policy</a>.
          </div>

          <div class="wr-form-details" id="wrFormDetails">
            <section class="wr-section">
              <div class="wr-field">
                <label for="review_comment">Tell us more about your experience</label>
              <textarea class="wr-textarea" id="review_comment" name="review_comment" placeholder="What made your experience great? How was the consultation, communication, and treatment? Remember to be honest, helpful, and constructive!" maxlength="1200" data-plain-text required></textarea>
            </div>

            <div class="wr-chip-box">
              <small>Other people mention</small>

              <div class="wr-chips">
                <span class="wr-chip">Staff</span>
                <span class="wr-chip">Behavior</span>
                <span class="wr-chip">Treatment</span>
              </div>
            </div>
          </section>

          <section class="wr-section">
            <div class="wr-field">
              <label for="review_title">Give your review a title</label>
              <input class="wr-input" type="text" id="review_title" name="review_title" placeholder="Leave empty and we will create one from your experience" maxlength="180" data-plain-text>
              <div class="wr-auto-title-alert" id="autoTitleAlert">
                We created a title from your experience. Please check it, then click Submit Review again. Title must be at least 15 characters.
              </div>
            </div>
          </section>

          <section class="wr-row">
            <div class="wr-field">
              <label for="visit_date">Date of experience <small>(optional)</small></label>
              <input class="wr-date" type="date" id="visit_date" name="visit_date" max="<?= e(date('Y-m-d')) ?>">
            </div>

            <div class="wr-field">
              <label for="treatment_type">Treatment / Service Type <small>(optional)</small></label>
              <input class="wr-input" type="text" id="treatment_type" name="treatment_type" placeholder="Consultation, surgery, follow-up" maxlength="180" data-plain-text>
            </div>
          </section>

          <section class="wr-section">
            <div class="wr-field">
              <label for="review_name">Patient Name</label>
              <input class="wr-input" type="text" id="review_name" name="review_name" placeholder="Leave empty to submit as Anonymous" maxlength="150" data-plain-text>

              <label class="wr-check">
                <input type="checkbox" name="is_anonymous" value="1" id="isAnonymousReview">
                Submit as Anonymous
              </label>

              <small>Only approved reviews are shown publicly.</small>
            </div>
          </section>

          <div class="wr-info-box">
            <div class="wr-info-icon">!</div>
            <div>
              This platform does not allow payments or benefits in exchange for leaving a review. Reviews should be honest, helpful, and based on a genuine experience.
            </div>
          </div>

          <div class="wr-math-box">
            <div class="wr-math-head">
              <div class="wr-math-title">Security Check</div>
              <div class="wr-math-badge">Required</div>
            </div>

            <div class="wr-math-body">
              <div class="wr-math-question-card">
                <?= e((string)$math_question['a']) ?> <?= e($math_question['op']) ?> <?= e((string)$math_question['b']) ?> = ?
              </div>

              <input
                class="wr-math-input"
                type="number"
                name="math_answer"
                id="mathAnswer"
                inputmode="numeric"
                placeholder="Enter answer"
                autocomplete="off"
                required
              >
            </div>

            <div class="wr-math-status" id="mathStatus">
              Solve the math question to enable Submit Review.
            </div>

            <input type="hidden" name="math_a" value="<?= e((string)$math_question['a']) ?>">
            <input type="hidden" name="math_b" value="<?= e((string)$math_question['b']) ?>">
            <input type="hidden" name="math_op" value="<?= e($math_question['op']) ?>">
            <input type="hidden" id="mathCorrectAnswer" value="<?= e((string)$math_answer_value) ?>">
          </div>

          <label class="wr-confirm">
            <input type="checkbox" required>
            <span>By submitting this review, you confirm it is based on a genuine experience and follows the site review policy.</span>
          </label>

          <div class="wr-actions">
            <div class="wr-actions-note">
              Review status will remain pending until admin approval.
            </div>

            <div class="wr-btn-row">
              <button type="submit" class="wr-btn wr-btn-primary wr-submit-disabled" id="submitReviewBtn" disabled>Submit Review</button>
              <a href="<?= e(site_url('doctor/' . $slug . '#patient-reviews')) ?>" class="wr-btn">Back to Reviews</a>
            </div>
          </div>
        </div>
        </form>
    </section>
  </div>
</main>

<script>
  (function () {
    const anonymous = document.getElementById('isAnonymousReview');
    const nameInput = document.getElementById('review_name');
    const ratingInputs = document.querySelectorAll('input[name="review_rating"]');
    const ratingCards = document.querySelectorAll('.wr-rating-card');
    const ratingSummary = document.getElementById('reviewRatingSummary');
    const formDetails = document.getElementById('wrFormDetails');
    const firstRecaptcha = document.getElementById('wrFirstRecaptcha');
    const ratingStrip = document.getElementById('reviewRatingGrid');
    const mathAnswer = document.getElementById('mathAnswer');
    const mathCorrectAnswer = document.getElementById('mathCorrectAnswer');
    const mathStatus = document.getElementById('mathStatus');
    const submitReviewBtn = document.getElementById('submitReviewBtn');
    const reviewForm = document.querySelector('.wr-form');
    const reviewTitle = document.getElementById('review_title');
    const reviewComment = document.getElementById('review_comment');
    const autoTitleAlert = document.getElementById('autoTitleAlert');
    const plainTextFields = document.querySelectorAll('[data-plain-text]');
    let autoTitleConfirmed = false;
    const reviewCookieKey = 'doctor_review_rating_<?= e(preg_replace('/[^a-zA-Z0-9_]/', '_', $slug)) ?>';

    const ratingMap = {
      '1': '1 - Poor',
      '2': '2 - Fair',
      '3': '3 - Good',
      '4': '4 - Very Good',
      '5': '5 - Excellent'
    };

    function syncNameField() {
      if (!anonymous || !nameInput) return;

      if (anonymous.checked) {
        nameInput.value = '';
        nameInput.disabled = true;
        nameInput.placeholder = 'Anonymous';
      } else {
        nameInput.disabled = false;
        nameInput.placeholder = 'Your name';
      }
    }

    function setReviewCookie(name, value, days) {
      const maxAge = days * 24 * 60 * 60;
      document.cookie = encodeURIComponent(name) + '=' + encodeURIComponent(value) + '; path=/; max-age=' + maxAge + '; SameSite=Lax';
    }

    function getReviewCookie(name) {
      const encodedName = encodeURIComponent(name) + '=';
      const parts = document.cookie ? document.cookie.split(';') : [];

      for (let i = 0; i < parts.length; i++) {
        let item = parts[i].trim();

        if (item.indexOf(encodedName) === 0) {
          return decodeURIComponent(item.substring(encodedName.length));
        }
      }

      return '';
    }

    function openReviewDetails() {
      if (formDetails && !formDetails.classList.contains('is-open')) {
        formDetails.classList.add('is-open');
      }

      if (firstRecaptcha) {
        firstRecaptcha.style.display = 'none';
      }
    }

    function setReviewCookie(name, value, days) {
      const maxAge = days * 24 * 60 * 60;
      document.cookie = encodeURIComponent(name) + '=' + encodeURIComponent(value) + '; path=/; max-age=' + maxAge + '; SameSite=Lax';
    }

    function getReviewCookie(name) {
      const encodedName = encodeURIComponent(name) + '=';
      const parts = document.cookie ? document.cookie.split(';') : [];

      for (let i = 0; i < parts.length; i++) {
        let item = parts[i].trim();

        if (item.indexOf(encodedName) === 0) {
          return decodeURIComponent(item.substring(encodedName.length));
        }
      }

      return '';
    }

    function openReviewDetails() {
      if (formDetails && !formDetails.classList.contains('is-open')) {
        formDetails.classList.add('is-open');
      }

      if (firstRecaptcha) {
        firstRecaptcha.style.display = 'none';
      }
    }

    function syncRatingCards() {
      let selected = '';

      ratingInputs.forEach(function (input) {
        if (input.checked) {
          selected = input.value;
        }
      });

      if (selected === '') {
        if (ratingStrip) {
          ratingStrip.className = 'wr-rating-strip';
        }

        ratingCards.forEach(function (card) {
          card.classList.remove('is-filled');
        });

        if (ratingSummary) {
          ratingSummary.innerHTML = '';
        }

        return;
      }

      const selectedNumber = parseInt(selected, 10);

      if (ratingStrip) {
        ratingStrip.className = 'wr-rating-strip rating-' + selected + ' wr-rating-animate';
        window.setTimeout(function () {
          if (ratingStrip) {
            ratingStrip.classList.remove('wr-rating-animate');
          }
        }, 260);
      }

      ratingCards.forEach(function (card) {
        const cardRating = parseInt(card.getAttribute('data-rating') || '0', 10);
        card.classList.toggle('is-filled', cardRating <= selectedNumber);
      });

      if (ratingSummary) {
        ratingSummary.innerHTML = 'Selected rating: <strong>' + ratingMap[selected] + '</strong>';
      }
    }

    const savedRating = getReviewCookie(reviewCookieKey);

    if (/^[1-5]$/.test(savedRating)) {
      const savedInput = document.getElementById('rating' + savedRating);

      if (savedInput) {
        savedInput.checked = true;
        if (mathAnswer) {
      mathAnswer.addEventListener('input', syncMathSubmit);
      syncMathSubmit();
    }

    if (reviewForm) {
      reviewForm.addEventListener('submit', handleAutoTitleBeforeSubmit);
    }

    plainTextFields.forEach(function (field) {
      field.addEventListener('input', function () {
        const fakeEvent = {
          preventDefault: function () {}
        };

        ensurePlainText(fakeEvent);
      });

      field.addEventListener('paste', function () {
        window.setTimeout(function () {
          const fakeEvent = {
            preventDefault: function () {}
          };

          ensurePlainText(fakeEvent);
        }, 30);
      });
    });

    if (reviewTitle) {
      reviewTitle.addEventListener('input', function () {
        autoTitleConfirmed = String(reviewTitle.value || '').trim() !== '';

        if (autoTitleAlert) {
          autoTitleAlert.classList.remove('is-visible');
        }
      });
    }

    syncRatingCards();
        openReviewDetails();
      }
    }

    function isPlainTextOnly(value) {
      const text = String(value || '');

      const blockedPatterns = [
        /<[^>]+>/i,
        /&lt;[^&]*&gt;/i,
        /https?:\/\/[^\s]+/i,
        /www\.[^\s]+/i,
        /[a-z0-9._%+\-]+@[a-z0-9.\-]+\.[a-z]{2,}/i,
        /!\[[^\]]*\]\([^)]+\)/,
        /\[[^\]]*\]\([^)]+\)/,
        /```/,
        /<\?php/i,
        /javascript:/i,
        /data:/i,
        /onerror\s*=/i,
        /onclick\s*=/i
      ];

      return !blockedPatterns.some(function (pattern) {
        return pattern.test(text);
      });
    }

    function ensurePlainText(event) {
      let isValid = true;

      plainTextFields.forEach(function (field) {
        let warning = field.parentElement ? field.parentElement.querySelector('.wr-plain-warning') : null;

        if (!warning && field.parentElement) {
          warning = document.createElement('div');
          warning.className = 'wr-plain-warning';
          warning.textContent = 'Only plain text is allowed. Remove links, HTML, code, markdown, emails, or special formatting.';
          field.parentElement.appendChild(warning);
        }

        if (!isPlainTextOnly(field.value)) {
          isValid = false;

          if (warning) {
            warning.classList.add('is-visible');
          }
        } else if (warning) {
          warning.classList.remove('is-visible');
        }
      });

      if (!isValid) {
        event.preventDefault();

        const firstWarning = document.querySelector('.wr-plain-warning.is-visible');

        if (firstWarning) {
          firstWarning.scrollIntoView({
            behavior: 'smooth',
            block: 'center'
          });
        }
      }

      return isValid;
    }

    function createAutoTitleFromComment(comment) {
      const clean = String(comment || '').replace(/\s+/g, ' ').trim();
      const limit = 42;
      const minLength = 15;

      if (clean === '') {
        return '';
      }

      const periodIndex = clean.indexOf('.');

      if (periodIndex >= 0) {
        return clean.substring(0, periodIndex + 1).trim();
      }

      if (clean.length <= limit) {
        return clean;
      }

      let title = clean.substring(0, limit).trim();
      title = title.replace(/[,.!?;:\-–—\s]+$/g, '').trim();

      return title !== '' ? title + '…' : '';
    }

    function handleAutoTitleBeforeSubmit(event) {
      if (!ensurePlainText(event)) {
        return;
      }

      if (!reviewTitle || !reviewComment) {
        return;
      }

      const titleValue = String(reviewTitle.value || '').trim();
      const commentValue = String(reviewComment.value || '').trim();

      if (titleValue === '' && commentValue !== '' && !autoTitleConfirmed) {
        event.preventDefault();

        const generatedTitle = createAutoTitleFromComment(commentValue);
        reviewTitle.value = generatedTitle;

        if (generatedTitle.length < 15) {
          autoTitleConfirmed = false;

          if (autoTitleAlert) {
            autoTitleAlert.textContent = 'Auto title is too short. Please write a longer title with at least 15 characters.';
            autoTitleAlert.classList.add('is-visible');
          }
        } else {
          autoTitleConfirmed = true;

          if (autoTitleAlert) {
            autoTitleAlert.textContent = 'We created a title from your experience. Please check it, then click Submit Review again.';
            autoTitleAlert.classList.add('is-visible');
          }
        }

        reviewTitle.scrollIntoView({
          behavior: 'smooth',
          block: 'center'
        });

        window.setTimeout(function () {
          reviewTitle.focus();
        }, 450);

        return;
      }

      if (reviewTitle && String(reviewTitle.value || '').trim().length < 15) {
        event.preventDefault();

        if (autoTitleAlert) {
          autoTitleAlert.textContent = 'Review title is too short. Please write a longer title with at least 15 characters.';
          autoTitleAlert.classList.add('is-visible');
        }

        reviewTitle.scrollIntoView({
          behavior: 'smooth',
          block: 'center'
        });

        window.setTimeout(function () {
          reviewTitle.focus();
        }, 450);
      }
    }

    function syncMathSubmit() {
      if (!mathAnswer || !mathCorrectAnswer || !submitReviewBtn) {
        return;
      }

      const current = String(mathAnswer.value || '').trim();
      const correct = String(mathCorrectAnswer.value || '').trim();

      if (current !== '' && current === correct) {
        submitReviewBtn.disabled = false;
        submitReviewBtn.classList.remove('wr-submit-disabled');
        submitReviewBtn.textContent = 'Submit Review';

        if (mathStatus) {
          mathStatus.textContent = 'Correct. Submit Review is enabled.';
          mathStatus.className = 'wr-math-status ok';
        }
      } else {
        submitReviewBtn.disabled = true;
        submitReviewBtn.classList.add('wr-submit-disabled');
        submitReviewBtn.textContent = 'Submit Review';

        if (mathStatus) {
          mathStatus.textContent = current === ''
            ? 'Solve the math question to enable Submit Review.'
            : 'Wrong answer. Please try again.';
          mathStatus.className = 'wr-math-status';
        }
      }
    }

    if (anonymous) {
      anonymous.addEventListener('change', syncNameField);
      syncNameField();
    }

    ratingInputs.forEach(function (input) {
      input.addEventListener('change', function () {
        if (input.checked) {
          setReviewCookie(reviewCookieKey, input.value, 180);
        }

        syncRatingCards();
        openReviewDetails();
      });
    });

    ratingCards.forEach(function (card) {
      card.addEventListener('mouseenter', function () {
        const hoverRating = parseInt(card.getAttribute('data-rating') || '0', 10);

        if (!ratingStrip || hoverRating <= 0) {
          return;
        }

        ratingStrip.className = 'wr-rating-strip hover-' + hoverRating;

        ratingCards.forEach(function (item) {
          const itemRating = parseInt(item.getAttribute('data-rating') || '0', 10);
          item.classList.toggle('is-hover-filled', itemRating <= hoverRating);
        });
      });

      card.addEventListener('click', function () {
        const clickedRating = card.getAttribute('data-rating') || '';

        if (/^[1-5]$/.test(clickedRating)) {
          setReviewCookie(reviewCookieKey, clickedRating, 180);
        }

        window.setTimeout(function () {
          syncRatingCards();
          openReviewDetails();
        }, 80);
      });
    });

    if (ratingStrip) {
      ratingStrip.addEventListener('mouseleave', function () {
        ratingCards.forEach(function (item) {
          item.classList.remove('is-hover-filled');
        });

        syncRatingCards();
      });
    }

    syncRatingCards();
  })();
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
