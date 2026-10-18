# Step 1.6 — Authentication Security

This step applies current user and company status checks to Sanctum bearer authentication while preserving the versioned and legacy APIs.

## Access policy

- A user is permitted only when `users.status` is exactly `Active`.
- The user must have a `company_id` referring to a non-deleted company.
- A company is operational only when `companies.status` is exactly `active`.
- `Inactive`, `Suspended`, unknown, and missing states fail closed.
- Subscription and trial status do not affect access in this step.
- An unassigned user is not inferred to be a Platform Super Admin.
- The account and company are read from the database on every protected request. Suspending either immediately blocks previously issued tokens.
- Sanctum bearer tokens expire after `SANCTUM_TOKEN_EXPIRATION` minutes, defaulting to 480 minutes.

Existing tokens are not deleted. Sanctum evaluates their original `created_at` value against the configured lifetime, so tokens older than the new limit stop authenticating when the configuration is deployed. Clear cached configuration after changing the environment value:

```bash
php artisan config:clear
```

## Postman variables

| Variable | Purpose |
|---|---|
| `base_url` | API origin, for example `http://127.0.0.1:8000` |
| `token_company_a` | Active disposable Company A user's token |
| `token_company_b` | Active disposable Company B user's token |
| `token_inactive_test` | Token issued before a disposable user is made inactive |
| `token_suspended_test` | Token issued before a disposable company is suspended |

Send `Accept: application/json` and `Content-Type: application/json`. Protected requests use `Authorization: Bearer {{token_company_a}}` or the appropriate test token.

## Login

### Versioned endpoint

```http
POST {{base_url}}/api/v1/login
```

### Legacy endpoint

```http
POST {{base_url}}/api/login
```

Body:

```json
{
  "email": "active-disposable@example.test",
  "password": "test-password"
}
```

An active user at an active company receives `200 OK` with the compatible response shape:

```json
{
  "message": "Login successful",
  "user": {},
  "token": "<sanctum-token>"
}
```

Postman test script:

```javascript
pm.test('Login succeeded', () => pm.response.to.have.status(200));
const json = pm.response.json();
pm.expect(json).to.have.keys('message', 'user', 'token');
pm.collectionVariables.set('token_company_a', json.token);
```

Incorrect credentials, inactive/suspended users, unassigned users, missing/deleted companies, and inactive/suspended companies all receive `401 Unauthorized`:

```json
{
  "message": "Invalid credentials or account unavailable."
}
```

No token is created for a rejected login. Missing or invalid request fields continue to return `422`. Both login endpoints retain the `5 requests per minute` throttle; excess attempts return `429` as JSON.

## Profile and protected access

```http
GET {{base_url}}/api/v1/me
Authorization: Bearer {{token_company_a}}
```

Legacy equivalent:

```http
GET {{base_url}}/api/user
Authorization: Bearer {{token_company_a}}
```

Expected results:

| Condition | Status |
|---|---:|
| Active token, user, and company | `200` |
| Missing, malformed, revoked, or expired token | `401` |
| Authenticated user becomes restricted | `403` |
| Authenticated company becomes restricted | `403` |
| Cross-company user resource | `404` |

## Logout

```http
POST {{base_url}}/api/v1/logout
Authorization: Bearer {{token_company_a}}
```

Legacy equivalent: `POST {{base_url}}/api/logout`.

Expected: `200 OK`. Only the presented token is revoked. A currently authenticated user may still call logout after their account or company becomes restricted, allowing safe token revocation without reopening profile or company resources. Reusing the revoked token returns `401`.

## Safe suspension tests

Use only disposable users and companies. Do not suspend Companies 1 or 2 or their real administrators.

### User suspension after token issuance

1. Create an active disposable user in a disposable active company.
2. Log in and save the token as `token_inactive_test`.
3. Change that disposable user's status to `Inactive` or `Suspended` using a trusted local administrative method.
4. Call `GET /api/v1/me` with that token; expect `403`.
5. Call `POST /api/v1/logout` with it; expect `200`.
6. Restore the original user status exactly to `Active`.

Postman assertion for step 4:

```javascript
pm.test('Issued token is blocked after suspension', () => {
    pm.response.to.have.status(403);
});
```

### Company suspension after token issuance

1. Create a disposable active company and assigned disposable user.
2. Log in and save the token as `token_suspended_test`.
3. Change only that disposable company's operational status to `suspended`.
4. Call both `GET /api/v1/me` and `GET /api/user`; expect `403`.
5. Restore the company status exactly to `active`.

## Expiration test

For a safe local-only test, set a short positive lifetime such as `SANCTUM_TOKEN_EXPIRATION=1`, clear configuration, log in, wait longer than one minute, and call `/api/v1/me`; expect `401`. Restore the value to `480` and clear configuration again. Do not edit token rows or delete all tokens.

## Tenant-isolation regression

With Company A and Company B disposable tokens:

- `GET /api/v1/users` returns only the authenticated company’s users.
- Posting a `company_id` to `/api/v1/users` returns `422`.
- Deleting a user belonging to the other company returns `404` and leaves that user intact.
- The same rules apply under legacy `/api/users` routes.

## Safe setup and cleanup

Back up the development database before manual status testing. Record the disposable records' original status values, use unique test email addresses, and restore statuses immediately afterward. Revoke only tokens created by the test. Never run destructive migration or database reset commands against the development database.

Platform Super Admin authentication, refresh tokens, subscription/trial enforcement, and RBAC remain deferred. The next approved build step is Step 1.7, Company-Based Roles and Permissions.
