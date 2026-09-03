<?php
require_once __DIR__ . '/../includes/blog-functions.php';
blog_ensure_tables();
require_admin_permission('specialties.manage');

$categoryId = max(0, (int) ($_GET['id'] ?? $_POST['id'] ?? 0));
$category = [
    'id' => 0, 'slug' => '', 'name_en' => '', 'name_bn' => '',
    'description_en' => '', 'description_bn' => '',
    'meta_title_en' => '', 'meta_title_bn' => '',
    'meta_description_en' => '', 'meta_description_bn' => '', 'status' => 'active',
];

if ($categoryId > 0) {
    try {
        $statement = $pdo->prepare('SELECT * FROM blog_categories WHERE id = :id LIMIT 1');
        $statement->execute([':id' => $categoryId]);
        $loaded = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($loaded)) {
            flash('error', 'Category was not found.');
            redirect('blog-categories.php');
        }
        $category = array_merge($category, $loaded);
    } catch (Throwable $exception) {
        flash('error', 'Category could not be loaded.');
        redirect('blog-categories.php');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!blog_verify_csrf($_POST['csrf_token'] ?? null)) {
        flash('error', 'Security check failed. Please try again.');
    } else {
        $result = blog_save_category($_POST, $categoryId);
        $category = array_merge($category, $result['data']);
        $category['id'] = $result['id'];

        if ($result['ok']) {
            flash('success', $categoryId > 0 ? 'Blog category updated.' : 'Blog category created.');
            redirect('blog-category-form.php?id=' . (int) $result['id']);
        }

        flash('error', implode(' ', $result['errors']));
    }
}

$isEdit = (int) $category['id'] > 0;
require_once __DIR__ . '/includes/header.php';
?>
<style>
  .bc-wrap { max-width: 1120px; margin: 0 auto; }
  .bc-top, .bc-actions { display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap; }
  .bc-top { margin-bottom:18px; }
  .bc-top h2 { margin:0; color:#1f2328; font-size:24px; }
  .bc-top p { margin:5px 0 0; color:#57606a; font-size:13px; }
  .bc-card { overflow:hidden; border:1px solid #d0d7de; border-radius:10px; background:#fff; box-shadow:0 1px 0 rgba(31,35,40,.04); }
  .bc-head { padding:16px 18px; border-bottom:1px solid #d8dee4; background:#f6f8fa; }
  .bc-head h3 { margin:0; font-size:15px; color:#24292f; }
  .bc-body { padding:18px; }
  .bc-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:15px; }
  .bc-field { display:grid; gap:6px; min-width:0; }
  .bc-field.full { grid-column:1/-1; }
  .bc-field label { color:#24292f; font-size:13px; font-weight:700; }
  .bc-field small { color:#57606a; font-size:12px; line-height:1.45; }
  .bc-field input, .bc-field textarea, .bc-field select { width:100%; min-height:40px; padding:8px 10px; border:1px solid #d0d7de; border-radius:7px; color:#24292f; background:#fff; font:inherit; font-size:14px; }
  .bc-field textarea { min-height:106px; resize:vertical; line-height:1.55; }
  .bc-field input:focus, .bc-field textarea:focus, .bc-field select:focus { outline:none; border-color:#0969da; box-shadow:0 0 0 3px rgba(9,105,218,.15); }
  .bc-divider { height:1px; margin:22px 0; background:#d8dee4; }
  .bc-lang { display:inline-flex; margin:0 0 13px; padding:4px 8px; border:1px solid #b6e3ff; border-radius:999px; color:#0550ae; background:#ddf4ff; font-size:11px; font-weight:800; }
  .bc-lang.bn { border-color:#a5d6b5; color:#1a7f37; background:#dafbe1; }
  .bc-actions { margin-top:18px; }
  .bc-actions-left, .bc-actions-right { display:flex; gap:8px; flex-wrap:wrap; }
  @media(max-width:720px){ .bc-body{padding:14px}.bc-grid{grid-template-columns:1fr}.bc-actions .btn{flex:1 1 auto;} }
</style>
<div class="bc-wrap">
  <div class="bc-top">
    <div><h2><?= $isEdit ? 'Edit Blog Category' : 'Add Blog Category' ?></h2><p>Use one category record for both English and Bangla public pages.</p></div>
    <a class="btn btn-outline" href="blog-categories.php">← Categories</a>
  </div>
  <?php show_flash(); ?>
  <form method="post" class="bc-card">
    <input type="hidden" name="csrf_token" value="<?= e(blog_csrf_token()) ?>"><input type="hidden" name="id" value="<?= (int) $category['id'] ?>">
    <div class="bc-head"><h3>Category Details</h3></div>
    <div class="bc-body">
      <div class="bc-grid">
        <div class="bc-field"><label for="category_slug">URL Slug</label><input id="category_slug" name="slug" required maxlength="190" value="<?= e($category['slug']) ?>"><small>Example: health-tips becomes /blog/category/health-tips.</small></div>
        <div class="bc-field"><label for="category_status">Status</label><select id="category_status" name="status"><option value="active" <?= $category['status']==='active'?'selected':'' ?>>Active</option><option value="inactive" <?= $category['status']==='inactive'?'selected':'' ?>>Inactive</option></select></div>
      </div>
      <div class="bc-divider"></div>
      <span class="bc-lang">ENGLISH</span>
      <div class="bc-grid">
        <div class="bc-field"><label for="name_en">Category Name <span style="color:#cf222e">*</span></label><input id="name_en" name="name_en" required maxlength="190" value="<?= e($category['name_en']) ?>"></div>
        <div class="bc-field"><label for="meta_title_en">SEO Title</label><input id="meta_title_en" name="meta_title_en" maxlength="255" value="<?= e($category['meta_title_en']) ?>"></div>
        <div class="bc-field full"><label for="description_en">Description</label><textarea id="description_en" name="description_en"><?= e($category['description_en']) ?></textarea></div>
        <div class="bc-field full"><label for="meta_description_en">SEO Description</label><textarea id="meta_description_en" name="meta_description_en"><?= e($category['meta_description_en']) ?></textarea></div>
      </div>
      <div class="bc-divider"></div>
      <span class="bc-lang bn">বাংলা</span>
      <div class="bc-grid">
        <div class="bc-field"><label for="name_bn">Category Name</label><input id="name_bn" name="name_bn" maxlength="190" value="<?= e($category['name_bn']) ?>"></div>
        <div class="bc-field"><label for="meta_title_bn">SEO Title</label><input id="meta_title_bn" name="meta_title_bn" maxlength="255" value="<?= e($category['meta_title_bn']) ?>"></div>
        <div class="bc-field full"><label for="description_bn">Description</label><textarea id="description_bn" name="description_bn"><?= e($category['description_bn']) ?></textarea></div>
        <div class="bc-field full"><label for="meta_description_bn">SEO Description</label><textarea id="meta_description_bn" name="meta_description_bn"><?= e($category['meta_description_bn']) ?></textarea></div>
      </div>
      <div class="bc-actions"><div class="bc-actions-left"><button class="btn btn-primary" type="submit"><?= $isEdit ? 'Save Changes' : 'Create Category' ?></button><a class="btn btn-outline" href="blog-categories.php">Cancel</a></div><?php if($isEdit): ?><div class="bc-actions-right"><a class="btn btn-outline" href="<?= e(blog_category_url($category,'en')) ?>" target="_blank" rel="noopener">Preview ↗</a></div><?php endif; ?></div>
    </div>
  </form>
</div>
<script>
(function(){var source=document.getElementById('name_en'),slug=document.getElementById('category_slug');if(!source||!slug)return;source.addEventListener('input',function(){if(slug.dataset.manual==='1')return;slug.value=source.value.toLowerCase().replace(/[^\p{L}\p{N}\s-]/gu,'').trim().replace(/[\s_]+/gu,'-').replace(/-+/g,'-');});slug.addEventListener('input',function(){slug.dataset.manual='1';});}());
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
