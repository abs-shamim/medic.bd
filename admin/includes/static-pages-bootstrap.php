<?php
/**
 * Shared setup for Static Pages admin list and form.
 */

require_once __DIR__ . '/../../includes/static-page-helper.php';

if (!function_exists('admin_static_pages_create_table')) {
    function admin_static_pages_create_table(): void
    {
        global $pdo;

        if (!isset($pdo) || !$pdo instanceof PDO) {
            throw new RuntimeException('Database connection is not available.');
        }

        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS `static_pages` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `slug` VARCHAR(100) NOT NULL,
                `title_en` VARCHAR(255) NOT NULL DEFAULT '',
                `title_bn` VARCHAR(255) NOT NULL DEFAULT '',
                `kicker_en` VARCHAR(255) NOT NULL DEFAULT '',
                `kicker_bn` VARCHAR(255) NOT NULL DEFAULT '',
                `intro_en` TEXT NULL,
                `intro_bn` TEXT NULL,
                `content_en` LONGTEXT NULL,
                `content_bn` LONGTEXT NULL,
                `meta_title_en` VARCHAR(255) NOT NULL DEFAULT '',
                `meta_title_bn` VARCHAR(255) NOT NULL DEFAULT '',
                `meta_description_en` TEXT NULL,
                `meta_description_bn` TEXT NULL,
                `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
                `delete_scheduled_at` DATETIME NULL DEFAULT NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `static_pages_slug_unique` (`slug`),
                KEY `static_pages_status_index` (`status`),
                KEY `static_pages_delete_scheduled_index` (`delete_scheduled_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }
}

if (!function_exists('admin_static_pages_column_exists')) {
    function admin_static_pages_column_exists(string $column): bool
    {
        global $pdo;

        if (!isset($pdo) || !$pdo instanceof PDO) {
            return false;
        }

        $statement = $pdo->prepare(
            'SELECT COUNT(*)
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = :table
               AND COLUMN_NAME = :column'
        );
        $statement->execute([
            ':table' => 'static_pages',
            ':column' => $column,
        ]);

        return (int) $statement->fetchColumn() > 0;
    }
}

if (!function_exists('admin_static_pages_index_exists')) {
    function admin_static_pages_index_exists(string $index): bool
    {
        global $pdo;

        if (!isset($pdo) || !$pdo instanceof PDO) {
            return false;
        }

        $statement = $pdo->prepare(
            'SELECT COUNT(*)
             FROM INFORMATION_SCHEMA.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = :table
               AND INDEX_NAME = :index'
        );
        $statement->execute([
            ':table' => 'static_pages',
            ':index' => $index,
        ]);

        return (int) $statement->fetchColumn() > 0;
    }
}

if (!function_exists('admin_static_pages_ensure_delete_schedule_column')) {
    function admin_static_pages_ensure_delete_schedule_column(): void
    {
        global $pdo;

        if (!isset($pdo) || !$pdo instanceof PDO) {
            throw new RuntimeException('Database connection is not available.');
        }

        if (!admin_static_pages_column_exists('delete_scheduled_at')) {
            $pdo->exec(
                'ALTER TABLE `static_pages`
                 ADD COLUMN `delete_scheduled_at` DATETIME NULL DEFAULT NULL AFTER `status`'
            );
        }

        if (!admin_static_pages_index_exists('static_pages_delete_scheduled_index')) {
            $pdo->exec(
                'ALTER TABLE `static_pages`
                 ADD KEY `static_pages_delete_scheduled_index` (`delete_scheduled_at`)'
            );
        }
    }
}

if (!function_exists('admin_static_pages_cleanup_scheduled_deletions')) {
    /**
     * Removes only non-core pages whose one-hour deletion window has ended.
     * Core policy pages are intentionally protected because the public footer
     * depends on their fixed URLs and bootstrap seeds their defaults.
     */
    function admin_static_pages_cleanup_scheduled_deletions(): int
    {
        global $pdo;

        if (!isset($pdo) || !$pdo instanceof PDO || !admin_static_pages_column_exists('delete_scheduled_at')) {
            return 0;
        }

        $coreSlugs = medic_static_page_allowed_slugs();
        $placeholders = implode(', ', array_fill(0, count($coreSlugs), '?'));

        $statement = $pdo->prepare(
            "DELETE FROM static_pages
             WHERE delete_scheduled_at IS NOT NULL
               AND delete_scheduled_at <= NOW()
               AND slug NOT IN ({$placeholders})"
        );
        $statement->execute($coreSlugs);

        return $statement->rowCount();
    }
}

if (!function_exists('admin_static_pages_seed_defaults')) {
    function admin_static_pages_seed_defaults(): void
    {
        global $pdo;

        $statement = $pdo->prepare(
            'INSERT IGNORE INTO static_pages (
                slug, title_en, title_bn, kicker_en, kicker_bn, intro_en, intro_bn,
                content_en, content_bn, meta_title_en, meta_title_bn,
                meta_description_en, meta_description_bn, status, delete_scheduled_at, created_at, updated_at
            ) VALUES (
                :slug, :title_en, :title_bn, :kicker_en, :kicker_bn, :intro_en, :intro_bn,
                :content_en, :content_bn, :meta_title_en, :meta_title_bn,
                :meta_description_en, :meta_description_bn, :status, NULL, NOW(), NOW()
            )'
        );

        foreach (medic_static_page_defaults() as $page) {
            $statement->execute([
                ':slug' => $page['slug'],
                ':title_en' => $page['title_en'],
                ':title_bn' => $page['title_bn'],
                ':kicker_en' => $page['kicker_en'],
                ':kicker_bn' => $page['kicker_bn'],
                ':intro_en' => $page['intro_en'],
                ':intro_bn' => $page['intro_bn'],
                ':content_en' => $page['content_en'],
                ':content_bn' => $page['content_bn'],
                ':meta_title_en' => $page['meta_title_en'],
                ':meta_title_bn' => $page['meta_title_bn'],
                ':meta_description_en' => $page['meta_description_en'],
                ':meta_description_bn' => $page['meta_description_bn'],
                ':status' => $page['status'],
            ]);
        }
    }
}

if (!function_exists('admin_static_pages_reserved_slugs')) {
    function admin_static_pages_reserved_slugs(): array
    {
        return [
            'index', 'bn', 'admin', 'api', 'assets', 'uploads', 'includes', 'languages',
            'doctors', 'hospitals', 'specialties', 'contact',
            'doctor', 'hospital', 'specialty', 'login', 'register', 'logout',
            'dashboard', 'sitemap', 'sitemap.xml', 'robots.txt', 'favicon.ico',
        ];
    }
}

if (!function_exists('admin_static_pages_normalize_slug')) {
    function admin_static_pages_normalize_slug(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/', '-', $value);
        $value = trim((string) $value, '-');

        return substr($value, 0, 100);
    }
}

if (!function_exists('admin_static_pages_validate_slug')) {
    function admin_static_pages_validate_slug(string $slug, int $ignoreId = 0): ?string
    {
        global $pdo;

        if ($slug === '') {
            return 'A page URL slug is required.';
        }

        if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
            return 'Use lowercase letters, numbers and hyphens only for the page URL.';
        }

        if (in_array($slug, admin_static_pages_reserved_slugs(), true)) {
            return 'This page URL is reserved by the system. Please choose another slug.';
        }

        $statement = $pdo->prepare(
            'SELECT id
             FROM static_pages
             WHERE slug = :slug
               AND id != :id
             LIMIT 1'
        );
        $statement->execute([
            ':slug' => $slug,
            ':id' => $ignoreId,
        ]);

        if ($statement->fetchColumn()) {
            return 'Another page is already using this URL slug.';
        }

        return null;
    }
}

if (!function_exists('admin_static_pages_blank_page')) {
    function admin_static_pages_blank_page(): array
    {
        return [
            'id' => 0,
            'slug' => '',
            'title_en' => '',
            'title_bn' => '',
            'kicker_en' => '',
            'kicker_bn' => '',
            'intro_en' => '',
            'intro_bn' => '',
            'content_en' => '',
            'content_bn' => '',
            'meta_title_en' => '',
            'meta_title_bn' => '',
            'meta_description_en' => '',
            'meta_description_bn' => '',
            'status' => 'active',
            'delete_scheduled_at' => null,
            'created_at' => '',
            'updated_at' => '',
        ];
    }
}

if (!function_exists('admin_static_pages_bootstrap')) {
    function admin_static_pages_bootstrap(): string
    {
        if (function_exists('admin_can') && !admin_can('settings.view')) {
            http_response_code(403);
            exit('You do not have permission to manage static pages.');
        }

        admin_static_pages_create_table();
        admin_static_pages_ensure_delete_schedule_column();
        admin_static_pages_cleanup_scheduled_deletions();
        admin_static_pages_seed_defaults();

        if (empty($_SESSION['admin_static_pages_csrf'])) {
            $_SESSION['admin_static_pages_csrf'] = bin2hex(random_bytes(32));
        }

        return (string) $_SESSION['admin_static_pages_csrf'];
    }
}
