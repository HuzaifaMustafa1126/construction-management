# Frontend Architecture

## Runtime

React 18 and Vite provide the SPA shell. React Router owns public, guest, platform, authenticated, and permission-aware routes. TanStack Query owns server state, React Hook Form and Zod own forms, and Zustand stores browser-only theme preferences.

The active application is JavaScript/JSX only. Runtime modules use `.js`, React components use `.jsx`, and the project has no TypeScript compiler configuration or direct TypeScript tooling dependencies. Historical files under `legacy-template/` are outside this maintained-source rule and are never imported.

## Source organization

- `src/api/`: the single Axios client and shared endpoint adapters.
- `src/components/`: reusable UI, table, and layout building blocks.
- `src/features/<feature>/`: pages, components, context, tests, and utilities owned by a business capability.
- `src/layouts/`: company and platform application shells.
- `src/routes/`: route guards and the complete lazy route registry.
- `src/lib/theme/`: shared theme state, provider, and CSS tokens.
- `src/App.jsx`: provider and router composition only.
- `src/main.jsx`: application bootstrap and global stylesheet imports.

The application currently has active features for auth, platform administration, dashboard, company, users, roles, departments, clients, projects, daily reports, site photos, profile, and settings. Business features own API exports, identity-scoped query hooks, runtime schemas, components, and lazy route pages. Empty feature placeholders are not created.

## Routing and authorization

`src/routes/AppRoutes.jsx` is the route registry and preserves all established URLs. Business pages remain lazy-loaded. `ProtectedRoute`, `GuestRoute`, and `PlatformRoute` provide presentation gating; backend authorization remains authoritative. Navigation uses effective permission names returned by `/me`, never role-name-only checks.

## State and API boundaries

`src/api/axiosClient.js` is the only active Axios instance. It attaches the session token and normalizes failures. `src/api/endpoints.js` defines shared resource calls without changing existing payloads or response handling. Auth and company identity live in `features/auth/context/AuthContext.jsx`; identity changes clear protected query cache.

Project list/detail/team query keys include the authenticated company and user IDs. `/projects`, `/projects/new`, `/projects/:id`, and `/projects/:id/edit` are lazy company-workspace routes gated by their corresponding effective permission; Laravel policies additionally enforce assignment and tenant access. The portfolio supports card/list presentation over the same server-filtered page, including project-manager filtering. Archived project listing and restoration require `projects.restore`; no permanent-delete UI exists. Project details retain Overview integrations and expose the authorized Team view as a separate tab.

Client list/detail query keys are company/user scoped. `/clients` CRUD routes use exact `clients.*` presentation permissions. Project create/edit uses the separate server-authorized client lookup endpoint so authorized project editors can search the complete tenant client directory without receiving general `clients.view` access.

Daily report query keys include company/user identity and project scope. The dashboard also uses the read-only authorized report collection, keyed by project/date/page parameters. `/daily-reports` now browses the authorized global collection with All projects / All time defaults; details and edit URLs retain that project as a query parameter so the nested resource can be addressed without inventing a global endpoint. Create, update, submit, review, visibility, and delete mutations invalidate identity-scoped report, project, and dashboard caches.

Site photo query keys likewise include company, user, and project identity. `/site-photos`, `/site-photos/upload`, and `/site-photos/:id` are lazy workspace routes. Private image bytes are requested through Laravel's authenticated file endpoint, converted to short-lived object URLs, and revoked on replacement/unmount. Project and report integrations query only their nested photo resources.

## Legacy boundary

Materials use lazy catalog and project-inventory routes. Their query keys include company/user identity plus project and inventory scope; Laravel ledger balances remain authoritative.


The old unrouted WowDash demo pages, layers, helpers, charts, duplicate services, and `MasterLayout` are preserved in `legacy-template/` outside `src`. Nothing in the maintained application imports that archive. It is excluded from active linting and bundling and can be inspected or selectively migrated later.

The maintained workspace shell uses a fixed desktop sidebar and top bar, a permission-filtered mobile bottom navigation, and an authorized Daily Report shortcut. Shell structure was adapted from the archived `MasterLayout` patterns, while its mock navigation, data, service layer, icon system, and theme implementation remain excluded. Shared shell sizing and colors are semantic theme tokens; page content remains feature-owned.

The company dashboard top section owns its heading, authorized project/period filters, and six-card KPI presentation under `src/features/dashboard`. Dashboard query keys include company, user, project, and period scope. Total and active project counts use server pagination metadata and are current snapshots; project scope applies to those counts. Period scope applies to Site Reports and the project-nested material-consumption summary. Unsupported labour attendance, client receivables, and contract-value metrics remain explicit unavailable states rather than client-derived estimates, and their cards follow the corresponding effective permissions.

## Verification boundary

The maintained Vitest command covers authentication, route authorization, platform company editing and administrator setup, company dashboard behavior, company profile permissions and refresh, users/roles/departments presentation authorization, profile/password flows, settings persistence, permission selection, and shared modal/form accessibility. Laravel feature tests remain the authoritative verification for tenant isolation and mutation authorization.

## Design Foundation — Phase 1: placement and Phase 1.1 review

SearchableSelect.jsx in src/components/ui owns the controlled combobox, keyboard handling and portal; AppUI.jsx re-exports it. modalScope.js supplies the shared modal context and stack used by AppModal and SearchableSelect. The top AppModal owns focus containment and Escape; its selects portal within the dialog subtree. Page menus use the lower page-dropdown layer and are dismissed when a modal opens. FilterBar owns its optional responsive grid layout. Projects list composes these shared primitives without a feature-local control system or new library. Existing native AppSelect remains available. Form integrations use Controller when adopting the controlled combobox.

index.html no longer loads legacy demo styles or Bootstrap; it retains Inter as an explicit font link. main.jsx imports tokens, theme definitions, then maintained component CSS. React Toastify retains its package stylesheet. Maintained CSS owns base box sizing, body typography, control inheritance and heading defaults. Archived components remain outside the runtime graph. Only Projects list gets page-specific composition changes in this phase. Visual rules belong in DESIGN_SYSTEM.md; preference logic is unchanged.

Phase 1.1 changes shared dropdown/modal integration and overflow containment only; Projects list remains the reference screen. No Dashboard or Project Detail layout restructuring is included. Source, automated and browser evidence are recorded separately in [DESIGN_FOUNDATION_VERIFICATION.md](../docs/DESIGN_FOUNDATION_VERIFICATION.md).

## Project client combobox

The shared ProjectForm uses Controller to connect ClientSelector to numeric-or-null client_id, including ref and blur for form focus/touched behavior. ClientSelector composes the existing SearchableSelect and useClientLookup; it supplies the selected client from project data and never marks selection dirty merely by fetching options. It debounces trimmed search by 250ms and disables secondary local filtering. Lookup query keys retain company/user identity plus search and bounded limit; React Query passes AbortSignal to the centralized Axios adapter so old requests cannot replace current query results. During debounce/refetch, stale options are hidden.

The existing lookup supports name/company search and a maximum limit of 50, with no pagination/cursor contract. Initial queries omit selected_id because that parameter alone restricts the response to the selected client. Current selection is supplied separately through selectedOption. This phase adds neither backend access nor a general clients.view requirement.

## Dashboard project progress preview

DashboardProgressOverview sits below the KPI grid and above Recent projects. Its separate useDashboardProgress query uses company/user/project-scoped dashboard keys with no period scope. All-project mode requests only page 1 with per_page=6, sort=created_at and direction=desc, and displays the returned total as a labelled preview. Selected-project mode uses the existing authorized project detail endpoint. Both read ProjectResource.progress_percentage directly, preserving zero and unavailable values. No chart pagination loop, aggregate inference, new endpoint or dependency is introduced; the existing project selector and KPI queries are unchanged. ApexCharts exists only in archived source and is absent from installed dependencies, so the chart uses feature-local HTML/CSS, adapting narrow rounded columns and dashed grid lines without legacy imports. Loading, empty, forbidden and request-error states precede chart rendering; full-name values are also available in a native details list.

## Dashboard financial summary availability

DashboardFinancialSummary is gated by effective expenses.view and sits beside Project Progress Overview when content width permits, otherwise below it. Current routes, controllers and resources expose no expense listing or complete category spending aggregate. ProjectResource.spent remains a literal placeholder and is never read by this widget. The card therefore renders only an unavailable message and explains that project/period filters do not apply. It performs no requests or financial calculations, creates no financial cache, and cannot display loading, zero or request-error states in the absence of a source. Identities without expenses.view receive no financial card. No accounting inclusion, currency conversion or category rules are inferred.

## Dashboard recent site photos

DashboardRecentSitePhotos reuses useSitePhotoCollection for GET /site-photos: optional project_id/date_from/date_to, three rows per page, fixed photo_date DESC/id DESC ordering. Site-photo collection keys retain company/user plus project/date/page parameters and remain under the existing mutation-invalidation root. Axios consumes AbortSignal; a keyed inner component resets page before scope changes. Both All projects and a selected project load automatically, with no project fan-out. Period/date validation and loading, empty, denied and request-error states are distinct. Authorized project metadata supplies every item's project name, nested detail URL and ProtectedPhoto project ID. Protected blob delivery, object-URL cleanup, decode fallback and identity-based remounting remain unchanged. Compact server pagination exposes older photos. View all is only offered for a selected project because the standalone gallery remains project-only.
## Dashboard site reports across projects

The existing DashboardTodaySiteReports.jsx module now renders Site Reports. It uses useDailyReportCollection and GET /daily-reports for both all-accessible-project and selected-project modes, with optional date_from/date_to from the dashboard period, four rows per server page and fixed report_date DESC/id DESC ordering. The standalone list supports all-project scope; dashboard View all carries project/period/date scope into the list while widget Previous/Next controls remain unchanged. Rows show a linked title, project, report date, optional creator, a compact existing summary excerpt and unchanged ReportStatusBadge; the title link uses each row's project ID.

Collection keys extend the existing daily-reports company/user root with collection and project/date/page parameters, so current mutations already invalidate them. AbortSignal reaches Axios. A keyed inner component resets pagination before new scope requests, including period changes; previous-data placeholders are not used. All time omits date filters and remains enabled without project selection. For bounded periods only, invalid/missing dates disable requests and show a valid-period prompt. Loading, empty, error and denied states remain distinct. The collection endpoint scopes report ownership and project access using TenantContext and Project.accessibleTo before counts, additionally retaining nested collection client visibility. It uses a compact preview resource and does not modify existing workflows or nested endpoints. Photos, protected images, charts, KPIs and lower dashboard sections are unchanged.

## Dashboard scope defaults and aligned widgets

Dashboard project and period state is local React state, with no saved filter restoration or auto-selection of the first project. New mounts default to empty project ID (All projects) and period=all (All time); deliberate live selections persist until the user changes them or the page unmounts. The existing This month/Custom controls remain. Material consumption accepts All time by omitting date filters from its existing selected-project summary; snapshot counts and unsupported KPI states are unchanged. Photos and reports share inclusive bounded-period scope and omit dates entirely for All time. Their selected project names may come from authorized selector data for scope text; individual item identification and navigation always use resource project metadata.

The shared dashboard-only row stretches two equal-width flex-column cards on desktop. The tallest content determines row height without fixed heights or JavaScript measurements; pagination uses auto top margins to sit at the bottom. The stacked mobile row retains natural independent heights. No standalone gallery/report layout, database record, schema or workflow is changed.

## Dashboard material stock status

DashboardMaterialStock sits below the completed Photos/Reports row and above Recent projects. useInventoryCollection and inventoryApi.collection consume the read-only GET /inventory collection with optional project_id and four rows per page. Keys extend the existing inventory company/user root with collection parameters, so existing inventory/material/document mutations invalidate the preview. AbortSignal reaches Axios and a keyed inner component resets pagination synchronously on project/identity changes. Dashboard periods are neither query keys nor parameters because current stock is a snapshot. Both All projects and selected-project modes retain project labels and project-scoped inventory navigation; View all is omitted for All projects because the inventory page is project-only. Effective inventory.view gates presentation and requests; the backend additionally enforces the existing client exclusion and project membership rules. No transaction-page sums, unit aggregation, record mutations or stock actions are introduced.

## Dashboard active projects

DashboardActiveProjects replaces the basic Recent/Your projects section without retaining a duplicate list. All-project mode requests GET /projects with status=active, per_page=5, page, sort=code and direction=asc; backend assignment/tenant/status restrictions precede pagination. Code is an existing supported ordering and normal active project codes are tenant-unique; the API exposes no secondary unique-ID sort parameter, so no unsupported tie-breaker is sent. Selected-project mode uses GET /projects/{id} because the list API has no exact project_id filter: the authorized single record is shown only for status=active, otherwise an explicit inactive-selection empty state is shown. No general paginated page is filtered locally.

useDashboardActiveProjects keys extend the projects company/user root with dashboard-active, project and page. Existing project mutations invalidate the root; optional AbortSignal now passes through the existing list/get adapters. Scope-keyed pagination resets synchronously, previous-data placeholders are absent, and dashboard periods are excluded. The shared DataTable has client search/sort/pagination disabled. ProjectStatusBadge and formatReportDate are reused; budgets follow existing project currency-formatting conventions and require projects.budget.view plus resource-authorized fields. No placeholder spending is consumed. View all is omitted because the standalone Projects page does not initialize an active filter from its URL. Read-only View links use the existing project detail route. Other widgets and Quick actions remain unchanged.

## Upcoming Milestones - Step 13 deferred

Source inspection found no maintained milestone/schedule model, migration, resource, endpoint, view permission, completion/status definition or navigation destination. No authoritative incomplete milestone collection exists to filter by a due-date window. Project expected_end_date/actual_end_date and ordinary work reports are not substituted for milestones. The legacy template also supplies no authoritative milestone capability.

Step 13 is deferred without adding a permanently unavailable card, scheduling schema, mock records, endpoint or dashboard request. Active Projects retains its existing full-width layout and current user edits. No 14-calendar-day window, timezone-boundary interpretation, urgency category or milestone status rule is implemented until an authoritative milestone capability defines those contracts. Existing widgets and Quick actions remain unchanged.

## Dashboard cleanup - Step 14

DashboardActiveProjects restores the previously commented server-pagination controls, resolving unused AppButton, setPage and meta lint failures. The existing five-row active-project request, server ordering, identity/project keys, cancellation and scope-reset behavior are unchanged. All-project results expose response-backed navigation/counts; selected-project mode hides pagination. CompanyDashboard adds a scoped composition wrapper and dashboardLayout.css for consistent section/header/footer spacing and compact permitted Quick actions, without modifying shared table/control behavior or existing widgets' data. Photos/Reports remain equal-height only at their desktop breakpoint; Financial Summary remains naturally compact. Milestones remain deferred. No backend, permission, API-contract or record changes are part of this cleanup.

## Daily Reports list integration - Step 16

DailyReportsPage now consumes useDailyReportCollection with submitted title search, status, optional project_id and date_from/date_to, and 15 rows per server page. Existing collection keys/cancellation and mutation invalidation are reused. Local title draft is distinct from the single committed search filter; changing another filter never submits unsubmitted text. Project/status/period/date changes and search submissions reset page. Explicit valid URL filters initialize the page, internal changes serialize them, and external URL/back-forward changes restore committed filters. Invalid live custom ranges show linked errors and disable requests; invalid explicit URL filter values use defaults. The list omits tables during denied/error states and handles an out-of-range page without impossible result ranges.

useReportProjectOptions owns only the remote project combobox lookup, using the existing project list search and detail adapters, identity-scoped project keys, 250ms debounce, AbortSignal and a bounded 50-row response with refine-search notice. Stale search options are hidden. projects.view gates lookup requests independently from daily_reports.view. Shared SearchableSelect and existing controls retain their focus/theme behavior.

The shared DataTable shows report title/summary, authorized project, report date, creator, stored worker total and ReportStatusBadge. Existing View/Edit/Delete rules and ConfirmDialog remain; URLs and deletion payloads use row.project.id, including All projects mode. Create remains permission/client-gated and may carry an explicitly selected project. Create/edit/detail pages and all other feature modules remain unchanged. Dashboard Site Reports View all is now available in both scopes and uses the shared report-list URL builder with current project/period/dates. Existing dashboard verification limitations remain outstanding.

## Create Daily Report - Step 17

DailyReportForm adds an opt-in sectioned presentation used only by CreateDailyReportPage. DailyReportCreateSections composes shared SectionCards and existing registered fields inside that same form. The default branch, EditDailyReportPage, initialization, update label/destination and legacy workforce markup are retained. Create removes its obsolete first-100-project wrapper request and uses ReportProjectField with the existing useReportProjectOptions server search, identity-scoped queries, cancellation, debounce, bounded results and refine-search notice. A URL-fixed project remains fixed and registered; its authorized label can load independently without clearing the field. No first project is selected automatically and report_date remains blank by default.

WorkersPresentField's opt-in sectioned branch uses Controller for enum-backed searchable categories and useWatch to disable categories currently used by other rows. Existing category values, useFieldArray IDs/add/remove behavior, append defaults (first unused category with explicit zero after Add category), numeric registration and server distinct-category rules remain unchanged. Initial workers remain empty. The Edit branch retains its existing native category selector and visual layout; its data/submission behavior is unchanged. No derived worker total or additional payload field is submitted.

The existing dailyReportSchema/reportPayload, project-nested create call, client exclusion/route permission, pending handling, success toast/detail destination, cancellation destination and server-error mapping remain intact. Save Report is still the operation label. Failed saves retain entered values because no form reset/remount is introduced. There was no unsaved-change prompt in the original form, and none is added. No backend, list/detail page, schema, workflow or dependency changes occur in this phase.

## Linked client workflow and unified directory

ClientsPage now composes one typed server-backed ClientDirectory; account/contact tabs are removed. The read-only directory includes business Clients and explicitly account-only client-role Users only where users.view authorizes account data. Row type determines routes/actions, so user IDs never enter Client CRUD. No business Client is fabricated or inferred by matching email/name. The existing role-user read endpoint remains compatible but is no longer the directory presentation. The unused separate-account component/hook were removed.

ClientForm uses contact-only clientSchema for editing and createClientSchema for new linked creation. Create adds the shared-section login email/password/confirmation fields and accessible per-field visibility controls through ClientAccountFields. ClientLoginAccountDialog mounts only for a deliberately chosen unlinked contact; clientLoginSchema validates its prefilled name/email and new credentials. Both forms retain non-sensitive values on failure, clear password fields after failed submissions, keep credentials in transient form/request memory only and guard duplicate submissions with a synchronous ref plus shared busy buttons. No browser persistence is used.

useClientAccountOptions exposes the backend role/permission/schema decision; the UI does not assume clients.create alone permits user provisioning. Both new and existing-contact actions enforce the exact respective client permission plus users.create, roles.assign and the server role-grant ceiling. The Add Client entry remains available to actors with the required base provisioning permissions. Its combined form stays visible during deployment/role availability failures, with a disabled fieldset and disabled save action, plus a precise reason. Credentials cannot be entered until creation is available; Cancel remains usable. Existing-contact actions remain visible but disabled when deployment is unavailable. Contact email edits remain independent of login email. Client details and directory show authoritative linked state and users.view-gated safe account metadata.

useClientDirectory includes company/user/filter/page identity under the company/users invalidation prefix, accepts AbortSignal, and does not use previous-scope placeholders. Existing Users mutations therefore invalidate account status in this directory. useClientMutation invalidates the clients root (including detail/options/project lookup), company/users, projects and dashboard roots. Contact/detail/options keys retain company/user scope; contact list/detail and unified-directory keys also distinguish users.view capability so a permission change does not reuse account-bearing rows. The UI explicitly gates account metadata as well. Submitted search, server ordering/counts/pagination, separate error/empty states and contained table scrolling are preserved.

The backend ClientService follows existing user creation defaults, hashing, team-scoped role assignment and safe audit conventions; the existing UserAccountService handles revocation/deletion rather than creation and is not repurposed. The unique nullable clients.user_id relationship is prepared in a migration, not applied. See ../docs/CLIENT_ACCOUNT_PROVISIONING.md for implementation files, transaction boundaries, read-only versus isolated runtime evidence and remaining limits.

### Clients directory presentation-only refinement

ClientsPage, ClientDirectory and directory-scoped additions in clients.css compose the existing PageHeader, SectionCard, AppInput, FilterBar, DataTable, badges and actions. The separate Entry type column is folded into Client/Account cells. Account-only rows still route solely to Users; business-client actions, confirmation/link dialog, permission predicates and backend availability gating are preserved. Server search remains explicitly submitted with page reset, and sorting/counts/pagination/recovery remain server-owned. No provisioning, API, query-hook, schema, relationship or grant changes are part of this refinement.
