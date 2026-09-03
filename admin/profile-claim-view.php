<?php
require_once __DIR__ . '/../includes/functions.php';

require_admin();

function pcv_table_exists(string $table): bool
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

function pcv_column_exists(string $table, string $column): bool
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

function pcv_select_column(string $table, string $alias, string $column, string $as): string
{
    return pcv_column_exists($table, $column)
        ? "{$alias}.{$column} AS {$as}"
        : "NULL AS {$as}";
}

function pcv_address_name_by_id(string $table, $id, string $lang = 'en'): string
{
    global $pdo;

    $id = (int)$id;

    if ($id <= 0) {
        return '';
    }

    $allowed = ['divisions', 'districts', 'thanas'];

    if (!in_array($table, $allowed, true) || !pcv_table_exists($table)) {
        return '';
    }

    if ($lang === 'bn' && pcv_column_exists($table, 'name_bn')) {
        $name_col = 'name_bn';
    } elseif (pcv_column_exists($table, 'name_en')) {
        $name_col = 'name_en';
    } elseif (pcv_column_exists($table, 'name')) {
        $name_col = 'name';
    } elseif (pcv_column_exists($table, 'title')) {
        $name_col = 'title';
    } else {
        return '';
    }

    try {
        $stmt = $pdo->prepare("SELECT {$name_col} FROM {$table} WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);

        return trim((string)($stmt->fetchColumn() ?: ''));
    } catch (Throwable $e) {
        return '';
    }
}

function pcv_e($value): string
{
    if (function_exists('e')) {
        return e($value);
    }

    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function pcv_value($value): string
{
    $value = trim((string)($value ?? ''));

    return $value !== '' ? $value : '—';
}

function pcv_first_value(array $row, array $keys): string
{
    foreach ($keys as $key) {
        if (isset($row[$key]) && trim((string)$row[$key]) !== '') {
            return trim((string)$row[$key]);
        }
    }

    return '';
}

function pcv_status_class(string $status): string
{
    if ($status === 'approved') {
        return 'approved';
    }

    if ($status === 'rejected') {
        return 'rejected';
    }

    return 'pending';
}

function pcv_type_label(string $type): string
{
    if ($type === 'doctor') {
        return 'Doctor Profile Claim';
    }

    if ($type === 'hospital') {
        return 'Hospital Profile Claim';
    }

    return ucwords(str_replace('_', ' ', $type));
}

function pcv_user_type_label(string $type): string
{
    $type = trim($type);

    if ($type === '') {
        return 'User';
    }

    if ($type === 'hospital_owner') {
        return 'Hospital Owner';
    }

    return ucwords(str_replace('_', ' ', $type));
}

function pcv_clean_compare($value, string $mode = 'text'): string
{
    $value = trim((string)($value ?? ''));

    if ($value === '') {
        return '';
    }

    $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $value = strip_tags($value);
    $value = preg_replace('/\s+/u', ' ', $value);
    $value = trim((string)$value);

    if ($mode === 'email') {
        return strtolower($value);
    }

    if ($mode === 'phone') {
        return preg_replace('/\D+/', '', $value);
    }

    if ($mode === 'license') {
        return strtoupper(preg_replace('/[^a-zA-Z0-9]+/', '', $value));
    }

    if ($mode === 'id') {
        return preg_replace('/\D+/', '', $value);
    }

    if (function_exists('mb_strtolower')) {
        return mb_strtolower($value, 'UTF-8');
    }

    return strtolower($value);
}

function pcv_compare_class($left, $right, string $mode = 'text'): string
{
    $left_clean = pcv_clean_compare($left, $mode);
    $right_clean = pcv_clean_compare($right, $mode);

    if ($left_clean === '' || $right_clean === '') {
        return 'neutral';
    }

    return $left_clean === $right_clean ? 'match' : 'mismatch';
}

function pcv_compare_row_class(array $row): string
{
    $left = $row['left_compare'] ?? $row['left'] ?? '';
    $right = $row['right_compare'] ?? $row['right'] ?? '';
    $mode = (string)($row['mode'] ?? 'text');

    return pcv_compare_class($left, $right, $mode);
}

function pcv_compare_label(string $class): string
{
    if ($class === 'match') {
        return 'Matched';
    }

    if ($class === 'mismatch') {
        return 'Not Matched';
    }

    return 'Missing Data';
}

function pcv_score_status(int $score): string
{
    if ($score >= 90) {
        return 'success';
    }

    if ($score >= 60) {
        return 'warning';
    }

    return 'danger';
}

function pcv_site_setting(string $key, string $default = ''): string
{
    global $pdo;

    $key = trim($key);

    if ($key === '') {
        return $default;
    }

    if (function_exists('get_site_setting')) {
        $value = trim((string)get_site_setting($key, $default));
        return $value !== '' ? $value : $default;
    }

    if (!pcv_table_exists('site_settings')) {
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

        $value = trim((string)$stmt->fetchColumn());

        return $value !== '' ? $value : $default;
    } catch (Throwable $e) {
        return $default;
    }
}

function pcv_url(string $path): string
{
    $path = trim($path);

    if ($path === '') {
        return '';
    }

    if (preg_match('/^https?:\/\//i', $path)) {
        return $path;
    }

    $path = str_replace('\\', '/', $path);
    $path = preg_replace('#^(\.\./)+#', '', $path);
    $path = ltrim($path, '/');

    if (function_exists('site_url')) {
        return site_url($path);
    }

    if (defined('BASE_URL') && BASE_URL !== '') {
        return rtrim((string)BASE_URL, '/') . '/' . $path;
    }

    return '../' . $path;
}

function pcv_admin_file_url(string $path): string
{
    $path = trim($path);

    if ($path === '') {
        return '';
    }

    if (preg_match('/^https?:\/\//i', $path)) {
        return $path;
    }

    $path = str_replace('\\', '/', $path);
    $path = preg_replace('#^(\.\./)+#', '', $path);
    $path = ltrim($path, '/');

    return '../' . $path;
}

function pcv_svg_fallback(string $type, string $name = ''): string
{
    $name = trim($name);

    if ($type === 'hospital') {
        $letter = 'H';
        $bg1 = '#0969da';
        $bg2 = '#8250df';
    } elseif ($type === 'doctor') {
        $letter = 'D';
        $bg1 = '#2da44e';
        $bg2 = '#0969da';
    } else {
        $letter = 'U';
        $bg1 = '#57606a';
        $bg2 = '#0969da';
    }

    if ($name !== '') {
        $letter = function_exists('mb_substr')
            ? mb_substr($name, 0, 1, 'UTF-8')
            : substr($name, 0, 1);
    }

    $label = htmlspecialchars(strtoupper($letter), ENT_QUOTES, 'UTF-8');

    $svg = '
    <svg xmlns="http://www.w3.org/2000/svg" width="240" height="240" viewBox="0 0 240 240">
        <defs>
            <linearGradient id="g" x1="0" y1="0" x2="1" y2="1">
                <stop offset="0%" stop-color="' . $bg1 . '"/>
                <stop offset="100%" stop-color="' . $bg2 . '"/>
            </linearGradient>
        </defs>
        <rect width="240" height="240" rx="34" fill="url(#g)"/>
        <circle cx="120" cy="88" r="38" fill="rgba(255,255,255,.9)"/>
        <rect x="54" y="140" width="132" height="46" rx="23" fill="rgba(255,255,255,.9)"/>
        <text x="120" y="126" text-anchor="middle" font-family="Arial, sans-serif" font-size="64" font-weight="800" fill="#ffffff">' . $label . '</text>
    </svg>';

    return 'data:image/svg+xml;base64,' . base64_encode($svg);
}

function pcv_profile_image(string $type, array $claim): string
{
    if ($type === 'doctor') {
        $default = pcv_site_setting('default_doctor_image', '../assets/images/default-doctor.webp');

        $image = trim((string)(
            $claim['doctor_image']
            ?? $claim['doctor_photo']
            ?? $claim['doctor_profile_image']
            ?? $claim['doctor_avatar']
            ?? ''
        ));

        return pcv_url($image !== '' ? $image : $default);
    }

    if ($type === 'hospital') {
        $default = pcv_site_setting('default_hospital_image', '../assets/images/default-hospital.webp');

        $image = trim((string)(
            $claim['hospital_image']
            ?? $claim['hospital_logo']
            ?? $claim['hospital_photo']
            ?? $claim['hospital_profile_image']
            ?? ''
        ));

        return pcv_url($image !== '' ? $image : $default);
    }

    return pcv_url('../assets/images/default-doctor.webp');
}

function pcv_user_image(array $claim): string
{
    $image = trim((string)(
        $claim['user_image']
        ?? $claim['user_photo']
        ?? $claim['user_profile_image']
        ?? $claim['user_avatar']
        ?? ''
    ));

    if ($image !== '') {
        return pcv_url($image);
    }

    return pcv_url(pcv_site_setting('default_user_image', '../assets/images/default-user.webp'));
}

function pcv_profile_public_url(string $type, array $claim): string
{
    if ($type === 'doctor') {
        $slug = trim((string)($claim['doctor_slug'] ?? ''));

        if ($slug !== '') {
            return pcv_url('doctor/' . $slug);
        }

        if (!empty($claim['doctor_id'])) {
            return pcv_url('doctor.php?id=' . (int)$claim['doctor_id']);
        }
    }

    if ($type === 'hospital') {
        $slug = trim((string)($claim['hospital_slug'] ?? ''));

        if ($slug !== '') {
            return pcv_url('hospital/' . $slug);
        }

        if (!empty($claim['hospital_id'])) {
            return pcv_url('hospital.php?id=' . (int)$claim['hospital_id']);
        }
    }

    return '';
}

function pcv_profile_edit_url(string $type, array $claim): string
{
    if ($type === 'doctor') {
        $id = (int)($claim['doctor_id'] ?? 0);

        if ($id <= 0) {
            return '';
        }

        $candidates = [
            'doctor-edit.php?id=' . $id,
            'doctors-edit.php?id=' . $id,
            'doctor-form.php?id=' . $id,
            'doctors.php?action=edit&id=' . $id,
            'doctors.php?edit=' . $id,
        ];

        foreach ($candidates as $candidate) {
            $file = strtok($candidate, '?');

            if ($file && file_exists(__DIR__ . '/' . $file)) {
                return $candidate;
            }
        }

        return 'doctor-form.php?id=' . $id;
    }

    if ($type === 'hospital') {
        $id = (int)($claim['hospital_id'] ?? 0);

        if ($id <= 0) {
            return '';
        }

        $candidates = [
            'hospital-edit.php?id=' . $id,
            'hospitals-edit.php?id=' . $id,
            'hospital-form.php?id=' . $id,
            'hospitals.php?action=edit&id=' . $id,
            'hospitals.php?edit=' . $id,
        ];

        foreach ($candidates as $candidate) {
            $file = strtok($candidate, '?');

            if ($file && file_exists(__DIR__ . '/' . $file)) {
                return $candidate;
            }
        }

        return 'hospital-form.php?id=' . $id;
    }

    return '';
}

if (!pcv_table_exists('profile_claims')) {
    require_once __DIR__ . '/includes/header.php';
    echo '<div style="padding:20px;"><div class="pcv-alert pcv-alert-danger">profile_claims table not found.</div></div>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    redirect('profile-claims.php');
}

$lang = trim((string)($_GET['lang'] ?? 'en'));
$claim = null;

try {
    $doctor_join = pcv_table_exists('doctors') ? "LEFT JOIN doctors d ON d.id = pc.doctor_id" : "";
    $hospital_join = pcv_table_exists('hospitals') ? "LEFT JOIN hospitals h ON h.id = pc.hospital_id" : "";

    $specialty_join = pcv_table_exists('specialties') && pcv_table_exists('doctors') && pcv_column_exists('doctors', 'specialty_id')
        ? "LEFT JOIN specialties s ON s.id = d.specialty_id"
        : "";

    $user_select = implode(",\n            ", [
        pcv_select_column('users', 'u', 'image', 'user_image'),
        pcv_select_column('users', 'u', 'photo', 'user_photo'),
        pcv_select_column('users', 'u', 'profile_image', 'user_profile_image'),
        pcv_select_column('users', 'u', 'avatar', 'user_avatar'),

        pcv_select_column('users', 'u', 'division_id', 'user_division_id'),
        pcv_select_column('users', 'u', 'district_id', 'user_district_id'),
        pcv_select_column('users', 'u', 'thana_id', 'user_thana_id'),

        pcv_select_column('users', 'u', 'division_name', 'user_division_name'),
        pcv_select_column('users', 'u', 'district_name', 'user_district_name'),
        pcv_select_column('users', 'u', 'thana_name', 'user_thana_name'),

        pcv_select_column('users', 'u', 'division', 'user_division'),
        pcv_select_column('users', 'u', 'district', 'user_district'),
        pcv_select_column('users', 'u', 'thana', 'user_thana'),

        pcv_select_column('users', 'u', 'bmdc_number', 'user_bmdc_number'),
        pcv_select_column('users', 'u', 'license_number', 'user_license_number'),
        pcv_select_column('users', 'u', 'doctor_specialty', 'user_doctor_specialty'),
        pcv_select_column('users', 'u', 'hospital_type', 'user_hospital_type'),

        pcv_select_column('users', 'u', 'authorized_person_name', 'user_authorized_person_name'),
        pcv_select_column('users', 'u', 'authorized_person_position', 'user_authorized_person_position'),
        pcv_select_column('users', 'u', 'authorized_person_phone', 'user_authorized_person_phone'),
        pcv_select_column('users', 'u', 'authorized_person_email', 'user_authorized_person_email'),

        pcv_select_column('users', 'u', 'area', 'user_area'),
        pcv_select_column('users', 'u', 'road_no', 'user_road_no'),
        pcv_select_column('users', 'u', 'house_no', 'user_house_no'),
        pcv_select_column('users', 'u', 'post_code', 'user_post_code'),
        pcv_select_column('users', 'u', 'address', 'user_address'),
    ]) . ",";

    $doctor_select = pcv_table_exists('doctors')
        ? implode(",\n            ", [
            "d.name AS doctor_name",
            pcv_select_column('doctors', 'd', 'slug', 'doctor_slug'),
            pcv_select_column('doctors', 'd', 'image', 'doctor_image'),
            pcv_select_column('doctors', 'd', 'photo', 'doctor_photo'),
            pcv_select_column('doctors', 'd', 'profile_image', 'doctor_profile_image'),
            pcv_select_column('doctors', 'd', 'avatar', 'doctor_avatar'),

            pcv_select_column('doctors', 'd', 'designation', 'doctor_designation'),
            pcv_select_column('doctors', 'd', 'degree', 'doctor_degree'),
            pcv_select_column('doctors', 'd', 'specialty', 'doctor_specialty'),
            pcv_select_column('doctors', 'd', 'department', 'doctor_department'),

            pcv_select_column('doctors', 'd', 'bmdc_number', 'doctor_bmdc_number'),
            pcv_select_column('doctors', 'd', 'bmdc_no', 'doctor_bmdc_no'),
            pcv_select_column('doctors', 'd', 'bmdc', 'doctor_bmdc'),
            pcv_select_column('doctors', 'd', 'registration_number', 'doctor_registration_number'),

            pcv_select_column('doctors', 'd', 'phone', 'doctor_phone'),
            pcv_select_column('doctors', 'd', 'mobile', 'doctor_mobile'),
            pcv_select_column('doctors', 'd', 'whatsapp', 'doctor_whatsapp'),
            pcv_select_column('doctors', 'd', 'email', 'doctor_email'),

            pcv_select_column('doctors', 'd', 'doctor_division_id', 'doctor_division_id'),
            pcv_select_column('doctors', 'd', 'doctor_district_id', 'doctor_district_id'),
            pcv_select_column('doctors', 'd', 'doctor_thana_id', 'doctor_thana_id'),
            pcv_select_column('doctors', 'd', 'doctor_division', 'doctor_division'),
            pcv_select_column('doctors', 'd', 'doctor_district', 'doctor_district'),
            pcv_select_column('doctors', 'd', 'doctor_thana', 'doctor_thana'),

            pcv_select_column('doctors', 'd', 'division_id', 'doctor_alt_division_id'),
            pcv_select_column('doctors', 'd', 'district_id', 'doctor_alt_district_id'),
            pcv_select_column('doctors', 'd', 'division_name', 'doctor_alt_division_name'),
            pcv_select_column('doctors', 'd', 'district_name', 'doctor_alt_district_name'),

            pcv_select_column('doctors', 'd', 'address', 'doctor_address'),
            pcv_select_column('doctors', 'd', 'status', 'doctor_status'),
            pcv_select_column('doctors', 'd', 'is_verified', 'doctor_is_verified'),
            $specialty_join !== '' ? "s.name AS doctor_specialty_name" : "NULL AS doctor_specialty_name",
        ]) . ","
        : "NULL AS doctor_name,
           NULL AS doctor_slug,
           NULL AS doctor_image,
           NULL AS doctor_photo,
           NULL AS doctor_profile_image,
           NULL AS doctor_avatar,
           NULL AS doctor_designation,
           NULL AS doctor_degree,
           NULL AS doctor_specialty,
           NULL AS doctor_department,
           NULL AS doctor_bmdc_number,
           NULL AS doctor_bmdc_no,
           NULL AS doctor_bmdc,
           NULL AS doctor_registration_number,
           NULL AS doctor_phone,
           NULL AS doctor_mobile,
           NULL AS doctor_whatsapp,
           NULL AS doctor_email,
           NULL AS doctor_division_id,
           NULL AS doctor_district_id,
           NULL AS doctor_thana_id,
           NULL AS doctor_division,
           NULL AS doctor_district,
           NULL AS doctor_thana,
           NULL AS doctor_alt_division_id,
           NULL AS doctor_alt_district_id,
           NULL AS doctor_alt_division_name,
           NULL AS doctor_alt_district_name,
           NULL AS doctor_address,
           NULL AS doctor_status,
           NULL AS doctor_is_verified,
           NULL AS doctor_specialty_name,";

    $hospital_select = pcv_table_exists('hospitals')
        ? implode(",\n            ", [
            "h.name AS hospital_name",
            pcv_select_column('hospitals', 'h', 'slug', 'hospital_slug'),
            pcv_select_column('hospitals', 'h', 'image', 'hospital_image'),
            pcv_select_column('hospitals', 'h', 'logo', 'hospital_logo'),
            pcv_select_column('hospitals', 'h', 'photo', 'hospital_photo'),
            pcv_select_column('hospitals', 'h', 'profile_image', 'hospital_profile_image'),

            pcv_select_column('hospitals', 'h', 'type', 'hospital_type'),
            pcv_select_column('hospitals', 'h', 'hospital_type', 'hospital_type_alt'),

            pcv_select_column('hospitals', 'h', 'license_number', 'hospital_license_number'),
            pcv_select_column('hospitals', 'h', 'registration_number', 'hospital_registration_number'),
            pcv_select_column('hospitals', 'h', 'registration_no', 'hospital_registration_no'),

            pcv_select_column('hospitals', 'h', 'phone', 'hospital_phone'),
            pcv_select_column('hospitals', 'h', 'mobile', 'hospital_mobile'),
            pcv_select_column('hospitals', 'h', 'whatsapp', 'hospital_whatsapp'),
            pcv_select_column('hospitals', 'h', 'email', 'hospital_email'),

            pcv_select_column('hospitals', 'h', 'division_id', 'hospital_division_id'),
            pcv_select_column('hospitals', 'h', 'district_id', 'hospital_district_id'),
            pcv_select_column('hospitals', 'h', 'thana_id', 'hospital_thana_id'),

            pcv_select_column('hospitals', 'h', 'division_name', 'hospital_division_name'),
            pcv_select_column('hospitals', 'h', 'district_name', 'hospital_district_name'),
            pcv_select_column('hospitals', 'h', 'thana_name', 'hospital_thana_name'),

            pcv_select_column('hospitals', 'h', 'division', 'hospital_division'),
            pcv_select_column('hospitals', 'h', 'district', 'hospital_district'),
            pcv_select_column('hospitals', 'h', 'thana', 'hospital_thana'),

            pcv_select_column('hospitals', 'h', 'city', 'hospital_city'),
            pcv_select_column('hospitals', 'h', 'address', 'hospital_address'),
            pcv_select_column('hospitals', 'h', 'status', 'hospital_status'),
            pcv_select_column('hospitals', 'h', 'is_verified', 'hospital_is_verified'),
        ]) . ","
        : "NULL AS hospital_name,
           NULL AS hospital_slug,
           NULL AS hospital_image,
           NULL AS hospital_logo,
           NULL AS hospital_photo,
           NULL AS hospital_profile_image,
           NULL AS hospital_type,
           NULL AS hospital_type_alt,
           NULL AS hospital_license_number,
           NULL AS hospital_registration_number,
           NULL AS hospital_registration_no,
           NULL AS hospital_phone,
           NULL AS hospital_mobile,
           NULL AS hospital_whatsapp,
           NULL AS hospital_email,
           NULL AS hospital_division_id,
           NULL AS hospital_district_id,
           NULL AS hospital_thana_id,
           NULL AS hospital_division_name,
           NULL AS hospital_district_name,
           NULL AS hospital_thana_name,
           NULL AS hospital_division,
           NULL AS hospital_district,
           NULL AS hospital_thana,
           NULL AS hospital_city,
           NULL AS hospital_address,
           NULL AS hospital_status,
           NULL AS hospital_is_verified,";

    $stmt = $pdo->prepare("
        SELECT
            pc.*,
            u.name AS user_name,
            u.email AS user_email,
            u.phone AS user_phone,
            u.user_type AS user_type,
            u.status AS user_status,
            u.claimed_doctor_id,
            u.claimed_hospital_id,
            {$user_select}
            {$doctor_select}
            {$hospital_select}
            pc.created_at AS claim_created_at,
            pc.updated_at AS claim_updated_at
        FROM profile_claims pc
        LEFT JOIN users u ON u.id = pc.user_id
        {$doctor_join}
        {$specialty_join}
        {$hospital_join}
        WHERE pc.id = :id
        LIMIT 1
    ");

    $stmt->execute([':id' => $id]);
    $claim = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $claim = null;
}

if (!$claim) {
    require_once __DIR__ . '/includes/header.php';
    echo '<div style="padding:20px;"><div class="pcv-alert pcv-alert-danger">Claim request not found.</div><a href="profile-claims.php">Back to claims</a></div>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

$status = (string)($claim['status'] ?? 'pending');
$status_class = pcv_status_class($status);
$claim_type = (string)($claim['claim_type'] ?? '');

$profile_name = $claim_type === 'doctor'
    ? trim((string)($claim['doctor_name'] ?? ''))
    : trim((string)($claim['hospital_name'] ?? ''));

if ($profile_name === '') {
    $profile_name = trim((string)($claim['profile_name'] ?? 'Profile ID not found'));
}

$profile_type_text = $claim_type === 'doctor'
    ? 'Doctor'
    : ($claim_type === 'hospital' ? 'Hospital' : 'Profile');

$license_label = $claim_type === 'hospital' ? 'License Number' : 'BMDC Registration Number';

$profile_image = pcv_profile_image($claim_type, $claim);
$profile_fallback = pcv_svg_fallback($claim_type, $profile_name);
$user_image = pcv_user_image($claim);
$user_fallback = pcv_svg_fallback('user', (string)($claim['user_name'] ?? 'User'));

$profile_public_url = pcv_profile_public_url($claim_type, $claim);
$profile_edit_url = pcv_profile_edit_url($claim_type, $claim);

$proof_file = trim((string)($claim['proof_file'] ?? ''));
$proof_url = $proof_file !== '' ? pcv_admin_file_url($proof_file) : '';

$date_text = !empty($claim['claim_created_at'])
    ? date('d M Y h:i A', strtotime((string)$claim['claim_created_at']))
    : '—';

$updated_text = !empty($claim['claim_updated_at'])
    ? date('d M Y h:i A', strtotime((string)$claim['claim_updated_at']))
    : '—';

$user_division_id = (int)pcv_first_value($claim, ['user_division_id', 'division_id']);
$user_district_id = (int)pcv_first_value($claim, ['user_district_id', 'district_id']);
$user_thana_id = (int)pcv_first_value($claim, ['user_thana_id', 'thana_id']);

$submitted_division = pcv_first_value($claim, ['user_division_name', 'user_division', 'division_name', 'division']);
$submitted_district = pcv_first_value($claim, ['user_district_name', 'user_district', 'district_name', 'district']);
$submitted_thana = pcv_first_value($claim, ['user_thana_name', 'user_thana', 'thana_name', 'thana']);

if ($submitted_division === '') {
    $submitted_division = pcv_address_name_by_id('divisions', $user_division_id, $lang);
}

if ($submitted_district === '') {
    $submitted_district = pcv_address_name_by_id('districts', $user_district_id, $lang);
}

if ($submitted_thana === '') {
    $submitted_thana = pcv_address_name_by_id('thanas', $user_thana_id, $lang);
}

$submitted_division_compare = $user_division_id > 0 ? (string)$user_division_id : $submitted_division;
$submitted_district_compare = $user_district_id > 0 ? (string)$user_district_id : $submitted_district;

if ($claim_type === 'doctor') {
    $doctor_division_id = (int)pcv_first_value($claim, ['doctor_division_id', 'doctor_alt_division_id']);
    $doctor_district_id = (int)pcv_first_value($claim, ['doctor_district_id', 'doctor_alt_district_id']);
    $doctor_thana_id = (int)pcv_first_value($claim, ['doctor_thana_id']);

    $actual_name = $claim['doctor_name'] ?? '';
    $actual_specialty = pcv_first_value($claim, ['doctor_specialty_name', 'doctor_specialty', 'doctor_department', 'doctor_designation']);
    $actual_license = pcv_first_value($claim, ['doctor_bmdc_number', 'doctor_bmdc_no', 'doctor_bmdc', 'doctor_registration_number']);
    $actual_phone = pcv_first_value($claim, ['doctor_phone', 'doctor_mobile', 'doctor_whatsapp']);
    $actual_email = pcv_first_value($claim, ['doctor_email']);

    $actual_division = pcv_first_value($claim, ['doctor_division', 'doctor_alt_division_name']);
    $actual_district = pcv_first_value($claim, ['doctor_district', 'doctor_alt_district_name']);
    $actual_thana = pcv_first_value($claim, ['doctor_thana']);
    $actual_address = pcv_first_value($claim, ['doctor_address']);

    if ($actual_division === '') {
        $actual_division = pcv_address_name_by_id('divisions', $doctor_division_id, $lang);
    }

    if ($actual_district === '') {
        $actual_district = pcv_address_name_by_id('districts', $doctor_district_id, $lang);
    }

    if ($actual_thana === '') {
        $actual_thana = pcv_address_name_by_id('thanas', $doctor_thana_id, $lang);
    }

    $actual_division_compare = $doctor_division_id > 0 ? (string)$doctor_division_id : $actual_division;
    $actual_district_compare = $doctor_district_id > 0 ? (string)$doctor_district_id : $actual_district;
    $is_profile_verified = (int)($claim['doctor_is_verified'] ?? 0) === 1;
} else {
    $hospital_division_id = (int)pcv_first_value($claim, ['hospital_division_id']);
    $hospital_district_id = (int)pcv_first_value($claim, ['hospital_district_id']);
    $hospital_thana_id = (int)pcv_first_value($claim, ['hospital_thana_id']);

    $actual_name = $claim['hospital_name'] ?? '';
    $actual_specialty = pcv_first_value($claim, ['hospital_type', 'hospital_type_alt']);
    $actual_license = pcv_first_value($claim, ['hospital_license_number', 'hospital_registration_number', 'hospital_registration_no']);
    $actual_phone = pcv_first_value($claim, ['hospital_phone', 'hospital_mobile', 'hospital_whatsapp']);
    $actual_email = pcv_first_value($claim, ['hospital_email']);

    $actual_division = pcv_first_value($claim, ['hospital_division_name', 'hospital_division']);
    $actual_district = pcv_first_value($claim, ['hospital_district_name', 'hospital_district', 'hospital_city']);
    $actual_thana = pcv_first_value($claim, ['hospital_thana_name', 'hospital_thana']);
    $actual_address = pcv_first_value($claim, ['hospital_address']);

    if ($actual_division === '') {
        $actual_division = pcv_address_name_by_id('divisions', $hospital_division_id, $lang);
    }

    if ($actual_district === '') {
        $actual_district = pcv_address_name_by_id('districts', $hospital_district_id, $lang);
    }

    if ($actual_thana === '') {
        $actual_thana = pcv_address_name_by_id('thanas', $hospital_thana_id, $lang);
    }

    $actual_division_compare = $hospital_division_id > 0 ? (string)$hospital_division_id : $actual_division;
    $actual_district_compare = $hospital_district_id > 0 ? (string)$hospital_district_id : $actual_district;
    $is_profile_verified = (int)($claim['hospital_is_verified'] ?? 0) === 1;
}

$submitted_name = pcv_first_value($claim, ['user_name', 'name']);

if ($claim_type === 'doctor') {
    $submitted_specialty = pcv_first_value($claim, ['user_doctor_specialty', 'specialty']);
    $submitted_license = pcv_first_value($claim, ['user_bmdc_number', 'license_number']);
} else {
    $submitted_specialty = pcv_first_value($claim, ['user_hospital_type', 'specialty']);
    $submitted_license = pcv_first_value($claim, ['user_license_number', 'license_number', 'user_bmdc_number']);
}

$submitted_phone = pcv_first_value($claim, ['user_phone', 'phone']);
$submitted_email = pcv_first_value($claim, ['user_email', 'email']);
$submitted_address = pcv_first_value($claim, ['user_address', 'address']);

$user_area = pcv_first_value($claim, ['user_area']);
$user_road_no = pcv_first_value($claim, ['user_road_no']);
$user_house_no = pcv_first_value($claim, ['user_house_no']);
$user_post_code = pcv_first_value($claim, ['user_post_code']);

$user_authorized_person_name = pcv_first_value($claim, ['user_authorized_person_name']);
$user_authorized_person_position = pcv_first_value($claim, ['user_authorized_person_position']);
$user_authorized_person_phone = pcv_first_value($claim, ['user_authorized_person_phone']);
$user_authorized_person_email = pcv_first_value($claim, ['user_authorized_person_email']);

$compare_rows = [
    ['label' => 'Name', 'left' => $submitted_name, 'right' => $actual_name, 'mode' => 'text', 'score_weight' => 25],
    ['label' => 'Specialty', 'left' => $submitted_specialty, 'right' => $actual_specialty, 'mode' => 'text', 'score_weight' => 25],
    [
        'label' => 'Division',
        'left' => $submitted_division,
        'right' => $actual_division,
        'left_compare' => $submitted_division_compare,
        'right_compare' => $actual_division_compare,
        'mode' => $user_division_id > 0 && (int)$actual_division_compare > 0 ? 'id' : 'text',
        'score_weight' => 20,
    ],
    [
        'label' => 'District',
        'left' => $submitted_district,
        'right' => $actual_district,
        'left_compare' => $submitted_district_compare,
        'right_compare' => $actual_district_compare,
        'mode' => $user_district_id > 0 && (int)$actual_district_compare > 0 ? 'id' : 'text',
        'score_weight' => 20,
    ],
    ['label' => 'Phone', 'left' => $submitted_phone, 'right' => $actual_phone, 'mode' => 'phone', 'score_weight' => 5],
    ['label' => 'Email', 'left' => $submitted_email, 'right' => $actual_email, 'mode' => 'email', 'score_weight' => 5],
    ['label' => $license_label, 'left' => $submitted_license, 'right' => $actual_license, 'mode' => 'license', 'score_weight' => 0],
    ['label' => 'Thana / Upazila', 'left' => $submitted_thana, 'right' => $actual_thana, 'mode' => 'text', 'score_weight' => 0],
];

$match_total = count($compare_rows);
$match_count = 0;
$mismatch_count = 0;
$missing_count = 0;
$match_percent = 0;

foreach ($compare_rows as $row) {
    $class = pcv_compare_row_class($row);

    if ($class === 'match') {
        $match_count++;
        $match_percent += (int)($row['score_weight'] ?? 0);
    } elseif ($class === 'mismatch') {
        $mismatch_count++;
    } else {
        $missing_count++;
    }
}

$proof_available = $proof_url !== '';
$user_already_connected = false;

if ($claim_type === 'doctor' && !empty($claim['claimed_doctor_id'])) {
    $user_already_connected = true;
}

if ($claim_type === 'hospital' && !empty($claim['claimed_hospital_id'])) {
    $user_already_connected = true;
}

$decision_class = pcv_score_status($match_percent);
$decision_title = 'Manual review recommended';
$decision_text = 'Check claimed user details and claimed profile details before approval.';

if ($match_percent >= 100) {
    $decision_class = 'success';
    $decision_title = 'Perfect match found';
    $decision_text = 'Name, specialty, division, district, phone, and email all matched. This claim looks strong for approval.';
} elseif ($match_percent >= 90) {
    $decision_class = 'success';
    $decision_title = 'Strong match found';
    $decision_text = 'Name, specialty, division, and district matched. Phone or email may still need manual checking.';
} elseif ($match_percent >= 60) {
    $decision_class = 'warning';
    $decision_title = 'Partial match found';
    $decision_text = 'Some important information matched, but admin should review carefully before approval.';
} else {
    $decision_class = 'danger';
    $decision_title = 'Low match detected';
    $decision_text = 'Important user information does not match the claimed profile. Review carefully before taking action.';
}

if (!$proof_available) {
    $decision_class = 'warning';
    $decision_title = 'Proof file missing';
    $decision_text = 'The user did not upload a proof file. Please verify proof text and profile details carefully.';
}

$review_items = [
    ['label' => 'Name Match', 'status' => pcv_compare_row_class($compare_rows[0])],
    ['label' => 'Specialty Match', 'status' => pcv_compare_row_class($compare_rows[1])],
    ['label' => 'Division Match', 'status' => pcv_compare_row_class($compare_rows[2])],
    ['label' => 'District Match', 'status' => pcv_compare_row_class($compare_rows[3])],
    ['label' => 'Phone Match', 'status' => pcv_compare_row_class($compare_rows[4])],
    ['label' => 'Email Match', 'status' => pcv_compare_row_class($compare_rows[5])],
    ['label' => $license_label, 'status' => pcv_compare_row_class($compare_rows[6])],
    ['label' => 'Proof File', 'status' => $proof_available ? 'match' : 'mismatch'],
    ['label' => 'Already Connected', 'status' => $user_already_connected ? 'mismatch' : 'match'],
];

require_once __DIR__ . '/includes/header.php';
?>

<style>
    :root {
        --pcv-bg: #f6f8fa;
        --pcv-canvas: #ffffff;
        --pcv-subtle: #f6f8fa;
        --pcv-border: #d0d7de;
        --pcv-border-muted: #d8dee4;
        --pcv-text: #24292f;
        --pcv-muted: #57606a;
        --pcv-blue: #0969da;
        --pcv-green: #1a7f37;
        --pcv-green-bg: #dafbe1;
        --pcv-red: #cf222e;
        --pcv-red-bg: #ffebe9;
        --pcv-yellow: #9a6700;
        --pcv-yellow-bg: #fff8c5;
        --pcv-shadow: 0 8px 24px rgba(140,149,159,.18);
    }

    body {
        background: var(--pcv-bg);
    }

    .pcv-page,
    .pcv-page * {
        box-sizing: border-box;
    }

    .pcv-page {
        max-width: 1180px;
        margin: 0 auto;
        padding: 12px 0 42px;
        color: var(--pcv-text);
    }

    .pcv-head {
        margin-bottom: 16px;
        padding: 18px 20px;
        border: 1px solid var(--pcv-border);
        border-radius: 12px;
        background:
            radial-gradient(circle at top right, rgba(9,105,218,.12), transparent 34%),
            linear-gradient(135deg, #ffffff, #f6f8fa);
        box-shadow: var(--pcv-shadow);
    }

    .pcv-head-row {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 16px;
        flex-wrap: wrap;
    }

    .pcv-breadcrumb {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        margin-bottom: 10px;
        color: var(--pcv-muted);
        font-size: 13px;
    }

    .pcv-breadcrumb a {
        color: var(--pcv-blue);
        text-decoration: none;
        font-weight: 600;
    }

    .pcv-head h1 {
        margin: 0;
        color: var(--pcv-text);
        font-size: 28px;
        line-height: 1.25;
        font-weight: 800;
        letter-spacing: -.035em;
    }

    .pcv-head p {
        max-width: 760px;
        margin: 7px 0 0;
        color: var(--pcv-muted);
        font-size: 14px;
        line-height: 1.65;
    }

    .pcv-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 36px;
        padding: 7px 13px;
        border-radius: 8px;
        border: 1px solid rgba(27,31,36,.15);
        background: var(--pcv-canvas);
        color: var(--pcv-blue);
        font-family: inherit;
        font-size: 13px;
        font-weight: 700;
        text-decoration: none;
        cursor: pointer;
        white-space: nowrap;
        transition: .15s ease;
    }

    .pcv-btn:hover {
        background: var(--pcv-subtle);
        text-decoration: none;
    }

    .pcv-btn-primary {
        background: #2da44e;
        color: #ffffff;
    }

    .pcv-btn-primary:hover {
        background: #2c974b;
        color: #ffffff;
    }

    .pcv-btn-danger {
        background: #cf222e;
        color: #ffffff;
    }

    .pcv-btn-danger:hover {
        background: #a40e26;
        color: #ffffff;
    }

    .pcv-btn-muted {
        color: var(--pcv-text);
    }

    .pcv-insight-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 12px;
        margin-bottom: 16px;
    }

    .pcv-insight-card {
        border: 1px solid var(--pcv-border);
        border-radius: 12px;
        background: var(--pcv-canvas);
        box-shadow: var(--pcv-shadow);
        padding: 14px;
    }

    .pcv-insight-card span {
        display: block;
        color: var(--pcv-muted);
        font-size: 12px;
        font-weight: 800;
        margin-bottom: 7px;
    }

    .pcv-insight-card strong {
        display: block;
        color: var(--pcv-text);
        font-size: 24px;
        line-height: 1;
        font-weight: 900;
    }

    .pcv-decision {
        border-radius: 12px;
        padding: 14px;
        border: 1px solid var(--pcv-border);
        margin-bottom: 16px;
    }

    .pcv-decision.success {
        background: var(--pcv-green-bg);
        border-color: rgba(26,127,55,.22);
        color: var(--pcv-green);
    }

    .pcv-decision.warning {
        background: var(--pcv-yellow-bg);
        border-color: rgba(154,103,0,.22);
        color: var(--pcv-yellow);
    }

    .pcv-decision.danger {
        background: var(--pcv-red-bg);
        border-color: rgba(207,34,46,.22);
        color: var(--pcv-red);
    }

    .pcv-decision strong {
        display: block;
        font-size: 15px;
        margin-bottom: 5px;
    }

    .pcv-decision p {
        margin: 0;
        line-height: 1.6;
        font-size: 13px;
    }

    .pcv-grid {
        display: grid;
        grid-template-columns: minmax(0, 1fr) 360px;
        gap: 16px;
        align-items: start;
    }

    .pcv-card {
        margin-bottom: 16px;
        border: 1px solid var(--pcv-border);
        border-radius: 12px;
        background: var(--pcv-canvas);
        box-shadow: var(--pcv-shadow);
        overflow: hidden;
    }

    .pcv-card-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 14px 16px;
        border-bottom: 1px solid var(--pcv-border);
        background: var(--pcv-subtle);
    }

    .pcv-card-head h2 {
        margin: 0;
        color: var(--pcv-text);
        font-size: 15px;
        font-weight: 800;
    }

    .pcv-card-body {
        padding: 16px;
    }

    .pcv-profile-actions {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }

    .pcv-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 26px;
        padding: 4px 9px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 800;
        border: 1px solid var(--pcv-border);
    }

    .pcv-badge.pending {
        color: var(--pcv-yellow);
        background: var(--pcv-yellow-bg);
        border-color: rgba(154,103,0,.22);
    }

    .pcv-badge.approved {
        color: var(--pcv-green);
        background: var(--pcv-green-bg);
        border-color: rgba(26,127,55,.22);
    }

    .pcv-badge.rejected {
        color: var(--pcv-red);
        background: var(--pcv-red-bg);
        border-color: rgba(207,34,46,.22);
    }

    .pcv-compare {
        border: 1px solid var(--pcv-border);
        border-radius: 12px;
        overflow: hidden;
    }

    .pcv-compare-head,
    .pcv-compare-row {
        display: grid;
        grid-template-columns: 170px 1fr 1fr 130px;
    }

    .pcv-compare-head {
        background: var(--pcv-subtle);
        border-bottom: 1px solid var(--pcv-border);
    }

    .pcv-compare-head div,
    .pcv-compare-row > div {
        padding: 12px;
        border-right: 1px solid var(--pcv-border-muted);
    }

    .pcv-compare-head div:last-child,
    .pcv-compare-row > div:last-child {
        border-right: 0;
    }

    .pcv-compare-head div {
        color: var(--pcv-muted);
        font-size: 12px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: .02em;
    }

    .pcv-compare-row {
        border-bottom: 1px solid var(--pcv-border-muted);
    }

    .pcv-compare-row:last-child {
        border-bottom: 0;
    }

    .pcv-compare-label {
        color: var(--pcv-muted);
        font-size: 13px;
        font-weight: 800;
    }

    .pcv-compare-value {
        color: var(--pcv-text);
        font-size: 14px;
        line-height: 1.6;
        word-break: break-word;
    }

    .pcv-compare-value.match {
        background: rgba(26,127,55,.08);
        color: #116329;
    }

    .pcv-compare-value.mismatch {
        background: rgba(207,34,46,.08);
        color: #a40e26;
    }

    .pcv-compare-value.neutral {
        background: #ffffff;
    }

    .pcv-match-badge {
        display: inline-flex;
        min-height: 24px;
        align-items: center;
        justify-content: center;
        padding: 3px 8px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 800;
        white-space: nowrap;
    }

    .pcv-match-badge.match {
        background: var(--pcv-green-bg);
        color: var(--pcv-green);
        border: 1px solid rgba(26,127,55,.22);
    }

    .pcv-match-badge.mismatch {
        background: var(--pcv-red-bg);
        color: var(--pcv-red);
        border: 1px solid rgba(207,34,46,.22);
    }

    .pcv-match-badge.neutral {
        background: var(--pcv-subtle);
        color: var(--pcv-muted);
        border: 1px solid var(--pcv-border);
    }

    .pcv-image-only {
        width: 82px;
        height: 82px;
        border-radius: 14px;
        object-fit: cover;
        border: 1px solid var(--pcv-border);
        background: var(--pcv-subtle);
        display: block;
    }

    .pcv-textbox {
        padding: 12px;
        border-radius: 10px;
        border: 1px solid var(--pcv-border);
        background: var(--pcv-subtle);
        white-space: pre-wrap;
        line-height: 1.7;
        color: var(--pcv-text);
    }

    .pcv-row {
        display: grid;
        grid-template-columns: 145px minmax(0, 1fr);
        gap: 12px;
        padding: 12px 0;
        border-bottom: 1px solid var(--pcv-border-muted);
    }

    .pcv-row:last-child {
        border-bottom: 0;
    }

    .pcv-row span {
        color: var(--pcv-muted);
        font-size: 13px;
        font-weight: 700;
    }

    .pcv-row strong,
    .pcv-row div {
        color: var(--pcv-text);
        font-size: 14px;
        line-height: 1.6;
        word-break: break-word;
    }

    .pcv-review-list {
        display: grid;
        gap: 8px;
    }

    .pcv-review-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        padding: 9px 10px;
        border: 1px solid var(--pcv-border-muted);
        border-radius: 10px;
        background: #fff;
    }

    .pcv-review-item span {
        color: var(--pcv-text);
        font-size: 13px;
        font-weight: 700;
    }

    .pcv-status-dot {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 23px;
        padding: 3px 8px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 900;
    }

    .pcv-status-dot.match {
        background: var(--pcv-green-bg);
        color: var(--pcv-green);
        border: 1px solid rgba(26,127,55,.22);
    }

    .pcv-status-dot.mismatch {
        background: var(--pcv-red-bg);
        color: var(--pcv-red);
        border: 1px solid rgba(207,34,46,.22);
    }

    .pcv-status-dot.neutral {
        background: var(--pcv-subtle);
        color: var(--pcv-muted);
        border: 1px solid var(--pcv-border);
    }

    .pcv-actions {
        display: grid;
        gap: 10px;
    }

    .pcv-actions form {
        margin: 0;
        display: grid;
        gap: 10px;
    }

    .pcv-actions input[type="text"] {
        width: 100%;
        min-height: 38px;
        border: 1px solid var(--pcv-border);
        border-radius: 8px;
        padding: 8px 10px;
        background: var(--pcv-canvas);
        outline: none;
    }

    .pcv-alert {
        padding: 12px 14px;
        border-radius: 10px;
        margin-bottom: 16px;
        font-size: 14px;
        line-height: 1.6;
    }

    .pcv-alert-note {
        background: var(--pcv-subtle);
        border: 1px solid var(--pcv-border);
        color: var(--pcv-muted);
    }

    .pcv-alert-danger {
        background: var(--pcv-red-bg);
        color: var(--pcv-red);
        border: 1px solid rgba(207,34,46,.22);
    }

    @media (max-width: 1080px) {
        .pcv-grid {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 850px) {
        .pcv-insight-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .pcv-compare-head,
        .pcv-compare-row {
            grid-template-columns: 1fr;
        }

        .pcv-compare-head div,
        .pcv-compare-row > div {
            border-right: 0;
            border-bottom: 1px solid var(--pcv-border-muted);
        }

        .pcv-compare-row > div:last-child {
            border-bottom: 0;
        }
    }

    @media (max-width: 520px) {
        .pcv-insight-grid {
            grid-template-columns: 1fr;
        }

        .pcv-head,
        .pcv-card-body,
        .pcv-card-head {
            padding: 14px;
        }

        .pcv-btn {
            width: 100%;
        }

        .pcv-profile-actions {
            display: grid;
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="pcv-page">

    <div class="pcv-head">
        <div class="pcv-head-row">
            <div>
                <div class="pcv-breadcrumb">
                    <a href="dashboard.php">Dashboard</a>
                    <span>/</span>
                    <a href="profile-claims.php">Profile Claims</a>
                    <span>/</span>
                    <span>View</span>
                </div>

                <h1>Claim Request #<?= pcv_e((string)$claim['id']) ?></h1>
                <p>Compare claimed user details with the selected <?= pcv_e(strtolower($profile_type_text)) ?> profile before taking action.</p>
            </div>

            <div>
                <a href="profile-claims.php" class="pcv-btn pcv-btn-muted">Back to Claims</a>
            </div>
        </div>
    </div>

    <div class="pcv-insight-grid">
        <div class="pcv-insight-card">
            <span>Match Score</span>
            <strong><?= pcv_e((string)$match_percent) ?>%</strong>
        </div>

        <div class="pcv-insight-card">
            <span>Matched Fields</span>
            <strong><?= pcv_e((string)$match_count) ?>/<?= pcv_e((string)$match_total) ?></strong>
        </div>

        <div class="pcv-insight-card">
            <span>Mismatched</span>
            <strong><?= pcv_e((string)$mismatch_count) ?></strong>
        </div>

        <div class="pcv-insight-card">
            <span>Missing Data</span>
            <strong><?= pcv_e((string)$missing_count) ?></strong>
        </div>
    </div>

    <div class="pcv-decision <?= pcv_e($decision_class) ?>">
        <strong><?= pcv_e($decision_title) ?></strong>
        <p><?= pcv_e($decision_text) ?></p>
    </div>

    <div class="pcv-grid">

        <main>
            <div class="pcv-card">
                <div class="pcv-card-head">
                    <h2>Claimed Profile</h2>
                    <span class="pcv-badge <?= pcv_e($status_class) ?>"><?= pcv_e(ucfirst($status)) ?></span>
                </div>

                <div class="pcv-card-body">
                    <div class="pcv-profile-actions">
                        <?php if ($profile_public_url !== ''): ?>
                            <a href="<?= pcv_e($profile_public_url) ?>" target="_blank" rel="noopener" class="pcv-btn">
                                View Public Profile
                            </a>
                        <?php endif; ?>

                        <?php if ($profile_edit_url !== ''): ?>
                            <a href="<?= pcv_e($profile_edit_url) ?>" class="pcv-btn pcv-btn-muted">
                                <?= $claim_type === 'hospital' ? 'Edit Hospital' : 'Edit Doctor' ?>
                            </a>
                        <?php endif; ?>

                        <?php if ($is_profile_verified): ?>
                            <span class="pcv-badge approved">✓ Verified <?= pcv_e($profile_type_text) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="pcv-card">
                <div class="pcv-card-head">
                    <h2>Claimed User vs Claimed Profile</h2>
                </div>

                <div class="pcv-card-body">
                    <div class="pcv-compare">
                        <div class="pcv-compare-head">
                            <div>Field</div>
                            <div>Claimed User</div>
                            <div>Claimed Profile</div>
                            <div>Match</div>
                        </div>

                        <div class="pcv-compare-row">
                            <div class="pcv-compare-label">Image</div>

                            <div class="pcv-compare-value neutral">
                                <img
                                    class="pcv-image-only"
                                    src="<?= pcv_e($user_image) ?>"
                                    alt="User Image"
                                    loading="lazy"
                                    onerror="this.onerror=null;this.src='<?= pcv_e($user_fallback) ?>';"
                                >
                            </div>

                            <div class="pcv-compare-value neutral">
                                <img
                                    class="pcv-image-only"
                                    src="<?= pcv_e($profile_image) ?>"
                                    alt="Profile Image"
                                    loading="lazy"
                                    onerror="this.onerror=null;this.src='<?= pcv_e($profile_fallback) ?>';"
                                >
                            </div>

                            <div>
                                <span class="pcv-match-badge neutral">Visual Check</span>
                            </div>
                        </div>

                        <?php foreach ($compare_rows as $row): ?>
                            <?php $compare_class = pcv_compare_row_class($row); ?>

                            <div class="pcv-compare-row">
                                <div class="pcv-compare-label"><?= pcv_e($row['label']) ?></div>

                                <div class="pcv-compare-value <?= pcv_e($compare_class) ?>">
                                    <?= pcv_e(pcv_value($row['left'])) ?>
                                </div>

                                <div class="pcv-compare-value <?= pcv_e($compare_class) ?>">
                                    <?= pcv_e(pcv_value($row['right'])) ?>
                                </div>

                                <div>
                                    <span class="pcv-match-badge <?= pcv_e($compare_class) ?>">
                                        <?= pcv_e(pcv_compare_label($compare_class)) ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="pcv-card">
                <div class="pcv-card-head">
                    <h2>Proof & Verification</h2>
                </div>

                <div class="pcv-card-body">
                    <div class="pcv-row">
                        <span>Proof Text</span>
                        <div class="pcv-textbox"><?= pcv_e(pcv_value($claim['proof_text'] ?? '')) ?></div>
                    </div>

                    <div class="pcv-row">
                        <span>Verification Message</span>
                        <div class="pcv-textbox"><?= pcv_e(pcv_value($claim['message'] ?? '')) ?></div>
                    </div>

                    <div class="pcv-row">
                        <span>Proof File</span>
                        <div>
                            <?php if ($proof_url !== ''): ?>
                                <a href="<?= pcv_e($proof_url) ?>" target="_blank" rel="noopener" class="pcv-btn">
                                    View Proof File
                                </a>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="pcv-row">
                        <span>Admin Note</span>
                        <div class="pcv-textbox"><?= pcv_e(pcv_value($claim['admin_note'] ?? '')) ?></div>
                    </div>
                </div>
            </div>
        </main>

        <aside>
            <div class="pcv-card">
                <div class="pcv-card-head">
                    <h2>Claimed User Details</h2>

                    <?php if (!empty($claim['user_id'])): ?>
                        <a href="user-view.php?id=<?= pcv_e((string)$claim['user_id']) ?>" class="pcv-btn">
                            View User Details
                        </a>
                    <?php endif; ?>
                </div>

                <div class="pcv-card-body">
                    <div class="pcv-row">
                        <span>Name</span>
                        <strong><?= pcv_e(pcv_value($submitted_name)) ?></strong>
                    </div>

                    <div class="pcv-row">
                        <span>User Type</span>
                        <strong><?= pcv_e(pcv_user_type_label((string)($claim['user_type'] ?? ''))) ?></strong>
                    </div>

                    <div class="pcv-row">
                        <span>Status</span>
                        <strong><?= pcv_e(pcv_value($claim['user_status'] ?? '')) ?></strong>
                    </div>

                    <div class="pcv-row">
                        <span><?= $claim_type === 'hospital' ? 'Hospital Type' : 'Specialty' ?></span>
                        <strong><?= pcv_e(pcv_value($submitted_specialty)) ?></strong>
                    </div>

                    <div class="pcv-row">
                        <span><?= pcv_e($license_label) ?></span>
                        <strong><?= pcv_e(pcv_value($submitted_license)) ?></strong>
                    </div>

                    <?php if ($claim_type === 'hospital'): ?>
                        <div class="pcv-row">
                            <span>Authorized Person</span>
                            <strong><?= pcv_e(pcv_value($user_authorized_person_name)) ?></strong>
                        </div>

                        <div class="pcv-row">
                            <span>Position</span>
                            <strong><?= pcv_e(pcv_value($user_authorized_person_position)) ?></strong>
                        </div>

                        <div class="pcv-row">
                            <span>Authorized Phone</span>
                            <strong><?= pcv_e(pcv_value($user_authorized_person_phone)) ?></strong>
                        </div>

                        <div class="pcv-row">
                            <span>Authorized Email</span>
                            <strong><?= pcv_e(pcv_value($user_authorized_person_email)) ?></strong>
                        </div>
                    <?php endif; ?>

                    <div class="pcv-row">
                        <span>Division</span>
                        <strong><?= pcv_e(pcv_value($submitted_division)) ?></strong>
                    </div>

                    <div class="pcv-row">
                        <span>District</span>
                        <strong><?= pcv_e(pcv_value($submitted_district)) ?></strong>
                    </div>

                    <div class="pcv-row">
                        <span>Thana</span>
                        <strong><?= pcv_e(pcv_value($submitted_thana)) ?></strong>
                    </div>

                    <div class="pcv-row">
                        <span>Area</span>
                        <strong><?= pcv_e(pcv_value($user_area)) ?></strong>
                    </div>

                    <div class="pcv-row">
                        <span>Road</span>
                        <strong><?= pcv_e(pcv_value($user_road_no)) ?></strong>
                    </div>

                    <div class="pcv-row">
                        <span>House</span>
                        <strong><?= pcv_e(pcv_value($user_house_no)) ?></strong>
                    </div>

                    <div class="pcv-row">
                        <span>Post Code</span>
                        <strong><?= pcv_e(pcv_value($user_post_code)) ?></strong>
                    </div>

                    <div class="pcv-row">
                        <span>Phone</span>
                        <strong><?= pcv_e(pcv_value($submitted_phone)) ?></strong>
                    </div>

                    <div class="pcv-row">
                        <span>Email</span>
                        <strong><?= pcv_e(pcv_value($submitted_email)) ?></strong>
                    </div>

                    <div class="pcv-row">
                        <span>Full Address</span>
                        <strong><?= pcv_e(pcv_value($submitted_address)) ?></strong>
                    </div>
                </div>
            </div>

            <div class="pcv-card">
                <div class="pcv-card-head">
                    <h2>Claimed Profile Details</h2>
                </div>

                <div class="pcv-card-body">
                    <div class="pcv-row">
                        <span>Name</span>
                        <strong><?= pcv_e(pcv_value($actual_name)) ?></strong>
                    </div>

                    <div class="pcv-row">
                        <span><?= $claim_type === 'hospital' ? 'Hospital Type' : 'Specialty' ?></span>
                        <strong><?= pcv_e(pcv_value($actual_specialty)) ?></strong>
                    </div>

                    <div class="pcv-row">
                        <span><?= pcv_e($license_label) ?></span>
                        <strong><?= pcv_e(pcv_value($actual_license)) ?></strong>
                    </div>

                    <div class="pcv-row">
                        <span>Division</span>
                        <strong><?= pcv_e(pcv_value($actual_division)) ?></strong>
                    </div>

                    <div class="pcv-row">
                        <span>District</span>
                        <strong><?= pcv_e(pcv_value($actual_district)) ?></strong>
                    </div>

                    <div class="pcv-row">
                        <span>Thana</span>
                        <strong><?= pcv_e(pcv_value($actual_thana)) ?></strong>
                    </div>

                    <div class="pcv-row">
                        <span>Phone</span>
                        <strong><?= pcv_e(pcv_value($actual_phone)) ?></strong>
                    </div>

                    <div class="pcv-row">
                        <span>Email</span>
                        <strong><?= pcv_e(pcv_value($actual_email)) ?></strong>
                    </div>

                    <div class="pcv-row">
                        <span>Address</span>
                        <strong><?= pcv_e(pcv_value($actual_address)) ?></strong>
                    </div>

                    <div class="pcv-row">
                        <span>Verified</span>
                        <strong>
                            <?php if ($is_profile_verified): ?>
                                <span class="pcv-badge approved">✓ Verified <?= pcv_e($profile_type_text) ?></span>
                            <?php else: ?>
                                <span class="pcv-badge pending">Not Verified</span>
                            <?php endif; ?>
                        </strong>
                    </div>
                </div>
            </div>

            <div class="pcv-card">
                <div class="pcv-card-head">
                    <h2>Claim Summary</h2>
                </div>

                <div class="pcv-card-body">
                    <div class="pcv-row">
                        <span>Status</span>
                        <strong>
                            <span class="pcv-badge <?= pcv_e($status_class) ?>"><?= pcv_e(ucfirst($status)) ?></span>
                        </strong>
                    </div>

                    <div class="pcv-row">
                        <span>Claim Type</span>
                        <strong><?= pcv_e(pcv_type_label($claim_type)) ?></strong>
                    </div>

                    <div class="pcv-row">
                        <span>Date</span>
                        <strong><?= pcv_e($date_text) ?></strong>
                    </div>

                    <div class="pcv-row">
                        <span>Updated</span>
                        <strong><?= pcv_e($updated_text) ?></strong>
                    </div>
                </div>
            </div>

            <div class="pcv-card">
                <div class="pcv-card-head">
                    <h2>Review Checklist</h2>
                </div>

                <div class="pcv-card-body">
                    <div class="pcv-review-list">
                        <?php foreach ($review_items as $item): ?>
                            <div class="pcv-review-item">
                                <span><?= pcv_e($item['label']) ?></span>

                                <strong class="pcv-status-dot <?= pcv_e($item['status']) ?>">
                                    <?= pcv_e(pcv_compare_label($item['status'])) ?>
                                </strong>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="pcv-card">
                <div class="pcv-card-head">
                    <h2>Action</h2>
                </div>

                <div class="pcv-card-body">
                    <?php if ($status === 'pending'): ?>
                        <div class="pcv-actions">
                            <form method="post" action="profile-claim-action.php" onsubmit="return confirm('Approve this claim request?');">
                                <input type="hidden" name="id" value="<?= pcv_e((string)$claim['id']) ?>">
                                <input type="hidden" name="action" value="approve">

                                <button type="submit" class="pcv-btn pcv-btn-primary">
                                    Approve Claim
                                </button>
                            </form>

                            <form method="post" action="profile-claim-action.php" onsubmit="return confirm('Reject this claim request?');">
                                <input type="hidden" name="id" value="<?= pcv_e((string)$claim['id']) ?>">
                                <input type="hidden" name="action" value="reject">

                                <input type="text" name="admin_note" placeholder="Reject note">

                                <button type="submit" class="pcv-btn pcv-btn-danger">
                                    Reject Claim
                                </button>
                            </form>
                        </div>
                    <?php else: ?>
                        <div class="pcv-alert pcv-alert-note">
                            This claim request is already <?= pcv_e($status) ?>.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </aside>

    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>