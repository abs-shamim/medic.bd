<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/contact-form-schema.php';

require_admin();

function ensure_contacts_table(): void
{
    global $pdo;

    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `contacts` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `name` VARCHAR(190) NOT NULL,
                `email` VARCHAR(190) NOT NULL,
                `subject` VARCHAR(190) NULL,
                `message` TEXT NULL,
                `status` ENUM('unread','read') NOT NULL DEFAULT 'unread',
                `created_at` DATETIME NULL,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        if (!column_exists('contacts', 'status')) {
            $pdo->exec("ALTER TABLE `contacts` ADD COLUMN `status` ENUM('unread','read') NOT NULL DEFAULT 'unread' AFTER `message`");
        }

        if (!column_exists('contacts', 'notes')) {
            $pdo->exec("ALTER TABLE `contacts` ADD COLUMN `notes` TEXT NULL AFTER `status`");
        }

        if (!column_exists('contacts', 'starred')) {
            $pdo->exec("ALTER TABLE `contacts` ADD COLUMN `starred` TINYINT(1) NOT NULL DEFAULT 0 AFTER `notes`");
        }

        if (!column_exists('contacts', 'category')) {
            $pdo->exec("ALTER TABLE `contacts` ADD COLUMN `category` VARCHAR(20) NOT NULL DEFAULT 'primary' AFTER `starred`");
        }

        if (!column_exists('contacts', 'request_type')) {
            $pdo->exec("ALTER TABLE `contacts` ADD COLUMN `request_type` VARCHAR(60) NOT NULL DEFAULT '' AFTER `subject`");
        }
    } catch (Throwable $e) {
        // If the hosting database user has no CREATE/ALTER permission,
        // the page still loads below and simply shows no messages.
    }
}

/*
 * Inbox tabs mirror the public contact form's Subject dropdown exactly
 * (see includes/contact-form-schema.php), since every new submission is
 * auto-tagged with its request_type at save time -- no manual "move to
 * category" triage is needed the way the old free-text subject required.
 * "Primary" stays as the All view for messages sent before this dropdown
 * existed (request_type is blank on those).
 */
if (!function_exists('cm_category_list')) {
    function cm_category_list(): array
    {
        $icons = [
            'add_doctor'        => 'fa-user-plus',
            'update_doctor'     => 'fa-user-md',
            'add_hospital'      => 'fa-hospital-o',
            'update_hospital'   => 'fa-edit',
            'claim_doctor'      => 'fa-id-badge',
            'claim_hospital'    => 'fa-building',
            'report_incorrect'  => 'fa-exclamation-triangle',
            'technical_support' => 'fa-wrench',
            'other'             => 'fa-question-circle',
        ];

        $list = ['primary' => ['label' => 'Primary', 'icon' => 'fa-inbox']];

        foreach (cf_subject_labels() as $type_key => $type_label) {
            $list[$type_key] = ['label' => $type_label, 'icon' => $icons[$type_key] ?? 'fa-envelope-o'];
        }

        return $list;
    }
}

ensure_contacts_table();

if (empty($_SESSION['admin_contact_bulk_csrf'])) {
    $_SESSION['admin_contact_bulk_csrf'] = bin2hex(random_bytes(32));
}

$bulk_csrf_token = $_SESSION['admin_contact_bulk_csrf'];

/*
|--------------------------------------------------------------------------
| Handle Actions Before Any HTML Output
|--------------------------------------------------------------------------
| includes/header.php prints HTML immediately, so any redirect() must run
| before it is required. Otherwise PHP throws a "headers already sent"
| warning and the redirect silently fails.
|--------------------------------------------------------------------------
*/
if (table_exists('contacts') && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'])) {
    if (function_exists('require_admin_permission')) {
        require_admin_permission('contacts.manage');
    }

    $redirect_url = 'contact-messages.php' . ($_SERVER['QUERY_STRING'] !== '' ? '?' . $_SERVER['QUERY_STRING'] : '');
    $posted_token = (string)($_POST['csrf_token'] ?? '');

    if (!hash_equals($bulk_csrf_token, $posted_token)) {
        flash('error', 'Security token mismatch. Please try again.');
        redirect($redirect_url);
    }

    $ids = array_values(array_unique(array_filter(
        array_map('intval', (array)($_POST['ids'] ?? [])),
        static function ($value) {
            return $value > 0;
        }
    )));

    if (!$ids) {
        flash('error', 'No messages were selected.');
        redirect($redirect_url);
    }

    $bulk_action = (string)$_POST['bulk_action'];
    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    if ($bulk_action === 'mark_read') {
        $stmt = $pdo->prepare("UPDATE contacts SET status='read' WHERE id IN ({$placeholders})");
        $stmt->execute($ids);
        flash('success', count($ids) . ' message(s) marked as read.');
    } elseif ($bulk_action === 'mark_unread') {
        $stmt = $pdo->prepare("UPDATE contacts SET status='unread' WHERE id IN ({$placeholders})");
        $stmt->execute($ids);
        flash('success', count($ids) . ' message(s) marked as unread.');
    } elseif ($bulk_action === 'delete') {
        $stmt = $pdo->prepare("DELETE FROM contacts WHERE id IN ({$placeholders})");
        $stmt->execute($ids);
        flash('success', count($ids) . ' message(s) deleted.');
    } elseif (strpos($bulk_action, 'move_') === 0) {
        $target_category = substr($bulk_action, strlen('move_'));
        $category_list = cm_category_list();

        if (array_key_exists($target_category, $category_list)) {
            $new_request_type = $target_category === 'primary' ? '' : $target_category;
            $stmt = $pdo->prepare("UPDATE contacts SET request_type=? WHERE id IN ({$placeholders})");
            $stmt->execute(array_merge([$new_request_type], $ids));
            flash('success', count($ids) . ' message(s) moved to ' . $category_list[$target_category]['label'] . '.');
        } else {
            flash('error', 'Invalid bulk action.');
        }
    } else {
        flash('error', 'Invalid bulk action.');
    }

    redirect($redirect_url);
}

if (table_exists('contacts') && isset($_GET['delete'])) {
    if (function_exists('require_admin_permission')) {
        require_admin_permission('contacts.manage');
    }

    $id = (int)$_GET['delete'];

    if ($id > 0) {
        $stmt = $pdo->prepare("DELETE FROM contacts WHERE id=:id");
        $stmt->execute([':id' => $id]);

        flash('success', 'Message deleted successfully.');
    }

    redirect('contact-messages.php');
}

if (table_exists('contacts') && isset($_GET['unread'])) {
    if (function_exists('require_admin_permission')) {
        require_admin_permission('contacts.manage');
    }

    $id = (int)$_GET['unread'];

    if ($id > 0) {
        $stmt = $pdo->prepare("UPDATE contacts SET status='unread' WHERE id=:id");
        $stmt->execute([':id' => $id]);

        flash('success', 'Message marked as unread.');
    }

    redirect('contact-messages.php');
}

if (table_exists('contacts') && isset($_GET['star'])) {
    if (function_exists('require_admin_permission')) {
        require_admin_permission('contacts.manage');
    }

    $id = (int)$_GET['star'];

    if ($id > 0) {
        $stmt = $pdo->prepare("SELECT starred FROM contacts WHERE id=:id");
        $stmt->execute([':id' => $id]);
        $current = (int)$stmt->fetchColumn();

        $update = $pdo->prepare("UPDATE contacts SET starred=:starred WHERE id=:id");
        $update->execute([':starred' => $current ? 0 : 1, ':id' => $id]);
    }

    $redirect_url = 'contact-messages.php' . ($_SERVER['QUERY_STRING'] !== '' ? '?' . preg_replace('/(^|&)star=\d+&?/', '$1', $_SERVER['QUERY_STRING']) : '');
    redirect(rtrim($redirect_url, '?&'));
}

require_once __DIR__ . '/includes/header.php';

if (function_exists('require_admin_permission')) {
    require_admin_permission('contacts.view');
}

if (!table_exists('contacts')) {
    echo '<h1 style="margin-bottom:18px;color:#0f172a;">Contact Messages</h1>';
    echo '<div class="card"><p>The contacts table was not found and could not be created automatically.</p></div>';
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
| Filters
|--------------------------------------------------------------------------
*/
$search = trim((string)($_GET['search'] ?? ''));
$status_filter = trim((string)($_GET['status'] ?? ''));
$date_from = trim((string)($_GET['date_from'] ?? ''));
$date_to = trim((string)($_GET['date_to'] ?? ''));
$category_filter = trim((string)($_GET['category'] ?? 'primary'));

if (!array_key_exists($category_filter, cm_category_list())) {
    $category_filter = 'primary';
}

$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 20;

function admin_contact_messages_current_query(array $extra = []): string
{
    $query = [
        'search' => trim((string)($_GET['search'] ?? '')),
        'status' => trim((string)($_GET['status'] ?? '')),
        'date_from' => trim((string)($_GET['date_from'] ?? '')),
        'date_to' => trim((string)($_GET['date_to'] ?? '')),
        'category' => trim((string)($_GET['category'] ?? '')),
        'page' => max(1, (int)($_GET['page'] ?? 1)),
    ];

    foreach ($extra as $key => $value) {
        if ($value === null) {
            unset($query[$key]);
        } else {
            $query[$key] = $value;
        }
    }

    $query = array_filter($query, static function ($value) {
        return $value !== '' && $value !== null;
    });

    return http_build_query($query);
}

$where = [];
$params = [];

/*
 * The "Primary" tab acts as an "All" view showing every message regardless
 * of request_type -- this is where older messages sent before the Subject
 * dropdown existed still show up, since their request_type is blank. The
 * other tabs filter strictly to their own request type.
 */
if ($category_filter !== 'primary') {
    $where[] = "request_type = :category";
    $params[':category'] = $category_filter;
}

if ($search !== '') {
    $where[] = "(name LIKE :search1 OR email LIKE :search2 OR subject LIKE :search3 OR message LIKE :search4)";
    $params[':search1'] = '%' . $search . '%';
    $params[':search2'] = '%' . $search . '%';
    $params[':search3'] = '%' . $search . '%';
    $params[':search4'] = '%' . $search . '%';
}

if (in_array($status_filter, ['unread', 'read'], true)) {
    $where[] = "status = :status";
    $params[':status'] = $status_filter;
} elseif ($status_filter === 'starred') {
    $where[] = "starred = 1";
}

if ($date_from !== '') {
    $where[] = "created_at >= :date_from";
    $params[':date_from'] = $date_from . ' 00:00:00';
}

if ($date_to !== '') {
    $where[] = "created_at <= :date_to";
    $params[':date_to'] = $date_to . ' 23:59:59';
}

$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$count_stmt = $pdo->prepare("SELECT COUNT(*) FROM contacts {$where_sql}");
$count_stmt->execute($params);
$total_messages = (int)$count_stmt->fetchColumn();

$total_pages = max(1, (int)ceil($total_messages / $per_page));
$page = min($page, $total_pages);
$offset = ($page - 1) * $per_page;

$sql = "SELECT * FROM contacts {$where_sql} ORDER BY id DESC LIMIT :limit OFFSET :offset";
$stmt = $pdo->prepare($sql);

foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}

$stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();

$messages = $stmt->fetchAll();

$total_all = (int)$pdo->query("SELECT COUNT(*) FROM contacts")->fetchColumn();
$total_unread = (int)$pdo->query("SELECT COUNT(*) FROM contacts WHERE status = 'unread'")->fetchColumn();
$total_today = (int)$pdo->query("SELECT COUNT(*) FROM contacts WHERE DATE(created_at) = CURDATE()")->fetchColumn();
$total_starred = (int)$pdo->query("SELECT COUNT(*) FROM contacts WHERE starred = 1")->fetchColumn();

$category_unread_counts = array_fill_keys(array_keys(cm_category_list()), 0);
$category_total_counts = array_fill_keys(array_keys(cm_category_list()), 0);
$category_counts_stmt = $pdo->query("SELECT request_type AS category, COUNT(*) AS total, SUM(status = 'unread') AS unread FROM contacts GROUP BY request_type");

foreach ($category_counts_stmt->fetchAll() as $row) {
    if (array_key_exists($row['category'], $category_unread_counts)) {
        $category_unread_counts[$row['category']] = (int)$row['unread'];
        $category_total_counts[$row['category']] = (int)$row['total'];
    }
}

$total_customers = 0;
$has_users_table = table_exists('users');

if ($has_users_table) {
    try {
        /*
         * A direct SQL JOIN comparing contacts.email to users.email can fail
         * with "Illegal mix of collations" when the two tables were created
         * with different default collations. Comparing each distinct email
         * against a PHP-bound parameter avoids that entirely.
         */
        $user_check_stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE LOWER(email) = LOWER(:email)");
        $email_groups = $pdo->query("SELECT email, COUNT(*) AS cnt FROM contacts GROUP BY LOWER(email)")->fetchAll();

        foreach ($email_groups as $group) {
            $user_check_stmt->execute([':email' => $group['email']]);

            if ((int)$user_check_stmt->fetchColumn() > 0) {
                $total_customers += (int)$group['cnt'];
            }
        }
    } catch (Throwable $e) {
        $total_customers = 0;
    }
}

/*
|--------------------------------------------------------------------------
| Per-Row Thread Size + Registered Customer Lookup
|--------------------------------------------------------------------------
| For each distinct email on this page, find how many total messages
| share that email (to show a "N messages" hint), and whether the email
| matches a registered user account.
|--------------------------------------------------------------------------
*/
$email_counts = [];
$customer_lookup = [];

if ($messages) {
    $count_by_email_stmt = $pdo->prepare("SELECT COUNT(*) FROM contacts WHERE LOWER(email) = LOWER(:email)");
    $customer_stmt = $has_users_table ? $pdo->prepare("SELECT id FROM users WHERE LOWER(email) = LOWER(:email) LIMIT 1") : null;

    foreach ($messages as $item) {
        $key = strtolower((string)$item['email']);

        if (!isset($email_counts[$key])) {
            $count_by_email_stmt->execute([':email' => $item['email']]);
            $email_counts[$key] = (int)$count_by_email_stmt->fetchColumn();
        }

        if ($customer_stmt && !array_key_exists($key, $customer_lookup)) {
            $customer_stmt->execute([':email' => $item['email']]);
            $customer_row = $customer_stmt->fetch();
            $customer_lookup[$key] = $customer_row ? (int)$customer_row['id'] : null;
        }
    }
}

$active_filter_count = 0;
foreach ([$search, $status_filter, $date_from, $date_to] as $filter_value) {
    if ($filter_value !== '') {
        $active_filter_count++;
    }
}
?>

<style>
    :root {
        --cm-bg: #f6f8fa;
        --cm-card: #ffffff;
        --cm-border: #d0d7de;
        --cm-border-soft: #d8dee4;
        --cm-text: #24292f;
        --cm-muted: #57606a;
        --cm-blue: #0969da;
        --cm-green: #1a7f37;
        --cm-green-bg: #dafbe1;
        --cm-shadow: 0 1px 0 rgba(27, 31, 36, 0.04);
    }

    .cm-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 16px;
        flex-wrap: wrap;
        margin-bottom: 16px;
        padding-bottom: 16px;
        border-bottom: 1px solid var(--cm-border);
    }

    .cm-header h1 {
        margin: 0 0 6px;
        color: var(--cm-text);
        font-size: 24px;
        line-height: 1.25;
        font-weight: 700;
        letter-spacing: -0.02em;
    }

    .cm-header p {
        margin: 0;
        color: var(--cm-muted);
        font-size: 14px;
    }

    .cm-overview {
        display: grid;
        grid-template-columns: repeat(4, minmax(150px, 1fr));
        gap: 12px;
        margin-bottom: 18px;
    }

    .cm-stat {
        display: flex;
        align-items: center;
        gap: 14px;
        background: var(--cm-card);
        border: 1px solid var(--cm-border);
        border-radius: 12px;
        padding: 16px;
        box-shadow: var(--cm-shadow);
    }

    .cm-stat-icon {
        width: 46px;
        height: 46px;
        flex: 0 0 46px;
        border-radius: 12px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
    }

    .cm-stat span {
        display: block;
        color: var(--cm-muted);
        font-size: 12px;
        font-weight: 600;
        margin-bottom: 4px;
    }

    .cm-stat strong {
        display: block;
        color: var(--cm-text);
        font-size: 22px;
        line-height: 1;
        font-weight: 700;
    }

    .cm-label-tabs {
        display: flex;
        gap: 4px;
        flex-wrap: wrap;
        margin-bottom: 12px;
        border-bottom: 1px solid var(--cm-border);
    }

    .cm-label-tab {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        padding: 10px 14px;
        margin-bottom: -1px;
        color: var(--cm-muted);
        text-decoration: none;
        font-size: 13px;
        font-weight: 600;
        border: 1px solid transparent;
        border-bottom: 2px solid transparent;
        border-radius: 6px 6px 0 0;
    }

    .cm-label-tab:hover {
        color: var(--cm-text);
        background: #f6f8fa;
        text-decoration: none;
    }

    .cm-label-tab.active {
        color: var(--cm-blue);
        border-bottom-color: var(--cm-blue);
    }

    .cm-label-tab .count {
        min-width: 18px;
        padding: 1px 6px;
        border-radius: 999px;
        background: #eaeef2;
        color: var(--cm-muted);
        font-size: 11px;
        font-weight: 700;
        text-align: center;
    }

    .cm-label-tab.active .count {
        background: #ddf4ff;
        color: var(--cm-blue);
    }

    .cm-toolbar {
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
        margin-bottom: 6px;
        padding: 10px 12px;
        background: var(--cm-card);
        border: 1px solid var(--cm-border);
        border-radius: 10px 10px 0 0;
        border-bottom: 0;
    }

    /*
     * The shared admin layout applies ".admin-main input/select/textarea { width: 100% }".
     * These IDs override that so toolbar controls keep their natural width
     * instead of each wrapping onto its own full-width line.
     */
    #cm-select-all,
    #cm-bulk-select,
    #cm-status-select,
    #cm-date-from,
    #cm-date-to {
        width: auto;
    }

    #cm-select-all {
        width: 15px !important;
        height: 15px !important;
        min-height: 15px !important;
        margin: 0;
        cursor: pointer;
    }

    .cm-toolbar-icon-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 34px;
        height: 34px;
        border-radius: 6px;
        border: 1px solid transparent;
        background: transparent;
        color: var(--cm-muted);
        cursor: pointer;
        text-decoration: none;
    }

    .cm-toolbar-icon-btn:hover {
        background: #eef1f4;
        color: var(--cm-text);
    }

    .cm-toolbar select {
        min-height: 34px;
        padding: 6px 10px;
        border: 1px solid var(--cm-border);
        border-radius: 6px;
        background: var(--cm-card);
        color: var(--cm-text);
        font-size: 13px;
        outline: none;
    }

    .cm-toolbar-search {
        margin-left: auto;
        display: flex;
        align-items: center;
        gap: 6px;
        min-width: 220px;
        padding: 6px 10px;
        border: 1px solid var(--cm-border);
        border-radius: 999px;
        background: var(--cm-bg);
    }

    .cm-toolbar-search input {
        border: 0;
        background: transparent;
        outline: none;
        font-size: 13px;
        width: 100%;
        color: var(--cm-text);
    }

    .cm-toolbar-search i {
        color: var(--cm-muted);
    }

    .cm-more-filters {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
        padding: 10px 12px;
        background: var(--cm-card);
        border: 1px solid var(--cm-border);
        border-top: 0;
        margin-bottom: 16px;
        font-size: 13px;
    }

    .cm-more-filters label {
        color: var(--cm-muted);
        font-weight: 600;
    }

    .cm-more-filters input[type="date"] {
        width: auto;
        min-height: 32px;
        padding: 4px 8px;
        border: 1px solid var(--cm-border);
        border-radius: 6px;
        background: var(--cm-card);
        color: var(--cm-text);
        font-size: 13px;
    }

    .cm-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        min-height: 32px;
        padding: 5px 12px;
        border-radius: 6px;
        border: 1px solid rgba(27, 31, 36, 0.15);
        background: #f6f8fa;
        color: var(--cm-text);
        font-size: 13px;
        font-weight: 600;
        text-decoration: none;
        cursor: pointer;
    }

    .cm-btn:hover {
        background: #eef1f4;
        text-decoration: none;
    }

    .cm-btn.primary {
        background: #ffffff;
        color: var(--cm-blue);
        border-color: var(--cm-blue);
        font: inherit;
    }

    .cm-btn.primary:hover {
        background: #ddf4ff;
    }

    .cm-table-note {
        margin: 10px 0;
        font-size: 13px;
        color: var(--cm-muted);
    }

    .cm-inbox {
        border: 1px solid var(--cm-border);
        border-radius: 0 0 10px 10px;
        overflow: hidden;
        background: var(--cm-card);
    }

    .cm-row {
        display: grid;
        grid-template-columns: 34px 34px 44px minmax(0, 1fr) auto;
        align-items: center;
        gap: 12px;
        padding: 12px 14px;
        border-bottom: 1px solid var(--cm-border-soft);
        text-decoration: none;
        color: inherit;
    }

    .cm-row:last-child {
        border-bottom: 0;
    }

    .cm-row.unread {
        background: #f6faff;
        font-weight: 600;
    }

    .cm-row:hover {
        background: #f6f8fa;
    }

    .cm-row-checkbox {
        width: 15px !important;
        height: 15px !important;
        min-height: 15px !important;
        align-self: center;
        justify-self: start;
        margin: 0;
        cursor: pointer;
    }

    .cm-row-star {
        border: 0;
        background: transparent;
        color: #d0d7de;
        cursor: pointer;
        font-size: 16px;
    }

    .cm-row-star.active {
        color: #eab308;
    }

    .cm-avatar {
        width: 34px;
        height: 34px;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: #ffffff;
        font-weight: 700;
        font-size: 14px;
        flex-shrink: 0;
    }

    .cm-row-main {
        min-width: 0;
    }

    .cm-row-top {
        display: flex;
        align-items: baseline;
        gap: 8px;
        min-width: 0;
    }

    .cm-row-name {
        color: var(--cm-text);
        font-size: 14px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        max-width: 220px;
    }

    .cm-row-email {
        color: var(--cm-muted);
        font-size: 12px;
        font-weight: 400;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .cm-row-subject-line {
        margin-top: 2px;
        display: flex;
        align-items: center;
        gap: 6px;
        min-width: 0;
        font-size: 13px;
        font-weight: 400;
        color: var(--cm-text);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .cm-row-preview {
        color: var(--cm-muted);
        font-weight: 400;
    }

    .cm-row-label {
        flex-shrink: 0;
        color: var(--cm-muted);
        font-weight: 700;
        font-size: 12px;
    }

    .cm-pill {
        display: inline-flex;
        align-items: center;
        flex-shrink: 0;
        padding: 2px 9px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 700;
    }

    .cm-pill.unread {
        background: #dbeafe;
        color: #1e3a8a;
    }

    .cm-pill.count {
        background: #ede9fe;
        color: #5b21b6;
    }

    .cm-row-right {
        display: flex;
        align-items: center;
        gap: 10px;
        justify-self: end;
    }

    .cm-row-date {
        color: var(--cm-muted);
        font-size: 12px;
        white-space: nowrap;
    }

    .cm-row-actions {
        display: none;
        align-items: center;
        gap: 4px;
    }

    .cm-row:hover .cm-row-actions {
        display: flex;
    }

    .cm-row:hover .cm-row-date {
        display: none;
    }

    .cm-icon-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 28px;
        height: 28px;
        border-radius: 6px;
        border: 1px solid transparent;
        background: transparent;
        color: var(--cm-muted);
        text-decoration: none;
        cursor: pointer;
    }

    .cm-icon-btn:hover {
        background: #eaeef2;
        color: var(--cm-text);
    }

    .cm-icon-btn.danger:hover {
        background: #ffebe9;
        color: #cf222e;
    }

    .cm-empty {
        padding: 40px 20px;
        text-align: center;
        color: var(--cm-muted);
    }

    .cm-pagination {
        display: flex;
        gap: 6px;
        align-items: center;
        justify-content: flex-end;
        margin-top: 16px;
        flex-wrap: wrap;
    }

    .cm-pagination a,
    .cm-pagination span {
        min-width: 32px;
        min-height: 32px;
        padding: 5px 10px;
        border-radius: 6px;
        background: var(--cm-card);
        border: 1px solid var(--cm-border);
        text-decoration: none;
        color: var(--cm-text);
        font-size: 13px;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }

    .cm-pagination a:hover {
        background: #f6f8fa;
        text-decoration: none;
    }

    .cm-pagination span.current {
        background: var(--cm-blue);
        color: #ffffff;
        border-color: var(--cm-blue);
    }

    @media(max-width: 1100px) {
        .cm-overview {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    @media(max-width: 760px) {
        .cm-row {
            grid-template-columns: 24px 24px 36px minmax(0, 1fr);
        }

        .cm-row-right {
            grid-column: 1 / -1;
            justify-self: start;
            padding-left: 60px;
        }

        .cm-toolbar-search {
            width: 100%;
            margin-left: 0;
        }
    }
</style>

<div class="cm-header">
    <div>
        <h1>Contact Messages</h1>
        <p>Messages submitted from the public Contact page.</p>
    </div>
</div>

<?php show_flash(); ?>

<div class="cm-overview">
    <div class="cm-stat">
        <div class="cm-stat-icon" style="background:#ddf4ff;color:#0969da;"><i class="fa fa-envelope-o"></i></div>
        <div>
            <span>Total Messages</span>
            <strong><?= number_format($total_all) ?></strong>
        </div>
    </div>

    <div class="cm-stat">
        <div class="cm-stat-icon" style="background:#ffebe9;color:#cf222e;"><i class="fa fa-envelope"></i></div>
        <div>
            <span>Unread</span>
            <strong><?= number_format($total_unread) ?></strong>
        </div>
    </div>

    <div class="cm-stat">
        <div class="cm-stat-icon" style="background:#dafbe1;color:#1a7f37;"><i class="fa fa-calendar-o"></i></div>
        <div>
            <span>Today</span>
            <strong><?= number_format($total_today) ?></strong>
        </div>
    </div>

    <div class="cm-stat">
        <div class="cm-stat-icon" style="background:#ede9fe;color:#6639ba;"><i class="fa fa-user-circle-o"></i></div>
        <div>
            <span>Registered Customers</span>
            <strong><?= number_format($total_customers) ?></strong>
        </div>
    </div>
</div>

<div class="cm-label-tabs">
    <?php foreach (cm_category_list() as $cat_key => $cat_info): ?>
        <a
            class="cm-label-tab <?= $category_filter === $cat_key ? 'active' : '' ?>"
            href="contact-messages.php?<?= e(admin_contact_messages_current_query(['category' => $cat_key, 'page' => null])) ?>"
        >
            <i class="fa <?= e($cat_info['icon']) ?>"></i>
            <?= e($cat_info['label']) ?>
            <?php
                $tab_total_count = $cat_key === 'primary' ? $total_all : $category_total_counts[$cat_key];
            ?>
            <?php if ($tab_total_count > 0): ?>
                <span class="count"><?= number_format($tab_total_count) ?></span>
            <?php endif; ?>
        </a>
    <?php endforeach; ?>
</div>

<form method="post" action="contact-messages.php<?= $_SERVER['QUERY_STRING'] !== '' ? '?' . e($_SERVER['QUERY_STRING']) : '' ?>" id="cm-bulk-form">
  <input type="hidden" name="csrf_token" value="<?= e($bulk_csrf_token) ?>">

  <div class="cm-toolbar">
    <input type="checkbox" id="cm-select-all">

    <a class="cm-toolbar-icon-btn" href="contact-messages.php<?= $_SERVER['QUERY_STRING'] !== '' ? '?' . e($_SERVER['QUERY_STRING']) : '' ?>" title="Refresh"><i class="fa fa-refresh"></i></a>

    <select id="cm-bulk-select" name="bulk_action">
      <option value="">Bulk actions</option>
      <option value="mark_read">Mark Read</option>
      <option value="mark_unread">Mark Unread</option>
      <option value="delete">Delete</option>
      <optgroup label="Move to">
        <?php foreach (cm_category_list() as $cat_key => $cat_info): ?>
          <option value="move_<?= e($cat_key) ?>">Move to <?= e($cat_info['label']) ?></option>
        <?php endforeach; ?>
      </optgroup>
    </select>

    <button type="submit" class="cm-btn primary">Apply</button>

    <select id="cm-status-select" onchange="window.location.href = this.value">
      <option value="contact-messages.php?<?= e(admin_contact_messages_current_query(['status' => null, 'page' => null])) ?>" <?= $status_filter === '' ? 'selected' : '' ?>>All Messages</option>
      <option value="contact-messages.php?<?= e(admin_contact_messages_current_query(['status' => 'unread', 'page' => null])) ?>" <?= $status_filter === 'unread' ? 'selected' : '' ?>>Unread (<?= number_format($total_unread) ?>)</option>
      <option value="contact-messages.php?<?= e(admin_contact_messages_current_query(['status' => 'starred', 'page' => null])) ?>" <?= $status_filter === 'starred' ? 'selected' : '' ?>>Starred (<?= number_format($total_starred) ?>)</option>
      <option value="contact-messages.php?<?= e(admin_contact_messages_current_query(['status' => 'read', 'page' => null])) ?>" <?= $status_filter === 'read' ? 'selected' : '' ?>>Read</option>
    </select>

    <div class="cm-toolbar-search" style="margin-left:auto;">
      <i class="fa fa-search"></i>
      <input
        type="text"
        id="cm-search-input"
        value="<?= e($search) ?>"
        placeholder="Search mail"
        onkeydown="if (event.key === 'Enter') { event.preventDefault(); window.location.href = 'contact-messages.php?<?= e(admin_contact_messages_current_query(['page' => null, 'search' => null])) ?>' + (this.value ? '&search=' + encodeURIComponent(this.value) : ''); }"
      >
    </div>
  </div>

  <div class="cm-more-filters">
    <label for="cm-date-from">From</label>
    <input type="date" id="cm-date-from" value="<?= e($date_from) ?>" onchange="window.location.href = 'contact-messages.php?<?= e(admin_contact_messages_current_query(['page' => null])) ?>&date_from=' + this.value">

    <label for="cm-date-to">To</label>
    <input type="date" id="cm-date-to" value="<?= e($date_to) ?>" onchange="window.location.href = 'contact-messages.php?<?= e(admin_contact_messages_current_query(['page' => null])) ?>&date_to=' + this.value">

    <?php if ($active_filter_count > 0): ?>
      <a href="contact-messages.php" class="cm-btn">Clear filters</a>
    <?php endif; ?>

    <span class="cm-table-note" style="margin:0 0 0 auto;">
      Showing <?= number_format(count($messages)) ?> of <?= number_format($total_messages) ?> matched messages<?= $active_filter_count ? ' with ' . e((string)$active_filter_count) . ' active filter(s)' : '' ?>.
    </span>
  </div>

  <div class="cm-inbox">
    <?php if ($messages): ?>
      <?php foreach ($messages as $item): ?>
        <?php
          $email_key = strtolower((string)$item['email']);
          $thread_count = $email_counts[$email_key] ?? 1;
          $is_customer = $has_users_table && !empty($customer_lookup[$email_key]);
          $is_unread = ($item['status'] ?? 'unread') === 'unread';
          $is_starred = !empty($item['starred']);
          $name = trim((string)$item['name']) !== '' ? trim((string)$item['name']) : $item['email'];
          $initial = mb_strtoupper(mb_substr($name, 0, 1));
        ?>
        <div class="cm-row <?= $is_unread ? 'unread' : '' ?>">
          <input type="checkbox" class="cm-row-checkbox" name="ids[]" value="<?= e((string)$item['id']) ?>">

          <a class="cm-row-star <?= $is_starred ? 'active' : '' ?>" href="contact-messages.php?star=<?= e((string)$item['id']) ?><?= $_SERVER['QUERY_STRING'] !== '' ? '&' . e($_SERVER['QUERY_STRING']) : '' ?>" title="<?= $is_starred ? 'Unstar' : 'Star' ?>">
            <i class="fa <?= $is_starred ? 'fa-star' : 'fa-star-o' ?>"></i>
          </a>

          <a href="contact-message-view.php?email=<?= e(rawurlencode($item['email'])) ?>&open=<?= e((string)$item['id']) ?>" class="cm-avatar" style="background:<?= e(cm_avatar_color($item['email'])) ?>;">
            <?= e($initial) ?>
          </a>

          <a href="contact-message-view.php?email=<?= e(rawurlencode($item['email'])) ?>&open=<?= e((string)$item['id']) ?>" class="cm-row-main" style="text-decoration:none;color:inherit;">
            <div class="cm-row-top">
              <span class="cm-row-name"><?= e($name) ?></span>
              <span class="cm-row-email"><?= e($item['email']) ?></span>
            </div>

            <?php
                $item_request_label = trim((string)($item['request_type'] ?? '')) !== ''
                    ? (cm_category_list()[$item['request_type']]['label'] ?? ucfirst((string)$item['request_type']))
                    : '';
                // The request type's label already matches the subject text for
                // every submission made through the dropdown, so the label chip
                // only needs to show when it adds information the subject doesn't
                // already state (e.g. after an admin manually reclassifies it).
                $show_request_label = $category_filter === 'primary' && $item_request_label !== '' && $item_request_label !== $item['subject'];
            ?>
            <div class="cm-row-subject-line">
              <?php if ($show_request_label): ?>
                <span class="cm-row-label"><?= e($item_request_label) ?></span>
              <?php endif; ?>

              <span><?= e($item['subject'] !== '' ? $item['subject'] : '(no subject)') ?></span>

              <?php if ($is_unread): ?>
                <span class="cm-pill unread">Unread</span>
              <?php endif; ?>

              <?php if ($thread_count > 1): ?>
                <span class="cm-pill count"><?= e((string)$thread_count) ?> messages</span>
              <?php endif; ?>

              <span class="cm-row-preview">- <?= e(mb_strimwidth((string)($item['message'] ?? ''), 0, 80, '...')) ?></span>
            </div>
          </a>

          <div class="cm-row-right">
            <span class="cm-row-date"><?= e(date('M d', strtotime((string)($item['created_at'] ?? 'now')))) ?></span>

            <div class="cm-row-actions">
              <a class="cm-icon-btn" href="contact-message-view.php?email=<?= e(rawurlencode($item['email'])) ?>&open=<?= e((string)$item['id']) ?>" title="View"><i class="fa fa-eye"></i></a>

              <?php if (!$is_unread): ?>
                <a class="cm-icon-btn" href="contact-messages.php?unread=<?= e((string)$item['id']) ?>" title="Mark Unread"><i class="fa fa-envelope"></i></a>
              <?php endif; ?>

              <a class="cm-icon-btn" href="mailto:<?= e($item['email']) ?>?subject=<?= e(rawurlencode('Re: ' . ($item['subject'] !== '' ? $item['subject'] : '(no subject)'))) ?>" title="Reply"><i class="fa fa-reply"></i></a>

              <a
                class="cm-icon-btn danger"
                href="contact-messages.php?delete=<?= e((string)$item['id']) ?>"
                onclick="return confirm('Delete this message?')"
                title="Delete"
              ><i class="fa fa-trash-o"></i></a>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    <?php else: ?>
      <div class="cm-empty">
        No messages found. Try changing the search keyword or clearing the selected filters.
      </div>
    <?php endif; ?>
  </div>
</form>

<script>
  (function () {
    var selectAll = document.getElementById('cm-select-all');
    var form = document.getElementById('cm-bulk-form');

    if (!selectAll || !form) {
      return;
    }

    selectAll.addEventListener('change', function () {
      var checked = selectAll.checked;
      form.querySelectorAll('.cm-row-checkbox').forEach(function (checkbox) {
        checkbox.checked = checked;
      });
    });

    form.addEventListener('submit', function (event) {
      var action = document.getElementById('cm-bulk-select').value;
      var checkedCount = form.querySelectorAll('.cm-row-checkbox:checked').length;

      if (action === '') {
        alert('Please select a bulk action.');
        event.preventDefault();
        return;
      }

      if (checkedCount === 0) {
        alert('Please select at least one message.');
        event.preventDefault();
        return;
      }

      if (action === 'delete' && !confirm('Delete all selected messages?')) {
        event.preventDefault();
      }
    });
  })();
</script>

<?php if ($total_pages > 1): ?>
    <div class="cm-pagination">
        <?php if ($page > 1): ?>
            <a href="contact-messages.php?<?= e(admin_contact_messages_current_query(['page' => $page - 1])) ?>">Previous</a>
        <?php endif; ?>

        <?php
        $start_page = max(1, $page - 2);
        $end_page = min($total_pages, $page + 2);
        ?>

        <?php if ($start_page > 1): ?>
            <a href="contact-messages.php?<?= e(admin_contact_messages_current_query(['page' => 1])) ?>">1</a>
            <?php if ($start_page > 2): ?>
                <span>...</span>
            <?php endif; ?>
        <?php endif; ?>

        <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
            <?php if ($i === $page): ?>
                <span class="current"><?= e((string)$i) ?></span>
            <?php else: ?>
                <a href="contact-messages.php?<?= e(admin_contact_messages_current_query(['page' => $i])) ?>">
                    <?= e((string)$i) ?>
                </a>
            <?php endif; ?>
        <?php endfor; ?>

        <?php if ($end_page < $total_pages): ?>
            <?php if ($end_page < $total_pages - 1): ?>
                <span>...</span>
            <?php endif; ?>
            <a href="contact-messages.php?<?= e(admin_contact_messages_current_query(['page' => $total_pages])) ?>"><?= e((string)$total_pages) ?></a>
        <?php endif; ?>

        <?php if ($page < $total_pages): ?>
            <a href="contact-messages.php?<?= e(admin_contact_messages_current_query(['page' => $page + 1])) ?>">Next</a>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
