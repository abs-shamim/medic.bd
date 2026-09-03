<?php
/**
 * Local rich text editor for Blog Post Content.
 * It uses contenteditable, source HTML mode, table/link/image controls and
 * uploads images to admin/blog-image-upload.php. No external editor library.
 */

require_once __DIR__ . '/../../includes/blog-functions.php';

if (!function_exists('blog_editor_field')) {
    function blog_editor_field(
        string $name,
        string $id,
        string $value = '',
        string $label = 'Content HTML',
        string $help = '',
        bool $readonly = false
    ): void {
        static $assetsPrinted = false;

        if (!$assetsPrinted) {
            $assetsPrinted = true;
            ?>
            <style>
              .blog-editor { overflow: hidden; border: 1px solid #cdd8e7; border-radius: 10px; background: #fff; }
              .blog-editor[aria-readonly="true"] { opacity: .78; }
              .blog-editor-top { display: flex; align-items: center; justify-content: space-between; gap: 10px; min-height: 48px; padding: 8px 10px; border-bottom: 1px solid #dfe7f1; background: #f7faff; }
              .blog-editor-modes { display: flex; flex-wrap: wrap; gap: 7px; }
              .blog-editor-mode { min-height: 32px; padding: 6px 10px; border: 1px solid #c7d9f3; border-radius: 7px; background: #fff; color: #134f9f; font: inherit; font-size: 12px; font-weight: 700; cursor: pointer; }
              .blog-editor-mode.is-active { color: #fff; background: #1769e0; border-color: #1769e0; }
              .blog-editor-count { color: #63748b; font-size: 12px; font-weight: 700; white-space: nowrap; }
              .blog-editor-toolbar { display: flex !important; flex-wrap: wrap !important; align-items: center; gap: 7px; width: 100% !important; min-width: 0 !important; overflow: visible !important; max-height: none !important; padding: 9px 10px; border-bottom: 1px solid #dfe7f1; background: #fff; }
              .blog-editor-toolbar select, .blog-editor-tool { flex: 0 0 auto; width: auto !important; min-width: 0 !important; min-height: 34px !important; margin: 0 !important; padding: 5px 9px !important; border: 1px solid #cdd8e7 !important; border-radius: 7px !important; background: #fff !important; color: #10243e !important; font: inherit !important; font-size: 13px !important; line-height: 1 !important; box-shadow: none !important; }
              .blog-editor-toolbar select { min-width: 105px !important; }
              .blog-editor-tool { cursor: pointer; font-weight: 700 !important; }
              .blog-editor-tool:hover, .blog-editor-tool:focus { border-color: #1769e0 !important; color: #0e58c8 !important; background: #edf5ff !important; outline: none; }
              .blog-editor-divider { width: 1px; height: 26px; flex: 0 0 1px; background: #dce6f3; }
              .blog-editor-canvas { min-height: 320px; padding: 16px; outline: none; color: #213547; font-size: 14px; line-height: 1.75; overflow-wrap: anywhere; }
              .blog-editor-canvas:empty::before { content: attr(data-placeholder); color: #8da1bd; pointer-events: none; }
              .blog-editor-canvas img { display: block; max-width: 100%; height: auto; margin: 16px 0; border-radius: 10px; }
              .blog-editor-canvas table { width: 100%; border-collapse: collapse; margin: 16px 0; }
              .blog-editor-canvas th, .blog-editor-canvas td { padding: 8px 10px; border: 1px solid #cdd8e7; text-align: left; }
              .blog-editor-source { display: none; width: 100% !important; min-height: 320px !important; margin: 0 !important; border: 0 !important; border-radius: 0 !important; resize: vertical; color: #162236; background: #fbfcff; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace !important; font-size: 12px !important; line-height: 1.65 !important; box-shadow: none !important; }
              .blog-editor.is-source .blog-editor-canvas { display: none; }
              .blog-editor.is-source .blog-editor-source { display: block; }
              .blog-editor-preview { display: none; min-height: 320px; padding: 16px; color: #213547; background: #fff; font-size: 14px; line-height: 1.75; overflow-wrap: anywhere; }
              .blog-editor-preview img { display: block; max-width: 100%; height: auto; margin: 16px 0; border-radius: 10px; }
              .blog-editor-preview table { width: 100%; border-collapse: collapse; margin: 16px 0; }
              .blog-editor-preview th, .blog-editor-preview td { padding: 8px 10px; border: 1px solid #cdd8e7; text-align: left; }
              .blog-editor.is-preview .blog-editor-canvas, .blog-editor.is-preview .blog-editor-source, .blog-editor.is-preview .blog-editor-toolbar { display: none; }
              .blog-editor.is-preview .blog-editor-preview { display: block; }
              .blog-editor-help { margin: 8px 0 0; color: #63748b; font-size: 12px; line-height: 1.55; }
              @media (max-width: 650px) { .blog-editor-top { align-items: flex-start; flex-direction: column; } .blog-editor-count { white-space: normal; } .blog-editor-toolbar { gap: 6px; } .blog-editor-toolbar select { min-width: 92px !important; } .blog-editor-divider { display: none; } }
            </style>
            <script>
            (function () {
              function wordCount(html) {
                var text = String(html || '').replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
                return text ? text.split(' ').length : 0;
              }
              function escapeHtml(value) {
                return String(value || '').replace(/[&<>"']/g, function (m) { return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'})[m]; });
              }
              function tableHtml() {
                return '<table><thead><tr><th>Heading 1</th><th>Heading 2</th></tr></thead><tbody><tr><td>Cell content</td><td>Cell content</td></tr><tr><td>Cell content</td><td>Cell content</td></tr></tbody></table><p><br></p>';
              }
              function initialiseEditor(root) {
                if (!root || root.dataset.initialised === '1') return;
                root.dataset.initialised = '1';
                var canvas = root.querySelector('.blog-editor-canvas');
                var source = root.querySelector('.blog-editor-source');
                var preview = root.querySelector('.blog-editor-preview');
                var store = root.querySelector('.blog-editor-store');
                var count = root.querySelector('.blog-editor-count');
                var csrf = root.getAttribute('data-csrf') || '';
                var readonly = root.getAttribute('aria-readonly') === 'true';
                var savedRange = null;

                function sync(from) {
                  if (from === 'source') {
                    if (canvas) canvas.innerHTML = source.value;
                    if (store) store.value = source.value;
                  } else {
                    var html = canvas ? canvas.innerHTML : '';
                    if (source) source.value = html;
                    if (store) store.value = html;
                  }
                  if (count) count.textContent = wordCount(store ? store.value : '') + ' words';
                }
                function setMode(mode) {
                  if (readonly) return;
                  root.classList.toggle('is-source', mode === 'source');
                  root.classList.toggle('is-preview', mode === 'preview');
                  root.querySelectorAll('[data-blog-mode]').forEach(function (button) {
                    button.classList.toggle('is-active', button.getAttribute('data-blog-mode') === mode);
                  });
                  if (mode === 'source') { sync('canvas'); source.focus(); }
                  if (mode === 'preview') { sync(root.classList.contains('is-source') ? 'source' : 'canvas'); preview.innerHTML = store.value || '<p>No content yet.</p>'; }
                  if (mode === 'edit') { sync(root.classList.contains('is-source') ? 'source' : 'canvas'); canvas.focus(); }
                }
                function rememberSelection() {
                  var selection = window.getSelection();
                  if (selection && selection.rangeCount && canvas && canvas.contains(selection.anchorNode)) savedRange = selection.getRangeAt(0).cloneRange();
                }
                function restoreSelection() {
                  if (!savedRange) return;
                  var selection = window.getSelection();
                  selection.removeAllRanges(); selection.addRange(savedRange);
                }
                function run(command, value) {
                  if (readonly || !canvas) return;
                  setMode('edit'); restoreSelection();
                  document.execCommand(command, false, value || null);
                  canvas.focus(); sync('canvas'); rememberSelection();
                }
                root.querySelectorAll('[data-blog-mode]').forEach(function (button) { button.addEventListener('click', function () { setMode(button.getAttribute('data-blog-mode')); }); });
                root.querySelectorAll('[data-command]').forEach(function (button) {
                  button.addEventListener('mousedown', function (event) { event.preventDefault(); });
                  button.addEventListener('click', function () {
                    var command = button.getAttribute('data-command');
                    if (command === 'createLink') { var url = window.prompt('Link URL'); if (url) run(command, url); return; }
                    if (command === 'unlink') { run(command); return; }
                    if (command === 'insertTable') { setMode('edit'); restoreSelection(); document.execCommand('insertHTML', false, tableHtml()); sync('canvas'); return; }
                    run(command, button.getAttribute('data-value') || '');
                  });
                });
                root.querySelectorAll('[data-select-command]').forEach(function (select) { select.addEventListener('change', function () { run(select.getAttribute('data-select-command'), select.value); }); });
                var uploadInput = root.querySelector('.blog-editor-upload');
                if (uploadInput) uploadInput.addEventListener('change', function () {
                  var file = uploadInput.files && uploadInput.files[0];
                  if (!file || readonly) return;
                  var form = new FormData(); form.append('image', file); form.append('csrf_token', csrf);
                  uploadInput.disabled = true;
                  fetch('blog-image-upload.php', { method: 'POST', body: form, credentials: 'same-origin' })
                    .then(function (response) { return response.json(); })
                    .then(function (payload) {
                      if (!payload || !payload.ok || !payload.url) throw new Error((payload && payload.message) || 'Upload failed.');
                      setMode('edit'); restoreSelection();
                      var alt = window.prompt('Image alt text (recommended for SEO)', '') || '';
                      document.execCommand('insertHTML', false, '<img src="' + escapeHtml(payload.url) + '" alt="' + escapeHtml(alt) + '" loading="lazy">');
                      sync('canvas');
                    })
                    .catch(function (error) { window.alert(error.message || 'Image upload failed.'); })
                    .finally(function () { uploadInput.value = ''; uploadInput.disabled = false; });
                });
                if (canvas) { canvas.addEventListener('input', function () { sync('canvas'); }); canvas.addEventListener('keyup', rememberSelection); canvas.addEventListener('mouseup', rememberSelection); canvas.addEventListener('blur', function () { sync('canvas'); }); }
                if (source) source.addEventListener('input', function () { sync('source'); });
                var form = root.closest('form');
                if (form) form.addEventListener('submit', function () { sync(root.classList.contains('is-source') ? 'source' : 'canvas'); });
                sync('canvas');
              }
              function boot() { document.querySelectorAll('.blog-editor').forEach(initialiseEditor); }
              if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot); else boot();
            }());
            </script>
            <?php
        }

        $safeHtml = blog_sanitize_html($value);
        $token = blog_csrf_token();
        ?>
        <div class="blog-editor" data-csrf="<?= e($token) ?>" aria-readonly="<?= $readonly ? 'true' : 'false' ?>">
          <div class="blog-editor-top">
            <div class="blog-editor-modes">
              <button type="button" class="blog-editor-mode is-active" data-blog-mode="edit" <?= $readonly ? 'disabled' : '' ?>>✎ Editor</button>
              <button type="button" class="blog-editor-mode" data-blog-mode="source" <?= $readonly ? 'disabled' : '' ?>>&lt;/&gt; Source</button>
              <button type="button" class="blog-editor-mode" data-blog-mode="preview" <?= $readonly ? 'disabled' : '' ?>>◉ Preview</button>
            </div>
            <span class="blog-editor-count">0 words</span>
          </div>
          <?php if (!$readonly): ?>
          <div class="blog-editor-toolbar" aria-label="Content editor toolbar">
            <select data-select-command="formatBlock" aria-label="Heading format">
              <option value="p">Normal</option><option value="h2">Heading 2</option><option value="h3">Heading 3</option><option value="h4">Heading 4</option><option value="blockquote">Quote</option><option value="pre">Code block</option>
            </select>
            <button type="button" class="blog-editor-tool" data-command="bold"><b>B</b></button><button type="button" class="blog-editor-tool" data-command="italic"><i>I</i></button><button type="button" class="blog-editor-tool" data-command="underline"><u>U</u></button><button type="button" class="blog-editor-tool" data-command="strikeThrough">S</button>
            <span class="blog-editor-divider"></span>
            <button type="button" class="blog-editor-tool" data-command="insertUnorderedList">• List</button><button type="button" class="blog-editor-tool" data-command="insertOrderedList">1. List</button><button type="button" class="blog-editor-tool" data-command="outdent">←</button><button type="button" class="blog-editor-tool" data-command="indent">→</button>
            <span class="blog-editor-divider"></span>
            <button type="button" class="blog-editor-tool" data-command="justifyLeft">≡</button><button type="button" class="blog-editor-tool" data-command="justifyCenter">≣</button><button type="button" class="blog-editor-tool" data-command="justifyRight">☰</button>
            <span class="blog-editor-divider"></span>
            <button type="button" class="blog-editor-tool" data-command="createLink">Link</button><button type="button" class="blog-editor-tool" data-command="unlink">Unlink</button><button type="button" class="blog-editor-tool" data-command="insertTable">Table</button>
            <label class="blog-editor-tool" title="Upload image">Image<input class="blog-editor-upload" type="file" accept="image/jpeg,image/png,image/webp" hidden></label>
            <button type="button" class="blog-editor-tool" data-command="removeFormat">Clear</button>
          </div>
          <?php endif; ?>
          <div id="<?= e($id) ?>_canvas" class="blog-editor-canvas" contenteditable="<?= $readonly ? 'false' : 'true' ?>" data-placeholder="Write blog content here..." role="textbox" aria-multiline="true"><?= $safeHtml ?></div>
          <textarea id="<?= e($id) ?>_source" class="blog-editor-source" <?= $readonly ? 'readonly' : '' ?>><?= e($safeHtml) ?></textarea>
          <div class="blog-editor-preview"></div>
          <textarea class="blog-editor-store" id="<?= e($id) ?>" name="<?= e($name) ?>" hidden><?= e($safeHtml) ?></textarea>
        </div>
        <?php if ($label !== ''): ?><div class="blog-editor-help"><strong><?= e($label) ?>.</strong> <?= e($help !== '' ? $help : 'Use the toolbar for headings, links, tables, images, source HTML and preview.') ?></div><?php endif; ?>
        <?php
    }
}
