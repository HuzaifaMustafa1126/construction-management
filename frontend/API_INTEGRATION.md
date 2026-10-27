# API Integration

## Verified source and client

These contracts were verified against the Laravel 12 project at `../backend` on 2026-10-27. Sources include `routes/api.php`, API controllers, Form Requests, `UserResource`, models, middleware, policies, `PermissionCatalog`, and `CompanyRoleInitializer`. The backend is authoritative.

`src/services/apiClient.ts` uses `VITE_API_BASE_URL` (normally `http://127.0.0.1:8000/api/v1`), sends JSON, attaches the session bearer token, and normalizes API failures. All paths below are relative to `/api/v1`. Protected routes require Sanctum, an active user, a valid company assignment, and an operational company. Company routes also establish tenant scope. Validation failures use `{ message, errors: Record<string,string[]> }`; nested registration keys are dot paths such as `company.email`.

## Company registration and email verification

### `POST /register-company` (public; 5/hour/IP)

```json
{
  "company": { "name": "Acme", "email": "office@example.com", "phone": "+92 300 1234567", "address": "Optional" },
  "admin": { "first_name": "Ayesha", "last_name": "Khan", "email": "ayesha@example.com", "phone": "+92 300 7654321", "password": "StrongPassword1!", "password_confirmation": "StrongPassword1!" }
}
```

Both objects are required. Company: `name` required string max 255; `email` required email max 255, globally unique; `phone` required string max 30 matching `^\+?[0-9][0-9\- ]{6,28}$`; `address` nullable string max 2000. Admin: names required strings max 100; `email` required email max 255, globally unique; `phone` nullable with the same phone rule; `password` required/confirmed, minimum 12 with mixed case, number, and symbol. Emails are trimmed/lowercased. Server-owned IDs, tenant, role/permission/status, verification, subscription, and audit fields are prohibited.

Created values: company `status=pending_verification`, `onboarding_status=pending_verification`, `subscription_status=trial`, `currency=PKR`, `timezone=Asia/Karachi`, `email_verification_required=true`; administrator `status=Active`, role `company_admin`.

HTTP 201 response:

```json
{
  "message": "Company registered successfully. Please verify your email.",
  "data": {
    "company": { "id": 1, "name": "Acme", "status": "pending_verification" },
    "admin": { "id": 1, "first_name": "Ayesha", "last_name": "Khan", "email": "ayesha@example.com", "email_verified": false },
    "onboarding_status": "pending_verification"
  }
}
```

Duplicate database values return 422 with `errors.registration`.

### `GET /verify-email/{id}/{hash}?expires=...&signature=...` (public signed link; 10/minute/IP)

The complete signed path/query must be preserved. Invalid/expired signature, user/company, or hash returns 403 `{ message }`. First success sets the user verification timestamp, company `status=active`, and onboarding `complete`, returning 200 `{ "message": "Email verified and company activated successfully." }`. A valid repeat/non-activating request returns 200 with `Email verification is already complete or company activation is unavailable.`

Laravel currently generates the normal named-route URL. Deployment must route that signed URL to the API or proxy it without changing signed host/path/query; the frontend cannot reconstruct an absolute signature.

### `POST /resend-verification` (public; 3/minute/IP)

Request `{ "email": "ayesha@example.com" }`; required email max 255, trimmed/lowercased. Always returns 200 with the non-enumerating message `If the registration is pending verification, a new verification email has been sent.`

## Authentication and profile

| Method/path | Request and response | Access |
|---|---|---|
| `POST /login` | Required email and string password; 200 `{ message,user,token }` | Public, 5/minute; active user, operational company, verified email. Invalid/unavailable: 401; unverified: 403. |
| `POST /logout` | 200 `{ message }`; deletes current token | Sanctum |
| `GET /me` | 200 `{ user }` | Sanctum + account access |
| `PUT /me` | Profile subset; 200 `{ message,user: UserResource }` | Authenticated tenant user |
| `PUT /me/password` | `current_password`, `password`, `password_confirmation`; new password min 8 with letters/numbers; 200 `{ message }` | Authenticated tenant user; wrong current password 422; other tokens revoked |

Profile fields: `first_name`/`last_name` required when present, string max 100; `name` required when present, max 255; `email` required when present, email max 255 and unique except self; `phone` and legacy `department` nullable strings max 30/100. Tenant, password, role(s), permissions, status, and remember token are prohibited.

Login and `/me` serialize the User model directly (password, remember token, company_id hidden). `UserResource` fields are `id,name,first_name,last_name,email,email_verified_at,phone,role,roles,status,department,department_id,department_details,created_at,updated_at,company_id,company`; conditional relationships appear only when loaded.

## Company profile

`GET /company` requires `company.view`, returning `{ data: Company }`. `PUT|PATCH /company` requires `company.update`, returning `{ data: Company, message }`. Shape: `id,name,email,phone,address,city,state,country,postal_code,website,logo,currency,timezone,created_at,updated_at`.

Update fields are optional: name required string max 255 when present; nullable email (email/max 255), phone (max 30), address (max 2000), city/state/country (max 100), postal_code (max 30), HTTP(S) website (max 255), logo string (max 255); currency required 3-character string and timezone valid when present. IDs, status, subscription, trial, and slug are prohibited.

Statuses: company `pending_verification|active|suspended`; onboarding `pending_verification|complete`; subscription `trial|active|suspended`.

## Users

All endpoints are tenant-scoped.

| Method/path | Contract | Permission |
|---|---|---|
| `GET /users` | Filters: search; `Active|Inactive|Suspended`; role; integer department_id; sort `name|created_at`; direction; page; per_page 1..100. `{ users: UserResource[], meta }` | `users.view` |
| `POST /users` | Required first_name, last_name, unique email, password min 8, role; nullable phone/legacy department. 201 `{ message,user }` | `users.create` + `roles.assign`; caller may grant only permissions held |
| `GET /users/{id}` | `{ user: UserResource }` | `users.view` |
| `PUT|PATCH /users/{id}` | Profile subset; password/role/status/security fields prohibited. `{ message,user }` | `users.update` |
| `PATCH /users/{id}/status` | `{ status: "Active"|"Inactive"|"Suspended" }`; `{ message,user }` | company_admin + `users.update`; last active admin protected |
| `DELETE /users/{id}` | `{ message }`; self and last active admin protected | `users.delete` |
| `PUT /users/{id}/roles` | `{ roles: string[] }`, min 1, distinct tenant names; `{ message,data:{user_id,roles} }` | `roles.assign`; grant subset and last admin rules apply |
| `PUT /users/{id}/department` | `{ department_id: integer|null }`, key required; `{ message,data:{user_id,department_id} }` | `departments.assign`; department must be active/current tenant |

Create accepts aliases `admin -> company_admin`, `supervisor -> site_supervisor`. Its response user contains `id,first_name,last_name,email,phone,role,status,department,roles`; legacy role is `admin|supervisor|client`.

## Roles and permissions

Role shape: `{ id,name,permissions:string[] }`.

| Method/path | Contract | Permission |
|---|---|---|
| `GET /roles` | `{ data: Role[] }` | `roles.view` |
| `POST /roles` | `{ name,permissions }`; 201 `{ data: Role }` | `roles.create`; all grants must be held |
| `GET /roles/{id}` | `{ data: Role }` | `roles.view` |
| `PUT|PATCH /roles/{id}` | complete name/permissions payload; `{ data: Role }` | `roles.update`; all grants must be held |
| `DELETE /roles/{id}` | `{ message }` | `roles.delete` |
| `GET /permissions` | `{ data: { group: string[] } }` | `roles.view` |

Role name: required, max 125, tenant/guard unique, `^[a-z][a-z0-9_]*$`, not `platform_super_admin`. Permissions is a required array of catalog strings. `company_id` prohibited. `company_admin` cannot be updated/deleted.

Catalog:

- dashboard: `dashboard.view`
- projects: `projects.view`, `projects.create`, `projects.update`, `projects.delete`, `projects.assign`, `projects.budget.view`
- site_reports: `site_reports.view`, `site_reports.create`, `site_reports.update`, `site_reports.delete`, `site_reports.approve`
- site_photos: `site_photos.view`, `site_photos.upload`, `site_photos.delete`
- photos: `photos.view`, `photos.create`, `photos.update`, `photos.delete`, `photos.approve`
- materials: `materials.view`, `materials.create`, `materials.update`, `materials.delete`, `material_categories.manage`, `measurement_units.manage`
- inventory: `inventory.view`, `inventory.create`, `inventory.update`, `inventory.opening_stock`
- suppliers: `suppliers.view`, `suppliers.manage`
- material_deliveries: `material_deliveries.view`, `material_deliveries.create`, `material_deliveries.update`, `material_deliveries.receive`, `material_deliveries.cancel`
- material_consumptions: `material_consumptions.view`, `material_consumptions.create`, `material_consumptions.update`, `material_consumptions.post`, `material_consumptions.cancel`
- stock_adjustments: `stock_adjustments.view`, `stock_adjustments.create`, `stock_adjustments.update`, `stock_adjustments.post`, `stock_adjustments.cancel`
- inventory_reports: `inventory_reports.view`, `inventory_reports.view_company`, `inventory_reports.view_movements`, `inventory_reports.view_alerts`
- labor: `labor.view`, `labor.manage`; expenses: `expenses.view`, `expenses.create`, `expenses.update`, `expenses.delete`; payments: `payments.view`, `payments.manage`
- users: `users.view`, `users.create`, `users.update`, `users.delete`
- roles: `roles.view`, `roles.create`, `roles.update`, `roles.delete`, `roles.assign`
- company: `company.view`, `company.update`
- departments: `departments.view`, `departments.create`, `departments.update`, `departments.delete`, `departments.assign`

Seed roles: `company_admin` (all), `site_supervisor` (catalog-defined operational subset), `client` (dashboard/project/site-report/site-photo/payment/photo view). Company/user/role/department authorization is inline in controllers; registered policies cover project/inventory/supplier/delivery/consumption/adjustment/report/photo domains and add tenant, membership, and role constraints.

## Departments

Shape: `{ id,name,code,description,status,created_by,created_at,updated_at,employee_count }`; status is `Active|Inactive`.

| Method/path | Contract | Permission |
|---|---|---|
| `GET /departments` | Search; status; sort `name|code|created_at`; direction; page; per_page 1..100. `{ data: Department[],meta }` | `departments.view` |
| `POST /departments` | Required name max 125 and code max 50 matching `^[A-Za-z0-9_-]+$`; nullable description max 2000; optional status. Name/code case-insensitively unique in active tenant rows. 201 `{ data,message }` | `departments.create` |
| `GET /departments/{id}` | `{ data }` | `departments.view` |
| `PUT|PATCH /departments/{id}` | Optional name/code/description; status prohibited. `{ data,message }` | `departments.update` |
| `PATCH /departments/{id}/status` | `{ status }`; `{ data,message }` | `departments.update` |
| `DELETE /departments/{id}` | Soft delete, assignments preserved; `{ message }` | `departments.delete` |
| `GET /departments/{id}/users` | Optional page/per_page 1..100; `{ users: UserResource[],meta }` | `departments.view` |

Pagination meta is `{ current_page,last_page,per_page,total }`. Missing tenant resources return 404, permission failures 403, validation/protected fields 422, and throttling 429. Frontend visibility is not authorization; Laravel remains authoritative.
