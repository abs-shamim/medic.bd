<?php
/*
|--------------------------------------------------------------------------
| Availability Helpers
|--------------------------------------------------------------------------
| These helpers make every step dynamic. A division, district, specialty, or
| thana is shown only when matching doctor data exists for that exact context.
|--------------------------------------------------------------------------
*/

function dp_has_doctors(array $filters): bool
{
    /*
     * Fast request-level cache.
     * Availability checks are used while building division/district/specialty/thana lists.
     * Use the lightweight count query instead of loading full doctor cards, hospital names,
     * GROUP_CONCAT, rating sorting and pagination data.
     */
    static $availability_cache = [];

    $cache_key = md5(json_encode($filters));

    if (array_key_exists($cache_key, $availability_cache)) {
        return $availability_cache[$cache_key];
    }

    $availability_cache[$cache_key] = dp_get_doctors_count($filters) > 0;

    return $availability_cache[$cache_key];
}

function dp_filter_divisions_with_doctors(array $divisions): array
{
    return array_values(array_filter($divisions, static function ($division) {
        return dp_has_doctors([
            'division_id' => (int)($division['id'] ?? 0),
            'division_name' => (string)($division['name'] ?? ''),
            'district_id' => 0,
            'district_name' => '',
            'thana_id' => 0,
            'thana_name' => '',
            'specialty_id' => 0,
            'specialty_slug' => '',
            'specialty_name' => '',
            'search' => '',
        ]);
    }));
}

function dp_filter_districts_with_doctors(array $districts): array
{
    return array_values(array_filter($districts, static function ($district) {
        return dp_has_doctors([
            'division_id' => 0,
            'division_name' => '',
            'district_id' => (int)($district['id'] ?? 0),
            'district_name' => (string)($district['name'] ?? ''),
            'thana_id' => 0,
            'thana_name' => '',
            'specialty_id' => 0,
            'specialty_slug' => '',
            'specialty_name' => '',
            'search' => '',
        ]);
    }));
}

function dp_filter_specialties_with_doctors(array $specialties, array $district, array $thana = []): array
{
    return array_values(array_filter($specialties, static function ($specialty) use ($district, $thana) {
        return dp_has_doctors([
            'division_id' => 0,
            'division_name' => '',
            'district_id' => (int)($district['id'] ?? 0),
            'district_name' => (string)($district['name'] ?? ''),
            'thana_id' => (int)($thana['id'] ?? 0),
            'thana_name' => (string)($thana['name'] ?? ''),
            'specialty_id' => (int)($specialty['id'] ?? 0),
            'specialty_slug' => dp_row_slug($specialty),
            'specialty_name' => (string)($specialty['name'] ?? ''),
            'search' => '',
        ]);
    }));
}

function dp_filter_thanas_with_doctors(array $thanas, array $district, array $specialty): array
{
    return array_values(array_filter($thanas, static function ($thana) use ($district, $specialty) {
        return dp_has_doctors([
            'division_id' => 0,
            'division_name' => '',
            'district_id' => (int)($district['id'] ?? 0),
            'district_name' => (string)($district['name'] ?? ''),
            'thana_id' => (int)($thana['id'] ?? 0),
            'thana_name' => (string)($thana['name'] ?? ''),
            'specialty_id' => (int)($specialty['id'] ?? 0),
            'specialty_slug' => dp_row_slug($specialty),
            'specialty_name' => (string)($specialty['name'] ?? ''),
            'search' => '',
        ]);
    }));
}

