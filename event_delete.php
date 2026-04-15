<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
session_start();
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }
$user = current_user();
if (!$user) { session_destroy(); header('Location: login.php'); exit; }

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: events.php'); exit; }

$event = get_event($id);
if (!$event || ($event['user_id'] != $user['id'] && !$user['is_admin'])) {
    header('Location: events.php'); exit;
}

// Удаляем превью события с диска
if ($event['thumbnail']) {
    $path = __DIR__ . '/' . ltrim($event['thumbnail'], '/');
    if (file_exists($path)) unlink($path);
}

// Удаляем превью всех медиа события с диска
$media_list = db()->prepare("SELECT thumbnail FROM media WHERE event_id = ?");
$media_list->execute([$id]);
foreach ($media_list->fetchAll() as $m) {
    if ($m['thumbnail']) {
        $path = __DIR__ . '/' . ltrim($m['thumbnail'], '/');
        if (file_exists($path)) unlink($path);
    }
}

// Удаляем событие (каскадно удалятся media, event_comments, event_access)
db()->prepare("DELETE FROM events WHERE id = ?")->execute([$id]);

header('Location: events.php');
exit;
