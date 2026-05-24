<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
session_start();
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }
load_language();

// AJAX проверка дубликата
if (isset($_GET['check_yt_id'])) {
    $yt_id = trim($_GET['check_yt_id']);
    $dup = db()->prepare("SELECT id, title FROM videos WHERE youtube_id = ?");
    $dup->execute([$yt_id]);
    $existing = $dup->fetch();
    header('Content-Type: application/json');
    echo json_encode($existing ? ['duplicate' => true, 'title' => $existing['title']] : ['duplicate' => false]);
    exit;
}

$user        = current_user();
$others      = other_users($user['id']);
$all_members = all_users(); // включая себя — для участников
$error       = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    if (is_demo_restricted()) {
        $error = 'Demo mode — data is not saved.';
    } else
    {
    $url       = trim($_POST['youtube_url'] ?? '');
    $title     = trim($_POST['title'] ?? '');
    $desc      = trim($_POST['description'] ?? '');
    $atype     = $_POST['access_type'] ?? 'only_me';
    $uids      = $_POST['user_ids'] ?? [];
    $filmed_at = trim($_POST['filmed_at'] ?? '');
    $location  = trim($_POST['location'] ?? '');
    $event     = trim($_POST['event'] ?? '');
    $event_id  = (int)($_POST['event_id'] ?? 0);
    $tags      = trim($_POST['tags'] ?? '');
    $part_ids  = $_POST['participant_ids'] ?? [];
    $ext_parts = trim($_POST['external_participants'] ?? '');

    $yt_id = extract_youtube_id($url);

    if (!$yt_id) {
        $error = t('add_err_url');
    } elseif (!$title) {
        $error = t('add_err_title');
    } elseif (!$filmed_at) {
        $error = t('add_err_date');
    } else {
        // Проверка дубликата
        $dup = db()->prepare("SELECT id, title FROM videos WHERE youtube_id = ?");
        $dup->execute([$yt_id]);
        $existing = $dup->fetch();
        if ($existing) {
            $error = sprintf(t('add_err_dup'), $existing['title']);
        } else {
        $stmt = db()->prepare("INSERT INTO videos (user_id, youtube_id, title, description, filmed_at, location, event, event_id, tags) VALUES (?,?,?,?,?,?,?,?,?)");
        $stmt->execute([$user['id'], $yt_id, $title, $desc, $filmed_at ?: null, $location ?: null, $event ?: null, $event_id ?: null, $tags ?: null]);
        $vid = db()->lastInsertId();
        save_video_access((int)$vid, $atype, $uids);
        save_video_participants((int)$vid, $part_ids, $ext_parts);
        telegram_notify_video((int)$vid, $user['id']);
        header('Location: index.php?tab=mine');
        exit;
        }
    }
    } // end !is_demo_restricted
}

require_once __DIR__ . '/layout.php';
layout_head(t('add_title'), false);
?>

<?php layout_nav($user, ''); ?>

<div class="main">
  <div class="sec-header">
    <div class="sec-title"><?= h(t('add_title')) ?></div>
    <a href="index.php" class="btn-outline"><?= h(t('back')) ?></a>
  </div>

  <?php if ($error): ?><div class="alert-err"><?= h($error) ?></div><?php endif; ?>

  <div class="fcard" style="max-width:620px">
    <form method="POST" id="addForm">
      <?= csrf_field() ?>

      <!-- YouTube URL -->
      <div class="mb-3">
        <label class="form-label"><?= h(t('form_yt_url')) ?></label>
        <div style="position:relative">
          <input type="url" name="youtube_url" id="youtube_url" class="form-control"
                 placeholder="https://youtube.com/watch?v=..." value="<?= h($_POST['youtube_url'] ?? '') ?>" required autofocus>
          <div id="apiSpinner" style="display:none;position:absolute;right:10px;top:50%;transform:translateY(-50%);font-size:0.75rem;color:var(--text-muted)"><?= h(t('add_js_loading')) ?></div>
        </div>
        <div id="dupWarning" style="display:none;margin-top:0.4rem;padding:0.5rem 0.8rem;background:rgba(192,57,43,.15);border:1px solid rgba(192,57,43,.3);color:#e07060;border-radius:3px;font-size:0.82rem"></div>
        <div id="ytPreview" class="url-preview" style="display:none"></div>
      </div>

      <!-- Название -->
      <div class="mb-3">
        <label class="form-label"><?= h(t('form_title_label')) ?></label>
        <input type="text" name="title" id="field_title" class="form-control"
               placeholder="<?= h(t('form_title_label')) ?>..." value="<?= h($_POST['title'] ?? '') ?>" required>
      </div>

      <!-- Описание -->
      <div class="mb-3">
        <label class="form-label"><?= h(t('form_desc_label')) ?> <span class="opt-label"><?= h(t('form_optional')) ?></span></label>
        <textarea name="description" id="field_description" class="form-control" rows="2"
                  placeholder="..."></textarea>
      </div>

      <!-- Метаданные -->
      <div style="border:1px solid var(--border);border-radius:4px;padding:1rem;margin-bottom:1rem;background:var(--surface2)">
        <div style="font-size:0.72rem;text-transform:uppercase;letter-spacing:0.1em;color:var(--text-muted);font-weight:600;margin-bottom:0.9rem"><?= h(t('form_about')) ?></div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem" class="meta-grid">

          <!-- Дата съёмки -->
          <div>
            <label class="form-label"><?= h(t('form_filmed_at')) ?> <span style="color:var(--accent)">*</span></label>
            <input type="date" name="filmed_at" id="field_filmed_at" class="form-control"
                   value="<?= h($_POST['filmed_at'] ?? '') ?>" required>
            <div style="font-size:0.68rem;color:var(--text-muted);margin-top:3px"><?= h(t('form_filmed_at_hint')) ?></div>
          </div>

          <!-- Место -->
          <div>
            <label class="form-label"><?= h(t('form_location')) ?> <span class="opt-label"><?= h(t('form_optional')) ?></span></label>
            <input type="text" name="location" class="form-control"
                   placeholder="<?= h(t('form_location_ph')) ?>" value="<?= h($_POST['location'] ?? '') ?>">
          </div>

          <!-- Событие — текст (только когда не выбрана привязка) -->
          <div id="event_text_wrap">
            <label class="form-label"><?= h(t('form_event_text')) ?> <span class="opt-label"><?= h(t('form_optional')) ?></span></label>
            <input type="text" name="event" id="event_text" class="form-control"
                   placeholder="<?= h(t('form_event_ph')) ?>" value="<?= h($_POST['event'] ?? '') ?>">
          </div>

          <!-- Привязка к событию из базы -->
          <div>
            <label class="form-label"><?= h(t('form_event_link')) ?> <span class="opt-label"><?= h(t('form_optional')) ?></span></label>
            <?php $all_events = get_all_events(); ?>
            <select name="event_id" id="event_id_select" class="form-control">
              <option value=""><?= h(t('form_event_none')) ?></option>
              <?php foreach ($all_events as $ev): ?>
              <option value="<?= $ev['id'] ?>" <?= ($_POST['event_id'] ?? 0) == $ev['id'] ? 'selected' : '' ?>>
                <?= h($ev['title']) ?><?= $ev['event_date'] ? ' (' . date('d.m.Y', strtotime($ev['event_date'])) . ')' : '' ?>
              </option>
              <?php endforeach; ?>
            </select>
            <div style="font-size:0.68rem;color:var(--text-muted);margin-top:3px">
              <?php /* translators: link to create event */ ?>
              <a href="event_edit.php" style="color:var(--gold)"><?= h(t('form_event_create')) ?></a>
            </div>
          </div>

          <!-- Теги -->
          <div>
            <label class="form-label"><?= h(t('form_tags')) ?> <span class="opt-label"><?= h(t('form_optional')) ?></span></label>
            <input type="text" name="tags" class="form-control"
                   placeholder="<?= h(t('form_tags_ph')) ?>" value="<?= h($_POST['tags'] ?? '') ?>">
          </div>

        </div>
      </div>

      <!-- Участники -->
      <div style="border:1px solid var(--border);border-radius:4px;padding:1rem;margin-bottom:1rem;background:var(--surface2)">
        <div style="font-size:0.72rem;text-transform:uppercase;letter-spacing:0.1em;color:var(--text-muted);font-weight:600;margin-bottom:0.9rem"><?= h(t('form_participants')) ?> <span class="opt-label"><?= h(t('form_optional')) ?></span></div>

        <!-- Члены семьи из системы -->
        <?php if (!empty($all_members)): ?>
        <div style="margin-bottom:0.75rem">
          <div style="font-size:0.72rem;color:var(--text-muted);margin-bottom:0.4rem"><?= h(t('form_family')) ?></div>
          <div style="display:flex;flex-wrap:wrap;gap:0.4rem">
            <?php foreach ($all_members as $m):
              $checked = in_array($m['id'], $_POST['participant_ids'] ?? []);
            ?>
            <label class="part-chip <?= $checked ? 'on' : '' ?>">
              <input type="checkbox" name="participant_ids[]" value="<?= $m['id'] ?>" <?= $checked ? 'checked' : '' ?> style="display:none">
              <div class="avatar av" style="background:<?= h($m['color']) ?>;width:18px;height:18px;font-size:0.5rem"><?= h(initials($m['display_name'])) ?></div>
              <?= h($m['display_name']) ?>
            </label>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>

        <!-- Внешние люди -->
        <div>
          <label class="form-label"><?= h(t('form_ext_people')) ?> <span class="opt-label"><?= h(t('form_ext_hint')) ?></span></label>
          <input type="text" name="external_participants" class="form-control"
                 placeholder="<?= h(t('form_ext_ph')) ?>" value="<?= h($_POST['external_participants'] ?? '') ?>">
        </div>
      </div>
      <div class="mb-4">
        <label class="form-label"><?= h(t('form_who_sees')) ?></label>
        <div class="access-box">
          <div class="access-btns">
            <button type="button" class="access-btn <?= ($_POST['access_type'] ?? 'only_me') === 'all' ? 'active' : '' ?>"
                    data-type="all" onclick="setAccess('all')"><?= h(t('access_all')) ?></button>
            <button type="button" class="access-btn <?= ($_POST['access_type'] ?? '') === 'selected' ? 'active' : '' ?>"
                    data-type="selected" onclick="setAccess('selected')"><?= h(t('access_selected')) ?></button>
            <button type="button" class="access-btn <?= ($_POST['access_type'] ?? 'only_me') === 'only_me' ? 'active' : '' ?>"
                    data-type="only_me" onclick="setAccess('only_me')"><?= h(t('access_only_me')) ?></button>
          </div>
          <input type="hidden" name="access_type" id="access_type" value="<?= h($_POST['access_type'] ?? 'only_me') ?>">

          <div id="memberList" class="member-list"
               style="opacity:<?= ($_POST['access_type'] ?? 'only_me') === 'selected' ? '1' : '0.35' ?>;
                      pointer-events:<?= ($_POST['access_type'] ?? 'only_me') === 'selected' ? 'auto' : 'none' ?>">
            <?php foreach ($others as $m):
              $checked = in_array($m['id'], $_POST['user_ids'] ?? []);
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
            <?php if (empty($others)): ?>
              <div style="font-size:0.8rem;color:var(--text-muted);padding:0.5rem 0">
                <?= h(t('form_no_others')) ?> <a href="admin.php" style="color:var(--gold)"><?= h(t('form_no_others_link')) ?></a>.
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <div style="display:flex;gap:0.7rem">
        <button type="submit" class="btn-gold"><?= h(t('btn_save')) ?></button>
        <a href="index.php" class="btn-outline"><?= h(t('btn_cancel')) ?></a>
      </div>
    </form>
  </div>
</div>

<style>
.opt-label { font-weight:300; text-transform:none; letter-spacing:0; font-size:0.7rem; }
@media(max-width:500px) { .meta-grid { grid-template-columns: 1fr !important; } }
.part-chip {
  display:inline-flex; align-items:center; gap:0.35rem;
  padding:0.25rem 0.6rem; border:1px solid var(--border);
  border-radius:20px; font-size:0.78rem; color:var(--text-muted);
  cursor:pointer; transition:all .15s; user-select:none;
}
.part-chip:hover { border-color:var(--text-muted); color:var(--text); }
.part-chip.on { border-color:var(--gold); color:var(--text); background:rgba(201,168,76,.08); }
</style>

<script>
const YT_API_KEY = '<?= YOUTUBE_API_KEY ?>';
const LANG_LOADING   = <?= json_encode(t('add_js_loading')) ?>;
const LANG_LINK_OK   = <?= json_encode(t('add_js_link_ok')) ?>;
const LANG_YT_LOAD   = <?= json_encode(t('add_js_yt_loading')) ?>;
const LANG_YT_LOADED = <?= json_encode(t('add_js_yt_loaded')) ?>;
const LANG_DUP       = <?= json_encode(t('add_js_dup')) ?>;

const urlInput = document.getElementById('youtube_url');
let fetchTimer = null;

if (urlInput) {
  urlInput.addEventListener('input', function() {
    clearTimeout(fetchTimer);
    const val = this.value.trim();
    const match = val.match(/(?:v=|youtu\.be\/|embed\/|shorts\/)([a-zA-Z0-9_-]{11})/);
    const preview = document.getElementById('ytPreview');

    if (!match) {
      if (preview) preview.style.display = 'none';
      return;
    }

    const ytId = match[1];

    // Сбрасываем предупреждение о дубликате
    const dupWarning = document.getElementById('dupWarning');
    if (dupWarning) dupWarning.style.display = 'none';

    // Сбрасываем поля которые заполнялись автоматически
    document.getElementById('field_title').value = '';
    document.getElementById('field_description').value = '';

    // Показываем превью сразу
    if (preview) {
      preview.innerHTML = '<img src="https://img.youtube.com/vi/'+ytId+'/mqdefault.jpg" onerror="this.style.display=\'none\'"><div><div class="pt">'+LANG_LINK_OK+'</div><div class="pc">'+LANG_YT_LOAD+'</div></div>';
      preview.style.display = 'flex';
    }

    // Через 600ms запрашиваем API и проверяем дубликат
    fetchTimer = setTimeout(() => {
      checkDuplicate(ytId);
      fetchYouTubeData(ytId);
    }, 600);
  });
}

function checkDuplicate(ytId) {
  fetch('add.php?check_yt_id=' + ytId)
    .then(r => r.json())
    .then(data => {
      const dupWarning = document.getElementById('dupWarning');
      if (data.duplicate) {
        dupWarning.textContent = LANG_DUP.replace('%s', data.title);
        dupWarning.style.display = 'block';
      } else {
        dupWarning.style.display = 'none';
      }
    })
    .catch(() => {});
}

function fetchYouTubeData(ytId) {
  const spinner = document.getElementById('apiSpinner');
  if (spinner) spinner.style.display = 'block';

  fetch(`https://www.googleapis.com/youtube/v3/videos?part=snippet&id=${ytId}&key=${YT_API_KEY}`)
    .then(r => r.json())
    .then(data => {
      if (spinner) spinner.style.display = 'none';
      if (!data.items || !data.items.length) return;

      const snippet = data.items[0].snippet;

      // Заполняем название если пустое
      const titleField = document.getElementById('field_title');
      if (titleField && !titleField.value.trim()) {
        titleField.value = snippet.title || '';
      }

      // Заполняем описание если пустое (берём первые 1500 символов)
      const descField = document.getElementById('field_description');
      if (descField && !descField.value.trim() && snippet.description) {
        descField.value = snippet.description.substring(0, 1500);
      }

      // Обновляем превью
      const preview = document.getElementById('ytPreview');
      if (preview) {
        preview.querySelector('.pc').textContent = LANG_YT_LOADED;
      }
    })
    .catch(() => {
      if (spinner) spinner.style.display = 'none';
    });
}
</script>

<script>
document.querySelectorAll('.part-chip').forEach(chip => {
  chip.addEventListener('click', function() {
    this.classList.toggle('on');
    const cb = this.querySelector('input[type=checkbox]');
    if (cb) cb.checked = !cb.checked;
  });
});

// Взаимоисключение: текстовое поле события и дропдаун
(function() {
  const sel = document.getElementById('event_id_select');
  const wrap = document.getElementById('event_text_wrap');
  const txt  = document.getElementById('event_text');
  if (!sel || !wrap || !txt) return;

  function syncFields() {
    if (sel.value) {
      wrap.style.display = 'none';
      txt.value = '';
    } else {
      wrap.style.display = '';
    }
  }

  txt.addEventListener('input', function() {
    if (this.value.trim()) {
      sel.value = '';
    }
  });

  sel.addEventListener('change', syncFields);
  syncFields();
})();
</script>

<?php layout_foot(); ?>
