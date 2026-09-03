<?php
/*
|--------------------------------------------------------------------------
| Database Safety Helpers
|--------------------------------------------------------------------------
*/

function dp_table_exists(string $table): bool
{
    global $pdo;

    static $table_exists_cache = [];

    $table = trim($table);

    if ($table === '') {
        return false;
    }

    if (array_key_exists($table, $table_exists_cache)) {
        return $table_exists_cache[$table];
    }

    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM INFORMATION_SCHEMA.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = :table
        ");
        $stmt->execute([':table' => $table]);

        $table_exists_cache[$table] = (int)$stmt->fetchColumn() > 0;
        return $table_exists_cache[$table];
    } catch (Throwable $e) {
        $table_exists_cache[$table] = false;
        return false;
    }
}

function dp_column_exists(string $table, string $column): bool
{
    global $pdo;

    static $column_exists_cache = [];

    $table = trim($table);
    $column = trim($column);
    $cache_key = $table . '.' . $column;

    if ($table === '' || $column === '') {
        return false;
    }

    if (array_key_exists($cache_key, $column_exists_cache)) {
        return $column_exists_cache[$cache_key];
    }

    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = :table
            AND COLUMN_NAME = :column
        ");
        $stmt->execute([
            ':table' => $table,
            ':column' => $column,
        ]);

        $column_exists_cache[$cache_key] = (int)$stmt->fetchColumn() > 0;
        return $column_exists_cache[$cache_key];
    } catch (Throwable $e) {
        $column_exists_cache[$cache_key] = false;
        return false;
    }
}

function dp_name_column(string $table = ''): string
{
    $lang = defined('CURRENT_LANG') && CURRENT_LANG === 'bn' ? 'bn' : 'en';

    if ($lang === 'bn' && ($table === '' || dp_column_exists($table, 'name_bn'))) {
        return 'name_bn';
    }

    if ($table !== '' && dp_column_exists($table, 'name_en')) {
        return 'name_en';
    }

    if ($table !== '' && dp_column_exists($table, 'name')) {
        return 'name';
    }

    if ($table !== '' && dp_column_exists($table, 'name_bn')) {
        return 'name_bn';
    }

    return 'name';
}

function dp_row_slug(array $row): string
{
    if (!empty($row['slug'])) {
        return dp_url_slug((string)$row['slug']);
    }

    if (!empty($row['name_en'])) {
        return dp_url_slug((string)$row['name_en']);
    }

    if (!empty($row['name_raw'])) {
        return dp_url_slug((string)$row['name_raw']);
    }

    return dp_url_slug((string)($row['name'] ?? ''));
}

