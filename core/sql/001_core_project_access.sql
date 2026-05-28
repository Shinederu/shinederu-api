-- Core project access model
-- Target DB: ShinedeCore
-- Centralizes project-specific roles and permissions.

CREATE TABLE IF NOT EXISTS core_projects (
  id INT NOT NULL AUTO_INCREMENT,
  code VARCHAR(64) NOT NULL,
  name VARCHAR(120) NOT NULL,
  description VARCHAR(255) DEFAULT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_core_projects_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS core_project_roles (
  id INT NOT NULL AUTO_INCREMENT,
  project_id INT NOT NULL,
  role_key VARCHAR(64) NOT NULL,
  label VARCHAR(120) NOT NULL,
  description VARCHAR(255) DEFAULT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_core_project_roles_project_key (project_id, role_key),
  KEY idx_core_project_roles_project (project_id),
  CONSTRAINT fk_core_project_roles_project
    FOREIGN KEY (project_id) REFERENCES core_projects(id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS core_project_permissions (
  id INT NOT NULL AUTO_INCREMENT,
  project_id INT NOT NULL,
  permission_key VARCHAR(96) NOT NULL,
  label VARCHAR(120) NOT NULL,
  description VARCHAR(255) DEFAULT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_core_project_permissions_project_key (project_id, permission_key),
  KEY idx_core_project_permissions_project (project_id),
  CONSTRAINT fk_core_project_permissions_project
    FOREIGN KEY (project_id) REFERENCES core_projects(id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS core_project_role_permissions (
  role_id INT NOT NULL,
  permission_id INT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (role_id, permission_id),
  KEY idx_core_role_permissions_permission (permission_id),
  CONSTRAINT fk_core_role_permissions_role
    FOREIGN KEY (role_id) REFERENCES core_project_roles(id)
    ON DELETE CASCADE,
  CONSTRAINT fk_core_role_permissions_permission
    FOREIGN KEY (permission_id) REFERENCES core_project_permissions(id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS core_user_project_roles (
  user_id INT NOT NULL,
  project_role_id INT NOT NULL,
  granted_by_user_id INT DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, project_role_id),
  KEY idx_core_user_project_roles_role (project_role_id),
  KEY idx_core_user_project_roles_granted_by (granted_by_user_id),
  CONSTRAINT fk_core_user_project_roles_user
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE CASCADE,
  CONSTRAINT fk_core_user_project_roles_role
    FOREIGN KEY (project_role_id) REFERENCES core_project_roles(id)
    ON DELETE CASCADE,
  CONSTRAINT fk_core_user_project_roles_granted_by
    FOREIGN KEY (granted_by_user_id) REFERENCES users(id)
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO core_projects (code, name, description)
VALUES
  ('core', 'Core', 'Shared platform administration'),
  ('auth', 'Auth', 'Authentication and user administration'),
  ('main', 'Main site', 'Main shinederu.ch site'),
  ('melodyquest', 'MelodyQuest', 'MelodyQuest game and catalog'),
  ('box', 'ShinedeBox', 'ShinedeBox file manager'),
  ('wake', 'ShinedeWake', 'Wake-on-LAN panel')
ON DUPLICATE KEY UPDATE
  name = VALUES(name),
  description = VALUES(description),
  is_active = 1;

INSERT INTO core_project_roles (project_id, role_key, label, description, sort_order)
SELECT id, 'super_admin', 'Super admin', 'Full access to every project', 100
FROM core_projects WHERE code = 'core'
ON DUPLICATE KEY UPDATE label = VALUES(label), description = VALUES(description), sort_order = VALUES(sort_order), is_active = 1;

INSERT INTO core_project_roles (project_id, role_key, label, description, sort_order)
SELECT id, 'admin', 'Auth admin', 'Manage users and global authentication settings', 100
FROM core_projects WHERE code = 'auth'
ON DUPLICATE KEY UPDATE label = VALUES(label), description = VALUES(description), sort_order = VALUES(sort_order), is_active = 1;

INSERT INTO core_project_roles (project_id, role_key, label, description, sort_order)
SELECT id, 'admin', 'Main admin', 'Manage main site announcements', 100
FROM core_projects WHERE code = 'main'
ON DUPLICATE KEY UPDATE label = VALUES(label), description = VALUES(description), sort_order = VALUES(sort_order), is_active = 1;

INSERT INTO core_project_roles (project_id, role_key, label, description, sort_order)
SELECT id, 'catalog_admin', 'Catalog admin', 'Manage MelodyQuest catalog and validation', 100
FROM core_projects WHERE code = 'melodyquest'
ON DUPLICATE KEY UPDATE label = VALUES(label), description = VALUES(description), sort_order = VALUES(sort_order), is_active = 1;

INSERT INTO core_project_roles (project_id, role_key, label, description, sort_order)
SELECT id, 'admin', 'Box admin', 'Manage ShinedeBox files', 100
FROM core_projects WHERE code = 'box'
ON DUPLICATE KEY UPDATE label = VALUES(label), description = VALUES(description), sort_order = VALUES(sort_order), is_active = 1;

INSERT INTO core_project_roles (project_id, role_key, label, description, sort_order)
SELECT id, 'wake', 'Wake access', 'Open the Wake panel and send magic packets', 50
FROM core_projects WHERE code = 'wake'
ON DUPLICATE KEY UPDATE label = VALUES(label), description = VALUES(description), sort_order = VALUES(sort_order), is_active = 1;

INSERT INTO core_project_roles (project_id, role_key, label, description, sort_order)
SELECT id, 'manage', 'Wake manager', 'Manage devices and Wake user access', 100
FROM core_projects WHERE code = 'wake'
ON DUPLICATE KEY UPDATE label = VALUES(label), description = VALUES(description), sort_order = VALUES(sort_order), is_active = 1;

INSERT INTO core_project_permissions (project_id, permission_key, label, description)
SELECT id, 'platform.admin', 'Platform admin', 'Bypass project checks as a platform administrator'
FROM core_projects WHERE code = 'core'
ON DUPLICATE KEY UPDATE label = VALUES(label), description = VALUES(description), is_active = 1;

INSERT INTO core_project_permissions (project_id, permission_key, label, description)
SELECT id, 'users.manage', 'Manage users', 'List users and change global user roles'
FROM core_projects WHERE code = 'auth'
ON DUPLICATE KEY UPDATE label = VALUES(label), description = VALUES(description), is_active = 1;

INSERT INTO core_project_permissions (project_id, permission_key, label, description)
SELECT id, 'announcements.manage', 'Manage announcements', 'Create, edit and delete main-site announcements'
FROM core_projects WHERE code = 'main'
ON DUPLICATE KEY UPDATE label = VALUES(label), description = VALUES(description), is_active = 1;

INSERT INTO core_project_permissions (project_id, permission_key, label, description)
SELECT id, 'catalog.manage', 'Manage catalog', 'Create, edit, delete and validate MelodyQuest catalog entries'
FROM core_projects WHERE code = 'melodyquest'
ON DUPLICATE KEY UPDATE label = VALUES(label), description = VALUES(description), is_active = 1;

INSERT INTO core_project_permissions (project_id, permission_key, label, description)
SELECT id, 'files.manage', 'Manage files', 'Upload, list, rename and delete ShinedeBox files'
FROM core_projects WHERE code = 'box'
ON DUPLICATE KEY UPDATE label = VALUES(label), description = VALUES(description), is_active = 1;

INSERT INTO core_project_permissions (project_id, permission_key, label, description)
SELECT id, 'devices.wake', 'Wake devices', 'Send Wake-on-LAN magic packets'
FROM core_projects WHERE code = 'wake'
ON DUPLICATE KEY UPDATE label = VALUES(label), description = VALUES(description), is_active = 1;

INSERT INTO core_project_permissions (project_id, permission_key, label, description)
SELECT id, 'devices.manage', 'Manage devices', 'Create, update and delete Wake devices'
FROM core_projects WHERE code = 'wake'
ON DUPLICATE KEY UPDATE label = VALUES(label), description = VALUES(description), is_active = 1;

INSERT INTO core_project_permissions (project_id, permission_key, label, description)
SELECT id, 'users.manage', 'Manage Wake users', 'Manage Wake-specific user access'
FROM core_projects WHERE code = 'wake'
ON DUPLICATE KEY UPDATE label = VALUES(label), description = VALUES(description), is_active = 1;

INSERT IGNORE INTO core_project_role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM core_project_roles r
JOIN core_projects pr ON pr.id = r.project_id
JOIN core_project_permissions p ON p.project_id = pr.id
WHERE pr.code = 'core'
  AND r.role_key = 'super_admin'
  AND p.permission_key = 'platform.admin';

INSERT IGNORE INTO core_project_role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM core_project_roles r
JOIN core_projects pr ON pr.id = r.project_id
JOIN core_project_permissions p ON p.project_id = pr.id
WHERE pr.code = 'auth'
  AND r.role_key = 'admin'
  AND p.permission_key = 'users.manage';

INSERT IGNORE INTO core_project_role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM core_project_roles r
JOIN core_projects pr ON pr.id = r.project_id
JOIN core_project_permissions p ON p.project_id = pr.id
WHERE pr.code = 'main'
  AND r.role_key = 'admin'
  AND p.permission_key = 'announcements.manage';

INSERT IGNORE INTO core_project_role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM core_project_roles r
JOIN core_projects pr ON pr.id = r.project_id
JOIN core_project_permissions p ON p.project_id = pr.id
WHERE pr.code = 'melodyquest'
  AND r.role_key = 'catalog_admin'
  AND p.permission_key = 'catalog.manage';

INSERT IGNORE INTO core_project_role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM core_project_roles r
JOIN core_projects pr ON pr.id = r.project_id
JOIN core_project_permissions p ON p.project_id = pr.id
WHERE pr.code = 'box'
  AND r.role_key = 'admin'
  AND p.permission_key = 'files.manage';

INSERT IGNORE INTO core_project_role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM core_project_roles r
JOIN core_projects pr ON pr.id = r.project_id
JOIN core_project_permissions p ON p.project_id = pr.id
WHERE pr.code = 'wake'
  AND r.role_key = 'wake'
  AND p.permission_key = 'devices.wake';

INSERT IGNORE INTO core_project_role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM core_project_roles r
JOIN core_projects pr ON pr.id = r.project_id
JOIN core_project_permissions p ON p.project_id = pr.id
WHERE pr.code = 'wake'
  AND r.role_key = 'manage'
  AND p.permission_key IN ('devices.wake', 'devices.manage', 'users.manage');

INSERT IGNORE INTO core_user_project_roles (user_id, project_role_id, granted_by_user_id)
SELECT u.id, r.id, NULL
FROM users u
JOIN core_projects p ON p.code = 'core'
JOIN core_project_roles r ON r.project_id = p.id AND r.role_key = 'super_admin'
WHERE LOWER(COALESCE(u.role, '')) = 'admin';

SET @core_wake_permissions_sql = (
  SELECT IF(
    COUNT(*) = 1,
    'INSERT IGNORE INTO core_user_project_roles (user_id, project_role_id, granted_by_user_id)
     SELECT w.user_id, r.id, w.granted_by_user_id
     FROM wake_user_permissions w
     JOIN core_projects p ON p.code = ''wake''
     JOIN core_project_roles r
       ON r.project_id = p.id
      AND r.role_key = CASE WHEN w.can_manage = 1 THEN ''manage'' ELSE ''wake'' END
     WHERE w.can_wake = 1 OR w.can_manage = 1',
    'SELECT 1'
  )
  FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'wake_user_permissions'
);

PREPARE core_wake_permissions_stmt FROM @core_wake_permissions_sql;
EXECUTE core_wake_permissions_stmt;
DEALLOCATE PREPARE core_wake_permissions_stmt;
SET @core_wake_permissions_sql = NULL;
