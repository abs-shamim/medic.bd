<?php
require_once __DIR__ . '/../includes/functions.php';
require_admin();

if (!defined('DOCTOR_FORM_MAIN_CONTEXT')) {
    define('DOCTOR_FORM_MAIN_CONTEXT', true);
}

/*
|--------------------------------------------------------------------------
| Load doctor form section files
|--------------------------------------------------------------------------
*/
$doctor_section_files = [
    'hospitals-availability.php',
    'basic-information.php',
    'contact-location.php',
    'professional-details.php',
    'status-display-options.php',
    'image-biography.php',
    'clinical-profile.php',
    'seo-information.php',
];

foreach ($doctor_section_files as $section_file) {
    $section_path = __DIR__ . '/doctors/' . $section_file;

    if (file_exists($section_path)) {
        require_once $section_path;
    }
}

/*
|--------------------------------------------------------------------------
| Common helpers
|--------------------------------------------------------------------------
*/
if (!function_exists('doctor_form_redirect_self')) {
    function doctor_form_redirect_self(array $query = []): void
    {
        $url = basename($_SERVER['PHP_SELF']);

        if (!empty($query)) {
            $url .= '?' . http_build_query($query);
        }

        redirect($url);
        exit;
    }
}

if (!function_exists('doctor_form_has_pdo')) {
    function doctor_form_has_pdo(): bool
    {
        global $pdo;
        return isset($pdo) && $pdo instanceof PDO;
    }
}

if (!function_exists('doctor_form_safe_identifier')) {
    function doctor_form_safe_identifier(string $name): string
    {
        return preg_match('/^[A-Za-z0-9_]+$/', $name) ? $name : '';
    }
}

if (!function_exists('doctor_form_get_doctor')) {
    function doctor_form_get_doctor(int $id): ?array
    {
        global $pdo;

        if ($id <= 0 || !doctor_form_has_pdo()) {
            return null;
        }

        try {
            $stmt = $pdo->prepare("SELECT * FROM doctors WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => $id]);
            $doctor = $stmt->fetch(PDO::FETCH_ASSOC);

            return $doctor ?: null;
        } catch (Throwable $e) {
            return null;
        }
    }
}

if (!function_exists('doctor_form_table_exists')) {
    function doctor_form_table_exists(string $table): bool
    {
        global $pdo;

        if (function_exists('doctor_component_table_exists')) {
            return doctor_component_table_exists($table);
        }

        if (!doctor_form_has_pdo() || doctor_form_safe_identifier($table) === '') {
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

if (!function_exists('doctor_form_column_exists')) {
    function doctor_form_column_exists(string $table, string $column): bool
    {
        global $pdo;

        static $cache = [];

        $cache_key = $table . '.' . $column;

        if (array_key_exists($cache_key, $cache)) {
            return $cache[$cache_key];
        }

        if (function_exists('doctor_component_column_exists')) {
            $cache[$cache_key] = doctor_component_column_exists($table, $column);
            return $cache[$cache_key];
        }

        if (
            !doctor_form_has_pdo() ||
            doctor_form_safe_identifier($table) === '' ||
            doctor_form_safe_identifier($column) === ''
        ) {
            $cache[$cache_key] = false;
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

            $cache[$cache_key] = (int)$stmt->fetchColumn() > 0;
            return $cache[$cache_key];
        } catch (Throwable $e) {
            $cache[$cache_key] = false;
            return false;
        }
    }
}

if (!function_exists('doctor_form_add_column_if_missing')) {
    function doctor_form_add_column_if_missing(string $table, string $column, string $definition): void
    {
        global $pdo;

        if (function_exists('doctor_component_add_column_if_missing')) {
            doctor_component_add_column_if_missing($table, $column, $definition);
            return;
        }

        $safe_table = doctor_form_safe_identifier($table);
        $safe_column = doctor_form_safe_identifier($column);

        if (
            $safe_table === '' ||
            $safe_column === '' ||
            !doctor_form_has_pdo() ||
            !doctor_form_table_exists($table) ||
            doctor_form_column_exists($table, $column)
        ) {
            return;
        }

        try {
            $pdo->exec("ALTER TABLE `{$safe_table}` ADD COLUMN `{$safe_column}` {$definition}");
        } catch (Throwable $e) {
            // Column boot should not break page loading.
        }
    }
}

if (!function_exists('doctor_form_required_columns_boot')) {
    function doctor_form_required_columns_boot(): void
    {
        global $pdo;

        if (!doctor_form_has_pdo()) {
            return;
        }

        if (!doctor_form_table_exists('doctors')) {
            try {
                $pdo->exec("
                    CREATE TABLE doctors (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        name VARCHAR(255) NULL,
                        slug VARCHAR(255) NULL,
                        specialty_id INT DEFAULT 0,
                        hospital_id INT DEFAULT 0,
                        status VARCHAR(30) DEFAULT 'active',
                        created_at DATETIME NULL,
                        updated_at DATETIME NULL,
                        INDEX slug_idx (slug),
                        INDEX specialty_idx (specialty_id),
                        INDEX hospital_idx (hospital_id),
                        INDEX status_idx (status)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                ");
            } catch (Throwable $e) {
                return;
            }
        }

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

            'experience_years' => "INT NULL",
            'consultation_fee' => "DECIMAL(10,2) DEFAULT 0",
            'follow_up_fee' => "DECIMAL(10,2) DEFAULT 0",
            'video_consultation_fee' => "DECIMAL(10,2) DEFAULT 0",

            'phone' => "VARCHAR(80) NULL",
            'whatsapp' => "VARCHAR(80) NULL",
            'email' => "VARCHAR(150) NULL",

            'doctor_division_id' => "INT DEFAULT 0",
            'doctor_district_id' => "INT DEFAULT 0",
            'doctor_thana_id' => "INT DEFAULT 0",

            'doctor_location_source' => "VARCHAR(30) DEFAULT 'auto'",
            'doctor_location_auto_chamber_id' => "INT DEFAULT 0",

            'image' => "VARCHAR(255) NULL",
            'og_image' => "VARCHAR(255) NULL",

            'bio' => "TEXT NULL",
            'bio_bn' => "TEXT NULL",
            'education' => "TEXT NULL",
            'education_bn' => "TEXT NULL",
            'training' => "TEXT NULL",
            'training_bn' => "TEXT NULL",
            'fellowship' => "TEXT NULL",
            'fellowship_bn' => "TEXT NULL",
            'expertise' => "TEXT NULL",
            'expertise_bn' => "TEXT NULL",
            'appointment_note' => "TEXT NULL",
            'appointment_note_bn' => "TEXT NULL",

            'rating' => "DECIMAL(3,1) DEFAULT 0",
            'reviews_count' => "INT DEFAULT 0",

            'is_verified' => "TINYINT(1) DEFAULT 0",
            'is_featured' => "TINYINT(1) DEFAULT 0",
            'featured_days' => "INT DEFAULT 0",
            'featured_started_at' => "DATE NULL",
            'featured_until' => "DATE NULL",
            'featured_position' => "INT DEFAULT 0",
            'featured_on_hold' => "TINYINT(1) DEFAULT 0",
            'featured_hold_remaining_days' => "INT DEFAULT 0",
            'featured_hold_started_at' => "DATE NULL",

            'emergency_available' => "TINYINT(1) DEFAULT 0",
            'online_consultation' => "TINYINT(1) DEFAULT 0",
            'home_visit' => "TINYINT(1) DEFAULT 0",

            'status' => "VARCHAR(30) DEFAULT 'active'",

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

            'created_at' => "DATETIME NULL",
            'updated_at' => "DATETIME NULL",
        ];

        foreach ($columns as $column => $definition) {
            doctor_form_add_column_if_missing('doctors', $column, $definition);
        }
    }
}

doctor_form_required_columns_boot();

if (!function_exists('doctor_form_text')) {
    function doctor_form_text($value): string
    {
        return trim(preg_replace('/\s+/', ' ', (string)$value));
    }
}

if (!function_exists('doctor_form_first_value')) {
    function doctor_form_first_value(array $source, array $keys): string
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $source)) {
                $value = doctor_form_text($source[$key]);

                if ($value !== '') {
                    return $value;
                }
            }
        }

        return '';
    }
}

if (!function_exists('doctor_form_join_unique')) {
    function doctor_form_join_unique(array $items, string $separator = ', '): string
    {
        $clean = [];

        foreach ($items as $item) {
            $item = doctor_form_text($item);

            if ($item === '') {
                continue;
            }

            $key = function_exists('mb_strtolower') ? mb_strtolower($item) : strtolower($item);

            if (!isset($clean[$key])) {
                $clean[$key] = $item;
            }
        }

        return implode($separator, array_values($clean));
    }
}

if (!function_exists('doctor_form_strlen')) {
    function doctor_form_strlen(string $text): int
    {
        return function_exists('mb_strlen') ? mb_strlen($text) : strlen($text);
    }
}

if (!function_exists('doctor_form_substr')) {
    function doctor_form_substr(string $text, int $start, int $length): string
    {
        return function_exists('mb_substr') ? mb_substr($text, $start, $length) : substr($text, $start, $length);
    }
}

if (!function_exists('doctor_form_limit_text')) {
    function doctor_form_limit_text(string $text, int $limit): string
    {
        $text = doctor_form_text($text);

        if ($limit <= 0 || doctor_form_strlen($text) <= $limit) {
            return $text;
        }

        return rtrim(doctor_form_substr($text, 0, $limit - 1)) . '…';
    }
}

/*
|--------------------------------------------------------------------------
| Dynamic save helpers
|--------------------------------------------------------------------------
*/
if (!function_exists('doctor_form_pick_existing_columns')) {
    function doctor_form_pick_existing_columns(string $table, array $fields): array
    {
        $existing = [];

        foreach ($fields as $column => $value) {
            if (doctor_form_column_exists($table, $column)) {
                $existing[$column] = $value;
            }
        }

        return $existing;
    }
}

if (!function_exists('doctor_form_insert_dynamic')) {
    function doctor_form_insert_dynamic(string $table, array $fields): int
    {
        global $pdo;

        $safe_table = doctor_form_safe_identifier($table);

        if ($safe_table === '' || !doctor_form_has_pdo()) {
            throw new RuntimeException('Invalid table or database connection.');
        }

        $fields = doctor_form_pick_existing_columns($table, $fields);

        if (empty($fields)) {
            throw new RuntimeException('No valid columns found for insert.');
        }

        $quoted_columns = [];
        $placeholders = [];
        $data = [];

        foreach ($fields as $column => $value) {
            $safe_column = doctor_form_safe_identifier($column);

            if ($safe_column === '') {
                continue;
            }

            $quoted_columns[] = "`{$safe_column}`";
            $placeholders[] = ':' . $safe_column;
            $data[':' . $safe_column] = $value;
        }

        if (empty($quoted_columns)) {
            throw new RuntimeException('No valid columns found for insert.');
        }

        $sql = "
            INSERT INTO `{$safe_table}`
            (" . implode(', ', $quoted_columns) . ")
            VALUES
            (" . implode(', ', $placeholders) . ")
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($data);

        return (int)$pdo->lastInsertId();
    }
}

if (!function_exists('doctor_form_update_dynamic')) {
    function doctor_form_update_dynamic(string $table, array $fields, int $id): void
    {
        global $pdo;

        $safe_table = doctor_form_safe_identifier($table);

        if ($safe_table === '' || !doctor_form_has_pdo()) {
            throw new RuntimeException('Invalid table or database connection.');
        }

        $fields = doctor_form_pick_existing_columns($table, $fields);

        if (empty($fields)) {
            throw new RuntimeException('No valid columns found for update.');
        }

        $set_parts = [];
        $data = [':id' => $id];

        foreach ($fields as $column => $value) {
            $safe_column = doctor_form_safe_identifier($column);

            if ($safe_column === '') {
                continue;
            }

            $set_parts[] = "`{$safe_column}` = :{$safe_column}";
            $data[':' . $safe_column] = $value;
        }

        if (empty($set_parts)) {
            throw new RuntimeException('No valid columns found for update.');
        }

        $sql = "
            UPDATE `{$safe_table}`
            SET " . implode(",\n                ", $set_parts) . "
            WHERE id = :id
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($data);
    }
}

/*
|--------------------------------------------------------------------------
| SEO helpers
|--------------------------------------------------------------------------
*/
if (!function_exists('doctor_form_db_name_pair')) {
    function doctor_form_db_name_pair(string $table, int $id): array
    {
        global $pdo;

        $result = [
            'en' => '',
            'bn' => '',
        ];

        $safe_table = doctor_form_safe_identifier($table);

        if ($id <= 0 || $safe_table === '' || !doctor_form_table_exists($table)) {
            return $result;
        }

        $select = ['id'];

        if (doctor_form_column_exists($table, 'name')) {
            $select[] = 'name';
        }

        if (doctor_form_column_exists($table, 'name_en')) {
            $select[] = 'name_en';
        }

        if (doctor_form_column_exists($table, 'name_bn')) {
            $select[] = 'name_bn';
        }

        if (count($select) <= 1) {
            return $result;
        }

        try {
            $stmt = $pdo->prepare("
                SELECT " . implode(', ', $select) . "
                FROM `{$safe_table}`
                WHERE id = :id
                LIMIT 1
            ");
            $stmt->execute([':id' => $id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                return $result;
            }

            $result['en'] = doctor_form_first_value($row, ['name_en', 'name']);
            $result['bn'] = doctor_form_first_value($row, ['name_bn']);

            return $result;
        } catch (Throwable $e) {
            return $result;
        }
    }
}

if (!function_exists('doctor_form_generate_auto_seo')) {
    function doctor_form_generate_auto_seo(array $source): array
    {
        $specialty_pair = doctor_form_db_name_pair(
            'specialties',
            (int)($source['specialty_id'] ?? 0)
        );

        $district_pair = doctor_form_db_name_pair(
            'districts',
            (int)($source['doctor_district_id'] ?? ($source['district_id'] ?? 0))
        );

        $name = doctor_form_first_value($source, ['name', 'doctor_name', 'name_en']);

        $specialty = doctor_form_first_value($source, [
            'specialty_name',
            'specialty',
            'doctor_specialty',
        ]);

        if ($specialty_pair['en'] !== '') {
            $specialty = $specialty_pair['en'];
        }

        $district = doctor_form_first_value($source, [
            'doctor_district_name',
            'doctor_district',
            'district_name',
            'district',
        ]);

        if ($district_pair['en'] !== '') {
            $district = $district_pair['en'];
        }

        $name_bn = doctor_form_first_value($source, ['name_bn', 'doctor_name_bn']);

        $specialty_bn = doctor_form_first_value($source, [
            'specialty_name_bn',
            'specialty_bn',
            'doctor_specialty_bn',
        ]);

        if ($specialty_pair['bn'] !== '') {
            $specialty_bn = $specialty_pair['bn'];
        }

        $district_bn = doctor_form_first_value($source, [
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

        /*
         * Strong SEO title rule:
         * Doctor Name - Specialty - District
         */
        $seo_title = doctor_form_join_unique([$name, $specialty, $district], ' - ');
        $seo_title_bn = doctor_form_join_unique([$name_bn, $specialty_bn, $district_bn], ' - ');

        $seo_description = '';

        if ($name !== '' || $specialty !== '' || $district !== '') {
            $seo_description = doctor_form_text(
                doctor_form_join_unique([$name, $specialty, $district], ', ') .
                '. View doctor profile, specialty, district, hospital availability, appointment information, consultation fee and schedule.'
            );
        }

        $seo_description_bn = '';

        if ($name_bn !== '' || $specialty_bn !== '' || $district_bn !== '') {
            $seo_description_bn = doctor_form_text(
                doctor_form_join_unique([$name_bn, $specialty_bn, $district_bn], ', ') .
                ' এর ডাক্তার প্রোফাইল, বিশেষজ্ঞতা, জেলা, হাসপাতাল, অ্যাপয়েন্টমেন্ট তথ্য, কনসালটেশন ফি ও সময়সূচি দেখুন।'
            );
        }

        $meta_keywords = doctor_form_join_unique([
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

        $meta_keywords_bn = doctor_form_join_unique([
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
            'seo_title' => doctor_form_limit_text($seo_title, 160),
            'seo_description' => doctor_form_limit_text($seo_description, 300),
            'meta_keywords' => doctor_form_limit_text($meta_keywords, 255),
            'seo_title_bn' => doctor_form_limit_text($seo_title_bn, 160),
            'seo_description_bn' => doctor_form_limit_text($seo_description_bn, 300),
            'meta_keywords_bn' => doctor_form_limit_text($meta_keywords_bn, 255),
        ];
    }
}

if (!function_exists('doctor_form_clean_seo_mode')) {
    function doctor_form_clean_seo_mode($mode): string
    {
        return trim((string)$mode) === 'manual' ? 'manual' : 'auto';
    }
}

if (!function_exists('doctor_form_seo_value_by_mode')) {
    function doctor_form_seo_value_by_mode($posted_value, $posted_mode, $new_auto_value): string
    {
        $posted_value = doctor_form_text($posted_value);
        $posted_mode = doctor_form_clean_seo_mode($posted_mode);
        $new_auto_value = doctor_form_text($new_auto_value);

        /*
         * If field is manual and has a value, keep only this field manual.
         * If manual field is empty, switch it back to auto value.
         */
        if ($posted_mode === 'manual' && $posted_value !== '') {
            return $posted_value;
        }

        return $new_auto_value;
    }
}

/*
|--------------------------------------------------------------------------
| Status / featured helpers
|--------------------------------------------------------------------------
*/
if (!function_exists('doctor_form_get_auto_rating_stats')) {
    function doctor_form_get_auto_rating_stats(int $doctor_id): array
    {
        if (function_exists('doctor_status_display_options_get_auto_rating_stats')) {
            return doctor_status_display_options_get_auto_rating_stats($doctor_id);
        }

        return [
            'rating' => 0,
            'reviews_count' => 0,
            'available' => false,
        ];
    }
}

if (!function_exists('doctor_form_normalize_gender')) {
    function doctor_form_normalize_gender($value): string
    {
        if (function_exists('doctor_basic_information_normalize_gender')) {
            return doctor_basic_information_normalize_gender($value);
        }

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

if (!function_exists('doctor_form_calculate_featured_until')) {
    function doctor_form_calculate_featured_until(int $days, ?string $started_at = null): ?string
    {
        if (function_exists('doctor_status_display_options_calculate_featured_until')) {
            return doctor_status_display_options_calculate_featured_until($days, $started_at);
        }

        if ($days <= 0) {
            return null;
        }

        $start = $started_at ?: date('Y-m-d');
        $timestamp = strtotime($start . ' +' . $days . ' days');

        return $timestamp ? date('Y-m-d', $timestamp) : null;
    }
}

if (!function_exists('doctor_form_featured_remaining_days')) {
    function doctor_form_featured_remaining_days(string $featured_until): int
    {
        if (function_exists('doctor_status_display_options_remaining_days')) {
            return doctor_status_display_options_remaining_days($featured_until);
        }

        $featured_until = trim($featured_until);

        if ($featured_until === '') {
            return 0;
        }

        try {
            $today = new DateTime(date('Y-m-d'));
            $end_date = new DateTime($featured_until);

            if ($end_date < $today) {
                return 0;
            }

            return (int)$today->diff($end_date)->days;
        } catch (Throwable $e) {
            return 0;
        }
    }
}

if (!function_exists('doctor_form_featured_history_boot')) {
    function doctor_form_featured_history_boot(): void
    {
        global $pdo;

        if (!doctor_form_has_pdo()) {
            return;
        }

        if (!doctor_form_table_exists('doctor_featured_history')) {
            try {
                $pdo->exec("
                    CREATE TABLE doctor_featured_history (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        doctor_id INT NOT NULL DEFAULT 0,
                        featured_days INT DEFAULT 0,
                        featured_started_at DATE NULL,
                        featured_until DATE NULL,
                        featured_position INT DEFAULT 0,
                        remaining_days INT DEFAULT 0,
                        status VARCHAR(30) DEFAULT 'active',
                        created_at DATETIME NULL,
                        INDEX doctor_id_idx (doctor_id),
                        INDEX status_idx (status),
                        INDEX featured_until_idx (featured_until)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                ");
            } catch (Throwable $e) {
                return;
            }
        }

        foreach ([
            'doctor_id' => "INT NOT NULL DEFAULT 0",
            'featured_days' => "INT DEFAULT 0",
            'featured_started_at' => "DATE NULL",
            'featured_until' => "DATE NULL",
            'featured_position' => "INT DEFAULT 0",
            'remaining_days' => "INT DEFAULT 0",
            'status' => "VARCHAR(30) DEFAULT 'active'",
            'created_at' => "DATETIME NULL",
        ] as $column => $definition) {
            doctor_form_add_column_if_missing('doctor_featured_history', $column, $definition);
        }
    }
}

doctor_form_featured_history_boot();

if (!function_exists('doctor_form_insert_featured_history')) {
    function doctor_form_insert_featured_history(
        int $doctor_id,
        int $featured_days,
        string $started_at,
        string $featured_until,
        int $featured_position,
        int $remaining_days,
        string $status
    ): void {
        global $pdo;

        if ($doctor_id <= 0 || !doctor_form_table_exists('doctor_featured_history')) {
            return;
        }

        try {
            $stmt = $pdo->prepare("
                INSERT INTO doctor_featured_history
                (
                    doctor_id,
                    featured_days,
                    featured_started_at,
                    featured_until,
                    featured_position,
                    remaining_days,
                    status,
                    created_at
                )
                VALUES
                (
                    :doctor_id,
                    :featured_days,
                    :featured_started_at,
                    :featured_until,
                    :featured_position,
                    :remaining_days,
                    :status,
                    NOW()
                )
            ");

            $stmt->execute([
                ':doctor_id' => $doctor_id,
                ':featured_days' => $featured_days,
                ':featured_started_at' => $started_at,
                ':featured_until' => $featured_until,
                ':featured_position' => $featured_position,
                ':remaining_days' => $remaining_days,
                ':status' => $status,
            ]);
        } catch (Throwable $e) {
            // History insert should not break saving.
        }
    }
}

if (!function_exists('doctor_form_handle_featured_popup_post')) {
    function doctor_form_handle_featured_popup_post(int $doctor_id, ?array $doctor): void
    {
        global $pdo;

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        $action = trim((string)($_POST['featured_popup_action'] ?? ''));

        if ($action === '') {
            return;
        }

        if ($doctor_id <= 0 || !$doctor) {
            doctor_form_redirect_self([
                'error' => 'Please save the doctor first, then use Featured Profile options.',
            ]);
        }

        $today = date('Y-m-d');

        $old_days = (int)($doctor['featured_days'] ?? 0);
        $old_position = (int)($doctor['featured_position'] ?? 0);
        $old_until = trim((string)($doctor['featured_until'] ?? ''));
        $old_hold_remaining = (int)($doctor['featured_hold_remaining_days'] ?? 0);

        $posted_days = (int)($_POST['featured_days'] ?? 0);
        $posted_position = (int)($_POST['featured_position'] ?? 0);

        if ($action === 'activate') {
            if ($posted_days <= 0) {
                doctor_form_redirect_self([
                    'id' => $doctor_id,
                    'error' => 'Please select Featured Active Duration.',
                ]);
            }

            $started_at = $today;
            $featured_until = doctor_form_calculate_featured_until($posted_days, $started_at);

            if (!$featured_until) {
                doctor_form_redirect_self([
                    'id' => $doctor_id,
                    'error' => 'Featured date could not be generated.',
                ]);
            }

            try {
                $stmt = $pdo->prepare("
                    UPDATE doctors
                    SET
                        is_featured = 1,
                        featured_days = :featured_days,
                        featured_started_at = :featured_started_at,
                        featured_until = :featured_until,
                        featured_position = :featured_position,
                        featured_on_hold = 0,
                        featured_hold_remaining_days = 0,
                        featured_hold_started_at = NULL,
                        updated_at = NOW()
                    WHERE id = :id
                ");

                $stmt->execute([
                    ':featured_days' => $posted_days,
                    ':featured_started_at' => $started_at,
                    ':featured_until' => $featured_until,
                    ':featured_position' => $posted_position,
                    ':id' => $doctor_id,
                ]);

                if (doctor_form_table_exists('doctor_featured_history')) {
                    $stmt = $pdo->prepare("
                        UPDATE doctor_featured_history
                        SET status = 'inactive'
                        WHERE doctor_id = :doctor_id
                          AND status IN ('active', 'hold')
                    ");
                    $stmt->execute([':doctor_id' => $doctor_id]);
                }

                doctor_form_insert_featured_history(
                    $doctor_id,
                    $posted_days,
                    $started_at,
                    $featured_until,
                    $posted_position,
                    $posted_days,
                    'active'
                );

                doctor_form_redirect_self([
                    'id' => $doctor_id,
                    'saved' => 'featured_saved',
                ]);
            } catch (Throwable $e) {
                doctor_form_redirect_self([
                    'id' => $doctor_id,
                    'error' => 'Featured Profile could not be saved.',
                ]);
            }
        }

        if ($action === 'hold' || $action === 'stop') {
            $remaining_days = doctor_form_featured_remaining_days($old_until);

            if ($remaining_days <= 0) {
                $remaining_days = max(0, $old_hold_remaining);
            }

            try {
                if ($remaining_days > 0) {
                    $stmt = $pdo->prepare("
                        UPDATE doctors
                        SET
                            is_featured = 0,
                            featured_on_hold = 1,
                            featured_hold_remaining_days = :remaining_days,
                            featured_hold_started_at = :hold_started_at,
                            updated_at = NOW()
                        WHERE id = :id
                    ");

                    $stmt->execute([
                        ':remaining_days' => $remaining_days,
                        ':hold_started_at' => $today,
                        ':id' => $doctor_id,
                    ]);

                    if (doctor_form_table_exists('doctor_featured_history')) {
                        $stmt = $pdo->prepare("
                            UPDATE doctor_featured_history
                            SET
                                status = 'hold',
                                remaining_days = :remaining_days
                            WHERE doctor_id = :doctor_id
                              AND status IN ('active', 'hold')
                        ");

                        $stmt->execute([
                            ':remaining_days' => $remaining_days,
                            ':doctor_id' => $doctor_id,
                        ]);
                    }

                    doctor_form_redirect_self([
                        'id' => $doctor_id,
                        'saved' => 'featured_hold',
                    ]);
                }

                $stmt = $pdo->prepare("
                    UPDATE doctors
                    SET
                        is_featured = 0,
                        featured_on_hold = 0,
                        featured_hold_remaining_days = 0,
                        featured_hold_started_at = NULL,
                        updated_at = NOW()
                    WHERE id = :id
                ");

                $stmt->execute([':id' => $doctor_id]);

                if (doctor_form_table_exists('doctor_featured_history')) {
                    $stmt = $pdo->prepare("
                        UPDATE doctor_featured_history
                        SET
                            status = 'inactive',
                            remaining_days = 0
                        WHERE doctor_id = :doctor_id
                          AND status IN ('active', 'hold')
                    ");

                    $stmt->execute([':doctor_id' => $doctor_id]);
                }

                doctor_form_redirect_self([
                    'id' => $doctor_id,
                    'saved' => 'featured_stopped',
                ]);
            } catch (Throwable $e) {
                doctor_form_redirect_self([
                    'id' => $doctor_id,
                    'error' => 'Featured Profile could not be updated.',
                ]);
            }
        }

        if ($action === 'resume') {
            $remaining_days = max(0, $old_hold_remaining);

            if ($remaining_days <= 0) {
                doctor_form_redirect_self([
                    'id' => $doctor_id,
                    'error' => 'No remaining featured days found to resume.',
                ]);
            }

            $days_for_db = $old_days > 0 ? $old_days : $remaining_days;
            $position_for_db = $posted_position >= 0 ? $posted_position : $old_position;
            $started_at = $today;
            $featured_until = doctor_form_calculate_featured_until($remaining_days, $started_at);

            if (!$featured_until) {
                doctor_form_redirect_self([
                    'id' => $doctor_id,
                    'error' => 'Featured resume date could not be generated.',
                ]);
            }

            try {
                $stmt = $pdo->prepare("
                    UPDATE doctors
                    SET
                        is_featured = 1,
                        featured_days = :featured_days,
                        featured_started_at = :featured_started_at,
                        featured_until = :featured_until,
                        featured_position = :featured_position,
                        featured_on_hold = 0,
                        featured_hold_remaining_days = 0,
                        featured_hold_started_at = NULL,
                        updated_at = NOW()
                    WHERE id = :id
                ");

                $stmt->execute([
                    ':featured_days' => $days_for_db,
                    ':featured_started_at' => $started_at,
                    ':featured_until' => $featured_until,
                    ':featured_position' => $position_for_db,
                    ':id' => $doctor_id,
                ]);

                if (doctor_form_table_exists('doctor_featured_history')) {
                    $stmt = $pdo->prepare("
                        UPDATE doctor_featured_history
                        SET status = 'inactive'
                        WHERE doctor_id = :doctor_id
                          AND status = 'hold'
                    ");

                    $stmt->execute([':doctor_id' => $doctor_id]);
                }

                doctor_form_insert_featured_history(
                    $doctor_id,
                    $days_for_db,
                    $started_at,
                    $featured_until,
                    $position_for_db,
                    $remaining_days,
                    'active'
                );

                doctor_form_redirect_self([
                    'id' => $doctor_id,
                    'saved' => 'featured_resumed',
                ]);
            } catch (Throwable $e) {
                doctor_form_redirect_self([
                    'id' => $doctor_id,
                    'error' => 'Featured Profile could not be resumed.',
                ]);
            }
        }
    }
}

/*
|--------------------------------------------------------------------------
| Main state
|--------------------------------------------------------------------------
*/
$id = (int)($_POST['doctor_id'] ?? ($_GET['id'] ?? 0));
$doctor = doctor_form_get_doctor($id);

doctor_form_handle_featured_popup_post($id, $doctor);

if (function_exists('doctor_hospitals_availability_handle_post')) {
    doctor_hospitals_availability_handle_post($id, $doctor);
}

/*
|--------------------------------------------------------------------------
| Slug save
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_action'] ?? '') === 'save_slug') {
    if (!$id || !$doctor) {
        doctor_form_redirect_self(['error' => 'Please save the doctor first, then update the slug.']);
    }

    $manual_slug = trim((string)($_POST['slug'] ?? ''));

    if ($manual_slug === '') {
        doctor_form_redirect_self(['id' => $id, 'error' => 'Slug cannot be empty.']);
    }

    try {
        $doctor_slug = make_unique_slug('doctors', slugify($manual_slug), $id);

        $fields = [
            'slug' => $doctor_slug,
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        doctor_form_update_dynamic('doctors', $fields, $id);

        doctor_form_redirect_self([
            'id' => $id,
            'saved' => 'slug',
        ]);
    } catch (Throwable $e) {
        doctor_form_redirect_self([
            'id' => $id,
            'error' => 'Slug could not be saved. Error: ' . $e->getMessage(),
        ]);
    }
}

/*
|--------------------------------------------------------------------------
| Doctor save
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (($_POST['form_action'] ?? '') === '' || ($_POST['form_action'] ?? '') === 'save_doctor')) {
    $specialty_id = (int)($_POST['specialty_id'] ?? 0);
    $submitted_gender = doctor_form_normalize_gender($_POST['gender'] ?? '');

    $doctor_division_id = (int)($_POST['doctor_division_id'] ?? 0);
    $doctor_district_id = (int)($_POST['doctor_district_id'] ?? 0);
    $doctor_thana_id = (int)($_POST['doctor_thana_id'] ?? 0);

    $required_errors = [];

    if (trim((string)($_POST['name'] ?? '')) === '') {
        $required_errors[] = 'Doctor Name';
    }

    if ($specialty_id <= 0) {
        $required_errors[] = 'Specialty';
    }

    if (trim((string)($_POST['primary_hospital'] ?? '')) === '') {
        $required_errors[] = 'Primary Hospital';
    }

    if (trim((string)($_POST['degree'] ?? '')) === '') {
        $required_errors[] = 'Degree';
    }

    if (trim((string)($_POST['designation'] ?? '')) === '') {
        $required_errors[] = 'Designation';
    }

    if ($submitted_gender === '') {
        $required_errors[] = 'Gender';
    }

    if (!empty($required_errors)) {
        $query = $id ? ['id' => $id] : [];
        $query['error'] = 'Please fill required fields: ' . implode(', ', $required_errors) . '.';
        doctor_form_redirect_self($query);
    }

    $image = $_POST['old_image'] ?? '';
    $og_image = $_POST['old_og_image'] ?? ($_POST['og_image'] ?? '');

    if (function_exists('doctor_image_biography_upload_named_image')) {
        $uploaded_image = doctor_image_biography_upload_named_image('image', $_POST['name'] ?? 'doctor');
        $uploaded_og_image = doctor_image_biography_upload_named_image('og_image_file', $_POST['name'] ?? 'doctor', 'og');

        if ($uploaded_image !== '') {
            $image = $uploaded_image;
        }

        if ($uploaded_og_image !== '') {
            $og_image = $uploaded_og_image;
        }
    }

    if ($id && !empty($doctor['slug'])) {
        $doctor_slug = $doctor['slug'];
    } else {
        $manual_slug = trim((string)($_POST['slug'] ?? ''));

        if ($manual_slug !== '') {
            $doctor_slug = slugify($manual_slug);
        } elseif (function_exists('build_doctor_slug')) {
            $district_pair = doctor_form_db_name_pair('districts', $doctor_district_id);
            $district_name = $district_pair['en'];
            $doctor_slug = build_doctor_slug($_POST['name'] ?? '', $specialty_id, $district_name);
        } else {
            $doctor_slug = slugify($_POST['name'] ?? 'doctor');
        }

        $doctor_slug = make_unique_slug('doctors', $doctor_slug, $id);
    }

    $is_featured = !empty($doctor['is_featured']) ? 1 : 0;
    $featured_days = (int)($doctor['featured_days'] ?? 0);
    $featured_position = (int)($doctor['featured_position'] ?? 0);
    $featured_started_at = trim((string)($doctor['featured_started_at'] ?? ''));
    $featured_until = trim((string)($doctor['featured_until'] ?? ''));
    $featured_on_hold = !empty($doctor['featured_on_hold']) ? 1 : 0;
    $featured_hold_remaining_days = (int)($doctor['featured_hold_remaining_days'] ?? 0);
    $featured_hold_started_at = trim((string)($doctor['featured_hold_started_at'] ?? ''));

    if (!$id || !$doctor) {
        $is_featured = 0;
        $featured_days = 0;
        $featured_position = 0;
        $featured_started_at = null;
        $featured_until = null;
        $featured_on_hold = 0;
        $featured_hold_remaining_days = 0;
        $featured_hold_started_at = null;
    } else {
        $featured_started_at = $featured_started_at !== '' ? $featured_started_at : null;
        $featured_until = $featured_until !== '' ? $featured_until : null;
        $featured_hold_started_at = $featured_hold_started_at !== '' ? $featured_hold_started_at : null;
    }

    $rating_stats = doctor_form_get_auto_rating_stats($id);

    $auto_seo_source = array_merge(is_array($doctor ?? null) ? $doctor : [], $_POST, [
        'specialty_id' => $specialty_id,
        'doctor_district_id' => $doctor_district_id,
    ]);

    $auto_seo_values = doctor_form_generate_auto_seo($auto_seo_source);

    $seo_title_mode = doctor_form_clean_seo_mode($_POST['seo_title_mode'] ?? 'auto');
    $seo_title_bn_mode = doctor_form_clean_seo_mode($_POST['seo_title_bn_mode'] ?? 'auto');
    $seo_description_mode = doctor_form_clean_seo_mode($_POST['seo_description_mode'] ?? 'auto');
    $seo_description_bn_mode = doctor_form_clean_seo_mode($_POST['seo_description_bn_mode'] ?? 'auto');
    $meta_keywords_mode = doctor_form_clean_seo_mode($_POST['meta_keywords_mode'] ?? 'auto');
    $meta_keywords_bn_mode = doctor_form_clean_seo_mode($_POST['meta_keywords_bn_mode'] ?? 'auto');

    $seo_title = doctor_form_seo_value_by_mode(
        $_POST['seo_title'] ?? '',
        $seo_title_mode,
        $auto_seo_values['seo_title'] ?? ''
    );

    $seo_title_bn = doctor_form_seo_value_by_mode(
        $_POST['seo_title_bn'] ?? '',
        $seo_title_bn_mode,
        $auto_seo_values['seo_title_bn'] ?? ''
    );

    $seo_description = doctor_form_seo_value_by_mode(
        $_POST['seo_description'] ?? '',
        $seo_description_mode,
        $auto_seo_values['seo_description'] ?? ''
    );

    $seo_description_bn = doctor_form_seo_value_by_mode(
        $_POST['seo_description_bn'] ?? '',
        $seo_description_bn_mode,
        $auto_seo_values['seo_description_bn'] ?? ''
    );

    $meta_keywords = doctor_form_seo_value_by_mode(
        $_POST['meta_keywords'] ?? '',
        $meta_keywords_mode,
        $auto_seo_values['meta_keywords'] ?? ''
    );

    $meta_keywords_bn = doctor_form_seo_value_by_mode(
        $_POST['meta_keywords_bn'] ?? '',
        $meta_keywords_bn_mode,
        $auto_seo_values['meta_keywords_bn'] ?? ''
    );

    /*
     * If a manual field was cleared, force only that single field back to auto mode.
     */
    if (doctor_form_text($_POST['seo_title'] ?? '') === '') {
        $seo_title_mode = 'auto';
    }

    if (doctor_form_text($_POST['seo_title_bn'] ?? '') === '') {
        $seo_title_bn_mode = 'auto';
    }

    if (doctor_form_text($_POST['seo_description'] ?? '') === '') {
        $seo_description_mode = 'auto';
    }

    if (doctor_form_text($_POST['seo_description_bn'] ?? '') === '') {
        $seo_description_bn_mode = 'auto';
    }

    if (doctor_form_text($_POST['meta_keywords'] ?? '') === '') {
        $meta_keywords_mode = 'auto';
    }

    if (doctor_form_text($_POST['meta_keywords_bn'] ?? '') === '') {
        $meta_keywords_bn_mode = 'auto';
    }

    $location_has_value = $doctor_division_id > 0 || $doctor_district_id > 0 || $doctor_thana_id > 0;
    $old_location_source = trim((string)($doctor['doctor_location_source'] ?? 'auto'));

    $doctor_location_source = $location_has_value ? $old_location_source : 'auto';

    if (
        isset($_POST['doctor_location_user_changed']) &&
        (string)$_POST['doctor_location_user_changed'] === '1' &&
        $location_has_value
    ) {
        $doctor_location_source = 'manual';
    }

    $doctor_fields = [
        'name' => $_POST['name'] ?? '',
        'name_bn' => $_POST['name_bn'] ?? '',
        'slug' => $doctor_slug,

        'degree' => $_POST['degree'] ?? '',
        'degree_bn' => $_POST['degree_bn'] ?? '',

        'designation' => $_POST['designation'] ?? '',
        'designation_bn' => $_POST['designation_bn'] ?? '',

        'bmdc_number' => $_POST['bmdc_number'] ?? '',
        'gender' => $submitted_gender,
        'gender_bn' => $_POST['gender_bn'] ?? '',

        'languages' => $_POST['languages'] ?? '',
        'languages_bn' => $_POST['languages_bn'] ?? '',

        'specialty_id' => $specialty_id,
        'hospital_id' => (int)($_POST['hospital_id'] ?? 0),

        'primary_hospital' => $_POST['primary_hospital'] ?? '',
        'primary_hospital_bn' => $_POST['primary_hospital_bn'] ?? '',

        'experience_years' => trim((string)($_POST['experience_years'] ?? '')) === ''
            ? null
            : (int)$_POST['experience_years'],

        'consultation_fee' => (float)($_POST['consultation_fee'] ?? 0),
        'follow_up_fee' => (float)($_POST['follow_up_fee'] ?? 0),
        'video_consultation_fee' => (float)($_POST['video_consultation_fee'] ?? 0),

        'phone' => $_POST['phone'] ?? '',
        'whatsapp' => $_POST['whatsapp'] ?? '',
        'email' => $_POST['email'] ?? '',

        'doctor_division_id' => $doctor_division_id,
        'doctor_district_id' => $doctor_district_id,
        'doctor_thana_id' => $doctor_thana_id,
        'doctor_location_source' => $doctor_location_source,
        'doctor_location_auto_chamber_id' => (int)($_POST['doctor_location_auto_chamber_id'] ?? ($doctor['doctor_location_auto_chamber_id'] ?? 0)),

        'image' => $image,
        'og_image' => $og_image,

        'bio' => $_POST['bio'] ?? '',
        'bio_bn' => $_POST['bio_bn'] ?? '',

        'education' => $_POST['education'] ?? '',
        'education_bn' => $_POST['education_bn'] ?? '',

        'training' => $_POST['training'] ?? '',
        'training_bn' => $_POST['training_bn'] ?? '',

        'fellowship' => $_POST['fellowship'] ?? '',
        'fellowship_bn' => $_POST['fellowship_bn'] ?? '',

        'expertise' => $_POST['expertise'] ?? '',
        'expertise_bn' => $_POST['expertise_bn'] ?? '',

        'appointment_note' => $_POST['appointment_note'] ?? '',
        'appointment_note_bn' => $_POST['appointment_note_bn'] ?? '',

        'rating' => (float)($rating_stats['rating'] ?? 0),
        'reviews_count' => (int)($rating_stats['reviews_count'] ?? 0),

        'is_verified' => isset($_POST['is_verified']) ? 1 : 0,
        'is_featured' => $is_featured,

        'featured_days' => $featured_days,
        'featured_started_at' => $featured_started_at,
        'featured_until' => $featured_until,
        'featured_position' => $featured_position,

        'featured_on_hold' => $featured_on_hold,
        'featured_hold_remaining_days' => $featured_hold_remaining_days,
        'featured_hold_started_at' => $featured_hold_started_at,

        'emergency_available' => isset($_POST['emergency_available']) ? 1 : 0,
        'online_consultation' => isset($_POST['online_consultation']) ? 1 : 0,
        'home_visit' => isset($_POST['home_visit']) ? 1 : 0,

        'status' => $_POST['status'] ?? 'active',

        'seo_title' => $seo_title,
        'seo_title_bn' => $seo_title_bn,

        'seo_description' => $seo_description,
        'seo_description_bn' => $seo_description_bn,

        'meta_keywords' => $meta_keywords,
        'meta_keywords_bn' => $meta_keywords_bn,

        'seo_title_mode' => $seo_title_mode,
        'seo_title_bn_mode' => $seo_title_bn_mode,
        'seo_description_mode' => $seo_description_mode,
        'seo_description_bn_mode' => $seo_description_bn_mode,
        'meta_keywords_mode' => $meta_keywords_mode,
        'meta_keywords_bn_mode' => $meta_keywords_bn_mode,

        'updated_at' => date('Y-m-d H:i:s'),
    ];

    try {
        if ($id > 0 && $doctor) {
            doctor_form_update_dynamic('doctors', $doctor_fields, $id);

            $saved_id = $id;
            $save_status = 'updated';
        } else {
            $doctor_fields['created_at'] = date('Y-m-d H:i:s');

            $saved_id = doctor_form_insert_dynamic('doctors', $doctor_fields);
            $save_status = 'created';
        }

        doctor_form_redirect_self([
            'id' => $saved_id,
            'saved' => $save_status,
        ]);
    } catch (Throwable $e) {
        $query = $id ? ['id' => $id] : [];
        $query['error'] = 'Doctor could not be saved. Error: ' . $e->getMessage();
        doctor_form_redirect_self($query);
    }
}

/*
|--------------------------------------------------------------------------
| Page data
|--------------------------------------------------------------------------
*/
$doctor = doctor_form_get_doctor($id);
$doctor_for_context = is_array($doctor) ? $doctor : [];

$doctor_name_for_title = trim((string)($doctor_for_context['name'] ?? ''));
$doctor_slug_for_view = trim((string)($doctor_for_context['slug'] ?? ''));

$doctor_page_title = $id && $doctor_name_for_title !== ''
    ? 'Edit Doctor - ' . $doctor_name_for_title
    : ($id ? 'Edit Doctor' : 'Add Doctor');

$doctor_form_title = $id && $doctor_name_for_title !== ''
    ? 'Edit ' . $doctor_name_for_title
    : ($id ? 'Edit Doctor' : 'Add Doctor');

$doctor_form_subtitle = $id && $doctor_name_for_title !== ''
    ? 'Update profile information for ' . $doctor_name_for_title . '.'
    : 'Add or update doctor profile information, specialty, hospital, contact details, image, SEO content, and publishing status from one clean panel.';

$specialties = function_exists('get_specialties') ? get_specialties() : [];
$hospitals = function_exists('get_hospitals') ? get_hospitals([], 5000) : [];

$doctor_form_divisions = function_exists('doctor_hospitals_availability_get_divisions')
    ? doctor_hospitals_availability_get_divisions()
    : [];

$doctor_form_hospital_options = function_exists('doctor_hospitals_availability_get_hospitals_for_select')
    ? doctor_hospitals_availability_get_hospitals_for_select()
    : [];

$doctor_chambers = ($id && function_exists('doctor_hospitals_availability_get_chambers'))
    ? doctor_hospitals_availability_get_chambers($id)
    : [];

$doctor_auto_rating_stats = doctor_form_get_auto_rating_stats($id);

$form_message = '';
$form_message_type = '';

if (isset($_GET['saved'])) {
    $form_message_type = 'success';

    $saved_message_map = [
        'slug' => 'Slug saved successfully.',
        'hospital' => 'Hospital availability saved successfully.',
        'hospital_updated' => 'Hospital availability updated successfully.',
        'hospital_deleted' => 'Hospital availability deleted successfully.',
        'chamber' => 'Hospital availability saved successfully.',
        'chamber_updated' => 'Hospital availability updated successfully.',
        'chamber_deleted' => 'Hospital availability deleted successfully.',
        'created' => 'Doctor saved successfully.',
        'updated' => 'Doctor information updated successfully.',
        'featured_saved' => 'Featured Profile saved successfully.',
        'featured_hold' => 'Featured Profile is now on hold. Remaining days are saved.',
        'featured_resumed' => 'Featured Profile resumed from remaining days.',
        'featured_stopped' => 'Featured Profile marked inactive because no remaining days were available.',
    ];

    $form_message = $saved_message_map[$_GET['saved']] ?? 'Doctor information updated successfully.';
}

if (isset($_GET['error'])) {
    $form_message_type = 'error';
    $form_message = trim((string)$_GET['error']);
}

$browser_title = $doctor_name_for_title !== ''
    ? $doctor_name_for_title
    : ($id ? 'Edit Doctor' : 'Add Doctor');

/*
|--------------------------------------------------------------------------
| Header
|--------------------------------------------------------------------------
*/
ob_start();
require_once __DIR__ . '/includes/header.php';
$admin_header_html = ob_get_clean();

$admin_header_title = e($browser_title) . ' | ' . e(APP_NAME);
$admin_header_html = preg_replace('/<title>.*?<\/title>/is', '<title>' . $admin_header_title . '</title>', $admin_header_html, 1);
echo $admin_header_html;

$doctor_form_context = [
    'id' => $id,
    'doctor' => $doctor_for_context,
    'specialties' => $specialties,
    'hospitals' => $hospitals,
    'doctor_chambers' => $doctor_chambers,
    'doctor_form_divisions' => $doctor_form_divisions,
    'doctor_form_hospital_options' => $doctor_form_hospital_options,
    'doctor_auto_rating_stats' => $doctor_auto_rating_stats,
    'doctor_name_for_title' => $doctor_name_for_title,
    'doctor_slug_for_view' => $doctor_slug_for_view,
    'doctor_page_title' => $doctor_page_title,
    'doctor_form_title' => $doctor_form_title,
    'doctor_form_subtitle' => $doctor_form_subtitle,
];

if (!function_exists('doctor_form_render_section_safe')) {
    function doctor_form_render_section_safe(array $renderers, array $context, string $label): void
    {
        $found_renderer = '';

        foreach ($renderers as $renderer) {
            if (function_exists($renderer)) {
                $found_renderer = $renderer;
                break;
            }
        }

        if ($found_renderer === '') {
            echo '<div class="lite-section-error">';
            echo '<strong>' . e($label) . ' not loaded.</strong>';
            echo '<p>Please check the section file and render function name.</p>';
            echo '</div>';
            return;
        }

        try {
            $found_renderer($context);
        } catch (Throwable $e) {
            echo '<div class="lite-section-error">';
            echo '<strong>' . e($label) . ' has an error.</strong>';
            echo '<p>' . e($e->getMessage()) . '</p>';
            echo '</div>';
        }
    }
}
?>

<style>
  :root {
    --pro-bg:#f6f8fa;
    --pro-card:#ffffff;
    --pro-text:#24292f;
    --pro-muted:#57606a;
    --pro-border:#d0d7de;
    --pro-primary:#0969da;
    --pro-success:#1a7f37;
    --pro-danger:#cf222e;
  }

  body {
    background: var(--pro-bg);
  }

  .lite-doctor-page,
  .lite-doctor-page * {
    box-sizing: border-box;
    font-weight: 500 !important;
  }

  .lite-doctor-page {
    max-width: 1280px;
    margin: 0 auto;
    padding: 16px 16px 36px;
    color: var(--pro-text);
  }

  .lite-page-hero,
  .lite-form-card {
    overflow: visible;
    border-radius: 12px;
    background: #fff;
    border: 1px solid var(--pro-border);
    box-shadow: 0 1px 0 rgba(27,31,36,.04);
  }

  .lite-page-hero {
    padding: 18px;
    margin-bottom: 16px;
  }

  .lite-page-hero-inner {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 24px;
  }

  .lite-breadcrumb {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 12px;
    padding: 7px 10px;
    border-radius: 6px;
    color: var(--pro-muted);
    font-size: 13px;
    background: #f6f8fa;
    border: 1px solid var(--pro-border);
  }

  .lite-breadcrumb a {
    color: var(--pro-primary);
    text-decoration: none;
  }

  .lite-page-hero h1 {
    margin: 0;
    color: var(--pro-text);
    font-size: clamp(26px,3vw,38px);
    line-height: 1.15;
    letter-spacing: -.04em;
  }

  .lite-page-hero p {
    max-width: 720px;
    margin: 10px 0 0;
    color: var(--pro-muted);
    font-size: 15px;
    line-height: 1.8;
  }

  .lite-hero-actions,
  .lite-submit-actions {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
    justify-content: flex-end;
  }

  .lite-btn-link,
  .lite-btn,
  .lite-mini-btn,
  .wp-admin-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 40px;
    padding: 9px 14px;
    border-radius: 6px;
    border: 1px solid rgba(27,31,36,.15);
    cursor: pointer;
    font-family: inherit;
    font-size: 13.5px;
    text-decoration: none;
    white-space: nowrap;
  }

  .lite-btn-link,
  .lite-mini-btn,
  .lite-btn-light {
    color: var(--pro-text);
    background: #f6f8fa;
  }

  .lite-btn-link:hover,
  .lite-mini-btn:hover,
  .lite-btn-light:hover {
    background: #eef1f4;
  }

  .lite-btn-primary,
  .wp-admin-button {
    background: #2da44e;
    color: #fff;
  }

  .lite-btn-primary:hover,
  .wp-admin-button:hover {
    background: #1f883d;
  }

  .lite-btn-social {
    color: #fff;
    background: #0969da;
    border-color: #0969da;
  }

  .lite-btn-social:hover {
    color: #fff;
    background: #0757b8;
    border-color: #0757b8;
  }

  .lite-alert {
    display: flex;
    align-items: flex-start;
    gap: 14px;
    margin-bottom: 16px;
    padding: 14px 16px;
    border-radius: 12px;
    border: 1px solid transparent;
    background: #fff;
  }

  .lite-alert.success {
    border-color: #a7f3d0;
    background: #ecfdf5;
    color: #065f46;
  }

  .lite-alert.error {
    border-color: #fecdd3;
    background: #fff1f2;
    color: #9f1239;
  }

  .lite-alert-icon {
    width: 38px;
    height: 38px;
    flex: 0 0 38px;
    border-radius: 8px;
    display: grid;
    place-items: center;
    color: #fff;
    font-size: 18px;
    background: var(--pro-primary);
  }

  .lite-alert.success .lite-alert-icon {
    background: var(--pro-success);
  }

  .lite-alert.error .lite-alert-icon {
    background: var(--pro-danger);
  }

  .lite-alert-content h3 {
    margin: 0 0 4px;
    font-size: 16px;
    color: inherit;
  }

  .lite-alert-content p {
    margin: 0;
    font-size: 14px;
    line-height: 1.6;
    color: inherit;
    opacity: .9;
  }

  .lite-form-card + .lite-form-card {
    margin-top: 22px;
  }

  .lite-form-card-header {
    padding: 20px 22px;
    border-bottom: 1px solid var(--pro-border);
    background: #f6f8fa;
  }

  .lite-form-card-header h2 {
    margin: 0;
    color: var(--pro-text);
    font-size: 20px;
    letter-spacing: -.025em;
  }

  .lite-form-card-header p {
    max-width: 880px;
    margin: 8px 0 0;
    color: var(--pro-muted);
    font-size: 14px;
    line-height: 1.65;
  }

  .lite-admin-form {
    padding: 22px;
  }

  .lite-section {
    margin-bottom: 22px;
  }

  .lite-section:last-of-type {
    margin-bottom: 0;
  }

  .lite-submit-bar {
    position: sticky;
    bottom: 16px;
    z-index: 5;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
    padding: 14px;
    border-radius: 12px;
    background: rgba(255,255,255,.92);
    border: 1px solid var(--pro-border);
    box-shadow: 0 12px 35px rgba(15,23,42,.1);
    backdrop-filter: blur(14px);
  }

  .lite-submit-bar p {
    margin: 0;
    color: var(--pro-muted);
    font-size: 13px;
  }

  .lite-section-error {
    padding: 14px 16px;
    border: 1px solid #fecdd3;
    border-radius: 10px;
    background: #fff1f2;
    color: #9f1239;
  }

  .lite-section-error strong {
    display: block;
    margin-bottom: 4px;
    font-weight: 700 !important;
  }

  .lite-section-error p {
    margin: 0;
    color: #9f1239;
    font-size: 13px;
    line-height: 1.6;
  }

  @media(max-width:900px) {
    .lite-page-hero-inner,
    .lite-submit-bar {
      flex-direction: column;
      align-items: stretch;
    }

    .lite-hero-actions,
    .lite-submit-actions {
      justify-content: flex-start;
    }
  }
</style>

<div class="lite-doctor-page">
    <?php if ($form_message !== ''): ?>
        <div class="lite-alert <?= e($form_message_type) ?>">
            <div class="lite-alert-icon"><?= $form_message_type === 'success' ? '✓' : '!' ?></div>
            <div class="lite-alert-content">
                <h3><?= $form_message_type === 'success' ? 'Success' : 'Error' ?></h3>
                <p><?= e($form_message) ?></p>
            </div>
        </div>
    <?php endif; ?>

    <div class="lite-page-hero">
        <div class="lite-page-hero-inner">
            <div>
                <div class="lite-breadcrumb">
                    <a href="dashboard.php">Dashboard</a>
                    <span>/</span>
                    <a href="doctors.php">Doctors</a>
                    <span>/</span>
                    <span><?= e($doctor_page_title) ?></span>
                </div>

                <h1><?= e($doctor_form_title) ?></h1>
                <p><?= e($doctor_form_subtitle) ?></p>
            </div>

            <div class="lite-hero-actions">
                <a href="doctors.php" class="lite-btn-link">Back to Doctors</a>

                <?php if ($id > 0): ?>
                    <a
                        href="doctor-social-post.php?doctor_id=<?= e((string)$id) ?>"
                        class="lite-btn-link lite-btn-social"
                    >
                        Social Post
                    </a>
                <?php endif; ?>

                <?php if ($id && $doctor_slug_for_view !== ''): ?>
                    <a href="<?= e(site_url('doctor/' . $doctor_slug_for_view)) ?>" target="_blank" rel="noopener" class="lite-btn-link">View Doctor Page</a>
                <?php else: ?>
                    <a href="<?= e(site_url()) ?>" target="_blank" rel="noopener" class="lite-btn-link">View Website</a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if (function_exists('doctor_hospitals_availability_render')): ?>
        <?php doctor_hospitals_availability_render($doctor_form_context); ?>
    <?php endif; ?>

    <div class="lite-form-card">
        <div class="lite-form-card-header">
            <h2><?= $id && $doctor_name_for_title !== '' ? 'Update ' . e($doctor_name_for_title) : ($id ? 'Update Doctor Information' : 'Create New Doctor') ?></h2>
            <p>Slug is locked by default. Featured Profile is controlled only from its popup, so normal profile updates will not restart featured days.</p>
        </div>

        <form class="lite-admin-form" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="form_action" value="save_doctor">
            <input type="hidden" name="doctor_id" value="<?= e((string)$id) ?>">
            <input type="hidden" name="old_image" value="<?= e($doctor_for_context['image'] ?? '') ?>">
            <input type="hidden" name="old_og_image" value="<?= e($doctor_for_context['og_image'] ?? '') ?>">

            <?php
            $section_renderers = [
                'Basic Information' => [
                    'doctor_basic_information_render',
                ],
                'Contact & Location' => [
                    'doctor_contact_location_render',
                ],
                'Professional Details' => [
                    'doctor_professional_details_render',
                    'doctor_professional_render',
                    'doctor_professional_details_section_render',
                ],
                'Status & Display Options' => [
                    'doctor_status_display_options_render',
                    'doctor_status_render',
                    'doctor_status_display_options_section_render',
                ],
                'Image & Biography' => [
                    'doctor_image_biography_render',
                    'doctor_image_biography_section_render',
                    'doctor_image_render',
                ],
                'Clinical Profile' => [
                    'doctor_clinical_profile_render',
                    'doctor_clinical_render',
                    'doctor_clinical_profile_section_render',
                ],
                'SEO Information' => [
                    'doctor_seo_information_render',
                    'doctor_seo_render',
                    'doctor_seo_information_section_render',
                ],
            ];

            foreach ($section_renderers as $section_label => $section_renderer_list) {
                echo '<div class="lite-section">';
                doctor_form_render_section_safe($section_renderer_list, $doctor_form_context, $section_label);
                echo '</div>';
            }
            ?>

            <div class="lite-submit-bar">
                <p><?= $id ? 'You are editing an existing doctor profile.' : 'You are creating a new doctor profile.' ?></p>

                <div class="lite-submit-actions">
                    <a href="doctors.php" class="lite-btn lite-btn-light">Cancel</a>
                    <button class="lite-btn lite-btn-primary" type="submit">
                        Save & Update Doctor
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>