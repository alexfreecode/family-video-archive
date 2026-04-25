<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
session_start();
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }

$current = current_user();
$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: index.php'); exit; }

// Получаем видео
$stmt = db()->prepare("
    SELECT v.*, COALESCE(u.display_name, 'Удалённый пользователь') as author_name, COALESCE(u.color, '#666666') as author_color,
           ev.title as event_title
    FROM videos v
    LEFT JOIN users u ON u.id = v.user_id
    LEFT JOIN events ev ON ev.id = v.event_id
    WHERE v.id = ?
");
$stmt->execute([$id]);
$v = $stmt->fetch();
if (!$v) { header('Location: index.php'); exit; }

// Проверяем доступ
$has_access = ($v['user_id'] == $current['id'])
    || (function() use ($id, $current) {
        $s = db()->prepare("SELECT COUNT(*) FROM video_access WHERE video_id=? AND (user_id=0 OR user_id=?)");
        $s->execute([$id, $current['id']]);
        return $s->fetchColumn() > 0;
    })();

if (!$has_access) { header('Location: index.php'); exit; }

$is_owner     = ($v['user_id'] == $current['id']);
$participants = get_video_participants($id);
$access_ids   = get_video_access($id);
$is_pub       = in_array(0, $access_ids);

// Обработка комментариев
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    if (isset($_POST['comment_text'])) {
        $text = trim($_POST['comment_text']);
        if ($text) {
            add_comment($id, $current['id'], $text);
            telegram_notify_video_comment($id, $current['id'], $text);
        }
    }
    if (isset($_POST['delete_comment_id'])) {
        delete_comment((int)$_POST['delete_comment_id'], $current['id'], $current['is_admin']);
    }
    header('Location: video.php?id=' . $id . '#comments');
    exit;
}

$comments = get_comments($id);

require_once __DIR__ . '/layout.php';
layout_head(h($v['title']), false);
?>

<?php layout_nav($current, ''); ?>

<div class="main" style="max-width:860px">
  <div class="sec-header">
    <a href="javascript:history.back()" class="btn-outline"><?= h(t('back')) ?></a>
    <?php if ($is_owner): ?>
    <div style="display:flex;gap:0.5rem">
      <a href="edit.php?id=<?= $id ?>" class="btn-outline"><?= h(t('video_edit')) ?></a>
      <form method="POST" action="delete.php" style="display:inline"
            onsubmit="return confirm('<?= h(addslashes(sprintf(t('video_delete_confirm'), $v['title']))) ?>')">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= $id ?>">
        <button type="submit" class="btn-danger-sm" style="display:flex;align-items:center"><?= h(t('video_delete')) ?></button>
      </form>
    </div>
    <?php endif; ?>
  </div>

  <!-- Плеер -->
  <div style="position:relative;aspect-ratio:16/9;background:#000;border-radius:4px;overflow:hidden;margin-bottom:1.2rem">
    <div id="yt-player"></div>
  </div>

  <!-- Заголовок и кнопка YouTube -->
  <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;margin-bottom:1.2rem">
    <h1 style="font-size:1.3rem;font-weight:700;color:var(--text);margin:0;line-height:1.3"><?= h($v['title']) ?></h1>
    <a href="https://youtube.com/watch?v=<?= h($v['youtube_id']) ?>" target="_blank"
       class="btn-outline" style="white-space:nowrap;flex-shrink:0">↗ YouTube</a>
  </div>

  <!-- Метаданные -->
  <div style="background:var(--surface);border:1px solid var(--border);border-radius:4px;padding:1.2rem;margin-bottom:1rem">

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.9rem" class="vdetail-grid">

      <?php if (!empty($v['filmed_at'])): ?>
      <div>
        <div class="vd-label"><?= h(t('video_label_filmed')) ?></div>
        <div class="vd-value"><?= date('d.m.Y', strtotime($v['filmed_at'])) ?></div>
      </div>
      <?php endif; ?>

      <?php if (!empty($v['location'])): ?>
      <div>
        <div class="vd-label"><?= h(t('video_label_loc')) ?></div>
        <div class="vd-value"><?= h($v['location']) ?></div>
      </div>
      <?php endif; ?>

      <?php if (!empty($v['event_id']) || !empty($v['event'])): ?>
      <div>
        <div class="vd-label"><?= h(t('video_label_event')) ?></div>
        <div class="vd-value">
          <?php if (!empty($v['event_id'])): ?>
            <?php $event_label = !empty($v['event_title']) ? $v['event_title'] : $v['event']; ?>
            <a href="event.php?id=<?= $v['event_id'] ?>" style="color:var(--gold);text-decoration:none"><?= h($event_label) ?></a>
          <?php else: ?>
            <?= h($v['event']) ?>
          <?php endif; ?>
        </div>
      </div>
      </div>
      <?php endif; ?>

      <div>
        <div class="vd-label"><?= h(t('video_label_author')) ?></div>
        <div class="vd-value" style="display:flex;align-items:center;gap:0.4rem">
          <div class="avatar av" style="background:<?= h($v['author_color']) ?>;width:20px;height:20px;font-size:0.55rem"><?= h(initials($v['author_name'])) ?></div>
          <a href="member.php?id=<?= $v['user_id'] ?>" style="color:var(--text);text-decoration:none"><?= h($v['author_name']) ?></a>
        </div>
      </div>

      <div>
        <div class="vd-label"><?= h(t('video_label_access')) ?></div>
        <div class="vd-value">
          <?php if ($is_pub): ?>
            <span style="color:var(--gold)"><?= h(t('video_access_pub')) ?></span>
          <?php elseif (empty($access_ids)): ?>
            <?= h(t('video_access_auth')) ?>
          <?php else: ?>
            <?php
              $all_u = [];
              foreach (all_users() as $u) $all_u[$u['id']] = $u;
              $names = [];
              foreach ($access_ids as $aid) {
                  if (isset($all_u[$aid])) $names[] = $all_u[$aid]['display_name'];
              }
              echo h(implode(', ', $names));
            ?>
          <?php endif; ?>
        </div>
      </div>

    </div>

    <?php if (!empty($v['tags'])): ?>
    <div style="margin-top:0.9rem;padding-top:0.9rem;border-top:1px solid var(--border)">
      <div class="vd-label" style="margin-bottom:0.4rem"><?= h(t('video_label_tags')) ?></div>
      <div class="vtags">
        <?php foreach (array_map('trim', explode(',', $v['tags'])) as $tag): ?>
          <?php if ($tag): ?><span class="vtag"><?= h($tag) ?></span><?php endif; ?>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($participants)): ?>
    <div style="margin-top:0.9rem;padding-top:0.9rem;border-top:1px solid var(--border)">
      <div class="vd-label" style="margin-bottom:0.5rem"><?= h(t('video_label_parts')) ?></div>
      <div class="vparts">
        <?php foreach ($participants as $p): ?>
          <?php if ($p['user_id']): ?>
            <a href="member.php?id=<?= $p['user_id'] ?>" class="vpart-link">
              <div class="avatar av" style="background:<?= h($p['user_color']) ?>;width:18px;height:18px;font-size:0.5rem"><?= h(initials($p['user_name'])) ?></div>
              <?= h($p['user_name']) ?>
            </a>
          <?php else: ?>
            <span class="vpart-ext"><?= h($p['name']) ?></span>
          <?php endif; ?>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($v['description'])): ?>
    <div style="margin-top:0.9rem;padding-top:0.9rem;border-top:1px solid var(--border)">
      <div class="vd-label" style="margin-bottom:0.4rem"><?= h(t('video_label_desc')) ?></div>
      <div style="font-size:0.85rem;color:var(--text);line-height:1.6;white-space:pre-wrap" id="video-description"><?= h($v['description']) ?></div>
    </div>
    <?php endif; ?>

  </div>
</div>

<style>
.vd-label { font-size:0.68rem; text-transform:uppercase; letter-spacing:0.09em; color:var(--text-muted); font-weight:600; margin-bottom:0.2rem; }
.vd-value { font-size:0.88rem; color:var(--text); }
@media(max-width:500px) { .vdetail-grid { grid-template-columns: 1fr !important; } }
</style>

  <!-- Комментарии -->
  <div id="comments" style="margin-top:1.5rem">
    <div style="font-size:0.75rem;text-transform:uppercase;letter-spacing:0.1em;color:var(--text-muted);font-weight:600;margin-bottom:1rem">
      <?= h(t('video_comments_sec')) ?> <?php if (!empty($comments)): ?><span style="color:var(--text)"><?= count($comments) ?></span><?php endif; ?>
    </div>

    <?php if (!empty($comments)): ?>
    <div style="display:flex;flex-direction:column;gap:0.7rem;margin-bottom:1.2rem">
      <?php foreach ($comments as $c): ?>
      <div style="background:var(--surface);border:1px solid var(--border);border-radius:4px;padding:0.9rem 1rem">
        <div style="display:flex;align-items:center;gap:0.6rem;margin-bottom:0.5rem">
          <div class="avatar" style="background:<?= h($c['user_color']) ?>;width:26px;height:26px;font-size:0.6rem;flex-shrink:0"><?= h(initials($c['user_name'])) ?></div>
          <a href="member.php?id=<?= $c['user_id'] ?>" style="font-size:0.83rem;font-weight:600;color:var(--text);text-decoration:none"><?= h($c['user_name']) ?></a>
          <span style="font-size:0.72rem;color:var(--text-muted);margin-left:auto"><?= date('d.m.Y H:i', strtotime($c['created_at'])) ?></span>
          <?php if ($c['user_id'] == $current['id'] || $current['is_admin']): ?>
          <form method="POST" style="display:inline" onsubmit="return confirm('<?= h(t('comment_delete_confirm')) ?>')">
            <?= csrf_field() ?>
            <input type="hidden" name="delete_comment_id" value="<?= $c['id'] ?>">
            <button type="submit" style="background:none;border:none;color:var(--text-muted);cursor:pointer;font-size:0.75rem;padding:0;line-height:1" title="<?= h(t('video_delete')) ?>">✕</button>
          </form>
          <?php endif; ?>
        </div>
        <div class="comment-text" style="font-size:0.87rem;color:var(--text);line-height:1.5;white-space:pre-wrap"><?= h($c['text']) ?></div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Форма добавления -->
    <form method="POST">
      <?= csrf_field() ?>
      <div style="display:flex;gap:0.6rem;align-items:flex-start">
        <div class="avatar" style="background:<?= h($current['color']) ?>;width:32px;height:32px;font-size:0.7rem;flex-shrink:0;margin-top:2px"><?= h(initials($current['display_name'])) ?></div>
        <div style="flex:1">
          <textarea name="comment_text" class="form-control" rows="2"
                    placeholder="<?= h(t('video_comment_ph')) ?>" style="resize:vertical;min-height:60px"></textarea>
          <button type="submit" class="btn-gold" style="margin-top:0.5rem;font-size:0.75rem;padding:0.4rem 1rem"><?= h(t('video_comment_send')) ?></button>
        </div>
      </div>
    </form>
  </div>

</div>

<style>
.vd-label { font-size:0.68rem; text-transform:uppercase; letter-spacing:0.09em; color:var(--text-muted); font-weight:600; margin-bottom:0.2rem; }
.vd-value { font-size:0.88rem; color:var(--text); }
@media(max-width:500px) { .vdetail-grid { grid-template-columns: 1fr !important; } }
.timecode {
  color: var(--gold); cursor: pointer; text-decoration: underline;
  text-decoration-style: dotted; font-weight: 600;
}
.timecode:hover { color: var(--gold-light); }
</style>

<script>
const YT_VIDEO_ID = '<?= h($v['youtube_id']) ?>';
let ytPlayer = null;

// Инициализация YouTube IFrame API
function onYouTubeIframeAPIReady() {
  ytPlayer = new YT.Player('yt-player', {
    videoId: YT_VIDEO_ID,
    width: '100%',
    height: '100%',
    playerVars: { autoplay: 0, rel: 0 },
  });
}

// Перемотка к нужному моменту
function seekTo(seconds) {
  if (ytPlayer && ytPlayer.seekTo) {
    ytPlayer.seekTo(seconds, true);
    ytPlayer.playVideo();
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }
}

// Парсим тайм-код в секунды
function parseTimecode(str) {
  const parts = str.split(':').map(Number);
  if (parts.length === 2) return parts[0] * 60 + parts[1];
  if (parts.length === 3) return parts[0] * 3600 + parts[1] * 60 + parts[2];
  return 0;
}

// Делаем тайм-коды кликабельными
function linkifyTimecodes(el) {
  el.innerHTML = el.textContent.replace(
    /\b(\d{1,2}:\d{2}(?::\d{2})?)\b/g,
    function(match) {
      const secs = parseTimecode(match);
      return '<span class="timecode" onclick="seekTo(' + secs + ')">' + match + '</span>';
    }
  );
}

document.addEventListener('DOMContentLoaded', function() {
  // Описание видео
  const descEl = document.getElementById('video-description');
  if (descEl) linkifyTimecodes(descEl);

  // Тексты комментариев
  document.querySelectorAll('.comment-text').forEach(linkifyTimecodes);
});
</script>
<script src="https://www.youtube.com/iframe_api"></script>

<?php layout_foot(); ?>
