# Core API Shared Database Layer

This folder contains shared backend pieces used by several API modules.

## Project access

Project rights are centralized in `core_*` tables:

- `core_projects`
- `core_project_roles`
- `core_project_permissions`
- `core_project_role_permissions`
- `core_user_project_roles`

`users` remains the central user table. The legacy `users.role = 'admin'` flag is still accepted as a fallback super-admin during the transition, but new project-specific rights should be stored through `core_user_project_roles`.

Migration:

- `sql/001_core_project_access.sql`

