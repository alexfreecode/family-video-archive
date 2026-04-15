<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
session_start();
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }

$user = current_user();
if (!$user) { session_destroy(); header('Location: login.php'); exit; }

$new = get_new_since_prev($user['id']);

require_once __DIR__ . '/layout.php';
layout_head(t('new_title'), false);
?>

<?php layout_nav($user, ''); ?>

<div class="main" style="max-width:740px">
  <div class="sec-header">
    <div class="sec-title"><?= h(t('new_heading')) ?></div>
    <a href="index.php" class="btn-outline"><?= h(t('back')) ?></a>
  </div>

  <?php if (!$new['since']): ?>
    <div class="empty">
      <div class="icon">🔔</div>
      <p><?= h(t('new_first_visit')) ?></p>
    </div>

  <?php elseif (empty($new['videos']) && empty($new['comments']) && empty($new['events']) && empty($new['media']) && empty($new['event_comments'])): ?>
    <div class="empty">
      <div class="icon">🔔</div>
      <p><?= h(t('new_nothing')) ?><br>
        <span style="font-size:0.8rem;color:var(--text-muted)"><?= date('d.m.Y H:i', strtotime($new['since'])) ?></span>
      </p>
    </div>

  <?php else: ?>
    <div style="font-size:0.78rem;color:var(--text-muted);margin-bottom:1.2rem">
      <?= sprintf(t('new_since'), date('d.m.Y H:i', strtotime($new['since']))) ?>
    </div>

    <?php if (!empty($new['videos'])): ?>
    <div class="fcard" style="margin-bottom:1rem">
      <div style="font-size:0.75rem;text-transform:uppercase;letter-spacing:0.1em;color:var(--text-muted);font-weight:600;margin-bottom:1rem">
        <?= sprintf(t('new_videos'), count($new['videos'])) ?>
      </div>
      <div style="display:flex;flex-direction:column;gap:0.6rem">
        <?php foreach ($new['videos'] as $v): ?>
        <a href="video.php?id=<?= $v['id'] ?>" style="display:flex;align-items:center;gap:0.8rem;text-decoration:none;padding:0.5rem;border:1px solid var(--border);border-radius:3px;transition:border-color .15s" onmouseover="this.style.borderColor='var(--gold)'" onmouseout="this.style.borderColor='var(--border)'">
          <img src="https://img.youtube.com/vi/<?= h($v['youtube_id']) ?>/default.jpg"
               style="width:72px;height:41px;object-fit:cover;border-radius:2px;flex-shrink:0">
          <div style="flex:1;min-width:0">
            <div style="font-size:0.85rem;font-weight:600;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= h($v['title']) ?></div>
            <div style="font-size:0.72rem;color:var(--text-muted);margin-top:2px">
              <?= h($v['author_name']) ?> · <?= date('d.m.Y', strtotime($v['created_at'])) ?>
            </div>
          </div>
        </a>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($new['comments'])): ?>
    <div class="fcard" style="margin-bottom:1rem">
      <div style="font-size:0.75rem;text-transform:uppercase;letter-spacing:0.1em;color:var(--text-muted);font-weight:600;margin-bottom:1rem">
        <?= sprintf(t('new_comments_v'), count($new['comments'])) ?>
      </div>
      <div style="display:flex;flex-direction:column;gap:0.6rem">
        <?php foreach ($new['comments'] as $c): ?>
        <a href="video.php?id=<?= $c['video_id'] ?>#comments" style="display:flex;gap:0.8rem;text-decoration:none;padding:0.6rem;border:1px solid var(--border);border-radius:3px;transition:border-color .15s" onmouseover="this.style.borderColor='var(--gold)'" onmouseout="this.style.borderColor='var(--border)'">
          <div class="avatar" style="background:<?= h($c['user_color']) ?>;width:32px;height:32px;font-size:0.7rem;flex-shrink:0"><?= h(initials($c['user_name'])) ?></div>
          <div style="flex:1;min-width:0">
            <div style="font-size:0.78rem;color:var(--text-muted);margin-bottom:2px"><strong style="color:var(--text)"><?= h($c['user_name']) ?></strong> <?= sprintf(t('new_to_video'), h($c['video_title'])) ?></div>
            <div style="font-size:0.83rem;color:var(--text);line-height:1.4;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden"><?= h($c['text']) ?></div>
            <div style="font-size:0.68rem;color:var(--text-muted);margin-top:3px"><?= date('d.m.Y H:i', strtotime($c['created_at'])) ?></div>
          </div>
        </a>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($new['events'])): ?>
    <div class="fcard" style="margin-bottom:1rem">
      <div style="font-size:0.75rem;text-transform:uppercase;letter-spacing:0.1em;color:var(--text-muted);font-weight:600;margin-bottom:1rem">
        <?= sprintf(t('new_events'), count($new['events'])) ?>
      </div>
      <div style="display:flex;flex-direction:column;gap:0.6rem">
        <?php foreach ($new['events'] as $e): ?>
        <a href="event.php?id=<?= $e['id'] ?>" style="display:flex;align-items:center;gap:0.8rem;text-decoration:none;padding:0.5rem;border:1px solid var(--border);border-radius:3px;transition:border-color .15s" onmouseover="this.style.borderColor='var(--gold)'" onmouseout="this.style.borderColor='var(--border)'">
          <?php if ($e['thumbnail']): ?>
          <img src="<?= h($e['thumbnail']) ?>" style="width:72px;height:41px;object-fit:cover;border-radius:2px;flex-shrink:0">
          <?php else: ?>
          <div style="width:72px;height:41px;background:var(--surface2);border-radius:2px;display:flex;align-items:center;justify-content:center;font-size:1.5rem;flex-shrink:0">📅</div>
          <?php endif; ?>
          <div style="flex:1;min-width:0">
            <div style="font-size:0.85rem;font-weight:600;color:var(--text)"><?= h($e['title']) ?></div>
            <div style="font-size:0.72rem;color:var(--text-muted);margin-top:2px"><?= h($e['author_name']) ?> · <?= date('d.m.Y', strtotime($e['created_at'])) ?></div>
          </div>
        </a>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($new['media'])): ?>
    <div class="fcard" style="margin-bottom:1rem">
      <div style="font-size:0.75rem;text-transform:uppercase;letter-spacing:0.1em;color:var(--text-muted);font-weight:600;margin-bottom:1rem">
        <?= sprintf(t('new_media'), count($new['media'])) ?>
      </div>
      <div style="display:flex;flex-direction:column;gap:0.6rem">
        <?php foreach ($new['media'] as $m): ?>
        <a href="event.php?id=<?= $m['event_id'] ?>" style="display:flex;align-items:center;gap:0.8rem;text-decoration:none;padding:0.5rem;border:1px solid var(--border);border-radius:3px;transition:border-color .15s" onmouseover="this.style.borderColor='var(--gold)'" onmouseout="this.style.borderColor='var(--border)'">
          <?php if ($m['thumbnail']): ?>
          <img src="<?= h($m['thumbnail']) ?>" style="width:72px;height:41px;object-fit:cover;border-radius:2px;flex-shrink:0">
          <?php else: ?>
          <div style="width:72px;height:41px;background:var(--surface2);border-radius:2px;display:flex;align-items:center;justify-content:center;font-size:1.5rem;flex-shrink:0"><?= $m['type'] === 'photo' ? '🖼' : ($m['type'] === 'album' ? '📁' : '🔗') ?></div>
          <?php endif; ?>
          <div style="flex:1;min-width:0">
            <?php
              if ($m['type'] === 'photo') $type_default = t('media_type_photo_btn');
              elseif ($m['type'] === 'album') $type_default = t('media_type_album_btn');
              else $type_default = t('media_type_link_btn');
            ?>
            <div style="font-size:0.85rem;font-weight:600;color:var(--text)"><?= h($m['title'] ?: $type_default) ?></div>
            <div style="font-size:0.72rem;color:var(--text-muted);margin-top:2px"><?= h($m['author_name']) ?> · <?= sprintf(t('new_in_event'), h($m['event_title'])) ?></div>
          </div>
        </a>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($new['event_comments'])): ?>
    <div class="fcard" style="margin-bottom:1rem">
      <div style="font-size:0.75rem;text-transform:uppercase;letter-spacing:0.1em;color:var(--text-muted);font-weight:600;margin-bottom:1rem">
        <?= sprintf(t('new_comments_e'), count($new['event_comments'])) ?>
      </div>
      <div style="display:flex;flex-direction:column;gap:0.6rem">
        <?php foreach ($new['event_comments'] as $c): ?>
        <a href="event.php?id=<?= $c['event_id'] ?>#comments" style="display:flex;gap:0.8rem;text-decoration:none;padding:0.6rem;border:1px solid var(--border);border-radius:3px;transition:border-color .15s" onmouseover="this.style.borderColor='var(--gold)'" onmouseout="this.style.borderColor='var(--border)'">
          <div class="avatar" style="background:<?= h($c['user_color']) ?>;width:32px;height:32px;font-size:0.7rem;flex-shrink:0"><?= h(initials($c['user_name'])) ?></div>
          <div style="flex:1;min-width:0">
            <div style="font-size:0.78rem;color:var(--text-muted);margin-bottom:2px"><strong style="color:var(--text)"><?= h($c['user_name']) ?></strong> <?= sprintf(t('new_to_event'), h($c['event_title'])) ?></div>
            <div style="font-size:0.83rem;color:var(--text);line-height:1.4;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden"><?= h($c['text']) ?></div>
            <div style="font-size:0.68rem;color:var(--text-muted);margin-top:3px"><?= date('d.m.Y H:i', strtotime($c['created_at'])) ?></div>
          </div>
        </a>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

  <?php endif; ?>
</div>

<?php layout_foot(); ?>
