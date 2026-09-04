<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/address-functions.php';

$functions_file_version = 'routing-security-php7-compat-20260629';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/*
|--------------------------------------------------------------------------
| PHP Compatibility Helpers
|--------------------------------------------------------------------------
*/
if (!function_exists('str_starts_with')) {
    function str_starts_with(string $haystack, string $needle): bool
    {
        return $needle === '' || strpos($haystack, $needle) === 0;
    }
}

if (!function_exists('str_ends_with')) {
    function str_ends_with(string $haystack, string $needle): bool
    {
        if ($needle === '') {
            return true;
        }

        return substr($haystack, -strlen($needle)) === $needle;
    }
}


if (!function_exists('str_contains')) {
    function str_contains(string $haystack, string $needle): bool
    {
        return $needle === '' || strpos($haystack, $needle) !== false;
    }
}

if (!function_exists('mb_strimwidth')) {
    function mb_strimwidth(
        string $string,
        int $start,
        int $width,
        string $trimMarker = '',
        string $encoding = 'UTF-8'
    ): string {
        $slice = substr($string, $start, $width);

        if (strlen($string) > ($start + $width)) {
            $slice .= $trimMarker;
        }

        return $slice;
    }
}


/*
|--------------------------------------------------------------------------
| Escape Output
|--------------------------------------------------------------------------
*/
function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/*
|--------------------------------------------------------------------------
| Site URL Helper
|--------------------------------------------------------------------------
*/
function site_url(string $path = ''): string
{
    $base = defined('APP_URL') ? rtrim(APP_URL, '/') : '';

    if ($base === '') {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $base = $scheme . '://' . $host;
    }

    $path = ltrim($path, '/');

    return $path !== '' ? $base . '/' . $path : $base;
}

/*
|--------------------------------------------------------------------------
| Current Route
|--------------------------------------------------------------------------
*/
function current_route(): string
{
    $route = trim((string)($_GET['route'] ?? ''), '/');

    if ($route !== '') {
        return $route;
    }

    $uri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
    $uri = trim((string)$uri, '/');

    if ($uri === '') {
        return '';
    }

    $app_path = '';

    if (defined('APP_URL')) {
        $parsed_app_path = parse_url(APP_URL, PHP_URL_PATH);
        $app_path = trim((string)($parsed_app_path ?? ''), '/');
    }

    if ($app_path !== '' && str_starts_with($uri, $app_path)) {
        $uri = trim(substr($uri, strlen($app_path)), '/');
    }

    return $uri;
}

/*
|--------------------------------------------------------------------------
| Current Page For Active Menu
|--------------------------------------------------------------------------
*/
function current_page(): string
{
    $route = current_route();

    if ($route !== '') {
        $first_segment = (string) strtok($route, '/');

        $page_map = [
            'doctor' => 'doctors.php',
            'hospital' => 'hospitals.php',
            'doctors' => 'doctors.php',
            'hospitals' => 'hospitals.php',
            'specialties' => 'specialties.php',
            'specialty' => 'specialties.php',
            'contact' => 'contact.php',
        ];

        return $page_map[$first_segment] ?? ($first_segment . '.php');
    }

    return basename($_SERVER['PHP_SELF'] ?? 'index.php');
}

function is_active(string $file): string
{
    return current_page() === $file ? 'active' : '';
}

/*
|--------------------------------------------------------------------------
| Redirect Helper
|--------------------------------------------------------------------------
*/
function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}


/*
|--------------------------------------------------------------------------
| Doctor Import Shared Helpers
|--------------------------------------------------------------------------
| Used by doctor CSV importer for safely joining unique non-empty values.
|--------------------------------------------------------------------------
*/
if (!function_exists('doctor_import_join_unique')) {
    function doctor_import_join_unique($items, string $separator = ', '): string
    {
        if ($items === null) {
            return '';
        }

        if (!is_array($items)) {
            $items = [$items];
        }

        $clean = [];

        foreach ($items as $item) {
            if (is_array($item)) {
                foreach ($item as $sub_item) {
                    $sub_item = trim(preg_replace('/\s+/u', ' ', (string)$sub_item));

                    if ($sub_item !== '') {
                        $clean[] = $sub_item;
                    }
                }

                continue;
            }

            $item = trim(preg_replace('/\s+/u', ' ', (string)$item));

            if ($item !== '') {
                $clean[] = $item;
            }
        }

        $clean = array_values(array_unique($clean));

        return implode($separator, $clean);
    }
}

if (!function_exists('doctor_import_clean_text')) {
    function doctor_import_clean_text($value): string
    {
        return trim(preg_replace('/\s+/u', ' ', (string)$value));
    }
}

/*
|--------------------------------------------------------------------------
| Slug Helpers
|--------------------------------------------------------------------------
*/
function slugify(string $text): string
{
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9]+/i', '-', $text);
    $text = preg_replace('/-+/', '-', (string)$text);

    return trim((string)$text, '-');
}

function seo_url_slug(string $text): string
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

function seo_slug_to_name(string $slug): string
{
    $slug = rawurldecode(trim($slug));
    $slug = str_replace('-', ' ', $slug);
    $slug = preg_replace('/\s+/u', ' ', (string)$slug);

    return trim(ucwords((string)$slug));
}

function hospitals_clean_url(array $filters = []): string
{
    /*
     * Supported clean URLs:
     * /hospitals
     * /hospitals/dhaka
     * /hospitals/dhaka/mirpur
     * /hospitals/dhaka/private-hospital
     * /hospitals/dhaka/mirpur/private-hospital
     * /hospitals/private-hospital
     */
    $district = trim((string)($filters['district'] ?? ($filters['city'] ?? '')));
    $thana = trim((string)($filters['thana'] ?? ''));
    $type = trim((string)($filters['type'] ?? ''));

    $path = 'hospitals';

    if ($district !== '') {
        $path .= '/' . seo_url_slug($district);

        if ($thana !== '') {
            $path .= '/' . seo_url_slug($thana);

            if ($type !== '') {
                $path .= '/' . seo_url_slug($type);
            }
        } elseif ($type !== '') {
            $path .= '/' . seo_url_slug($type);
        }
    } elseif ($type !== '') {
        $path .= '/' . seo_url_slug($type);
    }

    $query = [];

    foreach (['search', 'service', 'emergency', 'verified', 'featured'] as $key) {
        $value = trim((string)($filters[$key] ?? ''));

        if ($value !== '') {
            $query[$key] = $value;
        }
    }

    return site_url($path . (!empty($query) ? '?' . http_build_query($query) : ''));
}

function make_unique_slug(string $table, string $slug, int $ignore_id = 0): string
{
    global $pdo;

    $allowed_tables = ['doctors', 'hospitals', 'specialties'];

    if (!in_array($table, $allowed_tables, true)) {
        return $slug;
    }

    $base_slug = $slug ?: 'item';
    $final_slug = $base_slug;
    $counter = 2;

    while (true) {
        $sql = "SELECT id FROM {$table} WHERE slug = :slug";
        $params = [':slug' => $final_slug];

        if ($ignore_id > 0) {
            $sql .= " AND id != :id";
            $params[':id'] = $ignore_id;
        }

        $sql .= " LIMIT 1";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        if (!$stmt->fetch()) {
            return $final_slug;
        }

        $final_slug = $base_slug . '-' . $counter;
        $counter++;
    }
}

/*
|--------------------------------------------------------------------------
| Specialty Helpers
|--------------------------------------------------------------------------
*/
function get_specialty_name_by_id(int $specialty_id): string
{
    global $pdo;

    if ($specialty_id <= 0) {
        return '';
    }

    $stmt = $pdo->prepare("SELECT name FROM specialties WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $specialty_id]);

    $row = $stmt->fetch();

    return $row['name'] ?? '';
}

/*
|--------------------------------------------------------------------------
| Doctor Slug Builder
|--------------------------------------------------------------------------
| Manual slug has priority.
| If manual slug is empty, the slug will be generated from:
| doctor name + specialty name + district.
|--------------------------------------------------------------------------
*/
function build_doctor_slug(string $name, int $specialty_id, string $district, string $manual_slug = ''): string
{
    $manual_slug = trim($manual_slug);

    if ($manual_slug !== '') {
        return slugify($manual_slug);
    }

    $specialty_name = get_specialty_name_by_id($specialty_id);

    return slugify(trim($name . ' ' . $specialty_name . ' ' . $district));
}

/*
|--------------------------------------------------------------------------
| Hospital Slug Builder
|--------------------------------------------------------------------------
| Manual slug has priority.
| If manual slug is empty, the slug will be generated from:
| hospital name + district.
|--------------------------------------------------------------------------
*/
function build_hospital_slug(string $name, string $district, string $manual_slug = ''): string
{
    $manual_slug = trim($manual_slug);

    if ($manual_slug !== '') {
        return slugify($manual_slug);
    }

    return slugify(trim($name . ' ' . $district));
}

/*
|--------------------------------------------------------------------------
| Frontend Clean URL Helpers
|--------------------------------------------------------------------------
*/
function doctor_url(array $doctor): string
{
    $slug = trim((string)($doctor['slug'] ?? ''));

    if ($slug === '') {
        return site_url('doctors');
    }

    return site_url('doctor/' . $slug);
}

function hospital_url(array $hospital): string
{
    $slug = trim((string)($hospital['slug'] ?? ''));

    if ($slug === '') {
        return site_url('hospitals');
    }

    return site_url('hospital/' . $slug);
}

/*
|--------------------------------------------------------------------------
| UI Helpers
|--------------------------------------------------------------------------
*/
function verified_badge($status): string
{
    if (!(bool)$status) {
        return '';
    }

    return '<span class="verified-badge"><span class="check-icon">✓</span>Verified</span>';
}

function stars(float $rating): string
{
    $rating = max(0, min(5, $rating));
    $full = (int)round($rating);

    return str_repeat('★', $full) . str_repeat('☆', 5 - $full);
}

/*
|--------------------------------------------------------------------------
| Safe Database Structure Helpers
|--------------------------------------------------------------------------
*/
function table_exists(string $table): bool
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
        $cache[$table] = (int)$stmt->fetchColumn() > 0;
    } catch (Throwable $e) {
        $cache[$table] = false;
    }

    return $cache[$table];
}

function column_exists(string $table, string $column): bool
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
| Profile View Tracking
|--------------------------------------------------------------------------
| Counts visits to a doctor or hospital profile page for ranking purposes
| only (higher views_count sorts higher in the directory listing). Never
| shown on the public frontend -- only surfaced in the admin panel.
|
| One increment per visitor session per profile, so refreshing the page
| repeatedly cannot artificially inflate a profile's rank.
|--------------------------------------------------------------------------
*/
function ensure_profile_views_column(string $table): void
{
    global $pdo;

    if (!in_array($table, ['doctors', 'hospitals'], true)) {
        return;
    }

    try {
        if (!column_exists($table, 'views_count')) {
            $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN `views_count` INT UNSIGNED NOT NULL DEFAULT 0");
        }
    } catch (Throwable $e) {
        // If the hosting database user has no ALTER permission, view
        // tracking is silently skipped rather than breaking the page.
    }
}

function track_profile_view(string $table, int $id): void
{
    global $pdo;

    if ($id <= 0 || !in_array($table, ['doctors', 'hospitals'], true)) {
        return;
    }

    ensure_profile_views_column($table);

    if (!column_exists($table, 'views_count')) {
        return;
    }

    $session_key = 'viewed_' . $table;

    if (!isset($_SESSION[$session_key]) || !is_array($_SESSION[$session_key])) {
        $_SESSION[$session_key] = [];
    }

    if (!empty($_SESSION[$session_key][$id])) {
        return;
    }

    try {
        $stmt = $pdo->prepare("UPDATE `{$table}` SET views_count = views_count + 1 WHERE id = :id");
        $stmt->execute([':id' => $id]);

        $_SESSION[$session_key][$id] = true;
    } catch (Throwable $e) {
        // Never let view tracking break the profile page.
    }
}

function get_address_id_by_name(string $table, string $name): int
{
    global $pdo;

    $name = trim($name);

    if ($name === '') {
        return 0;
    }

    $allowed_tables = ['divisions', 'districts', 'thanas'];

    if (!in_array($table, $allowed_tables, true)) {
        return 0;
    }

    if (!table_exists($table)) {
        return 0;
    }

    try {
        $stmt = $pdo->prepare("
            SELECT id
            FROM {$table}
            WHERE name_en = :name
            OR name_bn = :name
            LIMIT 1
        ");

        $stmt->execute([':name' => $name]);

        return (int)$stmt->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

function get_division_by_district_name(string $district_name): array
{
    global $pdo;

    $district_name = trim($district_name);

    if ($district_name === '') {
        return [];
    }

    if (!table_exists('divisions') || !table_exists('districts')) {
        return [];
    }

    try {
        $stmt = $pdo->prepare("
            SELECT v.*
            FROM districts d
            INNER JOIN divisions v ON v.id = d.division_id
            WHERE d.name_en = :district
            OR d.name_bn = :district
            LIMIT 1
        ");

        $stmt->execute([':district' => $district_name]);

        $row = $stmt->fetch();

        return $row ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

function get_districts_by_division_id(int $division_id, string $lang = 'en'): array
{
    global $pdo;

    if ($division_id <= 0 || !table_exists('districts')) {
        return [];
    }

    $name_col = $lang === 'bn' ? 'name_bn' : 'name_en';

    try {
        $stmt = $pdo->prepare("
            SELECT id, {$name_col} AS name
            FROM districts
            WHERE division_id = :division_id
            ORDER BY {$name_col} ASC
        ");

        $stmt->execute([':division_id' => $division_id]);

        return $stmt->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

function get_thanas_by_district_name(string $district_name, string $lang = 'en'): array
{
    global $pdo;

    $district_name = trim($district_name);

    if ($district_name === '') {
        return [];
    }

    if (!table_exists('districts') || !table_exists('thanas')) {
        return [];
    }

    $name_col = $lang === 'bn' ? 'name_bn' : 'name_en';

    try {
        $stmt = $pdo->prepare("
            SELECT t.id, t.{$name_col} AS name
            FROM thanas t
            INNER JOIN districts d ON d.id = t.district_id
            WHERE d.name_en = :district
            OR d.name_bn = :district
            ORDER BY t.{$name_col} ASC
        ");

        $stmt->execute([':district' => $district_name]);

        return $stmt->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

/*
|--------------------------------------------------------------------------
| Dashboard Counts
|--------------------------------------------------------------------------
*/

function safe_table_count(string $table, string $where = ''): int
{
    global $pdo;

    $allowed_tables = [
        'doctors',
        'hospitals',
        'specialties',
        'reviews',
        'users',
        'profile_claims',
        'profile_update_requests',
        'divisions',
        'districts',
        'thanas',
        'site_settings',
    ];

    if (!in_array($table, $allowed_tables, true)) {
        return 0;
    }

    try {
        if (!table_exists($table)) {
            return 0;
        }

        $sql = "SELECT COUNT(*) FROM `{$table}`";

        if ($where !== '') {
            $sql .= " WHERE {$where}";
        }

        return (int)$pdo->query($sql)->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

function get_setting_counts(): array
{
    return [
        'doctors' => safe_table_count('doctors', "status='active'"),
        'hospitals' => safe_table_count('hospitals', "status='active'"),
        'specialties' => safe_table_count('specialties', "status='active'"),
        'locations' => safe_table_count('districts') + safe_table_count('thanas'),
        'reviews' => safe_table_count('reviews'),
        'users' => safe_table_count('users'),
        'active_users' => safe_table_count('users', "status='active'"),
        'blocked_users' => safe_table_count('users', "status='blocked'"),
        'pending_profile_claims' => safe_table_count('profile_claims', "status='pending'"),
        'pending_update_requests' => safe_table_count('profile_update_requests', "status='pending'"),
    ];
}

/*
|--------------------------------------------------------------------------
| Specialties
|--------------------------------------------------------------------------
*/
function get_specialties(): array
{
    global $pdo;

    $stmt = $pdo->query("SELECT * FROM specialties WHERE status='active' ORDER BY name ASC");

    return $stmt->fetchAll();
}

/*
|--------------------------------------------------------------------------
| Specialty Article Lookup
|--------------------------------------------------------------------------
| Used by specialty.php to load a single active specialty article by slug.
| The SELECT * query intentionally keeps all existing specialty fields
| available, including description, SEO fields and article content fields.
|--------------------------------------------------------------------------
*/
function get_specialty_by_slug(string $slug): ?array
{
    global $pdo;

    $slug = trim($slug);

    if ($slug === '' || !table_exists('specialties')) {
        return null;
    }

    try {
        $sql = "SELECT * FROM specialties WHERE slug = :slug";

        if (column_exists('specialties', 'status')) {
            $sql .= " AND status = 'active'";
        }

        $sql .= " LIMIT 1";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':slug' => $slug,
        ]);

        $specialty = $stmt->fetch();

        return $specialty ?: null;
    } catch (Throwable $e) {
        error_log('Specialty slug lookup failed: ' . $e->getMessage());

        return null;
    }
}

/*
|--------------------------------------------------------------------------
| Doctors
|--------------------------------------------------------------------------
*/
function get_featured_doctors(int $limit = 3): array
{
    global $pdo;

    $stmt = $pdo->prepare("
        SELECT d.*, s.name AS specialty_name, s.slug AS specialty_slug, h.name AS hospital_name
        FROM doctors d
        LEFT JOIN specialties s ON s.id = d.specialty_id
        LEFT JOIN hospitals h ON h.id = d.hospital_id
        WHERE d.status='active' AND d.is_featured=1
        ORDER BY d.id DESC
        LIMIT :limit
    ");

    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

function get_doctors(array $filters = [], int $limit = 20): array
{
    global $pdo;

    $sql = "
        SELECT d.*, s.name AS specialty_name, s.slug AS specialty_slug, h.name AS hospital_name
        FROM doctors d
        LEFT JOIN specialties s ON s.id = d.specialty_id
        LEFT JOIN hospitals h ON h.id = d.hospital_id
        WHERE d.status='active'
    ";

    $params = [];

    if (!empty($filters['search'])) {
        $sql .= " AND (
            d.name LIKE :search
            OR d.designation LIKE :search
            OR d.qualification LIKE :search
            OR h.name LIKE :search
            OR s.name LIKE :search
        )";
        $params[':search'] = '%' . trim((string)$filters['search']) . '%';
    }

    if (!empty($filters['specialty'])) {
        $sql .= " AND s.slug = :specialty";
        $params[':specialty'] = trim((string)$filters['specialty']);
    }

    if (!empty($filters['city'])) {
        $sql .= " AND d.city = :city";
        $params[':city'] = trim((string)$filters['city']);
    }

    $sql .= " ORDER BY d.id DESC LIMIT :limit";

    $stmt = $pdo->prepare($sql);

    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }

    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

function get_doctor_by_slug(string $slug): ?array
{
    global $pdo;

    $stmt = $pdo->prepare("
        SELECT d.*, s.name AS specialty_name, s.slug AS specialty_slug, h.name AS hospital_name
        FROM doctors d
        LEFT JOIN specialties s ON s.id = d.specialty_id
        LEFT JOIN hospitals h ON h.id = d.hospital_id
        WHERE d.slug = :slug AND d.status='active'
        LIMIT 1
    ");

    $stmt->execute([':slug' => trim($slug)]);

    $doctor = $stmt->fetch();

    return $doctor ?: null;
}

function get_related_doctors(array $doctor, int $limit = 4): array
{
    global $pdo;

    if (empty($doctor['id']) || empty($doctor['specialty_id'])) {
        return [];
    }

    $stmt = $pdo->prepare("
        SELECT d.*, s.name AS specialty_name, s.slug AS specialty_slug, h.name AS hospital_name
        FROM doctors d
        LEFT JOIN specialties s ON s.id = d.specialty_id
        LEFT JOIN hospitals h ON h.id = d.hospital_id
        WHERE d.status='active'
        AND d.id != :id
        AND d.specialty_id = :specialty_id
        ORDER BY d.id DESC
        LIMIT :limit
    ");

    $stmt->bindValue(':id', (int)$doctor['id'], PDO::PARAM_INT);
    $stmt->bindValue(':specialty_id', (int)$doctor['specialty_id'], PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

function get_doctor_chambers(int $doctor_id): array
{
    global $pdo;

    if ($doctor_id <= 0) {
        return [];
    }

    $stmt = $pdo->prepare("
        SELECT c.*, h.name AS hospital_name
        FROM chambers c
        LEFT JOIN hospitals h ON h.id = c.hospital_id
        WHERE c.doctor_id = :doctor_id AND c.status='active'
        ORDER BY c.sort_order ASC, c.id ASC
    ");

    $stmt->execute([':doctor_id' => $doctor_id]);

    return $stmt->fetchAll();
}

function get_doctor_reviews(int $doctor_id): array
{
    global $pdo;

    if ($doctor_id <= 0) {
        return [];
    }

    $stmt = $pdo->prepare("
        SELECT *
        FROM reviews
        WHERE doctor_id = :doctor_id AND status='approved'
        ORDER BY id DESC
        LIMIT 10
    ");

    $stmt->execute([':doctor_id' => $doctor_id]);

    return $stmt->fetchAll();
}

/*
|--------------------------------------------------------------------------
| Hospitals
|--------------------------------------------------------------------------
*/
function get_featured_hospitals(int $limit = 3): array
{
    global $pdo;

    $stmt = $pdo->prepare("
        SELECT *
        FROM hospitals
        WHERE status='active' AND is_featured=1
        ORDER BY id DESC
        LIMIT :limit
    ");

    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

function get_hospitals(array $filters = [], int $limit = 20): array
{
    global $pdo;

    if (!table_exists('hospitals')) {
        return [];
    }

    $sql = "SELECT * FROM hospitals WHERE 1=1";
    $params = [];

    if (column_exists('hospitals', 'status')) {
        $sql .= " AND status = 'active'";
    }

    if (!empty($filters['search'])) {
        $search_fields = ['name'];

        foreach ([
            'slug',
            'address',
            'city',
            'area',
            'road_no',
            'house_no',
            'post_code',
            'type',
            'phone',
            'email',
            'description',
            'services',
            'facilities',
            'seo_title',
            'seo_description'
        ] as $column) {
            if (column_exists('hospitals', $column)) {
                $search_fields[] = $column;
            }
        }

        $search_parts = [];

        foreach (array_unique($search_fields) as $field) {
            $search_parts[] = "{$field} LIKE :search";
        }

        $sql .= " AND (" . implode(' OR ', $search_parts) . ")";
        $params[':search'] = '%' . trim((string)$filters['search']) . '%';
    }

    if (!empty($filters['type']) && column_exists('hospitals', 'type')) {
        $sql .= " AND type = :type";
        $params[':type'] = trim((string)$filters['type']);
    }

    $district_name = trim((string)($filters['district'] ?? ($filters['city'] ?? '')));

    if ($district_name !== '') {
        $district_id = get_address_id_by_name('districts', $district_name);

        if ($district_id > 0 && column_exists('hospitals', 'district_id')) {
            $sql .= " AND district_id = :district_id";
            $params[':district_id'] = $district_id;
        } elseif (column_exists('hospitals', 'city')) {
            $sql .= " AND city LIKE :district_name";
            $params[':district_name'] = '%' . $district_name . '%';
        } elseif (column_exists('hospitals', 'address')) {
            $sql .= " AND address LIKE :district_name";
            $params[':district_name'] = '%' . $district_name . '%';
        }
    }

    if (!empty($filters['thana']) && column_exists('hospitals', 'thana_id')) {
        $thana_id = get_address_id_by_name('thanas', trim((string)$filters['thana']));

        if ($thana_id > 0) {
            $sql .= " AND thana_id = :thana_id";
            $params[':thana_id'] = $thana_id;
        }
    }

    if (!empty($filters['service'])) {
        $service_parts = [];

        foreach (['services', 'facilities', 'description', 'type'] as $column) {
            if (column_exists('hospitals', $column)) {
                $service_parts[] = "{$column} LIKE :service";
            }
        }

        $flag_map = [
            'Emergency' => 'emergency_available',
            'Ambulance' => 'ambulance_available',
            'ICU' => 'icu_available',
            'CCU' => 'ccu_available',
            'NICU' => 'nicu_available',
            'PICU' => 'picu_available',
            'Diagnostic' => 'diagnostic_available',
            'Pharmacy' => 'pharmacy_available',
            'Blood Bank' => 'blood_bank_available',
            'CT Scan' => 'ct_scan_available',
            'MRI' => 'mri_available',
            'X-Ray' => 'xray_available',
            'Lab' => 'lab_available',
            'Dental' => 'dental_unit_available',
            'Eye' => 'eye_unit_available',
            'Cardiac' => 'cardiac_unit_available',
            'Cancer' => 'cancer_unit_available',
        ];

        foreach ($flag_map as $label => $column) {
            if (stripos($label, (string)$filters['service']) !== false || stripos((string)$filters['service'], $label) !== false) {
                if (column_exists('hospitals', $column)) {
                    $service_parts[] = "{$column} = 1";
                }
            }
        }

        if (!empty($service_parts)) {
            $sql .= " AND (" . implode(' OR ', $service_parts) . ")";
            $params[':service'] = '%' . trim((string)$filters['service']) . '%';
        }
    }

    if (($filters['emergency'] ?? '') === '1' && column_exists('hospitals', 'emergency_available')) {
        $sql .= " AND emergency_available = 1";
    }

    if (($filters['verified'] ?? '') === '1' && column_exists('hospitals', 'is_verified')) {
        $sql .= " AND is_verified = 1";
    }

    if (($filters['featured'] ?? '') === '1' && column_exists('hospitals', 'is_featured')) {
        $sql .= " AND is_featured = 1";
    }

    $order = [];

    if (column_exists('hospitals', 'is_featured')) {
        $order[] = 'is_featured DESC';
    }

    if (column_exists('hospitals', 'is_verified')) {
        $order[] = 'is_verified DESC';
    }

    if (column_exists('hospitals', 'rating')) {
        $order[] = 'rating DESC';
    }

    $order[] = 'id DESC';

    $sql .= " ORDER BY " . implode(', ', $order) . " LIMIT :limit";

    try {
        $stmt = $pdo->prepare($sql);

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

function get_hospital_by_slug(string $slug): ?array
{
    global $pdo;

    $slug = trim($slug);

    if ($slug === '' || !table_exists('hospitals') || !column_exists('hospitals', 'slug')) {
        return null;
    }

    try {
        $sql = "SELECT * FROM hospitals WHERE slug = :slug";

        if (column_exists('hospitals', 'status')) {
            $sql .= " AND status = 'active'";
        }

        $sql .= " LIMIT 1";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([':slug' => $slug]);

        $hospital = $stmt->fetch();

        return $hospital ?: null;
    } catch (Throwable $e) {
        error_log('Hospital slug lookup failed: ' . $e->getMessage());

        return null;
    }
}

function get_hospital_doctors(int $hospital_id): array
{
    global $pdo;

    if ($hospital_id <= 0) {
        return [];
    }

    $stmt = $pdo->prepare("
        SELECT d.*, s.name AS specialty_name, s.slug AS specialty_slug, h.name AS hospital_name
        FROM doctors d
        LEFT JOIN specialties s ON s.id = d.specialty_id
        LEFT JOIN hospitals h ON h.id = d.hospital_id
        WHERE d.hospital_id = :hospital_id AND d.status='active'
        ORDER BY d.id DESC
    ");

    $stmt->execute([':hospital_id' => $hospital_id]);

    return $stmt->fetchAll();
}

/*
|--------------------------------------------------------------------------
| Flash Messages
|--------------------------------------------------------------------------
*/
function flash(string $type, string $message): void
{
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message,
    ];
}

function show_flash(): void
{
    if (!empty($_SESSION['flash'])) {
        $type = ($_SESSION['flash']['type'] ?? '') === 'error' ? 'alert-error' : 'alert-success';
        $message = $_SESSION['flash']['message'] ?? '';

        echo '<div class="' . e($type) . '">' . e($message) . '</div>';

        unset($_SESSION['flash']);
    }
}

/*
|--------------------------------------------------------------------------
| Admin Helpers
|--------------------------------------------------------------------------
*/
function is_admin(): bool
{
    return !empty($_SESSION['admin_logged_in']);
}

function require_admin(): void
{
    if (!is_admin()) {
        redirect('login.php');
    }
}

/*
|--------------------------------------------------------------------------
| Image Upload
|--------------------------------------------------------------------------
*/
function upload_image(string $field, string $folder): ?string
{
    if (
        empty($_FILES[$field])
        || !isset($_FILES[$field]['error'], $_FILES[$field]['tmp_name'], $_FILES[$field]['name'])
        || (int)$_FILES[$field]['error'] !== UPLOAD_ERR_OK
    ) {
        return null;
    }

    if (!defined('UPLOAD_PATH') || !defined('UPLOAD_URL')) {
        return null;
    }

    $tmp_name = (string)$_FILES[$field]['tmp_name'];
    $file_size = (int)($_FILES[$field]['size'] ?? 0);
    $original_name = (string)$_FILES[$field]['name'];

    if (
        $tmp_name === ''
        || !is_uploaded_file($tmp_name)
        || $file_size <= 0
        || $file_size > (8 * 1024 * 1024)
    ) {
        return null;
    }

    $extension = strtolower((string)pathinfo($original_name, PATHINFO_EXTENSION));
    $extension_map = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
    ];

    if (!isset($extension_map[$extension])) {
        return null;
    }

    $image_info = @getimagesize($tmp_name);

    if ($image_info === false || empty($image_info['mime'])) {
        return null;
    }

    $mime = strtolower((string)$image_info['mime']);

    if ($mime !== $extension_map[$extension]) {
        return null;
    }

    /*
     * Allow a simple safe folder name such as doctors, hospitals or reviews.
     * This prevents an unexpected folder argument from escaping UPLOAD_PATH.
     */
    $folder = trim((string)preg_replace('#[^a-zA-Z0-9/_-]+#', '', trim($folder)), '/');

    if ($folder === '' || strpos($folder, '..') !== false) {
        return null;
    }

    $directory = rtrim(UPLOAD_PATH, '/') . '/' . $folder . '/';

    if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
        return null;
    }

    try {
        $filename = 'img_' . bin2hex(random_bytes(16)) . '.' . $extension;
    } catch (Throwable $e) {
        $filename = 'img_' . str_replace('.', '', uniqid('', true)) . '.' . $extension;
    }

    $target = $directory . $filename;

    if (!move_uploaded_file($tmp_name, $target)) {
        return null;
    }

    return rtrim(UPLOAD_URL, '/') . '/' . $folder . '/' . $filename;
}

/*
|--------------------------------------------------------------------------
| Site Settings Helpers
|--------------------------------------------------------------------------
*/
function ensure_site_settings_table(): bool
{
    global $pdo;

    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `site_settings` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `setting_key` VARCHAR(150) NOT NULL,
                `setting_value` LONGTEXT NULL,
                `created_at` DATETIME DEFAULT NULL,
                `updated_at` DATETIME DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `site_settings_setting_key_unique` (`setting_key`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        return true;
    } catch (Throwable $e) {
        return false;
    }
}

function default_site_settings(): array
{
    return [
        'site_name' => defined('APP_NAME') ? APP_NAME : 'Deluti',
        'site_tagline' => 'Doctor and Hospital Directory',
        'site_short_name' => 'Deluti',
        'site_description' => 'Find doctors, hospitals, specialties and healthcare services easily.',
        'site_logo' => '',
        'site_dark_logo' => '',
        'site_favicon' => '',
        'site_apple_touch_icon' => '',
        'site_timezone' => 'Asia/Dhaka',
        'site_language' => 'en',

        'admin_email' => '',
        'support_email' => '',
        'contact_email' => '',
        'contact_phone' => '',
        'hotline_number' => '',
        'whatsapp_number' => '',
        'emergency_number' => '',
        'office_address' => '',
        'map_embed_code' => '',
        'business_hours' => '',

        'facebook_url' => '',
        'twitter_url' => '',
        'linkedin_url' => '',
        'instagram_url' => '',
        'youtube_url' => '',
        'tiktok_url' => '',
        'telegram_url' => '',
        'whatsapp_channel_url' => '',

        'meta_title' => '',
        'meta_description' => '',
        'meta_keywords' => '',
        'canonical_url' => '',
        'robots_meta' => 'index, follow',
        'google_site_verification' => '',
        'bing_site_verification' => '',
        'default_og_image' => '',
        'default_schema_type' => 'WebSite',

        'home_hero_title' => 'Find the Right Doctor Near You',
        'home_hero_subtitle' => 'Search doctors, hospitals, specialties and locations from one trusted platform.',
        'home_hero_button_text' => 'Find Doctor',
        'home_hero_button_url' => 'doctors.php',
        'home_hero_image' => '',
        'home_featured_specialties_limit' => '12',
        'home_featured_doctors_limit' => '8',
        'home_featured_hospitals_limit' => '8',

        'doctors_per_page' => '12',
        'hospitals_per_page' => '12',
        'reviews_per_page' => '10',
        'enable_doctor_reviews' => '1',
        'enable_hospital_reviews' => '1',
        'enable_profile_claim' => '1',
        'enable_profile_update_request' => '1',
        'default_doctor_image' => '',
        'default_hospital_image' => '',

        'enable_user_registration' => '1',
        'enable_user_login' => '1',
        'default_user_status' => 'active',
        'email_verification_required' => '0',
        'phone_verification_required' => '0',

        'mail_from_name' => defined('APP_NAME') ? APP_NAME : 'Deluti',
        'mail_from_email' => '',
        'smtp_enabled' => '0',
        'smtp_host' => '',
        'smtp_port' => '',
        'smtp_username' => '',
        'smtp_password' => '',
        'smtp_encryption' => 'tls',

        'sms_enabled' => '0',
        'sms_provider' => '',
        'sms_api_key' => '',
        'sms_sender_id' => '',
        'whatsapp_enabled' => '0',
        'whatsapp_api_key' => '',

        'google_analytics_id' => '',
        'google_tag_manager_id' => '',
        'facebook_pixel_id' => '',
        'custom_head_code' => '',
        'custom_footer_code' => '',

        'primary_color' => '#0f766e',
        'secondary_color' => '#14b8a6',
        'accent_color' => '#2da44e',
        'body_background_color' => '#f6f8fa',
        'header_style' => 'default',
        'footer_style' => 'default',
        'enable_dark_mode' => '0',

        'show_topbar' => '1',
        'show_search_bar' => '1',
        'show_login_button' => '1',
        'show_register_button' => '1',
        'header_button_text' => 'Add Listing',
        'header_button_url' => '',

        'footer_text' => 'All rights reserved.',
        'footer_about_text' => '',
        'footer_copyright_text' => '',
        'show_footer_social_links' => '1',
        'show_footer_contact_info' => '1',

        'privacy_policy_url' => '',
        'terms_conditions_url' => '',
        'refund_policy_url' => '',
        'cookie_policy_url' => '',
        'disclaimer_url' => '',

        'maintenance_mode' => '0',
        'maintenance_title' => 'Website Under Maintenance',
        'maintenance_message' => 'We are currently updating our website. Please check back soon.',
        'enable_recaptcha' => '0',
        'recaptcha_site_key' => '',
        'recaptcha_secret_key' => '',
        'admin_login_attempt_limit' => '5',

        'backup_email' => '',
        'system_notification_email' => '',
        'enable_error_log' => '1',
        'enable_debug_mode' => '0',
    ];
}

function seed_default_site_settings(bool $overwrite_empty = false): bool
{
    global $pdo;

    if (!ensure_site_settings_table()) {
        return false;
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO site_settings (setting_key, setting_value, created_at, updated_at)
            VALUES (:setting_key, :setting_value, NOW(), NOW())
            ON DUPLICATE KEY UPDATE
                setting_value = CASE
                    WHEN :overwrite_empty = 1 AND (setting_value IS NULL OR setting_value = '') THEN VALUES(setting_value)
                    ELSE setting_value
                END,
                updated_at = NOW()
        ");

        foreach (default_site_settings() as $key => $value) {
            $stmt->execute([
                ':setting_key' => $key,
                ':setting_value' => (string)$value,
                ':overwrite_empty' => $overwrite_empty ? 1 : 0,
            ]);
        }

        return true;
    } catch (Throwable $e) {
        return false;
    }
}

function get_site_setting(string $key, string $default = ''): string
{
    global $pdo;

    try {
        if (!table_exists('site_settings')) {
            return default_site_settings()[$key] ?? $default;
        }

        $stmt = $pdo->prepare("SELECT setting_value FROM site_settings WHERE setting_key = :setting_key LIMIT 1");
        $stmt->execute([':setting_key' => $key]);

        $value = $stmt->fetchColumn();

        if ($value === false || $value === null) {
            return default_site_settings()[$key] ?? $default;
        }

        return (string)$value;
    } catch (Throwable $e) {
        return default_site_settings()[$key] ?? $default;
    }
}

function update_site_setting(string $key, string $value): bool
{
    global $pdo;

    try {
        if (!ensure_site_settings_table()) {
            return false;
        }

        $stmt = $pdo->prepare("
            INSERT INTO site_settings (setting_key, setting_value, created_at, updated_at)
            VALUES (:setting_key, :setting_value, NOW(), NOW())
            ON DUPLICATE KEY UPDATE
                setting_value = VALUES(setting_value),
                updated_at = NOW()
        ");

        return $stmt->execute([
            ':setting_key' => $key,
            ':setting_value' => $value,
        ]);
    } catch (Throwable $e) {
        return false;
    }
}

function update_site_settings(array $settings): bool
{
    $ok = true;

    foreach ($settings as $key => $value) {
        if (!update_site_setting((string)$key, (string)$value)) {
            $ok = false;
        }
    }

    return $ok;
}

function get_all_site_settings(): array
{
    global $pdo;

    $settings = default_site_settings();

    try {
        if (!table_exists('site_settings')) {
            return $settings;
        }

        $stmt = $pdo->query("SELECT setting_key, setting_value FROM site_settings");
        $rows = $stmt->fetchAll();

        foreach ($rows as $row) {
            $settings[(string)$row['setting_key']] = (string)($row['setting_value'] ?? '');
        }

        return $settings;
    } catch (Throwable $e) {
        return $settings;
    }
}

function site_setting_bool(string $key, bool $default = false): bool
{
    $value = strtolower(trim(get_site_setting($key, $default ? '1' : '0')));

    return in_array($value, ['1', 'true', 'yes', 'on'], true);
}

function site_setting_int(string $key, int $default = 0): int
{
    $value = get_site_setting($key, (string)$default);

    return is_numeric($value) ? (int)$value : $default;
}

function site_asset_url(string $path): string
{
    $path = trim($path);

    if ($path === '') {
        return '';
    }

    if (preg_match('/^https?:\/\//i', $path)) {
        return $path;
    }

    return site_url($path);
}

function site_logo_url(): string
{
    return site_asset_url(get_site_setting('site_logo'));
}

function site_favicon_url(): string
{
    return site_asset_url(get_site_setting('site_favicon'));
}

function is_maintenance_mode(): bool
{
    if (is_admin()) {
        return false;
    }

    return site_setting_bool('maintenance_mode', false);
}

function show_maintenance_page_if_enabled(): void
{
    if (!is_maintenance_mode()) {
        return;
    }

    http_response_code(503);

    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">';
    echo '<title>' . e(get_site_setting('maintenance_title', 'Website Under Maintenance')) . '</title>';
    echo '<style>body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;font-family:Arial,sans-serif;background:#f6f8fa;color:#24292f}.box{max-width:560px;background:#fff;border:1px solid #d0d7de;border-radius:14px;padding:28px;text-align:center;box-shadow:0 12px 32px rgba(27,31,36,.08)}h1{margin:0 0 10px;font-size:28px}p{margin:0;color:#57606a;line-height:1.7}</style>';
    echo '</head><body><div class="box">';
    echo '<h1>' . e(get_site_setting('maintenance_title', 'Website Under Maintenance')) . '</h1>';
    echo '<p>' . e(get_site_setting('maintenance_message', 'We are currently updating our website. Please check back soon.')) . '</p>';
    echo '</div></body></html>';

    exit;
}

function render_tracking_codes(string $position = 'head'): void
{
    $position = $position === 'footer' ? 'footer' : 'head';

    if ($position === 'head') {
        $gtm_id = trim(get_site_setting('google_tag_manager_id'));
        $ga_id = trim(get_site_setting('google_analytics_id'));
        $facebook_pixel_id = trim(get_site_setting('facebook_pixel_id'));
        $custom_head_code = get_site_setting('custom_head_code');

        if ($gtm_id !== '') {
            echo "<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer','" . e($gtm_id) . "');</script>";
        }

        if ($ga_id !== '') {
            echo "<script async src=\"https://www.googletagmanager.com/gtag/js?id=" . e($ga_id) . "\"></script>";
            echo "<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','" . e($ga_id) . "');</script>";
        }

        if ($facebook_pixel_id !== '') {
            echo "<script>!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');fbq('init','" . e($facebook_pixel_id) . "');fbq('track','PageView');</script>";
        }

        if ($custom_head_code !== '') {
            echo $custom_head_code;
        }

        return;
    }

    $custom_footer_code = get_site_setting('custom_footer_code');

    if ($custom_footer_code !== '') {
        echo $custom_footer_code;
    }
}
/*
|--------------------------------------------------------------------------
| Admin Moderators / Roles / Permissions Helper
|--------------------------------------------------------------------------
*/

if (!function_exists('admin_permissions_list')) {
    function admin_permissions_list(): array
    {
        return [
            'dashboard.view' => 'View Dashboard',

            'doctors.view' => 'View Doctors',
            'doctors.create' => 'Add Doctor',
            'doctors.edit' => 'Edit Doctor',
            'doctors.delete' => 'Delete Doctor',

            'hospitals.view' => 'View Hospitals',
            'hospitals.create' => 'Add Hospital',
            'hospitals.edit' => 'Edit Hospital',
            'hospitals.delete' => 'Delete Hospital',

            'specialties.manage' => 'Manage Specialties',
            'locations.manage' => 'Manage Locations',

            'reviews.view' => 'View Reviews',
            'reviews.manage' => 'Manage Reviews',

            'contacts.view' => 'View Contact Messages',
            'contacts.manage' => 'Manage Contact Messages',

            'users.view' => 'View Users',
            'users.manage' => 'Manage Users',

            'claims.view' => 'View Profile Claims',
            'claims.manage' => 'Manage Profile Claims',

            'settings.view' => 'View Site Settings',
            'settings.manage' => 'Manage Site Settings',

            'moderators.view' => 'View Moderators',
            'moderators.manage' => 'Manage Moderators',
        ];
    }
}

if (!function_exists('admin_default_role_permissions')) {
    function admin_default_role_permissions(string $role): array
    {
        $all = array_keys(admin_permissions_list());

        if ($role === 'super_admin') {
            return $all;
        }

        if ($role === 'admin') {
            return array_values(array_filter($all, function ($permission) {
                return !in_array($permission, ['moderators.manage'], true);
            }));
        }

        if ($role === 'moderator') {
            return [
                'dashboard.view',
                'doctors.view',
                'doctors.edit',
                'hospitals.view',
                'hospitals.edit',
                'reviews.view',
                'reviews.manage',
                'contacts.view',
                'contacts.manage',
                'users.view',
                'claims.view',
                'claims.manage',
            ];
        }

        if ($role === 'editor') {
            return [
                'dashboard.view',
                'doctors.view',
                'doctors.create',
                'doctors.edit',
                'hospitals.view',
                'hospitals.create',
                'hospitals.edit',
                'specialties.manage',
                'locations.manage',
            ];
        }

        if ($role === 'support') {
            return [
                'dashboard.view',
                'users.view',
                'claims.view',
                'reviews.view',
                'contacts.view',
            ];
        }

        return ['dashboard.view'];
    }
}

if (!function_exists('current_admin_id')) {
    function current_admin_id(): int
    {
        return (int)($_SESSION['admin_id'] ?? 0);
    }
}

if (!function_exists('current_admin_role')) {
    function current_admin_role(): string
    {
        return (string)($_SESSION['admin_role'] ?? 'super_admin');
    }
}

if (!function_exists('current_admin_permissions')) {
    function current_admin_permissions(): array
    {
        $role = current_admin_role();

        if ($role === 'super_admin') {
            return array_keys(admin_permissions_list());
        }

        $permissions = $_SESSION['admin_permissions'] ?? [];

        if (is_string($permissions)) {
            $decoded = json_decode($permissions, true);
            $permissions = is_array($decoded) ? $decoded : [];
        }

        if (!is_array($permissions) || empty($permissions)) {
            return admin_default_role_permissions($role);
        }

        return array_values(array_unique(array_map('strval', $permissions)));
    }
}

if (!function_exists('admin_can')) {
    function admin_can(string $permission): bool
    {
        if (current_admin_role() === 'super_admin') {
            return true;
        }

        return in_array($permission, current_admin_permissions(), true);
    }
}

if (!function_exists('require_admin_permission')) {
    function require_admin_permission(string $permission): void
    {
        if (!admin_can($permission)) {
            http_response_code(403);

            echo '<div style="max-width:720px;margin:40px auto;padding:20px;border:1px solid #d0d7de;border-radius:10px;font-family:Arial,sans-serif;">
                    <h2 style="margin-top:0;">Access denied</h2>
                    <p>You do not have permission to access this page.</p>
                    <a href="dashboard.php">Back to Dashboard</a>
                  </div>';

            exit;
        }
    }
}

if (!function_exists('admin_log_activity')) {
    function admin_log_activity(string $action, string $description = ''): void
    {
        global $pdo;

        try {
            if (!function_exists('table_exists') || !table_exists('admin_activity_logs')) {
                return;
            }

            $stmt = $pdo->prepare("
                INSERT INTO admin_activity_logs 
                    (admin_id, action, description, ip_address, user_agent, created_at)
                VALUES 
                    (:admin_id, :action, :description, :ip_address, :user_agent, NOW())
            ");

            $stmt->execute([
                ':admin_id' => current_admin_id() ?: null,
                ':action' => $action,
                ':description' => $description,
                ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
                ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            ]);
        } catch (Throwable $e) {
            return;
        }
    }
}
