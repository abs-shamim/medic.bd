<?php
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

$district_id = (int)($_GET['district_id'] ?? 0);
$lang = $_GET['lang'] ?? 'en';

echo json_encode(get_thanas_by_district($district_id, $lang));