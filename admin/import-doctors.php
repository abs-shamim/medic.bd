<?php
require_once __DIR__ . '/../includes/functions.php';
require_admin();

/*
|--------------------------------------------------------------------------
| Doctor CSV Importer
|--------------------------------------------------------------------------
| - No CREATE TABLE
| - No ALTER TABLE
| - Uses existing DB tables/columns only
| - Duplicate doctor check: bmdc_number, email, slug
| - Same name doctor allowed
| - New doctor slug: name + specialty + district
| - If doctor district missing, first chamber district is used
| - Missing Specialty/District/Thana can be auto-created by checkbox
| - Chamber saves doctor_id + hospital_id + schedule/contact info
| - Chamber supports 5+ dynamic CSV groups and saves doctor_id + hospital_id + schedule/contact info
| - Chamber does NOT save: name, chamber_name, chamber_name_bn, serial_phone, assistant_phone
| - If chamber hospital not found, chamber_name creates hospital
| - Hospital slug priority:
|   1. hospital-name
|   2. hospital-name-district
|   3. hospital-name-district-thana
|   4. hospital-name-district-thana-2, -3
| - Bangladesh phone numbers are normalized to +880 format when recognized
| - Remote doctor images are saved as WebP only
|--------------------------------------------------------------------------
*/

$import_message = '';
$import_message_type = '';
$import_logs = [];

$import_stats = [
    'processed' => 0,
    'created' => 0,
    'updated' => 0,
    'skipped' => 0,

    'duplicates_found' => 0,
    'bmdc_duplicates' => 0,
    'email_duplicates' => 0,
    'slug_duplicates' => 0,

    'specialties_matched' => 0,
    'specialties_created' => 0,
    'specialties_unmatched' => 0,

    'divisions_matched' => 0,
    'divisions_created' => 0,
    'divisions_unmatched' => 0,

    'districts_matched' => 0,
    'districts_created' => 0,
    'districts_unmatched' => 0,

    'thanas_matched' => 0,
    'thanas_created' => 0,
    'thanas_unmatched' => 0,

    'hospitals_matched' => 0,
    'hospitals_created' => 0,
    'hospitals_unmatched' => 0,

    'images_uploaded' => 0,
    'images_failed' => 0,

    'chambers_created' => 0,
    'chambers_updated' => 0,
    'chambers_skipped' => 0,
];

if (!function_exists('doctor_import_e')) {
    function doctor_import_e($value): string
    {
        if (function_exists('e')) {
            return e($value);
        }

        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('doctor_import_has_pdo')) {
    function doctor_import_has_pdo(): bool
    {
        global $pdo;

        return isset($pdo) && $pdo instanceof PDO;
    }
}

if (!function_exists('doctor_import_safe_identifier')) {
    function doctor_import_safe_identifier(string $name): string
    {
        return preg_match('/^[A-Za-z0-9_]+$/', $name) ? $name : '';
    }
}

if (!function_exists('doctor_import_table_exists')) {
    function doctor_import_table_exists(string $table): bool
    {
        global $pdo;

        $table = doctor_import_safe_identifier($table);

        if ($table === '' || !doctor_import_has_pdo()) {
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

if (!function_exists('doctor_import_column_exists')) {
    function doctor_import_column_exists(string $table, string $column): bool
    {
        global $pdo;

        static $cache = [];

        $table = doctor_import_safe_identifier($table);
        $column = doctor_import_safe_identifier($column);

        if ($table === '' || $column === '' || !doctor_import_has_pdo()) {
            return false;
        }

        $key = $table . '.' . $column;

        if (array_key_exists($key, $cache)) {
            return $cache[$key];
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

            $cache[$key] = (int)$stmt->fetchColumn() > 0;

            return $cache[$key];
        } catch (Throwable $e) {
            $cache[$key] = false;

            return false;
        }
    }
}

if (!function_exists('doctor_import_clean')) {
    function doctor_import_clean($value, int $limit = 500): string
    {
        $value = html_entity_decode((string)$value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = str_replace("\xEF\xBB\xBF", '', $value);
        $value = strip_tags($value);
        $value = trim($value);
        $value = preg_replace('/\s+/u', ' ', (string)$value);

        if (function_exists('mb_substr')) {
            return mb_substr((string)$value, 0, $limit, 'UTF-8');
        }

        return substr((string)$value, 0, $limit);
    }
}

if (!function_exists('doctor_import_phone')) {
    function doctor_import_phone($value, int $limit = 100): string
    {
        $value = doctor_import_clean($value, $limit);

        if ($value === '') {
            return '';
        }

        /*
         * Bangladesh phone normalize.
         * Supported examples:
         * 01711111111      -> +8801711111111
         * 1711111111       -> +8801711111111
         * 8801711111111    -> +8801711111111
         * 008801711111111  -> +8801711111111
         * 09612345678      -> +8809612345678
         * 9612345678       -> +8809612345678
         */
        $value = preg_replace('/[\s\-\(\)\.]+/u', '', $value);
        $value = preg_replace('/[^0-9+]/', '', (string)$value);

        if ($value === '') {
            return '';
        }

        if (substr_count($value, '+') > 1) {
            $value = '+' . str_replace('+', '', $value);
        } elseif (strpos($value, '+') > 0) {
            $value = str_replace('+', '', $value);
            $value = '+' . $value;
        }

        // Already normalized Bangladesh mobile: +8801XXXXXXXXX
        if (preg_match('/^\+8801[0-9]{9}$/', $value)) {
            return $value;
        }

        // Already normalized Bangladesh 096/IP number: +88096XXXXXXXX
        if (preg_match('/^\+88096[0-9]{8}$/', $value)) {
            return $value;
        }

        // 008801XXXXXXXXX -> +8801XXXXXXXXX
        if (preg_match('/^00880(1[0-9]{9})$/', $value, $matches)) {
            return '+880' . $matches[1];
        }

        // 0088096XXXXXXXX -> +88096XXXXXXXX
        if (preg_match('/^00880(96[0-9]{8})$/', $value, $matches)) {
            return '+880' . $matches[1];
        }

        // 8801XXXXXXXXX -> +8801XXXXXXXXX
        if (preg_match('/^880(1[0-9]{9})$/', $value, $matches)) {
            return '+880' . $matches[1];
        }

        // 88096XXXXXXXX -> +88096XXXXXXXX
        if (preg_match('/^880(96[0-9]{8})$/', $value, $matches)) {
            return '+880' . $matches[1];
        }

        // 01XXXXXXXXX -> +8801XXXXXXXXX
        if (preg_match('/^(01[0-9]{9})$/', $value, $matches)) {
            return '+88' . $matches[1];
        }

        // 1XXXXXXXXX -> +8801XXXXXXXXX
        if (preg_match('/^(1[0-9]{9})$/', $value, $matches)) {
            return '+880' . $matches[1];
        }

        // 096XXXXXXXX -> +88096XXXXXXXX
        if (preg_match('/^(096[0-9]{8})$/', $value, $matches)) {
            return '+88' . $matches[1];
        }

        // 96XXXXXXXX -> +88096XXXXXXXX
        if (preg_match('/^(96[0-9]{8})$/', $value, $matches)) {
            return '+880' . $matches[1];
        }

        return $value;
    }
}

if (!function_exists('doctor_import_text')) {
    function doctor_import_text($value, int $limit = 8000): string
    {
        $value = html_entity_decode((string)$value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = str_replace("\xEF\xBB\xBF", '', $value);
        $value = strip_tags($value);
        $value = trim($value);
        $value = preg_replace("/\r\n|\r/u", "\n", (string)$value);
        $value = preg_replace('/[ \t]+/u', ' ', (string)$value);
        $value = preg_replace("/\n{3,}/u", "\n\n", (string)$value);

        if (function_exists('mb_substr')) {
            return mb_substr((string)$value, 0, $limit, 'UTF-8');
        }

        return substr((string)$value, 0, $limit);
    }
}

if (!function_exists('doctor_import_bool')) {
    function doctor_import_bool($value): int
    {
        $value = strtolower(trim((string)$value));

        return in_array($value, [
            '1',
            'yes',
            'true',
            'active',
            'verified',
            'featured',
            'published',
            'available',
        ], true) ? 1 : 0;
    }
}

if (!function_exists('doctor_import_int')) {
    function doctor_import_int($value): int
    {
        $value = preg_replace('/[^0-9]/', '', (string)$value);

        return $value === '' ? 0 : (int)$value;
    }
}

if (!function_exists('doctor_import_int_or_null')) {
    function doctor_import_int_or_null($value): ?int
    {
        $value = trim((string)$value);

        if ($value === '') {
            return null;
        }

        $value = preg_replace('/[^0-9]/', '', $value);

        return $value === '' ? null : (int)$value;
    }
}

if (!function_exists('doctor_import_float')) {
    function doctor_import_float($value): float
    {
        $value = preg_replace('/[^0-9.]/', '', (string)$value);

        return $value === '' ? 0.00 : (float)$value;
    }
}

if (!function_exists('doctor_import_rating')) {
    function doctor_import_rating($value): float
    {
        return min(5, max(0, doctor_import_float($value)));
    }
}

if (!function_exists('doctor_import_status')) {
    function doctor_import_status($value, string $default = 'active'): string
    {
        $value = strtolower(trim((string)$value));

        if (in_array($value, ['active', 'inactive', 'pending'], true)) {
            return $value;
        }

        return $default;
    }
}

if (!function_exists('doctor_import_gender')) {
    function doctor_import_gender($value): string
    {
        $value = strtolower(trim((string)$value));

        if (in_array($value, ['male', 'm', 'পুরুষ'], true)) {
            return 'Male';
        }

        if (in_array($value, ['female', 'f', 'মহিলা', 'নারী'], true)) {
            return 'Female';
        }

        if (in_array($value, ['other', 'others'], true)) {
            return 'Other';
        }

        return '';
    }
}

if (!function_exists('doctor_import_stat_inc')) {
    function doctor_import_stat_inc(string $key, int $amount = 1): void
    {
        if (isset($GLOBALS['import_stats'][$key])) {
            $GLOBALS['import_stats'][$key] += $amount;
        }
    }
}

if (!function_exists('doctor_import_slug')) {
    function doctor_import_slug(string $text): string
    {
        $text = doctor_import_clean($text, 255);

        if ($text === '') {
            return 'item';
        }

        if (function_exists('slugify')) {
            $slug = slugify($text);
        } else {
            $slug = strtolower($text);
            $slug = preg_replace('/[^a-z0-9]+/i', '-', $slug);
            $slug = trim((string)$slug, '-');
        }

        $slug = preg_replace('/[^a-z0-9-]+/i', '', (string)$slug);
        $slug = preg_replace('/-+/', '-', (string)$slug);
        $slug = trim((string)$slug, '-');

        return $slug !== '' ? strtolower($slug) : 'item';
    }
}

if (!function_exists('doctor_import_header_map')) {
    function doctor_import_header_map(array $header): array
    {
        $map = [];

        foreach ($header as $index => $column) {
            $key = strtolower(trim((string)$column));
            $key = str_replace("\xEF\xBB\xBF", '', $key);
            $key = preg_replace('/[^a-z0-9_]+/', '_', $key);
            $key = trim((string)$key, '_');

            if ($key !== '') {
                $map[$key] = $index;
            }
        }

        return $map;
    }
}

if (!function_exists('doctor_import_csv_value')) {
    function doctor_import_csv_value(array $row, array $map, string $key, string $default = ''): string
    {
        if (!isset($map[$key])) {
            return $default;
        }

        $index = (int)$map[$key];

        return isset($row[$index]) ? trim((string)$row[$index]) : $default;
    }
}

if (!function_exists('doctor_import_first_value')) {
    function doctor_import_first_value(array $row, array $map, array $keys, string $default = ''): string
    {
        foreach ($keys as $key) {
            $value = doctor_import_csv_value($row, $map, $key, '');

            if (trim($value) !== '') {
                return $value;
            }
        }

        return $default;
    }
}

/*
|--------------------------------------------------------------------------
| Dynamic Insert / Update
|--------------------------------------------------------------------------
*/

if (!function_exists('doctor_import_pick_existing_columns')) {
    function doctor_import_pick_existing_columns(string $table, array $data): array
    {
        $picked = [];

        foreach ($data as $column => $value) {
            $column = doctor_import_safe_identifier($column);

            if ($column !== '' && doctor_import_column_exists($table, $column)) {
                $picked[$column] = $value;
            }
        }

        return $picked;
    }
}

if (!function_exists('doctor_import_insert_dynamic')) {
    function doctor_import_insert_dynamic(string $table, array $data): int
    {
        global $pdo;

        $table = doctor_import_safe_identifier($table);

        if ($table === '' || !doctor_import_table_exists($table)) {
            return 0;
        }

        /*
         * Important:
         * Doctor images are already downloaded and converted by doctor_import_store_image()
         * before insert/update. That function returns the final local upload URL/path.
         * Do not pass that value through doctor_import_local_image_only() here, because
         * absolute UPLOAD_URL values like https://domain.com/assets/uploads/... would be
         * treated as external URLs and converted to an empty string.
         */

        $data = doctor_import_pick_existing_columns($table, $data);

        if (!$data) {
            return 0;
        }

        $columns = [];
        $values = [];
        $params = [];

        foreach ($data as $column => $value) {
            $columns[] = "`{$column}`";

            if ($value === '__NOW__') {
                $values[] = 'NOW()';
            } else {
                $placeholder = ':' . $column;
                $values[] = $placeholder;
                $params[$placeholder] = $value;
            }
        }

        $stmt = $pdo->prepare("
            INSERT INTO `{$table}` (" . implode(', ', $columns) . ")
            VALUES (" . implode(', ', $values) . ")
        ");
        $stmt->execute($params);

        return (int)$pdo->lastInsertId();
    }
}

if (!function_exists('doctor_import_update_dynamic')) {
    function doctor_import_update_dynamic(string $table, int $id, array $data): bool
    {
        global $pdo;

        $table = doctor_import_safe_identifier($table);

        if ($id <= 0 || $table === '' || !doctor_import_table_exists($table)) {
            return false;
        }

        /*
         * Important:
         * Doctor images are already downloaded and converted by doctor_import_store_image()
         * before insert/update. That function returns the final local upload URL/path.
         * Do not pass that value through doctor_import_local_image_only() here, because
         * absolute UPLOAD_URL values like https://domain.com/assets/uploads/... would be
         * treated as external URLs and converted to an empty string.
         */

        $data = doctor_import_pick_existing_columns($table, $data);

        if (!$data) {
            return false;
        }

        $sets = [];
        $params = [':id' => $id];

        foreach ($data as $column => $value) {
            if ($column === 'id') {
                continue;
            }

            if ($value === '__NOW__') {
                $sets[] = "`{$column}` = NOW()";
            } else {
                $placeholder = ':' . $column;
                $sets[] = "`{$column}` = {$placeholder}";
                $params[$placeholder] = $value;
            }
        }

        if (!$sets) {
            return false;
        }

        $stmt = $pdo->prepare("UPDATE `{$table}` SET " . implode(', ', $sets) . " WHERE id = :id");

        return $stmt->execute($params);
    }
}

if (!function_exists('doctor_import_preserve_existing_empty_values')) {
    function doctor_import_preserve_existing_empty_values(array $new_data, array $existing, array $zero_preserve_columns = []): array
    {
        foreach ($new_data as $column => $value) {
            $is_empty = $value === '' || $value === null;

            if (in_array($column, $zero_preserve_columns, true) && (string)$value === '0') {
                $is_empty = true;
            }

            if ($is_empty && array_key_exists($column, $existing)) {
                $new_data[$column] = $existing[$column];
            }
        }

        return $new_data;
    }
}



if (!function_exists('doctor_import_join_unique')) {
    function doctor_import_join_unique(array $items, string $separator = ', '): string
    {
        $clean = [];

        foreach ($items as $item) {
            if (is_array($item)) {
                foreach ($item as $sub_item) {
                    $sub_item = doctor_import_clean($sub_item, 255);

                    if ($sub_item !== '') {
                        $clean[] = $sub_item;
                    }
                }
            } else {
                $item = doctor_import_clean($item, 255);

                if ($item !== '') {
                    $clean[] = $item;
                }
            }
        }

        $clean = array_values(array_unique($clean));

        return implode($separator, $clean);
    }
}





/*
|--------------------------------------------------------------------------
| Generic record create
|--------------------------------------------------------------------------
*/

if (!function_exists('doctor_import_create_named_record')) {
    function doctor_import_create_named_record(string $table, array $data): int
    {
        if (!doctor_import_table_exists($table)) {
            return 0;
        }

        if (doctor_import_column_exists($table, 'status') && !isset($data['status'])) {
            $data['status'] = 'active';
        }

        if (doctor_import_column_exists($table, 'created_at') && !isset($data['created_at'])) {
            $data['created_at'] = '__NOW__';
        }

        if (doctor_import_column_exists($table, 'updated_at') && !isset($data['updated_at'])) {
            $data['updated_at'] = '__NOW__';
        }

        return doctor_import_insert_dynamic($table, $data);
    }
}

/*
|--------------------------------------------------------------------------
| Location Helpers with Auto Create
|--------------------------------------------------------------------------
*/

if (!function_exists('doctor_import_find_location_id')) {
    function doctor_import_find_location_id(string $table, string $name, string $parent_column = '', int $parent_id = 0): int
    {
        global $pdo;

        $allowed_tables = ['divisions', 'districts', 'thanas'];
        $allowed_parent_columns = ['division_id', 'district_id'];

        $table = doctor_import_safe_identifier($table);
        $name = doctor_import_clean($name, 150);

        if ($table === '' || $name === '' || !in_array($table, $allowed_tables, true) || !doctor_import_table_exists($table)) {
            return 0;
        }

        $columns = [];

        foreach (['name_en', 'name_bn', 'name'] as $column) {
            if (doctor_import_column_exists($table, $column)) {
                $columns[] = $column;
            }
        }

        foreach ($columns as $column) {
            $sql = "SELECT id FROM `{$table}` WHERE `{$column}` = :name";
            $params = [':name' => $name];

            if (
                $parent_column !== ''
                && in_array($parent_column, $allowed_parent_columns, true)
                && $parent_id > 0
                && doctor_import_column_exists($table, $parent_column)
            ) {
                $parent_column = doctor_import_safe_identifier($parent_column);
                $sql .= " AND `{$parent_column}` = :parent_id";
                $params[':parent_id'] = $parent_id;
            }

            if (doctor_import_column_exists($table, 'status')) {
                $sql .= " AND (status IS NULL OR status = '' OR status = 'active')";
            }

            $sql .= " LIMIT 1";

            try {
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);

                $id = (int)$stmt->fetchColumn();

                if ($id > 0) {
                    return $id;
                }
            } catch (Throwable $e) {
                continue;
            }
        }

        return 0;
    }
}

if (!function_exists('doctor_import_find_or_create_division_id')) {
    function doctor_import_find_or_create_division_id(string $division, bool $auto_create = false): int
    {
        $division = doctor_import_clean($division, 150);

        if ($division === '') {
            return 0;
        }

        $id = doctor_import_find_location_id('divisions', $division);

        if ($id > 0) {
            doctor_import_stat_inc('divisions_matched');
            return $id;
        }

        doctor_import_stat_inc('divisions_unmatched');

        if (!$auto_create) {
            return 0;
        }

        $data = [
            'name_en' => $division,
            'name' => $division,
            'slug' => doctor_import_unique_slug_for_table('divisions', $division),
            'status' => 'active',
        ];

        $new_id = doctor_import_create_named_record('divisions', $data);

        if ($new_id > 0) {
            doctor_import_stat_inc('divisions_created');
            $GLOBALS['import_logs'][] = "Division created #{$new_id} - {$division}.";
        }

        return $new_id;
    }
}

if (!function_exists('doctor_import_find_or_create_district_id')) {
    function doctor_import_find_or_create_district_id(string $district, int $division_id = 0, bool $auto_create = false): int
    {
        $district = doctor_import_clean($district, 150);

        if ($district === '') {
            return 0;
        }

        $id = doctor_import_find_location_id('districts', $district, 'division_id', $division_id);

        if ($id <= 0) {
            $id = doctor_import_find_location_id('districts', $district);
        }

        if ($id > 0) {
            doctor_import_stat_inc('districts_matched');
            return $id;
        }

        doctor_import_stat_inc('districts_unmatched');

        if (!$auto_create) {
            return 0;
        }

        $data = [
            'name_en' => $district,
            'name' => $district,
            'slug' => doctor_import_unique_slug_for_table('districts', $district),
            'division_id' => $division_id > 0 ? $division_id : null,
            'status' => 'active',
        ];

        $new_id = doctor_import_create_named_record('districts', $data);

        if ($new_id > 0) {
            doctor_import_stat_inc('districts_created');
            $GLOBALS['import_logs'][] = "District created #{$new_id} - {$district}.";
        }

        return $new_id;
    }
}

if (!function_exists('doctor_import_find_or_create_thana_id')) {
    function doctor_import_find_or_create_thana_id(string $thana, int $district_id = 0, bool $auto_create = false): int
    {
        $thana = doctor_import_clean($thana, 150);

        if ($thana === '') {
            return 0;
        }

        $id = doctor_import_find_location_id('thanas', $thana, 'district_id', $district_id);

        if ($id <= 0) {
            $id = doctor_import_find_location_id('thanas', $thana);
        }

        if ($id > 0) {
            doctor_import_stat_inc('thanas_matched');
            return $id;
        }

        doctor_import_stat_inc('thanas_unmatched');

        if (!$auto_create) {
            return 0;
        }

        if ($district_id <= 0) {
            $GLOBALS['import_logs'][] = "Thana not created because district is missing - {$thana}.";
            return 0;
        }

        $data = [
            'name_en' => $thana,
            'name' => $thana,
            'slug' => doctor_import_unique_slug_for_table('thanas', $thana),
            'district_id' => $district_id,
            'status' => 'active',
        ];

        $new_id = doctor_import_create_named_record('thanas', $data);

        if ($new_id > 0) {
            doctor_import_stat_inc('thanas_created');
            $GLOBALS['import_logs'][] = "Thana created #{$new_id} - {$thana}.";
        }

        return $new_id;
    }
}

if (!function_exists('doctor_import_resolve_location')) {
    function doctor_import_resolve_location(string $division, string $district, string $thana, bool $auto_create = false): array
    {
        global $pdo;

        $division = doctor_import_clean($division, 150);
        $district = doctor_import_clean($district, 150);
        $thana = doctor_import_clean($thana, 150);

        $division_id = doctor_import_find_or_create_division_id($division, $auto_create);

        $district_id = doctor_import_find_or_create_district_id($district, $division_id, $auto_create);

        if ($division_id <= 0 && $district_id > 0 && doctor_import_column_exists('districts', 'division_id')) {
            try {
                $stmt = $pdo->prepare("SELECT division_id FROM districts WHERE id = :id LIMIT 1");
                $stmt->execute([':id' => $district_id]);
                $division_id = (int)$stmt->fetchColumn();
            } catch (Throwable $e) {
                $division_id = 0;
            }
        }

        $thana_id = doctor_import_find_or_create_thana_id($thana, $district_id, $auto_create);

        return [
            'division_id' => $division_id,
            'district_id' => $district_id,
            'thana_id' => $thana_id,
        ];
    }
}

if (!function_exists('doctor_import_location_name')) {
    function doctor_import_location_name(string $table, int $id, string $lang = 'en'): string
    {
        global $pdo;

        $table = doctor_import_safe_identifier($table);

        if ($table === '' || $id <= 0 || !doctor_import_table_exists($table)) {
            return '';
        }

        $columns = $lang === 'bn'
            ? ['name_bn', 'name', 'name_en']
            : ['name_en', 'name', 'name_bn'];

        foreach ($columns as $column) {
            if (!doctor_import_column_exists($table, $column)) {
                continue;
            }

            try {
                $stmt = $pdo->prepare("SELECT `{$column}` FROM `{$table}` WHERE id = :id LIMIT 1");
                $stmt->execute([':id' => $id]);

                $value = doctor_import_clean($stmt->fetchColumn(), 150);

                if ($value !== '') {
                    return $value;
                }
            } catch (Throwable $e) {
                continue;
            }
        }

        return '';
    }
}

/*
|--------------------------------------------------------------------------
| Specialty Helpers with Auto Create
|--------------------------------------------------------------------------
*/

if (!function_exists('doctor_import_db_name')) {
    function doctor_import_db_name(string $table, int $id): string
    {
        global $pdo;

        $table = doctor_import_safe_identifier($table);

        if ($table === '' || $id <= 0 || !doctor_import_table_exists($table)) {
            return '';
        }

        foreach (['name', 'name_en', 'title'] as $column) {
            if (!doctor_import_column_exists($table, $column)) {
                continue;
            }

            try {
                $stmt = $pdo->prepare("SELECT `{$column}` FROM `{$table}` WHERE id = :id LIMIT 1");
                $stmt->execute([':id' => $id]);

                $value = doctor_import_clean($stmt->fetchColumn(), 255);

                if ($value !== '') {
                    return $value;
                }
            } catch (Throwable $e) {
                continue;
            }
        }

        return '';
    }
}

if (!function_exists('doctor_import_find_named_id')) {
    function doctor_import_find_named_id(string $table, string $name): int
    {
        global $pdo;

        $table = doctor_import_safe_identifier($table);
        $name = doctor_import_clean($name, 255);

        if ($table === '' || $name === '' || !doctor_import_table_exists($table)) {
            return 0;
        }

        $columns = [];

        foreach (['name', 'name_en', 'name_bn', 'title', 'title_bn'] as $column) {
            if (doctor_import_column_exists($table, $column)) {
                $columns[] = $column;
            }
        }

        foreach ($columns as $column) {
            try {
                $sql = "SELECT id FROM `{$table}` WHERE `{$column}` = :name";

                if (doctor_import_column_exists($table, 'status')) {
                    $sql .= " AND (status IS NULL OR status = '' OR status = 'active')";
                }

                $sql .= " LIMIT 1";

                $stmt = $pdo->prepare($sql);
                $stmt->execute([':name' => $name]);

                $id = (int)$stmt->fetchColumn();

                if ($id > 0) {
                    return $id;
                }
            } catch (Throwable $e) {
                continue;
            }
        }

        if (doctor_import_column_exists($table, 'slug')) {
            try {
                $slug = doctor_import_slug($name);
                $stmt = $pdo->prepare("SELECT id FROM `{$table}` WHERE slug = :slug LIMIT 1");
                $stmt->execute([':slug' => $slug]);

                $id = (int)$stmt->fetchColumn();

                if ($id > 0) {
                    return $id;
                }
            } catch (Throwable $e) {
                return 0;
            }
        }

        return 0;
    }
}

if (!function_exists('doctor_import_find_or_create_specialty_id')) {
    function doctor_import_find_or_create_specialty_id(string $value, bool $auto_create = false): int
    {
        $value = doctor_import_clean($value, 255);

        if ($value === '') {
            return 0;
        }

        if (ctype_digit($value)) {
            return (int)$value;
        }

        /*
         * First try to find existing specialty by name, name_en, name_bn, title, or slug.
         */
        $id = doctor_import_find_named_id('specialties', $value);

        if ($id > 0) {
            doctor_import_stat_inc('specialties_matched');
            return $id;
        }

        doctor_import_stat_inc('specialties_unmatched');

        if (!$auto_create) {
            $GLOBALS['import_logs'][] = "Specialty not created because auto-create is off - {$value}.";
            return 0;
        }

        $data = [
            'name' => $value,
            'name_en' => $value,
            'slug' => doctor_import_unique_slug_for_table('specialties', $value),
            'status' => 'active',
            'created_at' => '__NOW__',
            'updated_at' => '__NOW__',
        ];

        if (doctor_import_column_exists('specialties', 'name_bn')) {
            $data['name_bn'] = '';
        }

        $new_id = doctor_import_create_named_record('specialties', $data);

        if ($new_id > 0) {
            doctor_import_stat_inc('specialties_created');
            $GLOBALS['import_logs'][] = "Specialty created #{$new_id} - {$value}.";
            return $new_id;
        }

        $GLOBALS['import_logs'][] = "Specialty create failed - {$value}.";

        return 0;
    }
}

/*
|--------------------------------------------------------------------------
| Slug Helpers
|--------------------------------------------------------------------------
*/

if (!function_exists('doctor_import_unique_slug_for_table')) {
    function doctor_import_unique_slug_for_table(string $table, string $slug, int $ignore_id = 0): string
    {
        global $pdo;

        $table = doctor_import_safe_identifier($table);
        $slug = doctor_import_slug($slug);

        if ($table === '' || !doctor_import_table_exists($table) || !doctor_import_column_exists($table, 'slug')) {
            return $slug;
        }

        $base = $slug;
        $counter = 2;

        while (true) {
            $sql = "SELECT id FROM `{$table}` WHERE slug = :slug";
            $params = [':slug' => $slug];

            if ($ignore_id > 0) {
                $sql .= " AND id != :id";
                $params[':id'] = $ignore_id;
            }

            $sql .= " LIMIT 1";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            if (!$stmt->fetchColumn()) {
                return $slug;
            }

            $slug = $base . '-' . $counter;
            $counter++;
        }
    }
}

if (!function_exists('doctor_import_generate_doctor_slug')) {
    function doctor_import_generate_doctor_slug(string $name, int $specialty_id, int $doctor_district_id, int $first_chamber_district_id = 0, int $ignore_id = 0): string
    {
        $specialty_name = doctor_import_db_name('specialties', $specialty_id);

        $district_id = $doctor_district_id > 0 ? $doctor_district_id : $first_chamber_district_id;
        $district_name = $district_id > 0 ? doctor_import_db_name('districts', $district_id) : '';

        $parts = array_filter([
            $name,
            $specialty_name,
            $district_name,
        ]);

        return doctor_import_unique_slug_for_table('doctors', implode('-', $parts), $ignore_id);
    }
}

if (!function_exists('doctor_import_hospital_slug_candidates')) {
    function doctor_import_hospital_slug_candidates(string $hospital_name, int $district_id = 0, int $thana_id = 0): array
    {
        $hospital_name = doctor_import_clean($hospital_name, 255);
        $district_name = $district_id > 0 ? doctor_import_location_name('districts', $district_id, 'en') : '';
        $thana_name = $thana_id > 0 ? doctor_import_location_name('thanas', $thana_id, 'en') : '';

        $candidates = [];

        if ($hospital_name !== '') {
            $candidates[] = doctor_import_slug($hospital_name);
        }

        if ($hospital_name !== '' && $district_name !== '') {
            $candidates[] = doctor_import_slug($hospital_name . '-' . $district_name);
        }

        if ($hospital_name !== '' && $district_name !== '' && $thana_name !== '') {
            $candidates[] = doctor_import_slug($hospital_name . '-' . $district_name . '-' . $thana_name);
        }

        $candidates = array_values(array_unique(array_filter($candidates)));

        return $candidates ?: ['hospital'];
    }
}

if (!function_exists('doctor_import_first_available_hospital_slug')) {
    function doctor_import_first_available_hospital_slug(array $candidates, int $ignore_id = 0): string
    {
        global $pdo;

        if (!doctor_import_table_exists('hospitals') || !doctor_import_column_exists('hospitals', 'slug')) {
            return doctor_import_slug($candidates[0] ?? 'hospital');
        }

        foreach ($candidates as $candidate) {
            $candidate = doctor_import_slug($candidate);

            if ($candidate === '') {
                continue;
            }

            $sql = "SELECT id FROM hospitals WHERE slug = :slug";
            $params = [':slug' => $candidate];

            if ($ignore_id > 0) {
                $sql .= " AND id != :id";
                $params[':id'] = $ignore_id;
            }

            $sql .= " LIMIT 1";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
                return $candidate;
            }
        }

        $last_candidate = end($candidates);

        return doctor_import_unique_slug_for_table('hospitals', $last_candidate ?: ($candidates[0] ?? 'hospital'), $ignore_id);
    }
}

/*
|--------------------------------------------------------------------------
| Hospital Helpers
|--------------------------------------------------------------------------
*/

if (!function_exists('doctor_import_hospital_type_bn')) {
    function doctor_import_hospital_type_bn(string $type): string
    {
        $map = [
            'Private Hospital' => 'বেসরকারি হাসপাতাল',
            'Government Hospital' => 'সরকারি হাসপাতাল',
            'Medical College Hospital' => 'মেডিকেল কলেজ হাসপাতাল',
            'Specialized Hospital' => 'বিশেষায়িত হাসপাতাল',
            'General Hospital' => 'জেনারেল হাসপাতাল',
            'Diagnostic Center' => 'ডায়াগনস্টিক সেন্টার',
            'Clinic' => 'ক্লিনিক',
            'Eye Hospital' => 'চক্ষু হাসপাতাল',
            'Dental Hospital' => 'ডেন্টাল হাসপাতাল',
            'Cardiac Hospital' => 'কার্ডিয়াক হাসপাতাল',
            'Cancer Hospital' => 'ক্যান্সার হাসপাতাল',
            'Mother and Child Hospital' => 'মা ও শিশু হাসপাতাল',
            'Rehabilitation Center' => 'পুনর্বাসন কেন্দ্র',
        ];

        return $map[$type] ?? '';
    }
}

if (!function_exists('doctor_import_join_address')) {
    function doctor_import_join_address(array $items): string
    {
        $clean = [];

        foreach ($items as $item) {
            $item = trim(preg_replace('/\s+/', ' ', (string)$item));

            if ($item !== '') {
                $clean[] = $item;
            }
        }

        return implode(', ', $clean);
    }
}

if (!function_exists('doctor_import_find_hospital_by_name_location')) {
    function doctor_import_find_hospital_by_name_location(string $name, int $district_id = 0, int $thana_id = 0): int
    {
        global $pdo;

        $name = doctor_import_clean($name, 255);

        if ($name === '' || !doctor_import_table_exists('hospitals')) {
            return 0;
        }

        $columns = [];

        foreach (['name', 'name_en', 'name_bn'] as $column) {
            if (doctor_import_column_exists('hospitals', $column)) {
                $columns[] = $column;
            }
        }

        foreach ($columns as $column) {
            $base_sql = "SELECT id FROM hospitals WHERE `{$column}` = :name";
            $base_params = [':name' => $name];

            $tries = [];

            if ($district_id > 0 && $thana_id > 0 && doctor_import_column_exists('hospitals', 'district_id') && doctor_import_column_exists('hospitals', 'thana_id')) {
                $tries[] = [
                    $base_sql . " AND district_id = :district_id AND thana_id = :thana_id",
                    $base_params + [
                        ':district_id' => $district_id,
                        ':thana_id' => $thana_id,
                    ],
                ];
            }

            if ($district_id > 0 && doctor_import_column_exists('hospitals', 'district_id')) {
                $tries[] = [
                    $base_sql . " AND district_id = :district_id",
                    $base_params + [
                        ':district_id' => $district_id,
                    ],
                ];
            }

            $tries[] = [$base_sql, $base_params];

            foreach ($tries as $try) {
                [$sql, $params] = $try;

                if (doctor_import_column_exists('hospitals', 'status')) {
                    $sql .= " AND (status IS NULL OR status = '' OR status = 'active')";
                }

                $sql .= " LIMIT 1";

                try {
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);

                    $id = (int)$stmt->fetchColumn();

                    if ($id > 0) {
                        doctor_import_stat_inc('hospitals_matched');
                        return $id;
                    }
                } catch (Throwable $e) {
                    continue;
                }
            }
        }

        return 0;
    }
}

if (!function_exists('doctor_import_find_hospital_id')) {
    function doctor_import_find_hospital_id(string $value): int
    {
        $value = doctor_import_clean($value, 255);

        if ($value === '') {
            return 0;
        }

        if (ctype_digit($value)) {
            return (int)$value;
        }

        $id = doctor_import_find_hospital_by_name_location($value);

        if ($id <= 0) {
            doctor_import_stat_inc('hospitals_unmatched');
        }

        return $id;
    }
}

if (!function_exists('doctor_import_create_hospital_from_chamber')) {
    function doctor_import_create_hospital_from_chamber(string $hospital_name, array $location, array $row, array $map, int $index): int
    {
        if (!doctor_import_table_exists('hospitals')) {
            return 0;
        }

        $hospital_name = doctor_import_clean($hospital_name, 255);

        if ($hospital_name === '') {
            return 0;
        }

        $division_id = (int)($location['division_id'] ?? 0);
        $district_id = (int)($location['district_id'] ?? 0);
        $thana_id = (int)($location['thana_id'] ?? 0);

        $existing_id = doctor_import_find_hospital_by_name_location($hospital_name, $district_id, $thana_id);

        if ($existing_id > 0) {
            return $existing_id;
        }

        $division_en = $division_id > 0 ? doctor_import_location_name('divisions', $division_id, 'en') : '';
        $division_bn = $division_id > 0 ? doctor_import_location_name('divisions', $division_id, 'bn') : '';
        $district_en = $district_id > 0 ? doctor_import_location_name('districts', $district_id, 'en') : '';
        $district_bn = $district_id > 0 ? doctor_import_location_name('districts', $district_id, 'bn') : '';
        $thana_en = $thana_id > 0 ? doctor_import_location_name('thanas', $thana_id, 'en') : '';
        $thana_bn = $thana_id > 0 ? doctor_import_location_name('thanas', $thana_id, 'bn') : '';

        $type = doctor_import_clean(doctor_import_chamber_value($row, $map, 'type', $index, 'Private Hospital'), 120);
        $type_bn = doctor_import_clean(doctor_import_chamber_value($row, $map, 'type_bn', $index, ''), 120);

        if ($type_bn === '') {
            $type_bn = doctor_import_hospital_type_bn($type);
        }

        $area = doctor_import_clean(doctor_import_chamber_value($row, $map, 'area', $index, ''), 255);
        $area_bn = doctor_import_clean(doctor_import_chamber_value($row, $map, 'area_bn', $index, ''), 255);
        $road_no = doctor_import_clean(doctor_import_chamber_value($row, $map, 'road_no', $index, ''), 255);
        $road_no_bn = doctor_import_clean(doctor_import_chamber_value($row, $map, 'road_no_bn', $index, ''), 255);
        $house_no = doctor_import_clean(doctor_import_chamber_value($row, $map, 'house_no', $index, ''), 255);
        $house_no_bn = doctor_import_clean(doctor_import_chamber_value($row, $map, 'house_no_bn', $index, ''), 255);
        $post_code = doctor_import_clean(doctor_import_chamber_value($row, $map, 'post_code', $index, ''), 50);

        $address = doctor_import_text(doctor_import_chamber_value($row, $map, 'address', $index, ''), 1500);
        $address_bn = doctor_import_text(doctor_import_chamber_value($row, $map, 'address_bn', $index, ''), 1500);

        if ($address === '') {
            $address = doctor_import_join_address([
                $house_no,
                $road_no,
                $area,
                $post_code !== '' && $thana_en !== '' ? $thana_en . '-' . $post_code : ($thana_en ?: $post_code),
                $district_en,
                $division_en,
            ]);
        }

        if ($address_bn === '') {
            $address_bn = doctor_import_join_address([
                $house_no_bn !== '' ? $house_no_bn : $house_no,
                $road_no_bn !== '' ? $road_no_bn : $road_no,
                $area_bn !== '' ? $area_bn : $area,
                $post_code !== '' && $thana_bn !== '' ? $thana_bn . '-' . $post_code : ($thana_bn ?: $post_code),
                $district_bn !== '' ? $district_bn : $district_en,
                $division_bn !== '' ? $division_bn : $division_en,
            ]);
        }

        $slug_candidates = doctor_import_hospital_slug_candidates($hospital_name, $district_id, $thana_id);
        $hospital_slug = doctor_import_first_available_hospital_slug($slug_candidates);

        $hospital_image_source = doctor_import_first_value($row, $map, doctor_import_chamber_keys('image_url', $index), '');
        $hospital_cover_source = doctor_import_first_value($row, $map, doctor_import_chamber_keys('cover_image_url', $index), '');

        $hospital_image = $hospital_image_source !== ''
            ? doctor_import_store_image($hospital_image_source, 'hospitals', $hospital_name, '', 1400, 1400)
            : '';

        $hospital_cover = $hospital_cover_source !== ''
            ? doctor_import_store_image($hospital_cover_source, 'hospitals', $hospital_name, 'cover', 1920, 1080)
            : '';

        $hospital_data = [
            'name' => $hospital_name,
            'name_bn' => doctor_import_clean(doctor_import_chamber_value($row, $map, 'hospital_bn', $index, ''), 255),
            'slug' => $hospital_slug,
            'type' => $type,
            'type_bn' => $type_bn,
            'phone' => doctor_import_phone(doctor_import_chamber_value($row, $map, 'appointment_phone', $index, ''), 100),
            'email' => doctor_import_clean(doctor_import_chamber_value($row, $map, 'email', $index, ''), 180),
            'address' => $address,
            'address_bn' => $address_bn,
            'division_id' => $division_id ?: null,
            'district_id' => $district_id ?: null,
            'thana_id' => $thana_id ?: null,
            'area' => $area,
            'area_bn' => $area_bn,
            'road_no' => $road_no,
            'road_no_bn' => $road_no_bn,
            'house_no' => $house_no,
            'house_no_bn' => $house_no_bn,
            'post_code' => $post_code,
            'map_url' => doctor_import_clean(doctor_import_chamber_value($row, $map, 'map_url', $index, ''), 1000),
            'image' => $hospital_image,
            'cover_image' => $hospital_cover,
            'opening_hours' => doctor_import_clean(doctor_import_chamber_value($row, $map, 'opening_hours', $index, ''), 255),
            'opening_hours_bn' => doctor_import_clean(doctor_import_chamber_value($row, $map, 'opening_hours_bn', $index, ''), 255),
            'visiting_hours' => doctor_import_clean(doctor_import_chamber_value($row, $map, 'visiting_hours', $index, ''), 255),
            'visiting_hours_bn' => doctor_import_clean(doctor_import_chamber_value($row, $map, 'visiting_hours_bn', $index, ''), 255),
            'services' => doctor_import_text(doctor_import_chamber_value($row, $map, 'services', $index, ''), 3000),
            'services_bn' => doctor_import_text(doctor_import_chamber_value($row, $map, 'services_bn', $index, ''), 3000),
            'facilities' => doctor_import_text(doctor_import_chamber_value($row, $map, 'facilities', $index, ''), 3000),
            'facilities_bn' => doctor_import_text(doctor_import_chamber_value($row, $map, 'facilities_bn', $index, ''), 3000),
            'is_verified' => 0,
            'is_featured' => 0,
            'status' => 'active',
            'created_at' => '__NOW__',
            'updated_at' => '__NOW__',
        ];

        $new_hospital_id = doctor_import_insert_dynamic('hospitals', $hospital_data);

        if ($new_hospital_id > 0) {
            doctor_import_stat_inc('hospitals_created');
            $GLOBALS['import_logs'][] = "Hospital created from chamber name #{$new_hospital_id} - {$hospital_name}.";
        } else {
            doctor_import_stat_inc('hospitals_unmatched');
            $GLOBALS['import_logs'][] = "Hospital create failed from chamber name - {$hospital_name}.";
        }

        return $new_hospital_id;
    }
}

/*
|--------------------------------------------------------------------------
| Chamber Helpers
|--------------------------------------------------------------------------
*/

if (!function_exists('doctor_import_max_chamber_count')) {
    function doctor_import_max_chamber_count(array $map, int $minimum = 5): int
    {
        $max = max(1, $minimum);

        foreach (array_keys($map) as $key) {
            if (preg_match('/^chamber_[a-z0-9_]+_(\d+)$/', (string)$key, $matches)) {
                $max = max($max, (int)$matches[1]);
            }
        }

        return $max;
    }
}

if (!function_exists('doctor_import_chamber_keys')) {
    function doctor_import_chamber_keys(string $field, int $index): array
    {
        if ($index <= 1) {
            return ['chamber_' . $field, 'chamber_' . $field . '_1'];
        }

        return ['chamber_' . $field . '_' . $index];
    }
}

if (!function_exists('doctor_import_chamber_value')) {
    function doctor_import_chamber_value(array $row, array $map, string $field, int $index, string $default = ''): string
    {
        return doctor_import_first_value($row, $map, doctor_import_chamber_keys($field, $index), $default);
    }
}

if (!function_exists('doctor_import_get_chamber_location')) {
    function doctor_import_get_chamber_location(array $row, array $map, int $index, bool $auto_create = false): array
    {
        $division = doctor_import_chamber_value($row, $map, 'division', $index, doctor_import_csv_value($row, $map, 'division'));
        $district = doctor_import_chamber_value($row, $map, 'district', $index, doctor_import_csv_value($row, $map, 'district'));
        $thana = doctor_import_chamber_value($row, $map, 'thana', $index, doctor_import_first_value($row, $map, ['thana', 'upazila'], ''));

        return doctor_import_resolve_location($division, $district, $thana, $auto_create);
    }
}

if (!function_exists('doctor_import_find_existing_chamber')) {
    function doctor_import_find_existing_chamber(int $doctor_id, int $hospital_id): array
    {
        global $pdo;

        if (
            $doctor_id <= 0 ||
            $hospital_id <= 0 ||
            !doctor_import_table_exists('chambers') ||
            !doctor_import_column_exists('chambers', 'doctor_id') ||
            !doctor_import_column_exists('chambers', 'hospital_id')
        ) {
            return [];
        }

        try {
            $stmt = $pdo->prepare("
                SELECT *
                FROM chambers
                WHERE doctor_id = :doctor_id
                  AND hospital_id = :hospital_id
                LIMIT 1
            ");
            $stmt->execute([
                ':doctor_id' => $doctor_id,
                ':hospital_id' => $hospital_id,
            ]);

            return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }
}

if (!function_exists('doctor_import_save_chambers')) {
    function doctor_import_save_chambers(int $doctor_id, array $row, array $map, bool $replace_empty, bool $auto_create_missing): void
    {
        if ($doctor_id <= 0 || !doctor_import_table_exists('chambers')) {
            doctor_import_stat_inc('chambers_skipped');
            return;
        }

        /*
         * Strict chamber import rule:
         * Chamber will be created only from chamber_* CSV columns.
         * primary_hospital, hospital, clean_name or any general fallback will not create a chamber.
         */
        $max_chambers = doctor_import_max_chamber_count($map, 5);

        for ($i = 1; $i <= $max_chambers; $i++) {
            if ($i === 1) {
                $hospital_id_value = doctor_import_csv_value($row, $map, 'chamber_hospital_id', '');
                $hospital_name = doctor_import_clean(doctor_import_csv_value($row, $map, 'chamber_hospital', ''), 255);
                $chamber_name = doctor_import_clean(doctor_import_csv_value($row, $map, 'chamber_name', ''), 255);

                $address = doctor_import_text(doctor_import_csv_value($row, $map, 'chamber_address', ''), 1500);
                $address_bn = doctor_import_text(doctor_import_csv_value($row, $map, 'chamber_address_bn', ''), 1500);
                $schedule = doctor_import_clean(doctor_import_csv_value($row, $map, 'chamber_schedule', ''), 255);
                $schedule_bn = doctor_import_clean(doctor_import_csv_value($row, $map, 'chamber_schedule_bn', ''), 255);
                $visiting_hours = doctor_import_clean(doctor_import_csv_value($row, $map, 'chamber_visiting_hours', ''), 255);
                $visiting_hours_bn = doctor_import_clean(doctor_import_csv_value($row, $map, 'chamber_visiting_hours_bn', ''), 255);
                $consultation_fee = doctor_import_csv_value($row, $map, 'chamber_consultation_fee', '0');
                $appointment_phone = doctor_import_phone(doctor_import_csv_value($row, $map, 'chamber_appointment_phone', ''), 100);
                $available_days = doctor_import_text(doctor_import_csv_value($row, $map, 'chamber_days', ''), 1000);
                $available_from = doctor_import_clean(doctor_import_csv_value($row, $map, 'chamber_from', ''), 20);
                $available_to = doctor_import_clean(doctor_import_csv_value($row, $map, 'chamber_to', ''), 20);
                $is_closed = doctor_import_csv_value($row, $map, 'chamber_is_closed', '0');
                $sort_order = doctor_import_csv_value($row, $map, 'chamber_sort_order', '1');
                $status = doctor_import_csv_value($row, $map, 'chamber_status', 'active');

                $division = doctor_import_csv_value($row, $map, 'chamber_division', '');
                $district = doctor_import_csv_value($row, $map, 'chamber_district', '');
                $thana = doctor_import_csv_value($row, $map, 'chamber_thana', '');
            } else {
                $suffix = '_' . $i;

                $hospital_id_value = doctor_import_csv_value($row, $map, 'chamber_hospital_id' . $suffix, '');
                $hospital_name = doctor_import_clean(doctor_import_csv_value($row, $map, 'chamber_hospital' . $suffix, ''), 255);
                $chamber_name = doctor_import_clean(doctor_import_csv_value($row, $map, 'chamber_name' . $suffix, ''), 255);

                $address = doctor_import_text(doctor_import_csv_value($row, $map, 'chamber_address' . $suffix, ''), 1500);
                $address_bn = doctor_import_text(doctor_import_csv_value($row, $map, 'chamber_address_bn' . $suffix, ''), 1500);
                $schedule = doctor_import_clean(doctor_import_csv_value($row, $map, 'chamber_schedule' . $suffix, ''), 255);
                $schedule_bn = doctor_import_clean(doctor_import_csv_value($row, $map, 'chamber_schedule_bn' . $suffix, ''), 255);
                $visiting_hours = doctor_import_clean(doctor_import_csv_value($row, $map, 'chamber_visiting_hours' . $suffix, ''), 255);
                $visiting_hours_bn = doctor_import_clean(doctor_import_csv_value($row, $map, 'chamber_visiting_hours_bn' . $suffix, ''), 255);
                $consultation_fee = doctor_import_csv_value($row, $map, 'chamber_consultation_fee' . $suffix, '0');
                $appointment_phone = doctor_import_phone(doctor_import_csv_value($row, $map, 'chamber_appointment_phone' . $suffix, ''), 100);
                $available_days = doctor_import_text(doctor_import_csv_value($row, $map, 'chamber_days' . $suffix, ''), 1000);
                $available_from = doctor_import_clean(doctor_import_csv_value($row, $map, 'chamber_from' . $suffix, ''), 20);
                $available_to = doctor_import_clean(doctor_import_csv_value($row, $map, 'chamber_to' . $suffix, ''), 20);
                $is_closed = doctor_import_csv_value($row, $map, 'chamber_is_closed' . $suffix, '0');
                $sort_order = doctor_import_csv_value($row, $map, 'chamber_sort_order' . $suffix, (string)$i);
                $status = doctor_import_csv_value($row, $map, 'chamber_status' . $suffix, 'active');

                $division = doctor_import_csv_value($row, $map, 'chamber_division' . $suffix, '');
                $district = doctor_import_csv_value($row, $map, 'chamber_district' . $suffix, '');
                $thana = doctor_import_csv_value($row, $map, 'chamber_thana' . $suffix, '');
            }

            /*
             * chamber_hospital_id is first priority.
             * If ID is empty, chamber_hospital is used.
             * If chamber_hospital is empty, chamber_name is used as the hospital name.
             * No other field is allowed to create a chamber.
             */
            if ($hospital_name === '') {
                $hospital_name = $chamber_name;
            }

            $has_chamber_data = trim($hospital_id_value) !== ''
                || $hospital_name !== ''
                || $chamber_name !== ''
                || $address !== ''
                || $address_bn !== ''
                || $schedule !== ''
                || $schedule_bn !== ''
                || $visiting_hours !== ''
                || $visiting_hours_bn !== ''
                || $appointment_phone !== ''
                || $available_days !== ''
                || $available_from !== ''
                || $available_to !== '';

            if (!$has_chamber_data) {
                continue;
            }

            $location = doctor_import_resolve_location(
                $division,
                $district,
                $thana,
                $auto_create_missing
            );

            $hospital_id = 0;

            if (trim($hospital_id_value) !== '') {
                $hospital_id = doctor_import_int($hospital_id_value);
            }

            if ($hospital_id <= 0 && $hospital_name !== '') {
                $hospital_id = doctor_import_find_hospital_by_name_location(
                    $hospital_name,
                    (int)$location['district_id'],
                    (int)$location['thana_id']
                );
            }

            if ($hospital_id <= 0 && $hospital_name !== '') {
                $hospital_id = doctor_import_create_hospital_from_chamber(
                    $hospital_name,
                    $location,
                    $row,
                    $map,
                    $i
                );
            }

            if ($hospital_id <= 0) {
                doctor_import_stat_inc('chambers_skipped');
                $GLOBALS['import_logs'][] = "Doctor #{$doctor_id}: chamber {$i} skipped because chamber hospital could not be resolved.";
                continue;
            }

            $existing = doctor_import_find_existing_chamber($doctor_id, $hospital_id);
            $existing_id = (int)($existing['id'] ?? 0);

            $chamber_data = [
                'doctor_id' => $doctor_id,
                'hospital_id' => $hospital_id,
                'address' => $address,
                'address_bn' => $address_bn,
                'visiting_hours' => $visiting_hours,
                'visiting_hours_bn' => $visiting_hours_bn,
                'consultation_fee' => doctor_import_float($consultation_fee),
                'appointment_phone' => $appointment_phone,
                'schedule' => $schedule,
                'schedule_bn' => $schedule_bn,
                'available_days' => $available_days,
                'available_from' => $available_from,
                'available_to' => $available_to,
                'is_closed' => doctor_import_bool($is_closed),
                'sort_order' => doctor_import_int($sort_order),
                'status' => doctor_import_status($status, 'active'),
            ];

            if ($existing_id > 0) {
                if (!$replace_empty) {
                    $chamber_data = doctor_import_preserve_existing_empty_values($chamber_data, $existing, [
                        'consultation_fee',
                        'sort_order',
                        'is_closed',
                    ]);
                }

                $chamber_data['updated_at'] = '__NOW__';

                if (doctor_import_update_dynamic('chambers', $existing_id, $chamber_data)) {
                    doctor_import_stat_inc('chambers_updated');
                    $GLOBALS['import_logs'][] = "Doctor #{$doctor_id}: chamber updated #{$existing_id} with hospital #{$hospital_id}.";
                } else {
                    doctor_import_stat_inc('chambers_skipped');
                    $GLOBALS['import_logs'][] = "Doctor #{$doctor_id}: chamber {$i} update failed.";
                }
            } else {
                $chamber_data['created_at'] = '__NOW__';

                $new_chamber_id = doctor_import_insert_dynamic('chambers', $chamber_data);

                if ($new_chamber_id > 0) {
                    doctor_import_stat_inc('chambers_created');
                    $GLOBALS['import_logs'][] = "Doctor #{$doctor_id}: chamber created #{$new_chamber_id} with hospital #{$hospital_id}.";

                    if (doctor_import_column_exists('doctors', 'doctor_location_auto_chamber_id')) {
                        global $pdo;

                        try {
                            $stmt = $pdo->prepare("
                                UPDATE doctors
                                SET doctor_location_auto_chamber_id = :chamber_id
                                WHERE id = :doctor_id
                                  AND (doctor_location_auto_chamber_id IS NULL OR doctor_location_auto_chamber_id = 0)
                            ");

                            $stmt->execute([
                                ':chamber_id' => $new_chamber_id,
                                ':doctor_id' => $doctor_id,
                            ]);
                        } catch (Throwable $e) {
                            // Ignore auto chamber update error.
                        }
                    }
                } else {
                    doctor_import_stat_inc('chambers_skipped');
                    $GLOBALS['import_logs'][] = "Doctor #{$doctor_id}: chamber {$i} insert failed.";
                }
            }
        }
    }
}

/*
|--------------------------------------------------------------------------
| Image Helpers
|--------------------------------------------------------------------------
*/

if (!function_exists('doctor_import_is_url')) {
    function doctor_import_is_url(string $value): bool
    {
        $value = trim($value);

        return $value !== '' && filter_var($value, FILTER_VALIDATE_URL) !== false;
    }
}

if (!function_exists('doctor_import_upload_base_path')) {
    function doctor_import_upload_base_path(): string
    {
        if (defined('UPLOAD_PATH')) {
            return rtrim((string)UPLOAD_PATH, '/');
        }

        if (defined('PUBLIC_HTML_PATH')) {
            return rtrim((string)PUBLIC_HTML_PATH, '/') . '/assets/uploads';
        }

        if (defined('PUBLIC_PATH')) {
            return rtrim((string)PUBLIC_PATH, '/') . '/assets/uploads';
        }

        return rtrim(dirname(__DIR__), '/') . '/assets/uploads';
    }
}

if (!function_exists('doctor_import_upload_base_url')) {
    function doctor_import_upload_base_url(): string
    {
        if (defined('UPLOAD_URL')) {
            return rtrim((string)UPLOAD_URL, '/');
        }

        return '/assets/uploads';
    }
}


if (!function_exists('doctor_import_saved_image_path')) {
    function doctor_import_saved_image_path(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        $value = str_replace('\\', '/', $value);

        if (preg_match('/^https?:\/\//i', $value)) {
            $path = parse_url($value, PHP_URL_PATH);

            if (is_string($path) && $path !== '') {
                $value = $path;
            } else {
                return '';
            }
        }

        if (
            preg_match('#^/?assets/uploads/#i', $value) ||
            preg_match('#^/?uploads/#i', $value) ||
            preg_match('#^/?storage/#i', $value)
        ) {
            if ($value[0] !== '/') {
                $value = '/' . $value;
            }

            return doctor_import_clean($value, 700);
        }

        return '';
    }
}

if (!function_exists('doctor_import_local_image_only')) {
    function doctor_import_local_image_only(string $value): string
    {
        return doctor_import_saved_image_path($value);
    }
}

if (!function_exists('doctor_import_remote_image_fetch')) {
    function doctor_import_remote_image_fetch(string $url, int $max_bytes = 5242880): array
    {
        $url = trim($url);

        if (!doctor_import_is_url($url)) {
            return ['ok' => false, 'body' => '', 'error' => 'Invalid image URL'];
        }

        $body = '';
        $error = '';

        if (function_exists('curl_init')) {
            $ch = curl_init($url);

            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 5,
                CURLOPT_CONNECTTIMEOUT => 12,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => 0,
                CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; DoctorImporter/1.0)',
            ]);

            $body = curl_exec($ch);
            $error = curl_error($ch);
            $http_code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($body === false || $http_code < 200 || $http_code >= 300) {
                return ['ok' => false, 'body' => '', 'error' => $error ?: 'Image download failed'];
            }
        } elseif (ini_get('allow_url_fopen')) {
            $context = stream_context_create([
                'http' => [
                    'timeout' => 30,
                    'follow_location' => 1,
                    'max_redirects' => 5,
                    'user_agent' => 'Mozilla/5.0 (compatible; DoctorImporter/1.0)',
                ],
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                ],
            ]);

            $body = @file_get_contents($url, false, $context);

            if ($body === false) {
                return ['ok' => false, 'body' => '', 'error' => 'Image download failed'];
            }
        } else {
            return ['ok' => false, 'body' => '', 'error' => 'cURL or allow_url_fopen is required'];
        }

        if (!is_string($body) || $body === '') {
            return ['ok' => false, 'body' => '', 'error' => 'Downloaded image is empty'];
        }

        if (strlen($body) > $max_bytes) {
            return ['ok' => false, 'body' => '', 'error' => 'Image is larger than 5MB'];
        }

        return ['ok' => true, 'body' => $body, 'error' => ''];
    }
}

if (!function_exists('doctor_import_image_mime_from_file')) {
    function doctor_import_image_mime_from_file(string $file): string
    {
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);

            if ($finfo) {
                $mime = finfo_file($finfo, $file);
                finfo_close($finfo);

                if (is_string($mime) && $mime !== '') {
                    return strtolower($mime);
                }
            }
        }

        $info = @getimagesize($file);

        return !empty($info['mime']) ? strtolower((string)$info['mime']) : '';
    }
}

if (!function_exists('doctor_import_gd_source')) {
    function doctor_import_gd_source(string $file, string $mime)
    {
        switch ($mime) {
            case 'image/jpeg':
            case 'image/jpg':
                return function_exists('imagecreatefromjpeg') ? @imagecreatefromjpeg($file) : false;

            case 'image/png':
                return function_exists('imagecreatefrompng') ? @imagecreatefrompng($file) : false;

            case 'image/webp':
                return function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($file) : false;

            case 'image/gif':
                return function_exists('imagecreatefromgif') ? @imagecreatefromgif($file) : false;

            case 'image/bmp':
            case 'image/x-ms-bmp':
                return function_exists('imagecreatefrombmp') ? @imagecreatefrombmp($file) : false;
        }

        if (function_exists('imagecreatefromstring')) {
            $raw = @file_get_contents($file);

            if ($raw !== false) {
                return @imagecreatefromstring($raw);
            }
        }

        return false;
    }
}

if (!function_exists('doctor_import_save_image_as_webp')) {
    function doctor_import_save_image_as_webp(string $source_file, string $target_file, string $mime, int $max_width = 1200, int $max_height = 1200, int $quality = 82): bool
    {
        if (!function_exists('imagewebp')) {
            return false;
        }

        $source = doctor_import_gd_source($source_file, $mime);

        if (!$source) {
            return false;
        }

        $width = imagesx($source);
        $height = imagesy($source);

        if ($width <= 0 || $height <= 0) {
            imagedestroy($source);
            return false;
        }

        $ratio = min($max_width / $width, $max_height / $height, 1);
        $new_width = max(1, (int)round($width * $ratio));
        $new_height = max(1, (int)round($height * $ratio));

        $canvas = imagecreatetruecolor($new_width, $new_height);

        if (!$canvas) {
            imagedestroy($source);
            return false;
        }

        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);

        $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
        imagefilledrectangle($canvas, 0, 0, $new_width, $new_height, $transparent);
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $new_width, $new_height, $width, $height);

        $saved = imagewebp($canvas, $target_file, max(1, min(100, $quality)));

        imagedestroy($source);
        imagedestroy($canvas);

        return (bool)$saved;
    }
}

if (!function_exists('doctor_import_store_image')) {
    function doctor_import_store_image(string $value, string $folder, string $name, string $suffix = '', int $max_width = 1200, int $max_height = 1200): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        if (!doctor_import_is_url($value)) {
            return doctor_import_local_image_only($value);
        }

        $download = doctor_import_remote_image_fetch($value);

        if (!$download['ok']) {
            $GLOBALS['import_logs'][] = 'Image failed: ' . $value . ' - ' . $download['error'];
            doctor_import_stat_inc('images_failed');
            return '';
        }

        $tmp = tempnam(sys_get_temp_dir(), 'doctor-import-img-');

        if (!$tmp) {
            $GLOBALS['import_logs'][] = 'Image failed: ' . $value . ' - temp file could not be created';
            doctor_import_stat_inc('images_failed');
            return '';
        }

        file_put_contents($tmp, $download['body']);

        $mime = doctor_import_image_mime_from_file($tmp);
        $allowed = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp', 'image/gif', 'image/bmp', 'image/x-ms-bmp'];

        if (!in_array($mime, $allowed, true)) {
            @unlink($tmp);
            $GLOBALS['import_logs'][] = 'Image failed: ' . $value . ' - invalid mime: ' . $mime;
            doctor_import_stat_inc('images_failed');
            return '';
        }

        $upload_path = doctor_import_upload_base_path();
        $upload_url = doctor_import_upload_base_url();

        $folder = trim($folder, '/');
        $dir = rtrim($upload_path, '/') . '/' . $folder;

        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        if (!is_dir($dir) || !is_writable($dir)) {
            @unlink($tmp);
            $GLOBALS['import_logs'][] = 'Image failed: upload folder is not writable - ' . $dir;
            doctor_import_stat_inc('images_failed');
            return '';
        }

        $base = doctor_import_slug($name);

        if ($suffix !== '') {
            $base .= '-' . doctor_import_slug($suffix);
        }

        $date = date('ymdHis');
        $filename = $base . '-' . $date . '.webp';
        $target = $dir . '/' . $filename;
        $counter = 2;

        while (file_exists($target)) {
            $filename = $base . '-' . $date . '-' . $counter . '.webp';
            $target = $dir . '/' . $filename;
            $counter++;
        }

        $saved = doctor_import_save_image_as_webp($tmp, $target, $mime, $max_width, $max_height, 82);

        @unlink($tmp);

        if (!$saved || !file_exists($target)) {
            $GLOBALS['import_logs'][] = 'Image failed: ' . $value . ' - WebP conversion failed. Original format was not saved.';
            doctor_import_stat_inc('images_failed');
            return '';
        }

        @chmod($target, 0644);
        doctor_import_stat_inc('images_uploaded');

        return doctor_import_saved_image_path(rtrim($upload_url, '/') . '/' . $folder . '/' . $filename);
    }
}

/*
|--------------------------------------------------------------------------
| Duplicate Doctor Check
|--------------------------------------------------------------------------
*/

if (!function_exists('doctor_import_find_existing')) {
    function doctor_import_find_existing(string $bmdc_number, string $email, string $slug): array
    {
        global $pdo;

        if (!doctor_import_table_exists('doctors')) {
            return [];
        }

        $bmdc_number = doctor_import_clean($bmdc_number, 80);
        $email = doctor_import_clean($email, 150);
        $slug = doctor_import_clean($slug, 255);

        if ($bmdc_number !== '' && doctor_import_column_exists('doctors', 'bmdc_number')) {
            $stmt = $pdo->prepare("SELECT *, 'bmdc_number' AS duplicate_by FROM doctors WHERE bmdc_number = :bmdc_number LIMIT 1");
            $stmt->execute([':bmdc_number' => $bmdc_number]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row) {
                doctor_import_stat_inc('bmdc_duplicates');
                return $row;
            }
        }

        if ($email !== '' && doctor_import_column_exists('doctors', 'email')) {
            $stmt = $pdo->prepare("SELECT *, 'email' AS duplicate_by FROM doctors WHERE email = :email LIMIT 1");
            $stmt->execute([':email' => $email]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row) {
                doctor_import_stat_inc('email_duplicates');
                return $row;
            }
        }

        if ($slug !== '' && doctor_import_column_exists('doctors', 'slug')) {
            $stmt = $pdo->prepare("SELECT *, 'slug' AS duplicate_by FROM doctors WHERE slug = :slug LIMIT 1");
            $stmt->execute([':slug' => $slug]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row) {
                doctor_import_stat_inc('slug_duplicates');
                return $row;
            }
        }

        return [];
    }
}

/*
|--------------------------------------------------------------------------
| SEO Helper
|--------------------------------------------------------------------------
*/

if (!function_exists('doctor_import_gender_bn')) {
    function doctor_import_gender_bn(string $gender): string
    {
        $map = [
            'Male' => 'পুরুষ',
            'Female' => 'মহিলা',
            'Other' => 'অন্যান্য',
        ];

        return $map[$gender] ?? '';
    }
}

if (!function_exists('doctor_import_language_bn_map')) {
    function doctor_import_language_bn_map(): array
    {
        return [
            'bangla' => 'বাংলা',
            'bengali' => 'বাংলা',
            'বাংলা' => 'বাংলা',
            'english' => 'ইংরেজি',
            'ইংরেজি' => 'ইংরেজি',
            'hindi' => 'হিন্দি',
            'হিন্দি' => 'হিন্দি',
            'arabic' => 'আরবি',
            'আরবি' => 'আরবি',
            'urdu' => 'উর্দু',
            'উর্দু' => 'উর্দু',
            'chinese' => 'চীনা',
            'চীনা' => 'চীনা',
            'french' => 'ফরাসি',
            'ফরাসি' => 'ফরাসি',
        ];
    }
}

if (!function_exists('doctor_import_languages_bn')) {
    function doctor_import_languages_bn(string $languages): string
    {
        $languages = doctor_import_clean($languages, 255);

        if ($languages === '') {
            return '';
        }

        $map = doctor_import_language_bn_map();
        $items = preg_split('/[,|;]+/u', $languages) ?: [];
        $translated = [];

        foreach ($items as $item) {
            $item = doctor_import_clean($item, 80);

            if ($item === '') {
                continue;
            }

            $key = function_exists('mb_strtolower') ? mb_strtolower($item, 'UTF-8') : strtolower($item);
            $translated[] = $map[$key] ?? $item;
        }

        return doctor_import_clean(implode(', ', array_values(array_unique(array_filter($translated)))), 255);
    }
}

if (!function_exists('doctor_import_auto_seo')) {
    function doctor_import_auto_seo(string $name, string $name_bn, int $specialty_id, int $district_id): array
    {
        $name = doctor_import_clean($name, 255);
        $name_bn = doctor_import_clean($name_bn, 255);

        $specialty = doctor_import_db_name('specialties', $specialty_id);
        $district = doctor_import_db_name('districts', $district_id);

        $specialty_bn = doctor_import_location_name('specialties', $specialty_id, 'bn');
        if ($specialty_bn === '') {
            $specialty_bn = $specialty;
        }

        $district_bn = doctor_import_location_name('districts', $district_id, 'bn');
        if ($district_bn === '') {
            $district_bn = $district;
        }

        if ($name_bn === '') {
            $name_bn = $name;
        }

        /*
         * Strong SEO rule:
         * SEO Title always follows Doctor Name - Specialty - District.
         */
        $seo_title = doctor_import_join_unique([$name, $specialty, $district], ' - ');
        $seo_title_bn = doctor_import_join_unique([$name_bn, $specialty_bn, $district_bn], ' - ');

        $seo_description = '';
        if ($name !== '' || $specialty !== '' || $district !== '') {
            $seo_description = doctor_import_text(
                doctor_import_join_unique([$name, $specialty, $district], ', ') .
                '. View doctor profile, specialty, district, hospital availability, appointment information, consultation fee and schedule.',
                1500
            );
        }

        $seo_description_bn = '';
        if ($name_bn !== '' || $specialty_bn !== '' || $district_bn !== '') {
            $seo_description_bn = doctor_import_text(
                doctor_import_join_unique([$name_bn, $specialty_bn, $district_bn], ', ') .
                ' এর ডাক্তার প্রোফাইল, বিশেষজ্ঞতা, জেলা, হাসপাতাল, অ্যাপয়েন্টমেন্ট তথ্য, কনসালটেশন ফি ও সময়সূচি দেখুন।',
                1500
            );
        }

        $keywords = doctor_import_join_unique([
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

        $keywords_bn = doctor_import_join_unique([
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
            'seo_title' => doctor_import_clean($seo_title, 255),
            'seo_description' => doctor_import_text($seo_description, 1500),
            'meta_keywords' => doctor_import_clean($keywords, 255),
            'seo_title_bn' => doctor_import_clean($seo_title_bn, 255),
            'seo_description_bn' => doctor_import_text($seo_description_bn, 1500),
            'meta_keywords_bn' => doctor_import_clean($keywords_bn, 255),
        ];
    }
}

if (!function_exists('doctor_import_clean_seo_mode')) {
    function doctor_import_clean_seo_mode($mode): string
    {
        return trim((string)$mode) === 'manual' ? 'manual' : 'auto';
    }
}

if (!function_exists('doctor_import_csv_has_key')) {
    function doctor_import_csv_has_key(array $map, string $key): bool
    {
        return array_key_exists($key, $map);
    }
}

if (!function_exists('doctor_import_seo_mode_and_value')) {
    function doctor_import_seo_mode_and_value(
        array $row,
        array $map,
        string $field,
        string $auto_value,
        array $existing = [],
        bool $replace_empty = false
    ): array {
        $posted_value = doctor_import_text(doctor_import_csv_value($row, $map, $field), 1500);
        $posted_mode = doctor_import_clean_seo_mode(doctor_import_csv_value($row, $map, $field . '_mode', ''));
        $has_value_column = doctor_import_csv_has_key($map, $field);
        $has_mode_column = doctor_import_csv_has_key($map, $field . '_mode');

        $auto_value = doctor_import_text($auto_value, 1500);
        $existing_value = doctor_import_text($existing[$field] ?? '', 1500);
        $existing_mode = doctor_import_clean_seo_mode($existing[$field . '_mode'] ?? 'auto');

        /* Explicit auto mode always uses fresh auto value. */
        if ($has_mode_column && $posted_mode === 'auto') {
            return ['mode' => 'auto', 'value' => $auto_value];
        }

        /* Explicit manual mode only stays manual when the CSV value is not empty. */
        if ($has_mode_column && $posted_mode === 'manual') {
            if ($posted_value !== '') {
                return ['mode' => 'manual', 'value' => $posted_value];
            }

            return ['mode' => 'auto', 'value' => $auto_value];
        }

        /* A non-empty CSV SEO value means only this field becomes manual. */
        if ($has_value_column && $posted_value !== '') {
            return ['mode' => 'manual', 'value' => $posted_value];
        }

        /* Keep an existing manual field when updating and empty CSV values should not replace existing values. */
        if (!$replace_empty && $existing_value !== '' && $existing_mode === 'manual') {
            return ['mode' => 'manual', 'value' => $existing_value];
        }

        /* Empty CSV field or missing field falls back to fresh auto value. */
        return ['mode' => 'auto', 'value' => $auto_value];
    }
}

/*
|--------------------------------------------------------------------------
| Sample CSV
|--------------------------------------------------------------------------
*/

if (!function_exists('doctor_import_download_sample')) {
    function doctor_import_download_sample(): void
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="sample-doctor-import.csv"');

        $out = fopen('php://output', 'w');
        fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));

        $headers = [
            'name',
            'name_bn',
            'degree',
            'degree_bn',
            'designation',
            'designation_bn',
            'bmdc_number',
            'gender',
            'languages',
            'specialty',
            'specialty_id',
            'hospital',
            'hospital_id',
            'primary_hospital',
            'primary_hospital_bn',
            'experience_years',
            'consultation_fee',
            'phone',
            'whatsapp',
            'email',
            'division',
            'district',
            'thana',
            'image',
            'image_url',
            'bio',
            'bio_bn',
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


            'chamber_hospital',
            'chamber_hospital_bn',
            'chamber_name',
          
            'chamber_address',
            'chamber_address_bn',
            'chamber_schedule',
            'chamber_schedule_bn',

            'chamber_consultation_fee',
            'chamber_appointment_phone',

            'chamber_division',
            'chamber_district',
            'chamber_thana',


            'chamber_name_2',
            'chamber_address_2',
            'chamber_schedule_2',
            'chamber_consultation_fee_2',
            'chamber_appointment_phone_2',
            'chamber_district_2',
            'chamber_thana_2',


        ];

        fputcsv($out, $headers);

        $sample = array_fill_keys($headers, '');

        $sample['name'] = 'Dr. Md. Example Hasan';
        $sample['name_bn'] = 'ডা. মোঃ এক্সাম্পল হাসান';
        $sample['degree'] = 'MBBS, FCPS';
        $sample['designation'] = 'Consultant';
        $sample['bmdc_number'] = 'A-123456';
        $sample['gender'] = 'Male';
        $sample['languages'] = 'Bangla, English, Hindi';
        $sample['specialty'] = 'Cardiology';
        $sample['phone'] = '01711111111';
        $sample['email'] = 'doctor@example.com';
        $sample['division'] = 'Dhaka';
        $sample['district'] = 'Dhaka';
        $sample['thana'] = 'Dhanmondi';
        $sample['image_url'] = 'https://example.com/doctor.jpg';
        $sample['bio'] = 'Experienced cardiology specialist.';


        $sample['chamber_name'] = 'Square Hospital';
        $sample['chamber_address'] = 'Panthapath, Dhaka';
        $sample['chamber_schedule'] = 'Sat, Sun, Tue - 6:00 PM to 9:00 PM';
        $sample['chamber_consultation_fee'] = '1000';
        $sample['chamber_appointment_phone'] = '01711111111';
        $sample['chamber_division'] = 'Dhaka';
        $sample['chamber_district'] = 'Dhaka';
        $sample['chamber_thana'] = 'Dhanmondi';

        fputcsv($out, array_map(static function ($header) use ($sample) {
            return $sample[$header] ?? '';
        }, $headers));

        fclose($out);
        exit;
    }
}

if (isset($_GET['download_sample']) && $_GET['download_sample'] === '1') {
    doctor_import_download_sample();
}

/*
|--------------------------------------------------------------------------
| Import Process
|--------------------------------------------------------------------------
*/

if (!empty($_SESSION['doctor_import_result']) && is_array($_SESSION['doctor_import_result'])) {
    $result = $_SESSION['doctor_import_result'];

    $import_message = $result['message'] ?? '';
    $import_message_type = $result['message_type'] ?? '';
    $import_logs = $result['logs'] ?? [];
    $import_stats = $result['stats'] ?? $import_stats;

    unset($_SESSION['doctor_import_result']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $replace_empty = isset($_POST['replace_empty']);
    $auto_create_missing = isset($_POST['auto_create_missing']);

    $logs = [];
    $stats = [
        'processed' => 0,
        'created' => 0,
        'updated' => 0,
        'skipped' => 0,

        'duplicates_found' => 0,
        'bmdc_duplicates' => 0,
        'email_duplicates' => 0,
        'slug_duplicates' => 0,

        'specialties_matched' => 0,
        'specialties_created' => 0,
        'specialties_unmatched' => 0,

        'divisions_matched' => 0,
        'divisions_created' => 0,
        'divisions_unmatched' => 0,

        'districts_matched' => 0,
        'districts_created' => 0,
        'districts_unmatched' => 0,

        'thanas_matched' => 0,
        'thanas_created' => 0,
        'thanas_unmatched' => 0,

        'hospitals_matched' => 0,
        'hospitals_created' => 0,
        'hospitals_unmatched' => 0,

        'images_uploaded' => 0,
        'images_failed' => 0,

        'chambers_created' => 0,
        'chambers_updated' => 0,
        'chambers_skipped' => 0,
    ];

    $GLOBALS['import_stats'] = &$stats;
    $GLOBALS['import_logs'] = &$logs;

    $message = '';
    $message_type = '';

    if (!doctor_import_table_exists('doctors')) {
        $message = 'Doctors table was not found.';
        $message_type = 'error';
    } elseif (empty($_FILES['csv_file']['tmp_name']) || (int)($_FILES['csv_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $message = 'Please upload a valid CSV file.';
        $message_type = 'error';
    } else {
        $handle = fopen($_FILES['csv_file']['tmp_name'], 'r');

        if (!$handle) {
            $message = 'CSV file could not be opened.';
            $message_type = 'error';
        } else {
            $header = fgetcsv($handle);

            if (!$header || !is_array($header)) {
                $message = 'CSV header row is missing.';
                $message_type = 'error';
            } else {
                $map = doctor_import_header_map($header);

                if (!isset($map['name'])) {
                    $message = 'Required CSV column missing: name.';
                    $message_type = 'error';
                } else {
                    $line = 1;

                    while (($row = fgetcsv($handle)) !== false) {
                        $line++;

                        if (!array_filter($row, static function ($value) {
                            return trim((string)$value) !== '';
                        })) {
                            continue;
                        }

                        $stats['processed']++;

                        try {
                            $name = doctor_import_clean(doctor_import_csv_value($row, $map, 'name'), 255);

                            if ($name === '') {
                                $stats['skipped']++;
                                $logs[] = "Line {$line}: skipped because name is empty.";
                                continue;
                            }

                            $name_bn = doctor_import_clean(doctor_import_csv_value($row, $map, 'name_bn'), 255);
                            $email = doctor_import_clean(doctor_import_csv_value($row, $map, 'email'), 150);
                            $bmdc_number = doctor_import_clean(doctor_import_csv_value($row, $map, 'bmdc_number'), 80);
                            $gender = doctor_import_gender(doctor_import_csv_value($row, $map, 'gender'));

                            $specialty_value = doctor_import_first_value($row, $map, ['specialty_id', 'specialty', 'department', 'department_name'], '');
                            $specialty_id = doctor_import_find_or_create_specialty_id($specialty_value, $auto_create_missing);

                            $doctor_location = doctor_import_resolve_location(
                                doctor_import_first_value($row, $map, ['division'], ''),
                                doctor_import_first_value($row, $map, ['district'], ''),
                                doctor_import_first_value($row, $map, ['thana', 'upazila'], ''),
                                $auto_create_missing
                            );

                            $first_chamber_location = doctor_import_get_chamber_location($row, $map, 1, $auto_create_missing);

                            $csv_slug = doctor_import_clean(doctor_import_csv_value($row, $map, 'slug'), 255);

                            if ($csv_slug !== '') {
                                $base_slug = doctor_import_slug($csv_slug);
                            } else {
                                $base_slug = doctor_import_generate_doctor_slug(
                                    $name,
                                    $specialty_id,
                                    (int)$doctor_location['district_id'],
                                    (int)$first_chamber_location['district_id'],
                                    0
                                );
                            }

                            $existing = doctor_import_find_existing($bmdc_number, $email, $base_slug);
                            $existing_id = (int)($existing['id'] ?? 0);
                            $duplicate_by = (string)($existing['duplicate_by'] ?? '');

                            if ($existing_id > 0) {
                                $stats['duplicates_found']++;
                            }

                            $hospital_value = doctor_import_first_value($row, $map, ['hospital_id', 'hospital', 'primary_hospital'], '');
                            $hospital_id = doctor_import_find_hospital_id($hospital_value);

                            $primary_hospital = doctor_import_clean(doctor_import_csv_value($row, $map, 'primary_hospital'), 255);

                            if ($primary_hospital === '') {
                                $primary_hospital = doctor_import_clean($hospital_value, 255);
                            }

                            if ($primary_hospital === '' && $hospital_id > 0) {
                                $primary_hospital = doctor_import_db_name('hospitals', $hospital_id);
                            }

                            if ($existing_id > 0 && !empty($existing['slug'])) {
                                $slug = (string)$existing['slug'];
                            } elseif ($csv_slug !== '') {
                                $slug = doctor_import_unique_slug_for_table('doctors', $csv_slug, $existing_id);
                            } else {
                                $slug = doctor_import_generate_doctor_slug(
                                    $name,
                                    $specialty_id,
                                    (int)$doctor_location['district_id'],
                                    (int)$first_chamber_location['district_id'],
                                    $existing_id
                                );
                            }

                            $image_source = doctor_import_first_value($row, $map, ['image_url', 'image'], '');
                            $og_image_source = doctor_import_first_value($row, $map, ['og_image_url', 'og_image'], '');

                            $new_image = doctor_import_store_image($image_source, 'doctors', $name, '', 1200, 1200);
                            $new_og_image = doctor_import_store_image($og_image_source, 'doctors', $name, 'og', 1200, 630);

                            $image = $new_image !== '' ? $new_image : doctor_import_clean($existing['image'] ?? '', 500);
                            $og_image = $new_og_image !== '' ? $new_og_image : doctor_import_clean($existing['og_image'] ?? '', 500);

                            $seo_district_id = (int)$doctor_location['district_id'] > 0
                                ? (int)$doctor_location['district_id']
                                : (int)$first_chamber_location['district_id'];

                            $auto_seo = doctor_import_auto_seo($name, $name_bn, $specialty_id, $seo_district_id);

                            $seo_title_pair = doctor_import_seo_mode_and_value($row, $map, 'seo_title', $auto_seo['seo_title'] ?? '', $existing, $replace_empty);
                            $seo_title_bn_pair = doctor_import_seo_mode_and_value($row, $map, 'seo_title_bn', $auto_seo['seo_title_bn'] ?? '', $existing, $replace_empty);
                            $seo_description_pair = doctor_import_seo_mode_and_value($row, $map, 'seo_description', $auto_seo['seo_description'] ?? '', $existing, $replace_empty);
                            $seo_description_bn_pair = doctor_import_seo_mode_and_value($row, $map, 'seo_description_bn', $auto_seo['seo_description_bn'] ?? '', $existing, $replace_empty);
                            $meta_keywords_pair = doctor_import_seo_mode_and_value($row, $map, 'meta_keywords', $auto_seo['meta_keywords'] ?? '', $existing, $replace_empty);
                            $meta_keywords_bn_pair = doctor_import_seo_mode_and_value($row, $map, 'meta_keywords_bn', $auto_seo['meta_keywords_bn'] ?? '', $existing, $replace_empty);

                            $featured_days = doctor_import_int(doctor_import_csv_value($row, $map, 'featured_days'));
                            $is_featured = doctor_import_bool(doctor_import_csv_value($row, $map, 'is_featured'));
                            $featured_started_at = null;
                            $featured_until = null;

                            if ($is_featured && $featured_days > 0) {
                                $featured_started_at = date('Y-m-d');
                                $featured_until = date('Y-m-d', strtotime('+' . $featured_days . ' days'));
                            }

                            $row_data = [
                                'name' => $name,
                                'name_bn' => $name_bn,
                                'slug' => $slug,

                                'degree' => doctor_import_clean(doctor_import_csv_value($row, $map, 'degree'), 255),
                                'degree_bn' => doctor_import_clean(doctor_import_csv_value($row, $map, 'degree_bn'), 255),

                                'designation' => doctor_import_clean(doctor_import_csv_value($row, $map, 'designation'), 255),
                                'designation_bn' => doctor_import_clean(doctor_import_csv_value($row, $map, 'designation_bn'), 255),

                                'bmdc_number' => $bmdc_number,
                                'gender' => $gender,
                                'gender_bn' => doctor_import_gender_bn($gender),

                                'languages' => doctor_import_clean(doctor_import_csv_value($row, $map, 'languages'), 255),
                                'languages_bn' => doctor_import_languages_bn(doctor_import_csv_value($row, $map, 'languages')),

                                'specialty_id' => $specialty_id,
                                'hospital_id' => $hospital_id,

                                'primary_hospital' => $primary_hospital,
                                'primary_hospital_bn' => doctor_import_clean(doctor_import_csv_value($row, $map, 'primary_hospital_bn'), 255),

                                'experience_years' => doctor_import_int_or_null(doctor_import_csv_value($row, $map, 'experience_years')),

                                'consultation_fee' => doctor_import_float(doctor_import_csv_value($row, $map, 'consultation_fee')),
                                'follow_up_fee' => doctor_import_float(doctor_import_csv_value($row, $map, 'follow_up_fee')),
                                'video_consultation_fee' => doctor_import_float(doctor_import_csv_value($row, $map, 'video_consultation_fee')),

                                'phone' => doctor_import_phone(doctor_import_csv_value($row, $map, 'phone'), 100),
                                'whatsapp' => doctor_import_phone(doctor_import_csv_value($row, $map, 'whatsapp'), 100),
                                'email' => $email,

                                'doctor_division_id' => (int)$doctor_location['division_id'],
                                'doctor_district_id' => (int)$doctor_location['district_id'],
                                'doctor_thana_id' => (int)$doctor_location['thana_id'],
                                'doctor_location_source' => ((int)$doctor_location['division_id'] > 0 || (int)$doctor_location['district_id'] > 0 || (int)$doctor_location['thana_id'] > 0) ? 'manual' : 'auto',
                                'doctor_location_auto_chamber_id' => (int)($existing['doctor_location_auto_chamber_id'] ?? 0),

                                'image' => $image,
                                'og_image' => $og_image,

                                'bio' => doctor_import_text(doctor_import_csv_value($row, $map, 'bio'), 8000),
                                'bio_bn' => doctor_import_text(doctor_import_csv_value($row, $map, 'bio_bn'), 8000),

                                'education' => doctor_import_text(doctor_import_csv_value($row, $map, 'education'), 8000),
                                'education_bn' => doctor_import_text(doctor_import_csv_value($row, $map, 'education_bn'), 8000),

                                'training' => doctor_import_text(doctor_import_csv_value($row, $map, 'training'), 8000),
                                'training_bn' => doctor_import_text(doctor_import_csv_value($row, $map, 'training_bn'), 8000),

                                'fellowship' => doctor_import_text(doctor_import_csv_value($row, $map, 'fellowship'), 8000),
                                'fellowship_bn' => doctor_import_text(doctor_import_csv_value($row, $map, 'fellowship_bn'), 8000),

                                'expertise' => doctor_import_text(doctor_import_csv_value($row, $map, 'expertise'), 8000),
                                'expertise_bn' => doctor_import_text(doctor_import_csv_value($row, $map, 'expertise_bn'), 8000),

                                'appointment_note' => doctor_import_text(doctor_import_csv_value($row, $map, 'appointment_note'), 8000),
                                'appointment_note_bn' => doctor_import_text(doctor_import_csv_value($row, $map, 'appointment_note_bn'), 8000),

                                'rating' => doctor_import_rating(doctor_import_csv_value($row, $map, 'rating')),
                                'reviews_count' => doctor_import_int(doctor_import_csv_value($row, $map, 'reviews_count')),

                                'is_verified' => doctor_import_bool(doctor_import_csv_value($row, $map, 'is_verified')),
                                'is_featured' => $is_featured,
                                'featured_days' => $featured_days,
                                'featured_started_at' => $featured_started_at,
                                'featured_until' => $featured_until,
                                'featured_position' => doctor_import_int(doctor_import_csv_value($row, $map, 'featured_position')),
                                'featured_on_hold' => 0,
                                'featured_hold_remaining_days' => 0,
                                'featured_hold_started_at' => null,

                                'emergency_available' => doctor_import_bool(doctor_import_csv_value($row, $map, 'emergency_available')),
                                'online_consultation' => doctor_import_bool(doctor_import_csv_value($row, $map, 'online_consultation')),
                                'home_visit' => doctor_import_bool(doctor_import_csv_value($row, $map, 'home_visit')),

                                'status' => doctor_import_status(doctor_import_csv_value($row, $map, 'status'), 'active'),

                                'seo_title' => doctor_import_clean($seo_title_pair['value'] ?? '', 255),
                                'seo_title_bn' => doctor_import_clean($seo_title_bn_pair['value'] ?? '', 255),
                                'seo_description' => doctor_import_text($seo_description_pair['value'] ?? '', 1500),
                                'seo_description_bn' => doctor_import_text($seo_description_bn_pair['value'] ?? '', 1500),
                                'meta_keywords' => doctor_import_clean($meta_keywords_pair['value'] ?? '', 255),
                                'meta_keywords_bn' => doctor_import_clean($meta_keywords_bn_pair['value'] ?? '', 255),

                                'seo_title_mode' => doctor_import_clean_seo_mode($seo_title_pair['mode'] ?? 'auto'),
                                'seo_title_bn_mode' => doctor_import_clean_seo_mode($seo_title_bn_pair['mode'] ?? 'auto'),
                                'seo_description_mode' => doctor_import_clean_seo_mode($seo_description_pair['mode'] ?? 'auto'),
                                'seo_description_bn_mode' => doctor_import_clean_seo_mode($seo_description_bn_pair['mode'] ?? 'auto'),
                                'meta_keywords_mode' => doctor_import_clean_seo_mode($meta_keywords_pair['mode'] ?? 'auto'),
                                'meta_keywords_bn_mode' => doctor_import_clean_seo_mode($meta_keywords_bn_pair['mode'] ?? 'auto'),
                            ];

                            if ($existing_id > 0 && !$replace_empty) {
                                $row_data = doctor_import_preserve_existing_empty_values($row_data, $existing, [
                                    'specialty_id',
                                    'hospital_id',
                                    'doctor_division_id',
                                    'doctor_district_id',
                                    'doctor_thana_id',
                                    'consultation_fee',
                                    'follow_up_fee',
                                    'video_consultation_fee',
                                    'rating',
                                    'reviews_count',
                                    'featured_days',
                                    'featured_position',
                                ]);
                            }

                            if ($existing_id > 0) {
                                $row_data['updated_at'] = '__NOW__';
                                doctor_import_update_dynamic('doctors', $existing_id, $row_data);

                                $saved_id = $existing_id;
                                $stats['updated']++;
                                $logs[] = "Line {$line}: updated doctor #{$existing_id} by {$duplicate_by} - {$name}.";
                            } else {
                                $row_data['created_at'] = '__NOW__';
                                $row_data['updated_at'] = '__NOW__';

                                $saved_id = doctor_import_insert_dynamic('doctors', $row_data);

                                if ($saved_id > 0) {
                                    $stats['created']++;
                                    $logs[] = "Line {$line}: created doctor #{$saved_id} - {$name}.";
                                } else {
                                    $stats['skipped']++;
                                    $logs[] = "Line {$line}: doctor insert failed - {$name}.";
                                    continue;
                                }
                            }

                            doctor_import_save_chambers($saved_id, $row, $map, $replace_empty, $auto_create_missing);
                        } catch (Throwable $e) {
                            $stats['skipped']++;
                            $logs[] = "Line {$line}: error - " . $e->getMessage();
                        }
                    }

                    $message = 'Doctor import completed.';
                    $message_type = 'success';
                }
            }

            fclose($handle);
        }
    }

    $_SESSION['doctor_import_result'] = [
        'message' => $message,
        'message_type' => $message_type,
        'logs' => $logs,
        'stats' => $stats,
    ];

    $redirect_url = strtok($_SERVER['REQUEST_URI'], '?');
    header('Location: ' . $redirect_url . '?import_done=1');
    exit;
}

require_once __DIR__ . '/includes/header.php';
?>

<style>
    body {
        background: #f6f8fa;
    }

    .doctor-import-page,
    .doctor-import-page * {
        box-sizing: border-box;
    }

    .doctor-import-page {
        max-width: 1180px;
        margin: 0 auto;
        padding: 16px 16px 44px;
        color: #24292f;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Helvetica, Arial, sans-serif;
    }

    .di-hero,
    .di-card {
        background: #ffffff;
        border: 1px solid #d0d7de;
        border-radius: 12px;
        box-shadow: 0 1px 0 rgba(27,31,36,0.04);
        margin-bottom: 16px;
        overflow: hidden;
    }

    .di-hero {
        padding: 20px;
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 18px;
    }

    .di-breadcrumb {
        display: flex;
        gap: 7px;
        align-items: center;
        color: #57606a;
        font-size: 13px;
        margin-bottom: 10px;
    }

    .di-breadcrumb a {
        color: #0969da;
        text-decoration: none;
    }

    .di-hero h1 {
        margin: 0;
        font-size: 30px;
        line-height: 1.18;
        letter-spacing: -0.03em;
    }

    .di-hero p {
        margin: 8px 0 0;
        color: #57606a;
        line-height: 1.6;
        max-width: 800px;
        font-size: 14px;
    }

    .di-actions {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        justify-content: flex-end;
    }

    .di-btn {
        min-height: 36px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        padding: 7px 12px;
        border: 1px solid rgba(27,31,36,0.15);
        border-radius: 7px;
        background: #f6f8fa;
        color: #24292f;
        text-decoration: none;
        cursor: pointer;
        font-size: 13.5px;
        font-weight: 600;
        line-height: 1.4;
        white-space: nowrap;
    }

    .di-btn:hover {
        background: #eef1f4;
    }

    .di-btn-primary {
        color: #fff;
        background: #2da44e;
    }

    .di-btn-primary:hover {
        background: #1f883d;
    }

    .di-card-header {
        padding: 15px 18px;
        background: #f6f8fa;
        border-bottom: 1px solid #d0d7de;
    }

    .di-card-header h2 {
        margin: 0;
        font-size: 18px;
        line-height: 1.35;
    }

    .di-card-header p {
        margin: 5px 0 0;
        color: #57606a;
        font-size: 13px;
        line-height: 1.5;
    }

    .di-card-body {
        padding: 18px;
    }

    .di-upload-form {
        display: grid;
        grid-template-columns: 1fr auto;
        gap: 14px;
        align-items: end;
    }

    .di-field {
        display: grid;
        gap: 7px;
    }

    .di-field label {
        color: #24292f;
        font-size: 13.5px;
        font-weight: 600;
    }

    .di-field input[type="file"] {
        width: 100%;
        min-height: 40px;
        padding: 10px;
        border: 1px solid #d0d7de;
        border-radius: 7px;
        background: #fff;
        color: #24292f;
    }

    .di-checks {
        display: grid;
        gap: 8px;
        margin-top: 12px;
    }

    .di-checks label {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        color: #57606a;
        font-size: 13.5px;
        line-height: 1.4;
    }

    .di-checks input {
        width: 15px;
        height: 15px;
        accent-color: #1a7f37;
    }

    .di-alert {
        display: flex;
        gap: 10px;
        align-items: flex-start;
        margin-bottom: 16px;
        padding: 12px 14px;
        border-radius: 10px;
        border: 1px solid #d0d7de;
        font-size: 14px;
        line-height: 1.6;
    }

    .di-alert.success {
        color: #116329;
        background: #dafbe1;
        border-color: #aceebb;
    }

    .di-alert.error {
        color: #82071e;
        background: #ffebe9;
        border-color: #ff818266;
    }

    .di-note {
        padding: 13px 14px;
        border: 1px solid #d0d7de;
        border-radius: 10px;
        background: #f6f8fa;
        color: #57606a;
        font-size: 13.5px;
        line-height: 1.65;
    }

    .di-note + .di-note {
        margin-top: 12px;
    }

    .di-note strong {
        color: #24292f;
    }

    .di-note code {
        display: inline-block;
        padding: 2px 6px;
        border: 1px solid #d8dee4;
        border-radius: 6px;
        background: #fff;
        color: #24292f;
        font-family: ui-monospace, SFMono-Regular, Consolas, "Liberation Mono", Menlo, monospace;
        font-size: 12px;
    }

    .di-stats {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 12px;
    }

    .di-stat {
        padding: 14px;
        border: 1px solid #d0d7de;
        border-radius: 10px;
        background: #fff;
    }

    .di-stat strong {
        display: block;
        color: #24292f;
        font-size: 24px;
        line-height: 1.2;
        letter-spacing: -0.02em;
    }

    .di-stat span {
        display: block;
        margin-top: 3px;
        color: #57606a;
        font-size: 13px;
    }

    .di-log {
        max-height: 360px;
        overflow: auto;
        margin: 0;
        padding: 0;
        list-style: none;
        border: 1px solid #d0d7de;
        border-radius: 10px;
        background: #fff;
    }

    .di-log li {
        padding: 10px 12px;
        border-bottom: 1px solid #d8dee4;
        color: #57606a;
        font-size: 13px;
        line-height: 1.55;
    }

    .di-log li:last-child {
        border-bottom: 0;
    }

    @media (max-width: 860px) {
        .di-hero,
        .di-upload-form {
            grid-template-columns: 1fr;
            flex-direction: column;
            align-items: stretch;
        }

        .di-actions {
            justify-content: flex-start;
        }

        .di-stats {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 520px) {
        .di-stats {
            grid-template-columns: 1fr;
        }

        .di-btn {
            width: 100%;
        }
    }
</style>

<div class="doctor-import-page">
    <?php if ($import_message !== ''): ?>
        <div class="di-alert <?= doctor_import_e($import_message_type) ?>">
            <strong><?= $import_message_type === 'success' ? '✓' : '!' ?></strong>
            <div><?= doctor_import_e($import_message) ?></div>
        </div>
    <?php endif; ?>

    <div class="di-hero">
        <div>
            <div class="di-breadcrumb">
                <a href="dashboard.php">Dashboard</a>
                <span>/</span>
                <a href="doctors.php">Doctors</a>
                <span>/</span>
                <span>Import Doctors</span>
            </div>

            <h1>Import Doctors</h1>
            <p>
                Duplicate doctors are checked only by BMDC number, email, or slug.
                Missing Specialty, Division, District and Thana can be created automatically if enabled.
            </p>
        </div>

        <div class="di-actions">
            <a class="di-btn" href="doctors.php">Back to Doctors</a>
            <a class="di-btn" href="?download_sample=1">Download Sample CSV</a>
        </div>
    </div>

    <div class="di-card">
        <div class="di-card-header">
            <h2>Upload CSV</h2>
            <p>Required column: <strong>name</strong>. Recommended: specialty, district, chamber_name, bmdc_number, email, image_url.</p>
        </div>

        <div class="di-card-body">
            <form class="di-upload-form" method="POST" enctype="multipart/form-data">
                <div>
                    <div class="di-field">
                        <label for="csv_file">CSV File</label>
                        <input type="file" id="csv_file" name="csv_file" accept=".csv,text/csv" required>
                    </div>

                    <div class="di-checks">
                        <label>
                            <input type="checkbox" name="replace_empty" value="1">
                            Replace existing values with empty CSV values
                        </label>

                        <label>
                            <input type="checkbox" name="auto_create_missing" value="1">
                            Create missing Specialty, Division, District and Thana automatically
                        </label>
                    </div>
                </div>

                <button class="di-btn di-btn-primary" type="submit">Import Doctors</button>
            </form>
        </div>
    </div>

    <div class="di-card">
        <div class="di-card-header">
            <h2>Import Rules</h2>
            <p>Important behavior before uploading a large CSV file.</p>
        </div>

        <div class="di-card-body">
            <div class="di-note">
                <strong>Duplicate doctor check:</strong>
                only <code>bmdc_number</code>, <code>email</code>, and <code>slug</code> are used.
                Doctor name is not used for duplicate checking.
            </div>

            <div class="di-note">
                <strong>Auto create:</strong>
                if enabled, missing <code>specialty</code>, <code>division</code>,
                <code>district</code>, and <code>thana</code> will be created automatically.
            </div>

            <div class="di-note">
                <strong>Doctor slug:</strong>
                new doctor slug is generated from <code>name + specialty + district</code>.
                If doctor district is missing, first chamber district is used.
            </div>

            <div class="di-note">
                <strong>Hospital slug from chamber:</strong>
                when hospital is created from chamber name, slug tries
                <code>hospital-name</code>, then <code>hospital-name-district</code>,
                then <code>hospital-name-district-thana</code>, then <code>-2</code>, <code>-3</code>.
            </div>

            <div class="di-note">
                <strong>Image:</strong>
                remote <code>image_url</code> and <code>og_image_url</code> are downloaded and converted to WebP only.
            </div>
        </div>
    </div>

    <?php if (!empty($import_stats['processed']) || !empty($import_logs)): ?>
        <div class="di-card">
            <div class="di-card-header">
                <h2>Import Summary</h2>
                <p>Last import result.</p>
            </div>

            <div class="di-card-body">
                <div class="di-stats">
                    <div class="di-stat"><strong><?= (int)$import_stats['processed'] ?></strong><span>Processed</span></div>
                    <div class="di-stat"><strong><?= (int)$import_stats['created'] ?></strong><span>Doctors Created</span></div>
                    <div class="di-stat"><strong><?= (int)$import_stats['updated'] ?></strong><span>Doctors Updated</span></div>
                    <div class="di-stat"><strong><?= (int)$import_stats['skipped'] ?></strong><span>Skipped</span></div>

                    <div class="di-stat"><strong><?= (int)$import_stats['duplicates_found'] ?></strong><span>Duplicates</span></div>
                    <div class="di-stat"><strong><?= (int)$import_stats['bmdc_duplicates'] ?></strong><span>BMDC Duplicates</span></div>
                    <div class="di-stat"><strong><?= (int)$import_stats['email_duplicates'] ?></strong><span>Email Duplicates</span></div>
                    <div class="di-stat"><strong><?= (int)$import_stats['slug_duplicates'] ?></strong><span>Slug Duplicates</span></div>

                    <div class="di-stat"><strong><?= (int)$import_stats['specialties_matched'] ?></strong><span>Specialties Matched</span></div>
                    <div class="di-stat"><strong><?= (int)$import_stats['specialties_created'] ?></strong><span>Specialties Created</span></div>
                    <div class="di-stat"><strong><?= (int)$import_stats['districts_created'] ?></strong><span>Districts Created</span></div>
                    <div class="di-stat"><strong><?= (int)$import_stats['thanas_created'] ?></strong><span>Thanas Created</span></div>

                    <div class="di-stat"><strong><?= (int)$import_stats['hospitals_matched'] ?></strong><span>Hospitals Matched</span></div>
                    <div class="di-stat"><strong><?= (int)$import_stats['hospitals_created'] ?></strong><span>Hospitals Created</span></div>
                    <div class="di-stat"><strong><?= (int)$import_stats['images_uploaded'] ?></strong><span>Images Uploaded</span></div>
                    <div class="di-stat"><strong><?= (int)$import_stats['images_failed'] ?></strong><span>Images Failed</span></div>

                    <div class="di-stat"><strong><?= (int)$import_stats['chambers_created'] ?></strong><span>Chambers Created</span></div>
                    <div class="di-stat"><strong><?= (int)$import_stats['chambers_updated'] ?></strong><span>Chambers Updated</span></div>
                    <div class="di-stat"><strong><?= (int)$import_stats['chambers_skipped'] ?></strong><span>Chambers Skipped</span></div>
                    <div class="di-stat"><strong><?= (int)$import_stats['divisions_created'] ?></strong><span>Divisions Created</span></div>
                </div>
            </div>
        </div>

        <?php if (!empty($import_logs)): ?>
            <div class="di-card">
                <div class="di-card-header">
                    <h2>Import Logs</h2>
                    <p>Created, updated, skipped and failed row details.</p>
                </div>

                <div class="di-card-body">
                    <ul class="di-log">
                        <?php foreach ($import_logs as $log): ?>
                            <li><?= doctor_import_e($log) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>