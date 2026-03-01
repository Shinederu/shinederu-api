# Box API

Backend API de ShinedeBox, expose sous:

- `https://api.shinederu.lol/box/`

## Endpoints

- `GET /box/auth.php?action=status`
- `GET /box/auth.php?action=logout`
- `POST /box/upload.php`
- `GET /box/list.php`
- `POST /box/rename.php`
- `POST /box/delete.php`

## Authentification

- Session centralisee via cookie `sid` (domaine `.shinederu.lol`)
- Validation session dans table `sessions`
- Acces metier reserve aux admins:
  - `users.is_admin = 1` ou
  - `users.role = 'admin'`

## Configuration

Par defaut, l'API lit les variables depuis:

1. `API/box/.env` (optionnel, prioritaire)
2. `API/auth/.env` (partage auth/main-site/melodyquest)
3. `API/box/.env.example` (template)

Variables principales:

- `BASE_URL`
- `AUTH_PORTAL_URL`
- `AUTH_API_BASE`
- `DB_*` (ou `MQ_DB_*`)
- `UPLOAD_DIR`
- `MAX_FILE_MB`
- `ALLOWED_EXT`
- `ALLOWED_MIME`

## Verification locale

```bash
Get-ChildItem API/box -Filter *.php | % { php -l $_.FullName }
```
