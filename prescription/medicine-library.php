<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$requested_type = trim((string)($_GET['type'] ?? ''));

if ($requested_type !== '') {
    $type = prescription_normalize_library_type($requested_type);
    prescription_redirect(prescription_library_list_path($type));
}

prescription_redirect(prescription_library_dashboard_path());
