<?php
require_once __DIR__ . '/../includes/blog-functions.php';
blog_ensure_tables();
require_admin_permission('doctors.view');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    require_admin_permission('doctors.delete');
    if (!blog_verify_csrf($_POST['csrf_token'] ?? null)) {
        flash('error', 'Security check failed.');
    } else {
        $postId = max(0, (int) ($_POST['id'] ?? 0));
        if (blog_delete_post($postId)) {
            if (function_exists('admin_log_activity')) {
                admin_log_activity('blog_post_deleted', 'Blog post ID: ' . $postId);
            }
            flash('success', 'Blog post deleted.');
        } else {
            flash('error', 'Blog post could not be deleted.');
        }
    }
    redirect('blog-posts.php');
}

$status = trim((string) ($_GET['status'] ?? ''));
$status = in_array($status, ['draft', 'published', 'scheduled'], true) ? $status : '';
$search = blog_clean_text($_GET['q'] ?? '', 120);
$categoryId = max(0, (int) ($_GET['category'] ?? 0));
$page = max(1, (int) ($_GET['page'] ?? 1));
$categories = blog_categories(false);
$list = blog_list_posts([
    'public' => false,
    'status' => $status,
    'search' => $search,
    'category_id' => $categoryId,
    'page' => $page,
    'per_page' => 20,
]);

$baseQuery = ['status' => $status, 'q' => $search, 'category' => $categoryId];
$makeUrl = static function (array $extra = []) use ($baseQuery): string {
    $query = array_filter(array_merge($baseQuery, $extra), static function ($value, $key): bool { return $value !== '' && $value !== 0 && $key !== 'page'; }, ARRAY_FILTER_USE_BOTH);
    if (isset($extra['page']) && (int) $extra['page'] > 1) $query['page'] = (int) $extra['page'];
    return 'blog-posts.php' . ($query ? '?' . http_build_query($query) : '');
};

require_once __DIR__ . '/includes/header.php';
?>
<style>
  .bpl-wrap{max-width:1320px;margin:0 auto}.bpl-top{display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap;margin-bottom:18px}.bpl-top h2{margin:0;color:#1f2328;font-size:24px}.bpl-top p{margin:5px 0 0;color:#57606a;font-size:13px}.bpl-card{overflow:hidden;border:1px solid #d0d7de;border-radius:10px;background:#fff;box-shadow:0 1px 0 rgba(31,35,40,.04)}.bpl-filter{display:grid;grid-template-columns:minmax(170px,1fr) 180px 150px auto;gap:9px;padding:14px;border-bottom:1px solid #d8dee4;background:#f6f8fa}.bpl-filter input,.bpl-filter select{width:100%;min-height:38px;padding:7px 10px;border:1px solid #d0d7de;border-radius:7px;background:#fff;color:#24292f;font:inherit;font-size:13px}.bpl-table{width:100%;border:0!important;border-radius:0!important;box-shadow:none!important}.bpl-table th{font-size:12px!important}.bpl-post{display:flex;align-items:center;gap:10px;min-width:230px}.bpl-thumb{width:58px;height:42px;flex:0 0 58px;overflow:hidden;border:1px solid #d0d7de;border-radius:6px;background:#f6f8fa}.bpl-thumb img{display:block;width:100%;height:100%;object-fit:cover}.bpl-post strong{display:block;color:#24292f;font-size:13px;line-height:1.35}.bpl-post span{display:block;margin-top:3px;color:#57606a;font-size:11px;line-height:1.3}.bpl-pill{display:inline-flex;align-items:center;min-height:23px;padding:2px 8px;border-radius:999px;font-size:11px;font-weight:700}.bpl-pill.published{color:#116329;background:#dafbe1}.bpl-pill.draft{color:#57606a;background:#eaeef2}.bpl-pill.scheduled{color:#9a6700;background:#fff8c5}.bpl-actions{display:flex;justify-content:flex-end;gap:7px;flex-wrap:wrap}.bpl-inline{display:inline}.bpl-meta{color:#57606a;font-size:12px;line-height:1.45}.bpl-empty{padding:44px 20px;text-align:center;color:#57606a}.bpl-pager{display:flex;justify-content:center;gap:6px;padding:16px}.bpl-pager a,.bpl-pager span{display:inline-flex;align-items:center;justify-content:center;min-width:34px;height:34px;padding:0 9px;border:1px solid #d0d7de;border-radius:6px;color:#0969da;text-decoration:none;font-size:13px;font-weight:700}.bpl-pager span{color:#fff;background:#0969da;border-color:#0969da}@media(max-width:900px){.bpl-filter{grid-template-columns:1fr 1fr}.bpl-filter .btn{width:100%}}@media(max-width:760px){.bpl-table thead{display:none}.bpl-table,.bpl-table tbody,.bpl-table tr,.bpl-table td{display:block;width:100%;white-space:normal!important}.bpl-table tr{padding:13px;border-bottom:1px solid #d8dee4}.bpl-table td{padding:5px 0!important;border:0!important}.bpl-table td[data-label]::before{content:attr(data-label);display:block;margin-bottom:3px;color:#57606a;font-size:11px;font-weight:700;text-transform:uppercase}.bpl-actions{justify-content:flex-start}.bpl-filter{grid-template-columns:1fr}.bpl-post{min-width:0}}
</style>
<div class="bpl-wrap">
  <div class="bpl-top"><div><h2>Blog Posts</h2><p>Manage drafts, scheduled posts, published articles and bilingual content.</p></div><div style="display:flex;gap:8px;flex-wrap:wrap"><?php if(admin_can('specialties.manage')): ?><a class="btn btn-outline" href="blog-categories.php">Categories</a><?php endif; ?><?php if(admin_can('doctors.create')): ?><a class="btn btn-primary" href="blog-post-form.php">+ Add New Post</a><?php endif; ?></div></div>
  <?php show_flash(); ?>
  <section class="bpl-card">
    <form class="bpl-filter" method="get"><input type="search" name="q" value="<?= e($search) ?>" placeholder="Search title or excerpt"><select name="status"><option value="">All statuses</option><option value="published" <?= $status==='published'?'selected':'' ?>>Published</option><option value="draft" <?= $status==='draft'?'selected':'' ?>>Draft</option><option value="scheduled" <?= $status==='scheduled'?'selected':'' ?>>Scheduled</option></select><select name="category"><option value="0">All categories</option><?php foreach($categories as $category): ?><option value="<?= (int)$category['id'] ?>" <?= $categoryId===(int)$category['id']?'selected':'' ?>><?= e($category['name_en']) ?></option><?php endforeach; ?></select><button class="btn btn-outline" type="submit">Filter</button></form>
    <?php if($list['items']): ?><table class="bpl-table"><thead><tr><th>Post</th><th>Category</th><th>Status</th><th>Published</th><th>Views</th><th style="text-align:right">Actions</th></tr></thead><tbody><?php foreach($list['items'] as $post): ?><tr><td data-label="Post"><div class="bpl-post"><span class="bpl-thumb"><?php if(trim((string)$post['featured_image'])!==''): ?><img src="<?= e(blog_image_url($post['featured_image'])) ?>" alt=""><?php endif; ?></span><div><strong><?= e($post['title_en']) ?></strong><?php if(trim((string)$post['title_bn'])!==''): ?><span><?= e($post['title_bn']) ?></span><?php endif; ?><span>/blog/<?= e($post['slug']) ?></span></div></div></td><td data-label="Category" class="bpl-meta"><?= e($post['category_name_en'] ?: 'Uncategorized') ?></td><td data-label="Status"><span class="bpl-pill <?= e($post['status']) ?>"><?= e(ucfirst($post['status'])) ?></span></td><td data-label="Published" class="bpl-meta"><?= !empty($post['published_at'])?e(date('d M Y, h:i A',strtotime($post['published_at']))):'—' ?></td><td data-label="Views" class="bpl-meta"><?= number_format((int)$post['views']) ?></td><td data-label="Actions"><div class="bpl-actions"><?php if(admin_can('doctors.edit')): ?><a class="btn btn-outline" href="blog-post-form.php?id=<?= (int)$post['id'] ?>">Edit</a><?php endif; ?><a class="btn btn-outline" target="_blank" rel="noopener" href="<?= e(blog_url($post,'en')) ?>">View</a><?php if(admin_can('doctors.delete')): ?><form class="bpl-inline" method="post" onsubmit="return confirm('Delete this blog post permanently?');"><input type="hidden" name="csrf_token" value="<?= e(blog_csrf_token()) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$post['id'] ?>"><button class="btn btn-outline" style="color:#cf222e" type="submit">Delete</button></form><?php endif; ?></div></td></tr><?php endforeach; ?></tbody></table>
      <?php if($list['total_pages']>1): ?><nav class="bpl-pager" aria-label="Blog post pagination"><?php for($i=1;$i<=$list['total_pages'];$i++): ?><?php if($i===(int)$list['page']): ?><span><?= $i ?></span><?php else: ?><a href="<?= e($makeUrl(['page'=>$i])) ?>"><?= $i ?></a><?php endif; ?><?php endfor; ?></nav><?php endif; ?>
    <?php else: ?><div class="bpl-empty"><h3 style="margin:0 0 8px;color:#24292f">No blog posts found</h3><p style="margin:0 0 16px">Create a new post or choose different filters.</p><?php if(admin_can('doctors.create')): ?><a class="btn btn-primary" href="blog-post-form.php">+ Add New Post</a><?php endif; ?></div><?php endif; ?>
  </section>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
