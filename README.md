# Shinederu API

Backends des projets Shinederu et configuration de deploiement API.

## Contenu

- `auth/` : API d'authentification
- `melodyquest/` : backend MelodyQuest
- `index.html` : page statique de base
- `Nginx Configuration File.txt` : exemple de configuration Nginx

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

## Rotation credentials (obligatoire)

Les anciens secrets ont ete presents dans l'historique avant hardening. Il faut:

1. Regenerer `DB_PASS`
2. Regenerer `SMTP_PASS`
3. Redemarrer les services dependants
4. Mettre a jour `auth/.env` sur le serveur

Voir `SECURITY_CHECKLIST.md` pour la procedure detaillee.
