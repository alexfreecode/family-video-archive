<?php
require_once __DIR__ . '/config.php';

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}

// ─── I18n ──────────────────────────────────────────────────────────────────

/**
 * Определяет язык и загружает строки в $GLOBALS['_lang'].
 * Вызывать в начале каждого файла перед выводом HTML.
 * Если lang уже загружен — повторный вызов без аргумента пропускается.
 */
function load_language(?string $force_lang = null): void {
    if ($force_lang === null && isset($GLOBALS['_current_lang'])) {
        return; // уже загружено
    }

    $lang = $force_lang;

    if (!$lang) {
        if (isset($_SESSION['user_id'])) {
            try {
                $stmt = db()->prepare("SELECT language FROM users WHERE id=?");
                $stmt->execute([$_SESSION['user_id']]);
                $row  = $stmt->fetch();
                $lang = $row['language'] ?? null;
            } catch (Exception $e) {
                $lang = null; // колонка language ещё не существует
            }
        }
        if (!$lang && isset($_COOKIE['pref_lang'])) {
            $allowed_cookie = ['ru', 'en', 'uk', 'pl', 'es', 'pt', 'de'];
            $cookie_lang = $_COOKIE['pref_lang'];
            if (in_array($cookie_lang, $allowed_cookie, true)) {
                $lang = $cookie_lang;
            }
        }
        if (!$lang) {
            $lang = detect_browser_language();
        }
    }

    $allowed = ['ru', 'en', 'uk', 'pl', 'es', 'pt', 'de'];
    if (!in_array($lang, $allowed, true)) $lang = 'ru';

    $file = __DIR__ . '/lang/' . $lang . '.php';
    $GLOBALS['_lang']         = file_exists($file) ? require $file : [];
    $GLOBALS['_current_lang'] = $lang;
}

/** Возвращает переведённую строку по ключу, или $default / сам ключ. */
function t(string $key, string $default = ''): string {
    return $GLOBALS['_lang'][$key] ?? ($default !== '' ? $default : $key);
}

/** Возвращает текущий язык (ISO-код). */
function current_lang(): string {
    return $GLOBALS['_current_lang'] ?? 'ru';
}

/** Определяет язык по заголовку Accept-Language браузера. */
function detect_browser_language(): string {
    $accept = strtolower($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '');
    // Берём первые два символа первого тега
    if (preg_match('/^([a-z]{2})/', $accept, $m)) {
        $map = ['ru' => 'ru', 'uk' => 'uk', 'pl' => 'pl', 'en' => 'en', 'es' => 'es', 'pt' => 'pt', 'de' => 'de'];
        return $map[$m[1]] ?? 'ru';
    }
    return 'ru';
}

/** Сохраняет языковой выбор пользователя в БД. */
function save_user_language(int $user_id, string $lang): void {
    $allowed = ['ru', 'en', 'uk', 'pl', 'es', 'pt', 'de'];
    if (!in_array($lang, $allowed, true)) return;
    try {
        db()->prepare("UPDATE users SET language=? WHERE id=?")->execute([$lang, $user_id]);
    } catch (Exception $e) {
        return; // колонка language ещё не существует — миграция не выполнена
    }
    // Сбрасываем кэш чтобы t() и current_lang() сразу отражали новый язык
    $GLOBALS['_current_lang'] = null;
    unset($GLOBALS['_current_lang']);
    load_language($lang);
}

// ─── Конец I18n ─────────────────────────────────────────────────────────────

function current_user(): ?array {
    if (!isset($_SESSION['user_id'])) return null;
    $stmt = db()->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch() ?: null;
}

function other_users(int $exclude_id): array {
    $stmt = db()->prepare("SELECT id, display_name, color FROM users WHERE id != ? ORDER BY display_name");
    $stmt->execute([$exclude_id]);
    return $stmt->fetchAll();
}

function all_users(): array {
    return db()->query("SELECT id, username, display_name, color, is_admin, is_active, last_seen, telegram_chat_id, telegram_connected_at FROM users ORDER BY display_name")->fetchAll();
}

function get_videos_for_user(int $user_id, string $search = '', string $sort = 'filmed', string $order = 'desc'): array {
    $search_clause = $search ? "AND (v.title LIKE :search OR v.description LIKE :search2)" : "";
    $order_sql = $order === 'asc' ? 'ASC' : 'DESC';
    $sort_sql = $sort === 'added'
        ? "v.created_at $order_sql"
        : "COALESCE(v.filmed_at, v.created_at) $order_sql";
    $sql = "
        SELECT v.*, COALESCE(u.display_name, 'Удалённый пользователь') as author_name, COALESCE(u.color, '#666666') as author_color,
               ev.title as event_title,
               (SELECT GROUP_CONCAT(vu2.display_name SEPARATOR ', ')
                FROM video_access va2
                JOIN users vu2 ON vu2.id = va2.user_id
                WHERE va2.video_id = v.id AND va2.user_id != 0) as viewer_names,
               (SELECT COUNT(*) FROM video_access va3 WHERE va3.video_id = v.id AND va3.user_id = 0) as is_public
        FROM videos v
        LEFT JOIN users u ON u.id = v.user_id
        LEFT JOIN events ev ON ev.id = v.event_id
        WHERE (
            v.user_id = :uid
            OR EXISTS (SELECT 1 FROM video_access va WHERE va.video_id = v.id AND va.user_id = 0)
            OR EXISTS (SELECT 1 FROM video_access va WHERE va.video_id = v.id AND va.user_id = :uid2)
        )
        $search_clause
        ORDER BY $sort_sql
    ";
    $stmt = db()->prepare($sql);
    $params = [':uid' => $user_id, ':uid2' => $user_id];
    if ($search) {
        $params[':search']  = "%$search%";
        $params[':search2'] = "%$search%";
    }
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function get_my_videos(int $user_id, string $search = '', string $sort = 'filmed', string $order = 'desc'): array {
    $search_clause = $search ? "AND (v.title LIKE :search OR v.description LIKE :search2)" : "";
    $order_sql = $order === 'asc' ? 'ASC' : 'DESC';
    $sort_sql = $sort === 'added'
        ? "v.created_at $order_sql"
        : "COALESCE(v.filmed_at, v.created_at) $order_sql";
    $sql = "
        SELECT v.*, COALESCE(u.display_name, 'Удалённый пользователь') as author_name, COALESCE(u.color, '#666666') as author_color,
               ev.title as event_title,
               (SELECT GROUP_CONCAT(vu2.display_name SEPARATOR ', ')
                FROM video_access va2
                JOIN users vu2 ON vu2.id = va2.user_id
                WHERE va2.video_id = v.id AND va2.user_id != 0) as viewer_names,
               (SELECT COUNT(*) FROM video_access va3 WHERE va3.video_id = v.id AND va3.user_id = 0) as is_public
        FROM videos v
        LEFT JOIN users u ON u.id = v.user_id
        LEFT JOIN events ev ON ev.id = v.event_id
        WHERE v.user_id = :uid
        $search_clause
        ORDER BY COALESCE(v.filmed_at, v.created_at) DESC
    ";
    $stmt = db()->prepare($sql);
    $params = [':uid' => $user_id];
    if ($search) {
        $params[':search']  = "%$search%";
        $params[':search2'] = "%$search%";
    }
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function get_video_access(int $video_id): array {
    $stmt = db()->prepare("SELECT user_id FROM video_access WHERE video_id = ?");
    $stmt->execute([$video_id]);
    return array_column($stmt->fetchAll(), 'user_id');
}

function save_video_access(int $video_id, string $access_type, array $user_ids = []): void {
    db()->prepare("DELETE FROM video_access WHERE video_id = ?")->execute([$video_id]);
    if ($access_type === 'all') {
        db()->prepare("INSERT INTO video_access (video_id, user_id) VALUES (?, 0)")->execute([$video_id]);
    } elseif ($access_type === 'selected' && !empty($user_ids)) {
        $stmt = db()->prepare("INSERT INTO video_access (video_id, user_id) VALUES (?, ?)");
        foreach ($user_ids as $uid) {
            $stmt->execute([$video_id, (int)$uid]);
        }
    }
}

function extract_youtube_id(string $url): ?string {
    $patterns = [
        '/youtube\.com\/watch\?v=([a-zA-Z0-9_-]{11})/',
        '/youtu\.be\/([a-zA-Z0-9_-]{11})/',
        '/youtube\.com\/embed\/([a-zA-Z0-9_-]{11})/',
        '/youtube\.com\/shorts\/([a-zA-Z0-9_-]{11})/',
    ];
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $url, $m)) return $m[1];
    }
    return null;
}

function h(?string $s): string {
    return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
}

// Получить участников для нескольких видео сразу (против N+1)
function get_participants_for_videos(array $video_ids): array {
    if (empty($video_ids)) return [];
    $placeholders = implode(',', array_fill(0, count($video_ids), '?'));
    $stmt = db()->prepare("
        SELECT vp.video_id, vp.user_id, vp.name,
               u.display_name as user_name, u.color as user_color
        FROM video_participants vp
        LEFT JOIN users u ON u.id = vp.user_id
        WHERE vp.video_id IN ($placeholders)
        ORDER BY vp.id
    ");
    $stmt->execute($video_ids);
    $rows = $stmt->fetchAll();
    // Группируем по video_id
    $result = [];
    foreach ($rows as $row) {
        $result[$row['video_id']][] = $row;
    }
    return $result;
}

// Получить участников видео
function get_video_participants(int $video_id): array {
    $stmt = db()->prepare("
        SELECT vp.*, u.display_name as user_name, u.color as user_color
        FROM video_participants vp
        LEFT JOIN users u ON u.id = vp.user_id
        WHERE vp.video_id = ?
        ORDER BY vp.id
    ");
    $stmt->execute([$video_id]);
    return $stmt->fetchAll();
}

// Сохранить участников видео
function save_video_participants(int $video_id, array $user_ids, string $external_names): void {
    db()->prepare("DELETE FROM video_participants WHERE video_id = ?")->execute([$video_id]);
    // Системные пользователи
    $stmt = db()->prepare("INSERT INTO video_participants (video_id, user_id) VALUES (?, ?)");
    foreach ($user_ids as $uid) {
        $uid = (int)$uid;
        if ($uid > 0) $stmt->execute([$video_id, $uid]);
    }
    // Внешние люди
    foreach (array_map('trim', explode(',', $external_names)) as $name) {
        if ($name !== '') {
            db()->prepare("INSERT INTO video_participants (video_id, name) VALUES (?, ?)")
               ->execute([$video_id, $name]);
        }
    }
}

// Все видео участника (доступные текущему пользователю)
function get_member_videos(int $member_id, int $current_user_id): array {
    $sql = "
        SELECT v.*, COALESCE(u.display_name, 'Удалённый пользователь') as author_name, COALESCE(u.color, '#666666') as author_color,
               ev.title as event_title,
               (SELECT COUNT(*) FROM video_access va3 WHERE va3.video_id = v.id AND va3.user_id = 0) as is_public
        FROM videos v
        LEFT JOIN users u ON u.id = v.user_id
        LEFT JOIN events ev ON ev.id = v.event_id
        WHERE v.user_id = :member_id
          AND (
            v.user_id = :cur
            OR EXISTS (SELECT 1 FROM video_access va WHERE va.video_id = v.id AND va.user_id = 0)
            OR EXISTS (SELECT 1 FROM video_access va WHERE va.video_id = v.id AND va.user_id = :cur2)
          )
        ORDER BY COALESCE(v.filmed_at, v.created_at) DESC
    ";
    $stmt = db()->prepare($sql);
    $stmt->execute([':member_id' => $member_id, ':cur' => $current_user_id, ':cur2' => $current_user_id]);
    return $stmt->fetchAll();
}

// Создать инвайт-код
function create_invite_code(string $color): string {
    do {
        $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $exists = db()->prepare("SELECT COUNT(*) FROM invite_codes WHERE code=? AND used=0");
        $exists->execute([$code]);
    } while ($exists->fetchColumn() > 0);

    $expires = date('Y-m-d H:i:s', strtotime('+48 hours'));
    db()->prepare("INSERT INTO invite_codes (code, color, expires_at) VALUES (?,?,?)")
       ->execute([$code, $color, $expires]);
    return $code;
}

// Получить активные инвайт-коды
function get_invite_codes(): array {
    return db()->query("SELECT * FROM invite_codes WHERE used=0 AND expires_at > NOW() ORDER BY created_at DESC")->fetchAll();
}

// Создать код сброса пароля
function create_reset_code(int $user_id): string {
    $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $expires = date('Y-m-d H:i:s', strtotime('+2 hours'));
    db()->prepare("UPDATE users SET reset_code=?, reset_expires=? WHERE id=?")
       ->execute([$code, $expires, $user_id]);
    return $code;
}

// Создать уведомления для всех пользователей кроме нового
function create_access_notifications(int $new_user_id): void {
    // Уведомления с запросом доступа — только тем у кого есть видео
    $s1 = db()->prepare("SELECT DISTINCT user_id FROM videos WHERE user_id != ?");
    $s1->execute([$new_user_id]);
    $users_with_videos = $s1->fetchAll();
    $stmt = db()->prepare("INSERT IGNORE INTO access_notifications (user_id, new_user_id) VALUES (?, ?)");
    foreach ($users_with_videos as $u) {
        $stmt->execute([$u['user_id'], $new_user_id]);
    }
    // Информационные уведомления — всем остальным (без видео)
    $s2 = db()->prepare("SELECT id FROM users WHERE id != ? AND id NOT IN (SELECT DISTINCT user_id FROM videos)");
    $s2->execute([$new_user_id]);
    $users_without_videos = $s2->fetchAll();
    $stmt2 = db()->prepare("INSERT IGNORE INTO access_notifications (user_id, new_user_id) VALUES (?, ?)");
    foreach ($users_without_videos as $u) {
        $stmt2->execute([$u['id'], $new_user_id]);
    }
}

// Получить непрочитанные уведомления пользователя
function get_pending_notifications(int $user_id): array {
    $stmt = db()->prepare("
        SELECT an.*, u.display_name as new_user_name, u.color as new_user_color
        FROM access_notifications an
        JOIN users u ON u.id = an.new_user_id
        WHERE an.user_id = ?
        ORDER BY an.created_at DESC
    ");
    $stmt->execute([$user_id]);
    return $stmt->fetchAll();
}

// Удалить уведомление
function delete_notification(int $notification_id, int $user_id): void {
    db()->prepare("DELETE FROM access_notifications WHERE id = ? AND user_id = ?")
       ->execute([$notification_id, $user_id]);
}

// Дать доступ ко всем своим видео новому пользователю
function grant_all_access(int $owner_id, int $new_user_id): void {
    $videos = db()->prepare("SELECT id FROM videos WHERE user_id = ?");
    $videos->execute([$owner_id]);
    $stmt = db()->prepare("INSERT IGNORE INTO video_access (video_id, user_id) VALUES (?, ?)");
    foreach ($videos->fetchAll() as $v) {
        // Добавляем только если не публичное
        $is_pub = db()->prepare("SELECT COUNT(*) FROM video_access WHERE video_id=? AND user_id=0");
        $is_pub->execute([$v['id']]);
        if (!$is_pub->fetchColumn()) {
            $stmt->execute([$v['id'], $new_user_id]);
        }
    }
}

// Деактивировать пользователя
function deactivate_user(int $user_id): void {
    db()->prepare("UPDATE users SET is_active=0 WHERE id=?")->execute([$user_id]);
}

// Активировать пользователя
function activate_user(int $user_id): void {
    db()->prepare("UPDATE users SET is_active=1 WHERE id=?")->execute([$user_id]);
}

// Получить комментарии к видео
function get_comments(int $video_id): array {
    $stmt = db()->prepare("
        SELECT c.*, COALESCE(u.display_name, 'Удалённый пользователь') as user_name,
               COALESCE(u.color, '#666666') as user_color
        FROM comments c
        LEFT JOIN users u ON u.id = c.user_id
        WHERE c.video_id = ?
        ORDER BY c.created_at ASC
    ");
    $stmt->execute([$video_id]);
    return $stmt->fetchAll();
}

// Добавить комментарий
function add_comment(int $video_id, int $user_id, string $text): void {
    db()->prepare("INSERT INTO comments (video_id, user_id, text) VALUES (?, ?, ?)")
       ->execute([$video_id, $user_id, $text]);
}

// Удалить комментарий
function delete_comment(int $comment_id, int $user_id, bool $is_admin = false): void {
    if ($is_admin) {
        db()->prepare("DELETE FROM comments WHERE id = ?")->execute([$comment_id]);
    } else {
        db()->prepare("DELETE FROM comments WHERE id = ? AND user_id = ?")->execute([$comment_id, $user_id]);
    }
}

// Создать превью из файла или URL, вернуть путь или null
function make_thumbnail(string $source, string $prefix = 'thumb', bool $is_url = false): ?string {
    $upload_dir = __DIR__ . '/uploads/thumbnails/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

    if ($is_url) {
        // Скачиваем изображение по URL
        $ctx = stream_context_create(['http' => ['timeout' => 8, 'user_agent' => 'Mozilla/5.0']]);
        $data = @file_get_contents($source, false, $ctx);
        if (!$data) return null;
        $tmp = tempnam(sys_get_temp_dir(), 'img');
        file_put_contents($tmp, $data);
        $ext = 'jpg';
        // Определяем тип по содержимому
        $info = @getimagesize($tmp);
        if (!$info) { unlink($tmp); return null; }
        $mime = $info['mime'];
    } else {
        $tmp = $source;
        $info = @getimagesize($tmp);
        if (!$info) return null;
        $mime = $info['mime'];
    }

    $src = match($mime) {
        'image/jpeg' => @imagecreatefromjpeg($tmp),
        'image/png'  => @imagecreatefrompng($tmp),
        'image/webp' => @imagecreatefromwebp($tmp),
        default      => null,
    };
    if ($is_url) @unlink($tmp);
    if (!$src) return null;

    // EXIF ориентация
    if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
        $exif = @exif_read_data($is_url ? $tmp : $source);
        $orientation = $exif['Orientation'] ?? 1;
        $src = match($orientation) {
            3 => imagerotate($src, 180, 0),
            6 => imagerotate($src, -90, 0),
            8 => imagerotate($src, 90, 0),
            default => $src,
        };
    }

    $w = imagesx($src); $h = imagesy($src);
    $tw = 480; $th = 270;
    $ratio = min($tw/$w, $th/$h);
    $nw = (int)($w*$ratio); $nh = (int)($h*$ratio);
    $dst = imagecreatetruecolor($tw, $th);
    $bg  = imagecolorallocate($dst, 26, 24, 20);
    imagefill($dst, 0, 0, $bg);
    imagecopyresampled($dst, $src, (int)(($tw-$nw)/2), (int)(($th-$nh)/2), 0, 0, $nw, $nh, $w, $h);
    $filename = $prefix . '_' . uniqid() . '.jpg';
    imagejpeg($dst, $upload_dir . $filename, 85);
    imagedestroy($src);
    imagedestroy($dst);
    return 'uploads/thumbnails/' . $filename;
}

// Получить один медиа-элемент
function get_media_item(int $media_id): ?array {
    $stmt = db()->prepare("SELECT * FROM media WHERE id = ?");
    $stmt->execute([$media_id]);
    return $stmt->fetch() ?: null;
}

// Мои видео с ограниченным доступом (не "Все") — для страницы уведомлений
function get_my_private_videos(int $user_id): array {
    $sql = "
        SELECT v.*, COALESCE(u.display_name, 'Удалённый пользователь') as author_name
        FROM videos v
        LEFT JOIN users u ON u.id = v.user_id
        WHERE v.user_id = :uid
          AND NOT EXISTS (SELECT 1 FROM video_access va WHERE va.video_id = v.id AND va.user_id = 0)
        ORDER BY COALESCE(v.filmed_at, v.created_at) DESC
    ";
    $stmt = db()->prepare($sql);
    $stmt->execute([':uid' => $user_id]);
    return $stmt->fetchAll();
}

// =============================================
//  События
// =============================================

// Получить все доступные события для пользователя
function get_events_for_user(int $user_id, string $sort = 'event_date', string $order = 'desc'): array {
    $sort  = in_array($sort,  ['event_date', 'added']) ? $sort  : 'event_date';
    $order = in_array($order, ['asc', 'desc'])         ? $order : 'desc';
    $order_sql = ($order === 'asc') ? 'ASC' : 'DESC';
    $sort_sql  = ($sort === 'added')
        ? "e.created_at $order_sql"
        : "COALESCE(e.event_date, e.created_at) $order_sql";

    $sql = "
        SELECT e.*,
               COALESCE(u.display_name, 'Удалённый пользователь') as author_name,
               COALESCE(u.color, '#666666') as author_color,
               (SELECT COUNT(*) FROM videos v WHERE v.event_id = e.id
                AND (v.user_id = :uid
                     OR EXISTS (SELECT 1 FROM video_access va WHERE va.video_id = v.id AND va.user_id = 0)
                     OR EXISTS (SELECT 1 FROM video_access va WHERE va.video_id = v.id AND va.user_id = :uid2))
               ) as video_count,
               (SELECT COUNT(*) FROM media m WHERE m.event_id = e.id
                AND (m.user_id = :uid3
                     OR EXISTS (SELECT 1 FROM media_access ma WHERE ma.media_id = m.id AND ma.user_id = 0)
                     OR EXISTS (SELECT 1 FROM media_access ma WHERE ma.media_id = m.id AND ma.user_id = :uid4))
               ) as media_count
        FROM events e
        LEFT JOIN users u ON u.id = e.user_id
        WHERE (
            e.user_id = :uid5
            OR EXISTS (SELECT 1 FROM event_access ea WHERE ea.event_id = e.id AND ea.user_id = 0)
            OR EXISTS (SELECT 1 FROM event_access ea WHERE ea.event_id = e.id AND ea.user_id = :uid6)
        )
        ORDER BY $sort_sql
    ";
    $stmt = db()->prepare($sql);
    $stmt->execute([
        ':uid' => $user_id, ':uid2' => $user_id,
        ':uid3' => $user_id, ':uid4' => $user_id,
        ':uid5' => $user_id, ':uid6' => $user_id,
    ]);
    return $stmt->fetchAll();
}

// Получить одно событие
function get_event(int $event_id): ?array {
    $stmt = db()->prepare("
        SELECT e.*, COALESCE(u.display_name, 'Удалённый пользователь') as author_name,
               COALESCE(u.color, '#666666') as author_color
        FROM events e
        LEFT JOIN users u ON u.id = e.user_id
        WHERE e.id = ?
    ");
    $stmt->execute([$event_id]);
    return $stmt->fetch() ?: null;
}

// Получить видео события доступные пользователю
function get_event_videos(int $event_id, int $user_id): array {
    $sql = "
        SELECT v.*, COALESCE(u.display_name, 'Удалённый пользователь') as author_name,
               COALESCE(u.color, '#666666') as author_color,
               (SELECT COUNT(*) FROM video_access va3 WHERE va3.video_id = v.id AND va3.user_id = 0) as is_public
        FROM videos v
        LEFT JOIN users u ON u.id = v.user_id
        WHERE v.event_id = :eid
          AND (
            v.user_id = :uid
            OR EXISTS (SELECT 1 FROM video_access va WHERE va.video_id = v.id AND va.user_id = 0)
            OR EXISTS (SELECT 1 FROM video_access va WHERE va.video_id = v.id AND va.user_id = :uid2)
          )
        ORDER BY COALESCE(v.filmed_at, v.created_at) ASC
    ";
    $stmt = db()->prepare($sql);
    $stmt->execute([':eid' => $event_id, ':uid' => $user_id, ':uid2' => $user_id]);
    return $stmt->fetchAll();
}

// Получить медиа события доступные пользователю
function get_event_media(int $event_id, int $user_id): array {
    $sql = "
        SELECT m.*, COALESCE(u.display_name, 'Удалённый пользователь') as author_name,
               COALESCE(u.color, '#666666') as author_color
        FROM media m
        LEFT JOIN users u ON u.id = m.user_id
        WHERE m.event_id = :eid
          AND (
            m.user_id = :uid
            OR EXISTS (SELECT 1 FROM media_access ma WHERE ma.media_id = m.id AND ma.user_id = 0)
            OR EXISTS (SELECT 1 FROM media_access ma WHERE ma.media_id = m.id AND ma.user_id = :uid2)
          )
        ORDER BY m.created_at ASC
    ";
    $stmt = db()->prepare($sql);
    $stmt->execute([':eid' => $event_id, ':uid' => $user_id, ':uid2' => $user_id]);
    return $stmt->fetchAll();
}

// Получить комментарии к событию
function get_event_comments(int $event_id): array {
    $stmt = db()->prepare("
        SELECT c.*, COALESCE(u.display_name, 'Удалённый пользователь') as user_name,
               COALESCE(u.color, '#666666') as user_color
        FROM event_comments c
        LEFT JOIN users u ON u.id = c.user_id
        WHERE c.event_id = ?
        ORDER BY c.created_at ASC
    ");
    $stmt->execute([$event_id]);
    return $stmt->fetchAll();
}

// Добавить комментарий к событию
function add_event_comment(int $event_id, int $user_id, string $text): void {
    db()->prepare("INSERT INTO event_comments (event_id, user_id, text) VALUES (?, ?, ?)")
       ->execute([$event_id, $user_id, $text]);
}

// Удалить комментарий к событию
function delete_event_comment(int $comment_id, int $user_id, bool $is_admin = false): void {
    if ($is_admin) {
        db()->prepare("DELETE FROM event_comments WHERE id = ?")->execute([$comment_id]);
    } else {
        db()->prepare("DELETE FROM event_comments WHERE id = ? AND user_id = ?")->execute([$comment_id, $user_id]);
    }
}

// Сохранить доступ к событию
function save_event_access(int $event_id, string $access_type, array $user_ids = []): void {
    db()->prepare("DELETE FROM event_access WHERE event_id = ?")->execute([$event_id]);
    if ($access_type === 'all') {
        db()->prepare("INSERT INTO event_access (event_id, user_id) VALUES (?, 0)")->execute([$event_id]);
    } elseif ($access_type === 'selected' && !empty($user_ids)) {
        $stmt = db()->prepare("INSERT INTO event_access (event_id, user_id) VALUES (?, ?)");
        foreach ($user_ids as $uid) {
            $stmt->execute([$event_id, (int)$uid]);
        }
    }
}

// Получить все события для выпадающего списка
function get_all_events(): array {
    return db()->query("SELECT id, title, event_date FROM events ORDER BY COALESCE(event_date, created_at) DESC")->fetchAll();
}

// =============================================

function update_last_seen(int $user_id): void {
    // Сохраняем текущий last_seen в prev_seen, потом обновляем last_seen
    db()->prepare("
        UPDATE users SET prev_seen = last_seen, last_seen = NOW() WHERE id = ?
    ")->execute([$user_id]);
}

// Получить новинки с момента prev_seen
function get_new_since_prev(int $user_id): array {
    $stmt = db()->prepare("SELECT prev_seen FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $row = $stmt->fetch();
    $since = $row['prev_seen'] ?? null;

    if (!$since) return ['videos' => [], 'comments' => [], 'events' => [], 'media' => [], 'event_comments' => [], 'since' => null];

    // Новые доступные видео
    $vids = db()->prepare("
        SELECT v.id, v.youtube_id, v.title, v.filmed_at, v.created_at,
               COALESCE(u.display_name, 'Удалённый пользователь') as author_name
        FROM videos v
        LEFT JOIN users u ON u.id = v.user_id
        WHERE v.created_at > ?
          AND v.user_id != ?
          AND (
            EXISTS (SELECT 1 FROM video_access va WHERE va.video_id = v.id AND va.user_id = 0)
            OR EXISTS (SELECT 1 FROM video_access va WHERE va.video_id = v.id AND va.user_id = ?)
          )
        ORDER BY v.created_at DESC
    ");
    $vids->execute([$since, $user_id, $user_id]);

    // Новые комментарии к доступным видео
    $cmts = db()->prepare("
        SELECT c.id, c.text, c.created_at, c.video_id,
               v.title as video_title,
               COALESCE(u.display_name, 'Удалённый пользователь') as user_name,
               COALESCE(u.color, '#666666') as user_color
        FROM comments c
        JOIN videos v ON v.id = c.video_id
        LEFT JOIN users u ON u.id = c.user_id
        WHERE c.created_at > ?
          AND c.user_id != ?
          AND (
            v.user_id = ?
            OR EXISTS (SELECT 1 FROM video_access va WHERE va.video_id = v.id AND va.user_id = 0)
            OR EXISTS (SELECT 1 FROM video_access va WHERE va.video_id = v.id AND va.user_id = ?)
          )
        ORDER BY c.created_at DESC
    ");
    $cmts->execute([$since, $user_id, $user_id, $user_id]);

    // Новые доступные события
    $evts = db()->prepare("
        SELECT e.id, e.title, e.event_date, e.thumbnail, e.created_at,
               COALESCE(u.display_name, 'Удалённый пользователь') as author_name
        FROM events e
        LEFT JOIN users u ON u.id = e.user_id
        WHERE e.created_at > ?
          AND e.user_id != ?
          AND (
            EXISTS (SELECT 1 FROM event_access ea WHERE ea.event_id = e.id AND ea.user_id = 0)
            OR EXISTS (SELECT 1 FROM event_access ea WHERE ea.event_id = e.id AND ea.user_id = ?)
          )
        ORDER BY e.created_at DESC
    ");
    $evts->execute([$since, $user_id, $user_id]);

    // Новые медиа в доступных событиях
    $meds = db()->prepare("
        SELECT m.id, m.type, m.title, m.thumbnail, m.created_at, m.event_id,
               e.title as event_title,
               COALESCE(u.display_name, 'Удалённый пользователь') as author_name
        FROM media m
        JOIN events e ON e.id = m.event_id
        LEFT JOIN users u ON u.id = m.user_id
        WHERE m.created_at > ?
          AND m.user_id != ?
          AND (
            EXISTS (SELECT 1 FROM media_access ma WHERE ma.media_id = m.id AND ma.user_id = 0)
            OR EXISTS (SELECT 1 FROM media_access ma WHERE ma.media_id = m.id AND ma.user_id = ?)
          )
        ORDER BY m.created_at DESC
    ");
    $meds->execute([$since, $user_id, $user_id]);

    // Новые комментарии к доступным событиям
    $ecmts = db()->prepare("
        SELECT ec.id, ec.text, ec.created_at, ec.event_id,
               e.title as event_title,
               COALESCE(u.display_name, 'Удалённый пользователь') as user_name,
               COALESCE(u.color, '#666666') as user_color
        FROM event_comments ec
        JOIN events e ON e.id = ec.event_id
        LEFT JOIN users u ON u.id = ec.user_id
        WHERE ec.created_at > ?
          AND ec.user_id != ?
          AND (
            EXISTS (SELECT 1 FROM event_access ea WHERE ea.event_id = e.id AND ea.user_id = 0)
            OR EXISTS (SELECT 1 FROM event_access ea WHERE ea.event_id = e.id AND ea.user_id = ?)
          )
        ORDER BY ec.created_at DESC
    ");
    $ecmts->execute([$since, $user_id, $user_id]);

    return [
        'videos'         => $vids->fetchAll(),
        'comments'       => $cmts->fetchAll(),
        'events'         => $evts->fetchAll(),
        'media'          => $meds->fetchAll(),
        'event_comments' => $ecmts->fetchAll(),
        'since'          => $since,
    ];
}

// Получить первого активного администратора сайта
function get_site_admin(): ?array {
    $stmt = db()->prepare("SELECT * FROM users WHERE is_admin = 1 AND is_active = 1 ORDER BY id ASC LIMIT 1");
    $stmt->execute();
    return $stmt->fetch() ?: null;
}

function save_user_theme(int $user_id, string $theme): void {
    $theme = in_array($theme, ['dark', 'light']) ? $theme : 'dark';
    db()->prepare("UPDATE users SET theme=? WHERE id=?")->execute([$theme, $user_id]);
}

function initials(?string $name): string {
    if (!$name) return '?';
    $parts = explode(' ', trim($name));
    $ini = mb_substr($parts[0], 0, 1);
    if (isset($parts[1])) $ini .= mb_substr($parts[1], 0, 1);
    return mb_strtoupper($ini);
}

// ─── CSRF ─────────────────────────────────────────────────────────────────────

function csrf_token(): string {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Возвращает HTML hidden-поле с CSRF-токеном для вставки в формы. */
function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

/** Проверяет CSRF-токен в POST-запросе. При несовпадении — завершает скрипт с 403. */
function csrf_verify(): void {
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals(csrf_token(), $token)) {
        http_response_code(403);
        exit('Запрос отклонён (неверный CSRF-токен).');
    }
}

// ─── Конец CSRF ────────────────────────────────────────────────────────────────

// Remember me — создать токен и поставить cookie
function create_remember_token(int $user_id): void {
    $token   = bin2hex(random_bytes(32)); // 64 символа
    $expires = date('Y-m-d H:i:s', strtotime('+30 days'));
    db()->prepare("INSERT INTO remember_tokens (user_id, token_hash, expires_at) VALUES (?, ?, ?)")
       ->execute([$user_id, hash('sha256', $token), $expires]);
    setcookie('remember_token', $token, [
        'expires'  => strtotime('+30 days'),
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

// Remember me — проверить cookie и авторизовать если токен валидный
function check_remember_token(): bool {
    $token = $_COOKIE['remember_token'] ?? '';
    if (!$token) return false;

    $stmt = db()->prepare("
        SELECT rt.*, u.id as uid, u.is_active
        FROM remember_tokens rt
        JOIN users u ON u.id = rt.user_id
        WHERE rt.token_hash = ? AND rt.expires_at > NOW()
    ");
    $stmt->execute([hash('sha256', $token)]);
    $row = $stmt->fetch();

    if (!$row || !$row['is_active']) return false;

    // Авторизуем
    $_SESSION['user_id'] = $row['uid'];

    // Обновляем срок — скользящие 30 дней
    $new_expires = date('Y-m-d H:i:s', strtotime('+30 days'));
    db()->prepare("UPDATE remember_tokens SET expires_at = ? WHERE token_hash = ?")
       ->execute([$new_expires, hash('sha256', $token)]);
    setcookie('remember_token', $token, [
        'expires'  => strtotime('+30 days'),
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    return true;
}

// Remember me — удалить токен при выходе
function delete_remember_token(): void {
    $token = $_COOKIE['remember_token'] ?? '';
    if ($token) {
        db()->prepare("DELETE FROM remember_tokens WHERE token_hash = ?")->execute([hash('sha256', $token)]);
    }
    setcookie('remember_token', '', [
        'expires'  => time() - 3600,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

// =============================================
//  Telegram
// =============================================

function telegram_enabled(): bool {
    return defined('TELEGRAM_BOT_TOKEN') && TELEGRAM_BOT_TOKEN !== '';
}

// Генерирует токен для deep-link подключения (действует 24 часа)
function telegram_generate_link_token(int $user_id): string {
    $token   = bin2hex(random_bytes(16)); // 32 символа — вписывается в лимит 64
    $expires = date('Y-m-d H:i:s', time() + 86400);
    db()->prepare("UPDATE users SET telegram_link_code=?, telegram_link_expires=? WHERE id=?")
       ->execute([$token, $expires, $user_id]);
    return $token;
}

// Находит пользователя по токену (проверяет срок)
function telegram_user_by_token(string $token): ?array {
    $stmt = db()->prepare("SELECT * FROM users WHERE telegram_link_code=? AND telegram_link_expires > NOW()");
    $stmt->execute([$token]);
    return $stmt->fetch() ?: null;
}

// Сохраняет chat_id и очищает токен
function telegram_connect(int $user_id, int $chat_id): void {
    db()->prepare("UPDATE users SET telegram_chat_id=?, telegram_connected_at=NOW(), telegram_link_code=NULL, telegram_link_expires=NULL WHERE id=?")
       ->execute([$chat_id, $user_id]);
}

// Отключает Telegram от аккаунта
function telegram_disconnect(int $user_id): void {
    db()->prepare("UPDATE users SET telegram_chat_id=NULL, telegram_connected_at=NULL WHERE id=?")
       ->execute([$user_id]);
}

// Отправляет сообщение через Bot API
function telegram_send(int $chat_id, string $text): bool {
    if (!telegram_enabled()) return false;
    $url  = 'https://api.telegram.org/bot' . TELEGRAM_BOT_TOKEN . '/sendMessage';
    $data = http_build_query([
        'chat_id'                  => $chat_id,
        'text'                     => $text,
        'parse_mode'               => 'HTML',
        'disable_web_page_preview' => false,
    ]);
    $ctx = stream_context_create(['http' => [
        'method'  => 'POST',
        'header'  => 'Content-Type: application/x-www-form-urlencoded',
        'content' => $data,
        'timeout' => 5,
    ]]);
    return @file_get_contents($url, false, $ctx) !== false;
}

// Устанавливает вебхук
function telegram_set_webhook(): array {
    if (!telegram_enabled()) return ['ok' => false, 'description' => 'Bot not configured'];
    $webhook_url = rtrim(SITE_URL, '/') . '/telegram_bot.php';
    $url  = 'https://api.telegram.org/bot' . TELEGRAM_BOT_TOKEN . '/setWebhook';
    $data = http_build_query(['url' => $webhook_url]);
    $ctx  = stream_context_create(['http' => [
        'method'  => 'POST',
        'header'  => 'Content-Type: application/x-www-form-urlencoded',
        'content' => $data,
        'timeout' => 10,
    ]]);
    $result = @file_get_contents($url, false, $ctx);
    return $result ? json_decode($result, true) : ['ok' => false, 'description' => 'Request failed'];
}

// Удаляет вебхук
function telegram_delete_webhook(): array {
    if (!telegram_enabled()) return ['ok' => false];
    $url  = 'https://api.telegram.org/bot' . TELEGRAM_BOT_TOKEN . '/deleteWebhook';
    $ctx  = stream_context_create(['http' => ['method' => 'POST', 'timeout' => 10]]);
    $result = @file_get_contents($url, false, $ctx);
    return $result ? json_decode($result, true) : ['ok' => false];
}

// Информация о текущем вебхуке
function telegram_get_webhook_info(): array {
    if (!telegram_enabled()) return ['ok' => false];
    $url    = 'https://api.telegram.org/bot' . TELEGRAM_BOT_TOKEN . '/getWebhookInfo';
    $result = @file_get_contents($url);
    return $result ? json_decode($result, true) : ['ok' => false];
}

// Пользователи с Telegram, имеющие доступ к видео (кроме автора)
function telegram_recipients_for_video(int $video_id, int $author_id): array {
    $stmt = db()->prepare("
        SELECT u.telegram_chat_id
        FROM users u
        WHERE u.telegram_chat_id IS NOT NULL
          AND u.telegram_notify = 1
          AND u.is_active = 1
          AND u.id != :author
          AND (
              EXISTS (SELECT 1 FROM video_access va WHERE va.video_id = :vid  AND va.user_id = 0)
              OR EXISTS (SELECT 1 FROM video_access va WHERE va.video_id = :vid2 AND va.user_id = u.id)
              OR u.id = (SELECT user_id FROM videos WHERE id = :vid3)
          )
    ");
    $stmt->execute([':vid' => $video_id, ':vid2' => $video_id, ':vid3' => $video_id, ':author' => $author_id]);
    return $stmt->fetchAll();
}

// Пользователи с Telegram, имеющие доступ к событию (кроме автора)
function telegram_recipients_for_event(int $event_id, int $author_id): array {
    $stmt = db()->prepare("
        SELECT u.telegram_chat_id
        FROM users u
        WHERE u.telegram_chat_id IS NOT NULL
          AND u.telegram_notify = 1
          AND u.is_active = 1
          AND u.id != :author
          AND (
              EXISTS (SELECT 1 FROM event_access ea WHERE ea.event_id = :eid  AND ea.user_id = 0)
              OR EXISTS (SELECT 1 FROM event_access ea WHERE ea.event_id = :eid2 AND ea.user_id = u.id)
              OR u.id = (SELECT user_id FROM events WHERE id = :eid3)
          )
    ");
    $stmt->execute([':eid' => $event_id, ':eid2' => $event_id, ':eid3' => $event_id, ':author' => $author_id]);
    return $stmt->fetchAll();
}

// Пользователи с Telegram, имеющие доступ к медиа (кроме автора)
function telegram_recipients_for_media(int $media_id, int $author_id): array {
    $stmt = db()->prepare("
        SELECT u.telegram_chat_id
        FROM users u
        WHERE u.telegram_chat_id IS NOT NULL
          AND u.telegram_notify = 1
          AND u.is_active = 1
          AND u.id != :author
          AND (
              EXISTS (SELECT 1 FROM media_access ma WHERE ma.media_id = :mid  AND ma.user_id = 0)
              OR EXISTS (SELECT 1 FROM media_access ma WHERE ma.media_id = :mid2 AND ma.user_id = u.id)
              OR u.id = (SELECT user_id FROM media WHERE id = :mid3)
          )
    ");
    $stmt->execute([':mid' => $media_id, ':mid2' => $media_id, ':mid3' => $media_id, ':author' => $author_id]);
    return $stmt->fetchAll();
}

// Рассылает уведомление о новом видео
function telegram_notify_video(int $video_id, int $author_id): void {
    if (!telegram_enabled()) return;
    $stmt = db()->prepare("SELECT v.*, COALESCE(u.display_name, '?') as author_name FROM videos v LEFT JOIN users u ON u.id = v.user_id WHERE v.id = ?");
    $stmt->execute([$video_id]);
    $video = $stmt->fetch();
    if (!$video) return;
    $recipients = telegram_recipients_for_video($video_id, $author_id);
    if (empty($recipients)) return;

    $text  = "🎬 <b>" . htmlspecialchars($video['title'], ENT_QUOTES) . "</b>\n";
    if ($video['filmed_at']) $text .= "📅 " . date('d.m.Y', strtotime($video['filmed_at'])) . "\n";
    if ($video['location'])  $text .= "📍 " . htmlspecialchars($video['location'], ENT_QUOTES) . "\n";
    $text .= "👤 " . htmlspecialchars($video['author_name'], ENT_QUOTES) . "\n";
    $text .= SITE_URL . "/video.php?id=" . $video_id;

    foreach ($recipients as $r) {
        telegram_send((int)$r['telegram_chat_id'], $text);
    }
}

// Рассылает уведомление о новом событии
function telegram_notify_event(int $event_id, int $author_id): void {
    if (!telegram_enabled()) return;
    $stmt = db()->prepare("SELECT e.*, COALESCE(u.display_name, '?') as author_name FROM events e LEFT JOIN users u ON u.id = e.user_id WHERE e.id = ?");
    $stmt->execute([$event_id]);
    $event = $stmt->fetch();
    if (!$event) return;
    $recipients = telegram_recipients_for_event($event_id, $author_id);
    if (empty($recipients)) return;

    $text  = "📅 <b>" . htmlspecialchars($event['title'], ENT_QUOTES) . "</b>\n";
    if ($event['event_date'])   $text .= "🗓 " . date('d.m.Y', strtotime($event['event_date'])) . "\n";
    if ($event['description'])  $text .= htmlspecialchars(mb_substr($event['description'], 0, 120), ENT_QUOTES) . "\n";
    $text .= "👤 " . htmlspecialchars($event['author_name'], ENT_QUOTES) . "\n";
    $text .= SITE_URL . "/event.php?id=" . $event_id;

    foreach ($recipients as $r) {
        telegram_send((int)$r['telegram_chat_id'], $text);
    }
}

// Рассылает уведомление о новом медиа
function telegram_notify_media(int $media_id, int $event_id, int $author_id): void {
    if (!telegram_enabled()) return;
    $stmt = db()->prepare("
        SELECT m.*, COALESCE(u.display_name, '?') as author_name, e.title as event_title
        FROM media m
        LEFT JOIN users u ON u.id = m.user_id
        LEFT JOIN events e ON e.id = m.event_id
        WHERE m.id = ?
    ");
    $stmt->execute([$media_id]);
    $media = $stmt->fetch();
    if (!$media) return;
    $recipients = telegram_recipients_for_media($media_id, $author_id);
    if (empty($recipients)) return;

    $emoji      = match($media['type']) { 'photo' => '📷', 'album' => '🖼', default => '🔗' };
    $type_label = match($media['type']) { 'photo' => 'Фото', 'album' => 'Альбом', default => 'Ссылка' };
    $title      = $media['title'] ?: $type_label;

    $text  = "$emoji <b>" . htmlspecialchars(mb_substr($title, 0, 80), ENT_QUOTES) . "</b>\n";
    $text .= "🎉 " . htmlspecialchars($media['event_title'], ENT_QUOTES) . "\n";
    $text .= "👤 " . htmlspecialchars($media['author_name'], ENT_QUOTES) . "\n";
    $text .= SITE_URL . "/event.php?id=" . $event_id;

    foreach ($recipients as $r) {
        telegram_send((int)$r['telegram_chat_id'], $text);
    }
}

// Рассылает уведомление о новом комментарии к видео
function telegram_notify_video_comment(int $video_id, int $commenter_id, string $comment_text): void {
    if (!telegram_enabled()) return;
    $stmt = db()->prepare("
        SELECT v.title, COALESCE(u.display_name, '?') as commenter_name
        FROM videos v
        LEFT JOIN users u ON u.id = :uid
        WHERE v.id = :vid
    ");
    $stmt->execute([':vid' => $video_id, ':uid' => $commenter_id]);
    $row = $stmt->fetch();
    if (!$row) return;

    $recipients = telegram_recipients_for_video($video_id, $commenter_id);
    if (empty($recipients)) return;

    $excerpt = mb_substr($comment_text, 0, 120);
    if (mb_strlen($comment_text) > 120) $excerpt .= '…';

    $text  = "💬 <b>" . htmlspecialchars($row['title'], ENT_QUOTES) . "</b>\n";
    $text .= "👤 " . htmlspecialchars($row['commenter_name'], ENT_QUOTES) . "\n";
    $text .= htmlspecialchars($excerpt, ENT_QUOTES) . "\n";
    $text .= SITE_URL . "/video.php?id=" . $video_id . "#comments";

    foreach ($recipients as $r) {
        telegram_send((int)$r['telegram_chat_id'], $text);
    }
}

// Рассылает уведомление о новом комментарии к событию
function telegram_notify_event_comment(int $event_id, int $commenter_id, string $comment_text): void {
    if (!telegram_enabled()) return;
    $stmt = db()->prepare("
        SELECT e.title, COALESCE(u.display_name, '?') as commenter_name
        FROM events e
        LEFT JOIN users u ON u.id = :uid
        WHERE e.id = :eid
    ");
    $stmt->execute([':eid' => $event_id, ':uid' => $commenter_id]);
    $row = $stmt->fetch();
    if (!$row) return;

    $recipients = telegram_recipients_for_event($event_id, $commenter_id);
    if (empty($recipients)) return;

    $excerpt = mb_substr($comment_text, 0, 120);
    if (mb_strlen($comment_text) > 120) $excerpt .= '…';

    $text  = "💬 <b>" . htmlspecialchars($row['title'], ENT_QUOTES) . "</b>\n";
    $text .= "👤 " . htmlspecialchars($row['commenter_name'], ENT_QUOTES) . "\n";
    $text .= htmlspecialchars($excerpt, ENT_QUOTES) . "\n";
    $text .= SITE_URL . "/event.php?id=" . $event_id . "#comments";

    foreach ($recipients as $r) {
        telegram_send((int)$r['telegram_chat_id'], $text);
    }
}
