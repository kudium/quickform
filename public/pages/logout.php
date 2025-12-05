<?php
require_once __DIR__ . '/../../server/auth.php';
require_once __DIR__ . '/../../server/utils.php';
logoutUser();
$baseUrl = web_base_url();
header('Location: ' . $baseUrl . 'auth/login');
exit;
