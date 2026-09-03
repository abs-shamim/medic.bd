<?php
/**
 * Blog Module Helpers
 *
 * Raw PHP + PDO blog module for the MedicBD project.
 * This file creates the blog tables when they do not exist and provides
 * shared admin/public helpers. It intentionally contains no HTML output.
 */

require_once __DIR__ . '/functions.php';

if (!function_exists('blog_table_exists')) {
    function blog_table_exists(string $table): bool
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
}

if (!function_exists('blog_column_exists')) {
    function blog_column_exists(string $table, string $column): bool
    {
        global $pdo;
        static $cache = [];
        $key = $table . '.' . $column;

        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }

        try {
            $statement = $pdo->prepare(
                'SELECT COUNT(*)
                 FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = :table
                   AND COLUMN_NAME = :column'
            );
            $statement->execute([':table' => $table, ':column' => $column]);
            $cache[$key] = (int) $statement->fetchColumn() > 0;
        } catch (Throwable $exception) {
            $cache[$key] = false;
        }

        return $cache[$key];
    }
}

if (!function_exists('blog_add_column_if_missing')) {
    function blog_add_column_if_missing(string $table, string $column, string $definition): void
    {
        global $pdo;

        if (
            !isset($pdo)
            || !$pdo instanceof PDO
            || !in_array($table, ['blog_posts', 'blog_categories'], true)
            || !preg_match('/^[a-zA-Z0-9_]+$/', $column)
            || trim($definition) === ''
        ) {
            return;
        }

        if (blog_column_exists($table, $column)) {
            return;
        }

        try {
            $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
        } catch (Throwable $exception) {
            error_log('Blog schema update failed: ' . $exception->getMessage());
        }
    }
}

if (!function_exists('blog_ensure_tables')) {
    function blog_ensure_tables(): void
    {
        global $pdo;
        static $booted = false;

        if ($booted || !isset($pdo) || !$pdo instanceof PDO) {
            return;
        }

        $booted = true;

        try {
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS blog_categories (
                    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    slug VARCHAR(190) NOT NULL,
                    name_en VARCHAR(190) NOT NULL,
                    name_bn VARCHAR(190) NULL,
                    description_en TEXT NULL,
                    description_bn TEXT NULL,
                    meta_title_en VARCHAR(255) NULL,
                    meta_title_bn VARCHAR(255) NULL,
                    meta_description_en TEXT NULL,
                    meta_description_bn TEXT NULL,
                    status VARCHAR(20) NOT NULL DEFAULT 'active',
                    created_at DATETIME NULL,
                    updated_at DATETIME NULL,
                    PRIMARY KEY (id),
                    UNIQUE KEY blog_categories_slug_unique (slug),
                    KEY blog_categories_status_idx (status)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );

            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS blog_posts (
                    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    category_id INT UNSIGNED NOT NULL DEFAULT 0,
                    slug VARCHAR(190) NOT NULL,
                    title_en VARCHAR(255) NOT NULL,
                    title_bn VARCHAR(255) NULL,
                    excerpt_en TEXT NULL,
                    excerpt_bn TEXT NULL,
                    content_en LONGTEXT NULL,
                    content_bn LONGTEXT NULL,
                    content_css_en LONGTEXT NULL,
                    content_css_bn LONGTEXT NULL,
                    featured_image VARCHAR(500) NULL,
                    image_alt_en VARCHAR(255) NULL,
                    image_alt_bn VARCHAR(255) NULL,
                    author_name VARCHAR(120) NULL,
                    status VARCHAR(20) NOT NULL DEFAULT 'draft',
                    is_featured TINYINT(1) NOT NULL DEFAULT 0,
                    views INT UNSIGNED NOT NULL DEFAULT 0,
                    meta_title_en VARCHAR(255) NULL,
                    meta_title_bn VARCHAR(255) NULL,
                    meta_description_en TEXT NULL,
                    meta_description_bn TEXT NULL,
                    meta_keywords_en TEXT NULL,
                    meta_keywords_bn TEXT NULL,
                    published_at DATETIME NULL,
                    created_at DATETIME NULL,
                    updated_at DATETIME NULL,
                    PRIMARY KEY (id),
                    UNIQUE KEY blog_posts_slug_unique (slug),
                    KEY blog_posts_category_status_idx (category_id, status),
                    KEY blog_posts_public_idx (status, published_at),
                    KEY blog_posts_featured_idx (is_featured, status)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );

            // Separate GrapesJS-generated styles keep visual layouts intact on public posts.
            blog_add_column_if_missing('blog_posts', 'content_css_en', 'LONGTEXT NULL AFTER content_en');
            blog_add_column_if_missing('blog_posts', 'content_css_bn', 'LONGTEXT NULL AFTER content_bn');
        } catch (Throwable $exception) {
            error_log('Blog table bootstrap failed: ' . $exception->getMessage());
        }
    }
}

if (!function_exists('blog_lang')) {
    function blog_lang(?string $lang = null): string
    {
        $lang = $lang ?: (defined('CURRENT_LANG') ? (string) CURRENT_LANG : 'en');

        return $lang === 'bn' ? 'bn' : 'en';
    }
}

if (!function_exists('blog_text')) {
    function blog_text(array $row, string $field, ?string $lang = null, string $default = ''): string
    {
        $lang = blog_lang($lang);
        $localized = trim((string) ($row[$field . '_' . $lang] ?? ''));

        if ($localized !== '') {
            return $localized;
        }

        $english = trim((string) ($row[$field . '_en'] ?? ''));

        return $english !== '' ? $english : $default;
    }
}

if (!function_exists('blog_clean_text')) {
    function blog_clean_text($value, int $maxLength = 0): string
    {
        $value = trim((string) $value);
        $value = preg_replace('/\s+/u', ' ', $value);
        $value = trim((string) $value);

        if ($maxLength > 0 && function_exists('mb_substr')) {
            return mb_substr($value, 0, $maxLength, 'UTF-8');
        }

        return $maxLength > 0 ? substr($value, 0, $maxLength) : $value;
    }
}

if (!function_exists('blog_normalize_slug')) {
    function blog_normalize_slug(string $value): string
    {
        $value = trim(rawurldecode($value));

        if (function_exists('seo_url_slug')) {
            $value = seo_url_slug($value);
        } else {
            $value = preg_replace('/[^\p{L}\p{N}\s-]+/u', '', $value);
            $value = preg_replace('/[\s_]+/u', '-', (string) $value);
            $value = preg_replace('/-+/u', '-', (string) $value);
            $value = trim((string) $value, '-');
        }

        if (function_exists('mb_substr')) {
            return mb_substr($value, 0, 180, 'UTF-8');
        }

        return substr($value, 0, 180);
    }
}

if (!function_exists('blog_unique_slug')) {
    function blog_unique_slug(string $table, string $slug, int $ignoreId = 0): bool
    {
        global $pdo;

        if (!in_array($table, ['blog_posts', 'blog_categories'], true) || $slug === '') {
            return false;
        }

        try {
            $sql = "SELECT COUNT(*) FROM `{$table}` WHERE slug = :slug";
            $params = [':slug' => $slug];

            if ($ignoreId > 0) {
                $sql .= ' AND id != :id';
                $params[':id'] = $ignoreId;
            }

            $statement = $pdo->prepare($sql);
            $statement->execute($params);

            return (int) $statement->fetchColumn() === 0;
        } catch (Throwable $exception) {
            return false;
        }
    }
}

if (!function_exists('blog_safe_url')) {
    function blog_safe_url(string $url, bool $image = false): string
    {
        $url = trim(html_entity_decode($url, ENT_QUOTES, 'UTF-8'));

        if ($url === '') {
            return '';
        }

        if (preg_match('#^(?:https?://|/|\./|\.\./)#i', $url)) {
            return $url;
        }

        if (!$image && preg_match('#^(?:mailto:|tel:|#)#i', $url)) {
            return $url;
        }

        return '';
    }
}

/*
|--------------------------------------------------------------------------
| Safe Inline Style Sanitizer
|--------------------------------------------------------------------------
| Summernote writes basic visual formatting as inline style attributes.
| Retain a conservative, property-whitelisted subset and remove anything
| that could load code, external resources or browser expressions.
*/
if (!function_exists('blog_sanitize_inline_style')) {
    function blog_sanitize_inline_style(string $style): string
    {
        $style = trim($style);

        if ($style === '') {
            return '';
        }

        $allowedProperties = [
            'color', 'background-color', 'text-align', 'font-weight',
            'font-style', 'text-decoration', 'font-size', 'line-height',
            'font-family', 'margin', 'margin-top', 'margin-right',
            'margin-bottom', 'margin-left', 'padding', 'padding-top',
            'padding-right', 'padding-bottom', 'padding-left', 'border',
            'border-top', 'border-right', 'border-bottom', 'border-left',
            'border-radius', 'width', 'height', 'max-width', 'float',
        ];

        $safeDeclarations = [];

        foreach (explode(';', $style) as $declaration) {
            $parts = explode(':', $declaration, 2);

            if (count($parts) !== 2) {
                continue;
            }

            $property = strtolower(trim($parts[0]));
            $value = trim((string) preg_replace('/\s*!important\s*$/i', '', $parts[1]));

            if (
                !in_array($property, $allowedProperties, true)
                || $value === ''
                || preg_match('/(?:url\s*\(|expression\s*\(|javascript\s*:|@import|-moz-binding|behavior\s*:|[<>{}])/i', $value)
                || strlen($value) > 180
            ) {
                continue;
            }

            if ($property === 'text-align' && !in_array(strtolower($value), ['left', 'right', 'center', 'justify', 'start', 'end'], true)) {
                continue;
            }

            if ($property === 'font-style' && !in_array(strtolower($value), ['normal', 'italic', 'oblique'], true)) {
                continue;
            }

            /*
             * Blog typography is intentionally fixed at a medium 500 weight.
             * Bold controls may still exist in the editor, but the final
             * public article output always normalizes to this weight.
             */
            if ($property === 'font-weight') {
                $value = '500';
            }

            /*
             * Only accept Summernote's px font sizes and clamp them to 20px.
             * Other units can scale unpredictably across devices, so they are
             * dropped and the article's normal inherited size is used instead.
             */
            if ($property === 'font-size') {
                if (!preg_match('/^(\d+(?:\.\d+)?)px$/i', $value, $sizeMatch)) {
                    continue;
                }

                $value = (string) min(20, max(8, (int) round((float) $sizeMatch[1]))) . 'px';
            }

            if ($property === 'line-height' && !preg_match('/^(?:normal|\d+(?:\.\d+)?(?:px|em|rem|%|pt)?)$/i', $value)) {
                continue;
            }

            if ($property === 'font-family' && !preg_match('/^[a-zA-Z0-9,\s\-_\'"]+$/', $value)) {
                continue;
            }

            $safeDeclarations[] = $property . ': ' . $value;
        }

        return implode('; ', $safeDeclarations);
    }
}

if (!function_exists('blog_sanitize_html')) {
    function blog_sanitize_html(string $html): string
    {
        $html = trim($html);

        if ($html === '') {
            return '';
        }

        $fallback = static function (string $value): string {
            return strip_tags(
                $value,
                '<p><br><strong><b><em><i><u><s><del><h1><h2><h3><h4><h5><h6><ul><ol><li><blockquote><a><img><figure><figcaption><table><thead><tbody><tr><th><td><hr><pre><code><span><div>'
            );
        };

        if (!class_exists('DOMDocument')) {
            return $fallback($html);
        }

        $allowedTags = [
            'p', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'del',
            'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'ul', 'ol', 'li',
            'blockquote', 'a', 'img', 'figure', 'figcaption', 'table',
            'thead', 'tbody', 'tr', 'th', 'td', 'hr', 'pre', 'code',
            'span', 'div'
        ];
        $removeWithContents = ['script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'button', 'svg', 'math'];
        $allowedGlobal = ['class', 'title', 'id'];

        $previous = libxml_use_internal_errors(true);
        $document = new DOMDocument('1.0', 'UTF-8');
        $wrapped = '<div data-blog-root="1">' . $html . '</div>';
        $loaded = $document->loadHTML(
            '<?xml encoding="UTF-8">' . $wrapped,
            (defined('LIBXML_HTML_NOIMPLIED') ? LIBXML_HTML_NOIMPLIED : 0)
            | (defined('LIBXML_HTML_NODEFDTD') ? LIBXML_HTML_NODEFDTD : 0)
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (!$loaded) {
            return $fallback($html);
        }

        $root = null;
        foreach ($document->getElementsByTagName('div') as $div) {
            if ($div->getAttribute('data-blog-root') === '1') {
                $root = $div;
                break;
            }
        }

        if (!$root instanceof DOMElement) {
            return $fallback($html);
        }

        $sanitizeNode = static function (DOMNode $node) use (&$sanitizeNode, $allowedTags, $removeWithContents, $allowedGlobal): void {
            $children = [];
            foreach ($node->childNodes as $child) {
                $children[] = $child;
            }

            foreach ($children as $child) {
                if ($child instanceof DOMElement) {
                    $tag = strtolower($child->tagName);

                    if (in_array($tag, $removeWithContents, true)) {
                        $node->removeChild($child);
                        continue;
                    }

                    if (!in_array($tag, $allowedTags, true)) {
                        while ($child->firstChild) {
                            $node->insertBefore($child->firstChild, $child);
                        }
                        $node->removeChild($child);
                        continue;
                    }

                    $attributes = [];
                    foreach ($child->attributes as $attribute) {
                        $attributes[] = $attribute;
                    }

                    foreach ($attributes as $attribute) {
                        $name = strtolower($attribute->name);
                        $value = trim($attribute->value);
                        $allowed = in_array($name, $allowedGlobal, true);

                        if ($tag === 'a' && in_array($name, ['href', 'target', 'rel'], true)) {
                            $allowed = true;
                        }

                        if ($tag === 'img' && in_array($name, ['src', 'alt', 'width', 'height', 'loading'], true)) {
                            $allowed = true;
                        }

                        if ($tag === 'th' || $tag === 'td') {
                            $allowed = $allowed || in_array($name, ['colspan', 'rowspan'], true);
                        }

                        if ($name === 'style') {
                            $safeStyle = blog_sanitize_inline_style($value);

                            if ($safeStyle === '') {
                                $child->removeAttributeNode($attribute);
                            } else {
                                $child->setAttribute('style', $safeStyle);
                            }

                            continue;
                        }

                        if (!$allowed || strpos($name, 'on') === 0) {
                            $child->removeAttributeNode($attribute);
                            continue;
                        }

                        if ($tag === 'a' && $name === 'href') {
                            $safe = blog_safe_url($value, false);
                            if ($safe === '') {
                                $child->removeAttributeNode($attribute);
                            } else {
                                $child->setAttribute('href', $safe);
                            }
                        }

                        if ($tag === 'img' && $name === 'src') {
                            $safe = blog_safe_url($value, true);
                            if ($safe === '') {
                                $child->removeAttributeNode($attribute);
                            } else {
                                $child->setAttribute('src', $safe);
                            }
                        }
                    }

                    if ($tag === 'a' && $child->getAttribute('target') === '_blank') {
                        $child->setAttribute('rel', 'noopener noreferrer');
                    }

                    if ($tag === 'img') {
                        if ($child->getAttribute('src') === '') {
                            $node->removeChild($child);
                            continue;
                        }

                        $child->setAttribute('loading', 'lazy');
                    }
                }

                $sanitizeNode($child);
            }
        };

        $sanitizeNode($root);

        $output = '';
        foreach ($root->childNodes as $child) {
            $output .= $document->saveHTML($child);
        }

        return trim($output);
    }
}

/*
|--------------------------------------------------------------------------
| Generated Builder CSS Sanitizer
|--------------------------------------------------------------------------
| GrapesJS exports component CSS separately from its HTML. We retain useful
| layout rules while removing constructs that can load code or external CSS.
*/
if (!function_exists('blog_sanitize_css')) {
    function blog_sanitize_css(string $css): string
    {
        $css = trim($css);

        if ($css === '') {
            return '';
        }

        $css = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $css);
        $css = preg_replace('#</?style[^>]*>#i', '', (string) $css);
        $css = preg_replace('/@import\b[^;{}]*(?:;|$)/i', '', (string) $css);
        $css = preg_replace('/(?:expression\s*\(|javascript\s*:|-moz-binding\s*:|behavior\s*:)/i', '', (string) $css);
        $css = preg_replace('/url\s*\(\s*[\'"]?\s*(?:javascript|data\s*:\s*text\/html)[^)]*\)/i', '', (string) $css);
        $css = trim((string) $css);

        // Prevent a single post from creating unexpectedly large CSS payloads.
        if (strlen($css) > 100000) {
            $css = substr($css, 0, 100000);
        }

        return $css;
    }
}

if (!function_exists('blog_excerpt_from_html')) {
    function blog_excerpt_from_html(string $html, int $length = 180): string
    {
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES, 'UTF-8');
        $text = blog_clean_text($text);

        if ($text === '') {
            return '';
        }

        if (function_exists('mb_strimwidth')) {
            return mb_strimwidth($text, 0, $length, '…', 'UTF-8');
        }

        return strlen($text) > $length ? substr($text, 0, $length - 1) . '…' : $text;
    }
}

if (!function_exists('blog_image_url')) {
    function blog_image_url(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        if (preg_match('#^https?://#i', $value)) {
            return $value;
        }

        if (function_exists('site_url')) {
            return site_url(ltrim($value, './'));
        }

        return $value;
    }
}

if (!function_exists('blog_url')) {
    function blog_url(array $post, ?string $lang = null): string
    {
        $slug = rawurlencode(trim((string) ($post['slug'] ?? '')));

        return function_exists('front_url')
            ? front_url('blog/' . $slug, blog_lang($lang))
            : site_url('blog/' . $slug);
    }
}

if (!function_exists('blog_category_url')) {
    function blog_category_url(array $category, ?string $lang = null): string
    {
        $slug = rawurlencode(trim((string) ($category['slug'] ?? '')));

        return function_exists('front_url')
            ? front_url('blog/category/' . $slug, blog_lang($lang))
            : site_url('blog/category/' . $slug);
    }
}

if (!function_exists('blog_list_url')) {
    function blog_list_url(?string $lang = null): string
    {
        return function_exists('front_url')
            ? front_url('blog', blog_lang($lang))
            : site_url('blog');
    }
}

if (!function_exists('blog_category_by_slug')) {
    function blog_category_by_slug(string $slug, bool $onlyActive = true): ?array
    {
        global $pdo;
        blog_ensure_tables();

        try {
            $sql = 'SELECT * FROM blog_categories WHERE slug = :slug';
            if ($onlyActive) {
                $sql .= " AND status = 'active'";
            }
            $sql .= ' LIMIT 1';

            $statement = $pdo->prepare($sql);
            $statement->execute([':slug' => $slug]);
            $row = $statement->fetch(PDO::FETCH_ASSOC);

            return is_array($row) ? $row : null;
        } catch (Throwable $exception) {
            return null;
        }
    }
}

if (!function_exists('blog_categories')) {
    function blog_categories(bool $onlyActive = true): array
    {
        global $pdo;
        blog_ensure_tables();

        try {
            $sql = 'SELECT * FROM blog_categories';
            if ($onlyActive) {
                $sql .= " WHERE status = 'active'";
            }
            $sql .= ' ORDER BY name_en ASC, id DESC';

            return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $exception) {
            return [];
        }
    }
}

/*
|--------------------------------------------------------------------------
| Blog Publication Time
|--------------------------------------------------------------------------
| Blog dates are stored as Bangladesh local DATETIME values. Do not use
| MySQL NOW() for public visibility because the database server can run in a
| different timezone (commonly UTC), causing already-published posts to show
| as "Post not found".
*/
if (!function_exists('blog_now')) {
    function blog_now(): string
    {
        static $timezone = null;

        if ($timezone === null) {
            $timezoneName = defined('APP_TIMEZONE')
                ? trim((string) APP_TIMEZONE)
                : 'Asia/Dhaka';

            try {
                $timezone = new DateTimeZone($timezoneName !== '' ? $timezoneName : 'Asia/Dhaka');
            } catch (Throwable $exception) {
                $timezone = new DateTimeZone('Asia/Dhaka');
            }
        }

        return (new DateTimeImmutable('now', $timezone))->format('Y-m-d H:i:s');
    }
}

if (!function_exists('blog_public_condition')) {
    function blog_public_condition(string $alias = 'p'): string
    {
        $alias = preg_replace('/[^a-zA-Z0-9_]/', '', $alias) ?: 'p';
        $now = blog_now();

        return "{$alias}.status = 'published'
            AND ({$alias}.published_at IS NULL OR {$alias}.published_at <= '{$now}')";
    }
}

if (!function_exists('blog_list_posts')) {
    function blog_list_posts(array $options = []): array
    {
        global $pdo;
        blog_ensure_tables();

        $page = max(1, (int) ($options['page'] ?? 1));
        $perPage = max(1, min(30, (int) ($options['per_page'] ?? 9)));
        $categoryId = max(0, (int) ($options['category_id'] ?? 0));
        $search = blog_clean_text($options['search'] ?? '', 120);
        $onlyPublic = !array_key_exists('public', $options) || (bool) $options['public'];
        $status = trim((string) ($options['status'] ?? ''));
        $featuredOnly = !empty($options['featured']);
        $excludeId = max(0, (int) ($options['exclude_id'] ?? 0));

        $where = [];
        $params = [];

        if ($onlyPublic) {
            $where[] = blog_public_condition('p');
        } elseif (in_array($status, ['draft', 'published', 'scheduled'], true)) {
            $where[] = 'p.status = :status';
            $params[':status'] = $status;
        }

        if ($categoryId > 0) {
            $where[] = 'p.category_id = :category_id';
            $params[':category_id'] = $categoryId;
        }

        if ($search !== '') {
            $where[] = '(p.title_en LIKE :search OR p.title_bn LIKE :search OR p.excerpt_en LIKE :search OR p.excerpt_bn LIKE :search)';
            $params[':search'] = '%' . $search . '%';
        }

        if ($featuredOnly) {
            $where[] = 'p.is_featured = 1';
        }

        if ($excludeId > 0) {
            $where[] = 'p.id != :exclude_id';
            $params[':exclude_id'] = $excludeId;
        }

        $whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

        try {
            $countStatement = $pdo->prepare('SELECT COUNT(*) FROM blog_posts p' . $whereSql);
            $countStatement->execute($params);
            $total = (int) $countStatement->fetchColumn();

            $offset = ($page - 1) * $perPage;
            $statement = $pdo->prepare(
                'SELECT p.*, c.slug AS category_slug, c.name_en AS category_name_en, c.name_bn AS category_name_bn
                 FROM blog_posts p
                 LEFT JOIN blog_categories c ON c.id = p.category_id'
                . $whereSql
                . ' ORDER BY
                      CASE WHEN p.status = \'published\' THEN 0 ELSE 1 END,
                      COALESCE(p.published_at, p.created_at) DESC,
                      p.id DESC
                   LIMIT :limit OFFSET :offset'
            );

            foreach ($params as $key => $value) {
                $statement->bindValue($key, $value);
            }
            $statement->bindValue(':limit', $perPage, PDO::PARAM_INT);
            $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
            $statement->execute();

            return [
                'items' => $statement->fetchAll(PDO::FETCH_ASSOC) ?: [],
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => max(1, (int) ceil($total / $perPage)),
            ];
        } catch (Throwable $exception) {
            error_log('Blog list query failed: ' . $exception->getMessage());

            return ['items' => [], 'total' => 0, 'page' => $page, 'per_page' => $perPage, 'total_pages' => 1];
        }
    }
}

if (!function_exists('blog_find_post_by_slug')) {
    function blog_find_post_by_slug(string $slug, bool $publicOnly = true): ?array
    {
        global $pdo;
        blog_ensure_tables();

        try {
            $sql = 'SELECT p.*, c.slug AS category_slug, c.name_en AS category_name_en, c.name_bn AS category_name_bn
                    FROM blog_posts p
                    LEFT JOIN blog_categories c ON c.id = p.category_id
                    WHERE p.slug = :slug';
            if ($publicOnly) {
                $sql .= ' AND ' . blog_public_condition('p');
            }
            $sql .= ' LIMIT 1';

            $statement = $pdo->prepare($sql);
            $statement->execute([':slug' => $slug]);
            $row = $statement->fetch(PDO::FETCH_ASSOC);

            return is_array($row) ? $row : null;
        } catch (Throwable $exception) {
            return null;
        }
    }
}

if (!function_exists('blog_increment_views')) {
    function blog_increment_views(int $postId): void
    {
        global $pdo;

        if ($postId <= 0) {
            return;
        }

        try {
            $statement = $pdo->prepare('UPDATE blog_posts SET views = views + 1 WHERE id = :id');
            $statement->execute([':id' => $postId]);
        } catch (Throwable $exception) {
            // A view count must never stop a public page from rendering.
        }
    }
}

if (!function_exists('blog_related_posts')) {
    function blog_related_posts(array $post, int $limit = 3): array
    {
        $options = [
            'public' => true,
            'per_page' => max(1, min(6, $limit)),
            'exclude_id' => (int) ($post['id'] ?? 0),
        ];

        if ((int) ($post['category_id'] ?? 0) > 0) {
            $options['category_id'] = (int) $post['category_id'];
        }

        return blog_list_posts($options)['items'] ?? [];
    }
}

if (!function_exists('blog_csrf_token')) {
    function blog_csrf_token(): string
    {
        if (empty($_SESSION['blog_csrf_token'])) {
            try {
                $_SESSION['blog_csrf_token'] = bin2hex(random_bytes(32));
            } catch (Throwable $exception) {
                $_SESSION['blog_csrf_token'] = sha1(uniqid('blog', true));
            }
        }

        return (string) $_SESSION['blog_csrf_token'];
    }
}

if (!function_exists('blog_verify_csrf')) {
    function blog_verify_csrf($token): bool
    {
        return is_string($token)
            && hash_equals(blog_csrf_token(), $token);
    }
}

if (!function_exists('blog_parse_datetime')) {
    function blog_parse_datetime(string $value): ?string
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        $timestamp = strtotime($value);

        return $timestamp === false ? null : date('Y-m-d H:i:s', $timestamp);
    }
}

if (!function_exists('blog_save_category')) {
    function blog_save_category(array $input, int $id = 0): array
    {
        global $pdo;
        blog_ensure_tables();

        $data = [
            'slug' => blog_normalize_slug((string) ($input['slug'] ?? '')),
            'name_en' => blog_clean_text($input['name_en'] ?? '', 190),
            'name_bn' => blog_clean_text($input['name_bn'] ?? '', 190),
            'description_en' => blog_clean_text($input['description_en'] ?? '', 10000),
            'description_bn' => blog_clean_text($input['description_bn'] ?? '', 10000),
            'meta_title_en' => blog_clean_text($input['meta_title_en'] ?? '', 255),
            'meta_title_bn' => blog_clean_text($input['meta_title_bn'] ?? '', 255),
            'meta_description_en' => blog_clean_text($input['meta_description_en'] ?? '', 500),
            'meta_description_bn' => blog_clean_text($input['meta_description_bn'] ?? '', 500),
            'status' => (string) ($input['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active',
        ];

        $errors = [];
        if ($data['name_en'] === '') {
            $errors[] = 'English category name is required.';
        }
        if ($data['slug'] === '') {
            $errors[] = 'Category slug is required.';
        } elseif (!blog_unique_slug('blog_categories', $data['slug'], $id)) {
            $errors[] = 'This category slug is already in use.';
        }

        if ($errors) {
            return ['ok' => false, 'errors' => $errors, 'data' => $data, 'id' => $id];
        }

        try {
            if ($id > 0) {
                $statement = $pdo->prepare(
                    'UPDATE blog_categories SET
                        slug = :slug, name_en = :name_en, name_bn = :name_bn,
                        description_en = :description_en, description_bn = :description_bn,
                        meta_title_en = :meta_title_en, meta_title_bn = :meta_title_bn,
                        meta_description_en = :meta_description_en, meta_description_bn = :meta_description_bn,
                        status = :status, updated_at = NOW()
                     WHERE id = :id'
                );
                $data[':id'] = $id;
                $statement->execute([
                    ':slug' => $data['slug'], ':name_en' => $data['name_en'], ':name_bn' => $data['name_bn'],
                    ':description_en' => $data['description_en'] ?: null, ':description_bn' => $data['description_bn'] ?: null,
                    ':meta_title_en' => $data['meta_title_en'] ?: null, ':meta_title_bn' => $data['meta_title_bn'] ?: null,
                    ':meta_description_en' => $data['meta_description_en'] ?: null, ':meta_description_bn' => $data['meta_description_bn'] ?: null,
                    ':status' => $data['status'], ':id' => $id,
                ]);
            } else {
                $statement = $pdo->prepare(
                    'INSERT INTO blog_categories
                    (slug, name_en, name_bn, description_en, description_bn, meta_title_en, meta_title_bn, meta_description_en, meta_description_bn, status, created_at, updated_at)
                    VALUES
                    (:slug, :name_en, :name_bn, :description_en, :description_bn, :meta_title_en, :meta_title_bn, :meta_description_en, :meta_description_bn, :status, NOW(), NOW())'
                );
                $statement->execute([
                    ':slug' => $data['slug'], ':name_en' => $data['name_en'], ':name_bn' => $data['name_bn'],
                    ':description_en' => $data['description_en'] ?: null, ':description_bn' => $data['description_bn'] ?: null,
                    ':meta_title_en' => $data['meta_title_en'] ?: null, ':meta_title_bn' => $data['meta_title_bn'] ?: null,
                    ':meta_description_en' => $data['meta_description_en'] ?: null, ':meta_description_bn' => $data['meta_description_bn'] ?: null,
                    ':status' => $data['status'],
                ]);
                $id = (int) $pdo->lastInsertId();
            }

            return ['ok' => true, 'errors' => [], 'data' => $data, 'id' => $id];
        } catch (Throwable $exception) {
            error_log('Blog category save failed: ' . $exception->getMessage());
            return ['ok' => false, 'errors' => ['Category could not be saved.'], 'data' => $data, 'id' => $id];
        }
    }
}

if (!function_exists('blog_save_post')) {
    function blog_save_post(array $input, int $id = 0): array
    {
        global $pdo;
        blog_ensure_tables();

        $status = trim((string) ($input['status'] ?? 'draft'));
        $status = in_array($status, ['draft', 'published', 'scheduled'], true) ? $status : 'draft';

        $data = [
            'category_id' => max(0, (int) ($input['category_id'] ?? 0)),
            'slug' => blog_normalize_slug((string) ($input['slug'] ?? '')),
            'title_en' => blog_clean_text($input['title_en'] ?? '', 255),
            'title_bn' => blog_clean_text($input['title_bn'] ?? '', 255),
            'excerpt_en' => blog_clean_text($input['excerpt_en'] ?? '', 1000),
            'excerpt_bn' => blog_clean_text($input['excerpt_bn'] ?? '', 1000),
            'content_en' => blog_sanitize_html((string) ($input['content_en'] ?? '')),
            'content_bn' => blog_sanitize_html((string) ($input['content_bn'] ?? '')),
            'content_css_en' => blog_sanitize_css((string) ($input['content_en_css'] ?? '')),
            'content_css_bn' => blog_sanitize_css((string) ($input['content_bn_css'] ?? '')),
            'featured_image' => blog_safe_url((string) ($input['featured_image'] ?? ''), true),
            'image_alt_en' => blog_clean_text($input['image_alt_en'] ?? '', 255),
            'image_alt_bn' => blog_clean_text($input['image_alt_bn'] ?? '', 255),
            'author_name' => blog_clean_text($input['author_name'] ?? '', 120),
            'status' => $status,
            'is_featured' => !empty($input['is_featured']) ? 1 : 0,
            'meta_title_en' => blog_clean_text($input['meta_title_en'] ?? '', 255),
            'meta_title_bn' => blog_clean_text($input['meta_title_bn'] ?? '', 255),
            'meta_description_en' => blog_clean_text($input['meta_description_en'] ?? '', 500),
            'meta_description_bn' => blog_clean_text($input['meta_description_bn'] ?? '', 500),
            'meta_keywords_en' => blog_clean_text($input['meta_keywords_en'] ?? '', 500),
            'meta_keywords_bn' => blog_clean_text($input['meta_keywords_bn'] ?? '', 500),
            'published_at' => blog_parse_datetime((string) ($input['published_at'] ?? '')),
        ];

        $errors = [];

        if ($data['title_en'] === '') {
            $errors[] = 'English title is required.';
        }
        if ($data['slug'] === '') {
            $errors[] = 'Post slug is required.';
        } elseif (!blog_unique_slug('blog_posts', $data['slug'], $id)) {
            $errors[] = 'This post slug is already in use.';
        }
        if ($data['category_id'] > 0) {
            try {
                $statement = $pdo->prepare('SELECT COUNT(*) FROM blog_categories WHERE id = :id');
                $statement->execute([':id' => $data['category_id']]);
                if ((int) $statement->fetchColumn() === 0) {
                    $errors[] = 'The selected category does not exist.';
                }
            } catch (Throwable $exception) {
                $errors[] = 'Category validation failed.';
            }
        }
        if ($data['status'] === 'scheduled' && $data['published_at'] === null) {
            $errors[] = 'Choose a future publish date for a scheduled post.';
        }
        $blogCurrentTimestamp = strtotime(blog_now());

        if (
            $data['status'] === 'scheduled'
            && $data['published_at'] !== null
            && strtotime($data['published_at']) <= $blogCurrentTimestamp
        ) {
            $errors[] = 'A scheduled publish date must be in the future.';
        }

        if ($data['status'] === 'published' && $data['published_at'] === null) {
            $data['published_at'] = blog_now();
        }

        if ($errors) {
            return ['ok' => false, 'errors' => $errors, 'data' => $data, 'id' => $id];
        }

        try {
            $sqlColumns = [
                'category_id', 'slug', 'title_en', 'title_bn', 'excerpt_en', 'excerpt_bn',
                'content_en', 'content_bn', 'featured_image', 'image_alt_en', 'image_alt_bn',
                'author_name', 'status', 'is_featured', 'meta_title_en', 'meta_title_bn',
                'meta_description_en', 'meta_description_bn', 'meta_keywords_en', 'meta_keywords_bn', 'published_at'
            ];

            $params = [];
            foreach ($sqlColumns as $column) {
                $params[':' . $column] = $data[$column] === '' ? null : $data[$column];
            }
            $params[':is_featured'] = $data['is_featured'];

            if ($id > 0) {
                $assignments = [];
                foreach ($sqlColumns as $column) {
                    $assignments[] = "{$column} = :{$column}";
                }
                $statement = $pdo->prepare(
                    'UPDATE blog_posts SET ' . implode(', ', $assignments) . ', updated_at = NOW() WHERE id = :id'
                );
                $params[':id'] = $id;
                $statement->execute($params);
            } else {
                $columns = implode(', ', $sqlColumns);
                $placeholders = implode(', ', array_map(static function (string $column): string { return ':' . $column; }, $sqlColumns));
                $statement = $pdo->prepare(
                    'INSERT INTO blog_posts (' . $columns . ', created_at, updated_at) VALUES (' . $placeholders . ', NOW(), NOW())'
                );
                $statement->execute($params);
                $id = (int) $pdo->lastInsertId();
            }

            return ['ok' => true, 'errors' => [], 'data' => $data, 'id' => $id];
        } catch (Throwable $exception) {
            error_log('Blog post save failed: ' . $exception->getMessage());
            return ['ok' => false, 'errors' => ['Post could not be saved.'], 'data' => $data, 'id' => $id];
        }
    }
}

if (!function_exists('blog_delete_post')) {
    function blog_delete_post(int $id): bool
    {
        global $pdo;

        if ($id <= 0) {
            return false;
        }

        try {
            $statement = $pdo->prepare('DELETE FROM blog_posts WHERE id = :id');
            return $statement->execute([':id' => $id]);
        } catch (Throwable $exception) {
            return false;
        }
    }
}
