CREATE TABLE IF NOT EXISTS `wake_devices` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `mac_address` CHAR(17) NOT NULL,
  `target_ip` VARCHAR(45) DEFAULT NULL,
  `broadcast_address` VARCHAR(45) DEFAULT NULL,
  `port` SMALLINT UNSIGNED NOT NULL DEFAULT 9,
  `description` VARCHAR(255) DEFAULT NULL,
  `is_enabled` TINYINT(1) NOT NULL DEFAULT 1,
  `sort_order` INT NOT NULL DEFAULT 0,
  `last_wake_at` DATETIME DEFAULT NULL,
  `created_by_user_id` INT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_wake_devices_mac_address` (`mac_address`),
  KEY `idx_wake_devices_enabled_sort` (`is_enabled`, `sort_order`, `name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `wake_user_permissions` (
  `user_id` INT UNSIGNED NOT NULL,
  `can_wake` TINYINT(1) NOT NULL DEFAULT 1,
  `can_manage` TINYINT(1) NOT NULL DEFAULT 0,
  `granted_by_user_id` INT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`),
  KEY `idx_wake_user_permissions_manage` (`can_manage`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `wake_devices`
  (`name`, `mac_address`, `target_ip`, `broadcast_address`, `port`, `description`, `is_enabled`, `sort_order`)
VALUES
  ('Gooba', '50-EB-F6-B3-5F-BB', '192.168.10.30', '192.168.10.255', 9, 'Machine de reference initiale', 1, 10)
ON DUPLICATE KEY UPDATE
  `target_ip` = VALUES(`target_ip`),
  `broadcast_address` = VALUES(`broadcast_address`),
  `port` = VALUES(`port`),
  `description` = VALUES(`description`),
  `is_enabled` = VALUES(`is_enabled`),
  `sort_order` = VALUES(`sort_order`);
