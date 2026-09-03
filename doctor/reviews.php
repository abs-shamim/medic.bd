<?php
/*
|--------------------------------------------------------------------------
| Doctor Reviews Section
|--------------------------------------------------------------------------
| Self-contained reviews file.
| Old reviews display logic is kept unchanged.
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/../includes/functions.php';

$slug = $_GET['slug'] ?? '';

if (!isset($doctor) || !is_array($doctor)) {
    $doctor = function_exists('get_doctor_by_slug') ? get_doctor_by_slug($slug) : null;
}

if (!$doctor || !is_array($doctor)) {
    return;
}

/*
|--------------------------------------------------------------------------
| Review Include Path
|--------------------------------------------------------------------------
| Old logic: include ../includes/reviews.php
|--------------------------------------------------------------------------
*/

$reviews_file = __DIR__ . '/../includes/reviews.php';

if (!file_exists($reviews_file)) {
    return;
}

?>

<style>
.medic-reviews-section {
    background: #ffffff;
    border: 1px solid #d0d7de;
    border-radius: 14px;
    padding: 22px;
    margin-bottom: 20px;
    box-shadow: 0 1px 0 rgba(27, 31, 36, 0.04);
}

.medic-reviews-section h2,
.medic-reviews-section h3 {
    color: #24292f;
}

.medic-reviews-section p {
    color: #57606a;
}
</style>

<section class="medic-reviews-section">
    <?php include $reviews_file; ?>
</section>