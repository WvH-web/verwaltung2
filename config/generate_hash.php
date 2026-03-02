<?php
/**
 * WvH – Hash-Generator & Admin-Setup
 * Einmalig aufrufen: https://deutsche-online-schule.com/schooltools/teachersbilling/config/generate_hash.php
 * DANACH SOFORT LÖSCHEN!
 */

// Minimaler Schutz
$secret = $_GET['secret'] ?? '';
if ($secret !== 'wvh_setup_2026') {
    die('Zugriff verweigert. Bitte ?secret=wvh_setup_2026 anhängen.');
}

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/app.php';

$password = 'WvH@dmin2026!';
$hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

$db = getDB();

// Admin-User updaten oder neu anlegen
$check = $db->prepare("SELECT id FROM users WHERE email = ?");
$check->execute(['admin@wvh-online.com']);
$existing = $check->fetchColumn();

if ($existing) {
    $db->prepare("UPDATE users SET password_hash = ? WHERE email = ?")
       ->execute([$hash, 'admin@wvh-online.com']);
    echo "<p>✅ Admin-Passwort aktualisiert.</p>";
} else {
    $db->prepare(
        "INSERT INTO users (email, password_hash, role_id, first_name, last_name, is_active) VALUES (?,?,1,'System','Administrator',1)"
    )->execute(['admin@wvh-online.com', $hash]);
    echo "<p>✅ Admin-User neu angelegt.</p>";
}

// Verwaltungs-User
$check2 = $db->prepare("SELECT id FROM users WHERE email = ?");
$check2->execute(['verwaltung@wvh-online.com']);
$existing2 = $check2->fetchColumn();

if ($existing2) {
    $db->prepare("UPDATE users SET password_hash = ? WHERE email = ?")
       ->execute([$hash, 'verwaltung@wvh-online.com']);
    echo "<p>✅ Verwaltungs-Passwort aktualisiert.</p>";
} else {
    $db->prepare(
        "INSERT INTO users (email, password_hash, role_id, first_name, last_name, is_active) VALUES (?,?,2,'WvH','Verwaltung',1)"
    )->execute(['verwaltung@wvh-online.com', $hash]);
    echo "<p>✅ Verwaltungs-User neu angelegt.</p>";
}

// Auch Login-Cleanup in PHP (kein Event nötig)
$db->exec("DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)");

echo "<hr>";
echo "<p><strong>Login-Daten:</strong></p>";
echo "<ul>";
echo "<li>E-Mail: admin@wvh-online.com</li>";
echo "<li>Passwort: WvH@dmin2026!</li>";
echo "</ul>";
echo "<p style='color:red'><strong>⚠️ Diese Datei jetzt sofort löschen oder umbenennen!</strong></p>";
echo "<p><a href='/schooltools/teachersbilling/login.php'>→ Zum Login</a></p>";
