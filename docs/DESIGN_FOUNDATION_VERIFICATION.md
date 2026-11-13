# Design Foundation — Phase 1: verification report

## Phase 1 historical evidence (2026-11-11)

The following records describe the earlier implementation turn, not a rerun in Phase 1.1.

### Audit and cascade

The old index.html loaded Bootstrap, legacy style.css, extra.css and unused demo libraries. Legacy style.css defines --h1 up to 4.5rem and --h2 up to 3.75rem, forcing them onto headings with !important and defeating maintained 28px/18px rules. These runtime links are removed, rather than countered with overrides. Inter remains explicitly loaded with system fallbacks. Shared CSS previously mixed fixed 9px/14px radii, 24px padding, 650 button weight and inherited control typography. Projects search lacked a visible label; its flex filter bar did not align labelled fields with the Search button. No screenshots accompanied the supplied text attachment; rendered verification is still required.


### Legacy references and phase boundary

Reviewed WowDash DefaultInputs, DefaultCard and CustomDropdown. Adapted label-above-field grouping, restrained bordered cards and padded option rows as presentation patterns. No legacy implementation, icon package, Bootstrap behaviour or stylesheet was imported. Legacy dropdowns did not provide an editable accessible combobox. The new numeric scale comes from this phase's brief, not a claim that legacy typography was correct.

Projects list is the reference screen: labelled search, searchable status/manager filters, aligned controls, compact section actions, h3 names, muted codes, balanced metadata and existing permitted actions. Card/list switching, archives, server pagination, finance visibility and effective permission checks remain intact. No other page-specific layout is redesigned. Lint/build cannot establish rendered clipping, actual contrast, wrapping or touch/keyboard behaviour; those require browser review.


### Phase 1 verification record (2026-11-11)

The existing suite produced 45 passing and 4 failing tests. All four failures were reproduced using committed source loaded in memory: Projects edit permission fixture mismatch, Daily Reports permission fixture mismatch, Dashboard's ambiguous Tower Site text query, and Settings localStorage availability under the current Node environment. Permission rules and theme persistence were not changed to silence these failures.

An in-memory DOM check passed combobox opening, trimmed partial matching, numeric value integrity, selection, disabled-option skipping, retained labels, optional clearing, required clearing rejection, Escape/outside/Tab closing, loading and request-error states. No test or browser automation files were created. Browser tooling and supplied screenshots were unavailable, so desktop/tablet/360px rendering, real menu scrolling/positioning, light/dark and accent combinations, and representative Dashboard/Project Detail appearance remain unverified. Existing feature-specific styles outside the reference screen remain intentional exceptions; review Dashboard shared-style effects next, without starting that redesign in this phase.


## Phase 1.1 review (2026-11-11)

Outcome: PARTIAL pending browser verification.

### Source checks

- Frontend responds with HTTP 200 at http://localhost:5173/projects; listeners also exist on API port 8000. HTTP availability does not demonstrate an authenticated route rendering.
- No browser-control tool or connected document session is available. No browser debugging listener was found on port 9222. The agent cannot inspect computed styles, perform manual interactions, capture screenshots, or use an authenticated authorized option set. No accounts were reset and no business records were changed.
- Existing shared type sizes, filter grids, page/card padding, borders and surface rules were inspected. This does not establish actual appearance at 1440px, 1024px or 360px.
- Fixed a globally high body portal that could cover newer dialogs. Page menus now use a layer below modals; modal menus portal inside their owning dialog with shared stack/focus handling. Older menus dismiss when a newer AppModal mounts. Keyboard option navigation now changes only menu scrolling. Disabled selects dismiss rather than reopening stale search state when re-enabled.
- Source colour calculation found accent text on the dark surface at 3.43:1 (blue), 3.54:1 (green), and 3.43:1 (orange). Added a separate dark-mode accent text token for links/outline labels without changing filled action backgrounds. These are colour-value calculations, not browser contrast measurements.
- Added containment for long project codes, shared page-heading flex items and table scroll wrappers. Existing rules were changed directly rather than layered with duplicate overrides.
- Dashboard and Project Detail consumers were reviewed in source only. Their pages were not edited or restructured; concrete retained component values and migration status are now documented. Existing user changes in project pages/components/hooks and .vscode were preserved.

### Automated checks

- npm run lint: PASS.
- npm run build: PASS; dependency comment-annotation warnings from Zod were non-blocking.
- git diff --check: PASS.
- Existing shared-control suite (AppUI.test.jsx): 2 tests PASS. The full suite was not rerun in Phase 1.1; earlier Phase 1 counts above remain historical.
- In-memory synthetic DOM checks: PASS for trimmed matching, numeric ID integrity, selection/clearing, disabled-option skipping, Escape/outside/Tab closing, dismissal on disable, page-menu dismissal when a dialog opens, portal containment inside the owning modal, Tab/Shift+Tab wrapping, top-dialog-only Escape/focus, unique dialog title IDs, and focus restoration. These used synthetic component fixtures, not authorized application records. No test/automation files were created.
- CSS parsing and declared typography/layer token checks: PASS. This is not computed-style verification. Calculated new dark accent-text contrast against #111827: blue 6.98:1, green 10.18:1, orange 7.84:1; actual rendered themes remain unverified.

### Browser checks

None completed. All requested viewport widths (1440px, 1024px, 360px), card/list rendering, pagination presentation, computed typography, real dropdown positioning/scrolling, actual authorized options, modal interaction and screenshots remain pending. Light/dark with blue/green/orange accents also remain pending. No READY claim is made from lint/build or synthetic DOM checks.

### Scope

No backend/database, routes, permissions, API payloads, tenant scope or business actions were modified. No dependencies, skills, tests, specs, E2E or browser-automation files were installed, created or modified. Stop after this Projects review; no next-page redesign is authorized by this phase.

### Files changed by Phase 1.1

- frontend/src/components/ui/AppUI.jsx and SearchableSelect.jsx; new modalScope.js.
- frontend/src/components/ui/app.css and frontend/src/lib/theme/{tokens.css,themes.css}.
- frontend/{DESIGN_SYSTEM.md,FRONTEND_ARCHITECTURE.md,THEME_ARCHITECTURE.md}.
- This report. Other pre-existing working-tree changes were preserved.

## Dashboard Step 15 verification (2026-11-12)

Outcome: **PARTIAL**. Final implementation checks pass; specified browser interactions/rendering and data-dependent multi-page scenarios remain pending. Historical sections above are not current dashboard evidence.

### Access and method

Available tools were inspected first. No browser-control, computer-use or Playwright tool is exposed. Codex Document Control list_document_sessions returned executors=[] (no connected sessions). Therefore no authenticated browser could be controlled, no screenshots were captured, and no synthetic DOM check is presented as browser verification. No browser framework was installed and the unavailable browser/session discovery was not repeatedly retried.

Read-only API verification ran through the application's bootstrapped Laravel HTTP kernel using existing active company identities assigned to the Sanctum guard in process. Actual middleware, routes, request validation, database queries, policies and resources were exercised. This did not test credential login, a browser session, frontend event handling or network transport from the browser. No tokens, accounts, roles, permissions or business records were created or modified. Output was limited to statuses, boolean checks and aggregate coverage; no private paths or identifying response content is recorded here.

Current design/frontend/theme/API docs, the existing verification reports, dashboard components/styles/hooks and uncommitted changes were reviewed. Unrelated working-tree changes were preserved.

### Concrete defects fixed

1. **Incorrect selected-project KPI state after refresh failure.** Targeted evaluation of the actual selected-count expressions with isError=true and an existing cached selected project returned ready/ready before the fix. useDashboardSummary now checks the request error before cached project data. Re-evaluation returned error/error; inactive project still produced confirmed total=1, active=0, and loading remained loading. This is deterministic source-expression verification, not a browser-rendered failure or simulated API outage.
2. **Invalid report pagination range after a collection shrinks.** An actual read-only request for page 2 at per_page=4 returned HTTP 200, zero rows, total=2, last_page=1, from=null, to=null. The old fallback would display 5–2 of 2 reports. The widget now shows the total without a fabricated range, distinguishes an unavailable page from no matching reports, and lets Previous return directly to an available page. API behavior is unchanged. The frontend message/recovery branch was source-reviewed; browser interaction remains pending.
3. **Commented-out pagination made later projects/stock inaccessible.** The current working tree had Active Projects pagination and Stock's button group commented out. Initial lint found five unused-variable errors: Active Projects AppButton/setPage/meta and Stock AppButton/setPage. Restored only those comment-wrapped controls, retaining existing metadata, page sizes, project-key resets, selected-project hiding for Active Projects, and surrounding edits. No lint suppression or pagination-state deletion was used.

Files changed in Step 15 only:

- frontend/src/features/dashboard/hooks/useDashboardSummary.js
- frontend/src/features/dashboard/components/DashboardTodaySiteReports.jsx
- frontend/src/features/dashboard/components/DashboardActiveProjects.jsx
- frontend/src/features/dashboard/components/DashboardMaterialStock.jsx
- This verification report.

### Current read-only runtime evidence

- Photos, Reports, Stock and Projects collection routes rejected unauthenticated requests with 401.
- Authorized All projects / All time and selected-project requests returned 200. Active projects used status=active, per_page=5 and code ascending; selected mode used the existing project detail route.
- Photos and Reports accepted the current month range (2026-11-01 through 2026-11-30), returned rows only within their photo_date/report_date scope where present, accepted inclusive single-date custom ranges taken from existing records, rejected reversed ranges with 422, and returned to the original totals after date filters were omitted again.
- Material-consumption summary returned 200 for authorized selected-project All time and bounded-month requests. Its period semantics remain consumption_date on posted consumption; no company-wide material quantity is inferred.
- Existing photo/report detail endpoints returned 200 for displayed records. Three authorized private-image endpoint checks returned HTTP 200 with image content types. Browser image decoding, fallback appearance, object-URL lifecycle and click navigation remain unverified.
- Current displayed photo/report/project records passed their existing view policies and tenant ownership checks. Active project rows retained status=active and budget-field inclusion matched the existing viewBudget policy. Cross-tenant project requests returned 404 for all four sources. Unassigned-project requests returned 404 where suitable existing identities/projects existed (Photos, Reports and Projects). Stock returned 403 for excluded/unauthorized identities; populated stock policy checks were unavailable because there are no inventory rows.
- Single-page metadata was consistent with actual row counts and configured widget page sizes. Empty authorized collections were observed. Request-error rendering and network loading presentation were reviewed in source, not induced by breaking services or changing permissions.

### Sparse-data limits

| Widget | Maximum authorized total observed | Widget page size | Default-size multi-page runtime coverage |
|---|---:|---:|---|
| Photos | 2 | 3 | Not available |
| Reports | 2 | 4 | Not available |
| Stock | 0 | 4 | Not available; populated quantities/units/statuses also pending |
| Active Projects | 1 | 5 | Not available |

The explicit out-of-range report request above verifies an edge-case response, not successful multi-page widget browsing. No records were manufactured. Previous/Next disabled expressions, result counts, server-owned ordering, scope reset and cancellation were inspected in source; controls were not clicked in a browser.

### Source-only scope and accessibility review

- New dashboard mounts retain All projects / All time. No first-project auto-selection or filter restoration is introduced. All time omits date limits rather than using sentinel dates.
- Photos filter photo_date; Reports filter report_date. Stock, project counts and progress remain current snapshots. Material consumption retains authorized project/date scope. Financial Summary and unsupported attendance/receivable/contract-value KPI sources remain explicitly unavailable; milestones remain deferred.
- Photo/report scope-keyed inner components reset page on project/period/date/identity changes. Stock and Active Projects reset on project/identity changes. Keys include the relevant identity/scope/page. Collection requests use AbortSignal; different query keys prevent late project responses from replacing current-scope rows. Rapid-filter interaction has not been browser-tested.
- Authorized resource project metadata supplies photo/report/stock labels and detail destinations. Project-only View all destinations are not shown for all-project collections. Financial fields remain gated and placeholder spending is unused.
- Source preserves the 0–100% progress scale, full-name/value text equivalent, tooltip Escape dismissal, semantic colours, focus rules, searchable-select keyboard behavior, contained chart/table scrolling and wrapping pagination/Quick actions.
- Photos/Reports use equal desktop columns and stretched flex-column cards with bottom footers; smaller widths stack naturally. Financial Summary is not stretched to the progress chart's height. Dashboard spacing remains scoped. No root scaling or broad theme override was introduced.

### Final checks and pending acceptance work

- Frontend lint: PASS after the pagination controls were restored.
- Production build: PASS after all fixes; existing non-blocking Zod PURE-comment annotation warnings remain.
- git diff --check: PASS after source fixes and the verification-report update.
- No backend files, API contracts, accounting/stock/workflow rules, database records, test/spec/E2E/browser-automation files or dependencies were changed in this phase.
- Pending browser checks: 1440px/1024px/360px; expanded/collapsed sidebar; light/dark with blue/green/orange accents; computed equal heights, typography and contrast; heading/filter alignment, KPI reflow, long text, progress tooltips, contained scrolling, private image rendering/failure fallback, keyboard focus/searchable dropdowns, custom-date feedback, rapid changes, pagination reset/controls, navigation, Quick-action wrapping and absence of page-level horizontal overflow.
- No browser screenshot or interaction check was completed. Credential-login/session flows were not exercised. Multi-page widget behavior, populated stock and permission-denied UI for identities not represented in available data remain unverified. These limits prevent a VERIFIED claim; there is no currently identified unresolved blocking frontend defect.

## Daily Reports list - Step 16 (2026-11-13)

Outcome: implementation and read-only API checks completed; rendered verification remains PARTIAL. This phase does not resolve Step 15 dashboard browser/sparse-data limitations. Current whole-frontend lint is failing on unrelated working-tree edits, as detailed below.

### Changes and source review

DailyReportsPage now opens All projects / All time through the existing GET /daily-reports collection. Explicit valid project/period/date/search/status/page URL parameters initialize the list; malformed optional values use defaults. All time omits project/date restrictions where appropriate. Title input is submitted with Search; other filters preserve unsubmitted title text and reset pagination. The existing 15-row list page size is retained. Fixed report_date DESC/id DESC ordering and all filtering/counts/pagination remain server-owned. Invalid live custom ranges disable requests and show shared linked validation. Out-of-range pages show counts without impossible ranges and Previous returns to an available page.

The existing collection request/controller gained bounded title search and established status filtering before pagination; the existing preview resource now exposes stored total_workers_present without recalculating workforce. Tenant/project/client visibility rules are unchanged. Rows show title/summary, resource project, report date, creator, authoritative worker total and the existing status badge. Existing permission/status/author conditions for View/Edit/Delete and confirmation are preserved; all links and deletion payloads use the row's project ID. No Submit or Review action was added because the old list did not expose those actions. No create/edit/detail page was edited.

The shared Project combobox uses existing authorized server search rather than filtering a loaded page. Lookup is bounded to 50, debounced 250ms, scoped by identity/search, cancelled through AbortSignal and accompanied by a refine-search notice if results exceed the bound. Current selection survives search loading/errors; projects.view gates lookup separately from report permission. A shared URL builder lets dashboard Site Reports View all preserve all-project/selected-project and period/date scope. Other dashboard widgets were not edited.

### Checks completed

- PHP syntax checks and Pint: PASS for the collection controller, request and preview resource.
- Targeted ESLint: PASS for DailyReportsPage.jsx, DailyReportFilters.jsx, useReportProjectOptions.js, reportListFilters.js and the changed dashboard Site Reports component.
- Full npm run lint: FAIL on nine unused variables in existing unrelated DashboardActiveProjects.jsx, DashboardMaterialStock.jsx and DashboardRecentSitePhotos.jsx. Each has AppButton/setPage/meta unused because pagination blocks are commented out in the current working tree. These modules were preserved under Step 16's scope restrictions; no lint suppression or unrelated cleanup was performed.
- Production build: PASS; existing non-blocking Zod annotation warnings remain.
- git diff --check: PASS after documentation updates.
- Source-module URL checks: PASS for All projects / All time defaults, custom project/search/status/page links, serializer/parser round trips, malformed explicit URL values, reversed live ranges, and omission of All time date limits. These are source/helper checks, not browser interaction or synthetic DOM evidence.

### Current read-only runtime evidence

Requests ran through the bootstrapped Laravel HTTP kernel with existing company identities assigned in memory; no credential login, token creation, account reset, role change, report mutation or schema change occurred. Output contained statuses/counts/booleans rather than identifying report data.

- Anonymous collection request: 401. Authorized default collection: 200 with requested per_page=15.
- Combined existing report-title, status, project and inclusive report_date range filters returned only matching rows.
- A nonexistent title returned confirmed zero results. Literal title query 0 was not ignored by PHP truthiness. Invalid status and title length >255 returned 422.
- Returned rows passed existing report policies; clients retained approved/client-visible restrictions. Worker totals exactly matched existing stored model totals. Cross-tenant project requests returned 404.
- Existing project-nested report-detail endpoints returned 200 for displayed records; this verifies API destinations, not browser click navigation.
- Server pagination was exercised at per_page=1 using the existing two reports. At the actual 15-row list size, only a single page is available; multi-page list interaction remains unverified. Out-of-range pages returned no rows and null from/to metadata and are handled without fabricated ranges.

### Remaining access/data limits

No browser-control tool was exposed and Document Control returned no connected sessions. No screenshots or browser checks were performed. Desktop/360px layout, light/dark/accent rendering, searchable-project dropdown keyboard behavior, long text/table scrolling, custom-date feedback, browser history, rapid filter changes and View/Edit/Delete interaction remain pending. The deletion action and all workflows were reviewed in source but not invoked. Available data is sparse (maximum two authorized reports); no reports were manufactured. Earlier dashboard multi-page/populated-stock/browser limitations and milestone deferral remain in force.

Files changed in Step 16: backend collection controller/request/preview resource; frontend DailyReportsPage.jsx, DailyReportFilters.jsx, new useReportProjectOptions.js, reportListFilters.js and dailyReportsList.css; dashboard DashboardTodaySiteReports.jsx for View all only; API_INTEGRATION.md, FRONTEND_ARCHITECTURE.md, DESIGN_SYSTEM.md and this report. No test, spec, E2E or browser-automation file or dependency was created or modified.

## Create Daily Report - Step 17 (2026-11-13)

Outcome: create-page implementation complete; browser verification pending. This phase does not resolve the existing dashboard or Step 16 visual/data limitations.

### Source changes and boundaries

CreateDailyReportPage uses a 1200px maximum, shared header and a sectioned DailyReportForm variant. The five sections and footer retain all existing field names and one form element. ReportProjectField uses the existing authorized remote project search/selected-project lookup; blank project/date defaults and URL-fixed project behavior are preserved. Work and issue fields remain scalar textareas, weather remains free text, and progress remains numeric with its existing schema. No report content or workforce categories are prefilled.

Create-only workforce categories use the shared combobox and live form values to disable already-used categories. Allowed enum values, explicit-zero counts, nonnegative integer validation, add/remove behavior and existing backend duplicate-category rejection remain. Add/remove/Cancel are non-submit buttons; Save Report is the only submit action and retains the form's pending state. The existing schema/payload, server-error mapping, route/client permissions, success toast and nested detail destination are unchanged. EditDailyReportPage and dailyReportSchema.js have no diff; Edit uses the default legacy form/worker layout, with no visual redesign. Error/value retention was source-reviewed through the unchanged submit handler (no reset call), not verified by a live save.

### Completed checks

- Targeted lint PASS for CreateDailyReportPage, DailyReportForm, DailyReportCreateSections, ReportProjectField and WorkersPresentField.
- Full frontend lint FAIL on nine existing unrelated unused AppButton/setPage/meta errors in DashboardActiveProjects, DashboardMaterialStock and DashboardRecentSitePhotos, whose pagination blocks are commented out in the current tree. Those edits were not removed, restored or suppressed in Step 17.
- Production build PASS; existing non-blocking Zod annotation warnings remain.
- git diff --check PASS after documentation updates.
- Source-module validation/payload checks PASS: project/date defaults remain blank; initial workers are empty; progress=0 and explicitly entered worker count=0 survive serialization; blank progress becomes null; no project_id or total_workers_present is added to the payload; empty workers remain valid; negative/fractional counts fail; blank completed work and report date fail. These in-memory checks used the existing schema and payload builder and saved no report.
- Existing legacy FormLayoutLayer and VerticalInputForm were inspected for grouping/spacing only. No legacy global style or dependency was loaded.

### Pending checks and access limitations

No browser-control tool is exposed and Document Control returned no connected sessions. No screenshots or browser interaction checks were completed. Desktop/360px rendering, field/long-error wrapping, selected-project labels, category add/change/remove interactions, explicit zero presentation, keyboard focus/dropdowns, themes and footer wrapping remain pending. API mutation/error/success flows were not invoked to populate or visually test the page. No records, accounts, permissions, backend code, test/spec/E2E/browser-automation files or dependencies were changed.

Files changed: CreateDailyReportPage.jsx; DailyReportForm.jsx; WorkersPresentField.jsx; new DailyReportCreateSections.jsx, ReportProjectField.jsx and createDailyReport.css; DESIGN_SYSTEM.md, FRONTEND_ARCHITECTURE.md and this verification report. Daily Reports list, Edit/Detail pages, Site Photos and unrelated dashboard edits were preserved.

## Clients independent redesign and role review ? 2026-11-13

Outcome: independent contact/directory UI completed; automatic account provisioning deferred by the user pending role review. See CLIENT_ROLE_REVIEW.md for the anonymized active-database inspection, exact extra grants and controller/policy effects. No accounts, roles, grants, memberships or schema were changed; the nullable unique clients.user_id relationship remains proposed only. No account metadata was fabricated.

Targeted Clients/adapter lint and production build pass. Full lint retains nine unrelated no-unused-vars errors in DashboardActiveProjects, DashboardMaterialStock and DashboardRecentSitePhotos (AppButton, setPage and meta). git diff --check passes. No backend source changed, so PHP/Pint checks are not applicable. Database evidence is read-only and does not verify creation/rollback. Browser discovery found no connected Codex document sessions; desktop/360px, themes, form errors and navigation remain pending. No test/spec/browser-automation files were changed. Prior dashboard limitations are not resolved by this phase.

## Client-role directory visibility ? 2026-11-13

Implemented Client Accounts (default) and Business Contacts tabs. Added ClientAccountController, ListClientAccountsRequest and minimal ClientAccountResource, route /clients/accounts, account adapter/hook and ClientAccountsDirectory; extracted the prior contact list into BusinessContactsDirectory. ClientFormPages labels creation explicitly contact-only. Existing contact CRUD and lookup source is unchanged. Documentation updated in API_INTEGRATION, FRONTEND_ARCHITECTURE, DESIGN_SYSTEM and CLIENT_ROLE_REVIEW. No test/E2E files, grants, records, schema or provisioning were modified.

Permission decision: retain clients.view AND users.view for login-account metadata, rather than interpreting contact-only clients.view as staff/account authorization. Presentation and requests both follow this boundary; clients.view-only users can switch to Business Contacts. Manage accounts navigates to the existing Users screen, where existing action permissions and confirmations remain authoritative. No account IDs enter contact mutation routes.

Read-only runtime evidence: in-process HTTP-kernel GET requests with existing authorized administrator identities in three tenants returned 200; totals 1, 1 and 0 matched independently queried same-company client-role membership. Every returned ID belonged to the expected role/tenant set; response fields were restricted to id/name/email/phone/status and per_page was 15. Exact client-email searches passed where rows existed; unmatched search returned zero. Explicit other-tenant-email search and non-client administrator-email search returned zero. Page 2 preserved correct total/current_page with empty data; page 0 returned 422. Existing client-only identities returned 403. Existing business contacts and project-client lookup both returned 200 and one existing contact in the checked tenant. No creation/deletion/status mutation was performed.

Source-only: guard=web and tenant/pivot filters, soft-delete exclusion, additional-role inclusion, disabled-status inclusion, name/id stable ordering, filtering before paginate/count, query cancellation and identity keys, existing user-mutation cache invalidation. Existing data had only Active client accounts and at most one per tenant; populated multi-page navigation, disabled/multi-role examples and soft-deleted client examples remain runtime-unverified. One tenant lacked a suitable existing administrator. Contact mutations were not exercised.

Checks: changed PHP files and routes pass php -l and Pint --test. Targeted frontend lint passes; production build passes with the existing Zod annotation warnings. Full frontend lint retains nine unrelated dashboard no-unused-vars errors (AppButton, setPage, meta in DashboardActiveProjects, DashboardMaterialStock, DashboardRecentSitePhotos); unrelated files preserved. git diff --check passes. Browser desktop/360px/theme/tab interaction checks remain pending because no connected browser/document session was available in the preceding discovery. Prior dashboard limitations remain outstanding.

## Linked Client Account Creation - 2026-11-13

Outcome: source workflow implemented; NOT OPERATIONAL on the active database until 2026_11_13_120000_add_user_id_to_clients_table.php is applied. The migration was prepared but not run. This supersedes the earlier contact-only and tab-based phases. Existing grants, live records and project memberships were preserved. See CLIENT_ACCOUNT_PROVISIONING.md for the full file map, permissions, endpoints, relationship, lifecycle limitations and verification evidence.

New-client and explicit existing-contact flows are transactional, role/tenant/web-guard scoped, grant-ceiling checked, duplicate-safe and credential-free in audit/response. Existing-contact locking/recheck and early 409 prevent another account being created for linked contacts. Contact edits/deletion leave User unchanged. One server-backed typed directory preserves account-only entries without fabricating contacts or mixing IDs. Migration availability and exact provisioning authority gate forms/actions.

Disposable :memory: SQLite checks with manually mirrored schema and synthetic fixtures passed creation/hash/role/link, duplicate validation, forced-save rollback of User/role/Client/audit rows, explicit link request/controller 201, already-linked 409 and second-user prevention, tenant/grant-ceiling/missing-web-role rejection, safe response/audits, password/protected-field validation, contact edit/delete independence, typed multi-role/Inactive account-only entries, no email merge/duplicate linked rows, and users.view metadata boundaries. No migration or test file was executed/created. A joined-directory ambiguous column found during these checks was fixed. True simultaneous requests, MySQL lock behavior and concurrent uniqueness races remain source-only, not runtime passes.

Read-only live GET checks passed directory/contact-list/project-lookup reads and confirmed schema_ready=false with provisioning disabled. No live records were created or altered. PHP syntax/Pint and targeted frontend lint pass; production build passes; git diff --check passes. Full frontend lint retains the same nine unrelated dashboard unused-variable errors. Browser discovery returned no connected document sessions, so rendered desktop/360px/themes/form/modal/navigation checks remain pending. Prior dashboard limits are not resolved by this work.

## Confirmed combined-client workflow alignment - 2026-11-13

Inspected and reused the atomic backend workflow and typed server directory. Removed the unused separate-account frontend component/hook; renamed the current primary table module ClientDirectory. Add Client now remains navigable for base-authorized provisioning actors and shows its combined email/password/confirmation form during deployment prerequisites, with fields/saving disabled and an explanatory notice rather than a contact-only fallback. Existing-contact Create login account actions remain visible but disabled until availability succeeds. Ordinary contact viewing still requires only clients.view, with separate users.view gating in API/UI and permission-aware cache keys for account metadata. No backend authorization, role grant, project policy or live record was changed.

Deployment state changed since the preceding observation: read-only inspection NOW confirms migration 2026_11_13_120000_add_user_id_to_clients_table is recorded, clients.user_id is nullable and unique, and its users.id FK uses restrict deletion. The agent did not execute it. Account-options returned schema_ready/can_create/can_link=true for the checked administrator. Directory, contact list, project lookup and Users GET endpoints returned 200. No live POST/mutation was performed. No existing clients.view-only/non-users.view identity was available for a fresh live check; that boundary remains source-reviewed with the prior isolated result, not a newly claimed live pass.

Fresh targeted frontend lint and production build pass; full lint still reports the same nine unrelated dashboard no-unused-vars errors. git diff --check passes. Backend source was unchanged in this follow-up, so previous PHP syntax/Pint and isolated transaction checks were not unnecessarily repeated. Browser interaction, true concurrency and live account creation remain unverified. No test/E2E files or live verification fixtures were created. See CLIENT_ACCOUNT_PROVISIONING.md for the full workflow, source files and historical/current evidence.

## Clients directory visual redesign - 2026-11-13

Presentation-only changes: frontend/src/features/clients/pages/ClientsPage.jsx, ClientDirectory.jsx and clients.css; related DESIGN_SYSTEM/FRONTEND_ARCHITECTURE documentation updated. No backend, API/hooks, creation forms, relationships, role grants, project policies or live records were changed.

Header description now matches the requested text. The technical directory paragraph is removed. A visibly labelled shared AppInput replaces the old table-search label, removing its bottom margin that caused the field/button misalignment. The scoped toolbar provides a 360px desktop input, shared 44px controls, 12px gap and mobile stacking. One shared DataTable now has five columns: Client, Email, Phone, Account, Actions. Decorative initials, optional company text, a subtle account-only label, truthful link/status/restricted states, distinct authorized login-email differences and semantic Delete styling are implemented. Table headers/body inherit shared typography/density with scoped muted header tint/hover, balanced widths and wrapping actions. The final body-row border is removed and the existing server footer retains its single top divider. Table overflow stays inside its labelled focusable scroll container; no row heights or root scaling were introduced.

Source review confirms unchanged directory inclusion/query parameters, submitted search/page reset, server-owned pagination/counts, fetching-disabled Previous/Next, out-of-range recovery, permissions, business-client versus account-only routing, confirmation dialog and explicit provisioning action. Existing account-only records are never labelled No login account. No new account metadata is fetched or exposed, and missing company text is omitted instead of replaced by a dash.

Checks: targeted ESLint for ClientsPage/ClientDirectory passed; full frontend lint retains nine pre-existing no-unused-vars errors (AppButton/setPage/meta in DashboardActiveProjects, DashboardMaterialStock and DashboardRecentSitePhotos), with no dashboard files modified in this phase. Production build passed with existing non-blocking Zod annotation warnings. git diff --check passed. No test/spec/E2E/browser-automation files were created or modified. Backend checks are not applicable because no PHP changed.

Browser discovery returned no connected Codex document sessions. No rendered desktop/360px, long-name/email, account-state, light/dark/accent, focus, action-alignment or pagination checks are claimed. These remain pending; source inspection and build are not browser verification. No live data was created or mutated for this presentation phase.
