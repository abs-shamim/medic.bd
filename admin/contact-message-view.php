<?php
require_once __DIR__ . '/../includes/functions.php';

require_admin();

if (function_exists('require_admin_permission')) {
    require_admin_permission('contacts.manage');
}

if (table_exists('contacts')) {
    if (!column_exists('contacts', 'notes')) {
        try {
            $pdo->exec("ALTER TABLE `contacts` ADD COLUMN `notes` TEXT NULL AFTER `status`");
        } catch (Throwable $e) {
            // If the hosting database user has no ALTER permission, the note
            // form below still renders but saving it will have no effect.
        }
    }

    if (!column_exists('contacts', 'starred')) {
        try {
            $pdo->exec("ALTER TABLE `contacts` ADD COLUMN `starred` TINYINT(1) NOT NULL DEFAULT 0 AFTER `notes`");
        } catch (Throwable $e) {
            // Same fallback as above.
        }
    }
}

/*
|--------------------------------------------------------------------------
| Conversation Identity: One Stable URL Per Email
|--------------------------------------------------------------------------
| A conversation is identified by the sender's email address, not by a
| single message id. Old links that still pass ?id= are resolved to the
| matching email and redirected here once, so every action afterwards
| (star, delete, mark read/unread, notes) keeps returning to the same
| ?email=... URL no matter which individual message id it acted on.
|--------------------------------------------------------------------------
*/
$email = trim((string)($_GET['email'] ?? ''));

if ($email === '' && isset($_GET['id']) && table_exists('contacts')) {
    $legacy_id = (int)$_GET['id'];

    if ($legacy_id > 0) {
        $stmt = $pdo->prepare("SELECT email FROM contacts WHERE id=:id");
        $stmt->execute([':id' => $legacy_id]);
        $resolved_email = $stmt->fetchColumn();

        if ($resolved_email) {
            redirect('contact-message-view.php?email=' . rawurlencode($resolved_email));
        }
    }
}

$thread_url = 'contact-message-view.php?email=' . rawurlencode($email);

if ($email !== '' && table_exists('contacts')) {
    if (isset($_GET['delete'])) {
        $target_id = (int)$_GET['delete'];

        if ($target_id > 0) {
            $stmt = $pdo->prepare("DELETE FROM contacts WHERE id=:id");
            $stmt->execute([':id' => $target_id]);

            flash('success', 'Message deleted successfully.');
        }

        $remaining = $pdo->prepare("SELECT COUNT(*) FROM contacts WHERE LOWER(email) = LOWER(:email)");
        $remaining->execute([':email' => $email]);

        redirect((int)$remaining->fetchColumn() > 0 ? $thread_url : 'contact-messages.php');
    }

    if (isset($_GET['unread'])) {
        $target_id = (int)$_GET['unread'];

        if ($target_id > 0) {
            $stmt = $pdo->prepare("UPDATE contacts SET status='unread' WHERE id=:id");
            $stmt->execute([':id' => $target_id]);
        }

        redirect($thread_url);
    }

    if (isset($_GET['star'])) {
        $target_id = (int)$_GET['star'];

        if ($target_id > 0) {
            $stmt = $pdo->prepare("SELECT starred FROM contacts WHERE id=:id");
            $stmt->execute([':id' => $target_id]);
            $current = (int)$stmt->fetchColumn();

            $update = $pdo->prepare("UPDATE contacts SET starred=:starred WHERE id=:id");
            $update->execute([':starred' => $current ? 0 : 1, ':id' => $target_id]);
        }

        redirect($thread_url);
    }

    if (isset($_GET['delete_all'])) {
        $stmt = $pdo->prepare("DELETE FROM contacts WHERE LOWER(email) = LOWER(:email)");
        $stmt->execute([':email' => $email]);

        flash('success', 'Conversation deleted successfully.');
        redirect('contact-messages.php');
    }

    if (isset($_GET['mark_all'])) {
        $new_status = $_GET['mark_all'] === 'unread' ? 'unread' : 'read';
        $update = $pdo->prepare("UPDATE contacts SET status=:status WHERE LOWER(email) = LOWER(:email)");
        $update->execute([':status' => $new_status, ':email' => $email]);

        flash('success', 'All messages from this sender marked as ' . $new_status . '.');
        redirect($thread_url);
    }
}

if (empty($_SESSION['admin_contact_note_csrf'])) {
    $_SESSION['admin_contact_note_csrf'] = bin2hex(random_bytes(32));
}

$note_csrf_token = $_SESSION['admin_contact_note_csrf'];

if ($email !== '' && table_exists('contacts') && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_note') {
    $posted_token = (string)($_POST['csrf_token'] ?? '');
    $target_id = (int)($_POST['msg_id'] ?? 0);

    if (hash_equals($note_csrf_token, $posted_token) && $target_id > 0) {
        $note_value = trim((string)($_POST['notes'] ?? ''));

        $stmt = $pdo->prepare("UPDATE contacts SET notes=:notes WHERE id=:id");
        $stmt->execute([':notes' => $note_value !== '' ? $note_value : null, ':id' => $target_id]);

        flash('success', 'Note saved successfully.');
    } else {
        flash('error', 'Security token mismatch. Please try again.');
    }

    redirect($thread_url);
}

$messages = [];

if ($email !== '' && table_exists('contacts')) {
    /*
     * Opening a conversation marks every message in it as read, matching
     * how a normal inbox behaves when you open a thread.
     */
    $mark_read = $pdo->prepare("UPDATE contacts SET status='read' WHERE LOWER(email) = LOWER(:email) AND status='unread'");
    $mark_read->execute([':email' => $email]);

    $stmt = $pdo->prepare("SELECT * FROM contacts WHERE LOWER(email) = LOWER(:email) ORDER BY created_at ASC, id ASC");
    $stmt->execute([':email' => $email]);
    $messages = $stmt->fetchAll();
}

require_once __DIR__ . '/includes/header.php';

if (!$messages) {
    echo '<h1 style="margin-bottom:18px;color:#0f172a;">Conversation Not Found</h1>';
    echo '<div class="card"><p>No messages were found for this sender, or they were already deleted.</p></div>';
    echo '<p style="margin-top:14px;"><a class="btn btn-outline" href="contact-messages.php">Back to Contact Messages</a></p>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

if (!function_exists('cm_avatar_color')) {
    function cm_avatar_color(string $seed): string
    {
        $colors = ['#0969da', '#8250df', '#1a7f37', '#bf3989', '#9a6700', '#cf222e', '#0550ae', '#6639ba'];
        $index = abs(crc32(strtolower($seed))) % count($colors);
        return $colors[$index];
    }
}

/*
|--------------------------------------------------------------------------
| Registered Customer Match
|--------------------------------------------------------------------------
*/
$customer = null;

if (table_exists('users')) {
    try {
        $stmt = $pdo->prepare("SELECT id, name, email FROM users WHERE LOWER(email) = LOWER(:email) LIMIT 1");
        $stmt->execute([':email' => $email]);
        $customer = $stmt->fetch() ?: null;
    } catch (Throwable $e) {
        $customer = null;
    }
}

$latest_message = $messages[count($messages) - 1];
$display_name = trim((string)$latest_message['name']) !== '' ? trim((string)$latest_message['name']) : $email;
$reply_href = 'mailto:' . rawurlencode($email) . '?subject=' . rawurlencode('Re: ' . ($latest_message['subject'] !== '' ? $latest_message['subject'] : '(no subject)'));
$forward_body = "\n\n---------- Forwarded message ----------\n"
    . "From: " . $display_name . " <" . $email . ">\n"
    . "Date: " . date('M d, Y, h:i A', strtotime((string)($latest_message['created_at'] ?? 'now'))) . "\n"
    . "Subject: " . ($latest_message['subject'] !== '' ? $latest_message['subject'] : '(no subject)') . "\n\n"
    . (string)($latest_message['message'] ?? '');
$forward_href = 'mailto:?subject=' . rawurlencode('Fwd: ' . ($latest_message['subject'] !== '' ? $latest_message['subject'] : '(no subject)')) . '&body=' . rawurlencode($forward_body);
?>

<style>
    .cmv-toolbar {
        display: flex;
        align-items: center;
        gap: 6px;
        flex-wrap: wrap;
        margin-bottom: 16px;
        padding: 10px 14px;
        background: #ffffff;
        border: 1px solid #d0d7de;
        border-radius: 10px;
    }

    .cmv-toolbar .cmv-icon-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 34px;
        height: 34px;
        border-radius: 6px;
        border: 0;
        background: transparent;
        color: #57606a;
        text-decoration: none;
        cursor: pointer;
    }

    .cmv-toolbar .cmv-icon-btn:hover {
        background: #eef1f4;
        color: #24292f;
    }

    .cmv-toolbar .cmv-icon-btn.danger:hover {
        background: #ffebe9;
        color: #cf222e;
    }

    .cmv-toolbar-spacer {
        flex: 1;
    }

    .cmv-subject-row {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 12px;
        margin-bottom: 18px;
    }

    .cmv-subject-row h1 {
        margin: 0;
        color: #0f172a;
        font-size: 26px;
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
    }

    .cmv-subject-icons {
        display: flex;
        gap: 2px;
        flex-shrink: 0;
        padding-top: 4px;
    }

    .cmv-kebab {
        position: relative;
    }

    .cmv-kebab summary {
        list-style: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 30px;
        height: 30px;
        border-radius: 6px;
        color: #57606a;
        cursor: pointer;
    }

    .cmv-kebab summary::-webkit-details-marker {
        display: none;
    }

    .cmv-kebab[open] summary,
    .cmv-kebab summary:hover {
        background: #eef1f4;
        color: #24292f;
    }

    .cmv-kebab-menu {
        position: absolute;
        right: 0;
        top: 34px;
        z-index: 20;
        min-width: 170px;
        background: #ffffff;
        border: 1px solid #d0d7de;
        border-radius: 8px;
        box-shadow: 0 8px 24px rgba(27, 31, 36, 0.12);
        padding: 6px;
    }

    .cmv-kebab-menu a {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 7px 10px;
        border-radius: 6px;
        color: #24292f;
        font-size: 13px;
        text-decoration: none;
    }

    .cmv-kebab-menu a:hover {
        background: #f6f8fa;
        text-decoration: none;
    }

    .cmv-pill {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 3px 10px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 700;
        text-decoration: none;
    }

    .cmv-pill.gray {
        background: #eaeef2;
        color: #57606a;
    }

    .cmv-pill.green {
        background: #dafbe1;
        color: #1a7f37;
    }

    .cmv-pill.blue {
        background: #ddf4ff;
        color: #0969da;
    }

    .cmv-banner {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        flex-wrap: wrap;
        padding: 14px 16px;
        border-radius: 10px;
        background: #dafbe1;
        border: 1px solid rgba(26, 127, 55, 0.25);
        margin-bottom: 18px;
    }

    .cmv-banner-icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 38px;
        height: 38px;
        border-radius: 10px;
        background: #ffffff;
        color: #1a7f37;
        margin-right: 12px;
    }

    .cmv-banner-text strong {
        display: block;
        color: #0f172a;
        font-size: 14px;
    }

    .cmv-banner-text span {
        color: #4d7c62;
        font-size: 13px;
    }

    .cmv-conversation-bar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        flex-wrap: wrap;
        padding: 12px 16px;
        border-radius: 10px;
        background: #f6f8fa;
        border: 1px solid #d0d7de;
        margin-bottom: 18px;
        font-size: 13px;
        color: #57606a;
    }

    .cmv-thread {
        border: 1px solid #d0d7de;
        border-radius: 10px;
        overflow: hidden;
        background: #ffffff;
    }

    .cmv-message-card {
        border-bottom: 1px solid #d0d7de;
    }

    .cmv-message-card:last-child {
        border-bottom: 0;
    }

    .cmv-message-summary {
        display: flex;
        align-items: flex-start;
        gap: 12px;
        padding: 14px 18px;
        cursor: pointer;
    }

    .cmv-message-card.expanded .cmv-message-summary {
        align-items: center;
    }

    .cmv-message-card.expanded .cmv-message-summary {
        cursor: default;
    }

    .cmv-message-card:not(.expanded) .cmv-message-summary:hover {
        background: #f6f8fa;
    }

    .cmv-avatar {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: #ffffff;
        font-weight: 700;
        font-size: 14px;
        flex-shrink: 0;
    }

    .cmv-summary-main {
        min-width: 0;
        flex: 1;
        display: flex;
        align-items: baseline;
        gap: 10px;
    }

    .cmv-summary-name {
        color: #0f172a;
        font-weight: 700;
        font-size: 13px;
        white-space: nowrap;
    }

    .cmv-summary-preview {
        color: #57606a;
        font-size: 13px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        min-width: 0;
    }

    .cmv-message-card.expanded .cmv-summary-preview {
        display: none;
    }

    .cmv-summary-date {
        color: #8c959f;
        font-size: 12px;
        flex-shrink: 0;
    }

    .cmv-message-body-wrap {
        display: none;
        padding: 0 18px 18px;
    }

    .cmv-message-card.expanded .cmv-message-body-wrap {
        display: block;
    }

    .cmv-message-card.expanded .cmv-message-summary {
        padding-bottom: 4px;
    }

    .cmv-sender-email {
        color: #57606a;
        font-size: 12px;
        font-weight: 400;
    }

    .cmv-sender-to {
        color: #8c959f;
        font-size: 12px;
        margin: 2px 0 12px 48px;
    }

    .cmv-star-btn {
        border: 0;
        background: transparent;
        color: #d0d7de;
        cursor: pointer;
        font-size: 15px;
        text-decoration: none;
        flex-shrink: 0;
    }

    .cmv-star-btn.active {
        color: #eab308;
    }

    .cmv-message-body {
        padding: 20px;
        border-radius: 8px;
        background: #ffffff;
        border: 1px solid #eaeef2;
        color: #24292f;
        line-height: 1.7;
        margin: 0 0 16px 48px;
    }

    .cmv-btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 8px 16px;
        border-radius: 999px;
        border: 1px solid #d0d7de;
        background: #ffffff;
        color: #24292f;
        font-size: 13px;
        font-weight: 600;
        text-decoration: none;
        cursor: pointer;
    }

    .cmv-btn:hover {
        background: #f6f8fa;
        text-decoration: none;
    }

    .cmv-btn.primary {
        background: #0969da;
        border-color: #0969da;
        color: #ffffff;
    }

    .cmv-btn.primary:hover {
        background: #0550ae;
        color: #ffffff;
    }

    .cmv-btn.success {
        background: #dafbe1;
        border-color: rgba(26, 127, 55, 0.25);
        color: #1a7f37;
    }

    .cmv-note-box {
        margin: 0 0 16px 48px;
        padding: 14px;
        border-radius: 8px;
        background: #f6f8fa;
        border: 1px dashed #d0d7de;
    }

    .cmv-note-box h3 {
        margin: 0 0 4px;
        font-size: 13px;
        color: #57606a;
    }

    .cmv-note-box p {
        margin: 0 0 10px;
        font-size: 12px;
        color: #8c959f;
    }

    .cmv-note-box textarea {
        width: 100%;
        padding: 8px 10px;
        border: 1px solid #d0d7de;
        border-radius: 6px;
        color: #24292f;
        font-family: inherit;
        font-size: 13px;
        line-height: 1.5;
        resize: vertical;
    }

    .cmv-reply-composer {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        flex-wrap: wrap;
        padding: 16px;
        border-radius: 10px;
        background: #ffffff;
        border: 1px solid #d0d7de;
        color: #57606a;
        font-size: 13px;
        margin-top: 18px;
    }

    @media(max-width: 640px) {
        .cmv-sender-to,
        .cmv-message-body,
        .cmv-note-box {
            margin-left: 0;
        }
    }
</style>

<div class="cmv-toolbar">
    <a class="cmv-icon-btn" href="contact-messages.php" title="Back to Inbox"><i class="fa fa-arrow-left"></i></a>
    <a class="cmv-icon-btn" href="<?= e($thread_url) ?>" title="Refresh"><i class="fa fa-refresh"></i></a>
    <a
        class="cmv-icon-btn danger"
        href="<?= e($thread_url) ?>&delete_all=1"
        onclick="return confirm('Delete this entire conversation (<?= e((string)count($messages)) ?> message<?= count($messages) > 1 ? 's' : '' ?>)?')"
        title="Delete conversation"
    ><i class="fa fa-trash-o"></i></a>
    <a class="cmv-icon-btn" href="<?= e($thread_url) ?>&mark_all=unread" title="Mark all unread"><i class="fa fa-envelope-o"></i></a>

    <span class="cmv-toolbar-spacer"></span>

    <span class="cmv-pill gray"><?= e((string)count($messages)) ?> message<?= count($messages) > 1 ? 's' : '' ?></span>
</div>

<?php show_flash(); ?>

<div class="cmv-subject-row">
    <h1>
        <?= e($latest_message['subject'] !== '' ? $latest_message['subject'] : '(no subject)') ?>
        <span class="cmv-pill gray" style="font-size:12px;">Inbox</span>
        <?php if ($customer): ?><span class="cmv-pill blue" style="font-size:12px;"><i class="fa fa-user"></i> Registered Customer</span><?php endif; ?>
    </h1>

    <div class="cmv-subject-icons">
        <a class="cmv-icon-btn" href="#" onclick="window.print(); return false;" title="Print"><i class="fa fa-print"></i></a>

        <details class="cmv-kebab">
            <summary title="More"><i class="fa fa-ellipsis-v"></i></summary>
            <div class="cmv-kebab-menu">
                <a href="<?= e($thread_url) ?>&mark_all=read"><i class="fa fa-envelope-open-o"></i> Mark all read</a>
                <?php if ($customer): ?>
                    <a href="user-view.php?id=<?= e((string)$customer['id']) ?>"><i class="fa fa-user"></i> View Customer</a>
                <?php endif; ?>
            </div>
        </details>
    </div>
</div>

<?php if ($customer): ?>
    <div class="cmv-banner">
        <div style="display:flex;align-items:center;">
            <div class="cmv-banner-icon"><i class="fa fa-user-circle"></i></div>
            <div class="cmv-banner-text">
                <strong>Customer account found</strong>
                <span>This sender email matches: <?= e($customer['name'] ?: $customer['email']) ?> <span class="cmv-pill gray" style="margin-left:6px;">Source: users</span></span>
            </div>
        </div>

        <a class="cmv-btn success" href="user-view.php?id=<?= e((string)$customer['id']) ?>">Go to Customer Details <i class="fa fa-arrow-right"></i></a>
    </div>
<?php endif; ?>

<div class="cmv-conversation-bar">
    <span>Conversation from: <strong><?= e($email) ?></strong></span>

    <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <a class="cmv-btn" href="<?= e($thread_url) ?>&mark_all=read"><i class="fa fa-envelope-open-o"></i> Mark all read</a>
        <a class="cmv-btn" href="<?= e($thread_url) ?>&mark_all=unread"><i class="fa fa-envelope"></i> Mark all unread</a>
    </div>
</div>

<div class="cmv-thread" id="cmv-thread">
    <?php foreach ($messages as $index => $item): ?>
        <?php
            $is_last = $index === count($messages) - 1;
            $item_unread = ($item['status'] ?? 'unread') === 'unread';
            $item_starred = !empty($item['starred']);
            $item_name = trim((string)$item['name']) !== '' ? trim((string)$item['name']) : $item['email'];
            $item_initial = mb_strtoupper(mb_substr($item_name, 0, 1));
            $item_reply_href = 'mailto:' . rawurlencode($item['email']) . '?subject=' . rawurlencode('Re: ' . ($item['subject'] !== '' ? $item['subject'] : '(no subject)'));
        ?>
        <div class="cmv-message-card <?= $is_last ? 'expanded' : '' ?>" data-msg-id="<?= e((string)$item['id']) ?>">
            <div class="cmv-message-summary" onclick="cmvToggleMessage(<?= e((string)$item['id']) ?>)">
                <div class="cmv-avatar" style="background:<?= e(cm_avatar_color($item['email'])) ?>;"><?= e($item_initial) ?></div>

                <div class="cmv-summary-main">
                    <span class="cmv-summary-name"><?= e($item_name) ?></span>
                    <span class="cmv-summary-preview"><?= e($item['subject'] !== '' ? $item['subject'] . ' - ' : '') ?><?= e(mb_strimwidth((string)($item['message'] ?? ''), 0, 90, '...')) ?></span>
                </div>

                <?php if ($item_unread): ?><span class="cmv-pill blue">Unread</span><?php endif; ?>

                <span class="cmv-summary-date"><?= e(date('M d, Y, h:i A', strtotime((string)($item['created_at'] ?? 'now')))) ?></span>

                <a class="cmv-star-btn <?= $item_starred ? 'active' : '' ?>" href="<?= e($thread_url) ?>&star=<?= e((string)$item['id']) ?>" onclick="event.stopPropagation();" title="<?= $item_starred ? 'Unstar' : 'Star' ?>">
                    <i class="fa <?= $item_starred ? 'fa-star' : 'fa-star-o' ?>"></i>
                </a>

                <a class="cmv-star-btn" href="<?= e($item_reply_href) ?>" onclick="event.stopPropagation();" title="Reply">
                    <i class="fa fa-reply"></i>
                </a>

                <details class="cmv-kebab" onclick="event.stopPropagation();">
                    <summary title="More"><i class="fa fa-ellipsis-v"></i></summary>
                    <div class="cmv-kebab-menu">
                        <?php if (!$item_unread): ?>
                            <a href="<?= e($thread_url) ?>&unread=<?= e((string)$item['id']) ?>"><i class="fa fa-envelope"></i> Mark Unread</a>
                        <?php endif; ?>
                        <a
                            href="<?= e($thread_url) ?>&delete=<?= e((string)$item['id']) ?>"
                            onclick="return confirm('Delete this message?')"
                        ><i class="fa fa-trash-o"></i> Delete</a>
                    </div>
                </details>
            </div>

            <div class="cmv-message-body-wrap">
                <div class="cmv-sender-email">&lt;<?= e($item['email']) ?>&gt;<?php if ($customer): ?> <span class="cmv-pill blue">Customer</span><?php endif; ?></div>
                <div class="cmv-sender-to">to me</div>

                <div class="cmv-message-body">
                    <?= nl2br(e($item['message'] ?? '')) ?>
                </div>

                <div class="cmv-note-box">
                    <h3>Internal Note</h3>
                    <p>Only visible to admin/moderators. Not sent to the sender.</p>

                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?= e($note_csrf_token) ?>">
                        <input type="hidden" name="action" value="save_note">
                        <input type="hidden" name="msg_id" value="<?= e((string)$item['id']) ?>">

                        <textarea name="notes" rows="3" placeholder="Write a follow-up note for this message..."><?= e($item['notes'] ?? '') ?></textarea>

                        <div style="margin-top:8px;">
                            <button type="submit" class="cmv-btn primary"><i class="fa fa-floppy-o"></i> Save Note</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="cmv-reply-composer">
    <span><i class="fa fa-reply"></i> Click Reply to respond to <?= e($display_name) ?></span>
    <div style="display:flex;gap:8px;">
        <a class="cmv-btn primary" href="<?= e($reply_href) ?>"><i class="fa fa-reply"></i> Reply</a>
        <a class="cmv-btn" href="<?= e($forward_href) ?>"><i class="fa fa-share"></i> Forward</a>
    </div>
</div>

<script>
    function cmvToggleMessage(id) {
        var thread = document.getElementById('cmv-thread');
        var card = thread.querySelector('[data-msg-id="' + id + '"]');

        if (!card) {
            return;
        }

        card.classList.toggle('expanded');
    }
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
