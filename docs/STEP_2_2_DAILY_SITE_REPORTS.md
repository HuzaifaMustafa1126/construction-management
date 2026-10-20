# Step 2.2 — Daily Site Reports

## Scope and access model

All endpoints are under `/api/v1/projects/{projectId}/daily-reports` and require a Sanctum bearer token, an active account/company, tenant context, the relevant `site_reports.*` permission, and access to the parent project.

- Company Admin can access all reports in company projects, edit editable workflow states, review reports they did not create, control visibility, and soft-delete reports.
- Site Supervisor can access assigned projects, create reports, and edit/submit only their own `draft` or `changes_required` reports.
- Client can access assigned projects but sees only `approved` reports explicitly marked client-visible.
- Custom roles require the relevant permission and project assignment. A reviewer must not be a Client or the report creator.

Foreign-company, wrong-project, unassigned-project, and report-policy failures that could disclose a hidden report return 404.

## Permissions

The existing catalog already contains:

- `site_reports.view`
- `site_reports.create`
- `site_reports.update`
- `site_reports.delete`
- `site_reports.approve`

Company Admin has all five. Site Supervisor has view/create/update. Client has view only. Project assignment is always checked in addition to RBAC.

## Endpoints

- `GET /api/v1/projects/{id}/daily-reports`
- `POST /api/v1/projects/{id}/daily-reports`
- `GET /api/v1/projects/{id}/daily-reports/{reportId}`
- `PUT|PATCH /api/v1/projects/{id}/daily-reports/{reportId}`
- `POST /api/v1/projects/{id}/daily-reports/{reportId}/submit`
- `POST /api/v1/projects/{id}/daily-reports/{reportId}/review`
- `PATCH /api/v1/projects/{id}/daily-reports/{reportId}/visibility`
- `DELETE /api/v1/projects/{id}/daily-reports/{reportId}`

List filters: `search`, `date`, `date_from`, `date_to`, `status`, `sort`, `direction`, `page`, and `per_page` (maximum 100). Client filtering occurs in the database query, so hidden report counts are not exposed.

## Create a draft

```json
{
  "report_date": "2026-10-19",
  "title": "Foundation Work Progress",
  "work_completed": "Foundation reinforcement completed in Zone A.",
  "work_in_progress": "Concrete preparation in Zone B.",
  "issues": "Equipment maintenance required.",
  "delays": "Concrete delivery arrived late.",
  "delay_reason": "Supplier transport delay.",
  "weather_conditions": "Clear",
  "general_notes": "Work follows the revised plan.",
  "progress_percentage": 35,
  "workers": [
    { "worker_type": "mason", "workers_present": 8 },
    { "worker_type": "helper", "workers_present": 12 },
    { "worker_type": "electrician", "workers_present": 3 }
  ]
}
```

The server assigns company/project/creator, starts the report as `draft`, sets client visibility to false, and calculates `total_workers_present` from the workforce rows. It never changes overall project progress.

Allowed worker types are `mason`, `helper`, `electrician`, `plumber`, `carpenter`, `painter`, `steel_fixer`, `operator`, and `other`. Counts are non-negative integers; duplicate categories are rejected.

Only one active report is allowed per project/date. A generated nullable `active_report_date` with a `(project_id, active_report_date)` unique index implements soft-delete-aware uniqueness on MySQL/MariaDB. A date can be reused after soft deletion.

## Editing and workforce replacement

PUT/PATCH accepts the draft content fields and optional `workers`. When workers are supplied, the entire summary is replaced transactionally and the total is recalculated. Invalid input is rejected before existing content or workforce rows change.

Company/project IDs, calculated totals, status, visibility, creator/submission/review metadata, and review notes cannot be directly changed through create/update.

## Submission workflow

Submit with an empty JSON body:

`POST .../{reportId}/submit`

Allowed transitions:

- `draft` → `submitted`
- `changes_required` → `submitted`

Title and completed-work content are required. Resubmission clears stale review metadata and disables client visibility. Invalid transitions return 422.

## Management review

Approve:

```json
{ "action": "approve", "review_notes": "Report verified." }
```

Request changes:

```json
{ "action": "request_changes", "review_notes": "Correct the workforce count." }
```

Only `submitted` reports can be reviewed. Requesting changes requires notes. Reviewers need `site_reports.approve`, project access, and cannot be the report creator. Review history is preserved through activity logs.

## Client visibility

```json
{ "is_client_visible": true }
```

Only authorized Company Admins can change visibility. A report can be enabled only after approval; visibility can later be disabled. Approval does not automatically publish a report. Client resources omit internal review metadata.

## Deletion policy

DELETE soft-deletes the report. Workforce rows and activity history remain for historical integrity. Deleted reports are excluded from lists/details and can never be seen by clients. Projects, users, and worker history are not deleted.

## Responses

- 200: successful read/update/workflow/delete
- 201: draft created
- 401: unauthenticated
- 403: insufficient action permission on an otherwise accessible resource
- 404: missing, deleted, foreign, wrong-project, or undisclosable resource
- 422: validation or invalid workflow transition

## Activity events

Recorded events are `site_report.created`, `site_report.updated`, `site_report.submitted`, `site_report.approved`, `site_report.changes_requested`, `site_report.visibility_changed`, and `site_report.deleted`. Properties contain safe company/project/report identifiers and changes, never passwords or bearer tokens.

## Postman verification and cleanup

1. Create disposable Supervisor and Client users and a disposable project.
2. Assign both users through the Step 2.1 team API.
3. Create a draft as Supervisor and verify the server-calculated workforce total.
4. Confirm an unassigned Supervisor gets 404 and Client creation is denied.
5. Confirm Client lists zero reports through draft/submitted/changes-required/unpublished-approved states.
6. Submit, review using a different authorized user, then enable visibility.
7. Confirm Client can view the published report but receives no review notes.
8. Disable visibility, request changes on a separate submitted report, correct and resubmit it.
9. Soft-delete disposable reports and the disposable project through normal APIs.

Do not alter Users 5 or 11 during manual tests.

## Database safety

Before migration, the live database was backed up to `/tmp/construction_management_pre_step_2_2_20261019.sql` with SHA-256 `e90c8407771708bbdda53c0ced33f1fa52758bacf99036080de2e0684e0e7aed`. No projects, reports, assignments, or workforce rows are automatically seeded.
