<?php
/**
 * Summernote image upload endpoint.
 *
 * The shared upload_image() helper is reused so editor images follow the
 * same storage rules as featured images in this project.
 */

header('Content-Type: application/json; charset=utf-8');

$blogFunctionsFile = __DIR__ . '/../includes/blog-functions.php';

if (!is_readable($blogFunctionsFile)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Blog module files are missing.']);
    exit;
}

require_once $blogFunctionsFile;

if (!function_exists('require_admin')) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Admin authentication helpers are unavailable.']);
    exit;
}

require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Only POST requests are allowed.']);
    exit;
}

if (!function_exists('blog_verify_csrf') || !blog_verify_csrf($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Security check failed. Refresh the page and try again.']);
    exit;
}

if (empty($_FILES['editor_image']) || !is_array($_FILES['editor_image'])) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'No image file was received.']);
    exit;
}

$file = $_FILES['editor_image'];

if ((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'The image upload could not be completed.']);
    exit;
}

if ((int) ($file['size'] ?? 0) > 8 * 1024 * 1024) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'The maximum image size is 8 MB.']);
    exit;
}

$imageInfo = @getimagesize((string) ($file['tmp_name'] ?? ''));
$allowedMimeTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

if ($imageInfo === false || !in_array((string) ($imageInfo['mime'] ?? ''), $allowedMimeTypes, true)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Only valid JPG, PNG, WEBP, and GIF images are allowed.']);
    exit;
}

if (!function_exists('upload_image')) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'The shared image upload helper is unavailable.']);
    exit;
}

try {
    $storedPath = upload_image('editor_image', 'blog');

    if (!is_string($storedPath) || trim($storedPath) === '') {
        throw new RuntimeException('The image file could not be saved.');
    }

    $safePath = function_exists('blog_safe_url')
        ? blog_safe_url($storedPath, true)
        : trim($storedPath);

    if ($safePath === '') {
        throw new RuntimeException('The saved image path is invalid.');
    }

    $publicUrl = function_exists('blog_image_url') ? blog_image_url($safePath) : $safePath;

    echo json_encode([
        'success' => true,
        'url' => $publicUrl,
        'path' => $safePath,
    ]);
} catch (Throwable $exception) {
    error_log('Summernote image upload failed: ' . $exception->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'The image could not be uploaded.']);
}
