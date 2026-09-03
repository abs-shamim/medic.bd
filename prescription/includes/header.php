<?php
declare(strict_types=1);

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

    <style>
        :root {
            --rx-bg: #f6f8fa;
            --rx-surface: #ffffff;
            --rx-surface-muted: #f6f8fa;
            --rx-border: #d0d7de;
            --rx-border-soft: #eaeef2;
            --rx-text: #1f2328;
            --rx-muted: #656d76;
            --rx-link: #0969da;
            --rx-link-hover: #0550ae;
            --rx-success: #1a7f37;
            --rx-success-bg: #dafbe1;
            --rx-danger: #cf222e;
            --rx-danger-bg: #ffebe9;
            --rx-shadow: 0 1px 0 rgba(31, 35, 40, .04);
            --rx-sidebar-width: 248px;
            --rx-radius: 8px;
        }

        * {
            box-sizing: border-box;
        }

        html {
            background: var(--rx-bg);
        }

        body.rx-dashboard-body {
            margin: 0;
            min-height: 100vh;
            background: var(--rx-bg);
            color: var(--rx-text);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", "Noto Sans Bengali",
                "Noto Sans", Helvetica, Arial, sans-serif;
            -webkit-font-smoothing: antialiased;
            text-rendering: optimizeLegibility;
        }

        body.rx-sidebar-open {
            overflow: hidden;
        }

        .rx-app-shell {
            min-height: 100vh;
        }

        .rx-dashboard-sidebar {
            position: fixed;
            inset: 0 auto 0 0;
            z-index: 1000;
            width: var(--rx-sidebar-width);
            display: flex;
            flex-direction: column;
            background: var(--rx-surface);
            border-right: 1px solid var(--rx-border);
        }

        .rx-sidebar-brand-wrap {
            min-height: 64px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 12px 16px;
            border-bottom: 1px solid var(--rx-border-soft);
        }

        .rx-sidebar-brand {
            min-width: 0;
            display: flex;
            align-items: center;
            gap: 11px;
            color: var(--rx-text);
            text-decoration: none;
        }

        .rx-sidebar-brand:hover {
            color: var(--rx-text);
        }

        .rx-sidebar-brand-mark {
            width: 34px;
            height: 34px;
            flex: 0 0 34px;
            display: grid;
            place-items: center;
            border-radius: 8px;
            background: #24292f;
            color: #fff;
            font-size: 13px;
            font-weight: 700;
            letter-spacing: -.02em;
        }

        .rx-sidebar-brand strong,
        .rx-sidebar-brand small {
            display: block;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .rx-sidebar-brand strong {
            font-size: 14px;
            font-weight: 600;
            line-height: 1.25;
        }

        .rx-sidebar-brand small {
            margin-top: 2px;
            color: var(--rx-muted);
            font-size: 11px;
            line-height: 1.25;
        }

        .rx-sidebar-close {
            display: none;
            width: 32px;
            height: 32px;
            padding: 0;
            border: 0;
            border-radius: 6px;
            background: transparent;
            color: var(--rx-muted);
            font-size: 24px;
            line-height: 1;
            cursor: pointer;
        }

        .rx-sidebar-close:hover {
            background: var(--rx-surface-muted);
            color: var(--rx-text);
        }

        .rx-sidebar-doctor-card {
            margin: 14px 12px 8px;
            padding: 12px;
            display: flex;
            align-items: center;
            gap: 10px;
            border: 1px solid var(--rx-border);
            border-radius: var(--rx-radius);
            background: var(--rx-surface);
            box-shadow: var(--rx-shadow);
        }

        .rx-sidebar-avatar,
        .rx-doctor-avatar {
            display: grid;
            place-items: center;
            border: 1px solid var(--rx-border);
            border-radius: 50%;
            background: var(--rx-surface-muted);
            color: var(--rx-text);
            font-weight: 600;
            text-transform: uppercase;
        }

        .rx-sidebar-avatar {
            width: 36px;
            height: 36px;
            flex: 0 0 36px;
            font-size: 13px;
        }

        .rx-sidebar-doctor-card > span:last-child,
        .rx-topbar-profile > span:last-child {
            min-width: 0;
        }

        .rx-sidebar-doctor-card strong,
        .rx-sidebar-doctor-card small {
            display: block;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .rx-sidebar-doctor-card strong {
            font-size: 13px;
            font-weight: 600;
        }

        .rx-sidebar-doctor-card small {
            margin-top: 2px;
            color: var(--rx-muted);
            font-size: 11px;
        }

        .rx-sidebar-nav {
            padding: 8px 10px 12px;
        }

        .rx-sidebar-nav-title {
            display: block;
            padding: 8px 10px 6px;
            color: var(--rx-muted);
            font-size: 11px;
            font-weight: 600;
            letter-spacing: .04em;
            text-transform: uppercase;
        }

        .rx-sidebar-nav a,
        .rx-sidebar-dashboard-link {
            min-height: 40px;
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 10px;
            border-radius: 6px;
            color: var(--rx-text);
            font-size: 13px;
            font-weight: 500;
            line-height: 1.35;
            text-decoration: none;
            transition: background-color .15s ease, color .15s ease;
        }

        .rx-sidebar-nav a:hover,
        .rx-sidebar-dashboard-link:hover {
            background: rgba(208, 215, 222, .32);
            color: var(--rx-text);
        }

        .rx-sidebar-nav a.active {
            background: #eaeef2;
            color: var(--rx-text);
            font-weight: 600;
        }

        .rx-sidebar-nav a.active::before {
            content: "";
            width: 3px;
            height: 24px;
            margin-left: -10px;
            margin-right: 7px;
            border-radius: 0 2px 2px 0;
            background: #fd8c73;
        }

        .rx-sidebar-nav-icon {
            width: 20px;
            height: 20px;
            flex: 0 0 20px;
            display: grid;
            place-items: center;
            color: var(--rx-muted);
        }

        .rx-sidebar-nav-icon svg {
            width: 18px;
            height: 18px;
            fill: currentColor;
        }

        .rx-sidebar-nav a.active .rx-sidebar-nav-icon {
            color: var(--rx-text);
        }

        .rx-sidebar-bottom {
            margin-top: auto;
            padding: 12px;
            border-top: 1px solid var(--rx-border-soft);
        }

        .rx-sidebar-security {
            margin-bottom: 8px;
            padding: 11px;
            border: 1px solid var(--rx-border-soft);
            border-radius: 6px;
            background: var(--rx-surface-muted);
        }

        .rx-sidebar-security strong,
        .rx-sidebar-security span {
            display: block;
        }

        .rx-sidebar-security strong {
            font-size: 12px;
            font-weight: 600;
        }

        .rx-sidebar-security span {
            margin-top: 4px;
            color: var(--rx-muted);
            font-size: 11px;
            line-height: 1.45;
        }

        .rx-sidebar-overlay {
            position: fixed;
            inset: 0;
            z-index: 990;
            display: none;
            width: 100%;
            height: 100%;
            padding: 0;
            border: 0;
            background: rgba(27, 31, 36, .45);
            cursor: pointer;
        }

        .rx-dashboard-stage {
            min-height: 100vh;
            margin-left: var(--rx-sidebar-width);
        }

        .rx-dashboard-topbar {
            position: sticky;
            top: 0;
            z-index: 900;
            min-height: 64px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding: 10px 24px;
            background: rgba(255, 255, 255, .96);
            border-bottom: 1px solid var(--rx-border);
            backdrop-filter: blur(10px);
        }

        .rx-dashboard-topbar-left,
        .rx-dashboard-topbar-right,
        .rx-topbar-profile {
            display: flex;
            align-items: center;
        }

        .rx-dashboard-topbar-left {
            min-width: 0;
            gap: 12px;
        }

        .rx-dashboard-topbar-left > div {
            min-width: 0;
        }

        .rx-dashboard-eyebrow {
            display: block;
            margin-bottom: 2px;
            color: var(--rx-muted);
            font-size: 11px;
            font-weight: 600;
        }

        .rx-dashboard-topbar h1 {
            margin: 0;
            overflow: hidden;
            color: var(--rx-text);
            font-size: 17px;
            font-weight: 600;
            line-height: 1.3;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .rx-dashboard-topbar-right {
            gap: 10px;
        }

        .rx-sidebar-toggle {
            display: none;
            width: 34px;
            height: 34px;
            padding: 8px;
            border: 1px solid var(--rx-border);
            border-radius: 6px;
            background: var(--rx-surface);
            cursor: pointer;
        }

        .rx-sidebar-toggle:hover {
            background: var(--rx-surface-muted);
        }

        .rx-sidebar-toggle span {
            display: block;
            width: 100%;
            height: 2px;
            margin: 3px 0;
            border-radius: 999px;
            background: var(--rx-text);
        }

        .rx-topbar-new-rx {
            min-height: 34px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 6px 12px;
            border: 1px solid rgba(27, 31, 36, .15);
            border-radius: 6px;
            background: #1f883d;
            color: #fff;
            font-size: 13px;
            font-weight: 600;
            line-height: 1.4;
            text-decoration: none;
            box-shadow: 0 1px 0 rgba(27, 31, 36, .1), inset 0 1px 0 rgba(255, 255, 255, .03);
        }

        .rx-topbar-new-rx:hover {
            background: #1a7f37;
            color: #fff;
        }

        .rx-topbar-profile {
            min-height: 34px;
            gap: 8px;
            padding: 3px 8px 3px 4px;
            border: 1px solid var(--rx-border);
            border-radius: 6px;
            background: var(--rx-surface);
        }

        .rx-doctor-avatar {
            width: 26px;
            height: 26px;
            flex: 0 0 26px;
            font-size: 10px;
        }

        .rx-topbar-profile strong,
        .rx-topbar-profile small {
            display: block;
            max-width: 150px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .rx-topbar-profile strong {
            font-size: 12px;
            font-weight: 600;
        }

        .rx-topbar-profile small {
            margin-top: 1px;
            color: var(--rx-muted);
            font-size: 10px;
        }

        .rx-main {
            width: 100%;
            max-width: 1600px;
            margin: 0 auto;
            padding: 24px;
        }

        .rx-alert {
            margin-bottom: 18px;
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 12px 14px;
            border: 1px solid;
            border-radius: var(--rx-radius);
            background: var(--rx-surface);
            box-shadow: var(--rx-shadow);
        }

        .rx-alert.success {
            border-color: rgba(26, 127, 55, .35);
            background: var(--rx-success-bg);
            color: var(--rx-success);
        }

        .rx-alert.error {
            border-color: rgba(207, 34, 46, .35);
            background: var(--rx-danger-bg);
            color: var(--rx-danger);
        }

        .rx-alert-icon {
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

        .rx-alert strong {
            display: block;
            margin-bottom: 2px;
            font-size: 13px;
        }

        .rx-alert p {
            margin: 0;
            font-size: 13px;
            line-height: 1.45;
        }

        @media (max-width: 1024px) {
            .rx-dashboard-sidebar {
                width: min(86vw, 280px);
                transform: translateX(-100%);
                transition: transform .2s ease;
                box-shadow: 0 8px 24px rgba(140, 149, 159, .2);
            }

            .rx-dashboard-sidebar.is-open {
                transform: translateX(0);
            }

            .rx-sidebar-close,
            .rx-sidebar-toggle {
                display: inline-block;
            }

            .rx-sidebar-overlay.is-open {
                display: block;
            }

            .rx-dashboard-stage {
                margin-left: 0;
            }
        }

        @media (max-width: 720px) {
            .rx-dashboard-topbar {
                min-height: 58px;
                padding: 9px 14px;
            }

            .rx-dashboard-eyebrow,
            .rx-topbar-profile {
                display: none;
            }

            .rx-dashboard-topbar h1 {
                font-size: 15px;
            }

            .rx-topbar-new-rx {
                min-height: 32px;
                padding: 5px 9px;
                font-size: 12px;
            }

            .rx-main {
                padding: 16px 12px 24px;
            }
        }

        @media (max-width: 420px) {
            .rx-topbar-new-rx {
                font-size: 0;
            }

            .rx-topbar-new-rx::after {
                content: "+ Rx";
                font-size: 12px;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            .rx-dashboard-sidebar {
                transition: none;
            }
        }
    </style>
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

<script>
document.addEventListener('DOMContentLoaded', function () {
    const sidebar = document.getElementById('rxDashboardSidebar');
    const overlay = document.querySelector('.rx-sidebar-overlay');
    const toggle = document.querySelector('[data-sidebar-toggle]');
    const closeButtons = document.querySelectorAll('[data-sidebar-close]');

    if (!sidebar || !overlay || !toggle) {
        return;
    }

    const openSidebar = function () {
        sidebar.classList.add('is-open');
        overlay.classList.add('is-open');
        document.body.classList.add('rx-sidebar-open');
        toggle.setAttribute('aria-expanded', 'true');
    };

    const closeSidebar = function () {
        sidebar.classList.remove('is-open');
        overlay.classList.remove('is-open');
        document.body.classList.remove('rx-sidebar-open');
        toggle.setAttribute('aria-expanded', 'false');
    };

    toggle.addEventListener('click', openSidebar);

    closeButtons.forEach(function (button) {
        button.addEventListener('click', closeSidebar);
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closeSidebar();
        }
    });

    window.addEventListener('resize', function () {
        if (window.innerWidth > 1024) {
            closeSidebar();
        }
    });
});
</script>
