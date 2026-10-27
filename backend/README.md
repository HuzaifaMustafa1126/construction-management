# Construction Management API

A Laravel 12 REST API providing the authentication and user-administration foundation for a construction management system.

> **Current scope:** this repository implements authentication and user management. Projects, sites, tasks, materials, budgets, and reports are not implemented yet.

## Features

- Laravel Sanctum bearer-token authentication
- Login rate limiting (5 attempts per minute)
- Current-user lookup and logout/token revocation
- Administrator-only user listing, creation, and deletion
- `admin`, `supervisor`, and `client` roles
- User phone, department, role, and status fields
- JSON API errors and request validation
- Protection against administrators deleting their own accounts

## Technology stack

| Component | Technology |
|---|---|
| Backend | PHP 8.2+, Laravel 12 |
| Authentication | Laravel Sanctum 4 |
| Database | SQLite by default; MySQL supported |
| Frontend tooling | Vite 7, Tailwind CSS 4, Axios |
| Tests | PHPUnit 11 |
| Formatting | Laravel Pint |

The `/` web route displays Laravel's default welcome page. Implemented business functionality is under `/api`.

## Roles and permissions

| Action | Admin | Supervisor | Client | Guest |
|---|:---:|:---:|:---:|:---:|
| Log in | Yes | Yes | Yes | Yes |
| View own profile / log out | Yes | Yes | Yes | No |
| List and create users | Yes | No | No | No |
| Delete another user | Yes | No | No | No |
| Delete own account | No | No | No | No |

Role checks currently live in `UserController`; there are no separate policies or role middleware yet.

## Requirements

- PHP 8.2 or newer with Laravel's standard extensions
- Composer
- Node.js and npm
- SQLite, or MySQL/MariaDB (for example through XAMPP)

## Installation

### Automated setup

```bash
composer run setup
```

This installs dependencies, creates `.env` if absent, generates the application key, runs migrations, and builds frontend assets.

For the default SQLite configuration, make sure the database file exists before migration:

```powershell
New-Item -ItemType File -Path database/database.sqlite -Force
php artisan migrate
```

### Manual setup

```powershell
composer install
Copy-Item .env.example .env
php artisan key:generate
New-Item -ItemType File -Path database/database.sqlite -Force
php artisan migrate
npm install
npm run build
```

On macOS/Linux, use `cp .env.example .env` and `touch database/database.sqlite` instead.

### MySQL / XAMPP

Create a database such as `construction_management`, then configure `.env`:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=construction_management
DB_USERNAME=root
DB_PASSWORD=
```

Start MySQL in XAMPP and run `php artisan migrate`.

### Create the first administrator

The default seeder creates `test@example.com` with password `password`, but with the default `client` role. It is for development only. Create an administrator with `php artisan tinker`:

```php
App\Models\User::create([
    'name' => 'System Administrator',
    'first_name' => 'System',
    'last_name' => 'Administrator',
    'email' => 'admin@example.com',
    'password' => 'change-this-password',
    'role' => 'admin',
    'status' => 'Active',
]);
```

The model automatically hashes assigned passwords.

## Running the application

Run only the local API server:

```bash
php artisan serve
```

The default URL is `http://127.0.0.1:8000`. The health check is `GET /up`.

Run the server, queue worker, log viewer, and Vite together:

```bash
composer run dev
```

## Authentication

Only `POST /api/login` is public. Send the login token to every other endpoint:

```http
Authorization: Bearer YOUR_TOKEN
Accept: application/json
Content-Type: application/json
```

Tokens have no application-defined expiration. Logout revokes only the token used for that request.

## API reference

| Method | Endpoint | Access | Description |
|---|---|---|---|
| `POST` | `/api/login` | Public | Authenticate and issue a token |
| `GET` | `/api/user` | Authenticated | Return the current user |
| `POST` | `/api/logout` | Authenticated | Revoke the current token |
| `GET` | `/api/users` | Admin | List users, newest first |
| `POST` | `/api/users` | Admin | Create a user |
| `DELETE` | `/api/users/{id}` | Admin | Delete another user |

### Log in

`POST /api/login` (limited to five attempts per minute)

```json
{
  "email": "admin@example.com",
  "password": "change-this-password"
}
```

Success — `200 OK`:

```json
{
  "message": "Login successful",
  "user": {
    "id": 1,
    "name": "System Administrator",
    "email": "admin@example.com",
    "role": "admin",
    "status": "Active"
  },
  "token": "1|example-token"
}
```

Invalid credentials return `401`; invalid input returns `422`; exceeding the rate limit returns `429`.

```bash
curl -X POST http://127.0.0.1:8000/api/login \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@example.com","password":"change-this-password"}'
```

### Get current user

`GET /api/user` returns `200 OK` with:

```json
{
  "user": {
    "id": 1,
    "name": "System Administrator",
    "email": "admin@example.com",
    "role": "admin",
    "status": "Active"
  }
}
```

### Log out

`POST /api/logout` returns `200 OK`:

```json
{"message": "Logout successful"}
```

The bearer token used for logout becomes invalid.

### List users

`GET /api/users` requires an administrator and returns full user records, newest first:

```json
{
  "users": [
    {
      "id": 2,
      "name": "Site Supervisor",
      "first_name": "Site",
      "last_name": "Supervisor",
      "email": "supervisor@example.com",
      "phone": "03000000000",
      "role": "supervisor",
      "status": "Active",
      "department": "Operations",
      "email_verified_at": null,
      "created_at": "2026-10-16T10:10:00.000000Z",
      "updated_at": "2026-10-16T10:10:00.000000Z"
    }
  ]
}
```

### Create user

`POST /api/users` requires an administrator.

| Field | Required | Validation |
|---|:---:|---|
| `first_name` | Yes | String, maximum 100 characters |
| `last_name` | Yes | String, maximum 100 characters |
| `email` | Yes | Valid unique email, maximum 255 characters |
| `phone` | No | String, maximum 30 characters |
| `password` | Yes | Minimum 8 characters |
| `role` | Yes | `admin`, `supervisor`, or `client` |
| `department` | No | String, maximum 100 characters |

```json
{
  "first_name": "Ayesha",
  "last_name": "Khan",
  "email": "ayesha@example.com",
  "phone": "03001234567",
  "password": "secure-password",
  "role": "supervisor",
  "department": "Site Operations"
}
```

Success — `201 Created`:

```json
{
  "message": "User created successfully.",
  "user": {
    "id": 2,
    "first_name": "Ayesha",
    "last_name": "Khan",
    "email": "ayesha@example.com",
    "phone": "03001234567",
    "role": "supervisor",
    "status": "Active",
    "department": "Site Operations"
  }
}
```

The API composes `name`, hashes the password, and sets status to `Active`.

### Delete user

`DELETE /api/users/{id}` requires an administrator. It returns `200` when deleted, `404` when the ID does not exist, or `403` for insufficient permission or attempted self-deletion.

```json
{"message": "User deleted successfully."}
```

## Database

Application tables:

- `users` — credentials, profile, role, status, and department
- `personal_access_tokens` — Sanctum tokens

Framework-support tables include `password_reset_tokens`, `sessions`, `cache`, `cache_locks`, `jobs`, `job_batches`, and `failed_jobs`.

Important `users` fields:

| Column | Notes |
|---|---|
| `id` | Primary key |
| `name` | Full display name |
| `first_name`, `last_name` | Nullable at database level |
| `email` | Unique login email |
| `password` | Hashed and hidden from JSON |
| `phone` | Nullable; maximum 30 characters |
| `role` | Defaults to `client` |
| `status` | Defaults to `Active` |
| `department` | Nullable; maximum 100 characters |
| `email_verified_at` | Nullable timestamp |
| `created_at`, `updated_at` | Laravel timestamps |

## Project structure

```text
app/Http/Controllers/Api/
  AuthController.php       Login, current user, logout
  UserController.php       Admin user operations
app/Models/User.php        User model and Sanctum integration
bootstrap/app.php          Routing and JSON exception handling
database/migrations/       Database schema
database/seeders/          Development seed data
resources/                 Blade, CSS, and JavaScript entry points
routes/api.php             API routes
routes/web.php             Default welcome route
tests/Feature/             API feature tests
```

## Testing and code quality

```bash
composer test
vendor/bin/pint --test
npm run build
php artisan route:list --path=api
```

The API tests cover login, unauthenticated JSON responses, administrator listing and creation, and creation validation.

## Error responses

| Status | Meaning |
|---:|---|
| `200` | Successful request |
| `201` | User created |
| `401` | Invalid credentials or missing/invalid token |
| `403` | Permission denied |
| `404` | User not found |
| `422` | Validation failed |
| `429` | Login rate limit exceeded |
| `500` | Unexpected server error |

Validation failures follow Laravel's JSON format:

```json
{
  "message": "The first name field is required. (and 4 more errors)",
  "errors": {
    "first_name": ["The first name field is required."],
    "email": ["The email field is required."]
  }
}
```

Always send `Accept: application/json`. API exceptions are configured to render as JSON.

## Security and production notes

- Never commit `.env` or production credentials.
- Use HTTPS so bearer tokens are encrypted in transit.
- Replace development credentials before deployment.
- Set `APP_ENV=production` and `APP_DEBUG=false` in production.
- Restrict CORS origins to trusted frontend domains.
- Consider token expiration, account-status enforcement, all-device logout, audit logs, and policies/middleware before production use.
- Currently, a user whose `status` is not `Active` is still allowed to log in.
- Run `php artisan optimize` after configuring production.

## Current limitations and roadmap

This is an authentication and user-management foundation, not yet a complete construction management product. Suggested next modules are:

1. Projects, construction sites, and project membership
2. Tasks, milestones, assignments, and progress tracking
3. Contractors, workers, and attendance
4. Materials, inventory, suppliers, and purchase orders
5. Estimates, budgets, expenses, invoices, and payments
6. Documents, drawings, site photos, and approvals
7. Safety incidents, inspections, and daily site reports
8. Notifications, audit logs, dashboards, and reporting
9. User update, activation/deactivation, password reset, and pagination

New modules should include migrations, models, authorization policies, request validation, API resources, tests, and updated endpoint documentation.

## License

Built on Laravel, which is licensed under the [MIT License](https://opensource.org/licenses/MIT).
