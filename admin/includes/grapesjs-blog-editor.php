<?php
/**
 * GrapesJS Blog Editor
 *
 * Visual page-builder field for English and Bangla blog content.
 * HTML and generated CSS are saved in separate database columns so public
 * blog pages can render the layout without relying on inline styles.
 */

if (!function_exists('grapesjs_blog_editor_assets')) {
    function grapesjs_blog_editor_assets(): void
    {
        static $loaded = false;

        if ($loaded) {
            return;
        }

        $loaded = true;
        ?>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/grapesjs/0.23.2/css/grapes.min.css">
        <style>
          .gjs-blog-editor{overflow:hidden;border:1px solid #cdd8e7;border-radius:9px;background:#fff}
          .gjs-blog-editor-head{display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;padding:9px 10px;border-bottom:1px solid #d8e1ec;background:#f7faff}
          .gjs-blog-editor-head strong{color:#243b53;font-size:13px}.gjs-blog-editor-head span{color:#64748b;font-size:11px}
          .gjs-blog-actions{display:flex;align-items:center;gap:6px;flex-wrap:wrap}
          .gjs-blog-action{min-height:31px;padding:5px 9px;border:1px solid #cdd8e7;border-radius:6px;color:#243b53;background:#fff;font:inherit;font-size:12px;font-weight:700;cursor:pointer}
          .gjs-blog-action:hover{border-color:#0969da;color:#0969da;background:#f4f9ff}
          .gjs-blog-builder{display:grid;grid-template-columns:168px minmax(0,1fr);min-height:590px}
          .gjs-blog-blocks{overflow:auto;padding:10px;border-right:1px solid #d8e1ec;background:#fbfcfe}
          .gjs-blog-blocks-title{display:block;margin:0 0 9px;color:#64748b;font-size:10px;font-weight:800;letter-spacing:.07em;text-transform:uppercase}
          .gjs-blog-canvas{min-width:0;min-height:590px}
          .gjs-blog-canvas .gjs-one-bg{background:#fff}
          .gjs-blog-canvas .gjs-two-color{color:#243b53}
          .gjs-blog-canvas .gjs-four-color,.gjs-blog-canvas .gjs-four-color-h:hover{color:#0969da}
          .gjs-blog-canvas .gjs-pn-panel{position:static}
          .gjs-blog-canvas .gjs-pn-options{right:0}
          .gjs-blog-style{display:none}
          .gjs-blog-source{margin:0;border-top:1px solid #d8e1ec;background:#fff}
          .gjs-blog-source summary{padding:9px 11px;color:#57606a;cursor:pointer;font-size:12px;font-weight:700}
          .gjs-blog-source textarea{display:block;width:calc(100% - 20px);min-height:170px;margin:0 10px 10px;padding:10px;border:1px solid #cdd8e7;border-radius:7px;color:#24292f;background:#fff;font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,monospace;font-size:12px;line-height:1.6;resize:vertical}
          .gjs-blog-editor.is-ready .gjs-blog-source:not([open]){display:none}
          .gjs-blog-editor.is-fallback .gjs-blog-builder{display:none}
          .gjs-blog-editor.is-fallback .gjs-blog-source{display:block}
          .gjs-blog-editor .gjs-block{width:100%;min-height:0;margin:0 0 8px;padding:8px 5px;border:1px solid #d8e1ec;border-radius:6px;color:#243b53;background:#fff;box-shadow:none;font-size:11px}
          .gjs-blog-editor .gjs-block:hover{border-color:#0969da;color:#0969da}
          .gjs-blog-editor .gjs-block-label{font-size:11px}
          @media(max-width:850px){.gjs-blog-builder{grid-template-columns:1fr}.gjs-blog-blocks{border-right:0;border-bottom:1px solid #d8e1ec}.gjs-blog-canvas{min-height:520px}}
        </style>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/grapesjs/0.23.2/grapes.min.js" defer></script>
        <?php
    }
}

if (!function_exists('grapesjs_blog_editor_field')) {
    function grapesjs_blog_editor_field(
        string $name,
        string $id,
        string $html = '',
        string $css = '',
        string $label = 'Visual Content Builder',
        string $help = ''
    ): void {
        $name = preg_replace('/[^a-zA-Z0-9_]/', '', $name);
        $id = preg_replace('/[^a-zA-Z0-9_-]/', '', $id);

        if ($name === '' || $id === '') {
            return;
        }

        $cssName = $name . '_css';
        ?>
        <div
          class="gjs-blog-editor"
          data-grapes-blog-editor="<?= e($id) ?>"
          data-source-id="<?= e($id . '_source') ?>"
          data-css-id="<?= e($id . '_css') ?>"
          data-stage-id="<?= e($id . '_stage') ?>"
          data-blocks-id="<?= e($id . '_blocks') ?>"
          data-style-id="<?= e($id . '_styles') ?>"
        >
          <div class="gjs-blog-editor-head">
            <div>
              <strong><?= e($label) ?></strong>
              <?php if (trim($help) !== ''): ?><span> · <?= e($help) ?></span><?php endif; ?>
            </div>
            <div class="gjs-blog-actions" aria-label="Visual editor controls">
              <button class="gjs-blog-action" type="button" data-gjs-action="undo">Undo</button>
              <button class="gjs-blog-action" type="button" data-gjs-action="redo">Redo</button>
              <button class="gjs-blog-action" type="button" data-gjs-action="desktop">Desktop</button>
              <button class="gjs-blog-action" type="button" data-gjs-action="mobile">Mobile</button>
              <button class="gjs-blog-action" type="button" data-gjs-action="preview">Preview</button>
              <button class="gjs-blog-action" type="button" data-gjs-action="image">Image URL</button>
              <button class="gjs-blog-action" type="button" data-gjs-action="source">HTML</button>
            </div>
          </div>

          <div class="gjs-blog-builder">
            <aside class="gjs-blog-blocks">
              <span class="gjs-blog-blocks-title">Drag blocks</span>
              <div id="<?= e($id . '_blocks') ?>"></div>
            </aside>
            <div class="gjs-blog-canvas" id="<?= e($id . '_stage') ?>"></div>
            <div class="gjs-blog-style" id="<?= e($id . '_styles') ?>"></div>
          </div>

          <details class="gjs-blog-source">
            <summary>HTML source fallback</summary>
            <textarea id="<?= e($id . '_source') ?>" name="<?= e($name) ?>"><?= e($html) ?></textarea>
          </details>

          <input type="hidden" id="<?= e($id . '_css') ?>" name="<?= e($cssName) ?>" value="<?= e($css) ?>">
        </div>
        <?php
    }
}

if (!function_exists('grapesjs_blog_editor_script')) {
    function grapesjs_blog_editor_script(): void
    {
        static $printed = false;

        if ($printed) {
            return;
        }

        $printed = true;
        ?>
        <script>
        (function () {
          'use strict';

          var editors = {};

          function escapeHtml(value) {
            return String(value || '')
              .replace(/&/g, '&amp;')
              .replace(/</g, '&lt;')
              .replace(/>/g, '&gt;')
              .replace(/"/g, '&quot;')
              .replace(/'/g, '&#039;');
          }

          function byId(id) {
            return document.getElementById(id);
          }

          function blocks() {
            return [
              {
                id: 'gjs-blog-paragraph',
                label: 'Paragraph',
                category: 'Content',
                content: '<p>Write your paragraph here. Replace this sample text with your own content.</p>'
              },
              {
                id: 'gjs-blog-heading',
                label: 'Heading',
                category: 'Content',
                content: '<h2>Section heading</h2><p>Add supporting text here.</p>'
              },
              {
                id: 'gjs-blog-callout',
                label: 'Callout',
                category: 'Content',
                content: '<div class="blog-callout"><h3>Helpful note</h3><p>Add an important message, tip or summary here.</p></div>',
                style: '.blog-callout{padding:20px;border-left:4px solid #1769e0;background:#f4f8ff}.blog-callout h3{margin:0 0 8px}.blog-callout p{margin:0}'
              },
              {
                id: 'gjs-blog-quote',
                label: 'Quote',
                category: 'Content',
                content: '<blockquote>Insert a meaningful quotation or a short expert statement here.</blockquote>'
              },
              {
                id: 'gjs-blog-list',
                label: 'List',
                category: 'Content',
                content: '<ul><li>First point</li><li>Second point</li><li>Third point</li></ul>'
              },
              {
                id: 'gjs-blog-image',
                label: 'Image',
                category: 'Media',
                content: '<figure><img src="https://via.placeholder.com/1200x675?text=Blog+Image" alt="Describe this image"><figcaption>Optional image caption</figcaption></figure>'
              },
              {
                id: 'gjs-blog-button',
                label: 'Button link',
                category: 'Content',
                content: '<a class="blog-button" href="#">Read more</a>',
                style: '.blog-button{display:inline-block;padding:11px 16px;background:#1769e0;color:#ffffff;text-decoration:none;border-radius:6px}'
              },
              {
                id: 'gjs-blog-columns',
                label: 'Two columns',
                category: 'Layout',
                content: '<div class="blog-row"><div class="blog-column"><h3>Left heading</h3><p>Add text here.</p></div><div class="blog-column"><h3>Right heading</h3><p>Add text here.</p></div></div>',
                style: '.blog-row{display:flex;gap:24px}.blog-column{flex:1 1 0}@media(max-width:640px){.blog-row{display:block}.blog-column{margin-bottom:18px}}'
              },
              {
                id: 'gjs-blog-divider',
                label: 'Divider',
                category: 'Layout',
                content: '<hr>'
              },
              {
                id: 'gjs-blog-table',
                label: 'Table',
                category: 'Content',
                content: '<table><thead><tr><th>Item</th><th>Details</th></tr></thead><tbody><tr><td>Example</td><td>Add your value</td></tr></tbody></table>'
              }
            ];
          }

          function createEditor(root) {
            var key = root.getAttribute('data-grapes-blog-editor');

            if (!key || editors[key]) {
              return editors[key] || null;
            }

            var source = byId(root.getAttribute('data-source-id'));
            var cssInput = byId(root.getAttribute('data-css-id'));
            var stageId = root.getAttribute('data-stage-id');
            var blocksId = root.getAttribute('data-blocks-id');
            var stylesId = root.getAttribute('data-style-id');
            var sourceDetails = root.querySelector('.gjs-blog-source');

            if (!source || !cssInput || !stageId || !blocksId || !stylesId || !window.grapesjs) {
              root.classList.add('is-fallback');
              return null;
            }

            try {
              var editor = window.grapesjs.init({
                container: '#' + stageId,
                height: '590px',
                fromElement: false,
                storageManager: false,
                components: source.value || '<p>Start writing your article here.</p>',
                style: cssInput.value || '',
                blockManager: {
                  appendTo: '#' + blocksId,
                  blocks: blocks()
                },
                styleManager: {
                  appendTo: '#' + stylesId,
                  sectors: [
                    {
                      name: 'Layout',
                      open: false,
                      buildProps: ['display', 'position', 'top', 'right', 'bottom', 'left', 'width', 'height', 'margin', 'padding'],
                      properties: [
                        { property: 'display', type: 'select', defaults: 'block', options: [
                          { value: 'block', name: 'Block' },
                          { value: 'flex', name: 'Flex' },
                          { value: 'inline-block', name: 'Inline block' }
                        ] }
                      ]
                    },
                    {
                      name: 'Typography',
                      open: true,
                      buildProps: ['font-family', 'font-size', 'font-weight', 'letter-spacing', 'color', 'line-height', 'text-align', 'text-decoration'],
                      properties: [
                        { property: 'font-family', type: 'select', options: [
                          { value: 'Arial, Helvetica, sans-serif', name: 'Arial' },
                          { value: 'Georgia, serif', name: 'Georgia' },
                          { value: 'Tahoma, sans-serif', name: 'Tahoma' },
                          { value: '"Noto Sans Bengali", sans-serif', name: 'Noto Sans Bengali' }
                        ] }
                      ]
                    },
                    {
                      name: 'Decoration',
                      open: false,
                      buildProps: ['background-color', 'border', 'border-radius', 'box-shadow', 'opacity']
                    }
                  ]
                },
                deviceManager: {
                  devices: [
                    { name: 'Desktop', width: '' },
                    { name: 'Tablet', width: '768px', widthMedia: '900px' },
                    { name: 'Mobile', width: '375px', widthMedia: '480px' }
                  ]
                },
                assetManager: {
                  upload: false,
                  autoAdd: 0,
                  assets: []
                },
                selectorManager: {
                  componentFirst: true
                }
              });

              editors[key] = {
                editor: editor,
                root: root,
                source: source,
                css: cssInput,
                sourceDetails: sourceDetails
              };

              root.classList.add('is-ready');
              if (sourceDetails) {
                sourceDetails.open = false;
              }

              root.querySelectorAll('[data-gjs-action]').forEach(function (button) {
                button.addEventListener('click', function () {
                  var action = button.getAttribute('data-gjs-action');
                  var record = editors[key];

                  if (!record) {
                    return;
                  }

                  if (action === 'undo') {
                    record.editor.UndoManager.undo();
                  } else if (action === 'redo') {
                    record.editor.UndoManager.redo();
                  } else if (action === 'desktop') {
                    record.editor.setDevice('Desktop');
                  } else if (action === 'mobile') {
                    record.editor.setDevice('Mobile');
                  } else if (action === 'preview') {
                    record.editor.runCommand('preview');
                  } else if (action === 'image') {
                    var imageUrl = window.prompt('Paste a public image URL or a site-relative image path.');
                    if (imageUrl && /^(https?:\/\/|\/|\.\.?\/)/i.test(imageUrl.trim())) {
                      record.editor.addComponents(
                        '<figure><img src="' + escapeHtml(imageUrl.trim()) + '" alt=""><figcaption>Optional image caption</figcaption></figure>'
                      );
                    }
                  } else if (action === 'source' && record.sourceDetails) {
                    if (record.sourceDetails.open) {
                      record.editor.setComponents(record.source.value || '<p></p>');
                      record.sourceDetails.open = false;
                    } else {
                      sync(record);
                      record.sourceDetails.open = true;
                      window.setTimeout(function () {
                        record.source.focus();
                      }, 30);
                    }
                  }
                });
              });

              if (sourceDetails) {
                sourceDetails.addEventListener('toggle', function () {
                  if (sourceDetails.open) {
                    sync(editors[key]);
                  }
                });
              }

              return editors[key];
            } catch (error) {
              root.classList.add('is-fallback');
              return null;
            }
          }

          function sync(record) {
            if (!record || !record.editor || (record.sourceDetails && record.sourceDetails.open)) {
              return;
            }

            record.source.value = record.editor.getHtml();
            record.css.value = record.editor.getCss();
          }

          function bootVisibleEditors() {
            document.querySelectorAll('[data-grapes-blog-editor]').forEach(function (root) {
              if (root.offsetParent !== null) {
                createEditor(root);
              }
            });
          }

          document.addEventListener('DOMContentLoaded', function () {
            if (!window.grapesjs) {
              document.querySelectorAll('[data-grapes-blog-editor]').forEach(function (root) {
                root.classList.add('is-fallback');
              });
              return;
            }

            bootVisibleEditors();

            document.querySelectorAll('.bpf-tab').forEach(function (tab) {
              tab.addEventListener('click', function () {
                window.setTimeout(bootVisibleEditors, 30);
              });
            });

            var form = document.getElementById('blogPostForm');
            if (form) {
              form.addEventListener('submit', function () {
                Object.keys(editors).forEach(function (key) {
                  sync(editors[key]);
                });
              });
            }
          });
        }());
        </script>
        <?php
    }
}
