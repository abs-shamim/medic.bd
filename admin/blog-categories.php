<?php
require_once __DIR__ . '/../includes/blog-functions.php';
blog_ensure_tables();
require_admin_permission('specialties.manage');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    if (!blog_verify_csrf($_POST['csrf_token'] ?? null)) {
        flash('error', 'Security check failed.');
    } else {
        $id = max(0, (int) ($_POST['id'] ?? 0));
        try {
            $check = $pdo->prepare('SELECT COUNT(*) FROM blog_posts WHERE category_id = :id');
            $check->execute([':id' => $id]);
            if ((int) $check->fetchColumn() > 0) {
                flash('error', 'This category is used by one or more posts. Move those posts before deleting it.');
            } else {
                $statement = $pdo->prepare('DELETE FROM blog_categories WHERE id = :id');
                $statement->execute([':id' => $id]);
                flash('success', 'Blog category deleted.');
            }
        } catch (Throwable $exception) {
            flash('error', 'Category could not be deleted.');
        }
    }
    redirect('blog-categories.php');
}

$categories = [];
try {
    $categories = $pdo->query(
        'SELECT c.*, COUNT(p.id) AS post_count
         FROM blog_categories c
         LEFT JOIN blog_posts p ON p.category_id = c.id
         GROUP BY c.id
         ORDER BY c.name_en ASC, c.id DESC'
    )->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $exception) {
    $categories = [];
}

require_once __DIR__ . '/includes/header.php';
?>
<style>
  .bcl-wrap{max-width:1200px;margin:0 auto}.bcl-top{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:18px}.bcl-top h2{margin:0;color:#1f2328;font-size:24px}.bcl-top p{margin:5px 0 0;color:#57606a;font-size:13px}.bcl-card{overflow:hidden;border:1px solid #d0d7de;border-radius:10px;background:#fff;box-shadow:0 1px 0 rgba(31,35,40,.04)}.bcl-table{width:100%;border:0!important;border-radius:0!important;box-shadow:none!important}.bcl-table th{font-size:12px!important}.bcl-name strong{display:block;color:#24292f}.bcl-name span{display:block;margin-top:3px;color:#57606a;font-size:12px}.bcl-slug{color:#0550ae;font-size:12px;overflow-wrap:anywhere}.bcl-pill{display:inline-flex;align-items:center;min-height:23px;padding:2px 8px;border-radius:999px;font-size:11px;font-weight:700}.bcl-pill.active{color:#116329;background:#dafbe1}.bcl-pill.inactive{color:#cf222e;background:#ffebe9}.bcl-actions{display:flex;gap:7px;justify-content:flex-end;flex-wrap:wrap}.bcl-inline{display:inline}.bcl-empty{padding:42px 20px;color:#57606a;text-align:center}@media(max-width:760px){.bcl-table thead{display:none}.bcl-table,.bcl-table tbody,.bcl-table tr,.bcl-table td{display:block;width:100%;white-space:normal!important}.bcl-table tr{padding:12px;border-bottom:1px solid #d8dee4}.bcl-table td{padding:5px 0!important;border:0!important}.bcl-actions{justify-content:flex-start}.bcl-table td[data-label]::before{content:attr(data-label);display:block;margin-bottom:3px;color:#57606a;font-size:11px;font-weight:700;text-transform:uppercase}}
</style>
<div class="bcl-wrap">
  <div class="bcl-top"><div><h2>Blog Categories</h2><p>Create categories once and use them for both English and Bangla blog posts.</p></div><a class="btn btn-primary" href="blog-category-form.php">+ Add Category</a></div>
  <?php show_flash(); ?>
  <section class="bcl-card">
    <?php if($categories): ?>
      <table class="bcl-table"><thead><tr><th>Category</th><th>Slug</th><th>Posts</th><th>Status</th><th style="text-align:right">Actions</th></tr></thead><tbody>
      <?php foreach($categories as $category): ?>
        <tr>
          <td data-label="Category" class="bcl-name"><strong><?= e($category['name_en']) ?></strong><?php if(trim((string)$category['name_bn'])!==''): ?><span><?= e($category['name_bn']) ?></span><?php endif; ?></td>
          <td data-label="Slug" class="bcl-slug">/blog/category/<?= e($category['slug']) ?></td>
          <td data-label="Posts"><?= number_format((int)$category['post_count']) ?></td>
          <td data-label="Status"><span class="bcl-pill <?= $category['status']==='active'?'active':'inactive' ?>"><?= e(ucfirst($category['status'])) ?></span></td>
          <td data-label="Actions"><div class="bcl-actions"><a class="btn btn-outline" href="blog-category-form.php?id=<?= (int)$category['id'] ?>">Edit</a><a class="btn btn-outline" target="_blank" rel="noopener" href="<?= e(blog_category_url($category,'en')) ?>">View</a><form class="bcl-inline" method="post" onsubmit="return confirm('Delete this category?');"><input type="hidden" name="csrf_token" value="<?= e(blog_csrf_token()) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$category['id'] ?>"><button class="btn btn-outline" type="submit" style="color:#cf222e">Delete</button></form></div></td>
        </tr>
      <?php endforeach; ?>
      </tbody></table>
    <?php else: ?><div class="bcl-empty"><h3 style="margin:0 0 8px;color:#24292f">No categories yet</h3><p style="margin:0 0 16px">Create your first blog category before publishing posts.</p><a class="btn btn-primary" href="blog-category-form.php">+ Add Category</a></div><?php endif; ?>
  </section>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
