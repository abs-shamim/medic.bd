<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/social-post-helper.php';
require_admin();
if (function_exists('require_admin_permission')) {
    require_admin_permission('doctors.view');
}

if (!medic_social_database_ready()) {
    redirect('social-posts.php');
}

$post_id = (int) ($_GET['post_id'] ?? 0);
$posts = medic_social_recent_posts(40, $post_id);

require_once __DIR__ . '/includes/header.php';
?>
<style>
.medic-social-wrap{max-width:1250px;margin:0 auto;padding:22px}.medic-social-card{background:#fff;border:1px solid #d0d7de;border-radius:12px;padding:20px;margin-bottom:18px;box-shadow:0 1px 0 rgba(27,31,36,.04)}.medic-social-card h1,.medic-social-card h2{margin:0 0 9px}.medic-social-card p{color:#57606a;line-height:1.65}.medic-social-table{width:100%;border-collapse:collapse}.medic-social-table th,.medic-social-table td{padding:12px;border-bottom:1px solid #d8dee4;text-align:left;vertical-align:top}.medic-social-badge{display:inline-block;padding:4px 8px;border-radius:999px;background:#f6f8fa;border:1px solid #d0d7de;font-size:12px;font-weight:700}.medic-social-ok{background:#dafbe1;color:#116329;border-color:#a7f3d0}.medic-social-error{background:#ffebe9;color:#cf222e;border-color:#fecaca}.medic-social-muted{font-size:13px;color:#57606a}.medic-social-caption{max-width:420px;white-space:pre-wrap;line-height:1.5}.medic-social-btn{display:inline-block;border:1px solid #d0d7de;border-radius:7px;background:#f6f8fa;color:#24292f;padding:9px 12px;font-weight:700;text-decoration:none}.medic-social-alert{padding:13px 15px;border-radius:9px;margin-bottom:16px;font-weight:600}.medic-social-alert-success{background:#dafbe1;color:#116329;border:1px solid #a7f3d0}.medic-social-alert-error{background:#ffebe9;color:#cf222e;border:1px solid #fecaca}@media(max-width:760px){.medic-social-wrap{padding:14px}.medic-social-table{font-size:13px}.medic-social-table th:nth-child(5),.medic-social-table td:nth-child(5){display:none}}
</style>
<div class="medic-social-wrap">
    <?php medic_social_show_flash(); ?>
    <div class="medic-social-card">
        <p><a href="social-posts.php">← Social Posts</a></p>
        <h1>Publishing History</h1>
        <p>Each selected destination is stored as its own Buffer delivery. Errors retain their message, and safe typed Buffer errors can be retried by the cron job.</p>
    </div>
    <?php foreach ($posts as $post): $targets = medic_social_post_targets((int)$post['id']); ?>
        <div class="medic-social-card">
            <h2>#<?= e((string)$post['id']) ?> · <?= e((string)$post['title']) ?></h2>
            <p><span class="medic-social-badge <?= in_array($post['overall_status'], ['error','partial'], true) ? 'medic-social-error' : 'medic-social-ok' ?>"><?= e(medic_social_format_status((string)$post['overall_status'])) ?></span> &nbsp; <?= e((string)$post['publish_mode']) ?><?php if (!empty($post['scheduled_at_utc'])): ?> · <?= e(medic_social_utc_to_local((string)$post['scheduled_at_utc'])) ?><?php endif; ?></p>
            <?php if (!empty($post['link_url'])): ?><p class="medic-social-muted"><a target="_blank" rel="noopener" href="<?= e((string)$post['link_url']) ?>">Open doctor profile</a></p><?php endif; ?>
            <div style="overflow:auto"><table class="medic-social-table"><thead><tr><th>Platform</th><th>Channel</th><th>Status</th><th>Buffer ID / time</th><th>Caption</th><th>Error</th></tr></thead><tbody>
            <?php foreach ($targets as $target): $is_error = (string)$target['delivery_status'] === 'error'; ?>
                <tr>
                    <td><strong><?= e(ucfirst((string)$target['platform'])) ?></strong></td>
                    <td><?= e((string)$target['channel_name']) ?><br><span class="medic-social-muted"><?= e((string)$target['channel_id']) ?></span></td>
                    <td><span class="medic-social-badge <?= $is_error ? 'medic-social-error' : 'medic-social-ok' ?>"><?= e(medic_social_format_status((string)$target['delivery_status'])) ?></span><?php if (!empty($target['retry_allowed'])): ?><br><span class="medic-social-muted">Auto-retry allowed</span><?php endif; ?></td>
                    <td><?= e((string)$target['buffer_post_id']) ?><?php if (!empty($target['buffer_due_at'])): ?><br><span class="medic-social-muted"><?= e(medic_social_utc_to_local((string)$target['buffer_due_at'])) ?></span><?php endif; ?><?php if (!empty($target['external_link'])): ?><br><a href="<?= e((string)$target['external_link']) ?>" target="_blank" rel="noopener">Open destination</a><?php endif; ?></td>
                    <td class="medic-social-caption"><?= e((string)$target['caption']) ?></td>
                    <td class="medic-social-caption"><?= $is_error ? e((string)$target['error_message']) : '—' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody></table></div>
        </div>
    <?php endforeach; ?>
    <?php if (empty($posts)): ?><div class="medic-social-card"><p>No social post records yet.</p></div><?php endif; ?>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
