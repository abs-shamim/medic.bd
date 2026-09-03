<?php
/*
|--------------------------------------------------------------------------
| Blog Post Form Bootstrap
|--------------------------------------------------------------------------
| This page is safe to load on PHP 7.1+ and provides clear diagnostics when
| a required blog-module file was not copied to the expected project path.
*/
$blogFunctionsFile = __DIR__ . '/../includes/blog-functions.php';

if (!is_readable($blogFunctionsFile)) {
    http_response_code(500);
    exit('Blog module files are incomplete. Please upload includes/blog-functions.php.');
}

require_once $blogFunctionsFile;

if (!function_exists('require_admin')) {
    http_response_code(500);
    exit('Admin authentication helpers could not be loaded.');
}

require_admin();

if (!function_exists('blog_ensure_tables')) {
    http_response_code(500);
    exit('Blog module helpers could not be loaded.');
}

blog_ensure_tables();

$postId = max(0, (int) ($_GET['id'] ?? $_POST['id'] ?? 0));
$requiredPermission = $postId > 0 ? 'doctors.edit' : 'doctors.create';

if (function_exists('require_admin_permission')) {
    require_admin_permission($requiredPermission);
} elseif (function_exists('admin_can') && !admin_can($requiredPermission)) {
    http_response_code(403);
    exit('Access denied.');
}

$post = [
    'id' => 0, 'category_id' => 0, 'slug' => '', 'title_en' => '', 'title_bn' => '',
    'excerpt_en' => '', 'excerpt_bn' => '', 'content_en' => '', 'content_bn' => '',
    'featured_image' => '', 'image_alt_en' => '', 'image_alt_bn' => '',
    'author_name' => trim((string) ($_SESSION['admin_name'] ?? '')), 'status' => 'draft',
    'is_featured' => 0, 'meta_title_en' => '', 'meta_title_bn' => '',
    'meta_description_en' => '', 'meta_description_bn' => '',
    'meta_keywords_en' => '', 'meta_keywords_bn' => '', 'published_at' => null,
];

if ($postId > 0) {
    try {
        $statement = $pdo->prepare('SELECT * FROM blog_posts WHERE id = :id LIMIT 1');
        $statement->execute([':id' => $postId]);
        $loaded = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($loaded)) {
            flash('error', 'Blog post was not found.');
            redirect('blog-posts.php');
        }
        $post = array_merge($post, $loaded);
    } catch (Throwable $exception) {
        flash('error', 'Blog post could not be loaded.');
        redirect('blog-posts.php');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!blog_verify_csrf($_POST['csrf_token'] ?? null)) {
        flash('error', 'Security check failed. Please try again.');
    } else {
        $featuredImage = trim((string) ($_POST['old_featured_image'] ?? ''));
        $uploadedImage = function_exists('upload_image')
            ? upload_image('featured_image_upload', 'blog')
            : null;

        if ($uploadedImage !== null) {
            $featuredImage = $uploadedImage;
        }
        if (trim((string) ($_POST['featured_image'] ?? '')) !== '') {
            $featuredImage = trim((string) $_POST['featured_image']);
        }

        $input = $_POST;
        $input['featured_image'] = $featuredImage;
        $result = blog_save_post($input, $postId);
        $post = array_merge($post, $result['data']);
        $post['id'] = $result['id'];

        if ($result['ok']) {
            if (function_exists('admin_log_activity')) {
                admin_log_activity($postId > 0 ? 'blog_post_updated' : 'blog_post_created', 'Blog post: ' . $post['title_en']);
            }
            flash('success', $postId > 0 ? 'Blog post updated successfully.' : 'Blog post created successfully.');
            redirect('blog-post-form.php?id=' . (int) $result['id']);
        }

        flash('error', implode(' ', $result['errors']));
    }
}

$categories = blog_categories(false);
$isEdit = (int) $post['id'] > 0;
$publishedValue = trim((string) $post['published_at']);
$publishedInput = $publishedValue !== '' ? date('Y-m-d\TH:i', strtotime($publishedValue)) : '';

$editorFile = __DIR__ . '/includes/summernote-blog-editor.php';

if (!is_readable($editorFile)) {
    http_response_code(500);
    exit('Summernote editor file is missing. Please upload admin/includes/summernote-blog-editor.php.');
}

require_once __DIR__ . '/includes/header.php';
require_once $editorFile;

if (!function_exists('summernote_blog_editor_field')) {
    http_response_code(500);
    exit('Summernote editor could not be initialized.');
}

summernote_blog_editor_assets();
?>
<style>
  .bpf-wrap{max-width:1280px;margin:0 auto}.bpf-top{display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap;margin-bottom:18px}.bpf-top h2{margin:0;color:#1f2328;font-size:24px}.bpf-top p{margin:5px 0 0;color:#57606a;font-size:13px}.bpf-card{overflow:hidden;margin-bottom:16px;border:1px solid #d0d7de;border-radius:10px;background:#fff;box-shadow:0 1px 0 rgba(31,35,40,.04)}.bpf-card-head{display:flex;align-items:flex-start;gap:10px;padding:14px 18px;border-bottom:1px solid #d8dee4;background:#f6f8fa}.bpf-card-head strong{display:block;color:#24292f;font-size:15px}.bpf-card-head span{display:block;margin-top:3px;color:#57606a;font-size:12px;line-height:1.45}.bpf-number{display:grid;flex:0 0 26px;width:26px;height:26px;place-items:center;border:1px solid #b6e3ff;border-radius:6px;color:#0550ae;background:#ddf4ff;font-size:11px;font-weight:800}.bpf-body{padding:18px}.bpf-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:15px}.bpf-field{display:grid;gap:6px;min-width:0}.bpf-field.full{grid-column:1/-1}.bpf-field label{color:#24292f;font-size:13px;font-weight:700}.bpf-field small{color:#57606a;font-size:12px;line-height:1.45}.bpf-field>input,.bpf-field>select,.bpf-field>textarea{width:100%;min-height:40px;padding:8px 10px;border:1px solid #d0d7de;border-radius:7px;color:#24292f;background:#fff;font:inherit;font-size:14px}.bpf-field>textarea{min-height:102px;resize:vertical;line-height:1.55}.bpf-field>input:focus,.bpf-field>select:focus,.bpf-field>textarea:focus{outline:none;border-color:#0969da;box-shadow:0 0 0 3px rgba(9,105,218,.15)}.bpf-checkbox{display:flex;align-items:center;gap:8px;min-height:40px;padding:8px 10px;border:1px solid #d0d7de;border-radius:7px}.bpf-checkbox input{width:16px!important;min-height:auto!important;margin:0;padding:0;box-shadow:none}.bpf-divider{height:1px;margin:22px 0;background:#d8dee4}.bpf-language{display:inline-flex;align-items:center;min-height:23px;margin:0 0 13px;padding:2px 8px;border:1px solid #b6e3ff;border-radius:999px;color:#0550ae;background:#ddf4ff;font-size:11px;font-weight:800}.bpf-language.bn{border-color:#a5d6b5;color:#1a7f37;background:#dafbe1}.bpf-editor-stack{display:grid;gap:20px}.bpf-image-preview{display:flex;align-items:flex-start;gap:12px;flex-wrap:wrap;padding:10px;border:1px dashed #b8c7d9;border-radius:8px;background:#f9fbfd}.bpf-image-preview img{width:144px;height:96px;object-fit:cover;border:1px solid #d0d7de;border-radius:7px}.bpf-image-preview span{color:#57606a;font-size:12px;line-height:1.5}.bpf-actions{position:sticky;bottom:12px;z-index:10;display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;padding:12px 14px;border:1px solid #d0d7de;border-radius:10px;background:rgba(255,255,255,.95);box-shadow:0 8px 22px rgba(31,35,40,.12);backdrop-filter:blur(10px)}.bpf-action-group{display:flex;gap:8px;flex-wrap:wrap}.bpf-tabs{display:flex;flex-wrap:wrap;gap:7px;margin:0 0 15px}.bpf-tab{min-height:34px;padding:7px 11px;border:1px solid #cdd8e7;border-radius:7px;background:#fff;color:#24292f;font:inherit;font-size:13px;font-weight:700;cursor:pointer}.bpf-tab.active{border-color:#0969da;color:#fff;background:#0969da}.bpf-panel{display:none}.bpf-panel.active{display:block}@media(max-width:760px){.bpf-body{padding:14px}.bpf-grid{grid-template-columns:1fr}.bpf-actions{position:static}.bpf-actions .btn{flex:1 1 auto}.bpf-action-group{width:100%}}
</style>
<div class="bpf-wrap">
  <div class="bpf-top"><div><h2><?= $isEdit ? 'Edit Blog Post' : 'Add New Blog Post' ?></h2><p>Publish bilingual health content with a featured image, SEO metadata and the Summernote visual editor.</p></div><div style="display:flex;gap:8px;flex-wrap:wrap"><a class="btn btn-outline" href="blog-posts.php">← All Posts</a><?php if($isEdit): ?><a class="btn btn-outline" href="<?= e(blog_url($post,'en')) ?>" target="_blank" rel="noopener">Preview ↗</a><?php endif; ?></div></div>
  <?php show_flash(); ?>
  <form method="post" enctype="multipart/form-data" id="blogPostForm">
    <input type="hidden" name="csrf_token" value="<?= e(blog_csrf_token()) ?>"><input type="hidden" name="id" value="<?= (int)$post['id'] ?>"><input type="hidden" name="old_featured_image" value="<?= e($post['featured_image']) ?>">
    <section class="bpf-card"><div class="bpf-card-head"><span class="bpf-number">01</span><div><strong>Publish Setup</strong><span>Set the public URL, category, author and publication status.</span></div></div><div class="bpf-body"><div class="bpf-grid"><div class="bpf-field"><label for="post_slug">Post URL Slug <span style="color:#cf222e">*</span></label><input id="post_slug" name="slug" required maxlength="190" value="<?= e($post['slug']) ?>"><small>Example: healthy-heart-habits becomes /blog/healthy-heart-habits.</small></div><div class="bpf-field"><label for="post_category">Category</label><select id="post_category" name="category_id"><option value="0">No category</option><?php foreach($categories as $category): ?><option value="<?= (int)$category['id'] ?>" <?= (int)$post['category_id']===(int)$category['id']?'selected':'' ?>><?= e($category['name_en']) ?><?= trim((string)$category['name_bn'])!==''?' / '.e($category['name_bn']):'' ?></option><?php endforeach; ?></select></div><div class="bpf-field"><label for="post_status">Status</label><select id="post_status" name="status"><option value="draft" <?= $post['status']==='draft'?'selected':'' ?>>Draft</option><option value="published" <?= $post['status']==='published'?'selected':'' ?>>Published</option><option value="scheduled" <?= $post['status']==='scheduled'?'selected':'' ?>>Scheduled</option></select></div><div class="bpf-field"><label for="post_published_at">Publish Date and Time</label><input id="post_published_at" name="published_at" type="datetime-local" value="<?= e($publishedInput) ?>"><small>Required for Scheduled. A Published post without a date uses the current time.</small></div><div class="bpf-field"><label for="post_author">Author Name</label><input id="post_author" name="author_name" maxlength="120" value="<?= e($post['author_name']) ?>"></div><div class="bpf-field"><label>Featured Placement</label><label class="bpf-checkbox"><input type="checkbox" name="is_featured" value="1" <?= (int)$post['is_featured']===1?'checked':'' ?>> Show this post in featured placements</label></div></div></div></section>
    <section class="bpf-card"><div class="bpf-card-head"><span class="bpf-number">02</span><div><strong>Featured Image</strong><span>Upload an image from your device or provide a secure public image URL.</span></div></div><div class="bpf-body"><div class="bpf-grid"><div class="bpf-field"><label for="featured_image_upload">Upload Image</label><input id="featured_image_upload" type="file" name="featured_image_upload" accept="image/jpeg,image/png,image/webp"><small>JPG, PNG or WebP. Maximum 8 MB.</small></div><div class="bpf-field"><label for="featured_image">Image URL</label><input id="featured_image" name="featured_image" type="url" value="<?= e($post['featured_image']) ?>"><small>A new URL overrides the previous image.</small></div><div class="bpf-field"><label for="image_alt_en">English Alt Text</label><input id="image_alt_en" name="image_alt_en" maxlength="255" value="<?= e($post['image_alt_en']) ?>"></div><div class="bpf-field"><label for="image_alt_bn">Bangla Alt Text</label><input id="image_alt_bn" name="image_alt_bn" maxlength="255" value="<?= e($post['image_alt_bn']) ?>"></div><?php if(trim((string)$post['featured_image'])!==''): ?><div class="bpf-field full"><div class="bpf-image-preview"><img src="<?= e(blog_image_url($post['featured_image'])) ?>" alt=""><span>Current featured image.<br>Uploading a new image or adding another Image URL will replace it after saving.</span></div></div><?php endif; ?></div></div></section>
    <section class="bpf-card"><div class="bpf-card-head"><span class="bpf-number">03</span><div><strong>Post Content</strong><span>English and Bangla are separate. Leave Bangla blank only when the English fallback is intended.</span></div></div><div class="bpf-body"><div class="bpf-tabs"><button type="button" class="bpf-tab active" data-panel="en">English</button><button type="button" class="bpf-tab" data-panel="bn">বাংলা</button></div><div class="bpf-panel active" data-content-panel="en"><span class="bpf-language">ENGLISH</span><div class="bpf-grid"><div class="bpf-field full"><label for="title_en">Post Title <span style="color:#cf222e">*</span></label><input id="title_en" name="title_en" required maxlength="255" value="<?= e($post['title_en']) ?>"></div><div class="bpf-field full"><label for="excerpt_en">Short Excerpt</label><textarea id="excerpt_en" name="excerpt_en" maxlength="1000" placeholder="A concise card summary for the blog listing."><?= e($post['excerpt_en']) ?></textarea></div><div class="bpf-field full"><?php summernote_blog_editor_field(
    'content_en',
    'content_en_summernote',
    (string) $post['content_en'],
    'English Content Editor',
    'Use formatting, links, tables, images and source HTML.'
); ?></div></div></div><div class="bpf-panel" data-content-panel="bn"><span class="bpf-language bn">বাংলা</span><div class="bpf-grid"><div class="bpf-field full"><label for="title_bn">Post Title</label><input id="title_bn" name="title_bn" maxlength="255" value="<?= e($post['title_bn']) ?>"></div><div class="bpf-field full"><label for="excerpt_bn">Short Excerpt</label><textarea id="excerpt_bn" name="excerpt_bn" maxlength="1000" placeholder="ব্লগ লিস্টিংয়ের জন্য সংক্ষিপ্ত সারাংশ লিখুন।"><?= e($post['excerpt_bn']) ?></textarea></div><div class="bpf-field full"><?php summernote_blog_editor_field(
    'content_bn',
    'content_bn_summernote',
    (string) $post['content_bn'],
    'Bangla Content Editor',
    'Formatting, links, tables, images and source HTML are supported.'
); ?></div></div></div></div></section>
    <section class="bpf-card"><div class="bpf-card-head"><span class="bpf-number">04</span><div><strong>SEO Details</strong><span>Use a focused title and description for each language. Empty values use the post title and excerpt automatically.</span></div></div><div class="bpf-body"><div class="bpf-tabs"><button type="button" class="bpf-tab active" data-panel="seo-en">English SEO</button><button type="button" class="bpf-tab" data-panel="seo-bn">বাংলা SEO</button></div><div class="bpf-panel active" data-content-panel="seo-en"><div class="bpf-grid"><div class="bpf-field"><label for="meta_title_en">SEO Title</label><input id="meta_title_en" name="meta_title_en" maxlength="255" value="<?= e($post['meta_title_en']) ?>"></div><div class="bpf-field"><label for="meta_keywords_en">SEO Keywords</label><input id="meta_keywords_en" name="meta_keywords_en" maxlength="500" value="<?= e($post['meta_keywords_en']) ?>"></div><div class="bpf-field full"><label for="meta_description_en">SEO Description</label><textarea id="meta_description_en" name="meta_description_en" maxlength="500"><?= e($post['meta_description_en']) ?></textarea></div></div></div><div class="bpf-panel" data-content-panel="seo-bn"><div class="bpf-grid"><div class="bpf-field"><label for="meta_title_bn">SEO Title</label><input id="meta_title_bn" name="meta_title_bn" maxlength="255" value="<?= e($post['meta_title_bn']) ?>"></div><div class="bpf-field"><label for="meta_keywords_bn">SEO Keywords</label><input id="meta_keywords_bn" name="meta_keywords_bn" maxlength="500" value="<?= e($post['meta_keywords_bn']) ?>"></div><div class="bpf-field full"><label for="meta_description_bn">SEO Description</label><textarea id="meta_description_bn" name="meta_description_bn" maxlength="500"><?= e($post['meta_description_bn']) ?></textarea></div></div></div></div></section>
    <div class="bpf-actions"><div class="bpf-action-group"><button class="btn btn-primary" type="submit"><?= $isEdit?'Save Changes':'Create Post' ?></button><a class="btn btn-outline" href="blog-posts.php">Cancel</a></div><?php if($isEdit): ?><div class="bpf-action-group"><a class="btn btn-outline" target="_blank" rel="noopener" href="<?= e(blog_url($post,'en')) ?>">View English ↗</a><a class="btn btn-outline" target="_blank" rel="noopener" href="<?= e(blog_url($post,'bn')) ?>">View বাংলা ↗</a></div><?php endif; ?></div>
  </form>
</div>
<script>
(function(){
  document.querySelectorAll('.bpf-tabs').forEach(function(group){var tabs=group.querySelectorAll('.bpf-tab');tabs.forEach(function(tab){tab.addEventListener('click',function(){var target=tab.getAttribute('data-panel');var card=group.closest('.bpf-card');tabs.forEach(function(item){item.classList.toggle('active',item===tab);});card.querySelectorAll('[data-content-panel]').forEach(function(panel){panel.classList.toggle('active',panel.getAttribute('data-content-panel')===target);});});});});
  var title=document.getElementById('title_en'),slug=document.getElementById('post_slug');if(title&&slug){title.addEventListener('input',function(){if(slug.dataset.manual==='1')return;slug.value=title.value.toLowerCase().replace(/[^\p{L}\p{N}\s-]/gu,'').trim().replace(/[\s_]+/gu,'-').replace(/-+/g,'-');});slug.addEventListener('input',function(){slug.dataset.manual='1';});}
}());
</script>
<?php summernote_blog_editor_script(); ?>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
