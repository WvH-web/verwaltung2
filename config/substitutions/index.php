<?php
/**
 * WvH – Vertretungsplanung · Wochenkalender
 */
require_once __DIR__ . '/../config/bootstrap.php';
use Auth\Auth;

Auth::require();

$pageTitle   = 'Vertretungsplanung';
$breadcrumbs = ['Vertretungsplanung' => null];
$db = getDB();

/* ── Mein Teacher-Profil ──────────────────────────────── */
$myTeacherId = null;
$stmt = $db->prepare("SELECT id FROM teachers WHERE user_id=? LIMIT 1");
$stmt->execute([Auth::userId()]);
$myTeacherId = $stmt->fetchColumn() ?: null;

/* ── Benachrichtigung als gelesen markieren ───────────── */
if (isset($_GET['action']) && $_GET['action'] === 'read_notif') {
    $nid = (int)($_GET['id'] ?? 0);
    if ($nid) {
        try {
            $db->prepare("UPDATE substitution_notifications SET is_read=1 WHERE id=? AND recipient_id=?")
               ->execute([$nid, Auth::userId()]);
        } catch (\Exception $e) {}
    }
    $back = '?mode=' . ($_GET['mode'] ?? 'klasse')
          . '&week=' . (int)($_GET['week'] ?? 0)
          . '&class_id=' . (int)($_GET['class_id'] ?? 0);
    header('Location: ' . APP_URL . '/substitutions/index.php' . $back);
    exit;
}

/* ── Aktive Woche ─────────────────────────────────────── */
$weekOffset = (int)($_GET['week'] ?? 0);
$monday = new \DateTime('monday this week');
if ($weekOffset > 0) $monday->modify('+' . $weekOffset . ' weeks');
if ($weekOffset < 0) $monday->modify(abs($weekOffset) . ' weeks ago monday');
$friday    = clone $monday;
$friday->modify('+4 days');
$weekStart = $monday->format('Y-m-d');
$weekEnd   = $friday->format('Y-m-d');

/* ── Modus ────────────────────────────────────────────── */
$mode        = in_array($_GET['mode'] ?? '', ['klasse','lehrer']) ? $_GET['mode'] : 'klasse';
$filterClass = (int)($_GET['class_id']   ?? 0);
$filterTeach = (int)($_GET['teacher_id'] ?? 0);

/* ── Aktiver Plan ─────────────────────────────────────── */
$planId = 0;
$planRow = $db->query(
    "SELECT id FROM timetable_plans WHERE valid_from<=CURDATE() AND valid_until>=CURDATE() LIMIT 1"
)->fetch();
if ($planRow) $planId = (int)$planRow['id'];

/* ── Schulfreie Tage ──────────────────────────────────── */
$freeDays = []; // 'Y-m-d' => name

// aus school_holidays
try {
    $stmt = $db->prepare(
        "SELECT name,date_from,date_until FROM school_holidays
         WHERE is_school_free=1 AND date_from<=? AND date_until>=?"
    );
    $stmt->execute([$weekEnd, $weekStart]);
    foreach ($stmt->fetchAll() as $h) {
        $c = clone $monday;
        while ($c <= $friday) {
            $ds = $c->format('Y-m-d');
            if ($h['date_from'] <= $ds && $h['date_until'] >= $ds)
                $freeDays[$ds] = $h['name'];
            $c->modify('+1 day');
        }
    }
} catch (\Exception $e) {}

// aus school_calendar
try {
    $stmt = $db->prepare(
        "SELECT name,date_from,date_until FROM school_calendar
         WHERE affects_all=1 AND date_from<=? AND date_until>=?"
    );
    $stmt->execute([$weekEnd, $weekStart]);
    foreach ($stmt->fetchAll() as $ev) {
        $c = clone $monday;
        while ($c <= $friday) {
            $ds = $c->format('Y-m-d');
            if ($ev['date_from'] <= $ds && $ev['date_until'] >= $ds)
                $freeDays[$ds] = ($freeDays[$ds] ?? '') . ($freeDays[$ds] ? ' + ' : '') . $ev['name'];
            $c->modify('+1 day');
        }
    }
} catch (\Exception $e) {}

/* ── Dropdowns ────────────────────────────────────────── */
$klassenList = [];
$teacherList = [];
if ($planId) {
    $klassenList = $db->query(
        "SELECT DISTINCT c.id, c.name
         FROM classes c
         JOIN timetable_entry_classes tec ON tec.class_id=c.id
         JOIN timetable_entries te ON te.id=tec.entry_id
         WHERE te.plan_id=$planId ORDER BY c.name"
    )->fetchAll();

    $teacherList = $db->query(
        "SELECT DISTINCT t.id, CONCAT(u.first_name,' ',u.last_name) AS full_name
         FROM timetable_entries te
         JOIN teachers t ON t.id=te.teacher_id
         JOIN users u ON u.id=t.user_id
         WHERE te.plan_id=$planId ORDER BY u.last_name,u.first_name"
    )->fetchAll();
}

// Standard: Klasse 1a
if ($mode === 'klasse' && !$filterClass && !empty($klassenList)) {
    foreach ($klassenList as $kl) {
        if (strtolower($kl['name']) === '1a') { $filterClass = (int)$kl['id']; break; }
    }
    if (!$filterClass) $filterClass = (int)($klassenList[0]['id'] ?? 0);
}

/* ── Stunden + Vertretungen dieser Woche ──────────────── */
$lessons = [];
if ($planId) {
    // Wochentag → konkretes Datum
    // te.weekday=1 → Montag = weekStart
    $sql = "SELECT
                te.id AS entry_id,
                te.weekday,
                te.time_start,
                te.time_end,
                te.is_double_first,
                te.is_double_second,
                sub_e.name AS subject_name,
                sub_e.color AS subject_color,
                t.id AS teacher_id,
                CONCAT(u.first_name,' ',u.last_name) AS teacher_name,
                GROUP_CONCAT(DISTINCT c.name ORDER BY c.name SEPARATOR ', ') AS classes,
                GROUP_CONCAT(DISTINCT c.id   ORDER BY c.name SEPARATOR ',')  AS class_id_list,
                li.id     AS lesson_instance_id,
                li.status AS lesson_status,
                s.id      AS sub_id,
                s.status  AS sub_status,
                s.substitute_teacher_id,
                CONCAT(su.first_name,' ',su.last_name) AS substitute_name,
                s.self_assigned_by,
                s.notes   AS sub_notes
            FROM timetable_entries te
            JOIN teachers t    ON t.id    = te.teacher_id
            JOIN users u       ON u.id    = t.user_id
            JOIN subjects sub_e ON sub_e.id = te.subject_id
            LEFT JOIN timetable_entry_classes tec ON tec.entry_id = te.id
            LEFT JOIN classes c ON c.id = tec.class_id
            LEFT JOIN lesson_instances li
                   ON li.entry_id = te.id
                  AND li.lesson_date = DATE_ADD(?, INTERVAL (te.weekday - 1) DAY)
            LEFT JOIN substitutions s
                   ON s.lesson_id = li.id
                  AND s.status NOT IN ('cancelled','locked')
            LEFT JOIN teachers st ON st.id = s.substitute_teacher_id
            LEFT JOIN users su    ON su.id = st.user_id
            WHERE te.plan_id = ?";

    $params = [$weekStart, $planId];

    if ($mode === 'klasse' && $filterClass) {
        $sql .= " AND te.id IN (SELECT entry_id FROM timetable_entry_classes WHERE class_id=?)";
        $params[] = $filterClass;
    } elseif ($mode === 'lehrer' && $filterTeach) {
        $sql .= " AND te.teacher_id = ?";
        $params[] = $filterTeach;
    }

    $sql .= " GROUP BY te.id, li.id, s.id ORDER BY te.weekday, te.time_start";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $lessons = $stmt->fetchAll();
}

/* ── Zeitslots ────────────────────────────────────────── */
$slots = [];
foreach ($lessons as $l) {
    $k = $l['time_start'];
    if (!isset($slots[$k])) $slots[$k] = ['start'=>$l['time_start'],'end'=>$l['time_end']];
}
ksort($slots);

/* ── Offene Vertretungen Sidebar ─────────────────────── */
$openSubs = $db->query(
    "SELECT s.id, s.status, li.lesson_date, te.time_start,
            sub_e.name AS subject_name,
            CONCAT(uo.first_name,' ',uo.last_name) AS original_name,
            GROUP_CONCAT(DISTINCT c.name ORDER BY c.name SEPARATOR ', ') AS classes
     FROM substitutions s
     JOIN lesson_instances li ON li.id = s.lesson_id
     JOIN timetable_entries te ON te.id = li.entry_id
     JOIN subjects sub_e ON sub_e.id = te.subject_id
     JOIN teachers ot ON ot.id = s.original_teacher_id
     JOIN users uo ON uo.id = ot.user_id
     LEFT JOIN timetable_entry_classes tec ON tec.entry_id = te.id
     LEFT JOIN classes c ON c.id = tec.class_id
     WHERE s.status IN ('open','pending_confirm','conflict')
     GROUP BY s.id
     ORDER BY li.lesson_date, te.time_start
     LIMIT 20"
)->fetchAll();

/* ── Meine Benachrichtigungen ─────────────────────────── */
$myNotifs = [];
try {
    $stmt = $db->prepare(
        "SELECT sn.id, sn.type, sn.message, li.lesson_date, te.time_start, sub_e.name AS subject_name
         FROM substitution_notifications sn
         JOIN substitutions s ON s.id = sn.substitution_id
         JOIN lesson_instances li ON li.id = s.lesson_id
         JOIN timetable_entries te ON te.id = li.entry_id
         JOIN subjects sub_e ON sub_e.id = te.subject_id
         WHERE sn.recipient_id=? AND sn.is_read=0
         ORDER BY sn.created_at DESC LIMIT 10"
    );
    $stmt->execute([Auth::userId()]);
    $myNotifs = $stmt->fetchAll();
} catch (\Exception $e) {}

$today     = date('Y-m-d');
$dayNamesL = ['','Montag','Dienstag','Mittwoch','Donnerstag','Freitag'];
$dayNamesS = ['','Mo','Di','Mi','Do','Fr'];

include TEMPLATE_PATH . '/layouts/header.php';
?>

<style>
.lesson-slot { font-size:.72rem; border-radius:5px; padding:4px 6px; margin-bottom:3px; line-height:1.35; position:relative; }
.lesson-slot.mine { outline:2px solid var(--wvh-primary); outline-offset:1px; }
.sub-open     { background:#fef9c3; border-left:3px solid #eab308 !important; }
.sub-pending  { background:#fffbeb; border-left:3px solid #f59e0b !important; }
.sub-claimed  { background:#f0fdf4; border-left:3px solid #22c55e !important; }
.sub-confirmed{ background:#dcfce7; border-left:3px solid #16a34a !important; }
.sub-conflict { background:#fef2f2; border-left:3px solid #ef4444 !important; }
.sub-normal   { background:#f8f9fa; border-left:3px solid #dee2e6 !important; }
.today-col    { background:#f0f7ff !important; }
.freecol      { opacity:.3; background:#f8f8f8 !important; pointer-events:none; }
.btn-xs       { font-size:.62rem; padding:1px 5px; border-radius:3px; line-height:1.4; }
</style>

<!-- Kopfzeile -->
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
  <h3 class="fw-bold mb-0" style="color:var(--wvh-primary)">
    <i class="bi bi-arrow-left-right me-2"></i>Vertretungsplanung
  </h3>
  <div class="d-flex align-items-center gap-2 flex-wrap">
    <a href="?mode=<?=$mode?>&class_id=<?=$filterClass?>&teacher_id=<?=$filterTeach?>&week=<?=$weekOffset-1?>"
       class="btn btn-sm btn-outline-secondary"><i class="bi bi-chevron-left"></i></a>
    <span class="fw-semibold px-1">
      KW <?= $monday->format('W') ?> &nbsp;·&nbsp;
      <?= $monday->format('d.m.') ?> – <?= $friday->format('d.m.Y') ?>
    </span>
    <?php if ($weekOffset !== 0): ?>
    <a href="?mode=<?=$mode?>&class_id=<?=$filterClass?>&teacher_id=<?=$filterTeach?>&week=0"
       class="btn btn-sm btn-outline-primary">Heute</a>
    <?php endif; ?>
    <a href="?mode=<?=$mode?>&class_id=<?=$filterClass?>&teacher_id=<?=$filterTeach?>&week=<?=$weekOffset+1?>"
       class="btn btn-sm btn-outline-secondary"><i class="bi bi-chevron-right"></i></a>
  </div>
</div>

<!-- Benachrichtigungen -->
<?php if (!empty($myNotifs)): ?>
<div class="alert alert-warning alert-dismissible mb-3 py-2">
  <strong><i class="bi bi-bell-fill me-1"></i><?= count($myNotifs) ?> neue Mitteilung(en):</strong>
  <?php foreach ($myNotifs as $n): ?>
  <div class="small mt-1 d-flex justify-content-between align-items-center">
    <span>
      <strong><?= date('d.m.', strtotime($n['lesson_date'])) ?>
        <?= substr($n['time_start'],0,5) ?> <?= e($n['subject_name']) ?>:</strong>
      <?= e($n['message'] ?? $n['type']) ?>
    </span>
    <a href="?action=read_notif&id=<?=$n['id']?>&mode=<?=$mode?>&week=<?=$weekOffset?>&class_id=<?=$filterClass?>"
       class="btn btn-xs btn-outline-secondary ms-2">OK</a>
  </div>
  <?php endforeach; ?>
  <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="row g-3">

<!-- ── Sidebar ────────────────────────────────────────── -->
<div class="col-xl-3 col-lg-3 col-md-4">

  <!-- Modus -->
  <div class="card wvh-card mb-3">
    <div class="card-body p-2">
      <div class="d-grid gap-1" style="grid-template-columns:1fr 1fr">
        <a href="?mode=klasse&week=<?=$weekOffset?>"
           class="btn btn-sm <?= $mode==='klasse'?'btn-wvh':'btn-outline-secondary'?>">
          <i class="bi bi-people me-1"></i>Klasse
        </a>
        <a href="?mode=lehrer&week=<?=$weekOffset?>"
           class="btn btn-sm <?= $mode==='lehrer'?'btn-wvh':'btn-outline-secondary'?>">
          <i class="bi bi-person-workspace me-1"></i>Lehrer
        </a>
      </div>
    </div>
  </div>

  <!-- Auswahlliste -->
  <?php $selList = $mode==='klasse' ? $klassenList : $teacherList;
        $selKey  = $mode==='klasse' ? 'class_id' : 'teacher_id';
        $selVal  = $mode==='klasse' ? $filterClass : $filterTeach; ?>
  <?php if (!empty($selList)): ?>
  <div class="card wvh-card mb-3">
    <div class="card-header py-2 small fw-semibold">
      <?= $mode==='klasse' ? '<i class="bi bi-people me-1"></i>Klasse wählen' : '<i class="bi bi-person me-1"></i>Lehrkraft wählen' ?>
    </div>
    <div class="list-group list-group-flush" style="max-height:280px;overflow-y:auto">
      <?php foreach ($selList as $item):
        $itemId  = $mode==='klasse' ? $item['id'] : $item['id'];
        $itemLbl = $mode==='klasse' ? 'Klasse '.$item['name'] : $item['full_name'];
        $isSel   = (int)$itemId === (int)$selVal;
      ?>
      <a href="?mode=<?=$mode?>&<?=$selKey?>=<?=$itemId?>&week=<?=$weekOffset?>"
         class="list-group-item list-group-item-action py-2 px-3 small <?=$isSel?'active':''?>">
        <?= e($itemLbl) ?>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- Offene Vertretungen -->
  <div class="card wvh-card">
    <div class="card-header py-2">
      <div class="card-icon bg-warning bg-opacity-10 text-warning" style="width:26px;height:26px">
        <i class="bi bi-exclamation-triangle" style="font-size:.75rem"></i>
      </div>
      <span class="small fw-semibold">Offene Vertretungen</span>
      <?php if (count($openSubs)): ?>
        <span class="badge bg-danger ms-auto"><?= count($openSubs) ?></span>
      <?php endif; ?>
    </div>
    <?php if (empty($openSubs)): ?>
    <div class="p-3 text-center text-muted small">
      <i class="bi bi-check-circle-fill text-success fs-4 d-block mb-1"></i>Alles gedeckt
    </div>
    <?php else: ?>
    <div style="max-height:300px;overflow-y:auto">
      <?php foreach ($openSubs as $os):
        $clsOss = $os['status']==='conflict' ? 'sub-conflict'
                : ($os['status']==='pending_confirm' ? 'sub-pending' : 'sub-open');
      ?>
      <div class="p-2 border-bottom lesson-slot <?=$clsOss?>" style="margin:0;border-radius:0">
        <div class="fw-semibold"><?= date('D d.m.', strtotime($os['lesson_date'])) ?>
          <?= substr($os['time_start'],0,5) ?></div>
        <div class="text-truncate"><?= e($os['subject_name']) ?>
          <?php if ($os['classes']): ?><span class="text-muted"> · <?=e($os['classes'])?></span><?php endif; ?>
        </div>
        <div class="text-muted" style="font-size:.65rem">für <?= e($os['original_name']) ?></div>
        <?php if ($os['status']==='pending_confirm'): ?>
          <span class="badge bg-warning text-dark" style="font-size:.58rem">Warte auf Bestätigung</span>
        <?php elseif ($os['status']==='conflict'): ?>
          <span class="badge bg-danger" style="font-size:.58rem">Konflikt</span>
        <?php else: ?>
          <a href="<?= APP_URL ?>/substitutions/open.php"
             class="badge bg-success text-decoration-none text-white" style="font-size:.58rem">
            Übernehmen →</a>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
    <div class="p-2 text-center border-top">
      <a href="<?= APP_URL ?>/substitutions/open.php" class="btn btn-sm btn-wvh-outline w-100">
        Alle anzeigen
      </a>
    </div>
    <?php endif; ?>
  </div>

</div><!-- /sidebar -->

<!-- ── Wochenraster ────────────────────────────────────── -->
<div class="col-xl-9 col-lg-9 col-md-8">

  <?php if (!$planId): ?>
  <div class="card wvh-card text-center py-5">
    <div class="text-muted">
      <i class="bi bi-table fs-1 d-block mb-3"></i>
      <h5>Kein aktueller Stundenplan</h5>
    </div>
  </div>

  <?php else: ?>

  <!-- Schulfrei Banner -->
  <?php if (!empty($freeDays)): ?>
  <div class="alert alert-info py-2 mb-2 small">
    <i class="bi bi-sun me-1"></i>
    <?php foreach ($freeDays as $ds => $name): ?>
      <span class="badge bg-info text-dark me-1">
        <?= date('D d.m.', strtotime($ds)) ?>: <?= e($name) ?>
      </span>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- Legende -->
  <div class="d-flex gap-3 mb-2 flex-wrap small text-muted align-items-center">
    <?php if (Auth::isVerwaltung() || ($myTeacherId && Auth::isLehrer())): ?>
    <span><i class="bi bi-unlock text-warning me-1"></i>Klicke auf Stunde → freigeben</span>
    <span><i class="bi bi-person-plus text-primary me-1"></i>Bei fremder Stunde → eintragen</span>
    <?php endif; ?>
    <span class="d-flex align-items-center gap-1">
      <span class="lesson-slot sub-open d-inline-block px-2" style="font-size:.65rem;white-space:nowrap">Offen</span>
    </span>
    <span class="d-flex align-items-center gap-1">
      <span class="lesson-slot sub-pending d-inline-block px-2" style="font-size:.65rem;white-space:nowrap">Warte auf OK</span>
    </span>
    <span class="d-flex align-items-center gap-1">
      <span class="lesson-slot sub-confirmed d-inline-block px-2" style="font-size:.65rem;white-space:nowrap">Bestätigt</span>
    </span>
    <span class="d-flex align-items-center gap-1">
      <span class="lesson-slot sub-conflict d-inline-block px-2" style="font-size:.65rem;white-space:nowrap">Konflikt</span>
    </span>
  </div>

  <div class="card wvh-card overflow-hidden">
    <div class="table-responsive">
      <table class="table table-bordered mb-0" style="min-width:560px;table-layout:fixed">
        <colgroup>
          <col style="width:72px">
          <?php for ($d=1;$d<=5;$d++) echo '<col>'; ?>
        </colgroup>
        <thead>
          <tr style="background:#f8fafc">
            <th class="text-muted text-center small border-0 py-2"
                style="font-size:.68rem">Zeit</th>
            <?php
            $cur = clone $monday;
            for ($d=1; $d<=5; $d++):
              $ds = $cur->format('Y-m-d');
              $isToday = ($ds === $today);
              $isFree  = isset($freeDays[$ds]);
              $cur->modify('+1 day');
            ?>
            <th class="text-center py-2 border-0 <?= $isToday ? 'today-col' : '' ?>"
                style="font-size:.78rem;<?= $isFree ? 'opacity:.4' : '' ?>">
              <span class="<?= $isToday ? 'fw-bold' : 'fw-semibold' ?>"
                    style="color:<?= $isToday ? 'var(--wvh-primary)' : '#374151' ?>">
                <?= $dayNamesS[$d] ?> <span class="text-muted fw-normal"><?= date('d.m.', strtotime($ds)) ?></span>
              </span>
              <?php if ($isToday): ?>
                <span class="badge bg-primary" style="font-size:.5rem">Heute</span>
              <?php endif; ?>
              <?php if ($isFree): ?>
                <div style="font-size:.6rem;color:#9ca3af"><?= e($freeDays[$ds]) ?></div>
              <?php endif; ?>
            </th>
            <?php endfor; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($slots as $slotStart => $slot):
          ?>
          <tr>
            <td class="text-center align-middle py-1 px-1"
                style="font-size:.68rem;line-height:1.3;background:#f8fafc;white-space:nowrap">
              <strong><?= substr($slotStart,0,5) ?></strong><br>
              <span class="text-muted" style="font-size:.6rem">–<?= substr($slot['end'],0,5) ?></span>
            </td>
            <?php
            $cur2 = clone $monday;
            for ($d=1; $d<=5; $d++):
              $ds      = $cur2->format('Y-m-d');
              $isFree  = isset($freeDays[$ds]);
              $isToday = ($ds === $today);
              $cur2->modify('+1 day');

              $cells = [];
              foreach ($lessons as $l) {
                  if ((int)$l['weekday'] === $d && $l['time_start'] === $slotStart)
                      $cells[] = $l;
              }
            ?>
            <td class="p-1 align-top <?= $isFree?'freecol':($isToday?'today-col':'') ?>">
              <?php if (empty($cells)): ?>
                <div style="min-height:36px"></div>
              <?php endif; ?>
              <?php foreach ($cells as $cell):
                $ss = $cell['sub_status'] ?? null;
                if ($ss === 'conflict')            $cls = 'sub-conflict';
                elseif ($ss === 'pending_confirm') $cls = 'sub-pending';
                elseif ($ss === 'claimed')         $cls = 'sub-claimed';
                elseif ($ss === 'confirmed')       $cls = 'sub-confirmed';
                elseif ($ss === 'open')            $cls = 'sub-open';
                else                               $cls = 'sub-normal';

                $isMine = $myTeacherId && (int)$cell['teacher_id'] === (int)$myTeacherId;

                // Was kann ich tun?
                $canRelease    = !$ss && !$isFree
                                 && (Auth::isVerwaltung()
                                     || ($isMine && Auth::isLehrer()));
                $canClaim      = $ss === 'open' && !$isMine
                                 && (Auth::isLehrer() || Auth::isVerwaltung());
                $canSelfAssign = !$ss && !$isMine && !$isFree
                                 && (Auth::isLehrer() || Auth::isVerwaltung());
                $canConfirmMe  = $ss === 'pending_confirm' && $isMine;
                $canAdminOK    = Auth::isVerwaltung() && $ss === 'pending_confirm';
              ?>
              <div class="lesson-slot <?=$cls?> <?=$isMine?'mine':''?>">
                <div class="fw-semibold text-truncate" style="font-size:.72rem">
                  <?= e($cell['subject_name']) ?>
                  <?php if ($cell['is_double_first']): ?><span class="badge bg-secondary" style="font-size:.5rem">DS1</span><?php endif; ?>
                  <?php if ($cell['is_double_second']): ?><span class="badge bg-secondary" style="font-size:.5rem">DS2</span><?php endif; ?>
                </div>
                <?php if ($mode === 'klasse'): ?>
                <div class="text-muted text-truncate" style="font-size:.65rem">
                  <?= e($cell['teacher_name']) ?>
                </div>
                <?php else: ?>
                <div class="text-muted text-truncate" style="font-size:.65rem">
                  <?= e($cell['classes'] ?? '') ?>
                </div>
                <?php endif; ?>

                <!-- Vertretungs-Info -->
                <?php if ($ss === 'open'): ?>
                  <span class="badge bg-warning text-dark mt-1" style="font-size:.58rem">Offen</span>
                <?php elseif ($ss === 'pending_confirm'): ?>
                  <div class="mt-1" style="font-size:.62rem;color:#92400e">
                    ⏳ <?= e($cell['substitute_name'] ?? '?') ?> (warte auf OK)
                  </div>
                <?php elseif ($ss === 'claimed' || $ss === 'confirmed'): ?>
                  <div class="mt-1" style="font-size:.62rem;color:#15803d">
                    ✓ <?= e($cell['substitute_name'] ?? '') ?>
                    <?php if ($ss==='confirmed'): ?>
                      <span class="badge bg-success ms-1" style="font-size:.52rem">Bestätigt</span>
                    <?php endif; ?>
                  </div>
                <?php elseif ($ss === 'conflict'): ?>
                  <div class="mt-1" style="font-size:.62rem;color:#dc2626">
                    ⚠ Konflikt: <?= e($cell['substitute_name'] ?? '') ?>
                  </div>
                <?php endif; ?>

                <!-- Aktions-Buttons -->
                <div class="mt-1 d-flex flex-wrap gap-1">

                  <?php if ($canRelease): ?>
                  <button type="button"
                          class="btn btn-xs btn-outline-warning"
                          onclick="openRelease(<?=(int)$cell['entry_id']?>,'<?=$ds?>',
                            '<?=addslashes($cell['subject_name'])?>',
                            '<?=addslashes($cell['teacher_name'])?>',
                            '<?=$ds.' '.substr($slotStart,0,5)?>')"
                          title="Zur Vertretung freigeben">
                    <i class="bi bi-unlock-fill"></i> Freigeben
                  </button>
                  <?php endif; ?>

                  <?php if ($canClaim): ?>
                  <button type="button"
                          class="btn btn-xs btn-outline-success"
                          onclick="openClaim(<?=(int)$cell['sub_id']?>,'<?=addslashes($cell['subject_name'])?>','<?=$ds.' '.substr($slotStart,0,5)?>')"
                          title="Vertretung übernehmen">
                    <i class="bi bi-hand-index-fill"></i> Übernehmen
                  </button>
                  <?php endif; ?>

                  <?php if ($canSelfAssign): ?>
                  <button type="button"
                          class="btn btn-xs btn-outline-primary"
                          onclick="openSelfAssign(<?=(int)$cell['entry_id']?>,'<?=$ds?>',
                            '<?=addslashes($cell['subject_name'])?>',
                            '<?=addslashes($cell['teacher_name'])?>')"
                          title="Mich als Vertreter eintragen">
                    <i class="bi bi-person-plus-fill"></i> Vertreten
                  </button>
                  <?php endif; ?>

                  <?php if ($canConfirmMe): ?>
                  <button type="button"
                          class="btn btn-xs btn-success"
                          onclick="doAction('confirm',<?=(int)$cell['sub_id']?>)"
                          title="Vertretung bestätigen">
                    <i class="bi bi-check-lg"></i> OK
                  </button>
                  <button type="button"
                          class="btn btn-xs btn-outline-danger"
                          onclick="doAction('reject',<?=(int)$cell['sub_id']?>)"
                          title="Vertretung ablehnen">
                    <i class="bi bi-x-lg"></i> Ablehnen
                  </button>
                  <?php endif; ?>

                  <?php if ($canAdminOK): ?>
                  <button type="button"
                          class="btn btn-xs btn-outline-success"
                          onclick="openAdminAssign(<?=(int)$cell['sub_id']?>,'<?=addslashes($cell['subject_name'])?>','<?=$ds?>')"
                          title="Als Verwaltung bestätigen">
                    <i class="bi bi-shield-check"></i> Bestätigen
                  </button>
                  <?php endif; ?>

                  <?php if (Auth::isVerwaltung() && !$ss && !$isFree): ?>
                  <button type="button"
                          class="btn btn-xs btn-outline-secondary"
                          onclick="openAdminRelease(<?=(int)$cell['entry_id']?>,'<?=$ds?>',
                            '<?=addslashes($cell['subject_name'])?>',
                            '<?=addslashes($cell['teacher_name'])?>')"
                          title="Direkt vergeben (ohne Freigabe)">
                    <i class="bi bi-person-fill-gear"></i> Vergeben
                  </button>
                  <?php endif; ?>

                </div>
              </div>
              <?php endforeach; ?>
            </td>
            <?php endfor; ?>
          </tr>
          <?php endforeach; ?>

          <?php if (empty($slots)): ?>
          <tr>
            <td colspan="6" class="text-center py-5 text-muted">
              <i class="bi bi-calendar-x fs-2 d-block mb-2"></i>
              <?php if ($mode==='klasse' && !$filterClass): ?>
                Bitte links eine Klasse auswählen.
              <?php elseif ($mode==='lehrer' && !$filterTeach): ?>
                Bitte links eine Lehrkraft auswählen.
              <?php else: ?>
                Keine Stunden für diese Auswahl.
              <?php endif; ?>
            </td>
          </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <?php endif; /* planId */ ?>
</div>
</div><!-- /row -->


<!-- ══════════════════════════════════════════════════════════
     MODALS
═══════════════════════════════════════════════════════════ -->

<!-- Modal: Freigeben -->
<div class="modal fade" id="releaseModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" action="<?= APP_URL ?>/substitutions/action.php" class="modal-content">
      <?= csrfField() ?>
      <input type="hidden" name="action"      value="release">
      <input type="hidden" name="entry_id"    id="rEntryId">
      <input type="hidden" name="lesson_date" id="rDate">
      <input type="hidden" name="week"        value="<?=$weekOffset?>">
      <input type="hidden" name="mode"        value="<?=$mode?>">
      <input type="hidden" name="class_id"    value="<?=$filterClass?>">
      <input type="hidden" name="teacher_id"  value="<?=$filterTeach?>">
      <div class="modal-header bg-warning text-dark">
        <h5 class="modal-title fw-bold"><i class="bi bi-unlock-fill me-2"></i>Stunde freigeben</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p class="text-muted small mb-2">Folgende Stunde wird zur Vertretung freigegeben:</p>
        <div class="p-3 rounded bg-light mb-3">
          <div class="fw-semibold" id="rSubject"></div>
          <div class="text-muted small" id="rTeacher"></div>
          <div class="text-muted small" id="rDateStr"></div>
        </div>
        <label class="form-label fw-semibold">Notiz <span class="fw-normal text-muted">(optional)</span></label>
        <textarea name="notes" class="form-control form-control-sm" rows="2"
                  placeholder="Krankheit, Fortbildung, ..."></textarea>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Abbrechen</button>
        <button type="submit" class="btn btn-warning text-dark fw-semibold">
          <i class="bi bi-unlock-fill me-1"></i>Freigeben
        </button>
      </div>
    </form>
  </div>
</div>

<!-- Modal: Übernehmen -->
<div class="modal fade" id="claimModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" action="<?= APP_URL ?>/substitutions/action.php" class="modal-content">
      <?= csrfField() ?>
      <input type="hidden" name="action"     value="claim">
      <input type="hidden" name="sub_id"     id="cSubId">
      <input type="hidden" name="week"       value="<?=$weekOffset?>">
      <input type="hidden" name="mode"       value="<?=$mode?>">
      <input type="hidden" name="class_id"   value="<?=$filterClass?>">
      <input type="hidden" name="teacher_id" value="<?=$filterTeach?>">
      <div class="modal-header" style="background:#16a34a;color:#fff">
        <h5 class="modal-title fw-bold"><i class="bi bi-hand-index-fill me-2"></i>Vertretung übernehmen</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="p-3 rounded bg-light mb-3">
          <div class="fw-semibold" id="cSubject"></div>
          <div class="text-muted small" id="cDateStr"></div>
        </div>
        <div id="conflictBox" class="alert alert-danger d-none">
          <i class="bi bi-exclamation-triangle-fill me-1"></i>
          <strong>Stundenplan-Konflikt!</strong> Du hast in diesem Zeitfenster bereits eine eigene
          Stunde. Die Übernahme wird als Konflikt markiert.
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Abbrechen</button>
        <button type="submit" class="btn btn-success">
          <i class="bi bi-check-lg me-1"></i>Übernehmen
        </button>
      </div>
    </form>
  </div>
</div>

<!-- Modal: Selbst eintragen -->
<div class="modal fade" id="selfModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" action="<?= APP_URL ?>/substitutions/action.php" class="modal-content">
      <?= csrfField() ?>
      <input type="hidden" name="action"      value="self_assign">
      <input type="hidden" name="entry_id"    id="saEntryId">
      <input type="hidden" name="lesson_date" id="saDate">
      <input type="hidden" name="week"        value="<?=$weekOffset?>">
      <input type="hidden" name="mode"        value="<?=$mode?>">
      <input type="hidden" name="class_id"    value="<?=$filterClass?>">
      <input type="hidden" name="teacher_id"  value="<?=$filterTeach?>">
      <div class="modal-header" style="background:var(--wvh-primary);color:#fff">
        <h5 class="modal-title fw-bold"><i class="bi bi-person-plus-fill me-2"></i>Als Vertreter eintragen</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="alert alert-info small mb-3 py-2">
          <i class="bi bi-info-circle me-1"></i>
          Die betroffene Lehrkraft wird benachrichtigt und muss bestätigen.
          Bis dahin erscheint dies als <strong>Konflikt</strong> in der Verwaltung.
        </div>
        <div class="p-3 rounded bg-light mb-3">
          <div class="fw-semibold" id="saSubject"></div>
          <div class="text-muted small">Lehrkraft: <span id="saTeacher"></span></div>
        </div>
        <label class="form-label fw-semibold">Nachricht (optional)</label>
        <textarea name="notes" class="form-control form-control-sm" rows="2"
                  placeholder="Begründung oder Hinweis..."></textarea>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Abbrechen</button>
        <button type="submit" class="btn btn-wvh">
          <i class="bi bi-person-plus-fill me-1"></i>Eintragen &amp; Benachrichtigen
        </button>
      </div>
    </form>
  </div>
</div>

<!-- Modal: Admin Bestätigen/Vergeben -->
<?php if (Auth::isVerwaltung()): ?>
<div class="modal fade" id="adminModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" action="<?= APP_URL ?>/substitutions/action.php" class="modal-content">
      <?= csrfField() ?>
      <input type="hidden" name="action"     id="aaAction" value="admin_confirm">
      <input type="hidden" name="sub_id"     id="aaSubId">
      <input type="hidden" name="week"       value="<?=$weekOffset?>">
      <input type="hidden" name="mode"       value="<?=$mode?>">
      <input type="hidden" name="class_id"   value="<?=$filterClass?>">
      <input type="hidden" name="teacher_id" value="<?=$filterTeach?>">
      <div class="modal-header" style="background:var(--wvh-primary);color:#fff">
        <h5 class="modal-title fw-bold" id="aaTitle">Vertretung bestätigen</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="p-2 bg-light rounded mb-3 small" id="aaInfo"></div>
        <div id="aaTeacherRow">
          <label class="form-label fw-semibold">Vertretende Lehrkraft zuweisen</label>
          <select name="sub_teacher_id" class="form-select">
            <option value="">– Keine Änderung –</option>
            <?php foreach ($teacherList as $tl): ?>
            <option value="<?=$tl['id']?>"><?=e($tl['full_name'])?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="mt-3">
          <label class="form-label fw-semibold">Notiz</label>
          <input type="text" name="notes" class="form-control form-control-sm" placeholder="Optional">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Abbrechen</button>
        <button type="submit" class="btn btn-wvh">
          <i class="bi bi-shield-check me-1"></i>Bestätigen
        </button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<!-- Hidden form for simple actions -->
<form id="actionForm" method="POST" action="<?= APP_URL ?>/substitutions/action.php" style="display:none">
  <?= csrfField() ?>
  <input type="hidden" name="action"     id="afAction">
  <input type="hidden" name="sub_id"     id="afSubId">
  <input type="hidden" name="week"       value="<?=$weekOffset?>">
  <input type="hidden" name="mode"       value="<?=$mode?>">
  <input type="hidden" name="class_id"   value="<?=$filterClass?>">
  <input type="hidden" name="teacher_id" value="<?=$filterTeach?>">
</form>

<script>
function openRelease(eid, date, subject, teacher, datestr) {
    document.getElementById('rEntryId').value = eid;
    document.getElementById('rDate').value    = date;
    document.getElementById('rSubject').textContent  = subject;
    document.getElementById('rTeacher').textContent  = teacher;
    document.getElementById('rDateStr').textContent  = datestr;
    new bootstrap.Modal(document.getElementById('releaseModal')).show();
}
function openClaim(subId, subject, datestr) {
    document.getElementById('cSubId').value  = subId;
    document.getElementById('cSubject').textContent = subject;
    document.getElementById('cDateStr').textContent = datestr;
    document.getElementById('conflictBox').classList.add('d-none');
    // AJAX Konflikt-Check
    fetch('<?= APP_URL ?>/substitutions/check_conflict.php?sub_id=' + subId)
        .then(r => r.json())
        .then(d => { if (d.conflict) document.getElementById('conflictBox').classList.remove('d-none'); })
        .catch(() => {});
    new bootstrap.Modal(document.getElementById('claimModal')).show();
}
function openSelfAssign(eid, date, subject, teacher) {
    document.getElementById('saEntryId').value = eid;
    document.getElementById('saDate').value    = date;
    document.getElementById('saSubject').textContent = subject;
    document.getElementById('saTeacher').textContent = teacher;
    new bootstrap.Modal(document.getElementById('selfModal')).show();
}
function openAdminAssign(subId, subject, datestr) {
    document.getElementById('aaSubId').value   = subId;
    document.getElementById('aaAction').value  = 'admin_confirm';
    document.getElementById('aaTitle').textContent  = 'Vertretung bestätigen: ' + subject;
    document.getElementById('aaInfo').textContent   = datestr;
    new bootstrap.Modal(document.getElementById('adminModal')).show();
}
function openAdminRelease(eid, date, subject, teacher) {
    // Create a sub first, then assign directly
    document.getElementById('aaSubId').value   = '';
    document.getElementById('aaAction').value  = 'admin_direct';
    document.getElementById('aaTitle').textContent = 'Direkt vergeben: ' + subject;
    document.getElementById('aaInfo').textContent  = teacher + ' · ' + date;
    // We need entry_id+date for direct assignment
    const form = document.querySelector('#adminModal form');
    let ei = form.querySelector('[name="entry_id"]');
    if (!ei) { ei = document.createElement('input'); ei.type='hidden'; ei.name='entry_id'; form.appendChild(ei); }
    let ld = form.querySelector('[name="lesson_date"]');
    if (!ld) { ld = document.createElement('input'); ld.type='hidden'; ld.name='lesson_date'; form.appendChild(ld); }
    ei.value = eid; ld.value = date;
    new bootstrap.Modal(document.getElementById('adminModal')).show();
}
function doAction(action, subId) {
    document.getElementById('afAction').value = action;
    document.getElementById('afSubId').value  = subId;
    document.getElementById('actionForm').submit();
}
</script>

<?php include TEMPLATE_PATH . '/layouts/footer.php'; ?>
