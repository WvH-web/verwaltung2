<?php
/**
 * WvH – Benutzerverwaltung (Admin)
 */
require_once __DIR__ . '/../config/bootstrap.php';

use Auth\Auth;

Auth::requireRole(ROLE_ADMIN, ROLE_VERWALTUNG);

$pageTitle   = 'Benutzerverwaltung';
$breadcrumbs = ['Administration' => null, 'Benutzer' => null];
$db = getDB();
$error = $success = '';

// ---- Neuer Benutzer anlegen ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
    requireCsrf();

    $newEmail     = strtolower(trim($_POST['email'] ?? ''));
    $firstName    = trim($_POST['first_name'] ?? '');
    $lastName     = trim($_POST['last_name'] ?? '');
    $roleId       = (int)($_POST['role_id'] ?? ROLE_LEHRER);
    $password     = $_POST['password'] ?? '';
    $empType      = $_POST['employment_type'] ?? 'honorar';
    $hourlyRate   = (float)($_POST['hourly_rate'] ?? 0);
    $activeFrom   = $_POST['active_from'] ?? date('Y-m-d');

    // Validierung
    if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
        $error = 'Bitte gib eine gültige E-Mail-Adresse ein.';
    } elseif (strlen($password) < 8) {
        $error = 'Das Passwort muss mindestens 8 Zeichen haben.';
    } elseif (empty($firstName) || empty($lastName)) {
        $error = 'Vor- und Nachname sind Pflichtfelder.';
    } else {
        try {
            $db->beginTransaction();

            // User anlegen
            $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
            $stmt = $db->prepare(
                "INSERT INTO users (email, password_hash, role_id, first_name, last_name) VALUES (?,?,?,?,?)"
            );
            $stmt->execute([$newEmail, $hash, $roleId, $firstName, $lastName]);
            $userId = (int)$db->lastInsertId();

            // Teacher-Profil anlegen (wenn Lehrer oder Verwaltung)
            if ($roleId === ROLE_LEHRER) {
                $stmt2 = $db->prepare(
                    "INSERT INTO teachers (user_id, employment_type, hourly_rate, active_from) VALUES (?,?,?,?)"
                );
                $stmt2->execute([$userId, $empType, $hourlyRate, $activeFrom]);
                $teacherId = (int)$db->lastInsertId();

                // Startstundensatz in Historie
                $db->prepare(
                    "INSERT INTO teacher_rates (teacher_id, hourly_rate, valid_from, created_by) VALUES (?,?,?,?)"
                )->execute([$teacherId, $hourlyRate, $activeFrom, Auth::userId()]);
            }

            $db->commit();
            logAudit(Auth::userId(), 'user.created', 'users', $userId, [], ['email' => $newEmail, 'role_id' => $roleId]);
            setFlash('success', "Benutzer {$firstName} {$lastName} wurde erfolgreich angelegt.");
            header('Location: ' . APP_URL . '/admin/users.php');
            exit;

        } catch (\PDOException $e) {
            $db->rollBack();
            if ($e->getCode() == 23000) {
                $error = 'Diese E-Mail-Adresse ist bereits vergeben.';
            } else {
                $error = 'Datenbankfehler: ' . $e->getMessage();
            }
        }
    }
}

// ---- Benutzer aktivieren/deaktivieren ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle') {
    requireCsrf();
    $toggleId = (int)($_POST['user_id'] ?? 0);
    if ($toggleId && $toggleId !== Auth::userId()) {
        $db->prepare("UPDATE users SET is_active = NOT is_active WHERE id = ?")->execute([$toggleId]);
        setFlash('success', 'Benutzerstatus wurde geändert.');
        header('Location: ' . APP_URL . '/admin/users.php');
        exit;
    }
}

// ---- Alle Benutzer laden ----
$users = $db->query(
    "SELECT u.*, r.label AS role_label,
            t.employment_type, t.hourly_rate, t.id AS teacher_id
     FROM users u
     JOIN roles r ON r.id = u.role_id
     LEFT JOIN teachers t ON t.user_id = u.id
     ORDER BY r.id ASC, u.last_name ASC, u.first_name ASC"
)->fetchAll();

$roles = $db->query("SELECT * FROM roles ORDER BY id")->fetchAll();

include TEMPLATE_PATH . '/layouts/header.php';
?>

<!-- Toolbar -->
<div class="d-flex justify-content-between align-items-center mb-4">
  <h3 class="fw-bold mb-0" style="color:var(--wvh-primary)">
    <i class="bi bi-people-fill me-2"></i>Benutzerverwaltung
  </h3>
  <button class="btn btn-wvh" data-bs-toggle="modal" data-bs-target="#createUserModal">
    <i class="bi bi-person-plus-fill me-2"></i>Neuen Benutzer anlegen
  </button>
</div>

<?php if ($error): ?>
<div class="alert alert-danger"><i class="bi bi-x-circle me-2"></i><?= e($error) ?></div>
<?php endif; ?>

<!-- Suchfeld -->
<div class="mb-3">
  <div class="input-group" style="max-width:360px">
    <span class="input-group-text"><i class="bi bi-search"></i></span>
    <input type="text" class="form-control" id="userSearch" placeholder="Name oder E-Mail suchen…">
  </div>
</div>

<!-- Benutzer-Tabelle -->
<div class="card wvh-card">
  <div class="table-responsive">
    <table class="wvh-table" id="userTable">
      <thead>
        <tr>
          <th>Name</th>
          <th>E-Mail</th>
          <th>Rolle</th>
          <th>Typ</th>
          <th>Stundensatz</th>
          <th>Letzter Login</th>
          <th>Status</th>
          <th class="text-end">Aktionen</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($users as $u): ?>
        <tr>
          <td>
            <div class="d-flex align-items-center gap-2">
              <div class="wvh-avatar" style="width:32px;height:32px;font-size:.75rem">
                <?= strtoupper(substr($u['first_name'], 0, 1)) ?>
              </div>
              <span class="fw-medium"><?= e($u['first_name'] . ' ' . $u['last_name']) ?></span>
            </div>
          </td>
          <td class="text-muted"><?= e($u['email']) ?></td>
          <td>
            <?php
            $roleBadge = ['Administrator' => 'danger', 'Verwaltung' => 'primary', 'Lehrer/in' => 'secondary'];
            $rc = $roleBadge[$u['role_label']] ?? 'secondary';
            ?>
            <span class="badge bg-<?= $rc ?>"><?= e($u['role_label']) ?></span>
          </td>
          <td class="small">
            <?php if ($u['employment_type']): ?>
              <?= $u['employment_type'] === 'honorar' ? '<span class="badge bg-warning text-dark">Honorar</span>' : '<span class="badge bg-info">Festangestellt</span>' ?>
            <?php else: ?>
              <span class="text-muted">–</span>
            <?php endif; ?>
          </td>
          <td>
            <?= $u['hourly_rate'] ? formatEuro((float)$u['hourly_rate']) : '<span class="text-muted">–</span>' ?>
          </td>
          <td class="text-muted small">
            <?= $u['last_login'] ? formatDate($u['last_login'], 'd.m.Y H:i') : '–' ?>
          </td>
          <td>
            <?php if ($u['is_active']): ?>
              <span class="badge bg-success-subtle text-success border border-success"><i class="bi bi-circle-fill me-1" style="font-size:.5rem"></i>Aktiv</span>
            <?php else: ?>
              <span class="badge bg-danger-subtle text-danger border border-danger"><i class="bi bi-circle-fill me-1" style="font-size:.5rem"></i>Inaktiv</span>
            <?php endif; ?>
          </td>
          <td class="text-end">
            <div class="btn-group btn-group-sm">
              <a href="javascript:void(0)" class="btn btn-outline-secondary disabled" title="In Entwicklung">
                <i class="bi bi-pencil"></i>
              </a>
              <?php if ($u['id'] !== Auth::userId()): ?>
              <form method="POST" class="d-inline">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="toggle">
                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                <button type="submit" class="btn btn-outline-<?= $u['is_active'] ? 'warning' : 'success' ?>"
                        title="<?= $u['is_active'] ? 'Deaktivieren' : 'Aktivieren' ?>">
                  <i class="bi bi-<?= $u['is_active'] ? 'pause-circle' : 'play-circle' ?>"></i>
                </button>
              </form>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ===== Modal: Neuen Benutzer anlegen ===== -->
<div class="modal fade" id="createUserModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header" style="background:var(--wvh-primary);color:#fff">
        <h5 class="modal-title"><i class="bi bi-person-plus me-2"></i>Neuen Benutzer anlegen</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="create">
        <div class="modal-body">
          <div class="row g-3">

            <div class="col-md-6">
              <label class="form-label">Vorname *</label>
              <input type="text" name="first_name" class="form-control" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Nachname *</label>
              <input type="text" name="last_name" class="form-control" required>
            </div>

            <div class="col-md-8">
              <label class="form-label">WvH E-Mail *</label>
              <input type="email" name="email" class="form-control" placeholder="vorname.nachname@wvh-online.com" required>
            </div>
            <div class="col-md-4">
              <label class="form-label">Rolle *</label>
              <select name="role_id" class="form-select" id="roleSelect">
                <?php foreach ($roles as $r): ?>
                <option value="<?= $r['id'] ?>"><?= e($r['label']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="col-md-6">
              <label class="form-label">Passwort * (min. 8 Zeichen)</label>
              <input type="password" name="password" class="form-control" minlength="8" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Aktiv ab</label>
              <input type="date" name="active_from" class="form-control" value="<?= date('Y-m-d') ?>">
            </div>

            <!-- Lehrerfelder (nur wenn Rolle=Lehrer) -->
            <div id="teacherFields" class="col-12">
              <hr>
              <h6 class="text-muted mb-3"><i class="bi bi-person-badge me-1"></i>Lehrerdaten</h6>
              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label">Beschäftigungsart</label>
                  <select name="employment_type" class="form-select">
                    <option value="honorar">Honorarkraft</option>
                    <option value="festangestellt">Festangestellt</option>
                  </select>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Stundensatz (€/UE)</label>
                  <div class="input-group">
                    <span class="input-group-text">€</span>
                    <input type="number" name="hourly_rate" class="form-control" step="0.01" min="0" placeholder="0.00">
                  </div>
                </div>
              </div>
            </div>

          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Abbrechen</button>
          <button type="submit" class="btn btn-wvh">
            <i class="bi bi-person-check me-2"></i>Benutzer anlegen
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php
$extraScript = <<<JS
<script>
// Lehrerfelder nur bei Rolle=Lehrer anzeigen
const roleSelect = document.getElementById('roleSelect');
const teacherFields = document.getElementById('teacherFields');
roleSelect.addEventListener('change', () => {
  teacherFields.style.display = roleSelect.value == 3 ? '' : 'none';
});
roleSelect.dispatchEvent(new Event('change'));

// Live-Suche
initTableSearch('userSearch', 'userTable');
</script>
JS;
include TEMPLATE_PATH . '/layouts/footer.php';
?>
