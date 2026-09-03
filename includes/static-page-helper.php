<?php
/**
 * Dynamic Static Page Helpers
 *
 * Shared by the public static-page renderer and the admin page manager.
 */

if (!function_exists('medic_static_page_table_exists')) {
    function medic_static_page_table_exists(): bool
    {
        global $pdo;

        if (!isset($pdo) || !$pdo instanceof PDO) {
            return false;
        }

        try {
            $statement = $pdo->query(
                "SELECT COUNT(*)
                 FROM INFORMATION_SCHEMA.TABLES
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = 'static_pages'"
            );

            return (int) $statement->fetchColumn() > 0;
        } catch (Throwable $exception) {
            return false;
        }
    }
}

if (!function_exists('medic_static_page_setting')) {
    function medic_static_page_setting(string $key, string $default = ''): string
    {
        global $pdo;

        static $settings = null;

        if ($settings === null) {
            $settings = [];

            try {
                if (!isset($pdo) || !$pdo instanceof PDO) {
                    return $default;
                }

                $statement = $pdo->query(
                    "SELECT setting_key, setting_value
                     FROM site_settings"
                );

                foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $settings[(string) $row['setting_key']] = (string) $row['setting_value'];
                }
            } catch (Throwable $exception) {
                $settings = [];
            }
        }

        $value = trim((string) ($settings[$key] ?? ''));

        return $value !== '' ? $value : $default;
    }
}

if (!function_exists('medic_static_page_site_name')) {
    function medic_static_page_site_name(): string
    {
        return medic_static_page_setting('site_name', defined('APP_NAME') ? APP_NAME : 'Medic');
    }
}

if (!function_exists('medic_static_page_contact_email')) {
    function medic_static_page_contact_email(): string
    {
        return medic_static_page_setting(
            'contact_email',
            medic_static_page_setting('support_email', '')
        );
    }
}

if (!function_exists('medic_static_page_text')) {
    function medic_static_page_text(string $english = '', string $bangla = ''): string
    {
        if (defined('CURRENT_LANG') && CURRENT_LANG === 'bn' && trim($bangla) !== '') {
            return $bangla;
        }

        return $english;
    }
}

if (!function_exists('medic_static_page_url')) {
    function medic_static_page_url(string $slug = '', ?string $lang = null): string
    {
        $slug = trim($slug, '/');

        if (function_exists('front_url')) {
            return front_url($slug, $lang);
        }

        $lang = $lang ?: (defined('CURRENT_LANG') ? CURRENT_LANG : 'en');

        if ($lang === 'bn') {
            return site_url($slug === '' ? 'bn' : 'bn/' . $slug);
        }

        return site_url($slug);
    }
}

if (!function_exists('medic_static_page_length')) {
    function medic_static_page_length(string $text): int
    {
        return function_exists('mb_strlen')
            ? (int) mb_strlen($text, 'UTF-8')
            : strlen($text);
    }
}

if (!function_exists('medic_static_page_allowed_slugs')) {
    function medic_static_page_allowed_slugs(): array
    {
        return ['privacy-policy', 'terms', 'support'];
    }
}

if (!function_exists('medic_static_page_defaults')) {
    function medic_static_page_defaults(): array
    {
        return [
            [
                'slug' => 'privacy-policy',
                'title_en' => 'Privacy Policy',
                'title_bn' => 'গোপনীয়তা নীতি',
                'kicker_en' => 'How we handle your information',
                'kicker_bn' => 'আপনার তথ্য আমরা কীভাবে ব্যবহার করি',
                'intro_en' => 'This policy explains how %site_name% collects, uses and protects information when you use our website.',
                'intro_bn' => 'এই নীতিতে %site_name% ব্যবহার করার সময় আপনার তথ্য কীভাবে সংগ্রহ, ব্যবহার ও সুরক্ষিত রাখা হয় তা ব্যাখ্যা করা হয়েছে।',
                'content_en' => '<section class="medic-static-card"><h2>Information we collect</h2><p>We may collect information you provide directly, such as your name, email address, phone number, review, support request, account information or profile-claim details.</p></section><section class="medic-static-card"><h2>How we use information</h2><p>We use information to operate the directory, respond to requests, manage accounts, review profile claims, improve the service and maintain website security.</p></section><section class="medic-static-card"><h2>Directory information</h2><p>Doctor, hospital and appointment details may come from public sources, direct submissions or verified profile updates. Important information should be confirmed with the relevant provider before making a decision.</p></section><section class="medic-static-card"><h2>Contact</h2><p>For privacy questions, contact %support_email% or visit <a href="%support_url%">our support page</a>.</p></section>',
                'content_bn' => '<section class="medic-static-card"><h2>আমরা কোন তথ্য সংগ্রহ করি</h2><p>আপনার দেওয়া নাম, ইমেইল, ফোন নম্বর, রিভিউ, সাপোর্ট অনুরোধ, অ্যাকাউন্ট তথ্য বা প্রোফাইল দাবি সংক্রান্ত তথ্য সংগ্রহ করা হতে পারে।</p></section><section class="medic-static-card"><h2>তথ্য কীভাবে ব্যবহার করা হয়</h2><p>ডিরেক্টরি পরিচালনা, অনুরোধের উত্তর, অ্যাকাউন্ট ব্যবস্থাপনা, প্রোফাইল দাবি যাচাই, সেবা উন্নয়ন এবং ওয়েবসাইট নিরাপত্তার জন্য তথ্য ব্যবহার করা হয়।</p></section><section class="medic-static-card"><h2>ডিরেক্টরির তথ্য</h2><p>ডাক্তার, হাসপাতাল ও অ্যাপয়েন্টমেন্টের তথ্য প্রকাশ্য উৎস, সরাসরি জমা দেওয়া তথ্য বা যাচাইকৃত প্রোফাইল আপডেট থেকে আসতে পারে। গুরুত্বপূর্ণ সিদ্ধান্তের আগে সংশ্লিষ্ট সেবাদাতার সাথে তথ্য যাচাই করুন।</p></section><section class="medic-static-card"><h2>যোগাযোগ</h2><p>প্রাইভেসি সংক্রান্ত প্রশ্নের জন্য %support_email% এ যোগাযোগ করুন অথবা <a href="%support_url%">সাপোর্ট পেজে</a> যান।</p></section>',
                'meta_title_en' => 'Privacy Policy | %site_name%',
                'meta_title_bn' => 'গোপনীয়তা নীতি | %site_name%',
                'meta_description_en' => 'Read the Privacy Policy for %site_name% and learn how information is collected, used and protected.',
                'meta_description_bn' => '%site_name%-এর গোপনীয়তা নীতি পড়ুন এবং তথ্য কীভাবে সংগ্রহ, ব্যবহার ও সুরক্ষিত রাখা হয় তা জানুন।',
                'status' => 'active',
            ],
            [
                'slug' => 'terms',
                'title_en' => 'Terms of Use',
                'title_bn' => 'ব্যবহারের শর্তাবলি',
                'kicker_en' => 'Please read before using the site',
                'kicker_bn' => 'সাইট ব্যবহারের আগে পড়ুন',
                'intro_en' => 'These terms set out the rules for using %site_name% and its doctor, hospital and healthcare-directory services.',
                'intro_bn' => 'এই শর্তাবলিতে %site_name% এবং এর ডাক্তার, হাসপাতাল ও স্বাস্থ্যসেবা ডিরেক্টরি ব্যবহারের নিয়মগুলো বলা হয়েছে।',
                'content_en' => '<section class="medic-static-card"><h2>1. Acceptance of these terms</h2><p>By accessing or using %site_name%, you agree to these terms and our <a href="%privacy_url%">Privacy Policy</a>. Do not use the website if you do not agree with them.</p></section><section class="medic-static-card medic-static-note"><h2>2. Important medical disclaimer</h2><p>%site_name% provides directory and informational content only. It does not provide medical diagnosis, treatment, emergency advice or a replacement for professional healthcare. For urgent medical needs, contact an emergency service or a qualified healthcare professional immediately.</p></section><section class="medic-static-card"><h2>3. Directory information</h2><p>We aim to keep doctor, hospital, specialty, chamber and appointment information useful and current. Schedules, fees, availability, services and contact details can change. Confirm important information directly with the relevant provider.</p></section><section class="medic-static-card"><h2>4. Accounts, profile claims and submissions</h2><ul><li>Provide accurate information and keep account credentials secure.</li><li>Profile claims may require verification and do not guarantee approval or ownership.</li><li>Reviews, messages and other submissions must be truthful, lawful and respectful.</li></ul></section><section class="medic-static-card"><h2>5. Contact</h2><p>Questions about these terms can be sent to %support_email% or through <a href="%support_url%">our support page</a>.</p></section>',
                'content_bn' => '<section class="medic-static-card"><h2>১. শর্তাবলি গ্রহণ</h2><p>%site_name% ব্যবহার বা অ্যাক্সেস করার মাধ্যমে আপনি এই শর্তাবলি ও আমাদের <a href="%privacy_url%">গোপনীয়তা নীতিতে</a> সম্মত হচ্ছেন। সম্মত না হলে ওয়েবসাইট ব্যবহার করবেন না।</p></section><section class="medic-static-card medic-static-note"><h2>২. গুরুত্বপূর্ণ চিকিৎসা-সংক্রান্ত সতর্কতা</h2><p>%site_name% কেবল ডিরেক্টরি ও তথ্যভিত্তিক কনটেন্ট সরবরাহ করে। এটি চিকিৎসা নির্ণয়, চিকিৎসা, জরুরি পরামর্শ বা পেশাদার স্বাস্থ্যসেবার বিকল্প নয়। জরুরি চিকিৎসার প্রয়োজন হলে দ্রুত জরুরি সেবা বা যোগ্য স্বাস্থ্যসেবা পেশাজীবীর সাথে যোগাযোগ করুন।</p></section><section class="medic-static-card"><h2>৩. ডিরেক্টরির তথ্য</h2><p>ডাক্তার, হাসপাতাল, বিশেষজ্ঞতা, চেম্বার ও অ্যাপয়েন্টমেন্টের তথ্য সহায়ক ও হালনাগাদ রাখার চেষ্টা করা হয়। সময়সূচি, ফি, প্রাপ্যতা, সেবা এবং যোগাযোগের তথ্য পরিবর্তিত হতে পারে। গুরুত্বপূর্ণ তথ্য সংশ্লিষ্ট সেবাদাতার সাথে যাচাই করুন।</p></section><section class="medic-static-card"><h2>৪. অ্যাকাউন্ট, প্রোফাইল দাবি ও সাবমিশন</h2><ul><li>সঠিক তথ্য দিন এবং অ্যাকাউন্টের লগইন তথ্য নিরাপদ রাখুন।</li><li>প্রোফাইল দাবি যাচাইয়ের প্রয়োজন হতে পারে এবং দাবি অনুমোদন বা মালিকানা নিশ্চিত করে না।</li><li>রিভিউ, বার্তা ও অন্যান্য সাবমিশন সত্য, আইনসম্মত ও সম্মানজনক হতে হবে।</li></ul></section><section class="medic-static-card"><h2>৫. যোগাযোগ</h2><p>এই শর্তাবলি সম্পর্কে প্রশ্ন %support_email% এ পাঠাতে পারেন অথবা <a href="%support_url%">সাপোর্ট পেজে</a> যোগাযোগ করুন।</p></section>',
                'meta_title_en' => 'Terms of Use | %site_name%',
                'meta_title_bn' => 'ব্যবহারের শর্তাবলি | %site_name%',
                'meta_description_en' => 'Read the Terms of Use for %site_name%, including directory content, accounts, reviews and profile claims.',
                'meta_description_bn' => '%site_name% ব্যবহারের শর্তাবলি পড়ুন, যার মধ্যে ডিরেক্টরি কনটেন্ট, অ্যাকাউন্ট, রিভিউ ও প্রোফাইল দাবি অন্তর্ভুক্ত।',
                'status' => 'active',
            ],
            [
                'slug' => 'support',
                'title_en' => 'Support Center',
                'title_bn' => 'সাপোর্ট সেন্টার',
                'kicker_en' => 'We are here to help',
                'kicker_bn' => 'আমরা সাহায্য করতে প্রস্তুত',
                'intro_en' => 'Get help with doctor and hospital information, profile claims, appointment details and general website questions.',
                'intro_bn' => 'ডাক্তার ও হাসপাতালের তথ্য, প্রোফাইল দাবি, অ্যাপয়েন্টমেন্টের তথ্য এবং ওয়েবসাইট সংক্রান্ত সাধারণ সহায়তা নিন।',
                'content_en' => '<section class="medic-static-card"><h2>How to get support</h2><p>Send your question through the support form below. Please include enough detail for our team to understand and respond to your request.</p></section><section class="medic-static-card"><h2>Before you contact us</h2><ul><li>For appointment schedules, fees and availability, confirm details directly with the hospital or doctor.</li><li>For profile claims, provide accurate identification and relevant information.</li><li>Do not include highly sensitive health, financial or account-security information in a public message.</li></ul></section>',
                'content_bn' => '<section class="medic-static-card"><h2>কীভাবে সহায়তা পাবেন</h2><p>নিচের সাপোর্ট ফর্মের মাধ্যমে আপনার প্রশ্ন পাঠান। অনুরোধ বুঝতে এবং উত্তর দিতে প্রয়োজনীয় তথ্য দিন।</p></section><section class="medic-static-card"><h2>যোগাযোগের আগে</h2><ul><li>অ্যাপয়েন্টমেন্টের সময়সূচি, ফি ও প্রাপ্যতা সরাসরি হাসপাতাল বা ডাক্তারের সাথে যাচাই করুন।</li><li>প্রোফাইল দাবি করার ক্ষেত্রে সঠিক পরিচয় ও প্রাসঙ্গিক তথ্য দিন।</li><li>সার্বজনিক বার্তায় অতিরিক্ত সংবেদনশীল স্বাস্থ্য, আর্থিক বা অ্যাকাউন্ট নিরাপত্তার তথ্য দেবেন না।</li></ul></section>',
                'meta_title_en' => 'Support Center | %site_name%',
                'meta_title_bn' => 'সাপোর্ট সেন্টার | %site_name%',
                'meta_description_en' => 'Contact %site_name% support for help with doctors, hospitals, profile claims and appointment information.',
                'meta_description_bn' => 'ডাক্তার, হাসপাতাল, প্রোফাইল দাবি ও অ্যাপয়েন্টমেন্টের তথ্যের জন্য %site_name% সাপোর্টে যোগাযোগ করুন।',
                'status' => 'active',
            ],
        ];
    }
}

if (!function_exists('medic_static_page_default')) {
    function medic_static_page_default(string $slug): array
    {
        foreach (medic_static_page_defaults() as $page) {
            if ((string) $page['slug'] === $slug) {
                return $page;
            }
        }

        return [];
    }
}

if (!function_exists('medic_static_page_localized_value')) {
    function medic_static_page_localized_value(array $page, string $field): string
    {
        $suffix = defined('CURRENT_LANG') && CURRENT_LANG === 'bn' ? 'bn' : 'en';
        $localized = trim((string) ($page[$field . '_' . $suffix] ?? ''));
        $english = trim((string) ($page[$field . '_en'] ?? ''));

        return $localized !== '' ? $localized : $english;
    }
}

if (!function_exists('medic_static_page_get_by_slug')) {
    function medic_static_page_get_by_slug(string $slug, bool $activeOnly = true): ?array
    {
        global $pdo;

        $slug = strtolower(trim($slug));

        if ($slug === '' || !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
            return null;
        }

        if (medic_static_page_table_exists()) {
            try {
                $statement = $pdo->prepare(
                    'SELECT *
                     FROM static_pages
                     WHERE slug = :slug
                     LIMIT 1'
                );
                $statement->execute([':slug' => $slug]);
                $page = $statement->fetch(PDO::FETCH_ASSOC);

                if (is_array($page)) {
                    if ($activeOnly && (string) ($page['status'] ?? 'inactive') !== 'active') {
                        return null;
                    }

                    return $page;
                }
            } catch (Throwable $exception) {
                error_log('Static page fetch error: ' . $exception->getMessage());
            }
        }

        $default = medic_static_page_default($slug);

        if ($default === []) {
            return null;
        }

        return !$activeOnly || (string) ($default['status'] ?? 'inactive') === 'active'
            ? $default
            : null;
    }
}

if (!function_exists('medic_static_page_tokens')) {
    function medic_static_page_tokens(bool $htmlEscape = false): array
    {
        $values = [
            '%site_name%' => medic_static_page_site_name(),
            '%support_email%' => medic_static_page_contact_email(),
            '%support_url%' => medic_static_page_url('support'),
            '%privacy_url%' => medic_static_page_url('privacy-policy'),
            '%terms_url%' => medic_static_page_url('terms'),
        ];

        if ($htmlEscape) {
            foreach ($values as $token => $value) {
                $values[$token] = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
            }
        }

        return $values;
    }
}

if (!function_exists('medic_static_page_replace_tokens')) {
    function medic_static_page_replace_tokens(string $text, bool $htmlEscape = false): string
    {
        return strtr($text, medic_static_page_tokens($htmlEscape));
    }
}

if (!function_exists('medic_static_page_sanitize_html')) {
    function medic_static_page_sanitize_html(string $html): string
    {
        $html = trim($html);

        if ($html === '') {
            return '';
        }

        // Remove executable or embedded content before allowing safe formatting tags.
        $html = preg_replace(
            '#<\s*(script|style|iframe|object|embed|svg|math)[^>]*>.*?<\s*/\s*\1\s*>#is',
            '',
            $html
        );
        $html = strip_tags(
            (string) $html,
            '<section><p><h2><h3><h4><ul><ol><li><strong><b><em><i><a><br><blockquote><hr>'
        );

        // Remove event handlers, inline styles and all attributes except safe links/classes.
        $html = preg_replace('/\s+on[a-z0-9_-]+\s*=\s*(["\']).*?\1/is', '', $html);
        $html = preg_replace('/\s+on[a-z0-9_-]+\s*=\s*[^\s>]+/is', '', $html);
        $html = preg_replace('/\s+style\s*=\s*(["\']).*?\1/is', '', $html);
        $html = preg_replace_callback(
            '/<a\b([^>]*)>/i',
            static function (array $matches): string {
                $attributes = $matches[1] ?? '';
                $href = '';

                if (preg_match('/\bhref\s*=\s*(["\'])(.*?)\1/i', $attributes, $hrefMatch)) {
                    $candidate = trim(html_entity_decode($hrefMatch[2], ENT_QUOTES, 'UTF-8'));

                    if (
                        preg_match('#^(https?://|mailto:|tel:|/|\#)#i', $candidate)
                        || $candidate === ''
                    ) {
                        $href = $candidate;
                    }
                }

                if ($href === '') {
                    return '<a>';
                }

                $escapedHref = htmlspecialchars($href, ENT_QUOTES, 'UTF-8');
                $external = preg_match('#^https?://#i', $href) === 1;

                return '<a href="' . $escapedHref . '"' . ($external ? ' target="_blank" rel="noopener noreferrer"' : '') . '>';
            },
            $html
        );
        $html = preg_replace_callback(
            '/<section\b([^>]*)>/i',
            static function (array $matches): string {
                $attributes = $matches[1] ?? '';
                $class = '';

                if (preg_match('/\bclass\s*=\s*(["\'])(.*?)\1/i', $attributes, $classMatch)) {
                    $allowed = array_intersect(
                        preg_split('/\s+/', trim($classMatch[2])) ?: [],
                        ['medic-static-card', 'medic-static-note']
                    );
                    $class = implode(' ', $allowed);
                }

                return '<section' . ($class !== '' ? ' class="' . $class . '"' : '') . '>';
            },
            $html
        );

        return $html;
    }
}

if (!function_exists('medic_static_page_render_content')) {
    function medic_static_page_render_content(string $content): string
    {
        return medic_static_page_sanitize_html(
            medic_static_page_replace_tokens($content, true)
        );
    }
}
