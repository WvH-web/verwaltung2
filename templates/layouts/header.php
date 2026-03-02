<?php
/** isActive – muss VOR allen Aufrufen definiert sein */
function isActive(string $section): string {
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    return (strpos($uri, '/' . $section . '/') !== false
         || substr($uri, -(strlen($section) + 4)) === $section . '.php')
        ? 'active' : '';
}
?><!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow">
  <title><?= e($pageTitle ?? 'WvH') ?> – <?= APP_NAME ?></title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/wvh.css">
  <?= $extraHead ?? '' ?>
</head>
<body class="wvh-app">

<nav class="navbar navbar-expand-lg wvh-navbar shadow-sm">
  <div class="container-fluid px-4">

    <a class="navbar-brand d-flex align-items-center gap-2" href="<?= APP_URL ?>/dashboard.php">
      <div class="wvh-brand-icon"><i class="bi bi-building-check"></i></div>
      <div class="lh-1">
        <span class="fw-bold">WvH</span>
        <small class="d-block text-muted" style="font-size:.65rem;letter-spacing:.05em">ABRECHNUNGSSYSTEM</small>
      </div>
    </a>

    <button class="navbar-toggler border-0" type="button"
            data-bs-toggle="collapse" data-bs-target="#mainNav"
            aria-controls="mainNav" aria-expanded="false">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="mainNav">
      <ul class="navbar-nav me-auto gap-1 ms-3">

        <li class="nav-item">
          <a class="nav-link <?= isActive('dashboard') ?>" href="<?= APP_URL ?>/dashboard.php">
            <i class="bi bi-grid-1x2-fill me-1"></i>Dashboard
          </a>
        </li>

        <!-- Vertretungen – für alle sichtbar -->
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle <?= isActive('substitutions') ?>"
             href="javascript:;" role="button" data-bs-toggle="dropdown" aria-expanded="false">
            <i class="bi bi-arrow-left-right me-1"></i>Vertretungen
          </a>
          <ul class="dropdown-menu shadow border-0">
            <li><a class="dropdown-item" href="<?= APP_URL ?>/substitutions/week.php">
              <i class="bi bi-calendar-week me-2"></i>Wochenplan</a></li>
            <li><a class="dropdown-item" href="<?= APP_URL ?>/substitutions/open.php">
              <i class="bi bi-list-task me-2"></i>Offene Vertretungen</a></li>
            <?php if (\Auth\Auth::isVerwaltung()): ?>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item" href="<?= APP_URL ?>/substitutions/manage.php">
              <i class="bi bi-shield-check me-2"></i>Verwaltung</a></li>
            <?php endif; ?>
          </ul>
        </li>

        <?php if (\Auth\Auth::isVerwaltung()): ?>
        <!-- Deputate -->
        <li class="nav-item">
          <a class="nav-link <?= isActive('deputate') ?>" href="<?= APP_URL ?>/deputate/assignments.php">
            <i class="bi bi-award-fill me-1"></i>Deputate
          </a>
        </li>
        <?php endif; ?>

        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle <?= isActive('timetable') ?>"
             href="javascript:;" role="button" data-bs-toggle="dropdown" aria-expanded="false">
            <i class="bi bi-grid-3x3-gap me-1"></i>Stundenplan
          </a>
          <ul class="dropdown-menu shadow border-0">
            <li><a class="dropdown-item" href="<?= APP_URL ?>/timetable/view.php?mode=klasse">
              <i class="bi bi-people me-2"></i>Klassenplan</a></li>
            <li><a class="dropdown-item" href="<?= APP_URL ?>/timetable/view.php?mode=lehrer">
              <i class="bi bi-person-workspace me-2"></i>Lehrerplan</a></li>
            <?php if (\Auth\Auth::isVerwaltung()): ?>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item" href="<?= APP_URL ?>/timetable/import.php">
              <i class="bi bi-upload me-2"></i>CSV Import</a></li>
            <?php endif; ?>
          </ul>
        </li>

        <?php if (\Auth\Auth::isAdmin()): ?>
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle <?= isActive('admin') ?>"
             href="javascript:;" role="button" data-bs-toggle="dropdown" aria-expanded="false">
            <i class="bi bi-shield-fill-gear me-1"></i>Admin
          </a>
          <ul class="dropdown-menu shadow border-0">
            <li><a class="dropdown-item" href="<?= APP_URL ?>/admin/users.php">
              <i class="bi bi-people-fill me-2"></i>Benutzer</a></li>
            <li><a class="dropdown-item" href="<?= APP_URL ?>/admin/teachers.php">
              <i class="bi bi-person-badge me-2"></i>Lehrkräfte</a></li>
            <li><a class="dropdown-item" href="<?= APP_URL ?>/admin/teachers_import.php">
              <i class="bi bi-upload me-2"></i>Lehrer CSV-Import</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item" href="<?= APP_URL ?>/admin/holidays.php">
              <i class="bi bi-calendar-heart me-2"></i>Ferien &amp; Feiertage</a></li>
            <li><a class="dropdown-item" href="<?= APP_URL ?>/admin/special_events.php">
              <i class="bi bi-star me-2"></i>Sonderveranstaltungen</a></li>
          </ul>
        </li>
        <?php endif; ?>

      </ul>

      <ul class="navbar-nav ms-auto">
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle d-flex align-items-center gap-2"
             href="javascript:;" role="button" data-bs-toggle="dropdown" aria-expanded="false">
            <div class="wvh-avatar"><?= strtoupper(substr(\Auth\Auth::userFirstName(), 0, 1)) ?></div>
            <span class="d-none d-lg-inline"><?= e(\Auth\Auth::userName()) ?></span>
          </a>
          <ul class="dropdown-menu dropdown-menu-end shadow border-0">
            <li><div class="dropdown-header">
              <small class="text-muted"><?= e(\Auth\Auth::roleName()) ?></small><br>
              <strong><?= e(\Auth\Auth::userName()) ?></strong>
            </div></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item" href="<?= APP_URL ?>/profile/index.php">
              <i class="bi bi-person-fill me-2"></i>Mein Profil</a></li>
            <li><a class="dropdown-item" href="<?= APP_URL ?>/profile/change-password.php">
              <i class="bi bi-key-fill me-2"></i>Passwort ändern</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item text-danger" href="<?= APP_URL ?>/logout.php">
              <i class="bi bi-box-arrow-right me-2"></i>Abmelden</a></li>
          </ul>
        </li>
      </ul>
    </div>
  </div>
</nav>

<main class="wvh-main">
  <div class="container-fluid px-4 py-4">

    <?php if (!empty($breadcrumbs)): ?>
    <nav aria-label="breadcrumb" class="mb-3">
      <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item">
          <a href="<?= APP_URL ?>/dashboard.php"><i class="bi bi-house"></i></a>
        </li>
        <?php foreach ($breadcrumbs as $label => $url): ?>
          <?php if ($url): ?>
            <li class="breadcrumb-item"><a href="<?= e($url) ?>"><?= e($label) ?></a></li>
          <?php else: ?>
            <li class="breadcrumb-item active"><?= e($label) ?></li>
          <?php endif; ?>
        <?php endforeach; ?>
      </ol>
    </nav>
    <?php endif; ?>

    <?= renderFlash() ?>

