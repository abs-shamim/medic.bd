<?php
declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Doctor Social Post
|--------------------------------------------------------------------------
| File: admin/doctor-social-post.php
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/../includes/social-post-helper.php';

/*
|--------------------------------------------------------------------------
| Specialty Hashtag Mapping
|--------------------------------------------------------------------------
| The mapping file keeps English, specialty-aware, platform-specific hashtags
| in one reusable location for English and Bangla campaigns.
|--------------------------------------------------------------------------
*/
$medic_specialty_hashtags_file = __DIR__ . '/../includes/specialty-hashtags.php';

if (is_file($medic_specialty_hashtags_file)) {
    require_once $medic_specialty_hashtags_file;
}

require_admin();

if (function_exists('require_admin_permission')) {
    require_admin_permission('doctors.view');
}

/*
|--------------------------------------------------------------------------
| Local compatibility helpers
|--------------------------------------------------------------------------
*/
if (!function_exists('medic_social_buffer_ready')) {
    function medic_social_buffer_ready(): bool
    {
        if (function_exists('medic_buffer_has_api_key')) {
            return medic_buffer_has_api_key();
        }

        if (function_exists('medic_buffer_is_configured')) {
            return medic_buffer_is_configured();
        }

        return false;
    }
}

if (!function_exists('medic_social_doctor_caption_fallback')) {
    function medic_social_doctor_caption_fallback(array $doctor): array
    {
        $name = trim((string) ($doctor['name'] ?? 'Doctor'));
        $degree = trim((string) ($doctor['degree'] ?? ''));
        $designation = trim((string) ($doctor['designation'] ?? ''));
        $specialty = trim((string) ($doctor['specialty_name'] ?? ''));
        $hospital = trim((string) (
            $doctor['primary_hospital']
            ?? $doctor['hospital_name']
            ?? ''
        ));
        $profile_url = trim((string) ($doctor['profile_url'] ?? ''));

        $professional = implode(', ', array_filter([$degree, $designation, $specialty]));
        $professional = $professional !== '' ? $professional : 'Specialist doctor';

        $facebook = "Meet Dr. {$name}.\n\n";
        $facebook .= $professional . ".\n";
        if ($hospital !== '') {
            $facebook .= "Consultation location: {$hospital}.\n";
        }
        $facebook .= $profile_url !== '' ? "\nView profile and appointment details:\n{$profile_url}" : '';
        $facebook .= "\n\n#Doctor #Healthcare #MedicBD";

        $instagram = "Dr. {$name}\n{$professional}";
        if ($hospital !== '') {
            $instagram .= "\n{$hospital}";
        }
        $instagram .= $profile_url !== '' ? "\n\n{$profile_url}" : '';
        $instagram .= "\n\n#DoctorProfile #Healthcare #Bangladesh #MedicBD";

        $linkedin = "Professional profile: Dr. {$name}\n\n";
        $linkedin .= $professional . ".";
        if ($hospital !== '') {
            $linkedin .= "\nAffiliated with {$hospital}.";
        }
        $linkedin .= $profile_url !== '' ? "\n\nView the complete profile and appointment information: {$profile_url}" : '';

        $x = "Dr. {$name}";
        if ($specialty !== '') {
            $x .= " • {$specialty}";
        }
        $x .= $profile_url !== '' ? "\n{$profile_url}" : '';
        $x .= "\n#Healthcare #MedicBD";

        if (function_exists('medic_social_limit')) {
            $x = medic_social_limit($x, 280);
        } elseif (strlen($x) > 280) {
            $x = substr($x, 0, 279) . '…';
        }

        return [
            'facebook' => $facebook,
            'instagram' => $instagram,
            'linkedin' => $linkedin,
            'x' => $x,
        ];
    }
}


/*
|--------------------------------------------------------------------------
| Branded Doctor Photo Card Helpers
|--------------------------------------------------------------------------
| Social posts use the same stable English photo-card filename as the public
| doctor profile: Name - Specialty - District.
|--------------------------------------------------------------------------
*/
if (!function_exists('medic_social_card_absolute_url')) {
    function medic_social_card_absolute_url(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        if (preg_match('/^https?:\/\//i', $value)) {
            return $value;
        }

        $value = str_replace('\\', '/', $value);

        while (strpos($value, '../') === 0) {
            $value = substr($value, 3);
        }

        $value = ltrim($value, '/');

        if ($value === '') {
            return '';
        }

        if (function_exists('site_url')) {
            return site_url($value);
        }

        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? '';

        return $host !== ''
            ? rtrim($scheme . '://' . $host, '/') . '/' . $value
            : '/' . $value;
    }
}

if (!function_exists('medic_social_card_filename')) {
    function medic_social_card_filename(string $title): string
    {
        $title = trim($title);
        $title = preg_replace('/\.jpe?g$/iu', '', $title);

        if (function_exists('iconv')) {
            $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $title);

            if ($ascii !== false) {
                $title = $ascii;
            }
        }

        $title = strtolower($title);
        $title = preg_replace('/[^a-z0-9]+/', '-', $title);
        $title = trim((string) $title, '-');

        return ($title !== '' ? $title : 'doctor-profile-card') . '.jpg';
    }
}

if (!function_exists('medic_social_card_specialty')) {
    function medic_social_card_specialty(array $doctor): string
    {
        foreach (['specialty_name', 'speciality_name', 'specialty', 'speciality'] as $key) {
            $value = trim((string) ($doctor[$key] ?? ''));

            if ($value !== '') {
                return $value;
            }
        }

        $specialtyId = (int) ($doctor['specialty_id'] ?? ($doctor['speciality_id'] ?? 0));

        if ($specialtyId <= 0) {
            return '';
        }

        global $pdo;

        if (!isset($pdo) || !($pdo instanceof PDO)) {
            return '';
        }

        try {
            $stmt = $pdo->prepare('SELECT name FROM specialties WHERE id = :id LIMIT 1');
            $stmt->execute([':id' => $specialtyId]);

            return trim((string) $stmt->fetchColumn());
        } catch (Throwable $e) {
            return '';
        }
    }
}

if (!function_exists('medic_social_card_district_by_id')) {
    function medic_social_card_district_by_id(int $districtId): string
    {
        if ($districtId <= 0) {
            return '';
        }

        global $pdo;

        if (!isset($pdo) || !($pdo instanceof PDO)) {
            return '';
        }

        foreach ([
            'SELECT name_en FROM districts WHERE id = :id LIMIT 1',
            'SELECT name FROM districts WHERE id = :id LIMIT 1',
        ] as $query) {
            try {
                $stmt = $pdo->prepare($query);
                $stmt->execute([':id' => $districtId]);
                $district = trim((string) $stmt->fetchColumn());

                if ($district !== '') {
                    return $district;
                }
            } catch (Throwable $e) {
                // Try the next schema-compatible query.
            }
        }

        return '';
    }
}

if (!function_exists('medic_social_card_district')) {
    function medic_social_card_district(array $doctor): string
    {
        foreach (['district_name', 'district_name_en', 'district'] as $key) {
            $value = trim((string) ($doctor[$key] ?? ''));

            if ($value !== '') {
                return $value;
            }
        }

        $districtId = (int) (
            $doctor['doctor_district_id']
            ?? $doctor['district_id']
            ?? $doctor['resolved_district_id']
            ?? 0
        );

        $district = medic_social_card_district_by_id($districtId);

        if ($district !== '') {
            return $district;
        }

        $doctorId = (int) ($doctor['id'] ?? 0);

        if ($doctorId <= 0) {
            return '';
        }

        global $pdo;

        if (!isset($pdo) || !($pdo instanceof PDO)) {
            return '';
        }

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
            $stmt->execute([':doctor_id' => $doctorId]);

            return medic_social_card_district_by_id((int) $stmt->fetchColumn());
        } catch (Throwable $e) {
            return '';
        }
    }
}

if (!function_exists('medic_social_card_profile_image')) {
    function medic_social_card_profile_image(array $doctor): string
    {
        foreach (['image', 'photo', 'profile_image', 'social_image_url', 'og_image'] as $key) {
            $url = medic_social_card_absolute_url((string) ($doctor[$key] ?? ''));

            if ($url !== '') {
                return $url;
            }
        }

        return '';
    }
}

if (!function_exists('medic_social_card_localized_value')) {
    function medic_social_card_localized_value(
        array $doctor,
        array $english_keys,
        array $bangla_keys,
        string $language = 'en'
    ): string {
        $language = strtolower(trim($language)) === 'bn' ? 'bn' : 'en';
        $preferred = $language === 'bn' ? $bangla_keys : $english_keys;
        $fallback = $language === 'bn' ? $english_keys : $bangla_keys;

        foreach (array_merge($preferred, $fallback) as $key) {
            $value = trim((string) ($doctor[$key] ?? ''));

            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }
}

if (!function_exists('medic_social_card_specialty_localized')) {
    function medic_social_card_specialty_localized(array $doctor, string $language = 'en'): string
    {
        /*
         * Source of truth: doctors.specialty_id -> specialties.id.
         * English uses specialties.name and Bangla uses specialties.name_bn.
         */
        $language = strtolower(trim($language)) === 'bn' ? 'bn' : 'en';
        $specialty_id = (int) ($doctor['specialty_id'] ?? ($doctor['speciality_id'] ?? 0));

        global $pdo;

        if ($specialty_id > 0 && isset($pdo) && $pdo instanceof PDO) {
            try {
                $stmt = $pdo->prepare("
                    SELECT name, name_bn
                    FROM specialties
                    WHERE id = :id
                    LIMIT 1
                ");
                $stmt->execute([':id' => $specialty_id]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);

                if (is_array($row)) {
                    $english = trim((string) ($row['name'] ?? ''));
                    $bangla = trim((string) ($row['name_bn'] ?? ''));

                    if ($language === 'bn') {
                        return $bangla !== '' ? $bangla : $english;
                    }

                    return $english !== '' ? $english : $bangla;
                }
            } catch (Throwable $e) {
                /*
                 * Compatibility fallback for an older specialties table without
                 * name_bn. The current English value still comes from the ID.
                 */
                try {
                    $stmt = $pdo->prepare("SELECT name FROM specialties WHERE id = :id LIMIT 1");
                    $stmt->execute([':id' => $specialty_id]);
                    $value = trim((string) $stmt->fetchColumn());

                    if ($value !== '') {
                        return $value;
                    }
                } catch (Throwable $ignored) {
                    // Use the saved fallback only if the lookup is unavailable.
                }
            }
        }

        /* Fallback only when specialty_id is empty or no matching row exists. */
        return medic_social_card_localized_value(
            $doctor,
            ['specialty_name', 'speciality_name', 'specialty', 'speciality'],
            ['specialty_name_bn', 'speciality_name_bn', 'specialty_bn', 'speciality_bn'],
            $language
        );
    }
}

if (!function_exists('medic_social_card_district_by_id_localized')) {
    function medic_social_card_district_by_id_localized(int $district_id, string $language = 'en'): string
    {
        if ($district_id <= 0) {
            return '';
        }

        global $pdo;

        if (!isset($pdo) || !($pdo instanceof PDO)) {
            return '';
        }

        $language = strtolower(trim($language)) === 'bn' ? 'bn' : 'en';
        $queries = $language === 'bn'
            ? [
                "SELECT name_bn FROM districts WHERE id = :id LIMIT 1",
                "SELECT name_en FROM districts WHERE id = :id LIMIT 1",
                "SELECT name FROM districts WHERE id = :id LIMIT 1",
            ]
            : [
                "SELECT name_en FROM districts WHERE id = :id LIMIT 1",
                "SELECT name FROM districts WHERE id = :id LIMIT 1",
                "SELECT name_bn FROM districts WHERE id = :id LIMIT 1",
            ];

        foreach ($queries as $query) {
            try {
                $stmt = $pdo->prepare($query);
                $stmt->execute([':id' => $district_id]);
                $value = trim((string) $stmt->fetchColumn());

                if ($value !== '') {
                    return $value;
                }
            } catch (Throwable $e) {
                // Try the next schema-compatible column.
            }
        }

        return '';
    }
}

if (!function_exists('medic_social_card_district_localized')) {
    function medic_social_card_district_localized(array $doctor, string $language = 'en'): string
    {
        $value = medic_social_card_localized_value(
            $doctor,
            ['district_name', 'district_name_en', 'district'],
            ['district_name_bn', 'district_bn'],
            $language
        );

        if ($value !== '') {
            return $value;
        }

        $district_id = (int) (
            $doctor['doctor_district_id']
            ?? $doctor['district_id']
            ?? $doctor['resolved_district_id']
            ?? 0
        );

        $value = medic_social_card_district_by_id_localized($district_id, $language);

        if ($value !== '') {
            return $value;
        }

        $doctor_id = (int) ($doctor['id'] ?? 0);

        if ($doctor_id <= 0) {
            return '';
        }

        global $pdo;

        if (!isset($pdo) || !($pdo instanceof PDO)) {
            return '';
        }

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

            return medic_social_card_district_by_id_localized(
                (int) $stmt->fetchColumn(),
                $language
            );
        } catch (Throwable $e) {
            return '';
        }
    }
}

if (!function_exists('medic_social_card_profile_url')) {
    function medic_social_card_profile_url(array $doctor, string $language = 'en'): string
    {
        $language = strtolower(trim($language)) === 'bn' ? 'bn' : 'en';
        $keys = $language === 'bn'
            ? ['profile_url_bn', 'bn_profile_url', 'profile_url_bangla']
            : ['profile_url_en', 'profile_url'];

        foreach ($keys as $key) {
            $url = medic_social_card_absolute_url((string) ($doctor[$key] ?? ''));

            if ($url !== '') {
                return $url;
            }
        }

        $slug = trim((string) ($doctor['slug'] ?? ''));

        if ($slug !== '') {
            $path = $language === 'bn'
                ? 'bn/doctor/' . rawurlencode($slug)
                : 'doctor/' . rawurlencode($slug);

            return medic_social_card_absolute_url($path);
        }

        return '';
    }
}

if (!function_exists('medic_social_card_chamber_localized')) {
    function medic_social_card_chamber_localized(array $doctor, string $language = 'en'): string
    {
        /*
         * Source of truth: chambers.hospital_id -> hospitals.id.
         * English uses hospitals.name and Bangla uses hospitals.name_bn.
         * A chamber's own free-text name is intentionally not used here.
         */
        $language = strtolower(trim($language)) === 'bn' ? 'bn' : 'en';
        $doctor_id = (int) ($doctor['id'] ?? 0);

        if ($doctor_id <= 0) {
            return '';
        }

        global $pdo;

        if (!isset($pdo) || !($pdo instanceof PDO)) {
            return '';
        }

        $queries = [
            "
                SELECT h.name, h.name_bn
                FROM chambers c
                INNER JOIN hospitals h ON h.id = c.hospital_id
                WHERE c.doctor_id = :doctor_id
                  AND c.hospital_id > 0
                  AND (c.status = 'active' OR c.status = 'published' OR c.status IS NULL OR c.status = '')
                ORDER BY c.sort_order ASC, c.id ASC
                LIMIT 1
            ",
            "
                SELECT h.name, h.name_bn
                FROM chambers c
                INNER JOIN hospitals h ON h.id = c.hospital_id
                WHERE c.doctor_id = :doctor_id
                  AND c.hospital_id > 0
                ORDER BY c.id ASC
                LIMIT 1
            ",
            "
                SELECT h.name, '' AS name_bn
                FROM chambers c
                INNER JOIN hospitals h ON h.id = c.hospital_id
                WHERE c.doctor_id = :doctor_id
                  AND c.hospital_id > 0
                ORDER BY c.id ASC
                LIMIT 1
            ",
        ];

        foreach ($queries as $query) {
            try {
                $stmt = $pdo->prepare($query);
                $stmt->execute([':doctor_id' => $doctor_id]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!is_array($row)) {
                    continue;
                }

                $english = trim((string) ($row['name'] ?? ''));
                $bangla = trim((string) ($row['name_bn'] ?? ''));

                if ($language === 'bn') {
                    return $bangla !== '' ? $bangla : $english;
                }

                return $english !== '' ? $english : $bangla;
            } catch (Throwable $e) {
                // Try a schema-compatible query below.
            }
        }

        return '';
    }
}


/*
|--------------------------------------------------------------------------
| Platform-Specific Hashtag Bridge
|--------------------------------------------------------------------------
| English and Bangla doctor campaigns use the same English hashtag strategy.
| The specialty slug is resolved from specialties.slug when available, and
| the district is always resolved in English before the mapping is applied.
|--------------------------------------------------------------------------
*/
if (!function_exists('medic_social_slugify')) {
    function medic_social_slugify(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        if (function_exists('iconv')) {
            $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);

            if ($ascii !== false) {
                $value = $ascii;
            }
        }

        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/', '-', $value);

        return trim((string) $value, '-');
    }
}

if (!function_exists('medic_social_card_specialty_slug')) {
    function medic_social_card_specialty_slug(array $doctor): string
    {
        foreach (['specialty_slug', 'speciality_slug'] as $key) {
            $slug = trim((string) ($doctor[$key] ?? ''));

            if ($slug !== '') {
                return medic_social_slugify($slug);
            }
        }

        $specialty_id = (int) ($doctor['specialty_id'] ?? ($doctor['speciality_id'] ?? 0));

        global $pdo;

        if ($specialty_id > 0 && isset($pdo) && $pdo instanceof PDO) {
            try {
                $stmt = $pdo->prepare('SELECT slug FROM specialties WHERE id = :id LIMIT 1');
                $stmt->execute([':id' => $specialty_id]);
                $slug = trim((string) $stmt->fetchColumn());

                if ($slug !== '') {
                    return medic_social_slugify($slug);
                }
            } catch (Throwable $e) {
                // Fall back to the English specialty name below.
            }
        }

        return medic_social_slugify(medic_social_card_specialty_localized($doctor, 'en'));
    }
}

if (!function_exists('medic_social_platform_hashtags')) {
    function medic_social_platform_hashtags(array $doctor, string $platform = 'facebook'): string
    {
        $platform = strtolower(trim($platform));

        /* X posts intentionally do not include hashtags. */
        if (in_array($platform, ['x', 'twitter'], true)) {
            return '';
        }

        $specialty_slug = medic_social_card_specialty_slug($doctor);
        $district_name = medic_social_card_district_localized($doctor, 'en');

        if (function_exists('medic_generate_social_hashtags')) {
            $hashtags = trim((string) medic_generate_social_hashtags(
                $specialty_slug,
                $district_name,
                $platform
            ));

            if ($hashtags !== '') {
                return $hashtags;
            }
        }

        /* Safe fallback if the standalone mapping file is not yet uploaded. */
        return medic_social_hashtag_string([
            medic_social_card_specialty_localized($doctor, 'en'),
        ], 'en');
    }
}

if (!function_exists('medic_social_hashtag_string')) {
    function medic_social_hashtag_string(array $parts, string $language = 'en'): string
    {
        /*
         * Hashtags always stay English for both English and Bangla campaigns.
         * Fixed brand tags are followed by the English specialty hashtag.
         */
        $hashtags = [
            '#MediCare' => '#MediCare',
            '#DoctorProfile' => '#DoctorProfile',
            '#Healthcare' => '#Healthcare',
        ];

        foreach ($parts as $part) {
            $part = trim((string) $part);

            if ($part === '') {
                continue;
            }

            if (function_exists('iconv')) {
                $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $part);

                if ($ascii !== false) {
                    $part = $ascii;
                }
            }

            $tag = preg_replace('/[^A-Za-z0-9]+/', '', $part);
            $tag = trim((string) $tag);

            if ($tag !== '') {
                $hashtags['#' . $tag] = '#' . $tag;
            }
        }

        return implode(' ', array_values($hashtags));
    }
}

if (!function_exists('medic_social_doctor_photo_card')) {
    function medic_social_doctor_photo_card(array $doctor, string $language = 'en'): array
    {
        $language = strtolower(trim($language)) === 'bn' ? 'bn' : 'en';

        $name = medic_social_card_localized_value(
            $doctor,
            ['name'],
            ['name_bn'],
            $language
        );

        $degree = medic_social_card_localized_value(
            $doctor,
            ['degree'],
            ['degree_bn'],
            $language
        );

        $designation = medic_social_card_localized_value(
            $doctor,
            ['designation'],
            ['designation_bn'],
            $language
        );

        $hospital = medic_social_card_localized_value(
            $doctor,
            ['primary_hospital', 'hospital_name'],
            ['primary_hospital_bn', 'hospital_name_bn'],
            $language
        );

        $specialty = medic_social_card_specialty_localized($doctor, $language);
        $district = medic_social_card_district_localized($doctor, $language);
        $chamber = medic_social_card_chamber_localized($doctor, $language);

        /*
         * Both language cards share one stable English filename. The files are
         * separated only by directory: uploads/doctor-cards and bn-doctor-cards.
         */
        $filename_name = medic_social_card_localized_value($doctor, ['name'], ['name_bn'], 'en');
        $filename_specialty = medic_social_card_specialty_localized($doctor, 'en');
        $filename_district = medic_social_card_district_localized($doctor, 'en');

        $filename_title = implode(' - ', array_values(array_filter([
            $filename_name,
            $filename_specialty,
            $filename_district,
        ], static function ($value): bool {
            return trim((string) $value) !== '';
        })));

        if ($filename_title === '') {
            $filename_title = $name !== '' ? $name : 'Doctor Profile';
        }

        $title = implode(' - ', array_values(array_filter([
            $name,
            $specialty,
            $district,
        ], static function ($value): bool {
            return trim((string) $value) !== '';
        })));

        if ($title === '') {
            $title = $language === 'bn' ? 'ডাক্তার প্রোফাইল' : 'Doctor Profile';
        }

        $filename = medic_social_card_filename($filename_title);
        $relative_directory = $language === 'bn'
            ? 'uploads/bn-doctor-cards/'
            : 'uploads/doctor-cards/';
        $disk_directory = dirname(__DIR__) . '/' . rtrim($relative_directory, '/') . '/';
        $disk_path = $disk_directory . $filename;
        $exists = is_file($disk_path) && (int) @filesize($disk_path) > 0;
        $version = $exists ? (int) @filemtime($disk_path) : 0;
        $url = $exists
            ? medic_social_card_absolute_url($relative_directory . rawurlencode($filename))
            : '';

        if ($url !== '' && $version > 0) {
            $url .= (strpos($url, '?') === false ? '?' : '&') . 'v=' . $version;
        }

        $rating = (float) ($doctor['rating'] ?? 0);
        $rating_stars = $rating > 0 ? max(1, min(5, (int) round($rating))) : 0;

        $subject = $language === 'bn'
            ? 'ডাক্তার প্রোফাইল - ' . $title
            : 'Doctor Profile - ' . $title;

        if ($rating > 0) {
            $subject .= $language === 'bn'
                ? ' | রেটিং: ' . number_format($rating, 1) . '/5'
                : ' | Rating: ' . number_format($rating, 1) . '/5';
        }

        /*
         * Hashtags stay English for both English and Bangla posts. Specialty
         * comes from specialties.id -> specialties.name and district uses the
         * English district resolver.
         */
        $english_specialty = medic_social_card_specialty_localized($doctor, 'en');
        $english_district = medic_social_card_district_localized($doctor, 'en');

        $specialty_slug = medic_social_card_specialty_slug($doctor);
        $hashtags = medic_social_platform_hashtags($doctor, 'facebook');

        return [
            'language' => $language,
            'exists' => $exists,
            'url' => $url,
            'filename' => $filename,
            'title' => $title,
            'name' => $name,
            'degree' => $degree,
            'designation' => $designation,
            'hospital' => $hospital,
            'chamber' => $chamber,
            'specialty' => $specialty,
            'specialty_slug' => $specialty_slug,
            'district' => $district,
            'profile_url' => medic_social_card_profile_url($doctor, $language),
            'source_image' => medic_social_card_profile_image($doctor),
            'subject' => $subject,
            'tags' => implode(', ', array_filter([
                $name,
                $specialty,
                $district,
                $language === 'bn' ? 'ডাক্তার প্রোফাইল' : 'Doctor Profile',
                'MedicBD',
            ])),
            'hashtags' => $hashtags,
            'rating' => $rating_stars,
        ];
    }
}

/*
|--------------------------------------------------------------------------
| Platform-Specific Professional Caption Layouts
|--------------------------------------------------------------------------
| English and Bangla campaigns keep their own doctor data and profile URLs.
| Each network receives a distinct professional caption. Hashtags remain
| English and use the selected practical limits:
| Facebook 3, Instagram 5, LinkedIn 3, and X 0.
|--------------------------------------------------------------------------
*/
if (!function_exists('medic_social_caption_designation')) {
    function medic_social_caption_designation(string $designation): string
    {
        $designation = trim($designation);

        if ($designation === '') {
            return '';
        }

        /* Convert a parenthetical specialty to a professional comma format. */
        $designation = preg_replace_callback(
            '/\s*\(([^()]*)\)/u',
            static function (array $matches): string {
                $inside = trim((string) ($matches[1] ?? ''));

                return $inside !== '' ? ', ' . $inside : '';
            },
            $designation
        );

        $designation = preg_replace('/\s*,\s*/u', ', ', (string) $designation);
        $designation = preg_replace('/(?:,\s*){2,}/u', ', ', (string) $designation);

        return trim((string) $designation, " \t\n\r\0\x0B,");
    }
}

if (!function_exists('medic_social_caption_blocks')) {
    function medic_social_caption_blocks(array $values): array
    {
        return array_values(array_filter($values, static function ($value): bool {
            return trim((string) $value) !== '';
        }));
    }
}

if (!function_exists('medic_social_doctor_caption_fallback_for_language')) {
    function medic_social_doctor_caption_fallback_for_language(array $doctor, array $card, string $language = 'en'): array
    {
        $language = strtolower(trim($language)) === 'bn' ? 'bn' : 'en';

        $name = trim((string) ($card['name'] ?? ''));
        $specialty = trim((string) ($card['specialty'] ?? ''));
        $district = trim((string) ($card['district'] ?? ''));
        $degree = trim((string) ($card['degree'] ?? ''));
        $designation = medic_social_caption_designation(
            (string) ($card['designation'] ?? '')
        );
        $primary_hospital = trim((string) ($card['hospital'] ?? ''));
        $profile_url = trim((string) ($card['profile_url'] ?? ''));

        if ($name === '') {
            $name = $language === 'bn' ? 'ডাক্তার প্রোফাইল' : 'Doctor Profile';
        }

        $specialty_district = implode(' | ', medic_social_caption_blocks([
            $specialty,
            $district,
        ]));

        $facebook_invitation = $language === 'bn'
            ? 'সম্পূর্ণ প্রোফাইল ও অ্যাপয়েন্টমেন্ট তথ্য দেখুন:'
            : 'View full profile and appointment information:';
        $instagram_invitation = $language === 'bn'
            ? 'বিস্তারিত প্রোফাইল ও অ্যাপয়েন্টমেন্ট তথ্য দেখুন:'
            : 'Explore the full doctor profile and appointment information:';
        $x_invitation = $language === 'bn'
            ? 'প্রোফাইল ও অ্যাপয়েন্টমেন্ট:'
            : 'Profile & appointments:';

        /* LinkedIn uses a formal directory-style profile layout. */
        $linkedin_directory = $language === 'bn'
            ? 'মেডিকবিডি পেশাগত ডিরেক্টরি'
            : 'MedicBD Professional Directory';
        $linkedin_profile_heading = $language === 'bn'
            ? 'পেশাগত প্রোফাইল'
            : 'Professional Profile';
        $linkedin_qualifications_heading = $language === 'bn'
            ? 'শিক্ষাগত ও পেশাগত যোগ্যতা'
            : 'Qualifications';
        $linkedin_appointment_heading = $language === 'bn'
            ? 'বর্তমান কর্মস্থল'
            : 'Current Appointment';
        $linkedin_invitation = $language === 'bn'
            ? 'সম্পূর্ণ পেশাগত প্রোফাইল ও অ্যাপয়েন্টমেন্ট তথ্যের জন্য দেখুন:'
            : 'For complete professional profile and appointment information, please visit:';

        $header_block = implode("\n", medic_social_caption_blocks([
            $name,
            $specialty_district,
        ]));
        $professional_block = implode("\n", medic_social_caption_blocks([
            $designation,
            $primary_hospital,
        ]));

        $build_caption = static function (array $blocks, string $hashtags = ''): string {
            if ($hashtags !== '') {
                $blocks[] = $hashtags;
            }

            return implode("\n\n", medic_social_caption_blocks($blocks));
        };

        $facebook = $build_caption([
            $header_block,
            $degree,
            $professional_block,
            $profile_url !== '' ? $facebook_invitation . "\n" . $profile_url : '',
        ], medic_social_platform_hashtags($doctor, 'facebook'));

        $instagram = $build_caption([
            $header_block,
            $degree,
            $professional_block,
            $profile_url !== '' ? $instagram_invitation . "\n" . $profile_url : '',
        ], medic_social_platform_hashtags($doctor, 'instagram'));

        $linkedin = $build_caption([
            $linkedin_directory,
            $linkedin_profile_heading . "\n" . $header_block,
            $degree !== '' ? $linkedin_qualifications_heading . "\n" . $degree : '',
            $professional_block !== '' ? $linkedin_appointment_heading . "\n" . $professional_block : '',
            $profile_url !== '' ? $linkedin_invitation . "\n" . $profile_url : '',
        ], medic_social_platform_hashtags($doctor, 'linkedin'));

        /* X is intentionally concise and has no hashtags. */
        $x = implode("\n", medic_social_caption_blocks([
            $name . ($specialty_district !== '' ? ' — ' . $specialty_district : ''),
            $degree,
            $designation,
            $primary_hospital,
            $profile_url !== '' ? $x_invitation . ' ' . $profile_url : '',
        ]));

        if (function_exists('medic_social_limit')) {
            $x = medic_social_limit($x, 280);
        } elseif (function_exists('mb_strlen') && mb_strlen($x, 'UTF-8') > 280) {
            $x = mb_substr($x, 0, 279, 'UTF-8') . '…';
        } elseif (strlen($x) > 280) {
            $x = substr($x, 0, 279) . '…';
        }

        return [
            'facebook' => $facebook,
            'instagram' => $instagram,
            'linkedin' => $linkedin,
            'x' => $x,
        ];
    }
}

/*
|--------------------------------------------------------------------------
| Required database check
|--------------------------------------------------------------------------
*/
$database_status = function_exists('medic_social_database_status')
    ? medic_social_database_status()
    : [
        'ready' => function_exists('medic_social_database_ready')
            ? medic_social_database_ready()
            : false,
        'missing_tables' => [],
        'database_name' => '',
    ];

if (empty($database_status['ready'])) {
    require_once __DIR__ . '/includes/header.php';

    $missing_tables = implode(', ', (array) ($database_status['missing_tables'] ?? []));
    $database_name = trim((string) ($database_status['database_name'] ?? ''));

    echo '<div style="max-width:900px;margin:30px auto;padding:20px;background:#fff;border:1px solid #fecaca;border-radius:12px">';
    echo '<h1>Social module database is not ready</h1>';
    echo '<p>' . e(
        $missing_tables !== ''
            ? 'Missing tables: ' . $missing_tables . '.'
            : 'The required social tables could not be verified.'
    ) . '</p>';
    if ($database_name !== '') {
        echo '<p>Connected database: <code>' . e($database_name) . '</code></p>';
    }
    echo '<p><a href="social-posts.php">Back to Social Posts</a></p>';
    echo '</div>';

    require_once __DIR__ . '/includes/footer.php';
    exit;
}

$doctor_id = (int) ($_GET['doctor_id'] ?? $_POST['doctor_id'] ?? 0);
$doctor = medic_social_get_doctor($doctor_id);

if (!$doctor) {
    medic_social_flash('error', 'Doctor profile was not found.');
    redirect('social-posts.php');
}

$platforms = medic_social_platforms();
$configured_channels = medic_social_configured_channels();

/*
|--------------------------------------------------------------------------
| Branded photo-card selection
|--------------------------------------------------------------------------
| The saved public photo card is the default social image. It is the same
| stable card used by the doctor profile's Open Graph preview.
|--------------------------------------------------------------------------
*/
$doctor_photo_cards = [
    'en' => medic_social_doctor_photo_card($doctor, 'en'),
    'bn' => medic_social_doctor_photo_card($doctor, 'bn'),
];

/* English remains the first visible workspace when the page opens. */
$doctor_photo_card = $doctor_photo_cards['en'];
$doctor_profile_image = (string) ($doctor_photo_card['source_image'] ?? '');
$doctor_existing_social_image = medic_social_card_absolute_url((string) ($doctor['social_image_url'] ?? ''));

$campaign_default_images = [];
$campaign_default_image_sources = [];

foreach (['en', 'bn'] as $language_key) {
    $card = $doctor_photo_cards[$language_key];

    $campaign_default_images[$language_key] = !empty($card['exists'])
        ? (string) ($card['url'] ?? '')
        : ($doctor_existing_social_image !== '' ? $doctor_existing_social_image : $doctor_profile_image);

    $campaign_default_image_sources[$language_key] = !empty($card['exists'])
        ? 'Branded Doctor Photo Card'
        : ($campaign_default_images[$language_key] !== '' ? 'Doctor profile image' : 'No image selected');
}

/* Backward-compatible aliases used by the first-load English workspace. */
$campaign_default_image_url = $campaign_default_images['en'];
$campaign_default_image_source = $campaign_default_image_sources['en'];

/*
|--------------------------------------------------------------------------
| Independent English and Bangla captions
|--------------------------------------------------------------------------
*/
$captions_en = [];
if (function_exists('medic_social_doctor_captions')) {
    try {
        $captions_en = (array) medic_social_doctor_captions($doctor);
    } catch (Throwable $e) {
        $captions_en = [];
    }
}

$fallback_en = medic_social_doctor_caption_fallback_for_language(
    $doctor,
    $doctor_photo_cards['en'],
    'en'
);

$fallback_bn = medic_social_doctor_caption_fallback_for_language(
    $doctor,
    $doctor_photo_cards['bn'],
    'bn'
);

$captions_bn = [];

foreach (array_keys($platforms) as $platform_key) {
    /* The fixed campaign template is shown by default for every platform. */
    $captions_en[$platform_key] = $fallback_en[$platform_key] ?? '';
    $captions_bn[$platform_key] = $fallback_bn[$platform_key] ?? '';
}

$campaign_titles = [
    'en' => 'Doctor profile: ' . (string) ($doctor_photo_cards['en']['name'] ?? $doctor['name'] ?? ''),
    'bn' => 'ডাক্তার প্রোফাইল: ' . (string) ($doctor_photo_cards['bn']['name'] ?? $doctor['name_bn'] ?? $doctor['name'] ?? ''),
];

/*
|--------------------------------------------------------------------------
| Publish submission
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'publish_doctor') {
    if (!medic_social_verify_csrf($_POST['csrf_token'] ?? '')) {
        medic_social_flash('error', 'Security check failed. Refresh the page and try again.');
        redirect('doctor-social-post.php?doctor_id=' . $doctor_id);
    }

    if (!medic_social_buffer_ready()) {
        medic_social_flash('error', 'Buffer API key is not configured. Add it to config/social-config.php or your server environment.');
        redirect('doctor-social-post.php?doctor_id=' . $doctor_id);
    }

    /*
     * English and Bangla are separate campaigns. The active workspace decides
     * which title, profile URL, card image and captions reach Buffer.
     */
    $post_language = strtolower(trim((string) ($_POST['post_language'] ?? 'en')));
    $post_language = in_array($post_language, ['en', 'bn'], true) ? $post_language : 'en';
    $active_card = $doctor_photo_cards[$post_language];
    $active_default_image = $campaign_default_images[$post_language] ?? '';
    $active_profile_url = (string) ($active_card['profile_url'] ?? '');

    $selected_targets = $_POST['targets'] ?? [];
    $selected_targets = is_array($selected_targets)
        ? array_values(array_intersect(array_keys($platforms), $selected_targets))
        : [];

    if (empty($selected_targets)) {
        medic_social_flash('error', 'Choose at least one social platform.');
        redirect('doctor-social-post.php?doctor_id=' . $doctor_id);
    }

    $publish_mode = trim((string) ($_POST['publish_mode'] ?? 'addToQueue'));
    $allowed_modes = ['shareNow', 'addToQueue', 'customScheduled'];

    if (!in_array($publish_mode, $allowed_modes, true)) {
        $publish_mode = 'addToQueue';
    }

    $scheduled_utc = null;

    if ($publish_mode === 'customScheduled') {
        $schedule_local = trim((string) ($_POST['scheduled_at'] ?? ''));

        $scheduled_utc = function_exists('medic_buffer_local_to_utc')
            ? medic_buffer_local_to_utc($schedule_local, defined('MEDIC_SOCIAL_TIMEZONE') ? MEDIC_SOCIAL_TIMEZONE : 'Asia/Dhaka')
            : medic_social_local_to_utc($schedule_local);

        if ($scheduled_utc === null) {
            medic_social_flash('error', 'Choose a valid schedule date and time.');
            redirect('doctor-social-post.php?doctor_id=' . $doctor_id);
        }

        try {
            $scheduled_time = new DateTimeImmutable($scheduled_utc);
            $current_time = new DateTimeImmutable('now', new DateTimeZone('UTC'));

            if ($scheduled_time <= $current_time) {
                medic_social_flash('error', 'Scheduled time must be in the future.');
                redirect('doctor-social-post.php?doctor_id=' . $doctor_id);
            }
        } catch (Throwable $e) {
            medic_social_flash('error', 'Scheduled time could not be processed.');
            redirect('doctor-social-post.php?doctor_id=' . $doctor_id);
        }
    }

    $uploaded_image = medic_social_upload_image('social_image');

    if (empty($uploaded_image['ok'])) {
        medic_social_flash('error', (string) ($uploaded_image['message'] ?? 'Image upload failed.'));
        redirect('doctor-social-post.php?doctor_id=' . $doctor_id);
    }

    $image_url = medic_social_absolute_url(
        (string) ($_POST['image_url_' . $post_language] ?? $_POST['image_url'] ?? '')
    );

    if (trim((string) ($uploaded_image['url'] ?? '')) !== '') {
        $image_url = (string) $uploaded_image['url'];
    }

    if ($image_url === '') {
        $image_url = $active_default_image;
    }

    $captions_to_publish = [];

    foreach ($selected_targets as $platform_key) {
        $channel = $configured_channels[$platform_key] ?? [];

        if (empty($channel['configured'])) {
            medic_social_flash(
                'error',
                ($platforms[$platform_key]['label'] ?? ucfirst($platform_key))
                . ' has no saved Buffer channel mapping.'
            );
            redirect('doctor-social-post.php?doctor_id=' . $doctor_id);
        }

        if (!empty($channel['requires_image']) && $image_url === '') {
            medic_social_flash('error', 'Instagram requires a public image URL or a new image upload.');
            redirect('doctor-social-post.php?doctor_id=' . $doctor_id);
        }

        $caption = trim((string) (
            $_POST['caption_' . $platform_key . '_' . $post_language]
            ?? $_POST['caption_' . $platform_key]
            ?? ''
        ));

        if ($caption === '') {
            medic_social_flash(
                'error',
                ($platforms[$platform_key]['label'] ?? ucfirst($platform_key))
                . ' caption cannot be empty.'
            );
            redirect('doctor-social-post.php?doctor_id=' . $doctor_id);
        }

        $captions_to_publish[$platform_key] = $caption;
    }

    $campaign_title = trim((string) (
        $_POST['title_' . $post_language]
        ?? $_POST['title']
        ?? ''
    ));

    if ($campaign_title === '') {
        $campaign_title = (string) ($campaign_titles[$post_language] ?? 'Doctor profile');
    }

    try {
        $social_post_id = medic_social_create_post([
            'source_type' => 'doctor',
            'source_id' => $doctor_id,
            'title' => function_exists('medic_social_limit')
                ? medic_social_limit($campaign_title, 255)
                : substr($campaign_title, 0, 255),
            'link_url' => $active_profile_url,
            'image_url' => $image_url,
            'publish_mode' => $publish_mode,
            'scheduled_at_utc' => $scheduled_utc,
        ]);

        if (function_exists('medic_social_create_schedule_audit')) {
            medic_social_create_schedule_audit($social_post_id, $scheduled_utc);
        }

        $success_count = 0;
        $failed_count = 0;

        foreach ($selected_targets as $platform_key) {
            $channel = $configured_channels[$platform_key];
            $caption = $captions_to_publish[$platform_key];

            $target_id = medic_social_create_target($social_post_id, [
                'platform' => $platform_key,
                'channel_id' => (string) $channel['channel_id'],
                'channel_name' => trim((string) ($channel['channel_name'] ?? '')) !== ''
                    ? (string) $channel['channel_name']
                    : (string) ($channel['label'] ?? $platforms[$platform_key]['label']),
                'caption' => $caption,
            ]);

            /*
             * Facebook and Instagram require channel-specific post metadata.
             * A normal doctor promotion is sent as a feed post.
             */
            $platform_metadata = function_exists('medic_buffer_metadata_for_platform')
                ? medic_buffer_metadata_for_platform($platform_key)
                : [];

            $result = medic_buffer_create_post(
                (string) $channel['channel_id'],
                $caption,
                $publish_mode,
                $scheduled_utc,
                $image_url,
                $platform_metadata
            );

            medic_social_update_target_result($target_id, $result, $publish_mode);
            medic_social_log($social_post_id, $target_id, 'create_post', $result);

            if (!empty($result['ok'])) {
                $success_count++;
            } else {
                $failed_count++;
            }
        }

        medic_social_update_overall_status($social_post_id);

        if (function_exists('admin_log_activity')) {
            admin_log_activity(
                'social_post_created',
                'Created ' . strtoupper($post_language) . ' social campaign #' . $social_post_id
                . ' for doctor #' . $doctor_id
                . '. Success: ' . $success_count
                . ', failed: ' . $failed_count . '.'
            );
        }

        medic_social_flash(
            $failed_count > 0 ? 'error' : 'success',
            $failed_count > 0
                ? 'Campaign created, but ' . $failed_count . ' platform request(s) failed. Check Publishing History.'
                : 'Campaign sent to Buffer for ' . $success_count . ' platform(s).'
        );

        redirect('social-post-history.php?post_id=' . $social_post_id);
    } catch (Throwable $e) {
        medic_social_flash(
            'error',
            'The social campaign could not be created. Check the database columns and Buffer configuration.'
        );
        redirect('doctor-social-post.php?doctor_id=' . $doctor_id);
    }
}

$buffer_ready = medic_social_buffer_ready();
$mapped_count = 0;

foreach ($configured_channels as $channel) {
    if (!empty($channel['configured'])) {
        $mapped_count++;
    }
}

require_once __DIR__ . '/includes/header.php';
?>
<style>
:root {
    --gh-canvas-default: #ffffff;
    --gh-canvas-subtle: #f6f8fa;
    --gh-canvas-inset: #f6f8fa;
    --gh-border-default: #d0d7de;
    --gh-border-muted: #d8dee4;
    --gh-fg-default: #24292f;
    --gh-fg-muted: #57606a;
    --gh-fg-subtle: #6e7781;
    --gh-accent-fg: #0969da;
    --gh-accent-emphasis: #0969da;
    --gh-accent-muted: #ddf4ff;
    --gh-success-fg: #1a7f37;
    --gh-success-emphasis: #2da44e;
    --gh-success-muted: #dafbe1;
    --gh-danger-fg: #cf222e;
    --gh-danger-emphasis: #cf222e;
    --gh-danger-muted: #ffebe9;
    --gh-attention-fg: #9a6700;
    --gh-attention-muted: #fff8c5;
    --gh-shadow-small: 0 1px 0 rgba(27, 31, 36, .04);
    --gh-shadow-medium: 0 3px 6px rgba(140, 149, 159, .15);
    --gh-radius: 8px;
    --gh-radius-large: 12px;
}

body {
    background: var(--gh-canvas-subtle);
}

.gh-social-page,
.gh-social-page * {
    box-sizing: border-box;
}

.gh-social-page {
    max-width: 1240px;
    margin: 0 auto;
    padding: 28px 20px 52px;
    color: var(--gh-fg-default);
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Helvetica, Arial, sans-serif;
}

.gh-social-page a {
    color: var(--gh-accent-fg);
    text-decoration: none;
}

.gh-social-page a:hover {
    text-decoration: underline;
}

.gh-social-page code {
    padding: 2px 5px;
    border: 1px solid var(--gh-border-muted);
    border-radius: 6px;
    background: rgba(175, 184, 193, .2);
    color: var(--gh-fg-default);
    font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
    font-size: 12px;
}

/* Page heading */
.gh-page-heading {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 24px;
    margin-bottom: 18px;
}

.gh-breadcrumb {
    display: inline-flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
    margin-bottom: 12px;
    color: var(--gh-fg-muted);
    font-size: 14px;
}

.gh-breadcrumb-separator {
    color: var(--gh-fg-subtle);
}

.gh-page-title {
    margin: 0;
    color: var(--gh-fg-default);
    font-size: 30px;
    line-height: 1.25;
    letter-spacing: -.5px;
}

.gh-page-summary {
    max-width: 760px;
    margin: 8px 0 0;
    color: var(--gh-fg-muted);
    font-size: 14px;
    line-height: 1.55;
}

.gh-page-actions {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 8px;
    flex-wrap: wrap;
    padding-top: 4px;
}

/* Reusable button */
.gh-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 34px;
    padding: 6px 12px;
    border: 1px solid rgba(27, 31, 36, .15);
    border-radius: 6px;
    background: #f6f8fa;
    color: var(--gh-fg-default) !important;
    box-shadow: var(--gh-shadow-small);
    cursor: pointer;
    font: 600 14px/20px -apple-system, BlinkMacSystemFont, "Segoe UI", Helvetica, Arial, sans-serif;
    text-decoration: none !important;
    transition: background-color .15s ease, border-color .15s ease, box-shadow .15s ease;
}

.gh-btn:hover {
    background: #f3f4f6;
    border-color: rgba(27, 31, 36, .25);
    text-decoration: none !important;
}

.gh-btn:focus,
.gh-input:focus,
.gh-select:focus,
.gh-textarea:focus {
    outline: 2px solid transparent;
    outline-offset: 2px;
    border-color: var(--gh-accent-fg);
    box-shadow: 0 0 0 3px rgba(9, 105, 218, .3);
}

.gh-btn-primary {
    border-color: rgba(27, 31, 36, .15);
    background: var(--gh-success-emphasis);
    color: #fff !important;
}

.gh-btn-primary:hover {
    background: #2c974b;
}

/* Profile overview */
.gh-profile-overview {
    overflow: hidden;
    margin-bottom: 18px;
    border: 1px solid var(--gh-border-default);
    border-radius: var(--gh-radius-large);
    background: var(--gh-canvas-default);
    box-shadow: var(--gh-shadow-small);
}

.gh-profile-overview-main {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(300px, .42fr);
}

.gh-profile-primary {
    display: flex;
    gap: 18px;
    min-width: 0;
    padding: 24px;
}

.gh-avatar {
    width: 92px;
    height: 92px;
    flex: 0 0 92px;
    overflow: hidden;
    border: 1px solid var(--gh-border-default);
    border-radius: 50%;
    background: var(--gh-canvas-subtle);
    box-shadow: 0 0 0 4px #fff, 0 0 0 5px var(--gh-border-muted);
}

.gh-avatar img {
    display: block;
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.gh-avatar-placeholder {
    display: grid;
    width: 100%;
    height: 100%;
    place-items: center;
    background: var(--gh-accent-muted);
    color: var(--gh-accent-fg);
    font-weight: 700;
    font-size: 24px;
}

.gh-profile-copy {
    min-width: 0;
}

.gh-profile-name {
    margin: 0;
    color: var(--gh-fg-default);
    font-size: 26px;
    line-height: 1.25;
    letter-spacing: -.4px;
}

.gh-profile-meta {
    margin: 7px 0 0;
    color: var(--gh-fg-muted);
    font-size: 15px;
    line-height: 1.55;
}

.gh-profile-hospital {
    margin: 6px 0 0;
    color: var(--gh-fg-subtle);
    font-size: 14px;
}

.gh-profile-side {
    display: flex;
    flex-direction: column;
    justify-content: center;
    padding: 24px;
    border-left: 1px solid var(--gh-border-default);
    background: var(--gh-canvas-subtle);
}

.gh-side-label {
    margin-bottom: 6px;
    color: var(--gh-fg-muted);
    font-size: 12px;
    font-weight: 600;
    letter-spacing: .025em;
    text-transform: uppercase;
}

.gh-profile-url {
    overflow: hidden;
    color: var(--gh-accent-fg);
    font-size: 13px;
    line-height: 1.45;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.gh-profile-url-empty {
    color: var(--gh-fg-subtle);
}

.gh-status-row {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    padding: 14px 24px;
    border-top: 1px solid var(--gh-border-default);
    background: #fff;
}

.gh-status {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    min-height: 26px;
    padding: 3px 9px;
    border: 1px solid var(--gh-border-default);
    border-radius: 999px;
    background: var(--gh-canvas-subtle);
    color: var(--gh-fg-muted);
    font-size: 12px;
    font-weight: 600;
}

.gh-status-dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: #8c959f;
}

.gh-status.is-ok {
    border-color: #a7f3d0;
    background: var(--gh-success-muted);
    color: var(--gh-success-fg);
}

.gh-status.is-ok .gh-status-dot {
    background: var(--gh-success-emphasis);
}

.gh-status.is-bad {
    border-color: #ff8182;
    background: var(--gh-danger-muted);
    color: var(--gh-danger-fg);
}

.gh-status.is-bad .gh-status-dot {
    background: var(--gh-danger-emphasis);
}


/* Branded photo-card manager */
.gh-photo-card-manager {
    display: grid;
    grid-template-columns: 240px minmax(0, 1fr);
    gap: 20px;
    padding: 16px;
    border: 1px solid var(--gh-border-default);
    border-radius: 8px;
    background: linear-gradient(135deg, #f6f8fa 0%, #ffffff 68%);
}

.gh-photo-card-preview {
    position: relative;
    min-height: 126px;
    overflow: hidden;
    border: 1px solid var(--gh-border-default);
    border-radius: 7px;
    background: #0d1b2a;
    box-shadow: var(--gh-shadow-small);
}

.gh-photo-card-preview img {
    display: block;
    width: 100%;
    height: 100%;
    min-height: 126px;
    object-fit: cover;
}

.gh-photo-card-placeholder {
    display: grid;
    width: 100%;
    min-height: 126px;
    place-items: center;
    background: linear-gradient(135deg, #0b2239, #0969da);
    color: #fff;
    font-size: 42px;
    font-weight: 700;
}

.gh-photo-card-copy h3 {
    margin: 6px 0;
    color: var(--gh-fg-default);
    font-size: 16px;
    line-height: 1.4;
}

.gh-photo-card-copy > p {
    margin: 0;
    color: var(--gh-fg-muted);
    font-size: 13px;
    line-height: 1.55;
}

.gh-photo-card-eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    color: var(--gh-success-fg);
    font-size: 12px;
    font-weight: 700;
}

.gh-photo-card-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: var(--gh-success-emphasis);
    box-shadow: 0 0 0 3px var(--gh-success-muted);
}

.gh-photo-card-file {
    margin-top: 10px !important;
    word-break: break-word;
}

.gh-photo-card-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 12px;
}

.gh-btn.is-loading {
    opacity: .7;
    cursor: wait;
}

.gh-field-help.is-error {
    color: var(--gh-danger-fg);
    font-weight: 600;
}

.gh-field-help.is-success {
    color: var(--gh-success-fg);
    font-weight: 600;
}

/* Notice and alert */
.gh-social-page .medic-social-alert,
.gh-notice {
    display: flex;
    align-items: flex-start;
    gap: 11px;
    margin: 0 0 18px;
    padding: 14px 16px;
    border: 1px solid var(--gh-border-default);
    border-radius: var(--gh-radius);
    background: var(--gh-canvas-default);
    box-shadow: var(--gh-shadow-small);
    color: var(--gh-fg-default);
    font-size: 14px;
    line-height: 1.55;
}

.gh-social-page .medic-social-alert-success {
    border-color: #a7f3d0;
    background: var(--gh-success-muted);
    color: var(--gh-success-fg);
}

.gh-social-page .medic-social-alert-error,
.gh-notice-warning {
    border-color: #ff8182;
    background: var(--gh-danger-muted);
    color: var(--gh-danger-fg);
}

.gh-notice-icon {
    flex: 0 0 20px;
    font-weight: 700;
}

/* Forms */
.gh-form-card {
    overflow: hidden;
    margin-bottom: 18px;
    border: 1px solid var(--gh-border-default);
    border-radius: var(--gh-radius-large);
    background: var(--gh-canvas-default);
    box-shadow: var(--gh-shadow-small);
}

.gh-card-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 18px;
    padding: 20px 24px;
    border-bottom: 1px solid var(--gh-border-default);
    background: var(--gh-canvas-subtle);
}

.gh-card-heading {
    display: flex;
    align-items: flex-start;
    gap: 10px;
}

.gh-step {
    display: grid;
    width: 26px;
    height: 26px;
    flex: 0 0 26px;
    place-items: center;
    border: 1px solid #54aeff;
    border-radius: 50%;
    background: #ddf4ff;
    color: var(--gh-accent-fg);
    font-size: 12px;
    font-weight: 700;
}

.gh-card-header h2 {
    margin: 0;
    color: var(--gh-fg-default);
    font-size: 18px;
    line-height: 1.4;
}

.gh-card-header p {
    margin: 4px 0 0;
    color: var(--gh-fg-muted);
    font-size: 13px;
    line-height: 1.55;
}

.gh-card-body {
    padding: 24px;
}

.gh-fields {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 18px;
}

.gh-field-full {
    grid-column: 1 / -1;
}

.gh-label {
    display: block;
    margin: 0 0 7px;
    color: var(--gh-fg-default);
    font-size: 14px;
    font-weight: 600;
}

.gh-field-help {
    margin: 6px 0 0;
    color: var(--gh-fg-muted);
    font-size: 12px;
    line-height: 1.5;
}

.gh-input,
.gh-select,
.gh-textarea {
    display: block;
    width: 100%;
    border: 1px solid var(--gh-border-default);
    border-radius: 6px;
    background: #fff;
    color: var(--gh-fg-default);
    font: 400 14px/20px -apple-system, BlinkMacSystemFont, "Segoe UI", Helvetica, Arial, sans-serif;
    transition: border-color .15s ease, box-shadow .15s ease;
}

.gh-input,
.gh-select {
    min-height: 34px;
    padding: 5px 12px;
}

.gh-textarea {
    min-height: 205px;
    padding: 10px 12px;
    line-height: 1.55;
    resize: vertical;
}

.gh-input[type="file"] {
    padding: 7px 10px;
    color: var(--gh-fg-muted);
}

.gh-input::file-selector-button {
    margin-right: 10px;
    padding: 5px 9px;
    border: 1px solid rgba(27, 31, 36, .15);
    border-radius: 5px;
    background: #f6f8fa;
    color: var(--gh-fg-default);
    cursor: pointer;
    font: 600 12px/18px -apple-system, BlinkMacSystemFont, "Segoe UI", Helvetica, Arial, sans-serif;
}

.gh-info-box {
    display: flex;
    gap: 10px;
    padding: 12px;
    border: 1px solid var(--gh-border-muted);
    border-radius: 7px;
    background: var(--gh-canvas-subtle);
    color: var(--gh-fg-muted);
    font-size: 13px;
    line-height: 1.5;
    word-break: break-word;
}

.gh-info-box strong {
    color: var(--gh-fg-default);
}

.gh-info-icon {
    color: var(--gh-accent-fg);
    font-weight: 700;
}

/* Platform cards */
.gh-platform-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 16px;
}

.gh-platform-card {
    overflow: hidden;
    border: 1px solid var(--gh-border-default);
    border-radius: var(--gh-radius);
    background: var(--gh-canvas-default);
    transition: border-color .15s ease, box-shadow .15s ease, transform .15s ease;
}

.gh-platform-card:hover {
    border-color: #8c959f;
    box-shadow: var(--gh-shadow-medium);
    transform: translateY(-1px);
}

.gh-platform-card.is-unavailable {
    background: var(--gh-canvas-subtle);
    opacity: .78;
}

.gh-platform-card-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 14px 16px;
    border-bottom: 1px solid var(--gh-border-muted);
    background: var(--gh-canvas-subtle);
}

.gh-platform-check {
    display: flex;
    align-items: center;
    gap: 9px;
    min-width: 0;
    cursor: pointer;
}

.gh-platform-check input {
    width: 16px;
    height: 16px;
    margin: 0;
    accent-color: var(--gh-accent-emphasis);
}

.gh-platform-mark {
    display: grid;
    width: 25px;
    height: 25px;
    place-items: center;
    border: 1px solid var(--gh-border-default);
    border-radius: 6px;
    background: #fff;
    color: var(--gh-fg-default);
    font-size: 12px;
    font-weight: 700;
}

.gh-platform-name {
    overflow: hidden;
    color: var(--gh-fg-default);
    font-size: 14px;
    font-weight: 700;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.gh-mapping-state {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    max-width: 46%;
    overflow: hidden;
    padding: 3px 7px;
    border: 1px solid var(--gh-border-default);
    border-radius: 999px;
    background: #fff;
    color: var(--gh-fg-muted);
    font-size: 11px;
    font-weight: 600;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.gh-mapping-state.is-mapped {
    border-color: #a7f3d0;
    background: var(--gh-success-muted);
    color: var(--gh-success-fg);
}

.gh-platform-card-body {
    padding: 16px;
}

.gh-caption-meta {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin: 8px 0 0;
    color: var(--gh-fg-muted);
    font-size: 12px;
}

.gh-count.is-over-limit {
    color: var(--gh-danger-fg);
    font-weight: 700;
}

/* Footer action panel */
.gh-submit-panel {
    position: sticky;
    bottom: 16px;
    z-index: 20;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 18px;
    margin-top: 22px;
    padding: 14px 16px;
    border: 1px solid var(--gh-border-default);
    border-radius: var(--gh-radius-large);
    background: rgba(255, 255, 255, .96);
    box-shadow: 0 8px 24px rgba(140, 149, 159, .22);
    backdrop-filter: blur(10px);
}

.gh-submit-copy {
    min-width: 0;
}

.gh-submit-title {
    margin: 0;
    color: var(--gh-fg-default);
    font-size: 14px;
    font-weight: 700;
}

.gh-submit-note {
    margin: 3px 0 0;
    color: var(--gh-fg-muted);
    font-size: 12px;
    line-height: 1.45;
}

.gh-submit-actions {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 8px;
    flex-wrap: wrap;
}

@media (max-width: 900px) {
    .gh-profile-overview-main {
        grid-template-columns: 1fr;
    }

    .gh-profile-side {
        border-top: 1px solid var(--gh-border-default);
        border-left: 0;
    }

    .gh-platform-grid {
        grid-template-columns: 1fr;
    }

    .gh-photo-card-manager {
        grid-template-columns: 1fr;
    }

    .gh-photo-card-preview {
        max-width: 360px;
    }
}

@media (max-width: 720px) {
    .gh-social-page {
        padding: 18px 12px 36px;
    }

    .gh-page-heading,
    .gh-submit-panel {
        flex-direction: column;
        align-items: stretch;
    }

    .gh-page-actions,
    .gh-submit-actions {
        justify-content: flex-start;
    }

    .gh-profile-primary {
        align-items: flex-start;
        padding: 18px;
    }

    .gh-avatar {
        width: 70px;
        height: 70px;
        flex-basis: 70px;
    }

    .gh-profile-name {
        font-size: 22px;
    }

    .gh-status-row,
    .gh-card-header,
    .gh-card-body {
        padding-right: 16px;
        padding-left: 16px;
    }

    .gh-fields {
        grid-template-columns: 1fr;
    }

    .gh-field-full {
        grid-column: auto;
    }
}

/* English / Bangla campaign workspace switch */
.gh-language-workspace {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
    margin: 0 0 18px;
    padding: 12px;
    border: 1px solid var(--gh-border-default);
    border-radius: var(--gh-radius);
    background: var(--gh-canvas-default);
    box-shadow: var(--gh-shadow-small);
}

.gh-language-label {
    margin-right: 2px;
    color: var(--gh-fg-muted);
    font-size: 13px;
    font-weight: 700;
}

.gh-language-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 34px;
    padding: 6px 13px;
    border: 1px solid var(--gh-border-default);
    border-radius: 6px;
    background: var(--gh-canvas-subtle);
    color: var(--gh-fg-default);
    cursor: pointer;
    font: 700 13px/20px -apple-system, BlinkMacSystemFont, "Segoe UI", Helvetica, Arial, sans-serif;
}

.gh-language-button:hover {
    border-color: #8c959f;
    background: #eef1f4;
}

.gh-language-button.is-active {
    border-color: var(--gh-accent-fg);
    background: var(--gh-accent-fg);
    color: #fff;
    box-shadow: 0 1px 0 rgba(27, 31, 36, .1);
}

.gh-language-help {
    width: 100%;
    margin: 1px 0 0;
    color: var(--gh-fg-muted);
    font-size: 12px;
    line-height: 1.5;
}

.gh-lang-section[hidden] {
    display: none !important;
}

.gh-language-badge {
    display: inline-flex;
    align-items: center;
    min-height: 24px;
    padding: 2px 8px;
    border: 1px solid #54aeff;
    border-radius: 999px;
    background: #ddf4ff;
    color: var(--gh-accent-fg);
    font-size: 11px;
    font-weight: 700;
}

.gh-submit-language {
    color: var(--gh-accent-fg);
    font-weight: 700;
}
</style>

<div class="gh-social-page">
    <?php medic_social_show_flash(); ?>

    <header class="gh-page-heading">
        <div>
            <nav class="gh-breadcrumb" aria-label="Breadcrumb">
                <a href="social-posts.php">Social Posts</a>
                <span class="gh-breadcrumb-separator">/</span>
                <span>Doctor Campaign</span>
            </nav>

            <h1 class="gh-page-title">Create social campaign</h1>
            <p class="gh-page-summary">
                Review the doctor profile, tailor captions per network, then publish now, queue, or schedule through Buffer.
            </p>
        </div>

        <div class="gh-page-actions">
            <a class="gh-btn" href="social-posts.php">All Social Posts</a>
            <a class="gh-btn" href="doctor-form.php?id=<?= e((string) $doctor_id) ?>">Edit Doctor</a>
        </div>
    </header>

    <section class="gh-profile-overview" aria-label="Doctor profile summary">
        <div class="gh-profile-overview-main">
            <div class="gh-profile-primary">
                <div class="gh-avatar">
                    <?php if ($doctor_profile_image !== ''): ?>
                        <img src="<?= e($doctor_profile_image) ?>" alt="<?= e((string) ($doctor['name'] ?? 'Doctor')) ?>">
                    <?php else: ?>
                        <div class="gh-avatar-placeholder">
                            <?= e(strtoupper(substr(trim((string) ($doctor['name'] ?? 'D')), 0, 1))) ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="gh-profile-copy">
                    <h2 class="gh-profile-name"><?= e((string) ($doctor['name'] ?? 'Doctor')) ?></h2>

                    <?php $doctor_meta = implode(' · ', array_filter([
                        (string) ($doctor['degree'] ?? ''),
                        (string) ($doctor['designation'] ?? ''),
                        (string) ($doctor['specialty_name'] ?? ''),
                    ])); ?>

                    <?php if ($doctor_meta !== ''): ?>
                        <p class="gh-profile-meta"><?= e($doctor_meta) ?></p>
                    <?php endif; ?>

                    <?php $doctor_hospital = (string) (
                        $doctor['primary_hospital']
                        ?? $doctor['hospital_name']
                        ?? ''
                    ); ?>

                    <?php if (trim($doctor_hospital) !== ''): ?>
                        <p class="gh-profile-hospital"><?= e($doctor_hospital) ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <aside class="gh-profile-side">
                <span class="gh-side-label">Public doctor profile</span>

                <?php if (!empty($doctor['profile_url'])): ?>
                    <a
                        class="gh-profile-url"
                        href="<?= e((string) $doctor['profile_url']) ?>"
                        target="_blank"
                        rel="noopener"
                        title="<?= e((string) $doctor['profile_url']) ?>"
                    >
                        <?= e((string) $doctor['profile_url']) ?>
                    </a>
                <?php else: ?>
                    <span class="gh-profile-url gh-profile-url-empty">Profile link unavailable</span>
                <?php endif; ?>
            </aside>
        </div>

        <div class="gh-status-row">
            <span class="gh-status <?= $buffer_ready ? 'is-ok' : 'is-bad' ?>">
                <span class="gh-status-dot"></span>
                Buffer API: <?= $buffer_ready ? 'Ready' : 'Key missing' ?>
            </span>

            <span class="gh-status <?= $mapped_count > 0 ? 'is-ok' : 'is-bad' ?>">
                <span class="gh-status-dot"></span>
                Channels mapped: <?= e((string) $mapped_count) ?>/<?= e((string) count($platforms)) ?>
            </span>

            <span id="photo_card_status" class="gh-status <?= !empty($doctor_photo_card['exists']) ? 'is-ok' : 'is-bad' ?>">
                <span class="gh-status-dot"></span>
                Branded card: <?= !empty($doctor_photo_card['exists']) ? 'Ready' : 'Not generated' ?>
            </span>

            <span id="campaign_image_status" class="gh-status <?= $campaign_default_image_url !== '' ? 'is-ok' : 'is-bad' ?>">
                <span class="gh-status-dot"></span>
                Campaign image: <?= e($campaign_default_image_source) ?>
            </span>
        </div>
    </section>

    <?php if (!$buffer_ready): ?>
        <div class="gh-notice gh-notice-warning">
            <span class="gh-notice-icon">!</span>
            <div>
                <strong>Buffer API key is not configured.</strong>
                Add the new private key in <code>config/social-config.php</code> or your server environment, then refresh this page.
            </div>
        </div>
    <?php endif; ?>

    <?php if ($mapped_count === 0): ?>
        <div class="gh-notice gh-notice-warning">
            <span class="gh-notice-icon">!</span>
            <div>
                <strong>No social channel is mapped yet.</strong>
                <a href="social-posts.php">Open Social Post settings</a> and map your Facebook, Instagram, LinkedIn, or X channel.
            </div>
        </div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" novalidate>
        <input type="hidden" name="csrf_token" value="<?= e(medic_social_csrf_token()) ?>">
        <input type="hidden" name="action" value="publish_doctor">
        <input type="hidden" name="doctor_id" value="<?= e((string) $doctor_id) ?>">
        <input type="hidden" id="post_language" name="post_language" value="en">

        <div class="gh-language-workspace" role="tablist" aria-label="Post language">
            <span class="gh-language-label">Create separate campaign:</span>

            <button
                class="gh-language-button is-active"
                type="button"
                data-post-language="en"
                role="tab"
                aria-selected="true"
            >
                English Post
            </button>

            <button
                class="gh-language-button"
                type="button"
                data-post-language="bn"
                role="tab"
                aria-selected="false"
            >
                Bangla Post
            </button>

            <p class="gh-language-help">
                English and Bangla are saved and sent as separate Buffer campaigns. Choose one language, review that version, and send it to Buffer.
            </p>
        </div>

        <section class="gh-form-card">
            <div class="gh-card-header">
                <div class="gh-card-heading">
                    <span class="gh-step">1</span>
                    <div>
                        <h2>Campaign settings</h2>
                        <p>The selected language uses its own title, public profile URL, branded card, and caption set.</p>
                    </div>
                </div>
                <span class="gh-language-badge" id="active_language_badge">English campaign</span>
            </div>

            <div class="gh-card-body">
                <div class="gh-fields">
                    <div class="gh-field-full" data-language-section="en">
                        <label class="gh-label" for="campaign_title_en">Campaign title (English)</label>
                        <input
                            class="gh-input"
                            id="campaign_title_en"
                            name="title_en"
                            value="<?= e((string) $campaign_titles['en']) ?>"
                        >
                    </div>

                    <div class="gh-field-full" data-language-section="bn" hidden>
                        <label class="gh-label" for="campaign_title_bn">Campaign title (Bangla post)</label>
                        <input
                            class="gh-input"
                            id="campaign_title_bn"
                            name="title_bn"
                            value="<?= e((string) $campaign_titles['bn']) ?>"
                        >
                    </div>

                    <div>
                        <label class="gh-label" for="publish_mode">Publishing mode</label>
                        <select class="gh-select" id="publish_mode" name="publish_mode">
                            <option value="shareNow">Publish now</option>
                            <option value="addToQueue" selected>Add to Buffer queue</option>
                            <option value="customScheduled">Schedule exact time</option>
                        </select>
                        <p class="gh-field-help">Buffer performs the actual delivery to each connected profile.</p>
                    </div>

                    <div id="schedule_field" style="display:none">
                        <label class="gh-label" for="scheduled_at">Schedule time</label>
                        <input
                            class="gh-input"
                            id="scheduled_at"
                            type="datetime-local"
                            name="scheduled_at"
                        >
                        <p class="gh-field-help">Time zone: Bangladesh (Asia/Dhaka).</p>
                    </div>

                    <div class="gh-field-full">
                        <div class="gh-photo-card-manager" id="photo_card_manager">
                            <div class="gh-photo-card-preview">
                                <?php if (($campaign_default_images['en'] ?? '') !== ''): ?>
                                    <img
                                        id="photo_card_preview"
                                        src="<?= e((string) $campaign_default_images['en']) ?>"
                                        alt="<?= e((string) ($doctor_photo_cards['en']['title'] ?? 'Doctor Photo Card')) ?>"
                                    >
                                <?php else: ?>
                                    <div class="gh-photo-card-placeholder" id="photo_card_placeholder">
                                        <?= e(strtoupper(substr(trim((string) ($doctor['name'] ?? 'D')), 0, 1))) ?>
                                    </div>
                                    <img id="photo_card_preview" src="" alt="" style="display:none">
                                <?php endif; ?>
                            </div>

                            <div class="gh-photo-card-copy">
                                <div class="gh-photo-card-eyebrow">
                                    <span class="gh-photo-card-dot"></span>
                                    <span id="photo_card_state">
                                        <?= !empty($doctor_photo_cards['en']['exists']) ? 'English branded card is ready' : 'No English branded card saved yet' ?>
                                    </span>
                                </div>

                                <h3 id="photo_card_heading">English Doctor Photo Card</h3>
                                <p id="photo_card_description">
                                    This 1200 × 630 JPG shows name, degree, specialty, designation, and workplace without field labels.
                                </p>

                                <p class="gh-photo-card-file">
                                    <strong>Filename:</strong>
                                    <code id="photo_card_filename"><?= e((string) ($doctor_photo_cards['en']['filename'] ?? 'doctor-profile-card.jpg')) ?></code>
                                </p>

                                <div class="gh-photo-card-actions">
                                    <button class="gh-btn" id="generate_photo_card" type="button">
                                        <?= !empty($doctor_photo_cards['en']['exists']) ? 'Regenerate English Card' : 'Generate English Card' ?>
                                    </button>

                                    <a
                                        class="gh-btn"
                                        id="open_photo_card"
                                        href="<?= e((string) ($doctor_photo_cards['en']['url'] ?? '#')) ?>"
                                        target="_blank"
                                        rel="noopener"
                                        <?= empty($doctor_photo_cards['en']['exists']) ? 'style="display:none"' : '' ?>
                                    >
                                        Open Card
                                    </a>
                                </div>

                                <p class="gh-field-help" id="photo_card_message">
                                    English and Bangla cards are separate files, stored in separate public folders.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="gh-field-full" data-language-section="en">
                        <label class="gh-label" for="image_url_en">Public image URL (English post)</label>
                        <input
                            class="gh-input"
                            id="image_url_en"
                            type="url"
                            name="image_url_en"
                            value="<?= e((string) ($campaign_default_images['en'] ?? '')) ?>"
                            placeholder="https://medic.bd/uploads/doctor-cards/doctor-card.jpg"
                        >
                        <p class="gh-field-help">The English branded photo card is selected automatically when it exists.</p>
                    </div>

                    <div class="gh-field-full" data-language-section="bn" hidden>
                        <label class="gh-label" for="image_url_bn">Public image URL (Bangla Post)</label>
                        <input
                            class="gh-input"
                            id="image_url_bn"
                            type="url"
                            name="image_url_bn"
                            value="<?= e((string) ($campaign_default_images['bn'] ?? '')) ?>"
                            placeholder="https://medic.bd/uploads/bn-doctor-cards/doctor-card.jpg"
                        >
                        <p class="gh-field-help">The Bangla branded photo card is selected automatically when it exists.</p>
                    </div>

                    <div class="gh-field-full">
                        <label class="gh-label" for="social_image">Upload replacement image</label>
                        <input
                            class="gh-input"
                            id="social_image"
                            type="file"
                            name="social_image"
                            accept="image/jpeg,image/png,image/webp"
                        >
                        <p class="gh-field-help">Supported formats: JPG, PNG, or WEBP. The upload overrides the image only for the active language campaign.</p>
                    </div>

                    <div class="gh-field-full">
                        <div class="gh-info-box">
                            <span class="gh-info-icon">i</span>
                            <div>
                                <strong id="profile_url_label">English doctor profile URL</strong><br>
                                <span id="profile_url_value"><?= e((string) ($doctor_photo_cards['en']['profile_url'] ?? 'Not available')) ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="gh-form-card">
            <div class="gh-card-header">
                <div class="gh-card-heading">
                    <span class="gh-step">2</span>
                    <div>
                        <h2>Choose platforms and refine captions</h2>
                        <p>Each platform receives its own professional caption template in the selected language. Hashtags remain English: Facebook 3, Instagram 5, LinkedIn 3, and X 0.</p>
                    </div>
                </div>
            </div>

            <div class="gh-card-body">
                <div class="gh-platform-grid">
                    <?php foreach ($platforms as $platform_key => $platform): ?>
                        <?php
                        $channel = $configured_channels[$platform_key] ?? [];
                        $is_mapped = !empty($channel['configured']);
                        $platform_marks = [
                            'facebook' => 'f',
                            'instagram' => '◎',
                            'linkedin' => 'in',
                            'x' => '𝕏',
                        ];
                        $platform_mark = $platform_marks[$platform_key] ?? strtoupper(substr((string) $platform_key, 0, 1));
                        ?>
                        <article class="gh-platform-card <?= $is_mapped ? '' : 'is-unavailable' ?>">
                            <div class="gh-platform-card-head">
                                <label class="gh-platform-check">
                                    <input
                                        type="checkbox"
                                        name="targets[]"
                                        value="<?= e($platform_key) ?>"
                                        <?= $is_mapped ? 'checked' : 'disabled' ?>
                                    >
                                    <span class="gh-platform-mark"><?= e($platform_mark) ?></span>
                                    <span class="gh-platform-name"><?= e((string) $platform['label']) ?></span>
                                </label>

                                <span class="gh-mapping-state <?= $is_mapped ? 'is-mapped' : '' ?>">
                                    <?= e(
                                        $is_mapped
                                            ? (
                                                trim((string) ($channel['channel_name'] ?? '')) !== ''
                                                    ? (string) $channel['channel_name']
                                                    : 'Mapped'
                                            )
                                            : 'Not mapped'
                                    ) ?>
                                </span>
                            </div>

                            <div class="gh-platform-card-body">
                                <div data-language-section="en">
                                    <label class="gh-label" for="caption_<?= e($platform_key) ?>_en">English caption</label>
                                    <textarea
                                        class="gh-textarea"
                                        id="caption_<?= e($platform_key) ?>_en"
                                        name="caption_<?= e($platform_key) ?>_en"
                                        data-platform="<?= e($platform_key) ?>"
                                        data-language="en"
                                        data-limit="<?= $platform_key === 'x' ? '280' : '' ?>"
                                    ><?= e((string) ($captions_en[$platform_key] ?? '')) ?></textarea>

                                    <div class="gh-caption-meta">
                                        <span><?= $platform_key === 'x' ? 'X uses a concise professional caption with no hashtags and a 280-character limit.' : 'Uses a professional platform-specific English caption with the configured hashtag count. You may edit before publishing.' ?></span>
                                        <span class="gh-count" data-count-for="<?= e($platform_key) ?>-en">0<?= $platform_key === 'x' ? '/280' : '' ?></span>
                                    </div>
                                </div>

                                <div data-language-section="bn" hidden>
                                    <label class="gh-label" for="caption_<?= e($platform_key) ?>_bn">Bangla caption</label>
                                    <textarea
                                        class="gh-textarea"
                                        id="caption_<?= e($platform_key) ?>_bn"
                                        name="caption_<?= e($platform_key) ?>_bn"
                                        data-platform="<?= e($platform_key) ?>"
                                        data-language="bn"
                                        data-limit="<?= $platform_key === 'x' ? '280' : '' ?>"
                                    ><?= e((string) ($captions_bn[$platform_key] ?? '')) ?></textarea>

                                    <div class="gh-caption-meta">
                                        <span><?= $platform_key === 'x' ? 'X uses a concise Bangla caption with no hashtags. Keep it under 280 characters.' : 'Uses a professional platform-specific Bangla caption. Hashtags remain English.' ?></span>
                                        <span class="gh-count" data-count-for="<?= e($platform_key) ?>-bn">0<?= $platform_key === 'x' ? '/280' : '' ?></span>
                                    </div>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>

                <div class="gh-submit-panel">
                    <div class="gh-submit-copy">
                        <p class="gh-submit-title" id="submit_title">Ready to send English post?</p>
                        <p class="gh-submit-note" id="submit_note">
                            Only the English title, card, profile URL, and captions will be sent. Switch to Bangla Post to create a separate Bangla campaign.
                        </p>
                    </div>

                    <div class="gh-submit-actions">
                        <a class="gh-btn" href="doctor-form.php?id=<?= e((string) $doctor_id) ?>">Back to Doctor</a>
                        <a class="gh-btn" href="social-post-history.php">Publishing History</a>
                        <button class="gh-btn gh-btn-primary" id="submit_post_button" type="submit">Send English Post to Buffer</button>
                    </div>
                </div>
            </div>
        </section>
    </form>
</div>

<script>
(function () {
    var activeLanguage = 'en';
    var postLanguageInput = document.getElementById('post_language');
    var languageButtons = document.querySelectorAll('[data-post-language]');
    var languageSections = document.querySelectorAll('[data-language-section]');
    var languageBadge = document.getElementById('active_language_badge');
    var submitTitle = document.getElementById('submit_title');
    var submitNote = document.getElementById('submit_note');
    var submitButton = document.getElementById('submit_post_button');

    var mode = document.getElementById('publish_mode');
    var scheduleField = document.getElementById('schedule_field');
    var scheduleInput = document.getElementById('scheduled_at');

    var generateButton = document.getElementById('generate_photo_card');
    var previewImage = document.getElementById('photo_card_preview');
    var previewPlaceholder = document.getElementById('photo_card_placeholder');
    var statusBadge = document.getElementById('photo_card_status');
    var campaignImageStatus = document.getElementById('campaign_image_status');
    var cardState = document.getElementById('photo_card_state');
    var cardHeading = document.getElementById('photo_card_heading');
    var cardDescription = document.getElementById('photo_card_description');
    var cardFilename = document.getElementById('photo_card_filename');
    var cardMessage = document.getElementById('photo_card_message');
    var openCard = document.getElementById('open_photo_card');
    var profileUrlLabel = document.getElementById('profile_url_label');
    var profileUrlValue = document.getElementById('profile_url_value');

    var languageData = <?= json_encode([
        'en' => [
            'card' => $doctor_photo_cards['en'],
            'defaultImage' => (string) ($campaign_default_images['en'] ?? ''),
            'imageSource' => (string) ($campaign_default_image_sources['en'] ?? ''),
            'copy' => [
                'badge' => 'English campaign',
                'heading' => 'English Doctor Photo Card',
                'degreeLabel' => 'Degree',
                'designationLabel' => 'Designation',
                'description' => 'This 1200 × 630 JPG is selected automatically for the English Buffer campaign.',
                'ready' => 'English branded card is ready',
                'missing' => 'No English branded card saved yet',
                'generate' => 'Generate English Card',
                'regenerate' => 'Regenerate English Card',
                'profileLabel' => 'English doctor profile URL',
                'submitTitle' => 'Ready to send English post?',
                'submitNote' => 'Only the English title, card, profile URL, and captions will be sent. Switch to Bangla Post to create a separate Bangla campaign.',
                'submitButton' => 'Send English Post to Buffer',
                'generating' => 'Generating English card…',
                'saving' => 'Creating the 1200 × 630 English branded JPG and saving it to the public doctor-card URL.',
                'saved' => 'English branded card saved successfully. It is now selected for this Buffer campaign.',
                'findDoctors' => 'Find trusted doctors in Bangladesh',
                'doctorProfile' => 'Doctor Profile',
                'primaryHospital' => 'Workplace',
                'chamberLabel' => 'Chamber',
                'footer' => 'View profile and appointment information at medic.bd',
            ],
        ],
        'bn' => [
            'card' => $doctor_photo_cards['bn'],
            'defaultImage' => (string) ($campaign_default_images['bn'] ?? ''),
            'imageSource' => (string) ($campaign_default_image_sources['bn'] ?? ''),
            'copy' => [
                /* Admin UI remains English; the generated Bangla card uses the Bangla labels below. */
                'badge' => 'Bangla campaign',
                'heading' => 'Bangla Doctor Photo Card',
                'degreeLabel' => 'ডিগ্রি',
                'designationLabel' => 'পদবি',
                'description' => 'This 1200 × 630 JPG shows name, degree, specialty, designation, and workplace without field labels.',
                'ready' => 'Bangla branded card is ready',
                'missing' => 'No Bangla branded card saved yet',
                'generate' => 'Generate Bangla Card',
                'regenerate' => 'Regenerate Bangla Card',
                'profileLabel' => 'Bangla doctor profile URL',
                'submitTitle' => 'Ready to send Bangla post?',
                'submitNote' => 'Only the Bangla title, card, profile URL, and captions will be sent. Switch to English Post to create a separate English campaign.',
                'submitButton' => 'Send Bangla Post to Buffer',
                'generating' => 'Generating Bangla card…',
                'saving' => 'Creating the 1200 × 630 Bangla branded JPG and saving it to the public doctor-card URL.',
                'saved' => 'Bangla branded card saved successfully. It is now selected for this Buffer campaign.',
                'findDoctors' => 'বাংলাদেশের বিশ্বস্ত ডাক্তার খুঁজুন',
                'doctorProfile' => 'ডাক্তার প্রোফাইল',
                'primaryHospital' => 'কর্মস্থল',
                'chamberLabel' => 'চেম্বার',
                'footer' => 'প্রোফাইল ও অ্যাপয়েন্টমেন্ট তথ্য দেখুন medic.bd-তে',
            ],
        ],
        'endpoint' => function_exists('site_url')
            ? site_url('doctor/generate-doctor-card.php')
            : '../doctor/generate-doctor-card.php',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

    function getLanguageData() {
        return languageData[activeLanguage] || languageData.en;
    }

    function updateScheduleField() {
        if (!mode || !scheduleField || !scheduleInput) {
            return;
        }

        var isScheduled = mode.value === 'customScheduled';
        scheduleField.style.display = isScheduled ? 'block' : 'none';
        scheduleInput.required = isScheduled;
    }

    function updateCount(textarea) {
        var platform = textarea.getAttribute('data-platform');
        var language = textarea.getAttribute('data-language');
        var counter = document.querySelector('[data-count-for="' + platform + '-' + language + '"]');

        if (!counter) {
            return;
        }

        var current = textarea.value.length;
        var limit = parseInt(textarea.getAttribute('data-limit') || '0', 10);

        counter.textContent = limit > 0 ? current + '/' + limit : current + ' characters';

        if (limit > 0 && current > limit) {
            counter.classList.add('is-over-limit');
        } else {
            counter.classList.remove('is-over-limit');
        }
    }

    function setCardMessage(message, type) {
        if (!cardMessage) {
            return;
        }

        cardMessage.textContent = message;
        cardMessage.classList.remove('is-error', 'is-success');

        if (type) {
            cardMessage.classList.add(type);
        }
    }

    function imageInputFor(language) {
        return document.getElementById('image_url_' + language);
    }

    function updatePreview(url) {
        if (!url || !previewImage) {
            return;
        }

        previewImage.src = url;
        previewImage.style.display = 'block';

        if (previewPlaceholder) {
            previewPlaceholder.style.display = 'none';
        }
    }

    function updateLanguageWorkspace(language) {
        activeLanguage = language === 'bn' ? 'bn' : 'en';

        if (postLanguageInput) {
            postLanguageInput.value = activeLanguage;
        }

        for (var i = 0; i < languageButtons.length; i++) {
            var isActive = languageButtons[i].getAttribute('data-post-language') === activeLanguage;
            languageButtons[i].classList.toggle('is-active', isActive);
            languageButtons[i].setAttribute('aria-selected', isActive ? 'true' : 'false');
        }

        for (var j = 0; j < languageSections.length; j++) {
            languageSections[j].hidden = languageSections[j].getAttribute('data-language-section') !== activeLanguage;
        }

        var current = getLanguageData();
        var card = current.card || {};
        var copy = current.copy || {};
        var activeImageInput = imageInputFor(activeLanguage);

        if (languageBadge) {
            languageBadge.textContent = copy.badge || '';
        }

        if (submitTitle) {
            submitTitle.textContent = copy.submitTitle || '';
        }

        if (submitNote) {
            submitNote.textContent = copy.submitNote || '';
        }

        if (submitButton) {
            submitButton.textContent = copy.submitButton || 'Send to Buffer';
        }

        if (cardState) {
            cardState.textContent = card.exists ? copy.ready : copy.missing;
        }

        if (cardHeading) {
            cardHeading.textContent = copy.heading || 'Doctor Photo Card';
        }

        if (cardDescription) {
            cardDescription.textContent = copy.description || '';
        }

        if (cardFilename) {
            cardFilename.textContent = card.filename || 'doctor-profile-card.jpg';
        }

        if (generateButton) {
            generateButton.textContent = card.exists ? copy.regenerate : copy.generate;
        }

        if (profileUrlLabel) {
            profileUrlLabel.textContent = copy.profileLabel || 'Doctor profile URL';
        }

        if (profileUrlValue) {
            profileUrlValue.textContent = card.profile_url || 'Not available';
        }

        if (statusBadge) {
            statusBadge.className = 'gh-status ' + (card.exists ? 'is-ok' : 'is-bad');
            statusBadge.innerHTML = '<span class="gh-status-dot"></span>Branded card: ' + (card.exists ? 'Ready' : 'Not generated');
        }

        if (campaignImageStatus) {
            var hasImage = !!(current.defaultImage || (activeImageInput && activeImageInput.value));
            campaignImageStatus.className = 'gh-status ' + (hasImage ? 'is-ok' : 'is-bad');
            campaignImageStatus.innerHTML = '<span class="gh-status-dot"></span>Campaign image: ' + (current.imageSource || (hasImage ? 'Available' : 'Missing'));
        }

        if (openCard) {
            if (card.exists && card.url) {
                openCard.href = card.url;
                openCard.style.display = 'inline-flex';
            } else {
                openCard.href = '#';
                openCard.style.display = 'none';
            }
        }

        if (activeImageInput && !activeImageInput.value && current.defaultImage) {
            activeImageInput.value = current.defaultImage;
        }

        var visibleImage = activeImageInput && activeImageInput.value
            ? activeImageInput.value
            : current.defaultImage;

        if (visibleImage) {
            updatePreview(visibleImage);
        }

        setCardMessage(
            activeLanguage === 'bn'
                ? 'English and Bangla cards are separate public files and are used in separate Buffer campaigns.'
                : 'English and Bangla cards are separate public files and are used in separate Buffer campaigns.'
        );
    }

    function roundedRect(context, x, y, width, height, radius) {
        var safeRadius = Math.min(radius, width / 2, height / 2);

        context.beginPath();
        context.moveTo(x + safeRadius, y);
        context.arcTo(x + width, y, x + width, y + height, safeRadius);
        context.arcTo(x + width, y + height, x, y + height, safeRadius);
        context.arcTo(x, y + height, x, y, safeRadius);
        context.arcTo(x, y, x + width, y, safeRadius);
        context.arcTo(x, y, x + width, y, safeRadius);
        context.closePath();
    }

    function getInitials(name) {
        var parts = String(name || '').trim().split(/\s+/).filter(Boolean);

        if (!parts.length) {
            return 'D';
        }

        return parts.slice(0, 2).map(function (part) {
            return part.charAt(0);
        }).join('').toUpperCase();
    }

    function wrapText(context, text, maxWidth, maxLines) {
        var words = String(text || '').trim().split(/\s+/).filter(Boolean);
        var lines = [];
        var line = '';

        words.forEach(function (word) {
            var nextLine = line ? line + ' ' + word : word;

            if (context.measureText(nextLine).width <= maxWidth || line === '') {
                line = nextLine;
                return;
            }

            lines.push(line);
            line = word;
        });

        if (line) {
            lines.push(line);
        }

        if (lines.length > maxLines) {
            var clipped = lines.slice(0, maxLines);
            var lastLine = clipped[maxLines - 1];

            while (lastLine.length > 1 && context.measureText(lastLine + '…').width > maxWidth) {
                lastLine = lastLine.slice(0, -1);
            }

            clipped[maxLines - 1] = lastLine + '…';
            return clipped;
        }

        return lines;
    }

    function drawWrappedText(context, text, x, y, maxWidth, lineHeight, maxLines) {
        var lines = wrapText(context, text, maxWidth, maxLines);

        lines.forEach(function (line, index) {
            context.fillText(line, x, y + (index * lineHeight));
        });

        return y + (lines.length * lineHeight);
    }

    function ellipsizeText(context, text, maxWidth) {
        var value = String(text || '').trim();

        if (context.measureText(value).width <= maxWidth) {
            return value;
        }

        while (value.length > 1 && context.measureText(value + '…').width > maxWidth) {
            value = value.slice(0, -1);
        }

        return value + '…';
    }

    function loadImage(url) {
        return new Promise(function (resolve) {
            if (!url) {
                resolve(null);
                return;
            }

            var image = new Image();
            image.onload = function () { resolve(image); };
            image.onerror = function () { resolve(null); };
            image.src = url;
        });
    }

    function drawProfileImage(context, image, x, y, width, height, radius) {
        roundedRect(context, x, y, width, height, radius);
        context.save();
        context.clip();

        var imageRatio = image.naturalWidth / image.naturalHeight;
        var boxRatio = width / height;
        var drawWidth = width;
        var drawHeight = height;
        var drawX = x;
        var drawY = y;

        if (imageRatio > boxRatio) {
            drawWidth = height * imageRatio;
            drawX = x - ((drawWidth - width) / 2);
        } else {
            drawHeight = width / imageRatio;
            drawY = y - ((drawHeight - height) / 2);
        }

        context.drawImage(image, drawX, drawY, drawWidth, drawHeight);
        context.restore();
    }

    function drawPhotoPlaceholder(context, x, y, width, height, initials) {
        var gradient = context.createLinearGradient(x, y, x + width, y + height);
        gradient.addColorStop(0, '#8fc6ff');
        gradient.addColorStop(1, '#3b8fe7');

        roundedRect(context, x, y, width, height, 26);
        context.fillStyle = gradient;
        context.fill();

        context.fillStyle = '#ffffff';
        context.font = '700 86px "Segoe UI", "Noto Serif Bengali", Arial, sans-serif';
        context.textAlign = 'center';
        context.textBaseline = 'middle';
        context.fillText(initials, x + (width / 2), y + (height / 2));
        context.textAlign = 'left';
        context.textBaseline = 'alphabetic';
    }

    function drawMedicalMark(context, x, y, size) {
        context.save();
        context.fillStyle = 'rgba(47, 143, 240, 0.12)';
        context.beginPath();
        context.arc(x, y, size / 2, 0, Math.PI * 2);
        context.fill();

        context.strokeStyle = '#2f8ff0';
        context.lineWidth = 5;
        context.lineCap = 'round';
        context.beginPath();
        context.moveTo(x - (size * 0.18), y);
        context.lineTo(x + (size * 0.18), y);
        context.moveTo(x, y - (size * 0.18));
        context.lineTo(x, y + (size * 0.18));
        context.stroke();
        context.restore();
    }

    function drawDecorativePattern(context, width, height) {
        context.save();
        context.strokeStyle = 'rgba(47, 143, 240, 0.11)';
        context.lineWidth = 2;

        for (var index = 0; index < 7; index += 1) {
            var x = 760 + (index * 110);
            context.beginPath();
            context.arc(x, 95, 85 + (index * 12), Math.PI * 1.08, Math.PI * 1.78);
            context.stroke();
        }

        context.fillStyle = 'rgba(47, 143, 240, 0.10)';
        context.beginPath();
        context.arc(width - 70, height - 45, 210, 0, Math.PI * 2);
        context.fill();

        context.fillStyle = 'rgba(91, 166, 237, 0.07)';
        context.beginPath();
        context.arc(70, height - 80, 175, 0, Math.PI * 2);
        context.fill();
        context.restore();
    }

    function drawPhotoCardValue(context, value, x, y, width, font, color, lineHeight, maxLines) {
        value = String(value || '').trim();

        if (!value) {
            return y;
        }

        context.fillStyle = color;
        context.font = font;

        return drawWrappedText(context, value, x, y, width, lineHeight, maxLines);
    }

    function generateCardDataUrl() {
        var current = getLanguageData();
        var card = current.card || {};
        var copy = current.copy || {};

        return loadImage(card.source_image || '').then(function (profileImage) {
            var canvas = document.createElement('canvas');
            var width = 1200;
            var height = 630;
            var context = canvas.getContext('2d');

            if (!context) {
                throw new Error('Your browser could not create the photo card.');
            }

            canvas.width = width;
            canvas.height = height;

            var cardBackground = context.createLinearGradient(0, 0, width, height);
            cardBackground.addColorStop(0, '#f8fbff');
            cardBackground.addColorStop(0.48, '#eef7ff');
            cardBackground.addColorStop(1, '#d9edff');
            context.fillStyle = cardBackground;
            context.fillRect(0, 0, width, height);

            drawDecorativePattern(context, width, height);

            context.save();
            context.shadowColor = 'rgba(38, 90, 145, 0.14)';
            context.shadowBlur = 26;
            context.shadowOffsetY = 13;
            context.fillStyle = 'rgba(255, 255, 255, 0.97)';
            roundedRect(context, 30, 30, 1140, 570, 32);
            context.fill();
            context.restore();

            context.strokeStyle = 'rgba(110, 171, 231, 0.34)';
            context.lineWidth = 2;
            roundedRect(context, 30, 30, 1140, 570, 32);
            context.stroke();

            var leftPanel = context.createLinearGradient(30, 30, 376, 600);
            leftPanel.addColorStop(0, 'rgba(232, 245, 255, 0.98)');
            leftPanel.addColorStop(1, 'rgba(207, 232, 255, 0.98)');
            context.fillStyle = leftPanel;
            roundedRect(context, 30, 30, 350, 570, 32);
            context.fill();

            context.fillStyle = '#2f8ff0';
            roundedRect(context, 30, 30, 350, 10, 10);
            context.fill();

            context.fillStyle = '#1f5f9d';
            context.font = '700 24px "Segoe UI", "Noto Serif Bengali", Arial, sans-serif';
            context.fillText('MedicBD', 74, 88);

            context.fillStyle = '#6387aa';
            context.font = '500 15px "Segoe UI", "Noto Serif Bengali", Arial, sans-serif';
            context.fillText(copy.doctorProfile || 'Doctor Profile', 75, 116);

            drawMedicalMark(context, 323, 92, 54);

            var photoX = 74;
            var photoY = 151;
            var photoWidth = 262;
            var photoHeight = 326;

            context.save();
            context.shadowColor = 'rgba(36, 97, 157, 0.16)';
            context.shadowBlur = 18;
            context.shadowOffsetY = 9;
            context.fillStyle = '#ffffff';
            roundedRect(context, photoX - 7, photoY - 7, photoWidth + 14, photoHeight + 14, 30);
            context.fill();
            context.restore();

            var photoDrawn = false;

            if (profileImage && profileImage.naturalWidth > 0) {
                drawProfileImage(context, profileImage, photoX, photoY, photoWidth, photoHeight, 24);
                photoDrawn = true;
            }

            if (!photoDrawn) {
                drawPhotoPlaceholder(context, photoX, photoY, photoWidth, photoHeight, getInitials(card.name));
            }

            context.fillStyle = '#6387aa';
            context.font = '500 15px "Segoe UI", "Noto Serif Bengali", Arial, sans-serif';
            context.fillText('MEDICBD', 75, 530);

            context.fillStyle = '#234566';
            context.font = '700 19px "Segoe UI", "Noto Serif Bengali", Arial, sans-serif';
            drawWrappedText(context, card.name, 75, 560, 260, 25, 2);

            var mainX = 431;
            var mainWidth = 674;

            context.fillStyle = '#2f8ff0';
            context.font = '600 16px "Segoe UI", "Noto Serif Bengali", Arial, sans-serif';
            context.fillText(copy.doctorProfile || 'Doctor Profile', mainX, 92);

            /*
             * Photo-card text order (labels and chamber are intentionally absent):
             * 1. Doctor name
             * 2. Degree
             * 3. Specialty
             * 4. Designation
             * 5. Primary hospital / workplace
             *
             * Empty vertical space between degree, specialty and designation
             * visually matches the requested <br> structure.
             */
            context.fillStyle = '#1b3855';
            context.font = '700 36px "Segoe UI", "Noto Serif Bengali", Arial, sans-serif';
            var nameEndY = drawWrappedText(context, card.name || card.title, mainX, 148, mainWidth, 45, 2);

            context.fillStyle = '#2f8ff0';
            roundedRect(context, mainX, nameEndY + 12, 92, 5, 3);
            context.fill();

            var contentY = Math.max(258, nameEndY + 49);

            contentY = drawPhotoCardValue(
                context,
                card.degree,
                mainX,
                contentY,
                mainWidth,
                '500 21px "Segoe UI", "Noto Serif Bengali", Arial, sans-serif',
                '#294862',
                28,
                2
            );

            contentY += 21;

            contentY = drawPhotoCardValue(
                context,
                card.specialty,
                mainX,
                contentY,
                mainWidth,
                '700 23px "Segoe UI", "Noto Serif Bengali", Arial, sans-serif',
                '#2f8ff0',
                30,
                2
            );

            contentY += 21;

            contentY = drawPhotoCardValue(
                context,
                card.designation,
                mainX,
                contentY,
                mainWidth,
                '500 21px "Segoe UI", "Noto Serif Bengali", Arial, sans-serif',
                '#294862',
                28,
                2
            );

            contentY += 10;

            drawPhotoCardValue(
                context,
                card.hospital,
                mainX,
                contentY,
                mainWidth,
                '500 21px "Segoe UI", "Noto Serif Bengali", Arial, sans-serif',
                '#294862',
                28,
                2
            );

            var footerY = 540;
            context.fillStyle = '#f2f8ff';
            roundedRect(context, mainX, footerY, mainWidth, 40, 12);
            context.fill();

            context.strokeStyle = '#dbe9f6';
            context.lineWidth = 1;
            roundedRect(context, mainX, footerY, mainWidth, 40, 12);
            context.stroke();

            context.fillStyle = '#47759f';
            context.font = '500 15px "Segoe UI", Arial, sans-serif';
            context.fillText(ellipsizeText(context, card.profile_url || '', mainWidth - 38), mainX + 18, footerY + 26);

            try {
                return canvas.toDataURL('image/jpeg', 0.94);
            } catch (error) {
                throw new Error('The source image cannot be used for card generation. Upload a same-domain doctor photo first.');
            }
        });
    }

    function generateActiveLanguageCard() {
        if (!generateButton) {
            return;
        }

        var current = getLanguageData();
        var card = current.card || {};
        var copy = current.copy || {};
        var originalText = generateButton.textContent;

        generateButton.disabled = true;
        generateButton.classList.add('is-loading');
        generateButton.textContent = copy.generating || 'Generating card…';
        setCardMessage(copy.saving || 'Generating photo card…');

        generateCardDataUrl()
            .then(function (dataUrl) {
                var payload = new URLSearchParams();

                payload.set('action', 'save');
                payload.set('lang', activeLanguage);
                payload.set('card_image', dataUrl);
                payload.set('title', card.title || 'Doctor Profile');
                payload.set('filename', card.filename || 'doctor-profile-card.jpg');
                payload.set('subject', card.subject || card.title || 'Doctor Profile');
                payload.set('tags', card.tags || 'Doctor Profile, MedicBD');
                payload.set('author', 'MedicBD');
                payload.set('rating', String(card.rating || 0));

                return fetch(languageData.endpoint, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
                        'Accept': 'application/json'
                    },
                    body: payload.toString(),
                    credentials: 'same-origin'
                });
            })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('Card save request failed (' + response.status + ').');
                }

                return response.json();
            })
            .then(function (result) {
                if (!result || !result.success || !result.url) {
                    throw new Error('The card could not be saved.');
                }

                var publicUrl = result.url + (result.url.indexOf('?') === -1 ? '?' : '&') + 'v=' + Date.now();
                var input = imageInputFor(activeLanguage);

                if (input) {
                    input.value = publicUrl;
                }

                current.defaultImage = publicUrl;
                current.imageSource = 'Branded Doctor Photo Card';
                current.card.exists = true;
                current.card.url = publicUrl;

                updatePreview(publicUrl);
                updateLanguageWorkspace(activeLanguage);
                setCardMessage(copy.saved || 'Branded card saved successfully.', 'is-success');
            })
            .catch(function (error) {
                setCardMessage(
                    error && error.message ? error.message : 'Unable to generate the branded card.',
                    'is-error'
                );
            })
            .finally(function () {
                generateButton.disabled = false;
                generateButton.classList.remove('is-loading');

                if (generateButton.textContent === (copy.generating || 'Generating card…')) {
                    generateButton.textContent = originalText;
                }
            });
    }

    if (mode) {
        mode.addEventListener('change', updateScheduleField);
        updateScheduleField();
    }

    var captions = document.querySelectorAll('.gh-textarea[data-platform][data-language]');
    for (var i = 0; i < captions.length; i++) {
        captions[i].addEventListener('input', function () {
            updateCount(this);
        });
        updateCount(captions[i]);
    }

    for (var j = 0; j < languageButtons.length; j++) {
        languageButtons[j].addEventListener('click', function () {
            updateLanguageWorkspace(this.getAttribute('data-post-language') || 'en');
        });
    }

    if (generateButton) {
        generateButton.addEventListener('click', generateActiveLanguageCard);
    }

    updateLanguageWorkspace('en');
}());
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
