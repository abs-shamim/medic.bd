<?php if (!empty($hospital)): ?>
<?php
  /*
  |--------------------------------------------------------------------------
  | Hospital Card Display Conditions + EN/BN Support
  |--------------------------------------------------------------------------
  | Desktop:
  | - Location: full address
  |
  | Mobile:
  | - Location: only Thana, District
  | - Area / Locality will NOT be used as Thana
  | - If thana/district name missing, parse from address
  |
  | Language:
  | - /hospitals/... uses English values
  | - /bn/hospitals/... uses Bangla values when available
  | - Static labels come from languages/en.php and languages/bn.php
  | - Bangla database fields fallback to English fields when empty
  |--------------------------------------------------------------------------
  */

  if (!function_exists('medic_hc_current_lang')) {
      function medic_hc_current_lang(): string
      {
          global $lang;

          if (isset($lang) && $lang === 'bn') {
              return 'bn';
          }

          if (defined('CURRENT_LANG') && CURRENT_LANG === 'bn') {
              return 'bn';
          }

          if (isset($_GET['lang']) && $_GET['lang'] === 'bn') {
              return 'bn';
          }

          $path = trim((string)parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/');

          if ($path === 'bn' || str_starts_with($path, 'bn/')) {
              return 'bn';
          }

          return 'en';
      }
  }

  if (!function_exists('medic_hc_t')) {
      function medic_hc_t(string $key, string $default = '', array $replace = []): string
      {
          $text = '';

          if (function_exists('__t')) {
              try {
                  $translated = __t($key);

                  if (is_string($translated) && trim($translated) !== '' && $translated !== $key) {
                      $text = $translated;
                  }
              } catch (Throwable $e) {
                  $text = '';
              }
          }

          if ($text === '') {
              $text = $default !== '' ? $default : $key;
          }

          foreach ($replace as $name => $value) {
              $text = str_replace(':' . $name, (string)$value, $text);
          }

          return $text;
      }
  }

  /*
  |--------------------------------------------------------------------------
  | Bangla Digit Display Helper
  |--------------------------------------------------------------------------
  | Converts text shown to users on Bangla pages only. URLs and link targets
  | always keep English digits, so routing and click-to-call remain safe.
  */
  if (!function_exists('medic_hc_localize_digits')) {
      function medic_hc_localize_digits($value, ?string $lang = null): string
      {
          $lang = $lang ?: medic_hc_current_lang();
          $value = (string)$value;

          if ($lang !== 'bn') {
              return $value;
          }

          return strtr($value, [
              '0' => '০', '1' => '১', '2' => '২', '3' => '৩', '4' => '৪',
              '5' => '৫', '6' => '৬', '7' => '৭', '8' => '৮', '9' => '৯',
          ]);
      }
  }

  if (!function_exists('medic_hc_value')) {
      function medic_hc_value(array $row, array $keys): string
      {
          foreach ($keys as $key) {
              if (isset($row[$key]) && trim((string)$row[$key]) !== '') {
                  return trim((string)$row[$key]);
              }
          }

          return '';
      }
  }

  if (!function_exists('medic_hc_lang_value')) {
      function medic_hc_lang_value(array $row, array $base_keys, string $lang = 'en'): string
      {
          $keys = [];

          if ($lang === 'bn') {
              foreach ($base_keys as $key) {
                  $keys[] = $key . '_bn';
              }
          }

          foreach ($base_keys as $key) {
              $keys[] = $key;
          }

          if ($lang !== 'bn') {
              foreach ($base_keys as $key) {
                  $keys[] = $key . '_en';
              }
          }

          return medic_hc_value($row, array_values(array_unique($keys)));
      }
  }

  if (!function_exists('medic_hc_short_location_from_address')) {
      function medic_hc_short_location_from_address(string $address): array
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

              /*
               * Remove postcode text from thana part.
               * Example: "Khan Jahan Ali-9000" => "Khan Jahan Ali"
               */
              $part = preg_replace('/\b(post\s*code|postcode|postal\s*code)\b.*$/iu', '', $part);
              $part = preg_replace('/-\s*\d{3,6}$/u', '', $part);
              $part = trim((string)$part, " \t\n\r\0\x0B-:");

              if ($part === '') {
                  continue;
              }

              /*
               * Remove detailed address parts.
               * Important: Area / Locality is not used as thana directly.
               * This parser only uses the last clean parts from full address.
               */
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

  if (!function_exists('medic_hc_site_setting')) {
      function medic_hc_site_setting(string $key, string $default = ''): string
      {
          global $pdo;

          if ($key === '') {
              return $default;
          }

          if (!isset($pdo)) {
              return $default;
          }

          try {
              $stmt = $pdo->prepare("SELECT setting_value FROM site_settings WHERE setting_key = :setting_key LIMIT 1");
              $stmt->execute([':setting_key' => $key]);
              $value = $stmt->fetchColumn();

              if ($value === false || trim((string)$value) === '') {
                  return $default;
              }

              return trim((string)$value);
          } catch (Throwable $e) {
              return $default;
          }
      }
  }

  if (!function_exists('medic_hc_asset_url')) {
      function medic_hc_asset_url(string $path, string $fallback = ''): string
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

          $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
          $host = $_SERVER['HTTP_HOST'] ?? '';

          return $host !== '' ? $scheme . '://' . $host . '/' . $path : '/' . $path;
      }
  }

  if (!function_exists('medic_hc_hospital_url')) {
      function medic_hc_hospital_url(array $hospital, string $lang = 'en'): string
      {
          $slug = trim((string)($hospital['slug'] ?? ''));

          if ($slug !== '' && function_exists('site_url')) {
              return $lang === 'bn'
                  ? site_url('bn/hospital/' . $slug)
                  : site_url('hospital/' . $slug);
          }

          if (function_exists('hospital_url')) {
              return hospital_url($hospital);
          }

          return '#';
      }
  }

  $hospital_card_lang = medic_hc_current_lang();

  $hospital_card_display = static function ($value) use ($hospital_card_lang): string {
      return medic_hc_localize_digits((string)$value, $hospital_card_lang);
  };

  $hospital_name = medic_hc_lang_value($hospital, ['name', 'hospital_name'], $hospital_card_lang);

  if ($hospital_name === '') {
      $hospital_name = medic_hc_t('hospital_card_default_name', 'Hospital');
  }

  $hospital_type = medic_hc_lang_value($hospital, ['type', 'hospital_type'], $hospital_card_lang);

  if ($hospital_type === '') {
      $hospital_type = medic_hc_t('hospital_card_default_type', 'Hospital');
  }

  $hospital_default_image_setting = medic_hc_site_setting('default_hospital_image', 'assets/images/default-hospital.webp');

  $hospital_image = medic_hc_asset_url(
      medic_hc_value($hospital, ['image', 'hospital_image']),
      $hospital_default_image_setting
  );

  if ($hospital_image === '') {
      $hospital_image = medic_hc_asset_url('', 'assets/images/default-hospital.webp');
  }

  /*
  |--------------------------------------------------------------------------
  | Desktop Location
  |--------------------------------------------------------------------------
  | Full address for desktop.
  |--------------------------------------------------------------------------
  */
  $hospital_desktop_location = medic_hc_lang_value($hospital, [
      'address',
      'hospital_address',
      'city',
      'district_name',
      'district',
  ], $hospital_card_lang);

  if ($hospital_desktop_location === '') {
      $hospital_desktop_location = medic_hc_t('hospital_card_not_specified', 'Not specified');
  }

  /*
  |--------------------------------------------------------------------------
  | Mobile Location
  |--------------------------------------------------------------------------
  | Only Thana, District.
  | Do NOT use area / area_locality as thana.
  |--------------------------------------------------------------------------
  */
  $hospital_mobile_thana = medic_hc_lang_value($hospital, [
      'thana_name',
      'thana',
      'hospital_thana',
  ], $hospital_card_lang);

  $hospital_mobile_district = medic_hc_lang_value($hospital, [
      'district_name',
      'district',
      'hospital_district',
      'city',
  ], $hospital_card_lang);

  if (($hospital_mobile_thana === '' || $hospital_mobile_district === '') && $hospital_desktop_location !== '') {
      $parsed_location = medic_hc_short_location_from_address($hospital_desktop_location);

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
      : medic_hc_t('hospital_card_not_specified', 'Not specified');

  $hospital_departments = (int)($hospital['departments_count'] ?? 0);
  $hospital_doctors = (int)($hospital['doctors_count'] ?? 0);
  $hospital_description = medic_hc_lang_value($hospital, ['description', 'hospital_description'], $hospital_card_lang);

  $hospital_link = medic_hc_hospital_url($hospital, $hospital_card_lang);

  /*
   * Appointment button currently links to hospital profile.
   */
  $hospital_appointment_link = $hospital_link;
?>

<article class="medic-hospital-list-item">

  <div class="medic-hospital-list-photo">
    <img
      src="<?= e($hospital_image) ?>"
      alt="<?= e($hospital_card_display($hospital_name)) ?>"
      loading="lazy"
    >
  </div>

  <div class="medic-hospital-list-main">
    <div class="medic-hospital-list-title">
      <h2>
        <a href="<?= e($hospital_link) ?>">
          <?= e($hospital_card_display($hospital_name)) ?>
        </a>
      </h2>
    </div>

    <div class="medic-hospital-list-type">
      <span class="medic-hospital-list-tag">
        <?= e($hospital_card_display($hospital_type)) ?>
      </span>

      <span class="medic-hospital-list-tag medic-hospital-list-tag-success">
        <?= e($hospital_card_display(medic_hc_t('hospital_card_emergency_24_7', 'Emergency 24/7'))) ?>
      </span>
    </div>

    <div class="medic-hospital-list-info medic-hospital-desktop-info">
      <span>
        <strong><?= e($hospital_card_display(medic_hc_t('hospital_card_location', 'Location'))) ?>:</strong>
        <em><?= e($hospital_card_display($hospital_desktop_location)) ?></em>
      </span>

      <span>
        <strong><?= e($hospital_card_display(medic_hc_t('hospital_card_departments', 'Departments'))) ?>:</strong>
        <em><?= e($hospital_card_display((string)$hospital_departments)) ?>+</em>
      </span>

      <span>
        <strong><?= e($hospital_card_display(medic_hc_t('hospital_card_doctors', 'Doctors'))) ?>:</strong>
        <em><?= e($hospital_card_display((string)$hospital_doctors)) ?>+</em>
      </span>
    </div>

    <?php if ($hospital_description !== ''): ?>
      <p class="medic-hospital-list-desc medic-hospital-desktop-info">
        <?= e($hospital_card_display(mb_strimwidth($hospital_description, 0, 90, '...'))) ?>
      </p>
    <?php endif; ?>
  </div>

  <div class="medic-hospital-list-info medic-hospital-mobile-info">
    <span>
      <strong><?= e($hospital_card_display(medic_hc_t('hospital_card_location', 'Location'))) ?>:</strong>
      <em><?= e($hospital_card_display($hospital_mobile_location)) ?></em>
    </span>

    <span>
      <strong><?= e($hospital_card_display(medic_hc_t('hospital_card_departments', 'Departments'))) ?>:</strong>
      <em><?= e($hospital_card_display((string)$hospital_departments)) ?>+</em>
    </span>

    <span>
      <strong><?= e($hospital_card_display(medic_hc_t('hospital_card_doctors', 'Doctors'))) ?>:</strong>
      <em><?= e($hospital_card_display((string)$hospital_doctors)) ?>+</em>
    </span>

    <?php if ($hospital_description !== ''): ?>
      <div class="medic-hospital-mobile-desc">
        <?= e($hospital_card_display(mb_strimwidth($hospital_description, 0, 110, '...'))) ?>
      </div>
    <?php endif; ?>
  </div>

  <div class="medic-hospital-list-side">
    <div>
      <div class="medic-hospital-list-status">
        <strong><?= e($hospital_card_display(medic_hc_t('hospital_card_open', 'Open'))) ?></strong><br>
        <?= e($hospital_card_display(medic_hc_t('hospital_card_service_24_7', '24/7 service'))) ?>
      </div>
    </div>

    <div class="medic-hospital-list-actions">
      <a href="<?= e($hospital_link) ?>" class="medic-list-btn">
        <?= e($hospital_card_display(medic_hc_t('hospital_card_details', 'Details'))) ?>
      </a>

      <a href="<?= e($hospital_appointment_link) ?>" class="medic-list-btn medic-list-btn-primary">
        <?= e($hospital_card_display(medic_hc_t('hospital_card_appointment', 'Appointment'))) ?>
      </a>
    </div>
  </div>

</article>
<?php endif; ?>
