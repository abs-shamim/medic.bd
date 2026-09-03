<?php
/*
|--------------------------------------------------------------------------
| Doctor Profile Main Page
|--------------------------------------------------------------------------
| Loads doctor profile, sections, and JSON-LD schema.
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/includes/functions.php';

$slug = trim((string)($_GET['slug'] ?? ''));
$doctor = function_exists('get_doctor_by_slug') ? get_doctor_by_slug($slug) : null;

if (!$doctor || !is_array($doctor)) {
    http_response_code(404);

    $page_title = 'Doctor Not Found';
    $meta_description = 'The requested doctor profile could not be found.';

    include __DIR__ . '/includes/header.php';
    ?>

    <main class="doctor-profile-page">
        <div class="container" style="padding:60px 15px;">
            <div style="max-width:720px;margin:0 auto;background:#fff;border:1px solid #d0d7de;border-radius:14px;padding:30px;text-align:center;">
                <h1 style="margin:0 0 12px;color:#24292f;">Doctor Not Found</h1>
                <p style="margin:0 0 22px;color:#57606a;">The doctor profile you are looking for is not available.</p>

                <a href="<?= e(function_exists('site_url') ? site_url('doctors') : '/doctors') ?>" style="display:inline-flex;align-items:center;justify-content:center;min-height:42px;padding:0 18px;border-radius:6px;background:#0969da;color:#fff;text-decoration:none;font-weight:700;">
                    Back to Doctors
                </a>
            </div>
        </div>
    </main>

    <?php
    include __DIR__ . '/includes/footer.php';
    exit;
}

/*
|--------------------------------------------------------------------------
| Language
|--------------------------------------------------------------------------
*/

if (!function_exists('doctor_page_is_bn')) {
    function doctor_page_is_bn(): bool
    {
        if (defined('CURRENT_LANG') && CURRENT_LANG === 'bn') {
            return true;
        }

        $path = trim((string)parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/');

        return $path === 'bn' || strpos($path, 'bn/') === 0;
    }
}

$is_bn_page = doctor_page_is_bn();

/*
|--------------------------------------------------------------------------
| SEO, Social And Search Discovery Data
|--------------------------------------------------------------------------
| Database values always take priority. When a field is empty, the page
| generates a factual fallback from the doctor, specialty and district data.
|--------------------------------------------------------------------------
*/

if (!function_exists('doctor_profile_clean_text')) {
    function doctor_profile_clean_text($value): string
    {
        $value = strip_tags((string)$value);
        $value = html_entity_decode($value, ENT_QUOTES, 'UTF-8');
        $value = preg_replace('/\s+/u', ' ', $value);

        return trim((string)$value);
    }
}

if (!function_exists('doctor_profile_unique_values')) {
    function doctor_profile_unique_values(array $values): array
    {
        $unique = [];

        foreach ($values as $value) {
            $value = doctor_profile_clean_text($value);

            if ($value === '') {
                continue;
            }

            $key = function_exists('mb_strtolower')
                ? mb_strtolower($value, 'UTF-8')
                : strtolower($value);

            if (!isset($unique[$key])) {
                $unique[$key] = $value;
            }
        }

        return array_values($unique);
    }
}

/*
|--------------------------------------------------------------------------
| SEO Mode Helper
|--------------------------------------------------------------------------
| The database supports two modes for each language-specific SEO field:
| - auto: build a factual value from Name - Specialty - District.
| - manual: use the saved value from the doctors table.
|--------------------------------------------------------------------------
*/

if (!function_exists('doctor_profile_clean_seo_mode')) {
    function doctor_profile_clean_seo_mode($value): string
    {
        return strtolower(trim((string)$value)) === 'manual' ? 'manual' : 'auto';
    }
}

if (!function_exists('doctor_profile_seo_value_by_mode')) {
    function doctor_profile_seo_value_by_mode(string $mode, $manual_value, $auto_value): string
    {
        $manual_value = doctor_profile_clean_text($manual_value);
        $auto_value = doctor_profile_clean_text($auto_value);

        /*
         * A saved manual value is used only when it is non-empty. If an
         * admin switches to Manual but leaves the input empty, the automatic
         * fallback prevents an empty title, description or keyword tag.
         */
        if ($mode === 'manual' && $manual_value !== '') {
            return $manual_value;
        }

        return $auto_value;
    }
}

if (!function_exists('doctor_profile_get_name_pair_by_id')) {
    function doctor_profile_get_name_pair_by_id(string $table, int $id): array
    {
        global $pdo;

        $result = [
            'en' => '',
            'bn' => '',
        ];

        /*
         * Districts use name_en/name_bn while specialties commonly use
         * name/name_bn. Detect the available columns so both structures work.
         */
        if (
            $id <= 0 ||
            !isset($pdo) ||
            !($pdo instanceof PDO) ||
            !in_array($table, ['specialties', 'districts'], true)
        ) {
            return $result;
        }

        try {
            $column_stmt = $pdo->prepare("
                SELECT COLUMN_NAME
                FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = :table
                  AND COLUMN_NAME IN ('name', 'name_en', 'name_bn')
            ");
            $column_stmt->execute([':table' => $table]);

            $columns = array_map(
                static fn ($value): string => (string)$value,
                $column_stmt->fetchAll(PDO::FETCH_COLUMN) ?: []
            );

            if (empty($columns)) {
                return $result;
            }

            $select = ['id'];

            foreach (['name', 'name_en', 'name_bn'] as $column) {
                if (in_array($column, $columns, true)) {
                    $select[] = "`{$column}`";
                }
            }

            $stmt = $pdo->prepare(
                'SELECT ' . implode(', ', $select) .
                " FROM `{$table}` WHERE id = :id LIMIT 1"
            );
            $stmt->execute([':id' => $id]);

            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (is_array($row)) {
                $result['en'] = doctor_profile_clean_text(
                    $row['name_en'] ?? ($row['name'] ?? '')
                );
                $result['bn'] = doctor_profile_clean_text($row['name_bn'] ?? '');
            }
        } catch (Throwable $e) {
            /* A missing optional lookup table must not break the profile. */
        }

        return $result;
    }
}

/*
|--------------------------------------------------------------------------
| District Fallback From Chamber / Hospital
|--------------------------------------------------------------------------
| Some imported doctors have doctor_district_id = 0. Their location is kept
| in doctor_location_auto_chamber_id or in the linked hospital instead.
| This resolver keeps the automatic SEO format complete for those records.
|--------------------------------------------------------------------------
*/

if (!function_exists('doctor_profile_hospital_district_id')) {
    function doctor_profile_hospital_district_id(int $hospital_id): int
    {
        global $pdo;

        if ($hospital_id <= 0 || !isset($pdo) || !($pdo instanceof PDO)) {
            return 0;
        }

        try {
            $stmt = $pdo->prepare(
                'SELECT district_id FROM hospitals WHERE id = :id LIMIT 1'
            );
            $stmt->execute([':id' => $hospital_id]);

            return max(0, (int)$stmt->fetchColumn());
        } catch (Throwable $e) {
            return 0;
        }
    }
}

if (!function_exists('doctor_profile_chamber_district_id')) {
    function doctor_profile_chamber_district_id(int $chamber_id): int
    {
        global $pdo;

        if ($chamber_id <= 0 || !isset($pdo) || !($pdo instanceof PDO)) {
            return 0;
        }

        try {
            $stmt = $pdo->prepare("
                SELECT h.district_id
                FROM chambers c
                INNER JOIN hospitals h ON h.id = c.hospital_id
                WHERE c.id = :chamber_id
                  AND c.hospital_id > 0
                LIMIT 1
            ");
            $stmt->execute([':chamber_id' => $chamber_id]);

            return max(0, (int)$stmt->fetchColumn());
        } catch (Throwable $e) {
            return 0;
        }
    }
}

if (!function_exists('doctor_profile_first_chamber_district_id')) {
    function doctor_profile_first_chamber_district_id(int $doctor_id): int
    {
        global $pdo;

        if ($doctor_id <= 0 || !isset($pdo) || !($pdo instanceof PDO)) {
            return 0;
        }

        try {
            $stmt = $pdo->prepare("
                SELECT h.district_id
                FROM chambers c
                INNER JOIN hospitals h ON h.id = c.hospital_id
                WHERE c.doctor_id = :doctor_id
                  AND c.hospital_id > 0
                  AND (c.status = 'active' OR c.status = 'published' OR c.status IS NULL OR c.status = '')
                ORDER BY c.sort_order ASC, c.id ASC
                LIMIT 1
            ");
            $stmt->execute([':doctor_id' => $doctor_id]);

            return max(0, (int)$stmt->fetchColumn());
        } catch (Throwable $e) {
            /* Older chamber tables may not have status/sort_order. */
            try {
                $stmt = $pdo->prepare("
                    SELECT h.district_id
                    FROM chambers c
                    INNER JOIN hospitals h ON h.id = c.hospital_id
                    WHERE c.doctor_id = :doctor_id
                      AND c.hospital_id > 0
                    ORDER BY c.id ASC
                    LIMIT 1
                ");
                $stmt->execute([':doctor_id' => $doctor_id]);

                return max(0, (int)$stmt->fetchColumn());
            } catch (Throwable $ignored) {
                return 0;
            }
        }
    }
}

if (!function_exists('doctor_profile_resolve_district_id')) {
    function doctor_profile_resolve_district_id(array $doctor): int
    {
        /* 1. Explicit doctor location always has the highest priority. */
        $district_id = (int)($doctor['doctor_district_id'] ?? ($doctor['district_id'] ?? 0));

        if ($district_id > 0) {
            return $district_id;
        }

        /* 2. Use the exact auto-selected chamber when one is stored. */
        $district_id = doctor_profile_chamber_district_id(
            (int)($doctor['doctor_location_auto_chamber_id'] ?? 0)
        );

        if ($district_id > 0) {
            return $district_id;
        }

        /* 3. Use the primary hospital saved directly on the doctor record. */
        $district_id = doctor_profile_hospital_district_id(
            (int)($doctor['hospital_id'] ?? 0)
        );

        if ($district_id > 0) {
            return $district_id;
        }

        /* 4. Final fallback: first active chamber hospital for this doctor. */
        return doctor_profile_first_chamber_district_id((int)($doctor['id'] ?? 0));
    }
}

if (!function_exists('doctor_profile_to_absolute_media_url')) {
    function doctor_profile_to_absolute_media_url($image): string
    {
        $image = trim((string)$image);

        if ($image === '') {
            return '';
        }

        if (preg_match('/^https?:\/\//i', $image)) {
            return $image;
        }

        $image = str_replace('\\', '/', $image);

        while (strpos($image, '../') === 0) {
            $image = substr($image, 3);
        }

        $image = ltrim($image, '/');

        if ($image === '') {
            return '';
        }

        if (function_exists('site_url')) {
            return site_url($image);
        }

        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? '';

        return $host !== ''
            ? rtrim($scheme . '://' . $host, '/') . '/' . $image
            : $image;
    }
}

/*
|--------------------------------------------------------------------------
| Doctor Photo Card Storage Helper
|--------------------------------------------------------------------------
| The shared card is saved as a public JPG. The same stable URL is then used
| by Open Graph, X/Twitter and the page schema when the file exists.
|--------------------------------------------------------------------------
*/

if (!function_exists('doctor_profile_card_filename')) {
    function doctor_profile_card_filename(string $title): string
    {
        $title = doctor_profile_clean_text($title);

        /*
         * Public photo-card URL format:
         * dr-ahmed-hasan-cardiology-dhaka.jpg
         */
        if (function_exists('iconv')) {
            $ascii_title = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $title);

            if ($ascii_title !== false) {
                $title = $ascii_title;
            }
        }

        $title = strtolower($title);
        $title = preg_replace('/[^a-z0-9]+/', '-', $title);
        $title = trim((string)$title, '-');

        return ($title !== '' ? $title : 'doctor-profile-card') . '.jpg';
    }
}

$doctor_name_en = doctor_profile_clean_text($doctor['name'] ?? 'Doctor');
$doctor_name_bn = doctor_profile_clean_text($doctor['name_bn'] ?? '');

$specialty_name_en = doctor_profile_clean_text(
    $doctor['specialty_name']
    ?? $doctor['speciality_name']
    ?? ''
);

$specialty_name_bn = doctor_profile_clean_text(
    $doctor['specialty_name_bn']
    ?? $doctor['speciality_name_bn']
    ?? ''
);

$district_name_en = doctor_profile_clean_text(
    $doctor['district_name']
    ?? $doctor['district']
    ?? ''
);

$district_name_bn = doctor_profile_clean_text(
    $doctor['district_name_bn']
    ?? ''
);

$primary_hospital_en = doctor_profile_clean_text($doctor['primary_hospital'] ?? '');
$primary_hospital_bn = doctor_profile_clean_text($doctor['primary_hospital_bn'] ?? '');

/*
|--------------------------------------------------------------------------
| Reliable Specialty And District Labels
|--------------------------------------------------------------------------
| Use saved joined data when present, then refresh from the source tables.
|--------------------------------------------------------------------------
*/

$specialty_pair = doctor_profile_get_name_pair_by_id(
    'specialties',
    (int)($doctor['specialty_id'] ?? ($doctor['speciality_id'] ?? 0))
);

if ($specialty_pair['en'] !== '') {
    $specialty_name_en = $specialty_pair['en'];
}

if ($specialty_pair['bn'] !== '') {
    $specialty_name_bn = $specialty_pair['bn'];
}

$resolved_district_id = doctor_profile_resolve_district_id($doctor);

$district_pair = doctor_profile_get_name_pair_by_id(
    'districts',
    $resolved_district_id
);

if ($district_pair['en'] !== '') {
    $district_name_en = $district_pair['en'];
}

if ($district_pair['bn'] !== '') {
    $district_name_bn = $district_pair['bn'];
}

/*
 * Give all frontend sections the resolved labels too. Sections that reuse
 * the already-loaded $doctor array can now show the same district value.
 */
if ($district_name_en !== '') {
    $doctor['district_name'] = $district_name_en;
}

if ($district_name_bn !== '') {
    $doctor['district_name_bn'] = $district_name_bn;
}

if ($resolved_district_id > 0) {
    $doctor['resolved_district_id'] = $resolved_district_id;
}

/*
|--------------------------------------------------------------------------
| Language-Specific Values
|--------------------------------------------------------------------------
*/

if ($is_bn_page) {
    $doctor_name = $doctor_name_bn !== '' ? $doctor_name_bn : $doctor_name_en;
    $specialty_name_for_title = $specialty_name_bn !== '' ? $specialty_name_bn : $specialty_name_en;
    $district_name_for_title = $district_name_bn !== '' ? $district_name_bn : $district_name_en;
    $primary_hospital_for_seo = $primary_hospital_bn !== '' ? $primary_hospital_bn : $primary_hospital_en;
} else {
    $doctor_name = $doctor_name_en !== '' ? $doctor_name_en : $doctor_name_bn;
    $specialty_name_for_title = $specialty_name_en !== '' ? $specialty_name_en : $specialty_name_bn;
    $district_name_for_title = $district_name_en !== '' ? $district_name_en : $district_name_bn;
    $primary_hospital_for_seo = $primary_hospital_en !== '' ? $primary_hospital_en : $primary_hospital_bn;
}

/*
|--------------------------------------------------------------------------
| SEO Modes
|--------------------------------------------------------------------------
| Each language has separate Auto / Manual controls in the doctors table.
| Auto ignores the saved SEO field and creates factual values from the
| doctor name, specialty and district. Manual uses the saved database value.
|--------------------------------------------------------------------------
*/

$seo_title_mode = doctor_profile_clean_seo_mode($doctor['seo_title_mode'] ?? 'auto');
$seo_title_bn_mode = doctor_profile_clean_seo_mode($doctor['seo_title_bn_mode'] ?? 'auto');
$seo_description_mode = doctor_profile_clean_seo_mode($doctor['seo_description_mode'] ?? 'auto');
$seo_description_bn_mode = doctor_profile_clean_seo_mode($doctor['seo_description_bn_mode'] ?? 'auto');
$meta_keywords_mode = doctor_profile_clean_seo_mode($doctor['meta_keywords_mode'] ?? 'auto');
$meta_keywords_bn_mode = doctor_profile_clean_seo_mode($doctor['meta_keywords_bn_mode'] ?? 'auto');

$current_title_mode = $is_bn_page ? $seo_title_bn_mode : $seo_title_mode;
$current_description_mode = $is_bn_page ? $seo_description_bn_mode : $seo_description_mode;
$current_keywords_mode = $is_bn_page ? $meta_keywords_bn_mode : $meta_keywords_mode;

/*
|--------------------------------------------------------------------------
| Auto Meta Title
|--------------------------------------------------------------------------
| Required format:
| Name - Specialty - District
|--------------------------------------------------------------------------
*/

$generated_page_title = implode(' - ', doctor_profile_unique_values([
    $doctor_name,
    $specialty_name_for_title,
    $district_name_for_title,
]));

if ($generated_page_title === '') {
    $generated_page_title = $is_bn_page ? 'ডাক্তার প্রোফাইল' : 'Doctor Profile';
}

/*
|--------------------------------------------------------------------------
| Stable Photo Card Name And URL
|--------------------------------------------------------------------------
| The card uses the English Name - Specialty - District where available so
| both English and Bangla profile URLs share one consistent JPG card.
|--------------------------------------------------------------------------
*/

/*
 * Public photo-card filename must remain English, independently of whether
 * the visitor is on /bn/ or the English profile URL.
 */
$doctor_card_title = implode(' - ', doctor_profile_unique_values([
    $doctor_name_en,
    $specialty_name_en,
    $district_name_en,
]));

if ($doctor_card_title === '') {
    $doctor_card_title = implode(' - ', doctor_profile_unique_values([
        $doctor_name_en !== '' ? $doctor_name_en : $doctor_name,
        $specialty_name_en !== '' ? $specialty_name_en : $specialty_name_for_title,
        $district_name_en !== '' ? $district_name_en : $district_name_for_title,
    ]));
}

if ($doctor_card_title === '') {
    $doctor_card_title = $generated_page_title;
}

$doctor_card_file_name = doctor_profile_card_filename($doctor_card_title);

/*
|--------------------------------------------------------------------------
| Language-Based Photo Card Storage
|--------------------------------------------------------------------------
| English card:
|   /uploads/doctor-cards/dr-ahmed-hasan-cardiology-dhaka.jpg
|
| Bangla card:
|   /uploads/bn-doctor-cards/dr-ahmed-hasan-cardiology-dhaka.jpg
|--------------------------------------------------------------------------
*/

$doctor_card_relative_directory = $is_bn_page
    ? 'uploads/bn-doctor-cards/'
    : 'uploads/doctor-cards/';

$doctor_card_disk_directory = __DIR__ . '/' . (
    $is_bn_page
        ? 'uploads/bn-doctor-cards/'
        : 'uploads/doctor-cards/'
);

$doctor_card_relative_path = $doctor_card_relative_directory . rawurlencode($doctor_card_file_name);
$doctor_card_disk_path = $doctor_card_disk_directory . $doctor_card_file_name;

$doctor_card_og_image = (
    is_file($doctor_card_disk_path) &&
    filesize($doctor_card_disk_path) > 0
)
    ? doctor_profile_to_absolute_media_url($doctor_card_relative_path)
    : '';

/*
|--------------------------------------------------------------------------
| Social Image Cache Version
|--------------------------------------------------------------------------
| The filename remains stable for SEO. A changed file modification time adds
| only a query-string version to og:image, so social crawlers can fetch the
| newest card after an overwrite.
|--------------------------------------------------------------------------
*/

if ($doctor_card_og_image !== '') {
    $doctor_card_image_version = @filemtime($doctor_card_disk_path);

    if ($doctor_card_image_version !== false) {
        $doctor_card_og_image .= (str_contains($doctor_card_og_image, '?') ? '&' : '?')
            . 'v=' . (int)$doctor_card_image_version;
    }
}

/*
|--------------------------------------------------------------------------
| Meta Title
|--------------------------------------------------------------------------
| Auto:   Name - Specialty - District
| Manual: seo_title / seo_title_bn from the doctors table
|--------------------------------------------------------------------------
*/

$saved_page_title = $is_bn_page
    ? ($doctor['seo_title_bn'] ?? '')
    : ($doctor['seo_title'] ?? '');

$page_title = doctor_profile_seo_value_by_mode(
    $current_title_mode,
    $saved_page_title,
    $generated_page_title
);

/*
|--------------------------------------------------------------------------
| Auto Meta Description
|--------------------------------------------------------------------------
| Uses the same factual Name - Specialty - District source, then adds
| useful profile details for search visitors.
|--------------------------------------------------------------------------
*/

$description_subject = implode(', ', doctor_profile_unique_values([
    $doctor_name,
    $specialty_name_for_title,
    $district_name_for_title,
]));

if ($is_bn_page) {
    $generated_meta_description = $description_subject !== ''
        ? $description_subject . ' এর ডাক্তার প্রোফাইল, চেম্বার, ভিজিটিং সময়, অ্যাপয়েন্টমেন্ট, কনসালটেশন ফি ও বিস্তারিত তথ্য দেখুন।'
        : 'ডাক্তারের প্রোফাইল, চেম্বার, ভিজিটিং সময়, অ্যাপয়েন্টমেন্ট, কনসালটেশন ফি ও বিস্তারিত তথ্য দেখুন।';
} else {
    $generated_meta_description = $description_subject !== ''
        ? $description_subject . '. View doctor profile, chamber details, visiting hours, appointment information, consultation fee and schedule.'
        : 'View doctor profile, chamber details, visiting hours, appointment information, consultation fee and schedule.';
}

if ($primary_hospital_for_seo !== '') {
    $generated_meta_description .= $is_bn_page
        ? ' হাসপাতাল: ' . $primary_hospital_for_seo . '।'
        : ' Hospital: ' . $primary_hospital_for_seo . '.';
}

/*
|--------------------------------------------------------------------------
| Meta Description
|--------------------------------------------------------------------------
| Auto:   generated factual description
| Manual: seo_description / seo_description_bn from the doctors table
|--------------------------------------------------------------------------
*/

$saved_meta_description = $is_bn_page
    ? ($doctor['seo_description_bn'] ?? '')
    : ($doctor['seo_description'] ?? '');

$meta_description = doctor_profile_seo_value_by_mode(
    $current_description_mode,
    $saved_meta_description,
    $generated_meta_description
);

/*
|--------------------------------------------------------------------------
| Auto Meta Keywords
|--------------------------------------------------------------------------
| Kept for compatibility with systems that still read keywords. This tag is
| not treated as a Google ranking signal.
|--------------------------------------------------------------------------
*/

$keyword_candidates = [
    $doctor_name,
    $specialty_name_for_title,
    $district_name_for_title,
    $primary_hospital_for_seo,
];

if ($doctor_name !== '' && $specialty_name_for_title !== '') {
    $keyword_candidates[] = $doctor_name . ' ' . $specialty_name_for_title;
}

if ($doctor_name !== '' && $district_name_for_title !== '') {
    $keyword_candidates[] = $doctor_name . ' ' . $district_name_for_title;
}

if ($specialty_name_for_title !== '' && $district_name_for_title !== '') {
    $keyword_candidates[] = $is_bn_page
        ? $district_name_for_title . ' এর ' . $specialty_name_for_title . ' ডাক্তার'
        : $specialty_name_for_title . ' doctor in ' . $district_name_for_title;
}

if ($doctor_name !== '') {
    $keyword_candidates[] = $is_bn_page
        ? $doctor_name . ' অ্যাপয়েন্টমেন্ট'
        : $doctor_name . ' appointment';
}

$generated_meta_keywords = implode(', ', doctor_profile_unique_values($keyword_candidates));

/*
|--------------------------------------------------------------------------
| Meta Keywords
|--------------------------------------------------------------------------
| Auto:   generated keywords
| Manual: meta_keywords / meta_keywords_bn from the doctors table
|--------------------------------------------------------------------------
*/

$saved_meta_keywords = $is_bn_page
    ? ($doctor['meta_keywords_bn'] ?? '')
    : ($doctor['meta_keywords'] ?? '');

$meta_keywords = doctor_profile_seo_value_by_mode(
    $current_keywords_mode,
    $saved_meta_keywords,
    $generated_meta_keywords
);

/*
|--------------------------------------------------------------------------
| Open Graph And X/Twitter Image
|--------------------------------------------------------------------------
| Priority:
| 1. Doctor-specific Open Graph image.
| 2. Doctor profile image.
| 3. Site default Open Graph image, selected by the header.
|--------------------------------------------------------------------------
*/

$doctor_og_image = doctor_profile_to_absolute_media_url($doctor['og_image'] ?? '');
$doctor_profile_image = '';

foreach (['image', 'photo', 'profile_image'] as $image_field) {
    $candidate_image = doctor_profile_to_absolute_media_url($doctor[$image_field] ?? '');

    if ($candidate_image !== '') {
        $doctor_profile_image = $candidate_image;
        break;
    }
}

/*
 * Priority:
 * 1. Stored public doctor photo card.
 * 2. Manually selected doctor Open Graph image.
 * 3. Real doctor profile photo.
 * 4. Site default image inside header.php.
 */
if ($doctor_card_og_image !== '') {
    $og_image = $doctor_card_og_image;
} elseif ($doctor_og_image !== '') {
    $og_image = $doctor_og_image;
} elseif ($doctor_profile_image !== '') {
    $og_image = $doctor_profile_image;
}

$og_title = $page_title;
$og_description = $meta_description;
$og_type = 'profile';
$og_locale = $is_bn_page ? 'bn_BD' : 'en_US';
$twitter_card = 'summary_large_image';

$og_image_alt = $doctor_card_og_image !== ''
    ? $doctor_card_title
    : ($is_bn_page
        ? $doctor_name . ' এর প্রোফাইল ছবি'
        : $doctor_name . ' profile photo');

$og_image_width = $doctor_card_og_image !== '' ? 1200 : 0;
$og_image_height = $doctor_card_og_image !== '' ? 630 : 0;
$og_image_type = $doctor_card_og_image !== '' ? 'image/jpeg' : '';

$og_image = $og_image ?? '';

$robots_meta = 'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1';

/*
|--------------------------------------------------------------------------
| Section Loader
|--------------------------------------------------------------------------
*/

function doctor_profile_include_section(string $section_file): void
{
    $section_file = basename($section_file);
    $section_path = __DIR__ . '/doctor/' . $section_file;

    if (is_file($section_path)) {
        include $section_path;
    }
}

/*
|--------------------------------------------------------------------------
| Schema Helpers
|--------------------------------------------------------------------------
*/

if (!function_exists('doctor_schema_clean_value')) {
    function doctor_schema_clean_value($value): string
    {
        return trim(strip_tags(html_entity_decode((string)$value, ENT_QUOTES, 'UTF-8')));
    }
}

if (!function_exists('doctor_schema_pick_lang')) {
    function doctor_schema_pick_lang($en_value, $bn_value = ''): string
    {
        $en_value = doctor_schema_clean_value($en_value);
        $bn_value = doctor_schema_clean_value($bn_value);

        if (doctor_page_is_bn() && $bn_value !== '') {
            return $bn_value;
        }

        return $en_value !== '' ? $en_value : $bn_value;
    }
}

if (!function_exists('doctor_schema_slugify')) {
    function doctor_schema_slugify(string $text): string
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
}

if (!function_exists('doctor_schema_site_url')) {
    function doctor_schema_site_url(string $path = ''): string
    {
        $path = trim($path);

        if ($path !== '' && preg_match('/^https?:\/\//i', $path)) {
            return $path;
        }

        if (function_exists('front_url')) {
            return front_url($path);
        }

        if (function_exists('site_url')) {
            return site_url($path);
        }

        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? '';

        return rtrim($scheme . '://' . $host, '/') . '/' . ltrim($path, '/');
    }
}

if (!function_exists('doctor_schema_current_url')) {
    function doctor_schema_current_url(): string
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? '';
        $request_uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($request_uri, PHP_URL_PATH);

        if (!is_string($path) || $path === '') {
            $path = '/';
        }

        return $scheme . '://' . $host . $path;
    }
}

if (!function_exists('doctor_schema_media_url')) {
    function doctor_schema_media_url($image): string
    {
        $image = trim((string)$image);

        if ($image === '') {
            return '';
        }

        if (preg_match('/^https?:\/\//i', $image)) {
            return $image;
        }

        if (strpos($image, '../') === 0) {
            $image = substr($image, 3);
        }

        $image = ltrim($image, '/');

        return doctor_schema_site_url($image);
    }
}

if (!function_exists('doctor_schema_get_site_settings')) {
    function doctor_schema_get_site_settings(): array
    {
        global $pdo;

        static $settings = null;

        if ($settings !== null) {
            return $settings;
        }

        $settings = [];

        try {
            $stmt = $pdo->query("SELECT setting_key, setting_value FROM site_settings");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($rows as $row) {
                $settings[(string)$row['setting_key']] = (string)$row['setting_value'];
            }
        } catch (Throwable $e) {
            $settings = [];
        }

        return $settings;
    }
}

if (!function_exists('doctor_schema_setting_value')) {
    function doctor_schema_setting_value(string $key, string $default = ''): string
    {
        $settings = doctor_schema_get_site_settings();
        $value = trim((string)($settings[$key] ?? ''));

        return $value !== '' ? $value : $default;
    }
}

if (!function_exists('doctor_schema_default_doctor_image')) {
    function doctor_schema_default_doctor_image(array $doctor): string
    {
        $gender = strtolower(trim((string)($doctor['gender'] ?? '')));

        if (in_array($gender, ['male', 'm'], true)) {
            $image = doctor_schema_setting_value('default_doctor_male_image', 'assets/images/default-doctor-male.webp');

            if ($image !== '') {
                return doctor_schema_media_url($image);
            }
        }

        if (in_array($gender, ['female', 'f'], true)) {
            $image = doctor_schema_setting_value('default_doctor_female_image', 'assets/images/default-doctor-female.webp');

            if ($image !== '') {
                return doctor_schema_media_url($image);
            }
        }

        $image = doctor_schema_setting_value('default_doctor_image', 'assets/images/default-doctor.webp');

        return doctor_schema_media_url($image);
    }
}

if (!function_exists('doctor_schema_default_hospital_image')) {
    function doctor_schema_default_hospital_image(): string
    {
        $image = doctor_schema_setting_value('default_hospital_image', 'assets/images/default-hospital.webp');

        return doctor_schema_media_url($image);
    }
}

if (!function_exists('doctor_schema_pick_doctor_image')) {
    function doctor_schema_pick_doctor_image(array $doctor): string
    {
        $image = doctor_schema_media_url(
            $doctor['image']
            ?? $doctor['photo']
            ?? $doctor['profile_image']
            ?? ''
        );

        if ($image !== '') {
            return $image;
        }

        return doctor_schema_default_doctor_image($doctor);
    }
}

if (!function_exists('doctor_schema_pick_hospital_image')) {
    function doctor_schema_pick_hospital_image(array $chamber, string $fallback_doctor_image = ''): string
    {
        $image = doctor_schema_media_url(
            $chamber['hospital_image']
            ?? $chamber['hospital_cover_image']
            ?? $chamber['image']
            ?? $chamber['cover_image']
            ?? ''
        );

        if ($image !== '') {
            return $image;
        }

        $default_hospital_image = doctor_schema_default_hospital_image();

        if ($default_hospital_image !== '') {
            return $default_hospital_image;
        }

        return $fallback_doctor_image;
    }
}

if (!function_exists('doctor_schema_extract_postal_code')) {
    function doctor_schema_extract_postal_code(string $address): string
    {
        $address = doctor_schema_clean_value($address);

        if ($address === '') {
            return '';
        }

        if (preg_match('/(?:^|[\s,\-])(\d{4})(?:$|[\s,\.,\)])/', $address, $matches)) {
            return $matches[1];
        }

        return '';
    }
}

if (!function_exists('doctor_schema_remove_empty')) {
    function doctor_schema_remove_empty(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $value = doctor_schema_remove_empty($value);

                if ($value === []) {
                    unset($data[$key]);
                    continue;
                }

                $data[$key] = $value;
            }

            if ($value === '' || $value === null || $value === []) {
                unset($data[$key]);
            }
        }

        return $data;
    }
}

if (!function_exists('doctor_schema_table_exists')) {
    function doctor_schema_table_exists(string $table): bool
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
            return $cache[$table];
        } catch (Throwable $e) {
            $cache[$table] = false;
            return false;
        }
    }
}

if (!function_exists('doctor_schema_column_exists')) {
    function doctor_schema_column_exists(string $table, string $column): bool
    {
        global $pdo;

        static $cache = [];

        $table = trim($table);
        $column = trim($column);
        $key = $table . '.' . $column;

        if ($table === '' || $column === '') {
            return false;
        }

        if (array_key_exists($key, $cache)) {
            return $cache[$key];
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

            $cache[$key] = (int)$stmt->fetchColumn() > 0;
            return $cache[$key];
        } catch (Throwable $e) {
            $cache[$key] = false;
            return false;
        }
    }
}

if (!function_exists('doctor_schema_get_db_schema_specialty')) {
    function doctor_schema_get_db_schema_specialty(array $doctor): string
    {
        global $pdo;

        $direct = doctor_schema_clean_value($doctor['schema_specialty'] ?? '');

        if ($direct !== '') {
            return $direct;
        }

        if (
            !doctor_schema_table_exists('specialties') ||
            !doctor_schema_column_exists('specialties', 'schema_specialty')
        ) {
            return '';
        }

        $where = [];
        $params = [];

        $specialty_id = (int)($doctor['specialty_id'] ?? ($doctor['speciality_id'] ?? 0));
        $specialty_slug = doctor_schema_clean_value($doctor['specialty_slug'] ?? '');
        $specialty_name = doctor_schema_clean_value($doctor['specialty_name'] ?? ($doctor['speciality_name'] ?? ''));

        if ($specialty_id > 0 && doctor_schema_column_exists('specialties', 'id')) {
            $where[] = 'id = :id';
            $params[':id'] = $specialty_id;
        }

        if ($specialty_slug !== '' && doctor_schema_column_exists('specialties', 'slug')) {
            $where[] = 'slug = :slug';
            $params[':slug'] = $specialty_slug;
        }

        if ($specialty_name !== '' && doctor_schema_column_exists('specialties', 'name')) {
            $where[] = 'name = :name';
            $params[':name'] = $specialty_name;
        }

        if (empty($where)) {
            return '';
        }

        try {
            $stmt = $pdo->prepare("
                SELECT schema_specialty
                FROM specialties
                WHERE " . implode(' OR ', $where) . "
                LIMIT 1
            ");
            $stmt->execute($params);

            return doctor_schema_clean_value($stmt->fetchColumn());
        } catch (Throwable $e) {
            return '';
        }
    }
}

if (!function_exists('doctor_schema_medical_specialty_enum')) {
    function doctor_schema_medical_specialty_enum(string $specialty, string $db_schema_specialty = ''): string
    {
        $db_schema_specialty = trim($db_schema_specialty);

        if ($db_schema_specialty !== '') {
            if (preg_match('/^https?:\/\//i', $db_schema_specialty)) {
                return $db_schema_specialty;
            }

            return 'https://schema.org/' . trim($db_schema_specialty, '/');
        }

        $specialty = strtolower(trim($specialty));

        $map = [
            'anesthesia' => 'Anesthesia',
            'anaesthesia' => 'Anesthesia',
            'anesthesiologist' => 'Anesthesia',
            'anaesthesiologist' => 'Anesthesia',
            'pain' => 'Anesthesia',

            'cardiology' => 'Cardiovascular',
            'cardiologist' => 'Cardiovascular',
            'cardio' => 'Cardiovascular',
            'heart' => 'Cardiovascular',
            'cardiac' => 'Cardiovascular',

            'community health' => 'CommunityHealth',
            'public health' => 'PublicHealth',

            'dentistry' => 'Dentistry',
            'dentist' => 'Dentistry',
            'dental' => 'Dentistry',
            'orthodont' => 'Dentistry',
            'prosthodont' => 'Dentistry',
            'maxillofacial' => 'Dentistry',

            'dermatology' => 'Dermatology',
            'dermatologist' => 'Dermatology',
            'skin' => 'Dermatology',
            'venereology' => 'Dermatology',

            'diet' => 'DietNutrition',
            'nutrition' => 'DietNutrition',
            'nutritionist' => 'DietNutrition',
            'dietitian' => 'DietNutrition',

            'emergency' => 'Emergency',

            'endocrine' => 'Endocrine',
            'endocrinology' => 'Endocrine',
            'endocrinologist' => 'Endocrine',
            'diabetes' => 'Endocrine',
            'diabetologist' => 'Endocrine',
            'hormone' => 'Endocrine',

            'gastroenterology' => 'Gastroenterologic',
            'gastroenterologist' => 'Gastroenterologic',
            'gastro' => 'Gastroenterologic',
            'hepatology' => 'Gastroenterologic',
            'hepatologist' => 'Gastroenterologic',
            'liver' => 'Gastroenterologic',

            'genetic' => 'Genetic',
            'genetics' => 'Genetic',

            'geriatric' => 'Geriatric',
            'geriatrics' => 'Geriatric',

            'gynecology' => 'Gynecologic',
            'gynaecology' => 'Gynecologic',
            'gynecologist' => 'Gynecologic',
            'gynaecologist' => 'Gynecologic',
            'gynae' => 'Gynecologic',
            'infertility' => 'Gynecologic',
            'reproductive' => 'Gynecologic',
            'embryologist' => 'Gynecologic',
            'delivery' => 'Gynecologic',

            'hematology' => 'Hematologic',
            'haematology' => 'Hematologic',
            'hematologist' => 'Hematologic',
            'blood' => 'Hematologic',

            'infectious' => 'Infectious',
            'infection' => 'Infectious',

            'laboratory' => 'LaboratoryScience',
            'pathology' => 'LaboratoryScience',
            'pathologist' => 'LaboratoryScience',
            'lab' => 'LaboratoryScience',

            'midwifery' => 'Midwifery',
            'midwife' => 'Midwifery',

            'neurology' => 'Neurologic',
            'neurologist' => 'Neurologic',
            'neuro' => 'Neurologic',
            'brain' => 'Neurologic',

            'nursing' => 'Nursing',

            'obstetric' => 'Obstetric',
            'obstetrics' => 'Obstetric',

            'oncology' => 'Oncologic',
            'oncologist' => 'Oncologic',
            'cancer' => 'Oncologic',

            'optometry' => 'Optometric',
            'optometrist' => 'Optometric',
            'ophthalmology' => 'Optometric',
            'ophthalmologist' => 'Optometric',
            'eye' => 'Optometric',
            'retina' => 'Optometric',
            'cornea' => 'Optometric',
            'oculoplastic' => 'Optometric',
            'vitreoretinal' => 'Optometric',

            'otolaryngology' => 'Otolaryngologic',
            'otolaryngologist' => 'Otolaryngologic',
            'ent' => 'Otolaryngologic',
            'ear' => 'Otolaryngologic',
            'nose' => 'Otolaryngologic',
            'throat' => 'Otolaryngologic',

            'pediatric' => 'Pediatric',
            'paediatric' => 'Pediatric',
            'pediatrics' => 'Pediatric',
            'paediatrics' => 'Pediatric',
            'pediatrician' => 'Pediatric',
            'paediatrician' => 'Pediatric',
            'child' => 'Pediatric',

            'pharmacy' => 'PharmacySpecialty',
            'pharmacist' => 'PharmacySpecialty',

            'physiotherapy' => 'Physiotherapy',
            'physiotherapist' => 'Physiotherapy',
            'physical medicine' => 'Physiotherapy',
            'rehabilitation' => 'Physiotherapy',
            'occupational therapist' => 'Physiotherapy',

            'plastic surgery' => 'PlasticSurgery',
            'plastic surgeon' => 'PlasticSurgery',
            'aesthetic' => 'PlasticSurgery',
            'cosmetic' => 'PlasticSurgery',

            'podiatry' => 'Podiatric',
            'podiatrist' => 'Podiatric',

            'psychiatry' => 'Psychiatric',
            'psychiatrist' => 'Psychiatric',
            'psychologist' => 'Psychiatric',
            'mental' => 'Psychiatric',

            'pulmonology' => 'Pulmonary',
            'pulmonologist' => 'Pulmonary',
            'chest' => 'Pulmonary',
            'respiratory' => 'Pulmonary',
            'lung' => 'Pulmonary',

            'radiology' => 'Radiography',
            'radiologist' => 'Radiography',
            'radiation' => 'Radiography',
            'imaging' => 'Radiography',

            'nephrology' => 'Renal',
            'nephrologist' => 'Renal',
            'kidney' => 'Renal',
            'renal' => 'Renal',

            'orthopedic' => 'Musculoskeletal',
            'orthopedics' => 'Musculoskeletal',
            'orthopaedic' => 'Musculoskeletal',
            'orthopaedics' => 'Musculoskeletal',
            'bone' => 'Musculoskeletal',
            'spine' => 'Musculoskeletal',
            'rheumatology' => 'Musculoskeletal',
            'rheumatologist' => 'Musculoskeletal',

            'surgery' => 'Surgical',
            'surgeon' => 'Surgical',
            'urology' => 'Surgical',
            'urologist' => 'Surgical',
            'laparoscopic' => 'Surgical',
            'colorectal' => 'Surgical',
            'vascular' => 'Surgical',
            'breast' => 'Surgical',

            'primary care' => 'PrimaryCare',
            'general medicine' => 'PrimaryCare',
            'medicine' => 'PrimaryCare',
            'general physician' => 'PrimaryCare',
            'general practitioner' => 'PrimaryCare',
            'family medicine' => 'PrimaryCare',
            'family physician' => 'PrimaryCare',
            'homeopathic' => 'PrimaryCare',
            'ayurvedic' => 'PrimaryCare',
            'unani' => 'PrimaryCare',
            'alternative' => 'PrimaryCare',
        ];

        foreach ($map as $keyword => $schema_value) {
            if (strpos($specialty, $keyword) !== false) {
                return 'https://schema.org/' . $schema_value;
            }
        }

        return 'https://schema.org/PrimaryCare';
    }
}

if (!function_exists('doctor_schema_get_chambers')) {
    function doctor_schema_get_chambers(array $doctor): array
    {
        global $pdo;

        $doctor_id = (int)($doctor['id'] ?? 0);

        if ($doctor_id <= 0) {
            return [];
        }

        if (
            !doctor_schema_table_exists('chambers') ||
            !doctor_schema_column_exists('chambers', 'doctor_id')
        ) {
            return [];
        }

        $select = [
            'c.id',
            'c.doctor_id',
            doctor_schema_column_exists('chambers', 'hospital_id') ? 'c.hospital_id' : '0 AS hospital_id',
            doctor_schema_column_exists('chambers', 'address') ? 'c.address AS chamber_address' : "'' AS chamber_address",
            doctor_schema_column_exists('chambers', 'address_bn') ? 'c.address_bn AS chamber_address_bn' : "'' AS chamber_address_bn",
            doctor_schema_column_exists('chambers', 'visiting_hours') ? 'c.visiting_hours AS chamber_visiting_hours' : "'' AS chamber_visiting_hours",
            doctor_schema_column_exists('chambers', 'visiting_hours_bn') ? 'c.visiting_hours_bn AS chamber_visiting_hours_bn' : "'' AS chamber_visiting_hours_bn",
            doctor_schema_column_exists('chambers', 'schedule') ? 'c.schedule AS chamber_schedule' : "'' AS chamber_schedule",
            doctor_schema_column_exists('chambers', 'schedule_bn') ? 'c.schedule_bn AS chamber_schedule_bn' : "'' AS chamber_schedule_bn",
            doctor_schema_column_exists('chambers', 'consultation_fee') ? 'c.consultation_fee AS chamber_consultation_fee' : "'' AS chamber_consultation_fee",
            doctor_schema_column_exists('chambers', 'appointment_phone') ? 'c.appointment_phone AS chamber_appointment_phone' : "'' AS chamber_appointment_phone",
        ];

        $join = '';
        $hospital_select = [];

        if (
            doctor_schema_table_exists('hospitals') &&
            doctor_schema_column_exists('chambers', 'hospital_id')
        ) {
            $join = ' LEFT JOIN hospitals h ON h.id = c.hospital_id ';

            $hospital_select[] = doctor_schema_column_exists('hospitals', 'name') ? 'h.name AS hospital_name' : "'' AS hospital_name";
            $hospital_select[] = doctor_schema_column_exists('hospitals', 'name_bn') ? 'h.name_bn AS hospital_name_bn' : "'' AS hospital_name_bn";
            $hospital_select[] = doctor_schema_column_exists('hospitals', 'slug') ? 'h.slug AS hospital_slug' : "'' AS hospital_slug";
            $hospital_select[] = doctor_schema_column_exists('hospitals', 'phone') ? 'h.phone AS hospital_phone' : "'' AS hospital_phone";
            $hospital_select[] = doctor_schema_column_exists('hospitals', 'address') ? 'h.address AS hospital_address' : "'' AS hospital_address";
            $hospital_select[] = doctor_schema_column_exists('hospitals', 'address_bn') ? 'h.address_bn AS hospital_address_bn' : "'' AS hospital_address_bn";
            $hospital_select[] = doctor_schema_column_exists('hospitals', 'post_code') ? 'h.post_code AS hospital_post_code' : "'' AS hospital_post_code";
            $hospital_select[] = doctor_schema_column_exists('hospitals', 'image') ? 'h.image AS hospital_image' : "'' AS hospital_image";
            $hospital_select[] = doctor_schema_column_exists('hospitals', 'cover_image') ? 'h.cover_image AS hospital_cover_image' : "'' AS hospital_cover_image";
            $hospital_select[] = doctor_schema_column_exists('hospitals', 'visiting_hours') ? 'h.visiting_hours AS hospital_visiting_hours' : "'' AS hospital_visiting_hours";
            $hospital_select[] = doctor_schema_column_exists('hospitals', 'visiting_hours_bn') ? 'h.visiting_hours_bn AS hospital_visiting_hours_bn' : "'' AS hospital_visiting_hours_bn";
            $hospital_select[] = doctor_schema_column_exists('hospitals', 'opening_hours') ? 'h.opening_hours AS hospital_opening_hours' : "'' AS hospital_opening_hours";
            $hospital_select[] = doctor_schema_column_exists('hospitals', 'opening_hours_bn') ? 'h.opening_hours_bn AS hospital_opening_hours_bn' : "'' AS hospital_opening_hours_bn";
            $hospital_select[] = doctor_schema_column_exists('hospitals', 'district_id') ? 'h.district_id AS hospital_district_id' : '0 AS hospital_district_id';
            $hospital_select[] = doctor_schema_column_exists('hospitals', 'thana_id') ? 'h.thana_id AS hospital_thana_id' : '0 AS hospital_thana_id';
        }

        $select = array_merge($select, $hospital_select);

        $where = ['c.doctor_id = :doctor_id'];

        if (doctor_schema_column_exists('chambers', 'status')) {
            $where[] = "(c.status = 'active' OR c.status = 'published' OR c.status IS NULL OR c.status = '')";
        }

        $order_by = 'c.id ASC';

        if (doctor_schema_column_exists('chambers', 'sort_order')) {
            $order_by = 'c.sort_order ASC, c.id ASC';
        }

        try {
            $stmt = $pdo->prepare("
                SELECT " . implode(', ', $select) . "
                FROM chambers c
                {$join}
                WHERE " . implode(' AND ', $where) . "
                ORDER BY {$order_by}
            ");

            $stmt->execute([':doctor_id' => $doctor_id]);

            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return is_array($rows) ? $rows : [];
        } catch (Throwable $e) {
            return [];
        }
    }
}

/*
|--------------------------------------------------------------------------
| Schema Output
|--------------------------------------------------------------------------
*/

if (!function_exists('doctor_schema_output')) {
    function doctor_schema_output(array $doctor, string $page_title, string $meta_description): void
    {
        global $district_name_for_title, $specialty_name_for_title, $og_image, $og_image_alt, $meta_keywords;

        $current_url = doctor_schema_current_url();
        $is_bn = doctor_page_is_bn();
        $language = $is_bn ? 'bn' : 'en';

        $doctor_name = doctor_schema_pick_lang($doctor['name'] ?? 'Doctor', $doctor['name_bn'] ?? '');
        $degree = doctor_schema_pick_lang($doctor['degree'] ?? '', $doctor['degree_bn'] ?? '');
        $designation = doctor_schema_pick_lang($doctor['designation'] ?? '', $doctor['designation_bn'] ?? '');
        $primary_hospital = doctor_schema_pick_lang($doctor['primary_hospital'] ?? '', $doctor['primary_hospital_bn'] ?? '');
        $bio = doctor_schema_pick_lang($doctor['bio'] ?? '', $doctor['bio_bn'] ?? '');
        $training = doctor_schema_pick_lang($doctor['training'] ?? '', $doctor['training_bn'] ?? '');
        $fellowship = doctor_schema_pick_lang($doctor['fellowship'] ?? '', $doctor['fellowship_bn'] ?? '');
        $expertise = doctor_schema_pick_lang($doctor['expertise'] ?? '', $doctor['expertise_bn'] ?? '');

        $specialty_name = doctor_schema_pick_lang(
            $doctor['specialty_name'] ?? ($doctor['speciality_name'] ?? ''),
            $doctor['specialty_name_bn'] ?? ''
        );

        if ($specialty_name === '') {
            $specialty_name = doctor_schema_clean_value($specialty_name_for_title ?? '');
        }

        $db_schema_specialty = doctor_schema_get_db_schema_specialty($doctor);
        $medical_specialty_enum = doctor_schema_medical_specialty_enum($specialty_name, $db_schema_specialty);

        $phone = doctor_schema_clean_value($doctor['phone'] ?? '');
        $email = doctor_schema_clean_value($doctor['email'] ?? '');
        $bmdc_number = doctor_schema_clean_value($doctor['bmdc_number'] ?? '');
        $experience_years = doctor_schema_clean_value($doctor['experience_years'] ?? '');
        $consultation_fee = doctor_schema_clean_value($doctor['consultation_fee'] ?? '');
        $address = doctor_schema_clean_value($doctor['address'] ?? '');
        $district_name = doctor_schema_clean_value($district_name_for_title ?? '');

        if ($district_name === '') {
            $district_name = doctor_schema_pick_lang(
                $doctor['district_name'] ?? ($doctor['district'] ?? ''),
                $doctor['district_name_bn'] ?? ''
            );
        }

        $image = doctor_schema_pick_doctor_image($doctor);
        $postal_code = '';

        $chambers = doctor_schema_get_chambers($doctor);

        /*
        |--------------------------------------------------------------------------
        | Breadcrumb Data
        |--------------------------------------------------------------------------
        */

        $breadcrumb_district_name = $district_name;
        $breadcrumb_thana_name = '';
        $breadcrumb_specialty_name = $specialty_name;
        $breadcrumb_district_slug = doctor_schema_slugify($district_name);
        $breadcrumb_thana_slug = '';
        $breadcrumb_specialty_slug = doctor_schema_slugify($specialty_name);

        if (
            function_exists('doctor_breadcrumb_location_row_by_id') &&
            function_exists('doctor_breadcrumb_location_label') &&
            function_exists('doctor_breadcrumb_location_slug') &&
            function_exists('doctor_breadcrumb_first_chamber_hospital_location')
        ) {
            $breadcrumb_district_row = [];
            $breadcrumb_thana_row = [];

            $doctor_district_id = (int)($doctor['doctor_district_id'] ?? 0);
            $doctor_thana_id = (int)($doctor['doctor_thana_id'] ?? 0);

            if ($doctor_district_id > 0) {
                $breadcrumb_district_row = doctor_breadcrumb_location_row_by_id('districts', $doctor_district_id);
            }

            if ($doctor_thana_id > 0) {
                $breadcrumb_thana_row = doctor_breadcrumb_location_row_by_id('thanas', $doctor_thana_id);
            }

            $found_district_name = doctor_breadcrumb_location_label($breadcrumb_district_row);
            $found_thana_name = doctor_breadcrumb_location_label($breadcrumb_thana_row);

            if ($found_district_name === '') {
                $hospital_location = doctor_breadcrumb_first_chamber_hospital_location((int)($doctor['id'] ?? 0));

                $hospital_district_id = (int)($hospital_location['district_id'] ?? 0);
                $hospital_thana_id = (int)($hospital_location['thana_id'] ?? 0);

                if ($hospital_district_id > 0) {
                    $breadcrumb_district_row = doctor_breadcrumb_location_row_by_id('districts', $hospital_district_id);
                    $found_district_name = doctor_breadcrumb_location_label($breadcrumb_district_row);
                }

                if ($hospital_thana_id > 0) {
                    $breadcrumb_thana_row = doctor_breadcrumb_location_row_by_id('thanas', $hospital_thana_id);
                    $found_thana_name = doctor_breadcrumb_location_label($breadcrumb_thana_row);
                }
            }

            if ($found_district_name !== '') {
                $breadcrumb_district_name = $found_district_name;
                $breadcrumb_district_slug = doctor_breadcrumb_location_slug($breadcrumb_district_row, $found_district_name);
            }

            if ($found_thana_name !== '') {
                $breadcrumb_thana_name = $found_thana_name;
                $breadcrumb_thana_slug = doctor_breadcrumb_location_slug($breadcrumb_thana_row, $found_thana_name);
            }
        }

        if (
            function_exists('doctor_breadcrumb_specialty_row_by_doctor') &&
            function_exists('doctor_breadcrumb_specialty_label') &&
            function_exists('doctor_breadcrumb_specialty_slug_from_row') &&
            function_exists('doctor_breadcrumb_specialty_slug_from_doctor')
        ) {
            $breadcrumb_specialty_row = doctor_breadcrumb_specialty_row_by_doctor($doctor);

            $found_specialty_name = doctor_breadcrumb_specialty_label($breadcrumb_specialty_row, $specialty_name);
            $found_specialty_slug = doctor_breadcrumb_specialty_slug_from_row(
                $breadcrumb_specialty_row,
                doctor_breadcrumb_specialty_slug_from_doctor($doctor)
            );

            if ($found_specialty_name !== '') {
                $breadcrumb_specialty_name = $found_specialty_name;
            }

            if ($found_specialty_slug !== '') {
                $breadcrumb_specialty_slug = $found_specialty_slug;
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Clinic Schemas
        |--------------------------------------------------------------------------
        */

        $clinic_references = [];
        $clinic_schema_items = [];

        foreach ($chambers as $index => $chamber) {
            if (!is_array($chamber)) {
                continue;
            }

            $clinic_name = doctor_schema_pick_lang(
                $chamber['hospital_name'] ?? '',
                $chamber['hospital_name_bn'] ?? ''
            );

            if ($clinic_name === '') {
                $clinic_name = $is_bn ? 'মেডিকেল চেম্বার' : 'Medical Chamber';
            }

            $clinic_address = doctor_schema_pick_lang(
                $chamber['hospital_address'] ?? ($chamber['chamber_address'] ?? ''),
                $chamber['hospital_address_bn'] ?? ($chamber['chamber_address_bn'] ?? '')
            );

            $clinic_phone = doctor_schema_clean_value(
                $chamber['chamber_appointment_phone']
                ?? $chamber['hospital_phone']
                ?? $phone
            );

            $clinic_hours = doctor_schema_pick_lang(
                $chamber['chamber_visiting_hours'] ?? ($chamber['chamber_schedule'] ?? ($chamber['hospital_visiting_hours'] ?? ($chamber['hospital_opening_hours'] ?? ''))),
                $chamber['chamber_visiting_hours_bn'] ?? ($chamber['chamber_schedule_bn'] ?? ($chamber['hospital_visiting_hours_bn'] ?? ($chamber['hospital_opening_hours_bn'] ?? '')))
            );

            $clinic_fee = doctor_schema_clean_value($chamber['chamber_consultation_fee'] ?? '');

            $clinic_price_range = $clinic_fee !== ''
                ? $clinic_fee
                : ($consultation_fee !== '' ? $consultation_fee : ($is_bn ? 'চেম্বার অনুযায়ী ভিন্ন হতে পারে' : 'Varies by chamber'));

            $clinic_postal_code = doctor_schema_clean_value($chamber['hospital_post_code'] ?? '');

            if ($clinic_postal_code === '') {
                $clinic_postal_code = doctor_schema_extract_postal_code($clinic_address);
            }

            $clinic_image = doctor_schema_pick_hospital_image($chamber, $image);

            if ($phone === '' && $clinic_phone !== '') {
                $phone = $clinic_phone;
            }

            if ($address === '' && $clinic_address !== '') {
                $address = $clinic_address;
            }

            if ($postal_code === '' && $clinic_postal_code !== '') {
                $postal_code = $clinic_postal_code;
            }

            if ($consultation_fee === '' && $clinic_fee !== '') {
                $consultation_fee = $clinic_fee;
            }

            $clinic_id = $current_url . '#clinic-' . ($index + 1);

            $clinic_schema = [
                '@context' => 'https://schema.org',
                '@type' => 'MedicalClinic',
                '@id' => $clinic_id,
                'name' => $clinic_name,
                'url' => $current_url,
                'image' => $clinic_image,
                'telephone' => $clinic_phone,
                'priceRange' => $clinic_price_range,
                'medicalSpecialty' => $medical_specialty_enum,
                'description' => $specialty_name !== '' ? $specialty_name : null,
                'address' => [
                    '@type' => 'PostalAddress',
                    'streetAddress' => $clinic_address,
                    'addressLocality' => $breadcrumb_district_name,
                    'addressCountry' => 'BD',
                    'postalCode' => $clinic_postal_code,
                ],
                'openingHours' => $clinic_hours,
            ];

            $clinic_schema_items[] = doctor_schema_remove_empty($clinic_schema);

            $clinic_references[] = [
                '@type' => 'MedicalClinic',
                '@id' => $clinic_id,
                'name' => $clinic_name,
            ];
        }

        if ($postal_code === '') {
            $postal_code = doctor_schema_extract_postal_code($address);
        }

        $price_range = $consultation_fee !== ''
            ? $consultation_fee
            : ($is_bn ? 'চেম্বার অনুযায়ী ভিন্ন হতে পারে' : 'Varies by chamber');

        /*
        |--------------------------------------------------------------------------
        | Breadcrumb Schema
        |--------------------------------------------------------------------------
        */

        $breadcrumb_schema = [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            '@id' => $current_url . '#breadcrumb',
            'itemListElement' => [],
        ];

        $position = 1;

        $breadcrumb_schema['itemListElement'][] = [
            '@type' => 'ListItem',
            'position' => $position++,
            'name' => $is_bn ? 'হোম' : 'Home',
            'item' => doctor_schema_site_url('/'),
        ];

        $breadcrumb_schema['itemListElement'][] = [
            '@type' => 'ListItem',
            'position' => $position++,
            'name' => $is_bn ? 'ডাক্তার' : 'Doctors',
            'item' => doctor_schema_site_url('doctors'),
        ];

        if ($breadcrumb_district_name !== '' && $breadcrumb_district_slug !== '') {
            $breadcrumb_schema['itemListElement'][] = [
                '@type' => 'ListItem',
                'position' => $position++,
                'name' => $breadcrumb_district_name,
                'item' => doctor_schema_site_url('doctors/' . $breadcrumb_district_slug),
            ];
        }

        if ($breadcrumb_thana_name !== '' && $breadcrumb_district_slug !== '' && $breadcrumb_thana_slug !== '') {
            $breadcrumb_schema['itemListElement'][] = [
                '@type' => 'ListItem',
                'position' => $position++,
                'name' => $breadcrumb_thana_name,
                'item' => doctor_schema_site_url('doctors/' . $breadcrumb_district_slug . '/' . $breadcrumb_thana_slug),
            ];
        }

        if ($breadcrumb_specialty_name !== '' && $breadcrumb_specialty_slug !== '') {
            $specialty_path = 'doctors';

            if ($breadcrumb_district_slug !== '') {
                $specialty_path .= '/' . $breadcrumb_district_slug;
            }

            if ($breadcrumb_thana_slug !== '') {
                $specialty_path .= '/' . $breadcrumb_thana_slug;
            }

            $specialty_path .= '/' . $breadcrumb_specialty_slug;

            $breadcrumb_schema['itemListElement'][] = [
                '@type' => 'ListItem',
                'position' => $position++,
                'name' => $breadcrumb_specialty_name,
                'item' => doctor_schema_site_url($specialty_path),
            ];
        }

        $breadcrumb_schema['itemListElement'][] = [
            '@type' => 'ListItem',
            'position' => $position,
            'name' => $doctor_name,
            'item' => $current_url,
        ];

        /*
        |--------------------------------------------------------------------------
        | Physician Schema
        |--------------------------------------------------------------------------
        */

        $description = $bio !== '' ? $bio : $meta_description;

        if ($experience_years !== '') {
            $description = trim($description . ' ' . ($is_bn ? 'অভিজ্ঞতা: ' : 'Experience: ') . $experience_years);
        }

        $knows_about = [];

        foreach ([$specialty_name, $degree, $designation, $fellowship, $training, $expertise] as $item) {
            if ($item !== '') {
                $knows_about[] = $item;
            }
        }

        $physician_schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Physician',
            '@id' => $current_url . '#doctor',
            'name' => $doctor_name,
            'url' => $current_url,
            'mainEntityOfPage' => [
                '@id' => $current_url . '#webpage',
            ],
            'description' => $description,
            'image' => $image,
            'telephone' => $phone,
            'email' => $email,
            'priceRange' => $price_range,
            'medicalSpecialty' => $medical_specialty_enum,
            'knowsAbout' => $knows_about,
            'address' => [
                '@type' => 'PostalAddress',
                'streetAddress' => $address,
                'addressLocality' => $breadcrumb_district_name,
                'addressCountry' => 'BD',
                'postalCode' => $postal_code,
            ],
            'identifier' => $bmdc_number !== '' ? [
                '@type' => 'PropertyValue',
                'propertyID' => 'BMDC',
                'value' => $bmdc_number,
            ] : null,
            'hasCredential' => $degree !== '' ? [
                '@type' => 'EducationalOccupationalCredential',
                'name' => $degree,
                'credentialCategory' => $is_bn ? 'মেডিকেল ডিগ্রি' : 'Medical Degree',
            ] : null,
            'memberOf' => $fellowship !== '' ? [
                '@type' => 'Organization',
                'name' => $fellowship,
            ] : null,
            'areaServed' => $breadcrumb_district_name !== '' ? [
                '@type' => 'City',
                'name' => $breadcrumb_district_name,
            ] : null,
        ];

        $rating_value = 0;
        $review_count = 0;

        if (isset($doctor['rating']) && is_numeric($doctor['rating'])) {
            $rating_value = (float)$doctor['rating'];
        }

        if (isset($doctor['reviews_count']) && is_numeric($doctor['reviews_count'])) {
            $review_count = (int)$doctor['reviews_count'];
        }

        if ($rating_value > 0 && $review_count > 0) {
            $physician_schema['aggregateRating'] = [
                '@type' => 'AggregateRating',
                'ratingValue' => number_format(max(0, min(5, $rating_value)), 1, '.', ''),
                'reviewCount' => max(0, $review_count),
                'bestRating' => '5',
                'worstRating' => '1',
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | WebPage / WebSite Schema
        |--------------------------------------------------------------------------
        */

        $webpage_schema = [
            '@context' => 'https://schema.org',
            '@type' => 'WebPage',
            '@id' => $current_url . '#webpage',
            'url' => $current_url,
            'name' => $page_title,
            'description' => $meta_description,
            'keywords' => doctor_schema_clean_value($meta_keywords ?? ''),
            'inLanguage' => $language,
            'primaryImageOfPage' => $og_image !== '' ? [
                '@type' => 'ImageObject',
                'contentUrl' => $og_image,
                'caption' => doctor_schema_clean_value($og_image_alt ?? ''),
            ] : null,
            'breadcrumb' => [
                '@id' => $current_url . '#breadcrumb',
            ],
            'mainEntity' => [
                '@id' => $current_url . '#doctor',
            ],
            'isPartOf' => [
                '@id' => doctor_schema_site_url('/') . '#website',
            ],
        ];

        $website_schema = [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            '@id' => doctor_schema_site_url('/') . '#website',
            'name' => doctor_schema_setting_value('site_name', 'MedicBD'),
            'url' => doctor_schema_site_url('/'),
            'inLanguage' => $language,
        ];

        /*
        |--------------------------------------------------------------------------
        | Output Order
        |--------------------------------------------------------------------------
        | 01 Doctor / Physician
        | 02 Hospital / MedicalClinic
        | 03 Breadcrumb
        | 04 WebPage
        | 05 WebSite
        |--------------------------------------------------------------------------
        */

        echo "\n<!-- 01 Doctor Physician Schema Loaded -->\n";
        echo '<script type="application/ld+json">' . PHP_EOL;
        echo json_encode(doctor_schema_remove_empty($physician_schema), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
        echo PHP_EOL . '</script>' . PHP_EOL;

        foreach ($clinic_schema_items as $clinic_schema_item) {
            echo "\n<!-- 02 Doctor MedicalClinic Schema Loaded -->\n";
            echo '<script type="application/ld+json">' . PHP_EOL;
            echo json_encode(doctor_schema_remove_empty($clinic_schema_item), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
            echo PHP_EOL . '</script>' . PHP_EOL;
        }

        echo "\n<!-- 03 Doctor Breadcrumb Schema Loaded -->\n";
        echo '<script type="application/ld+json">' . PHP_EOL;
        echo json_encode(doctor_schema_remove_empty($breadcrumb_schema), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
        echo PHP_EOL . '</script>' . PHP_EOL;

        echo "\n<!-- 04 Doctor WebPage Schema Loaded -->\n";
        echo '<script type="application/ld+json">' . PHP_EOL;
        echo json_encode(doctor_schema_remove_empty($webpage_schema), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
        echo PHP_EOL . '</script>' . PHP_EOL;

        echo "\n<!-- 05 Doctor WebSite Schema Loaded -->\n";
        echo '<script type="application/ld+json">' . PHP_EOL;
        echo json_encode(doctor_schema_remove_empty($website_schema), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
        echo PHP_EOL . '</script>' . PHP_EOL;
    }
}

/*
|--------------------------------------------------------------------------
| Content Sections And Profile Tabs
|--------------------------------------------------------------------------
| Every section is rendered by PHP with the initial page response. Tabs only
| control visibility in the browser, so important profile content remains
| available in the HTML for search engines and for visitors without JS.
|--------------------------------------------------------------------------
*/

$doctor_info_sections = [
    'chambers-schedule.php',
    'about.php',
    'achievements-memberships.php',
    'contact-address.php',
    'consultation-options.php',
];

/*
 * Experience tab order:
 * 1. Education
 * 2. Training
 * 3. Fellowship
 * 4. Medical Focus
 *
 * education-training.php renders the first three profile blocks.
 * clinical-expertise.php renders Medical Focus.
 */
$doctor_experience_sections = [
    'education-training.php',
    'clinical-expertise.php',
];

$doctor_review_sections = [
    'reviews.php',
];

$doctor_tab_labels = [
    'info' => $is_bn_page ? 'তথ্য' : 'Info',
    'experience' => $is_bn_page ? 'অভিজ্ঞতা' : 'Experience',
    'reviews' => $is_bn_page ? 'রিভিউ' : 'Reviews',
];

include __DIR__ . '/includes/header.php';
?>

<style>
.doctor-profile-page {
    background: #f6f8fa;
    padding: 24px 0 40px;
}

.doctor-profile-page .container {
    width: 100%;
    max-width: 1180px;
    margin: 0 auto;
    padding-left: 15px;
    padding-right: 15px;
}

.doctor-profile-full-width {
    width: 100%;
}

/* Profile navigation. All tab panel content stays server-rendered in the page. */
.doctor-profile-tabs-wrap {
    margin: 0 0 26px;
    border-bottom: 1px solid #dfe4ea;
}

.doctor-profile-tabs {
    display: flex;
    align-items: stretch;
    gap: 18px;
    min-height: 58px;
    overflow-x: auto;
    scrollbar-width: thin;
}

.doctor-profile-tab {
    position: relative;
    display: inline-flex;
    align-items: center;
    gap: 9px;
    flex: 0 0 auto;
    min-height: 58px;
    padding: 0 10px;
    border: 0;
    border-bottom: 3px solid transparent;
    background: transparent;
    color: #657084;
    font: inherit;
    font-size: 16px;
    font-weight: 500;
    line-height: 1.2;
    cursor: pointer;
    transition: color .18s ease, border-color .18s ease;
}

.doctor-profile-tab:hover,
.doctor-profile-tab:focus-visible {
    color: #126ff1;
}

.doctor-profile-tab:focus-visible {
    outline: 2px solid #126ff1;
    outline-offset: -2px;
    border-radius: 5px 5px 0 0;
}

.doctor-profile-tab.is-active,
.doctor-profile-tab[aria-selected="true"] {
    border-bottom-color: #126ff1;
    color: #126ff1;
}

.doctor-profile-tab-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 20px;
    height: 20px;
    color: currentColor;
}

.doctor-profile-tab-icon svg {
    width: 20px;
    height: 20px;
    fill: currentColor;
}

.doctor-profile-layout {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 340px;
    gap: 22px;
    align-items: start;
}

.doctor-profile-main {
    min-width: 0;
}

.doctor-profile-tab-panel {
    min-width: 0;
}

/* Related doctors are intentionally outside the tab panels. */
.doctor-profile-related-doctors {
    min-width: 0;
    margin-top: 26px;
}

/* Panels are only hidden after JavaScript has initialized the accessible tabs. */
.doctor-profile-tabs-ready .doctor-profile-tab-panel:not(.is-active) {
    display: none;
}

.doctor-profile-sidebar {
    min-width: 0;
    position: sticky;
    top: 90px;
}

@media (max-width: 992px) {
    .doctor-profile-layout {
        grid-template-columns: 1fr;
    }

    .doctor-profile-sidebar {
        position: static;
    }
}

@media (max-width: 576px) {
    .doctor-profile-page {
        padding: 16px 0 28px;
    }

    .doctor-profile-page .container {
        padding-left: 12px;
        padding-right: 12px;
    }

    .doctor-profile-tabs-wrap {
        margin-bottom: 18px;
    }

    .doctor-profile-tabs {
        gap: 5px;
        min-height: 52px;
    }

    .doctor-profile-tab {
        gap: 6px;
        min-height: 52px;
        padding: 0 8px;
        font-size: 14px;
    }

    .doctor-profile-tab-icon,
    .doctor-profile-tab-icon svg {
        width: 18px;
        height: 18px;
    }
.doctor-profile-tabs {
    gap: 0;
    min-height: 52px;
    justify-content: space-evenly;
}
    .doctor-profile-layout {
        gap: 16px;
    }

    .doctor-profile-related-doctors {
        margin-top: 18px;
    }
}
</style>

<main class="doctor-profile-page">
    <div class="container">
        <div class="doctor-profile-full-width">
            <?php doctor_profile_include_section('breadcrumb.php'); ?>
            <?php doctor_profile_include_section('hero.php'); ?>
        </div>

        <nav class="doctor-profile-tabs-wrap" aria-label="<?= e($is_bn_page ? 'ডাক্তার প্রোফাইল বিভাগ' : 'Doctor profile sections') ?>">
            <div class="doctor-profile-tabs" role="tablist">
                <button
                    id="doctor-tab-info"
                    class="doctor-profile-tab is-active"
                    type="button"
                    role="tab"
                    aria-selected="true"
                    aria-controls="doctor-tab-panel-info"
                    data-doctor-tab="info"
                >
                    <span class="doctor-profile-tab-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24"><path d="M11 17h2v-6h-2v6Zm1-15a10 10 0 1 0 0 20 10 10 0 0 0 0-20Zm0 18a8 8 0 1 1 0-16 8 8 0 0 1 0 16Zm-1-11h2V7h-2v2Z"/></svg>
                    </span>
                    <span><?= e($doctor_tab_labels['info']) ?></span>
                </button>

                <button
                    id="doctor-tab-experience"
                    class="doctor-profile-tab"
                    type="button"
                    role="tab"
                    aria-selected="false"
                    aria-controls="doctor-tab-panel-experience"
                    tabindex="-1"
                    data-doctor-tab="experience"
                >
                    <span class="doctor-profile-tab-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24"><path d="M19 5h-3V3a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2H5a3 3 0 0 0-3 3v8a3 3 0 0 0 3 3h2v2a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2v-2h2a3 3 0 0 0 3-3V8a3 3 0 0 0-3-3ZM10 3h4v2h-4V3Zm5 18H9v-2h6v2Zm5-5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1v-4h16v4Zm0-6H4V8a1 1 0 0 1 1-1h14a1 1 0 0 1 1 1v2Z"/></svg>
                    </span>
                    <span><?= e($doctor_tab_labels['experience']) ?></span>
                </button>

                <button
                    id="doctor-tab-reviews"
                    class="doctor-profile-tab"
                    type="button"
                    role="tab"
                    aria-selected="false"
                    aria-controls="doctor-tab-panel-reviews"
                    tabindex="-1"
                    data-doctor-tab="reviews"
                >
                    <span class="doctor-profile-tab-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24"><path d="M20 2H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h4l4 4 4-4h4a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2Zm0 16h-4.83L12 21.17 8.83 18H4V4h16v14ZM7 9h10V7H7v2Zm0 4h7v-2H7v2Z"/></svg>
                    </span>
                    <span><?= e($doctor_tab_labels['reviews']) ?></span>
                </button>
            </div>
        </nav>

        <div class="doctor-profile-layout">
            <div class="doctor-profile-main">
                <section
                    id="doctor-tab-panel-info"
                    class="doctor-profile-tab-panel is-active"
                    role="tabpanel"
                    aria-labelledby="doctor-tab-info"
                    tabindex="0"
                    data-doctor-tab-panel="info"
                >
                    <?php foreach ($doctor_info_sections as $section_file): ?>
                        <?php doctor_profile_include_section($section_file); ?>
                    <?php endforeach; ?>
                </section>

                <section
                    id="doctor-tab-panel-experience"
                    class="doctor-profile-tab-panel"
                    role="tabpanel"
                    aria-labelledby="doctor-tab-experience"
                    tabindex="0"
                    data-doctor-tab-panel="experience"
                >
                    <?php foreach ($doctor_experience_sections as $section_file): ?>
                        <?php doctor_profile_include_section($section_file); ?>
                    <?php endforeach; ?>
                </section>

                <section
                    id="doctor-tab-panel-reviews"
                    class="doctor-profile-tab-panel"
                    role="tabpanel"
                    aria-labelledby="doctor-tab-reviews"
                    tabindex="0"
                    data-doctor-tab-panel="reviews"
                >
                    <?php foreach ($doctor_review_sections as $section_file): ?>
                        <?php doctor_profile_include_section($section_file); ?>
                    <?php endforeach; ?>
                </section>

                <!-- Related doctors stay visible as a standalone profile section, outside Info / Experience / Reviews tabs. -->
                <div class="doctor-profile-related-doctors">
                    <?php doctor_profile_include_section('related-doctors.php'); ?>
                </div>
            </div>

            <aside class="doctor-profile-sidebar">
                <?php doctor_profile_include_section('sidebar.php'); ?>
            </aside>
        </div>
    </div>
</main>

<script>
(function () {
    var tabList = document.querySelector('.doctor-profile-tabs[role="tablist"]');

    if (!tabList) {
        return;
    }

    var tabs = Array.prototype.slice.call(tabList.querySelectorAll('[role="tab"]'));
    var panels = Array.prototype.slice.call(document.querySelectorAll('[data-doctor-tab-panel]'));
    var page = document.querySelector('.doctor-profile-page');

    if (!tabs.length || !panels.length || !page) {
        return;
    }

    page.classList.add('doctor-profile-tabs-ready');

    function activateTab(tabName, updateHash) {
        var selectedTab = null;

        tabs.forEach(function (tab) {
            var isActive = tab.getAttribute('data-doctor-tab') === tabName;

            tab.classList.toggle('is-active', isActive);
            tab.setAttribute('aria-selected', isActive ? 'true' : 'false');
            tab.tabIndex = isActive ? 0 : -1;

            if (isActive) {
                selectedTab = tab;
            }
        });

        panels.forEach(function (panel) {
            var isActive = panel.getAttribute('data-doctor-tab-panel') === tabName;
            panel.classList.toggle('is-active', isActive);
            panel.hidden = !isActive;
        });

        if (selectedTab && updateHash && window.history && window.history.replaceState) {
            window.history.replaceState(null, '', '#' + tabName);
        }
    }

    var initialTab = window.location.hash.replace('#', '');
    var allowedTabs = ['info', 'experience', 'reviews'];

    if (allowedTabs.indexOf(initialTab) === -1) {
        initialTab = 'info';
    }

    activateTab(initialTab, false);

    tabs.forEach(function (tab, index) {
        tab.addEventListener('click', function () {
            activateTab(tab.getAttribute('data-doctor-tab'), true);
        });

        tab.addEventListener('keydown', function (event) {
            var nextIndex = null;

            if (event.key === 'ArrowRight' || event.key === 'ArrowDown') {
                nextIndex = (index + 1) % tabs.length;
            } else if (event.key === 'ArrowLeft' || event.key === 'ArrowUp') {
                nextIndex = (index - 1 + tabs.length) % tabs.length;
            } else if (event.key === 'Home') {
                nextIndex = 0;
            } else if (event.key === 'End') {
                nextIndex = tabs.length - 1;
            }

            if (nextIndex === null) {
                return;
            }

            event.preventDefault();
            tabs[nextIndex].focus();
            activateTab(tabs[nextIndex].getAttribute('data-doctor-tab'), true);
        });
    });

    window.addEventListener('hashchange', function () {
        var hashTab = window.location.hash.replace('#', '');

        if (allowedTabs.indexOf(hashTab) !== -1) {
            activateTab(hashTab, false);
        }
    });
}());
</script>

<?php doctor_schema_output($doctor, $page_title, $meta_description); ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
