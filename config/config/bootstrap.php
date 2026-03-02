<?php
/**
 * WvH Abrechnungssystem – Bootstrap
 * Wird von allen öffentlichen Seiten als erstes eingebunden
 */

require_once __DIR__ . '/app.php';
require_once __DIR__ . '/database.php';

// Alle Klassen aus src/ laden
spl_autoload_register(function (string $class): void {
    $file = SRC_PATH . '/' . str_replace('\\', '/', $class) . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

// Hilfsfunktionen laden
require_once SRC_PATH . '/Helpers/functions.php';

// Session starten
session_name(SESSION_NAME);
session_set_cookie_params([
    'lifetime' => SESSION_LIFETIME,
    'path'     => '/',
    'secure'   => true,
    'httponly' => true,
    'samesite' => 'Strict',
]);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
