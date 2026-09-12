<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/html-normalizer.php';

if (function_exists('front_normalize_html_output')) {
    ob_start('front_normalize_html_output');
}

$page_title = $page_title ?? 'Prescription';
$doctor_profile = $doctor_profile ?? prescription_get_doctor($pdo, $doctor_id);
$flash = prescription_get_flash();

$current_file = basename((string)($_SERVER['PHP_SELF'] ?? 'index.php'));
$current_uri = (string)($_SERVER['REQUEST_URI'] ?? '');

$doctor_name = trim((string)($doctor_profile['name'] ?? 'Doctor'));
$doctor_degree = trim((string)($doctor_profile['degree'] ?? ''));
$doctor_initial = prescription_initial($doctor_name);
$hide_new_prescription_nav = (bool)($hide_new_prescription_nav ?? false);
$topbar_action_label = trim((string)($topbar_action_label ?? '+ New Prescription'));
$topbar_action_url = (string)($topbar_action_url ?? prescription_url('new.php'));
$show_topbar_action = (bool)($show_topbar_action ?? true);

if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
}

$nav_items = [
    [
        'label' => 'Prescriptions',
        'url' => prescription_url(),
        'active' => $current_file === 'index.php'
            || in_array($current_file, ['view.php', 'print.php', 'edit.php'], true),
        'icon' => 'prescription',
    ],
    [
        'label' => 'New Prescription',
        'url' => prescription_url('new.php'),
        'active' => $current_file === 'new.php',
        'icon' => 'plus',
    ],
    [
        'label' => 'Patients',
        'url' => prescription_url('patients.php'),
        'active' => in_array($current_file, ['patients.php', 'patient-view.php'], true),
        'icon' => 'patients',
    ],
    [
        'label' => 'Appointment',
        'url' => prescription_url('appointment.php'),
        'active' => $current_file === 'appointment.php',
        'icon' => 'appointment',
    ],
    [
        'label' => 'Libraries',
        'url' => prescription_library_dashboard_url(),
        'active' => str_contains($current_uri, '/prescription/Library/')
            || in_array($current_file, ['librarys.php', 'medicine-library.php'], true),
        'icon' => 'library',
    ],
];

if ($hide_new_prescription_nav) {
    $nav_items = array_values(array_filter(
        $nav_items,
        static fn(array $item): bool => (string)($item['label'] ?? '') !== 'New Prescription'
    ));
}

function prescription_nav_icon(string $icon): string
{
    $icons = [
        'prescription' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6.75 3.75h7.5a2 2 0 0 1 2 2v2.5h1a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H6.75a2 2 0 0 1-2-2v-12.5a2 2 0 0 1 2-2Zm0 1.5a.5.5 0 0 0-.5.5v12.5a.5.5 0 0 0 .5.5h10.5a.5.5 0 0 0 .5-.5v-8a.5.5 0 0 0-.5-.5h-1v1.5a.75.75 0 0 1-1.5 0v-5.5a.5.5 0 0 0-.5-.5h-7.5Zm2 4.25h4.5a.75.75 0 0 1 0 1.5h-4.5a.75.75 0 0 1 0-1.5Zm0 3.5h6.5a.75.75 0 0 1 0 1.5h-6.5a.75.75 0 0 1 0-1.5Zm0 3.5h4.5a.75.75 0 0 1 0 1.5h-4.5a.75.75 0 0 1 0-1.5Z"/></svg>',
        'plus' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 4.75a.75.75 0 0 1 .75.75v5.75h5.75a.75.75 0 0 1 0 1.5h-5.75v5.75a.75.75 0 0 1-1.5 0v-5.75H5.5a.75.75 0 0 1 0-1.5h5.75V5.5a.75.75 0 0 1 .75-.75Z"/></svg>',
        'patients' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9.5 4a4 4 0 1 1 0 8 4 4 0 0 1 0-8Zm0 1.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5Zm7.25 1a3.25 3.25 0 1 1 0 6.5.75.75 0 0 1 0-1.5 1.75 1.75 0 1 0 0-3.5.75.75 0 0 1 0-1.5ZM3.5 18.75c0-3.15 2.58-5.25 6-5.25s6 2.1 6 5.25a1.75 1.75 0 0 1-1.75 1.75h-8.5A1.75 1.75 0 0 1 3.5 18.75Zm6-3.75C6.84 15 5 16.51 5 18.75c0 .14.11.25.25.25h8.5c.14 0 .25-.11.25-.25C14 16.51 12.16 15 9.5 15Zm6.1-.95c2.85.27 4.9 2.06 4.9 4.7a1.75 1.75 0 0 1-1.75 1.75h-1.5a.75.75 0 0 1 0-1.5h1.5c.14 0 .25-.11.25-.25 0-1.78-1.32-2.98-3.54-3.2a.75.75 0 1 1 .14-1.5Z"/></svg>',
        'appointment' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7.75 3a.75.75 0 0 1 .75.75V5h7V3.75a.75.75 0 0 1 1.5 0V5h.25A2.75 2.75 0 0 1 20 7.75v10.5A2.75 2.75 0 0 1 17.25 21H6.75A2.75 2.75 0 0 1 4 18.25V7.75A2.75 2.75 0 0 1 6.75 5H7V3.75A.75.75 0 0 1 7.75 3Zm9.5 3.5H6.75c-.69 0-1.25.56-1.25 1.25V9h13V7.75c0-.69-.56-1.25-1.25-1.25ZM18.5 10.5h-13v7.75c0 .69.56 1.25 1.25 1.25h10.5c.69 0 1.25-.56 1.25-1.25V10.5Zm-6.5 1.75a.75.75 0 0 1 .75.75v1.25H14a.75.75 0 0 1 0 1.5h-1.25V17a.75.75 0 0 1-1.5 0v-1.25H10a.75.75 0 0 1 0-1.5h1.25V13a.75.75 0 0 1 .75-.75Z"/></svg>',
        'library' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4.75 4A1.75 1.75 0 0 0 3 5.75v12.5C3 19.22 3.78 20 4.75 20h14.5c.97 0 1.75-.78 1.75-1.75V8.75A1.75 1.75 0 0 0 19.25 7H13l-1.58-2.1A2.25 2.25 0 0 0 9.62 4H4.75Zm0 1.5h4.87c.24 0 .46.11.6.3l1.8 2.4c.14.19.36.3.6.3h6.63c.14 0 .25.11.25.25v9.5c0 .14-.11.25-.25.25H4.75a.25.25 0 0 1-.25-.25V5.75c0-.14.11-.25.25-.25Z"/></svg>',
    ];

    return $icons[$icon] ?? $icons['prescription'];
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <meta name="color-scheme" content="light">
    <title><?= prescription_e($page_title) ?> | Prescription</title>

    <link rel="stylesheet" href="<?= prescription_e(prescription_url('assets/css/prescription.css')) ?>">
    <link rel="stylesheet" href="<?= prescription_e(prescription_url('assets/css/dashboard-shell.css')) ?>">

</head>
<body class="rx-dashboard-body">
<div class="rx-app-shell">
    <aside
        class="rx-dashboard-sidebar"
        id="rxDashboardSidebar"
        aria-label="Prescription dashboard navigation"
    >
        <div class="rx-sidebar-brand-wrap">
            <a class="rx-sidebar-brand" href="<?= prescription_e(prescription_url()) ?>">
                <span class="rx-sidebar-brand-mark">Rx</span>
                <span>
                    <strong>Prescription</strong>
                    <small>Doctor workspace</small>
                </span>
            </a>

            <button
                type="button"
                class="rx-sidebar-close"
                data-sidebar-close
                aria-label="Close navigation"
            >×</button>
        </div>

        <div class="rx-sidebar-doctor-card">
            <span class="rx-sidebar-avatar"><?= prescription_e($doctor_initial) ?></span>
            <span>
                <strong><?= prescription_e($doctor_name) ?></strong>
                <small><?= prescription_e($doctor_degree !== '' ? $doctor_degree : 'Approved Doctor') ?></small>
            </span>
        </div>

        <nav class="rx-sidebar-nav">
            <span class="rx-sidebar-nav-title">Workspace</span>

            <?php foreach ($nav_items as $item): ?>
                <a
                    href="<?= prescription_e((string)$item['url']) ?>"
                    class="<?= $item['active'] ? 'active' : '' ?>"
                    <?= $item['active'] ? 'aria-current="page"' : '' ?>
                >
                    <span class="rx-sidebar-nav-icon">
                        <?= prescription_nav_icon((string)$item['icon']) ?>
                    </span>
                    <span><?= prescription_e((string)$item['label']) ?></span>
                </a>
            <?php endforeach; ?>
        </nav>

        <div class="rx-sidebar-bottom">
            <div class="rx-sidebar-security">
                <strong>Private medical workspace</strong>
                <span>Patient records are limited to your doctor account.</span>
            </div>

            <a
                class="rx-sidebar-dashboard-link"
                href="<?= prescription_e(prescription_user_url('dashboard.php')) ?>"
            >
                <span class="rx-sidebar-nav-icon">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M4.75 3.5h5.5A1.75 1.75 0 0 1 12 5.25v5A1.75 1.75 0 0 1 10.25 12h-5.5A1.75 1.75 0 0 1 3 10.25v-5A1.75 1.75 0 0 1 4.75 3.5Zm0 1.5a.25.25 0 0 0-.25.25v5c0 .14.11.25.25.25h5.5c.14 0 .25-.11.25-.25v-5a.25.25 0 0 0-.25-.25h-5.5Zm9 0h5.5c.97 0 1.75.78 1.75 1.75v12.5A1.75 1.75 0 0 1 19.25 21h-5.5A1.75 1.75 0 0 1 12 19.25V6.75C12 5.78 12.78 5 13.75 5Zm0 1.5a.25.25 0 0 0-.25.25v12.5c0 .14.11.25.25.25h5.5c.14 0 .25-.11.25-.25V6.75a.25.25 0 0 0-.25-.25h-5.5Zm-9 7h5.5A1.75 1.75 0 0 1 12 15.25v4A1.75 1.75 0 0 1 10.25 21h-5.5A1.75 1.75 0 0 1 3 19.25v-4c0-.97.78-1.75 1.75-1.75Zm0 1.5a.25.25 0 0 0-.25.25v4c0 .14.11.25.25.25h5.5c.14 0 .25-.11.25-.25v-4a.25.25 0 0 0-.25-.25h-5.5Z"/>
                    </svg>
                </span>
                <span>Back to Main Dashboard</span>
            </a>
        </div>
    </aside>

    <button
        type="button"
        class="rx-sidebar-overlay"
        data-sidebar-close
        aria-label="Close navigation"
    ></button>

    <div class="rx-dashboard-stage">
        <header class="rx-dashboard-topbar">
            <div class="rx-dashboard-topbar-left">
                <button
                    type="button"
                    class="rx-sidebar-toggle"
                    data-sidebar-toggle
                    aria-label="Open navigation"
                    aria-controls="rxDashboardSidebar"
                    aria-expanded="false"
                >
                    <span></span>
                    <span></span>
                    <span></span>
                </button>

                <div>
                    <span class="rx-dashboard-eyebrow">Doctor Workspace</span>
                    <h1><?= prescription_e($page_title) ?></h1>
                </div>
            </div>

            <div class="rx-dashboard-topbar-right">
                <?php if ($show_topbar_action && $topbar_action_label !== ''): ?>
                    <a
                        class="rx-topbar-new-rx"
                        href="<?= prescription_e($topbar_action_url) ?>"
                    ><?= prescription_e($topbar_action_label) ?></a>
                <?php endif; ?>

                <div class="rx-topbar-profile">
                    <span class="rx-doctor-avatar"><?= prescription_e($doctor_initial) ?></span>
                    <span>
                        <strong><?= prescription_e($doctor_name) ?></strong>
                        <small><?= prescription_e($doctor_degree !== '' ? $doctor_degree : 'Doctor') ?></small>
                    </span>
                </div>
            </div>
        </header>

        <main class="rx-main">
            <?php if ($flash['message'] !== ''): ?>
                <div
                    class="rx-alert <?= prescription_e($flash['type'] === 'error' ? 'error' : 'success') ?>"
                    role="alert"
                >
                    <span class="rx-alert-icon"><?= $flash['type'] === 'error' ? '!' : '✓' ?></span>
                    <div>
                        <strong><?= $flash['type'] === 'error' ? 'Error' : 'Success' ?></strong>
                        <p><?= prescription_e($flash['message']) ?></p>
                    </div>
                </div>
            <?php endif; ?>

<script src="<?= prescription_e(prescription_url('assets/js/sidebar-toggle.js')) ?>" defer></script>

