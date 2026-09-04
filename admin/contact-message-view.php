<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/contact-form-schema.php';

require_admin();

function cmv_review_statuses(): array
{
    return [
        'new'       => 'New',
        'under_review' => 'Under Review',
        'need_info' => 'Need More Information',
        'approved'  => 'Approved',
        'rejected'  => 'Rejected',
        'resolved'  => 'Resolved',
    ];
}

function cmv_review_status_color(string $status): string
{
    $colors = [
        'new'          => 'blue',
        'under_review' => 'gray',
        'need_info'    => 'gray',
        'approved'     => 'green',
        'rejected'     => 'gray',
        'resolved'     => 'green',
    ];

    return $colors[$status] ?? 'gray';
}

/*
 * Turns a stored form_data field key (e.g. "doctor_name") into the exact
 * label the visitor saw on the public form, by looking it up in the same
 * schema contact.php renders from. Falls back to a humanized version of the
 * key for anything the schema doesn't recognize (e.g. an older submission).
 */
function cmv_field_label(string $request_type, string $field_key): string
{
    $sections = cf_subject_sections();

    if (isset($sections[$request_type]['fields'][$field_key]['label'])) {
        return (string)$sections[$request_type]['fields'][$field_key]['label'];
    }

    return ucfirst(str_replace('_', ' ', $field_key));
}

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

/*
 * Preserve whatever message was explicitly open (if any) across these
 * action redirects. Without this, clicking star/read/unread/delete on any
 * message would land back on a bare thread URL, and -- combined with the
 * "don't default-expand anything" rule below -- silently close whatever
 * the admin already had open, or worse, pop open a message they never
 * asked to see. A stale id (e.g. the message just deleted) is harmless:
 * it simply won't match a real message later and nothing opens.
 */
$preserve_open = isset($_GET['open']) ? '&open=' . (int)$_GET['open'] : '';

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

        redirect((int)$remaining->fetchColumn() > 0 ? $thread_url . $preserve_open : 'contact-messages.php');
    }

    if (isset($_GET['unread'])) {
        $target_id = (int)$_GET['unread'];

        if ($target_id > 0) {
            $stmt = $pdo->prepare("UPDATE contacts SET status='unread' WHERE id=:id");
            $stmt->execute([':id' => $target_id]);
        }

        redirect($thread_url . $preserve_open);
    }

    if (isset($_GET['read'])) {
        $target_id = (int)$_GET['read'];

        if ($target_id > 0) {
            $stmt = $pdo->prepare("UPDATE contacts SET status='read' WHERE id=:id");
            $stmt->execute([':id' => $target_id]);
        }

        redirect($thread_url . $preserve_open);
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

        redirect($thread_url . $preserve_open);
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
        redirect($thread_url . $preserve_open);
    }

    if (isset($_GET['review_status'], $_GET['msg_id']) && column_exists('contacts', 'review_status')) {
        $target_id = (int)$_GET['msg_id'];
        $new_review_status = (string)$_GET['review_status'];

        if ($target_id > 0 && array_key_exists($new_review_status, cmv_review_statuses())) {
            $stmt = $pdo->prepare("UPDATE contacts SET review_status=:review_status WHERE id=:id");
            $stmt->execute([':review_status' => $new_review_status, ':id' => $target_id]);
        }

        redirect($thread_url . '&open=' . $target_id);
    }
}

if (empty($_SESSION['admin_contact_note_csrf'])) {
    $_SESSION['admin_contact_note_csrf'] = bin2hex(random_bytes(32));
}

$note_csrf_token = $_SESSION['admin_contact_note_csrf'];

if ($email !== '' && table_exists('contacts') && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_note') {
    $posted_token = (string)($_POST['csrf_token'] ?? '');
    $target_id = (int)($_POST['msg_id'] ?? 0);
    $is_ajax_save = ($_POST['ajax'] ?? '') === '1';

    if (hash_equals($note_csrf_token, $posted_token) && $target_id > 0) {
        $note_value = trim((string)($_POST['notes'] ?? ''));

        $stmt = $pdo->prepare("UPDATE contacts SET notes=:notes WHERE id=:id");
        $stmt->execute([':notes' => $note_value !== '' ? $note_value : null, ':id' => $target_id]);

        if ($is_ajax_save) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'notes' => $note_value]);
            exit;
        }

        flash('success', 'Note saved successfully.');
    } else {
        if ($is_ajax_save) {
            header('Content-Type: application/json');
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Security token mismatch.']);
            exit;
        }

        flash('error', 'Security token mismatch. Please try again.');
    }

    redirect($thread_url . $preserve_open);
}

$messages = [];

if ($email !== '' && table_exists('contacts')) {
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

/*
 * The list page links here with &open=<message id> so the specific row a
 * user clicked opens expanded. When no valid &open= is present (e.g. a
 * redirect back from star/read/unread/delete), nothing is force-expanded
 * -- previously this defaulted to the latest message, which meant acting
 * on any message could pop open one the admin never asked to see (or,
 * combined with the read-marking below, silently re-read it).
 */
$explicitly_opened_id = isset($_GET['open']) ? (int)$_GET['open'] : 0;
$open_message_ids = array_map('intval', array_column($messages, 'id'));

if (!in_array($explicitly_opened_id, $open_message_ids, true)) {
    $explicitly_opened_id = 0;
}

$open_message_id = $explicitly_opened_id;

/*
 * Only a message the user genuinely navigated to (a real &open=<id> from
 * the list page) gets marked read here -- never the id this page merely
 * defaults to displaying. Without that distinction, every action here
 * (star, delete, "Mark Unread", ...) redirects back to a plain thread URL
 * with no &open=, which would then default-open and instantly re-read the
 * latest message, silently undoing a "Mark Unread" click whenever it
 * happened to be the latest one.
 */
if ($explicitly_opened_id > 0) {
    $mark_read = $pdo->prepare("UPDATE contacts SET status='read' WHERE id = :id AND status='unread'");
    $mark_read->execute([':id' => $explicitly_opened_id]);
}

if ($explicitly_opened_id > 0) {
    foreach ($messages as &$message_row) {
        if ((int)$message_row['id'] === $explicitly_opened_id) {
            $message_row['status'] = 'read';
        }
    }
    unset($message_row);
}

$latest_message = $messages[count($messages) - 1];
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

    .cmv-item-subject {
        display: none;
        margin: 0;
        padding: 14px 18px 0;
        color: #0f172a;
        font-size: 18px;
        font-weight: 700;
    }

    .cmv-message-card.expanded .cmv-item-subject {
        display: block;
    }

    .cmv-summary-email {
        display: none;
        color: #57606a;
        font-size: 12px;
        font-weight: 400;
        white-space: nowrap;
    }

    .cmv-message-card.expanded .cmv-summary-email {
        display: inline;
    }

    .cmv-header-toggle {
        margin: 2px 0 12px;
        position: relative;
    }

    .cmv-sender-to {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        color: #8c959f;
        font-size: 12px;
        cursor: pointer;
        list-style: none;
        user-select: none;
    }

    .cmv-sender-to::-webkit-details-marker {
        display: none;
    }

    .cmv-header-toggle:hover .cmv-sender-to {
        color: #57606a;
        text-decoration: underline;
    }

    .cmv-header-panel {
        position: absolute;
        z-index: 15;
        margin-top: 8px;
        padding: 16px 20px;
        background: #ffffff;
        border: 1px solid #dadce0;
        border-radius: 8px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1), 0 4px 16px rgba(27, 31, 36, 0.14);
    }

    .cmv-header-panel table {
        border-collapse: collapse;
    }

    .cmv-header-panel td {
        padding: 4px 10px;
        font-size: 13px;
        line-height: 1.5;
        color: #202124;
        white-space: nowrap;
        vertical-align: top;
    }

    .cmv-header-panel td:first-child {
        color: #5f6368;
        text-align: right;
        white-space: nowrap;
    }

    .cmv-header-panel td:last-child {
        white-space: normal;
        min-width: 220px;
    }

    .cmv-message-actions {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        margin: 0 0 16px;
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
        margin: 0 0 16px;
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
        margin: 0 0 16px;
        padding: 14px;
        border-radius: 8px;
        background: #f6f8fa;
        border: 1px dashed #d0d7de;
    }

    .cmv-note-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 10px;
    }

    .cmv-note-box h3 {
        margin: 0;
        font-size: 13px;
        color: #57606a;
    }

    .cmv-note-view {
        padding: 10px 12px;
        margin-bottom: 4px;
        border-radius: 6px;
        background: #ffffff;
        border: 1px solid #d0d7de;
        color: #24292f;
        font-size: 13px;
        line-height: 1.6;
    }

    .cmv-note-status {
        font-size: 12px;
        color: #8c959f;
    }

    .cmv-note-status.saving {
        color: #9a6700;
    }

    .cmv-note-status.saved {
        color: #1a7f37;
    }

    .cmv-note-status.error {
        color: #cf222e;
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

    .cmv-request-panel {
        margin: 0 0 16px;
        padding: 14px 16px;
        border-radius: 8px;
        background: #f6f8fa;
        border: 1px solid #d0d7de;
    }

    .cmv-request-panel-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 10px;
        margin-bottom: 10px;
        font-size: 13px;
        font-weight: 700;
        color: #24292f;
    }

    .cmv-review-status {
        display: flex;
        align-items: center;
        gap: 8px;
        font-weight: 400;
    }

    .cmv-review-status label {
        font-size: 12px;
        color: #57606a;
    }

    .cmv-review-status select {
        border: 0;
        cursor: pointer;
        font-size: 12px;
        font-weight: 700;
        padding: 4px 10px;
    }

    .cmv-details-table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 10px;
        background: #ffffff;
        border: 1px solid #eaeef2;
        border-radius: 6px;
        overflow: hidden;
    }

    .cmv-details-table tr:not(:last-child) td {
        border-bottom: 1px solid #eaeef2;
    }

    .cmv-details-table td {
        padding: 7px 12px;
        font-size: 13px;
        vertical-align: top;
    }

    .cmv-details-label {
        width: 220px;
        color: #57606a;
        font-weight: 600;
        white-space: nowrap;
    }

    .cmv-details-value {
        color: #24292f;
        word-break: break-word;
    }

    .cmv-attachments {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
    }

    .cmv-attachment-item {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 6px;
        width: 110px;
        padding: 8px;
        border-radius: 8px;
        background: #ffffff;
        border: 1px solid #d0d7de;
        text-decoration: none;
        color: #24292f;
        font-size: 11px;
        text-align: center;
    }

    .cmv-attachment-item:hover {
        border-color: #0969da;
        text-decoration: none;
    }

    .cmv-attachment-item img {
        width: 100%;
        height: 70px;
        object-fit: cover;
        border-radius: 4px;
        background: #f6f8fa;
    }

    .cmv-attachment-file-icon {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 100%;
        height: 70px;
        border-radius: 4px;
        background: #f6f8fa;
        color: #cf222e;
        font-size: 26px;
    }

    .cmv-attachment-label {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        width: 100%;
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
            $is_open = (int)$item['id'] === $open_message_id;
            $item_unread = ($item['status'] ?? 'unread') === 'unread';
            $item_starred = !empty($item['starred']);
            $item_name = trim((string)$item['name']) !== '' ? trim((string)$item['name']) : $item['email'];
            $item_initial = mb_strtoupper(mb_substr($item_name, 0, 1));
            $item_subject_display = $item['subject'] !== '' ? $item['subject'] : '(no subject)';
            $item_reply_href = 'mailto:' . rawurlencode($item['email']) . '?subject=' . rawurlencode('Re: ' . $item_subject_display);
            $item_forward_body = "\n\n---------- Forwarded message ----------\n"
                . "From: " . $item_name . " <" . $item['email'] . ">\n"
                . "Date: " . date('M d, Y, h:i A', strtotime((string)($item['created_at'] ?? 'now'))) . "\n"
                . "Subject: " . $item_subject_display . "\n\n"
                . (string)($item['message'] ?? '');
            $item_forward_href = 'mailto:?subject=' . rawurlencode('Fwd: ' . $item_subject_display) . '&body=' . rawurlencode($item_forward_body);
        ?>
        <div class="cmv-message-card <?= $is_open ? 'expanded' : '' ?>" data-msg-id="<?= e((string)$item['id']) ?>" data-unread="<?= $item_unread ? '1' : '0' ?>">
            <h2 class="cmv-item-subject"><?= e($item_subject_display) ?></h2>

            <div class="cmv-message-summary" onclick="cmvToggleMessage(<?= e((string)$item['id']) ?>)">
                <div class="cmv-avatar" style="background:<?= e(cm_avatar_color($item['email'])) ?>;"><?= e($item_initial) ?></div>

                <div class="cmv-summary-main">
                    <span class="cmv-summary-name"><?= e($item_name) ?></span>
                    <span class="cmv-summary-email"><?php if ($customer): ?><span class="cmv-pill blue" style="margin-right:4px;">Customer</span><?php endif; ?>&lt;<?= e($item['email']) ?>&gt;</span>
                    <span class="cmv-summary-preview"><?= e($item['subject'] !== '' ? $item['subject'] . ' - ' : '') ?><?= e(mb_strimwidth((string)($item['message'] ?? ''), 0, 90, '...')) ?></span>
                </div>

                <span class="cmv-pill blue cmv-unread-pill" id="cmv-unread-pill-<?= e((string)$item['id']) ?>" style="<?= $item_unread ? '' : 'display:none;' ?>">Unread</span>

                <span class="cmv-summary-date"><?= e(date('M d, Y, h:i A', strtotime((string)($item['created_at'] ?? 'now')))) ?></span>

                <a class="cmv-star-btn <?= $item_starred ? 'active' : '' ?>" href="<?= e($thread_url) ?>&star=<?= e((string)$item['id']) ?>" onclick="event.stopPropagation();" title="<?= $item_starred ? 'Unstar' : 'Star' ?>">
                    <i class="fa <?= $item_starred ? 'fa-star' : 'fa-star-o' ?>"></i>
                </a>

                <a class="cmv-star-btn" href="<?= e($thread_url) ?>&open=<?= e((string)$item['id']) ?>" onclick="event.stopPropagation();" title="View">
                    <i class="fa fa-eye"></i>
                </a>

                <a
                    class="cmv-star-btn cmv-read-toggle-btn"
                    id="cmv-read-toggle-btn-<?= e((string)$item['id']) ?>"
                    href="<?= e($thread_url) ?>&<?= $item_unread ? 'read' : 'unread' ?>=<?= e((string)$item['id']) ?>"
                    onclick="event.stopPropagation();"
                    title="<?= $item_unread ? 'Mark Read' : 'Mark Unread' ?>"
                    data-read-href="<?= e($thread_url) ?>&read=<?= e((string)$item['id']) ?>"
                    data-unread-href="<?= e($thread_url) ?>&unread=<?= e((string)$item['id']) ?>"
                >
                    <i class="fa <?= $item_unread ? 'fa-envelope-open-o' : 'fa-envelope' ?>"></i>
                </a>

                <a class="cmv-star-btn" href="<?= e($item_reply_href) ?>" onclick="event.stopPropagation();" title="Reply">
                    <i class="fa fa-reply"></i>
                </a>

                <a
                    class="cmv-star-btn"
                    href="<?= e($thread_url) ?>&delete=<?= e((string)$item['id']) ?>"
                    onclick="event.stopPropagation(); return confirm('Delete this message?');"
                    title="Delete"
                >
                    <i class="fa fa-trash-o"></i>
                </a>
            </div>

            <div class="cmv-message-body-wrap">
                <details class="cmv-header-toggle" name="cmv-header-group">
                    <summary class="cmv-sender-to">to me <i class="fa fa-caret-down"></i></summary>
                    <div class="cmv-header-panel">
                        <table>
                            <tr><td>from:</td><td><strong><?= e($item_name) ?></strong> &lt;<?= e($item['email']) ?>&gt;</td></tr>
                            <tr><td>reply-to:</td><td><?= e($item['email']) ?></td></tr>
                            <tr><td>to:</td><td><?= e(defined('APP_NAME') ? APP_NAME : 'Admin') ?></td></tr>
                            <tr><td>date:</td><td><?= e(date('M d, Y, h:i A', strtotime((string)($item['created_at'] ?? 'now')))) ?></td></tr>
                            <tr><td>subject:</td><td><?= e($item_subject_display) ?></td></tr>
                            <tr><td>message id:</td><td>#<?= e((string)$item['id']) ?></td></tr>
                            <tr><td>status:</td><td><?= $item_unread ? 'Unread' : 'Read' ?><?= $item_starred ? ' · Starred' : '' ?></td></tr>
                            <?php if ($customer): ?>
                                <tr><td>customer:</td><td><i class="fa fa-check-circle" style="color:#1a7f37;"></i> Registered account match</td></tr>
                            <?php endif; ?>
                        </table>
                    </div>
                </details>

                <div class="cmv-message-body">
                    <?= nl2br(e($item['message'] ?? '')) ?>
                </div>

                <?php
                    $item_request_type = trim((string)($item['request_type'] ?? ''));
                    $item_form_data = [];
                    $item_attachments = [];

                    if ($item_request_type !== '') {
                        $decoded_form_data = json_decode((string)($item['form_data'] ?? ''), true);
                        $item_form_data = is_array($decoded_form_data) ? $decoded_form_data : [];

                        $decoded_attachments = json_decode((string)($item['attachments'] ?? ''), true);
                        $item_attachments = is_array($decoded_attachments) ? $decoded_attachments : [];
                    }

                    $item_review_status = (string)($item['review_status'] ?? 'new');
                ?>

                <?php if ($item_request_type !== ''): ?>
                    <div class="cmv-request-panel">
                        <div class="cmv-request-panel-head">
                            <span><i class="fa fa-list-alt"></i> Request Details</span>

                            <div class="cmv-review-status">
                                <label for="cmv-review-status-<?= e((string)$item['id']) ?>">Review Status:</label>
                                <select
                                    id="cmv-review-status-<?= e((string)$item['id']) ?>"
                                    class="cmv-pill <?= e(cmv_review_status_color($item_review_status)) ?>"
                                    onchange='window.location.href = <?= e(json_encode($thread_url . "&open=" . $item['id'] . "&msg_id=" . $item['id'] . "&review_status=", JSON_HEX_APOS)) ?> + this.value;'
                                >
                                    <?php foreach (cmv_review_statuses() as $rs_key => $rs_label): ?>
                                        <option value="<?= e($rs_key) ?>" <?= $item_review_status === $rs_key ? 'selected' : '' ?>><?= e($rs_label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <?php if ($item_form_data): ?>
                            <table class="cmv-details-table">
                                <?php foreach ($item_form_data as $field_key => $field_value): ?>
                                    <?php
                                        $display_value = is_array($field_value) ? implode(', ', $field_value) : (string)$field_value;

                                        if (trim($display_value) === '') {
                                            continue;
                                        }
                                    ?>
                                    <tr>
                                        <td class="cmv-details-label"><?= e(cmv_field_label($item_request_type, (string)$field_key)) ?></td>
                                        <td class="cmv-details-value"><?= nl2br(e($display_value)) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </table>
                        <?php endif; ?>

                        <?php if ($item_attachments): ?>
                            <div class="cmv-attachments">
                                <?php foreach ($item_attachments as $att_key => $att): ?>
                                    <?php
                                        $att_path = (string)($att['path'] ?? '');

                                        if ($att_path === '') {
                                            continue;
                                        }

                                        $att_url = site_url($att_path);
                                        $att_mime = (string)($att['mime'] ?? '');
                                        $att_is_image = strpos($att_mime, 'image/') === 0;
                                        $att_label = (string)($att['field'] ?? $att_key);
                                    ?>
                                    <a class="cmv-attachment-item" href="<?= e($att_url) ?>" target="_blank" rel="noopener">
                                        <?php if ($att_is_image): ?>
                                            <img src="<?= e($att_url) ?>" alt="<?= e($att_label) ?>">
                                        <?php else: ?>
                                            <span class="cmv-attachment-file-icon"><i class="fa fa-file-pdf-o"></i></span>
                                        <?php endif; ?>
                                        <span class="cmv-attachment-label"><?= e($att_label) ?></span>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <div class="cmv-message-actions">
                    <a class="cmv-btn primary" href="<?= e($item_reply_href) ?>"><i class="fa fa-reply"></i> Reply</a>
                    <a class="cmv-btn" href="<?= e($item_forward_href) ?>"><i class="fa fa-share"></i> Forward</a>
                </div>

                <?php
                    $item_note = trim((string)($item['notes'] ?? ''));
                    $item_has_note = $item_note !== '';
                ?>
                <div class="cmv-note-box" data-has-note="<?= $item_has_note ? '1' : '0' ?>">
                    <div class="cmv-note-header">
                        <div>
                            <h3>Note</h3>
                        </div>

                        <button
                            type="button"
                            class="cmv-btn"
                            id="cmv-note-toggle-btn-<?= e((string)$item['id']) ?>"
                            onclick="cmvNoteEdit(<?= e((string)$item['id']) ?>)"
                        >
                            <?php if ($item_has_note): ?>
                                <i class="fa fa-pencil"></i> Edit
                            <?php else: ?>
                                <i class="fa fa-plus"></i> Add Note
                            <?php endif; ?>
                        </button>
                    </div>

                    <?php if ($item_has_note): ?>
                        <div class="cmv-note-view" id="cmv-note-view-<?= e((string)$item['id']) ?>">
                            <?= nl2br(e($item_note)) ?>
                        </div>
                    <?php endif; ?>

                    <form method="post" class="cmv-note-form" id="cmv-note-form-<?= e((string)$item['id']) ?>" style="display:none;">
                        <input type="hidden" name="csrf_token" value="<?= e($note_csrf_token) ?>">
                        <input type="hidden" name="action" value="save_note">
                        <input type="hidden" name="msg_id" value="<?= e((string)$item['id']) ?>">

                        <textarea name="notes" rows="3" placeholder="Write a follow-up note for this message..."><?= e($item['notes'] ?? '') ?></textarea>

                        <div style="margin-top:8px;display:flex;align-items:center;gap:8px;">
                            <button type="submit" class="cmv-btn primary"><i class="fa fa-floppy-o"></i> Save</button>

                            <button
                                type="button"
                                class="cmv-btn"
                                id="cmv-note-delete-btn-<?= e((string)$item['id']) ?>"
                                style="<?= $item_has_note ? '' : 'display:none;' ?>"
                                onclick="cmvNoteDelete(<?= e((string)$item['id']) ?>)"
                            ><i class="fa fa-trash-o"></i> Delete</button>

                            <button type="button" class="cmv-btn" onclick="cmvNoteCancel(<?= e((string)$item['id']) ?>)">Cancel</button>
                            <span class="cmv-note-status" id="cmv-note-status-<?= e((string)$item['id']) ?>"></span>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<script>
    function cmvToggleMessage(id) {
        var thread = document.getElementById('cmv-thread');
        var card = thread.querySelector('[data-msg-id="' + id + '"]');

        if (!card) {
            return;
        }

        var wasExpanded = card.classList.contains('expanded');
        card.classList.toggle('expanded');

        /*
         * Expanding a message (whether by clicking its row or the eye
         * icon) marks it read, matching how opening a message in any real
         * inbox behaves -- not just the explicit "Mark Read" action.
         */
        if (!wasExpanded && card.getAttribute('data-unread') === '1') {
            cmvMarkMessageRead(id, card);
        }
    }

    function cmvMarkMessageRead(id, card) {
        var toggleBtn = document.getElementById('cmv-read-toggle-btn-' + id);

        fetch(toggleBtn ? toggleBtn.dataset.readHref : (window.location.pathname + window.location.search), {
            method: 'GET',
            credentials: 'same-origin'
        }).then(function () {
            card.setAttribute('data-unread', '0');

            var pill = document.getElementById('cmv-unread-pill-' + id);
            if (pill) pill.style.display = 'none';

            if (toggleBtn) {
                toggleBtn.href = toggleBtn.dataset.unreadHref;
                toggleBtn.title = 'Mark Unread';
                var icon = toggleBtn.querySelector('i');
                if (icon) icon.className = 'fa fa-envelope';
            }
        }).catch(function () {
            // Leave the UI as-is; the next full page load will reconcile it.
        });
    }

    function cmvNoteEdit(id) {
        var view = document.getElementById('cmv-note-view-' + id);
        var form = document.getElementById('cmv-note-form-' + id);
        var toggleBtn = document.getElementById('cmv-note-toggle-btn-' + id);

        if (view) view.style.display = 'none';
        if (form) form.style.display = 'block';
        if (toggleBtn) toggleBtn.style.display = 'none';
    }

    function cmvNoteCancel(id) {
        var view = document.getElementById('cmv-note-view-' + id);
        var form = document.getElementById('cmv-note-form-' + id);
        var toggleBtn = document.getElementById('cmv-note-toggle-btn-' + id);

        if (form) {
            var textarea = form.querySelector('textarea');
            if (textarea) {
                textarea.value = textarea.defaultValue;
            }

            form.style.display = 'none';
        }

        if (view) view.style.display = 'block';
        if (toggleBtn) toggleBtn.style.display = '';
    }

    function cmvNoteDelete(id) {
        if (!confirm('Delete this note?')) {
            return;
        }

        var form = document.getElementById('cmv-note-form-' + id);
        if (!form) return;

        var textarea = form.querySelector('textarea');
        if (textarea) {
            textarea.value = '';
        }

        if (cmvNoteTimers[id]) {
            window.clearTimeout(cmvNoteTimers[id]);
        }

        cmvNoteSave(id);
    }

    var cmvNoteTimers = {};

    function cmvEscapeHtml(str) {
        var div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    function cmvNoteSetStatus(id, state, text) {
        var status = document.getElementById('cmv-note-status-' + id);
        if (!status) return;
        status.className = 'cmv-note-status' + (state ? ' ' + state : '');
        status.textContent = text || '';
    }

    function cmvNoteSave(id) {
        var form = document.getElementById('cmv-note-form-' + id);
        if (!form) return;

        var textarea = form.querySelector('textarea');
        var formData = new FormData(form);
        formData.set('ajax', '1');

        cmvNoteSetStatus(id, 'saving', 'Saving...');

        fetch(window.location.href, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        })
            .then(function (response) { return response.json(); })
            .then(function (data) {
                if (!data || !data.success) {
                    throw new Error((data && data.message) || 'Save failed');
                }

                var noteText = (data.notes || '').trim();

                if (textarea) {
                    textarea.defaultValue = textarea.value;
                }

                var box = form.closest('.cmv-note-box');

                if (box) {
                    box.setAttribute('data-has-note', noteText !== '' ? '1' : '0');

                    var toggleBtn = document.getElementById('cmv-note-toggle-btn-' + id);
                    if (toggleBtn) {
                        toggleBtn.innerHTML = noteText !== ''
                            ? '<i class="fa fa-pencil"></i> Edit'
                            : '<i class="fa fa-plus"></i> Add Note';
                    }

                    var deleteBtn = document.getElementById('cmv-note-delete-btn-' + id);
                    if (deleteBtn) {
                        deleteBtn.style.display = noteText !== '' ? '' : 'none';
                    }

                    var view = document.getElementById('cmv-note-view-' + id);

                    if (!view && noteText !== '') {
                        view = document.createElement('div');
                        view.className = 'cmv-note-view';
                        view.id = 'cmv-note-view-' + id;
                        view.style.display = 'none';
                        form.parentNode.insertBefore(view, form);
                    }

                    if (view) {
                        view.innerHTML = cmvEscapeHtml(noteText).replace(/\n/g, '<br>');
                    }
                }

                cmvNoteSetStatus(id, 'saved', 'Saved');

                window.setTimeout(function () {
                    cmvNoteSetStatus(id, '', '');
                }, 2000);
            })
            .catch(function () {
                cmvNoteSetStatus(id, 'error', 'Could not save. Try again.');
            });
    }

    document.querySelectorAll('.cmv-note-form').forEach(function (form) {
        var id = form.id.replace('cmv-note-form-', '');
        var textarea = form.querySelector('textarea');

        if (textarea) {
            textarea.addEventListener('input', function () {
                cmvNoteSetStatus(id, 'saving', 'Typing...');

                if (cmvNoteTimers[id]) {
                    window.clearTimeout(cmvNoteTimers[id]);
                }

                cmvNoteTimers[id] = window.setTimeout(function () {
                    cmvNoteSave(id);
                }, 1500);
            });
        }

        form.addEventListener('submit', function (event) {
            event.preventDefault();

            if (cmvNoteTimers[id]) {
                window.clearTimeout(cmvNoteTimers[id]);
            }

            cmvNoteSave(id);
        });
    });

    document.addEventListener('DOMContentLoaded', function () {
        var opened = document.querySelector('.cmv-message-card.expanded[data-msg-id="<?= e((string)$open_message_id) ?>"]');

        if (opened) {
            opened.scrollIntoView({ block: 'center' });
        }
    });

    document.addEventListener('click', function (event) {
        document.querySelectorAll('.cmv-header-toggle[open]').forEach(function (details) {
            if (!details.contains(event.target)) {
                details.removeAttribute('open');
            }
        });
    });
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
