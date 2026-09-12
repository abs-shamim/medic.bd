<?php
$doctor_reviews_version = 'site-settings-reviews-full-fixed-20260604';
/*
|--------------------------------------------------------------------------
| Doctor Reviews Display Section
|--------------------------------------------------------------------------
| File path:
| /htdocs/deluti.com/includes/reviews.php
|
| Required from doctor.php:
| - $doctor array must be available
| - $pdo must be available from includes/functions.php
|--------------------------------------------------------------------------
*/

if (!isset($doctor) || empty($doctor['id'])) {
    return;
}

if (!function_exists('doctor_review_table_exists')) {
    function doctor_review_table_exists(string $table): bool
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

if (!function_exists('doctor_review_column_exists')) {
    function doctor_review_column_exists(string $table, string $column): bool
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
}


if (!function_exists('doctor_review_site_setting')) {
    function doctor_review_site_setting(string $key, string $default = ''): string
    {
        global $pdo;

        static $settings_cache = null;

        if ($settings_cache === null) {
            $settings_cache = [];

            try {
                if (!isset($pdo) || !doctor_review_table_exists('site_settings')) {
                    return $default;
                }

                $stmt = $pdo->query("SELECT setting_key, setting_value FROM site_settings");
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

                foreach ($rows as $row) {
                    $settings_cache[(string)$row['setting_key']] = (string)$row['setting_value'];
                }
            } catch (Throwable $e) {
                $settings_cache = [];
            }
        }

        $value = trim((string)($settings_cache[$key] ?? ''));

        return $value !== '' ? $value : $default;
    }
}

if (!function_exists('doctor_review_setting_int')) {
    function doctor_review_setting_int(string $key, int $default, int $min = 1, int $max = 100): int
    {
        $value = (int)doctor_review_site_setting($key, (string)$default);

        if ($value < $min) {
            return $default;
        }

        if ($value > $max) {
            return $max;
        }

        return $value;
    }
}

if (!function_exists('doctor_review_safe_text')) {
    function doctor_review_safe_text($value): string
    {
        return trim((string)($value ?? ''));
    }
}

if (!function_exists('doctor_review_public_reviews')) {
    function doctor_review_public_reviews(int $doctor_id, int $limit = 100): array
    {
        global $pdo;

        if ($doctor_id <= 0 || !doctor_review_table_exists('reviews')) {
            return [];
        }

        try {
            $where = ['doctor_id = :doctor_id'];
            $params = [':doctor_id' => $doctor_id];

            if (doctor_review_column_exists('reviews', 'status')) {
                $where[] = "status IN ('approved', 'active', 'published')";
            }

            $order = doctor_review_column_exists('reviews', 'created_at')
                ? 'created_at DESC, id DESC'
                : 'id DESC';

            $limit = max(1, (int)$limit);

            $stmt = $pdo->prepare("
                SELECT *
                FROM reviews
                WHERE " . implode(' AND ', $where) . "
                ORDER BY {$order}
                LIMIT {$limit}
            ");

            $stmt->execute($params);

            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            error_log('Doctor public reviews fetch failed: ' . $e->getMessage());
            return [];
        }
    }
}

if (!function_exists('doctor_review_stats')) {
    function doctor_review_stats(array $reviews): array
    {
        $breakdown = [
            5 => 0,
            4 => 0,
            3 => 0,
            2 => 0,
            1 => 0,
        ];

        $total = 0;
        $sum = 0;

        foreach ($reviews as $review) {
            $rating = (int)($review['rating'] ?? 0);

            if ($rating < 1 || $rating > 5) {
                continue;
            }

            $breakdown[$rating]++;
            $sum += $rating;
            $total++;
        }

        return [
            'total' => $total,
            'average' => $total > 0 ? round($sum / $total, 1) : 0,
            'breakdown' => $breakdown,
        ];
    }
}

if (!function_exists('doctor_review_star_text')) {
    function doctor_review_star_text(int $rating): string
    {
        $rating = max(1, min(5, $rating));

        return str_repeat('★', $rating) . str_repeat('☆', 5 - $rating);
    }
}

if (!function_exists('doctor_review_date_text')) {
    function doctor_review_date_text($date): string
    {
        $date = trim((string)($date ?? ''));

        if ($date === '' || $date === '0000-00-00' || $date === '0000-00-00 00:00:00') {
            return '';
        }

        $timestamp = strtotime($date);

        if (!$timestamp) {
            return '';
        }

        return date('M d, Y', $timestamp);
    }
}

if (!function_exists('doctor_review_initials')) {
    function doctor_review_initials(string $name): string
    {
        $name = trim($name);

        if ($name === '') {
            return 'P';
        }

        $parts = preg_split('/\s+/u', $name);
        $initials = '';

        foreach ($parts as $part) {
            $part = trim((string)$part);

            if ($part === '') {
                continue;
            }

            $initials .= function_exists('mb_substr')
                ? mb_substr($part, 0, 1, 'UTF-8')
                : substr($part, 0, 1);

            if (strlen($initials) >= 2) {
                break;
            }
        }

        $initials = strtoupper($initials);

        return $initials !== '' ? $initials : 'P';
    }
}

$doctor_review_id = (int)($doctor['id'] ?? 0);
$doctor_review_slug = trim((string)($doctor['slug'] ?? ($_GET['slug'] ?? '')));
$doctor_review_url = $doctor_review_slug !== ''
    ? site_url('doctor/' . $doctor_review_slug . '/write-review/')
    : '#';

$doctor_reviews = doctor_review_public_reviews($doctor_review_id, 100);
$doctor_review_stats = doctor_review_stats($doctor_reviews);

$doctor_review_total = (int)$doctor_review_stats['total'];
$doctor_review_average = (float)$doctor_review_stats['average'];
$doctor_review_breakdown = $doctor_review_stats['breakdown'];
$initial_review_limit = doctor_review_setting_int('doctor_reviews_initial_limit', 5, 1, 50);
$review_load_step = doctor_review_setting_int('doctor_reviews_load_step', 5, 1, 50);

$review_section_title = doctor_review_site_setting('doctor_reviews_title', 'Patient Reviews');
$review_section_description = doctor_review_site_setting('doctor_reviews_description', 'Approved patient feedback, treatment experience, and appointment service reviews.');
$review_button_text = doctor_review_site_setting('doctor_reviews_button_text', 'Write a Review');
$review_empty_title = doctor_review_site_setting('doctor_reviews_empty_title', 'No approved patient reviews yet.');
$review_empty_text = doctor_review_site_setting('doctor_reviews_empty_text', '<?= e($review_empty_text) ?>');
$review_load_more_text = doctor_review_site_setting('doctor_reviews_load_more_text', '<?= e($review_load_more_text) ?>');

?>

<link rel="stylesheet" href="<?= e(site_url('assets/css/doctor-reviews.css')) ?>?v=<?= e($doctor_reviews_version) ?>">

<section class="medic-lite-reviews" id="patient-reviews">
  <div class="medic-lite-review-top">
    <div class="medic-lite-review-title">
      <h2><?= e($review_section_title) ?></h2>
      <p><?= e($review_section_description) ?></p>
    </div>

    <?php if ($doctor_review_url !== '#'): ?>
      <a href="<?= e($doctor_review_url) ?>" class="medic-lite-review-btn"><?= e($review_button_text) ?></a>
    <?php endif; ?>
  </div>

  <?php if ($doctor_review_total > 0): ?>
    <div class="medic-lite-summary">
      <div class="medic-lite-score">
        <strong><?= e(number_format($doctor_review_average, 1)) ?></strong>
        <span><?= e(doctor_review_star_text((int)round($doctor_review_average))) ?></span>
        <small><?= e((string)$doctor_review_total) ?> approved reviews</small>
      </div>

      <div class="medic-lite-bars">
        <?php for ($star = 5; $star >= 1; $star--): ?>
          <?php
            $count = (int)($doctor_review_breakdown[$star] ?? 0);
            $percent = $doctor_review_total > 0 ? round(($count / $doctor_review_total) * 100) : 0;
          ?>

          <div class="medic-lite-bar-row">
            <span><?= $star ?> Star</span>

            <div class="medic-lite-bar">
              <i data-bar-percent="<?= (int)$percent ?>"></i>
            </div>

            <em><?= $count ?></em>
          </div>
        <?php endfor; ?>
      </div>
    </div>

    <div class="medic-lite-review-heading">
      <h3>Latest Reviews</h3>
      <span><?= e((string)$doctor_review_total) ?> total</span>
    </div>

    <div class="medic-lite-review-list" id="doctorReviewList">
      <?php foreach ($doctor_reviews as $index => $review): ?>
        <?php
          $rating = max(1, min(5, (int)($review['rating'] ?? 5)));
          $is_hidden = $index >= $initial_review_limit;

          $is_anonymous = !empty($review['is_anonymous']);
          $review_name = $is_anonymous
              ? 'Anonymous Patient'
              : doctor_review_safe_text($review['name'] ?? 'Patient');

          if ($review_name === '') {
              $review_name = 'Patient';
          }

          $review_title = doctor_review_safe_text($review['title'] ?? '');
          $review_comment = doctor_review_safe_text($review['comment'] ?? '');
          $treatment_type = doctor_review_safe_text($review['treatment_type'] ?? '');
          $visit_date = doctor_review_date_text($review['visit_date'] ?? '');
          $created_at = doctor_review_date_text($review['created_at'] ?? '');
          $is_verified_patient = !empty($review['is_verified_patient']);
        ?>

        <article
          class="medic-lite-review-item <?= $is_hidden ? 'is-hidden' : '' ?>"
          data-review-item
        >
          <div class="medic-lite-review-head">
            <div>
              <div class="medic-lite-review-name">
                <?= e($review_name) ?>

                <?php if ($created_at !== ''): ?>
                  <span class="medic-lite-review-date"> · <?= e($created_at) ?></span>
                <?php endif; ?>
              </div>
            </div>

            <div class="medic-lite-stars"><?= e(doctor_review_star_text($rating)) ?></div>
          </div>

          <?php if ($review_title !== ''): ?>
            <h4><?= e($review_title) ?></h4>
          <?php endif; ?>

          <?php if ($treatment_type !== '' || $visit_date !== ''): ?>
            <p class="medic-lite-service-line">
              <?php if ($treatment_type !== ''): ?>
                <span>Treatment / Service:</span> <?= e($treatment_type) ?>
              <?php endif; ?>

              <?php if ($treatment_type !== '' && $visit_date !== ''): ?>
                ·
              <?php endif; ?>

              <?php if ($visit_date !== ''): ?>
                <span>Visit Date:</span> <?= e($visit_date) ?>
              <?php endif; ?>
            </p>
          <?php endif; ?>

          <?php if ($review_comment !== ''): ?>
            <p class="medic-lite-review-text"><?= nl2br(e($review_comment)) ?></p>
          <?php endif; ?>

          <?php if ($is_verified_patient): ?>
            <div class="medic-lite-meta">
              <span class="verified">Verified Patient</span>
            </div>
          <?php endif; ?>
        </article>
      <?php endforeach; ?>
    </div>

    <?php if ($doctor_review_total > $initial_review_limit): ?>
      <div class="medic-lite-load-wrap">
        <button
          type="button"
          class="medic-lite-load-more"
          id="doctorReviewLoadMore"
          data-step="<?= e((string)$review_load_step) ?>"
        >
          Load More Reviews
        </button>
      </div>
    <?php endif; ?>

  <?php else: ?>
    <div class="medic-lite-empty">
      <strong><?= e($review_empty_title) ?></strong>
      Patient reviews will appear here after admin approval.

      <?php if ($doctor_review_url !== '#'): ?>
        <br><br>
        <a href="<?= e($doctor_review_url) ?>" class="medic-lite-review-btn"><?= e($review_button_text) ?></a>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</section>

<script src="<?= e(site_url('assets/js/doctor-reviews.js')) ?>?v=<?= e($doctor_reviews_version) ?>" defer></script>
