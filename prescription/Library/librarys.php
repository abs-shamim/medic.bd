<?php
declare(strict_types=1);

/*
 * Shared library layout.
 *
 * Every library list and form page loads this file after includes/header.php.
 * It provides the common library sidebar, page heading, buttons and CSS.
 */

$library_file = realpath(__FILE__);
$request_file = realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? ''));

if ($library_file !== false && $request_file === $library_file) {
    require_once dirname(__DIR__) . '/includes/auth.php';
    require_once dirname(__DIR__) . '/includes/functions.php';

    prescription_redirect(prescription_library_list_path('medicine'));
}

if (!isset($library_type) || !is_string($library_type)) {
    http_response_code(500);
    exit('Library type is not configured.');
}

if (!isset($managed_pages) || !is_array($managed_pages)) {
    $managed_pages = prescription_managed_library_pages();
}

if (!isset($definition) || !is_array($definition)) {
    $definition = prescription_library_definition($library_type);
}

if (!$definition || !isset($managed_pages[$library_type])) {
    http_response_code(404);
    exit('Library page was not found.');
}

$library_menu = [
    'Medicine' => [
        'medicine' => 'Medicine Names',
        'medicine_form' => 'Medicine Forms',
        'strength' => 'Strength',
        'dosage' => 'Dosage',
        'frequency' => 'Frequency',
        'duration' => 'Duration',
        'instruction' => 'Medicine Instructions',
    ],
    'Clinical' => [
        'test' => 'Tests / Investigations',
        'diagnosis' => 'Diagnosis',
        'complaint' => 'Chief Complaints',
        'examination' => 'Examination Findings',
        'advice' => 'Advice',
    ],
];

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
];

$library_accents = [
    'medicine' => '#1f883d',
    'medicine_form' => '#8250df',
    'strength' => '#0969da',
    'dosage' => '#8250df',
    'frequency' => '#bf8700',
    'duration' => '#0969da',
    'instruction' => '#1a7f37',
    'test' => '#0969da',
    'diagnosis' => '#8250df',
    'complaint' => '#cf222e',
    'examination' => '#57606a',
    'advice' => '#1a7f37',
];

$library_counts = [];

foreach ($library_menu as $menu_group => $menu_items) {
    foreach ($menu_items as $menu_type => $menu_label) {
        if (!isset($managed_pages[$menu_type])) {
            continue;
        }

        try {
            $library_counts[$menu_type] = prescription_count_visible_library_items(
                $pdo,
                $doctor_id,
                $menu_type
            );
        } catch (Throwable $e) {
            error_log('[Library Shared Header][' . $menu_type . '] ' . $e->getMessage());
            $library_counts[$menu_type] = 0;
        }
    }
}

$library_shell_title = isset($library_shell_title)
    ? trim((string)$library_shell_title)
    : (string)$definition['title'];

$library_shell_kicker = isset($library_shell_kicker)
    ? trim((string)$library_shell_kicker)
    : 'Prescription Libraries';

$library_shell_description = isset($library_shell_description)
    ? trim((string)$library_shell_description)
    : (string)($definition['description'] ?? '');

$library_shell_primary_label = isset($library_shell_primary_label)
    ? trim((string)$library_shell_primary_label)
    : '';

$library_shell_primary_url = isset($library_shell_primary_url)
    ? trim((string)$library_shell_primary_url)
    : '';

$library_shell_secondary_label = isset($library_shell_secondary_label)
    ? trim((string)$library_shell_secondary_label)
    : 'Library Dashboard';

$library_shell_secondary_url = isset($library_shell_secondary_url)
    ? trim((string)$library_shell_secondary_url)
    : prescription_library_dashboard_url();

$library_accent = $library_accents[$library_type] ?? '#0969da';

if (!function_exists('rx_library_shared_form_value')) {
    function rx_library_shared_form_value(?array $item, string $field, string $default = ''): string
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && array_key_exists($field, $_POST)) {
            return trim((string)$_POST[$field]);
        }

        return trim((string)($item[$field] ?? $default));
    }
}

if (!function_exists('rx_library_shared_layout_end')) {
    function rx_library_shared_layout_end(): void
    {
        echo "</section></div></div>";
    }
}
?>

<link rel="stylesheet" href="<?= prescription_e(prescription_url('assets/css/library-shared.css')) ?>">
<script src="<?= prescription_e(prescription_url('assets/js/library-shared.js')) ?>" defer></script>

<div class="rx-library-shared" data-accent="<?= prescription_e($library_accent) ?>">
    <div class="rx-lib-layout">
        <aside class="rx-lib-menu" aria-label="Library menu">
            <div class="rx-lib-menu-head">
                <strong>Library Menu</strong>
                <span>Select a library to manage</span>
            </div>

            <nav class="rx-lib-menu-body">
                <?php foreach ($library_menu as $menu_group => $menu_items): ?>
                    <div class="rx-lib-menu-group">
                        <span class="rx-lib-menu-title"><?= prescription_e($menu_group) ?></span>

                        <?php foreach ($menu_items as $menu_type => $menu_label): ?>
                            <?php if (!isset($managed_pages[$menu_type])) { continue; } ?>

                            <a
                                class="rx-lib-menu-link <?= $menu_type === $library_type ? 'is-active' : '' ?>"
                                href="<?= prescription_e(prescription_library_list_url($menu_type)) ?>"
                                <?= $menu_type === $library_type ? 'aria-current="page"' : '' ?>
                            >
                                <span class="rx-lib-menu-icon">
                                    <?= prescription_e($library_icons[$menu_type] ?? 'L') ?>
                                </span>
                                <span><?= prescription_e($menu_label) ?></span>
                                <span class="rx-lib-menu-count">
                                    <?= prescription_e((string)($library_counts[$menu_type] ?? 0)) ?>
                                </span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            </nav>

            <div class="rx-lib-menu-foot">
                <a href="<?= prescription_e(prescription_library_dashboard_url()) ?>">
                    <span>Library Dashboard</span>
                    <span aria-hidden="true">→</span>
                </a>
            </div>
        </aside>

        <section class="rx-lib-content">
            <header class="rx-lib-pagehead">
                <div>
                    <span class="rx-lib-kicker"><?= prescription_e($library_shell_kicker) ?></span>
                    <h2><?= prescription_e($library_shell_title) ?></h2>
                    <?php if ($library_shell_description !== ''): ?>
                        <p><?= prescription_e($library_shell_description) ?></p>
                    <?php endif; ?>
                </div>

                <div class="rx-lib-page-actions">
                    <?php if ($library_shell_primary_label !== '' && $library_shell_primary_url !== ''): ?>
                        <a
                            class="rx-lib-btn is-accent"
                            href="<?= prescription_e($library_shell_primary_url) ?>"
                        >
                            <?= prescription_e($library_shell_primary_label) ?>
                        </a>
                    <?php endif; ?>

                    <?php if ($library_shell_secondary_label !== '' && $library_shell_secondary_url !== ''): ?>
                        <a
                            class="rx-lib-btn"
                            href="<?= prescription_e($library_shell_secondary_url) ?>"
                        >
                            <?= prescription_e($library_shell_secondary_label) ?>
                        </a>
                    <?php endif; ?>
                </div>
            </header>
