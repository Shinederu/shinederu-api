# Main Site API

Backend dedie au site principal `shinederu.ch`.

## Etat de reprise

Le site principal est mis en pause stable depuis le 2026-06-12. La documentation
complete de reprise est dans le repo frontend: `../Shinederu/REPRISE.md`.

Ce module ne porte aujourd'hui que les annonces du site principal. Le reste du
handoff API du site est partage avec `API/auth` (authentification, utilisateurs,
avatars, blocage de comptes) et `API/core` (permissions centralisees).

## Endpoint

- URL cible (Nginx): `https://api.shinederu.ch/main-site/`
- Entry point: `main-site/index.php`

## Base de donnees

- Meme schema partage que `auth` et `melodyquest` (`ShinedeCore`)
- Utilise les variables `DB_*` chargees depuis `API/auth/.env`

## Actions exposees

Public:
- `GET action=listPublicAnnouncements`

Admin (session requise + droit central `main.announcements.manage` ou super-admin global):
- `GET action=listAnnouncements`
- `POST action=createAnnouncement`
- `PUT action=updateAnnouncement`
- `DELETE action=deleteAnnouncement`

## Migration SQL

Table annonces:
- `sql/001_main_site_announcements.sql`
- `sql/002_rename_main_announcements.sql`

Etat cible:
- table `main_announcements`
