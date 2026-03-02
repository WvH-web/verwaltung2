<?php
/**
 * WvH – AJAX Konflikt-Check
 * GET: ?sub_id=X  → JSON {"conflict": true/false}
 */
require_once __DIR__ . '/../config/bootstrap.php';
use Auth\Auth;

Auth::require();
header('Content-Type: application/json');

$db    = getDB();
$subId = (int)($_GET['sub_id'] ?? 0);

if (!$subId) {
    echo json_encode(['conflict' => false]);
    exit;
}

// Mein Teacher-ID
$s = $db->prepare("SELECT id FROM teachers WHERE user_id=? LIMIT 1");
$s->execute([Auth::userId()]);
$myTid = (int)($s->fetchColumn() ?: 0);

if (!$myTid) {
    echo json_encode(['conflict' => false]);
    exit;
}

// Vertretungsstunde Details
$s = $db->prepare(
    "SELECT li.lesson_date, te.time_start, te.time_end
     FROM substitutions s
     JOIN lesson_instances li ON li.id = s.lesson_id
     JOIN timetable_entries te ON te.id = li.entry_id
     WHERE s.id = ? LIMIT 1"
);
$s->execute([$subId]);
$sub = $s->fetch();

if (!$sub) {
    echo json_encode(['conflict' => false]);
    exit;
}

$weekday = (int)date('N', strtotime($sub['lesson_date'])); // 1=Mo,5=Fr

// Eigene Stunden an dem Wochentag + Uhrzeit
$s2 = $db->prepare(
    "SELECT COUNT(*) FROM timetable_entries te
     JOIN timetable_plans tp ON tp.id = te.plan_id
     WHERE te.teacher_id = ?
       AND te.weekday = ?
       AND tp.valid_from <= ? AND tp.valid_until >= ?
       AND te.time_start < ? AND te.time_end > ?"
);
$s2->execute([
    $myTid, $weekday,
    $sub['lesson_date'], $sub['lesson_date'],
    $sub['time_end'], $sub['time_start']
]);
$conflict = (int)$s2->fetchColumn() > 0;

echo json_encode(['conflict' => $conflict]);
