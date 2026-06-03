# Box API

Backend API de ShinedeBox, expose sous:

- `https://api.shinederu.ch/box/`

L'API gere les fichiers via metadonnees SQL et stockage physique hors webroot. Les telechargements passent par `download.php`; les liens publics passent par des tokens en base.

## Endpoints

- `GET /box/auth.php?action=status`
- `GET /box/auth.php?action=logout`
- `GET /box/list.php`
- `POST /box/upload.php`
- `POST /box/rename.php`
- `POST /box/delete.php`
- `GET /box/share.php?id=<file_id>` : liste des liens d'un fichier, admin requis.
- `POST /box/share.php` : creation ou revocation d'un lien public, admin requis.
- `GET /box/share.php?token=<token>` : lecture publique des infos d'un partage.
- `GET /box/download.php?id=<file_id>` : telechargement admin.
- `GET /box/download.php?token=<token>` : telechargement public.

## Authentification

- Session centralisee via cookie `sid` (domaine `.shinederu.ch`).
- Validation session dans `auth_sessions`.
- Acces metier reserve au droit central `box.files.manage` ou au super-admin global.

## Donnees

Migration principale:

- `sql/001_box_files.sql`

Tables:

- `box_files` : metadonnees fichier, nom public, nom stocke, taille, MIME, checksum, soft delete.
- `box_shares` : tokens publics, expiration optionnelle, limite optionnelle de telechargements.
- `box_download_events` : journal minimal des telechargements avec hashes IP/user-agent.

Les suppressions UI sont des soft deletes (`deleted_at`) et ne suppriment pas immediatement le fichier physique.

## Configuration

Par defaut, l'API lit les variables depuis:

1. `API/box/.env` (optionnel, prioritaire)
2. `API/auth/.env` (fallback credentials partages)
3. `API/box/.env.example` (template)

Variables principales:

- `BASE_URL`
- `BOX_API_BASE`
- `AUTH_PORTAL_URL`
- `AUTH_API_BASE`
- `DB_*` (ou `MQ_DB_*`)
- `UPLOAD_DIR`
- `MAX_FILE_MB`
- `ALLOWED_EXT`
- `BLOCKED_EXT`
- `ALLOWED_MIME`

`UPLOAD_DIR` doit pointer hors dossier public. En production, la valeur attendue est:

```text
/var/www/ShinedeBoxStorage/files
```

## Verification locale

```powershell
Get-ChildItem API/box -Recurse -Filter *.php | % { php -l $_.FullName }
```
