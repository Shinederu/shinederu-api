-- Main site table prefix migration
-- Target DB: ShinedeCore
-- Renames main-site announcements to the main_* namespace.
-- Run after deploying API code that uses the new table name.

RENAME TABLE
  main_site_announcements TO main_announcements;

ALTER TABLE main_announcements
  MODIFY author_user_id INT DEFAULT NULL,
  MODIFY updated_by_user_id INT DEFAULT NULL;

ALTER TABLE main_announcements
  RENAME INDEX idx_main_site_announcements_published TO idx_main_announcements_published;

ALTER TABLE main_announcements
  RENAME INDEX idx_main_site_announcements_author TO idx_main_announcements_author;

ALTER TABLE main_announcements
  RENAME INDEX idx_main_site_announcements_updated_by TO idx_main_announcements_updated_by;

ALTER TABLE main_announcements
  ADD CONSTRAINT fk_main_announcements_author
  FOREIGN KEY (author_user_id) REFERENCES users(id)
  ON DELETE SET NULL;

ALTER TABLE main_announcements
  ADD CONSTRAINT fk_main_announcements_updated_by
  FOREIGN KEY (updated_by_user_id) REFERENCES users(id)
  ON DELETE SET NULL;
