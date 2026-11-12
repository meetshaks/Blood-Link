# BloodLink

BloodLink is a bilingual Bangladesh blood-donor directory planned with plain PHP, MySQL/MariaDB, HTML, CSS, and vanilla JavaScript.

## Current status

Phases 1–15 are complete: the project foundation, configuration, PDO layer, bootstrap, protection rules, database schema, geography importer, shared application core, bilingual UI, validation, CSRF, uploads, rate limiting, donor registration, authentication, donor dashboards, profile management, public search, availability controls, donation history, donation badges, admin CSV donor export, separate admin authentication, admin statistics, donor administration, system settings, change requests, audit logs, responsive polish, async states, toasts, modals, accessibility improvements, automated verification, security checks, performance verification, deployment configuration, backups, maintenance, and handover documentation are available.

## Deployment

See [Deployment Checklist](docs/DEPLOYMENT.md), [Admin Guide](docs/ADMIN_GUIDE.md), and the production configuration template at [config.production.sample.php](app/config/config.production.sample.php).

## PWA support

The app includes a web app manifest, service worker, offline fallback, and a bilingual branded splash shown on the first page load in a tab. The splash respects reduced-motion preferences and does not repeat during internal navigation. iOS home-screen metadata and the app icon are configured; native launch-screen appearance remains platform-controlled and can differ by browser and OS.

For Bengali deployment steps and Service Worker/asset version updates, see [App Update Guide (Bangla)](docs/APP_UPDATE_GUIDE_BN.md).

## Verification

Run the standard Phase 14 checks:

```bash
php tools/run_phase14_tests.php
```

Run Service Worker cache, offline fallback, and update-lifecycle checks:

```bash
node tools/test_service_worker.js
```

Run the temporary 5,000-donor insert check and query verification:

```bash
php tools/run_phase14_tests.php --performance
```

Measure donor search at 1,000, 10,000, and 50,000 temporary matching donors:

```bash
php tools/benchmark_donor_search.php
```

Both tools remove their temporary test records automatically.

## Documentation

- [Technical Requirements](docs/TRD.md)
- [Database Schema](docs/Schema.md)
- [Application Flow](docs/AppFlow.md)
- [UI Specification](docs/UI_Spec.md)
- [Development Plan](docs/Plan.md)

## Geography data

The source file is [Assets/bangladesh-geo.json](Assets/bangladesh-geo.json). Its documented counts are 8 divisions, 64 districts, 495 upazilas, 12 city corporations, 4,540 unions, and 328 pourashavas.

## Planned local setup

1. Install PHP 8.1+, `pdo_mysql`, `mbstring`, `fileinfo`, `gd`, and MySQL/MariaDB.
2. Copy or edit `app/config/config.php` from [app/config/config.sample.php](app/config/config.sample.php).
3. Use `session_secure=false` only for local HTTP; use `true` behind production HTTPS.
4. Verify the foundation:

   ```sh
   php tools/health_check.php
   ```

5. Import the schema:

   ```sh
   mysql -u root -p bloodlink < database/schema.sql
   ```

6. Import geography data:

   ```sh
   php tools/import_geo.php Assets/bangladesh-geo.json
   ```

Production must use HTTPS and `session_secure=true`. The public document root should be `public/`; keep `app/` outside the web root where the hosting setup permits it.

## Phase 2 verification

Run the following after importing the schema and geography data:

```sh
php tools/validate_geo.php
```

Expected counts are 8 divisions, 64 districts, 12 city corporations, 495 upazilas, 4,537 unions, and 328 pourashavas. The importer refuses to run when the divisions table is non-empty.

## First super administrator

After the schema is loaded, create the first administrator from the CLI:

```sh
php tools/create_admin.php "Administrator Name" admin_username "a-strong-password"
```

Do not place real passwords in shell history on production systems. Prefer an interactive secret wrapper or a protected deployment environment.

## Local demo data

To load replaceable demo donors and a demo administrator into the local database:

```sh
php tools/seed_demo_data.php
```

The command prints the demo credentials. These accounts are for local testing only; remove them before production deployment.

## Simple database connection

Pages may use the shared connection file instead of opening a new PDO connection:

```php
require __DIR__ . '/../app/config/database.php';

$stmt = $conn->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$userId]);
$user = $stmt->fetch();
```

The connection file reads credentials from `app/config/config.php`, enables exceptions and associative fetches, disables emulated prepares, and logs detailed connection errors without exposing them to visitors.
