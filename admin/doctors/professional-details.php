<?php
/**
 * Professional Details section component.
 * GitHub-style clean UI.
 * CSS conflict fixed by using a unique CSS function.
 */

if (!function_exists('e')) {
    function e($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('doctor_component_safe_identifier')) {
    function doctor_component_safe_identifier(string $identifier): string
    {
        return preg_match('/^[A-Za-z0-9_]+$/', $identifier) ? $identifier : '';
    }
}

if (!function_exists('doctor_component_has_pdo')) {
    function doctor_component_has_pdo(): bool
    {
        global $pdo;
        return isset($pdo) && $pdo instanceof PDO;
    }
}

if (!function_exists('doctor_component_table_exists')) {
    function doctor_component_table_exists(string $table): bool
    {
        global $pdo;

        $table = doctor_component_safe_identifier($table);

        if ($table === '' || !doctor_component_has_pdo()) {
            return false;
        }

        try {
            $stmt = $pdo->prepare("\n                SELECT COUNT(*)\n                FROM INFORMATION_SCHEMA.TABLES\n                WHERE TABLE_SCHEMA = DATABASE()\n                  AND TABLE_NAME = :table\n            ");
            $stmt->execute([':table' => $table]);

            return (int)$stmt->fetchColumn() > 0;
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('doctor_component_column_exists')) {
    function doctor_component_column_exists(string $table, string $column): bool
    {
        global $pdo;

        $table = doctor_component_safe_identifier($table);
        $column = doctor_component_safe_identifier($column);

        if ($table === '' || $column === '' || !doctor_component_has_pdo()) {
            return false;
        }

        try {
            $stmt = $pdo->prepare("\n                SELECT COUNT(*)\n                FROM INFORMATION_SCHEMA.COLUMNS\n                WHERE TABLE_SCHEMA = DATABASE()\n                  AND TABLE_NAME = :table\n                  AND COLUMN_NAME = :column\n            ");
            $stmt->execute([
                ':table' => $table,
                ':column' => $column,
            ]);

            return (int)$stmt->fetchColumn() > 0;
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('doctor_component_add_column_if_missing')) {
    function doctor_component_add_column_if_missing(string $table, string $column, string $definition): void
    {
        global $pdo;

        $table = doctor_component_safe_identifier($table);
        $column = doctor_component_safe_identifier($column);

        if ($table === '' || $column === '' || trim($definition) === '' || !doctor_component_has_pdo()) {
            return;
        }

        if (!doctor_component_table_exists($table)) {
            return;
        }

        if (doctor_component_column_exists($table, $column)) {
            return;
        }

        try {
            $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
        } catch (Throwable $e) {
            // Component boot should never break page rendering.
        }
    }
}

/**
 * Unique CSS for Professional Details.
 * Do not use doctor_component_css() here because other section files may define it first.
 */
if (!function_exists('doctor_professional_details_css')) {
    function doctor_professional_details_css(): void
    {
        static $printed = false;

        if ($printed) {
            return;
        }

        $printed = true;
        ?>
        <style>
            :root {
                --gh-bg: #f6f8fa;
                --gh-card: #ffffff;
                --gh-text: #24292f;
                --gh-muted: #57606a;
                --gh-border: #d0d7de;
                --gh-border-soft: #d8dee4;
                --gh-blue: #0969da;
                --gh-blue-soft: #ddf4ff;
                --gh-green: #1a7f37;
                --gh-red: #cf222e;
                --gh-soft: #f6f8fa;
                --gh-soft-hover: #eef1f4;
                --gh-shadow: 0 1px 0 rgba(27,31,36,.04);
            }

            .gh-pro-details-card,
            .gh-pro-details-card * {
                box-sizing: border-box;
            }

            .gh-pro-details-card {
                width: 100%;
                margin-bottom: 20px;
                border: 1px solid var(--gh-border);
                border-radius: 12px;
                background: var(--gh-card);
                box-shadow: var(--gh-shadow);
                overflow: hidden;
                color: var(--gh-text);
                font-family: inherit;
            }

            .gh-pro-details-head {
                display: flex;
                align-items: flex-start;
                justify-content: space-between;
                gap: 18px;
                padding: 18px 20px;
                background: linear-gradient(180deg, #ffffff 0%, #f6f8fa 100%);
                border-bottom: 1px solid var(--gh-border);
            }

            .gh-pro-details-title {
                display: flex;
                align-items: flex-start;
                gap: 12px;
                min-width: 0;
            }

            .gh-pro-details-icon {
                width: 36px;
                height: 36px;
                border-radius: 10px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                color: var(--gh-blue);
                background: var(--gh-blue-soft);
                border: 1px solid rgba(84,174,255,.45);
                font-size: 13px;
                font-weight: 800 !important;
                flex: 0 0 auto;
            }

            .gh-pro-details-title h3 {
                margin: 0;
                color: var(--gh-text);
                font-size: 18px;
                line-height: 1.35;
                font-weight: 700 !important;
                letter-spacing: -.02em;
            }

            .gh-pro-details-title p {
                margin: 4px 0 0;
                color: var(--gh-muted);
                font-size: 13px;
                line-height: 1.55;
            }

            .gh-pro-details-badge {
                display: inline-flex;
                align-items: center;
                gap: 7px;
                padding: 6px 10px;
                border-radius: 999px;
                background: #fff;
                border: 1px solid var(--gh-border);
                color: var(--gh-muted);
                font-size: 12px;
                white-space: nowrap;
                flex: 0 0 auto;
            }

            .gh-pro-details-badge-dot {
                width: 8px;
                height: 8px;
                border-radius: 50%;
                background: var(--gh-green);
            }

            .gh-pro-details-body {
                padding: 20px;
            }

            .gh-pro-details-note {
                display: flex;
                align-items: flex-start;
                gap: 10px;
                margin-bottom: 16px;
                padding: 12px 14px;
                border: 1px solid var(--gh-border);
                border-radius: 10px;
                background: var(--gh-soft);
                color: var(--gh-muted);
                font-size: 12.5px;
                line-height: 1.6;
            }

            .gh-pro-details-note-icon {
                width: 18px;
                height: 18px;
                border-radius: 50%;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                background: var(--gh-blue-soft);
                color: var(--gh-blue);
                font-size: 12px;
                font-weight: 800 !important;
                flex: 0 0 auto;
                margin-top: 1px;
            }

            .gh-pro-details-grid {
                display: grid;
                grid-template-columns: repeat(4, minmax(0, 1fr));
                gap: 16px;
            }

            .gh-pro-field {
                display: grid;
                gap: 7px;
                min-width: 0;
            }

            .gh-pro-field label {
                color: #344054;
                font-size: 13.5px;
                font-weight: 600 !important;
                line-height: 1.35;
            }

            .gh-pro-label-pair {
                display: grid;
                grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
                gap: 10px;
                align-items: center;
            }

            .gh-pro-label-pair .bn {
                text-align: right;
                color: var(--gh-muted);
                font-weight: 600 !important;
            }

            .gh-pro-field input {
                width: 100%;
                min-height: 42px;
                border: 1px solid var(--gh-border);
                border-radius: 6px;
                padding: 10px 12px;
                color: var(--gh-text);
                background: #fff;
                outline: none;
                font-family: inherit;
                font-size: 14px;
                line-height: 1.45;
                box-shadow: inset 0 1px 0 rgba(208,215,222,.2);
            }

            .gh-pro-field input:focus {
                border-color: var(--gh-blue);
                box-shadow: 0 0 0 3px rgba(9,105,218,.12);
            }

            .gh-pro-field small {
                color: var(--gh-muted);
                font-size: 12px;
                line-height: 1.5;
            }

            .gh-money-input {
                position: relative;
            }

            .gh-money-input input {
                padding-left: 38px;
            }

            .gh-money-prefix {
                position: absolute;
                left: 12px;
                top: 50%;
                transform: translateY(-50%);
                color: var(--gh-muted);
                font-size: 13px;
                font-weight: 600 !important;
                pointer-events: none;
            }

            .gh-year-input {
                position: relative;
            }

            .gh-year-input input {
                padding-left: 40px;
            }

            .gh-year-prefix {
                position: absolute;
                left: 12px;
                top: 50%;
                transform: translateY(-50%);
                color: var(--gh-muted);
                font-size: 13px;
                font-weight: 600 !important;
                pointer-events: none;
            }

            .gh-pro-summary {
                display: grid;
                grid-template-columns: repeat(3, minmax(0, 1fr));
                gap: 12px;
                margin-top: 16px;
            }

            .gh-pro-summary-card {
                padding: 12px 14px;
                border: 1px solid var(--gh-border);
                border-radius: 10px;
                background: #fff;
            }

            .gh-pro-summary-card strong {
                display: block;
                color: var(--gh-text);
                font-size: 14px;
                line-height: 1.4;
                margin-bottom: 3px;
            }

            .gh-pro-summary-card span {
                color: var(--gh-muted);
                font-size: 12px;
                line-height: 1.5;
            }

            @media (max-width: 1100px) {
                .gh-pro-details-grid {
                    grid-template-columns: repeat(2, minmax(0, 1fr));
                }
            }

            @media (max-width: 900px) {
                .gh-pro-details-head {
                    flex-direction: column;
                    align-items: stretch;
                }

                .gh-pro-details-badge {
                    width: fit-content;
                }

                .gh-pro-details-grid,
                .gh-pro-summary {
                    grid-template-columns: 1fr;
                }

                .gh-pro-label-pair {
                    grid-template-columns: 1fr;
                    gap: 4px;
                }

                .gh-pro-label-pair .bn {
                    text-align: left;
                }
            }
        </style>
        <?php
    }
}

if (!function_exists('doctor_professional_details_boot')) {
    function doctor_professional_details_boot(): void
    {
        $columns = [
            'experience_years' => "INT NULL",
            'consultation_fee' => "DECIMAL(10,2) DEFAULT 0",
            'follow_up_fee' => "DECIMAL(10,2) DEFAULT 0",
            'video_consultation_fee' => "DECIMAL(10,2) DEFAULT 0",
            'updated_at' => "DATETIME NULL",
        ];

        foreach ($columns as $column => $definition) {
            doctor_component_add_column_if_missing('doctors', $column, $definition);
        }
    }
}

doctor_professional_details_boot();

if (!function_exists('doctor_professional_details_render')) {
    function doctor_professional_details_render(array $context = []): void
    {
        extract($context, EXTR_SKIP);

        $doctor = isset($doctor) && is_array($doctor) ? $doctor : [];

        doctor_professional_details_css();

        $current_year = (int)date('Y');

        $starting_year_raw = trim((string)($doctor['experience_years'] ?? ''));
        $starting_year_value = '';

        if ($starting_year_raw !== '' && (int)$starting_year_raw > 0) {
            $starting_year_value = (string)(int)$starting_year_raw;
        }

        $starting_year = $starting_year_value !== '' ? (int)$starting_year_value : 0;
        $experience_text = 'Not set yet';

        if ($starting_year >= 1900 && $starting_year <= $current_year) {
            $total_years = max(0, $current_year - $starting_year);
            $experience_text = $total_years . ' year' . ($total_years === 1 ? '' : 's') . ' experience';
        }

        $consultation_fee = (float)($doctor['consultation_fee'] ?? 0);
        $follow_up_fee = (float)($doctor['follow_up_fee'] ?? 0);
        $video_fee = (float)($doctor['video_consultation_fee'] ?? 0);

        $fee_count = 0;
        if ($consultation_fee > 0) { $fee_count++; }
        if ($follow_up_fee > 0) { $fee_count++; }
        if ($video_fee > 0) { $fee_count++; }
        ?>

        <div class="gh-pro-details-card">
            <div class="gh-pro-details-head">
                <div class="gh-pro-details-title">
                    <div class="gh-pro-details-icon">PRO</div>
                    <div>
                        <h3>Professional Details</h3>
                        <p>Set starting year, consultation fee, follow-up fee and video consultation fee.</p>
                    </div>
                </div>

                <div class="gh-pro-details-badge">
                    <span class="gh-pro-details-badge-dot"></span>
                    <span><?= e((string)$fee_count) ?> / 3 fees set</span>
                </div>
            </div>

            <div class="gh-pro-details-body">
                <div class="gh-pro-details-note">
                    <span class="gh-pro-details-note-icon">i</span>
                    <span>
                        Starting Year is optional. Keep it empty if you do not want to show experience. Fee fields are optional, but adding them improves patient clarity.
                    </span>
                </div>

                <div class="gh-pro-details-grid">
                    <div class="gh-pro-field">
                        <label>Starting Year</label>
                        <div class="gh-year-input">
                            <span class="gh-year-prefix">YR</span>
                            <input
                                type="number"
                                name="experience_years"
                                min="1900"
                                max="<?= e((string)$current_year) ?>"
                                placeholder="Leave empty or enter 2010"
                                value="<?= e($starting_year_value) ?>"
                            >
                        </div>
                        <small>Optional. Leave empty if you do not want to show experience.</small>
                    </div>

                    <div class="gh-pro-field">
                        <label>Consultation Fee</label>
                        <div class="gh-money-input">
                            <span class="gh-money-prefix">৳</span>
                            <input
                                type="number"
                                step="0.01"
                                min="0"
                                name="consultation_fee"
                                placeholder="1000"
                                value="<?= e((string)($doctor['consultation_fee'] ?? '')) ?>"
                            >
                        </div>
                        <small>Main chamber visit fee</small>
                    </div>

                    <div class="gh-pro-field">
                        <label>Follow-up Fee</label>
                        <div class="gh-money-input">
                            <span class="gh-money-prefix">৳</span>
                            <input
                                type="number"
                                step="0.01"
                                min="0"
                                name="follow_up_fee"
                                placeholder="500"
                                value="<?= e((string)($doctor['follow_up_fee'] ?? '')) ?>"
                            >
                        </div>
                        <small>Fee for next visit or report review</small>
                    </div>

                    <div class="gh-pro-field">
                        <label>Video Consultation Fee</label>
                        <div class="gh-money-input">
                            <span class="gh-money-prefix">৳</span>
                            <input
                                type="number"
                                step="0.01"
                                min="0"
                                name="video_consultation_fee"
                                placeholder="800"
                                value="<?= e((string)($doctor['video_consultation_fee'] ?? '')) ?>"
                            >
                        </div>
                        <small>Online consultation fee</small>
                    </div>
                </div>

                <div class="gh-pro-summary">
                    <div class="gh-pro-summary-card">
                        <strong><?= e($experience_text) ?></strong>
                        <span>Calculated from Starting Year</span>
                    </div>

                    <div class="gh-pro-summary-card">
                        <strong><?= $consultation_fee > 0 ? '৳' . e(number_format($consultation_fee, 0)) : 'Not set' ?></strong>
                        <span>Consultation Fee</span>
                    </div>

                    <div class="gh-pro-summary-card">
                        <strong><?= $video_fee > 0 ? '৳' . e(number_format($video_fee, 0)) : 'Not set' ?></strong>
                        <span>Video Consultation Fee</span>
                    </div>
                </div>
            </div>
        </div>

        <?php
    }
}


if (!function_exists('doctor_professional_render')) {
    function doctor_professional_render(array $context = []): void
    {
        doctor_professional_details_render($context);
    }
}

if (!function_exists('doctor_professional_details_section_render')) {
    function doctor_professional_details_section_render(array $context = []): void
    {
        doctor_professional_details_render($context);
    }
}

if (!empty($GLOBALS['doctor_form_auto_render_sections'])) {
    doctor_professional_details_render($GLOBALS['doctor_form_context'] ?? []);
}