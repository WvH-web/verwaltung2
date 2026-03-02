<?php
/**
 * WvH – Ferien & Feiertage (BW / Stuttgart)
 */
require_once __DIR__ . '/../config/bootstrap.php';
use Auth\Auth;

Auth::requireRole(ROLE_ADMIN, ROLE_VERWALTUNG);

$pageTitle   = 'Ferien & Feiertage';
$breadcrumbs = ['Admin' => APP_URL . '/admin/users.php', 'Ferien & Feiertage' => null];
$db = getDB();

// Prüfen ob Tabelle existiert
$tableExists = false;
try {
    $db->query("SELECT 1 FROM school_holidays LIMIT 1");
    $tableExists = true;
} catch (\Throwable $e) {}

$schoolYear = $_GET['sy'] ?? '2025-2026';

$types = [
    'sommerferien'          => ['label' => 'Sommerferien',        'color' => '#ea580c', 'bg' => '#fff7ed'],
    'herbstferien'          => ['label' => 'Herbstferien',         'color' => '#d97706', 'bg' => '#fffbeb'],
    'weihnachtsferien'      => ['label' => 'Weihnachtsferien',     'color' => '#2563eb', 'bg' => '#eff6ff'],
    'osterferien'           => ['label' => 'Osterferien',          'color' => '#16a34a', 'bg' => '#f0fdf4'],
    'pfingstferien'         => ['label' => 'Pfingstferien',        'color' => '#7c3aed', 'bg' => '#f5f3ff'],
    'faschingsferien'       => ['label' => 'Faschingsferien',      'color' => '#db2777', 'bg' => '#fdf2f8'],
    'feiertag'              => ['label' => 'Feiertag',             'color' => '#dc2626', 'bg' => '#fef2f2'],
    'beweglicher_ferientag' => ['label' => 'Beweg. Ferientag',     'color' => '#0891b2', 'bg' => '#ecfeff'],
    'sonstiges'             => ['label' => 'Sonstiges',            'color' => '#6b7280', 'bg' => '#f9fafb'],
];

// ── POST ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tableExists) {
    requireCsrf();
    $act = $_POST['action'] ?? '';

    if ($act === 'save') {
        $id    = (int)($_POST['id'] ?? 0);
        $sy    = trim($_POST['school_year'] ?? $schoolYear);
        $name  = trim($_POST['name'] ?? '');
        $type  = $_POST['type'] ?? 'sonstiges';
        $from  = $_POST['date_from']  ?? '';
        $until = $_POST['date_until'] ?? '';
        $free  = isset($_POST['is_school_free']) ? 1 : 0;
        $notes = trim($_POST['notes'] ?? '');

        if (!$name || !$from || !$until) {
            setFlash('error', 'Name und beide Datumsfelder sind Pflichtfelder.');
        } elseif ($until < $from) {
            setFlash('error', '"Bis"-Datum darf nicht vor "Von"-Datum liegen.');
        } else {
            if ($id) {
                $db->prepare("UPDATE school_holidays
                              SET school_year=?,name=?,type=?,date_from=?,date_until=?,is_school_free=?,notes=?
                              WHERE id=?")
                   ->execute([$sy,$name,$type,$from,$until,$free,$notes,$id]);
                logAudit(Auth::userId(), 'holiday.updated', 'school_holidays', $id);
                setFlash('success', '„'.htmlspecialchars($name).'" aktualisiert.');
            } else {
                $db->prepare("INSERT INTO school_holidays
                              (school_year,name,type,date_from,date_until,is_school_free,notes,created_by)
                              VALUES (?,?,?,?,?,?,?,?)")
                   ->execute([$sy,$name,$type,$from,$until,$free,$notes,Auth::userId()]);
                logAudit(Auth::userId(), 'holiday.created', 'school_holidays', (int)$db->lastInsertId());
                setFlash('success', '„'.htmlspecialchars($name).'" angelegt.');
            }
            $schoolYear = $sy;
        }
        header('Location: ' . APP_URL . '/admin/holidays.php?sy=' . urlencode($schoolYear));
        exit;
    }

    if ($act === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $s  = $db->prepare("SELECT name, school_year FROM school_holidays WHERE id=?");
        $s->execute([$id]);
        $row = $s->fetch();
        if ($row) {
            $db->prepare("DELETE FROM school_holidays WHERE id=?")->execute([$id]);
            logAudit(Auth::userId(), 'holiday.deleted', 'school_holidays', $id);
            setFlash('success', '„'.htmlspecialchars($row['name']).'" gelöscht.');
            header('Location: ' . APP_URL . '/admin/holidays.php?sy=' . urlencode($row['school_year']));
            exit;
        }
    }
}

// ── Daten ──────────────────────────────────────────────────
$holidays = [];
$years    = ['2025-2026'];

if ($tableExists) {
    $s = $db->prepare("SELECT * FROM school_holidays WHERE school_year=? ORDER BY date_from, type");
    $s->execute([$schoolYear]);
    $holidays = $s->fetchAll();

    $dbYears = $db->query("SELECT DISTINCT school_year FROM school_holidays ORDER BY school_year DESC")
                  ->fetchAll(\PDO::FETCH_COLUMN);
    if ($dbYears) $years = $dbYears;
    if (!in_array($schoolYear, $years)) array_unshift($years, $schoolYear);
}

$today = date('Y-m-d');
$todayHoliday = null;
$nextHoliday  = null;
foreach ($holidays as $h) {
    if (!$todayHoliday && $h['date_from'] <= $today && $h['date_until'] >= $today) $todayHoliday = $h;
    if (!$nextHoliday  && $h['is_school_free'] && $h['date_from'] > $today) $nextHoliday = $h;
}

// Tages-Map für Kalender
$dayMap = [];
foreach ($holidays as $h) {
    if (!$h['is_school_free']) continue;
    $cur = new \DateTime($h['date_from']);
    $end = new \DateTime($h['date_until']);
    while ($cur <= $end) {
        $dayMap[$cur->format('Y-m-d')] = $h;
        $cur->modify('+1 day');
    }
}

include TEMPLATE_PATH . '/layouts/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
  <h3 class="fw-bold mb-0" style="color:var(--wvh-primary)">
    <i class="bi bi-calendar-heart me-2"></i>Ferien & Feiertage
  </h3>
  <div class="d-flex gap-2 align-items-center">
    <label class="small text-muted me-1">Schuljahr:</label>
    <select class="form-select form-select-sm" style="width:130px" onchange="location='?sy='+this.value">
      <?php foreach ($years as $y): ?>
        <option value="<?= e($y) ?>" <?= $y===$schoolYear?'selected':''?>><?= e($y) ?></option>
      <?php endforeach; ?>
    </select>
    <?php if ($tableExists): ?>
    <button class="btn btn-wvh btn-sm" data-bs-toggle="modal" data-bs-target="#editModal"
            onclick="openModal(null)">
      <i class="bi bi-plus-lg me-1"></i>Neuer Eintrag
    </button>
    <?php endif; ?>
  </div>
</div>

<?php if (!$tableExists): ?>
<div class="alert alert-warning">
  <i class="bi bi-exclamation-triangle me-2"></i>
  <strong>Tabelle fehlt!</strong> Bitte zuerst <code>config/PATCH_holidays.sql</code>
  in phpMyAdmin ausführen, um die Ferien-Tabelle anzulegen.
</div>
<?php else: ?>

<!-- Status-Karten -->
<div class="row g-3 mb-4">
  <div class="col-md-4">
    <div class="card wvh-card">
      <div class="card-body py-3 d-flex align-items-center gap-3">
        <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
             style="width:44px;height:44px;background:<?= $todayHoliday?'#fef2f2':'#f0fdf4' ?>">
          <i class="bi <?= $todayHoliday?'bi-sun-fill text-danger':'bi-book text-success' ?> fs-5"></i>
        </div>
        <div>
          <div class="small text-muted">Heute</div>
          <div class="fw-semibold"><?= $todayHoliday ? e($todayHoliday['name']) : 'Schultag' ?></div>
          <?php if ($todayHoliday): ?>
            <div class="small text-muted">bis <?= date('d.m.Y', strtotime($todayHoliday['date_until'])) ?></div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card wvh-card">
      <div class="card-body py-3 d-flex align-items-center gap-3">
        <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
             style="width:44px;height:44px;background:#eff6ff">
          <i class="bi bi-calendar-event text-primary fs-5"></i>
        </div>
        <div>
          <div class="small text-muted">Nächste Ferien</div>
          <div class="fw-semibold"><?= $nextHoliday ? e($nextHoliday['name']) : '–' ?></div>
          <?php if ($nextHoliday):
            $dLeft = max(0, (int)round((strtotime($nextHoliday['date_from']) - time()) / 86400)); ?>
            <div class="small text-muted">in <?= $dLeft ?> Tag<?= $dLeft!==1?'en':'' ?></div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card wvh-card">
      <div class="card-body py-3 d-flex align-items-center gap-3">
        <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
             style="width:44px;height:44px;background:#f5f3ff">
          <i class="bi bi-list-check fs-5" style="color:#7c3aed"></i>
        </div>
        <div>
          <div class="small text-muted">Schuljahr <?= e($schoolYear) ?></div>
          <div class="fw-semibold"><?= count($holidays) ?> Einträge</div>
          <div class="small text-muted">
            <?= count(array_filter($holidays, fn($h)=>$h['type']==='feiertag')) ?> Feiertage &middot;
            <?= count(array_filter($holidays, fn($h)=>substr($h['type'],-6)==='ferien')) ?> Perioden
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Jahreskalender -->
<div class="card wvh-card mb-4">
  <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div class="d-flex align-items-center gap-2">
      <div class="card-icon bg-success bg-opacity-10 text-success"><i class="bi bi-calendar3"></i></div>
      Schuljahres-Kalender <?= e($schoolYear) ?>
    </div>
    <div class="d-flex flex-wrap gap-1">
      <?php foreach ($types as $k => $tc): if ($k === 'sonstiges') continue; ?>
        <span class="badge fw-normal" style="background:<?= $tc['bg'] ?>;color:<?= $tc['color'] ?>;
              border:1px solid <?= $tc['color'] ?>44;font-size:.62rem">
          <?= e($tc['label']) ?>
        </span>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="card-body p-3">
    <div class="overflow-auto">
      <div style="min-width:680px">
        <?php
        [$y1, $y2] = explode('-', $schoolYear);
        $calStart  = new \DateTime("{$y1}-09-01");
        $calEnd    = new \DateTime("{$y2}-09-14");
        $cur       = clone $calStart;
        $monthNames = ['','Jan','Feb','Mär','Apr','Mai','Jun','Jul','Aug','Sep','Okt','Nov','Dez'];
        while ($cur < $calEnd):
            $ym  = (int)$cur->format('Y');
            $m   = (int)$cur->format('m');
            $dim = (int)(new \DateTime("$ym-$m-01"))->format('t');
        ?>
        <div class="mb-3">
          <div class="small text-muted fw-semibold mb-1">
            <?= $monthNames[$m] ?> <?= $ym ?>
          </div>
          <div class="d-flex flex-wrap gap-1">
            <?php for ($d = 1; $d <= $dim; $d++):
              $ds   = sprintf('%04d-%02d-%02d', $ym, $m, $d);
              $dow  = (int)(new \DateTime($ds))->format('N');
              $isWE = $dow >= 6;
              $hol  = $dayMap[$ds] ?? null;
              $tc   = $hol ? $types[$hol['type']] : null;
              $title = date('d.m.Y', strtotime($ds)) . ($hol ? ' · ' . $hol['name'] : ($isWE ? ' · WE' : ''));
            ?>
            <div title="<?= e($title) ?>"
                 style="width:22px;height:22px;border-radius:4px;font-size:.6rem;
                        display:flex;align-items:center;justify-content:center;
                        background:<?= $tc ? $tc['bg'] : ($isWE ? '#f3f4f6' : '#fff') ?>;
                        border:1px solid <?= $tc ? $tc['color'].'44' : ($isWE ? '#e5e7eb' : '#dee2e6') ?>;
                        color:<?= $tc ? $tc['color'] : ($isWE ? '#9ca3af' : '#374151') ?>;
                        font-weight:<?= $tc ? '700' : '400' ?>;
                        <?= $ds===$today ? 'outline:2px solid #2d6fa4;outline-offset:-1px;' : '' ?>">
              <?= $d ?>
            </div>
            <?php endfor; ?>
          </div>
        </div>
        <?php $cur->modify('+1 month'); endwhile; ?>
      </div>
    </div>
    <div class="small text-muted mt-1">
      <i class="bi bi-circle-fill me-1" style="font-size:.5rem"></i>Heute mit blauem Rahmen
    </div>
  </div>
</div>

<!-- Eintrags-Tabelle -->
<div class="card wvh-card">
  <div class="card-header">
    <div class="card-icon bg-primary bg-opacity-10 text-primary"><i class="bi bi-table"></i></div>
    Alle Einträge – <?= e($schoolYear) ?>
    <span class="badge bg-secondary ms-2"><?= count($holidays) ?></span>
  </div>
  <?php if (empty($holidays)): ?>
  <div class="card-body text-center py-4 text-muted">
    <i class="bi bi-inbox fs-2 d-block mb-2"></i>
    Keine Einträge vorhanden.
    <a href="#" data-bs-toggle="modal" data-bs-target="#editModal" onclick="openModal(null)">
      Ersten Eintrag anlegen
    </a>
  </div>
  <?php else: ?>
  <div class="table-responsive">
    <table class="wvh-table">
      <thead>
        <tr>
          <th>Name</th><th>Typ</th><th>Von</th><th>Bis</th>
          <th class="text-center">Tage</th><th class="text-center">Schulfrei</th>
          <th>Notiz</th><th style="width:80px"></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($holidays as $h):
          $tc   = $types[$h['type']] ?? $types['sonstiges'];
          $days = max(1, (int)round((strtotime($h['date_until'])-strtotime($h['date_from']))/86400)+1);
          $now  = $h['date_from'] <= $today && $h['date_until'] >= $today;
        ?>
        <tr class="<?= $now ? 'table-warning' : '' ?>">
          <td class="fw-medium">
            <?= e($h['name']) ?>
            <?php if ($now): ?>
              <span class="badge bg-warning text-dark ms-1" style="font-size:.58rem">HEUTE</span>
            <?php endif; ?>
          </td>
          <td>
            <span class="badge fw-normal px-2"
                  style="background:<?= $tc['bg'] ?>;color:<?= $tc['color'] ?>;
                         border:1px solid <?= $tc['color'] ?>44">
              <?= e($tc['label']) ?>
            </span>
          </td>
          <td class="text-nowrap"><?= date('d.m.Y', strtotime($h['date_from'])) ?></td>
          <td class="text-nowrap"><?= date('d.m.Y', strtotime($h['date_until'])) ?></td>
          <td class="text-center"><?= $days ?></td>
          <td class="text-center">
            <?= $h['is_school_free']
              ? '<i class="bi bi-check-circle-fill text-success"></i>'
              : '<i class="bi bi-dash-circle text-muted"></i>' ?>
          </td>
          <td class="small text-muted"><?= e($h['notes'] ?? '') ?></td>
          <td class="text-end pe-3">
            <button class="btn btn-sm btn-outline-secondary"
                    data-bs-toggle="modal" data-bs-target="#editModal"
                    onclick='openModal(<?= htmlspecialchars(json_encode($h), ENT_QUOTES) ?>)'>
              <i class="bi bi-pencil"></i>
            </button>
            <form method="POST" class="d-inline"
                  onsubmit="return confirm('„<?= e(addslashes($h['name'])) ?>" wirklich löschen?')">
              <?= csrfField() ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= $h['id'] ?>">
              <button type="submit" class="btn btn-sm btn-outline-danger">
                <i class="bi bi-trash"></i>
              </button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<!-- Edit-Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="POST">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" id="hId" value="0">
        <div class="modal-header" style="background:var(--wvh-primary);color:#fff">
          <h5 class="modal-title fw-bold" id="hTitle">Neuer Eintrag</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-8">
              <label class="form-label fw-semibold">Name *</label>
              <input type="text" name="name" id="hName" class="form-control" required
                     placeholder="z.B. Herbstferien 2025">
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">Schuljahr</label>
              <input type="text" name="school_year" id="hSY" class="form-control"
                     value="<?= e($schoolYear) ?>" placeholder="2025-2026">
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">Typ *</label>
              <select name="type" id="hType" class="form-select">
                <?php foreach ($types as $k => $tc): ?>
                  <option value="<?= $k ?>"><?= e($tc['label']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">Von *</label>
              <input type="date" name="date_from" id="hFrom" class="form-control" required>
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">Bis *</label>
              <input type="date" name="date_until" id="hUntil" class="form-control" required>
            </div>
            <div class="col-12">
              <label class="form-label fw-semibold">Notiz</label>
              <input type="text" name="notes" id="hNotes" class="form-control">
            </div>
            <div class="col-12">
              <div class="form-check">
                <input type="checkbox" name="is_school_free" id="hFree"
                       class="form-check-input" value="1" checked>
                <label class="form-check-label fw-medium" for="hFree">
                  Schulfrei (kein Unterricht)
                </label>
              </div>
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
function openModal(h) {
  if (!h) {
    document.getElementById('hTitle').textContent = 'Neuer Eintrag';
    document.getElementById('hId').value    = '0';
    document.getElementById('hName').value  = '';
    document.getElementById('hType').value  = 'feiertag';
    document.getElementById('hFrom').value  = '';
    document.getElementById('hUntil').value = '';
    document.getElementById('hNotes').value = '';
    document.getElementById('hFree').checked = true;
    document.getElementById('hSY').value    = '<?= e($schoolYear) ?>';
  } else {
    document.getElementById('hTitle').textContent = 'Eintrag bearbeiten';
    document.getElementById('hId').value    = h.id;
    document.getElementById('hName').value  = h.name;
    document.getElementById('hType').value  = h.type;
    document.getElementById('hFrom').value  = h.date_from;
    document.getElementById('hUntil').value = h.date_until;
    document.getElementById('hNotes').value = h.notes || '';
    document.getElementById('hFree').checked = (h.is_school_free == 1);
    document.getElementById('hSY').value    = h.school_year;
  }
}
document.getElementById('hFrom').addEventListener('change', function() {
  if (!document.getElementById('hUntil').value) {
    document.getElementById('hUntil').value = this.value;
  }
});
</script>

<?php endif; /* tableExists */ ?>

<?php include TEMPLATE_PATH . '/layouts/footer.php'; ?>
