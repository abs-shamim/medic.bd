<?php
/**
 * Hospital directory URL state and pagination data.
 */

/*
|--------------------------------------------------------------------------
| Page State
|--------------------------------------------------------------------------
*/

$segments = hp_current_hospitals_segments();
$first_slug = $segments[0] ?? '';
$second_slug = $segments[1] ?? '';
$third_slug = $segments[2] ?? '';

$division = [];
$district = [];
$thana = [];
$hospital_type = '';
$page_step = 'division';

if ($first_slug !== '' && $second_slug === '') {
    $type_candidate = hp_hospital_type_from_slug($first_slug);

    if ($type_candidate !== '') {
        $hospital_type = $type_candidate;
        $page_step = 'hospital_list';
    } elseif (substr($first_slug, -9) === '-division') {
        $division = hp_get_division_by_slug($first_slug);
        $page_step = !empty($division) ? 'district' : 'not_found';
    } else {
        $district_candidate = hp_get_district_by_slug_any($first_slug);

        if (!empty($district_candidate)) {
            $district = $district_candidate;
            $division = hp_get_division_by_id((int)($district['division_id'] ?? 0));
            $page_step = 'thana_type';
        } else {
            $division_candidate = hp_get_division_by_slug($first_slug);

            if (!empty($division_candidate)) {
                $division = $division_candidate;
                $page_step = 'district';
            } else {
                $page_step = 'not_found';
            }
        }
    }
} elseif ($first_slug !== '' && $second_slug !== '' && $third_slug === '') {
    $district = hp_get_district_by_slug_any($first_slug);

    if (!empty($district)) {
        $division = hp_get_division_by_id((int)($district['division_id'] ?? 0));

        $type_candidate = hp_hospital_type_from_slug($second_slug);
        $thana_candidate = hp_get_thana_by_slug((int)$district['id'], $second_slug);

        if ($type_candidate !== '') {
            $hospital_type = $type_candidate;
            $page_step = 'hospital_list';
        } elseif (!empty($thana_candidate)) {
            $thana = $thana_candidate;
            $page_step = 'thana_type';
        } else {
            $page_step = 'not_found';
        }
    } else {
        $page_step = 'not_found';
    }
} elseif ($first_slug !== '' && $second_slug !== '' && $third_slug !== '') {
    $district = hp_get_district_by_slug_any($first_slug);

    if (!empty($district)) {
        $division = hp_get_division_by_id((int)($district['division_id'] ?? 0));
        $thana = hp_get_thana_by_slug((int)$district['id'], $second_slug);
        $hospital_type = hp_hospital_type_from_slug($third_slug);

        if (!empty($thana) && $hospital_type !== '') {
            $page_step = 'hospital_list';
        } else {
            $page_step = 'not_found';
        }
    } else {
        $page_step = 'not_found';
    }
}

$search_value = trim($_GET['search'] ?? ($_GET['serch'] ?? ''));
$service_value = trim($_GET['service'] ?? '');

if (!empty($_GET['serch']) && empty($_GET['search'])) {
    $_GET['search'] = $_GET['serch'];
    unset($_GET['serch']);

    $query = $_GET;
    $query['search'] = $search_value;

    $clean_query = http_build_query(array_filter($query, static function ($value) {
        return trim((string)$value) !== '';
    }));

    $current_route = function_exists('current_route')
        ? trim((string)current_route(), '/')
        : trim((string)parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/');

    if (hp_starts_with($current_route, 'bn/')) {
        $current_route = trim(substr($current_route, 3), '/');
    }

    redirect(hp_front_url($current_route . ($clean_query ? '?' . $clean_query : '')));
}

$filters = [
    'search' => $search_value,
    'service' => $service_value,
    'division_id' => (int)($division['id'] ?? 0),
    'district_id' => (int)($district['id'] ?? 0),
    'thana_id' => (int)($thana['id'] ?? 0),
    'type' => $hospital_type,
];

$divisions = hp_filter_divisions_with_hospitals(hp_get_divisions());
$districts = !empty($division) ? hp_filter_districts_with_hospitals(hp_get_districts_by_division_id((int)$division['id'])) : [];
$all_thanas = !empty($district) ? hp_get_thanas_by_district_id((int)$district['id']) : [];
$thanas = !empty($district) ? hp_filter_thanas_with_hospitals($all_thanas, $district) : [];
$hospital_types = hp_filter_types_with_hospitals(hp_hospital_types(), $district, $thana);

$per_page = hp_setting_int('hospitals_per_page', 10, 1, 100);
$current_page = max(1, (int)($_GET['page'] ?? 1));
$total_hospitals = $page_step !== 'not_found' ? hp_get_hospitals_count($filters) : 0;
$total_pages = $total_hospitals > 0 ? (int)ceil($total_hospitals / $per_page) : 1;

if ($current_page > $total_pages) {
    $current_page = $total_pages;
}

$offset = ($current_page - 1) * $per_page;
$hospitals = $page_step !== 'not_found' ? hp_get_hospitals($filters, $per_page, $offset) : [];
