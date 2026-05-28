-- Auth table prefix migration
-- Target DB: ShinedeCore
-- Renames historical auth tables to the auth_* namespace.
-- Run after deploying API code that uses the new table names.

RENAME TABLE
  sessions TO auth_sessions,
  password_reset_tokens TO auth_password_reset_tokens,
  email_verification_tokens TO auth_email_verification_tokens;

ALTER TABLE auth_sessions
  DROP FOREIGN KEY sessions_ibfk_1;

ALTER TABLE auth_password_reset_tokens
  DROP FOREIGN KEY password_reset_tokens_ibfk_1;

ALTER TABLE auth_email_verification_tokens
  DROP FOREIGN KEY email_verification_tokens_ibfk_1;

ALTER TABLE auth_sessions
  RENAME INDEX user_id TO idx_auth_sessions_user;

ALTER TABLE auth_password_reset_tokens
  RENAME INDEX user_id TO idx_auth_password_reset_tokens_user;

ALTER TABLE auth_email_verification_tokens
  RENAME INDEX user_id TO idx_auth_email_verification_tokens_user;

ALTER TABLE auth_sessions
  ADD CONSTRAINT fk_auth_sessions_user
  FOREIGN KEY (user_id) REFERENCES users(id)
  ON DELETE CASCADE;

ALTER TABLE auth_password_reset_tokens
  ADD CONSTRAINT fk_auth_password_reset_tokens_user
  FOREIGN KEY (user_id) REFERENCES users(id)
  ON DELETE CASCADE;

ALTER TABLE auth_email_verification_tokens
  ADD CONSTRAINT fk_auth_email_verification_tokens_user
  FOREIGN KEY (user_id) REFERENCES users(id)
  ON DELETE CASCADE;
