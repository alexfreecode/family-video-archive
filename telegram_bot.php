<?php
// =============================================
//  Telegram Webhook — обработчик команд бота
//  Вызывается автоматически Telegram при каждом
//  сообщении пользователя боту.
// =============================================

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

// Принимаем только POST от Telegram
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

if (!telegram_enabled()) {
    http_response_code(503);
    exit('Bot not configured');
}

$body = file_get_contents('php://input');
$data = json_decode($body, true);

if (!$data || !isset($data['message'])) {
    http_response_code(200);
    exit('OK');
}

$message = $data['message'];
$chat_id = (int)($message['chat']['id'] ?? 0);
$text    = trim($message['text'] ?? '');

if (!$chat_id) {
    http_response_code(200);
    exit('OK');
}

// /start TOKEN — подключение аккаунта через deep link
if (preg_match('/^\/start\s+([a-f0-9]{32})$/', $text, $m)) {
    $token = $m[1];
    $user  = telegram_user_by_token($token);
    if ($user) {
        telegram_connect((int)$user['id'], $chat_id);
        telegram_send($chat_id,
            "✅ <b>Аккаунт подключён!</b>\n\n" .
            "Привет, " . htmlspecialchars($user['display_name'], ENT_QUOTES) . "! " .
            "Теперь я буду присылать уведомления о новых видео, событиях и медиа, к которым у вас есть доступ.\n\n" .
            "Чтобы отключить уведомления — напишите /stop"
        );
    } else {
        telegram_send($chat_id,
            "❌ <b>Ссылка устарела или недействительна.</b>\n\n" .
            "Пожалуйста, получите новую ссылку в настройках профиля на сайте."
        );
    }

// /start без токена
} elseif ($text === '/start') {
    // Проверим — может пользователь уже подключён
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

http_response_code(200);
echo 'OK';
