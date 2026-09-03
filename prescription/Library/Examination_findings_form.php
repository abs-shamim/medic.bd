<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/functions.php';

$library_type = 'examination';
$library_type = prescription_normalize_library_type($library_type);
$definition = prescription_library_definition($library_type);
$managed_pages = prescription_managed_library_pages();

if (!$definition || !isset($managed_pages[$library_type])) {
    http_response_code(404);
    exit('Library form was not found.');
}

$item_id = max(0, (int)($_POST['item_id'] ?? $_GET['id'] ?? 0));
$editing_item = $item_id > 0
    ? prescription_get_own_library_item($pdo, $item_id, $doctor_id, $library_type)
    : null;

if ($item_id > 0 && !$editing_item && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    prescription_flash('error', 'Only your own library item can be edited.');
    prescription_redirect(prescription_library_list_path($library_type));
}

$form_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!prescription_verify_csrf($_POST['csrf_token'] ?? null)) {
        $form_error = 'Security verification failed. Please refresh and try again.';
    } else {
        try {
            prescription_save_library_item(
                $pdo,
                $doctor_id,
                $library_type,
                $_POST,
                $item_id
            );

            prescription_flash(
                'success',
                $item_id > 0
                    ? $definition['singular'] . ' updated successfully.'
                    : $definition['singular'] . ' added to your personal library.'
            );

            prescription_redirect(prescription_library_list_path($library_type));
        } catch (InvalidArgumentException $e) {
            $form_error = $e->getMessage();
        } catch (Throwable $e) {
            error_log('[Library Form][examination] ' . $e->getMessage());
            $form_error = $definition['singular'] . ' could not be saved.';
        }
    }
}

$page_title = ($editing_item ? 'Edit ' : 'Add ') . $definition['singular'];
require_once dirname(__DIR__) . '/includes/header.php';

$library_shell_title = ($editing_item ? 'Edit ' : 'Add ') . $definition['singular'];
$library_shell_description = $definition['description']
    . ' This personal value remains private to your doctor account.';
$library_shell_primary_label = 'Manage Items';
$library_shell_primary_url = prescription_library_list_url($library_type);
$library_shell_secondary_label = 'Library Dashboard';
$library_shell_secondary_url = prescription_library_dashboard_url();

require __DIR__ . '/librarys.php';
?>

<?php if ($form_error !== ''): ?>
    <div class="rx-lib-error" role="alert">
        <span class="rx-lib-error-icon">!</span>
        <div>
            <strong>Library item was not saved</strong>
            <p><?= prescription_e($form_error) ?></p>
        </div>
    </div>
<?php endif; ?>

<div class="rx-lib-notice">
    <span class="rx-lib-notice-icon">i</span>
    <div>
        <strong>Personal library item</strong>
        <span>
            You can edit, disable or delete this value later. Shared demo items remain protected.
        </span>
    </div>
</div>

<section class="rx-lib-panel">
    <div class="rx-lib-panel-head">
        <div>
            <h3>
                <?= $editing_item ? 'Update ' : 'Create ' ?>
                <?= prescription_e($definition['singular']) ?>
            </h3>
            <p>Complete the required fields and save the item.</p>
        </div>

        <span class="rx-lib-badge">
            <?= $editing_item ? 'Editing Item' : 'New Item' ?>
        </span>
    </div>

    <form method="POST" class="rx-lib-panel-body" autocomplete="off">
        <input
            type="hidden"
            name="csrf_token"
            value="<?= prescription_e(prescription_csrf_token()) ?>"
        >
        <input
            type="hidden"
            name="item_id"
            value="<?= prescription_e((string)$item_id) ?>"
        >

        <div class="rx-lib-form-grid">
            <?php if (!empty($definition['medicine'])): ?>
                <div class="rx-lib-field">
                    <label for="rxGenericName">
                        Generic Name <span class="rx-lib-required">*</span>
                    </label>
                    <input
                        id="rxGenericName"
                        type="text"
                        name="generic_name"
                        value="<?= prescription_e(
                            rx_library_shared_form_value($editing_item, 'generic_name')
                        ) ?>"
                        required
                        maxlength="200"
                        placeholder="Example: Paracetamol"
                        autofocus
                    >
                    <small>The generic medicine name is required.</small>
                </div>

                <div class="rx-lib-field">
                    <label for="rxBrandName">Brand Name (optional)</label>
                    <input
                        id="rxBrandName"
                        type="text"
                        name="brand_name"
                        value="<?= prescription_e(
                            rx_library_shared_form_value($editing_item, 'brand_name')
                        ) ?>"
                        maxlength="200"
                        placeholder="Example: Optional brand name"
                    >
                    <small>Leave empty when no brand name is needed.</small>
                </div>
            <?php else: ?>
                <div class="rx-lib-field is-full">
                    <label for="rxOptionValue">
                        <?= prescription_e($definition['singular']) ?>
                        <span class="rx-lib-required">*</span>
                    </label>
                    <input
                        id="rxOptionValue"
                        type="text"
                        name="option_value"
                        value="<?= prescription_e(
                            rx_library_shared_form_value($editing_item, 'option_value')
                        ) ?>"
                        required
                        maxlength="<?= prescription_e((string)$definition['max_length']) ?>"
                        placeholder="<?= prescription_e($definition['placeholder']) ?>"
                        autofocus
                    >
                </div>
            <?php endif; ?>

            <div class="rx-lib-field is-full">
                <label for="rxLibraryStatus">Status</label>
                <?php
                $selected_status = rx_library_shared_form_value(
                    $editing_item,
                    'status',
                    'active'
                );
                ?>
                <select id="rxLibraryStatus" name="status">
                    <option value="active" <?= $selected_status === 'active' ? 'selected' : '' ?>>
                        Active
                    </option>
                    <option value="inactive" <?= $selected_status === 'inactive' ? 'selected' : '' ?>>
                        Inactive
                    </option>
                </select>
                <small>
                    Inactive values remain saved but do not appear in prescription dropdowns.
                </small>
            </div>
        </div>

        <div class="rx-lib-submitbar">
            <p>
                <?= $editing_item
                    ? 'Update your personal library value.'
                    : 'Save this value to your personal doctor library.' ?>
            </p>

            <div class="rx-lib-actions">
                <a
                    class="rx-lib-btn"
                    href="<?= prescription_e(prescription_library_list_url($library_type)) ?>"
                >Cancel</a>
                <button type="submit" class="rx-lib-btn is-primary">
                    <?= $editing_item ? 'Update Item' : 'Save Item' ?>
                </button>
            </div>
        </div>
    </form>
</section>

<?php
rx_library_shared_layout_end();
require_once dirname(__DIR__) . '/includes/footer.php';
