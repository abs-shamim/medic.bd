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

<style>

          :root{
            --gh-bg:#f6f8fa;
            --gh-card:#ffffff;
            --gh-text:#24292f;
            --gh-muted:#57606a;
            --gh-border:#d0d7de;
            --gh-soft:#f6f8fa;
            --gh-blue:#0969da;
            --gh-blue-soft:#ddf4ff;
            --gh-green:#1a7f37;
            --gh-red:#cf222e;
            --gh-red-soft:#ffebe9;
          }

          .lite-section{position:relative;padding:24px;border-radius:12px;background:var(--gh-card);border:1px solid var(--gh-border);margin-bottom:20px;box-shadow:0 1px 0 rgba(27,31,36,.04)}
          .lite-section-title{display:flex;align-items:flex-start;justify-content:space-between;gap:14px;margin-bottom:18px;padding-bottom:14px;border-bottom:1px dashed #d8dee4}
          .lite-section-title h3{position:relative;margin:0;padding-left:14px;color:var(--gh-text);font-size:17px;letter-spacing:-.02em}
          .lite-section-title h3:before{content:"";position:absolute;left:0;top:3px;width:5px;height:18px;border-radius:99px;background:var(--gh-blue)}
          .lite-section-title span{color:var(--gh-muted);font-size:13px;line-height:1.5;text-align:right}

          .wp-style-chamber-box,.lite-chamber-card,.lite-auto-box,.wp-preview-note{border-radius:12px;background:#fff;border:1px solid var(--gh-border);box-shadow:0 1px 0 rgba(27,31,36,.04)}
          .wp-style-chamber-head{padding:14px 16px;border-bottom:1px solid var(--gh-border);background:var(--gh-soft);color:var(--gh-text);font-size:14px;font-weight:700!important}
          .wp-style-chamber-body{padding:16px}
          .wp-chain-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr)) auto;gap:11px;align-items:end}
          .wp-chain-grid label,.wp-schedule-grid label{display:block;margin-bottom:6px;color:#344054;font-size:13px;font-weight:600!important}

          .wp-chain-grid input,
          .wp-chain-grid select,
          .wp-schedule-grid input,
          .wp-schedule-grid select{
            width:100%;
            min-height:42px;
            border:1px solid var(--gh-border);
            border-radius:6px;
            padding:10px 12px;
            color:var(--gh-text);
            background:#fff;
            outline:none;
            font-family:inherit;
            font-size:14px;
          }

          .wp-chain-grid input:focus,
          .wp-chain-grid select:focus,
          .wp-schedule-grid input:focus,
          .wp-schedule-grid select:focus{
            border-color:var(--gh-blue);
            box-shadow:0 0 0 3px rgba(9,105,218,.12);
          }

          .wp-schedule-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin-top:14px}
          .wp-field-pair{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;grid-column:1 / -1}
          .wp-field-pair > div{min-width:0}
          .lite-chamber-list{display:grid;gap:14px;margin-top:16px}
          .lite-chamber-table-wrap{
            width:100%;
            overflow-x:auto;
            margin-top:16px;
            border:1px solid var(--gh-border);
            border-radius:12px;
            background:#fff;
          }

          .lite-chamber-table{
            width:100%;
            border-collapse:separate;
            border-spacing:0;
            min-width:760px;
            color:var(--gh-text);
          }

          .lite-chamber-table th{
            padding:13px 14px;
            background:#f6f8fa;
            border-bottom:1px solid var(--gh-border);
            color:#57606a;
            font-size:12px;
            line-height:1.35;
            text-align:left;
            font-weight:800!important;
            white-space:nowrap;
          }

          .lite-chamber-table td{
            padding:14px;
            border-bottom:1px solid #d8dee4;
            vertical-align:middle;
            font-size:13.5px;
            line-height:1.45;
          }

          .lite-chamber-table tbody tr:last-child td{border-bottom:0}
          .lite-chamber-table .lite-chamber-edit-row td{background:#f6f8fa;padding:16px}
          .lite-chamber-table .lite-chamber-edit-row{display:none}
          .lite-chamber-table .lite-chamber-edit-row.is-open{display:table-row}
          .lite-chamber-entry.is-editing [data-chamber-edit-toggle]{visibility:hidden!important;pointer-events:none!important;display:inline-flex!important}

          .lite-chamber-name-cell{display:grid;gap:4px;min-width:220px}
          .lite-chamber-name-main{display:flex;align-items:center;gap:10px;min-width:0}
          .lite-chamber-name-main strong{font-size:14px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
          .lite-chamber-sub{color:var(--gh-muted);font-size:12px;line-height:1.45;max-width:320px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
          .lite-serial-cell{width:70px;text-align:center;color:#24292f;font-size:14px;font-weight:800!important;}
          .lite-serial-number{display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;border-radius:6px;border:1px solid #d0d7de;background:#f6f8fa;color:#24292f;font-weight:800!important;}

          .lite-table-action-group{display:inline-flex;align-items:center;justify-content:flex-start;gap:8px;white-space:nowrap;min-width:104px;}
          .lite-table-btn{
            display:inline-flex!important;
            align-items:center!important;
            justify-content:center!important;
            min-height:34px!important;
            padding:7px 13px!important;
            border:1px solid rgba(27,31,36,.15)!important;
            border-radius:6px!important;
            background:#f6f8fa!important;
            color:#24292f!important;
            font-family:inherit!important;
            font-size:13px!important;
            line-height:1!important;
            font-weight:700!important;
            text-decoration:none!important;
            cursor:pointer!important;
            box-shadow:none!important;
          }
          .lite-table-btn:hover{background:#eef1f4!important;color:#24292f!important}
          .lite-table-btn-delete{background:#f6f8fa!important;color:#cf222e!important;border-color:rgba(27,31,36,.15)!important}
          .lite-table-btn-delete:hover{background:#ffebe9!important;color:#cf222e!important;border-color:#ff818266!important}


          .lite-chamber-entry.is-highlighted {
            animation: lite-chamber-row-highlight 1.6s ease;
          }

          @keyframes lite-chamber-row-highlight {
            0% { background:#fff8c5; }
            100% { background:#fff; }
          }

          .lite-table-action-group .lite-chamber-menu-wrap{position:relative;}
          .lite-table-action-group .lite-chamber-menu{top:32px;right:0;min-width:170px;}
          .lite-table-action-group .lite-kebab{color:#57606a!important;background:transparent!important;}
          .lite-table-action-group .lite-kebab:hover{color:#57606a!important;background:transparent!important;}
          .lite-chamber-empty-cell{padding:16px!important;color:var(--gh-muted)!important;font-size:13px!important}

          .lite-chamber-card{padding:18px}
          .lite-chamber-head{display:flex;justify-content:space-between;gap:14px;align-items:flex-start;margin-bottom:0;padding-bottom:0;border-bottom:0;cursor:pointer}
          .lite-chamber-head h4{margin:0 0 5px;color:var(--gh-text);font-size:16px}
          .lite-chamber-head p{margin:0;color:var(--gh-muted);font-size:13px;line-height:1.6}
          .lite-chamber-options{display:none;margin-top:16px;padding-top:16px;border-top:1px dashed #d8dee4}
          .lite-chamber-card.is-open .lite-chamber-options{display:block}
          .lite-chamber-title-row{
            display:flex;
            align-items:center;
            gap:10px;
            flex-wrap:wrap;
            margin-bottom:6px;
          }

          .lite-chamber-title-row h4{
            margin:0;
          }

          .lite-chamber-badge{
            display:inline-flex;
            align-items:center;
            justify-content:center;
            width:28px;
            height:28px;
            border-radius:6px;
            font-size:14px;
            line-height:1;
            font-weight:800!important;
            border:1px solid #d0d7de;
            background:#f6f8fa;
            color:#24292f;
            white-space:nowrap;
          }

          .chamber-color-1{background:#ddf4ff;color:#0969da;border-color:#54aeff66}
          .chamber-color-2{background:#dafbe1;color:#1a7f37;border-color:#2da44e66}
          .chamber-color-3{background:#fbefff;color:#8250df;border-color:#d8b9ff}
          .chamber-color-4{background:#fff8c5;color:#7d4e00;border-color:#f0d98c}
          .chamber-color-5{background:#ffebe9;color:#cf222e;border-color:#ff818266}
          .chamber-color-6{background:#ddf4ff;color:#0550ae;border-color:#54aeff66}
          .chamber-color-7{background:#dafbe1;color:#116329;border-color:#2da44e66}
          .chamber-color-8{background:#f6f8fa;color:#24292f;border-color:#d0d7de}
          .chamber-color-9{background:#fbefff;color:#6639ba;border-color:#d8b9ff}

          .lite-card-actions{
            position:relative;
            display:flex;
            align-items:flex-start;
            justify-content:flex-end;
            flex:0 0 auto;
          }

          .lite-card-actions form{margin:0;display:block}

          .lite-chamber-menu-wrap{
            position:relative;
            display:inline-flex;
            align-items:center;
            justify-content:center;
          }

          .lite-kebab{
            display:inline-flex;
            align-items:center;
            justify-content:center;
            width:26px;
            height:26px;
            border:0;
            background:transparent;
            color:#57606a;
            padding:0;
            margin:0;
            cursor:pointer;
            font-size:16px;
            line-height:1;
            box-shadow:none;
          }

          .lite-kebab:hover,
          .lite-kebab:focus,
          .lite-kebab:active,
          .lite-chamber-menu-wrap.is-open .lite-kebab{
            color:#57606a !important;
            background:transparent !important;
            border-radius:0 !important;
            box-shadow:none !important;
            transform:none !important;
          }

          .lite-kebab i{
            display:inline-flex;
            align-items:center;
            justify-content:center;
            width:16px;
            height:16px;
            font-size:16px;
            line-height:1;
          }

          .lite-chamber-menu{
            position:absolute;
            top:30px;
            right:0;
            z-index:100;
            display:none;
            min-width:158px;
            padding:6px 0;
            border:1px solid #d0d7de;
            border-radius:6px;
            background:#fff;
            box-shadow:0 8px 24px rgba(140,149,159,.24);
          }

          .lite-chamber-menu-wrap.is-open .lite-chamber-menu{
            display:block;
          }

          .lite-chamber-menu form{
            display:block !important;
            width:100% !important;
            margin:0 !important;
            padding:0 !important;
          }

          .lite-menu-link,
          .lite-menu-button{
            display:flex !important;
            align-items:center !important;
            justify-content:flex-start !important;
            gap:11px !important;
            width:100% !important;
            min-height:34px !important;
            padding:8px 12px !important;
            border:0 !important;
            border-radius:0 !important;
            background:transparent !important;
            color:#57606a !important;
            cursor:pointer !important;
            font-family:inherit !important;
            font-size:12.5px !important;
            line-height:1.35 !important;
            text-align:left !important;
            text-decoration:none !important;
            white-space:nowrap !important;
            font-weight:600!important;
            box-shadow:none !important;
            transform:none !important;
          }

          .lite-menu-link:hover,
          .lite-menu-button:hover{
            background:#f6f8fa !important;
            color:#57606a !important;
            text-decoration:none !important;
            box-shadow:none !important;
            transform:none !important;
          }

          .lite-menu-icon{
            width:15px;
            min-width:15px;
            display:inline-flex;
            align-items:center;
            justify-content:center;
            color:#6a737d;
            font-size:13px;
            line-height:1;
          }

          .lite-menu-link:hover .lite-menu-icon,
          .lite-menu-button:hover .lite-menu-icon{
            color:inherit;
          }

          .lite-menu-danger,
          .lite-menu-danger:hover,
          .lite-menu-danger:focus,
          .lite-menu-danger:active{
            color:#57606a !important;
            background:transparent !important;
            border:0 !important;
            box-shadow:none !important;
          }

          .lite-menu-danger:hover{
            background:#f6f8fa !important;
          }

          .lite-menu-danger .lite-menu-icon{
            color:#6a737d !important;
          }

          .lite-mini-btn,.wp-admin-button,.lite-btn{
            display:inline-flex;
            align-items:center;
            justify-content:center;
            gap:7px;
            min-height:34px;
            padding:7px 11px;
            border-radius:7px;
            border:1px solid rgba(27,31,36,.15);
            cursor:pointer;
            font-family:inherit;
            font-size:12.5px;
            line-height:1;
            text-decoration:none;
            white-space:nowrap;
            font-weight:700!important;
            transition:background .12s ease,border-color .12s ease,box-shadow .12s ease,transform .12s ease;
          }

          .lite-mini-btn:hover,
          .lite-btn:hover,
          .wp-admin-button:hover{
            transform:translateY(-1px);
            box-shadow:0 2px 6px rgba(27,31,36,.08);
          }

          .wp-admin-button,.lite-btn-primary{background:#2da44e;color:#fff}
          .wp-admin-button:hover,.lite-btn-primary:hover{background:#1f883d;color:#fff}
          .lite-mini-btn{color:var(--gh-text);background:#fff}
          .lite-mini-btn:hover{background:#f3f4f6;color:var(--gh-text)}
          .lite-danger-btn{color:#cf222e;background:#fff;border-color:#ff818266}
          .lite-danger-btn:hover{background:#ffebe9;color:#cf222e;border-color:#ff8182}
          .lite-view-btn{color:#0969da;background:#fff;border-color:#54aeff66}
          .lite-view-btn:hover{background:#ddf4ff;color:#0969da;border-color:#54aeff}
          .lite-edit-btn{color:#8250df;background:#fff;border-color:#d8b9ff}
          .lite-edit-btn:hover{background:#fbefff;color:#6639ba;border-color:#d8b9ff}
          .lite-status-badge{display:inline-flex;align-items:center;min-height:26px;padding:4px 9px;border-radius:999px;font-size:12px;font-weight:700!important;border:1px solid transparent}
          .lite-status-active{color:var(--gh-green);background:#dafbe1;border-color:#2da44e66}
          .lite-status-inactive{color:var(--gh-red);background:var(--gh-red-soft);border-color:#ff818266}
          .lite-edit-form-actions{display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-top:14px}
          .lite-btn-light{background:#fff!important;color:var(--gh-text)!important;border:1px solid var(--gh-border)!important}
          .lite-btn-light:hover{background:#f6f8fa!important;color:var(--gh-text)!important}

          .lite-auto-box,.wp-preview-note{padding:12px 14px;color:var(--gh-muted);font-size:12.5px;line-height:1.6}
          .wp-preview-note{margin-top:12px}

          .wp-days-row{display:flex;flex-wrap:wrap;gap:8px;margin-top:14px}
          .wp-day-pill{
            position:relative;
            display:inline-flex;
            align-items:center;
            gap:8px;
            padding:8px 11px;
            border:1px solid var(--gh-border);
            border-radius:999px;
            background:#fff;
            color:#344054;
            font-size:12px;
            cursor:pointer;
            user-select:none;
            transition:.12s ease;
          }
          .wp-day-pill:hover{border-color:#8c959f;background:#f6f8fa}
          .wp-day-pill input{position:absolute;opacity:0;pointer-events:none}
          .wp-day-check{
            width:16px;
            height:16px;
            border:1px solid #8c959f;
            border-radius:4px;
            display:inline-flex;
            align-items:center;
            justify-content:center;
            background:#fff;
            flex:0 0 auto;
          }
          .wp-day-check:after{
            content:"";
            width:8px;
            height:4px;
            border-left:2px solid #fff;
            border-bottom:2px solid #fff;
            transform:rotate(-45deg);
            margin-top:-2px;
            opacity:0;
          }
          .wp-day-pill input:checked + .wp-day-check{background:var(--gh-green);border-color:var(--gh-green)}
          .wp-day-pill input:checked + .wp-day-check:after{opacity:1}
          .wp-day-pill input:checked ~ .wp-day-text{color:var(--gh-green);font-weight:700!important}
          .wp-day-pill:has(input:checked){border-color:#2da44e66;background:#dafbe1}
          .wp-day-pill-closed input:checked + .wp-day-check{background:var(--gh-red);border-color:var(--gh-red)}
          .wp-day-pill-closed input:checked ~ .wp-day-text{color:var(--gh-red)}
          .wp-day-pill-closed:has(input:checked){border-color:#ff818266;background:var(--gh-red-soft)}

          .wp-search-dropdown{position:relative}
          .wp-search-dropdown-input{
            width:100%;
            min-height:42px;
            border:1px solid var(--gh-border);
            border-radius:6px;
            padding:10px 38px 10px 12px;
            color:var(--gh-text);
            background:#fff;
            outline:none;
            font-family:inherit;
            font-size:14px;
            cursor:text;
          }
          .wp-search-dropdown-input:focus{border-color:var(--gh-blue);box-shadow:0 0 0 3px rgba(9,105,218,.12)}
          .wp-search-dropdown-arrow{position:absolute;right:10px;top:10px;width:22px;height:22px;display:flex;align-items:center;justify-content:center;color:var(--gh-muted);pointer-events:none;font-size:12px}
          .wp-search-dropdown-list{
            position:absolute;
            left:0;
            right:0;
            top:calc(100% + 6px);
            z-index:80;
            display:none;
            max-height:280px;
            overflow:auto;
            border:1px solid var(--gh-border);
            border-radius:8px;
            background:#fff;
            box-shadow:0 8px 24px rgba(140,149,159,.25);
          }
          .wp-search-dropdown.is-open .wp-search-dropdown-list{display:block}
          .wp-search-dropdown-item{padding:10px 12px;cursor:pointer;color:var(--gh-text);font-size:14px;line-height:1.4;border-bottom:1px solid #f0f2f4}
          .wp-search-dropdown-item:hover,.wp-search-dropdown-item.is-active{background:var(--gh-soft);color:var(--gh-blue)}
          .wp-search-dropdown-empty,.wp-search-dropdown-loading{padding:13px;color:var(--gh-muted);font-size:13px;text-align:center}
          .wp-search-dropdown-meta{display:block;margin-top:3px;color:var(--gh-muted);font-size:11.5px}

          .lite-chamber-summary{
            display:grid;
            grid-template-columns:repeat(2,minmax(0,1fr));
            gap:8px 12px;
            margin-top:12px;
            padding:12px;
            border:1px solid #d8dee4;
            border-radius:10px;
            background:#f6f8fa;
          }
          .lite-chamber-summary-item{
            display:flex;
            gap:7px;
            align-items:flex-start;
            min-width:0;
            color:var(--gh-muted);
            font-size:12.5px;
            line-height:1.5;
          }
          .lite-chamber-summary-item.full{grid-column:1 / -1}
          .lite-chamber-summary-label{
            flex:0 0 auto;
            color:var(--gh-text);
            font-weight:700!important;
          }
          .lite-chamber-summary-value{
            min-width:0;
            overflow-wrap:anywhere;
          }
          .lite-required-note{
            margin-top:10px;
            color:var(--gh-muted);
            font-size:12px;
            line-height:1.5;
          }

          @media(max-width:900px){
            .wp-chain-grid,.wp-schedule-grid,.lite-chamber-summary{grid-template-columns:1fr}
            .lite-section-title,.lite-chamber-head{align-items:stretch;flex-direction:column}
            .lite-section-title span{text-align:left}
            .lite-card-actions{justify-content:flex-start;width:100%}
          }
        

  body{background:#f6f8fa}
  .lite-doctor-page,.lite-doctor-page *{box-sizing:border-box}
  .lite-doctor-page{max-width:1280px;margin:0 auto;padding:16px 16px 36px;color:#24292f}
  .lite-page-hero,.lite-form-card{overflow:visible;border-radius:12px;background:#fff;border:1px solid #d0d7de;box-shadow:0 1px 0 rgba(27,31,36,.04)}
  .lite-page-hero{padding:18px;margin-bottom:16px}
  .lite-page-hero-inner{display:flex;align-items:flex-start;justify-content:space-between;gap:24px}
  .lite-breadcrumb{display:inline-flex;align-items:center;gap:8px;margin-bottom:12px;padding:7px 10px;border-radius:6px;color:#57606a;font-size:13px;background:#f6f8fa;border:1px solid #d0d7de}
  .lite-breadcrumb a{color:#0969da;text-decoration:none}
  .lite-page-hero h1{margin:0;color:#24292f;font-size:clamp(26px,3vw,38px);line-height:1.15;letter-spacing:-.04em}
  .lite-page-hero p{max-width:760px;margin:10px 0 0;color:#57606a;font-size:15px;line-height:1.8}
  .lite-hero-actions{display:flex;align-items:center;gap:10px;flex-wrap:wrap;justify-content:flex-end}
  .lite-btn-link{display:inline-flex;align-items:center;justify-content:center;min-height:40px;padding:9px 14px;border-radius:6px;border:1px solid rgba(27,31,36,.15);color:#24292f;background:#f6f8fa;font-size:13.5px;text-decoration:none;white-space:nowrap}
  .lite-btn-link:hover{background:#eef1f4;color:#24292f}
  .lite-form-card{margin-bottom:22px}
  .lite-form-card-header{padding:20px 22px;border-bottom:1px solid #d0d7de;background:#f6f8fa;border-radius:12px 12px 0 0}
  .lite-form-card-header h2{margin:0;color:#24292f;font-size:20px;letter-spacing:-.025em}
  .lite-form-card-header p{max-width:920px;margin:8px 0 0;color:#57606a;font-size:14px;line-height:1.65}
  .lite-admin-form{padding:22px}
  .lite-alert{display:flex;align-items:flex-start;gap:14px;margin-bottom:16px;padding:14px 16px;border-radius:12px;border:1px solid transparent;background:#fff}
  .lite-alert.success{border-color:#a7f3d0;background:#ecfdf5;color:#065f46}
  .lite-alert.error{border-color:#fecdd3;background:#fff1f2;color:#9f1239}
  .lite-alert-icon{width:38px;height:38px;flex:0 0 38px;border-radius:8px;display:grid;place-items:center;color:#fff;font-size:18px;background:#0969da}
  .lite-alert.success .lite-alert-icon{background:#1a7f37}.lite-alert.error .lite-alert-icon{background:#cf222e}
  .lite-alert-content h3{margin:0 0 4px;font-size:16px;color:inherit}.lite-alert-content p{margin:0;font-size:14px;line-height:1.6;color:inherit}
  .lite-request-table-wrap{width:100%;overflow-x:auto;border:1px solid #d0d7de;border-radius:12px;background:#fff}
  .lite-request-table{width:100%;border-collapse:collapse;min-width:760px}
  .lite-request-table th{padding:13px 14px;background:#f6f8fa;border-bottom:1px solid #d0d7de;color:#57606a;font-size:12px;text-align:left;white-space:nowrap}
  .lite-request-table td{padding:14px;border-bottom:1px solid #d8dee4;font-size:13.5px;vertical-align:middle}.lite-request-table tr:last-child td{border-bottom:0}
  .lite-request-status{display:inline-flex;padding:4px 9px;border-radius:999px;background:#f6f8fa;border:1px solid #d0d7de;font-size:12px;font-weight:700}
  .wp-owner-grid{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr) auto;gap:11px;align-items:end}
  .wp-owner-grid label{display:block;margin-bottom:6px;color:#344054;font-size:13px;font-weight:600}
  .wp-owner-grid select,.wp-owner-grid input{width:100%;min-height:42px;border:1px solid #d0d7de;border-radius:6px;padding:10px 12px;background:#fff;color:#24292f;font:inherit}
  @media(max-width:900px){.lite-page-hero-inner{flex-direction:column;align-items:stretch}.lite-hero-actions{justify-content:flex-start}.wp-owner-grid{grid-template-columns:1fr}}
</style>

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
        <h2>Hospitals &amp; Availability</h2>
        <p><?= $is_hospital ? 'Select a doctor, then add this hospital as a chamber. New chamber requests require admin approval.' : 'Select Division, District, Thana and Hospital Name, then click Add. Hospital search works from the full database.' ?></p>
      </div>

      <div class="lite-admin-form">
        <div class="lite-section">
          <div class="lite-section-title">
            <h3>Add Hospital / Chamber</h3>
            <span><?= $is_hospital ? 'Hospital owner chamber request' : 'Lightweight AJAX hospital search' ?></span>
          </div>

          <form method="POST" class="wp-style-chamber-box" id="addChamberAjaxForm">
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
                <tr><th style="width:70px;text-align:center;">#</th><th>Hospital</th><th>Appointment</th><th>Time</th><th>Status</th><th>Actions</th></tr>
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
                              <form method="POST" onsubmit="return confirm('Delete this chamber?');">
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

<script>
(function() {
  const isHospitalOwner = <?= $is_hospital ? 'true' : 'false' ?>;
  const fixedDoctorId = <?= (int)$doctor_id ?>;
  const fixedHospital = <?= json_encode($fixed_hospital, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
  const csrfToken = <?= json_encode($csrf_token, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

  const division = document.getElementById('doctor_chamber_division');
  const district = document.getElementById('doctor_chamber_district');
  const thana = document.getElementById('doctor_chamber_thana');
  const dropdown = document.getElementById('doctor_chamber_hospital_dropdown');
  const hospitalSearch = document.getElementById('doctor_chamber_hospital_search');
  const hospitalId = document.getElementById('doctor_chamber_hospital');
  const hospitalViewUrl = document.getElementById('doctor_chamber_hospital_view_url');
  const hospitalAddress = document.getElementById('doctor_chamber_hospital_address');
  const hospitalLocation = document.getElementById('doctor_chamber_hospital_location');
  const hospitalList = document.getElementById('doctor_chamber_hospital_list');
  const doctorSelect = document.getElementById('hospital_owner_doctor_id');
  const preview = document.getElementById('doctor_chamber_preview');
  const addForm = document.getElementById('addChamberAjaxForm');
  const chamberList = document.getElementById('doctorChamberList');
  let searchTimer = null;
  let lastSearchKey = '';

  function escapeHtml(value) {
    return String(value || '').replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;').replaceAll('"','&quot;').replaceAll("'",'&#039;');
  }

  function ajaxUrl(action, params = {}) {
    const url = new URL(window.location.href);
    url.searchParams.set('chamber_ajax', action);
    Object.keys(params).forEach(key => url.searchParams.set(key, params[key]));
    return url.toString();
  }

  function resetSelect(select, placeholder) {
    if (!select) return;
    select.innerHTML = `<option value="">${placeholder}</option>`;
    select.disabled = true;
  }

  function fillSelect(select, rows, placeholder) {
    if (!select) return;
    select.innerHTML = `<option value="">${placeholder}</option>`;
    (rows || []).forEach(row => {
      const option = document.createElement('option');
      option.value = row.id;
      option.textContent = row.name;
      select.appendChild(option);
    });
    select.disabled = false;
  }

  function clearHospital(keepText = false) {
    if (hospitalId) hospitalId.value = '';
    if (hospitalViewUrl) hospitalViewUrl.value = '';
    if (hospitalAddress) hospitalAddress.value = '';
    if (hospitalLocation) hospitalLocation.value = '';
    if (hospitalSearch && !keepText) hospitalSearch.value = '';
    updatePreview();
  }

  function openDropdown() { if (dropdown) dropdown.classList.add('is-open'); }
  function closeDropdown() { if (dropdown) dropdown.classList.remove('is-open'); }

  function renderHospitals(items, message = '') {
    if (!hospitalList) return;
    hospitalList.innerHTML = '';
    if (message) { hospitalList.innerHTML = `<div class="wp-search-dropdown-empty">${escapeHtml(message)}</div>`; return; }
    if (!Array.isArray(items) || !items.length) { hospitalList.innerHTML = '<div class="wp-search-dropdown-empty">No hospital found</div>'; return; }
    items.forEach(item => {
      const row = document.createElement('div');
      row.className = 'wp-search-dropdown-item';
      row.innerHTML = `<strong>${escapeHtml(item.name || 'Hospital')}</strong><span class="wp-search-dropdown-meta">${escapeHtml(item.location || '')}</span>`;
      row.addEventListener('click', function() {
        hospitalId.value = String(item.id || '');
        hospitalViewUrl.value = String(item.view_url || '');
        hospitalAddress.value = String(item.address || '');
        hospitalLocation.value = String(item.location || '');
        hospitalSearch.value = String(item.name || '');
        closeDropdown();
        updatePreview();
      });
      hospitalList.appendChild(row);
    });
  }

  async function searchHospitals(force = false) {
    if (isHospitalOwner || !hospitalSearch) return;
    const key = [hospitalSearch.value.trim(), division?.value || '', district?.value || '', thana?.value || ''].join('|');
    if (!force && key === lastSearchKey) return;
    lastSearchKey = key;
    openDropdown();
    renderHospitals([], 'Searching hospitals...');
    try {
      const response = await fetch(ajaxUrl('hospitals', {q:hospitalSearch.value.trim(),division_id:division?.value || '',district_id:district?.value || '',thana_id:thana?.value || '',limit:'50'}));
      const data = await response.json();
      renderHospitals(data && data.success ? (data.items || []) : [], data && data.success ? '' : (data.message || 'Hospital search failed'));
    } catch (error) {
      renderHospitals([], 'Hospital search failed');
    }
  }

  function scheduleSearch(force = false) {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => searchHospitals(force), 180);
  }

  async function loadDistricts(id) {
    resetSelect(district, '— Select District —');
    resetSelect(thana, '— All Thanas —');
    clearHospital();
    if (!id) { scheduleSearch(true); return; }
    try { const response = await fetch(ajaxUrl('districts', {division_id:id})); fillSelect(district, await response.json(), '— Select District —'); } catch (e) {}
    scheduleSearch(true); updatePreview();
  }

  async function loadThanas(id) {
    resetSelect(thana, '— All Thanas —');
    clearHospital();
    if (!id) { scheduleSearch(true); return; }
    try { const response = await fetch(ajaxUrl('thanas', {district_id:id})); fillSelect(thana, await response.json(), '— All Thanas —'); } catch (e) {}
    scheduleSearch(true); updatePreview();
  }

  function selectedText(select) {
    return select && select.selectedIndex > 0 ? select.options[select.selectedIndex].textContent.trim() : '';
  }

  function updatePreview() {
    if (!preview) return;
    if (isHospitalOwner) {
      const doctorName = selectedText(doctorSelect);
      preview.textContent = [doctorName, fixedHospital.name || ''].filter(Boolean).join(' → ') || 'Select a doctor and click Add.';
      return;
    }
    const parts = [selectedText(division), selectedText(district), selectedText(thana), hospitalId?.value ? hospitalSearch?.value.trim() : ''].filter(Boolean);
    preview.textContent = parts.length ? parts.join(' → ') : 'Select Division → District → Thana → Hospital Name.';
  }

  function currentSelection() {
    if (isHospitalOwner) {
      return {
        doctorId: doctorSelect?.value || '',
        doctorName: selectedText(doctorSelect),
        hospitalId: fixedHospital.id || '',
        hospitalName: fixedHospital.name || 'Hospital',
        viewUrl: fixedHospital.view_url || '#',
        address: fixedHospital.address || '',
        location: fixedHospital.location || ''
      };
    }
    return {
      doctorId: fixedDoctorId,
      doctorName: '',
      hospitalId: hospitalId?.value || '',
      hospitalName: hospitalSearch?.value.trim() || '',
      viewUrl: hospitalViewUrl?.value || '#',
      address: hospitalAddress?.value || '',
      location: hospitalLocation?.value || ''
    };
  }

  function duplicateExists(doctorIdValue, hospitalIdValue) {
    return Array.from(chamberList?.querySelectorAll('.lite-chamber-entry') || []).some(row =>
      String(row.dataset.doctorId || '') === String(doctorIdValue) && String(row.dataset.hospitalId || '') === String(hospitalIdValue)
    );
  }

  function nextSortOrder() {
    return (chamberList?.querySelectorAll('.lite-chamber-entry').length || 0) + 1;
  }

  function dayPills() {
    const days = [['sat','Saturday / শনিবার'],['sun','Sunday / রবিবার'],['mon','Monday / সোমবার'],['tue','Tuesday / মঙ্গলবার'],['wed','Wednesday / বুধবার'],['thu','Thursday / বৃহস্পতিবার'],['fri','Friday / শুক্রবার']];
    return days.map(day => `<label class="wp-day-pill"><input type="checkbox" name="available_days[]" value="${day[0]}"><span class="wp-day-check"></span><span class="wp-day-text">${day[1]}</span></label>`).join('');
  }

  function renumberRows() {
    Array.from(chamberList?.querySelectorAll('.lite-chamber-entry') || []).forEach((row,index) => {
      const number = row.querySelector('.lite-serial-number'); if (number) number.textContent = String(index + 1);
      if (row.classList.contains('unsaved-chamber')) {
        const editRow = row.nextElementSibling;
        const sortInput = editRow?.querySelector('input[name="chamber_sort_order"]');
        if (sortInput) sortInput.value = String(index + 1);
      }
    });
  }

  function buildNewRows(data) {
    const sort = nextSortOrder();
    const editId = `newChamberEditRow${data.hospitalId}_${data.doctorId}_${Date.now()}`;
    const subtitle = isHospitalOwner ? `Doctor: ${data.doctorName || ''}` : (data.location || 'Not saved yet. Fill options and click Save Chamber.');
    return `
      <tr class="lite-chamber-entry unsaved-chamber is-editing" data-doctor-id="${escapeHtml(data.doctorId)}" data-hospital-id="${escapeHtml(data.hospitalId)}" data-sort-order="${sort}">
        <td class="lite-serial-cell"><span class="lite-serial-number">${sort}</span></td>
        <td><div class="lite-chamber-name-cell"><div class="lite-chamber-name-main"><strong>${escapeHtml(data.hospitalName)}</strong></div><div class="lite-chamber-sub">${escapeHtml(subtitle)}</div></div></td>
        <td>—</td><td>—</td><td><span class="lite-status-badge lite-status-inactive">Unsaved</span></td>
        <td><div class="lite-table-action-group"><button type="button" class="lite-table-btn" data-chamber-edit-toggle="${editId}">Edit</button><div class="lite-chamber-menu-wrap" data-chamber-menu-wrap><button type="button" class="lite-kebab" data-chamber-menu-toggle aria-label="More actions"><i class="fa fa-ellipsis-v fa-solid fa-ellipsis-vertical"></i></button><div class="lite-chamber-menu"><a href="${escapeHtml(data.viewUrl)}" target="_blank" rel="noopener" class="lite-menu-link"><span class="lite-menu-icon">👁</span><span>View</span></a></div></div></div></td>
      </tr>
      <tr class="lite-chamber-edit-row is-open" id="${editId}"><td colspan="6">
        <form method="POST">
          <input type="hidden" name="csrf_token" value="${escapeHtml(csrfToken)}"><input type="hidden" name="form_action" value="save_chamber"><input type="hidden" name="doctor_id" value="${escapeHtml(data.doctorId)}"><input type="hidden" name="chamber_hospital_id" value="${escapeHtml(data.hospitalId)}">
          <div class="wp-schedule-grid">
            <div><label>Appointment Phone</label><input type="text" name="chamber_appointment_phone" placeholder="+880..."></div>
            <div><label>Consultation Fee</label><input type="number" min="0" step="0.01" name="chamber_consultation_fee" placeholder="1000"></div>
            <div><label>From</label><input type="time" name="available_from"></div>
            <div><label>To</label><input type="time" name="available_to"></div>
            <div><label>Sort Order</label><input type="number" min="0" name="chamber_sort_order" value="${sort}"></div>
            <div><label>Status</label><select name="chamber_status"><option value="active" selected>Active</option><option value="inactive">Inactive</option></select></div>
            <div class="wp-field-pair"><div><label>Schedule</label><input type="text" name="schedule" placeholder="Saturday to Thursday 3pm to 9pm (Closed: Friday)"></div><div><label>Schedule Bangla</label><input type="text" name="schedule_bn" placeholder="শনিবার থেকে বৃহস্পতিবার বিকাল ৩টা থেকে রাত ৯টা (শুক্রবার বন্ধ)"></div></div>
            <div class="wp-field-pair"><div><label>Chamber Address</label><input type="text" name="chamber_address" value="${escapeHtml(data.address)}" placeholder="Chamber address or room/floor details"></div><div><label>Chamber Address Bangla</label><input type="text" name="chamber_address_bn" placeholder="চেম্বারের ঠিকানা বা রুম/ফ্লোরের তথ্য"></div></div>
          </div>
          <div class="wp-days-row">${dayPills()}<label class="wp-day-pill wp-day-pill-closed"><input type="checkbox" name="is_closed" value="1"><span class="wp-day-check"></span><span class="wp-day-text">Closed / বন্ধ</span></label></div>
          <div class="lite-edit-form-actions"><button type="submit" class="lite-btn lite-btn-primary">Save Chamber</button><button type="button" class="lite-btn lite-btn-light" data-unsaved-remove>Remove</button></div>
        </form>
      </td></tr>`;
  }

  function closeAllExcept(target) {
    document.querySelectorAll('.lite-chamber-edit-row.is-open').forEach(row => {
      if (row !== target) { row.classList.remove('is-open'); row.previousElementSibling?.classList.remove('is-editing'); }
    });
  }

  function bindInteractions(root = document) {
    root.querySelectorAll('[data-chamber-edit-toggle]').forEach(button => {
      if (button.dataset.bound) return; button.dataset.bound = '1';
      button.addEventListener('click', function() {
        const row = document.getElementById(this.dataset.chamberEditToggle);
        if (!row) return;
        const opening = !row.classList.contains('is-open');
        closeAllExcept(row);
        row.classList.toggle('is-open', opening);
        row.previousElementSibling?.classList.toggle('is-editing', opening);
      });
    });
    root.querySelectorAll('[data-chamber-edit-cancel]').forEach(button => {
      if (button.dataset.bound) return; button.dataset.bound = '1';
      button.addEventListener('click', function() { const row = this.closest('.lite-chamber-edit-row'); row?.classList.remove('is-open'); row?.previousElementSibling?.classList.remove('is-editing'); });
    });
    root.querySelectorAll('[data-unsaved-remove]').forEach(button => {
      if (button.dataset.bound) return; button.dataset.bound = '1';
      button.addEventListener('click', function() { const editRow = this.closest('.lite-chamber-edit-row'); const mainRow = editRow?.previousElementSibling; editRow?.remove(); mainRow?.remove(); renumberRows(); if (!(chamberList?.querySelector('.lite-chamber-entry'))) chamberList.innerHTML = '<tr id="noChamberMessage"><td colspan="6" class="lite-chamber-empty-cell">No saved chamber yet. Select the required doctor/hospital and click Add to open the option form.</td></tr>'; });
    });
    root.querySelectorAll('[data-chamber-menu-toggle]').forEach(button => {
      if (button.dataset.bound) return; button.dataset.bound = '1';
      button.addEventListener('click', function(event) { event.stopPropagation(); const wrap = this.closest('[data-chamber-menu-wrap]'); document.querySelectorAll('[data-chamber-menu-wrap].is-open').forEach(item => { if (item !== wrap) item.classList.remove('is-open'); }); wrap?.classList.toggle('is-open'); });
    });
  }

  if (division) division.addEventListener('change', () => loadDistricts(division.value));
  if (district) district.addEventListener('change', () => loadThanas(district.value));
  if (thana) thana.addEventListener('change', () => { clearHospital(); scheduleSearch(true); updatePreview(); });
  if (hospitalSearch) {
    hospitalSearch.addEventListener('focus', () => scheduleSearch(true));
    hospitalSearch.addEventListener('input', function() { clearHospital(true); scheduleSearch(false); });
  }
  if (doctorSelect) doctorSelect.addEventListener('change', updatePreview);

  document.addEventListener('click', function(event) {
    if (dropdown && !dropdown.contains(event.target)) closeDropdown();
    if (!event.target.closest('[data-chamber-menu-wrap]')) document.querySelectorAll('[data-chamber-menu-wrap].is-open').forEach(item => item.classList.remove('is-open'));
  });

  if (addForm) addForm.addEventListener('submit', function(event) {
    event.preventDefault();
    const data = currentSelection();
    if (!data.hospitalId) { alert('Please select a hospital first.'); return; }
    if (!data.doctorId) { alert('Please select a doctor first.'); return; }
    if (duplicateExists(data.doctorId, data.hospitalId)) { alert('This chamber already exists for the selected doctor and hospital.'); return; }
    document.getElementById('noChamberMessage')?.remove();
    chamberList.insertAdjacentHTML('afterbegin', buildNewRows(data));
    bindInteractions(chamberList);
    renumberRows();
    if (!isHospitalOwner) { clearHospital(); closeDropdown(); }
  });

  bindInteractions(document);
  updatePreview();
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
