<?php
/**
 * WvH – Vertretungsplanung · Action-Handler
 * Alle POST-Aktionen der Vertretungsplanung
 */
ini_set('display_errors', 1);
error_reporting(E_ALL);
set_exception_handler(function(\Throwable $e) {
    echo "<pre>FATAL: " . $e->getMessage() . "\nFile: " . $e->getFile() . ":" . $e->getLine() . "\n" . $e->getTraceAsString() . "</pre>";
    exit;
});
require_once __DIR__ . '/../config/bootstrap.php';
use Auth\Auth;

\Auth\Auth::require();

// GET-Anfragen → zurück zur Wochenansicht
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . APP_URL . '/substitutions/index.php');
    exit;
}

requireCsrf();

$db     = getDB();
$action = $_POST['action'] ?? '';

// Rücksprung-URL
$week      = (int)($_POST['week']       ?? 0);
$mode      = in_array($_POST['mode'] ?? '', ['klasse','lehrer']) ? $_POST['mode'] : 'klasse';
$classId   = (int)($_POST['class_id']   ?? 0);
$teacherId = (int)($_POST['teacher_id'] ?? 0);
$back = APP_URL . '/substitutions/index.php'
      . '?mode='.$mode.'&week='.$week
      . '&class_id='.$classId.'&teacher_id='.$teacherId;

/* ── Hilfsfunktion: Meine Teacher-ID ────────────────── */
function getMyTeacherId(\PDO $db): ?int {
    $s = $db->prepare("SELECT id FROM teachers WHERE user_id=? LIMIT 1");
    $s->execute([\Auth\Auth::userId()]);
    return $s->fetchColumn() ?: null;
}

/* ── Hilfsfunktion: lesson_instance sicherstellen ───── */
function ensureLessonInstance(\PDO $db, int $entryId, string $date): int {
    $s = $db->prepare(
        "SELECT id FROM lesson_instances WHERE entry_id=? AND lesson_date=? LIMIT 1"
    );
    $s->execute([$entryId, $date]);
    $existing = $s->fetchColumn();
    if ($existing) return (int)$existing;

    $db->prepare(
        "INSERT INTO lesson_instances (entry_id, lesson_date, status, is_billable)
         VALUES (?, ?, 'released', 1)"
    )->execute([$entryId, $date]);
    return (int)$db->lastInsertId();
}

/* ── Hilfsfunktion: Konflikt prüfen ─────────────────── */
function hasConflict(\PDO $db, int $substituteTid, string $date, int $subId): bool {
    // Welche Zeiten hat die zu übernehmende Stunde?
    $s = $db->prepare(
        "SELECT te.time_start, te.time_end
         FROM substitutions s
         JOIN lesson_instances li ON li.id = s.lesson_id
         JOIN timetable_entries te ON te.id = li.entry_id
         WHERE s.id = ?"
    );
    $s->execute([$subId]);
    $subLesson = $s->fetch();
    if (!$subLesson) return false;

    $weekday = date('N', strtotime($date)); // 1=Mon, 5=Fri

    // Hat der Vertreter an diesem Datum+Uhrzeit schon eine eigene Stunde?
    $s2 = $db->prepare(
        "SELECT COUNT(*) FROM timetable_entries te
         JOIN timetable_plans tp ON tp.id = te.plan_id
         WHERE te.teacher_id = ?
           AND te.weekday = ?
           AND tp.valid_from <= ? AND tp.valid_until >= ?
           AND te.time_start < ? AND te.time_end > ?"
    );
    $s2->execute([
        $substituteTid, $weekday, $date, $date,
        $subLesson['time_end'], $subLesson['time_start']
    ]);
    return (int)$s2->fetchColumn() > 0;
}

/* ── Hilfsfunktion: Benachrichtigung erstellen ──────── */
function notify(\PDO $db, int $subId, int $recipientUserId, string $type, string $msg): void {
    try {
        $db->prepare(
            "INSERT INTO substitution_notifications
             (substitution_id, recipient_id, type, message)
             VALUES (?,?,?,?)"
        )->execute([$subId, $recipientUserId, $type, $msg]);
    } catch (\Exception $e) { /* Tabelle existiert noch nicht */ }
}

/* ══════════════════════════════════════════════════════
   ACTION: release – Stunde freigeben
══════════════════════════════════════════════════════ */
if ($action === 'release') {
    $entryId = (int)($_POST['entry_id']    ?? 0);
    $date    = $_POST['lesson_date'] ?? '';
    $notes   = trim($_POST['notes'] ?? '');

    if (!$entryId || !$date) {
        setFlash('error', 'Ungültige Eingabe.');
        header('Location: '.$back); exit;
    }

    // Nur eigene Stunde oder Verwaltung
    $myTid = getMyTeacherId($db);
    if (!\Auth\Auth::isVerwaltung()) {
        $s = $db->prepare("SELECT teacher_id FROM timetable_entries WHERE id=? LIMIT 1");
        $s->execute([$entryId]);
        $ownerTid = (int)$s->fetchColumn();
        if ($ownerTid !== $myTid) {
            setFlash('error', 'Keine Berechtigung, diese Stunde freizugeben.');
            header('Location: '.$back); exit;
        }
    }

    // Doppelstunden-Scope: single | first | second | both
    $doubleScope    = $_POST['double_scope']     ?? 'single';
    $partnerEntryId = (int)($_POST['partner_entry_id'] ?? 0);

    // Hilfsfunktion: eine Stunde freigeben
    $releaseOne = function(int $eid, string $dt, ?int $myTid, string $notes) use ($db): void {
        $lessonId = ensureLessonInstance($db, $eid, $dt);

        $existing = $db->prepare(
            "SELECT id, status FROM substitutions WHERE lesson_id=? AND status NOT IN ('cancelled','locked') LIMIT 1"
        );
        $existing->execute([$lessonId]);
        $existRow = $existing->fetch();
        if ($existRow) {
            if ($existRow['status'] === 'confirmed') {
                $db->prepare("UPDATE substitutions SET status='cancelled' WHERE id=?")
                   ->execute([$existRow['id']]);
            } else {
                throw new \RuntimeException('Für diese Stunde gibt es bereits eine offene Vertretung.');
            }
        }

        $s = $db->prepare("SELECT teacher_id FROM timetable_entries WHERE id=? LIMIT 1");
        $s->execute([$eid]);
        $originalTid = (int)$s->fetchColumn();

        $db->prepare(
            "INSERT INTO substitutions
             (lesson_id, original_teacher_id, status, notes, released_by, released_at, billing_month)
             VALUES (?, ?, 'open', ?, ?, NOW(), ?)"
        )->execute([
            $lessonId, $originalTid,
            $notes ?: null,
            $myTid,
            date('Y-m-01', strtotime($dt))
        ]);

        $db->prepare("UPDATE lesson_instances SET status='released' WHERE id=?")->execute([$lessonId]);
    };

    $db->beginTransaction();
    try {
        $releaseOne($entryId, $date, $myTid, $notes);

        // Bei "both" auch Partner freigeben
        if ($doubleScope === 'both' && $partnerEntryId) {
            $releaseOne($partnerEntryId, $date, $myTid, $notes);
        }

        $db->commit();
        logAudit(\Auth\Auth::userId(), 'substitution.released', 'lesson_instances', $entryId);

        $msg = 'Stunde vom '.date('d.m.Y', strtotime($date)).' freigegeben.';
        if ($doubleScope === 'both' && $partnerEntryId) {
            $msg = 'Doppelstunde vom '.date('d.m.Y', strtotime($date)).' komplett freigegeben.';
        } elseif (in_array($doubleScope, ['first','second'])) {
            $which = $doubleScope === 'first' ? '1. Stunde' : '2. Stunde';
            $msg = $which.' der Doppelstunde vom '.date('d.m.Y', strtotime($date)).' freigegeben.';
        }
        setFlash('success', $msg);
    } catch (\Exception $e) {
        $db->rollBack();
        setFlash('error', $e->getMessage());
    }
    header('Location: '.$back); exit;
}

/* ══════════════════════════════════════════════════════
   ACTION: claim – Vertretung übernehmen
══════════════════════════════════════════════════════ */
if ($action === 'claim') {
    $subId = (int)($_POST['sub_id'] ?? 0);
    if (!$subId) { setFlash('error','Ungültige Vertretungs-ID.'); header('Location:'.$back); exit; }

    $myTid = getMyTeacherId($db);
    if (!$myTid) { setFlash('error','Kein Lehrerprofil gefunden.'); header('Location:'.$back); exit; }

    $sub = $db->prepare("SELECT * FROM substitutions WHERE id=? AND status='open' LIMIT 1");
    $sub->execute([$subId]);
    $sub = $sub->fetch();
    if (!$sub) { setFlash('error','Vertretung nicht mehr verfügbar.'); header('Location:'.$back); exit; }

    // Darf man nicht die eigene Stunde übernehmen
    if ((int)$sub['original_teacher_id'] === $myTid && !\Auth\Auth::isVerwaltung()) {
        setFlash('error', 'Du kannst nicht deine eigene Stunde übernehmen.');
        header('Location:'.$back); exit;
    }

    // Datum der Stunde holen
    $li = $db->prepare("SELECT li.lesson_date FROM lesson_instances li WHERE li.id=? LIMIT 1");
    $li->execute([$sub['lesson_id']]);
    $lessonDate = $li->fetchColumn();

    $conflict = hasConflict($db, $myTid, $lessonDate, $subId);
    $newStatus = $conflict ? 'conflict' : 'claimed';

    $db->prepare(
        "UPDATE substitutions
         SET substitute_teacher_id=?, status=?, claimed_at=NOW()
         WHERE id=?"
    )->execute([$myTid, $newStatus, $subId]);

    $db->prepare("UPDATE lesson_instances SET status='substituted' WHERE id=?")
       ->execute([$sub['lesson_id']]);

    // Originallehrer benachrichtigen
    $origUser = $db->prepare(
        "SELECT u.id FROM teachers t JOIN users u ON u.id=t.user_id WHERE t.id=? LIMIT 1"
    );
    $origUser->execute([$sub['original_teacher_id']]);
    $origUserId = $origUser->fetchColumn();
    if ($origUserId) {
        $myName = \Auth\Auth::userName();
        notify($db, $subId, $origUserId, 'confirmed',
            "{$myName} übernimmt deine Vertretung." . ($conflict ? ' ⚠ Konflikt!' : ''));
    }

    logAudit(\Auth\Auth::userId(), 'substitution.claimed', 'substitutions', $subId);
    setFlash($conflict ? 'warning' : 'success',
        $conflict ? 'Übernommen – aber Stundenplan-Konflikt erkannt!' : 'Vertretung erfolgreich übernommen.');
    header('Location:'.$back); exit;
}

/* ══════════════════════════════════════════════════════
   ACTION: self_assign – Kollege trägt sich selbst ein
══════════════════════════════════════════════════════ */
if ($action === 'self_assign') {
    $entryId = (int)($_POST['entry_id']    ?? 0);
    $date    = $_POST['lesson_date'] ?? '';
    $notes   = trim($_POST['notes'] ?? '');

    if (!$entryId || !$date) { setFlash('error','Ungültige Eingabe.'); header('Location:'.$back); exit; }

    $myTid = getMyTeacherId($db);
    if (!$myTid) { setFlash('error','Kein Lehrerprofil.'); header('Location:'.$back); exit; }

    // Original-Lehrer dieser Stunde
    $s = $db->prepare("SELECT teacher_id FROM timetable_entries WHERE id=? LIMIT 1");
    $s->execute([$entryId]);
    $originalTid = (int)$s->fetchColumn();

    if ($originalTid === $myTid) {
        setFlash('error', 'Das ist deine eigene Stunde.');
        header('Location:'.$back); exit;
    }

    $db->beginTransaction();
    try {
        $lessonId = ensureLessonInstance($db, $entryId, $date);

        // Schon eine Vertretung?
        $ex = $db->prepare(
            "SELECT id FROM substitutions WHERE lesson_id=? AND status NOT IN ('cancelled','locked') LIMIT 1"
        );
        $ex->execute([$lessonId]);
        if ($ex->fetchColumn()) {
            $db->rollBack();
            setFlash('warning','Für diese Stunde gibt es bereits eine Vertretungsanfrage.');
            header('Location:'.$back); exit;
        }

        $db->prepare(
            "INSERT INTO substitutions
             (lesson_id, original_teacher_id, substitute_teacher_id,
              self_assigned_by, self_assigned_at, status, notes, billing_month)
             VALUES (?,?,?,?,NOW(),'pending_confirm',?,?)"
        )->execute([
            $lessonId, $originalTid, $myTid, $myTid,
            $notes ?: null,
            date('Y-m-01', strtotime($date))
        ]);
        $subId = (int)$db->lastInsertId();

        $db->prepare("UPDATE lesson_instances SET status='released' WHERE id=?")->execute([$lessonId]);
        $db->commit();

        // Original-Lehrer benachrichtigen
        $origUser = $db->prepare(
            "SELECT u.id FROM teachers t JOIN users u ON u.id=t.user_id WHERE t.id=? LIMIT 1"
        );
        $origUser->execute([$originalTid]);
        $origUserId = $origUser->fetchColumn();
        if ($origUserId) {
            $myName = \Auth\Auth::userName();
            notify($db, $subId, $origUserId, 'pending_confirm',
                "{$myName} möchte am ".date('d.m.Y', strtotime($date))
                ." deine Stunde vertreten. Bitte bestätigen oder ablehnen.");
        }

        // Verwaltung informieren (alle Admins)
        $admins = $db->query(
            "SELECT u.id FROM users u JOIN roles r ON r.id=u.role_id
             WHERE r.name IN ('admin','verwaltung') AND u.is_active=1"
        )->fetchAll();
        foreach ($admins as $adm) {
            notify($db, $subId, $adm['id'], 'pending_confirm',
                \Auth\Auth::userName().' hat sich als Vertreter eingetragen (warte auf Bestätigung).');
        }

        logAudit(\Auth\Auth::userId(), 'substitution.self_assigned', 'substitutions', $subId);
        setFlash('success', 'Eingetragen. Die Lehrkraft wird benachrichtigt.');
    } catch (\Exception $e) {
        $db->rollBack();
        setFlash('error', 'Fehler: '.$e->getMessage());
    }
    header('Location:'.$back); exit;
}

/* ══════════════════════════════════════════════════════
   ACTION: confirm – Originallehrer bestätigt
══════════════════════════════════════════════════════ */
if ($action === 'confirm') {
    $subId = (int)($_POST['sub_id'] ?? 0);
    $myTid = getMyTeacherId($db);

    $sub = $db->prepare(
        "SELECT * FROM substitutions WHERE id=? AND status='pending_confirm' LIMIT 1"
    );
    $sub->execute([$subId]);
    $sub = $sub->fetch();

    if (!$sub || ((int)$sub['original_teacher_id'] !== $myTid && !\Auth\Auth::isVerwaltung())) {
        setFlash('error','Keine Berechtigung oder Vertretung nicht gefunden.');
        header('Location:'.$back); exit;
    }

    $db->prepare(
        "UPDATE substitutions
         SET status='confirmed', confirmed_at=NOW(), confirmed_by=?
         WHERE id=?"
    )->execute([\Auth\Auth::userId(), $subId]);

    // Vertreter benachrichtigen
    $stUser = $db->prepare(
        "SELECT u.id FROM teachers t JOIN users u ON u.id=t.user_id WHERE t.id=? LIMIT 1"
    );
    $stUser->execute([$sub['substitute_teacher_id']]);
    $stUserId = $stUser->fetchColumn();
    if ($stUserId) {
        notify($db, $subId, $stUserId, 'confirmed',
            'Deine Vertretung wurde bestätigt. ✓');
    }

    logAudit(\Auth\Auth::userId(), 'substitution.confirmed', 'substitutions', $subId);
    setFlash('success', 'Vertretung bestätigt.');
    header('Location:'.$back); exit;
}

/* ══════════════════════════════════════════════════════
   ACTION: reject – Originallehrer lehnt ab
══════════════════════════════════════════════════════ */
if ($action === 'reject') {
    $subId = (int)($_POST['sub_id'] ?? 0);
    $myTid = getMyTeacherId($db);

    $sub = $db->prepare(
        "SELECT * FROM substitutions WHERE id=? AND status='pending_confirm' LIMIT 1"
    );
    $sub->execute([$subId]);
    $sub = $sub->fetch();

    if (!$sub || ((int)$sub['original_teacher_id'] !== $myTid && !\Auth\Auth::isVerwaltung())) {
        setFlash('error', 'Keine Berechtigung.');
        header('Location:'.$back); exit;
    }

    $db->prepare("UPDATE substitutions SET status='open', substitute_teacher_id=NULL,
                  self_assigned_by=NULL, self_assigned_at=NULL WHERE id=?")
       ->execute([$subId]);
    $db->prepare("UPDATE lesson_instances SET status='released' WHERE id=?")
       ->execute([$sub['lesson_id']]);

    // Vertreter benachrichtigen
    $stUser = $db->prepare(
        "SELECT u.id FROM teachers t JOIN users u ON u.id=t.user_id WHERE t.id=? LIMIT 1"
    );
    $stUser->execute([$sub['substitute_teacher_id']]);
    $stUserId = $stUser->fetchColumn();
    if ($stUserId) {
        notify($db, $subId, $stUserId, 'rejected',
            'Deine Vertretungsanfrage wurde abgelehnt. Die Stunde ist wieder offen.');
    }

    logAudit(\Auth\Auth::userId(), 'substitution.rejected', 'substitutions', $subId);
    setFlash('warning', 'Vertretungsanfrage abgelehnt. Stunde wieder offen.');
    header('Location:'.$back); exit;
}

/* ══════════════════════════════════════════════════════
   ACTION: admin_confirm – Verwaltung bestätigt pending
══════════════════════════════════════════════════════ */
if ($action === 'admin_confirm') {
    \Auth\Auth::requireRole(ROLE_ADMIN, ROLE_VERWALTUNG);
    $subId       = (int)($_POST['sub_id']        ?? 0);
    $newTeachId  = (int)($_POST['sub_teacher_id'] ?? 0);
    $notes       = trim($_POST['notes'] ?? '');

    $db->beginTransaction();
    try {
        $update = "UPDATE substitutions SET status='confirmed', confirmed_at=NOW(), confirmed_by=?";
        $params = [\Auth\Auth::userId()];
        if ($newTeachId) {
            $update .= ", substitute_teacher_id=?";
            $params[] = $newTeachId;
        }
        if ($notes) { $update .= ", resolution_notes=?"; $params[] = $notes; }
        $update .= " WHERE id=?";
        $params[] = $subId;
        $db->prepare($update)->execute($params);
        $db->commit();
        logAudit(\Auth\Auth::userId(), 'substitution.admin_confirmed', 'substitutions', $subId);
        setFlash('success', 'Vertretung bestätigt.');
    } catch (\Exception $e) {
        $db->rollBack();
        setFlash('error', $e->getMessage());
    }
    header('Location:'.$back); exit;
}

/* ══════════════════════════════════════════════════════
   ACTION: admin_direct – Verwaltung vergibt direkt
══════════════════════════════════════════════════════ */
if ($action === 'admin_direct') {
    \Auth\Auth::requireRole(ROLE_ADMIN, ROLE_VERWALTUNG);
    $entryId    = (int)($_POST['entry_id']       ?? 0);
    $date       = $_POST['lesson_date'] ?? '';
    $newTeachId = (int)($_POST['sub_teacher_id'] ?? 0);
    $notes      = trim($_POST['notes'] ?? '');

    if (!$entryId || !$date || !$newTeachId) {
        setFlash('error', 'Bitte Lehrkraft auswählen.');
        header('Location:'.$back); exit;
    }

    $db->beginTransaction();
    try {
        $lessonId = ensureLessonInstance($db, $entryId, $date);

        $s = $db->prepare("SELECT teacher_id FROM timetable_entries WHERE id=? LIMIT 1");
        $s->execute([$entryId]);
        $originalTid = (int)$s->fetchColumn();

        // Existierende Vertretung finden (auch bestätigte für Neuzuweisung)
        $ex = $db->prepare(
            "SELECT id FROM substitutions WHERE lesson_id=? AND status NOT IN ('locked','cancelled') ORDER BY FIELD(status,'confirmed','open','pending_confirm') LIMIT 1"
        );
        $ex->execute([$lessonId]);
        $existSubId = $ex->fetchColumn();

        if ($existSubId) {
            $db->prepare(
                "UPDATE substitutions SET substitute_teacher_id=?, status='confirmed',
                 confirmed_at=NOW(), confirmed_by=?, resolution_notes=?
                 WHERE id=?"
            )->execute([$newTeachId, \Auth\Auth::userId(), $notes ?: null, $existSubId]);
            $subId = $existSubId;
        } else {
            $db->prepare(
                "INSERT INTO substitutions
                 (lesson_id, original_teacher_id, substitute_teacher_id,
                  status, confirmed_at, confirmed_by, notes, billing_month)
                 VALUES (?,?,?,'confirmed',NOW(),?,?,?)"
            )->execute([
                $lessonId, $originalTid, $newTeachId,
                \Auth\Auth::userId(), $notes ?: null,
                date('Y-m-01', strtotime($date))
            ]);
            $subId = (int)$db->lastInsertId();
        }

        $db->prepare("UPDATE lesson_instances SET status='substituted' WHERE id=?")
           ->execute([$lessonId]);
        $db->commit();

        // Vertreter informieren
        $stUser = $db->prepare(
            "SELECT u.id FROM teachers t JOIN users u ON u.id=t.user_id WHERE t.id=? LIMIT 1"
        );
        $stUser->execute([$newTeachId]);
        $stUserId = $stUser->fetchColumn();
        if ($stUserId) {
            notify($db, $subId, $stUserId, 'assigned',
                'Du wurdest als Vertreter am '.date('d.m.Y', strtotime($date)).' eingetragen.');
        }

        logAudit(\Auth\Auth::userId(), 'substitution.admin_direct', 'substitutions', $subId);
        setFlash('success', 'Vertretung direkt vergeben.');
    } catch (\Exception $e) {
        $db->rollBack();
        setFlash('error', $e->getMessage());
    }
    header('Location:'.$back); exit;
}

// Fallback
setFlash('error', 'Unbekannte Aktion: '.htmlspecialchars($action));
header('Location:'.$back); exit;
