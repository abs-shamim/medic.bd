<?php
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

$division_id = (int)($_GET['division_id'] ?? 0);
$lang = $_GET['lang'] ?? 'en';

echo json_encode(get_districts_by_division($division_id, $lang));