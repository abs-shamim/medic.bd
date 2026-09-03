<?php
require_once __DIR__ . '/includes/header.php';

if (!table_exists('reviews')) {
    echo '<h1 style="margin-bottom:18px;color:#0f172a;">Reviews</h1>';
    echo '<div class="card"><p>The reviews table was not found. Please import the latest database SQL first.</p></div>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

$has_hospital_id = column_exists('reviews', 'hospital_id');

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
            WHERE doctor_id=:doctor_id
            AND status='approved'
        ");
        $stmt->execute([':doctor_id' => $doctor_id]);
        $stats = $stmt->fetch() ?: ['total_reviews' => 0, 'avg_rating' => 0];

        $update = $pdo->prepare("
            UPDATE doctors
            SET reviews_count=:reviews_count, rating=:rating
            WHERE id=:doctor_id
        ");
        $update->execute([
            ':reviews_count' => (int)$stats['total_reviews'],
            ':rating' => round((float)$stats['avg_rating'], 1),
            ':doctor_id' => $doctor_id,
        ]);
    } catch (Throwable $e) {
        return;
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
            WHERE hospital_id=:hospital_id
            AND status='approved'
        ");
        $stmt->execute([':hospital_id' => $hospital_id]);
        $avg_rating = round((float)$stmt->fetchColumn(), 1);

        $update = $pdo->prepare("
            UPDATE hospitals
            SET rating=:rating
            WHERE id=:hospital_id
        ");
        $update->execute([
            ':rating' => $avg_rating,
            ':hospital_id' => $hospital_id,
        ]);
    } catch (Throwable $e) {
        return;
    }
}

if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];

    if ($id > 0) {
        $stmt = $pdo->prepare("SELECT doctor_id" . ($has_hospital_id ? ", hospital_id" : "") . " FROM reviews WHERE id=:id");
        $stmt->execute([':id' => $id]);
        $old = $stmt->fetch();

        $delete = $pdo->prepare("DELETE FROM reviews WHERE id=:id");
        $delete->execute([':id' => $id]);

        if ($old) {
            admin_refresh_doctor_review_stats((int)($old['doctor_id'] ?? 0));

            if ($has_hospital_id) {
                admin_refresh_hospital_review_stats((int)($old['hospital_id'] ?? 0));
            }
        }

        flash('success', 'Review deleted successfully.');
    }

    redirect('reviews.php');
}

$sql = "
    SELECT r.*, d.name AS doctor_name" . ($has_hospital_id ? ", h.name AS hospital_name" : ", NULL AS hospital_name") . "
    FROM reviews r
    LEFT JOIN doctors d ON d.id = r.doctor_id
";

if ($has_hospital_id) {
    $sql .= " LEFT JOIN hospitals h ON h.id = r.hospital_id ";
}

$sql .= " ORDER BY r.id DESC";
$reviews = $pdo->query($sql)->fetchAll();
?>

<div style="display:flex;align-items:center;justify-content:space-between;gap:15px;margin-bottom:18px;">
  <div>
    <h1 style="margin:0;color:#0f172a;">Reviews</h1>
    <p style="margin:7px 0 0;color:#64748b;">Manage doctor and hospital reviews from one place.</p>
  </div>

  <a href="review-form.php" class="btn btn-primary">Add New Review</a>
</div>

<?php show_flash(); ?>

<?php if (!$has_hospital_id): ?>
  <div class="card" style="margin-bottom:18px;">
    <strong>Hospital review support is not active yet.</strong>
    <p>Run <code>admin_reviews_update.sql</code> if you want to attach reviews directly to hospitals too.</p>
  </div>
<?php endif; ?>

<table class="admin-table">
  <thead>
    <tr>
      <th>Reviewer</th>
      <th>Doctor</th>
      <?php if ($has_hospital_id): ?>
        <th>Hospital</th>
      <?php endif; ?>
      <th>Rating</th>
      <th>Status</th>
      <th style="width:180px;">Actions</th>
    </tr>
  </thead>

  <tbody>
    <?php if ($reviews): ?>
      <?php foreach ($reviews as $review): ?>
        <tr>
          <td><?= e($review['name'] ?? '') ?></td>
          <td><?= e($review['doctor_name'] ?? 'N/A') ?></td>

          <?php if ($has_hospital_id): ?>
            <td><?= e($review['hospital_name'] ?? 'N/A') ?></td>
          <?php endif; ?>

          <td><?= e((string)($review['rating'] ?? 0)) ?> ★</td>

          <td>
            <?php if (($review['status'] ?? '') === 'approved'): ?>
              <span style="display:inline-block;padding:5px 10px;border-radius:999px;background:#dcfce7;color:#166534;font-size:12px;">Approved</span>
            <?php elseif (($review['status'] ?? '') === 'pending'): ?>
              <span style="display:inline-block;padding:5px 10px;border-radius:999px;background:#fef9c3;color:#854d0e;font-size:12px;">Pending</span>
            <?php else: ?>
              <span style="display:inline-block;padding:5px 10px;border-radius:999px;background:#fee2e2;color:#991b1b;font-size:12px;">Rejected</span>
            <?php endif; ?>
          </td>

          <td>
            <div class="admin-actions">
              <a class="btn btn-outline small-btn" href="review-form.php?id=<?= e((string)$review['id']) ?>">Edit</a>

              <a
                class="btn btn-danger small-btn"
                href="reviews.php?delete=<?= e((string)$review['id']) ?>"
                onclick="return confirm('Delete this review?')"
              >Delete</a>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
    <?php else: ?>
      <tr>
        <td colspan="<?= $has_hospital_id ? '6' : '5' ?>" style="text-align:center;color:#64748b;padding:25px;">
          No reviews found.
        </td>
      </tr>
    <?php endif; ?>
  </tbody>
</table>

<?php require_once __DIR__ . '/includes/footer.php'; ?>