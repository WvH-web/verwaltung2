<?php
ini_set('display_errors', 1);
ini_set('log_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/../config/bootstrap.php';
use Auth\Auth;
Auth::require();
$db = getDB();
echo "<pre>PHP " . PHP_VERSION . " | User: " . Auth::userId() . "\n";

// Reproduce EXACTLY what index.php does, step by step
echo "1. myTeacherId... ";
$stmt = $db->prepare("SELECT id FROM teachers WHERE user_id=? LIMIT 1");
$stmt->execute([Auth::userId()]);
$myTeacherId = $stmt->fetchColumn() ?: null;
echo "OK ($myTeacherId)\n";

echo "2. Week calc... ";
$weekOffset = 0;
$monday = new \DateTime('monday this week');
if ($weekOffset < 0) $monday->modify(abs($weekOffset) . ' weeks ago monday');
$friday = clone $monday; $friday->modify('+4 days');
$weekStart = $monday->format('Y-m-d');
echo "OK ($weekStart)\n";

echo "3. Plan... ";
$planRow = $db->query("SELECT id FROM timetable_plans WHERE valid_from<=CURDATE() AND valid_until>=CURDATE() LIMIT 1")->fetch();
$planId = $planRow ? (int)$planRow['id'] : 0;
echo "OK (planId=$planId)\n";

echo "4. freeDays... ";
$freeDays = [];
$stmt = $db->prepare("SELECT name,date_from,date_until FROM school_holidays WHERE is_school_free=1 AND date_from<=? AND date_until>=?");
$stmt->execute([$friday->format('Y-m-d'), $weekStart]);
echo "OK\n";

echo "5. klassenList... ";
$klassenList = $db->prepare("SELECT DISTINCT c.id, c.name FROM classes c JOIN timetable_entry_classes tec ON tec.class_id=c.id JOIN timetable_entries te ON te.id=tec.entry_id WHERE te.plan_id=? AND c.is_active=1 ORDER BY c.name");
$klassenList->execute([$planId]);
$klassenList = $klassenList->fetchAll();
echo "OK (" . count($klassenList) . " classes)\n";

echo "6. filterClass default... ";
$filterClass = (int)($_GET['class_id'] ?? 0);
if (!$filterClass && $klassenList) $filterClass = (int)$klassenList[0]['id'];
echo "OK ($filterClass)\n";

echo "7. openSubs query... ";
$openSubs = $db->query("SELECT s.id, s.status, li.lesson_date, te.time_start, te.time_end, sub_e.name AS subject_name, CONCAT(u.first_name,' ',u.last_name) AS teacher_name, GROUP_CONCAT(DISTINCT c.name ORDER BY c.name SEPARATOR ', ') AS classes FROM substitutions s JOIN lesson_instances li ON li.id=s.lesson_id JOIN timetable_entries te ON te.id=li.entry_id JOIN subjects sub_e ON sub_e.id=te.subject_id JOIN teachers t ON t.id=s.original_teacher_id JOIN users u ON u.id=t.user_id LEFT JOIN timetable_entry_classes tec ON tec.entry_id=te.id LEFT JOIN classes c ON c.id=tec.class_id WHERE s.status IN ('open','pending_confirm','conflict') GROUP BY s.id ORDER BY li.lesson_date,te.time_start")->fetchAll();
echo "OK\n";

echo "8. notifs query... ";
$notifs = $db->prepare("SELECT n.id, n.type, n.message, n.created_at, s.lesson_id FROM substitution_notifications n JOIN substitutions s ON s.id=n.substitution_id WHERE n.recipient_id=? AND n.is_read=0 ORDER BY n.created_at DESC LIMIT 5");
$notifs->execute([Auth::userId()]);
$notifs = $notifs->fetchAll();
echo "OK\n";

echo "9. Main week data loop (Mon-Fri)... ";
$weekData = [];
for ($wd = 1; $wd <= 5; $wd++) {
    $dt = clone $monday;
    $dt->modify('+' . ($wd-1) . ' days');
    $date = $dt->format('Y-m-d');
    
    $stmt = $db->prepare(
        "SELECT te.id AS entry_id, te.weekday, te.time_start, te.time_end,
                sub_j.name AS subject_name,
                t.id AS teacher_id,
                CONCAT(u.first_name,' ',u.last_name) AS teacher_name,
                GROUP_CONCAT(DISTINCT c.name ORDER BY c.name SEPARATOR ', ') AS classes,
                li.id AS instance_id, li.status AS instance_status,
                sv.id AS sub_id, sv.status AS sub_status,
                sv.self_assigned_by, sv.notes AS sub_notes,
                CONCAT(su.first_name,' ',su.last_name) AS substitute_name
         FROM timetable_entries te
         JOIN timetable_plans tp ON tp.id=te.plan_id
         JOIN teachers t ON t.id=te.teacher_id
         JOIN users u ON u.id=t.user_id
         JOIN subjects sub_j ON sub_j.id=te.subject_id
         LEFT JOIN timetable_entry_classes tec ON tec.entry_id=te.id
         LEFT JOIN classes c ON c.id=tec.class_id
         LEFT JOIN lesson_instances li ON li.entry_id=te.id
               AND li.lesson_date = DATE_ADD(?, INTERVAL (te.weekday - 1) DAY)
         LEFT JOIN substitutions sv ON sv.lesson_id=li.id AND sv.status NOT IN ('cancelled','locked')
         LEFT JOIN teachers st ON st.id=sv.substitute_teacher_id
         LEFT JOIN users su ON su.id=st.user_id
         WHERE tp.valid_from<=CURDATE() AND tp.valid_until>=CURDATE()
           AND EXISTS (SELECT 1 FROM timetable_entry_classes tec2 WHERE tec2.entry_id=te.id AND tec2.class_id=?)
         GROUP BY te.id, li.id, sv.id
         ORDER BY te.time_start"
    );
    $stmt->execute([$weekStart, $filterClass]);
    $weekData[$wd] = $stmt->fetchAll();
    echo "wd$wd:" . count($weekData[$wd]) . " ";
}
echo "OK\n";

echo "10. include header.php... ";
$pageTitle = 'Test'; $breadcrumbs = [];
include TEMPLATE_PATH . '/layouts/header.php';
echo "OK\n";

echo "=== ALL STEPS PASSED ===\n</pre>";
