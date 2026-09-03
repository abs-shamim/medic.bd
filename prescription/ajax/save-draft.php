<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    prescription_json([
        'ok' => false,
        'message' => 'Invalid request method.',
    ], 405);
}

if (!prescription_verify_csrf($_POST['csrf_token'] ?? null)) {
    prescription_json([
        'ok' => false,
        'message' => 'Security verification failed. Refresh the page and try again.',
    ], 419);
}

try {
    $prescription_id = (int)($_POST['prescription_id'] ?? 0);
    $saved_id = prescription_save_from_request(
        $pdo,
        $doctor_id,
        $_POST,
        $prescription_id,
        'draft'
    );

    prescription_json([
        'ok' => true,
        'id' => $saved_id,
        'message' => 'Draft saved successfully.',
        'edit_url' => prescription_url('edit.php?id=' . $saved_id),
    ]);
} catch (InvalidArgumentException $e) {
    prescription_json([
        'ok' => false,
        'message' => $e->getMessage(),
    ], 422);
} catch (Throwable $e) {
    try {
        $error_reference = strtoupper(substr(
            hash('sha256', microtime(true) . random_bytes(8)),
            0,
            10
        ));
    } catch (Throwable $reference_error) {
        $error_reference = strtoupper(substr(md5((string)microtime(true)), 0, 10));
    }

    error_log(
        '[Prescription Draft][' . $error_reference . '] '
        . get_class($e)
        . ' in ' . $e->getFile()
        . ':' . $e->getLine()
        . ' - ' . $e->getMessage()
    );

    prescription_json([
        'ok' => false,
        'message' => 'Draft could not be saved. Error reference: ' . $error_reference,
    ], 500);
}
