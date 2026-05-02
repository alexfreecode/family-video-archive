<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
session_start();
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }

$current = current_user();
$member_id = (int)($_GET['id'] ?? 0);

if (!$member_id) { header('Location: index.php'); exit; }

// Получаем данные участника
$stmt = db()->prepare("SELECT id, display_name, color FROM users WHERE id = ?");
$stmt->execute([$member_id]);
$member = $stmt->fetch();

if (!$member) { header('Location: index.php'); exit; }

$videos = get_member_videos($member_id, $current['id']);

require_once __DIR__ . '/layout.php';
layout_head(h($member['display_name']), false);
?>

<?php layout_nav($current, ''); ?>

<div class="main">
  <div class="sec-header">
    <div style="display:flex;align-items:center;gap:0.9rem">
      <div class="avatar" style="background:<?= h($member['color']) ?>;width:44px;height:44px;font-size:0.9rem">
        <?= h(initials($member['display_name'])) ?>
      </div>
      <div>
        <div class="sec-title" style="margin:0"><?= h($member['display_name']) ?></div>
        <div style="font-size:0.75rem;color:var(--text-muted);margin-top:2px"><?= sprintf(t('member_avail'), count($videos)) ?></div>
      </div>
    </div>
    <a href="javascript:history.back()" class="btn-outline"><?= h(t('back')) ?></a>
  </div>

  <?php if (empty($videos)): ?>
    <div class="empty">
      <div class="icon">🎬</div>
      <p><?= h(t('member_no_videos')) ?> <?= h($member['display_name']) ?>.</p>
    </div>
  <?php else: ?>
    <div class="video-grid">
      <?php foreach ($videos as $v):
        $meta_str = $v['is_public'] ? t('member_pub') : t('member_limited');
      ?>
      <div class="vcard">
        <div class="vthumb" onclick="watchVideo('<?= h($v['youtube_id']) ?>','<?= h(addslashes($v['title'])) ?>','<?= h(addslashes($meta_str)) ?>')">
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
          <?php
            $chips = [];
            if (!empty($v['filmed_at'])) $chips[] = '📅 ' . date('d.m.Y', strtotime($v['filmed_at']));
            if (!empty($v['location']))  $chips[] = '📍 ' . h($v['location']);
            if (!empty($v['event']))     $chips[] = '🎉 ' . h($v['event']);
          ?>
          <?php if ($chips): ?>
          <div class="vchips">
            <?php foreach ($chips as $c): ?><span class="vchip"><?= $c ?></span><?php endforeach; ?>
          </div>
          <?php endif; ?>
          <?php if (!empty($v['tags'])): ?>
          <div class="vtags">
            <?php foreach (array_map('trim', explode(',', $v['tags'])) as $tag): ?>
              <?php if ($tag): ?><span class="vtag"><?= h($tag) ?></span><?php endif; ?>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
          <?php if ($v['description']): ?>
            <div class="vdesc"><?= h($v['description']) ?></div>
          <?php endif; ?>
          <div class="vmeta" style="margin-top:0.5rem">
            <a href="https://youtube.com/watch?v=<?= h($v['youtube_id']) ?>" target="_blank"
               class="btn-outline" style="font-size:0.7rem;padding:0.25rem 0.6rem;margin-left:auto">↗ YouTube</a>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<?php layout_foot(); ?>
