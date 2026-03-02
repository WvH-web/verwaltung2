<?php
/**
 * WvH – Einstiegspunkt
 * Weiterleitung: eingeloggt → Dashboard, sonst → Login
 */
require_once __DIR__ . '/config/bootstrap.php';

use Auth\Auth;

if (Auth::check()) {
    header('Location: ' . APP_URL . '/dashboard.php');
} else {
    header('Location: ' . APP_URL . '/login.php');
}
exit;
