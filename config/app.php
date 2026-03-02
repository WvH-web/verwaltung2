<?php
/**
 * WvH Abrechnungssystem – Anwendungskonfiguration
 */

// Basis-URL (kein abschließender Slash)
define('APP_URL',      'https://deutsche-online-schule.com/schooltools/teachersbilling');
define('APP_NAME',     'WvH Abrechnungssystem');
define('APP_VERSION',  '1.0.0');
define('APP_TIMEZONE', 'Europe/Berlin');

// Pfade (absolut)
define('BASE_PATH',    dirname(__DIR__));
define('CONFIG_PATH',  BASE_PATH . '/config');
define('SRC_PATH',     BASE_PATH . '/src');
define('UPLOAD_PATH',  BASE_PATH . '/uploads');
define('LOG_PATH',     BASE_PATH . '/logs');
define('TEMPLATE_PATH',BASE_PATH . '/templates');

// Session-Einstellungen
define('SESSION_NAME',    'wvh_sess');
define('SESSION_LIFETIME', 7200); // 2 Stunden

// Sicherheit
define('CSRF_TOKEN_LENGTH', 32);
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_MINUTES', 15);

// Rollen-IDs (müssen mit DB übereinstimmen)
define('ROLE_ADMIN',      1);
define('ROLE_VERWALTUNG', 2);
define('ROLE_LEHRER',     3);

// Zeitzone setzen
date_default_timezone_set(APP_TIMEZONE);

// Fehler-Anzeige (für Produktion auf 0 setzen!)
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', LOG_PATH . '/php_errors.log');
