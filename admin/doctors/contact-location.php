<?php
/**
 * Contact & Location section component.
 * Self-working GitHub-style UI.
 *
 * Clean ID-only location structure:
 * - Saves only doctor_division_id, doctor_district_id, doctor_thana_id
 * - Does not create/save doctor_division, doctor_district, doctor_thana name columns
 * - Division/District/Thana names are loaded from their own tables when needed
 * - Select options display only English/name, no Bangla beside option text
 * - Empty Thana always falls back to — Select Thana —
 */

if (!function_exists('doctor_contact_location_e')) {
    function doctor_contact_location_e($value): string
    {
        if (function_exists('e')) {
            return e($value);
        }

        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('doctor_contact_location_has_pdo')) {
    function doctor_contact_location_has_pdo(): bool
    {
        return isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO;
    }
}

if (!function_exists('doctor_contact_location_safe_identifier')) {
    function doctor_contact_location_safe_identifier(string $identifier): string
    {
        return preg_match('/^[A-Za-z0-9_]+$/', $identifier) ? $identifier : '';
    }
}

if (!function_exists('doctor_component_table_exists')) {
    function doctor_component_table_exists(string $table): bool
    {
        global $pdo;

        $table = doctor_contact_location_safe_identifier($table);

        if ($table === '' || !doctor_contact_location_has_pdo()) {
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

        $table = doctor_contact_location_safe_identifier($table);
        $column = doctor_contact_location_safe_identifier($column);

        if ($table === '' || $column === '' || !doctor_contact_location_has_pdo()) {
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

        $table = doctor_contact_location_safe_identifier($table);
        $column = doctor_contact_location_safe_identifier($column);

        if ($table === '' || $column === '' || !doctor_contact_location_has_pdo()) {
            return;
        }

        try {
            if (!doctor_component_table_exists($table)) {
                return;
            }

            if (!doctor_component_column_exists($table, $column)) {
                $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
            }
        } catch (Throwable $e) {
            // Column boot should not break page rendering.
        }
    }
}

if (!function_exists('doctor_contact_location_name_column')) {
    function doctor_contact_location_name_column(string $table): string
    {
        if (doctor_component_column_exists($table, 'name_en')) {
            return 'name_en';
        }

        if (doctor_component_column_exists($table, 'name')) {
            return 'name';
        }

        return '';
    }
}

if (!function_exists('doctor_contact_location_bn_column')) {
    function doctor_contact_location_bn_column(string $table): string
    {
        return doctor_component_column_exists($table, 'name_bn') ? 'name_bn' : '';
    }
}

if (!function_exists('doctor_contact_location_rows')) {
    function doctor_contact_location_rows(string $table, string $parent_column = '', int $parent_id = 0): array
    {
        global $pdo;

        $table = doctor_contact_location_safe_identifier($table);
        $parent_column = doctor_contact_location_safe_identifier($parent_column);

        $allowed_tables = ['divisions', 'districts', 'thanas'];
        $allowed_parent_columns = ['division_id', 'district_id'];

        if (
            $table === '' ||
            !in_array($table, $allowed_tables, true) ||
            !doctor_component_table_exists($table) ||
            !doctor_contact_location_has_pdo()
        ) {
            return [];
        }

        $name_column = doctor_contact_location_name_column($table);
        $bn_column = doctor_contact_location_bn_column($table);

        if ($name_column === '') {
            return [];
        }

        $select = "id, {$name_column} AS name";
        $select .= $bn_column !== '' ? ", {$bn_column} AS name_bn" : ", '' AS name_bn";

        $where = [];
        $params = [];

        if (
            $parent_column !== '' &&
            in_array($parent_column, $allowed_parent_columns, true) &&
            $parent_id > 0 &&
            doctor_component_column_exists($table, $parent_column)
        ) {
            $where[] = "{$parent_column} = :parent_id";
            $params[':parent_id'] = $parent_id;
        }

        if (doctor_component_column_exists($table, 'status')) {
            $where[] = "(status IS NULL OR status = '' OR status = 'active')";
        }

        try {
            $sql = "SELECT {$select} FROM `{$table}`";

            if (!empty($where)) {
                $sql .= ' WHERE ' . implode(' AND ', $where);
            }

            $sql .= " ORDER BY {$name_column} ASC";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }
}

if (!function_exists('doctor_contact_location_json_response')) {
    function doctor_contact_location_json_response(array $rows): void
    {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($rows);
        exit;
    }
}

/*
|--------------------------------------------------------------------------
| Self AJAX
|--------------------------------------------------------------------------
| This removes hard dependency on ../ajax/get-districts.php and get-thanas.php.
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['doctor_contact_location_ajax'])) {
    $type = trim((string)($_GET['doctor_contact_location_ajax'] ?? ''));

    if ($type === 'districts') {
        doctor_contact_location_json_response(
            doctor_contact_location_rows('districts', 'division_id', (int)($_GET['division_id'] ?? 0))
        );
    }

    if ($type === 'thanas') {
        doctor_contact_location_json_response(
            doctor_contact_location_rows('thanas', 'district_id', (int)($_GET['district_id'] ?? 0))
        );
    }

    doctor_contact_location_json_response([]);
}

/**
 * Unique CSS for Contact & Location.
 */
if (!function_exists('doctor_contact_location_css')) {
    function doctor_contact_location_css(): void
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
                --gh-red-soft: #ffebe9;
                --gh-soft: #f6f8fa;
                --gh-soft-hover: #eef1f4;
                --gh-shadow: 0 1px 0 rgba(27,31,36,.04);
            }

            .gh-contact-card,
            .gh-contact-card * {
                box-sizing: border-box;
            }

            .gh-contact-card {
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

            .gh-contact-head {
                display: flex;
                align-items: flex-start;
                justify-content: space-between;
                gap: 18px;
                padding: 18px 20px;
                background: linear-gradient(180deg, #ffffff 0%, #f6f8fa 100%);
                border-bottom: 1px solid var(--gh-border);
            }

            .gh-contact-title {
                display: flex;
                gap: 12px;
                align-items: flex-start;
                min-width: 0;
            }

            .gh-contact-icon {
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

            .gh-contact-title h3 {
                margin: 0;
                color: var(--gh-text);
                font-size: 18px;
                line-height: 1.35;
                font-weight: 700 !important;
                letter-spacing: -.02em;
            }

            .gh-contact-title p {
                margin: 4px 0 0;
                color: var(--gh-muted);
                font-size: 13px;
                line-height: 1.55;
            }

            .gh-contact-badge {
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

            .gh-contact-badge-dot {
                width: 8px;
                height: 8px;
                border-radius: 50%;
                background: var(--gh-green);
            }

            .gh-contact-body {
                padding: 20px;
            }

            .gh-contact-grid {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 16px;
            }

            .gh-contact-grid.three {
                grid-template-columns: repeat(3, minmax(0, 1fr));
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

            .gh-field textarea {
                min-height: 118px;
                resize: vertical;
                line-height: 1.7;
            }

            .gh-field input:focus,
            .gh-field select:focus,
            .gh-field textarea:focus {
                border-color: var(--gh-blue);
                box-shadow: 0 0 0 3px rgba(9,105,218,.12);
            }

            .gh-field select:disabled {
                background: var(--gh-soft);
                color: var(--gh-muted);
                cursor: not-allowed;
            }

            .gh-field small {
                color: var(--gh-muted);
                font-size: 12px;
                line-height: 1.5;
            }

            .gh-auto-note {
                display: flex;
                align-items: flex-start;
                gap: 10px;
                margin-bottom: 16px;
                padding: 12px 14px;
                border: 1px solid rgba(84,174,255,.45);
                border-radius: 10px;
                background: var(--gh-blue-soft);
                color: #0550ae;
                font-size: 12.5px;
                line-height: 1.6;
            }

            .gh-auto-note-icon {
                width: 18px;
                height: 18px;
                border-radius: 50%;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                background: #fff;
                color: var(--gh-blue);
                font-size: 12px;
                font-weight: 800 !important;
                flex: 0 0 auto;
                margin-top: 1px;
            }

            @media (max-width: 900px) {
                .gh-contact-head {
                    flex-direction: column;
                    align-items: stretch;
                }

                .gh-contact-badge {
                    width: fit-content;
                }

                .gh-contact-grid,
                .gh-contact-grid.three {
                    grid-template-columns: 1fr;
                }
            }
        </style>
        <?php
    }
}

if (!function_exists('doctor_contact_location_boot')) {
    function doctor_contact_location_boot(): void
    {
        foreach ([
            'phone' => "VARCHAR(80) NULL",
            'whatsapp' => "VARCHAR(80) NULL",
            'email' => "VARCHAR(150) NULL",
            'doctor_division_id' => "INT DEFAULT 0",
            'doctor_district_id' => "INT DEFAULT 0",
            'doctor_thana_id' => "INT DEFAULT 0",
            'doctor_location_source' => "VARCHAR(20) DEFAULT 'auto'",
            'doctor_location_auto_chamber_id' => "INT DEFAULT 0",
            'updated_at' => "DATETIME NULL",
        ] as $column => $definition) {
            doctor_component_add_column_if_missing('doctors', $column, $definition);
        }
    }
}

doctor_contact_location_boot();

if (!function_exists('doctor_contact_location_is_location_empty')) {
    function doctor_contact_location_is_location_empty(array $doctor): bool
    {
        $fields = [
            'doctor_division_id',
            'doctor_district_id',
            'doctor_thana_id',
        ];

        foreach ($fields as $field) {
            $value = trim((string)($doctor[$field] ?? ''));

            if ($value !== '' && $value !== '0') {
                return false;
            }
        }

        return true;
    }
}

if (!function_exists('doctor_contact_location_get_first_chamber_location')) {
    function doctor_contact_location_get_first_chamber_location(int $doctor_id): array
    {
        global $pdo;

        if ($doctor_id <= 0 || !doctor_contact_location_has_pdo()) {
            return [];
        }

        if (!doctor_component_table_exists('chambers') || !doctor_component_table_exists('hospitals')) {
            return [];
        }

        if (!doctor_component_column_exists('chambers', 'hospital_id') || !doctor_component_column_exists('hospitals', 'district_id')) {
            return [];
        }

        $hospital_has_division_id = doctor_component_column_exists('hospitals', 'division_id');
        $hospital_has_thana_id = doctor_component_column_exists('hospitals', 'thana_id');

        $division_id_select = $hospital_has_division_id
            ? 'COALESCE(h.division_id, 0) AS division_id'
            : '0 AS division_id';

        $thana_id_select = $hospital_has_thana_id
            ? 'COALESCE(h.thana_id, 0) AS thana_id'
            : '0 AS thana_id';

        $district_join = '';

        if (!$hospital_has_division_id && doctor_component_table_exists('districts') && doctor_component_column_exists('districts', 'division_id')) {
            $district_join = " LEFT JOIN districts d ON d.id = h.district_id";
            $division_id_select = 'COALESCE(d.division_id, 0) AS division_id';
        }

        try {
            $sql = "
                SELECT
                    c.id AS chamber_id,
                    h.id AS hospital_id,
                    {$division_id_select},
                    COALESCE(h.district_id, 0) AS district_id,
                    {$thana_id_select}
                FROM chambers c
                LEFT JOIN hospitals h ON h.id = c.hospital_id
                {$district_join}
                WHERE c.doctor_id = :doctor_id
                  AND c.hospital_id > 0
                ORDER BY c.sort_order ASC, c.id DESC
                LIMIT 1
            ";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([':doctor_id' => $doctor_id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            return $row ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }
}

if (!function_exists('doctor_contact_location_apply_auto_from_first_chamber')) {
    function doctor_contact_location_apply_auto_from_first_chamber(int $doctor_id, array $doctor, array $first_chamber_location): array
    {
        global $pdo;

        if ($doctor_id <= 0 || !doctor_contact_location_has_pdo() || empty($first_chamber_location)) {
            return $doctor;
        }

        $new_division_id = (int)($first_chamber_location['division_id'] ?? 0);
        $new_district_id = (int)($first_chamber_location['district_id'] ?? 0);
        $new_thana_id = (int)($first_chamber_location['thana_id'] ?? 0);
        $new_chamber_id = (int)($first_chamber_location['chamber_id'] ?? 0);

        $old_division_id = (int)($doctor['doctor_division_id'] ?? 0);
        $old_district_id = (int)($doctor['doctor_district_id'] ?? 0);
        $old_thana_id = (int)($doctor['doctor_thana_id'] ?? 0);
        $old_chamber_id = (int)($doctor['doctor_location_auto_chamber_id'] ?? 0);
        $old_source = trim((string)($doctor['doctor_location_source'] ?? ''));

        $needs_update = (
            $old_source !== 'auto' ||
            $old_division_id !== $new_division_id ||
            $old_district_id !== $new_district_id ||
            $old_thana_id !== $new_thana_id ||
            $old_chamber_id !== $new_chamber_id
        );

        if ($needs_update) {
            try {
                $stmt = $pdo->prepare("
                    UPDATE doctors
                    SET
                        doctor_division_id = :division_id,
                        doctor_district_id = :district_id,
                        doctor_thana_id = :thana_id,
                        doctor_location_source = 'auto',
                        doctor_location_auto_chamber_id = :chamber_id,
                        updated_at = NOW()
                    WHERE id = :id
                ");
                $stmt->execute([
                    ':division_id' => $new_division_id,
                    ':district_id' => $new_district_id,
                    ':thana_id' => $new_thana_id,
                    ':chamber_id' => $new_chamber_id,
                    ':id' => $doctor_id,
                ]);
            } catch (Throwable $e) {
                // Auto location update should not break the form page.
            }
        }

        $doctor['doctor_division_id'] = $new_division_id;
        $doctor['doctor_district_id'] = $new_district_id;
        $doctor['doctor_thana_id'] = $new_thana_id;
        $doctor['doctor_location_source'] = 'auto';
        $doctor['doctor_location_auto_chamber_id'] = $new_chamber_id;

        return $doctor;
    }
}

if (!function_exists('doctor_contact_location_handle_manual_post')) {
    function doctor_contact_location_handle_manual_post(): void
    {
        global $pdo;

        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !doctor_contact_location_has_pdo()) {
            return;
        }

        $form_action = trim((string)($_POST['form_action'] ?? ''));

        if (!in_array($form_action, ['', 'save_doctor'], true)) {
            return;
        }

        $doctor_id = (int)($_POST['doctor_id'] ?? ($_GET['id'] ?? 0));

        if ($doctor_id <= 0) {
            return;
        }

        $posted_source = trim((string)($_POST['doctor_location_source'] ?? ''));
        $posted_division_id = (int)($_POST['doctor_division_id'] ?? 0);
        $posted_district_id = (int)($_POST['doctor_district_id'] ?? 0);
        $posted_thana_id = (int)($_POST['doctor_thana_id'] ?? 0);
        $posted_location_is_empty = ($posted_division_id <= 0 && $posted_district_id <= 0 && $posted_thana_id <= 0);

        /*
         * Empty location should never stay manual.
         * If all location IDs are empty, switch back to auto mode.
         */
        if ($posted_location_is_empty) {
            try {
                $stmt = $pdo->prepare("
                    UPDATE doctors
                    SET
                        doctor_location_source = 'auto',
                        doctor_location_auto_chamber_id = 0,
                        updated_at = NOW()
                    WHERE id = :id
                ");
                $stmt->execute([':id' => $doctor_id]);
            } catch (Throwable $e) {
                // Auto source reset should not break the doctor save flow.
            }

            return;
        }

        if ($posted_source !== 'manual') {
            return;
        }

        try {
            $stmt = $pdo->prepare("
                UPDATE doctors
                SET
                    doctor_location_source = 'manual',
                    doctor_location_auto_chamber_id = 0,
                    updated_at = NOW()
                WHERE id = :id
            ");
            $stmt->execute([':id' => $doctor_id]);
        } catch (Throwable $e) {
            // Manual source tracking should not break the doctor save flow.
        }
    }
}

doctor_contact_location_handle_manual_post();

if (!function_exists('doctor_contact_location_get_divisions')) {
    function doctor_contact_location_get_divisions(array $context = []): array
    {
        if (!empty($context['doctor_form_divisions']) && is_array($context['doctor_form_divisions'])) {
            return $context['doctor_form_divisions'];
        }

        return doctor_contact_location_rows('divisions');
    }
}

if (!function_exists('doctor_contact_location_render')) {
    function doctor_contact_location_render(array $context = []): void
    {
        extract($context, EXTR_SKIP);

        $doctor = isset($doctor) && is_array($doctor) ? $doctor : [];
        $id = (int)($id ?? ($doctor['id'] ?? 0));

        doctor_contact_location_css();

        $doctor_contact_auto_from_chamber = false;
        $doctor_contact_fallback = [];
        $doctor_location_source = trim((string)($doctor['doctor_location_source'] ?? ''));
        $doctor_location_is_empty = doctor_contact_location_is_location_empty($doctor);

        /*
         * Auto rule:
         * - Empty location always stays in auto mode.
         * - If source is auto, keep syncing with the current first chamber.
         * - Manual mode only applies when a saved location exists.
         */
        if ($doctor_location_is_empty) {
            $doctor_location_source = 'auto';
            $doctor['doctor_location_source'] = 'auto';
        }

        $doctor_location_is_manual = ($doctor_location_source === 'manual' && !$doctor_location_is_empty);
        $should_auto_sync = $doctor_location_is_empty || (!$doctor_location_is_manual && $doctor_location_source === 'auto');

        if ($id > 0 && $should_auto_sync) {
            $doctor_contact_fallback = doctor_contact_location_get_first_chamber_location($id);

            if (!empty($doctor_contact_fallback)) {
                $doctor_contact_auto_from_chamber = true;
                $doctor = doctor_contact_location_apply_auto_from_first_chamber($id, $doctor, $doctor_contact_fallback);
                $doctor_location_source = 'auto';
            }
        }

        if ($doctor_location_source === '') {
            $doctor_location_source = $doctor_contact_auto_from_chamber || $doctor_location_is_empty ? 'auto' : 'manual';
        }

        $contact_division_id = (int)($doctor['doctor_division_id'] ?? 0);
        $contact_district_id = (int)($doctor['doctor_district_id'] ?? 0);
        $contact_thana_id = (int)($doctor['doctor_thana_id'] ?? 0);

        $doctor_has_location = (
            $contact_division_id > 0 ||
            $contact_district_id > 0 ||
            $contact_thana_id > 0
        );

        $doctor_form_divisions = doctor_contact_location_get_divisions($context);
        ?>

        <div class="gh-contact-card">
            <div class="gh-contact-head">
                <div class="gh-contact-title">
                    <div class="gh-contact-icon">LOC</div>
                    <div>
                        <h3>Contact & Location</h3>
                        <p>Manage doctor phone number, WhatsApp, email and ID-based location chain.</p>
                    </div>
                </div>

                <div class="gh-contact-badge">
                    <span class="gh-contact-badge-dot"></span>
                    <span><?= $doctor_has_location ? 'Location ready' : 'Location empty' ?></span>
                </div>
            </div>

            <div class="gh-contact-body">
                <?php if ($doctor_contact_auto_from_chamber): ?>
                    <div class="gh-auto-note">
                        <span class="gh-auto-note-icon">i</span>
                        <span>
                            Location is auto-synced from the first hospital availability chamber. If the first chamber changes, this location will update again automatically.
                        </span>
                    </div>
                <?php elseif ($doctor_location_source === 'manual' && !$doctor_location_is_empty): ?>
                    <div class="gh-auto-note">
                        <span class="gh-auto-note-icon">i</span>
                        <span>
                            Manual location mode is active. Hospital availability changes will not overwrite this location.
                        </span>
                    </div>
                <?php endif; ?>

                <input type="hidden" name="doctor_location_source" id="doctor_location_source" value="<?= doctor_contact_location_e($doctor_location_source) ?>">

                <div class="gh-contact-grid">
                    <div class="gh-field">
                        <label>Phone</label>
                        <input
                            type="text"
                            name="phone"
                            placeholder="+8801712345678"
                            value="<?= doctor_contact_location_e($doctor['phone'] ?? '') ?>"
                        >
                    </div>

                    <div class="gh-field">
                        <label>WhatsApp</label>
                        <input
                            type="text"
                            name="whatsapp"
                            placeholder="+8801712345678"
                            value="<?= doctor_contact_location_e($doctor['whatsapp'] ?? '') ?>"
                        >
                    </div>

                    <div class="gh-field full">
                        <label>Email</label>
                        <input
                            type="email"
                            name="email"
                            placeholder="doctor@example.com"
                            value="<?= doctor_contact_location_e($doctor['email'] ?? '') ?>"
                        >
                    </div>
                </div>

                <div class="gh-contact-grid three" style="margin-top:16px;">
                    <div class="gh-field">
                        <label>Division</label>
                        <select id="doctor_address_division" name="doctor_division_id">
                            <option value="">— Select Division —</option>
                            <?php foreach ($doctor_form_divisions as $division): ?>
                                <option
                                    value="<?= doctor_contact_location_e((string)($division['id'] ?? '')) ?>"
                                    <?= ($contact_division_id === (int)($division['id'] ?? 0)) ? 'selected' : '' ?>
                                >
                                    <?= doctor_contact_location_e($division['name'] ?? ($division['name_en'] ?? '')) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="gh-field">
                        <label>District</label>
                        <select
                            id="doctor_address_district"
                            name="doctor_district_id"
                            data-current="<?= doctor_contact_location_e((string)$contact_district_id) ?>"
                            disabled
                        >
                            <option value="">— Select District —</option>
                        </select>
                    </div>

                    <div class="gh-field">
                        <label>Thana / Upazila</label>
                        <select
                            id="doctor_address_thana"
                            name="doctor_thana_id"
                            data-current="<?= doctor_contact_location_e((string)$contact_thana_id) ?>"
                            disabled
                        >
                            <option value="">— Select Thana —</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <script>
            (function() {
                const doctorAddressDivision = document.getElementById('doctor_address_division');
                const doctorAddressDistrict = document.getElementById('doctor_address_district');
                const doctorAddressThana = document.getElementById('doctor_address_thana');
                const doctorLocationSource = document.getElementById('doctor_location_source');

                function resetSelect(select, placeholder) {
                    if (!select) {
                        return;
                    }

                    select.innerHTML = `<option value="">${placeholder}</option>`;
                    select.value = '';
                    select.disabled = true;
                }

                function fillSelect(select, items, placeholder) {
                    if (!select) {
                        return;
                    }

                    select.innerHTML = `<option value="">${placeholder}</option>`;

                    if (!Array.isArray(items)) {
                        items = [];
                    }

                    items.forEach(function(item) {
                        const option = document.createElement('option');
                        option.value = item.id || '';
                        option.textContent = item.name || '';
                        select.appendChild(option);
                    });

                    select.disabled = false;
                    select.value = '';
                }

                function selectValueIfExists(select, selectedValue, placeholder) {
                    if (!select) {
                        return;
                    }

                    const value = String(selectedValue || '');

                    if (!value) {
                        select.value = '';
                        return;
                    }

                    const optionExists = Array.from(select.options).some(function(option) {
                        return option.value === value;
                    });

                    select.value = optionExists ? value : '';

                    if (!optionExists && placeholder) {
                        select.innerHTML = `<option value="">${placeholder}</option>` + select.innerHTML.replace(`<option value="">${placeholder}</option>`, '');
                        select.value = '';
                    }
                }

                function updateLocationSourceMode() {
                    if (!doctorLocationSource) {
                        return;
                    }

                    const hasAnyLocation = Boolean(
                        (doctorAddressDivision && doctorAddressDivision.value) ||
                        (doctorAddressDistrict && doctorAddressDistrict.value) ||
                        (doctorAddressThana && doctorAddressThana.value)
                    );

                    doctorLocationSource.value = hasAnyLocation ? 'manual' : 'auto';
                }

                function ajaxUrl(type, params) {
                    const url = new URL(window.location.href);
                    url.searchParams.set('doctor_contact_location_ajax', type);

                    Object.keys(params || {}).forEach(function(key) {
                        url.searchParams.set(key, params[key]);
                    });

                    return url.toString();
                }

                async function loadDistricts(divisionId, selectedDistrictId = '') {
                    resetSelect(doctorAddressDistrict, '— Select District —');
                    resetSelect(doctorAddressThana, '— Select Thana —');

                    if (!divisionId) {
                        updateLocationSourceMode();
                        return;
                    }

                    try {
                        const response = await fetch(ajaxUrl('districts', {division_id: divisionId}), {
                            headers: {'Accept': 'application/json'}
                        });

                        const districts = await response.json();

                        fillSelect(doctorAddressDistrict, districts, '— Select District —');
                        selectValueIfExists(doctorAddressDistrict, selectedDistrictId, '— Select District —');

                        if (!doctorAddressDistrict.value) {
                            resetSelect(doctorAddressThana, '— Select Thana —');
                        }

                        updateLocationSourceMode();
                    } catch (error) {
                        resetSelect(doctorAddressDistrict, '— Select District —');
                        resetSelect(doctorAddressThana, '— Select Thana —');
                        updateLocationSourceMode();
                        console.error(error);
                    }
                }

                async function loadThanas(districtId, selectedThanaId = '') {
                    resetSelect(doctorAddressThana, '— Select Thana —');

                    if (!districtId) {
                        updateLocationSourceMode();
                        return;
                    }

                    try {
                        const response = await fetch(ajaxUrl('thanas', {district_id: districtId}), {
                            headers: {'Accept': 'application/json'}
                        });

                        const thanas = await response.json();

                        fillSelect(doctorAddressThana, thanas, '— Select Thana —');
                        selectValueIfExists(doctorAddressThana, selectedThanaId, '— Select Thana —');

                        if (!doctorAddressThana.value) {
                            doctorAddressThana.value = '';
                        }

                        updateLocationSourceMode();
                    } catch (error) {
                        resetSelect(doctorAddressThana, '— Select Thana —');
                        updateLocationSourceMode();
                        console.error(error);
                    }
                }

                if (doctorAddressDivision) {
                    doctorAddressDivision.addEventListener('change', function() {
                        updateLocationSourceMode();
                        loadDistricts(this.value);
                    });
                }

                if (doctorAddressDistrict) {
                    doctorAddressDistrict.addEventListener('change', function() {
                        updateLocationSourceMode();
                        loadThanas(this.value);
                    });
                }

                if (doctorAddressThana) {
                    doctorAddressThana.addEventListener('change', function() {
                        if (!this.value) {
                            this.value = '';
                        }

                        updateLocationSourceMode();
                    });
                }

                if (doctorAddressDivision && doctorAddressDivision.value) {
                    const savedDistrictId = doctorAddressDistrict ? doctorAddressDistrict.dataset.current : '';
                    const savedThanaId = doctorAddressThana ? doctorAddressThana.dataset.current : '';

                    loadDistricts(doctorAddressDivision.value, savedDistrictId).then(function() {
                        if (doctorAddressDistrict && doctorAddressDistrict.value) {
                            loadThanas(doctorAddressDistrict.value, savedThanaId);
                        } else {
                            resetSelect(doctorAddressThana, '— Select Thana —');
                        }
                    });
                } else {
                    resetSelect(doctorAddressDistrict, '— Select District —');
                    resetSelect(doctorAddressThana, '— Select Thana —');
                }
            })();
        </script>
        <?php
    }
}

if (!function_exists('doctor_contact_render')) {
    function doctor_contact_render(array $context = []): void
    {
        doctor_contact_location_render($context);
    }
}

if (!function_exists('doctor_contact_location_section_render')) {
    function doctor_contact_location_section_render(array $context = []): void
    {
        doctor_contact_location_render($context);
    }
}

if (!empty($GLOBALS['doctor_form_auto_render_sections'])) {
    doctor_contact_location_render($GLOBALS['doctor_form_context'] ?? []);
}