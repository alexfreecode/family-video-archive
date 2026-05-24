<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
session_start();

if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

// Определяем язык до любого вывода
load_language();

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = trim($_POST['login'] ?? '');
    $pass  = $_POST['pass'] ?? '';
    if ($login && $pass) {
        $stmt = db()->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$login]);
        $user = $stmt->fetch();
        if ($user && password_verify($pass, $user['password'])) {
            if (!$user['is_active']) {
                $error = t('login_err_inactive');
            } else {
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['id'];
                if (!empty($_POST['remember'])) {
                    create_remember_token($user['id']);
                }
                // Сохраняем тему и язык в куки — чтобы страница входа выглядела правильно при следующем визите
                $expire = time() + 60 * 60 * 24 * 365;
                setcookie('pref_lang',  $user['language'] ?? 'ru',  $expire, '/', '', false, true);
                setcookie('pref_theme', $user['theme']    ?? 'dark', $expire, '/', '', false, true);
                header('Location: index.php');
                exit;
            }
        } else {
            $error = t('login_err_wrong');
        }
    }
}
?>
<?php
$login_theme = $_COOKIE['pref_theme'] ?? 'dark';
?>
<!DOCTYPE html>
<html lang="<?= h(current_lang()) ?>" data-theme="<?= h($login_theme) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= h(t('login_title')) ?> — <?= h(SITE_NAME) ?></title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
<style>
:root { --bg:#0f0e0c; --surface:#1a1814; --gold:#c9a84c; --gold-light:#e8c97a; --text:#e8e2d4; --text-muted:#8a8070; --border:#2e2a22; }
[data-theme="light"] { --bg:#f5f2ec; --surface:#ffffff; --gold:#a07828; --gold-light:#c9a84c; --text:#1a1610; --text-muted:#7a7060; --border:#d8d0c0; }
* { box-sizing:border-box; }
body { background:var(--bg); color:var(--text); font-family:'Segoe UI',system-ui,sans-serif; min-height:100vh; display:flex; align-items:center; justify-content:center; }
.login-box { background:var(--surface); border:1px solid var(--border); border-radius:4px; padding:2rem; width:100%; max-width:380px; }
.site-name { font-size:1.4rem; font-weight:700; color:var(--gold); margin-bottom:0.25rem; }
.site-sub { font-size:0.75rem; color:var(--text-muted); letter-spacing:0.12em; text-transform:uppercase; margin-bottom:1.8rem; }
.form-label { font-size:0.72rem; text-transform:uppercase; letter-spacing:0.1em; color:var(--text-muted); font-weight:600; margin-bottom:0.3rem; }
.form-control { background:var(--bg); border:1px solid var(--border); color:var(--text); border-radius:2px; font-size:0.9rem; padding:0.55rem 0.8rem; width:100%; }
.form-control:focus { outline:none; border-color:var(--gold); box-shadow:0 0 0 2px rgba(201,168,76,.12); }
.form-control::placeholder { color:var(--text-muted); opacity:.5; }
.btn-login { background:var(--gold); color:#0f0e0c; border:none; font-weight:700; font-size:0.82rem; letter-spacing:0.1em; text-transform:uppercase; padding:0.65rem; width:100%; border-radius:2px; cursor:pointer; margin-top:1.2rem; transition:background .15s; }
.btn-login:hover { background:var(--gold-light); }
.err { background:rgba(192,57,43,.15); border:1px solid rgba(192,57,43,.3); color:#e07060; border-radius:3px; padding:0.6rem 0.9rem; font-size:0.83rem; margin-bottom:1rem; }
</style>
</head>
<body>
<div class="login-box">
  <div class="site-name"><?= h(SITE_NAME) ?></div>
  <div class="site-sub"><?= h(t('login_subtitle')) ?></div>
  <?php if ($error): ?><div class="err"><?= h($error) ?></div><?php endif; ?>
  <form method="POST">
    <div class="mb-3">
      <label class="form-label"><?= h(t('login_label_user')) ?></label>
      <input type="text" name="login" class="form-control" autofocus autocomplete="username" value="<?= h($_POST['login'] ?? '') ?>">
    </div>
    <div class="mb-1">
      <label class="form-label"><?= h(t('login_label_pass')) ?></label>
      <input type="password" name="pass" class="form-control" autocomplete="current-password">
    </div>
    <label style="display:flex;align-items:center;gap:0.5rem;margin-top:0.9rem;cursor:pointer;font-size:0.82rem;color:var(--text-muted)">
      <input type="checkbox" name="remember" value="1" style="width:15px;height:15px;accent-color:var(--gold);cursor:pointer">
      <?= h(t('login_remember')) ?>
    </label>
    <button type="submit" class="btn-login"><?= h(t('login_btn')) ?></button>
  </form>
  <div style="margin-top:1rem;text-align:center;font-size:0.78rem;color:var(--text-muted)">
    <a href="reset.php" style="color:var(--gold)"><?= h(t('login_forgot')) ?></a>
    &nbsp;·&nbsp;
    <a href="register.php" style="color:var(--gold)"><?= h(t('login_register_link')) ?></a>
  </div>
</div>
</body>
</html>
