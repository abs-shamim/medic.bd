<?php
require_once __DIR__ . '/../includes/blog-functions.php';

header('Content-Type: application/json; charset=UTF-8');

if (!is_admin()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'Login required.']);
    exit;
}

if (function_exists('require_admin_permission') && !admin_can('doctors.edit')) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'You do not have permission to upload blog images.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !blog_verify_csrf($_POST['csrf_token'] ?? null)) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'Security check failed. Refresh the page and try again.']);
    exit;
}

$imageUrl = upload_image('image', 'blog/content');

if ($imageUrl === null) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'Upload failed. Use a JPG, PNG or WebP image up to 8 MB.']);
    exit;
}

echo json_encode(['ok' => true, 'url' => $imageUrl]);
