<?php
ob_start();

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/static-pages-bootstrap.php';
require_once __DIR__ . '/includes/static-page-editor.php';

$csrfToken = admin_static_pages_bootstrap();

$pageId = max(0, (int) ($_GET['id'] ?? $_POST['id'] ?? 0));
$page = admin_static_pages_blank_page();

if ($pageId > 0) {
    $statement = $pdo->prepare('SELECT * FROM static_pages WHERE id = :id LIMIT 1');
    $statement->execute([':id' => $pageId]);
    $loadedPage = $statement->fetch(PDO::FETCH_ASSOC);

    if (!is_array($loadedPage)) {
        flash('error', 'The selected static page was not found.');
        redirect('static-pages.php');
    }

    $page = array_merge($page, $loadedPage);
}

$isEdit = (int) $page['id'] > 0;
$coreSlugs = medic_static_page_allowed_slugs();
$isCorePage = $isEdit && in_array((string) $page['slug'], $coreSlugs, true);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_static_page'])) {
    $postedToken = (string) ($_POST['csrf_token'] ?? '');

    if (!hash_equals($csrfToken, $postedToken)) {
        flash('error', 'Security token mismatch. Please try again.');
    } else {
        $fields = [
            'title_en', 'title_bn', 'kicker_en', 'kicker_bn',
            'intro_en', 'intro_bn', 'content_en', 'content_bn',
            'meta_title_en', 'meta_title_bn', 'meta_description_en', 'meta_description_bn',
        ];

        $draft = admin_static_pages_blank_page();
        $draft['id'] = $pageId;
        $draft['status'] = (string) ($_POST['status'] ?? 'inactive') === 'active' ? 'active' : 'inactive';

        foreach ($fields as $field) {
            $draft[$field] = trim((string) ($_POST[$field] ?? ''));
        }

        $postedSlug = admin_static_pages_normalize_slug((string) ($_POST['slug'] ?? ''));
        $draft['slug'] = $isCorePage ? (string) $page['slug'] : $postedSlug;

        $errors = [];

        if ($draft['title_en'] === '') {
            $errors[] = 'English page title is required.';
        }

        if (medic_static_page_length($draft['title_en']) > 255 || medic_static_page_length($draft['title_bn']) > 255) {
            $errors[] = 'Page titles must be 255 characters or fewer.';
        }

        if (medic_static_page_length($draft['kicker_en']) > 255 || medic_static_page_length($draft['kicker_bn']) > 255) {
            $errors[] = 'Hero kicker text must be 255 characters or fewer.';
        }

        if (medic_static_page_length($draft['meta_title_en']) > 255 || medic_static_page_length($draft['meta_title_bn']) > 255) {
            $errors[] = 'SEO titles must be 255 characters or fewer.';
        }

        if (medic_static_page_length($draft['content_en']) > 100000 || medic_static_page_length($draft['content_bn']) > 100000) {
            $errors[] = 'Page content is too long.';
        }

        $slugError = admin_static_pages_validate_slug($draft['slug'], $pageId);
        if ($slugError !== null) {
            $errors[] = $slugError;
        }

        if ($errors === []) {
            try {
                if ($pageId > 0) {
                    $statement = $pdo->prepare(
                        'UPDATE static_pages SET
                            slug = :slug,
                            title_en = :title_en,
                            title_bn = :title_bn,
                            kicker_en = :kicker_en,
                            kicker_bn = :kicker_bn,
                            intro_en = :intro_en,
                            intro_bn = :intro_bn,
                            content_en = :content_en,
                            content_bn = :content_bn,
                            meta_title_en = :meta_title_en,
                            meta_title_bn = :meta_title_bn,
                            meta_description_en = :meta_description_en,
                            meta_description_bn = :meta_description_bn,
                            status = :status,
                            updated_at = NOW()
                         WHERE id = :id'
                    );

                    $statement->execute([
                        ':slug' => $draft['slug'],
                        ':title_en' => $draft['title_en'],
                        ':title_bn' => $draft['title_bn'],
                        ':kicker_en' => $draft['kicker_en'],
                        ':kicker_bn' => $draft['kicker_bn'],
                        ':intro_en' => $draft['intro_en'],
                        ':intro_bn' => $draft['intro_bn'],
                        ':content_en' => $draft['content_en'],
                        ':content_bn' => $draft['content_bn'],
                        ':meta_title_en' => $draft['meta_title_en'],
                        ':meta_title_bn' => $draft['meta_title_bn'],
                        ':meta_description_en' => $draft['meta_description_en'],
                        ':meta_description_bn' => $draft['meta_description_bn'],
                        ':status' => $draft['status'],
                        ':id' => $pageId,
                    ]);

                    flash('success', 'Static page updated successfully.');
                } else {
                    $statement = $pdo->prepare(
                        'INSERT INTO static_pages (
                            slug, title_en, title_bn, kicker_en, kicker_bn, intro_en, intro_bn,
                            content_en, content_bn, meta_title_en, meta_title_bn,
                            meta_description_en, meta_description_bn, status, created_at, updated_at
                        ) VALUES (
                            :slug, :title_en, :title_bn, :kicker_en, :kicker_bn, :intro_en, :intro_bn,
                            :content_en, :content_bn, :meta_title_en, :meta_title_bn,
                            :meta_description_en, :meta_description_bn, :status, NOW(), NOW()
                        )'
                    );
                    $statement->execute([
                        ':slug' => $draft['slug'],
                        ':title_en' => $draft['title_en'],
                        ':title_bn' => $draft['title_bn'],
                        ':kicker_en' => $draft['kicker_en'],
                        ':kicker_bn' => $draft['kicker_bn'],
                        ':intro_en' => $draft['intro_en'],
                        ':intro_bn' => $draft['intro_bn'],
                        ':content_en' => $draft['content_en'],
                        ':content_bn' => $draft['content_bn'],
                        ':meta_title_en' => $draft['meta_title_en'],
                        ':meta_title_bn' => $draft['meta_title_bn'],
                        ':meta_description_en' => $draft['meta_description_en'],
                        ':meta_description_bn' => $draft['meta_description_bn'],
                        ':status' => $draft['status'],
                    ]);

                    $pageId = (int) $pdo->lastInsertId();
                    flash('success', 'New static page created successfully.');
                }

                redirect('static-page-form.php?id=' . $pageId);
            } catch (Throwable $exception) {
                error_log('Static page save error: ' . $exception->getMessage());
                flash('error', 'The page could not be saved. Please try again.');
            }
        } else {
            flash('error', implode(' ', $errors));
        }

        $page = array_merge($page, $draft);
        $isEdit = (int) $page['id'] > 0;
        $isCorePage = $isEdit && in_array((string) $page['slug'], $coreSlugs, true);
    }
}

$publicUrl = $isEdit ? medic_static_page_url((string) $page['slug'], 'en') : '';
?>
<link rel="stylesheet" href="<?= e(admin_static_page_editor_css_url()) ?>">
<style>
  :root {
    --spf-ink: #10243e;
    --spf-muted: #6b7a90;
    --spf-line: #e5ebf3;
    --spf-soft: #f7faff;
    --spf-primary: #1769e0;
    --spf-primary-soft: #eaf2ff;
    --spf-success: #16824b;
    --spf-warning: #ae6711;
    --spf-radius: 16px;
  }

  .static-page-form-wrap {
    max-width: 1240px;
    margin: 0 auto;
    padding-bottom: 28px;
  }

  .static-page-form-hero {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
    flex-wrap: wrap;
    margin: 0 0 20px;
  }

  .static-page-form-eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    margin-bottom: 8px;
    color: var(--spf-primary);
    font-size: 12px;
    font-weight: 800;
    letter-spacing: .08em;
    text-transform: uppercase;
  }

  .static-page-form-hero h1 {
    margin: 0;
    color: var(--spf-ink);
    font-size: clamp(26px, 3vw, 31px);
    line-height: 1.2;
  }

  .static-page-form-hero p {
    max-width: 780px;
    margin: 8px 0 0;
    color: var(--spf-muted);
    font-size: 14px;
    line-height: 1.7;
  }

  .static-page-form-top-actions,
  .static-page-actions {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
  }

  .static-page-form-top-actions .btn,
  .static-page-actions .btn {
    text-decoration: none;
  }

  .static-page-editor {
    overflow: hidden;
    background: #fff;
    border: 1px solid var(--spf-line);
    border-radius: var(--spf-radius);
    box-shadow: 0 12px 30px rgba(16, 36, 62, .06);
  }

  .static-page-editor-head {
    padding: 22px 24px;
    border-bottom: 1px solid var(--spf-line);
    background:
      radial-gradient(circle at right top, rgba(23, 105, 224, .09), transparent 34%),
      var(--spf-soft);
  }

  .static-page-editor-head-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    flex-wrap: wrap;
  }

  .static-page-editor-head h2 {
    margin: 0;
    color: var(--spf-ink);
    font-size: 20px;
    line-height: 1.35;
  }

  .static-page-editor-head p {
    max-width: 800px;
    margin: 7px 0 0;
    color: var(--spf-muted);
    font-size: 13px;
    line-height: 1.65;
  }

  .static-page-editor-head code,
  .static-page-field code,
  .static-page-language-hint code {
    padding: 2px 5px;
    border-radius: 5px;
    background: rgba(23, 105, 224, .08);
    color: #1556b5;
    font-size: .92em;
  }

  .static-page-form {
    padding: 24px;
  }

  .static-page-section {
    margin: 0 0 22px;
    padding: 20px;
    border: 1px solid var(--spf-line);
    border-radius: 13px;
    background: #fff;
  }

  .static-page-section-title {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 14px;
    flex-wrap: wrap;
    margin-bottom: 18px;
  }

  .static-page-section h3 {
    margin: 0;
    color: var(--spf-ink);
    font-size: 16px;
    line-height: 1.4;
  }

  .static-page-section > p,
  .static-page-section-title p {
    margin: 5px 0 0;
    color: var(--spf-muted);
    font-size: 13px;
    line-height: 1.6;
  }

  .static-page-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 16px;
  }

  .static-page-field {
    display: grid;
    gap: 7px;
  }

  .static-page-field.full {
    grid-column: 1 / -1;
  }

  .static-page-field label {
    color: #2c405b;
    font-size: 13px;
    font-weight: 800;
  }

  .static-page-field label span {
    color: var(--spf-muted);
    font-size: 11px;
    font-weight: 600;
  }

  .static-page-field input,
  .static-page-field textarea,
  .static-page-field select {
    width: 100%;
    border: 1px solid #cdd8e7;
    border-radius: 9px;
    background: #fff;
    color: var(--spf-ink);
    box-sizing: border-box;
    font: inherit;
    font-size: 14px;
    outline: none;
    transition: border-color .16s ease, box-shadow .16s ease, background-color .16s ease;
  }

  .static-page-field input,
  .static-page-field select {
    min-height: 44px;
    padding: 0 12px;
  }

  .static-page-field textarea {
    min-height: 105px;
    padding: 11px 12px;
    line-height: 1.6;
    resize: vertical;
  }

  .static-page-field textarea.code {
    min-height: 330px;
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
    font-size: 12px;
    line-height: 1.65;
  }

  .static-page-field input:focus,
  .static-page-field textarea:focus,
  .static-page-field select:focus {
    border-color: var(--spf-primary);
    box-shadow: 0 0 0 3px rgba(23, 105, 224, .12);
  }

  .static-page-field input[readonly] {
    background: #f6f8fb;
    color: var(--spf-muted);
    cursor: not-allowed;
  }

  .static-page-field small {
    color: var(--spf-muted);
    font-size: 12px;
    line-height: 1.55;
  }

  .static-page-url-preview {
    display: inline-flex;
    align-items: center;
    width: fit-content;
    max-width: 100%;
    min-height: 39px;
    padding: 0 11px;
    border: 1px solid #bcd4fb;
    border-radius: 8px;
    background: var(--spf-primary-soft);
    color: #1356b9;
    font-size: 13px;
    overflow-wrap: anywhere;
    text-decoration: none;
  }

  .static-page-language-block {
    margin: 0 0 22px;
    border: 1px solid var(--spf-line);
    border-radius: 13px;
    overflow: hidden;
    background: #fff;
  }

  .static-page-language-switcher {
    display: flex;
    align-items: center;
    gap: 7px;
    padding: 13px 15px;
    border-bottom: 1px solid var(--spf-line);
    background: #fbfcfe;
  }

  .static-page-language-switcher-label {
    margin-right: 5px;
    color: var(--spf-muted);
    font-size: 12px;
    font-weight: 800;
  }

  /*
  |----------------------------------------------------------------------
  | Language Tabs
  | These rules deliberately use !important because the shared admin CSS
  | styles every button globally.
  |----------------------------------------------------------------------
  */
  .static-page-language-switcher button.static-page-language-tab {
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    min-height: 36px !important;
    padding: 0 14px !important;
    border: 1px solid #d0d7de !important;
    border-radius: 6px !important;
    background-color: #ffffff !important;
    background-image: none !important;
    color: #181818 !important;
    font: inherit !important;
    font-size: 13px !important;
    font-weight: 700 !important;
    line-height: 1 !important;
    cursor: pointer !important;
    box-shadow: none !important;
    text-decoration: none !important;
    text-shadow: none !important;
    transition: background-color .16s ease, color .16s ease, border-color .16s ease !important;
  }

  .static-page-language-switcher button.static-page-language-tab:hover:not(.is-active),
  .static-page-language-switcher button.static-page-language-tab:focus:not(.is-active) {
    border-color: #b8c2cc !important;
    background-color: #ffffff !important;
    background-image: none !important;
    color: #181818 !important;
    box-shadow: none !important;
    outline: none !important;
  }

  .static-page-language-switcher button.static-page-language-tab.is-active,
  .static-page-language-switcher button.static-page-language-tab.is-active:hover,
  .static-page-language-switcher button.static-page-language-tab.is-active:focus {
    border-color: #16824b !important;
    background-color: #16824b !important;
    background-image: none !important;
    color: #ffffff !important;
    box-shadow: none !important;
    outline: none !important;
  }

  .static-page-language-panel {
    display: none;
    padding: 21px 20px;
    animation: staticPageFade .2s ease;
  }

  .static-page-language-panel.is-active {
    display: block;
  }

  @keyframes staticPageFade {
    from { opacity: 0; transform: translateY(4px); }
    to { opacity: 1; transform: translateY(0); }
  }

  .static-page-language-heading {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 14px;
    flex-wrap: wrap;
    margin: 0 0 18px;
  }

  .static-page-language-heading h3 {
    display: flex;
    align-items: center;
    gap: 8px;
    margin: 0;
    color: var(--spf-ink);
    font-size: 16px;
  }

  .static-page-language-dot {
    width: 9px;
    height: 9px;
    border-radius: 50%;
    background: var(--spf-primary);
  }

  .static-page-language-heading p {
    max-width: 650px;
    margin: 4px 0 0;
    color: var(--spf-muted);
    font-size: 13px;
    line-height: 1.6;
  }

  .static-page-language-hint {
    padding: 9px 11px;
    border: 1px solid #e0e8f4;
    border-radius: 8px;
    background: #f7faff;
    color: #53657b;
    font-size: 12px;
    line-height: 1.55;
  }

  .static-page-actions {
    position: sticky;
    bottom: 14px;
    z-index: 5;
    padding: 13px 15px;
    border: 1px solid #d8e1ec;
    border-radius: 12px;
    background: rgba(255, 255, 255, .94);
    box-shadow: 0 8px 20px rgba(16, 36, 62, .1);
    backdrop-filter: blur(10px);
  }

  .static-page-actions .btn-primary {
    min-width: 138px;
  }

  @media (max-width: 760px) {
    .static-page-form { padding: 16px; }
    .static-page-editor-head { padding: 18px 16px; }
    .static-page-section { padding: 16px; }
    .static-page-language-panel { padding: 17px 16px; }
    .static-page-grid { grid-template-columns: 1fr; }
    .static-page-form-top-actions,
    .static-page-actions { width: 100%; }
    .static-page-form-top-actions .btn,
    .static-page-actions .btn { flex: 1 1 auto; justify-content: center; }
    .static-page-language-switcher { align-items: stretch; flex-wrap: wrap; }
    .static-page-language-switcher-label { flex: 0 0 100%; }
    .static-page-language-switcher button.static-page-language-tab { flex: 1 1 0; }
  }
</style>

<div class="static-page-form-wrap">
  <div class="static-page-form-hero">
    <div>
      <div class="static-page-form-eyebrow">Content Management</div>
      <h1><?= $isEdit ? 'Edit Static Page' : 'Add New Static Page' ?></h1>
      <p>Set up the public page, then manage English and Bangla content from separate tabs for a cleaner editing experience.</p>
    </div>

    <div class="static-page-form-top-actions">
      <a class="btn btn-secondary" href="static-pages.php">← Page List</a>
      <?php if ($publicUrl !== '' && ($page['status'] ?? '') === 'active'): ?>
        <a class="btn btn-secondary" href="<?= e($publicUrl) ?>" target="_blank" rel="noopener noreferrer">Preview Page ↗</a>
      <?php endif; ?>
    </div>
  </div>

  <?php show_flash(); ?>

  <section class="static-page-editor">
    <div class="static-page-editor-head">
      <div class="static-page-editor-head-row">
        <div>
          <h2><?= $isEdit ? e((string) $page['title_en']) : 'New Page Details' ?></h2>
          <p>Use the page setup once, then choose a language tab to edit its page content and SEO details.</p>
        </div>
      </div>
    </div>

    <form class="static-page-form" method="post" action="static-page-form.php<?= $isEdit ? '?id=' . (int) $page['id'] : '' ?>">
      <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
      <input type="hidden" name="id" value="<?= (int) $page['id'] ?>">

      <section class="static-page-section">
        <div class="static-page-section-title">
          <div>
            <h3>Page Setup</h3>
            <p>Create the public URL and control whether visitors can access this page.</p>
          </div>
        </div>

        <div class="static-page-grid">
          <div class="static-page-field">
            <label for="slug">Page URL Slug</label>
            <input id="slug" name="slug" maxlength="100" value="<?= e((string) $page['slug']) ?>" <?= $isCorePage ? 'readonly' : '' ?> required>
            <small>Example: <code>about-us</code> becomes <code>/about-us</code>.</small>
          </div>

          <div class="static-page-field">
            <label for="status">Page Status</label>
            <select id="status" name="status">
              <option value="active" <?= ($page['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Active</option>
              <option value="inactive" <?= ($page['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
            </select>
            <small>Inactive pages return a public 404 response.</small>
          </div>

          <?php if ($publicUrl !== ''): ?>
            <div class="static-page-field full">
              <label>Public URL</label>
              <a class="static-page-url-preview" href="<?= e($publicUrl) ?>" target="_blank" rel="noopener noreferrer"><?= e($publicUrl) ?></a>
            </div>
          <?php endif; ?>
        </div>
      </section>

      <section class="static-page-language-block">
        <div class="static-page-language-switcher" role="tablist" aria-label="Content language">
          <span class="static-page-language-switcher-label">Content Language</span>
          <button class="static-page-language-tab is-active" type="button" id="language-tab-en" role="tab" aria-selected="true" aria-controls="language-panel-en" data-static-language="en">English</button>
          <button class="static-page-language-tab" type="button" id="language-tab-bn" role="tab" aria-selected="false" aria-controls="language-panel-bn" data-static-language="bn">বাংলা</button>
        </div>

        <div class="static-page-language-panel is-active" id="language-panel-en" role="tabpanel" aria-labelledby="language-tab-en" data-static-language-panel="en">
          <div class="static-page-language-heading">
            <div>
              <h3><span class="static-page-language-dot"></span>English Content</h3>
              <p>These fields are shown on the default English public URL.</p>
            </div>
            <div class="static-page-language-hint">Tokens: <code>%site_name%</code> <code>%support_email%</code> <code>%support_url%</code> <code>%privacy_url%</code> <code>%terms_url%</code></div>
          </div>

          <div class="static-page-grid">
            <div class="static-page-field">
              <label for="title_en">Page Title <span>Required</span></label>
              <input id="title_en" name="title_en" maxlength="255" value="<?= e((string) ($page['title_en'] ?? '')) ?>" required>
            </div>

            <div class="static-page-field">
              <label for="kicker_en">Hero Kicker</label>
              <input id="kicker_en" name="kicker_en" maxlength="255" value="<?= e((string) ($page['kicker_en'] ?? '')) ?>">
            </div>

            <div class="static-page-field full">
              <label for="intro_en">Hero Introduction</label>
              <textarea id="intro_en" name="intro_en"><?= e((string) ($page['intro_en'] ?? '')) ?></textarea>
            </div>

            <div class="static-page-field">
              <label for="meta_title_en">SEO Title <span>Optional</span></label>
              <input id="meta_title_en" name="meta_title_en" maxlength="255" value="<?= e((string) ($page['meta_title_en'] ?? '')) ?>">
              <small>When empty, the page title is used automatically.</small>
            </div>

            <div class="static-page-field">
              <label for="meta_description_en">SEO Description</label>
              <textarea id="meta_description_en" name="meta_description_en"><?= e((string) ($page['meta_description_en'] ?? '')) ?></textarea>
            </div>

            <?php
            admin_static_page_editor_field(
                'content_en',
                'content_en',
                (string) ($page['content_en'] ?? ''),
                'Page Content',
                'Use the toolbar for headings, formatting, lists, links, tables, images, source HTML and preview. The public page still applies server-side HTML safety rules.'
            );
            ?>
          </div>
        </div>

        <div class="static-page-language-panel" id="language-panel-bn" role="tabpanel" aria-labelledby="language-tab-bn" data-static-language-panel="bn" hidden>
          <div class="static-page-language-heading">
            <div>
              <h3><span class="static-page-language-dot"></span>Bangla Content</h3>
              <p>এই ফিল্ডগুলো <code>/bn/</code> public URL-এ দেখা যাবে। কোনো ফিল্ড খালি রাখলে English content fallback হিসেবে ব্যবহার হবে।</p>
            </div>
            <div class="static-page-language-hint">Tokens: <code>%site_name%</code> <code>%support_email%</code> <code>%support_url%</code> <code>%privacy_url%</code> <code>%terms_url%</code></div>
          </div>

          <div class="static-page-grid">
            <div class="static-page-field">
              <label for="title_bn">Page Title</label>
              <input id="title_bn" name="title_bn" maxlength="255" value="<?= e((string) ($page['title_bn'] ?? '')) ?>">
            </div>

            <div class="static-page-field">
              <label for="kicker_bn">Hero Kicker</label>
              <input id="kicker_bn" name="kicker_bn" maxlength="255" value="<?= e((string) ($page['kicker_bn'] ?? '')) ?>">
            </div>

            <div class="static-page-field full">
              <label for="intro_bn">Hero Introduction</label>
              <textarea id="intro_bn" name="intro_bn"><?= e((string) ($page['intro_bn'] ?? '')) ?></textarea>
            </div>

            <div class="static-page-field">
              <label for="meta_title_bn">SEO Title <span>Optional</span></label>
              <input id="meta_title_bn" name="meta_title_bn" maxlength="255" value="<?= e((string) ($page['meta_title_bn'] ?? '')) ?>">
              <small>খালি থাকলে Page Title ব্যবহার হবে।</small>
            </div>

            <div class="static-page-field">
              <label for="meta_description_bn">SEO Description</label>
              <textarea id="meta_description_bn" name="meta_description_bn"><?= e((string) ($page['meta_description_bn'] ?? '')) ?></textarea>
            </div>

            <?php
            admin_static_page_editor_field(
                'content_bn',
                'content_bn',
                (string) ($page['content_bn'] ?? ''),
                'Page Content',
                'Toolbar ব্যবহার করে heading, formatting, list, link, table, image, source HTML এবং preview যোগ করুন। Public page-এর server-side HTML safety rules প্রয়োগ হবে।'
            );
            ?>
          </div>
        </div>
      </section>

      <div class="static-page-actions">
        <button type="submit" class="btn btn-primary" name="save_static_page" value="1"><?= $isEdit ? 'Save Changes' : 'Create Page' ?></button>
        <a class="btn btn-secondary" href="static-pages.php">Cancel</a>
        <?php if ($publicUrl !== '' && ($page['status'] ?? '') === 'active'): ?>
          <a class="btn btn-secondary" href="<?= e($publicUrl) ?>" target="_blank" rel="noopener noreferrer">Preview Public Page</a>
        <?php endif; ?>
      </div>
    </form>
  </section>
</div>

<script src="<?= e(admin_static_page_editor_js_url()) ?>"></script>

<script>
(function () {
  function initialiseLanguageTabs() {
    var tabs = document.querySelectorAll('[data-static-language]');
    var panels = document.querySelectorAll('[data-static-language-panel]');

    if (!tabs.length || !panels.length) {
      return;
    }

    function setTabAppearance(tab, isActive) {
      /*
       * Shared admin CSS applies styles to all buttons. Inline priority
       * guarantees that only the selected language remains green.
       */
      tab.style.setProperty('background-color', isActive ? '#16824b' : '#ffffff', 'important');
      tab.style.setProperty('background-image', 'none', 'important');
      tab.style.setProperty('border-color', isActive ? '#16824b' : '#d0d7de', 'important');
      tab.style.setProperty('color', isActive ? '#ffffff' : '#181818', 'important');
      tab.style.setProperty('box-shadow', 'none', 'important');
    }

    function selectLanguage(language) {
      tabs.forEach(function (tab) {
        var isActive = tab.getAttribute('data-static-language') === language;

        tab.classList.toggle('is-active', isActive);
        tab.setAttribute('aria-selected', isActive ? 'true' : 'false');
        tab.setAttribute('aria-pressed', isActive ? 'true' : 'false');
        tab.tabIndex = isActive ? 0 : -1;

        setTabAppearance(tab, isActive);
      });

      panels.forEach(function (panel) {
        var isActive = panel.getAttribute('data-static-language-panel') === language;

        panel.classList.toggle('is-active', isActive);
        panel.hidden = !isActive;
      });
    }

    tabs.forEach(function (tab) {
      tab.addEventListener('click', function () {
        selectLanguage(tab.getAttribute('data-static-language'));
      });

      tab.addEventListener('keydown', function (event) {
        if (event.key !== 'ArrowLeft' && event.key !== 'ArrowRight') {
          return;
        }

        event.preventDefault();

        var currentIndex = Array.prototype.indexOf.call(tabs, tab);
        var nextIndex = event.key === 'ArrowRight'
          ? (currentIndex + 1) % tabs.length
          : (currentIndex - 1 + tabs.length) % tabs.length;

        tabs[nextIndex].focus();
        selectLanguage(tabs[nextIndex].getAttribute('data-static-language'));
      });
    });

    // English is always selected when the page first opens.
    selectLanguage('en');
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initialiseLanguageTabs);
  } else {
    initialiseLanguageTabs();
  }
}());
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
