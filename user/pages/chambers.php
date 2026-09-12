<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

$user = require_user_login();

$type = user_profile_type($user);
$is_doctor = $type === 'doctor';
$is_hospital = $type === 'hospital';

$doctor_id = (int)($user['claimed_doctor_id'] ?? 0);
$hospital_id = (int)($user['claimed_hospital_id'] ?? 0);

$form_message = '';
$form_message_type = '';
$error = '';

if (!user_has_claim($user)) {
    $error = 'Your profile claim must be approved before adding, updating, or deleting chambers.';
}

if ($is_doctor && $doctor_id <= 0) {
    $error = 'No approved doctor profile found for this account.';
}

if ($is_hospital && $hospital_id <= 0) {
    $error = 'No approved hospital profile found for this account.';
}

if (!$is_doctor && !$is_hospital) {
    $error = 'Invalid user profile type.';
}

/*
|--------------------------------------------------------------------------
| Flash Message
|--------------------------------------------------------------------------
*/

if (!empty($_SESSION['chamber_flash_message'])) {
    $form_message_type = $_SESSION['chamber_flash_type'] ?? 'success';
    $form_message = $_SESSION['chamber_flash_message'];

    unset($_SESSION['chamber_flash_type'], $_SESSION['chamber_flash_message']);
}

function user_chamber_flash(string $type, string $message): void
{
    $_SESSION['chamber_flash_type'] = $type;
    $_SESSION['chamber_flash_message'] = $message;
    redirect('chambers.php');
}

/*
|--------------------------------------------------------------------------
| Chambers Page Helpers
|--------------------------------------------------------------------------
*/

function user_chamber_table_exists(string $table): bool
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

        return (int)$stmt->fetchColumn() > 0;
    } catch (Throwable $e) {
        return false;
    }
}

function user_chamber_column_exists(string $table, string $column): bool
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

        return (int)$stmt->fetchColumn() > 0;
    } catch (Throwable $e) {
        return false;
    }
}

function user_chamber_label_column(string $table): string
{
    foreach (['name', 'name_en', 'title'] as $column) {
        if (user_chamber_column_exists($table, $column)) {
            return $column;
        }
    }

    return 'id';
}

function user_chamber_options(string $table): array
{
    global $pdo;

    $allowed = ['divisions', 'districts', 'thanas', 'hospitals'];

    if (!in_array($table, $allowed, true) || !user_chamber_table_exists($table)) {
        return [];
    }

    try {
        $label = user_chamber_label_column($table);
        $where = '';
        $extra = '';

        if ($table === 'districts' && user_chamber_column_exists('districts', 'division_id')) {
            $extra = ', division_id';
        }

        if ($table === 'thanas' && user_chamber_column_exists('thanas', 'district_id')) {
            $extra = ', district_id';
        }

        if ($table === 'hospitals') {
            $parts = [];

            foreach (['division_id', 'district_id', 'thana_id', 'address', 'phone'] as $column) {
                if (user_chamber_column_exists('hospitals', $column)) {
                    $parts[] = $column;
                }
            }

            $extra = $parts ? ', ' . implode(', ', $parts) : '';

            if (user_chamber_column_exists('hospitals', 'status')) {
                $where = "WHERE status = 'active'";
            }
        }

        $stmt = $pdo->query("
            SELECT id, {$label} AS label {$extra}
            FROM {$table}
            {$where}
            ORDER BY {$label} ASC
            LIMIT 3000
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return [];
    }
}

function user_chamber_clean_days(array $days): string
{
    $allowed = ['sat', 'sun', 'mon', 'tue', 'wed', 'thu', 'fri'];
    $clean = [];

    foreach ($days as $day) {
        $day = trim((string)$day);

        if (in_array($day, $allowed, true)) {
            $clean[] = $day;
        }
    }

    return implode(',', array_values(array_unique($clean)));
}

function user_chamber_selected_days(string $days): array
{
    if (trim($days) === '') {
        return [];
    }

    return array_filter(array_map('trim', explode(',', $days)));
}

function user_chamber_get_hospital_name(int $hospital_id): string
{
    global $pdo;

    if ($hospital_id <= 0 || !user_chamber_table_exists('hospitals')) {
        return '';
    }

    try {
        $stmt = $pdo->prepare("SELECT name FROM hospitals WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $hospital_id]);

        return trim((string)$stmt->fetchColumn());
    } catch (Throwable $e) {
        return '';
    }
}

function user_chamber_get_doctor_name(int $doctor_id): string
{
    global $pdo;

    if ($doctor_id <= 0 || !user_chamber_table_exists('doctors')) {
        return '';
    }

    try {
        $stmt = $pdo->prepare("SELECT name FROM doctors WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $doctor_id]);

        return trim((string)$stmt->fetchColumn());
    } catch (Throwable $e) {
        return '';
    }
}

function user_chamber_request_json(array $payload): string
{
    if (function_exists('request_data_json')) {
        return request_data_json($payload);
    }

    return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
}

function user_chamber_duplicate_exists(int $doctor_id, int $hospital_id): bool
{
    global $pdo;

    if ($doctor_id <= 0 || $hospital_id <= 0) {
        return false;
    }

    try {
        if (user_chamber_table_exists('chambers')) {
            $stmt = $pdo->prepare("
                SELECT id
                FROM chambers
                WHERE doctor_id = :doctor_id
                AND hospital_id = :hospital_id
                LIMIT 1
            ");
            $stmt->execute([
                ':doctor_id' => $doctor_id,
                ':hospital_id' => $hospital_id,
            ]);

            if ($stmt->fetchColumn()) {
                return true;
            }
        }

        if (user_chamber_table_exists('profile_update_requests')) {
            $stmt = $pdo->prepare("
                SELECT id
                FROM profile_update_requests
                WHERE request_type = 'chamber_add'
                AND status = 'pending'
                AND doctor_id = :doctor_id
                AND hospital_id = :hospital_id
                LIMIT 1
            ");
            $stmt->execute([
                ':doctor_id' => $doctor_id,
                ':hospital_id' => $hospital_id,
            ]);

            if ($stmt->fetchColumn()) {
                return true;
            }
        }

        return false;
    } catch (Throwable $e) {
        return false;
    }
}

function user_chamber_authorized_chamber(int $chamber_id, bool $is_doctor, bool $is_hospital, int $doctor_id, int $hospital_id): ?array
{
    global $pdo;

    if ($chamber_id <= 0 || !user_chamber_table_exists('chambers')) {
        return null;
    }

    try {
        if ($is_doctor) {
            $stmt = $pdo->prepare("
                SELECT *
                FROM chambers
                WHERE id = :id
                AND doctor_id = :doctor_id
                LIMIT 1
            ");
            $stmt->execute([
                ':id' => $chamber_id,
                ':doctor_id' => $doctor_id,
            ]);
        } else {
            $stmt = $pdo->prepare("
                SELECT *
                FROM chambers
                WHERE id = :id
                AND hospital_id = :hospital_id
                LIMIT 1
            ");
            $stmt->execute([
                ':id' => $chamber_id,
                ':hospital_id' => $hospital_id,
            ]);
        }

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    } catch (Throwable $e) {
        return null;
    }
}

function user_chamber_update_direct(int $chamber_id, array $data): void
{
    global $pdo;

    $allowed = [
        'name',
        'chamber_name',
        'address',
        'address_bn',
        'visiting_hours',
        'visiting_hours_bn',
        'consultation_fee',
        'appointment_phone',
        'serial_phone',
        'assistant_phone',
        'schedule',
        'schedule_bn',
        'available_days',
        'available_from',
        'available_to',
        'is_closed',
        'sort_order',
        'status',
    ];

    $sets = [];
    $params = [':id' => $chamber_id];

    foreach ($allowed as $column) {
        if (array_key_exists($column, $data) && user_chamber_column_exists('chambers', $column)) {
            $sets[] = "`{$column}` = :{$column}";
            $params[':' . $column] = $data[$column];
        }
    }

    if (user_chamber_column_exists('chambers', 'updated_at')) {
        $sets[] = "updated_at = NOW()";
    }

    if (!$sets) {
        throw new RuntimeException('No valid chamber columns found for update.');
    }

    $stmt = $pdo->prepare("
        UPDATE chambers
        SET " . implode(', ', $sets) . "
        WHERE id = :id
        LIMIT 1
    ");

    $stmt->execute($params);
}



function user_chamber_normalize_status($status): string
{
    return strtolower(trim((string)$status)) === 'inactive' ? 'inactive' : 'active';
}

function user_chamber_format_single_time($time): string
{
    $time = trim((string)$time);

    if ($time === '') {
        return '';
    }

    $timestamp = strtotime($time);
    return $timestamp === false ? $time : date('h:i A', $timestamp);
}

function user_chamber_format_time_range($from, $to): string
{
    $from = user_chamber_format_single_time($from);
    $to = user_chamber_format_single_time($to);

    if ($from !== '' && $to !== '') {
        return $from . ' - ' . $to;
    }

    return $from !== '' ? $from : $to;
}

function user_chamber_day_labels(): array
{
    return [
        'sat' => 'Saturday',
        'sun' => 'Sunday',
        'mon' => 'Monday',
        'tue' => 'Tuesday',
        'wed' => 'Wednesday',
        'thu' => 'Thursday',
        'fri' => 'Friday',
    ];
}

function user_chamber_format_days(string $days): string
{
    $labels = user_chamber_day_labels();
    $formatted = [];

    foreach (user_chamber_selected_days($days) as $day) {
        if (isset($labels[$day])) {
            $formatted[] = $labels[$day];
        }
    }

    return implode(', ', $formatted);
}

function user_chamber_fee($fee): string
{
    $fee = (float)$fee;

    if ($fee <= 0) {
        return '';
    }

    return '৳' . rtrim(rtrim(number_format($fee, 2, '.', ''), '0'), '.');
}

function user_chamber_public_hospital_url(int $hospital_id, string $slug = ''): string
{
    $slug = trim($slug);

    if ($slug !== '') {
        return function_exists('site_url')
            ? site_url('hospital/' . rawurlencode($slug))
            : '../hospital/' . rawurlencode($slug);
    }

    return function_exists('site_url')
        ? site_url('hospital-directory/?id=' . $hospital_id)
        : '../hospital-directory/?id=' . $hospital_id;
}

function user_chamber_hospital_details(int $hospital_id): array
{
    global $pdo;

    static $cache = [];

    if (isset($cache[$hospital_id])) {
        return $cache[$hospital_id];
    }

    $result = [
        'id' => $hospital_id,
        'name' => '',
        'slug' => '',
        'address' => '',
        'phone' => '',
        'location' => '',
        'view_url' => user_chamber_public_hospital_url($hospital_id),
    ];

    if ($hospital_id <= 0 || !user_chamber_table_exists('hospitals')) {
        return $result;
    }

    $select = ['h.id'];
    $select[] = user_chamber_column_exists('hospitals', 'name') ? 'h.name' : "CONCAT('Hospital #', h.id) AS name";
    $select[] = user_chamber_column_exists('hospitals', 'slug') ? 'h.slug' : "'' AS slug";
    $select[] = user_chamber_column_exists('hospitals', 'address') ? 'h.address' : "'' AS address";
    $select[] = user_chamber_column_exists('hospitals', 'phone') ? 'h.phone' : "'' AS phone";

    $joins = [];
    $location_parts = [];

    if (user_chamber_column_exists('hospitals', 'district_id') && user_chamber_table_exists('districts')) {
        $joins[] = 'LEFT JOIN districts d ON d.id = h.district_id';
        $district_label = user_chamber_label_column('districts');
        $location_parts[] = 'd.`' . $district_label . '`';
    }

    if (user_chamber_column_exists('hospitals', 'thana_id') && user_chamber_table_exists('thanas')) {
        $joins[] = 'LEFT JOIN thanas t ON t.id = h.thana_id';
        $thana_label = user_chamber_label_column('thanas');
        $location_parts[] = 't.`' . $thana_label . '`';
    }

    $select[] = $location_parts
        ? "CONCAT_WS(', ', " . implode(', ', $location_parts) . ') AS location_name'
        : "'' AS location_name";

    try {
        $stmt = $pdo->prepare(
            'SELECT ' . implode(', ', $select) .
            ' FROM hospitals h ' . implode(' ', $joins) .
            ' WHERE h.id = :id LIMIT 1'
        );
        $stmt->execute([':id' => $hospital_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return $result;
        }

        $result['name'] = trim((string)($row['name'] ?? ''));
        $result['slug'] = trim((string)($row['slug'] ?? ''));
        $result['address'] = trim((string)($row['address'] ?? ''));
        $result['phone'] = trim((string)($row['phone'] ?? ''));
        $result['location'] = trim((string)($row['location_name'] ?? ''));
        $result['view_url'] = user_chamber_public_hospital_url($hospital_id, $result['slug']);
    } catch (Throwable $e) {
        return $result;
    }

    $cache[$hospital_id] = $result;
    return $result;
}

function user_chamber_hospital_owner_can_use_doctor(int $doctor_id, int $hospital_id): bool
{
    global $pdo;

    if ($doctor_id <= 0 || $hospital_id <= 0 || !user_chamber_table_exists('doctors')) {
        return false;
    }

    try {
        $conditions = [];
        $params = [':doctor_id' => $doctor_id];

        if (user_chamber_column_exists('doctors', 'hospital_id')) {
            $conditions[] = 'd.hospital_id = :primary_hospital_id';
            $params[':primary_hospital_id'] = $hospital_id;
        }

        if (user_chamber_table_exists('chambers')) {
            $conditions[] = 'EXISTS (SELECT 1 FROM chambers c WHERE c.doctor_id = d.id AND c.hospital_id = :chamber_hospital_id)';
            $params[':chamber_hospital_id'] = $hospital_id;
        }

        if (!$conditions) {
            return false;
        }

        $stmt = $pdo->prepare(
            'SELECT d.id FROM doctors d WHERE d.id = :doctor_id AND (' . implode(' OR ', $conditions) . ') LIMIT 1'
        );
        $stmt->execute($params);

        return (bool)$stmt->fetchColumn();
    } catch (Throwable $e) {
        return false;
    }
}

function user_chamber_fetch_location_rows(string $table, string $parent_column = '', int $parent_id = 0): array
{
    global $pdo;

    if (!in_array($table, ['divisions', 'districts', 'thanas'], true) || !user_chamber_table_exists($table)) {
        return [];
    }

    $label = user_chamber_label_column($table);
    $sql = "SELECT id, `{$label}` AS name FROM `{$table}`";
    $params = [];
    $where = [];

    if ($parent_column !== '' && $parent_id > 0 && user_chamber_column_exists($table, $parent_column)) {
        $where[] = "`{$parent_column}` = :parent_id";
        $params[':parent_id'] = $parent_id;
    }

    if (user_chamber_column_exists($table, 'status')) {
        $where[] = "(`status` IS NULL OR `status` = '' OR `status` = 'active')";
    }

    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }

    $sql .= " ORDER BY `{$label}` ASC";

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return array_map(static function (array $row): array {
            return [
                'id' => (int)($row['id'] ?? 0),
                'name' => trim((string)($row['name'] ?? '')),
            ];
        }, $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
    } catch (Throwable $e) {
        return [];
    }
}

function user_chamber_search_hospitals(array $filters): array
{
    global $pdo;

    if (!user_chamber_table_exists('hospitals')) {
        return [];
    }

    $select = ['h.id'];
    $select[] = user_chamber_column_exists('hospitals', 'name') ? 'h.name' : "CONCAT('Hospital #', h.id) AS name";
    $select[] = user_chamber_column_exists('hospitals', 'slug') ? 'h.slug' : "'' AS slug";
    $select[] = user_chamber_column_exists('hospitals', 'address') ? 'h.address' : "'' AS address";
    $select[] = user_chamber_column_exists('hospitals', 'phone') ? 'h.phone' : "'' AS phone";

    $joins = [];
    $location_parts = [];

    if (user_chamber_column_exists('hospitals', 'district_id') && user_chamber_table_exists('districts')) {
        $joins[] = 'LEFT JOIN districts d ON d.id = h.district_id';
        $location_parts[] = 'd.`' . user_chamber_label_column('districts') . '`';
    }

    if (user_chamber_column_exists('hospitals', 'thana_id') && user_chamber_table_exists('thanas')) {
        $joins[] = 'LEFT JOIN thanas t ON t.id = h.thana_id';
        $location_parts[] = 't.`' . user_chamber_label_column('thanas') . '`';
    }

    $select[] = $location_parts
        ? "CONCAT_WS(', ', " . implode(', ', $location_parts) . ') AS location_name'
        : "'' AS location_name";

    $where = [];
    $params = [];
    $query = trim((string)($filters['q'] ?? ''));

    if ($query !== '' && user_chamber_column_exists('hospitals', 'name')) {
        $where[] = 'h.name LIKE :q';
        $params[':q'] = '%' . $query . '%';
    }

    foreach ([
        'division_id' => (int)($filters['division_id'] ?? 0),
        'district_id' => (int)($filters['district_id'] ?? 0),
        'thana_id' => (int)($filters['thana_id'] ?? 0),
    ] as $column => $value) {
        if ($value > 0 && user_chamber_column_exists('hospitals', $column)) {
            $where[] = 'h.`' . $column . '` = :' . $column;
            $params[':' . $column] = $value;
        }
    }

    if (user_chamber_column_exists('hospitals', 'status')) {
        $where[] = "(h.status IS NULL OR h.status = '' OR h.status = 'active')";
    }

    $limit = max(1, min(100, (int)($filters['limit'] ?? 50)));
    $sql = 'SELECT ' . implode(', ', $select) . ' FROM hospitals h ' . implode(' ', $joins);

    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }

    $order_column = user_chamber_column_exists('hospitals', 'name') ? 'h.name' : 'h.id';
    $sql .= ' ORDER BY ' . $order_column . ' ASC LIMIT ' . $limit;

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return array_map(static function (array $row): array {
            $id = (int)($row['id'] ?? 0);
            $slug = trim((string)($row['slug'] ?? ''));
            $location = trim((string)($row['location_name'] ?? ''));
            $address = trim((string)($row['address'] ?? ''));
            $phone = trim((string)($row['phone'] ?? ''));

            return [
                'id' => $id,
                'name' => trim((string)($row['name'] ?? 'Hospital')),
                'location' => implode(' | ', array_values(array_filter([$location, $address, $phone]))),
                'address' => $address,
                'phone' => $phone,
                'view_url' => user_chamber_public_hospital_url($id, $slug),
            ];
        }, $rows);
    } catch (Throwable $e) {
        return [];
    }
}

function user_chamber_json(array $payload): void
{
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }

    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$chamber_ajax_action = trim((string)($_GET['chamber_ajax'] ?? ''));

if ($chamber_ajax_action !== '') {
    if ($error !== '') {
        user_chamber_json(['success' => false, 'message' => $error]);
    }

    if ($chamber_ajax_action === 'districts') {
        user_chamber_json(user_chamber_fetch_location_rows('districts', 'division_id', (int)($_GET['division_id'] ?? 0)));
    }

    if ($chamber_ajax_action === 'thanas') {
        user_chamber_json(user_chamber_fetch_location_rows('thanas', 'district_id', (int)($_GET['district_id'] ?? 0)));
    }

    if ($chamber_ajax_action === 'hospitals') {
        user_chamber_json([
            'success' => true,
            'items' => $is_hospital
                ? [user_chamber_hospital_details($hospital_id)]
                : user_chamber_search_hospitals($_GET),
        ]);
    }

    user_chamber_json(['success' => false, 'message' => 'Invalid AJAX action.']);
}


/*
|--------------------------------------------------------------------------
| Load Select Data
|--------------------------------------------------------------------------
*/

$divisions = user_chamber_options('divisions');

/*
|--------------------------------------------------------------------------
| Submit Add / Direct Update / Delete
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $error === '') {
    if (!function_exists('verify_csrf_token') || !verify_csrf_token()) {
        user_chamber_flash('error', 'Your session token is invalid or expired. Please try again.');
    }

    $form_action = trim((string)($_POST['form_action'] ?? ''));

    if ($form_action === 'save_chamber') {
        $submitted_doctor_id = $is_doctor ? $doctor_id : (int)($_POST['doctor_id'] ?? 0);
        $submitted_hospital_id = $is_hospital ? $hospital_id : (int)($_POST['chamber_hospital_id'] ?? 0);

        if ($submitted_doctor_id <= 0) {
            user_chamber_flash('error', 'Please select a doctor.');
        }

        if ($submitted_hospital_id <= 0) {
            user_chamber_flash('error', 'Please select a hospital.');
        }

        if ($is_hospital && !user_chamber_hospital_owner_can_use_doctor($submitted_doctor_id, $hospital_id)) {
            user_chamber_flash('error', 'You cannot add a chamber for the selected doctor.');
        }

        if (user_chamber_duplicate_exists($submitted_doctor_id, $submitted_hospital_id)) {
            $doctor_name = user_chamber_get_doctor_name($submitted_doctor_id);
            $hospital_name = user_chamber_get_hospital_name($submitted_hospital_id);

            user_chamber_flash(
                'error',
                'This chamber already exists or has a pending request for ' .
                ($doctor_name !== '' ? $doctor_name : 'this doctor') .
                ' at ' .
                ($hospital_name !== '' ? $hospital_name : 'this hospital') .
                '. Duplicate chamber is not allowed.'
            );
        }

        $available_days = $_POST['available_days'] ?? [];

        if (!is_array($available_days)) {
            $available_days = [];
        }

        $hospital_name = user_chamber_get_hospital_name($submitted_hospital_id);
        $submitted_chamber_name = trim((string)($_POST['chamber_name'] ?? ''));

        if ($submitted_chamber_name === '') {
            $submitted_chamber_name = $hospital_name !== '' ? $hospital_name : 'Doctor Chamber';
        }

        $schedule = trim((string)($_POST['schedule'] ?? ''));
        $schedule_bn = trim((string)($_POST['schedule_bn'] ?? ''));
        $status = user_chamber_normalize_status($_POST['chamber_status'] ?? 'active');

        $payload = [
            'doctor_id' => $submitted_doctor_id,
            'hospital_id' => $submitted_hospital_id,
            'name' => $submitted_chamber_name,
            'chamber_name' => $submitted_chamber_name,
            'address' => trim((string)($_POST['chamber_address'] ?? '')),
            'address_bn' => trim((string)($_POST['chamber_address_bn'] ?? '')),
            'visiting_hours' => $schedule,
            'visiting_hours_bn' => $schedule_bn,
            'consultation_fee' => max(0, (float)($_POST['chamber_consultation_fee'] ?? 0)),
            'appointment_phone' => trim((string)($_POST['chamber_appointment_phone'] ?? '')),
            'schedule' => $schedule,
            'schedule_bn' => $schedule_bn,
            'available_days' => user_chamber_clean_days($available_days),
            'available_from' => trim((string)($_POST['available_from'] ?? '')),
            'available_to' => trim((string)($_POST['available_to'] ?? '')),
            'is_closed' => isset($_POST['is_closed']) ? 1 : 0,
            'sort_order' => max(0, (int)($_POST['chamber_sort_order'] ?? 0)),
            'status' => $status,
        ];

        try {
            $stmt = $pdo->prepare("
                INSERT INTO profile_update_requests
                (
                    user_id,
                    request_type,
                    doctor_id,
                    hospital_id,
                    chamber_id,
                    request_data,
                    status,
                    created_at
                )
                VALUES
                (
                    :user_id,
                    'chamber_add',
                    :doctor_id,
                    :hospital_id,
                    NULL,
                    :request_data,
                    'pending',
                    NOW()
                )
            ");

            $stmt->execute([
                ':user_id' => (int)$user['id'],
                ':doctor_id' => $submitted_doctor_id,
                ':hospital_id' => $submitted_hospital_id,
                ':request_data' => user_chamber_request_json($payload),
            ]);

            user_chamber_flash('success', 'New chamber request submitted successfully. Admin approval is required.');
        } catch (Throwable $e) {
            user_chamber_flash('error', 'Request could not be saved. Please check profile_update_requests table and request_type enum.');
        }
    }

    if ($form_action === 'update_chamber') {
        $chamber_id = (int)($_POST['chamber_id'] ?? 0);
        $chamber = user_chamber_authorized_chamber($chamber_id, $is_doctor, $is_hospital, $doctor_id, $hospital_id);

        if (!$chamber) {
            user_chamber_flash('error', 'Invalid chamber update request.');
        }

        $available_days = $_POST['available_days'] ?? [];

        if (!is_array($available_days)) {
            $available_days = [];
        }

        $submitted_chamber_name = trim((string)($_POST['chamber_name'] ?? ''));
        $submitted_chamber_name = $submitted_chamber_name !== ''
            ? $submitted_chamber_name
            : (string)($chamber['name'] ?? 'Doctor Chamber');

        $schedule = trim((string)($_POST['schedule'] ?? ''));
        $schedule_bn = trim((string)($_POST['schedule_bn'] ?? ''));

        $update_data = [
            'name' => $submitted_chamber_name,
            'chamber_name' => $submitted_chamber_name,
            'address' => trim((string)($_POST['chamber_address'] ?? '')),
            'address_bn' => trim((string)($_POST['chamber_address_bn'] ?? '')),
            'visiting_hours' => $schedule,
            'visiting_hours_bn' => $schedule_bn,
            'consultation_fee' => max(0, (float)($_POST['chamber_consultation_fee'] ?? 0)),
            'appointment_phone' => trim((string)($_POST['chamber_appointment_phone'] ?? '')),
            'schedule' => $schedule,
            'schedule_bn' => $schedule_bn,
            'available_days' => user_chamber_clean_days($available_days),
            'available_from' => trim((string)($_POST['available_from'] ?? '')),
            'available_to' => trim((string)($_POST['available_to'] ?? '')),
            'is_closed' => isset($_POST['is_closed']) ? 1 : 0,
            'sort_order' => max(0, (int)($_POST['chamber_sort_order'] ?? 0)),
            'status' => user_chamber_normalize_status($_POST['chamber_status'] ?? 'active'),
        ];

        try {
            user_chamber_update_direct($chamber_id, $update_data);
            user_chamber_flash('success', 'Chamber options updated successfully. Admin approval is not required for chamber option updates.');
        } catch (Throwable $e) {
            user_chamber_flash('error', 'Chamber could not be updated. Please check chambers table columns.');
        }
    }

    if ($form_action === 'delete_chamber') {
        $chamber_id = (int)($_POST['chamber_id'] ?? 0);
        $chamber = user_chamber_authorized_chamber($chamber_id, $is_doctor, $is_hospital, $doctor_id, $hospital_id);

        if (!$chamber) {
            user_chamber_flash('error', 'Invalid chamber delete request.');
        }

        try {
            $stmt = $pdo->prepare("DELETE FROM chambers WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => $chamber_id]);

            user_chamber_flash('success', 'Chamber deleted successfully.');
        } catch (Throwable $e) {
            user_chamber_flash('error', 'Chamber could not be deleted.');
        }
    }
}

/*
|--------------------------------------------------------------------------
| Current Chambers / Doctors / Requests
|--------------------------------------------------------------------------
*/

$doctors = [];
$current_chambers = [];
$requests = [];

try {
    if ($is_hospital && $hospital_id > 0 && user_chamber_table_exists('doctors')) {
        $stmt = $pdo->prepare("
            SELECT DISTINCT d.id, d.name
            FROM doctors d
            LEFT JOIN chambers c ON c.doctor_id = d.id
            WHERE d.hospital_id = :primary_hospital_id
               OR c.hospital_id = :chamber_hospital_id
            ORDER BY d.name ASC
            LIMIT 1000
        ");
        $stmt->execute([
            ':primary_hospital_id' => $hospital_id,
            ':chamber_hospital_id' => $hospital_id,
        ]);
        $doctors = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Throwable $e) {
    $doctors = [];
}

try {
    if (user_chamber_table_exists('chambers')) {
        if ($is_doctor) {
            $stmt = $pdo->prepare("
                SELECT c.*, h.name AS hospital_name, d.name AS doctor_name
                FROM chambers c
                LEFT JOIN hospitals h ON h.id = c.hospital_id
                LEFT JOIN doctors d ON d.id = c.doctor_id
                WHERE c.doctor_id = :doctor_id
                ORDER BY c.sort_order ASC, c.id DESC
            ");
            $stmt->execute([':doctor_id' => $doctor_id]);
        } else {
            $stmt = $pdo->prepare("
                SELECT c.*, h.name AS hospital_name, d.name AS doctor_name
                FROM chambers c
                LEFT JOIN hospitals h ON h.id = c.hospital_id
                LEFT JOIN doctors d ON d.id = c.doctor_id
                WHERE c.hospital_id = :hospital_id
                ORDER BY c.sort_order ASC, c.id DESC
            ");
            $stmt->execute([':hospital_id' => $hospital_id]);
        }

        $current_chambers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Throwable $e) {
    $current_chambers = [];
}

try {
    if (user_chamber_table_exists('profile_update_requests')) {
        $stmt = $pdo->prepare("
            SELECT
                r.*,
                d.name AS doctor_name,
                h.name AS hospital_name
            FROM profile_update_requests r
            LEFT JOIN doctors d ON d.id = r.doctor_id
            LEFT JOIN hospitals h ON h.id = r.hospital_id
            WHERE r.user_id = :user_id
            AND r.request_type IN ('chamber_add', 'chamber_update')
            ORDER BY r.id DESC
            LIMIT 30
        ");
        $stmt->execute([':user_id' => (int)$user['id']]);
        $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Throwable $e) {
    $requests = [];
}

$page_title = 'Chambers';
$csrf_token = function_exists('csrf_token') ? csrf_token() : '';
$fixed_hospital = $is_hospital ? user_chamber_hospital_details($hospital_id) : [];

require_once __DIR__ . '/../includes/header.php';
?>

<link rel="stylesheet" href="<?php echo BASE_URL; ?>/user/assets/css/user-chambers.css">

<div class="lite-doctor-page">
  <?php if ($form_message !== ''): ?>
    <div class="lite-alert <?= e($form_message_type) ?>">
      <div class="lite-alert-icon"><?= $form_message_type === 'success' ? '✓' : '!' ?></div>
      <div class="lite-alert-content">
        <h3><?= $form_message_type === 'success' ? 'Success' : 'Error' ?></h3>
        <p><?= e($form_message) ?></p>
      </div>
    </div>
  <?php endif; ?>

  <?php if ($error !== ''): ?>
    <div class="lite-alert error">
      <div class="lite-alert-icon">!</div>
      <div class="lite-alert-content"><h3>Error</h3><p><?= e($error) ?></p></div>
    </div>
  <?php endif; ?>

  <div class="lite-page-hero">
    <div class="lite-page-hero-inner">
      <div>
        <div class="lite-breadcrumb"><a href="dashboard.php">Dashboard</a><span>/</span><span>Chambers</span></div>
        <h1>Hospitals &amp; Availability</h1>
        <p>Add and manage chamber information using the same hospital search, table layout and inline options available in the admin doctor form.</p>
      </div>
      <div class="lite-hero-actions"><a href="dashboard.php" class="lite-btn-link">Back to Dashboard</a></div>
    </div>
  </div>

  <?php if ($error === ''): ?>
    <div class="lite-form-card">
      <div class="lite-form-card-header">
        <h2>Add &amp; Manage Chambers</h2>
        <p><?= $is_hospital ? 'Select a doctor, then add this hospital as a chamber. New chamber requests require admin approval.' : 'Select Division, District, Thana and Hospital Name, then click Add. Hospital search works from the full database.' ?></p>
      </div>

      <div class="lite-admin-form">
        <div class="lite-section">
          <div class="lite-section-title">
            <h3>Add Hospital / Chamber</h3>
            <span><?= $is_hospital ? 'Hospital owner chamber request' : 'Lightweight AJAX hospital search' ?></span>
          </div>

          <form method="POST" class="wp-style-chamber-box" id="addChamberAjaxForm" data-is-hospital-owner="<?= $is_hospital ? 'true' : 'false' ?>" data-fixed-doctor-id="<?= (int)$doctor_id ?>" data-fixed-hospital="<?= json_encode($fixed_hospital, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>" data-csrf-token="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
            <div class="wp-style-chamber-head">
              <?= $is_hospital ? 'Select Doctor → Confirm Hospital → Add' : 'Select Division → District → Thana → Hospital Name' ?>
            </div>

            <div class="wp-style-chamber-body">
              <?php if ($is_hospital): ?>
                <div class="wp-owner-grid">
                  <div>
                    <label>Doctor</label>
                    <select id="hospital_owner_doctor_id">
                      <option value="">— Select Doctor —</option>
                      <?php foreach ($doctors as $doctor_option): ?>
                        <option value="<?= e((string)$doctor_option['id']) ?>"><?= e($doctor_option['name'] ?? '') ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div>
                    <label>Hospital Name</label>
                    <input type="text" value="<?= e($fixed_hospital['name'] ?? '') ?>" readonly>
                  </div>
                  <div><button type="submit" class="wp-admin-button">Add</button></div>
                </div>
                <div class="wp-preview-note" id="doctor_chamber_preview">
                  <?= e(($fixed_hospital['name'] ?? '') !== '' ? ($fixed_hospital['name'] . ' — select a doctor and click Add.') : 'Select a doctor and click Add.') ?>
                </div>
              <?php else: ?>
                <div class="wp-chain-grid">
                  <div>
                    <label>Division</label>
                    <select id="doctor_chamber_division">
                      <option value="">— Select Division —</option>
                      <?php foreach ($divisions as $division): ?>
                        <option value="<?= e((string)$division['id']) ?>"><?= e($division['label'] ?? '') ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div><label>District</label><select id="doctor_chamber_district" disabled><option value="">— Select District —</option></select></div>
                  <div><label>Thana</label><select id="doctor_chamber_thana" disabled><option value="">— All Thanas —</option></select></div>
                  <div>
                    <label>Hospital Name</label>
                    <div class="wp-search-dropdown" id="doctor_chamber_hospital_dropdown">
                      <input type="text" class="wp-search-dropdown-input" id="doctor_chamber_hospital_search" placeholder="— Select or search hospital —" autocomplete="off">
                      <span class="wp-search-dropdown-arrow">▼</span>
                      <input type="hidden" id="doctor_chamber_hospital" value="">
                      <input type="hidden" id="doctor_chamber_hospital_view_url" value="">
                      <input type="hidden" id="doctor_chamber_hospital_address" value="">
                      <input type="hidden" id="doctor_chamber_hospital_location" value="">
                      <div class="wp-search-dropdown-list" id="doctor_chamber_hospital_list"></div>
                    </div>
                  </div>
                  <div><button type="submit" class="wp-admin-button">Add</button></div>
                </div>
                <div class="wp-preview-note" id="doctor_chamber_preview">Select Division → District → Thana → Hospital Name. One hospital can be added only once for the same doctor.</div>
              <?php endif; ?>
            </div>
          </form>

          <div class="lite-chamber-table-wrap">
            <table class="lite-chamber-table">
              <thead>
                <tr><th class="lite-chamber-col-index">#</th><th>Hospital</th><th>Appointment</th><th>Time</th><th>Status</th><th>Actions</th></tr>
              </thead>
              <tbody id="doctorChamberList">
                <?php if ($current_chambers): ?>
                  <?php foreach ($current_chambers as $chamber_index => $chamber): ?>
                    <?php
                      $selected_days = user_chamber_selected_days((string)($chamber['available_days'] ?? ''));
                      $chamber_status = user_chamber_normalize_status($chamber['status'] ?? 'active');
                      $hospital_details = user_chamber_hospital_details((int)($chamber['hospital_id'] ?? 0));
                      $hospital_title = trim((string)($chamber['hospital_name'] ?? $hospital_details['name'] ?? 'Hospital'));
                      $doctor_title = trim((string)($chamber['doctor_name'] ?? user_chamber_get_doctor_name((int)($chamber['doctor_id'] ?? 0))));
                      $chamber_number = $chamber_index + 1;
                      $display_sort_order = (int)($chamber['sort_order'] ?? 0) ?: $chamber_number;
                      $display_time = user_chamber_format_time_range($chamber['available_from'] ?? '', $chamber['available_to'] ?? '');
                      $display_status = $chamber_status === 'active' ? 'Active' : 'Inactive';
                      if (!empty($chamber['is_closed'])) { $display_status .= ' / Closed'; }
                      $subtitle = $is_hospital
                        ? ($doctor_title !== '' ? 'Doctor: ' . $doctor_title : '')
                        : trim((string)($hospital_details['location'] ?? $chamber['address'] ?? ''));
                      $edit_row_id = 'chamberEditRow' . (int)$chamber['id'];
                    ?>
                    <tr class="lite-chamber-entry" data-doctor-id="<?= e((string)($chamber['doctor_id'] ?? 0)) ?>" data-hospital-id="<?= e((string)($chamber['hospital_id'] ?? 0)) ?>" data-sort-order="<?= e((string)$display_sort_order) ?>">
                      <td class="lite-serial-cell"><span class="lite-serial-number"><?= e((string)$chamber_number) ?></span></td>
                      <td><div class="lite-chamber-name-cell"><div class="lite-chamber-name-main"><strong><?= e($hospital_title) ?></strong></div><?php if ($subtitle !== ''): ?><div class="lite-chamber-sub"><?= e($subtitle) ?></div><?php endif; ?></div></td>
                      <td><?= !empty($chamber['appointment_phone']) ? e($chamber['appointment_phone']) : '—' ?></td>
                      <td><?= $display_time !== '' ? e($display_time) : '—' ?></td>
                      <td><span class="lite-status-badge <?= $chamber_status === 'active' ? 'lite-status-active' : 'lite-status-inactive' ?>"><?= e($display_status) ?></span></td>
                      <td>
                        <div class="lite-table-action-group">
                          <button type="button" class="lite-table-btn" data-chamber-edit-toggle="<?= e($edit_row_id) ?>">Edit</button>
                          <div class="lite-chamber-menu-wrap" data-chamber-menu-wrap>
                            <button type="button" class="lite-kebab" data-chamber-menu-toggle aria-label="More actions"><i class="fa fa-ellipsis-v fa-solid fa-ellipsis-vertical"></i></button>
                            <div class="lite-chamber-menu">
                              <a href="<?= e($hospital_details['view_url'] ?? '#') ?>" target="_blank" rel="noopener" class="lite-menu-link"><span class="lite-menu-icon">👁</span><span>View</span></a>
                              <form method="POST" data-confirm-message="Delete this chamber?">
                                <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">
                                <input type="hidden" name="form_action" value="delete_chamber">
                                <input type="hidden" name="chamber_id" value="<?= e((string)$chamber['id']) ?>">
                                <button type="submit" class="lite-menu-button lite-menu-danger"><span class="lite-menu-icon">🗑</span><span>Delete</span></button>
                              </form>
                            </div>
                          </div>
                        </div>
                      </td>
                    </tr>
                    <tr class="lite-chamber-edit-row" id="<?= e($edit_row_id) ?>">
                      <td colspan="6">
                        <form method="POST">
                          <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">
                          <input type="hidden" name="form_action" value="update_chamber">
                          <input type="hidden" name="chamber_id" value="<?= e((string)$chamber['id']) ?>">
                          <div class="wp-schedule-grid">
                            <div><label>Appointment Phone</label><input type="text" name="chamber_appointment_phone" value="<?= e($chamber['appointment_phone'] ?? '') ?>" placeholder="+880..."></div>
                            <div><label>Consultation Fee</label><input type="number" min="0" step="0.01" name="chamber_consultation_fee" value="<?= e((string)($chamber['consultation_fee'] ?? '')) ?>" placeholder="1000"></div>
                            <div><label>From</label><input type="time" name="available_from" value="<?= e($chamber['available_from'] ?? '') ?>"></div>
                            <div><label>To</label><input type="time" name="available_to" value="<?= e($chamber['available_to'] ?? '') ?>"></div>
                            <div><label>Sort Order</label><input type="number" min="0" name="chamber_sort_order" value="<?= e((string)$display_sort_order) ?>"></div>
                            <div><label>Status</label><select name="chamber_status"><option value="active" <?= $chamber_status === 'active' ? 'selected' : '' ?>>Active</option><option value="inactive" <?= $chamber_status === 'inactive' ? 'selected' : '' ?>>Inactive</option></select></div>
                            <div class="wp-field-pair">
                              <div><label>Schedule</label><input type="text" name="schedule" value="<?= e($chamber['schedule'] ?? $chamber['visiting_hours'] ?? '') ?>" placeholder="Saturday to Thursday 3pm to 9pm (Closed: Friday)"></div>
                              <div><label>Schedule Bangla</label><input type="text" name="schedule_bn" value="<?= e($chamber['schedule_bn'] ?? $chamber['visiting_hours_bn'] ?? '') ?>" placeholder="শনিবার থেকে বৃহস্পতিবার বিকাল ৩টা থেকে রাত ৯টা (শুক্রবার বন্ধ)"></div>
                            </div>
                            <div class="wp-field-pair">
                              <div><label>Chamber Address</label><input type="text" name="chamber_address" value="<?= e($chamber['address'] ?? '') ?>" placeholder="Chamber address or room/floor details"></div>
                              <div><label>Chamber Address Bangla</label><input type="text" name="chamber_address_bn" value="<?= e($chamber['address_bn'] ?? '') ?>" placeholder="চেম্বারের ঠিকানা বা রুম/ফ্লোরের তথ্য"></div>
                            </div>
                          </div>
                          <div class="wp-days-row">
                            <?php foreach (user_chamber_day_labels() as $day_key => $day_label): ?>
                              <?php $bn_days = ['sat'=>'শনিবার','sun'=>'রবিবার','mon'=>'সোমবার','tue'=>'মঙ্গলবার','wed'=>'বুধবার','thu'=>'বৃহস্পতিবার','fri'=>'শুক্রবার']; ?>
                              <label class="wp-day-pill"><input type="checkbox" name="available_days[]" value="<?= e($day_key) ?>" <?= in_array($day_key, $selected_days, true) ? 'checked' : '' ?>><span class="wp-day-check"></span><span class="wp-day-text"><?= e($day_label . ' / ' . ($bn_days[$day_key] ?? '')) ?></span></label>
                            <?php endforeach; ?>
                            <label class="wp-day-pill wp-day-pill-closed"><input type="checkbox" name="is_closed" value="1" <?= !empty($chamber['is_closed']) ? 'checked' : '' ?>><span class="wp-day-check"></span><span class="wp-day-text">Closed / বন্ধ</span></label>
                          </div>
                          <div class="lite-edit-form-actions"><button type="submit" class="lite-btn lite-btn-primary">Update Chamber</button><button type="button" class="lite-btn lite-btn-light" data-chamber-edit-cancel>Close</button></div>
                        </form>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php else: ?>
                  <tr id="noChamberMessage"><td colspan="6" class="lite-chamber-empty-cell">No saved chamber yet. Select the required doctor/hospital and click Add to open the option form.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <div class="lite-form-card">
    <div class="lite-form-card-header"><h2>Recent Chamber Requests</h2><p>New chamber requests stay pending until an administrator approves or rejects them.</p></div>
    <div class="lite-admin-form">
      <div class="lite-request-table-wrap">
        <table class="lite-request-table">
          <thead><tr><th>ID</th><th>Type</th><th>Doctor</th><th>Hospital</th><th>Status</th><th>Date</th></tr></thead>
          <tbody>
            <?php if (!$requests): ?><tr><td colspan="6">No chamber request found.</td></tr><?php endif; ?>
            <?php foreach ($requests as $request): ?>
              <tr><td>#<?= e((string)($request['id'] ?? '')) ?></td><td><?= e($request['request_type'] ?? '') ?></td><td><?= e($request['doctor_name'] ?? user_chamber_get_doctor_name((int)($request['doctor_id'] ?? 0))) ?></td><td><?= e($request['hospital_name'] ?? user_chamber_get_hospital_name((int)($request['hospital_id'] ?? 0))) ?></td><td><span class="lite-request-status"><?= e($request['status'] ?? 'pending') ?></span></td><td><?= e($request['created_at'] ?? '') ?></td></tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<script src="<?php echo BASE_URL; ?>/user/assets/js/chambers.js" defer></script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
