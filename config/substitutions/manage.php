<?php
/**
 * WvH – Verwaltungs-Center für Vertretungen
 * Admin: Konflikte lösen, Vertretungen zuweisen
 */
require_once __DIR__ . '/../config/bootstrap.php';
use Auth\Auth;

Auth::requireRole(ROLE_ADMIN, ROLE_VERWALTUNG);

$db       = getDB();
$myUserId = Auth::userId();

/* ── Spalten-Check: neue Felder evtl. noch nicht migriert ── */
$hasNewCols = false;
try {
    $db->query("SELECT self_assigned_by FROM substitutions LIMIT 0");
    $hasNewCols = true;
} catch (\Exception $e) {}

/* ── POST Actions ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $act   = $_POST['action'] ?? '';
    $subId = (int)($_POST['sub_id'] ?? 0);

    if ($act === 'admin_confirm' && $subId) {
        $db->prepare(
            "UPDATE substitutions
             SET status='confirmed', confirmed_by=?, confirmed_at=NOW(),
                 resolved_by=?, resolved_at=NOW()
             WHERE id=?"
        )->execute([$myUserId, $myUserId, $subId]);
        $db->prepare(
            "UPDATE lesson_instances li
             JOIN substitutions s ON s.lesson_id = li.id
             SET li.status='substituted' WHERE s.id=?"
        )->execute([$subId]);
        logAudit($myUserId, 'substitution.admin_confirmed', 'substitutions', $subId);
        setFlash('success', 'Vertretung durch Verwaltung bestätigt.');
    }

    if ($act === 'admin_reject' && $subId) {
        $notes = trim($_POST['resolution_notes'] ?? '');
        $db->prepare(
            "UPDATE substitutions
             SET status='open', substitute_teacher_id=NULL,
                 claimed_at=NULL, self_assigned_by=NULL, self_assigned_at=NULL,
                 resolved_by=?, resolved_at=NOW(), resolution_notes=?
             WHERE id=?"
        )->execute([$myUserId, $notes ?: null, $subId]);
        logAudit($myUserId, 'substitution.admin_rejected', 'substitutions', $subId);
        setFlash('info', 'Vorschlag abgelehnt. Stunde wieder offen.');
    }

    if ($act === 'admin_assign' && $subId) {
        $teacherId = (int)($_POST['teacher_id'] ?? 0);
        $notes     = trim($_POST['resolution_notes'] ?? '');
        if ($teacherId) {
            $db->prepare(
                "UPDATE substitutions
                 SET status='confirmed', substitute_teacher_id=?,
                     confirmed_by=?, confirmed_at=NOW(),
                     resolved_by=?, resolved_at=NOW(), resolution_notes=?
                 WHERE id=?"
            )->execute([$teacherId, $myUserId, $myUserId, $notes ?: null, $subId]);
            $db->prepare(
                "UPDATE lesson_instances li
                 JOIN substitutions s ON s.lesson_id = li.id
                 SET li.status='substituted' WHERE s.id=?"
            )->execute([$subId]);
            logAudit($myUserId, 'substitution.admin_assigned', 'substitutions', $subId);
            setFlash('success', 'Vertretung direkt zugewiesen.');
        }
    }

    if ($act === 'cancel' && $subId) {
        $db->prepare(
            "UPDATE substitutions SET status='cancelled', resolved_by=?, resolved_at=NOW() WHERE id=?"
        )->execute([$myUserId, $subId]);
        $db->prepare(
            "UPDATE lesson_instances li
             JOIN substitutions s ON s.lesson_id = li.id
             SET li.status='released' WHERE s.id=?"
        )->execute([$subId]);
        logAudit($myUserId, 'substitution.cancelled', 'substitutions', $subId);
        setFlash('info', 'Vertretung storniert.');
    }

    header('Location: manage.php'); exit;
}

/* ── Daten laden ── */
$notesSelect = $hasNewCols ? ", s.notes AS sub_notes" : ", NULL AS sub_notes";
$selfSelect  = $hasNewCols
    ? ", CONCAT(sa_u.first_name,' ',sa_u.last_name) AS self_assigned_name"
    : ", NULL AS self_assigned_name";
$selfJoin    = $hasNewCols
    ? "LEFT JOIN teachers sa_t ON sa_t.id = s.self_assigned_by
       LEFT JOIN users sa_u ON sa_u.id = sa_t.user_id"
    : "";

// Konflikte + ausstehende
$conflicts = $db->query(
    "SELECT s.id, s.status, s.released_at, s.claimed_at
            $notesSelect $selfSelect,
            li.lesson_date, te.time_start, te.time_end, te.weekday,
            sub_e.name AS subject_name,
            CONCAT(ot.first_name,' ',ot.last_name) AS original_name,
            GROUP_CONCAT(DISTINCT c.name ORDER BY c.name SEPARATOR ', ') AS classes,
            CONCAT(st.first_name,' ',st.last_name) AS substitute_name,
            s.substitute_teacher_id
     FROM substitutions s
     JOIN lesson_instances li ON li.id = s.lesson_id
     JOIN timetable_entries te ON te.id = li.entry_id
     JOIN subjects sub_e ON sub_e.id = te.subject_id
     JOIN teachers t_orig ON t_orig.id = s.original_teacher_id
     JOIN users ot ON ot.id = t_orig.user_id
     LEFT JOIN timetable_entry_classes tec ON tec.entry_id = te.id
     LEFT JOIN classes c ON c.id = tec.class_id
     LEFT JOIN teachers sv_t ON sv_t.id = s.substitute_teacher_id
     LEFT JOIN users st ON st.id = sv_t.user_id
     $selfJoin
     WHERE s.status IN ('conflict','pending_confirm','claimed')
     GROUP BY s.id
     ORDER BY s.status DESC, li.lesson_date ASC"
)->fetchAll();

// Offene ohne Vertreter
$openSubs = $db->query(
    "SELECT s.id, s.status, li.lesson_date, te.time_start, te.time_end, te.weekday,
            sub_e.name AS subject_name,
            CONCAT(ot.first_name,' ',ot.last_name) AS original_name,
            GROUP_CONCAT(DISTINCT c.name ORDER BY c.name SEPARATOR ', ') AS classes,
            DATEDIFF(li.lesson_date, CURDATE()) AS days_until
     FROM substitutions s
     JOIN lesson_instances li ON li.id = s.lesson_id
     JOIN timetable_entries te ON te.id = li.entry_id
     JOIN subjects sub_e ON sub_e.id = te.subject_id
     JOIN teachers t_orig ON t_orig.id = s.original_teacher_id
     JOIN users ot ON ot.id = t_orig.user_id
     LEFT JOIN timetable_entry_classes tec ON tec.entry_id = te.id
     LEFT JOIN classes c ON c.id = tec.class_id
     WHERE s.status = 'open'
     GROUP BY s.id ORDER BY li.lesson_date ASC"
)->fetchAll();

// Letzte bestätigte
$recentConfirmed = $db->query(
    "SELECT s.id, s.confirmed_at, li.lesson_date, te.time_start, te.time_end, te.weekday,
            sub_e.name AS subject_name,
            CONCAT(ot.first_name,' ',ot.last_name) AS original_name,
            CONCAT(st.first_name,' ',st.last_name) AS substitute_name
     FROM substitutions s
     JOIN lesson_instances li ON li.id = s.lesson_id
     JOIN timetable_entries te ON te.id = li.entry_id
     JOIN subjects sub_e ON sub_e.id = te.subject_id
     JOIN teachers t_orig ON t_orig.id = s.original_teacher_id
     JOIN users ot ON ot.id = t_orig.user_id
     LEFT JOIN teachers sv_t ON sv_t.id = s.substitute_teacher_id
     LEFT JOIN users st ON st.id = sv_t.user_id
     WHERE s.status = 'confirmed'
     ORDER BY s.confirmed_at DESC LIMIT 20"
)->fetchAll();

$allTeachers = $db->query(
    "SELECT t.id, CONCAT(u.first_name,' ',u.last_name) AS name
     FROM teachers t JOIN users u ON u.id=t.user_id
     WHERE u.is_active=1 ORDER BY u.last_name, u.first_name"
)->fetchAll();

$dayShort = [1=>'Mo',2=>'Di',3=>'Mi',4=>'Do',5=>'Fr'];
$pageTitle   = 'Vertretungs-Verwaltung';
$breadcrumbs = ['Vertretungsplanung' => APP_URL.'/substitutions/index.php', 'Verwaltung' => null];
include TEMPLATE_PATH . '/layouts/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
  <h3 class="fw-bold mb-0" style="color:var(--wvh-primary)">
    <i class="bi bi-shield-check me-2"></i>Vertretungs-Verwaltung
  </h3>
  <div class="d-flex gap-2">
    <a href="<?= APP_URL ?>/substitutions/index.php" class="btn btn-sm btn-outline-primary">
      <i class="bi bi-calendar-week me-1"></i>Wochenansicht
    </a>
    <a href="<?= APP_URL ?>/substitutions/open.php" class="btn btn-sm btn-outline-secondary">
      <i class="bi bi-list-task me-1"></i>Offene Liste
    </a>
  </div>
</div>

<?php if (!$hasNewCols): ?>
<div class="alert alert-warning">
  <i class="bi bi-exclamation-triangle-fill me-2"></i>
  <strong>Datenbank-Patch ausstehend.</strong>
  Bitte <code>config/PATCH_substitutions.sql</code> in phpMyAdmin ausführen,
  um alle Funktionen freizuschalten.
</div>
<?php endif; ?>

<!-- Stat-Karten -->
<div class="row g-3 mb-4">
  <div class="col-6 col-lg-3">
    <div class="wvh-stat bg-wvh-warning">
      <div class="stat-value"><?= count($openSubs) ?></div>
      <div class="stat-label">Offen</div>
      <i class="bi bi-exclamation-circle stat-icon"></i>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <?php $conflictsOnly = array_filter($conflicts, fn($x) => $x['status']==='conflict'); ?>
    <div class="wvh-stat" style="background:<?= count($conflictsOnly)>0 ? 'linear-gradient(135deg,#ef4444,#dc2626)' : 'linear-gradient(135deg,#22c55e,#16a34a)' ?>;color:#fff">
      <div class="stat-value"><?= count($conflictsOnly) ?></div>
      <div class="stat-label">Konflikte</div>
      <i class="bi bi-exclamation-triangle stat-icon"></i>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <?php $pending = array_filter($conflicts, fn($x) => in_array($x['status'],['pending_confirm','claimed'])); ?>
    <div class="wvh-stat bg-wvh-info">
      <div class="stat-value"><?= count($pending) ?></div>
      <div class="stat-label">Ausstehend</div>
      <i class="bi bi-hourglass-split stat-icon"></i>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="wvh-stat bg-wvh-success">
      <div class="stat-value"><?= count($recentConfirmed) ?></div>
      <div class="stat-label">Zuletzt bestätigt</div>
      <i class="bi bi-check-circle stat-icon"></i>
    </div>
  </div>
</div>

<!-- Konflikte & ausstehende -->
<?php if (!empty($conflicts)): ?>
<div class="card wvh-card mb-4">
  <div class="card-header">
    <div class="card-icon bg-danger bg-opacity-10 text-danger">
      <i class="bi bi-exclamation-triangle-fill"></i>
    </div>
    Ausstehend &amp; Konflikte
    <span class="badge bg-danger ms-1"><?= count($conflicts) ?></span>
  </div>
  <div class="table-responsive">
    <table class="wvh-table">
      <thead>
        <tr>
          <th>Datum</th><th>Zeit</th><th>Fach</th><th>Klassen</th>
          <th>Abwesend</th><th>Vorgeschlagen</th><th>Status</th><th>Aktionen</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($conflicts as $s):
          $isConflict = $s['status'] === 'conflict';
          // Woche berechnen für Wochenansicht-Link
          $subMon   = date('Y-m-d', strtotime('monday this week', strtotime($s['lesson_date'])));
          $todayMon = date('Y-m-d', strtotime('monday this week'));
          $wOff     = (int)round((strtotime($subMon) - strtotime($todayMon)) / (7*86400));
        ?>
        <tr class="<?= $isConflict ? 'table-danger' : 'table-warning' ?>">
          <td class="fw-medium text-nowrap">
            <?= ($dayShort[(int)$s['weekday']] ?? '') ?>,
            <?= date('d.m.Y', strtotime($s['lesson_date'])) ?>
          </td>
          <td class="small text-nowrap">
            <?= substr($s['time_start'],0,5) ?>–<?= substr($s['time_end'],0,5) ?>
          </td>
          <td><?= e($s['subject_name']) ?></td>
          <td class="small"><?= e($s['classes'] ?? '–') ?></td>
          <td><?= e($s['original_name']) ?></td>
          <td>
            <?= e($s['substitute_name'] ?? '–') ?>
            <?php if ($s['sub_notes']): ?>
              <div class="small text-muted">„<?= e($s['sub_notes']) ?>"</div>
            <?php endif; ?>
            <?php if ($s['self_assigned_name']): ?>
              <div class="small text-muted fst-italic">
                Selbst eingetragen: <?= e($s['self_assigned_name']) ?>
              </div>
            <?php endif; ?>
          </td>
          <td>
            <?php if ($isConflict): ?>
              <span class="badge bg-danger">Stundenkonflikt</span>
            <?php else: ?>
              <span class="badge bg-warning text-dark">Warte auf Bestätigung</span>
            <?php endif; ?>
          </td>
          <td>
            <div class="d-flex gap-1 flex-wrap">
              <form method="POST" class="d-inline">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="admin_confirm">
                <input type="hidden" name="sub_id" value="<?=$s['id']?>">
                <button class="btn btn-xs btn-sm py-0 px-2 btn-success">
                  <i class="bi bi-check"></i> OK
                </button>
              </form>
              <button class="btn btn-xs btn-sm py-0 px-2 btn-outline-danger"
                      data-bs-toggle="modal" data-bs-target="#rejectModal"
                      onclick="document.getElementById('rejSubId').value=<?=$s['id']?>">
                <i class="bi bi-x"></i>
              </button>
              <button class="btn btn-xs btn-sm py-0 px-2 btn-outline-primary"
                      data-bs-toggle="modal" data-bs-target="#assignModal"
                      onclick="setAssign(<?=$s['id']?>,'<?=e(addslashes($s['subject_name']))?>
                       am <?=$s['lesson_date']?>')">
                <i class="bi bi-person-plus"></i>
              </button>
              <a href="<?= APP_URL ?>/substitutions/index.php?week=<?=$wOff?>"
                 class="btn btn-xs btn-sm py-0 px-2 btn-outline-secondary">
                <i class="bi bi-calendar2-week"></i>
              </a>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<!-- Offene ohne Vertreter -->
<div class="card wvh-card mb-4">
  <div class="card-header">
    <div class="card-icon bg-warning bg-opacity-10 text-warning">
      <i class="bi bi-unlock-fill"></i>
    </div>
    Offen – kein Vertreter
    <span class="badge bg-secondary ms-1"><?= count($openSubs) ?></span>
  </div>
  <?php if (empty($openSubs)): ?>
  <div class="card-body text-center py-4 text-muted">
    <i class="bi bi-check-circle text-success me-1"></i>Alle freigegebenen Stunden sind vertreten.
  </div>
  <?php else: ?>
  <div class="table-responsive">
    <table class="wvh-table">
      <thead>
        <tr><th>Datum</th><th>Noch</th><th>Zeit</th><th>Fach</th><th>Klassen</th><th>Abwesend</th><th>Aktionen</th></tr>
      </thead>
      <tbody>
        <?php foreach ($openSubs as $s):
          $dl = (int)$s['days_until'];
          $urgency = $dl <= 0 ? 'table-danger' : ($dl <= 2 ? 'table-warning' : '');
          $subMon  = date('Y-m-d', strtotime('monday this week', strtotime($s['lesson_date'])));
          $todayMon= date('Y-m-d', strtotime('monday this week'));
          $wOff    = (int)round((strtotime($subMon)-strtotime($todayMon))/(7*86400));
        ?>
        <tr class="<?=$urgency?>">
          <td class="fw-medium text-nowrap">
            <?= ($dayShort[(int)$s['weekday']] ?? '') ?>, <?= date('d.m.Y', strtotime($s['lesson_date'])) ?>
          </td>
          <td class="text-center">
            <?php if ($dl<=0): ?><span class="badge bg-danger">Überfällig</span>
            <?php elseif ($dl===1): ?><span class="badge bg-warning text-dark">Morgen</span>
            <?php else: ?><span class="text-muted small"><?=$dl?>d</span>
            <?php endif; ?>
          </td>
          <td class="small text-nowrap"><?= substr($s['time_start'],0,5) ?>–<?= substr($s['time_end'],0,5) ?></td>
          <td><?= e($s['subject_name']) ?></td>
          <td class="small"><?= e($s['classes'] ?? '–') ?></td>
          <td><?= e($s['original_name']) ?></td>
          <td>
            <div class="d-flex gap-1">
              <button class="btn btn-sm btn-primary"
                      data-bs-toggle="modal" data-bs-target="#assignModal"
                      onclick="setAssign(<?=$s['id']?>,'<?=e(addslashes($s['subject_name']))?>
                       am <?=$s['lesson_date']?>')">
                <i class="bi bi-person-plus me-1"></i>Zuweisen
              </button>
              <form method="POST" class="d-inline">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="cancel">
                <input type="hidden" name="sub_id" value="<?=$s['id']?>">
                <button class="btn btn-sm btn-outline-secondary"
                        onclick="return confirm('Freigabe stornieren?')">
                  <i class="bi bi-x-circle"></i>
                </button>
              </form>
              <a href="<?= APP_URL ?>/substitutions/index.php?week=<?=$wOff?>"
                 class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-calendar2-week"></i>
              </a>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<!-- Letzte bestätigte -->
<?php if (!empty($recentConfirmed)): ?>
<div class="card wvh-card">
  <div class="card-header">
    <div class="card-icon bg-success bg-opacity-10 text-success">
      <i class="bi bi-check-circle-fill"></i>
    </div>
    Zuletzt bestätigt
  </div>
  <div class="table-responsive">
    <table class="wvh-table">
      <thead>
        <tr><th>Datum</th><th>Zeit</th><th>Fach</th><th>Abwesend</th><th>Vertreter</th><th>Bestätigt</th></tr>
      </thead>
      <tbody>
        <?php foreach ($recentConfirmed as $s): ?>
        <tr>
          <td><?= ($dayShort[(int)$s['weekday']] ?? '') ?>, <?= date('d.m.Y', strtotime($s['lesson_date'])) ?></td>
          <td class="small"><?= substr($s['time_start'],0,5) ?>–<?= substr($s['time_end'],0,5) ?></td>
          <td><?= e($s['subject_name']) ?></td>
          <td><?= e($s['original_name']) ?></td>
          <td class="fw-medium text-success"><?= e($s['substitute_name'] ?? '–') ?></td>
          <td class="small text-muted">
            <?= $s['confirmed_at'] ? date('d.m. H:i', strtotime($s['confirmed_at'])) : '–' ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<!-- Modal: Ablehnen -->
<div class="modal fade" id="rejectModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" class="modal-content">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="admin_reject">
      <input type="hidden" name="sub_id" id="rejSubId">
      <div class="modal-header" style="background:#ef4444;color:#fff">
        <h5 class="modal-title fw-bold">Vorschlag ablehnen</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <label class="form-label fw-semibold">Begründung (optional)</label>
        <input type="text" name="resolution_notes" class="form-control"
               placeholder="Grund für Ablehnung…">
        <p class="small text-muted mt-2">Die Stunde wird wieder als „offen" markiert.</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Abbrechen</button>
        <button type="submit" class="btn btn-danger">Ablehnen &amp; zurücksetzen</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal: Direkt zuweisen -->
<div class="modal fade" id="assignModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" class="modal-content">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="admin_assign">
      <input type="hidden" name="sub_id" id="asgSubId">
      <div class="modal-header" style="background:var(--wvh-primary);color:#fff">
        <h5 class="modal-title fw-bold"><i class="bi bi-person-plus me-2"></i>Vertretung direkt zuweisen</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p class="small text-muted mb-3" id="asgInfo"></p>
        <div class="mb-3">
          <label class="form-label fw-semibold">Lehrkraft *</label>
          <select name="teacher_id" class="form-select" required>
            <option value="">– Bitte wählen –</option>
            <?php foreach ($allTeachers as $t): ?>
            <option value="<?=$t['id']?>"><?=e($t['name'])?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="mb-2">
          <label class="form-label fw-semibold">Notiz (optional)</label>
          <input type="text" name="resolution_notes" class="form-control" placeholder="Interne Info…">
        </div>
        <div class="alert alert-info small">
          <i class="bi bi-info-circle me-1"></i>
          Direkte Zuweisung durch Verwaltung gilt als sofort bestätigt.
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Abbrechen</button>
        <button type="submit" class="btn btn-wvh">
          <i class="bi bi-check me-1"></i>Verbindlich zuweisen
        </button>
      </div>
    </form>
  </div>
</div>

<script>
function setAssign(subId, info) {
    document.getElementById('asgSubId').value = subId;
    document.getElementById('asgInfo').textContent = info;
}
</script>

<?php include TEMPLATE_PATH . '/layouts/footer.php'; ?>
