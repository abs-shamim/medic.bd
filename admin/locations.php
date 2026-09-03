<?php
require_once __DIR__ . '/includes/header.php';

$allowed_location_types = ['division', 'district', 'thana'];
$type = $_GET['type'] ?? 'division';
$type = in_array($type, $allowed_location_types, true) ? $type : 'division';

$tables = [
    'division' => 'divisions',
    'district' => 'districts',
    'thana' => 'thanas',
];

$labels = [
    'division' => 'Division',
    'district' => 'District',
    'thana' => 'Thana / Upazila',
];

if (isset($_GET['delete'])) {
    $delete_type = $_GET['type'] ?? 'division';
    $delete_type = in_array($delete_type, $allowed_location_types, true) ? $delete_type : 'division';

    $table = $tables[$delete_type];
    $id = (int)$_GET['delete'];

    if ($id > 0) {
        $stmt = $pdo->prepare("DELETE FROM {$table} WHERE id=:id");
        $stmt->execute([':id' => $id]);

        flash('success', $labels[$delete_type] . ' deleted successfully.');
    }

    redirect('locations.php?type=' . $delete_type);
}

if ($type === 'division') {
    $items = $pdo->query("SELECT * FROM divisions ORDER BY name_en ASC")->fetchAll();
} elseif ($type === 'district') {
    $items = $pdo->query("
        SELECT d.*, v.name_en AS division_name
        FROM districts d
        LEFT JOIN divisions v ON v.id = d.division_id
        ORDER BY d.name_en ASC
    ")->fetchAll();
} else {
    $items = $pdo->query("
        SELECT t.*, d.name_en AS district_name, v.name_en AS division_name
        FROM thanas t
        LEFT JOIN districts d ON d.id = t.district_id
        LEFT JOIN divisions v ON v.id = d.division_id
        ORDER BY t.name_en ASC
    ")->fetchAll();
}
?>

<div style="display:flex;align-items:center;justify-content:space-between;gap:15px;margin-bottom:18px;">
  <div>
    <h1 style="margin:0;color:#0f172a;">Locations</h1>
    <p style="margin:7px 0 0;color:#64748b;">Manage divisions, districts, and thanas/upazilas.</p>
  </div>

  <a href="location-form.php?type=<?= e($type) ?>" class="btn btn-primary">
    Add <?= e($labels[$type]) ?>
  </a>
</div>

<?php show_flash(); ?>

<div class="admin-actions" style="margin-bottom:18px;">
  <a href="locations.php?type=division" class="btn <?= $type === 'division' ? 'btn-primary' : 'btn-outline' ?>">Divisions</a>
  <a href="locations.php?type=district" class="btn <?= $type === 'district' ? 'btn-primary' : 'btn-outline' ?>">Districts</a>
  <a href="locations.php?type=thana" class="btn <?= $type === 'thana' ? 'btn-primary' : 'btn-outline' ?>">Thanas / Upazilas</a>
</div>

<table class="admin-table">
  <thead>
    <tr>
      <th>English Name</th>
      <th>Bangla Name</th>

      <?php if ($type === 'district'): ?>
        <th>Division</th>
      <?php endif; ?>

      <?php if ($type === 'thana'): ?>
        <th>District</th>
        <th>Division</th>
      <?php endif; ?>

      <th>Slug</th>
      <th>Status</th>
      <th style="width:180px;">Actions</th>
    </tr>
  </thead>

  <tbody>
    <?php if ($items): ?>
      <?php foreach ($items as $item): ?>
        <tr>
          <td><?= e($item['name_en'] ?? '') ?></td>
          <td><?= e($item['name_bn'] ?? '') ?></td>

          <?php if ($type === 'district'): ?>
            <td><?= e($item['division_name'] ?? '') ?></td>
          <?php endif; ?>

          <?php if ($type === 'thana'): ?>
            <td><?= e($item['district_name'] ?? '') ?></td>
            <td><?= e($item['division_name'] ?? '') ?></td>
          <?php endif; ?>

          <td><?= e($item['slug'] ?? '') ?></td>

          <td>
            <?php if (($item['status'] ?? '') === 'active'): ?>
              <span style="display:inline-block;padding:5px 10px;border-radius:999px;background:#dcfce7;color:#166534;font-size:12px;">Active</span>
            <?php else: ?>
              <span style="display:inline-block;padding:5px 10px;border-radius:999px;background:#fee2e2;color:#991b1b;font-size:12px;">Inactive</span>
            <?php endif; ?>
          </td>

          <td>
            <div class="admin-actions">
              <a class="btn btn-outline small-btn" href="location-form.php?type=<?= e($type) ?>&id=<?= e((string)$item['id']) ?>">Edit</a>

              <a
                class="btn btn-danger small-btn"
                href="locations.php?type=<?= e($type) ?>&delete=<?= e((string)$item['id']) ?>"
                onclick="return confirm('Delete this location?')"
              >Delete</a>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
    <?php else: ?>
      <tr>
        <td colspan="<?= $type === 'thana' ? '7' : ($type === 'district' ? '6' : '5') ?>" style="text-align:center;color:#64748b;padding:25px;">
          No <?= e(strtolower($labels[$type])) ?> found.
        </td>
      </tr>
    <?php endif; ?>
  </tbody>
</table>

<?php require_once __DIR__ . '/includes/footer.php'; ?>