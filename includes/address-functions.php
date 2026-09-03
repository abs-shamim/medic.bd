<?php

function get_divisions($lang = 'en') {
    global $pdo;

    $name_col = $lang === 'bn' ? 'name_bn' : 'name_en';

    $stmt = $pdo->query("SELECT id, {$name_col} AS name FROM divisions WHERE status='active' ORDER BY name ASC");
    return $stmt->fetchAll();
}

function get_districts_by_division($division_id, $lang = 'en') {
    global $pdo;

    $name_col = $lang === 'bn' ? 'name_bn' : 'name_en';

    $stmt = $pdo->prepare("SELECT id, {$name_col} AS name FROM districts WHERE division_id=:division_id AND status='active' ORDER BY name ASC");
    $stmt->execute([':division_id' => (int)$division_id]);

    return $stmt->fetchAll();
}

function get_thanas_by_district($district_id, $lang = 'en') {
    global $pdo;

    $name_col = $lang === 'bn' ? 'name_bn' : 'name_en';

    $stmt = $pdo->prepare("SELECT id, {$name_col} AS name FROM thanas WHERE district_id=:district_id AND status='active' ORDER BY name ASC");
    $stmt->execute([':district_id' => (int)$district_id]);

    return $stmt->fetchAll();
}