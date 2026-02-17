# Security Checklist (API)

## Immediate actions

1. Rotate database password (`DB_PASS`).
2. Rotate SMTP password (`SMTP_PASS`).
3. Update server-side `auth/.env` with new values.
4. Restart PHP-FPM / web service.

## Repository hygiene

1. Keep `auth/.env` untracked.
2. Commit only `auth/.env.example`.
3. Keep `auth/vendor/` out of Git; install via Composer.

## Deployment checklist

1. Pull latest `main` on server.
2. Run:

```bash
cd /path/to/api/auth
composer install --no-dev --optimize-autoloader
```

3. Ensure file permissions are correct.
4. Verify health endpoints / auth flow (`login`, `me`, `logout`).

## Optional hardening

1. Enable secret scanning in GitHub.
2. Add branch protection on `main`.
3. Add CI checks (lint/tests/security scan).
