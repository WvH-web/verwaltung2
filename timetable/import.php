<?php
/**
 * WvH – Stundenplan CSV-Import (3-Schritt-Wizard)
 */
require_once __DIR__ . '/../config/bootstrap.php';
use Auth\Auth;
use Controllers\TimetableImporter;

Auth::requireRole(ROLE_ADMIN, ROLE_VERWALTUNG);
$pageTitle   = 'Stundenplan importieren';
$breadcrumbs = ['Stundenplan' => APP_URL.'/timetable/view.php', 'CSV Import' => null];
$importer    = new TimetableImporter();
$errors      = [];
$step        = (int)($_POST['step'] ?? $_GET['step'] ?? 1);

/* ── Schritt 1 → 2 ── */
if ($step===1 && $_SERVER['REQUEST_METHOD']==='POST') {
    requireCsrf();
    $planName  = trim($_POST['plan_name']??'');
    $vFrom     = trim($_POST['valid_from']??'');
    $vUntil    = trim($_POST['valid_until']??'');
    if (!$planName) $errors[]='Plan-Bezeichnung fehlt.';
    if (!$vFrom)    $errors[]='Startdatum fehlt.';
    if (!$vUntil)   $errors[]='Enddatum fehlt.';
    if ($vFrom && $vUntil && $vFrom>=$vUntil) $errors[]='Enddatum muss nach Startdatum liegen.';
    $fileOk = isset($_FILES['csv_file']) && $_FILES['csv_file']['error']===UPLOAD_ERR_OK;
    if (!$fileOk) $errors[]='Bitte eine CSV-Datei hochladen.';
    elseif (strtolower(pathinfo($_FILES['csv_file']['name'],PATHINFO_EXTENSION))!=='csv')
        $errors[]='Nur .csv-Dateien erlaubt.';
    if (empty($errors)) {
        $tmpPath = UPLOAD_PATH.'/tmp_tt_'.session_id().'.csv';
        if (!move_uploaded_file($_FILES['csv_file']['tmp_name'],$tmpPath))
            $errors[]='Datei konnte nicht gespeichert werden (uploads/ beschreibbar?)';
        else {
            $parsed = $importer->parseCsv($tmpPath);
            if (empty($parsed['rows'])) {
                $errors = array_merge(['Keine importierbaren Einträge gefunden.'],$parsed['errors']);
            } else {
                $_SESSION['tt_import']=['plan_name'=>$planName,'valid_from'=>$vFrom,
                    'valid_until'=>$vUntil,'tmp_path'=>$tmpPath,'parsed'=>$parsed];
                $step=2;
            }
        }
    }
}

/* ── Schritt 2 → 3 ── */
if ($step===2 && $_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['teacher_map'])) {
    requireCsrf();
    if (empty($_SESSION['tt_import'])) redirect('timetable/import.php');
    $map=[];
    foreach ($_POST['teacher_map'] as $k=>$tid)
        if ($tid!=='') $map[urldecode($k)]=(int)$tid;
    if (empty($map)) { $errors[]='Mindestens einen Lehrer zuordnen.'; $step=2; }
    else { $_SESSION['tt_import']['teacher_map']=$map; $step=3; }
}

/* ── Schritt 3: Import ── */
if ($step===3 && $_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['do_import'])) {
    requireCsrf();
    $sess=$_SESSION['tt_import']??null;
    if (!$sess) redirect('timetable/import.php');
    $res=$importer->importToDb($sess['parsed'],$sess['teacher_map'],
        ['name'=>$sess['plan_name'],'valid_from'=>$sess['valid_from'],'valid_until'=>$sess['valid_until']],
        Auth::userId(),$sess['tmp_path']);
    unset($_SESSION['tt_import']);
    if ($res['success']) {
        setFlash('success',"✅ {$res['imported']} Einträge importiert, {$res['skipped']} übersprungen.");
        redirect('timetable/view.php?plan_id='.$res['plan_id']);
    } else {
        setFlash('error','Import fehlgeschlagen: '.$res['error']);
        redirect('timetable/import.php');
    }
}

/* ── Daten für Step 2 ── */
$sess       = $_SESSION['tt_import']??null;
$parsed     = $sess['parsed']??null;
$autoMap    = [];
$dbTeachers = [];
if ($parsed && $step===2) {
    $autoMap    = $importer->getTeacherMapping($parsed['teachers']);
    $dbTeachers = getDB()->query(
        "SELECT t.id AS teacher_id,CONCAT(u.first_name,' ',u.last_name) AS display_name FROM teachers t
         JOIN users u ON u.id=t.user_id WHERE u.is_active=1
         ORDER BY u.last_name,u.first_name")->fetchAll();
}

include TEMPLATE_PATH.'/layouts/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
  <h3 class="fw-bold mb-0" style="color:var(--wvh-primary)"><i class="bi bi-file-earmark-arrow-up me-2"></i>Stundenplan importieren</h3>
  <a href="<?=APP_URL?>/timetable/view.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Übersicht</a>
</div>

<!-- Wizard-Progress -->
<div class="card wvh-card mb-4">
  <div class="card-body py-3 px-4">
    <div class="d-flex align-items-center">
    <?php foreach(['Upload & Gültigkeit','Lehrer-Zuordnung','Import bestätigen'] as $i=>$lbl):
      $n=$i+1; $done=$n<$step; $act=$n===$step; ?>
      <div class="d-flex align-items-center <?=$i>0?'flex-grow-1':''?>">
        <?php if($i>0):?><div class="flex-grow-1 mx-2" style="height:2px;background:<?=$done?'var(--wvh-secondary)':'#dee2e6'?>"></div><?php endif;?>
        <div class="d-flex flex-column align-items-center" style="min-width:110px">
          <div class="rounded-circle d-flex align-items-center justify-content-center fw-bold"
               style="width:38px;height:38px;font-size:.9rem;
                      background:<?=$done?'var(--wvh-secondary)':($act?'var(--wvh-primary)':'#dee2e6')?>;
                      color:<?=($done||$act)?'#fff':'#999'?>">
            <?=$done?'<i class="bi bi-check-lg"></i>':$n?>
          </div>
          <small class="mt-1 text-nowrap <?=$act?'fw-semibold':'text-muted'?>" style="font-size:.73rem"><?=e($lbl)?></small>
        </div>
      </div>
    <?php endforeach;?>
    </div>
  </div>
</div>

<?php if($errors):?>
<div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-2"></i>
  <ul class="mb-0 mt-1"><?php foreach($errors as $err):?><li><?=e($err)?></li><?php endforeach;?></ul>
</div>
<?php endif;?>

<?php /* ===== STEP 1 ===== */ if($step===1):?>
<div class="row justify-content-center"><div class="col-lg-6">
<div class="card wvh-card">
  <div class="card-header"><div class="card-icon bg-primary bg-opacity-10 text-primary"><i class="bi bi-cloud-upload"></i></div>CSV-Datei &amp; Gültigkeitszeitraum</div>
  <div class="card-body">
    <form method="POST" enctype="multipart/form-data">
      <?=csrfField()?><input type="hidden" name="step" value="1">

      <label class="form-label fw-semibold">Stundenplan-Datei *</label>
      <div id="dz" class="rounded-3 p-4 text-center mb-1" style="border:2px dashed #adb5bd;cursor:pointer">
        <i class="bi bi-file-earmark-spreadsheet fs-2 text-success d-block mb-2"></i>
        <p class="text-muted small mb-2">CSV hier ablegen oder klicken</p>
        <label class="btn btn-sm btn-wvh-outline">Datei wählen
          <input type="file" name="csv_file" id="csvIn" accept=".csv" class="d-none" required>
        </label>
        <p class="small text-muted mt-2 mb-0" id="csvName">Keine Datei gewählt</p>
      </div>
      <div class="form-text mb-4">FET-Export · Semikolon-getrennt · „timetable.csv"</div>

      <div class="mb-3">
        <label class="form-label fw-semibold">Plan-Bezeichnung *</label>
        <input type="text" name="plan_name" class="form-control" value="2. Halbjahr 2025/26" required>
      </div>

      <div class="row g-3 mb-4">
        <div class="col-6">
          <label class="form-label fw-semibold"><i class="bi bi-calendar-check text-success me-1"></i>Gültig ab *</label>
          <input type="date" name="valid_from" class="form-control" required>
          <div class="form-text">Erster Tag dieses Plans</div>
        </div>
        <div class="col-6">
          <label class="form-label fw-semibold"><i class="bi bi-calendar-x text-danger me-1"></i>Gültig bis *</label>
          <input type="date" name="valid_until" class="form-control" required>
          <div class="form-text">Letzter Tag (inkl.)</div>
        </div>
      </div>

      <div class="alert alert-info small d-flex gap-2 mb-4">
        <i class="bi bi-info-circle-fill text-info mt-1 flex-shrink-0"></i>
        <span>Überlappende bestehende Pläne werden automatisch bis zum Vortag des neuen Startdatums gekürzt.</span>
      </div>

      <div class="d-flex justify-content-end">
        <button type="submit" class="btn btn-wvh">Weiter: Lehrer zuordnen <i class="bi bi-arrow-right ms-2"></i></button>
      </div>
    </form>
  </div>
</div>
</div></div>
<script>
document.getElementById('csvIn').addEventListener('change',function(){
  document.getElementById('csvName').textContent=this.files[0]?.name||'Keine Datei';
  document.getElementById('dz').style.borderColor='var(--wvh-secondary)';
});
</script>

<?php /* ===== STEP 2 ===== */ elseif($step===2 && $parsed):
$autoMapped=count(array_filter($autoMap));
$total=count($parsed['teachers']);
?>
<form method="POST">
<?=csrfField()?><input type="hidden" name="step" value="2">
<div class="row g-4">

  <!-- Stats -->
  <div class="col-lg-4">
    <div class="card wvh-card mb-3">
      <div class="card-header"><div class="card-icon bg-success bg-opacity-10 text-success"><i class="bi bi-bar-chart"></i></div>Import-Vorschau</div>
      <div class="card-body p-0">
        <table class="table table-sm mb-0">
          <tr><td class="ps-3 text-muted small">Importierbare Einträge</td><td class="pe-3 fw-bold text-end text-success"><?=$parsed['stats']['total']?></td></tr>
          <tr><td class="ps-3 text-muted small">Ohne Lehrer (übersprungen)</td><td class="pe-3 text-muted text-end"><?=$parsed['stats']['skipped_no_teacher']?></td></tr>
          <tr><td class="ps-3 text-muted small">Doppelstunden-Paare</td><td class="pe-3 fw-bold text-end"><?=$parsed['stats']['doubles']?></td></tr>
          <tr><td class="ps-3 text-muted small">Lehrer (CSV)</td><td class="pe-3 fw-bold text-end"><?=$total?></td></tr>
          <tr><td class="ps-3 text-muted small">Fächer</td><td class="pe-3 fw-bold text-end"><?=$parsed['stats']['subjects']?></td></tr>
          <tr><td class="ps-3 text-muted small">Klassen/Kurse</td><td class="pe-3 fw-bold text-end"><?=$parsed['stats']['classes']?></td></tr>
        </table>
      </div>
    </div>

    <div class="card wvh-card mb-3">
      <div class="card-body py-3">
        <div class="d-flex align-items-center gap-2 mb-2">
          <div class="rounded-circle bg-success text-white d-flex align-items-center justify-content-center fw-bold" style="width:40px;height:40px"><?=$autoMapped?></div>
          <div><div class="fw-semibold small">Automatisch erkannt</div><div class="text-muted" style="font-size:.73rem">Exakter Namensabgleich</div></div>
        </div>
        <?php if($total-$autoMapped>0):?>
        <div class="d-flex align-items-center gap-2">
          <div class="rounded-circle bg-warning text-dark d-flex align-items-center justify-content-center fw-bold" style="width:40px;height:40px"><?=$total-$autoMapped?></div>
          <div><div class="fw-semibold small">Manuell zuordnen</div><div class="text-muted" style="font-size:.73rem">Name nicht in DB</div></div>
        </div>
        <?php else:?>
        <div class="alert alert-success py-2 mb-0 small"><i class="bi bi-check-circle me-1"></i>Alle automatisch erkannt!</div>
        <?php endif;?>
      </div>
    </div>

    <div class="card wvh-card">
      <div class="card-body py-3">
        <div class="small text-muted">Plan</div><div class="fw-bold"><?=e($sess['plan_name'])?></div>
        <div class="small text-muted mt-2">Gültig</div>
        <div class="fw-semibold text-success"><?=formatDate($sess['valid_from'])?></div>
        <div class="text-muted small">–</div>
        <div class="fw-semibold text-danger"><?=formatDate($sess['valid_until'])?></div>
      </div>
    </div>
  </div>

  <!-- Mapping -->
  <div class="col-lg-8">
    <div class="card wvh-card">
      <div class="card-header">
        <div class="card-icon bg-warning bg-opacity-10 text-warning"><i class="bi bi-person-lines-fill"></i></div>
        Lehrer-Zuordnung: CSV → WvH-Benutzer
      </div>
      <div class="px-3 pt-3 pb-2 border-bottom">
        <input type="text" id="tSearch" class="form-control form-control-sm" placeholder="🔍 Name suchen…">
      </div>
      <div style="max-height:520px;overflow-y:auto" id="tList">
        <?php foreach($parsed['teachers'] as $csvName):
          $tid=$autoMap[$csvName]??null; $isCoT=(strpos($csvName,'+')!==false);
          $mapKey=urlencode($csvName);?>
        <div class="trow d-flex align-items-center gap-2 px-3 py-2 border-bottom <?=$tid===null?'bg-warning bg-opacity-10':''?>">
          <div style="flex:0 0 43%;min-width:0">
            <div class="fw-medium small text-truncate"><?=e($csvName)?></div>
            <?php if($isCoT):?><span class="badge bg-info mt-1" style="font-size:.6rem"><i class="bi bi-people-fill me-1"></i>Co-Teaching</span><?php endif;?>
          </div>
          <div style="flex:0 0 24px;text-align:center">
            <?php if($tid!==null):?>
              <i class="bi bi-check-circle-fill text-success"></i>
            <?php else:?>
              <i class="bi bi-exclamation-circle-fill text-warning"></i>
            <?php endif;?>
          </div>
          <div class="flex-grow-1">
            <select name="teacher_map[<?=e($mapKey)?>]" class="form-select form-select-sm">
              <option value="">– nicht importieren –</option>
              <?php foreach($dbTeachers as $dbt):?>
              <option value="<?=$dbt['teacher_id']?>" <?=(int)$tid===(int)$dbt['teacher_id']?'selected':''?>>
                <?=e($dbt['display_name'])?>
              </option>
              <?php endforeach;?>
            </select>
          </div>
        </div>
        <?php endforeach;?>
      </div>
      <div class="card-footer bg-transparent d-flex justify-content-between">
        <a href="<?=APP_URL?>/timetable/import.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Zurück</a>
        <button type="submit" class="btn btn-wvh">Weiter: Importieren <i class="bi bi-arrow-right ms-2"></i></button>
      </div>
    </div>
  </div>
</div>
</form>
<script>
document.getElementById('tSearch').addEventListener('input',function(){
  const q=this.value.toLowerCase();
  document.querySelectorAll('.trow').forEach(r=>r.style.display=r.textContent.toLowerCase().includes(q)?'':'none');
});
</script>

<?php /* ===== STEP 3 ===== */ elseif($step===3 && $sess):?>
<div class="row justify-content-center"><div class="col-lg-5">
<div class="card wvh-card">
  <div class="card-header" style="background:linear-gradient(135deg,#d1fae5,#a7f3d0);color:#065f46">
    <div class="card-icon" style="background:#10b981;color:#fff"><i class="bi bi-check2-all"></i></div>Import bestätigen
  </div>
  <div class="card-body">
    <table class="table table-sm mb-4">
      <tr><td class="text-muted">Plan</td><td class="fw-bold"><?=e($sess['plan_name'])?></td></tr>
      <tr><td class="text-muted">Gültig von</td><td class="fw-bold text-success"><?=formatDate($sess['valid_from'])?></td></tr>
      <tr><td class="text-muted">Gültig bis</td><td class="fw-bold text-danger"><?=formatDate($sess['valid_until'])?></td></tr>
      <tr><td class="text-muted">Importierbar</td><td class="fw-bold"><?=$sess['parsed']['stats']['total']?> Einträge</td></tr>
      <tr><td class="text-muted">Lehrer zugeordnet</td><td class="fw-bold"><?=count($sess['teacher_map'])?> / <?=count($sess['parsed']['teachers'])?></td></tr>
      <tr><td class="text-muted">Doppelstunden</td><td class="fw-bold"><?=$sess['parsed']['stats']['doubles']?> Paare</td></tr>
    </table>
    <div class="alert alert-warning small d-flex gap-2 mb-4">
      <i class="bi bi-exclamation-triangle-fill text-warning mt-1 flex-shrink-0"></i>
      <span>Nicht zugeordnete Lehrer werden übersprungen. Überlappende Pläne werden gekürzt. Nicht rückgängig machbar.</span>
    </div>
    <form method="POST">
      <?=csrfField()?>
      <input type="hidden" name="step" value="3">
      <input type="hidden" name="do_import" value="1">
      <div class="d-flex gap-2">
        <a href="<?=APP_URL?>/timetable/import.php" class="btn btn-outline-secondary flex-grow-1"><i class="bi bi-x me-1"></i>Abbrechen</a>
        <button type="submit" class="btn btn-success flex-grow-1 fw-semibold"><i class="bi bi-database-fill-add me-2"></i>Jetzt importieren</button>
      </div>
    </form>
  </div>
</div>
</div></div>
<?php endif;?>

<?php include TEMPLATE_PATH.'/layouts/footer.php';?>
