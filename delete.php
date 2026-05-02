<?php // delete.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
session_start();
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: index.php'); exit; }
csrf_verify();
$user = current_user();
$id   = (int)($_POST['id'] ?? 0);
$stmt = db()->prepare("SELECT user_id FROM videos WHERE id = ?");
$stmt->execute([$id]);
$v = $stmt->fetch();
if ($v && $v['user_id'] == $user['id']) {
    db()->prepare("DELETE FROM videos WHERE id = ?")->execute([$id]);
}
header('Location: index.php?tab=mine');
exit;
