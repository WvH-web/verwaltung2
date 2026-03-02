<?php
/**
 * WvH – Globale Hilfsfunktionen
 */

// ----------------------------------------------------------------
// Sicherheit
// ----------------------------------------------------------------

/** HTML-Sonderzeichen escapen (immer verwenden bei User-Output!) */
function e(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/** JSON-Antwort senden und beenden */
function jsonResponse(array $data, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/** CSRF-Token als Hidden-Field ausgeben */
function csrfField(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(\Auth\Auth::csrfToken()) . '">';
}

/** CSRF prüfen, bei Fehler abbrechen */
function requireCsrf(): void
{
    $token = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!\Auth\Auth::verifyCsrf($token)) {
        jsonResponse(['error' => 'Ungültiger CSRF-Token.'], 403);
    }
}

// ----------------------------------------------------------------
// Umleitungen
// ----------------------------------------------------------------

function redirect(string $path): void
{
    header('Location: ' . APP_URL . '/' . ltrim($path, '/'));
    exit;
}

// ----------------------------------------------------------------
// Datum & Zeit (Europe/Berlin)
// ----------------------------------------------------------------

/** Datum formatieren für Anzeige */
function formatDate(?string $date, string $format = 'd.m.Y'): string
{
    if (!$date) return '–';
    return date($format, strtotime($date));
}

/** DateTime für HTML date-Input */
function htmlDate(?string $date): string
{
    if (!$date) return '';
    return date('Y-m-d', strtotime($date));
}

/** Monat als Text: "Februar 2026" */
function monthLabel(string $yearMonth): string
{
    $months = [
        '01' => 'Januar', '02' => 'Februar', '03' => 'März',
        '04' => 'April',  '05' => 'Mai',      '06' => 'Juni',
        '07' => 'Juli',   '08' => 'August',   '09' => 'September',
        '10' => 'Oktober','11' => 'November', '12' => 'Dezember'
    ];
    [$year, $month] = explode('-', substr($yearMonth, 0, 7));
    return ($months[$month] ?? $month) . ' ' . $year;
}

// ----------------------------------------------------------------
// Zahlen & Währung
// ----------------------------------------------------------------

/** Betrag als Euro-String */
function formatEuro(float $amount): string
{
    return number_format($amount, 2, ',', '.') . ' €';
}

/** Stunden formatieren */
function formatHours(float $hours): string
{
    return number_format($hours, 2, ',', '.') . ' UE';
}

// ----------------------------------------------------------------
// Audit Log
// ----------------------------------------------------------------

function logAudit(
    int    $userId,
    string $action,
    string $entityType,
    int    $entityId,
    array  $oldValues = [],
    array  $newValues = []
): void {
    try {
        $db = getDB();
        $stmt = $db->prepare(
            "INSERT INTO audit_log
               (user_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $userId,
            $action,
            $entityType,
            $entityId,
            $oldValues ? json_encode($oldValues, JSON_UNESCAPED_UNICODE) : null,
            $newValues ? json_encode($newValues, JSON_UNESCAPED_UNICODE) : null,
            $_SERVER['REMOTE_ADDR'] ?? null,
            substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
        ]);
    } catch (\Throwable $e) {
        error_log('[WvH-Audit] Fehler: ' . $e->getMessage());
    }
}

// ----------------------------------------------------------------
// Flash-Nachrichten
// ----------------------------------------------------------------

function setFlash(string $type, string $message): void
{
    $_SESSION['flash'][$type][] = $message;
}

function getFlash(): array
{
    $flash = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flash;
}

function renderFlash(): string
{
    $flash = getFlash();
    if (empty($flash)) return '';
    $html = '';
    $map = [
        'success' => ['bg-success-subtle border-success', 'bi-check-circle-fill text-success'],
        'error'   => ['bg-danger-subtle border-danger',   'bi-x-circle-fill text-danger'],
        'warning' => ['bg-warning-subtle border-warning', 'bi-exclamation-triangle-fill text-warning'],
        'info'    => ['bg-info-subtle border-info',       'bi-info-circle-fill text-info'],
    ];
    foreach ($flash as $type => $messages) {
        [$bgClass, $iconClass] = $map[$type] ?? $map['info'];
        foreach ($messages as $msg) {
            $html .= '<div class="alert alert-dismissible border ' . $bgClass . ' d-flex align-items-center gap-2 mb-3" role="alert">';
            $html .= '<i class="bi ' . $iconClass . ' fs-5"></i>';
            $html .= '<span>' . e($msg) . '</span>';
            $html .= '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
            $html .= '</div>';
        }
    }
    return $html;
}

// ----------------------------------------------------------------
// Wochentag-Label
// ----------------------------------------------------------------

function weekdayLabel(int $day): string
{
    return ['', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag'][$day] ?? '?';
}

// ----------------------------------------------------------------
// Badge für Abrechnungsstatus
// ----------------------------------------------------------------

function billingStatusBadge(string $status): string
{
    $map = [
        'draft'           => ['secondary', 'Entwurf'],
        'closed'          => ['warning',   'Geschlossen'],
        'review'          => ['info',      'In Prüfung'],
        'confirmed'       => ['primary',   'Bestätigt'],
        'final'           => ['dark',      'Finalisiert'],
        'paid'            => ['success',   'Ausgezahlt'],
    ];
    [$color, $label] = $map[$status] ?? ['secondary', $status];
    return '<span class="badge bg-' . $color . '">' . e($label) . '</span>';
}

/** Badge für Vertretungsstatus */
function subStatusBadge(string $status): string
{
    $map = [
        'open'          => ['warning',   'Offen'],
        'claimed'       => ['info',      'Beansprucht'],
        'confirmed'     => ['success',   'Bestätigt'],
        'conflict'      => ['danger',    'Konflikt'],
        'admin_resolved'=> ['primary',   'Gelöst'],
        'cancelled'     => ['secondary', 'Verfallen'],
        'locked'        => ['dark',      'Gesperrt'],
    ];
    [$color, $label] = $map[$status] ?? ['secondary', $status];
    return '<span class="badge bg-' . $color . '">' . e($label) . '</span>';
}
