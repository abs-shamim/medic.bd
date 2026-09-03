<?php
/**
 * Summernote Blog Editor
 *
 * Lightweight WYSIWYG editor for bilingual blog content.
 * It uses Summernote Lite, so this admin area does not need Bootstrap.
 */

if (!function_exists('summernote_blog_editor_assets')) {
    function summernote_blog_editor_assets(): void
    {
        static $loaded = false;

        if ($loaded) {
            return;
        }

        $loaded = true;
        ?>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/summernote/0.8.20/summernote-lite.min.css">
        <style>
          .sn-blog-editor{overflow:hidden;border:1px solid #cdd8e7;border-radius:9px;background:#fff}
          .sn-blog-editor-head{display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;padding:9px 11px;border-bottom:1px solid #d8e1ec;background:#f7faff}
          .sn-blog-editor-head strong{color:#243b53;font-size:13px}.sn-blog-editor-head span{color:#64748b;font-size:11px}
          .sn-blog-editor .note-editor{border:0!important;border-radius:0!important}
          .sn-blog-editor .note-toolbar{padding:8px!important;border:0!important;border-bottom:1px solid #d8e1ec!important;background:#fff!important}
          .sn-blog-editor .note-toolbar .note-btn-group{display:inline-flex!important;margin:0 5px 6px 0!important;vertical-align:top!important}
          .sn-blog-editor .note-btn{min-width:32px!important;min-height:32px!important;border:1px solid #cdd8e7!important;border-radius:6px!important;color:#243b53!important;background:#fff!important;box-shadow:none!important;font-size:12px!important}
          .sn-blog-editor .note-btn:hover,.sn-blog-editor .note-btn:focus{border-color:#0969da!important;color:#0969da!important;background:#f4f9ff!important}
          .sn-blog-editor .note-dropdown-menu{z-index:99999!important}
          .sn-blog-editor .note-editing-area .note-editable{min-height:360px!important;padding:17px!important;color:#24292f!important;background:#fff!important;font-size:15px!important;font-weight:500!important;line-height:1.72!important}
          .sn-blog-editor .note-editing-area .note-editable *{font-weight:500!important}
          .sn-blog-editor .note-editing-area .note-editable p{margin:0 0 14px}
          .sn-blog-editor .note-editing-area .note-editable img{max-width:100%;height:auto}
          .sn-blog-editor .note-statusbar{border-top:1px solid #d8e1ec!important;background:#fafcff!important}
          .sn-blog-editor .note-status-output{color:#64748b!important;font-size:11px!important}
          .sn-blog-editor .note-modal-content{border-radius:10px}
          .sn-blog-editor .note-modal .note-btn-primary{background:#2da44e!important;border-color:#2da44e!important;color:#fff!important}
          @media(max-width:680px){.sn-blog-editor .note-editing-area .note-editable{min-height:300px!important;padding:13px!important}.sn-blog-editor .note-toolbar{padding:7px!important}}
        </style>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/summernote/0.8.20/summernote-lite.min.js"></script>
        <?php
    }
}

if (!function_exists('summernote_blog_editor_field')) {
    function summernote_blog_editor_field(
        string $name,
        string $id,
        string $html = '',
        string $label = 'Content Editor',
        string $help = ''
    ): void {
        $name = preg_replace('/[^a-zA-Z0-9_]/', '', $name);
        $id = preg_replace('/[^a-zA-Z0-9_-]/', '', $id);

        if ($name === '' || $id === '') {
            return;
        }
        ?>
        <div class="sn-blog-editor">
          <div class="sn-blog-editor-head">
            <strong><?= e($label) ?></strong>
            <?php if (trim($help) !== ''): ?><span><?= e($help) ?></span><?php endif; ?>
          </div>
          <textarea
            id="<?= e($id) ?>"
            name="<?= e($name) ?>"
            class="summernote-blog-field"
            data-upload-url="summernote-image-upload.php"
          ><?= e($html) ?></textarea>
        </div>
        <?php
    }
}

if (!function_exists('summernote_blog_editor_script')) {
    function summernote_blog_editor_script(): void
    {
        static $printed = false;

        if ($printed) {
            return;
        }

        $printed = true;
        ?>
        <script>
        (function ($) {
          'use strict';

          function csrfToken() {
            var input = document.querySelector('input[name="csrf_token"]');
            return input ? input.value : '';
          }

          function uploadImage(file, $editor) {
            var uploadUrl = $editor.data('upload-url') || 'summernote-image-upload.php';
            var data = new FormData();

            data.append('image', file);
            data.append('csrf_token', csrfToken());

            $.ajax({
              url: uploadUrl,
              method: 'POST',
              data: data,
              processData: false,
              contentType: false,
              dataType: 'json'
            }).done(function (response) {
              if (response && response.ok && response.url) {
                $editor.summernote('insertImage', response.url, function ($image) {
                  $image.attr('alt', '');
                  $image.attr('loading', 'lazy');
                });
                return;
              }

              window.alert((response && response.message) ? response.message : 'Image upload failed.');
            }).fail(function () {
              window.alert('Image upload failed. Please check the image size, format and upload permission.');
            });
          }

          function initialiseEditor(element) {
            var $editor = $(element);

            if ($editor.data('summernote')) {
              return;
            }

            $editor.summernote({
              height: 420,
              minHeight: 300,
              maxHeight: null,
              focus: false,
              dialogsInBody: true,
              disableDragAndDrop: false,
              placeholder: 'Write your blog content here...',
              toolbar: [
                ['style', ['style']],
                ['font', ['bold', 'italic', 'underline', 'strikethrough', 'clear']],
                ['fontname', ['fontname']],
                ['fontsize', ['fontsize']],
                ['color', ['color']],
                ['para', ['ul', 'ol', 'paragraph']],
                ['table', ['table']],
                ['insert', ['link', 'picture', 'video']],
                ['view', ['fullscreen', 'codeview', 'help']]
              ],
              styleTags: ['p', 'blockquote', 'pre', 'h2', 'h3', 'h4', 'h5', 'h6'],
              fontSizes: [8, 10, 12, 14, 16, 18, 20],
              callbacks: {
                onImageUpload: function (files) {
                  for (var index = 0; index < files.length; index += 1) {
                    uploadImage(files[index], $editor);
                  }
                }
              }
            });
          }

          function initialiseVisibleEditors() {
            $('.summernote-blog-field').each(function () {
              if ($(this).is(':visible')) {
                initialiseEditor(this);
              }
            });
          }

          $(function () {
            initialiseVisibleEditors();

            $('.bpf-tab').on('click', function () {
              window.setTimeout(initialiseVisibleEditors, 20);
            });

            $('#blogPostForm').on('submit', function () {
              $('.summernote-blog-field').each(function () {
                var $editor = $(this);

                if ($editor.data('summernote')) {
                  $editor.val($editor.summernote('code'));
                }
              });
            });
          });
        }(jQuery));
        </script>
        <?php
    }
}
