<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/functions.php';

$q = prescription_clean_text($_GET['q'] ?? '', 100);

if (prescription_strlen($q) < 2) {
    prescription_json(['ok' => true, 'items' => []]);
}

try {
    $search = '%' . $q . '%';
    $phone_digits = prescription_normalize_phone($q);
    $has_phone_normalized = prescription_column_exists(
        $pdo,
        'prescription_patients',
        'phone_normalized'
    );

    $snapshot_map = [
        'last_weight' => 'weight',
        'last_height' => 'height',
        'last_blood_pressure' => 'blood_pressure',
        'last_temperature' => 'temperature',
        'last_pulse' => 'pulse',
        'last_spo2' => 'spo2',
        'medical_history' => 'medical_history',
        'last_visit_date' => 'visit_date',
    ];

    $snapshot_selects = [];

    foreach ($snapshot_map as $patient_column => $prescription_column) {
        if (prescription_column_exists($pdo, 'prescription_patients', $patient_column)) {
            if ($patient_column === 'last_visit_date') {
                $snapshot_selects[] = "COALESCE(p.`{$patient_column}`, latest.`{$prescription_column}`) AS last_visit";
            } else {
                $snapshot_selects[] = "COALESCE(NULLIF(p.`{$patient_column}`, ''), latest.`{$prescription_column}`) AS `{$patient_column}`";
            }
        } elseif ($patient_column === 'last_visit_date') {
            $snapshot_selects[] = "latest.`{$prescription_column}` AS last_visit";
        } else {
            $snapshot_selects[] = "latest.`{$prescription_column}` AS `{$patient_column}`";
        }
    }

    $phone_condition = '';
    $params = [
        ':doctor_id' => $doctor_id,
        ':search_name' => $search,
        ':search_phone' => $search,
        ':search_code' => $search,
    ];

    if ($has_phone_normalized && prescription_strlen($phone_digits) >= 3) {
        $phone_condition = ' OR p.phone_normalized LIKE :phone_digits';
        $params[':phone_digits'] = '%' . $phone_digits . '%';
    }

    $phone_normalized_select = $has_phone_normalized
        ? 'p.phone_normalized'
        : "'' AS phone_normalized";

    $stmt = $pdo->prepare("\n        SELECT
            p.id,
            p.patient_code,
            p.name,
            p.phone,
            {$phone_normalized_select},
            p.age,
            p.gender,
            p.blood_group,
            p.address,
            " . implode(",\n            ", $snapshot_selects) . ",
            latest.diagnosis AS last_diagnosis,
            latest.id AS latest_prescription_id,
            (
                SELECT COUNT(*)
                FROM prescriptions pc
                WHERE pc.doctor_id = p.doctor_id
                  AND pc.patient_id = p.id
            ) AS prescription_count
        FROM prescription_patients p
        LEFT JOIN prescriptions latest
          ON latest.id = (
                SELECT px.id
                FROM prescriptions px
                WHERE px.doctor_id = p.doctor_id
                  AND px.patient_id = p.id
                ORDER BY px.visit_date DESC, px.id DESC
                LIMIT 1
          )
        WHERE p.doctor_id = :doctor_id
          AND (
                p.name LIKE :search_name
             OR p.phone LIKE :search_phone
             OR p.patient_code LIKE :search_code
             {$phone_condition}
          )
        ORDER BY p.updated_at DESC, p.id DESC
        LIMIT 15
    ");
    $stmt->execute($params);

    prescription_json([
        'ok' => true,
        'items' => $stmt->fetchAll(PDO::FETCH_ASSOC),
    ]);
} catch (Throwable $e) {
    error_log('[Prescription Patient Search] ' . get_class($e) . ': ' . $e->getMessage());
    prescription_json([
        'ok' => false,
        'message' => 'Patient search failed.',
        'items' => [],
    ], 500);
}
