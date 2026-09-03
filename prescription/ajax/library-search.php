<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/functions.php';

$type = prescription_normalize_library_type((string)($_GET['type'] ?? 'medicine'));
$q = prescription_clean_text($_GET['q'] ?? '', 100);

try {
    $definition = prescription_library_definition($type);

    if (!$definition) {
        prescription_json([
            'ok' => false,
            'message' => 'Invalid library type.',
            'items' => [],
        ], 422);
    }

    $rows = prescription_get_library_options($pdo, $doctor_id, $type, $q, 30);
    $items = [];

    foreach ($rows as $row) {
        if (!empty($definition['medicine'])) {
            $items[] = [
                'id' => (int)($row['id'] ?? 0),
                'library_id' => (int)($row['id'] ?? 0),
                'generic_name' => (string)($row['generic_name'] ?? ''),
                'brand_name' => (string)($row['brand_name'] ?? ''),
                'label' => trim((string)($row['generic_name'] ?? '')),
                'secondary' => trim((string)($row['brand_name'] ?? '')),
                'usage_count' => (int)($row['usage_count'] ?? 0),
                'is_demo' => !empty($row['is_demo']),
            ];
        } else {
            $value = trim((string)($row['option_value'] ?? ''));
            $items[] = [
                'id' => (int)($row['id'] ?? 0),
                'value' => $value,
                'label' => $value,
                'usage_count' => (int)($row['usage_count'] ?? 0),
                'is_demo' => !empty($row['is_demo']),
            ];
        }
    }

    prescription_json([
        'ok' => true,
        'type' => $type,
        'query' => $q,
        'items' => $items,
    ]);
} catch (Throwable $e) {
    error_log('[Prescription Library Search] ' . $e->getMessage());

    prescription_json([
        'ok' => false,
        'message' => 'Library search failed.',
        'items' => [],
    ], 500);
}
