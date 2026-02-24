CREATE TABLE IF NOT EXISTS `main_site_announcements` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(160) NOT NULL,
  `message` TEXT NOT NULL,
  `button_label` VARCHAR(120) DEFAULT NULL,
  `button_link` VARCHAR(1024) DEFAULT NULL,
  `published_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `author_user_id` INT UNSIGNED DEFAULT NULL,
  `updated_by_user_id` INT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_main_site_announcements_published` (`published_at` DESC, `id` DESC),
  KEY `idx_main_site_announcements_author` (`author_user_id`),
  KEY `idx_main_site_announcements_updated_by` (`updated_by_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

