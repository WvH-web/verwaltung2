<?php
namespace Auth;

use PDO;

/**
 * WvH Auth – Authentifizierung & Rollenverwaltung
 */
class Auth
{
    private PDO $db;

    public function __construct()
    {
        $this->db = getDB();
    }

    // ----------------------------------------------------------------
    // Login
    // ----------------------------------------------------------------

    public function login(string $email, string $password): array
    {
        $email = strtolower(trim($email));

        // Alte Fehlversuche aufräumen (ersetzt den DB-Event)
        $this->cleanupOldAttempts();

        // Brute-Force-Schutz
        if ($this->isLockedOut($email)) {
            return ['success' => false, 'error' => 'Zu viele Fehlversuche. Bitte warte ' . LOGIN_LOCKOUT_MINUTES . ' Minuten.'];
        }

        // Benutzer suchen
        $stmt = $this->db->prepare(
            "SELECT u.id, u.email, u.password_hash, u.role_id, u.first_name, u.last_name, u.is_active,
                    r.name AS role_name
             FROM users u
             JOIN roles r ON r.id = u.role_id
             WHERE u.email = ?
             LIMIT 1"
        );
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            $this->recordFailedAttempt($email);
            return ['success' => false, 'error' => 'E-Mail oder Passwort falsch.'];
        }

        if (!$user['is_active']) {
            return ['success' => false, 'error' => 'Dein Konto ist deaktiviert. Bitte wende dich an die Verwaltung.'];
        }

        // Fehlversuche löschen
        $this->clearFailedAttempts($email);

        // Session setzen
        session_regenerate_id(true);
        $_SESSION['user_id']    = $user['id'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_name']  = $user['first_name'] . ' ' . $user['last_name'];
        $_SESSION['first_name'] = $user['first_name'];
        $_SESSION['role_id']    = $user['role_id'];
        $_SESSION['role_name']  = $user['role_name'];
        $_SESSION['logged_in']  = true;
        $_SESSION['login_time'] = time();
        $_SESSION['csrf_token'] = bin2hex(random_bytes(CSRF_TOKEN_LENGTH));

        // Letzten Login speichern
        $this->db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")
                 ->execute([$user['id']]);

        // Audit
        logAudit($user['id'], 'user.login', 'users', $user['id']);

        return ['success' => true];
    }

    // ----------------------------------------------------------------
    // Logout
    // ----------------------------------------------------------------

    public function logout(): void
    {
        if (isset($_SESSION['user_id'])) {
            logAudit($_SESSION['user_id'], 'user.logout', 'users', $_SESSION['user_id']);
        }
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params['path'], $params['domain'],
                $params['secure'], $params['httponly']
            );
        }
        session_destroy();
    }

    // ----------------------------------------------------------------
    // Session-Prüfung
    // ----------------------------------------------------------------

    public static function check(): bool
    {
        if (!isset($_SESSION['logged_in'], $_SESSION['login_time'])) {
            return false;
        }
        if (time() - $_SESSION['login_time'] > SESSION_LIFETIME) {
            session_destroy();
            return false;
        }
        $_SESSION['login_time'] = time();
        return true;
    }

    public static function require(): void
    {
        if (!self::check()) {
            $redirect = urlencode($_SERVER['REQUEST_URI'] ?? '');
            header('Location: ' . APP_URL . '/login.php?redirect=' . $redirect);
            exit;
        }
    }

    public static function requireRole(int ...$roleIds): void
    {
        self::require();
        if (!in_array($_SESSION['role_id'] ?? 0, $roleIds, true)) {
            header('HTTP/1.1 403 Forbidden');
            include TEMPLATE_PATH . '/layouts/403.php';
            exit;
        }
    }

    // ----------------------------------------------------------------
    // Rollen-Helfer
    // ----------------------------------------------------------------

    public static function isAdmin(): bool
    {
        return ($_SESSION['role_id'] ?? 0) === ROLE_ADMIN;
    }

    public static function isVerwaltung(): bool
    {
        return in_array($_SESSION['role_id'] ?? 0, [ROLE_ADMIN, ROLE_VERWALTUNG], true);
    }

    public static function isLehrer(): bool
    {
        return ($_SESSION['role_id'] ?? 0) === ROLE_LEHRER;
    }

    public static function userId(): int      { return (int)($_SESSION['user_id']  ?? 0); }
    public static function userName(): string { return $_SESSION['user_name']  ?? ''; }
    public static function userFirstName(): string { return $_SESSION['first_name'] ?? ''; }
    public static function roleName(): string { return $_SESSION['role_name']  ?? ''; }
    public static function csrfToken(): string { return $_SESSION['csrf_token'] ?? ''; }

    // ----------------------------------------------------------------
    // CSRF
    // ----------------------------------------------------------------

    public static function verifyCsrf(string $token): bool
    {
        return isset($_SESSION['csrf_token'])
            && hash_equals($_SESSION['csrf_token'], $token);
    }

    // ----------------------------------------------------------------
    // Passwort ändern
    // ----------------------------------------------------------------

    public function changePassword(int $userId, string $currentPassword, string $newPassword): array
    {
        $stmt = $this->db->prepare("SELECT password_hash FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($currentPassword, $user['password_hash'])) {
            return ['success' => false, 'error' => 'Aktuelles Passwort falsch.'];
        }
        if (strlen($newPassword) < 8) {
            return ['success' => false, 'error' => 'Das neue Passwort muss mindestens 8 Zeichen haben.'];
        }

        $hash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
        $this->db->prepare("UPDATE users SET password_hash = ? WHERE id = ?")->execute([$hash, $userId]);
        logAudit($userId, 'user.password_changed', 'users', $userId);
        return ['success' => true];
    }

    // ----------------------------------------------------------------
    // Brute-Force-Schutz
    // ----------------------------------------------------------------

    private function isLockedOut(string $email): bool
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM login_attempts
             WHERE email = ? AND attempted_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)"
        );
        $stmt->execute([$email, LOGIN_LOCKOUT_MINUTES]);
        return (int)$stmt->fetchColumn() >= MAX_LOGIN_ATTEMPTS;
    }

    private function recordFailedAttempt(string $email): void
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $this->db->prepare(
            "INSERT INTO login_attempts (email, ip_address, attempted_at) VALUES (?, ?, NOW())"
        )->execute([$email, $ip]);
    }

    private function clearFailedAttempts(string $email): void
    {
        $this->db->prepare("DELETE FROM login_attempts WHERE email = ?")->execute([$email]);
    }

    /** Ersetzt den DB-Event: löscht alte Einträge direkt beim Login (1% Wahrscheinlichkeit) */
    private function cleanupOldAttempts(): void
    {
        if (rand(1, 100) === 1) {
            $this->db->exec("DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)");
        }
    }
}
