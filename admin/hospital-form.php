<?php
require_once __DIR__ . '/../includes/functions.php';
require_admin();

/*
|--------------------------------------------------------------------------
| Hospital Form - Clean Manual Version
| Sticky Save Bar Version: 2026-06-25
|--------------------------------------------------------------------------
| Removed completely:
| - Common Service option panel
| - Common Facility option panel
| - Diagnostic option panel
| - Critical care option panel
| - Doctor selection option
| - Department selection option
| - Review/rating input option
| - Hospital gallery multiple image upload
| - Dynamic service checkbox list
| - Dynamic facility checkbox list
| - Doctor/chamber/department/review dependency
| - Appointment Note, Insurance Support, Payment Methods fields
|
| Location dropdowns use divisions, districts, and thanas tables.
|
| This file only saves/updates data in the hospitals table.
*/

$id = (int)($_GET['id'] ?? 0);
$hospital = null;
$form_message = '';
$form_message_type = '';

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/
if (!function_exists('hospital_form_clean')) {
    function hospital_form_clean($value): string
    {
        return trim((string)$value);
    }
}

if (!function_exists('hospital_form_int_or_null')) {
    function hospital_form_int_or_null($value): ?int
    {
        $value = trim((string)$value);
        return $value === '' ? null : (int)$value;
    }
}


if (!function_exists('hospital_form_table_exists')) {
    function hospital_form_table_exists(string $table): bool
    {
        global $pdo;

        try {
            $stmt = $pdo->prepare("\n                SELECT COUNT(*)\n                FROM INFORMATION_SCHEMA.TABLES\n                WHERE TABLE_SCHEMA = DATABASE()\n                  AND TABLE_NAME = :table\n            ");
            $stmt->execute([':table' => $table]);

            return (int)$stmt->fetchColumn() > 0;
        } catch (Throwable $e) {
            return false;
        }
    }
}


if (!function_exists('hospital_form_column_exists')) {
    function hospital_form_column_exists(string $table, string $column): bool
    {
        global $pdo;

        try {
            $stmt = $pdo->prepare("\n                SELECT COUNT(*)\n                FROM INFORMATION_SCHEMA.COLUMNS\n                WHERE TABLE_SCHEMA = DATABASE()\n                  AND TABLE_NAME = :table\n                  AND COLUMN_NAME = :column\n            ");
            $stmt->execute([
                ':table' => $table,
                ':column' => $column,
            ]);

            return (int)$stmt->fetchColumn() > 0;
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('hospital_form_ensure_hospitals_required_columns')) {
    function hospital_form_ensure_hospitals_required_columns(): void
    {
        global $pdo;

        $create_table_sql = "CREATE TABLE IF NOT EXISTS hospitals (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL,
            name_bn VARCHAR(255) NULL,
            slug VARCHAR(255) NOT NULL,
            type VARCHAR(100) NULL,
            type_bn VARCHAR(100) NULL,
            phone VARCHAR(100) NULL,
            email VARCHAR(190) NULL,
            address TEXT NULL,
            address_bn TEXT NULL,
            division_id INT UNSIGNED NULL,
            district_id INT UNSIGNED NULL,
            thana_id INT UNSIGNED NULL,
            area VARCHAR(255) NULL,
            area_bn VARCHAR(255) NULL,
            road_no VARCHAR(255) NULL,
            road_no_bn VARCHAR(255) NULL,
            house_no VARCHAR(255) NULL,
            house_no_bn VARCHAR(255) NULL,
            post_code VARCHAR(50) NULL,
            map_url TEXT NULL,
            image TEXT NULL,
            cover_image TEXT NULL,
            description LONGTEXT NULL,
            description_bn LONGTEXT NULL,
            departments_count INT UNSIGNED NOT NULL DEFAULT 0,
            doctors_count INT UNSIGNED NOT NULL DEFAULT 0,
            rating DECIMAL(3,2) NOT NULL DEFAULT 0.00,
            is_verified TINYINT(1) NOT NULL DEFAULT 0,
            is_featured TINYINT(1) NOT NULL DEFAULT 0,
            status VARCHAR(30) NOT NULL DEFAULT 'active',
            seo_title VARCHAR(255) NULL,
            seo_title_bn VARCHAR(255) NULL,
            seo_description TEXT NULL,
            seo_description_bn TEXT NULL,
            created_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
            website_url TEXT NULL,
            whatsapp VARCHAR(100) NULL,
            emergency_phone VARCHAR(100) NULL,
            ambulance_phone VARCHAR(100) NULL,
            opening_hours VARCHAR(255) NULL,
            opening_hours_bn VARCHAR(255) NULL,
            visiting_hours VARCHAR(255) NULL,
            visiting_hours_bn VARCHAR(255) NULL,
            services LONGTEXT NULL,
            services_bn LONGTEXT NULL,
            facilities LONGTEXT NULL,
            facilities_bn LONGTEXT NULL,
            bed_count INT UNSIGNED NOT NULL DEFAULT 0,
            established_year SMALLINT UNSIGNED NULL,
            video_url TEXT NULL,
            license_number VARCHAR(190) NULL,
            meta_keywords TEXT NULL,
            meta_keywords_bn TEXT NULL,
            PRIMARY KEY (id),
            KEY idx_hospitals_slug (slug),
            KEY idx_hospitals_location (division_id, district_id, thana_id),
            KEY idx_hospitals_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        try {
            $pdo->exec($create_table_sql);
        } catch (Throwable $e) {
            // Table auto-create failed. Column checks below will still try to protect the save process.
        }

        if (!hospital_form_table_exists('hospitals')) {
            return;
        }

        $columns = [
            'id' => "ALTER TABLE hospitals ADD COLUMN id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST",
            'name' => "ALTER TABLE hospitals ADD COLUMN name VARCHAR(255) NOT NULL AFTER id",
            'name_bn' => "ALTER TABLE hospitals ADD COLUMN name_bn VARCHAR(255) NULL AFTER name",
            'slug' => "ALTER TABLE hospitals ADD COLUMN slug VARCHAR(255) NOT NULL AFTER name_bn",
            'type' => "ALTER TABLE hospitals ADD COLUMN type VARCHAR(100) NULL AFTER slug",
            'type_bn' => "ALTER TABLE hospitals ADD COLUMN type_bn VARCHAR(100) NULL AFTER type",
            'phone' => "ALTER TABLE hospitals ADD COLUMN phone VARCHAR(100) NULL AFTER type_bn",
            'email' => "ALTER TABLE hospitals ADD COLUMN email VARCHAR(190) NULL AFTER phone",
            'address' => "ALTER TABLE hospitals ADD COLUMN address TEXT NULL AFTER email",
            'address_bn' => "ALTER TABLE hospitals ADD COLUMN address_bn TEXT NULL AFTER address",
            'division_id' => "ALTER TABLE hospitals ADD COLUMN division_id INT UNSIGNED NULL AFTER address_bn",
            'district_id' => "ALTER TABLE hospitals ADD COLUMN district_id INT UNSIGNED NULL AFTER division_id",
            'thana_id' => "ALTER TABLE hospitals ADD COLUMN thana_id INT UNSIGNED NULL AFTER district_id",
            'area' => "ALTER TABLE hospitals ADD COLUMN area VARCHAR(255) NULL AFTER thana_id",
            'area_bn' => "ALTER TABLE hospitals ADD COLUMN area_bn VARCHAR(255) NULL AFTER area",
            'road_no' => "ALTER TABLE hospitals ADD COLUMN road_no VARCHAR(255) NULL AFTER area_bn",
            'road_no_bn' => "ALTER TABLE hospitals ADD COLUMN road_no_bn VARCHAR(255) NULL AFTER road_no",
            'house_no' => "ALTER TABLE hospitals ADD COLUMN house_no VARCHAR(255) NULL AFTER road_no_bn",
            'house_no_bn' => "ALTER TABLE hospitals ADD COLUMN house_no_bn VARCHAR(255) NULL AFTER house_no",
            'post_code' => "ALTER TABLE hospitals ADD COLUMN post_code VARCHAR(50) NULL AFTER house_no_bn",
            'map_url' => "ALTER TABLE hospitals ADD COLUMN map_url TEXT NULL AFTER post_code",
            'image' => "ALTER TABLE hospitals ADD COLUMN image TEXT NULL AFTER map_url",
            'cover_image' => "ALTER TABLE hospitals ADD COLUMN cover_image TEXT NULL AFTER image",
            'description' => "ALTER TABLE hospitals ADD COLUMN description LONGTEXT NULL AFTER cover_image",
            'description_bn' => "ALTER TABLE hospitals ADD COLUMN description_bn LONGTEXT NULL AFTER description",
            'departments_count' => "ALTER TABLE hospitals ADD COLUMN departments_count INT UNSIGNED NOT NULL DEFAULT 0 AFTER description_bn",
            'doctors_count' => "ALTER TABLE hospitals ADD COLUMN doctors_count INT UNSIGNED NOT NULL DEFAULT 0 AFTER departments_count",
            'rating' => "ALTER TABLE hospitals ADD COLUMN rating DECIMAL(3,2) NOT NULL DEFAULT 0.00 AFTER doctors_count",
            'is_verified' => "ALTER TABLE hospitals ADD COLUMN is_verified TINYINT(1) NOT NULL DEFAULT 0 AFTER rating",
            'is_featured' => "ALTER TABLE hospitals ADD COLUMN is_featured TINYINT(1) NOT NULL DEFAULT 0 AFTER is_verified",
            'status' => "ALTER TABLE hospitals ADD COLUMN status VARCHAR(30) NOT NULL DEFAULT 'active' AFTER is_featured",
            'seo_title' => "ALTER TABLE hospitals ADD COLUMN seo_title VARCHAR(255) NULL AFTER status",
            'seo_title_bn' => "ALTER TABLE hospitals ADD COLUMN seo_title_bn VARCHAR(255) NULL AFTER seo_title",
            'seo_description' => "ALTER TABLE hospitals ADD COLUMN seo_description TEXT NULL AFTER seo_title_bn",
            'seo_description_bn' => "ALTER TABLE hospitals ADD COLUMN seo_description_bn TEXT NULL AFTER seo_description",
            'created_at' => "ALTER TABLE hospitals ADD COLUMN created_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP AFTER seo_description_bn",
            'website_url' => "ALTER TABLE hospitals ADD COLUMN website_url TEXT NULL AFTER created_at",
            'whatsapp' => "ALTER TABLE hospitals ADD COLUMN whatsapp VARCHAR(100) NULL AFTER website_url",
            'emergency_phone' => "ALTER TABLE hospitals ADD COLUMN emergency_phone VARCHAR(100) NULL AFTER whatsapp",
            'ambulance_phone' => "ALTER TABLE hospitals ADD COLUMN ambulance_phone VARCHAR(100) NULL AFTER emergency_phone",
            'opening_hours' => "ALTER TABLE hospitals ADD COLUMN opening_hours VARCHAR(255) NULL AFTER ambulance_phone",
            'opening_hours_bn' => "ALTER TABLE hospitals ADD COLUMN opening_hours_bn VARCHAR(255) NULL AFTER opening_hours",
            'visiting_hours' => "ALTER TABLE hospitals ADD COLUMN visiting_hours VARCHAR(255) NULL AFTER opening_hours_bn",
            'visiting_hours_bn' => "ALTER TABLE hospitals ADD COLUMN visiting_hours_bn VARCHAR(255) NULL AFTER visiting_hours",
            'services' => "ALTER TABLE hospitals ADD COLUMN services LONGTEXT NULL AFTER visiting_hours_bn",
            'services_bn' => "ALTER TABLE hospitals ADD COLUMN services_bn LONGTEXT NULL AFTER services",
            'facilities' => "ALTER TABLE hospitals ADD COLUMN facilities LONGTEXT NULL AFTER services_bn",
            'facilities_bn' => "ALTER TABLE hospitals ADD COLUMN facilities_bn LONGTEXT NULL AFTER facilities",
            'bed_count' => "ALTER TABLE hospitals ADD COLUMN bed_count INT UNSIGNED NOT NULL DEFAULT 0 AFTER facilities_bn",
            'established_year' => "ALTER TABLE hospitals ADD COLUMN established_year SMALLINT UNSIGNED NULL AFTER bed_count",
            'video_url' => "ALTER TABLE hospitals ADD COLUMN video_url TEXT NULL AFTER established_year",
            'license_number' => "ALTER TABLE hospitals ADD COLUMN license_number VARCHAR(190) NULL AFTER video_url",
            'meta_keywords' => "ALTER TABLE hospitals ADD COLUMN meta_keywords TEXT NULL AFTER license_number",
            'meta_keywords_bn' => "ALTER TABLE hospitals ADD COLUMN meta_keywords_bn TEXT NULL AFTER meta_keywords",
        ];

        foreach ($columns as $column => $sql) {
            try {
                if (!hospital_form_column_exists('hospitals', $column)) {
                    $pdo->exec($sql);
                }
            } catch (Throwable $e) {
                // Missing column auto-create failed. The save error will show the real database issue.
            }
        }
    }
}

hospital_form_ensure_hospitals_required_columns();

if (!function_exists('hospital_form_location_rows')) {
    function hospital_form_location_rows(string $table, string $parent_column = '', int $parent_id = 0): array
    {
        global $pdo;

        $allowed_tables = ['divisions', 'districts', 'thanas'];
        $allowed_parent_columns = ['division_id', 'district_id'];

        if (!in_array($table, $allowed_tables, true) || !hospital_form_table_exists($table)) {
            return [];
        }

        try {
            $sql = "SELECT id, name_en, name_bn FROM {$table}";
            $params = [];
            $where = [];

            if ($parent_column !== '' && in_array($parent_column, $allowed_parent_columns, true) && $parent_id > 0) {
                $where[] = "{$parent_column} = :parent_id";
                $params[':parent_id'] = $parent_id;
            }

            $where[] = "(status IS NULL OR status = '' OR status = 'active')";

            if ($where) {
                $sql .= ' WHERE ' . implode(' AND ', $where);
            }

            $sql .= ' ORDER BY name_en ASC';

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }
}

if (!function_exists('hospital_form_location_row')) {
    function hospital_form_location_row(string $table, int $id): array
    {
        global $pdo;

        $allowed_tables = ['divisions', 'districts', 'thanas'];

        if ($id <= 0 || !in_array($table, $allowed_tables, true) || !hospital_form_table_exists($table)) {
            return [];
        }

        try {
            $stmt = $pdo->prepare("SELECT id, name_en, name_bn FROM {$table} WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => $id]);

            return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }
}

if (!function_exists('hospital_form_join_address')) {
    function hospital_form_join_address(array $items): string
    {
        $clean = [];

        foreach ($items as $item) {
            $item = trim(preg_replace('/\s+/', ' ', (string)$item));

            if ($item !== '') {
                $clean[] = $item;
            }
        }

        return implode(', ', $clean);
    }
}

if (!function_exists('hospital_form_basic_slug')) {
    function hospital_form_basic_slug(string $text): string
    {
        $text = trim($text);

        if ($text === '') {
            return 'hospital';
        }

        if (function_exists('slugify')) {
            $slug = slugify($text);
        } else {
            $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $text));
            $slug = trim(preg_replace('/-+/', '-', $slug), '-');
        }

        return $slug !== '' ? $slug : 'hospital';
    }
}

if (!function_exists('hospital_form_unique_slug')) {
    function hospital_form_unique_slug(string $slug, int $ignore_id = 0): string
    {
        global $pdo;

        $slug = hospital_form_basic_slug($slug);

        if (function_exists('make_unique_slug')) {
            return make_unique_slug('hospitals', $slug, $ignore_id);
        }

        $base = $slug;
        $counter = 2;

        while (true) {
            $sql = "SELECT id FROM hospitals WHERE slug = :slug";
            $params = [':slug' => $slug];

            if ($ignore_id > 0) {
                $sql .= " AND id != :id";
                $params[':id'] = $ignore_id;
            }

            $sql .= " LIMIT 1";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
                return $slug;
            }

            $slug = $base . '-' . $counter;
            $counter++;
        }
    }
}

if (!function_exists('hospital_form_filename_slug')) {
    function hospital_form_filename_slug(string $text): string
    {
        $slug = hospital_form_basic_slug($text);
        $slug = preg_replace('/[^a-z0-9-]+/i', '', $slug);
        $slug = trim(preg_replace('/-+/', '-', $slug), '-');

        return $slug !== '' ? strtolower($slug) : 'hospital';
    }
}

/*
|--------------------------------------------------------------------------
| Image Upload Helpers
|--------------------------------------------------------------------------
*/
if (!function_exists('hospital_form_image_mime')) {
    function hospital_form_image_mime(string $tmp_name): string
    {
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);

            if ($finfo) {
                $mime = finfo_file($finfo, $tmp_name);
                finfo_close($finfo);

                if (is_string($mime) && $mime !== '') {
                    return strtolower($mime);
                }
            }
        }

        $info = @getimagesize($tmp_name);
        return !empty($info['mime']) ? strtolower((string)$info['mime']) : '';
    }
}

if (!function_exists('hospital_form_gd_source')) {
    function hospital_form_gd_source(string $tmp_name, string $mime)
    {
        switch ($mime) {
            case 'image/jpeg':
            case 'image/jpg':
                return function_exists('imagecreatefromjpeg') ? @imagecreatefromjpeg($tmp_name) : false;
            case 'image/png':
                return function_exists('imagecreatefrompng') ? @imagecreatefrompng($tmp_name) : false;
            case 'image/webp':
                return function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($tmp_name) : false;
            case 'image/gif':
                return function_exists('imagecreatefromgif') ? @imagecreatefromgif($tmp_name) : false;
            case 'image/bmp':
            case 'image/x-ms-bmp':
                return function_exists('imagecreatefrombmp') ? @imagecreatefrombmp($tmp_name) : false;
            case 'image/avif':
                return function_exists('imagecreatefromavif') ? @imagecreatefromavif($tmp_name) : false;
        }

        if (function_exists('imagecreatefromstring')) {
            $raw = @file_get_contents($tmp_name);
            return $raw !== false ? @imagecreatefromstring($raw) : false;
        }

        return false;
    }
}

if (!function_exists('hospital_form_save_webp_gd')) {
    function hospital_form_save_webp_gd(string $tmp_name, string $target, string $mime, int $max_width, int $max_height, int $quality): bool
    {
        if (!function_exists('imagewebp')) {
            return false;
        }

        $source = hospital_form_gd_source($tmp_name, $mime);

        if (!$source) {
            return false;
        }

        $width = imagesx($source);
        $height = imagesy($source);

        if ($width <= 0 || $height <= 0) {
            imagedestroy($source);
            return false;
        }

        $ratio = min($max_width / $width, $max_height / $height, 1);
        $new_width = max(1, (int)round($width * $ratio));
        $new_height = max(1, (int)round($height * $ratio));

        $canvas = imagecreatetruecolor($new_width, $new_height);

        if (!$canvas) {
            imagedestroy($source);
            return false;
        }

        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
        imagefilledrectangle($canvas, 0, 0, $new_width, $new_height, $transparent);
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $new_width, $new_height, $width, $height);

        $saved = imagewebp($canvas, $target, max(1, min(100, $quality)));

        imagedestroy($source);
        imagedestroy($canvas);

        return (bool)$saved;
    }
}

if (!function_exists('hospital_form_save_webp_imagick')) {
    function hospital_form_save_webp_imagick(string $tmp_name, string $target, int $max_width, int $max_height, int $quality): bool
    {
        if (!class_exists('Imagick')) {
            return false;
        }

        try {
            $image = new Imagick();
            $image->readImage($tmp_name);

            if ($image->getNumberImages() > 1) {
                $image = $image->coalesceImages();
                $image->setIteratorIndex(0);
            }

            $image->setImageOrientation(Imagick::ORIENTATION_TOPLEFT);
            $image->thumbnailImage($max_width, $max_height, true, true);
            $image->setImageFormat('webp');
            $image->setImageCompressionQuality(max(1, min(100, $quality)));
            $image->stripImage();

            $saved = $image->writeImage($target);
            $image->clear();
            $image->destroy();

            return (bool)$saved;
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('upload_hospital_webp_image')) {
    function upload_hospital_webp_image(string $field, string $folder, string $hospital_name, int $max_width = 1400, int $max_height = 1400, int $quality = 82): ?string
    {
        if (empty($_FILES[$field]['tmp_name']) || empty($_FILES[$field]['name'])) {
            return null;
        }

        if ((int)($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return null;
        }

        $tmp_name = $_FILES[$field]['tmp_name'];

        if (!is_uploaded_file($tmp_name) || !defined('UPLOAD_PATH') || !defined('UPLOAD_URL')) {
            return null;
        }

        $mime = hospital_form_image_mime($tmp_name);
        $allowed_mimes = [
            'image/jpeg',
            'image/jpg',
            'image/png',
            'image/webp',
            'image/gif',
            'image/bmp',
            'image/x-ms-bmp',
            'image/avif',
            'image/heic',
            'image/heif',
            'image/tiff',
            'image/svg+xml',
        ];

        if (!in_array($mime, $allowed_mimes, true)) {
            return null;
        }

        $dir = rtrim(UPLOAD_PATH, '/') . '/' . trim($folder, '/') . '/';

        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        if (!is_writable($dir)) {
            return null;
        }

        $base = hospital_form_filename_slug($hospital_name);
        $stamp = date('ymdHis');
        $filename = $base . '-' . $stamp . '.webp';
        $target = rtrim($dir, '/') . '/' . $filename;
        $counter = 2;

        while (file_exists($target)) {
            $filename = $base . '-' . $stamp . '-' . $counter . '.webp';
            $target = rtrim($dir, '/') . '/' . $filename;
            $counter++;
        }

        $saved = hospital_form_save_webp_gd($tmp_name, $target, $mime, $max_width, $max_height, $quality);

        if (!$saved) {
            $saved = hospital_form_save_webp_imagick($tmp_name, $target, $max_width, $max_height, $quality);
        }

        if (!$saved || !file_exists($target)) {
            if (file_exists($target)) {
                @unlink($target);
            }

            return null;
        }

        @chmod($target, 0644);

        return rtrim(UPLOAD_URL, '/') . '/' . trim($folder, '/') . '/' . $filename;
    }
}

/*
|--------------------------------------------------------------------------
| Hospital Types
|--------------------------------------------------------------------------
*/
$hospital_type_map = [
    'Private Hospital' => 'বেসরকারি হাসপাতাল',
    'Government Hospital' => 'সরকারি হাসপাতাল',
    'Medical College Hospital' => 'মেডিকেল কলেজ হাসপাতাল',
    'Specialized Hospital' => 'বিশেষায়িত হাসপাতাল',
    'General Hospital' => 'জেনারেল হাসপাতাল',
    'Diagnostic Center' => 'ডায়াগনস্টিক সেন্টার',
    'Clinic' => 'ক্লিনিক',
    'Eye Hospital' => 'চক্ষু হাসপাতাল',
    'Dental Hospital' => 'ডেন্টাল হাসপাতাল',
    'Cardiac Hospital' => 'কার্ডিয়াক হাসপাতাল',
    'Cancer Hospital' => 'ক্যান্সার হাসপাতাল',
    'Mother and Child Hospital' => 'মা ও শিশু হাসপাতাল',
    'Rehabilitation Center' => 'পুনর্বাসন কেন্দ্র',
];

$hospital_types = array_keys($hospital_type_map);


/*
|--------------------------------------------------------------------------
| Location AJAX
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['hf_location'])) {
    header('Content-Type: application/json; charset=utf-8');

    $type = hospital_form_clean($_GET['hf_location'] ?? '');

    if ($type === 'districts') {
        echo json_encode(hospital_form_location_rows('districts', 'division_id', (int)($_GET['division_id'] ?? 0)));
        exit;
    }

    if ($type === 'thanas') {
        echo json_encode(hospital_form_location_rows('thanas', 'district_id', (int)($_GET['district_id'] ?? 0)));
        exit;
    }

    echo json_encode([]);
    exit;
}

/*
|--------------------------------------------------------------------------
| Load Existing Hospital
|--------------------------------------------------------------------------
*/
if ($id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM hospitals WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $id]);
    $hospital = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

if (isset($_GET['slug_saved']) && $_GET['slug_saved'] === '1') {
    $form_message_type = 'success';
    $form_message = 'Slug saved successfully.';
}

// Post/Redirect/Get: after a normal hospital save, show the success message
// from a fresh GET request so browser Back never asks to resubmit the form.
if (isset($_GET['saved']) && $_GET['saved'] === '1') {
    $form_message_type = 'success';
    $form_message = 'Hospital information saved successfully.';
}

/*
|--------------------------------------------------------------------------
| Handle Slug Save Only
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_action'] ?? '') === 'save_slug') {
    if (!$id || !$hospital) {
        $form_message_type = 'error';
        $form_message = 'Please save the hospital first, then update the slug.';
    } else {
        $manual_slug = hospital_form_clean($_POST['slug'] ?? '');

        if ($manual_slug === '') {
            $form_message_type = 'error';
            $form_message = 'Slug cannot be empty.';
        } else {
            try {
                $hospital_slug = hospital_form_unique_slug($manual_slug, $id);
                $stmt = $pdo->prepare("UPDATE hospitals SET slug = :slug WHERE id = :id");
                $stmt->execute([
                    ':slug' => $hospital_slug,
                    ':id' => $id,
                ]);

                $redirect_url = basename($_SERVER['PHP_SELF']) . '?id=' . $id . '&slug_saved=1';
                header('Location: ' . $redirect_url, true, 303);
                exit;
            } catch (PDOException $e) {
                $form_message_type = 'error';
                $form_message = 'Slug could not be saved. Error: ' . $e->getMessage();
            }
        }
    }
}

/*
|--------------------------------------------------------------------------
| Handle Save
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_action'] ?? '') !== 'save_slug') {
    $hospital_name = hospital_form_clean($_POST['name'] ?? '');
    $hospital_name_bn = hospital_form_clean($_POST['name_bn'] ?? '');

    if ($hospital_name === '') {
        $form_message_type = 'error';
        $form_message = 'Hospital name is required.';
    } else {
        $posted_type = hospital_form_clean($_POST['type'] ?? 'Private Hospital');
        $hospital_type = in_array($posted_type, $hospital_types, true) ? $posted_type : 'Private Hospital';
        $hospital_type_bn = hospital_form_clean($_POST['type_bn'] ?? '');

        if ($hospital_type_bn === '') {
            $hospital_type_bn = $hospital_type_map[$hospital_type] ?? '';
        }

        $division_id = (int)($_POST['division_id'] ?? 0);
        $district_id = (int)($_POST['district_id'] ?? 0);
        $thana_id = (int)($_POST['thana_id'] ?? 0);

        $division_row = hospital_form_location_row('divisions', $division_id);
        $district_row = hospital_form_location_row('districts', $district_id);
        $thana_row = hospital_form_location_row('thanas', $thana_id);

        // Division is retained only as a relational ID for filtering and dependent dropdowns.
        // It is intentionally excluded from the saved postal address.
        $district_name = hospital_form_clean($district_row['name_en'] ?? '');
        $district_name_bn = hospital_form_clean($district_row['name_bn'] ?? '');
        $thana_name = hospital_form_clean($thana_row['name_en'] ?? '');
        $thana_name_bn = hospital_form_clean($thana_row['name_bn'] ?? '');
        $area = hospital_form_clean($_POST['area'] ?? '');
        $area_bn = hospital_form_clean($_POST['area_bn'] ?? '');
        $road_no = hospital_form_clean($_POST['road_no'] ?? '');
        $road_no_bn = hospital_form_clean($_POST['road_no_bn'] ?? '');
        $house_no = hospital_form_clean($_POST['house_no'] ?? '');
        $house_no_bn = hospital_form_clean($_POST['house_no_bn'] ?? '');
        $post_code = hospital_form_clean($_POST['post_code'] ?? '');

        $thana_with_post_code = $post_code !== ''
            ? ($thana_name !== '' ? $thana_name . '-' . $post_code : $post_code)
            : $thana_name;

        $thana_with_post_code_bn = $post_code !== ''
            ? ($thana_name_bn !== '' ? $thana_name_bn . '-' . $post_code : $post_code)
            : $thana_name_bn;

        // Keep the address concise: house, road, area, thana/post code and district.
        // Division remains stored separately in division_id and is not added to address fields.
        $full_address = hospital_form_join_address([
            $house_no,
            $road_no,
            $area,
            $thana_with_post_code,
            $district_name,
        ]);

        $full_address_bn = hospital_form_join_address([
            $house_no_bn !== '' ? $house_no_bn : $house_no,
            $road_no_bn !== '' ? $road_no_bn : $road_no,
            $area_bn !== '' ? $area_bn : $area,
            $thana_with_post_code_bn !== '' ? $thana_with_post_code_bn : $thana_with_post_code,
            $district_name_bn !== '' ? $district_name_bn : $district_name,
        ]);

        $manual_slug = hospital_form_clean($_POST['slug'] ?? '');

        if ($manual_slug !== '') {
            $hospital_slug = hospital_form_unique_slug($manual_slug, $id);
        } elseif ($id && !empty($hospital['slug'])) {
            $hospital_slug = $hospital['slug'];
        } else {
            $hospital_slug = hospital_form_unique_slug($hospital_name . '-' . $district_name, $id);
        }

        $image = upload_hospital_webp_image('image', 'hospitals', $hospital_name, 1400, 1400, 82)
            ?: hospital_form_clean($_POST['old_image'] ?? '');

        $cover = upload_hospital_webp_image('cover_image', 'hospitals', $hospital_name, 1920, 1080, 82)
            ?: hospital_form_clean($_POST['old_cover_image'] ?? '');

        $data = [
            ':name' => $hospital_name,
            ':name_bn' => $hospital_name_bn,
            ':slug' => $hospital_slug,
            ':type' => $hospital_type,
            ':type_bn' => $hospital_type_bn,
            ':phone' => hospital_form_clean($_POST['phone'] ?? ''),
            ':email' => hospital_form_clean($_POST['email'] ?? ''),
            ':address' => $full_address,
            ':address_bn' => $full_address_bn,
            ':division_id' => $division_id ?: null,
            ':district_id' => $district_id ?: null,
            ':thana_id' => $thana_id ?: null,
            ':area' => $area,
            ':area_bn' => $area_bn,
            ':road_no' => $road_no,
            ':road_no_bn' => $road_no_bn,
            ':house_no' => $house_no,
            ':house_no_bn' => $house_no_bn,
            ':post_code' => $post_code,
            ':map_url' => hospital_form_clean($_POST['map_url'] ?? ''),
            ':image' => $image,
            ':cover_image' => $cover,
            ':description' => $_POST['description'] ?? '',
            ':description_bn' => $_POST['description_bn'] ?? '',
            ':departments_count' => (int)($hospital['departments_count'] ?? 0),
            ':doctors_count' => (int)($hospital['doctors_count'] ?? 0),
            ':rating' => (float)($hospital['rating'] ?? 0),
            ':is_verified' => isset($_POST['is_verified']) ? 1 : 0,
            ':is_featured' => isset($_POST['is_featured']) ? 1 : 0,
            ':status' => hospital_form_clean($_POST['status'] ?? 'active'),
            ':seo_title' => hospital_form_clean($_POST['seo_title'] ?? ''),
            ':seo_title_bn' => hospital_form_clean($_POST['seo_title_bn'] ?? ''),
            ':seo_description' => $_POST['seo_description'] ?? '',
            ':seo_description_bn' => $_POST['seo_description_bn'] ?? '',
            ':website_url' => hospital_form_clean($_POST['website_url'] ?? ''),
            ':whatsapp' => hospital_form_clean($_POST['whatsapp'] ?? ''),
            ':emergency_phone' => hospital_form_clean($_POST['emergency_phone'] ?? ''),
            ':ambulance_phone' => hospital_form_clean($_POST['ambulance_phone'] ?? ''),
            ':opening_hours' => hospital_form_clean($_POST['opening_hours'] ?? ''),
            ':opening_hours_bn' => hospital_form_clean($_POST['opening_hours_bn'] ?? ''),
            ':visiting_hours' => hospital_form_clean($_POST['visiting_hours'] ?? ''),
            ':visiting_hours_bn' => hospital_form_clean($_POST['visiting_hours_bn'] ?? ''),
            ':services' => $_POST['services'] ?? '',
            ':services_bn' => $_POST['services_bn'] ?? '',
            ':facilities' => $_POST['facilities'] ?? '',
            ':facilities_bn' => $_POST['facilities_bn'] ?? '',
            ':bed_count' => (int)($_POST['bed_count'] ?? 0),
            ':established_year' => hospital_form_int_or_null($_POST['established_year'] ?? ''),
            ':video_url' => hospital_form_clean($_POST['video_url'] ?? ''),
            ':license_number' => hospital_form_clean($_POST['license_number'] ?? ''),
            ':meta_keywords' => hospital_form_clean($_POST['meta_keywords'] ?? ''),
            ':meta_keywords_bn' => hospital_form_clean($_POST['meta_keywords_bn'] ?? ''),
        ];

        try {
            if ($id > 0 && $hospital) {
                $data[':id'] = $id;

                $sql = "UPDATE hospitals SET
                    name = :name,
                    name_bn = :name_bn,
                    slug = :slug,
                    type = :type,
                    type_bn = :type_bn,
                    phone = :phone,
                    email = :email,
                    address = :address,
                    address_bn = :address_bn,
                    division_id = :division_id,
                    district_id = :district_id,
                    thana_id = :thana_id,
                    area = :area,
                    area_bn = :area_bn,
                    road_no = :road_no,
                    road_no_bn = :road_no_bn,
                    house_no = :house_no,
                    house_no_bn = :house_no_bn,
                    post_code = :post_code,
                    map_url = :map_url,
                    image = :image,
                    cover_image = :cover_image,
                    description = :description,
                    description_bn = :description_bn,
                    departments_count = :departments_count,
                    doctors_count = :doctors_count,
                    rating = :rating,
                    is_verified = :is_verified,
                    is_featured = :is_featured,
                    status = :status,
                    seo_title = :seo_title,
                    seo_title_bn = :seo_title_bn,
                    seo_description = :seo_description,
                    seo_description_bn = :seo_description_bn,
                    website_url = :website_url,
                    whatsapp = :whatsapp,
                    emergency_phone = :emergency_phone,
                    ambulance_phone = :ambulance_phone,
                    opening_hours = :opening_hours,
                    opening_hours_bn = :opening_hours_bn,
                    visiting_hours = :visiting_hours,
                    visiting_hours_bn = :visiting_hours_bn,
                    services = :services,
                    services_bn = :services_bn,
                    facilities = :facilities,
                    facilities_bn = :facilities_bn,
                    bed_count = :bed_count,
                    established_year = :established_year,
                    video_url = :video_url,
                    license_number = :license_number,
                    meta_keywords = :meta_keywords,
                    meta_keywords_bn = :meta_keywords_bn
                    WHERE id = :id";
            } else {
                $sql = "INSERT INTO hospitals
                (
                    name, name_bn, slug, type, type_bn, phone, email,
                    address, address_bn, division_id, district_id, thana_id,
                    area, area_bn, road_no, road_no_bn, house_no, house_no_bn,
                    post_code, map_url, image, cover_image, description, description_bn,
                    departments_count, doctors_count, rating, is_verified, is_featured, status,
                    seo_title, seo_title_bn, seo_description, seo_description_bn, created_at,
                    website_url, whatsapp, emergency_phone, ambulance_phone,
                    opening_hours, opening_hours_bn, visiting_hours, visiting_hours_bn,
                    services, services_bn, facilities, facilities_bn,
                    bed_count, established_year,
                    video_url, license_number,
                    meta_keywords, meta_keywords_bn
                )
                VALUES
                (
                    :name, :name_bn, :slug, :type, :type_bn, :phone, :email,
                    :address, :address_bn, :division_id, :district_id, :thana_id,
                    :area, :area_bn, :road_no, :road_no_bn, :house_no, :house_no_bn,
                    :post_code, :map_url, :image, :cover_image, :description, :description_bn,
                    :departments_count, :doctors_count, :rating, :is_verified, :is_featured, :status,
                    :seo_title, :seo_title_bn, :seo_description, :seo_description_bn, NOW(),
                    :website_url, :whatsapp, :emergency_phone, :ambulance_phone,
                    :opening_hours, :opening_hours_bn, :visiting_hours, :visiting_hours_bn,
                    :services, :services_bn, :facilities, :facilities_bn,
                    :bed_count, :established_year,
                    :video_url, :license_number,
                    :meta_keywords, :meta_keywords_bn
                )";
            }

            $stmt = $pdo->prepare($sql);
            $stmt->execute($data);

            if (!$id) {
                $id = (int)$pdo->lastInsertId();
            }

            // Post/Redirect/Get prevents the browser from showing a
            // Confirm Form Resubmission prompt after Save & Update Hospital.
            $redirect_url = basename($_SERVER['PHP_SELF']) . '?id=' . $id . '&saved=1';
            header('Location: ' . $redirect_url, true, 303);
            exit;
        } catch (PDOException $e) {
            $form_message_type = 'error';
            $form_message = 'Hospital could not be saved. Error: ' . $e->getMessage();
        }
    }
}

$current_type = $hospital['type'] ?? 'Private Hospital';

if (!in_array($current_type, $hospital_types, true)) {
    $current_type = 'Private Hospital';
}

$current_type_bn = trim((string)($hospital['type_bn'] ?? ''));

if ($current_type_bn === '') {
    $current_type_bn = $hospital_type_map[$current_type] ?? '';
}

$saved_division_id = (int)($hospital['division_id'] ?? 0);
$saved_district_id = (int)($hospital['district_id'] ?? 0);
$saved_thana_id = (int)($hospital['thana_id'] ?? 0);

$divisions = hospital_form_location_rows('divisions');
$districts = $saved_division_id > 0 ? hospital_form_location_rows('districts', 'division_id', $saved_division_id) : [];
$thanas = $saved_district_id > 0 ? hospital_form_location_rows('thanas', 'district_id', $saved_district_id) : [];

$browser_title = $id && !empty($hospital['name'])
    ? $hospital['name']
    : ($id ? 'Edit Hospital' : 'Add Hospital');

ob_start();
require_once __DIR__ . '/includes/header.php';
$admin_header_html = ob_get_clean();
$admin_header_title = e($browser_title) . ' | ' . e(APP_NAME);
$admin_header_html = preg_replace('/<title>.*?<\/title>/is', '<title>' . $admin_header_title . '</title>', $admin_header_html, 1);
echo $admin_header_html;
?>

<style>
  :root {
    --hf-bg:#f6f8fa;
    --hf-card:#fff;
    --hf-text:#24292f;
    --hf-muted:#57606a;
    --hf-border:#d0d7de;
    --hf-blue:#0969da;
    --hf-blue-soft:#ddf4ff;
    --hf-green:#1a7f37;
    --hf-red:#cf222e;
    --hf-red-soft:#ffebe9;
    --hf-shadow:0 1px 0 rgba(27,31,36,.04);
  }

  body { background: var(--hf-bg); }

  .hospital-form-page,
  .hospital-form-page * {
    box-sizing: border-box;
    font-weight: 500 !important;
  }

  .hospital-form-page {
    max-width: 1280px;
    margin: 0 auto;
    padding: 16px 16px 36px;
    color: var(--hf-text);
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Helvetica, Arial, sans-serif;
  }

  .hf-hero,
  .hf-card {
    background: var(--hf-card);
    border: 1px solid var(--hf-border);
    border-radius: 12px;
    box-shadow: var(--hf-shadow);
  }

  /*
   * Keep the form-card overflow visible so the sticky save bar can stay
   * visible while the administrator scrolls through the hospital form.
   */
  .hf-card {
    overflow: visible;
  }

  .hf-hero {
    overflow: hidden;
    margin-bottom: 16px;
    padding: 18px;
  }

  .hf-hero-inner {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 20px;
  }

  .hf-breadcrumb {
    display: inline-flex;
    gap: 7px;
    align-items: center;
    margin-bottom: 10px;
    color: var(--hf-muted);
    font-size: 13px;
  }

  .hf-breadcrumb a,
  .hf-current-file { color: var(--hf-blue); text-decoration: none; }

  .hf-hero h1 {
    margin: 0;
    font-size: clamp(25px, 3vw, 34px);
    line-height: 1.15;
    letter-spacing: -.025em;
  }

  .hf-hero p,
  .hf-card-header p,
  .hf-submit-bar p,
  .hf-help {
    color: var(--hf-muted);
  }

  .hf-hero p {
    max-width: 760px;
    margin: 8px 0 0;
    font-size: 14px;
    line-height: 1.65;
  }

  .hf-actions,
  .hf-submit-actions {
    display: flex;
    gap: 9px;
    flex-wrap: wrap;
    justify-content: flex-end;
  }

  .hf-btn {
    min-height: 38px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 8px 13px;
    border: 1px solid rgba(27,31,36,.15);
    border-radius: 7px;
    color: var(--hf-text);
    background: #f6f8fa;
    text-decoration: none;
    cursor: pointer;
    font-size: 13.5px;
    line-height: 1.4;
  }

  .hf-btn:hover { background: #eef1f4; }
  .hf-btn-primary { color: #fff; background: #2da44e; }
  .hf-btn-primary:hover { background: #1f883d; }

  .hf-alert {
    display: flex;
    gap: 12px;
    margin-bottom: 16px;
    padding: 13px 15px;
    border-radius: 10px;
    border: 1px solid var(--hf-border);
    background: #fff;
  }

  .hf-alert.success { color: #065f46; background: #ecfdf5; border-color: #a7f3d0; }
  .hf-alert.error { color: #9f1239; background: var(--hf-red-soft); border-color: #fecdd3; }

  .hf-alert-icon {
    width: 34px;
    height: 34px;
    border-radius: 9px;
    display: grid;
    place-items: center;
    color: #fff;
    background: var(--hf-green);
    flex: 0 0 34px;
  }

  .hf-alert.error .hf-alert-icon { background: var(--hf-red); }
  .hf-alert h3 { margin: 0 0 4px; font-size: 15px; }
  .hf-alert p { margin: 0; font-size: 13px; line-height: 1.55; }

  .hf-card-header {
    padding: 18px 20px;
    border-bottom: 1px solid var(--hf-border);
    background: #f6f8fa;
  }

  .hf-card-header h2 { margin: 0; font-size: 20px; }
  .hf-card-header p { margin: 6px 0 0; font-size: 13px; line-height: 1.55; }

  .hf-form { padding: 20px; }

  .hf-section {
    margin-bottom: 18px;
    border: 1px solid var(--hf-border);
    border-radius: 12px;
    overflow: hidden;
    background: #fff;
  }

  .hf-section-title {
    padding: 14px 16px;
    border-bottom: 1px solid var(--hf-border);
    background: #f6f8fa;
  }

  .hf-section-title h3 { margin: 0; font-size: 16px; }
  .hf-section-title span { display: block; margin-top: 3px; color: var(--hf-muted); font-size: 12.5px; }

  .hf-grid,
  .hf-grid-3,
  .hf-en-bn-row {
    display: grid;
    gap: 16px;
    padding: 16px;
  }

  .hf-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .hf-grid-3 { grid-template-columns: repeat(3, minmax(0, 1fr)); }
  .hf-en-bn-row { grid-template-columns: repeat(2, minmax(0, 1fr)); }

  .hf-field { display: grid; gap: 7px; min-width: 0; }
  .hf-field.full { grid-column: 1 / -1; }

  .hf-field label,
  .hf-toggle-label {
    color: #344054;
    font-size: 13.5px;
    line-height: 1.35;
  }

  .hf-label-bn { color: var(--hf-muted); }

  .hf-field input,
  .hf-field select,
  .hf-field textarea {
    width: 100%;
    min-height: 40px;
    padding: 9px 11px;
    border: 1px solid var(--hf-border);
    border-radius: 7px;
    background: #fff;
    color: var(--hf-text);
    font-family: inherit;
    font-size: 14px;
    outline: none;
    box-shadow: inset 0 1px 0 rgba(208,215,222,.2);
  }

  .hf-field textarea {
    min-height: 118px;
    resize: vertical;
    line-height: 1.65;
  }

  .hf-field input:focus,
  .hf-field select:focus,
  .hf-field textarea:focus {
    border-color: var(--hf-blue);
    box-shadow: 0 0 0 3px rgba(9,105,218,.12);
  }

  .hf-help { font-size: 12px; line-height: 1.5; }
  .hf-required { color: var(--hf-red); }

  .hf-inline {
    display: grid;
    grid-template-columns: 1fr auto;
    gap: 8px;
    align-items: end;
  }

  .hf-slug-inline {
    grid-template-columns: 1fr auto auto;
  }

  .hf-file-box {
    padding: 13px;
    border: 1px dashed var(--hf-border);
    border-radius: 8px;
    background: #f6f8fa;
  }

  .hf-current-file {
    display: inline-flex;
    margin-top: 8px;
    padding: 4px 8px;
    border-radius: 999px;
    background: var(--hf-blue-soft);
    border: 1px solid #54aeff;
    font-size: 12px;
  }

  .hf-preview {
    min-height: 42px;
    padding: 10px 12px;
    border: 1px dashed var(--hf-border);
    border-radius: 8px;
    background: #f6f8fa;
    color: var(--hf-muted);
    font-size: 13px;
    line-height: 1.55;
  }

  .hf-toggle-row {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    padding: 16px;
  }

  .hf-toggle-label {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    min-height: 38px;
    padding: 8px 10px;
    border: 1px solid var(--hf-border);
    border-radius: 7px;
    background: #fff;
    cursor: pointer;
  }

  .hf-toggle-label:hover { background: #f6f8fa; border-color: var(--hf-blue); }
  .hf-toggle-label input { width: 16px; height: 16px; accent-color: var(--hf-green); }

  /*
   * Same sticky save-bar behavior used by the Doctor Form.
   * It remains visible at the bottom while scrolling inside this form.
   */
  .hf-submit-bar {
    position: sticky;
    bottom: 16px;
    z-index: 5;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
    padding: 14px;
    border-radius: 12px;
    background: rgba(255,255,255,.92);
    border: 1px solid var(--hf-border);
    box-shadow: 0 12px 35px rgba(15,23,42,.1);
    backdrop-filter: blur(14px);
  }

  .hf-submit-bar p {
    margin: 0;
    color: var(--hf-muted);
    font-size: 13px;
  }

  @media(max-width: 900px) {
    .hf-hero-inner,
    .hf-submit-bar { flex-direction: column; align-items: stretch; }
    .hf-actions,
    .hf-submit-actions { justify-content: flex-start; }
    .hf-grid,
    .hf-grid-3,
    .hf-en-bn-row,
    .hf-inline { grid-template-columns: 1fr; }
    .hf-btn { width: 100%; }
  }
</style>

<div class="hospital-form-page">
    <?php if ($form_message !== ''): ?>
        <div class="hf-alert <?= e($form_message_type) ?>">
            <div class="hf-alert-icon"><?= $form_message_type === 'success' ? '✓' : '!' ?></div>
            <div>
                <h3><?= $form_message_type === 'success' ? 'Success' : 'Error' ?></h3>
                <p><?= e($form_message) ?></p>
            </div>
        </div>
    <?php endif; ?>

    <div class="hf-hero">
        <div class="hf-hero-inner">
            <div>
                <div class="hf-breadcrumb">
                    <a href="dashboard.php">Dashboard</a>
                    <span>/</span>
                    <a href="hospitals.php">Hospitals</a>
                    <span>/</span>
                    <span><?= $id ? 'Edit' : 'Add New' ?></span>
                </div>

                <h1><?= $id ? e($hospital['name'] ?? 'Edit Hospital') : 'Add Hospital' ?></h1>
                <p>Add or update hospital profile information. This clean form has no external table dependency and no dynamic option panel.</p>
            </div>

            <div class="hf-actions">
                <a href="hospitals.php" class="hf-btn">← Back to Hospitals</a>

                <?php if ($id && !empty($hospital['slug'])): ?>
                    <a href="../hospital/<?= e($hospital['slug']) ?>" target="_blank" rel="noopener" class="hf-btn">View Hospital Page</a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="hf-card">
        <div class="hf-card-header">
            <h2><?= $id ? 'Update ' . e($hospital['name'] ?? 'Hospital') : 'Create New Hospital' ?></h2>
            <p>English fields stay on the left, Bangla fields stay on the right. Service and facility fields are manual textareas only.</p>
        </div>

        <form class="hf-form" id="hospitalForm" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="old_image" value="<?= e($hospital['image'] ?? '') ?>">
            <input type="hidden" name="old_cover_image" value="<?= e($hospital['cover_image'] ?? '') ?>">

            <div class="hf-section">
                <div class="hf-section-title">
                    <h3>Basic Information</h3>
                    <span>Hospital identity, type and public profile URL.</span>
                </div>

                <div class="hf-en-bn-row">
                    <div class="hf-field">
                        <label>Hospital Name <span class="hf-required">*</span></label>
                        <input type="text" name="name" id="hospitalNameInput" placeholder="Example: Square Hospital" value="<?= e($hospital['name'] ?? '') ?>" required>
                    </div>

                    <div class="hf-field">
                        <label class="hf-label-bn">Hospital Name Bangla</label>
                        <input type="text" name="name_bn" placeholder="উদাহরণ: স্কয়ার হাসপাতাল" value="<?= e($hospital['name_bn'] ?? '') ?>">
                    </div>
                </div>

                <div class="hf-en-bn-row">
                    <div class="hf-field">
                        <label>Hospital Type</label>
                        <select name="type" id="hospitalTypeSelect">
                            <?php foreach ($hospital_types as $type): ?>
                                <option value="<?= e($type) ?>" <?= $current_type === $type ? 'selected' : '' ?>>
                                    <?= e($type) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="hf-field">
                        <label class="hf-label-bn">Hospital Type Bangla</label>
                        <select name="type_bn" id="hospitalTypeBnSelect">
                            <?php foreach ($hospital_type_map as $type_en => $type_bn): ?>
                                <option value="<?= e($type_bn) ?>" data-type-en="<?= e($type_en) ?>" <?= $current_type_bn === $type_bn ? 'selected' : '' ?>>
                                    <?= e($type_bn) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="hf-grid">
                    <div class="hf-field">
                        <label>License Number</label>
                        <input type="text" name="license_number" placeholder="Example: DGHS-123456" value="<?= e($hospital['license_number'] ?? '') ?>">
                    </div>

                    <div class="hf-field">
                        <label>Established Year</label>
                        <input type="number" name="established_year" placeholder="Example: 2005" value="<?= e((string)($hospital['established_year'] ?? '')) ?>">
                    </div>

                    <div class="hf-field">
                        <label>Bed Count</label>
                        <input type="number" name="bed_count" placeholder="Example: 250" value="<?= e((string)($hospital['bed_count'] ?? '0')) ?>">
                    </div>

                    <div class="hf-field">
                        <label>Website URL</label>
                        <input type="url" name="website_url" placeholder="https://example.com" value="<?= e($hospital['website_url'] ?? '') ?>">
                    </div>

                    <div class="hf-field full">
                        <label>Slug</label>
                        <div class="hf-inline hf-slug-inline">
                            <input type="text" name="slug" id="hospitalSlugInput" placeholder="auto-generated after save" value="<?= e($hospital['slug'] ?? '') ?>" readonly>
                            <button type="button" class="hf-btn" id="editSlugBtn">Edit Slug</button>
                            <button type="submit" class="hf-btn hf-btn-primary" name="form_action" value="save_slug" id="saveSlugBtn" formnovalidate style="display:none;" <?= $id ? '' : 'disabled' ?>>Save Slug</button>
                        </div>
                        <span class="hf-help">Click Edit Slug, change the slug, then click Save Slug. It will save only the slug and reload this page.</span>
                    </div>
                </div>
            </div>

            <div class="hf-section">
                <div class="hf-section-title">
                    <h3>Address Information</h3>
                    <span>Division, District and Thana come from database dropdown tables.</span>
                </div>

                <div class="hf-grid-3">
                    <div class="hf-field">
                        <label>Division <span class="hf-required">*</span></label>
                        <select id="hospital_division_id" name="division_id" required>
                            <option value="">— Select Division —</option>
                            <?php foreach ($divisions as $division): ?>
                                <option
                                    value="<?= e((string)$division['id']) ?>"
                                    data-name-en="<?= e($division['name_en'] ?? '') ?>"
                                    data-name-bn="<?= e($division['name_bn'] ?? '') ?>"
                                    <?= $saved_division_id === (int)$division['id'] ? 'selected' : '' ?>
                                >
                                    <?= e($division['name_en'] ?? '') ?><?= !empty($division['name_bn']) ? ' / ' . e($division['name_bn']) : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="hf-field">
                        <label>District <span class="hf-required">*</span></label>
                        <select id="hospital_district_id" name="district_id" data-current="<?= e((string)$saved_district_id) ?>" required <?= $saved_division_id > 0 ? '' : 'disabled' ?>>
                            <option value="">— Select District —</option>
                            <?php foreach ($districts as $district): ?>
                                <option
                                    value="<?= e((string)$district['id']) ?>"
                                    data-name-en="<?= e($district['name_en'] ?? '') ?>"
                                    data-name-bn="<?= e($district['name_bn'] ?? '') ?>"
                                    <?= $saved_district_id === (int)$district['id'] ? 'selected' : '' ?>
                                >
                                    <?= e($district['name_en'] ?? '') ?><?= !empty($district['name_bn']) ? ' / ' . e($district['name_bn']) : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="hf-field">
                        <label>Thana / Upazila</label>
                        <select id="hospital_thana_id" name="thana_id" data-current="<?= e((string)$saved_thana_id) ?>" <?= $saved_district_id > 0 ? '' : 'disabled' ?>>
                            <option value="">— Select Thana —</option>
                            <?php foreach ($thanas as $thana): ?>
                                <option
                                    value="<?= e((string)$thana['id']) ?>"
                                    data-name-en="<?= e($thana['name_en'] ?? '') ?>"
                                    data-name-bn="<?= e($thana['name_bn'] ?? '') ?>"
                                    <?= $saved_thana_id === (int)$thana['id'] ? 'selected' : '' ?>
                                >
                                    <?= e($thana['name_en'] ?? '') ?><?= !empty($thana['name_bn']) ? ' / ' . e($thana['name_bn']) : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="hf-en-bn-row">
                    <div class="hf-field">
                        <label>Area / Locality</label>
                        <input type="text" id="hospital_area" name="area" placeholder="Example: Panthapath" value="<?= e($hospital['area'] ?? '') ?>">
                    </div>

                    <div class="hf-field">
                        <label class="hf-label-bn">Area / Locality Bangla</label>
                        <input type="text" id="hospital_area_bn" name="area_bn" placeholder="উদাহরণ: পান্থপথ" value="<?= e($hospital['area_bn'] ?? '') ?>">
                    </div>
                </div>

                <div class="hf-en-bn-row">
                    <div class="hf-field">
                        <label>Road / Street</label>
                        <input type="text" id="hospital_road_no" name="road_no" placeholder="Example: Road 8/A" value="<?= e($hospital['road_no'] ?? '') ?>">
                    </div>

                    <div class="hf-field">
                        <label class="hf-label-bn">Road / Street Bangla</label>
                        <input type="text" id="hospital_road_no_bn" name="road_no_bn" placeholder="উদাহরণ: রোড ৮/এ" value="<?= e($hospital['road_no_bn'] ?? '') ?>">
                    </div>
                </div>

                <div class="hf-en-bn-row">
                    <div class="hf-field">
                        <label>House / Building</label>
                        <input type="text" id="hospital_house_no" name="house_no" placeholder="Example: House 12, Level 3" value="<?= e($hospital['house_no'] ?? '') ?>">
                    </div>

                    <div class="hf-field">
                        <label class="hf-label-bn">House / Building Bangla</label>
                        <input type="text" id="hospital_house_no_bn" name="house_no_bn" placeholder="উদাহরণ: বাড়ি ১২, লেভেল ৩" value="<?= e($hospital['house_no_bn'] ?? '') ?>">
                    </div>
                </div>

                <div class="hf-grid">
                    <div class="hf-field">
                        <label>Post Code</label>
                        <input type="text" id="hospital_post_code" name="post_code" placeholder="1205" value="<?= e($hospital['post_code'] ?? '') ?>">
                    </div>

                    <div class="hf-field">
                        <label>Google Map URL</label>
                        <input type="url" name="map_url" placeholder="https://maps.google.com/..." value="<?= e($hospital['map_url'] ?? '') ?>">
                    </div>

                    <div class="hf-field full">
                        <label>Address Preview</label>
                        <div class="hf-preview" id="hospital_address_preview">Address preview will appear here.</div>
                    </div>

                    <div class="hf-field full">
                        <label class="hf-label-bn">Address Preview Bangla</label>
                        <div class="hf-preview" id="hospital_address_preview_bn">বাংলা ঠিকানার preview এখানে দেখাবে।</div>
                    </div>
                </div>
            </div>

            <div class="hf-section">
                <div class="hf-section-title">
                    <h3>Contact Information</h3>
                    <span>Phone, email, WhatsApp and emergency contact numbers.</span>
                </div>

                <div class="hf-grid">
                    <div class="hf-field">
                        <label>Phone</label>
                        <input type="text" name="phone" placeholder="+8801712345678" value="<?= e($hospital['phone'] ?? '') ?>">
                    </div>

                    <div class="hf-field">
                        <label>WhatsApp</label>
                        <input type="text" name="whatsapp" placeholder="+8801712345678" value="<?= e($hospital['whatsapp'] ?? '') ?>">
                    </div>

                    <div class="hf-field">
                        <label>Emergency Phone</label>
                        <input type="text" name="emergency_phone" placeholder="+8801712345678" value="<?= e($hospital['emergency_phone'] ?? '') ?>">
                    </div>

                    <div class="hf-field">
                        <label>Ambulance Phone</label>
                        <input type="text" name="ambulance_phone" placeholder="+8801712345678" value="<?= e($hospital['ambulance_phone'] ?? '') ?>">
                    </div>

                    <div class="hf-field full">
                        <label>Email</label>
                        <input type="email" name="email" placeholder="hospital@example.com" value="<?= e($hospital['email'] ?? '') ?>">
                    </div>
                </div>
            </div>

            <div class="hf-section">
                <div class="hf-section-title">
                    <h3>Description & Timing</h3>
                    <span>Services, facilities, insurance and payment methods are manual text only.</span>
                </div>

                <div class="hf-en-bn-row">
                    <div class="hf-field">
                        <label>Description</label>
                        <textarea name="description" placeholder="Write hospital description"><?= e($hospital['description'] ?? '') ?></textarea>
                    </div>

                    <div class="hf-field">
                        <label class="hf-label-bn">Description Bangla</label>
                        <textarea name="description_bn" placeholder="হাসপাতালের বাংলা বিবরণ লিখুন"><?= e($hospital['description_bn'] ?? '') ?></textarea>
                    </div>
                </div>

                <div class="hf-en-bn-row">
                    <div class="hf-field">
                        <label>Opening Hours</label>
                        <input type="text" name="opening_hours" placeholder="Example: 24/7 Open" value="<?= e($hospital['opening_hours'] ?? '') ?>">
                    </div>

                    <div class="hf-field">
                        <label class="hf-label-bn">Opening Hours Bangla</label>
                        <input type="text" name="opening_hours_bn" placeholder="উদাহরণ: ২৪ ঘণ্টা খোলা" value="<?= e($hospital['opening_hours_bn'] ?? '') ?>">
                    </div>
                </div>

                <div class="hf-en-bn-row">
                    <div class="hf-field">
                        <label>Visiting Hours</label>
                        <input type="text" name="visiting_hours" placeholder="Example: 10 AM - 8 PM" value="<?= e($hospital['visiting_hours'] ?? '') ?>">
                    </div>

                    <div class="hf-field">
                        <label class="hf-label-bn">Visiting Hours Bangla</label>
                        <input type="text" name="visiting_hours_bn" placeholder="উদাহরণ: সকাল ১০টা - রাত ৮টা" value="<?= e($hospital['visiting_hours_bn'] ?? '') ?>">
                    </div>
                </div>

                <div class="hf-en-bn-row">
                    <div class="hf-field">
                        <label>Services</label>
                        <textarea name="services" placeholder="Write services manually, one per line"><?= e($hospital['services'] ?? '') ?></textarea>
                    </div>

                    <div class="hf-field">
                        <label class="hf-label-bn">Services Bangla</label>
                        <textarea name="services_bn" placeholder="বাংলা services লিখুন, প্রতি লাইনে একটি"><?= e($hospital['services_bn'] ?? '') ?></textarea>
                    </div>
                </div>

                <div class="hf-en-bn-row">
                    <div class="hf-field">
                        <label>Facilities</label>
                        <textarea name="facilities" placeholder="Write facilities manually, one per line"><?= e($hospital['facilities'] ?? '') ?></textarea>
                    </div>

                    <div class="hf-field">
                        <label class="hf-label-bn">Facilities Bangla</label>
                        <textarea name="facilities_bn" placeholder="বাংলা facilities লিখুন, প্রতি লাইনে একটি"><?= e($hospital['facilities_bn'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>

            <div class="hf-section">
                <div class="hf-section-title">
                    <h3>Media</h3>
                    <span>Only main image and cover image are available. Gallery upload is removed.</span>
                </div>

                <div class="hf-grid">
                    <div class="hf-field">
                        <label>Hospital Image</label>
                        <div class="hf-file-box">
                            <input type="file" name="image" accept="image/*">
                            <?php if (!empty($hospital['image'])): ?>
                                <span class="hf-current-file"><?= e($hospital['image']) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="hf-field">
                        <label>Cover Image</label>
                        <div class="hf-file-box">
                            <input type="file" name="cover_image" accept="image/*">
                            <?php if (!empty($hospital['cover_image'])): ?>
                                <span class="hf-current-file"><?= e($hospital['cover_image']) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="hf-field full">
                        <label>Video URL</label>
                        <input type="url" name="video_url" placeholder="https://youtube.com/..." value="<?= e($hospital['video_url'] ?? '') ?>">
                    </div>
                </div>
            </div>

            <div class="hf-section">
                <div class="hf-section-title">
                    <h3>Status</h3>
                    <span>Publishing and display status.</span>
                </div>

                <div class="hf-grid">
                    <div class="hf-field">
                        <label>Status</label>
                        <select name="status">
                            <?php foreach (['active', 'inactive', 'pending'] as $status): ?>
                                <option value="<?= e($status) ?>" <?= ($hospital['status'] ?? 'active') === $status ? 'selected' : '' ?>>
                                    <?= e(ucfirst($status)) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="hf-toggle-row">
                    <label class="hf-toggle-label">
                        <input type="checkbox" name="is_verified" <?= !empty($hospital['is_verified']) ? 'checked' : '' ?>>
                        <span>Verified</span>
                    </label>

                    <label class="hf-toggle-label">
                        <input type="checkbox" name="is_featured" <?= !empty($hospital['is_featured']) ? 'checked' : '' ?>>
                        <span>Featured</span>
                    </label>
                </div>
            </div>

            <div class="hf-section">
                <div class="hf-section-title">
                    <h3>SEO Information</h3>
                    <span>Search title, description and keywords in English and Bangla.</span>
                </div>

                <div class="hf-en-bn-row">
                    <div class="hf-field">
                        <label>SEO Title</label>
                        <input type="text" name="seo_title" placeholder="Hospital name - District" value="<?= e($hospital['seo_title'] ?? '') ?>">
                    </div>

                    <div class="hf-field">
                        <label class="hf-label-bn">SEO Title Bangla</label>
                        <input type="text" name="seo_title_bn" placeholder="বাংলা SEO title" value="<?= e($hospital['seo_title_bn'] ?? '') ?>">
                    </div>
                </div>

                <div class="hf-en-bn-row">
                    <div class="hf-field">
                        <label>SEO Description</label>
                        <textarea name="seo_description" placeholder="Write SEO description"><?= e($hospital['seo_description'] ?? '') ?></textarea>
                    </div>

                    <div class="hf-field">
                        <label class="hf-label-bn">SEO Description Bangla</label>
                        <textarea name="seo_description_bn" placeholder="বাংলা SEO description লিখুন"><?= e($hospital['seo_description_bn'] ?? '') ?></textarea>
                    </div>
                </div>

                <div class="hf-en-bn-row">
                    <div class="hf-field">
                        <label>Meta Keywords</label>
                        <input type="text" name="meta_keywords" placeholder="hospital name, district, service" value="<?= e($hospital['meta_keywords'] ?? '') ?>">
                    </div>

                    <div class="hf-field">
                        <label class="hf-label-bn">Meta Keywords Bangla</label>
                        <input type="text" name="meta_keywords_bn" placeholder="হাসপাতালের নাম, জেলা, সেবা" value="<?= e($hospital['meta_keywords_bn'] ?? '') ?>">
                    </div>
                </div>
            </div>

            <div class="hf-submit-bar">
                <p><?= $id ? 'You are editing an existing hospital profile.' : 'You are creating a new hospital profile.' ?></p>

                <div class="hf-submit-actions">
                    <a href="hospitals.php" class="hf-btn">Cancel</a>
                    <button type="submit" class="hf-btn hf-btn-primary">
                        <?= $id ? 'Save & Update Hospital' : 'Save Hospital' ?>
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
(function() {
    const hospitalTypeEnToBnMap = {
        'Private Hospital': 'বেসরকারি হাসপাতাল',
        'Government Hospital': 'সরকারি হাসপাতাল',
        'Medical College Hospital': 'মেডিকেল কলেজ হাসপাতাল',
        'Specialized Hospital': 'বিশেষায়িত হাসপাতাল',
        'General Hospital': 'জেনারেল হাসপাতাল',
        'Diagnostic Center': 'ডায়াগনস্টিক সেন্টার',
        'Clinic': 'ক্লিনিক',
        'Eye Hospital': 'চক্ষু হাসপাতাল',
        'Dental Hospital': 'ডেন্টাল হাসপাতাল',
        'Cardiac Hospital': 'কার্ডিয়াক হাসপাতাল',
        'Cancer Hospital': 'ক্যান্সার হাসপাতাল',
        'Mother and Child Hospital': 'মা ও শিশু হাসপাতাল',
        'Rehabilitation Center': 'পুনর্বাসন কেন্দ্র'
    };

    const hospitalTypeBnToEnMap = Object.keys(hospitalTypeEnToBnMap).reduce(function(result, enType) {
        result[hospitalTypeEnToBnMap[enType]] = enType;
        return result;
    }, {});

    const hospitalTypeSelect = document.getElementById('hospitalTypeSelect');
    const hospitalTypeBnSelect = document.getElementById('hospitalTypeBnSelect');

    function syncHospitalTypeFromEnglish() {
        if (!hospitalTypeSelect || !hospitalTypeBnSelect) {
            return;
        }

        const bnValue = hospitalTypeEnToBnMap[hospitalTypeSelect.value] || '';

        if (bnValue) {
            hospitalTypeBnSelect.value = bnValue;
        }
    }

    function syncHospitalTypeFromBangla() {
        if (!hospitalTypeSelect || !hospitalTypeBnSelect) {
            return;
        }

        const enValue = hospitalTypeBnToEnMap[hospitalTypeBnSelect.value] || '';

        if (enValue) {
            hospitalTypeSelect.value = enValue;
        }
    }

    if (hospitalTypeSelect && hospitalTypeBnSelect) {
        if (!hospitalTypeBnSelect.value) {
            syncHospitalTypeFromEnglish();
        }

        hospitalTypeSelect.addEventListener('change', syncHospitalTypeFromEnglish);
        hospitalTypeBnSelect.addEventListener('change', syncHospitalTypeFromBangla);
    }

    const editSlugBtn = document.getElementById('editSlugBtn');
    const saveSlugBtn = document.getElementById('saveSlugBtn');
    const hospitalSlugInput = document.getElementById('hospitalSlugInput');

    if (saveSlugBtn) {
        saveSlugBtn.style.display = 'none';
    }

    if (editSlugBtn && hospitalSlugInput) {
        editSlugBtn.style.display = 'inline-flex';

        editSlugBtn.addEventListener('click', function() {
            hospitalSlugInput.removeAttribute('readonly');
            hospitalSlugInput.focus();
            hospitalSlugInput.select();

            editSlugBtn.style.display = 'none';

            if (saveSlugBtn && !saveSlugBtn.disabled) {
                saveSlugBtn.style.display = 'inline-flex';
            }
        });
    }

    const divisionSelect = document.getElementById('hospital_division_id');
    const districtSelect = document.getElementById('hospital_district_id');
    const thanaSelect = document.getElementById('hospital_thana_id');

    const addressFields = [
        'hospital_area',
        'hospital_road_no',
        'hospital_house_no',
        'hospital_area_bn',
        'hospital_road_no_bn',
        'hospital_house_no_bn',
        'hospital_post_code'
    ];

    function cleanText(value) {
        return String(value || '').replace(/\s+/g, ' ').trim();
    }

    function joinAddress(items) {
        return items.map(cleanText).filter(Boolean).join(', ');
    }

    function valueById(id) {
        const el = document.getElementById(id);
        return el ? el.value : '';
    }

    function selectedData(select, key) {
        if (!select || !select.selectedOptions || !select.selectedOptions.length) {
            return '';
        }

        return select.selectedOptions[0].dataset[key] || '';
    }

    function optionHtml(row) {
        const id = String(row.id || '');
        const nameEn = String(row.name_en || '');
        const nameBn = String(row.name_bn || '');
        const title = nameBn ? nameEn + ' / ' + nameBn : nameEn;

        return '<option value="' + escapeHtml(id) + '" data-name-en="' + escapeHtml(nameEn) + '" data-name-bn="' + escapeHtml(nameBn) + '">' + escapeHtml(title) + '</option>';
    }

    function escapeHtml(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function resetSelect(select, placeholder, disabled) {
        if (!select) {
            return;
        }

        select.innerHTML = '<option value="">' + placeholder + '</option>';
        select.disabled = !!disabled;
    }

    function loadLocation(type, params, target, placeholder, selectedValue) {
        if (!target) {
            return Promise.resolve();
        }

        const url = new URL(window.location.href);
        url.searchParams.set('hf_location', type);

        Object.keys(params || {}).forEach(function(key) {
            url.searchParams.set(key, params[key]);
        });

        target.disabled = true;
        target.innerHTML = '<option value="">Loading...</option>';

        return fetch(url.toString(), {headers: {'Accept': 'application/json'}})
            .then(function(response) { return response.json(); })
            .then(function(rows) {
                target.innerHTML = '<option value="">' + placeholder + '</option>' + rows.map(optionHtml).join('');
                target.disabled = false;

                if (selectedValue) {
                    target.value = String(selectedValue);
                }

                updateAddressPreview();
            })
            .catch(function() {
                resetSelect(target, placeholder, false);
                updateAddressPreview();
            });
    }

    function updateAddressPreview() {
        const postCode = valueById('hospital_post_code');
        const thana = selectedData(thanaSelect, 'nameEn');
        const thanaBn = selectedData(thanaSelect, 'nameBn');

        const thanaWithPost = postCode ? (thana ? thana + '-' + postCode : postCode) : thana;
        const thanaWithPostBn = postCode ? (thanaBn ? thanaBn + '-' + postCode : postCode) : thanaBn;

        // Division controls the dependent District dropdown only.
        // Do not show it in either saved-address preview.
        const address = joinAddress([
            valueById('hospital_house_no'),
            valueById('hospital_road_no'),
            valueById('hospital_area'),
            thanaWithPost,
            selectedData(districtSelect, 'nameEn')
        ]);

        const addressBn = joinAddress([
            valueById('hospital_house_no_bn') || valueById('hospital_house_no'),
            valueById('hospital_road_no_bn') || valueById('hospital_road_no'),
            valueById('hospital_area_bn') || valueById('hospital_area'),
            thanaWithPostBn || thanaWithPost,
            selectedData(districtSelect, 'nameBn') || selectedData(districtSelect, 'nameEn')
        ]);

        const preview = document.getElementById('hospital_address_preview');
        const previewBn = document.getElementById('hospital_address_preview_bn');

        if (preview) {
            preview.textContent = address || 'Address preview will appear here.';
        }

        if (previewBn) {
            previewBn.textContent = addressBn || 'বাংলা ঠিকানার preview এখানে দেখাবে।';
        }
    }

    if (divisionSelect) {
        divisionSelect.addEventListener('change', function() {
            resetSelect(districtSelect, '— Select District —', true);
            resetSelect(thanaSelect, '— Select Thana —', true);

            if (divisionSelect.value) {
                loadLocation('districts', {division_id: divisionSelect.value}, districtSelect, '— Select District —', '');
            }

            updateAddressPreview();
        });
    }

    if (districtSelect) {
        districtSelect.addEventListener('change', function() {
            resetSelect(thanaSelect, '— Select Thana —', true);

            if (districtSelect.value) {
                loadLocation('thanas', {district_id: districtSelect.value}, thanaSelect, '— Select Thana —', '');
            }

            updateAddressPreview();
        });
    }

    if (thanaSelect) {
        thanaSelect.addEventListener('change', updateAddressPreview);
    }

    addressFields.forEach(function(id) {
        const field = document.getElementById(id);

        if (field) {
            field.addEventListener('input', updateAddressPreview);
            field.addEventListener('change', updateAddressPreview);
        }
    });

    updateAddressPreview();
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
