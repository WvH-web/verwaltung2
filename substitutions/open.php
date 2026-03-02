<?php
/**
 * WvH – Offene Vertretungen
 * Übersicht aller Vertretungen
 */
require_once __DIR__ . '/../config/bootstrap.php';
use Auth\Auth;
Auth::require();

$pageTitle   = 'Offene Vertretungen';
$breadcrumbs = ['Vertretungsplanung' => APP_URL.'/substitutions/index.php', 'Offene Vertretungen' => null];
$db = getDB();

$myTeacherId = null;
$s = $db->prepare("SELECT id FROM teachers WHERE user_id=? LIMIT 1");
$s->execute([Auth::userId()]);
$myTeacherId = $s->fetchColumn() ?: null;

$filter  = in_array($_GET['filter'] ?? '', ['open','pending','conflict','confirmed','all'])
           ? $_GET['filter'] : 'open';

$where = match($filter) {
    'open'      => "s.status = 'open'",
    'pending'   => "s.status = 'pending_confirm'",
    'conflict'  => "s.status IN ('conflict','pending_confirm')",
    'confirmed' => "s.status = 'confirmed'",
    'all'       => "s.status NOT IN ('cancelled','locked')",
    default     => "s.status = 'open'",
};

// Columns self_assigned_by and notes may not exist yet – use safe query
$hasSelfAssigned = false;
try {
    $db->query("SELECT self_assigned_by FROM substitutions LIMIT 0");
    $hasSelfAssigned = true;
} catch (\Exception $e) {}

$extraSelect = $hasSelfAssigned
    ? ", s.self_assigned_by, s.notes AS sub_notes"
    : ", NULL AS self_assigned_by, NULL AS sub_notes";

$subs = $db->query(
    "SELECT s.id, s.status, s.released_at, s.claimed_at, s.confirmed_at
            $extraSelect,
            li.lesson_date, te.time_start, te.time_end, te.weekday,
            sub_e.name AS subject_name,
            CONCAT(uo.first_name,' ',uo.last_name) AS original_name,
            t_orig.id AS original_teacher_id,
            CONCAT(us.first_name,' ',us.last_name) AS substitute_name,
            GROUP_CONCAT(DISTINCT c.name ORDER BY c.name SEPARATOR ', ') AS classes
     FROM substitutions s
     JOIN lesson_instances li ON li.id = s.lesson_id
     JOIN timetable_entries te ON te.id = li.entry_id
     JOIN subjects sub_e ON sub_e.id = te.subject_id
     JOIN teachers t_orig ON t_orig.id = s.original_teacher_id
     JOIN users uo ON uo.id = t_orig.user_id
     LEFT JOIN teachers t_sub ON t_sub.id = s.substitute_teacher_id
     LEFT JOIN users us ON us.id = t_sub.user_id
     LEFT JOIN timetable_entry_classes tec ON tec.entry_id = te.id
     LEFT JOIN classes c ON c.id = tec.class_id
     WHERE $where
     GROUP BY s.id
     ORDER BY li.lesson_date, te.time_start"
)->fetchAll();

// Zähler
$counts = $db->query(
    "SELECT status, COUNT(*) FROM substitutions
     WHERE status NOT IN ('cancelled','locked')
     GROUP BY status"
)->fetchAll(\PDO::FETCH_KEY_PAIR);

$dayNames = [1=>'Mo',2=>'Di',3=>'Mi',4=>'Do',5=>'Fr'];

include TEMPLATE_PATH . '/layouts/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <h3 class="fw-bold mb-0" style="color:var(--wvh-primary)">
    <i class="bi bi-list-check me-2"></i>Vertretungen
  </h3>
  <a href="<?= APP_URL ?>/substitutions/index.php" class="btn btn-sm btn-wvh-outline">
    <i class="bi bi-calendar-week me-1"></i>Wochenansicht
  </a>
</div>

<ul class="nav nav-tabs mb-3">
  <?php foreach ([
    'open'      => ['Offen',        $counts['open']            ?? 0, 'warning'],
    'pending'   => ['Warte auf OK', $counts['pending_confirm'] ?? 0, 'warning'],
    'conflict'  => ['Konflikte',    $counts['conflict']        ?? 0, 'danger'],
    'confirmed' => ['Bestätigt',    null, 'success'],
    'all'       => ['Alle',         null, 'secondary'],
  ] as $val => [$label, $cnt, $bc]): ?>
  <li class="nav-item">
    <a class="nav-link <?= $filter===$val?'active':'' ?>" href="?filter=<?=$val?>">
      <?= $label ?>
      <?php if ($cnt): ?><span class="badge bg-<?=$bc?> ms-1"><?=$cnt?></span><?php endif; ?>
    </a>
  </li>
  <?php endforeach; ?>
</ul>

<?php if (empty($subs)): ?>
<div class="card wvh-card">
  <div class="card-body text-center py-5 text-muted">
    <i class="bi bi-check-circle-fill text-success fs-1 d-block mb-3"></i>
    <h5>Keine Einträge für diesen Filter</h5>
  </div>
</div>
<?php else: ?>
<div class="card wvh-card">
  <div class="table-responsive">
    <table class="wvh-table">
      <thead>
        <tr>
          <th>Datum</th><th>Zeit</th><th>Fach / Klassen</th>
          <th>Lehrkraft</th><th>Vertreter</th><th>Status</th>
          <th class="text-end pe-3">Aktion</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($subs as $sub):
          $isPast = $sub['lesson_date'] < date('Y-m-d');
        ?>
        <tr class="<?= $isPast?'text-muted':'' ?>">
          <td class="fw-medium text-nowrap">
            <?= ($dayNames[(int)$sub['weekday']] ?? '') ?>,
            <?= date('d.m.Y', strtotime($sub['lesson_date'])) ?>
          </td>
          <td class="text-nowrap small">
            <?= substr($sub['time_start'],0,5) ?>–<?= substr($sub['time_end'],0,5) ?>
          </td>
          <td>
            <div class="fw-semibold"><?= e($sub['subject_name']) ?></div>
            <?php if ($sub['classes']): ?>
            <div class="small text-muted"><?= e($sub['classes']) ?></div>
            <?php endif; ?>
            <?php if ($sub['sub_notes']): ?>
            <div class="small text-muted fst-italic">„<?= e($sub['sub_notes']) ?>"</div>
            <?php endif; ?>
          </td>
          <td><?= e($sub['original_name']) ?></td>
          <td><?= $sub['substitute_name'] ? e($sub['substitute_name']) : '<span class="text-muted">–</span>' ?></td>
          <td>
            <?php $badges = [
              'open'            => ['bg-warning text-dark','Offen'],
              'pending_confirm' => ['bg-warning','Warte auf OK'],
              'claimed'         => ['bg-info text-dark','Übernommen'],
              'confirmed'       => ['bg-success','Bestätigt'],
              'conflict'        => ['bg-danger','Konflikt'],
              'admin_resolved'  => ['bg-secondary','Gelöst'],
            ];
            [$bc,$bl] = $badges[$sub['status']] ?? ['bg-secondary','?']; ?>
            <span class="badge <?=$bc?> fw-normal"><?=$bl?></span>
          </td>
          <td class="text-end pe-3">
            <?php if ($sub['status']==='open' && !$isPast && $myTeacherId
                   && (int)$sub['original_teacher_id'] !== (int)$myTeacherId): ?>
            <form method="POST" action="<?= APP_URL ?>/substitutions/action.php" class="d-inline">
              <?= csrfField() ?>
              <input type="hidden" name="action" value="claim">
              <input type="hidden" name="sub_id" value="<?=$sub['id']?>">
              <input type="hidden" name="week"   value="0">
              <button type="submit" class="btn btn-sm btn-success">
                <i class="bi bi-hand-index-fill me-1"></i>Übernehmen
              </button>
            </form>
            <?php elseif ($sub['status']==='pending_confirm' && $myTeacherId
                          && (int)$sub['original_teacher_id'] === (int)$myTeacherId): ?>
            <form method="POST" action="<?= APP_URL ?>/substitutions/action.php" class="d-inline">
              <?= csrfField() ?>
              <input type="hidden" name="action" value="confirm">
              <input type="hidden" name="sub_id" value="<?=$sub['id']?>">
              <input type="hidden" name="week"   value="0">
              <button type="submit" class="btn btn-sm btn-success">
                <i class="bi bi-check-lg me-1"></i>Bestätigen
              </button>
            </form>
            <form method="POST" action="<?= APP_URL ?>/substitutions/action.php" class="d-inline ms-1">
              <?= csrfField() ?>
              <input type="hidden" name="action" value="reject">
              <input type="hidden" name="sub_id" value="<?=$sub['id']?>">
              <input type="hidden" name="week"   value="0">
              <button type="submit" class="btn btn-sm btn-outline-danger">
                <i class="bi bi-x-lg"></i> Ablehnen
              </button>
            </form>
            <?php elseif (Auth::isVerwaltung() && in_array($sub['status'],['pending_confirm','conflict'])): ?>
            <form method="POST" action="<?= APP_URL ?>/substitutions/action.php" class="d-inline">
              <?= csrfField() ?>
              <input type="hidden" name="action" value="admin_confirm">
              <input type="hidden" name="sub_id" value="<?=$sub['id']?>">
              <input type="hidden" name="week"   value="0">
              <button type="submit" class="btn btn-sm btn-wvh">
                <i class="bi bi-shield-check me-1"></i>Admin-OK
              </button>
            </form>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php include TEMPLATE_PATH . '/layouts/footer.php'; ?>
