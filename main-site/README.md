# Main Site API

Backend dedie au site principal `shinederu.ch`.

## Endpoint

- URL cible (Nginx): `https://api.shinederu.ch/main-site/`
- Entry point: `main-site/index.php`

## Base de donnees

- Meme schema partage que `auth` et `melodyquest` (`ShinedeCore`)
- Utilise les variables `DB_*` chargees depuis `API/auth/.env`

## Actions exposees

Public:
- `GET action=listPublicAnnouncements`

Admin (session requise + `users.is_admin = 1` ou `users.role = 'admin'`):
- `GET action=listAnnouncements`
- `POST action=createAnnouncement`
- `PUT action=updateAnnouncement`
- `DELETE action=deleteAnnouncement`

## Migration SQL

Table annonces:
- `sql/001_main_site_announcements.sql`
