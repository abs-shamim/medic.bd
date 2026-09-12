<?php
declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Prescription Module Functions
|--------------------------------------------------------------------------
*/

if (!function_exists('prescription_e')) {
    function prescription_e($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

function prescription_url(string $path = ''): string
{
    $path = ltrim($path, '/');
    $module_path = 'prescription' . ($path !== '' ? '/' . $path : '/');

    if (function_exists('site_url')) {
        return site_url($module_path);
    }

    return '/' . $module_path;
}

function prescription_user_url(string $path = 'dashboard.php'): string
{
    $path = ltrim($path, '/');

    if (function_exists('site_url')) {
        return site_url('user/' . $path);
    }

    return '/user/' . $path;
}

function prescription_redirect(string $path = ''): void
{
    $url = preg_match('#^https?://#i', $path)
        ? $path
        : prescription_url($path);

    if (!headers_sent()) {
        header('Location: ' . $url);
        exit;
    }

    echo '<script src="' . prescription_e(prescription_url('assets/js/redirect.js'))
        . '" data-url="' . prescription_e($url) . '"></script>';
    exit;
}

function prescription_json(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function prescription_csrf_token(): string
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    if (empty($_SESSION['prescription_csrf_token'])) {
        $_SESSION['prescription_csrf_token'] = bin2hex(random_bytes(32));
    }

    return (string)$_SESSION['prescription_csrf_token'];
}

function prescription_verify_csrf(?string $token): bool
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    $session_token = (string)($_SESSION['prescription_csrf_token'] ?? '');

    return $session_token !== ''
        && is_string($token)
        && hash_equals($session_token, $token);
}

function prescription_flash(string $type, string $message): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    $_SESSION['prescription_flash_type'] = $type;
    $_SESSION['prescription_flash_message'] = $message;
}

function prescription_get_flash(): array
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    $flash = [
        'type' => (string)($_SESSION['prescription_flash_type'] ?? ''),
        'message' => (string)($_SESSION['prescription_flash_message'] ?? ''),
    ];

    unset($_SESSION['prescription_flash_type'], $_SESSION['prescription_flash_message']);

    return $flash;
}

function prescription_table_exists(PDO $pdo, string $table): bool
{
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
        return false;
    }

    try {
        $stmt = $pdo->prepare("SHOW TABLES LIKE :table_name");
        $stmt->execute([':table_name' => $table]);

        return (bool)$stmt->fetchColumn();
    } catch (Throwable $e) {
        return false;
    }
}

function prescription_column_exists(PDO $pdo, string $table, string $column): bool
{
    if (
        !preg_match('/^[a-zA-Z0-9_]+$/', $table)
        || !preg_match('/^[a-zA-Z0-9_]+$/', $column)
    ) {
        return false;
    }

    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = :table_name
              AND COLUMN_NAME = :column_name
        ");
        $stmt->execute([
            ':table_name' => $table,
            ':column_name' => $column,
        ]);

        return (int)$stmt->fetchColumn() > 0;
    } catch (Throwable $e) {
        return false;
    }
}

function prescription_install_schema(PDO $pdo): void
{
    $queries = [];

    $queries[] = "
        CREATE TABLE IF NOT EXISTS prescription_patients (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            doctor_id BIGINT UNSIGNED NOT NULL,
            patient_code VARCHAR(50) NOT NULL,
            name VARCHAR(150) NOT NULL,
            phone VARCHAR(50) DEFAULT NULL,
            phone_normalized VARCHAR(30) DEFAULT NULL,
            age VARCHAR(30) DEFAULT NULL,
            gender VARCHAR(20) DEFAULT NULL,
            blood_group VARCHAR(20) DEFAULT NULL,
            address TEXT DEFAULT NULL,
            last_weight VARCHAR(30) DEFAULT NULL,
            last_height VARCHAR(30) DEFAULT NULL,
            last_blood_pressure VARCHAR(30) DEFAULT NULL,
            last_temperature VARCHAR(30) DEFAULT NULL,
            last_pulse VARCHAR(30) DEFAULT NULL,
            last_spo2 VARCHAR(30) DEFAULT NULL,
            medical_history TEXT DEFAULT NULL,
            last_visit_date DATE DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_patient_code_doctor (doctor_id, patient_code),
            KEY idx_patient_doctor (doctor_id),
            KEY idx_patient_phone (phone),
            KEY idx_patient_phone_normalized (phone_normalized),
            KEY idx_patient_name (name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";

    $queries[] = "
        CREATE TABLE IF NOT EXISTS prescriptions (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            prescription_no VARCHAR(60) NOT NULL,
            doctor_id BIGINT UNSIGNED NOT NULL,
            patient_id BIGINT UNSIGNED DEFAULT NULL,
            patient_name VARCHAR(150) NOT NULL,
            patient_age VARCHAR(30) DEFAULT NULL,
            patient_gender VARCHAR(20) DEFAULT NULL,
            patient_phone VARCHAR(50) DEFAULT NULL,
            patient_blood_group VARCHAR(20) DEFAULT NULL,
            patient_address TEXT DEFAULT NULL,
            visit_date DATE NOT NULL,
            weight VARCHAR(30) DEFAULT NULL,
            height VARCHAR(30) DEFAULT NULL,
            blood_pressure VARCHAR(30) DEFAULT NULL,
            temperature VARCHAR(30) DEFAULT NULL,
            pulse VARCHAR(30) DEFAULT NULL,
            spo2 VARCHAR(30) DEFAULT NULL,
            chief_complaints TEXT DEFAULT NULL,
            medical_history TEXT DEFAULT NULL,
            examination TEXT DEFAULT NULL,
            diagnosis TEXT DEFAULT NULL,
            advice TEXT DEFAULT NULL,
            follow_up_date DATE DEFAULT NULL,
            private_notes TEXT DEFAULT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_prescription_no (prescription_no),
            KEY idx_prescription_doctor (doctor_id),
            KEY idx_prescription_patient (patient_id),
            KEY idx_prescription_visit_date (visit_date),
            KEY idx_prescription_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";

    $queries[] = "
        CREATE TABLE IF NOT EXISTS prescription_daily_sequences (
            sequence_date DATE NOT NULL,
            last_number INT UNSIGNED NOT NULL DEFAULT 0,
            updated_at DATETIME DEFAULT NULL,
            PRIMARY KEY (sequence_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";

    $queries[] = "
        CREATE TABLE IF NOT EXISTS prescription_medicines (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            prescription_id BIGINT UNSIGNED NOT NULL,
            library_id BIGINT UNSIGNED DEFAULT NULL,
            medicine_form VARCHAR(100) DEFAULT NULL,
            generic_name VARCHAR(200) DEFAULT NULL,
            brand_name VARCHAR(200) DEFAULT NULL,
            medicine_name VARCHAR(200) NOT NULL,
            strength VARCHAR(100) DEFAULT NULL,
            dosage VARCHAR(100) DEFAULT NULL,
            frequency VARCHAR(100) DEFAULT NULL,
            duration VARCHAR(100) DEFAULT NULL,
            instruction VARCHAR(255) DEFAULT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_medicine_prescription (prescription_id),
            KEY idx_medicine_name (medicine_name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";


    $queries[] = "
        CREATE TABLE IF NOT EXISTS prescription_medicine_name_library (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            doctor_id BIGINT UNSIGNED NOT NULL,
            generic_name VARCHAR(200) NOT NULL,
            brand_name VARCHAR(200) NOT NULL DEFAULT '',
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            usage_count INT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_medicine_name_doctor (doctor_id, generic_name, brand_name),
            KEY idx_medicine_name_generic (generic_name),
            KEY idx_medicine_name_brand (brand_name),
            KEY idx_medicine_name_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";

    $queries[] = "
        CREATE TABLE IF NOT EXISTS prescription_medicine_form_library (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            doctor_id BIGINT UNSIGNED NOT NULL,
            option_value VARCHAR(100) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            usage_count INT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_medicine_form_doctor (doctor_id, option_value),
            KEY idx_medicine_form_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";

    $queries[] = "
        CREATE TABLE IF NOT EXISTS prescription_strength_library (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            doctor_id BIGINT UNSIGNED NOT NULL,
            option_value VARCHAR(100) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            usage_count INT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_strength_doctor (doctor_id, option_value),
            KEY idx_strength_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";

    $queries[] = "
        CREATE TABLE IF NOT EXISTS prescription_dosage_library (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            doctor_id BIGINT UNSIGNED NOT NULL,
            option_value VARCHAR(100) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            usage_count INT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_dosage_doctor (doctor_id, option_value),
            KEY idx_dosage_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";

    $queries[] = "
        CREATE TABLE IF NOT EXISTS prescription_frequency_library (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            doctor_id BIGINT UNSIGNED NOT NULL,
            option_value VARCHAR(100) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            usage_count INT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_frequency_doctor (doctor_id, option_value),
            KEY idx_frequency_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";

    $queries[] = "
        CREATE TABLE IF NOT EXISTS prescription_duration_library (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            doctor_id BIGINT UNSIGNED NOT NULL,
            option_value VARCHAR(100) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            usage_count INT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_duration_doctor (doctor_id, option_value),
            KEY idx_duration_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";

    $queries[] = "
        CREATE TABLE IF NOT EXISTS prescription_instruction_library (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            doctor_id BIGINT UNSIGNED NOT NULL,
            option_value VARCHAR(255) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            usage_count INT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_instruction_doctor (doctor_id, option_value),
            KEY idx_instruction_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";


    $option_library_tables = [
        'prescription_test_name_library' => 200,
        'prescription_test_instruction_library' => 255,
        'prescription_complaint_library' => 255,
        'prescription_diagnosis_library' => 255,
        'prescription_history_library' => 255,
        'prescription_examination_library' => 255,
        'prescription_advice_library' => 255,
    ];

    foreach ($option_library_tables as $table_name => $value_length) {
        $queries[] = "
            CREATE TABLE IF NOT EXISTS `{$table_name}` (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                doctor_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
                option_value VARCHAR({$value_length}) NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'active',
                usage_count INT UNSIGNED NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uq_library_doctor_value (doctor_id, option_value),
                KEY idx_library_status (status),
                KEY idx_library_value (option_value)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";
    }

    $queries[] = "
        CREATE TABLE IF NOT EXISTS prescription_library_hidden (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            doctor_id BIGINT UNSIGNED NOT NULL,
            library_type VARCHAR(40) NOT NULL,
            item_id BIGINT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_hidden_library_item (doctor_id, library_type, item_id),
            KEY idx_hidden_doctor_type (doctor_id, library_type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";

    $queries[] = "
        CREATE TABLE IF NOT EXISTS prescription_tests (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            prescription_id BIGINT UNSIGNED NOT NULL,
            test_name VARCHAR(200) NOT NULL,
            instruction VARCHAR(255) DEFAULT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_test_prescription (prescription_id),
            KEY idx_test_name (test_name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";

    foreach ($queries as $query) {
        try {
            $pdo->exec($query);
        } catch (Throwable $e) {
            error_log('[Prescription Schema][Create Table] ' . $e->getMessage());
        }
    }

    // Migrate legacy combined-library values without blocking core prescription saves.
    if (prescription_table_exists($pdo, 'prescription_medicine_library')) {
        try {
            $pdo->exec("
                INSERT IGNORE INTO prescription_medicine_name_library
                    (doctor_id, generic_name, brand_name, status, usage_count, created_at, updated_at)
                SELECT
                    doctor_id,
                    generic_name,
                    COALESCE(brand_name, ''),
                    status,
                    usage_count,
                    created_at,
                    updated_at
                FROM prescription_medicine_library
                WHERE TRIM(generic_name) != ''
            ");
        } catch (Throwable $e) {
            error_log('[Prescription Schema][Legacy Medicine Migration] ' . $e->getMessage());
        }

        $migration_map = [
            'strength' => 'prescription_strength_library',
            'dosage' => 'prescription_dosage_library',
            'frequency' => 'prescription_frequency_library',
            'duration' => 'prescription_duration_library',
            'instruction' => 'prescription_instruction_library',
        ];

        foreach ($migration_map as $column => $table) {
            try {
                $pdo->exec("
                    INSERT IGNORE INTO `{$table}`
                        (doctor_id, option_value, status, usage_count, created_at, updated_at)
                    SELECT
                        doctor_id,
                        TRIM(`{$column}`),
                        status,
                        usage_count,
                        created_at,
                        updated_at
                    FROM prescription_medicine_library
                    WHERE `{$column}` IS NOT NULL
                      AND TRIM(`{$column}`) != ''
                ");
            } catch (Throwable $e) {
                error_log('[Prescription Schema][Legacy ' . $column . ' Migration] ' . $e->getMessage());
            }
        }
    }

    $patient_columns = [
        'phone_normalized' => "VARCHAR(30) DEFAULT NULL AFTER phone",
        'last_weight' => "VARCHAR(30) DEFAULT NULL AFTER address",
        'last_height' => "VARCHAR(30) DEFAULT NULL AFTER last_weight",
        'last_blood_pressure' => "VARCHAR(30) DEFAULT NULL AFTER last_height",
        'last_temperature' => "VARCHAR(30) DEFAULT NULL AFTER last_blood_pressure",
        'last_pulse' => "VARCHAR(30) DEFAULT NULL AFTER last_temperature",
        'last_spo2' => "VARCHAR(30) DEFAULT NULL AFTER last_pulse",
        'medical_history' => "TEXT DEFAULT NULL AFTER last_spo2",
        'last_visit_date' => "DATE DEFAULT NULL AFTER medical_history",
    ];

    foreach ($patient_columns as $column => $definition) {
        if (!prescription_column_exists($pdo, 'prescription_patients', $column)) {
            try {
                $pdo->exec("ALTER TABLE prescription_patients ADD COLUMN `{$column}` {$definition}");
            } catch (Throwable $e) {
                error_log('[Prescription Schema][Patient Column ' . $column . '] ' . $e->getMessage());
            }
        }
    }

    try {
        $pdo->exec("CREATE INDEX idx_patient_phone_normalized ON prescription_patients (phone_normalized)");
    } catch (Throwable $e) {
        // The index already exists or cannot be created. Patient search still works.
    }

    if (prescription_column_exists($pdo, 'prescription_patients', 'phone_normalized')) {
        try {
            $phone_digits_sql = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone, '+', ''), '-', ''), ' ', ''), '(', ''), ')', ''), '.', '')";
            $pdo->exec("
                UPDATE prescription_patients
                SET phone_normalized = CASE
                    WHEN LEFT({$phone_digits_sql}, 5) = '00880' THEN SUBSTRING({$phone_digits_sql}, 3)
                    WHEN LEFT({$phone_digits_sql}, 1) = '0' AND CHAR_LENGTH({$phone_digits_sql}) = 11 THEN CONCAT('88', {$phone_digits_sql})
                    ELSE {$phone_digits_sql}
                END
                WHERE phone IS NOT NULL
                  AND TRIM(phone) != ''
                  AND (phone_normalized IS NULL OR phone_normalized = '')
            ");
        } catch (Throwable $e) {
            error_log('[Prescription Schema][Phone Backfill] ' . $e->getMessage());
        }
    }

    $medicine_columns = [
        'library_id' => "BIGINT UNSIGNED DEFAULT NULL AFTER prescription_id",
        'medicine_form' => "VARCHAR(100) DEFAULT NULL AFTER library_id",
        'generic_name' => "VARCHAR(200) DEFAULT NULL AFTER medicine_form",
        'brand_name' => "VARCHAR(200) DEFAULT NULL AFTER generic_name",
    ];

    foreach ($medicine_columns as $column => $definition) {
        if (!prescription_column_exists($pdo, 'prescription_medicines', $column)) {
            try {
                $pdo->exec("ALTER TABLE prescription_medicines ADD COLUMN `{$column}` {$definition}");
            } catch (Throwable $e) {
                error_log('[Prescription Schema][Medicine Column ' . $column . '] ' . $e->getMessage());
            }
        }
    }

    try {
        prescription_seed_global_library_demos($pdo);
    } catch (Throwable $e) {
        error_log('[Prescription Schema][Demo Seed] ' . $e->getMessage());
    }
}

function prescription_schema_ready(PDO $pdo): bool
{
    $required_tables = [
        'prescription_patients',
        'prescriptions',
        'prescription_daily_sequences',
        'prescription_medicines',
        'prescription_tests',
        'prescription_medicine_name_library',
        'prescription_medicine_form_library',
        'prescription_strength_library',
        'prescription_dosage_library',
        'prescription_frequency_library',
        'prescription_duration_library',
        'prescription_instruction_library',
        'prescription_test_name_library',
        'prescription_test_instruction_library',
        'prescription_complaint_library',
        'prescription_diagnosis_library',
        'prescription_history_library',
        'prescription_examination_library',
        'prescription_advice_library',
        'prescription_library_hidden',
    ];

    foreach ($required_tables as $table) {
        if (!prescription_table_exists($pdo, $table)) {
            return false;
        }
    }

    $required_patient_columns = [
        'phone_normalized',
        'last_weight',
        'last_height',
        'last_blood_pressure',
        'last_temperature',
        'last_pulse',
        'last_spo2',
        'medical_history',
        'last_visit_date',
    ];

    foreach ($required_patient_columns as $column) {
        if (!prescription_column_exists($pdo, 'prescription_patients', $column)) {
            return false;
        }
    }

    foreach (['library_id', 'medicine_form', 'generic_name', 'brand_name'] as $column) {
        if (!prescription_column_exists($pdo, 'prescription_medicines', $column)) {
            return false;
        }
    }

    return true;
}

function prescription_get_doctor(PDO $pdo, int $doctor_id): array
{
    if ($doctor_id <= 0 || !prescription_table_exists($pdo, 'doctors')) {
        return [];
    }

    try {
        $stmt = $pdo->prepare("SELECT * FROM doctors WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $doctor_id]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

function prescription_strlen(string $value): int
{
    return function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
}

function prescription_substr(string $value, int $start, ?int $length = null): string
{
    if (function_exists('mb_substr')) {
        return $length === null ? mb_substr($value, $start) : mb_substr($value, $start, $length);
    }

    return $length === null ? substr($value, $start) : substr($value, $start, $length);
}

function prescription_excerpt($value, int $length = 70): string
{
    $value = trim((string)$value);

    if ($value === '') {
        return '—';
    }

    if (prescription_strlen($value) <= $length) {
        return $value;
    }

    return rtrim(prescription_substr($value, 0, $length - 1)) . '…';
}

function prescription_initial(string $value): string
{
    $first = prescription_substr(trim($value), 0, 1);

    return function_exists('mb_strtoupper') ? mb_strtoupper($first) : strtoupper($first);
}

function prescription_clean_text($value, int $max_length = 0): string
{
    $value = trim((string)$value);
    $value = preg_replace('/\r\n?|\n/', "\n", $value) ?? $value;

    if ($max_length > 0 && prescription_strlen($value) > $max_length) {
        $value = prescription_substr($value, 0, $max_length);
    }

    return $value;
}

function prescription_valid_date($value, bool $allow_empty = true): ?string
{
    $value = trim((string)$value);

    if ($value === '') {
        return $allow_empty ? null : date('Y-m-d');
    }

    $date = DateTimeImmutable::createFromFormat('Y-m-d', $value);

    if (!$date || $date->format('Y-m-d') !== $value) {
        return $allow_empty ? null : date('Y-m-d');
    }

    return $value;
}

function prescription_normalize_phone($value): string
{
    $digits = preg_replace('/\D+/', '', (string)$value) ?? '';

    if (str_starts_with($digits, '00880')) {
        $digits = substr($digits, 2);
    }

    if (str_starts_with($digits, '880') && strlen($digits) > 10) {
        return $digits;
    }

    if (str_starts_with($digits, '0') && strlen($digits) === 11) {
        return '88' . $digits;
    }

    return $digits;
}

function prescription_generate_number(
    PDO $pdo,
    int $doctor_id,
    ?string $number_date = null
): string {
    // Keep the doctor parameter for backward compatibility. The daily sequence is
    // global because prescription_no has a global UNIQUE database index.
    unset($doctor_id);

    $date_value = trim((string)$number_date);
    $date_object = $date_value !== ''
        ? DateTimeImmutable::createFromFormat('!Y-m-d', $date_value)
        : false;

    if (!$date_object || $date_object->format('Y-m-d') !== $date_value) {
        $date_object = new DateTimeImmutable('today');
    }

    $sequence_date = $date_object->format('Y-m-d');
    $prefix = 'RX' . $date_object->format('ymd');
    $pattern = $prefix . '%';

    try {
        if (prescription_table_exists($pdo, 'prescription_daily_sequences')) {
            // This row is locked inside the current prescription transaction.
            // It prevents two doctors from receiving the same daily number.
            $insert_sequence = $pdo->prepare("
                INSERT IGNORE INTO prescription_daily_sequences
                    (sequence_date, last_number, updated_at)
                VALUES
                    (:sequence_date, 0, NOW())
            ");
            $insert_sequence->execute([':sequence_date' => $sequence_date]);

            $lock_sequence = $pdo->prepare("
                SELECT last_number
                FROM prescription_daily_sequences
                WHERE sequence_date = :sequence_date
                FOR UPDATE
            ");
            $lock_sequence->execute([':sequence_date' => $sequence_date]);
            $stored_number = (int)$lock_sequence->fetchColumn();

            // Protect upgrades where new-format prescriptions existed before the
            // sequence table was created.
            $existing_max = $pdo->prepare("
                SELECT COALESCE(
                    MAX(CAST(SUBSTRING(prescription_no, 9) AS UNSIGNED)),
                    0
                )
                FROM prescriptions
                WHERE prescription_no LIKE :pattern
                  AND prescription_no REGEXP '^RX[0-9]{8,}$'
            ");
            $existing_max->execute([':pattern' => $pattern]);
            $existing_number = (int)$existing_max->fetchColumn();

            $next_number = max($stored_number, $existing_number) + 1;

            $update_sequence = $pdo->prepare("
                UPDATE prescription_daily_sequences
                SET last_number = :last_number,
                    updated_at = NOW()
                WHERE sequence_date = :sequence_date
                LIMIT 1
            ");
            $update_sequence->execute([
                ':last_number' => $next_number,
                ':sequence_date' => $sequence_date,
            ]);

            return $prefix . str_pad((string)$next_number, 2, '0', STR_PAD_LEFT);
        }
    } catch (Throwable $e) {
        error_log('[Prescription Number Sequence] ' . $e->getMessage());
    }

    // Permission-safe fallback for installations where the sequence table could
    // not be created. The UNIQUE index still protects against saved duplicates.
    for ($attempt = 0; $attempt < 5; $attempt++) {
        $max_statement = $pdo->prepare("
            SELECT COALESCE(
                MAX(CAST(SUBSTRING(prescription_no, 9) AS UNSIGNED)),
                0
            )
            FROM prescriptions
            WHERE prescription_no LIKE :pattern
              AND prescription_no REGEXP '^RX[0-9]{8,}$'
        ");
        $max_statement->execute([':pattern' => $pattern]);

        $next_number = (int)$max_statement->fetchColumn() + 1 + $attempt;
        $number = $prefix . str_pad((string)$next_number, 2, '0', STR_PAD_LEFT);

        $exists_statement = $pdo->prepare("
            SELECT id
            FROM prescriptions
            WHERE prescription_no = :number
            LIMIT 1
        ");
        $exists_statement->execute([':number' => $number]);

        if (!$exists_statement->fetchColumn()) {
            return $number;
        }
    }

    throw new RuntimeException('A unique daily prescription number could not be generated.');
}

function prescription_generate_patient_code(PDO $pdo, int $doctor_id): string
{
    for ($attempt = 0; $attempt < 8; $attempt++) {
        $code = sprintf(
            'PT-D%d-%s',
            $doctor_id,
            strtoupper(substr(bin2hex(random_bytes(4)), 0, 8))
        );

        $stmt = $pdo->prepare("
            SELECT id
            FROM prescription_patients
            WHERE doctor_id = :doctor_id
              AND patient_code = :code
            LIMIT 1
        ");
        $stmt->execute([
            ':doctor_id' => $doctor_id,
            ':code' => $code,
        ]);

        if (!$stmt->fetchColumn()) {
            return $code;
        }
    }

    return 'PT-D' . $doctor_id . '-' . date('YmdHis') . '-' . random_int(100, 999);
}

function prescription_get_patient(PDO $pdo, int $patient_id, int $doctor_id): ?array
{
    if ($patient_id <= 0) {
        return null;
    }

    $phone_normalized = prescription_column_exists($pdo, 'prescription_patients', 'phone_normalized')
        ? "p.phone_normalized"
        : "''";

    $snapshot_map = [
        'last_weight' => 'weight',
        'last_height' => 'height',
        'last_blood_pressure' => 'blood_pressure',
        'last_temperature' => 'temperature',
        'last_pulse' => 'pulse',
        'last_spo2' => 'spo2',
        'medical_history' => 'medical_history',
        'last_visit_date' => 'visit_date',
    ];

    $snapshot_selects = [];

    foreach ($snapshot_map as $patient_column => $prescription_column) {
        if (prescription_column_exists($pdo, 'prescription_patients', $patient_column)) {
            if ($patient_column === 'last_visit_date') {
                $snapshot_selects[] = "COALESCE(p.`{$patient_column}`, latest.`{$prescription_column}`) AS `{$patient_column}`";
            } else {
                $snapshot_selects[] = "COALESCE(NULLIF(p.`{$patient_column}`, ''), latest.`{$prescription_column}`) AS `{$patient_column}`";
            }
        } else {
            $snapshot_selects[] = "latest.`{$prescription_column}` AS `{$patient_column}`";
        }
    }

    $stmt = $pdo->prepare("
        SELECT
            p.*,
            {$phone_normalized} AS phone_normalized,
            " . implode(",
            ", $snapshot_selects) . ",
            latest.diagnosis AS last_diagnosis,
            latest.id AS latest_prescription_id
        FROM prescription_patients p
        LEFT JOIN prescriptions latest
          ON latest.id = (
                SELECT px.id
                FROM prescriptions px
                WHERE px.doctor_id = p.doctor_id
                  AND px.patient_id = p.id
                ORDER BY px.visit_date DESC, px.id DESC
                LIMIT 1
          )
        WHERE p.id = :id
          AND p.doctor_id = :doctor_id
        LIMIT 1
    ");
    $stmt->execute([
        ':id' => $patient_id,
        ':doctor_id' => $doctor_id,
    ]);

    $patient = $stmt->fetch(PDO::FETCH_ASSOC);

    return $patient ?: null;
}

function prescription_get_record(PDO $pdo, int $prescription_id, int $doctor_id): ?array
{
    if ($prescription_id <= 0) {
        return null;
    }

    $stmt = $pdo->prepare("
        SELECT *
        FROM prescriptions
        WHERE id = :id
          AND doctor_id = :doctor_id
        LIMIT 1
    ");
    $stmt->execute([
        ':id' => $prescription_id,
        ':doctor_id' => $doctor_id,
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

function prescription_library_definitions(): array
{
    return [
        'medicine' => [
            'table' => 'prescription_medicine_name_library',
            'title' => 'Medicine Names',
            'singular' => 'Medicine Name',
            'description' => 'Generic name with an optional brand name.',
            'placeholder' => 'Example: Paracetamol',
            'max_length' => 200,
            'medicine' => true,
            'group' => 'Medicine',
        ],
        'medicine_form' => [
            'table' => 'prescription_medicine_form_library',
            'title' => 'Medicine Forms',
            'singular' => 'Medicine Form',
            'description' => 'Reusable medicine forms such as Tab., Cap., Syr., Inj. or Cream.',
            'placeholder' => 'Example: Tab.',
            'max_length' => 100,
            'group' => 'Medicine',
        ],
        'strength' => [
            'table' => 'prescription_strength_library',
            'title' => 'Strength',
            'singular' => 'Strength',
            'description' => 'Reusable strengths such as 500 mg or 20 mg/5 ml.',
            'placeholder' => 'Example: 500 mg',
            'max_length' => 100,
            'group' => 'Medicine',
        ],
        'dosage' => [
            'table' => 'prescription_dosage_library',
            'title' => 'Dosage',
            'singular' => 'Dosage',
            'description' => 'Reusable dose amounts such as 1 tablet or 5 ml.',
            'placeholder' => 'Example: 1 tablet',
            'max_length' => 100,
            'group' => 'Medicine',
        ],
        'frequency' => [
            'table' => 'prescription_frequency_library',
            'title' => 'Frequency',
            'singular' => 'Frequency',
            'description' => 'Reusable schedules such as 1-0-1 or twice daily.',
            'placeholder' => 'Example: 1-0-1',
            'max_length' => 100,
            'group' => 'Medicine',
        ],
        'duration' => [
            'table' => 'prescription_duration_library',
            'title' => 'Duration',
            'singular' => 'Duration',
            'description' => 'Reusable treatment durations such as 5 days.',
            'placeholder' => 'Example: 5 days',
            'max_length' => 100,
            'group' => 'Medicine',
        ],
        'instruction' => [
            'table' => 'prescription_instruction_library',
            'title' => 'Medicine Instructions',
            'singular' => 'Medicine Instruction',
            'description' => 'Reusable medicine instructions such as After meal.',
            'placeholder' => 'Example: After meal',
            'max_length' => 255,
            'group' => 'Medicine',
        ],
        'test' => [
            'table' => 'prescription_test_name_library',
            'title' => 'Tests / Investigations',
            'singular' => 'Test / Investigation',
            'description' => 'Laboratory, imaging and other investigation names.',
            'placeholder' => 'Example: Complete Blood Count (CBC)',
            'max_length' => 200,
            'group' => 'Clinical',
        ],
        'test_instruction' => [
            'table' => 'prescription_test_instruction_library',
            'title' => 'Test Instructions',
            'singular' => 'Test Instruction',
            'description' => 'Reusable preparation or reporting instructions for tests.',
            'placeholder' => 'Example: Fasting for 8 hours',
            'max_length' => 255,
            'group' => 'Clinical',
        ],
        'complaint' => [
            'table' => 'prescription_complaint_library',
            'title' => 'Chief Complaints',
            'singular' => 'Chief Complaint',
            'description' => 'Frequently used presenting complaints.',
            'placeholder' => 'Example: Fever for 3 days',
            'max_length' => 255,
            'group' => 'Clinical',
        ],
        'diagnosis' => [
            'table' => 'prescription_diagnosis_library',
            'title' => 'Diagnosis',
            'singular' => 'Diagnosis',
            'description' => 'Frequently used diagnoses or provisional diagnoses.',
            'placeholder' => 'Example: Provisional diagnosis',
            'max_length' => 255,
            'group' => 'Clinical',
        ],
        'history' => [
            'table' => 'prescription_history_library',
            'title' => 'Medical History',
            'singular' => 'Medical History',
            'description' => 'Reusable disease and medical history notes.',
            'placeholder' => 'Example: No significant past medical history',
            'max_length' => 255,
            'group' => 'Clinical',
        ],
        'examination' => [
            'table' => 'prescription_examination_library',
            'title' => 'Examination Findings',
            'singular' => 'Examination Finding',
            'description' => 'Reusable on-examination and clinical finding notes.',
            'placeholder' => 'Example: General condition stable',
            'max_length' => 255,
            'group' => 'Clinical',
        ],
        'advice' => [
            'table' => 'prescription_advice_library',
            'title' => 'Advice',
            'singular' => 'Advice',
            'description' => 'Reusable patient advice and follow-up instructions.',
            'placeholder' => 'Example: Follow up with reports',
            'max_length' => 255,
            'group' => 'Clinical',
        ],
    ];
}

function prescription_normalize_library_type(string $type): string
{
    $type = strtolower(trim($type));
    $aliases = [
        'generic' => 'medicine',
        'generic_name' => 'medicine',
        'brand' => 'medicine',
        'brand_name' => 'medicine',
        'medicine_name' => 'medicine',
        'form' => 'medicine_form',
        'dosage_form' => 'medicine_form',
        'medicine_type' => 'medicine_form',
        'investigation' => 'test',
        'tests' => 'test',
        'chief_complaints' => 'complaint',
        'chief_complaint' => 'complaint',
        'medical_history' => 'history',
        'findings' => 'examination',
        'o_e' => 'examination',
    ];

    return $aliases[$type] ?? $type;
}

function prescription_library_definition(string $type): ?array
{
    $type = prescription_normalize_library_type($type);
    $definitions = prescription_library_definitions();

    return $definitions[$type] ?? null;
}

function prescription_library_demo_items(): array
{
    return [
        'medicine' => [
            ['Paracetamol', ''],
            ['Omeprazole', ''],
            ['Oral Rehydration Salts', 'ORS'],
        ],
        'medicine_form' => [
            'Tab.',
            'Cap.',
            'Softgel',
            'Chew. Tab.',
            'Dispersible Tab.',
            'Eff. Tab.',
            'Sublingual Tab.',
            'Buccal Tab.',
            'Lozenge',
            'Troche',
            'Sachet',
            'Granules',
            'Powder',
            'Oral Powder',
            'Syr.',
            'Susp.',
            'Oral Susp.',
            'Soln.',
            'Oral Soln.',
            'Elixir',
            'Emulsion',
            'Drops',
            'Mixture',
            'Linctus',
            'Inj.',
            'IV Inj.',
            'IM Inj.',
            'SC Inj.',
            'ID Inj.',
            'Inf.',
            'IV Inf.',
            'Amp.',
            'Vial',
            'Prefilled Syringe',
            'Cream',
            'Oint.',
            'Gel',
            'Lotion',
            'Paste',
            'Paint',
            'Liniment',
            'Topical Soln.',
            'Topical Spray',
            'Foam',
            'Shampoo',
            'Soap',
            'Patch',
            'Eye Drop',
            'Eye Oint.',
            'Ophthalmic Soln.',
            'Ophthalmic Gel',
            'Eye Insert',
            'Ear Drop',
            'Otic Soln.',
            'Ear Spray',
            'Nasal Drop',
            'Nasal Spray',
            'Nasal Gel',
            'Nasal Wash',
            'Inhaler',
            'MDI',
            'DPI',
            'Neb. Soln.',
            'Respules',
            'Rotacap',
            'Transhaler',
            'Inhalation Cap.',
            'Nasal Inhaler',
            'Supp.',
            'Rectal Cream',
            'Rectal Oint.',
            'Enema',
            'Vag. Tab.',
            'Vag. Cap.',
            'Pessary',
            'Vag. Cream',
            'Vag. Gel',
            'Vag. Supp.',
            'Mouthwash',
            'Gargle',
            'Oral Gel',
            'Dental Gel',
            'Dental Paste',
            'Oral Spray',
            'Throat Spray',
            'Implant',
            'Pellet',
            'Ring',
            'Irrigation Soln.',
            'Dialysis Soln.',
            'Medical Gas',
            'Kit',
            'Device',
            'Other'
        ],
        'strength' => ['500 mg', '250 mg/5 ml', '20 mg', '5 mg'],
        'dosage' => ['1 tablet', '1 capsule', '5 ml', 'As directed'],
        'frequency' => ['Once daily', 'Twice daily', 'Three times daily', '1-0-1', 'When required'],
        'duration' => ['3 days', '5 days', '7 days', '1 month', 'Continue'],
        'instruction' => ['After meal', 'Before meal', 'At bedtime', 'With water', 'As directed'],
        'test' => [
            'Complete Blood Count (CBC)',
            'Fasting Blood Glucose',
            'Serum Creatinine',
            'Urine Routine Examination',
            'Chest X-ray',
        ],
        'test_instruction' => [
            'Fasting for 8 hours',
            'Bring previous reports',
            'Collect morning sample',
            'Complete before next visit',
        ],
        'complaint' => [
            'Fever',
            'Cough',
            'Headache',
            'Abdominal pain',
            'Weakness',
        ],
        'diagnosis' => [
            'Provisional diagnosis',
            'Viral fever',
            'Upper respiratory tract infection',
            'Hypertension',
            'Type 2 diabetes mellitus',
        ],
        'history' => [
            'No significant past medical history',
            'History of hypertension',
            'History of diabetes mellitus',
            'Known drug allergy: __________',
        ],
        'examination' => [
            'General condition stable',
            'Patient is afebrile',
            'Chest is clear',
            'Blood pressure: __________',
            'No peripheral oedema',
        ],
        'advice' => [
            'Take medicines exactly as prescribed',
            'Drink adequate water',
            'Bring all investigation reports at follow-up',
            'Return earlier if symptoms worsen',
            'Follow up on the advised date',
        ],
    ];
}

function prescription_seed_global_library_demos(PDO $pdo): void
{
    foreach (prescription_library_demo_items() as $type => $items) {
        $definition = prescription_library_definition($type);

        if (!$definition || !prescription_table_exists($pdo, $definition['table'])) {
            continue;
        }

        $table = $definition['table'];

        if (!empty($definition['medicine'])) {
            $stmt = $pdo->prepare("
                INSERT IGNORE INTO `{$table}`
                    (doctor_id, generic_name, brand_name, status, usage_count, created_at)
                VALUES
                    (0, :generic_name, :brand_name, 'active', 0, NOW())
            ");

            foreach ($items as $item) {
                $stmt->execute([
                    ':generic_name' => trim((string)($item[0] ?? '')),
                    ':brand_name' => trim((string)($item[1] ?? '')),
                ]);
            }

            continue;
        }

        $stmt = $pdo->prepare("
            INSERT IGNORE INTO `{$table}`
                (doctor_id, option_value, status, usage_count, created_at)
            VALUES
                (0, :option_value, 'active', 0, NOW())
        ");

        foreach ($items as $item) {
            $value = trim((string)$item);

            if ($value !== '') {
                $stmt->execute([':option_value' => $value]);
            }
        }
    }
}

function prescription_get_library_item(PDO $pdo, int $item_id, int $doctor_id, string $type): ?array
{
    $definition = prescription_library_definition($type);

    if (!$definition || $item_id <= 0 || $doctor_id <= 0) {
        return null;
    }

    $table = $definition['table'];
    $type = prescription_normalize_library_type($type);

    $stmt = $pdo->prepare("
        SELECT l.*,
               CASE WHEN l.doctor_id = 0 THEN 1 ELSE 0 END AS is_demo
        FROM `{$table}` l
        LEFT JOIN prescription_library_hidden h
          ON h.doctor_id = :viewer_id
         AND h.library_type = :library_type
         AND h.item_id = l.id
         AND l.doctor_id = 0
        WHERE l.id = :id
          AND l.doctor_id IN (0, :doctor_id)
          AND h.id IS NULL
        LIMIT 1
    ");
    $stmt->execute([
        ':viewer_id' => $doctor_id,
        ':library_type' => $type,
        ':id' => $item_id,
        ':doctor_id' => $doctor_id,
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

function prescription_get_own_library_item(PDO $pdo, int $item_id, int $doctor_id, string $type): ?array
{
    $definition = prescription_library_definition($type);

    if (!$definition || $item_id <= 0 || $doctor_id <= 0) {
        return null;
    }

    $table = $definition['table'];
    $stmt = $pdo->prepare("SELECT * FROM `{$table}` WHERE id = :id AND doctor_id = :doctor_id LIMIT 1");
    $stmt->execute([
        ':id' => $item_id,
        ':doctor_id' => $doctor_id,
    ]);

    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function prescription_save_library_item(
    PDO $pdo,
    int $doctor_id,
    string $type,
    array $source,
    int $item_id = 0
): int {
    $type = prescription_normalize_library_type($type);
    $definition = prescription_library_definition($type);

    if (!$definition || $doctor_id <= 0) {
        throw new InvalidArgumentException('Invalid library type.');
    }

    $status = prescription_clean_text($source['status'] ?? 'active', 20);

    if (!in_array($status, ['active', 'inactive'], true)) {
        $status = 'active';
    }

    $table = $definition['table'];

    if (!empty($definition['medicine'])) {
        $generic_name = prescription_clean_text($source['generic_name'] ?? '', 200);
        $brand_name = prescription_clean_text($source['brand_name'] ?? '', 200);

        if ($generic_name === '') {
            throw new InvalidArgumentException('Generic name is required.');
        }

        $duplicate_sql = "SELECT id FROM `{$table}` WHERE doctor_id = :doctor_id AND generic_name = :generic_name AND brand_name = :brand_name";
        $duplicate_params = [
            ':doctor_id' => $doctor_id,
            ':generic_name' => $generic_name,
            ':brand_name' => $brand_name,
        ];

        if ($item_id > 0) {
            $duplicate_sql .= ' AND id != :id';
            $duplicate_params[':id'] = $item_id;
        }

        $duplicate_sql .= ' LIMIT 1';
        $duplicate_stmt = $pdo->prepare($duplicate_sql);
        $duplicate_stmt->execute($duplicate_params);

        if ($duplicate_stmt->fetchColumn()) {
            throw new InvalidArgumentException('This generic and brand combination already exists in your library.');
        }

        if ($item_id > 0) {
            if (!prescription_get_own_library_item($pdo, $item_id, $doctor_id, $type)) {
                throw new RuntimeException('Only your own library item can be edited.');
            }

            $stmt = $pdo->prepare("
                UPDATE `{$table}`
                SET generic_name = :generic_name,
                    brand_name = :brand_name,
                    status = :status,
                    updated_at = NOW()
                WHERE id = :id AND doctor_id = :doctor_id
                LIMIT 1
            ");
            $stmt->execute([
                ':generic_name' => $generic_name,
                ':brand_name' => $brand_name,
                ':status' => $status,
                ':id' => $item_id,
                ':doctor_id' => $doctor_id,
            ]);

            return $item_id;
        }

        $stmt = $pdo->prepare("
            INSERT INTO `{$table}`
                (doctor_id, generic_name, brand_name, status, created_at)
            VALUES
                (:doctor_id, :generic_name, :brand_name, :status, NOW())
        ");
        $stmt->execute([
            ':doctor_id' => $doctor_id,
            ':generic_name' => $generic_name,
            ':brand_name' => $brand_name,
            ':status' => $status,
        ]);

        return (int)$pdo->lastInsertId();
    }

    $value = prescription_clean_text(
        $source['option_value'] ?? $source['value'] ?? '',
        (int)$definition['max_length']
    );

    if ($value === '') {
        throw new InvalidArgumentException($definition['singular'] . ' is required.');
    }

    $duplicate_sql = "SELECT id FROM `{$table}` WHERE doctor_id = :doctor_id AND option_value = :option_value";
    $duplicate_params = [
        ':doctor_id' => $doctor_id,
        ':option_value' => $value,
    ];

    if ($item_id > 0) {
        $duplicate_sql .= ' AND id != :id';
        $duplicate_params[':id'] = $item_id;
    }

    $duplicate_sql .= ' LIMIT 1';
    $duplicate_stmt = $pdo->prepare($duplicate_sql);
    $duplicate_stmt->execute($duplicate_params);

    if ($duplicate_stmt->fetchColumn()) {
        throw new InvalidArgumentException($definition['singular'] . ' already exists in your library.');
    }

    if ($item_id > 0) {
        if (!prescription_get_own_library_item($pdo, $item_id, $doctor_id, $type)) {
            throw new RuntimeException('Only your own library item can be edited.');
        }

        $stmt = $pdo->prepare("
            UPDATE `{$table}`
            SET option_value = :option_value,
                status = :status,
                updated_at = NOW()
            WHERE id = :id AND doctor_id = :doctor_id
            LIMIT 1
        ");
        $stmt->execute([
            ':option_value' => $value,
            ':status' => $status,
            ':id' => $item_id,
            ':doctor_id' => $doctor_id,
        ]);

        return $item_id;
    }

    $stmt = $pdo->prepare("
        INSERT INTO `{$table}`
            (doctor_id, option_value, status, created_at)
        VALUES
            (:doctor_id, :option_value, :status, NOW())
    ");
    $stmt->execute([
        ':doctor_id' => $doctor_id,
        ':option_value' => $value,
        ':status' => $status,
    ]);

    return (int)$pdo->lastInsertId();
}

function prescription_delete_library_item(PDO $pdo, int $item_id, int $doctor_id, string $type): string
{
    $type = prescription_normalize_library_type($type);
    $definition = prescription_library_definition($type);
    $item = prescription_get_library_item($pdo, $item_id, $doctor_id, $type);

    if (!$definition || !$item) {
        throw new RuntimeException('Library item was not found.');
    }

    if ((int)($item['doctor_id'] ?? -1) === 0) {
        $stmt = $pdo->prepare("
            INSERT IGNORE INTO prescription_library_hidden
                (doctor_id, library_type, item_id, created_at)
            VALUES
                (:doctor_id, :library_type, :item_id, NOW())
        ");
        $stmt->execute([
            ':doctor_id' => $doctor_id,
            ':library_type' => $type,
            ':item_id' => $item_id,
        ]);

        return 'hidden';
    }

    $table = $definition['table'];
    $stmt = $pdo->prepare("DELETE FROM `{$table}` WHERE id = :id AND doctor_id = :doctor_id LIMIT 1");
    $stmt->execute([
        ':id' => $item_id,
        ':doctor_id' => $doctor_id,
    ]);

    return 'deleted';
}

function prescription_restore_library_demos(PDO $pdo, int $doctor_id, string $type = ''): void
{
    if ($doctor_id <= 0) {
        return;
    }

    $type = $type !== '' ? prescription_normalize_library_type($type) : '';

    if ($type !== '' && !prescription_library_definition($type)) {
        throw new InvalidArgumentException('Invalid library type.');
    }

    if ($type === '') {
        $stmt = $pdo->prepare("DELETE FROM prescription_library_hidden WHERE doctor_id = :doctor_id");
        $stmt->execute([':doctor_id' => $doctor_id]);
        return;
    }

    $stmt = $pdo->prepare("
        DELETE FROM prescription_library_hidden
        WHERE doctor_id = :doctor_id
          AND library_type = :library_type
    ");
    $stmt->execute([
        ':doctor_id' => $doctor_id,
        ':library_type' => $type,
    ]);
}

function prescription_get_library_options(
    PDO $pdo,
    int $doctor_id,
    string $type,
    string $query = '',
    int $limit = 50
): array {
    $type = prescription_normalize_library_type($type);
    $definition = prescription_library_definition($type);

    if (!$definition || $doctor_id <= 0) {
        return [];
    }

    $limit = max(1, min(200, $limit));
    $table = $definition['table'];
    $params = [
        ':viewer_id' => $doctor_id,
        ':library_type' => $type,
        ':doctor_id' => $doctor_id,
        ':doctor_order' => $doctor_id,
    ];
    $where = "
        l.doctor_id IN (0, :doctor_id)
        AND l.status = 'active'
        AND h.id IS NULL
    ";

    if ($query !== '') {
        $params[':search'] = '%' . $query . '%';

        if (!empty($definition['medicine'])) {
            $where .= ' AND (l.generic_name LIKE :search OR l.brand_name LIKE :search)';
        } else {
            $where .= ' AND l.option_value LIKE :search';
        }
    }

    $order = !empty($definition['medicine'])
        ? 'CASE WHEN l.doctor_id = :doctor_order THEN 0 ELSE 1 END, l.usage_count DESC, l.generic_name ASC, l.brand_name ASC'
        : 'CASE WHEN l.doctor_id = :doctor_order THEN 0 ELSE 1 END, l.usage_count DESC, l.option_value ASC';

    $stmt = $pdo->prepare("
        SELECT l.*,
               CASE WHEN l.doctor_id = 0 THEN 1 ELSE 0 END AS is_demo
        FROM `{$table}` l
        LEFT JOIN prescription_library_hidden h
          ON h.doctor_id = :viewer_id
         AND h.library_type = :library_type
         AND h.item_id = l.id
         AND l.doctor_id = 0
        WHERE {$where}
        ORDER BY {$order}
        LIMIT {$limit}
    ");
    $stmt->execute($params);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function prescription_get_library_management_items(
    PDO $pdo,
    int $doctor_id,
    string $type,
    string $query = '',
    string $status = '',
    string $scope = '',
    int $limit = 20,
    int $offset = 0
): array {
    $type = prescription_normalize_library_type($type);
    $definition = prescription_library_definition($type);

    if (!$definition || $doctor_id <= 0) {
        return ['items' => [], 'total' => 0];
    }

    $table = $definition['table'];
    $limit = max(1, min(100, $limit));
    $offset = max(0, $offset);
    $params = [
        ':viewer_id' => $doctor_id,
        ':library_type' => $type,
        ':doctor_id' => $doctor_id,
        ':doctor_order' => $doctor_id,
    ];
    $where = ['l.doctor_id IN (0, :doctor_id)', 'h.id IS NULL'];

    if ($query !== '') {
        $params[':search'] = '%' . $query . '%';
        $where[] = !empty($definition['medicine'])
            ? '(l.generic_name LIKE :search OR l.brand_name LIKE :search)'
            : 'l.option_value LIKE :search';
    }

    if (in_array($status, ['active', 'inactive'], true)) {
        $params[':status'] = $status;
        $where[] = 'l.status = :status';
    }

    if ($scope === 'demo') {
        $where[] = 'l.doctor_id = 0';
    } elseif ($scope === 'mine') {
        $where[] = 'l.doctor_id = :doctor_scope';
        $params[':doctor_scope'] = $doctor_id;
    }

    $where_sql = implode(' AND ', $where);
    $joins = "
        LEFT JOIN prescription_library_hidden h
          ON h.doctor_id = :viewer_id
         AND h.library_type = :library_type
         AND h.item_id = l.id
         AND l.doctor_id = 0
    ";

    $count_params = $params;
    unset($count_params[':doctor_order']);

    $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM `{$table}` l {$joins} WHERE {$where_sql}");
    $count_stmt->execute($count_params);
    $total = (int)$count_stmt->fetchColumn();

    $order = !empty($definition['medicine'])
        ? 'CASE WHEN l.doctor_id = :doctor_order THEN 0 ELSE 1 END, l.usage_count DESC, l.generic_name ASC, l.brand_name ASC, l.id DESC'
        : 'CASE WHEN l.doctor_id = :doctor_order THEN 0 ELSE 1 END, l.usage_count DESC, l.option_value ASC, l.id DESC';

    $stmt = $pdo->prepare("
        SELECT l.*,
               CASE WHEN l.doctor_id = 0 THEN 1 ELSE 0 END AS is_demo
        FROM `{$table}` l
        {$joins}
        WHERE {$where_sql}
        ORDER BY {$order}
        LIMIT {$limit} OFFSET {$offset}
    ");
    $stmt->execute($params);

    return [
        'items' => $stmt->fetchAll(PDO::FETCH_ASSOC),
        'total' => $total,
    ];
}

function prescription_count_visible_library_items(PDO $pdo, int $doctor_id, string $type): int
{
    $result = prescription_get_library_management_items($pdo, $doctor_id, $type, '', '', '', 1, 0);

    return (int)($result['total'] ?? 0);
}

function prescription_increment_library_usage(
    PDO $pdo,
    int $doctor_id,
    string $type,
    string $value,
    string $secondary = '',
    int $item_id = 0
): void {
    try {
        $type = prescription_normalize_library_type($type);
        $definition = prescription_library_definition($type);
        $value = trim($value);

        if (!$definition || $doctor_id <= 0 || $value === '') {
            return;
        }

        $table = (string)$definition['table'];

        if (!prescription_table_exists($pdo, $table)) {
            return;
        }

        if ($item_id > 0) {
            $stmt = $pdo->prepare("
                UPDATE `{$table}`
                SET usage_count = usage_count + 1, updated_at = NOW()
                WHERE id = :id
                  AND doctor_id IN (0, :doctor_id)
                LIMIT 1
            ");
            $stmt->execute([':id' => $item_id, ':doctor_id' => $doctor_id]);
            return;
        }

        if (!empty($definition['medicine'])) {
            $find = $pdo->prepare("
                SELECT id
                FROM `{$table}`
                WHERE doctor_id IN (0, :doctor_id)
                  AND generic_name = :generic_name
                  AND brand_name = :brand_name
                ORDER BY CASE WHEN doctor_id = :doctor_order THEN 0 ELSE 1 END
                LIMIT 1
            ");
            $find->execute([
                ':doctor_id' => $doctor_id,
                ':doctor_order' => $doctor_id,
                ':generic_name' => $value,
                ':brand_name' => trim($secondary),
            ]);
        } else {
            $find = $pdo->prepare("
                SELECT id
                FROM `{$table}`
                WHERE doctor_id IN (0, :doctor_id)
                  AND option_value = :option_value
                ORDER BY CASE WHEN doctor_id = :doctor_order THEN 0 ELSE 1 END
                LIMIT 1
            ");
            $find->execute([
                ':doctor_id' => $doctor_id,
                ':doctor_order' => $doctor_id,
                ':option_value' => $value,
            ]);
        }

        $found_id = (int)$find->fetchColumn();

        if ($found_id > 0) {
            $pdo->prepare("UPDATE `{$table}` SET usage_count = usage_count + 1, updated_at = NOW() WHERE id = :id LIMIT 1")
                ->execute([':id' => $found_id]);
        }
    } catch (Throwable $e) {
        // Usage counters are helpful but must never roll back a prescription save.
        error_log('[Prescription Library Usage][' . $type . '] ' . $e->getMessage());
    }
}

// Backward-compatible wrappers used by older module files.
function prescription_get_medicine_library_item(PDO $pdo, int $item_id, int $doctor_id): ?array
{
    return prescription_get_library_item($pdo, $item_id, $doctor_id, 'medicine');
}

function prescription_get_medicine_library_options(PDO $pdo, int $doctor_id, string $field, int $limit = 100): array
{
    $rows = prescription_get_library_options($pdo, $doctor_id, $field, '', $limit);

    return array_values(array_filter(array_map(
        static fn(array $row): string => trim((string)($row['option_value'] ?? '')),
        $rows
    )));
}

function prescription_save_medicine_library_item(PDO $pdo, int $doctor_id, array $source, int $item_id = 0): int
{
    return prescription_save_library_item($pdo, $doctor_id, 'medicine', $source, $item_id);
}

function prescription_delete_medicine_library_item(PDO $pdo, int $item_id, int $doctor_id): void
{
    prescription_delete_library_item($pdo, $item_id, $doctor_id, 'medicine');
}

function prescription_get_medicines(PDO $pdo, int $prescription_id): array
{
    $stmt = $pdo->prepare("
        SELECT *
        FROM prescription_medicines
        WHERE prescription_id = :prescription_id
        ORDER BY sort_order ASC, id ASC
    ");
    $stmt->execute([':prescription_id' => $prescription_id]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function prescription_get_tests(PDO $pdo, int $prescription_id): array
{
    $stmt = $pdo->prepare("
        SELECT *
        FROM prescription_tests
        WHERE prescription_id = :prescription_id
        ORDER BY sort_order ASC, id ASC
    ");
    $stmt->execute([':prescription_id' => $prescription_id]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function prescription_extract_rows(array $source, string $primary_key, array $keys): array
{
    $primary = $source[$primary_key] ?? [];

    if (!is_array($primary)) {
        return [];
    }

    $rows = [];

    foreach ($primary as $index => $primary_value) {
        $primary_value = prescription_clean_text($primary_value, 200);

        if ($primary_value === '') {
            continue;
        }

        $row = [$primary_key => $primary_value];

        foreach ($keys as $key => $limit) {
            $values = $source[$key] ?? [];
            $row[$key] = is_array($values)
                ? prescription_clean_text($values[$index] ?? '', $limit)
                : '';
        }

        $rows[] = $row;
    }

    return $rows;
}

function prescription_upsert_patient(PDO $pdo, int $doctor_id, array $data): int
{
    $patient_id = (int)($data['patient_id'] ?? 0);
    $name = prescription_clean_text($data['patient_name'] ?? '', 150);
    $phone = prescription_clean_text($data['patient_phone'] ?? '', 50);
    $phone_normalized = prescription_normalize_phone($phone);
    $age = prescription_clean_text($data['patient_age'] ?? '', 30);
    $gender = prescription_clean_text($data['patient_gender'] ?? '', 20);
    $blood_group = prescription_clean_text($data['patient_blood_group'] ?? '', 20);
    $address = prescription_clean_text($data['patient_address'] ?? '', 1000);
    $has_phone_normalized = prescription_column_exists(
        $pdo,
        'prescription_patients',
        'phone_normalized'
    );

    if ($patient_id <= 0 && $phone !== '') {
        $where_phone = $has_phone_normalized && $phone_normalized !== ''
            ? '(phone = :phone OR phone_normalized = :phone_normalized)'
            : 'phone = :phone';

        $stmt = $pdo->prepare("
            SELECT id
            FROM prescription_patients
            WHERE doctor_id = :doctor_id
              AND {$where_phone}
            ORDER BY id DESC
            LIMIT 1
        ");

        $params = [
            ':doctor_id' => $doctor_id,
            ':phone' => $phone,
        ];

        if ($has_phone_normalized && $phone_normalized !== '') {
            $params[':phone_normalized'] = $phone_normalized;
        }

        $stmt->execute($params);
        $patient_id = (int)$stmt->fetchColumn();
    }

    if ($patient_id <= 0 && $phone === '' && $name !== '') {
        $stmt = $pdo->prepare("
            SELECT id
            FROM prescription_patients
            WHERE doctor_id = :doctor_id
              AND LOWER(TRIM(name)) = LOWER(TRIM(:name))
            ORDER BY id DESC
            LIMIT 2
        ");
        $stmt->execute([
            ':doctor_id' => $doctor_id,
            ':name' => $name,
        ]);
        $name_matches = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (count($name_matches) === 1) {
            $patient_id = (int)$name_matches[0];
        }
    }

    if ($patient_id > 0) {
        $patient = prescription_get_patient($pdo, $patient_id, $doctor_id);

        if (!$patient) {
            throw new RuntimeException('Selected patient was not found.');
        }

        $assignments = [
            'name = :name',
            "phone = COALESCE(NULLIF(:phone, ''), phone)",
            "age = COALESCE(NULLIF(:age, ''), age)",
            "gender = COALESCE(NULLIF(:gender, ''), gender)",
            "blood_group = COALESCE(NULLIF(:blood_group, ''), blood_group)",
            "address = COALESCE(NULLIF(:address, ''), address)",
            'updated_at = NOW()',
        ];

        $params = [
            ':name' => $name,
            ':phone' => $phone,
            ':age' => $age,
            ':gender' => $gender,
            ':blood_group' => $blood_group,
            ':address' => $address,
            ':id' => $patient_id,
            ':doctor_id' => $doctor_id,
        ];

        if ($has_phone_normalized) {
            array_splice(
                $assignments,
                2,
                0,
                ["phone_normalized = COALESCE(NULLIF(:phone_normalized, ''), phone_normalized)"]
            );
            $params[':phone_normalized'] = $phone_normalized;
        }

        $stmt = $pdo->prepare("
            UPDATE prescription_patients
            SET " . implode(",
                ", $assignments) . "
            WHERE id = :id
              AND doctor_id = :doctor_id
            LIMIT 1
        ");
        $stmt->execute($params);

        return $patient_id;
    }

    $columns = [
        'doctor_id',
        'patient_code',
        'name',
        'phone',
        'age',
        'gender',
        'blood_group',
        'address',
        'created_at',
    ];

    $values = [
        ':doctor_id',
        ':patient_code',
        ':name',
        ':phone',
        ':age',
        ':gender',
        ':blood_group',
        ':address',
        'NOW()',
    ];

    $params = [
        ':doctor_id' => $doctor_id,
        ':patient_code' => prescription_generate_patient_code($pdo, $doctor_id),
        ':name' => $name,
        ':phone' => $phone !== '' ? $phone : null,
        ':age' => $age !== '' ? $age : null,
        ':gender' => $gender !== '' ? $gender : null,
        ':blood_group' => $blood_group !== '' ? $blood_group : null,
        ':address' => $address !== '' ? $address : null,
    ];

    if ($has_phone_normalized) {
        array_splice($columns, 4, 0, ['phone_normalized']);
        array_splice($values, 4, 0, [':phone_normalized']);
        $params[':phone_normalized'] = $phone_normalized !== '' ? $phone_normalized : null;
    }

    $stmt = $pdo->prepare(
        'INSERT INTO prescription_patients (`'
        . implode('`, `', $columns)
        . '`) VALUES ('
        . implode(', ', $values)
        . ')'
    );
    $stmt->execute($params);

    return (int)$pdo->lastInsertId();
}

function prescription_refresh_patient_snapshot(PDO $pdo, int $doctor_id, int $patient_id): void
{
    if ($patient_id <= 0) {
        return;
    }

    $column_map = [
        'last_weight' => 'weight',
        'last_height' => 'height',
        'last_blood_pressure' => 'blood_pressure',
        'last_temperature' => 'temperature',
        'last_pulse' => 'pulse',
        'last_spo2' => 'spo2',
        'medical_history' => 'medical_history',
        'last_visit_date' => 'visit_date',
    ];

    $available = [];

    foreach ($column_map as $patient_column => $prescription_column) {
        if (prescription_column_exists($pdo, 'prescription_patients', $patient_column)) {
            $available[$patient_column] = $prescription_column;
        }
    }

    if (!$available) {
        return;
    }

    $stmt = $pdo->prepare("
        SELECT
            visit_date,
            weight,
            height,
            blood_pressure,
            temperature,
            pulse,
            spo2,
            medical_history
        FROM prescriptions
        WHERE doctor_id = :doctor_id
          AND patient_id = :patient_id
        ORDER BY visit_date DESC, id DESC
        LIMIT 1
    ");
    $stmt->execute([
        ':doctor_id' => $doctor_id,
        ':patient_id' => $patient_id,
    ]);
    $latest = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$latest) {
        $clearable = array_filter(
            array_keys($available),
            static fn(string $column): bool => $column !== 'medical_history'
        );

        if (!$clearable) {
            return;
        }

        $assignments = array_map(
            static fn(string $column): string => "`{$column}` = NULL",
            $clearable
        );
        $assignments[] = 'updated_at = NOW()';

        $pdo->prepare("
            UPDATE prescription_patients
            SET " . implode(",
                ", $assignments) . "
            WHERE id = :patient_id
              AND doctor_id = :doctor_id
            LIMIT 1
        ")->execute([
            ':patient_id' => $patient_id,
            ':doctor_id' => $doctor_id,
        ]);
        return;
    }

    $assignments = [];
    $params = [
        ':patient_id' => $patient_id,
        ':doctor_id' => $doctor_id,
    ];

    foreach ($available as $patient_column => $prescription_column) {
        $placeholder = ':' . $patient_column;

        if ($patient_column === 'last_visit_date') {
            $assignments[] = "`{$patient_column}` = {$placeholder}";
            $params[$placeholder] = $latest[$prescription_column] ?? null;
        } else {
            $assignments[] = "`{$patient_column}` = COALESCE(NULLIF({$placeholder}, ''), `{$patient_column}`)";
            $params[$placeholder] = (string)($latest[$prescription_column] ?? '');
        }
    }

    $assignments[] = 'updated_at = NOW()';

    $update = $pdo->prepare("
        UPDATE prescription_patients
        SET " . implode(",
            ", $assignments) . "
        WHERE id = :patient_id
          AND doctor_id = :doctor_id
        LIMIT 1
    ");
    $update->execute($params);
}

function prescription_save_from_request(
    PDO $pdo,
    int $doctor_id,
    array $source,
    int $prescription_id = 0,
    string $forced_status = ''
): int {
    $patient_name = prescription_clean_text($source['patient_name'] ?? '', 150);

    if ($patient_name === '') {
        throw new InvalidArgumentException('Patient name is required.');
    }

    $visit_date = prescription_valid_date($source['visit_date'] ?? '', false) ?? date('Y-m-d');
    $follow_up_date = prescription_valid_date($source['follow_up_date'] ?? '', true);

    $status = $forced_status !== ''
        ? $forced_status
        : prescription_clean_text($source['save_status'] ?? 'active', 20);

    if (!in_array($status, ['draft', 'active'], true)) {
        $status = 'active';
    }

    $data = [
        'patient_id' => (int)($source['patient_id'] ?? 0),
        'patient_name' => $patient_name,
        'patient_age' => prescription_clean_text($source['patient_age'] ?? '', 30),
        'patient_gender' => prescription_clean_text($source['patient_gender'] ?? '', 20),
        'patient_phone' => prescription_clean_text($source['patient_phone'] ?? '', 50),
        'patient_blood_group' => prescription_clean_text($source['patient_blood_group'] ?? '', 20),
        'patient_address' => prescription_clean_text($source['patient_address'] ?? '', 1000),
        'visit_date' => $visit_date,
        'weight' => prescription_clean_text($source['weight'] ?? '', 30),
        'height' => prescription_clean_text($source['height'] ?? '', 30),
        'blood_pressure' => prescription_clean_text($source['blood_pressure'] ?? '', 30),
        'temperature' => prescription_clean_text($source['temperature'] ?? '', 30),
        'pulse' => prescription_clean_text($source['pulse'] ?? '', 30),
        'spo2' => prescription_clean_text($source['spo2'] ?? '', 30),
        'chief_complaints' => prescription_clean_text($source['chief_complaints'] ?? '', 5000),
        'medical_history' => prescription_clean_text($source['medical_history'] ?? '', 5000),
        'examination' => prescription_clean_text($source['examination'] ?? '', 5000),
        'diagnosis' => prescription_clean_text($source['diagnosis'] ?? '', 5000),
        'advice' => prescription_clean_text($source['advice'] ?? '', 5000),
        'follow_up_date' => $follow_up_date,
        'private_notes' => prescription_clean_text($source['private_notes'] ?? '', 5000),
        'status' => $status,
    ];

    if (
        empty($source['medicine_generic_name'])
        && !empty($source['medicine_name'])
        && is_array($source['medicine_name'])
    ) {
        $source['medicine_generic_name'] = $source['medicine_name'];
    }

    $medicines = prescription_extract_rows($source, 'medicine_generic_name', [
        'medicine_library_id' => 30,
        'medicine_brand_name' => 200,
        'medicine_form' => 100,
        'medicine_strength' => 100,
        'medicine_dosage' => 100,
        'medicine_frequency' => 100,
        'medicine_duration' => 100,
        'medicine_instruction' => 255,
    ]);

    $tests = prescription_extract_rows($source, 'test_name', [
        'test_instruction' => 255,
    ]);

    $previous_patient_id = 0;
    $pdo->beginTransaction();

    try {
        $patient_id = prescription_upsert_patient($pdo, $doctor_id, $data);

        if ($prescription_id > 0) {
            $existing = prescription_get_record($pdo, $prescription_id, $doctor_id);

            if (!$existing) {
                throw new RuntimeException('Prescription was not found or access was denied.');
            }

            $previous_patient_id = (int)($existing['patient_id'] ?? 0);

            $stmt = $pdo->prepare("
                UPDATE prescriptions
                SET patient_id = :patient_id,
                    patient_name = :patient_name,
                    patient_age = :patient_age,
                    patient_gender = :patient_gender,
                    patient_phone = :patient_phone,
                    patient_blood_group = :patient_blood_group,
                    patient_address = :patient_address,
                    visit_date = :visit_date,
                    weight = :weight,
                    height = :height,
                    blood_pressure = :blood_pressure,
                    temperature = :temperature,
                    pulse = :pulse,
                    spo2 = :spo2,
                    chief_complaints = :chief_complaints,
                    medical_history = :medical_history,
                    examination = :examination,
                    diagnosis = :diagnosis,
                    advice = :advice,
                    follow_up_date = :follow_up_date,
                    private_notes = :private_notes,
                    status = :status,
                    updated_at = NOW()
                WHERE id = :id
                  AND doctor_id = :doctor_id
                LIMIT 1
            ");
            $stmt->execute([
                ':patient_id' => $patient_id,
                ':patient_name' => $data['patient_name'],
                ':patient_age' => $data['patient_age'] !== '' ? $data['patient_age'] : null,
                ':patient_gender' => $data['patient_gender'] !== '' ? $data['patient_gender'] : null,
                ':patient_phone' => $data['patient_phone'] !== '' ? $data['patient_phone'] : null,
                ':patient_blood_group' => $data['patient_blood_group'] !== '' ? $data['patient_blood_group'] : null,
                ':patient_address' => $data['patient_address'] !== '' ? $data['patient_address'] : null,
                ':visit_date' => $data['visit_date'],
                ':weight' => $data['weight'] !== '' ? $data['weight'] : null,
                ':height' => $data['height'] !== '' ? $data['height'] : null,
                ':blood_pressure' => $data['blood_pressure'] !== '' ? $data['blood_pressure'] : null,
                ':temperature' => $data['temperature'] !== '' ? $data['temperature'] : null,
                ':pulse' => $data['pulse'] !== '' ? $data['pulse'] : null,
                ':spo2' => $data['spo2'] !== '' ? $data['spo2'] : null,
                ':chief_complaints' => $data['chief_complaints'] !== '' ? $data['chief_complaints'] : null,
                ':medical_history' => $data['medical_history'] !== '' ? $data['medical_history'] : null,
                ':examination' => $data['examination'] !== '' ? $data['examination'] : null,
                ':diagnosis' => $data['diagnosis'] !== '' ? $data['diagnosis'] : null,
                ':advice' => $data['advice'] !== '' ? $data['advice'] : null,
                ':follow_up_date' => $data['follow_up_date'],
                ':private_notes' => $data['private_notes'] !== '' ? $data['private_notes'] : null,
                ':status' => $data['status'],
                ':id' => $prescription_id,
                ':doctor_id' => $doctor_id,
            ]);
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO prescriptions
                (
                    prescription_no,
                    doctor_id,
                    patient_id,
                    patient_name,
                    patient_age,
                    patient_gender,
                    patient_phone,
                    patient_blood_group,
                    patient_address,
                    visit_date,
                    weight,
                    height,
                    blood_pressure,
                    temperature,
                    pulse,
                    spo2,
                    chief_complaints,
                    medical_history,
                    examination,
                    diagnosis,
                    advice,
                    follow_up_date,
                    private_notes,
                    status,
                    created_at
                )
                VALUES
                (
                    :prescription_no,
                    :doctor_id,
                    :patient_id,
                    :patient_name,
                    :patient_age,
                    :patient_gender,
                    :patient_phone,
                    :patient_blood_group,
                    :patient_address,
                    :visit_date,
                    :weight,
                    :height,
                    :blood_pressure,
                    :temperature,
                    :pulse,
                    :spo2,
                    :chief_complaints,
                    :medical_history,
                    :examination,
                    :diagnosis,
                    :advice,
                    :follow_up_date,
                    :private_notes,
                    :status,
                    NOW()
                )
            ");
            $stmt->execute([
                ':prescription_no' => prescription_generate_number($pdo, $doctor_id, $data['visit_date']),
                ':doctor_id' => $doctor_id,
                ':patient_id' => $patient_id,
                ':patient_name' => $data['patient_name'],
                ':patient_age' => $data['patient_age'] !== '' ? $data['patient_age'] : null,
                ':patient_gender' => $data['patient_gender'] !== '' ? $data['patient_gender'] : null,
                ':patient_phone' => $data['patient_phone'] !== '' ? $data['patient_phone'] : null,
                ':patient_blood_group' => $data['patient_blood_group'] !== '' ? $data['patient_blood_group'] : null,
                ':patient_address' => $data['patient_address'] !== '' ? $data['patient_address'] : null,
                ':visit_date' => $data['visit_date'],
                ':weight' => $data['weight'] !== '' ? $data['weight'] : null,
                ':height' => $data['height'] !== '' ? $data['height'] : null,
                ':blood_pressure' => $data['blood_pressure'] !== '' ? $data['blood_pressure'] : null,
                ':temperature' => $data['temperature'] !== '' ? $data['temperature'] : null,
                ':pulse' => $data['pulse'] !== '' ? $data['pulse'] : null,
                ':spo2' => $data['spo2'] !== '' ? $data['spo2'] : null,
                ':chief_complaints' => $data['chief_complaints'] !== '' ? $data['chief_complaints'] : null,
                ':medical_history' => $data['medical_history'] !== '' ? $data['medical_history'] : null,
                ':examination' => $data['examination'] !== '' ? $data['examination'] : null,
                ':diagnosis' => $data['diagnosis'] !== '' ? $data['diagnosis'] : null,
                ':advice' => $data['advice'] !== '' ? $data['advice'] : null,
                ':follow_up_date' => $data['follow_up_date'],
                ':private_notes' => $data['private_notes'] !== '' ? $data['private_notes'] : null,
                ':status' => $data['status'],
            ]);

            $prescription_id = (int)$pdo->lastInsertId();
        }

        $pdo->prepare("DELETE FROM prescription_medicines WHERE prescription_id = :id")
            ->execute([':id' => $prescription_id]);
        $pdo->prepare("DELETE FROM prescription_tests WHERE prescription_id = :id")
            ->execute([':id' => $prescription_id]);

        if ($medicines) {
            $medicine_columns = [
                'prescription_id',
                'medicine_name',
                'strength',
                'dosage',
                'frequency',
                'duration',
                'instruction',
                'sort_order',
                'created_at',
            ];
            $medicine_values = [
                ':prescription_id',
                ':medicine_name',
                ':strength',
                ':dosage',
                ':frequency',
                ':duration',
                ':instruction',
                ':sort_order',
                'NOW()',
            ];

            $optional_medicine_columns = [
                'library_id' => ':library_id',
                'medicine_form' => ':medicine_form',
                'generic_name' => ':generic_name',
                'brand_name' => ':brand_name',
            ];

            foreach (array_reverse($optional_medicine_columns, true) as $column => $placeholder) {
                if (prescription_column_exists($pdo, 'prescription_medicines', $column)) {
                    array_splice($medicine_columns, 1, 0, [$column]);
                    array_splice($medicine_values, 1, 0, [$placeholder]);
                }
            }

            $medicine_stmt = $pdo->prepare(
                'INSERT INTO prescription_medicines (`'
                . implode('`, `', $medicine_columns)
                . '`) VALUES ('
                . implode(', ', $medicine_values)
                . ')'
            );

            foreach ($medicines as $index => $medicine) {
                $library_id = max(0, (int)($medicine['medicine_library_id'] ?? 0));
                $generic_name = prescription_clean_text(
                    $medicine['medicine_generic_name'] ?? '',
                    200
                );
                $brand_name = prescription_clean_text(
                    $medicine['medicine_brand_name'] ?? '',
                    200
                );
                $medicine_form = prescription_clean_text(
                    $medicine['medicine_form'] ?? '',
                    100
                );

                $medicine_params = [
                    ':prescription_id' => $prescription_id,
                    ':medicine_name' => $generic_name,
                    ':strength' => $medicine['medicine_strength'] !== '' ? $medicine['medicine_strength'] : null,
                    ':dosage' => $medicine['medicine_dosage'] !== '' ? $medicine['medicine_dosage'] : null,
                    ':frequency' => $medicine['medicine_frequency'] !== '' ? $medicine['medicine_frequency'] : null,
                    ':duration' => $medicine['medicine_duration'] !== '' ? $medicine['medicine_duration'] : null,
                    ':instruction' => $medicine['medicine_instruction'] !== '' ? $medicine['medicine_instruction'] : null,
                    ':sort_order' => $index,
                ];

                if (in_array('library_id', $medicine_columns, true)) {
                    $medicine_params[':library_id'] = $library_id > 0 ? $library_id : null;
                }

                if (in_array('medicine_form', $medicine_columns, true)) {
                    $medicine_params[':medicine_form'] = $medicine_form !== '' ? $medicine_form : null;
                }

                if (in_array('generic_name', $medicine_columns, true)) {
                    $medicine_params[':generic_name'] = $generic_name;
                }

                if (in_array('brand_name', $medicine_columns, true)) {
                    $medicine_params[':brand_name'] = $brand_name !== '' ? $brand_name : null;
                }

                $medicine_stmt->execute($medicine_params);

                prescription_increment_library_usage(
                    $pdo,
                    $doctor_id,
                    'medicine',
                    $generic_name,
                    $brand_name,
                    0
                );
                prescription_increment_library_usage($pdo, $doctor_id, 'medicine_form', (string)($medicine['medicine_form'] ?? ''));
                prescription_increment_library_usage($pdo, $doctor_id, 'strength', (string)($medicine['medicine_strength'] ?? ''));
                prescription_increment_library_usage($pdo, $doctor_id, 'dosage', (string)($medicine['medicine_dosage'] ?? ''));
                prescription_increment_library_usage($pdo, $doctor_id, 'frequency', (string)($medicine['medicine_frequency'] ?? ''));
                prescription_increment_library_usage($pdo, $doctor_id, 'duration', (string)($medicine['medicine_duration'] ?? ''));
                prescription_increment_library_usage($pdo, $doctor_id, 'instruction', (string)($medicine['medicine_instruction'] ?? ''));
            }
        }

        if ($tests) {
            $test_stmt = $pdo->prepare("
                INSERT INTO prescription_tests
                (
                    prescription_id,
                    test_name,
                    instruction,
                    sort_order,
                    created_at
                )
                VALUES
                (
                    :prescription_id,
                    :test_name,
                    :instruction,
                    :sort_order,
                    NOW()
                )
            ");

            foreach ($tests as $index => $test) {
                $test_stmt->execute([
                    ':prescription_id' => $prescription_id,
                    ':test_name' => $test['test_name'],
                    ':instruction' => $test['test_instruction'] !== '' ? $test['test_instruction'] : null,
                    ':sort_order' => $index,
                ]);

                prescription_increment_library_usage($pdo, $doctor_id, 'test', (string)$test['test_name']);
                prescription_increment_library_usage($pdo, $doctor_id, 'test_instruction', (string)($test['test_instruction'] ?? ''));
            }
        }

        $clinical_usage_map = [
            'chief_complaints' => 'complaint',
            'diagnosis' => 'diagnosis',
            'medical_history' => 'history',
            'examination' => 'examination',
            'advice' => 'advice',
        ];

        foreach ($clinical_usage_map as $field => $library_type) {
            $lines = preg_split('/\R+/', (string)($data[$field] ?? '')) ?: [];

            foreach ($lines as $line) {
                prescription_increment_library_usage($pdo, $doctor_id, $library_type, trim((string)$line));
            }
        }

        prescription_refresh_patient_snapshot($pdo, $doctor_id, $patient_id);

        if ($previous_patient_id > 0 && $previous_patient_id !== $patient_id) {
            prescription_refresh_patient_snapshot($pdo, $doctor_id, $previous_patient_id);
        }

        $pdo->commit();

        return $prescription_id;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $e;
    }
}

function prescription_delete_record(PDO $pdo, int $prescription_id, int $doctor_id): void
{
    $record = prescription_get_record($pdo, $prescription_id, $doctor_id);

    if (!$record) {
        throw new RuntimeException('Prescription was not found or access was denied.');
    }

    $pdo->beginTransaction();

    try {
        $pdo->prepare("DELETE FROM prescription_medicines WHERE prescription_id = :id")
            ->execute([':id' => $prescription_id]);
        $pdo->prepare("DELETE FROM prescription_tests WHERE prescription_id = :id")
            ->execute([':id' => $prescription_id]);
        $pdo->prepare("DELETE FROM prescriptions WHERE id = :id AND doctor_id = :doctor_id LIMIT 1")
            ->execute([
                ':id' => $prescription_id,
                ':doctor_id' => $doctor_id,
            ]);

        prescription_refresh_patient_snapshot($pdo, $doctor_id, (int)($record['patient_id'] ?? 0));

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $e;
    }
}

function prescription_status_label(string $status): string
{
    return match ($status) {
        'draft' => 'Draft',
        'active' => 'Completed',
        'cancelled' => 'Cancelled',
        default => ucfirst($status),
    };
}

function prescription_date($value, string $format = 'd M Y'): string
{
    $value = trim((string)$value);

    if ($value === '') {
        return '—';
    }

    try {
        return (new DateTimeImmutable($value))->format($format);
    } catch (Throwable $e) {
        return $value;
    }
}

try {
    if (isset($pdo) && $pdo instanceof PDO && !prescription_schema_ready($pdo)) {
        prescription_install_schema($pdo);
    }
} catch (Throwable $e) {
    error_log('[Prescription Module] Schema setup failed: ' . $e->getMessage());
}


/*
|--------------------------------------------------------------------------
| Separate Library Page Map
|--------------------------------------------------------------------------
*/

function prescription_managed_library_pages(): array
{
    return [
        'medicine' => ['list' => 'Medicine.php', 'form' => 'Medicine_form.php'],
        'medicine_form' => ['list' => 'Medicine_type.php', 'form' => 'Medicine_type_form.php'],
        'strength' => ['list' => 'Strength.php', 'form' => 'Strength_form.php'],
        'dosage' => ['list' => 'Dosage.php', 'form' => 'Dosage_form.php'],
        'frequency' => ['list' => 'Frequency.php', 'form' => 'Frequency_form.php'],
        'duration' => ['list' => 'Duration.php', 'form' => 'Duration_form.php'],
        'instruction' => ['list' => 'Medicine_instruction.php', 'form' => 'Medicine_instruction_form.php'],
        'test' => ['list' => 'Tests.php', 'form' => 'Tests_form.php'],
        'diagnosis' => ['list' => 'Diagnosis.php', 'form' => 'Diagnosis_form.php'],
        'complaint' => ['list' => 'Chief_complaints.php', 'form' => 'Chief_complaints_form.php'],
        'examination' => ['list' => 'Examination_findings.php', 'form' => 'Examination_findings_form.php'],
        'advice' => ['list' => 'Advice.php', 'form' => 'Advice_form.php'],
    ];
}

function prescription_managed_library_types(): array
{
    return array_keys(prescription_managed_library_pages());
}

function prescription_library_list_path(string $type): string
{
    $type = prescription_normalize_library_type($type);
    $pages = prescription_managed_library_pages();

    if (!isset($pages[$type])) {
        $type = 'medicine';
    }

    return 'Library/' . $pages[$type]['list'];
}

function prescription_library_form_path(string $type, int $item_id = 0): string
{
    $type = prescription_normalize_library_type($type);
    $pages = prescription_managed_library_pages();

    if (!isset($pages[$type])) {
        $type = 'medicine';
    }

    $path = 'Library/' . $pages[$type]['form'];

    if ($item_id > 0) {
        $path .= '?id=' . $item_id;
    }

    return $path;
}

function prescription_library_dashboard_path(): string
{
    return 'librarys.php';
}

function prescription_library_dashboard_url(): string
{
    return prescription_url(prescription_library_dashboard_path());
}

function prescription_library_list_url(string $type): string
{
    return prescription_url(prescription_library_list_path($type));
}

function prescription_library_form_url(string $type, int $item_id = 0): string
{
    return prescription_url(prescription_library_form_path($type, $item_id));
}
