CREATE TABLE IF NOT EXISTS `wake_device_components` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `device_id` INT UNSIGNED NOT NULL,
  `component_type` VARCHAR(40) NOT NULL,
  `label` VARCHAR(120) NOT NULL,
  `details` VARCHAR(255) DEFAULT NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_wake_device_components_device` (`device_id`, `sort_order`, `id`),
  KEY `idx_wake_device_components_type` (`component_type`),
  CONSTRAINT `fk_wake_device_components_device`
    FOREIGN KEY (`device_id`) REFERENCES `wake_devices` (`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
