<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
session_start();
if (!isset($_SESSION['user_id'])) {
    if (!check_remember_token()) { header('Location: login.php'); exit; }
}
$user = current_user();
if (!$user) { session_destroy(); header('Location: login.php'); exit; }

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: events.php'); exit; }

$event = get_event($id);
if (!$event) { header('Location: events.php'); exit; }

// Проверяем доступ
$has_access = ($event['user_id'] == $user['id'])
    || (function() use ($id, $user) {
        $s = db()->prepare("SELECT COUNT(*) FROM event_access WHERE event_id=? AND (user_id=0 OR user_id=?)");
        $s->execute([$id, $user['id']]);
        return $s->fetchColumn() > 0;
    })();
if (!$has_access) { header('Location: events.php'); exit; }

$videos   = get_event_videos($id, $user['id']);
$media    = get_event_media($id, $user['id']);
$comments = get_event_comments($id);

// Обработка комментариев
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    if (isset($_POST['comment_text'])) {
        $text = trim($_POST['comment_text']);
        if ($text) {
            add_event_comment($id, $user['id'], $text);
            telegram_notify_event_comment($id, $user['id'], $text);
        }
    }
    if (isset($_POST['delete_comment_id'])) {
        delete_event_comment((int)$_POST['delete_comment_id'], $user['id'], $user['is_admin']);
    }
    header('Location: event.php?id=' . $id . '#comments');
    exit;
}

require_once __DIR__ . '/layout.php';
layout_head(h($event['title']), false);
?>

<?php layout_nav($user, 'events'); ?>

<div class="main" style="max-width:960px">
  <div class="sec-header">
    <a href="events.php" class="btn-outline"><?= h(t('event_back')) ?></a>
    <div style="display:flex;gap:0.5rem">
      <?php if ($event['user_id'] == $user['id']): ?>
      <a href="event_edit.php?id=<?= $id ?>" class="btn-outline"><?= h(t('event_edit')) ?></a>
      <form method="POST" action="event_delete.php" style="display:inline"
            onsubmit="return confirm('<?= h(sprintf(t('event_delete_confirm'), addslashes($event['title']))) ?>')">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= $id ?>">
        <button type="submit" class="btn-danger-sm" style="display:flex;align-items:center"><?= h(t('video_delete')) ?></button>
      </form>
      <?php endif; ?>
      <a href="media_add.php?event_id=<?= $id ?>" class="btn-gold"><?= h(t('event_add_media')) ?></a>
    </div>
  </div>

  <!-- Шапка события -->
  <div style="margin-bottom:1.5rem">
    <div>
      <h1 style="font-size:1.4rem;font-weight:700;color:var(--text);margin:0 0 0.5rem"><?= h($event['title']) ?></h1>
      <?php if ($event['event_date']): ?>
        <div style="font-size:0.85rem;color:var(--text-muted);margin-bottom:0.3rem">📅 <?= date('d.m.Y', strtotime($event['event_date'])) ?></div>
      <?php endif; ?>
      <?php if ($event['description']): ?>
        <div style="font-size:0.85rem;color:var(--text);line-height:1.5"><?= h($event['description']) ?></div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Видео события -->
  <?php if (!empty($videos)): ?>
  <div style="margin-bottom:2rem">
    <div style="font-size:0.75rem;text-transform:uppercase;letter-spacing:0.1em;color:var(--text-muted);font-weight:600;margin-bottom:1rem">
      <?= h(t('event_section_videos')) ?> — <?= count($videos) ?>
    </div>
    <div class="video-grid">
      <?php foreach ($videos as $v): ?>
      <div class="vcard">
        <div class="vthumb" onclick="location.href='video.php?id=<?= $v['id'] ?>'" style="cursor:pointer">
          <img src="https://img.youtube.com/vi/<?= h($v['youtube_id']) ?>/mqdefault.jpg"
               alt="<?= h($v['title']) ?>" loading="lazy"
               onerror="this.src='https://img.youtube.com/vi/<?= h($v['youtube_id']) ?>/0.jpg'">
          <div class="play-overlay">
            <div class="play-circle">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="white"><path d="M8 5v14l11-7z"/></svg>
            </div>
          </div>
        </div>
        <div class="vinfo">
          <div class="vtitle"><?= h($v['title']) ?></div>
          <?php if ($v['filmed_at']): ?>
          <div class="vchips"><span class="vchip">📅 <?= date('d.m.Y', strtotime($v['filmed_at'])) ?></span></div>
          <?php endif; ?>
          <div class="vmeta" style="margin-top:0.4rem">
            <div class="vauthor">
              <div class="avatar av" style="background:<?= h($v['author_color']) ?>"><?= h(initials($v['author_name'])) ?></div>
              <span class="name"><?= h($v['author_name']) ?></span>
            </div>
          </div>
          <div class="vactions">
            <a href="video.php?id=<?= $v['id'] ?>" class="btn-gold" style="font-size:0.7rem;padding:0.25rem 0.8rem"><?= h(t('video_details')) ?></a>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- Медиа события -->
  <?php if (!empty($media)): ?>
  <div style="margin-bottom:2rem">
    <div style="font-size:0.75rem;text-transform:uppercase;letter-spacing:0.1em;color:var(--text-muted);font-weight:600;margin-bottom:1rem">
      <?= h(t('event_section_media')) ?> — <?= count($media) ?>
    </div>
    <div class="video-grid">
      <?php foreach ($media as $m): ?>
      <div class="vcard">
        <a href="<?= h($m['url']) ?>" target="_blank" style="text-decoration:none;display:block">
          <div class="vthumb" style="background:var(--surface2)">
            <?php if ($m['thumbnail']): ?>
              <img src="<?= h($m['thumbnail']) ?>" alt="" style="width:100%;height:100%;object-fit:cover">
            <?php else: ?>
              <div style="width:100%;height:100%;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:0.4rem">
                <span style="font-size:2.5rem"><?= $m['type'] === 'photo' ? '🖼' : ($m['type'] === 'album' ? '📁' : '🔗') ?></span>
                <span style="font-size:0.7rem;color:var(--text-muted)"><?= $m['type'] === 'photo' ? h(t('media_type_photo')) : ($m['type'] === 'album' ? h(t('media_type_album')) : h(t('media_type_link'))) ?></span>
              </div>
            <?php endif; ?>
          </div>
        </a>
        <div class="vinfo">
          <div class="vtitle"><?= h($m['title'] ?: ($m['type'] === 'photo' ? t('media_type_photo') : ($m['type'] === 'album' ? t('media_type_album') : t('media_type_link')))) ?></div>
          <?php if ($m['description']): ?>
          <div class="vdesc"><?= h($m['description']) ?></div>
          <?php endif; ?>
          <div class="vmeta" style="margin-top:0.4rem">
            <div class="vauthor">
              <div class="avatar av" style="background:<?= h($m['author_color']) ?>"><?= h(initials($m['author_name'])) ?></div>
              <span class="name"><?= h($m['author_name']) ?></span>
            </div>
          </div>
          <div class="vactions">
            <a href="<?= h($m['url']) ?>" target="_blank" class="btn-gold" style="font-size:0.7rem;padding:0.25rem 0.8rem"><?= h(t('media_open')) ?></a>
            <?php if ($m['user_id'] == $user['id']): ?>
            <a href="media_edit.php?id=<?= $m['id'] ?>" class="btn-outline" style="font-size:0.7rem;padding:0.25rem 0.6rem"><?= h(t('video_edit')) ?></a>
            <form method="POST" action="media_delete.php" style="display:inline"
                  onsubmit="return confirm('<?= h(t('media_delete_confirm')) ?>')">
              <?= csrf_field() ?>
              <input type="hidden" name="id" value="<?= $m['id'] ?>">
              <button type="submit" class="btn-danger-sm"><?= h(t('video_delete')) ?></button>
            </form>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <?php if (empty($videos) && empty($media)): ?>
  <div class="empty">
    <div class="icon">📭</div>
    <p><?= h(t('event_no_media')) ?></p>
  </div>
  <?php endif; ?>

  <!-- Комментарии к событию -->
  <div id="comments">
    <div style="font-size:0.75rem;text-transform:uppercase;letter-spacing:0.1em;color:var(--text-muted);font-weight:600;margin-bottom:1rem">
      <?= h(t('event_comments')) ?> <?php if (!empty($comments)): ?><span style="color:var(--text)"><?= count($comments) ?></span><?php endif; ?>
    </div>

    <?php if (!empty($comments)): ?>
    <div style="display:flex;flex-direction:column;gap:0.7rem;margin-bottom:1.2rem">
      <?php foreach ($comments as $c): ?>
      <div style="background:var(--surface);border:1px solid var(--border);border-radius:4px;padding:0.9rem 1rem">
        <div style="display:flex;align-items:center;gap:0.6rem;margin-bottom:0.5rem">
          <div class="avatar" style="background:<?= h($c['user_color']) ?>;width:26px;height:26px;font-size:0.6rem;flex-shrink:0"><?= h(initials($c['user_name'])) ?></div>
          <span style="font-size:0.83rem;font-weight:600;color:var(--text)"><?= h($c['user_name']) ?></span>
          <span style="font-size:0.72rem;color:var(--text-muted);margin-left:auto"><?= date('d.m.Y H:i', strtotime($c['created_at'])) ?></span>
          <?php if ($c['user_id'] == $user['id'] || $user['is_admin']): ?>
          <form method="POST" style="display:inline" onsubmit="return confirm('<?= h(t('comment_delete_confirm')) ?>')">
            <?= csrf_field() ?>
            <input type="hidden" name="delete_comment_id" value="<?= $c['id'] ?>">
            <button type="submit" style="background:none;border:none;color:var(--text-muted);cursor:pointer;font-size:0.75rem;padding:0;line-height:1">✕</button>
          </form>
          <?php endif; ?>
        </div>
        <div style="font-size:0.87rem;color:var(--text);line-height:1.5;white-space:pre-wrap"><?= h($c['text']) ?></div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <form method="POST">
      <?= csrf_field() ?>
      <div style="display:flex;gap:0.6rem;align-items:flex-start">
        <div class="avatar" style="background:<?= h($user['color']) ?>;width:32px;height:32px;font-size:0.7rem;flex-shrink:0;margin-top:2px"><?= h(initials($user['display_name'])) ?></div>
        <div style="flex:1">
          <textarea name="comment_text" class="form-control" rows="2"
                    placeholder="<?= h(t('comment_placeholder')) ?>" style="resize:vertical;min-height:60px"></textarea>
          <button type="submit" class="btn-gold" style="margin-top:0.5rem;font-size:0.75rem;padding:0.4rem 1rem"><?= h(t('comment_submit')) ?></button>
        </div>
      </div>
    </form>
  </div>
</div>

<?php layout_foot(); ?>
