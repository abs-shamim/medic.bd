<?php
require_once __DIR__ . '/includes/header.php';

$site_settings_version = 'premium-search-ai-crawler-control-live-preview-20260626';

if (!defined('ADMIN_SITE_MAX_UPLOAD_SIZE')) {
    define('ADMIN_SITE_MAX_UPLOAD_SIZE', 5 * 1024 * 1024); // 5 MB
}

if (!defined('ADMIN_SITE_WEBP_QUALITY')) {
    define('ADMIN_SITE_WEBP_QUALITY', 82);
}

/*
|--------------------------------------------------------------------------
| Site Settings Page
|--------------------------------------------------------------------------
| Upload path: admin/site-settings.php
| This page creates the site_settings table if missing, saves settings,
| handles media uploads, and displays all setting groups in a left menu.
*/

if (!function_exists('admin_settings_slug')) {
    function admin_settings_slug(string $text): string
    {
        $text = strtolower(trim($text));
        $text = preg_replace('/[^a-z0-9]+/i', '-', $text);
        return trim((string)$text, '-');
    }
}



if (!function_exists('admin_site_image_slug')) {
    function admin_site_image_slug(string $text): string
    {
        $text = strtolower(trim($text));
        $text = preg_replace('/[^a-z0-9]+/i', '-', $text);
        $text = trim((string)$text, '-');

        return $text !== '' ? $text : 'site-image';
    }
}

if (!function_exists('admin_site_assets_images_dir')) {
    function admin_site_assets_images_dir(): string
    {
        return __DIR__ . '/../assets/images';
    }
}

if (!function_exists('admin_site_assets_images_url')) {
    function admin_site_assets_images_url(): string
    {
        return '../assets/images';
    }
}


if (!function_exists('admin_site_cache_bust_url')) {
    function admin_site_cache_bust_url(string $url): string
    {
        $url = trim($url);

        if ($url === '') {
            return '';
        }

        $path = admin_site_uploaded_url_to_path($url);
        $version = $path !== '' && is_file($path) ? (string)filemtime($path) : (string)time();

        return $url . (strpos($url, '?') === false ? '?v=' : '&v=') . rawurlencode($version);
    }
}

if (!function_exists('admin_site_uploaded_url_to_path')) {
    function admin_site_uploaded_url_to_path(string $url): string
    {
        $url = trim($url);

        if ($url === '') {
            return '';
        }

        $path_part = (string)parse_url($url, PHP_URL_PATH);
        $file_name = basename($path_part);

        if ($file_name === '' || $file_name === '.' || $file_name === '..') {
            return '';
        }

        $allowed_extensions = ['webp', 'ico'];
        $extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

        if (!in_array($extension, $allowed_extensions, true)) {
            return '';
        }

        // Only allow deleting files from /assets/images created by this settings page.
        if (strpos($url, '../assets/images/') !== 0 && strpos($path_part, '/assets/images/') === false) {
            return '';
        }

        $path = admin_site_assets_images_dir() . '/' . $file_name;
        $images_dir = realpath(admin_site_assets_images_dir());
        $real_path = realpath($path);

        if (!$images_dir || !$real_path || strpos($real_path, $images_dir . DIRECTORY_SEPARATOR) !== 0) {
            return '';
        }

        return $real_path;
    }
}

if (!function_exists('admin_site_is_generated_upload')) {
    function admin_site_is_generated_upload(string $url): bool
    {
        $path_part = (string)parse_url($url, PHP_URL_PATH);
        $file_name = basename($path_part);

        return (bool)preg_match('/-[0-9]{12,}(?:-[0-9]+)?\.(webp|ico)$/i', $file_name);
    }
}

if (!function_exists('admin_site_delete_old_uploaded_file')) {
    function admin_site_delete_old_uploaded_file(string $old_url, string $new_url): void
    {
        $old_url = trim($old_url);
        $new_url = trim($new_url);

        if ($old_url === '' || $new_url === '' || $old_url === $new_url) {
            return;
        }

        if (!admin_site_is_generated_upload($old_url)) {
            return;
        }

        $old_path = admin_site_uploaded_url_to_path($old_url);

        if ($old_path !== '' && is_file($old_path)) {
            @unlink($old_path);
        }
    }
}

if (!function_exists('admin_site_normalize_hex_color')) {
    function admin_site_normalize_hex_color(string $color, string $fallback = '#0f766e'): string
    {
        $color = trim($color);
        $fallback = trim($fallback) !== '' ? trim($fallback) : '#0f766e';

        if ($color !== '' && $color[0] !== '#') {
            $color = '#' . $color;
        }

        if (preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) {
            return strtolower($color);
        }

        return preg_match('/^#[0-9A-Fa-f]{6}$/', $fallback) ? strtolower($fallback) : '#0f766e';
    }
}

if (!function_exists('admin_site_ensure_assets_folders')) {
    function admin_site_ensure_assets_folders(): void
    {
        $folders = [
            __DIR__ . '/../assets',
            __DIR__ . '/../assets/images',
            __DIR__ . '/../assets/images/site',
            __DIR__ . '/../assets/images/defaults',
        ];

        foreach ($folders as $folder) {
            if (!is_dir($folder)) {
                @mkdir($folder, 0775, true);
            }
        }
    }
}

if (!function_exists('admin_site_upload_target')) {
    function admin_site_upload_target(string $field_name, string $extension = 'webp'): array
    {
        admin_site_ensure_assets_folders();

        $extension = strtolower(trim($extension, '.'));
        $extension = $extension !== '' ? $extension : 'webp';

        $fixed_names = [
            'site_logo' => 'site-logo',
            'site_dark_logo' => 'site-dark-logo',
            'site_favicon' => 'favicon',
            'site_apple_touch_icon' => 'apple-touch-icon',
            'default_og_image' => 'default-og-image',
            'home_hero_image' => 'home-hero-image',
            'default_doctor_image' => 'default-doctor',
            'default_doctor_male_image' => 'default-doctor-male',
            'default_doctor_female_image' => 'default-doctor-female',
            'default_hospital_image' => 'default-hospital',
            'default_hospital_cover_image' => 'default-hospital-cover',
        ];

        $base_name = $fixed_names[$field_name] ?? admin_site_image_slug($field_name);
        $base_name = admin_site_image_slug($base_name);
        $time_part = date('ymdHis');

        $file_name = $base_name . '-' . $time_part . '.' . $extension;
        $path = admin_site_assets_images_dir() . '/' . $file_name;
        $counter = 2;

        while (file_exists($path)) {
            $file_name = $base_name . '-' . $time_part . '-' . $counter . '.' . $extension;
            $path = admin_site_assets_images_dir() . '/' . $file_name;
            $counter++;
        }

        return [
            'path' => $path,
            'url' => admin_site_assets_images_url() . '/' . $file_name,
            'file_name' => $file_name,
            'extension' => $extension,
        ];
    }
}

if (!function_exists('admin_site_get_image_mime')) {
    function admin_site_get_image_mime(string $tmp_file): string
    {
        if (function_exists('finfo_open')) {
            $finfo = @finfo_open(FILEINFO_MIME_TYPE);

            if ($finfo) {
                $mime = @finfo_file($finfo, $tmp_file);
                @finfo_close($finfo);

                if (is_string($mime) && $mime !== '') {
                    return strtolower($mime);
                }
            }
        }

        $info = @getimagesize($tmp_file);

        if ($info && !empty($info['mime'])) {
            return strtolower((string)$info['mime']);
        }

        return '';
    }
}

if (!function_exists('admin_site_create_image_resource')) {
    function admin_site_create_image_resource(string $file, string $mime)
    {
        switch ($mime) {
            case 'image/jpeg':
            case 'image/jpg':
                return function_exists('imagecreatefromjpeg') ? @imagecreatefromjpeg($file) : false;

            case 'image/png':
                return function_exists('imagecreatefrompng') ? @imagecreatefrompng($file) : false;

            case 'image/webp':
                return function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($file) : false;

            case 'image/gif':
                return function_exists('imagecreatefromgif') ? @imagecreatefromgif($file) : false;

            case 'image/bmp':
            case 'image/x-ms-bmp':
                return function_exists('imagecreatefrombmp') ? @imagecreatefrombmp($file) : false;

            case 'image/vnd.wap.wbmp':
                return function_exists('imagecreatefromwbmp') ? @imagecreatefromwbmp($file) : false;

            case 'image/avif':
                return function_exists('imagecreatefromavif') ? @imagecreatefromavif($file) : false;
        }

        if (function_exists('imagecreatefromstring')) {
            $raw = @file_get_contents($file);

            if ($raw !== false) {
                return @imagecreatefromstring($raw);
            }
        }

        return false;
    }
}

if (!function_exists('admin_site_save_webp_with_gd')) {
    function admin_site_save_webp_with_gd(
        string $tmp_file,
        string $target_path,
        string $mime,
        int $max_width = 1600,
        int $max_height = 1600,
        int $quality = 82
    ): bool {
        if (!function_exists('imagewebp') || !function_exists('imagecreatetruecolor')) {
            return false;
        }

        $source = admin_site_create_image_resource($tmp_file, $mime);

        if (!$source) {
            return false;
        }

        $source_width = imagesx($source);
        $source_height = imagesy($source);

        if ($source_width <= 0 || $source_height <= 0) {
            imagedestroy($source);
            return false;
        }

        $ratio = min($max_width / $source_width, $max_height / $source_height, 1);
        $target_width = max(1, (int)round($source_width * $ratio));
        $target_height = max(1, (int)round($source_height * $ratio));

        $canvas = imagecreatetruecolor($target_width, $target_height);

        if (!$canvas) {
            imagedestroy($source);
            return false;
        }

        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
        imagefilledrectangle($canvas, 0, 0, $target_width, $target_height, $transparent);

        imagecopyresampled(
            $canvas,
            $source,
            0,
            0,
            0,
            0,
            $target_width,
            $target_height,
            $source_width,
            $source_height
        );

        $target_dir = dirname($target_path);

        if (!is_dir($target_dir)) {
            @mkdir($target_dir, 0775, true);
        }

        $saved = imagewebp($canvas, $target_path, max(1, min(100, $quality)));

        imagedestroy($canvas);
        imagedestroy($source);

        return (bool)$saved;
    }
}

if (!function_exists('admin_site_save_webp_with_imagick')) {
    function admin_site_save_webp_with_imagick(
        string $tmp_file,
        string $target_path,
        int $max_width = 1600,
        int $max_height = 1600,
        int $quality = 82
    ): bool {
        if (!class_exists('Imagick')) {
            return false;
        }

        try {
            $image = new Imagick();
            $image->readImage($tmp_file);

            if ($image->getNumberImages() > 1) {
                $image = $image->coalesceImages();
                $image->setIteratorIndex(0);
            }

            $image->setImageOrientation(Imagick::ORIENTATION_TOPLEFT);
            $image->thumbnailImage($max_width, $max_height, true, true);
            $image->setImageFormat('webp');
            $image->setImageCompressionQuality(max(1, min(100, $quality)));
            $image->stripImage();

            $target_dir = dirname($target_path);

            if (!is_dir($target_dir)) {
                @mkdir($target_dir, 0775, true);
            }

            $saved = $image->writeImage($target_path);
            $image->clear();
            $image->destroy();

            return (bool)$saved;
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('admin_site_upload_file')) {
    function admin_site_upload_file(string $field_name): string
    {
        if (empty($_FILES[$field_name]['name']) || empty($_FILES[$field_name]['tmp_name'])) {
            return '';
        }

        if ((int)($_FILES[$field_name]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return '';
        }

        if (!is_uploaded_file($_FILES[$field_name]['tmp_name'])) {
            return '';
        }

        if ((int)($_FILES[$field_name]['size'] ?? 0) > ADMIN_SITE_MAX_UPLOAD_SIZE) {
            return '';
        }

        $original_name = (string)$_FILES[$field_name]['name'];
        $extension = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
        $tmp_file = $_FILES[$field_name]['tmp_name'];

        $allowed_extensions = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp', 'wbmp', 'avif', 'ico'];

        if (!in_array($extension, $allowed_extensions, true)) {
            return '';
        }

        admin_site_ensure_assets_folders();

        if ($field_name === 'site_favicon' && $extension === 'ico') {
            $target = admin_site_upload_target($field_name, 'ico');

            if (@move_uploaded_file($tmp_file, $target['path'])) {
                return $target['url'];
            }

            return '';
        }

        $mime = admin_site_get_image_mime($tmp_file);
        $allowed_mimes = [
            'image/jpeg',
            'image/jpg',
            'image/png',
            'image/webp',
            'image/gif',
            'image/bmp',
            'image/x-ms-bmp',
            'image/vnd.wap.wbmp',
            'image/avif',
        ];

        if (!in_array($mime, $allowed_mimes, true)) {
            return '';
        }

        $target = admin_site_upload_target($field_name, 'webp');
        $max_width = $field_name === 'default_og_image' ? 1920 : 1600;
        $max_height = $field_name === 'default_og_image' ? 1080 : 1600;

        $saved = admin_site_save_webp_with_gd($tmp_file, $target['path'], $mime, $max_width, $max_height, ADMIN_SITE_WEBP_QUALITY);

        if (!$saved) {
            $saved = admin_site_save_webp_with_imagick($tmp_file, $target['path'], $max_width, $max_height, ADMIN_SITE_WEBP_QUALITY);
        }

        if ($saved) {
            @chmod($target['path'], 0664);
            return $target['url'];
        }

        return '';
    }
}

if (!function_exists('admin_site_settings_table')) {
    function admin_site_settings_table(): void
    {
        global $pdo;

        try {
            $pdo->exec("\n                CREATE TABLE IF NOT EXISTS `site_settings` (\n                    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,\n                    `setting_key` VARCHAR(150) NOT NULL,\n                    `setting_value` LONGTEXT NULL,\n                    `created_at` DATETIME DEFAULT NULL,\n                    `updated_at` DATETIME DEFAULT NULL,\n                    PRIMARY KEY (`id`),\n                    UNIQUE KEY `site_settings_setting_key_unique` (`setting_key`)\n                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci\n            ");
        } catch (Throwable $e) {
            // The page will still load and show empty values if creation fails.
        }
    }
}

admin_site_settings_table();

if (!function_exists('admin_get_all_site_settings')) {
    function admin_get_all_site_settings(): array
    {
        global $pdo;

        $settings = [];

        try {
            $stmt = $pdo->query("SELECT `setting_key`, `setting_value` FROM `site_settings`");
            $rows = $stmt->fetchAll();

            foreach ($rows as $row) {
                $settings[(string)$row['setting_key']] = (string)$row['setting_value'];
            }
        } catch (Throwable $e) {
            return [];
        }

        return $settings;
    }
}

if (!function_exists('admin_update_site_setting')) {
    function admin_update_site_setting(string $key, string $value): bool
    {
        global $pdo;

        try {
            $stmt = $pdo->prepare("\n                INSERT INTO `site_settings` (`setting_key`, `setting_value`, `created_at`, `updated_at`)\n                VALUES (:setting_key, :setting_value, NOW(), NOW())\n                ON DUPLICATE KEY UPDATE\n                    `setting_value` = VALUES(`setting_value`),\n                    `updated_at` = NOW()\n            ");

            return $stmt->execute([
                ':setting_key' => $key,
                ':setting_value' => $value,
            ]);
        } catch (Throwable $e) {
            return false;
        }
    }
}



if (!function_exists('admin_settings_value')) {
    function admin_settings_value(array $settings, string $key, string $default = ''): string
    {
        return (string)($settings[$key] ?? $default);
    }
}

if (!function_exists('admin_render_settings_field')) {
    function admin_render_settings_field(array $settings, array $field): void
    {
        $key = (string)$field['key'];
        $label = (string)$field['label'];
        $type = (string)($field['type'] ?? 'text');
        $placeholder = (string)($field['placeholder'] ?? '');
        $help = (string)($field['help'] ?? '');
        $default = (string)($field['default'] ?? '');
        $value = admin_settings_value($settings, $key, $default);
        $class = !empty($field['full']) ? 'settings-field settings-field-full' : 'settings-field';

        if ($type === 'crawler_mode') {
            $class .= ' settings-crawler-field';
        }

        echo '<div class="' . e($class) . '">';
        echo '<label for="' . e($key) . '">' . e($label) . '</label>';

        if ($type === 'textarea') {
            echo '<textarea id="' . e($key) . '" name="' . e($key) . '" placeholder="' . e($placeholder) . '">' . e($value) . '</textarea>';
        } elseif ($type === 'code') {
            echo '<textarea class="settings-code-textarea" id="' . e($key) . '" name="' . e($key) . '" placeholder="' . e($placeholder) . '">' . e($value) . '</textarea>';
        } elseif ($type === 'select') {
            echo '<select id="' . e($key) . '" name="' . e($key) . '">';

            foreach (($field['options'] ?? []) as $option_value => $option_label) {
                $selected = ((string)$option_value === $value) ? ' selected' : '';
                echo '<option value="' . e((string)$option_value) . '"' . $selected . '>' . e((string)$option_label) . '</option>';
            }

            echo '</select>';
        } elseif ($type === 'checkbox') {
            $checked = $value === '1' ? ' checked' : '';
            echo '<label class="settings-toggle">';
            echo '<input type="checkbox" id="' . e($key) . '" name="' . e($key) . '" value="1"' . $checked . '>';
            echo '<span></span>';
            echo '</label>';
        } elseif ($type === 'crawler_mode') {
            $crawler_name = (string)($field['crawler_name'] ?? $label);
            $crawler_type = (string)($field['crawler_type'] ?? 'Crawler');
            $mode_label = [
                'follow' => 'Follow Default',
                'allow' => 'Allow Public Pages',
                'block' => 'Block Entire Site',
            ][$value] ?? 'Follow Default';

            echo '<div class="crawler-card-head">';
            echo '<div class="crawler-card-mark">' . e(strtoupper(substr($crawler_name, 0, 1))) . '</div>';
            echo '<div class="crawler-card-copy">';
            echo '<strong>' . e($crawler_name) . '</strong>';
            echo '<span>' . e($crawler_type) . '</span>';
            echo '</div>';
            echo '<span class="crawler-mode-badge crawler-mode-' . e($value) . '">' . e($mode_label) . '</span>';
            echo '</div>';

            echo '<select class="crawler-mode-select" id="' . e($key) . '" name="' . e($key) . '">';
            foreach (($field['options'] ?? []) as $option_value => $option_label) {
                $selected = ((string)$option_value === $value) ? ' selected' : '';
                echo '<option value="' . e((string)$option_value) . '"' . $selected . '>' . e((string)$option_label) . '</option>';
            }
            echo '</select>';
        } elseif ($type === 'file') {
            echo '<div class="settings-upload">';

            echo '<div class="settings-upload-thumb">';
            if ($value !== '') {
                echo '<img src="' . e(admin_site_cache_bust_url($value)) . '" alt="' . e($label) . '">';
            } else {
                echo '<span>No file</span>';
            }
            echo '</div>';

            echo '<div class="settings-upload-controls">';
            echo '<span class="settings-upload-btn-wrap">';
            echo '<input type="file" class="settings-upload-input" id="' . e($key) . '" name="' . e($key) . '" accept="' . e((string)($field['accept'] ?? 'image/*')) . '">';
            echo '<span class="settings-upload-btn">Choose File</span>';
            echo '</span>';
            echo '<span class="settings-upload-filename" data-empty-text="No file chosen">No file chosen</span>';

            if ($value !== '') {
                echo '<a href="' . e($value) . '" target="_blank" rel="noopener" class="settings-upload-view">View current file</a>';
            }
            echo '</div>';

            echo '</div>';
        } elseif ($type === 'color') {
            $color = admin_site_normalize_hex_color($value, (string)($field['default'] ?? '#0f766e'));
            echo '<div class="settings-color-wrap">';
            echo '<input type="color" id="' . e($key) . '" name="' . e($key) . '" value="' . e($color) . '">';
            echo '<input type="text" name="' . e($key) . '_text" value="' . e($color) . '" placeholder="#0f766e">';
            echo '</div>';
        } else {
            echo '<input type="' . e($type) . '" id="' . e($key) . '" name="' . e($key) . '" value="' . e($value) . '" placeholder="' . e($placeholder) . '">';
        }

        if ($help !== '') {
            echo '<small>' . e($help) . '</small>';
        }

        echo '</div>';
    }
}


/*
|--------------------------------------------------------------------------
| Robots.txt Controls
|--------------------------------------------------------------------------
| These helpers generate the root robots.txt file from the Site Settings
| page. Cloudflare Managed robots.txt rules, when enabled, are still added
| by Cloudflare before this generated file is served to visitors and bots.
*/

if (!function_exists('admin_robots_clean_paths')) {
    function admin_robots_clean_paths(string $value): array
    {
        $paths = [];
        $lines = preg_split('/\R/u', $value) ?: [];

        foreach ($lines as $line) {
            $line = trim((string)$line);

            if ($line === '' || strpos($line, '#') === 0) {
                continue;
            }

            // Remove control characters and prevent line injection into robots.txt.
            $line = preg_replace('/[\r\n\x00-\x1F\x7F]/u', '', $line);

            if ($line === '') {
                continue;
            }

            if (substr($line, 0, 1) !== '/') {
                $line = '/' . $line;
            }

            if (!in_array($line, $paths, true)) {
                $paths[] = $line;
            }
        }

        return $paths;
    }
}

if (!function_exists('admin_robots_crawler_catalog')) {
    function admin_robots_crawler_catalog(): array
    {
        return [
            [
                'key' => 'robots_crawler_googlebot',
                'agent' => 'Googlebot',
                'label' => 'Google Search',
                'type' => 'Search Engine',
                'default' => 'allow',
                'help' => 'Google Search crawler. Keep allowed for Google indexing.',
            ],
            [
                'key' => 'robots_crawler_bingbot',
                'agent' => 'bingbot',
                'label' => 'Bing and Microsoft Search',
                'type' => 'Search Engine',
                'default' => 'allow',
                'help' => 'Bing, Copilot, Microsoft Search and related discovery.',
            ],
            [
                'key' => 'robots_crawler_yandexbot',
                'agent' => 'YandexBot',
                'label' => 'Yandex Search',
                'type' => 'Search Engine',
                'default' => 'allow',
                'help' => 'Yandex web search crawler.',
            ],
            [
                'key' => 'robots_crawler_baiduspider',
                'agent' => 'Baiduspider',
                'label' => 'Baidu Search',
                'type' => 'Search Engine',
                'default' => 'allow',
                'help' => 'Baidu web search crawler.',
            ],
            [
                'key' => 'robots_crawler_duckduckbot',
                'agent' => 'DuckDuckBot',
                'label' => 'DuckDuckGo Search',
                'type' => 'Search Engine',
                'default' => 'allow',
                'help' => 'DuckDuckGo search crawler.',
            ],
            [
                'key' => 'robots_crawler_oai_searchbot',
                'agent' => 'OAI-SearchBot',
                'label' => 'ChatGPT Search',
                'type' => 'AI Search',
                'default' => 'allow',
                'help' => 'Allow public MedicBD pages to be considered for ChatGPT Search.',
            ],
            [
                'key' => 'robots_crawler_perplexitybot',
                'agent' => 'PerplexityBot',
                'label' => 'Perplexity Search',
                'type' => 'AI Search',
                'default' => 'allow',
                'help' => 'Allow public MedicBD pages to be considered for Perplexity answers.',
            ],
            [
                'key' => 'robots_crawler_applebot',
                'agent' => 'Applebot',
                'label' => 'Apple Search',
                'type' => 'Search Engine',
                'default' => 'allow',
                'help' => 'Apple web crawler for search and Siri-related discovery.',
            ],
            [
                'key' => 'robots_crawler_gptbot',
                'agent' => 'GPTBot',
                'label' => 'GPTBot',
                'type' => 'AI Training',
                'default' => 'block',
                'help' => 'OpenAI training crawler. Blocking does not block OAI-SearchBot.',
            ],
            [
                'key' => 'robots_crawler_claudebot',
                'agent' => 'ClaudeBot',
                'label' => 'ClaudeBot',
                'type' => 'AI Training',
                'default' => 'block',
                'help' => 'Anthropic crawler and data collection control.',
            ],
            [
                'key' => 'robots_crawler_google_extended',
                'agent' => 'Google-Extended',
                'label' => 'Google-Extended',
                'type' => 'AI Usage Control',
                'default' => 'block',
                'help' => 'Does not control normal Google Search indexing.',
            ],
            [
                'key' => 'robots_crawler_ccbot',
                'agent' => 'CCBot',
                'label' => 'CCBot',
                'type' => 'Dataset Crawler',
                'default' => 'block',
                'help' => 'Common Crawl dataset crawler.',
            ],
            [
                'key' => 'robots_crawler_bytespider',
                'agent' => 'Bytespider',
                'label' => 'Bytespider',
                'type' => 'Data Crawler',
                'default' => 'block',
                'help' => 'ByteDance crawler control.',
            ],
            [
                'key' => 'robots_crawler_amazonbot',
                'agent' => 'Amazonbot',
                'label' => 'Amazonbot',
                'type' => 'Crawler',
                'default' => 'block',
                'help' => 'Amazon crawler control.',
            ],
            [
                'key' => 'robots_crawler_applebot_extended',
                'agent' => 'Applebot-Extended',
                'label' => 'Applebot-Extended',
                'type' => 'AI Usage Control',
                'default' => 'block',
                'help' => 'Apple extended AI crawler control.',
            ],
            [
                'key' => 'robots_crawler_meta_externalagent',
                'agent' => 'meta-externalagent',
                'label' => 'Meta External Agent',
                'type' => 'AI/Data Crawler',
                'default' => 'block',
                'help' => 'Meta external automated agent control.',
            ],
            [
                'key' => 'robots_crawler_cloudflare_rendering',
                'agent' => 'CloudflareBrowserRenderingCrawler',
                'label' => 'Cloudflare Browser Rendering',
                'type' => 'Rendering Crawler',
                'default' => 'block',
                'help' => 'Block unless you actively use a Cloudflare rendering workflow.',
            ],
        ];
    }
}

if (!function_exists('admin_robots_normalize_mode')) {
    function admin_robots_normalize_mode(string $mode, string $default = 'follow'): string
    {
        $mode = strtolower(trim($mode));

        if (in_array($mode, ['follow', 'allow', 'block'], true)) {
            return $mode;
        }

        return in_array($default, ['follow', 'allow', 'block'], true) ? $default : 'follow';
    }
}

if (!function_exists('admin_robots_append_public_group')) {
    function admin_robots_append_public_group(array &$lines, string $user_agent, array $allow_paths, array $disallow_paths): void
    {
        $lines[] = '';
        $lines[] = 'User-agent: ' . $user_agent;
        $lines[] = 'Allow: /';

        foreach ($allow_paths as $path) {
            $lines[] = 'Allow: ' . $path;
        }

        foreach ($disallow_paths as $path) {
            $lines[] = 'Disallow: ' . $path;
        }
    }
}

if (!function_exists('admin_site_build_robots_txt')) {
    function admin_site_build_robots_txt(array $settings): string
    {
        $default_disallow_paths = "/admin/\n/user/\n/ajax/\n/config/\n/includes/\n/.ssh/\n/README.md";

        $default_access = (string)($settings['robots_default_access'] ?? 'allow');
        $default_access = $default_access === 'block' ? 'block' : 'allow';

        $content_signal = trim((string)($settings['robots_content_signal'] ?? ''));
        $allowed_content_signals = [
            '',
            'search=yes,ai-train=no',
            'search=yes,ai-input=no,ai-train=no',
            'search=yes,ai-input=yes,ai-train=no',
            'search=yes,ai-input=yes,ai-train=yes',
        ];

        if (!in_array($content_signal, $allowed_content_signals, true)) {
            $content_signal = '';
        }

        $disallow_value = array_key_exists('robots_disallow_paths', $settings)
            ? (string)$settings['robots_disallow_paths']
            : $default_disallow_paths;

        $allow_value = (string)($settings['robots_allow_paths'] ?? '');
        $disallow_paths = admin_robots_clean_paths($disallow_value);
        $allow_paths = admin_robots_clean_paths($allow_value);

        $sitemap_url = trim((string)($settings['robots_sitemap_url'] ?? 'https://medic.bd/sitemap.xml'));

        if (!filter_var($sitemap_url, FILTER_VALIDATE_URL)) {
            $sitemap_url = 'https://medic.bd/sitemap.xml';
        }

        $lines = [
            '# MedicBD Robots.txt',
            '# Search Engine, AI Search and Crawler Access Rules',
            '',
            'User-agent: *',
        ];

        if ($content_signal !== '') {
            $lines[] = 'Content-Signal: ' . $content_signal;
        }

        if ($default_access === 'block') {
            foreach ($allow_paths as $path) {
                $lines[] = 'Allow: ' . $path;
            }

            $lines[] = 'Disallow: /';
        } else {
            $lines[] = 'Allow: /';

            foreach ($allow_paths as $path) {
                $lines[] = 'Allow: ' . $path;
            }

            foreach ($disallow_paths as $path) {
                $lines[] = 'Disallow: ' . $path;
            }
        }

        foreach (admin_robots_crawler_catalog() as $crawler) {
            $key = (string)$crawler['key'];
            $agent = (string)$crawler['agent'];
            $default_mode = (string)$crawler['default'];

            $mode = admin_robots_normalize_mode(
                (string)($settings[$key] ?? ''),
                $default_mode
            );

            if ($mode === 'follow') {
                continue;
            }

            if ($mode === 'block') {
                $lines[] = '';
                $lines[] = 'User-agent: ' . $agent;
                $lines[] = 'Disallow: /';
                continue;
            }

            admin_robots_append_public_group($lines, $agent, $allow_paths, $disallow_paths);
        }

        $lines[] = '';
        $lines[] = 'Sitemap: ' . $sitemap_url;

        return implode("\n", $lines) . "\n";
    }
}

if (!function_exists('admin_site_write_robots_txt')) {
    function admin_site_write_robots_txt(array $settings): bool
    {
        $robots_path = dirname(__DIR__) . '/robots.txt';
        $temporary_path = $robots_path . '.tmp';
        $content = admin_site_build_robots_txt($settings);

        $bytes_written = @file_put_contents($temporary_path, $content, LOCK_EX);

        if ($bytes_written === false) {
            return false;
        }

        if (!@rename($temporary_path, $robots_path)) {
            @unlink($temporary_path);
            return false;
        }

        @chmod($robots_path, 0644);

        return true;
    }
}

$settings_schema = [
    'Website Identity' => [
        'icon' => '🌐',
        'description' => 'Website name, logo, favicon, language and timezone.',
        'fields' => [
            ['key' => 'site_name', 'label' => 'Site Name', 'type' => 'text', 'default' => 'Deluti'],
            ['key' => 'site_short_name', 'label' => 'Short Name', 'type' => 'text', 'default' => 'Deluti'],
            ['key' => 'site_tagline', 'label' => 'Site Tagline', 'type' => 'text', 'default' => 'Doctor and Hospital Directory'],
            ['key' => 'site_language', 'label' => 'Default Language', 'type' => 'select', 'default' => 'en', 'options' => ['en' => 'English', 'bn' => 'Bangla']],
            ['key' => 'site_timezone', 'label' => 'Timezone', 'type' => 'text', 'default' => 'Asia/Dhaka'],
            ['key' => 'site_logo', 'label' => 'Site Logo', 'type' => 'file', 'full' => true, 'accept' => 'image/*', 'default' => '../assets/images/site-logo.webp', 'help' => 'JPG, PNG, GIF or WebP will be saved as /assets/images/site-logo-YYMMDDHHMMSS.webp.'],
            ['key' => 'site_dark_logo', 'label' => 'Dark Logo', 'type' => 'file', 'full' => true, 'accept' => 'image/*', 'default' => '../assets/images/site-dark-logo.webp', 'help' => 'JPG, PNG, GIF or WebP will be saved as /assets/images/site-dark-logo-YYMMDDHHMMSS.webp.'],
            ['key' => 'site_favicon', 'label' => 'Favicon', 'type' => 'file', 'full' => true, 'accept' => 'image/*,.ico', 'default' => '../assets/images/favicon.ico', 'help' => 'ICO files will be saved as /assets/images/favicon-YYMMDDHHMMSS.ico. Other image formats will be converted to /assets/images/favicon-YYMMDDHHMMSS.webp.'],
            ['key' => 'site_apple_touch_icon', 'label' => 'Apple Touch Icon', 'type' => 'file', 'full' => true, 'accept' => 'image/*', 'default' => '../assets/images/apple-touch-icon.webp', 'help' => 'Converted and saved as /assets/images/apple-touch-icon-YYMMDDHHMMSS.webp.'],
            ['key' => 'site_description', 'label' => 'Site Description', 'type' => 'textarea', 'full' => true],
        ],
    ],
    'Contact Information' => [
        'icon' => '📞',
        'description' => 'Email, phone, hotline, WhatsApp, address and map.',
        'fields' => [
            ['key' => 'admin_email', 'label' => 'Admin Email', 'type' => 'email'],
            ['key' => 'support_email', 'label' => 'Support Email', 'type' => 'email'],
            ['key' => 'contact_email', 'label' => 'Contact Email', 'type' => 'email'],
            ['key' => 'contact_phone', 'label' => 'Contact Phone', 'type' => 'text'],
            ['key' => 'hotline_number', 'label' => 'Hotline Number', 'type' => 'text'],
            ['key' => 'whatsapp_number', 'label' => 'WhatsApp Number', 'type' => 'text'],
            ['key' => 'emergency_number', 'label' => 'Emergency Number', 'type' => 'text'],
            ['key' => 'business_hours', 'label' => 'Business Hours', 'type' => 'text', 'placeholder' => 'Sat - Thu, 9 AM - 8 PM'],
            ['key' => 'office_address', 'label' => 'Office Address', 'type' => 'textarea', 'full' => true],
            ['key' => 'map_embed_code', 'label' => 'Google Map Embed Code', 'type' => 'code', 'full' => true],
        ],
    ],
    'Social Links' => [
        'icon' => '🔗',
        'description' => 'Social media and community profile URLs.',
        'fields' => [
            ['key' => 'facebook_url', 'label' => 'Facebook URL', 'type' => 'url'],
            ['key' => 'twitter_url', 'label' => 'Twitter / X URL', 'type' => 'url'],
            ['key' => 'linkedin_url', 'label' => 'LinkedIn URL', 'type' => 'url'],
            ['key' => 'instagram_url', 'label' => 'Instagram URL', 'type' => 'url'],
            ['key' => 'youtube_url', 'label' => 'YouTube URL', 'type' => 'url'],
            ['key' => 'tiktok_url', 'label' => 'TikTok URL', 'type' => 'url'],
            ['key' => 'telegram_url', 'label' => 'Telegram URL', 'type' => 'url'],
            ['key' => 'whatsapp_channel_url', 'label' => 'WhatsApp Channel URL', 'type' => 'url'],
        ],
    ],
    'SEO Settings' => [
        'icon' => '🔍',
        'description' => 'Meta title, description, robots and verification codes.',
        'fields' => [
            ['key' => 'meta_title', 'label' => 'Default Meta Title', 'type' => 'text', 'full' => true],
            ['key' => 'meta_description', 'label' => 'Default Meta Description', 'type' => 'textarea', 'full' => true],
            ['key' => 'meta_keywords', 'label' => 'Meta Keywords', 'type' => 'textarea', 'full' => true],
            ['key' => 'canonical_url', 'label' => 'Canonical URL', 'type' => 'url'],
            ['key' => 'robots_meta', 'label' => 'Robots Meta', 'type' => 'select', 'default' => 'index, follow', 'options' => [
                'index, follow' => 'Index, Follow',
                'noindex, follow' => 'Noindex, Follow',
                'index, nofollow' => 'Index, Nofollow',
                'noindex, nofollow' => 'Noindex, Nofollow',
            ]],
            ['key' => 'default_schema_type', 'label' => 'Default Schema Type', 'type' => 'select', 'default' => 'WebSite', 'options' => [
                'WebSite' => 'WebSite',
                'Organization' => 'Organization',
                'MedicalOrganization' => 'MedicalOrganization',
                'LocalBusiness' => 'LocalBusiness',
            ]],
            ['key' => 'google_site_verification', 'label' => 'Google Site Verification', 'type' => 'text'],
            ['key' => 'bing_site_verification', 'label' => 'Bing Site Verification', 'type' => 'text'],
            ['key' => 'yandex_site_verification', 'label' => 'Yandex Site Verification', 'type' => 'text'],
            ['key' => 'default_og_image', 'label' => 'Default OG Image', 'type' => 'file', 'full' => true, 'accept' => 'image/*', 'default' => '../assets/images/default-og-image.webp', 'help' => 'Converted and saved as /assets/images/default-og-image-YYMMDDHHMMSS.webp.'],
        ],
    ],
    'Robots.txt Control' => [
        'icon' => '🤖',
        'description' => 'Manage search engines, AI search crawlers, training bots, protected paths and the live robots.txt output.',
        'fields' => [
            [
                'key' => 'robots_default_access',
                'label' => 'Default Website Access',
                'type' => 'select',
                'default' => 'allow',
                'options' => [
                    'allow' => 'Allow Public Crawling',
                    'block' => 'Block All Crawling',
                ],
                'help' => 'Allow Public Crawling is recommended for normal SEO. Specific crawler settings below can override this rule.',
            ],
            [
                'key' => 'robots_disallow_paths',
                'label' => 'Protected and Non-Indexable Paths',
                'type' => 'textarea',
                'full' => true,
                'default' => "/admin/\n/user/\n/ajax/\n/config/\n/includes/\n/.ssh/\n/README.md",
                'help' => 'One path per line. These paths are excluded from crawlers that are allowed to access public pages.',
            ],
            [
                'key' => 'robots_allow_paths',
                'label' => 'Explicitly Allowed Paths',
                'type' => 'textarea',
                'full' => true,
                'default' => '',
                'help' => 'Optional. Add one path per line only when it needs an explicit Allow directive.',
            ],
            [
                'key' => 'robots_sitemap_url',
                'label' => 'XML Sitemap URL',
                'type' => 'url',
                'default' => 'https://medic.bd/sitemap.xml',
                'full' => true,
                'help' => 'Use the complete public sitemap URL.',
            ],
            [
                'key' => 'robots_content_signal',
                'label' => 'Content Signal',
                'type' => 'select',
                'default' => 'search=yes,ai-train=no',
                'options' => [
                    '' => 'Do Not Add Content Signal',
                    'search=yes,ai-train=no' => 'Allow Search, Block AI Training',
                    'search=yes,ai-input=no,ai-train=no' => 'Allow Search, Block AI Input and Training',
                    'search=yes,ai-input=yes,ai-train=no' => 'Allow Search and AI Input, Block Training',
                    'search=yes,ai-input=yes,ai-train=yes' => 'Allow Search, AI Input and Training',
                ],
                'help' => 'Disable this only if Cloudflare Managed robots.txt is adding the same directive.',
            ],
            [
                'key' => 'robots_crawler_googlebot',
                'label' => 'Google Search',
                'crawler_name' => 'Googlebot',
                'crawler_type' => 'Search Engine',
                'type' => 'crawler_mode',
                'default' => 'allow',
                'options' => ['follow' => 'Follow Default Website Access', 'allow' => 'Allow Public Pages', 'block' => 'Block Entire Site'],
                'help' => 'Keep allowed for Google indexing.',
            ],
            [
                'key' => 'robots_crawler_bingbot',
                'label' => 'Bing and Microsoft Search',
                'crawler_name' => 'bingbot',
                'crawler_type' => 'Search Engine',
                'type' => 'crawler_mode',
                'default' => 'allow',
                'options' => ['follow' => 'Follow Default Website Access', 'allow' => 'Allow Public Pages', 'block' => 'Block Entire Site'],
                'help' => 'Supports Bing, Microsoft Search and related discovery.',
            ],
            [
                'key' => 'robots_crawler_yandexbot',
                'label' => 'Yandex Search',
                'crawler_name' => 'YandexBot',
                'crawler_type' => 'Search Engine',
                'type' => 'crawler_mode',
                'default' => 'allow',
                'options' => ['follow' => 'Follow Default Website Access', 'allow' => 'Allow Public Pages', 'block' => 'Block Entire Site'],
                'help' => 'Yandex web search crawler.',
            ],
            [
                'key' => 'robots_crawler_baiduspider',
                'label' => 'Baidu Search',
                'crawler_name' => 'Baiduspider',
                'crawler_type' => 'Search Engine',
                'type' => 'crawler_mode',
                'default' => 'allow',
                'options' => ['follow' => 'Follow Default Website Access', 'allow' => 'Allow Public Pages', 'block' => 'Block Entire Site'],
                'help' => 'Baidu web search crawler.',
            ],
            [
                'key' => 'robots_crawler_duckduckbot',
                'label' => 'DuckDuckGo Search',
                'crawler_name' => 'DuckDuckBot',
                'crawler_type' => 'Search Engine',
                'type' => 'crawler_mode',
                'default' => 'allow',
                'options' => ['follow' => 'Follow Default Website Access', 'allow' => 'Allow Public Pages', 'block' => 'Block Entire Site'],
                'help' => 'DuckDuckGo web search crawler.',
            ],
            [
                'key' => 'robots_crawler_applebot',
                'label' => 'Apple Search',
                'crawler_name' => 'Applebot',
                'crawler_type' => 'Search Engine',
                'type' => 'crawler_mode',
                'default' => 'allow',
                'options' => ['follow' => 'Follow Default Website Access', 'allow' => 'Allow Public Pages', 'block' => 'Block Entire Site'],
                'help' => 'Apple web search and Siri-related discovery crawler.',
            ],
            [
                'key' => 'robots_crawler_oai_searchbot',
                'label' => 'ChatGPT Search',
                'crawler_name' => 'OAI-SearchBot',
                'crawler_type' => 'AI Search',
                'type' => 'crawler_mode',
                'default' => 'allow',
                'options' => ['follow' => 'Follow Default Website Access', 'allow' => 'Allow Public Pages', 'block' => 'Block Entire Site'],
                'help' => 'Allows public pages to be considered for ChatGPT Search. Separate from GPTBot.',
            ],
            [
                'key' => 'robots_crawler_perplexitybot',
                'label' => 'Perplexity Search',
                'crawler_name' => 'PerplexityBot',
                'crawler_type' => 'AI Search',
                'type' => 'crawler_mode',
                'default' => 'allow',
                'options' => ['follow' => 'Follow Default Website Access', 'allow' => 'Allow Public Pages', 'block' => 'Block Entire Site'],
                'help' => 'Allows public pages to be considered for Perplexity answers.',
            ],
            [
                'key' => 'robots_crawler_gptbot',
                'label' => 'GPTBot',
                'crawler_name' => 'GPTBot',
                'crawler_type' => 'AI Training',
                'type' => 'crawler_mode',
                'default' => 'block',
                'options' => ['follow' => 'Follow Default Website Access', 'allow' => 'Allow Public Pages', 'block' => 'Block Entire Site'],
                'help' => 'OpenAI training crawler. It can remain blocked while ChatGPT Search is allowed.',
            ],
            [
                'key' => 'robots_crawler_claudebot',
                'label' => 'ClaudeBot',
                'crawler_name' => 'ClaudeBot',
                'crawler_type' => 'AI Training',
                'type' => 'crawler_mode',
                'default' => 'block',
                'options' => ['follow' => 'Follow Default Website Access', 'allow' => 'Allow Public Pages', 'block' => 'Block Entire Site'],
                'help' => 'Anthropic crawler and data collection control.',
            ],
            [
                'key' => 'robots_crawler_google_extended',
                'label' => 'Google-Extended',
                'crawler_name' => 'Google-Extended',
                'crawler_type' => 'AI Usage Control',
                'type' => 'crawler_mode',
                'default' => 'block',
                'options' => ['follow' => 'Follow Default Website Access', 'allow' => 'Allow Public Pages', 'block' => 'Block Entire Site'],
                'help' => 'Does not control standard Google Search indexing.',
            ],
            [
                'key' => 'robots_crawler_ccbot',
                'label' => 'CCBot',
                'crawler_name' => 'CCBot',
                'crawler_type' => 'Dataset Crawler',
                'type' => 'crawler_mode',
                'default' => 'block',
                'options' => ['follow' => 'Follow Default Website Access', 'allow' => 'Allow Public Pages', 'block' => 'Block Entire Site'],
                'help' => 'Common Crawl dataset crawler.',
            ],
            [
                'key' => 'robots_crawler_bytespider',
                'label' => 'Bytespider',
                'crawler_name' => 'Bytespider',
                'crawler_type' => 'Data Crawler',
                'type' => 'crawler_mode',
                'default' => 'block',
                'options' => ['follow' => 'Follow Default Website Access', 'allow' => 'Allow Public Pages', 'block' => 'Block Entire Site'],
                'help' => 'ByteDance crawler control.',
            ],
            [
                'key' => 'robots_crawler_amazonbot',
                'label' => 'Amazonbot',
                'crawler_name' => 'Amazonbot',
                'crawler_type' => 'Crawler',
                'type' => 'crawler_mode',
                'default' => 'block',
                'options' => ['follow' => 'Follow Default Website Access', 'allow' => 'Allow Public Pages', 'block' => 'Block Entire Site'],
                'help' => 'Amazon crawler control.',
            ],
            [
                'key' => 'robots_crawler_applebot_extended',
                'label' => 'Applebot-Extended',
                'crawler_name' => 'Applebot-Extended',
                'crawler_type' => 'AI Usage Control',
                'type' => 'crawler_mode',
                'default' => 'block',
                'options' => ['follow' => 'Follow Default Website Access', 'allow' => 'Allow Public Pages', 'block' => 'Block Entire Site'],
                'help' => 'Apple extended AI crawler control.',
            ],
            [
                'key' => 'robots_crawler_meta_externalagent',
                'label' => 'Meta External Agent',
                'crawler_name' => 'meta-externalagent',
                'crawler_type' => 'AI/Data Crawler',
                'type' => 'crawler_mode',
                'default' => 'block',
                'options' => ['follow' => 'Follow Default Website Access', 'allow' => 'Allow Public Pages', 'block' => 'Block Entire Site'],
                'help' => 'Meta external automated agent control.',
            ],
            [
                'key' => 'robots_crawler_cloudflare_rendering',
                'label' => 'Cloudflare Browser Rendering',
                'crawler_name' => 'CloudflareBrowserRenderingCrawler',
                'crawler_type' => 'Rendering Crawler',
                'type' => 'crawler_mode',
                'default' => 'block',
                'options' => ['follow' => 'Follow Default Website Access', 'allow' => 'Allow Public Pages', 'block' => 'Block Entire Site'],
                'help' => 'Block unless you actively use a Cloudflare rendering workflow.',
            ],
        ],
    ],
    'Homepage Settings' => [
        'icon' => '🏠',
        'description' => 'Hero section, featured doctors, hospitals and homepage limits.',
        'fields' => [
            ['key' => 'home_hero_title', 'label' => 'Hero Title', 'type' => 'text', 'default' => 'Find the Right Doctor Near You'],
            ['key' => 'home_hero_title_bn', 'label' => 'Hero Title (Bangla)', 'type' => 'text', 'help' => 'Leave blank to use the default Bangla text.'],
            ['key' => 'home_hero_subtitle', 'label' => 'Hero Subtitle', 'type' => 'textarea'],
            ['key' => 'home_hero_subtitle_bn', 'label' => 'Hero Subtitle (Bangla)', 'type' => 'textarea', 'help' => 'Leave blank to use the default Bangla text.'],
            ['key' => 'home_hero_button_text', 'label' => 'Hero Button Text', 'type' => 'text', 'default' => 'Find Doctor'],
            ['key' => 'home_hero_button_url', 'label' => 'Hero Button URL', 'type' => 'text', 'default' => 'doctors.php'],
            ['key' => 'home_featured_specialties_limit', 'label' => 'Featured Specialties Limit', 'type' => 'number', 'default' => '12'],
            ['key' => 'home_featured_doctors_limit', 'label' => 'Featured Doctors Limit', 'type' => 'number', 'default' => '8'],
            ['key' => 'home_featured_hospitals_limit', 'label' => 'Featured Hospitals Limit', 'type' => 'number', 'default' => '8'],
        ],
    ],
    'Directory Settings' => [
        'icon' => '📋',
        'description' => 'Doctor/hospital listing, reviews, claim and update request controls.',
        'fields' => [
            ['key' => 'doctors_per_page', 'label' => 'Doctors Per Page', 'type' => 'number', 'default' => '12'],
            ['key' => 'hospitals_per_page', 'label' => 'Hospitals Per Page', 'type' => 'number', 'default' => '12'],
            ['key' => 'reviews_per_page', 'label' => 'Reviews Per Page', 'type' => 'number', 'default' => '10'],
            ['key' => 'enable_doctor_reviews', 'label' => 'Enable Doctor Reviews', 'type' => 'checkbox', 'default' => '1'],
            ['key' => 'enable_hospital_reviews', 'label' => 'Enable Hospital Reviews', 'type' => 'checkbox', 'default' => '1'],
            ['key' => 'enable_profile_claim', 'label' => 'Enable Profile Claim', 'type' => 'checkbox', 'default' => '1'],
            ['key' => 'enable_profile_update_request', 'label' => 'Enable Profile Update Request', 'type' => 'checkbox', 'default' => '1'],
            ['key' => 'default_doctor_male_image', 'label' => 'Default Doctor Image - Male', 'type' => 'file', 'full' => true, 'accept' => 'image/*', 'default' => '../assets/images/default-doctor-male.webp', 'help' => 'Used when doctor gender is Male and no profile image is uploaded. JPG, PNG, GIF or WebP will be converted and saved as /assets/images/default-doctor-male-YYMMDDHHMMSS.webp.'],
            ['key' => 'default_doctor_female_image', 'label' => 'Default Doctor Image - Female', 'type' => 'file', 'full' => true, 'accept' => 'image/*', 'default' => '../assets/images/default-doctor-female.webp', 'help' => 'Used when doctor gender is Female and no profile image is uploaded. JPG, PNG, GIF or WebP will be converted and saved as /assets/images/default-doctor-female-YYMMDDHHMMSS.webp.'],
            ['key' => 'default_doctor_image', 'label' => 'Default Doctor Image - Fallback', 'type' => 'file', 'full' => true, 'accept' => 'image/*', 'default' => '../assets/images/default-doctor.webp', 'help' => 'Used when gender is missing/Other or Male/Female default image is not set. JPG, PNG, GIF or WebP will be converted and saved as /assets/images/default-doctor-YYMMDDHHMMSS.webp.'],
            ['key' => 'default_hospital_image', 'label' => 'Default Hospital Logo', 'type' => 'file', 'full' => true, 'accept' => 'image/*', 'default' => '../assets/images/default-hospital.webp', 'help' => 'Small square logo shown next to a hospital\'s name when that hospital has not uploaded its own logo. JPG, PNG, GIF or WebP will be converted and saved as /assets/images/default-hospital-YYMMDDHHMMSS.webp.'],
            ['key' => 'default_hospital_cover_image', 'label' => 'Default Hospital Cover Image', 'type' => 'file', 'full' => true, 'accept' => 'image/*', 'default' => '../assets/images/default-hospital.webp', 'help' => 'Wide banner image shown behind a hospital\'s profile when that hospital has not uploaded its own cover photo. Kept separate from the logo above so a small logo is never stretched to fill the banner. JPG, PNG, GIF or WebP will be converted and saved as /assets/images/default-hospital-cover-YYMMDDHHMMSS.webp.'],
        ],
    ],
    'User Settings' => [
        'icon' => '👤',
        'description' => 'Registration, login and verification options.',
        'fields' => [
            ['key' => 'enable_user_registration', 'label' => 'Enable User Registration', 'type' => 'checkbox', 'default' => '1'],
            ['key' => 'enable_user_login', 'label' => 'Enable User Login', 'type' => 'checkbox', 'default' => '1'],
            ['key' => 'default_user_status', 'label' => 'Default User Status', 'type' => 'select', 'default' => 'active', 'options' => ['active' => 'Active', 'blocked' => 'Blocked', 'pending' => 'Pending']],
            ['key' => 'email_verification_required', 'label' => 'Email Verification Required', 'type' => 'checkbox', 'default' => '0'],
            ['key' => 'phone_verification_required', 'label' => 'Phone Verification Required', 'type' => 'checkbox', 'default' => '0'],
        ],
    ],
    'Email & SMTP' => [
        'icon' => '📧',
        'description' => 'Mail sender and SMTP configuration.',
        'fields' => [
            ['key' => 'mail_from_name', 'label' => 'Mail From Name', 'type' => 'text', 'default' => 'Deluti'],
            ['key' => 'mail_from_email', 'label' => 'Mail From Email', 'type' => 'email'],
            ['key' => 'smtp_enabled', 'label' => 'Enable SMTP', 'type' => 'checkbox', 'default' => '0'],
            ['key' => 'smtp_host', 'label' => 'SMTP Host', 'type' => 'text'],
            ['key' => 'smtp_port', 'label' => 'SMTP Port', 'type' => 'number'],
            ['key' => 'smtp_username', 'label' => 'SMTP Username', 'type' => 'text'],
            ['key' => 'smtp_password', 'label' => 'SMTP Password', 'type' => 'password'],
            ['key' => 'smtp_encryption', 'label' => 'SMTP Encryption', 'type' => 'select', 'default' => 'tls', 'options' => ['' => 'None', 'tls' => 'TLS', 'ssl' => 'SSL']],
        ],
    ],
    'SMS & WhatsApp API' => [
        'icon' => '💬',
        'description' => 'SMS provider and WhatsApp API settings.',
        'fields' => [
            ['key' => 'sms_enabled', 'label' => 'Enable SMS', 'type' => 'checkbox', 'default' => '0'],
            ['key' => 'sms_provider', 'label' => 'SMS Provider', 'type' => 'text'],
            ['key' => 'sms_api_key', 'label' => 'SMS API Key', 'type' => 'password'],
            ['key' => 'sms_sender_id', 'label' => 'SMS Sender ID', 'type' => 'text'],
            ['key' => 'whatsapp_enabled', 'label' => 'Enable WhatsApp API', 'type' => 'checkbox', 'default' => '0'],
            ['key' => 'whatsapp_api_key', 'label' => 'WhatsApp API Key', 'type' => 'password'],
        ],
    ],
    'Analytics & Tracking' => [
        'icon' => '📊',
        'description' => 'Analytics, Tag Manager, Pixel and custom scripts.',
        'fields' => [
            ['key' => 'google_analytics_id', 'label' => 'Google Analytics ID', 'type' => 'text'],
            ['key' => 'google_tag_manager_id', 'label' => 'Google Tag Manager ID', 'type' => 'text'],
            ['key' => 'facebook_pixel_id', 'label' => 'Facebook Pixel ID', 'type' => 'text'],
            ['key' => 'custom_head_code', 'label' => 'Custom Head Code', 'type' => 'code', 'full' => true],
            ['key' => 'custom_footer_code', 'label' => 'Custom Footer Code', 'type' => 'code', 'full' => true],
        ],
    ],
    'Design Settings' => [
        'icon' => '🎨',
        'description' => 'Brand colors, header/footer style and dark mode.',
        'fields' => [
            ['key' => 'primary_color', 'label' => 'Primary Color', 'type' => 'color', 'default' => '#0f766e'],
            ['key' => 'secondary_color', 'label' => 'Secondary Color', 'type' => 'color', 'default' => '#14b8a6'],
            ['key' => 'accent_color', 'label' => 'Accent Color', 'type' => 'color', 'default' => '#2da44e'],
            ['key' => 'body_background_color', 'label' => 'Body Background Color', 'type' => 'color', 'default' => '#f6f8fa'],
            ['key' => 'header_style', 'label' => 'Header Style', 'type' => 'select', 'default' => 'default', 'options' => ['default' => 'Default', 'compact' => 'Compact', 'modern' => 'Modern']],
            ['key' => 'footer_style', 'label' => 'Footer Style', 'type' => 'select', 'default' => 'default', 'options' => ['default' => 'Default', 'simple' => 'Simple', 'large' => 'Large']],
            ['key' => 'enable_dark_mode', 'label' => 'Enable Dark Mode', 'type' => 'checkbox', 'default' => '0'],
        ],
    ],
    'Header & Footer' => [
        'icon' => '🧩',
        'description' => 'Header buttons, topbar, footer text and footer visibility.',
        'fields' => [
            ['key' => 'show_topbar', 'label' => 'Show Topbar', 'type' => 'checkbox', 'default' => '1'],
            ['key' => 'show_search_bar', 'label' => 'Show Search Bar', 'type' => 'checkbox', 'default' => '1'],
            ['key' => 'show_login_button', 'label' => 'Show Login Button', 'type' => 'checkbox', 'default' => '1'],
            ['key' => 'show_register_button', 'label' => 'Show Register Button', 'type' => 'checkbox', 'default' => '1'],
            ['key' => 'header_button_text', 'label' => 'Header Button Text', 'type' => 'text', 'default' => 'Add Listing'],
            ['key' => 'header_button_url', 'label' => 'Header Button URL', 'type' => 'text'],
            ['key' => 'footer_text', 'label' => 'Footer Text', 'type' => 'textarea', 'full' => true],
            ['key' => 'footer_about_text', 'label' => 'Footer About Text', 'type' => 'textarea', 'full' => true],
            ['key' => 'footer_copyright_text', 'label' => 'Footer Copyright Text', 'type' => 'text', 'full' => true],
            ['key' => 'show_footer_social_links', 'label' => 'Show Footer Social Links', 'type' => 'checkbox', 'default' => '1'],
            ['key' => 'show_footer_contact_info', 'label' => 'Show Footer Contact Info', 'type' => 'checkbox', 'default' => '1'],
        ],
    ],
    'Legal Pages' => [
        'icon' => '📄',
        'description' => 'Privacy, terms, refund, cookie and disclaimer pages.',
        'fields' => [
            ['key' => 'privacy_policy_url', 'label' => 'Privacy Policy URL', 'type' => 'text'],
            ['key' => 'terms_conditions_url', 'label' => 'Terms & Conditions URL', 'type' => 'text'],
            ['key' => 'refund_policy_url', 'label' => 'Refund Policy URL', 'type' => 'text'],
            ['key' => 'cookie_policy_url', 'label' => 'Cookie Policy URL', 'type' => 'text'],
            ['key' => 'disclaimer_url', 'label' => 'Disclaimer URL', 'type' => 'text'],
        ],
    ],
    'Security & System' => [
        'icon' => '🔒',
        'description' => 'Maintenance mode, reCAPTCHA, debug and system emails.',
        'fields' => [
            ['key' => 'maintenance_mode', 'label' => 'Maintenance Mode', 'type' => 'checkbox', 'default' => '0'],
            ['key' => 'maintenance_title', 'label' => 'Maintenance Title', 'type' => 'text', 'default' => 'Website Under Maintenance'],
            ['key' => 'maintenance_message', 'label' => 'Maintenance Message', 'type' => 'textarea', 'full' => true],
            ['key' => 'maintenance_until', 'label' => 'Back Online At', 'type' => 'datetime-local', 'help' => 'Optional. Shows a live countdown on the maintenance page. Leave blank to hide the countdown.'],
            ['key' => 'maintenance_auto_disable', 'label' => 'Auto Disable When Time Ends', 'type' => 'checkbox', 'default' => '1', 'help' => 'When "Back Online At" passes, automatically turn Maintenance Mode off instead of waiting for a manual switch. Requires "Back Online At" to be set.'],
            ['key' => 'enable_recaptcha', 'label' => 'Enable reCAPTCHA', 'type' => 'checkbox', 'default' => '0'],
            ['key' => 'recaptcha_site_key', 'label' => 'reCAPTCHA Site Key', 'type' => 'text'],
            ['key' => 'recaptcha_secret_key', 'label' => 'reCAPTCHA Secret Key', 'type' => 'password'],
            ['key' => 'admin_login_attempt_limit', 'label' => 'Admin Login Attempt Limit', 'type' => 'number', 'default' => '5'],
            ['key' => 'backup_email', 'label' => 'Backup Email', 'type' => 'email'],
            ['key' => 'system_notification_email', 'label' => 'System Notification Email', 'type' => 'email'],
            ['key' => 'enable_error_log', 'label' => 'Enable Error Log', 'type' => 'checkbox', 'default' => '1'],
            ['key' => 'enable_debug_mode', 'label' => 'Enable Debug Mode', 'type' => 'checkbox', 'default' => '0'],
        ],
    ],
];

$all_fields = [];
$file_fields = [];
$checkbox_fields = [];
$color_fields = [];

foreach ($settings_schema as $section) {
    foreach ($section['fields'] as $field) {
        $field_key = (string)$field['key'];
        $field_type = (string)($field['type'] ?? 'text');

        $all_fields[] = $field_key;

        if ($field_type === 'file') {
            $file_fields[] = $field_key;
        } elseif ($field_type === 'checkbox') {
            $checkbox_fields[] = $field_key;
        } elseif ($field_type === 'color') {
            $color_fields[] = $field_key;
        }
    }
}

if (empty($_SESSION['admin_site_settings_csrf'])) {
    $_SESSION['admin_site_settings_csrf'] = bin2hex(random_bytes(32));
}

$csrf_token = $_SESSION['admin_site_settings_csrf'];
$errors = [];
$success = '';
$current_settings_before_save = admin_get_all_site_settings();
$should_write_robots_txt = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $posted_token = (string)($_POST['csrf_token'] ?? '');

    if (!hash_equals($csrf_token, $posted_token)) {
        $errors[] = 'Security token mismatch. Please try again.';
    } else {
        foreach ($all_fields as $field_key) {
            if (in_array($field_key, $file_fields, true)) {
                continue;
            }

            if (in_array($field_key, $checkbox_fields, true)) {
                admin_update_site_setting($field_key, isset($_POST[$field_key]) ? '1' : '0');
                continue;
            }

            if (in_array($field_key, $color_fields, true)) {
                $color_value = trim((string)($_POST[$field_key] ?? ''));
                $color_text_value = trim((string)($_POST[$field_key . '_text'] ?? ''));

                if ($color_text_value !== '') {
                    $color_value = $color_text_value;
                }

                $field_default = '#0f766e';

                foreach ($settings_schema as $section) {
                    foreach ($section['fields'] as $schema_field) {
                        if ((string)$schema_field['key'] === $field_key) {
                            $field_default = (string)($schema_field['default'] ?? '#0f766e');
                            break 2;
                        }
                    }
                }

                admin_update_site_setting($field_key, admin_site_normalize_hex_color($color_value, $field_default));
                continue;
            }

            admin_update_site_setting($field_key, trim((string)($_POST[$field_key] ?? '')));
        }

        foreach ($file_fields as $file_field) {
            $uploaded_path = admin_site_upload_file($file_field);

            if ($uploaded_path !== '') {
                $old_path = (string)($current_settings_before_save[$file_field] ?? '');

                if (admin_update_site_setting($file_field, $uploaded_path)) {
                    admin_site_delete_old_uploaded_file($old_path, $uploaded_path);
                }
            }
        }

        // Maintenance Mode was just turned off (or wasn't turned on) in this
        // save - clear "Back Online At" so a stale past/old date is never
        // left sitting there the next time Maintenance Mode gets switched on.
        if (!isset($_POST['maintenance_mode'])) {
            admin_update_site_setting('maintenance_until', '');
        }

        $should_write_robots_txt = true;
        $success = 'Site settings updated successfully.';
    }
}

$settings = admin_get_all_site_settings();

foreach ($settings_schema as $section) {
    foreach ($section['fields'] as $field) {
        $key = (string)$field['key'];

        if (!array_key_exists($key, $settings)) {
            $settings[$key] = (string)($field['default'] ?? '');
            admin_update_site_setting($key, $settings[$key]);
        }
    }
}

if ($should_write_robots_txt) {
    if (!admin_site_write_robots_txt($settings)) {
        $errors[] = 'Settings were saved, but robots.txt could not be updated. Check that the website root folder is writable by PHP.';
        $success = '';
    }
}

$schema_keys = array_keys($settings_schema);
$first_tab_key = admin_settings_slug((string)reset($schema_keys));
$active_tab = admin_settings_slug(trim((string)($_GET['tab'] ?? '')));
$active_tab = $active_tab !== '' ? $active_tab : $first_tab_key;
$robots_preview = admin_site_build_robots_txt($settings);

if (!function_exists('admin_settings_hex_to_rgba')) {
    function admin_settings_hex_to_rgba(string $hex, float $alpha): string
    {
        $hex = ltrim($hex, '#');

        if (strlen($hex) !== 6) {
            return 'rgba(9, 105, 218, ' . $alpha . ')';
        }

        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));

        return "rgba({$r}, {$g}, {$b}, {$alpha})";
    }
}

/*
|--------------------------------------------------------------------------
| Single Accent Color
|--------------------------------------------------------------------------
| One quiet accent color (the site's own configured primary color, when
| set) is reused everywhere a tab, icon or highlight needs one. A single
| consistent accent reads calmer and more premium than a different color
| per section.
*/
$settings_accent = admin_site_normalize_hex_color((string)($settings['primary_color'] ?? ''), '#0969da');
$settings_accent_soft = admin_settings_hex_to_rgba($settings_accent, 0.10);
$settings_accent_border = admin_settings_hex_to_rgba($settings_accent, 0.22);
?>

<style>
    .settings-page {
        color: #24292f;
        --accent: <?= e($settings_accent) ?>;
        --accent-soft: <?= e($settings_accent_soft) ?>;
        --accent-border: <?= e($settings_accent_border) ?>;
    }

    .settings-hero {
        position: relative;
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 16px;
        flex-wrap: wrap;
        margin-bottom: 16px;
        padding: 22px;
        border-radius: 12px;
        border: 1px solid #d0d7de;
        background: linear-gradient(135deg, #ffffff 0%, #f6f8fa 60%, var(--accent-soft, #f6f8fa) 100%);
        box-shadow: 0 1px 0 rgba(27, 31, 36, 0.04);
        overflow: hidden;
    }

    .settings-hero-top {
        display: flex;
        align-items: flex-start;
        gap: 14px;
    }

    .settings-hero-icon {
        width: 46px;
        height: 46px;
        flex: 0 0 46px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 12px;
        background: #ffffff;
        border: 1px solid var(--accent-border, #d0d7de);
        font-size: 22px;
    }

    .settings-hero h2 {
        margin: 0 0 6px;
        color: #24292f;
        font-size: 24px;
        line-height: 1.2;
        font-weight: 700;
        letter-spacing: -0.03em;
    }

    .settings-hero p {
        margin: 0;
        max-width: 780px;
        color: #57606a;
        font-size: 14px;
        line-height: 1.6;
    }

    .settings-hero-stats {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        margin-top: 12px;
    }

    .settings-hero-stat {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 5px 11px;
        border-radius: 999px;
        background: rgba(255, 255, 255, 0.7);
        border: 1px solid #d0d7de;
        color: #24292f;
        font-size: 12px;
        font-weight: 700;
    }

    .settings-hero-stat strong {
        color: var(--accent, #0969da);
    }

    .settings-hero-actions {
        position: relative;
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }

    .settings-alert {
        margin-bottom: 16px;
        padding: 12px 14px;
        border-radius: 8px;
        border: 1px solid transparent;
        font-size: 14px;
    }

    .settings-alert.success {
        background: #dafbe1;
        color: #1a7f37;
        border-color: rgba(26, 127, 55, 0.25);
    }

    .settings-alert.error {
        background: #ffebe9;
        color: #cf222e;
        border-color: rgba(207, 34, 46, 0.25);
    }

    .settings-layout {
        display: grid;
        grid-template-columns: 285px 1fr;
        gap: 16px;
        align-items: start;
    }

    .settings-tabs {
        position: sticky;
        top: 88px;
        border: 1px solid #d0d7de;
        border-radius: 10px;
        background: #ffffff;
        box-shadow: 0 1px 0 rgba(27, 31, 36, 0.04);
        overflow: hidden;
    }

    .settings-tabs-head {
        padding: 13px 14px;
        border-bottom: 1px solid #d0d7de;
        background: #f6f8fa;
        text-align: left;
    }

    .settings-tabs-head strong {
        display: block;
        color: #24292f;
        font-size: 14px;
        font-weight: 700;
        text-align: left;
    }

    .settings-tabs-head span {
        display: block;
        margin-top: 3px;
        color: #57606a;
        font-size: 12px;
        text-align: left;
    }

    .settings-tab-list {
        display: grid;
        padding: 8px;
        gap: 2px;
    }

    .settings-tab-btn {
        width: 100%;
        min-height: 38px;
        display: flex;
        align-items: center;
        justify-content: flex-start;
        gap: 10px;
        padding: 8px 9px;
        border: 0;
        border-left: 3px solid transparent;
        border-radius: 6px;
        background: transparent;
        color: #24292f;
        text-align: left;
        font-size: 13px;
        font-weight: 700;
        line-height: 1.2;
        cursor: pointer;
        box-shadow: none;
        transition: background .15s ease, color .15s ease;
    }

    .settings-tab-btn:hover {
        background: #f6f8fa;
        color: #24292f;
    }

    .settings-tab-btn.active {
        background: var(--accent-soft, #ddf4ff);
        color: var(--accent, #0969da);
        border-left-color: var(--accent, #0969da);
    }

    .settings-tab-btn span {
        text-align: left;
    }

    .settings-tab-btn span:last-child {
        flex: 1;
        display: block;
        text-align: left;
    }

    .settings-tab-icon {
        width: 28px;
        height: 28px;
        flex: 0 0 28px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 6px;
        background: #f6f8fa;
        border: 1px solid #d8dee4;
        color: #57606a;
        font-size: 14px;
    }

    .settings-tab-btn.active .settings-tab-icon {
        background: #ffffff;
        color: var(--accent, #0969da);
        border-color: var(--accent, #0969da);
    }

    .settings-main {
        min-width: 0;
    }

    .settings-section {
        display: none;
        border: 1px solid #d0d7de;
        border-radius: 10px;
        background: #ffffff;
        box-shadow: 0 1px 0 rgba(27, 31, 36, 0.04);
        overflow: hidden;
    }

    .settings-section.active {
        display: block;
    }

    .settings-section-head {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 16px 18px;
        border-bottom: 1px solid #d0d7de;
        background: #f6f8fa;
    }

    .settings-section-icon {
        width: 38px;
        height: 38px;
        flex: 0 0 38px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 10px;
        background: #ffffff;
        border: 1px solid var(--accent-border, #d8dee4);
        color: var(--accent, #57606a);
        font-size: 17px;
    }

    .settings-section-head h3 {
        margin: 0 0 4px;
        color: #24292f;
        font-size: 17px;
        font-weight: 700;
    }

    .settings-section-head p {
        margin: 0;
        color: #57606a;
        font-size: 13px;
        line-height: 1.5;
    }

    .settings-section-body {
        padding: 16px;
    }

    .settings-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(230px, 1fr));
        gap: 14px;
    }

    .settings-field {
        display: grid;
        gap: 7px;
    }

    .settings-field-full {
        grid-column: 1 / -1;
    }

    .settings-field label {
        color: #24292f;
        font-size: 13px;
        font-weight: 700;
    }

    .settings-field small {
        color: #57606a;
        font-size: 12px;
        line-height: 1.5;
    }

    .settings-field input,
    .settings-field select,
    .settings-field textarea {
        width: 100%;
        min-height: 38px;
        border: 1px solid #d0d7de;
        border-radius: 6px;
        padding: 8px 10px;
        outline: none;
        background: #ffffff;
        color: #24292f;
        font-size: 14px;
        font-family: inherit;
    }

    .settings-field textarea {
        min-height: 110px;
        resize: vertical;
    }

    .settings-field input:focus,
    .settings-field select:focus,
    .settings-field textarea:focus {
        border-color: #0969da;
        box-shadow: 0 0 0 3px rgba(9, 105, 218, 0.15);
    }

    .settings-code-textarea {
        min-height: 170px;
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
        font-size: 13px;
    }

    .settings-color-wrap {
        display: grid;
        grid-template-columns: 56px 1fr;
        gap: 8px;
        align-items: center;
    }

    .settings-color-wrap input[type="color"] {
        padding: 3px;
        height: 38px;
    }

    .settings-toggle {
        width: 52px;
        height: 30px;
        position: relative;
        display: inline-flex;
        align-items: center;
        cursor: pointer;
    }

    .settings-toggle input {
        display: none;
    }

    .settings-toggle span {
        position: absolute;
        inset: 0;
        border-radius: 999px;
        background: #d8dee4;
        transition: .15s ease;
    }

    .settings-toggle span::before {
        content: "";
        position: absolute;
        top: 4px;
        left: 4px;
        width: 22px;
        height: 22px;
        border-radius: 50%;
        background: #ffffff;
        box-shadow: 0 1px 2px rgba(27, 31, 36, 0.20);
        transition: .15s ease;
    }

    .settings-toggle input:checked + span {
        background: #2da44e;
    }

    .settings-toggle input:checked + span::before {
        transform: translateX(22px);
    }

    .settings-upload {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .settings-upload-thumb {
        width: 56px;
        height: 56px;
        flex: 0 0 56px;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 1px solid #d0d7de;
        border-radius: 8px;
        background: #f6f8fa;
        overflow: hidden;
    }

    .settings-upload-thumb img {
        width: 100%;
        height: 100%;
        object-fit: contain;
        padding: 5px;
    }

    .settings-upload-thumb span {
        color: #8c959f;
        font-size: 10px;
        font-weight: 700;
        text-align: center;
    }

    .settings-upload-controls {
        min-width: 0;
        flex: 1;
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
    }

    .settings-upload-btn-wrap {
        position: relative;
        display: inline-flex;
    }

    .settings-upload-input {
        position: absolute;
        inset: 0;
        z-index: 1;
        width: 100%;
        height: 100%;
        margin: 0;
        opacity: 0;
        cursor: pointer;
    }

    .settings-upload-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 32px;
        padding: 6px 12px;
        border-radius: 6px;
        border: 1px solid rgba(27, 31, 36, 0.15);
        background: #f6f8fa;
        color: #24292f;
        font-size: 12px;
        font-weight: 700;
        cursor: pointer;
        white-space: nowrap;
        pointer-events: none;
    }

    .settings-upload-btn-wrap:hover .settings-upload-btn {
        background: #eef1f4;
    }

    .settings-upload-input:focus-visible + .settings-upload-btn {
        outline: 2px solid var(--accent, #0969da);
        outline-offset: 2px;
    }

    .settings-upload-filename {
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        color: #57606a;
        font-size: 12px;
    }

    .settings-upload-view {
        color: #0969da;
        font-size: 12px;
        font-weight: 700;
        text-decoration: none;
        white-space: nowrap;
    }

    .settings-upload-view:hover {
        text-decoration: underline;
    }

    .settings-save-bar {
        position: sticky;
        bottom: 16px;
        z-index: 20;
        margin-top: 16px;
        padding: 12px;
        border: 1px solid #d0d7de;
        border-radius: 10px;
        background: rgba(255, 255, 255, 0.94);
        box-shadow: 0 8px 24px rgba(140, 149, 159, 0.22);
        backdrop-filter: blur(12px);
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        flex-wrap: wrap;
        transition: border-color .15s ease, background .15s ease;
    }

    .settings-save-bar.has-changes {
        border-color: rgba(191, 135, 0, 0.4);
        background: rgba(255, 248, 231, 0.96);
    }

    .settings-save-bar.has-changes span {
        color: #9a6700;
        font-weight: 700;
    }

    .settings-save-bar span {
        color: #57606a;
        font-size: 13px;
    }

    .settings-btn {
        min-height: 34px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 7px;
        padding: 7px 12px;
        border-radius: 6px;
        border: 1px solid rgba(27, 31, 36, 0.15);
        background: #f6f8fa;
        color: #24292f;
        font-size: 13px;
        font-weight: 700;
        text-decoration: none;
        cursor: pointer;
        box-shadow: 0 1px 0 rgba(27, 31, 36, 0.04);
    }

    .settings-btn:hover {
        background: #eef1f4;
        color: #24292f;
        text-decoration: none;
    }

    .settings-btn-primary {
        background: #2da44e;
        color: #ffffff;
    }

    .settings-btn-primary:hover {
        background: #1f883d;
        color: #ffffff;
    }

    .settings-preview-card {
        margin-top: 16px;
        border: 1px solid #d0d7de;
        border-radius: 10px;
        background: #ffffff;
        overflow: hidden;
    }

    .settings-preview-head {
        padding: 13px 14px;
        border-bottom: 1px solid #d0d7de;
        background: #f6f8fa;
        font-size: 14px;
        font-weight: 700;
        color: #24292f;
    }

    .settings-preview-body {
        padding: 14px;
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .settings-preview-logo {
        width: 70px;
        height: 70px;
        border: 1px dashed #d0d7de;
        border-radius: 10px;
        background: #f6f8fa;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
    }

    .settings-preview-logo img {
        width: 100%;
        height: 100%;
        object-fit: contain;
        padding: 8px;
    }

    .settings-preview-logo span {
        color: #57606a;
        font-weight: 800;
        font-size: 22px;
    }

    .settings-preview-body strong {
        display: block;
        color: #24292f;
        font-size: 16px;
        margin-bottom: 3px;
    }

    .settings-preview-body small {
        color: #57606a;
        font-size: 13px;
    }


    /* Premium robots.txt crawler controls */
    .settings-crawler-field {
        position: relative;
        padding: 14px;
        border: 1px solid #d8dee4;
        border-radius: 10px;
        background: linear-gradient(180deg, #ffffff 0%, #f6f8fa 100%);
        transition: border-color .15s ease, transform .15s ease, box-shadow .15s ease;
    }

    .settings-crawler-field:hover {
        border-color: #8c959f;
        box-shadow: 0 6px 18px rgba(140, 149, 159, .14);
        transform: translateY(-1px);
    }

    .crawler-card-head {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 12px;
    }

    .crawler-card-mark {
        width: 34px;
        height: 34px;
        flex: 0 0 34px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 9px;
        background: var(--accent-soft, #ddf4ff);
        border: 1px solid var(--accent-border, rgba(9, 105, 218, .16));
        color: var(--accent, #0969da);
        font-size: 13px;
        font-weight: 800;
    }

    .crawler-card-copy {
        min-width: 0;
        flex: 1;
    }

    .crawler-card-copy strong {
        display: block;
        overflow: hidden;
        color: #24292f;
        font-size: 13px;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .crawler-card-copy span {
        display: block;
        margin-top: 2px;
        color: #57606a;
        font-size: 11px;
        font-weight: 600;
    }

    .crawler-mode-badge {
        display: inline-flex;
        align-items: center;
        min-height: 22px;
        padding: 3px 7px;
        border-radius: 999px;
        font-size: 10px;
        font-weight: 800;
        white-space: nowrap;
    }

    .crawler-mode-allow {
        background: #dafbe1;
        color: #1a7f37;
    }

    .crawler-mode-block {
        background: #ffebe9;
        color: #cf222e;
    }

    .crawler-mode-follow {
        background: #ddf4ff;
        color: #0969da;
    }

    .crawler-mode-select {
        background: #ffffff;
        font-size: 13px;
        font-weight: 700;
    }

    .robots-summary {
        grid-column: 1 / -1;
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 10px;
        margin: 2px 0 4px;
    }

    .robots-summary-item {
        padding: 12px;
        border: 1px solid #d8dee4;
        border-radius: 9px;
        background: #f6f8fa;
    }

    .robots-summary-item strong {
        display: block;
        color: #24292f;
        font-size: 18px;
        line-height: 1;
    }

    .robots-summary-item span {
        display: block;
        margin-top: 5px;
        color: #57606a;
        font-size: 11px;
        font-weight: 700;
    }

    .robots-preview-card {
        margin-top: 18px;
        border: 1px solid #d0d7de;
        border-radius: 12px;
        overflow: hidden;
        background: #ffffff;
        box-shadow: 0 10px 26px rgba(140, 149, 159, .15);
    }

    .robots-preview-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 14px;
        flex-wrap: wrap;
        padding: 15px 16px;
        border-bottom: 1px solid #d0d7de;
        background: linear-gradient(135deg, #f6f8fa 0%, var(--accent-soft, #ddf4ff) 100%);
    }

    .robots-preview-title {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .robots-preview-icon {
        width: 34px;
        height: 34px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 9px;
        background: var(--accent, #0969da);
        color: #ffffff;
        font-size: 15px;
        font-weight: 800;
    }

    .robots-preview-title strong {
        display: block;
        color: #24292f;
        font-size: 14px;
    }

    .robots-preview-title span {
        display: block;
        margin-top: 3px;
        color: #57606a;
        font-size: 12px;
    }

    .robots-preview-code {
        max-height: 560px;
        margin: 0;
        padding: 18px;
        overflow: auto;
        background: #0d1117;
        color: #c9d1d9;
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
        font-size: 13px;
        line-height: 1.65;
        white-space: pre-wrap;
        word-break: break-word;
    }

    @media(max-width: 1100px) {
        .settings-layout {
            grid-template-columns: 1fr;
        }

        .settings-tabs {
            position: static;
        }

        .settings-tab-list {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    @media(max-width: 720px) {
        .settings-hero,
        .settings-section-body {
            padding: 14px;
        }

        .settings-grid,
        .settings-tab-list,
        .robots-summary {
            grid-template-columns: 1fr;
        }

        .settings-save-bar {
            bottom: 10px;
        }

        .settings-save-bar .settings-btn {
            width: 100%;
        }
    }
</style>

<div class="settings-page">
    <div class="settings-hero">
        <div class="settings-hero-top">
            <span class="settings-hero-icon">⚙️</span>

            <div>
                <h2>Site Settings</h2>
                <p>Manage website identity, contact information, social links, SEO, homepage content, directory controls, email, analytics, design, legal pages and security options.</p>

                <div class="settings-hero-stats">
                    <span class="settings-hero-stat"><strong><?= e((string)count($settings_schema)) ?></strong> Sections</span>
                    <span class="settings-hero-stat"><strong><?= e((string)count($all_fields)) ?></strong> Settings</span>
                </div>
            </div>
        </div>

        <div class="settings-hero-actions">
            <a href="dashboard.php" class="settings-btn">Dashboard</a>
            <a href="../index.php" target="_blank" rel="noopener" class="settings-btn">View Website</a>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="settings-alert success"><?= e($success) ?></div>
    <?php endif; ?>

    <?php if ($errors): ?>
        <div class="settings-alert error">
            <?php foreach ($errors as $error): ?>
                <div><?= e($error) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" id="siteSettingsForm">
        <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">

        <div class="settings-layout">
            <aside>
                <div class="settings-tabs">
                    <div class="settings-tabs-head">
                        <strong>Settings Menu</strong>
                        <span>Select a section to edit</span>
                    </div>

                    <div class="settings-tab-list">
                        <?php foreach ($settings_schema as $section_title => $section): ?>
                            <?php $tab_key = admin_settings_slug($section_title); ?>

                            <button
                                type="button"
                                class="settings-tab-btn <?= $active_tab === $tab_key ? 'active' : '' ?>"
                                data-tab="<?= e($tab_key) ?>"
                            >
                                <span class="settings-tab-icon"><?= e((string)$section['icon']) ?></span>
                                <span><?= e($section_title) ?></span>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="settings-preview-card">
                    <div class="settings-preview-head">Website Preview</div>

                    <div class="settings-preview-body">
                        <div class="settings-preview-logo">
                            <?php if (!empty($settings['site_logo'])): ?>
                                <img src="<?= e(admin_site_cache_bust_url($settings['site_logo'])) ?>" alt="<?= e($settings['site_name'] ?? APP_NAME) ?>">
                            <?php else: ?>
                                <span><?= e(strtoupper(substr((string)($settings['site_name'] ?? APP_NAME), 0, 1))) ?></span>
                            <?php endif; ?>
                        </div>

                        <div>
                            <strong><?= e($settings['site_name'] ?? APP_NAME) ?></strong>
                            <small><?= e($settings['site_tagline'] ?? 'Website tagline') ?></small>
                        </div>
                    </div>
                </div>
            </aside>

            <main class="settings-main">
                <?php foreach ($settings_schema as $section_title => $section): ?>
                    <?php $tab_key = admin_settings_slug($section_title); ?>

                    <section class="settings-section <?= $active_tab === $tab_key ? 'active' : '' ?>" data-tab-panel="<?= e($tab_key) ?>">
                        <div class="settings-section-head">
                            <span class="settings-section-icon"><?= e((string)$section['icon']) ?></span>
                            <div>
                                <h3><?= e($section_title) ?></h3>
                                <p><?= e((string)$section['description']) ?></p>
                            </div>
                        </div>

                        <div class="settings-section-body">
                            <div class="settings-grid">
                                <?php if ($section_title === 'Robots.txt Control'): ?>
                                    <?php
                                    $crawler_catalog = admin_robots_crawler_catalog();
                                    $allowed_count = 0;
                                    $blocked_count = 0;
                                    $follow_count = 0;

                                    foreach ($crawler_catalog as $crawler) {
                                        $mode = admin_robots_normalize_mode(
                                            (string)($settings[(string)$crawler['key']] ?? ''),
                                            (string)$crawler['default']
                                        );

                                        if ($mode === 'allow') {
                                            $allowed_count++;
                                        } elseif ($mode === 'block') {
                                            $blocked_count++;
                                        } else {
                                            $follow_count++;
                                        }
                                    }
                                    ?>
                                    <div class="robots-summary">
                                        <div class="robots-summary-item">
                                            <strong><?= e((string)$allowed_count) ?></strong>
                                            <span>Public Access Allowed</span>
                                        </div>
                                        <div class="robots-summary-item">
                                            <strong><?= e((string)$blocked_count) ?></strong>
                                            <span>Entire Site Blocked</span>
                                        </div>
                                        <div class="robots-summary-item">
                                            <strong><?= e((string)$follow_count) ?></strong>
                                            <span>Following Default Rule</span>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <?php foreach ($section['fields'] as $field): ?>
                                    <?php admin_render_settings_field($settings, $field); ?>
                                <?php endforeach; ?>
                            </div>

                            <?php if ($section_title === 'Robots.txt Control'): ?>
                                <div class="robots-preview-card">
                                    <div class="robots-preview-head">
                                        <div class="robots-preview-title">
                                            <span class="robots-preview-icon">R</span>
                                            <div>
                                                <strong>Generated robots.txt Preview</strong>
                                                <span>Full output based on your saved crawler and path settings.</span>
                                            </div>
                                        </div>

                                        <a href="../robots.txt" target="_blank" rel="noopener" class="settings-btn">
                                            Open Live robots.txt
                                        </a>
                                    </div>

                                    <pre class="robots-preview-code"><?= e($robots_preview) ?></pre>
                                </div>
                            <?php endif; ?>
                        </div>
                    </section>
                <?php endforeach; ?>

                <div class="settings-save-bar" id="settingsSaveBar">
                    <span id="settingsSaveBarText">After changing settings, click save to update your website configuration.</span>

                    <div class="settings-hero-actions">
                        <a href="dashboard.php" class="settings-btn">Cancel</a>
                        <button type="submit" class="settings-btn settings-btn-primary" id="settingsSaveBtn">Save Settings</button>
                    </div>
                </div>
            </main>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const buttons = document.querySelectorAll('.settings-tab-btn');
    const panels = document.querySelectorAll('.settings-section');

    buttons.forEach(function (button) {
        button.addEventListener('click', function () {
            const tab = button.getAttribute('data-tab');

            buttons.forEach(function (item) {
                item.classList.remove('active');
            });

            panels.forEach(function (panel) {
                panel.classList.remove('active');
            });

            button.classList.add('active');

            const activePanel = document.querySelector('[data-tab-panel="' + tab + '"]');

            if (activePanel) {
                activePanel.classList.add('active');
            }

            const url = new URL(window.location.href);
            url.searchParams.set('tab', tab);
            window.history.replaceState({}, '', url.toString());
        });
    });

    document.querySelectorAll('.settings-color-wrap').forEach(function (wrap) {
        const colorInput = wrap.querySelector('input[type="color"]');
        const textInput = wrap.querySelector('input[type="text"]');

        if (!colorInput || !textInput) {
            return;
        }

        colorInput.addEventListener('input', function () {
            textInput.value = colorInput.value;
        });

        textInput.addEventListener('input', function () {
            if (/^#[0-9A-Fa-f]{6}$/.test(textInput.value)) {
                colorInput.value = textInput.value;
            }
        });
    });

    document.querySelectorAll('.settings-upload-input').forEach(function (input) {
        const filenameEl = input.closest('.settings-upload-controls').querySelector('.settings-upload-filename');

        if (!filenameEl) {
            return;
        }

        input.addEventListener('change', function () {
            filenameEl.textContent = input.files && input.files.length > 0
                ? input.files[0].name
                : filenameEl.getAttribute('data-empty-text');
        });
    });

    const settingsForm = document.getElementById('siteSettingsForm');
    const saveBar = document.getElementById('settingsSaveBar');
    const saveBarText = document.getElementById('settingsSaveBarText');

    if (settingsForm && saveBar && saveBarText) {
        const defaultSaveBarText = saveBarText.textContent;

        settingsForm.addEventListener('input', function () {
            saveBar.classList.add('has-changes');
            saveBarText.textContent = 'You have unsaved changes.';
        });

        settingsForm.addEventListener('submit', function () {
            saveBar.classList.remove('has-changes');
            saveBarText.textContent = defaultSaveBarText;
        });
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
