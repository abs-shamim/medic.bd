<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/functions.php';

$library_type = 'medicine_form';
$library_type = prescription_normalize_library_type($library_type);
$definition = prescription_library_definition($library_type);
$managed_pages = prescription_managed_library_pages();

if (!$definition || !isset($managed_pages[$library_type])) {
    http_response_code(404);
    exit('Library page was not found.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!prescription_verify_csrf($_POST['csrf_token'] ?? null)) {
        prescription_flash('error', 'Security verification failed. Please refresh and try again.');
        prescription_redirect(prescription_library_list_path($library_type));
    }

    $form_action = prescription_clean_text($_POST['form_action'] ?? '', 40);

    try {
        if ($form_action === 'delete_item') {
            $result = prescription_delete_library_item(
                $pdo,
                (int)($_POST['item_id'] ?? 0),
                $doctor_id,
                $library_type
            );

            prescription_flash(
                'success',
                $result === 'hidden'
                    ? 'The shared demo item is now hidden only from your account.'
                    : $definition['singular'] . ' deleted successfully.'
            );
        } elseif ($form_action === 'restore_demos') {
            prescription_restore_library_demos($pdo, $doctor_id, $library_type);
            prescription_flash('success', 'All shared demo items for this library are visible again.');
        }
    } catch (Throwable $e) {
        error_log('[Library List][medicine_form] ' . $e->getMessage());
        prescription_flash('error', 'The requested library action could not be completed.');
    }

    prescription_redirect(prescription_library_list_path($library_type));
}

$q = prescription_clean_text($_GET['q'] ?? '', 100);
$status = prescription_clean_text($_GET['status'] ?? '', 20);
$scope = prescription_clean_text($_GET['scope'] ?? '', 20);
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

$result = prescription_get_library_management_items(
    $pdo,
    $doctor_id,
    $library_type,
    $q,
    $status,
    $scope,
    $per_page,
    $offset
);

$items = $result['items'] ?? [];
$total = (int)($result['total'] ?? 0);
$total_pages = max(1, (int)ceil($total / $per_page));

$page_title = $definition['title'] . ' Library';
require_once dirname(__DIR__) . '/includes/header.php';

$library_shell_title = $definition['title'] . ' Library';
$library_shell_description = $definition['description']
    . ' Shared demos are available to approved doctors, while personal values remain private.';
$library_shell_primary_label = '+ Add ' . $definition['singular'];
$library_shell_primary_url = prescription_library_form_url($library_type);
$library_shell_secondary_label = 'Library Dashboard';
$library_shell_secondary_url = prescription_library_dashboard_url();

require __DIR__ . '/librarys.php';
?>

<div class="rx-lib-notice">
    <span class="rx-lib-notice-icon">i</span>
    <div>
        <strong>How shared demos work</strong>
        <span>
            Removing a demo hides it only for your account. Personal values can be edited,
            disabled or permanently deleted.
        </span>
    </div>
</div>

<section class="rx-lib-panel">
    <div class="rx-lib-panel-head">
        <div>
            <h3><?= prescription_e($definition['title']) ?></h3>
            <p><?= prescription_e((string)$total) ?> visible item<?= $total === 1 ? '' : 's' ?>.</p>
        </div>

        <form method="POST">
            <input
                type="hidden"
                name="csrf_token"
                value="<?= prescription_e(prescription_csrf_token()) ?>"
            >
            <input type="hidden" name="form_action" value="restore_demos">
            <button type="submit" class="rx-lib-btn is-small">Restore Demo Items</button>
        </form>
    </div>

    <div class="rx-lib-panel-body">
        <form method="GET" class="rx-lib-filter">
            <div class="rx-lib-field">
                <label for="rxLibrarySearch">Search</label>
                <input
                    id="rxLibrarySearch"
                    type="search"
                    name="q"
                    value="<?= prescription_e($q) ?>"
                    placeholder="Search <?= prescription_e(strtolower($definition['singular'])) ?>"
                >
            </div>

            <div class="rx-lib-field">
                <label for="rxLibraryScope">Source</label>
                <select id="rxLibraryScope" name="scope">
                    <option value="">Demo + My Items</option>
                    <option value="demo" <?= $scope === 'demo' ? 'selected' : '' ?>>Shared Demo</option>
                    <option value="mine" <?= $scope === 'mine' ? 'selected' : '' ?>>My Library</option>
                </select>
            </div>

            <div class="rx-lib-field">
                <label for="rxLibraryStatus">Status</label>
                <select id="rxLibraryStatus" name="status">
                    <option value="">All Statuses</option>
                    <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                </select>
            </div>

            <div class="rx-lib-actions">
                <button type="submit" class="rx-lib-btn is-primary">Filter</button>
                <a
                    class="rx-lib-btn"
                    href="<?= prescription_e(prescription_library_list_url($library_type)) ?>"
                >Reset</a>
            </div>
        </form>

        <?php if (!$items): ?>
            <div class="rx-lib-empty">
                <strong>No item found</strong>
                <span>Add a personal value, restore demos, or change the filters.</span>
            </div>
        <?php else: ?>
            <div class="rx-lib-table-wrap">
                <table class="rx-lib-table">
                    <thead>
                    <tr>
                        <th>
                            <?= !empty($definition['medicine'])
                                ? 'Generic / Brand'
                                : prescription_e($definition['singular']) ?>
                        </th>
                        <th>Source</th>
                        <th>Used</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($items as $item): ?>
                        <?php $is_demo = (int)($item['doctor_id'] ?? 0) === 0; ?>
                        <tr>
                            <td>
                                <?php if (!empty($definition['medicine'])): ?>
                                    <strong><?= prescription_e($item['generic_name'] ?? '') ?></strong>
                                    <?php if (trim((string)($item['brand_name'] ?? '')) !== ''): ?>
                                        <span class="rx-lib-subtext">
                                            Brand: <?= prescription_e($item['brand_name']) ?>
                                        </span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <strong><?= prescription_e($item['option_value'] ?? '') ?></strong>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="rx-lib-source <?= $is_demo ? 'demo' : 'personal' ?>">
                                    <?= $is_demo ? 'Shared Demo' : 'My Library' ?>
                                </span>
                            </td>
                            <td><?= prescription_e((string)($item['usage_count'] ?? 0)) ?></td>
                            <td>
                                <span class="rx-lib-status <?= prescription_e((string)($item['status'] ?? 'active')) ?>">
                                    <?= prescription_e(ucfirst((string)($item['status'] ?? 'active'))) ?>
                                </span>
                            </td>
                            <td>
                                <div class="rx-lib-actions">
                                    <?php if (!$is_demo): ?>
                                        <a
                                            class="rx-lib-btn is-small"
                                            href="<?= prescription_e(
                                                prescription_library_form_url(
                                                    $library_type,
                                                    (int)$item['id']
                                                )
                                            ) ?>"
                                        >Edit</a>
                                    <?php endif; ?>

                                    <form
                                        method="POST"
                                        data-confirm-message="<?= prescription_e($is_demo
                                            ? 'Hide this shared demo from your account?'
                                            : 'Delete this personal library item permanently?') ?>"
                                    >
                                        <input
                                            type="hidden"
                                            name="csrf_token"
                                            value="<?= prescription_e(prescription_csrf_token()) ?>"
                                        >
                                        <input type="hidden" name="form_action" value="delete_item">
                                        <input
                                            type="hidden"
                                            name="item_id"
                                            value="<?= prescription_e((string)$item['id']) ?>"
                                        >
                                        <button
                                            type="submit"
                                            class="rx-lib-btn is-small <?= $is_demo ? '' : 'is-danger' ?>"
                                        >
                                            <?= $is_demo ? 'Hide Demo' : 'Delete' ?>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($total_pages > 1): ?>
                <nav class="rx-lib-pagination" aria-label="Library pagination">
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <?php
                        $params = array_filter(
                            [
                                'q' => $q,
                                'status' => $status,
                                'scope' => $scope,
                                'page' => $i,
                            ],
                            static fn($value) => $value !== ''
                        );

                        $page_url = prescription_library_list_url($library_type)
                            . '?'
                            . http_build_query($params);
                        ?>
                        <a
                            href="<?= prescription_e($page_url) ?>"
                            class="<?= $i === $page ? 'is-active' : '' ?>"
                        ><?= prescription_e((string)$i) ?></a>
                    <?php endfor; ?>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>

<?php
rx_library_shared_layout_end();
require_once dirname(__DIR__) . '/includes/footer.php';
