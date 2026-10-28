# API Integration

## Verified source and client

These contracts were verified against the Laravel 12 project at `../backend` on 2026-10-27. Sources include `routes/api.php`, API controllers, Form Requests, `UserResource`, models, middleware, policies, `PermissionCatalog`, and `CompanyRoleInitializer`. The backend is authoritative.

`src/api/axiosClient.js` is the sole Axios client. It uses `VITE_API_BASE_URL` (normally `http://127.0.0.1:8000/api/v1`), sends JSON, attaches the session bearer token, and normalizes API failures. `src/api/endpoints.js` contains the currently shared endpoint adapters. All paths below are relative to `/api/v1`. Protected routes require Sanctum, an active user, a valid company assignment, and an operational company. Company routes also establish tenant scope. Validation failures use `{ message, errors: Record<string,string[]> }`; nested registration keys are dot paths such as `company.email`.

## Platform company creation and email verification

Anonymous company registration is retired. `POST /register-company` returns 404. Platform company creation is documented in `../docs/PLATFORM_COMPANY_MANAGEMENT.md` and is protected by Sanctum plus `platform.admin`.

### Retired: `POST /register-company`

The route is intentionally absent and returns 404 without writing data. Company creation moved to authenticated `POST /platform/companies`.

### `POST /administrator/password/setup` (public; 5/minute)

Accepts `{ email, token, password, password_confirmation }`. Passwords require at least 12 characters with mixed case, a number, and a symbol. Success returns `{ message }`. Invalid, expired, unknown, or previously used tokens return the same 422 message. The endpoint consumes Laravel's configured password-broker token and never changes company status.

### Platform company APIs

All `/platform/*` endpoints require Sanctum and `platform.admin`. `PATCH /platform/companies/{company}` accepts only `name`, `email`, `phone`, and `address`. The frontend edit route is `/platform/companies/:id/edit` and invalidates company list, detail, and dashboard query caches after a successful save.

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
| `GET /me` | 200 `{ user }`; company users include backend-authoritative `roles`, `effective_permissions`, and a minimal `company` identity | Sanctum; active platform user or operational company user |
| `PUT /me` | Profile subset; 200 `{ message,user: UserResource }` | Active company user or company-less Platform Super Admin |
| `PUT /me/password` | `current_password`, `password`, `password_confirmation`; new password min 8 with letters/numbers; 200 `{ message }` | Active company user or Platform Super Admin; wrong current password 422; other tokens revoked |

Profile fields: `first_name`/`last_name` required when present, string max 100; `name` required when present, max 255; `email` required when present, email max 255 and unique except self; `phone` and legacy `department` nullable strings max 30/100. Tenant, password, role(s), permissions, status, and remember token are prohibited.

Login returns the authenticated user and token, while the frontend treats `/me` as the authoritative identity refresh. Company identities include company-scoped roles and effective permission names evaluated with the authenticated company team context. The company summary contains only `id`, `name`, and `status`.

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

The Step 2.3 frontend follows these contracts directly: user role payloads contain role names, role permission payloads contain catalog names, and department assignment uses `{ department_id: integer|null }`. No company-management form sends `company_id` or platform privilege fields.

## Projects management

All project routes require an authenticated operational company context. Listing additionally requires `projects.view` and is tenant-scoped; non-`company_admin` identities receive only projects to which they are assigned. Project resources expose `id`, supported descriptive/address/date fields, status, progress, audit IDs/timestamps, and team summary. `budget` and `currency` are conditionally omitted unless the policy grants `projects.budget.view`, including for clients.

| Method/path | Contract | Access |
|---|---|---|
| `GET /projects` | Server filters `search,status,date_from,date_to`; sort `name,code,status,start_date,expected_end_date,created_at`; direction and pagination. Returns `{data,meta}`. | `projects.view` plus assignment/policy scope |
| `POST /projects` | Supported project descriptive, address, date, and optional budget fields. Server owns company, currency, status=`planning`, progress=0, and audit fields. | `projects.create` |
| `GET /projects/{id}` | `{data: ProjectResource}` or tenant-safe 404. | `projects.view` plus project policy |
| `PUT|PATCH /projects/{id}` | Supported descriptive, address, date, and budget fields only. | `projects.update`; clients denied by policy |
| `DELETE /projects/{id}` | Soft archives the project. | `projects.delete`; company-admin policy required |
| `PATCH /projects/{id}/status` | `{status}` using the backend transition graph. | `projects.update`; clients denied |
| `PATCH /projects/{id}/progress` | `{progress_percentage: integer 0..100}`; does not implicitly complete a project. | `projects.update`; clients denied |
| `GET /projects/{id}/team` | Team members with roles and assignment metadata. | `projects.assign`; company-admin manage-team policy |
| `PUT /projects/{id}/team` | Full replacement `{members:[{user_id,assignment_type}]}`. Types: `member`, `supervisor`, `client`; only active same-company role-eligible users. | `projects.assign`; company-admin manage-team policy |
| `GET /projects/{id}/overview` | Safe overview with policy-conditional budget. | `projects.view` plus project policy |

Statuses are `planning`, `active`, `on_hold`, `completed`, and `cancelled`. Allowed transitions are planning→active/cancelled, active→on_hold/completed/cancelled, and on_hold→active/cancelled. Completed and cancelled are terminal. Generic create/update forms prohibit `company_id`, currency, ownership/audit fields, status, progress, members, and team.

## Daily site reports

All report routes are project-nested and tenant-scoped. The caller must be able to view the project; non-administrators only see assigned projects. There is no company-wide report list, summary, or dashboard endpoint, so the frontend selects one project and never derives company totals from paginated/project subsets.

| Method/path | Contract |
|---|---|
| `GET /projects/{id}/daily-reports` | Filters: `search` (title), `date`, `date_from`, `date_to`, `status`, sort `report_date|created_at`, direction, page, per_page 1..100. Returns `{data,meta}`. Clients receive only approved, client-visible reports. |
| `POST /projects/{id}/daily-reports` | Creates a draft. Required `report_date`, `title`, `work_completed`, and present `workers` array; optional content fields and progress. One active report per project/date. |
| `GET /projects/{id}/daily-reports/{reportId}` | Returns one policy-visible report. Client resources omit review identity/notes. |
| `PUT|PATCH /projects/{id}/daily-reports/{reportId}` | Updates supported content only while draft/changes-required and policy-editable. Workforce replacement recalculates the server-owned total. |
| `POST .../{reportId}/submit` | Draft/changes-required to submitted; caller needs update permission and submit policy. |
| `POST .../{reportId}/review` | `{action: approve|request_changes, review_notes?}`; notes required for changes. Creator cannot review own report. |
| `PATCH .../{reportId}/visibility` | `{is_client_visible:boolean}`; company admin policy and approve permission; only approved reports may become visible. |
| `DELETE .../{reportId}` | Company-admin policy soft deletion with delete permission. |

Content fields are `report_date`, `title`, `work_completed`, `work_in_progress`, `issues`, `delays`, `delay_reason`, `weather_conditions`, `general_notes`, `progress_percentage`, and `workers`. Worker types are `mason`, `helper`, `electrician`, `plumber`, `carpenter`, `painter`, `steel_fixer`, `operator`, and `other`; counts are nonnegative integers. The server owns company/project IDs, total, status, visibility, creator/submitter/reviewer fields, and timestamps. No past/future date restriction or report attachment contract exists.

## Site photos

All photo APIs are project-nested. `GET /projects/{id}/photos` supports caption search, exact/date-range filters, `time_category`, management-only `approval_status`, server sort, and pagination. Clients receive only approved, explicitly client-visible photos. There is no company-wide gallery endpoint.

`POST /projects/{id}/photos` accepts multipart `photo`; `POST /projects/{id}/photos/batch` accepts `photos[]` with 1–10 files. Shared metadata is required `photo_date` and `time_category` (`morning|afternoon|evening`), plus nullable `caption` (255), `description` (5000), and same-project `daily_site_report_id`. Files must be images with JPG/JPEG, PNG, or WebP MIME/extension and at most 10,240 KB each. Laravel stores randomized names on the private local disk and owns tenant, project, uploader, storage, approval, visibility, and review fields.

`GET /projects/{id}/photos/{photoId}` returns safe metadata. `GET .../{photoId}/file` requires authentication and policy visibility, streams private bytes with `private, no-store` and `nosniff`, and returns no storage URL. The frontend therefore fetches blobs through the bearer-authenticated client and uses revocable object URLs.

`PATCH .../{photoId}` updates supported metadata. `POST .../{photoId}/review` accepts `approve|reject` with notes required for rejection. `PATCH .../{photoId}/visibility` controls approved-photo client visibility. `DELETE .../{photoId}` soft-deletes the record while retaining the private file. `GET /projects/{id}/daily-reports/{reportId}/photos` returns policy-safe associated photos.

## Materials and project inventory

`/materials` provides tenant-scoped catalog CRUD using category and measurement-unit IDs. Clients are denied catalog access and supervisors are view-only. `/projects/{id}/inventory` returns backend-calculated current stock, minimum levels, overview metrics, opening-stock state, and immutable transaction history. Quantities are decimal strings using unit precision; browser payloads never include company IDs or balances.

Laravel also exposes supplier-backed delivery, consumption, adjustment, and inventory-reporting document workflows. Transfers do not exist. The current frontend does not simulate these multi-step posting workflows with direct balance edits.

## Personal account

These endpoints require an authenticated active account but do not require company context, so both company users and Platform Super Admins can use them.

| Method/path | Contract | Security behavior |
|---|---|---|
| `GET /me` | `{ user: UserResource }` | Returns the authoritative identity, company, roles, permissions, and platform flag. |
| `PUT /me` | Optional `first_name,last_name,name,email,phone,department`; `{ message,user }` | Company IDs, passwords, statuses, roles, permissions, and platform privilege fields are rejected. |
| `PUT /me/password` | Required `current_password,password,password_confirmation`; password min 8 with letters and numbers; `{ message }` | Throttled to 5/minute. Verifies the current password, hashes the replacement, retains the calling token, and revokes all other tokens. |

Validation errors are returned as JSON 422 field errors. Password values are never returned or written to activity properties. A suspended company user remains blocked by account access middleware; allowing company-less Platform Super Admins does not bypass active-account checks.

## Browser token security

The current frontend stores the opaque Sanctum bearer token in `sessionStorage`. This limits persistence to the browser tab/session and the Axios interceptor clears it on protected 401 responses, but JavaScript-readable storage remains exposed if an XSS vulnerability executes in the origin. Active source does not use raw HTML injection or log credentials/tokens. Migrating to HttpOnly cookies is intentionally out of scope and would require coordinated Sanctum stateful-domain, CORS credential, CSRF-cookie, session-cookie, and Axios `withCredentials` changes.

## S7 material requests and stock ledger

Material request approval is non-posting. `POST /v1/material-requests/{id}/issue` posts positive `material_issued` quantities with direction `out`; `POST /v1/material-requests/{id}/return` posts `material_returned` with direction `in`. Both use UUID idempotency keys. `POST /v1/inventory-transfers` atomically posts a linked `transfer_out`/`transfer_in` pair. Do not retry a timed-out stock mutation with a new key; reload state or reuse the same key.
