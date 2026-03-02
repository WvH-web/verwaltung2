<?php
/**
 * WvH – Lehrkräfte verwalten
 * Namen bearbeiten + Klassen-Zuordnungen
 */
require_once __DIR__ . '/../config/bootstrap.php';
use Auth\Auth;

Auth::requireRole(ROLE_ADMIN, ROLE_VERWALTUNG);

$pageTitle   = 'Lehrkräfte';
$breadcrumbs = ['Admin' => APP_URL . '/admin/users.php', 'Lehrkräfte' => null];
$db = getDB();

/* ── POST ──────────────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $act = $_POST['action'] ?? '';

    /* Lehrerdaten speichern */
    if ($act === 'save_teacher') {
        $userId    = (int)($_POST['user_id']    ?? 0);
        $teacherId = (int)($_POST['teacher_id'] ?? 0);
        $firstName = trim($_POST['first_name']  ?? '');
        $lastName  = trim($_POST['last_name']   ?? '');
        $phone     = trim($_POST['phone']       ?? '');
        $empType   = in_array($_POST['employment_type'] ?? '', ['honorar','festangestellt'])
                     ? $_POST['employment_type'] : 'honorar';

        if (!$firstName || !$lastName || !$userId) {
            setFlash('error', 'Vor- und Nachname sind Pflichtfelder.');
        } else {
            $db->prepare(
                "UPDATE users SET first_name=?, last_name=? WHERE id=?"
            )->execute([$firstName, $lastName, $userId]);
            $db->prepare(
                "UPDATE teachers SET phone=?, employment_type=? WHERE id=?"
            )->execute([$phone ?: null, $empType, $teacherId]);
            logAudit(Auth::userId(), 'teacher.updated', 'teachers', $teacherId);
            setFlash('success', "Gespeichert: {$firstName} {$lastName}");
        }
        header('Location: ' . APP_URL . '/admin/teachers.php');
        exit;
    }

    /* Klassen-Zuordnung */
    if ($act === 'save_class_assignment') {
        $entryId  = (int)($_POST['entry_id'] ?? 0);
        $classIds = array_map('intval', (array)($_POST['class_ids'] ?? []));
        if ($entryId) {
            $db->beginTransaction();
            try {
                $db->prepare("DELETE FROM timetable_entry_classes WHERE entry_id=?")->execute([$entryId]);
                $stmt = $db->prepare("INSERT INTO timetable_entry_classes (entry_id, class_id) VALUES (?,?)");
                foreach ($classIds as $cid) { if ($cid > 0) $stmt->execute([$entryId, $cid]); }
                $db->commit();
                logAudit(Auth::userId(), 'timetable.class_assignment_changed', 'timetable_entries', $entryId);
                setFlash('success', 'Klassen-Zuordnung gespeichert.');
            } catch (\Exception $e) {
                $db->rollBack();
                setFlash('error', 'Fehler: ' . $e->getMessage());
            }
        }
        header('Location: ' . APP_URL . '/admin/teachers.php?tab=assignments&entry=' . $entryId);
        exit;
    }
}

/* ── Daten laden ──────────────────────────────────────── */
$teachers = $db->query(
    "SELECT t.id AS teacher_id, t.employment_type, t.phone, t.active_from,
            u.id AS user_id, u.first_name, u.last_name, u.email, u.is_active,
            (SELECT COUNT(*) FROM timetable_entries te2
             JOIN timetable_plans tp ON tp.id = te2.plan_id
             WHERE te2.teacher_id = t.id
               AND tp.valid_from <= CURDATE() AND tp.valid_until >= CURDATE()
            ) AS lesson_count
     FROM teachers t
     JOIN users u ON u.id = t.user_id
     ORDER BY u.last_name, u.first_name"
)->fetchAll();

/* wise_recipient_id – optionale Spalte, safe fetch */
$hasWise = false;
try {
    $db->query("SELECT wise_recipient_id FROM teachers LIMIT 1");
    $hasWise = true;
} catch (\Exception $e) { /* column not yet added */ }

if ($hasWise) {
    $wiseData = $db->query("SELECT id, wise_recipient_id FROM teachers")->fetchAll(\PDO::FETCH_KEY_PAIR);
}

$allClasses = $db->query("SELECT id, name FROM classes ORDER BY name")->fetchAll();

$tab   = $_GET['tab'] ?? 'list';
$today = date('Y-m-d');

/* aktueller Plan für Assignments-Tab */
$currentPlanId = null;
try {
    $currentPlanId = $db->query(
        "SELECT id FROM timetable_plans
         WHERE valid_from <= CURDATE() AND valid_until >= CURDATE() LIMIT 1"
    )->fetchColumn() ?: null;
} catch (\Exception $e) {}

$assignmentEntries = [];
$editEntry = null;
$editEntryClasses = [];

if ($tab === 'assignments' && $currentPlanId) {
    $assignmentEntries = $db->query(
        "SELECT te.id, te.weekday, te.time_start, te.time_end,
                sub.name AS subject_name,
                CONCAT(u.first_name,' ',u.last_name) AS teacher_name,
                GROUP_CONCAT(c.name  ORDER BY c.name SEPARATOR ', ') AS classes,
                GROUP_CONCAT(c.id    ORDER BY c.name SEPARATOR ',')   AS class_ids_str
         FROM timetable_entries te
         JOIN teachers t   ON t.id   = te.teacher_id
         JOIN users u      ON u.id   = t.user_id
         JOIN subjects sub ON sub.id = te.subject_id
         LEFT JOIN timetable_entry_classes tec ON tec.entry_id = te.id
         LEFT JOIN classes c ON c.id = tec.class_id
         WHERE te.plan_id = $currentPlanId
         GROUP BY te.id
         ORDER BY te.weekday, te.time_start, u.last_name"
    )->fetchAll();

    $editEntryId = (int)($_GET['entry'] ?? 0);
    if ($editEntryId) {
        foreach ($assignmentEntries as $ae) {
            if ((int)$ae['id'] === $editEntryId) {
                $editEntry = $ae;
                $editEntryClasses = $ae['class_ids_str']
                    ? array_map('intval', explode(',', $ae['class_ids_str'])) : [];
                break;
            }
        }
    }
}

$dayNames = ['','Mo','Di','Mi','Do','Fr'];
include TEMPLATE_PATH . '/layouts/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
  <h3 class="fw-bold mb-0" style="color:var(--wvh-primary)">
    <i class="bi bi-people-fill me-2"></i>Lehrkräfte
  </h3>
  <a href="<?= APP_URL ?>/admin/teachers_import.php" class="btn btn-wvh btn-sm">
    <i class="bi bi-upload me-1"></i>CSV Import
  </a>
</div>

<ul class="nav nav-tabs mb-4">
  <li class="nav-item">
    <a class="nav-link <?= $tab === 'list' ? 'active' : '' ?>"
       href="<?= APP_URL ?>/admin/teachers.php?tab=list">
      <i class="bi bi-list-ul me-1"></i>Lehrkraft-Liste
    </a>
  </li>
  <li class="nav-item">
    <a class="nav-link <?= $tab === 'assignments' ? 'active' : '' ?>"
       href="<?= APP_URL ?>/admin/teachers.php?tab=assignments">
      <i class="bi bi-diagram-2 me-1"></i>Klassen-Zuordnungen
    </a>
  </li>
</ul>

<?php if ($tab === 'list'): ?>
<!-- ═══ TAB: Liste ═══════════════════════════════════════ -->
<div class="card wvh-card">
  <div class="card-header">
    <div class="card-icon bg-primary bg-opacity-10 text-primary"><i class="bi bi-people"></i></div>
    Alle Lehrkräfte (<?= count($teachers) ?>)
  </div>
  <div class="table-responsive">
    <table class="wvh-table">
      <thead>
        <tr>
          <th>Name</th>
          <th>E-Mail</th>
          <th>Typ</th>
          <th class="text-center">Stunden</th>
          <?php if ($hasWise): ?><th class="text-center">Wise</th><?php endif; ?>
          <th class="text-center">Aktiv</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($teachers as $t): ?>
        <tr>
          <td class="fw-medium"><?= e($t['first_name'] . ' ' . $t['last_name']) ?></td>
          <td class="small text-muted"><?= e($t['email']) ?></td>
          <td>
            <span class="badge fw-normal <?= $t['employment_type'] === 'honorar' ? 'bg-info' : 'bg-secondary' ?>">
              <?= $t['employment_type'] === 'honorar' ? 'Honorar' : 'Festangest.' ?>
            </span>
          </td>
          <td class="text-center">
            <?= $t['lesson_count'] > 0
              ? '<span class="badge bg-success">'.$t['lesson_count'].'</span>'
              : '<span class="text-muted">–</span>' ?>
          </td>
          <?php if ($hasWise): ?>
          <td class="text-center">
            <?= !empty($wiseData[$t['teacher_id']])
              ? '<i class="bi bi-check-circle-fill text-success"></i>'
              : '<i class="bi bi-dash-circle text-muted"></i>' ?>
          </td>
          <?php endif; ?>
          <td class="text-center">
            <?= $t['is_active']
              ? '<span class="badge bg-success fw-normal">Aktiv</span>'
              : '<span class="badge bg-secondary fw-normal">Inaktiv</span>' ?>
          </td>
          <td class="text-end pe-3">
            <button class="btn btn-sm btn-outline-primary"
                    data-bs-toggle="modal" data-bs-target="#teacherModal"
                    onclick="fillModal(<?= (int)$t['user_id'] ?>,<?= (int)$t['teacher_id'] ?>,
                      '<?= e(addslashes($t['first_name'])) ?>',
                      '<?= e(addslashes($t['last_name'])) ?>',
                      '<?= e(addslashes($t['phone'] ?? '')) ?>',
                      '<?= e($t['employment_type']) ?>')">
              <i class="bi bi-pencil me-1"></i>Bearbeiten
            </button>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="alert alert-info mt-3 small">
  <i class="bi bi-info-circle me-1"></i>
  Namensänderungen aktualisieren automatisch alle Stundenplan-Einträge (JOIN über user_id).
</div>

<!-- Modal -->
<div class="modal fade" id="teacherModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="save_teacher">
        <input type="hidden" name="user_id"    id="mUserId">
        <input type="hidden" name="teacher_id" id="mTeacherId">
        <div class="modal-header" style="background:var(--wvh-primary);color:#fff">
          <h5 class="modal-title fw-bold">Lehrkraft bearbeiten</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-6">
              <label class="form-label fw-semibold">Vorname *</label>
              <input type="text" name="first_name" id="mFirstName" class="form-control" required>
            </div>
            <div class="col-6">
              <label class="form-label fw-semibold">Nachname *</label>
              <input type="text" name="last_name" id="mLastName" class="form-control" required>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Anstellungsart</label>
              <select name="employment_type" id="mEmpType" class="form-select">
                <option value="honorar">Honorarkraft</option>
                <option value="festangestellt">Festangestellt</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Telefon</label>
              <input type="text" name="phone" id="mPhone" class="form-control" placeholder="+49 ...">
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Abbrechen</button>
          <button type="submit" class="btn btn-wvh"><i class="bi bi-floppy me-1"></i>Speichern</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function fillModal(uid, tid, fn, ln, phone, emp) {
  document.getElementById('mUserId').value    = uid;
  document.getElementById('mTeacherId').value = tid;
  document.getElementById('mFirstName').value = fn;
  document.getElementById('mLastName').value  = ln;
  document.getElementById('mPhone').value     = phone;
  document.getElementById('mEmpType').value   = emp;
}
</script>

<?php endif; /* list */ ?>


<?php if ($tab === 'assignments'): ?>
<!-- ═══ TAB: Klassen-Zuordnungen ════════════════════════ -->
<?php if (!$currentPlanId): ?>
<div class="alert alert-warning">
  <i class="bi bi-exclamation-triangle me-1"></i>
  Kein aktuell gültiger Stundenplan vorhanden.
</div>
<?php else: ?>
<div class="row g-4">

  <!-- Eintragsliste -->
  <div class="col-lg-6">
    <div class="card wvh-card">
      <div class="card-header">
        <div class="card-icon bg-warning bg-opacity-10 text-warning"><i class="bi bi-diagram-2"></i></div>
        Stunden (aktueller Plan)
        <span class="badge bg-secondary ms-1"><?= count($assignmentEntries) ?></span>
      </div>
      <div style="max-height:550px;overflow-y:auto">
        <table class="wvh-table">
          <thead><tr><th>Tag</th><th>Zeit</th><th>Fach</th><th>Lehrkraft</th><th>Klassen</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($assignmentEntries as $ae):
            $isSel = $editEntry && (int)$ae['id'] === (int)$editEntry['id'];
          ?>
          <tr class="<?= $isSel ? 'table-primary' : '' ?>">
            <td class="fw-medium text-center"><?= $dayNames[(int)$ae['weekday']] ?></td>
            <td class="small text-muted text-nowrap"><?= substr($ae['time_start'],0,5) ?></td>
            <td class="small fw-medium"><?= e($ae['subject_name']) ?></td>
            <td class="small"><?= e($ae['teacher_name']) ?></td>
            <td class="small">
              <?= $ae['classes']
                ? '<span class="badge bg-secondary fw-normal">'.e($ae['classes']).'</span>'
                : '<span class="text-muted">–</span>' ?>
            </td>
            <td class="text-end pe-2">
              <a href="?tab=assignments&amp;entry=<?= $ae['id'] ?>"
                 class="btn btn-xs btn-sm py-0 px-2 <?= $isSel ? 'btn-primary' : 'btn-outline-secondary' ?>">
                <i class="bi bi-pencil"></i>
              </a>
            </td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Bearbeitungsformular -->
  <div class="col-lg-6">
  <?php if ($editEntry): ?>
    <div class="card wvh-card">
      <div class="card-header" style="background:var(--wvh-primary);color:#fff">
        <div class="card-icon" style="background:rgba(255,255,255,.2);color:#fff">
          <i class="bi bi-pencil-square"></i>
        </div>
        Klassen-Zuordnung bearbeiten
      </div>
      <div class="card-body">
        <div class="mb-3 p-2 rounded bg-light">
          <div class="fw-semibold"><?= e($editEntry['subject_name']) ?></div>
          <div class="small text-muted">
            <?= $dayNames[(int)$editEntry['weekday']] ?> ·
            <?= substr($editEntry['time_start'],0,5) ?>–<?= substr($editEntry['time_end'],0,5) ?> ·
            <?= e($editEntry['teacher_name']) ?>
          </div>
          <div class="small text-muted mt-1">
            Aktuell: <?= $editEntry['classes'] ? e($editEntry['classes']) : '– keine –' ?>
          </div>
        </div>
        <form method="POST">
          <?= csrfField() ?>
          <input type="hidden" name="action"   value="save_class_assignment">
          <input type="hidden" name="entry_id" value="<?= $editEntry['id'] ?>">
          <label class="form-label fw-semibold">Klassen zuweisen</label>
          <div class="row g-2 mb-3">
            <?php foreach ($allClasses as $cl):
              $checked = in_array((int)$cl['id'], $editEntryClasses);
            ?>
            <div class="col-4">
              <div class="form-check">
                <input type="checkbox" name="class_ids[]"
                       id="cl<?= $cl['id'] ?>" value="<?= $cl['id'] ?>"
                       class="form-check-input" <?= $checked ? 'checked' : '' ?>>
                <label class="form-check-label small" for="cl<?= $cl['id'] ?>"><?= e($cl['name']) ?></label>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
          <div class="d-flex gap-2">
            <button type="submit" class="btn btn-wvh">
              <i class="bi bi-floppy me-1"></i>Speichern
            </button>
            <a href="?tab=assignments" class="btn btn-outline-secondary">Abbrechen</a>
          </div>
        </form>
      </div>
    </div>
  <?php else: ?>
    <div class="card wvh-card">
      <div class="card-body text-center py-5 text-muted">
        <i class="bi bi-arrow-left fs-2 d-block mb-3"></i>
        Stunden-Eintrag links auswählen.
      </div>
    </div>
  <?php endif; ?>
  </div>

</div>
<?php endif; /* currentPlan */ ?>
<?php endif; /* assignments */ ?>

<?php include TEMPLATE_PATH . '/layouts/footer.php'; ?>
