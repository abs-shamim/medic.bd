<?php
/**
 * Image & Biography section component.
 * GitHub-style clean UI.
 * Overlap fixed.
 */

/*
|--------------------------------------------------------------------------
| Self-working safety helpers
|--------------------------------------------------------------------------
| This file can work as a standalone section inside admin/doctors/.
| It also stays compatible with the main doctor-form.php file.
*/
if (!function_exists('doctor_image_biography_e')) {
    function doctor_image_biography_e($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('e')) {
    function e($value): string
    {
        return doctor_image_biography_e($value);
    }
}

if (!function_exists('site_url')) {
    function site_url(string $path = ''): string
    {
        $path = ltrim($path, '/');
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

        return rtrim($scheme . '://' . $host, '/') . ($path !== '' ? '/' . $path : '');
    }
}

if (!function_exists('doctor_image_biography_safe_identifier')) {
    function doctor_image_biography_safe_identifier(string $name): string
    {
        return preg_replace('/[^a-zA-Z0-9_]/', '', $name) ?: '';
    }
}

if (!function_exists('doctor_component_table_exists')) {
    function doctor_component_table_exists(string $table): bool
    {
        global $pdo;

        try {
            $stmt = $pdo->prepare("
                SELECT COUNT(*)
                FROM INFORMATION_SCHEMA.TABLES
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = :table
            ");
            $stmt->execute([':table' => $table]);
            return (int)$stmt->fetchColumn() > 0;
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('doctor_component_column_exists')) {
    function doctor_component_column_exists(string $table, string $column): bool
    {
        global $pdo;

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
            return (int)$stmt->fetchColumn() > 0;
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('doctor_component_add_column_if_missing')) {
    function doctor_component_add_column_if_missing(string $table, string $column, string $definition): void
    {
        global $pdo;

        try {
            if (!isset($pdo) || !($pdo instanceof PDO)) {
                return;
            }

            $safe_table = function_exists('doctor_image_biography_safe_identifier')
                ? doctor_image_biography_safe_identifier($table)
                : preg_replace('/[^a-zA-Z0-9_]/', '', $table);

            $safe_column = function_exists('doctor_image_biography_safe_identifier')
                ? doctor_image_biography_safe_identifier($column)
                : preg_replace('/[^a-zA-Z0-9_]/', '', $column);

            if ($safe_table === '' || $safe_column === '') {
                return;
            }

            if (!doctor_component_table_exists($safe_table)) {
                return;
            }

            if (!doctor_component_column_exists($safe_table, $safe_column)) {
                $pdo->exec("ALTER TABLE `{$safe_table}` ADD COLUMN `{$safe_column}` {$definition}");
            }
        } catch (Throwable $e) {
            // Missing-column repair should never break section rendering.
        }
    }
}

/**
 * Unique CSS for this section.
 * This prevents conflict with other section files.
 */
if (!function_exists('doctor_image_biography_css')) {
    function doctor_image_biography_css(): void
    {
        static $printed = false;

        if ($printed) {
            return;
        }

        $printed = true;
        ?>
        <style>
            :root {
                --gh-card: #ffffff;
                --gh-text: #24292f;
                --gh-muted: #57606a;
                --gh-border: #d0d7de;
                --gh-blue: #0969da;
                --gh-blue-soft: #ddf4ff;
                --gh-green: #1a7f37;
                --gh-soft: #f6f8fa;
                --gh-shadow: 0 1px 0 rgba(27,31,36,.04);
            }

            .gh-imagebio-card,
            .gh-imagebio-card * {
                box-sizing: border-box;
            }

            .gh-imagebio-card {
                width: 100%;
                margin-bottom: 20px;
                border: 1px solid var(--gh-border);
                border-radius: 12px;
                background: var(--gh-card);
                box-shadow: var(--gh-shadow);
                overflow: hidden;
                color: var(--gh-text);
                font-family: inherit;
            }

            .gh-imagebio-head {
                display: flex;
                align-items: flex-start;
                justify-content: space-between;
                gap: 18px;
                padding: 18px 20px;
                background: linear-gradient(180deg, #ffffff 0%, #f6f8fa 100%);
                border-bottom: 1px solid var(--gh-border);
            }

            .gh-imagebio-title {
                display: flex;
                align-items: flex-start;
                gap: 12px;
                min-width: 0;
            }

            .gh-imagebio-icon {
                width: 36px;
                height: 36px;
                border-radius: 10px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                color: var(--gh-blue);
                background: var(--gh-blue-soft);
                border: 1px solid rgba(84,174,255,.45);
                font-size: 13px;
                font-weight: 800 !important;
                flex: 0 0 auto;
            }

            .gh-imagebio-title h3 {
                margin: 0;
                color: var(--gh-text);
                font-size: 18px;
                line-height: 1.35;
                font-weight: 700 !important;
                letter-spacing: -.02em;
            }

            .gh-imagebio-title p {
                margin: 4px 0 0;
                color: var(--gh-muted);
                font-size: 13px;
                line-height: 1.55;
            }

            .gh-imagebio-badge {
                display: inline-flex;
                align-items: center;
                gap: 7px;
                padding: 6px 10px;
                border-radius: 999px;
                background: #fff;
                border: 1px solid var(--gh-border);
                color: var(--gh-muted);
                font-size: 12px;
                white-space: nowrap;
                flex: 0 0 auto;
            }

            .gh-imagebio-badge-dot {
                width: 8px;
                height: 8px;
                border-radius: 50%;
                background: var(--gh-green);
            }

            .gh-imagebio-body {
                padding: 20px;
            }

            /*
             * Main layout.
             * This fixes the overwrite/overlap issue.
             */
            .gh-imagebio-main {
                display: grid;
                grid-template-columns: minmax(260px, 340px) minmax(0, 1fr);
                gap: 18px;
                align-items: start;
                width: 100%;
            }

            .gh-imagebio-main > * {
                min-width: 0;
            }

            .gh-image-side {
                min-width: 0;
            }

            .gh-bio-side {
                min-width: 0;
                display: grid;
                gap: 12px;
            }

            .gh-bio-grid {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 16px;
                align-items: start;
                width: 100%;
            }

            .gh-bio-field {
                display: grid;
                gap: 7px;
                min-width: 0;
            }

            .gh-image-upload-box {
                border: 1px solid var(--gh-border);
                border-radius: 12px;
                background: var(--gh-soft);
                padding: 14px;
                display: grid;
                gap: 12px;
                width: 100%;
                min-width: 0;
            }

            .gh-image-preview {
                width: 100%;
                min-height: 250px;
                max-height: 290px;
                border: 1px dashed #8c959f;
                border-radius: 10px;
                background: #fff;
                display: flex;
                align-items: center;
                justify-content: center;
                overflow: hidden;
                color: var(--gh-muted);
                font-size: 13px;
                line-height: 1.5;
                text-align: center;
                padding: 12px;
            }

            .gh-image-preview img {
                width: 100%;
                height: 100%;
                max-height: 265px;
                object-fit: cover;
                display: block;
                border-radius: 8px;
            }

            .gh-image-empty {
                display: grid;
                gap: 6px;
                place-items: center;
            }

            .gh-image-empty strong {
                color: var(--gh-text);
                font-size: 14px;
            }

            .gh-image-empty span {
                color: var(--gh-muted);
                font-size: 12.5px;
            }

            .gh-field {
                display: grid;
                gap: 7px;
                min-width: 0;
            }

            .gh-field label,
            .gh-bio-side label {
                color: #344054;
                font-size: 13.5px;
                font-weight: 600 !important;
                line-height: 1.35;
                margin: 0;
            }

            .gh-bio-lang-pill {
                display: inline-flex;
                width: fit-content;
                align-items: center;
                padding: 4px 8px;
                border-radius: 999px;
                background: var(--gh-blue-soft);
                color: var(--gh-blue);
                font-size: 11.5px;
                font-weight: 700 !important;
            }

            .gh-field input[type="file"] {
                width: 100%;
                min-height: 42px;
                border: 1px solid var(--gh-border);
                border-radius: 6px;
                padding: 9px 10px;
                color: var(--gh-text);
                background: #fff;
                outline: none;
                font-family: inherit;
                font-size: 13.5px;
                cursor: pointer;
            }

            .gh-field input[type="file"]:focus {
                border-color: var(--gh-blue);
                box-shadow: 0 0 0 3px rgba(9,105,218,.12);
            }

            .gh-bio-side textarea {
                display: block;
                width: 100%;
                min-width: 0;
                min-height: 370px;
                border: 1px solid var(--gh-border);
                border-radius: 6px;
                padding: 12px 13px;
                color: var(--gh-text);
                background: #fff;
                outline: none;
                font-family: inherit;
                font-size: 14px;
                line-height: 1.7;
                resize: vertical;
                box-shadow: inset 0 1px 0 rgba(208,215,222,.2);
            }

            .gh-bio-side textarea:focus {
                border-color: var(--gh-blue);
                box-shadow: 0 0 0 3px rgba(9,105,218,.12);
            }

            .gh-bio-side textarea::placeholder {
                color: #8c959f;
            }

            .gh-help-box {
                display: flex;
                align-items: flex-start;
                gap: 10px;
                padding: 11px 12px;
                border: 1px solid var(--gh-border);
                border-radius: 9px;
                background: #fff;
                color: var(--gh-muted);
                font-size: 12.5px;
                line-height: 1.55;
                min-width: 0;
            }

            .gh-help-icon {
                width: 18px;
                height: 18px;
                border-radius: 50%;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                background: var(--gh-blue-soft);
                color: var(--gh-blue);
                font-size: 12px;
                font-weight: 800 !important;
                flex: 0 0 auto;
                margin-top: 1px;
            }

            .gh-current-file {
                display: grid;
                grid-template-columns: minmax(0, 1fr) auto;
                align-items: center;
                gap: 10px;
                padding: 10px 12px;
                border: 1px solid rgba(84,174,255,.45);
                border-radius: 9px;
                background: var(--gh-blue-soft);
                color: #0550ae;
                font-size: 12.5px;
                line-height: 1.5;
                min-width: 0;
            }

            .gh-current-file span {
                min-width: 0;
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
            }

            .gh-current-file a {
                color: var(--gh-blue);
                text-decoration: none;
                font-weight: 700 !important;
                white-space: nowrap;
            }

            .gh-current-file a:hover {
                text-decoration: underline;
            }

            .gh-bio-counter {
                display: flex;
                justify-content: space-between;
                gap: 10px;
                color: var(--gh-muted);
                font-size: 12px;
                line-height: 1.5;
            }

            .gh-bio-counter strong {
                color: var(--gh-text);
            }

            @media (max-width: 1000px) {
                .gh-imagebio-main {
                    grid-template-columns: 1fr;
                }

                .gh-bio-grid {
                    grid-template-columns: 1fr;
                }

                .gh-bio-side textarea {
                    min-height: 230px;
                }

                .gh-image-preview {
                    min-height: 220px;
                }
            }

            @media (max-width: 900px) {
                .gh-imagebio-head {
                    flex-direction: column;
                    align-items: stretch;
                }

                .gh-imagebio-badge {
                    width: fit-content;
                }
            }
        </style>
        <?php
    }
}

if (!function_exists('doctor_image_biography_safe_filename')) {
    function doctor_image_biography_safe_filename(string $text): string
    {
        $text = strtolower(trim($text));
        $text = preg_replace('/[^a-z0-9]+/i', '-', $text);
        $text = trim((string)$text, '-');

        return $text !== '' ? $text : 'doctor';
    }
}

if (!function_exists('doctor_image_biography_create_image_resource')) {
    function doctor_image_biography_create_image_resource(string $file, string $mime)
    {
        if ($mime === 'image/jpeg') {
            return function_exists('imagecreatefromjpeg') ? @imagecreatefromjpeg($file) : false;
        }

        if ($mime === 'image/png') {
            return function_exists('imagecreatefrompng') ? @imagecreatefrompng($file) : false;
        }

        if ($mime === 'image/webp') {
            return function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($file) : false;
        }

        return false;
    }
}

if (!function_exists('doctor_image_biography_upload_named_image')) {
    function doctor_image_biography_upload_named_image(string $field, string $doctor_name, string $suffix = ''): string
    {
        if (
            empty($_FILES[$field]['name']) ||
            empty($_FILES[$field]['tmp_name']) ||
            !is_uploaded_file($_FILES[$field]['tmp_name'])
        ) {
            return '';
        }

        $info = @getimagesize($_FILES[$field]['tmp_name']);

        if (
            !$info ||
            empty($info['mime']) ||
            !in_array($info['mime'], ['image/jpeg', 'image/png', 'image/webp'], true)
        ) {
            return '';
        }

        $upload_dir = __DIR__ . '/../../assets/uploads/doctors/';

        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0775, true);
        }

        $base_name = doctor_image_biography_safe_filename($doctor_name);
        $suffix_part = $suffix !== '' ? '-' . doctor_image_biography_safe_filename($suffix) : '';
        $time_part = date('ymdHis');

        $source = doctor_image_biography_create_image_resource($_FILES[$field]['tmp_name'], $info['mime']);
        $can_make_webp = $source && function_exists('imagewebp') && function_exists('imagecreatetruecolor');

        if ($can_make_webp) {
            $source_width = (int)$info[0];
            $source_height = (int)$info[1];

            $max_width = $suffix === 'og' ? 1200 : 900;
            $max_height = $suffix === 'og' ? 630 : 900;

            $ratio = min(
                $max_width / max(1, $source_width),
                $max_height / max(1, $source_height),
                1
            );

            $target_width = max(1, (int)round($source_width * $ratio));
            $target_height = max(1, (int)round($source_height * $ratio));

            $canvas = imagecreatetruecolor($target_width, $target_height);

            imagealphablending($canvas, false);
            imagesavealpha($canvas, true);

            $transparent = imagecolorallocatealpha($canvas, 255, 255, 255, 127);
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

            $file_name = $base_name . $suffix_part . '-' . $time_part . '.webp';
            $target = $upload_dir . $file_name;

            if (imagewebp($canvas, $target, 82)) {
                imagedestroy($canvas);
                imagedestroy($source);

                return 'assets/uploads/doctors/' . $file_name;
            }

            imagedestroy($canvas);
            imagedestroy($source);
        }

        $ext = strtolower(pathinfo((string)$_FILES[$field]['name'], PATHINFO_EXTENSION));
        $ext = in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true) ? $ext : 'jpg';

        $file_name = $base_name . $suffix_part . '-' . $time_part . '.' . $ext;

        return move_uploaded_file($_FILES[$field]['tmp_name'], $upload_dir . $file_name)
            ? 'assets/uploads/doctors/' . $file_name
            : '';
    }
}

if (!defined('DOCTOR_FORM_MAIN_CONTEXT') && !function_exists('doctor_form_upload_named_image')) {
    function doctor_form_upload_named_image(string $field, string $doctor_name, string $suffix = ''): string
    {
        return doctor_image_biography_upload_named_image($field, $doctor_name, $suffix);
    }
}

if (!function_exists('doctor_image_biography_boot')) {
    function doctor_image_biography_boot(): void
    {
        foreach ([
            'image' => "VARCHAR(255) NULL",
            'og_image' => "VARCHAR(255) NULL",
            'bio' => "TEXT NULL",
            'bio_bn' => "TEXT NULL",
            'updated_at' => "DATETIME NULL",
        ] as $column => $definition) {
            doctor_component_add_column_if_missing('doctors', $column, $definition);
        }
    }
}

doctor_image_biography_boot();

if (!function_exists('doctor_image_biography_render')) {
    function doctor_image_biography_render(array $context = []): void
    {
        extract($context, EXTR_SKIP);

        doctor_image_biography_css();

        $doctor_image = trim((string)($doctor['image'] ?? ''));
        $doctor_bio = (string)($doctor['bio'] ?? '');
        $doctor_bio_bn = (string)($doctor['bio_bn'] ?? '');
        $has_image = $doctor_image !== '';
        $has_bio = trim($doctor_bio) !== '';
        $has_bio_bn = trim($doctor_bio_bn) !== '';
        $image_url = $has_image ? site_url($doctor_image) : '';
        ?>

        <div class="gh-imagebio-card">
            <div class="gh-imagebio-head">
                <div class="gh-imagebio-title">
                    <div class="gh-imagebio-icon">IMG</div>
                    <div>
                        <h3>Image & Biography</h3>
                        <p>Upload doctor photo and write a short profile biography for the public profile page.</p>
                    </div>
                </div>

                <div class="gh-imagebio-badge">
                    <span class="gh-imagebio-badge-dot"></span>
                    <span><?= $has_image ? 'Image uploaded' : 'Image empty' ?></span>
                </div>
            </div>

            <div class="gh-imagebio-body">
                <div class="gh-imagebio-main">
                    <div class="gh-image-side">
                        <div class="gh-image-upload-box">
                            <div class="gh-image-preview" id="doctorImagePreview">
                                <?php if ($has_image): ?>
                                    <img src="<?= e($image_url) ?>" alt="Doctor image preview">
                                <?php else: ?>
                                    <div class="gh-image-empty">
                                        <strong>No image uploaded</strong>
                                        <span>JPG, PNG or WebP accepted</span>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="gh-field">
                                <label>Doctor Image</label>
                                <input type="file" name="image" id="doctorImageInput" accept="image/jpeg,image/png,image/webp">
                            </div>

                            <div class="gh-help-box">
                                <span class="gh-help-icon">i</span>
                                <span>
                                    Images are resized and saved as optimized WebP when your server supports GD/WebP.
                                </span>
                            </div>

                            <?php if ($has_image): ?>
                                <div class="gh-current-file">
                                    <span><?= e($doctor_image) ?></span>
                                    <a href="<?= e($image_url) ?>" target="_blank">View</a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="gh-bio-side">
                        <div class="gh-bio-grid">
                            <div class="gh-bio-field">
                                <span class="gh-bio-lang-pill">English</span>
                                <label>Doctor Bio</label>
                                <textarea
                                    name="bio"
                                    id="doctorBioInput"
                                    placeholder="Write a clear doctor biography. Example: Dr. Ahmed Hasan is a highly experienced cardiologist with special interest in heart disease, hypertension and preventive cardiac care."
                                ><?= e($doctor_bio) ?></textarea>

                                <div class="gh-bio-counter">
                                    <span><?= $has_bio ? 'English biography added' : 'English biography empty' ?></span>
                                    <span>
                                        <strong id="doctorBioCount"><?= e((string)mb_strlen(trim($doctor_bio))) ?></strong> characters
                                    </span>
                                </div>
                            </div>

                            <div class="gh-bio-field">
                                <span class="gh-bio-lang-pill">Bangla</span>
                                <label>Doctor Bio Bangla</label>
                                <textarea
                                    name="bio_bn"
                                    id="doctorBioBnInput"
                                    placeholder="ডাক্তারের বাংলা জীবনী লিখুন। উদাহরণ: ডা. আহমেদ হাসান একজন অভিজ্ঞ হৃদরোগ বিশেষজ্ঞ..."
                                ><?= e($doctor_bio_bn) ?></textarea>

                                <div class="gh-bio-counter">
                                    <span><?= $has_bio_bn ? 'Bangla biography added' : 'Bangla biography empty' ?></span>
                                    <span>
                                        <strong id="doctorBioBnCount"><?= e((string)mb_strlen(trim($doctor_bio_bn))) ?></strong> characters
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <script>
            (function() {
                const imageInput = document.getElementById('doctorImageInput');
                const imagePreview = document.getElementById('doctorImagePreview');
                const bioInput = document.getElementById('doctorBioInput');
                const bioCount = document.getElementById('doctorBioCount');
                const bioBnInput = document.getElementById('doctorBioBnInput');
                const bioBnCount = document.getElementById('doctorBioBnCount');

                if (imageInput && imagePreview) {
                    imageInput.addEventListener('change', function() {
                        const file = this.files && this.files[0] ? this.files[0] : null;

                        if (!file) {
                            return;
                        }

                        if (!file.type.match(/^image\/(jpeg|png|webp)$/)) {
                            imagePreview.innerHTML = '<div class="gh-image-empty"><strong>Invalid image type</strong><span>Please upload JPG, PNG or WebP.</span></div>';
                            this.value = '';
                            return;
                        }

                        const reader = new FileReader();

                        reader.onload = function(event) {
                            imagePreview.innerHTML = '<img src="' + event.target.result + '" alt="New doctor image preview">';
                        };

                        reader.readAsDataURL(file);
                    });
                }

                if (bioInput && bioCount) {
                    bioInput.addEventListener('input', function() {
                        bioCount.textContent = this.value.trim().length;
                    });
                }

                if (bioBnInput && bioBnCount) {
                    bioBnInput.addEventListener('input', function() {
                        bioBnCount.textContent = this.value.trim().length;
                    });
                }
            })();
        </script>

        <?php
    }
}


if (!function_exists('doctor_image_biography_section_render')) {
    function doctor_image_biography_section_render(array $context = []): void
    {
        doctor_image_biography_render($context);
    }
}

if (!function_exists('doctor_image_render')) {
    function doctor_image_render(array $context = []): void
    {
        doctor_image_biography_render($context);
    }
}

if (!empty($GLOBALS['doctor_form_auto_render_sections'])) {
    doctor_image_biography_render($GLOBALS['doctor_form_context'] ?? []);
}