<?php
/*
|--------------------------------------------------------------------------
| Add this block inside /user/includes/header.php navigation.
| The menu is visible only to an approved doctor with a connected profile.
|--------------------------------------------------------------------------
*/

$prescription_user_type = function_exists('user_profile_type')
    ? user_profile_type($user)
    : (string)($user['user_type'] ?? '');

$prescription_doctor_id = (int)($user['claimed_doctor_id'] ?? 0);
$prescription_has_claim = function_exists('user_has_claim')
    ? user_has_claim($user)
    : $prescription_doctor_id > 0;

$can_use_prescription =
    $prescription_user_type === 'doctor'
    && $prescription_has_claim
    && $prescription_doctor_id > 0;
?>

<?php if ($can_use_prescription): ?>
    <a
        href="<?= htmlspecialchars(function_exists('site_url') ? site_url('prescription/') : '/prescription/', ENT_QUOTES, 'UTF-8') ?>"
        class="<?= str_contains((string)($_SERVER['REQUEST_URI'] ?? ''), '/prescription/') ? 'active' : '' ?>"
    >
        <i class="fa-solid fa-prescription" aria-hidden="true"></i>
        <span>Prescription</span>
    </a>
<?php endif; ?>
