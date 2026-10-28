# Frontend Phase 3.3 — Site Photos

Date: 2026-10-28

## Architecture and routes

The feature lives in `frontend/src/features/site-photos` with API, hooks, schemas, formatters, gallery/upload/viewer components, pages, and tests. Lazy workspace routes are `/site-photos`, `/site-photos/upload`, and `/site-photos/:id`; nested resources retain `?project={id}`. Navigation and route visibility use `photos.view` and `photos.create`; update, approval, visibility, and deletion actions use their exact effective permissions plus known policy state.

## Laravel contract and storage

Laravel exposes project-nested list, single upload, atomic batch upload, details, authenticated file stream, metadata update, review, visibility, and soft-delete endpoints. Batch upload accepts 1–10 images. JPEG, PNG, and WebP are supported up to 10 MB each. Required metadata is photo date and `morning|afternoon|evening`; caption, description, and a tenant/project-valid daily report link are optional.

Files use randomized names on `storage/app/private` through the `local` disk. Resources never expose storage paths. The authenticated file endpoint applies photo policy and returns private/no-store and nosniff headers. The frontend uses the existing bearer-authenticated Axios client, blob responses, and revocable object URLs. No storage visibility or filesystem permission was changed.

## Gallery, upload, and workflow

The gallery selects one authorized project because no company-wide photo API exists. It supports backend caption, date-range, period, approval-status, and pagination controls. Images lazy-load through protected blob requests with loading and broken-image fallbacks.

Upload supports one or up to ten files with shared metadata, object-URL previews, file removal, browser-side type/size/count validation, upload progress, server validation mapping, and duplicate-submit prevention. The browser constructs multipart boundaries. Successful uploads enter `pending` review state.

Authorized owners/admins can edit supported metadata. Company administrators with approval permission can approve or reject pending photos, publish approved photos to clients, and soft-delete photos. Laravel retains deleted private files by documented backend behavior.

## Integrations and role experience

- Project details display four recent project photos with view-all and upload actions.
- Daily report details list associated policy-safe photos; uploads can select a same-project daily report.
- Company administrators browse project galleries and receive permission/policy-compatible management controls.
- Assigned supervisors upload to assigned projects and edit only their own pending/rejected photos under policy.
- Clients have read-only access to approved and explicitly client-visible photos; review fields and management controls are omitted.

## Security

Query keys include company, user, project, filters, and report identity. Mutations invalidate photo, project, daily-report, and dashboard namespaces. No browser payload includes company, project ownership, storage path, approval, visibility, or reviewer fields. The backend validates MIME/content, file size, assignment, tenant scope, report association, and private-file access.

## Verification

- ESLint: passed.
- Vitest: passed — 17 files, 47 tests.
- Laravel `SitePhotosTest`: passed — 8 tests, 82 assertions using `Storage::fake('local')` and the isolated test database.
- Vite build: passed — 638 modules. Main JS 466.77 kB raw / 148.99 kB gzip; CSS 32.93 kB raw / 6.73 kB gzip.
- Manual real-browser, device, and screen-reader QA: not tested.

## Known limitations

- The main gallery requires project selection because no cross-project endpoint exists.
- The resource has no thumbnail derivative, so protected original images are used for cards.
- Next/previous viewer navigation is unavailable on the standalone detail route because Laravel returns no adjacent-photo context.
- Deleted photo files are intentionally retained by the backend; no cleanup was performed.
- Dashboard photo widgets were omitted because no reliable aggregate/recent cross-project endpoint exists.
