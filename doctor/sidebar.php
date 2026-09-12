<?php
/*
|--------------------------------------------------------------------------
| Doctor Sidebar Section
|--------------------------------------------------------------------------
| Simple light sidebar with full English/Bangla fallback.
|
| Fallback order:
| English page: Chamber English -> Chamber Bangla -> Hospital English -> Hospital Bangla
| Bangla page:  Chamber Bangla -> Chamber English -> Hospital Bangla -> Hospital English
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

    define(
        'CURRENT_LANG',
        ($current_request_path === 'bn' || str_starts_with($current_request_path, 'bn/')) ? 'bn' : 'en'
    );
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
| Sidebar Helpers
|--------------------------------------------------------------------------
*/

if (!function_exists('doctor_sidebar_table_exists')) {
    function doctor_sidebar_table_exists(string $table): bool
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
            $stmt = $pdo->prepare("
                SELECT COUNT(*)
                FROM INFORMATION_SCHEMA.TABLES
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = :table
            ");
            $stmt->execute([':table' => $table]);

            return $cache[$table] = (int)$stmt->fetchColumn() > 0;
        } catch (Throwable $e) {
            return $cache[$table] = false;
        }
    }
}

if (!function_exists('doctor_sidebar_column_exists')) {
    function doctor_sidebar_column_exists(string $table, string $column): bool
    {
        global $pdo;

        static $cache = [];

        $table = trim($table);
        $column = trim($column);
        $key = $table . '.' . $column;

        if ($table === '' || $column === '') {
            return false;
        }

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

            return $cache[$key] = (int)$stmt->fetchColumn() > 0;
        } catch (Throwable $e) {
            return $cache[$key] = false;
        }
    }
}

if (!function_exists('doctor_sidebar_first_value')) {
    function doctor_sidebar_first_value(array $source, array $keys, string $default = ''): string
    {
        foreach ($keys as $key) {
            $value = trim((string)($source[$key] ?? ''));

            if ($value !== '') {
                return $value;
            }
        }

        return $default;
    }
}

if (!function_exists('doctor_sidebar_bilingual_value')) {
    function doctor_sidebar_bilingual_value(array $source, array $english_keys, array $bangla_keys): string
    {
        $english = doctor_sidebar_first_value($source, $english_keys);
        $bangla = doctor_sidebar_first_value($source, $bangla_keys);

        if (defined('CURRENT_LANG') && CURRENT_LANG === 'bn') {
            return $bangla !== '' ? $bangla : $english;
        }

        return $english !== '' ? $english : $bangla;
    }
}

if (!function_exists('doctor_sidebar_chamber_hospital_value')) {
    function doctor_sidebar_chamber_hospital_value(
        array $chamber,
        array $chamber_english_keys,
        array $chamber_bangla_keys,
        array $hospital_english_keys,
        array $hospital_bangla_keys
    ): string {
        $chamber_english = doctor_sidebar_first_value($chamber, $chamber_english_keys);
        $chamber_bangla = doctor_sidebar_first_value($chamber, $chamber_bangla_keys);
        $hospital_english = doctor_sidebar_first_value($chamber, $hospital_english_keys);
        $hospital_bangla = doctor_sidebar_first_value($chamber, $hospital_bangla_keys);

        if (defined('CURRENT_LANG') && CURRENT_LANG === 'bn') {
            foreach ([$chamber_bangla, $chamber_english, $hospital_bangla, $hospital_english] as $value) {
                if ($value !== '') {
                    return $value;
                }
            }
        } else {
            foreach ([$chamber_english, $chamber_bangla, $hospital_english, $hospital_bangla] as $value) {
                if ($value !== '') {
                    return $value;
                }
            }
        }

        return '';
    }
}

if (!function_exists('doctor_sidebar_phone_link')) {
    function doctor_sidebar_phone_link(string $phone): string
    {
        return preg_replace('/[^\d+]/', '', trim($phone));
    }
}

if (!function_exists('doctor_sidebar_display_number')) {
    function doctor_sidebar_display_number(string $value): string
    {
        if (!defined('CURRENT_LANG') || CURRENT_LANG !== 'bn') {
            return $value;
        }

        return str_replace(
            ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'],
            ['০', '১', '২', '৩', '৪', '৫', '৬', '৭', '৮', '৯'],
            $value
        );
    }
}

/*
|--------------------------------------------------------------------------
| Specialty Lookup
|--------------------------------------------------------------------------
*/

if (!function_exists('doctor_sidebar_get_specialty_by_id')) {
    function doctor_sidebar_get_specialty_by_id(int $specialty_id): array
    {
        global $pdo;

        static $cache = [];
        $empty = ['name' => '', 'name_bn' => ''];

        if ($specialty_id <= 0) {
            return $empty;
        }

        if (isset($cache[$specialty_id])) {
            return $cache[$specialty_id];
        }

        if (
            !isset($pdo) ||
            !($pdo instanceof PDO) ||
            !doctor_sidebar_table_exists('specialties') ||
            !doctor_sidebar_column_exists('specialties', 'id')
        ) {
            return $cache[$specialty_id] = $empty;
        }

        $english_select = doctor_sidebar_column_exists('specialties', 'name')
            ? 'name'
            : (doctor_sidebar_column_exists('specialties', 'name_en') ? 'name_en AS name' : "'' AS name");

        $bangla_select = doctor_sidebar_column_exists('specialties', 'name_bn')
            ? 'name_bn'
            : "'' AS name_bn";

        try {
            $stmt = $pdo->prepare("
                SELECT {$english_select}, {$bangla_select}
                FROM specialties
                WHERE id = :specialty_id
                LIMIT 1
            ");
            $stmt->execute([':specialty_id' => $specialty_id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!is_array($row)) {
                return $cache[$specialty_id] = $empty;
            }

            return $cache[$specialty_id] = [
                'name' => trim((string)($row['name'] ?? '')),
                'name_bn' => trim((string)($row['name_bn'] ?? '')),
            ];
        } catch (Throwable $e) {
            return $cache[$specialty_id] = $empty;
        }
    }
}

/*
|--------------------------------------------------------------------------
| Location Lookup
|--------------------------------------------------------------------------
| Only thana and district are used. Division is never queried or displayed.
|--------------------------------------------------------------------------
*/

if (!function_exists('doctor_sidebar_location_pair')) {
    function doctor_sidebar_location_pair(string $table, int $id): array
    {
        global $pdo;

        static $cache = [];
        $empty = ['en' => '', 'bn' => ''];

        if ($id <= 0 || !in_array($table, ['thanas', 'districts'], true)) {
            return $empty;
        }

        $cache_key = $table . ':' . $id;

        if (isset($cache[$cache_key])) {
            return $cache[$cache_key];
        }

        if (
            !isset($pdo) ||
            !($pdo instanceof PDO) ||
            !doctor_sidebar_table_exists($table) ||
            !doctor_sidebar_column_exists($table, 'id')
        ) {
            return $cache[$cache_key] = $empty;
        }

        $english_select = doctor_sidebar_column_exists($table, 'name')
            ? 'name'
            : (doctor_sidebar_column_exists($table, 'name_en') ? 'name_en AS name' : "'' AS name");

        $bangla_select = doctor_sidebar_column_exists($table, 'name_bn')
            ? 'name_bn'
            : "'' AS name_bn";

        try {
            $stmt = $pdo->prepare("
                SELECT {$english_select}, {$bangla_select}
                FROM `{$table}`
                WHERE id = :id
                LIMIT 1
            ");
            $stmt->execute([':id' => $id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!is_array($row)) {
                return $cache[$cache_key] = $empty;
            }

            return $cache[$cache_key] = [
                'en' => trim((string)($row['name'] ?? '')),
                'bn' => trim((string)($row['name_bn'] ?? '')),
            ];
        } catch (Throwable $e) {
            return $cache[$cache_key] = $empty;
        }
    }
}

if (!function_exists('doctor_sidebar_structured_hospital_address')) {
    function doctor_sidebar_structured_hospital_address(array $chamber): string
    {
        $house = doctor_sidebar_bilingual_value(
            $chamber,
            ['hospital_house', 'hospital_house_no', 'hospital_house_number'],
            ['hospital_house_bn', 'hospital_house_no_bn', 'hospital_house_number_bn']
        );

        $road = doctor_sidebar_bilingual_value(
            $chamber,
            ['hospital_road', 'hospital_road_no'],
            ['hospital_road_bn', 'hospital_road_no_bn']
        );

        $area = doctor_sidebar_bilingual_value(
            $chamber,
            ['hospital_area'],
            ['hospital_area_bn']
        );

        $thana_pair = doctor_sidebar_location_pair('thanas', (int)($chamber['hospital_thana_id'] ?? 0));
        $district_pair = doctor_sidebar_location_pair('districts', (int)($chamber['hospital_district_id'] ?? 0));

        $thana = defined('CURRENT_LANG') && CURRENT_LANG === 'bn'
            ? ($thana_pair['bn'] !== '' ? $thana_pair['bn'] : $thana_pair['en'])
            : ($thana_pair['en'] !== '' ? $thana_pair['en'] : $thana_pair['bn']);

        $district = defined('CURRENT_LANG') && CURRENT_LANG === 'bn'
            ? ($district_pair['bn'] !== '' ? $district_pair['bn'] : $district_pair['en'])
            : ($district_pair['en'] !== '' ? $district_pair['en'] : $district_pair['bn']);

        $post_code = doctor_sidebar_first_value($chamber, ['hospital_post_code', 'hospital_postal_code']);

        $parts = array_values(array_filter([$house, $road, $area], static function ($value): bool {
            return trim((string)$value) !== '';
        }));

        if ($thana !== '') {
            $parts[] = $post_code !== ''
                ? $thana . '-' . doctor_sidebar_display_number($post_code)
                : $thana;
        } elseif ($post_code !== '') {
            $parts[] = doctor_sidebar_display_number($post_code);
        }

        if ($district !== '') {
            $parts[] = $district;
        }

        return implode(', ', $parts);
    }
}

/*
|--------------------------------------------------------------------------
| Chamber Query
|--------------------------------------------------------------------------
*/

if (!function_exists('doctor_sidebar_hospital_select')) {
    function doctor_sidebar_hospital_select(string $column, string $alias): string
    {
        return doctor_sidebar_column_exists('hospitals', $column)
            ? 'h.`' . $column . '` AS `' . $alias . '`'
            : "'' AS `{$alias}`";
    }
}

if (!function_exists('doctor_sidebar_get_chambers')) {
    function doctor_sidebar_get_chambers(int $doctor_id): array
    {
        global $pdo;

        if (
            $doctor_id <= 0 ||
            !isset($pdo) ||
            !($pdo instanceof PDO) ||
            !doctor_sidebar_table_exists('chambers')
        ) {
            return [];
        }

        $select = ['c.*'];
        $join = '';

        if (
            doctor_sidebar_table_exists('hospitals') &&
            doctor_sidebar_column_exists('chambers', 'hospital_id')
        ) {
            $join = ' LEFT JOIN hospitals h ON h.id = c.hospital_id ';

            $hospital_fields = [
                'name' => 'hospital_name',
                'name_bn' => 'hospital_name_bn',
                'address' => 'hospital_address',
                'address_bn' => 'hospital_address_bn',
                'phone' => 'hospital_phone',
                'map_url' => 'hospital_map_url',
                'google_map_url' => 'hospital_google_map_url',
                'house' => 'hospital_house',
                'house_bn' => 'hospital_house_bn',
                'house_no' => 'hospital_house_no',
                'house_no_bn' => 'hospital_house_no_bn',
                'house_number' => 'hospital_house_number',
                'house_number_bn' => 'hospital_house_number_bn',
                'road' => 'hospital_road',
                'road_bn' => 'hospital_road_bn',
                'road_no' => 'hospital_road_no',
                'road_no_bn' => 'hospital_road_no_bn',
                'area' => 'hospital_area',
                'area_bn' => 'hospital_area_bn',
                'post_code' => 'hospital_post_code',
                'postal_code' => 'hospital_postal_code',
                'thana_id' => 'hospital_thana_id',
                'district_id' => 'hospital_district_id',
                'visiting_hours' => 'hospital_visiting_hours',
                'visiting_hours_bn' => 'hospital_visiting_hours_bn',
                'opening_hours' => 'hospital_opening_hours',
                'opening_hours_bn' => 'hospital_opening_hours_bn',
            ];

            foreach ($hospital_fields as $column => $alias) {
                $select[] = doctor_sidebar_hospital_select($column, $alias);
            }
        } else {
            $fallback_aliases = [
                'hospital_name', 'hospital_name_bn',
                'hospital_address', 'hospital_address_bn',
                'hospital_phone', 'hospital_map_url', 'hospital_google_map_url',
                'hospital_house', 'hospital_house_bn',
                'hospital_house_no', 'hospital_house_no_bn',
                'hospital_house_number', 'hospital_house_number_bn',
                'hospital_road', 'hospital_road_bn',
                'hospital_road_no', 'hospital_road_no_bn',
                'hospital_area', 'hospital_area_bn',
                'hospital_post_code', 'hospital_postal_code',
                'hospital_thana_id', 'hospital_district_id',
                'hospital_visiting_hours', 'hospital_visiting_hours_bn',
                'hospital_opening_hours', 'hospital_opening_hours_bn',
            ];

            foreach ($fallback_aliases as $alias) {
                $select[] = (str_ends_with($alias, '_id') ? '0' : "''") . " AS `{$alias}`";
            }
        }

        $where = ['c.doctor_id = :doctor_id'];

        if (doctor_sidebar_column_exists('chambers', 'status')) {
            $where[] = "(c.status = 'active' OR c.status = 'published' OR c.status IS NULL OR c.status = '')";
        }

        $order_by = 'c.id ASC';

        if (doctor_sidebar_column_exists('chambers', 'sort_order')) {
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

            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return is_array($rows) ? $rows : [];
        } catch (Throwable $e) {
            return [];
        }
    }
}

/*
|--------------------------------------------------------------------------
| Profile Claim Status
|--------------------------------------------------------------------------
*/

if (!function_exists('doctor_sidebar_claim_status')) {
    function doctor_sidebar_claim_status(int $doctor_id): array
    {
        global $pdo;

        $status = [
            'is_claimed' => false,
            'has_pending_claim' => false,
            'label' => __t('unclaimed_profile', 'Unclaimed profile'),
        ];

        if ($doctor_id <= 0 || !isset($pdo) || !($pdo instanceof PDO)) {
            return $status;
        }

        if (
            doctor_sidebar_table_exists('users') &&
            doctor_sidebar_column_exists('users', 'claimed_doctor_id')
        ) {
            try {
                $where = ['claimed_doctor_id = :doctor_id'];

                if (doctor_sidebar_column_exists('users', 'status')) {
                    $where[] = "(status = 'active' OR status IS NULL OR status = '')";
                }

                $stmt = $pdo->prepare(
                    'SELECT id FROM users WHERE ' . implode(' AND ', $where) . ' LIMIT 1'
                );
                $stmt->execute([':doctor_id' => $doctor_id]);

                if ((int)$stmt->fetchColumn() > 0) {
                    $status['is_claimed'] = true;
                    $status['label'] = __t('claimed_profile', 'Claimed profile');

                    return $status;
                }
            } catch (Throwable $e) {
                // Keep page available.
            }
        }

        if (
            doctor_sidebar_table_exists('profile_claims') &&
            doctor_sidebar_column_exists('profile_claims', 'doctor_id') &&
            doctor_sidebar_column_exists('profile_claims', 'status')
        ) {
            try {
                $where = ['doctor_id = :doctor_id'];

                if (doctor_sidebar_column_exists('profile_claims', 'claim_type')) {
                    $where[] = "claim_type = 'doctor'";
                }

                $stmt = $pdo->prepare("
                    SELECT status
                    FROM profile_claims
                    WHERE " . implode(' AND ', $where) . "
                    ORDER BY id DESC
                    LIMIT 1
                ");
                $stmt->execute([':doctor_id' => $doctor_id]);

                $claim_status = strtolower(trim((string)$stmt->fetchColumn()));

                if ($claim_status === 'approved') {
                    $status['is_claimed'] = true;
                    $status['label'] = __t('claimed_profile', 'Claimed profile');
                } elseif ($claim_status === 'pending') {
                    $status['has_pending_claim'] = true;
                    $status['label'] = __t('claim_request_pending', 'Claim request pending');
                }
            } catch (Throwable $e) {
                // Keep page available.
            }
        }

        return $status;
    }
}

/*
|--------------------------------------------------------------------------
| Doctor Data
|--------------------------------------------------------------------------
*/

$doctor_id = (int)($doctor['id'] ?? 0);

$doctor_name = doctor_sidebar_bilingual_value($doctor, ['name'], ['name_bn']);
$doctor_degree = doctor_sidebar_bilingual_value($doctor, ['degree'], ['degree_bn']);
$doctor_training = doctor_sidebar_bilingual_value($doctor, ['training'], ['training_bn']);
$doctor_fellowship = doctor_sidebar_bilingual_value($doctor, ['fellowship'], ['fellowship_bn']);
$doctor_designation = doctor_sidebar_bilingual_value($doctor, ['designation'], ['designation_bn']);
$doctor_primary_hospital = doctor_sidebar_bilingual_value($doctor, ['primary_hospital'], ['primary_hospital_bn']);

$specialty = doctor_sidebar_get_specialty_by_id(
    (int)($doctor['specialty_id'] ?? ($doctor['speciality_id'] ?? 0))
);

$doctor_specialty_name = doctor_sidebar_bilingual_value(
    [
        'specialty_name' => $specialty['name'] !== ''
            ? $specialty['name']
            : ($doctor['specialty_name'] ?? ($doctor['speciality_name'] ?? '')),
        'specialty_name_bn' => $specialty['name_bn'] !== ''
            ? $specialty['name_bn']
            : ($doctor['specialty_name_bn'] ?? ($doctor['speciality_name_bn'] ?? '')),
    ],
    ['specialty_name'],
    ['specialty_name_bn']
);

$has_doctor_info = $doctor_name !== ''
    || $doctor_degree !== ''
    || $doctor_training !== ''
    || $doctor_fellowship !== ''
    || $doctor_specialty_name !== ''
    || $doctor_designation !== ''
    || $doctor_primary_hospital !== '';

$claim_status = doctor_sidebar_claim_status($doctor_id);
$is_claimed = (bool)$claim_status['is_claimed'];
$has_pending_claim = (bool)$claim_status['has_pending_claim'];
$has_claim_info = $doctor_id > 0;

$claim_url = function_exists('site_url')
    ? site_url('claim-profile.php?type=doctor&id=' . $doctor_id)
    : '#';

$chambers = doctor_sidebar_get_chambers($doctor_id);

if (!$chambers && function_exists('get_doctor_chambers')) {
    $chambers = get_doctor_chambers($doctor_id);
}

$has_chamber_info = !empty($chambers);

if (!$has_doctor_info && !$has_claim_info && !$has_chamber_info) {
    return;
}

?>

<link rel="stylesheet" href="<?= e(site_url('assets/css/doctor-sidebar.css')) ?>">

<aside class="medic-sidebar-light">

    <?php if ($has_doctor_info): ?>
        <section class="medic-sidebar-light-card">
            <header class="medic-sidebar-light-head">
                <h3><?= e(__t('doctor_information', 'Doctor Information')) ?></h3>
            </header>

            <div class="medic-sidebar-light-body">
                <?php if ($doctor_name !== ''): ?>
                    <p class="medic-sidebar-light-name"><?= e($doctor_name) ?></p>
                <?php endif; ?>

                <div class="medic-sidebar-light-list">
                    <?php if ($doctor_degree !== ''): ?>
                        <div class="medic-sidebar-light-row">
                            <span class="medic-sidebar-light-label"><?= e(__t('degree', 'Degree')) ?></span>
                            <span class="medic-sidebar-light-value"><?= nl2br(e($doctor_degree)) ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if ($doctor_training !== ''): ?>
                        <div class="medic-sidebar-light-row">
                            <span class="medic-sidebar-light-label"><?= e(__t('training', 'Training')) ?></span>
                            <span class="medic-sidebar-light-value"><?= nl2br(e($doctor_training)) ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if ($doctor_fellowship !== ''): ?>
                        <div class="medic-sidebar-light-row">
                            <span class="medic-sidebar-light-label"><?= e(__t('fellowship', 'Fellowship')) ?></span>
                            <span class="medic-sidebar-light-value"><?= nl2br(e($doctor_fellowship)) ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if ($doctor_specialty_name !== ''): ?>
                        <div class="medic-sidebar-light-row">
                            <span class="medic-sidebar-light-label"><?= e(__t('specialty', 'Specialty')) ?></span>
                            <span class="medic-sidebar-light-value"><?= e($doctor_specialty_name) ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if ($doctor_designation !== ''): ?>
                        <div class="medic-sidebar-light-row">
                            <span class="medic-sidebar-light-label"><?= e(__t('designation', 'Designation')) ?></span>
                            <span class="medic-sidebar-light-value"><?= e($doctor_designation) ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if ($doctor_primary_hospital !== ''): ?>
                        <div class="medic-sidebar-light-row">
                            <span class="medic-sidebar-light-label"><?= e(__t('primary_hospital', 'Primary Hospital')) ?></span>
                            <span class="medic-sidebar-light-value"><?= e($doctor_primary_hospital) ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <?php if ($has_claim_info): ?>
        <section class="medic-sidebar-light-card">
            <header class="medic-sidebar-light-head">
                <h3><?= e(__t('profile_claims', 'Profile Claims')) ?></h3>
            </header>

            <div class="medic-sidebar-light-body">
                <?php if ($is_claimed): ?>
                    <span class="medic-sidebar-light-badge claimed">
                        ✓ <?= e(__t('claimed_profile', 'Claimed profile')) ?>
                    </span>
                <?php elseif ($has_pending_claim): ?>
                    <span class="medic-sidebar-light-badge pending">
                        <?= e(__t('claim_request_pending', 'Claim request pending')) ?>
                    </span>
                <?php else: ?>
                    <span class="medic-sidebar-light-badge unclaimed">
                        <?= e(__t('unclaimed_profile', 'Unclaimed profile')) ?>
                    </span>
                <?php endif; ?>

                <?php if (!$is_claimed): ?>
                    <div class="medic-sidebar-light-actions">
                        <a href="<?= e($claim_url) ?>" class="medic-sidebar-light-btn">
                            <?= e(__t('claim_profile', 'Claim Profile')) ?>
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    <?php endif; ?>

    <?php if ($has_chamber_info): ?>
        <section class="medic-sidebar-light-card">
            <header class="medic-sidebar-light-head">
                <h3><?= e(__t('chamber_information', 'Chamber Information')) ?></h3>
            </header>

            <div class="medic-sidebar-light-body">
                <?php foreach ($chambers as $chamber): ?>
                    <?php
                    $hospital_name = doctor_sidebar_chamber_hospital_value(
                        $chamber,
                        ['name', 'chamber_name', 'hospital_name'],
                        ['name_bn', 'chamber_name_bn', 'hospital_name_bn'],
                        ['hospital_name'],
                        ['hospital_name_bn']
                    );

                    $address = doctor_sidebar_chamber_hospital_value(
                        $chamber,
                        ['address'],
                        ['address_bn'],
                        ['hospital_address'],
                        ['hospital_address_bn']
                    );

                    if ($address === '') {
                        $address = doctor_sidebar_structured_hospital_address($chamber);
                    }

                    $visiting_hour = doctor_sidebar_chamber_hospital_value(
                        $chamber,
                        ['schedule', 'visiting_hours'],
                        ['schedule_bn', 'visiting_hours_bn'],
                        ['hospital_visiting_hours', 'hospital_opening_hours'],
                        ['hospital_visiting_hours_bn', 'hospital_opening_hours_bn']
                    );

                    $phone = doctor_sidebar_chamber_hospital_value(
                        $chamber,
                        ['appointment_phone', 'serial_phone', 'assistant_phone', 'phone'],
                        ['appointment_phone_bn', 'serial_phone_bn', 'assistant_phone_bn', 'phone_bn'],
                        ['hospital_phone'],
                        ['hospital_phone_bn']
                    );

                    $map_url = doctor_sidebar_first_value($chamber, [
                        'hospital_map_url',
                        'hospital_google_map_url',
                        'map_url',
                        'google_map_url',
                    ]);
                    ?>

                    <?php if ($hospital_name !== '' || $address !== '' || $visiting_hour !== '' || $phone !== '' || $map_url !== ''): ?>
                        <article class="medic-sidebar-light-chamber">
                            <?php if ($hospital_name !== ''): ?>
                                <p class="medic-sidebar-light-chamber-title"><?= e($hospital_name) ?></p>
                            <?php endif; ?>

                            <div class="medic-sidebar-light-list">
                                <?php if ($address !== ''): ?>
                                    <div class="medic-sidebar-light-row">
                                        <span class="medic-sidebar-light-label"><?= e(__t('address', 'Address')) ?></span>
                                        <span class="medic-sidebar-light-value"><?= e($address) ?></span>
                                    </div>
                                <?php endif; ?>

                                <?php if ($visiting_hour !== ''): ?>
                                    <div class="medic-sidebar-light-row">
                                        <span class="medic-sidebar-light-label"><?= e(__t('visiting_hour', 'Visiting Hour')) ?></span>
                                        <span class="medic-sidebar-light-value"><?= e($visiting_hour) ?></span>
                                    </div>
                                <?php endif; ?>

                                <?php if ($phone !== ''): ?>
                                    <div class="medic-sidebar-light-row">
                                        <span class="medic-sidebar-light-label"><?= e(__t('call', 'Call')) ?></span>
                                        <span class="medic-sidebar-light-value">
                                            <a href="tel:<?= e(doctor_sidebar_phone_link($phone)) ?>">
                                                <?= e(doctor_sidebar_display_number($phone)) ?>
                                            </a>
                                        </span>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <?php if ($phone !== '' || $map_url !== ''): ?>
                                <div class="medic-sidebar-light-actions">
                                    <?php if ($phone !== ''): ?>
                                        <a href="tel:<?= e(doctor_sidebar_phone_link($phone)) ?>" class="medic-sidebar-light-btn">
                                            <?= e(__t('call_now', 'Call Now')) ?>
                                        </a>
                                    <?php endif; ?>

                                    <?php if ($map_url !== ''): ?>
                                        <a href="<?= e($map_url) ?>" class="medic-sidebar-light-btn" target="_blank" rel="noopener">
                                            <?= e(__t('view_google_map', 'View Google Map')) ?>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </article>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

</aside>
