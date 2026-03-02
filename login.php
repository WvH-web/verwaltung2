<?php
/**
 * WvH – Login-Seite
 */
require_once __DIR__ . '/config/bootstrap.php';

use Auth\Auth;

// Bereits eingeloggt? → Dashboard
if (Auth::check()) {
    header('Location: ' . APP_URL . '/dashboard.php');
    exit;
}

$error   = '';
$email   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF-Schutz
    if (!Auth::verifyCsrf($_POST['csrf_token'] ?? '')) {
        $error = 'Ungültige Anfrage. Bitte lade die Seite neu.';
    } else {
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        $auth   = new Auth();
        $result = $auth->login($email, $password);

        if ($result['success']) {
            $redirect = $_GET['redirect'] ?? '';
            // Sicherheitscheck: Nur interne URLs
            if ($redirect && (strpos(urldecode($redirect), '/') === 0)) {
                header('Location: ' . urldecode($redirect));
            } else {
                header('Location: ' . APP_URL . '/dashboard.php');
            }
            exit;
        } else {
            $error = $result['error'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow">
  <title>Anmelden – <?= APP_NAME ?></title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/wvh.css">
</head>
<body class="wvh-login-bg">

<div class="wvh-login-card">

  <!-- Logo -->
  <div class="wvh-login-logo">
    <i class="bi bi-building-check"></i>
  </div>

  <!-- Titel -->
  <h1 class="text-center mb-1 fw-bold" style="color:var(--wvh-primary);font-size:1.4rem;">
    <?= APP_NAME ?>
  </h1>
  <p class="text-center text-muted mb-4 small">Wilhelm von Humboldt Online Privatschule</p>

  <!-- Fehler-Anzeige -->
  <?php if ($error): ?>
  <div class="alert alert-danger d-flex align-items-center gap-2 mb-4" role="alert">
    <i class="bi bi-exclamation-triangle-fill"></i>
    <span><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></span>
  </div>
  <?php endif; ?>

  <!-- Login-Formular -->
  <form method="POST" action="<?= APP_URL ?>/login.php<?= isset($_GET['redirect']) ? '?redirect=' . urlencode($_GET['redirect']) : '' ?>" novalidate>
    <input type="hidden" name="csrf_token" value="<?php
      // CSRF-Token für Login-Seite (vor dem Session-Login)
      if (empty($_SESSION['csrf_token'])) {
          $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
      }
      echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8');
    ?>">

    <!-- E-Mail -->
    <div class="mb-3">
      <label for="email" class="form-label">
        <i class="bi bi-envelope me-1"></i>WvH E-Mail Adresse
      </label>
      <div class="wvh-input-icon">
        <i class="bi bi-envelope icon"></i>
        <input
          type="email"
          class="form-control form-control-lg <?= $error ? 'is-invalid' : '' ?>"
          id="email"
          name="email"
          value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>"
          placeholder="vorname.nachname@wvh-online.com"
          autocomplete="email"
          required
          autofocus
        >
      </div>
    </div>

    <!-- Passwort -->
    <div class="mb-4">
      <div class="d-flex justify-content-between">
        <label for="password" class="form-label">
          <i class="bi bi-lock me-1"></i>Passwort
        </label>
      </div>
      <div class="wvh-input-icon">
        <i class="bi bi-lock icon"></i>
        <input
          type="password"
          class="form-control form-control-lg <?= $error ? 'is-invalid' : '' ?>"
          id="password"
          name="password"
          placeholder="Dein Passwort"
          autocomplete="current-password"
          required
        >
        <button type="button" class="btn btn-sm btn-link position-absolute end-0 top-50 translate-middle-y pe-3 text-muted"
                onclick="togglePassword()" tabindex="-1">
          <i class="bi bi-eye" id="pwEye"></i>
        </button>
      </div>
    </div>

    <!-- Submit -->
    <button type="submit" class="btn btn-wvh w-100 btn-lg fw-semibold">
      <i class="bi bi-box-arrow-in-right me-2"></i>Anmelden
    </button>

  </form>

  <!-- Footer -->
  <p class="text-center text-muted small mt-4 mb-0">
    Probleme beim Login?<br>
    Bitte wende dich an den <strong>Administrator</strong>.
  </p>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function togglePassword() {
  const pw  = document.getElementById('password');
  const eye = document.getElementById('pwEye');
  if (pw.type === 'password') {
    pw.type = 'text';
    eye.className = 'bi bi-eye-slash';
  } else {
    pw.type = 'password';
    eye.className = 'bi bi-eye';
  }
}
// Enter-Taste → Submit
document.getElementById('email').addEventListener('keydown', e => {
  if (e.key === 'Enter') document.getElementById('password').focus();
});
</script>
</body>
</html>
