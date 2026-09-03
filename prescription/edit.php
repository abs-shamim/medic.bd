<?php
declare(strict_types=1);

define('PRESCRIPTION_EDIT_ID', (int)($_GET['id'] ?? 0));
require __DIR__ . '/new.php';
