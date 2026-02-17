# Shinederu API

Backends des projets Shinederu et configuration de deploiement API.

## Contenu

- `auth/` : API d'authentification
- `melodyquest/` : endpoints backend MelodyQuest
- `index.html` : page statique de base
- `Nginx Configuration File.txt` : exemple de configuration

## Securite

- Les secrets ne doivent pas etre versionnes.
- Utiliser `auth/.env` local uniquement.
- Exemple de variables fourni dans `auth/.env.example`.

## Setup local (auth)

1. Copier `auth/.env.example` vers `auth/.env`
2. Renseigner les variables DB/SMTP
3. Verifier les permissions d'acces aux fichiers

## Note sur `vendor/`

Le dossier `auth/vendor/` est present dans ce repo pour l'etat actuel du projet.
A terme, il est recommande de basculer vers une installation de dependances reproductible (`composer.json` / `composer.lock`) et de ne plus versionner `vendor/`.
