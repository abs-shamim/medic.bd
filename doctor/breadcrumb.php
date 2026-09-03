<?php
/*
|--------------------------------------------------------------------------
| Doctor Breadcrumb Section
|--------------------------------------------------------------------------
| Self-contained breadcrumb file.
| Location logic:
| 1. Use doctor_district_id / doctor_thana_id first.
| 2. If doctor location is not found, use first chamber hospital_id.
| 3. From hospitals table, use district_id / thana_id.
| 4. Thana is always optional.
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
            return $bangla;
        }

        return $english;
    }
}

if (!function_exists('front_url')) {
    function front_url(string $path = '', ?string $lang = null): string
    {
        $path = trim($path);

        if ($path !== '' && preg_match('/^https?:\/\//i', $path)) {
            return $path;
        }

        $lang = $lang ?: (defined('CURRENT_LANG') ? CURRENT_LANG : 'en');
        $path = trim($path, '/');

        if ($lang === 'bn') {
            return site_url($path !== '' ? 'bn/' . $path : 'bn');
        }

        return site_url($path);
    }
}

/*
|--------------------------------------------------------------------------
| Breadcrumb Helpers
|--------------------------------------------------------------------------
*/

if (!function_exists('doctor_breadcrumb_table_exists')) {
    function doctor_breadcrumb_table_exists(string $table): bool
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

if (!function_exists('doctor_breadcrumb_column_exists')) {
    function doctor_breadcrumb_column_exists(string $table, string $column): bool
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

if (!function_exists('doctor_breadcrumb_slugify')) {
    function doctor_breadcrumb_slugify(string $text): string
    {
        $text = trim($text);
        $text = preg_replace('/[^\p{L}\p{N}\s-]+/u', '', $text);
        $text = preg_replace('/[\s_]+/u', '-', (string)$text);
        $text = preg_replace('/-+/u', '-', (string)$text);

        if (function_exists('mb_strtolower')) {
            $text = mb_strtolower($text, 'UTF-8');
        } else {
            $text = strtolower($text);
        }

        return trim((string)$text, '-');
    }
}

if (!function_exists('doctor_breadcrumb_location_select_parts')) {
    function doctor_breadcrumb_location_select_parts(string $table): array
    {
        $select = ['id'];

        foreach (['name_en', 'name_bn', 'name', 'slug', 'district_id', 'division_id'] as $column) {
            if (doctor_breadcrumb_column_exists($table, $column)) {
                $select[] = $column;
            }
        }

        return array_values(array_unique($select));
    }
}

if (!function_exists('doctor_breadcrumb_location_row_by_id')) {
    function doctor_breadcrumb_location_row_by_id(string $table, int $id): array
    {
        global $pdo;

        if ($id <= 0 || !doctor_breadcrumb_table_exists($table)) {
            return [];
        }

        try {
            $stmt = $pdo->prepare('SELECT ' . implode(', ', doctor_breadcrumb_location_select_parts($table)) . " FROM {$table} WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => $id]);

            $row = $stmt->fetch();
            return is_array($row) ? $row : [];
        } catch (Throwable $e) {
            return [];
        }
    }
}

if (!function_exists('doctor_breadcrumb_location_label')) {
    function doctor_breadcrumb_location_label(array $row, string $fallback = ''): string
    {
        if (defined('CURRENT_LANG') && CURRENT_LANG === 'bn' && trim((string)($row['name_bn'] ?? '')) !== '') {
            return trim((string)$row['name_bn']);
        }

        foreach (['name_en', 'name', 'name_bn'] as $key) {
            if (trim((string)($row[$key] ?? '')) !== '') {
                return trim((string)$row[$key]);
            }
        }

        return trim($fallback);
    }
}

if (!function_exists('doctor_breadcrumb_location_slug')) {
    function doctor_breadcrumb_location_slug(array $row, string $fallback = ''): string
    {
        if (trim((string)($row['slug'] ?? '')) !== '') {
            return doctor_breadcrumb_slugify((string)$row['slug']);
        }

        foreach (['name_en', 'name', 'name_bn'] as $key) {
            if (trim((string)($row[$key] ?? '')) !== '') {
                return doctor_breadcrumb_slugify((string)$row[$key]);
            }
        }

        return doctor_breadcrumb_slugify($fallback);
    }
}

if (!function_exists('doctor_breadcrumb_first_chamber_hospital_location')) {
    function doctor_breadcrumb_first_chamber_hospital_location(int $doctor_id): array
    {
        global $pdo;

        if ($doctor_id <= 0) {
            return [];
        }

        if (
            !doctor_breadcrumb_table_exists('chambers') ||
            !doctor_breadcrumb_table_exists('hospitals') ||
            !doctor_breadcrumb_column_exists('chambers', 'doctor_id') ||
            !doctor_breadcrumb_column_exists('chambers', 'hospital_id')
        ) {
            return [];
        }

        $select = [
            'h.id AS hospital_id',
            doctor_breadcrumb_column_exists('hospitals', 'district_id') ? 'h.district_id AS district_id' : '0 AS district_id',
            doctor_breadcrumb_column_exists('hospitals', 'thana_id') ? 'h.thana_id AS thana_id' : '0 AS thana_id',
        ];

        $where = [
            'c.doctor_id = :doctor_id',
            'c.hospital_id > 0',
        ];

        if (doctor_breadcrumb_column_exists('chambers', 'status')) {
            $where[] = "(c.status = 'active' OR c.status = 'published' OR c.status IS NULL OR c.status = '')";
        }

        $order_by = 'c.id ASC';

        if (doctor_breadcrumb_column_exists('chambers', 'sort_order')) {
            $order_by = 'c.sort_order ASC, c.id ASC';
        }

        try {
            $stmt = $pdo->prepare("
                SELECT " . implode(', ', $select) . "
                FROM chambers c
                INNER JOIN hospitals h ON h.id = c.hospital_id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY {$order_by}
                LIMIT 1
            ");

            $stmt->execute([':doctor_id' => $doctor_id]);

            $row = $stmt->fetch();
            return is_array($row) ? $row : [];
        } catch (Throwable $e) {
            return [];
        }
    }
}

if (!function_exists('doctor_breadcrumb_specialty_row_by_doctor')) {
    function doctor_breadcrumb_specialty_row_by_doctor(array $doctor): array
    {
        global $pdo;

        $specialty_id = (int)($doctor['specialty_id'] ?? 0);

        if ($specialty_id <= 0 || !doctor_breadcrumb_table_exists('specialties')) {
            return [];
        }

        $select = ['id'];

        foreach (['name', 'name_bn', 'slug'] as $column) {
            if (doctor_breadcrumb_column_exists('specialties', $column)) {
                $select[] = $column;
            }
        }

        try {
            $stmt = $pdo->prepare('SELECT ' . implode(', ', array_values(array_unique($select))) . ' FROM specialties WHERE id = :id LIMIT 1');
            $stmt->execute([':id' => $specialty_id]);

            $row = $stmt->fetch();
            return is_array($row) ? $row : [];
        } catch (Throwable $e) {
            return [];
        }
    }
}

if (!function_exists('doctor_breadcrumb_specialty_label')) {
    function doctor_breadcrumb_specialty_label(array $row, string $fallback = ''): string
    {
        if (defined('CURRENT_LANG') && CURRENT_LANG === 'bn' && trim((string)($row['name_bn'] ?? '')) !== '') {
            return trim((string)$row['name_bn']);
        }

        foreach (['name', 'name_bn'] as $key) {
            if (trim((string)($row[$key] ?? '')) !== '') {
                return trim((string)$row[$key]);
            }
        }

        return trim($fallback);
    }
}

if (!function_exists('doctor_breadcrumb_specialty_slug_from_row')) {
    function doctor_breadcrumb_specialty_slug_from_row(array $row, string $fallback = ''): string
    {
        if (trim((string)($row['slug'] ?? '')) !== '') {
            return doctor_breadcrumb_slugify((string)$row['slug']);
        }

        if (trim((string)($row['name'] ?? '')) !== '') {
            return doctor_breadcrumb_slugify((string)$row['name']);
        }

        return doctor_breadcrumb_slugify($fallback);
    }
}

if (!function_exists('doctor_breadcrumb_specialty_slug_from_doctor')) {
    function doctor_breadcrumb_specialty_slug_from_doctor(array $doctor): string
    {
        global $pdo;

        foreach (['specialty_slug', 'speciality_slug'] as $key) {
            if (!empty($doctor[$key])) {
                return doctor_breadcrumb_slugify((string)$doctor[$key]);
            }
        }

        $specialty_id = (int)($doctor['specialty_id'] ?? 0);

        if ($specialty_id > 0 && doctor_breadcrumb_table_exists('specialties')) {
            $slug_column = doctor_breadcrumb_column_exists('specialties', 'slug') ? 'slug' : '';
            $name_column = doctor_breadcrumb_column_exists('specialties', 'name') ? 'name' : '';

            if ($slug_column !== '' || $name_column !== '') {
                $select_column = $slug_column !== '' ? $slug_column : $name_column;

                try {
                    $stmt = $pdo->prepare("SELECT {$select_column} FROM specialties WHERE id = :id LIMIT 1");
                    $stmt->execute([':id' => $specialty_id]);

                    $value = trim((string)$stmt->fetchColumn());

                    if ($value !== '') {
                        return doctor_breadcrumb_slugify($value);
                    }
                } catch (Throwable $e) {
                    return '';
                }
            }
        }

        if (!empty($doctor['specialty_name'])) {
            return doctor_breadcrumb_slugify((string)$doctor['specialty_name']);
        }

        return '';
    }
}

if (!function_exists('doctor_breadcrumb_doctors_url')) {
    function doctor_breadcrumb_doctors_url(array $filters = []): string
    {
        $district = trim((string)($filters['district_slug'] ?? $filters['district'] ?? ''));
        $thana = trim((string)($filters['thana_slug'] ?? $filters['thana'] ?? ''));
        $specialty = trim((string)($filters['specialty_slug'] ?? $filters['specialty'] ?? ''));
        $lang = $filters['lang'] ?? null;

        $segments = ['doctors'];

        if ($district !== '') {
            $segments[] = doctor_breadcrumb_slugify($district);
        }

        if ($thana !== '') {
            $segments[] = doctor_breadcrumb_slugify($thana);
        }

        if ($specialty !== '') {
            $segments[] = doctor_breadcrumb_slugify($specialty);
        }

        return front_url(implode('/', $segments), $lang);
    }
}

/*
|--------------------------------------------------------------------------
| Breadcrumb Data
|--------------------------------------------------------------------------
*/

$doctor_name = lang_text(
    (string)($doctor['name'] ?? ''),
    (string)($doctor['name_bn'] ?? '')
);

$breadcrumb_district_row = [];
$breadcrumb_thana_row = [];

$doctor_district_id = (int)($doctor['doctor_district_id'] ?? 0);
$doctor_thana_id = (int)($doctor['doctor_thana_id'] ?? 0);

/*
|--------------------------------------------------------------------------
| Step 1: Doctor Location ID
|--------------------------------------------------------------------------
| Use doctor_district_id first.
| Use doctor_thana_id only if available.
|--------------------------------------------------------------------------
*/

if ($doctor_district_id > 0) {
    $breadcrumb_district_row = doctor_breadcrumb_location_row_by_id('districts', $doctor_district_id);
}

if ($doctor_thana_id > 0) {
    $breadcrumb_thana_row = doctor_breadcrumb_location_row_by_id('thanas', $doctor_thana_id);
}

$breadcrumb_district_name = doctor_breadcrumb_location_label($breadcrumb_district_row);
$breadcrumb_thana_name = doctor_breadcrumb_location_label($breadcrumb_thana_row);

/*
|--------------------------------------------------------------------------
| Step 2 & 3: First Chamber Hospital Location Fallback
|--------------------------------------------------------------------------
| If doctor district is missing, use first chamber hospital_id.
| From hospitals table, use district_id and thana_id.
| Thana is optional.
|--------------------------------------------------------------------------
*/

if ($breadcrumb_district_name === '') {
    $hospital_location = doctor_breadcrumb_first_chamber_hospital_location((int)($doctor['id'] ?? 0));

    $hospital_district_id = (int)($hospital_location['district_id'] ?? 0);
    $hospital_thana_id = (int)($hospital_location['thana_id'] ?? 0);

    if ($hospital_district_id > 0) {
        $breadcrumb_district_row = doctor_breadcrumb_location_row_by_id('districts', $hospital_district_id);
        $breadcrumb_district_name = doctor_breadcrumb_location_label($breadcrumb_district_row);
    }

    if ($hospital_thana_id > 0) {
        $breadcrumb_thana_row = doctor_breadcrumb_location_row_by_id('thanas', $hospital_thana_id);
        $breadcrumb_thana_name = doctor_breadcrumb_location_label($breadcrumb_thana_row);
    }
}

/*
|--------------------------------------------------------------------------
| Specialty Data
|--------------------------------------------------------------------------
*/

$breadcrumb_specialty_row = doctor_breadcrumb_specialty_row_by_doctor($doctor);

$breadcrumb_specialty_name = doctor_breadcrumb_specialty_label(
    $breadcrumb_specialty_row,
    lang_text(
        trim((string)($doctor['specialty_name'] ?? '')),
        trim((string)($doctor['specialty_name_bn'] ?? ''))
    )
);

$breadcrumb_specialty_slug = doctor_breadcrumb_specialty_slug_from_row(
    $breadcrumb_specialty_row,
    doctor_breadcrumb_specialty_slug_from_doctor($doctor)
);

$breadcrumb_district_slug = doctor_breadcrumb_location_slug($breadcrumb_district_row, $breadcrumb_district_name);
$breadcrumb_thana_slug = doctor_breadcrumb_location_slug($breadcrumb_thana_row, $breadcrumb_thana_name);

/*
|--------------------------------------------------------------------------
| Breadcrumb URLs
|--------------------------------------------------------------------------
*/

$breadcrumb_district_url = ($breadcrumb_district_name !== '' && $breadcrumb_district_slug !== '')
    ? doctor_breadcrumb_doctors_url(['district_slug' => $breadcrumb_district_slug])
    : '';

$breadcrumb_thana_url = ($breadcrumb_district_name !== '' && $breadcrumb_thana_name !== '' && $breadcrumb_district_slug !== '' && $breadcrumb_thana_slug !== '')
    ? doctor_breadcrumb_doctors_url([
        'district_slug' => $breadcrumb_district_slug,
        'thana_slug' => $breadcrumb_thana_slug,
    ])
    : '';

$breadcrumb_specialty_url = ($breadcrumb_district_name !== '' && $breadcrumb_specialty_slug !== '' && $breadcrumb_district_slug !== '')
    ? doctor_breadcrumb_doctors_url([
        'district_slug' => $breadcrumb_district_slug,
        'thana_slug' => $breadcrumb_thana_slug,
        'specialty_slug' => $breadcrumb_specialty_slug,
    ])
    : '';

?>

<style>
.medic-breadcrumb {
    display: flex;
    flex-wrap: wrap;
    gap: 7px;
    align-items: center;
    margin: 0 0 18px;
    color: #57606a;
    font-size: 14px;
}

.medic-breadcrumb a {
    color: var(--doctor-primary, #0969da);
    text-decoration: none;
    font-weight: 600;
}

.medic-breadcrumb a:hover {
    text-decoration: underline;
}

.medic-breadcrumb span {
    color: #8c959f;
}
</style>

<nav class="medic-breadcrumb">
    <a href="<?= e(front_url()) ?>"><?= e(__t('home', 'Home')) ?></a>
    <span>/</span>
    <a href="<?= e(front_url('doctors')) ?>"><?= e(__t('doctors', 'Doctors')) ?></a>

    <?php if ($breadcrumb_district_name !== ''): ?>
        <span>/</span>
        <a href="<?= e($breadcrumb_district_url) ?>"><?= e($breadcrumb_district_name) ?></a>
    <?php endif; ?>

    <?php if ($breadcrumb_thana_name !== ''): ?>
        <span>/</span>
        <a href="<?= e($breadcrumb_thana_url) ?>"><?= e($breadcrumb_thana_name) ?></a>
    <?php endif; ?>

    <?php if ($breadcrumb_specialty_name !== '' && $breadcrumb_specialty_url !== ''): ?>
        <span>/</span>
        <a href="<?= e($breadcrumb_specialty_url) ?>"><?= e($breadcrumb_specialty_name) ?></a>
    <?php endif; ?>

    <span>/</span>
    <span><?= e($doctor_name) ?></span>
</nav>