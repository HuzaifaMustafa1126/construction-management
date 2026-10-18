# Step 1.9 — Company Profile & Department Management

## Overview

Step 1.9 adds tenant-scoped company profile maintenance and single-primary-department management. All endpoints require a Sanctum bearer token, `account.access`, and `company.context`. Company identity always comes from the authenticated tenant context; no endpoint accepts a company selector.

Base URL: `/api/v1`

## Permissions

| Capability | Permission | Company Admin | Site Supervisor | Client |
|---|---|---:|---:|---:|
| View company | `company.view` | Yes | No | No |
| Update company | `company.update` | Yes | No | No |
| View departments | `departments.view` | Yes | Yes | No |
| Create departments | `departments.create` | Yes | No | No |
| Update/status departments | `departments.update` | Yes | No | No |
| Delete departments | `departments.delete` | Yes | No | No |
| Assign users | `departments.assign` | Yes | No | No |

Permissions are still evaluated dynamically. A custom role receives only permissions explicitly granted to it.

## Company profile

### Read

`GET /api/v1/company`

Returns `data` with safe profile fields: `id`, `name`, `email`, `phone`, `address`, `city`, `state`, `country`, `postal_code`, `website`, `logo`, `currency`, `timezone`, and timestamps.

### Update

`PUT /api/v1/company` or `PATCH /api/v1/company`

```json
{
  "name": "Company A Construction",
  "email": "info@example.com",
  "phone": "+923001234567",
  "address": "Main Office",
  "city": "Lahore",
  "state": "Punjab",
  "country": "Pakistan",
  "postal_code": "54000",
  "website": "https://example.com",
  "currency": "PKR",
  "timezone": "Asia/Karachi"
}
```

`id`, `company_id`, `slug`, company/subscription status, plan, and trial fields are prohibited and return 422. The response is `{ "data": {...}, "message": "..." }`.

## Departments

### List

`GET /api/v1/departments?search=eng&status=Active&sort=name&direction=asc&per_page=15&page=1`

Supported sort fields: `name`, `code`, `created_at`. The response contains `data` and pagination `meta`. Each department includes `employee_count`.

### Create

`POST /api/v1/departments`

```json
{
  "name": "Engineering",
  "code": "ENG",
  "description": "Engineering delivery team",
  "status": "Active"
}
```

The server sets `company_id` and `created_by`. Name and code are case-insensitively unique among active (non-deleted) departments in that company. Another company may use the same values.

### Read, update, delete

- `GET /api/v1/departments/{id}`
- `PUT|PATCH /api/v1/departments/{id}`
- `DELETE /api/v1/departments/{id}`

Updates accept `name`, `code`, and `description`; status has its dedicated endpoint. Delete is always a soft delete. Existing user `department_id` values are retained for historical reporting, the relationship can still resolve the deleted record, and the deleted department cannot receive new assignments. No user is moved automatically.

### Status

`PATCH /api/v1/departments/{id}/status`

```json
{ "status": "Inactive" }
```

Allowed values are `Active` and `Inactive`. Existing assignments remain in place; inactive departments reject new assignments.

### Assigned users

`GET /api/v1/departments/{id}/users?per_page=15&page=1`

Returns non-deleted users from the current company only, with safe user, role, status, and department details plus pagination metadata.

## User assignment and filtering

Assign or replace the primary department:

`PUT /api/v1/users/{id}/department`

```json
{ "department_id": 3 }
```

Remove the assignment:

```json
{ "department_id": null }
```

The target user and department must belong to the authenticated company. The department must be active and non-deleted. `company_id`, role, and status are prohibited.

Filter the existing user list with `GET /api/v1/users?department_id=3`. User responses retain the legacy string `department` field and add `department_id` plus a safe `department_details` object. This avoids breaking clients that use the pre-existing `department` string column.

## Errors and tenant isolation

- 401: missing/invalid authentication
- 403: missing permission
- 404: missing, deleted, or cross-company target
- 422: invalid input, prohibited field, duplicate name/code, or assignment to an inactive department

To verify isolation in Postman, authenticate as Company A and request a Company B department or user ID. Expect 404, not 403, and verify the foreign record is unchanged.

## Activity events

The implementation records `company.profile_updated`, `department.created`, `department.updated`, `department.status_changed`, `department.deleted`, `department.user_assigned`, and `department.user_unassigned`. Properties include tenant/target identifiers and safe changes; passwords and tokens are never logged.

## Postman cleanup and safety

Create a disposable department such as `API Test <timestamp>` and a disposable employee. Exercise assignment, deactivation/reactivation, unassignment, then soft-delete the disposable department and user through their APIs. Do not edit or delete User 5 or User 11. Because deletion is soft and assignments are preserved, do not reuse production departments for destructive tests.

## Database design

The departments table uses generated nullable `active_name` and `active_code` columns plus unique indexes with `company_id`. The generated values are lowercase only while `deleted_at` is null. This provides case-insensitive active-row uniqueness on MySQL/MariaDB while allowing values from soft-deleted rows to be reused. `users.department_id` is nullable and uses `nullOnDelete`; normal application deletion is soft, so history remains intact.

Before applying these additive migrations, a verified SQL backup was created at `/tmp/construction_management_pre_step_1_9_20261018.sql` (SHA-256 `965a67486b064193f98f8337e243cf47383b4b723bc450215c7ccdb502514fd5`). No departments are seeded automatically.
