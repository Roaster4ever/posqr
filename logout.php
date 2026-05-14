<?php
// logout.php
// config.php handles session startup via DB handler
require_once __DIR__ . '/includes/config.php';
session_destroy();
header('Location: /login.php');
exit;
