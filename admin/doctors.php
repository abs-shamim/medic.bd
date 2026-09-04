<?php
require_once __DIR__ . '/../includes/functions.php';

require_admin();

ensure_profile_views_column('doctors');

function admin_doctors_table_exists(string $table): bool
{
    global $pdo;

    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM INFORMATION_SCHEMA.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = :table
        ");
        $stmt->execute([':table' => $table]);

        return (int) $stmt->fetchColumn() > 0;
    } catch (Throwable $e) {
        return false;
    }
}

function admin_doctors_column_exists(string $table, string $column): bool
{
    global $pdo;

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

        return (int) $stmt->fetchColumn() > 0;
    } catch (Throwable $e) {
        return false;
    }
}


/**
 * Return an EXISTS expression when the doctor has at least one saved chamber.
 * The current Doctor Hospital Availability module stores chambers in `chambers`.
 * Any saved chamber counts as complete, even when it is currently inactive or closed.
 */
function admin_doctors_chamber_exists_sql(): string
{
    $candidate_tables = [
        'chambers',
        'doctor_chambers',
        'doctor_hospitals',
        'doctor_hospital_availability',
        'doctor_availability',
    ];

    foreach ($candidate_tables as $table) {
        if (
            !admin_doctors_table_exists($table) ||
            !admin_doctors_column_exists($table, 'doctor_id')
        ) {
            continue;
        }

        return "EXISTS (
            SELECT 1
            FROM `{$table}` c
            WHERE c.`doctor_id` = d.`id`
            LIMIT 1
        )";
    }

    return '0 = 1';
}

/**
 * Build a 0-100 doctor profile completion expression.
 *
 * Score distribution:
 * Basic Information: 30%
 * Chamber Added: 30%
 * Contact & Location: 5%
 * Professional Details: 5%
 * Image & Biography: 10%
 * Clinical Profile: 20%
 *
 * SEO, status, verification and featured controls are excluded from this score.
 */
function admin_doctors_profile_completion_sql(): string
{
    $field_weights = [
        // Basic Information: 30%
        ['name', 3, 'text'],
        ['name_bn', 1, 'text'],
        ['degree', 3, 'text'],
        ['degree_bn', 1, 'text'],
        ['designation', 3, 'text'],
        ['designation_bn', 1, 'text'],
        ['primary_hospital', 3, 'text'],
        ['primary_hospital_bn', 1, 'text'],
        ['languages', 1, 'text'],
        ['languages_bn', 1, 'text'],
        ['bmdc_number', 2, 'text'],
        ['gender', 2, 'text'],
        ['gender_bn', 1, 'text'],
        ['specialty_id', 3, 'positive_number'],
        ['slug', 4, 'text'],

        // Contact & Location: 5%
        ['phone', 1, 'text'],
        ['whatsapp', 0.5, 'text'],
        ['email', 0.5, 'text'],
        ['doctor_division_id', 1, 'positive_number'],
        ['doctor_district_id', 1, 'positive_number'],
        ['doctor_thana_id', 1, 'positive_number'],

        // Professional Details: 5%
        ['experience_years', 1.5, 'positive_number'],
        ['consultation_fee', 1.5, 'positive_number'],
        ['follow_up_fee', 1, 'positive_number'],
        ['video_consultation_fee', 1, 'positive_number'],

        // Image & Biography: 10%
        ['image', 4, 'text'],
        ['bio', 3, 'text'],
        ['bio_bn', 3, 'text'],

        // Clinical Profile: 20%
        ['education', 2, 'text'],
        ['education_bn', 2, 'text'],
        ['training', 2, 'text'],
        ['training_bn', 2, 'text'],
        ['fellowship', 2, 'text'],
        ['fellowship_bn', 2, 'text'],
        ['expertise', 2, 'text'],
        ['expertise_bn', 2, 'text'],
        ['appointment_note', 2, 'text'],
        ['appointment_note_bn', 2, 'text'],
    ];

    $parts = [];

    foreach ($field_weights as [$column, $weight, $type]) {
        if (!admin_doctors_column_exists('doctors', $column)) {
            continue;
        }

        $weight_sql = rtrim(
            rtrim(number_format((float) $weight, 2, '.', ''), '0'),
            '.'
        );

        if ($type === 'positive_number') {
            $condition = "COALESCE(d.`{$column}`, 0) > 0";
        } else {
            $condition = "NULLIF(TRIM(COALESCE(d.`{$column}`, '')), '') IS NOT NULL";
        }

        $parts[] = "CASE WHEN {$condition} THEN {$weight_sql} ELSE 0 END";
    }

    // Chamber Added: 30%. One or more saved chambers gives the full 30%.
    $chamber_exists_sql = admin_doctors_chamber_exists_sql();
    $parts[] = "CASE WHEN {$chamber_exists_sql} THEN 30 ELSE 0 END";

    return !empty($parts)
        ? '(' . implode(' + ', $parts) . ')'
        : '0';
}

function admin_doctors_get_items(string $table, string $name_column = 'name_en', string $where_sql = '', array $params = []): array
{
    global $pdo;

    if (!admin_doctors_table_exists($table)) {
        return [];
    }

    if (!admin_doctors_column_exists($table, 'id') || !admin_doctors_column_exists($table, $name_column)) {
        return [];
    }

    $status_sql = '';

    if (admin_doctors_column_exists($table, 'status')) {
        $status_sql = $where_sql === '' ? "WHERE status = 'active'" : "AND status = 'active'";
    }

    try {
        $stmt = $pdo->prepare("
            SELECT id, {$name_column} AS name
            FROM {$table}
            {$where_sql}
            {$status_sql}
            ORDER BY {$name_column} ASC
        ");
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return [];
    }
}

function admin_doctors_safe_return_url(string $return_url): string
{
    $return_url = trim($return_url);

    if ($return_url === '') {
        return 'doctors.php';
    }

    $parts = parse_url($return_url);

    if (!empty($parts['scheme']) || !empty($parts['host'])) {
        return 'doctors.php';
    }

    $path = $parts['path'] ?? '';

    if ($path !== 'doctors.php' && basename($path) !== 'doctors.php') {
        return 'doctors.php';
    }

    return $return_url;
}

function admin_doctors_page_url(int $page): string
{
    $query_params = $_GET;
    $query_params['page'] = $page;

    return 'doctors.php?' . http_build_query($query_params);
}

function admin_doctors_days_from_date(?string $date): ?int
{
    $date = trim((string) $date);

    if ($date === '') {
        return null;
    }

    try {
        $today = new DateTime(date('Y-m-d'));
        $target = new DateTime($date);

        $days = (int) $today->diff($target)->days;

        return $target >= $today ? $days : -$days;
    } catch (Throwable $e) {
        return null;
    }
}

function admin_doctors_get_last_featured_end_date(int $doctor_id): string
{
    global $pdo;

    if ($doctor_id <= 0 || !admin_doctors_table_exists('doctor_featured_history')) {
        return '';
    }

    if (
        !admin_doctors_column_exists('doctor_featured_history', 'doctor_id') ||
        !admin_doctors_column_exists('doctor_featured_history', 'featured_until')
    ) {
        return '';
    }

    try {
        $stmt = $pdo->prepare("
            SELECT featured_until
            FROM doctor_featured_history
            WHERE doctor_id = :doctor_id
              AND featured_until IS NOT NULL
              AND featured_until != ''
            ORDER BY featured_until DESC, id DESC
            LIMIT 1
        ");
        $stmt->execute([':doctor_id' => $doctor_id]);

        return trim((string) $stmt->fetchColumn());
    } catch (Throwable $e) {
        return '';
    }
}

function admin_doctors_featured_badge(array $doctor): string
{
    $is_featured = !empty($doctor['is_featured']);
    $is_on_hold = !empty($doctor['featured_on_hold']);
    $doctor_id = (int) ($doctor['id'] ?? 0);

    if ($is_featured) {
        $remaining = admin_doctors_days_from_date($doctor['featured_until'] ?? '');

        if ($remaining === null) {
            $remaining = (int) ($doctor['featured_days'] ?? 0);
        }

        return '<span class="featured-badge active">Active (' . e((string) $remaining) . ')</span>';
    }

    if ($is_on_hold) {
        $hold_remaining = (int) ($doctor['featured_hold_remaining_days'] ?? 0);

        return '<span class="featured-badge inactive">Inactive (' . e((string) $hold_remaining) . ')</span>';
    }

    $last_end_date = admin_doctors_get_last_featured_end_date($doctor_id);

    if ($last_end_date !== '') {
        $signed_days = admin_doctors_days_from_date($last_end_date);

        if ($signed_days === null) {
            $signed_days = 0;
        }

        return '<span class="featured-badge inactive">Inactive (' . e((string) $signed_days) . ')</span>';
    }

    return '<span class="featured-badge off">Off</span>';
}


function admin_doctors_verified_icon(array $doctor): string
{
    if (!empty($doctor['is_verified'])) {
        return '<span class="verified-icon verified" title="Verified" aria-label="Verified">✓</span>';
    }

    return '<span class="verified-icon not-verified" title="Not verified" aria-label="Not verified">×</span>';
}

function admin_doctors_initials(string $name): string
{
    $name = trim($name);

    if ($name === '') {
        return 'DR';
    }

    $parts = preg_split('/\s+/', $name);
    $initials = '';

    foreach ($parts as $part) {
        $part = trim($part);

        if ($part !== '') {
            $initials .= strtoupper(mb_substr($part, 0, 1));
        }

        if (mb_strlen($initials) >= 2) {
            break;
        }
    }

    return $initials !== '' ? $initials : 'DR';
}

function admin_doctors_image_url(?string $image): string
{
    $image = trim((string) $image);

    if ($image === '') {
        return '';
    }

    if (preg_match('#^(https?:)?//#i', $image) || str_starts_with($image, 'data:')) {
        return $image;
    }

    if (str_starts_with($image, '../') || str_starts_with($image, './')) {
        return $image;
    }

    if (function_exists('site_url')) {
        return site_url(ltrim($image, '/'));
    }

    return '../' . ltrim($image, '/');
}

function admin_doctors_get_site_setting(string $key, string $default = ''): string
{
    global $pdo;

    if (function_exists('get_setting')) {
        $value = trim((string) get_setting($key, ''));

        if ($value !== '') {
            return $value;
        }
    }

    if (!admin_doctors_table_exists('site_settings')) {
        return $default;
    }

    if (
        !admin_doctors_column_exists('site_settings', 'setting_key') ||
        !admin_doctors_column_exists('site_settings', 'setting_value')
    ) {
        return $default;
    }

    try {
        $stmt = $pdo->prepare("
            SELECT setting_value
            FROM site_settings
            WHERE setting_key = :setting_key
            LIMIT 1
        ");
        $stmt->execute([':setting_key' => $key]);

        $value = trim((string) $stmt->fetchColumn());

        return $value !== '' ? $value : $default;
    } catch (Throwable $e) {
        return $default;
    }
}

function admin_doctors_get_first_site_setting(array $keys, string $default = ''): string
{
    foreach ($keys as $key) {
        $value = admin_doctors_get_site_setting($key, '');

        if ($value !== '') {
            return $value;
        }
    }

    return $default;
}

function admin_doctors_default_image_path(array $doctor): string
{
    $gender = strtolower(trim((string) ($doctor['gender'] ?? '')));

    if ($gender === 'male' || $gender === 'm') {
        return admin_doctors_get_first_site_setting(
            [
                'default_doctor_male_image',
                'default_doctor_image_male',
                'doctor_default_male_image',
            ],
            '../assets/images/default-doctor-male.webp'
        );
    }

    if ($gender === 'female' || $gender === 'f') {
        return admin_doctors_get_first_site_setting(
            [
                'default_doctor_female_image',
                'default_doctor_image_female',
                'doctor_default_female_image',
            ],
            '../assets/images/default-doctor-female.webp'
        );
    }

    return admin_doctors_get_first_site_setting(
        [
            'default_doctor_image',
            'doctor_default_image',
        ],
        '../assets/images/default-doctor.webp'
    );
}

function admin_doctors_default_image_url(array $doctor): string
{
    return admin_doctors_image_url(admin_doctors_default_image_path($doctor));
}

function admin_doctors_image_html(array $doctor): string
{
    $name = trim((string) ($doctor['name'] ?? ''));
    $image_url = admin_doctors_image_url($doctor['image'] ?? '');

    if ($image_url === '') {
        $image_url = admin_doctors_default_image_url($doctor);
    }

    $fallback_url = admin_doctors_image_url(
        admin_doctors_get_first_site_setting(
            [
                'default_doctor_image',
                'doctor_default_image',
            ],
            '../assets/images/default-doctor.webp'
        )
    );

    if ($fallback_url === '') {
        $fallback_url = 'data:image/svg+xml;utf8,' . rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" width="80" height="80" viewBox="0 0 80 80"><rect width="80" height="80" rx="40" fill="#f6f8fa"/><text x="40" y="46" text-anchor="middle" font-size="20" font-family="Arial" fill="#57606a" font-weight="700">DR</text></svg>');
    }

    if ($image_url !== '') {
        return '<span class="doctor-avatar"><img src="' . e($image_url) . '" alt="' . e($name !== '' ? $name : 'Doctor') . '" loading="lazy" onerror="this.onerror=null;this.src=\'' . e($fallback_url) . '\';"></span>';
    }

    return '<span class="doctor-avatar placeholder">' . e(admin_doctors_initials($name)) . '</span>';
}

function admin_doctors_featured_counts(): array
{
    global $pdo;

    $counts = [
        'active' => 0,
        'inactive' => 0,
    ];

    try {
        if (admin_doctors_column_exists('doctors', 'is_featured')) {
            $counts['active'] = (int) $pdo->query("
                SELECT COUNT(*)
                FROM doctors
                WHERE is_featured = 1
            ")->fetchColumn();
        }

        $history_exists_sql = admin_doctors_table_exists('doctor_featured_history')
            ? "OR EXISTS (
                SELECT 1
                FROM doctor_featured_history h
                WHERE h.doctor_id = doctors.id
                LIMIT 1
              )"
            : "";

        $hold_sql = admin_doctors_column_exists('doctors', 'featured_on_hold')
            ? "OR featured_on_hold = 1"
            : "";

        $counts['inactive'] = (int) $pdo->query("
            SELECT COUNT(*)
            FROM doctors
            WHERE COALESCE(is_featured, 0) = 0
              AND (
                1 = 0
                {$hold_sql}
                {$history_exists_sql}
              )
        ")->fetchColumn();
    } catch (Throwable $e) {
        return $counts;
    }

    return $counts;
}

$current_return_url = 'doctors.php' . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '');

if (empty($_SESSION['doctor_delete_csrf'])) {
    $_SESSION['doctor_delete_csrf'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_doctor'])) {
    $doctor_id = isset($_POST['doctor_id']) ? (int) $_POST['doctor_id'] : 0;
    $csrf_token = $_POST['csrf_token'] ?? '';
    $return_url = admin_doctors_safe_return_url($_POST['return_url'] ?? 'doctors.php');

    if (!hash_equals($_SESSION['doctor_delete_csrf'], $csrf_token)) {
        $_SESSION['flash_error'] = 'Invalid security token. Please try again.';
        header('Location: ' . $return_url);
        exit;
    }

    if ($doctor_id <= 0) {
        $_SESSION['flash_error'] = 'Invalid doctor ID.';
        header('Location: ' . $return_url);
        exit;
    }

    try {
        $stmt = $pdo->prepare("DELETE FROM doctors WHERE id = :id");
        $stmt->execute([':id' => $doctor_id]);

        $_SESSION['flash_success'] = 'Doctor deleted successfully.';
    } catch (Throwable $e) {
        $_SESSION['flash_error'] = 'Doctor could not be deleted.';
    }

    header('Location: ' . $return_url);
    exit;
}

$search = trim($_GET['search'] ?? '');
$division_id = (int) ($_GET['division_id'] ?? 0);
$district_id = (int) ($_GET['district_id'] ?? 0);
$thana_id = (int) ($_GET['thana_id'] ?? 0);
$specialty_id = (int) ($_GET['specialty_id'] ?? 0);
$verified = trim($_GET['verified'] ?? '');
$featured = trim($_GET['featured'] ?? '');
$featured_status = trim($_GET['featured_status'] ?? '');
$improved = isset($_GET['improved']) && $_GET['improved'] === '1';

$page = max(1, (int) ($_GET['page'] ?? 1));
$per_page = 30;
$offset = ($page - 1) * $per_page;

$featured_counts = admin_doctors_featured_counts();

$divisions = admin_doctors_get_items('divisions', 'name_en');

$districts = [];

if ($division_id > 0) {
    $districts = admin_doctors_get_items(
        'districts',
        'name_en',
        'WHERE division_id = :division_id',
        [':division_id' => $division_id]
    );
}

if ($district_id > 0 && !empty($districts)) {
    $valid_district_ids = array_map('intval', array_column($districts, 'id'));

    if (!in_array($district_id, $valid_district_ids, true)) {
        $district_id = 0;
        $thana_id = 0;
    }
}

$thanas = [];

if ($district_id > 0) {
    $thanas = admin_doctors_get_items(
        'thanas',
        'name_en',
        'WHERE district_id = :district_id',
        [':district_id' => $district_id]
    );
}

if ($thana_id > 0 && !empty($thanas)) {
    $valid_thana_ids = array_map('intval', array_column($thanas, 'id'));

    if (!in_array($thana_id, $valid_thana_ids, true)) {
        $thana_id = 0;
    }
}

$specialties = [];

try {
    if (function_exists('get_specialties')) {
        $specialties = get_specialties();
    }

    if (empty($specialties) && admin_doctors_table_exists('specialties')) {
        $specialties = admin_doctors_get_items('specialties', 'name');
    }
} catch (Throwable $e) {
    $specialties = [];
}

$where = [];
$params = [];

$select_parts = ['d.*'];
$join_parts = [];

/**
 * Completion percentage is used in the table and by Improved (New).
 */
$completion_score_sql = admin_doctors_profile_completion_sql();
$select_parts[] = "{$completion_score_sql} AS completion_percentage";

/**
 * Total number of doctor profiles that still need improvement.
 * This is intentionally calculated without the current search/filter values,
 * so the Improved button always shows the site-wide number below 90%.
 */
$improved_total_count = 0;

try {
    $improved_count_stmt = $pdo->query("
        SELECT COUNT(*)
        FROM doctors d
        WHERE ({$completion_score_sql}) < 90
    ");

    $improved_total_count = (int) $improved_count_stmt->fetchColumn();
} catch (Throwable $e) {
    error_log('Admin doctor improved count query failed: ' . $e->getMessage());
}

if (
    admin_doctors_table_exists('specialties') &&
    admin_doctors_column_exists('doctors', 'specialty_id') &&
    admin_doctors_column_exists('specialties', 'id') &&
    admin_doctors_column_exists('specialties', 'name')
) {
    $select_parts[] = 's.name AS specialty_name';
    $join_parts[] = 'LEFT JOIN specialties s ON s.id = d.specialty_id';
}

if (
    admin_doctors_table_exists('divisions') &&
    admin_doctors_column_exists('doctors', 'doctor_division_id') &&
    admin_doctors_column_exists('divisions', 'id') &&
    admin_doctors_column_exists('divisions', 'name_en')
) {
    $select_parts[] = 'dv.name_en AS division_name';
    $join_parts[] = 'LEFT JOIN divisions dv ON dv.id = d.doctor_division_id';
}

if (
    admin_doctors_table_exists('districts') &&
    admin_doctors_column_exists('doctors', 'doctor_district_id') &&
    admin_doctors_column_exists('districts', 'id') &&
    admin_doctors_column_exists('districts', 'name_en')
) {
    $select_parts[] = 'ds.name_en AS district_name';
    $join_parts[] = 'LEFT JOIN districts ds ON ds.id = d.doctor_district_id';
}

if (
    admin_doctors_table_exists('thanas') &&
    admin_doctors_column_exists('doctors', 'doctor_thana_id') &&
    admin_doctors_column_exists('thanas', 'id') &&
    admin_doctors_column_exists('thanas', 'name_en')
) {
    $select_parts[] = 'th.name_en AS thana_name';
    $join_parts[] = 'LEFT JOIN thanas th ON th.id = d.doctor_thana_id';
}

if ($search !== '') {
    $searchable_columns = [
        'name',
        'name_bn',
        'phone',
        'email',
        'bmdc_number',
        'designation',
        'designation_bn',
        'degree',
        'degree_bn',
        'primary_hospital',
        'address',
        'address_bn',
        'area_locality',
        'doctor_division',
        'doctor_district',
        'doctor_thana',
        'city',
    ];

    $search_conditions = [];

    foreach ($searchable_columns as $index => $column) {
        if (admin_doctors_column_exists('doctors', $column)) {
            /*
             * Do not reuse the same named placeholder multiple times.
             * Some PDO/MySQL setups throw "Invalid parameter number" when one
             * placeholder such as :search is used in many OR conditions.
             */
            $placeholder = ':search_' . $index;
            $search_conditions[] = "d.{$column} LIKE {$placeholder}";
            $params[$placeholder] = '%' . $search . '%';
        }
    }

    if (!empty($search_conditions)) {
        $where[] = '(' . implode(' OR ', $search_conditions) . ')';
    }
}

if ($division_id > 0 && admin_doctors_column_exists('doctors', 'doctor_division_id')) {
    $where[] = 'd.doctor_division_id = :division_id';
    $params[':division_id'] = $division_id;
}

if ($district_id > 0 && admin_doctors_column_exists('doctors', 'doctor_district_id')) {
    $where[] = 'd.doctor_district_id = :district_id';
    $params[':district_id'] = $district_id;
}

if ($thana_id > 0 && admin_doctors_column_exists('doctors', 'doctor_thana_id')) {
    $where[] = 'd.doctor_thana_id = :thana_id';
    $params[':thana_id'] = $thana_id;
}

if ($specialty_id > 0 && admin_doctors_column_exists('doctors', 'specialty_id')) {
    $where[] = 'd.specialty_id = :specialty_id';
    $params[':specialty_id'] = $specialty_id;
}

if ($verified !== '' && admin_doctors_column_exists('doctors', 'is_verified')) {
    $where[] = 'd.is_verified = :verified';
    $params[':verified'] = (int) $verified;
}

if ($featured !== '' && admin_doctors_column_exists('doctors', 'is_featured')) {
    $where[] = 'd.is_featured = :featured';
    $params[':featured'] = (int) $featured;
}

if ($featured_status === 'active' && admin_doctors_column_exists('doctors', 'is_featured')) {
    $where[] = 'd.is_featured = 1';
}

if ($featured_status === 'inactive') {
    $inactive_conditions = [];

    if (admin_doctors_column_exists('doctors', 'featured_on_hold')) {
        $inactive_conditions[] = 'd.featured_on_hold = 1';
    }

    if (admin_doctors_table_exists('doctor_featured_history')) {
        $inactive_conditions[] = "EXISTS (
            SELECT 1
            FROM doctor_featured_history h
            WHERE h.doctor_id = d.id
            LIMIT 1
        )";
    }

    if (!empty($inactive_conditions)) {
        $where[] = 'COALESCE(d.is_featured, 0) = 0';
        $where[] = '(' . implode(' OR ', $inactive_conditions) . ')';
    }
}

/**
 * Improved (New): only profiles below 90% complete.
 * The list is sorted from lowest completion to highest completion.
 */
if ($improved) {
    $where[] = "({$completion_score_sql}) < 90";
}

$select_sql = implode(",\n        ", $select_parts);
$join_sql = !empty($join_parts) ? implode("\n    ", $join_parts) : '';
$where_sql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

$order_sql = $improved
    ? 'ORDER BY completion_percentage ASC, d.id DESC'
    : 'ORDER BY d.id DESC';

$total_filtered_doctors = 0;

try {
    $count_stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM doctors d
        {$join_sql}
        {$where_sql}
    ");
    $count_stmt->execute($params);

    $total_filtered_doctors = (int) $count_stmt->fetchColumn();
} catch (Throwable $e) {
    $total_filtered_doctors = 0;
}

$total_pages = max(1, (int) ceil($total_filtered_doctors / $per_page));
$page = min($page, $total_pages);
$offset = ($page - 1) * $per_page;

$doctors = [];

try {
    $stmt = $pdo->prepare("
        SELECT
            {$select_sql}
        FROM doctors d
        {$join_sql}
        {$where_sql}
        {$order_sql}
        LIMIT :limit OFFSET :offset
    ");

    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }

    $stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    $doctors = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $doctors = [];
    error_log('Admin doctor list query failed: ' . $e->getMessage());
    $_SESSION['flash_error'] = 'Doctor list could not be loaded. Please check database columns.';
}

$total_doctors = 0;

try {
    $total_doctors = (int) $pdo->query("SELECT COUNT(*) FROM doctors")->fetchColumn();
} catch (Throwable $e) {
    $total_doctors = count($doctors);
}

require_once __DIR__ . '/includes/header.php';
?>

<style>
.admin-icon-actions {
    display: flex;
    gap: 6px;
    align-items: center;
}

/*
 * Scoped under .admin-icon-actions so these beat the admin panel's global
 * ".admin-main button" rule (class+element specificity) on every property,
 * including background -- a bare ".admin-icon-btn.danger" class selector
 * still loses the background fight to that more specific global rule even
 * though it wins on color, leaving the Delete button green instead of red.
 */
.admin-icon-actions .admin-icon-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    min-height: 0;
    padding: 0;
    border-radius: 6px;
    border: 1px solid #d0d7de;
    background: #ffffff;
    color: #57606a;
    text-decoration: none;
    cursor: pointer;
    font-size: 14px;
}

.admin-icon-actions .admin-icon-btn:hover {
    background: #f6f8fa;
    color: #24292f;
    text-decoration: none;
}

.admin-icon-actions .admin-icon-btn.danger {
    background: #ffffff;
    color: #cf222e;
    border-color: rgba(207, 34, 46, 0.35);
}

.admin-icon-actions .admin-icon-btn.danger:hover {
    background: #ffebe9;
    color: #a40e26;
}

.doctor-top-actions {
    margin-bottom: 16px;
    display: flex;
    gap: 10px;
    align-items: center;
    flex-wrap: wrap;
}

.improved-link {
    display: inline-flex;
    align-items: center;
    gap: 7px;
}

.improved-new-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 2px 7px;
    border-radius: 999px;
    background: #dbeafe;
    color: #1d4ed8;
    font-size: 11px;
    font-weight: 800;
    line-height: 1.3;
}

.admin-table th.completion-col,
.admin-table td.completion-col {
    min-width: 148px;
}

.completion-wrap {
    display: grid;
    gap: 6px;
}

.completion-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    font-size: 12px;
}

.completion-score {
    color: #0f172a;
    font-weight: 800;
}

.completion-label {
    font-weight: 800;
}

.completion-bar {
    height: 7px;
    overflow: hidden;
    border-radius: 999px;
    background: #e2e8f0;
}

.completion-bar > span {
    display: block;
    height: 100%;
    border-radius: inherit;
}

.admin-table th.sl-col,
.admin-table td.sl-col {
    width: 62px;
    text-align: center;
}

.admin-table th.image-col,
.admin-table td.image-col {
    width: 74px;
    text-align: center;
}

.doctor-name-cell {
    min-width: 180px;
    font-weight: 700;
    color: #24292f;
}

.doctor-avatar {
    width: 42px;
    height: 42px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    overflow: hidden;
    border: 1px solid #d0d7de;
    background: #f6f8fa;
    color: #57606a;
    font-size: 12px;
    font-weight: 800;
}

.doctor-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}

.verified-icon {
    width: 30px;
    height: 30px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 999px;
    font-size: 16px;
    font-weight: 900;
    line-height: 1;
    border: 1px solid #d0d7de;
}

.verified-icon.verified {
    background: #dafbe1;
    border-color: #aceebb;
    color: #116329;
}

.verified-icon.not-verified {
    background: #ffebe9;
    border-color: #ff818266;
    color: #cf222e;
}

.featured-count-wrap {
    display: inline-flex;
    gap: 8px;
    align-items: center;
    flex-wrap: wrap;
    margin-left: 4px;
}

.featured-count-label {
    font-weight: 700;
    color: #24292f;
}

.featured-count-link {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    min-height: 36px;
    padding: 7px 12px;
    border-radius: 999px;
    border: 1px solid #d0d7de;
    background: #fff;
    color: #24292f;
    font-size: 13px;
    font-weight: 700;
    text-decoration: none;
}

.featured-count-link.active {
    background: #dafbe1;
    border-color: #aceebb;
    color: #116329;
}

.featured-count-link.inactive {
    background: #f6f8fa;
    border-color: #d0d7de;
    color: #57606a;
}

.featured-count-link.is-selected {
    outline: 3px solid rgba(9,105,218,.15);
    border-color: #0969da;
}

.featured-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 28px;
    padding: 5px 10px;
    border-radius: 999px;
    border: 1px solid #d0d7de;
    font-size: 12px;
    font-weight: 800;
    white-space: nowrap;
}

.featured-badge.active {
    background: #dafbe1;
    border-color: #aceebb;
    color: #116329;
}

.featured-badge.inactive {
    background: #fff8c5;
    border-color: #f0d98c;
    color: #7d4e00;
}

.featured-badge.off {
    background: #f6f8fa;
    border-color: #d0d7de;
    color: #57606a;
}
</style>

<h1 style="margin-bottom:18px;color:#0f172a;">Doctors</h1>

<?php show_flash(); ?>

<?php if (!empty($_SESSION['flash_success'])): ?>
  <div style="margin-bottom:16px;padding:12px 15px;border-radius:10px;background:#dcfce7;color:#166534;border:1px solid #86efac;">
    <?= e($_SESSION['flash_success']) ?>
  </div>
  <?php unset($_SESSION['flash_success']); ?>
<?php endif; ?>

<?php if (!empty($_SESSION['flash_error'])): ?>
  <div style="margin-bottom:16px;padding:12px 15px;border-radius:10px;background:#fee2e2;color:#991b1b;border:1px solid #fecaca;">
    <?= e($_SESSION['flash_error']) ?>
  </div>
  <?php unset($_SESSION['flash_error']); ?>
<?php endif; ?>

<div class="doctor-top-actions">
  <a href="doctor-form.php" class="btn btn-primary">Add Doctor</a>
  <a href="import-doctors.php" class="btn btn-outline">Import Doctors</a>

  <a
    href="doctors.php?improved=1"
    class="btn <?= $improved ? 'btn-primary' : 'btn-outline' ?> improved-link"
    title="<?= e((string) $improved_total_count) ?> doctor profiles are below 90% completion"
  >
    <span>Improved (<?= e((string) $improved_total_count) ?>)</span>
    <span class="improved-new-badge">New</span>
  </a>

  <div class="featured-count-wrap">
    <span class="featured-count-label">Featured:</span>

    <a
      href="doctors.php?featured_status=active"
      class="featured-count-link active <?= $featured_status === 'active' ? 'is-selected' : '' ?>"
    >
      Active (<?= e((string) $featured_counts['active']) ?>)
    </a>

    <a
      href="doctors.php?featured_status=inactive"
      class="featured-count-link inactive <?= $featured_status === 'inactive' ? 'is-selected' : '' ?>"
    >
      Inactive (<?= e((string) $featured_counts['inactive']) ?>)
    </a>
  </div>
</div>

<form method="GET" action="doctors.php" style="margin-bottom:18px;" id="doctorFilterForm">
  <div style="background:#f8fafc;border:1px solid #e2e8f0;padding:16px;border-radius:14px;">

    <?php if ($featured_status !== ''): ?>
      <input type="hidden" name="featured_status" value="<?= e($featured_status) ?>">
    <?php endif; ?>

    <?php if ($improved): ?>
      <input type="hidden" name="improved" value="1">
    <?php endif; ?>

    <div style="display:grid;grid-template-columns:repeat(7,minmax(140px,1fr));gap:12px;margin-bottom:12px;">

      <input
        type="text"
        name="search"
        value="<?= e($search) ?>"
        placeholder="Keyword search..."
        style="padding:11px 13px;border:1px solid #cbd5e1;border-radius:10px;font-size:14px;outline:none;width:100%;"
      >

      <select name="division_id" id="divisionFilter" style="padding:11px 13px;border:1px solid #cbd5e1;border-radius:10px;font-size:14px;outline:none;width:100%;">
        <option value="0">All Divisions</option>
        <?php foreach ($divisions as $division): ?>
          <option value="<?= e((string) $division['id']) ?>" <?= $division_id === (int) $division['id'] ? 'selected' : '' ?>>
            <?= e($division['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>

      <select name="district_id" id="districtFilter" style="padding:11px 13px;border:1px solid #cbd5e1;border-radius:10px;font-size:14px;outline:none;width:100%;" <?= $division_id <= 0 ? 'disabled' : '' ?>>
        <option value="0">All Districts</option>
        <?php foreach ($districts as $district): ?>
          <option value="<?= e((string) $district['id']) ?>" <?= $district_id === (int) $district['id'] ? 'selected' : '' ?>>
            <?= e($district['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>

      <select name="thana_id" id="thanaFilter" style="padding:11px 13px;border:1px solid #cbd5e1;border-radius:10px;font-size:14px;outline:none;width:100%;" <?= $district_id <= 0 ? 'disabled' : '' ?>>
        <option value="0">All Thanas</option>
        <?php foreach ($thanas as $thana): ?>
          <option value="<?= e((string) $thana['id']) ?>" <?= $thana_id === (int) $thana['id'] ? 'selected' : '' ?>>
            <?= e($thana['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>

      <select name="specialty_id" style="padding:11px 13px;border:1px solid #cbd5e1;border-radius:10px;font-size:14px;outline:none;width:100%;">
        <option value="0">All Specialties</option>
        <?php foreach ($specialties as $specialty): ?>
          <option value="<?= e((string) ($specialty['id'] ?? 0)) ?>" <?= $specialty_id === (int) ($specialty['id'] ?? 0) ? 'selected' : '' ?>>
            <?= e($specialty['name'] ?? '') ?>
          </option>
        <?php endforeach; ?>
      </select>

      <select name="verified" style="padding:11px 13px;border:1px solid #cbd5e1;border-radius:10px;font-size:14px;outline:none;width:100%;">
        <option value="">All Verified</option>
        <option value="1" <?= $verified === '1' ? 'selected' : '' ?>>Verified</option>
        <option value="0" <?= $verified === '0' ? 'selected' : '' ?>>Not Verified</option>
      </select>

      <select name="featured" style="padding:11px 13px;border:1px solid #cbd5e1;border-radius:10px;font-size:14px;outline:none;width:100%;">
        <option value="">All Featured</option>
        <option value="1" <?= $featured === '1' ? 'selected' : '' ?>>Featured Active</option>
        <option value="0" <?= $featured === '0' ? 'selected' : '' ?>>Not Active</option>
      </select>

    </div>

    <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
      <button type="submit" class="btn btn-primary">Filter Search</button>
      <a href="doctors.php" class="btn btn-outline">Reset</a>

      <span style="color:#475569;font-size:14px;">
        Showing <strong><?= count($doctors) ?></strong> of <strong><?= $total_filtered_doctors ?></strong> doctors
      </span>

      <?php if ($improved): ?>
        <span style="color:#b45309;font-size:14px;font-weight:700;">
          Showing profiles below 90% completion, from lowest to highest. Any saved chamber adds the full 30%.
        </span>
      <?php endif; ?>

      <?php if ($total_pages > 1): ?>
        <span style="color:#64748b;font-size:14px;">
          Page <strong><?= e((string) $page) ?></strong> of <strong><?= e((string) $total_pages) ?></strong>
        </span>
      <?php endif; ?>
    </div>

  </div>
</form>

<table class="admin-table">
  <thead>
    <tr>
      <th class="sl-col">SL</th>
      <th class="image-col">Image</th>
      <th>Name</th>
      <th>Specialty</th>
      <th>Division</th>
      <th>District</th>
      <th>Thana</th>
      <th>Verified</th>
      <th>Featured</th>
      <th>Views</th>
      <th class="completion-col">Completion</th>
      <th>Actions</th>
    </tr>
  </thead>

  <tbody>
    <?php if (!empty($doctors)): ?>
      <?php foreach ($doctors as $index => $doctor): ?>
        <?php
        $completion_percentage = (float) ($doctor['completion_percentage'] ?? 0);
        $completion_percentage = max(0, min(100, $completion_percentage));
        $completion_label = (int) round($completion_percentage);

        if ($completion_label >= 90) {
            $completion_color = '#16a34a';
            $completion_text = 'Complete';
        } elseif ($completion_label >= 60) {
            $completion_color = '#f59e0b';
            $completion_text = 'In progress';
        } else {
            $completion_color = '#dc2626';
            $completion_text = 'Needs work';
        }
        ?>
        <tr>
          <td class="sl-col"><?= e((string) ($offset + $index + 1)) ?></td>
          <td class="image-col"><?= admin_doctors_image_html($doctor) ?></td>
          <td class="doctor-name-cell"><?= e($doctor['name'] ?? '') ?></td>
          <td><?= e($doctor['specialty_name'] ?? '') ?></td>
          <td><?= e($doctor['division_name'] ?? $doctor['doctor_division'] ?? '') ?></td>
          <td><?= e($doctor['district_name'] ?? $doctor['doctor_district'] ?? $doctor['city'] ?? '') ?></td>
          <td><?= e($doctor['thana_name'] ?? $doctor['doctor_thana'] ?? '') ?></td>
          <td><?= admin_doctors_verified_icon($doctor) ?></td>
          <td><?= admin_doctors_featured_badge($doctor) ?></td>
          <td><?= number_format((int)($doctor['views_count'] ?? 0)) ?></td>
          <td class="completion-col">
            <div class="completion-wrap">
              <div class="completion-top">
                <span class="completion-score"><?= e((string) $completion_label) ?>%</span>
                <span class="completion-label" style="color:<?= e($completion_color) ?>;">
                  <?= e($completion_text) ?>
                </span>
              </div>

              <div class="completion-bar">
                <span
                  style="width:<?= e((string) $completion_label) ?>%;background:<?= e($completion_color) ?>;"
                ></span>
              </div>
            </div>
          </td>
          <td>
            <div class="admin-icon-actions">
              <a class="admin-icon-btn" href="doctor-form.php?id=<?= e((string) ($doctor['id'] ?? '')) ?>" title="Edit"><i class="fa fa-pencil"></i></a>

              <?php if (!empty($doctor['slug']) && function_exists('site_url')): ?>
                <a class="admin-icon-btn" href="<?= e(site_url('doctor/' . $doctor['slug'])) ?>" target="_blank" title="View"><i class="fa fa-eye"></i></a>
              <?php elseif (!empty($doctor['slug'])): ?>
                <a class="admin-icon-btn" href="../doctor/<?= e($doctor['slug']) ?>" target="_blank" title="View"><i class="fa fa-eye"></i></a>
              <?php else: ?>
                <a class="admin-icon-btn" href="doctor-form.php?id=<?= e((string) ($doctor['id'] ?? '')) ?>" title="View"><i class="fa fa-eye"></i></a>
              <?php endif; ?>

              <form method="POST" action="doctors.php" style="display:inline;" onsubmit="return confirm('Delete this doctor?');">
                <input type="hidden" name="csrf_token" value="<?= e($_SESSION['doctor_delete_csrf']) ?>">
                <input type="hidden" name="doctor_id" value="<?= e((string) ($doctor['id'] ?? '')) ?>">
                <input type="hidden" name="return_url" value="<?= e($current_return_url) ?>">
                <button type="submit" name="delete_doctor" class="admin-icon-btn danger" title="Delete"><i class="fa fa-trash-o"></i></button>
              </form>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
    <?php else: ?>
      <tr>
        <td colspan="12" style="text-align:center;padding:20px;color:#64748b;">
          No doctors found for your filter.
        </td>
      </tr>
    <?php endif; ?>
  </tbody>
</table>

<?php if ($total_pages > 1): ?>
  <div style="margin-top:18px;display:flex;gap:8px;align-items:center;flex-wrap:wrap;">

    <?php if ($page > 1): ?>
      <a href="<?= e(admin_doctors_page_url($page - 1)) ?>" class="btn btn-outline small-btn">Previous</a>
    <?php endif; ?>

    <?php
      $start_page = max(1, $page - 2);
      $end_page = min($total_pages, $page + 2);
    ?>

    <?php if ($start_page > 1): ?>
      <a href="<?= e(admin_doctors_page_url(1)) ?>" class="btn btn-outline small-btn">1</a>
      <?php if ($start_page > 2): ?>
        <span style="padding:8px;color:#64748b;">...</span>
      <?php endif; ?>
    <?php endif; ?>

    <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
      <a href="<?= e(admin_doctors_page_url($i)) ?>" class="btn <?= $i === $page ? 'btn-primary' : 'btn-outline' ?> small-btn">
        <?= e((string) $i) ?>
      </a>
    <?php endfor; ?>

    <?php if ($end_page < $total_pages): ?>
      <?php if ($end_page < $total_pages - 1): ?>
        <span style="padding:8px;color:#64748b;">...</span>
      <?php endif; ?>

      <a href="<?= e(admin_doctors_page_url($total_pages)) ?>" class="btn btn-outline small-btn">
        <?= e((string) $total_pages) ?>
      </a>
    <?php endif; ?>

    <?php if ($page < $total_pages): ?>
      <a href="<?= e(admin_doctors_page_url($page + 1)) ?>" class="btn btn-outline small-btn">Next</a>
    <?php endif; ?>

    <span style="color:#64748b;font-size:14px;margin-left:6px;">
      Page <?= e((string) $page) ?> of <?= e((string) $total_pages) ?>
    </span>
  </div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('doctorFilterForm');
    const divisionFilter = document.getElementById('divisionFilter');
    const districtFilter = document.getElementById('districtFilter');
    const thanaFilter = document.getElementById('thanaFilter');

    if (divisionFilter) {
        divisionFilter.addEventListener('change', function () {
            if (districtFilter) {
                districtFilter.value = '0';
                districtFilter.disabled = true;
            }

            if (thanaFilter) {
                thanaFilter.value = '0';
                thanaFilter.disabled = true;
            }

            form.submit();
        });
    }

    if (districtFilter) {
        districtFilter.addEventListener('change', function () {
            if (thanaFilter) {
                thanaFilter.value = '0';
                thanaFilter.disabled = true;
            }

            form.submit();
        });
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>