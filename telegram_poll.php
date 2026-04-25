<?php
// =============================================
//  Telegram Polling — опрос обновлений через cron
//
//  Запускать через cron (URL-вариант):
//  curl -s "https://your-domain.com/telegram_poll.php?key=TELEGRAM_POLL_KEY" > /dev/null
//
//  Запускать через cron (CLI-вариант):
//  php /path/to/telegram_poll.php
//
//  Интервал запуска — настраивается в cron (рекомендуется 1-5 минут).
//  Частота не влияет на логику: скрипт всегда забирает ВСЕ накопившиеся обновления.
// =============================================

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

// ---- Защита от несанкционированного запуска ----
$is_cli = (php_sapi_name() === 'cli');
if (!$is_cli) {
    $key = $_GET['key'] ?? '';
    if (!defined('TELEGRAM_POLL_KEY') || TELEGRAM_POLL_KEY === '' || $key !== TELEGRAM_POLL_KEY) {
        http_response_code(403);
        exit('Forbidden');
    }
}

if (!telegram_enabled()) {
    exit('Bot not configured');
}

// ---- Файл для хранения последнего обработанного update_id ----
$offset_file = __DIR__ . '/telegram_offset.txt';
$offset      = file_exists($offset_file) ? (int)file_get_contents($offset_file) : 0;

// ---- Запрашиваем обновления у Telegram ----
$url    = 'https://api.telegram.org/bot' . TELEGRAM_BOT_TOKEN
        . '/getUpdates?offset=' . ($offset + 1) . '&limit=50&timeout=0';
$result = @file_get_contents($url);

if (!$result) {
    exit('Telegram API unavailable');
}

$data = json_decode($result, true);
if (!($data['ok'] ?? false) || empty($data['result'])) {
    exit('No updates');
}

// ---- Обрабатываем каждое обновление ----
foreach ($data['result'] as $update) {
    $update_id = (int)$update['update_id'];
    $message   = $update['message'] ?? null;

    if (!$message) {
        $offset = $update_id;
        continue;
    }

    $chat_id = (int)($message['chat']['id'] ?? 0);
    $text    = trim($message['text'] ?? '');

    if (!$chat_id) {
        $offset = $update_id;
        continue;
    }

    // /start TOKEN — подключение через deep link
    if (preg_match('/^\/start\s+([a-f0-9]{32})$/', $text, $m)) {
        $token = $m[1];
        $user  = telegram_user_by_token($token);
        if ($user) {
            telegram_connect((int)$user['id'], $chat_id);
            telegram_send($chat_id,
                "✅ <b>Аккаунт подключён!</b>\n\n" .
                "Привет, " . htmlspecialchars($user['display_name'], ENT_QUOTES) . "! " .
                "Теперь я буду присылать уведомления о новых видео, событиях и медиа, " .
                "к которым у вас есть доступ.\n\n" .
                "Чтобы отключить — напишите /stop"
            );
        } else {
            telegram_send($chat_id,
                "❌ <b>Ссылка устарела или недействительна.</b>\n\n" .
                "Получите новую ссылку в настройках профиля на сайте."
            );
        }

    // /start без токена
    } elseif ($text === '/start') {
        $stmt = db()->prepare("SELECT display_name FROM users WHERE telegram_chat_id = ?");
        $stmt->execute([$chat_id]);
        $existing = $stmt->fetch();
        if ($existing) {
            telegram_send($chat_id,
                "👋 Вы уже подключены как <b>" . htmlspecialchars($existing['display_name'], ENT_QUOTES) . "</b>.\n\n" .
                "Чтобы отключить уведомления — напишите /stop"
            );
        } else {
            telegram_send($chat_id,
                "👋 Это бот для уведомлений семейного архива <b>" . htmlspecialchars(SITE_NAME, ENT_QUOTES) . "</b>.\n\n" .
                "Для подключения перейдите в профиль на сайте и нажмите «Подключить Telegram»."
            );
        }

    // /stop — отключение
    } elseif ($text === '/stop') {
        $stmt = db()->prepare("SELECT id, display_name FROM users WHERE telegram_chat_id = ?");
        $stmt->execute([$chat_id]);
        $user = $stmt->fetch();
        if ($user) {
            telegram_disconnect((int)$user['id']);
            telegram_send($chat_id,
                "🔕 Уведомления отключены.\n\n" .
                "Чтобы снова подключиться — перейдите в профиль на сайте."
            );
        } else {
            telegram_send($chat_id, "Вы не были подключены.");
        }

    // Любое другое сообщение
    } else {
        telegram_send($chat_id,
            "Доступные команды:\n" .
            "/stop — отключить уведомления"
        );
    }

    $offset = $update_id;
}

// ---- Сохраняем offset чтобы не обрабатывать одно и то же дважды ----
file_put_contents($offset_file, $offset);

exit('OK');
