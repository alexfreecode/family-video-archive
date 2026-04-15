<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
session_start();
if (!isset($_SESSION['user_id'])) {
    if (!check_remember_token()) { header('Location: login.php'); exit; }
}
$user = current_user();
if (!$user) { session_destroy(); header('Location: login.php'); exit; }

$events = get_events_for_user($user['id']);

require_once __DIR__ . '/layout.php';
layout_head(t('nav_events'), false);
?>

<?php layout_nav($user, 'events'); ?>

<div class="main">
  <div class="sec-header">
    <div class="sec-title"><?= h(t('nav_events')) ?> <small><?= count($events) ?></small></div>
    <a href="event_edit.php" class="btn-gold"><?= h(t('events_add')) ?></a>
  </div>

  <?php if (empty($events)): ?>
    <div class="empty">
      <div class="icon">📅</div>
      <p><?= h(t('events_empty')) ?></p>
    </div>
  <?php else: ?>
    <div class="video-grid">
      <?php foreach ($events as $e): ?>
      <a href="event.php?id=<?= $e['id'] ?>" style="text-decoration:none">
        <div class="vcard">
          <!-- Превью -->
          <div class="vthumb" style="cursor:pointer;background:var(--surface2)">
            <?php if ($e['thumbnail']): ?>
              <img src="<?= h($e['thumbnail']) ?>" alt="<?= h($e['title']) ?>" style="width:100%;height:100%;object-fit:cover">
            <?php else: ?>
              <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;font-size:2.5rem">📅</div>
            <?php endif; ?>
          </div>
          <div class="vinfo">
            <div class="vtitle"><?= h($e['title']) ?></div>
            <?php if ($e['event_date']): ?>
            <div class="vchips">
              <span class="vchip">📅 <?= date('d.m.Y', strtotime($e['event_date'])) ?></span>
            </div>
            <?php endif; ?>
            <div style="font-size:0.75rem;color:var(--text-muted);margin-top:0.3rem">
              🎬 <?= $e['video_count'] ?> <?= h(t('index_videos_count')) ?>
              <?php if ($e['media_count'] > 0): ?> · 🖼 <?= $e['media_count'] ?> <?= h(t('events_media')) ?><?php endif; ?>
            </div>
            <?php if ($e['description']): ?>
              <div class="vdesc"><?= h($e['description']) ?></div>
            <?php endif; ?>
            <div class="vmeta" style="margin-top:0.5rem">
              <div class="vauthor">
                <div class="avatar av" style="background:<?= h($e['author_color']) ?>"><?= h(initials($e['author_name'])) ?></div>
                <span class="name"><?= h($e['author_name']) ?></span>
              </div>
            </div>
          </div>
        </div>
      </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<?php layout_foot(); ?>
