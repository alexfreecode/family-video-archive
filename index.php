<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
session_start();
if (!isset($_SESSION['user_id'])) {
    if (!check_remember_token()) {
        header('Location: login.php'); exit;
    }
}
$user = current_user();
if (!$user) { session_destroy(); header('Location: login.php'); exit; }

require_once __DIR__ . '/layout.php';
layout_head('', false);
$tab    = $_GET['tab'] ?? 'all';
$search = trim($_GET['q'] ?? '');
$sort   = in_array($_GET['sort'] ?? '', ['filmed', 'added']) ? $_GET['sort'] : 'filmed';
$order  = in_array($_GET['order'] ?? '', ['asc', 'desc']) ? $_GET['order'] : 'desc';

$videos = ($tab === 'mine')
    ? get_my_videos($user['id'], $search, $sort, $order)
    : get_videos_for_user($user['id'], $search, $sort, $order);

// Загружаем участников всех видео одним запросом
$video_ids = array_column($videos, 'id');
$all_participants = get_participants_for_videos($video_ids);
?>

<?php layout_nav($user, $tab); ?>

<?php
$notifications = get_pending_notifications($user['id']);
if (!empty($notifications)):
  $my_video_count = count(get_my_videos($user['id']));
?>
<div style="background:rgba(201,168,76,.1);border-bottom:1px solid rgba(201,168,76,.3);padding:0.75rem 1.5rem;display:flex;align-items:center;gap:1rem;flex-wrap:wrap">
  <div style="flex:1;min-width:200px">
    <?php foreach ($notifications as $n): ?>
    <div style="display:flex;align-items:center;gap:0.6rem;margin-bottom:0.3rem">
      <div class="avatar" style="background:<?= h($n['new_user_color']) ?>;width:24px;height:24px;font-size:0.55rem;flex-shrink:0"><?= h(initials($n['new_user_name'])) ?></div>
      <span style="font-size:0.85rem;color:var(--text)"><?= h(t('index_new_member')) ?><strong><?= h($n['new_user_name']) ?></strong><?= $my_video_count > 0 ? '. ' . h(t('index_new_member_access')) : '.' ?></span>
    </div>
    <?php endforeach; ?>
  </div>
  <?php if ($my_video_count > 0): ?>
    <a href="notify.php" class="btn-gold" style="white-space:nowrap"><?= h(t('index_setup_access')) ?></a>
  <?php else: ?>
    <form method="POST" action="notify.php">
      <?= csrf_field() ?>
      <?php foreach ($notifications as $n): ?>
        <input type="hidden" name="notif_ids[]" value="<?= $n['id'] ?>">
      <?php endforeach; ?>
      <button type="submit" class="btn-outline" style="white-space:nowrap"><?= h(t('index_dismiss')) ?></button>
    </form>
  <?php endif; ?>
</div>
<?php endif; ?>

<div class="main">
  <div class="sec-header">
    <div class="sec-title">
      <?= $tab === 'mine' ? h(t('nav_mine')) : h(t('nav_all')) ?>
      <small><?= count($videos) ?> <?= h(t('index_videos_count')) ?></small>
    </div>
    <div style="display:flex;align-items:center;gap:0.5rem">
      <button type="button" class="btn-outline" data-bs-toggle="modal" data-bs-target="#sortModal"
              style="display:flex;align-items:center;gap:0.5rem">
        <span style="font-size:1rem">⇅</span><span style="font-size:0.9rem">╱</span><span style="font-size:0.85rem">▽</span>
      </button>
      <a href="add.php" class="btn-gold"><?= h(t('nav_add')) ?></a>
    </div>
  </div>

  <?php if (empty($videos)): ?>
    <div class="empty">
      <div class="icon">🎬</div>
      <?php if ($search): ?>
        <p><?= h(sprintf(t('index_search_none'), $search)) ?></p>
        <a href="index.php<?= $tab === 'mine' ? '?tab=mine' : '' ?>" class="btn-outline"><?= h(t('index_reset_search')) ?></a>
      <?php else: ?>
        <p><?= h(t('index_no_videos')) ?><br><?= h(t('index_add_first')) ?></p>
        <a href="add.php" class="btn-gold"><?= h(t('index_add_video')) ?></a>
      <?php endif; ?>
    </div>
  <?php else: ?>
    <div class="video-grid">
      <?php foreach ($videos as $v):
        $is_owner = ($v['user_id'] == $user['id']);
        $is_pub   = !empty($v['is_public']);

        // Бейдж доступа
        if ($is_pub) {
            $badge = '<span class="vbadge pub">' . h(t('badge_public')) . '</span>';
        } elseif (!empty($v['viewer_names'])) {
            $names = explode(', ', $v['viewer_names']);
            $badge_text = count($names) > 2 ? implode(', ', array_slice($names, 0, 2)) . '…' : implode(', ', $names);
            $badge = '<span class="vbadge">' . h($badge_text) . '</span>';
        } else {
            $badge = '<span class="vbadge">' . h(t('badge_private')) . '</span>';
        }
      ?>
      <div class="vcard">
        <div class="vthumb" onclick="location.href='video.php?id=<?= $v['id'] ?>'" style="cursor:pointer">
          <img src="https://img.youtube.com/vi/<?= h($v['youtube_id']) ?>/mqdefault.jpg"
               alt="<?= h($v['title']) ?>"
               loading="lazy"
               onerror="this.src='https://img.youtube.com/vi/<?= h($v['youtube_id']) ?>/0.jpg'">
          <div class="play-overlay">
            <div class="play-circle">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="white"><path d="M8 5v14l11-7z"/></svg>
            </div>
          </div>
          <?= $badge ?>
        </div>
        <div class="vinfo">
          <div class="vtitle"><?= h($v['title']) ?></div>
          <?php
            $meta_chips = [];
            if (!empty($v['filmed_at'])) {
                $meta_chips[] = '📅 ' . date('d.m.Y', strtotime($v['filmed_at']));
            }
            if (!empty($v['location'])) {
                $meta_chips[] = '📍 ' . h($v['location']);
            }
            if (!empty($v['event_id'])) {
                $event_label = !empty($v['event_title']) ? $v['event_title'] : $v['event'];
                $meta_chips[] = '🎉 <a href="event.php?id=' . $v['event_id'] . '" style="color:inherit;text-decoration:none">' . h($event_label) . '</a>';
            } elseif (!empty($v['event'])) {
                $meta_chips[] = '🎉 ' . h($v['event']);
            }
          ?>
          <?php if (!empty($meta_chips)): ?>
          <div class="vchips">
            <?php foreach ($meta_chips as $chip): ?>
              <span class="vchip"><?= $chip ?></span>
            <?php endforeach; ?>
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
          <?php
            $participants = $all_participants[$v['id']] ?? [];
            if (!empty($participants)):
          ?>
          <div class="vparts">
            <?php foreach ($participants as $p): ?>
              <?php if ($p['user_id']): ?>
                <a href="member.php?id=<?= $p['user_id'] ?>" class="vpart-link">
                  <div class="avatar av" style="background:<?= h($p['user_color']) ?>;width:16px;height:16px;font-size:0.45rem"><?= h(initials($p['user_name'])) ?></div>
                  <?= h($p['user_name']) ?>
                </a>
              <?php else: ?>
                <span class="vpart-ext"><?= h($p['name']) ?></span>
              <?php endif; ?>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
          <div class="vmeta">
            <div class="vauthor">
              <div class="avatar av" style="background:<?= h($v['author_color']) ?>"><?= h(initials($v['author_name'])) ?></div>
              <a href="member.php?id=<?= $v['user_id'] ?>" class="name" style="color:var(--text-muted);text-decoration:none"><?= h($v['author_name']) ?></a>
            </div>
          </div>
          <div class="vactions">
            <a href="video.php?id=<?= $v['id'] ?>" class="btn-gold"><?= h(t('video_details')) ?></a>
            <?php if ($is_owner): ?>
            <a href="edit.php?id=<?= $v['id'] ?>" class="btn-outline"><?= h(t('video_edit')) ?></a>
            <form method="POST" action="delete.php" style="display:inline"
                  onsubmit="return confirm('<?= h(sprintf(t('video_delete_confirm'), addslashes($v['title']))) ?>')">
              <?= csrf_field() ?>
              <input type="hidden" name="id" value="<?= $v['id'] ?>">
              <button type="submit" class="btn-danger-sm"><?= h(t('video_delete')) ?></button>
            </form>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<?php layout_foot(); ?>
<!-- Модальное окно сортировки и фильтров -->
<div class="modal fade" id="sortModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered" style="max-width:400px">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><?= h(t('sort_title')) ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <form id="sortForm" method="GET" action="index.php">
          <?php if ($tab === 'mine'): ?><input type="hidden" name="tab" value="mine"><?php endif; ?>
          <?php if ($search): ?><input type="hidden" name="q" value="<?= h($search) ?>"><?php endif; ?>

          <div style="font-size:0.72rem;text-transform:uppercase;letter-spacing:0.1em;color:var(--text-muted);font-weight:600;margin-bottom:0.8rem"><?= h(t('sort_heading')) ?></div>

          <div style="display:flex;flex-direction:column;gap:0.4rem;margin-bottom:1.5rem">
            <?php
              $opts = [
                ['filmed','desc', t('sort_filmed_new')],
                ['filmed','asc',  t('sort_filmed_old')],
                ['added', 'desc', t('sort_added_new')],
                ['added', 'asc',  t('sort_added_old')],
              ];
              foreach ($opts as [$s, $o, $label]):
                $checked = ($sort === $s && $order === $o);
            ?>
            <label class="sort-opt <?= $checked ? 'active' : '' ?>">
              <input type="radio" name="sort_combo" value="<?= $s ?>|<?= $o ?>" <?= $checked ? 'checked' : '' ?>>
              <?= $label ?>
            </label>
            <?php endforeach; ?>
          </div>

          <input type="hidden" name="sort" id="sort_hidden" value="<?= h($sort) ?>">
          <input type="hidden" name="order" id="order_hidden" value="<?= h($order) ?>">

          <button type="submit" class="btn-gold" style="width:100%"><?= h(t('sort_apply')) ?></button>
        </form>
      </div>
    </div>
  </div>
</div>

<style>
.sort-opt {
  display:flex; align-items:center; gap:0.65rem;
  padding:0.55rem 0.9rem; border:1px solid var(--border);
  border-radius:3px; cursor:pointer; font-size:0.85rem;
  color:var(--text-muted); transition:all .15s; user-select:none;
}
.sort-opt:hover { border-color:var(--text-muted); color:var(--text); }
.sort-opt.active { border-color:var(--gold); color:var(--text); }
.sort-opt input[type=radio] { width:15px; height:15px; accent-color:var(--gold); flex-shrink:0; cursor:pointer; }
</style>

<script>
// Обновляем скрытые поля при выборе
document.querySelectorAll('input[name="sort_combo"]').forEach(radio => {
  radio.addEventListener('change', function() {
    const [s, o] = this.value.split('|');
    document.getElementById('sort_hidden').value = s;
    document.getElementById('order_hidden').value = o;
    document.querySelectorAll('.sort-opt').forEach(opt => opt.classList.remove('active'));
    this.closest('.sort-opt').classList.add('active');
  });
});
</script>
