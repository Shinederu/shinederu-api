-- User account moderation fields.
-- Target DB: ShinedeCore
-- Idempotent migration: adds ban/block metadata to the central users table.

SET @schema_name = DATABASE();

SET @sql = (
  SELECT IF(
    EXISTS (
      SELECT 1
      FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = @schema_name
        AND TABLE_NAME = 'users'
        AND COLUMN_NAME = 'is_banned'
    ),
    'SELECT 1',
    'ALTER TABLE users ADD COLUMN is_banned TINYINT(1) NOT NULL DEFAULT 0'
  )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (
  SELECT IF(
    EXISTS (
      SELECT 1
      FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = @schema_name
        AND TABLE_NAME = 'users'
        AND COLUMN_NAME = 'banned_at'
    ),
    'SELECT 1',
    'ALTER TABLE users ADD COLUMN banned_at DATETIME NULL'
  )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (
  SELECT IF(
    EXISTS (
      SELECT 1
      FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = @schema_name
        AND TABLE_NAME = 'users'
        AND COLUMN_NAME = 'banned_by_user_id'
    ),
    'SELECT 1',
    'ALTER TABLE users ADD COLUMN banned_by_user_id INT NULL'
  )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (
  SELECT IF(
    EXISTS (
      SELECT 1
      FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = @schema_name
        AND TABLE_NAME = 'users'
        AND COLUMN_NAME = 'ban_reason'
    ),
    'SELECT 1',
    'ALTER TABLE users ADD COLUMN ban_reason VARCHAR(255) NULL'
  )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (
  SELECT IF(
    EXISTS (
      SELECT 1
      FROM INFORMATION_SCHEMA.STATISTICS
      WHERE TABLE_SCHEMA = @schema_name
        AND TABLE_NAME = 'users'
        AND INDEX_NAME = 'idx_users_is_banned'
    ),
    'SELECT 1',
    'ALTER TABLE users ADD INDEX idx_users_is_banned (is_banned)'
  )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
