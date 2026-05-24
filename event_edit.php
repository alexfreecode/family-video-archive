<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
session_start();
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }
load_language();
$user = current_user();
if (!$user) { session_destroy(); header('Location: login.php'); exit; }

$id    = (int)($_GET['id'] ?? 0);
$event = null;
$is_new = !$id;

if (!$is_new) {
    $event = get_event($id);
    if (!$event || $event['user_id'] != $user['id']) {
        header('Location: events.php'); exit;
    }
}

$others = other_users($user['id']);
$error  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    if (is_demo_restricted()) {
        $error = 'Demo mode — data is not saved.';
    } else {
    $title      = trim($_POST['title'] ?? '');
    $event_date = trim($_POST['event_date'] ?? '');
    $desc       = trim($_POST['description'] ?? '');
    $atype      = $_POST['access_type'] ?? 'all';
    $uids       = $_POST['user_ids'] ?? [];
    $thumbnail  = $event['thumbnail'] ?? null;

    if (!$title) {
        $error = t('evtedit_err_title');
    } else {
        // Обработка загрузки превью
        if (!empty($_FILES['thumbnail']['tmp_name'])) {
            $file = $_FILES['thumbnail'];
            $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg','jpeg','png','webp'])) {
                $error = t('evtedit_err_format');
            } elseif ($file['size'] > 10 * 1024 * 1024) {
                $error = t('evtedit_err_size');
            } else {
                $upload_dir = __DIR__ . '/uploads/thumbnails/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
                $filename = 'event_' . uniqid() . '.jpg';
                $filepath = $upload_dir . $filename;

                // Загружаем изображение
                $src = match($ext) {
                    'jpg','jpeg' => imagecreatefromjpeg($file['tmp_name']),
                    'png'        => imagecreatefrompng($file['tmp_name']),
                    'webp'       => imagecreatefromwebp($file['tmp_name']),
                };

                // Исправляем EXIF-ориентацию (фото с телефона)
                if ($src && in_array($ext, ['jpg','jpeg']) && function_exists('exif_read_data')) {
                    $exif = @exif_read_data($file['tmp_name']);
                    $orientation = $exif['Orientation'] ?? 1;
                    $src = match($orientation) {
                        3 => imagerotate($src, 180, 0),
                        6 => imagerotate($src, -90, 0),
                        8 => imagerotate($src, 90, 0),
                        default => $src,
                    };
                }

                if ($src) {
                    $w = imagesx($src);
                    $h = imagesy($src);
                    $tw = 480; $th = 270;
                    $ratio = min($tw/$w, $th/$h);
                    $nw = (int)($w * $ratio);
                    $nh = (int)($h * $ratio);
                    $dst = imagecreatetruecolor($tw, $th);
                    $bg  = imagecolorallocate($dst, 26, 24, 20);
                    imagefill($dst, 0, 0, $bg);
                    $x = (int)(($tw - $nw) / 2);
                    $y = (int)(($th - $nh) / 2);
                    imagecopyresampled($dst, $src, $x, $y, 0, 0, $nw, $nh, $w, $h);
                    imagejpeg($dst, $filepath, 85);
                    imagedestroy($src);
                    imagedestroy($dst);
                    if ($event && $event['thumbnail']) {
                        $old = __DIR__ . '/' . ltrim($event['thumbnail'], '/');
                        if (file_exists($old)) unlink($old);
                    }
                    $thumbnail = 'uploads/thumbnails/' . $filename;
                }
            }
        }

        if (!$error) {
            if ($is_new) {
                db()->prepare("INSERT INTO events (user_id, title, event_date, description, thumbnail) VALUES (?,?,?,?,?)")
                   ->execute([$user['id'], $title, $event_date ?: null, $desc ?: null, $thumbnail]);
                $new_id = (int)db()->lastInsertId();
                save_event_access($new_id, $atype, $uids);
                telegram_notify_event($new_id, $user['id']);
                header('Location: event.php?id=' . $new_id);
            } else {
                db()->prepare("UPDATE events SET title=?, event_date=?, description=?, thumbnail=? WHERE id=?")
                   ->execute([$title, $event_date ?: null, $desc ?: null, $thumbnail, $id]);
                save_event_access($id, $atype, $uids);
                header('Location: event.php?id=' . $id);
            }
            exit;
        }
    }
    } // end !is_demo_restricted
}

// Текущий доступ
$current_atype = 'all';
$current_uids  = [];
if (!$is_new) {
    $access_ids = db()->prepare("SELECT user_id FROM event_access WHERE event_id=?");
    $access_ids->execute([$id]);
    $aids = array_column($access_ids->fetchAll(), 'user_id');
    if (in_array(0, $aids)) $current_atype = 'all';
    elseif (!empty($aids)) { $current_atype = 'selected'; $current_uids = $aids; }
    else $current_atype = 'only_me';
}

require_once __DIR__ . '/layout.php';
layout_head($is_new ? t('evtedit_title_new') : t('evtedit_title_edit'), false);
?>

<?php layout_nav($user, 'events'); ?>

<div class="main">
  <div class="sec-header">
    <div class="sec-title"><?= h($is_new ? t('evtedit_title_new') : t('evtedit_title_edit')) ?></div>
    <a href="<?= $is_new ? 'events.php' : 'event.php?id=' . $id ?>" class="btn-outline"><?= h(t('back')) ?></a>
  </div>

  <?php if ($error): ?><div class="alert-err"><?= h($error) ?></div><?php endif; ?>

  <div class="fcard" style="max-width:620px">
    <form method="POST" enctype="multipart/form-data">
      <?= csrf_field() ?>

      <div class="mb-3">
        <label class="form-label"><?= h(t('evtedit_name')) ?> <span style="color:var(--accent)">*</span></label>
        <input type="text" name="title" class="form-control"
               value="<?= h($_POST['title'] ?? $event['title'] ?? '') ?>" required autofocus>
      </div>

      <div class="mb-3">
        <label class="form-label"><?= h(t('evtedit_date')) ?> <span class="opt-label"><?= h(t('form_optional')) ?></span></label>
        <input type="date" name="event_date" class="form-control"
               value="<?= h($_POST['event_date'] ?? ($event['event_date'] ? substr($event['event_date'], 0, 10) : '')) ?>">
      </div>

      <div class="mb-3">
        <label class="form-label"><?= h(t('evtedit_desc')) ?> <span class="opt-label"><?= h(t('form_optional')) ?></span></label>
        <textarea name="description" class="form-control" rows="2"><?= h($_POST['description'] ?? $event['description'] ?? '') ?></textarea>
      </div>

      <!-- Превью -->
      <div class="mb-3">
        <label class="form-label"><?= h(t('evtedit_thumb')) ?> <span class="opt-label"><?= h(t('evtedit_thumb_opt')) ?></span></label>
        <?php if (!$is_new && $event['thumbnail']): ?>
        <div style="margin-bottom:0.6rem">
          <img src="<?= h($event['thumbnail']) ?>" style="width:160px;height:90px;object-fit:cover;border-radius:3px;border:1px solid var(--border)">
          <div style="font-size:0.72rem;color:var(--text-muted);margin-top:3px"><?= h(t('evtedit_thumb_repl')) ?></div>
        </div>
        <?php endif; ?>
        <input type="file" name="thumbnail" class="form-control" accept="image/jpeg,image/png,image/webp">
      </div>

      <!-- Доступ -->
      <div class="mb-4">
        <label class="form-label"><?= h(t('evtedit_who_sees')) ?></label>
        <div class="access-box">
          <div class="access-btns">
            <button type="button" class="access-btn <?= $current_atype === 'all' ? 'active' : '' ?>"
                    data-type="all" onclick="setAccess('all')"><?= h(t('access_all')) ?></button>
            <button type="button" class="access-btn <?= $current_atype === 'selected' ? 'active' : '' ?>"
                    data-type="selected" onclick="setAccess('selected')"><?= h(t('access_selected')) ?></button>
            <button type="button" class="access-btn <?= $current_atype === 'only_me' ? 'active' : '' ?>"
                    data-type="only_me" onclick="setAccess('only_me')"><?= h(t('access_only_me')) ?></button>
          </div>
          <input type="hidden" name="access_type" id="access_type" value="<?= h($current_atype) ?>">
          <div id="memberList" class="member-list"
               style="opacity:<?= $current_atype === 'selected' ? '1' : '0.35' ?>;
                      pointer-events:<?= $current_atype === 'selected' ? 'auto' : 'none' ?>">
            <?php foreach ($others as $m):
              $checked = in_array($m['id'], $current_uids);
            ?>
            <div class="member-row <?= $checked ? 'on' : '' ?>">
              <div class="avatar av" style="background:<?= h($m['color']) ?>"><?= h(initials($m['display_name'])) ?></div>
              <span class="mname"><?= h($m['display_name']) ?></span>
              <div class="chk">
                <svg width="10" height="8" viewBox="0 0 10 8" fill="none">
                  <path d="M1 3.5l2.5 2.5 5-5" stroke="var(--bg)" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
              </div>
              <input type="checkbox" name="user_ids[]" value="<?= $m['id'] ?>" <?= $checked ? 'checked' : '' ?> style="display:none">
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <button type="submit" class="btn-gold"><?= h($is_new ? t('evtedit_btn_create') : t('btn_save')) ?></button>
    </form>
  </div>
</div>

<style>
.opt-label { font-weight:300; text-transform:none; letter-spacing:0; font-size:0.7rem; }
</style>

<?php layout_foot(); ?>
