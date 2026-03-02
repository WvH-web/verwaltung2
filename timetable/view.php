<?php
/**
 * WvH – Stundenplan (Klassen- & Lehreransicht)
 */
require_once __DIR__ . '/../config/bootstrap.php';
use Auth\Auth;
use Controllers\TimetableImporter;

Auth::require();

$pageTitle   = 'Stundenplan';
$breadcrumbs = ['Stundenplan' => null];
$db          = getDB();
$importer    = new TimetableImporter();

// ── POST: Gültigkeitszeitraum / Plan löschen ───────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    Auth::requireRole(ROLE_ADMIN, ROLE_VERWALTUNG);
    requireCsrf();

    if ($_POST['action'] === 'update_validity') {
        $planId = (int)($_POST['plan_id'] ?? 0);
        $result = $importer->updateValidity(
            $planId,
            $_POST['valid_from']  ?? '',
            $_POST['valid_until'] ?? '',
            Auth::userId()
        );
        setFlash($result['success'] ? 'success' : 'error',
            $result['success'] ? 'Gültigkeitszeitraum aktualisiert.' : $result['error']);
        header('Location: ' . APP_URL . '/timetable/view.php');
        exit;
    }

    if ($_POST['action'] === 'delete_plan') {
        $planId = (int)($_POST['plan_id'] ?? 0);
        $db->prepare("DELETE FROM timetable_entries WHERE plan_id=?")->execute([$planId]);
        $db->prepare("DELETE FROM timetable_plans WHERE id=?")->execute([$planId]);
        logAudit(Auth::userId(), 'timetable.deleted', 'timetable_plans', $planId);
        setFlash('success', 'Plan gelöscht.');
        header('Location: ' . APP_URL . '/timetable/view.php');
        exit;
    }
}

// ── Modus: klasse | lehrer ─────────────────────────────────
$mode = $_GET['mode'] ?? 'klasse';
if (!in_array($mode, ['klasse', 'lehrer'])) $mode = 'klasse';

// ── Plan wählen ────────────────────────────────────────────
$planId = (int)($_GET['plan_id'] ?? 0);
$today  = date('Y-m-d');

$plans = $db->query(
    "SELECT p.*,
            CONCAT(u.first_name,' ',u.last_name) AS uploaded_by_name,
            COUNT(te.id) AS entry_count
     FROM timetable_plans p
     LEFT JOIN users u  ON u.id  = p.uploaded_by
     LEFT JOIN timetable_entries te ON te.plan_id = p.id
     GROUP BY p.id
     ORDER BY p.valid_from DESC"
)->fetchAll();

if (!$planId && $plans) {
    foreach ($plans as $p) {
        if ($p['valid_from'] <= $today && $p['valid_until'] >= $today) {
            $planId = (int)$p['id'];
            break;
        }
    }
    if (!$planId) $planId = (int)($plans[0]['id'] ?? 0);
}

$planInfo = null;
if ($planId) {
    $s = $db->prepare("SELECT * FROM timetable_plans WHERE id=?");
    $s->execute([$planId]);
    $planInfo = $s->fetch();
}

// ── Dropdown-Listen ────────────────────────────────────────
$klassenList = [];
$teacherList = [];
if ($planId) {
    $klassenList = $db->query(
        "SELECT DISTINCT c.id, c.name
         FROM classes c
         JOIN timetable_entry_classes tec ON tec.class_id = c.id
         JOIN timetable_entries te ON te.id = tec.entry_id
         WHERE te.plan_id = $planId
         ORDER BY c.name"
    )->fetchAll();

    $teacherList = $db->query(
        "SELECT DISTINCT t.id,
                u.first_name, u.last_name,
                CONCAT(u.first_name,' ',u.last_name) AS full_name
         FROM timetable_entries te
         JOIN teachers t ON t.id = te.teacher_id
         JOIN users u    ON u.id = t.user_id
         WHERE te.plan_id = $planId
         ORDER BY u.last_name, u.first_name"
    )->fetchAll();
}

// ── Aktiver Filter ─────────────────────────────────────────
$filterClassId   = (int)($_GET['class_id']   ?? 0);
$filterTeacherId = (int)($_GET['teacher_id'] ?? 0);

// Standard Klasse 1a beim Klassenplan
if ($mode === 'klasse' && !$filterClassId && !empty($klassenList)) {
    foreach ($klassenList as $kl) {
        if (strtolower($kl['name']) === '1a') {
            $filterClassId = (int)$kl['id'];
            break;
        }
    }
    if (!$filterClassId) $filterClassId = (int)($klassenList[0]['id'] ?? 0);
}

// ── Einträge laden ─────────────────────────────────────────
$entries = [];
if ($planId) {
    // NOTE: display_name ist GENERATED ALWAYS STORED – kann problemlos selektiert werden
    $sql = "SELECT te.id, te.weekday, te.time_start, te.time_end,
                   te.period_start, te.is_double_first, te.is_double_second, te.double_group_id,
                   t.id AS teacher_id,
                   u.first_name AS t_first, u.last_name AS t_last,
                   CONCAT(u.first_name,' ',u.last_name) AS teacher_name,
                   sub.name AS subject_name,
                   GROUP_CONCAT(c.name ORDER BY c.name SEPARATOR ', ') AS classes,
                   GROUP_CONCAT(c.id   ORDER BY c.name SEPARATOR ',')   AS class_ids_csv
            FROM timetable_entries te
            JOIN teachers t   ON t.id   = te.teacher_id
            JOIN users u      ON u.id   = t.user_id
            JOIN subjects sub ON sub.id = te.subject_id
            LEFT JOIN timetable_entry_classes tec ON tec.entry_id = te.id
            LEFT JOIN classes c ON c.id = tec.class_id
            WHERE te.plan_id = ?";
    $params = [$planId];

    if ($mode === 'klasse' && $filterClassId) {
        $sql .= " AND te.id IN (
                    SELECT entry_id FROM timetable_entry_classes WHERE class_id = ?
                  )";
        $params[] = $filterClassId;
    } elseif ($mode === 'lehrer' && $filterTeacherId) {
        $sql .= " AND te.teacher_id = ?";
        $params[] = $filterTeacherId;
    }

    $sql .= " GROUP BY te.id ORDER BY te.weekday, te.time_start";
    $s = $db->prepare($sql);
    $s->execute($params);
    $entries = $s->fetchAll();
}

// ── Zeitslots ermitteln ────────────────────────────────────
$slots = [];
foreach ($entries as $e) {
    $k = $e['time_start'];
    if (!isset($slots[$k])) {
        $slots[$k] = ['start' => $e['time_start'], 'end' => $e['time_end'], 'period' => $e['period_start']];
    }
}
ksort($slots);

// ── Fachfarben (feste Palette, keine DB-Spalte) ────────────
$palette = ['#2d6fa4','#16a34a','#7c3aed','#ea580c','#0891b2','#db2777','#d97706','#374151','#0f766e','#b45309'];
$subjectColors = [];
$ci = 0;
foreach ($entries as $e) {
    if (!isset($subjectColors[$e['subject_name']])) {
        $subjectColors[$e['subject_name']] = $palette[$ci % count($palette)];
        $ci++;
    }
}

// ── Doppelstunden: Rowspan + Skip-Maps ────────────────────
// Build partner lookup per entry_id (within same weekday)
$dsPartner = []; // entry_id → partner entry row
foreach ($entries as $e) {
    if (!$e['double_group_id']) continue;
    foreach ($entries as $m) {
        if ($m['double_group_id'] === $e['double_group_id']
            && $m['id'] !== $e['id']
            && $m['weekday'] === $e['weekday']) {
            $dsPartner[(int)$e['id']] = $m;
            break;
        }
    }
}

$slotKeys = array_keys($slots);
$dsRowspanCells = []; // [wd][slotStart] → true  needs rowspan=2
$dsSkipCells    = []; // [wd][slotStart] → true  skip this td
foreach ($entries as $e) {
    if (!$e['is_double_first'] || !$e['double_group_id']) continue;
    $wd = (int)$e['weekday'];
    $partner = $dsPartner[(int)$e['id']] ?? null;
    if (!$partner) continue;
    $ds1Slot = $e['time_start'];
    $ds2Slot = $partner['time_start'];
    $idx = array_search($ds1Slot, $slotKeys);
    // Only merge if DS2 is the immediately next slot (no break/gap check here)
    if ($idx !== false && isset($slotKeys[$idx+1]) && $slotKeys[$idx+1] === $ds2Slot) {
        $dsRowspanCells[$wd][$ds1Slot] = true;
        $dsSkipCells[$wd][$ds2Slot]    = true;
    }
}
$dsRenderedPartner = []; // entry_ids already rendered inside DS1

// ── Pausen zwischen Slots erkennen ────────────────────────
$slotsWithBreaks = [];
$prevSlot = null;
foreach ($slots as $slot) {
    if ($prevSlot) {
        $prevEnd  = strtotime('1970-01-01 ' . $prevSlot['end']);
        $curStart = strtotime('1970-01-01 ' . $slot['start']);
        $gap = (int)(($curStart - $prevEnd) / 60);
        if ($gap >= 5) {
            $slotsWithBreaks[] = ['is_break' => true, 'start' => $prevSlot['end'], 'end' => $slot['start'], 'duration' => $gap];
        }
    }
    $slotsWithBreaks[] = $slot;
    $prevSlot = $slot;
}

// ── Heute-Info ─────────────────────────────────────────────
$todayDow    = (int)date('N'); // 1=Mo…7=So
$isSchoolDay = $todayDow >= 1 && $todayDow <= 5;
$todayHoliday = null;
try {
    $s = $db->prepare("SELECT name FROM school_holidays WHERE is_school_free=1 AND date_from<=? AND date_until>=? LIMIT 1");
    $s->execute([$today, $today]);
    $todayHoliday = $s->fetchColumn() ?: null;
} catch (\Throwable $e) { /* Tabelle evtl. noch nicht angelegt */ }

$dayNames = ['', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag'];

// ── Ansichts-Titel ─────────────────────────────────────────
$viewTitle = '';
if ($mode === 'klasse' && $filterClassId) {
    foreach ($klassenList as $kl) {
        if ((int)$kl['id'] === $filterClassId) { $viewTitle = 'Klasse ' . $kl['name']; break; }
    }
} elseif ($mode === 'lehrer' && $filterTeacherId) {
    foreach ($teacherList as $tl) {
        if ((int)$tl['id'] === $filterTeacherId) { $viewTitle = $tl['full_name']; break; }
    }
}

include TEMPLATE_PATH . '/layouts/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <h3 class="fw-bold mb-0" style="color:var(--wvh-primary)">
    <i class="bi bi-grid-3x3-gap me-2"></i>Stundenplan
    <?php if ($viewTitle): ?>
      <span class="fw-normal fs-5 text-muted ms-2">&ndash; <?= e($viewTitle) ?></span>
    <?php endif; ?>
  </h3>
  <div class="d-flex gap-2 align-items-center">
    <?php if ($todayHoliday): ?>
      <span class="badge bg-warning text-dark"><i class="bi bi-sun me-1"></i><?= e($todayHoliday) ?></span>
    <?php endif; ?>
    <?php if (Auth::isVerwaltung()): ?>
    <a href="<?= APP_URL ?>/timetable/import.php" class="btn btn-sm btn-wvh">
      <i class="bi bi-upload me-1"></i>CSV Import
    </a>
    <?php endif; ?>
  </div>
</div>

<div class="row g-3">

<!-- ── Sidebar ──────────────────────────────────────────── -->
<div class="col-lg-3 col-md-4">

  <!-- Modus-Toggle -->
  <div class="card wvh-card mb-3">
    <div class="card-body p-2">
      <div class="d-grid gap-2" style="grid-template-columns:1fr 1fr">
        <a href="?mode=klasse&plan_id=<?= $planId ?>"
           class="btn btn-sm <?= $mode==='klasse' ? 'btn-wvh' : 'btn-outline-secondary' ?>">
          <i class="bi bi-people me-1"></i>Klassenplan
        </a>
        <a href="?mode=lehrer&plan_id=<?= $planId ?>"
           class="btn btn-sm <?= $mode==='lehrer' ? 'btn-wvh' : 'btn-outline-secondary' ?>">
          <i class="bi bi-person-workspace me-1"></i>Lehrerplan
        </a>
      </div>
    </div>
  </div>

  <!-- Plan-Versionen -->
  <div class="card wvh-card mb-3">
    <div class="card-header py-2">
      <div class="card-icon bg-primary bg-opacity-10 text-primary" style="width:28px;height:28px">
        <i class="bi bi-calendar3" style="font-size:.8rem"></i>
      </div>
      <span class="small fw-semibold">Plan-Versionen</span>
    </div>
    <div class="list-group list-group-flush">
      <?php if (empty($plans)): ?>
        <div class="list-group-item text-center text-muted py-3 small">
          <i class="bi bi-inbox d-block mb-1"></i>Kein Plan vorhanden
        </div>
      <?php endif; ?>
      <?php foreach ($plans as $p):
        $isCurr = $p['valid_from'] <= $today && $p['valid_until'] >= $today;
        $isSel  = (int)$p['id'] === $planId;
      ?>
      <div class="list-group-item p-2 <?= $isSel ? 'active' : '' ?>">
        <a href="?mode=<?= $mode ?>&plan_id=<?= $p['id'] ?>"
           class="text-decoration-none d-block <?= $isSel ? 'text-white' : '' ?>">
          <div class="fw-semibold" style="font-size:.78rem"><?= e($p['name']) ?></div>
          <div class="<?= $isSel ? 'text-white-50' : 'text-muted' ?>" style="font-size:.68rem">
            <?= formatDate($p['valid_from']) ?> – <?= formatDate($p['valid_until']) ?>
          </div>
        </a>
        <?php if ($isCurr): ?>
          <span class="badge <?= $isSel ? 'bg-warning text-dark' : 'bg-success' ?>" style="font-size:.6rem">Aktiv</span>
        <?php endif; ?>
        <?php if (Auth::isVerwaltung() && $isSel): ?>
        <div class="mt-1">
          <button class="btn btn-sm py-0 px-2 btn-outline-light" style="font-size:.7rem"
                  data-bs-toggle="modal" data-bs-target="#editPlanModal"
                  data-plan-id="<?= $p['id'] ?>"
                  data-plan-name="<?= e($p['name']) ?>"
                  data-valid-from="<?= $p['valid_from'] ?>"
                  data-valid-until="<?= $p['valid_until'] ?>">
            <i class="bi bi-pencil me-1"></i>Zeitraum
          </button>
        </div>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Klassen-Liste -->
  <?php if ($mode === 'klasse' && !empty($klassenList)): ?>
  <div class="card wvh-card">
    <div class="card-header py-2">
      <div class="card-icon bg-success bg-opacity-10 text-success" style="width:28px;height:28px">
        <i class="bi bi-people" style="font-size:.8rem"></i>
      </div>
      <span class="small fw-semibold">Klasse wählen</span>
    </div>
    <div class="list-group list-group-flush" style="max-height:320px;overflow-y:auto">
      <?php foreach ($klassenList as $kl):
        $sel = (int)$kl['id'] === $filterClassId;
      ?>
      <a href="?mode=klasse&plan_id=<?= $planId ?>&class_id=<?= $kl['id'] ?>"
         class="list-group-item list-group-item-action py-2 px-3 <?= $sel ? 'active' : '' ?>"
         style="font-size:.82rem">
        <i class="bi bi-people me-2 <?= $sel ? 'text-white' : 'text-muted' ?>"></i>
        Klasse <?= e($kl['name']) ?>
      </a>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Lehrer-Liste -->
  <?php elseif ($mode === 'lehrer' && !empty($teacherList)): ?>
  <div class="card wvh-card">
    <div class="card-header py-2">
      <div class="card-icon bg-info bg-opacity-10 text-info" style="width:28px;height:28px">
        <i class="bi bi-person" style="font-size:.8rem"></i>
      </div>
      <span class="small fw-semibold">Lehrkraft wählen</span>
    </div>
    <div class="list-group list-group-flush" style="max-height:380px;overflow-y:auto">
      <?php foreach ($teacherList as $tl):
        $sel = (int)$tl['id'] === $filterTeacherId;
      ?>
      <a href="?mode=lehrer&plan_id=<?= $planId ?>&teacher_id=<?= $tl['id'] ?>"
         class="list-group-item list-group-item-action py-2 px-3 <?= $sel ? 'active' : '' ?>"
         style="font-size:.82rem">
        <i class="bi bi-person me-2 <?= $sel ? 'text-white' : 'text-muted' ?>"></i>
        <?= e($tl['full_name']) ?>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

</div><!-- /sidebar -->

<!-- ── Hauptbereich ──────────────────────────────────────── -->
<div class="col-lg-9 col-md-8">

<?php if (!$planInfo): ?>
  <div class="card wvh-card">
    <div class="card-body text-center py-5">
      <i class="bi bi-table fs-1 text-muted d-block mb-3"></i>
      <h5>Kein Stundenplan vorhanden</h5>
      <p class="text-muted">Importiere eine CSV-Datei, um zu beginnen.</p>
      <?php if (Auth::isVerwaltung()): ?>
      <a href="<?= APP_URL ?>/timetable/import.php" class="btn btn-wvh">
        <i class="bi bi-upload me-2"></i>CSV importieren
      </a>
      <?php endif; ?>
    </div>
  </div>

<?php else: ?>

  <!-- Info-Zeile -->
  <div class="d-flex align-items-center gap-2 mb-2 flex-wrap">
    <span class="badge bg-secondary"><?= e($planInfo['name']) ?></span>
    <span class="small text-muted"><?= formatDate($planInfo['valid_from']) ?> – <?= formatDate($planInfo['valid_until']) ?></span>
    <span class="badge bg-light text-dark border"><?= count($entries) ?> Einträge</span>
  </div>

  <?php if (empty($slots)): ?>
  <div class="card wvh-card">
    <div class="card-body text-center py-4 text-muted">
      <i class="bi bi-calendar-x fs-2 d-block mb-2"></i>
      Keine Einträge für diese Auswahl.
      <?php if ($mode === 'klasse'): ?>
        <div class="small mt-1">Bitte links eine Klasse wählen.</div>
      <?php else: ?>
        <div class="small mt-1">Bitte links eine Lehrkraft wählen.</div>
      <?php endif; ?>
    </div>
  </div>

  <?php else: ?>
  <div class="card wvh-card">
    <div class="table-responsive">
      <table class="table mb-0" style="min-width:580px;table-layout:fixed">
        <colgroup>
          <col style="width:78px">
          <col><col><col><col><col>
        </colgroup>
        <thead style="background:#f8fafc">
          <tr>
            <th class="text-center small text-muted py-2 px-1 border-0">Zeit</th>
            <?php for ($d = 1; $d <= 5; $d++): ?>
            <th class="py-2 px-1 border-0" style="font-size:.82rem;font-weight:600;
                color:<?= ($isSchoolDay && $d === $todayDow && !$todayHoliday) ? 'var(--wvh-primary)' : '#374151' ?>">
              <?= $dayNames[$d] ?>
              <?php if ($isSchoolDay && $d === $todayDow && !$todayHoliday): ?>
                <span class="badge bg-primary ms-1" style="font-size:.5rem">Heute</span>
              <?php endif; ?>
            </th>
            <?php endfor; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($slotsWithBreaks as $slot): ?>

          <?php if (!empty($slot['is_break'])): ?>
          <tr style="background:#f8fafc">
            <td class="text-center py-1 small text-muted border-0" style="font-size:.65rem;border-right:2px dashed #e5e7eb!important">
              <?= substr($slot['start'], 0, 5) ?><br>
              <span style="color:#ccc">Pause</span><br>
              <?= substr($slot['end'], 0, 5) ?>
            </td>
            <?php for ($d = 1; $d <= 5; $d++): ?>
            <td class="border-0" style="background:repeating-linear-gradient(-45deg,#f9fafb,#f9fafb 4px,#f3f4f6 4px,#f3f4f6 8px)">
              <span class="d-block text-center text-muted" style="font-size:.62rem;line-height:2.2">
                <?= $slot['duration'] ?> min
              </span>
            </td>
            <?php endfor; ?>
          </tr>

          <?php else: ?>
          <?php
            $slotStart   = $slot['start'];
            $slotEntries = array_filter($entries, fn($e) => $e['time_start'] === $slotStart);
            $trCells = '';
            for ($d = 1; $d <= 5; $d++) {
                $isToday     = $isSchoolDay && $d === $todayDow && !$todayHoliday;
                $needRowspan = !empty($dsRowspanCells[$d][$slotStart]);
                $needSkip    = !empty($dsSkipCells[$d][$slotStart]);
                if ($needSkip) continue;

                $bg = $isToday ? 'background:#f0f7ff;' : '';
                $rs = $needRowspan ? ' rowspan="2"' : '';
                $trCells .= '<td class="p-1 border-0"'.$rs.' style="vertical-align:top;'.$bg.'">';

                $dayEntries = array_values(array_filter($slotEntries,
                    fn($e) => (int)$e['weekday'] === $d && !isset($dsRenderedPartner[(int)$e['id']])));

                foreach ($dayEntries as $e) {
                    if (isset($dsRenderedPartner[(int)$e['id']])) continue;
                    $col     = $subjectColors[$e['subject_name']] ?? '#2d6fa4';
                    $isDS    = $e['is_double_first'] || $e['is_double_second'];
                    $dsBadge = $isDS
                        ? '<span class="badge ms-1" style="background:'.$col.';font-size:.5rem;padding:.15em .35em">DS</span>'
                        : '';

                    if ($mode === 'klasse') {
                        $sub = '<div class="text-truncate" style="color:#555;font-size:.65rem">'.e($e['teacher_name']).'</div>';
                    } elseif ($e['classes']) {
                        $sub = '<div class="text-truncate" style="color:#555;font-size:.65rem">'.e($e['classes']).'</div>';
                    } else {
                        $sub = '';
                    }

                    $trCells .= '<div class="rounded mb-1" style="background:'.$col.'14;border-left:3px solid '.$col.';padding:3px 5px;font-size:.7rem;line-height:1.35">'
                        . '<div class="fw-semibold text-truncate" style="color:'.$col.'">'.e($e['subject_name']).$dsBadge.'</div>'
                        . $sub
                        . '</div>';

                    // DS1 with rowspan → also render DS2 partner below
                    if ($e['is_double_first'] && $needRowspan) {
                        $partner = $dsPartner[(int)$e['id']] ?? null;
                        if ($partner) {
                            $dsRenderedPartner[(int)$partner['id']] = true;
                            $pCol  = $subjectColors[$partner['subject_name']] ?? '#2d6fa4';
                            $pTime = substr($partner['time_start'], 0, 5);
                            if ($mode === 'klasse') {
                                $pSub = '<div class="text-truncate" style="color:#555;font-size:.65rem">'.e($partner['teacher_name']).'</div>';
                            } elseif ($partner['classes']) {
                                $pSub = '<div class="text-truncate" style="color:#555;font-size:.65rem">'.e($partner['classes']).'</div>';
                            } else {
                                $pSub = '';
                            }
                            $trCells .= '<div style="height:2px;background:#e5e7eb;margin:4px 0"></div>'
                                . '<div class="rounded" style="background:'.$pCol.'14;border-left:3px solid '.$pCol.';padding:3px 5px;font-size:.7rem;line-height:1.35">'
                                . '<div class="fw-semibold text-truncate" style="color:'.$pCol.'">'.e($partner['subject_name'])
                                . '<span class="badge ms-1" style="background:'.$pCol.';font-size:.5rem;padding:.15em .35em">DS</span>'
                                . '<span style="color:#888;font-size:.6rem;font-weight:400"> '.$pTime.'</span></div>'
                                . $pSub.'</div>';
                        }
                    }
                }
                $trCells .= '</td>';
            }
          ?>
          <tr style="border-top:1px solid #e9ecef">
            <td class="text-center p-1 border-0" style="font-size:.7rem;color:#6b7280;line-height:1.3;border-right:2px solid #dee2e6!important">
              <strong><?= substr($slot['start'], 0, 5) ?></strong><br>
              <span style="font-size:.62rem">–<?= substr($slot['end'], 0, 5) ?></span>
            </td>
            <?= $trCells ?>
          </tr>
          <?php endif; ?>

          <?php endforeach; ?>
        </tbody>
      </table>
    </div><!-- /table-responsive -->

    <div class="card-footer bg-transparent d-flex gap-3 flex-wrap py-2">
      <div class="d-flex align-items-center gap-1 small text-muted">
        <div style="width:10px;height:10px;border-radius:2px;background:#2d6fa414;border-left:3px solid #2d6fa4"></div>
        Fach
      </div>
      <div class="d-flex align-items-center gap-1 small text-muted">
        <span class="badge bg-primary" style="font-size:.55rem">DS</span>
        Doppelstunde
      </div>
      <?php if ($mode === 'klasse' && $filterClassId): ?>
      <div class="ms-auto">
        <a href="?mode=lehrer&plan_id=<?= $planId ?>" class="btn btn-sm btn-outline-secondary">
          <i class="bi bi-person-workspace me-1"></i>Zur Lehreransicht
        </a>
      </div>
      <?php elseif ($mode === 'lehrer'): ?>
      <div class="ms-auto">
        <a href="?mode=klasse&plan_id=<?= $planId ?>" class="btn btn-sm btn-outline-secondary">
          <i class="bi bi-people me-1"></i>Zur Klassenansicht
        </a>
      </div>
      <?php endif; ?>
    </div>
  </div><!-- /card -->
  <?php endif; /* slots */ ?>
<?php endif; /* planInfo */ ?>

</div><!-- /main col -->
</div><!-- /row -->

<!-- ── Modal: Gültigkeitszeitraum ─────────────────────────── -->
<?php if (Auth::isVerwaltung()): ?>
<div class="modal fade" id="editPlanModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header" style="background:var(--wvh-primary);color:#fff">
        <h5 class="modal-title"><i class="bi bi-calendar-range me-2"></i>Gültigkeitszeitraum</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="update_validity">
        <input type="hidden" name="plan_id" id="editPlanId">
        <div class="modal-body">
          <p class="small text-muted" id="editPlanName"></p>
          <div class="row g-3">
            <div class="col-6">
              <label class="form-label fw-semibold">Gültig ab</label>
              <input type="date" name="valid_from" id="editValidFrom" class="form-control" required>
            </div>
            <div class="col-6">
              <label class="form-label fw-semibold">Gültig bis</label>
              <input type="date" name="valid_until" id="editValidUntil" class="form-control" required>
            </div>
          </div>
          <div class="alert alert-warning small mt-3 mb-0">
            <i class="bi bi-exclamation-triangle me-1"></i>
            Wirkt sich auf Abrechnung und Vertretungsplanung aus.
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Abbrechen</button>
          <button type="submit" class="btn btn-wvh"><i class="bi bi-save me-1"></i>Speichern</button>
        </div>
      </form>
    </div>
  </div>
</div>
<script>
document.getElementById('editPlanModal').addEventListener('show.bs.modal', function(e) {
  var b = e.relatedTarget;
  document.getElementById('editPlanId').value     = b.getAttribute('data-plan-id');
  document.getElementById('editPlanName').textContent = 'Plan: ' + b.getAttribute('data-plan-name');
  document.getElementById('editValidFrom').value  = b.getAttribute('data-valid-from');
  document.getElementById('editValidUntil').value = b.getAttribute('data-valid-until');
});
</script>
<?php endif; ?>

<?php include TEMPLATE_PATH . '/layouts/footer.php'; ?>
