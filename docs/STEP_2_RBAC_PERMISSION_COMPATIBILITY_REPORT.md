# Step 2 — RBAC Permission Compatibility Report

Date: 2026-10-29  
Scope: exact-equivalent permission compatibility only. Step 3 and other audit findings were not implemented.

## A. Executive Result

**IMPLEMENTED — P1-1 is resolved in application code.** Exact-equivalent legacy and canonical permission names now authorize the same action through one backend compatibility contract. Implemented backend and frontend consumers use canonical names, `/api/v1/me` returns a de-duplicated canonical effective-permission list, default role initialization uses canonical grants, and the role editor exposes one choice per implemented semantic action.

Existing legacy grants remain valid. Reserved/undefined grants are hidden from new selections and preserved when an existing role is edited. A preview-first, idempotent grant-upgrade command supports role and direct-user grants without removing legacy definitions or assignments.

**PASSED — connected database preview.** All four canonical definitions exist, all permission definitions use the `web` guard, no unsupported model grants or invalid assignments were found, and all roles holding mapped legacy grants already hold their canonical equivalent. Therefore the preview reports zero currently affected role/direct-user grants.

**NOT APPLIED — database mutation.** `--apply` was deliberately not executed. No grant rows were changed during Step 2. Manual browser/API acceptance remains **NOT VERIFIED**.

## B. Root Cause

`PermissionCatalog` advertised both canonical and legacy names as independent permissions while controllers and frontend screens predominantly checked legacy names. Spatie correctly treated each string as a different permission, so a canonical-only custom role could be denied by a legacy-named consumer. `/me` returned stored names without semantic normalization, and the role editor displayed duplicate choices.

## C. Final Permission Contract

- Implemented consumers request canonical names for project editing and report view/create/approve.
- The `User` authorization path resolves an exact-equivalent request against the canonical name and its legacy aliases. This covers Laravel Gate/`can()`, Spatie's Gate callback, controllers, policies/services that call `can()`, and permission middleware using the Gate.
- Resource policies still run because compatibility returns only permission possession through Spatie's existing Gate integration; it does not add a global Gate override that approves resource abilities.
- Report update and delete remain separate legacy-named actions because no exact canonical equivalent was defined.
- No permission implies create, edit, delete, approval, or publication beyond its existing semantic action.
- Tenant context, account/company status, membership, ownership, client identity, platform-admin, and last-admin safeguards are unchanged.

## D. Exact Alias Mapping Table

| Legacy | Canonical | Direction | Reason |
|---|---|---|---|
| `projects.update` | `projects.edit` | Bidirectional authorization; stored grants upgrade legacy → canonical | Both guard the existing project edit/status/progress action |
| `site_reports.view` | `daily_reports.view` | Bidirectional authorization; stored grants upgrade legacy → canonical | Same daily-report list/detail action |
| `site_reports.create` | `daily_reports.create` | Bidirectional authorization; stored grants upgrade legacy → canonical | Same daily-report creation action |
| `site_reports.approve` | `daily_reports.approve` | Bidirectional authorization; stored grants upgrade legacy → canonical | Same review/approval action; existing policy separately controls publication |

The authoritative backend mapping is `PermissionCatalog::LEGACY_TO_CANONICAL`. The frontend transition mapping is centralized in one helper module rather than repeated across components.

## E. Ambiguous/Reserved Permission Handling

`users.manage` and `company.settings` have no defined granular action contract and no current authorization consumers.

- **IMPLEMENTED:** marked reserved in `PermissionCatalog::RESERVED`.
- **IMPLEMENTED:** removed from new role-editor selectable groups.
- **IMPLEMENTED:** existing stored grants are returned as `preserved_permissions` and retained server-side during role updates.
- **IMPLEMENTED:** they are not expanded into user CRUD or company view/update grants.
- **IMPLEMENTED:** a client cannot create a new reserved grant through the current role API.
- **PASSED (read-only inventory):** each reserved permission has two role grants in the connected database. They were not changed.

Unrecognized stored grants receive the same preservation treatment. Hidden grants are not trusted from the browser; the server reads and merges them from the existing role.

## F. Backend Compatibility Implementation

- `PermissionCatalog` now defines aliases, reserved names, canonical normalization, equivalent-name lookup, and selectable groups.
- `User::hasPermissionTo()` wraps Spatie's original implementation and checks only the exact equivalence set. Missing definitions on one side are tolerated without granting unrelated access.
- Project edit/status/progress controllers now request `projects.edit`.
- Daily-report list/detail/create/review/visibility and report-photo access now request canonical `daily_reports.*` names.
- `RoleController` accepts canonical names and transitional exact legacy names, normalizes them, prevents ambiguous new grants, and preserves unrelated hidden grants.
- `CompanyRoleInitializer` and the role seeder now use canonical defaults and only synchronize newly created roles. Existing customized/default role grants are not overwritten by initialization or reseeding logic.
- `permissions:upgrade-canonical` defaults to preview, checks definitions/guards/model types/team integrity, and requires `--apply` for a transactional `insertOrIgnore` upgrade. It preserves legacy definitions and grants and clears Spatie's permission cache after successful application.

No API URL, authentication mechanism, tenant middleware, policy membership rule, or HTTP status convention was changed.

## G. Frontend Changes

- Added a central permission normalizer at `frontend/src/features/auth/permissions.js`.
- `AuthContext` canonicalizes both direct and role-derived permissions and canonicalizes requested permission names, preserving transition compatibility.
- Route guards, navigation, project edit controls, and report view/create/approve controls now use canonical permission names.
- Report update/delete remain fine-grained separate actions.
- The role editor receives canonical selectable groups, displays no duplicate alias choices, refreshes the current identity after a role mutation, and informs the administrator when reserved/unrecognized grants are being preserved.
- Existing layout and responsive components were retained; the role page was not redesigned.

## H. `/me` Compatibility

**IMPLEMENTED:** `/api/v1/me` combines role and direct-user permissions under the active Spatie company/team context, maps exact legacy names to canonical names, removes duplicates, and preserves existing response fields. Canonical effective permissions appear in both nested `user.effective_permissions` and the existing top-level `permissions` response field.

Older API clients may continue calling actions authorized by legacy names because backend compatibility is bidirectional. Transitional role-update payloads using the four exact legacy names are accepted and normalized. An older UI that only interprets raw legacy strings from `/me` without its own compatibility logic may hide a canonicalized action; deployment should update frontend and backend together.

No authentication tokens, passwords, or secrets were added to the response.

## I. Existing Grant Inventory

Connected database read-only inventory:

| Item | Result |
|---|---:|
| Permission definitions | 107 |
| Guards | `web` only |
| Default-role rows | 18 across two companies |
| Custom-role rows | 0 |
| Direct-user permission grants | 0 |
| Unsupported direct model grants | 0 |
| `projects.update` role grants | 4 |
| `site_reports.view` role grants | 16 |
| `site_reports.create` role grants | 6 |
| `site_reports.approve` role grants | 5 |
| `users.manage` role grants | 2 |
| `company.settings` role grants | 2 |

All mapped legacy role grants already have their canonical grant. No custom roles or direct-user grants exist in this connected database; the implementation nevertheless handles both.

Other environments are **NOT VERIFIED** and must run preview independently.

## J. Upgrade Preview Results

Command executed read-only:

```powershell
php artisan permissions:upgrade-canonical --no-ansi
```

Result: **PASSED**

- `projects.update` → `projects.edit`: 0 roles/direct users missing canonical grant; companies 1 and 2 contain preserved legacy grants.
- `site_reports.view` → `daily_reports.view`: 0 missing canonical grants; companies 1 and 2.
- `site_reports.create` → `daily_reports.create`: 0 missing canonical grants; companies 1 and 2.
- `site_reports.approve` → `daily_reports.approve`: 0 missing canonical grants; companies 1 and 2.
- Missing canonical definitions: none.
- Unsupported guards: none.
- Unsupported direct-grant model rows: 0.
- Invalid or ambiguous assignments: 0.
- Overall safe to apply: yes.

The command is idempotent: it adds only absent canonical pivot rows with `insertOrIgnore`. Legacy rows remain, so provenance is not deleted. Applying and previewing again is safe; after successful application, preview reports zero grants still needing addition.

Rollback limitation: removing canonical grants later cannot reliably reconstruct whether a canonical grant predated the upgrade because provenance is not stored. Roll back code independently; do not mass-delete canonical grants as an assumed database rollback.

## K. Exact Deployment Commands

Run from `backend` after deploying backend and frontend code together:

```powershell
php artisan permissions:upgrade-canonical --no-ansi
```

Review every row. Only when `Overall safe to apply: yes` and affected counts are understood:

```powershell
php artisan permissions:upgrade-canonical --apply --no-ansi
php artisan permission:cache-reset --no-ansi
```

Then deploy/build frontend using the project's normal release process. No migration or destructive reseed is required. Do not use the role seeder as the grant-upgrade mechanism.

## L. Database Mutation Status

**NOT APPLIED.** The connected database was inspected only. The upgrade command ran without `--apply`; no permission definitions, role grants, direct-user grants, assignments, companies, or application data were mutated.

Because this database already contains all canonical equivalents, applying would currently add zero grant rows. Other environments may differ.

## M. Files Created and Modified

Created:

- `backend/app/Console/Commands/UpgradeCanonicalPermissions.php`
- `frontend/src/features/auth/permissions.js`
- `docs/STEP_2_RBAC_PERMISSION_COMPATIBILITY_REPORT.md`

Modified for Step 2:

- `backend/app/Support/Authorization/PermissionCatalog.php`
- `backend/app/Models/User.php`
- `backend/app/Http/Controllers/Api/AuthController.php`
- `backend/app/Http/Controllers/Api/ProjectController.php`
- `backend/app/Http/Controllers/Api/DailySiteReportController.php`
- `backend/app/Http/Controllers/Api/SitePhotoController.php`
- `backend/app/Http/Controllers/Api/RoleController.php`
- `backend/app/Services/CompanyRoleInitializer.php`
- `backend/database/seeders/CompanyRolesPermissionsSeeder.php`
- `frontend/src/features/auth/context/AuthContext.jsx`
- `frontend/src/routes/AppRoutes.jsx`
- `frontend/src/components/layout/navigation.js`
- `frontend/src/features/projects/pages/ProjectsPage.jsx`
- `frontend/src/features/projects/pages/ProjectDetailsPage.jsx`
- `frontend/src/features/daily-reports/pages/DailyReportsPage.jsx`
- `frontend/src/features/daily-reports/pages/DailyReportDetailsPage.jsx`
- `frontend/src/features/roles/pages/RolesPage.jsx`

The worktree already contained unrelated/uncommitted changes from prior work; they were preserved.

## N. Checks Performed

| Check | Status | Result |
|---|---|---|
| PHP syntax on modified PHP files | **PASSED** | No syntax errors |
| Laravel Pint targeted check | **PASSED** | `vendor/bin/pint --test ...` passed after formatting two Step 2 files |
| ESLint | **PASSED** | `npm.cmd run lint` |
| Frontend production build | **PASSED** | Vite built 668 modules; Rollup emitted two non-fatal third-party Zod annotation warnings |
| Git whitespace | **PASSED** | `git diff --check` returned no errors |
| Static legacy consumer search | **PASSED** | Mapped legacy names remain only in the two centralized compatibility maps (test files excluded as required) |
| Command registration | **PASSED** | `permissions:upgrade-canonical` appears in Artisan command list |
| Read-only upgrade preview | **PASSED** | Safe, zero missing canonical grants |
| Automated tests | **NOT APPLICABLE** | Explicitly prohibited; none run |
| Browser/API mutation scenarios | **NOT VERIFIED** | Not executed |

The initial `npm run` attempts were blocked by the Windows PowerShell script policy. The equivalent `npm.cmd run lint` and `npm.cmd run build` commands were then executed successfully.

## O. Manual Acceptance Checklist

All scenarios below are **NOT VERIFIED** until executed manually in an isolated environment:

- [ ] Canonical-only `projects.edit` permits editing an assigned project.
- [ ] `projects.edit` does not permit project creation or deletion.
- [ ] Legacy-only `projects.update` remains compatible.
- [ ] Canonical report view, create, and approve grants work independently.
- [ ] Report update and delete remain separate.
- [ ] Direct-user legacy and canonical grants follow the same mapping.
- [ ] Editing a custom role refreshes effective permissions after `/me` refresh/login.
- [ ] Client approval/publication visibility restrictions remain enforced.
- [ ] An unassigned user remains unable to access a project.
- [ ] Foreign-company resource IDs return 404.
- [ ] Denied actions on accessible resources return 403.
- [ ] Editing a role preserves unrelated reserved/unrecognized grants.
- [ ] Upgrade preview/apply/preview is idempotent.

Recommended setup: create temporary roles/users in a disposable non-production database, test each permission in isolation, and record actual HTTP statuses and UI visibility. Do not use the connected production-like database for destructive acceptance setup.

## P. Remaining Findings

- **P1-3 remains:** material-request cancel lacks requester ownership parity with update/submit.
- Same-company composite foreign keys remain a future hardening task.
- Spatie company/team foreign keys remain a future hardening task.
- Role-name identity/action checks still require classification.
- Legacy permission definitions and grants are intentionally retained during compatibility transition.
- Runtime/manual acceptance is pending.

## Q. Next Step Handoff

### Step 2 status

P1-1 is **fully resolved in code** and **partially verified at runtime** through read-only database preview and build/static checks. Database grant application remains **pending by policy**, although the connected database currently needs zero canonical additions. Manual API/browser scenarios remain **NOT VERIFIED**.

### Relevant decisions and files

- Exact aliases live in `PermissionCatalog::LEGACY_TO_CANONICAL`.
- Backend compatibility is implemented at `User::hasPermissionTo()` so Gate/Spatie consumers share one path without bypassing resource policies.
- `/me` returns canonical, de-duplicated effective permissions.
- Frontend compatibility is centralized in `frontend/src/features/auth/permissions.js`.
- Reserved grants are preserved server-side and not selectable.
- Upgrade command: `permissions:upgrade-canonical`; preview is default, mutation requires `--apply`.

### Blockers before production rollout

- Run preview in every target environment.
- Review custom roles/direct grants in those environments; this connected database has none.
- Deploy backend/frontend together or accept that an old UI may not recognize canonical-only `/me` output.
- Complete the manual acceptance checklist.

### Recommended next task — P1-3 only

Implement material-request cancellation ownership parity without starting composite-FK work:

- Inspect `MaterialRequestController::update`, `submit`, and `cancel`.
- Require company admin or original requester for cancellation unless product owners explicitly define a separate privileged cancellation permission.
- Preserve project membership, tenant-scoped lookup, workflow status checks, and 404/403 behavior.
- Suggested manual acceptance: creator and company admin can cancel valid states; another assigned member with `material_requests.update` receives 403; inaccessible/foreign-company IDs return 404; invalid states remain rejected.

Do not infer approver/store-keeper cancellation authority from `material_requests.update`; define it explicitly if required.
