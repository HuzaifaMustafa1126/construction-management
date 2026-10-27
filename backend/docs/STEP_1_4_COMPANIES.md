# Step 1.4 — Companies Table and User Relationships

## Outcome

The application now has a data-preserving company schema and optional User-to-Company relationship. Existing users remain unassigned until an explicit company mapping is approved. No tenant scope, company API, Spatie role assignment, or public company registration has been introduced.

## Created files

- `app/Models/Company.php` — Company model, status constants, casts, soft deletion, and `users()` relationship
- `app/Console/Commands/AssignUsersToCompany.php` — explicit, dry-run-first backfill command
- `database/factories/CompanyFactory.php` — realistic company data and active, suspended, and trial states
- `database/migrations/2026_10_16_120000_create_companies_table.php`
- `database/migrations/2026_10_16_120100_add_company_id_to_users_table.php`
- `tests/Feature/CompanyTest.php` — schema, model, relationship, deletion, factory, API-safety, and command tests
- `docs/STEP_1_4_COMPANIES.md`

## Modified files

- `app/Models/User.php`
  - Added `company()` relationship.
  - Hid `company_id` from raw API serialization to preserve existing response formats.
  - Did not add `company_id` to `$fillable`, preventing mass assignment through current APIs.

No controller, route, authentication, role, or response implementation was changed.

## Companies schema

| Column | Definition |
|---|---|
| `id` | Unsigned BIGINT primary key |
| `name` | Required VARCHAR(255) |
| `slug` | Required VARCHAR(255), unique |
| `logo` | Nullable VARCHAR(255) |
| `email` | Nullable VARCHAR(255) |
| `phone` | Nullable VARCHAR(30) |
| `address` | Nullable TEXT |
| `city` | Nullable VARCHAR(100) |
| `country` | Nullable VARCHAR(100) |
| `currency` | VARCHAR(10), default `PKR` |
| `timezone` | VARCHAR(100), default `Asia/Karachi` |
| `subscription_plan_id` | Nullable unsigned BIGINT, indexed, no foreign key yet |
| `subscription_status` | VARCHAR(30), default `trial`, indexed |
| `trial_ends_at` | Nullable timestamp |
| `status` | VARCHAR(30), default `active`, indexed |
| timestamps | `created_at`, `updated_at` |
| `deleted_at` | Nullable soft-delete timestamp |

`subscription_plan_id` is reserved for V2. It intentionally has no foreign key because no subscription-plans table exists.

## User schema change

`users.company_id` is:

- Nullable to preserve all existing users and allow future explicitly authorized platform accounts
- An unsigned BIGINT
- Explicitly indexed as `users_company_id_index`
- A foreign key to `companies.id`
- Restricted on hard company deletion while users are assigned

It is intentionally not non-null and no existing record was automatically assigned.

## Eloquent relationships

Company:

```php
public function users(): HasMany
{
    return $this->hasMany(User::class);
}
```

User:

```php
public function company(): BelongsTo
{
    return $this->belongsTo(Company::class);
}
```

No global tenant scope or `BelongsToCompany` trait is active yet.

## Existing-data preservation

Before migration:

```text
Users: 3
Personal access tokens: 14
Companies table: absent
users.company_id: absent
```

After migration:

```text
Users: 3
Personal access tokens: 14
Existing users with company_id NULL: 3
Companies created automatically: 0
```

User IDs, emails, passwords, statuses, roles, and Sanctum tokens were not modified.

The current admin account was not promoted or treated as a platform Super Admin. That decision requires explicit authorization.

## Controlled company backfill

The assignment command requires an existing company plus explicit user IDs. It refuses missing users and users assigned to another company. Re-running the same mapping is safe.

First perform a dry run:

```powershell
php artisan company:assign-users COMPANY_ID USER_ID_1 USER_ID_2
```

Review the company and counts, then explicitly commit:

```powershell
php artisan company:assign-users COMPANY_ID USER_ID_1 USER_ID_2 --commit
```

A company slug may be supplied instead of its ID. The command never selects all unassigned users implicitly.

No production/development company was created in this step. Before using the command, obtain approval for:

1. The company record and its authoritative details
2. Which existing user IDs belong to that company
3. Whether any account is an authorized platform-level Super Admin and should retain `company_id = null`

## Migration verification

The existing history was checked and SQL reviewed first:

```powershell
php artisan migrate:status
php artisan migrate --pretend
```

Only these migrations were applied:

```text
2026_10_16_120000_create_companies_table          DONE
2026_10_16_120100_add_company_id_to_users_table   DONE
```

Take a verified backup before applying equivalent migrations in production.

## Automated tests

Tests use SQLite `:memory:` and do not modify MySQL application data.

Coverage includes:

- Required table and columns
- Explicit `company_id` index
- Company creation and unique slug enforcement
- Company `hasMany` users
- User `belongsTo` company
- Nullable company for existing users
- Soft-deletion preservation of users
- Database restriction of hard deletion with assigned users
- Factory states and unique slugs
- Prevention of `company_id` injection through `POST /api/v1/users`
- Dry-run-first and idempotent company assignment
- Existing Sanctum, user management, versioning, and Spatie configuration regressions

Run:

```powershell
php artisan test
vendor/bin/pint --test
php artisan route:list --path=api -v
```

Actual final result:

```text
Tests: 34 passed (128 assertions)
Failures: 0
```

All Step 1.4 PHP files pass Laravel Pint, and all 13 existing API routes remain registered.

## Verification commands

Inspect migration state and schemas:

```powershell
php artisan migrate:status
php artisan db:table companies
php artisan db:table users
```

Confirm command registration:

```powershell
php artisan list --raw | Select-String 'company:assign-users'
```

Confirm API regression behavior using the examples in `docs/STEP_1_2_API_TESTING.md`.

## Security considerations

- `company_id` is not accepted by existing user creation validation.
- `company_id` is not mass assignable on User.
- `company_id` is hidden from current raw User JSON to prevent an accidental response-contract change.
- Companies use soft deletes, while the database restricts hard deletion when users are assigned.
- A nullable `company_id` must not become an implicit privilege bypass in Step 1.5.
- Only explicitly authorized platform accounts should operate without a company.
- No tenant isolation exists yet; company relationships alone do not prevent cross-company access.
- No company CRUD endpoints exist.

## Preparation for Step 1.5

Step 1.5 should introduce Multi-Tenant Isolation only after the initial company and existing-user mapping decisions are approved. It should cover tenant context, the `BelongsToCompany` trait, company-active middleware, policies, scoped route-model binding, queued-job company context, and cross-tenant isolation tests.
