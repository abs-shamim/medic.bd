<?php
/**
 * Summernote Blog Image Upload Endpoint
 *
 * Receives one image file from the Summernote picture tool and returns its
 * public URL as JSON. It reuses the project's existing upload_image helper.
 */

require_once __DIR__ . '/../includes/blog-functions.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Only POST requests are allowed.']);
    exit;
}

if (!function_exists('require_admin')) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Admin authentication is unavailable.']);
    exit;
}

require_admin();

if (function_exists('require_admin_permission')) {
    require_admin_permission('doctors.create');
} elseif (function_exists('admin_can') && !admin_can('doctors.create')) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'You do not have permission to upload images.']);
    exit;
}

if (!blog_verify_csrf($_POST['csrf_token'] ?? null)) {
    http_response_code(419);
    echo json_encode(['ok' => false, 'message' => 'Security check failed. Reload the page and try again.']);
    exit;
}

if (empty($_FILES['image']) || !is_array($_FILES['image'])) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'No image was received.']);
    exit;
}

if (!function_exists('upload_image')) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'The site image upload helper is unavailable.']);
    exit;
}

try {
    $uploadedPath = upload_image('image', 'blog');

    if ($uploadedPath === null || trim((string) $uploadedPath) === '') {
        http_response_code(422);
        echo json_encode(['ok' => false, 'message' => 'The image could not be uploaded. Use JPG, PNG or WebP within the site upload limit.']);
        exit;
    }

    echo json_encode([
        'ok' => true,
        'url' => blog_image_url((string) $uploadedPath),
    ]);
} catch (Throwable $exception) {
    error_log('Summernote blog image upload error: ' . $exception->getMessage());

    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'The image upload failed. Please try again.']);
}
