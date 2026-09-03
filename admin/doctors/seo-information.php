<?php
/**
 * SEO Information section component.
 * Self-working GitHub-style clean UI.
 *
 * Per-field SEO mode:
 * - 6 SEO fields have separate auto/manual mode
 * - If one field is edited manually, only that field becomes manual
 * - Other fields continue auto-sync
 * - If a manual field is cleared, it returns to auto mode
 * - Auto SEO strongly follows: Doctor Name + Specialty + District
 *
 * Place this file at: admin/doctors/seo-information.php
 */

if (!function_exists('e')) {
    function e($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('site_url')) {
    function site_url(string $path = ''): string
    {
        $path = ltrim($path, '/');
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? '';

        if ($host === '') {
            return $path;
        }

        return rtrim($scheme . '://' . $host, '/') . ($path !== '' ? '/' . $path : '');
    }
}

if (!function_exists('doctor_seo_information_valid_identifier')) {
    function doctor_seo_information_valid_identifier(string $identifier): bool
    {
        return (bool)preg_match('/^[A-Za-z0-9_]+$/', $identifier);
    }
}

if (!function_exists('doctor_seo_information_quote_identifier')) {
    function doctor_seo_information_quote_identifier(string $identifier): string
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }
}

if (!function_exists('doctor_seo_information_has_pdo')) {
    function doctor_seo_information_has_pdo(): bool
    {
        global $pdo;
        return isset($pdo) && $pdo instanceof PDO;
    }
}

if (!function_exists('doctor_component_table_exists')) {
    function doctor_component_table_exists(string $table): bool
    {
        global $pdo;

        if (!doctor_seo_information_has_pdo() || !doctor_seo_information_valid_identifier($table)) {
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

        if (
            !doctor_seo_information_has_pdo() ||
            !doctor_seo_information_valid_identifier($table) ||
            !doctor_seo_information_valid_identifier($column)
        ) {
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

        if (
            !doctor_seo_information_has_pdo() ||
            !doctor_seo_information_valid_identifier($table) ||
            !doctor_seo_information_valid_identifier($column) ||
            !doctor_component_table_exists($table) ||
            doctor_component_column_exists($table, $column)
        ) {
            return;
        }

        try {
            $pdo->exec(
                'ALTER TABLE ' . doctor_seo_information_quote_identifier($table) .
                ' ADD COLUMN ' . doctor_seo_information_quote_identifier($column) . ' ' . $definition
            );
        } catch (Throwable $e) {
            // Column boot must not break section rendering.
        }
    }
}

if (!function_exists('doctor_seo_information_strlen')) {
    function doctor_seo_information_strlen(string $text): int
    {
        return function_exists('mb_strlen') ? mb_strlen($text) : strlen($text);
    }
}

if (!function_exists('doctor_seo_information_substr')) {
    function doctor_seo_information_substr(string $text, int $start, int $length): string
    {
        return function_exists('mb_substr') ? mb_substr($text, $start, $length) : substr($text, $start, $length);
    }
}

if (!function_exists('doctor_seo_information_lower')) {
    function doctor_seo_information_lower(string $text): string
    {
        return function_exists('mb_strtolower') ? mb_strtolower($text) : strtolower($text);
    }
}

if (!function_exists('doctor_seo_information_text')) {
    function doctor_seo_information_text($value): string
    {
        return trim(preg_replace('/\s+/', ' ', (string)$value));
    }
}

if (!function_exists('doctor_seo_information_first')) {
    function doctor_seo_information_first(array $source, array $keys): string
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $source)) {
                $value = doctor_seo_information_text($source[$key]);

                if ($value !== '') {
                    return $value;
                }
            }
        }

        return '';
    }
}

if (!function_exists('doctor_seo_information_join_unique')) {
    function doctor_seo_information_join_unique(array $items, string $separator = ', '): string
    {
        $clean = [];

        foreach ($items as $item) {
            $item = doctor_seo_information_text($item);

            if ($item === '') {
                continue;
            }

            $key = doctor_seo_information_lower($item);

            if (!isset($clean[$key])) {
                $clean[$key] = $item;
            }
        }

        return implode($separator, array_values($clean));
    }
}

if (!function_exists('doctor_seo_information_limit')) {
    function doctor_seo_information_limit(string $text, int $limit): string
    {
        $text = doctor_seo_information_text($text);

        if ($limit <= 0 || doctor_seo_information_strlen($text) <= $limit) {
            return $text;
        }

        return rtrim(doctor_seo_information_substr($text, 0, $limit - 1)) . '…';
    }
}

if (!function_exists('doctor_seo_information_db_name_pair')) {
    function doctor_seo_information_db_name_pair(string $table, int $id): array
    {
        global $pdo;

        $result = ['en' => '', 'bn' => ''];

        if ($id <= 0 || !doctor_seo_information_has_pdo() || !doctor_component_table_exists($table)) {
            return $result;
        }

        $select = ['id'];

        foreach (['name', 'name_en', 'name_bn'] as $column) {
            if (doctor_component_column_exists($table, $column)) {
                $select[] = $column;
            }
        }

        if (count($select) <= 1) {
            return $result;
        }

        $quoted_select = array_map('doctor_seo_information_quote_identifier', $select);

        try {
            $stmt = $pdo->prepare(
                'SELECT ' . implode(', ', $quoted_select) .
                ' FROM ' . doctor_seo_information_quote_identifier($table) .
                ' WHERE `id` = :id LIMIT 1'
            );
            $stmt->execute([':id' => $id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                return $result;
            }

            $result['en'] = doctor_seo_information_first($row, ['name_en', 'name']);
            $result['bn'] = doctor_seo_information_first($row, ['name_bn']);

            return $result;
        } catch (Throwable $e) {
            return $result;
        }
    }
}

if (!function_exists('doctor_seo_information_auto_values')) {
    function doctor_seo_information_auto_values(array $doctor): array
    {
        $specialty_pair = doctor_seo_information_db_name_pair('specialties', (int)($doctor['specialty_id'] ?? 0));
        $district_pair = doctor_seo_information_db_name_pair('districts', (int)($doctor['doctor_district_id'] ?? ($doctor['district_id'] ?? 0)));

        $name = doctor_seo_information_first($doctor, [
            'name',
            'doctor_name',
            'name_en',
        ]);

        $specialty = doctor_seo_information_first($doctor, [
            'specialty_name',
            'specialty',
            'doctor_specialty',
        ]);

        if ($specialty_pair['en'] !== '') {
            $specialty = $specialty_pair['en'];
        }

        $district = doctor_seo_information_first($doctor, [
            'doctor_district_name',
            'doctor_district',
            'district_name',
            'district',
        ]);

        if ($district_pair['en'] !== '') {
            $district = $district_pair['en'];
        }

        $name_bn = doctor_seo_information_first($doctor, [
            'name_bn',
            'doctor_name_bn',
        ]);

        $specialty_bn = doctor_seo_information_first($doctor, [
            'specialty_name_bn',
            'specialty_bn',
            'doctor_specialty_bn',
        ]);

        if ($specialty_pair['bn'] !== '') {
            $specialty_bn = $specialty_pair['bn'];
        }

        $district_bn = doctor_seo_information_first($doctor, [
            'doctor_district_name_bn',
            'doctor_district_bn',
            'district_name_bn',
            'district_bn',
        ]);

        if ($district_pair['bn'] !== '') {
            $district_bn = $district_pair['bn'];
        }

        if ($name_bn === '') {
            $name_bn = $name;
        }

        if ($specialty_bn === '') {
            $specialty_bn = $specialty;
        }

        if ($district_bn === '') {
            $district_bn = $district;
        }

        $seo_title = doctor_seo_information_join_unique([
            $name,
            $specialty,
            $district,
        ], ' - ');

        $seo_title_bn = doctor_seo_information_join_unique([
            $name_bn,
            $specialty_bn,
            $district_bn,
        ], ' - ');

        $seo_description = '';

        if ($name !== '' || $specialty !== '' || $district !== '') {
            $seo_description = doctor_seo_information_text(
                doctor_seo_information_join_unique([$name, $specialty, $district], ', ') .
                '. View doctor profile, specialty, district, chamber, appointment information, consultation fee and schedule.'
            );
        }

        $seo_description_bn = '';

        if ($name_bn !== '' || $specialty_bn !== '' || $district_bn !== '') {
            $seo_description_bn = doctor_seo_information_text(
                doctor_seo_information_join_unique([$name_bn, $specialty_bn, $district_bn], ', ') .
                ' এর ডাক্তার প্রোফাইল, বিশেষজ্ঞতা, জেলা, চেম্বার, অ্যাপয়েন্টমেন্ট তথ্য, কনসালটেশন ফি ও সময়সূচি দেখুন।'
            );
        }

        $meta_keywords = doctor_seo_information_join_unique([
            $name,
            $specialty,
            $district,
            $name !== '' && $specialty !== '' ? $name . ' ' . $specialty : '',
            $name !== '' && $district !== '' ? $name . ' ' . $district : '',
            $specialty !== '' && $district !== '' ? $specialty . ' ' . $district : '',
            $name !== '' && $specialty !== '' && $district !== '' ? $name . ' ' . $specialty . ' ' . $district : '',
            $specialty !== '' && $district !== '' ? $specialty . ' doctor in ' . $district : '',
            $name !== '' && $district !== '' ? $name . ' doctor in ' . $district : '',
        ]);

        $meta_keywords_bn = doctor_seo_information_join_unique([
            $name_bn,
            $specialty_bn,
            $district_bn,
            $name_bn !== '' && $specialty_bn !== '' ? $name_bn . ' ' . $specialty_bn : '',
            $name_bn !== '' && $district_bn !== '' ? $name_bn . ' ' . $district_bn : '',
            $specialty_bn !== '' && $district_bn !== '' ? $specialty_bn . ' ' . $district_bn : '',
            $name_bn !== '' && $specialty_bn !== '' && $district_bn !== '' ? $name_bn . ' ' . $specialty_bn . ' ' . $district_bn : '',
            $specialty_bn !== '' && $district_bn !== '' ? $district_bn . ' এর ' . $specialty_bn . ' ডাক্তার' : '',
            $name_bn !== '' && $district_bn !== '' ? $district_bn . ' এর ' . $name_bn : '',
        ]);

        return [
            'seo_title' => doctor_seo_information_limit($seo_title, 160),
            'seo_title_bn' => doctor_seo_information_limit($seo_title_bn, 160),
            'seo_description' => doctor_seo_information_limit($seo_description, 300),
            'seo_description_bn' => doctor_seo_information_limit($seo_description_bn, 300),
            'meta_keywords' => doctor_seo_information_limit($meta_keywords, 255),
            'meta_keywords_bn' => doctor_seo_information_limit($meta_keywords_bn, 255),
        ];
    }
}

if (!function_exists('doctor_seo_information_clean_mode')) {
    function doctor_seo_information_clean_mode($mode): string
    {
        $mode = doctor_seo_information_text($mode);
        return $mode === 'manual' ? 'manual' : 'auto';
    }
}

if (!function_exists('doctor_seo_information_field_mode')) {
    function doctor_seo_information_field_mode(array $doctor, string $field, string $auto_value): string
    {
        $mode_column = $field . '_mode';

        if (array_key_exists($mode_column, $doctor)) {
            return doctor_seo_information_clean_mode($doctor[$mode_column]);
        }

        $saved_value = doctor_seo_information_text($doctor[$field] ?? '');
        $auto_value = doctor_seo_information_text($auto_value);

        if ($saved_value !== '' && $auto_value !== '' && $saved_value !== $auto_value) {
            return 'manual';
        }

        return 'auto';
    }
}

if (!function_exists('doctor_seo_information_display_value')) {
    function doctor_seo_information_display_value(array $doctor, string $field, string $auto_value, string $mode): string
    {
        $saved_value = doctor_seo_information_text($doctor[$field] ?? '');
        $auto_value = doctor_seo_information_text($auto_value);

        if ($mode === 'manual' && $saved_value !== '') {
            return $saved_value;
        }

        if ($auto_value !== '') {
            return $auto_value;
        }

        return $saved_value;
    }
}

/**
 * Unique CSS for SEO Information.
 */
if (!function_exists('doctor_seo_information_css')) {
    function doctor_seo_information_css(): void
    {
        static $printed = false;

        if ($printed) {
            return;
        }

        $printed = true;
        ?>
        <style>
            :root {
                --gh-card: #ffffff;
                --gh-text: #24292f;
                --gh-muted: #57606a;
                --gh-border: #d0d7de;
                --gh-blue: #0969da;
                --gh-blue-soft: #ddf4ff;
                --gh-green: #1a7f37;
                --gh-red: #cf222e;
                --gh-red-soft: #ffebe9;
                --gh-soft: #f6f8fa;
                --gh-soft-hover: #eef1f4;
                --gh-shadow: 0 1px 0 rgba(27,31,36,.04);
            }

            .gh-seo-card,
            .gh-seo-card * {
                box-sizing: border-box;
            }

            .gh-seo-card {
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

            .gh-seo-head {
                display: flex;
                align-items: flex-start;
                justify-content: space-between;
                gap: 18px;
                padding: 18px 20px;
                background: linear-gradient(180deg, #ffffff 0%, #f6f8fa 100%);
                border-bottom: 1px solid var(--gh-border);
            }

            .gh-seo-title {
                display: flex;
                align-items: flex-start;
                gap: 12px;
                min-width: 0;
            }

            .gh-seo-icon {
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

            .gh-seo-title h3 {
                margin: 0;
                color: var(--gh-text);
                font-size: 18px;
                line-height: 1.35;
                font-weight: 700 !important;
                letter-spacing: -.02em;
            }

            .gh-seo-title p {
                margin: 4px 0 0;
                color: var(--gh-muted);
                font-size: 13px;
                line-height: 1.55;
            }

            .gh-seo-badge {
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

            .gh-seo-badge-dot {
                width: 8px;
                height: 8px;
                border-radius: 50%;
                background: var(--gh-green);
            }

            .gh-seo-body {
                padding: 20px;
            }

            .gh-seo-grid {
                display: grid;
                grid-template-columns: minmax(0, 1fr) 330px;
                gap: 18px;
                align-items: start;
            }

            .gh-seo-main,
            .gh-seo-side {
                min-width: 0;
                display: grid;
                gap: 16px;
            }

            .gh-seo-pair-grid {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 16px;
                align-items: start;
            }

            .gh-field {
                display: grid;
                gap: 7px;
                min-width: 0;
            }

            .gh-field label {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 10px;
                color: #344054;
                font-size: 13.5px;
                font-weight: 600 !important;
                line-height: 1.35;
                margin: 0;
            }

            .gh-field-tip {
                color: var(--gh-muted);
                font-size: 11.5px;
                font-weight: 400 !important;
                white-space: nowrap;
            }

            .gh-field input[type="text"],
            .gh-field input[type="file"],
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

            .gh-field input[type="file"] {
                padding: 9px 10px;
                cursor: pointer;
                font-size: 13.5px;
            }

            .gh-field textarea {
                min-height: 130px;
                resize: vertical;
                line-height: 1.7;
            }

            .gh-field input:focus,
            .gh-field textarea:focus {
                border-color: var(--gh-blue);
                box-shadow: 0 0 0 3px rgba(9,105,218,.12);
            }

            .gh-field input::placeholder,
            .gh-field textarea::placeholder {
                color: #8c959f;
            }

            .gh-counter-row {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 10px;
                color: var(--gh-muted);
                font-size: 12px;
                line-height: 1.5;
            }

            .gh-counter-row strong {
                color: var(--gh-text);
            }

            .gh-auto-seo-badge {
                display: inline-flex;
                align-items: center;
                width: fit-content;
                padding: 4px 8px;
                border-radius: 999px;
                background: var(--gh-blue-soft);
                color: var(--gh-blue);
                font-size: 11.5px;
                font-weight: 700 !important;
            }

            .gh-auto-seo-badge.manual {
                background: #fff8c5;
                color: #7d4e00;
                border: 1px solid rgba(191,135,0,.25);
            }

            .gh-seo-note {
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

            .gh-seo-note-icon {
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

            .gh-og-box {
                border: 1px solid var(--gh-border);
                border-radius: 12px;
                background: var(--gh-soft);
                padding: 14px;
                display: grid;
                gap: 12px;
                min-width: 0;
            }

            .gh-og-preview {
                width: 100%;
                aspect-ratio: 1200 / 630;
                border: 1px dashed #8c959f;
                border-radius: 10px;
                background: #fff;
                overflow: hidden;
                display: flex;
                align-items: center;
                justify-content: center;
                color: var(--gh-muted);
                font-size: 13px;
                line-height: 1.5;
                text-align: center;
                padding: 12px;
            }

            .gh-og-preview img {
                width: 100%;
                height: 100%;
                object-fit: cover;
                display: block;
                border-radius: 8px;
            }

            .gh-og-empty {
                display: grid;
                gap: 5px;
                place-items: center;
            }

            .gh-og-empty strong {
                color: var(--gh-text);
                font-size: 14px;
            }

            .gh-og-empty span {
                color: var(--gh-muted);
                font-size: 12.5px;
            }

            .gh-current-og {
                display: grid;
                grid-template-columns: minmax(0, 1fr) auto;
                align-items: center;
                gap: 10px;
                padding: 10px 12px;
                border: 1px solid rgba(84,174,255,.45);
                border-radius: 9px;
                background: var(--gh-blue-soft);
                color: #0550ae;
                font-size: 12.5px;
                line-height: 1.5;
                min-width: 0;
            }

            .gh-current-og span {
                min-width: 0;
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
            }

            .gh-current-og a {
                color: var(--gh-blue);
                text-decoration: none;
                font-weight: 700 !important;
                white-space: nowrap;
            }

            .gh-current-og a:hover {
                text-decoration: underline;
            }

            @media (max-width: 1050px) {
                .gh-seo-grid {
                    grid-template-columns: 1fr;
                }
            }

            @media (max-width: 900px) {
                .gh-seo-head {
                    flex-direction: column;
                    align-items: stretch;
                }

                .gh-seo-badge {
                    width: fit-content;
                }

                .gh-field label {
                    flex-direction: column;
                    align-items: flex-start;
                    gap: 4px;
                }

                .gh-seo-pair-grid {
                    grid-template-columns: 1fr;
                }

                .gh-field-tip {
                    white-space: normal;
                }
            }
        </style>
        <?php
    }
}

if (!function_exists('doctor_seo_information_boot')) {
    function doctor_seo_information_boot(): void
    {
        $columns = [
            'seo_title' => "VARCHAR(255) NULL",
            'seo_title_bn' => "VARCHAR(255) NULL",
            'seo_description' => "TEXT NULL",
            'seo_description_bn' => "TEXT NULL",
            'meta_keywords' => "VARCHAR(255) NULL",
            'meta_keywords_bn' => "VARCHAR(255) NULL",

            'seo_title_mode' => "VARCHAR(20) DEFAULT 'auto'",
            'seo_title_bn_mode' => "VARCHAR(20) DEFAULT 'auto'",
            'seo_description_mode' => "VARCHAR(20) DEFAULT 'auto'",
            'seo_description_bn_mode' => "VARCHAR(20) DEFAULT 'auto'",
            'meta_keywords_mode' => "VARCHAR(20) DEFAULT 'auto'",
            'meta_keywords_bn_mode' => "VARCHAR(20) DEFAULT 'auto'",

            'og_image' => "VARCHAR(255) NULL",
            'updated_at' => "DATETIME NULL",
        ];

        foreach ($columns as $column => $definition) {
            doctor_component_add_column_if_missing('doctors', $column, $definition);
        }
    }
}

doctor_seo_information_boot();

if (!function_exists('doctor_seo_information_render')) {
    function doctor_seo_information_render(array $context = []): void
    {
        extract($context, EXTR_SKIP);

        doctor_seo_information_css();

        $doctor = is_array($doctor ?? null) ? $doctor : [];
        $auto_seo = doctor_seo_information_auto_values($doctor);

        $seo_title_mode = doctor_seo_information_field_mode($doctor, 'seo_title', $auto_seo['seo_title'] ?? '');
        $seo_title_bn_mode = doctor_seo_information_field_mode($doctor, 'seo_title_bn', $auto_seo['seo_title_bn'] ?? '');
        $seo_description_mode = doctor_seo_information_field_mode($doctor, 'seo_description', $auto_seo['seo_description'] ?? '');
        $seo_description_bn_mode = doctor_seo_information_field_mode($doctor, 'seo_description_bn', $auto_seo['seo_description_bn'] ?? '');
        $meta_keywords_mode = doctor_seo_information_field_mode($doctor, 'meta_keywords', $auto_seo['meta_keywords'] ?? '');
        $meta_keywords_bn_mode = doctor_seo_information_field_mode($doctor, 'meta_keywords_bn', $auto_seo['meta_keywords_bn'] ?? '');

        $seo_title = doctor_seo_information_display_value($doctor, 'seo_title', $auto_seo['seo_title'] ?? '', $seo_title_mode);
        $seo_title_bn = doctor_seo_information_display_value($doctor, 'seo_title_bn', $auto_seo['seo_title_bn'] ?? '', $seo_title_bn_mode);
        $seo_description = doctor_seo_information_display_value($doctor, 'seo_description', $auto_seo['seo_description'] ?? '', $seo_description_mode);
        $seo_description_bn = doctor_seo_information_display_value($doctor, 'seo_description_bn', $auto_seo['seo_description_bn'] ?? '', $seo_description_bn_mode);
        $meta_keywords = doctor_seo_information_display_value($doctor, 'meta_keywords', $auto_seo['meta_keywords'] ?? '', $meta_keywords_mode);
        $meta_keywords_bn = doctor_seo_information_display_value($doctor, 'meta_keywords_bn', $auto_seo['meta_keywords_bn'] ?? '', $meta_keywords_bn_mode);

        $og_image = trim((string)($doctor['og_image'] ?? ''));

        $has_seo_title = trim($seo_title) !== '';
        $has_seo_description = trim($seo_description) !== '';
        $has_og_image = $og_image !== '';
        $seo_count = 0;

        if ($has_seo_title || trim($seo_title_bn) !== '') {
            $seo_count++;
        }

        if ($has_seo_description || trim($seo_description_bn) !== '') {
            $seo_count++;
        }

        if (trim($meta_keywords) !== '' || trim($meta_keywords_bn) !== '') {
            $seo_count++;
        }

        if ($has_og_image) {
            $seo_count++;
        }

        $og_image_url = $has_og_image ? site_url($og_image) : '';

        $badge_class = function (string $mode): string {
            return $mode === 'manual' ? 'gh-auto-seo-badge manual' : 'gh-auto-seo-badge';
        };

        $badge_text = function (string $mode): string {
            return $mode === 'manual' ? 'Manual' : 'Auto-sync enabled';
        };
        ?>

        <div class="gh-seo-card">
            <div class="gh-seo-head">
                <div class="gh-seo-title">
                    <div class="gh-seo-icon">SEO</div>
                    <div>
                        <h3>SEO Information</h3>
                        <p>Auto SEO strongly follows Doctor Name, Specialty and District.</p>
                    </div>
                </div>

                <div class="gh-seo-badge">
                    <span class="gh-seo-badge-dot"></span>
                    <span><?= e((string)$seo_count) ?> / 4 completed</span>
                </div>
            </div>

            <div class="gh-seo-body">
                <div class="gh-seo-grid">
                    <div class="gh-seo-main">
                        <div class="gh-seo-note">
                            <span class="gh-seo-note-icon">i</span>
                            <span>
                                Each SEO field has its own mode. If you manually edit one field, only that field becomes manual. The other SEO fields continue auto-updating from Doctor Name, Specialty and District. Clear a manual field to return it to auto mode.
                            </span>
                        </div>

                        <div class="gh-seo-pair-grid">
                            <div class="gh-field">
                                <label>
                                    <span>SEO Title</span>
                                    <span class="gh-field-tip">Auto: Doctor Name - Specialty - District</span>
                                </label>
                                <input
                                    type="text"
                                    name="seo_title"
                                    id="doctorSeoTitle"
                                    maxlength="160"
                                    placeholder="Example: Dr. Ahmed Hasan - Cardiology - Dhaka"
                                    value="<?= e($seo_title) ?>"
                                >
                                <input type="hidden" name="seo_title_mode" id="doctorSeoTitleMode" value="<?= e($seo_title_mode) ?>">
                                <input type="hidden" name="seo_title_auto_original" id="doctorSeoTitleAutoOriginal" value="<?= e($auto_seo['seo_title'] ?? '') ?>">
                                <div class="gh-counter-row">
                                    <span>Search result title</span>
                                    <span><strong id="doctorSeoTitleCount"><?= e((string)doctor_seo_information_strlen(trim($seo_title))) ?></strong> characters</span>
                                </div>
                                <span class="<?= e($badge_class($seo_title_mode)) ?>" id="doctorSeoTitleBadge"><?= e($badge_text($seo_title_mode)) ?></span>
                            </div>

                            <div class="gh-field">
                                <label>
                                    <span>SEO Title Bangla</span>
                                    <span class="gh-field-tip">Auto Bangla title</span>
                                </label>
                                <input
                                    type="text"
                                    name="seo_title_bn"
                                    id="doctorSeoTitleBn"
                                    maxlength="160"
                                    placeholder="উদাহরণ: ডা. আহমেদ হাসান - কার্ডিওলজি - ঢাকা"
                                    value="<?= e($seo_title_bn) ?>"
                                >
                                <input type="hidden" name="seo_title_bn_mode" id="doctorSeoTitleBnMode" value="<?= e($seo_title_bn_mode) ?>">
                                <input type="hidden" name="seo_title_bn_auto_original" id="doctorSeoTitleBnAutoOriginal" value="<?= e($auto_seo['seo_title_bn'] ?? '') ?>">
                                <div class="gh-counter-row">
                                    <span>Bangla search result title</span>
                                    <span><strong id="doctorSeoTitleBnCount"><?= e((string)doctor_seo_information_strlen(trim($seo_title_bn))) ?></strong> characters</span>
                                </div>
                                <span class="<?= e($badge_class($seo_title_bn_mode)) ?>" id="doctorSeoTitleBnBadge"><?= e($badge_text($seo_title_bn_mode)) ?></span>
                            </div>
                        </div>

                        <div class="gh-seo-pair-grid">
                            <div class="gh-field">
                                <label>
                                    <span>SEO Description</span>
                                    <span class="gh-field-tip">Auto from title base</span>
                                </label>
                                <textarea
                                    name="seo_description"
                                    id="doctorSeoDescription"
                                    maxlength="300"
                                    placeholder="Auto: Doctor Name, Specialty, District."
                                ><?= e($seo_description) ?></textarea>
                                <input type="hidden" name="seo_description_mode" id="doctorSeoDescriptionMode" value="<?= e($seo_description_mode) ?>">
                                <input type="hidden" name="seo_description_auto_original" id="doctorSeoDescriptionAutoOriginal" value="<?= e($auto_seo['seo_description'] ?? '') ?>">
                                <div class="gh-counter-row">
                                    <span>Search snippet description</span>
                                    <span><strong id="doctorSeoDescriptionCount"><?= e((string)doctor_seo_information_strlen(trim($seo_description))) ?></strong> characters</span>
                                </div>
                                <span class="<?= e($badge_class($seo_description_mode)) ?>" id="doctorSeoDescriptionBadge"><?= e($badge_text($seo_description_mode)) ?></span>
                            </div>

                            <div class="gh-field">
                                <label>
                                    <span>SEO Description Bangla</span>
                                    <span class="gh-field-tip">Auto Bangla description</span>
                                </label>
                                <textarea
                                    name="seo_description_bn"
                                    id="doctorSeoDescriptionBn"
                                    maxlength="300"
                                    placeholder="Auto: ডাক্তার নাম, বিশেষজ্ঞতা, জেলা।"
                                ><?= e($seo_description_bn) ?></textarea>
                                <input type="hidden" name="seo_description_bn_mode" id="doctorSeoDescriptionBnMode" value="<?= e($seo_description_bn_mode) ?>">
                                <input type="hidden" name="seo_description_bn_auto_original" id="doctorSeoDescriptionBnAutoOriginal" value="<?= e($auto_seo['seo_description_bn'] ?? '') ?>">
                                <div class="gh-counter-row">
                                    <span>Bangla search snippet description</span>
                                    <span><strong id="doctorSeoDescriptionBnCount"><?= e((string)doctor_seo_information_strlen(trim($seo_description_bn))) ?></strong> characters</span>
                                </div>
                                <span class="<?= e($badge_class($seo_description_bn_mode)) ?>" id="doctorSeoDescriptionBnBadge"><?= e($badge_text($seo_description_bn_mode)) ?></span>
                            </div>
                        </div>

                        <div class="gh-seo-pair-grid">
                            <div class="gh-field">
                                <label>
                                    <span>Meta Keywords</span>
                                    <span class="gh-field-tip">Auto keyword combinations</span>
                                </label>
                                <input
                                    type="text"
                                    name="meta_keywords"
                                    id="doctorMetaKeywords"
                                    placeholder="doctor name, specialty, district"
                                    value="<?= e($meta_keywords) ?>"
                                >
                                <input type="hidden" name="meta_keywords_mode" id="doctorMetaKeywordsMode" value="<?= e($meta_keywords_mode) ?>">
                                <input type="hidden" name="meta_keywords_auto_original" id="doctorMetaKeywordsAutoOriginal" value="<?= e($auto_seo['meta_keywords'] ?? '') ?>">
                                <span class="<?= e($badge_class($meta_keywords_mode)) ?>" id="doctorMetaKeywordsBadge"><?= e($badge_text($meta_keywords_mode)) ?></span>
                            </div>

                            <div class="gh-field">
                                <label>
                                    <span>Meta Keywords Bangla</span>
                                    <span class="gh-field-tip">Auto Bangla keywords</span>
                                </label>
                                <input
                                    type="text"
                                    name="meta_keywords_bn"
                                    id="doctorMetaKeywordsBn"
                                    placeholder="ডাক্তারের নাম, বিশেষজ্ঞতা, জেলা"
                                    value="<?= e($meta_keywords_bn) ?>"
                                >
                                <input type="hidden" name="meta_keywords_bn_mode" id="doctorMetaKeywordsBnMode" value="<?= e($meta_keywords_bn_mode) ?>">
                                <input type="hidden" name="meta_keywords_bn_auto_original" id="doctorMetaKeywordsBnAutoOriginal" value="<?= e($auto_seo['meta_keywords_bn'] ?? '') ?>">
                                <span class="<?= e($badge_class($meta_keywords_bn_mode)) ?>" id="doctorMetaKeywordsBnBadge"><?= e($badge_text($meta_keywords_bn_mode)) ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="gh-seo-side">
                        <div class="gh-og-box">
                            <div class="gh-og-preview" id="doctorOgPreview">
                                <?php if ($has_og_image): ?>
                                    <img src="<?= e($og_image_url) ?>" alt="Open Graph image preview">
                                <?php else: ?>
                                    <div class="gh-og-empty">
                                        <strong>No OG image uploaded</strong>
                                        <span>Recommended size: 1200×630</span>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="gh-field">
                                <label>Open Graph Image</label>
                                <input
                                    type="file"
                                    name="og_image_file"
                                    id="doctorOgImageInput"
                                    accept="image/jpeg,image/png,image/webp"
                                >
                            </div>

                            <div class="gh-seo-note">
                                <span class="gh-seo-note-icon">i</span>
                                <span>
                                    Recommended OG size is 1200×630. The file will be optimized to WebP when possible.
                                </span>
                            </div>

                            <?php if ($has_og_image): ?>
                                <div class="gh-current-og">
                                    <span><?= e($og_image) ?></span>
                                    <a href="<?= e($og_image_url) ?>" target="_blank">View</a>
                                </div>
                            <?php endif; ?>

                            <input type="hidden" name="og_image" value="<?= e($og_image) ?>">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <script>
            (function() {
                const seoFields = {
                    title: {
                        field: document.getElementById('doctorSeoTitle'),
                        mode: document.getElementById('doctorSeoTitleMode'),
                        autoOriginal: document.getElementById('doctorSeoTitleAutoOriginal'),
                        counter: document.getElementById('doctorSeoTitleCount'),
                        badge: document.getElementById('doctorSeoTitleBadge')
                    },
                    titleBn: {
                        field: document.getElementById('doctorSeoTitleBn'),
                        mode: document.getElementById('doctorSeoTitleBnMode'),
                        autoOriginal: document.getElementById('doctorSeoTitleBnAutoOriginal'),
                        counter: document.getElementById('doctorSeoTitleBnCount'),
                        badge: document.getElementById('doctorSeoTitleBnBadge')
                    },
                    description: {
                        field: document.getElementById('doctorSeoDescription'),
                        mode: document.getElementById('doctorSeoDescriptionMode'),
                        autoOriginal: document.getElementById('doctorSeoDescriptionAutoOriginal'),
                        counter: document.getElementById('doctorSeoDescriptionCount'),
                        badge: document.getElementById('doctorSeoDescriptionBadge')
                    },
                    descriptionBn: {
                        field: document.getElementById('doctorSeoDescriptionBn'),
                        mode: document.getElementById('doctorSeoDescriptionBnMode'),
                        autoOriginal: document.getElementById('doctorSeoDescriptionBnAutoOriginal'),
                        counter: document.getElementById('doctorSeoDescriptionBnCount'),
                        badge: document.getElementById('doctorSeoDescriptionBnBadge')
                    },
                    keywords: {
                        field: document.getElementById('doctorMetaKeywords'),
                        mode: document.getElementById('doctorMetaKeywordsMode'),
                        autoOriginal: document.getElementById('doctorMetaKeywordsAutoOriginal'),
                        counter: null,
                        badge: document.getElementById('doctorMetaKeywordsBadge')
                    },
                    keywordsBn: {
                        field: document.getElementById('doctorMetaKeywordsBn'),
                        mode: document.getElementById('doctorMetaKeywordsBnMode'),
                        autoOriginal: document.getElementById('doctorMetaKeywordsBnAutoOriginal'),
                        counter: null,
                        badge: document.getElementById('doctorMetaKeywordsBnBadge')
                    }
                };

                const ogInput = document.getElementById('doctorOgImageInput');
                const ogPreview = document.getElementById('doctorOgPreview');

                const doctorNameInput = document.querySelector('[name="name"]');
                const doctorNameBnInput = document.querySelector('[name="name_bn"]');
                const specialtySelect = document.querySelector('[name="specialty_id"]');
                const districtSelect = document.querySelector('[name="doctor_district_id"]');

                let isApplyingAuto = false;

                function cleanText(value) {
                    return String(value || '').replace(/\s+/g, ' ').trim();
                }

                function limitText(value, limit) {
                    value = cleanText(value);

                    if (!limit || value.length <= limit) {
                        return value;
                    }

                    return cleanText(value.substring(0, limit - 1)) + '…';
                }

                function optionText(select) {
                    if (!select || !select.options || select.selectedIndex < 0) {
                        return '';
                    }

                    const option = select.options[select.selectedIndex];

                    if (!option || !option.value) {
                        return '';
                    }

                    let text = cleanText(option.textContent || '');

                    if (text.includes('/')) {
                        text = cleanText(text.split('/')[0]);
                    }

                    if (text.indexOf('— Select') !== -1) {
                        return '';
                    }

                    return text;
                }

                function joinUnique(items, separator) {
                    const clean = [];
                    const used = {};

                    items.forEach(function(item) {
                        item = cleanText(item);

                        if (!item) {
                            return;
                        }

                        const key = item.toLowerCase();

                        if (!used[key]) {
                            used[key] = true;
                            clean.push(item);
                        }
                    });

                    return clean.join(separator || ', ');
                }

                function updateCount(item) {
                    if (!item || !item.field || !item.counter) {
                        return;
                    }

                    item.counter.textContent = cleanText(item.field.value).length;
                }

                function setBadge(item) {
                    if (!item || !item.mode || !item.badge) {
                        return;
                    }

                    const isManual = item.mode.value === 'manual';

                    item.badge.textContent = isManual ? 'Manual' : 'Auto-sync enabled';

                    if (isManual) {
                        item.badge.classList.add('manual');
                    } else {
                        item.badge.classList.remove('manual');
                    }
                }

                function buildAutoSeo() {
                    const name = cleanText(doctorNameInput ? doctorNameInput.value : '');
                    const specialty = optionText(specialtySelect);
                    const district = optionText(districtSelect);

                    const nameBn = cleanText(doctorNameBnInput ? doctorNameBnInput.value : '') || name;
                    const specialtyBn = specialty;
                    const districtBn = district;

                    const title = limitText(joinUnique([name, specialty, district], ' - '), 160);
                    const titleBn = limitText(joinUnique([nameBn, specialtyBn, districtBn], ' - '), 160);

                    let description = '';

                    if (name || specialty || district) {
                        description = limitText(
                            joinUnique([name, specialty, district], ', ') +
                            '. View doctor profile, specialty, district, chamber, appointment information, consultation fee and schedule.',
                            300
                        );
                    }

                    let descriptionBn = '';

                    if (nameBn || specialtyBn || districtBn) {
                        descriptionBn = limitText(
                            joinUnique([nameBn, specialtyBn, districtBn], ', ') +
                            ' এর ডাক্তার প্রোফাইল, বিশেষজ্ঞতা, জেলা, চেম্বার, অ্যাপয়েন্টমেন্ট তথ্য, কনসালটেশন ফি ও সময়সূচি দেখুন।',
                            300
                        );
                    }

                    const keywords = limitText(joinUnique([
                        name,
                        specialty,
                        district,
                        name && specialty ? name + ' ' + specialty : '',
                        name && district ? name + ' ' + district : '',
                        specialty && district ? specialty + ' ' + district : '',
                        name && specialty && district ? name + ' ' + specialty + ' ' + district : '',
                        specialty && district ? specialty + ' doctor in ' + district : '',
                        name && district ? name + ' doctor in ' + district : ''
                    ]), 255);

                    const keywordsBn = limitText(joinUnique([
                        nameBn,
                        specialtyBn,
                        districtBn,
                        nameBn && specialtyBn ? nameBn + ' ' + specialtyBn : '',
                        nameBn && districtBn ? nameBn + ' ' + districtBn : '',
                        specialtyBn && districtBn ? specialtyBn + ' ' + districtBn : '',
                        nameBn && specialtyBn && districtBn ? nameBn + ' ' + specialtyBn + ' ' + districtBn : '',
                        specialtyBn && districtBn ? districtBn + ' এর ' + specialtyBn + ' ডাক্তার' : '',
                        nameBn && districtBn ? districtBn + ' এর ' + nameBn : ''
                    ]), 255);

                    return {
                        title: title,
                        titleBn: titleBn,
                        description: description,
                        descriptionBn: descriptionBn,
                        keywords: keywords,
                        keywordsBn: keywordsBn
                    };
                }

                function applyFieldAuto(item, autoValue) {
                    if (!item || !item.field || !item.mode) {
                        return;
                    }

                    if (item.autoOriginal) {
                        item.autoOriginal.value = autoValue || '';
                    }

                    if (item.mode.value === 'auto') {
                        item.field.value = autoValue || '';
                    }

                    updateCount(item);
                    setBadge(item);
                }

                function applyAutoSeo() {
                    const autoSeo = buildAutoSeo();

                    isApplyingAuto = true;

                    applyFieldAuto(seoFields.title, autoSeo.title);
                    applyFieldAuto(seoFields.titleBn, autoSeo.titleBn);
                    applyFieldAuto(seoFields.description, autoSeo.description);
                    applyFieldAuto(seoFields.descriptionBn, autoSeo.descriptionBn);
                    applyFieldAuto(seoFields.keywords, autoSeo.keywords);
                    applyFieldAuto(seoFields.keywordsBn, autoSeo.keywordsBn);

                    isApplyingAuto = false;
                }

                function bindSeoManualMode(item) {
                    if (!item || !item.field || !item.mode) {
                        return;
                    }

                    item.field.addEventListener('input', function() {
                        if (isApplyingAuto) {
                            return;
                        }

                        if (cleanText(item.field.value) === '') {
                            item.mode.value = 'auto';

                            const autoSeo = buildAutoSeo();

                            const map = {
                                doctorSeoTitle: autoSeo.title,
                                doctorSeoTitleBn: autoSeo.titleBn,
                                doctorSeoDescription: autoSeo.description,
                                doctorSeoDescriptionBn: autoSeo.descriptionBn,
                                doctorMetaKeywords: autoSeo.keywords,
                                doctorMetaKeywordsBn: autoSeo.keywordsBn
                            };

                            item.field.value = map[item.field.id] || '';
                        } else {
                            item.mode.value = 'manual';
                        }

                        updateCount(item);
                        setBadge(item);
                    });

                    item.field.addEventListener('blur', function() {
                        if (cleanText(item.field.value) === '') {
                            item.mode.value = 'auto';
                            applyAutoSeo();
                        }

                        updateCount(item);
                        setBadge(item);
                    });

                    setBadge(item);
                    updateCount(item);
                }

                function bindAutoSource(inputOrSelect) {
                    if (!inputOrSelect) {
                        return;
                    }

                    inputOrSelect.addEventListener('input', applyAutoSeo);
                    inputOrSelect.addEventListener('change', applyAutoSeo);
                }

                Object.keys(seoFields).forEach(function(key) {
                    bindSeoManualMode(seoFields[key]);
                });

                bindAutoSource(doctorNameInput);
                bindAutoSource(doctorNameBnInput);
                bindAutoSource(specialtySelect);
                bindAutoSource(districtSelect);

                if (districtSelect) {
                    const districtObserver = new MutationObserver(function() {
                        applyAutoSeo();
                    });

                    districtObserver.observe(districtSelect, {
                        childList: true,
                        subtree: true,
                        attributes: true,
                        attributeFilter: ['disabled', 'data-current']
                    });
                }

                const form = document.querySelector('form');

                if (form) {
                    form.addEventListener('submit', function() {
                        applyAutoSeo();

                        Object.keys(seoFields).forEach(function(key) {
                            const item = seoFields[key];

                            if (!item || !item.field || !item.mode) {
                                return;
                            }

                            if (cleanText(item.field.value) === '') {
                                item.mode.value = 'auto';
                            }

                            setBadge(item);
                            updateCount(item);
                        });
                    });
                }

                applyAutoSeo();

                if (ogInput && ogPreview) {
                    ogInput.addEventListener('change', function() {
                        const file = this.files && this.files[0] ? this.files[0] : null;

                        if (!file) {
                            return;
                        }

                        if (!file.type.match(/^image\/(jpeg|png|webp)$/)) {
                            ogPreview.innerHTML = '<div class="gh-og-empty"><strong>Invalid image type</strong><span>Please upload JPG, PNG or WebP.</span></div>';
                            this.value = '';
                            return;
                        }

                        const reader = new FileReader();

                        reader.onload = function(event) {
                            ogPreview.innerHTML = '<img src="' + event.target.result + '" alt="New Open Graph image preview">';
                        };

                        reader.readAsDataURL(file);
                    });
                }
            })();
        </script>

        <?php
    }
}

if (!function_exists('doctor_seo_render')) {
    function doctor_seo_render(array $context = []): void
    {
        doctor_seo_information_render($context);
    }
}

if (!function_exists('doctor_seo_information_section_render')) {
    function doctor_seo_information_section_render(array $context = []): void
    {
        doctor_seo_information_render($context);
    }
}

if (!empty($GLOBALS['doctor_form_auto_render_sections'])) {
    doctor_seo_information_render($GLOBALS['doctor_form_context'] ?? []);
}