<?php
/**
 * WvH – Dashboard
 */
require_once __DIR__ . '/config/bootstrap.php';

use Auth\Auth;

Auth::require();

$pageTitle   = 'Dashboard';
$breadcrumbs = [];
$db = getDB();

// ---- Statistiken laden ----
// Offene Vertretungen
$openSubs = (int)$db->query(
    "SELECT COUNT(*) FROM substitutions WHERE status IN ('open','claimed','conflict')"
)->fetchColumn();

// Aktueller Monat
$currentMonth = date('Y-m-01');
$monthLabel   = monthLabel($currentMonth);

// Abrechnungsstatus aktueller Monat
$billingStatus = $db->prepare(
    "SELECT status FROM billing_months WHERE month = ? LIMIT 1"
);
$billingStatus->execute([$currentMonth]);
$bmStatus = $billingStatus->fetchColumn() ?: 'draft';

// Lehrer gesamt (aktiv)
$teacherCount = (int)$db->query(
    "SELECT COUNT(*) FROM teachers t JOIN users u ON u.id=t.user_id WHERE u.is_active=1"
)->fetchColumn();

// Honorarkräfte
$honorarCount = (int)$db->query(
    "SELECT COUNT(*) FROM teachers t JOIN users u ON u.id=t.user_id
     WHERE u.is_active=1 AND t.employment_type='honorar'"
)->fetchColumn();

// Konflikte (Verwaltung)
$conflicts = 0;
if (Auth::isVerwaltung()) {
    $conflicts = (int)$db->query(
        "SELECT COUNT(*) FROM substitutions WHERE status='conflict'"
    )->fetchColumn();
}

// Meine letzten Abrechnungen (für Lehrer)
$myRecords = [];
if (Auth::isLehrer()) {
    $teacher = $db->prepare(
        "SELECT id FROM teachers WHERE user_id = ? LIMIT 1"
    );
    $teacher->execute([Auth::userId()]);
    $teacherId = $teacher->fetchColumn();

    if ($teacherId) {
        $stmt = $db->prepare(
            "SELECT br.*, bm.month, bm.status AS month_status
             FROM billing_records br
             JOIN billing_months bm ON bm.id = br.billing_month_id
             WHERE br.teacher_id = ?
             ORDER BY bm.month DESC
             LIMIT 6"
        );
        $stmt->execute([$teacherId]);
        $myRecords = $stmt->fetchAll();
    }
}

// Offene Vertretungen (die mich betreffen als Lehrer)
$myOpenSubs = [];
if (Auth::isLehrer() && isset($teacherId) && $teacherId) {
    $stmt = $db->prepare(
        "SELECT s.*, li.lesson_date, te.time_start, te.time_end,
                sub.name AS subject_name,
                CONCAT(u.first_name,' ',u.last_name) AS original_name
         FROM substitutions s
         JOIN lesson_instances li ON li.id = s.lesson_id
         JOIN timetable_entries te ON te.id = li.entry_id
         JOIN subjects sub ON sub.id = te.subject_id
         JOIN teachers ot ON ot.id = s.original_teacher_id
         JOIN users u ON u.id = ot.user_id
         WHERE s.status = 'open'
         ORDER BY li.lesson_date ASC
         LIMIT 5"
    );
    $stmt->execute();
    $myOpenSubs = $stmt->fetchAll();
}

include TEMPLATE_PATH . '/layouts/header.php';
?>

<!-- Begrüßung -->
<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <h2 class="fw-bold mb-1" style="color:var(--wvh-primary)">
      Guten <?= date('H') < 12 ? 'Morgen' : (date('H') < 17 ? 'Tag' : 'Abend') ?>,
      <?= e(Auth::userFirstName()) ?>! 👋
    </h2>
    <p class="text-muted mb-0">
      <?= formatDate(date('Y-m-d'), 'l, d. F Y') ?> &middot;
      <span class="badge bg-secondary"><?= e(Auth::roleName()) ?></span>
    </p>
  </div>
  <div class="text-end d-none d-md-block">
    <div class="small text-muted">Aktueller Monat</div>
    <div class="fw-semibold" style="color:var(--wvh-primary)"><?= e($monthLabel) ?></div>
    <?= billingStatusBadge($bmStatus) ?>
  </div>
</div>

<!-- ---- Statistik-Karten ---- -->
<div class="row g-3 mb-4">

  <div class="col-6 col-lg-3">
    <div class="wvh-stat bg-wvh-primary">
      <div class="stat-value"><?= $teacherCount ?></div>
      <div class="stat-label">Aktive Lehrkräfte</div>
      <i class="bi bi-people stat-icon"></i>
    </div>
  </div>

  <div class="col-6 col-lg-3">
    <div class="wvh-stat bg-wvh-warning">
      <div class="stat-value"><?= $openSubs ?></div>
      <div class="stat-label">Offene Vertretungen</div>
      <i class="bi bi-arrow-left-right stat-icon"></i>
    </div>
  </div>

  <?php if (Auth::isVerwaltung()): ?>
  <div class="col-6 col-lg-3">
    <div class="wvh-stat <?= $conflicts > 0 ? 'bg-wvh-danger' : 'bg-wvh-success' ?>">
      <div class="stat-value"><?= $conflicts ?></div>
      <div class="stat-label">Konflikte</div>
      <i class="bi bi-exclamation-triangle stat-icon"></i>
    </div>
  </div>

  <div class="col-6 col-lg-3">
    <div class="wvh-stat bg-wvh-info">
      <div class="stat-value"><?= $honorarCount ?></div>
      <div class="stat-label">Honorarkräfte</div>
      <i class="bi bi-currency-euro stat-icon"></i>
    </div>
  </div>
  <?php endif; ?>

</div>

<!-- ---- Inhalt je Rolle ---- -->
<div class="row g-4">

  <?php if (Auth::isVerwaltung()): ?>
  <!-- Schnellaktionen für Verwaltung -->
  <div class="col-md-4">
    <div class="card wvh-card h-100">
      <div class="card-header">
        <div class="card-icon bg-primary bg-opacity-10 text-primary"><i class="bi bi-lightning-charge-fill"></i></div>
        Schnellaktionen
      </div>
      <div class="card-body d-grid gap-2">
        <a href="javascript:void(0)" class="btn btn-wvh disabled" title="In Entwicklung">
          <i class="bi bi-calculator me-2"></i>Monat abrechnen
        </a>
        <a href="<?= APP_URL ?>/substitutions/index.php" class="btn btn-wvh-outline">
          <i class="bi bi-arrow-left-right me-2"></i>Vertretungen verwalten
        </a>
        <a href="<?= APP_URL ?>/deputate/assignments.php" class="btn btn-wvh-outline">
          <i class="bi bi-award me-2"></i>Sonderdeputate vergeben
        </a>
        <a href="javascript:void(0)" class="btn btn-wvh-outline disabled" title="In Entwicklung">
          <i class="bi bi-send me-2"></i>Wise Export erstellen
        </a>
        <?php if ($conflicts > 0): ?>
        <a href="javascript:void(0)" class="btn btn-danger disabled" title="In Entwicklung">
          <i class="bi bi-exclamation-triangle me-2"></i><?= $conflicts ?> Konflikt<?= $conflicts !== 1 ? 'e' : '' ?> lösen!
        </a>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Monats-Workflow -->
  <div class="col-md-8">
    <div class="card wvh-card h-100">
      <div class="card-header">
        <div class="card-icon bg-success bg-opacity-10 text-success"><i class="bi bi-diagram-3-fill"></i></div>
        Monats-Workflow – <?= e($monthLabel) ?>
      </div>
      <div class="card-body">
        <?php
        $steps = ['draft'=>'Entwurf','closed'=>'Geschlossen','review'=>'In Prüfung','confirmed'=>'Bestätigt','final'=>'Finalisiert','paid'=>'Ausgezahlt'];
        $stepKeys = array_keys($steps);
        $currentIdx = array_search($bmStatus, $stepKeys);
        ?>
        <div class="wvh-workflow-steps mb-3">
          <?php foreach ($steps as $key => $label): ?>
            <?php
              $idx = array_search($key, $stepKeys);
              $cls = $idx < $currentIdx ? 'done' : ($key === $bmStatus ? 'active' : '');
            ?>
            <div class="step <?= $cls ?>">
              <?php if ($idx < $currentIdx): ?><i class="bi bi-check-lg"></i> <?php endif; ?>
              <?= e($label) ?>
            </div>
          <?php endforeach; ?>
        </div>
        <div class="d-flex gap-2 flex-wrap">
          <a href="javascript:void(0)" class="btn btn-sm btn-wvh disabled" title="In Entwicklung">
            <i class="bi bi-arrow-right me-1"></i>Zum Monatsabschluss
          </a>
          <a href="javascript:void(0)" class="btn btn-sm btn-wvh-outline disabled" title="In Entwicklung">
            <i class="bi bi-list-check me-1"></i>Alle Abrechnungen
          </a>
        </div>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <?php if (Auth::isLehrer()): ?>
  <!-- Meine letzten Abrechnungen (Lehrer) -->
  <div class="col-md-6">
    <div class="card wvh-card h-100">
      <div class="card-header">
        <div class="card-icon bg-primary bg-opacity-10 text-primary"><i class="bi bi-receipt"></i></div>
        Meine Abrechnungen
      </div>
      <?php if ($myRecords): ?>
      <div class="table-responsive">
        <table class="wvh-table">
          <thead>
            <tr>
              <th>Monat</th>
              <th>Planst.</th>
              <th>Gesamt</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($myRecords as $rec): ?>
            <tr>
              <td class="fw-medium"><?= e(monthLabel($rec['month'])) ?></td>
              <td><?= formatHours((float)$rec['effective_plan_hours']) ?></td>
              <td class="fw-semibold"><?= formatEuro((float)$rec['gross_total']) ?></td>
              <td><?= billingStatusBadge($rec['status']) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php else: ?>
      <div class="card-body text-center text-muted py-4">
        <i class="bi bi-inbox fs-2 d-block mb-2"></i>
        Noch keine Abrechnungen vorhanden.
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Offene Vertretungen (Lehrer) -->
  <div class="col-md-6">
    <div class="card wvh-card h-100">
      <div class="card-header">
        <div class="card-icon bg-warning bg-opacity-10 text-warning"><i class="bi bi-arrow-left-right"></i></div>
        Offene Vertretungen
        <?php if ($openSubs > 0): ?>
          <span class="badge bg-warning ms-auto"><?= $openSubs ?></span>
        <?php endif; ?>
      </div>
      <?php if ($myOpenSubs): ?>
      <div class="table-responsive">
        <table class="wvh-table">
          <thead>
            <tr><th>Datum</th><th>Zeit</th><th>Fach</th><th></th></tr>
          </thead>
          <tbody>
            <?php foreach ($myOpenSubs as $sub): ?>
            <tr>
              <td><?= formatDate($sub['lesson_date']) ?></td>
              <td class="text-muted small"><?= e(substr($sub['time_start'],0,5)) ?>–<?= e(substr($sub['time_end'],0,5)) ?></td>
              <td><?= e($sub['subject_name']) ?></td>
              <td>
                <a href="<?= APP_URL ?>/substitutions/open.php" class="btn btn-xs btn-sm btn-wvh py-0 px-2">
                  Übernehmen
                </a>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php else: ?>
      <div class="card-body text-center text-muted py-4">
        <i class="bi bi-check-circle fs-2 d-block mb-2 text-success"></i>
        Keine offenen Vertretungen.
      </div>
      <?php endif; ?>
      <div class="card-footer bg-transparent border-0">
        <a href="javascript:void(0)" class="btn btn-sm btn-wvh-outline w-100 disabled" title="In Entwicklung">
          Alle Vertretungen anzeigen
        </a>
      </div>
    </div>
  </div>
  <?php endif; ?>

</div>

<?php
// ── Dashboard Stundenplan-Widget: Klasse 1a ────────────────
// Wird am Ende des Dashboard eingefügt (vor footer include)

// Aktuellen Plan finden
$today_d = date('Y-m-d');
$dashPlan = $db->query(
    "SELECT id, name FROM timetable_plans
     WHERE valid_from <= CURDATE() AND valid_until >= CURDATE()
     LIMIT 1"
)->fetch();

// Klasse 1a finden
$dashClass = null;
if ($dashPlan) {
    $s = $db->prepare(
        "SELECT c.id, c.name FROM classes c
         JOIN timetable_entry_classes tec ON tec.class_id = c.id
         JOIN timetable_entries te ON te.id = tec.entry_id
         WHERE te.plan_id = ? AND LOWER(c.name) = '1a'
         LIMIT 1"
    );
    $s->execute([$dashPlan['id']]);
    $dashClass = $s->fetch();
    // Falls 1a nicht da: erste Klasse nehmen
    if (!$dashClass) {
        $s = $db->prepare(
            "SELECT DISTINCT c.id, c.name FROM classes c
             JOIN timetable_entry_classes tec ON tec.class_id = c.id
             JOIN timetable_entries te ON te.id = tec.entry_id
             WHERE te.plan_id = ?
             ORDER BY c.name LIMIT 1"
        );
        $s->execute([$dashPlan['id']]);
        $dashClass = $s->fetch();
    }
}

// Stundenplan-Einträge für die Klasse laden
$dashEntries = [];
if ($dashPlan && $dashClass) {
    $s = $db->prepare(
        "SELECT te.weekday, te.time_start, te.time_end,
                sub.name AS subject_name,
                CONCAT(u.first_name,' ',u.last_name) AS teacher_name
         FROM timetable_entries te
         JOIN teachers t   ON t.id    = te.teacher_id
         JOIN users u      ON u.id    = t.user_id
         JOIN subjects sub ON sub.id  = te.subject_id
         JOIN timetable_entry_classes tec ON tec.entry_id = te.id
         WHERE te.plan_id = ? AND tec.class_id = ?
         ORDER BY te.weekday, te.time_start"
    );
    $s->execute([$dashPlan['id'], $dashClass['id']]);
    $dashEntries = $s->fetchAll();
}

// Ferien heute?
$dashHoliday = null;
try {
    $s = $db->prepare(
        "SELECT name FROM school_holidays
         WHERE is_school_free=1 AND date_from<=? AND date_until>=? LIMIT 1"
    );
    $s->execute([$today_d, $today_d]);
    $dashHoliday = $s->fetchColumn() ?: null;
} catch (\Exception $ex) {}

// Palette für Fächer
$dashPalette = ['#2d6fa4','#16a34a','#7c3aed','#ea580c','#0891b2','#db2777','#d97706'];
$dashColMap  = []; $dci = 0;
foreach ($dashEntries as $e) {
    if (!isset($dashColMap[$e['subject_name']])) {
        $dashColMap[$e['subject_name']] = $dashPalette[$dci++ % count($dashPalette)];
    }
}

// Heutiger Wochentag
$todayDow = (int)date('N');
$isSchoolDay = $todayDow >= 1 && $todayDow <= 5;
$dashDayNames = ['','Mo','Di','Mi','Do','Fr'];
?>

<?php if ($dashPlan && $dashClass && !empty($dashEntries)): ?>
<div class="row mt-4">
  <div class="col-12">
    <div class="card wvh-card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-2">
          <div class="card-icon bg-primary bg-opacity-10 text-primary">
            <i class="bi bi-grid-3x3-gap"></i>
          </div>
          <span>
            Stundenplan
            <strong>Klasse <?= e($dashClass['name']) ?></strong>
          </span>
          <?php if ($dashHoliday): ?>
            <span class="badge bg-warning text-dark">
              <i class="bi bi-sun me-1"></i><?= e($dashHoliday) ?>
            </span>
          <?php endif; ?>
        </div>
        <a href="<?= APP_URL ?>/timetable/view.php?mode=klasse&class_id=<?= $dashClass['id'] ?>"
           class="btn btn-sm btn-outline-secondary">
          <i class="bi bi-arrows-fullscreen me-1"></i>Vollansicht
        </a>
      </div>

      <div class="table-responsive">
        <table class="table table-sm mb-0" style="min-width:500px">
          <thead style="background:#f8fafc">
            <tr>
              <th class="text-muted small border-0 py-2 ps-3" style="width:100px">Zeit</th>
              <?php for ($d=1; $d<=5; $d++): ?>
              <th class="border-0 py-2 small fw-semibold"
                  style="color:<?= ($isSchoolDay&&$d===$todayDow&&!$dashHoliday)?'var(--wvh-primary)':'#374151' ?>">
                <?= $dashDayNames[$d] ?>
                <?php if ($isSchoolDay&&$d===$todayDow&&!$dashHoliday): ?>
                  <span class="badge bg-primary" style="font-size:.5rem">Heute</span>
                <?php endif; ?>
              </th>
              <?php endfor; ?>
            </tr>
          </thead>
          <tbody>
            <?php
            // Zeitslots sammeln
            $dashSlots = [];
            foreach ($dashEntries as $e) {
                $k = $e['time_start'];
                if (!isset($dashSlots[$k])) $dashSlots[$k] = $e;
            }
            ksort($dashSlots);
            foreach ($dashSlots as $slot):
            ?>
            <tr style="border-top:1px solid #f0f0f0">
              <td class="ps-3 text-muted small" style="font-size:.7rem;white-space:nowrap">
                <?= substr($slot['time_start'],0,5) ?><br>
                <span style="font-size:.63rem">–<?= substr($slot['time_end'],0,5) ?></span>
              </td>
              <?php for ($d=1; $d<=5; $d++):
                $cell = null;
                foreach ($dashEntries as $e) {
                    if ($e['weekday']==$d && $e['time_start']===$slot['time_start']) {
                        $cell = $e; break;
                    }
                }
                $isToday = $isSchoolDay && $d===$todayDow && !$dashHoliday;
              ?>
              <td class="p-1" style="<?= $isToday?'background:#f0f7ff':'' ?>">
                <?php if ($cell): $col = $dashColMap[$cell['subject_name']] ?? '#2d6fa4'; ?>
                <div style="background:<?= $col ?>15;border-left:2px solid <?= $col ?>;
                            padding:3px 5px;border-radius:4px;font-size:.68rem;line-height:1.3">
                  <div class="fw-semibold text-truncate" style="color:<?= $col ?>">
                    <?= e($cell['subject_name']) ?>
                  </div>
                  <div class="text-truncate text-muted" style="font-size:.63rem">
                    <?= e($cell['teacher_name']) ?>
                  </div>
                </div>
                <?php endif; ?>
              </td>
              <?php endfor; ?>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div><!-- /table-responsive -->
    </div>
  </div>
</div>
<?php endif; /* dashPlan */ ?>

<?php include TEMPLATE_PATH . '/layouts/footer.php'; ?>
