<?php
/**
 * WvH – Passwort ändern
 */
require_once __DIR__ . '/../config/bootstrap.php';

use Auth\Auth;

Auth::require();

$pageTitle   = 'Passwort ändern';
$breadcrumbs = ['Mein Profil' => APP_URL . '/profile/index.php', 'Passwort ändern' => null];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $auth   = new Auth();
    $result = $auth->changePassword(
        Auth::userId(),
        $_POST['current_password'] ?? '',
        $_POST['new_password'] ?? ''
    );
    if ($result['success']) {
        setFlash('success', 'Dein Passwort wurde erfolgreich geändert.');
        header('Location: ' . APP_URL . '/profile/index.php');
        exit;
    } else {
        setFlash('error', $result['error']);
    }
}

include TEMPLATE_PATH . '/layouts/header.php';
?>

<div class="row justify-content-center">
  <div class="col-md-5">
    <div class="card wvh-card">
      <div class="card-header">
        <div class="card-icon bg-warning bg-opacity-10 text-warning"><i class="bi bi-key-fill"></i></div>
        Passwort ändern
      </div>
      <div class="card-body">
        <form method="POST" data-dirty-check>
          <?= csrfField() ?>
          <div class="mb-3">
            <label class="form-label">Aktuelles Passwort</label>
            <input type="password" name="current_password" class="form-control" required autocomplete="current-password">
          </div>
          <div class="mb-3">
            <label class="form-label">Neues Passwort</label>
            <input type="password" name="new_password" id="newPw" class="form-control" minlength="8" required autocomplete="new-password">
            <div class="form-text">Mindestens 8 Zeichen</div>
          </div>
          <div class="mb-4">
            <label class="form-label">Neues Passwort bestätigen</label>
            <input type="password" name="confirm_password" id="confirmPw" class="form-control" required autocomplete="new-password">
          </div>
          <button type="submit" class="btn btn-wvh w-100">
            <i class="bi bi-lock-fill me-2"></i>Passwort speichern
          </button>
        </form>
      </div>
    </div>
  </div>
</div>

<?php
$extraScript = <<<JS
<script>
document.querySelector('form').addEventListener('submit', e => {
  if (document.getElementById('newPw').value !== document.getElementById('confirmPw').value) {
    e.preventDefault();
    alert('Die Passwörter stimmen nicht überein!');
  }
});
</script>
JS;
include TEMPLATE_PATH . '/layouts/footer.php';
?>
