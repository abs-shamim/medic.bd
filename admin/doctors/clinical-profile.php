<?php
/**
 * Clinical Profile section component.
 * GitHub-style clean UI.
 *
 * Removed:
 * services, services_bn,
 * diseases_treated, diseases_treated_bn,
 * procedures, procedures_bn,
 * awards, awards_bn,
 * membership, membership_bn,
 * research_publications, research_publications_bn
 */


/*
|--------------------------------------------------------------------------
| Self-working fallbacks
|--------------------------------------------------------------------------
| This component can stay inside admin/doctors/clinical-profile.php.
| Main doctor-form.php only needs to include/render it.
*/
if (!function_exists('doctor_clinical_profile_e')) {
    function doctor_clinical_profile_e($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('e')) {
    function e($value): string
    {
        return doctor_clinical_profile_e($value);
    }
}

if (!function_exists('doctor_clinical_profile_has_pdo')) {
    function doctor_clinical_profile_has_pdo(): bool
    {
        global $pdo;
        return isset($pdo) && $pdo instanceof PDO;
    }
}

if (!function_exists('doctor_clinical_profile_safe_identifier')) {
    function doctor_clinical_profile_safe_identifier(string $identifier): string
    {
        $identifier = trim($identifier);
        return preg_match('/^[A-Za-z0-9_]+$/', $identifier) ? $identifier : '';
    }
}

if (!function_exists('doctor_component_table_exists')) {
    function doctor_component_table_exists(string $table): bool
    {
        global $pdo;

        if (!doctor_clinical_profile_has_pdo()) {
            return false;
        }

        $table = doctor_clinical_profile_safe_identifier($table);

        if ($table === '') {
            return false;
        }

        try {
            $stmt = $pdo->prepare("
                SELECT COUNT(*)
                FROM INFORMATION_SCHEMA.TABLES
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = :table
            ");
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

        if (!doctor_clinical_profile_has_pdo()) {
            return false;
        }

        $table = doctor_clinical_profile_safe_identifier($table);
        $column = doctor_clinical_profile_safe_identifier($column);

        if ($table === '' || $column === '') {
            return false;
        }

        try {
            $stmt = $pdo->prepare("
                SELECT COUNT(*)
                FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = :table
                  AND COLUMN_NAME = :column
            ");
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

        if (!doctor_clinical_profile_has_pdo()) {
            return;
        }

        $table = doctor_clinical_profile_safe_identifier($table);
        $column = doctor_clinical_profile_safe_identifier($column);

        if ($table === '' || $column === '' || !doctor_component_table_exists($table)) {
            return;
        }

        if (!doctor_component_column_exists($table, $column)) {
            try {
                $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
            } catch (Throwable $e) {
                // Column auto-create should not break page rendering.
            }
        }
    }
}

/**
 * Unique CSS for Clinical Profile.
 */
if (!function_exists('doctor_clinical_profile_css')) {
    function doctor_clinical_profile_css(): void
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

            .gh-clinical-card,
            .gh-clinical-card * {
                box-sizing: border-box;
            }

            .gh-clinical-card {
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

            .gh-clinical-head {
                display: flex;
                align-items: flex-start;
                justify-content: space-between;
                gap: 18px;
                padding: 18px 20px;
                background: linear-gradient(180deg, #ffffff 0%, #f6f8fa 100%);
                border-bottom: 1px solid var(--gh-border);
            }

            .gh-clinical-title {
                display: flex;
                align-items: flex-start;
                gap: 12px;
                min-width: 0;
            }

            .gh-clinical-icon {
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

            .gh-clinical-title h3 {
                margin: 0;
                color: var(--gh-text);
                font-size: 18px;
                line-height: 1.35;
                font-weight: 700 !important;
                letter-spacing: -.02em;
            }

            .gh-clinical-title p {
                margin: 4px 0 0;
                color: var(--gh-muted);
                font-size: 13px;
                line-height: 1.55;
            }

            .gh-clinical-badge {
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

            .gh-clinical-badge-dot {
                width: 8px;
                height: 8px;
                border-radius: 50%;
                background: var(--gh-green);
            }

            .gh-clinical-body {
                padding: 20px;
            }

            .gh-clinical-grid {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 16px;
            }

            .gh-clinical-field {
                display: grid;
                gap: 7px;
                min-width: 0;
            }

            .gh-clinical-field.full {
                grid-column: 1 / -1;
            }

            .gh-clinical-field label {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 10px;
                color: #344054;
                font-size: 13.5px;
                font-weight: 600 !important;
                line-height: 1.35;
            }

            .gh-field-tip {
                color: var(--gh-muted);
                font-size: 11.5px;
                font-weight: 400 !important;
                white-space: nowrap;
            }

            .gh-clinical-field textarea {
                width: 100%;
                min-height: 116px;
                border: 1px solid var(--gh-border);
                border-radius: 6px;
                padding: 10px 12px;
                color: var(--gh-text);
                background: #fff;
                outline: none;
                font-family: inherit;
                font-size: 14px;
                line-height: 1.65;
                resize: vertical;
                box-shadow: inset 0 1px 0 rgba(208,215,222,.2);
            }

            .gh-clinical-field textarea:focus {
                border-color: var(--gh-blue);
                box-shadow: 0 0 0 3px rgba(9,105,218,.12);
            }

            .gh-clinical-field textarea::placeholder {
                color: #8c959f;
            }

            .gh-clinical-note {
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

            .gh-clinical-note-icon {
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

            .gh-clinical-section-title {
                grid-column: 1 / -1;
                display: flex;
                align-items: center;
                gap: 8px;
                margin-top: 4px;
                padding: 10px 12px;
                border: 1px solid var(--gh-border);
                border-radius: 8px;
                background: var(--gh-soft);
                color: var(--gh-text);
                font-size: 13px;
                font-weight: 700 !important;
            }

            .gh-clinical-section-title:before {
                content: "";
                width: 8px;
                height: 8px;
                border-radius: 50%;
                background: var(--gh-blue);
                flex: 0 0 auto;
            }

            @media (max-width: 900px) {
                .gh-clinical-head {
                    flex-direction: column;
                    align-items: stretch;
                }

                .gh-clinical-badge {
                    width: fit-content;
                }

                .gh-clinical-grid {
                    grid-template-columns: 1fr;
                }

                .gh-clinical-field label {
                    align-items: flex-start;
                    flex-direction: column;
                    gap: 4px;
                }

                .gh-field-tip {
                    white-space: normal;
                }
            }
        </style>
        <?php
    }
}

if (!function_exists('doctor_clinical_profile_boot')) {
    function doctor_clinical_profile_boot(): void
    {
        foreach ([
            'education',
            'education_bn',
            'training',
            'training_bn',
            'fellowship',
            'fellowship_bn',
            'expertise',
            'expertise_bn',
            'appointment_note',
            'appointment_note_bn',
        ] as $column) {
            doctor_component_add_column_if_missing('doctors', $column, "TEXT NULL");
        }
    }
}

doctor_clinical_profile_boot();

if (!function_exists('doctor_clinical_profile_field')) {
    function doctor_clinical_profile_field(array $doctor, string $name, string $label, string $tip, string $placeholder, bool $is_bangla = false): void
    {
        ?>
        <div class="gh-clinical-field">
            <label>
                <span><?= e($label) ?></span>
                <span class="gh-field-tip"><?= e($tip) ?></span>
            </label>
            <textarea
                name="<?= e($name) ?>"
                placeholder="<?= e($placeholder) ?>"
                <?= $is_bangla ? 'lang="bn"' : '' ?>
            ><?= e($doctor[$name] ?? '') ?></textarea>
        </div>
        <?php
    }
}

if (!function_exists('doctor_clinical_profile_render')) {
    function doctor_clinical_profile_render(array $context = []): void
    {
        extract($context, EXTR_SKIP);

        $doctor = isset($doctor) && is_array($doctor) ? $doctor : [];

        doctor_clinical_profile_css();

        $clinical_fields = [
            'education',
            'education_bn',
            'training',
            'training_bn',
            'fellowship',
            'fellowship_bn',
            'expertise',
            'expertise_bn',
            'appointment_note',
            'appointment_note_bn',
        ];

        $filled_count = 0;

        foreach ($clinical_fields as $field) {
            if (trim((string)($doctor[$field] ?? '')) !== '') {
                $filled_count++;
            }
        }
        ?>

        <div class="gh-clinical-card">
            <div class="gh-clinical-head">
                <div class="gh-clinical-title">
                    <div class="gh-clinical-icon">CP</div>
                    <div>
                        <h3>Clinical Profile</h3>
                        <p>Manage education, training, fellowship, expertise and appointment instructions.</p>
                    </div>
                </div>

                <div class="gh-clinical-badge">
                    <span class="gh-clinical-badge-dot"></span>
                    <span><?= e((string)$filled_count) ?> / <?= e((string)count($clinical_fields)) ?> filled</span>
                </div>
            </div>

            <div class="gh-clinical-body">
                <div class="gh-clinical-note">
                    <span class="gh-clinical-note-icon">i</span>
                    <span>
                        Write English information on the left and Bangla information on the right. For list-style content, write one item per line so it can be displayed cleanly on the doctor profile page.
                    </span>
                </div>

                <div class="gh-clinical-grid">
                    <div class="gh-clinical-section-title">Education & Training</div>

                    <?php
                    doctor_clinical_profile_field(
                        $doctor,
                        'education',
                        'Education',
                        'Degrees and institutions',
                        "Example:\nMBBS - Dhaka Medical College\nFCPS - Bangladesh College of Physicians and Surgeons"
                    );

                    doctor_clinical_profile_field(
                        $doctor,
                        'education_bn',
                        'Education Bangla',
                        'বাংলা শিক্ষাগত যোগ্যতা',
                        "উদাহরণ:\nএমবিবিএস - ঢাকা মেডিকেল কলেজ\nএফসিপিএস - বাংলাদেশ কলেজ অব ফিজিশিয়ানস অ্যান্ড সার্জনস",
                        true
                    );

                    doctor_clinical_profile_field(
                        $doctor,
                        'training',
                        'Training',
                        'Clinical training, workshops',
                        'Special training, clinical training, workshops, hands-on courses...'
                    );

                    doctor_clinical_profile_field(
                        $doctor,
                        'training_bn',
                        'Training Bangla',
                        'বাংলা ট্রেনিং তথ্য',
                        'বিশেষ ট্রেনিং, ক্লিনিক্যাল ট্রেনিং, ওয়ার্কশপ, হাতে-কলমে কোর্স...',
                        true
                    );

                    doctor_clinical_profile_field(
                        $doctor,
                        'fellowship',
                        'Fellowship',
                        'Higher qualification',
                        'Fellowships and higher qualifications...'
                    );

                    doctor_clinical_profile_field(
                        $doctor,
                        'fellowship_bn',
                        'Fellowship Bangla',
                        'বাংলা ফেলোশিপ তথ্য',
                        'ফেলোশিপ এবং উচ্চতর যোগ্যতা...',
                        true
                    );
                    ?>

                    <div class="gh-clinical-section-title">Expertise</div>

                    <?php
                    doctor_clinical_profile_field(
                        $doctor,
                        'expertise',
                        'Expertise',
                        'One item per line',
                        "Heart disease\nHigh blood pressure\nDiabetes care"
                    );

                    doctor_clinical_profile_field(
                        $doctor,
                        'expertise_bn',
                        'Expertise Bangla',
                        'প্রতি লাইনে একটি বিষয়',
                        "হৃদরোগ\nউচ্চ রক্তচাপ\nডায়াবেটিস চিকিৎসা",
                        true
                    );
                    ?>

                    <div class="gh-clinical-section-title">Appointment Instruction</div>

                    <?php
                    doctor_clinical_profile_field(
                        $doctor,
                        'appointment_note',
                        'Appointment Note',
                        'Shown to patients',
                        "Example:\nPlease bring old reports.\nSerial starts at 9 AM.\nEmergency patients should call before visiting."
                    );

                    doctor_clinical_profile_field(
                        $doctor,
                        'appointment_note_bn',
                        'Appointment Note Bangla',
                        'রোগীদের দেখানো হবে',
                        "উদাহরণ:\nপুরোনো রিপোর্ট সঙ্গে আনুন।\nসিরিয়াল সকাল ৯টায় শুরু হয়।\nজরুরি রোগীরা আসার আগে ফোন করুন।",
                        true
                    );
                    ?>
                </div>
            </div>
        </div>

        <?php
    }
}



if (!function_exists('doctor_clinical_render')) {
    function doctor_clinical_render(array $context = []): void
    {
        doctor_clinical_profile_render($context);
    }
}

if (!function_exists('doctor_clinical_profile_section_render')) {
    function doctor_clinical_profile_section_render(array $context = []): void
    {
        doctor_clinical_profile_render($context);
    }
}

if (!empty($GLOBALS['doctor_form_auto_render_sections'])) {
    doctor_clinical_profile_render($GLOBALS['doctor_form_context'] ?? []);
}