<?php
/**
 * WvH – Sonderveranstaltungen (Klassenfahrten, Projekttage etc.)
 */
require_once __DIR__ . '/../config/bootstrap.php';
use Auth\Auth;
Auth::requireRole(ROLE_ADMIN, ROLE_VERWALTUNG);

$db       = getDB();
$myUserId = Auth::userId();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $act = $_POST['action'] ?? '';

    if ($act === 'save') {
        $id        = (int)($_POST['id'] ?? 0);
        $name      = trim($_POST['name'] ?? '');
        $type      = in_array($_POST['type'] ?? '', ['ferien','feiertag_bw','schliestag','sonderevent','projektwoche'])
                     ? $_POST['type'] : 'sonderevent';
        $dateFrom  = $_POST['date_from']  ?? '';
        $dateUntil = $_POST['date_until'] ?? '';
        $affectsAll= !empty($_POST['affects_all']) ? 1 : 0;
        $notes     = trim($_POST['notes'] ?? '');
        $classIds  = array_map('intval', (array)($_POST['class_ids'] ?? []));

        if ($name && $dateFrom && $dateUntil) {
            if ($id) {
                $db->prepare(
                    "UPDATE school_calendar SET name=?,type=?,date_from=?,date_until=?,affects_all=?,notes=? WHERE id=?"
                )->execute([$name,$type,$dateFrom,$dateUntil,$affectsAll,$notes?:null,$id]);
                $db->prepare("DELETE FROM calendar_event_classes WHERE event_id=?")->execute([$id]);
            } else {
                $db->prepare(
                    "INSERT INTO school_calendar (name,type,date_from,date_until,affects_all,notes,created_by)
                     VALUES (?,?,?,?,?,?,?)"
                )->execute([$name,$type,$dateFrom,$dateUntil,$affectsAll,$notes?:null,$myUserId]);
                $id = (int)$db->lastInsertId();
            }
            if (!$affectsAll && $classIds) {
                $stmt = $db->prepare("INSERT INTO calendar_event_classes (event_id,class_id) VALUES (?,?)");
                foreach ($classIds as $cid) { if ($cid > 0) $stmt->execute([$id, $cid]); }
            }
            logAudit($myUserId, 'special_event.saved', 'school_calendar', $id);
            setFlash('success', "Ereignis gespeichert: {$name}");
        } else {
            setFlash('error', 'Name und Datum sind Pflichtfelder.');
        }
        header('Location: special_events.php'); exit;
    }

    if ($act === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $db->prepare("DELETE FROM school_calendar WHERE id=?")->execute([$id]);
            logAudit($myUserId, 'special_event.deleted', 'school_calendar', $id);
            setFlash('info', 'Ereignis gelöscht.');
        }
        header('Location: special_events.php'); exit;
    }
}

$events = $db->query(
    "SELECT sc.*, sc.created_by,
            GROUP_CONCAT(cec.class_id ORDER BY cec.class_id SEPARATOR ',') AS class_ids_str,
            GROUP_CONCAT(c.name       ORDER BY c.name SEPARATOR ', ')       AS class_names
     FROM school_calendar sc
     LEFT JOIN calendar_event_classes cec ON cec.event_id = sc.id
     LEFT JOIN classes c ON c.id = cec.class_id
     GROUP BY sc.id
     ORDER BY sc.date_from DESC"
)->fetchAll();

$allClasses = $db->query("SELECT id, name FROM classes WHERE is_active=1 ORDER BY name")->fetchAll();

$typeLabels = [
    'ferien'       => 'Ferien',
    'feiertag_bw'  => 'Feiertag BW',
    'schliestag'   => 'Schließtag',
    'sonderevent'  => 'Sonderereignis',
    'projektwoche' => 'Projektwoche',
];
$typeColors = [
    'ferien'=>'bg-primary','feiertag_bw'=>'bg-danger','schliestag'=>'bg-secondary',
    'sonderevent'=>'bg-purple','projektwoche'=>'bg-success',
];

$editEvent = null;
if (isset($_GET['edit'])) {
    foreach ($events as $ev) {
        if ((int)$ev['id'] === (int)$_GET['edit']) { $editEvent = $ev; break; }
    }
}

$pageTitle   = 'Sonderveranstaltungen';
$breadcrumbs = ['Admin' => APP_URL.'/admin/users.php', 'Sonderveranstaltungen' => null];
include TEMPLATE_PATH . '/layouts/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
  <h3 class="fw-bold mb-0" style="color:var(--wvh-primary)">
    <i class="bi bi-star-fill me-2"></i>Sonderveranstaltungen
  </h3>
  <a href="?new=1" class="btn btn-wvh btn-sm">
    <i class="bi bi-plus-lg me-1"></i>Neu
  </a>
</div>

<div class="row g-4">
  <!-- Liste -->
  <div class="col-lg-7">
    <div class="card wvh-card">
      <div class="card-header">
        <div class="card-icon bg-purple-100 text-purple"><i class="bi bi-star"></i></div>
        Alle Ereignisse (<?= count($events) ?>)
      </div>
      <div class="table-responsive">
        <table class="wvh-table">
          <thead><tr><th>Name</th><th>Typ</th><th>Von</th><th>Bis</th><th>Klassen</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($events as $ev):
              $badgeCls = $typeColors[$ev['type']] ?? 'bg-secondary';
            ?>
            <tr>
              <td class="fw-medium"><?= e($ev['name']) ?></td>
              <td><span class="badge <?= $badgeCls ?> fw-normal">
                <?= $typeLabels[$ev['type']] ?? $ev['type'] ?>
              </span></td>
              <td class="small"><?= date('d.m.Y', strtotime($ev['date_from'])) ?></td>
              <td class="small"><?= date('d.m.Y', strtotime($ev['date_until'])) ?></td>
              <td class="small">
                <?= $ev['affects_all']
                  ? '<span class="badge bg-info fw-normal">Alle</span>'
                  : e($ev['class_names'] ?: '–') ?>
              </td>
              <td class="text-end pe-3">
                <a href="?edit=<?= $ev['id'] ?>" class="btn btn-sm btn-outline-primary">
                  <i class="bi bi-pencil"></i>
                </a>
                <form method="POST" class="d-inline">
                  <?= csrfField() ?>
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id"     value="<?= $ev['id'] ?>">
                  <button class="btn btn-sm btn-outline-danger"
                          onclick="return confirm('Löschen?')">
                    <i class="bi bi-trash"></i>
                  </button>
                </form>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Formular -->
  <div class="col-lg-5">
    <?php if (isset($_GET['new']) || $editEvent): ?>
    <div class="card wvh-card">
      <div class="card-header" style="background:var(--wvh-primary);color:#fff">
        <div class="card-icon" style="background:rgba(255,255,255,.2);color:#fff">
          <i class="bi bi-calendar-plus"></i>
        </div>
        <?= $editEvent ? 'Bearbeiten' : 'Neues Ereignis' ?>
      </div>
      <div class="card-body">
        <form method="POST">
          <?= csrfField() ?>
          <input type="hidden" name="action" value="save">
          <input type="hidden" name="id" value="<?= $editEvent ? $editEvent['id'] : '' ?>">

          <div class="mb-3">
            <label class="form-label fw-semibold">Name *</label>
            <input type="text" name="name" class="form-control" required
                   value="<?= e($editEvent['name'] ?? '') ?>" placeholder="z.B. Klassenfahrt 7a">
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Typ</label>
            <select name="type" class="form-select">
              <?php foreach ($typeLabels as $k => $v): ?>
              <option value="<?= $k ?>" <?= ($editEvent['type'] ?? 'sonderevent') === $k ? 'selected' : '' ?>>
                <?= $v ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="row g-2 mb-3">
            <div class="col">
              <label class="form-label fw-semibold">Von *</label>
              <input type="date" name="date_from" class="form-control" required
                     value="<?= $editEvent['date_from'] ?? '' ?>">
            </div>
            <div class="col">
              <label class="form-label fw-semibold">Bis *</label>
              <input type="date" name="date_until" class="form-control" required
                     value="<?= $editEvent['date_until'] ?? '' ?>">
            </div>
          </div>
          <div class="mb-3">
            <div class="form-check">
              <input type="checkbox" name="affects_all" id="affAll" class="form-check-input"
                     value="1" <?= !empty($editEvent['affects_all']) ? 'checked' : '' ?>
                     onchange="document.getElementById('classSection').style.display=this.checked?'none':'block'">
              <label class="form-check-label" for="affAll">Betrifft alle Klassen</label>
            </div>
          </div>
          <div id="classSection" class="mb-3"
               style="display:<?= !empty($editEvent['affects_all']) ? 'none' : 'block' ?>">
            <label class="form-label fw-semibold">Betroffene Klassen</label>
            <?php
              $selClassIds = $editEvent && $editEvent['class_ids_str']
                ? array_map('intval', explode(',', $editEvent['class_ids_str'])) : [];
            ?>
            <div class="row g-1">
              <?php foreach ($allClasses as $cl): ?>
              <div class="col-4">
                <div class="form-check">
                  <input type="checkbox" name="class_ids[]"
                         id="cls<?= $cl['id'] ?>" value="<?= $cl['id'] ?>"
                         class="form-check-input"
                         <?= in_array((int)$cl['id'], $selClassIds) ? 'checked' : '' ?>>
                  <label class="form-check-label small" for="cls<?= $cl['id'] ?>">
                    <?= e($cl['name']) ?>
                  </label>
                </div>
              </div>
              <?php endforeach; ?>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Notiz</label>
            <textarea name="notes" class="form-control" rows="2"
                      placeholder="Optionale Anmerkung …"><?= e($editEvent['notes'] ?? '') ?></textarea>
          </div>
          <div class="d-flex gap-2">
            <button type="submit" class="btn btn-wvh">
              <i class="bi bi-floppy me-1"></i>Speichern
            </button>
            <a href="special_events.php" class="btn btn-outline-secondary">Abbrechen</a>
          </div>
        </form>
      </div>
    </div>
    <?php else: ?>
    <div class="card wvh-card">
      <div class="card-body text-center py-5 text-muted">
        <i class="bi bi-arrow-left fs-2 d-block mb-2"></i>
        Ereignis auswählen oder „Neu" klicken.
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php include TEMPLATE_PATH . '/layouts/footer.php'; ?>
