<?php
/*
|--------------------------------------------------------------------------
| Doctor Related Doctors Section
|--------------------------------------------------------------------------
| Self-contained related-doctors file.
| Related doctors will show 3 items in list layout using doctor-card.php.
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

/*
|--------------------------------------------------------------------------
| Related Doctors Helpers
|--------------------------------------------------------------------------
*/

if (!function_exists('doctor_related_table_exists')) {
    function doctor_related_table_exists(string $table): bool
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

if (!function_exists('doctor_related_column_exists')) {
    function doctor_related_column_exists(string $table, string $column): bool
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

if (!function_exists('doctor_related_slugify')) {
    function doctor_related_slugify(string $text): string
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

if (!function_exists('doctor_related_address_id_by_name')) {
    function doctor_related_address_id_by_name(string $table, string $name): int
    {
        global $pdo;

        $name = trim($name);

        if ($name === '' || !doctor_related_table_exists($table)) {
            return 0;
        }

        $conditions = [];
        $params = [':name' => $name];

        foreach (['name_en', 'name_bn', 'name'] as $column) {
            if (doctor_related_column_exists($table, $column)) {
                $conditions[] = "{$column} = :name";
            }
        }

        if (empty($conditions)) {
            return 0;
        }

        try {
            $stmt = $pdo->prepare("
                SELECT id
                FROM {$table}
                WHERE " . implode(' OR ', $conditions) . "
                LIMIT 1
            ");
            $stmt->execute($params);

            return (int)$stmt->fetchColumn();
        } catch (Throwable $e) {
            return 0;
        }
    }
}

if (!function_exists('doctor_related_address_name_by_id')) {
    function doctor_related_address_name_by_id(string $table, int $id): string
    {
        global $pdo;

        if ($id <= 0 || !doctor_related_table_exists($table)) {
            return '';
        }

        $name_column = 'name';

        if (defined('CURRENT_LANG') && CURRENT_LANG === 'bn' && doctor_related_column_exists($table, 'name_bn')) {
            $name_column = 'name_bn';
        } elseif (doctor_related_column_exists($table, 'name_en')) {
            $name_column = 'name_en';
        } elseif (doctor_related_column_exists($table, 'name')) {
            $name_column = 'name';
        } elseif (doctor_related_column_exists($table, 'name_bn')) {
            $name_column = 'name_bn';
        } else {
            return '';
        }

        try {
            $stmt = $pdo->prepare("SELECT {$name_column} FROM {$table} WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => $id]);

            return trim((string)$stmt->fetchColumn());
        } catch (Throwable $e) {
            return '';
        }
    }
}

if (!function_exists('doctor_related_doctor_added_location_name')) {
    function doctor_related_doctor_added_location_name(array $doctor, string $type): string
    {
        $type = $type === 'thana' ? 'thana' : 'district';

        $id_key = $type === 'district' ? 'doctor_district_id' : 'doctor_thana_id';
        $table = $type === 'district' ? 'districts' : 'thanas';

        $location_id = (int)($doctor[$id_key] ?? 0);

        if ($location_id > 0) {
            $name = doctor_related_address_name_by_id($table, $location_id);

            if ($name !== '') {
                return $name;
            }
        }

        $possible_keys = $type === 'district'
            ? ['doctor_district', 'doctor_district_name', 'district', 'district_name', 'city']
            : ['doctor_thana', 'doctor_thana_name', 'thana', 'thana_name', 'area'];

        foreach ($possible_keys as $key) {
            if (!empty($doctor[$key])) {
                return trim((string)$doctor[$key]);
            }

            if (!empty($doctor[$key . '_bn']) && defined('CURRENT_LANG') && CURRENT_LANG === 'bn') {
                return trim((string)$doctor[$key . '_bn']);
            }
        }

        return '';
    }
}

if (!function_exists('doctor_related_specialty_id')) {
    function doctor_related_specialty_id(array $doctor): int
    {
        global $pdo;

        $specialty_id = (int)($doctor['specialty_id'] ?? 0);

        if ($specialty_id > 0) {
            return $specialty_id;
        }

        $specialty_name = trim((string)($doctor['specialty_name'] ?? ''));

        if ($specialty_name === '' || !doctor_related_table_exists('specialties')) {
            return 0;
        }

        $conditions = [];
        $params = [':specialty_name' => $specialty_name];

        if (doctor_related_column_exists('specialties', 'name')) {
            $conditions[] = 'name = :specialty_name';
        }

        if (doctor_related_column_exists('specialties', 'slug')) {
            $conditions[] = 'slug = :specialty_slug';
            $params[':specialty_slug'] = doctor_related_slugify($specialty_name);
        }

        if (empty($conditions)) {
            return 0;
        }

        try {
            $stmt = $pdo->prepare('
                SELECT id
                FROM specialties
                WHERE ' . implode(' OR ', $conditions) . '
                LIMIT 1
            ');
            $stmt->execute($params);

            return (int)$stmt->fetchColumn();
        } catch (Throwable $e) {
            return 0;
        }
    }
}

if (!function_exists('doctor_related_select_parts')) {
    function doctor_related_select_parts(): array
    {
        $select = ['d.*'];

        if (doctor_related_table_exists('specialties')) {
            $select[] = doctor_related_column_exists('specialties', 'name') ? 's.name AS specialty_name' : "'' AS specialty_name";
            $select[] = doctor_related_column_exists('specialties', 'name_bn') ? 's.name_bn AS specialty_name_bn' : "'' AS specialty_name_bn";
            $select[] = doctor_related_column_exists('specialties', 'slug') ? 's.slug AS specialty_slug' : "'' AS specialty_slug";
        } else {
            $select[] = "'' AS specialty_name";
            $select[] = "'' AS specialty_name_bn";
            $select[] = "'' AS specialty_slug";
        }

        if (doctor_related_table_exists('hospitals')) {
            $select[] = "COALESCE(NULLIF(GROUP_CONCAT(DISTINCT NULLIF(h.name, '') ORDER BY c.sort_order ASC, c.id ASC SEPARATOR ', '), ''), NULLIF(ph.name, ''), '') AS hospital_name";

            if (doctor_related_column_exists('hospitals', 'name_bn')) {
                $select[] = "COALESCE(NULLIF(GROUP_CONCAT(DISTINCT NULLIF(h.name_bn, '') ORDER BY c.sort_order ASC, c.id ASC SEPARATOR ', '), ''), NULLIF(ph.name_bn, ''), '') AS hospital_name_bn";
            } else {
                $select[] = "'' AS hospital_name_bn";
            }
        } else {
            $select[] = "'' AS hospital_name";
            $select[] = "'' AS hospital_name_bn";
        }

        return $select;
    }
}

if (!function_exists('doctor_related_query')) {
    function doctor_related_query(
        int $current_doctor_id,
        int $specialty_id,
        string $district_name = '',
        string $thana_name = '',
        int $limit = 3,
        array $exclude_ids = []
    ): array {
        global $pdo;

        if ($current_doctor_id <= 0 || $specialty_id <= 0 || $limit <= 0 || !doctor_related_table_exists('doctors')) {
            return [];
        }

        $district_name = trim($district_name);
        $thana_name = trim($thana_name);

        $district_id = $district_name !== '' ? doctor_related_address_id_by_name('districts', $district_name) : 0;
        $thana_id = $thana_name !== '' ? doctor_related_address_id_by_name('thanas', $thana_name) : 0;

        $where = [
            'd.id != :current_doctor_id',
            'd.specialty_id = :specialty_id',
        ];

        $params = [
            ':current_doctor_id' => $current_doctor_id,
            ':specialty_id' => $specialty_id,
        ];

        if (doctor_related_column_exists('doctors', 'status')) {
            $where[] = "(d.status = 'active' OR d.status = 'published' OR d.status = '' OR d.status IS NULL)";
        }

        $exclude_ids = array_values(array_unique(array_filter(array_map('intval', $exclude_ids))));

        if (!empty($exclude_ids)) {
            $placeholders = [];

            foreach ($exclude_ids as $index => $exclude_id) {
                $key = ':exclude_id_' . $index;
                $placeholders[] = $key;
                $params[$key] = $exclude_id;
            }

            $where[] = 'd.id NOT IN (' . implode(',', $placeholders) . ')';
        }

        $join = '';

        if (doctor_related_table_exists('specialties')) {
            $join .= ' LEFT JOIN specialties s ON s.id = d.specialty_id ';
        }

        if (doctor_related_table_exists('chambers') && doctor_related_table_exists('hospitals')) {
            $join .= ' LEFT JOIN chambers c ON c.doctor_id = d.id ';

            if (doctor_related_column_exists('chambers', 'status')) {
                $join .= " AND (c.status = 'active' OR c.status = 'published' OR c.status = '' OR c.status IS NULL) ";
            }

            $join .= ' LEFT JOIN hospitals h ON h.id = c.hospital_id ';
            $join .= ' LEFT JOIN hospitals ph ON ph.id = d.hospital_id ';

            if ($district_name !== '') {
                $district_parts = [];

                if ($district_id > 0 && doctor_related_column_exists('doctors', 'doctor_district_id')) {
                    $district_parts[] = 'd.doctor_district_id = :doctor_added_district_id';
                    $params[':doctor_added_district_id'] = $district_id;
                }

                foreach (['doctor_district', 'doctor_district_name', 'district', 'district_name', 'city'] as $doctor_district_column) {
                    if (doctor_related_column_exists('doctors', $doctor_district_column)) {
                        $district_parts[] = 'd.' . $doctor_district_column . ' LIKE :doctor_added_district_name';
                        $params[':doctor_added_district_name'] = '%' . $district_name . '%';
                        break;
                    }
                }

                if ($district_id > 0 && doctor_related_column_exists('hospitals', 'district_id')) {
                    $district_parts[] = 'h.district_id = :district_id';
                    $params[':district_id'] = $district_id;
                }

                if (doctor_related_column_exists('chambers', 'address')) {
                    $district_parts[] = 'c.address LIKE :district_address';
                    $params[':district_address'] = '%' . $district_name . '%';
                }

                if (doctor_related_column_exists('hospitals', 'address')) {
                    $district_parts[] = 'h.address LIKE :district_hospital_address';
                    $params[':district_hospital_address'] = '%' . $district_name . '%';
                }

                if (doctor_related_column_exists('hospitals', 'city')) {
                    $district_parts[] = 'h.city LIKE :district_hospital_city';
                    $params[':district_hospital_city'] = '%' . $district_name . '%';
                }

                if (!empty($district_parts)) {
                    $where[] = '(' . implode(' OR ', $district_parts) . ')';
                }
            }

            if ($thana_name !== '') {
                $thana_parts = [];

                if ($thana_id > 0 && doctor_related_column_exists('doctors', 'doctor_thana_id')) {
                    $thana_parts[] = 'd.doctor_thana_id = :doctor_added_thana_id';
                    $params[':doctor_added_thana_id'] = $thana_id;
                }

                foreach (['doctor_thana', 'doctor_thana_name', 'thana', 'thana_name', 'area'] as $doctor_thana_column) {
                    if (doctor_related_column_exists('doctors', $doctor_thana_column)) {
                        $thana_parts[] = 'd.' . $doctor_thana_column . ' LIKE :doctor_added_thana_name';
                        $params[':doctor_added_thana_name'] = '%' . $thana_name . '%';
                        break;
                    }
                }

                if ($thana_id > 0 && doctor_related_column_exists('hospitals', 'thana_id')) {
                    $thana_parts[] = 'h.thana_id = :thana_id';
                    $params[':thana_id'] = $thana_id;
                }

                if (doctor_related_column_exists('chambers', 'address')) {
                    $thana_parts[] = 'c.address LIKE :thana_address';
                    $params[':thana_address'] = '%' . $thana_name . '%';
                }

                if (doctor_related_column_exists('hospitals', 'address')) {
                    $thana_parts[] = 'h.address LIKE :thana_hospital_address';
                    $params[':thana_hospital_address'] = '%' . $thana_name . '%';
                }

                if (doctor_related_column_exists('hospitals', 'city')) {
                    $thana_parts[] = 'h.city LIKE :thana_hospital_city';
                    $params[':thana_hospital_city'] = '%' . $thana_name . '%';
                }

                if (!empty($thana_parts)) {
                    $where[] = '(' . implode(' OR ', $thana_parts) . ')';
                }
            }
        } elseif (doctor_related_table_exists('hospitals')) {
            $join .= ' LEFT JOIN hospitals h ON h.id = d.hospital_id ';
            $join .= ' LEFT JOIN hospitals ph ON ph.id = d.hospital_id ';
        }

        $order = [];

        if (doctor_related_column_exists('doctors', 'is_featured')) {
            $order[] = 'd.is_featured DESC';
        }

        if (doctor_related_column_exists('doctors', 'is_verified')) {
            $order[] = 'd.is_verified DESC';
        }

        if (doctor_related_column_exists('doctors', 'rating')) {
            $order[] = 'd.rating DESC';
        }

        $order[] = 'd.id DESC';

        $limit = max(1, (int)$limit);

        try {
            $stmt = $pdo->prepare('
                SELECT ' . implode(', ', doctor_related_select_parts()) . '
                FROM doctors d
                ' . $join . '
                WHERE ' . implode(' AND ', $where) . '
                GROUP BY d.id
                ORDER BY ' . implode(', ', $order) . '
                LIMIT ' . $limit . '
            ');
            $stmt->execute($params);

            return $stmt->fetchAll();
        } catch (Throwable $e) {
            error_log('Related doctors query failed: ' . $e->getMessage());
            return [];
        }
    }
}

if (!function_exists('doctor_related_get_items')) {
    function doctor_related_get_items(array $doctor, int $limit = 3): array
    {
        $doctor_id = (int)($doctor['id'] ?? 0);
        $specialty_id = doctor_related_specialty_id($doctor);

        if ($doctor_id <= 0 || $specialty_id <= 0) {
            return [];
        }

        $district_name = doctor_related_doctor_added_location_name($doctor, 'district');
        $thana_name = doctor_related_doctor_added_location_name($doctor, 'thana');

        $results = [];

        if (trim($thana_name) !== '') {
            $results = doctor_related_query(
                $doctor_id,
                $specialty_id,
                $district_name,
                $thana_name,
                $limit
            );
        }

        if (count($results) < $limit && trim($district_name) !== '') {
            $more = doctor_related_query(
                $doctor_id,
                $specialty_id,
                $district_name,
                '',
                $limit - count($results),
                array_column($results, 'id')
            );

            $results = array_merge($results, $more);
        }

        if (count($results) < $limit) {
            $more = doctor_related_query(
                $doctor_id,
                $specialty_id,
                '',
                '',
                $limit - count($results),
                array_column($results, 'id')
            );

            $results = array_merge($results, $more);
        }

        return array_slice($results, 0, $limit);
    }
}

/*
|--------------------------------------------------------------------------
| Related Doctors Data
|--------------------------------------------------------------------------
*/

$related_doctors = doctor_related_get_items($doctor, 3);

if (!$related_doctors) {
    return;
}

$doctor_card_file = __DIR__ . '/../includes/doctor-card.php';

if (!file_exists($doctor_card_file)) {
    return;
}

$original_doctor = $doctor;

?>

<link rel="stylesheet" href="<?= e(site_url('assets/css/doctor-related-doctors.css')) ?>">

<section class="medic-card">
    <h2><?= e(__t('related_doctors', 'Related Doctors')) ?></h2>
    <p><?= e(__t('related_doctors_text', 'Other verified doctors from the same district and specialty.')) ?></p>

    <div class="medic-related-doctors">
        <?php foreach ($related_doctors as $related_doctor): ?>
            <?php
            front_line_break();
            $doctor = $related_doctor;
            include $doctor_card_file;
            ?>
        <?php endforeach; ?>

        <?php $doctor = $original_doctor; ?>
    </div>
</section>