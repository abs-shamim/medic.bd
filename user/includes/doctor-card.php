<?php if (!empty($doctor) && is_array($doctor)): ?>
<?php
/*
|--------------------------------------------------------------------------
| User Doctor Card
|--------------------------------------------------------------------------
| Safe card for:
| user/includes/doctor-card.php
|
| Works even if helper functions like doctor_url(), verified_badge(),
| site_url(), get_site_setting() are missing.
|--------------------------------------------------------------------------
*/

if (!function_exists('user_dc_e')) {
    function user_dc_e($value): string
    {
        if (function_exists('e')) {
            return e($value);
        }

        return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('user_dc_value')) {
    function user_dc_value(array $row, array $keys, string $default = ''): string
    {
        foreach ($keys as $key) {
            if (isset($row[$key]) && trim((string)$row[$key]) !== '') {
                return trim((string)$row[$key]);
            }
        }

        return $default;
    }
}

if (!function_exists('user_dc_short_location_from_address')) {
    function user_dc_short_location_from_address(string $address): array
    {
        $address = trim($address);

        if ($address === '') {
            return [
                'thana' => '',
                'district' => '',
            ];
        }

        $parts = preg_split('/[,|]+/u', $address);
        $clean_parts = [];

        foreach ($parts as $part) {
            $part = trim((string)$part);

            if ($part === '') {
                continue;
            }

            $part = preg_replace('/\b(post\s*code|postcode|postal\s*code)\b.*$/iu', '', $part);
            $part = trim((string)$part, " \t\n\r\0\x0B-:");

            if ($part === '') {
                continue;
            }

            if (preg_match('/^(house|building|road|street|floor|flat|block|sector|plot|lane|room)\b/i', $part)) {
                continue;
            }

            if (preg_match('/^\d{3,6}$/', $part)) {
                continue;
            }

            $clean_parts[] = $part;
        }

        $clean_parts = array_values(array_unique($clean_parts));

        $district = '';
        $thana = '';

        if (!empty($clean_parts)) {
            $district = $clean_parts[count($clean_parts) - 1];
        }

        if (count($clean_parts) >= 2) {
            $thana = $clean_parts[count($clean_parts) - 2];
        }

        return [
            'thana' => $thana,
            'district' => $district,
        ];
    }
}

if (!function_exists('user_dc_valid_fee')) {
    function user_dc_valid_fee($value): float
    {
        $value = trim((string)($value ?? ''));

        if ($value === '') {
            return 0;
        }

        $value = str_replace([',', '৳', 'tk', 'Tk', 'BDT', 'bdt'], '', $value);
        $value = trim($value);

        if (!is_numeric($value)) {
            return 0;
        }

        $fee = (float)$value;

        return $fee > 0 ? $fee : 0;
    }
}

if (!function_exists('user_dc_first_available_fee')) {
    function user_dc_first_available_fee(array $doctor): float
    {
        $doctor_fee = user_dc_valid_fee($doctor['consultation_fee'] ?? '');

        if ($doctor_fee > 0) {
            return $doctor_fee;
        }

        $first_chamber_fee = user_dc_valid_fee($doctor['first_chamber_fee'] ?? '');

        if ($first_chamber_fee > 0) {
            return $first_chamber_fee;
        }

        $first_chamber_consultation_fee = user_dc_valid_fee($doctor['first_chamber_consultation_fee'] ?? '');

        if ($first_chamber_consultation_fee > 0) {
            return $first_chamber_consultation_fee;
        }

        $fee_list_text = trim((string)(
            $doctor['all_chamber_fees']
            ?? $doctor['chamber_fees']
            ?? $doctor['consultation_fees']
            ?? $doctor['all_consultation_fees']
            ?? ''
        ));

        if ($fee_list_text !== '') {
            $fees = preg_split('/[,|]+/u', $fee_list_text);

            foreach ($fees as $fee_item) {
                $fee = user_dc_valid_fee($fee_item);

                if ($fee > 0) {
                    return $fee;
                }
            }
        }

        return 0;
    }
}

if (!function_exists('user_dc_asset_url')) {
    function user_dc_asset_url(string $path, string $fallback = ''): string
    {
        $path = trim($path);

        if ($path === '') {
            $path = $fallback;
        }

        if ($path === '') {
            return '';
        }

        if (preg_match('/^https?:\/\//i', $path)) {
            return $path;
        }

        $path = str_replace('\\', '/', $path);
        $path = preg_replace('#^\.\./+#', '', $path);
        $path = ltrim($path, '/');

        if (function_exists('site_url')) {
            return site_url($path);
        }

        if (defined('BASE_URL') && BASE_URL !== '') {
            return rtrim((string)BASE_URL, '/') . '/' . $path;
        }

        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? '';

        return $host !== '' ? $scheme . '://' . $host . '/' . $path : '/' . $path;
    }
}

if (!function_exists('user_dc_site_setting')) {
    function user_dc_site_setting(string $key, string $default = ''): string
    {
        global $pdo;

        $key = trim($key);

        if ($key === '') {
            return $default;
        }

        if (function_exists('get_site_setting')) {
            $value = get_site_setting($key, $default);
            return trim((string)$value) !== '' ? trim((string)$value) : $default;
        }

        if (!isset($pdo)) {
            return $default;
        }

        try {
            $stmt = $pdo->prepare("
                SELECT setting_value
                FROM site_settings
                WHERE setting_key = :setting_key
                LIMIT 1
            ");

            $stmt->execute([
                ':setting_key' => $key,
            ]);

            $value = trim((string)$stmt->fetchColumn());

            return $value !== '' ? $value : $default;
        } catch (Throwable $e) {
            return $default;
        }
    }
}

if (!function_exists('user_dc_years_experience_text')) {
    function user_dc_years_experience_text($starting_year): string
    {
        $starting_year = (int)trim((string)($starting_year ?? ''));
        $current_year = (int)date('Y');

        if ($starting_year < 1900 || $starting_year > $current_year) {
            return 'Not specified';
        }

        $years = $current_year - $starting_year;

        if ($years < 0) {
            $years = 0;
        }

        return (string)$years . '+ Years';
    }
}

if (!function_exists('user_dc_verified_badge')) {
    function user_dc_verified_badge(int $is_verified): string
    {
        if (function_exists('verified_badge')) {
            return verified_badge($is_verified);
        }

        if ($is_verified !== 1) {
            return '';
        }

        return '<span class="medic-doctor-verified">Verified</span>';
    }
}

if (!function_exists('user_dc_doctor_url')) {
    function user_dc_doctor_url(array $doctor): string
    {
        if (function_exists('doctor_url')) {
            return doctor_url($doctor);
        }

        $slug = trim((string)($doctor['slug'] ?? ''));

        if ($slug !== '') {
            return user_dc_asset_url('doctor/' . $slug);
        }

        $id = (int)($doctor['id'] ?? 0);

        if ($id > 0) {
            return user_dc_asset_url('doctor.php?id=' . $id);
        }

        return '#';
    }
}

if (!function_exists('user_dc_initial')) {
    function user_dc_initial(string $name): string
    {
        $name = trim($name);

        if ($name === '') {
            return 'D';
        }

        return function_exists('mb_substr') ? mb_substr($name, 0, 1) : substr($name, 0, 1);
    }
}

$first_chamber_name = user_dc_value($doctor, [
    'first_chamber_name',
    'chamber_name',
    'primary_hospital',
    'hospital_name',
]);

$first_chamber_address = user_dc_value($doctor, [
    'first_chamber_address',
    'chamber_address',
    'hospital_address',
]);

$desktop_hospital = user_dc_value($doctor, [
    'hospital_name',
    'all_chamber_names',
    'chambers_name',
    'primary_hospital',
]);

if ($desktop_hospital === '') {
    $desktop_hospital = 'Not specified';
}

$desktop_location = user_dc_value($doctor, [
    'address',
    'first_chamber_address',
    'chamber_address',
    'hospital_address',
    'city',
]);

if ($desktop_location === '') {
    $desktop_location = 'Not specified';
}

$mobile_hospital = user_dc_value($doctor, [
    'primary_hospital',
    'first_chamber_name',
    'chamber_name',
    'hospital_name',
]);

if ($mobile_hospital === '') {
    $mobile_hospital = 'Not specified';
}

$mobile_thana = user_dc_value($doctor, [
    'doctor_thana',
    'thana_name',
    'thana',
]);

$mobile_district = user_dc_value($doctor, [
    'doctor_district',
    'district_name',
    'district',
    'city',
]);

if (($mobile_thana === '' || $mobile_district === '') && $first_chamber_address !== '') {
    $parsed_location = user_dc_short_location_from_address($first_chamber_address);

    if ($mobile_thana === '' && !empty($parsed_location['thana'])) {
        $mobile_thana = $parsed_location['thana'];
    }

    if ($mobile_district === '' && !empty($parsed_location['district'])) {
        $mobile_district = $parsed_location['district'];
    }
}

$mobile_location_parts = [];

if ($mobile_thana !== '') {
    $mobile_location_parts[] = $mobile_thana;
}

if ($mobile_district !== '' && $mobile_district !== $mobile_thana) {
    $mobile_location_parts[] = $mobile_district;
}

$mobile_location = !empty($mobile_location_parts)
    ? implode(', ', $mobile_location_parts)
    : 'Not specified';

$card_consultation_fee = user_dc_first_available_fee($doctor);

$card_consultation_fee_text = $card_consultation_fee > 0
    ? '৳' . number_format($card_consultation_fee, 0)
    : 'Unknown';

$profile_link = user_dc_doctor_url($doctor);

$doctor_default_image_setting = user_dc_site_setting(
    'default_doctor_image',
    'assets/images/default-doctor.webp'
);

$doctor_image_url = user_dc_asset_url(
    (string)($doctor['image'] ?? ''),
    $doctor_default_image_setting
);

$doctor_default_png_url = user_dc_asset_url('assets/images/default-doctor.png');

$years_experience_text = user_dc_years_experience_text($doctor['experience_years'] ?? '');

$doctor_name = trim((string)($doctor['name'] ?? 'Doctor'));
$doctor_designation = trim((string)($doctor['designation'] ?? ''));
$doctor_specialty = trim((string)($doctor['specialty_name'] ?? ''));
$doctor_degree = trim((string)($doctor['degree'] ?? ''));
$doctor_rating = trim((string)($doctor['rating'] ?? '0'));
$doctor_reviews_count = trim((string)($doctor['reviews_count'] ?? '0'));
?>

<article class="medic-doctor-list-item">

  <style>
    .medic-doctor-list-item {
      display: grid;
      grid-template-columns: 58px minmax(0, 1fr) 170px;
      gap: 14px;
      align-items: center;
      background: #ffffff;
      border: 1px solid #d0d7de;
      border-radius: 6px;
      padding: 12px;
      width: 100%;
    }

    .medic-doctor-list-item:hover {
      background: #f6f8fa;
      border-color: #8c959f;
    }

    .medic-doctor-list-photo {
      width: 58px;
      height: 58px;
      border-radius: 50%;
      overflow: hidden;
      background: #f6f8fa;
      border: 1px solid #d0d7de;
      flex-shrink: 0;
    }

    .medic-doctor-list-photo img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      display: block;
    }

    .medic-doctor-list-photo-fallback {
      width: 100%;
      height: 100%;
      display: none;
      align-items: center;
      justify-content: center;
      background: linear-gradient(135deg, #2da44e, #0969da);
      color: #ffffff;
      font-size: 22px;
      font-weight: 800;
      text-transform: uppercase;
    }

    .medic-doctor-list-main {
      min-width: 0;
    }

    .medic-doctor-list-title {
      display: flex;
      align-items: center;
      flex-wrap: wrap;
      gap: 6px;
      margin-bottom: 3px;
    }

    .medic-doctor-list-title h3 {
      margin: 0;
      color: #24292f;
      font-size: 16px;
      line-height: 1.35;
      font-weight: 700;
    }

    .medic-doctor-list-title h3 a {
      color: #0969da;
      text-decoration: none;
    }

    .medic-doctor-list-title h3 a:hover {
      text-decoration: underline;
    }

    .medic-doctor-verified {
      display: inline-flex;
      align-items: center;
      min-height: 20px;
      padding: 2px 7px;
      border-radius: 999px;
      background: #dafbe1;
      color: #1a7f37;
      border: 1px solid rgba(26, 127, 55, 0.25);
      font-size: 11px;
      font-weight: 700;
    }

    .medic-doctor-list-specialty {
      display: block;
      margin-bottom: 6px;
      color: #57606a;
      font-size: 13px;
      line-height: 1.4;
      font-weight: 600;
    }

    .medic-doctor-list-info {
      display: flex;
      flex-wrap: wrap;
      gap: 5px 12px;
      color: #57606a;
      font-size: 13px;
      line-height: 1.45;
    }

    .medic-doctor-list-info span {
      display: inline-flex;
      align-items: center;
      max-width: 100%;
      min-width: 0;
    }

    .medic-doctor-list-info strong {
      color: #24292f;
      font-weight: 600;
      margin-right: 4px;
      flex-shrink: 0;
    }

    .medic-doctor-list-info em {
      color: #57606a;
      font-style: normal;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
      min-width: 0;
    }

    .medic-mobile-info {
      display: none;
    }

    .medic-doctor-list-side {
      text-align: right;
    }

    .medic-doctor-list-fee {
      color: #24292f;
      font-size: 15px;
      font-weight: 700;
      margin-bottom: 4px;
    }

    .medic-doctor-list-rating {
      color: #57606a;
      font-size: 12px;
      line-height: 1.4;
      margin-bottom: 8px;
    }

    .medic-doctor-list-actions {
      display: flex;
      justify-content: flex-end;
      gap: 6px;
    }

    .medic-list-btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      min-height: 32px;
      padding: 0 10px;
      border-radius: 6px;
      border: 1px solid #d0d7de;
      background: #ffffff;
      color: #0969da;
      font-size: 13px;
      font-weight: 600;
      text-decoration: none;
      white-space: nowrap;
    }

    .medic-list-btn:hover {
      background: #f6f8fa;
    }

    .medic-list-btn-primary {
      background: #2da44e;
      border-color: #2da44e;
      color: #ffffff;
    }

    .medic-list-btn-primary:hover {
      background: #1f883d;
      border-color: #1f883d;
      color: #ffffff;
    }

    @media (max-width: 760px) {
      .medic-doctor-list-item {
        grid-template-columns: 52px minmax(0, 1fr);
        align-items: start;
      }

      .medic-doctor-list-photo {
        width: 52px;
        height: 52px;
      }

      .medic-desktop-info {
        display: none;
      }

      .medic-mobile-info {
        grid-column: 1 / -1;
        display: grid;
        gap: 5px;
        width: 100%;
        margin-top: 8px;
        padding-top: 10px;
        border-top: 1px solid #d0d7de;
        text-align: left;
      }

      .medic-mobile-info span {
        display: grid;
        grid-template-columns: 82px minmax(0, 1fr);
        align-items: start;
        gap: 0;
        width: 100%;
      }

      .medic-mobile-info strong {
        margin-right: 0;
        color: #24292f;
      }

      .medic-mobile-info em {
        white-space: normal;
        text-align: left;
      }

      .medic-doctor-list-side {
        grid-column: 1 / -1;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        text-align: left;
        border-top: 1px solid #d0d7de;
        padding-top: 10px;
      }

      .medic-doctor-list-actions {
        justify-content: flex-end;
      }
    }

    @media (max-width: 480px) {
      .medic-doctor-list-item {
        grid-template-columns: 52px minmax(0, 1fr);
        gap: 10px;
      }

      .medic-mobile-info span {
        grid-template-columns: 78px minmax(0, 1fr);
      }

      .medic-doctor-list-side {
        align-items: stretch;
        flex-direction: column;
      }

      .medic-doctor-list-actions {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 8px;
      }

      .medic-list-btn {
        width: 100%;
      }
    }

    @media (max-width: 360px) {
      .medic-doctor-list-item {
        grid-template-columns: 1fr;
      }

      .medic-doctor-list-photo {
        width: 56px;
        height: 56px;
      }

      .medic-mobile-info span {
        grid-template-columns: 1fr;
        gap: 2px;
      }

      .medic-doctor-list-actions {
        grid-template-columns: 1fr;
      }
    }
  </style>

  <div class="medic-doctor-list-photo">
    <?php if ($doctor_image_url !== ''): ?>
      <img
        src="<?= user_dc_e($doctor_image_url) ?>"
        alt="<?= user_dc_e($doctor_name) ?>"
        loading="lazy"
        onerror="this.onerror=null;this.style.display='none';this.parentNode.querySelector('.medic-doctor-list-photo-fallback').style.display='flex';"
      >
    <?php endif; ?>

    <span class="medic-doctor-list-photo-fallback" style="<?= $doctor_image_url === '' ? 'display:flex;' : '' ?>">
      <?= user_dc_e(user_dc_initial($doctor_name)) ?>
    </span>
  </div>

  <div class="medic-doctor-list-main">
    <div class="medic-doctor-list-title">
      <h3>
        <?php if ($profile_link !== '#'): ?>
          <a href="<?= user_dc_e($profile_link) ?>">
            <?= user_dc_e($doctor_name) ?>
          </a>
        <?php else: ?>
          <?= user_dc_e($doctor_name) ?>
        <?php endif; ?>
      </h3>

      <?= user_dc_verified_badge((int)($doctor['is_verified'] ?? 0)) ?>
    </div>

    <span class="medic-doctor-list-specialty">
      <?= user_dc_e($doctor_designation !== '' ? $doctor_designation : $doctor_specialty) ?>
    </span>

    <div class="medic-doctor-list-info medic-desktop-info">
      <?php if ($doctor_degree !== ''): ?>
        <span>
          <strong>Degree:</strong>
          <em><?= user_dc_e($doctor_degree) ?></em>
        </span>
      <?php endif; ?>

      <span>
        <strong>Hospital:</strong>
        <em><?= user_dc_e($desktop_hospital) ?></em>
      </span>

      <span>
        <strong>Location:</strong>
        <em><?= user_dc_e($desktop_location) ?></em>
      </span>

      <span>
        <strong>Experience:</strong>
        <em><?= user_dc_e($years_experience_text) ?></em>
      </span>
    </div>
  </div>

  <div class="medic-doctor-list-info medic-mobile-info">
    <?php if ($doctor_degree !== ''): ?>
      <span>
        <strong>Degree:</strong>
        <em><?= user_dc_e($doctor_degree) ?></em>
      </span>
    <?php endif; ?>

    <span>
      <strong>Hospital:</strong>
      <em><?= user_dc_e($mobile_hospital) ?></em>
    </span>

    <span>
      <strong>Location:</strong>
      <em><?= user_dc_e($mobile_location) ?></em>
    </span>

    <span>
      <strong>Experience:</strong>
      <em><?= user_dc_e($years_experience_text) ?></em>
    </span>
  </div>

  <div class="medic-doctor-list-side">
    <div>
      <div class="medic-doctor-list-fee">
        <?= user_dc_e($card_consultation_fee_text) ?>
      </div>

      <div class="medic-doctor-list-rating">
        <?= user_dc_e($doctor_rating) ?> rating · <?= user_dc_e($doctor_reviews_count) ?> reviews
      </div>
    </div>

    <div class="medic-doctor-list-actions">
      <?php if ($profile_link !== '#'): ?>
        <a href="<?= user_dc_e($profile_link) ?>" class="medic-list-btn">
          Profile
        </a>
      <?php endif; ?>

      <a href="<?= user_dc_e($profile_link !== '#' ? $profile_link : '#') ?>" class="medic-list-btn medic-list-btn-primary">
        Appointment
      </a>
    </div>
  </div>

</article>
<?php endif; ?>