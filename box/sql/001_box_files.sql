CREATE TABLE IF NOT EXISTS `box_files` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `public_id` CHAR(26) NOT NULL,
  `owner_user_id` INT DEFAULT NULL,
  `original_name` VARCHAR(255) NOT NULL,
  `display_name` VARCHAR(255) NOT NULL,
  `stored_name` VARCHAR(160) NOT NULL,
  `extension` VARCHAR(32) NOT NULL,
  `mime_type` VARCHAR(160) NOT NULL DEFAULT 'application/octet-stream',
  `size_bytes` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `checksum_sha256` CHAR(64) DEFAULT NULL,
  `storage_path` VARCHAR(512) NOT NULL,
  `description` VARCHAR(500) DEFAULT NULL,
  `download_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_box_files_public_id` (`public_id`),
  UNIQUE KEY `uq_box_files_stored_name` (`stored_name`),
  KEY `idx_box_files_owner` (`owner_user_id`),
  KEY `idx_box_files_deleted_created` (`deleted_at`, `created_at`),
  KEY `idx_box_files_display_name` (`display_name`),
  CONSTRAINT `fk_box_files_owner`
    FOREIGN KEY (`owner_user_id`) REFERENCES `users` (`id`)
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `box_shares` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `file_id` BIGINT UNSIGNED NOT NULL,
  `token` CHAR(40) NOT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `expires_at` DATETIME DEFAULT NULL,
  `max_downloads` INT UNSIGNED DEFAULT NULL,
  `download_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `created_by_user_id` INT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_box_shares_token` (`token`),
  KEY `idx_box_shares_file_active` (`file_id`, `is_active`),
  KEY `idx_box_shares_created_by` (`created_by_user_id`),
  CONSTRAINT `fk_box_shares_file`
    FOREIGN KEY (`file_id`) REFERENCES `box_files` (`id`)
    ON DELETE CASCADE,
  CONSTRAINT `fk_box_shares_created_by`
    FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`)
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `box_download_events` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `file_id` BIGINT UNSIGNED NOT NULL,
  `share_id` BIGINT UNSIGNED DEFAULT NULL,
  `user_id` INT DEFAULT NULL,
  `ip_hash` CHAR(64) DEFAULT NULL,
  `user_agent_hash` CHAR(64) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_box_download_events_file` (`file_id`, `created_at`),
  KEY `idx_box_download_events_share` (`share_id`, `created_at`),
  KEY `idx_box_download_events_user` (`user_id`, `created_at`),
  CONSTRAINT `fk_box_download_events_file`
    FOREIGN KEY (`file_id`) REFERENCES `box_files` (`id`)
    ON DELETE CASCADE,
  CONSTRAINT `fk_box_download_events_share`
    FOREIGN KEY (`share_id`) REFERENCES `box_shares` (`id`)
    ON DELETE SET NULL,
  CONSTRAINT `fk_box_download_events_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
