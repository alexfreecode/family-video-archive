<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
session_start();
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }
$user = current_user();
if (!$user) { session_destroy(); header('Location: login.php'); exit; }

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: events.php'); exit; }

$media = get_media_item($id);
if (!$media || ($media['user_id'] != $user['id'] && !$user['is_admin'])) {
    header('Location: events.php'); exit;
}

// Удаляем превью с диска
if ($media['thumbnail']) {
    $path = __DIR__ . '/' . ltrim($media['thumbnail'], '/');
    if (file_exists($path)) unlink($path);
}

$event_id = $media['event_id'];
db()->prepare("DELETE FROM media WHERE id=?")->execute([$id]);
header('Location: event.php?id=' . $event_id);
exit;
