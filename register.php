<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
session_start();

if (isset($_SESSION['user_id'])) { header('Location: index.php'); exit; }

// Определяем язык до любого вывода
load_language();

$error   = '';
$success = '';
$step    = 'code'; // code → form → done
$invite  = null;

// Шаг 1 — проверка кода
$code = trim($_GET['code'] ?? $_POST['code'] ?? '');
if ($code) {
    $stmt = db()->prepare("SELECT * FROM invite_codes WHERE code=? AND used=0 AND expires_at > NOW()");
    $stmt->execute([$code]);
    $invite = $stmt->fetch();
    if ($invite) {
        $step = 'form';
    } else {
        $error = t('reg_err_invalid');
    }
}

// Шаг 2 — регистрация
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    $code    = trim($_POST['code'] ?? '');
    $name    = trim($_POST['display_name'] ?? '');
    $login   = trim($_POST['username'] ?? '');
    $pass    = $_POST['password'] ?? '';
    $pass2   = $_POST['password2'] ?? '';

    // Перепроверяем код
    $stmt = db()->prepare("SELECT * FROM invite_codes WHERE code=? AND used=0 AND expires_at > NOW()");
    $stmt->execute([$code]);
    $invite = $stmt->fetch();

    if (!$invite) {
        $error = t('reg_err_invalid_s');
        $step  = 'code';
    } elseif (!$name || !$login || !$pass) {
        $error = t('reg_err_fill_all');
        $step  = 'form';
    } elseif (strlen($pass) < 4) {
        $error = t('reg_err_pass_min');
        $step  = 'form';
    } elseif ($pass !== $pass2) {
        $error = t('reg_err_pass_match');
        $step  = 'form';
    } else {
        try {
            $hash = password_hash($pass, PASSWORD_DEFAULT);
            db()->prepare("INSERT INTO users (username, display_name, password, color) VALUES (?,?,?,?)")
               ->execute([$login, $name, $hash, $invite['color']]);
            $new_user_id = (int)db()->lastInsertId();

            // Помечаем код использованным
            db()->prepare("UPDATE invite_codes SET used=1 WHERE id=?")->execute([$invite['id']]);

            // Уведомления для остальных
            create_access_notifications($new_user_id);

            // Авторизуем
            $_SESSION['user_id'] = $new_user_id;
            header('Location: index.php');
            exit;
        } catch (Exception $e) {
            $error = t('reg_err_taken');
            $step  = 'form';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="<?= h(current_lang()) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= h(t('reg_title')) ?> — <?= h(SITE_NAME) ?></title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
<style>
:root { --bg:#0f0e0c; --surface:#1a1814; --gold:#c9a84c; --gold-light:#e8c97a; --text:#e8e2d4; --text-muted:#8a8070; --border:#2e2a22; --danger:#c0392b; }
* { box-sizing:border-box; }
body { background:var(--bg); color:var(--text); font-family:'Segoe UI',system-ui,sans-serif; min-height:100vh; display:flex; align-items:center; justify-content:center; padding:1rem; }
.box { background:var(--surface); border:1px solid var(--border); border-radius:4px; padding:2rem; width:100%; max-width:400px; }
.site-name { font-size:1.3rem; font-weight:700; color:var(--gold); margin-bottom:0.2rem; }
.site-sub { font-size:0.75rem; color:var(--text-muted); letter-spacing:0.12em; text-transform:uppercase; margin-bottom:1.8rem; }
.form-label { font-size:0.72rem; text-transform:uppercase; letter-spacing:0.1em; color:var(--text-muted); font-weight:600; margin-bottom:0.3rem; }
.form-control { background:var(--bg); border:1px solid var(--border); color:var(--text); border-radius:2px; font-size:0.9rem; padding:0.55rem 0.8rem; width:100%; }
.form-control:focus { outline:none; border-color:var(--gold); box-shadow:0 0 0 2px rgba(201,168,76,.12); }
.form-control::placeholder { color:var(--text-muted); opacity:.5; }
.btn-gold { background:var(--gold); color:#0f0e0c; border:none; font-weight:700; font-size:0.82rem; letter-spacing:0.1em; text-transform:uppercase; padding:0.65rem; width:100%; border-radius:2px; cursor:pointer; margin-top:1.2rem; transition:background .15s; }
.btn-gold:hover { background:var(--gold-light); }
.err { background:rgba(192,57,43,.15); border:1px solid rgba(192,57,43,.3); color:#e07060; border-radius:3px; padding:0.6rem 0.9rem; font-size:0.83rem; margin-bottom:1rem; }
.code-input { font-size:1.8rem; font-weight:700; letter-spacing:0.3em; text-align:center; }
a { color:var(--gold); }
</style>
</head>
<body>
<div class="box">
  <div class="site-name"><?= h(SITE_NAME) ?></div>
  <div class="site-sub"><?= h(t('reg_subtitle')) ?></div>

  <?php if ($error): ?><div class="err"><?= h($error) ?></div><?php endif; ?>

  <?php if ($step === 'code'): ?>
  <!-- Шаг 1: ввод кода -->
  <form method="POST">
    <div class="mb-3">
      <label class="form-label"><?= h(t('reg_label_code')) ?></label>
      <input type="text" name="code" class="form-control code-input"
             placeholder="000000" maxlength="6" autofocus
             value="<?= h($code) ?>">
      <div style="font-size:0.72rem;color:var(--text-muted);margin-top:0.4rem"><?= h(t('reg_code_hint')) ?></div>
    </div>
    <button type="submit" class="btn-gold"><?= h(t('reg_btn_continue')) ?></button>
  </form>

  <?php elseif ($step === 'form'): ?>
  <!-- Шаг 2: заполнение данных -->
  <div style="display:flex;align-items:center;gap:0.6rem;margin-bottom:1.5rem;padding:0.7rem 0.9rem;background:rgba(201,168,76,.08);border:1px solid rgba(201,168,76,.2);border-radius:3px">
    <div style="width:28px;height:28px;border-radius:50%;background:<?= h($invite['color']) ?>;flex-shrink:0"></div>
    <div style="font-size:0.82rem;color:var(--text)"><?= h(t('reg_confirmed')) ?></div>
  </div>

  <form method="POST">
    <input type="hidden" name="code" value="<?= h($code) ?>">
    <input type="hidden" name="register" value="1">

    <div class="mb-3">
      <label class="form-label"><?= h(t('reg_label_name')) ?></label>
      <input type="text" name="display_name" class="form-control"
             placeholder="Маша" autofocus value="<?= h($_POST['display_name'] ?? '') ?>">
    </div>
    <div class="mb-3">
      <label class="form-label"><?= h(t('reg_label_login')) ?></label>
      <input type="text" name="username" class="form-control"
             placeholder="masha" value="<?= h($_POST['username'] ?? '') ?>"
             autocomplete="username">
    </div>
    <div class="mb-3">
      <label class="form-label"><?= h(t('reg_label_pass')) ?></label>
      <input type="password" name="password" class="form-control"
             placeholder="<?= h(t('reg_pass_min')) ?>" autocomplete="new-password">
    </div>
    <div class="mb-1">
      <label class="form-label"><?= h(t('reg_label_pass2')) ?></label>
      <input type="password" name="password2" class="form-control"
             placeholder="" autocomplete="new-password">
    </div>
    <div style="margin-top:0.9rem;padding:0.75rem;background:rgba(201,168,76,.07);border:1px solid rgba(201,168,76,.2);border-radius:3px;font-size:0.78rem;color:var(--text-muted);line-height:1.5">
      <?= t('reg_security_tip') ?>
    </div>
    <button type="submit" class="btn-gold"><?= h(t('reg_btn_create')) ?></button>
  </form>
  <?php endif; ?>

  <div style="margin-top:1.2rem;text-align:center;font-size:0.78rem;color:var(--text-muted)">
    <?= h(t('reg_has_account')) ?> <a href="login.php"><?= h(t('reg_login_link')) ?></a>
  </div>
</div>
</body>
</html>
