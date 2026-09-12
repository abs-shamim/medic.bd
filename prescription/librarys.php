<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!prescription_verify_csrf($_POST['csrf_token'] ?? null)) {
        prescription_flash('error', 'Security verification failed. Please refresh and try again.');
        prescription_redirect(prescription_library_dashboard_path());
    }

    $form_action = prescription_clean_text($_POST['form_action'] ?? '', 50);

    try {
        if ($form_action === 'restore_all_demos') {
            prescription_restore_library_demos($pdo, $doctor_id);
            prescription_flash('success', 'All hidden shared demo items are visible again.');
        }
    } catch (Throwable $e) {
        error_log('[Prescription Library Dashboard] ' . $e->getMessage());
        prescription_flash('error', 'The library dashboard action could not be completed.');
    }

    prescription_redirect(prescription_library_dashboard_path());
}

$managed_pages = prescription_managed_library_pages();
$grouped_libraries = [];
$library_stats = [];

$total_visible = 0;
$total_demo = 0;
$total_personal = 0;
$hidden_demo_count = 0;

foreach ($managed_pages as $type => $pages) {
    $definition = prescription_library_definition($type);

    if (!$definition) {
        continue;
    }

    try {
        $visible_result = prescription_get_library_management_items(
            $pdo,
            $doctor_id,
            $type,
            '',
            '',
            '',
            1,
            0
        );

        $demo_result = prescription_get_library_management_items(
            $pdo,
            $doctor_id,
            $type,
            '',
            '',
            'demo',
            1,
            0
        );

        $personal_result = prescription_get_library_management_items(
            $pdo,
            $doctor_id,
            $type,
            '',
            '',
            'mine',
            1,
            0
        );

        $visible_count = (int)($visible_result['total'] ?? 0);
        $demo_count = (int)($demo_result['total'] ?? 0);
        $personal_count = (int)($personal_result['total'] ?? 0);
    } catch (Throwable $e) {
        error_log('[Prescription Library Stats][' . $type . '] ' . $e->getMessage());

        $visible_count = 0;
        $demo_count = 0;
        $personal_count = 0;
    }

    $library_stats[$type] = [
        'visible' => $visible_count,
        'demo' => $demo_count,
        'personal' => $personal_count,
    ];

    $total_visible += $visible_count;
    $total_demo += $demo_count;
    $total_personal += $personal_count;

    $group = (string)($definition['group'] ?? 'Other');
    $grouped_libraries[$group][$type] = $definition;
}

try {
    if (prescription_table_exists($pdo, 'prescription_library_hidden')) {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*)
             FROM prescription_library_hidden
             WHERE doctor_id = :doctor_id'
        );

        $stmt->execute([':doctor_id' => $doctor_id]);
        $hidden_demo_count = (int)$stmt->fetchColumn();
    }
} catch (Throwable $e) {
    error_log('[Prescription Hidden Demo Count] ' . $e->getMessage());
    $hidden_demo_count = 0;
}

$library_icons = [
    'medicine' => 'M',
    'medicine_form' => 'MF',
    'strength' => 'S',
    'dosage' => 'D',
    'frequency' => 'F',
    'duration' => 'T',
    'instruction' => 'I',
    'test' => 'L',
    'diagnosis' => 'Dx',
    'complaint' => 'C',
    'examination' => 'E',
    'advice' => 'A',
    'history' => 'H',
];

$group_descriptions = [
    'Medicine' => 'Reusable medicine names, forms, strengths, doses and prescribing directions.',
    'Clinical' => 'Reusable complaints, examinations, tests, diagnoses, history and advice.',
    'Other' => 'Additional reusable prescription values available to your account.',
];

$page_title = 'Library Dashboard';
require_once __DIR__ . '/includes/header.php';
?>

<link rel="stylesheet" href="<?= prescription_e(prescription_url('assets/css/library-dashboard.css')) ?>">

<div class="rx-lib-admin-page">
    <section class="rx-lib-admin-toolbar">
        <div class="rx-lib-admin-toolbar-copy">
            <span class="rx-lib-admin-kicker">Prescription Libraries</span>
            <h2>Library Dashboard</h2>
            <p>
                Manage medicine, clinical and reusable prescription values from one place.
                Shared demos remain available to approved doctors, while personal items stay private.
            </p>
        </div>

        <div class="rx-lib-admin-toolbar-actions">
            <a
                class="rx-lib-admin-btn rx-lib-admin-btn-primary"
                href="<?= prescription_e(prescription_library_form_url('medicine')) ?>"
            >
                + Add Medicine
            </a>

            <a
                class="rx-lib-admin-btn"
                href="<?= prescription_e(prescription_url('new.php')) ?>"
            >
                + New Prescription
            </a>

            <a
                class="rx-lib-admin-btn rx-lib-admin-btn-link"
                href="<?= prescription_e(prescription_url()) ?>"
            >
                Prescriptions
            </a>
        </div>
    </section>

    <div class="rx-lib-admin-layout">
        <aside class="rx-lib-admin-menu" aria-label="Library menu">
            <div class="rx-lib-admin-menu-head">
                <strong>Library Menu</strong>
                <span>Select a library to manage</span>
            </div>

            <div class="rx-lib-admin-menu-body">
                <?php foreach ($grouped_libraries as $group_name => $libraries): ?>
                    <div class="rx-lib-admin-menu-group">
                        <span class="rx-lib-admin-menu-group-title">
                            <?= prescription_e($group_name) ?>
                        </span>

                        <?php foreach ($libraries as $type => $definition): ?>
                            <?php
                            $stats = $library_stats[$type] ?? [
                                'visible' => 0,
                                'demo' => 0,
                                'personal' => 0,
                            ];
                            ?>

                            <a
                                class="rx-lib-admin-menu-link"
                                href="<?= prescription_e(prescription_library_form_url($type)) ?>"
                            >
                                <span class="rx-lib-admin-menu-icon">
                                    <?= prescription_e($library_icons[$type] ?? 'L') ?>
                                </span>

                                <span><?= prescription_e($definition['title']) ?></span>

                                <span class="rx-lib-admin-menu-count">
                                    <?= prescription_e((string)$stats['visible']) ?>
                                </span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </aside>

        <div class="rx-lib-admin-content">
            <section class="rx-lib-admin-overview">
                <div class="rx-lib-admin-section-head">
                    <div>
                        <h3>Library Overview</h3>
                        <p>Current reusable prescription data available to your doctor account.</p>
                    </div>

                    <span class="rx-lib-admin-section-badge">
                        <?= prescription_e((string)count($managed_pages)) ?> Libraries
                    </span>
                </div>

                <div class="rx-lib-admin-stats">
                    <article class="rx-lib-admin-stat">
                        <span>Library Types</span>
                        <strong><?= prescription_e((string)count($managed_pages)) ?></strong>
                        <small>Separate reusable libraries</small>
                    </article>

                    <article class="rx-lib-admin-stat">
                        <span>Visible Items</span>
                        <strong><?= prescription_e((string)$total_visible) ?></strong>
                        <small>Demo and personal combined</small>
                    </article>

                    <article class="rx-lib-admin-stat">
                        <span>My Items</span>
                        <strong><?= prescription_e((string)$total_personal) ?></strong>
                        <small>Private to your account</small>
                    </article>

                    <article class="rx-lib-admin-stat">
                        <span>Shared Demos</span>
                        <strong><?= prescription_e((string)$total_demo) ?></strong>
                        <small><?= prescription_e((string)$hidden_demo_count) ?> currently hidden</small>
                    </article>
                </div>
            </section>

            <section class="rx-lib-admin-notice">
                <div class="rx-lib-admin-notice-copy">
                    <span class="rx-lib-admin-notice-icon">i</span>
                    <div>
                        <strong>Shared demo rules</strong>
                        <span>
                            Hiding a shared demo affects only your account. Personal values can be
                            edited, disabled or permanently deleted.
                        </span>
                    </div>
                </div>

                <?php if ($hidden_demo_count > 0): ?>
                    <form method="POST">
                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?= prescription_e(prescription_csrf_token()) ?>"
                        >
                        <input type="hidden" name="form_action" value="restore_all_demos">

                        <button type="submit" class="rx-lib-admin-btn">
                            Restore Hidden Demos
                        </button>
                    </form>
                <?php endif; ?>
            </section>

            <?php foreach ($grouped_libraries as $group_name => $libraries): ?>
                <section
                    class="rx-lib-admin-panel"
                    id="group-<?= prescription_e(strtolower(preg_replace('/[^a-z0-9]+/i', '-', $group_name))) ?>"
                >
                    <div class="rx-lib-admin-section-head">
                        <div>
                            <h3><?= prescription_e($group_name) ?> Libraries</h3>
                            <p>
                                <?= prescription_e(
                                    $group_descriptions[$group_name]
                                    ?? $group_descriptions['Other']
                                ) ?>
                            </p>
                        </div>

                        <span class="rx-lib-admin-section-badge">
                            <?= prescription_e((string)count($libraries)) ?> Libraries
                        </span>
                    </div>

                    <ul class="rx-lib-admin-list">
                        <?php foreach ($libraries as $type => $definition): ?>
                            <?php
                            $stats = $library_stats[$type] ?? [
                                'visible' => 0,
                                'demo' => 0,
                                'personal' => 0,
                            ];
                            ?>

                            <li
                                class="rx-lib-admin-row"
                                id="library-<?= prescription_e($type) ?>"
                                data-library-section="<?= prescription_e($type) ?>"
                            >
                                <div class="rx-lib-admin-row-main">
                                    <span class="rx-lib-admin-row-icon">
                                        <?= prescription_e($library_icons[$type] ?? 'L') ?>
                                    </span>

                                    <div class="rx-lib-admin-row-copy">
                                        <h4><?= prescription_e($definition['title']) ?></h4>
                                        <p><?= prescription_e($definition['description']) ?></p>
                                    </div>
                                </div>

                                <div class="rx-lib-admin-row-meta">
                                    <div class="rx-lib-admin-counts">
                                        <span class="rx-lib-admin-count">
                                            <strong><?= prescription_e((string)$stats['visible']) ?></strong>
                                            Total
                                        </span>

                                        <span class="rx-lib-admin-count">
                                            <strong><?= prescription_e((string)$stats['demo']) ?></strong>
                                            Demo
                                        </span>

                                        <span class="rx-lib-admin-count">
                                            <strong><?= prescription_e((string)$stats['personal']) ?></strong>
                                            Mine
                                        </span>
                                    </div>

                                    <div class="rx-lib-admin-row-actions">
                                        <a
                                            class="rx-lib-admin-btn rx-lib-admin-btn-primary"
                                            href="<?= prescription_e(prescription_library_list_url($type)) ?>"
                                        >
                                            Manage
                                        </a>

                                        <a
                                            class="rx-lib-admin-btn"
                                            href="<?= prescription_e(prescription_library_form_url($type)) ?>"
                                        >
                                            + Add New
                                        </a>
                                    </div>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </section>
            <?php endforeach; ?>
        </div>
    </div>
</div>



<?php require_once __DIR__ . '/includes/footer.php'; ?>
