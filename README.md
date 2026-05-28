# Shinederu API

Backends des projets Shinederu et configuration de deploiement API.

## Contenu

- `auth/` : API d'authentification
- `melodyquest/` : backend MelodyQuest
- `main-site/` : backend du site principal (annonces, contenus dynamiques)
- `box/` : backend ShinedeBox (gestion fichiers admin)
- `wake/` : backend Wake-on-LAN dedie a ShinedeWake
- `index.html` : page statique de base
- `Nginx Configuration File.txt` : exemple de configuration Nginx

## Mapping deploiement (serveur actuel)

- Dossier deploye: `API/`
- Auth: `API/auth/index.php` -> `https://api.shinederu.ch/auth/`
- MelodyQuest: `API/melodyquest/index.php` -> `https://api.shinederu.ch/melodyquest/`
- Main site: `API/main-site/index.php` -> `https://api.shinederu.ch/main-site/`
- Box: `API/box/*.php` -> `https://api.shinederu.ch/box/`
- Wake: `API/wake/index.php` -> `https://api.shinederu.ch/wake/`

## CORS et domaines

- La configuration Nginx de l'API doit autoriser `https://shinederu.ch` et ses sous-domaines `*.shinederu.ch`.
- L'ancien domaine `*.shinederu.lol` ne doit plus etre utilise dans les regles CORS.

## Base de donnees partagee

Les projets utilisent la meme instance MySQL et le meme schema partage:

- `auth/` -> schema partage (credentials `DB_*`)
- `melodyquest/` -> schema partage (credentials `MQ_DB_*`)
- `main-site/` -> schema partage (credentials `DB_*`)
- `box/` -> schema partage (credentials `DB_*` ou `MQ_DB_*`)
- `wake/` -> schema partage (credentials `DB_*` ou `MQ_DB_*`)

Les identifiants techniques peuvent differer (`DB_*` / `MQ_DB_*`), mais pointent vers le meme schema.

Convention de nommage:

- `users` reste la table centrale partagee.
- Les droits applicatifs centralises utilisent les tables `core_*`.
- Les tables Auth utilisent le prefixe `auth_` (`auth_sessions`, `auth_password_reset_tokens`, `auth_email_verification_tokens`).
- Les tables du site principal utilisent le prefixe `main_` (`main_announcements`).
- MelodyQuest conserve le prefixe `mq_`.
- Wake conserve le prefixe `wake_`.

`auth?action=me` expose aussi `user.project_access` pour les droits projet courants, en gardant `user.is_admin` comme indicateur super-admin compatible avec les anciens frontends.
Les endpoints d'administration `core_*` passent par `auth` et exigent `core.super_admin`.

Migrations de nommage/alignment:

- `core/sql/001_core_project_access.sql`
- `auth/sql/001_auth_prefix_tables.sql`
- `main-site/sql/002_rename_main_announcements.sql`
- `wake/sql/002_align_user_foreign_keys.sql`

## MelodyQuest (blindtest)

- Auth partagee via `auth/` (session commune domaine/sous-domaines)
- Frontend sans framework (JS/CSS/HTML)
- Lobbies configurables par leur createur
- Catalogue YouTube structure par categories/familles
- Admin catalogue via role utilisateur en DB (`users.role`)
- Backend PHP structure inspiree de `auth/`:
  - `melodyquest/controllers`
  - `melodyquest/services`
  - `melodyquest/middlewares`
  - `melodyquest/utils`
  - `melodyquest/config`

Migration schema MelodyQuest:

- `melodyquest/sql/001_melodyquest_core.sql`

## Securite

- Les secrets ne doivent jamais etre versionnes.
- Le fichier local utilise est `auth/.env` (ignore par Git).
- Le template a versionner est `auth/.env.example`.

## Gestion des dependances (auth)

`auth/vendor/` n'est plus versionne.

Fichier de dependances:

- `auth/composer.json`

Installation locale (sur machine avec Composer):

```bash
cd auth
composer install --no-dev --optimize-autoloader
```

## Setup local rapide

1. Copier `auth/.env.example` vers `auth/.env`
2. Renseigner DB/SMTP
3. Installer les dependances Composer
4. Verifier les permissions fichiers

## MelodyQuest backend (env)

Le backend `melodyquest/` lit ses credentials DB via variables d'environnement:

- `MQ_DB_TYPE`
- `MQ_DB_HOST`
- `MQ_DB_NAME`
- `MQ_DB_USER`
- `MQ_DB_PASS`
- `MQ_DB_PORT`

Template fourni:

- `melodyquest/.env.example`

## Rotation credentials (obligatoire)

Les anciens secrets ont ete presents dans l'historique avant hardening. Il faut:

1. Regenerer `DB_PASS`
2. Regenerer `SMTP_PASS`
3. Redemarrer les services dependants
4. Mettre a jour `auth/.env` sur le serveur

Voir `SECURITY_CHECKLIST.md` pour la procedure detaillee.
