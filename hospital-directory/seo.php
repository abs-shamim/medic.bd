<?php

$hospital_directory_seo_version = 'bangla-heading-hierarchy-20260629';

/**
 * Hospital directory SEO, localized heading, canonical URL,
 * Open Graph and pagination helpers.
 *
 * Load this file before /includes/header.php.
 * Do not print <meta>, <link> or <script> tags here.
 */

/*
|--------------------------------------------------------------------------
| Translation Helpers
|--------------------------------------------------------------------------
*/
function hp_seo_locale(): string
{
    if (function_exists('hp_directory_lang')) {
        return hp_directory_lang();
    }

    global $lang;

    return isset($lang) && $lang === 'bn' ? 'bn' : 'en';
}

function hp_seo_t(string $key, string $english, string $bangla, array $replace = []): string
{
    $locale = hp_seo_locale();
    $fallback = $locale === 'bn' ? $bangla : $english;

    return hp_t($key, $fallback, $replace);
}

function hp_seo_clean_text($value): string
{
    $value = trim(strip_tags((string) $value));
    $value = preg_replace('/\s+/u', ' ', $value);

    return trim((string) $value);
}

function hp_seo_limit_text(string $text, int $limit = 160): string
{
    $text = hp_seo_clean_text($text);

    if ($text === '') {
        return '';
    }

    if (function_exists('mb_strimwidth')) {
        return mb_strimwidth($text, 0, $limit, '…', 'UTF-8');
    }

    /*
     * Unicode-safe fallback when mbstring is unavailable.
     * This prevents a Bangla UTF-8 character from being cut in the middle.
     */
    $character_count = preg_match_all('/./us', $text, $characters);

    if ($character_count !== false) {
        if ($character_count <= $limit) {
            return $text;
        }

        return implode('', array_slice($characters[0], 0, max(0, $limit - 1))) . '…';
    }

    return strlen($text) > $limit
        ? substr($text, 0, max(0, $limit - 3)) . '...'
        : $text;
}

function hp_seo_name(array $row, string $fallback): string
{
    return hp_display(
        hp_value($row, 'name', $fallback)
    );
}

function hp_seo_location(array $division, array $district, array $thana): string
{
    $parts = [];

    if (!empty($thana)) {
        $parts[] = hp_seo_name(
            $thana,
            hp_seo_t(
                'hospitals_page_selected_area',
                'Selected Area',
                'নির্বাচিত এলাকা'
            )
        );
    }

    if (!empty($district)) {
        $parts[] = hp_seo_name(
            $district,
            hp_seo_t(
                'hospitals_page_selected_district',
                'Selected District',
                'নির্বাচিত জেলা'
            )
        );
    }

    if (empty($district) && !empty($division)) {
        $parts[] = hp_seo_name(
            $division,
            hp_seo_t(
                'hospitals_page_selected_division',
                'Selected Division',
                'নির্বাচিত বিভাগ'
            )
        );
    }

    return implode(', ', array_filter($parts, static function ($item) {
        return trim((string) $item) !== '';
    }));
}


/*
|--------------------------------------------------------------------------
| Localized Hospital Type List Labels
|--------------------------------------------------------------------------
| The normal type label is used for tags, for example “Diagnostic Center”.
| A separate list label is used in headings, for example
| “ডায়াগনস্টিক সেন্টারের তালিকা”.
*/
function hp_hospital_type_list_label(string $type): string
{
    $type = trim($type);

    $keys = [
        'Private Hospital' => 'hospitals_page_type_private_hospital_list',
        'Government Hospital' => 'hospitals_page_type_government_hospital_list',
        'Medical College Hospital' => 'hospitals_page_type_medical_college_hospital_list',
        'Specialized Hospital' => 'hospitals_page_type_specialized_hospital_list',
        'General Hospital' => 'hospitals_page_type_general_hospital_list',
        'Diagnostic Center' => 'hospitals_page_type_diagnostic_center_list',
        'Clinic' => 'hospitals_page_type_clinic_list',
        'Eye Hospital' => 'hospitals_page_type_eye_hospital_list',
        'Dental Hospital' => 'hospitals_page_type_dental_hospital_list',
        'Cardiac Hospital' => 'hospitals_page_type_cardiac_hospital_list',
        'Cancer Hospital' => 'hospitals_page_type_cancer_hospital_list',
        'Mother and Child Hospital' => 'hospitals_page_type_mother_child_hospital_list',
        'Rehabilitation Center' => 'hospitals_page_type_rehabilitation_center_list',
    ];

    if ($type !== '' && isset($keys[$type])) {
        return hp_t($keys[$type], $type . ' List');
    }

    return $type !== ''
        ? hp_hospital_type_label($type)
        : hp_t('hospitals_page_default_hospital_type', 'Hospital');
}

/*
|--------------------------------------------------------------------------
| Visible H1 Heading
|--------------------------------------------------------------------------
*/
function hp_page_heading(
    string $page_step,
    array $division,
    array $district,
    array $thana,
    string $hospital_type,
    array $filters
): string {
    $search = hp_seo_clean_text((string) ($filters['search'] ?? ''));

    $division_name = hp_seo_name(
        $division,
        hp_seo_t(
            'hospitals_page_selected_division',
            'Selected Division',
            'নির্বাচিত বিভাগ'
        )
    );

    $district_name = hp_seo_name(
        $district,
        hp_seo_t(
            'hospitals_page_selected_district',
            'Selected District',
            'নির্বাচিত জেলা'
        )
    );

    $thana_name = hp_seo_name(
        $thana,
        hp_seo_t(
            'hospitals_page_selected_area',
            'Selected Area',
            'নির্বাচিত এলাকা'
        )
    );

    if ($page_step === 'not_found') {
        return hp_seo_t(
            'hospitals_page_not_found_heading',
            'Page not found',
            'পৃষ্ঠা পাওয়া যায়নি'
        );
    }

    if ($search !== '') {
        return hp_seo_t(
            'hospitals_page_heading_search_results',
            'Hospital Search Results for :search',
            ':search-এর হাসপাতাল অনুসন্ধানের ফলাফল',
            [
                ':search' => hp_seo_limit_text($search, 70),
            ]
        );
    }

    if ($hospital_type !== '') {
        $type_list = hp_display(hp_hospital_type_list_label($hospital_type));

        if (!empty($thana) && !empty($district)) {
            return hp_seo_t(
                'hospitals_page_heading_type_thana',
                ':type_list in :thana Thana, :district District',
                ':thana থানার :district জেলার :type_list',
                [
                    ':type_list' => $type_list,
                    ':thana' => $thana_name,
                    ':district' => $district_name,
                ]
            );
        }

        if (!empty($district)) {
            return hp_seo_t(
                'hospitals_page_heading_type_district',
                ':type_list in :district District',
                ':district জেলার :type_list',
                [
                    ':type_list' => $type_list,
                    ':district' => $district_name,
                ]
            );
        }

        if (!empty($division)) {
            return hp_seo_t(
                'hospitals_page_heading_type_division',
                ':type_list in :division Division',
                ':division বিভাগের :type_list',
                [
                    ':type_list' => $type_list,
                    ':division' => $division_name,
                ]
            );
        }

        return hp_seo_t(
            'hospitals_page_heading_type_root',
            ':type_list in Bangladesh',
            'বাংলাদেশের :type_list',
            [
                ':type_list' => $type_list,
            ]
        );
    }

    if (!empty($thana) && !empty($district)) {
        return hp_seo_t(
            'hospitals_page_heading_thana_list',
            'Hospital List in :thana Thana, :district District',
            ':thana থানার :district জেলার হাসপাতালের তালিকা',
            [
                ':thana' => $thana_name,
                ':district' => $district_name,
            ]
        );
    }

    if (!empty($district)) {
        return hp_seo_t(
            'hospitals_page_heading_district_list',
            'Hospital List in :district District',
            ':district জেলার হাসপাতালের তালিকা',
            [
                ':district' => $district_name,
            ]
        );
    }

    if (!empty($division)) {
        return hp_seo_t(
            'hospitals_page_heading_division_list',
            'Hospital List in :division Division',
            ':division বিভাগের হাসপাতালের তালিকা',
            [
                ':division' => $division_name,
            ]
        );
    }

    return hp_seo_t(
        'hospitals_page_heading_root',
        'Hospital List in Bangladesh',
        'বাংলাদেশের হাসপাতালের তালিকা'
    );
}

/*
|--------------------------------------------------------------------------
| SEO Page Title
|--------------------------------------------------------------------------
*/
function hp_page_title(
    string $page_step,
    array $division,
    array $district,
    array $thana,
    string $hospital_type,
    array $filters
): string {
    $site_name = hp_site_name();

    $heading = hp_page_heading(
        $page_step,
        $division,
        $district,
        $thana,
        $hospital_type,
        $filters
    );

    return hp_seo_t(
        'hospitals_page_seo_title',
        ':title | :site',
        ':title | :site',
        [
            ':title' => $heading,
            ':site' => $site_name,
        ]
    );
}

/*
|--------------------------------------------------------------------------
| SEO Meta Description
|--------------------------------------------------------------------------
*/
function hp_page_description(
    string $page_step,
    array $division,
    array $district,
    array $thana,
    string $hospital_type,
    array $filters = []
): string {
    $search = hp_seo_clean_text((string) ($filters['search'] ?? ''));

    $division_name = hp_seo_name(
        $division,
        hp_seo_t('hospitals_page_this_division', 'this division', 'এই বিভাগ')
    );

    $district_name = hp_seo_name(
        $district,
        hp_seo_t('hospitals_page_this_district', 'this district', 'এই জেলা')
    );

    $thana_name = hp_seo_name(
        $thana,
        hp_seo_t('hospitals_page_this_area', 'this area', 'এই এলাকা')
    );

    if ($page_step === 'not_found') {
        return hp_seo_t(
            'hospitals_page_not_found_description',
            'The requested hospital directory page could not be found. Browse hospitals by division, district, thana and hospital category.',
            'অনুরোধকৃত হাসপাতাল ডিরেক্টরি পৃষ্ঠাটি পাওয়া যায়নি। বিভাগ, জেলা, থানা ও হাসপাতালের ক্যাটাগরি অনুযায়ী হাসপাতাল খুঁজুন।'
        );
    }

    if ($search !== '') {
        return hp_seo_t(
            'hospitals_page_search_results_description',
            'Browse hospital search results for :search. Check location, departments, doctors, facilities and appointment details.',
            ':search-এর হাসপাতাল অনুসন্ধানের ফলাফল দেখুন। অবস্থান, বিভাগ, ডাক্তার, সুবিধা ও অ্যাপয়েন্টমেন্টের তথ্য দেখুন।',
            [
                ':search' => hp_seo_limit_text($search, 70),
            ]
        );
    }

    if ($hospital_type !== '') {
        $type_list = hp_display(hp_hospital_type_list_label($hospital_type));

        if (!empty($thana) && !empty($district)) {
            return hp_seo_t(
                'hospitals_page_type_thana_description',
                'Browse :type_list in :thana Thana, :district District. Check hospital information, doctors, departments, facilities and appointment details.',
                ':thana থানার :district জেলার :type_list দেখুন। হাসপাতালের তথ্য, ডাক্তার, বিভাগ, সুবিধা ও অ্যাপয়েন্টমেন্টের তথ্য দেখুন।',
                [
                    ':type_list' => $type_list,
                    ':thana' => $thana_name,
                    ':district' => $district_name,
                ]
            );
        }

        if (!empty($district)) {
            return hp_seo_t(
                'hospitals_page_type_district_description',
                'Browse :type_list in :district District. Check hospital information, doctors, departments, facilities and appointment details.',
                ':district জেলার :type_list দেখুন। হাসপাতালের তথ্য, ডাক্তার, বিভাগ, সুবিধা ও অ্যাপয়েন্টমেন্টের তথ্য দেখুন।',
                [
                    ':type_list' => $type_list,
                    ':district' => $district_name,
                ]
            );
        }

        if (!empty($division)) {
            return hp_seo_t(
                'hospitals_page_type_division_description',
                'Browse :type_list in :division Division. Check hospital information, doctors, departments, facilities and appointment details.',
                ':division বিভাগের :type_list দেখুন। হাসপাতালের তথ্য, ডাক্তার, বিভাগ, সুবিধা ও অ্যাপয়েন্টমেন্টের তথ্য দেখুন।',
                [
                    ':type_list' => $type_list,
                    ':division' => $division_name,
                ]
            );
        }

        return hp_seo_t(
            'hospitals_page_type_root_description',
            'Browse :type_list in Bangladesh. Check hospital information, doctors, departments, facilities and appointment details.',
            'বাংলাদেশের :type_list দেখুন। হাসপাতালের তথ্য, ডাক্তার, বিভাগ, সুবিধা ও অ্যাপয়েন্টমেন্টের তথ্য দেখুন।',
            [
                ':type_list' => $type_list,
            ]
        );
    }

    if ($page_step === 'division') {
        return hp_seo_t(
            'hospitals_page_select_division_description',
            'Find hospitals in Bangladesh by division, district, thana and hospital category.',
            'বিভাগ, জেলা, থানা ও হাসপাতালের ক্যাটাগরি অনুযায়ী বাংলাদেশের হাসপাতাল খুঁজুন।'
        );
    }

    if ($page_step === 'district' && !empty($division)) {
        return hp_seo_t(
            'hospitals_page_division_list_description',
            'Browse the hospital list in :division Division. Select a district to view hospitals by location and category.',
            ':division বিভাগের হাসপাতালের তালিকা দেখুন। লোকেশন ও ক্যাটাগরি অনুযায়ী হাসপাতাল দেখতে একটি জেলা নির্বাচন করুন।',
            [
                ':division' => $division_name,
            ]
        );
    }

    if ($page_step === 'thana_type' && !empty($thana) && !empty($district)) {
        return hp_seo_t(
            'hospitals_page_thana_list_description',
            'Browse the hospital list in :thana Thana, :district District. Choose a hospital category or view the available hospitals below.',
            ':thana থানার :district জেলার হাসপাতালের তালিকা দেখুন। হাসপাতালের ক্যাটাগরি নির্বাচন করুন অথবা নিচের হাসপাতালগুলো দেখুন।',
            [
                ':thana' => $thana_name,
                ':district' => $district_name,
            ]
        );
    }

    if ($page_step === 'thana_type' && !empty($district)) {
        return hp_seo_t(
            'hospitals_page_district_list_description',
            'Browse the hospital list in :district District. Select a thana or hospital category to refine the list.',
            ':district জেলার হাসপাতালের তালিকা দেখুন। তালিকাটি নির্দিষ্ট করতে একটি থানা অথবা হাসপাতালের ক্যাটাগরি নির্বাচন করুন।',
            [
                ':district' => $district_name,
            ]
        );
    }

    return hp_seo_t(
        'hospitals_page_default_description',
        'Find hospitals in Bangladesh by division, district, thana and hospital category.',
        'বিভাগ, জেলা, থানা ও হাসপাতালের ক্যাটাগরি অনুযায়ী বাংলাদেশের হাসপাতাল খুঁজুন।'
    );
}

/*
|--------------------------------------------------------------------------
| Main Variables Used By Header And View
|--------------------------------------------------------------------------
*/
$hospital_page_heading = hp_page_heading(
    $page_step,
    $division,
    $district,
    $thana,
    $hospital_type,
    $filters
);

/*
 * Keeps compatibility with existing view.php code.
 */
$hp_clean_page_heading = $hospital_page_heading;

$page_title = hp_page_title(
    $page_step,
    $division,
    $district,
    $thana,
    $hospital_type,
    $filters
);

$meta_description = hp_seo_limit_text(
    hp_page_description(
        $page_step,
        $division,
        $district,
        $thana,
        $hospital_type,
        $filters
    ),
    160
);

/*
|--------------------------------------------------------------------------
| Optional Root English SEO Override
|--------------------------------------------------------------------------
*/
$default_meta_title = hp_site_setting('meta_title', '');
$default_meta_description = hp_site_setting('meta_description', '');

if (hp_seo_locale() === 'en' && $page_step === 'division') {
    if ($default_meta_title !== '') {
        $page_title = hp_seo_clean_text($default_meta_title);
    }

    if ($default_meta_description !== '') {
        $meta_description = hp_seo_limit_text($default_meta_description, 160);
    }
}

/*
|--------------------------------------------------------------------------
| Canonical URL And Query Arguments
|--------------------------------------------------------------------------
*/
$route_args = [
    'division_slug' => !empty($division) && empty($district)
        ? hp_row_slug($division)
        : '',
    'district_slug' => !empty($district)
        ? hp_row_slug($district)
        : '',
    'thana_slug' => !empty($thana)
        ? hp_row_slug($thana)
        : '',
    'type_slug' => $hospital_type !== ''
        ? hp_url_slug($hospital_type)
        : '',
];

$canonical_args = array_merge($route_args, [
    'search' => trim((string) ($filters['search'] ?? '')),
    'service' => trim((string) ($filters['service'] ?? '')),
    'page' => $current_page > 1 ? (string) $current_page : '',
]);

$seo_canonical_args = array_merge($route_args, [
    'search' => '',
    'service' => '',
    'page' => '',
]);

$canonical_url = hp_hospitals_url($seo_canonical_args);

/*
|--------------------------------------------------------------------------
| Robots Meta
|--------------------------------------------------------------------------
*/
$has_search_filter = trim((string) ($filters['search'] ?? '')) !== '';
$has_service_filter = trim((string) ($filters['service'] ?? '')) !== '';
$is_paginated = $current_page > 1;

$robots_meta = (
    $page_step === 'not_found'
    || $has_search_filter
    || $has_service_filter
    || $is_paginated
)
    ? 'noindex, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1'
    : 'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1';

/*
|--------------------------------------------------------------------------
| Open Graph And Twitter
|--------------------------------------------------------------------------
*/
$og_title = $page_title;
$og_description = $meta_description;
$og_type = 'website';
$og_locale = hp_seo_locale() === 'bn' ? 'bn_BD' : 'en_US';
$og_image = hp_setting_url('default_og_image', 'assets/images/default-og-image.webp');

$og_image_alt = hp_seo_t(
    'hospitals_page_og_image_alt',
    'MedicBD hospital directory',
    'MedicBD হাসপাতাল ডিরেক্টরি'
);

$twitter_card = $og_image !== ''
    ? 'summary_large_image'
    : 'summary';

/*
|--------------------------------------------------------------------------
| Search Form Action URL
|--------------------------------------------------------------------------
*/
$current_action_url = hp_hospitals_url($route_args);

if ($page_step === 'division') {
    $current_action_url = hp_front_url('hospitals');
}

/*
|--------------------------------------------------------------------------
| Hospital List And Category Headings
|--------------------------------------------------------------------------
*/
$hospital_list_heading = hp_page_heading(
    $page_step,
    $division,
    $district,
    $thana,
    $hospital_type,
    $filters
);

$hospital_category_heading = hp_seo_t(
    'hospitals_page_category_heading_root',
    'Hospital Categories',
    'হাসপাতালের ক্যাটাগরি'
);

if (!empty($thana) && !empty($district)) {
    $hospital_category_heading = hp_seo_t(
        'hospitals_page_category_heading_thana',
        ':thana Thana, :district District Hospital Categories',
        ':thana থানার :district জেলার হাসপাতালের ক্যাটাগরি',
        [
            ':thana' => hp_seo_name(
                $thana,
                hp_seo_t('hospitals_page_selected_area', 'Selected Area', 'নির্বাচিত এলাকা')
            ),
            ':district' => hp_seo_name(
                $district,
                hp_seo_t('hospitals_page_selected_district', 'Selected District', 'নির্বাচিত জেলা')
            ),
        ]
    );
} elseif (!empty($district)) {
    $hospital_category_heading = hp_seo_t(
        'hospitals_page_category_heading_district',
        ':district District Hospital Categories',
        ':district জেলার হাসপাতালের ক্যাটাগরি',
        [
            ':district' => hp_seo_name(
                $district,
                hp_seo_t('hospitals_page_selected_district', 'Selected District', 'নির্বাচিত জেলা')
            ),
        ]
    );
} elseif (!empty($division)) {
    $hospital_category_heading = hp_seo_t(
        'hospitals_page_category_heading_division',
        ':division Division Hospital Categories',
        ':division বিভাগের হাসপাতালের ক্যাটাগরি',
        [
            ':division' => hp_seo_name(
                $division,
                hp_seo_t('hospitals_page_selected_division', 'Selected Division', 'নির্বাচিত বিভাগ')
            ),
        ]
    );
}

/*
|--------------------------------------------------------------------------
| Pagination Helpers
|--------------------------------------------------------------------------
*/
function hp_pagination_url(array $canonical_args, int $page): string
{
    $args = $canonical_args;
    $args['page'] = $page > 1 ? (string) $page : '';

    return hp_hospitals_url($args);
}

function hp_pagination_items(int $current_page, int $total_pages, bool $mobile = false): array
{
    $current_page = max(1, $current_page);
    $total_pages = max(1, $total_pages);

    if ($total_pages <= ($mobile ? 7 : 10)) {
        return range(1, $total_pages);
    }

    if ($mobile) {
        $items = [1, 2, 3];

        if ($current_page > 3 && $current_page < $total_pages - 1) {
            $items[] = 'dots-left';
            $items[] = $current_page;
            $items[] = 'dots-right';
        } else {
            $items[] = 'dots-right';
        }

        $items[] = $total_pages - 1;
        $items[] = $total_pages;

        return array_values(array_unique($items, SORT_REGULAR));
    }

    $items = [1, 2, 3, 4];

    if ($current_page > 4 && $current_page < $total_pages - 3) {
        $items[] = 'dots-left';
        $items[] = $current_page;
        $items[] = 'dots-right';
    } else {
        $items[] = 'dots-right';
    }

    $items[] = $total_pages - 3;
    $items[] = $total_pages - 2;
    $items[] = $total_pages - 1;
    $items[] = $total_pages;

    $items = array_filter($items, static function ($item) use ($total_pages) {
        return is_string($item) || ((int) $item >= 1 && (int) $item <= $total_pages);
    });

    return array_values(array_unique($items, SORT_REGULAR));
}
/*
|--------------------------------------------------------------------------
| Hospital Directory: Complete SEO, Social Image and JSON-LD Schema
|--------------------------------------------------------------------------
| This block runs before the shared header is included by view.php.
| It provides:
| - Context-aware title, description, canonical and robots settings.
| - Dynamic Open Graph / Twitter image selection.
| - WebSite, CollectionPage, BreadcrumbList and ItemList JSON-LD.
| - A Hospital, MedicalClinic or DiagnosticLab node for each visible hospital.
|
| No fake ratings, prices, operating hours or addresses are created. A field
| is added to schema only when the database provides a usable value.
|--------------------------------------------------------------------------
*/

if (!function_exists('hp_schema_text')) {
    function hp_schema_text($value): string
    {
        $value = trim(strip_tags((string) $value));
        $value = preg_replace('/\s+/u', ' ', $value);

        return trim((string) $value);
    }
}

if (!function_exists('hp_schema_asset_url')) {
    function hp_schema_asset_url(string $path): string
    {
        $path = trim($path);

        if ($path === '') {
            return '';
        }

        if (preg_match('/^https?:\/\//i', $path)) {
            return $path;
        }

        $path = str_replace('\\', '/', $path);
        $path = preg_replace('#^(?:\./|\.\./)+#', '', $path);
        $path = ltrim((string) $path, '/');

        return function_exists('site_url') ? site_url($path) : '/' . $path;
    }
}

if (!function_exists('hp_schema_image_type')) {
    function hp_schema_image_type(string $url): string
    {
        $path = (string) parse_url($url, PHP_URL_PATH);
        $extension = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));

        return match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            default => '',
        };
    }
}

if (!function_exists('hp_schema_page_name')) {
    function hp_schema_page_name(string $title, string $site_name): string
    {
        $title = hp_schema_text($title);
        $site_name = hp_schema_text($site_name);
        $suffix = $site_name !== '' ? ' | ' . $site_name : '';

        if ($suffix !== '' && str_ends_with($title, $suffix)) {
            return trim(substr($title, 0, -strlen($suffix)));
        }

        return $title;
    }
}

if (!function_exists('hp_schema_hospital_url')) {
    function hp_schema_hospital_url(array $hospital): string
    {
        $slug = hp_url_slug((string) ($hospital['slug'] ?? ''));

        if ($slug === '') {
            $slug = hp_url_slug((string) ($hospital['name'] ?? ''));
        }

        return $slug !== '' ? hp_front_url('hospital/' . $slug) : '';
    }
}

if (!function_exists('hp_schema_hospital_type')) {
    function hp_schema_hospital_type(array $hospital): string
    {
        $type = strtolower(trim((string) ($hospital['type'] ?? '')));

        if (str_contains($type, 'diagnostic')) {
            return 'DiagnosticLab';
        }

        if (
            str_contains($type, 'clinic')
            || str_contains($type, 'rehabilitation')
        ) {
            return 'MedicalClinic';
        }

        return 'Hospital';
    }
}

if (!function_exists('hp_schema_hospital_image')) {
    function hp_schema_hospital_image(array $hospital): string
    {
        foreach (['social_card_image', 'share_card_image', 'cover_image', 'image'] as $key) {
            $candidate = trim((string) ($hospital[$key] ?? ''));

            if ($candidate !== '') {
                return hp_schema_asset_url($candidate);
            }
        }

        return '';
    }
}

if (!function_exists('hp_schema_hospital_node')) {
    function hp_schema_hospital_node(
        array $hospital,
        array $division,
        array $district,
        array $thana
    ): array {
        $name = hp_schema_text(hp_value($hospital, 'name', ''));
        $url = hp_schema_hospital_url($hospital);

        if ($name === '' || $url === '') {
            return [];
        }

        $node = [
            '@type' => hp_schema_hospital_type($hospital),
            '@id' => $url . '#hospital',
            'name' => $name,
            'url' => $url,
        ];

        $image = hp_schema_hospital_image($hospital);

        if ($image !== '') {
            $node['image'] = $image;
        }

        $description = hp_schema_text(hp_value($hospital, 'description', ''));

        if ($description !== '') {
            $node['description'] = hp_seo_limit_text($description, 300);
        }

        $telephone = hp_schema_text((string) ($hospital['phone'] ?? ($hospital['emergency_phone'] ?? '')));

        if ($telephone !== '') {
            $node['telephone'] = $telephone;
        }

        $email = hp_schema_text((string) ($hospital['email'] ?? ''));

        if ($email !== '') {
            $node['email'] = $email;
        }

        $website = trim((string) ($hospital['website_url'] ?? ''));

        if (preg_match('/^https?:\/\//i', $website)) {
            $node['sameAs'] = [$website];
        }

        $address = hp_schema_text(hp_value($hospital, 'address', ''));
        $district_name = hp_schema_text(hp_value($district, 'name', ''));
        $division_name = hp_schema_text(hp_value($division, 'name', ''));

        if ($address !== '' || $district_name !== '' || $division_name !== '') {
            $postal_address = [
                '@type' => 'PostalAddress',
                'addressCountry' => 'BD',
            ];

            if ($address !== '') {
                $postal_address['streetAddress'] = $address;
            }

            if ($district_name !== '') {
                $postal_address['addressLocality'] = $district_name;
            }

            if ($division_name !== '') {
                $postal_address['addressRegion'] = $division_name;
            }

            $postal_code = hp_schema_text((string) ($hospital['post_code'] ?? ''));

            if ($postal_code !== '') {
                $postal_address['postalCode'] = $postal_code;
            }

            $node['address'] = $postal_address;
        }

        $latitude = trim((string) ($hospital['latitude'] ?? ''));
        $longitude = trim((string) ($hospital['longitude'] ?? ''));

        if (is_numeric($latitude) && is_numeric($longitude)) {
            $node['geo'] = [
                '@type' => 'GeoCoordinates',
                'latitude' => (float) $latitude,
                'longitude' => (float) $longitude,
            ];
        }

        $opening_hours = hp_schema_text(hp_value($hospital, 'opening_hours', ''));

        if ($opening_hours !== '') {
            $node['openingHours'] = $opening_hours;
        }

        $department_count = (int) ($hospital['departments_count'] ?? 0);
        $doctor_count = (int) ($hospital['doctors_count'] ?? 0);

        if ($department_count > 0 || $doctor_count > 0) {
            $additional_property = [];

            if ($department_count > 0) {
                $additional_property[] = [
                    '@type' => 'PropertyValue',
                    'name' => hp_seo_locale() === 'bn' ? 'বিভাগের সংখ্যা' : 'Departments',
                    'value' => (string) $department_count,
                ];
            }

            if ($doctor_count > 0) {
                $additional_property[] = [
                    '@type' => 'PropertyValue',
                    'name' => hp_seo_locale() === 'bn' ? 'ডাক্তারের সংখ্যা' : 'Doctors',
                    'value' => (string) $doctor_count,
                ];
            }

            $node['additionalProperty'] = $additional_property;
        }

        return $node;
    }
}

if (!function_exists('hp_schema_breadcrumbs')) {
    function hp_schema_breadcrumbs(
        array $division,
        array $district,
        array $thana,
        string $hospital_type,
        string $canonical_url
    ): array {
        $items = [];
        $position = 1;

        $add = static function (string $name, string $url) use (&$items, &$position): void {
            $name = hp_schema_text($name);
            $url = trim($url);

            if ($name === '' || $url === '') {
                return;
            }

            $items[] = [
                '@type' => 'ListItem',
                'position' => $position++,
                'name' => $name,
                'item' => $url,
            ];
        };

        $add(hp_seo_locale() === 'bn' ? 'হোম' : 'Home', hp_front_url());
        $add(hp_seo_locale() === 'bn' ? 'হাসপাতাল' : 'Hospitals', hp_front_url('hospitals'));

        if (!empty($division) && empty($district)) {
            $add(
                hp_value($division, 'name', ''),
                hp_hospitals_url(['division_slug' => hp_row_slug($division)])
            );
        }

        if (!empty($district)) {
            $add(
                hp_value($district, 'name', ''),
                hp_hospitals_url(['district_slug' => hp_row_slug($district)])
            );
        }

        if (!empty($thana) && !empty($district)) {
            $add(
                hp_value($thana, 'name', ''),
                hp_hospitals_url([
                    'district_slug' => hp_row_slug($district),
                    'thana_slug' => hp_row_slug($thana),
                ])
            );
        }

        if ($hospital_type !== '') {
            $add(
                hp_hospital_type_label($hospital_type),
                $canonical_url
            );
        } elseif ($canonical_url !== '') {
            $last_item = end($items);

            if (!is_array($last_item) || ($last_item['item'] ?? '') !== $canonical_url) {
                $add(
                    hp_schema_page_name($GLOBALS['page_title'] ?? '', hp_site_name()),
                    $canonical_url
                );
            }
        }

        return $items;
    }
}

if (!function_exists('hp_build_directory_schema')) {
    function hp_build_directory_schema(
        string $page_step,
        array $division,
        array $district,
        array $thana,
        string $hospital_type,
        array $hospitals,
        int $total_hospitals,
        int $current_page,
        array $filters,
        string $page_title,
        string $meta_description,
        string $canonical_url,
        string $og_image
    ): array {
        if ($page_step === 'not_found' || $canonical_url === '') {
            return [];
        }

        $site_name = hp_site_name();
        $page_name = hp_schema_page_name($page_title, $site_name);
        $description = hp_schema_text($meta_description);
        $website_url = hp_front_url();
        $webpage_id = $canonical_url . '#webpage';
        $breadcrumb_id = $canonical_url . '#breadcrumb';
        $graph = [];

        $graph[] = [
            '@type' => 'WebSite',
            '@id' => $website_url . '#website',
            'name' => $site_name,
            'url' => $website_url,
            'inLanguage' => hp_seo_locale() === 'bn' ? 'bn-BD' : 'en-BD',
        ];

        $page_node = [
            '@type' => 'CollectionPage',
            '@id' => $webpage_id,
            'url' => $canonical_url,
            'name' => $page_name,
            'isPartOf' => ['@id' => $website_url . '#website'],
            'inLanguage' => hp_seo_locale() === 'bn' ? 'bn-BD' : 'en-BD',
        ];

        if ($description !== '') {
            $page_node['description'] = $description;
        }

        if ($og_image !== '') {
            $page_node['primaryImageOfPage'] = [
                '@type' => 'ImageObject',
                'url' => $og_image,
            ];
        }

        $graph[] = $page_node;

        $breadcrumb_items = hp_schema_breadcrumbs(
            $division,
            $district,
            $thana,
            $hospital_type,
            $canonical_url
        );

        if (!empty($breadcrumb_items)) {
            $graph[] = [
                '@type' => 'BreadcrumbList',
                '@id' => $breadcrumb_id,
                'itemListElement' => $breadcrumb_items,
            ];
        }

        $is_clean_first_page = $current_page === 1
            && trim((string) ($filters['search'] ?? '')) === ''
            && trim((string) ($filters['service'] ?? '')) === '';

        if ($is_clean_first_page && !empty($hospitals)) {
            $item_list = [];

            foreach ($hospitals as $index => $hospital) {
                $hospital_node = hp_schema_hospital_node($hospital, $division, $district, $thana);

                if (empty($hospital_node)) {
                    continue;
                }

                $item_list[] = [
                    '@type' => 'ListItem',
                    'position' => $index + 1,
                    'item' => $hospital_node,
                ];
            }

            if (!empty($item_list)) {
                $graph[] = [
                    '@type' => 'ItemList',
                    '@id' => $canonical_url . '#hospital-list',
                    'name' => $page_name,
                    'url' => $canonical_url,
                    'numberOfItems' => count($item_list),
                    'itemListOrder' => 'https://schema.org/ItemListUnordered',
                    'itemListElement' => $item_list,
                ];
            }
        }

        return [
            '@context' => 'https://schema.org',
            '@graph' => $graph,
        ];
    }
}

if (!function_exists('hp_render_directory_schema')) {
    function hp_render_directory_schema(array $schema): string
    {
        if (empty($schema['@graph']) || !is_array($schema['@graph'])) {
            return '';
        }

        $json = json_encode(
            $schema,
            JSON_UNESCAPED_SLASHES
            | JSON_UNESCAPED_UNICODE
            | JSON_HEX_TAG
            | JSON_HEX_AMP
            | JSON_HEX_APOS
            | JSON_HEX_QUOT
        );

        if ($json === false) {
            return '';
        }

        return "<script type=\"application/ld+json\">\n" . $json . "\n</script>";
    }
}

if (!function_exists('hp_directory_og_image_details')) {
    function hp_directory_og_image_details(
        array $hospitals,
        array $division,
        array $district,
        array $thana,
        string $page_title
    ): array {
        $candidates = [];

        foreach ($hospitals as $hospital) {
            $image = hp_schema_hospital_image($hospital);

            if ($image !== '') {
                $candidates[] = $image;
                break;
            }
        }

        foreach ([$thana, $district, $division] as $location) {
            $location_image = trim((string) ($location['image'] ?? ''));

            if ($location_image !== '') {
                $candidates[] = hp_schema_asset_url($location_image);
            }
        }

        $candidates[] = hp_setting_url('default_og_image', 'assets/images/default-og-image.webp');

        $image_url = '';

        foreach ($candidates as $candidate) {
            $candidate = trim((string) $candidate);

            if ($candidate !== '') {
                $image_url = $candidate;
                break;
            }
        }

        return [
            'url' => $image_url,
            'alt' => hp_schema_page_name($page_title, hp_site_name()),
            'type' => hp_schema_image_type($image_url),
            /* Do not claim 1200x630 unless a dedicated social-card generator exists. */
            'width' => 0,
            'height' => 0,
        ];
    }
}

/*
 * Make paginated, public category pages self-canonical. Search and service
 * filters remain noindex and canonically point to the clean directory URL.
 */
if ($current_page > 1 && !$has_search_filter && !$has_service_filter) {
    $paged_canonical_args = array_merge($route_args, [
        'search' => '',
        'service' => '',
        'page' => (string) $current_page,
    ]);

    $canonical_url = hp_hospitals_url($paged_canonical_args);
}

$meta_keywords_parts = [];
$seo_location = hp_seo_location($division, $district, $thana);

if ($seo_location !== '') {
    $meta_keywords_parts[] = hp_seo_locale() === 'bn'
        ? $seo_location . ' হাসপাতাল'
        : 'hospitals in ' . $seo_location;
}

if ($hospital_type !== '') {
    $meta_keywords_parts[] = hp_hospital_type_label($hospital_type);
}

$meta_keywords_parts[] = hp_seo_locale() === 'bn' ? 'হাসপাতালের তালিকা' : 'hospital list';
$meta_keywords_parts[] = hp_seo_locale() === 'bn' ? 'বাংলাদেশের হাসপাতাল' : 'hospitals in Bangladesh';

$meta_keywords_parts = array_values(array_unique(array_filter(array_map(
    'hp_schema_text',
    $meta_keywords_parts
))));

$meta_keywords = implode(', ', $meta_keywords_parts);

$hospital_directory_og_image = hp_directory_og_image_details(
    $hospitals,
    $division,
    $district,
    $thana,
    $page_title
);

$og_image = (string) ($hospital_directory_og_image['url'] ?? '');
$og_image_alt = (string) ($hospital_directory_og_image['alt'] ?? '');
$og_image_type = (string) ($hospital_directory_og_image['type'] ?? '');
$og_image_width = (int) ($hospital_directory_og_image['width'] ?? 0);
$og_image_height = (int) ($hospital_directory_og_image['height'] ?? 0);
$og_title = $page_title;
$og_description = $meta_description;
$og_type = 'website';
$og_locale = hp_seo_locale() === 'bn' ? 'bn_BD' : 'en_US';
$twitter_card = $og_image !== '' ? 'summary_large_image' : 'summary';

$hospital_directory_schema = hp_build_directory_schema(
    $page_step,
    $division,
    $district,
    $thana,
    $hospital_type,
    $hospitals,
    $total_hospitals,
    $current_page,
    $filters,
    $page_title,
    $meta_description,
    $canonical_url,
    $og_image
);
