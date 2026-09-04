<?php
require_once __DIR__ . '/../includes/functions.php';

require_admin();

ensure_profile_views_column('hospitals');

/**
 * Validate SQL identifier before using it in dynamic SQL.
 */
function admin_hospitals_is_valid_identifier(string $value): bool
{
    return (bool) preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value);
}

/**
 * Quote a trusted SQL identifier.
 */
function admin_hospitals_quote_identifier(string $identifier): string
{
    if (!admin_hospitals_is_valid_identifier($identifier)) {
        throw new InvalidArgumentException('Invalid database identifier.');
    }

    return '`' . $identifier . '`';
}

/**
 * Check whether a table exists in the active database.
 */
function admin_hospitals_table_exists(string $table): bool
{
    global $pdo;

    static $cache = [];

    if (array_key_exists($table, $cache)) {
        return $cache[$table];
    }

    if (!admin_hospitals_is_valid_identifier($table)) {
        $cache[$table] = false;
        return false;
    }

    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM INFORMATION_SCHEMA.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = :table
        ");

        $stmt->execute([
            ':table' => $table,
        ]);

        $cache[$table] = ((int) $stmt->fetchColumn() > 0);

        return $cache[$table];
    } catch (Throwable $e) {
        error_log('Hospital table check failed: ' . $e->getMessage());

        $cache[$table] = false;
        return false;
    }
}

/**
 * Get all columns of a table once per request.
 */
function admin_hospitals_get_table_columns(string $table): array
{
    global $pdo;

    static $cache = [];

    if (array_key_exists($table, $cache)) {
        return $cache[$table];
    }

    $cache[$table] = [];

    if (!admin_hospitals_table_exists($table)) {
        return $cache[$table];
    }

    try {
        $stmt = $pdo->prepare("
            SELECT COLUMN_NAME
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = :table
        ");

        $stmt->execute([
            ':table' => $table,
        ]);

        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);

        foreach ($columns as $column) {
            $cache[$table][(string) $column] = true;
        }
    } catch (Throwable $e) {
        error_log('Hospital column check failed: ' . $e->getMessage());
    }

    return $cache[$table];
}

/**
 * Check whether a column exists.
 */
function admin_hospitals_column_exists(string $table, string $column): bool
{
    $columns = admin_hospitals_get_table_columns($table);

    return isset($columns[$column]);
}

/**
 * Return the first available column from a list.
 */
function admin_hospitals_first_existing_column(string $table, array $possible_columns): ?string
{
    foreach ($possible_columns as $column) {
        if (admin_hospitals_column_exists($table, $column)) {
            return $column;
        }
    }

    return null;
}

/**
 * Get a safe row value from possible field names.
 */
function admin_hospitals_get_row_value(array $row, array $possible_keys): string
{
    foreach ($possible_keys as $key) {
        if (!empty($row[$key])) {
            return trim((string) $row[$key]);
        }
    }

    return '';
}


/**
 * Build the hospital profile completion score.
 *
 * Score allocation:
 * Basic Information: 30%
 * Address Information: 30%
 * Contact Information: 15%
 * Description & Timing: 10%
 * Media: 5%
 * SEO Information: 10%
 *
 * Status, verified and featured are intentionally excluded.
 */
function admin_hospitals_profile_completion_sql(): string
{
    $field_weights = [
        // Basic Information: 30%
        ['name', 5, 'text'],
        ['name_bn', 3, 'text'],
        ['type', 3, 'text'],
        ['type_bn', 2, 'text'],
        ['license_number', 3, 'text'],
        ['established_year', 3, 'positive_number'],
        ['bed_count', 3, 'positive_number'],
        ['website_url', 3, 'text'],
        ['slug', 5, 'text'],

        // Address Information: 30%
        ['division_id', 4, 'positive_number'],
        ['district_id', 4, 'positive_number'],
        ['thana_id', 3, 'positive_number'],
        ['area', 3, 'text'],
        ['area_bn', 2, 'text'],
        ['road_no', 3, 'text'],
        ['road_no_bn', 2, 'text'],
        ['house_no', 3, 'text'],
        ['house_no_bn', 2, 'text'],
        ['post_code', 2, 'text'],
        ['map_url', 2, 'text'],

        // Contact Information: 15%
        ['phone', 4, 'text'],
        ['whatsapp', 3, 'text'],
        ['emergency_phone', 3, 'text'],
        ['ambulance_phone', 3, 'text'],
        ['email', 2, 'text'],

        // Description & Timing: 10%
        ['description', 2, 'text'],
        ['description_bn', 1, 'text'],
        ['opening_hours', 0.75, 'text'],
        ['opening_hours_bn', 0.5, 'text'],
        ['visiting_hours', 0.75, 'text'],
        ['visiting_hours_bn', 0.5, 'text'],
        ['services', 1.25, 'text'],
        ['services_bn', 0.75, 'text'],
        ['facilities', 1.5, 'text'],
        ['facilities_bn', 1, 'text'],

        // Media: 5%
        ['image', 2, 'text'],
        ['cover_image', 2, 'text'],
        ['video_url', 1, 'text'],

        // SEO Information: 10%
        ['seo_title', 1.5, 'text'],
        ['seo_title_bn', 1.5, 'text'],
        ['seo_description', 2, 'text'],
        ['seo_description_bn', 2, 'text'],
        ['meta_keywords', 1.5, 'text'],
        ['meta_keywords_bn', 1.5, 'text'],
    ];

    $parts = [];
    $available_weight = 0.0;

    foreach ($field_weights as [$column, $weight, $type]) {
        if (!admin_hospitals_column_exists('hospitals', $column)) {
            continue;
        }

        $quoted_column = admin_hospitals_quote_identifier($column);
        $weight_sql = rtrim(rtrim(number_format((float) $weight, 2, '.', ''), '0'), '.');

        if ($type === 'positive_number') {
            $condition = "COALESCE(h.{$quoted_column}, 0) > 0";
        } else {
            $condition = "h.{$quoted_column} IS NOT NULL AND TRIM(h.{$quoted_column}) <> ''";
        }

        $parts[] = "CASE WHEN {$condition} THEN {$weight_sql} ELSE 0 END";
        $available_weight += (float) $weight;
    }

    if (empty($parts) || $available_weight <= 0) {
        return '0';
    }

    $available_weight_sql = rtrim(
        rtrim(number_format($available_weight, 2, '.', ''), '0'),
        '.'
    );

    return '((' . implode(' + ', $parts) . ') / ' . $available_weight_sql . ' * 100)';
}

/**
 * Count hospitals that need profile improvement.
 *
 * The count is calculated from the full hospitals table, not from the
 * currently selected search or location filters. Only profiles below 90%
 * completion are included, and deleted rows are excluded.
 */
function admin_hospitals_get_improved_count(): int
{
    global $pdo;

    static $count = null;

    if ($count !== null) {
        return $count;
    }

    $count = 0;

    if (
        !admin_hospitals_table_exists('hospitals') ||
        !admin_hospitals_column_exists('hospitals', 'id')
    ) {
        return $count;
    }

    $completion_score_sql = admin_hospitals_profile_completion_sql();

    if ($completion_score_sql === '0') {
        return $count;
    }

    $where = ["({$completion_score_sql}) < 90"];

    if (admin_hospitals_column_exists('hospitals', 'status')) {
        $where[] = "(h.`status` IS NULL OR h.`status` <> 'deleted')";
    }

    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM `hospitals` h
            WHERE " . implode(' AND ', $where));

        $stmt->execute();
        $count = (int) $stmt->fetchColumn();
    } catch (Throwable $e) {
        error_log('Hospital improved count query failed: ' . $e->getMessage());
    }

    return $count;
}

/**
 * Get dropdown items safely.
 */
function admin_hospitals_get_items(
    string $table,
    ?string $name_column = null,
    string $where_sql = '',
    array $params = []
): array {
    global $pdo;

    if (!admin_hospitals_table_exists($table)) {
        return [];
    }

    if (!admin_hospitals_column_exists($table, 'id')) {
        return [];
    }

    if ($name_column === null || $name_column === '') {
        $name_column = admin_hospitals_first_existing_column(
            $table,
            ['name_en', 'name', 'title']
        );
    }

    if (!$name_column || !admin_hospitals_column_exists($table, $name_column)) {
        return [];
    }

    try {
        $quoted_table = admin_hospitals_quote_identifier($table);
        $quoted_name_column = admin_hospitals_quote_identifier($name_column);

        $where_sql = trim($where_sql);
        $status_sql = '';

        if (admin_hospitals_column_exists($table, 'status')) {
            $status_sql = $where_sql === ''
                ? "WHERE (`status` = 'active' OR `status` IS NULL)"
                : "AND (`status` = 'active' OR `status` IS NULL)";
        }

        $stmt = $pdo->prepare("
            SELECT
                `id`,
                {$quoted_name_column} AS name
            FROM {$quoted_table}
            {$where_sql}
            {$status_sql}
            ORDER BY {$quoted_name_column} ASC
        ");

        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        error_log('Hospital dropdown query failed: ' . $e->getMessage());

        return [];
    }
}

/**
 * Safe return URL after delete.
 */
function admin_hospitals_safe_return_url(string $return_url): string
{
    $return_url = trim($return_url);

    if ($return_url === '') {
        return 'hospitals.php';
    }

    $parts = parse_url($return_url);

    if ($parts === false) {
        return 'hospitals.php';
    }

    if (!empty($parts['scheme']) || !empty($parts['host'])) {
        return 'hospitals.php';
    }

    $path = $parts['path'] ?? '';

    if ($path !== 'hospitals.php' && basename($path) !== 'hospitals.php') {
        return 'hospitals.php';
    }

    return $return_url;
}

/**
 * Build pagination URL.
 */
function admin_hospitals_page_url(int $page): string
{
    $query_params = $_GET;
    $query_params['page'] = max(1, $page);

    return 'hospitals.php?' . http_build_query($query_params);
}

/**
 * Current page URL with active filter values.
 */
$current_return_url = 'hospitals.php' . (
    !empty($_SERVER['QUERY_STRING'])
        ? '?' . $_SERVER['QUERY_STRING']
        : ''
);

/**
 * CSRF token for delete action.
 */
if (empty($_SESSION['hospital_delete_csrf'])) {
    $_SESSION['hospital_delete_csrf'] = bin2hex(random_bytes(32));
}

/**
 * Delete hospital.
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_hospital'])) {
    $hospital_id = isset($_POST['hospital_id']) ? (int) $_POST['hospital_id'] : 0;
    $csrf_token = $_POST['csrf_token'] ?? '';
    $return_url = admin_hospitals_safe_return_url($_POST['return_url'] ?? 'hospitals.php');

    if (!hash_equals($_SESSION['hospital_delete_csrf'], $csrf_token)) {
        $_SESSION['flash_error'] = 'Invalid security token. Please try again.';

        header('Location: ' . $return_url);
        exit;
    }

    if ($hospital_id <= 0) {
        $_SESSION['flash_error'] = 'Invalid hospital ID.';

        header('Location: ' . $return_url);
        exit;
    }

    if (!admin_hospitals_table_exists('hospitals')) {
        $_SESSION['flash_error'] = 'Hospitals table was not found.';

        header('Location: ' . $return_url);
        exit;
    }

    try {
        $stmt = $pdo->prepare("
            DELETE FROM hospitals
            WHERE id = :id
        ");

        $stmt->execute([
            ':id' => $hospital_id,
        ]);

        $_SESSION['flash_success'] = 'Hospital deleted successfully.';
    } catch (Throwable $e) {
        error_log('Hospital delete failed: ' . $e->getMessage());

        $_SESSION['flash_error'] = 'Hospital could not be deleted.';
    }

    header('Location: ' . $return_url);
    exit;
}

/**
 * Filter values.
 */
$search = trim($_GET['search'] ?? '');

$division_id = isset($_GET['division_id'])
    ? (int) $_GET['division_id']
    : 0;

$district_id = isset($_GET['district_id'])
    ? (int) $_GET['district_id']
    : 0;

$thana_id = isset($_GET['thana_id'])
    ? (int) $_GET['thana_id']
    : 0;

$verified = isset($_GET['verified']) && in_array($_GET['verified'], ['0', '1'], true)
    ? $_GET['verified']
    : '';

$featured = isset($_GET['featured']) && in_array($_GET['featured'], ['0', '1'], true)
    ? $_GET['featured']
    : '';

$improved = isset($_GET['improved']) && $_GET['improved'] === '1';

/**
 * Pagination values.
 */
$page = max(1, (int) ($_GET['page'] ?? 1));
$per_page = 30;
$offset = ($page - 1) * $per_page;

/**
 * Detect common database naming styles.
 */
$hospital_name_column = admin_hospitals_first_existing_column(
    'hospitals',
    ['name', 'name_en', 'hospital_name', 'title']
);

$division_name_column = admin_hospitals_first_existing_column(
    'divisions',
    ['name_en', 'name', 'title']
);

$district_name_column = admin_hospitals_first_existing_column(
    'districts',
    ['name_en', 'name', 'title']
);

$thana_name_column = admin_hospitals_first_existing_column(
    'thanas',
    ['name_en', 'name', 'title']
);

/**
 * Dynamic divisions.
 */
$divisions = admin_hospitals_get_items(
    'divisions',
    $division_name_column
);

/**
 * Dynamic districts by division.
 */
$districts = [];

if ($division_id > 0) {
    $districts = admin_hospitals_get_items(
        'districts',
        $district_name_column,
        'WHERE `division_id` = :division_id',
        [
            ':division_id' => $division_id,
        ]
    );
}

/**
 * Reset invalid district.
 */
if ($district_id > 0 && !empty($districts)) {
    $valid_district_ids = array_map(
        'intval',
        array_column($districts, 'id')
    );

    if (!in_array($district_id, $valid_district_ids, true)) {
        $district_id = 0;
        $thana_id = 0;
    }
}

/**
 * Dynamic thanas by district.
 */
$thanas = [];

if ($district_id > 0) {
    $thanas = admin_hospitals_get_items(
        'thanas',
        $thana_name_column,
        'WHERE `district_id` = :district_id',
        [
            ':district_id' => $district_id,
        ]
    );
}

/**
 * Reset invalid thana.
 */
if ($thana_id > 0 && !empty($thanas)) {
    $valid_thana_ids = array_map(
        'intval',
        array_column($thanas, 'id')
    );

    if (!in_array($thana_id, $valid_thana_ids, true)) {
        $thana_id = 0;
    }
}

/**
 * Prepare data variables.
 */
$hospitals = [];
$total_filtered_hospitals = 0;
$total_pages = 1;
$load_error = '';
$improved_count = 0;

$hospitals_table_ready = (
    admin_hospitals_table_exists('hospitals')
    && admin_hospitals_column_exists('hospitals', 'id')
);

if (!$hospitals_table_ready) {
    $load_error = 'Hospitals table or its ID column could not be found.';
} else {
    // This total is shown in the Improved button and always represents
    // every hospital profile below 90% completion in the database.
    $improved_count = admin_hospitals_get_improved_count();

    /**
     * Build hospital query.
     */
    $where = [];
    $params = [];

    $completion_score_sql = admin_hospitals_profile_completion_sql();

    $select_parts = [
        'h.*',
        "{$completion_score_sql} AS completion_percentage",
    ];

    $join_parts = [];

    /**
     * Add a universal display alias for hospital name.
     */
    if ($hospital_name_column) {
        $quoted_hospital_name_column = admin_hospitals_quote_identifier($hospital_name_column);

        $select_parts[] = "h.{$quoted_hospital_name_column} AS hospital_display_name";
    }

    /**
     * Division join.
     */
    if (
        admin_hospitals_table_exists('divisions')
        && admin_hospitals_column_exists('hospitals', 'division_id')
        && admin_hospitals_column_exists('divisions', 'id')
        && $division_name_column
    ) {
        $quoted_division_name = admin_hospitals_quote_identifier($division_name_column);

        $select_parts[] = "dv.{$quoted_division_name} AS division_name";
        $join_parts[] = "LEFT JOIN `divisions` dv ON dv.`id` = h.`division_id`";
    }

    /**
     * District join.
     */
    if (
        admin_hospitals_table_exists('districts')
        && admin_hospitals_column_exists('hospitals', 'district_id')
        && admin_hospitals_column_exists('districts', 'id')
        && $district_name_column
    ) {
        $quoted_district_name = admin_hospitals_quote_identifier($district_name_column);

        $select_parts[] = "ds.{$quoted_district_name} AS district_name";
        $join_parts[] = "LEFT JOIN `districts` ds ON ds.`id` = h.`district_id`";
    }

    /**
     * Thana join.
     */
    if (
        admin_hospitals_table_exists('thanas')
        && admin_hospitals_column_exists('hospitals', 'thana_id')
        && admin_hospitals_column_exists('thanas', 'id')
        && $thana_name_column
    ) {
        $quoted_thana_name = admin_hospitals_quote_identifier($thana_name_column);

        $select_parts[] = "th.{$quoted_thana_name} AS thana_name";
        $join_parts[] = "LEFT JOIN `thanas` th ON th.`id` = h.`thana_id`";
    }

    /**
     * Doctors count.
     */
    if (
        admin_hospitals_table_exists('doctors')
        && admin_hospitals_column_exists('doctors', 'hospital_id')
    ) {
        $select_parts[] = '
            (
                SELECT COUNT(*)
                FROM `doctors` d
                WHERE d.`hospital_id` = h.`id`
            ) AS doctors_count
        ';
    } else {
        $select_parts[] = '0 AS doctors_count';
    }

    /**
     * Departments count.
     */
    if (
        admin_hospitals_table_exists('departments')
        && admin_hospitals_column_exists('departments', 'hospital_id')
    ) {
        $select_parts[] = '
            (
                SELECT COUNT(*)
                FROM `departments` dp
                WHERE dp.`hospital_id` = h.`id`
            ) AS departments_count
        ';
    } elseif (
        admin_hospitals_table_exists('hospital_departments')
        && admin_hospitals_column_exists('hospital_departments', 'hospital_id')
    ) {
        $select_parts[] = '
            (
                SELECT COUNT(*)
                FROM `hospital_departments` hd
                WHERE hd.`hospital_id` = h.`id`
            ) AS departments_count
        ';
    } else {
        $select_parts[] = '0 AS departments_count';
    }

    /**
     * Keyword search.
     *
     * Every search field gets a unique placeholder.
     * This fixes the duplicate :search PDO parameter problem.
     */
    if ($search !== '') {
        $searchable_columns = array_unique([
            $hospital_name_column,
            'name',
            'name_bn',
            'name_en',
            'hospital_name',
            'type',
            'type_bn',
            'phone',
            'email',
            'whatsapp',
            'emergency_phone',
            'ambulance_phone',
            'address',
            'address_bn',
            'area',
            'area_bn',
            'road_no',
            'road_no_bn',
            'house_no',
            'house_no_bn',
            'post_code',
            'license_number',
            'website_url',
            'slug',
        ]);

        $search_conditions = [];
        $search_index = 0;

        foreach ($searchable_columns as $column) {
            if (
                !empty($column)
                && admin_hospitals_column_exists('hospitals', $column)
            ) {
                $quoted_column = admin_hospitals_quote_identifier($column);
                $placeholder = ':keyword_' . $search_index;

                $search_conditions[] = "h.{$quoted_column} LIKE {$placeholder}";
                $params[$placeholder] = '%' . $search . '%';

                $search_index++;
            }
        }

        if (!empty($search_conditions)) {
            $where[] = '(' . implode(' OR ', $search_conditions) . ')';
        }
    }

    /**
     * Division filter.
     */
    if (
        $division_id > 0
        && admin_hospitals_column_exists('hospitals', 'division_id')
    ) {
        $where[] = 'h.`division_id` = :division_id';
        $params[':division_id'] = $division_id;
    }

    /**
     * District filter.
     */
    if (
        $district_id > 0
        && admin_hospitals_column_exists('hospitals', 'district_id')
    ) {
        $where[] = 'h.`district_id` = :district_id';
        $params[':district_id'] = $district_id;
    }

    /**
     * Thana filter.
     */
    if (
        $thana_id > 0
        && admin_hospitals_column_exists('hospitals', 'thana_id')
    ) {
        $where[] = 'h.`thana_id` = :thana_id';
        $params[':thana_id'] = $thana_id;
    }

    /**
     * Verified filter.
     */
    if (
        $verified !== ''
        && admin_hospitals_column_exists('hospitals', 'is_verified')
    ) {
        $where[] = 'h.`is_verified` = :verified';
        $params[':verified'] = (int) $verified;
    }

    /**
     * Featured filter.
     */
    if (
        $featured !== ''
        && admin_hospitals_column_exists('hospitals', 'is_featured')
    ) {
        $where[] = 'h.`is_featured` = :featured';
        $params[':featured'] = (int) $featured;
    }

    /**
     * Improved filter.
     * Show only hospitals whose profile completion is below 90%.
     */
    if ($improved) {
        $where[] = "({$completion_score_sql}) < 90";
    }

    /**
     * Exclude only deleted hospitals.
     * Includes rows where status is NULL.
     */
    if (admin_hospitals_column_exists('hospitals', 'status')) {
        $where[] = "(h.`status` IS NULL OR h.`status` <> 'deleted')";
    }

    $select_sql = implode(",\n            ", $select_parts);
    $join_sql = !empty($join_parts)
        ? implode("\n        ", $join_parts)
        : '';

    $where_sql = !empty($where)
        ? 'WHERE ' . implode(' AND ', $where)
        : '';

    /**
     * Improved list order:
     * lowest completion first, then newest ID for matching percentages.
     * Example: 1%, 2%, 3% ... 89%.
     */
    $order_sql = $improved
        ? 'completion_percentage ASC, h.`id` DESC'
        : 'h.`id` DESC';

    /**
     * Count filtered hospitals.
     */
    try {
        $count_stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM `hospitals` h
            {$join_sql}
            {$where_sql}
        ");

        foreach ($params as $key => $value) {
            $count_stmt->bindValue($key, $value);
        }

        $count_stmt->execute();

        $total_filtered_hospitals = (int) $count_stmt->fetchColumn();
    } catch (Throwable $e) {
        error_log('Hospital count query failed: ' . $e->getMessage());

        $load_error = 'Hospital list could not be loaded. Please check database columns.';
    }

    /**
     * Calculate pagination.
     */
    if ($load_error === '') {
        $total_pages = max(
            1,
            (int) ceil($total_filtered_hospitals / $per_page)
        );

        $page = min($page, $total_pages);
        $offset = ($page - 1) * $per_page;

        /**
         * Load hospital list.
         */
        try {
            $stmt = $pdo->prepare("
                SELECT
                    {$select_sql}
                FROM `hospitals` h
                {$join_sql}
                {$where_sql}
                ORDER BY {$order_sql}
                LIMIT :limit OFFSET :offset
            ");

            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }

            $stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

            $stmt->execute();

            $hospitals = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            error_log('Hospital list query failed: ' . $e->getMessage());

            $hospitals = [];
            $load_error = 'Hospital list could not be loaded. Please check database columns.';
        }
    }
}

/**
 * Read and clear flash messages.
 */
$flash_success = $_SESSION['flash_success'] ?? '';
$flash_error = $_SESSION['flash_error'] ?? '';

unset($_SESSION['flash_success'], $_SESSION['flash_error']);

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
</style>

<h1 style="margin-bottom:18px;color:#0f172a;">Hospitals</h1>

<?php if ($flash_success !== ''): ?>
    <div style="margin-bottom:16px;padding:12px 15px;border-radius:10px;background:#dcfce7;color:#166534;border:1px solid #86efac;">
        <?= e($flash_success) ?>
    </div>
<?php endif; ?>

<?php if ($flash_error !== ''): ?>
    <div style="margin-bottom:16px;padding:12px 15px;border-radius:10px;background:#fee2e2;color:#991b1b;border:1px solid #fecaca;">
        <?= e($flash_error) ?>
    </div>
<?php endif; ?>

<?php if ($load_error !== ''): ?>
    <div style="margin-bottom:16px;padding:12px 15px;border-radius:10px;background:#fee2e2;color:#991b1b;border:1px solid #fecaca;">
        <?= e($load_error) ?>
    </div>
<?php endif; ?>

<div style="margin-bottom:16px;display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
    <a href="hospital-form.php" class="btn btn-primary">Add Hospital</a>

    <a href="import-hospitals.php" class="btn btn-outline">Import Hospitals</a>

    <a
        href="hospitals.php?featured=1"
        class="btn <?= $featured === '1' && !$improved ? 'btn-primary' : 'btn-outline' ?>"
    >
        Featured
    </a>

    <a
        href="hospitals.php?improved=1"
        class="btn <?= $improved ? 'btn-primary' : 'btn-outline' ?>"
        style="display:inline-flex;align-items:center;gap:7px;"
        title="<?= e((string) $improved_count) ?> hospitals need improvement: completion below 90%, lowest first"
    >
        <span>Improved (<?= e((string) $improved_count) ?>)</span>
        <span style="padding:2px 7px;border-radius:999px;background:#dbeafe;color:#1d4ed8;font-size:11px;font-weight:700;line-height:1.2;">
            New
        </span>
    </a>
</div>

<form method="GET" action="hospitals.php" style="margin-bottom:18px;" id="hospitalFilterForm">
    <div style="background:#f8fafc;border:1px solid #e2e8f0;padding:16px;border-radius:14px;">

        <div style="display:grid;grid-template-columns:repeat(6,minmax(150px,1fr));gap:12px;margin-bottom:12px;">

            <input
                type="text"
                name="search"
                value="<?= e($search) ?>"
                placeholder="Keyword search..."
                style="padding:11px 13px;border:1px solid #cbd5e1;border-radius:10px;font-size:14px;outline:none;width:100%;"
            >

            <select
                name="division_id"
                id="divisionFilter"
                style="padding:11px 13px;border:1px solid #cbd5e1;border-radius:10px;font-size:14px;outline:none;width:100%;"
            >
                <option value="0">All Divisions</option>

                <?php foreach ($divisions as $division): ?>
                    <option
                        value="<?= e((string) $division['id']) ?>"
                        <?= $division_id === (int) $division['id'] ? 'selected' : '' ?>
                    >
                        <?= e($division['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <select
                name="district_id"
                id="districtFilter"
                style="padding:11px 13px;border:1px solid #cbd5e1;border-radius:10px;font-size:14px;outline:none;width:100%;"
                <?= $division_id <= 0 ? 'disabled' : '' ?>
            >
                <option value="0">All Districts</option>

                <?php foreach ($districts as $district): ?>
                    <option
                        value="<?= e((string) $district['id']) ?>"
                        <?= $district_id === (int) $district['id'] ? 'selected' : '' ?>
                    >
                        <?= e($district['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <select
                name="thana_id"
                id="thanaFilter"
                style="padding:11px 13px;border:1px solid #cbd5e1;border-radius:10px;font-size:14px;outline:none;width:100%;"
                <?= $district_id <= 0 ? 'disabled' : '' ?>
            >
                <option value="0">All Thanas</option>

                <?php foreach ($thanas as $thana): ?>
                    <option
                        value="<?= e((string) $thana['id']) ?>"
                        <?= $thana_id === (int) $thana['id'] ? 'selected' : '' ?>
                    >
                        <?= e($thana['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <select
                name="verified"
                style="padding:11px 13px;border:1px solid #cbd5e1;border-radius:10px;font-size:14px;outline:none;width:100%;"
            >
                <option value="">All Verified</option>
                <option value="1" <?= $verified === '1' ? 'selected' : '' ?>>Verified</option>
                <option value="0" <?= $verified === '0' ? 'selected' : '' ?>>Not Verified</option>
            </select>

            <select
                name="featured"
                style="padding:11px 13px;border:1px solid #cbd5e1;border-radius:10px;font-size:14px;outline:none;width:100%;"
            >
                <option value="">All Featured</option>
                <option value="1" <?= $featured === '1' ? 'selected' : '' ?>>Featured</option>
                <option value="0" <?= $featured === '0' ? 'selected' : '' ?>>Not Featured</option>
            </select>

        </div>

        <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
            <button type="submit" class="btn btn-primary">
                Filter Search
            </button>

            <a href="hospitals.php" class="btn btn-outline">
                Reset
            </a>

            <span style="color:#475569;font-size:14px;">
                Showing <strong><?= count($hospitals) ?></strong>
                of <strong><?= $total_filtered_hospitals ?></strong> hospitals
            </span>

            <?php if ($improved): ?>
                <span style="color:#b45309;font-size:14px;font-weight:700;">
                    <?= e((string) $improved_count) ?> profiles need improvement • Below 90% completion • Lowest first
                </span>
            <?php endif; ?>

            <?php if ($total_pages > 1): ?>
                <span style="color:#64748b;font-size:14px;">
                    Page <strong><?= e((string) $page) ?></strong>
                    of <strong><?= e((string) $total_pages) ?></strong>
                </span>
            <?php endif; ?>
        </div>

    </div>
</form>

<table class="admin-table">
    <thead>
        <tr>
            <th>Name</th>
            <th>Division</th>
            <th>District</th>
            <th>Thana</th>
            <th>Departments</th>
            <th>Doctors</th>
            <th>Verified</th>
            <th>Featured</th>
            <th>Views</th>
            <th>Completion</th>
            <th>Actions</th>
        </tr>
    </thead>

    <tbody>
        <?php if (!empty($hospitals)): ?>
            <?php foreach ($hospitals as $hospital): ?>

                <?php
                $hospital_name = admin_hospitals_get_row_value(
                    $hospital,
                    ['hospital_display_name', 'name', 'name_en', 'hospital_name', 'title']
                );

                $division_name = admin_hospitals_get_row_value(
                    $hospital,
                    ['division_name', 'division']
                );

                $district_name = admin_hospitals_get_row_value(
                    $hospital,
                    ['district_name', 'district', 'city']
                );

                $thana_name = admin_hospitals_get_row_value(
                    $hospital,
                    ['thana_name', 'thana', 'area_locality']
                );

                $completion_percentage = (float) ($hospital['completion_percentage'] ?? 0);
                $completion_percentage = max(0, min(100, $completion_percentage));

                $completion_label = rtrim(
                    rtrim(number_format($completion_percentage, 1, '.', ''), '0'),
                    '.'
                );

                $completion_width = number_format($completion_percentage, 2, '.', '');
                $completion_color = $completion_percentage >= 90 ? '#16a34a' : '#f59e0b';
                $completion_text = $completion_percentage >= 90 ? 'Complete' : 'Needs work';
                ?>

                <tr>
                    <td><?= e($hospital_name) ?></td>
                    <td><?= e($division_name) ?></td>
                    <td><?= e($district_name) ?></td>
                    <td><?= e($thana_name) ?></td>
                    <td><?= e((string) ($hospital['departments_count'] ?? 0)) ?></td>
                    <td><?= e((string) ($hospital['doctors_count'] ?? 0)) ?></td>
                    <td><?= !empty($hospital['is_verified']) ? 'Yes' : 'No' ?></td>
                    <td><?= !empty($hospital['is_featured']) ? 'Yes' : 'No' ?></td>
                    <td><?= number_format((int)($hospital['views_count'] ?? 0)) ?></td>

                    <td style="min-width:145px;">
                        <div style="display:grid;gap:6px;">
                            <div style="display:flex;justify-content:space-between;gap:8px;font-size:12px;">
                                <strong><?= e((string) $completion_label) ?>%</strong>

                                <span style="color:<?= e($completion_color) ?>;font-weight:700;">
                                    <?= e($completion_text) ?>
                                </span>
                            </div>

                            <div style="height:7px;background:#e2e8f0;border-radius:999px;overflow:hidden;">
                                <span
                                    style="
                                        display:block;
                                        width:<?= e($completion_width) ?>%;
                                        height:100%;
                                        border-radius:999px;
                                        background:<?= e($completion_color) ?>;
                                    "
                                ></span>
                            </div>
                        </div>
                    </td>

                    <td>
                        <div class="admin-icon-actions">

                            <a
                                class="admin-icon-btn"
                                href="hospital-form.php?id=<?= e((string) ($hospital['id'] ?? '')) ?>"
                                title="Edit"
                            >
                                <i class="fa fa-pencil"></i>
                            </a>

                            <?php if (!empty($hospital['slug']) && function_exists('site_url')): ?>
                                <a
                                    class="admin-icon-btn"
                                    href="<?= e(site_url('hospital/' . $hospital['slug'])) ?>"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    title="View"
                                >
                                    <i class="fa fa-eye"></i>
                                </a>

                            <?php elseif (!empty($hospital['slug'])): ?>
                                <a
                                    class="admin-icon-btn"
                                    href="../hospital/<?= e($hospital['slug']) ?>"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    title="View"
                                >
                                    <i class="fa fa-eye"></i>
                                </a>

                            <?php else: ?>
                                <a
                                    class="admin-icon-btn"
                                    href="hospital-form.php?id=<?= e((string) ($hospital['id'] ?? '')) ?>"
                                    title="View"
                                >
                                    <i class="fa fa-eye"></i>
                                </a>
                            <?php endif; ?>

                            <form
                                method="POST"
                                action="hospitals.php"
                                style="display:inline;"
                                onsubmit="return confirm('Delete this hospital?');"
                            >
                                <input
                                    type="hidden"
                                    name="csrf_token"
                                    value="<?= e($_SESSION['hospital_delete_csrf']) ?>"
                                >

                                <input
                                    type="hidden"
                                    name="hospital_id"
                                    value="<?= e((string) ($hospital['id'] ?? '')) ?>"
                                >

                                <input
                                    type="hidden"
                                    name="return_url"
                                    value="<?= e($current_return_url) ?>"
                                >

                                <button
                                    type="submit"
                                    name="delete_hospital"
                                    class="admin-icon-btn danger"
                                    title="Delete"
                                >
                                    <i class="fa fa-trash-o"></i>
                                </button>
                            </form>

                        </div>
                    </td>
                </tr>

            <?php endforeach; ?>

        <?php else: ?>
            <tr>
                <td colspan="11" style="text-align:center;padding:20px;color:#64748b;">
                    No hospitals found for your filter.
                </td>
            </tr>
        <?php endif; ?>
    </tbody>
</table>

<?php if ($total_pages > 1): ?>
    <div style="margin-top:18px;display:flex;gap:8px;align-items:center;flex-wrap:wrap;">

        <?php if ($page > 1): ?>
            <a
                href="<?= e(admin_hospitals_page_url($page - 1)) ?>"
                class="btn btn-outline small-btn"
            >
                Previous
            </a>
        <?php endif; ?>

        <?php
        $start_page = max(1, $page - 2);
        $end_page = min($total_pages, $page + 2);
        ?>

        <?php if ($start_page > 1): ?>
            <a
                href="<?= e(admin_hospitals_page_url(1)) ?>"
                class="btn btn-outline small-btn"
            >
                1
            </a>

            <?php if ($start_page > 2): ?>
                <span style="padding:8px;color:#64748b;">...</span>
            <?php endif; ?>
        <?php endif; ?>

        <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
            <a
                href="<?= e(admin_hospitals_page_url($i)) ?>"
                class="btn <?= $i === $page ? 'btn-primary' : 'btn-outline' ?> small-btn"
            >
                <?= e((string) $i) ?>
            </a>
        <?php endfor; ?>

        <?php if ($end_page < $total_pages): ?>
            <?php if ($end_page < $total_pages - 1): ?>
                <span style="padding:8px;color:#64748b;">...</span>
            <?php endif; ?>

            <a
                href="<?= e(admin_hospitals_page_url($total_pages)) ?>"
                class="btn btn-outline small-btn"
            >
                <?= e((string) $total_pages) ?>
            </a>
        <?php endif; ?>

        <?php if ($page < $total_pages): ?>
            <a
                href="<?= e(admin_hospitals_page_url($page + 1)) ?>"
                class="btn btn-outline small-btn"
            >
                Next
            </a>
        <?php endif; ?>

        <span style="color:#64748b;font-size:14px;margin-left:6px;">
            Page <?= e((string) $page) ?> of <?= e((string) $total_pages) ?>
        </span>

    </div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('hospitalFilterForm');
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