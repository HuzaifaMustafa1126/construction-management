# Step 2.5 — Company Registration and SaaS Onboarding

## Overview

This release adds public, backend-only onboarding:

`register company → create first administrator → initialize tenant roles → email verification → activate company → login`

No billing, subscription-plan enforcement, frontend, or platform-superadmin functionality is included.

## API endpoints

| Method | Endpoint | Authentication | Limit | Purpose |
|---|---|---|---|---|
| POST | `/api/v1/register-company` | Public | 5/IP/hour | Register a company and first administrator |
| GET | `/api/v1/verify-email/{id}/{hash}` | Signed URL | 10/IP/minute | Verify the administrator and activate the company |
| POST | `/api/v1/resend-verification` | Public | 3/IP/minute | Resend without revealing whether an account exists |
| POST | `/api/v1/login` | Public | Existing limit | Login after activation |

An onboarding-status endpoint was intentionally deferred: pending users have no token, and a public lookup would create an account-enumeration risk.

## Registration

Request:

```json
{
  "company": {
    "name": "ABC Construction",
    "email": "info@abcconstruction.com",
    "phone": "+923001234567",
    "address": "Lahore, Pakistan"
  },
  "admin": {
    "first_name": "Ali",
    "last_name": "Ahmed",
    "email": "ali@abcconstruction.com",
    "phone": "+923009876543",
    "password": "StrongPassword123!",
    "password_confirmation": "StrongPassword123!"
  }
}
```

Success (`201`):

```json
{
  "message": "Company registered successfully. Please verify your email.",
  "data": {
    "company": {"id": 3, "name": "ABC Construction", "status": "pending_verification"},
    "admin": {"id": 12, "first_name": "Ali", "last_name": "Ahmed", "email": "ali@abcconstruction.com", "email_verified": false},
    "onboarding_status": "pending_verification"
  }
}
```

Emails are trimmed and lowercased. Company and administrator creation, tenant role initialization, assignments, and activity records share one database transaction. The verification notification is sent only after that transaction commits. No Sanctum token is returned.

Passwords require at least 12 characters, upper and lower case letters, a number, a symbol, and confirmation. Phone numbers accept an optional leading `+`, digits, spaces, and hyphens. Duplicate company and globally unique user emails return `422`.

Client-supplied IDs, company status, onboarding state, role, permissions, administrator status, verification timestamp, and ownership fields are prohibited.

## Tenant initialization

Every registration creates distinct `company_admin`, `site_supervisor`, and `client` role records under the new `company_id`. Permissions come from `PermissionCatalog`; they are not duplicated in an onboarding-specific list. The first user receives the tenant's `company_admin` role. The previous Spatie team context is restored in a `finally` block, including failure paths.

The administrator's `users.company_id` is assigned server-side. Existing last-admin safeguards and tenant middleware therefore apply after activation.

## Verification, activation, and login

Laravel's `MustVerifyEmail` contract and signed, expiring URL are used. Verification derives company ownership from the stored user; callers cannot supply a company ID. A valid link sets `email_verified_at` and changes only a `pending_verification` company with matching onboarding state to `active`/`complete`.

Replaying a link is safe and never reactivates a suspended company. Invalid, modified, expired, or wrong-hash links return `403`. Resend always returns the same `200` message for known and unknown addresses.

New companies have `email_verification_required = true`. Their unverified administrator receives `403` from login and no token. Legacy companies default to `false`, so pre-existing users with a null verification timestamp are not unexpectedly locked out. Inactive users and non-operational companies retain the existing generic `401` response.

## Database migration

`2026_10_24_120000_add_saas_onboarding_to_companies_table.php` adds:

- `onboarding_status`, default `complete`, indexed;
- `email_verification_required`, default `false`;
- a unique constraint on nullable `companies.email`.

The defaults preserve existing companies. New registrations explicitly use pending states and require verification. No tables or existing rows are deleted.

## Activity records

The `company_onboarding` log records company registration, first administrator creation, initial role assignment, administrator verification, and company activation. Properties contain company ID, administrator ID, event time, and no passwords, tokens, or raw verification credentials.

## Mail configuration

Delivery uses Laravel's configured mailer. Configure standard `MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_ENCRYPTION`, `MAIL_FROM_ADDRESS`, and `MAIL_FROM_NAME` values as appropriate. Credentials must remain outside source control. Automated tests use the array mailer and fake notifications. Local `log` delivery proves generation only, not real inbox delivery.

## Postman guide

1. Set `base_url` (for example `http://localhost/construction-management/public`).
2. Send `POST {{base_url}}/api/v1/register-company` with the JSON request above. Confirm `201`, pending statuses, and the absence of `token`.
3. Obtain the verification link from the configured local mail sink or Laravel log. Send the exact signed URL with `GET`; confirm `200`.
4. Send the same URL again; confirm `200` and that a suspended company is not reactivated.
5. Send `POST {{base_url}}/api/v1/resend-verification` with `{"email":"ali@abcconstruction.com"}`. Unknown valid emails return the same message.
6. Before verification, call `POST {{base_url}}/api/v1/login`; confirm `403` and no token. After verification, repeat with the administrator credentials and save `token`.
7. Send `GET {{base_url}}/api/v1/company` with `Authorization: Bearer {{token}}`; confirm it returns only the registered company.
8. Negative checks: alter a signed URL, reuse company/admin emails, inject `company.status` or `admin.company_id`, submit a weak password, and exceed each rate limit.

## Testing and limitations

Tests run with `APP_ENV=testing`, in-memory SQLite, the array mailer, and an array cache. Coverage includes transaction rollback, validation, protected fields, uniqueness, hashing, role/permission initialization, team cleanup, notification generation, signed-link failures and replay, activation, login, resend behavior, throttling, and access to an existing protected company endpoint.

Real email delivery is not claimed unless the selected external mail transport is separately tested. Application-level duplicate validation is backed by database uniqueness, but exact concurrent-write behavior still depends on the deployed MySQL/MariaDB engine and transaction configuration.
