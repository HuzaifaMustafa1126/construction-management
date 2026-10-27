# Step 1.8 — Company User Management

All endpoints require a Sanctum bearer token, an active user and company, trusted company context, and the listed RBAC permission. Send `Accept: application/json` and `Content-Type: application/json`.

## Endpoints

| Method | Endpoint | Requirement |
|---|---|---|
| GET | `/api/v1/users` | `users.view` |
| POST | `/api/v1/users` | `users.create` and `roles.assign` |
| GET | `/api/v1/users/{id}` | `users.view` |
| PUT/PATCH | `/api/v1/users/{id}` | `users.update` |
| PATCH | `/api/v1/users/{id}/status` | Company Admin and `users.update` |
| DELETE | `/api/v1/users/{id}` | `users.delete` |
| PUT | `/api/v1/me` | Authenticated account |
| PUT | `/api/v1/me/password` | Authenticated account |

Company IDs always come from authenticated context. Cross-company targets return `404`. Passwords, hashes, remember tokens, and Sanctum tokens are never returned.

## List, search, and paginate

```http
GET {{base_url}}/api/v1/users?search=ahmed&status=Active&role=site_supervisor&sort=name&direction=asc&page=1&per_page=10
Authorization: Bearer {{token_company_a}}
```

Search covers name and email. Status values are `Active`, `Inactive`, or `Suspended`. Sort is restricted to `name` or `created_at`; `per_page` is limited to 100.

```json
{
  "users": [],
  "meta": {"current_page": 1, "last_page": 1, "per_page": 10, "total": 0}
}
```

The `users` key is preserved for legacy clients. Legacy `GET /api/users` receives the same tenant-safe paginated structure.

## Create a user

```http
POST {{base_url}}/api/v1/users
```

```json
{
  "first_name": "Ahmed",
  "last_name": "Ali",
  "email": "ahmed@example.com",
  "phone": "03001234567",
  "password": "StrongPassword123!",
  "role": "site_supervisor"
}
```

Expected: `201`. The password is hashed, status starts as `Active`, and the selected role must belong to the current company. Legacy role inputs `admin`, `supervisor`, and `client` remain mapped to their stable Spatie roles. A caller cannot assign a role containing permissions they do not possess. `company_id` and `status` are rejected.

## View and update

```http
GET {{base_url}}/api/v1/users/{{test_user_id}}
```

```http
PATCH {{base_url}}/api/v1/users/{{test_user_id}}
```

```json
{
  "first_name": "Ahmed Updated",
  "phone": "03009999999"
}
```

General updates allow name, first/last name, email, phone, and department only. Company, password, status, roles, permissions, and internal fields are rejected. Use `/api/v1/users/{id}/roles` for roles and the status endpoint for account state.

## Suspend or reactivate

```http
PATCH {{base_url}}/api/v1/users/{{test_user_id}}/status
```

```json
{"status":"Suspended"}
```

Expected: `200`. Existing tokens immediately receive `403` while the user is restricted. Reactivating with `{"status":"Active"}` does not create a token; an otherwise valid, unexpired existing token becomes usable again. The last active Company Admin cannot be made inactive or suspended (`422`).

## Delete/deactivate

```http
DELETE {{base_url}}/api/v1/users/{{test_user_id}}
```

Deletion is reversible soft deletion. The record, relationships, and history remain, but the user is excluded from normal queries, cannot log in, and existing tokens return `401`. Cross-company deletion returns `404`; self-deletion and removal of the last active administrator are blocked.

## Own profile

```http
PUT {{base_url}}/api/v1/me
```

```json
{"first_name":"Ahmed","last_name":"Khan","phone":"03001112222"}
```

Users may update only non-sensitive profile fields. Company, status, roles, permissions, and passwords are prohibited.

## Change password

```http
PUT {{base_url}}/api/v1/me/password
```

```json
{
  "current_password":"CurrentPassword123!",
  "password":"NewStrongPassword123!",
  "password_confirmation":"NewStrongPassword123!"
}
```

The current password is verified and the replacement requires confirmation and strength validation. The presented token remains valid; all other Sanctum tokens for the user are revoked. Password values and hashes are never logged.

## Postman security checks

1. With `token_company_a`, request a Company B user ID from detail, update, status, and delete endpoints; expect `404`.
2. Submit `company_id` during creation or update; expect `422`.
3. With Site Supervisor and Client tokens, call company user-management APIs; expect `403` under the default matrix.
4. Issue a disposable user token, suspend that user, and call `/api/v1/me`; expect `403`. Reactivate and retry the same unexpired token; expect `200`.
5. Soft-delete a disposable user, retry login and the issued token; expect `401`.
6. Attempt to suspend the sole Company Admin; expect `422`.
7. Change a disposable user's password with two active tokens. Confirm the current token works and the other returns `401`.

## Validation and cleanup

Validation failures return `422` with an `errors` object. Unauthenticated requests return `401`; insufficient permissions return `403`.

Use only disposable users for status, password, and deletion tests. Record original statuses, avoid Companies 1/2 administrators, and restore test state afterward. Soft-deleted records are intentionally retained; no restore API is exposed in this phase. Activity events record safe changed fields and identifiers without authentication secrets.
