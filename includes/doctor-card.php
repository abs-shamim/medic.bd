<?php if (!empty($doctor)): ?>
<?php
  /*
  |--------------------------------------------------------------------------
  | Doctor Card
  |--------------------------------------------------------------------------
  | Chamber:
  | - Chamber data comes only from chambers table.
  | - Chamber name comes from hospitals table using chambers.hospital_id.
  | - primary_hospital will never be treated as chamber.
  | - One chamber: Chamber: Name
  | - Multiple chambers: Chamber 1: Name, Chamber 2: Name
  |
  | Location:
  | - Always uses doctors.doctor_thana_id and doctors.doctor_district_id.
  | - If query already provides doctor_thana / doctor_district, it uses those.
  | - If not, this file fetches names from thanas and districts by ID.
  | - First chamber location fallback removed.
  |
  | Fee:
  | - Empty / 0 / 00 consultation fee will show nothing.
  |--------------------------------------------------------------------------
  */

  if (!function_exists('medic_dc_lang')) {
      function medic_dc_lang(): string
      {
          return defined('CURRENT_LANG') && CURRENT_LANG === 'bn' ? 'bn' : 'en';
      }
  }

  if (!function_exists('medic_dc_t')) {
      function medic_dc_t(string $key, string $fallback = ''): string
      {
          if (function_exists('__t')) {
              return __t($key, $fallback !== '' ? $fallback : $key);
          }

          return $fallback !== '' ? $fallback : $key;
      }
  }


  /*
  |--------------------------------------------------------------------------
  | Bengali Digit Display
  |--------------------------------------------------------------------------
  | Converts only visitor-facing text in Bengali mode.
  | URLs, IDs, image paths and other functional values keep ASCII digits.
  |--------------------------------------------------------------------------
  */
  if (!function_exists('medic_dc_display_text')) {
      function medic_dc_display_text($value): string
      {
          $value = (string)($value ?? '');

          if (medic_dc_lang() !== 'bn' || $value === '') {
              return $value;
          }

          return strtr($value, [
              '0' => '০',
              '1' => '১',
              '2' => '২',
              '3' => '৩',
              '4' => '৪',
              '5' => '৫',
              '6' => '৬',
              '7' => '৭',
              '8' => '৮',
              '9' => '৯',
          ]);
      }
  }

  if (!function_exists('medic_dc_lang_value')) {
      function medic_dc_lang_value(array $row, string $base_key, string $fallback_key = ''): string
      {
          $lang = medic_dc_lang();

          if ($lang === 'bn') {
              $bn_key = $base_key . '_bn';

              if (isset($row[$bn_key]) && trim((string)$row[$bn_key]) !== '') {
                  return trim((string)$row[$bn_key]);
              }
          }

          if (isset($row[$base_key]) && trim((string)$row[$base_key]) !== '') {
              return trim((string)$row[$base_key]);
          }

          if ($fallback_key !== '') {
              return medic_dc_lang_value($row, $fallback_key);
          }

          return '';
      }
  }

  if (!function_exists('medic_dc_first_lang_value')) {
      function medic_dc_first_lang_value(array $row, array $base_keys): string
      {
          foreach ($base_keys as $base_key) {
              $value = medic_dc_lang_value($row, $base_key);

              if ($value !== '') {
                  return $value;
              }
          }

          return '';
      }
  }

  if (!function_exists('medic_dc_url_slug')) {
      function medic_dc_url_slug(string $text): string
      {
          $text = trim($text);

          if ($text === '') {
              return '';
          }

          $text = preg_replace('/[^\p{L}\p{N}\s-]+/u', '', $text);
          $text = preg_replace('/[\s_]+/u', '-', (string)$text);
          $text = preg_replace('/-+/u', '-', (string)$text);

          if (function_exists('mb_strtolower')) {
              $text = mb_strtolower($text, 'UTF-8');
          } else {
              $text = strtolower($text);
          }

          return trim((string)$text, '-');
      }
  }

  if (!function_exists('medic_dc_front_url')) {
      function medic_dc_front_url(string $path = '', ?string $lang = null): string
      {
          $path = trim($path, '/');
          $lang = $lang ?: medic_dc_lang();

          if ($lang === 'bn') {
              return function_exists('site_url')
                  ? site_url($path !== '' ? 'bn/' . $path : 'bn')
                  : '/bn/' . $path;
          }

          return function_exists('site_url')
              ? site_url($path)
              : '/' . $path;
      }
  }

  if (!function_exists('medic_dc_doctor_profile_url')) {
      function medic_dc_doctor_profile_url(array $doctor): string
      {
          $slug = '';

          if (!empty($doctor['slug'])) {
              $slug = medic_dc_url_slug((string)$doctor['slug']);
          }

          if ($slug === '' && !empty($doctor['name'])) {
              $slug = medic_dc_url_slug((string)$doctor['name']);
          }

          if ($slug === '' && !empty($doctor['id'])) {
              $slug = (string)(int)$doctor['id'];
          }

          return medic_dc_front_url('doctor/' . $slug, medic_dc_lang());
      }
  }

  if (!function_exists('medic_dc_valid_fee')) {
      function medic_dc_valid_fee($value): float
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

  if (!function_exists('medic_dc_first_available_fee')) {
      function medic_dc_first_available_fee(array $doctor): float
      {
          $doctor_fee = medic_dc_valid_fee($doctor['consultation_fee'] ?? '');

          if ($doctor_fee > 0) {
              return $doctor_fee;
          }

          $first_chamber_fee = medic_dc_valid_fee($doctor['first_chamber_fee'] ?? '');

          if ($first_chamber_fee > 0) {
              return $first_chamber_fee;
          }

          $first_chamber_consultation_fee = medic_dc_valid_fee($doctor['first_chamber_consultation_fee'] ?? '');

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
                  $fee = medic_dc_valid_fee($fee_item);

                  if ($fee > 0) {
                      return $fee;
                  }
              }
          }

          return 0;
      }
  }

  if (!function_exists('medic_dc_asset_url')) {
      function medic_dc_asset_url(string $path, string $fallback = ''): string
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

          $path = preg_replace('#^\.\./+#', '', $path);
          $path = ltrim($path, '/');

          if (function_exists('site_url')) {
              return site_url($path);
          }

          $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
          $host = $_SERVER['HTTP_HOST'] ?? '';

          return $host !== '' ? $scheme . '://' . $host . '/' . $path : '/' . $path;
      }
  }

  if (!function_exists('medic_dc_years_experience_text')) {
      function medic_dc_years_experience_text($starting_year): string
      {
          $starting_year = (int)trim((string)($starting_year ?? ''));
          $current_year = (int)date('Y');

          if ($starting_year < 1900 || $starting_year > $current_year) {
              return medic_dc_t('not_specified', 'Not specified');
          }

          $years = $current_year - $starting_year;

          if ($years < 0) {
              $years = 0;
          }

          return (string)$years . '+ ' . medic_dc_t('years', 'Years');
      }
  }

  if (!function_exists('medic_dc_site_setting')) {
      function medic_dc_site_setting(string $key, string $default = ''): string
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
              $stmt = $pdo->prepare("SELECT setting_value FROM site_settings WHERE setting_key = :setting_key LIMIT 1");
              $stmt->execute([':setting_key' => $key]);
              $value = trim((string)$stmt->fetchColumn());

              return $value !== '' ? $value : $default;
          } catch (Throwable $e) {
              return $default;
          }
      }
  }

  if (!function_exists('medic_dc_default_doctor_image_setting')) {
      function medic_dc_default_doctor_image_setting(array $doctor): string
      {
          $gender = strtolower(trim((string)($doctor['gender'] ?? '')));

          if (in_array($gender, ['male', 'm'], true)) {
              $value = medic_dc_site_setting('default_doctor_male_image', '');

              if ($value !== '') {
                  return $value;
              }
          }

          if (in_array($gender, ['female', 'f'], true)) {
              $value = medic_dc_site_setting('default_doctor_female_image', '');

              if ($value !== '') {
                  return $value;
              }
          }

          return medic_dc_site_setting('default_doctor_image', 'assets/images/default-doctor.webp');
      }
  }

  if (!function_exists('medic_dc_location_name_by_id')) {
      function medic_dc_location_name_by_id(string $table, int $id): array
      {
          global $pdo;

          if (!isset($pdo) || !$pdo instanceof PDO || $id <= 0) {
              return [
                  'name_en' => '',
                  'name_bn' => '',
              ];
          }

          if (!in_array($table, ['districts', 'thanas'], true)) {
              return [
                  'name_en' => '',
                  'name_bn' => '',
              ];
          }

          static $cache = [];

          $cache_key = $table . ':' . $id;

          if (isset($cache[$cache_key])) {
              return $cache[$cache_key];
          }

          try {
              $stmt = $pdo->prepare("SELECT name_en, name_bn FROM {$table} WHERE id = :id LIMIT 1");
              $stmt->execute([':id' => $id]);
              $row = $stmt->fetch(PDO::FETCH_ASSOC);

              if (!$row) {
                  $cache[$cache_key] = [
                      'name_en' => '',
                      'name_bn' => '',
                  ];

                  return $cache[$cache_key];
              }

              $cache[$cache_key] = [
                  'name_en' => trim((string)($row['name_en'] ?? '')),
                  'name_bn' => trim((string)($row['name_bn'] ?? '')),
              ];

              return $cache[$cache_key];
          } catch (Throwable $e) {
              $cache[$cache_key] = [
                  'name_en' => '',
                  'name_bn' => '',
              ];

              return $cache[$cache_key];
          }
      }
  }

  if (!function_exists('medic_dc_location_lang_name')) {
      function medic_dc_location_lang_name(array $names): string
      {
          if (medic_dc_lang() === 'bn' && trim((string)($names['name_bn'] ?? '')) !== '') {
              return trim((string)$names['name_bn']);
          }

          return trim((string)($names['name_en'] ?? ''));
      }
  }

  if (!function_exists('medic_dc_unique_clean_list')) {
      function medic_dc_unique_clean_list(array $items): array
      {
          $clean = [];

          foreach ($items as $item) {
              $item = trim((string)$item);

              if ($item === '') {
                  continue;
              }

              $key = function_exists('mb_strtolower')
                  ? mb_strtolower($item, 'UTF-8')
                  : strtolower($item);

              $clean[$key] = $item;
          }

          return array_values($clean);
      }
  }

  if (!function_exists('medic_dc_chamber_rows_to_names')) {
      function medic_dc_chamber_rows_to_names(array $rows): array
      {
          $items = [];

          foreach ($rows as $row) {
              $name = '';

              if (medic_dc_lang() === 'bn' && trim((string)($row['hospital_name_bn'] ?? '')) !== '') {
                  $name = trim((string)$row['hospital_name_bn']);
              } else {
                  $name = trim((string)($row['hospital_name'] ?? ''));
              }

              if ($name !== '') {
                  $items[] = $name;
              }
          }

          return medic_dc_unique_clean_list($items);
      }
  }

  if (!function_exists('medic_dc_chamber_list')) {
      function medic_dc_chamber_list(array $doctor): array
      {
          global $pdo, $medic_dc_preloaded_chambers;

          $doctor_id = (int)($doctor['id'] ?? 0);

          if ($doctor_id > 0 && isset($medic_dc_preloaded_chambers) && array_key_exists($doctor_id, $medic_dc_preloaded_chambers)) {
              return medic_dc_chamber_rows_to_names($medic_dc_preloaded_chambers[$doctor_id]);
          }

          if (!isset($pdo) || !$pdo instanceof PDO || $doctor_id <= 0) {
              return [];
          }

          try {
              $hospital_name_bn_select = (function_exists('column_exists') && column_exists('hospitals', 'name_bn'))
                  ? "h.name_bn AS hospital_name_bn"
                  : "'' AS hospital_name_bn";

              $stmt = $pdo->prepare("
                  SELECT
                      c.id AS chamber_id,
                      c.sort_order,
                      c.status,
                      h.name AS hospital_name,
                      {$hospital_name_bn_select}
                  FROM chambers c
                  LEFT JOIN hospitals h ON h.id = c.hospital_id
                  WHERE c.doctor_id = :doctor_id
                    AND c.status = 'active'
                  ORDER BY c.sort_order ASC, c.id ASC
              ");

              $stmt->execute([
                  ':doctor_id' => $doctor_id,
              ]);

              return medic_dc_chamber_rows_to_names($stmt->fetchAll(PDO::FETCH_ASSOC));
          } catch (Throwable $e) {
              return [];
          }
      }
  }

  $not_specified_text = medic_dc_t('not_specified', 'Not specified');
  $doctor_text = medic_dc_t('doctor', 'Doctor');

  $doctor_name = medic_dc_lang_value($doctor, 'name');
  $doctor_designation = medic_dc_lang_value($doctor, 'designation');
  $doctor_degree = medic_dc_lang_value($doctor, 'degree');
  $doctor_specialty_name = medic_dc_lang_value($doctor, 'specialty_name');

  if ($doctor_name === '') {
      $doctor_name = $doctor_text;
  }

  /*
  |--------------------------------------------------------------------------
  | Doctor Chambers
  |--------------------------------------------------------------------------
  */

  $doctor_chambers = medic_dc_chamber_list($doctor);

  /*
  |--------------------------------------------------------------------------
  | Doctor Location
  |--------------------------------------------------------------------------
  */

  $doctor_thana = medic_dc_first_lang_value($doctor, [
      'doctor_thana',
      'thana_name',
      'thana',
  ]);

  $doctor_district = medic_dc_first_lang_value($doctor, [
      'doctor_district',
      'district_name',
      'district',
  ]);

  if ($doctor_thana === '') {
      $doctor_thana_id = (int)($doctor['doctor_thana_id'] ?? 0);
      $doctor_thana = medic_dc_location_lang_name(
          medic_dc_location_name_by_id('thanas', $doctor_thana_id)
      );
  }

  if ($doctor_district === '') {
      $doctor_district_id = (int)($doctor['doctor_district_id'] ?? 0);
      $doctor_district = medic_dc_location_lang_name(
          medic_dc_location_name_by_id('districts', $doctor_district_id)
      );
  }

  $doctor_location_parts = [];

  if ($doctor_thana !== '') {
      $doctor_location_parts[] = $doctor_thana;
  }

  if ($doctor_district !== '' && $doctor_district !== $doctor_thana) {
      $doctor_location_parts[] = $doctor_district;
  }

  $doctor_location = !empty($doctor_location_parts)
      ? implode(', ', $doctor_location_parts)
      : $not_specified_text;

  $card_consultation_fee = medic_dc_first_available_fee($doctor);

  $card_consultation_fee_text = $card_consultation_fee > 0
      ? '৳' . number_format($card_consultation_fee, 0)
      : '';

  $profile_link = medic_dc_doctor_profile_url($doctor);

  $doctor_default_image_setting = medic_dc_default_doctor_image_setting($doctor);

  $doctor_image_url = medic_dc_asset_url(
      (string)($doctor['image'] ?? ''),
      $doctor_default_image_setting
  );

  $doctor_default_png_url = medic_dc_asset_url('assets/images/default-doctor.png');

  $years_experience_text = medic_dc_years_experience_text($doctor['experience_years'] ?? '');

  $doctor_subtitle = $doctor_designation !== ''
      ? $doctor_designation
      : $doctor_specialty_name;

  $has_meta_items = $doctor_degree !== ''
      || !empty($doctor_chambers)
      || $doctor_location !== $not_specified_text
      || $years_experience_text !== $not_specified_text;
?>

<article class="medic-doctor-list-item">

  <div class="medic-doctor-list-head">
    <div class="medic-doctor-list-photo">
      <img
        src="<?= e($doctor_image_url) ?>"
        alt="<?= e(medic_dc_display_text($doctor_name)) ?>"
        loading="lazy"
        data-fallback="webp"
        data-fallback-png="<?= e($doctor_default_png_url) ?>"
      >
    </div>

    <div class="medic-doctor-list-main">
      <div class="medic-doctor-list-title">
        <h3>
          <a href="<?= e($profile_link) ?>">
            <?= e(medic_dc_display_text($doctor_name)) ?>
          </a>
        </h3>

        <?= verified_badge((int)($doctor['is_verified'] ?? 0)) ?>
      </div>

      <?php if ($doctor_subtitle !== ''): ?>
        <span class="medic-doctor-list-specialty">
          <?= e(medic_dc_display_text($doctor_subtitle)) ?>
        </span>
      <?php endif; ?>

      <div class="medic-doctor-list-rating">
        <svg viewBox="0 0 16 16" aria-hidden="true"><path d="m8 1.6 1.9 3.9 4.3.6-3.1 3 .7 4.3L8 11.4l-3.8 2 .7-4.3-3.1-3 4.3-.6L8 1.6Z"></path></svg>
        <?= e(medic_dc_display_text((string)($doctor['rating'] ?? '0'))) ?>
        <?= e(medic_dc_display_text(medic_dc_t('rating', 'rating'))) ?>
        ·
        <?= e(medic_dc_display_text((string)($doctor['reviews_count'] ?? '0'))) ?>
        <?= e(medic_dc_display_text(medic_dc_t('reviews', 'reviews'))) ?>
      </div>
    </div>

    <?php if ($card_consultation_fee_text !== ''): ?>
      <div class="medic-doctor-list-fee">
        <?= e(medic_dc_display_text($card_consultation_fee_text)) ?>
      </div>
    <?php endif; ?>
  </div>

  <?php if ($has_meta_items): ?>
  <ul class="medic-doctor-list-meta">
    <?php if ($doctor_degree !== ''): ?>
      <li>
        <svg viewBox="0 0 16 16" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.4"><path d="M1 5.5 8 2l7 3.5-7 3.5-7-3.5Z"></path><path d="M4 7.2v3c0 1 1.8 1.8 4 1.8s4-.8 4-1.8v-3"></path><path d="M14 5.5v4.2"></path></svg>
        <span><?= e(medic_dc_display_text($doctor_degree)) ?></span>
      </li>
    <?php endif; ?>

    <?php if (!empty($doctor_chambers)): ?>
      <?php if (count($doctor_chambers) === 1): ?>
        <li>
          <svg viewBox="0 0 16 16" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.4"><path d="M2 14V3.5L8 1l6 2.5V14"></path><path d="M2 14h12"></path><path d="M6.5 14v-3h3v3"></path><path d="M6 5h1M9 5h1M6 8h1M9 8h1"></path></svg>
          <span><?= e(medic_dc_display_text($doctor_chambers[0])) ?></span>
        </li>
      <?php else: ?>
        <?php foreach ($doctor_chambers as $chamber_index => $chamber_name): ?>
          <li>
            <svg viewBox="0 0 16 16" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.4"><path d="M2 14V3.5L8 1l6 2.5V14"></path><path d="M2 14h12"></path><path d="M6.5 14v-3h3v3"></path><path d="M6 5h1M9 5h1M6 8h1M9 8h1"></path></svg>
            <span><strong><?= e(medic_dc_display_text(medic_dc_t('chamber', 'Chamber'))) ?> <?= e(medic_dc_display_text((string)($chamber_index + 1))) ?>:</strong> <?= e(medic_dc_display_text($chamber_name)) ?></span>
          </li>
        <?php endforeach; ?>
      <?php endif; ?>
    <?php endif; ?>

    <?php if ($doctor_location !== $not_specified_text): ?>
      <li>
        <svg viewBox="0 0 16 16" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.4"><path d="M8 14.5s5-4.2 5-8.2A5 5 0 0 0 3 6.3c0 4 5 8.2 5 8.2Z"></path><circle cx="8" cy="6.2" r="1.8"></circle></svg>
        <span><?= e(medic_dc_display_text($doctor_location)) ?></span>
      </li>
    <?php endif; ?>

    <?php if ($years_experience_text !== $not_specified_text): ?>
      <li>
        <svg viewBox="0 0 16 16" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.4"><circle cx="8" cy="8" r="6.3"></circle><path d="M8 4.8v3.4l2.4 1.4"></path></svg>
        <span><?= e(medic_dc_display_text($years_experience_text)) ?></span>
      </li>
    <?php endif; ?>
  </ul>
  <?php endif; ?>

  <div class="medic-doctor-list-actions">
    <a href="<?= e($profile_link) ?>" class="medic-list-btn">
      <?= e(medic_dc_display_text(medic_dc_t('profile', 'Profile'))) ?>
    </a>

    <a href="<?= e($profile_link) ?>" class="medic-list-btn medic-list-btn-primary">
      <?= e(medic_dc_display_text(medic_dc_t('appointment', 'Appointment'))) ?>
    </a>
  </div>

</article>
<?php endif; ?>