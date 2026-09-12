<?php
/*
|--------------------------------------------------------------------------
| Dynamic Data Fetchers
|--------------------------------------------------------------------------
*/

function dp_get_divisions(): array
{
    global $pdo;

    if (!dp_table_exists('divisions')) {
        return [];
    }

    $name_col = dp_name_column('divisions');
    $select = ["id", "{$name_col} AS name"];

    if (dp_column_exists('divisions', 'name')) {
        $select[] = 'name AS name_raw';
    }

    if (dp_column_exists('divisions', 'name_bn')) {
        $select[] = 'name_bn';
    }

    if (dp_column_exists('divisions', 'name_en')) {
        $select[] = 'name_en';
    }

    if (dp_column_exists('divisions', 'slug')) {
        $select[] = 'slug';
    } else {
        $select[] = "'' AS slug";
    }

    if (dp_column_exists('divisions', 'image')) {
        $select[] = 'image';
    } else {
        $select[] = "'' AS image";
    }

    try {
        $stmt = $pdo->query("
            SELECT " . implode(', ', $select) . "
            FROM divisions
            ORDER BY {$name_col} ASC
        ");

        return $stmt->fetchAll();
    } catch (Throwable $e) {
        error_log('Division query failed: ' . $e->getMessage());
        return [];
    }
}

function dp_get_division_by_slug(string $slug): array
{
    global $pdo;

    $slug = dp_url_slug($slug);

    if ($slug === '' || !dp_table_exists('divisions')) {
        return [];
    }

    $name_col = dp_name_column('divisions');
    $select = ["id", "{$name_col} AS name"];

    if (dp_column_exists('divisions', 'slug')) {
        $select[] = 'slug';
    } else {
        $select[] = "'' AS slug";
    }

    if (dp_column_exists('divisions', 'image')) {
        $select[] = 'image';
    } else {
        $select[] = "'' AS image";
    }

    try {
        $rows = $pdo->query("
            SELECT " . implode(', ', $select) . "
            FROM divisions
            ORDER BY {$name_col} ASC
        ")->fetchAll();

        foreach ($rows as $row) {
            $row_slug = dp_row_slug($row);
            $division_name_slug = dp_url_slug((string)($row['name'] ?? '') . ' Division');

            if ($row_slug === $slug || $division_name_slug === $slug) {
                return $row;
            }
        }

        return [];
    } catch (Throwable $e) {
        error_log('Division slug lookup failed: ' . $e->getMessage());
        return [];
    }
}

function dp_get_districts_by_division_id(int $division_id): array
{
    global $pdo;

    if ($division_id <= 0 || !dp_table_exists('districts')) {
        return [];
    }

    $name_col = dp_name_column('districts');
    $select = ["id", "division_id", "{$name_col} AS name"];

    if (dp_column_exists('districts', 'name')) {
        $select[] = 'name AS name_raw';
    }

    if (dp_column_exists('districts', 'name_bn')) {
        $select[] = 'name_bn';
    }

    if (dp_column_exists('districts', 'name_en')) {
        $select[] = 'name_en';
    }

    if (dp_column_exists('districts', 'slug')) {
        $select[] = 'slug';
    } else {
        $select[] = "'' AS slug";
    }

    if (dp_column_exists('districts', 'image')) {
        $select[] = 'image';
    } else {
        $select[] = "'' AS image";
    }

    try {
        $stmt = $pdo->prepare("
            SELECT " . implode(', ', $select) . "
            FROM districts
            WHERE division_id = :division_id
            ORDER BY {$name_col} ASC
        ");
        $stmt->execute([':division_id' => $division_id]);

        return $stmt->fetchAll();
    } catch (Throwable $e) {
        error_log('District query failed: ' . $e->getMessage());
        return [];
    }
}

function dp_get_district_ids_by_division_id(int $division_id): array
{
    $ids = [];

    foreach (dp_get_districts_by_division_id($division_id) as $district) {
        $id = (int)($district['id'] ?? 0);

        if ($id > 0) {
            $ids[] = $id;
        }
    }

    return array_values(array_unique($ids));
}

function dp_get_district_by_slug(int $division_id, string $slug): array
{
    $slug = dp_url_slug($slug);

    if ($division_id <= 0 || $slug === '') {
        return [];
    }

    $districts = dp_get_districts_by_division_id($division_id);

    foreach ($districts as $district) {
        if (dp_row_slug($district) === $slug) {
            return $district;
        }
    }

    return [];
}

function dp_get_district_by_slug_any(string $slug): array
{
    global $pdo;

    $slug = dp_url_slug($slug);

    if ($slug === '' || !dp_table_exists('districts')) {
        return [];
    }

    $name_col = dp_name_column('districts');
    $select = ["id", "division_id", "{$name_col} AS name"];

    if (dp_column_exists('districts', 'slug')) {
        $select[] = 'slug';
    } else {
        $select[] = "'' AS slug";
    }

    if (dp_column_exists('districts', 'image')) {
        $select[] = 'image';
    } else {
        $select[] = "'' AS image";
    }

    try {
        $rows = $pdo->query("
            SELECT " . implode(', ', $select) . "
            FROM districts
            ORDER BY {$name_col} ASC
        ")->fetchAll();

        foreach ($rows as $row) {
            if (dp_row_slug($row) === $slug) {
                return $row;
            }
        }

        return [];
    } catch (Throwable $e) {
        error_log('Global district slug lookup failed: ' . $e->getMessage());
        return [];
    }
}

function dp_get_division_by_id(int $division_id): array
{
    global $pdo;

    if ($division_id <= 0 || !dp_table_exists('divisions')) {
        return [];
    }

    $name_col = dp_name_column('divisions');
    $select = ["id", "{$name_col} AS name"];

    if (dp_column_exists('divisions', 'slug')) {
        $select[] = 'slug';
    } else {
        $select[] = "'' AS slug";
    }

    if (dp_column_exists('divisions', 'image')) {
        $select[] = 'image';
    } else {
        $select[] = "'' AS image";
    }

    try {
        $stmt = $pdo->prepare("
            SELECT " . implode(', ', $select) . "
            FROM divisions
            WHERE id = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $division_id]);

        $row = $stmt->fetch();
        return $row ?: [];
    } catch (Throwable $e) {
        error_log('Division ID lookup failed: ' . $e->getMessage());
        return [];
    }
}

function dp_get_thanas_by_district_id(int $district_id): array
{
    global $pdo;

    if ($district_id <= 0 || !dp_table_exists('thanas')) {
        return [];
    }

    $name_col = dp_name_column('thanas');
    $select = ["id", "district_id", "{$name_col} AS name"];

    if (dp_column_exists('thanas', 'name')) {
        $select[] = 'name AS name_raw';
    }

    if (dp_column_exists('thanas', 'name_bn')) {
        $select[] = 'name_bn';
    }

    if (dp_column_exists('thanas', 'name_en')) {
        $select[] = 'name_en';
    }

    if (dp_column_exists('thanas', 'slug')) {
        $select[] = 'slug';
    } else {
        $select[] = "'' AS slug";
    }

    if (dp_column_exists('thanas', 'image')) {
        $select[] = 'image';
    } else {
        $select[] = "'' AS image";
    }

    try {
        $stmt = $pdo->prepare("
            SELECT " . implode(', ', $select) . "
            FROM thanas
            WHERE district_id = :district_id
            ORDER BY {$name_col} ASC
        ");
        $stmt->execute([':district_id' => $district_id]);

        return $stmt->fetchAll();
    } catch (Throwable $e) {
        error_log('Thana query failed: ' . $e->getMessage());
        return [];
    }
}


function dp_get_thana_by_slug(int $district_id, string $slug): array
{
    $slug = dp_url_slug($slug);

    if ($district_id <= 0 || $slug === '') {
        return [];
    }

    foreach (dp_get_thanas_by_district_id($district_id) as $thana) {
        if (dp_row_slug($thana) === $slug) {
            return $thana;
        }
    }

    return [];
}

function dp_get_specialties(): array
{
    global $pdo;

    $has_specialties_table = dp_table_exists('specialties');
    $has_alternate_names = $has_specialties_table
        && dp_column_exists('specialties', 'alternate_names');
    $has_alternate_names_bn = $has_specialties_table
        && dp_column_exists('specialties', 'alternate_names_bn');
    $has_image = $has_specialties_table
        && dp_column_exists('specialties', 'image');

    /*
     * The project-level get_specialties() helper may return the main specialty
     * fields only. Enrich those rows here so directory SEO always receives
     * alternate_names and alternate_names_bn when those columns exist.
     */
    $enrich_aliases = static function (array $items) use (
        $pdo,
        $has_specialties_table,
        $has_alternate_names,
        $has_alternate_names_bn,
        $has_image
    ): array {
        if (
            empty($items)
            || !$has_specialties_table
            || (!$has_alternate_names && !$has_alternate_names_bn && !$has_image)
        ) {
            return $items;
        }

        $ids = [];

        foreach ($items as $item) {
            $id = (int)($item['id'] ?? 0);

            if ($id > 0) {
                $ids[] = $id;
            }
        }

        $ids = array_values(array_unique($ids));

        if (empty($ids)) {
            return $items;
        }

        $select = ['id'];

        if ($has_alternate_names) {
            $select[] = 'alternate_names';
        }

        if ($has_alternate_names_bn) {
            $select[] = 'alternate_names_bn';
        }

        if ($has_image) {
            $select[] = 'image';
        }

        $placeholders = [];
        $params = [];

        foreach ($ids as $index => $id) {
            $placeholder = ':specialty_id_' . $index;
            $placeholders[] = $placeholder;
            $params[$placeholder] = $id;
        }

        try {
            $stmt = $pdo->prepare("
                SELECT " . implode(', ', $select) . "
                FROM specialties
                WHERE id IN (" . implode(', ', $placeholders) . ")
            ");
            $stmt->execute($params);

            $aliases_by_id = [];

            foreach ($stmt->fetchAll() as $row) {
                $aliases_by_id[(int)$row['id']] = $row;
            }

            foreach ($items as &$item) {
                $id = (int)($item['id'] ?? 0);
                $alias_row = $aliases_by_id[$id] ?? [];

                $item['alternate_names'] = trim((string)($alias_row['alternate_names'] ?? $item['alternate_names'] ?? ''));
                $item['alternate_names_bn'] = trim((string)($alias_row['alternate_names_bn'] ?? $item['alternate_names_bn'] ?? ''));
                $item['image'] = trim((string)($alias_row['image'] ?? $item['image'] ?? ''));
            }
            unset($item);
        } catch (Throwable $e) {
            error_log('Specialty alias enrichment failed: ' . $e->getMessage());
        }

        return $items;
    };

    $normalize_items = static function (array $items) use ($enrich_aliases): array {
        $items = array_values(array_filter($items, static function ($item) {
            return is_array($item) && !empty($item['name']);
        }));

        $items = $enrich_aliases($items);

        foreach ($items as &$item) {
            if (empty($item['name_raw']) && !empty($item['name'])) {
                $item['name_raw'] = $item['name'];
            }

            $item['alternate_names'] = trim((string)($item['alternate_names'] ?? ''));
            $item['alternate_names_bn'] = trim((string)($item['alternate_names_bn'] ?? ''));
            $item['image'] = trim((string)($item['image'] ?? ''));

            if (defined('CURRENT_LANG') && CURRENT_LANG === 'bn' && !empty($item['name_bn'])) {
                $item['name'] = $item['name_bn'];
            }
        }
        unset($item);

        return $items;
    };

    if (function_exists('get_specialties')) {
        try {
            $items = get_specialties();

            if (is_array($items)) {
                return $normalize_items($items);
            }
        } catch (Throwable $e) {
            error_log('get_specialties failed: ' . $e->getMessage());
        }
    }

    if (!$has_specialties_table) {
        return [];
    }

    $name_col = dp_name_column('specialties');
    $select = ["id", "{$name_col} AS name"];

    if (dp_column_exists('specialties', 'name')) {
        $select[] = 'name AS name_raw';
    }

    if (dp_column_exists('specialties', 'name_bn')) {
        $select[] = 'name_bn';
    }

    if ($has_alternate_names) {
        $select[] = 'alternate_names';
    } else {
        $select[] = "'' AS alternate_names";
    }

    if ($has_alternate_names_bn) {
        $select[] = 'alternate_names_bn';
    } else {
        $select[] = "'' AS alternate_names_bn";
    }

    if (dp_column_exists('specialties', 'slug')) {
        $select[] = 'slug';
    } else {
        $select[] = "'' AS slug";
    }

    if ($has_image) {
        $select[] = 'image';
    } else {
        $select[] = "'' AS image";
    }

    try {
        $stmt = $pdo->query("
            SELECT " . implode(', ', $select) . "
            FROM specialties
            ORDER BY {$name_col} ASC
        ");

        return $normalize_items($stmt->fetchAll());
    } catch (Throwable $e) {
        error_log('Specialty query failed: ' . $e->getMessage());
        return [];
    }
}

function dp_get_specialty_by_slug(string $slug): array
{
    $slug = dp_url_slug($slug);

    if ($slug === '') {
        return [];
    }

    foreach (dp_get_specialties() as $specialty) {
        if (dp_row_slug($specialty) === $slug) {
            return $specialty;
        }
    }

    return [];
}

/*
|--------------------------------------------------------------------------
| Doctor Query
|--------------------------------------------------------------------------
*/

function dp_address_like_value(string $value): string
{
    $value = trim($value);

    if ($value === '') {
        return '';
    }

    return '%' . $value . '%';
}

function dp_build_doctor_search_query(array $filters, int $limit = 10, int $offset = 0): array
{
    $where = [];
    $params = [];

    if (!dp_table_exists('doctors')) {
        return [
            'sql' => '',
            'params' => [],
        ];
    }

    $can_join_specialties = dp_table_exists('specialties')
        && dp_column_exists('doctors', 'specialty_id');

    $can_join_hospitals = dp_table_exists('hospitals')
        && dp_column_exists('doctors', 'hospital_id');

    $can_join_chambers = dp_table_exists('chambers')
        && $can_join_hospitals
        && dp_column_exists('chambers', 'doctor_id')
        && dp_column_exists('chambers', 'hospital_id');

    if (dp_column_exists('doctors', 'status')) {
        $where[] = "(d.status = 'active' OR d.status = 'published' OR d.status = '' OR d.status IS NULL)";
    }


    /*
     * Context Featured Doctor rule:
     * Step 4 and thana URL stay the same.
     * If a doctor is manually featured for this exact district/thana/specialty,
     * show that doctor even when the doctor has no chamber/hospital in that location.
     */
    $is_step_four_specialty_page = !empty($filters['district_id'])
        && (!empty($filters['specialty_id']) || !empty($filters['specialty_slug']));

    $context_featured_enabled = $is_step_four_specialty_page
        && !empty($filters['specialty_id'])
        && dp_table_exists('doctor_featured_contexts');

    if (!empty($filters['search'])) {
        $search = trim((string)$filters['search']);
        $search_slug = dp_url_slug($search);

        $name_parts = [];

        if (dp_column_exists('doctors', 'name')) {
            $name_parts[] = 'd.name LIKE :search_name';
            $params[':search_name'] = '%' . $search . '%';
        }

        if (dp_column_exists('doctors', 'name_bn')) {
            $name_parts[] = 'd.name_bn LIKE :search_name_bn';
            $params[':search_name_bn'] = '%' . $search . '%';
        }

        if ($search_slug !== '' && dp_column_exists('doctors', 'slug')) {
            $name_parts[] = 'd.slug LIKE :search_slug';
            $params[':search_slug'] = '%' . $search_slug . '%';
        }

        if (!empty($name_parts)) {
            $where[] = '(' . implode(' OR ', $name_parts) . ')';
        }
    }

    if (!empty($filters['specialty_id']) && $can_join_specialties) {
        if ($context_featured_enabled) {
            $where[] = '(d.specialty_id = :specialty_id OR dfc.id IS NOT NULL)';
        } else {
            $where[] = 'd.specialty_id = :specialty_id';
        }

        $params[':specialty_id'] = (int)$filters['specialty_id'];
    } elseif (!empty($filters['specialty_slug']) && $can_join_specialties) {
        $specialty_slug = trim((string)$filters['specialty_slug']);
        $specialty_name = dp_slug_to_name($specialty_slug);

        $specialty_parts = ['s.slug = :specialty_slug'];
        $params[':specialty_slug'] = $specialty_slug;

        if (dp_column_exists('specialties', 'name')) {
            $specialty_parts[] = 's.name LIKE :specialty_name';
            $params[':specialty_name'] = '%' . $specialty_name . '%';
        }

        if (dp_column_exists('specialties', 'name_bn')) {
            $specialty_parts[] = 's.name_bn LIKE :specialty_name_bn';
            $params[':specialty_name_bn'] = '%' . $specialty_name . '%';
        }

        if (dp_column_exists('specialties', 'alternate_names')) {
            $specialty_parts[] = 's.alternate_names LIKE :specialty_alternate_names';
            $params[':specialty_alternate_names'] = '%' . $specialty_name . '%';
        }

        if (dp_column_exists('specialties', 'alternate_names_bn')) {
            $specialty_parts[] = 's.alternate_names_bn LIKE :specialty_alternate_names_bn';
            $params[':specialty_alternate_names_bn'] = '%' . $specialty_name . '%';
        }

        $where[] = '(' . implode(' OR ', $specialty_parts) . ')';
    }

    if (empty($filters['district_id']) && !empty($filters['division_id'])) {
        $division_id = (int)$filters['division_id'];
        $division_parts = [];

        if ($division_id > 0 && $can_join_hospitals && dp_column_exists('hospitals', 'division_id')) {
            $division_parts[] = 'h.division_id = :division_id';
            $params[':division_id'] = $division_id;
        }

        if ($division_id > 0 && $can_join_hospitals && dp_column_exists('hospitals', 'district_id')) {
            $district_ids = dp_get_district_ids_by_division_id($division_id);
            $district_placeholders = [];

            foreach ($district_ids as $index => $district_id_for_division) {
                $placeholder = ':division_district_id_' . $index;
                $district_placeholders[] = $placeholder;
                $params[$placeholder] = (int)$district_id_for_division;
            }

            if (!empty($district_placeholders)) {
                $division_parts[] = 'h.district_id IN (' . implode(', ', $district_placeholders) . ')';
            }
        }

        if (!empty($division_parts)) {
            $where[] = '(' . implode(' OR ', $division_parts) . ')';
        }
    }

    if (!empty($filters['district_id'])) {
        $district_id = (int)$filters['district_id'];
        $district_name = trim((string)($filters['district_name'] ?? ''));

        $district_parts = [];

        /*
         * Doctor added location rule:
         * A doctor must also be shown when district is saved directly
         * in doctors.doctor_district_id / doctors.doctor_district / city.
         */
        if ($district_id > 0 && dp_column_exists('doctors', 'doctor_district_id')) {
            $district_parts[] = 'd.doctor_district_id = :doctor_location_district_id';
            $params[':doctor_location_district_id'] = $district_id;
        }

        if ($district_name !== '') {
            foreach (['doctor_district', 'doctor_district_name', 'district', 'district_name', 'city'] as $doctor_district_column) {
                if (dp_column_exists('doctors', $doctor_district_column)) {
                    $district_parts[] = 'd.' . $doctor_district_column . ' LIKE :doctor_location_district_name';
                    $params[':doctor_location_district_name'] = dp_address_like_value($district_name);
                    break;
                }
            }
        }

        if ($district_id > 0 && $can_join_hospitals && dp_column_exists('hospitals', 'district_id')) {
            $district_parts[] = 'h.district_id = :district_id';
            $params[':district_id'] = $district_id;
        }

        if ($district_name !== '' && $can_join_chambers && dp_column_exists('chambers', 'address')) {
            $district_parts[] = 'c.address LIKE :district_chamber_address';
            $params[':district_chamber_address'] = dp_address_like_value($district_name);
        }

        if ($district_name !== '' && $can_join_chambers && dp_column_exists('chambers', 'address_bn')) {
            $district_parts[] = 'c.address_bn LIKE :district_chamber_address_bn';
            $params[':district_chamber_address_bn'] = dp_address_like_value($district_name);
        }

        if ($district_name !== '' && $can_join_hospitals && dp_column_exists('hospitals', 'address')) {
            $district_parts[] = 'h.address LIKE :district_hospital_address';
            $params[':district_hospital_address'] = dp_address_like_value($district_name);
        }

        if ($district_name !== '' && $can_join_hospitals && dp_column_exists('hospitals', 'address_bn')) {
            $district_parts[] = 'h.address_bn LIKE :district_hospital_address_bn';
            $params[':district_hospital_address_bn'] = dp_address_like_value($district_name);
        }

        if ($district_name !== '' && $can_join_hospitals && dp_column_exists('hospitals', 'city')) {
            $district_parts[] = 'h.city LIKE :district_hospital_city';
            $params[':district_hospital_city'] = dp_address_like_value($district_name);
        }

        if (!$can_join_chambers && $district_name !== '' && dp_column_exists('doctors', 'city')) {
            $district_parts[] = 'd.city LIKE :district_doctor_city';
            $params[':district_doctor_city'] = dp_address_like_value($district_name);
        }

        if ($context_featured_enabled) {
            $district_parts[] = 'dfc.id IS NOT NULL';
        }

        if (!empty($district_parts)) {
            $where[] = '(' . implode(' OR ', $district_parts) . ')';
        }
    }

    if (!empty($filters['thana_id']) || !empty($filters['thana_name'])) {
        $thana_id = (int)($filters['thana_id'] ?? 0);
        $thana_name = trim((string)($filters['thana_name'] ?? ''));

        $thana_parts = [];

        /*
         * Doctor added location rule:
         * A doctor must also be shown when thana is saved directly
         * in doctors.doctor_thana_id / doctors.doctor_thana / area.
         */
        if ($thana_id > 0 && dp_column_exists('doctors', 'doctor_thana_id')) {
            $thana_parts[] = 'd.doctor_thana_id = :doctor_location_thana_id';
            $params[':doctor_location_thana_id'] = $thana_id;
        }

        if ($thana_name !== '') {
            foreach (['doctor_thana', 'doctor_thana_name', 'thana', 'thana_name', 'area'] as $doctor_thana_column) {
                if (dp_column_exists('doctors', $doctor_thana_column)) {
                    $thana_parts[] = 'd.' . $doctor_thana_column . ' LIKE :doctor_location_thana_name';
                    $params[':doctor_location_thana_name'] = dp_address_like_value($thana_name);
                    break;
                }
            }
        }

        if ($thana_id > 0 && $can_join_hospitals && dp_column_exists('hospitals', 'thana_id')) {
            $thana_parts[] = 'h.thana_id = :thana_id';
            $params[':thana_id'] = $thana_id;
        }

        if ($thana_name !== '' && $can_join_chambers && dp_column_exists('chambers', 'address')) {
            $thana_parts[] = 'c.address LIKE :thana_chamber_address';
            $params[':thana_chamber_address'] = dp_address_like_value($thana_name);
        }

        if ($thana_name !== '' && $can_join_chambers && dp_column_exists('chambers', 'address_bn')) {
            $thana_parts[] = 'c.address_bn LIKE :thana_chamber_address_bn';
            $params[':thana_chamber_address_bn'] = dp_address_like_value($thana_name);
        }

        if ($thana_name !== '' && $can_join_hospitals && dp_column_exists('hospitals', 'address')) {
            $thana_parts[] = 'h.address LIKE :thana_hospital_address';
            $params[':thana_hospital_address'] = dp_address_like_value($thana_name);
        }

        if ($thana_name !== '' && $can_join_hospitals && dp_column_exists('hospitals', 'address_bn')) {
            $thana_parts[] = 'h.address_bn LIKE :thana_hospital_address_bn';
            $params[':thana_hospital_address_bn'] = dp_address_like_value($thana_name);
        }

        if ($thana_name !== '' && $can_join_hospitals && dp_column_exists('hospitals', 'city')) {
            $thana_parts[] = 'h.city LIKE :thana_hospital_city';
            $params[':thana_hospital_city'] = dp_address_like_value($thana_name);
        }

        if ($context_featured_enabled) {
            $thana_parts[] = 'dfc.id IS NOT NULL';
        }

        if (!empty($thana_parts)) {
            $where[] = '(' . implode(' OR ', $thana_parts) . ')';
        }
    }


    $where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $select = ['d.*'];

    if ($can_join_specialties) {
        $select[] = 's.name AS specialty_name';
        $select[] = dp_column_exists('specialties', 'name_bn') ? 's.name_bn AS specialty_name_bn' : "'' AS specialty_name_bn";
    } else {
        $select[] = "'' AS specialty_name";
        $select[] = "'' AS specialty_name_bn";
    }

    if ($can_join_hospitals) {
        if ($can_join_chambers) {
            $select[] = "COALESCE(
                NULLIF(GROUP_CONCAT(DISTINCT NULLIF(h.name, '') ORDER BY c.sort_order ASC, c.id ASC SEPARATOR ', '), ''),
                NULLIF(ph.name, ''),
                ''
            ) AS hospital_name";

            if (dp_column_exists('hospitals', 'name_bn')) {
                $select[] = "COALESCE(
                    NULLIF(GROUP_CONCAT(DISTINCT NULLIF(h.name_bn, '') ORDER BY c.sort_order ASC, c.id ASC SEPARATOR ', '), ''),
                    NULLIF(ph.name_bn, ''),
                    ''
                ) AS hospital_name_bn";
            } else {
                $select[] = "'' AS hospital_name_bn";
            }
        } else {
            $select[] = 'h.name AS hospital_name';
            $select[] = dp_column_exists('hospitals', 'name_bn') ? 'h.name_bn AS hospital_name_bn' : "'' AS hospital_name_bn";
        }
    } else {
        $select[] = "'' AS hospital_name";
        $select[] = "'' AS hospital_name_bn";
    }

    if ($context_featured_enabled) {
        $select[] = "MAX(CASE WHEN dfc.id IS NOT NULL THEN 1 ELSE 0 END) AS context_is_featured";
        $select[] = "MIN(CASE
            WHEN dfc.id IS NOT NULL AND COALESCE(dfc.featured_position, 0) > 0 THEN dfc.featured_position
            WHEN dfc.id IS NOT NULL THEN 999999
            ELSE 999999
        END) AS context_featured_position";
    } else {
        $select[] = "0 AS context_is_featured";
        $select[] = "999999 AS context_featured_position";
    }

    $join = '';

    if ($can_join_specialties) {
        $join .= ' LEFT JOIN specialties s ON s.id = d.specialty_id ';
    }

    if ($can_join_chambers && $can_join_hospitals) {
        $join .= " LEFT JOIN chambers c ON c.doctor_id = d.id ";

        if (dp_column_exists('chambers', 'status')) {
            $join .= " AND (c.status = 'active' OR c.status = 'published' OR c.status = '' OR c.status IS NULL) ";
        }

        $join .= ' LEFT JOIN hospitals h ON h.id = c.hospital_id ';
        $join .= ' LEFT JOIN hospitals ph ON ph.id = d.hospital_id ';
    } elseif ($can_join_hospitals) {
        $join .= ' LEFT JOIN hospitals h ON h.id = d.hospital_id ';
    }

    if ($context_featured_enabled) {
        $params[':context_featured_district_id'] = (int)$filters['district_id'];
        $params[':context_featured_thana_id'] = (int)($filters['thana_id'] ?? 0);
        $params[':context_featured_specialty_id'] = (int)$filters['specialty_id'];

        $join .= " LEFT JOIN doctor_featured_contexts dfc
            ON dfc.doctor_id = d.id
            AND dfc.district_id = :context_featured_district_id
            AND dfc.thana_id = :context_featured_thana_id
            AND dfc.specialty_id = :context_featured_specialty_id
            AND dfc.status = 'active'
            AND (dfc.featured_until IS NULL OR dfc.featured_until >= CURDATE()) ";
    }

    $order = [];

    if (!empty($filters['search']) && dp_column_exists('doctors', 'name')) {
        $order[] = "CASE
            WHEN d.name = :search_exact THEN 0
            WHEN d.name LIKE :search_starts THEN 1
            WHEN d.name LIKE :search_contains THEN 2
            ELSE 3
        END ASC";

        $params[':search_exact'] = trim((string)$filters['search']);
        $params[':search_starts'] = trim((string)$filters['search']) . '%';
        $params[':search_contains'] = '%' . trim((string)$filters['search']) . '%';
    }

    /*
     * Sorting rule:
     * - Step 4 specialty doctor-list pages: context featured active doctors first, sorted by context position.
     * - Other pages: profile popularity (views), then rating and reviews.
     */
    if ($context_featured_enabled) {
        $order[] = 'context_is_featured DESC';
        $order[] = 'context_featured_position ASC';
    }

    if (dp_column_exists('doctors', 'views_count')) {
        $order[] = 'COALESCE(d.views_count, 0) DESC';
    }

    if (dp_column_exists('doctors', 'rating')) {
        $order[] = 'COALESCE(d.rating, 0) DESC';
    }

    if (dp_column_exists('doctors', 'reviews_count')) {
        $order[] = 'COALESCE(d.reviews_count, 0) DESC';
    }

    $order[] = 'd.id DESC';

    return [
        'sql' => "
            SELECT " . implode(', ', $select) . "
            FROM doctors d
            {$join}
            {$where_sql}
            GROUP BY d.id
            ORDER BY " . implode(', ', $order) . "
            LIMIT " . dp_safe_int($limit) . "
            OFFSET " . dp_safe_int($offset) . "
        ",
        'params' => $params,
    ];
}

function dp_run_doctor_query(array $query_parts): array
{
    global $pdo;

    if (empty($query_parts['sql'])) {
        return [];
    }

    try {
        $stmt = $pdo->prepare($query_parts['sql']);
        $stmt->execute($query_parts['params']);

        return $stmt->fetchAll();
    } catch (Throwable $e) {
        error_log('Doctor search query failed: ' . $e->getMessage());
        return [];
    }
}


function dp_build_doctor_count_query(array $filters): array
{
    $query = dp_build_doctor_search_query($filters, 1, 0);

    if (empty($query['sql'])) {
        return [
            'sql' => '',
            'params' => [],
        ];
    }

    $sql = (string)$query['sql'];
    $from_pos = strpos($sql, 'FROM doctors d');
    $group_pos = strpos($sql, 'GROUP BY d.id');

    if ($from_pos === false || $group_pos === false || $group_pos <= $from_pos) {
        return [
            'sql' => '',
            'params' => [],
        ];
    }

    $from_join_where = substr($sql, $from_pos, $group_pos - $from_pos);
    $params = $query['params'];

    /*
     * These parameters are only used in the ORDER BY search ranking.
     * Count query does not include that ORDER BY part, so remove them.
     */
    unset($params[':search_exact'], $params[':search_starts'], $params[':search_contains']);

    return [
        'sql' => "
            SELECT COUNT(*)
            FROM (
                SELECT d.id
                {$from_join_where}
                GROUP BY d.id
            ) matched_doctors
        ",
        'params' => $params,
    ];
}

/*
 * Same shape as dp_build_doctor_count_query(), but for callers that only
 * need to know whether ANY doctor matches (e.g. "does this division have
 * doctors, so should it show as a clickable option"), not the exact
 * count. Adding LIMIT 1 to the inner query lets MySQL stop scanning at
 * the first match instead of walking every matching row just to discard
 * the total - this is what dp_doctors_exist() below uses.
 */
function dp_build_doctor_exists_query(array $filters): array
{
    $query = dp_build_doctor_count_query($filters);

    if (empty($query['sql'])) {
        return $query;
    }

    $sql = (string)$query['sql'];
    $group_pos = strpos($sql, 'GROUP BY d.id');

    if ($group_pos === false) {
        return $query;
    }

    $insert_at = $group_pos + strlen('GROUP BY d.id');
    $sql = substr($sql, 0, $insert_at) . "\n                LIMIT 1" . substr($sql, $insert_at);

    return [
        'sql' => $sql,
        'params' => $query['params'],
    ];
}

function dp_doctors_exist(array $filters): bool
{
    global $pdo;

    $query = dp_build_doctor_exists_query($filters);

    if (empty($query['sql'])) {
        return false;
    }

    try {
        $stmt = $pdo->prepare($query['sql']);
        $stmt->execute($query['params']);

        return ((int)$stmt->fetchColumn()) > 0;
    } catch (Throwable $e) {
        error_log('Doctor existence query failed: ' . $e->getMessage());

        return false;
    }
}

function dp_get_doctors_count(array $filters): int
{
    global $pdo;

    $query = dp_build_doctor_count_query($filters);

    if (empty($query['sql'])) {
        return 0;
    }

    $resolver = static function () use ($pdo, $query): int {
        try {
            $stmt = $pdo->prepare($query['sql']);
            $stmt->execute($query['params']);

            return max(0, (int)$stmt->fetchColumn());
        } catch (Throwable $e) {
            error_log('Doctor count query failed: ' . $e->getMessage());
            return 0;
        }
    };

    /*
     * This count re-runs the same chambers/hospitals joins as the main
     * listing query just to total the pages, and visitors browsing the
     * same district/specialty combination hit it repeatedly. A 45-second
     * cache keeps pagination correct within a normal browsing session
     * while skipping that join+GROUP BY cost on every repeat view.
     */
    if (!function_exists('medic_cache_remember')) {
        return $resolver();
    }

    $cache_key = 'dp_doctors_count:' . (defined('CURRENT_LANG') ? CURRENT_LANG : '') . ':' . $query['sql'] . ':' . json_encode($query['params']);

    return (int)medic_cache_remember($cache_key, 45, $resolver);
}

function dp_clean_pagination_items(array $items, int $total_pages): array
{
    $clean_items = [];

    foreach ($items as $item) {
        if (is_int($item)) {
            if ($item >= 1 && $item <= $total_pages && !in_array($item, $clean_items, true)) {
                $clean_items[] = $item;
            }
        } elseif (is_string($item)) {
            $last_item = end($clean_items);

            if (is_string($last_item)) {
                continue;
            }

            $clean_items[] = $item;
        }
    }

    if (is_string(end($clean_items))) {
        array_pop($clean_items);
    }

    return $clean_items;
}

function dp_pagination_items_desktop(int $current_page, int $total_pages): array
{
    $current_page = max(1, $current_page);
    $total_pages = max(1, $total_pages);

    if ($total_pages <= 8) {
        return range(1, $total_pages);
    }

    /*
     * Desktop target:
     * Prev | 1 | 2 | 3 | 4 | ... | 17 | 18 | 19 | 20 | Next
     */
    if ($current_page <= 4) {
        $items = [1, 2, 3, 4, 'dots-right', $total_pages - 3, $total_pages - 2, $total_pages - 1, $total_pages];
    } elseif ($current_page >= ($total_pages - 3)) {
        $items = [1, 2, 3, 4, 'dots-left', $total_pages - 3, $total_pages - 2, $total_pages - 1, $total_pages];
    } else {
        $items = [1, 2, 'dots-left', $current_page - 1, $current_page, $current_page + 1, 'dots-right', $total_pages - 2, $total_pages - 1, $total_pages];
    }

    return dp_clean_pagination_items($items, $total_pages);
}

function dp_pagination_items_mobile(int $current_page, int $total_pages): array
{
    $current_page = max(1, $current_page);
    $total_pages = max(1, $total_pages);

    if ($total_pages <= 6) {
        return range(1, $total_pages);
    }

    /*
     * Mobile target:
     * Prev | 1 | 2 | 3 | ... | 19 | 20 | Next
     */
    if ($current_page <= 3) {
        $items = [1, 2, 3, 'dots-right', $total_pages - 1, $total_pages];
    } elseif ($current_page >= ($total_pages - 2)) {
        $items = [1, 2, 'dots-left', $total_pages - 2, $total_pages - 1, $total_pages];
    } else {
        $items = [1, 2, 'dots-left', $current_page, 'dots-right', $total_pages - 1, $total_pages];
    }

    return dp_clean_pagination_items($items, $total_pages);
}

function dp_get_doctors(array $filters, int $limit = 10, int $offset = 0): array
{
    $query = dp_build_doctor_search_query($filters, $limit, $offset);
    $results = dp_run_doctor_query($query);

    if (!empty($results)) {
        return $results;
    }

    return [];
}

