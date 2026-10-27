# Step 1.5 — Postman Tenant-Isolation Testing

This guide verifies that company users cannot read, create, or delete another company's data. Use disposable local test records only.

## Prerequisites

1. Back up the development database.
2. Start Laravel with `php artisan serve`.
3. Use `http://127.0.0.1:8000` as the base URL.
4. Prepare two active disposable companies and explicitly assigned users:
   - Company A Admin with `role = admin`
   - Company B Admin with `role = admin`
   - At least one disposable user in each company
5. Prepare, when required, one unassigned admin and one admin belonging to a suspended company.

Do not convert an existing admin into a Platform Super Admin. Do not test deletion with non-disposable users.

## Collection variables

Create these Postman collection variables:

| Variable | Example placeholder |
|---|---|
| `base_url` | `http://127.0.0.1:8000` |
| `token_company_a` | Set from Company A login |
| `token_company_b` | Set from Company B login |
| `token_unassigned` | Set from unassigned-user login |
| `token_suspended` | Set from suspended-company login |
| `company_a_id` | Disposable Company A ID |
| `company_b_id` | Disposable Company B ID |
| `company_a_user_id` | Disposable Company A user ID |
| `company_b_user_id` | Disposable Company B user ID |

Every JSON request should send:

```http
Accept: application/json
Content-Type: application/json
```

Protected requests use Authorization → Bearer Token and the appropriate token variable.

## 1. Login as Company A Admin

```http
POST {{base_url}}/api/v1/login
```

```json
{
  "email": "company-a-admin@example.test",
  "password": "YOUR_TEST_PASSWORD"
}
```

Expected: `200 OK` with `user` and `token`. Save the token as `token_company_a`.

Optional Postman Tests script:

```javascript
const body = pm.response.json();
pm.collectionVariables.set('token_company_a', body.token);
```

Repeat with the Company B administrator and save `token_company_b`.

## 2. List Company A users

```http
GET {{base_url}}/api/v1/users
Authorization: Bearer {{token_company_a}}
```

Expected: `200 OK`.

```json
{
  "users": []
}
```

Every returned user must belong to Company A. No Company B user ID or email may appear.

Repeat with `token_company_b`; only Company B users may appear.

## 3. Create a Company A user

```http
POST {{base_url}}/api/v1/users
Authorization: Bearer {{token_company_a}}
```

```json
{
  "first_name": "Ayesha",
  "last_name": "Tenant Test",
  "email": "ayesha.tenant-test@example.test",
  "phone": "03001234567",
  "password": "secure-password",
  "role": "supervisor",
  "department": "Site Operations"
}
```

Expected: `201 Created` using the existing response structure. The backend assigns Company A from the authenticated context; `company_id` is not returned.

Save the returned ID as `company_a_user_id` if this account will be deleted during cleanup.

## 4. Attempt a company override

Use Company A's token but submit Company B's ID:

```http
POST {{base_url}}/api/v1/users
Authorization: Bearer {{token_company_a}}
```

```json
{
  "first_name": "Invalid",
  "last_name": "Override",
  "email": "invalid.override@example.test",
  "password": "secure-password",
  "role": "client",
  "company_id": "{{company_b_id}}"
}
```

Expected: `422 Unprocessable Content`.

```json
{
  "message": "The company id field is prohibited.",
  "errors": {
    "company_id": [
      "The company id field is prohibited."
    ]
  }
}
```

No user should be created.

## 5. Attempt a cross-company deletion

Use Company A's token against a disposable Company B user:

```http
DELETE {{base_url}}/api/v1/users/{{company_b_user_id}}
Authorization: Bearer {{token_company_a}}
```

Expected: `404 Not Found`.

```json
{
  "message": "User not found."
}
```

Confirm the Company B user still exists by listing users with `token_company_b`.

## 6. Test an unassigned user

Login through `/api/v1/login` as an admin whose `company_id` is null and save the token as `token_unassigned`. Login itself remains available so the account can be diagnosed safely.

```http
GET {{base_url}}/api/v1/users
Authorization: Bearer {{token_unassigned}}
```

Expected: `403 Forbidden`.

```json
{
  "message": "A company assignment is required."
}
```

A null company never grants platform access.

## 7. Test a suspended company

Login as an admin assigned to a disposable company whose `status` is `suspended`.

```http
GET {{base_url}}/api/v1/users
Authorization: Bearer {{token_suspended}}
```

Expected: `403 Forbidden`.

```json
{
  "message": "Company access is suspended."
}
```

## 8. Verify role restrictions

Using a Company A supervisor or client token:

```http
GET {{base_url}}/api/v1/users
```

Expected: `403 Forbidden`. A valid company assignment does not grant administrator privileges.

## 9. Verify self-deletion protection

```http
DELETE {{base_url}}/api/v1/users/COMPANY_A_ADMIN_ID
Authorization: Bearer {{token_company_a}}
```

Expected: `403 Forbidden`.

```json
{
  "message": "You cannot delete your own account."
}
```

## 10. Verify unauthenticated behavior

Send `GET {{base_url}}/api/v1/users` without Authorization.

Expected: `401 Unauthorized`.

## 11. Verify legacy-route parity

Repeat the list, override, and cross-company delete tests against:

- `GET {{base_url}}/api/users`
- `POST {{base_url}}/api/users`
- `DELETE {{base_url}}/api/users/{{company_b_user_id}}`

Expected statuses and tenant boundaries must match `/api/v1` exactly.

Legacy login, current-user, and logout endpoints remain:

- `POST /api/login`
- `GET /api/user`
- `POST /api/logout`

## Safe test-data preparation

Do not create or assign a company implicitly. First create an approved disposable company through a trusted local process, then preview an explicit assignment:

```powershell
php artisan company:assign-users COMPANY_ID USER_ID
```

The command is dry-run by default. To persist an approved mapping:

```powershell
php artisan company:assign-users COMPANY_ID USER_ID --commit
```

Only explicitly listed users are assigned. The command refuses cross-company reassignment.

## Cleanup

1. Delete only users created specifically for this test, using an administrator from the same company.
2. Do not delete an administrator's own account through the API.
3. Confirm each target ID and company before deletion.
4. Companies use soft deletion, but a hard delete is restricted while users remain assigned.
5. Do not run destructive migration or database-reset commands.

## Automated verification

```powershell
php artisan test
php artisan route:list --path=api -v
vendor/bin/pint --test
```

The automated tenant suite uses disposable Company A and Company B records in SQLite `:memory:` and does not modify the development MySQL database.
