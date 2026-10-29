# Step 1 — Current Multi-Tenant and RBAC Gap Audit

Date: 2026-10-29  
Scope: current repository and the connected deployed database; read-only inspection only.

## A. Executive verdict

The implemented tenant boundary is substantially stronger than the historical baseline. All 18 discovered tenant-owned Eloquent models use `BelongsToCompany`; tenant HTTP routes establish and clear a company context; missing context fails closed; model-level creates, updates, deletes, and restores are company-scoped; project membership is applied to project resources; and the sampled deployed data contains no cross-company relationship mismatches.

No P0 defect was verified. The system should **not yet be described as fully tenant-hardened**, principally because same-company relationships are not enforced by composite database constraints and several bulk/query-builder paths rely on controller validation. The most immediate functional security defect is RBAC vocabulary drift: canonical permissions such as `projects.edit`, `daily_reports.*`, `users.manage`, and `company.settings` coexist with the legacy permissions actually checked by implemented backend/frontend features. A custom role can therefore be granted an apparently correct canonical permission and still be denied. One implemented material-request transition also omits the own-record rule enforced by update/submit.

The permanent-user-deletion support migration is deployed (batch 2), so the source and connected schema are aligned for nullable attribution cleanup. This audit did not re-run historical HTTP scenarios and does not claim production readiness.

## B. Verified existing protections

- `BelongsToCompany` adds a global `company_id` scope, gets the company from `TenantContext`, assigns ownership on create, rejects ownership changes, and rejects cross-company saves: `backend/app/Models/Concerns/BelongsToCompany.php:15-35`.
- `TenantContext::companyId()` throws when no context exists; `run()` restores the prior context after trusted scoped execution: `backend/app/Support/Tenancy/TenantContext.php:13-63`.
- Tenant middleware rejects company-less users, resolves the company, sets the Spatie team ID, and clears both context and team ID in `finally`: `backend/app/Http/Middleware/EnsureCompanyContext.php:24-47`.
- Spatie teams are enabled with `company_id` as the team key: `backend/config/permission.php:100,138`.
- Tenant routes are inside `auth:sanctum`, `account.access`, and `company.context` middleware groups: `backend/routes/api.php:55-190`.
- Manual resource IDs are consistently resolved through tenant-scoped model queries. Nested report/photo controllers additionally constrain children to the resolved project. Examples: `ProjectTeamController.php:88-93`, `DailySiteReportController.php:251-268`, `SitePhotoController.php:292-314`.
- Project access uses same-company plus company-admin-or-membership logic: `backend/app/Models/Project.php:91-101`, `backend/app/Policies/ProjectPolicy.php:10-34`.
- Project team synchronization validates every user against the current company and active status and writes `company_id` into the pivot payload: `ProjectTeamController.php:41-64`.
- Client report visibility is consistent between list and policy: approved **and** client-visible are required. Review publication is explicit: `DailySiteReportController.php:36,179-185`; `DailySiteReportPolicy.php:21-26`.
- Daily-report update/delete ownership restrictions are policy-enforced for non-admins: `DailySiteReportPolicy.php:34-51`.
- Material-request update and submit enforce requester ownership for non-admins: `MaterialRequestController.php:64-91`.
- Permanent deletion is transactional, tenant-locks the target, revokes tokens/sessions, removes access/membership rows, nulls supported attribution, and calls `forceDelete()`: `backend/app/Services/UserAccountService.php:23-64`. Controller safeguards and `users.delete` are applied at `UserController.php:163-199`.
- The deployed migration `2026_10_29_120000_make_user_attribution_nullable_for_permanent_deletion` is recorded as **Ran, batch 2**.

## C. Model/table/pivot coverage matrix

The schema column/index/FK values below were read from the connected database, not inferred only from migrations. `R` means the FK is restrictive/no-action rather than cascading business history.

| Model / table | Ownership | Deployed company column | Protection | Delete/restore implications |
|---|---|---|---|---|
| `Company` / `companies` | Platform | n/a | Platform-admin middleware/controllers | Soft-deleted; tenant rows restrict company deletion |
| `User` / `users` | Hybrid | nullable, indexed, FK R | Explicit controller/service scoping; platform admins may be company-less | SoftDeletes exists, but current tenant delete force-deletes; history attribution is nulled |
| `MeasurementUnit` / `measurement_units` | Shared | nullable, indexed, FK R | Explicit query includes global system units plus current-company units | Soft-delete; global rows intentionally have null company |
| `Department` / `departments` | Tenant | not null, indexed/FK R | `BelongsToCompany` | Soft-delete and scoped restore |
| `Project` / `projects` | Tenant | not null, indexed/FK R | `BelongsToCompany` plus membership policy | Soft-delete and scoped restore |
| `DailySiteReport` / `daily_site_reports` | Tenant | not null, composite index/FK R | `BelongsToCompany`; parent/project checks and policy | Soft-delete; child workers retained/deleted only by explicit workflow |
| `DailySiteReportWorker` / `daily_site_report_workers` | Tenant child | not null, composite index/FK R | `BelongsToCompany`; created/replaced through scoped report | Hard-delete child; relationship bulk delete bypasses model events but remains parent constrained |
| `SitePhoto` / `site_photos` | Tenant | not null, indexed/FK R | `BelongsToCompany`; project/report and policy checks | Soft-delete; storage deletion behavior remains workflow-specific |
| `MaterialCategory` / `material_categories` | Tenant | not null, indexed/FK R | `BelongsToCompany` | Soft-delete |
| `Material` / `materials` | Tenant | not null, indexed/FK R | `BelongsToCompany`; category/unit validated in requests/controllers | Soft-delete |
| `ProjectInventory` / `project_inventories` | Tenant | not null, indexed/FK R | `BelongsToCompany`; project/material validation | Soft-delete |
| `InventoryTransaction` / `inventory_transactions` | Tenant ledger | not null, indexed/FK R | `BelongsToCompany`; created by validated workflows | Hard-delete model; business workflows do not expose deletion |
| no model / `inventory_transfers` | Tenant ledger | not null, indexed/FK R | Query-builder controller explicitly supplies company and validates both projects/inventories | No soft delete; model events unavailable |
| `Supplier` / `suppliers` | Tenant | not null, indexed/FK R | `BelongsToCompany` and policy | Soft-delete |
| `MaterialDelivery` / `material_deliveries` | Tenant aggregate | not null, indexed/FK R | `BelongsToCompany`; project/supplier validation | Soft-delete |
| `MaterialDeliveryItem` / `material_delivery_items` | Tenant child | not null, indexed/FK R | `BelongsToCompany`; parent/inventory/material validation | Hard-delete child during draft replacement |
| `MaterialConsumption` / `material_consumptions` | Tenant aggregate | not null, indexed/FK R | `BelongsToCompany`; project policy | Soft-delete |
| `MaterialConsumptionItem` / `material_consumption_items` | Tenant child | not null, indexed/FK R | `BelongsToCompany`; validated parent/inventory/material | Hard-delete child during draft replacement |
| `MaterialStockAdjustment` / `material_stock_adjustments` | Tenant aggregate | not null, indexed/FK R | `BelongsToCompany`; project policy | Soft-delete |
| `MaterialStockAdjustmentItem` / `material_stock_adjustment_items` | Tenant child | not null, indexed/FK R | `BelongsToCompany`; validated parent/inventory/material | Hard-delete child during draft replacement |
| `MaterialRequest` / `material_requests` | Tenant aggregate | not null, indexed/FK R | `BelongsToCompany`; project membership and permissions | Soft-delete |
| `MaterialRequestItem` / `material_request_items` | Tenant child | not null, indexed/FK R | `BelongsToCompany`; inventory constrained to project/company | Hard-delete child during draft replacement |
| `project_user` | Tenant pivot | not null, indexed/FK R | Explicit validation and company-bearing `sync()` payload | Membership removed on user deletion; project retained |
| `roles` | Tenant RBAC | nullable, indexed, **no company FK** | Role controllers explicitly scope company; nullable supports package/global shape but none deployed | Orphans possible at DB level |
| `model_has_roles`, `model_has_permissions` | Tenant RBAC pivots | not null, indexed, **no company FK** | Spatie team context plus scoped assignment endpoints | Explicitly cleaned during permanent deletion |
| `permissions`, `role_has_permissions` | Global RBAC catalog | no company column | Permission names global; tenant roles own grants | Shared by design |
| tokens, sessions, password resets, cache, jobs, activity log | Platform/infrastructure | no tenant column | Authentication keys/user references or explicit activity properties | Not tenant business aggregates |

All 18 tenant-owned models listed above use `BelongsToCompany`. The non-model `inventory_transfers` table is the notable explicit alternative. `Company`, `User`, and `MeasurementUnit` are intentionally not trait-scoped because their platform/shared semantics require null or cross-context access.

## D. Remaining findings ranked P0/P1/P2

### P0

No P0 finding was verified in current code or sampled deployed data.

### P1-1 — Canonical and enforced permission names are not aliases

**Failure scenario.** A custom role receives `projects.edit`, `daily_reports.create`, `users.manage`, or `company.settings` from the catalog. The role editor shows the permission as granted, but the matching implemented screen/endpoint checks `projects.update`, `site_reports.create`, granular `users.*`, or `company.update`, so the user is denied or the UI is hidden. Conversely, maintaining two names makes it unclear which grant is authoritative.

**Evidence.** Both vocabularies are advertised in `PermissionCatalog.php:9-31`. Project backend/frontend check `projects.update` (`ProjectController.php:90`; `frontend/src/routes/AppRoutes.jsx:170`). Reports check `site_reports.*` (`DailySiteReportController.php:29-225`; `frontend/src/components/layout/navigation.js:15`). Users and company features use granular legacy names. `/api/v1/me` returns effective stored permissions rather than translating aliases.

**Proposed fix.** Choose one canonical vocabulary for implemented modules. Prefer a one-time role-permission grant migration plus coordinated backend/frontend replacement. During a bounded compatibility window, authorization may accept either name through one centralized mapping; do not scatter `can(A) || can(B)` checks.

**Compatibility/data migration.** Inventory existing custom-role grants first. Map old grants to canonical grants without broadening privilege, update defaults, then retire legacy permissions only after all consumers are migrated. Future-only modules (`tasks`, `workers`, `expenses`, etc.) should not be treated as defects merely because routes are absent.

**Manual acceptance.** Create a custom role with each canonical grant; verify the matching UI and endpoint work, adjacent actions remain forbidden, `/me` reports the expected effective grant, and legacy-role users behave unchanged during migration.

### P1-2 — Same-company relationship integrity is primarily application-enforced

**Failure scenario.** A future import, command, direct query-builder write, or controller regression inserts a tenant-A `company_id` with a tenant-B project/material/inventory ID. Individual FKs accept the row because they validate only record existence. A tenant-filtered raw join can then aggregate foreign business data into tenant A's result.

**Evidence.** Deployed tables have individual `company_id` and record FKs but no universal composite `(company_id, id)` relationship constraints. Controllers currently validate relationships—for example project team (`ProjectTeamController.php:41-64`) and request inventories (`MaterialRequestController.php:238-260`)—but reporting joins in `InventoryReportingService.php` and inventory controllers rely on those invariants.

**Proposed fix.** Add composite unique parent keys and composite tenant-aware FKs in dependency order for high-risk project, inventory, material, report, item, and pivot links. Keep application validation for clear 404/422 responses. Centralize multi-record validation for workflow services and require explicit tenant context in non-HTTP entry points.

**Compatibility/data migration.** Run full mismatch/orphan preflight queries before DDL. Composite indexes increase index/storage and migration-lock cost. Nullable/shared measurement-unit references require a separate design and cannot use the same unconditional composite FK.

**Manual acceptance.** Cross-company inserts fail at DB level; valid same-company inserts still work; global measurement units remain usable; all workflow/list/report paths return unchanged results; migration preflight reports zero blockers.

### P1-3 — Material-request cancel omits the requester/own-record restriction

**Failure scenario.** A site engineer, supervisor, or quantity surveyor with `material_requests.update` and project membership can cancel another member's draft or submitted request. The same user cannot update or submit that other request, making cancel wider than the surrounding workflow.

**Evidence.** Update and submit require requester ownership for non-admins at `MaterialRequestController.php:69,87`; cancel checks only permission and status at `MaterialRequestController.php:128-136`. The route is `POST /api/v1/material-requests/{requestId}/cancel` (`routes/api.php:172`).

**Proposed fix.** Apply the same company-admin-or-requester condition to cancel, preferably in a dedicated policy/state-transition service shared by update, submit, and cancel.

**Compatibility/data migration.** No data migration is needed. Confirm whether an approver/storekeeper is intentionally allowed to cancel others' submitted requests; if so, give that authority a distinct permission rather than inheriting `update`.

**Manual acceptance.** Creator and company admin can cancel allowed states; another assigned project member with only update receives 403; foreign-company and inaccessible-project IDs remain 404; invalid states remain rejected.

### P1-4 — RBAC company integrity lacks database foreign keys

**Failure scenario.** A failed cleanup or direct write leaves a role/team pivot pointing to a deleted or different company. Spatie may resolve a grant under the wrong team if application assumptions are later weakened.

**Evidence.** Deployed `roles.company_id`, `model_has_roles.company_id`, and `model_has_permissions.company_id` are indexed but have no FK to `companies`. Application scoping and Spatie team configuration currently mitigate this. Current data query found zero global/orphan roles.

**Proposed fix.** Add company FKs after confirming package upgrade requirements; add cross-table consistency checks for role company, pivot company, and user company. Keep nullable `roles.company_id` only if a documented global-role use case exists.

**Compatibility/data migration.** Spatie schema conventions and company soft deletion must be considered. Restrictive FKs are safer than cascading role grants silently. Preflight orphan and mismatch queries are mandatory.

**Manual acceptance.** Invalid company IDs and mismatched role assignments are rejected; normal tenant role creation/assignment works; platform-admin authentication remains independent of tenant roles.

### P2-1 — One inventory-transfer readback is not explicitly company-filtered

**Failure scenario.** After insert, the controller reads the new transfer with a primary-key-only query-builder lookup. It is safe while the ID is generated in the same transaction, but copying this pattern to caller-supplied IDs would create an exposure.

**Evidence.** `InventoryTransferController` validates company-scoped source/destination data and inserts `company_id`, then uses `DB::table('inventory_transfers')->find($transferId)` for the response.

**Proposed fix.** Add `where('company_id', $tenant->companyId())` to the readback or introduce a tenant-scoped model/repository for consistency.

**Compatibility/data migration.** None.

**Manual acceptance.** Transfer creation response remains unchanged; direct lookup cannot return a row outside the established company.

### P2-2 — Role-name checks encode access tiers outside permissions

**Failure scenario.** A carefully configured custom role has the needed permission but is denied because a policy requires `company_admin`, or it receives behavior based on a role name rather than its grants. Examples include team management, supplier modification, client filtering, and admin ownership bypasses.

**Evidence.** `ProjectPolicy.php:13-28`, `SupplierPolicy.php:12-18`, `SitePhotoPolicy.php:13-56`, `DailySiteReportPolicy.php:21-73`, and `InventoryReportingService.php:17` use role names.

**Proposed fix.** Classify each check as (a) deliberate security identity, such as client data redaction; (b) ownership bypass; or (c) action authorization. Retain documented identity checks, but migrate action authorization to explicit permissions/policies. Do not simply remove client denials.

**Compatibility/data migration.** Custom-role behavior can change. Add explicit permissions only after defining the intended matrix.

**Manual acceptance.** A custom role with an action permission gets exactly that action; client redaction and publication constraints remain; non-admin members do not gain tenant-wide access.

## E. Affected routes, write paths, and files

| Area | Routes / paths | Write or lookup path |
|---|---|---|
| Permission vocabulary | Project, report, user, company, role endpoints under `/api/v1` | `PermissionCatalog`, controllers, policies, frontend `can()`/`ProtectedRoute`, `/me` resource |
| Material request ownership | `PATCH /material-requests/{requestId}`, `POST .../submit`, `POST .../cancel` | `MaterialRequestController::find/update/submit/cancel` |
| Project membership | `GET/PUT /projects/{id}/team` | Manual tenant lookup; validated `belongsToMany()->sync()` |
| Reports/photos | `/projects/{id}/daily-reports/{reportId}...`, `/projects/{id}/photos/{photoId}...` | Manual project plus nested child resolution; policies |
| Inventory relationships | Project inventory, delivery, consumption, adjustment, request, transfer routes | Model `forceCreate`/`save`, relationship child delete/recreate, query-builder transfer insert |
| User deletion | `DELETE /users/{id}` | `UserController` → `UserAccountService` transaction and raw cleanup |
| Raw reporting | Inventory overview, movement, alert endpoints | `InventoryReportingService`, controller joins/aggregates with explicit base-company predicates |

No `withoutGlobalScope`/`withoutGlobalScopes` use was found in application code. Most tenant routes use scalar manual IDs rather than implicit model binding; this is not itself a defect because the current resolvers apply company and parent/project constraints. API URL changes are not recommended solely to introduce binding.

## F. Platform/global exceptions and enforcement

- **Company-less platform administrators:** `User.company_id` is nullable. Platform routes use `EnsurePlatformAdmin`; tenant middleware rejects company-less users. The `CreatePlatformAdmin` command is a deliberate trusted path.
- **Companies:** platform-owned and not tenant-trait scoped. Tenant code obtains the authenticated user's company; platform company CRUD is separately protected.
- **System measurement units:** `measurement_units.company_id` is nullable. Query logic exposes global system units plus current-company units; tenant mutations must not alter global units.
- **Permissions:** permission definitions are global; roles/grants are team-scoped by `company_id`.
- **CLI:** `AssignUsersToCompany` is an explicit trusted migration command, dry-run unless committed, and only assigns unassigned users. It bypasses tenant HTTP context intentionally.
- **Jobs/scheduling/imports:** no application tenant jobs, scheduled tenant work, or import subsystem was discovered. No demonstration job is recommended.

## G. Existing data-integrity findings

Read-only deployed-data checks returned **zero mismatches** for these sampled relationships:

- project pivot → project company; project pivot → user company;
- report → project; report worker → report;
- photo → project;
- project inventory → project and material;
- material request → project.

RBAC checks also found zero global roles and zero roles referencing a missing company. These are current connected-database observations only, not proof about other environments. The check was sampled rather than exhaustive across every nullable attribution and polymorphic reference; no records were changed.

## H. RBAC compatibility and own-record findings

- Nine default role definitions exist: company admin, project manager, site engineer, site supervisor, quantity surveyor, accountant, store keeper, client, and worker.
- Default roles often receive both canonical and legacy grants, masking the alias defect. Custom roles remain affected because each stored permission is independent.
- Frontend and backend are generally compatible with each other for implemented screens because both predominantly use legacy names (`projects.update`, `site_reports.*`, granular user/company permissions).
- `/api/v1/me` exposes effective permissions, roles, company, and account state; it does not normalize canonical aliases.
- Role and user management are company-scoped. Project-team assignment separately validates user company and active status.
- Permanent deletion preserves self-deletion, platform-admin, and last-active-company-admin safeguards in the controller/service path.
- Daily reports and site photos enforce creator/uploader rules in policies. Material-request update and submit enforce own-record rules, but cancel does not (P1-3).
- Role-name checks remain in multiple policies. Some are legitimate identity rules—especially client filtering—and must not be mechanically converted into broad permission grants.
- No verified stale-permission-cache defect was established. Role grant mutation explicitly forgets Spatie's permission cache; assignment methods use Spatie APIs. `/me` also refreshes effective authorization state, though the extra global cache clear there deserves performance review rather than a security finding.

## I. Checks actually performed

- Read the supplied audit specification and treated historical results only as context.
- Enumerated repository models, migrations, routes, controllers, policies, requests, services, commands, and frontend permission checks with `rg`/PowerShell.
- Ran `php artisan route:list --path=api/v1 --except-vendor`; 140 API v1 routes were enumerated.
- Ran `php artisan migrate:status --no-ansi`; all listed migrations are applied, including the permanent-deletion attribution migration in batch 2.
- Queried deployed schema metadata read-only for table columns, nullability, indexes, and foreign keys.
- Inventoried `DB::table`, joins, aggregates, bulk updates/deletes, inserts, model `forceCreate`/`forceDelete`, and relationship `sync` paths.
- Ran read-only mismatch counts against the connected database; all sampled counts were zero.
- Inspected report publication, photo/report policies, project membership, user deletion, authentication middleware, Spatie team configuration, permission catalog, `/me`, and frontend guards.
- Did **not** run automated test suites, seeders, migrations, builds that write artifacts, or any mutating HTTP/database operation.

## J. Items not verified and why

- Historical cross-company 404, assignment/revocation, client publication, photo upload, disable/enable, login, and permanent-deletion scenarios were not repeated because the requested step is read-only and several require mutations.
- File upload/storage ACL behavior was inspected in code but not exercised because upload would write files and records.
- Token/session revocation and email reuse were inspected in code/schema but not executed because that requires destructive account operations.
- Restore behavior was not exercised. Trait scoping appears to apply to restore queries/events, but an end-to-end restore is mutating.
- Every possible polymorphic `reference_type/reference_id` target was not data-validated; polymorphic integrity cannot be expressed by ordinary FKs and requires per-type checks.
- Other deployment environments were not inspected; deployed-schema statements apply only to the connected database.
- No automated tests were run, as explicitly required.

## K. Recommended implementation sequence

1. Resolve the permission vocabulary contract for implemented modules; inventory live custom grants and implement a non-broadening compatibility migration.
2. Fix the material-request cancel ownership rule and centralize its state/ownership authorization.
3. Add exhaustive, read-only mismatch/orphan preflight commands or deployment SQL covering every tenant relationship and RBAC pivot.
4. Add composite tenant-aware keys/FKs in small dependency-ordered migrations, beginning with project membership, report children, and inventory/material workflow tables.
5. Add restrictive company FKs/checks to Spatie team tables after confirming supported package schema.
6. Harden remaining query-builder readbacks and centralize raw reporting tenant predicates.
7. Review role-name checks and document which represent immutable identities versus replaceable action authorization.
8. Manually re-run the historical acceptance matrix in an isolated non-production environment after implementation.

## NEXT STEP HANDOFF

### Highest-priority implementation task

Normalize RBAC permission names for currently implemented modules without widening any role. This is higher priority than adding new features because the current catalog can create roles that appear correctly configured but cannot use the corresponding backend action.

### Likely affected files/components

- `backend/app/Support/Authorization/PermissionCatalog.php`
- `backend/database/seeders/CompanyRolesPermissionsSeeder.php`
- implemented controllers and policies under `backend/app/Http/Controllers/Api` and `backend/app/Policies`
- `backend/app/Http/Controllers/Api/AuthController.php` or the `/me` response path
- frontend `src/routes/AppRoutes.jsx`, `src/components/layout/navigation.js`, and feature-level `can()` calls
- a new data migration for existing role-permission grants (after read-only preflight)

### Decisions supported by inspected code

- Keep current API URLs and manual scoped lookup; route-model-binding conversion is not required for security.
- Use one canonical permission per implemented action and a centralized transitional alias map.
- Migrate stored grants by semantic action, not by granting both broad groups.
- Preserve policy/membership checks in addition to permissions.
- Treat client role-name checks as data-visibility identity rules until explicitly redesigned.

### Unresolved questions/blockers

- Which vocabulary is the product contract: newer canonical names (`projects.edit`, `daily_reports.*`, `users.manage`, `company.settings`) or the granular legacy names already used end to end?
- Should `users.manage`/`company.settings` be aliases, bundles, or removed? Treating them as bundles could unintentionally broaden access.
- Are any external API clients using legacy permission strings?
- Should approvers/store keepers have an explicit permission to cancel someone else's submitted material request?

### Suggested commands/checks for the next task

```powershell
rg -n "can\(|hasPermissionTo|permission:|ProtectedRoute|permission:" backend/app backend/routes frontend/src
php backend/artisan route:list --path=api/v1 --except-vendor
php backend/artisan migrate:status --no-ansi
```

Before changing grants, run a read-only query/export of roles and their current permissions grouped by `company_id`, identify custom roles, and prepare an old-to-new permission matrix for review. After implementation, manually verify canonical-only custom roles against their matching UI and API actions, confirm adjacent actions remain 403, and verify cross-company IDs remain 404.
