# Family Video Archive

A private family web archive for storing links to YouTube videos, photos, and Google Photos albums. Each member can add their own media and control who can see it.

## Requirements

- PHP 7.4+ with extensions: `pdo_mysql`, `gd`, `exif`
- MySQL 5.7+ / MariaDB 10.3+
- Shared hosting or any web server (no composer, no Docker needed)

## Installation

### 1. Upload files

Upload all files to your web server (e.g. via FTP or Git).

### 2. Create the database

Create a MySQL database in your hosting control panel.

### 3. Configure

Copy `config.example.php` to `config.php` and fill in your values:

```php
define('DB_HOST',     'localhost');
define('DB_NAME',     'your_db_name');
define('DB_USER',     'your_db_user');
define('DB_PASS',     'your_db_password');
define('SITE_NAME',   'Our Archive');           // Your archive name
define('SITE_URL',    'https://your-domain.com'); // No trailing slash
define('SECRET_KEY',  'random-string-here');    // Any random string
define('YOUTUBE_API_KEY', 'your_api_key');      // Optional, see below

// Telegram bot (optional — leave empty to disable)
define('TELEGRAM_BOT_TOKEN',    '');
define('TELEGRAM_BOT_USERNAME', '');
define('TELEGRAM_MODE',         'poll');         // 'poll' recommended for shared hosting
define('TELEGRAM_POLL_KEY',     'random-string-here');
```

**YouTube API key** (optional): get a free key at [Google Cloud Console](https://console.cloud.google.com). Enables auto-filling video title and description when adding videos.

### 4. Set folder permissions

Make sure `uploads/thumbnails/` is writable by the web server (chmod 755).

### 5. Run the installer

Open `https://your-site.com/install.php` in your browser. The installer will:
- Check the environment
- Create all database tables
- Create the first administrator account

After successful installation you can delete `install.php` from the server (the installer will offer to do this automatically).

### 6. Invite family members

Log in as administrator → go to **Family management** → create invite codes and send them to family members. Each member registers using their 6-digit code.

### 7. Set up Telegram bot (optional)

To enable instant Telegram notifications when new videos, events, media or comments are added:

1. Create a bot via [@BotFather](https://t.me/BotFather) in Telegram (`/newbot`)
2. Copy the token and bot username into `config.php`
3. Set `TELEGRAM_MODE` to `'poll'` (recommended for shared hosting) and set a random `TELEGRAM_POLL_KEY`
4. Add a cron job in your hosting control panel to run every 1–5 minutes:
   ```
   https://your-domain.com/telegram_poll.php?key=YOUR_TELEGRAM_POLL_KEY
   ```
   On LH.pl: Hosting panel → Cron jobs → add URL, interval e.g. `*/5 * * * *`
5. Each family member connects their Telegram account in their **Profile** page → **Connect Telegram**

> **Note:** Notifications are sent instantly when content is created — the cron job is only needed to process bot commands (/start, /stop) from users.

## Features

- 🎬 YouTube video catalog (unlisted videos supported)
- 🖼 Photo and album links (Google Photos auto-preview)
- 📅 Events — shared pages combining videos, photos, and links
- 👥 Video participants (archive members + external people)
- 🔔 "What's new" bell — shows additions since last visit
- 💬 Comments on videos and events
- 🤖 Telegram bot — instant notifications for new content (optional)
- 🌍 Interface languages: English, German, Polish, Portuguese, Russian, Spanish, Ukrainian
- 🌗 Dark / light theme
- 🔒 Invite-only registration, access control per video/event/media
- 📱 Mobile-friendly (Bootstrap 5)

## File structure

```
config.php              — DB credentials and site settings (not in git)
config.example.php      — config template
db.php                  — DB connection and all data functions
layout.php              — shared template, CSS, navigation
install.php             — first-run installer (delete after setup)
schema.sql              — full database schema
lang/                   — translation files (ru, en, uk, pl, es, pt, de)
uploads/thumbnails/     — generated preview images (not in git)
```

## Tech stack

- PHP + MySQL (PDO), no frameworks
- Bootstrap 5
- No composer required
