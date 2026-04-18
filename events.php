<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
session_start();
if (!isset($_SESSION['user_id'])) {
    if (!check_remember_token()) { header('Location: login.php'); exit; }
}
$user = current_user();
if (!$user) { session_destroy(); header('Location: login.php'); exit; }
load_language();

$sort  = in_array($_GET['sort']  ?? '', ['event_date', 'added']) ? $_GET['sort']  : 'event_date';
$order = in_array($_GET['order'] ?? '', ['asc', 'desc'])        ? $_GET['order'] : 'desc';

$events = get_events_for_user($user['id'], $sort, $order);

require_once __DIR__ . '/layout.php';
layout_head(t('nav_events'), false);
?>

<?php layout_nav($user, 'events'); ?>

<div class="main">
  <div class="sec-header">
    <div class="sec-title"><?= h(t('nav_events')) ?> <small><?= count($events) ?></small></div>
    <div style="display:flex;align-items:center;gap:0.5rem">
      <button type="button" class="btn-outline" data-bs-toggle="modal" data-bs-target="#sortModal"
              style="display:flex;align-items:center;gap:0.5rem">
        <span style="font-size:1rem">⇅</span><span style="font-size:0.9rem">╱</span><span style="font-size:0.85rem">▽</span>
      </button>
      <a href="event_edit.php" class="btn-gold"><?= h(t('events_add')) ?></a>
    </div>
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

<!-- Модальное окно сортировки -->
<div class="modal fade" id="sortModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered" style="max-width:400px">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><?= h(t('sort_title')) ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <form id="sortForm" method="GET" action="events.php">
          <div style="font-size:0.72rem;text-transform:uppercase;letter-spacing:0.1em;color:var(--text-muted);font-weight:600;margin-bottom:0.8rem"><?= h(t('sort_heading')) ?></div>

          <div style="display:flex;flex-direction:column;gap:0.4rem;margin-bottom:1.5rem">
            <?php
              $opts = [
                ['event_date', 'desc', t('sort_event_new')],
                ['event_date', 'asc',  t('sort_event_old')],
                ['added',      'desc', t('sort_added_new')],
                ['added',      'asc',  t('sort_added_old')],
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

          <input type="hidden" name="sort"  id="sort_hidden"  value="<?= h($sort) ?>">
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
