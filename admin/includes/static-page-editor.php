<?php
/**
 * Reusable Static Page Rich Editor Field
 *
 * The editor markup stays outside static-page-form.php.
 * Behaviour is handled by assets/js/static-page-editor.js and styles by
 * assets/css/static-page-editor.css.
 */

if (!function_exists('admin_static_page_editor_css_url')) {
    function admin_static_page_editor_css_url(): string
    {
        return 'assets/css/static-page-editor.css?v=20260706';
    }
}

if (!function_exists('admin_static_page_editor_js_url')) {
    function admin_static_page_editor_js_url(): string
    {
        return 'assets/js/static-page-editor.js?v=20260706';
    }
}

if (!function_exists('admin_static_page_editor_field')) {
    /**
     * Render one editable HTML content field.
     *
     * @param string $id        Unique HTML id for the hidden form field.
     * @param string $name      Form field name saved by PHP.
     * @param string $value     Existing HTML content from the database.
     * @param string $label     Visible field label.
     * @param string $helpText  Optional help text under the editor.
     */
    function admin_static_page_editor_field(
        string $id,
        string $name,
        string $value,
        string $label = 'Page Content',
        string $helpText = ''
    ): void {
        $safeId = preg_replace('/[^a-zA-Z0-9_-]/', '-', $id);
        $editorId = 'static-editor-' . $safeId;

        if ($helpText === '') {
            $helpText = 'Use the toolbar for headings, formatting, lists, links, tables, images, source HTML and preview. The public page still applies server-side HTML safety rules.';
        }
        ?>
        <div class="static-page-field full">
            <label for="<?= e($id) ?>"><?= e($label) ?></label>

            <textarea
                id="<?= e($id) ?>"
                class="static-page-editor-source"
                name="<?= e($name) ?>"
                data-static-editor-source
                hidden
            ><?= e($value) ?></textarea>

            <div
                id="<?= e($editorId) ?>"
                class="static-page-rich-editor"
                data-static-page-editor
                data-source-id="<?= e($id) ?>"
                data-editor-label="<?= e($label) ?>"
            ></div>

            <small><?= e($helpText) ?></small>
        </div>
        <?php
    }
}
