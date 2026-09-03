<?php
/*
|--------------------------------------------------------------------------
| Doctor Chamber & Appointment Section
|--------------------------------------------------------------------------
| File: chambers-schedule.php
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

        $value = trim((string)($translations[$key] ?? ''));

        return $value !== '' ? $value : ($fallback !== '' ? $fallback : $key);
    }
}

if (!function_exists('lang_text')) {
    /**
     * Returns the current page language first, then falls back to the other
     * language when the matching value is empty.
     *
     * Bangla page: Bangla -> English
     * English page: English -> Bangla
     */
    function lang_text(string $english = '', string $bangla = ''): string
    {
        $english = trim($english);
        $bangla = trim($bangla);
        $is_bn = defined('CURRENT_LANG') && CURRENT_LANG === 'bn';

        if ($is_bn) {
            return $bangla !== '' ? $bangla : $english;
        }

        return $english !== '' ? $english : $bangla;
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
| Helpers
|--------------------------------------------------------------------------
*/

if (!function_exists('doctor_chambers_table_exists')) {
    function doctor_chambers_table_exists(string $table): bool
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

if (!function_exists('doctor_chambers_column_exists')) {
    function doctor_chambers_column_exists(string $table, string $column): bool
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

if (!function_exists('doctor_chambers_value')) {
    function doctor_chambers_value(array $chamber, array $keys, string $default = ''): string
    {
        foreach ($keys as $key) {
            if (isset($chamber[$key]) && trim((string)$chamber[$key]) !== '') {
                return trim((string)$chamber[$key]);
            }
        }

        return $default;
    }
}

if (!function_exists('doctor_chambers_lang_value')) {
    /**
     * Selects one value from a single source in this order:
     * Bangla page: Bangla -> English
     * English page: English -> Bangla
     */
    function doctor_chambers_lang_value(array $data, array $english_keys, array $bangla_keys, string $default = ''): string
    {
        $english = doctor_chambers_value($data, $english_keys, '');
        $bangla = doctor_chambers_value($data, $bangla_keys, '');
        $value = lang_text($english, $bangla);

        return $value !== '' ? $value : $default;
    }
}

if (!function_exists('doctor_chambers_pick_chamber_hospital_value')) {
    /**
     * One shared, strict fallback rule for every bilingual chamber value.
     *
     * English page: Chamber EN -> Chamber BN -> Hospital EN -> Hospital BN
     * Bangla page:  Chamber BN -> Chamber EN -> Hospital BN -> Hospital EN
     *
     * It returns the first non-empty value only. This keeps chamber data as
     * the first priority and hospital data as the fallback in both languages.
     */
    function doctor_chambers_pick_chamber_hospital_value(
        array $data,
        array $chamber_english_keys,
        array $chamber_bangla_keys,
        array $hospital_english_keys = [],
        array $hospital_bangla_keys = [],
        string $default = ''
    ): string {
        $is_bn = defined('CURRENT_LANG') && CURRENT_LANG === 'bn';

        $candidate_sets = $is_bn
            ? [
                $chamber_bangla_keys,
                $chamber_english_keys,
                $hospital_bangla_keys,
                $hospital_english_keys,
            ]
            : [
                $chamber_english_keys,
                $chamber_bangla_keys,
                $hospital_english_keys,
                $hospital_bangla_keys,
            ];

        foreach ($candidate_sets as $keys) {
            $value = doctor_chambers_value($data, $keys, '');

            if ($value !== '') {
                return $value;
            }
        }

        return $default;
    }
}

if (!function_exists('doctor_chambers_chamber_then_hospital_value')) {
    /** Backward-compatible name used by the template below. */
    function doctor_chambers_chamber_then_hospital_value(
        array $chamber,
        array $chamber_english_keys,
        array $chamber_bangla_keys,
        array $hospital_english_keys = [],
        array $hospital_bangla_keys = [],
        string $default = ''
    ): string {
        return doctor_chambers_pick_chamber_hospital_value(
            $chamber,
            $chamber_english_keys,
            $chamber_bangla_keys,
            $hospital_english_keys,
            $hospital_bangla_keys,
            $default
        );
    }
}

/*
|--------------------------------------------------------------------------
| Visiting Schedule Resolver
|--------------------------------------------------------------------------
| The schedule field is strictly Chamber first. Any available language is
| accepted when the preferred language is empty:
|
| English page: Chamber EN -> Chamber BN -> Hospital EN -> Hospital BN
| Bangla page:  Chamber BN -> Chamber EN -> Hospital BN -> Hospital EN
|
| This explicit resolver prevents a valid schedule/schedule_bn value from
| disappearing when only one language has been saved.
*/
if (!function_exists('doctor_chambers_visiting_schedule_value')) {
    function doctor_chambers_visiting_schedule_value(array $chamber): string
    {
        return doctor_chambers_pick_chamber_hospital_value(
            $chamber,
            [
                'schedule',
                'visiting_hours',
                'visiting_hour',
                'appointment_schedule',
            ],
            [
                'schedule_bn',
                'visiting_hours_bn',
                'visiting_hour_bn',
                'appointment_schedule_bn',
            ],
            [
                'hospital_visiting_hours',
                'hospital_opening_hours',
                'hospital_schedule',
            ],
            [
                'hospital_visiting_hours_bn',
                'hospital_opening_hours_bn',
                'hospital_schedule_bn',
            ]
        );
    }
}

/*
|--------------------------------------------------------------------------
| Bilingual Address Resolver
|--------------------------------------------------------------------------
| Current-language values are always preferred. If the current-language
| value is empty, the matching field from the other language is used.
|
| Bangla page: Bangla -> English
| English page: English -> Bangla
|
| Location output is limited to chamber/hospital address, house, road, area,
| thana, post code and district. No division field is joined, assembled or rendered.
*/
if (!function_exists('doctor_chambers_unique_address_parts')) {
    function doctor_chambers_unique_address_parts(array $parts): array
    {
        $unique = [];
        $seen = [];

        foreach ($parts as $part) {
            $part = trim((string)$part);

            if ($part === '') {
                continue;
            }

            $key = function_exists('mb_strtolower')
                ? mb_strtolower($part, 'UTF-8')
                : strtolower($part);

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $unique[] = $part;
        }

        return $unique;
    }
}

if (!function_exists('doctor_chambers_strip_division_from_address')) {
    /**
     * Removes a trailing Bangladesh division label from an old free-text
     * address. This covers records saved before address components existed.
     */
    function doctor_chambers_strip_division_from_address(string $address): string
    {
        $address = trim(preg_replace('/\s+/u', ' ', $address));

        if ($address === '') {
            return '';
        }

        $parts = preg_split('/\s*[,،]\s*/u', $address) ?: [];
        $parts = doctor_chambers_unique_address_parts($parts);

        if (count($parts) < 2) {
            return $address;
        }

        $last = trim((string)end($parts));
        $last_key = function_exists('mb_strtolower')
            ? mb_strtolower($last, 'UTF-8')
            : strtolower($last);

        $division_labels = [
            'dhaka division', 'chattogram division', 'chittagong division',
            'rajshahi division', 'khulna division', 'barishal division',
            'barisal division', 'sylhet division', 'rangpur division',
            'mymensingh division',
            'ঢাকা বিভাগ', 'চট্টগ্রাম বিভাগ', 'রাজশাহী বিভাগ', 'খুলনা বিভাগ',
            'বরিশাল বিভাগ', 'সিলেট বিভাগ', 'রংপুর বিভাগ', 'ময়মনসিংহ বিভাগ',
            'ময়মনসিংহ বিভাগ',
        ];

        $division_keys = [];

        foreach ($division_labels as $label) {
            $division_keys[] = function_exists('mb_strtolower')
                ? mb_strtolower($label, 'UTF-8')
                : strtolower($label);
        }

        $previous_keys = [];

        foreach (array_slice($parts, 0, -1) as $part) {
            $previous_keys[] = function_exists('mb_strtolower')
                ? mb_strtolower(trim((string)$part), 'UTF-8')
                : strtolower(trim((string)$part));
        }

        /* Remove an explicit division suffix or a duplicated final location. */
        if (in_array($last_key, $division_keys, true) || in_array($last_key, $previous_keys, true)) {
            array_pop($parts);
        }

        return implode(', ', doctor_chambers_unique_address_parts($parts));
    }
}

if (!function_exists('doctor_chambers_preferred_address')) {
    function doctor_chambers_preferred_address(array $chamber): string
    {
        /*
         * Uses the same source and language priority as Visiting Hour:
         *
         * English page: Chamber EN -> Chamber BN -> Hospital EN -> Hospital BN
         * Bangla page:  Chamber BN -> Chamber EN -> Hospital BN -> Hospital EN
         *
         * Hospital house / road / area / thana / post code / district are used
         * only when Chamber EN/BN and Hospital EN/BN free-text addresses are empty.
         * Division is never selected, built or shown.
         */
        $free_text_address = doctor_chambers_pick_chamber_hospital_value(
            $chamber,
            ['address'],
            ['address_bn'],
            ['hospital_address'],
            ['hospital_address_bn']
        );

        if ($free_text_address !== '') {
            return doctor_chambers_strip_division_from_address($free_text_address);
        }

        /* Final fallback: House, Road, Area, Thana-Post Code, District. */
        $house = doctor_chambers_lang_value(
            $chamber,
            ['hospital_house_no'],
            ['hospital_house_no_bn']
        );
        $road = doctor_chambers_lang_value(
            $chamber,
            ['hospital_road_no'],
            ['hospital_road_no_bn']
        );
        $area = doctor_chambers_lang_value(
            $chamber,
            ['hospital_area'],
            ['hospital_area_bn']
        );
        $thana = doctor_chambers_lang_value(
            $chamber,
            ['hospital_thana_name'],
            ['hospital_thana_name_bn']
        );
        $district = doctor_chambers_lang_value(
            $chamber,
            ['hospital_district_name'],
            ['hospital_district_name_bn']
        );
        $post_code = trim((string)($chamber['hospital_post_code'] ?? ''));

        $thana_with_post_code = '';

        if ($thana !== '' && $post_code !== '') {
            $thana_with_post_code = $thana . '-' . $post_code;
        } elseif ($thana !== '') {
            $thana_with_post_code = $thana;
        } elseif ($post_code !== '') {
            $thana_with_post_code = $post_code;
        }

        return implode(', ', doctor_chambers_unique_address_parts([
            $house,
            $road,
            $area,
            $thana_with_post_code,
            $district,
        ]));
    }
}

if (!function_exists('doctor_chambers_phone_link')) {
    function doctor_chambers_phone_link(string $phone): string
    {
        $phone = trim($phone);

        if ($phone === '') {
            return '';
        }

        return preg_replace('/[^\d+]/', '', $phone);
    }
}

if (!function_exists('doctor_chambers_format_single_time')) {
    function doctor_chambers_format_single_time($time): string
    {
        $time = trim((string)$time);

        if ($time === '') {
            return '';
        }

        $timestamp = strtotime($time);

        if ($timestamp === false) {
            return $time;
        }

        return date('h:i A', $timestamp);
    }
}

if (!function_exists('doctor_chambers_format_time_range')) {
    function doctor_chambers_format_time_range($from, $to): string
    {
        $from = doctor_chambers_format_single_time($from);
        $to = doctor_chambers_format_single_time($to);

        if ($from !== '' && $to !== '') {
            return $from . ' - ' . $to;
        }

        return $from !== '' ? $from : $to;
    }
}

if (!function_exists('doctor_chambers_day_labels')) {
    function doctor_chambers_day_labels(string $days): string
    {
        $days = trim($days);

        if ($days === '') {
            return '';
        }

        $labels = [
            'sat' => __t('saturday', 'Saturday'),
            'sun' => __t('sunday', 'Sunday'),
            'mon' => __t('monday', 'Monday'),
            'tue' => __t('tuesday', 'Tuesday'),
            'wed' => __t('wednesday', 'Wednesday'),
            'thu' => __t('thursday', 'Thursday'),
            'fri' => __t('friday', 'Friday'),
        ];

        $parts = array_filter(array_map('trim', explode(',', strtolower($days))));
        $output = [];

        foreach ($parts as $part) {
            if (isset($labels[$part])) {
                $output[] = $labels[$part];
            }
        }

        return implode(', ', $output);
    }
}

if (!function_exists('doctor_chambers_fee')) {
    function doctor_chambers_fee($fee): string
    {
        $fee = (float)$fee;

        if ($fee <= 0) {
            return '';
        }

        $formatted_fee = rtrim(rtrim(number_format($fee, 2, '.', ''), '0'), '.');

        return '৳' . doctor_chambers_display_text($formatted_fee);
    }
}

if (!function_exists('doctor_chambers_bn_number')) {
    function doctor_chambers_bn_number(string $number): string
    {
        $en = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
        $bn = ['০', '১', '২', '৩', '৪', '৫', '৬', '৭', '৮', '৯'];

        return str_replace($en, $bn, $number);
    }
}

if (!function_exists('doctor_chambers_display_text')) {
    /**
     * Converts only visitor-facing numbers to Bangla in Bangla mode.
     * Raw values used for URLs, IDs and tel: links must stay unchanged.
     */
    function doctor_chambers_display_text($value): string
    {
        $value = (string)$value;

        if (defined('CURRENT_LANG') && CURRENT_LANG === 'bn') {
            return doctor_chambers_bn_number($value);
        }

        return $value;
    }
}

if (!function_exists('doctor_chambers_card_title')) {
    function doctor_chambers_card_title(int $index): string
    {
        $number = doctor_chambers_display_text((string)($index + 1));

        $template = __t(
            'chamber_appointment_title',
            'Chamber-:number & Appointment'
        );

        return str_replace(':number', $number, $template);
    }
}

if (!function_exists('doctor_chambers_hospital_url')) {
    function doctor_chambers_hospital_url(array $chamber, string $hospital_name): string
    {
        $slug = trim((string)($chamber['hospital_slug'] ?? ''));

        if ($slug !== '') {
            return front_url('hospital/' . $slug);
        }

        $hospital_id = (int)($chamber['hospital_id'] ?? 0);

        if ($hospital_id > 0) {
            return front_url('hospitals') . '?hospital_id=' . $hospital_id;
        }

        if ($hospital_name !== '') {
            return front_url('hospitals') . '?search=' . urlencode($hospital_name);
        }

        return front_url('hospitals');
    }
}

if (!function_exists('doctor_chambers_get_items')) {
    function doctor_chambers_get_items(int $doctor_id): array
    {
        global $pdo;

        if ($doctor_id <= 0 || !doctor_chambers_table_exists('chambers')) {
            return [];
        }

        $select = ['c.*'];
        $join = '';

        if (doctor_chambers_table_exists('hospitals')) {
            $select[] = doctor_chambers_column_exists('hospitals', 'name') ? 'h.name AS hospital_name' : "'' AS hospital_name";
            $select[] = doctor_chambers_column_exists('hospitals', 'name_bn') ? 'h.name_bn AS hospital_name_bn' : "'' AS hospital_name_bn";
            $select[] = doctor_chambers_column_exists('hospitals', 'slug') ? 'h.slug AS hospital_slug' : "'' AS hospital_slug";
            $select[] = doctor_chambers_column_exists('hospitals', 'address') ? 'h.address AS hospital_address' : "'' AS hospital_address";
            $select[] = doctor_chambers_column_exists('hospitals', 'address_bn') ? 'h.address_bn AS hospital_address_bn' : "'' AS hospital_address_bn";
            $select[] = doctor_chambers_column_exists('hospitals', 'house_no') ? 'h.house_no AS hospital_house_no' : "'' AS hospital_house_no";
            $select[] = doctor_chambers_column_exists('hospitals', 'house_no_bn') ? 'h.house_no_bn AS hospital_house_no_bn' : "'' AS hospital_house_no_bn";
            $select[] = doctor_chambers_column_exists('hospitals', 'road_no') ? 'h.road_no AS hospital_road_no' : "'' AS hospital_road_no";
            $select[] = doctor_chambers_column_exists('hospitals', 'road_no_bn') ? 'h.road_no_bn AS hospital_road_no_bn' : "'' AS hospital_road_no_bn";
            $select[] = doctor_chambers_column_exists('hospitals', 'area') ? 'h.area AS hospital_area' : "'' AS hospital_area";
            $select[] = doctor_chambers_column_exists('hospitals', 'area_bn') ? 'h.area_bn AS hospital_area_bn' : "'' AS hospital_area_bn";
            $select[] = doctor_chambers_column_exists('hospitals', 'post_code') ? 'h.post_code AS hospital_post_code' : "'' AS hospital_post_code";
            $select[] = doctor_chambers_column_exists('hospitals', 'phone') ? 'h.phone AS hospital_phone' : "'' AS hospital_phone";
            $select[] = doctor_chambers_column_exists('hospitals', 'visiting_hours') ? 'h.visiting_hours AS hospital_visiting_hours' : "'' AS hospital_visiting_hours";
            $select[] = doctor_chambers_column_exists('hospitals', 'visiting_hours_bn') ? 'h.visiting_hours_bn AS hospital_visiting_hours_bn' : "'' AS hospital_visiting_hours_bn";
            $select[] = doctor_chambers_column_exists('hospitals', 'opening_hours') ? 'h.opening_hours AS hospital_opening_hours' : "'' AS hospital_opening_hours";
            $select[] = doctor_chambers_column_exists('hospitals', 'opening_hours_bn') ? 'h.opening_hours_bn AS hospital_opening_hours_bn' : "'' AS hospital_opening_hours_bn";

            $join = 'LEFT JOIN hospitals h ON h.id = c.hospital_id';

            /* Division is never joined or selected. */
            if (
                doctor_chambers_table_exists('thanas') &&
                doctor_chambers_column_exists('hospitals', 'thana_id') &&
                doctor_chambers_column_exists('thanas', 'id')
            ) {
                $join .= ' LEFT JOIN thanas t ON t.id = h.thana_id ';
                $select[] = doctor_chambers_column_exists('thanas', 'name_en') ? 't.name_en AS hospital_thana_name' : "'' AS hospital_thana_name";
                $select[] = doctor_chambers_column_exists('thanas', 'name_bn') ? 't.name_bn AS hospital_thana_name_bn' : "'' AS hospital_thana_name_bn";
            } else {
                $select[] = "'' AS hospital_thana_name";
                $select[] = "'' AS hospital_thana_name_bn";
            }

            if (
                doctor_chambers_table_exists('districts') &&
                doctor_chambers_column_exists('hospitals', 'district_id') &&
                doctor_chambers_column_exists('districts', 'id')
            ) {
                $join .= ' LEFT JOIN districts d ON d.id = h.district_id ';
                $select[] = doctor_chambers_column_exists('districts', 'name_en') ? 'd.name_en AS hospital_district_name' : "'' AS hospital_district_name";
                $select[] = doctor_chambers_column_exists('districts', 'name_bn') ? 'd.name_bn AS hospital_district_name_bn' : "'' AS hospital_district_name_bn";
            } else {
                $select[] = "'' AS hospital_district_name";
                $select[] = "'' AS hospital_district_name_bn";
            }
        } else {
            $select[] = "'' AS hospital_name";
            $select[] = "'' AS hospital_name_bn";
            $select[] = "'' AS hospital_slug";
            $select[] = "'' AS hospital_address";
            $select[] = "'' AS hospital_address_bn";
            $select[] = "'' AS hospital_house_no";
            $select[] = "'' AS hospital_house_no_bn";
            $select[] = "'' AS hospital_road_no";
            $select[] = "'' AS hospital_road_no_bn";
            $select[] = "'' AS hospital_area";
            $select[] = "'' AS hospital_area_bn";
            $select[] = "'' AS hospital_post_code";
            $select[] = "'' AS hospital_phone";
            $select[] = "'' AS hospital_visiting_hours";
            $select[] = "'' AS hospital_visiting_hours_bn";
            $select[] = "'' AS hospital_opening_hours";
            $select[] = "'' AS hospital_opening_hours_bn";
            $select[] = "'' AS hospital_thana_name";
            $select[] = "'' AS hospital_thana_name_bn";
            $select[] = "'' AS hospital_district_name";
            $select[] = "'' AS hospital_district_name_bn";
        }

        $where = ['c.doctor_id = :doctor_id'];

        if (doctor_chambers_column_exists('chambers', 'status')) {
            $where[] = "(c.status = 'active' OR c.status IS NULL OR c.status = '')";
        }

        $order_by = 'c.id ASC';

        if (doctor_chambers_column_exists('chambers', 'sort_order')) {
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

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            return [];
        }
    }
}

/*
|--------------------------------------------------------------------------
| Data
|--------------------------------------------------------------------------
*/

$chambers = doctor_chambers_get_items((int)($doctor['id'] ?? 0));

if (!$chambers && function_exists('get_doctor_chambers')) {
    $chambers = get_doctor_chambers((int)($doctor['id'] ?? 0));
}

if (!$chambers) {
    return;
}

?>

<style>
/*
|--------------------------------------------------------------------------
| Premium Light Chamber Design
|--------------------------------------------------------------------------
| Clean, text-focused card design. All visible text stays at 500 weight.
*/
.medic-chamber-text-section,
.medic-chamber-text-section * {
    font-weight: 500 !important;
}

.medic-chamber-text-section {
    width: 100%;
    margin: 0 0 28px;
    color: #18385f;
}

.medic-chamber-text-heading {
    margin: 0 0 13px;
    color: #1769e9;
    font-size: clamp(21px, 2vw, 25px);
    line-height: 1.3;
    letter-spacing: -0.012em;
}

.medic-chamber-text-list {
    display: grid;
    gap: 14px;
}

.medic-chamber-text-card {
    overflow: hidden;
    border: 1px solid #e0e9f3;
    border-radius: 16px;
    background: #ffffff;
    box-shadow: 0 8px 24px rgba(35, 75, 118, 0.045);
}

.medic-chamber-text-card-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
    padding: 16px 18px;
    border-bottom: 1px solid #e9f0f7;
    background: #fbfdff;
}

.medic-chamber-text-hospital {
    min-width: 0;
    margin: 0;
    color: #123b69;
    font-size: 17px;
    line-height: 1.42;
    letter-spacing: -0.01em;
    overflow-wrap: anywhere;
}

.medic-chamber-text-hospital a {
    color: inherit;
    text-decoration: none;
}

.medic-chamber-text-hospital a:hover {
    color: #1769e9;
    text-decoration: underline;
    text-underline-offset: 3px;
}

.medic-chamber-text-closed {
    flex: 0 0 auto;
    padding: 4px 9px;
    border: 1px solid #ffd8d8;
    border-radius: 999px;
    background: #fff7f7;
    color: #c34b4b;
    font-size: 12px;
    line-height: 1.25;
}

.medic-chamber-text-body {
    padding: 8px 18px 16px;
}

.medic-chamber-text-row {
    display: grid;
    grid-template-columns: minmax(142px, 26%) minmax(0, 1fr);
    gap: 18px;
    align-items: start;
    padding: 12px 0;
    border-bottom: 1px solid #edf2f7;
}

.medic-chamber-text-row:last-child {
    border-bottom: 0;
}

.medic-chamber-text-label {
    color: #7185a0;
    font-size: 14px;
    line-height: 1.5;
}

.medic-chamber-text-value {
    color: #1b3e67;
    font-size: 14px;
    line-height: 1.55;
    overflow-wrap: anywhere;
}

.medic-chamber-text-value.is-available {
    color: #287b55;
}

.medic-chamber-text-value.is-fee {
    color: #1268e7;
}

.medic-chamber-text-actions {
    display: flex;
    align-items: center;
    padding-top: 15px;
}

.medic-chamber-text-call {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 38px;
    padding: 0 15px;
    border: 1px solid #c9dcff;
    border-radius: 8px;
    background: #f5f9ff;
    color: #1769e9;
    font-size: 14px;
    line-height: 1;
    text-decoration: none;
    transition: background-color .18s ease, border-color .18s ease, color .18s ease;
}

.medic-chamber-text-call:hover,
.medic-chamber-text-call:focus-visible {
    border-color: #a8c8ff;
    background: #edf5ff;
    color: #0f58c5;
    text-decoration: none;
}

.medic-chamber-text-call:focus-visible {
    outline: 3px solid rgba(23, 105, 233, .16);
    outline-offset: 2px;
}

@media (max-width: 576px) {
    .medic-chamber-text-section {
        margin-bottom: 22px;
    }

    .medic-chamber-text-heading {
        margin-bottom: 10px;
        font-size: 21px;
    }

    .medic-chamber-text-card {
        border-radius: 14px;
    }

    .medic-chamber-text-card-head {
        padding: 14px 15px;
    }

    .medic-chamber-text-hospital {
        font-size: 16px;
    }

    .medic-chamber-text-body {
        padding: 7px 15px 14px;
    }

    .medic-chamber-text-row {
        grid-template-columns: 1fr;
        gap: 3px;
        padding: 11px 0;
    }

    .medic-chamber-text-label,
    .medic-chamber-text-value {
        font-size: 13px;
    }

    .medic-chamber-text-call {
        width: 100%;
    }
}
</style>

<section id="doctor-chambers" class="medic-chamber-text-section" aria-labelledby="doctor-chambers-heading">
    <h2 id="doctor-chambers-heading" class="medic-chamber-text-heading">
        <?= e(__t('chamber_appointment', 'Chamber & Appointment')) ?>
    </h2>

    <div class="medic-chamber-text-list">
        <?php foreach ($chambers as $chamber): ?>
            <?php
            /*
             * Every bilingual value follows one predictable order:
             * Chamber current language -> Chamber other language ->
             * Hospital current language -> Hospital other language.
             */
            $hospital_name = doctor_chambers_chamber_then_hospital_value(
                $chamber,
                ['chamber_name', 'name'],
                ['chamber_name_bn', 'name_bn'],
                ['hospital_name'],
                ['hospital_name_bn'],
                __t('hospital_chamber', 'Hospital / Chamber')
            );

            /* Address uses the same bilingual chamber-first, hospital-second fallback as Visiting Hour. */
            $address = doctor_chambers_preferred_address($chamber);

            $visiting_hour = doctor_chambers_visiting_schedule_value($chamber);

            $available_days = doctor_chambers_day_labels((string)($chamber['available_days'] ?? ''));
            $visiting_time = doctor_chambers_format_time_range(
                $chamber['available_from'] ?? '',
                $chamber['available_to'] ?? ''
            );
            $consultation_fee = doctor_chambers_fee($chamber['consultation_fee'] ?? 0);
            $appointment_phone = doctor_chambers_value($chamber, ['appointment_phone'], '');

            if ($appointment_phone === '') {
                $appointment_phone = doctor_chambers_value($chamber, ['hospital_phone'], '');
            }

            $is_closed = !empty($chamber['is_closed']);
            $hospital_url = doctor_chambers_hospital_url($chamber, $hospital_name);
            ?>

            <article class="medic-chamber-text-card">
                <header class="medic-chamber-text-card-head">
                    <h3 class="medic-chamber-text-hospital">
                        <a href="<?= e($hospital_url) ?>">
                            <?= e(doctor_chambers_display_text($hospital_name)) ?>
                        </a>
                    </h3>

                    <?php if ($is_closed): ?>
                        <span class="medic-chamber-text-closed">
                            <?= e(__t('closed', 'Closed')) ?>
                        </span>
                    <?php endif; ?>
                </header>

                <div class="medic-chamber-text-body">
                    <?php if ($address !== ''): ?>
                        <div class="medic-chamber-text-row">
                            <span class="medic-chamber-text-label"><?= e(__t('address', 'Address')) ?></span>
                            <span class="medic-chamber-text-value"><?= e(doctor_chambers_display_text($address)) ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if ($visiting_hour !== '' || $visiting_time !== ''): ?>
                        <div class="medic-chamber-text-row">
                            <span class="medic-chamber-text-label"><?= e(__t('visiting_hour', 'Visiting Hour')) ?></span>
                            <span class="medic-chamber-text-value">
                                <?= e(doctor_chambers_display_text($visiting_hour !== '' ? $visiting_hour : $visiting_time)) ?>
                            </span>
                        </div>
                    <?php endif; ?>

                    <?php if ($available_days !== ''): ?>
                        <div class="medic-chamber-text-row">
                            <span class="medic-chamber-text-label"><?= e(__t('available_days', 'Available Days')) ?></span>
                            <span class="medic-chamber-text-value is-available"><?= e(doctor_chambers_display_text($available_days)) ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if ($consultation_fee !== ''): ?>
                        <div class="medic-chamber-text-row">
                            <span class="medic-chamber-text-label"><?= e(__t('consultation_fee', 'Consultation Fee')) ?></span>
                            <span class="medic-chamber-text-value is-fee"><?= e($consultation_fee) ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if ($appointment_phone !== ''): ?>
                        <div class="medic-chamber-text-row">
                            <span class="medic-chamber-text-label"><?= e(__t('appointment', 'Appointment')) ?></span>
                            <span class="medic-chamber-text-value"><?= e(doctor_chambers_display_text($appointment_phone)) ?></span>
                        </div>

                        <div class="medic-chamber-text-actions">
                            <a href="tel:<?= e(doctor_chambers_phone_link($appointment_phone)) ?>" class="medic-chamber-text-call">
                                <?= e(__t('call_now', 'Call Now')) ?>
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</section>
