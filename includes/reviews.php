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

if (!function_exists('doctor_review_setting_color')) {
    function doctor_review_setting_color(string $key, string $default): string
    {
        $value = doctor_review_site_setting($key, $default);

        if (preg_match('/^#[0-9a-fA-F]{6}$/', $value)) {
            return $value;
        }

        return $default;
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

$review_primary_color = doctor_review_setting_color('primary_color', '#0969da');
$review_accent_color = doctor_review_setting_color('accent_color', '#2da44e');
$review_body_background = doctor_review_setting_color('body_background_color', '#f6f8fa');
?>

<style>
  :root {
    --review-primary: <?= e($review_primary_color) ?>;
    --review-accent: <?= e($review_accent_color) ?>;
    --review-bg: <?= e($review_body_background) ?>;
    --review-dark: var(--review-dark);
    --review-muted: var(--review-muted);
    --review-border: var(--review-border);
    --review-border-soft: var(--review-border-soft);
    --review-card: var(--review-card);
  }

  .medic-lite-reviews {
    background: var(--review-card);
    border: 1px solid var(--review-border);
    border-radius: 14px;
    padding: 22px;
    box-shadow: 0 1px 0 rgba(27, 31, 36, 0.04);
  }

  .medic-lite-reviews * {
    box-sizing: border-box;
  }

  .medic-lite-review-top {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
    margin-bottom: 18px;
    padding-bottom: 16px;
    border-bottom: 1px solid var(--review-border-soft);
  }

  .medic-lite-review-title h2 {
    margin: 0;
    color: var(--review-dark);
    font-size: 24px;
    line-height: 1.2;
    letter-spacing: -0.03em;
    font-weight: 800;
  }

  .medic-lite-review-title p {
    margin: 6px 0 0;
    color: var(--review-muted);
    font-size: 14px;
    line-height: 1.6;
  }

  .medic-lite-review-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 38px;
    padding: 0 14px;
    border-radius: 6px;
    border: 1px solid rgba(27, 31, 36, 0.15);
    background: var(--review-accent);
    color: var(--review-card);
    font-size: 14px;
    font-weight: 700;
    text-decoration: none;
    white-space: nowrap;
  }

  .medic-lite-review-btn:hover {
    background: var(--review-accent);
    color: var(--review-card);
  }

  .medic-lite-summary {
    display: grid;
    grid-template-columns: 180px minmax(0, 1fr);
    gap: 18px;
    align-items: center;
    margin-bottom: 20px;
    padding: 16px;
    background: var(--review-bg);
    border: 1px solid var(--review-border-soft);
    border-radius: 12px;
  }

  .medic-lite-score {
    text-align: center;
    padding: 14px;
    background: var(--review-card);
    border: 1px solid var(--review-border-soft);
    border-radius: 10px;
  }

  .medic-lite-score strong {
    display: block;
    color: var(--review-dark);
    font-size: 42px;
    line-height: 1;
    letter-spacing: -0.05em;
    font-weight: 900;
  }

  .medic-lite-score span {
    display: block;
    margin-top: 8px;
    color: #bf8700;
    font-size: 17px;
    letter-spacing: 0.03em;
    font-weight: 800;
  }

  .medic-lite-score small {
    display: block;
    margin-top: 6px;
    color: var(--review-muted);
    font-size: 12px;
    font-weight: 700;
  }

  .medic-lite-bars {
    display: grid;
    gap: 8px;
  }

  .medic-lite-bar-row {
    display: grid;
    grid-template-columns: 52px minmax(0, 1fr) 32px;
    gap: 10px;
    align-items: center;
    color: var(--review-muted);
    font-size: 13px;
    font-weight: 700;
  }

  .medic-lite-bar {
    height: 8px;
    border-radius: 999px;
    overflow: hidden;
    background: var(--review-border-soft);
  }

  .medic-lite-bar i {
    display: block;
    height: 100%;
    border-radius: inherit;
    background: #bf8700;
  }

  .medic-lite-bar-row em {
    color: var(--review-dark);
    font-style: normal;
    text-align: right;
  }

  .medic-lite-review-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    margin: 18px 0 12px;
  }

  .medic-lite-review-heading h3 {
    margin: 0;
    color: var(--review-dark);
    font-size: 17px;
    line-height: 1.3;
    font-weight: 800;
  }

  .medic-lite-review-heading span {
    display: inline-flex;
    align-items: center;
    min-height: 26px;
    padding: 4px 10px;
    border-radius: 999px;
    background: var(--review-bg);
    border: 1px solid var(--review-border);
    color: var(--review-muted);
    font-size: 12px;
    font-weight: 800;
  }

  .medic-lite-review-list {
    display: grid;
    gap: 12px;
  }

  .medic-lite-review-item {
    padding: 16px;
    border: 1px solid var(--review-border-soft);
    border-radius: 12px;
    background: var(--review-card);
    transition: 0.18s ease;
  }

  .medic-lite-review-item:hover {
    border-color: #8c959f;
    background: #fbfcfe;
  }

  .medic-lite-review-item.is-hidden {
    display: none;
  }

  .medic-lite-review-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 4px;
  }

  .medic-lite-review-name {
    color: var(--review-dark);
    font-size: 14px;
    font-weight: 800;
  }

  .medic-lite-review-date {
    color: #6e7781;
    font-size: 13px;
    font-weight: 600;
  }

  .medic-lite-stars {
    color: #bf8700;
    font-size: 14px;
    font-weight: 800;
    white-space: nowrap;
    letter-spacing: 0.02em;
  }

  .medic-lite-review-item h4 {
    margin: 10px 0 8px;
    color: var(--review-dark);
    font-size: 16px;
    line-height: 1.35;
    font-weight: 800;
  }

  .medic-lite-service-line {
    margin: 0 0 8px;
    color: var(--review-muted);
    font-size: 13px;
    line-height: 1.6;
    font-weight: 600;
  }

  .medic-lite-service-line span {
    color: var(--review-dark);
    font-weight: 700;
  }

  .medic-lite-review-text {
    margin: 0;
    color: var(--review-muted);
    font-size: 14px;
    line-height: 1.75;
  }

  .medic-lite-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 7px;
    margin-top: 10px;
  }

  .medic-lite-meta span {
    display: inline-flex;
    align-items: center;
    min-height: 24px;
    padding: 3px 8px;
    border-radius: 999px;
    background: #ddf4ff;
    border: 1px solid rgba(9, 105, 218, 0.18);
    color: var(--review-primary);
    font-size: 12px;
    font-weight: 700;
  }

  .medic-lite-meta .verified {
    background: #dafbe1;
    border-color: rgba(26, 127, 55, 0.22);
    color: #1a7f37;
  }

  .medic-lite-meta .verified::before {
    content: "✓";
    margin-right: 5px;
  }

  .medic-lite-empty {
    padding: 22px;
    border: 1px dashed var(--review-border);
    border-radius: 12px;
    background: var(--review-bg);
    text-align: center;
    color: var(--review-muted);
    line-height: 1.7;
  }

  .medic-lite-empty strong {
    display: block;
    margin-bottom: 5px;
    color: var(--review-dark);
    font-size: 16px;
  }

  .medic-lite-load-wrap {
    display: flex;
    justify-content: center;
    margin-top: 16px;
  }

  .medic-lite-load-more {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 38px;
    padding: 0 15px;
    border-radius: 6px;
    border: 1px solid var(--review-border);
    background: var(--review-card);
    color: var(--review-primary);
    font-size: 14px;
    font-weight: 800;
    cursor: pointer;
  }

  .medic-lite-load-more:hover {
    background: var(--review-bg);
  }

  @media (max-width: 760px) {
    .medic-lite-reviews {
      padding: 16px;
      border-radius: 12px;
    }

    .medic-lite-review-top {
      flex-direction: column;
    }

    .medic-lite-summary {
      grid-template-columns: 1fr;
      padding: 12px;
    }

    .medic-lite-review-item {
      padding: 13px;
    }

    .medic-lite-review-head {
      flex-direction: column;
      gap: 6px;
    }

    .medic-lite-review-btn,
    .medic-lite-load-more {
      width: 100%;
    }
  }
</style>

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
              <i style="width: <?= (int)$percent ?>%;"></i>
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

<script>
  document.addEventListener('DOMContentLoaded', function () {
    const loadMoreButton = document.getElementById('doctorReviewLoadMore');

    if (!loadMoreButton) {
      return;
    }

    const reviewItems = Array.from(document.querySelectorAll('[data-review-item]'));
    const step = parseInt(loadMoreButton.getAttribute('data-step') || '5', 10);

    loadMoreButton.addEventListener('click', function () {
      const hiddenItems = reviewItems.filter(function (item) {
        return item.classList.contains('is-hidden');
      });

      hiddenItems.slice(0, step).forEach(function (item) {
        item.classList.remove('is-hidden');
      });

      const remainingHiddenItems = reviewItems.filter(function (item) {
        return item.classList.contains('is-hidden');
      });

      if (remainingHiddenItems.length === 0) {
        loadMoreButton.style.display = 'none';
      }
    });
  });
</script>
