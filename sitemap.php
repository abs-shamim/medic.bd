<?php
declare(strict_types=1);

/**
 * MediCare Premium Fast XML Sitemap Generator
 *
 * File: sitemap.php
 * Place in the website root beside index.php, route.php and .htaccess.
 *
 * Sitemap groups:
 * - sitemap-main.xml
 * - sitemap-doctors-N.xml                 (single doctor profiles)
 * - sitemap-hospitals-N.xml               (single hospital profiles)
 * - sitemap-specialties-N.xml             (specialty article pages)
 * - sitemap-doctor-directory-N.xml       (bulk-validated doctor directory URLs)
 * - sitemap-hospital-directory-N.xml     (bulk-validated hospital directory URLs)
 *
 * Each generated XML file contains a maximum of 1,000 <url> entries.
 * English and Bangla pages are emitted separately, so each file contains
 * up to 500 logical records.
 */

require_once __DIR__ . '/includes/functions.php';

header('Content-Type: application/xml; charset=UTF-8');
header('Cache-Control: public, max-age=3600');
header('X-Robots-Tag: noindex, follow');

$type = strtolower(trim((string) ($_GET['type'] ?? 'index')));
$page = max(1, (int) ($_GET['page'] ?? 1));

const SM_RECORDS_PER_FILE = 500;

/*
|--------------------------------------------------------------------------
| XML Helpers
|--------------------------------------------------------------------------
*/

function sm_escape($value): string
{
    return htmlspecialchars((string) $value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
}

function sm_date(?string $value = null): string
{
    if ($value !== null && trim($value) !== '') {
        $timestamp = strtotime($value);

        if ($timestamp !== false) {
            return gmdate('Y-m-d', $timestamp);
        }
    }

    return gmdate('Y-m-d');
}

function sm_path_url(string $path = '', string $lang = 'en'): string
{
    $path = trim($path, '/');

    if ($lang === 'bn') {
        $path = $path === '' ? 'bn' : 'bn/' . $path;
    }

    return site_url($path);
}

function sm_start_index(): void
{
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    echo '<?xml-stylesheet type="text/xsl" href="' . sm_escape(site_url('sitemap.xsl')) . '"?>' . "\n";
    echo '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
}

function sm_end_index(): void
{
    echo "</sitemapindex>\n";
}

function sm_start_urlset(): void
{
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    echo '<?xml-stylesheet type="text/xsl" href="' . sm_escape(site_url('sitemap.xsl')) . '"?>' . "\n";
    echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">' . "\n";
}

function sm_end_urlset(): void
{
    echo "</urlset>\n";
}

function sm_emit_sitemap(string $path, ?string $lastmod = null): void
{
    echo "  <sitemap>\n";
    echo '    <loc>' . sm_escape(site_url($path)) . "</loc>\n";
    echo '    <lastmod>' . sm_date($lastmod) . "</lastmod>\n";
    echo "  </sitemap>\n";
}

function sm_emit_alternates(string $path): void
{
    echo '    <xhtml:link rel="alternate" hreflang="en" href="' . sm_escape(sm_path_url($path, 'en')) . '" />' . "\n";
    echo '    <xhtml:link rel="alternate" hreflang="bn" href="' . sm_escape(sm_path_url($path, 'bn')) . '" />' . "\n";
    echo '    <xhtml:link rel="alternate" hreflang="x-default" href="' . sm_escape(sm_path_url($path, 'en')) . '" />' . "\n";
}

function sm_emit_url(
    string $path,
    ?string $lastmod = null,
    string $changefreq = 'weekly',
    string $priority = '0.70'
): void {
    foreach (['en', 'bn'] as $lang) {
        echo "  <url>\n";
        echo '    <loc>' . sm_escape(sm_path_url($path, $lang)) . "</loc>\n";
        echo '    <lastmod>' . sm_date($lastmod) . "</lastmod>\n";
        echo '    <changefreq>' . sm_escape($changefreq) . "</changefreq>\n";
        echo '    <priority>' . sm_escape($priority) . "</priority>\n";
        sm_emit_alternates($path);
        echo "  </url>\n";
    }
}

/*
|--------------------------------------------------------------------------
| Database Helpers
|--------------------------------------------------------------------------
*/

function sm_table_exists(string $table): bool
{
    global $pdo;

    static $cache = [];

    if ($table === '') {
        return false;
    }

    if (array_key_exists($table, $cache)) {
        return $cache[$table];
    }

    try {
        $statement = $pdo->prepare(
            'SELECT COUNT(*)
             FROM INFORMATION_SCHEMA.TABLES
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = :table'
        );
        $statement->execute([':table' => $table]);

        $cache[$table] = (int) $statement->fetchColumn() > 0;
    } catch (Throwable $exception) {
        $cache[$table] = false;
    }

    return $cache[$table];
}

function sm_column_exists(string $table, string $column): bool
{
    global $pdo;

    static $cache = [];

    $cache_key = $table . '.' . $column;

    if ($table === '' || $column === '') {
        return false;
    }

    if (array_key_exists($cache_key, $cache)) {
        return $cache[$cache_key];
    }

    try {
        $statement = $pdo->prepare(
            'SELECT COUNT(*)
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = :table
               AND COLUMN_NAME = :column'
        );
        $statement->execute([
            ':table' => $table,
            ':column' => $column,
        ]);

        $cache[$cache_key] = (int) $statement->fetchColumn() > 0;
    } catch (Throwable $exception) {
        $cache[$cache_key] = false;
    }

    return $cache[$cache_key];
}

function sm_fetch_all(string $sql): array
{
    global $pdo;

    try {
        $statement = $pdo->query($sql);

        return $statement ? ($statement->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
    } catch (Throwable $exception) {
        error_log('Sitemap query failed: ' . $exception->getMessage());

        return [];
    }
}

function sm_lastmod_sql(string $alias, string $table): string
{
    $available = [];

    foreach (['updated_at', 'created_at'] as $column) {
        if (sm_column_exists($table, $column)) {
            $available[] = $alias . '.' . $column;
        }
    }

    if (empty($available)) {
        return 'NULL';
    }

    if (count($available) === 1) {
        return $available[0];
    }

    return 'COALESCE(' . implode(', ', $available) . ')';
}

function sm_doctor_status_sql(string $alias = 'd'): string
{
    if (!sm_column_exists('doctors', 'status')) {
        return '';
    }

    return " AND (
        {$alias}.status = 'active'
        OR {$alias}.status = 'published'
        OR {$alias}.status = ''
        OR {$alias}.status IS NULL
    )";
}

function sm_hospital_status_sql(string $alias = 'h'): string
{
    if (!sm_column_exists('hospitals', 'status')) {
        return '';
    }

    return " AND {$alias}.status = 'active'";
}

function sm_specialty_status_sql(string $alias = 's'): string
{
    if (!sm_column_exists('specialties', 'status')) {
        return '';
    }

    return " AND {$alias}.status = 'active'";
}

function sm_slug(string $value): string
{
    $value = trim(rawurldecode($value));

    if ($value === '') {
        return '';
    }

    return rawurlencode($value);
}

function sm_add_record(array &$records, string $path, ?string $lastmod, string $priority): void
{
    $path = trim($path, '/');

    if ($path === '') {
        return;
    }

    if (!isset($records[$path])) {
        $records[$path] = [
            'path' => $path,
            'lastmod' => $lastmod,
            'priority' => $priority,
        ];

        return;
    }

    $current_time = strtotime((string) ($records[$path]['lastmod'] ?? '')) ?: 0;
    $new_time = strtotime((string) $lastmod) ?: 0;

    if ($new_time > $current_time) {
        $records[$path]['lastmod'] = $lastmod;
    }

    if ((float) $priority > (float) ($records[$path]['priority'] ?? 0)) {
        $records[$path]['priority'] = $priority;
    }
}


function sm_add_listing_records(
    array &$records,
    string $path,
    int $total_results,
    int $per_page,
    ?string $lastmod,
    string $priority
): void {
    if ($total_results <= 0) {
        return;
    }

    $per_page = max(1, $per_page);

    sm_add_record($records, $path, $lastmod, $priority);

    $total_pages = (int) ceil($total_results / $per_page);

    /*
     * Page 1 is the clean canonical URL. Add page 2+ only when those
     * pages contain real results. This can create thousands of valid,
     * crawlable directory URLs without adding empty pagination pages.
     */
    for ($listing_page = 2; $listing_page <= $total_pages; $listing_page++) {
        sm_add_record(
            $records,
            $path . '?page=' . $listing_page,
            $lastmod,
            (string) max(0.50, (float) $priority - 0.08)
        );
    }
}

function sm_records_page(array $records, int $page): array
{
    $records = array_values($records);
    $offset = ($page - 1) * SM_RECORDS_PER_FILE;

    return array_slice($records, $offset, SM_RECORDS_PER_FILE);
}

function sm_records_total_pages(array $records): int
{
    return max(1, (int) ceil(count($records) / SM_RECORDS_PER_FILE));
}

/*
|--------------------------------------------------------------------------
| Single Doctor Profiles
|--------------------------------------------------------------------------
*/

function sm_doctor_profile_records(): array
{
    $records = [];

    if (!sm_table_exists('doctors') || !sm_column_exists('doctors', 'slug')) {
        return $records;
    }

    $lastmod = sm_lastmod_sql('d', 'doctors');

    $rows = sm_fetch_all("
        SELECT d.slug, {$lastmod} AS lastmod
        FROM doctors d
        WHERE d.slug IS NOT NULL
          AND d.slug <> ''
          " . sm_doctor_status_sql('d') . "
        ORDER BY d.id DESC
    ");

    foreach ($rows as $row) {
        $slug = sm_slug((string) ($row['slug'] ?? ''));

        if ($slug !== '') {
            sm_add_record($records, 'doctor/' . $slug, $row['lastmod'] ?? null, '0.85');
        }
    }

    return $records;
}

/*
|--------------------------------------------------------------------------
| Single Hospital Profiles
|--------------------------------------------------------------------------
*/

function sm_hospital_profile_records(): array
{
    $records = [];

    if (!sm_table_exists('hospitals') || !sm_column_exists('hospitals', 'slug')) {
        return $records;
    }

    $lastmod = sm_lastmod_sql('h', 'hospitals');

    $rows = sm_fetch_all("
        SELECT h.slug, {$lastmod} AS lastmod
        FROM hospitals h
        WHERE h.slug IS NOT NULL
          AND h.slug <> ''
          " . sm_hospital_status_sql('h') . "
        ORDER BY h.id DESC
    ");

    foreach ($rows as $row) {
        $slug = sm_slug((string) ($row['slug'] ?? ''));

        if ($slug !== '') {
            sm_add_record($records, 'hospital/' . $slug, $row['lastmod'] ?? null, '0.85');
        }
    }

    return $records;
}

/*
|--------------------------------------------------------------------------
| Specialty Article Pages
|--------------------------------------------------------------------------
|
| Public URLs:
| /specialty/{slug}
| /bn/specialty/{slug}
|--------------------------------------------------------------------------
*/

function sm_specialty_article_records(): array
{
    $records = [];

    if (
        !sm_table_exists('specialties')
        || !sm_column_exists('specialties', 'slug')
    ) {
        return $records;
    }

    $lastmod = sm_lastmod_sql('s', 'specialties');

    $rows = sm_fetch_all("
        SELECT
            s.slug,
            {$lastmod} AS lastmod
        FROM specialties s
        WHERE s.slug IS NOT NULL
          AND s.slug <> ''
          " . sm_specialty_status_sql('s') . "
        ORDER BY s.slug ASC
    ");

    foreach ($rows as $row) {
        $slug = sm_slug((string) ($row['slug'] ?? ''));

        if ($slug !== '') {
            sm_add_record(
                $records,
                'specialty/' . $slug,
                $row['lastmod'] ?? null,
                '0.80'
            );
        }
    }

    return $records;
}

/*
|--------------------------------------------------------------------------
| Doctor Directory
|--------------------------------------------------------------------------
|
| Canonical public URLs:
| /doctors/{division}
| /doctors/{district}
| /doctors/{specialty}
| /doctors/{district}/{thana}
| /doctors/{district}/{specialty}
| /doctors/{district}/{thana}/{specialty}
|--------------------------------------------------------------------------
*/

/*
|--------------------------------------------------------------------------
| Fast Doctor Directory Sitemap Records
|--------------------------------------------------------------------------
|
| This does not run one dp_get_doctors_count() query for every URL.
| It uses bulk SQL to collect only contexts that already have real matching
| doctors. Therefore every generated URL has at least one real doctor result,
| while sitemap.xml stays fast enough for shared hosting.
|--------------------------------------------------------------------------
*/

function sm_doctor_directory_records(): array
{
    $records = [];

    if (
        !sm_table_exists('doctors')
        || !sm_table_exists('districts')
        || !sm_table_exists('specialties')
    ) {
        return $records;
    }

    $doctor_status = sm_column_exists('doctors', 'status')
        ? "(d.status = 'active' OR d.status = 'published' OR d.status = '' OR d.status IS NULL)"
        : '1 = 1';

    $hospital_status = sm_column_exists('hospitals', 'status')
        ? "(h.status = 'active' OR h.status = 'published' OR h.status = '' OR h.status IS NULL)"
        : '1 = 1';

    $chamber_status = sm_column_exists('chambers', 'status')
        ? "(c.status = 'active' OR c.status = 'published' OR c.status = '' OR c.status IS NULL)"
        : '1 = 1';

    $direct_source = '';
    if (
        sm_column_exists('doctors', 'doctor_district_id')
        && sm_column_exists('doctors', 'specialty_id')
    ) {
        $direct_thana = sm_column_exists('doctors', 'doctor_thana_id')
            ? 'COALESCE(d.doctor_thana_id, 0)'
            : '0';

        $direct_source = "
            SELECT
                d.id AS doctor_id,
                d.doctor_district_id AS district_id,
                {$direct_thana} AS thana_id,
                d.specialty_id
            FROM doctors d
            WHERE {$doctor_status}
              AND d.doctor_district_id > 0
              AND d.specialty_id > 0
        ";
    }

    $hospital_source = '';
    if (
        sm_table_exists('hospitals')
        && sm_column_exists('doctors', 'hospital_id')
        && sm_column_exists('doctors', 'specialty_id')
        && sm_column_exists('hospitals', 'district_id')
    ) {
        $hospital_thana = sm_column_exists('hospitals', 'thana_id')
            ? 'COALESCE(h.thana_id, 0)'
            : '0';

        $hospital_source = "
            SELECT
                d.id AS doctor_id,
                h.district_id,
                {$hospital_thana} AS thana_id,
                d.specialty_id
            FROM doctors d
            INNER JOIN hospitals h ON h.id = d.hospital_id
            WHERE {$doctor_status}
              AND {$hospital_status}
              AND h.district_id > 0
              AND d.specialty_id > 0
        ";
    }

    $chamber_source = '';
    if (
        sm_table_exists('chambers')
        && sm_table_exists('hospitals')
        && sm_column_exists('chambers', 'doctor_id')
        && sm_column_exists('chambers', 'hospital_id')
        && sm_column_exists('doctors', 'specialty_id')
        && sm_column_exists('hospitals', 'district_id')
    ) {
        $chamber_thana = sm_column_exists('hospitals', 'thana_id')
            ? 'COALESCE(h.thana_id, 0)'
            : '0';

        $chamber_source = "
            SELECT
                d.id AS doctor_id,
                h.district_id,
                {$chamber_thana} AS thana_id,
                d.specialty_id
            FROM chambers c
            INNER JOIN doctors d ON d.id = c.doctor_id
            INNER JOIN hospitals h ON h.id = c.hospital_id
            WHERE {$doctor_status}
              AND {$chamber_status}
              AND {$hospital_status}
              AND h.district_id > 0
              AND d.specialty_id > 0
        ";
    }

    $featured_source = '';
    if (
        sm_table_exists('doctor_featured_contexts')
        && sm_column_exists('doctor_featured_contexts', 'doctor_id')
        && sm_column_exists('doctor_featured_contexts', 'district_id')
        && sm_column_exists('doctor_featured_contexts', 'specialty_id')
    ) {
        $featured_thana = sm_column_exists('doctor_featured_contexts', 'thana_id')
            ? 'COALESCE(dfc.thana_id, 0)'
            : '0';

        $featured_status = sm_column_exists('doctor_featured_contexts', 'status')
            ? "dfc.status = 'active'"
            : '1 = 1';

        $featured_until = sm_column_exists('doctor_featured_contexts', 'featured_until')
            ? "AND (dfc.featured_until IS NULL OR dfc.featured_until >= CURDATE())"
            : '';

        $featured_source = "
            SELECT
                d.id AS doctor_id,
                dfc.district_id,
                {$featured_thana} AS thana_id,
                dfc.specialty_id
            FROM doctor_featured_contexts dfc
            INNER JOIN doctors d ON d.id = dfc.doctor_id
            WHERE {$doctor_status}
              AND {$featured_status}
              {$featured_until}
              AND dfc.district_id > 0
              AND dfc.specialty_id > 0
        ";
    }

    $sources = array_values(array_filter([
        $direct_source,
        $hospital_source,
        $chamber_source,
        $featured_source,
    ]));

    if (empty($sources)) {
        return $records;
    }

    $context_sql = implode("
UNION ALL
", $sources);

    /*
     * One bulk query returns every valid location + specialty context.
     * A row exists only when an active doctor is actually linked to it.
     */
    $rows = sm_fetch_all("
        SELECT DISTINCT
            source_context.district_id,
            source_context.thana_id,
            source_context.specialty_id,
            districts.slug AS district_slug,
            divisions.slug AS division_slug,
            thanas.slug AS thana_slug,
            specialties.slug AS specialty_slug
        FROM (
            {$context_sql}
        ) AS source_context
        INNER JOIN districts ON districts.id = source_context.district_id
        INNER JOIN divisions ON divisions.id = districts.division_id
        INNER JOIN specialties ON specialties.id = source_context.specialty_id
        LEFT JOIN thanas ON thanas.id = source_context.thana_id
        WHERE districts.slug IS NOT NULL
          AND districts.slug <> ''
          AND specialties.slug IS NOT NULL
          AND specialties.slug <> ''
    ");

    foreach ($rows as $row) {
        $division_slug = sm_slug((string) ($row['division_slug'] ?? ''));
        $district_slug = sm_slug((string) ($row['district_slug'] ?? ''));
        $thana_slug = sm_slug((string) ($row['thana_slug'] ?? ''));
        $specialty_slug = sm_slug((string) ($row['specialty_slug'] ?? ''));

        if ($district_slug === '' || $specialty_slug === '') {
            continue;
        }

        /*
         * Division pages require a suffix because the live router uses it
         * to distinguish a division from a district with the same slug.
         */
        if ($division_slug !== '') {
            sm_add_record($records, 'doctors/' . $division_slug . '-division', null, '0.74');
        }

        sm_add_record($records, 'doctors/' . $district_slug, null, '0.78');
        sm_add_record($records, 'doctors/' . $specialty_slug, null, '0.80');
        sm_add_record(
            $records,
            'doctors/' . $district_slug . '/' . $specialty_slug,
            null,
            '0.82'
        );

        if ($thana_slug !== '') {
            sm_add_record(
                $records,
                'doctors/' . $district_slug . '/' . $thana_slug,
                null,
                '0.76'
            );

            sm_add_record(
                $records,
                'doctors/' . $district_slug . '/' . $thana_slug . '/' . $specialty_slug,
                null,
                '0.84'
            );
        }
    }

    return $records;
}

/*
|--------------------------------------------------------------------------
| Hospital Directory
|--------------------------------------------------------------------------
|
| Canonical public URLs:
| /hospitals/{division}
| /hospitals/{district}
| /hospitals/{type}
| /hospitals/{district}/{thana}
| /hospitals/{district}/{type}
| /hospitals/{district}/{thana}/{type}
|--------------------------------------------------------------------------
*/

function sm_hospital_type_slug(string $type): string
{
    $type = trim($type);

    $allowed = [
        'Private Hospital',
        'Government Hospital',
        'Medical College Hospital',
        'Specialized Hospital',
        'General Hospital',
        'Diagnostic Center',
        'Clinic',
        'Eye Hospital',
        'Dental Hospital',
        'Cardiac Hospital',
        'Cancer Hospital',
        'Mother and Child Hospital',
        'Rehabilitation Center',
    ];

    if (!in_array($type, $allowed, true)) {
        return '';
    }

    $slug = strtolower($type);
    $slug = preg_replace('/[^a-z0-9]+/i', '-', $slug);

    return trim((string) $slug, '-');
}

/*
|--------------------------------------------------------------------------
| Fast Hospital Directory Sitemap Records
|--------------------------------------------------------------------------
|
| This uses one bulk query for active hospital contexts instead of running
| a separate COUNT query for every directory URL.
|--------------------------------------------------------------------------
*/

function sm_hospital_directory_records(): array
{
    $records = [];

    if (
        !sm_table_exists('hospitals')
        || !sm_table_exists('districts')
        || !sm_table_exists('divisions')
        || !sm_column_exists('hospitals', 'district_id')
    ) {
        return $records;
    }

    $hospital_status = sm_column_exists('hospitals', 'status')
        ? "(h.status = 'active' OR h.status = 'published' OR h.status = '' OR h.status IS NULL)"
        : '1 = 1';

    $hospital_thana = sm_column_exists('hospitals', 'thana_id')
        ? 'COALESCE(h.thana_id, 0)'
        : '0';

    $hospital_type = sm_column_exists('hospitals', 'type')
        ? 'COALESCE(h.type, \'\')'
        : "''";

    $rows = sm_fetch_all("
        SELECT DISTINCT
            h.district_id,
            {$hospital_thana} AS thana_id,
            {$hospital_type} AS hospital_type,
            districts.slug AS district_slug,
            divisions.slug AS division_slug,
            thanas.slug AS thana_slug
        FROM hospitals h
        INNER JOIN districts ON districts.id = h.district_id
        INNER JOIN divisions ON divisions.id = districts.division_id
        LEFT JOIN thanas ON thanas.id = {$hospital_thana}
        WHERE {$hospital_status}
          AND h.district_id > 0
          AND districts.slug IS NOT NULL
          AND districts.slug <> ''
    ");

    foreach ($rows as $row) {
        $division_slug = sm_slug((string) ($row['division_slug'] ?? ''));
        $district_slug = sm_slug((string) ($row['district_slug'] ?? ''));
        $thana_slug = sm_slug((string) ($row['thana_slug'] ?? ''));
        $type_slug = sm_hospital_type_slug((string) ($row['hospital_type'] ?? ''));

        if ($district_slug === '') {
            continue;
        }

        if ($division_slug !== '') {
            sm_add_record($records, 'hospitals/' . $division_slug . '-division', null, '0.74');
        }

        sm_add_record($records, 'hospitals/' . $district_slug, null, '0.78');

        if ($type_slug !== '') {
            sm_add_record($records, 'hospitals/' . $type_slug, null, '0.72');

            sm_add_record(
                $records,
                'hospitals/' . $district_slug . '/' . $type_slug,
                null,
                '0.82'
            );
        }

        if ($thana_slug !== '') {
            sm_add_record(
                $records,
                'hospitals/' . $district_slug . '/' . $thana_slug,
                null,
                '0.76'
            );

            if ($type_slug !== '') {
                sm_add_record(
                    $records,
                    'hospitals/' . $district_slug . '/' . $thana_slug . '/' . $type_slug,
                    null,
                    '0.84'
                );
            }
        }
    }

    return $records;
}

/*
|--------------------------------------------------------------------------
| Output Routes
|--------------------------------------------------------------------------
*/

if ($type === 'index') {
    $doctor_profiles = sm_doctor_profile_records();
    $hospital_profiles = sm_hospital_profile_records();
    $specialty_articles = sm_specialty_article_records();
    $doctor_directory = sm_doctor_directory_records();
    $hospital_directory = sm_hospital_directory_records();

    sm_start_index();

    sm_emit_sitemap('sitemap-main.xml');

    for ($number = 1; $number <= sm_records_total_pages($doctor_profiles); $number++) {
        sm_emit_sitemap('sitemap-doctors-' . $number . '.xml');
    }

    for ($number = 1; $number <= sm_records_total_pages($hospital_profiles); $number++) {
        sm_emit_sitemap('sitemap-hospitals-' . $number . '.xml');
    }

    for ($number = 1; $number <= sm_records_total_pages($specialty_articles); $number++) {
        sm_emit_sitemap('sitemap-specialties-' . $number . '.xml');
    }

    for ($number = 1; $number <= sm_records_total_pages($doctor_directory); $number++) {
        sm_emit_sitemap('sitemap-doctor-directory-' . $number . '.xml');
    }

    for ($number = 1; $number <= sm_records_total_pages($hospital_directory); $number++) {
        sm_emit_sitemap('sitemap-hospital-directory-' . $number . '.xml');
    }

    sm_end_index();
    exit;
}

if ($type === 'main') {
    sm_start_urlset();

    sm_emit_url('', null, 'daily', '1.0');
    sm_emit_url('doctors', null, 'daily', '0.90');
    sm_emit_url('hospitals', null, 'daily', '0.90');
    sm_emit_url('specialties', null, 'weekly', '0.80');
    sm_emit_url('contact', null, 'monthly', '0.50');

    sm_end_urlset();
    exit;
}

if ($type === 'doctors') {
    sm_start_urlset();

    foreach (sm_records_page(sm_doctor_profile_records(), $page) as $record) {
        sm_emit_url(
            (string) $record['path'],
            $record['lastmod'] ?? null,
            'weekly',
            (string) ($record['priority'] ?? '0.85')
        );
    }

    sm_end_urlset();
    exit;
}

if ($type === 'hospitals') {
    sm_start_urlset();

    foreach (sm_records_page(sm_hospital_profile_records(), $page) as $record) {
        sm_emit_url(
            (string) $record['path'],
            $record['lastmod'] ?? null,
            'weekly',
            (string) ($record['priority'] ?? '0.85')
        );
    }

    sm_end_urlset();
    exit;
}

if ($type === 'specialties') {
    sm_start_urlset();

    foreach (sm_records_page(sm_specialty_article_records(), $page) as $record) {
        sm_emit_url(
            (string) $record['path'],
            $record['lastmod'] ?? null,
            'weekly',
            (string) ($record['priority'] ?? '0.80')
        );
    }

    sm_end_urlset();
    exit;
}

if ($type === 'doctor-directory') {
    sm_start_urlset();

    foreach (sm_records_page(sm_doctor_directory_records(), $page) as $record) {
        sm_emit_url(
            (string) $record['path'],
            $record['lastmod'] ?? null,
            'weekly',
            (string) ($record['priority'] ?? '0.70')
        );
    }

    sm_end_urlset();
    exit;
}

if ($type === 'hospital-directory') {
    sm_start_urlset();

    foreach (sm_records_page(sm_hospital_directory_records(), $page) as $record) {
        sm_emit_url(
            (string) $record['path'],
            $record['lastmod'] ?? null,
            'weekly',
            (string) ($record['priority'] ?? '0.70')
        );
    }

    sm_end_urlset();
    exit;
}

/*
|--------------------------------------------------------------------------
| Legacy Combined Directory Sitemap
|--------------------------------------------------------------------------
| This preserves old sitemap-locations.xml URLs.
|--------------------------------------------------------------------------
*/
if ($type === 'locations') {
    $legacy_records = [];

    foreach (sm_doctor_directory_records() as $record) {
        sm_add_record(
            $legacy_records,
            (string) $record['path'],
            $record['lastmod'] ?? null,
            (string) ($record['priority'] ?? '0.70')
        );
    }

    foreach (sm_hospital_directory_records() as $record) {
        sm_add_record(
            $legacy_records,
            (string) $record['path'],
            $record['lastmod'] ?? null,
            (string) ($record['priority'] ?? '0.70')
        );
    }

    sm_start_urlset();

    foreach (sm_records_page($legacy_records, $page) as $record) {
        sm_emit_url(
            (string) $record['path'],
            $record['lastmod'] ?? null,
            'weekly',
            (string) ($record['priority'] ?? '0.70')
        );
    }

    sm_end_urlset();
    exit;
}

http_response_code(404);

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<error>Invalid sitemap type.</error>';
exit;
