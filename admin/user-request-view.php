<?php
require_once __DIR__ . '/../includes/functions.php';

require_admin();

/*
|--------------------------------------------------------------------------
| User Request View Page
|--------------------------------------------------------------------------
| File path: admin/user-request-view.php
|--------------------------------------------------------------------------
*/

function request_view_table_exists(string $table): bool
{
    global $pdo;

    try {
        if (function_exists('table_exists')) {
            return table_exists($table);
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

function request_view_column_exists(string $table, string $column): bool
{
    global $pdo;

    try {
        if (function_exists('column_exists')) {
            return column_exists($table, $column);
        }

        if (!request_view_table_exists($table)) {
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

function request_view_decode(?string $json): array
{
    if (!$json) {
        return [];
    }

    $data = json_decode($json, true);

    return is_array($data) ? $data : [];
}

function request_view_label(string $type): string
{
    $labels = [
        'doctor_profile_create' => 'Doctor Profile Create',
        'hospital_profile_create' => 'Hospital Profile Create',
        'doctor_profile_update' => 'Doctor Profile Update',
        'hospital_profile_update' => 'Hospital Profile Update',
        'chamber_add' => 'Hospital Add',
        'chamber_update' => 'Hospital Update',
        'hospital_doctor_add' => 'Hospital Doctor Add',
    ];

    return $labels[$type] ?? ucwords(str_replace('_', ' ', $type));
}

function request_view_status_class(string $status): string
{
    if ($status === 'approved') {
        return 'approved';
    }

    if ($status === 'rejected') {
        return 'rejected';
    }

    return 'pending';
}

function request_view_value(array $data, array $keys): string
{
    foreach ($keys as $key) {
        if (array_key_exists($key, $data) && trim((string)$data[$key]) !== '') {
            return trim((string)$data[$key]);
        }
    }

    return '';
}

function request_view_pretty_key(string $key): string
{
    $labels = [
        'name' => 'Name',
        'doctor_name' => 'Doctor Name',
        'hospital_name' => 'Hospital Name',
        'chamber_name' => 'Hospital Name',
        'email' => 'Email',
        'phone' => 'Phone',
        'mobile' => 'Mobile',
        'contact_phone' => 'Contact Phone',
        'contact_email' => 'Contact Email',
        'specialty_id' => 'Specialty',
        'specialty_name' => 'Specialty',
        'speciality_id' => 'Specialty',
        'speciality_name' => 'Specialty',
        'district' => 'Zilla',
        'zilla' => 'Zilla',
        'zila' => 'Zilla',
        'city' => 'Zilla',
        'thana' => 'Thana',
        'upazila' => 'Thana',
        'area' => 'Thana',
        'address' => 'Address',
        'gender' => 'Gender',
        'designation' => 'Designation',
        'degrees' => 'Degrees',
        'experience' => 'Starting Year',
        'experience_years' => 'Starting Year',
        'bio' => 'Bio',
        'description' => 'Description',
        'consultation_fee' => 'Consultation Fee',
        'follow_up_fee' => 'Follow Up Fee',
        'video_consultation_fee' => 'Video Consultation Fee',
        'visiting_fee' => 'Visiting Fee',
        'appointment_phone' => 'Appointment Phone',
        'serial_phone' => 'Serial Phone',
        'serial_no' => 'Serial No',
        'image' => 'Image',
        'photo' => 'Photo',
        'logo' => 'Logo',
        'primary_hospital' => 'Primary Hospital',
        'languages' => 'Languages',
    ];

    return $labels[$key] ?? ucwords(str_replace('_', ' ', $key));
}

function request_view_money($value): string
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

function request_view_is_fee_key(string $key): bool
{
    return in_array($key, [
        'consultation_fee',
        'follow_up_fee',
        'video_consultation_fee',
        'visiting_fee',
    ], true);
}

function request_view_compare_value(string $key, $value): string
{
    $value = trim((string)$value);

    if ($value === '') {
        return '';
    }

    if (request_view_is_fee_key($key)) {
        return request_view_money($value);
    }

    return $value;
}

function request_view_find_name_by_id(string $table, int $id, array $columns = ['name', 'title']): string
{
    global $pdo;

    if ($id <= 0 || !request_view_table_exists($table)) {
        return '';
    }

    $name_column = '';

    foreach ($columns as $column) {
        if (request_view_column_exists($table, $column)) {
            $name_column = $column;
            break;
        }
    }

    if ($name_column === '') {
        return '';
    }

    try {
        $stmt = $pdo->prepare("SELECT `{$name_column}` FROM `{$table}` WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);

        $name = $stmt->fetchColumn();

        return $name !== false ? trim((string)$name) : '';
    } catch (Throwable $e) {
        return '';
    }
}

function request_view_id_name(string $key, $value): string
{
    $value = trim((string)$value);

    if ($value === '' || !is_numeric($value) || (int)$value <= 0) {
        return '';
    }

    $id = (int)$value;
    $key = strtolower(trim($key));

    $maps = [
        'user_id' => [
            ['users', ['name', 'full_name', 'email', 'username']],
        ],
        'doctor_id' => [
            ['doctors', ['name', 'doctor_name', 'title']],
        ],
        'hospital_id' => [
            ['hospitals', ['name', 'hospital_name', 'title']],
        ],
        'chamber_id' => [
            ['chambers', ['name', 'chamber_name', 'hospital_name', 'title']],
            ['hospitals', ['name', 'hospital_name', 'title']],
        ],
        'specialty_id' => [
            ['specialties', ['name', 'title', 'specialty_name']],
            ['specialities', ['name', 'title', 'specialty_name']],
            ['doctor_specialties', ['name', 'title', 'specialty_name']],
        ],
        'speciality_id' => [
            ['specialties', ['name', 'title', 'specialty_name']],
            ['specialities', ['name', 'title', 'specialty_name']],
            ['doctor_specialties', ['name', 'title', 'specialty_name']],
        ],
        'doctor_specialty_id' => [
            ['specialties', ['name', 'title', 'specialty_name']],
            ['specialities', ['name', 'title', 'specialty_name']],
            ['doctor_specialties', ['name', 'title', 'specialty_name']],
        ],
        'division_id' => [
            ['divisions', ['name', 'name_en', 'title']],
        ],
        'doctor_division_id' => [
            ['divisions', ['name', 'name_en', 'title']],
        ],
        'district_id' => [
            ['districts', ['name', 'name_en', 'title']],
        ],
        'doctor_district_id' => [
            ['districts', ['name', 'name_en', 'title']],
        ],
        'thana_id' => [
            ['thanas', ['name', 'name_en', 'title']],
        ],
        'doctor_thana_id' => [
            ['thanas', ['name', 'name_en', 'title']],
        ],
        'upazila_id' => [
            ['thanas', ['name', 'name_en', 'title']],
        ],
    ];

    if (!isset($maps[$key])) {
        return '';
    }

    foreach ($maps[$key] as $config) {
        [$table, $columns] = $config;

        $name = request_view_find_name_by_id($table, $id, $columns);

        if ($name !== '') {
            return $name;
        }
    }

    return '';
}

function request_view_id_name_or_value(string $key, $value): string
{
    $name = request_view_id_name($key, $value);

    if ($name !== '') {
        return $name;
    }

    return trim((string)$value);
}

function request_view_specialty_name(array $request_data, array $target_row = []): string
{
    $direct_name = request_view_value($request_data, [
        'specialty',
        'specialty_name',
        'doctor_specialty',
        'speciality',
        'speciality_name',
    ]);

    if ($direct_name !== '') {
        return $direct_name;
    }

    $target_direct_name = request_view_value($target_row, [
        'specialty',
        'specialty_name',
        'doctor_specialty',
        'speciality',
        'speciality_name',
    ]);

    if ($target_direct_name !== '') {
        return $target_direct_name;
    }

    $specialty_id = (int)request_view_value($request_data, [
        'specialty_id',
        'speciality_id',
        'doctor_specialty_id',
    ]);

    if ($specialty_id <= 0) {
        $specialty_id = (int)request_view_value($target_row, [
            'specialty_id',
            'speciality_id',
            'doctor_specialty_id',
        ]);
    }

    if ($specialty_id <= 0) {
        return '';
    }

    foreach (['specialties', 'specialities', 'doctor_specialties'] as $table) {
        $name = request_view_find_name_by_id($table, $specialty_id, ['name', 'title', 'specialty_name']);

        if ($name !== '') {
            return $name;
        }
    }

    return '';
}

function request_view_fetch_row(string $table, int $id): array
{
    global $pdo;

    if ($id <= 0 || !request_view_table_exists($table)) {
        return [];
    }

    try {
        $stmt = $pdo->prepare("SELECT * FROM `{$table}` WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : [];
    } catch (Throwable $e) {
        return [];
    }
}

function request_view_normalize_compare_data(array $data): array
{
    $hidden_keys = [
        'id',
        'user_id',
        'doctor_id',
        'hospital_id',
        'chamber_id',
        'created_at',
        'updated_at',
        'deleted_at',
        'password',
        'remember_token',
    ];

    $normalized = [];

    foreach ($data as $key => $value) {
        $key = (string)$key;

        if (in_array($key, $hidden_keys, true)) {
            continue;
        }

        if (is_array($value)) {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE);
        }

        $normalized[$key] = trim((string)$value);
    }

    return $normalized;
}

function request_view_display_value_by_key(string $key, $value): string
{
    // The experience_years field stores the doctor's starting year, for example 2010.
    $value = trim((string)$value);

    if (request_view_is_fee_key($key)) {
        return request_view_money($value);
    }

    if ($value === '') {
        return '—';
    }

    $name = request_view_id_name((string)$key, $value);

    if ($name !== '') {
        return $name;
    }

    return $value;
}

function request_view_slugify(string $text): string
{
    $text = trim($text);

    if ($text === '') {
        return '';
    }

    if (function_exists('seo_name_to_slug')) {
        return seo_name_to_slug($text);
    }

    $text = strtolower($text);
    $text = preg_replace('/[^a-z0-9]+/i', '-', $text);
    $text = trim((string)$text, '-');

    return $text;
}

function request_view_profile_slug(array $row, string $fallback_name = ''): string
{
    foreach (['slug', 'seo_slug', 'profile_slug'] as $key) {
        if (!empty($row[$key])) {
            return trim((string)$row[$key]);
        }
    }

    $name = '';

    foreach (['name', 'doctor_name', 'hospital_name', 'title'] as $key) {
        if (!empty($row[$key])) {
            $name = trim((string)$row[$key]);
            break;
        }
    }

    if ($name === '') {
        $name = $fallback_name;
    }

    return request_view_slugify($name);
}

function request_view_public_url(string $type, string $slug): string
{
    if ($slug === '') {
        return '';
    }

    if ($type === 'Doctor') {
        return '../doctor/' . $slug;
    }

    if ($type === 'Hospital') {
        return '../hospital/' . $slug;
    }

    return '';
}

function request_view_prepare_card_data(array $row, array $request_data, string $type, string $target_name, string $slug): array
{
    $card = $row;

    foreach ($request_data as $key => $value) {
        if (!array_key_exists($key, $card) || trim((string)($card[$key] ?? '')) === '') {
            $card[$key] = $value;
        }
    }

    if ($target_name !== '') {
        $card['name'] = $target_name;
    }

    if ($slug !== '') {
        $card['slug'] = $slug;
    }

    foreach (['consultation_fee', 'follow_up_fee', 'video_consultation_fee', 'visiting_fee'] as $fee_key) {
        $card[$fee_key] = request_view_money($card[$fee_key] ?? '0');
    }

    if ($type === 'Doctor') {
        if (empty($card['doctor_name']) && !empty($card['name'])) {
            $card['doctor_name'] = $card['name'];
        }

        if (empty($card['specialty_name'])) {
            $specialty_name = request_view_specialty_name($request_data, $row);

            if ($specialty_name !== '') {
                $card['specialty_name'] = $specialty_name;
            }
        }
    }

    if ($type === 'Hospital') {
        if (empty($card['hospital_name']) && !empty($card['name'])) {
            $card['hospital_name'] = $card['name'];
        }
    }

    return $card;
}

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    redirect('user-requests.php');
}

$request = null;

try {
    $stmt = $pdo->prepare("
        SELECT
            pur.*,
            u.name AS user_name,
            u.email AS user_email,
            u.phone AS user_phone,
            u.user_type AS user_type
        FROM profile_update_requests pur
        LEFT JOIN users u ON u.id = pur.user_id
        WHERE pur.id = :id
        LIMIT 1
    ");

    $stmt->execute([':id' => $id]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $request = null;
}

if (!$request) {
    require_once __DIR__ . '/includes/header.php';
    ?>

    <div style="padding:22px;background:#ffebe9;border:1px solid rgba(207,34,46,.25);color:#cf222e;border-radius:10px;">
        <h2 style="margin-top:0;">Request not found</h2>
        <p>The requested item does not exist.</p>
        <a href="user-requests.php">Back to User Requests</a>
    </div>

    <?php
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

$request_data = request_view_decode($request['request_data'] ?? '');
$status = (string)($request['status'] ?? 'pending');
$request_type = (string)($request['request_type'] ?? '');

$user_id = (int)($request['user_id'] ?? 0);
$user_name = trim((string)($request['user_name'] ?? ''));
$user_email = trim((string)($request['user_email'] ?? ''));
$user_phone = trim((string)($request['user_phone'] ?? ''));
$user_type = trim((string)($request['user_type'] ?? ''));

$doctor_id = (int)($request['doctor_id'] ?? 0);
$hospital_id = (int)($request['hospital_id'] ?? 0);
$chamber_id = (int)($request['chamber_id'] ?? 0);

$target_type = '';
$target_table = '';
$target_id = 0;
$target_name = '';
$target_row = [];

if ($doctor_id > 0) {
    $target_type = 'Doctor';
    $target_table = 'doctors';
    $target_id = $doctor_id;
    $target_name = request_view_find_name_by_id('doctors', $doctor_id, ['name', 'doctor_name', 'title']);
    $target_row = request_view_fetch_row('doctors', $doctor_id);
}

if ($hospital_id > 0) {
    $target_type = 'Hospital';
    $target_table = 'hospitals';
    $target_id = $hospital_id;
    $target_name = request_view_find_name_by_id('hospitals', $hospital_id, ['name', 'hospital_name', 'title']);
    $target_row = request_view_fetch_row('hospitals', $hospital_id);
}

if ($chamber_id > 0 && $target_id <= 0) {
    $target_type = 'Hospital';
    $target_table = 'chambers';
    $target_id = $chamber_id;
    $target_name = request_view_find_name_by_id('chambers', $chamber_id, ['name', 'chamber_name', 'hospital_name', 'title']);
    $target_row = request_view_fetch_row('chambers', $chamber_id);

    if (!$target_row) {
        $target_table = 'hospitals';
        $target_name = request_view_find_name_by_id('hospitals', $chamber_id, ['name', 'hospital_name', 'title']);
        $target_row = request_view_fetch_row('hospitals', $chamber_id);
    }
}

if ($target_name === '') {
    $target_name = request_view_value($request_data, [
        'doctor_name',
        'hospital_name',
        'chamber_name',
        'name',
        'primary_hospital',
    ]);
}

$submitted_title = request_view_value($request_data, [
    'name',
    'doctor_name',
    'hospital_name',
    'chamber_name',
    'primary_hospital',
    'title',
]);

if ($submitted_title === '' && $target_name !== '') {
    $submitted_title = $target_name;
}

if ($target_type === '' && str_contains($request_type, 'doctor')) {
    $target_type = 'Doctor';
}

if ($target_type === '' && (str_contains($request_type, 'hospital') || str_contains($request_type, 'chamber'))) {
    $target_type = 'Hospital';
}

$specialty_name = request_view_specialty_name($request_data, $target_row);

$thana = request_view_value($request_data, [
    'thana',
    'upazila',
    'area',
    'police_station',
    'location',
]);

if ($thana === '') {
    $thana = request_view_value($target_row, [
        'thana',
        'upazila',
        'area',
        'police_station',
        'location',
    ]);
}

if ($thana === '') {
    $thana_id_value = request_view_value($request_data, ['thana_id', 'doctor_thana_id', 'upazila_id']);

    if ($thana_id_value === '') {
        $thana_id_value = request_view_value($target_row, ['thana_id', 'doctor_thana_id', 'upazila_id']);
    }

    $thana = request_view_id_name_or_value('thana_id', $thana_id_value);
}

$zilla = request_view_value($request_data, [
    'zilla',
    'district',
    'zila',
    'city',
]);

if ($zilla === '') {
    $zilla = request_view_value($target_row, [
        'zilla',
        'district',
        'zila',
        'city',
    ]);
}

if ($zilla === '') {
    $district_id_value = request_view_value($request_data, ['district_id', 'doctor_district_id']);

    if ($district_id_value === '') {
        $district_id_value = request_view_value($target_row, ['district_id', 'doctor_district_id']);
    }

    $zilla = request_view_id_name_or_value('district_id', $district_id_value);
}

$current_data = request_view_normalize_compare_data($target_row);
$new_data = request_view_normalize_compare_data($request_data);

$compare_keys = array_values(array_unique(array_merge(array_keys($new_data), array_keys($current_data))));

$changed_count = 0;
$new_field_count = 0;
$unchanged_count = 0;

foreach ($compare_keys as $key) {
    $old_value = trim((string)($current_data[$key] ?? ''));
    $new_value = trim((string)($new_data[$key] ?? ''));

    $old_compare = request_view_compare_value((string)$key, $old_value);
    $new_compare = request_view_compare_value((string)$key, $new_value);

    if ($new_compare === '') {
        continue;
    }

    if ($old_compare === '' && $new_compare !== '') {
        $new_field_count++;
    } elseif ($old_compare !== $new_compare) {
        $changed_count++;
    } else {
        $unchanged_count++;
    }
}

$target_slug = request_view_profile_slug($target_row, $target_name ?: $submitted_title);
$target_view_url = request_view_public_url($target_type, $target_slug);
$target_edit_url = '';

if ($target_type === 'Doctor' && $target_id > 0) {
    $target_edit_url = 'doctor-form.php?id=' . $target_id;
}

if ($target_type === 'Hospital' && $target_id > 0) {
    $target_edit_url = 'hospital-form.php?id=' . $target_id;
}

$user_view_url = $user_id > 0 ? 'user-view.php?id=' . $user_id : '';

$target_card_data = request_view_prepare_card_data($target_row, $request_data, $target_type, $target_name ?: $submitted_title, $target_slug);

require_once __DIR__ . '/includes/header.php';
?>

<style>
    .rv-page {
        color: #24292f;
    }

    .rv-hero {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 16px;
        flex-wrap: wrap;
        padding: 18px;
        margin-bottom: 16px;
        border: 1px solid #d0d7de;
        border-radius: 10px;
        background: #ffffff;
        box-shadow: 0 1px 0 rgba(27, 31, 36, 0.04);
    }

    .rv-hero h1 {
        margin: 0 0 6px;
        font-size: 24px;
        line-height: 1.2;
        letter-spacing: -0.03em;
        color: #24292f;
    }

    .rv-hero p {
        margin: 0;
        max-width: 820px;
        color: #57606a;
        font-size: 14px;
        line-height: 1.6;
    }

    .rv-hero-actions,
    .rv-actions-row,
    .rv-profile-actions {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }

    .rv-btn {
        min-height: 34px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 7px;
        padding: 7px 12px;
        border-radius: 6px;
        border: 1px solid rgba(27, 31, 36, 0.15);
        background: #f6f8fa;
        color: #24292f;
        font-size: 13px;
        font-weight: 700;
        line-height: 1;
        text-decoration: none;
        cursor: pointer;
        box-shadow: 0 1px 0 rgba(27, 31, 36, 0.04);
        font-family: inherit;
    }

    .rv-btn:hover {
        background: #eef1f4;
        color: #24292f;
        text-decoration: none;
    }

    .rv-btn-primary {
        background: #2da44e;
        color: #ffffff;
    }

    .rv-btn-primary:hover {
        background: #1f883d;
        color: #ffffff;
    }

    .rv-btn-blue {
        background: #0969da;
        color: #ffffff;
    }

    .rv-btn-blue:hover {
        background: #0757b8;
        color: #ffffff;
    }

    .rv-btn-danger {
        background: #ffebe9;
        color: #cf222e;
        border-color: rgba(207, 34, 46, 0.25);
    }

    .rv-btn-danger:hover {
        background: #ffd8d3;
        color: #a40e26;
    }

    .rv-profile-panel {
        margin-bottom: 16px;
        border: 1px solid #d0d7de;
        border-radius: 10px;
        background: #ffffff;
        box-shadow: 0 1px 0 rgba(27, 31, 36, 0.04);
        overflow: hidden;
    }

    .rv-profile-panel-head {
        padding: 14px 16px;
        background: #f6f8fa;
        border-bottom: 1px solid #d0d7de;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        flex-wrap: wrap;
    }

    .rv-profile-panel-head h2 {
        margin: 0;
        color: #24292f;
        font-size: 15px;
    }

    .rv-profile-panel-body {
        padding: 16px;
    }

    .rv-profile-card-wrap {
        border: 1px solid #d8dee4;
        border-radius: 10px;
        background: #ffffff;
        overflow: hidden;
    }

    .rv-profile-card-wrap .doctor-card,
    .rv-profile-card-wrap .hospital-card {
        margin: 0 !important;
        border: 0 !important;
        box-shadow: none !important;
    }

    .rv-stats {
        display: grid;
        grid-template-columns: repeat(4, minmax(150px, 1fr));
        gap: 12px;
        margin-bottom: 16px;
    }

    .rv-stat {
        padding: 15px;
        border-radius: 10px;
        background: #ffffff;
        border: 1px solid #d0d7de;
        box-shadow: 0 1px 0 rgba(27, 31, 36, 0.04);
    }

    .rv-stat span {
        display: block;
        color: #57606a;
        font-size: 13px;
        margin-bottom: 7px;
        font-weight: 600;
    }

    .rv-stat strong {
        display: block;
        font-size: 26px;
        line-height: 1;
        color: #24292f;
    }

    .rv-layout {
        display: grid;
        grid-template-columns: 350px 1fr;
        gap: 16px;
        align-items: start;
    }

    .rv-stack {
        display: grid;
        gap: 16px;
    }

    .rv-card {
        overflow: hidden;
        border-radius: 10px;
        background: #ffffff;
        border: 1px solid #d0d7de;
        box-shadow: 0 1px 0 rgba(27, 31, 36, 0.04);
    }

    .rv-card-header {
        padding: 14px 16px;
        border-bottom: 1px solid #d0d7de;
        background: #f6f8fa;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        flex-wrap: wrap;
    }

    .rv-card-header h2 {
        margin: 0;
        font-size: 15px;
        color: #24292f;
    }

    .rv-card-header p,
    .rv-card-header span {
        margin: 0;
        color: #57606a;
        font-size: 13px;
        line-height: 1.5;
    }

    .rv-card-body {
        padding: 16px;
    }

    .rv-info-list {
        display: grid;
        gap: 10px;
    }

    .rv-info-row {
        display: grid;
        gap: 5px;
        padding: 11px 12px;
        border: 1px solid #d8dee4;
        border-radius: 8px;
        background: #ffffff;
    }

    .rv-info-row span {
        color: #57606a;
        font-size: 12px;
        font-weight: 600;
    }

    .rv-info-row strong,
    .rv-info-row div {
        color: #24292f;
        font-size: 13px;
        line-height: 1.5;
        word-break: break-word;
    }

    .rv-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 24px;
        padding: 4px 9px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 700;
        white-space: nowrap;
        border: 1px solid #d8dee4;
        background: #f6f8fa;
        color: #57606a;
    }

    .rv-badge.pending {
        color: #9a6700;
        background: #fff8c5;
        border-color: rgba(154, 103, 0, 0.25);
    }

    .rv-badge.approved {
        color: #1a7f37;
        background: #dafbe1;
        border-color: rgba(26, 127, 55, 0.25);
    }

    .rv-badge.rejected {
        color: #cf222e;
        background: #ffebe9;
        border-color: rgba(207, 34, 46, 0.25);
    }

    .rv-badge.changed {
        color: #9a6700;
        background: #fff8c5;
        border-color: rgba(154, 103, 0, 0.25);
    }

    .rv-badge.new {
        color: #0969da;
        background: #ddf4ff;
        border-color: rgba(9, 105, 218, 0.25);
    }

    .rv-badge.same {
        color: #57606a;
        background: #f6f8fa;
        border-color: #d8dee4;
    }

    .rv-compare-wrap,
    .rv-table-wrap {
        overflow-x: auto;
    }

    .rv-compare-table,
    .rv-table {
        width: 100%;
        min-width: 850px;
        border-collapse: separate;
        border-spacing: 0;
    }

    .rv-compare-table th,
    .rv-compare-table td,
    .rv-table th,
    .rv-table td {
        padding: 12px;
        border-bottom: 1px solid #d8dee4;
        text-align: left;
        vertical-align: top;
        font-size: 13px;
    }

    .rv-compare-table th,
    .rv-table th {
        background: #f6f8fa;
        color: #57606a;
        font-weight: 700;
    }

    .rv-compare-table tr:last-child td,
    .rv-table tr:last-child td {
        border-bottom: 0;
    }

    .rv-row-changed {
        background: #fffdf0;
    }

    .rv-row-new {
        background: #f0f8ff;
    }

    .rv-old-value {
        color: #cf222e;
        background: #ffebe9;
        border: 1px solid rgba(207, 34, 46, 0.20);
        border-radius: 6px;
        padding: 7px 9px;
        min-height: 34px;
        white-space: pre-wrap;
    }

    .rv-new-value {
        color: #1a7f37;
        background: #dafbe1;
        border: 1px solid rgba(26, 127, 55, 0.22);
        border-radius: 6px;
        padding: 7px 9px;
        min-height: 34px;
        white-space: pre-wrap;
    }

    .rv-same-value {
        color: #57606a;
        background: #f6f8fa;
        border: 1px solid #d8dee4;
        border-radius: 6px;
        padding: 7px 9px;
        min-height: 34px;
        white-space: pre-wrap;
    }

    .rv-target-box {
        padding: 14px;
        border-radius: 8px;
        border: 1px solid #d8dee4;
        background: #ffffff;
    }

    .rv-target-box h3 {
        margin: 0 0 6px;
        color: #24292f;
        font-size: 15px;
    }

    .rv-target-box p {
        margin: 0;
        color: #57606a;
        font-size: 13px;
        line-height: 1.5;
    }

    .rv-target-actions {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        margin-top: 12px;
    }

    .rv-note {
        width: 100%;
        min-height: 96px;
        border: 1px solid #d0d7de;
        border-radius: 8px;
        padding: 10px 12px;
        resize: vertical;
        outline: none;
        font-family: inherit;
    }

    .rv-note:focus {
        border-color: #0969da;
        box-shadow: 0 0 0 3px rgba(9, 105, 218, 0.15);
    }

    .rv-empty {
        padding: 22px;
        text-align: center;
        color: #57606a;
        font-size: 14px;
        background: #f6f8fa;
        border: 1px dashed #d0d7de;
        border-radius: 8px;
    }

    @media (max-width: 1100px) {
        .rv-layout {
            grid-template-columns: 1fr;
        }

        .rv-stats {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 700px) {
        .rv-hero,
        .rv-card-body,
        .rv-profile-panel-body {
            padding: 14px;
        }

        .rv-stats {
            grid-template-columns: 1fr;
        }

        .rv-btn {
            width: 100%;
        }
    }
</style>

<div class="rv-page">

    <div class="rv-hero">
        <div>
            <h1><?= e(request_view_label($request_type)) ?></h1>
            <p>
                Review the submitted request, compare old and new profile data, then approve or reject it.
            </p>
        </div>

        <div class="rv-hero-actions">
            <a href="user-requests.php" class="rv-btn">Back to Requests</a>

            <?php if ($user_view_url !== ''): ?>
                <a href="<?= e($user_view_url) ?>" class="rv-btn">User View</a>
            <?php endif; ?>

            <?php if ($target_view_url !== ''): ?>
                <a href="<?= e($target_view_url) ?>" target="_blank" rel="noopener" class="rv-btn rv-btn-blue">View Profile</a>
            <?php endif; ?>

            <?php if ($target_edit_url !== ''): ?>
                <a href="<?= e($target_edit_url) ?>" class="rv-btn rv-btn-primary">Edit Profile</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="rv-profile-panel">
        <div class="rv-profile-panel-head">
            <div>
                <h2><?= e($target_type !== '' ? $target_type . ' Profile Preview' : 'Profile Preview') ?></h2>
            </div>
        </div>

        <div class="rv-profile-panel-body">
            <div class="rv-profile-card-wrap">
                <?php if ($target_type === 'Doctor'): ?>
                    <?php
                    $doctor = $target_card_data;
                    include __DIR__ . '/../includes/doctor-card.php';
                    ?>
                <?php elseif ($target_type === 'Hospital'): ?>
                    <?php
                    $hospital = $target_card_data;
                    include __DIR__ . '/../includes/hospital-card.php';
                    ?>
                <?php else: ?>
                    <div class="rv-empty">
                        No doctor or hospital profile card available for this request.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="rv-stats">
        <div class="rv-stat">
            <span>Changed Fields</span>
            <strong><?= e((string)$changed_count) ?></strong>
        </div>

        <div class="rv-stat">
            <span>New Fields</span>
            <strong><?= e((string)$new_field_count) ?></strong>
        </div>

        <div class="rv-stat">
            <span>Same Fields</span>
            <strong><?= e((string)$unchanged_count) ?></strong>
        </div>

        <div class="rv-stat">
            <span>Status</span>
            <strong style="font-size:16px;">
                <span class="rv-badge <?= e(request_view_status_class($status)) ?>">
                    <?= e(ucfirst($status)) ?>
                </span>
            </strong>
        </div>
    </div>

    <div class="rv-layout">

        <aside class="rv-stack">

            <div class="rv-card">
                <div class="rv-card-header">
                    <h2>Request Summary</h2>
                    <span>#<?= e((string)$request['id']) ?></span>
                </div>

                <div class="rv-card-body">
                    <div class="rv-info-list">
                        <div class="rv-info-row">
                            <span>Request Type</span>
                            <strong><?= e(request_view_label($request_type)) ?></strong>
                        </div>

                        <div class="rv-info-row">
                            <span>Status</span>
                            <strong>
                                <span class="rv-badge <?= e(request_view_status_class($status)) ?>">
                                    <?= e(ucfirst($status)) ?>
                                </span>
                            </strong>
                        </div>

                        <div class="rv-info-row">
                            <span>Submitted Title</span>
                            <strong><?= e($submitted_title !== '' ? $submitted_title : 'No title') ?></strong>
                        </div>

                        <?php if ($specialty_name !== ''): ?>
                            <div class="rv-info-row">
                                <span>Specialty</span>
                                <strong><?= e($specialty_name) ?></strong>
                            </div>
                        <?php endif; ?>

                        <?php if ($thana !== ''): ?>
                            <div class="rv-info-row">
                                <span>Thana</span>
                                <strong><?= e($thana) ?></strong>
                            </div>
                        <?php endif; ?>

                        <?php if ($zilla !== ''): ?>
                            <div class="rv-info-row">
                                <span>Zilla</span>
                                <strong><?= e($zilla) ?></strong>
                            </div>
                        <?php endif; ?>

                        <div class="rv-info-row">
                            <span>Submitted At</span>
                            <strong>
                                <?= !empty($request['created_at']) ? e(date('d M Y h:i A', strtotime((string)$request['created_at']))) : '—' ?>
                            </strong>
                        </div>

                        <?php if (!empty($request['admin_note'])): ?>
                            <div class="rv-info-row">
                                <span>Admin Note</span>
                                <strong><?= e($request['admin_note']) ?></strong>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="rv-card">
                <div class="rv-card-header">
                    <h2>User</h2>
                    <span>Requester</span>
                </div>

                <div class="rv-card-body">
                    <div class="rv-info-list">
                        <div class="rv-info-row">
                            <span>Name</span>
                            <strong><?= e($user_name !== '' ? $user_name : 'Unknown User') ?></strong>
                        </div>

                        <?php if ($user_email !== ''): ?>
                            <div class="rv-info-row">
                                <span>Email</span>
                                <strong><?= e($user_email) ?></strong>
                            </div>
                        <?php endif; ?>

                        <?php if ($user_phone !== ''): ?>
                            <div class="rv-info-row">
                                <span>Phone</span>
                                <strong><?= e($user_phone) ?></strong>
                            </div>
                        <?php endif; ?>

                        <?php if ($user_type !== ''): ?>
                            <div class="rv-info-row">
                                <span>User Type</span>
                                <strong><?= e($user_type) ?></strong>
                            </div>
                        <?php endif; ?>

                        <?php if ($user_view_url !== ''): ?>
                            <div class="rv-info-row">
                                <span>User Profile</span>
                                <strong>
                                    <a href="<?= e($user_view_url) ?>" class="rv-btn">Open User View</a>
                                </strong>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="rv-card">
                <div class="rv-card-header">
                    <h2>Target Profile</h2>
                    <span>View / Edit</span>
                </div>

                <div class="rv-card-body">
                    <?php if ($target_id > 0): ?>
                        <div class="rv-target-box">
                            <h3><?= e($target_name !== '' ? $target_name : $target_type . ' Profile') ?></h3>
                            <p><?= e($target_type) ?> profile connected with this request.</p>

                            <div class="rv-target-actions">
                                <?php if ($target_view_url !== ''): ?>
                                    <a href="<?= e($target_view_url) ?>" target="_blank" rel="noopener" class="rv-btn rv-btn-blue">View Profile</a>
                                <?php endif; ?>

                                <?php if ($target_edit_url !== ''): ?>
                                    <a href="<?= e($target_edit_url) ?>" class="rv-btn rv-btn-primary">Edit Profile</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="rv-empty">
                            This is a new profile request. No existing doctor or hospital profile is connected yet.
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($status === 'pending'): ?>
                <div class="rv-card">
                    <div class="rv-card-header">
                        <h2>Admin Action</h2>
                        <span>Approve or reject</span>
                    </div>

                    <div class="rv-card-body">
                        <div class="rv-stack">
                            <form method="post" action="user-request-action.php" onsubmit="return confirm('Approve this request?');">
                                <input type="hidden" name="id" value="<?= e((string)$request['id']) ?>">
                                <input type="hidden" name="action" value="approve">
                                <button type="submit" class="rv-btn rv-btn-primary" style="width:100%;">
                                    Approve Request
                                </button>
                            </form>

                            <form method="post" action="user-request-action.php" onsubmit="return confirm('Reject this request?');">
                                <input type="hidden" name="id" value="<?= e((string)$request['id']) ?>">
                                <input type="hidden" name="action" value="reject">

                                <textarea name="admin_note" class="rv-note" placeholder="Write rejection reason or admin note"></textarea>

                                <button type="submit" class="rv-btn rv-btn-danger" style="width:100%;margin-top:10px;">
                                    Reject Request
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

        </aside>

        <main class="rv-stack">

            <div class="rv-card">
                <div class="rv-card-header">
                    <div>
                        <h2>Before vs After Changes</h2>
                        <p>Red means old data, green means submitted new data. Fee fields are normalized, so 1000 and 1000.00 are treated as the same.</p>
                    </div>
                </div>

                <div class="rv-card-body">
                    <?php if (!$new_data): ?>
                        <div class="rv-empty">No submitted data found for comparison.</div>
                    <?php else: ?>
                        <div class="rv-compare-wrap">
                            <table class="rv-compare-table">
                                <thead>
                                    <tr>
                                        <th>Field</th>
                                        <th>Before</th>
                                        <th>After</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>

                                <tbody>
                                    <?php foreach ($compare_keys as $key): ?>
                                        <?php
                                            $old_raw = trim((string)($current_data[$key] ?? ''));
                                            $new_raw = trim((string)($new_data[$key] ?? ''));

                                            $old_compare = request_view_compare_value((string)$key, $old_raw);
                                            $new_compare = request_view_compare_value((string)$key, $new_raw);

                                            if ($new_compare === '') {
                                                continue;
                                            }

                                            $old_display = request_view_display_value_by_key((string)$key, $old_raw);
                                            $new_display = request_view_display_value_by_key((string)$key, $new_raw);

                                            $row_class = '';
                                            $badge_class = 'same';
                                            $badge_text = 'Same';
                                            $old_class = 'rv-same-value';
                                            $new_class = 'rv-same-value';

                                            if ($old_compare === '' && $new_compare !== '') {
                                                $row_class = 'rv-row-new';
                                                $badge_class = 'new';
                                                $badge_text = 'New Field';
                                                $old_class = 'rv-old-value';
                                                $new_class = 'rv-new-value';
                                            } elseif ($old_compare !== $new_compare) {
                                                $row_class = 'rv-row-changed';
                                                $badge_class = 'changed';
                                                $badge_text = 'Changed';
                                                $old_class = 'rv-old-value';
                                                $new_class = 'rv-new-value';
                                            }
                                        ?>

                                        <tr class="<?= e($row_class) ?>">
                                            <td>
                                                <strong><?= e(request_view_pretty_key((string)$key)) ?></strong>
                                                <div style="color:#57606a;font-size:12px;margin-top:3px;"><?= e((string)$key) ?></div>
                                            </td>

                                            <td>
                                                <div class="<?= e($old_class) ?>"><?= e($old_display) ?></div>
                                            </td>

                                            <td>
                                                <div class="<?= e($new_class) ?>"><?= e($new_display) ?></div>
                                            </td>

                                            <td>
                                                <span class="rv-badge <?= e($badge_class) ?>"><?= e($badge_text) ?></span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="rv-card">
                <div class="rv-card-header">
                    <div>
                        <h2>Submitted Raw Data</h2>
                        <p>Full request_data JSON values submitted from user panel.</p>
                    </div>
                </div>

                <div class="rv-card-body">
                    <div class="rv-table-wrap">
                        <table class="rv-table">
                            <?php if (!$request_data): ?>
                                <tr>
                                    <td>No request data found.</td>
                                </tr>
                            <?php endif; ?>

                            <?php foreach ($request_data as $key => $value): ?>
                                <tr>
                                    <th><?= e(request_view_pretty_key((string)$key)) ?></th>
                                    <td>
                                        <?php if (is_array($value)): ?>
                                            <?= e(json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)) ?>
                                        <?php else: ?>
                                            <?= e(request_view_display_value_by_key((string)$key, $value)) ?>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </table>
                    </div>
                </div>
            </div>

        </main>

    </div>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>