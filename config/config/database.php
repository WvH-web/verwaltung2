<?php
/**
 * WvH Abrechnungssystem – Datenbankkonfiguration
 * GoDaddy / Plesk / Percona MySQL 8.0
 */

define('DB_HOST',     'a2nlmysql41plsk.secureserver.net');
define('DB_PORT',     3306);
define('DB_NAME',     'wvhdata1');
define('DB_USER',     'wvhadmin1');
define('DB_PASS',     '&8K28szt3');
define('DB_CHARSET',  'utf8mb4');

/**
 * PDO-Verbindung herstellen (Singleton)
 */
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            DB_HOST, DB_PORT, DB_NAME, DB_CHARSET
        );
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '+01:00'",
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log('[WvH-DB] Verbindungsfehler: ' . $e->getMessage());
            die(json_encode(['error' => 'Datenbankverbindung fehlgeschlagen.']));
        }
    }
    return $pdo;
}
