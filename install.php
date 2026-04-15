<?php
/**
 * install.php — первоначальная настройка архива
 * После успешной установки предложит удалить этот файл.
 * Для повторного тестирования просто загрузите файл снова.
 */

$step   = 'check'; // check → install → done
$errors = [];
$info   = [];

// ── Шаг: проверка окружения ───────────────────────────────────────────────
function check_env(): array {
    $errors = [];
    if (!file_exists(__DIR__ . '/config.php')) {
        $errors[] = 'Файл <strong>config.php</strong> не найден. Скопируйте <code>config.example.php</code> → <code>config.php</code> и заполните данные.';
    }
    if (!extension_loaded('pdo_mysql')) {
        $errors[] = 'Расширение PHP <strong>pdo_mysql</strong> не загружено.';
    }
    if (!extension_loaded('gd')) {
        $errors[] = 'Расширение PHP <strong>GD</strong> не загружено (нужно для превью).';
    }
    $uploads = __DIR__ . '/uploads/thumbnails';
    if (!is_dir($uploads)) {
        if (!mkdir($uploads, 0755, true)) {
            $errors[] = 'Не удалось создать папку <strong>uploads/thumbnails/</strong>. Создайте вручную и дайте права 755.';
        }
    } elseif (!is_writable($uploads)) {
        $errors[] = 'Папка <strong>uploads/thumbnails/</strong> не доступна для записи. Дайте права 755.';
    }
    return $errors;
}

// ── Шаг: подключение к БД ────────────────────────────────────────────────
function try_connect(): ?PDO {
    if (!file_exists(__DIR__ . '/config.php')) return null;
    require_once __DIR__ . '/config.php';
    try {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET,
            DB_USER, DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        return $pdo;
    } catch (Exception $e) {
        return null;
    }
}

// ── Шаг: проверка — уже установлено? ─────────────────────────────────────
function is_installed(PDO $pdo): bool {
    try {
        $pdo->query("SELECT 1 FROM users LIMIT 1");
        return true;
    } catch (Exception $e) {
        return false;
    }
}

// ── Обработка формы установки ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['install'])) {
    $step = 'install';

    $name   = trim($_POST['display_name'] ?? '');
    $login  = trim($_POST['username'] ?? '');
    $pass   = $_POST['password'] ?? '';
    $pass2  = $_POST['password2'] ?? '';

    if (!$name)              $errors[] = 'Введите имя администратора.';
    if (!$login)             $errors[] = 'Введите логин.';
    if (strlen($pass) < 4)  $errors[] = 'Пароль — минимум 4 символа.';
    if ($pass !== $pass2)   $errors[] = 'Пароли не совпадают.';

    $env_errors = check_env();
    $errors = array_merge($errors, $env_errors);

    if (empty($errors)) {
        $pdo = try_connect();
        if (!$pdo) {
            $errors[] = 'Не удалось подключиться к базе данных. Проверьте config.php.';
        } else {
            try {
                // Создаём таблицы из schema.sql
                $sql = file_get_contents(__DIR__ . '/schema.sql');
                // Убираем комментарии и миграции, оставляем только CREATE TABLE
                $statements = [];
                foreach (explode(';', $sql) as $stmt) {
                    $s = trim($stmt);
                    if (stripos($s, 'CREATE TABLE') === 0) {
                        $statements[] = $s;
                    }
                }
                foreach ($statements as $stmt) {
                    $pdo->exec($stmt . ';');
                }

                // Создаём администратора
                $hash = password_hash($pass, PASSWORD_DEFAULT);
                $pdo->prepare(
                    "INSERT INTO users (username, display_name, password, color, is_admin, is_active)
                     VALUES (?, ?, ?, '#c9a84c', 1, 1)"
                )->execute([$login, $name, $hash]);

                $step = 'done';
            } catch (Exception $e) {
                $errors[] = 'Ошибка при создании таблиц: ' . htmlspecialchars($e->getMessage());
            }
        }
    }

    if (!empty($errors)) $step = 'check';
}

// ── Предварительная проверка для отображения формы ───────────────────────
$pre_errors = [];
$db_ok      = false;
$installed  = false;

if ($step === 'check' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $pre_errors = check_env();
    $pdo = try_connect();
    if ($pdo) {
        $db_ok     = true;
        $installed = is_installed($pdo);
    } else {
        if (file_exists(__DIR__ . '/config.php')) {
            $pre_errors[] = 'Не удалось подключиться к базе данных. Проверьте данные в config.php.';
        }
    }
}

// ── Удаление файла ────────────────────────────────────────────────────────
if (isset($_GET['delete_installer']) && $_GET['delete_installer'] === '1') {
    if (@unlink(__FILE__)) {
        header('Location: index.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Установка архива</title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
<style>
:root { --bg:#0f0e0c; --surface:#1a1814; --gold:#c9a84c; --gold-light:#e8c97a; --text:#e8e2d4; --text-muted:#8a8070; --border:#2e2a22; }
* { box-sizing:border-box; }
body { background:var(--bg); color:var(--text); font-family:'Segoe UI',system-ui,sans-serif; min-height:100vh; display:flex; align-items:center; justify-content:center; padding:1.5rem; }
.box { background:var(--surface); border:1px solid var(--border); border-radius:6px; padding:2rem; width:100%; max-width:480px; }
h1 { font-size:1.3rem; font-weight:700; color:var(--gold); margin:0 0 0.25rem; }
.subtitle { font-size:0.75rem; color:var(--text-muted); letter-spacing:0.1em; text-transform:uppercase; margin-bottom:1.8rem; }
.form-label { font-size:0.72rem; text-transform:uppercase; letter-spacing:0.1em; color:var(--text-muted); font-weight:600; margin-bottom:0.3rem; }
.form-control { background:var(--bg); border:1px solid var(--border); color:var(--text); border-radius:3px; font-size:0.9rem; padding:0.55rem 0.8rem; width:100%; }
.form-control:focus { outline:none; border-color:var(--gold); box-shadow:0 0 0 2px rgba(201,168,76,.12); }
.btn-gold { background:var(--gold); color:#0f0e0c; border:none; font-weight:700; font-size:0.82rem; letter-spacing:0.08em; text-transform:uppercase; padding:0.65rem 1.5rem; border-radius:3px; cursor:pointer; transition:background .15s; }
.btn-gold:hover { background:var(--gold-light); }
.btn-danger-soft { background:rgba(192,57,43,.15); border:1px solid rgba(192,57,43,.4); color:#e07060; font-size:0.82rem; padding:0.5rem 1rem; border-radius:3px; cursor:pointer; text-decoration:none; transition:background .15s; }
.btn-danger-soft:hover { background:rgba(192,57,43,.25); color:#e07060; }
.err { background:rgba(192,57,43,.12); border:1px solid rgba(192,57,43,.3); color:#e07060; border-radius:3px; padding:0.6rem 0.9rem; font-size:0.83rem; margin-bottom:1rem; }
.ok  { background:rgba(39,174,96,.1); border:1px solid rgba(39,174,96,.3); color:#6fcf97; border-radius:3px; padding:0.6rem 0.9rem; font-size:0.83rem; margin-bottom:0.5rem; }
.warn { background:rgba(230,162,60,.1); border:1px solid rgba(230,162,60,.3); color:#f2c94c; border-radius:3px; padding:0.6rem 0.9rem; font-size:0.83rem; margin-bottom:0.5rem; }
.sep { border:none; border-top:1px solid var(--border); margin:1.5rem 0; }
</style>
</head>
<body>
<div class="box">

  <h1>📦 Установка архива</h1>
  <div class="subtitle">первоначальная настройка</div>

  <?php if ($step === 'check'): ?>

    <?php foreach ($errors as $e): ?>
      <div class="err">⚠ <?= $e ?></div>
    <?php endforeach; ?>
    <?php foreach ($pre_errors as $e): ?>
      <div class="err">⚠ <?= $e ?></div>
    <?php endforeach; ?>

    <?php if (empty($pre_errors)): ?>
      <div class="ok">✓ config.php найден</div>
      <?php if ($db_ok): ?>
        <div class="ok">✓ Подключение к базе данных успешно</div>
      <?php endif; ?>
      <?php if ($installed): ?>
        <div class="warn">⚠ База данных уже содержит таблицу users. Повторная установка пересоздаст таблицы (данные не сотрёт — используется IF NOT EXISTS).</div>
      <?php endif; ?>
    <?php endif; ?>

    <?php if (empty($pre_errors) && $db_ok): ?>
    <hr class="sep">
    <p style="font-size:0.85rem;color:var(--text-muted);margin-bottom:1.2rem">Создайте учётную запись администратора:</p>
    <form method="POST">
      <div class="mb-3">
        <label class="form-label">Имя (отображается)</label>
        <input type="text" name="display_name" class="form-control" placeholder="Алекс" autofocus
               value="<?= htmlspecialchars($_POST['display_name'] ?? '') ?>">
      </div>
      <div class="mb-3">
        <label class="form-label">Логин</label>
        <input type="text" name="username" class="form-control" placeholder="alex"
               value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
               autocomplete="username">
      </div>
      <div class="mb-3">
        <label class="form-label">Пароль</label>
        <input type="password" name="password" class="form-control"
               placeholder="минимум 4 символа" autocomplete="new-password">
      </div>
      <div class="mb-3">
        <label class="form-label">Повторите пароль</label>
        <input type="password" name="password2" class="form-control" autocomplete="new-password">
      </div>
      <button type="submit" name="install" value="1" class="btn-gold" style="width:100%;margin-top:0.5rem">
        Установить →
      </button>
    </form>
    <?php endif; ?>

  <?php elseif ($step === 'done'): ?>

    <div class="ok" style="font-size:0.9rem;padding:0.9rem">
      ✅ Установка завершена успешно!<br>
      <span style="color:var(--text-muted);font-size:0.82rem">Таблицы созданы, администратор добавлен.</span>
    </div>

    <div style="margin:1.5rem 0;font-size:0.85rem;color:var(--text-muted);line-height:1.6">
      Теперь вы можете <a href="login.php" style="color:var(--gold)">войти в систему</a> с указанными данными.
    </div>

    <hr class="sep">
    <p style="font-size:0.85rem;color:var(--text-muted);margin-bottom:1rem">
      Удалить этот файл с сервера?
    </p>
    <div style="display:flex;gap:0.8rem;flex-wrap:wrap">
      <a href="?delete_installer=1" class="btn-danger-soft">🗑 Удалить install.php</a>
      <a href="login.php" class="btn-gold" style="text-decoration:none">Войти →</a>
    </div>
    <p style="font-size:0.75rem;color:var(--text-muted);margin-top:0.8rem">
      Если оставить файл — можно будет запустить установку повторно для тестирования.
    </p>

  <?php endif; ?>

</div>
</body>
</html>
