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
define('DB_HOST',          'localhost');
define('DB_NAME',          'your_db_name');
define('DB_USER',          'your_db_user');
define('DB_PASS',          'your_db_password');
define('SITE_NAME',        'Our Archive');       // Your archive name
define('YOUTUBE_API_KEY',  'your_api_key');      // Optional but recommended
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

## Features

- 🎬 YouTube video catalog (unlisted videos supported)
- 🖼 Photo and album links (Google Photos auto-preview)
- 📅 Events — shared pages combining videos, photos, and links
- 👥 Video participants (archive members + external people)
- 🔔 "What's new" bell — shows additions since last visit
- 💬 Comments on videos and events
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
