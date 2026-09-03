<?php
declare(strict_types=1);

require_once __DIR__ . '/social-hashtags.php';

if (!function_exists('medic_social_strlen')) {
    function medic_social_strlen(string $value): int
    {
        return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
    }
}

if (!function_exists('medic_social_substr')) {
    function medic_social_substr(string $value, int $start, int $length): string
    {
        return function_exists('mb_substr')
            ? mb_substr($value, $start, $length, 'UTF-8')
            : substr($value, $start, $length);
    }
}

if (!function_exists('medic_social_limit')) {
    function medic_social_limit(string $value, int $limit): string
    {
        $value = trim($value);

        if ($limit <= 0 || medic_social_strlen($value) <= $limit) {
            return $value;
        }

        return rtrim(medic_social_substr($value, 0, max(1, $limit - 1))) . '…';
    }
}

if (!function_exists('medic_social_join_lines')) {
    function medic_social_join_lines(array $lines): string
    {
        $lines = array_values(array_filter(array_map('medic_social_text', $lines), static function (string $line): bool {
            return $line !== '';
        }));

        return implode("\n", $lines);
    }
}

if (!function_exists('medic_social_doctor_details')) {
    function medic_social_doctor_details(array $doctor): array
    {
        return [
            'name' => medic_social_text($doctor['name'] ?? ''),
            'degree' => medic_social_text($doctor['degree'] ?? ''),
            'designation' => medic_social_text($doctor['designation'] ?? ''),
            'specialty' => medic_social_text($doctor['specialty_name'] ?? ''),
            'hospital' => medic_social_text($doctor['primary_hospital'] ?? ($doctor['hospital_name'] ?? '')),
            'district' => medic_social_text($doctor['district_name'] ?? ''),
            'link' => medic_social_text($doctor['profile_url'] ?? ''),
        ];
    }
}

if (!function_exists('medic_social_doctor_captions')) {
    /**
     * Creates editable defaults. Admin can change any caption before publishing.
     * The copy is informational only and avoids medical guarantees or advice.
     */
    function medic_social_doctor_captions(array $doctor): array
    {
        $d = medic_social_doctor_details($doctor);
        $professional = implode(', ', array_filter([$d['degree'], $d['designation']]));
        $profile_call = $d['link'] !== '' ? 'সম্পূর্ণ প্রোফাইল ও অ্যাপয়েন্টমেন্ট তথ্য: ' . $d['link'] : 'সম্পূর্ণ প্রোফাইল ও অ্যাপয়েন্টমেন্ট তথ্য MediCare-এ দেখুন।';

        $facebook = medic_social_join_lines([
            $d['name'] !== '' ? 'ডা. ' . $d['name'] : 'ডাক্তার প্রোফাইল',
            $professional,
            $d['specialty'] !== '' ? 'বিশেষজ্ঞতা: ' . $d['specialty'] : '',
            $d['hospital'] !== '' ? 'চেম্বার / হাসপাতাল: ' . $d['hospital'] : '',
            $profile_call,
            medic_social_doctor_hashtags($doctor, 'facebook'),
        ]);

        $instagram = medic_social_join_lines([
            $d['name'] !== '' ? 'Meet Dr. ' . $d['name'] : 'Meet a doctor on MediCare',
            $d['specialty'] !== '' ? $d['specialty'] . ' specialist' : '',
            $d['hospital'] !== '' ? 'Available at: ' . $d['hospital'] : '',
            $d['link'] !== '' ? 'Profile and appointment details: ' . $d['link'] : '',
            medic_social_doctor_hashtags($doctor, 'instagram'),
        ]);

        $linkedin = medic_social_join_lines([
            $d['name'] !== '' ? 'Doctor profile: Dr. ' . $d['name'] : 'Doctor profile on MediCare',
            $professional,
            $d['specialty'] !== '' ? 'Specialty: ' . $d['specialty'] : '',
            $d['hospital'] !== '' ? 'Practice location: ' . $d['hospital'] : '',
            $d['district'] !== '' ? 'Location: ' . $d['district'] : '',
            $d['link'] !== '' ? 'View profile and appointment information: ' . $d['link'] : '',
            medic_social_doctor_hashtags($doctor, 'linkedin'),
        ]);

        $x = medic_social_join_lines([
            $d['name'] !== '' ? 'Dr. ' . $d['name'] : 'Doctor profile',
            $d['specialty'] !== '' ? '• ' . $d['specialty'] : '',
            $d['link'],
            medic_social_doctor_hashtags($doctor, 'x'),
        ]);

        return [
            'facebook' => $facebook,
            'instagram' => $instagram,
            'linkedin' => $linkedin,
            'x' => medic_social_limit($x, 280),
        ];
    }
}
