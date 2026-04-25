<?php
// =============================================================
//  Скопируй этот файл в config.php и заполни своими данными
//  Copy this file to config.php and fill in your own values
// =============================================================

// ----- База данных / Database --------------------------------
define('DB_HOST',    'localhost');   // Хост БД / DB host
define('DB_NAME',    'your_db_name');   // Имя БД / DB name
define('DB_USER',    'your_db_user');   // Пользователь / DB user
define('DB_PASS',    'your_db_password');   // Пароль / DB password
define('DB_CHARSET', 'utf8mb4');

// ----- Название сайта / Site name ----------------------------
// Отображается в заголовке браузера и на странице входа
// Displayed in browser title and on login page
define('SITE_NAME', 'Our Archive');

// ----- YouTube Data API v3 -----------------------------------
// Получи бесплатный ключ на https://console.cloud.google.com
// Get a free key at https://console.cloud.google.com
// Нужен для автозаполнения названия и описания при добавлении видео
// Required for auto-filling title and description when adding videos
define('YOUTUBE_API_KEY', 'your_youtube_api_key_here');

// ----- URL сайта / Site URL ----------------------------------
// Без слэша в конце / Without trailing slash
define('SITE_URL', 'https://your-domain.com');

// ----- Секретный ключ / Secret key ---------------------------
// Любая случайная строка / Any random string
define('SECRET_KEY', 'change-this-to-random-string-xyz123');

// ----- Telegram-бот (необязательно) / Telegram bot (optional) ----
// Создайте бота через @BotFather в Telegram
// Create a bot via @BotFather in Telegram
// Если оставить пустым — функция уведомлений отключена
// If left empty — notification feature is disabled
define('TELEGRAM_BOT_TOKEN',    '');   // Токен от @BotFather / Token from @BotFather
define('TELEGRAM_BOT_USERNAME', '');   // Без @ / Without @, e.g.: myfamilybot

// Режим работы бота / Bot mode
// 'webhook' — Telegram сам шлёт запросы на сервер (требует открытого порта)
// 'poll'    — сервер сам опрашивает Telegram через cron (рекомендуется для shared хостинга)
// 'webhook' — Telegram pushes updates to your server (requires accessible endpoint)
// 'poll'    — server polls Telegram via cron (recommended for shared hosting)
define('TELEGRAM_MODE', 'poll');

// Секретный ключ для запуска poll-скрипта через URL (cron)
// Secret key to trigger the poll script via URL (cron)
// Любая случайная строка / Any random string
define('TELEGRAM_POLL_KEY', 'change-this-to-random-string');
