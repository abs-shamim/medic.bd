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
     * Use the lightweight existence query (LIMIT 1, stops at the first match) instead of
     * loading full doctor cards, hospital names, GROUP_CONCAT, rating sorting, pagination
     * data, or even a full COUNT(*) - this only ever needs to know "any match at all?".
     */
    static $availability_cache = [];

    $cache_key = md5(json_encode($filters));

    if (array_key_exists($cache_key, $availability_cache)) {
        return $availability_cache[$cache_key];
    }

    /*
     * Cross-request cache.
     * The "choose a specialty" step calls this once per specialty (dozens of
     * calls, each running the same chambers/hospitals-joined query as the
     * main doctor search, just with LIMIT 1) to decide which specialties are
     * clickable for the current district/thana - measured at ~2.5 seconds
     * total for one page view before this cache. Whether a district has any
     * doctor of a given specialty changes only when a doctor is added or
     * edited, not between two page views, so a short TTL is safe here.
     */
    if (!function_exists('medic_cache_remember')) {
        $availability_cache[$cache_key] = dp_doctors_exist($filters);

        return $availability_cache[$cache_key];
    }

    $lang = defined('CURRENT_LANG') ? CURRENT_LANG : '';
    $resolver = static function () use ($filters): bool {
        return dp_doctors_exist($filters);
    };

    $availability_cache[$cache_key] = (bool)medic_cache_remember('dp_has_doctors:' . $lang . ':' . $cache_key, 300, $resolver);

    return $availability_cache[$cache_key];
}

function dp_filter_divisions_with_doctors(array $divisions, array $specialty = []): array
{
    return array_values(array_filter($divisions, static function ($division) use ($specialty) {
        return dp_has_doctors([
            'division_id' => (int)($division['id'] ?? 0),
            'division_name' => (string)($division['name'] ?? ''),
            'district_id' => 0,
            'district_name' => '',
            'thana_id' => 0,
            'thana_name' => '',
            'specialty_id' => (int)($specialty['id'] ?? 0),
            'specialty_slug' => !empty($specialty) ? dp_row_slug($specialty) : '',
            'specialty_name' => (string)($specialty['name'] ?? ''),
            'search' => '',
        ]);
    }));
}

function dp_filter_districts_with_doctors(array $districts, array $specialty = []): array
{
    return array_values(array_filter($districts, static function ($district) use ($specialty) {
        return dp_has_doctors([
            'division_id' => 0,
            'division_name' => '',
            'district_id' => (int)($district['id'] ?? 0),
            'district_name' => (string)($district['name'] ?? ''),
            'thana_id' => 0,
            'thana_name' => '',
            'specialty_id' => (int)($specialty['id'] ?? 0),
            'specialty_slug' => !empty($specialty) ? dp_row_slug($specialty) : '',
            'specialty_name' => (string)($specialty['name'] ?? ''),
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

