<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
session_start();
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }

$user = current_user();
$id   = (int)($_GET['id'] ?? 0);

$stmt = db()->prepare("SELECT * FROM videos WHERE id = ?");
$stmt->execute([$id]);
$video = $stmt->fetch();

if (!$video || $video['user_id'] != $user['id']) {
    header('Location: index.php');
    exit;
}

$others      = other_users($user['id']);
$all_members = all_users();
$current_ids = get_video_access($id);
$is_pub      = in_array(0, $current_ids);
$current_uids = array_filter($current_ids, fn($x) => $x !== 0);

if ($is_pub) $current_atype = 'all';
elseif (!empty($current_uids)) $current_atype = 'selected';
else $current_atype = 'only_me';

// Текущие участники
$current_parts = get_video_participants($id);
$current_part_user_ids = array_column(array_filter($current_parts, fn($p) => $p['user_id']), 'user_id');
$current_ext_names = implode(', ', array_column(array_filter($current_parts, fn($p) => !$p['user_id']), 'name'));

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title     = trim($_POST['title'] ?? '');
    $desc      = trim($_POST['description'] ?? '');
    $atype     = $_POST['access_type'] ?? 'all';
    $uids      = $_POST['user_ids'] ?? [];
    $filmed_at = trim($_POST['filmed_at'] ?? '');
    $location  = trim($_POST['location'] ?? '');
    $event     = trim($_POST['event'] ?? '');
    $event_id  = (int)($_POST['event_id'] ?? 0);
    $tags      = trim($_POST['tags'] ?? '');
    $part_ids  = $_POST['participant_ids'] ?? [];
    $ext_parts = trim($_POST['external_participants'] ?? '');

    if (!$title) {
        $error = t('edit_err_title');
    } elseif (!$filmed_at) {
        $error = t('edit_err_date');
    } else {
        db()->prepare("UPDATE videos SET title=?, description=?, filmed_at=?, location=?, event=?, event_id=?, tags=? WHERE id=?")
           ->execute([$title, $desc, $filmed_at ?: null, $location ?: null, $event ?: null, $event_id ?: null, $tags ?: null, $id]);
        save_video_access($id, $atype, $uids);
        save_video_participants($id, $part_ids, $ext_parts);
        header('Location: index.php?tab=mine');
        exit;
    }
    $current_atype = $atype;
    $current_uids  = $uids;
}

require_once __DIR__ . '/layout.php';
layout_head(t('edit_title'), false);
?>

<?php layout_nav($user, ''); ?>

<div class="main">
  <div class="sec-header">
    <div class="sec-title"><?= h(t('edit_title')) ?></div>
    <a href="index.php?tab=mine" class="btn-outline"><?= h(t('back')) ?></a>
  </div>

  <?php if ($error): ?><div class="alert-err"><?= h($error) ?></div><?php endif; ?>

  <div class="fcard" style="max-width:620px">
    <!-- Preview -->
    <div class="url-preview" style="display:flex;margin-bottom:1.2rem">
      <img src="https://img.youtube.com/vi/<?= h($video['youtube_id']) ?>/mqdefault.jpg" alt="">
      <div>
        <div class="pt">youtube.com/watch?v=<?= h($video['youtube_id']) ?></div>
        <div class="pc"><?= h(t('edit_url_hint')) ?></div>
      </div>
    </div>

    <form method="POST">

      <!-- Название -->
      <div class="mb-3">
        <label class="form-label"><?= h(t('form_title_label')) ?></label>
        <input type="text" name="title" class="form-control"
               value="<?= h($_POST['title'] ?? $video['title']) ?>" required>
      </div>

      <!-- Описание -->
      <div class="mb-3">
        <label class="form-label"><?= h(t('form_desc_label')) ?> <span class="opt-label"><?= h(t('form_optional')) ?></span></label>
        <textarea name="description" class="form-control" rows="2"><?= h($_POST['description'] ?? $video['description'] ?? '') ?></textarea>
      </div>

      <!-- Метаданные -->
      <div style="border:1px solid var(--border);border-radius:4px;padding:1rem;margin-bottom:1rem;background:var(--surface2)">
        <div style="font-size:0.72rem;text-transform:uppercase;letter-spacing:0.1em;color:var(--text-muted);font-weight:600;margin-bottom:0.9rem"><?= h(t('form_about')) ?></div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem" class="meta-grid">

          <div>
            <label class="form-label"><?= h(t('form_filmed_at')) ?> <span style="color:var(--accent)">*</span></label>
            <input type="date" name="filmed_at" class="form-control"
                   value="<?= h($_POST['filmed_at'] ?? ($video['filmed_at'] ? substr($video['filmed_at'], 0, 10) : '')) ?>" required>
            <div style="font-size:0.68rem;color:var(--text-muted);margin-top:3px"><?= h(t('form_filmed_at_hint')) ?></div>
          </div>

          <div>
            <label class="form-label"><?= h(t('form_location')) ?> <span class="opt-label"><?= h(t('form_optional')) ?></span></label>
            <input type="text" name="location" class="form-control"
                   placeholder="<?= h(t('form_location_ph')) ?>" value="<?= h($_POST['location'] ?? $video['location'] ?? '') ?>">
          </div>

          <!-- Событие — текст (только когда не выбрана привязка) -->
          <div id="event_text_wrap">
            <label class="form-label"><?= h(t('form_event_text')) ?> <span class="opt-label"><?= h(t('form_optional')) ?></span></label>
            <input type="text" name="event" id="event_text" class="form-control"
                   placeholder="<?= h(t('form_event_ph')) ?>" value="<?= h($_POST['event'] ?? $video['event'] ?? '') ?>">
          </div>

          <!-- Привязка к событию из базы -->
          <div>
            <label class="form-label"><?= h(t('form_event_link')) ?> <span class="opt-label"><?= h(t('form_optional')) ?></span></label>
            <?php $all_events = get_all_events(); ?>
            <select name="event_id" id="event_id_select" class="form-control">
              <option value=""><?= h(t('form_event_none')) ?></option>
              <?php foreach ($all_events as $ev): ?>
              <option value="<?= $ev['id'] ?>" <?= ($_POST['event_id'] ?? $video['event_id'] ?? 0) == $ev['id'] ? 'selected' : '' ?>>
                <?= h($ev['title']) ?><?= $ev['event_date'] ? ' (' . date('d.m.Y', strtotime($ev['event_date'])) . ')' : '' ?>
              </option>
              <?php endforeach; ?>
            </select>
            <div style="font-size:0.68rem;color:var(--text-muted);margin-top:3px">
              <a href="event_edit.php" style="color:var(--gold)"><?= h(t('form_event_create')) ?></a>
            </div>
          </div>

          <div>
            <label class="form-label"><?= h(t('form_tags')) ?> <span class="opt-label"><?= h(t('form_optional')) ?></span></label>
            <input type="text" name="tags" class="form-control"
                   placeholder="<?= h(t('form_tags_ph')) ?>" value="<?= h($_POST['tags'] ?? $video['tags'] ?? '') ?>">
          </div>

        </div>
      </div>

      <!-- Участники -->
      <div style="border:1px solid var(--border);border-radius:4px;padding:1rem;margin-bottom:1rem;background:var(--surface2)">
        <div style="font-size:0.72rem;text-transform:uppercase;letter-spacing:0.1em;color:var(--text-muted);font-weight:600;margin-bottom:0.9rem"><?= h(t('form_participants')) ?> <span class="opt-label"><?= h(t('form_optional')) ?></span></div>

        <?php if (!empty($all_members)): ?>
        <div style="margin-bottom:0.75rem">
          <div style="font-size:0.72rem;color:var(--text-muted);margin-bottom:0.4rem"><?= h(t('form_family')) ?></div>
          <div style="display:flex;flex-wrap:wrap;gap:0.4rem">
            <?php foreach ($all_members as $m):
              $checked = in_array($m['id'], $_POST['participant_ids'] ?? $current_part_user_ids);
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

        <div>
          <label class="form-label"><?= h(t('form_ext_people')) ?> <span class="opt-label"><?= h(t('form_ext_hint')) ?></span></label>
          <input type="text" name="external_participants" class="form-control"
                 placeholder="<?= h(t('form_ext_ph')) ?>" value="<?= h($_POST['external_participants'] ?? $current_ext_names) ?>">
        </div>
      </div>

      <!-- Доступ -->
      <div class="mb-4">
        <label class="form-label"><?= h(t('form_who_sees')) ?></label>
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
              $checked = in_array($m['id'], (array)$current_uids);
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

      <div style="display:flex;gap:0.7rem">
        <button type="submit" class="btn-gold"><?= h(t('btn_save')) ?></button>
        <a href="index.php?tab=mine" class="btn-outline"><?= h(t('btn_cancel')) ?></a>
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
