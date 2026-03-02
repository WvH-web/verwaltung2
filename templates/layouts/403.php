<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Zugriff verweigert – WvH Abrechnungssystem</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="bg-light d-flex align-items-center justify-content-center" style="min-height:100vh">
  <div class="text-center p-4">
    <div style="font-size:5rem;color:#dc3545"><i class="bi bi-shield-x"></i></div>
    <h1 class="fw-bold mt-3">Zugriff verweigert</h1>
    <p class="text-muted">Du hast keine Berechtigung für diesen Bereich.</p>
    <a href="<?= APP_URL ?>/dashboard.php" class="btn btn-primary mt-2">
      <i class="bi bi-house me-2"></i>Zurück zum Dashboard
    </a>
  </div>
</body>
</html>
