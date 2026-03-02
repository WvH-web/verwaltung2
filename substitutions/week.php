<?php
// Redirect → index.php (vollständige Wochenansicht)
require_once __DIR__ . '/../config/bootstrap.php';
$p = '?';
foreach (['mode','id','w'] as $k) {
    if (isset($_GET[$k])) {
        // map old param 'id' to class_id or teacher_id based on mode
        $mode = $_GET['mode'] ?? 'klasse';
        if ($k === 'id') {
            $mapped = ($mode === 'lehrer') ? 'teacher_id' : 'class_id';
            $p .= $mapped . '=' . (int)$_GET[$k] . '&';
        } elseif ($k === 'w') {
            $p .= 'week=' . (int)$_GET[$k] . '&';
        } else {
            $p .= urlencode($k) . '=' . urlencode($_GET[$k]) . '&';
        }
    }
}
header('Location: ' . APP_URL . '/substitutions/index.php' . rtrim($p, '?&'));
exit;
