# Step 1.7 — Multi-Tenant Roles and Permissions

The API uses Sanctum authentication, active account/company validation, trusted tenant context, and Spatie's `company_id` team context. Send `Accept: application/json`, `Content-Type: application/json`, and `Authorization: Bearer {{token_company_a}}` unless stated otherwise.

## Default roles

- `company_admin`: all approved company permissions.
- `site_supervisor`: operational create/update permissions for reports, photos, materials, labor, and expenses; no user or role administration.
- `client`: read-only dashboard, project, report, photo, and payment access.

Permissions do not replace future project ownership and assignment policies. No construction business routes are introduced here.

## Postman variables

`base_url`, `token_company_a`, `token_company_b`, `company_a_role_id`, `company_b_role_id`, and `test_user_id`.

## Endpoints

| Method | Endpoint | Permission |
|---|---|---|
| GET | `/api/v1/roles` | `roles.view` |
| POST | `/api/v1/roles` | `roles.create` |
| GET | `/api/v1/roles/{id}` | `roles.view` |
| PUT | `/api/v1/roles/{id}` | `roles.update` |
| DELETE | `/api/v1/roles/{id}` | `roles.delete` |
| GET | `/api/v1/permissions` | `roles.view` |
| PUT | `/api/v1/users/{id}/roles` | `roles.assign` |

Existing user APIs require `users.view`, `users.create`, and `users.delete`. Legacy `/api/users` routes use the same RBAC and tenant rules.

## List roles

```http
GET {{base_url}}/api/v1/roles
Authorization: Bearer {{token_company_a}}
```

```json
{
  "data": [
    {
      "id": 1,
      "name": "company_admin",
      "permissions": ["users.create", "users.delete", "users.update", "users.view"]
    }
  ]
}
```

Only roles belonging to the authenticated company are returned.

## Create or update a role

```http
POST {{base_url}}/api/v1/roles
```

```json
{
  "name": "project_viewer",
  "permissions": ["dashboard.view", "projects.view"]
}
```

Expected: `201 Created`. Use the same body with `PUT /api/v1/roles/{id}` for an update (`200 OK`). Names must be lowercase machine identifiers. Unknown permissions, duplicate same-company names, and `company_id` return `422`. A caller cannot grant permissions they do not possess.

The `company_admin` role cannot be updated or deleted. Deleting another same-company role returns `200`:

```json
{"message":"Role deleted successfully."}
```

## Permission catalog

```http
GET {{base_url}}/api/v1/permissions
```

Returns the approved catalog grouped under dashboard, projects, site reports, site photos, materials, labor, expenses, payments, users, roles, and company. Platform-only permissions are not exposed.

## Assign roles

```http
PUT {{base_url}}/api/v1/users/{{test_user_id}}/roles
```

```json
{"roles":["site_supervisor"]}
```

Expected: `200 OK`. The operation atomically replaces the target user's company roles. The target and every requested role must belong to the authenticated company. `company_id` is prohibited. The last active `company_admin` cannot lose that role.

## Expected failures

- No/invalid/expired token: `401`.
- Site Supervisor, Client, or insufficient permission: `403` with `{"message":"You do not have permission to perform this action."}`.
- A Company A token requesting a Company B role or user resource: `404`.
- Invalid, unknown, duplicate, or cross-company role input: `422`.

## Two-company isolation checks

1. Log in as Company A admin and save `token_company_a`.
2. Log in as Company B admin and save `token_company_b`.
3. Create `project_viewer` independently with both tokens; both requests should succeed.
4. Save the returned IDs separately.
5. Request Company B's role ID with Company A's token; expect `404`.
6. Try updating or deleting Company A's role with Company B's token; expect `404`.
7. Try assigning a Company B-only role or Company B user using Company A's token; expect `422` or `404`, respectively.
8. Confirm each `GET /api/v1/roles` response contains only that company's roles.

## User API checks

- Company Admin can list, create, and delete permitted same-company users.
- Site Supervisor and Client cannot list or delete company users under the default matrix.
- Cross-company deletion remains `404` and leaves the target intact.
- Submitted `company_id` remains prohibited with `422`.

## Activity log

Role creation, update, deletion, permission changes, and user role additions/removals are recorded in `activity_log`. Properties contain actor/target/company identifiers and non-sensitive changes only; passwords and bearer tokens are never logged.

## Safe setup and cleanup

Back up the development database before seeding or manual API tests. Bootstrap only the verified records with:

```bash
php artisan db:seed --class=CompanyRolesPermissionsSeeder
```

The seeder is transactional and idempotent. It creates the approved catalog and three roles for Companies 1 and 2, then assigns `company_admin` only to verified Users 5 and 11. It does not alter passwords, tokens, legacy roles, or company assignments.

Use disposable custom roles and users for Postman tests. Never delete either `company_admin` role or real administrator. Step 1.8 may proceed only after explicit approval.
