<?php
require_once __DIR__ . '/../includes/functions.php';
require_admin();

$import_message = '';
$import_message_type = '';
$import_logs = [];

$import_stats = [
    'processed' => 0,
    'created' => 0,
    'updated' => 0,
    'skipped' => 0,
    'duplicates_found' => 0,
    'license_duplicates' => 0,
    'email_duplicates' => 0,
    'slug_duplicates' => 0,
    'location_duplicates' => 0,
    'divisions_matched' => 0,
    'divisions_unmatched' => 0,
    'districts_matched' => 0,
    'districts_unmatched' => 0,
    'thanas_matched' => 0,
    'thanas_unmatched' => 0,
    'images_uploaded' => 0,
    'images_failed' => 0,
];

if (!function_exists('hospital_import_e')) {
    function hospital_import_e($value): string
    {
        if (function_exists('e')) {
            return e($value);
        }

        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('hospital_import_clean')) {
    function hospital_import_clean($value, int $limit = 500): string
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

if (!function_exists('hospital_import_text')) {
    function hospital_import_text($value, int $limit = 8000): string
    {
        $value = html_entity_decode((string)$value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = str_replace("\xEF\xBB\xBF", '', $value);
        $value = strip_tags($value);
        $value = trim($value);
        $value = preg_replace("/\r\n|\r/u", "\n", (string)$value);
        $value = preg_replace("/[ \t]+/u", ' ', (string)$value);
        $value = preg_replace("/\n{3,}/u", "\n\n", (string)$value);

        if (function_exists('mb_substr')) {
            return mb_substr((string)$value, 0, $limit, 'UTF-8');
        }

        return substr((string)$value, 0, $limit);
    }
}

if (!function_exists('hospital_import_bool')) {
    function hospital_import_bool($value): int
    {
        $value = strtolower(trim((string)$value));

        return in_array($value, ['1', 'yes', 'true', 'active', 'verified', 'featured', 'published'], true) ? 1 : 0;
    }
}

if (!function_exists('hospital_import_int')) {
    function hospital_import_int($value): int
    {
        $value = preg_replace('/[^0-9]/', '', (string)$value);

        return $value === '' ? 0 : (int)$value;
    }
}

if (!function_exists('hospital_import_int_or_null')) {
    function hospital_import_int_or_null($value): ?int
    {
        $value = trim((string)$value);

        if ($value === '') {
            return null;
        }

        $number = preg_replace('/[^0-9]/', '', $value);

        return $number === '' ? null : (int)$number;
    }
}

if (!function_exists('hospital_import_float')) {
    function hospital_import_float($value): float
    {
        $value = preg_replace('/[^0-9.]/', '', (string)$value);

        return $value === '' ? 0.00 : (float)$value;
    }
}

if (!function_exists('hospital_import_status')) {
    function hospital_import_status($value, string $default = 'active'): string
    {
        $value = strtolower(trim((string)$value));

        if (in_array($value, ['active', 'inactive', 'pending'], true)) {
            return $value;
        }

        return $default;
    }
}

if (!function_exists('hospital_import_stat_inc')) {
    function hospital_import_stat_inc(string $key, int $amount = 1): void
    {
        if (isset($GLOBALS['import_stats'][$key])) {
            $GLOBALS['import_stats'][$key] += $amount;
        }
    }
}

if (!function_exists('hospital_import_table_exists')) {
    function hospital_import_table_exists(string $table): bool
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

if (!function_exists('hospital_import_column_exists')) {
    function hospital_import_column_exists(string $table, string $column): bool
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

if (!function_exists('hospital_import_add_column_if_missing')) {
    function hospital_import_add_column_if_missing(string $table, string $column, string $definition): void
    {
        global $pdo;

        try {
            if (hospital_import_table_exists($table) && !hospital_import_column_exists($table, $column)) {
                $pdo->exec("ALTER TABLE {$table} ADD COLUMN {$column} {$definition}");
            }
        } catch (Throwable $e) {
            error_log('Hospital import column add failed: ' . $table . '.' . $column . ' - ' . $e->getMessage());
        }
    }
}

if (!function_exists('hospital_import_ensure_hospitals_table')) {
    function hospital_import_ensure_hospitals_table(): void
    {
        global $pdo;

        $create_table_sql = "CREATE TABLE IF NOT EXISTS hospitals (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL,
            name_bn VARCHAR(255) NULL,
            slug VARCHAR(255) NOT NULL,
            type VARCHAR(100) NULL,
            type_bn VARCHAR(100) NULL,
            phone VARCHAR(100) NULL,
            email VARCHAR(190) NULL,
            address TEXT NULL,
            address_bn TEXT NULL,
            division_id INT UNSIGNED NULL,
            district_id INT UNSIGNED NULL,
            thana_id INT UNSIGNED NULL,
            area VARCHAR(255) NULL,
            area_bn VARCHAR(255) NULL,
            road_no VARCHAR(255) NULL,
            road_no_bn VARCHAR(255) NULL,
            house_no VARCHAR(255) NULL,
            house_no_bn VARCHAR(255) NULL,
            post_code VARCHAR(50) NULL,
            map_url TEXT NULL,
            image TEXT NULL,
            cover_image TEXT NULL,
            description LONGTEXT NULL,
            description_bn LONGTEXT NULL,
            departments_count INT UNSIGNED NOT NULL DEFAULT 0,
            doctors_count INT UNSIGNED NOT NULL DEFAULT 0,
            rating DECIMAL(3,2) NOT NULL DEFAULT 0.00,
            is_verified TINYINT(1) NOT NULL DEFAULT 0,
            is_featured TINYINT(1) NOT NULL DEFAULT 0,
            status VARCHAR(30) NOT NULL DEFAULT 'active',
            seo_title VARCHAR(255) NULL,
            seo_title_bn VARCHAR(255) NULL,
            seo_description TEXT NULL,
            seo_description_bn TEXT NULL,
            created_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL,
            website_url TEXT NULL,
            whatsapp VARCHAR(100) NULL,
            emergency_phone VARCHAR(100) NULL,
            ambulance_phone VARCHAR(100) NULL,
            opening_hours VARCHAR(255) NULL,
            opening_hours_bn VARCHAR(255) NULL,
            visiting_hours VARCHAR(255) NULL,
            visiting_hours_bn VARCHAR(255) NULL,
            services LONGTEXT NULL,
            services_bn LONGTEXT NULL,
            facilities LONGTEXT NULL,
            facilities_bn LONGTEXT NULL,
            bed_count INT UNSIGNED NOT NULL DEFAULT 0,
            established_year SMALLINT UNSIGNED NULL,
            video_url TEXT NULL,
            license_number VARCHAR(190) NULL,
            meta_keywords TEXT NULL,
            meta_keywords_bn TEXT NULL,
            PRIMARY KEY (id),
            KEY idx_hospitals_slug (slug),
            KEY idx_hospitals_location (division_id, district_id, thana_id),
            KEY idx_hospitals_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        try {
            $pdo->exec($create_table_sql);
        } catch (Throwable $e) {
            error_log('Hospital import create table failed: ' . $e->getMessage());
        }

        if (!hospital_import_table_exists('hospitals')) {
            return;
        }

        $columns = [
            'id' => "INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST",
            'name' => "VARCHAR(255) NOT NULL AFTER id",
            'name_bn' => "VARCHAR(255) NULL AFTER name",
            'slug' => "VARCHAR(255) NOT NULL AFTER name_bn",
            'type' => "VARCHAR(100) NULL AFTER slug",
            'type_bn' => "VARCHAR(100) NULL AFTER type",
            'phone' => "VARCHAR(100) NULL AFTER type_bn",
            'email' => "VARCHAR(190) NULL AFTER phone",
            'address' => "TEXT NULL AFTER email",
            'address_bn' => "TEXT NULL AFTER address",
            'division_id' => "INT UNSIGNED NULL AFTER address_bn",
            'district_id' => "INT UNSIGNED NULL AFTER division_id",
            'thana_id' => "INT UNSIGNED NULL AFTER district_id",
            'area' => "VARCHAR(255) NULL AFTER thana_id",
            'area_bn' => "VARCHAR(255) NULL AFTER area",
            'road_no' => "VARCHAR(255) NULL AFTER area_bn",
            'road_no_bn' => "VARCHAR(255) NULL AFTER road_no",
            'house_no' => "VARCHAR(255) NULL AFTER road_no_bn",
            'house_no_bn' => "VARCHAR(255) NULL AFTER house_no",
            'post_code' => "VARCHAR(50) NULL AFTER house_no_bn",
            'map_url' => "TEXT NULL AFTER post_code",
            'image' => "TEXT NULL AFTER map_url",
            'cover_image' => "TEXT NULL AFTER image",
            'description' => "LONGTEXT NULL AFTER cover_image",
            'description_bn' => "LONGTEXT NULL AFTER description",
            'departments_count' => "INT UNSIGNED NOT NULL DEFAULT 0 AFTER description_bn",
            'doctors_count' => "INT UNSIGNED NOT NULL DEFAULT 0 AFTER departments_count",
            'rating' => "DECIMAL(3,2) NOT NULL DEFAULT 0.00 AFTER doctors_count",
            'is_verified' => "TINYINT(1) NOT NULL DEFAULT 0 AFTER rating",
            'is_featured' => "TINYINT(1) NOT NULL DEFAULT 0 AFTER is_verified",
            'status' => "VARCHAR(30) NOT NULL DEFAULT 'active' AFTER is_featured",
            'seo_title' => "VARCHAR(255) NULL AFTER status",
            'seo_title_bn' => "VARCHAR(255) NULL AFTER seo_title",
            'seo_description' => "TEXT NULL AFTER seo_title_bn",
            'seo_description_bn' => "TEXT NULL AFTER seo_description",
            'created_at' => "DATETIME NULL DEFAULT CURRENT_TIMESTAMP AFTER seo_description_bn",
            'updated_at' => "DATETIME NULL AFTER created_at",
            'website_url' => "TEXT NULL AFTER updated_at",
            'whatsapp' => "VARCHAR(100) NULL AFTER website_url",
            'emergency_phone' => "VARCHAR(100) NULL AFTER whatsapp",
            'ambulance_phone' => "VARCHAR(100) NULL AFTER emergency_phone",
            'opening_hours' => "VARCHAR(255) NULL AFTER ambulance_phone",
            'opening_hours_bn' => "VARCHAR(255) NULL AFTER opening_hours",
            'visiting_hours' => "VARCHAR(255) NULL AFTER opening_hours_bn",
            'visiting_hours_bn' => "VARCHAR(255) NULL AFTER visiting_hours",
            'services' => "LONGTEXT NULL AFTER visiting_hours_bn",
            'services_bn' => "LONGTEXT NULL AFTER services",
            'facilities' => "LONGTEXT NULL AFTER services_bn",
            'facilities_bn' => "LONGTEXT NULL AFTER facilities",
            'bed_count' => "INT UNSIGNED NOT NULL DEFAULT 0 AFTER facilities_bn",
            'established_year' => "SMALLINT UNSIGNED NULL AFTER bed_count",
            'video_url' => "TEXT NULL AFTER established_year",
            'license_number' => "VARCHAR(190) NULL AFTER video_url",
            'meta_keywords' => "TEXT NULL AFTER license_number",
            'meta_keywords_bn' => "TEXT NULL AFTER meta_keywords",
        ];

        foreach ($columns as $column => $definition) {
            if (!hospital_import_column_exists('hospitals', $column)) {
                hospital_import_add_column_if_missing('hospitals', $column, $definition);
            }
        }
    }
}

$hospital_type_map = [
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

if (!function_exists('hospital_import_type_bn')) {
    function hospital_import_type_bn(string $type): string
    {
        global $hospital_type_map;

        return $hospital_type_map[$type] ?? '';
    }
}

if (!function_exists('hospital_import_slug')) {
    function hospital_import_slug(string $text): string
    {
        $text = hospital_import_clean($text, 255);

        if ($text === '') {
            return 'hospital';
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

        return $slug !== '' ? strtolower($slug) : 'hospital';
    }
}

if (!function_exists('hospital_import_unique_slug')) {
    function hospital_import_unique_slug(string $slug, int $ignore_id = 0): string
    {
        global $pdo;

        $slug = hospital_import_slug($slug);

        if (function_exists('make_unique_slug')) {
            return make_unique_slug('hospitals', $slug, $ignore_id);
        }

        $base = $slug;
        $counter = 2;

        while (true) {
            $sql = "SELECT id FROM hospitals WHERE slug = :slug";
            $params = [':slug' => $slug];

            if ($ignore_id > 0) {
                $sql .= " AND id != :id";
                $params[':id'] = $ignore_id;
            }

            $sql .= " LIMIT 1";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
                return $slug;
            }

            $slug = $base . '-' . $counter;
            $counter++;
        }
    }
}

if (!function_exists('hospital_import_generate_slug_candidates')) {
    function hospital_import_generate_slug_candidates(string $name, string $dist_en = '', string $sub_en = ''): array
    {
        $candidates = [];

        $name = hospital_import_clean($name, 255);
        $dist_en = hospital_import_clean($dist_en, 150);
        $sub_en = hospital_import_clean($sub_en, 150);

        if ($name !== '') {
            $candidates[] = hospital_import_slug($name);
        }

        if ($name !== '' && $dist_en !== '') {
            $candidates[] = hospital_import_slug($name . '-' . $dist_en);
        }

        if ($name !== '' && $dist_en !== '' && $sub_en !== '') {
            $candidates[] = hospital_import_slug($name . '-' . $dist_en . '-' . $sub_en);
        }

        $candidates = array_values(array_unique(array_filter($candidates)));

        return $candidates ?: ['hospital'];
    }
}

if (!function_exists('hospital_import_first_available_slug')) {
    function hospital_import_first_available_slug(array $candidates, int $ignore_id = 0): string
    {
        global $pdo;

        foreach ($candidates as $candidate) {
            $candidate = hospital_import_slug($candidate);

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

        return hospital_import_unique_slug($candidates[0] ?? 'hospital', $ignore_id);
    }
}

if (!function_exists('hospital_import_header_map')) {
    function hospital_import_header_map(array $header): array
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

if (!function_exists('hospital_import_csv_value')) {
    function hospital_import_csv_value(array $row, array $map, string $key, string $default = ''): string
    {
        if (!isset($map[$key])) {
            return $default;
        }

        $index = (int)$map[$key];

        return isset($row[$index]) ? trim((string)$row[$index]) : $default;
    }
}

if (!function_exists('hospital_import_first_value')) {
    function hospital_import_first_value(array $row, array $map, array $keys, string $default = ''): string
    {
        foreach ($keys as $key) {
            $value = hospital_import_csv_value($row, $map, $key, '');

            if (trim($value) !== '') {
                return $value;
            }
        }

        return $default;
    }
}

if (!function_exists('hospital_import_find_location_id')) {
    function hospital_import_find_location_id(string $table, string $name, string $parent_column = '', int $parent_id = 0): int
    {
        global $pdo;

        $allowed_tables = ['divisions', 'districts', 'thanas'];
        $allowed_parent_columns = ['division_id', 'district_id'];

        $name = hospital_import_clean($name, 150);

        if ($name === '' || !in_array($table, $allowed_tables, true) || !hospital_import_table_exists($table)) {
            return 0;
        }

        $name_columns = [];

        foreach (['name_en', 'name_bn', 'name'] as $column) {
            if (hospital_import_column_exists($table, $column)) {
                $name_columns[] = $column;
            }
        }

        if (!$name_columns) {
            return 0;
        }

        foreach ($name_columns as $column) {
            $sql = "SELECT id FROM {$table} WHERE {$column} = :name";
            $params = [':name' => $name];

            if (
                $parent_column !== ''
                && in_array($parent_column, $allowed_parent_columns, true)
                && $parent_id > 0
                && hospital_import_column_exists($table, $parent_column)
            ) {
                $sql .= " AND {$parent_column} = :parent_id";
                $params[':parent_id'] = $parent_id;
            }

            if (hospital_import_column_exists($table, 'status')) {
                $sql .= " AND (status IS NULL OR status = '' OR status = 'active')";
            }

            $sql .= " LIMIT 1";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            $id = (int)$stmt->fetchColumn();

            if ($id > 0) {
                return $id;
            }
        }

        return 0;
    }
}

if (!function_exists('hospital_import_location_row')) {
    function hospital_import_location_row(string $table, int $id): array
    {
        global $pdo;

        $allowed_tables = ['divisions', 'districts', 'thanas'];

        if ($id <= 0 || !in_array($table, $allowed_tables, true) || !hospital_import_table_exists($table)) {
            return [];
        }

        $select = ['id'];

        foreach (['name_en', 'name_bn', 'name'] as $column) {
            if (hospital_import_column_exists($table, $column)) {
                $select[] = $column;
            }
        }

        try {
            $stmt = $pdo->prepare("SELECT " . implode(', ', $select) . " FROM {$table} WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => $id]);

            return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }
}

if (!function_exists('hospital_import_loc_en')) {
    function hospital_import_loc_en(array $row): string
    {
        return hospital_import_clean($row['name_en'] ?? $row['name'] ?? '', 150);
    }
}

if (!function_exists('hospital_import_loc_bn')) {
    function hospital_import_loc_bn(array $row): string
    {
        return hospital_import_clean($row['name_bn'] ?? '', 150);
    }
}

if (!function_exists('hospital_import_resolve_location')) {
    function hospital_import_resolve_location(string $division, string $district, string $thana): array
    {
        $division = hospital_import_clean($division, 150);
        $district = hospital_import_clean($district, 150);
        $thana = hospital_import_clean($thana, 150);

        $div_id = hospital_import_find_location_id('divisions', $division);

        if ($div_id > 0) {
            hospital_import_stat_inc('divisions_matched');
        } elseif ($division !== '') {
            hospital_import_stat_inc('divisions_unmatched');
        }

        $dist_id = hospital_import_find_location_id('districts', $district, 'division_id', $div_id);

        if ($dist_id <= 0) {
            $dist_id = hospital_import_find_location_id('districts', $district);
        }

        if ($dist_id > 0) {
            hospital_import_stat_inc('districts_matched');

            if ($div_id <= 0 && hospital_import_column_exists('districts', 'division_id')) {
                global $pdo;

                $stmt = $pdo->prepare("SELECT division_id FROM districts WHERE id = :id LIMIT 1");
                $stmt->execute([':id' => $dist_id]);
                $div_id = (int)$stmt->fetchColumn();
            }
        } elseif ($district !== '') {
            hospital_import_stat_inc('districts_unmatched');
        }

        $sub_id = hospital_import_find_location_id('thanas', $thana, 'district_id', $dist_id);

        if ($sub_id <= 0) {
            $sub_id = hospital_import_find_location_id('thanas', $thana);
        }

        if ($sub_id > 0) {
            hospital_import_stat_inc('thanas_matched');
        } elseif ($thana !== '') {
            hospital_import_stat_inc('thanas_unmatched');
        }

        $div_row = hospital_import_location_row('divisions', $div_id);
        $dist_row = hospital_import_location_row('districts', $dist_id);
        $sub_row = hospital_import_location_row('thanas', $sub_id);

        return [
            'div_id' => $div_id ?: null,
            'dist_id' => $dist_id ?: null,
            'sub_id' => $sub_id ?: null,

            'div_en' => hospital_import_loc_en($div_row),
            'div_bn' => hospital_import_loc_bn($div_row),
            'dist_en' => hospital_import_loc_en($dist_row),
            'dist_bn' => hospital_import_loc_bn($dist_row),
            'sub_en' => hospital_import_loc_en($sub_row),
            'sub_bn' => hospital_import_loc_bn($sub_row),
        ];
    }
}

if (!function_exists('hospital_import_join_address')) {
    function hospital_import_join_address(array $items): string
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

if (!function_exists('hospital_import_is_url')) {
    function hospital_import_is_url(string $value): bool
    {
        $value = trim($value);

        return $value !== '' && filter_var($value, FILTER_VALIDATE_URL) !== false;
    }
}

if (!function_exists('hospital_import_upload_base_path')) {
    function hospital_import_upload_base_path(): string
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

if (!function_exists('hospital_import_upload_base_url')) {
    function hospital_import_upload_base_url(): string
    {
        if (defined('UPLOAD_URL')) {
            return rtrim((string)UPLOAD_URL, '/');
        }

        return '/assets/uploads';
    }
}

if (!function_exists('hospital_import_local_image_only')) {
    function hospital_import_local_image_only(string $value): string
    {
        $value = trim($value);

        if ($value === '' || hospital_import_is_url($value)) {
            return '';
        }

        if (preg_match('#^(uploads|assets/uploads|storage)/#', $value)) {
            return hospital_import_clean($value, 500);
        }

        if (preg_match('#^/uploads/#', $value)) {
            return hospital_import_clean($value, 500);
        }

        return '';
    }
}

if (!function_exists('hospital_import_remote_image_fetch')) {
    function hospital_import_remote_image_fetch(string $url, int $max_bytes = 5242880): array
    {
        $url = trim($url);

        if (!hospital_import_is_url($url)) {
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
                CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; HospitalImporter/1.0)',
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
                    'user_agent' => 'Mozilla/5.0 (compatible; HospitalImporter/1.0)',
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

if (!function_exists('hospital_import_image_mime_from_file')) {
    function hospital_import_image_mime_from_file(string $file): string
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

if (!function_exists('hospital_import_gd_source')) {
    function hospital_import_gd_source(string $file, string $mime)
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

if (!function_exists('hospital_import_save_image_as_webp')) {
    function hospital_import_save_image_as_webp(string $source_file, string $target_file, string $mime, int $max_width = 1400, int $max_height = 1400, int $quality = 82): bool
    {
        if (!function_exists('imagewebp')) {
            return false;
        }

        $source = hospital_import_gd_source($source_file, $mime);

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

if (!function_exists('hospital_import_store_image')) {
    function hospital_import_store_image(string $value, string $folder, string $hospital_name, int $max_width = 1400, int $max_height = 1400): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        if (!hospital_import_is_url($value)) {
            return hospital_import_local_image_only($value);
        }

        $download = hospital_import_remote_image_fetch($value);

        if (!$download['ok']) {
            $GLOBALS['import_logs'][] = 'Image failed: ' . $value . ' - ' . $download['error'];
            hospital_import_stat_inc('images_failed');
            return '';
        }

        $tmp = tempnam(sys_get_temp_dir(), 'hospital-import-img-');

        if (!$tmp) {
            $GLOBALS['import_logs'][] = 'Image failed: ' . $value . ' - temp file could not be created';
            hospital_import_stat_inc('images_failed');
            return '';
        }

        file_put_contents($tmp, $download['body']);

        $mime = hospital_import_image_mime_from_file($tmp);

        $ext_map = [
            'image/jpeg' => 'jpg',
            'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            'image/bmp' => 'bmp',
            'image/x-ms-bmp' => 'bmp',
        ];

        if (!isset($ext_map[$mime])) {
            @unlink($tmp);
            $GLOBALS['import_logs'][] = 'Image failed: ' . $value . ' - invalid mime: ' . $mime;
            hospital_import_stat_inc('images_failed');
            return '';
        }

        $upload_path = hospital_import_upload_base_path();
        $upload_url = hospital_import_upload_base_url();

        $folder = trim($folder, '/');
        $dir = rtrim($upload_path, '/') . '/' . $folder;

        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        if (!is_dir($dir) || !is_writable($dir)) {
            @unlink($tmp);
            $GLOBALS['import_logs'][] = 'Image failed: upload folder is not writable - ' . $dir;
            hospital_import_stat_inc('images_failed');
            return '';
        }

        $base = hospital_import_slug($hospital_name);
        $date = date('ymdHis');

        $filename = $base . '-' . $date . '.webp';
        $target = $dir . '/' . $filename;
        $counter = 2;

        while (file_exists($target)) {
            $filename = $base . '-' . $date . '-' . $counter . '.webp';
            $target = $dir . '/' . $filename;
            $counter++;
        }

        $saved = hospital_import_save_image_as_webp($tmp, $target, $mime, $max_width, $max_height, 82);

        @unlink($tmp);

        if (!$saved || !file_exists($target)) {
            $GLOBALS['import_logs'][] = 'Image failed: ' . $value . ' - WebP conversion failed. Original format was not saved.';
            hospital_import_stat_inc('images_failed');
            return '';
        }

        @chmod($target, 0644);

        hospital_import_stat_inc('images_uploaded');

        return rtrim($upload_url, '/') . '/' . $folder . '/' . $filename;
    }
}

if (!function_exists('hospital_import_find_existing')) {
    function hospital_import_find_existing(
        string $license_number,
        string $email,
        string $slug,
        string $name,
        ?int $dist_id = null,
        ?int $sub_id = null
    ): array {
        global $pdo;

        if (!hospital_import_table_exists('hospitals')) {
            return [];
        }

        $license_number = hospital_import_clean($license_number, 190);
        $email = hospital_import_clean($email, 190);
        $slug = hospital_import_clean($slug, 255);
        $name = hospital_import_clean($name, 255);

        if ($license_number !== '' && hospital_import_column_exists('hospitals', 'license_number')) {
            $stmt = $pdo->prepare("SELECT *, 'license_number' AS duplicate_by FROM hospitals WHERE license_number = :license_number LIMIT 1");
            $stmt->execute([':license_number' => $license_number]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row) {
                hospital_import_stat_inc('license_duplicates');
                return $row;
            }
        }

        if ($email !== '' && hospital_import_column_exists('hospitals', 'email')) {
            $stmt = $pdo->prepare("SELECT *, 'email' AS duplicate_by FROM hospitals WHERE email = :email LIMIT 1");
            $stmt->execute([':email' => $email]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row) {
                hospital_import_stat_inc('email_duplicates');
                return $row;
            }
        }

        if ($slug !== '' && hospital_import_column_exists('hospitals', 'slug')) {
            $stmt = $pdo->prepare("SELECT *, 'slug' AS duplicate_by FROM hospitals WHERE slug = :slug LIMIT 1");
            $stmt->execute([':slug' => $slug]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row) {
                hospital_import_stat_inc('slug_duplicates');
                return $row;
            }
        }

        if (
            $name !== ''
            && $dist_id
            && $sub_id
            && hospital_import_column_exists('hospitals', 'district_id')
            && hospital_import_column_exists('hospitals', 'thana_id')
        ) {
            $stmt = $pdo->prepare("
                SELECT *, 'name_district_thana' AS duplicate_by
                FROM hospitals
                WHERE name = :name
                  AND district_id = :district_id
                  AND thana_id = :thana_id
                LIMIT 1
            ");
            $stmt->execute([
                ':name' => $name,
                ':district_id' => $dist_id,
                ':thana_id' => $sub_id,
            ]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row) {
                hospital_import_stat_inc('location_duplicates');
                return $row;
            }
        }

        if (
            $name !== ''
            && $dist_id
            && !$sub_id
            && hospital_import_column_exists('hospitals', 'district_id')
        ) {
            $stmt = $pdo->prepare("
                SELECT *, 'name_district' AS duplicate_by
                FROM hospitals
                WHERE name = :name
                  AND district_id = :district_id
                LIMIT 1
            ");
            $stmt->execute([
                ':name' => $name,
                ':district_id' => $dist_id,
            ]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row) {
                hospital_import_stat_inc('location_duplicates');
                return $row;
            }
        }

        return [];
    }
}

if (!function_exists('hospital_import_insert_dynamic')) {
    function hospital_import_insert_dynamic(array $data): int
    {
        global $pdo;

        $columns = [];
        $values = [];
        $params = [];

        foreach ($data as $column => $value) {
            if (!hospital_import_column_exists('hospitals', $column)) {
                continue;
            }

            $columns[] = $column;

            if ($value === '__NOW__') {
                $values[] = 'NOW()';
            } else {
                $placeholder = ':' . $column;
                $values[] = $placeholder;
                $params[$placeholder] = $value;
            }
        }

        if (!$columns) {
            return 0;
        }

        $stmt = $pdo->prepare("
            INSERT INTO hospitals (" . implode(', ', $columns) . ")
            VALUES (" . implode(', ', $values) . ")
        ");
        $stmt->execute($params);

        return (int)$pdo->lastInsertId();
    }
}

if (!function_exists('hospital_import_update_dynamic')) {
    function hospital_import_update_dynamic(int $id, array $data): bool
    {
        global $pdo;

        if ($id <= 0) {
            return false;
        }

        $sets = [];
        $params = [':id' => $id];

        foreach ($data as $column => $value) {
            if ($column === 'id' || !hospital_import_column_exists('hospitals', $column)) {
                continue;
            }

            if ($value === '__NOW__') {
                $sets[] = "{$column} = NOW()";
            } else {
                $placeholder = ':' . $column;
                $sets[] = "{$column} = {$placeholder}";
                $params[$placeholder] = $value;
            }
        }

        if (!$sets) {
            return false;
        }

        $stmt = $pdo->prepare("UPDATE hospitals SET " . implode(', ', $sets) . " WHERE id = :id");

        return $stmt->execute($params);
    }
}

if (!function_exists('hospital_import_download_sample')) {
    function hospital_import_download_sample(): void
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="sample-hospital-import.csv"');

        $out = fopen('php://output', 'w');
        fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));

        $headers = [
            'name',
            'name_bn',
            'slug',
            'type',
            'type_bn',
            'phone',
            'email',
            'division',
            'district',
            'thana',
            'area',
            'area_bn',
            'road_no',
            'road_no_bn',
            'house_no',
            'house_no_bn',
            'post_code',
            'address',
            'address_bn',
            'map_url',
            'image',
            'cover_image',
            'image_url',
            'cover_image_url',
            'description',
            'description_bn',
            'departments_count',
            'doctors_count',
            'rating',
            'is_verified',
            'is_featured',
            'status',
            'seo_title',
            'seo_title_bn',
            'seo_description',
            'seo_description_bn',
            'website_url',
            'whatsapp',
            'emergency_phone',
            'ambulance_phone',
            'opening_hours',
            'opening_hours_bn',
            'visiting_hours',
            'visiting_hours_bn',
            'services',
            'services_bn',
            'facilities',
            'facilities_bn',
            'bed_count',
            'established_year',
            'video_url',
            'license_number',
            'meta_keywords',
            'meta_keywords_bn',
        ];

        fputcsv($out, $headers);

        $sample = array_fill_keys($headers, '');

        $sample['name'] = 'Square Hospital';
        $sample['name_bn'] = 'স্কয়ার হাসপাতাল';
        $sample['slug'] = '';
        $sample['type'] = 'Private Hospital';
        $sample['type_bn'] = '';
        $sample['phone'] = '01711111111';
        $sample['email'] = 'info@example.com';
        $sample['division'] = 'Dhaka';
        $sample['district'] = 'Dhaka';
        $sample['thana'] = 'Dhanmondi';
        $sample['area'] = 'Panthapath';
        $sample['area_bn'] = 'পান্থপথ';
        $sample['road_no'] = 'Road 5';
        $sample['road_no_bn'] = 'রোড ৫';
        $sample['house_no'] = 'House 10';
        $sample['house_no_bn'] = 'বাড়ি ১০';
        $sample['post_code'] = '1205';
        $sample['address'] = '';
        $sample['address_bn'] = '';
        $sample['map_url'] = 'https://maps.google.com/';
        $sample['image'] = '';
        $sample['cover_image'] = '';
        $sample['image_url'] = 'https://example.com/hospital.jpg';
        $sample['cover_image_url'] = 'https://example.com/hospital-cover.jpg';
        $sample['description'] = 'A modern private hospital.';
        $sample['description_bn'] = 'একটি আধুনিক বেসরকারি হাসপাতাল।';
        $sample['departments_count'] = '0';
        $sample['doctors_count'] = '0';
        $sample['rating'] = '0';
        $sample['is_verified'] = '1';
        $sample['is_featured'] = '1';
        $sample['status'] = 'active';
        $sample['seo_title'] = 'Best Private Hospital in Dhaka';
        $sample['seo_title_bn'] = 'ঢাকার সেরা বেসরকারি হাসপাতাল';
        $sample['seo_description'] = 'Modern hospital in Dhaka with emergency and specialist services.';
        $sample['seo_description_bn'] = 'ঢাকায় ইমার্জেন্সি ও বিশেষজ্ঞ সেবা সহ আধুনিক হাসপাতাল।';
        $sample['website_url'] = 'https://example.com';
        $sample['whatsapp'] = '01711111111';
        $sample['emergency_phone'] = '10616';
        $sample['ambulance_phone'] = '01722222222';
        $sample['opening_hours'] = '24/7 Open';
        $sample['opening_hours_bn'] = '২৪ ঘণ্টা খোলা';
        $sample['visiting_hours'] = '10 AM - 8 PM';
        $sample['visiting_hours_bn'] = 'সকাল ১০টা - রাত ৮টা';
        $sample['services'] = 'Emergency, ICU, Cardiology';
        $sample['services_bn'] = 'ইমার্জেন্সি, আইসিইউ, কার্ডিওলজি';
        $sample['facilities'] = 'Parking, Pharmacy';
        $sample['facilities_bn'] = 'পার্কিং, ফার্মেসি';
        $sample['bed_count'] = '250';
        $sample['established_year'] = '2005';
        $sample['video_url'] = 'https://youtube.com/';
        $sample['license_number'] = 'DGHS-123456';
        $sample['meta_keywords'] = 'hospital, dhaka hospital';
        $sample['meta_keywords_bn'] = 'হাসপাতাল, ঢাকা হাসপাতাল';

        fputcsv($out, array_map(static function ($header) use ($sample) {
            return $sample[$header] ?? '';
        }, $headers));

        fclose($out);
        exit;
    }
}

if (isset($_GET['download_sample']) && $_GET['download_sample'] === '1') {
    hospital_import_download_sample();
}

hospital_import_ensure_hospitals_table();

if (!empty($_SESSION['hospital_import_result']) && is_array($_SESSION['hospital_import_result'])) {
    $result = $_SESSION['hospital_import_result'];

    $import_message = $result['message'] ?? '';
    $import_message_type = $result['message_type'] ?? '';
    $import_logs = $result['logs'] ?? [];
    $import_stats = $result['stats'] ?? $import_stats;

    unset($_SESSION['hospital_import_result']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $update_existing = isset($_POST['update_existing']);
    $replace_empty = isset($_POST['replace_empty']);

    $logs = [];
    $stats = [
        'processed' => 0,
        'created' => 0,
        'updated' => 0,
        'skipped' => 0,
        'duplicates_found' => 0,
        'license_duplicates' => 0,
        'email_duplicates' => 0,
        'slug_duplicates' => 0,
        'location_duplicates' => 0,
        'divisions_matched' => 0,
        'divisions_unmatched' => 0,
        'districts_matched' => 0,
        'districts_unmatched' => 0,
        'thanas_matched' => 0,
        'thanas_unmatched' => 0,
        'images_uploaded' => 0,
        'images_failed' => 0,
    ];

    $GLOBALS['import_stats'] = &$stats;
    $GLOBALS['import_logs'] = &$logs;

    $message = '';
    $message_type = '';

    if (empty($_FILES['csv_file']['tmp_name']) || (int)($_FILES['csv_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
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
                $map = hospital_import_header_map($header);

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
                            $name = hospital_import_clean(hospital_import_csv_value($row, $map, 'name'), 255);

                            if ($name === '') {
                                $stats['skipped']++;
                                $logs[] = "Line {$line}: skipped because name is empty.";
                                continue;
                            }

                            $name_bn = hospital_import_clean(hospital_import_csv_value($row, $map, 'name_bn'), 255);

                            $division = hospital_import_first_value($row, $map, ['division'], '');
                            $district = hospital_import_first_value($row, $map, ['district'], '');
                            $thana = hospital_import_first_value($row, $map, ['thana', 'upazila'], '');

                            $location = hospital_import_resolve_location($division, $district, $thana);

                            $area = hospital_import_clean(hospital_import_csv_value($row, $map, 'area'), 255);
                            $area_bn = hospital_import_clean(hospital_import_csv_value($row, $map, 'area_bn'), 255);
                            $road_no = hospital_import_clean(hospital_import_csv_value($row, $map, 'road_no'), 255);
                            $road_no_bn = hospital_import_clean(hospital_import_csv_value($row, $map, 'road_no_bn'), 255);
                            $house_no = hospital_import_clean(hospital_import_csv_value($row, $map, 'house_no'), 255);
                            $house_no_bn = hospital_import_clean(hospital_import_csv_value($row, $map, 'house_no_bn'), 255);
                            $post_code = hospital_import_clean(hospital_import_csv_value($row, $map, 'post_code'), 50);

                            $sub_with_post_code = $post_code !== ''
                                ? (($location['sub_en'] ?? '') !== '' ? $location['sub_en'] . '-' . $post_code : $post_code)
                                : ($location['sub_en'] ?? '');

                            $sub_with_post_code_bn = $post_code !== ''
                                ? (($location['sub_bn'] ?? '') !== '' ? $location['sub_bn'] . '-' . $post_code : $post_code)
                                : ($location['sub_bn'] ?? '');

                            $address = hospital_import_text(hospital_import_csv_value($row, $map, 'address'), 1500);
                            $address_bn = hospital_import_text(hospital_import_csv_value($row, $map, 'address_bn'), 1500);

                            if ($address === '') {
                                $address = hospital_import_join_address([
                                    $house_no,
                                    $road_no,
                                    $area,
                                    $sub_with_post_code,
                                    $location['dist_en'] ?? '',
                                    $location['div_en'] ?? '',
                                ]);
                            }

                            if ($address_bn === '') {
                                $address_bn = hospital_import_join_address([
                                    $house_no_bn !== '' ? $house_no_bn : $house_no,
                                    $road_no_bn !== '' ? $road_no_bn : $road_no,
                                    $area_bn !== '' ? $area_bn : $area,
                                    $sub_with_post_code_bn !== '' ? $sub_with_post_code_bn : $sub_with_post_code,
                                    ($location['dist_bn'] ?? '') !== '' ? $location['dist_bn'] : ($location['dist_en'] ?? ''),
                                    ($location['div_bn'] ?? '') !== '' ? $location['div_bn'] : ($location['div_en'] ?? ''),
                                ]);
                            }

                            $csv_slug = hospital_import_clean(hospital_import_csv_value($row, $map, 'slug'), 255);

                            $slug_candidates = hospital_import_generate_slug_candidates(
                                $name,
                                $location['dist_en'] ?? '',
                                $location['sub_en'] ?? ''
                            );

                            $base_slug = $csv_slug !== ''
                                ? hospital_import_slug($csv_slug)
                                : $slug_candidates[0];

                            $duplicate_slug = $csv_slug !== '' ? $base_slug : '';
                            $email = hospital_import_clean(hospital_import_csv_value($row, $map, 'email'), 190);
                            $license_number = hospital_import_clean(hospital_import_csv_value($row, $map, 'license_number'), 190);

                            $existing = hospital_import_find_existing(
                                $license_number,
                                $email,
                                $duplicate_slug,
                                $name,
                                !empty($location['dist_id']) ? (int)$location['dist_id'] : null,
                                !empty($location['sub_id']) ? (int)$location['sub_id'] : null
                            );

                            $existing_id = (int)($existing['id'] ?? 0);
                            $duplicate_by = (string)($existing['duplicate_by'] ?? '');

                            if ($existing_id > 0) {
                                $stats['duplicates_found']++;
                            }

                            if ($existing_id > 0 && !$update_existing) {
                                $stats['skipped']++;
                                $logs[] = "Line {$line}: duplicate found by {$duplicate_by}, update disabled, skipped #{$existing_id} - {$name}.";
                                continue;
                            }

                            $csv_type = hospital_import_clean(hospital_import_csv_value($row, $map, 'type'), 100);
                            $csv_type_bn = hospital_import_clean(hospital_import_csv_value($row, $map, 'type_bn'), 100);

                            $old_type = hospital_import_clean($existing['type'] ?? '', 100);
                            $old_type_bn = hospital_import_clean($existing['type_bn'] ?? '', 100);

                            if ($csv_type !== '') {
                                $type = $csv_type;
                            } elseif ($existing_id > 0 && $old_type !== '') {
                                $type = $old_type;
                            } else {
                                $type = 'Private Hospital';
                            }

                            if ($csv_type_bn !== '') {
                                $type_bn = $csv_type_bn;
                            } elseif ($existing_id > 0 && $csv_type === '' && $old_type_bn !== '') {
                                $type_bn = $old_type_bn;
                            } else {
                                $type_bn = hospital_import_type_bn($type);
                            }

                            if ($existing_id > 0 && $csv_slug === '' && !empty($existing['slug'])) {
                                $slug = (string)$existing['slug'];
                            } elseif ($csv_slug !== '') {
                                $slug = hospital_import_unique_slug($base_slug, $existing_id);
                            } else {
                                $slug = hospital_import_first_available_slug($slug_candidates, $existing_id);
                            }

                            $image_source = hospital_import_first_value($row, $map, ['image_url', 'image'], '');
                            $cover_source = hospital_import_first_value($row, $map, ['cover_image_url', 'cover_image'], '');

                            $new_image = hospital_import_store_image($image_source, 'hospitals', $name, 1400, 1400);
                            $new_cover_image = hospital_import_store_image($cover_source, 'hospitals', $name . '-cover', 1920, 1080);

                            $image = $new_image !== '' ? $new_image : hospital_import_clean($existing['image'] ?? '', 500);
                            $cover_image = $new_cover_image !== '' ? $new_cover_image : hospital_import_clean($existing['cover_image'] ?? '', 500);

                            $row_data = [
                                'name' => $name,
                                'name_bn' => $name_bn,
                                'slug' => $slug,
                                'type' => $type,
                                'type_bn' => $type_bn,
                                'phone' => hospital_import_clean(hospital_import_csv_value($row, $map, 'phone'), 100),
                                'email' => $email,
                                'address' => $address,
                                'address_bn' => $address_bn,
                                'division_id' => $location['div_id'],
                                'district_id' => $location['dist_id'],
                                'thana_id' => $location['sub_id'],
                                'area' => $area,
                                'area_bn' => $area_bn,
                                'road_no' => $road_no,
                                'road_no_bn' => $road_no_bn,
                                'house_no' => $house_no,
                                'house_no_bn' => $house_no_bn,
                                'post_code' => $post_code,
                                'map_url' => hospital_import_clean(hospital_import_csv_value($row, $map, 'map_url'), 1000),
                                'image' => $image,
                                'cover_image' => $cover_image,
                                'description' => hospital_import_text(hospital_import_csv_value($row, $map, 'description'), 8000),
                                'description_bn' => hospital_import_text(hospital_import_csv_value($row, $map, 'description_bn'), 8000),
                                'departments_count' => hospital_import_int(hospital_import_csv_value($row, $map, 'departments_count')),
                                'doctors_count' => hospital_import_int(hospital_import_csv_value($row, $map, 'doctors_count')),
                                'rating' => hospital_import_float(hospital_import_csv_value($row, $map, 'rating')),
                                'is_verified' => hospital_import_bool(hospital_import_csv_value($row, $map, 'is_verified')),
                                'is_featured' => hospital_import_bool(hospital_import_csv_value($row, $map, 'is_featured')),
                                'status' => hospital_import_status(hospital_import_csv_value($row, $map, 'status'), 'active'),
                                'seo_title' => hospital_import_clean(hospital_import_csv_value($row, $map, 'seo_title'), 255),
                                'seo_title_bn' => hospital_import_clean(hospital_import_csv_value($row, $map, 'seo_title_bn'), 255),
                                'seo_description' => hospital_import_text(hospital_import_csv_value($row, $map, 'seo_description'), 1500),
                                'seo_description_bn' => hospital_import_text(hospital_import_csv_value($row, $map, 'seo_description_bn'), 1500),
                                'website_url' => hospital_import_clean(hospital_import_csv_value($row, $map, 'website_url'), 1000),
                                'whatsapp' => hospital_import_clean(hospital_import_csv_value($row, $map, 'whatsapp'), 100),
                                'emergency_phone' => hospital_import_clean(hospital_import_csv_value($row, $map, 'emergency_phone'), 100),
                                'ambulance_phone' => hospital_import_clean(hospital_import_csv_value($row, $map, 'ambulance_phone'), 100),
                                'opening_hours' => hospital_import_clean(hospital_import_csv_value($row, $map, 'opening_hours'), 255),
                                'opening_hours_bn' => hospital_import_clean(hospital_import_csv_value($row, $map, 'opening_hours_bn'), 255),
                                'visiting_hours' => hospital_import_clean(hospital_import_csv_value($row, $map, 'visiting_hours'), 255),
                                'visiting_hours_bn' => hospital_import_clean(hospital_import_csv_value($row, $map, 'visiting_hours_bn'), 255),
                                'services' => hospital_import_text(hospital_import_csv_value($row, $map, 'services'), 8000),
                                'services_bn' => hospital_import_text(hospital_import_csv_value($row, $map, 'services_bn'), 8000),
                                'facilities' => hospital_import_text(hospital_import_csv_value($row, $map, 'facilities'), 8000),
                                'facilities_bn' => hospital_import_text(hospital_import_csv_value($row, $map, 'facilities_bn'), 8000),
                                'bed_count' => hospital_import_int(hospital_import_csv_value($row, $map, 'bed_count')),
                                'established_year' => hospital_import_int_or_null(hospital_import_csv_value($row, $map, 'established_year')),
                                'video_url' => hospital_import_clean(hospital_import_csv_value($row, $map, 'video_url'), 1000),
                                'license_number' => $license_number,
                                'meta_keywords' => hospital_import_text(hospital_import_csv_value($row, $map, 'meta_keywords'), 2000),
                                'meta_keywords_bn' => hospital_import_text(hospital_import_csv_value($row, $map, 'meta_keywords_bn'), 2000),
                            ];

                            if ($existing_id > 0 && !$replace_empty) {
                                foreach ($row_data as $column => $value) {
                                    if (
                                        $value === ''
                                        || $value === null
                                        || (in_array($column, ['departments_count', 'doctors_count', 'rating', 'bed_count'], true) && (string)$value === '0')
                                    ) {
                                        if (array_key_exists($column, $existing)) {
                                            $row_data[$column] = $existing[$column];
                                        }
                                    }
                                }
                            }

                            if ($existing_id > 0) {
                                $row_data['updated_at'] = '__NOW__';
                                hospital_import_update_dynamic($existing_id, $row_data);
                                $stats['updated']++;
                                $logs[] = "Line {$line}: updated hospital #{$existing_id} by {$duplicate_by} - {$name}.";
                            } else {
                                $row_data['created_at'] = '__NOW__';
                                $new_id = hospital_import_insert_dynamic($row_data);
                                $stats['created']++;
                                $logs[] = "Line {$line}: created hospital #{$new_id} - {$name}.";
                            }
                        } catch (Throwable $e) {
                            $stats['skipped']++;
                            $logs[] = "Line {$line}: error - " . $e->getMessage();
                        }
                    }

                    $message = 'Hospital import completed.';
                    $message_type = 'success';
                }
            }

            fclose($handle);
        }
    }

    $_SESSION['hospital_import_result'] = [
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
    :root {
        --gh-bg: #f6f8fa;
        --gh-canvas: #ffffff;
        --gh-border: #d0d7de;
        --gh-border-muted: #d8dee4;
        --gh-text: #24292f;
        --gh-muted: #57606a;
        --gh-blue: #0969da;
        --gh-green: #1a7f37;
        --gh-green-bg: #dafbe1;
        --gh-red-bg: #ffebe9;
        --gh-neutral: #f6f8fa;
        --gh-shadow: 0 1px 0 rgba(27,31,36,0.04);
    }

    body {
        background: var(--gh-bg);
    }

    .hospital-import-page,
    .hospital-import-page * {
        box-sizing: border-box;
    }

    .hospital-import-page {
        max-width: 1180px;
        margin: 0 auto;
        padding: 16px 16px 44px;
        color: var(--gh-text);
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Helvetica, Arial, sans-serif;
    }

    .hi-hero,
    .hi-card {
        background: var(--gh-canvas);
        border: 1px solid var(--gh-border);
        border-radius: 12px;
        box-shadow: var(--gh-shadow);
        margin-bottom: 16px;
        overflow: hidden;
    }

    .hi-hero {
        padding: 20px;
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 18px;
    }

    .hi-breadcrumb {
        display: flex;
        gap: 7px;
        align-items: center;
        color: var(--gh-muted);
        font-size: 13px;
        margin-bottom: 10px;
    }

    .hi-breadcrumb a {
        color: var(--gh-blue);
        text-decoration: none;
    }

    .hi-hero h1 {
        margin: 0;
        font-size: 30px;
        line-height: 1.18;
        letter-spacing: -0.03em;
    }

    .hi-hero p {
        margin: 8px 0 0;
        color: var(--gh-muted);
        line-height: 1.6;
        max-width: 770px;
        font-size: 14px;
    }

    .hi-actions {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        justify-content: flex-end;
    }

    .hi-btn {
        min-height: 36px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        padding: 7px 12px;
        border: 1px solid rgba(27,31,36,0.15);
        border-radius: 7px;
        background: #f6f8fa;
        color: var(--gh-text);
        text-decoration: none;
        cursor: pointer;
        font-size: 13.5px;
        font-weight: 600;
        line-height: 1.4;
        white-space: nowrap;
    }

    .hi-btn:hover {
        background: #eef1f4;
    }

    .hi-btn-primary {
        color: #fff;
        background: #2da44e;
        border-color: rgba(27,31,36,0.15);
    }

    .hi-btn-primary:hover {
        background: #1f883d;
    }

    .hi-card-header {
        padding: 15px 18px;
        background: var(--gh-neutral);
        border-bottom: 1px solid var(--gh-border);
    }

    .hi-card-header h2 {
        margin: 0;
        font-size: 18px;
        line-height: 1.35;
    }

    .hi-card-header p {
        margin: 5px 0 0;
        color: var(--gh-muted);
        font-size: 13px;
        line-height: 1.5;
    }

    .hi-card-body {
        padding: 18px;
    }

    .hi-upload-form {
        display: grid;
        grid-template-columns: 1fr auto;
        gap: 14px;
        align-items: end;
    }

    .hi-field {
        display: grid;
        gap: 7px;
    }

    .hi-field label {
        color: var(--gh-text);
        font-size: 13.5px;
        font-weight: 600;
    }

    .hi-field input[type="file"] {
        width: 100%;
        min-height: 40px;
        padding: 10px;
        border: 1px solid var(--gh-border);
        border-radius: 7px;
        background: #fff;
        color: var(--gh-text);
    }

    .hi-checks {
        display: grid;
        gap: 8px;
        margin-top: 12px;
    }

    .hi-checks label {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        color: var(--gh-muted);
        font-size: 13.5px;
        line-height: 1.4;
    }

    .hi-checks input {
        width: 15px;
        height: 15px;
        accent-color: var(--gh-green);
    }

    .hi-alert {
        display: flex;
        gap: 10px;
        align-items: flex-start;
        margin-bottom: 16px;
        padding: 12px 14px;
        border-radius: 10px;
        border: 1px solid var(--gh-border);
        font-size: 14px;
        line-height: 1.6;
    }

    .hi-alert.success {
        color: #116329;
        background: var(--gh-green-bg);
        border-color: #aceebb;
    }

    .hi-alert.error {
        color: #82071e;
        background: var(--gh-red-bg);
        border-color: #ff818266;
    }

    .hi-note {
        padding: 13px 14px;
        border: 1px solid var(--gh-border);
        border-radius: 10px;
        background: var(--gh-neutral);
        color: var(--gh-muted);
        font-size: 13.5px;
        line-height: 1.65;
    }

    .hi-note + .hi-note {
        margin-top: 12px;
    }

    .hi-note strong {
        color: var(--gh-text);
    }

    .hi-note code {
        display: inline-block;
        padding: 2px 6px;
        border: 1px solid var(--gh-border-muted);
        border-radius: 6px;
        background: #fff;
        color: var(--gh-text);
        font-family: ui-monospace, SFMono-Regular, Consolas, "Liberation Mono", Menlo, monospace;
        font-size: 12px;
    }

    .hi-stats {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 12px;
    }

    .hi-stat {
        padding: 14px;
        border: 1px solid var(--gh-border);
        border-radius: 10px;
        background: #fff;
    }

    .hi-stat strong {
        display: block;
        color: var(--gh-text);
        font-size: 24px;
        line-height: 1.2;
        letter-spacing: -0.02em;
    }

    .hi-stat span {
        display: block;
        margin-top: 3px;
        color: var(--gh-muted);
        font-size: 13px;
    }

    .hi-log {
        max-height: 360px;
        overflow: auto;
        margin: 0;
        padding: 0;
        list-style: none;
        border: 1px solid var(--gh-border);
        border-radius: 10px;
        background: #fff;
    }

    .hi-log li {
        padding: 10px 12px;
        border-bottom: 1px solid var(--gh-border-muted);
        color: var(--gh-muted);
        font-size: 13px;
        line-height: 1.55;
    }

    .hi-log li:last-child {
        border-bottom: 0;
    }

    @media (max-width: 860px) {
        .hi-hero,
        .hi-upload-form {
            grid-template-columns: 1fr;
            flex-direction: column;
            align-items: stretch;
        }

        .hi-actions {
            justify-content: flex-start;
        }

        .hi-stats {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 520px) {
        .hi-hero h1 {
            font-size: 25px;
        }

        .hi-stats {
            grid-template-columns: 1fr;
        }

        .hi-btn {
            width: 100%;
        }
    }
</style>

<div class="hospital-import-page">
    <div class="hi-hero">
        <div>
            <div class="hi-breadcrumb">
                <a href="dashboard.php">Dashboard</a>
                <span>/</span>
                <a href="hospitals.php">Hospitals</a>
                <span>/</span>
                <span>Import</span>
            </div>

            <h1>Import Hospitals</h1>
            <p>
                Upload a CSV file to import hospitals using the same fields as your hospital form.
                Location names are used only to match IDs from divisions, districts and thanas.
            </p>
        </div>

        <div class="hi-actions">
            <a href="hospitals.php" class="hi-btn">← Back to Hospitals</a>
            <a href="?download_sample=1" class="hi-btn hi-btn-primary">Download Sample CSV</a>
        </div>
    </div>

    <?php if ($import_message !== ''): ?>
        <div class="hi-alert <?= hospital_import_e($import_message_type) ?>">
            <strong><?= $import_message_type === 'success' ? 'Success:' : 'Error:' ?></strong>
            <span><?= hospital_import_e($import_message) ?></span>
        </div>
    <?php endif; ?>

    <div class="hi-card">
        <div class="hi-card-header">
            <h2>Upload CSV</h2>
            <p>Use the sample file format. Only the name column is required.</p>
        </div>

        <div class="hi-card-body">
            <form method="POST" enctype="multipart/form-data" class="hi-upload-form">
                <div>
                    <div class="hi-field">
                        <label>Select CSV File</label>
                        <input type="file" name="csv_file" accept=".csv,text/csv" required>
                    </div>

                    <div class="hi-checks">
                        <label>
                            <input type="checkbox" name="update_existing" value="1" checked>
                            Update existing hospital if duplicate is found
                        </label>

                        <label>
                            <input type="checkbox" name="replace_empty" value="1">
                            Replace old values with empty CSV values
                        </label>
                    </div>
                </div>

                <button type="submit" class="hi-btn hi-btn-primary">Import Hospitals</button>
            </form>

            <div class="hi-note" style="margin-top:14px;">
                <strong>Duplicate Rules:</strong><br>
                Check order:
                <code>license_number</code> →
                <code>email</code> →
                <code>slug</code> →
                <code>name + district_id + thana_id</code> →
                <code>name + district_id</code><br>
                If thana is matched, duplicate check follows district and thana together. If thana is not matched, it falls back to district.
            </div>

            <div class="hi-note">
                <strong>Slug Rules:</strong><br>
                If CSV slug is empty, importer tries:
                <code>Hospital Name</code> →
                <code>Hospital Name + District</code> →
                <code>Hospital Name + District + Thana</code>.
                If all are already taken, it adds <code>-2</code>, <code>-3</code> automatically.
            </div>

            <div class="hi-note">
                <strong>Update Date:</strong><br>
                New hospital gets <code>created_at</code>. Existing hospital update gets <code>updated_at</code>.
            </div>

            <div class="hi-note">
                <strong>Image system:</strong><br>
                Remote image URL columns: <code>image_url</code>, <code>cover_image_url</code><br>
                Local path columns: <code>image</code>, <code>cover_image</code><br>
                Upload folder: <code><?= hospital_import_e(hospital_import_upload_base_path() . '/hospitals') ?></code>
            </div>
        </div>
    </div>

    <?php if (!empty($import_stats['processed']) || !empty($import_logs)): ?>
        <div class="hi-card">
            <div class="hi-card-header">
                <h2>Import Summary</h2>
                <p>Last import result.</p>
            </div>

            <div class="hi-card-body">
                <div class="hi-stats">
                    <?php foreach ($import_stats as $label => $value): ?>
                        <div class="hi-stat">
                            <strong><?= hospital_import_e((string)$value) ?></strong>
                            <span><?= hospital_import_e(ucwords(str_replace('_', ' ', $label))) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="hi-card">
            <div class="hi-card-header">
                <h2>Import Log</h2>
                <p>Created, updated, skipped and image upload details.</p>
            </div>

            <div class="hi-card-body">
                <?php if (!empty($import_logs)): ?>
                    <ul class="hi-log">
                        <?php foreach ($import_logs as $log): ?>
                            <li><?= hospital_import_e($log) ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <div class="hi-note">No import log found.</div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>