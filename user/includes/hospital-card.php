<?php if (!empty($hospital) && is_array($hospital)): ?>
<?php
/*
|--------------------------------------------------------------------------
| User Hospital Card
|--------------------------------------------------------------------------
| File:
| user/includes/hospital-card.php
|
| Safe version:
| - hospital_url() না থাকলেও কাজ করবে
| - site_url() না থাকলেও image URL কাজ করবে
| - mb_strimwidth() না থাকলেও text short হবে
|--------------------------------------------------------------------------
*/

if (!function_exists('user_hc_e')) {
    function user_hc_e($value): string
    {
        if (function_exists('e')) {
            return e($value);
        }

        return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('user_hc_value')) {
    function user_hc_value(array $row, array $keys, string $default = ''): string
    {
        foreach ($keys as $key) {
            if (isset($row[$key]) && trim((string)$row[$key]) !== '') {
                return trim((string)$row[$key]);
            }
        }

        return $default;
    }
}

if (!function_exists('user_hc_short_text')) {
    function user_hc_short_text(string $text, int $limit = 100): string
    {
        $text = trim($text);

        if ($text === '') {
            return '';
        }

        if (function_exists('mb_strimwidth')) {
            return mb_strimwidth($text, 0, $limit, '...', 'UTF-8');
        }

        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            return mb_strlen($text, 'UTF-8') > $limit
                ? mb_substr($text, 0, $limit, 'UTF-8') . '...'
                : $text;
        }

        return strlen($text) > $limit ? substr($text, 0, $limit) . '...' : $text;
    }
}

if (!function_exists('user_hc_short_location_from_address')) {
    function user_hc_short_location_from_address(string $address): array
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
            $part = preg_replace('/-\s*\d{3,6}$/u', '', $part);
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

if (!function_exists('user_hc_site_setting')) {
    function user_hc_site_setting(string $key, string $default = ''): string
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

if (!function_exists('user_hc_asset_url')) {
    function user_hc_asset_url(string $path, string $fallback = ''): string
    {
        $path = trim($path);

        if ($path === '') {
            $path = trim($fallback);
        }

        if ($path === '') {
            return '';
        }

        if (preg_match('/^https?:\/\//i', $path)) {
            return $path;
        }

        $path = str_replace('\\', '/', $path);
        $path = preg_replace('#^(\.\./)+#', '', $path);
        $path = ltrim((string)$path, '/');

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

if (!function_exists('user_hc_initial')) {
    function user_hc_initial(string $name): string
    {
        $name = trim($name);

        if ($name === '') {
            return 'H';
        }

        if (function_exists('mb_substr')) {
            return mb_substr($name, 0, 1, 'UTF-8');
        }

        return substr($name, 0, 1);
    }
}

if (!function_exists('user_hc_hospital_url')) {
    function user_hc_hospital_url(array $hospital): string
    {
        if (function_exists('hospital_url')) {
            return hospital_url($hospital);
        }

        $slug = trim((string)($hospital['slug'] ?? ''));

        if ($slug !== '') {
            return user_hc_asset_url('hospital/' . $slug);
        }

        $id = (int)($hospital['id'] ?? 0);

        if ($id > 0) {
            return user_hc_asset_url('hospital.php?id=' . $id);
        }

        return '#';
    }
}

$hospital_id = (int)($hospital['id'] ?? 0);

$hospital_name = user_hc_value($hospital, [
    'name',
    'hospital_name',
], 'Hospital');

$hospital_type = user_hc_value($hospital, [
    'type',
    'hospital_type',
], 'Hospital');

$hospital_default_image_setting = user_hc_site_setting(
    'default_hospital_image',
    'assets/images/default-hospital.webp'
);

$hospital_image = user_hc_asset_url(
    user_hc_value($hospital, [
        'image',
        'photo',
        'logo',
        'hospital_logo',
        'profile_image',
    ]),
    $hospital_default_image_setting
);

$hospital_default_png_url = user_hc_asset_url('assets/images/default-hospital.png');

$hospital_desktop_location = user_hc_value($hospital, [
    'address',
    'hospital_address',
    'location',
    'city',
    'district_name',
    'district',
]);

if ($hospital_desktop_location === '') {
    $hospital_desktop_location = 'Not specified';
}

$hospital_mobile_thana = user_hc_value($hospital, [
    'thana_name',
    'thana',
    'hospital_thana',
]);

$hospital_mobile_district = user_hc_value($hospital, [
    'district_name',
    'district',
    'hospital_district',
    'city',
]);

if (($hospital_mobile_thana === '' || $hospital_mobile_district === '') && $hospital_desktop_location !== '') {
    $parsed_location = user_hc_short_location_from_address($hospital_desktop_location);

    if ($hospital_mobile_thana === '' && !empty($parsed_location['thana'])) {
        $hospital_mobile_thana = $parsed_location['thana'];
    }

    if ($hospital_mobile_district === '' && !empty($parsed_location['district'])) {
        $hospital_mobile_district = $parsed_location['district'];
    }
}

$hospital_mobile_location_parts = [];

if ($hospital_mobile_thana !== '') {
    $hospital_mobile_location_parts[] = $hospital_mobile_thana;
}

if ($hospital_mobile_district !== '' && $hospital_mobile_district !== $hospital_mobile_thana) {
    $hospital_mobile_location_parts[] = $hospital_mobile_district;
}

$hospital_mobile_location = !empty($hospital_mobile_location_parts)
    ? implode(', ', $hospital_mobile_location_parts)
    : 'Not specified';

$hospital_departments = (int)($hospital['departments_count'] ?? $hospital['department_count'] ?? 0);
$hospital_doctors = (int)($hospital['doctors_count'] ?? $hospital['doctor_count'] ?? 0);

$hospital_description = user_hc_value($hospital, [
    'description',
    'short_description',
]);

$hospital_phone = user_hc_value($hospital, [
    'phone',
    'mobile',
    'whatsapp',
    'whatsapp_number',
]);

$hospital_email = user_hc_value($hospital, [
    'email',
]);

$hospital_registration = user_hc_value($hospital, [
    'registration_number',
    'registration_no',
    'license_number',
]);

$is_verified = (int)($hospital['is_verified'] ?? 0);

$hospital_link = user_hc_hospital_url($hospital);
$hospital_appointment_link = $hospital_link;
?>

<article class="medic-hospital-list-item">

  <style>
    .medic-hospital-list-item {
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

    .medic-hospital-list-item:hover {
      background: #f6f8fa;
      border-color: #8c959f;
    }

    .medic-hospital-list-photo {
      width: 58px;
      height: 58px;
      border-radius: 10px;
      overflow: hidden;
      background: #f6f8fa;
      border: 1px solid #d0d7de;
      flex-shrink: 0;
    }

    .medic-hospital-list-photo img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      display: block;
    }

    .medic-hospital-photo-fallback {
      width: 100%;
      height: 100%;
      display: none;
      align-items: center;
      justify-content: center;
      background: linear-gradient(135deg, #0969da, #8250df);
      color: #ffffff;
      font-size: 22px;
      font-weight: 800;
      text-transform: uppercase;
    }

    .medic-hospital-list-main {
      min-width: 0;
    }

    .medic-hospital-list-title {
      display: flex;
      align-items: center;
      flex-wrap: wrap;
      gap: 6px;
      margin-bottom: 3px;
    }

    .medic-hospital-list-title h2 {
      margin: 0;
      color: #24292f;
      font-size: 16px;
      line-height: 1.35;
      font-weight: 700;
    }

    .medic-hospital-list-title h2 a {
      color: #0969da;
      text-decoration: none;
    }

    .medic-hospital-list-title h2 a:hover {
      text-decoration: underline;
    }

    .medic-hospital-list-type {
      display: flex;
      flex-wrap: wrap;
      gap: 6px;
      margin-bottom: 6px;
    }

    .medic-hospital-list-tag {
      display: inline-flex;
      align-items: center;
      min-height: 22px;
      padding: 2px 8px;
      border-radius: 999px;
      background: #ddf4ff;
      color: #0969da;
      border: 1px solid rgba(9, 105, 218, 0.18);
      font-size: 12px;
      font-weight: 600;
    }

    .medic-hospital-list-tag-success {
      background: #dafbe1;
      color: #1a7f37;
      border-color: rgba(26, 127, 55, 0.2);
    }

    .medic-hospital-list-info {
      display: flex;
      flex-wrap: wrap;
      gap: 5px 12px;
      color: #57606a;
      font-size: 13px;
      line-height: 1.45;
    }

    .medic-hospital-list-info span {
      display: inline-flex;
      align-items: center;
      max-width: 100%;
      min-width: 0;
    }

    .medic-hospital-list-info strong {
      color: #24292f;
      font-weight: 600;
      margin-right: 4px;
      flex-shrink: 0;
    }

    .medic-hospital-list-info em {
      color: #57606a;
      font-style: normal;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
      min-width: 0;
    }

    .medic-hospital-list-desc {
      margin: 6px 0 0;
      color: #57606a;
      font-size: 13px;
      line-height: 1.45;
    }

    .medic-hospital-mobile-info {
      display: none;
    }

    .medic-hospital-list-side {
      text-align: right;
    }

    .medic-hospital-list-status {
      color: #57606a;
      font-size: 12px;
      line-height: 1.4;
      margin-bottom: 8px;
    }

    .medic-hospital-list-status strong {
      color: #1a7f37;
      font-weight: 700;
    }

    .medic-hospital-list-id {
      color: #57606a;
      font-size: 12px;
      line-height: 1.4;
      margin-bottom: 8px;
    }

    .medic-hospital-list-actions {
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
      .medic-hospital-list-item {
        grid-template-columns: 52px minmax(0, 1fr);
        align-items: start;
      }

      .medic-hospital-list-photo {
        width: 52px;
        height: 52px;
      }

      .medic-hospital-desktop-info {
        display: none;
      }

      .medic-hospital-mobile-info {
        grid-column: 1 / -1;
        display: grid;
        gap: 5px;
        width: 100%;
        margin-top: 8px;
        padding-top: 10px;
        border-top: 1px solid #d0d7de;
        text-align: left;
      }

      .medic-hospital-mobile-info span {
        display: grid;
        grid-template-columns: 92px minmax(0, 1fr);
        align-items: start;
        gap: 0;
        width: 100%;
      }

      .medic-hospital-mobile-info strong {
        margin-right: 0;
        color: #24292f;
      }

      .medic-hospital-mobile-info em {
        white-space: normal;
        text-align: left;
        color: #57606a;
        font-style: normal;
      }

      .medic-hospital-mobile-desc {
        margin-top: 4px;
        color: #57606a;
        font-size: 13px;
        line-height: 1.45;
      }

      .medic-hospital-list-side {
        grid-column: 1 / -1;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        text-align: left;
        border-top: 1px solid #d0d7de;
        padding-top: 10px;
      }

      .medic-hospital-list-actions {
        justify-content: flex-end;
      }
    }

    @media (max-width: 480px) {
      .medic-hospital-list-item {
        grid-template-columns: 52px minmax(0, 1fr);
        gap: 10px;
      }

      .medic-hospital-mobile-info span {
        grid-template-columns: 88px minmax(0, 1fr);
      }

      .medic-hospital-list-side {
        align-items: stretch;
        flex-direction: column;
      }

      .medic-hospital-list-actions {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 8px;
      }

      .medic-list-btn {
        width: 100%;
      }
    }

    @media (max-width: 360px) {
      .medic-hospital-list-item {
        grid-template-columns: 1fr;
      }

      .medic-hospital-list-photo {
        width: 56px;
        height: 56px;
      }

      .medic-hospital-mobile-info span {
        grid-template-columns: 1fr;
        gap: 2px;
      }

      .medic-hospital-list-actions {
        grid-template-columns: 1fr;
      }
    }
  </style>

  <div class="medic-hospital-list-photo">
    <?php if ($hospital_image !== ''): ?>
      <img
        src="<?= user_hc_e($hospital_image) ?>"
        alt="<?= user_hc_e($hospital_name) ?>"
        loading="lazy"
        onerror="this.onerror=null;this.style.display='none';this.parentNode.querySelector('.medic-hospital-photo-fallback').style.display='flex';"
      >
    <?php endif; ?>

    <span class="medic-hospital-photo-fallback" style="<?= $hospital_image === '' ? 'display:flex;' : '' ?>">
      <?= user_hc_e(user_hc_initial($hospital_name)) ?>
    </span>
  </div>

  <div class="medic-hospital-list-main">
    <div class="medic-hospital-list-title">
      <h2>
        <?php if ($hospital_link !== '#'): ?>
          <a href="<?= user_hc_e($hospital_link) ?>">
            <?= user_hc_e($hospital_name) ?>
          </a>
        <?php else: ?>
          <?= user_hc_e($hospital_name) ?>
        <?php endif; ?>
      </h2>
    </div>

    <div class="medic-hospital-list-type">
      <span class="medic-hospital-list-tag">
        <?= user_hc_e($hospital_type) ?>
      </span>

      <?php if ($is_verified === 1): ?>
        <span class="medic-hospital-list-tag medic-hospital-list-tag-success">
          Verified
        </span>
      <?php else: ?>
        <span class="medic-hospital-list-tag medic-hospital-list-tag-success">
          Emergency 24/7
        </span>
      <?php endif; ?>
    </div>

    <div class="medic-hospital-list-info medic-hospital-desktop-info">
      <span>
        <strong>Location:</strong>
        <em><?= user_hc_e($hospital_desktop_location) ?></em>
      </span>

      <span>
        <strong>Departments:</strong>
        <em><?= user_hc_e((string)$hospital_departments) ?>+</em>
      </span>

      <span>
        <strong>Doctors:</strong>
        <em><?= user_hc_e((string)$hospital_doctors) ?>+</em>
      </span>

      <?php if ($hospital_phone !== ''): ?>
        <span>
          <strong>Phone:</strong>
          <em><?= user_hc_e($hospital_phone) ?></em>
        </span>
      <?php endif; ?>

      <?php if ($hospital_registration !== ''): ?>
        <span>
          <strong>Reg:</strong>
          <em><?= user_hc_e($hospital_registration) ?></em>
        </span>
      <?php endif; ?>
    </div>

    <?php if ($hospital_description !== ''): ?>
      <p class="medic-hospital-list-desc medic-hospital-desktop-info">
        <?= user_hc_e(user_hc_short_text($hospital_description, 90)) ?>
      </p>
    <?php endif; ?>
  </div>

  <div class="medic-hospital-list-info medic-hospital-mobile-info">
    <span>
      <strong>Location:</strong>
      <em><?= user_hc_e($hospital_mobile_location) ?></em>
    </span>

    <span>
      <strong>Departments:</strong>
      <em><?= user_hc_e((string)$hospital_departments) ?>+</em>
    </span>

    <span>
      <strong>Doctors:</strong>
      <em><?= user_hc_e((string)$hospital_doctors) ?>+</em>
    </span>

    <?php if ($hospital_phone !== ''): ?>
      <span>
        <strong>Phone:</strong>
        <em><?= user_hc_e($hospital_phone) ?></em>
      </span>
    <?php endif; ?>

    <?php if ($hospital_email !== ''): ?>
      <span>
        <strong>Email:</strong>
        <em><?= user_hc_e($hospital_email) ?></em>
      </span>
    <?php endif; ?>

    <?php if ($hospital_description !== ''): ?>
      <div class="medic-hospital-mobile-desc">
        <?= user_hc_e(user_hc_short_text($hospital_description, 110)) ?>
      </div>
    <?php endif; ?>
  </div>

  <div class="medic-hospital-list-side">
    <div>
      <div class="medic-hospital-list-status">
        <strong>Open</strong><br>
        24/7 service
      </div>

      <div class="medic-hospital-list-id">
        Hospital ID: #<?= user_hc_e((string)$hospital_id) ?>
      </div>
    </div>

    <div class="medic-hospital-list-actions">
      <?php if ($hospital_link !== '#'): ?>
        <a href="<?= user_hc_e($hospital_link) ?>" class="medic-list-btn">
          Details
        </a>
      <?php endif; ?>

      <a href="<?= user_hc_e($hospital_appointment_link !== '#' ? $hospital_appointment_link : '#') ?>" class="medic-list-btn medic-list-btn-primary">
        Appointment
      </a>
    </div>
  </div>

</article>
<?php endif; ?>