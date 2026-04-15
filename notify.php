<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
session_start();
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }

load_language();

$user  = current_user();
$notifications = get_pending_notifications($user['id']);

if (empty($notifications)) {
    header('Location: index.php');
    exit;
}

$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Массовое закрытие для пользователей без видео
    if (isset($_POST['notif_ids'])) {
        foreach ($_POST['notif_ids'] as $nid) {
            delete_notification((int)$nid, $user['id']);
        }
        header('Location: index.php');
        exit;
    }

    $notif_id  = (int)($_POST['notif_id'] ?? 0);
    $new_uid   = (int)($_POST['new_user_id'] ?? 0);
    $video_ids = $_POST['video_ids'] ?? [];

    $stmt = db()->prepare("INSERT IGNORE INTO video_access (video_id, user_id) VALUES (?, ?)");
    foreach ($video_ids as $vid) {
        $stmt->execute([(int)$vid, $new_uid]);
    }
    delete_notification($notif_id, $user['id']);
    $success = t('notify_ok');

    $notifications = get_pending_notifications($user['id']);
    if (empty($notifications)) {
        header('Location: index.php');
        exit;
    }
}

$notif     = $notifications[0];
$my_videos = get_my_private_videos($user['id']);

require_once __DIR__ . '/layout.php';
layout_head(t('notify_title'), false);
?>

<?php layout_nav($user, ''); ?>

<div class="main" style="max-width:700px">
  <div class="sec-header">
    <div class="sec-title"><?= h(t('notify_title')) ?></div>
    <a href="index.php" class="btn-outline"><?= h(t('notify_skip')) ?></a>
  </div>

  <?php if ($success): ?><div class="alert-ok"><?= h($success) ?></div><?php endif; ?>

  <div class="fcard">
    <!-- Аватар участника -->
    <div style="display:flex;align-items:center;gap:1rem;margin-bottom:1.2rem">
      <div class="avatar" style="background:<?= h($notif['new_user_color']) ?>;width:48px;height:48px;font-size:1rem">
        <?= h(initials($notif['new_user_name'])) ?>
      </div>
      <div>
        <div style="font-size:1.05rem;font-weight:600;color:var(--text)"><?= h($notif['new_user_name']) ?></div>
        <div style="font-size:0.78rem;color:var(--text-muted);margin-top:2px"><?= h(t('notify_joined')) ?></div>
      </div>
      <?php if (count($notifications) > 1): ?>
      <div style="margin-left:auto;font-size:0.75rem;color:var(--text-muted)">
        <?= h(sprintf(t('notify_more'), count($notifications) - 1)) ?>
      </div>
      <?php endif; ?>
    </div>

    <!-- Пояснение -->
    <div style="font-size:0.88rem;color:var(--text-muted);margin-bottom:1.2rem;padding:0.8rem;background:var(--surface2);border-radius:3px;border:1px solid var(--border)">
      <?= sprintf(t('notify_hint1'), '<strong style="color:var(--text)">' . h($notif['new_user_name']) . '</strong>') ?><br><br>
      <?= sprintf(t('notify_hint2'), '<strong style="color:var(--text)">' . h($notif['new_user_name']) . '</strong>') ?>
    </div>

    <?php if (!empty($my_videos)): ?>
    <form method="POST" id="notifyForm">
      <input type="hidden" name="notif_id" value="<?= $notif['id'] ?>">
      <input type="hidden" name="new_user_id" value="<?= $notif['new_user_id'] ?>">

      <!-- Кнопки управления выбором -->
      <div style="display:flex;align-items:center;gap:0.5rem;margin-bottom:0.8rem;flex-wrap:wrap">
        <button type="button" class="btn-outline" style="font-size:0.72rem;padding:0.3rem 0.7rem"
                onclick="selectAll()"
                data-bs-toggle="popover" data-bs-placement="top"
                data-bs-content="<?= h(t('notify_pop_all')) ?>">
          <?= h(t('notify_select_all')) ?>
        </button>
        <button type="button" class="btn-outline" style="font-size:0.72rem;padding:0.3rem 0.7rem"
                onclick="clearAll()"
                data-bs-toggle="popover" data-bs-placement="top"
                data-bs-content="<?= h(t('notify_pop_clear')) ?>">
          <?= h(t('notify_clear')) ?>
        </button>
        <span style="font-size:0.72rem;color:var(--text-muted);margin-left:auto">
          <?= h(t('notify_selected')) ?> <span id="selectedCount">0</span> <?= h(t('notify_of')) ?> <?= count($my_videos) ?>
        </span>
      </div>

      <!-- Список видео -->
      <div style="display:flex;flex-direction:column;gap:0.4rem;margin-bottom:1rem;max-height:340px;overflow-y:auto">
        <?php foreach ($my_videos as $v): ?>
        <label class="video-pick-row">
          <input type="checkbox" name="video_ids[]" value="<?= $v['id'] ?>" onchange="updateCount()">
          <img src="https://img.youtube.com/vi/<?= h($v['youtube_id']) ?>/default.jpg"
               style="width:60px;height:34px;object-fit:cover;border-radius:2px;flex-shrink:0">
          <div style="flex:1;min-width:0">
            <div style="font-size:0.83rem;color:var(--text);font-weight:500;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= h($v['title']) ?></div>
            <?php if ($v['filmed_at']): ?>
            <div style="font-size:0.68rem;color:var(--text-muted)"><?= date('d.m.Y', strtotime($v['filmed_at'])) ?></div>
            <?php endif; ?>
          </div>
        </label>
        <?php endforeach; ?>
      </div>

      <!-- Кнопка сохранения -->
      <button type="submit" class="btn-gold"
              data-bs-toggle="popover" data-bs-placement="top"
              data-bs-content="<?= h(t('notify_pop_save')) ?>">
        <?= h(t('btn_save')) ?>
      </button>
    </form>

    <?php else: ?>
    <div style="font-size:0.82rem;color:var(--text-muted)">
      <?= h(t('notify_no_videos')) ?>
    </div>
    <form method="POST" style="margin-top:1rem">
      <input type="hidden" name="notif_id" value="<?= $notif['id'] ?>">
      <input type="hidden" name="new_user_id" value="<?= $notif['new_user_id'] ?>">
      <button type="submit" class="btn-gold"><?= h(t('notify_close')) ?></button>
    </form>
    <?php endif; ?>
  </div>
</div>

<style>
.video-pick-row {
  display:flex; align-items:center; gap:0.7rem;
  padding:0.5rem 0.6rem; border:1px solid var(--border);
  border-radius:3px; cursor:pointer;
  transition:background .12s; user-select:none;
}
.video-pick-row:hover { background:var(--surface2); }
.video-pick-row input[type=checkbox] {
  width:15px; height:15px; accent-color:var(--gold);
  flex-shrink:0; cursor:pointer;
}
</style>

<script>
// Инициализация Bootstrap popovers
document.addEventListener('DOMContentLoaded', function() {
  document.querySelectorAll('[data-bs-toggle="popover"]').forEach(el => {
    new bootstrap.Popover(el, { trigger: 'hover' });
    // Скрываем popover при клике
    el.addEventListener('click', function() {
      bootstrap.Popover.getInstance(this)?.hide();
      this.blur();
    });
  });
});

function selectAll() {
  document.querySelectorAll('#notifyForm input[type=checkbox]').forEach(cb => cb.checked = true);
  updateCount();
}

function clearAll() {
  document.querySelectorAll('#notifyForm input[type=checkbox]').forEach(cb => cb.checked = false);
  updateCount();
}

function updateCount() {
  const checked = document.querySelectorAll('#notifyForm input[type=checkbox]:checked').length;
  document.getElementById('selectedCount').textContent = checked;
}
</script>

<?php layout_foot(); ?>
