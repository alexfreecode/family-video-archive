<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
session_start();
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }
load_language();
$user = current_user();
if (!$user) { session_destroy(); header('Location: login.php'); exit; }

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: events.php'); exit; }

$media = get_media_item($id);
if (!$media || $media['user_id'] != $user['id']) { header('Location: events.php'); exit; }

$event = get_event($media['event_id']);
$others = other_users($user['id']);
$error = '';

// Текущий доступ
$access_ids = db()->prepare("SELECT user_id FROM media_access WHERE media_id=?");
$access_ids->execute([$id]);
$aids = array_column($access_ids->fetchAll(), 'user_id');
if (in_array(0, $aids)) $current_atype = 'all';
elseif (!empty($aids)) { $current_atype = 'selected'; $current_uids = $aids; }
else $current_atype = 'only_me';
$current_uids = $current_uids ?? [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    if (is_demo_restricted()) {
        $error = 'Demo mode — data is not saved.';
    } else {
    $url   = trim($_POST['url'] ?? '');
    $title = trim($_POST['title'] ?? '');
    $desc  = trim($_POST['description'] ?? '');
    $atype = $_POST['access_type'] ?? 'all';
    $uids  = $_POST['user_ids'] ?? [];
    $thumbnail = $media['thumbnail'];

    if (!$url) {
        $error = t('media_err_url');
    } else {
        // Новое превью
        if (!empty($_FILES['thumbnail']['tmp_name'])) {
            $file = $_FILES['thumbnail'];
            $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg','jpeg','png','webp']) && $file['size'] <= 10*1024*1024) {
                $t = make_thumbnail($file['tmp_name'], 'media');
                if ($t) {
                    // Удаляем старое
                    if ($thumbnail) { $old = __DIR__ . '/' . ltrim($thumbnail, '/'); if (file_exists($old)) unlink($old); }
                    $thumbnail = $t;
                }
            } else {
                $error = t('media_err_thumb');
            }
        }

        // Удалить превью
        if (isset($_POST['remove_thumbnail']) && $thumbnail) {
            $old = __DIR__ . '/' . ltrim($thumbnail, '/');
            if (file_exists($old)) unlink($old);
            $thumbnail = null;
        }

        if (!$error) {
            db()->prepare("UPDATE media SET url=?, title=?, description=?, thumbnail=? WHERE id=?")
               ->execute([$url, $title ?: null, $desc ?: null, $thumbnail, $id]);
            db()->prepare("DELETE FROM media_access WHERE media_id=?")->execute([$id]);
            if ($atype === 'all') {
                db()->prepare("INSERT INTO media_access (media_id, user_id) VALUES (?,0)")->execute([$id]);
            } elseif ($atype === 'selected' && !empty($uids)) {
                $stmt = db()->prepare("INSERT INTO media_access (media_id, user_id) VALUES (?,?)");
                foreach ($uids as $uid) $stmt->execute([$id, (int)$uid]);
            }
            header('Location: event.php?id=' . $media['event_id']);
            exit;
        }
    }
    } // end !is_demo_restricted
}

require_once __DIR__ . '/layout.php';
layout_head(t('media_edit_title'), false);
?>

<?php layout_nav($user, 'events'); ?>

<div class="main">
  <div class="sec-header">
    <div class="sec-title"><?= h(t('media_edit_title')) ?></div>
    <a href="event.php?id=<?= $media['event_id'] ?>" class="btn-outline"><?= h(t('back')) ?></a>
  </div>

  <?php if ($error): ?><div class="alert-err"><?= h($error) ?></div><?php endif; ?>

  <div class="fcard" style="max-width:620px">
    <div style="font-size:0.72rem;color:var(--text-muted);margin-bottom:1rem">
      <?= h(t('media_type_info')) ?> <?= $media['type'] === 'photo' ? h(t('media_type_photo_btn')) : ($media['type'] === 'album' ? h(t('media_type_album_btn')) : h(t('media_type_link_btn'))) ?>
      · <?= h(t('media_event_lbl')) ?>: <a href="event.php?id=<?= $media['event_id'] ?>" style="color:var(--gold)"><?= h($event['title']) ?></a>
    </div>

    <form method="POST" enctype="multipart/form-data">
      <?= csrf_field() ?>

      <div class="mb-3">
        <label class="form-label"><?= h(t('media_url_label')) ?> <span style="color:var(--accent)">*</span></label>
        <input type="url" name="url" class="form-control" value="<?= h($_POST['url'] ?? $media['url']) ?>" required>
      </div>

      <div class="mb-3">
        <label class="form-label"><?= h(t('form_title_label')) ?> <span class="opt-label"><?= h(t('form_optional')) ?></span></label>
        <input type="text" name="title" class="form-control" value="<?= h($_POST['title'] ?? $media['title'] ?? '') ?>">
      </div>

      <div class="mb-3">
        <label class="form-label"><?= h(t('form_desc_label')) ?> <span class="opt-label"><?= h(t('form_optional')) ?></span></label>
        <textarea name="description" class="form-control" rows="2"><?= h($_POST['description'] ?? $media['description'] ?? '') ?></textarea>
      </div>

      <div class="mb-3">
        <label class="form-label"><?= h(t('media_thumb_lbl')) ?></label>
        <?php if ($media['thumbnail']): ?>
        <div style="margin-bottom:0.6rem;display:flex;align-items:center;gap:1rem">
          <img src="<?= h($media['thumbnail']) ?>" style="width:120px;height:68px;object-fit:cover;border-radius:3px;border:1px solid var(--border)">
          <label style="display:flex;align-items:center;gap:0.4rem;font-size:0.82rem;color:var(--text-muted);cursor:pointer">
            <input type="checkbox" name="remove_thumbnail" value="1"> <?= h(t('media_remove_thumb')) ?>
          </label>
        </div>
        <div style="font-size:0.72rem;color:var(--text-muted);margin-bottom:0.4rem"><?= h(t('media_thumb_repl')) ?></div>
        <?php endif; ?>
        <input type="file" name="thumbnail" class="form-control" accept="image/jpeg,image/png,image/webp">
      </div>

      <div class="mb-4">
        <label class="form-label"><?= h(t('form_who_sees')) ?></label>
        <div class="access-box">
          <div class="access-btns">
            <button type="button" class="access-btn <?= $current_atype === 'all'      ? 'active' : '' ?>" data-type="all"      onclick="setAccess('all')"><?= h(t('access_all')) ?></button>
            <button type="button" class="access-btn <?= $current_atype === 'selected' ? 'active' : '' ?>" data-type="selected" onclick="setAccess('selected')"><?= h(t('access_selected')) ?></button>
            <button type="button" class="access-btn <?= $current_atype === 'only_me'  ? 'active' : '' ?>" data-type="only_me"  onclick="setAccess('only_me')"><?= h(t('access_only_me')) ?></button>
          </div>
          <input type="hidden" name="access_type" id="access_type" value="<?= h($current_atype) ?>">
          <div id="memberList" class="member-list" style="opacity:<?= $current_atype === 'selected' ? '1' : '0.35' ?>;pointer-events:<?= $current_atype === 'selected' ? 'auto' : 'none' ?>">
            <?php foreach ($others as $m):
              $checked = in_array($m['id'], $current_uids);
            ?>
            <div class="member-row <?= $checked ? 'on' : '' ?>">
              <div class="avatar av" style="background:<?= h($m['color']) ?>"><?= h(initials($m['display_name'])) ?></div>
              <span class="mname"><?= h($m['display_name']) ?></span>
              <div class="chk"><svg width="10" height="8" viewBox="0 0 10 8" fill="none"><path d="M1 3.5l2.5 2.5 5-5" stroke="var(--bg)" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></div>
              <input type="checkbox" name="user_ids[]" value="<?= $m['id'] ?>" <?= $checked ? 'checked' : '' ?> style="display:none">
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <div style="display:flex;gap:0.7rem">
        <button type="submit" class="btn-gold"><?= h(t('btn_save')) ?></button>
        <a href="event.php?id=<?= $media['event_id'] ?>" class="btn-outline"><?= h(t('btn_cancel')) ?></a>
      </div>
    </form>
  </div>
</div>

<style>.opt-label { font-weight:300; text-transform:none; letter-spacing:0; font-size:0.7rem; }</style>

<?php layout_foot(); ?>
