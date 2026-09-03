<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';

require_admin();

/*
|--------------------------------------------------------------------------
| User Request Action
|--------------------------------------------------------------------------
| File path: admin/user-request-action.php
|--------------------------------------------------------------------------
| Supports:
| - doctor_profile_update
| - hospital_profile_update
| - chamber_update
| - doctor_profile_create
| - hospital_profile_create
| - chamber_add
|
| Important:
| Update requests only update submitted request_data fields.
| Missing fields are never overwritten with empty values.
|--------------------------------------------------------------------------
*/

function ura_redirect(string $url): void
{
    if (function_exists('redirect')) {
        redirect($url);
        exit;
    }

    header('Location: ' . $url);
    exit;
}

function ura_start_session_if_needed(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        @session_start();
    }
}

function ura_flash(string $type, string $message): void
{
    ura_start_session_if_needed();

    $_SESSION['admin_flash_type'] = $type;
    $_SESSION['admin_flash_message'] = $message;

    // Extra compatibility for admin headers that use generic flash keys.
    $_SESSION['flash_type'] = $type;
    $_SESSION['flash_message'] = $message;
}

function ura_back_to_request(int $id): void
{
    ura_redirect('user-request-view.php?id=' . $id);
}

function ura_back_to_list(): void
{
    ura_redirect('user-requests.php');
}

function ura_table_exists(string $table): bool
{
    global $pdo;

    try {
        if (function_exists('table_exists')) {
            return (bool)table_exists($table);
        }

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

function ura_column_exists(string $table, string $column): bool
{
    global $pdo;

    try {
        if (function_exists('column_exists')) {
            return (bool)column_exists($table, $column);
        }

        if (!ura_table_exists($table)) {
            return false;
        }

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

function ura_decode_request_data(?string $json): array
{
    if (!$json) {
        return [];
    }

    $data = json_decode($json, true);

    return is_array($data) ? $data : [];
}

function ura_normalize_money($value): string
{
    $value = trim((string)$value);

    if ($value === '') {
        return '0.00';
    }

    $value = str_replace([',', '৳', 'BDT', 'Tk', 'tk', 'TK'], '', $value);
    $value = trim($value);

    if (!is_numeric($value)) {
        return '0.00';
    }

    return number_format((float)$value, 2, '.', '');
}

function ura_is_money_field(string $field): bool
{
    return in_array($field, [
        'consultation_fee',
        'follow_up_fee',
        'video_consultation_fee',
        'visiting_fee',
        'fee',
        'price',
    ], true);
}

function ura_bool_value($value): int
{
    if (is_bool($value)) {
        return $value ? 1 : 0;
    }

    $value = strtolower(trim((string)$value));

    return in_array($value, ['1', 'yes', 'true', 'on'], true) ? 1 : 0;
}

function ura_clean_value(string $field, $value)
{
    if (is_array($value)) {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    if (ura_is_money_field($field)) {
        return ura_normalize_money($value);
    }

    if (
        str_ends_with($field, '_available') ||
        in_array($field, [
            'online_consultation',
            'home_visit',
            'is_featured',
            'featured',
        ], true)
    ) {
        return ura_bool_value($value);
    }

    if (str_ends_with($field, '_id') || in_array($field, [
        'doctor_id',
        'hospital_id',
        'chamber_id',
        'experience_years',
        'serial_no',
        'status_order',
    ], true)) {
        $raw = trim((string)$value);

        if ($raw === '') {
            return 0;
        }

        if (is_numeric($raw)) {
            return (int)$raw;
        }

        return $raw;
    }

    return trim((string)$value);
}

function ura_fetch_request(int $id): ?array
{
    global $pdo;

    if (!ura_table_exists('profile_update_requests')) {
        return null;
    }

    try {
        $stmt = $pdo->prepare("
            SELECT *
            FROM profile_update_requests
            WHERE id = :id
            LIMIT 1
        ");

        $stmt->execute([':id' => $id]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    } catch (Throwable $e) {
        return null;
    }
}

function ura_fetch_row(string $table, int $id): ?array
{
    global $pdo;

    if ($id <= 0 || !ura_table_exists($table)) {
        return null;
    }

    try {
        $stmt = $pdo->prepare("SELECT * FROM `{$table}` WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    } catch (Throwable $e) {
        return null;
    }
}

function ura_update_request_status(int $id, string $status, string $admin_note = ''): void
{
    global $pdo;

    $set_parts = ['status = :status'];

    $params = [
        ':status' => $status,
        ':id' => $id,
    ];

    if (ura_column_exists('profile_update_requests', 'admin_note')) {
        $set_parts[] = 'admin_note = :admin_note';
        $params[':admin_note'] = $admin_note;
    }

    if (ura_column_exists('profile_update_requests', 'reviewed_at')) {
        $set_parts[] = 'reviewed_at = NOW()';
    }

    if (ura_column_exists('profile_update_requests', 'updated_at')) {
        $set_parts[] = 'updated_at = NOW()';
    }

    $sql = "
        UPDATE profile_update_requests
        SET " . implode(', ', $set_parts) . "
        WHERE id = :id
        LIMIT 1
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
}

function ura_allowed_doctor_fields(): array
{
    return [
        'name',
        'doctor_name',
        'slug',
        'degree',
        'degrees',
        'designation',
        'bmdc_number',
        'gender',
        'languages',
        'specialty_id',
        'speciality_id',
        'specialty_name',
        'speciality_name',
        'hospital_id',
        'primary_hospital',
        'experience',
        'experience_years',
        'consultation_fee',
        'follow_up_fee',
        'video_consultation_fee',
        'visiting_fee',
        'serial_no',
        'education',
        'training',
        'fellowship',
        'expertise',
        'services',
        'diseases_treated',
        'procedures',
        'awards',
        'membership',
        'research_publications',
        'phone',
        'mobile',
        'whatsapp',
        'email',
        'city',
        'district',
        'zilla',
        'zila',
        'thana',
        'upazila',
        'area',
        'doctor_division_id',
        'doctor_district_id',
        'doctor_thana_id',
        'doctor_division',
        'doctor_district',
        'doctor_thana',
        'area_locality',
        'road_street',
        'house_building',
        'post_code',
        'google_map_url',
        'address',
        'image',
        'photo',
        'og_image',
        'bio',
        'description',
        'appointment_note',
        'appointment_phone',
        'serial_phone',
        'emergency_available',
        'online_consultation',
        'home_visit',
        'seo_title',
        'seo_description',
        'meta_keywords',
        'status',
    ];
}

function ura_allowed_hospital_fields(): array
{
    return [
        'name',
        'hospital_name',
        'chamber_name',
        'slug',
        'type',
        'license_number',

        'logo',
        'image',
        'photo',
        'og_image',
        'cover_image',

        'description',
        'bio',

        'phone',
        'mobile',
        'contact_phone',
        'appointment_phone',
        'serial_phone',
        'email',
        'contact_email',
        'website',
        'website_url',
        'whatsapp',
        'emergency_phone',
        'ambulance_phone',

        'division_id',
        'district_id',
        'thana_id',
        'division',
        'division_name',
        'district',
        'district_name',
        'zilla',
        'zila',
        'city',
        'thana',
        'thana_name',
        'upazila',
        'area',
        'area_locality',
        'road_street',
        'road_no',
        'house_building',
        'house_no',
        'post_code',
        'address',
        'google_map_url',
        'map_url',

        'services',
        'facilities',
        'departments',
        'departments_count',
        'doctors_count',
        'rating',

        'opening_hours',
        'visiting_hours',
        'appointment_note',
        'bed_count',
        'established_year',
        'video_url',

        'emergency_available',
        'ambulance_available',
        'icu_available',
        'ccu_available',
        'nicu_available',
        'picu_available',
        'operation_theater_available',
        'diagnostic_available',
        'pharmacy_available',
        'blood_bank_available',
        'parking_available',
        'cafeteria_available',
        'wheelchair_available',
        'oxygen_available',
        'dialysis_available',
        'mri_available',
        'ct_scan_available',
        'xray_available',
        'lab_available',
        'dental_unit_available',
        'eye_unit_available',
        'mother_child_unit_available',
        'cancer_unit_available',
        'cardiac_unit_available',
        'rehab_unit_available',

        'online_consultation',
        'home_visit',

        'seo_title',
        'seo_description',
        'meta_keywords',
    ];
}

function ura_allowed_chamber_fields(): array
{
    return [
        'doctor_id',
        'hospital_id',
        'name',
        'chamber_name',
        'hospital_name',
        'title',
        'slug',
        'phone',
        'mobile',
        'contact_phone',
        'appointment_phone',
        'serial_phone',
        'email',
        'contact_email',
        'division_id',
        'district_id',
        'thana_id',
        'division',
        'district',
        'zilla',
        'zila',
        'city',
        'thana',
        'upazila',
        'area',
        'area_locality',
        'road_street',
        'house_building',
        'post_code',
        'address',
        'google_map_url',
        'visiting_fee',
        'consultation_fee',
        'follow_up_fee',
        'video_consultation_fee',
        'available_days',
        'available_time',
        'start_time',
        'end_time',
        'opening_hours',
        'serial_no',
        'description',
        'bio',
        'services',
        'facilities',
        'logo',
        'image',
        'photo',
        'og_image',
        'status',
        'seo_title',
        'seo_description',
        'meta_keywords',
    ];
}

function ura_pick_first(array $data, array $keys): string
{
    foreach ($keys as $key) {
        if (array_key_exists($key, $data) && trim((string)$data[$key]) !== '') {
            return trim((string)$data[$key]);
        }
    }

    return '';
}

function ura_map_aliases_for_table(string $table, array $data): array
{
    /*
     * chamber_add request_data often uses hospital/chamber mixed names.
     * This mapper copies the value into the actual existing column name.
     * Existing submitted fields are never removed.
     */

    $mapped = $data;

    $name_value = ura_pick_first($data, ['name', 'chamber_name', 'hospital_name', 'title', 'primary_hospital']);

    if ($name_value !== '') {
        foreach (['name', 'chamber_name', 'hospital_name', 'title'] as $column) {
            if (ura_column_exists($table, $column) && !array_key_exists($column, $mapped)) {
                $mapped[$column] = $name_value;
            }
        }
    }

    $phone_value = ura_pick_first($data, ['phone', 'mobile', 'contact_phone', 'appointment_phone', 'serial_phone']);

    if ($phone_value !== '') {
        foreach (['phone', 'mobile', 'contact_phone', 'appointment_phone', 'serial_phone'] as $column) {
            if (ura_column_exists($table, $column) && !array_key_exists($column, $mapped)) {
                $mapped[$column] = $phone_value;
                break;
            }
        }
    }

    $email_value = ura_pick_first($data, ['email', 'contact_email']);

    if ($email_value !== '') {
        foreach (['email', 'contact_email'] as $column) {
            if (ura_column_exists($table, $column) && !array_key_exists($column, $mapped)) {
                $mapped[$column] = $email_value;
                break;
            }
        }
    }

    $district_value = ura_pick_first($data, ['district', 'zilla', 'zila', 'city']);

    if ($district_value !== '') {
        foreach (['district', 'zilla', 'zila', 'city'] as $column) {
            if (ura_column_exists($table, $column) && !array_key_exists($column, $mapped)) {
                $mapped[$column] = $district_value;
            }
        }
    }

    $thana_value = ura_pick_first($data, ['thana', 'upazila', 'area']);

    if ($thana_value !== '') {
        foreach (['thana', 'upazila', 'area'] as $column) {
            if (ura_column_exists($table, $column) && !array_key_exists($column, $mapped)) {
                $mapped[$column] = $thana_value;
            }
        }
    }

    $description_value = ura_pick_first($data, ['description', 'bio']);

    if ($description_value !== '') {
        foreach (['description', 'bio'] as $column) {
            if (ura_column_exists($table, $column) && !array_key_exists($column, $mapped)) {
                $mapped[$column] = $description_value;
            }
        }
    }

    return $mapped;
}

function ura_filter_update_data(string $table, array $request_data, array $allowed_fields): array
{
    $filtered = [];
    $request_data = ura_map_aliases_for_table($table, $request_data);

    foreach ($request_data as $field => $value) {
        $field = trim((string)$field);

        if ($field === '') {
            continue;
        }

        if (!in_array($field, $allowed_fields, true)) {
            continue;
        }

        if (!ura_column_exists($table, $field)) {
            continue;
        }

        $filtered[$field] = ura_clean_value($field, $value);
    }

    return $filtered;
}

function ura_update_existing_profile(string $table, int $target_id, array $request_data, array $allowed_fields): int
{
    global $pdo;

    if ($target_id <= 0) {
        throw new RuntimeException('Target profile ID not found.');
    }

    if (!ura_table_exists($table)) {
        throw new RuntimeException($table . ' table not found.');
    }

    $current_row = ura_fetch_row($table, $target_id);

    if (!$current_row) {
        throw new RuntimeException('Target profile not found.');
    }

    $update_data = ura_filter_update_data($table, $request_data, $allowed_fields);

    if (!$update_data) {
        throw new RuntimeException('No valid submitted field found for update.');
    }

    $set_parts = [];
    $params = [':id' => $target_id];

    foreach ($update_data as $field => $value) {
        $param_key = ':field_' . $field;
        $set_parts[] = "`{$field}` = {$param_key}";
        $params[$param_key] = $value;
    }

    if (ura_column_exists($table, 'updated_at')) {
        $set_parts[] = 'updated_at = NOW()';
    }

    $sql = "
        UPDATE `{$table}`
        SET " . implode(', ', $set_parts) . "
        WHERE id = :id
        LIMIT 1
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return count($update_data);
}

function ura_insert_new_profile(string $table, array $request_data, array $allowed_fields, array $extra_data = []): int
{
    global $pdo;

    if (!ura_table_exists($table)) {
        throw new RuntimeException($table . ' table not found.');
    }

    $request_data = array_merge($request_data, $extra_data);
    $insert_data = ura_filter_update_data($table, $request_data, $allowed_fields);

    if (!$insert_data) {
        throw new RuntimeException('No valid submitted field found for create request.');
    }

    if (ura_column_exists($table, 'status') && !array_key_exists('status', $insert_data)) {
        $insert_data['status'] = 'active';
    }

    if (ura_column_exists($table, 'created_at') && !array_key_exists('created_at', $insert_data)) {
        $insert_data['created_at'] = date('Y-m-d H:i:s');
    }

    if (ura_column_exists($table, 'updated_at') && !array_key_exists('updated_at', $insert_data)) {
        $insert_data['updated_at'] = date('Y-m-d H:i:s');
    }

    $columns = [];
    $placeholders = [];
    $params = [];

    foreach ($insert_data as $field => $value) {
        $columns[] = "`{$field}`";
        $placeholder = ':field_' . $field;
        $placeholders[] = $placeholder;
        $params[$placeholder] = $value;
    }

    $sql = "
        INSERT INTO `{$table}` (" . implode(', ', $columns) . ")
        VALUES (" . implode(', ', $placeholders) . ")
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return (int)$pdo->lastInsertId();
}

function ura_attach_created_profile_to_request(int $request_id, string $type, int $new_id): void
{
    global $pdo;

    $set_parts = [];
    $params = [':id' => $request_id];

    if ($type === 'doctor' && ura_column_exists('profile_update_requests', 'doctor_id')) {
        $set_parts[] = 'doctor_id = :target_id';
        $params[':target_id'] = $new_id;
    }

    if ($type === 'hospital' && ura_column_exists('profile_update_requests', 'hospital_id')) {
        $set_parts[] = 'hospital_id = :target_id';
        $params[':target_id'] = $new_id;
    }

    if ($type === 'chamber' && ura_column_exists('profile_update_requests', 'chamber_id')) {
        $set_parts[] = 'chamber_id = :target_id';
        $params[':target_id'] = $new_id;
    }

    if (!$set_parts) {
        return;
    }

    if (ura_column_exists('profile_update_requests', 'updated_at')) {
        $set_parts[] = 'updated_at = NOW()';
    }

    $sql = "
        UPDATE profile_update_requests
        SET " . implode(', ', $set_parts) . "
        WHERE id = :id
        LIMIT 1
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
}

function ura_approve_request(array $request): string
{
    $request_type = trim((string)($request['request_type'] ?? ''));
    $request_data = ura_decode_request_data($request['request_data'] ?? '');

    if (!$request_data) {
        throw new RuntimeException('No request data found.');
    }

    $doctor_id = (int)($request['doctor_id'] ?? 0);
    $hospital_id = (int)($request['hospital_id'] ?? 0);
    $chamber_id = (int)($request['chamber_id'] ?? 0);

    if ($request_type === 'doctor_profile_update') {
        $updated = ura_update_existing_profile(
            'doctors',
            $doctor_id,
            $request_data,
            ura_allowed_doctor_fields()
        );

        return 'Doctor profile approved. Updated fields: ' . $updated . '.';
    }

    if ($request_type === 'hospital_profile_update') {
        $updated = ura_update_existing_profile(
            'hospitals',
            $hospital_id,
            $request_data,
            ura_allowed_hospital_fields()
        );

        return 'Hospital profile approved. Updated fields: ' . $updated . '.';
    }

    if ($request_type === 'chamber_update') {
        $target_id = $chamber_id > 0 ? $chamber_id : $hospital_id;

        if (ura_table_exists('chambers') && $target_id > 0) {
            $updated = ura_update_existing_profile(
                'chambers',
                $target_id,
                $request_data,
                ura_allowed_chamber_fields()
            );

            return 'Chamber/Hospital profile approved. Updated fields: ' . $updated . '.';
        }

        $updated = ura_update_existing_profile(
            'hospitals',
            $hospital_id,
            $request_data,
            ura_allowed_hospital_fields()
        );

        return 'Hospital profile approved. Updated fields: ' . $updated . '.';
    }

    if ($request_type === 'doctor_profile_create') {
        $new_id = ura_insert_new_profile(
            'doctors',
            $request_data,
            ura_allowed_doctor_fields()
        );

        ura_attach_created_profile_to_request((int)$request['id'], 'doctor', $new_id);

        return 'Doctor profile created and approved. New ID: ' . $new_id . '.';
    }

    if ($request_type === 'hospital_profile_create') {
        $new_id = ura_insert_new_profile(
            'hospitals',
            $request_data,
            ura_allowed_hospital_fields()
        );

        ura_attach_created_profile_to_request((int)$request['id'], 'hospital', $new_id);

        return 'Hospital profile created and approved. New ID: ' . $new_id . '.';
    }

    if ($request_type === 'chamber_add') {
        /*
         * Important fix:
         * chamber_add should create in chambers table if chambers table exists.
         * The previous logic often used hospitals table first, so no useful insert happened
         * when chamber-specific request_data did not match hospital columns.
         */
        $extra_data = [];

        if ($doctor_id > 0) {
            $extra_data['doctor_id'] = $doctor_id;
        }

        if ($hospital_id > 0) {
            $extra_data['hospital_id'] = $hospital_id;
        }

        if (ura_table_exists('chambers')) {
            $new_id = ura_insert_new_profile(
                'chambers',
                $request_data,
                ura_allowed_chamber_fields(),
                $extra_data
            );

            ura_attach_created_profile_to_request((int)$request['id'], 'chamber', $new_id);

            return 'Chamber/Hospital added and approved. New ID: ' . $new_id . '.';
        }

        $new_id = ura_insert_new_profile(
            'hospitals',
            $request_data,
            ura_allowed_hospital_fields(),
            $extra_data
        );

        ura_attach_created_profile_to_request((int)$request['id'], 'hospital', $new_id);

        return 'Hospital added and approved. New ID: ' . $new_id . '.';
    }

    if ($request_type === 'hospital_doctor_add') {
        throw new RuntimeException('hospital_doctor_add approval logic is not configured yet.');
    }

    throw new RuntimeException('Unsupported request type: ' . $request_type);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ura_back_to_list();
}

$id = (int)($_POST['id'] ?? 0);
$action = trim((string)($_POST['action'] ?? ''));
$admin_note = trim((string)($_POST['admin_note'] ?? ''));

if ($id <= 0) {
    ura_flash('error', 'Invalid request ID.');
    ura_back_to_list();
}

if (!in_array($action, ['approve', 'reject'], true)) {
    ura_flash('error', 'Invalid action.');
    ura_back_to_request($id);
}

$request = ura_fetch_request($id);

if (!$request) {
    ura_flash('error', 'Request not found.');
    ura_back_to_list();
}

$status = trim((string)($request['status'] ?? 'pending'));

if ($status !== 'pending') {
    ura_flash('error', 'This request has already been reviewed.');
    ura_back_to_request($id);
}

try {
    $pdo->beginTransaction();

    if ($action === 'reject') {
        ura_update_request_status($id, 'rejected', $admin_note);
        $pdo->commit();

        ura_flash('success', 'Request rejected successfully.');
        ura_back_to_request($id);
    }

    $message = ura_approve_request($request);

    ura_update_request_status($id, 'approved', $admin_note);

    $pdo->commit();

    ura_flash('success', $message);
    ura_back_to_request($id);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    ura_flash('error', 'Action failed: ' . $e->getMessage());
    ura_back_to_request($id);
}
