# Step 2.3 — Site Photos API

All endpoints use `/api/v1`, require `Authorization: Bearer <TOKEN>`, and return JSON except the authenticated file endpoint. Photos are stored on Laravel's private `local` disk under a server-generated company/project path. Raw paths and stored filenames are never returned.

## Endpoints

| Method | Endpoint | Permission | Purpose |
|---|---|---|---|
| POST | `/projects/{id}/photos` | `photos.create` | Upload one image |
| POST | `/projects/{id}/photos/batch` | `photos.create` | Atomically upload 1–10 images |
| GET | `/projects/{id}/photos` | `photos.view` | Paginated gallery |
| GET | `/projects/{id}/photos/{photoId}` | `photos.view` | Safe metadata |
| GET | `/projects/{id}/photos/{photoId}/file` | `photos.view` | Stream private image |
| PUT/PATCH | `/projects/{id}/photos/{photoId}` | `photos.update` | Update metadata |
| POST | `/projects/{id}/photos/{photoId}/review` | `photos.approve` | Approve or reject |
| PATCH | `/projects/{id}/photos/{photoId}/visibility` | `photos.approve` | Publish/unpublish for clients |
| DELETE | `/projects/{id}/photos/{photoId}` | `photos.delete` | Soft delete |
| GET | `/projects/{id}/daily-reports/{reportId}/photos` | `site_reports.view`, `photos.view` | Report photos |

Project authorization is enforced in addition to permissions. Company admins can manage company-project photos. Assigned supervisors can view/upload and edit only their own pending or rejected photos. Assigned clients can only view/download approved and client-visible photos. Inaccessible company, project, report, and photo combinations return `404`.

## Uploads

Use `multipart/form-data`. A single upload accepts `photo`; a batch accepts `photos[]`. Shared fields are `caption`, `description`, `photo_date` (`YYYY-MM-DD`), `time_category` (`morning`, `afternoon`, or `evening`), and optional `daily_site_report_id`.

Example single form fields:

```text
photo=@foundation.jpg
caption=Foundation Work
description=Reinforcement completed in Zone A
photo_date=2026-10-20
time_category=morning
daily_site_report_id=15
```

JPEG, PNG, and WebP are accepted up to 10 MB each. SVG, empty, executable, disguised, unsupported, and oversized files are rejected with `422`. Batch requests are limited to 10 and validate completely before storage. Stored files and rows are rolled back together if processing fails.

The API rejects client-provided ownership, approval, visibility, reviewer, filename, disk, path, MIME, and size fields. A new photo is always `pending` and not client-visible.

## Gallery and workflow

Gallery query parameters: `search`, `date`, `date_from`, `date_to`, `time_category`, `approval_status` (management only), `sort` (`photo_date` or `created_at`), `direction`, `page`, and `per_page` (maximum 100). Client pagination counts only records clients can see.

Metadata updates accept only `caption`, `description`, `photo_date`, `time_category`, and `daily_site_report_id`. Image replacement is not supported.

Review body:

```json
{"action":"approve","review_notes":"Photo verified."}
```

```json
{"action":"reject","review_notes":"Please upload a clearer photo."}
```

Only pending photos may be reviewed; rejection notes are mandatory. Approval does not publish. Publish an approved photo with `{"is_client_visible":true}` and unpublish with `false`. Report approval/visibility and photo approval/visibility remain independent.

## Authenticated image display in React

Fetch the file with the bearer token, convert the response to a Blob, create an object URL, and revoke it when the component unmounts:

```js
const response = await fetch(fileEndpoint, { headers: { Authorization: `Bearer ${token}` } });
if (!response.ok) throw new Error('Photo unavailable');
const objectUrl = URL.createObjectURL(await response.blob());
// Use objectUrl as <img src>; later call URL.revokeObjectURL(objectUrl).
```

Responses use the detected image MIME, inline safe filename, `Cache-Control: private, no-store`, and `X-Content-Type-Options: nosniff`. There are no public links.

## Postman security checks

Use disposable projects and users; do not alter users 5 or 11. Verify unauthenticated download is `401`; cross-company, wrong-project, unassigned, hidden-report, pending, rejected, and unpublished client requests do not reveal resources. Verify protected multipart fields produce `422`.

## Retention and cleanup

Deletion is metadata-only soft deletion. The private original is retained for audit/history and cannot be reached through normal APIs. Permanent deletion is intentionally outside Step 2.3. A future retention job should select expired soft-deleted records, verify the configured private disk and tenant-prefixed path, delete the file, then permanently remove metadata under an audited policy. Never expose `storage/app/private` through `storage:link`.
