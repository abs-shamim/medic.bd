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

$wr_css_file = $form_status === 'submitted' ? 'write-review-success.css' : 'write-review.css';
$extra_head_html = '<link rel="stylesheet" href="' . e(site_url('assets/css/' . $wr_css_file)) . '?v=' . e(front_asset_version()) . '">';

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

      <script src="<?= e(site_url('assets/js/write-review-success.js')) ?>?v=<?= e(front_asset_version()) ?>" defer></script>
    <?php
    include __DIR__ . '/includes/footer.php';
    exit;
}
?>

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
        <?php endif; ?>

        <form method="post" class="wr-form" data-review-cookie-key="<?= e('doctor_review_rating_' . preg_replace('/[^a-zA-Z0-9_]/', '_', $slug)) ?>">
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
            signals for security purposes as described in our <a href="#" class="wr-noop-link">Privacy Policy</a>.
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
                aria-label="Answer to the security question above"
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

<script src="<?= e(site_url('assets/js/write-review.js')) ?>" defer></script>

<?php include __DIR__ . '/includes/footer.php'; ?>
