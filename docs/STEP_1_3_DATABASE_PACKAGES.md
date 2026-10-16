# Step 1.3 — Database Configuration and Required Packages

## Outcome

The application remains on its existing MySQL database and now has the database/configuration foundation for team-aware permissions and activity logging. Authentication, user management, existing roles, API responses, and all 13 API routes remain unchanged.

No companies, tenant isolation, role assignments, activity recording, or `HasRoles` integration are introduced in this step.

## Database configuration

The active local environment was verified without displaying credentials:

| Setting | Value |
|---|---|
| Driver | MySQL |
| Host | `127.0.0.1` |
| Port | `3306` |
| Database | `construction_management` |
| Connection | Successful |

`.env.example` now documents MySQL as the expected development database. The real `.env` was not changed.

Before migration, the database contained 3 users and 14 personal access tokens. After migration, those counts remained unchanged.

## Installed packages

| Package | Installed version | Composer constraint |
|---|---:|---:|
| `spatie/laravel-permission` | `6.25.0` | `^6.25` |
| `spatie/laravel-activitylog` | `4.12.3` | `^4.12` |
| `spatie/laravel-package-tools` | `1.93.3` | Transitive dependency |

Installation command:

```powershell
composer require spatie/laravel-permission spatie/laravel-activitylog --no-interaction
```

Composer selected these compatible releases because the latest major versions require PHP 8.3 or PHP 8.4, while this project supports PHP 8.2. Composer reported no security vulnerability advisories.

## Published files

Configuration:

- `config/permission.php`
- `config/activitylog.php`

Migrations:

- `2026_10_16_112853_create_permission_tables.php`
- `2026_10_16_112855_create_activity_log_table.php`
- `2026_10_16_112856_add_event_column_to_activity_log_table.php`
- `2026_10_16_112857_add_batch_uuid_column_to_activity_log_table.php`

Publishing commands:

```powershell
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
php artisan vendor:publish --provider="Spatie\Activitylog\ActivitylogServiceProvider" --tag="activitylog-migrations"
php artisan vendor:publish --provider="Spatie\Activitylog\ActivitylogServiceProvider" --tag="activitylog-config"
```

## Permission teams configuration

Teams were enabled before running the published permission migration:

```php
'column_names' => [
    'team_foreign_key' => 'company_id',
],

'teams' => true,
```

The resulting schema has `company_id` on:

- `roles` — nullable, indexed, and included in the role uniqueness constraint
- `model_has_roles` — required, indexed, and included in the composite primary key
- `model_has_permissions` — required, indexed, and included in the composite primary key

There is intentionally no foreign key to `companies` because that table will be introduced in Step 1.4.

The existing `users.role` column remains unchanged, and the User model does not yet use Spatie's `HasRoles` trait.

## New database tables

- `roles`
- `permissions`
- `model_has_roles`
- `model_has_permissions`
- `role_has_permissions`
- `activity_log`

The activity table includes subject and causer polymorphic fields, `event`, JSON `properties`, and `batch_uuid`. No application model currently records activities, so passwords, tokens, and secrets are not logged.

## Migration safety and results

Existing migration history and table-name conflicts were checked first. The SQL was reviewed using:

```powershell
php artisan migrate --pretend
```

The dry run confirmed that the migrations only created package tables and added two columns to the newly created `activity_log` table. They did not modify `users`, `personal_access_tokens`, or other existing application tables.

Applied migrations:

```text
2026_10_16_112853_create_permission_tables                 DONE
2026_10_16_112855_create_activity_log_table                DONE
2026_10_16_112856_add_event_column_to_activity_log_table   DONE
2026_10_16_112857_add_batch_uuid_column_to_activity_log_table DONE
```

For production, take and verify a database backup before applying migrations:

```powershell
php artisan migrate --pretend
php artisan migrate
php artisan migrate:status
```

Never use `migrate:fresh`, `migrate:refresh`, `migrate:reset`, or `db:wipe` on a database containing application data.

## MySQL verification

Confirm the safe environment values without printing credentials:

```powershell
Get-Content .env | Where-Object {
    $_ -match '^(DB_CONNECTION|DB_HOST|DB_PORT|DB_DATABASE)='
}
```

Confirm connectivity and migration history:

```powershell
php artisan migrate:status
```

Inspect individual tables:

```powershell
php artisan db:table users
php artisan db:table personal_access_tokens
php artisan db:table roles
php artisan db:table model_has_roles
php artisan db:table activity_log
```

## Automated verification

Tests use SQLite `:memory:` and do not modify the configured MySQL database:

```powershell
php artisan test
```

Package-focused tests verify:

- Teams are enabled.
- The team key is `company_id`.
- All five permission tables are created.
- Team-aware columns exist on the three required tables.
- `activity_log` and its published columns are created.
- Package migrations do not conflict under SQLite.

Actual result after installation and migration:

```text
Tests: 25 passed (100 assertions)
Failures: 0
```

All package-created and modified PHP files pass Laravel Pint. Composer Audit reported no known security vulnerability advisories.

Other useful checks:

```powershell
php artisan route:list --path=api -v
vendor/bin/pint --test
composer audit
```

## Postman regression testing

1. Start the server with `php artisan serve`.
2. Send `GET http://127.0.0.1:8000/api/v1/health`; expect `200`.
3. Send `POST /api/v1/login` using a valid development account; expect a bearer token.
4. Use that token with `GET /api/v1/me`; expect the authenticated user.
5. For an admin token, call `GET /api/v1/users`; expect `200`.
6. For a supervisor token, call `GET /api/v1/users`; expect `403`.
7. Call `POST /api/v1/logout`, then retry `/api/v1/me`; expect `401`.
8. Confirm representative legacy calls such as `/api/login`, `/api/user`, and `/api/users` retain their existing formats.

Do not delete non-disposable users during manual regression testing. See `docs/STEP_1_2_API_TESTING.md` for complete payloads and PowerShell examples.

## Errors encountered

- The first Composer attempt could not reach Packagist inside the restricted sandbox. It succeeded after approved network access.
- `php artisan db:show --counts` could not query `performance_schema.session_status` in the local XAMPP database installation. Database connectivity, table presence, and preservation counts were verified through Laravel's database connection directly instead.
- Tinker could not write its PsySH history file in the restricted environment. No application failure or database issue was involved.

## Remaining work for Step 1.4

Step 1.4 should implement the Companies table and User relationships. It should:

1. Create `companies` without rebuilding existing tables.
2. Add `users.company_id` through a safe nullable/backfill/finalize sequence.
3. Preserve the three existing users and their credentials.
4. Define Company and User relationships.
5. Decide how existing users are assigned to an initial company.
6. Add foreign keys and indexes only after data is valid.
7. Prepare the tenant context needed by Spatie teams.
8. Add company relationship and migration tests.

Tenant scoping, `BelongsToCompany`, Spatie role assignment, policies, and company-aware activity logging should remain separate integration steps unless Step 1.4 explicitly includes them.
