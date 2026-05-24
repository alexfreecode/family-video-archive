<?php
/**
 * install.php — initial setup for the archive
 * After successful installation it will offer to delete this file.
 * To re-run the installer simply upload the file again.
 */

$step   = 'check'; // check → install → done
$errors = [];
$info   = [];

// ── Step: environment check ───────────────────────────────────────────────
function check_env(): array {
    $errors = [];
    if (!file_exists(__DIR__ . '/config.php')) {
        $errors[] = 'File <strong>config.php</strong> not found. Copy <code>config.example.php</code> → <code>config.php</code> and fill in your details.';
    }
    if (!extension_loaded('pdo_mysql')) {
        $errors[] = 'PHP extension <strong>pdo_mysql</strong> is not loaded.';
    }
    if (!extension_loaded('gd')) {
        $errors[] = 'PHP extension <strong>GD</strong> is not loaded (required for thumbnails).';
    }
    $uploads = __DIR__ . '/uploads/thumbnails';
    if (!is_dir($uploads)) {
        if (!mkdir($uploads, 0755, true)) {
            $errors[] = 'Could not create folder <strong>uploads/thumbnails/</strong>. Create it manually and set permissions to 755.';
        }
    } elseif (!is_writable($uploads)) {
        $errors[] = 'Folder <strong>uploads/thumbnails/</strong> is not writable. Set permissions to 755.';
    }
    return $errors;
}

// ── Step: database connection ─────────────────────────────────────────────
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

// ── Step: check if already installed ─────────────────────────────────────
function is_installed(PDO $pdo): bool {
    try {
        $pdo->query("SELECT 1 FROM users LIMIT 1");
        return true;
    } catch (Exception $e) {
        return false;
    }
}

// ── Handle install form ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['install'])) {
    $step = 'install';

    $name   = trim($_POST['display_name'] ?? '');
    $login  = trim($_POST['username'] ?? '');
    $pass   = $_POST['password'] ?? '';
    $pass2  = $_POST['password2'] ?? '';

    if (!$name)              $errors[] = 'Please enter the administrator name.';
    if (!$login)             $errors[] = 'Please enter a username.';
    if (strlen($pass) < 4)  $errors[] = 'Password must be at least 4 characters.';
    if ($pass !== $pass2)   $errors[] = 'Passwords do not match.';

    $env_errors = check_env();
    $errors = array_merge($errors, $env_errors);

    if (empty($errors)) {
        $pdo = try_connect();
        if (!$pdo) {
            $errors[] = 'Could not connect to the database. Please check config.php.';
        } else {
            try {
                $tables = [
                    "CREATE TABLE IF NOT EXISTS `users` (
                      `id`            int(11)      NOT NULL AUTO_INCREMENT,
                      `username`      varchar(60)  NOT NULL,
                      `display_name`  varchar(100) NOT NULL,
                      `password`      varchar(255) NOT NULL,
                      `color`         varchar(7)   NOT NULL DEFAULT '#c9a84c',
                      `is_admin`      tinyint(1)   NOT NULL DEFAULT 0,
                      `created_at`    datetime     NOT NULL DEFAULT current_timestamp(),
                      `theme`         varchar(10)  NOT NULL DEFAULT 'dark',
                      `language`      varchar(5)   NOT NULL DEFAULT 'ru',
                      `reset_code`    varchar(10)  DEFAULT NULL,
                      `reset_expires` datetime     DEFAULT NULL,
                      `is_active`     tinyint(1)   NOT NULL DEFAULT 1,
                      `last_seen`             datetime     DEFAULT NULL,
                      `prev_seen`             datetime     DEFAULT NULL,
                      `telegram_chat_id`      bigint(20)   DEFAULT NULL,
                      `telegram_notify`       tinyint(1)   NOT NULL DEFAULT 1,
                      `telegram_connected_at` datetime     DEFAULT NULL,
                      `telegram_link_code`    varchar(32)  DEFAULT NULL,
                      `telegram_link_expires` datetime     DEFAULT NULL,
                      PRIMARY KEY (`id`),
                      UNIQUE KEY `username` (`username`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",

                    "CREATE TABLE IF NOT EXISTS `events` (
                      `id`          int(11)      NOT NULL AUTO_INCREMENT,
                      `user_id`     int(11)      NOT NULL,
                      `title`       varchar(255) NOT NULL,
                      `event_date`  date         DEFAULT NULL,
                      `description` text         DEFAULT NULL,
                      `thumbnail`   varchar(255) DEFAULT NULL,
                      `created_at`  datetime     NOT NULL DEFAULT current_timestamp(),
                      PRIMARY KEY (`id`),
                      KEY `user_id` (`user_id`),
                      CONSTRAINT `events_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",

                    "CREATE TABLE IF NOT EXISTS `videos` (
                      `id`          int(11)      NOT NULL AUTO_INCREMENT,
                      `user_id`     int(11)      NOT NULL,
                      `youtube_id`  varchar(20)  NOT NULL,
                      `title`       varchar(255) NOT NULL,
                      `description` text         DEFAULT NULL,
                      `created_at`  datetime     NOT NULL DEFAULT current_timestamp(),
                      `filmed_at`   date         DEFAULT NULL,
                      `location`    varchar(255) DEFAULT NULL,
                      `event`       varchar(255) DEFAULT NULL,
                      `event_id`    int(11)      DEFAULT NULL,
                      `tags`        varchar(500) DEFAULT NULL,
                      PRIMARY KEY (`id`),
                      KEY `user_id` (`user_id`),
                      CONSTRAINT `videos_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
                      CONSTRAINT `videos_event_fk` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE SET NULL
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",

                    "CREATE TABLE IF NOT EXISTS `video_access` (
                      `video_id` int(11) NOT NULL,
                      `user_id`  int(11) NOT NULL,
                      PRIMARY KEY (`video_id`, `user_id`),
                      CONSTRAINT `video_access_ibfk_1` FOREIGN KEY (`video_id`) REFERENCES `videos` (`id`) ON DELETE CASCADE
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",

                    "CREATE TABLE IF NOT EXISTS `video_participants` (
                      `id`       int(11)      NOT NULL AUTO_INCREMENT,
                      `video_id` int(11)      NOT NULL,
                      `user_id`  int(11)      DEFAULT NULL,
                      `name`     varchar(100) DEFAULT NULL,
                      PRIMARY KEY (`id`),
                      KEY `video_id` (`video_id`),
                      CONSTRAINT `vp_ibfk_1` FOREIGN KEY (`video_id`) REFERENCES `videos` (`id`) ON DELETE CASCADE
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",

                    "CREATE TABLE IF NOT EXISTS `event_access` (
                      `event_id` int(11) NOT NULL,
                      `user_id`  int(11) NOT NULL,
                      PRIMARY KEY (`event_id`, `user_id`),
                      CONSTRAINT `ea_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",

                    "CREATE TABLE IF NOT EXISTS `media` (
                      `id`          int(11)      NOT NULL AUTO_INCREMENT,
                      `event_id`    int(11)      NOT NULL,
                      `user_id`     int(11)      NOT NULL,
                      `type`        enum('photo','album','link') NOT NULL,
                      `url`         text         NOT NULL,
                      `title`       varchar(255) DEFAULT NULL,
                      `thumbnail`   varchar(255) DEFAULT NULL,
                      `description` text         DEFAULT NULL,
                      `created_at`  datetime     NOT NULL DEFAULT current_timestamp(),
                      PRIMARY KEY (`id`),
                      KEY `event_id` (`event_id`),
                      CONSTRAINT `media_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE,
                      CONSTRAINT `media_ibfk_2` FOREIGN KEY (`user_id`)  REFERENCES `users`  (`id`) ON DELETE CASCADE
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",

                    "CREATE TABLE IF NOT EXISTS `media_access` (
                      `media_id` int(11) NOT NULL,
                      `user_id`  int(11) NOT NULL,
                      PRIMARY KEY (`media_id`, `user_id`),
                      CONSTRAINT `ma_ibfk_1` FOREIGN KEY (`media_id`) REFERENCES `media` (`id`) ON DELETE CASCADE
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",

                    "CREATE TABLE IF NOT EXISTS `event_comments` (
                      `id`         int(11)  NOT NULL AUTO_INCREMENT,
                      `event_id`   int(11)  NOT NULL,
                      `user_id`    int(11)  NOT NULL,
                      `text`       text     NOT NULL,
                      `created_at` datetime NOT NULL DEFAULT current_timestamp(),
                      PRIMARY KEY (`id`),
                      KEY `event_id` (`event_id`),
                      CONSTRAINT `ec_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE,
                      CONSTRAINT `ec_ibfk_2` FOREIGN KEY (`user_id`)  REFERENCES `users`  (`id`) ON DELETE CASCADE
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",

                    "CREATE TABLE IF NOT EXISTS `invite_codes` (
                      `id`         int(11)     NOT NULL AUTO_INCREMENT,
                      `code`       varchar(10) NOT NULL,
                      `color`      varchar(7)  NOT NULL DEFAULT '#c9a84c',
                      `used`       tinyint(1)  NOT NULL DEFAULT 0,
                      `created_at` datetime    NOT NULL DEFAULT current_timestamp(),
                      `expires_at` datetime    NOT NULL,
                      PRIMARY KEY (`id`),
                      UNIQUE KEY `code` (`code`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",

                    "CREATE TABLE IF NOT EXISTS `access_notifications` (
                      `id`          int(11)  NOT NULL AUTO_INCREMENT,
                      `user_id`     int(11)  NOT NULL,
                      `new_user_id` int(11)  NOT NULL,
                      `created_at`  datetime NOT NULL DEFAULT current_timestamp(),
                      PRIMARY KEY (`id`),
                      UNIQUE KEY `user_new_user` (`user_id`, `new_user_id`),
                      KEY `an_ibfk_2` (`new_user_id`),
                      CONSTRAINT `an_ibfk_1` FOREIGN KEY (`user_id`)     REFERENCES `users` (`id`) ON DELETE CASCADE,
                      CONSTRAINT `an_ibfk_2` FOREIGN KEY (`new_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",

                    "CREATE TABLE IF NOT EXISTS `comments` (
                      `id`         int(11)  NOT NULL AUTO_INCREMENT,
                      `video_id`   int(11)  NOT NULL,
                      `user_id`    int(11)  NOT NULL,
                      `text`       text     NOT NULL,
                      `created_at` datetime NOT NULL DEFAULT current_timestamp(),
                      PRIMARY KEY (`id`),
                      KEY `video_id` (`video_id`),
                      KEY `comments_ibfk_2` (`user_id`),
                      CONSTRAINT `comments_ibfk_1` FOREIGN KEY (`video_id`) REFERENCES `videos` (`id`) ON DELETE CASCADE,
                      CONSTRAINT `comments_ibfk_2` FOREIGN KEY (`user_id`)  REFERENCES `users`  (`id`) ON DELETE CASCADE
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",

                    "CREATE TABLE IF NOT EXISTS `remember_tokens` (
                      `id`         int(11)      NOT NULL AUTO_INCREMENT,
                      `user_id`    int(11)      NOT NULL,
                      `token_hash` varchar(64)  NOT NULL,
                      `expires_at` datetime     NOT NULL,
                      PRIMARY KEY (`id`),
                      UNIQUE KEY `token_hash` (`token_hash`),
                      KEY `user_id` (`user_id`),
                      CONSTRAINT `rt_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",
                ];

                $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
                foreach ($tables as $sql) {
                    $pdo->exec($sql);
                }
                $pdo->exec('SET FOREIGN_KEY_CHECKS=1');

                // Create administrator account
                $hash = password_hash($pass, PASSWORD_DEFAULT);
                $pdo->prepare(
                    "INSERT INTO users (username, display_name, password, color, is_admin, is_active, language)
                     VALUES (?, ?, ?, '#c9a84c', 1, 1, 'en')"
                )->execute([$login, $name, $hash]);

                $step = 'done';
            } catch (Exception $e) {
                $errors[] = 'Error creating tables: ' . htmlspecialchars($e->getMessage());
            }
        }
    }

    if (!empty($errors)) $step = 'check';
}

// ── Pre-check for form display ────────────────────────────────────────────
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
            $pre_errors[] = 'Could not connect to the database. Please check the credentials in config.php.';
        }
    }
}

// ── Delete installer file ─────────────────────────────────────────────────
if (isset($_GET['delete_installer']) && $_GET['delete_installer'] === '1') {
    if (@unlink(__FILE__)) {
        header('Location: index.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Archive Setup</title>
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

  <h1>📦 Archive Setup</h1>
  <div class="subtitle">initial configuration</div>

  <?php if ($step === 'check'): ?>

    <?php foreach ($errors as $e): ?>
      <div class="err">⚠ <?= $e ?></div>
    <?php endforeach; ?>
    <?php foreach ($pre_errors as $e): ?>
      <div class="err">⚠ <?= $e ?></div>
    <?php endforeach; ?>

    <?php if (empty($pre_errors)): ?>
      <div class="ok">✓ config.php found</div>
      <?php if ($db_ok): ?>
        <div class="ok">✓ Database connection successful</div>
      <?php endif; ?>
      <?php if ($installed): ?>
        <div class="warn">⚠ The database already contains a <strong>users</strong> table. Re-running the installer will recreate all tables (existing data is preserved — <code>IF NOT EXISTS</code> is used).</div>
      <?php endif; ?>
    <?php endif; ?>

    <?php if (empty($pre_errors) && $db_ok): ?>
    <hr class="sep">
    <p style="font-size:0.85rem;color:var(--text-muted);margin-bottom:1.2rem">Create the administrator account:</p>
    <form method="POST">
      <div class="mb-3">
        <label class="form-label">Display name</label>
        <input type="text" name="display_name" class="form-control" placeholder="Alex" autofocus
               value="<?= htmlspecialchars($_POST['display_name'] ?? '') ?>">
      </div>
      <div class="mb-3">
        <label class="form-label">Username</label>
        <input type="text" name="username" class="form-control" placeholder="alex"
               value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
               autocomplete="username">
      </div>
      <div class="mb-3">
        <label class="form-label">Password</label>
        <input type="password" name="password" class="form-control"
               placeholder="at least 4 characters" autocomplete="new-password">
      </div>
      <div class="mb-3">
        <label class="form-label">Confirm password</label>
        <input type="password" name="password2" class="form-control" autocomplete="new-password">
      </div>
      <button type="submit" name="install" value="1" class="btn-gold" style="width:100%;margin-top:0.5rem">
        Install →
      </button>
    </form>
    <?php endif; ?>

  <?php elseif ($step === 'done'): ?>

    <div class="ok" style="font-size:0.9rem;padding:0.9rem">
      ✅ Installation completed successfully!<br>
      <span style="color:var(--text-muted);font-size:0.82rem">Tables created, administrator account added.</span>
    </div>

    <div style="margin:1.5rem 0;font-size:0.85rem;color:var(--text-muted);line-height:1.6">
      You can now <a href="login.php" style="color:var(--gold)">log in</a> with the credentials you just created.
    </div>

    <hr class="sep">
    <p style="font-size:0.85rem;color:var(--text-muted);margin-bottom:1rem">
      Delete this file from the server?
    </p>
    <div style="display:flex;gap:0.8rem;flex-wrap:wrap">
      <a href="?delete_installer=1" class="btn-danger-soft">🗑 Delete install.php</a>
      <a href="login.php" class="btn-gold" style="text-decoration:none">Log in →</a>
    </div>
    <p style="font-size:0.75rem;color:var(--text-muted);margin-top:0.8rem">
      If you keep the file, you can re-run the installer at any time for testing.
    </p>

  <?php endif; ?>

</div>
</body>
</html>
