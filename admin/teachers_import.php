<?php
/**
 * WvH – Lehrer CSV-Import
 *
 * Unterstützte CSV-Spalten:
 *   Vorname | Nachname | Username (= Schul-E-Mail, Identifier) |
 *   Notfalltelefonnummer | ArtDerMitarbeit (Honorarkraft | SBOS = fest) |
 *   recipientId fuer Wise | name fuer Wise | recipientDetail fuer Wise |
 *   receiverType fuer Wise
 *
 *   Optional: Stundensatz | aktiv_ab
 *
 * Logik:
 *   - Identifier = E-Mail → Upsert
 *   - SBOS = festangestellt, Honorarkraft = honorar
 *   - Stundensatz-Änderung wird in teacher_rates historisiert
 *   - Wise-Name wird SEPARAT gespeichert (kann Ehename, GmbH, Künstlername sein!)
 */
require_once __DIR__ . '/../config/bootstrap.php';

use Auth\Auth;

Auth::requireRole(ROLE_ADMIN, ROLE_VERWALTUNG);

$pageTitle   = 'Lehrer CSV-Import';
$breadcrumbs = ['Administration' => APP_URL.'/admin/users.php', 'Lehrer importieren' => null];
$db          = getDB();
$errors      = [];
$step        = (int)($_POST['step'] ?? 1);

/* ================================================================
   SCHRITT 1 → 2: Datei parsen, Vorschau aufbauen
   ================================================================ */
if ($step === 1 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();

    $fileOk = isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK;
    if (!$fileOk)
        $errors[] = 'Bitte eine CSV-Datei hochladen.';
    elseif (strtolower(pathinfo($_FILES['csv_file']['name'], PATHINFO_EXTENSION)) !== 'csv')
        $errors[] = 'Nur .csv-Dateien erlaubt.';

    if (empty($errors)) {
        $defaultPw   = trim($_POST['default_password']   ?? 'WvH@Start2026!');
        $defaultFrom = trim($_POST['active_from_default'] ?? date('Y-m-d'));
        $defaultRate = (float)str_replace(',', '.', trim($_POST['default_rate'] ?? '0'));

        [$preview, $parseErrors] = parseTeachersCsv(
            $_FILES['csv_file']['tmp_name'], $defaultFrom, $defaultRate, $db
        );
        $errors = $parseErrors;

        if (empty($errors) && empty($preview))
            $errors[] = 'Keine gültigen Zeilen gefunden – E-Mail-Adressen prüfen.';

        if (empty($errors)) {
            $_SESSION['ti'] = ['preview' => $preview, 'pw' => $defaultPw];
            $step = 2;
        }
    }
}

/* ================================================================
   SCHRITT 2 → Import ausführen
   ================================================================ */
if ($step === 2 && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['do_import'])) {
    requireCsrf();
    $sess = $_SESSION['ti'] ?? null;
    if (!$sess) { setFlash('error', 'Sitzung abgelaufen.'); redirect('admin/teachers_import.php'); }

    $result = doImport($sess['preview'], $_POST['sel'] ?? [], $sess['pw'], $db);
    unset($_SESSION['ti']);
    $_SESSION['ti_done'] = $result;

    setFlash('success', "{$result['created']} angelegt · {$result['updated']} aktualisiert · {$result['skipped']} übersprungen");
    redirect('admin/teachers_import.php?done=1');
}

/* Nach Redirect */
$done = null;
if (isset($_GET['done'], $_SESSION['ti_done'])) {
    $done = $_SESSION['ti_done'];
    unset($_SESSION['ti_done']);
    $step = 99;
}

$preview = $_SESSION['ti']['preview'] ?? [];

include TEMPLATE_PATH . '/layouts/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
  <h3 class="fw-bold mb-0" style="color:var(--wvh-primary)">
    <i class="bi bi-person-plus-fill me-2"></i>Lehrer CSV-Import
  </h3>
  <a href="<?= APP_URL ?>/admin/users.php" class="btn btn-sm btn-outline-secondary">
    <i class="bi bi-arrow-left me-1"></i>Benutzerverwaltung
  </a>
</div>

<!-- Wizard-Progress -->
<div class="card wvh-card mb-4">
  <div class="card-body py-3 px-4">
    <div class="d-flex align-items-center">
      <?php foreach (['CSV hochladen', 'Vorschau & Import', 'Fertig'] as $i => $lbl):
        $n = $i + 1;
        $isDone = ($step === 99 ? true  : $n < $step);
        $isAct  = ($step === 99 ? $n === 3 : $n === $step);
      ?>
      <div class="d-flex align-items-center <?= $i > 0 ? 'flex-grow-1' : '' ?>">
        <?php if ($i > 0): ?>
          <div class="flex-grow-1 mx-2" style="height:2px;background:<?= $isDone ? 'var(--wvh-secondary)' : '#dee2e6' ?>"></div>
        <?php endif; ?>
        <div class="d-flex flex-column align-items-center" style="min-width:110px">
          <div class="rounded-circle d-flex align-items-center justify-content-center fw-bold"
               style="width:38px;height:38px;font-size:.9rem;
                      background:<?= $isDone ? 'var(--wvh-secondary)' : ($isAct ? 'var(--wvh-primary)' : '#dee2e6') ?>;
                      color:<?= ($isDone || $isAct) ? '#fff' : '#999' ?>">
            <?= $isDone ? '<i class="bi bi-check-lg"></i>' : $n ?>
          </div>
          <small class="mt-1 text-nowrap <?= $isAct ? 'fw-semibold' : 'text-muted' ?>" style="font-size:.73rem">
            <?= e($lbl) ?>
          </small>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<?php if ($errors): ?>
<div class="alert alert-danger">
  <i class="bi bi-exclamation-triangle me-2"></i>
  <ul class="mb-0 mt-1"><?php foreach ($errors as $e): ?><li><?= e($e) ?></li><?php endforeach; ?></ul>
</div>
<?php endif; ?>


<!-- ================================================================
     SCHRITT 1: Upload + Optionen
     ================================================================ -->
<?php if ($step === 1): ?>

<div class="row g-4">
  <div class="col-lg-7">
    <div class="card wvh-card">
      <div class="card-header">
        <div class="card-icon bg-primary bg-opacity-10 text-primary"><i class="bi bi-cloud-upload"></i></div>
        Lehrerliste hochladen
      </div>
      <div class="card-body">
        <form method="POST" enctype="multipart/form-data">
          <?= csrfField() ?><input type="hidden" name="step" value="1">

          <!-- Drop-Zone -->
          <label class="form-label fw-semibold">CSV-Datei *</label>
          <div id="dz" class="rounded-3 p-4 text-center mb-4" style="border:2px dashed #adb5bd">
            <i class="bi bi-person-vcard fs-2 text-primary d-block mb-2"></i>
            <p class="text-muted small mb-2">CSV hier ablegen oder klicken zum Auswählen</p>
            <label class="btn btn-sm btn-wvh-outline cursor-pointer">
              Datei wählen
              <input type="file" name="csv_file" id="csvIn" accept=".csv" class="d-none" required>
            </label>
            <p class="small text-muted mt-2 mb-0" id="csvName">Keine Datei gewählt</p>
          </div>

          <hr>

          <div class="row g-3 mb-4">
            <div class="col-md-6">
              <label class="form-label fw-semibold">
                <i class="bi bi-key text-warning me-1"></i>Standard-Passwort (neue Lehrer)
              </label>
              <input type="text" name="default_password" class="form-control" value="WvH@Start2026!" required>
              <div class="form-text">Muss beim ersten Login geändert werden.</div>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">
                <i class="bi bi-calendar-check text-success me-1"></i>Standard „Aktiv ab"
              </label>
              <input type="date" name="active_from_default" class="form-control" value="<?= date('Y-m-d') ?>">
              <div class="form-text">Fallback falls Spalte fehlt/leer.</div>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">
                <i class="bi bi-currency-euro text-info me-1"></i>Standard-Stundensatz (€/UE)
              </label>
              <input type="number" name="default_rate" class="form-control" value="0" min="0" step="0.50"
                     placeholder="0">
              <div class="form-text">Gilt wenn CSV keine Stundensatz-Spalte hat.</div>
            </div>
          </div>

          <div class="d-flex justify-content-end">
            <button type="submit" class="btn btn-wvh px-4">
              CSV analysieren <i class="bi bi-arrow-right ms-2"></i>
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <div class="col-lg-5">
    <div class="card wvh-card mb-3">
      <div class="card-header">
        <div class="card-icon bg-info bg-opacity-10 text-info"><i class="bi bi-table"></i></div>
        Erwartete CSV-Spalten
      </div>
      <div class="card-body p-0">
        <table class="table table-sm small mb-0">
          <thead><tr><th class="ps-3">Spalte</th><th>Inhalt</th><th class="text-center pe-3">✓</th></tr></thead>
          <tbody>
            <tr><td class="ps-3 fw-medium">Vorname</td><td>Vorname</td><td class="text-center pe-3 text-danger">Pflicht</td></tr>
            <tr><td class="ps-3 fw-medium">Nachname</td><td>Nachname</td><td class="text-center pe-3 text-danger">Pflicht</td></tr>
            <tr><td class="ps-3 fw-medium">Username</td><td>Schul-E-Mail = Identifier</td><td class="text-center pe-3 text-danger">Pflicht</td></tr>
            <tr class="table-light"><td class="ps-3 fw-medium">ArtDerMitarbeit</td><td>„Honorarkraft" oder „SBOS" (= fest)</td><td class="text-center pe-3 text-muted">opt.</td></tr>
            <tr class="table-light"><td class="ps-3 fw-medium">Notfalltelefonnummer</td><td>Handynummer</td><td class="text-center pe-3 text-muted">opt.</td></tr>
            <tr class="table-light"><td class="ps-3 fw-medium">recipientId fuer Wise</td><td>Wise Recipient UUID</td><td class="text-center pe-3 text-muted">opt.</td></tr>
            <tr class="table-light"><td class="ps-3 fw-medium">name fuer Wise</td><td>Kontoinhaber-Name in Wise<br><span class="text-warning fw-medium">⚠ kann abweichen!</span></td><td class="text-center pe-3 text-muted">opt.</td></tr>
            <tr class="table-light"><td class="ps-3 fw-medium">recipientDetail fuer Wise</td><td>„Sparkasse ending 8770"</td><td class="text-center pe-3 text-muted">opt.</td></tr>
            <tr class="table-light"><td class="ps-3 fw-medium">receiverType fuer Wise</td><td>PERSON oder INSTITUTION</td><td class="text-center pe-3 text-muted">opt.</td></tr>
            <tr class="table-light"><td class="ps-3 fw-medium">Stundensatz</td><td>z.B. 25,50 oder 25.50</td><td class="text-center pe-3 text-muted">opt.</td></tr>
            <tr class="table-light"><td class="ps-3 fw-medium">aktiv_ab</td><td>YYYY-MM-DD oder DD.MM.YYYY</td><td class="text-center pe-3 text-muted">opt.</td></tr>
          </tbody>
        </table>
      </div>
    </div>

    <div class="card wvh-card">
      <div class="card-body small py-3">
        <div class="d-flex gap-2 mb-2 align-items-start">
          <i class="bi bi-arrow-repeat text-success fs-5 flex-shrink-0"></i>
          <span><strong>Upsert:</strong> Identifier ist die E-Mail. Existiert sie bereits → Update. Sonst → Neu anlegen.</span>
        </div>
        <div class="d-flex gap-2 mb-2 align-items-start">
          <i class="bi bi-exclamation-triangle-fill text-warning fs-5 flex-shrink-0"></i>
          <span><strong>Wise-Name</strong> wird separat gespeichert – er ist der tatsächliche Kontoinhaber bei Wise (Ehepartner, GmbH, Künstlername). Kritisch für korrekte Überweisung!</span>
        </div>
        <div class="d-flex gap-2 align-items-start">
          <i class="bi bi-clock-history text-info fs-5 flex-shrink-0"></i>
          <span><strong>Stundensatz:</strong> Änderungen werden automatisch in der Rate-Historie gespeichert.</span>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
const csvIn = document.getElementById('csvIn');
const dz    = document.getElementById('dz');
csvIn.addEventListener('change', () => {
  document.getElementById('csvName').textContent = csvIn.files[0]?.name || '';
  dz.style.borderColor = 'var(--wvh-secondary)';
});
dz.addEventListener('dragover',  e => { e.preventDefault(); dz.style.background = '#f0f8ff'; });
dz.addEventListener('dragleave', () => { dz.style.background = ''; });
dz.addEventListener('drop', e => {
  e.preventDefault(); dz.style.background = '';
  const dt = new DataTransfer(); dt.items.add(e.dataTransfer.files[0]);
  csvIn.files = dt.files;
  document.getElementById('csvName').textContent = csvIn.files[0]?.name || '';
  dz.style.borderColor = 'var(--wvh-secondary)';
});
</script>

<?php endif; // Schritt 1 ?>


<!-- ================================================================
     SCHRITT 2: Vorschau
     ================================================================ -->
<?php if ($step === 2 && !empty($preview)):
  $cntNew    = count(array_filter($preview, fn($r) => $r['action'] === 'create'));
  $cntUpd    = count(array_filter($preview, fn($r) => $r['action'] === 'update'));
  $cntWise   = count(array_filter($preview, fn($r) => !empty($r['wise_id'])));
  $cntInst   = count(array_filter($preview, fn($r) => ($r['wise_type'] ?? '') === 'INSTITUTION'));
  $cntNoWise = count(array_filter($preview, fn($r) => empty($r['wise_id'])));
?>

<form method="POST">
  <?= csrfField() ?><input type="hidden" name="step" value="2"><input type="hidden" name="do_import" value="1">

  <!-- Kopf-Stats -->
  <div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
    <span class="badge bg-success px-3 py-2 fs-6"><?= $cntNew ?> neu</span>
    <span class="badge bg-warning text-dark px-3 py-2 fs-6"><?= $cntUpd ?> Update</span>
    <span class="badge bg-primary px-3 py-2 fs-6"><?= $cntWise ?> mit Wise-Daten</span>
    <?php if ($cntInst): ?><span class="badge bg-dark px-3 py-2"><?= $cntInst ?> INSTITUTION</span><?php endif; ?>
    <?php if ($cntNoWise): ?><span class="badge bg-secondary px-3 py-2"><?= $cntNoWise ?> ohne Wise</span><?php endif; ?>
    <div class="ms-auto d-flex gap-2">
      <a href="<?= APP_URL ?>/admin/teachers_import.php" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Zurück
      </a>
      <button type="submit" class="btn btn-success fw-semibold px-4">
        <i class="bi bi-person-check me-2"></i>Jetzt importieren
      </button>
    </div>
  </div>

  <!-- Wise-Hinweis -->
  <div class="alert alert-warning small d-flex gap-2 mb-3">
    <i class="bi bi-exclamation-triangle-fill text-warning mt-1 flex-shrink-0"></i>
    <div>
      <strong>Wise-Namen prüfen:</strong> Zeilen mit
      <i class="bi bi-exclamation-circle text-warning"></i> haben einen abweichenden Wise-Namen.
      Das ist <em>gewollt</em>, wenn der Lehrer über Ehepartnerkonto, eigene GmbH oder mit Künstlernamen registriert ist.
      Bitte einmalig verifizieren.
    </div>
  </div>

  <div class="card wvh-card">
    <div class="card-header">
      <div class="card-icon bg-warning bg-opacity-10 text-warning"><i class="bi bi-eye-fill"></i></div>
      Vorschau – einzelne Zeilen abwählen um sie zu überspringen
      <label class="ms-auto d-flex align-items-center gap-2 small mb-0 cursor-pointer">
        <input type="checkbox" id="selAll" class="form-check-input" checked> Alle
      </label>
    </div>
    <div class="table-responsive">
      <table class="wvh-table" style="font-size:.8rem">
        <thead>
          <tr>
            <th style="width:36px"></th>
            <th>Name</th>
            <th>E-Mail</th>
            <th>Typ</th>
            <th>Telefon</th>
            <th>Satz</th>
            <th>Wise-ID</th>
            <th>
              Wise-Name
              <i class="bi bi-info-circle text-muted" data-bs-toggle="tooltip"
                 title="Kontoinhaber wie in Wise hinterlegt – kann vom echten Namen abweichen"></i>
            </th>
            <th>Bank (Wise)</th>
            <th>Aktiv ab</th>
            <th>Aktion</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($preview as $r):
            $wiseNameDiffers = !empty($r['wise_name']) &&
                mb_strtolower(trim($r['wise_name'])) !== mb_strtolower($r['first_name'].' '.$r['last_name']);
          ?>
          <tr class="<?= $r['action'] === 'update' ? 'bg-warning bg-opacity-10' : '' ?>">
            <td class="text-center">
              <input type="checkbox" name="sel[<?= e($r['email']) ?>]" value="1"
                     class="form-check-input row-cb" checked>
            </td>
            <td>
              <div class="fw-semibold"><?= e($r['first_name'].' '.$r['last_name']) ?></div>
              <?php if ($r['action']==='update' && $r['existing_name']): ?>
                <div class="text-muted" style="font-size:.68rem">bisher: <?= e($r['existing_name']) ?></div>
              <?php endif; ?>
            </td>
            <td style="white-space:nowrap" class="text-muted"><?= e($r['email']) ?></td>
            <td>
              <span class="badge <?= $r['emp_type']==='festangestellt' ? 'bg-primary' : 'bg-secondary' ?>">
                <?= $r['emp_type']==='festangestellt' ? 'Fest' : 'Honorar' ?>
              </span>
            </td>
            <td class="text-muted" style="white-space:nowrap;font-size:.72rem"><?= e($r['phone'] ?? '–') ?></td>
            <td><?= $r['rate'] > 0 ? formatEuro($r['rate']) : '<span class="text-muted">–</span>' ?></td>
            <td>
              <?php if (!empty($r['wise_id'])): ?>
                <code class="small text-success" style="font-size:.65rem"><?= e(substr($r['wise_id'],0,8)) ?>…</code>
              <?php else: ?>
                <span class="text-muted">–</span>
              <?php endif; ?>
            </td>
            <td>
              <?php if (!empty($r['wise_name'])): ?>
                <span class="<?= $wiseNameDiffers ? 'text-warning fw-semibold' : '' ?>">
                  <?= e($r['wise_name']) ?>
                </span>
                <?php if ($r['wise_type']==='INSTITUTION'): ?>
                  <span class="badge bg-dark ms-1" style="font-size:.6rem">ORG</span>
                <?php elseif ($wiseNameDiffers): ?>
                  <i class="bi bi-exclamation-circle text-warning ms-1"
                     data-bs-toggle="tooltip" title="Weicht vom echten Namen ab – bitte prüfen"></i>
                <?php endif; ?>
              <?php else: ?>
                <span class="text-muted">–</span>
              <?php endif; ?>
            </td>
            <td class="text-muted" style="font-size:.72rem"><?= e($r['wise_detail'] ?? '–') ?></td>
            <td class="text-muted small"><?= e($r['active_from']) ?></td>
            <td>
              <?php if ($r['action']==='create'): ?>
                <span class="badge bg-success"><i class="bi bi-plus me-1"></i>Neu</span>
              <?php else: ?>
                <span class="badge bg-warning text-dark"><i class="bi bi-pencil me-1"></i>Update</span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div class="card-footer d-flex justify-content-between">
      <a href="<?= APP_URL ?>/admin/teachers_import.php" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Zurück
      </a>
      <button type="submit" class="btn btn-success fw-semibold px-4">
        <i class="bi bi-person-check me-2"></i>Import bestätigen
      </button>
    </div>
  </div>
</form>

<script>
document.getElementById('selAll').addEventListener('change', function() {
  document.querySelectorAll('.row-cb').forEach(cb => cb.checked = this.checked);
});
// Tooltips aktivieren
document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el =>
  new bootstrap.Tooltip(el)
);
</script>

<?php endif; // Schritt 2 ?>


<!-- ================================================================
     SCHRITT 3: Ergebnis
     ================================================================ -->
<?php if ($step === 99 && $done): ?>

<div class="row justify-content-center">
  <div class="col-lg-8">
    <div class="card wvh-card">
      <div class="card-header" style="background:linear-gradient(135deg,#d1fae5,#a7f3d0);color:#065f46">
        <div class="card-icon" style="background:#10b981;color:#fff"><i class="bi bi-check2-all"></i></div>
        Import abgeschlossen
      </div>
      <div class="card-body">
        <!-- Zähler -->
        <div class="row g-3 mb-4 text-center">
          <div class="col-4">
            <div class="rounded-3 p-3" style="background:#d1fae5">
              <div class="fw-bold fs-2 text-success"><?= $done['created'] ?></div>
              <div class="small text-muted">Neu angelegt</div>
            </div>
          </div>
          <div class="col-4">
            <div class="rounded-3 p-3" style="background:#fef3c7">
              <div class="fw-bold fs-2 text-warning-emphasis"><?= $done['updated'] ?></div>
              <div class="small text-muted">Aktualisiert</div>
            </div>
          </div>
          <div class="col-4">
            <div class="rounded-3 p-3" style="background:#f3f4f6">
              <div class="fw-bold fs-2 text-secondary"><?= $done['skipped'] ?></div>
              <div class="small text-muted">Übersprungen/Fehler</div>
            </div>
          </div>
        </div>

        <!-- Wise-Spalten fehlen noch? -->
        <?php if (!empty($done['wise_missing'])): ?>
        <div class="alert alert-warning d-flex gap-2 mb-3">
          <i class="bi bi-exclamation-triangle-fill text-warning mt-1 flex-shrink-0"></i>
          <div class="small">
            <strong>Wise-Daten noch nicht gespeichert!</strong><br>
            Die Wise-Spalten fehlen noch in der Datenbank. Bitte jetzt den SQL-Patch
            <code>PATCH_wise_felder_v2.sql</code> in phpMyAdmin ausführen und dann
            den Import erneut starten – alle Lehrer werden dann auf „Update" erkannt
            und die Wise-Felder werden nachgetragen.
          </div>
        </div>
        <?php endif; ?>

        <!-- Detail-Tabelle -->
        <div style="max-height:420px;overflow-y:auto">
          <table class="table table-sm small">
            <thead class="sticky-top bg-white border-bottom">
              <tr>
                <th>Name</th>
                <th>E-Mail</th>
                <th class="text-center">Wise</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($done['rows'] as $r): ?>
              <tr class="<?= ($r['status']==='error') ? 'table-danger' : '' ?>">
                <td class="fw-medium"><?= e($r['name']) ?></td>
                <td class="text-muted"><?= e($r['email']) ?></td>
                <td class="text-center">
                  <?php if (!empty($r['has_wise'])): ?>
                    <i class="bi bi-check-circle-fill text-success" title="Wise-Daten gespeichert"></i>
                  <?php elseif (!empty($r['wise_pending'])): ?>
                    <i class="bi bi-clock text-warning" title="Wise-Spalten fehlen – bitte Patch ausführen"></i>
                  <?php else: ?>
                    <span class="text-muted">–</span>
                  <?php endif; ?>
                </td>
                <td>
                  <?php if ($r['status']==='created'): ?>
                    <span class="badge bg-success">Angelegt</span>
                  <?php elseif ($r['status']==='updated'): ?>
                    <span class="badge bg-warning text-dark">Aktualisiert</span>
                  <?php elseif ($r['status']==='skipped'): ?>
                    <span class="badge bg-secondary">Übersprungen</span>
                  <?php else: ?>
                    <span class="badge bg-danger">Fehler</span>
                    <?php if (!empty($r['error'])): ?>
                      <div class="text-danger mt-1" style="font-size:.68rem"><?= e($r['error']) ?></div>
                    <?php endif; ?>
                  <?php endif; ?>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <div class="d-flex gap-2 justify-content-end mt-3">
          <a href="<?= APP_URL ?>/admin/teachers_import.php" class="btn btn-outline-secondary">
            <i class="bi bi-upload me-1"></i>Erneut importieren
          </a>
          <a href="<?= APP_URL ?>/admin/users.php" class="btn btn-wvh">
            <i class="bi bi-people me-1"></i>Benutzerverwaltung
          </a>
        </div>
      </div>
    </div>
  </div>
</div>

<?php endif; // Schritt 3 ?>

<?php include TEMPLATE_PATH . '/layouts/footer.php'; ?>


<?php
/* ================================================================
   PARSE-FUNKTION
   ================================================================ */
function parseTeachersCsv(string $filePath, string $defaultFrom, float $defaultRate, \PDO $db): array
{
    $raw = file_get_contents($filePath);

    // Encoding: BOM-UTF8 → direkt; sonst Latin-1 → UTF-8
    if (substr($raw, 0, 3) === "\xEF\xBB\xBF") {
        $raw = substr($raw, 3);
    } else {
        $converted = mb_convert_encoding($raw, 'UTF-8', 'ISO-8859-1');
        if ($converted) $raw = $converted;
    }

    $lines = array_values(array_filter(
        explode("\n", str_replace("\r\n", "\n", $raw)),
        fn($l) => trim($l) !== ''
    ));

    if (count($lines) < 2) return [[], ['CSV leer oder kein Header.']];

    // Trennzeichen auto-erkennen
    $delim = substr_count($lines[0], ';') >= substr_count($lines[0], ',') ? ';' : ',';

    // Header normalisieren
    $hdr = array_map(
        fn($h) => mb_strtolower(trim($h, " \"\r\n\t")),
        str_getcsv($lines[0], $delim, '"')
    );

    // Spalten-Index finden
    $col = function(array $candidates) use ($hdr): ?int {
        foreach ($candidates as $c) {
            $idx = array_search(mb_strtolower($c), $hdr);
            if ($idx !== false) return (int)$idx;
        }
        return null;
    };

    // Pflicht
    $cFirst = $col(['vorname','first_name','firstname']);
    $cLast  = $col(['nachname','last_name','lastname','name']);
    $cEmail = $col(['username','email','e-mail','mail','benutzername']);
    // Optional
    $cTyp    = $col(['artdermitarbeit','typ','type','art','employment_type']);
    $cPhone  = $col(['notfalltelefonnummer','telefon','phone','mobile','handy']);
    $cWiseId = $col(['recipientid fuer wise','wise_recipient_id','recipientid','wise_id']);
    $cWiseN  = $col(['name fuer wise','wise_recipient_name','wise_name']);
    $cWiseD  = $col(['recipientdetail fuer wise','wise_recipient_detail','recipientdetail']);
    $cWiseT  = $col(['receivertype fuer wise','wise_receiver_type','receivertype']);
    $cRate   = $col(['stundensatz','hourly_rate','rate','satz']);
    $cFrom   = $col(['aktiv_ab','active_from','eintrittsdatum','von','start']);

    $errs = [];
    if ($cFirst === null) $errs[] = 'Spalte „Vorname" fehlt.';
    if ($cLast  === null) $errs[] = 'Spalte „Nachname" fehlt.';
    if ($cEmail === null) $errs[] = 'Spalte „Username" / „Email" fehlt.';
    if ($errs) return [[], $errs];

    $get = fn(array $cols, ?int $i, string $def = '') =>
        ($i !== null && isset($cols[$i])) ? trim($cols[$i], " \"\r\n\t") : $def;

    $rows = [];

    foreach (array_slice($lines, 1) as $line) {
        $cols  = str_getcsv($line, $delim, '"');
        $email = mb_strtolower($get($cols, $cEmail));
        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) continue;

        $first = trim($get($cols, $cFirst));
        $last  = trim($get($cols, $cLast));
        if (!$first || !$last) continue;

        // Beschäftigungstyp: SBOS = festangestellt
        $typRaw  = mb_strtolower($get($cols, $cTyp));
        $empType = ((strpos($typRaw, 'sbos') !== false) || (strpos($typRaw, 'fest') !== false))
                   ? 'festangestellt' : 'honorar';

        // Stundensatz
        $rateStr = $get($cols, $cRate);
        $rate    = $rateStr !== '' ? (float)str_replace([',',' ','€'], ['.','',''], $rateStr) : $defaultRate;

        // Aktiv ab – DD.MM.YYYY → YYYY-MM-DD
        $fromStr = $get($cols, $cFrom) ?: $defaultFrom;
        if (preg_match('/^(\d{2})\.(\d{2})\.(\d{4})$/', $fromStr, $m)) {
            $fromStr = "{$m[3]}-{$m[2]}-{$m[1]}";
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromStr)) $fromStr = $defaultFrom;

        // Telefon bereinigen
        $phone = $get($cols, $cPhone) ?: null;
        if ($phone && in_array($phone, ['-', '–', 'n/a', 'keine'], true)) $phone = null;

        // Wise-Felder
        $wiseId   = $get($cols, $cWiseId) ?: null;
        $wiseName = $get($cols, $cWiseN)  ?: null;
        $wiseDetail = $get($cols, $cWiseD) ?: null;
        $wiseType = strtoupper($get($cols, $cWiseT, 'PERSON'));
        if (!in_array($wiseType, ['PERSON','INSTITUTION'])) $wiseType = 'PERSON';
        // Wenn kein Wise-Name vorhanden → kein Type
        if (!$wiseId) { $wiseName = $wiseDetail = null; $wiseType = null; }

        // Existiert bereits?
        $s = $db->prepare("SELECT u.id, u.first_name, u.last_name FROM users u WHERE u.email = ?");
        $s->execute([$email]);
        $ex = $s->fetch();

        $rows[] = [
            'first_name'   => $first,
            'last_name'    => $last,
            'email'        => $email,
            'emp_type'     => $empType,
            'rate'         => $rate,
            'active_from'  => $fromStr,
            'phone'        => $phone,
            'wise_id'      => $wiseId,
            'wise_name'    => $wiseName,
            'wise_detail'  => $wiseDetail,
            'wise_type'    => $wiseType,
            'action'       => $ex ? 'update' : 'create',
            'existing_id'  => $ex['id']       ?? null,
            'existing_name'=> $ex ? ($ex['first_name'].' '.$ex['last_name']) : null,
        ];
    }

    return [$rows, []];
}


/* ================================================================
   IMPORT-FUNKTION
   ================================================================ */
function doImport(array $preview, array $selected, string $defaultPw, \PDO $db): array
{
    $created = $updated = $skipped = 0;
    $rows = [];

    // ── Wise-Spalten vorhanden? (Patch ggf. noch nicht gelaufen) ──
    $wiseColsExist = false;
    try {
        $check = $db->query("SHOW COLUMNS FROM `teachers` LIKE 'wise_recipient_id'");
        $wiseColsExist = ($check && $check->rowCount() > 0);
    } catch (\Throwable $e) { /* ignorieren */ }

    foreach ($preview as $r) {
        // Nur ausgewählte Zeilen
        if (!isset($selected[$r['email']])) {
            $skipped++;
            $rows[] = ['email'=>$r['email'],'name'=>$r['first_name'].' '.$r['last_name'],
                       'status'=>'skipped','has_wise'=>false];
            continue;
        }

        try {
            $db->beginTransaction();

            if ($r['action'] === 'create') {
                // ── User anlegen ──
                $hash = password_hash($defaultPw, PASSWORD_BCRYPT, ['cost'=>12]);
                $db->prepare(
                    "INSERT INTO users
                       (email,password_hash,role_id,first_name,last_name,is_active)
                     VALUES (?,?,?,?,?,1)"
                )->execute([$r['email'], $hash, ROLE_LEHRER,
                            $r['first_name'], $r['last_name']]);
                $userId = (int)$db->lastInsertId();

                // ── Teacher-Profil: erst ohne Wise ──
                $db->prepare(
                    "INSERT INTO teachers
                       (user_id,employment_type,hourly_rate,active_from,phone)
                     VALUES (?,?,?,?,?)"
                )->execute([$userId, $r['emp_type'], $r['rate'],
                            $r['active_from'], $r['phone']]);
                $teacherId = (int)$db->lastInsertId();

                // ── Stundensatz historisieren ──
                if ($r['rate'] > 0) {
                    $db->prepare(
                        "INSERT INTO teacher_rates
                           (teacher_id,hourly_rate,valid_from,created_by)
                         VALUES (?,?,?,?)"
                    )->execute([$teacherId, $r['rate'], $r['active_from'], Auth::userId()]);
                }

                $db->commit();

                // ── Wise-Daten separat updaten (falls Spalten vorhanden) ──
                $hasWise = false;
                if ($wiseColsExist && $r['wise_id']) {
                    try {
                        $db->prepare(
                            "UPDATE teachers SET
                               wise_recipient_id=?,wise_recipient_name=?,
                               wise_recipient_detail=?,wise_receiver_type=?
                             WHERE id=?"
                        )->execute([$r['wise_id'],$r['wise_name'],
                                    $r['wise_detail'],$r['wise_type'],$teacherId]);
                        $hasWise = true;
                    } catch (\Throwable $e) { /* Wise-Update ignorieren */ }
                }

                logAudit(Auth::userId(), 'teacher.csv_created', 'users', $userId, [], [
                    'email'=>$r['email'],'emp_type'=>$r['emp_type'],
                ]);
                $created++;
                $rows[] = ['email'=>$r['email'],
                           'name'=>$r['first_name'].' '.$r['last_name'],
                           'status'=>'created','has_wise'=>$hasWise,
                           'wise_pending'=>(!$wiseColsExist && $r['wise_id'])];

            } else {
                // ── Bestehenden User aktualisieren ──
                $db->prepare(
                    "UPDATE users SET first_name=?,last_name=?,is_active=1 WHERE id=?"
                )->execute([$r['first_name'],$r['last_name'],$r['existing_id']]);

                // Teacher-Profil laden
                $s = $db->prepare("SELECT id,hourly_rate FROM teachers WHERE user_id=?");
                $s->execute([$r['existing_id']]);
                $t = $s->fetch();

                if ($t) {
                    // Core-Felder updaten (ohne Wise)
                    $db->prepare(
                        "UPDATE teachers SET
                           employment_type=?,hourly_rate=?,active_from=?,phone=?
                         WHERE user_id=?"
                    )->execute([$r['emp_type'],$r['rate'],$r['active_from'],
                                $r['phone'],$r['existing_id']]);

                    // Stundensatz geändert → historisieren
                    if ($r['rate'] > 0 && (float)$t['hourly_rate'] !== $r['rate']) {
                        $db->prepare(
                            "UPDATE teacher_rates SET valid_until=?
                             WHERE teacher_id=? AND valid_until IS NULL"
                        )->execute([date('Y-m-d', strtotime($r['active_from'].' -1 day')),$t['id']]);
                        $db->prepare(
                            "INSERT INTO teacher_rates
                               (teacher_id,hourly_rate,valid_from,created_by)
                             VALUES (?,?,?,?)"
                        )->execute([$t['id'],$r['rate'],$r['active_from'],Auth::userId()]);
                    }
                    $tId = (int)$t['id'];
                } else {
                    // Noch kein Teacher-Profil → anlegen
                    $db->prepare(
                        "INSERT INTO teachers
                           (user_id,employment_type,hourly_rate,active_from,phone)
                         VALUES (?,?,?,?,?)"
                    )->execute([$r['existing_id'],$r['emp_type'],$r['rate'],
                                $r['active_from'],$r['phone']]);
                    $tId = (int)$db->lastInsertId();
                }

                $db->commit();

                // ── Wise-Daten separat updaten (falls Spalten vorhanden) ──
                $hasWise = false;
                if ($wiseColsExist && $r['wise_id']) {
                    try {
                        $db->prepare(
                            "UPDATE teachers SET
                               wise_recipient_id=?,wise_recipient_name=?,
                               wise_recipient_detail=?,wise_receiver_type=?
                             WHERE id=?"
                        )->execute([$r['wise_id'],$r['wise_name'],
                                    $r['wise_detail'],$r['wise_type'],$tId]);
                        $hasWise = true;
                    } catch (\Throwable $e) { /* Wise-Update ignorieren */ }
                }

                logAudit(Auth::userId(),'teacher.csv_updated','users',$r['existing_id'],[],[
                    'email'=>$r['email'],
                ]);
                $updated++;
                $rows[] = ['email'=>$r['email'],
                           'name'=>$r['first_name'].' '.$r['last_name'],
                           'status'=>'updated','has_wise'=>$hasWise,
                           'wise_pending'=>(!$wiseColsExist && $r['wise_id'])];
            }

        } catch (\Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            error_log('[WvH-TeacherImport] '.$r['email'].': '.$e->getMessage());
            $rows[] = ['email'=>$r['email'],
                       'name'=>$r['first_name'].' '.$r['last_name'],
                       'status'=>'error','error'=>$e->getMessage(),'has_wise'=>false];
            $skipped++;
        }
    }

    $wiseMissing = !$wiseColsExist;
    return ['created'=>$created,'updated'=>$updated,'skipped'=>$skipped,
            'rows'=>$rows,'wise_missing'=>$wiseMissing];
}
