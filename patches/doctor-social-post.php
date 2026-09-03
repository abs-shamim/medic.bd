<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/social-post-helper.php';
require_admin();
if (function_exists('require_admin_permission')) {
    require_admin_permission('doctors.view');
}

if (!medic_social_database_ready()) {
    require_once __DIR__ . '/includes/header.php';
    echo '<div style="max-width:900px;margin:30px auto;padding:20px;background:#fff;border:1px solid #fecaca;border-radius:12px"><h1>Social module database is not ready</h1><p>Import <code>database/social-posts.sql</code>, then return here.</p><p><a href="social-posts.php">Back to Social Posts</a></p></div>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

$doctor_id = (int) ($_GET['doctor_id'] ?? $_POST['doctor_id'] ?? 0);
$doctor = medic_social_get_doctor($doctor_id);

if (!$doctor) {
    medic_social_flash('error', 'Doctor profile was not found.');
    redirect('social-posts.php');
}

$configured = medic_social_configured_channels();
$captions = medic_social_doctor_captions($doctor);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'publish_doctor') {
    if (!medic_social_verify_csrf($_POST['csrf_token'] ?? '')) {
        medic_social_flash('error', 'Security check failed. Refresh the page and try again.');
        redirect('doctor-social-post.php?doctor_id=' . $doctor_id);
    }

    if (!medic_buffer_is_configured()) {
        medic_social_flash('error', 'Buffer API key is not configured on the server.');
        redirect('doctor-social-post.php?doctor_id=' . $doctor_id);
    }

    $selected = $_POST['targets'] ?? [];
    $selected = is_array($selected) ? array_values(array_intersect(array_keys(medic_social_platforms()), $selected)) : [];

    if (empty($selected)) {
        medic_social_flash('error', 'Choose at least one social platform.');
        redirect('doctor-social-post.php?doctor_id=' . $doctor_id);
    }

    $mode = trim((string) ($_POST['publish_mode'] ?? 'addToQueue'));
    $mode = in_array($mode, ['shareNow', 'addToQueue', 'customScheduled'], true) ? $mode : 'addToQueue';
    $scheduled_utc = null;

    if ($mode === 'customScheduled') {
        $scheduled_utc = medic_social_local_to_utc((string) ($_POST['scheduled_at'] ?? ''));

        if ($scheduled_utc === null) {
            medic_social_flash('error', 'Choose a valid future schedule date and time.');
            redirect('doctor-social-post.php?doctor_id=' . $doctor_id);
        }

        try {
            if (new DateTimeImmutable($scheduled_utc) <= new DateTimeImmutable('now', new DateTimeZone('UTC'))) {
                medic_social_flash('error', 'Scheduled time must be in the future.');
                redirect('doctor-social-post.php?doctor_id=' . $doctor_id);
            }
        } catch (Throwable $e) {
            medic_social_flash('error', 'Scheduled time could not be processed.');
            redirect('doctor-social-post.php?doctor_id=' . $doctor_id);
        }
    }

    $uploaded = medic_social_upload_image('social_image');
    if (!$uploaded['ok']) {
        medic_social_flash('error', $uploaded['message']);
        redirect('doctor-social-post.php?doctor_id=' . $doctor_id);
    }

    $image_url = medic_social_absolute_url((string) ($_POST['image_url'] ?? ''));
    if ($uploaded['url'] !== '') {
        $image_url = $uploaded['url'];
    }
    if ($image_url === '') {
        $image_url = (string) ($doctor['social_image_url'] ?? '');
    }

    foreach ($selected as $platform) {
        if (empty($configured[$platform]['configured'])) {
            medic_social_flash('error', ucfirst($platform) . ' does not have a saved Buffer channel mapping.');
            redirect('doctor-social-post.php?doctor_id=' . $doctor_id);
        }

        if (!empty($configured[$platform]['requires_image']) && $image_url === '') {
            medic_social_flash('error', 'Instagram requires a public image URL or a newly uploaded image.');
            redirect('doctor-social-post.php?doctor_id=' . $doctor_id);
        }

        $caption = trim((string) ($_POST['caption_' . $platform] ?? ''));
        if ($caption === '') {
            medic_social_flash('error', ucfirst($platform) . ' caption cannot be empty.');
            redirect('doctor-social-post.php?doctor_id=' . $doctor_id);
        }
    }

    $title = trim((string) ($_POST['title'] ?? ''));
    if ($title === '') {
        $title = 'Doctor social post: ' . (string) ($doctor['name'] ?? '');
    }

    $social_post_id = medic_social_create_post([
        'source_type' => 'doctor',
        'source_id' => $doctor_id,
        'title' => medic_social_limit($title, 255),
        'link_url' => (string) ($doctor['profile_url'] ?? ''),
        'image_url' => $image_url,
        'publish_mode' => $mode,
        'scheduled_at_utc' => $scheduled_utc,
    ]);

    medic_social_create_schedule_audit($social_post_id, $scheduled_utc);

    $success = 0;
    $failed = 0;
    foreach ($selected as $platform) {
        $channel = $configured[$platform];
        $caption = trim((string) ($_POST['caption_' . $platform] ?? ''));
        $target_id = medic_social_create_target($social_post_id, [
            'platform' => $platform,
            'channel_id' => $channel['channel_id'],
            'channel_name' => $channel['channel_name'] !== '' ? $channel['channel_name'] : $channel['label'],
            'caption' => $caption,
        ]);

        $result = medic_buffer_create_post(
            $channel['channel_id'],
            $caption,
            $mode,
            $scheduled_utc,
            $image_url
        );

        medic_social_update_target_result($target_id, $result, $mode);
        medic_social_log($social_post_id, $target_id, 'create_post', $result);

        if (!empty($result['ok'])) {
            $success++;
        } else {
            $failed++;
        }
    }

    medic_social_update_overall_status($social_post_id);

    if (function_exists('admin_log_activity')) {
        admin_log_activity('social_post_created', 'Created social campaign #' . $social_post_id . ' for doctor #' . $doctor_id . '. Success: ' . $success . ', failed: ' . $failed . '.');
    }

    medic_social_flash($failed > 0 ? 'error' : 'success', $failed > 0
        ? 'Campaign created, but ' . $failed . ' platform request(s) failed. Check Publishing History.'
        : 'Campaign sent to Buffer for ' . $success . ' platform(s).');
    redirect('social-post-history.php?post_id=' . $social_post_id);
}

require_once __DIR__ . '/includes/header.php';
?>
<style>
.medic-social-wrap{max-width:1180px;margin:0 auto;padding:22px}.medic-social-card{background:#fff;border:1px solid #d0d7de;border-radius:12px;padding:20px;margin-bottom:18px;box-shadow:0 1px 0 rgba(27,31,36,.04)}.medic-social-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}.medic-social-full{grid-column:1/-1}.medic-social-card h1,.medic-social-card h2,.medic-social-card h3{margin:0 0 9px;color:#24292f}.medic-social-card p{color:#57606a;line-height:1.65}.medic-social-label{display:block;font-size:13px;font-weight:700;margin:0 0 7px}.medic-social-input,.medic-social-select,.medic-social-textarea{width:100%;border:1px solid #d0d7de;border-radius:7px;padding:10px 11px;background:#fff;font:inherit}.medic-social-textarea{min-height:180px;line-height:1.55;resize:vertical}.medic-social-btn{display:inline-block;border:1px solid #1f883d;border-radius:7px;background:#2da44e;color:#fff;padding:10px 14px;font-weight:700;text-decoration:none;cursor:pointer}.medic-social-btn-light{border-color:#d0d7de;background:#f6f8fa;color:#24292f}.medic-social-alert{padding:13px 15px;border-radius:9px;margin-bottom:16px;font-weight:600}.medic-social-alert-success{background:#dafbe1;color:#116329;border:1px solid #a7f3d0}.medic-social-alert-error{background:#ffebe9;color:#cf222e;border:1px solid #fecaca}.medic-social-profile{display:flex;gap:16px;align-items:center}.medic-social-profile img{width:82px;height:82px;border-radius:14px;object-fit:cover;background:#f6f8fa;border:1px solid #d0d7de}.medic-social-target{border:1px solid #d8dee4;border-radius:10px;padding:15px}.medic-social-target-head{display:flex;justify-content:space-between;gap:10px;align-items:center;margin-bottom:12px}.medic-social-badge{display:inline-block;padding:4px 8px;border-radius:999px;background:#f1f8ff;color:#0969da;font-size:12px;font-weight:700}.medic-social-muted{color:#57606a;font-size:13px}.medic-social-preview{padding:12px;border-radius:8px;background:#f6f8fa;font-size:13px;word-break:break-all}@media(max-width:760px){.medic-social-wrap{padding:14px}.medic-social-grid{grid-template-columns:1fr}.medic-social-profile{align-items:flex-start}}
</style>
<div class="medic-social-wrap">
    <div class="medic-social-card">
        <p><a href="social-posts.php">← Back to Social Posts</a></p>
        <div class="medic-social-profile">
            <?php if (!empty($doctor['social_image_url'])): ?><img src="<?= e($doctor['social_image_url']) ?>" alt="<?= e((string)$doctor['name']) ?>"><?php endif; ?>
            <div>
                <h1><?= e((string)($doctor['name'] ?? 'Doctor')) ?></h1>
                <p><?= e(implode(' · ', array_filter([(string)($doctor['degree'] ?? ''), (string)($doctor['designation'] ?? ''), (string)($doctor['specialty_name'] ?? '')]))) ?></p>
                <p class="medic-social-muted"><?= e((string)($doctor['primary_hospital'] ?? ($doctor['hospital_name'] ?? ''))) ?></p>
            </div>
        </div>
    </div>

    <?php if (!medic_buffer_is_configured()): ?><div class="medic-social-alert medic-social-alert-error">Buffer API key is missing. Configure it before publishing.</div><?php endif; ?>
    <?php foreach ($configured as $channel): if (!$channel['configured']): ?><div class="medic-social-alert medic-social-alert-error"><?= e($channel['label']) ?> is not mapped. <a href="social-posts.php">Open Buffer settings</a></div><?php break; endif; endforeach; ?>

    <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= e(medic_social_csrf_token()) ?>">
        <input type="hidden" name="action" value="publish_doctor">
        <input type="hidden" name="doctor_id" value="<?= e((string)$doctor_id) ?>">
        <div class="medic-social-card">
            <h2>Campaign Settings</h2>
            <div class="medic-social-grid">
                <div class="medic-social-full"><label class="medic-social-label">Campaign title</label><input class="medic-social-input" name="title" value="<?= e('Doctor profile: ' . (string)($doctor['name'] ?? '')) ?>"></div>
                <div><label class="medic-social-label">Publish mode</label><select class="medic-social-select" name="publish_mode" id="publishMode"><option value="shareNow">Publish now</option><option value="addToQueue" selected>Add to Buffer queue</option><option value="customScheduled">Schedule for exact time</option></select></div>
                <div id="scheduleField" style="display:none"><label class="medic-social-label">Schedule time (Bangladesh time)</label><input class="medic-social-input" type="datetime-local" name="scheduled_at"></div>
                <div class="medic-social-full"><label class="medic-social-label">Image URL</label><input class="medic-social-input" type="url" name="image_url" value="<?= e((string)($doctor['social_image_url'] ?? '')) ?>" placeholder="https://medic.bd/assets/uploads/...jpg"><p class="medic-social-muted">Use a public HTTPS image. Instagram needs an image.</p></div>
                <div class="medic-social-full"><label class="medic-social-label">Or upload a new image</label><input class="medic-social-input" type="file" name="social_image" accept="image/jpeg,image/png,image/webp"></div>
                <div class="medic-social-full"><div class="medic-social-preview"><strong>Doctor profile URL:</strong> <?= e((string)($doctor['profile_url'] ?? '')) ?></div></div>
            </div>
        </div>

        <div class="medic-social-card">
            <h2>Platform Captions</h2>
            <p>Edit the generated caption for each platform. Only selected and configured platforms will be sent to Buffer.</p>
            <div class="medic-social-grid">
                <?php foreach (medic_social_platforms() as $platform => $config): $channel = $configured[$platform]; ?>
                    <div class="medic-social-target">
                        <div class="medic-social-target-head"><label><input type="checkbox" name="targets[]" value="<?= e($platform) ?>" <?= $channel['configured'] ? 'checked' : 'disabled' ?>> <strong><?= e($config['label']) ?></strong></label><span class="medic-social-badge"><?= e($channel['configured'] ? ($channel['channel_name'] ?: 'Mapped') : 'Not mapped') ?></span></div>
                        <label class="medic-social-label">Caption</label>
                        <textarea class="medic-social-textarea" name="caption_<?= e($platform) ?>"><?= e($captions[$platform] ?? '') ?></textarea>
                    </div>
                <?php endforeach; ?>
            </div>
            <p style="margin-top:18px"><button class="medic-social-btn" type="submit">Send Selected Posts to Buffer</button> <a class="medic-social-btn medic-social-btn-light" href="doctor-form.php?id=<?= e((string)$doctor_id) ?>">Back to Doctor</a></p>
        </div>
    </form>
</div>
<script>
(function(){var mode=document.getElementById('publishMode'),field=document.getElementById('scheduleField');function toggle(){field.style.display=mode.value==='customScheduled'?'block':'none';}mode.addEventListener('change',toggle);toggle();}());
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
