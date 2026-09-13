<?php
/*
|--------------------------------------------------------------------------
| Doctor Hero Section
|--------------------------------------------------------------------------
| Self-contained hero file.
|
| Desktop Display:
| 1. Name
| 2. Degree
| 3. Specialty
| 4. Designation | Primary Hospital
| 5. Consultation fee and share option
|
| Mobile Display:
| 1. Name
| 2. Degree
| 3. Training
| 4. Fellowship
| 5. Specialty
| 6. Designation
| 7. Primary Hospital
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/../includes/functions.php';

$slug = $_GET['slug'] ?? '';

if (!isset($doctor) || !is_array($doctor)) {
    $doctor = function_exists('get_doctor_by_slug') ? get_doctor_by_slug($slug) : null;
}

if (!$doctor || !is_array($doctor)) {
    return;
}

/*
|--------------------------------------------------------------------------
| Basic Language Fallbacks
|--------------------------------------------------------------------------
*/

if (!defined('CURRENT_LANG')) {
    $current_request_path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    $current_request_path = trim((string)$current_request_path, '/');

    if (defined('APP_URL')) {
        $app_path = trim((string)(parse_url(APP_URL, PHP_URL_PATH) ?? ''), '/');

        if ($app_path !== '' && str_starts_with($current_request_path, $app_path)) {
            $current_request_path = trim(substr($current_request_path, strlen($app_path)), '/');
        }
    }

    define('CURRENT_LANG', ($current_request_path === 'bn' || str_starts_with($current_request_path, 'bn/')) ? 'bn' : 'en');
}

if (!function_exists('__t')) {
    function __t(string $key, string $fallback = ''): string
    {
        $lang = defined('CURRENT_LANG') ? CURRENT_LANG : 'en';
        $base_dir = dirname(__DIR__);
        $default_file = $base_dir . '/languages/en.php';
        $lang_file = $base_dir . '/languages/' . $lang . '.php';

        static $translations = null;

        if ($translations === null) {
            $translations = [];

            if (file_exists($default_file)) {
                $default_translations = require $default_file;

                if (is_array($default_translations)) {
                    $translations = $default_translations;
                }
            }

            if ($lang !== 'en' && file_exists($lang_file)) {
                $current_translations = require $lang_file;

                if (is_array($current_translations)) {
                    $translations = array_merge($translations, $current_translations);
                }
            }
        }

        $value = $translations[$key] ?? '';

        if (trim((string)$value) !== '') {
            return (string)$value;
        }

        return $fallback !== '' ? $fallback : $key;
    }
}

if (!function_exists('lang_text')) {
    function lang_text(string $english = '', string $bangla = ''): string
    {
        if (defined('CURRENT_LANG') && CURRENT_LANG === 'bn' && trim($bangla) !== '') {
            return trim($bangla);
        }

        return trim($english);
    }
}

/*
|--------------------------------------------------------------------------
| Hero Helpers
|--------------------------------------------------------------------------
*/

if (!function_exists('doctor_hero_local_number')) {
    function doctor_hero_local_number($value): string
    {
        $value = (string)$value;

        if (defined('CURRENT_LANG') && CURRENT_LANG === 'bn') {
            $en = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
            $bn = ['০', '১', '২', '৩', '৪', '৫', '৬', '৭', '৮', '৯'];

            return str_replace($en, $bn, $value);
        }

        return $value;
    }
}

if (!function_exists('doctor_hero_table_exists')) {
    function doctor_hero_table_exists(string $table): bool
    {
        global $pdo;

        static $table_exists_cache = [];

        $table = trim($table);

        if ($table === '') {
            return false;
        }

        if (array_key_exists($table, $table_exists_cache)) {
            return $table_exists_cache[$table];
        }

        try {
            $stmt = $pdo->prepare("
                SELECT COUNT(*)
                FROM INFORMATION_SCHEMA.TABLES
                WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = :table
            ");
            $stmt->execute([':table' => $table]);

            $table_exists_cache[$table] = (int)$stmt->fetchColumn() > 0;
            return $table_exists_cache[$table];
        } catch (Throwable $e) {
            $table_exists_cache[$table] = false;
            return false;
        }
    }
}

if (!function_exists('doctor_hero_column_exists')) {
    function doctor_hero_column_exists(string $table, string $column): bool
    {
        global $pdo;

        static $column_exists_cache = [];

        $table = trim($table);
        $column = trim($column);
        $cache_key = $table . '.' . $column;

        if ($table === '' || $column === '') {
            return false;
        }

        if (array_key_exists($cache_key, $column_exists_cache)) {
            return $column_exists_cache[$cache_key];
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

            $column_exists_cache[$cache_key] = (int)$stmt->fetchColumn() > 0;
            return $column_exists_cache[$cache_key];
        } catch (Throwable $e) {
            $column_exists_cache[$cache_key] = false;
            return false;
        }
    }
}

if (!function_exists('doctor_hero_site_setting')) {
    function doctor_hero_site_setting(string $key, string $default = ''): string
    {
        global $pdo;

        static $settings_cache = null;

        if ($settings_cache === null) {
            $settings_cache = [];

            try {
                if (!doctor_hero_table_exists('site_settings')) {
                    return $default;
                }

                $stmt = $pdo->query("SELECT setting_key, setting_value FROM site_settings");
                $rows = $stmt->fetchAll();

                foreach ($rows as $row) {
                    $settings_cache[(string)$row['setting_key']] = (string)$row['setting_value'];
                }
            } catch (Throwable $e) {
                $settings_cache = [];
            }
        }

        $value = trim((string)($settings_cache[$key] ?? ''));

        return $value !== '' ? $value : $default;
    }
}

if (!function_exists('doctor_hero_default_doctor_image_setting')) {
    function doctor_hero_default_doctor_image_setting(array $doctor): string
    {
        $gender = strtolower(trim((string)($doctor['gender'] ?? '')));

        if (in_array($gender, ['male', 'm'], true)) {
            $value = doctor_hero_site_setting('default_doctor_male_image', '');

            if ($value !== '') {
                return $value;
            }
        }

        if (in_array($gender, ['female', 'f'], true)) {
            $value = doctor_hero_site_setting('default_doctor_female_image', '');

            if ($value !== '') {
                return $value;
            }
        }

        return doctor_hero_site_setting('default_doctor_image', 'assets/images/default-doctor.webp');
    }
}

if (!function_exists('doctor_hero_asset_url')) {
    function doctor_hero_asset_url(string $path, string $fallback = ''): string
    {
        $path = trim($path);

        if ($path === '') {
            $path = $fallback;
        }

        if ($path === '') {
            return '';
        }

        if (preg_match('/^https?:\/\//i', $path)) {
            return $path;
        }

        $path = str_replace('\\', '/', $path);

        while (strpos($path, '../') === 0) {
            $path = substr($path, 3);
        }

        $path = ltrim($path, '/');

        if ($path === '') {
            return '';
        }

        if (function_exists('site_url')) {
            return site_url($path);
        }

        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? '';

        return $host !== '' ? $scheme . '://' . $host . '/' . $path : '/' . $path;
    }
}

if (!function_exists('doctor_hero_phone_link')) {
    function doctor_hero_phone_link(string $phone): string
    {
        $phone = trim($phone);

        if ($phone === '') {
            return '';
        }

        return preg_replace('/[^\d+]/', '', $phone);
    }
}

if (!function_exists('doctor_hero_chamber_value')) {
    function doctor_hero_chamber_value(array $chamber, array $keys, string $default = ''): string
    {
        foreach ($keys as $key) {
            if (isset($chamber[$key]) && trim((string)$chamber[$key]) !== '') {
                return trim((string)$chamber[$key]);
            }
        }

        return $default;
    }
}

if (!function_exists('doctor_hero_data_value')) {
    function doctor_hero_data_value(array $data, array $keys, string $default = ''): string
    {
        foreach ($keys as $key) {
            if (isset($data[$key]) && trim((string)$data[$key]) !== '') {
                return trim((string)$data[$key]);
            }
        }

        return $default;
    }
}

if (!function_exists('doctor_hero_multiline_text')) {
    function doctor_hero_multiline_text(string $value): string
    {
        /*
         * Preserve line-break tags from Training/Fellowship content while
         * escaping all other HTML for safe frontend output.
         */
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        /* Normalize valid and commonly used <br> variants, including </br>. */
        $value = preg_replace('~<\s*/\s*br\s*>~iu', '<br>', $value);
        $value = preg_replace('~<\s*br\s*/?\s*>~iu', '<br>', $value);

        $value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');

        /* Restore only the normalized safe break tag after escaping. */
        $value = str_ireplace('&lt;br&gt;', '<br>', $value);

        /* Plain text new lines also remain visible as line breaks. */
        return nl2br($value, false);
    }
}

if (!function_exists('doctor_hero_lang_value')) {
    function doctor_hero_lang_value(array $data, array $english_keys, array $bangla_keys, string $default = ''): string
    {
        $english = doctor_hero_data_value($data, $english_keys, '');
        $bangla = doctor_hero_data_value($data, $bangla_keys, '');

        $value = lang_text($english, $bangla);

        return $value !== '' ? $value : $default;
    }
}

if (!function_exists('doctor_hero_get_specialty_by_id')) {
    function doctor_hero_get_specialty_by_id(int $specialty_id): array
    {
        global $pdo;

        $specialty = [
            'name' => '',
            'name_bn' => '',
        ];

        if (
            $specialty_id <= 0 ||
            !isset($pdo) ||
            !($pdo instanceof PDO) ||
            !doctor_hero_table_exists('specialties')
        ) {
            return $specialty;
        }

        try {
            $name_bn_select = doctor_hero_column_exists('specialties', 'name_bn')
                ? 'name_bn'
                : "'' AS name_bn";

            $stmt = $pdo->prepare("
                SELECT name, {$name_bn_select}
                FROM specialties
                WHERE id = :specialty_id
                LIMIT 1
            ");
            $stmt->execute([':specialty_id' => $specialty_id]);

            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (is_array($row)) {
                $specialty['name'] = trim((string)($row['name'] ?? ''));
                $specialty['name_bn'] = trim((string)($row['name_bn'] ?? ''));
            }
        } catch (Throwable $e) {
            // Keep the profile page available if the specialty lookup fails.
        }

        return $specialty;
    }
}

/*
|--------------------------------------------------------------------------
| District Resolver
|--------------------------------------------------------------------------
| Same priority as breadcrumb.php:
| 1. doctors.doctor_district_id
| 2. first active chamber -> hospitals.district_id
| 3. districts.name / name_en / name_bn
|--------------------------------------------------------------------------
*/

if (!function_exists('doctor_hero_get_district_pair_by_id')) {
    function doctor_hero_get_district_pair_by_id(int $district_id): array
    {
        global $pdo;

        $empty = [
            'en' => '',
            'bn' => '',
        ];

        if (
            $district_id <= 0 ||
            !isset($pdo) ||
            !($pdo instanceof PDO) ||
            !doctor_hero_table_exists('districts') ||
            !doctor_hero_column_exists('districts', 'id')
        ) {
            return $empty;
        }

        $english_select = doctor_hero_column_exists('districts', 'name_en')
            ? 'name_en'
            : (doctor_hero_column_exists('districts', 'name') ? 'name AS name_en' : "'' AS name_en");

        $bangla_select = doctor_hero_column_exists('districts', 'name_bn')
            ? 'name_bn'
            : "'' AS name_bn";

        try {
            $stmt = $pdo->prepare("
                SELECT {$english_select}, {$bangla_select}
                FROM districts
                WHERE id = :district_id
                LIMIT 1
            ");
            $stmt->execute([':district_id' => $district_id]);

            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!is_array($row)) {
                return $empty;
            }

            return [
                'en' => trim((string)($row['name_en'] ?? '')),
                'bn' => trim((string)($row['name_bn'] ?? '')),
            ];
        } catch (Throwable $e) {
            return $empty;
        }
    }
}

if (!function_exists('doctor_hero_first_chamber_hospital_district_id')) {
    function doctor_hero_first_chamber_hospital_district_id(int $doctor_id): int
    {
        global $pdo;

        if (
            $doctor_id <= 0 ||
            !isset($pdo) ||
            !($pdo instanceof PDO) ||
            !doctor_hero_table_exists('chambers') ||
            !doctor_hero_table_exists('hospitals') ||
            !doctor_hero_column_exists('chambers', 'doctor_id') ||
            !doctor_hero_column_exists('chambers', 'hospital_id') ||
            !doctor_hero_column_exists('hospitals', 'district_id')
        ) {
            return 0;
        }

        $where = [
            'c.doctor_id = :doctor_id',
            'c.hospital_id > 0',
        ];

        if (doctor_hero_column_exists('chambers', 'status')) {
            $where[] = "(c.status = 'active' OR c.status = 'published' OR c.status IS NULL OR c.status = '')";
        }

        $order_by = doctor_hero_column_exists('chambers', 'sort_order')
            ? 'c.sort_order ASC, c.id ASC'
            : 'c.id ASC';

        try {
            $stmt = $pdo->prepare("
                SELECT h.district_id
                FROM chambers c
                INNER JOIN hospitals h ON h.id = c.hospital_id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY {$order_by}
                LIMIT 1
            ");
            $stmt->execute([':doctor_id' => $doctor_id]);

            return max(0, (int)$stmt->fetchColumn());
        } catch (Throwable $e) {
            return 0;
        }
    }
}

if (!function_exists('doctor_hero_resolve_district_id')) {
    function doctor_hero_resolve_district_id(array $doctor): int
    {
        $doctor_district_id = (int)($doctor['doctor_district_id'] ?? 0);

        if ($doctor_district_id > 0) {
            return $doctor_district_id;
        }

        return doctor_hero_first_chamber_hospital_district_id(
            (int)($doctor['id'] ?? 0)
        );
    }
}

if (!function_exists('doctor_hero_get_chambers')) {
    function doctor_hero_get_chambers(int $doctor_id): array
    {
        global $pdo;

        if ($doctor_id <= 0 || !doctor_hero_table_exists('chambers')) {
            return [];
        }

        $select = ['c.*'];
        $join = '';

        if (doctor_hero_table_exists('hospitals')) {
            $select[] = 'h.name AS hospital_name';
            $select[] = doctor_hero_column_exists('hospitals', 'name_bn') ? 'h.name_bn AS hospital_name_bn' : "'' AS hospital_name_bn";
            $select[] = doctor_hero_column_exists('hospitals', 'slug') ? 'h.slug AS hospital_slug' : "'' AS hospital_slug";
            $select[] = doctor_hero_column_exists('hospitals', 'address') ? 'h.address AS hospital_address' : "'' AS hospital_address";
            $select[] = doctor_hero_column_exists('hospitals', 'address_bn') ? 'h.address_bn AS hospital_address_bn' : "'' AS hospital_address_bn";
            $select[] = doctor_hero_column_exists('hospitals', 'phone') ? 'h.phone AS hospital_phone' : "'' AS hospital_phone";
            $join = 'LEFT JOIN hospitals h ON h.id = c.hospital_id';
        } else {
            $select[] = "'' AS hospital_name";
            $select[] = "'' AS hospital_name_bn";
            $select[] = "'' AS hospital_slug";
            $select[] = "'' AS hospital_address";
            $select[] = "'' AS hospital_address_bn";
            $select[] = "'' AS hospital_phone";
        }

        $where = ['c.doctor_id = :doctor_id'];

        if (doctor_hero_column_exists('chambers', 'status')) {
            $where[] = "(c.status = 'active' OR c.status = 'published' OR c.status IS NULL OR c.status = '')";
        }

        $order_by = 'c.id ASC';

        if (doctor_hero_column_exists('chambers', 'sort_order')) {
            $order_by = 'c.sort_order ASC, c.id ASC';
        }

        try {
            $stmt = $pdo->prepare("
                SELECT " . implode(', ', $select) . "
                FROM chambers c
                {$join}
                WHERE " . implode(' AND ', $where) . "
                ORDER BY {$order_by}
            ");
            $stmt->execute([':doctor_id' => $doctor_id]);

            return $stmt->fetchAll();
        } catch (Throwable $e) {
            return [];
        }
    }
}

if (!function_exists('doctor_hero_years_experience_text')) {
    function doctor_hero_years_experience_text($starting_year): string
    {
        $starting_year = (int)trim((string)($starting_year ?? ''));
        $current_year = (int)date('Y');

        $years_label = __t('years', defined('CURRENT_LANG') && CURRENT_LANG === 'bn' ? 'বছর' : 'Years');

        /* Accept either a start year (for example, 2013) or saved experience years (for example, 13). */
        if ($starting_year >= 1900 && $starting_year <= $current_year) {
            $years = max(0, $current_year - $starting_year);
            return doctor_hero_local_number($years . '+') . ' ' . $years_label;
        }

        if ($starting_year > 0 && $starting_year <= 100) {
            return doctor_hero_local_number($starting_year . '+') . ' ' . $years_label;
        }

        return '';
    }
}

if (!function_exists('doctor_hero_money_text')) {
    function doctor_hero_money_text($amount): string
    {
        $amount = (float)$amount;

        if ($amount <= 0) {
            return '';
        }

        $formatted = number_format($amount, 0);

        return '৳' . doctor_hero_local_number($formatted);
    }
}

if (!function_exists('doctor_hero_rating_text')) {
    function doctor_hero_rating_text($rating): string
    {
        $rating = (float)$rating;

        if ($rating <= 0) {
            return '';
        }

        $formatted = rtrim(rtrim(number_format($rating, 1), '0'), '.');

        return doctor_hero_local_number($formatted);
    }
}

if (!function_exists('doctor_hero_count_text')) {
    function doctor_hero_count_text($count): string
    {
        return doctor_hero_local_number(number_format((int)$count));
    }
}

if (!function_exists('doctor_hero_card_filename')) {
    function doctor_hero_card_filename(string $title): string
    {
        $title = trim((string)$title);

        if (function_exists('iconv')) {
            $ascii_title = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $title);

            if ($ascii_title !== false) {
                $title = $ascii_title;
            }
        }

        $title = strtolower($title);
        $title = preg_replace('/[^a-z0-9]+/', '-', $title);
        $title = trim((string)$title, '-');

        return ($title !== '' ? $title : 'doctor-profile-card') . '.jpg';
    }
}

if (!function_exists('doctor_hero_image_alt_text')) {
    function doctor_hero_image_alt_text(string $name, string $specialty = '', string $district = ''): string
    {
        $parts = [];

        if (trim($name) !== '') {
            $parts[] = trim($name);
        }

        if (trim($specialty) !== '') {
            $parts[] = trim($specialty);
        }

        if (trim($district) !== '') {
            $parts[] = trim($district);
        }

        return implode(' - ', $parts);
    }
}

if (!function_exists('doctor_hero_get_claim_status')) {
    function doctor_hero_get_claim_status(int $doctor_id): array
    {
        global $pdo;

        $status = [
            'is_claimed' => false,
            'has_pending_claim' => false,
            'label' => __t('unclaimed_profile', 'Unclaimed profile'),
        ];

        if ($doctor_id <= 0) {
            return $status;
        }

        if (doctor_hero_table_exists('users') && doctor_hero_column_exists('users', 'claimed_doctor_id')) {
            try {
                $where = ['claimed_doctor_id = :doctor_id'];

                if (doctor_hero_column_exists('users', 'status')) {
                    $where[] = "(status = 'active' OR status IS NULL OR status = '')";
                }

                $stmt = $pdo->prepare('SELECT id FROM users WHERE ' . implode(' AND ', $where) . ' LIMIT 1');
                $stmt->execute([':doctor_id' => $doctor_id]);

                if ((int)$stmt->fetchColumn() > 0) {
                    $status['is_claimed'] = true;
                    $status['label'] = __t('claimed_profile', 'Claimed profile');
                    return $status;
                }
            } catch (Throwable $e) {
                // Keep frontend safe.
            }
        }

        if (doctor_hero_table_exists('profile_claims')) {
            try {
                $stmt = $pdo->prepare("
                    SELECT status
                    FROM profile_claims
                    WHERE claim_type = 'doctor'
                    AND doctor_id = :doctor_id
                    ORDER BY id DESC
                    LIMIT 1
                ");
                $stmt->execute([':doctor_id' => $doctor_id]);

                $claim_status = strtolower(trim((string)$stmt->fetchColumn()));

                if ($claim_status === 'approved') {
                    $status['is_claimed'] = true;
                    $status['label'] = __t('claimed_profile', 'Claimed profile');
                    return $status;
                }

                if ($claim_status === 'pending') {
                    $status['has_pending_claim'] = true;
                }
            } catch (Throwable $e) {
                // Ignore claim lookup failure.
            }
        }

        return $status;
    }
}

if (!function_exists('verified_badge')) {
    function verified_badge(int $is_verified): string
    {
        if ($is_verified !== 1) {
            return '';
        }

        return '<span class="medic-verified-badge">✓ Verified</span>';
    }
}

/*
|--------------------------------------------------------------------------
| Hero Data
|--------------------------------------------------------------------------
*/

$doctor_name = lang_text(
    (string)($doctor['name'] ?? ''),
    (string)($doctor['name_bn'] ?? '')
);

$doctor_degree = lang_text(
    (string)($doctor['degree'] ?? ''),
    (string)($doctor['degree_bn'] ?? '')
);

$doctor_training = doctor_hero_lang_value(
    $doctor,
    ['training', 'training_text', 'professional_training'],
    ['training_bn', 'training_text_bn', 'professional_training_bn']
);

$doctor_fellowship = doctor_hero_lang_value(
    $doctor,
    ['fellowship', 'fellowship_text', 'fellowships'],
    ['fellowship_bn', 'fellowship_text_bn', 'fellowships_bn']
);

/*
|--------------------------------------------------------------------------
| Training + Fellowship Display
|--------------------------------------------------------------------------
| Keep both values together on the existing Training/Courses line.
| No layout or design change is made.
|--------------------------------------------------------------------------
*/

$doctor_training_fellowship = implode('<br>', array_values(array_filter([
    trim((string)$doctor_training),
    trim((string)$doctor_fellowship),
], static function ($value): bool {
    return $value !== '';
})));


/*
|--------------------------------------------------------------------------
| Specialty From specialties Table
|--------------------------------------------------------------------------
| Only specialty_id is used for this lookup:
| specialties.id -> specialties.name / specialties.name_bn
| No other specialty field is required.
*/

$doctor_specialty_row = doctor_hero_get_specialty_by_id(
    (int)($doctor['specialty_id'] ?? 0)
);

$doctor_specialty_name = lang_text(
    $doctor_specialty_row['name'] !== ''
        ? $doctor_specialty_row['name']
        : (string)($doctor['specialty_name'] ?? ''),
    $doctor_specialty_row['name_bn'] !== ''
        ? $doctor_specialty_row['name_bn']
        : (string)($doctor['specialty_name_bn'] ?? '')
);

$doctor_designation = lang_text(
    (string)($doctor['designation'] ?? ''),
    (string)($doctor['designation_bn'] ?? '')
);

/*
|--------------------------------------------------------------------------
| District Name
|--------------------------------------------------------------------------
| Uses the exact same fallback order as breadcrumb.php.
|--------------------------------------------------------------------------
*/

$doctor_district_name = '';
$doctor_resolved_district_id = doctor_hero_resolve_district_id($doctor);
$doctor_resolved_district = doctor_hero_get_district_pair_by_id($doctor_resolved_district_id);

if ($doctor_resolved_district['en'] !== '' || $doctor_resolved_district['bn'] !== '') {
    $doctor_district_name = doctor_hero_lang_value(
        $doctor_resolved_district,
        ['en'],
        ['bn']
    );
}

if ($doctor_district_name === '') {
    $doctor_district_name = doctor_hero_lang_value(
        $doctor,
        ['district_name', 'district'],
        ['district_name_bn', 'district_bn']
    );
}

$doctor_image_alt = doctor_hero_image_alt_text(
    $doctor_name,
    $doctor_specialty_name,
    $doctor_district_name
);

$doctor_default_image = doctor_hero_default_doctor_image_setting($doctor);

$doctor_image_url = doctor_hero_asset_url(
    (string)($doctor['image'] ?? ''),
    $doctor_default_image
);

$chambers = doctor_hero_get_chambers((int)($doctor['id'] ?? 0));

if (!$chambers && function_exists('get_doctor_chambers')) {
    $chambers = get_doctor_chambers((int)($doctor['id'] ?? 0));
}

$first_chamber = $chambers[0] ?? [];

/*
|--------------------------------------------------------------------------
| Primary Hospital Name
|--------------------------------------------------------------------------
*/

$doctor_primary_hospital_name = lang_text(
    trim((string)($doctor['primary_hospital'] ?? '')),
    trim((string)($doctor['primary_hospital_bn'] ?? ''))
);

if ($doctor_primary_hospital_name === '' && !empty($doctor['hospital_id']) && doctor_hero_table_exists('hospitals')) {
    try {
        $stmt = $pdo->prepare("
            SELECT
                name,
                " . (doctor_hero_column_exists('hospitals', 'name_bn') ? "name_bn" : "'' AS name_bn") . "
            FROM hospitals
            WHERE id = :hospital_id
            LIMIT 1
        ");

        $stmt->execute([
            ':hospital_id' => (int)$doctor['hospital_id'],
        ]);

        $primary_hospital_row = $stmt->fetch();

        if ($primary_hospital_row) {
            $doctor_primary_hospital_name = lang_text(
                trim((string)($primary_hospital_row['name'] ?? '')),
                trim((string)($primary_hospital_row['name_bn'] ?? ''))
            );
        }
    } catch (Throwable $e) {
        $doctor_primary_hospital_name = '';
    }
}

if ($doctor_primary_hospital_name === '') {
    $doctor_primary_hospital_name = lang_text(
        doctor_hero_chamber_value($first_chamber, ['hospital_name', 'name', 'chamber_name']),
        doctor_hero_chamber_value($first_chamber, ['hospital_name_bn', 'name_bn', 'chamber_name_bn'])
    );
}

$first_chamber_fee = (float)($first_chamber['consultation_fee'] ?? 0);

if ($first_chamber_fee <= 0) {
    $first_chamber_fee = (float)($doctor['consultation_fee'] ?? 0);
}

$doctor_experience_text = doctor_hero_years_experience_text($doctor['experience_years'] ?? '');

$doctor_claim_status = doctor_hero_get_claim_status((int)($doctor['id'] ?? 0));
$doctor_is_claimed = (bool)$doctor_claim_status['is_claimed'];
$doctor_has_pending_claim = (bool)$doctor_claim_status['has_pending_claim'];
$doctor_claim_label = (string)$doctor_claim_status['label'];

$doctor_claim_label = lang_text(
    $doctor_claim_label,
    __t($doctor_is_claimed ? 'claimed_profile' : 'unclaimed_profile', $doctor_claim_label)
);

$doctor_rating_number = (float)($doctor['rating'] ?? 0);
$doctor_reviews_number = (int)($doctor['reviews_count'] ?? 0);

$doctor_bmdc_number = trim((string)($doctor['bmdc_number'] ?? ''));

$doctor_specialty_tags = [];
$doctor_tag_sources = [
    $doctor_specialty_name,
    doctor_hero_lang_value(
        $doctor,
        ['sub_specialty', 'secondary_specialty', 'specialties'],
        ['sub_specialty_bn', 'secondary_specialty_bn', 'specialties_bn']
    ),
];

foreach ($doctor_tag_sources as $doctor_tag_source) {
    foreach (preg_split('/\s*[,|]\s*/u', (string)$doctor_tag_source) ?: [] as $doctor_tag) {
        $doctor_tag = trim($doctor_tag);

        if ($doctor_tag === '') {
            continue;
        }

        $doctor_tag_key = function_exists('mb_strtolower')
            ? mb_strtolower($doctor_tag, 'UTF-8')
            : strtolower($doctor_tag);

        if (!isset($doctor_specialty_tags[$doctor_tag_key])) {
            $doctor_specialty_tags[$doctor_tag_key] = $doctor_tag;
        }
    }
}

$doctor_specialty_tags = array_slice(array_values($doctor_specialty_tags), 0, 3);

$doctor_fee_text = $first_chamber_fee > 0
    ? doctor_hero_money_text($first_chamber_fee)
    : '';

/*
|--------------------------------------------------------------------------
| Share Chamber Name
|--------------------------------------------------------------------------
| Uses the first available chamber with the same page-language fallback.
|--------------------------------------------------------------------------
*/

$doctor_share_chamber_name = doctor_hero_lang_value(
    $first_chamber,
    ['name', 'chamber_name', 'hospital_name'],
    ['name_bn', 'chamber_name_bn', 'hospital_name_bn']
);

/*
|--------------------------------------------------------------------------
| Share Title
|--------------------------------------------------------------------------
| Keep Name - Specialty - District as the first and most important line.
|--------------------------------------------------------------------------
*/

$doctor_share_title_parts = array_values(array_filter([
    trim((string)$doctor_name),
    trim((string)$doctor_specialty_name),
    trim((string)$doctor_district_name),
], static function ($value): bool {
    return $value !== '';
}));

$doctor_share_title = implode(' - ', $doctor_share_title_parts);

if ($doctor_share_title === '') {
    $doctor_share_title = trim((string)($page_title ?? $doctor_name));
}

/*
|--------------------------------------------------------------------------
| English Photo Card Filename
|--------------------------------------------------------------------------
| Keep the public JPG name in English even when the current page language
| is Bangla. The share text and card content still follow CURRENT_LANG.
|--------------------------------------------------------------------------
*/

$doctor_card_name_en = trim((string)($doctor['name'] ?? ''));

if ($doctor_card_name_en === '') {
    $doctor_card_name_en = $doctor_name;
}

$doctor_card_specialty_en = trim((string)($doctor_specialty_row['name'] ?? ''));

if ($doctor_card_specialty_en === '') {
    $doctor_card_specialty_en = trim((string)($doctor['specialty_name'] ?? ''));
}

if ($doctor_card_specialty_en === '') {
    $doctor_card_specialty_en = $doctor_specialty_name;
}

$doctor_card_district_en = trim((string)($doctor_resolved_district['en'] ?? ''));

if ($doctor_card_district_en === '') {
    $doctor_card_district_en = trim((string)($doctor['district_name'] ?? $doctor['district'] ?? ''));
}

if ($doctor_card_district_en === '') {
    $doctor_card_district_en = $doctor_district_name;
}

$doctor_card_english_title = implode(' - ', array_values(array_filter([
    $doctor_card_name_en,
    $doctor_card_specialty_en,
    $doctor_card_district_en,
], static function ($value): bool {
    return trim((string)$value) !== '';
})));

if ($doctor_card_english_title === '') {
    $doctor_card_english_title = $doctor_share_title;
}

/*
|--------------------------------------------------------------------------
| Photo Card JPG Metadata
|--------------------------------------------------------------------------
| The server endpoint embeds these values into the downloaded JPG file.
|--------------------------------------------------------------------------
*/

$doctor_card_site_name = doctor_hero_site_setting('site_name', 'MedicBD');
$doctor_card_generator_url = function_exists('site_url')
    ? site_url('doctor/generate-doctor-card.php')
    : doctor_hero_asset_url('doctor/generate-doctor-card.php');

$doctor_card_rating_stars = $doctor_rating_number > 0
    ? max(1, min(5, (int)round($doctor_rating_number)))
    : 0;

$doctor_card_subject = __t('doctor_profile', 'Doctor Profile') . ' - ' . $doctor_share_title;

if ($doctor_rating_number > 0) {
    $doctor_card_subject .= ' | ' . __t('rating', 'Rating') . ': ' . number_format($doctor_rating_number, 1) . '/5';
}

$doctor_card_tags = array_values(array_filter([
    $doctor_name,
    $doctor_specialty_name,
    $doctor_district_name,
    __t('doctor_profile', 'Doctor Profile'),
    $doctor_card_site_name,
], static function ($value): bool {
    return trim((string)$value) !== '';
}));

$doctor_rating_text = $doctor_rating_number > 0
    ? doctor_hero_rating_text($doctor_rating_number)
    : '';

$doctor_review_text = $doctor_reviews_number > 0
    ? doctor_hero_count_text($doctor_reviews_number)
    : '';

$doctor_hero_share_title_json = json_encode(
    $doctor_share_title,
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
);

$doctor_hero_profile_details_json = json_encode(
    array_values(array_filter([
        trim((string)$doctor_degree),
        trim((string)$doctor_designation),
        trim((string)$doctor_primary_hospital_name),
        trim((string)$doctor_share_chamber_name) !== ''
            ? (defined('CURRENT_LANG') && CURRENT_LANG === 'bn' ? 'চেম্বার:-' : 'Chamber:-')
            : '',
        trim((string)$doctor_share_chamber_name),
    ], static function ($value): bool {
        return $value !== '';
    })),
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
);

$doctor_hero_profile_card_json = json_encode([
    'siteName' => doctor_hero_site_setting('site_name', 'MedicBD'),
    'profileLabel' => __t('doctor_profile', 'Doctor Profile'),
    'degreeLabel' => __t('degree', 'Degree'),
    'designationLabel' => __t('designation', 'Designation'),
    'hospitalLabel' => __t('primary_hospital', 'Primary Hospital'),
    'chamberLabel' => defined('CURRENT_LANG') && CURRENT_LANG === 'bn' ? 'চেম্বার' : 'Chamber',
    'name' => $doctor_name,
    'title' => $doctor_share_title,
    'degree' => $doctor_degree,
    'specialty' => $doctor_specialty_name,
    'designation' => $doctor_designation,
    'hospital' => $doctor_primary_hospital_name,
    'chamber' => $doctor_share_chamber_name,
    'image' => $doctor_image_url,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

$doctor_hero_profile_card_meta_json = json_encode([
    'endpoint' => $doctor_card_generator_url,
    'title' => $doctor_share_title,
    'filename' => doctor_hero_card_filename($doctor_card_english_title),
    'lang' => (defined('CURRENT_LANG') && CURRENT_LANG === 'bn') ? 'bn' : 'en',
    'subject' => $doctor_card_subject,
    'tags' => implode(', ', $doctor_card_tags),
    'author' => $doctor_card_site_name,
    'rating' => $doctor_card_rating_stars,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

?>

<link rel="stylesheet" href="<?= e(site_url('assets/css/doctor-hero.css')) ?>">


<section class="medic-profile-hero" aria-labelledby="doctor-profile-name">
    <div class="medic-profile-main">
        <div class="medic-doctor-photo-wrap">
            <img
                class="medic-doctor-photo"
                src="<?= e($doctor_image_url) ?>"
                width="400"
                height="530"
                alt="<?= e($doctor_image_alt) ?>"
                decoding="async"
                fetchpriority="high"
            >
        </div>

        <div class="medic-profile-content">
            <div class="medic-name-line">
                <h1 id="doctor-profile-name"><?= e($doctor_name) ?></h1>
            </div>

            <div class="medic-doctor-info-list">
                <?php if ($doctor_degree !== ''): ?>
                    <p class="medic-info-line medic-degree-line"><?= e($doctor_degree) ?></p>
                <?php endif; ?>

                <?php if ($doctor_training_fellowship !== ''): ?>
                    <p class="medic-info-line medic-training-line">
                        <?= doctor_hero_multiline_text($doctor_training_fellowship) ?>
                    </p>
                <?php endif; ?>

                <?php if (!empty($doctor_specialty_tags)): ?>
                    <div class="medic-specialty-tags" aria-label="<?= e(__t('specialties', 'Specialties')) ?>">
                        <?php foreach ($doctor_specialty_tags as $doctor_specialty_tag): ?>
                            <span class="medic-specialty-tag"><?= e($doctor_specialty_tag) ?></span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if ($doctor_designation !== ''): ?>
                    <p class="medic-info-line medic-designation-line"><?= e($doctor_designation) ?></p>
                <?php endif; ?>

                <?php if ($doctor_primary_hospital_name !== ''): ?>
                    <p class="medic-info-line"><?= e($doctor_primary_hospital_name) ?></p>
                <?php endif; ?>
            </div>

            <?php if ($doctor_experience_text !== '' || $doctor_bmdc_number !== '' || $doctor_rating_text !== ''): ?>
                <div class="medic-hero-meta">
                    <?php if ($doctor_experience_text !== ''): ?>
                        <div class="medic-meta-item">
                            <span class="medic-meta-label"><?= e(__t('total_experience', 'Total Experience')) ?></span>
                            <span class="medic-meta-value"><?= e($doctor_experience_text) ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if ($doctor_bmdc_number !== ''): ?>
                        <div class="medic-meta-item">
                            <span class="medic-meta-label"><?= e(__t('bmdc_number', 'BMDC Number')) ?></span>
                            <span class="medic-meta-value"><?= e($doctor_bmdc_number) ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if ($doctor_rating_text !== ''): ?>
                        <div class="medic-meta-item">
                            <span class="medic-meta-label"><?= e(__t('total_rating', 'Total Rating')) ?></span>
                            <span class="medic-meta-value">
                                <span class="medic-rating-star" aria-hidden="true">★</span>
                                <?= e($doctor_rating_text) ?>
                                <?php if ($doctor_review_text !== ''): ?>
                                    <?= e('(' . $doctor_review_text . ')') ?>
                                <?php endif; ?>
                            </span>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="medic-profile-actions">
            <button
                type="button"
                class="medic-share-button"
                data-doctor-share
                data-share-title="<?= e($doctor_hero_share_title_json) ?>"
                data-profile-details="<?= e($doctor_hero_profile_details_json) ?>"
                data-profile-card="<?= e($doctor_hero_profile_card_json) ?>"
                data-profile-card-meta="<?= e($doctor_hero_profile_card_meta_json) ?>"
                data-copied-label="<?= e(__t('copied', 'Copied')) ?>"
                aria-label="<?= e(__t('share_doctor_profile', 'Share doctor profile')) ?>"
                title="<?= e(__t('share', 'Share')) ?>"
            >
                <svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="18" cy="5" r="3"></circle>
                    <circle cx="6" cy="12" r="3"></circle>
                    <circle cx="18" cy="19" r="3"></circle>
                    <path d="m8.6 13.5 6.8 4"></path>
                    <path d="m15.4 6.5-6.8 4"></path>
                </svg>
            </button>

            <div
                class="medic-social-share-menu"
                data-social-share-menu
                hidden
                aria-label="<?= e(__t('share', 'Share')) ?>"
            >
                <div class="medic-social-share-grid">
                    <a class="medic-social-share-item" data-share-network="facebook" href="#" target="_blank" rel="noopener noreferrer">
                        <span class="medic-social-share-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24"><path d="M13.7 21v-8h2.7l.4-3h-3.1V8.1c0-.9.3-1.5 1.6-1.5H17V3.9c-.3 0-1.1-.1-2.1-.1-2.1 0-3.5 1.3-3.5 3.7V10H9v3h2.4v8h2.3Z"/></svg>
                        </span>
                        <span>Facebook</span>
                    </a>

                    <a class="medic-social-share-item" data-share-network="whatsapp" href="#" target="_blank" rel="noopener noreferrer">
                        <span class="medic-social-share-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24"><path d="M20.5 3.5A11.8 11.8 0 0 0 12.1 0C5.6 0 .3 5.3.3 11.8c0 2.1.6 4.2 1.7 6L0 24l6.4-1.7a11.8 11.8 0 0 0 5.7 1.5h.1c6.5 0 11.8-5.3 11.8-11.8 0-3.2-1.3-6.1-3.5-8.5ZM12.1 21.8a9.9 9.9 0 0 1-5.1-1.4l-.4-.2-3.8 1 1-3.7-.3-.4a9.8 9.8 0 0 1-1.5-5.2c0-5.4 4.4-9.8 9.8-9.8 2.6 0 5.1 1 6.9 2.9a9.7 9.7 0 0 1 2.9 6.9c0 5.4-4.4 9.8-9.8 9.8h.3Zm5.4-7.4c-.3-.1-1.8-.9-2.1-1-.3-.1-.5-.1-.7.2-.2.3-.8 1-.9 1.2-.2.2-.3.2-.6.1a7.9 7.9 0 0 1-2.3-1.4 8.7 8.7 0 0 1-1.6-2c-.2-.3 0-.4.1-.5l.4-.4c.1-.1.2-.3.3-.5.1-.2 0-.4 0-.5L9.2 7c-.2-.5-.5-.4-.7-.4h-.6c-.2 0-.5.1-.8.4-.3.3-1 1-1 2.4s1.1 2.8 1.2 3c.2.2 2.1 3.2 5.1 4.5.7.3 1.3.5 1.7.6.7.2 1.4.2 1.9.1.6-.1 1.8-.7 2-1.4.3-.7.3-1.3.2-1.4-.1-.2-.3-.3-.6-.4Z"/></svg>
                        </span>
                        <span>WhatsApp</span>
                    </a>

                    <a class="medic-social-share-item" data-share-network="telegram" href="#" target="_blank" rel="noopener noreferrer">
                        <span class="medic-social-share-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24"><path d="M21.9 3.6 18.7 20c-.2 1.2-.9 1.5-1.8.9l-5-3.7-2.4 2.3c-.3.3-.5.5-1 .5l.4-5 9.1-8.2c.4-.4-.1-.6-.6-.3L6.1 13.7 1.3 12.2c-1-.3-1-1 .2-1.5L20.2 3.5c.9-.3 1.8.2 1.7.1Z"/></svg>
                        </span>
                        <span>Telegram</span>
                    </a>

                    <a class="medic-social-share-item" data-share-network="twitter" href="#" target="_blank" rel="noopener noreferrer">
                        <span class="medic-social-share-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24"><path d="M18.9 2H22l-6.8 7.8L23.2 22h-6.3l-4.9-7-6.1 7H2.8l7.3-8.4L2.4 2h6.5l4.4 6.3L18.9 2Zm-1.1 18h1.7L8 3.9H6.2L17.8 20Z"/></svg>
                        </span>
                        <span>Twitter (X)</span>
                    </a>

                    <a class="medic-social-share-item" data-share-network="linkedin" href="#" target="_blank" rel="noopener noreferrer">
                        <span class="medic-social-share-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24"><path d="M20.5 3h-17A.5.5 0 0 0 3 3.5v17a.5.5 0 0 0 .5.5h17a.5.5 0 0 0 .5-.5v-17a.5.5 0 0 0-.5-.5ZM8.3 18.3H5.7V9.9h2.6v8.4ZM7 8.7a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3Zm11.3 9.6h-2.6v-4.1c0-1 0-2.2-1.4-2.2s-1.6 1-1.6 2.1v4.2h-2.6V9.9h2.5V11h.1c.3-.7 1.2-1.4 2.5-1.4 2.7 0 3.2 1.8 3.2 4.1v4.6Z"/></svg>
                        </span>
                        <span>LinkedIn</span>
                    </a>

                    <button type="button" class="medic-social-share-item" data-share-network="copy">
                        <span class="medic-social-share-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24"><path d="M16 1H4a2 2 0 0 0-2 2v14h2V3h12V1Zm3 4H8a2 2 0 0 0-2 2v14a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2Zm0 16H8V7h11v14Z"/></svg>
                        </span>
                        <span>Copy Link</span>
                    </button>
                </div>
            </div>

            <?php if ($doctor_fee_text !== ''): ?>
                <div class="medic-fee-box">
                    <span class="medic-fee-label"><?= e(__t('consultation_fee', 'Consultation Fee')) ?></span>

                    <div class="medic-fee-value-row">
                        <span class="medic-fee-value"><?= e($doctor_fee_text) ?></span>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </div>
</section>

<script src="<?= e(site_url('assets/js/doctor-hero.js')) ?>" defer></script>
