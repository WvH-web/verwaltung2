<?php
/**
 * WvH – Sonderdeputate (monatlich & einmalig)
 */
require_once __DIR__ . '/../config/bootstrap.php';

use Auth\Auth;

Auth::requireRole(ROLE_ADMIN, ROLE_VERWALTUNG);

$pageTitle   = 'Sonderdeputate';
$breadcrumbs = ['Deputate' => null, 'Sonderdeputate' => null];
$db          = getDB();

// Aktueller Monat (Default)
$selMonth = $_GET['month'] ?? date('Y-m');
$monthDate = $selMonth . '-01';

/* ── AKTIONEN ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $action = $_POST['action'] ?? '';

    // Sonderdeputat zuweisen
    if ($action === 'assign') {
        $teacherId = (int)$_POST['teacher_id'];
        $typeId    = (int)$_POST['type_id'];
        $month     = $_POST['billing_month'] . '-01';
        $units     = (float)($_POST['units'] ?? 1);
        $rateOvr   = $_POST['rate_override']   !== '' ? (float)$_POST['rate_override']   : null;
        $amtOvr    = $_POST['amount_override']  !== '' ? (float)$_POST['amount_override'] : null;
        $isOneTime = (int)($_POST['is_one_time'] ?? 0);
        $isRetro   = (int)($_POST['is_retroactive'] ?? 0);
        $notes     = trim($_POST['notes'] ?? '');

        $stmt = $db->prepare(
            "INSERT INTO deputate_assignments
               (teacher_id,type_id,billing_month,units,rate_override,amount_override,
                is_one_time,is_retroactive,assigned_by,notes)
             VALUES (?,?,?,?,?,?,?,?,?,?)"
        );
        $stmt->execute([$teacherId,$typeId,$month,$units,$rateOvr,$amtOvr,
                        $isOneTime,$isRetro,Auth::userId(),$notes]);
        $newId = (int)$db->lastInsertId();

        logAudit(Auth::userId(), 'deputate.assigned', 'deputate_assignments', $newId, [], [
            'teacher_id'=>$teacherId,'type_id'=>$typeId,'month'=>$month,'units'=>$units,
        ]);

        $label = $isRetro ? ' (rückwirkend)' : '';
        setFlash('success', "Sonderdeputat zugewiesen{$label}.");
        header('Location: ' . APP_URL . '/deputate/assignments.php?month=' . $_POST['billing_month']);
        exit;
    }

    // Zuweisung löschen
    if ($action === 'delete') {
        $id = (int)$_POST['assignment_id'];
        $s  = $db->prepare("SELECT * FROM deputate_assignments WHERE id=?");
        $s->execute([$id]);
        $old = $s->fetch();
        if ($old) {
            $db->prepare("DELETE FROM deputate_assignments WHERE id=?")->execute([$id]);
            logAudit(Auth::userId(),'deputate.deleted','deputate_assignments',$id,$old,[]);
            setFlash('success','Deputat-Zuweisung gelöscht.');
        }
        header('Location: ' . APP_URL . '/deputate/assignments.php?month=' . $selMonth);
        exit;
    }
}

/* ── DATEN LADEN ── */

// Alle aktiven Lehrer
$teachers = $db->query(
    "SELECT t.id, CONCAT(u.first_name,' ',u.last_name) AS display_name, t.employment_type,t.hourly_rate
     FROM teachers t JOIN users u ON u.id=t.user_id
     WHERE u.is_active=1 ORDER BY u.last_name,u.first_name"
)->fetchAll();

// Deputat-Typen
$depTypes = $db->query(
    "SELECT * FROM deputate_types WHERE is_active=1 ORDER BY name"
)->fetchAll();

// Zuweisungen für gewählten Monat
$assignments = $db->query(
    "SELECT da.*, dt.name AS type_name, dt.default_rate, dt.is_recurring,
            CONCAT(u.first_name,' ',u.last_name) AS teacher_name, t.employment_type,
            CONCAT(ub.first_name,' ',ub.last_name) AS assigned_by_name
     FROM deputate_assignments da
     JOIN deputate_types dt ON dt.id=da.type_id
     JOIN teachers t ON t.id=da.teacher_id
     JOIN users u ON u.id=t.user_id
     JOIN users ub ON ub.id=da.assigned_by
     WHERE da.billing_month='$monthDate'
     ORDER BY u.last_name,u.first_name,dt.name"
)->fetchAll();

// Monate für Navigation (letzten 6 + nächste 3)
$months = [];
for ($i = -6; $i <= 3; $i++) {
    $d = date('Y-m', strtotime("first day of $i month"));
    $months[$d] = monthLabel($d . '-01');
}

include TEMPLATE_PATH . '/layouts/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
  <h3 class="fw-bold mb-0" style="color:var(--wvh-primary)">
    <i class="bi bi-award-fill me-2"></i>Sonderdeputate
  </h3>
  <button class="btn btn-wvh" data-bs-toggle="modal" data-bs-target="#addDeputatModal">
    <i class="bi bi-plus-lg me-2"></i>Deputat zuweisen
  </button>
</div>

<!-- Monats-Navigation -->
<div class="card wvh-card mb-4">
  <div class="card-body py-2 d-flex align-items-center gap-2 flex-wrap">
    <i class="bi bi-calendar-month text-muted me-1"></i>
    <span class="text-muted small fw-medium">Monat:</span>
    <?php foreach ($months as $mKey => $mLabel): ?>
      <a href="<?= APP_URL ?>/deputate/assignments.php?month=<?= $mKey ?>"
         class="btn btn-sm <?= $mKey === $selMonth ? 'btn-wvh' : 'btn-outline-secondary' ?>">
        <?= e($mLabel) ?>
      </a>
    <?php endforeach; ?>
  </div>
</div>

<div class="row g-4">

  <!-- Linke Spalte: Zusammenfassung pro Lehrer -->
  <div class="col-lg-4">
    <div class="card wvh-card">
      <div class="card-header">
        <div class="card-icon bg-success bg-opacity-10 text-success"><i class="bi bi-people-fill"></i></div>
        <?= e(monthLabel($monthDate)) ?> – Übersicht
      </div>
      <?php
        // Gruppieren nach Lehrer
        $byTeacher = [];
        foreach ($assignments as $a) {
            $byTeacher[$a['teacher_name']][] = $a;
        }
        arsort($byTeacher); // A–Z
      ?>
      <?php if (empty($assignments)): ?>
        <div class="card-body text-center text-muted py-4">
          <i class="bi bi-inbox fs-2 d-block mb-2"></i>
          Noch keine Deputate für diesen Monat.
        </div>
      <?php else: ?>
        <div class="list-group list-group-flush" style="max-height:500px;overflow-y:auto">
          <?php foreach ($byTeacher as $teacherName => $tAssignments):
            $total = 0;
            foreach ($tAssignments as $a) {
                $rate = $a['rate_override'] ?? $a['default_rate'] ?? 0;
                $total += $a['amount_override'] ?? ($a['units'] * $rate);
            }
          ?>
          <div class="list-group-item px-3 py-2">
            <div class="d-flex justify-content-between align-items-start">
              <div>
                <div class="fw-semibold small"><?= e($teacherName) ?></div>
                <div class="text-muted" style="font-size:.72rem">
                  <?= count($tAssignments) ?> Position<?= count($tAssignments)>1?'en':'' ?>
                </div>
              </div>
              <span class="badge bg-primary-subtle text-primary border border-primary fw-semibold">
                <?= formatEuro($total) ?>
              </span>
            </div>
            <!-- Mini-Liste -->
            <?php foreach ($tAssignments as $a): ?>
            <div class="d-flex justify-content-between align-items-center mt-1" style="font-size:.71rem">
              <span class="text-muted text-truncate me-2">
                <?php if ($a['is_one_time']): ?>
                  <span class="badge bg-warning text-dark me-1" style="font-size:.6rem">einmalig</span>
                <?php endif; ?>
                <?php if ($a['is_retroactive']): ?>
                  <span class="badge bg-info me-1" style="font-size:.6rem">rückwirkend</span>
                <?php endif; ?>
                <?= e($a['type_name']) ?>
                <?php if ($a['units'] != 1): ?> (<?= $a['units'] ?> UE)<?php endif; ?>
              </span>
              <div class="d-flex align-items-center gap-1">
                <span class="fw-semibold">
                  <?php
                    $rate = $a['rate_override'] ?? $a['default_rate'] ?? 0;
                    $amt  = $a['amount_override'] ?? ($a['units'] * $rate);
                    echo formatEuro($amt);
                  ?>
                </span>
                <form method="POST" class="d-inline">
                  <?= csrfField() ?>
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="assignment_id" value="<?= $a['id'] ?>">
                  <button type="submit" class="btn btn-link btn-sm p-0 text-danger"
                          onclick="return confirm('Deputat-Zuweisung löschen?')"
                          title="Löschen">
                    <i class="bi bi-trash3" style="font-size:.8rem"></i>
                  </button>
                </form>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
          <?php endforeach; ?>
        </div>

        <!-- Gesamt -->
        <?php
          $grandTotal = 0;
          foreach ($assignments as $a) {
              $rate = $a['rate_override'] ?? $a['default_rate'] ?? 0;
              $grandTotal += $a['amount_override'] ?? ($a['units'] * $rate);
          }
        ?>
        <div class="card-footer d-flex justify-content-between align-items-center fw-bold">
          <span>Gesamt <?= e(monthLabel($monthDate)) ?></span>
          <span class="text-success fs-5"><?= formatEuro($grandTotal) ?></span>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Rechte Spalte: Detailtabelle -->
  <div class="col-lg-8">
    <div class="card wvh-card">
      <div class="card-header">
        <div class="card-icon bg-warning bg-opacity-10 text-warning"><i class="bi bi-list-check"></i></div>
        Alle Zuweisungen – <?= e(monthLabel($monthDate)) ?>
        <span class="ms-auto badge bg-secondary"><?= count($assignments) ?></span>
      </div>

      <?php if (empty($assignments)): ?>
        <div class="card-body text-center text-muted py-5">
          <i class="bi bi-award fs-1 d-block mb-3"></i>
          <p>Keine Sonderdeputate für <?= e(monthLabel($monthDate)) ?>.</p>
          <button class="btn btn-wvh" data-bs-toggle="modal" data-bs-target="#addDeputatModal">
            <i class="bi bi-plus-lg me-2"></i>Erstes Deputat zuweisen
          </button>
        </div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="wvh-table">
            <thead>
              <tr>
                <th>Lehrkraft</th>
                <th>Deputat-Typ</th>
                <th>UE</th>
                <th>Satz</th>
                <th>Betrag</th>
                <th>Art</th>
                <th>Zugewiesen von</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($assignments as $a):
                $rate = $a['rate_override'] ?? $a['default_rate'] ?? 0;
                $amt  = $a['amount_override'] ?? ($a['units'] * $rate);
              ?>
              <tr>
                <td>
                  <div class="fw-medium small"><?= e($a['teacher_name']) ?></div>
                  <small class="text-muted"><?= $a['employment_type'] === 'honorar' ? 'Honorar' : 'Festangest.' ?></small>
                </td>
                <td>
                  <div class="small"><?= e($a['type_name']) ?></div>
                  <?php if ($a['notes']): ?>
                    <small class="text-muted"><?= e($a['notes']) ?></small>
                  <?php endif; ?>
                </td>
                <td class="small"><?= $a['units'] ?></td>
                <td class="small">
                  <?php if ($a['amount_override'] !== null): ?>
                    <span class="badge bg-secondary">Pauschal</span>
                  <?php else: ?>
                    <?= formatEuro((float)$rate) ?>
                  <?php endif; ?>
                </td>
                <td class="fw-semibold"><?= formatEuro((float)$amt) ?></td>
                <td>
                  <?php if ($a['is_one_time']): ?>
                    <span class="badge bg-warning text-dark">Einmalig</span>
                  <?php else: ?>
                    <span class="badge bg-success-subtle text-success border border-success">Monatlich</span>
                  <?php endif; ?>
                  <?php if ($a['is_retroactive']): ?>
                    <span class="badge bg-info ms-1">Rückwirkend</span>
                  <?php endif; ?>
                </td>
                <td class="text-muted small"><?= e($a['assigned_by_name']) ?></td>
                <td class="text-end">
                  <form method="POST" class="d-inline">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="assignment_id" value="<?= $a['id'] ?>">
                    <button type="submit" class="btn btn-sm btn-outline-danger"
                            onclick="return confirm('Zuweisung wirklich löschen?')">
                      <i class="bi bi-trash3"></i>
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
  </div>
</div>


<!-- ===== Modal: Deputat zuweisen ===== -->
<div class="modal fade" id="addDeputatModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header" style="background:var(--wvh-primary);color:#fff">
        <h5 class="modal-title"><i class="bi bi-award me-2"></i>Sonderdeputat zuweisen</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="assign">

        <div class="modal-body">
          <div class="row g-3">

            <!-- Lehrer -->
            <div class="col-md-6">
              <label class="form-label fw-semibold">Lehrkraft *</label>
              <select name="teacher_id" class="form-select" required>
                <option value="">Bitte wählen…</option>
                <?php foreach ($teachers as $t): ?>
                  <option value="<?= $t['id'] ?>"><?= e($t['display_name']) ?>
                    <?= $t['employment_type']==='honorar' ? '(Honorar)' : '(Fest)' ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <!-- Monat -->
            <div class="col-md-6">
              <label class="form-label fw-semibold">Abrechnungsmonat *</label>
              <select name="billing_month" class="form-select" required>
                <?php foreach ($months as $mKey => $mLabel): ?>
                  <option value="<?= $mKey ?>" <?= $mKey === $selMonth ? 'selected' : '' ?>>
                    <?= e($mLabel) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <!-- Typ -->
            <div class="col-md-8">
              <label class="form-label fw-semibold">Deputat-Typ *</label>
              <select name="type_id" class="form-select" id="depTypeSelect" required>
                <option value="">Bitte wählen…</option>
                <?php foreach ($depTypes as $dt): ?>
                  <option value="<?= $dt['id'] ?>"
                          data-rate="<?= $dt['default_rate'] ?>"
                          data-recurring="<?= $dt['is_recurring'] ?>">
                    <?= e($dt['name']) ?>
                    (<?= formatEuro((float)$dt['default_rate']) ?>/UE)
                    <?= $dt['is_recurring'] ? '' : ' – Einmalig' ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <!-- Einheiten -->
            <div class="col-md-4">
              <label class="form-label fw-semibold">Einheiten (UE)</label>
              <input type="number" name="units" class="form-control" value="1" min="0.5" step="0.5">
            </div>

            <!-- Art (monatlich / einmalig) -->
            <div class="col-md-6">
              <label class="form-label fw-semibold">Art</label>
              <div class="d-flex gap-3 mt-1">
                <div class="form-check">
                  <input class="form-check-input" type="radio" name="is_one_time" id="typeMonthly" value="0" checked>
                  <label class="form-check-label" for="typeMonthly">
                    <i class="bi bi-repeat text-success me-1"></i>Monatlich wiederkehrend
                  </label>
                </div>
                <div class="form-check">
                  <input class="form-check-input" type="radio" name="is_one_time" id="typeOnce" value="1">
                  <label class="form-check-label" for="typeOnce">
                    <i class="bi bi-1-circle text-warning me-1"></i>Einmalig (dieser Monat)
                  </label>
                </div>
              </div>
            </div>

            <!-- Rückwirkend -->
            <div class="col-md-6">
              <label class="form-label fw-semibold">Rückwirkend?</label>
              <div class="mt-1">
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" name="is_retroactive" id="isRetro" value="1">
                  <label class="form-check-label" for="isRetro">
                    Rückwirkende Vergütung (erzeugt Korrekturhinweis)
                  </label>
                </div>
              </div>
            </div>

            <!-- Individuelle Sätze (optional) -->
            <div class="col-12">
              <div class="card bg-light border-0">
                <div class="card-body py-2 px-3">
                  <p class="small fw-semibold mb-2 text-muted">
                    <i class="bi bi-sliders me-1"></i>Optionale Überschreibungen (leer = Typ-Standard)
                  </p>
                  <div class="row g-2">
                    <div class="col-md-5">
                      <label class="form-label small">Abweichender Stundensatz (€/UE)</label>
                      <div class="input-group input-group-sm">
                        <span class="input-group-text">€</span>
                        <input type="number" name="rate_override" class="form-control" step="0.01" min="0" placeholder="Standard">
                      </div>
                    </div>
                    <div class="col-md-5">
                      <label class="form-label small">Oder: Pauschalbetrag (€)</label>
                      <div class="input-group input-group-sm">
                        <span class="input-group-text">€</span>
                        <input type="number" name="amount_override" class="form-control" step="0.01" min="0" placeholder="Kein Pauschal">
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Notiz -->
            <div class="col-12">
              <label class="form-label fw-semibold">Notiz / Begründung</label>
              <input type="text" name="notes" class="form-control" placeholder="z.B. Vertretung Klassenleitung März 2026">
            </div>

          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Abbrechen</button>
          <button type="submit" class="btn btn-wvh">
            <i class="bi bi-check-lg me-2"></i>Deputat zuweisen
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
// Typ-Select: einmalig/monatlich auto-setzen
document.getElementById('depTypeSelect').addEventListener('change', function() {
  const opt = this.selectedOptions[0];
  const isRecurring = opt?.dataset.recurring === '1';
  document.getElementById('typeMonthly').checked = isRecurring;
  document.getElementById('typeOnce').checked = !isRecurring;
});
</script>

<?php include TEMPLATE_PATH . '/layouts/footer.php'; ?>
