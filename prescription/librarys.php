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

<style>
    .rx-lib-admin-page {
        --lib-bg: #f6f8fa;
        --lib-panel: #ffffff;
        --lib-panel-soft: #f6f8fa;
        --lib-border: #d0d7de;
        --lib-border-soft: #eaeef2;
        --lib-text: #1f2328;
        --lib-muted: #656d76;
        --lib-blue: #0969da;
        --lib-blue-soft: #ddf4ff;
        --lib-green: #1f883d;
        --lib-green-hover: #1a7f37;
        --lib-danger: #cf222e;
        color: var(--lib-text);
    }

    .rx-lib-admin-toolbar {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 18px;
        margin-bottom: 16px;
        padding: 18px 20px;
        border: 1px solid var(--lib-border);
        border-radius: 8px;
        background: var(--lib-panel);
        box-shadow: 0 1px 0 rgba(31, 35, 40, .04);
    }

    .rx-lib-admin-toolbar-copy {
        min-width: 0;
    }

    .rx-lib-admin-kicker {
        display: block;
        margin-bottom: 4px;
        color: var(--lib-muted);
        font-size: 12px;
        font-weight: 600;
    }

    .rx-lib-admin-toolbar h2 {
        margin: 0;
        font-size: 22px;
        font-weight: 650;
        line-height: 1.25;
        letter-spacing: -.02em;
    }

    .rx-lib-admin-toolbar p {
        max-width: 850px;
        margin: 6px 0 0;
        color: var(--lib-muted);
        font-size: 13px;
        line-height: 1.55;
    }

    .rx-lib-admin-toolbar-actions {
        flex: 0 0 auto;
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
    }

    .rx-lib-admin-btn {
        min-height: 34px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        padding: 6px 12px;
        border: 1px solid rgba(27, 31, 36, .15);
        border-radius: 6px;
        background: var(--lib-panel-soft);
        color: var(--lib-text);
        font-size: 12px;
        font-weight: 600;
        line-height: 1.4;
        text-decoration: none;
        white-space: nowrap;
        cursor: pointer;
        box-shadow: 0 1px 0 rgba(27, 31, 36, .04);
        transition: background-color .15s ease, border-color .15s ease;
    }

    .rx-lib-admin-btn:hover {
        background: #eef1f4;
        color: var(--lib-text);
        text-decoration: none;
    }

    .rx-lib-admin-btn-primary {
        border-color: rgba(27, 31, 36, .15);
        background: var(--lib-green);
        color: #ffffff;
    }

    .rx-lib-admin-btn-primary:hover {
        background: var(--lib-green-hover);
        color: #ffffff;
    }

    .rx-lib-admin-btn-link {
        border-color: var(--lib-border);
        background: #ffffff;
    }

    .rx-lib-admin-layout {
        display: grid;
        grid-template-columns: 282px minmax(0, 1fr);
        gap: 16px;
        align-items: start;
    }

    .rx-lib-admin-menu {
        position: sticky;
        top: 80px;
        overflow: hidden;
        border: 1px solid var(--lib-border);
        border-radius: 8px;
        background: var(--lib-panel);
        box-shadow: 0 1px 0 rgba(31, 35, 40, .04);
    }

    .rx-lib-admin-menu-head {
        padding: 14px 15px 13px;
        border-bottom: 1px solid var(--lib-border);
        background: var(--lib-panel-soft);
    }

    .rx-lib-admin-menu-head strong {
        display: block;
        font-size: 13px;
        font-weight: 650;
    }

    .rx-lib-admin-menu-head span {
        display: block;
        margin-top: 4px;
        color: var(--lib-muted);
        font-size: 11px;
    }

    .rx-lib-admin-menu-body {
        max-height: calc(100vh - 190px);
        overflow-y: auto;
        padding: 8px;
        scrollbar-width: thin;
    }

    .rx-lib-admin-menu-group + .rx-lib-admin-menu-group {
        margin-top: 8px;
        padding-top: 8px;
        border-top: 1px solid var(--lib-border-soft);
    }

    .rx-lib-admin-menu-group-title {
        display: block;
        padding: 5px 8px;
        color: var(--lib-muted);
        font-size: 10px;
        font-weight: 700;
        letter-spacing: .06em;
        text-transform: uppercase;
    }

    .rx-lib-admin-menu-link {
        display: grid;
        grid-template-columns: 28px minmax(0, 1fr) auto;
        align-items: center;
        gap: 8px;
        min-height: 42px;
        padding: 6px 8px;
        border-radius: 6px;
        color: var(--lib-text);
        font-size: 12px;
        font-weight: 500;
        text-decoration: none;
        transition: background-color .15s ease, color .15s ease;
    }

    .rx-lib-admin-menu-link:hover {
        background: var(--lib-panel-soft);
        color: var(--lib-text);
        text-decoration: none;
    }

    .rx-lib-admin-menu-link.is-active {
        background: var(--lib-blue-soft);
        color: var(--lib-blue);
    }

    .rx-lib-admin-menu-icon,
    .rx-lib-admin-row-icon {
        display: grid;
        place-items: center;
        border: 1px solid var(--lib-border);
        border-radius: 6px;
        background: var(--lib-panel-soft);
        color: #57606a;
        font-weight: 650;
        line-height: 1;
    }

    .rx-lib-admin-menu-icon {
        width: 28px;
        height: 28px;
        font-size: 10px;
    }

    .rx-lib-admin-menu-link.is-active .rx-lib-admin-menu-icon {
        border-color: #b6ddf5;
        background: #ffffff;
        color: var(--lib-blue);
    }

    .rx-lib-admin-menu-count {
        min-width: 24px;
        padding: 2px 6px;
        border-radius: 999px;
        background: #eaeef2;
        color: var(--lib-muted);
        font-size: 10px;
        font-weight: 600;
        text-align: center;
    }

    .rx-lib-admin-content {
        min-width: 0;
    }

    .rx-lib-admin-overview {
        overflow: hidden;
        margin-bottom: 16px;
        border: 1px solid var(--lib-border);
        border-radius: 8px;
        background: var(--lib-panel);
        box-shadow: 0 1px 0 rgba(31, 35, 40, .04);
    }

    .rx-lib-admin-section-head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 16px;
        padding: 16px;
        border-bottom: 1px solid var(--lib-border);
        background: var(--lib-panel-soft);
    }

    .rx-lib-admin-section-head h3 {
        margin: 0;
        font-size: 16px;
        font-weight: 650;
        line-height: 1.3;
    }

    .rx-lib-admin-section-head p {
        margin: 5px 0 0;
        color: var(--lib-muted);
        font-size: 12px;
        line-height: 1.5;
    }

    .rx-lib-admin-section-badge {
        flex: 0 0 auto;
        padding: 4px 8px;
        border: 1px solid var(--lib-border);
        border-radius: 999px;
        background: #ffffff;
        color: var(--lib-muted);
        font-size: 10px;
        font-weight: 600;
        white-space: nowrap;
    }

    .rx-lib-admin-stats {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
    }

    .rx-lib-admin-stat {
        min-width: 0;
        padding: 16px;
        border-right: 1px solid var(--lib-border-soft);
    }

    .rx-lib-admin-stat:last-child {
        border-right: 0;
    }

    .rx-lib-admin-stat span,
    .rx-lib-admin-stat small {
        display: block;
    }

    .rx-lib-admin-stat span {
        color: var(--lib-muted);
        font-size: 11px;
        font-weight: 600;
    }

    .rx-lib-admin-stat strong {
        display: block;
        margin: 5px 0 4px;
        font-size: 24px;
        font-weight: 650;
        line-height: 1;
        letter-spacing: -.03em;
    }

    .rx-lib-admin-stat small {
        color: var(--lib-muted);
        font-size: 10px;
        line-height: 1.4;
    }

    .rx-lib-admin-notice {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 14px;
        margin-bottom: 16px;
        padding: 12px 14px;
        border: 1px solid #b6ddf5;
        border-radius: 8px;
        background: #ddf4ff;
        color: #0969da;
    }

    .rx-lib-admin-notice-copy {
        min-width: 0;
        display: flex;
        align-items: flex-start;
        gap: 10px;
    }

    .rx-lib-admin-notice-icon {
        width: 22px;
        height: 22px;
        flex: 0 0 22px;
        display: grid;
        place-items: center;
        border: 1px solid currentColor;
        border-radius: 50%;
        font-size: 12px;
        font-weight: 700;
    }

    .rx-lib-admin-notice strong,
    .rx-lib-admin-notice span {
        display: block;
    }

    .rx-lib-admin-notice strong {
        font-size: 12px;
    }

    .rx-lib-admin-notice span {
        margin-top: 3px;
        color: #0550ae;
        font-size: 11px;
        line-height: 1.5;
    }

    .rx-lib-admin-panel {
        overflow: hidden;
        margin-bottom: 16px;
        scroll-margin-top: 86px;
        border: 1px solid var(--lib-border);
        border-radius: 8px;
        background: var(--lib-panel);
        box-shadow: 0 1px 0 rgba(31, 35, 40, .04);
    }

    .rx-lib-admin-panel:last-child {
        margin-bottom: 0;
    }

    .rx-lib-admin-list {
        margin: 0;
        padding: 0;
        list-style: none;
    }

    .rx-lib-admin-row {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: 18px;
        align-items: center;
        padding: 15px 16px;
        border-bottom: 1px solid var(--lib-border-soft);
        scroll-margin-top: 86px;
    }

    .rx-lib-admin-row:last-child {
        border-bottom: 0;
    }

    .rx-lib-admin-row-main {
        min-width: 0;
        display: flex;
        align-items: flex-start;
        gap: 12px;
    }

    .rx-lib-admin-row-icon {
        width: 36px;
        height: 36px;
        flex: 0 0 36px;
        font-size: 11px;
    }

    .rx-lib-admin-row-copy {
        min-width: 0;
    }

    .rx-lib-admin-row-copy h4 {
        margin: 0;
        font-size: 13px;
        font-weight: 650;
        line-height: 1.35;
    }

    .rx-lib-admin-row-copy p {
        max-width: 720px;
        margin: 4px 0 0;
        color: var(--lib-muted);
        font-size: 11px;
        line-height: 1.5;
    }

    .rx-lib-admin-row-meta {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 10px;
    }

    .rx-lib-admin-counts {
        display: flex;
        align-items: center;
        gap: 4px;
        flex-wrap: wrap;
        justify-content: flex-end;
    }

    .rx-lib-admin-count {
        min-width: 54px;
        padding: 5px 7px;
        border: 1px solid var(--lib-border-soft);
        border-radius: 6px;
        background: var(--lib-panel-soft);
        color: var(--lib-muted);
        font-size: 9px;
        line-height: 1.25;
        text-align: center;
    }

    .rx-lib-admin-count strong {
        display: block;
        margin-bottom: 1px;
        color: var(--lib-text);
        font-size: 11px;
    }

    .rx-lib-admin-row-actions {
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .rx-lib-admin-row-actions .rx-lib-admin-btn {
        min-height: 30px;
        padding: 5px 9px;
        font-size: 11px;
    }

    @media (max-width: 1180px) {
        .rx-lib-admin-layout {
            grid-template-columns: 245px minmax(0, 1fr);
        }

        .rx-lib-admin-row {
            grid-template-columns: 1fr;
        }

        .rx-lib-admin-row-meta {
            justify-content: space-between;
            padding-left: 48px;
        }

        .rx-lib-admin-counts {
            justify-content: flex-start;
        }
    }

    @media (max-width: 920px) {
        .rx-lib-admin-layout {
            grid-template-columns: 1fr;
        }

        .rx-lib-admin-menu {
            position: static;
        }

        .rx-lib-admin-menu-body {
            max-height: none;
            display: flex;
            gap: 6px;
            overflow-x: auto;
            padding: 8px;
        }

        .rx-lib-admin-menu-group {
            display: contents;
        }

        .rx-lib-admin-menu-group + .rx-lib-admin-menu-group {
            margin: 0;
            padding: 0;
            border: 0;
        }

        .rx-lib-admin-menu-group-title {
            display: none;
        }

        .rx-lib-admin-menu-link {
            flex: 0 0 auto;
            grid-template-columns: 28px auto auto;
            min-width: max-content;
            border: 1px solid var(--lib-border-soft);
        }

        .rx-lib-admin-stats {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .rx-lib-admin-stat:nth-child(2) {
            border-right: 0;
        }

        .rx-lib-admin-stat:nth-child(-n + 2) {
            border-bottom: 1px solid var(--lib-border-soft);
        }
    }

    @media (max-width: 680px) {
        .rx-lib-admin-toolbar {
            flex-direction: column;
            padding: 15px;
        }

        .rx-lib-admin-toolbar h2 {
            font-size: 19px;
        }

        .rx-lib-admin-toolbar-actions {
            width: 100%;
        }

        .rx-lib-admin-toolbar-actions .rx-lib-admin-btn {
            flex: 1 1 auto;
        }

        .rx-lib-admin-section-head {
            padding: 14px;
        }

        .rx-lib-admin-stat {
            padding: 14px;
        }

        .rx-lib-admin-notice {
            align-items: flex-start;
            flex-direction: column;
        }

        .rx-lib-admin-row {
            padding: 14px;
        }

        .rx-lib-admin-row-meta {
            align-items: stretch;
            flex-direction: column;
            padding-left: 48px;
        }

        .rx-lib-admin-row-actions {
            width: 100%;
        }

        .rx-lib-admin-row-actions .rx-lib-admin-btn {
            flex: 1 1 0;
        }
    }

    @media (max-width: 430px) {
        .rx-lib-admin-stats {
            grid-template-columns: 1fr;
        }

        .rx-lib-admin-stat {
            border-right: 0;
            border-bottom: 1px solid var(--lib-border-soft);
        }

        .rx-lib-admin-stat:last-child {
            border-bottom: 0;
        }

        .rx-lib-admin-row-meta {
            padding-left: 0;
        }

        .rx-lib-admin-counts {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }

        .rx-lib-admin-count {
            min-width: 0;
        }
    }
</style>

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
