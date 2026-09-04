<?php
require_once __DIR__ . '/includes/functions.php';


/*
|--------------------------------------------------------------------------
| Frontend Language Helpers
|--------------------------------------------------------------------------
| The route loader normally defines CURRENT_LANG, __t(), lang_text(), and
| front_url(). These fallbacks keep direct hospital.php access safe too.
*/
if (!function_exists('__t')) {
    function __t(string $key, string $fallback = ''): string
    {
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
    function front_url(string $path = '', $lang = null): string
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
| PHP 7 Compatibility Helper
|--------------------------------------------------------------------------
*/
function hospital_page_starts_with(string $haystack, string $needle): bool
{
    return $needle === '' || strpos($haystack, $needle) === 0;
}

function hospital_page_lang(): string
{
    return defined('CURRENT_LANG') && CURRENT_LANG === 'bn' ? 'bn' : 'en';
}

function hospital_page_localized_value(array $data, string $key, string $default = ''): string
{
    $english = trim((string)($data[$key] ?? ''));
    $bangla = trim((string)($data[$key . '_bn'] ?? ''));

    $value = lang_text($english, $bangla);

    return $value !== '' ? $value : $default;
}

$hospital_profile_page_version = 'profile-query-safe-20260629';

$slug = $_GET['slug'] ?? '';

/*
|--------------------------------------------------------------------------
| Redirect Old Query URL To Clean URL
|--------------------------------------------------------------------------
*/
if ($slug && basename($_SERVER['SCRIPT_NAME'] ?? '') === 'hospital.php') {
    $request_uri = $_SERVER['REQUEST_URI'] ?? '';

    if (strpos($request_uri, 'hospital.php') !== false) {
        redirect(front_url('hospital/' . $slug));
    }
}

$hospital = get_hospital_by_slug($slug);

if (!$hospital) {
    http_response_code(404);

    $page_title = __t('hospital_profile_not_found_title', 'Hospital Not Found');
    $meta_description = __t('hospital_profile_not_found_description', 'The hospital you are looking for was not found.');

    include __DIR__ . '/includes/header.php';
    ?>

    <main class="page">
        <div class="container">
            <div class="card">
                <h2><?= e(__t('hospital_profile_not_found_heading', 'Hospital not found')) ?></h2>
                <p><?= e(__t('hospital_profile_not_found_text', 'Please go back to hospital list.')) ?></p>
                <a href="<?= e(front_url('hospitals')) ?>" class="btn btn-primary"><?= e(__t('hospitals', 'Hospitals')) ?></a>
            </div>
        </div>
    </main>

    <?php
    include __DIR__ . '/includes/footer.php';
    exit;
}

$hospital_id = (int)$hospital['id'];

track_profile_view('hospitals', $hospital_id);

/*
|--------------------------------------------------------------------------
| Safe Table And Column Checkers
|--------------------------------------------------------------------------
*/
function hospital_page_table_exists(string $table): bool
{
    global $pdo;

    static $cache = [];

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
        $cache[$table] = (int)$stmt->fetchColumn() > 0;
    } catch (Throwable $e) {
        $cache[$table] = false;
    }

    return $cache[$table];
}

function hospital_page_column_exists(string $table, string $column): bool
{
    global $pdo;

    static $cache = [];
    $cache_key = $table . '.' . $column;

    if (array_key_exists($cache_key, $cache)) {
        return $cache[$cache_key];
    }

    if (!hospital_page_table_exists($table)) {
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
    } catch (Throwable $e) {
        $cache[$cache_key] = false;
    }

    return $cache[$cache_key];
}


/*
|--------------------------------------------------------------------------
| Site Settings Helpers
|--------------------------------------------------------------------------
| This hospital profile page uses values from admin/site-settings.php.
|--------------------------------------------------------------------------
*/

function hospital_page_site_setting(string $key, string $default = ''): string
{
    global $pdo;

    static $settings_cache = null;

    if ($settings_cache === null) {
        $settings_cache = [];

        try {
            if (!hospital_page_table_exists('site_settings')) {
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

function hospital_page_site_name(): string
{
    return hospital_page_site_setting('site_name', defined('APP_NAME') ? APP_NAME : 'Deluti');
}

function hospital_page_setting_url(string $key, string $default = ''): string
{
    $value = hospital_page_site_setting($key, $default);

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

function hospital_page_setting_color(string $key, string $default): string
{
    $value = hospital_page_site_setting($key, $default);

    if (preg_match('/^#[0-9a-fA-F]{6}$/', $value)) {
        return $value;
    }

    return $default;
}

function hospital_page_asset_url(string $path, string $fallback = ''): string
{
    $path = trim($path);

    if ($path === '') {
        $path = trim($fallback);
    }

    if ($path === '') {
        return '';
    }

    if (preg_match('/^https?:\/\//i', $path)) {
        return $path;
    }

    $path = ltrim($path, './');

    if (function_exists('site_url')) {
        return site_url($path);
    }

    return '/' . $path;
}


/*
|--------------------------------------------------------------------------
| Hospital Value Helper
|--------------------------------------------------------------------------
*/
function hospital_value(array $hospital, string $key, $default = '')
{
    return array_key_exists($key, $hospital) && $hospital[$key] !== null ? $hospital[$key] : $default;
}

function hospital_page_summary(string $text, int $width = 155): string
{
    $text = trim($text);

    if ($text === '') {
        return '';
    }

    if (function_exists('mb_strimwidth')) {
        return mb_strimwidth($text, 0, $width, '...', 'UTF-8');
    }

    if (strlen($text) <= $width) {
        return $text;
    }

    return substr($text, 0, max(0, $width - 3)) . '...';
}

/*
|--------------------------------------------------------------------------
| Bangla Digit Display Helper
|--------------------------------------------------------------------------
| Converts user-facing text on /bn/hospital/... from 0-9 to ০-৯. Machine
| values, URLs, schema JSON and tel/WhatsApp link targets are unchanged.
*/
function hospital_page_current_lang(): string
{
    global $lang;

    if (isset($lang) && $lang === 'bn') {
        return 'bn';
    }

    if (defined('CURRENT_LANG') && CURRENT_LANG === 'bn') {
        return 'bn';
    }

    $path = trim((string)parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/');

    return $path === 'bn' || hospital_page_starts_with($path, 'bn/') ? 'bn' : 'en';
}

function hospital_page_localize_digits($value): string
{
    $value = (string)$value;

    if (hospital_page_current_lang() !== 'bn') {
        return $value;
    }

    return strtr($value, [
        '0' => '০', '1' => '১', '2' => '২', '3' => '৩', '4' => '৪',
        '5' => '৫', '6' => '৬', '7' => '৭', '8' => '৮', '9' => '৯',
    ]);
}

function hospital_page_url_slug(string $text): string
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

function hospital_page_hospitals_url(array $filters = []): string
{
    /*
     * Local breadcrumb URL builder.
     * Do not use any global hospitals_clean_url() here, because some older versions
     * may drop the hospital type when the URL has district + type only.
     *
     * Supported clean listing URLs:
     * /hospitals
     * /hospitals/khulna
     * /hospitals/khulna/paikgachha
     * /hospitals/khulna/paikgachha/private-hospital
     * /hospitals/khulna/private-hospital
     * /hospitals/private-hospital
     */
    $district = trim((string)($filters['district'] ?? ($filters['city'] ?? '')));
    $thana = trim((string)($filters['thana'] ?? ''));
    $type = trim((string)($filters['type'] ?? ''));

    $path = 'hospitals';

    if ($district !== '') {
        $path .= '/' . hospital_page_url_slug($district);

        if ($thana !== '') {
            $path .= '/' . hospital_page_url_slug($thana);

            if ($type !== '') {
                $path .= '/' . hospital_page_url_slug($type);
            }
        } elseif ($type !== '') {
            $path .= '/' . hospital_page_url_slug($type);
        }
    } elseif ($type !== '') {
        $path .= '/' . hospital_page_url_slug($type);
    }

    $query = [];

    foreach (['search', 'service', 'emergency', 'verified', 'featured'] as $key) {
        $value = trim((string)($filters[$key] ?? ''));

        if ($value !== '') {
            $query[$key] = $value;
        }
    }

    return front_url($path . (!empty($query) ? '?' . http_build_query($query) : ''));
}

function hospital_page_type_url(string $hospital_type, string $district = '', string $thana = ''): string
{
    $hospital_type = trim($hospital_type);
    $district = trim($district);
    $thana = trim($thana);

    if ($hospital_type === '') {
        return front_url('hospitals');
    }

    if ($district !== '' && $thana !== '') {
        return hospital_page_hospitals_url([
            'district' => $district,
            'thana' => $thana,
            'type' => $hospital_type,
        ]);
    }

    if ($district !== '') {
        return hospital_page_hospitals_url([
            'district' => $district,
            'type' => $hospital_type,
        ]);
    }

    return hospital_page_hospitals_url([
        'type' => $hospital_type,
    ]);
}

/*
|--------------------------------------------------------------------------
| Breadcrumb Address Helper
|--------------------------------------------------------------------------
*/
function hospital_page_address_name_by_id(string $table, int $id, string $lang = 'en'): string
{
    global $pdo;

    $allowed_tables = ['divisions', 'districts', 'thanas'];

    if (
        $id <= 0
        || !in_array($table, $allowed_tables, true)
        || !hospital_page_table_exists($table)
    ) {
        return '';
    }

    $name_col = $lang === 'bn' ? 'name_bn' : 'name_en';

    if (!hospital_page_column_exists($table, $name_col)) {
        $name_col = hospital_page_column_exists($table, 'name') ? 'name' : '';
    }

    if ($name_col === '') {
        return '';
    }

    try {
        $stmt = $pdo->prepare("SELECT `{$name_col}` AS name FROM `{$table}` WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        return trim((string)($row['name'] ?? ''));
    } catch (Throwable $e) {
        error_log('Hospital breadcrumb lookup failed: ' . $e->getMessage());
        return '';
    }
}

/*
|--------------------------------------------------------------------------
| Text List Parser
|--------------------------------------------------------------------------
*/
function hospital_text_list(string $text): array
{
    $text = trim($text);

    if ($text === '') {
        return [];
    }

    $parts = preg_split('/\r\n|\r|\n|,/', $text);
    $items = [];

    foreach ($parts as $part) {
        $part = trim((string)$part);

        if ($part !== '') {
            $items[] = $part;
        }
    }

    return array_values(array_unique($items));
}


/*
|--------------------------------------------------------------------------
| Limited Tag Renderer
|--------------------------------------------------------------------------
*/
function render_hospital_limited_tags(array $items, int $limit = 10): void
{
    $items = array_values(array_filter(array_unique($items)));
    $total = count($items);

    if ($total <= 0) {
        return;
    }

    foreach ($items as $index => $item) {
        $extra_class = $index >= $limit ? ' hospital-hidden-tag' : '';
        ?>
        <span class="tag<?= e($extra_class) ?>"><?= e(hospital_page_localize_digits($item)) ?></span>
        <?php
    }

    if ($total > $limit) {
        $remaining_count = $total - $limit;
        $more_label = hospital_page_localize_digits($remaining_count) . '+';
        ?>
        <button type="button" class="tag hospital-more-tag" data-show-more="1" data-more-text="<?= e($more_label) ?>" aria-label="<?= e(hospital_page_localize_digits(sprintf(__t('hospital_profile_show_remaining_aria', 'Show remaining %d items'), $remaining_count))) ?>">
            <?= e($more_label) ?>
        </button>
        <?php
    }
}

/*
|--------------------------------------------------------------------------
| Get Auto Departments From Chamber Doctors
|--------------------------------------------------------------------------
*/
function get_hospital_page_departments(int $hospital_id): array
{
    global $pdo;

    if ($hospital_id <= 0) {
        return [];
    }

    if (
        !hospital_page_table_exists('chambers')
        || !hospital_page_table_exists('doctors')
        || !hospital_page_table_exists('specialties')
    ) {
        return [];
    }

    /*
     * Build the SELECT list only from columns that exist. This supports both
     * the current schema and older installations without optional Bangla/SEO
     * fields. A profile page must never fail just because optional data is
     * unavailable.
     */
    $specialty_columns = ['id', 'name', 'slug'];

    foreach (['name_bn', 'description', 'description_bn', 'icon'] as $column) {
        if (hospital_page_column_exists('specialties', $column)) {
            $specialty_columns[] = $column;
        }
    }

    $select_parts = [];

    foreach (array_unique($specialty_columns) as $column) {
        $select_parts[] = 's.`' . $column . '`';
    }

    $conditions = ['c.hospital_id = :hospital_id'];

    if (hospital_page_column_exists('chambers', 'status')) {
        $conditions[] = "c.status = 'active'";
    }

    if (hospital_page_column_exists('doctors', 'status')) {
        $conditions[] = "d.status = 'active'";
    }

    if (hospital_page_column_exists('specialties', 'status')) {
        $conditions[] = "s.status = 'active'";
    }

    try {
        $sql = "SELECT DISTINCT " . implode(', ', $select_parts) . "
                FROM chambers c
                INNER JOIN doctors d ON d.id = c.doctor_id
                INNER JOIN specialties s ON s.id = d.specialty_id
                WHERE " . implode(' AND ', $conditions) . "
                ORDER BY s.`name` ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([':hospital_id' => $hospital_id]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        error_log('Hospital departments query failed: ' . $e->getMessage());
        return [];
    }
}

/*
|--------------------------------------------------------------------------
| Get Auto Doctors From Chambers
|--------------------------------------------------------------------------
*/
function get_hospital_page_doctors(int $hospital_id): array
{
    global $pdo;

    if ($hospital_id <= 0) {
        return [];
    }

    if (!hospital_page_table_exists('chambers') || !hospital_page_table_exists('doctors')) {
        return [];
    }

    $conditions = ['c.hospital_id = :hospital_id'];

    if (hospital_page_column_exists('chambers', 'status')) {
        $conditions[] = "c.status = 'active'";
    }

    if (hospital_page_column_exists('doctors', 'status')) {
        $conditions[] = "d.status = 'active'";
    }

    $specialty_join = hospital_page_table_exists('specialties')
        ? 'LEFT JOIN specialties s ON s.id = d.specialty_id'
        : '';

    $specialty_fields = hospital_page_table_exists('specialties')
        ? ', s.name AS specialty_name, s.slug AS specialty_slug'
        : ", '' AS specialty_name, '' AS specialty_slug";

    $hospital_join = hospital_page_table_exists('hospitals')
        ? 'LEFT JOIN hospitals h ON h.id = c.hospital_id'
        : '';

    $hospital_field = hospital_page_table_exists('hospitals')
        ? ', h.name AS hospital_name'
        : ", '' AS hospital_name";

    try {
        $sql = "SELECT DISTINCT d.*{$specialty_fields}{$hospital_field}
                FROM chambers c
                INNER JOIN doctors d ON d.id = c.doctor_id
                {$specialty_join}
                {$hospital_join}
                WHERE " . implode(' AND ', $conditions) . "
                ORDER BY d.id DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([':hospital_id' => $hospital_id]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        error_log('Hospital doctors query failed: ' . $e->getMessage());
        return [];
    }
}

/*
|--------------------------------------------------------------------------
| Get Auto Rating From Reviews
|--------------------------------------------------------------------------
*/
function get_hospital_page_rating(int $hospital_id): float
{
    global $pdo;

    if (
        $hospital_id <= 0
        || !hospital_page_table_exists('reviews')
        || !hospital_page_column_exists('reviews', 'hospital_id')
        || !hospital_page_column_exists('reviews', 'rating')
    ) {
        return 0.0;
    }

    $status_condition = hospital_page_column_exists('reviews', 'status')
        ? " AND status = 'approved'"
        : '';

    try {
        $stmt = $pdo->prepare(
            "SELECT AVG(rating) FROM reviews WHERE hospital_id = :hospital_id{$status_condition}"
        );
        $stmt->execute([':hospital_id' => $hospital_id]);
        $rating = $stmt->fetchColumn();

        return ($rating === null || $rating === false) ? 0.0 : round((float)$rating, 1);
    } catch (Throwable $e) {
        error_log('Hospital rating query failed: ' . $e->getMessage());
        return 0.0;
    }
}

/*
|--------------------------------------------------------------------------
| Get Review Count
|--------------------------------------------------------------------------
*/
function get_hospital_page_review_count(int $hospital_id): int
{
    global $pdo;

    if (
        $hospital_id <= 0
        || !hospital_page_table_exists('reviews')
        || !hospital_page_column_exists('reviews', 'hospital_id')
    ) {
        return 0;
    }

    $status_condition = hospital_page_column_exists('reviews', 'status')
        ? " AND status = 'approved'"
        : '';

    try {
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM reviews WHERE hospital_id = :hospital_id{$status_condition}"
        );
        $stmt->execute([':hospital_id' => $hospital_id]);

        return (int)$stmt->fetchColumn();
    } catch (Throwable $e) {
        error_log('Hospital review count query failed: ' . $e->getMessage());
        return 0;
    }
}

/*
|--------------------------------------------------------------------------
| Sync Hospital Stats
|--------------------------------------------------------------------------
*/
function sync_hospital_page_stats(int $hospital_id): array
{
    /*
     * Kept only for backwards compatibility. This page deliberately does not
     * write to the database during a public visit.
     */
    return [
        'departments_count' => 0,
        'doctors_count' => 0,
        'rating' => 0.0,
    ];
}

/*
 * Calculate presentation values once. Do not re-query or update the hospitals
 * table on a public request. Admin save/import actions can persist these
 * values separately when needed.
 */
$hospital_departments = get_hospital_page_departments($hospital_id);
$hospital_doctors = get_hospital_page_doctors($hospital_id);
$hospital_rating = get_hospital_page_rating($hospital_id);
$hospital_review_count = get_hospital_page_review_count($hospital_id);

$hospital_stats = [
    'departments_count' => count($hospital_departments),
    'doctors_count' => count($hospital_doctors),
    'rating' => $hospital_rating,
];

$hospital['departments_count'] = $hospital_stats['departments_count'];
$hospital['doctors_count'] = $hospital_stats['doctors_count'];
$hospital['rating'] = $hospital_stats['rating'];

$hospital_page_current_lang = hospital_page_lang();

$hospital_name_en = trim((string)($hospital['name'] ?? ''));
$hospital_name = hospital_page_localized_value($hospital, 'name', __t('hospital_profile_default_name', 'Hospital'));
$hospital_type_en = trim((string)hospital_value($hospital, 'type', ''));
$hospital_type = hospital_page_localized_value($hospital, 'type', __t('hospital_profile_default_type', 'Hospital'));
$hospital_description = hospital_page_localized_value($hospital, 'description');
$hospital_address = hospital_page_localized_value($hospital, 'address');
$opening_hours = hospital_page_localized_value($hospital, 'opening_hours');
$visiting_hours = hospital_page_localized_value($hospital, 'visiting_hours');

$hospital_seo_title = hospital_page_localized_value($hospital, 'seo_title');
$hospital_seo_description = hospital_page_localized_value($hospital, 'seo_description');

$page_title = $hospital_seo_title !== ''
    ? $hospital_seo_title
    : trim($hospital_name . ' | ' . hospital_page_site_name());

$meta_description = $hospital_seo_description !== ''
    ? $hospital_seo_description
    : hospital_page_summary(
        (string)($hospital_description ?: sprintf(
            __t('hospital_profile_default_meta_description', '%s profile, doctors, departments, location and contact information on %s.'),
            $hospital_name,
            hospital_page_site_name()
        ))
    );

$default_hospital_image = hospital_page_site_setting('default_hospital_image', 'assets/images/default-hospital.webp');

$cover_image = !empty($hospital['cover_image'])
    ? hospital_page_asset_url((string)$hospital['cover_image'], $default_hospital_image)
    : hospital_page_asset_url((string)($hospital['image'] ?? ''), $default_hospital_image);

$hospital_phone = trim((string)hospital_value($hospital, 'phone'));
$hospital_email = trim((string)hospital_value($hospital, 'email'));
$breadcrumb_district_name = '';
$breadcrumb_district_name_en = '';
$breadcrumb_thana_name = '';
$breadcrumb_thana_name_en = '';
$breadcrumb_district_url = '';
$breadcrumb_thana_url = '';
$breadcrumb_type_url = '';

if (!empty($hospital['district_id'])) {
    $breadcrumb_district_name_en = hospital_page_address_name_by_id('districts', (int)$hospital['district_id'], 'en');
    $breadcrumb_district_name = hospital_page_address_name_by_id('districts', (int)$hospital['district_id'], $hospital_page_current_lang);
}

if ($breadcrumb_district_name_en === '') {
    $breadcrumb_district_name_en = trim((string)hospital_value($hospital, 'city'));
}

if ($breadcrumb_district_name === '') {
    $breadcrumb_district_name = $breadcrumb_district_name_en;
}

if (!empty($hospital['thana_id'])) {
    $breadcrumb_thana_name_en = hospital_page_address_name_by_id('thanas', (int)$hospital['thana_id'], 'en');
    $breadcrumb_thana_name = hospital_page_address_name_by_id('thanas', (int)$hospital['thana_id'], $hospital_page_current_lang);
}

if ($breadcrumb_thana_name === '') {
    $breadcrumb_thana_name = $breadcrumb_thana_name_en;
}

if ($breadcrumb_district_name_en !== '') {
    $breadcrumb_district_url = hospital_page_hospitals_url([
        'district' => $breadcrumb_district_name_en,
    ]);
}

if ($breadcrumb_district_name_en !== '' && $breadcrumb_thana_name_en !== '') {
    $breadcrumb_thana_url = hospital_page_hospitals_url([
        'district' => $breadcrumb_district_name_en,
        'thana' => $breadcrumb_thana_name_en,
    ]);
}

if ($hospital_type_en !== '') {
    $breadcrumb_type_url = hospital_page_type_url(
        $hospital_type_en,
        $breadcrumb_district_name_en,
        $breadcrumb_thana_name_en
    );
}

$hospital_map_url = trim((string)hospital_value($hospital, 'map_url'));
$hospital_website = trim((string)hospital_value($hospital, 'website_url'));
$hospital_whatsapp = trim((string)hospital_value($hospital, 'whatsapp'));
$emergency_phone = trim((string)hospital_value($hospital, 'emergency_phone'));
$ambulance_phone = trim((string)hospital_value($hospital, 'ambulance_phone'));
$appointment_call_number = $emergency_phone ?: ($hospital_phone ?: $ambulance_phone);
$appointment_note = hospital_page_localized_value($hospital, 'appointment_note');
$video_url = trim((string)hospital_value($hospital, 'video_url'));
$bed_count = (int)hospital_value($hospital, 'bed_count', 0);
$established_year = hospital_value($hospital, 'established_year', '');

$services = hospital_text_list(hospital_page_localized_value($hospital, 'services'));
$facilities = hospital_text_list(hospital_page_localized_value($hospital, 'facilities'));

$availability_flags = [
    __t('hospital_profile_facility_emergency', 'Emergency') => (int)hospital_value($hospital, 'emergency_available', 0),
    __t('hospital_profile_facility_ambulance', 'Ambulance') => (int)hospital_value($hospital, 'ambulance_available', 0),
    'ICU' => (int)hospital_value($hospital, 'icu_available', 0),
    'CCU' => (int)hospital_value($hospital, 'ccu_available', 0),
    'NICU' => (int)hospital_value($hospital, 'nicu_available', 0),
    'PICU' => (int)hospital_value($hospital, 'picu_available', 0),
    __t('hospital_profile_facility_operation_theater', 'Operation Theater') => (int)hospital_value($hospital, 'operation_theater_available', 0),
    __t('hospital_profile_facility_diagnostic', 'Diagnostic') => (int)hospital_value($hospital, 'diagnostic_available', 0),
    __t('hospital_profile_facility_pharmacy', 'Pharmacy') => (int)hospital_value($hospital, 'pharmacy_available', 0),
    __t('hospital_profile_facility_blood_bank', 'Blood Bank') => (int)hospital_value($hospital, 'blood_bank_available', 0),
    __t('hospital_profile_facility_parking', 'Parking') => (int)hospital_value($hospital, 'parking_available', 0),
    __t('hospital_profile_facility_cafeteria', 'Cafeteria') => (int)hospital_value($hospital, 'cafeteria_available', 0),
    __t('hospital_profile_facility_wheelchair_access', 'Wheelchair Access') => (int)hospital_value($hospital, 'wheelchair_available', 0),
    __t('hospital_profile_facility_oxygen_support', 'Oxygen Support') => (int)hospital_value($hospital, 'oxygen_available', 0),
    __t('hospital_profile_facility_dialysis', 'Dialysis') => (int)hospital_value($hospital, 'dialysis_available', 0),
    'MRI' => (int)hospital_value($hospital, 'mri_available', 0),
    __t('hospital_profile_facility_ct_scan', 'CT Scan') => (int)hospital_value($hospital, 'ct_scan_available', 0),
    __t('hospital_profile_facility_xray', 'X-Ray') => (int)hospital_value($hospital, 'xray_available', 0),
    __t('hospital_profile_facility_lab', 'Lab') => (int)hospital_value($hospital, 'lab_available', 0),
    __t('hospital_profile_facility_dental_unit', 'Dental Unit') => (int)hospital_value($hospital, 'dental_unit_available', 0),
    __t('hospital_profile_facility_eye_unit', 'Eye Unit') => (int)hospital_value($hospital, 'eye_unit_available', 0),
    __t('hospital_profile_facility_mother_child_unit', 'Mother & Child Unit') => (int)hospital_value($hospital, 'mother_child_unit_available', 0),
    __t('hospital_profile_facility_cancer_unit', 'Cancer Unit') => (int)hospital_value($hospital, 'cancer_unit_available', 0),
    __t('hospital_profile_facility_cardiac_unit', 'Cardiac Unit') => (int)hospital_value($hospital, 'cardiac_unit_available', 0),
    __t('hospital_profile_facility_rehabilitation_unit', 'Rehabilitation Unit') => (int)hospital_value($hospital, 'rehab_unit_available', 0),
];

$available_items = array_keys(array_filter($availability_flags));

$schema_data = [
    '@context' => 'https://schema.org',
    '@type' => 'Hospital',
    'name' => $hospital_name,
    'description' => $meta_description,
    'url' => front_url('hospital/' . ($hospital['slug'] ?? '')),
    'telephone' => $hospital_phone ?: $emergency_phone,
    'email' => $hospital_email,
    'address' => [
        '@type' => 'PostalAddress',
        'streetAddress' => $hospital_address,
        'addressLocality' => $breadcrumb_district_name ?: hospital_value($hospital, 'city'),
        'addressCountry' => 'BD',
    ],
];

if (!empty($cover_image)) {
    $schema_data['image'] = $cover_image;
}

if ((float)($hospital['rating'] ?? 0) > 0 && $hospital_review_count > 0) {
    $schema_data['aggregateRating'] = [
        '@type' => 'AggregateRating',
        'ratingValue' => (float)$hospital['rating'],
        'reviewCount' => $hospital_review_count,
        'bestRating' => 5,
        'worstRating' => 1,
    ];
}

$hospital_page_primary_color = hospital_page_setting_color('primary_color', '#0969da');
$hospital_page_accent_color = hospital_page_setting_color('accent_color', '#2da44e');
$hospital_page_body_background = hospital_page_setting_color('body_background_color', '#f6f8fa');
$hospital_page_og_image = !empty(hospital_value($hospital, 'og_image'))
    ? hospital_page_asset_url((string)hospital_value($hospital, 'og_image'))
    : hospital_page_setting_url('default_og_image', 'assets/images/default-og-image.webp');

if ($hospital_page_og_image === '' && $cover_image !== '') {
    $hospital_page_og_image = $cover_image;
}

$canonical_url = front_url('hospital/' . ($hospital['slug'] ?? $slug));
$og_title = $page_title;
$og_description = $meta_description;
$og_type = 'website';
$og_image = $hospital_page_og_image;
$og_locale = $hospital_page_current_lang === 'bn' ? 'bn_BD' : 'en_US';

include __DIR__ . '/includes/header.php';
?>
<style>
  :root {
    --hospital-primary: <?= e($hospital_page_primary_color) ?>;
    --hospital-accent: <?= e($hospital_page_accent_color) ?>;
    --hospital-bg: <?= e($hospital_page_body_background) ?>;
  }

  /*
   * Keep all typography inside the hospital profile at a consistent medium weight.
   * The !important flag protects this page from stronger global theme rules.
   */
  .medic-hospital-page,
  .medic-hospital-page * {
    font-weight: 500 !important;
  }

  .hospital-hidden-tag {
    display: none !important;
  }

  .hospital-more-tag {
    font: inherit;
    cursor: pointer;
    appearance: none;
    -webkit-appearance: none;
  }

  .hospital-more-tag:focus {
    outline: none;
  }

  .medic-hospital-page {
    background:
      radial-gradient(circle at top left, rgba(9, 105, 218, 0.10), transparent 32%),
      radial-gradient(circle at top right, rgba(45, 164, 78, 0.10), transparent 34%),
      var(--hospital-bg);
    padding: 28px 0 56px;
  }

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
    color: var(--hospital-primary);
    text-decoration: none;
    font-weight: 500;
  }

  .medic-breadcrumb a:hover {
    text-decoration: underline;
  }

  .medic-breadcrumb span {
    color: #8c959f;
  }

  .medic-hospital-hero {
    overflow: hidden;
    background: #ffffff;
    border: 1px solid #d0d7de;
    border-radius: 14px;
    margin-bottom: 20px;
    box-shadow: 0 1px 0 rgba(27, 31, 36, 0.04);
  }

  .medic-hospital-cover {
    position: relative;
    min-height: 270px;
    background-size: cover;
    background-position: center;
    display: flex;
    align-items: flex-end;
    padding: 22px;
  }

  .medic-cover-badge {
    display: inline-flex;
    align-items: center;
    min-height: 34px;
    padding: 0 12px;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.92);
    border: 1px solid rgba(255, 255, 255, 0.55);
    color: #24292f;
    font-size: 13px;
    font-weight: 500;
    box-shadow: 0 8px 24px rgba(27, 31, 36, 0.20);
  }

  .medic-single-hospital-main {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 22px;
    align-items: start;
    padding: 24px;
  }

  .medic-title-line {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
  }

  .medic-title-line h1 {
    margin: 0;
    color: #24292f;
    font-size: clamp(30px, 4vw, 46px);
    line-height: 1.1;
    letter-spacing: -0.04em;
    font-weight: 500;
  }

  .medic-hospital-description {
    max-width: 790px;
    margin: 12px 0 0;
    color: #57606a;
    font-size: 16px;
    line-height: 1.7;
  }

  .medic-tag-row {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 16px;
  }

  .medic-tag,
  .hospital-more-tag {
    display: inline-flex;
    align-items: center;
    min-height: 30px;
    padding: 0 10px;
    border-radius: 999px;
    background: #ddf4ff;
    border: 1px solid rgba(9, 105, 218, 0.20);
    color: var(--hospital-primary);
    font-size: 13px;
    font-weight: 500;
    text-decoration: none;
  }

  .hospital-more-tag {
    min-height: 30px;
  }

  .medic-tag:hover,
  .hospital-more-tag:hover {
    background: #b6e3ff;
    color: var(--hospital-primary);
  }

  .medic-hero-actions {
    display: flex;
    flex-wrap: wrap;
    justify-content: flex-end;
    gap: 10px;
  }

  .medic-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 42px;
    padding: 0 18px;
    border-radius: 6px;
    border: 1px solid rgba(27, 31, 36, 0.15);
    background: var(--hospital-accent);
    color: #ffffff;
    font-size: 14px;
    font-weight: 500;
    text-decoration: none;
    cursor: pointer;
    transition: 0.2s ease;
    white-space: nowrap;
  }

  .medic-btn:hover {
    background: var(--hospital-accent);
    color: #ffffff;
  }

  .medic-btn-outline {
    background: #ffffff;
    color: var(--hospital-primary);
    border-color: #d0d7de;
  }

  .medic-btn-outline:hover {
    background: var(--hospital-bg);
    color: var(--hospital-primary);
  }

  .medic-hospital-stats {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    border-top: 1px solid #d0d7de;
    background: var(--hospital-bg);
  }

  .medic-stat-item {
    padding: 18px;
    border-right: 1px solid #d0d7de;
  }

  .medic-stat-item:last-child {
    border-right: 0;
  }

  .medic-stat-item h3 {
    margin: 0 0 5px;
    color: #24292f;
    font-size: 24px;
    letter-spacing: -0.03em;
  }

  .medic-stat-item p {
    margin: 0;
    color: #57606a;
    font-size: 13px;
    font-weight: 500;
  }

  .medic-content-grid {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 320px;
    gap: 24px;
    align-items: start;
  }

  .medic-main-column {
    display: grid;
    gap: 18px;
    min-width: 0;
  }

  .medic-sidebar {
    position: sticky;
    top: 88px;
    display: grid;
    gap: 16px;
  }

  .medic-card {
    background: #ffffff;
    border: 1px solid #d0d7de;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 1px 0 rgba(27, 31, 36, 0.04);
  }

  .medic-card h2 {
    margin: 0 0 12px;
    color: #24292f;
    font-size: 22px;
    letter-spacing: -0.03em;
    font-weight: 500;
  }

  .medic-card h3 {
    margin: 18px 0 10px;
    color: #24292f;
    font-size: 17px;
    letter-spacing: -0.02em;
    font-weight: 500;
  }

  .medic-card p {
    color: #57606a;
    line-height: 1.7;
  }

  .medic-info-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
    margin-top: 18px;
  }

  .medic-info-box {
    padding: 14px;
    background: var(--hospital-bg);
    border: 1px solid #d0d7de;
    border-radius: 10px;
  }

  .medic-info-box span {
    display: block;
    color: #57606a;
    font-size: 12px;
    margin-bottom: 5px;
    font-weight: 500;
  }

  .medic-info-box strong {
    display: block;
    color: #24292f;
    font-size: 14px;
    line-height: 1.5;
  }

  .medic-hospital-doctor-list {
    display: grid;
    gap: 14px;
    margin-top: 16px;
  }

  .medic-appointment-card,
  .medic-contact-card {
    background: #ffffff;
    border: 1px solid #d0d7de;
    border-radius: 12px;
    padding: 18px;
    box-shadow: 0 1px 0 rgba(27, 31, 36, 0.04);
  }

  .medic-appointment-card h2,
  .medic-contact-card h2 {
    margin: 0 0 14px;
    color: #24292f;
    font-size: 20px;
    letter-spacing: -0.03em;
    font-weight: 500;
  }

  .medic-emergency-box {
    background: var(--hospital-bg);
    border: 1px solid #d0d7de;
    border-radius: 10px;
    padding: 14px;
    margin-bottom: 14px;
  }

  .medic-emergency-box span {
    display: block;
    color: #57606a;
    font-size: 13px;
    margin-bottom: 4px;
  }

  .medic-emergency-box h3 {
    margin: 0;
    color: #24292f;
    font-size: 24px;
    line-height: 1.25;
  }

  .medic-emergency-box a {
    color: #24292f;
    text-decoration: none;
  }

  .medic-contact-card p {
    margin: 0 0 10px;
    color: #57606a;
    line-height: 1.55;
  }

  .medic-contact-card a {
    color: var(--hospital-primary);
    text-decoration: none;
    font-weight: 500;
  }

  .medic-contact-card a:hover {
    text-decoration: underline;
  }

  .medic-map-box {
    margin-top: 14px;
    min-height: 120px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 1px dashed #d0d7de;
    border-radius: 10px;
    background: var(--hospital-bg);
    color: #57606a;
    font-weight: 500;
  }

  @media (max-width: 980px) {
    .medic-content-grid {
      grid-template-columns: 1fr;
    }

    .medic-sidebar {
      position: static;
    }

    .medic-single-hospital-main {
      grid-template-columns: 1fr;
    }

    .medic-hero-actions {
      justify-content: flex-start;
    }

    .medic-hospital-stats {
      grid-template-columns: repeat(2, 1fr);
    }

    .medic-stat-item:nth-child(2) {
      border-right: 0;
    }

    .medic-stat-item:nth-child(1),
    .medic-stat-item:nth-child(2) {
      border-bottom: 1px solid #d0d7de;
    }
  }

  @media (max-width: 700px) {
    .medic-hospital-page {
      padding-top: 18px;
    }

    .medic-hospital-cover {
      min-height: 190px;
      padding: 16px;
    }

    .medic-single-hospital-main {
      padding: 18px;
    }

    .medic-hospital-stats,
    .medic-info-grid {
      grid-template-columns: 1fr;
    }

    .medic-stat-item {
      border-right: 0;
      border-bottom: 1px solid #d0d7de;
    }

    .medic-stat-item:last-child {
      border-bottom: 0;
    }

    .medic-hero-actions .medic-btn,
    .medic-appointment-card .medic-btn,
    .medic-contact-card .medic-btn {
      width: 100%;
    }
  }
</style>

<script type="application/ld+json">
<?= json_encode($schema_data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?>
</script>

<main class="medic-hospital-page">
  <div class="container">

    <nav class="medic-breadcrumb">
      <a href="<?= e(front_url()) ?>"><?= e(__t('home', 'Home')) ?></a>
      <span>/</span>
      <a href="<?= e(front_url('hospitals')) ?>"><?= e(__t('hospitals', 'Hospitals')) ?></a>

      <?php if ($breadcrumb_district_name): ?>
        <span>/</span>
        <?php if ($breadcrumb_district_url): ?>
          <a href="<?= e($breadcrumb_district_url) ?>"><?= e(hospital_page_localize_digits($breadcrumb_district_name)) ?></a>
        <?php else: ?>
          <span><?= e(hospital_page_localize_digits($breadcrumb_district_name)) ?></span>
        <?php endif; ?>
      <?php endif; ?>

      <?php if ($breadcrumb_thana_name): ?>
        <span>/</span>
        <?php if ($breadcrumb_thana_url): ?>
          <a href="<?= e($breadcrumb_thana_url) ?>"><?= e(hospital_page_localize_digits($breadcrumb_thana_name)) ?></a>
        <?php else: ?>
          <span><?= e(hospital_page_localize_digits($breadcrumb_thana_name)) ?></span>
        <?php endif; ?>
      <?php endif; ?>

      <?php if ($hospital_type): ?>
        <span>/</span>
        <?php if ($breadcrumb_type_url): ?>
          <a href="<?= e($breadcrumb_type_url) ?>"><?= e(hospital_page_localize_digits($hospital_type)) ?></a>
        <?php else: ?>
          <span><?= e(hospital_page_localize_digits($hospital_type)) ?></span>
        <?php endif; ?>
      <?php endif; ?>

      <span>/</span>
      <span><?= e(hospital_page_localize_digits($hospital_name)) ?></span>
    </nav>

    <section class="medic-hospital-hero">
      <div
        class="medic-hospital-cover"
        style="background-image:linear-gradient(180deg, rgba(15,23,42,.12), rgba(15,23,42,.48)), url('<?= e($cover_image) ?>');"
      >
        <?php if ((int)hospital_value($hospital, 'emergency_available', 0)): ?>
          <span class="medic-cover-badge"><?= e(hospital_page_localize_digits(__t('hospital_profile_open_24_7_emergency', 'Open 24/7 Emergency Service'))) ?></span>
        <?php elseif ($opening_hours): ?>
          <span class="medic-cover-badge"><?= e(hospital_page_localize_digits($opening_hours)) ?></span>
        <?php else: ?>
          <span class="medic-cover-badge"><?= e(hospital_page_localize_digits($hospital_type)) ?></span>
        <?php endif; ?>
      </div>

      <div class="medic-single-hospital-main">
        <div>
          <div class="medic-title-line">
            <h1><?= e(hospital_page_localize_digits($hospital_name)) ?></h1>
            <?= verified_badge((int)($hospital['is_verified'] ?? 0)) ?>
          </div>

          <?php if ($hospital_description !== ''): ?>
            <p class="medic-hospital-description"><?= e(hospital_page_localize_digits($hospital_description)) ?></p>
          <?php endif; ?>

          <div class="medic-tag-row">
            <span class="medic-tag"><?= e(hospital_page_localize_digits($hospital_type ?: __t('hospital_profile_default_type', 'Hospital'))) ?></span>

            <?php if ((int)hospital_value($hospital, 'emergency_available', 0)): ?>
              <span class="medic-tag"><?= e(hospital_page_localize_digits(__t('hospital_profile_emergency_24_7', 'Emergency 24/7'))) ?></span>
            <?php endif; ?>

            <?php if ((int)hospital_value($hospital, 'ambulance_available', 0)): ?>
              <span class="medic-tag"><?= e(__t('hospital_profile_facility_ambulance', 'Ambulance')) ?></span>
            <?php endif; ?>

            <?php if (!empty($hospital_departments)): ?>
              <?php foreach (array_slice($hospital_departments, 0, 3) as $department): ?>
                <span class="medic-tag"><?= e(hospital_page_localize_digits(lang_text((string)($department['name'] ?? ''), (string)($department['name_bn'] ?? '')))) ?></span>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>

        <div class="medic-hero-actions">
          <?php if ($appointment_call_number): ?>
            <a href="tel:<?= e($appointment_call_number) ?>" class="medic-btn">
              <?= e(__t('book_appointment', 'Book Appointment')) ?>
            </a>
          <?php endif; ?>

          <?php if ($hospital_phone): ?>
            <a href="tel:<?= e($hospital_phone) ?>" class="medic-btn medic-btn-outline"><?= e(__t('hospital_profile_call_hospital', 'Call Hospital')) ?></a>
          <?php endif; ?>

          <?php if ($hospital_whatsapp): ?>
            <a href="https://wa.me/<?= e(preg_replace('/[^0-9]/', '', $hospital_whatsapp)) ?>" target="_blank" rel="noopener" class="medic-btn medic-btn-outline">
              <?= e(__t('hospital_profile_whatsapp', 'WhatsApp')) ?>
            </a>
          <?php endif; ?>
        </div>
      </div>

      <div class="medic-hospital-stats">
        <div class="medic-stat-item">
          <h3><?= e(hospital_page_localize_digits((string)((int)($hospital['departments_count'] ?? 0)))) ?>+</h3>
          <p><?= e(__t('hospital_profile_departments', 'Departments')) ?></p>
        </div>

        <div class="medic-stat-item">
          <h3><?= e(hospital_page_localize_digits((string)((int)($hospital['doctors_count'] ?? 0)))) ?>+</h3>
          <p><?= e(__t('doctors', 'Doctors')) ?></p>
        </div>

        <div class="medic-stat-item">
          <h3><?= $bed_count > 0 ? e(hospital_page_localize_digits((string)$bed_count)) . '+' : e(hospital_page_localize_digits('24/7')) ?></h3>
          <p><?= e($bed_count > 0 ? __t('hospital_profile_beds', 'Beds') : __t('hospital_profile_support', 'Support')) ?></p>
        </div>

        <div class="medic-stat-item">
          <h3><?= e(hospital_page_localize_digits((string)($hospital['rating'] ?? '0'))) ?></h3>
          <p><?= e(__t('hospital_profile_rating', 'Rating')) ?></p>
        </div>
      </div>
    </section>

    <div class="medic-content-grid">
      <div class="medic-main-column">

        <section class="medic-card">
          <h2><?= e(__t('hospital_profile_about', 'About Hospital')) ?></h2>

          <?php if ($hospital_description !== ''): ?>
            <p><?= e(hospital_page_localize_digits($hospital_description)) ?></p>
          <?php else: ?>
            <p><?= e(__t('hospital_profile_no_description', 'No description has been added for this hospital yet.')) ?></p>
          <?php endif; ?>

          <div class="medic-info-grid">
            <div class="medic-info-box">
              <span><?= e(__t('hospital_profile_type', 'Hospital Type')) ?></span>
              <strong><?= e(hospital_page_localize_digits($hospital_type ?: __t('hospital_profile_default_type', 'Hospital'))) ?></strong>
            </div>

            <div class="medic-info-box">
              <span><?= e(__t('hospital_profile_location', 'Location')) ?></span>
              <strong><?= e(hospital_page_localize_digits($hospital_address ?: __t('hospital_profile_not_available', 'Not available'))) ?></strong>
            </div>

            <?php if ($established_year): ?>
              <div class="medic-info-box">
                <span><?= e(__t('hospital_profile_established', 'Established')) ?></span>
                <strong><?= e(hospital_page_localize_digits((string)$established_year)) ?></strong>
              </div>
            <?php endif; ?>

            <?php if ($opening_hours): ?>
              <div class="medic-info-box">
                <span><?= e(__t('hospital_profile_opening_hours', 'Opening Hours')) ?></span>
                <strong><?= e(hospital_page_localize_digits($opening_hours)) ?></strong>
              </div>
            <?php endif; ?>

            <?php if ($visiting_hours): ?>
              <div class="medic-info-box">
                <span><?= e(__t('hospital_profile_visiting_hours', 'Visiting Hours')) ?></span>
                <strong><?= e(hospital_page_localize_digits($visiting_hours)) ?></strong>
              </div>
            <?php endif; ?>

            <div class="medic-info-box">
              <span><?= e(__t('appointments', 'Appointment')) ?></span>
              <strong><?= e(__t('hospital_profile_online_phone_booking', 'Online & Phone Booking')) ?></strong>
            </div>
          </div>
        </section>

        <?php if (!empty($available_items)): ?>
          <section class="medic-card">
            <h2><?= e(__t('hospital_profile_available_facilities', 'Available Facilities')) ?></h2>
            <p><?= e(__t('hospital_profile_facilities_note', 'These facilities are listed by the hospital admin.')) ?></p>

            <div class="medic-tag-row hospital-limited-tags">
              <?php render_hospital_limited_tags($available_items, 10); ?>
            </div>
          </section>
        <?php endif; ?>

        <?php if (!empty($services) || !empty($facilities)): ?>
          <section class="medic-card">
            <h2><?= e(__t('hospital_profile_services_facilities', 'Services & Facilities')) ?></h2>

            <?php if (!empty($services)): ?>
              <h3><?= e(__t('hospital_profile_services', 'Services')) ?></h3>
              <div class="medic-tag-row hospital-limited-tags">
                <?php render_hospital_limited_tags($services, 10); ?>
              </div>
            <?php endif; ?>

            <?php if (!empty($facilities)): ?>
              <h3><?= e(__t('hospital_profile_facilities', 'Facilities')) ?></h3>
              <div class="medic-tag-row hospital-limited-tags">
                <?php render_hospital_limited_tags($facilities, 10); ?>
              </div>
            <?php endif; ?>
          </section>
        <?php endif; ?>

        <section class="medic-card">
          <h2><?= e(__t('hospital_profile_departments', 'Departments')) ?></h2>
          <p><?= e(__t('hospital_profile_departments_note', 'Departments are generated from doctors who have active chambers in this hospital.')) ?></p>

          <?php if (!empty($hospital_departments)): ?>
            <div class="medic-tag-row">
              <?php foreach ($hospital_departments as $department): ?>
                <a class="medic-tag" href="<?= e(front_url('doctors?specialty=' . ($department['slug'] ?? ''))) ?>">
                  <?= e(hospital_page_localize_digits(lang_text((string)($department['name'] ?? ''), (string)($department['name_bn'] ?? '')))) ?>
                </a>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <p><?= e(__t('hospital_profile_no_departments', 'No department found yet. Add doctors with chambers under this hospital.')) ?></p>
          <?php endif; ?>
        </section>

        <section class="medic-card">
          <h2><?= e(__t('hospital_profile_hospital_doctors', 'Hospital Doctors')) ?></h2>
          <p><?= e(__t('hospital_profile_doctors_note', 'Doctors are automatically listed from active chamber records for this hospital.')) ?></p>

          <div class="medic-hospital-doctor-list">
            <?php if (!empty($hospital_doctors)): ?>
              <?php foreach ($hospital_doctors as $doctor): ?>
                <?php include __DIR__ . '/includes/doctor-card.php'; ?>
              <?php endforeach; ?>
            <?php else: ?>
              <p><?= e(__t('hospital_profile_no_doctors', 'No doctor listed for this hospital yet.')) ?></p>
            <?php endif; ?>
          </div>
        </section>

        <?php if ($video_url): ?>
          <section class="medic-card">
            <h2><?= e(__t('hospital_profile_video', 'Hospital Video')) ?></h2>
            <p>
              <a href="<?= e($video_url) ?>" target="_blank" rel="noopener" class="medic-btn medic-btn-outline">
                <?= e(__t('hospital_profile_watch_video', 'Watch Video')) ?>
              </a>
            </p>
          </section>
        <?php endif; ?>

        <section class="medic-card">
          <h2><?= e(__t('hospital_profile_rating_reviews', 'Rating & Reviews')) ?></h2>

          <div class="medic-info-grid">
            <div class="medic-info-box">
              <span><?= e(__t('hospital_profile_average_rating', 'Average Rating')) ?></span>
              <strong><?= e(hospital_page_localize_digits((string)($hospital['rating'] ?? '0') . ' / 5')) ?></strong>
            </div>

            <div class="medic-info-box">
              <span><?= e(__t('hospital_profile_total_reviews', 'Total Reviews')) ?></span>
              <strong><?= e(hospital_page_localize_digits((string)$hospital_review_count)) ?></strong>
            </div>
          </div>

          <?php if ($hospital_review_count <= 0): ?>
            <p><?= e(__t('hospital_profile_no_reviews', 'No approved review has been added for this hospital yet.')) ?></p>
          <?php endif; ?>
        </section>

      </div>

      <aside class="medic-sidebar">

        <section class="medic-appointment-card">
          <h2><?= e(__t('hospital_profile_request_appointment', 'Request Appointment')) ?></h2>

          <?php if ($appointment_call_number): ?>
            <div class="medic-emergency-box">
              <span><?= e(__t('hospital_profile_emergency_hotline', 'Emergency Hotline')) ?></span>
              <h3>
                <a href="tel:<?= e($appointment_call_number) ?>">
                  <?= e(hospital_page_localize_digits($appointment_call_number)) ?>
                </a>
              </h3>
            </div>

            <a href="tel:<?= e($appointment_call_number) ?>" class="medic-btn" style="width:100%;">
              <?= e(__t('hospital_profile_call_for_appointment', 'Call for Appointment')) ?>
            </a>
          <?php else: ?>
            <p><?= e(__t('hospital_profile_no_appointment_phone', 'No appointment phone number has been added yet.')) ?></p>
          <?php endif; ?>

          <?php if ($appointment_note): ?>
            <p><?= e(hospital_page_localize_digits($appointment_note)) ?></p>
          <?php endif; ?>
        </section>

        <section class="medic-contact-card">
          <h2><?= e(__t('hospital_profile_contact', 'Hospital Contact')) ?></h2>

          <p><?= e(hospital_page_localize_digits($hospital_name)) ?></p>

          <?php if ($hospital_address): ?>
            <p><?= e($hospital_address) ?></p>
          <?php endif; ?>

          <?php if ($hospital_phone): ?>
            <p><?= e(__t('hospital_profile_phone', 'Phone')) ?>: <a href="tel:<?= e($hospital_phone) ?>"><?= e(hospital_page_localize_digits($hospital_phone)) ?></a></p>
          <?php endif; ?>

          <?php if ($emergency_phone): ?>
            <p><?= e(__t('hospital_profile_facility_emergency', 'Emergency')) ?>: <a href="tel:<?= e($emergency_phone) ?>"><?= e(hospital_page_localize_digits($emergency_phone)) ?></a></p>
          <?php endif; ?>

          <?php if ($ambulance_phone): ?>
            <p><?= e(__t('hospital_profile_facility_ambulance', 'Ambulance')) ?>: <a href="tel:<?= e($ambulance_phone) ?>"><?= e(hospital_page_localize_digits($ambulance_phone)) ?></a></p>
          <?php endif; ?>

          <?php if ($hospital_whatsapp): ?>
            <p><?= e(__t('hospital_profile_whatsapp', 'WhatsApp')) ?>: <a href="https://wa.me/<?= e(preg_replace('/[^0-9]/', '', $hospital_whatsapp)) ?>" target="_blank" rel="noopener"><?= e(hospital_page_localize_digits($hospital_whatsapp)) ?></a></p>
          <?php endif; ?>

          <?php if ($hospital_email): ?>
            <p><?= e(__t('hospital_profile_email', 'Email')) ?>: <a href="mailto:<?= e($hospital_email) ?>"><?= e($hospital_email) ?></a></p>
          <?php endif; ?>

          <?php if ($hospital_website): ?>
            <p><?= e(__t('hospital_profile_website', 'Website')) ?>: <a href="<?= e($hospital_website) ?>" target="_blank" rel="noopener"><?= e(__t('hospital_profile_visit_website', 'Visit Website')) ?></a></p>
          <?php endif; ?>

          <?php if ($hospital_map_url): ?>
            <a href="<?= e($hospital_map_url) ?>" target="_blank" rel="noopener" class="medic-btn medic-btn-outline" style="width:100%;">
              <?= e(__t('hospital_profile_view_google_map', 'View Google Map')) ?>
            </a>
          <?php else: ?>
            <div class="medic-map-box"><?= e(__t('hospital_profile_map_area', 'Map Area')) ?></div>
          <?php endif; ?>
        </section>

      </aside>
    </div>
  </div>
</main>

<script>
  document.addEventListener('click', function(event) {
    const button = event.target.closest('[data-show-more]');

    if (!button) {
      return;
    }

    event.preventDefault();

    const wrapper = button.closest('.hospital-limited-tags');

    if (!wrapper) {
      return;
    }

    const hiddenTags = wrapper.querySelectorAll('.hospital-hidden-tag');

    hiddenTags.forEach(function(tag) {
      tag.classList.remove('hospital-hidden-tag');
    });

    button.remove();
  });
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
