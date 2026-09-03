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

<style>
.rx-library-shared {
    --lib-accent: <?= prescription_e($library_accent) ?>;
    --lib-panel: #ffffff;
    --lib-soft: #f6f8fa;
    --lib-border: #d0d7de;
    --lib-border-soft: #eaeef2;
    --lib-text: #1f2328;
    --lib-muted: #656d76;
    --lib-blue: #0969da;
    --lib-green: #1f883d;
    --lib-danger: #cf222e;
    color: var(--lib-text);
}

.rx-library-shared,
.rx-library-shared * {
    box-sizing: border-box;
}

.rx-lib-layout {
    display: grid;
    grid-template-columns: 270px minmax(0, 1fr);
    gap: 16px;
    align-items: start;
}

.rx-lib-menu {
    position: sticky;
    top: 80px;
    overflow: hidden;
    border: 1px solid var(--lib-border);
    border-radius: 8px;
    background: var(--lib-panel);
    box-shadow: 0 1px 0 rgba(31, 35, 40, .04);
}

.rx-lib-menu-head {
    padding: 14px 15px;
    border-bottom: 1px solid var(--lib-border);
    background: var(--lib-soft);
}

.rx-lib-menu-head strong,
.rx-lib-menu-head span {
    display: block;
}

.rx-lib-menu-head strong {
    font-size: 13px;
    font-weight: 650;
}

.rx-lib-menu-head span {
    margin-top: 4px;
    color: var(--lib-muted);
    font-size: 11px;
}

.rx-lib-menu-body {
    max-height: calc(100vh - 190px);
    overflow-y: auto;
    padding: 8px;
    scrollbar-width: thin;
}

.rx-lib-menu-group + .rx-lib-menu-group {
    margin-top: 8px;
    padding-top: 8px;
    border-top: 1px solid var(--lib-border-soft);
}

.rx-lib-menu-title {
    display: block;
    padding: 5px 8px;
    color: var(--lib-muted);
    font-size: 10px;
    font-weight: 700;
    letter-spacing: .06em;
    text-transform: uppercase;
}

.rx-lib-menu-link {
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

.rx-lib-menu-link:hover {
    background: var(--lib-soft);
    color: var(--lib-text);
    text-decoration: none;
}

.rx-lib-menu-link.is-active {
    background: #ddf4ff;
    color: var(--lib-blue);
    font-weight: 600;
}

.rx-lib-menu-icon {
    width: 28px;
    height: 28px;
    display: grid;
    place-items: center;
    border: 1px solid var(--lib-border);
    border-radius: 6px;
    background: var(--lib-soft);
    color: #57606a;
    font-size: 10px;
    font-weight: 700;
}

.rx-lib-menu-link.is-active .rx-lib-menu-icon {
    border-color: #b6ddf5;
    background: #ffffff;
    color: var(--lib-blue);
}

.rx-lib-menu-count {
    min-width: 24px;
    padding: 2px 6px;
    border-radius: 999px;
    background: #eaeef2;
    color: var(--lib-muted);
    font-size: 10px;
    font-weight: 600;
    text-align: center;
}

.rx-lib-menu-foot {
    padding: 10px;
    border-top: 1px solid var(--lib-border-soft);
}

.rx-lib-menu-foot a {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    padding: 9px 10px;
    border-radius: 6px;
    color: var(--lib-text);
    font-size: 12px;
    font-weight: 600;
    text-decoration: none;
}

.rx-lib-menu-foot a:hover {
    background: var(--lib-soft);
}

.rx-lib-content {
    min-width: 0;
}

.rx-lib-pagehead {
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

.rx-lib-kicker {
    display: block;
    margin-bottom: 4px;
    color: var(--lib-muted);
    font-size: 12px;
    font-weight: 600;
}

.rx-lib-pagehead h2 {
    margin: 0;
    font-size: 22px;
    font-weight: 650;
    line-height: 1.25;
    letter-spacing: -.02em;
}

.rx-lib-pagehead p {
    max-width: 820px;
    margin: 6px 0 0;
    color: var(--lib-muted);
    font-size: 12px;
    line-height: 1.55;
}

.rx-lib-page-actions,
.rx-lib-actions {
    display: flex;
    align-items: center;
    gap: 7px;
    flex-wrap: wrap;
}

.rx-lib-btn {
    min-height: 34px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    padding: 6px 12px;
    border: 1px solid rgba(27, 31, 36, .15);
    border-radius: 6px;
    background: var(--lib-soft);
    color: var(--lib-text);
    font: inherit;
    font-size: 12px;
    font-weight: 600;
    line-height: 1.4;
    text-decoration: none;
    white-space: nowrap;
    cursor: pointer;
    box-shadow: 0 1px 0 rgba(27, 31, 36, .04);
}

.rx-lib-btn:hover {
    background: #eef1f4;
    color: var(--lib-text);
    text-decoration: none;
}

.rx-lib-btn.is-primary {
    background: var(--lib-green);
    color: #ffffff;
}

.rx-lib-btn.is-primary:hover {
    background: #1a7f37;
    color: #ffffff;
}

.rx-lib-btn.is-accent {
    background: var(--lib-accent);
    color: #ffffff;
}

.rx-lib-btn.is-accent:hover {
    filter: brightness(.94);
    color: #ffffff;
}

.rx-lib-btn.is-danger {
    background: var(--lib-danger);
    color: #ffffff;
}

.rx-lib-btn.is-small {
    min-height: 30px;
    padding: 5px 9px;
    font-size: 11px;
}

.rx-lib-notice,
.rx-lib-error {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    margin-bottom: 16px;
    padding: 12px 14px;
    border: 1px solid #b6ddf5;
    border-radius: 8px;
    background: #ddf4ff;
    color: #0969da;
}

.rx-lib-error {
    border-color: #ff8182;
    background: #ffebe9;
    color: #cf222e;
}

.rx-lib-notice-icon,
.rx-lib-error-icon {
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

.rx-lib-notice strong,
.rx-lib-notice span,
.rx-lib-error strong,
.rx-lib-error p {
    display: block;
}

.rx-lib-notice strong,
.rx-lib-error strong {
    font-size: 12px;
}

.rx-lib-notice span {
    margin-top: 3px;
    color: #0550ae;
    font-size: 11px;
    line-height: 1.5;
}

.rx-lib-error p {
    margin: 3px 0 0;
    font-size: 11px;
    line-height: 1.5;
}

.rx-lib-panel {
    overflow: hidden;
    border: 1px solid var(--lib-border);
    border-radius: 8px;
    background: var(--lib-panel);
    box-shadow: 0 1px 0 rgba(31, 35, 40, .04);
}

.rx-lib-panel-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
    padding: 15px 16px;
    border-bottom: 1px solid var(--lib-border);
    background: var(--lib-soft);
}

.rx-lib-panel-head h3 {
    margin: 0;
    font-size: 16px;
    font-weight: 650;
}

.rx-lib-panel-head p {
    margin: 5px 0 0;
    color: var(--lib-muted);
    font-size: 11px;
    line-height: 1.5;
}

.rx-lib-badge {
    flex: 0 0 auto;
    padding: 4px 8px;
    border: 1px solid var(--lib-border);
    border-radius: 999px;
    background: #ffffff;
    color: var(--lib-muted);
    font-size: 10px;
    font-weight: 600;
}

.rx-lib-panel-body {
    padding: 16px;
}

.rx-lib-filter {
    display: grid;
    grid-template-columns: minmax(220px, 1fr) 180px 160px auto;
    gap: 10px;
    align-items: end;
    margin-bottom: 16px;
}

.rx-lib-field label {
    display: block;
    margin-bottom: 6px;
    color: var(--lib-text);
    font-size: 11px;
    font-weight: 650;
}

.rx-lib-field input,
.rx-lib-field select {
    width: 100%;
    min-height: 38px;
    padding: 7px 10px;
    border: 1px solid var(--lib-border);
    border-radius: 6px;
    background: #ffffff;
    color: var(--lib-text);
    font: inherit;
    font-size: 13px;
    outline: none;
}

.rx-lib-field input:focus,
.rx-lib-field select:focus {
    border-color: var(--lib-blue);
    box-shadow: 0 0 0 3px rgba(9, 105, 218, .15);
}

.rx-lib-field small {
    display: block;
    margin-top: 5px;
    color: var(--lib-muted);
    font-size: 10px;
    line-height: 1.45;
}

.rx-lib-required {
    color: var(--lib-danger);
}

.rx-lib-form-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 16px;
}

.rx-lib-field.is-full {
    grid-column: 1 / -1;
}

.rx-lib-submitbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
    margin: 18px -16px -16px;
    padding: 13px 16px;
    border-top: 1px solid var(--lib-border);
    background: var(--lib-soft);
}

.rx-lib-submitbar p {
    margin: 0;
    color: var(--lib-muted);
    font-size: 11px;
}

.rx-lib-empty {
    padding: 32px 16px;
    border: 1px dashed var(--lib-border);
    border-radius: 8px;
    text-align: center;
}

.rx-lib-empty strong,
.rx-lib-empty span {
    display: block;
}

.rx-lib-empty strong {
    font-size: 14px;
}

.rx-lib-empty span {
    margin-top: 5px;
    color: var(--lib-muted);
    font-size: 11px;
}

.rx-lib-table-wrap {
    overflow: auto;
    border: 1px solid var(--lib-border);
    border-radius: 7px;
}

.rx-lib-table {
    width: 100%;
    min-width: 720px;
    border-collapse: collapse;
}

.rx-lib-table th,
.rx-lib-table td {
    padding: 11px 12px;
    border-bottom: 1px solid var(--lib-border-soft);
    text-align: left;
    vertical-align: middle;
    font-size: 12px;
}

.rx-lib-table th {
    background: var(--lib-soft);
    color: var(--lib-muted);
    font-size: 10px;
    font-weight: 700;
    letter-spacing: .03em;
    text-transform: uppercase;
}

.rx-lib-table tbody tr:last-child td {
    border-bottom: 0;
}

.rx-lib-table tbody tr:hover {
    background: #f8fafc;
}

.rx-lib-subtext {
    display: block;
    margin-top: 3px;
    color: var(--lib-muted);
    font-size: 10px;
}

.rx-lib-source,
.rx-lib-status {
    display: inline-flex;
    align-items: center;
    min-height: 24px;
    padding: 3px 7px;
    border-radius: 999px;
    font-size: 10px;
    font-weight: 650;
}

.rx-lib-source.demo {
    background: #fff8c5;
    color: #9a6700;
}

.rx-lib-source.personal,
.rx-lib-status.active {
    background: #dafbe1;
    color: #1a7f37;
}

.rx-lib-status.inactive {
    background: #eaeef2;
    color: #57606a;
}

.rx-lib-pagination {
    display: flex;
    align-items: center;
    gap: 5px;
    flex-wrap: wrap;
    margin-top: 16px;
}

.rx-lib-pagination a {
    min-width: 32px;
    height: 32px;
    display: grid;
    place-items: center;
    border: 1px solid var(--lib-border);
    border-radius: 6px;
    background: #ffffff;
    color: var(--lib-text);
    font-size: 11px;
    font-weight: 600;
    text-decoration: none;
}

.rx-lib-pagination a.is-active {
    border-color: var(--lib-blue);
    background: var(--lib-blue);
    color: #ffffff;
}

@media (max-width: 1050px) {
    .rx-lib-layout {
        grid-template-columns: 230px minmax(0, 1fr);
    }

    .rx-lib-filter {
        grid-template-columns: 1fr 1fr;
    }

    .rx-lib-filter .rx-lib-actions {
        grid-column: 1 / -1;
    }
}

@media (max-width: 860px) {
    .rx-lib-layout {
        grid-template-columns: 1fr;
    }

    .rx-lib-menu {
        position: static;
    }

    .rx-lib-menu-body {
        max-height: none;
        display: flex;
        gap: 6px;
        overflow-x: auto;
    }

    .rx-lib-menu-group {
        display: contents;
    }

    .rx-lib-menu-group + .rx-lib-menu-group {
        margin: 0;
        padding: 0;
        border: 0;
    }

    .rx-lib-menu-title {
        display: none;
    }

    .rx-lib-menu-link {
        flex: 0 0 auto;
        min-width: max-content;
        border: 1px solid var(--lib-border-soft);
    }

    .rx-lib-menu-foot {
        display: none;
    }
}

@media (max-width: 680px) {
    .rx-lib-pagehead {
        flex-direction: column;
        padding: 15px;
    }

    .rx-lib-pagehead h2 {
        font-size: 19px;
    }

    .rx-lib-page-actions {
        width: 100%;
    }

    .rx-lib-page-actions .rx-lib-btn {
        flex: 1 1 auto;
    }

    .rx-lib-form-grid,
    .rx-lib-filter {
        grid-template-columns: 1fr;
    }

    .rx-lib-filter .rx-lib-actions {
        grid-column: auto;
    }

    .rx-lib-submitbar {
        align-items: stretch;
        flex-direction: column;
    }

    .rx-lib-submitbar .rx-lib-actions {
        width: 100%;
    }

    .rx-lib-submitbar .rx-lib-btn {
        flex: 1 1 0;
    }
}
</style>

<div class="rx-library-shared">
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
