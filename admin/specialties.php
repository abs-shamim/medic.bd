<?php
ob_start();

require_once __DIR__ . '/includes/header.php';

/*
|--------------------------------------------------------------------------
| Create CSRF Token
|--------------------------------------------------------------------------
*/
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/*
|--------------------------------------------------------------------------
| Delete Specialty
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_specialty'])) {
    $specialtyId = (int) ($_POST['specialty_id'] ?? 0);
    $csrfToken   = $_POST['csrf_token'] ?? '';

    try {
        if (
            empty($_SESSION['csrf_token']) ||
            !hash_equals($_SESSION['csrf_token'], $csrfToken)
        ) {
            flash('error', 'Invalid request. Please try again.');
            redirect('specialties.php');
            exit;
        }

        if ($specialtyId <= 0) {
            flash('error', 'Invalid specialty selected.');
            redirect('specialties.php');
            exit;
        }

        $specialtyCheckStmt = $pdo->prepare("
            SELECT id, name
            FROM specialties
            WHERE id = :id
            LIMIT 1
        ");

        $specialtyCheckStmt->execute([
            ':id' => $specialtyId
        ]);

        $selectedSpecialty = $specialtyCheckStmt->fetch(PDO::FETCH_ASSOC);

        if (!$selectedSpecialty) {
            flash('error', 'This specialty was not found.');
            redirect('specialties.php');
            exit;
        }

        $doctorCheckStmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM doctors
            WHERE specialty_id = :specialty_id
        ");

        $doctorCheckStmt->execute([
            ':specialty_id' => $specialtyId
        ]);

        $doctorCount = (int) $doctorCheckStmt->fetchColumn();

        if ($doctorCount > 0) {
            flash(
                'error',
                '"' . ($selectedSpecialty['name'] ?? 'This specialty') . '" cannot be deleted because ' .
                $doctorCount . ' doctor(s) are currently assigned to it.'
            );

            redirect('specialties.php');
            exit;
        }

        $deleteStmt = $pdo->prepare("
            DELETE FROM specialties
            WHERE id = :id
        ");

        $deleteStmt->execute([
            ':id' => $specialtyId
        ]);

        if ($deleteStmt->rowCount() > 0) {
            flash(
                'success',
                '"' . ($selectedSpecialty['name'] ?? 'Specialty') . '" deleted successfully.'
            );
        } else {
            flash('error', 'Specialty could not be deleted. Please try again.');
        }
    } catch (Throwable $e) {
        error_log('Specialty delete error: ' . $e->getMessage());
        flash('error', 'Something went wrong while deleting the specialty. Please try again.');
    }

    redirect('specialties.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| Summary Data
|--------------------------------------------------------------------------
*/
$summaryStmt = $pdo->query("
    SELECT
        COUNT(*) AS total_specialties,
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) AS active_specialties,
        SUM(CASE WHEN status != 'active' OR status IS NULL THEN 1 ELSE 0 END) AS inactive_specialties
    FROM specialties
");

$summary = $summaryStmt->fetch(PDO::FETCH_ASSOC);

$totalSpecialties    = (int) ($summary['total_specialties'] ?? 0);
$activeSpecialties   = (int) ($summary['active_specialties'] ?? 0);
$inactiveSpecialties = (int) ($summary['inactive_specialties'] ?? 0);

/*
|--------------------------------------------------------------------------
| Get All Specialties With Doctor Count, A-Z
|--------------------------------------------------------------------------
*/
$stmt = $pdo->query("
    SELECT
        s.id,
        s.name,
        s.name_bn,
        s.slug,
        s.status,
        s.created_at,
        COUNT(d.id) AS doctor_count
    FROM specialties s
    LEFT JOIN doctors d
        ON d.specialty_id = s.id
    GROUP BY
        s.id,
        s.name,
        s.name_bn,
        s.slug,
        s.status,
        s.created_at
    ORDER BY s.name ASC
");

$specialties = $stmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Doctors Shown In The Table
|--------------------------------------------------------------------------
| This total is calculated from the same doctor_count value displayed
| in the Doctors column, so it excludes doctors without a valid specialty.
*/
$totalDoctors = array_sum(
    array_map(
        static fn(array $specialty): int => (int) ($specialty['doctor_count'] ?? 0),
        $specialties
    )
);
?>

<div style="display:flex;align-items:center;justify-content:space-between;gap:15px;margin-bottom:18px;flex-wrap:wrap;">
    <div>
        <h1 style="margin:0;color:#0f172a;">Specialties</h1>
        <p style="margin:6px 0 0;color:#64748b;font-size:14px;">
            Manage medical specialty categories and doctors.
        </p>
    </div>

    <a href="specialty-form.php" class="btn btn-primary">Add New Specialty</a>
</div>

<?php show_flash(); ?>

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:16px;margin-bottom:22px;">
    <div style="background:#ffffff;border:1px solid #e2e8f0;border-radius:12px;padding:18px;box-shadow:0 2px 8px rgba(15,23,42,.04);">
        <div style="font-size:13px;color:#64748b;margin-bottom:8px;">Total Specialty Categories</div>
        <div style="font-size:28px;font-weight:700;color:#0f172a;"><?= $totalSpecialties ?></div>
    </div>

    <div style="background:#ffffff;border:1px solid #e2e8f0;border-radius:12px;padding:18px;box-shadow:0 2px 8px rgba(15,23,42,.04);">
        <div style="font-size:13px;color:#64748b;margin-bottom:8px;">Doctors</div>
        <div style="font-size:28px;font-weight:700;color:#2563eb;"><?= $totalDoctors ?></div>
    </div>

    <div style="background:#ffffff;border:1px solid #e2e8f0;border-radius:12px;padding:18px;box-shadow:0 2px 8px rgba(15,23,42,.04);">
        <div style="font-size:13px;color:#64748b;margin-bottom:8px;">Active Specialties</div>
        <div style="font-size:28px;font-weight:700;color:#16a34a;"><?= $activeSpecialties ?></div>
    </div>

    <div style="background:#ffffff;border:1px solid #e2e8f0;border-radius:12px;padding:18px;box-shadow:0 2px 8px rgba(15,23,42,.04);">
        <div style="font-size:13px;color:#64748b;margin-bottom:8px;">Inactive Specialties</div>
        <div style="font-size:28px;font-weight:700;color:#dc2626;"><?= $inactiveSpecialties ?></div>
    </div>
</div>

<div style="overflow-x:auto;">
    <table class="admin-table">
        <thead>
            <tr>
                <th style="width:70px;text-align:center;">SL</th>
                <th>English Name</th>
                <th>Bangla Name</th>
                <th>Slug</th>
                <th style="text-align:center;">Doctors</th>
                <th>Status</th>
                <th style="width:300px;">Actions</th>
            </tr>
        </thead>

        <tbody>
            <?php if (!empty($specialties)): ?>
                <?php foreach ($specialties as $index => $specialty): ?>
                    <?php
                    $serialNumber  = $index + 1;
                    $specialtyId   = (int) ($specialty['id'] ?? 0);
                    $specialtySlug = trim((string) ($specialty['slug'] ?? ''));
                    $doctorCount   = (int) ($specialty['doctor_count'] ?? 0);
                    $doctorPageUrl = 'https://medic.bd/doctors/' . rawurlencode($specialtySlug);
                    ?>

                    <tr>
                        <td style="text-align:center;font-weight:700;color:#475569;"><?= $serialNumber ?></td>

                        <td>
                            <strong style="color:#0f172a;"><?= e($specialty['name'] ?? '') ?></strong>
                        </td>

                        <td>
                            <?php if (!empty($specialty['name_bn'])): ?>
                                <?= e($specialty['name_bn']) ?>
                            <?php else: ?>
                                <span style="color:#94a3b8;">Not added</span>
                            <?php endif; ?>
                        </td>

                        <td>
                            <?php if (!empty($specialtySlug)): ?>
                                <code style="background:#f1f5f9;color:#334155;padding:4px 8px;border-radius:6px;font-size:12px;">
                                    <?= e($specialtySlug) ?>
                                </code>
                            <?php else: ?>
                                <span style="color:#94a3b8;">No slug</span>
                            <?php endif; ?>
                        </td>

                        <td style="text-align:center;">
                            <span style="display:inline-flex;align-items:center;justify-content:center;min-width:38px;height:30px;padding:0 10px;border-radius:999px;background:#eff6ff;color:#1d4ed8;font-size:13px;font-weight:700;">
                                <?= $doctorCount ?>
                            </span>
                        </td>

                        <td>
                            <?php if (($specialty['status'] ?? '') === 'active'): ?>
                                <span style="display:inline-block;padding:5px 10px;border-radius:999px;background:#dcfce7;color:#166534;font-size:12px;font-weight:600;">Active</span>
                            <?php else: ?>
                                <span style="display:inline-block;padding:5px 10px;border-radius:999px;background:#fee2e2;color:#991b1b;font-size:12px;font-weight:600;">Inactive</span>
                            <?php endif; ?>
                        </td>

                        <td>
                            <div class="admin-actions" style="display:flex;align-items:center;gap:7px;flex-wrap:wrap;">
                                <?php if (!empty($specialtySlug)): ?>
                                    <a class="btn btn-outline small-btn" href="<?= e($doctorPageUrl) ?>" target="_blank" rel="noopener noreferrer">View Doctors</a>
                                <?php endif; ?>

                                <a class="btn btn-outline small-btn" href="specialty-form.php?id=<?= $specialtyId ?>">Edit</a>

                                <form method="POST" action="specialties.php" style="display:inline;" onsubmit="return confirm('Delete this specialty permanently? This action cannot be undone.')">
                                    <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                                    <input type="hidden" name="specialty_id" value="<?= $specialtyId ?>">
                                    <button type="submit" name="delete_specialty" class="btn btn-danger small-btn">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="7" style="text-align:center;color:#64748b;padding:30px;">No specialties found.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

<?php ob_end_flush(); ?>
