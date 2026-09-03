<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/social-post-helper.php';
require_admin();
if (function_exists('require_admin_permission')) {
    require_admin_permission('doctors.view');
}

$database_ready = medic_social_database_ready();
$platforms = medic_social_platforms();
$current_org_id = trim((string) ($_GET['organization_id'] ?? medic_social_site_setting('social_buffer_organization_id')));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!medic_social_verify_csrf($_POST['csrf_token'] ?? '')) {
        medic_social_flash('error', 'Security check failed. Refresh the page and try again.');
        redirect('social-posts.php');
    }

    $action = trim((string) ($_POST['action'] ?? ''));

    if ($action === 'test_connection') {
        $result = medic_buffer_get_organizations();
        medic_social_flash($result['ok'] ? 'success' : 'error', $result['ok'] ? 'Buffer connection is working.' : implode(' ', $result['errors']));
        redirect('social-posts.php');
    }

    if ($action === 'save_mapping') {
        $organization_id = trim((string) ($_POST['organization_id'] ?? ''));
        $channels = medic_buffer_get_channels($organization_id);

        if ($organization_id === '' || !$channels['ok']) {
            medic_social_flash('error', $organization_id === '' ? 'Choose a Buffer organization first.' : implode(' ', $channels['errors']));
            redirect('social-posts.php');
        }

        $available = [];
        foreach ($channels['channels'] as $channel) {
            $available[(string) ($channel['id'] ?? '')] = $channel;
        }

        $settings = ['social_buffer_organization_id' => $organization_id];

        foreach ($platforms as $platform_key => $platform) {
            $selected_id = trim((string) ($_POST['channel_' . $platform_key] ?? ''));
            $channel = $available[$selected_id] ?? null;

            $settings[$platform['setting_id']] = $channel ? $selected_id : '';
            $settings[$platform['setting_name']] = $channel
                ? trim((string) (($channel['displayName'] ?? '') ?: ($channel['name'] ?? '')))
                : '';
        }

        $saved_mapping = medic_social_save_settings($settings);
        medic_social_flash(
            $saved_mapping ? 'success' : 'error',
            $saved_mapping ? 'Buffer channel mapping saved.' : 'Could not save Buffer channel mapping.'
        );
        redirect('social-posts.php?organization_id=' . rawurlencode($organization_id));
    }
}

$organizations = medic_buffer_is_configured() ? medic_buffer_get_organizations() : ['ok' => false, 'organizations' => [], 'errors' => []];
$channels = $current_org_id !== '' && medic_buffer_is_configured()
    ? medic_buffer_get_channels($current_org_id)
    : ['ok' => false, 'channels' => [], 'errors' => []];
$configured_channels = medic_social_configured_channels();

$doctor_q = trim((string) ($_GET['q'] ?? ''));
$doctors = [];
if ($database_ready) {
    try {
        $sql = 'SELECT id, name, specialty_id, primary_hospital, slug, image FROM doctors';
        $params = [];
        if ($doctor_q !== '') {
            $sql .= ' WHERE name LIKE :q OR primary_hospital LIKE :q';
            $params[':q'] = '%' . $doctor_q . '%';
        }
        $sql .= ' ORDER BY id DESC LIMIT 30';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $doctors = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $doctors = [];
    }
}

require_once __DIR__ . '/includes/header.php';
?>
<style>
.medic-social-wrap{max-width:1250px;margin:0 auto;padding:22px}.medic-social-card{background:#fff;border:1px solid #d0d7de;border-radius:12px;padding:20px;margin-bottom:18px;box-shadow:0 1px 0 rgba(27,31,36,.04)}.medic-social-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}.medic-social-card h1,.medic-social-card h2{margin:0 0 10px;color:#24292f}.medic-social-card p{color:#57606a;line-height:1.65}.medic-social-label{display:block;font-size:13px;font-weight:700;margin:0 0 7px}.medic-social-select,.medic-social-input{width:100%;min-height:42px;border:1px solid #d0d7de;border-radius:7px;padding:9px 11px;background:#fff}.medic-social-btn{display:inline-block;border:1px solid #1f883d;border-radius:7px;background:#2da44e;color:#fff;padding:10px 14px;font-weight:700;text-decoration:none;cursor:pointer}.medic-social-btn-light{border-color:#d0d7de;background:#f6f8fa;color:#24292f}.medic-social-alert{padding:13px 15px;border-radius:9px;margin-bottom:16px;font-weight:600}.medic-social-alert-success{background:#dafbe1;color:#116329;border:1px solid #a7f3d0}.medic-social-alert-error{background:#ffebe9;color:#cf222e;border:1px solid #fecaca}.medic-social-table{width:100%;border-collapse:collapse}.medic-social-table th,.medic-social-table td{padding:12px;border-bottom:1px solid #d8dee4;text-align:left;vertical-align:top}.medic-social-badge{display:inline-block;padding:4px 8px;border-radius:999px;background:#f1f8ff;color:#0969da;font-size:12px;font-weight:700}.medic-social-muted{color:#57606a;font-size:13px}.medic-social-inline{display:flex;gap:10px;align-items:end;flex-wrap:wrap}.medic-social-inline>div{flex:1;min-width:240px}@media(max-width:760px){.medic-social-grid{grid-template-columns:1fr}.medic-social-wrap{padding:14px}.medic-social-table{font-size:13px}.medic-social-table th:nth-child(3),.medic-social-table td:nth-child(3){display:none}}
</style>
<div class="medic-social-wrap">
    <?php medic_social_show_flash(); ?>
    <div class="medic-social-card">
        <h1>Social Posts</h1>
        <p>Connect Buffer once, map your Facebook Page, Instagram Professional account, LinkedIn Page and X channel, then publish doctor profiles from one admin workflow.</p>
        <?php if (!$database_ready): ?>
            <div class="medic-social-alert medic-social-alert-error">Import <code>database/social-posts.sql</code> before using this module.</div>
        <?php endif; ?>
        <?php if (!medic_buffer_is_configured()): ?>
            <div class="medic-social-alert medic-social-alert-error">BUFFER_API_KEY is not configured. Set it server-side in the environment, then reload this page.</div>
        <?php endif; ?>
        <form method="post" style="display:inline">
            <input type="hidden" name="csrf_token" value="<?= e(medic_social_csrf_token()) ?>">
            <input type="hidden" name="action" value="test_connection">
            <button class="medic-social-btn medic-social-btn-light" type="submit">Test Buffer Connection</button>
        </form>
        <a class="medic-social-btn medic-social-btn-light" href="social-post-history.php">Open Publishing History</a>
    </div>

    <div class="medic-social-card">
        <h2>Buffer Channel Mapping</h2>
        <p>Select the exact Buffer channel used for each destination. A channel may be connected but locked or disconnected, so check the status shown in Buffer before publishing.</p>
        <?php if (!$organizations['ok'] && medic_buffer_is_configured()): ?>
            <div class="medic-social-alert medic-social-alert-error"><?= e(implode(' ', $organizations['errors'])) ?></div>
        <?php endif; ?>
        <form method="get" class="medic-social-inline" style="margin-bottom:16px">
            <div>
                <label class="medic-social-label">Buffer Organization</label>
                <select class="medic-social-select" name="organization_id" onchange="this.form.submit()">
                    <option value="">Select organization</option>
                    <?php foreach ($organizations['organizations'] as $organization): ?>
                        <option value="<?= e((string)($organization['id'] ?? '')) ?>" <?= (string)($organization['id'] ?? '') === $current_org_id ? 'selected' : '' ?>><?= e((string)(($organization['name'] ?? '') ?: ($organization['id'] ?? ''))) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="flex:0 0 auto"><button class="medic-social-btn medic-social-btn-light" type="submit">Load Channels</button></div>
        </form>
        <?php if ($current_org_id !== '' && $channels['ok']): ?>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= e(medic_social_csrf_token()) ?>">
                <input type="hidden" name="action" value="save_mapping">
                <input type="hidden" name="organization_id" value="<?= e($current_org_id) ?>">
                <div class="medic-social-grid">
                    <?php foreach ($platforms as $key => $platform): $selected = $configured_channels[$key]['channel_id'] ?? ''; ?>
                        <div>
                            <label class="medic-social-label"><?= e($platform['label']) ?></label>
                            <select class="medic-social-select" name="channel_<?= e($key) ?>">
                                <option value="">Not connected in this workflow</option>
                                <?php foreach ($channels['channels'] as $channel):
                                    $channel_id = (string)($channel['id'] ?? '');
                                    $name = (string)(($channel['displayName'] ?? '') ?: ($channel['name'] ?? ''));
                                    $state = !empty($channel['isDisconnected']) ? 'Disconnected' : (!empty($channel['isLocked']) ? 'Locked' : 'Active');
                                ?>
                                    <option value="<?= e($channel_id) ?>" <?= $channel_id === $selected ? 'selected' : '' ?>><?= e($name . ' — ' . ($channel['descriptor'] ?? $channel['service'] ?? '') . ' (' . $state . ')') ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endforeach; ?>
                </div>
                <p style="margin-top:16px"><button class="medic-social-btn" type="submit">Save Channel Mapping</button></p>
            </form>
        <?php elseif ($current_org_id !== '' && !$channels['ok']): ?>
            <div class="medic-social-alert medic-social-alert-error"><?= e(implode(' ', $channels['errors'])) ?></div>
        <?php endif; ?>
    </div>

    <div class="medic-social-card">
        <h2>Publish a Doctor Profile</h2>
        <form method="get" class="medic-social-inline" style="margin-bottom:12px">
            <div><label class="medic-social-label">Search doctor or hospital</label><input class="medic-social-input" name="q" value="<?= e($doctor_q) ?>" placeholder="Doctor name or hospital"></div>
            <div style="flex:0 0 auto"><button class="medic-social-btn medic-social-btn-light" type="submit">Search</button></div>
        </form>
        <div style="overflow:auto">
            <table class="medic-social-table">
                <thead><tr><th>Doctor</th><th>Hospital</th><th>Profile</th><th>Action</th></tr></thead>
                <tbody>
                <?php foreach ($doctors as $doctor): ?>
                    <tr>
                        <td><strong><?= e((string)($doctor['name'] ?? '')) ?></strong><br><span class="medic-social-muted">ID #<?= e((string)($doctor['id'] ?? '')) ?></span></td>
                        <td><?= e((string)($doctor['primary_hospital'] ?? '')) ?></td>
                        <td><?php if (!empty($doctor['slug'])): ?><a href="<?= e(site_url('doctor/' . $doctor['slug'])) ?>" target="_blank" rel="noopener">View</a><?php endif; ?></td>
                        <td><a class="medic-social-btn" href="doctor-social-post.php?doctor_id=<?= e((string)($doctor['id'] ?? '')) ?>">Social Post</a></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($doctors)): ?><tr><td colspan="4" class="medic-social-muted">No doctors found.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
