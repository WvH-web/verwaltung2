<?php
/**
 * WvH – Logout
 */
require_once __DIR__ . '/config/bootstrap.php';

use Auth\Auth;

$auth = new Auth();
$auth->logout();

header('Location: ' . APP_URL . '/login.php?logged_out=1');
exit;
