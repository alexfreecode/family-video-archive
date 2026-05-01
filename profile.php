<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
session_start();
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }

$user    = current_user();
if (!$user) { session_destroy(); header('Location: login.php'); exit; }

// Загружаем язык ДО обработки POST — чтобы t() работало в сообщениях
load_language();

$success = '';
$error   = '';

// Генерация токена и редирект в Telegram (GET, защищён проверкой сессии)
if (isset($_GET['action']) && $_GET['action'] === 'telegram_link' && telegram_enabled()) {
    $token = telegram_generate_link_token($user['id']);
    $bot   = defined('TELEGRAM_BOT_USERNAME') ? TELEGRAM_BOT_USERNAME : '';
    header('Location: https://t.me/' . $bot . '?start=' . $token);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    if (isset($_POST['action']) && $_POST['action'] === 'telegram_disconnect') {
        telegram_disconnect($user['id']);
        $success = t('tg_disconnected');
        $user = current_user();
    } elseif (isset($_POST['action']) && $_POST['action'] === 'change_pass') {
        $old  = $_POST['old_pass'] ?? '';
        $new  = $_POST['new_pass'] ?? '';
        $new2 = $_POST['new_pass2'] ?? '';
        if (!password_verify($old, $user['password'])) {
            $error = t('profile_err_oldpass');
        } elseif (strlen($new) < 8) {
            $error = t('profile_err_passmin');
        } elseif ($new !== $new2) {
            $error = t('profile_err_passmatch');
        } else {
            db()->prepare("UPDATE users SET password=? WHERE id=?")->execute([password_hash($new, PASSWORD_DEFAULT), $user['id']]);
            $success = t('profile_ok_pass');
        }
    } elseif (!isset($_POST['action'])) {
        $theme = $_POST['theme'] ?? 'dark';
        save_user_theme($user['id'], $theme);
        $lang  = $_POST['language'] ?? 'ru';
        save_user_language($user['id'], $lang); // сохраняет и перезагружает $_lang
        // Обновляем куки — страница входа тоже получит актуальные настройки
        $expire = time() + 60 * 60 * 24 * 365;
        setcookie('pref_lang',  $lang,  $expire, '/', '', false, true);
        setcookie('pref_theme', $theme, $expire, '/', '', false, true);
        $success = t('profile_ok_saved');
    }
    $user = current_user(); // обновляем данные пользователя (включая language)
}

require_once __DIR__ . '/layout.php';
layout_head(t('profile_title'), false);
?>

<?php layout_nav($user, ''); ?>

<div class="main">
  <div class="sec-header">
    <div class="sec-title"><?= h(t('profile_title')) ?></div>
    <a href="index.php" class="btn-outline"><?= h(t('back')) ?></a>
  </div>

  <?php if ($success): ?><div class="alert-ok"><?= h($success) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="alert-err"><?= h($error) ?></div><?php endif; ?>

  <div class="fcard">

    <!-- Аватар и имя -->
    <div style="display:flex;align-items:center;gap:1rem;margin-bottom:1.8rem;padding-bottom:1.5rem;border-bottom:1px solid var(--border)">
      <div class="avatar" style="background:<?= h($user['color']) ?>;width:52px;height:52px;font-size:1.1rem">
        <?= h(initials($user['display_name'])) ?>
      </div>
      <div>
        <div style="font-size:1.05rem;font-weight:600;color:var(--text)"><?= h($user['display_name']) ?></div>
        <div style="font-size:0.78rem;color:var(--text-muted);margin-top:2px"><?= h($user['username']) ?></div>
      </div>
    </div>

    <!-- Настройки -->
    <form method="POST">
      <?= csrf_field() ?>
      <div style="font-size:0.75rem;text-transform:uppercase;letter-spacing:0.1em;color:var(--text-muted);font-weight:600;margin-bottom:1rem">
        <?= h(t('profile_settings')) ?>
      </div>

      <!-- Тема -->
      <div class="mb-4">
        <label class="form-label"><?= h(t('profile_theme')) ?></label>
        <div style="display:flex;flex-direction:column;gap:0.5rem;margin-top:0.3rem">
          <label class="theme-opt <?= ($user['theme'] ?? 'dark') === 'dark' ? 'active' : '' ?>">
            <input type="radio" name="theme" value="dark" <?= ($user['theme'] ?? 'dark') === 'dark' ? 'checked' : '' ?>>
            <span class="theme-preview dark-prev"></span>
            <span><?= h(t('profile_theme_dark')) ?></span>
          </label>
          <label class="theme-opt <?= ($user['theme'] ?? 'dark') === 'light' ? 'active' : '' ?>">
            <input type="radio" name="theme" value="light" <?= ($user['theme'] ?? 'dark') === 'light' ? 'checked' : '' ?>>
            <span class="theme-preview light-prev"></span>
            <span><?= h(t('profile_theme_light')) ?></span>
          </label>
        </div>
      </div>

      <!-- Язык -->
      <div class="mb-4">
        <label class="form-label"><?= h(t('profile_language')) ?></label>
        <?php
          $cur_lang = $user['language'] ?? current_lang();
          $langs = [
            'ru' => 'Русский',
            'en' => 'English',
            'uk' => 'Українська',
            'pl' => 'Polski',
            'es' => 'Español',
            'pt' => 'Português',
            'de' => 'Deutsch',
          ];
        ?>
        <div style="display:flex;flex-direction:column;gap:0.5rem;margin-top:0.3rem">
          <?php foreach ($langs as $code => $label): ?>
          <label class="theme-opt <?= $cur_lang === $code ? 'active' : '' ?>">
            <input type="radio" name="language" value="<?= h($code) ?>" <?= $cur_lang === $code ? 'checked' : '' ?>>
            <span><?= $label ?></span>
          </label>
          <?php endforeach; ?>
        </div>
      </div>

      <button type="submit" class="btn-gold"><?= h(t('profile_btn_save')) ?></button>
    </form>

    <!-- Telegram -->
    <?php if (telegram_enabled()): ?>
    <div style="margin-top:1.8rem;padding-top:1.5rem;border-top:1px solid var(--border)">
      <div style="font-size:0.75rem;text-transform:uppercase;letter-spacing:0.1em;color:var(--text-muted);font-weight:600;margin-bottom:1rem">
        <?= h(t('tg_section')) ?>
      </div>
      <?php if ($user['telegram_chat_id']): ?>
        <div style="display:flex;align-items:center;gap:0.8rem;margin-bottom:1rem">
          <span style="font-size:1.3rem">✅</span>
          <div>
            <div style="font-size:0.9rem;color:var(--text)"><?= h(t('tg_connected')) ?></div>
            <?php if ($user['telegram_connected_at']): ?>
            <div style="font-size:0.75rem;color:var(--text-muted)"><?= date('d.m.Y H:i', strtotime($user['telegram_connected_at'])) ?></div>
            <?php endif; ?>
          </div>
        </div>
        <form method="POST">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="telegram_disconnect">
          <button type="submit" class="btn-outline" style="font-size:0.85rem">
            <?= h(t('tg_disconnect_btn')) ?>
          </button>
        </form>
      <?php else: ?>
        <div style="font-size:0.85rem;color:var(--text-muted);margin-bottom:1rem">
          <?= h(t('tg_not_connected')) ?>
        </div>
        <a href="profile.php?action=telegram_link" class="btn-gold" style="display:inline-flex;align-items:center;gap:0.5rem;text-decoration:none">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12 12-5.373 12-12S18.627 0 12 0zm5.562 8.248l-2.007 9.456c-.148.658-.537.818-1.084.508l-3-2.21-1.447 1.394c-.16.16-.295.295-.605.295l.213-3.053 5.56-5.023c.242-.213-.054-.333-.373-.12L7.26 14.4l-2.95-.924c-.641-.2-.654-.641.136-.953l11.527-4.444c.533-.194 1.002.13.59.17z"/></svg>
          <?= h(t('tg_connect_btn')) ?>
        </a>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Смена пароля -->
    <div style="margin-top:1.8rem;padding-top:1.5rem;border-top:1px solid var(--border)">
      <div style="font-size:0.75rem;text-transform:uppercase;letter-spacing:0.1em;color:var(--text-muted);font-weight:600;margin-bottom:1rem"><?= h(t('profile_change_pass')) ?></div>
      <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="change_pass">
        <div class="mb-3">
          <label class="form-label"><?= h(t('profile_curr_pass')) ?></label>
          <input type="password" name="old_pass" class="form-control" autocomplete="current-password">
        </div>
        <div class="mb-3">
          <label class="form-label"><?= h(t('profile_new_pass')) ?></label>
          <input type="password" name="new_pass" class="form-control" placeholder="<?= h(t('profile_pass_ph')) ?>" autocomplete="new-password">
        </div>
        <div class="mb-3">
          <label class="form-label"><?= h(t('profile_new_pass2')) ?></label>
          <input type="password" name="new_pass2" class="form-control" autocomplete="new-password">
        </div>
        <button type="submit" class="btn-outline"><?= h(t('profile_btn_chpass')) ?></button>
      </form>
    </div>

  </div>
</div>

<style>
.theme-opt {
  display: flex;
  align-items: center;
  gap: 0.65rem;
  padding: 0.55rem 0.9rem;
  border: 1px solid var(--border);
  border-radius: 3px;
  cursor: pointer;
  font-size: 0.88rem;
  color: var(--text-muted);
  transition: all .15s;
  user-select: none;
  max-width: 260px;
}
.theme-opt:hover { border-color: var(--text-muted); color: var(--text); }
.theme-opt.active { border-color: var(--gold); color: var(--text); }
.theme-opt input[type=radio] {
  width: 15px; height: 15px;
  accent-color: var(--gold);
  flex-shrink: 0;
  cursor: pointer;
}
.theme-preview {
  width: 22px; height: 22px;
  border-radius: 3px;
  border: 1px solid var(--border);
  flex-shrink: 0;
}
.dark-prev  { background: #0f0e0c; }
.light-prev { background: #f5f2ec; border-color: #d8d0c0; }
</style>

<script>
document.querySelectorAll('.theme-opt').forEach(opt => {
  opt.addEventListener('click', function() {
    // Обновляем активный стиль только внутри своей группы (theme vs language)
    const name = this.querySelector('input[type=radio]').name;
    document.querySelectorAll(`.theme-opt input[name="${name}"]`).forEach(r => {
      r.closest('.theme-opt').classList.remove('active');
    });
    this.classList.add('active');
  });
});
</script>

<?php layout_foot(); ?>
