# Frontend Phase 3.2 — Daily Site Reports

Date: 2026-10-28

## Architecture and routes

The module lives in `frontend/src/features/daily-reports` with API, identity-scoped TanStack Query hooks, Zod contract, shared feature components, pages, utilities, and tests. Lazy workspace routes are `/daily-reports`, `/daily-reports/new`, `/daily-reports/:id`, and `/daily-reports/:id/edit`. Detail/edit links retain `?project={id}` because Laravel exposes only nested report resources.

The sidebar uses `site_reports.view`. Create, edit, delete, submit, review, and visibility controls combine exact effective permissions with known policy state for presentation; Laravel remains authoritative.

## Actual Laravel contract

All operations are under `/api/v1/projects/{projectId}/daily-reports`. Supported methods are list, create, show, update, delete, submit, review, and visibility update. List filters are title search, exact date, date range, status, server sorting, and pagination. There is no company-wide list or report-summary API.

Supported report content: report date, title, completed work, work in progress, issues, delays, delay reason, weather conditions, general notes, progress percentage, and workforce rows. Workforce uses the nine backend categories and nonnegative integer counts; Laravel computes `total_workers_present`. Server-owned tenant, project, workflow, visibility, and audit fields are never submitted.

Report date is required but has no backend past/future restriction. One non-deleted report per project/date is enforced. Attachments are not part of the report contract; site photos remain a separate module.

## Experiences and workflow

- Company administrators can select authorized projects, monitor report history, edit draft/changes-required reports, review another creator's submitted report, publish approved reports to clients, and soft-delete when their effective permissions allow it.
- Assigned supervisors can list/create reports for assigned projects, edit their own draft or changes-required reports, and submit them. Backend project policy rejects unassigned projects and editing another supervisor's report.
- Assigned clients receive read-only access to approved reports explicitly marked client-visible. Laravel filters list/detail access and suppresses review fields.
- Project details show the five most recent authorized reports, create/view-all actions, and direct report links without fetching reports for other projects.

## Security and cache isolation

Query keys include authenticated company ID, user ID, and project ID. Authentication already clears protected caches on logout/identity change. Mutations invalidate daily-report, project, and dashboard namespaces. Browser payloads never contain `company_id`, and nested project/report IDs are revalidated by Laravel tenant and project policies. Normalized 401/403/404/422/server errors are shown without raw exception output.

## Dashboard integration

No report widget was added. The backend provides neither reliable company-wide metrics nor a cross-project recent-report endpoint, and calculating them from paginated project lists would be misleading.

## Verification

- ESLint: passed.
- Vitest maintained suite: passed, 15 files / 40 tests. New coverage verifies list data/states/filter controls/presentation permissions and the report/workforce schema.
- Vite production build: passed, 624 modules. Main JS 464.67 kB raw / 148.44 kB gzip; CSS 30.38 kB raw / 6.32 kB gzip. Report routes are lazy chunks.
- Laravel `DailySiteReportsTest`: passed, 15 tests / 95 assertions using the isolated test environment.
- Full browser/device/screen-reader QA: not tested.

## Known limitations

- `/daily-reports` must select one project; company-wide history and summary cards are blocked by the missing backend endpoints.
- A report URL needs its project query parameter because the backend has no global report lookup route.
- Submitted/approved reports are intentionally locked by backend policy.
- Dashboard reporting widgets are blocked by missing accurate aggregate APIs.
- Site photos and attachments are outside Phase 3.2.
