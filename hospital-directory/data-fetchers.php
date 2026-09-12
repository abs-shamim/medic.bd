<?php
/**
 * Hospital directory database and location helpers.
 */

function hp_table_exists(string $table): bool
{
    global $pdo;

    static $request_cache = [];

    $table = trim($table);

    if ($table === '') {
        return false;
    }

    if (array_key_exists($table, $request_cache)) {
        return $request_cache[$table];
    }

    $resolver = static function () use ($pdo, $table): bool {
        try {
            $stmt = $pdo->prepare("\n                SELECT COUNT(*)\n                FROM INFORMATION_SCHEMA.TABLES\n                WHERE TABLE_SCHEMA = DATABASE()\n                AND TABLE_NAME = :table\n            ");
            $stmt->execute([':table' => $table]);

            return (int)$stmt->fetchColumn() > 0;
        } catch (Throwable $e) {
            return false;
        }
    };

    $value = function_exists('medic_schema_exists_cached')
        ? medic_schema_exists_cached('table:' . $table, $resolver)
        : $resolver();

    $request_cache[$table] = $value;

    return $value;
}

function hp_column_exists(string $table, string $column): bool
{
    global $pdo;

    static $request_cache = [];

    $table = trim($table);
    $column = trim($column);
    $cache_key = $table . '.' . $column;

    if ($table === '' || $column === '') {
        return false;
    }

    if (array_key_exists($cache_key, $request_cache)) {
        return $request_cache[$cache_key];
    }

    $resolver = static function () use ($pdo, $table, $column): bool {
        try {
            $stmt = $pdo->prepare("\n                SELECT COUNT(*)\n                FROM INFORMATION_SCHEMA.COLUMNS\n                WHERE TABLE_SCHEMA = DATABASE()\n                AND TABLE_NAME = :table\n                AND COLUMN_NAME = :column\n            ");
            $stmt->execute([
                ':table' => $table,
                ':column' => $column,
            ]);

            return (int)$stmt->fetchColumn() > 0;
        } catch (Throwable $e) {
            return false;
        }
    };

    $value = function_exists('medic_schema_exists_cached')
        ? medic_schema_exists_cached('column:' . $cache_key, $resolver)
        : $resolver();

    $request_cache[$cache_key] = $value;

    return $value;
}


/*
|--------------------------------------------------------------------------
| Site Settings Helpers
|--------------------------------------------------------------------------
| This page uses values from admin/site-settings.php / site_settings table.
|--------------------------------------------------------------------------
*/

function hp_site_setting(string $key, string $default = ''): string
{
    global $pdo;

    static $settings_cache = null;

    if ($settings_cache === null) {
        $settings_cache = [];

        try {
            if (!hp_table_exists('site_settings')) {
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

function hp_site_name(): string
{
    return hp_site_setting('site_name', defined('APP_NAME') ? APP_NAME : 'Deluti');
}

function hp_setting_int(string $key, int $default = 10, int $min = 1, int $max = 100): int
{
    $value = (int)hp_site_setting($key, (string)$default);

    if ($value < $min) {
        return $default;
    }

    if ($value > $max) {
        return $max;
    }

    return $value;
}

function hp_setting_url(string $key, string $default = ''): string
{
    $value = hp_site_setting($key, $default);

    if ($value === '') {
        return '';
    }

    if (preg_match('/^https?:\/\//i', $value)) {
        return $value;
    }

    $value = ltrim($value, './');

    if (function_exists('site_url')) {
        return site_url($value);
    }

    return '/' . $value;
}

function hp_setting_color(string $key, string $default): string
{
    $value = hp_site_setting($key, $default);

    if (preg_match('/^#[0-9a-fA-F]{6}$/', $value)) {
        return $value;
    }

    return $default;
}

/*
|--------------------------------------------------------------------------
| Directory Language And Location Name Column
|--------------------------------------------------------------------------
| The URL is the final source of truth for the hospital directory.
| /bn/hospitals/... always uses name_bn.
|
| This intentionally does not depend on INFORMATION_SCHEMA checks when
| deciding the active name column. Some hosting environments can return
| incomplete metadata results even though name_bn exists in the table.
|--------------------------------------------------------------------------
*/
function hp_is_bangla_directory_request(): bool
{
    global $lang;

    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    $path = trim((string)$path, '/');
    $segments = array_values(array_filter(explode('/', $path), static function ($segment) {
        return trim((string)$segment) !== '';
    }));

    $hospitals_index = array_search('hospitals', $segments, true);

    /*
     * Supports:
     * /bn/hospitals
     * /bn/hospitals/dhaka-division
     * /project-folder/bn/hospitals
     */
    if (
        $hospitals_index !== false
        && $hospitals_index > 0
        && (($segments[$hospitals_index - 1] ?? '') === 'bn')
    ) {
        return true;
    }

    /*
     * Fallback for the standard language constant.
     */
    return isset($lang) && $lang === 'bn';
}

function hp_name_column(string $table = ''): string
{
    /*
     * Your divisions, districts and thanas tables have both:
     * name_en and name_bn.
     *
     * Use the URL language directly so Bangla location names cannot fall
     * back to English because of a metadata permission or cache issue.
     */
    return hp_is_bangla_directory_request() ? 'name_bn' : 'name_en';
}

function hp_row_slug(array $row): string
{
    if (!empty($row['slug'])) {
        return hp_url_slug((string)$row['slug']);
    }

    return hp_url_slug((string)($row['name'] ?? ''));
}

function hp_hospitals_url(array $params = []): string
{
    $division = trim((string)($params['division_slug'] ?? ''));
    $district = trim((string)($params['district_slug'] ?? ''));
    $thana = trim((string)($params['thana_slug'] ?? ''));
    $type = trim((string)($params['type_slug'] ?? ''));

    $path = 'hospitals';

    if ($district !== '') {
        $path .= '/' . hp_url_slug($district);

        if ($thana !== '' && $type !== '') {
            $path .= '/' . hp_url_slug($thana) . '/' . hp_url_slug($type);
        } elseif ($thana !== '') {
            $path .= '/' . hp_url_slug($thana);
        } elseif ($type !== '') {
            $path .= '/' . hp_url_slug($type);
        }
    } elseif ($division !== '') {
        $path .= '/' . hp_url_slug($division);
    } elseif ($type !== '') {
        $path .= '/' . hp_url_slug($type);
    }

    $query = hp_clean_query([
        'search' => $params['search'] ?? '',
        'service' => $params['service'] ?? '',
        'page' => $params['page'] ?? '',
    ]);

    return hp_front_url($path . ($query ? '?' . $query : ''));
}

function hp_get_divisions(): array
{
    global $pdo;

    if (!hp_table_exists('divisions')) {
        return [];
    }

    $name_col = hp_name_column('divisions');
    $select = ['id', "{$name_col} AS name"];
    $select[] = hp_column_exists('divisions', 'slug') ? 'slug' : "'' AS slug";
    $select[] = hp_column_exists('divisions', 'image') ? 'image' : "'' AS image";

    try {
        $stmt = $pdo->query("\n            SELECT " . implode(', ', $select) . "\n            FROM divisions\n            ORDER BY {$name_col} ASC\n        ");

        return $stmt->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

function hp_get_division_by_slug(string $slug): array
{
    $slug = hp_url_slug($slug);

    if ($slug === '') {
        return [];
    }

    foreach (hp_get_divisions() as $division) {
        $normal_slug = hp_row_slug($division);
        $division_slug = hp_url_slug((string)($division['name'] ?? '') . ' Division');

        if ($normal_slug === $slug || $division_slug === $slug) {
            return $division;
        }
    }

    return [];
}

function hp_get_division_by_id(int $division_id): array
{
    global $pdo;

    if ($division_id <= 0 || !hp_table_exists('divisions')) {
        return [];
    }

    $name_col = hp_name_column('divisions');
    $select = ['id', "{$name_col} AS name"];
    $select[] = hp_column_exists('divisions', 'slug') ? 'slug' : "'' AS slug";
    $select[] = hp_column_exists('divisions', 'image') ? 'image' : "'' AS image";

    try {
        $stmt = $pdo->prepare("\n            SELECT " . implode(', ', $select) . "\n            FROM divisions\n            WHERE id = :id\n            LIMIT 1\n        ");
        $stmt->execute([':id' => $division_id]);

        $row = $stmt->fetch();
        return $row ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

function hp_get_districts_by_division_id(int $division_id): array
{
    global $pdo;

    if ($division_id <= 0 || !hp_table_exists('districts')) {
        return [];
    }

    $name_col = hp_name_column('districts');
    $select = ['id', 'division_id', "{$name_col} AS name"];
    $select[] = hp_column_exists('districts', 'slug') ? 'slug' : "'' AS slug";
    $select[] = hp_column_exists('districts', 'image') ? 'image' : "'' AS image";

    try {
        $stmt = $pdo->prepare("\n            SELECT " . implode(', ', $select) . "\n            FROM districts\n            WHERE division_id = :division_id\n            ORDER BY {$name_col} ASC\n        ");
        $stmt->execute([':division_id' => $division_id]);

        return $stmt->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

function hp_get_district_by_slug_any(string $slug): array
{
    global $pdo;

    $slug = hp_url_slug($slug);

    if ($slug === '' || !hp_table_exists('districts')) {
        return [];
    }

    $name_col = hp_name_column('districts');
    $select = ['id', 'division_id', "{$name_col} AS name"];
    $select[] = hp_column_exists('districts', 'slug') ? 'slug' : "'' AS slug";
    $select[] = hp_column_exists('districts', 'image') ? 'image' : "'' AS image";

    try {
        $rows = $pdo->query("\n            SELECT " . implode(', ', $select) . "\n            FROM districts\n            ORDER BY {$name_col} ASC\n        ")->fetchAll();

        foreach ($rows as $row) {
            if (hp_row_slug($row) === $slug) {
                return $row;
            }
        }
    } catch (Throwable $e) {
        return [];
    }

    return [];
}

function hp_get_thanas_by_district_id(int $district_id): array
{
    global $pdo;

    if ($district_id <= 0 || !hp_table_exists('thanas')) {
        return [];
    }

    $name_col = hp_name_column('thanas');
    $select = ['id', 'district_id', "{$name_col} AS name"];
    $select[] = hp_column_exists('thanas', 'slug') ? 'slug' : "'' AS slug";
    $select[] = hp_column_exists('thanas', 'image') ? 'image' : "'' AS image";

    try {
        $stmt = $pdo->prepare("\n            SELECT " . implode(', ', $select) . "\n            FROM thanas\n            WHERE district_id = :district_id\n            ORDER BY {$name_col} ASC\n        ");
        $stmt->execute([':district_id' => $district_id]);

        return $stmt->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

function hp_get_thana_by_slug(int $district_id, string $slug): array
{
    $slug = hp_url_slug($slug);

    if ($district_id <= 0 || $slug === '') {
        return [];
    }

    foreach (hp_get_thanas_by_district_id($district_id) as $thana) {
        if (hp_row_slug($thana) === $slug) {
            return $thana;
        }
    }

    return [];
}

function hp_get_district_ids_by_division_id(int $division_id): array
{
    $ids = [];

    foreach (hp_get_districts_by_division_id($division_id) as $district) {
        $id = (int)($district['id'] ?? 0);

        if ($id > 0) {
            $ids[] = $id;
        }
    }

    return array_values(array_unique($ids));
}

function hp_safe_limit(int $limit): int
{
    return max(1, min(100, $limit));
}

