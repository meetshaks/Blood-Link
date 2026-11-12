# BloodLink deployment checklist

## Before deployment

- Provision PHP 8.1+ with `pdo_mysql`, `mbstring`, `fileinfo`, and `gd`.
- Provision MySQL/MariaDB with a dedicated application user; never use `root`.
- Copy `app/config/config.production.sample.php` to the private configuration location and set real values outside source control.
- Set `debug=false`, `base_url` to HTTPS, and `session_secure=true`.
- Configure the web server document root to `public/`.
- Enable HTTPS and redirect all HTTP traffic to HTTPS.
- Confirm `app/`, `database/`, `docs/`, and `tools/` are not web-accessible.

## Database deployment

```sh
mysql -u root -p bloodlink < database/schema.sql
php tools/import_geo.php Assets/bangladesh-geo.json
php tools/validate_geo.php
php tools/create_admin.php "Administrator" admin_username
```

Use a protected secret wrapper for the administrator password; do not put it in shell history.

## Permissions

- Application source: read-only for the web-server user.
- `public/uploads/avatars`: writable by the web-server user, not executable.
- `app/logs`: writable only if file logging is enabled.
- Backup directory: outside the web root, mode `0700`, accessible only to the backup account.

## Scheduled maintenance

Run daily:

```cron
15 2 * * * /usr/bin/php /var/www/bloodlink/tools/maintenance.php
45 2 * * * BLOODLINK_DB_NAME=bloodlink BLOODLINK_DB_USER=bloodlink_backup BLOODLINK_BACKUP_DIR=/var/backups/bloodlink /var/www/bloodlink/tools/backup_database.sh
```

Set `MYSQL_PWD` only through a protected service environment or use a client option file with mode `0600`.

## Verification after deployment

```sh
php tools/health_check.php
php tools/run_phase14_tests.php
curl -I https://bloodlink.example.com/
```

Confirm the response includes HTTPS, CSP, `X-Frame-Options`, and `Permissions-Policy` headers. Test registration, login, admin login, donor search, donation history, uploads, and logout manually.

## Backup restore drill

```sh
gzip -dc /var/backups/bloodlink/bloodlink-YYYYMMDDTHHMMSSZ.sql.gz | mysql -u root -p bloodlink_restore
```

Perform a restore test at least monthly and retain encrypted off-host copies according to the hosting provider's retention policy.
