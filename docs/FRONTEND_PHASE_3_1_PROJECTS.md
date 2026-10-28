# Frontend Phase 3.1 — Projects Management

Date: 2026-10-28

## Architecture and routes

The Projects module lives in `frontend/src/features/projects` and contains its API export, TanStack Query hooks, Zod schema, feature components, pages, and tests. It reuses the centralized Axios client and shared design system. Implemented lazy workspace routes are:

- `/projects` — list/search/filter/paginate projects (`projects.view`).
- `/projects/new` — create a project (`projects.create`).
- `/projects/:id` — project overview, safe budget display, team, status, and progress.
- `/projects/:id/edit` — edit supported fields (`projects.update`).

Navigation uses the backend catalog permission `projects.view`. Action visibility uses `projects.create`, `projects.update`, `projects.delete`, and `projects.assign`; Laravel permissions, policies, assignments, and tenant scope remain authoritative.

## API contracts and behavior

The module integrates `GET/POST /projects`, `GET/PATCH/DELETE /projects/{id}`, dedicated status and progress PATCH endpoints, project overview, and project team GET/PUT. Create/update payloads contain only Laravel-supported fields and never send company, currency, status, progress, ownership, audit, or team fields. The server owns defaults and tenant assignment.

The list uses backend search, status filtering, and pagination. Client-side table sorting and pagination are disabled for server pages. Create navigates to the returned project; mutations invalidate all identity-scoped project queries and dashboard queries.

## Team, status, and progress

Team updates use the backend's full replacement array. The selector loads active company-scoped users and filters them by the exact role eligibility rules for `member`, `supervisor`, or `client`; the backend rejects inactive, foreign, duplicate, or ineligible users.

Status controls use the backend transition graph. Completed and cancelled are terminal. Progress accepts backend-supported integer values from 0 through 100 and never changes status automatically.

## Role visibility and tenant isolation

- Company administrators see all company projects only when they hold effective project permissions. Team management and deletion also require the backend company-admin policy.
- Supervisors see assigned projects only. Update actions still require `projects.update` and project assignment.
- Clients see assigned projects and policy-safe resource fields. Budget/currency are omitted unless the backend policy permits them; clients cannot mutate projects or access team administration.
- Query keys include company ID and user ID, and the existing authentication lifecycle clears protected caches on logout/identity change.
- Cross-company project IDs return tenant-safe 404 responses. Browser payloads cannot override company context.

## Tests and verification

- ESLint: PASSED.
- Vitest: PASSED — 13 files, 33 tests, including project list permissions/states/search and schema/transition contracts.
- Production build: PASSED with route-level project chunks.
- Laravel `ProjectManagementTest`: PASSED — 15 tests, 104 assertions, isolated SQLite `:memory:` test environment.
- Git whitespace validation: PASSED.

## Known limitations

- The backend has no project activity-feed endpoint, so no activity feed is rendered.
- Daily reports, site photos, materials, inventory, workforce, expenses, and finance UI are intentionally outside Phase 3.1.
- Team GET is intentionally restricted by the backend to project assigners/company administrators; non-managers see only the project resource's aggregate team summary.
- Full manual browser, responsive-device, and assistive-technology QA remains a release activity.
