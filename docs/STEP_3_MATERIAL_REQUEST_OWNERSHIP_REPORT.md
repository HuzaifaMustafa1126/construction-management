# Step 3 — Material Request Ownership and Cancellation Authorization

## A. Executive Result

**IMPLEMENTED — P1-3 is resolved in application code.** Material-request update, submit, and cancel now use one company-admin-or-original-requester ownership predicate. Cancellation still requires `material_requests.update`, project visibility, tenant visibility, and an eligible `draft` or `submitted` state.

Runtime/API/browser acceptance is **NOT VERIFIED**. This report does not claim production readiness, and Step 2 manual acceptance remains pending.

## B. Verified Root Cause

Static inspection confirmed the audit finding in `MaterialRequestController`:

- Update required `material_requests.update`, `draft`, and company admin or `requested_by` ownership.
- Submit required `material_requests.submit`, `draft`, and company admin or `requested_by` ownership.
- Cancel required only `material_requests.update` and `draft`/`submitted` state.

Consequently, any assigned project member who could view the request and held `material_requests.update` could cancel another requester's eligible request. The only material-request cancellation API found is `POST /api/v1/material-requests/{requestId}/cancel`. The frontend API wrapper mapped to that route, but the material-request detail page had no cancellation control before this step.

## C. Before/After Authorization Rules

| Action | Before | After |
|---|---|---|
| Update | Viewable tenant/project record + `material_requests.update` + `draft` + admin/owner | Same, using the shared ownership predicate |
| Submit | Viewable tenant/project record + `material_requests.submit` + `draft` + admin/owner | Same, using the shared ownership predicate |
| Cancel | Viewable tenant/project record + `material_requests.update` + `draft`/`submitted` | Viewable tenant/project record + `material_requests.update` + admin/owner + locked `draft`/`submitted` recheck |

The company-admin bypass applies only to ownership. It does not bypass the action permission, company scope, project access, or state rules. Approver, Store Keeper, project membership, or update permission alone does not confer ownership.

## D. Shared Ownership Implementation

**IMPLEMENTED.** `MaterialRequestController::ownsRequestOrIsCompanyAdmin()` is used by update, submit, and cancel. It reuses the company-aware Spatie `hasRole('company_admin')` check under the established company/team middleware context. A non-admin matches ownership only when `requested_by` is non-null and equals the authenticated user ID.

The frontend uses `ownsMaterialRequestOrIsCompanyAdmin()` as one shared presentation predicate for Submit and Cancel. Backend authorization remains authoritative.

## E. HTTP Status Behavior

Static inspection shows the existing response contract is preserved:

- **IMPLEMENTED:** unauthenticated requests remain handled by Sanctum as 401.
- **IMPLEMENTED:** foreign-company IDs and inaccessible-project IDs are resolved through `find()` and remain 404.
- **IMPLEMENTED:** missing permission or failed ownership on an otherwise visible record returns 403.
- **IMPLEMENTED:** an ineligible cancel state continues to return the controller's existing 403 response.
- **IMPLEMENTED:** visibility is resolved before ownership or state denial is exposed.

Runtime confirmation of these status codes is **NOT VERIFIED**.

## F. Workflow State and Concurrency Handling

**IMPLEMENTED.** Cancellation now runs in a database transaction, obtains a row lock on the same-company material request, and rechecks `draft`/`submitted` after acquiring the lock. A transition committed before the lock is acquired prevents cancellation. Duplicate or stale cancellation attempts retain the existing denied-action behavior.

Approval, rejection, issuing, and returning implementations were not changed. No state graph or valid cancellation state was changed.

## G. Frontend Action Changes

**IMPLEMENTED.** The detail page now:

- Shows Submit only for an owner/company admin with `material_requests.submit` while the request is `draft`.
- Shows Cancel only for an owner/company admin with `material_requests.update` while the request is `draft` or `submitted`.
- Uses the existing confirmation dialog, busy/duplicate-click protection, API error normalization, and toast presentation.
- Performs no optimistic cancellation update.
- Uses the existing material-request mutation invalidation, which refreshes material-request list/detail keys plus the existing inventory, project, and dashboard keys after success.

The list page exposes only View, so there was no list cancellation control to change.

## H. Stock/History Preservation

**IMPLEMENTED by scope preservation; runtime effects NOT VERIFIED.** Cancellation changes only `material_requests.status`. No request items, inventory balances, inventory transactions, stock ledger entries, or audit/history writers were added or modified. Static searches found no material-request cancellation audit writer and no alternate cancellation endpoint.

## I. Files Created and Modified

Created:

- `frontend/src/features/material-requests/authorization.js`
- `docs/STEP_3_MATERIAL_REQUEST_OWNERSHIP_REPORT.md`

Modified:

- `backend/app/Http/Controllers/Api/MaterialRequestController.php`
- `frontend/src/features/material-requests/pages/MaterialRequestDetailsPage.jsx`

No schema, seed, role-grant, test, fixture, E2E, or test-configuration file was created or modified.

## J. Checks Actually Performed

| Check | Result |
|---|---|
| `php -l app/Http/Controllers/Api/MaterialRequestController.php` from `backend` | **PASSED** — no syntax errors |
| `.\\vendor\\bin\\pint --test app/Http/Controllers/Api/MaterialRequestController.php` from `backend` | **PASSED** |
| `npm.cmd run lint -- --quiet src/features/material-requests/pages/MaterialRequestDetailsPage.jsx src/features/material-requests/authorization.js` from `frontend` | **PASSED** |
| `npm.cmd run build` from `frontend` | **PASSED** — Vite built 669 modules; only third-party Zod annotation warnings were emitted |
| `git diff --check` | **PASSED** |
| Static `rg` search for cancellation routes, API calls, and duplicated ownership predicates | **PASSED** — one backend material-request cancel route and one frontend wrapper; shared backend/frontend predicates identified |

The first attempt to invoke ESLint with `npm` was **BLOCKED** by the Windows PowerShell execution policy for `npm.ps1`; the equivalent `npm.cmd` invocation subsequently **PASSED**.

Per the task restrictions, no automated test suite was run.

## K. Manual Acceptance Matrix

Use dedicated non-production records. No scenario below was executed during this step.

| Scenario | Expected | Status |
|---|---|---|
| A. Original requester with required permission cancels eligible request | Success | **NOT VERIFIED** |
| B. Company Admin with required permission cancels eligible request | Success | **NOT VERIFIED** |
| C. Another assigned member with `material_requests.update` cancels it | 403; no mutation | **NOT VERIFIED** |
| D. Original requester without required permission cancels it | 403; no mutation | **NOT VERIFIED** |
| E. User without project access cancels it | 404 | **NOT VERIFIED** |
| F. User from another company cancels it | 404 | **NOT VERIFIED** |
| G. Original requester cancels an ineligible state | Existing state denial; no mutation | **NOT VERIFIED** |
| H. `requested_by` is null | Non-admin denied; authorized admin behavior retained | **NOT VERIFIED** |
| I. Update and submit enforce the same ownership rule | Owner/admin allowed subject to permission/state; other member denied | **NOT VERIFIED** |
| J. Approval, issue, return, and ledger behavior unchanged | No regression | **NOT VERIFIED** |
| K. Frontend controls reflect ownership and state | Correct Submit/Cancel visibility | **NOT VERIFIED** |

For denial cases, compare request status/items and inventory/ledger/history before and after the call to confirm no mutation. Do not use valuable records.

## L. Remaining Unverified Behavior

- All API status and no-mutation scenarios in the manual matrix.
- Browser visibility and interaction for requester, company admin, other project member, and null-requester records.
- Live concurrent cancel versus approval/rejection behavior under the deployed database isolation configuration.
- Step 2 compatibility manual acceptance, which remains pending and was not marked complete.

## M. Remaining Audit Findings

- **P1-1:** resolved in code by Step 2, with its manual acceptance still pending.
- **P1-2:** same-company relationship integrity remains primarily application-enforced.
- **P1-4:** RBAC company integrity still lacks the audited database foreign keys.
- **P2-1:** inventory-transfer readback lacks an explicit company filter.
- **P2-2:** role-name checks still encode some access tiers outside permissions.

No Step 2 compatibility concern was discovered while implementing this change. The centralized canonical permission compatibility path remains in use; `permissions:upgrade-canonical --apply` was not run.

## N. Next Step Handoff

P1-3 is **IMPLEMENTED** in code, with manual acceptance **NOT VERIFIED**. Relevant decisions and files are:

- Backend ownership is centralized in `MaterialRequestController::ownsRequestOrIsCompanyAdmin()`.
- Cancel retains `material_requests.update`; no new privileged cancellation permission was introduced.
- Cancel eligibility remains `draft` or `submitted` and is rechecked under a row lock.
- Frontend ownership visibility is centralized in `frontend/src/features/material-requests/authorization.js`.
- Tenant lookup, project policy access, effective permission compatibility, stock accounting, and other workflow actions remain in their existing paths.

Tenant-integrity risks P1-2 and P1-4 remain. The recommended next task is **STEP 4 — EXHAUSTIVE READ-ONLY TENANT RELATIONSHIP PREFLIGHT**, covering tenant parent/child links, project memberships, inventory/material relationships, RBAC company/team consistency, orphans and cross-company mismatches, and nullable/global exceptions. Do not begin foreign-key migrations until that read-only preflight is reviewed.
