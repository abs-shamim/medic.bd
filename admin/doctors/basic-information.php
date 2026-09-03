<?php
/**
 * Basic Information section component.
 * GitHub-style clean UI.
 * Horizontal language checkboxes + hidden Bangla value.
 * Gender English select + hidden Bangla value.
 * CSS conflict fixed by using a unique CSS function.
 */

if (!function_exists('doctor_component_table_exists')) {
    function doctor_component_table_exists(string $table): bool
    {
        global $pdo;

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

        if (!doctor_component_table_exists($table)) {
            return;
        }

        if (!doctor_component_column_exists($table, $column)) {
            $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
        }
    }
}

if (!function_exists('doctor_basic_information_e')) {
    function doctor_basic_information_e($value): string
    {
        if (function_exists('e')) {
            return e($value);
        }

        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('doctor_basic_information_site_url')) {
    function doctor_basic_information_site_url(string $path = ''): string
    {
        if (function_exists('site_url')) {
            return site_url($path);
        }

        $base = '';

        if (!empty($_SERVER['HTTP_HOST'])) {
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $base = $scheme . '://' . $_SERVER['HTTP_HOST'];
        }

        return rtrim($base, '/') . '/' . ltrim($path, '/');
    }
}

if (!function_exists('doctor_basic_information_specialty_name')) {
    function doctor_basic_information_specialty_name(array $specialty): string
    {
        foreach (['name', 'name_en', 'title'] as $key) {
            if (!empty($specialty[$key])) {
                return trim((string)$specialty[$key]);
            }
        }

        return 'Specialty #' . (int)($specialty['id'] ?? 0);
    }
}

if (!function_exists('doctor_basic_information_language_options')) {
    function doctor_basic_information_language_options(): array
    {
        return [
            'Bangla' => 'বাংলা',
            'English' => 'ইংরেজি',
            'Hindi' => 'হিন্দি',
            'Arabic' => 'আরবি',
            'Urdu' => 'উর্দু',
        ];
    }
}

if (!function_exists('doctor_basic_information_gender_bn')) {
    function doctor_basic_information_gender_bn(string $gender): string
    {
        $map = [
            'Male' => 'পুরুষ',
            'Female' => 'মহিলা',
            'Other' => 'অন্যান্য',
        ];

        return $map[$gender] ?? '';
    }
}

/**
 * Unique CSS function for Basic Information.
 * Do not use doctor_component_css() here because other section files may define it first.
 */
if (!function_exists('doctor_basic_information_css')) {
    function doctor_basic_information_css(): void
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
                --gh-green-btn: #2da44e;
                --gh-green-btn-hover: #1f883d;
                --gh-red: #cf222e;
                --gh-soft: #f6f8fa;
                --gh-soft-hover: #eef1f4;
                --gh-shadow: 0 1px 0 rgba(27,31,36,.04);
            }

            .gh-basic-card,
            .gh-basic-card * {
                box-sizing: border-box;
            }

            .gh-basic-card {
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

            .gh-basic-head {
                display: flex;
                align-items: flex-start;
                justify-content: space-between;
                gap: 18px;
                padding: 18px 20px;
                background: linear-gradient(180deg, #ffffff 0%, #f6f8fa 100%);
                border-bottom: 1px solid var(--gh-border);
            }

            .gh-basic-title {
                display: flex;
                gap: 12px;
                align-items: flex-start;
                min-width: 0;
            }

            .gh-basic-icon {
                width: 36px;
                height: 36px;
                border-radius: 10px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                color: var(--gh-blue);
                background: var(--gh-blue-soft);
                border: 1px solid rgba(84,174,255,.45);
                font-size: 17px;
                flex: 0 0 auto;
            }

            .gh-basic-title h3 {
                margin: 0;
                color: var(--gh-text);
                font-size: 18px;
                line-height: 1.35;
                font-weight: 700 !important;
                letter-spacing: -.02em;
            }

            .gh-basic-title p {
                margin: 4px 0 0;
                color: var(--gh-muted);
                font-size: 13px;
                line-height: 1.55;
            }

            .gh-basic-status {
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

            .gh-basic-status-dot {
                width: 8px;
                height: 8px;
                border-radius: 50%;
                background: var(--gh-green);
            }

            .gh-basic-body {
                padding: 20px;
            }

            .gh-form-grid {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 16px;
            }

            .gh-field {
                display: grid;
                gap: 7px;
                min-width: 0;
            }

            .gh-field.full {
                grid-column: 1 / -1;
            }

            .gh-field label {
                color: #344054;
                font-size: 13.5px;
                font-weight: 600 !important;
                line-height: 1.35;
            }

            .gh-required {
                color: var(--gh-red);
                margin-left: 4px;
                font-weight: 800 !important;
            }

            .gh-field input,
            .gh-field select,
            .gh-field textarea {
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

            .gh-field input:focus,
            .gh-field select:focus,
            .gh-field textarea:focus {
                border-color: var(--gh-blue);
                box-shadow: 0 0 0 3px rgba(9,105,218,.12);
            }

            .gh-field input[readonly] {
                color: var(--gh-muted);
                background: var(--gh-soft);
            }

            .gh-field small {
                color: var(--gh-muted);
                font-size: 12px;
                line-height: 1.5;
            }

            /* Horizontal checkbox style */
            .gh-check-grid {
                display: flex;
                flex-wrap: wrap;
                align-items: center;
                gap: 18px 24px;
                margin-top: 2px;
            }

            .gh-check-item {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                width: auto;
                color: var(--gh-text);
                font-size: 14px;
                font-weight: 400 !important;
                line-height: 1.4;
                cursor: pointer;
                user-select: none;
            }

            .gh-check-item input {
                width: 15px !important;
                height: 15px !important;
                min-height: 15px !important;
                margin: 0;
                padding: 0;
                border: 1px solid #8c959f;
                border-radius: 2px;
                box-shadow: none;
                cursor: pointer;
                accent-color: var(--gh-blue);
            }

            .gh-check-item span {
                color: var(--gh-text);
                font-size: 14px;
                font-weight: 400 !important;
            }

            .gh-slug-box {
                display: grid;
                grid-template-columns: minmax(0, 1fr) auto;
                gap: 10px;
                align-items: end;
            }

            .gh-btn {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                min-height: 42px;
                padding: 9px 14px;
                border-radius: 6px;
                border: 1px solid rgba(27,31,36,.15);
                cursor: pointer;
                font-family: inherit;
                font-size: 13.5px;
                font-weight: 600 !important;
                text-decoration: none;
                white-space: nowrap;
                transition: .12s ease;
            }

            .gh-btn-muted {
                background: var(--gh-soft);
                color: var(--gh-text);
            }

            .gh-btn-muted:hover {
                background: var(--gh-soft-hover);
            }

            .gh-btn-success {
                color: #fff;
                background: var(--gh-green-btn);
            }

            .gh-btn-success:hover {
                color: #fff;
                background: var(--gh-green-btn-hover);
            }

            .gh-btn[disabled] {
                opacity: .62;
                cursor: not-allowed;
            }

            .gh-slug-help {
                display: flex;
                flex-wrap: wrap;
                align-items: center;
                gap: 8px;
                margin-top: 2px;
                word-break: break-word;
            }

            .gh-url-pill {
                display: inline-flex;
                align-items: center;
                padding: 4px 8px;
                border-radius: 999px;
                background: var(--gh-blue-soft);
                color: var(--gh-blue);
                font-size: 12px;
                font-weight: 700 !important;
                flex: 0 0 auto;
            }

            .gh-hint-box {
                grid-column: 1 / -1;
                display: flex;
                align-items: flex-start;
                gap: 10px;
                padding: 12px 14px;
                border: 1px solid var(--gh-border);
                border-radius: 10px;
                background: var(--gh-soft);
                color: var(--gh-muted);
                font-size: 12.5px;
                line-height: 1.6;
            }

            .gh-hint-icon {
                width: 18px;
                height: 18px;
                border-radius: 50%;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                background: var(--gh-blue-soft);
                color: var(--gh-blue);
                font-size: 12px;
                font-weight: 700 !important;
                flex: 0 0 auto;
                margin-top: 1px;
            }

            .gh-error-field {
                border-color: var(--gh-red) !important;
                box-shadow: 0 0 0 3px rgba(207,34,46,.12) !important;
            }

            @media (max-width: 900px) {
                .gh-basic-head {
                    flex-direction: column;
                    align-items: stretch;
                }

                .gh-basic-status {
                    width: fit-content;
                }

                .gh-form-grid {
                    grid-template-columns: 1fr;
                }

                .gh-slug-box {
                    grid-template-columns: 1fr;
                }

                .gh-btn {
                    width: 100%;
                }

                .gh-check-grid {
                    gap: 14px 18px;
                }
            }
        </style>
        <?php
    }
}

if (!function_exists('doctor_basic_information_normalize_gender')) {
    function doctor_basic_information_normalize_gender($value): string
    {
        $value = strtolower(trim((string)$value));

        if ($value === 'male') {
            return 'Male';
        }

        if ($value === 'female') {
            return 'Female';
        }

        if ($value === 'other') {
            return 'Other';
        }

        return '';
    }
}

if (!defined('DOCTOR_FORM_MAIN_CONTEXT') && !function_exists('doctor_form_normalize_gender')) {
    function doctor_form_normalize_gender($value): string
    {
        return doctor_basic_information_normalize_gender($value);
    }
}

if (!function_exists('doctor_basic_information_boot')) {
    function doctor_basic_information_boot(): void
    {
        $columns = [
            'name' => "VARCHAR(255) NULL",
            'name_bn' => "VARCHAR(255) NULL",
            'slug' => "VARCHAR(255) NULL",
            'degree' => "VARCHAR(255) NULL",
            'degree_bn' => "VARCHAR(255) NULL",
            'designation' => "VARCHAR(255) NULL",
            'designation_bn' => "VARCHAR(255) NULL",
            'bmdc_number' => "VARCHAR(80) NULL",
            'gender' => "VARCHAR(30) NULL",
            'gender_bn' => "VARCHAR(30) NULL",
            'languages' => "VARCHAR(255) NULL",
            'languages_bn' => "VARCHAR(255) NULL",
            'specialty_id' => "INT DEFAULT 0",
            'hospital_id' => "INT DEFAULT 0",
            'primary_hospital' => "VARCHAR(255) NULL",
            'primary_hospital_bn' => "VARCHAR(255) NULL",
            'created_at' => "DATETIME NULL",
            'updated_at' => "DATETIME NULL",
        ];

        foreach ($columns as $column => $definition) {
            doctor_component_add_column_if_missing('doctors', $column, $definition);
        }
    }
}

doctor_basic_information_boot();

if (!function_exists('doctor_basic_information_render')) {
    function doctor_basic_information_render(array $context = []): void
    {
        extract($context, EXTR_SKIP);

        doctor_basic_information_css();

        $doctor_gender_value = function_exists('doctor_form_normalize_gender')
            ? doctor_form_normalize_gender($doctor['gender'] ?? '')
            : doctor_basic_information_normalize_gender($doctor['gender'] ?? '');

        $doctor_gender_bn_value = trim((string)($doctor['gender_bn'] ?? ''));

        if ($doctor_gender_bn_value === '') {
            $doctor_gender_bn_value = doctor_basic_information_gender_bn($doctor_gender_value);
        }

        $doctor_slug_value = trim((string)($doctor['slug'] ?? ''));
        $doctor_has_id = !empty($id);
        $doctor_name = trim((string)($doctor['name'] ?? ''));
        $doctor_hospital_id = (int)($doctor['hospital_id'] ?? 0);

        $language_options = doctor_basic_information_language_options();

        $selected_languages = array_filter(array_map('trim', explode(',', (string)($doctor['languages'] ?? ''))));
        $selected_languages_bn = array_filter(array_map('trim', explode(',', (string)($doctor['languages_bn'] ?? ''))));

        if (empty($selected_languages) && !empty($selected_languages_bn)) {
            foreach ($language_options as $language_en => $language_bn) {
                if (in_array($language_bn, $selected_languages_bn, true)) {
                    $selected_languages[] = $language_en;
                }
            }
        }
        ?>

        <div class="gh-basic-card">
            <div class="gh-basic-head">
                <div class="gh-basic-title">
                    <div class="gh-basic-icon">DR</div>
                    <div>
                        <h3>Basic Information</h3>
                        <p>
                            <?= $doctor_name !== ''
                                ? 'Update identity, specialty, slug and primary profile details for ' . doctor_basic_information_e($doctor_name) . '.'
                                : 'Add doctor identity, specialty, slug and primary profile details.'
                            ?>
                        </p>
                    </div>
                </div>

                <div class="gh-basic-status">
                    <span class="gh-basic-status-dot"></span>
                    <span><?= $doctor_has_id ? 'Saved profile' : 'New profile' ?></span>
                </div>
            </div>

            <div class="gh-basic-body">
                <div class="gh-form-grid">
                    <div class="gh-field">
                        <label>Doctor Name <span class="gh-required">*</span></label>
                        <input
                            type="text"
                            name="name"
                            placeholder="Example: Dr. Ahmed Hasan"
                            value="<?= doctor_basic_information_e($doctor['name'] ?? '') ?>"
                            required
                        >
                    </div>

                    <div class="gh-field">
                        <label>Doctor Name Bangla</label>
                        <input
                            type="text"
                            name="name_bn"
                            placeholder="উদাহরণ: ডা. আহমেদ হাসান"
                            value="<?= doctor_basic_information_e($doctor['name_bn'] ?? '') ?>"
                        >
                    </div>

                    <div class="gh-field">
                        <label>Degree <span class="gh-required">*</span></label>
                        <input
                            type="text"
                            name="degree"
                            placeholder="MBBS, FCPS, MD"
                            value="<?= doctor_basic_information_e($doctor['degree'] ?? '') ?>"
                            required
                        >
                    </div>

                    <div class="gh-field">
                        <label>Degree Bangla</label>
                        <input
                            type="text"
                            name="degree_bn"
                            placeholder="এমবিবিএস, এফসিপিএস, এমডি"
                            value="<?= doctor_basic_information_e($doctor['degree_bn'] ?? '') ?>"
                        >
                    </div>

                    <div class="gh-field">
                        <label>Designation <span class="gh-required">*</span></label>
                        <input
                            type="text"
                            name="designation"
                            placeholder="Consultant, Professor, Specialist"
                            value="<?= doctor_basic_information_e($doctor['designation'] ?? '') ?>"
                            required
                        >
                    </div>

                    <div class="gh-field">
                        <label>Designation Bangla</label>
                        <input
                            type="text"
                            name="designation_bn"
                            placeholder="কনসালটেন্ট, অধ্যাপক, বিশেষজ্ঞ"
                            value="<?= doctor_basic_information_e($doctor['designation_bn'] ?? '') ?>"
                        >
                    </div>
                  
                    <div class="gh-field">
                        <label>Primary Hospital <span class="gh-required">*</span></label>
                        <input
                            type="text"
                            name="primary_hospital"
                            placeholder="Example: Popular Diagnostic Center / Dhaka Medical"
                            value="<?= doctor_basic_information_e($doctor['primary_hospital'] ?? '') ?>"
                            required
                        >
                        <input type="hidden" name="hospital_id" value="<?= doctor_basic_information_e((string)$doctor_hospital_id) ?>">
                    </div>

                    <div class="gh-field">
                        <label>Primary Hospital Bangla</label>
                        <input
                            type="text"
                            name="primary_hospital_bn"
                            placeholder="উদাহরণ: পপুলার ডায়াগনস্টিক সেন্টার / ঢাকা মেডিকেল"
                            value="<?= doctor_basic_information_e($doctor['primary_hospital_bn'] ?? '') ?>"
                        >
                    </div>

                    <div class="gh-field full">
                        <label>Languages</label>

                        <div class="gh-check-grid">
                            <?php foreach ($language_options as $language_en => $language_bn): ?>
                                <label class="gh-check-item">
                                    <input
                                        type="checkbox"
                                        class="doctor-language-check"
                                        value="<?= doctor_basic_information_e($language_en) ?>"
                                        data-bn="<?= doctor_basic_information_e($language_bn) ?>"
                                        <?= in_array($language_en, $selected_languages, true) ? 'checked' : '' ?>
                                    >
                                    <span><?= doctor_basic_information_e($language_en) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>

                        <input
                            type="hidden"
                            name="languages"
                            id="doctorLanguagesInput"
                            value="<?= doctor_basic_information_e($doctor['languages'] ?? '') ?>"
                        >

                        <input
                            type="hidden"
                            name="languages_bn"
                            id="doctorLanguagesBnInput"
                            value="<?= doctor_basic_information_e($doctor['languages_bn'] ?? '') ?>"
                        >

                        <small>Selected languages will save automatically in English and Bangla.</small>
                    </div>



                    <div class="gh-field">
                        <label>BMDC / Registration Number</label>
                        <input
                            type="text"
                            name="bmdc_number"
                            placeholder="Example: A-12345"
                            value="<?= doctor_basic_information_e($doctor['bmdc_number'] ?? '') ?>"
                        >
                    </div>

                    <div class="gh-field">
                        <label>Gender <span class="gh-required">*</span></label>

                        <select name="gender" id="doctorGenderSelect" required>
                            <option value="" data-bn="" <?= $doctor_gender_value === '' ? 'selected' : '' ?>>Select Gender</option>
                            <option value="Male" data-bn="পুরুষ" <?= $doctor_gender_value === 'Male' ? 'selected' : '' ?>>Male</option>
                            <option value="Female" data-bn="মহিলা" <?= $doctor_gender_value === 'Female' ? 'selected' : '' ?>>Female</option>
                            <option value="Other" data-bn="অন্যান্য" <?= $doctor_gender_value === 'Other' ? 'selected' : '' ?>>Other</option>
                        </select>

                        <input
                            type="hidden"
                            name="gender_bn"
                            id="doctorGenderBnInput"
                            value="<?= doctor_basic_information_e($doctor_gender_bn_value) ?>"
                        >
                    </div>

                    <div class="gh-field full">
                        <label>Specialty <span class="gh-required">*</span></label>
                        <select name="specialty_id" required>
                            <option value="">Select Specialty</option>
                            <?php foreach (($specialties ?? []) as $specialty): ?>
                                <option
                                    value="<?= doctor_basic_information_e((string)$specialty['id']) ?>"
                                    <?= (($doctor['specialty_id'] ?? '') == $specialty['id']) ? 'selected' : '' ?>
                                >
                                    <?= doctor_basic_information_e(doctor_basic_information_specialty_name($specialty)) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="gh-field full">
                        <label>Doctor Slug</label>

                        <div class="gh-slug-box">
                            <input
                                type="text"
                                name="slug"
                                id="slugInput"
                                placeholder="Auto-generated after save"
                                value="<?= doctor_basic_information_e($doctor_slug_value) ?>"
                                readonly
                            >

                            <button
                                type="button"
                                class="gh-btn gh-btn-muted"
                                id="slugActionBtn"
                                data-has-id="<?= $doctor_has_id ? '1' : '0' ?>"
                            >
                                <?= $doctor_has_id ? 'Edit Slug' : 'Save profile first' ?>
                            </button>
                        </div>

                        <input type="hidden" name="form_action" id="slugFormAction" value="">

                        <?php if ($doctor_slug_value !== ''): ?>
                            <small class="gh-slug-help">
                                <span class="gh-url-pill">Current URL</span>
                                <span><?= doctor_basic_information_e(doctor_basic_information_site_url('doctor/' . $doctor_slug_value)) ?></span>
                            </small>
                        <?php else: ?>
                            <small>
                                Slug will be generated from doctor name, specialty and district. You can edit it after the first save.
                            </small>
                        <?php endif; ?>
                    </div>

                    <div class="gh-hint-box">
                        <span class="gh-hint-icon">i</span>
                        <span>
                            Required fields must be filled before saving. Slug is locked by default; click Edit Slug only when you need a custom doctor URL.
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <script>
            (function() {
                const slugInput = document.getElementById('slugInput');
                const slugActionBtn = document.getElementById('slugActionBtn');
                const slugFormAction = document.getElementById('slugFormAction');

                if (slugInput && slugActionBtn && slugFormAction) {
                    const hasDoctorId = slugActionBtn.dataset.hasId === '1';

                    if (!hasDoctorId) {
                        slugActionBtn.disabled = true;
                    } else {
                        let isEditingSlug = false;

                        slugActionBtn.addEventListener('click', function() {
                            if (!isEditingSlug) {
                                isEditingSlug = true;

                                slugInput.removeAttribute('readonly');
                                slugInput.focus();
                                slugInput.select();

                                slugActionBtn.textContent = 'Save Slug';
                                slugActionBtn.classList.remove('gh-btn-muted');
                                slugActionBtn.classList.add('gh-btn-success');

                                return;
                            }

                            const slugValue = slugInput.value.trim();

                            if (!slugValue) {
                                slugInput.classList.add('gh-error-field');
                                slugInput.focus();
                                return;
                            }

                            const form = slugInput.closest('form');

                            if (form) {
                                const actionFields = form.querySelectorAll('input[name="form_action"]');

                                actionFields.forEach(function(field) {
                                    field.value = 'save_slug';
                                });

                                if (!actionFields.length) {
                                    slugFormAction.value = 'save_slug';
                                }

                                form.submit();
                            }
                        });

                        slugInput.addEventListener('input', function() {
                            slugInput.classList.remove('gh-error-field');
                        });

                        slugInput.addEventListener('keydown', function(event) {
                            if (event.key === 'Enter' && isEditingSlug) {
                                event.preventDefault();
                                slugActionBtn.click();
                            }
                        });
                    }
                }

                const languageChecks = document.querySelectorAll('.doctor-language-check');
                const languagesInput = document.getElementById('doctorLanguagesInput');
                const languagesBnInput = document.getElementById('doctorLanguagesBnInput');

                function updateDoctorLanguages() {
                    if (!languageChecks.length || !languagesInput || !languagesBnInput) {
                        return;
                    }

                    const selectedEn = [];
                    const selectedBn = [];

                    languageChecks.forEach(function(check) {
                        if (check.checked) {
                            selectedEn.push(check.value);

                            if (check.dataset.bn) {
                                selectedBn.push(check.dataset.bn);
                            }
                        }
                    });

                    languagesInput.value = selectedEn.join(', ');
                    languagesBnInput.value = selectedBn.join(', ');
                }

                languageChecks.forEach(function(check) {
                    check.addEventListener('change', updateDoctorLanguages);
                });

                updateDoctorLanguages();

                const genderSelect = document.getElementById('doctorGenderSelect');
                const genderBnInput = document.getElementById('doctorGenderBnInput');

                function updateDoctorGenderBn() {
                    if (!genderSelect || !genderBnInput) {
                        return;
                    }

                    const selectedOption = genderSelect.options[genderSelect.selectedIndex];
                    genderBnInput.value = selectedOption ? (selectedOption.dataset.bn || '') : '';
                }

                if (genderSelect && genderBnInput) {
                    genderSelect.addEventListener('change', updateDoctorGenderBn);
                    updateDoctorGenderBn();
                }
            })();
        </script>
        <?php
    }
}

if (!empty($GLOBALS['doctor_form_auto_render_sections'])) {
    doctor_basic_information_render($GLOBALS['doctor_form_context'] ?? []);
}