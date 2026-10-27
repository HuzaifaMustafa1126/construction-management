# Step 2.1 — Project Management

## Scope and authorization

All endpoints use `/api/v1`, Sanctum authentication, current account/company checks, and tenant context. Spatie permissions decide whether an action is available; `ProjectPolicy` and explicit project assignment decide which records are accessible.

- Company Admin: all projects in the current company; CRUD, status, progress, team management, and budget visibility.
- Site Supervisor: assigned projects only. View is enabled by default. Updates require both `projects.update` and assignment; that update permission is not granted by default.
- Client: assigned projects only, read-only, with no budget or internal team endpoint access.
- Other/custom roles: require the applicable permission and an explicit assignment unless they are a Company Admin.

Cross-company and unassigned single-project requests return 404. Lists silently exclude inaccessible projects.

## Permissions

Existing permissions remain unchanged. Step 2.1 adds:

- `projects.assign`
- `projects.budget.view`

Both are granted to Company Admin by the non-destructive tenant-aware seeder. They are not granted to Site Supervisor, Client, or custom roles.

## Project CRUD

### List

`GET /api/v1/projects`

Optional query parameters: `search`, `status`, `date_from`, `date_to`, `sort`, `direction`, `page`, `per_page` (maximum 100). Search covers name and code. Supported sort fields are `name`, `code`, `status`, `start_date`, `expected_end_date`, and `created_at`.

### Create

`POST /api/v1/projects`

```json
{
  "name": "Central Tower",
  "code": "CT-001",
  "description": "Commercial tower",
  "location": "DHA Phase 5",
  "address": "Main Boulevard",
  "city": "Lahore",
  "state": "Punjab",
  "country": "Pakistan",
  "postal_code": "54000",
  "start_date": "2026-11-01",
  "expected_end_date": "2028-04-30",
  "budget": 250000000.00
}
```

The server assigns company, creator, company currency, `planning` status, and zero progress. Company/currency/creator/updater, status/progress, and team injection are rejected. Codes are case-insensitively unique among non-deleted projects in one company and may be reused after soft deletion.

### Read, update, archive

- `GET /api/v1/projects/{id}`
- `PUT|PATCH /api/v1/projects/{id}`
- `DELETE /api/v1/projects/{id}`

Updates accept the same descriptive/location/date/budget fields as creation. Team, status, and progress use dedicated endpoints. Delete is soft-only: assignments and audit history remain, users and companies are never deleted, and the project disappears from normal APIs.

Financial values use `DECIMAL(15,2)`. Budget cannot be negative. Expected and actual end dates must be on or after the effective start date. In Step 2.1, `actual_end_date` is manually supplied and is not automatically changed by status transitions.

## Status and progress

`PATCH /api/v1/projects/{id}/status`

```json
{ "status": "active" }
```

Allowed transitions:

- `planning` → `active`, `cancelled`
- `active` → `on_hold`, `completed`, `cancelled`
- `on_hold` → `active`, `cancelled`
- `completed` and `cancelled` are terminal

Completion does not automatically set progress to 100 or set an actual end date.

`PATCH /api/v1/projects/{id}/progress`

```json
{ "progress_percentage": 45 }
```

Progress is a manually maintained integer from 0 through 100.

## Project team and clients

`GET /api/v1/projects/{id}/team` requires `projects.assign` and team-management authorization.

`PUT /api/v1/projects/{id}/team` atomically replaces the complete assignment set:

```json
{
  "members": [
    { "user_id": 15, "assignment_type": "supervisor" },
    { "user_id": 16, "assignment_type": "member" },
    { "user_id": 17, "assignment_type": "client" }
  ]
}
```

An empty `members` array removes all assignments. Duplicate users, invalid types, foreign-company users, deleted users, inactive/suspended users, and role-ineligible types return 422 before any assignment is changed. `supervisor` requires the tenant `site_supervisor` role, `client` requires `client`, and `member` accepts `company_admin` or `site_supervisor`. Project assignment never changes Spatie roles.

## Overview and budget visibility

`GET /api/v1/projects/{id}/overview`

Returns dates, location, status, progress, and assignment counts. `budget` and `currency` appear only when the caller has `projects.budget.view` and record access. Clients do not receive financial fields.

## Responses and errors

- 200: successful read/update/archive
- 201: project created
- 401: unauthenticated
- 403: permission/action denied for an otherwise accessible record
- 404: missing, deleted, foreign-company, or unassigned project
- 422: validation, transition, or assignment eligibility failure

Resources expose safe project/team fields only; passwords, tokens, and unrelated personal data are excluded.

## Activity events

Logged events: `project.created`, `project.updated`, `project.deleted`, `project.status_changed`, `project.progress_changed`, `project.team_updated`, `project.member_assigned`, and `project.member_removed`. Logs contain company/project/target identifiers and safe changes, never passwords or tokens.

## Postman verification

1. Create disposable active Site Supervisor and Client users in one test company.
2. Create a disposable project as Company Admin.
3. Confirm another-company admin receives 404 for its ID.
4. Confirm unassigned Supervisor/Client receive 404.
5. Atomically assign both users; confirm each can list/view the project.
6. Confirm Client overview omits budget and team management returns 403.
7. Exercise valid and invalid status transitions and progress boundaries.
8. Submit an invalid replacement containing a foreign or inactive user, then confirm the old team remains.
9. Remove assignments with `{"members":[]}` and archive the disposable project.

Do not alter Users 5 or 11. Use disposable projects/users and clean them up only through normal soft-delete APIs.

## Database safety

Before migration, the live database was verified and backed up to `/tmp/construction_management_pre_step_2_1_20261018.sql` with SHA-256 `158093528e8ebb7853c84ebe583ec66645bd10d2892c3e827acf1294e21bae0b`.

Projects use a lowercase generated `active_code` plus `(company_id, active_code)` unique index. The generated value becomes null after soft deletion, providing MariaDB/MySQL-compatible active-row uniqueness and safe code reuse. No projects or assignments are automatically seeded.
