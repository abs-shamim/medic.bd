<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$q = prescription_clean_text($_GET['q'] ?? '', 100);
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

$where = ['p.doctor_id = :doctor_id'];
$params = [':doctor_id' => $doctor_id];

if ($q !== '') {
    $search_parts = [
        'p.name LIKE :search_name',
        'p.phone LIKE :search_phone',
        'p.patient_code LIKE :search_code',
    ];
    $params[':search_name'] = '%' . $q . '%';
    $params[':search_phone'] = '%' . $q . '%';
    $params[':search_code'] = '%' . $q . '%';

    $phone_digits = prescription_normalize_phone($q);
    if (prescription_strlen($phone_digits) >= 3) {
        $search_parts[] = 'p.phone_normalized LIKE :search_digits';
        $params[':search_digits'] = '%' . $phone_digits . '%';
    }

    $where[] = '(' . implode(' OR ', $search_parts) . ')';
}

$where_sql = implode(' AND ', $where);

$count_stmt = $pdo->prepare("SELECT COUNT(*) FROM prescription_patients p WHERE {$where_sql}");
$count_stmt->execute($params);
$total = (int)$count_stmt->fetchColumn();
$total_pages = max(1, (int)ceil($total / $per_page));

$stmt = $pdo->prepare("
    SELECT
        p.*,
        COUNT(pr.id) AS prescription_count,
        MAX(pr.visit_date) AS last_visit
    FROM prescription_patients p
    LEFT JOIN prescriptions pr
      ON pr.patient_id = p.id
     AND pr.doctor_id = p.doctor_id
    WHERE {$where_sql}
    GROUP BY p.id
    ORDER BY p.id DESC
    LIMIT {$per_page} OFFSET {$offset}
");
$stmt->execute($params);
$patients = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'Patients';
require_once __DIR__ . '/includes/header.php';
?>

<section class="rx-page-hero">
    <div>
        <div class="rx-breadcrumb">
            <a href="<?= prescription_e(prescription_url()) ?>">Prescriptions</a>
            <span>/</span>
            <span>Patients</span>
        </div>
        <h1>Patient Directory</h1>
        <p>Patients are saved once, reused by name or mobile number, and linked to their complete prescription history.</p>
    </div>
    <div class="rx-hero-actions">
        <a class="rx-btn rx-btn-white" href="<?= prescription_e(prescription_url()) ?>">All Prescriptions</a>
        <a class="rx-btn rx-btn-primary" href="<?= prescription_e(prescription_url('appointment.php')) ?>">+ Add Patient</a>
    </div>
</section>

<section class="rx-card">
    <div class="rx-card-head">
        <div>
            <h2>All Patients</h2>
            <p><?= prescription_e((string)$total) ?> patient record(s) found.</p>
        </div>
    </div>
    <div class="rx-card-body">
        <form method="GET" class="rx-filter-bar" style="grid-template-columns:minmax(240px,1fr) auto;">
            <div class="rx-field">
                <label>Search Patient</label>
                <input type="search" name="q" value="<?= prescription_e($q) ?>" placeholder="Name, phone or patient code">
            </div>
            <div class="rx-actions">
                <button class="rx-btn rx-btn-primary" type="submit">Search</button>
                <a class="rx-btn rx-btn-soft" href="<?= prescription_e(prescription_url('patients.php')) ?>">Reset</a>
            </div>
        </form>

        <?php if (!$patients): ?>
            <div class="rx-empty">
                <strong>No patient found</strong>
                <span>Add a patient for an appointment or save a prescription to create a patient record.</span>
            </div>
        <?php else: ?>
            <div class="rx-table-wrap">
                <table class="rx-table">
                    <thead>
                    <tr>
                        <th>Patient</th>
                        <th>Phone</th>
                        <th>Age / Gender</th>
                        <th>Prescriptions</th>
                        <th>Last Visit</th>
                        <th>Action</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($patients as $patient): ?>
                        <tr>
                            <td>
                                <a class="rx-main-link" href="<?= prescription_e(prescription_url('patient-view.php?id=' . (int)$patient['id'])) ?>">
                                    <?= prescription_e($patient['name'] ?? '') ?>
                                </a>
                                <span class="rx-subtext"><?= prescription_e($patient['patient_code'] ?? '') ?></span>
                            </td>
                            <td><?= prescription_e($patient['phone'] ?: '—') ?></td>
                            <td><?= prescription_e(trim(($patient['age'] ?: '—') . ' / ' . ($patient['gender'] ?: '—'))) ?></td>
                            <td><?= prescription_e((string)($patient['prescription_count'] ?? 0)) ?></td>
                            <td><?= prescription_e(prescription_date($patient['last_visit'] ?? '')) ?></td>
                            <td>
                                <div class="rx-inline-actions">
                                    <a class="rx-btn rx-btn-soft rx-btn-sm" href="<?= prescription_e(prescription_url('patient-view.php?id=' . (int)$patient['id'])) ?>">View</a>
                                    <a class="rx-btn rx-btn-primary rx-btn-sm" href="<?= prescription_e(prescription_url('new.php?patient_id=' . (int)$patient['id'])) ?>">New Rx</a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($total_pages > 1): ?>
                <div class="rx-pagination">
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <?php $query = http_build_query(['q' => $q, 'page' => $i]); ?>
                        <?php if ($i === $page): ?>
                            <span class="active"><?= $i ?></span>
                        <?php else: ?>
                            <a href="<?= prescription_e(prescription_url('patients.php?' . $query)) ?>"><?= $i ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
