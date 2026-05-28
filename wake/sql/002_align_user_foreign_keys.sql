-- Wake user reference alignment
-- Target DB: ShinedeCore
-- Aligns user reference column types with users.id and adds foreign keys.

ALTER TABLE wake_devices
  MODIFY created_by_user_id INT DEFAULT NULL;

ALTER TABLE wake_user_permissions
  MODIFY user_id INT NOT NULL,
  MODIFY granted_by_user_id INT DEFAULT NULL;

ALTER TABLE wake_devices
  ADD KEY idx_wake_devices_created_by (created_by_user_id);

ALTER TABLE wake_user_permissions
  ADD KEY idx_wake_permissions_granted_by (granted_by_user_id);

ALTER TABLE wake_devices
  ADD CONSTRAINT fk_wake_devices_created_by
  FOREIGN KEY (created_by_user_id) REFERENCES users(id)
  ON DELETE SET NULL;

ALTER TABLE wake_user_permissions
  ADD CONSTRAINT fk_wake_permissions_user
  FOREIGN KEY (user_id) REFERENCES users(id)
  ON DELETE CASCADE;

ALTER TABLE wake_user_permissions
  ADD CONSTRAINT fk_wake_permissions_granted_by
  FOREIGN KEY (granted_by_user_id) REFERENCES users(id)
  ON DELETE SET NULL;
