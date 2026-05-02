<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
session_start();
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }
load_language();
$user = current_user();
if (!$user) { session_destroy(); header('Location: login.php'); exit; }

// AJAX — проверить превью для фото или альбома
if (isset($_GET['check_preview'])) {
    $url  = trim($_GET['url'] ?? '');
    $type = trim($_GET['type'] ?? '');
    header('Content-Type: application/json');
    if (!$url) { echo json_encode(['ok' => false]); exit; }

    // Для прямых ссылок на картинки — сразу возвращаем как превью
    $ext = strtolower(pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
    if (in_array($ext, ['jpg','jpeg','png','webp'])) {
        echo json_encode(['ok' => true, 'img' => $url]);
        exit;
    }

    // Для страниц — пробуем og:image
    $ctx  = stream_context_create(['http' => ['timeout' => 6, 'user_agent' => 'Mozilla/5.0 (compatible; bot)', 'follow_location' => true]]);
    $html = @file_get_contents($url, false, $ctx);
    if ($html && preg_match('/<meta[^>]+property="og:image"[^>]+content="([^"]+)"/i', $html, $m)) {
        echo json_encode(['ok' => true, 'img' => $m[1]]);
    } else {
        echo json_encode(['ok' => false]);
    }
    exit;
}

$event_id = (int)($_GET['event_id'] ?? 0);
if (!$event_id) { header('Location: events.php'); exit; }
$event = get_event($event_id);
if (!$event) { header('Location: events.php'); exit; }

$others = other_users($user['id']);
$error  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $type       = $_POST['type'] ?? 'link';
    $url        = trim($_POST['url'] ?? '');
    $title      = trim($_POST['title'] ?? '');
    $desc       = trim($_POST['description'] ?? '');
    $atype      = $_POST['access_type'] ?? 'all';
    $uids       = $_POST['user_ids'] ?? [];
    $og_img_url = trim($_POST['og_image_url'] ?? '');
    if (!in_array($type, ['photo','album','link'])) $type = 'link';

    if (!$url) {
        $error = t('media_err_url');
    } else {
        $thumbnail = null;

        // Для фото — сначала пробуем og_image_url найденный при AJAX-проверке
        if ($type === 'photo' && $og_img_url) {
            $thumbnail = make_thumbnail($og_img_url, 'media', true);
        }
        // Если og:image не дал результат — пробуем прямую ссылку
        if ($type === 'photo' && !$thumbnail) {
            $ext = strtolower(pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg','jpeg','png','webp'])) {
                $thumbnail = make_thumbnail($url, 'media', true);
            }
        }

        // Для альбома — используем og:image найденный при AJAX-проверке
        if ($type === 'album' && $og_img_url) {
            $thumbnail = make_thumbnail($og_img_url, 'media', true);
        }

        // Если пользователь загрузил своё превью — оно приоритетнее
        if (!empty($_FILES['thumbnail']['tmp_name'])) {
            $file = $_FILES['thumbnail'];
            $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg','jpeg','png','webp']) && $file['size'] <= 10*1024*1024) {
                $t = make_thumbnail($file['tmp_name'], 'media');
                if ($t) $thumbnail = $t;
            } else {
                $error = t('media_err_thumb');
            }
        }

        if (!$error) {
            db()->prepare("INSERT INTO media (event_id, user_id, type, url, title, description, thumbnail) VALUES (?,?,?,?,?,?,?)")
               ->execute([$event_id, $user['id'], $type, $url, $title ?: null, $desc ?: null, $thumbnail]);
            $media_id = (int)db()->lastInsertId();
            db()->prepare("DELETE FROM media_access WHERE media_id=?")->execute([$media_id]);
            if ($atype === 'all') {
                db()->prepare("INSERT INTO media_access (media_id, user_id) VALUES (?,0)")->execute([$media_id]);
            } elseif ($atype === 'selected' && !empty($uids)) {
                $stmt = db()->prepare("INSERT INTO media_access (media_id, user_id) VALUES (?,?)");
                foreach ($uids as $uid) $stmt->execute([$media_id, (int)$uid]);
            }
            telegram_notify_media((int)$media_id, $event_id, $user['id']);
            header('Location: event.php?id=' . $event_id);
            exit;
        }
    }
}

require_once __DIR__ . '/layout.php';
layout_head(t('media_add_title'), false);
?>

<?php layout_nav($user, 'events'); ?>

<div class="main">
  <div class="sec-header">
    <div class="sec-title"><?= sprintf(t('media_add_to'), h($event['title'])) ?></div>
    <a href="event.php?id=<?= $event_id ?>" class="btn-outline"><?= h(t('back')) ?></a>
  </div>

  <?php if ($error): ?><div class="alert-err"><?= h($error) ?></div><?php endif; ?>

  <div class="fcard" style="max-width:620px">
    <form method="POST" enctype="multipart/form-data">
      <?= csrf_field() ?>

      <div class="mb-3">
        <label class="form-label"><?= h(t('media_type_label')) ?></label>
        <div class="access-btns" style="margin-bottom:0" id="typeBtns">
          <button type="button" class="access-btn active" data-type="link"  onclick="setType('link')"><?= h(t('media_type_link_btn')) ?></button>
          <button type="button" class="access-btn"        data-type="photo" onclick="setType('photo')"><?= h(t('media_type_photo_btn')) ?></button>
          <button type="button" class="access-btn"        data-type="album" onclick="setType('album')"><?= h(t('media_type_album_btn')) ?></button>
        </div>
        <input type="hidden" name="type" id="media_type" value="link">
      </div>

      <div class="mb-3">
        <label class="form-label" id="url_label"><?= h(t('media_url_link')) ?> <span style="color:var(--accent)">*</span></label>
        <input type="url" name="url" id="media_url" class="form-control"
               placeholder="https://..." value="<?= h($_POST['url'] ?? '') ?>" required>
        <!-- Скрытое поле для URL обложки найденной через og:image -->
        <input type="hidden" name="og_image_url" id="og_image_url" value="">
        <div id="url_hint" style="font-size:0.68rem;color:var(--text-muted);margin-top:3px"></div>
        <!-- Блок результата проверки обложки альбома -->
        <div id="album_preview" style="display:none;margin-top:0.6rem;padding:0.6rem;background:var(--surface2);border:1px solid var(--border);border-radius:3px;font-size:0.82rem">
          <div id="album_preview_content"></div>
        </div>
      </div>

      <div class="mb-3">
        <label class="form-label"><?= h(t('form_title_label')) ?> <span class="opt-label"><?= h(t('form_optional')) ?></span></label>
        <input type="text" name="title" class="form-control"
               placeholder="<?= h(t('add_title_ph')) ?>" value="<?= h($_POST['title'] ?? '') ?>">
      </div>

      <div class="mb-3">
        <label class="form-label"><?= h(t('form_desc_label')) ?> <span class="opt-label"><?= h(t('form_optional')) ?></span></label>
        <textarea name="description" class="form-control" rows="2"><?= h($_POST['description'] ?? '') ?></textarea>
      </div>

      <div class="mb-3">
        <label class="form-label" id="thumb_label"><?= h(t('media_thumb_lbl')) ?> <span class="opt-label"><?= h(t('media_thumb_opt')) ?></span></label>
        <div id="thumb_hint" style="font-size:0.72rem;color:var(--text-muted);margin-bottom:0.4rem"></div>
        <input type="file" name="thumbnail" class="form-control" accept="image/jpeg,image/png,image/webp">
      </div>

      <div class="mb-4">
        <label class="form-label"><?= h(t('form_who_sees')) ?></label>
        <div class="access-box">
          <div class="access-btns">
            <button type="button" class="access-btn active" data-type="all"      onclick="setAccess('all')"><?= h(t('access_all')) ?></button>
            <button type="button" class="access-btn"        data-type="selected" onclick="setAccess('selected')"><?= h(t('access_selected')) ?></button>
            <button type="button" class="access-btn"        data-type="only_me"  onclick="setAccess('only_me')"><?= h(t('access_only_me')) ?></button>
          </div>
          <input type="hidden" name="access_type" id="access_type" value="all">
          <div id="memberList" class="member-list" style="opacity:0.35;pointer-events:none">
            <?php foreach ($others as $m): ?>
            <div class="member-row">
              <div class="avatar av" style="background:<?= h($m['color']) ?>"><?= h(initials($m['display_name'])) ?></div>
              <span class="mname"><?= h($m['display_name']) ?></span>
              <div class="chk"><svg width="10" height="8" viewBox="0 0 10 8" fill="none"><path d="M1 3.5l2.5 2.5 5-5" stroke="var(--bg)" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></div>
              <input type="checkbox" name="user_ids[]" value="<?= $m['id'] ?>" style="display:none">
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <button type="submit" class="btn-gold"><?= h(t('btn_save')) ?></button>
    </form>
  </div>
</div>

<style>.opt-label { font-weight:300; text-transform:none; letter-spacing:0; font-size:0.7rem; }</style>

<script>
const LANG_MEDIA_URL_LINK  = <?= json_encode(t('media_url_link')) ?>;
const LANG_MEDIA_URL_PHOTO = <?= json_encode(t('media_url_photo')) ?>;
const LANG_MEDIA_URL_ALBUM = <?= json_encode(t('media_url_album')) ?>;
const LANG_HINT_LINK       = <?= json_encode(t('media_hint_link')) ?>;
const LANG_HINT_PHOTO      = <?= json_encode(t('media_hint_photo')) ?>;
const LANG_HINT_ALBUM      = <?= json_encode(t('media_hint_album')) ?>;
const LANG_HINT_MEDIA      = <?= json_encode(t('media_hint_media')) ?>;
const LANG_CHECKING        = <?= json_encode(t('media_js_checking')) ?>;
const LANG_PREVIEW_OK      = <?= json_encode(t('media_js_ok')) ?>;
const LANG_PREVIEW_OK_HINT = <?= json_encode(t('media_js_ok_hint')) ?>;
const LANG_PREVIEW_NOOK    = <?= json_encode(t('media_js_nook')) ?>;
const LANG_PREVIEW_UPLOAD  = <?= json_encode(t('media_js_upload')) ?>;
const LANG_PREVIEW_ERROR   = <?= json_encode(t('media_js_error')) ?>;

const typeConfig = {
  link:  { urlLabel: LANG_MEDIA_URL_LINK,  urlHint: '',             thumbHint: LANG_HINT_LINK,  checkPreview: false },
  photo: { urlLabel: LANG_MEDIA_URL_PHOTO, urlHint: LANG_HINT_PHOTO, thumbHint: LANG_HINT_MEDIA, checkPreview: true },
  album: { urlLabel: LANG_MEDIA_URL_ALBUM, urlHint: LANG_HINT_ALBUM, thumbHint: LANG_HINT_MEDIA, checkPreview: true },
};
let currentType = 'link';
let previewTimer = null;

function setType(type) {
  currentType = type;
  document.querySelectorAll('#typeBtns .access-btn').forEach(b => b.classList.toggle('active', b.dataset.type === type));
  document.getElementById('media_type').value = type;
  const cfg = typeConfig[type];
  document.getElementById('url_label').textContent  = cfg.urlLabel + ' *';
  document.getElementById('url_hint').textContent   = cfg.urlHint;
  document.getElementById('thumb_hint').textContent = cfg.thumbHint;
  document.getElementById('album_preview').style.display = 'none';
  document.getElementById('og_image_url').value = '';
}
setType('link');

const urlInput = document.getElementById('media_url');
if (urlInput) {
  urlInput.addEventListener('input', function() {
    clearTimeout(previewTimer);
    if (!typeConfig[currentType].checkPreview) return;
    const val = this.value.trim();
    if (!val) {
      document.getElementById('album_preview').style.display = 'none';
      document.getElementById('og_image_url').value = '';
      return;
    }
    const preview = document.getElementById('album_preview');
    const content = document.getElementById('album_preview_content');
    preview.style.display = 'block';
    content.innerHTML = '<span style="color:var(--text-muted)">' + LANG_CHECKING + '</span>';
    document.getElementById('og_image_url').value = '';

    previewTimer = setTimeout(() => {
      fetch('media_add.php?check_preview=1&type=' + currentType + '&url=' + encodeURIComponent(val))
        .then(r => r.json())
        .then(data => {
          if (data.ok) {
            document.getElementById('og_image_url').value = data.img;
            content.innerHTML = '<div style="display:flex;align-items:center;gap:0.7rem">'
              + '<img src="' + data.img + '" style="width:80px;height:45px;object-fit:cover;border-radius:2px;flex-shrink:0" onerror="this.style.display=\'none\'">'
              + '<div><div style="color:var(--gold);font-weight:600">' + LANG_PREVIEW_OK + '</div>'
              + '<div style="color:var(--text-muted);font-size:0.75rem;margin-top:2px">' + LANG_PREVIEW_OK_HINT + '</div></div>'
              + '</div>';
          } else {
            document.getElementById('og_image_url').value = '';
            content.innerHTML = '<div style="color:var(--text-muted)">' + LANG_PREVIEW_NOOK + ' '
              + '<strong style="color:var(--text)">' + LANG_PREVIEW_UPLOAD + '</strong></div>';
          }
        })
        .catch(() => {
          document.getElementById('og_image_url').value = '';
          content.innerHTML = '<span style="color:var(--text-muted)">' + LANG_PREVIEW_ERROR + '</span>';
        });
    }, 800);
  });
}
</script>

<?php layout_foot(); ?>
