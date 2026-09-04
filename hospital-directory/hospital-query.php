<?php
/**
 * Hospital directory list and filter queries.
 * Priority stays: Featured → Verified → Rating → Newest.
 */

function hp_build_hospital_query_parts(array $filters): array
{
    global $lang;

    $where = [];
    $params = [];

    if (!hp_table_exists('hospitals')) {
        return [
            'where_sql' => '',
            'order_sql' => '',
            'params' => [],
        ];
    }

    if (hp_column_exists('hospitals', 'status')) {
        $where[] = "h.status = 'active'";
    }

    if (!empty($filters['search'])) {
        $search = trim((string)$filters['search']);
        $search_slug = hp_url_slug($search);
        $search_parts = [];

        $search_columns = $lang === 'bn'
            ? ['name_bn', 'name', 'description_bn', 'description']
            : ['name', 'description'];

        foreach ($search_columns as $index => $column) {
            if (!hp_column_exists('hospitals', $column)) {
                continue;
            }

            $placeholder = ':search_' . $index;
            $search_parts[] = 'h.' . $column . ' LIKE ' . $placeholder;
            $params[$placeholder] = '%' . $search . '%';
        }

        if ($search_slug !== '' && hp_column_exists('hospitals', 'slug')) {
            $search_parts[] = 'h.slug LIKE :search_slug';
            $params[':search_slug'] = '%' . $search_slug . '%';
        }

        if (!empty($search_parts)) {
            $where[] = '(' . implode(' OR ', $search_parts) . ')';
        }
    }

    if (!empty($filters['division_id']) && empty($filters['district_id'])) {
        $division_id = (int)$filters['division_id'];
        $division_parts = [];

        if ($division_id > 0 && hp_column_exists('hospitals', 'division_id')) {
            $division_parts[] = 'h.division_id = :division_id';
            $params[':division_id'] = $division_id;
        }

        if ($division_id > 0 && hp_column_exists('hospitals', 'district_id')) {
            $district_ids = hp_get_district_ids_by_division_id($division_id);
            $placeholders = [];

            foreach ($district_ids as $index => $district_id_for_division) {
                $placeholder = ':division_district_' . $index;
                $placeholders[] = $placeholder;
                $params[$placeholder] = $district_id_for_division;
            }

            if (!empty($placeholders)) {
                $division_parts[] = 'h.district_id IN (' . implode(',', $placeholders) . ')';
            }
        }

        if (!empty($division_parts)) {
            $where[] = '(' . implode(' OR ', $division_parts) . ')';
        }
    }

    if (!empty($filters['district_id']) && hp_column_exists('hospitals', 'district_id')) {
        $where[] = 'h.district_id = :district_id';
        $params[':district_id'] = (int)$filters['district_id'];
    }

    if (!empty($filters['thana_id']) && hp_column_exists('hospitals', 'thana_id')) {
        $where[] = 'h.thana_id = :thana_id';
        $params[':thana_id'] = (int)$filters['thana_id'];
    }

    if (!empty($filters['type']) && hp_column_exists('hospitals', 'type')) {
        $where[] = 'h.type = :type';
        $params[':type'] = (string)$filters['type'];
    }

    if (!empty($filters['service'])) {
        $service = trim((string)$filters['service']);
        $service_parts = [];
        $index = 1;

        $service_columns = $lang === 'bn'
            ? ['services_bn', 'facilities_bn', 'description_bn', 'type_bn', 'services', 'facilities', 'description', 'type']
            : ['services', 'facilities', 'description', 'type'];

        foreach ($service_columns as $column) {
            if (hp_column_exists('hospitals', $column)) {
                $placeholder = ':service_' . $index;
                $service_parts[] = "h.{$column} LIKE {$placeholder}";
                $params[$placeholder] = '%' . $service . '%';
                $index++;
            }
        }

        $flag_map = [
            'Emergency' => 'emergency_available',
            'Ambulance' => 'ambulance_available',
            'ICU' => 'icu_available',
            'CCU' => 'ccu_available',
            'NICU' => 'nicu_available',
            'Pharmacy' => 'pharmacy_available',
            'Blood Bank' => 'blood_bank_available',
            'CT Scan' => 'ct_scan_available',
            'MRI' => 'mri_available',
            'X-Ray' => 'xray_available',
            'Lab' => 'lab_available',
            'Dental' => 'dental_unit_available',
            'Eye' => 'eye_unit_available',
            'Cardiac' => 'cardiac_unit_available',
            'Cancer' => 'cancer_unit_available',
        ];

        foreach ($flag_map as $label => $column) {
            if ((stripos($label, $service) !== false || stripos($service, $label) !== false) && hp_column_exists('hospitals', $column)) {
                $service_parts[] = "h.{$column} = 1";
            }
        }

        if (!empty($service_parts)) {
            $where[] = '(' . implode(' OR ', $service_parts) . ')';
        }
    }

    $where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $order = [];

    if (!empty($filters['search']) && hp_column_exists('hospitals', 'name')) {
        $order_name_column = $lang === 'bn' && hp_column_exists('hospitals', 'name_bn')
            ? 'name_bn'
            : 'name';

        $order[] = "CASE
            WHEN h.{$order_name_column} = :search_exact THEN 0
            WHEN h.{$order_name_column} LIKE :search_starts THEN 1
            WHEN h.{$order_name_column} LIKE :search_contains THEN 2
            ELSE 3
        END ASC";
        $params[':search_exact'] = trim((string)$filters['search']);
        $params[':search_starts'] = trim((string)$filters['search']) . '%';
        $params[':search_contains'] = '%' . trim((string)$filters['search']) . '%';
    }

    if (hp_column_exists('hospitals', 'is_featured')) {
        $order[] = 'h.is_featured DESC';
    }

    if (hp_column_exists('hospitals', 'is_verified')) {
        $order[] = 'h.is_verified DESC';
    }

    if (hp_column_exists('hospitals', 'views_count')) {
        $order[] = 'COALESCE(h.views_count, 0) DESC';
    }

    if (hp_column_exists('hospitals', 'rating')) {
        $order[] = 'h.rating DESC';
    }

    $order[] = 'h.id DESC';

    return [
        'where_sql' => $where_sql,
        'order_sql' => implode(', ', $order),
        'params' => $params,
    ];
}

function hp_build_hospital_search_query(array $filters, int $limit = 10, int $offset = 0): array
{
    if (!hp_table_exists('hospitals')) {
        return ['sql' => '', 'params' => []];
    }

    $parts = hp_build_hospital_query_parts($filters);
    $limit = hp_safe_limit($limit);
    $offset = max(0, (int)$offset);

    return [
        'sql' => "
            SELECT h.*
            FROM hospitals h
            {$parts['where_sql']}
            ORDER BY {$parts['order_sql']}
            LIMIT {$limit} OFFSET {$offset}
        ",
        'params' => $parts['params'],
    ];
}

function hp_build_hospital_count_query(array $filters): array
{
    if (!hp_table_exists('hospitals')) {
        return ['sql' => '', 'params' => []];
    }

    $parts = hp_build_hospital_query_parts($filters);

    $count_params = $parts['params'];

    // These parameters are used only by the relevance ORDER BY clause,
    // which is intentionally omitted from COUNT(*).
    unset($count_params[':search_exact'], $count_params[':search_starts'], $count_params[':search_contains']);

    return [
        'sql' => "
            SELECT COUNT(*)
            FROM hospitals h
            {$parts['where_sql']}
        ",
        'params' => $count_params,
    ];
}

function hp_run_hospital_query(array $query_parts): array
{
    global $pdo;

    if (empty($query_parts['sql'])) {
        return [];
    }

    try {
        $stmt = $pdo->prepare($query_parts['sql']);
        $stmt->execute($query_parts['params']);

        $rows = $stmt->fetchAll();

        return array_map('hp_localize_hospital_row', $rows);
    } catch (Throwable $e) {
        error_log('Hospital search query failed: ' . $e->getMessage());
        return [];
    }
}

function hp_get_hospitals(array $filters, int $limit = 10, int $offset = 0): array
{
    return hp_run_hospital_query(hp_build_hospital_search_query($filters, $limit, $offset));
}

function hp_get_hospitals_count(array $filters): int
{
    global $pdo;

    $query_parts = hp_build_hospital_count_query($filters);

    if (empty($query_parts['sql'])) {
        return 0;
    }

    try {
        $stmt = $pdo->prepare($query_parts['sql']);
        $stmt->execute($query_parts['params']);

        return (int)$stmt->fetchColumn();
    } catch (Throwable $e) {
        error_log('Hospital count query failed: ' . $e->getMessage());
        return 0;
    }
}

function hp_has_hospitals(array $filters): bool
{
    return !empty(hp_get_hospitals($filters, 1));
}

function hp_filter_divisions_with_hospitals(array $divisions): array
{
    return array_values(array_filter($divisions, static function ($division) {
        return hp_has_hospitals([
            'division_id' => (int)($division['id'] ?? 0),
            'district_id' => 0,
            'thana_id' => 0,
            'type' => '',
            'service' => '',
            'search' => '',
        ]);
    }));
}

function hp_filter_districts_with_hospitals(array $districts): array
{
    return array_values(array_filter($districts, static function ($district) {
        return hp_has_hospitals([
            'division_id' => 0,
            'district_id' => (int)($district['id'] ?? 0),
            'thana_id' => 0,
            'type' => '',
            'service' => '',
            'search' => '',
        ]);
    }));
}

function hp_filter_thanas_with_hospitals(array $thanas, array $district): array
{
    return array_values(array_filter($thanas, static function ($thana) use ($district) {
        return hp_has_hospitals([
            'division_id' => 0,
            'district_id' => (int)($district['id'] ?? 0),
            'thana_id' => (int)($thana['id'] ?? 0),
            'type' => '',
            'service' => '',
            'search' => '',
        ]);
    }));
}

function hp_filter_types_with_hospitals(array $types, array $district = [], array $thana = []): array
{
    return array_values(array_filter($types, static function ($type) use ($district, $thana) {
        return hp_has_hospitals([
            'division_id' => 0,
            'district_id' => (int)($district['id'] ?? 0),
            'thana_id' => (int)($thana['id'] ?? 0),
            'type' => (string)$type,
            'service' => '',
            'search' => '',
        ]);
    }));
}
