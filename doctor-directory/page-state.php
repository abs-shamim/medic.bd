<?php
/*
|--------------------------------------------------------------------------
| Page State
|--------------------------------------------------------------------------
| URL rule (district-first flow):
| /doctors/                                      => Division list + all doctors
| /doctors/{division-slug}/                      => District list + doctors from selected division
| /doctors/{district-slug}/                      => Specialty list + doctors from selected district
| /doctors/{district-slug}/{specialty}/          => Doctor list from selected district and specialty
| /doctors/{district-slug}/{thana}/              => Specialty list + doctors from selected district and thana
| /doctors/{district-slug}/{thana}/{specialty}/  => Doctor list from selected district, thana and specialty
|
| URL rule (specialty-first flow, e.g. clicking a specialty on the homepage):
| /doctors/{specialty}/                          => Division list scoped to this specialty
| /doctors/{division-slug}/{specialty}/          => District list scoped to this division + specialty
| /doctors/{district-slug}/{specialty}/          => Doctor list (same URL/step as the district-first flow)
| /doctors/{district-slug}/{thana}/{specialty}/  => Doctor list (same URL/step as the district-first flow)
|--------------------------------------------------------------------------
*/

$segments = dp_current_doctors_segments();

$first_slug = $segments[0] ?? '';
$second_slug = $segments[1] ?? '';
$third_slug = $segments[2] ?? '';

$division = [];
$district = [];
$thana = [];
$specialty = [];
$page_step = 'division';

if ($first_slug !== '' && $second_slug === '') {
    if (substr($first_slug, -9) === '-division') {
        $division_candidate = dp_get_division_by_slug($first_slug);

        if (!empty($division_candidate)) {
            $division = $division_candidate;
            $page_step = 'district';
        } else {
            $page_step = 'not_found';
        }
    } else {
        $district_candidate = dp_get_district_by_slug_any($first_slug);

        if (!empty($district_candidate)) {
            $district = $district_candidate;
            $division = dp_get_division_by_id((int)($district['division_id'] ?? 0));
            $page_step = 'specialty';
        } else {
            $division_candidate = dp_get_division_by_slug($first_slug);

            if (!empty($division_candidate)) {
                $division = $division_candidate;
                $page_step = 'district';
            } else {
                $specialty_candidate = dp_get_specialty_by_slug($first_slug);

                if (!empty($specialty_candidate)) {
                    /*
                     * Specialty-first flow, step 1:
                     * /doctors/{specialty-slug}/
                     * Shows the division list scoped to this specialty
                     * (plus the nationwide doctor list for this specialty,
                     * same as the plain division step already does).
                     */
                    $specialty = $specialty_candidate;
                    $page_step = 'division';
                } else {
                    $page_step = 'not_found';
                }
            }
        }
    }
} elseif ($first_slug !== '' && $second_slug !== '' && $third_slug === '' && substr($first_slug, -9) === '-division') {
    /*
     * Specialty-first flow, step 2:
     * /doctors/{division-slug}/{specialty-slug}/
     * Shows the district list scoped to this division + specialty.
     */
    $division_candidate = dp_get_division_by_slug($first_slug);
    $specialty_candidate = dp_get_specialty_by_slug($second_slug);

    if (!empty($division_candidate) && !empty($specialty_candidate)) {
        $division = $division_candidate;
        $specialty = $specialty_candidate;
        $page_step = 'district';
    } else {
        $page_step = 'not_found';
    }
} elseif ($first_slug !== '' && $second_slug !== '' && $third_slug === '') {
    /*
     * Two segments can be:
     * /doctors/{district}/{specialty}/
     * /doctors/{district}/{thana}/
     *
     * Priority:
     * 1. Find the first segment as district.
     * 2. If the second segment is a specialty, show district + specialty doctors.
     * 3. If the second segment is a thana under that district, show district + thana doctors.
     */
    $district = dp_get_district_by_slug_any($first_slug);

    if (!empty($district)) {
        $division = dp_get_division_by_id((int)($district['division_id'] ?? 0));

        $specialty_candidate = dp_get_specialty_by_slug($second_slug);
        $thana_candidate = dp_get_thana_by_slug((int)$district['id'], $second_slug);

        if (!empty($specialty_candidate)) {
            $specialty = $specialty_candidate;
            $page_step = 'doctor_list';
        } elseif (!empty($thana_candidate)) {
            /*
             * Example:
             * /doctors/dhaka/dhanmondi
             *
             * This page shows:
             * 1. Specialty options available in this thana/area.
             * 2. Doctor list from this thana/area.
             */
            $thana = $thana_candidate;
            $page_step = 'thana_specialty';
        } else {
            $page_step = 'not_found';
        }
    } else {
        $page_step = 'not_found';
    }
} elseif ($first_slug !== '' && $second_slug !== '' && $third_slug !== '') {
    /*
     * Three segments can be:
     * New thana URL: /doctors/{district}/{thana}/{specialty}/
     * Legacy URL:    /doctors/{division}/{district}/{specialty}/
     */
    $legacy_division = dp_get_division_by_slug($first_slug);
    $legacy_district = !empty($legacy_division) ? dp_get_district_by_slug((int)$legacy_division['id'], $second_slug) : [];
    $legacy_specialty = dp_get_specialty_by_slug($third_slug);

    if (!empty($legacy_district) && !empty($legacy_specialty)) {
        redirect(dp_doctors_url([
            'district_slug' => dp_row_slug($legacy_district),
            'specialty_slug' => dp_row_slug($legacy_specialty),
            'search' => trim($_GET['search'] ?? ($_GET['serch'] ?? '')),
        ]));
    }

    $district = dp_get_district_by_slug_any($first_slug);

    if (!empty($district)) {
        $division = dp_get_division_by_id((int)($district['division_id'] ?? 0));
        $thana = dp_get_thana_by_slug((int)$district['id'], $second_slug);
        $specialty = dp_get_specialty_by_slug($third_slug);
    }

    if (!empty($district) && !empty($thana) && !empty($specialty)) {
        $page_step = 'doctor_list';
    } else {
        $page_step = 'not_found';
    }
}

$search_value = trim($_GET['search'] ?? ($_GET['serch'] ?? ''));

if (!empty($_GET['serch']) && empty($_GET['search'])) {
    $_GET['search'] = $_GET['serch'];
    unset($_GET['serch']);

    $query = $_GET;
    $query['search'] = $search_value;

    $clean_query = http_build_query(array_filter($query, static function ($value) {
        return trim((string)$value) !== '';
    }));

    $current_path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
    redirect(front_url(trim((string)$current_path, '/')) . ($clean_query ? '?' . $clean_query : ''));
}

$specialty_slug_value = !empty($specialty) ? dp_row_slug($specialty) : '';

$filters = [
    'search' => $search_value,
    'division_id' => (int)($division['id'] ?? 0),
    'division_name' => (string)($division['name'] ?? ''),
    'district_id' => (int)($district['id'] ?? 0),
    'district_name' => (string)($district['name'] ?? ''),
    'thana_id' => (int)($thana['id'] ?? 0),
    'thana_name' => (string)($thana['name'] ?? ''),
    'specialty_id' => (int)($specialty['id'] ?? 0),
    'specialty_slug' => $specialty_slug_value,
    'specialty_name' => (string)($specialty['name'] ?? ''),
];

/*
 * Performance rule:
 * Do not build every step list on every request.
 * Previously this page loaded divisions, districts, specialties and thanas together,
 * and each filter used dp_has_doctors(), which can create many SQL queries.
 * Now only the list needed for the current step is loaded.
 * Doctor list/card/search/pagination/featured context logic stays unchanged.
 */
$divisions = [];
$districts = [];
$all_specialties = [];
$specialties = [];
$thanas = [];
$available_thanas = [];

if ($page_step === 'division') {
    $divisions = dp_filter_divisions_with_doctors(dp_get_divisions(), $specialty);
}

if ($page_step === 'district' && !empty($division)) {
    $districts = dp_filter_districts_with_doctors(dp_get_districts_by_division_id((int)$division['id']), $specialty);
}

if (in_array($page_step, ['specialty', 'thana_specialty'], true) && !empty($district)) {
    $all_specialties = dp_get_specialties();
    $specialties = dp_filter_specialties_with_doctors($all_specialties, $district, $thana);
}

if (in_array($page_step, ['thana_specialty', 'doctor_list'], true) && !empty($district)) {
    $thanas = dp_get_thanas_by_district_id((int)$district['id']);
}

if ($page_step === 'doctor_list' && !empty($district) && !empty($specialty)) {
    $available_thanas = dp_filter_thanas_with_doctors($thanas, $district, $specialty);
}

$doctors_per_page = dp_setting_int('doctors_per_page', 10, 1, 100);
$current_page = dp_current_page();
$total_doctors = $page_step !== 'not_found' ? dp_get_doctors_count($filters) : 0;
$total_pages = max(1, (int)ceil($total_doctors / $doctors_per_page));

if ($current_page > $total_pages) {
    $current_page = $total_pages;
}

$doctor_offset = ($current_page - 1) * $doctors_per_page;
$doctors = $page_step !== 'not_found' ? dp_get_doctors($filters, $doctors_per_page, $doctor_offset) : [];

$has_next_page = $current_page < $total_pages;
$has_previous_page = $current_page > 1;
$pagination_items_desktop = dp_pagination_items_desktop($current_page, $total_pages);
$pagination_items_mobile = dp_pagination_items_mobile($current_page, $total_pages);

