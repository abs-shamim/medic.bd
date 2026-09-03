<?php
/*
|--------------------------------------------------------------------------
| SEO
|--------------------------------------------------------------------------
*/

/*
|--------------------------------------------------------------------------
| Dynamic bilingual SEO helpers
|--------------------------------------------------------------------------
| The directory can run in English or Bangla without maintaining duplicate
| SEO templates. When CURRENT_LANG is bn, name_bn is used when available;
| otherwise the normal name column remains a safe fallback.
*/
function dp_current_seo_lang(): string
{
    return defined('CURRENT_LANG') && CURRENT_LANG === 'bn' ? 'bn' : 'en';
}

function dp_seo_row_name(array $row, string $lang, string $fallback = ''): string
{
    $preferred_keys = $lang === 'bn'
        ? ['name_bn', 'title_bn', 'label_bn', 'name']
        : ['name_en', 'title_en', 'label_en', 'name'];

    foreach ($preferred_keys as $key) {
        $value = trim((string)($row[$key] ?? ''));

        if ($value !== '') {
            return $value;
        }
    }

    return $fallback;
}

function dp_seo_location_name(array $district, array $thana = [], string $lang = ''): string
{
    $lang = $lang === '' ? dp_current_seo_lang() : $lang;
    $district_name = dp_seo_row_name(
        $district,
        $lang,
        $lang === 'bn' ? 'নির্বাচিত জেলা' : 'Selected District'
    );
    $thana_name = dp_seo_row_name($thana, $lang, '');

    if ($thana_name !== '' && !empty($district)) {
        return $thana_name . ', ' . $district_name;
    }

    return $district_name;
}


function dp_seo_specialty_doctor_label(string $specialty_name, string $lang): string
{
    $specialty_name = trim($specialty_name);

    if ($specialty_name === '') {
        return $lang === 'bn' ? 'বিশেষজ্ঞ ডাক্তার' : 'Specialist Doctors';
    }

    if ($lang === 'bn') {
        $has_doctor_word = function_exists('mb_stripos')
            ? mb_stripos($specialty_name, 'ডাক্তার', 0, 'UTF-8') !== false
            : str_contains($specialty_name, 'ডাক্তার');

        $has_specialist_word = function_exists('mb_stripos')
            ? mb_stripos($specialty_name, 'বিশেষজ্ঞ', 0, 'UTF-8') !== false
            : str_contains($specialty_name, 'বিশেষজ্ঞ');

        if ($has_doctor_word) {
            return $specialty_name;
        }

        if ($has_specialist_word) {
            return $specialty_name . ' ডাক্তার';
        }

        return $specialty_name . ' বিশেষজ্ঞ ডাক্তার';
    }

    return stripos($specialty_name, 'doctor') !== false
        ? $specialty_name
        : $specialty_name . ' Doctors';
}

/*
|--------------------------------------------------------------------------
| Dynamic English Specialty SEO Labels
|--------------------------------------------------------------------------
| Each specialty uses a natural plural doctor label for English headings and
| meta titles. The optional meta_suffix adds a relevant high-intent phrase,
| for example: Cardiologists in Dhaka | Heart Specialists | MedicBD.
| Slugs are used instead of category IDs, so the labels remain stable when
| specialties are reordered in the admin panel.
*/
function dp_seo_english_specialty_data(array $specialty): array
{
    $slug = trim((string)($specialty['slug'] ?? ''));

    /*
     * These values control English page headings and meta titles.
     * The labels and optional suffixes match the approved SEO title format:
     * Example: Cardiologists in Dhaka | Heart Specialists | MedicBD
     */
    $labels = [
        'alternative-medicine' => [
            'label' => 'Alternative Medicine Doctors',
            'meta_suffix' => '',
        ],
        'anesthesiology-pain-medicine' => [
            'label' => 'Anesthesiologists & Pain Specialists',
            'meta_suffix' => '',
        ],
        'cardiac-thoracic-surgery' => [
            'label' => 'Cardiac & Thoracic Surgeons',
            'meta_suffix' => '',
        ],
        'cardiology' => [
            'label' => 'Cardiologists',
            'meta_suffix' => 'Heart Specialists',
        ],
        'dentistry' => [
            'label' => 'Dentists',
            'meta_suffix' => 'Dental Specialists',
        ],
        'dermatology-venereology' => [
            'label' => 'Dermatologists & Skin Specialists',
            'meta_suffix' => '',
        ],
        'endocrinology-diabetes' => [
            'label' => 'Endocrinologists & Diabetes Specialists',
            'meta_suffix' => '',
        ],
        'ent' => [
            'label' => 'ENT Specialists',
            'meta_suffix' => 'Ear, Nose & Throat Doctors',
        ],
        'gastroenterology-hepatology' => [
            'label' => 'Gastroenterologists & Liver Specialists',
            'meta_suffix' => '',
        ],
        'general-surgery' => [
            'label' => 'General Surgeons',
            'meta_suffix' => '',
        ],
        'gynecology-obstetrics' => [
            'label' => 'Gynecologists & Obstetricians',
            'meta_suffix' => '',
        ],
        'hematology' => [
            'label' => 'Hematologists',
            'meta_suffix' => 'Blood Disease Specialists',
        ],
        'medicine-general-physician' => [
            'label' => 'Medicine Doctors & General Physicians',
            'meta_suffix' => '',
        ],
        'nephrology' => [
            'label' => 'Nephrologists',
            'meta_suffix' => 'Kidney Specialists',
        ],
        'neurology' => [
            'label' => 'Neurologists',
            'meta_suffix' => 'Brain & Nerve Specialists',
        ],
        'neurosurgery' => [
            'label' => 'Neurosurgeons',
            'meta_suffix' => 'Brain & Spine Surgeons',
        ],
        'nuclear-medicine' => [
            'label' => 'Nuclear Medicine Specialists',
            'meta_suffix' => '',
        ],
        'nutrition-dietetics' => [
            'label' => 'Nutritionists & Dietitians',
            'meta_suffix' => '',
        ],
        'oncology' => [
            'label' => 'Oncologists',
            'meta_suffix' => 'Cancer Specialists',
        ],
        'ophthalmology' => [
            'label' => 'Eye Specialists & Ophthalmologists',
            'meta_suffix' => '',
        ],
        'orthopedics' => [
            'label' => 'Orthopedic Doctors',
            'meta_suffix' => 'Bone & Joint Specialists',
        ],
        'pathology-laboratory-medicine' => [
            'label' => 'Pathologists & Laboratory Medicine Specialists',
            'meta_suffix' => '',
        ],
        'pediatric-surgery' => [
            'label' => 'Pediatric Surgeons',
            'meta_suffix' => 'Child Surgery Specialists',
        ],
        'pediatrics' => [
            'label' => 'Child Specialists & Pediatricians',
            'meta_suffix' => '',
        ],
        'physical-medicine-rehabilitation' => [
            'label' => 'Physical Medicine & Rehabilitation Specialists',
            'meta_suffix' => '',
        ],
        'psychiatry-mental-health' => [
            'label' => 'Psychiatrists & Mental Health Specialists',
            'meta_suffix' => '',
        ],
        'pulmonology-respiratory-medicine' => [
            'label' => 'Pulmonologists & Respiratory Specialists',
            'meta_suffix' => '',
        ],
        'radiology-imaging' => [
            'label' => 'Radiologists & Imaging Specialists',
            'meta_suffix' => '',
        ],
        'reproductive-medicine-infertility' => [
            'label' => 'Infertility & Reproductive Medicine Specialists',
            'meta_suffix' => '',
        ],
        'rheumatology' => [
            'label' => 'Rheumatologists',
            'meta_suffix' => 'Arthritis Specialists',
        ],
        'urology' => [
            'label' => 'Urologists',
            'meta_suffix' => 'Urinary & Kidney Specialists',
        ],
    ];

    if ($slug !== '' && isset($labels[$slug])) {
        return $labels[$slug];
    }

    $specialty_name = dp_seo_row_name($specialty, 'en', 'Specialist');

    return [
        'label' => dp_seo_specialty_doctor_label($specialty_name, 'en'),
        'meta_suffix' => '',
    ];
}

function dp_seo_english_specialty_label(array $specialty): string
{
    $data = dp_seo_english_specialty_data($specialty);

    return trim((string)($data['label'] ?? 'Specialist Doctors'));
}

function dp_seo_english_specialty_meta_suffix(array $specialty): string
{
    $data = dp_seo_english_specialty_data($specialty);

    return trim((string)($data['meta_suffix'] ?? ''));
}

function dp_seo_english_doctor_list_meta_title(
    array $district,
    array $thana,
    array $specialty,
    string $site_name
): string {
    $specialty_label = dp_seo_english_specialty_label($specialty);
    $location_name = dp_seo_location_name($district, $thana, 'en');
    $meta_suffix = dp_seo_english_specialty_meta_suffix($specialty);

    $title = $location_name !== '' && !empty($district)
        ? $specialty_label . ' in ' . $location_name
        : $specialty_label . ' in Bangladesh';

    if ($meta_suffix !== '') {
        $title .= ' | ' . $meta_suffix;
    }

    return $title . ' | ' . $site_name;
}

/*
|--------------------------------------------------------------------------
| Dynamic Bangla Specialty SEO Labels
|--------------------------------------------------------------------------
| Uses the specialty slug rather than the database ID. Bangla labels are
| written for natural page headings and meta titles, while the optional
| suffix mirrors the extra English SEO phrase where it adds search value.
*/
function dp_seo_bangla_specialty_data(array $specialty): array
{
    $slug = trim((string)($specialty['slug'] ?? ''));

    $labels = [
        'alternative-medicine' => [
            'label' => 'বিকল্প চিকিৎসা বিশেষজ্ঞ ডাক্তার',
            'meta_suffix' => '',
        ],
        'anesthesiology-pain-medicine' => [
            'label' => 'অ্যানেস্থেসিওলজি ও ব্যথা চিকিৎসা বিশেষজ্ঞ ডাক্তার',
            'meta_suffix' => '',
        ],
        'cardiac-thoracic-surgery' => [
            'label' => 'কার্ডিয়াক ও থোরাসিক সার্জন',
            'meta_suffix' => '',
        ],
        'cardiology' => [
            'label' => 'হৃদরোগ বিশেষজ্ঞ ডাক্তার',
            'meta_suffix' => 'হার্ট স্পেশালিস্ট',
        ],
        'dentistry' => [
            'label' => 'দন্ত চিকিৎসক',
            'meta_suffix' => 'ডেন্টাল বিশেষজ্ঞ',
        ],
        'dermatology-venereology' => [
            'label' => 'চর্ম ও ত্বক রোগ বিশেষজ্ঞ ডাক্তার',
            'meta_suffix' => '',
        ],
        'endocrinology-diabetes' => [
            'label' => 'হরমোন ও ডায়াবেটিস বিশেষজ্ঞ ডাক্তার',
            'meta_suffix' => '',
        ],
        'ent' => [
            'label' => 'নাক, কান ও গলা বিশেষজ্ঞ ডাক্তার',
            'meta_suffix' => 'ইএনটি ডাক্তার',
        ],
        'gastroenterology-hepatology' => [
            'label' => 'পেট ও লিভার বিশেষজ্ঞ ডাক্তার',
            'meta_suffix' => '',
        ],
        'general-surgery' => [
            'label' => 'জেনারেল সার্জন',
            'meta_suffix' => '',
        ],
        'gynecology-obstetrics' => [
            'label' => 'স্ত্রীরোগ ও প্রসূতি বিশেষজ্ঞ ডাক্তার',
            'meta_suffix' => '',
        ],
        'hematology' => [
            'label' => 'রক্তরোগ বিশেষজ্ঞ ডাক্তার',
            'meta_suffix' => 'রক্ত রোগের বিশেষজ্ঞ',
        ],
        'medicine-general-physician' => [
            'label' => 'মেডিসিন ও জেনারেল ফিজিশিয়ান',
            'meta_suffix' => '',
        ],
        'nephrology' => [
            'label' => 'কিডনি রোগ বিশেষজ্ঞ ডাক্তার',
            'meta_suffix' => 'কিডনি বিশেষজ্ঞ',
        ],
        'neurology' => [
            'label' => 'স্নায়ু ও মস্তিষ্ক রোগ বিশেষজ্ঞ ডাক্তার',
            'meta_suffix' => 'ব্রেইন ও নার্ভ স্পেশালিস্ট',
        ],
        'neurosurgery' => [
            'label' => 'নিউরোসার্জন',
            'meta_suffix' => 'মস্তিষ্ক ও মেরুদণ্ড সার্জন',
        ],
        'nuclear-medicine' => [
            'label' => 'নিউক্লিয়ার মেডিসিন বিশেষজ্ঞ ডাক্তার',
            'meta_suffix' => '',
        ],
        'nutrition-dietetics' => [
            'label' => 'পুষ্টিবিদ ও ডায়েটিশিয়ান',
            'meta_suffix' => '',
        ],
        'oncology' => [
            'label' => 'ক্যান্সার বিশেষজ্ঞ ডাক্তার',
            'meta_suffix' => 'ক্যান্সার রোগ বিশেষজ্ঞ',
        ],
        'ophthalmology' => [
            'label' => 'চক্ষু বিশেষজ্ঞ ও অপথালমোলজিস্ট',
            'meta_suffix' => '',
        ],
        'orthopedics' => [
            'label' => 'অর্থোপেডিক বিশেষজ্ঞ ডাক্তার',
            'meta_suffix' => 'হাড় ও জয়েন্ট বিশেষজ্ঞ',
        ],
        'pathology-laboratory-medicine' => [
            'label' => 'প্যাথলজি ও ল্যাবরেটরি মেডিসিন বিশেষজ্ঞ',
            'meta_suffix' => '',
        ],
        'pediatric-surgery' => [
            'label' => 'শিশু সার্জন',
            'meta_suffix' => 'শিশু সার্জারি বিশেষজ্ঞ',
        ],
        'pediatrics' => [
            'label' => 'শিশু রোগ বিশেষজ্ঞ ও শিশু বিশেষজ্ঞ ডাক্তার',
            'meta_suffix' => '',
        ],
        'physical-medicine-rehabilitation' => [
            'label' => 'শারীরিক চিকিৎসা ও পুনর্বাসন বিশেষজ্ঞ',
            'meta_suffix' => '',
        ],
        'psychiatry-mental-health' => [
            'label' => 'মনোরোগ ও মানসিক স্বাস্থ্য বিশেষজ্ঞ ডাক্তার',
            'meta_suffix' => '',
        ],
        'pulmonology-respiratory-medicine' => [
            'label' => 'বক্ষব্যাধি ও শ্বাসতন্ত্র বিশেষজ্ঞ ডাক্তার',
            'meta_suffix' => '',
        ],
        'radiology-imaging' => [
            'label' => 'রেডিওলজি ও ইমেজিং বিশেষজ্ঞ ডাক্তার',
            'meta_suffix' => '',
        ],
        'reproductive-medicine-infertility' => [
            'label' => 'বন্ধ্যাত্ব ও প্রজনন চিকিৎসা বিশেষজ্ঞ ডাক্তার',
            'meta_suffix' => '',
        ],
        'rheumatology' => [
            'label' => 'রিউমাটোলজিস্ট',
            'meta_suffix' => 'বাত ও আর্থ্রাইটিস বিশেষজ্ঞ',
        ],
        'urology' => [
            'label' => 'ইউরোলজিস্ট',
            'meta_suffix' => 'মূত্র ও কিডনি রোগ বিশেষজ্ঞ',
        ],
    ];

    if ($slug !== '' && isset($labels[$slug])) {
        return $labels[$slug];
    }

    $specialty_name = dp_seo_row_name($specialty, 'bn', 'বিশেষজ্ঞ');

    return [
        'label' => dp_seo_specialty_doctor_label($specialty_name, 'bn'),
        'meta_suffix' => '',
    ];
}

function dp_seo_bangla_specialty_label(array $specialty): string
{
    $data = dp_seo_bangla_specialty_data($specialty);

    return trim((string)($data['label'] ?? 'বিশেষজ্ঞ ডাক্তার'));
}

function dp_seo_bangla_specialty_meta_suffix(array $specialty): string
{
    $data = dp_seo_bangla_specialty_data($specialty);

    return trim((string)($data['meta_suffix'] ?? ''));
}

/*
|--------------------------------------------------------------------------
| Bangla Location Grammar Helpers
|--------------------------------------------------------------------------
| Converts a normal location name to a possessive phrase used by natural
| Bangla titles, such as ঢাকা -> ঢাকার and চট্টগ্রাম -> চট্টগ্রামের.
*/
function dp_seo_bangla_possessive_location(string $location): string
{
    $location = trim($location);

    if ($location === '') {
        return 'নির্বাচিত এলাকার';
    }

    /* Do not add a suffix twice when the supplied label is already possessive. */
    if (preg_match('/(?:ার|ের|ীর|য়ের|য়ের)$/u', $location)) {
        return $location;
    }

    if (preg_match('/[ািীুূেৈোৌ]$/u', $location)) {
        return $location . 'র';
    }

    return $location . 'ের';
}

/*
|--------------------------------------------------------------------------
| Bangla Location Title Prefix
|--------------------------------------------------------------------------
| District page: ঢাকা -> ঢাকার
| Thana page: ধানমন্ডি, ঢাকা -> ধানমন্ডি, ঢাকার
*/
function dp_seo_bangla_location_title_prefix(array $district, array $thana = []): string
{
    $district_name = dp_seo_row_name($district, 'bn', 'নির্বাচিত এলাকা');
    $thana_name = dp_seo_row_name($thana, 'bn', '');
    $district_possessive = dp_seo_bangla_possessive_location($district_name);

    if ($thana_name !== '') {
        return $thana_name . ', ' . $district_possessive;
    }

    return $district_possessive;
}



function dp_seo_bangla_doctor_list_meta_title(
    array $district,
    array $thana,
    array $specialty,
    string $site_name
): string {
    $specialty_label = dp_seo_bangla_specialty_label($specialty);
    $meta_suffix = dp_seo_bangla_specialty_meta_suffix($specialty);

    $title = !empty($district)
        ? dp_seo_bangla_location_title_prefix($district, $thana) . ' ' . $specialty_label
        : 'বাংলাদেশের ' . $specialty_label;

    if ($meta_suffix !== '') {
        $title .= ' | ' . $meta_suffix;
    }

    return $title . ' | ' . $site_name;
}

/*
|--------------------------------------------------------------------------
| Specialty alternate names for meta descriptions
|--------------------------------------------------------------------------
| Uses every saved alternate name. Bangla pages prefer alternate_names_bn;
| English pages prefer alternate_names. The other field is a fallback.
*/
function dp_seo_specialty_alternate_names(array $specialty, string $lang): string
{
    $primary_key = $lang === 'bn'
        ? 'alternate_names_bn'
        : 'alternate_names';

    $fallback_key = $lang === 'bn'
        ? 'alternate_names'
        : 'alternate_names_bn';

    $raw_names = trim((string)($specialty[$primary_key] ?? ''));

    if ($raw_names === '') {
        $raw_names = trim((string)($specialty[$fallback_key] ?? ''));
    }

    if ($raw_names === '') {
        return '';
    }

    $items = preg_split('/\s*,\s*/u', $raw_names, -1, PREG_SPLIT_NO_EMPTY);

    if (!is_array($items) || empty($items)) {
        return '';
    }

    $items = array_values(array_unique(array_filter(array_map(
        'trim',
        $items
    ), static function ($item) {
        return $item !== '';
    })));

    return implode(', ', $items);
}

function dp_page_title(
    string $page_step,
    array $division,
    array $district,
    array $thana,
    array $specialty,
    array $filters
): string {
    $site_name = dp_site_name();
    $lang = dp_current_seo_lang();

    $division_name = dp_seo_row_name(
        $division,
        $lang,
        $lang === 'bn' ? 'নির্বাচিত বিভাগ' : 'Selected Division'
    );
    $district_name = dp_seo_row_name(
        $district,
        $lang,
        $lang === 'bn' ? 'নির্বাচিত জেলা' : 'Selected District'
    );
    $specialty_name = dp_seo_row_name(
        $specialty,
        $lang,
        $lang === 'bn' ? 'বিশেষজ্ঞ' : 'Specialist'
    );
    $location_name = dp_seo_location_name($district, $thana, $lang);
    $search = trim((string)($filters['search'] ?? ''));

    if ($page_step === 'division') {
        return $lang === 'bn'
            ? 'বাংলাদেশের ডাক্তার তালিকা | ' . $site_name
            : 'Find Doctors in Bangladesh | ' . $site_name;
    }

    if ($page_step === 'district') {
        return $lang === 'bn'
            ? $division_name . ' বিভাগের জেলা অনুযায়ী ডাক্তার | ' . $site_name
            : 'Doctors by District in ' . $division_name . ' | ' . $site_name;
    }

    if ($page_step === 'specialty') {
        return $lang === 'bn'
            ? $district_name . ' জেলার বিশেষজ্ঞ ডাক্তার | ' . $site_name
            : 'Specialist Doctors in ' . $district_name . ' | ' . $site_name;
    }

    if ($page_step === 'thana_specialty') {
        return $lang === 'bn'
            ? $location_name . ' এলাকার বিশেষজ্ঞ ডাক্তার | ' . $site_name
            : 'Specialist Doctors in ' . $location_name . ' | ' . $site_name;
    }

    if ($page_step === 'doctor_list') {
        /*
         * Alternate names are used in both Bangla and English automatic meta
         * descriptions. When no alternate names are saved, each language uses
         * its existing natural specialty label as the fallback.
         */
        $specialty_doctor_label = dp_seo_specialty_doctor_label($specialty_name, $lang);

        if ($search !== '') {
            return $lang === 'bn'
                ? $search . ' ডাক্তার খোঁজার ফলাফল | ' . $site_name
                : 'Search Results for ' . $search . ' | ' . $site_name;
        }

        if (!empty($specialty) && empty($district)) {
            return $lang === 'bn'
                ? dp_seo_bangla_doctor_list_meta_title([], [], $specialty, $site_name)
                : dp_seo_english_doctor_list_meta_title([], [], $specialty, $site_name);
        }

        if (!empty($district) && empty($specialty)) {
            return $lang === 'bn'
                ? dp_seo_bangla_location_title_prefix($district, $thana) . ' ডাক্তার তালিকা | ' . $site_name
                : 'Doctors in ' . $location_name . ' | ' . $site_name;
        }

        return $lang === 'bn'
            ? dp_seo_bangla_doctor_list_meta_title($district, $thana, $specialty, $site_name)
            : dp_seo_english_doctor_list_meta_title($district, $thana, $specialty, $site_name);
    }

    if ($page_step === 'not_found') {
        return $lang === 'bn'
            ? 'পৃষ্ঠা পাওয়া যায়নি | ' . $site_name
            : 'Page Not Found | ' . $site_name;
    }

    return $site_name;
}

function dp_page_description(
    string $page_step,
    array $division,
    array $district,
    array $thana,
    array $specialty,
    array $filters = []
): string {
    $lang = dp_current_seo_lang();
    $division_name = dp_seo_row_name($division, $lang, $lang === 'bn' ? 'এই বিভাগ' : 'this division');
    $district_name = dp_seo_row_name($district, $lang, $lang === 'bn' ? 'এই জেলা' : 'this district');
    $specialty_name = dp_seo_row_name($specialty, $lang, $lang === 'bn' ? 'বিশেষজ্ঞ' : 'specialist');
    $specialty_alternate_names = dp_seo_specialty_alternate_names($specialty, $lang);
    $location_name = dp_seo_location_name($district, $thana, $lang);
    $search = trim((string)($filters['search'] ?? ''));

    if ($page_step === 'division') {
        return $lang === 'bn'
            ? 'বাংলাদেশের বিভাগ, জেলা, থানা ও বিশেষজ্ঞতা অনুযায়ী অভিজ্ঞ ডাক্তার খুঁজুন। চেম্বার, হাসপাতাল, অ্যাপয়েন্টমেন্ট ও যোগাযোগের তথ্য দেখুন।'
            : 'Find experienced doctors across Bangladesh by division, district, area and specialty. View chamber, hospital, appointment and contact information.';
    }

    if ($page_step === 'district') {
        return $lang === 'bn'
            ? $division_name . ' বিভাগের জেলা নির্বাচন করে বিশেষজ্ঞ ডাক্তার, হাসপাতাল, চেম্বার এবং অ্যাপয়েন্টমেন্টের তথ্য খুঁজুন।'
            : 'Choose a district in ' . $division_name . ' to find specialist doctors, hospital details, chamber information and appointments.';
    }

    if ($page_step === 'specialty') {
        return $lang === 'bn'
            ? $district_name . ' জেলার বিভিন্ন বিশেষজ্ঞ ডাক্তার খুঁজুন। ডাক্তারদের চেম্বার, হাসপাতাল, ভিজিটিং সময় ও অ্যাপয়েন্টমেন্ট তথ্য দেখুন।'
            : 'Find specialist doctors in ' . $district_name . '. Browse chamber locations, hospitals, visiting hours and appointment details.';
    }

    if ($page_step === 'thana_specialty') {
        return $lang === 'bn'
            ? $location_name . ' এলাকার বিশেষজ্ঞ ডাক্তার খুঁজুন। চেম্বার, হাসপাতাল, ভিজিটিং সময় এবং অ্যাপয়েন্টমেন্ট তথ্য দেখুন।'
            : 'Find specialist doctors in ' . $location_name . '. Explore hospital, chamber, visiting-hour and appointment information.';
    }

    if ($page_step === 'doctor_list') {
        $specialty_doctor_label = dp_seo_specialty_doctor_label($specialty_name, $lang);

        if ($search !== '') {
            return $lang === 'bn'
                ? $search . ' ডাক্তার খোঁজার ফলাফল দেখুন। বিশেষজ্ঞতা, চেম্বার, হাসপাতাল, ভিজিটিং সময় এবং অ্যাপয়েন্টমেন্টের তথ্য পাওয়া যাবে।'
                : 'Browse doctor search results for ' . $search . ', including specialty, chamber, hospital and appointment information.';
        }

        if (!empty($specialty) && empty($district)) {
            $description_specialty_name = $specialty_alternate_names !== ''
                ? $specialty_alternate_names
                : ($lang === 'bn'
                    ? $specialty_name
                    : dp_seo_english_specialty_label($specialty));

            return $lang === 'bn'
                ? 'বাংলাদেশের ' . $description_specialty_name . ' ডাক্তারদের চেম্বার, হাসপাতাল, ভিজিটিং সময় ও অ্যাপয়েন্টমেন্ট তথ্য দেখুন।'
                : 'Find ' . $description_specialty_name . ' in Bangladesh with chamber, hospital, visiting-hour and appointment details.';
        }

        if (!empty($district) && empty($specialty)) {
            return $lang === 'bn'
                ? $location_name . ' ডাক্তার তালিকায় বিভিন্ন বিশেষজ্ঞ ডাক্তার, চেম্বার, হাসপাতাল, ভিজিটিং সময় ও অ্যাপয়েন্টমেন্টের তথ্য দেখুন।'
                : 'Browse doctors in ' . $location_name . ' with specialty, chamber, hospital and appointment information.';
        }

        $description_specialty_name = $specialty_alternate_names !== ''
            ? $specialty_alternate_names
            : ($lang === 'bn'
                ? $specialty_name
                : dp_seo_english_specialty_label($specialty));

        if ($lang === 'bn') {
            return dp_seo_bangla_location_title_prefix($district, $thana) . ' '
                . $description_specialty_name
                . ' ডাক্তারদের চেম্বার, হাসপাতাল, ভিজিটিং সময় ও অ্যাপয়েন্টমেন্ট তথ্য দেখুন।';
        }

        return 'Find ' . $description_specialty_name . ' in ' . $location_name
            . ' with chamber, hospital, visiting-hour and appointment details.';
    }

    if ($page_step === 'not_found') {
        return $lang === 'bn'
            ? 'আপনি যে পৃষ্ঠাটি খুঁজছেন সেটি পাওয়া যায়নি। ডাক্তার খুঁজতে ডক্টর ডিরেক্টরিতে ফিরে যান।'
            : 'The page you requested was not found. Return to the doctor directory to find doctors by location and specialty.';
    }

    return $lang === 'bn'
        ? 'বাংলাদেশে বিশেষজ্ঞ ডাক্তার, হাসপাতাল, চেম্বার এবং অ্যাপয়েন্টমেন্টের তথ্য খুঁজুন।'
        : 'Find doctors, hospitals, chambers and appointment information in Bangladesh.';
}

function dp_auto_article_location_name(array $district, array $thana = [], string $lang = ''): string
{
    $lang = $lang === '' ? dp_current_seo_lang() : $lang;

    return dp_seo_location_name($district, $thana, $lang);
}

function dp_auto_article_title(array $district, array $thana, array $specialty, string $lang): string
{
    $specialty_name = dp_seo_row_name(
        $specialty,
        $lang,
        $lang === 'bn' ? 'বিশেষজ্ঞ' : 'Specialist'
    );
    $specialty_doctor_label = dp_seo_specialty_doctor_label($specialty_name, $lang);
    $location = dp_auto_article_location_name($district, $thana, $lang);

    if ($lang === 'bn') {
        $bangla_specialty_label = dp_seo_bangla_specialty_label($specialty);

        return $location !== ''
            ? dp_seo_bangla_location_title_prefix($district, $thana)
                . ' '
                . $bangla_specialty_label
                . ' | তালিকা ও অ্যাপয়েন্টমেন্ট তথ্য'
            : 'বাংলাদেশের ' . $bangla_specialty_label . ' | তালিকা ও অ্যাপয়েন্টমেন্ট তথ্য';
    }

    $english_specialty_label = dp_seo_english_specialty_label($specialty);

    return $location !== ''
        ? $english_specialty_label . ' in ' . $location
        : $english_specialty_label . ' in Bangladesh';
}

function dp_auto_article_intro(array $district, array $thana, array $specialty, int $total_doctors, string $lang): string
{
    $specialty_name = dp_seo_row_name(
        $specialty,
        $lang,
        $lang === 'bn' ? 'বিশেষজ্ঞ' : 'specialist'
    );
    $specialty_doctor_label = dp_seo_specialty_doctor_label($specialty_name, $lang);
    $location = dp_auto_article_location_name($district, $thana, $lang);

    if ($lang === 'bn') {
        $bangla_specialty_label = dp_seo_bangla_specialty_label($specialty);

        return $location !== ''
            ? dp_seo_bangla_location_title_prefix($district, $thana) . ' অভিজ্ঞ '
                . $bangla_specialty_label . ' খুঁজছেন? এখানে ডাক্তার তালিকা, চেম্বার, হাসপাতাল, ভিজিটিং সময় ও অ্যাপয়েন্টমেন্টের বিস্তারিত তথ্য পাওয়া যাবে।'
            : 'অভিজ্ঞ ' . $bangla_specialty_label . ' খুঁজছেন? এখানে ডাক্তার তালিকা, চেম্বার, হাসপাতাল, ভিজিটিং সময় ও অ্যাপয়েন্টমেন্টের বিস্তারিত তথ্য পাওয়া যাবে।';
    }

    $english_specialty_label = dp_seo_english_specialty_label($specialty);

    return $location !== ''
        ? 'If you are looking for experienced ' . $english_specialty_label . ' in ' . $location . ', this page lists doctors with chamber, hospital, visiting-hour and appointment details.'
        : 'This page helps you find experienced ' . $english_specialty_label . ' across Bangladesh with chamber, hospital, visiting-hour and appointment details.';
}

function dp_auto_article_doctor_names(array $doctors, int $limit = 10, string $lang = ''): array
{
    $names = [];
    $lang = $lang === '' ? dp_current_seo_lang() : ($lang === 'bn' ? 'bn' : 'en');

    foreach ($doctors as $doctor) {
        /*
         * Bangla directory pages show the doctor's Bangla name first.
         * If name_bn is empty, the normal English name remains a safe fallback.
         */
        $name = '';

        if ($lang === 'bn') {
            $name = trim((string)($doctor['name_bn'] ?? ''));
        }

        if ($name === '') {
            $name = trim((string)($doctor['name'] ?? ($doctor['name_en'] ?? '')));
        }

        if ($name !== '' && !in_array($name, $names, true)) {
            $names[] = $name;
        }

        if (count($names) >= $limit) {
            break;
        }
    }

    return $names;
}

function dp_auto_article_list_title(string $lang): string
{
    return $lang === 'bn' ? 'এই বিভাগের উল্লেখযোগ্য ডাক্তার' : 'Notable doctors in this category';
}

function dp_add_column_if_missing(string $table, string $column, string $definition): void
{
    global $pdo;

    if (!dp_table_exists($table)) {
        return;
    }

    if (!dp_column_exists($table, $column)) {
        try {
            $pdo->exec("ALTER TABLE {$table} ADD COLUMN {$column} {$definition}");
        } catch (Throwable $e) {
            error_log('Column add failed for ' . $table . '.' . $column . ': ' . $e->getMessage());
        }
    }
}

function dp_directory_articles_boot(): void
{
    global $pdo;

    try {
        if (!dp_table_exists('doctor_directory_articles')) {
            $pdo->exec("
                CREATE TABLE doctor_directory_articles (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    district_id INT DEFAULT 0,
                    thana_id INT DEFAULT 0,
                    specialty_id INT DEFAULT 0,
                    lang VARCHAR(10) DEFAULT 'en',
                    title VARCHAR(255) NULL,
                    intro TEXT NULL,
                    meta_title VARCHAR(255) NULL,
                    meta_description TEXT NULL,
                    meta_keywords TEXT NULL,
                    content LONGTEXT NULL,
                    show_doctor_list TINYINT(1) NOT NULL DEFAULT 1,
                    status VARCHAR(30) DEFAULT 'active',
                    created_at DATETIME NULL,
                    updated_at DATETIME NULL,
                    UNIQUE KEY unique_article_context (district_id, thana_id, specialty_id, lang),
                    INDEX article_status_idx (status),
                    INDEX article_context_idx (district_id, thana_id, specialty_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
            return;
        }

        dp_add_column_if_missing('doctor_directory_articles', 'district_id', "INT DEFAULT 0");
        dp_add_column_if_missing('doctor_directory_articles', 'thana_id', "INT DEFAULT 0");
        dp_add_column_if_missing('doctor_directory_articles', 'specialty_id', "INT DEFAULT 0");
        dp_add_column_if_missing('doctor_directory_articles', 'lang', "VARCHAR(10) DEFAULT 'en'");
        dp_add_column_if_missing('doctor_directory_articles', 'title', "VARCHAR(255) NULL");
        dp_add_column_if_missing('doctor_directory_articles', 'intro', "TEXT NULL");
        dp_add_column_if_missing('doctor_directory_articles', 'meta_title', "VARCHAR(255) NULL");
        dp_add_column_if_missing('doctor_directory_articles', 'meta_description', "TEXT NULL");
        dp_add_column_if_missing('doctor_directory_articles', 'meta_keywords', "TEXT NULL");
        dp_add_column_if_missing('doctor_directory_articles', 'content', "LONGTEXT NULL");
        dp_add_column_if_missing('doctor_directory_articles', 'show_doctor_list', "TINYINT(1) NOT NULL DEFAULT 1");
        dp_add_column_if_missing('doctor_directory_articles', 'status', "VARCHAR(30) DEFAULT 'active'");
        dp_add_column_if_missing('doctor_directory_articles', 'created_at', "DATETIME NULL");
        dp_add_column_if_missing('doctor_directory_articles', 'updated_at', "DATETIME NULL");
    } catch (Throwable $e) {
        error_log('Directory article table boot failed: ' . $e->getMessage());
    }
}

function dp_get_dynamic_directory_article(array $district, array $thana, array $specialty, string $lang): array
{
    global $pdo;

    dp_directory_articles_boot();

    if (!dp_table_exists('doctor_directory_articles')) {
        return [];
    }

    $district_id = (int)($district['id'] ?? 0);
    $thana_id = (int)($thana['id'] ?? 0);
    $specialty_id = (int)($specialty['id'] ?? 0);
    $lang = $lang === 'bn' ? 'bn' : 'en';

    if ($specialty_id <= 0) {
        return [];
    }

    try {
        $stmt = $pdo->prepare("
            SELECT title, meta_title, meta_description, meta_keywords, content, show_doctor_list
            FROM doctor_directory_articles
            WHERE district_id = :district_id
              AND thana_id = :thana_id
              AND specialty_id = :specialty_id
              AND lang = :lang
              AND status = 'active'
            LIMIT 1
        ");

        $stmt->execute([
            ':district_id' => $district_id,
            ':thana_id' => $thana_id,
            ':specialty_id' => $specialty_id,
            ':lang' => $lang,
        ]);

        $article = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!empty($article)) {
            return $article;
        }

        /*
         * Fallback:
         * If thana-specific dynamic article is not found,
         * try district + specialty article.
         */
        if ($thana_id > 0) {
            $stmt = $pdo->prepare("
                SELECT title, meta_title, meta_description, meta_keywords, content, show_doctor_list
                FROM doctor_directory_articles
                WHERE district_id = :district_id
                  AND thana_id = 0
                  AND specialty_id = :specialty_id
                  AND lang = :lang
                  AND status = 'active'
                LIMIT 1
            ");

            $stmt->execute([
                ':district_id' => $district_id,
                ':specialty_id' => $specialty_id,
                ':lang' => $lang,
            ]);

            $article = $stmt->fetch(PDO::FETCH_ASSOC);

            return $article ?: [];
        }

        return [];
    } catch (Throwable $e) {
        error_log('Dynamic directory article query failed: ' . $e->getMessage());
        return [];
    }
}

function dp_render_directory_article_content(string $content): string
{
    $content = trim($content);

    if ($content === '') {
        return '';
    }

    /*
     * Content is expected to be saved from trusted admin panel.
     * This keeps formatting like paragraph, list, bold, links and headings.
     */
    return $content;
}

$lang = dp_current_seo_lang();

$page_title = dp_page_title($page_step, $division, $district, $thana, $specialty, $filters);
$meta_description = dp_page_description($page_step, $division, $district, $thana, $specialty, $filters);

/*
 * Optional homepage SEO settings.
 * Use dedicated Bangla values on Bangla pages so an English homepage setting
 * never replaces the automatically generated Bangla SEO text.
 */
$default_meta_title = $lang === 'bn'
    ? dp_site_setting('meta_title_bn', '')
    : dp_site_setting('meta_title', '');

$default_meta_description = $lang === 'bn'
    ? dp_site_setting('meta_description_bn', '')
    : dp_site_setting('meta_description', '');

if ($page_step === 'division' && $default_meta_title !== '') {
    $page_title = $default_meta_title;
}

if ($page_step === 'division' && $default_meta_description !== '') {
    $meta_description = $default_meta_description;
}

/*
|--------------------------------------------------------------------------
| Directory Article SEO Override
|--------------------------------------------------------------------------
| Article title and public content remain independent from SEO fields.
| On doctor list pages, the admin-managed Meta Title, Meta Description and
| Meta Keywords override only the relevant head tags when they are present.
|--------------------------------------------------------------------------
*/
$directory_article_seo = [];

if ($page_step === 'doctor_list') {
    $directory_article_seo = dp_get_dynamic_directory_article(
        $district,
        $thana,
        $specialty,
        $lang
    );

    $directory_article_meta_title = trim((string)($directory_article_seo['meta_title'] ?? ''));
    $directory_article_meta_description = trim((string)($directory_article_seo['meta_description'] ?? ''));
    $directory_article_meta_keywords = trim((string)($directory_article_seo['meta_keywords'] ?? ''));

    if ($directory_article_meta_title !== '') {
        $page_title = $directory_article_meta_title;
    }

    if ($directory_article_meta_description !== '') {
        $meta_description = $directory_article_meta_description;
    }

    if ($directory_article_meta_keywords !== '') {
        $meta_keywords = $directory_article_meta_keywords;
    }
}

$canonical_url = front_url('doctors');
$current_action_url = front_url('doctors');

if ($page_step === 'district') {
    $current_action_url = dp_doctors_url([
        'division_slug' => dp_row_slug($division),
    ]);
    $canonical_url = $current_action_url;
} elseif ($page_step === 'specialty') {
    $current_action_url = dp_doctors_url([
        'district_slug' => dp_row_slug($district),
    ]);
    $canonical_url = $current_action_url;
} elseif ($page_step === 'thana_specialty') {
    $current_action_url = dp_doctors_url([
        'district_slug' => dp_row_slug($district),
        'thana_slug' => dp_row_slug($thana),
    ]);
    $canonical_url = $current_action_url;
} elseif ($page_step === 'doctor_list') {
    if (!empty($specialty) && empty($district)) {
        $url_args = [
            'specialty_slug' => dp_row_slug($specialty),
        ];
    } else {
        $url_args = [
            'district_slug' => dp_row_slug($district),
            'specialty_slug' => dp_row_slug($specialty),
        ];

        if (!empty($thana)) {
            $url_args['thana_slug'] = dp_row_slug($thana);
        }
    }

    $current_action_url = dp_doctors_url($url_args);
    $url_args['search'] = $filters['search'];
    $url_args['page'] = $current_page;
    $canonical_url = dp_doctors_url($url_args);
}

$doctor_list_heading = $lang === 'bn' ? 'সকল ডাক্তার' : 'All Doctors';

$heading_division_name = dp_seo_row_name(
    $division,
    $lang,
    $lang === 'bn' ? 'নির্বাচিত বিভাগ' : 'Selected Division'
);
$heading_location_name = dp_seo_location_name($district, $thana, $lang);
$heading_specialty_name = dp_seo_row_name(
    $specialty,
    $lang,
    $lang === 'bn' ? 'বিশেষজ্ঞ' : 'Specialist'
);
$heading_specialty_doctor_label = dp_seo_specialty_doctor_label($heading_specialty_name, $lang);

if (!empty($division) && empty($district)) {
    $doctor_list_heading = $lang === 'bn'
        ? $heading_division_name . ' বিভাগের ডাক্তার তালিকা'
        : 'Doctors in ' . $heading_division_name;
} elseif (!empty($district) && empty($specialty)) {
    $doctor_list_heading = $lang === 'bn'
        ? $heading_location_name . ' ডাক্তার তালিকা'
        : 'Doctors in ' . $heading_location_name;
} elseif (!empty($specialty) && empty($district)) {
    $doctor_list_heading = $lang === 'bn'
        ? 'বাংলাদেশের ' . dp_seo_bangla_specialty_label($specialty) . ' তালিকা'
        : dp_seo_english_specialty_label($specialty);
} elseif (!empty($district) && !empty($specialty)) {
    $doctor_list_heading = $lang === 'bn'
        ? dp_seo_bangla_location_title_prefix($district, $thana)
            . ' '
            . dp_seo_bangla_specialty_label($specialty)
            . ' তালিকা'
        : dp_seo_english_specialty_label($specialty) . ' in ' . $heading_location_name;
}

if (($filters['search'] ?? '') !== '') {
    $doctor_list_heading = $lang === 'bn'
        ? $filters['search'] . ' ডাক্তার খোঁজার ফলাফল'
        : 'Search Results for ' . $filters['search'];
}

/*
|--------------------------------------------------------------------------
| SEO Pagination Links
|--------------------------------------------------------------------------
| Numbered pagination is kept crawlable for SEO.
| Every page has a normal href URL such as:
| /doctors/cardiology?page=2
|--------------------------------------------------------------------------
*/
$prev_page_url = '';
$next_page_url = '';

if ($page_step !== 'not_found' && $total_pages > 1) {
    if ($has_previous_page) {
        $prev_page_url = dp_pagination_url($current_action_url, $current_page - 1, $filters['search']);
    }

    if ($has_next_page) {
        $next_page_url = dp_pagination_url($current_action_url, $current_page + 1, $filters['search']);
    }
}

$site_name = dp_site_name();


/*
|--------------------------------------------------------------------------
| Structured Data Schema
|--------------------------------------------------------------------------
| Generates safe JSON-LD for all directory pages.
| - WebSite: identifies the directory website.
| - CollectionPage: identifies the current directory result page.
| - BreadcrumbList: mirrors the visible page hierarchy.
| - ItemList: lists doctors only on doctor result pages.
|--------------------------------------------------------------------------
*/

function dp_schema_text(string $value): string
{
    $value = strip_tags($value);
    $value = preg_replace('/\s+/u', ' ', $value);

    return trim((string)$value);
}

function dp_schema_page_name(string $page_title, string $site_name): string
{
    $suffix = ' | ' . $site_name;

    if ($site_name !== '' && str_ends_with($page_title, $suffix)) {
        return trim(substr($page_title, 0, -strlen($suffix)));
    }

    return trim($page_title);
}


/*
|--------------------------------------------------------------------------
| MedicalSpecialty Schema Mapping
|--------------------------------------------------------------------------
| Schema.org medicalSpecialty accepts MedicalSpecialty enumeration URLs,
| not free-text labels such as "Cardiology Specialist".
| Unknown specialties intentionally return an empty value so invalid schema
| is never emitted. The visible specialty text remains available as a normal
| description field on the Physician item.
|--------------------------------------------------------------------------
*/
function dp_schema_medical_specialty_url(string $specialty_name): string
{
    $raw = dp_schema_text($specialty_name);

    if ($raw === '') {
        return '';
    }

    $normalized = function_exists('mb_strtolower')
        ? mb_strtolower($raw, 'UTF-8')
        : strtolower($raw);

    $map = [
        'anesthes' => 'https://schema.org/Anesthesia',
        'pain specialist' => 'https://schema.org/Anesthesia',
        'cardiology' => 'https://schema.org/Cardiovascular',
        'cardiac' => 'https://schema.org/Cardiovascular',
        'heart' => 'https://schema.org/Cardiovascular',
        'cancer surgeon' => 'https://schema.org/Surgical',
        'surgical oncology' => 'https://schema.org/Surgical',
        'oncology' => 'https://schema.org/Oncologic',
        'cancer specialist' => 'https://schema.org/Oncologic',
        'chest' => 'https://schema.org/Pulmonary',
        'asthma' => 'https://schema.org/Pulmonary',
        'pulmonary' => 'https://schema.org/Pulmonary',
        'child specialist' => 'https://schema.org/Pediatric',
        'pediatric' => 'https://schema.org/Pediatric',
        'colorectal' => 'https://schema.org/Surgical',
        'laparoscopic' => 'https://schema.org/Surgical',
        'surgery' => 'https://schema.org/Surgical',
        'dental' => 'https://schema.org/Dentistry',
        'dentist' => 'https://schema.org/Dentistry',
        'diabetes' => 'https://schema.org/Endocrine',
        'hormone' => 'https://schema.org/Endocrine',
        'endocrin' => 'https://schema.org/Endocrine',
        'ent' => 'https://schema.org/Otolaryngologic',
        'ear nose throat' => 'https://schema.org/Otolaryngologic',
        'eye specialist' => 'https://schema.org/Ophthalmologic',
        'ophthalm' => 'https://schema.org/Ophthalmologic',
        'gastro' => 'https://schema.org/Gastroenterologic',
        'gyne' => 'https://schema.org/Gynecologic',
        'obstetric' => 'https://schema.org/Obstetric',
        'medicine' => 'https://schema.org/PrimaryCare',
        'dermat' => 'https://schema.org/Dermatology',
        'skin' => 'https://schema.org/Dermatology',
        'neurolog' => 'https://schema.org/Neurologic',
        'neuro medicine' => 'https://schema.org/Neurologic',
        'psychiatr' => 'https://schema.org/Psychiatric',
        'mental health' => 'https://schema.org/Psychiatric',
        'kidney' => 'https://schema.org/Renal',
        'renal' => 'https://schema.org/Renal',
        'urolog' => 'https://schema.org/Urologic',
        'orthop' => 'https://schema.org/Musculoskeletal',
        'bone' => 'https://schema.org/Musculoskeletal',
        'rheumat' => 'https://schema.org/Rheumatologic',
        'blood' => 'https://schema.org/Hematologic',
        'hematolog' => 'https://schema.org/Hematologic',
        'infectious' => 'https://schema.org/Infectious',
        'patholog' => 'https://schema.org/Pathology',
        'radiolog' => 'https://schema.org/Radiography',
        'physiotherap' => 'https://schema.org/Physiotherapy',
        'physical medicine' => 'https://schema.org/Physiotherapy',
        'plastic' => 'https://schema.org/PlasticSurgery',
        'nutrition' => 'https://schema.org/DietNutrition',
        'emergency' => 'https://schema.org/Emergency',
        'general practitioner' => 'https://schema.org/PrimaryCare',
    ];

    foreach ($map as $needle => $url) {
        if (str_contains($normalized, $needle)) {
            return $url;
        }
    }

    return '';
}


/*
|--------------------------------------------------------------------------
| Physician Data Helpers
|--------------------------------------------------------------------------
| Builds optional Physician properties only from known doctor data. When a
| doctor-specific value is unavailable, safe directory-level fallbacks are
| used only for image, price range and address.
|--------------------------------------------------------------------------
*/
function dp_schema_row_value(array $row, array $keys): string
{
    foreach ($keys as $key) {
        if (array_key_exists($key, $row) && trim((string)$row[$key]) !== '') {
            return trim((string)$row[$key]);
        }
    }

    return '';
}

function dp_schema_site_setting_value(string $key, string $default = ''): string
{
    global $pdo;

    if ($key === '' || !isset($pdo)) {
        return $default;
    }

    static $cache = [];

    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    try {
        if (!function_exists('dp_table_exists') || !dp_table_exists('site_settings')) {
            return $cache[$key] = $default;
        }

        $stmt = $pdo->prepare("SELECT setting_value FROM site_settings WHERE setting_key = :setting_key LIMIT 1");
        $stmt->execute([':setting_key' => $key]);
        $value = trim((string)$stmt->fetchColumn());

        return $cache[$key] = ($value !== '' ? $value : $default);
    } catch (Throwable $e) {
        return $cache[$key] = $default;
    }
}

function dp_schema_absolute_url(string $value): string
{
    $value = trim($value);

    if ($value === '') {
        return '';
    }

    if (preg_match('/^https?:\/\//i', $value)) {
        return $value;
    }

    if (str_starts_with($value, '//')) {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https:' : 'http:';
        return $scheme . $value;
    }

    $value = str_replace('\\', '/', $value);
    $value = preg_replace('#^(\.\./)+#', '', $value);
    $value = ltrim((string)$value, '/');

    $url = '';

    if (function_exists('site_url')) {
        try {
            $url = trim((string)site_url($value));
        } catch (Throwable $e) {
            $url = '';
        }
    }

    if ($url !== '' && preg_match('/^https?:\/\//i', $url)) {
        return $url;
    }

    /*
     * Do not publish a relative or empty social image URL. This fallback
     * protects Open Graph and Twitter metadata when a custom site_url()
     * helper returns a path-only value or an unexpected empty value.
     */
    $base = defined('APP_URL') ? trim((string)APP_URL) : '';

    if ($base === '') {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = trim((string)(
            $_SERVER['HTTP_X_FORWARDED_HOST']
            ?? $_SERVER['HTTP_HOST']
            ?? ''
        ));

        if ($host !== '') {
            $host = trim(explode(',', $host)[0]);
            $base = $scheme . '://' . $host;
        }
    }

    if ($base !== '') {
        return rtrim($base, '/') . '/' . $value;
    }

    return $url;
}

function dp_schema_doctor_url(array $doctor, string $fallback_url): string
{
    $direct_url = dp_schema_row_value($doctor, ['url', 'profile_url', 'doctor_url', 'permalink']);

    if ($direct_url !== '') {
        return dp_schema_absolute_url($direct_url);
    }

    if (function_exists('doctor_url')) {
        try {
            $url = doctor_url($doctor);

            if (is_string($url) && trim($url) !== '') {
                return $url;
            }
        } catch (Throwable $e) {
            // Use the directory page URL as a safe fallback.
        }
    }

    return $fallback_url;
}

function dp_schema_doctor_image(array $doctor): string
{
    $image = dp_schema_row_value($doctor, [
        'image', 'image_url', 'photo', 'photo_url', 'profile_image',
        'profile_photo', 'avatar', 'avatar_url', 'featured_image',
    ]);

    if ($image === '') {
        $image = dp_schema_site_setting_value('default_doctor_image', 'assets/images/default-doctor.webp');
    }

    return dp_schema_absolute_url($image);
}

function dp_schema_doctor_telephone(array $doctor, ?array $chambers = null): string
{
    /*
     * Phone priority:
     * 1. Doctor's own phone fields
     * 2. First active chamber appointment/contact phone
     * 3. Related hospital phone
     * 4. Directory contact phone fallback
     */
    $telephone = dp_schema_row_value($doctor, [
        'phone', 'phone_number', 'mobile', 'mobile_number', 'telephone',
        'contact_number', 'appointment_phone', 'whatsapp',
    ]);

    if ($telephone === '') {
        if ($chambers === null) {
            $chambers = dp_schema_get_doctor_chambers((int)($doctor['id'] ?? 0));
        }

        foreach ((array)$chambers as $chamber) {
            $telephone = dp_schema_row_value($chamber, [
                'chamber_appointment_phone',
                'chamber_phone',
                'chamber_telephone',
                'hospital_phone',
                'hospital_telephone',
            ]);

            if ($telephone !== '') {
                break;
            }
        }
    }

    if ($telephone === '') {
        $telephone = dp_schema_site_setting_value('contact_phone', '');
    }

    if ($telephone === '') {
        $telephone = dp_schema_site_setting_value('site_phone', '');
    }

    return dp_schema_text($telephone);
}

function dp_schema_price_range_from_value(string $raw_fee): string
{
    $raw_fee = dp_schema_text($raw_fee);

    if ($raw_fee === '') {
        return '';
    }

    /* Do not publish zero/default values such as 0, 0.00 or ৳0.00. */
    $numeric = preg_replace('/[^0-9.]+/u', '', $raw_fee);
    if ($numeric !== '' && is_numeric($numeric) && (float)$numeric <= 0) {
        return '';
    }

    if (preg_match('/^\d+(?:\.\d+)?$/', $raw_fee)) {
        return '৳' . $raw_fee;
    }

    return $raw_fee;
}

function dp_schema_doctor_price_range(array $doctor, ?array $chambers = null): string
{
    $raw_fee = dp_schema_row_value($doctor, [
        'consultation_fee', 'consultation_fee_amount', 'fee', 'fees',
        'visit_fee', 'appointment_fee', 'price', 'price_range',
    ]);

    $price_range = dp_schema_price_range_from_value($raw_fee);

    /* If the doctor profile has no valid fee, use the first active chamber fee. */
    if ($price_range === '') {
        if ($chambers === null) {
            $chambers = dp_schema_get_doctor_chambers((int)($doctor['id'] ?? 0));
        }

        foreach ((array)$chambers as $chamber) {
            $price_range = dp_schema_price_range_from_value(
                dp_schema_row_value($chamber, ['chamber_consultation_fee', 'chamber_fee'])
            );

            if ($price_range !== '') {
                break;
            }
        }
    }

    /* Optional site-wide fallback only when the admin has saved a real value. */
    if ($price_range === '') {
        $price_range = dp_schema_price_range_from_value(
            dp_schema_site_setting_value('doctor_price_range', '')
        );
    }

    return $price_range;
}

function dp_schema_chamber_hospital_postal_code(array $chambers): string
{
    /*
     * Doctor profile pages often have no postal code of their own.
     * For a Physician location, use the first active chamber postal code first,
     * then the linked hospital postal code. The chamber list is already sorted
     * by sort_order and chamber ID, so this follows the primary chamber order.
     */
    foreach ($chambers as $chamber) {
        $postal_code = dp_schema_row_value($chamber, [
            'chamber_post_code',
            'chamber_postal_code',
            'hospital_post_code',
            'hospital_postal_code',
        ]);

        if ($postal_code !== '') {
            return dp_schema_text($postal_code);
        }
    }

    return '';
}

function dp_schema_doctor_address(array $doctor, array $district, array $thana, ?array $chambers = null): array
{
    if ($chambers === null) {
        $chambers = dp_schema_get_doctor_chambers((int)($doctor['id'] ?? 0));
    }

    $street_address = dp_schema_row_value($doctor, [
        'address', 'address_en', 'doctor_address', 'chamber_address',
        'hospital_address', 'practice_address',
    ]);

    $locality = dp_schema_row_value($doctor, [
        'doctor_thana', 'doctor_thana_name', 'thana_name', 'thana', 'area', 'city',
    ]);

    if ($locality === '') {
        $locality = dp_schema_text(dp_seo_row_name($thana, dp_current_seo_lang(), ''));
    }

    $region = dp_schema_row_value($doctor, [
        'doctor_district', 'doctor_district_name', 'district_name', 'district', 'city',
    ]);

    if ($region === '') {
        $region = dp_schema_text(dp_seo_row_name($district, dp_current_seo_lang(), ''));
    }

    /* Google expects a locality. Use the selected district only as a last fallback. */
    if ($locality === '') {
        $locality = $region;
    }

    /*
     * Postal code priority:
     * 1. First active chamber post code
     * 2. Linked hospital post code of that chamber
     * 3. Doctor profile post code
     */
    $postal_code = dp_schema_chamber_hospital_postal_code((array)$chambers);

    if ($postal_code === '') {
        $postal_code = dp_schema_row_value($doctor, [
            'post_code', 'postal_code', 'postcode', 'zip', 'zip_code',
        ]);
    }

    if ($street_address === '') {
        $street_address = trim(implode(', ', array_filter([$locality, $region])));
    }

    $address = [
        '@type' => 'PostalAddress',
        'addressCountry' => 'BD',
    ];

    if ($street_address !== '') {
        $address['streetAddress'] = dp_schema_text($street_address);
    }

    if ($locality !== '') {
        $address['addressLocality'] = dp_schema_text($locality);
    }

    if ($region !== '') {
        $address['addressRegion'] = dp_schema_text($region);
    }

    if ($postal_code !== '') {
        $address['postalCode'] = dp_schema_text($postal_code);
    }

    return $address;
}


/*
|--------------------------------------------------------------------------
| Doctor Chambers and Hospital Schema Helpers
|--------------------------------------------------------------------------
| Every active chamber is loaded separately for the current doctor. This
| keeps every hospital, chamber address, phone, fee and visiting schedule
| available in the Physician schema without changing the doctor-list query.
|--------------------------------------------------------------------------
*/
function dp_schema_select_column(string $table_alias, string $table, string $column, string $alias): string
{
    if (function_exists('dp_column_exists') && dp_column_exists($table, $column)) {
        return $table_alias . '.' . $column . ' AS ' . $alias;
    }

    return "'' AS " . $alias;
}

function dp_schema_get_doctor_chambers(int $doctor_id): array
{
    global $pdo;

    static $cache = [];

    if ($doctor_id <= 0 || !isset($pdo)) {
        return [];
    }

    if (array_key_exists($doctor_id, $cache)) {
        return $cache[$doctor_id];
    }

    if (!function_exists('dp_table_exists') || !dp_table_exists('chambers')) {
        return $cache[$doctor_id] = [];
    }

    $select = [
        'c.id AS chamber_id',
        'c.doctor_id AS doctor_id',
        dp_schema_select_column('c', 'chambers', 'hospital_id', 'hospital_id'),
        dp_schema_select_column('c', 'chambers', 'name', 'chamber_name'),
        dp_schema_select_column('c', 'chambers', 'address', 'chamber_address'),
        dp_schema_select_column('c', 'chambers', 'address_bn', 'chamber_address_bn'),
        dp_schema_select_column('c', 'chambers', 'phone', 'chamber_phone'),
        dp_schema_select_column('c', 'chambers', 'telephone', 'chamber_telephone'),
        dp_schema_select_column('c', 'chambers', 'appointment_phone', 'chamber_appointment_phone'),
        dp_schema_select_column('c', 'chambers', 'consultation_fee', 'chamber_consultation_fee'),
        dp_schema_select_column('c', 'chambers', 'fee', 'chamber_fee'),
        dp_schema_select_column('c', 'chambers', 'visiting_hours', 'chamber_visiting_hours'),
        dp_schema_select_column('c', 'chambers', 'schedule', 'chamber_schedule'),
        dp_schema_select_column('c', 'chambers', 'post_code', 'chamber_post_code'),
        dp_schema_select_column('c', 'chambers', 'postal_code', 'chamber_postal_code'),
        dp_schema_select_column('c', 'chambers', 'area', 'chamber_area'),
        dp_schema_select_column('c', 'chambers', 'city', 'chamber_city'),
        dp_schema_select_column('c', 'chambers', 'sort_order', 'chamber_sort_order'),
    ];

    $join = '';

    if (dp_table_exists('hospitals')) {
        $join = ' LEFT JOIN hospitals h ON h.id = c.hospital_id ';
        $select[] = dp_schema_select_column('h', 'hospitals', 'name', 'hospital_name');
        $select[] = dp_schema_select_column('h', 'hospitals', 'name_bn', 'hospital_name_bn');
        $select[] = dp_schema_select_column('h', 'hospitals', 'slug', 'hospital_slug');
        $select[] = dp_schema_select_column('h', 'hospitals', 'address', 'hospital_address');
        $select[] = dp_schema_select_column('h', 'hospitals', 'address_bn', 'hospital_address_bn');
        $select[] = dp_schema_select_column('h', 'hospitals', 'city', 'hospital_city');
        $select[] = dp_schema_select_column('h', 'hospitals', 'phone', 'hospital_phone');
        $select[] = dp_schema_select_column('h', 'hospitals', 'telephone', 'hospital_telephone');
        $select[] = dp_schema_select_column('h', 'hospitals', 'image', 'hospital_image');
        $select[] = dp_schema_select_column('h', 'hospitals', 'image_url', 'hospital_image_url');
        $select[] = dp_schema_select_column('h', 'hospitals', 'cover_image', 'hospital_cover_image');
        $select[] = dp_schema_select_column('h', 'hospitals', 'post_code', 'hospital_post_code');
        $select[] = dp_schema_select_column('h', 'hospitals', 'postal_code', 'hospital_postal_code');
        $select[] = dp_schema_select_column('h', 'hospitals', 'area', 'hospital_area');
        $select[] = dp_schema_select_column('h', 'hospitals', 'thana_id', 'hospital_thana_id');
        $select[] = dp_schema_select_column('h', 'hospitals', 'district_id', 'hospital_district_id');
    }

    $status_where = '';
    if (dp_column_exists('chambers', 'status')) {
        $status_where = " AND (c.status = 'active' OR c.status = 'published' OR c.status = '' OR c.status IS NULL)";
    }

    try {
        $sql = "
            SELECT " . implode(', ', $select) . "
            FROM chambers c
            {$join}
            WHERE c.doctor_id = :doctor_id
            {$status_where}
            ORDER BY " . (dp_column_exists('chambers', 'sort_order') ? 'COALESCE(c.sort_order, 999999) ASC, ' : '') . "c.id ASC
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([':doctor_id' => $doctor_id]);

        return $cache[$doctor_id] = ($stmt->fetchAll() ?: []);
    } catch (Throwable $e) {
        error_log('Doctor chamber schema query failed: ' . $e->getMessage());
        return $cache[$doctor_id] = [];
    }
}

function dp_schema_hospital_url(array $chamber): string
{
    $direct_url = dp_schema_row_value($chamber, ['hospital_url', 'url']);

    if ($direct_url !== '') {
        return dp_schema_absolute_url($direct_url);
    }

    if (function_exists('hospital_url') && !empty($chamber['hospital_id'])) {
        try {
            $hospital = [
                'id' => (int)$chamber['hospital_id'],
                'slug' => (string)($chamber['hospital_slug'] ?? ''),
                'name' => (string)($chamber['hospital_name'] ?? ''),
            ];
            $url = hospital_url($hospital);

            if (is_string($url) && trim($url) !== '') {
                return $url;
            }
        } catch (Throwable $e) {
            // Keep the schema valid even where a hospital URL helper is absent.
        }
    }

    return '';
}

function dp_schema_chamber_address(array $chamber, array $district, array $thana): array
{
    $street = dp_schema_row_value($chamber, [
        'chamber_address', 'chamber_address_bn', 'hospital_address', 'hospital_address_bn',
    ]);

    $locality = dp_schema_row_value($chamber, [
        'chamber_area', 'hospital_area', 'chamber_city', 'hospital_city',
    ]);

    if ($locality === '') {
        $locality = dp_schema_text(dp_seo_row_name($thana, dp_current_seo_lang(), ''));
    }

    $region = dp_schema_text(dp_seo_row_name($district, dp_current_seo_lang(), ''));

    if ($locality === '') {
        $locality = $region;
    }

    $postal_code = dp_schema_row_value($chamber, [
        'chamber_post_code', 'chamber_postal_code',
        'hospital_post_code', 'hospital_postal_code',
    ]);

    if ($street === '') {
        $street = trim(implode(', ', array_filter([$locality, $region])));
    }

    $address = [
        '@type' => 'PostalAddress',
        'addressCountry' => 'BD',
    ];

    if ($street !== '') {
        $address['streetAddress'] = dp_schema_text($street);
    }

    if ($locality !== '') {
        $address['addressLocality'] = dp_schema_text($locality);
    }

    if ($region !== '') {
        $address['addressRegion'] = $region;
    }

    if ($postal_code !== '') {
        $address['postalCode'] = dp_schema_text($postal_code);
    }

    return $address;
}

function dp_schema_hospital_price_range(array $chamber, array $doctor = []): string
{
    $price_range = dp_schema_price_range_from_value(
        dp_schema_row_value($chamber, ['chamber_consultation_fee', 'chamber_fee'])
    );

    if ($price_range === '') {
        $price_range = dp_schema_doctor_price_range($doctor, [$chamber]);
    }

    if ($price_range === '') {
        $price_range = dp_schema_price_range_from_value(
            dp_schema_site_setting_value('hospital_price_range', '')
        );
    }

    return $price_range;
}


function dp_schema_hospital_image(array $chamber): string
{
    /*
     * Matches the hospital card fallback pattern:
     * Use the saved hospital image first. If no image exists, use the
     * site setting default_hospital_image. If that is empty, use the
     * built-in default hospital image.
     */
    $image = dp_schema_row_value($chamber, [
        'hospital_image',
        'hospital_image_url',
        'hospital_cover_image',
    ]);

    if ($image === '') {
        $image = dp_schema_site_setting_value(
            'default_hospital_image',
            'assets/images/default-hospital.webp'
        );
    }

    return dp_schema_absolute_url($image);
}

function dp_schema_build_chamber_affiliations(
    array $doctor,
    array $district,
    array $thana,
    ?array $loaded_chambers = null
): array
{
    $doctor_id = (int)($doctor['id'] ?? 0);
    $chambers = $loaded_chambers ?? dp_schema_get_doctor_chambers($doctor_id);
    $affiliations = [];

    foreach ($chambers as $chamber) {
        $hospital_name = dp_schema_row_value($chamber, ['hospital_name', 'hospital_name_bn', 'chamber_name']);

        if ($hospital_name === '') {
            continue;
        }

        $hospital = [
            '@type' => 'Hospital',
            'name' => dp_schema_text($hospital_name),
            'address' => dp_schema_chamber_address($chamber, $district, $thana),
        ];

        $hospital_url = dp_schema_hospital_url($chamber);
        if ($hospital_url !== '') {
            $hospital['url'] = $hospital_url;
        }

        $telephone = dp_schema_row_value($chamber, [
            'chamber_appointment_phone', 'chamber_phone', 'chamber_telephone',
            'hospital_phone', 'hospital_telephone',
        ]);
        if ($telephone !== '') {
            $hospital['telephone'] = dp_schema_text($telephone);
        }

        $image = dp_schema_hospital_image($chamber);
        if ($image !== '') {
            $hospital['image'] = $image;
        }

        $hospital_price_range = dp_schema_hospital_price_range($chamber, $doctor);
        if ($hospital_price_range !== '') {
            $hospital['priceRange'] = $hospital_price_range;
        }

        $properties = [];
        $chamber_name = dp_schema_row_value($chamber, ['chamber_name']);
        if ($chamber_name !== '') {
            $properties[] = [
                '@type' => 'PropertyValue',
                'name' => dp_current_seo_lang() === 'bn' ? 'চেম্বারের নাম' : 'Chamber name',
                'value' => dp_schema_text($chamber_name),
            ];
        }

        $fee = dp_schema_row_value($chamber, ['chamber_consultation_fee', 'chamber_fee']);
        if ($fee !== '') {
            $properties[] = [
                '@type' => 'PropertyValue',
                'name' => dp_current_seo_lang() === 'bn' ? 'পরামর্শ ফি' : 'Consultation fee',
                'value' => dp_schema_doctor_price_range(['fee' => $fee]),
            ];
        }

        $schedule = dp_schema_row_value($chamber, ['chamber_visiting_hours', 'chamber_schedule']);
        if ($schedule !== '') {
            $properties[] = [
                '@type' => 'PropertyValue',
                'name' => dp_current_seo_lang() === 'bn' ? 'ভিজিটিং সময়' : 'Visiting hours',
                'value' => dp_schema_text($schedule),
            ];
        }

        if (!empty($properties)) {
            $hospital['additionalProperty'] = $properties;
        }

        $affiliations[] = $hospital;
    }

    return $affiliations;
}

function dp_schema_breadcrumb_items(
    array $division,
    array $district,
    array $thana,
    array $specialty,
    string $page_step,
    string $canonical_url
): array {
    $items = [];

    $items[] = [
        '@type' => 'ListItem',
        'position' => 1,
        'name' => dp_schema_text(__t('home', 'Home')),
        'item' => front_url(),
    ];

    $items[] = [
        '@type' => 'ListItem',
        'position' => 2,
        'name' => dp_schema_text(__t('doctors', 'Doctors')),
        'item' => front_url('doctors'),
    ];

    $position = 3;

    if (!empty($division) && empty($district)) {
        $items[] = [
            '@type' => 'ListItem',
            'position' => $position++,
            'name' => dp_schema_text(dp_seo_row_name($division, dp_current_seo_lang(), '')),
            'item' => dp_doctors_url([
                'division_slug' => dp_row_slug($division),
            ]),
        ];
    }

    if (!empty($district)) {
        $items[] = [
            '@type' => 'ListItem',
            'position' => $position++,
            'name' => dp_schema_text(dp_seo_row_name($district, dp_current_seo_lang(), '')),
            'item' => dp_doctors_url([
                'district_slug' => dp_row_slug($district),
            ]),
        ];
    }

    if (!empty($thana)) {
        $items[] = [
            '@type' => 'ListItem',
            'position' => $position++,
            'name' => dp_schema_text(dp_seo_row_name($thana, dp_current_seo_lang(), '')),
            'item' => dp_doctors_url([
                'district_slug' => dp_row_slug($district),
                'thana_slug' => dp_row_slug($thana),
            ]),
        ];
    }

    if (!empty($specialty)) {
        $specialty_url_args = empty($district)
            ? ['specialty_slug' => dp_row_slug($specialty)]
            : [
                'district_slug' => dp_row_slug($district),
                'thana_slug' => !empty($thana) ? dp_row_slug($thana) : '',
                'specialty_slug' => dp_row_slug($specialty),
            ];

        $items[] = [
            '@type' => 'ListItem',
            'position' => $position++,
            'name' => dp_schema_text(dp_seo_row_name($specialty, dp_current_seo_lang(), '')),
            'item' => dp_doctors_url($specialty_url_args),
        ];
    }

    /*
     * Keep the current page URL as the last breadcrumb when it is different
     * from the nearest directory context URL, for example paginated/search pages.
     */
    $last_item = end($items);
    $last_url = is_array($last_item) ? (string)($last_item['item'] ?? '') : '';

    if ($canonical_url !== '' && $canonical_url !== $last_url && $page_step !== 'not_found') {
        $items[] = [
            '@type' => 'ListItem',
            'position' => $position,
            'name' => dp_schema_text(__t('current_page', 'Current Page')),
            'item' => $canonical_url,
        ];
    }

    return $items;
}

function dp_build_directory_schema(
    string $page_step,
    string $page_title,
    string $meta_description,
    string $canonical_url,
    string $site_name,
    array $division,
    array $district,
    array $thana,
    array $specialty,
    array $doctors,
    int $total_doctors,
    string $social_image = ''
): array {
    if ($page_step === 'not_found' || $canonical_url === '') {
        return [];
    }

    $schema_page_name = dp_schema_text(dp_schema_page_name($page_title, $site_name));
    $schema_description = dp_schema_text($meta_description);
    $website_url = front_url();
    $website_id = rtrim($website_url, '/') . '/#website';
    $webpage_id = $canonical_url . '#webpage';
    $breadcrumb_id = $canonical_url . '#breadcrumb';

    $website_schema = [
        '@type' => 'WebSite',
        '@id' => $website_id,
        'url' => $website_url,
        'name' => dp_schema_text($site_name),
        'inLanguage' => (defined('CURRENT_LANG') && CURRENT_LANG === 'bn') ? 'bn-BD' : 'en',
    ];

    $breadcrumb_schema = [
        '@type' => 'BreadcrumbList',
        '@id' => $breadcrumb_id,
        'itemListElement' => dp_schema_breadcrumb_items(
            $division,
            $district,
            $thana,
            $specialty,
            $page_step,
            $canonical_url
        ),
    ];

    $page_schema = [
        '@type' => 'CollectionPage',
        '@id' => $webpage_id,
        'url' => $canonical_url,
        'name' => $schema_page_name,
        'description' => $schema_description,
        'isPartOf' => [
            '@id' => $website_id,
        ],
        'breadcrumb' => [
            '@id' => $breadcrumb_id,
        ],
        'inLanguage' => (defined('CURRENT_LANG') && CURRENT_LANG === 'bn') ? 'bn-BD' : 'en',
    ];

    if ($page_step === 'doctor_list') {
        $item_list = [];
        $position = 1;
        $schema_lang = dp_current_seo_lang();
        $location_name = dp_auto_article_location_name($district, $thana, $schema_lang);
        $specialty_name = dp_schema_text(dp_seo_row_name($specialty, $schema_lang, ''));

        foreach ($doctors as $doctor) {
            $doctor_name = dp_schema_text((string)($doctor['name'] ?? ''));

            if ($doctor_name === '') {
                continue;
            }

            $physician = [
                '@type' => 'Physician',
                '@id' => dp_schema_doctor_url($doctor, $canonical_url) . '#physician-' . $position,
                'name' => $doctor_name,
                'url' => dp_schema_doctor_url($doctor, $canonical_url),
            ];

            $doctor_image = dp_schema_doctor_image($doctor);
            if ($doctor_image !== '') {
                $physician['image'] = $doctor_image;
            }

            /*
             * Load active chambers once. The same chamber rows are used for
             * doctor-phone fallback and for Hospital affiliation schema.
             */
            $doctor_chambers = dp_schema_get_doctor_chambers((int)($doctor['id'] ?? 0));

            $doctor_telephone = dp_schema_doctor_telephone($doctor, $doctor_chambers);
            if ($doctor_telephone !== '') {
                $physician['telephone'] = $doctor_telephone;
            }

            $doctor_price_range = dp_schema_doctor_price_range($doctor, $doctor_chambers);
            if ($doctor_price_range !== '') {
                $physician['priceRange'] = $doctor_price_range;
            }

            $physician['address'] = dp_schema_doctor_address($doctor, $district, $thana, $doctor_chambers);

            /*
             * Show every active chamber/hospital for this doctor.
             * Each Hospital item includes available address, phone, image, fee
             * and visiting-hours data without changing the existing page query.
             */
            $chamber_affiliations = dp_schema_build_chamber_affiliations(
                $doctor,
                $district,
                $thana,
                $doctor_chambers
            );
            if (!empty($chamber_affiliations)) {
                $physician['hospitalAffiliation'] = $chamber_affiliations;
            }

            if ($specialty_name !== '') {
                $medical_specialty_url = dp_schema_medical_specialty_url($specialty_name);

                if ($medical_specialty_url !== '') {
                    $physician['medicalSpecialty'] = $medical_specialty_url;
                }

                /* Preserve a readable specialty description without using
                 * free text as an invalid medicalSpecialty enum value. */
                $physician['description'] = dp_current_seo_lang() === 'bn'
                    ? dp_seo_bangla_specialty_label($specialty)
                    : 'Specialist in ' . $specialty_name;
            }

            if ($location_name !== '') {
                $physician['areaServed'] = [
                    '@type' => 'AdministrativeArea',
                    'name' => dp_schema_text($location_name),
                ];
            }

            $item_list[] = [
                '@type' => 'ListItem',
                'position' => $position++,
                'item' => $physician,
            ];
        }

        if (!empty($item_list)) {
            $item_list_id = $canonical_url . '#doctor-list';

            $page_schema['mainEntity'] = [
                '@id' => $item_list_id,
            ];

            $doctor_list_schema = [
                '@type' => 'ItemList',
                '@id' => $item_list_id,
                'name' => $schema_page_name,
                'numberOfItems' => max(0, $total_doctors),
                'itemListElement' => $item_list,
            ];
        }
    }

    $graph = [$website_schema, $breadcrumb_schema, $page_schema];

    if (!empty($doctor_list_schema)) {
        $graph[] = $doctor_list_schema;
    }

    /*
     * The directory result page is also published as an Article entity.
     * CollectionPage remains the main page type because this page is a doctor
     * directory, while Article provides a clear article-level declaration for
     * the page content without changing visible content or doctor-list logic.
     */
    if ($page_step === 'doctor_list') {
        /*
         * Article schema uses the same final SEO variables that are used by
         * the page. Therefore, when a manual/dynamic directory article sets:
         * - Title  -> page Meta Title
         * - Intro  -> page Meta Description
         *
         * the Article JSON-LD headline and description automatically use
         * those exact same final values as well.
         */
        $article_schema_headline = $schema_page_name;
        $article_schema_description = $schema_description;

        $article_schema = [
            '@type' => 'Article',
            '@id' => $canonical_url . '#article',
            'mainEntityOfPage' => [
                '@id' => $webpage_id,
            ],
            'url' => $canonical_url,
            'headline' => $article_schema_headline,
            'description' => $article_schema_description,
            'inLanguage' => (defined('CURRENT_LANG') && CURRENT_LANG === 'bn') ? 'bn-BD' : 'en',
            'isPartOf' => [
                '@id' => $website_id,
            ],
            /*
             * BreadcrumbList remains a separate graph entity and is linked
             * from CollectionPage. Do not add "breadcrumb" to Article because
             * validators do not recognize it for the Article type.
             */
            'author' => [
                '@type' => 'Organization',
                'name' => dp_schema_text($site_name),
                'url' => $website_url,
            ],
            'publisher' => [
                '@type' => 'Organization',
                'name' => dp_schema_text($site_name),
                'url' => $website_url,
            ],
        ];

        if (!empty($doctor_list_schema)) {
            $article_schema['mainEntity'] = [
                '@id' => $doctor_list_schema['@id'],
            ];
        }

        /*
         * Use the same social image that is published in the Open Graph and
         * Twitter metadata. This keeps Article JSON-LD, link previews and the
         * directory page visually consistent.
         */
        $article_image = trim($social_image);

        if ($article_image === '') {
            $article_image = dp_schema_absolute_url(
                dp_schema_site_setting_value('default_og_image', '')
            );
        }

        if ($article_image !== '') {
            $article_schema['image'] = $article_image;
        }

        $graph[] = $article_schema;
    }

    return [
        '@context' => 'https://schema.org',
        '@graph' => $graph,
    ];
}

function dp_render_directory_schema(array $schema): string
{
    if (empty($schema)) {
        return '';
    }

    $json = json_encode(
        $schema,
        JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
        | JSON_HEX_TAG
        | JSON_HEX_AMP
        | JSON_HEX_APOS
        | JSON_HEX_QUOT
    );

    if ($json === false) {
        return '';
    }

    return "<script type=\"application/ld+json\">\n" . $json . "\n</script>";
}

/*
|--------------------------------------------------------------------------
| Directory Open Graph Image
|--------------------------------------------------------------------------
| A directory page needs a share image for Facebook, Messenger, WhatsApp,
| X and other social platforms. Priority is intentionally predictable:
|
| 1. Current specialty image when a specialty is selected
| 2. The first listed doctor's prepared social card (doctors.og_image)
| 3. Current thana, district or division image
| 4. The first listed doctor's regular photo
| 5. Default site Open Graph image
|
| The variables below are read by includes/header.php before it renders the
| <head> section. The same selected image is also passed to Article JSON-LD.
|--------------------------------------------------------------------------
*/
function dp_directory_og_image_type(string $image_url): string
{
    $path = (string)(parse_url($image_url, PHP_URL_PATH) ?: $image_url);
    $extension = strtolower((string)pathinfo($path, PATHINFO_EXTENSION));

    return match ($extension) {
        'jpg', 'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
        'gif' => 'image/gif',
        default => '',
    };
}

function dp_directory_og_image_dimensions(string $image_url): array
{
    $path = strtolower((string)(parse_url($image_url, PHP_URL_PATH) ?: $image_url));

    /* Generated doctor social cards are stored as 1200 × 630 JPEG files. */
    if (
        str_contains($path, '/uploads/doctor-cards/')
        || str_contains($path, '/uploads/bn-doctor-cards/')
    ) {
        return [1200, 630];
    }

    return [0, 0];
}

function dp_directory_og_image_details(
    string $page_title,
    string $site_name,
    string $page_step,
    array $division,
    array $district,
    array $thana,
    array $specialty,
    array $doctors
): array {
    $candidates = [];

    /*
     * Specialty archive pages must use the selected specialty image first.
     * Example: /doctors/cardiology uses specialties.image for Cardiology.
     */
    if ($page_step === 'doctor_list' && !empty($specialty)) {
        $specialty_image = trim((string)($specialty['image'] ?? ''));

        if ($specialty_image !== '') {
            $candidates[] = $specialty_image;
        }
    }

    /* Use a dedicated doctor social card only after the specialty image. */
    if ($page_step === 'doctor_list') {
        foreach ($doctors as $doctor) {
            $doctor_og_image = trim((string)($doctor['og_image'] ?? ''));

            if ($doctor_og_image !== '') {
                $candidates[] = $doctor_og_image;
                break;
            }
        }
    }

    /* Add remaining contextual images as fallbacks without duplicating specialty. */
    foreach ([$thana, $district, $division] as $context_item) {
        $context_image = trim((string)($context_item['image'] ?? ''));

        if ($context_image !== '') {
            $candidates[] = $context_image;
        }
    }


    /* A doctor profile photo is a useful final page-specific fallback. */
    foreach ($doctors as $doctor) {
        $doctor_image = trim((string)($doctor['image'] ?? ''));

        if ($doctor_image !== '') {
            $candidates[] = $doctor_image;
            break;
        }
    }

    $candidates[] = dp_schema_site_setting_value(
        'default_og_image',
        'assets/images/default-og-image.webp'
    );

    $image_url = '';

    foreach ($candidates as $candidate) {
        $candidate = trim((string)$candidate);

        if ($candidate === '') {
            continue;
        }

        $candidate_url = dp_schema_absolute_url($candidate);

        if ($candidate_url !== '' && preg_match('/^https?:\/\//i', $candidate_url)) {
            $image_url = $candidate_url;
            break;
        }
    }

    /*
     * Final hard fallback: every public directory page must expose a valid
     * absolute share image, even when the current doctor, specialty and
     * Site Settings image values are empty or malformed.
     */
    if ($image_url === '') {
        $image_url = dp_schema_absolute_url('assets/images/default-og-image.webp');
    }

    $alt = trim((string)preg_replace(
        '/\s*\|\s*' . preg_quote($site_name, '/') . '\s*$/u',
        '',
        $page_title
    ));

    if ($alt === '') {
        $alt = $site_name;
    }

    [$width, $height] = dp_directory_og_image_dimensions($image_url);

    return [
        'url' => $image_url,
        'alt' => $alt,
        'type' => dp_directory_og_image_type($image_url),
        'width' => $width,
        'height' => $height,
    ];
}

$directory_og_image = dp_directory_og_image_details(
    $page_title,
    $site_name,
    $page_step,
    $division,
    $district,
    $thana,
    $specialty,
    $doctors
);

/* Header variables: includes/header.php renders these inside <head>. */
$og_image = trim((string)($directory_og_image['url'] ?? ''));

/* Keep the shared header and social crawlers on one guaranteed absolute URL. */
if ($og_image === '' || !preg_match('/^https?:\/\//i', $og_image)) {
    $og_image = dp_schema_absolute_url('assets/images/default-og-image.webp');
}

$og_image_alt = (string)($directory_og_image['alt'] ?? '');
$og_image_type = (string)($directory_og_image['type'] ?? '');
$og_image_width = (int)($directory_og_image['width'] ?? 0);
$og_image_height = (int)($directory_og_image['height'] ?? 0);
$og_title = $page_title;
$og_description = $meta_description;
$og_type = 'website';
$twitter_card = 'summary_large_image';

$directory_schema = dp_build_directory_schema(
    $page_step,
    $page_title,
    $meta_description,
    $canonical_url,
    $site_name,
    $division,
    $district,
    $thana,
    $specialty,
    $doctors,
    $total_doctors,
    $og_image
);
