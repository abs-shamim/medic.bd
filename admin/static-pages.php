<?php
ob_start();

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/static-pages-bootstrap.php';

$csrfToken = admin_static_pages_bootstrap();
$coreSlugs = medic_static_page_allowed_slugs();

/*
|--------------------------------------------------------------------------
| Schedule / cancel deletion
|--------------------------------------------------------------------------
| Confirming deletion makes the page inactive immediately. The row remains
| available for one hour, then the cleanup job permanently removes it.
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedToken = (string) ($_POST['csrf_token'] ?? '');
    $action = (string) ($_POST['static_page_action'] ?? '');
    $pageId = max(0, (int) ($_POST['page_id'] ?? 0));

    if (!hash_equals($csrfToken, $postedToken)) {
        flash('error', 'Security token mismatch. Please try again.');
        redirect('static-pages.php');
    }

    if ($pageId <= 0) {
        flash('error', 'Invalid page selected.');
        redirect('static-pages.php');
    }

    try {
        $findStatement = $pdo->prepare(
            'SELECT id, slug, title_en, status, delete_scheduled_at
             FROM static_pages
             WHERE id = :id
             LIMIT 1'
        );
        $findStatement->execute([':id' => $pageId]);
        $selectedPage = $findStatement->fetch(PDO::FETCH_ASSOC);

        if (!is_array($selectedPage)) {
            flash('error', 'The selected page was not found.');
            redirect('static-pages.php');
        }

        $selectedSlug = (string) ($selectedPage['slug'] ?? '');
        $selectedTitle = trim((string) ($selectedPage['title_en'] ?? '')) ?: 'This page';

        if (in_array($selectedSlug, $coreSlugs, true)) {
            flash('error', 'Core policy pages cannot be deleted. You can make them inactive from Page Form instead.');
            redirect('static-pages.php');
        }

        if ($action === 'schedule_delete') {
            $updateStatement = $pdo->prepare(
                'UPDATE static_pages
                 SET status = :status,
                     delete_scheduled_at = DATE_ADD(NOW(), INTERVAL 1 HOUR),
                     updated_at = NOW()
                 WHERE id = :id'
            );
            $updateStatement->execute([
                ':status' => 'inactive',
                ':id' => $pageId,
            ]);

            flash('success', $selectedTitle . ' is now inactive and will be permanently deleted in one hour.');
        } elseif ($action === 'cancel_scheduled_delete') {
            $updateStatement = $pdo->prepare(
                'UPDATE static_pages
                 SET delete_scheduled_at = NULL,
                     updated_at = NOW()
                 WHERE id = :id'
            );
            $updateStatement->execute([':id' => $pageId]);

            flash('success', 'Scheduled deletion was cancelled. The page stays inactive until you reactivate it from Page Form.');
        } else {
            flash('error', 'Invalid action requested.');
        }
    } catch (Throwable $exception) {
        error_log('Static page deletion scheduling error: ' . $exception->getMessage());
        flash('error', 'The delete request could not be processed. Please try again.');
    }

    redirect('static-pages.php');
}

try {
    $statement = $pdo->query(
        "SELECT id, slug, title_en, title_bn, status, delete_scheduled_at, created_at, updated_at
         FROM static_pages
         ORDER BY FIELD(slug, 'privacy-policy', 'terms', 'support'), id DESC"
    );
    $pages = $statement->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $exception) {
    error_log('Static pages list error: ' . $exception->getMessage());
    $pages = [];
    flash('error', 'Static pages could not be loaded. Please try again.');
}
?>
<style>
  .static-pages-wrap { max-width: 1380px; margin: 0 auto; }
  .static-pages-hero { display:flex; align-items:flex-start; justify-content:space-between; gap:16px; flex-wrap:wrap; margin-bottom:18px; }
  .static-pages-hero h1 { margin:0; color:#0f172a; font-size:28px; }
  .static-pages-hero p { max-width:820px; margin:7px 0 0; color:#64748b; font-size:14px; line-height:1.65; }
  .static-pages-card { overflow:hidden; background:#fff; border:1px solid #e2e8f0; border-radius:12px; box-shadow:0 2px 10px rgba(15,23,42,.04); }
  .static-pages-card-head { display:flex; align-items:center; justify-content:space-between; gap:12px; padding:16px 18px; border-bottom:1px solid #e2e8f0; background:#f8fafc; }
  .static-pages-card-head h2 { margin:0; color:#0f172a; font-size:17px; }
  .static-pages-card-head p { margin:4px 0 0; color:#64748b; font-size:12px; }
  .static-pages-table-wrap { overflow:auto; }
  .static-pages-table { width:100%; min-width:1080px; border-collapse:collapse; }
  .static-pages-table th { padding:12px 16px; background:#f8fafc; color:#475569; font-size:12px; font-weight:800; text-align:left; text-transform:uppercase; letter-spacing:.03em; }
  .static-pages-table td { padding:14px 16px; border-top:1px solid #f1f5f9; color:#334155; font-size:14px; vertical-align:middle; }
  .static-pages-name { display:grid; gap:3px; }
  .static-pages-name strong { color:#0f172a; }
  .static-pages-name small { color:#64748b; }
  .static-pages-slug { display:inline-flex; padding:4px 8px; border-radius:6px; background:#f1f5f9; color:#334155; font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,monospace; font-size:12px; }
  .static-pages-badge { display:inline-flex; padding:4px 9px; border-radius:999px; font-size:11px; font-weight:800; }
  .static-pages-badge.active { background:#dcfce7; color:#166534; }
  .static-pages-badge.inactive { background:#fee2e2; color:#991b1b; }
  .static-pages-badge.pending-delete { background:#fff3cd; color:#8a5a00; }
  .static-pages-core { display:inline-flex; margin-left:7px; padding:3px 7px; border:1px solid #bfdbfe; border-radius:999px; color:#1d4ed8; font-size:10px; font-weight:800; }
  .static-pages-delete-time { display:block; max-width:195px; margin-top:6px; color:#9a5a00; font-size:11px; line-height:1.45; }
  .static-pages-actions { display:flex; gap:8px; align-items:center; flex-wrap:wrap; }
  .static-pages-actions form { margin:0; }
  .static-pages-empty { padding:46px 20px; color:#64748b; text-align:center; }
  .static-pages-note { margin-top:14px; padding:11px 13px; border:1px solid #fde68a; border-radius:9px; background:#fffbeb; color:#854d0e; font-size:12px; line-height:1.55; }

  /* Strong selectors prevent the global .admin-main button rule from changing these controls. */
  .admin-main .static-pages-delete-trigger,
  .admin-main .static-pages-cancel-delete {
    display:inline-flex; align-items:center; justify-content:center; min-height:34px; padding:7px 12px;
    border-radius:6px; font:inherit; font-size:13px; font-weight:700; line-height:1; cursor:pointer; text-decoration:none; box-shadow:none;
  }
  .admin-main .static-pages-delete-trigger { border:1px solid #dc2626; background:#dc2626; color:#fff; }
  .admin-main .static-pages-delete-trigger:hover { border-color:#b91c1c; background:#b91c1c; color:#fff; }
  .admin-main .static-pages-cancel-delete { border:1px solid #d6a100; background:#fff; color:#7c5200; }
  .admin-main .static-pages-cancel-delete:hover { border-color:#b77900; background:#fff8dc; color:#7c5200; }

  .static-pages-modal[hidden] { display:none !important; }
  .static-pages-modal { position:fixed; inset:0; z-index:9999; display:grid; place-items:center; padding:18px; background:rgba(15,23,42,.52); }
  .static-pages-modal-card { width:min(100%, 480px); padding:24px; border:1px solid #e2e8f0; border-radius:14px; background:#fff; box-shadow:0 22px 64px rgba(15,23,42,.28); }
  .static-pages-modal-icon { display:grid; width:42px; height:42px; place-items:center; margin-bottom:13px; border-radius:50%; background:#fee2e2; color:#b91c1c; font-size:21px; font-weight:800; }
  .static-pages-modal-card h2 { margin:0; color:#0f172a; font-size:20px; }
  .static-pages-modal-card p { margin:9px 0 0; color:#475569; font-size:14px; line-height:1.6; }
  .static-pages-modal-page-name { color:#0f172a; font-weight:800; }
  .static-pages-modal-warning { margin:15px 0; padding:11px 12px; border:1px solid #fde68a; border-radius:8px; background:#fffbeb; color:#854d0e; font-size:13px; line-height:1.55; }
  .static-pages-modal-actions { display:flex; justify-content:flex-end; gap:10px; flex-wrap:wrap; }
  .admin-main .static-pages-modal-cancel,
  .admin-main .static-pages-modal-confirm { display:inline-flex; align-items:center; justify-content:center; min-height:40px; padding:0 15px; border-radius:7px; font:inherit; font-size:13px; font-weight:800; cursor:pointer; box-shadow:none; }
  .admin-main .static-pages-modal-cancel { border:1px solid #cbd5e1; background:#fff; color:#1f2937; }
  .admin-main .static-pages-modal-confirm { border:1px solid #dc2626; background:#dc2626; color:#fff; }
  .admin-main .static-pages-modal-confirm:hover { border-color:#b91c1c; background:#b91c1c; color:#fff; }
  body.static-pages-modal-open { overflow:hidden; }
  @media (max-width:700px) { .static-pages-hero .btn { width:100%; justify-content:center; } .static-pages-modal-card { padding:20px; } .static-pages-modal-actions > * { flex:1 1 auto; } }
</style>

<div class="static-pages-wrap">
  <div class="static-pages-hero">
    <div>
      <h1>Static Pages</h1>
      <p>Manage all public legal, support and custom content pages from one place. Use Page Form to add a new page or edit an existing one.</p>
    </div>
    <a class="btn btn-primary" href="static-page-form.php">+ Add New Page</a>
  </div>

  <?php show_flash(); ?>

  <section class="static-pages-card">
    <div class="static-pages-card-head">
      <div>
        <h2>Page List</h2>
        <p><?= e((string) count($pages)) ?> page(s) available. Deleted pages are made inactive immediately, then permanently removed after one hour.</p>
      </div>
    </div>

    <?php if ($pages === []): ?>
      <div class="static-pages-empty">No static pages were found.</div>
    <?php else: ?>
      <div class="static-pages-table-wrap">
        <table class="static-pages-table">
          <thead>
            <tr>
              <th>Page</th>
              <th>URL</th>
              <th>Status</th>
              <th>Last Updated</th>
              <th style="width:310px;">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($pages as $page): ?>
              <?php
              $pageId = (int) ($page['id'] ?? 0);
              $slug = (string) ($page['slug'] ?? '');
              $title = trim((string) ($page['title_en'] ?? '')) ?: 'Untitled Page';
              $isCore = in_array($slug, $coreSlugs, true);
              $status = (string) ($page['status'] ?? 'inactive');
              $deleteScheduledAt = trim((string) ($page['delete_scheduled_at'] ?? ''));
              $isPendingDelete = $deleteScheduledAt !== '';
              ?>
              <tr>
                <td>
                  <div class="static-pages-name">
                    <strong><?= e($title) ?></strong>
                    <small><?= e((string) ($page['title_bn'] ?? '')) ?: 'No Bangla title' ?></small>
                  </div>
                </td>
                <td>
                  <span class="static-pages-slug">/<?= e($slug) ?></span>
                  <?php if ($isCore): ?><span class="static-pages-core">Core page</span><?php endif; ?>
                </td>
                <td>
                  <?php if ($isPendingDelete): ?>
                    <span class="static-pages-badge pending-delete">Delete scheduled</span>
                    <span class="static-pages-delete-time">Deletes after: <?= e($deleteScheduledAt) ?></span>
                  <?php else: ?>
                    <span class="static-pages-badge <?= e($status) ?>"><?= e(ucfirst($status)) ?></span>
                  <?php endif; ?>
                </td>
                <td><?= e((string) ($page['updated_at'] ?? '')) ?></td>
                <td>
                  <div class="static-pages-actions">
                    <a class="btn btn-primary btn-sm" href="static-page-form.php?id=<?= $pageId ?>">Edit</a>

                    <?php if ($status === 'active' && !$isPendingDelete): ?>
                      <a class="btn btn-secondary btn-sm" href="<?= e(medic_static_page_url($slug, 'en')) ?>" target="_blank" rel="noopener noreferrer">View</a>
                    <?php endif; ?>

                    <?php if (!$isCore && !$isPendingDelete): ?>
                      <button
                        type="button"
                        class="static-pages-delete-trigger"
                        data-static-page-delete
                        data-page-id="<?= $pageId ?>"
                        data-page-title="<?= e($title) ?>"
                      >Delete</button>
                    <?php elseif (!$isCore && $isPendingDelete): ?>
                      <form method="post" action="static-pages.php">
                        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                        <input type="hidden" name="page_id" value="<?= $pageId ?>">
                        <input type="hidden" name="static_page_action" value="cancel_scheduled_delete">
                        <button type="submit" class="static-pages-cancel-delete">Cancel Delete</button>
                      </form>
                    <?php elseif ($isCore): ?>
                      <span style="color:#94a3b8;font-size:12px;">Core page cannot be deleted</span>
                    <?php else: ?>
                      <span style="color:#94a3b8;font-size:12px;">Public page inactive</span>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>

  <div class="static-pages-note">
    Core pages, including Privacy Policy, Terms and Support, stay protected because they are linked by the site footer. Use Page Form to set a core page inactive when necessary.
  </div>
</div>

<div class="static-pages-modal" id="static-page-delete-modal" hidden aria-hidden="true">
  <div class="static-pages-modal-card" role="dialog" aria-modal="true" aria-labelledby="static-page-delete-title" aria-describedby="static-page-delete-description">
    <div class="static-pages-modal-icon" aria-hidden="true">!</div>
    <h2 id="static-page-delete-title">Schedule page deletion?</h2>
    <p id="static-page-delete-description">
      <span class="static-pages-modal-page-name" id="static-page-delete-name"></span> will become inactive immediately and visitors will see a 404 page.
    </p>
    <div class="static-pages-modal-warning">Clicking <strong>OK, Schedule Delete</strong> keeps the page for one hour, then permanently deletes it. You can cancel the scheduled deletion before that time.</div>

    <form method="post" action="static-pages.php" id="static-page-delete-form">
      <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
      <input type="hidden" name="page_id" id="static-page-delete-id" value="">
      <input type="hidden" name="static_page_action" value="schedule_delete">
      <div class="static-pages-modal-actions">
        <button type="button" class="static-pages-modal-cancel" data-static-page-delete-cancel>Cancel</button>
        <button type="submit" class="static-pages-modal-confirm">OK, Schedule Delete</button>
      </div>
    </form>
  </div>
</div>

<script>
(function () {
  var modal = document.getElementById('static-page-delete-modal');
  var pageIdInput = document.getElementById('static-page-delete-id');
  var pageName = document.getElementById('static-page-delete-name');
  var openButtons = document.querySelectorAll('[data-static-page-delete]');
  var cancelButton = document.querySelector('[data-static-page-delete-cancel]');
  var lastTrigger = null;

  if (!modal || !pageIdInput || !pageName) {
    return;
  }

  function closeModal() {
    modal.hidden = true;
    modal.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('static-pages-modal-open');

    if (lastTrigger) {
      lastTrigger.focus();
    }
  }

  openButtons.forEach(function (button) {
    button.addEventListener('click', function () {
      lastTrigger = button;
      pageIdInput.value = button.getAttribute('data-page-id') || '';
      pageName.textContent = button.getAttribute('data-page-title') || 'This page';
      modal.hidden = false;
      modal.setAttribute('aria-hidden', 'false');
      document.body.classList.add('static-pages-modal-open');
      var confirmButton = modal.querySelector('.static-pages-modal-confirm');
      if (confirmButton) {
        confirmButton.focus();
      }
    });
  });

  if (cancelButton) {
    cancelButton.addEventListener('click', closeModal);
  }

  modal.addEventListener('click', function (event) {
    if (event.target === modal) {
      closeModal();
    }
  });

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape' && !modal.hidden) {
      closeModal();
    }
  });
}());
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
