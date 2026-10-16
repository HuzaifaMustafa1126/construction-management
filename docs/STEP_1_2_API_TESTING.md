# Step 1.2 — API Testing Guide

This guide tests API version `v1` and confirms that the legacy endpoints remain compatible. Examples use placeholders only.

## Start the API

```powershell
php artisan serve
```

Base URL: `http://127.0.0.1:8000`

For JSON requests send:

```http
Accept: application/json
Content-Type: application/json
```

Protected endpoints also require:

```http
Authorization: Bearer YOUR_TOKEN
```

## Endpoint reference

| Method | Endpoint | Authentication | Success |
|---|---|---|---:|
| `GET` | `/api/v1/health` | Public | `200` |
| `POST` | `/api/v1/login` | Public | `200` |
| `GET` | `/api/v1/me` | Bearer token | `200` |
| `POST` | `/api/v1/logout` | Bearer token | `200` |
| `GET` | `/api/v1/users` | Admin token | `200` |
| `POST` | `/api/v1/users` | Admin token | `201` |
| `DELETE` | `/api/v1/users/{id}` | Admin token | `200` |

## Expected requests and responses

### Health

`GET /api/v1/health`

```json
{
  "success": true,
  "message": "Construction ERP API is running",
  "version": "v1"
}
```

This checks only that the API responds; it does not verify the database or external services.

### Login

`POST /api/v1/login`

```json
{
  "email": "admin@example.com",
  "password": "YOUR_PASSWORD"
}
```

Expected `200 OK`:

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
  "token": "YOUR_SANCTUM_TOKEN"
}
```

### Current user

`GET /api/v1/me`, with a bearer token.

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

### Logout

`POST /api/v1/logout`, with a bearer token.

```json
{
  "message": "Logout successful"
}
```

### List users

`GET /api/v1/users`, with an administrator bearer token.

```json
{
  "users": [
    {
      "id": 2,
      "name": "Ayesha Khan",
      "email": "ayesha@example.com",
      "role": "supervisor",
      "status": "Active"
    }
  ]
}
```

### Create user

`POST /api/v1/users`, with an administrator bearer token.

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

Expected `201 Created`:

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

### Delete user

`DELETE /api/v1/users/{id}`, with an administrator bearer token. Use only the ID of a disposable test user.

```json
{
  "message": "User deleted successfully."
}
```

## Common error codes

| Status | Meaning |
|---:|---|
| `401` | Invalid login or missing, invalid, or revoked token |
| `403` | Authenticated user is not an admin, or attempted self-deletion |
| `404` | User ID was not found |
| `422` | Request validation failed |
| `429` | More than five login attempts were made within one minute |

## Postman instructions

1. Create a collection and set `base_url` to `http://127.0.0.1:8000`.
2. Add the `Accept: application/json` header to the collection.
3. Send the login request with Body → raw → JSON.
4. Copy the returned token into a collection variable named `token`.
5. For protected requests, select Authorization → Bearer Token and enter `{{token}}`.
6. Test user deletion only with a disposable user created for this purpose.
7. Send logout, then resend `/api/v1/me`; the second request must return `401`.

An optional Tests script on the login request can save the token automatically:

```javascript
const body = pm.response.json();
pm.collectionVariables.set('token', body.token);
```

## PowerShell verification

```powershell
$BaseUrl = 'http://127.0.0.1:8000'
$JsonHeaders = @{ Accept = 'application/json' }
```

Health:

```powershell
Invoke-RestMethod -Method Get -Uri "$BaseUrl/api/v1/health" -Headers $JsonHeaders
```

Login and save the token:

```powershell
$LoginBody = @{
    email = 'admin@example.com'
    password = 'YOUR_PASSWORD'
} | ConvertTo-Json

$Login = Invoke-RestMethod -Method Post `
    -Uri "$BaseUrl/api/v1/login" `
    -Headers $JsonHeaders `
    -ContentType 'application/json' `
    -Body $LoginBody

$Token = $Login.token
$AuthHeaders = @{
    Accept = 'application/json'
    Authorization = "Bearer $Token"
}
```

Current user and user list:

```powershell
Invoke-RestMethod -Method Get -Uri "$BaseUrl/api/v1/me" -Headers $AuthHeaders
Invoke-RestMethod -Method Get -Uri "$BaseUrl/api/v1/users" -Headers $AuthHeaders
```

Create a disposable user:

```powershell
$NewUser = @{
    first_name = 'Ayesha'
    last_name = 'Khan'
    email = 'ayesha@example.com'
    phone = '03001234567'
    password = 'secure-password'
    role = 'supervisor'
    department = 'Site Operations'
} | ConvertTo-Json

$Created = Invoke-RestMethod -Method Post `
    -Uri "$BaseUrl/api/v1/users" `
    -Headers $AuthHeaders `
    -ContentType 'application/json' `
    -Body $NewUser
```

Delete only that disposable user:

```powershell
$DisposableUserId = $Created.user.id
Invoke-RestMethod -Method Delete `
    -Uri "$BaseUrl/api/v1/users/$DisposableUserId" `
    -Headers $AuthHeaders
```

Verify token revocation:

```powershell
Invoke-RestMethod -Method Post -Uri "$BaseUrl/api/v1/logout" -Headers $AuthHeaders

Invoke-WebRequest -Method Get `
    -Uri "$BaseUrl/api/v1/me" `
    -Headers $AuthHeaders `
    -SkipHttpErrorCheck
```

The final request must return `401 Unauthorized`.

## Legacy compatibility

The following original routes remain available with unchanged formats:

| Method | Legacy endpoint |
|---|---|
| `POST` | `/api/login` |
| `GET` | `/api/user` |
| `POST` | `/api/logout` |
| `GET` | `/api/users` |
| `POST` | `/api/users` |
| `DELETE` | `/api/users/{id}` |

After obtaining a fresh token, verify representative legacy endpoints:

```powershell
Invoke-RestMethod -Method Get -Uri "$BaseUrl/api/user" -Headers $AuthHeaders
Invoke-RestMethod -Method Get -Uri "$BaseUrl/api/users" -Headers $AuthHeaders
```

Use a new login token if the earlier token was revoked during the logout test.

## Automated checks

The automated suite uses SQLite in-memory and does not modify the configured development MySQL database:

```powershell
php artisan route:list --path=api -v
php artisan test
vendor/bin/pint --test
```
