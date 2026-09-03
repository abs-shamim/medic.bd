<?php
require_once __DIR__ . '/../includes/auth.php';
unset($_SESSION['user_id']);
redirect('login.php');
