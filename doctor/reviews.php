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

<link rel="stylesheet" href="<?= e(site_url('assets/css/doctor-reviews-wrapper.css')) ?>">

<section class="medic-reviews-section">
    <?php include $reviews_file; ?>
</section>