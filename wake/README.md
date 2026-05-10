# Wake API

Backend Wake-on-LAN dedie a ShinedeWake, expose sous:

- `https://api.shinederu.ch/wake/index.php`

## Objectif

- reutiliser la session `sid` de `shinederu/auth`
- appliquer des permissions specifiques au site via des tables dediees
- lister, ajouter, modifier et supprimer les machines reveillables
- envoyer un Magic Packet WOL depuis une machine situee sur le meme LAN que les cibles

## Endpoints

Public / session-aware:

- `GET ?action=status`

Acces WOL requis:

- `GET ?action=listDevices`
- `POST ?action=wakeDevice`

Droit de gestion requis:

- `POST ?action=createDevice`
- `PUT ?action=updateDevice`
- `DELETE ?action=deleteDevice`
- `GET ?action=listUsers`
- `PUT ?action=updateUserPermissions`

## Tables dediees

La migration `sql/001_wake_core.sql` cree:

- `wake_devices`
- `wake_user_permissions`

Les comptes admin globaux (`users.is_admin = 1` ou `users.role = 'admin'`) disposent d'un acces complet implicite.
Pour les autres comptes, les droits sont portes par `wake_user_permissions`.

Niveaux de permission utilises par le panel:

- `none`: aucun acces
- `wake`: acces au panel + envoi de magic packet
- `manage`: acces WOL + gestion des machines + gestion des utilisateurs

Exemple d'autorisation explicite pour un compte non-admin:

```sql
INSERT INTO wake_user_permissions (user_id, can_wake, can_manage, granted_by_user_id)
VALUES (42, 1, 0, 1)
ON DUPLICATE KEY UPDATE
  can_wake = VALUES(can_wake),
  can_manage = VALUES(can_manage),
  granted_by_user_id = VALUES(granted_by_user_id);
```

## Configuration

Ordre de lecture des variables:

1. `API/wake/.env`
2. `API/auth/.env`
3. `API/wake/.env.example`

Variables principales:

- `AUTH_API_BASE`
- `WAKE_DEFAULT_BROADCAST`
- `WAKE_DEFAULT_PORT`
- `WAKE_LOG_ENABLED`
- `WAKE_LOG_FILE`
- `WAKE_PING_ENABLED`
- `WAKE_PING_TIMEOUT_SECONDS`
- `WAKE_PING_COMMAND`
- `DB_*`

## Logs WOL

Chaque tentative de reveil ecrit un `request_id` et un evenement detaille:

- `wake_attempt_started`
- `wake_packet_sent`
- `wake_attempt_succeeded`
- `wake_attempt_failed`

Par defaut, l'API tente aussi d'ecrire un fichier local:

- `API/wake/logs/wake.log`

Si le dossier n'est pas writable par PHP, les traces restent visibles dans le `error_log` PHP/FPM.

## Format MAC

Les adresses MAC peuvent etre saisies avec `-`, `:` ou sans separateur.
Avant l'envoi, l'API retire tous les separateurs et construit le Magic Packet a partir de 12 caracteres hexadecimaux.

## Verification locale

```bash
Get-ChildItem API/wake -Recurse -Filter *.php | % { php -l $_.FullName }
```
