<?php
/**
 * WvH – Mein Profil
 */
require_once __DIR__ . '/../config/bootstrap.php';

use Auth\Auth;

Auth::require();

$pageTitle   = 'Mein Profil';
$breadcrumbs = ['Mein Profil' => null];
$db = getDB();

$isVwg = Auth::isVerwaltung();

$stmt = $db->prepare(
    "SELECT u.*, r.label AS role_label, t.*, t.id AS teacher_id
     FROM users u
     JOIN roles r ON r.id = u.role_id
     LEFT JOIN teachers t ON t.user_id = u.id
     WHERE u.id = ?"
);
$stmt->execute([Auth::userId()]);
$profile = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $action = $_POST['action'] ?? 'profile';

    if ($action === 'profile') {
        $street  = trim($_POST['street']  ?? '');
        $zip     = trim($_POST['zip']     ?? '');
        $city    = trim($_POST['city']    ?? '');
        $country = trim($_POST['country'] ?? 'Deutschland');
        $phone   = trim($_POST['phone']   ?? '');
        $taxId   = trim($_POST['tax_id']  ?? '');

        if ($profile['teacher_id'] ?? false) {
            $db->prepare(
                "UPDATE teachers SET street=?, zip=?, city=?, country=?, phone=?, tax_id=?, updated_at=NOW()
                 WHERE user_id=?"
            )->execute([$street, $zip, $city, $country, $phone, $taxId, Auth::userId()]);
        }
        logAudit(Auth::userId(), 'user.profile_updated', 'users', Auth::userId());
        setFlash('success', 'Dein Profil wurde gespeichert.');
        header('Location: ' . APP_URL . '/profile/index.php');
        exit;

    } elseif ($action === 'bank') {
        $iban          = strtoupper(preg_replace('/\s+/', '', $_POST['iban'] ?? ''));
        $bic           = strtoupper(trim($_POST['bic']           ?? ''));
        $bankName      = trim($_POST['bank_name']      ?? '');
        $accountHolder = trim($_POST['account_holder'] ?? '');

        if ($profile['teacher_id'] ?? false) {
            $db->prepare(
                "UPDATE teachers SET iban=?, bic=?, bank_name=?, account_holder=?,
                  bank_data_approved=0, bank_data_approved_by=NULL, bank_data_approved_at=NULL,
                  updated_at=NOW()
                 WHERE user_id=?"
            )->execute([$iban, $bic, $bankName, $accountHolder, Auth::userId()]);
            logAudit(Auth::userId(), 'teacher.bank_data_changed', 'teachers', $profile['teacher_id']);
            setFlash('warning', 'Bankdaten wurden gespeichert und warten auf Freigabe durch die Verwaltung.');
        }
        header('Location: ' . APP_URL . '/profile/index.php');
        exit;
    }
}

$stmt->execute([Auth::userId()]);
$profile = $stmt->fetch();

include TEMPLATE_PATH . '/layouts/header.php';
?>

<div class="row g-4">
  <div class="col-md-4">
    <div class="card wvh-card text-center">
      <div class="card-body py-4">
        <div class="wvh-avatar mx-auto mb-3" style="width:64px;height:64px;font-size:1.6rem">
          <?= strtoupper(substr($profile['first_name'], 0, 1)) ?>
        </div>
        <h5 class="fw-bold mb-1"><?= e($profile['first_name'] . ' ' . $profile['last_name']) ?></h5>
        <p class="text-muted small mb-2"><?= e($profile['email']) ?></p>
        <span class="badge bg-primary"><?= e($profile['role_label']) ?></span>
        <?php if ($profile['employment_type'] ?? false): ?>
          <br><br>
          <span class="badge <?= $profile['employment_type'] === 'honorar' ? 'bg-warning text-dark' : 'bg-info' ?>">
            <?= $profile['employment_type'] === 'honorar' ? 'Honorarkraft' : 'Festangestellt' ?>
          </span>
          <?php if ($profile['employment_type'] === 'honorar' && $profile['hourly_rate']): ?>
            <p class="mt-2 mb-0 text-muted small">Stundensatz: <strong><?= formatEuro((float)$profile['hourly_rate']) ?></strong>/UE</p>
          <?php endif; ?>
        <?php endif; ?>
        <hr>
        <div class="d-grid gap-2">
          <a href="<?= APP_URL ?>/profile/change-password.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-key me-1"></i>Passwort ändern
          </a>
        </div>
      </div>
    </div>
  </div>

  <div class="col-md-8">

    <!-- Stammdaten -->
    <div class="card wvh-card mb-4">
      <div class="card-header">
        <div class="card-icon bg-primary bg-opacity-10 text-primary"><i class="bi bi-person-fill"></i></div>
        Persönliche Daten
      </div>
      <div class="card-body">
        <form method="POST" data-dirty-check>
          <?= csrfField() ?>
          <input type="hidden" name="action" value="profile">
          <div class="row g-3">
            <div class="col-12">
              <label class="form-label">Straße & Hausnummer</label>
              <input type="text" name="street" class="form-control" value="<?= e($profile['street'] ?? '') ?>">
            </div>
            <div class="col-md-4">
              <label class="form-label">PLZ</label>
              <input type="text" name="zip" class="form-control" value="<?= e($profile['zip'] ?? '') ?>">
            </div>
            <div class="col-md-5">
              <label class="form-label">Stadt</label>
              <input type="text" name="city" class="form-control" value="<?= e($profile['city'] ?? '') ?>">
            </div>
            <div class="col-md-3">
              <label class="form-label">Land</label>
              <input type="text" name="country" class="form-control" value="<?= e($profile['country'] ?? 'Deutschland') ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label">Telefon</label>
              <input type="tel" name="phone" class="form-control" value="<?= e($profile['phone'] ?? '') ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label">Steuer-ID / USt-ID</label>
              <input type="text" name="tax_id" class="form-control" value="<?= e($profile['tax_id'] ?? '') ?>">
            </div>
          </div>
          <div class="mt-3">
            <button type="submit" class="btn btn-wvh">
              <i class="bi bi-save me-2"></i>Speichern
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Bankdaten -->
    <?php if ($profile['employment_type'] ?? false): ?>
    <div class="card wvh-card">
      <div class="card-header">
        <div class="card-icon bg-success bg-opacity-10 text-success"><i class="bi bi-bank2"></i></div>
        Bankverbindung
        <?php if ($profile['bank_data_approved']): ?>
          <span class="badge bg-success ms-auto"><i class="bi bi-check-circle me-1"></i>Freigegeben</span>
        <?php elseif ($profile['iban']): ?>
          <span class="badge bg-warning text-dark ms-auto"><i class="bi bi-clock me-1"></i>Warte auf Freigabe</span>
        <?php endif; ?>
      </div>
      <div class="card-body">
        <?php if (!$profile['bank_data_approved'] && $profile['iban']): ?>
        <div class="alert alert-warning mb-3">
          <i class="bi bi-clock me-2"></i>
          Deine Bankdaten wurden geändert und warten auf Freigabe durch die Verwaltung.
        </div>
        <?php endif; ?>

        <form method="POST" id="bankForm">
          <?= csrfField() ?>
          <input type="hidden" name="action" value="bank">

          <?php if ($isVwg): ?>
          <!-- VERWALTUNG: alle Felder frei editierbar -->
          <div class="row g-3">
            <div class="col-md-8">
              <label class="form-label fw-semibold">IBAN</label>
              <div class="input-group">
                <input type="text" name="iban" id="ibanInput"
                       class="form-control font-monospace"
                       value="<?= e($profile['iban'] ?? '') ?>"
                       placeholder="DE00 0000 0000 0000 0000 00"
                       style="letter-spacing:.1em"
                       oninput="lookupIban(this.value)">
                <span class="input-group-text" id="ibanSpinner" style="display:none">
                  <span class="spinner-border spinner-border-sm"></span>
                </span>
                <span class="input-group-text text-success" id="ibanOk" style="display:none">
                  <i class="bi bi-check-circle-fill"></i>
                </span>
              </div>
              <div id="ibanError" class="form-text text-danger" style="display:none"></div>
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">BIC / SWIFT</label>
              <input type="text" name="bic" id="bicInput"
                     class="form-control font-monospace"
                     value="<?= e($profile['bic'] ?? '') ?>"
                     placeholder="DEUTDEDB">
              <div class="form-text text-success" id="bicAutoNote" style="display:none">
                <i class="bi bi-magic me-1"></i>Automatisch ermittelt
              </div>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Bankname</label>
              <input type="text" name="bank_name" id="bankNameInput"
                     class="form-control"
                     value="<?= e($profile['bank_name'] ?? '') ?>">
              <div class="form-text text-success" id="bankAutoNote" style="display:none">
                <i class="bi bi-magic me-1"></i>Automatisch ermittelt
              </div>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Kontoinhaber/in</label>
              <input type="text" name="account_holder" id="holderInput"
                     class="form-control"
                     value="<?= e($profile['account_holder'] ?? '') ?>">
            </div>
          </div>

          <?php else: ?>
          <!-- LEHRER: nur IBAN eingeben, BIC + Bank werden automatisch ermittelt -->
          <p class="text-muted small mb-3">
            <i class="bi bi-info-circle me-1"></i>
            Gib deine IBAN ein – BIC und Bankname werden automatisch ermittelt.
          </p>
          <div class="row g-3">
            <div class="col-md-8">
              <label class="form-label fw-semibold">IBAN</label>
              <div class="input-group">
                <input type="text" name="iban" id="ibanInput"
                       class="form-control form-control-lg font-monospace"
                       value="<?= e($profile['iban'] ?? '') ?>"
                       placeholder="DE00 0000 0000 0000 0000 00"
                       style="letter-spacing:.12em"
                       oninput="lookupIban(this.value)"
                       autocomplete="off">
                <span class="input-group-text" id="ibanSpinner" style="display:none">
                  <span class="spinner-border spinner-border-sm text-secondary"></span>
                </span>
                <span class="input-group-text text-success fw-bold" id="ibanOk" style="display:none">
                  <i class="bi bi-check-circle-fill"></i>
                </span>
              </div>
              <div id="ibanError" class="form-text text-danger mt-1" style="display:none"></div>
            </div>

            <div class="col-md-6">
              <label class="form-label fw-semibold">Kontoinhaber/in</label>
              <input type="text" name="account_holder" id="holderInput"
                     class="form-control"
                     value="<?= e($profile['account_holder'] ?? '') ?>"
                     placeholder="<?= e($profile['first_name'].' '.$profile['last_name']) ?>">
            </div>
          </div>

          <!-- Automatisch ermittelte Bankdaten (read-only Anzeige) -->
          <div id="bankAutoFields" class="mt-3 p-3 rounded"
               style="background:#f8fafc;border:1px solid #e2e8f0;display:<?= $profile['bic'] ? 'block' : 'none' ?>">
            <div class="row g-3">
              <div class="col-sm-4">
                <div class="text-muted small mb-1"><i class="bi bi-magic me-1"></i>BIC (automatisch)</div>
                <div class="fw-semibold font-monospace" id="bicDisplay"><?= e($profile['bic'] ?? '–') ?></div>
                <input type="hidden" name="bic" id="bicInput" value="<?= e($profile['bic'] ?? '') ?>">
              </div>
              <div class="col-sm-8">
                <div class="text-muted small mb-1"><i class="bi bi-magic me-1"></i>Bank (automatisch)</div>
                <div class="fw-semibold" id="bankNameDisplay"><?= e($profile['bank_name'] ?? '–') ?></div>
                <input type="hidden" name="bank_name" id="bankNameInput" value="<?= e($profile['bank_name'] ?? '') ?>">
              </div>
            </div>
          </div>
          <?php endif; ?>

          <div class="mt-3 d-flex align-items-center gap-3 flex-wrap">
            <button type="submit" class="btn btn-wvh">
              <i class="bi bi-save me-2"></i>Bankdaten speichern
            </button>
            <small class="text-muted">
              <i class="bi bi-info-circle me-1"></i>
              Änderungen warten auf Freigabe durch die Verwaltung.
            </small>
          </div>
        </form>
      </div>
    </div>
    <?php endif; ?>

  </div>
</div>

<script>
let lookupTimer = null;

function lookupIban(raw) {
    const iban = raw.replace(/\s+/g, '').toUpperCase();
    const okIcon   = document.getElementById('ibanOk');
    const spinner  = document.getElementById('ibanSpinner');
    const errDiv   = document.getElementById('ibanError');

    okIcon.style.display  = 'none';
    errDiv.style.display  = 'none';

    if (iban.length < 15) {
        spinner.style.display = 'none';
        return;
    }

    clearTimeout(lookupTimer);
    spinner.style.display = '';

    lookupTimer = setTimeout(async () => {
        try {
            const res  = await fetch(
                `https://openiban.com/validate/${encodeURIComponent(iban)}?getBIC=true&validateBankCode=true`,
                { signal: AbortSignal.timeout(8000) }
            );
            const data = await res.json();
            spinner.style.display = 'none';

            if (data.valid) {
                const bic      = data.bankData?.bic  ?? '';
                const bankName = data.bankData?.name ?? '';

                setField('bicInput',      bic);
                setField('bankNameInput', bankName);

                // Anzeige (Lehrer-Ansicht)
                setText('bicDisplay',      bic      || '–');
                setText('bankNameDisplay', bankName || '–');
                show('bankAutoFields');

                // Verwaltungs-Ansicht: Hinweis
                show('bicAutoNote');
                show('bankAutoNote');

                okIcon.style.display = '';
            } else {
                errDiv.textContent   = 'IBAN ungültig oder nicht erkannt – bitte prüfen.';
                errDiv.style.display = '';
                setField('bicInput',      '');
                setField('bankNameInput', '');
                setText('bicDisplay',      '–');
                setText('bankNameDisplay', '–');
            }
        } catch (e) {
            spinner.style.display = 'none';
            // Netzwerkfehler still ignorieren
        }
    }, 800);
}

function setField(id, val) {
    const el = document.getElementById(id);
    if (el) el.value = val;
}
function setText(id, val) {
    const el = document.getElementById(id);
    if (el) el.textContent = val;
}
function show(id) {
    const el = document.getElementById(id);
    if (el) el.style.display = '';
}
</script>

<?php include TEMPLATE_PATH . '/layouts/footer.php'; ?>
