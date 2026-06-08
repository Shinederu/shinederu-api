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

Seed initial:

- projet `core`, role `super_admin`, permission `platform.admin`
- projet `auth`, role `admin`, permission `users.manage`
- projet `main`, role `admin`, permission `announcements.manage`
- projet `melodyquest`, role `catalog_admin`, permission `catalog.manage`
- projet `box`, role `admin`, permission `files.manage`
- projet `wake`, roles `wake` et `manage`, permissions `devices.wake`, `devices.manage`, `users.manage`

La migration assigne automatiquement `core.super_admin` aux utilisateurs dont `users.role = 'admin'`, puis importe les anciens droits `wake_user_permissions` vers les roles centralises Wake quand la table legacy existe.

Administration:

- `GET /auth/?action=listCoreAccess` liste projets, roles, permissions et assignations.
- `PUT /auth/` avec `action=saveCoreProject`, `saveCoreRole`, `saveCorePermission`, `setCoreRolePermissions` ou `setCoreUserProjectRoles` modifie le modele.
- Ces endpoints sont reserves a `core.super_admin`.
- L'interface utilisateur correspondante est dans `Shinederu` sur `/permissions` (tuile Dashboard "Permissions").

Convention backend:

- utiliser `ProjectAccessService::hasPermission($userId, '<project>', '<permission>')`
- ne pas coder de logique metier dependante du libelle d'un role
- conserver `users.role = 'admin'` uniquement comme fallback de transition
