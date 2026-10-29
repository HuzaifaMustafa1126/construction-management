# Step 4 — Read-Only Tenant Relationship Integrity Preflight

## A. Executive Result

**IMPLEMENTED, EXECUTION BLOCKED.** A reusable read-only command now exists as `php artisan tenancy:integrity-preflight`. It inventories deployed foreign keys at runtime and applies explicit tenant, project, material, attribution, inventory, transfer, global-unit, polymorphic, and Spatie team checks using deterministic identifiers, full violation counts, and bounded identifier-only samples.

The live database inspection is **BLOCKED** because the configured MySQL endpoint refused the connection. No database identity was verified and no relationship is reported clean. No data, schema, grants, caches, sessions, or application workflows were changed. This is not a production-readiness statement.

## B. Database Identity and Inspection Scope

| Property | Result |
|---|---|
| Resolved Laravel connection name | `mysql` |
| Configured driver | MySQL; live driver/server handshake **NOT VERIFIED** |
| Database name | **NOT VERIFIED**; the connection error referenced configured target `construction_management`, but `database()` did not execute |
| Server version | **NOT VERIFIED** |
| Server read-only mode | **NOT VERIFIED** |
| Intended target reached | **BLOCKED** — TCP connection to the configured local MySQL endpoint was actively refused |

The command prints no password, token, connection URL, email address, names, or other personal data. Samples contain only trusted table/type labels and numeric record identifiers.

Application bootstrap inspection found policy, rate-limiter, and password-reset URL registration only. The command does not clear permission/config/application caches, establish a tenant session, run migrations, or invoke model events.

## C. Relationship Coverage Matrix

Because database connection failed before schema metadata could be read, every live row result below is **NOT EXECUTED**.

| Area | Registered coverage | Live result |
|---|---|---|
| Deployed schema | Required-table presence; every declared FK discovered from `information_schema` | **NOT EXECUTED** |
| Tenant ownership | Every deployed table with `company_id`; missing/null parent; column type/nullability; soft-deleted and inactive company lifecycle | **NOT EXECUTED** |
| Users/departments | User department company equality; nullable company exceptions | **NOT EXECUTED** |
| Projects/memberships | Pivot/project/user/assigner company equality; duplicates; assignment allowlist | **NOT EXECUTED** |
| Reports/workers/photos | Parent existence, company/project equality, optional report alignment, user attribution | **NOT EXECUTED** |
| Catalog | Category/material company equality; shared/system measurement-unit rule | **NOT EXECUTED** |
| Project inventory | Project/material/company alignment | **NOT EXECUTED** |
| Deliveries | Supplier/project/document/item/inventory/material alignment | **NOT EXECUTED** |
| Consumptions | Project/document/item/inventory/material alignment | **NOT EXECUTED** |
| Adjustments | Project/document/item/inventory/material/reference transaction alignment | **NOT EXECUTED** |
| Requests | Project/document/item/inventory/material alignment | **NOT EXECUTED** |
| Ledger | Inventory/project/material consistency; typed document references; related transfer leg | **NOT EXECUTED** |
| Transfers | Source/destination inventory, project, company, and material consistency | **NOT EXECUTED** |
| RBAC | Role/team/user company equality; guards; missing subjects; unsupported morph types | **NOT EXECUTED** |
| Polymorphic references | Trusted inventory reference allowlist; partial and unknown references | **NOT EXECUTED** |

The runtime FK inventory includes soft-deleted rows because it uses physical tables directly and does not apply Eloquent global scopes.

## D. Check Registry and Rules

The command uses these deterministic families:

- `schema.required_tables`: registry prerequisites.
- `foreign_key.<table>.<column>`: every deployed single-column FK has a physical parent. The current schema declares single-column references; future multi-column deployed constraints must be reviewed before relying on this generic form.
- `company.<table>.parent`: every deployed `company_id` resolves, with null allowed only for `users`, `roles`, and `measurement_units` pending their specific rules.
- `company.lifecycle.*`: separates soft-deleted and inactive/suspended companies from referential corruption.
- `attribution.<table>.<column>.company`: every non-null tenant attribution user belongs to the row company.
- Named project, report/photo, catalog, inventory, workflow-document, ledger, and transfer checks: enforce company plus semantic project/material alignment.
- `rbac.*`: roles, direct grants, team IDs, user ownership, guards, subject existence, and supported model types.
- `polymorphic.inventory_transactions.*`: reference completeness and trusted types only.

All SQL table and column identifiers come from a fixed registry or validated schema metadata (`[a-z0-9_]+`). User input cannot supply SQL identifiers. `--sample-limit` is integer-clamped to 1–100. Counts cover all matching rows; only samples are bounded.

Exit codes are:

- `0`: complete inspection with zero violations.
- `1`: complete inspection with one or more violations.
- `2`: blocked or incomplete inspection. Incomplete execution can never appear clean.

The optional `--json` output carries database identity, summary, status, counts, samples, and reasons.

## E. Execution Results

Command executed:

```text
php artisan tenancy:integrity-preflight --json --sample-limit=10
```

Result: **BLOCKED**, process exit code `2`.

The first identity query (`database()`, `version()`, and `@@read_only`) could not connect because the configured local MySQL endpoint refused the connection. Inspection stopped before schema or business-table queries. This is correct fail-closed behavior.

No “zero violations” results exist. Empty-table behavior was not observed; when reachable, an empty checked table may yield zero violations but does not prove its workflow works.

## F. Violations and Bounded Identifier Samples

**NOT APPLICABLE for this execution.** No scan ran, so there are no verified violations and no samples. Connection failure is a blocker, not evidence of integrity.

When executable, the command counts every violation and displays at most the requested sample limit. It does not dump business fields or personal data.

## G. Nullable/Global Exceptions

The registry explicitly models:

- `users.company_id`: null is permitted only for the application's platform-administrator identity model; tenant relationships separately reject a company-less user.
- Permanent-user-deletion attribution fields: nullable attribution is valid; non-null dangling or cross-company attribution is invalid. The future constraint design must preserve `nullOnDelete` behavior.
- `measurement_units.company_id`: null is valid only when `is_system = 1`. A material may reference a global/system unit or a same-company unit, never another company's unit.
- `roles.company_id`: the deployed tenant RBAC design expects company roles; null/global roles are reported as violations.

A simple composite material-to-unit FK cannot directly express “same company OR valid global system row.” That relationship needs separate same-company/global paths, a normalized ownership key design with carefully enforced system semantics, or continued application enforcement plus checks.

Composite `SET NULL` attribution FKs are also unsafe if they include non-null `company_id`: MySQL would attempt to null every child FK column. Nullable user attribution therefore needs a design that preserves company ownership independently, such as a simple user FK plus another enforcement mechanism or a dedicated nullable attribution ownership key.

## H. RBAC Integrity Findings

Live findings are **NOT VERIFIED**. Registered checks cover configured Spatie tables and keys:

- `roles.company_id` resolves and is not unintentionally global.
- `model_has_roles.company_id` equals both role company and User company.
- `model_has_permissions.company_id` equals User company.
- Permission/role guard names align with the application's `web` guard.
- Role-permission guards agree.
- User subjects exist physically, including soft-deleted users.
- Unsupported morph subject types are reported, not queried dynamically.

Ordinary foreign keys can ensure that role, permission, team, and user rows exist, but cannot alone ensure role-company = pivot-team = user-company. That equality requires composite keys/constraints or equivalent controlled enforcement.

## I. Polymorphic/Unsupported References

The trusted inventory reference allowlist is:

- `App\Models\MaterialDeliveryItem`
- `App\Models\MaterialConsumptionItem`
- `App\Models\MaterialStockAdjustmentItem`
- `App\Models\MaterialRequestItem`
- `inventory_transfer`

Unknown reference types are counted with identifier/type-only samples and treated as violations. The command never instantiates or queries an arbitrary class. Partial `(reference_type, reference_id)` pairs are also violations.

Spatie morphs explicitly support `App\Models\User`; unknown types are reported. Activity-log and personal-access-token polymorphic references are excluded from tenant equality enforcement: they are framework/history infrastructure, lack a tenant ownership column, and can legitimately retain or remove targets according to their package lifecycle. Their ordinary physical target integrity is therefore **UNSUPPORTED/NOT VERIFIED** by this business registry and must not be interpreted as valid.

Polymorphic references cannot be enforced by one ordinary FK because the target table varies by type. Trusted type-specific columns, trigger-based enforcement, or application checks plus recurring preflight are the practical alternatives.

## J. Performance and Coverage Limitations

- The scan is a point-in-time observation and cannot prevent a later invalid write or eliminate races.
- Checks use set-based joins/anti-joins and avoid per-row Eloquent queries.
- Full counts may scan large child tables where no suitable composite index exists; identifier samples alone are limited.
- Lifecycle union checks scan every non-null tenant-owned table and may be comparatively expensive.
- The configured server/account read-only status was not verified. The implementation itself contains only `SELECT`/metadata reads and no lock clause.
- Schema-dependent checks were not exercised because the server was unavailable.
- Activity-log, tokens, sessions, cache, queue, and password-reset infrastructure are not treated as tenant business aggregates.
- Source inspection cannot replace a reachable deployed-schema scan; deployed drift remains unknown.

## K. Future Constraint Dependency Plan

This is a plan only; no DDL was written or executed.

1. **Restore read-only inspection access and rerun the preflight.** Capture server version, table sizes, indexes, column definitions, and all blockers. Repair decisions require separate approval.
2. **Parent tenant keys.** Add/confirm unique `(company_id, id)` keys on tenant parents in dependency order: companies/users/projects/categories/materials/suppliers, then project inventories and workflow documents, then item/detail tables. Verify unsigned bigint order/type/nullability exactly.
3. **Low-risk tenant children first.** Candidate first batch after a clean scan: `daily_site_report_workers(company_id, daily_site_report_id)` → reports, and workflow item `(company_id, document_id)` → document parents. Use restrictive deletion except existing intentional detail cascades, which require deliberate composite-cascade review.
4. **Project-bearing documents.** Enforce `(company_id, project_id)` against projects for reports, photos, inventories, deliveries, consumptions, adjustments, requests, and both transfer project columns. Parent composite uniqueness must exist first.
5. **Semantic inventory alignment.** Same-company FKs are insufficient. Project inventory needs company/project/material keys that children can reference so document project, inventory project, and material identity cannot diverge. This may require composite unique keys such as `(company_id, project_id, material_id, id)` and matching ordered child indexes.
6. **Ledger and transfer relationships.** Add constraints only after project/material semantic keys exist. Preserve related-transaction nullability and verify transaction-type rules remain application/conditional constraints.
7. **RBAC teams.** Plan composite role/team and user/team enforcement separately. Existing Spatie primary keys and package upgrade compatibility must be reviewed; simple company FKs do not enforce equality.
8. **Nullable attribution.** Preserve permanent-deletion cleanup. Do not use a composite `SET NULL` that can null `company_id`; choose an explicit compatible design.
9. **Shared measurement units and polymorphs.** Exclude from unconditional tenant composite FKs. Document alternative enforcement and recurring checks.
10. **Operational migration review.** Before each small batch, assess MySQL/MariaDB version support, online DDL behavior, table size, lock duration, replication impact, backup/rollback path, and restrictive-delete consequences.

No relationship is currently eligible to be declared clean for Step 5 because live coverage is incomplete.

## L. Files Created and Modified

Created:

- `backend/app/Console/Commands/TenantIntegrityPreflight.php`
- `docs/STEP_4_TENANT_INTEGRITY_PREFLIGHT_REPORT.md`

No application authorization/workflow, migration, seeder, database, permission grant, frontend, test, fixture, E2E, package, or testing-configuration file was changed for Step 4.

## M. Checks Actually Performed

| Command/check | Result |
|---|---|
| `php -l app/Console/Commands/TenantIntegrityPreflight.php` | **PASSED** |
| `.\vendor\bin\pint app/Console/Commands/TenantIntegrityPreflight.php` | **PASSED** after formatting the new file |
| `.\vendor\bin\pint --test app/Console/Commands/TenantIntegrityPreflight.php` | **PASSED** |
| `php artisan list --raw` filtered for `tenancy:integrity-preflight` | **PASSED** — command registered |
| Static mutation-keyword search of the command | **PASSED** — matches were field/descriptive words only; no write query, DDL, transaction, or lock path exists |
| `php artisan tenancy:integrity-preflight --json --sample-limit=10` | **BLOCKED**, exit `2` — configured MySQL endpoint refused connection |
| `git diff --check` | **PASSED** before report creation; rerun in final review |

No automated tests or frontend build were run. No package was installed. No migration, seeder, grant upgrade, cache-clear, or repair command was run.

## N. Pending Steps 2 and 3 Manual Acceptance

- Step 2 RBAC permission-compatibility manual API/browser acceptance remains **NOT VERIFIED**.
- Step 3 material-request ownership/cancellation manual acceptance matrix remains **NOT VERIFIED**.

Neither is marked passed by this step.

## O. Next Step Handoff

The next action is to make the intended database available to a trusted operator/read-only account and rerun:

```text
php artisan tenancy:integrity-preflight --json --sample-limit=10
```

Only after a complete result should **STEP 5** be scoped. If violations appear, Step 5 should first approve precise, auditable repair options without automatic repair. If the eligible relationships are clean, begin with a small constraint batch for tenant detail-to-parent relationships, after confirming composite parent keys, matching child indexes/types, table sizes, and lock behavior.

Carry forward P2-1 (inventory-transfer readback company filtering), P2-2 (role-name access classification), Steps 2/3 manual acceptance, shared/global measurement-unit handling, nullable attribution design, RBAC team equality, and polymorphic exclusions. Do not declare all relationships safe while this live preflight remains blocked.
