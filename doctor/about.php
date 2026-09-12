<?php
/*
|--------------------------------------------------------------------------
| Doctor About Section
|--------------------------------------------------------------------------
| Bilingual fallback and location logic:
| - Bangla page: Bangla value -> English value.
| - English page: English value -> Bangla value.
| - Chamber values always have priority over hospital values.
| - Division is never selected or displayed.
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

            if (is_file($default_file)) {
                $default_translations = require $default_file;

                if (is_array($default_translations)) {
                    $translations = $default_translations;
                }
            }

            if ($lang !== 'en' && is_file($lang_file)) {
                $current_translations = require $lang_file;

                if (is_array($current_translations)) {
                    $translations = array_merge($translations, $current_translations);
                }
            }
        }

        $value = trim((string)($translations[$key] ?? ''));

        return $value !== '' ? $value : ($fallback !== '' ? $fallback : $key);
    }
}

/*
|--------------------------------------------------------------------------
| About Helpers
|--------------------------------------------------------------------------
*/

if (!function_exists('doctor_about_bilingual_value')) {
    function doctor_about_bilingual_value($english = '', $bangla = ''): string
    {
        $english = trim((string)$english);
        $bangla = trim((string)$bangla);
        $is_bn = defined('CURRENT_LANG') && CURRENT_LANG === 'bn';

        if ($is_bn) {
            return $bangla !== '' ? $bangla : $english;
        }

        return $english !== '' ? $english : $bangla;
    }
}

if (!function_exists('doctor_about_field')) {
    function doctor_about_field(array $doctor, string $key): string
    {
        return doctor_about_bilingual_value(
            $doctor[$key] ?? '',
            $doctor[$key . '_bn'] ?? ''
        );
    }
}

if (!function_exists('doctor_about_value_from_keys')) {
    function doctor_about_value_from_keys(array $data, array $english_keys, array $bangla_keys): string
    {
        $english = '';
        $bangla = '';

        foreach ($english_keys as $key) {
            if (isset($data[$key]) && trim((string)$data[$key]) !== '') {
                $english = trim((string)$data[$key]);
                break;
            }
        }

        foreach ($bangla_keys as $key) {
            if (isset($data[$key]) && trim((string)$data[$key]) !== '') {
                $bangla = trim((string)$data[$key]);
                break;
            }
        }

        return doctor_about_bilingual_value($english, $bangla);
    }
}

if (!function_exists('doctor_about_split_items')) {
    function doctor_about_split_items(string $text): array
    {
        $text = trim($text);

        if ($text === '') {
            return [];
        }

        $items = preg_split('/[\n,|]+/u', $text);
        $clean = [];

        foreach ($items as $item) {
            $item = trim(strip_tags((string)$item));

            if ($item !== '') {
                $clean[] = $item;
            }
        }

        return array_values(array_unique($clean));
    }
}

if (!function_exists('doctor_about_table_exists')) {
    function doctor_about_table_exists(string $table): bool
    {
        global $pdo;

        static $cache = [];
        $table = trim($table);

        if ($table === '') {
            return false;
        }

        if (array_key_exists($table, $cache)) {
            return $cache[$table];
        }

        try {
            $stmt = $pdo->prepare("\n                SELECT COUNT(*)\n                FROM INFORMATION_SCHEMA.TABLES\n                WHERE TABLE_SCHEMA = DATABASE()\n                  AND TABLE_NAME = :table\n            ");
            $stmt->execute([':table' => $table]);

            $cache[$table] = (int)$stmt->fetchColumn() > 0;
        } catch (Throwable $e) {
            $cache[$table] = false;
        }

        return $cache[$table];
    }
}

if (!function_exists('doctor_about_column_exists')) {
    function doctor_about_column_exists(string $table, string $column): bool
    {
        global $pdo;

        static $cache = [];
        $table = trim($table);
        $column = trim($column);
        $cache_key = $table . '.' . $column;

        if ($table === '' || $column === '') {
            return false;
        }

        if (array_key_exists($cache_key, $cache)) {
            return $cache[$cache_key];
        }

        try {
            $stmt = $pdo->prepare("\n                SELECT COUNT(*)\n                FROM INFORMATION_SCHEMA.COLUMNS\n                WHERE TABLE_SCHEMA = DATABASE()\n                  AND TABLE_NAME = :table\n                  AND COLUMN_NAME = :column\n            ");
            $stmt->execute([
                ':table' => $table,
                ':column' => $column,
            ]);

            $cache[$cache_key] = (int)$stmt->fetchColumn() > 0;
        } catch (Throwable $e) {
            $cache[$cache_key] = false;
        }

        return $cache[$cache_key];
    }
}

/*
|--------------------------------------------------------------------------
| Specialty Lookup
|--------------------------------------------------------------------------
| Uses doctors.specialty_id (or the legacy speciality_id) to load the
| specialty name directly from the specialties table. This keeps the About
| section and the automatic bio aligned with hero.php.
*/

if (!function_exists('doctor_about_get_specialty_by_id')) {
    function doctor_about_get_specialty_by_id(int $specialty_id): array
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
            !doctor_about_table_exists('specialties')
        ) {
            return $specialty;
        }

        try {
            $select = [];

            if (doctor_about_column_exists('specialties', 'name')) {
                $select[] = 'name';
            } elseif (doctor_about_column_exists('specialties', 'name_en')) {
                $select[] = 'name_en AS name';
            } else {
                $select[] = "'' AS name";
            }

            if (doctor_about_column_exists('specialties', 'name_bn')) {
                $select[] = 'name_bn';
            } else {
                $select[] = "'' AS name_bn";
            }

            $stmt = $pdo->prepare(
                'SELECT ' . implode(', ', $select) .
                ' FROM specialties WHERE id = :specialty_id LIMIT 1'
            );
            $stmt->execute([':specialty_id' => $specialty_id]);

            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (is_array($row)) {
                $specialty['name'] = trim((string)($row['name'] ?? ''));
                $specialty['name_bn'] = trim((string)($row['name_bn'] ?? ''));
            }
        } catch (Throwable $e) {
            /* A missing optional specialty value must not break the page. */
        }

        return $specialty;
    }
}

if (!function_exists('doctor_about_hospital_select_fields')) {
    function doctor_about_hospital_select_fields(string $alias = 'h'): array
    {
        $fields = [];
        $columns = [
            'name', 'name_bn', 'address', 'address_bn',
            'house_no', 'house_no_bn', 'road_no', 'road_no_bn',
            'area', 'area_bn', 'post_code', 'district_id', 'thana_id',
            'visiting_hours', 'visiting_hours_bn', 'opening_hours', 'opening_hours_bn',
        ];

        foreach ($columns as $column) {
            $fields[] = doctor_about_column_exists('hospitals', $column)
                ? "{$alias}.{$column} AS hospital_{$column}"
                : "'' AS hospital_{$column}";
        }

        return $fields;
    }
}

if (!function_exists('doctor_about_hospital_location_joins')) {
    function doctor_about_hospital_location_joins(string $hospital_alias = 'h'): array
    {
        $joins = [];
        $fields = [
            "'' AS hospital_district_name_en",
            "'' AS hospital_district_name_bn",
            "'' AS hospital_thana_name_en",
            "'' AS hospital_thana_name_bn",
        ];

        if (
            doctor_about_table_exists('districts') &&
            doctor_about_column_exists('hospitals', 'district_id')
        ) {
            $joins[] = "LEFT JOIN districts d ON d.id = {$hospital_alias}.district_id";
            $fields[0] = doctor_about_column_exists('districts', 'name_en') ? 'd.name_en AS hospital_district_name_en' : "'' AS hospital_district_name_en";
            $fields[1] = doctor_about_column_exists('districts', 'name_bn') ? 'd.name_bn AS hospital_district_name_bn' : "'' AS hospital_district_name_bn";
        }

        if (
            doctor_about_table_exists('thanas') &&
            doctor_about_column_exists('hospitals', 'thana_id')
        ) {
            $joins[] = "LEFT JOIN thanas t ON t.id = {$hospital_alias}.thana_id";
            $fields[2] = doctor_about_column_exists('thanas', 'name_en') ? 't.name_en AS hospital_thana_name_en' : "'' AS hospital_thana_name_en";
            $fields[3] = doctor_about_column_exists('thanas', 'name_bn') ? 't.name_bn AS hospital_thana_name_bn' : "'' AS hospital_thana_name_bn";
        }

        return [$joins, $fields];
    }
}

if (!function_exists('doctor_about_get_chambers')) {
    function doctor_about_get_chambers(int $doctor_id): array
    {
        global $pdo;

        if ($doctor_id <= 0 || !doctor_about_table_exists('chambers')) {
            return [];
        }

        $select = ['c.*'];
        $joins = [];

        if (doctor_about_table_exists('hospitals') && doctor_about_column_exists('chambers', 'hospital_id')) {
            $select = array_merge($select, doctor_about_hospital_select_fields('h'));
            [$location_joins, $location_fields] = doctor_about_hospital_location_joins('h');
            $select = array_merge($select, $location_fields);
            $joins = array_merge($joins, ['LEFT JOIN hospitals h ON h.id = c.hospital_id'], $location_joins);
        } else {
            foreach ([
                'name', 'name_bn', 'address', 'address_bn', 'house_no', 'house_no_bn',
                'road_no', 'road_no_bn', 'area', 'area_bn', 'post_code', 'district_id', 'thana_id',
            ] as $column) {
                $select[] = "'' AS hospital_{$column}";
            }

            $select[] = "'' AS hospital_district_name_en";
            $select[] = "'' AS hospital_district_name_bn";
            $select[] = "'' AS hospital_thana_name_en";
            $select[] = "'' AS hospital_thana_name_bn";
        }

        $where = ['c.doctor_id = :doctor_id'];

        if (doctor_about_column_exists('chambers', 'status')) {
            $where[] = "(c.status = 'active' OR c.status = 'published' OR c.status IS NULL OR c.status = '')";
        }

        $order_by = doctor_about_column_exists('chambers', 'sort_order')
            ? 'c.sort_order ASC, c.id ASC'
            : 'c.id ASC';

        try {
            $stmt = $pdo->prepare("\n                SELECT " . implode(', ', $select) . "\n                FROM chambers c\n                " . implode("\n", $joins) . "\n                WHERE " . implode(' AND ', $where) . "\n                ORDER BY {$order_by}\n            ");
            $stmt->execute([':doctor_id' => $doctor_id]);

            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return is_array($rows) ? $rows : [];
        } catch (Throwable $e) {
            return [];
        }
    }
}

if (!function_exists('doctor_about_get_hospital_by_id')) {
    function doctor_about_get_hospital_by_id(int $hospital_id): array
    {
        global $pdo;

        if ($hospital_id <= 0 || !doctor_about_table_exists('hospitals')) {
            return [];
        }

        $select = doctor_about_hospital_select_fields('h');
        [$joins, $location_fields] = doctor_about_hospital_location_joins('h');
        $select = array_merge($select, $location_fields);

        try {
            $stmt = $pdo->prepare("\n                SELECT " . implode(', ', $select) . "\n                FROM hospitals h\n                " . implode("\n", $joins) . "\n                WHERE h.id = :hospital_id\n                LIMIT 1\n            ");
            $stmt->execute([':hospital_id' => $hospital_id]);

            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return is_array($row) ? $row : [];
        } catch (Throwable $e) {
            return [];
        }
    }
}

if (!function_exists('doctor_about_structured_hospital_address')) {
    function doctor_about_structured_hospital_address(array $hospital): string
    {
        $house = doctor_about_bilingual_value($hospital['hospital_house_no'] ?? '', $hospital['hospital_house_no_bn'] ?? '');
        $road = doctor_about_bilingual_value($hospital['hospital_road_no'] ?? '', $hospital['hospital_road_no_bn'] ?? '');
        $area = doctor_about_bilingual_value($hospital['hospital_area'] ?? '', $hospital['hospital_area_bn'] ?? '');
        $thana = doctor_about_bilingual_value($hospital['hospital_thana_name_en'] ?? '', $hospital['hospital_thana_name_bn'] ?? '');
        $district = doctor_about_bilingual_value($hospital['hospital_district_name_en'] ?? '', $hospital['hospital_district_name_bn'] ?? '');
        $post_code = trim((string)($hospital['hospital_post_code'] ?? ''));

        $parts = array_values(array_filter([$house, $road, $area], static fn ($value): bool => trim((string)$value) !== ''));

        if ($thana !== '' && $post_code !== '') {
            $parts[] = $thana . '-' . $post_code;
        } elseif ($thana !== '') {
            $parts[] = $thana;
        } elseif ($post_code !== '') {
            $parts[] = $post_code;
        }

        if ($district !== '') {
            $parts[] = $district;
        }

        return implode(', ', $parts);
    }
}

if (!function_exists('doctor_about_location_value')) {
    function doctor_about_location_value(array $chamber, array $doctor): string
    {
        /*
         * Exact priority:
         * English page: Chamber English -> Chamber Bangla -> Hospital English -> Hospital Bangla.
         * Bangla page: Chamber Bangla -> Chamber English -> Hospital Bangla -> Hospital English.
         */
        $chamber_address = doctor_about_bilingual_value(
            $chamber['address'] ?? '',
            $chamber['address_bn'] ?? ''
        );

        if ($chamber_address !== '') {
            return $chamber_address;
        }

        $hospital_address = doctor_about_bilingual_value(
            $chamber['hospital_address'] ?? '',
            $chamber['hospital_address_bn'] ?? ''
        );

        if ($hospital_address !== '') {
            return $hospital_address;
        }

        $structured_address = doctor_about_structured_hospital_address($chamber);

        if ($structured_address !== '') {
            return $structured_address;
        }

        return doctor_about_bilingual_value(
            $doctor['address'] ?? ($doctor['city'] ?? ''),
            $doctor['address_bn'] ?? ''
        );
    }
}

/*
|--------------------------------------------------------------------------
| Automatic Doctor Article
|--------------------------------------------------------------------------
| Manual bio always has the highest priority. When both bio and bio_bn are
| empty, this file creates a factual article using saved profile data only.
|
| Two display options are supported:
| - short: a compact summary using the available profile data.
| - detailed: separate paragraphs for basic information, professional
|   background and chamber information. This is the default.
|
| The article is display-only. Nothing is written back to the database.
|--------------------------------------------------------------------------
*/

if (!function_exists('doctor_about_local_number')) {
    function doctor_about_local_number($value): string
    {
        $value = (string)$value;

        if (defined('CURRENT_LANG') && CURRENT_LANG === 'bn') {
            return str_replace(
                ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'],
                ['০', '১', '২', '৩', '৪', '৫', '৬', '৭', '৮', '৯'],
                $value
            );
        }

        return $value;
    }
}

if (!function_exists('doctor_about_experience_years')) {
    function doctor_about_experience_years($value): int
    {
        $value = (int)$value;
        $current_year = (int)date('Y');

        if ($value >= 1900 && $value <= $current_year) {
            return max(0, $current_year - $value);
        }

        return ($value > 0 && $value <= 100) ? $value : 0;
    }
}

if (!function_exists('doctor_about_clean_article_value')) {
    function doctor_about_clean_article_value($value): string
    {
        $value = trim(strip_tags((string)$value));
        $value = preg_replace('/\s+/u', ' ', $value);

        return trim((string)$value);
    }
}

if (!function_exists('doctor_about_chamber_schedule_value')) {
    function doctor_about_chamber_schedule_value(array $chamber): string
    {
        /*
         * Source priority remains chamber first, then hospital. Within each
         * source, the current language is preferred and the other language is
         * used when the matching value is empty.
         */
        $chamber_schedule = doctor_about_bilingual_value(
            doctor_about_value_from_keys($chamber, ['schedule', 'visiting_hours'], []),
            doctor_about_value_from_keys($chamber, [], ['schedule_bn', 'visiting_hours_bn'])
        );

        if ($chamber_schedule !== '') {
            return $chamber_schedule;
        }

        return doctor_about_bilingual_value(
            doctor_about_value_from_keys($chamber, ['hospital_visiting_hours', 'hospital_opening_hours'], []),
            doctor_about_value_from_keys($chamber, [], ['hospital_visiting_hours_bn', 'hospital_opening_hours_bn'])
        );
    }
}

if (!function_exists('doctor_about_chamber_article_entries')) {
    /**
     * Builds concise chamber entries for the automatic article.
     * Address is intentionally excluded here: the article lists only the
     * chamber / hospital name and visiting hours when they are available.
     */
    function doctor_about_chamber_article_entries(array $chambers, int $limit = 3): array
    {
        $entries = [];
        $seen = [];

        foreach ($chambers as $chamber) {
            if (!is_array($chamber)) {
                continue;
            }

            $name = doctor_about_bilingual_value(
                doctor_about_value_from_keys($chamber, ['hospital_name', 'chamber_name', 'name'], []),
                doctor_about_value_from_keys($chamber, [], ['hospital_name_bn', 'chamber_name_bn', 'name_bn'])
            );

            $schedule = doctor_about_chamber_schedule_value($chamber);

            if ($name === '' && $schedule === '') {
                continue;
            }

            $key_source = $name . '|' . $schedule;
            $key = function_exists('mb_strtolower')
                ? mb_strtolower($key_source, 'UTF-8')
                : strtolower($key_source);

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $entries[] = [
                'name' => doctor_about_clean_article_value($name),
                'schedule' => doctor_about_clean_article_value($schedule),
            ];

            if (count($entries) >= max(1, $limit)) {
                break;
            }
        }

        return $entries;
    }
}

if (!function_exists('doctor_about_chamber_article_text')) {
    /**
     * Returns chamber names with visiting hours only. No chamber or hospital
     * address is included in the generated article.
     */
    function doctor_about_chamber_article_text(array $entries): string
    {
        $is_bn = defined('CURRENT_LANG') && CURRENT_LANG === 'bn';
        $items = [];

        foreach ($entries as $entry) {
            $name = doctor_about_clean_article_value($entry['name'] ?? '');
            $schedule = doctor_about_clean_article_value($entry['schedule'] ?? '');

            if ($name === '') {
                continue;
            }

            if ($schedule !== '') {
                $items[] = $is_bn
                    ? $name . ' (ভিজিটিং সময়: ' . $schedule . ')'
                    : $name . ' (visiting hours: ' . $schedule . ')';
            } else {
                $items[] = $name;
            }
        }

        if (count($items) <= 1) {
            return $items[0] ?? '';
        }

        $last = array_pop($items);

        return implode(', ', $items) . ($is_bn ? ' এবং ' : ' and ') . $last;
    }
}


/*
|--------------------------------------------------------------------------
| Service Area Helpers
|--------------------------------------------------------------------------
| Builds one natural accessibility sentence from saved chamber and hospital
| location data. Only stored values are used; no location is guessed from a
| free-text address.
|--------------------------------------------------------------------------
*/

if (!function_exists('doctor_about_clean_service_area_name')) {
    function doctor_about_clean_service_area_name(string $area): string
    {
        $area = trim(strip_tags($area));
        $area = preg_replace('/\s+/u', ' ', $area);

        if ($area === '') {
            return '';
        }

        if (defined('CURRENT_LANG') && CURRENT_LANG === 'bn') {
            $area = preg_replace('/\s+(এলাকা|থানা|উপজেলা)$/u', '', $area);
        } else {
            $area = preg_replace('/\s+(area|thana|upazila)$/iu', '', $area);
        }

        return trim((string)$area);
    }
}

if (!function_exists('doctor_about_service_area_join')) {
    function doctor_about_service_area_join(array $areas, bool $is_bn): string
    {
        $areas = array_values(array_filter(
            array_map(static fn ($area): string => trim((string)$area), $areas),
            static fn ($area): bool => $area !== ''
        ));

        if (count($areas) === 0) {
            return '';
        }

        if (count($areas) === 1) {
            return $areas[0];
        }

        if (count($areas) === 2) {
            return $areas[0] . ($is_bn ? ' ও ' : ' and ') . $areas[1];
        }

        $last = array_pop($areas);

        return implode(', ', $areas) . ($is_bn ? ' ও ' : ', and ') . $last;
    }
}

if (!function_exists('doctor_about_is_dhaka_district')) {
    function doctor_about_is_dhaka_district(string $district): bool
    {
        $district = trim($district);

        if ($district === '') {
            return false;
        }

        $normalized = function_exists('mb_strtolower')
            ? mb_strtolower($district, 'UTF-8')
            : strtolower($district);

        return in_array($normalized, ['ঢাকা', 'dhaka', 'dhaka district'], true);
    }
}

if (!function_exists('doctor_about_service_area_data')) {
    function doctor_about_service_area_data(array $chambers, int $limit = 3): array
    {
        $areas = [];
        $seen_areas = [];
        $districts = [];

        foreach ($chambers as $chamber) {
            if (!is_array($chamber)) {
                continue;
            }

            /* Chamber location has priority over hospital location. */
            $area = doctor_about_value_from_keys(
                $chamber,
                ['area', 'location', 'thana_name'],
                ['area_bn', 'location_bn', 'thana_name_bn']
            );

            if ($area === '') {
                $area = doctor_about_value_from_keys(
                    $chamber,
                    ['hospital_area', 'hospital_thana_name_en'],
                    ['hospital_area_bn', 'hospital_thana_name_bn']
                );
            }

            $area = doctor_about_clean_service_area_name($area);

            if ($area !== '') {
                $area_key = function_exists('mb_strtolower')
                    ? mb_strtolower($area, 'UTF-8')
                    : strtolower($area);

                if (!isset($seen_areas[$area_key])) {
                    $seen_areas[$area_key] = true;
                    $areas[] = $area;
                }
            }

            $district = doctor_about_value_from_keys(
                $chamber,
                ['hospital_district_name_en', 'district_name', 'district'],
                ['hospital_district_name_bn', 'district_name_bn', 'district_bn']
            );

            $district = trim(strip_tags($district));

            if ($district !== '') {
                $district_key = function_exists('mb_strtolower')
                    ? mb_strtolower($district, 'UTF-8')
                    : strtolower($district);

                $districts[$district_key] = $district;
            }

            if (count($areas) >= max(1, $limit)) {
                break;
            }
        }

        return [
            'areas' => $areas,
            'district' => count($districts) === 1 ? (string) reset($districts) : '',
        ];
    }
}

if (!function_exists('doctor_about_service_area_sentence')) {
    function doctor_about_service_area_sentence(array $chambers): string
    {
        $data = doctor_about_service_area_data($chambers);
        $areas = $data['areas'] ?? [];
        $district = trim((string)($data['district'] ?? ''));
        $is_bn = defined('CURRENT_LANG') && CURRENT_LANG === 'bn';
        $area_text = doctor_about_service_area_join($areas, $is_bn);

        if ($area_text === '') {
            return '';
        }

        if ($is_bn) {
            if (doctor_about_is_dhaka_district($district)) {
                return 'ঢাকার ' . $area_text . ' এলাকায় বসবাসকারী রোগীদের জন্য তাঁর পরামর্শসেবা সহজলভ্য।';
            }

            if ($district !== '') {
                return $district . ' জেলার ' . $area_text . ' এলাকায় বসবাসকারী রোগীদের জন্য তাঁর পরামর্শসেবা সহজলভ্য।';
            }

            return $area_text . ' এলাকায় বসবাসকারী রোগীদের জন্য তাঁর পরামর্শসেবা সহজলভ্য।';
        }

        if ($district !== '') {
            return 'Consultation services are conveniently available for patients living in ' . $area_text . ', ' . $district . '.';
        }

        return 'Consultation services are conveniently available for patients living in the ' . $area_text . ' area.';
    }
}

if (!function_exists('doctor_about_auto_article')) {
    function doctor_about_auto_article(
        array $doctor,
        array $profile,
        array $chambers,
        string $template = 'detailed'
    ): string {
        $template = strtolower(trim($template));
        $template = $template === 'short' ? 'short' : 'detailed';
        $is_bn = defined('CURRENT_LANG') && CURRENT_LANG === 'bn';

        $name = doctor_about_bilingual_value($doctor['name'] ?? '', $doctor['name_bn'] ?? '');
        $specialty = doctor_about_clean_article_value($profile['specialty'] ?? '');
        $degree = doctor_about_clean_article_value($profile['degree'] ?? '');
        $designation = doctor_about_clean_article_value($profile['designation'] ?? '');
        $education = doctor_about_clean_article_value($profile['education'] ?? '');
        $training = doctor_about_clean_article_value($profile['training'] ?? '');
        $fellowship = doctor_about_clean_article_value($profile['fellowship'] ?? '');
        $expertise = doctor_about_clean_article_value($profile['expertise'] ?? '');
        $hospital = doctor_about_clean_article_value($profile['hospital'] ?? '');
        $bmdc_number = doctor_about_clean_article_value($profile['bmdc_number'] ?? '');
        $experience_years = doctor_about_experience_years($doctor['experience_years'] ?? 0);
        $chamber_entries = doctor_about_chamber_article_entries($chambers);
        $chamber_text = doctor_about_chamber_article_text($chamber_entries);
        $service_area_sentence = doctor_about_service_area_sentence($chambers);

        if ($name === '') {
            $name = $is_bn ? 'এই চিকিৎসক' : 'This doctor';
        }

        $paragraphs = [];

        /* Basic professional introduction. */
        if ($is_bn) {
            $basic = $specialty !== ''
                ? $name . ' ' . $specialty . ' বিষয়ে চিকিৎসাসেবা দিয়ে থাকেন।'
                : $name . ' একজন চিকিৎসক।';

            if ($degree !== '') {
                $basic .= ' তাঁর শিক্ষাগত যোগ্যতার মধ্যে রয়েছে ' . $degree . '।';
            }

            if ($designation !== '') {
                $basic .= ' তিনি বর্তমানে ' . $designation . ' হিসেবে দায়িত্ব পালন করছেন।';
            }

            if ($experience_years > 0) {
                $basic .= ' পেশাগতভাবে তাঁর ' . doctor_about_local_number($experience_years . '+') . ' বছরের অভিজ্ঞতা রয়েছে।';
            }

            if ($bmdc_number !== '') {
                $basic .= ' বিএমডিসি নিবন্ধন নম্বর: ' . doctor_about_local_number($bmdc_number) . '।';
            }
        } else {
            $basic = $specialty !== ''
                ? $name . ' provides medical care in ' . $specialty . '.'
                : $name . ' is a medical doctor.';

            if ($degree !== '') {
                $basic .= ' Qualifications include ' . $degree . '.';
            }

            if ($designation !== '') {
                $basic .= ' The doctor currently serves as ' . $designation . '.';
            }

            if ($experience_years > 0) {
                $basic .= ' The doctor has ' . doctor_about_local_number($experience_years . '+') . ' years of professional experience.';
            }

            if ($bmdc_number !== '') {
                $basic .= ' BMDC registration number: ' . doctor_about_local_number($bmdc_number) . '.';
            }
        }

        $paragraphs[] = $basic;

        /* Education, training, fellowship and clinical focus. */
        $background_parts = [];

        if ($is_bn) {
            if ($education !== '') {
                $background_parts[] = 'শিক্ষা ও যোগ্যতা: ' . $education;
            }

            if ($training !== '') {
                $background_parts[] = 'বিশেষ প্রশিক্ষণ: ' . $training;
            }

            if ($fellowship !== '') {
                $background_parts[] = 'ফেলোশিপ: ' . $fellowship;
            }

            if ($expertise !== '') {
                $background_parts[] = 'চিকিৎসা-সংশ্লিষ্ট দক্ষতা ও আগ্রহ: ' . $expertise;
            }
        } else {
            if ($education !== '') {
                $background_parts[] = 'Education and qualifications: ' . $education;
            }

            if ($training !== '') {
                $background_parts[] = 'Specialized training: ' . $training;
            }

            if ($fellowship !== '') {
                $background_parts[] = 'Fellowship: ' . $fellowship;
            }

            if ($expertise !== '') {
                $background_parts[] = 'Clinical focus: ' . $expertise;
            }
        }

        if (!empty($background_parts)) {
            $paragraphs[] = implode('. ', $background_parts) . '.';
        }

        /* Chamber details: names and visiting hours only, never addresses. */
        if ($is_bn) {
            $consultation = '';

            if ($hospital !== '') {
                $consultation = $hospital . '-এ পরামর্শসেবা পাওয়া যায়।';
            }

            if ($chamber_text !== '') {
                $consultation .= ($consultation !== '' ? ' ' : '')
                    . 'চেম্বার ও ভিজিটিং সময়: ' . $chamber_text . '।';
            }

            if ($service_area_sentence !== '') {
                $consultation .= ($consultation !== '' ? ' ' : '') . $service_area_sentence;
            }

            if ($consultation !== '') {
                $paragraphs[] = $consultation;
            }
        } else {
            $consultation = '';

            if ($hospital !== '') {
                $consultation = 'Consultation information is available at ' . $hospital . '.';
            }

            if ($chamber_text !== '') {
                $consultation .= ($consultation !== '' ? ' ' : '')
                    . 'Chamber and visiting hours: ' . $chamber_text . '.';
            }

            if ($service_area_sentence !== '') {
                $consultation .= ($consultation !== '' ? ' ' : '') . $service_area_sentence;
            }

            if ($consultation !== '') {
                $paragraphs[] = $consultation;
            }
        }

        if ($template === 'short' && count($paragraphs) > 1) {
            /* Compact option still includes chamber and visiting-hour details. */
            return trim($paragraphs[0] . "\n\n" . implode(' ', array_slice($paragraphs, 1)));
        }

        return trim(implode("\n\n", $paragraphs));
    }
}

/*
|--------------------------------------------------------------------------
| About Data
|--------------------------------------------------------------------------
*/

/*
 * Primary source: specialties.id -> specialties.name / specialties.name_bn.
 * Direct doctor fields remain only as a backward-compatible fallback.
 */
$doctor_specialty_row = doctor_about_get_specialty_by_id(
    (int)($doctor['specialty_id'] ?? ($doctor['speciality_id'] ?? 0))
);

$doctor_specialty_name = doctor_about_bilingual_value(
    $doctor_specialty_row['name'] !== ''
        ? $doctor_specialty_row['name']
        : ($doctor['specialty_name'] ?? ($doctor['speciality_name'] ?? '')),
    $doctor_specialty_row['name_bn'] !== ''
        ? $doctor_specialty_row['name_bn']
        : ($doctor['specialty_name_bn'] ?? ($doctor['speciality_name_bn'] ?? ''))
);

$doctor_degree = doctor_about_bilingual_value(
    $doctor['degree'] ?? '',
    $doctor['degree_bn'] ?? ''
);

$chambers = doctor_about_get_chambers((int)($doctor['id'] ?? 0));

if (!$chambers && function_exists('get_doctor_chambers')) {
    $chambers = get_doctor_chambers((int)($doctor['id'] ?? 0));
}

$first_chamber = is_array($chambers[0] ?? null) ? $chambers[0] : [];

/*
|--------------------------------------------------------------------------
| Primary Hospital Name
|--------------------------------------------------------------------------
| Priority:
| 1. Doctor primary_hospital / primary_hospital_bn
| 2. Doctor hospital_id relation
| 3. First chamber hospital
|--------------------------------------------------------------------------
*/

$doctor_primary_hospital_name = doctor_about_bilingual_value(
    $doctor['primary_hospital'] ?? '',
    $doctor['primary_hospital_bn'] ?? ''
);

if ($doctor_primary_hospital_name === '' && !empty($doctor['hospital_id'])) {
    $primary_hospital = doctor_about_get_hospital_by_id((int)$doctor['hospital_id']);

    $doctor_primary_hospital_name = doctor_about_bilingual_value(
        $primary_hospital['hospital_name'] ?? '',
        $primary_hospital['hospital_name_bn'] ?? ''
    );
}

if ($doctor_primary_hospital_name === '') {
    $doctor_primary_hospital_name = doctor_about_bilingual_value(
        $first_chamber['hospital_name'] ?? '',
        $first_chamber['hospital_name_bn'] ?? ''
    );
}

$first_chamber_address = doctor_about_location_value($first_chamber, $doctor);
$language_items = doctor_about_split_items(doctor_about_field($doctor, 'languages'));

/*
 * Manual English/Bangla bio remains untouched. When both fields are empty,
 * the page creates a display-only bio from confirmed profile data. It does
 * not write generated text back to the database.
 */
$doctor_manual_bio = doctor_about_bilingual_value(
    $doctor['bio'] ?? '',
    $doctor['bio_bn'] ?? ''
);

$doctor_auto_bio_template = strtolower(trim((string)(
    $doctor['auto_bio_template']
    ?? $doctor['auto_bio_style']
    ?? 'detailed'
)));

if (!in_array($doctor_auto_bio_template, ['short', 'detailed'], true)) {
    $doctor_auto_bio_template = 'detailed';
}

$doctor_bio = $doctor_manual_bio;
$doctor_bio_is_auto = false;

if ($doctor_bio === '') {
    $doctor_bio = doctor_about_auto_article(
        $doctor,
        [
            'specialty' => $doctor_specialty_name,
            'degree' => $doctor_degree,
            'designation' => doctor_about_field($doctor, 'designation'),
            'education' => doctor_about_field($doctor, 'education'),
            'training' => doctor_about_field($doctor, 'training'),
            'fellowship' => doctor_about_field($doctor, 'fellowship'),
            'expertise' => doctor_about_field($doctor, 'expertise'),
            'hospital' => $doctor_primary_hospital_name,
            'location' => $first_chamber_address,
            'bmdc_number' => doctor_about_field($doctor, 'bmdc_number'),
        ],
        $chambers,
        $doctor_auto_bio_template
    );

    $doctor_bio_is_auto = $doctor_bio !== '';
}

/*
|--------------------------------------------------------------------------
| Dynamic About Heading
|--------------------------------------------------------------------------
| Bangla example: ডা. এম.ডি. শফিউল আকরাম এর জীবনী এবং প্রোফাইল বিবরণ
| English example: Dr. M. D. Shafiul Akram Biography and Profile
|--------------------------------------------------------------------------
*/
$doctor_about_heading_name = doctor_about_bilingual_value(
    $doctor['name'] ?? '',
    $doctor['name_bn'] ?? ''
);

if (defined('CURRENT_LANG') && CURRENT_LANG === 'bn') {
    $doctor_about_heading = $doctor_about_heading_name !== ''
        ? $doctor_about_heading_name . ' এর জীবনী এবং প্রোফাইল বিবরণ'
        : 'ডাক্তারের জীবনী এবং প্রোফাইল বিবরণ';
} else {
    $doctor_about_heading = $doctor_about_heading_name !== ''
        ? $doctor_about_heading_name . ' Biography and Profile'
        : 'Doctor Biography and Profile';
}

?>


<link rel="stylesheet" href="<?= e(site_url('assets/css/doctor-about.css')) ?>">

<?php if ($doctor_bio !== ''): ?>
    <section class="medic-about-card" aria-labelledby="about-doctor-heading">
        <h2 id="about-doctor-heading" class="medic-about-card-title">
            <?= e($doctor_about_heading) ?>
        </h2>

        <div class="medic-about-article<?= $doctor_bio_is_auto ? ' is-auto-generated' : '' ?>">
            <?php foreach (preg_split('/\n{2,}/u', trim($doctor_bio)) ?: [] as $doctor_bio_paragraph): ?>
                <?php $doctor_bio_paragraph = trim((string)$doctor_bio_paragraph); ?>
                <?php if ($doctor_bio_paragraph !== ''): ?>
                    <p><?= nl2br(e($doctor_bio_paragraph)) ?></p>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>
