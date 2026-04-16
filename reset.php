<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
session_start();

if (isset($_SESSION['user_id'])) { header('Location: index.php'); exit; }

// Определяем язык до любого вывода
load_language();

$error  = '';
$step   = 'code';
$user_r = null;

$code = trim($_GET['code'] ?? $_POST['code'] ?? '');
if ($code) {
    $stmt = db()->prepare("SELECT * FROM users WHERE reset_code=? AND reset_expires > NOW()");
    $stmt->execute([$code]);
    $user_r = $stmt->fetch();
    if ($user_r) {
        $step = 'form';
    } else {
        $error = t('reset_err_invalid');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset'])) {
    $code  = trim($_POST['code'] ?? '');
    $pass  = $_POST['password'] ?? '';
    $pass2 = $_POST['password2'] ?? '';

    $stmt = db()->prepare("SELECT * FROM users WHERE reset_code=? AND reset_expires > NOW()");
    $stmt->execute([$code]);
    $user_r = $stmt->fetch();

    if (!$user_r) {
        $error = t('reset_err_invalid_s');
        $step  = 'code';
    } elseif (strlen($pass) < 8) {
        $error = t('reset_err_pass_min');
        $step  = 'form';
    } elseif ($pass !== $pass2) {
        $error = t('reset_err_pass_match');
        $step  = 'form';
    } else {
        $hash = password_hash($pass, PASSWORD_DEFAULT);
        db()->prepare("UPDATE users SET password=?, reset_code=NULL, reset_expires=NULL WHERE id=?")
           ->execute([$hash, $user_r['id']]);
        $_SESSION['user_id'] = $user_r['id'];
        header('Location: index.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="<?= h(current_lang()) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= h(t('reset_title')) ?> — <?= h(SITE_NAME) ?></title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
<style>
:root { --bg:#0f0e0c; --surface:#1a1814; --gold:#c9a84c; --gold-light:#e8c97a; --text:#e8e2d4; --text-muted:#8a8070; --border:#2e2a22; }
* { box-sizing:border-box; }
body { background:var(--bg); color:var(--text); font-family:'Segoe UI',system-ui,sans-serif; min-height:100vh; display:flex; align-items:center; justify-content:center; padding:1rem; }
.box { background:var(--surface); border:1px solid var(--border); border-radius:4px; padding:2rem; width:100%; max-width:380px; }
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
  <div class="site-sub"><?= h(t('reset_subtitle')) ?></div>

  <?php if ($error): ?><div class="err"><?= h($error) ?></div><?php endif; ?>

  <?php if ($step === 'code'): ?>
  <form method="POST">
    <div class="mb-3">
      <label class="form-label"><?= h(t('reset_label_code')) ?></label>
      <input type="text" name="code" class="form-control code-input"
             placeholder="000000" maxlength="6" autofocus value="<?= h($code) ?>">
      <div style="font-size:0.72rem;color:var(--text-muted);margin-top:0.4rem"><?= h(t('reset_code_hint')) ?></div>
    </div>
    <button type="submit" class="btn-gold"><?= h(t('reset_btn_continue')) ?></button>
  </form>

  <?php elseif ($step === 'form'): ?>
  <div style="font-size:0.85rem;color:var(--text-muted);margin-bottom:1.2rem">
    <?= h(t('reset_set_for')) ?> <strong style="color:var(--text)"><?= h($user_r['display_name']) ?></strong>
  </div>
  <form method="POST">
    <input type="hidden" name="code" value="<?= h($code) ?>">
    <input type="hidden" name="reset" value="1">
    <div class="mb-3">
      <label class="form-label"><?= h(t('reset_label_pass')) ?></label>
      <input type="password" name="password" class="form-control"
             placeholder="<?= h(t('reg_pass_min8')) ?>" autofocus autocomplete="new-password">
    </div>
    <div class="mb-1">
      <label class="form-label"><?= h(t('reset_label_pass2')) ?></label>
      <input type="password" name="password2" class="form-control" autocomplete="new-password">
    </div>
    <button type="submit" class="btn-gold"><?= h(t('reset_btn_save')) ?></button>
  </form>
  <?php endif; ?>

  <div style="margin-top:1.2rem;text-align:center;font-size:0.78rem;color:var(--text-muted)">
    <a href="login.php"><?= h(t('reset_back')) ?></a>
  </div>
</div>
</body>
</html>
