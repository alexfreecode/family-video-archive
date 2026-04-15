-- =============================================
--  Наш архив — схема базы данных
--  Актуально на: апрель 2026
-- =============================================

-- Миграция: добавить поле language (если обновляетесь с предыдущей версии)
-- ALTER TABLE users ADD COLUMN language VARCHAR(5) NOT NULL DEFAULT 'ru' AFTER theme;
--
-- Для новой установки поле уже включено в CREATE TABLE ниже.

CREATE TABLE IF NOT EXISTS `users` (
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
  `last_seen`     datetime     DEFAULT NULL,
  `prev_seen`     datetime     DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


CREATE TABLE IF NOT EXISTS `videos` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


CREATE TABLE IF NOT EXISTS `video_access` (
  `video_id` int(11) NOT NULL,
  `user_id`  int(11) NOT NULL,
  PRIMARY KEY (`video_id`, `user_id`),
  CONSTRAINT `video_access_ibfk_1` FOREIGN KEY (`video_id`) REFERENCES `videos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


CREATE TABLE IF NOT EXISTS `video_participants` (
  `id`       int(11)      NOT NULL AUTO_INCREMENT,
  `video_id` int(11)      NOT NULL,
  `user_id`  int(11)      DEFAULT NULL,
  `name`     varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `video_id` (`video_id`),
  CONSTRAINT `vp_ibfk_1` FOREIGN KEY (`video_id`) REFERENCES `videos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


CREATE TABLE IF NOT EXISTS `events` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


CREATE TABLE IF NOT EXISTS `event_access` (
  `event_id` int(11) NOT NULL,
  `user_id`  int(11) NOT NULL,
  PRIMARY KEY (`event_id`, `user_id`),
  CONSTRAINT `ea_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


CREATE TABLE IF NOT EXISTS `media` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


CREATE TABLE IF NOT EXISTS `media_access` (
  `media_id` int(11) NOT NULL,
  `user_id`  int(11) NOT NULL,
  PRIMARY KEY (`media_id`, `user_id`),
  CONSTRAINT `ma_ibfk_1` FOREIGN KEY (`media_id`) REFERENCES `media` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


CREATE TABLE IF NOT EXISTS `event_comments` (
  `id`         int(11)  NOT NULL AUTO_INCREMENT,
  `event_id`   int(11)  NOT NULL,
  `user_id`    int(11)  NOT NULL,
  `text`       text     NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `event_id` (`event_id`),
  CONSTRAINT `ec_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ec_ibfk_2` FOREIGN KEY (`user_id`)  REFERENCES `users`  (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


CREATE TABLE IF NOT EXISTS `invite_codes` (
  `id`         int(11)     NOT NULL AUTO_INCREMENT,
  `code`       varchar(10) NOT NULL,
  `color`      varchar(7)  NOT NULL DEFAULT '#c9a84c',
  `used`       tinyint(1)  NOT NULL DEFAULT 0,
  `created_at` datetime    NOT NULL DEFAULT current_timestamp(),
  `expires_at` datetime    NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


CREATE TABLE IF NOT EXISTS `access_notifications` (
  `id`          int(11)  NOT NULL AUTO_INCREMENT,
  `user_id`     int(11)  NOT NULL,
  `new_user_id` int(11)  NOT NULL,
  `created_at`  datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_new_user` (`user_id`, `new_user_id`),
  KEY `an_ibfk_2` (`new_user_id`),
  CONSTRAINT `an_ibfk_1` FOREIGN KEY (`user_id`)     REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `an_ibfk_2` FOREIGN KEY (`new_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


CREATE TABLE IF NOT EXISTS `comments` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


CREATE TABLE IF NOT EXISTS `remember_tokens` (
  `id`         int(11)      NOT NULL AUTO_INCREMENT,
  `user_id`    int(11)      NOT NULL,
  `token_hash` varchar(64)  NOT NULL,
  `expires_at` datetime     NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `token_hash` (`token_hash`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `rt_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- =============================================
--  Миграции — история изменений БД
-- =============================================

-- [ВЫПОЛНЕНО] Светлая тема:
-- ALTER TABLE `users` ADD COLUMN `theme` varchar(10) NOT NULL DEFAULT 'dark';

-- [ВЫПОЛНЕНО] Метаданные видео:
-- ALTER TABLE `videos` ADD COLUMN `filmed_at` date DEFAULT NULL;
-- ALTER TABLE `videos` ADD COLUMN `location` varchar(255) DEFAULT NULL;
-- ALTER TABLE `videos` ADD COLUMN `event` varchar(255) DEFAULT NULL;
-- ALTER TABLE `videos` ADD COLUMN `tags` varchar(500) DEFAULT NULL;

-- [ВЫПОЛНЕНО] Участники видео:
-- CREATE TABLE IF NOT EXISTS `video_participants` ( ... )

-- [ВЫПОЛНЕНО] Инвайт-коды и сброс пароля:
-- CREATE TABLE IF NOT EXISTS `invite_codes` ( ... )
-- ALTER TABLE `users` ADD COLUMN `reset_code` varchar(10) DEFAULT NULL;
-- ALTER TABLE `users` ADD COLUMN `reset_expires` datetime DEFAULT NULL;

-- [ВЫПОЛНЕНО] Уведомления при добавлении пользователя:
-- CREATE TABLE IF NOT EXISTS `access_notifications` ( ... )

-- [ВЫПОЛНЕНО] Деактивация пользователей:
-- ALTER TABLE `users` ADD COLUMN `is_active` tinyint(1) NOT NULL DEFAULT 1;

-- [ВЫПОЛНЕНО] Remember me:
-- CREATE TABLE IF NOT EXISTS `remember_tokens` ( ... )
-- ALTER TABLE `remember_tokens` ADD UNIQUE KEY `token_hash` (`token_hash`);

-- [ВЫПОЛНЕНО] Комментарии к видео:
-- CREATE TABLE IF NOT EXISTS `comments` ( ... )

-- [ВЫПОЛНЕНО] Даты посещения:
-- ALTER TABLE `users` ADD COLUMN `last_seen` datetime DEFAULT NULL;
-- ALTER TABLE `users` ADD COLUMN `prev_seen` datetime DEFAULT NULL;

-- [ВЫПОЛНЕНО] Система событий:
-- CREATE TABLE IF NOT EXISTS `events` ( ... )
-- CREATE TABLE IF NOT EXISTS `event_access` ( ... )
-- CREATE TABLE IF NOT EXISTS `media` ( ... )
-- CREATE TABLE IF NOT EXISTS `media_access` ( ... )
-- CREATE TABLE IF NOT EXISTS `event_comments` ( ... )
-- ALTER TABLE `videos` ADD COLUMN `event_id` int(11) DEFAULT NULL;
-- ALTER TABLE `videos` ADD CONSTRAINT `videos_event_fk` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE SET NULL;
