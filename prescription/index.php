<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_action'] ?? '') === 'delete_prescription') {
    if (!prescription_verify_csrf($_POST['csrf_token'] ?? null)) {
        prescription_flash('error', 'Security verification failed. Please try again.');
        prescription_redirect();
    }

    try {
        prescription_delete_record($pdo, (int)($_POST['prescription_id'] ?? 0), $doctor_id);
        prescription_flash('success', 'Prescription deleted successfully.');
    } catch (Throwable $e) {
        prescription_flash('error', 'Prescription could not be deleted.');
    }

    prescription_redirect();
}

$q = prescription_clean_text($_GET['q'] ?? '', 100);
$status = prescription_clean_text($_GET['status'] ?? '', 20);
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 15;
$offset = ($page - 1) * $per_page;

$where = ['doctor_id = :doctor_id'];
$params = [':doctor_id' => $doctor_id];

if ($q !== '') {
    $where[] = '(prescription_no LIKE :search OR patient_name LIKE :search OR patient_phone LIKE :search OR diagnosis LIKE :search)';
    $params[':search'] = '%' . $q . '%';
}

if (in_array($status, ['active', 'draft'], true)) {
    $where[] = 'status = :status';
    $params[':status'] = $status;
}

$where_sql = implode(' AND ', $where);

$count_stmt = $pdo->prepare("SELECT COUNT(*) FROM prescriptions WHERE {$where_sql}");
$count_stmt->execute($params);
$total = (int)$count_stmt->fetchColumn();
$total_pages = max(1, (int)ceil($total / $per_page));

$list_stmt = $pdo->prepare("
    SELECT *
    FROM prescriptions
    WHERE {$where_sql}
    ORDER BY visit_date DESC, id DESC
    LIMIT {$per_page} OFFSET {$offset}
");
$list_stmt->execute($params);
$prescriptions = $list_stmt->fetchAll(PDO::FETCH_ASSOC);

$stats_stmt = $pdo->prepare("
    SELECT
        COUNT(*) AS total_count,
        SUM(status = 'active') AS active_count,
        SUM(status = 'draft') AS draft_count,
        COUNT(DISTINCT patient_id) AS patient_count
    FROM prescriptions
    WHERE doctor_id = :doctor_id
");
$stats_stmt->execute([':doctor_id' => $doctor_id]);
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC) ?: [];

$page_title = 'Prescriptions';
require_once __DIR__ . '/includes/header.php';
?>

<section class="rx-page-hero">
    <div>
        <div class="rx-breadcrumb">
            <a href="<?= prescription_e(prescription_user_url('dashboard.php')) ?>">User Dashboard</a>
            <span>/</span>
            <span>Prescriptions</span>
        </div>
        <h1>Prescription Management</h1>
        <p>Create, edit, review and print secure prescriptions. Every record is restricted to your approved doctor profile.</p>
    </div>

    <div class="rx-hero-actions">
        <a class="rx-btn rx-btn-white" href="<?= prescription_e(prescription_library_list_url('medicine')) ?>">Medicine Library</a>
        <a class="rx-btn rx-btn-white" href="<?= prescription_e(prescription_url('patients.php')) ?>">View Patients</a>
        <a class="rx-btn rx-btn-primary" href="<?= prescription_e(prescription_url('new.php')) ?>">+ New Prescription</a>
    </div>
</section>

<div class="rx-stats">
    <div class="rx-stat-card">
        <span>Total Prescriptions</span>
        <strong><?= prescription_e((string)($stats['total_count'] ?? 0)) ?></strong>
        <small>All saved records</small>
    </div>
    <div class="rx-stat-card">
        <span>Completed</span>
        <strong><?= prescription_e((string)($stats['active_count'] ?? 0)) ?></strong>
        <small>Ready to view and print</small>
    </div>
    <div class="rx-stat-card">
        <span>Drafts</span>
        <strong><?= prescription_e((string)($stats['draft_count'] ?? 0)) ?></strong>
        <small>Work in progress</small>
    </div>
    <div class="rx-stat-card">
        <span>Patients</span>
        <strong><?= prescription_e((string)($stats['patient_count'] ?? 0)) ?></strong>
        <small>Patients with prescriptions</small>
    </div>
</div>

<section class="rx-card">
    <div class="rx-card-head">
        <div>
            <h2>All Prescriptions</h2>
            <p>Search by prescription number, patient name, phone number or diagnosis.</p>
        </div>
    </div>

    <div class="rx-card-body">
        <form method="GET" class="rx-filter-bar">
            <div class="rx-field">
                <label>Search</label>
                <input type="search" name="q" value="<?= prescription_e($q) ?>" placeholder="Prescription no, patient, phone or diagnosis">
            </div>
            <div class="rx-field">
                <label>Status</label>
                <select name="status">
                    <option value="">All Statuses</option>
                    <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Completed</option>
                    <option value="draft" <?= $status === 'draft' ? 'selected' : '' ?>>Draft</option>
                </select>
            </div>
            <div class="rx-actions">
                <button type="submit" class="rx-btn rx-btn-primary">Filter</button>
                <a href="<?= prescription_e(prescription_url()) ?>" class="rx-btn rx-btn-soft">Reset</a>
            </div>
        </form>

        <?php if (!$prescriptions): ?>
            <div class="rx-empty">
                <strong>No prescription found</strong>
                <span>Create your first prescription or change the search filter.</span>
            </div>
        <?php else: ?>
            <div class="rx-table-wrap">
                <table class="rx-table">
                    <thead>
                    <tr>
                        <th>Prescription</th>
                        <th>Patient</th>
                        <th>Visit Date</th>
                        <th>Diagnosis</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($prescriptions as $row): ?>
                        <tr>
                            <td>
                                <a class="rx-main-link" href="<?= prescription_e(prescription_url('view.php?id=' . (int)$row['id'])) ?>">
                                    <?= prescription_e($row['prescription_no'] ?? '') ?>
                                </a>
                                <span class="rx-subtext">Created <?= prescription_e(prescription_date($row['created_at'] ?? '', 'd M Y, h:i A')) ?></span>
                            </td>
                            <td>
                                <strong><?= prescription_e($row['patient_name'] ?? '') ?></strong>
                                <span class="rx-subtext"><?= prescription_e($row['patient_phone'] ?: 'No phone') ?></span>
                            </td>
                            <td><?= prescription_e(prescription_date($row['visit_date'] ?? '')) ?></td>
                            <td>
                                <?= prescription_e(prescription_excerpt($row['diagnosis'] ?? '—', 65)) ?>
                            </td>
                            <td>
                                <span class="rx-status <?= prescription_e($row['status'] ?? 'draft') ?>">
                                    <?= prescription_e(prescription_status_label((string)($row['status'] ?? 'draft'))) ?>
                                </span>
                            </td>
                            <td>
                                <div class="rx-inline-actions">
                                    <a class="rx-btn rx-btn-soft rx-btn-sm" href="<?= prescription_e(prescription_url('view.php?id=' . (int)$row['id'])) ?>">View</a>
                                    <a class="rx-btn rx-btn-soft rx-btn-sm" href="<?= prescription_e(prescription_url('edit.php?id=' . (int)$row['id'])) ?>">Edit</a>
                                    <a class="rx-btn rx-btn-soft rx-btn-sm" target="_blank" href="<?= prescription_e(prescription_url('print.php?id=' . (int)$row['id'])) ?>">Print</a>
                                    <form method="POST" class="js-confirm-delete">
                                        <input type="hidden" name="csrf_token" value="<?= prescription_e(prescription_csrf_token()) ?>">
                                        <input type="hidden" name="form_action" value="delete_prescription">
                                        <input type="hidden" name="prescription_id" value="<?= prescription_e((string)$row['id']) ?>">
                                        <button type="submit" class="rx-btn rx-btn-danger rx-btn-sm">Delete</button>
                                    </form>
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
                        <?php
                        $query = http_build_query([
                            'q' => $q,
                            'status' => $status,
                            'page' => $i,
                        ]);
                        ?>
                        <?php if ($i === $page): ?>
                            <span class="active"><?= $i ?></span>
                        <?php else: ?>
                            <a href="<?= prescription_e(prescription_url('?' . $query)) ?>"><?= $i ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
