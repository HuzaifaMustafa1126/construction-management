# Platform Company Management

All endpoints require Sanctum authentication plus `platform.admin` middleware.

| Method | Endpoint | Purpose |
|---|---|---|
| GET | `/api/v1/platform/dashboard` | Global, database-backed totals and recent companies |
| GET/POST | `/api/v1/platform/companies` | Search/list or atomically create a company and first administrator |
| GET/PATCH | `/api/v1/platform/companies/{company}` | Read or update permitted company profile fields |
| PATCH | `/api/v1/platform/companies/{company}/status` | Activate or suspend a company |
| PATCH | `/api/v1/platform/companies/{company}/administrators/{user}/status` | Activate, suspend, or deactivate a company administrator; last-active-admin protection applies |

Public `POST /api/v1/register-company` is retired. Creation accepts only company identity fields and first-administrator contact fields. The server owns company IDs, status, onboarding, subscription, roles, and verification state. It creates company roles and the administrator in one transaction, generates a random unusable initial password, then requests Laravel's time-limited password-reset notification. Mail and the application's password-reset URL must be configured in each deployment.

Creation, profile updates, and status changes are written to the activity log. The frontend routes are `/platform/dashboard`, `/platform/companies`, `/platform/companies/new`, `/platform/companies/:id`, and `/platform/companies/:id/edit`.

## Administrator password setup

New administrators are created as inactive with a cryptographically random, undisclosed password. Laravel's configured `users` password broker stores a hashed, single-use token in `password_reset_tokens`; it expires after 60 minutes. The standard reset notification links to `/administrator/password/setup?token=...&email=...` on `FRONTEND_URL`.

`POST /api/v1/administrator/password/setup` accepts `email`, `token`, `password`, and `password_confirmation`. Passwords require 12 characters with mixed case, a number, and a symbol. Invalid, expired, unknown, and reused credentials receive the same response. A successful setup verifies the administrator email, revokes existing tokens, consumes the reset token, and activates the administrator only when its company is active. It never changes company status.

Run backend tests only with the checked-in `phpunit.xml` SQLite in-memory configuration: `php artisan test`. Frontend verification is `npm run lint` and `npm run build`.
