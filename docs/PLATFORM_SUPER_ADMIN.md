# Platform Super Administrator

Platform administrators are company-independent `users` with the server-owned `is_platform_admin` flag and a null `company_id`. This flag is not mass assignable. `platform.admin` middleware protects every platform API; company roles never satisfy it.

Provision the first administrator interactively after migrations:

```sh
php artisan platform-admin:create --email=operator@example.com --name="Platform Operator"
```

The command refuses to create a second platform administrator, requires explicit operator confirmation, accepts passwords only through hidden prompts, and never changes company users.

Platform users authenticate through the rate-limited `/api/v1/login` endpoint and receive a Sanctum token with the `platform` ability. They are routed to `/platform/dashboard`; tenant users remain in the company workspace. Suspending a company revokes all tokens belonging to its users.

Platform company create, read, update, status, and administrator-management routes require both Sanctum authentication and `platform.admin`. Company administrators receive 403 and anonymous requests receive 401. Public company registration is not routed.

The schema change is additive and reversible. Never use destructive migration commands. Back up the configured database before running `php artisan migrate` in a deployed environment.
