<?php
// layout.php — подключается во всех страницах
// Использование: layout_head('Заголовок страницы') и layout_foot()

function layout_head(string $title = '', bool $require_auth = true): void {
    if (session_status() === PHP_SESSION_NONE) session_start();
    require_once __DIR__ . '/db.php';

    if ($require_auth && !isset($_SESSION['user_id'])) {
        if (!check_remember_token()) {
            header('Location: login.php');
            exit;
        }
    }

    // Обновляем last_seen раз в 5 минут чтобы не нагружать БД
    if (isset($_SESSION['user_id']) && empty($_SESSION['seen_updated'])) {
        update_last_seen($_SESSION['user_id']);
        $_SESSION['seen_updated'] = true;
    }

    $site_name = SITE_NAME;
    $full_title = $title ? "$title — $site_name" : $site_name;
    $theme = 'dark';
    if (isset($_SESSION['user_id'])) {
        $u = db()->prepare("SELECT theme FROM users WHERE id=?");
        $u->execute([$_SESSION['user_id']]);
        $row = $u->fetch();
        if ($row && $row['theme']) $theme = $row['theme'];
    }
    // load_language() сам читает язык из БД с try/catch — безопасно при любой схеме
    load_language();
    ?>
<!DOCTYPE html>
<html lang="<?= h(current_lang()) ?>" data-theme="<?= h($theme) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= h($full_title) ?></title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
<style>
:root {
  --bg: #0f0e0c;
  --surface: #1a1814;
  --surface2: #242018;
  --gold: #c9a84c;
  --gold-light: #e8c97a;
  --text: #e8e2d4;
  --text-muted: #8a8070;
  --border: #2e2a22;
  --accent: #d4682a;
  --danger: #c0392b;
}
[data-theme="light"] {
  --bg: #f5f2ec;
  --surface: #ffffff;
  --surface2: #ede9e0;
  --gold: #a07828;
  --gold-light: #c9a84c;
  --text: #1a1610;
  --text-muted: #7a7060;
  --border: #d8d0c0;
  --accent: #d4682a;
  --danger: #c0392b;
}
* { box-sizing: border-box; }
body {
  background: var(--bg);
  color: var(--text);
  font-family: 'Segoe UI', system-ui, sans-serif;
  font-weight: 300;
  min-height: 100vh;
}

/* NAV */
.app-nav {
  background: var(--surface);
  border-bottom: 1px solid var(--border);
  padding: 0.75rem 1.5rem;
  display: flex;
  align-items: center;
  gap: 1rem;
  position: sticky;
  top: 0;
  z-index: 100;
}
.app-nav .brand {
  font-size: 1.1rem;
  font-weight: 600;
  color: var(--gold);
  text-decoration: none;
  letter-spacing: 0.03em;
  white-space: nowrap;
}
.search-wrap { flex: 1; max-width: 340px; margin: 0 auto; }
.search-wrap input {
  width: 100%;
  background: var(--bg);
  border: 1px solid var(--border);
  color: var(--text);
  padding: 0.4rem 0.9rem;
  border-radius: 20px;
  font-size: 0.85rem;
  transition: border-color 0.2s;
}
.search-wrap input:focus { outline: none; border-color: var(--gold); }
.search-wrap input::placeholder { color: var(--text-muted); opacity: 0.6; }
.nav-user { display: flex; align-items: center; gap: 0.5rem; margin-left: auto; }
.avatar {
  width: 32px; height: 32px;
  border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  font-size: 0.7rem; font-weight: 700;
  color: #0f0e0c;
  flex-shrink: 0;
}
.nav-user .name { font-size: 0.82rem; color: var(--text); }
.nav-user a { color: var(--text-muted); font-size: 0.75rem; text-decoration: none; }
.nav-user a:hover { color: var(--text); }
.nav-user a[title] { font-size: 1.1rem; line-height: 1; }

/* TABS */
.app-tabs {
  background: var(--surface);
  border-bottom: 1px solid var(--border);
  display: flex;
  padding: 0 1.5rem;
  overflow-x: auto;
  gap: 0;
}
.app-tabs::-webkit-scrollbar { display: none; }
.app-tabs a {
  color: var(--text-muted);
  text-decoration: none;
  padding: 0.7rem 1rem;
  font-size: 0.78rem;
  font-weight: 600;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  border-bottom: 2px solid transparent;
  white-space: nowrap;
  transition: all 0.15s;
}
.app-tabs a:hover { color: var(--text); }
.app-tabs a.active { color: var(--gold); border-bottom-color: var(--gold); }

/* MAIN */
.main { padding: 1.5rem; max-width: 1600px; margin: 0 auto; }

/* SECTION HEADER */
.sec-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1.2rem;
  gap: 1rem;
}
.sec-title { font-size: 1.1rem; font-weight: 600; color: var(--text); }
.sec-title small { font-size: 0.72rem; color: var(--text-muted); font-weight: 300; margin-left: 0.4rem; }

/* BUTTONS */
.btn-gold {
  background: var(--gold); color: var(--bg); border: none;
  padding: 0.5rem 1.2rem; font-size: 0.78rem; font-weight: 700;
  letter-spacing: 0.08em; text-transform: uppercase;
  border-radius: 2px; text-decoration: none; display: inline-block;
  transition: background 0.15s;
  white-space: nowrap;
}
.btn-gold:hover { background: var(--gold-light); color: var(--bg); }

.btn-outline {
  background: transparent; color: var(--text-muted);
  border: 1px solid var(--border);
  padding: 0.45rem 1rem; font-size: 0.75rem; font-weight: 600;
  letter-spacing: 0.08em; text-transform: uppercase;
  border-radius: 2px; text-decoration: none; display: inline-block;
  transition: all 0.15s;
}
.btn-outline:hover { border-color: var(--text-muted); color: var(--text); }

.btn-danger-sm {
  background: transparent; color: var(--text-muted);
  border: 1px solid var(--border);
  padding: 0.3rem 0.7rem; font-size: 0.72rem;
  border-radius: 2px; text-decoration: none; display: inline-block;
  transition: all 0.15s;
}
.btn-danger-sm:hover { border-color: var(--danger); color: var(--danger); }

/* VIDEO GRID */
.video-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
  gap: 1rem;
}
.vcard {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 4px;
  overflow: hidden;
  transition: border-color 0.2s, transform 0.2s, box-shadow 0.2s;
}
.vcard:hover {
  border-color: var(--gold);
  transform: translateY(-2px);
  box-shadow: 0 6px 24px rgba(0,0,0,.4);
}
.vthumb {
  position: relative;
  aspect-ratio: 16/9;
  background: var(--surface2);
  cursor: pointer;
  overflow: hidden;
}
.vthumb img { width:100%; height:100%; object-fit:cover; opacity:.85; transition:opacity .2s; display:block; }
.vcard:hover .vthumb img { opacity:1; }
.play-overlay {
  position:absolute; inset:0;
  display:flex; align-items:center; justify-content:center;
  opacity:0; transition:opacity .2s;
  background:rgba(0,0,0,.25);
}
.vcard:hover .play-overlay { opacity:1; }
.play-circle {
  width:48px; height:48px; border-radius:50%;
  background:rgba(201,168,76,.9);
  display:flex; align-items:center; justify-content:center;
}
.vbadge {
  position:absolute; top:7px; right:7px;
  background:rgba(15,14,12,.82);
  border:1px solid var(--border);
  color:var(--text-muted);
  font-size:0.62rem; font-weight:700;
  padding:2px 6px; border-radius:2px;
  letter-spacing:0.07em; text-transform:uppercase;
}
.vbadge.pub { color:var(--gold); border-color:rgba(201,168,76,.3); }

.vinfo { padding:0.8rem 0.9rem; }
.vtitle { font-size:0.88rem; font-weight:600; color:var(--text); margin-bottom:0.25rem; line-height:1.3; }
.vdesc {
  font-size:0.76rem; color:var(--text-muted);
  margin-bottom:0.6rem; line-height:1.5;
  display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden;
}
.vmeta { display:flex; justify-content:space-between; align-items:center; }
.vauthor { display:flex; align-items:center; gap:0.4rem; }
.vauthor .av { width:20px; height:20px; font-size:0.58rem; }
.vauthor .name { font-size:0.72rem; color:var(--text-muted); font-weight:500; }
.viewers { display:flex; gap:2px; }
.viewers .av { width:18px; height:18px; font-size:0.55rem; opacity:.75; }
.vactions { display:flex; flex-wrap:wrap; align-items:center; justify-content:center; gap:0.4rem; margin-top:0.6rem; border-top:1px solid var(--border); padding-top:0.6rem; }
.vactions .btn-gold,
.vactions .btn-outline,
.vactions .btn-danger-sm { font-size:0.7rem !important; padding:0.22rem 0.5rem !important; }

/* Метаданные на карточках */
.vchips { display:flex; flex-wrap:wrap; gap:0.3rem; margin:0.3rem 0; }
.vchip { font-size:0.68rem; color:var(--text-muted); background:var(--surface2); border:1px solid var(--border); border-radius:2px; padding:1px 5px; white-space:nowrap; }
.vtags { display:flex; flex-wrap:wrap; gap:0.3rem; margin:0.3rem 0; }
.vtag { font-size:0.65rem; color:var(--gold); background:rgba(201,168,76,.08); border:1px solid rgba(201,168,76,.2); border-radius:2px; padding:1px 5px; }

/* Участники на карточках */
.vparts { display:flex; flex-wrap:wrap; gap:0.35rem; margin:0.3rem 0; align-items:center; }
.vpart-link {
  display:inline-flex; align-items:center; gap:0.25rem;
  font-size:0.68rem; color:var(--gold); text-decoration:none;
  background:rgba(201,168,76,.08); border:1px solid rgba(201,168,76,.2);
  border-radius:20px; padding:1px 6px;
  transition: background .15s;
}
.vpart-link:hover { background:rgba(201,168,76,.18); color:var(--gold-light); }
.vpart-ext {
  font-size:0.68rem; color:var(--text-muted);
  border:1px solid var(--border); border-radius:20px; padding:1px 6px;
}

/* FORM */
.fcard {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 4px;
  padding: 1.5rem;
  max-width: 560px;
}
.form-label { font-size:0.73rem; text-transform:uppercase; letter-spacing:0.1em; color:var(--text-muted); font-weight:600; margin-bottom:0.35rem; }
.form-control, .form-select {
  background: var(--bg); border:1px solid var(--border);
  color:var(--text); border-radius:2px;
  font-size:0.87rem; padding:0.5rem 0.75rem;
}
.form-control:focus, .form-select:focus {
  background:var(--bg); border-color:var(--gold); color:var(--text);
  box-shadow:0 0 0 2px rgba(201,168,76,.12);
}
.form-control::placeholder { color:var(--text-muted); opacity:.5; }

/* ACCESS SELECTOR */
.access-box { background:var(--surface2); border:1px solid var(--border); border-radius:4px; padding:1rem; }
.access-btns { display:flex; gap:0.4rem; margin-bottom:0.9rem; }
.access-btn {
  flex:1; padding:0.4rem 0.5rem;
  font-size:0.72rem; font-weight:700; letter-spacing:0.06em; text-transform:uppercase;
  border:1px solid var(--border); background:transparent; color:var(--text-muted);
  border-radius:2px; cursor:pointer; transition:all .15s;
}
.access-btn.active { background:var(--gold); border-color:var(--gold); color:var(--bg); }
.member-list { display:flex; flex-direction:column; gap:0.4rem; }
.member-row {
  display:flex; align-items:center; gap:0.65rem;
  padding:0.45rem 0.55rem; border-radius:3px;
  cursor:pointer; border:1px solid transparent;
  transition:background .12s;
  user-select: none;
}
.member-row:hover { background:var(--surface); }
.member-row.on { background:rgba(201,168,76,.07); border-color:rgba(201,168,76,.2); }
.member-row .av { width:26px; height:26px; font-size:0.65rem; flex-shrink:0; }
.member-row .mname { font-size:0.84rem; color:var(--text); font-weight:500; flex:1; }
.chk {
  width:17px; height:17px; border:1.5px solid var(--border);
  border-radius:2px; display:flex; align-items:center; justify-content:center;
  transition:all .12s;
}
.member-row.on .chk { background:var(--gold); border-color:var(--gold); }

/* MODAL */
.modal-content { background:var(--surface); border:1px solid var(--border); border-radius:4px; }
.modal-header { border-bottom:1px solid var(--border); padding:1rem 1.3rem; }
.modal-title { color:var(--gold); font-size:1.05rem; font-weight:600; }
.btn-close { filter:invert(.6); }
.modal-body { padding:1.2rem 1.3rem; }
.embed-wrap { position:relative; aspect-ratio:16/9; background:#000; border-radius:3px; overflow:hidden; }
.embed-wrap iframe { width:100%; height:100%; border:none; }
.embed-meta { padding:0.8rem 0 0; }
.embed-meta .etitle { font-size:1.05rem; font-weight:600; color:var(--text); margin-bottom:0.2rem; }
.embed-meta .emeta { font-size:0.75rem; color:var(--text-muted); }

/* EMPTY STATE */
.empty { text-align:center; padding:4rem 1rem; color:var(--text-muted); }
.empty .icon { font-size:2.5rem; margin-bottom:1rem; }
.empty p { font-size:0.9rem; margin-bottom:1.2rem; }

/* URL PREVIEW */
.url-preview {
  display:flex; gap:0.7rem; align-items:center;
  background:var(--surface2); border:1px solid var(--border);
  border-radius:3px; padding:0.6rem 0.8rem; margin-top:0.4rem;
}
.url-preview img { width:72px; height:41px; object-fit:cover; border-radius:2px; }
.url-preview .pt { font-size:0.82rem; color:var(--text); font-weight:500; }
.url-preview .pc { font-size:0.7rem; color:var(--text-muted); margin-top:2px; }

/* ALERTS */
.alert-err { background:rgba(192,57,43,.15); border:1px solid rgba(192,57,43,.3); color:#e07060; border-radius:3px; padding:0.7rem 1rem; font-size:0.85rem; margin-bottom:1rem; }
.alert-ok  { background:rgba(74,165,90,.15); border:1px solid rgba(74,165,90,.3); color:#80d490; border-radius:3px; padding:0.7rem 1rem; font-size:0.85rem; margin-bottom:1rem; }

/* ADMIN TABLE */
.admin-table { width:100%; border-collapse:collapse; font-size:0.85rem; }
.admin-table th { font-size:0.7rem; text-transform:uppercase; letter-spacing:0.1em; color:var(--text-muted); font-weight:600; padding:0.5rem 0.75rem; border-bottom:1px solid var(--border); text-align:left; }
.admin-table td { padding:0.65rem 0.75rem; border-bottom:1px solid rgba(46,42,34,.5); vertical-align:middle; }
.admin-table tr:last-child td { border-bottom:none; }
.admin-table tr:hover td { background:var(--surface2); }

/* Responsive */
@media(max-width:600px) {
  .app-nav { padding:0.65rem 1rem; }
  .main { padding:1rem; }
  .video-grid { grid-template-columns: 1fr; }
  .search-wrap { max-width:none; }
  .nav-user .name { display:none; }
}
</style>
<?php } // end layout_head


function layout_nav(?array $user, string $active_tab = 'all'): void {
    if (!$user) return;
    $search = h($_GET['q'] ?? '');
    ?>
<nav class="app-nav">
  <a href="index.php" class="brand"><?= h(SITE_NAME) ?></a>
  <div class="search-wrap">
    <form method="GET" action="index.php">
      <?php if ($active_tab === 'mine'): ?><input type="hidden" name="tab" value="mine"><?php endif; ?>
      <?php if (!empty($_GET['sort'])): ?><input type="hidden" name="sort" value="<?= h($_GET['sort']) ?>"><?php endif; ?>
      <?php if (!empty($_GET['order'])): ?><input type="hidden" name="order" value="<?= h($_GET['order']) ?>"><?php endif; ?>
      <input type="text" name="q" placeholder="<?= h(t('nav_search_ph')) ?>" value="<?= $search ?>" autocomplete="off">
    </form>
  </div>
  <div class="nav-user">
    <div class="avatar" style="background:<?= h($user['color']) ?>"><?= h(initials($user['display_name'])) ?></div>
    <span class="name"><?= h($user['display_name']) ?></span>
    <?php
      $new = get_new_since_prev($user['id']);
      $new_count = count($new['videos']) + count($new['comments']) + count($new['events']) + count($new['media']) + count($new['event_comments']);
    ?>
    <?php if ($new_count > 0): ?>
    <a href="whats_new.php" title="<?= h(t('nav_new_bell')) ?>" style="position:relative;text-decoration:none;font-size:1.1rem;line-height:1">
      🔔<span style="position:absolute;top:-4px;right:-6px;background:var(--accent);color:#fff;font-size:0.55rem;font-weight:700;border-radius:10px;padding:1px 4px;line-height:1.4"><?= $new_count ?></span>
    </a>
    <?php else: ?>
    <a href="whats_new.php" title="<?= h(t('nav_no_new')) ?>" style="font-size:1.1rem;line-height:1;opacity:0.4">🔔</a>
    <?php endif; ?>
    <a href="profile.php" title="<?= h(t('nav_profile')) ?>">⚙</a>
    <a href="help.php" title="<?= h(t('nav_help')) ?>">?</a>
    <?php if ($user['is_admin']): ?>
      <a href="admin.php" title="<?= h(t('nav_admin')) ?>">👥</a>
    <?php endif; ?>
    <a href="logout.php"><?= h(t('nav_logout')) ?></a>
  </div>
</nav>
<div class="app-tabs">
  <a href="index.php" class="<?= $active_tab === 'all' ? 'active' : '' ?>"><?= h(t('nav_all')) ?></a>
  <a href="index.php?tab=mine" class="<?= $active_tab === 'mine' ? 'active' : '' ?>"><?= h(t('nav_mine')) ?></a>
  <a href="events.php" class="<?= $active_tab === 'events' ? 'active' : '' ?>"><?= h(t('nav_events')) ?></a>
  <a href="add.php" style="margin-left:auto; color:var(--gold)"><?= h(t('nav_add')) ?></a>
</div>
    <?php
}

function layout_foot(): void {
    ?>
<!-- EMBED MODAL -->
<div class="modal fade" id="watchModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered" style="max-width:680px">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="watchTitle"><?= h(t('modal_video')) ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="embed-wrap">
          <iframe id="watchFrame" src="" allowfullscreen allow="autoplay"></iframe>
        </div>
        <div class="embed-meta">
          <div class="etitle" id="watchName"></div>
          <div class="emeta" id="watchMeta"></div>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
<script>
// Watch modal
function watchVideo(ytId, title, meta) {
  document.getElementById('watchTitle').textContent = title;
  document.getElementById('watchName').textContent = title;
  document.getElementById('watchMeta').textContent = meta;
  document.getElementById('watchFrame').src = 'https://www.youtube.com/embed/' + ytId + '?autoplay=1';
  new bootstrap.Modal(document.getElementById('watchModal')).show();
}
document.getElementById('watchModal').addEventListener('hidden.bs.modal', function() {
  document.getElementById('watchFrame').src = '';
});

// Access type toggle
function setAccess(type) {
  document.querySelectorAll('.access-btn').forEach(b => b.classList.remove('active'));
  document.querySelector('.access-btn[data-type="'+type+'"]').classList.add('active');
  document.getElementById('access_type').value = type;
  const list = document.getElementById('memberList');
  if (list) list.style.opacity = (type === 'selected') ? '1' : '0.35';
  if (list) list.style.pointerEvents = (type === 'selected') ? 'auto' : 'none';
}

// Member row toggle
document.querySelectorAll('.member-row').forEach(row => {
  row.addEventListener('click', function() {
    this.classList.toggle('on');
    const cb = this.querySelector('input[type=checkbox]');
    if (cb) cb.checked = !cb.checked;
  });
});

</script>
</body>
</html>
<?php } // end layout_foot
?>
