# Step 2.4.5 — Stock Adjustments, Wastage & Inventory Corrections

This backend phase adds auditable, project-scoped stock reconciliation documents while retaining `inventory_transactions` as the only inventory balance source. No frontend, valuation, purchasing, return, transfer, or financial functionality is included.

## Schema

- `material_stock_adjustments`: tenant/project document header, company-unique `ADJ-{date}-{ULID}` number, draft/posted/cancelled status, date, reason, notes, creator, posting actor/time, cancellation actor/time, and soft deletion.
- `material_stock_adjustment_items`: inventory/material source, type, `DECIMAL(18,4)` quantity, controlled reason, notes, correction explanation, and optional original ledger reference.
- `inventory_transactions.stock_adjustment_item_id`: nullable source foreign key with a unique constraint, guaranteeing at most one ledger movement per adjustment item.

The migration is additive and leaves all existing opening, receiving, consumption, and ledger history unchanged.

## Types and directions

| Type | Direction | Purpose |
|---|---|---|
| `adjustment_in` | `in` | Physical stock exceeds system stock |
| `adjustment_out` | `out` | Physical stock is below system stock |
| `material_wastage` | `out` | Damage, breakage, theft, expiry, or site loss |
| `inventory_correction_in` | `in` | Positive compensating correction |
| `inventory_correction_out` | `out` | Negative compensating correction |

Reason codes are `physical_count_difference`, `damage`, `breakage`, `theft`, `expiry`, `site_loss`, `recording_error`, and `other`. `other` requires notes. Corrections require both an explanation and an existing original ledger transaction from the same tenant, project, and inventory assignment. The original row is never modified.

## Lifecycle

```text
Draft → validate all items and balances → atomically post ledger rows → Posted (terminal)
  └── cancel without ledger impact → Cancelled (terminal)
```

Draft editing replaces items atomically and never affects stock. Posted and cancelled documents are immutable. Posted documents cannot be cancelled or returned to draft.

## API and permissions

- `GET /api/v1/projects/{id}/stock-adjustments` — `stock_adjustments.view`
- `POST /api/v1/projects/{id}/stock-adjustments` — `stock_adjustments.create`
- `GET /api/v1/projects/{id}/stock-adjustments/{adjustmentId}` — `stock_adjustments.view`
- `PUT|PATCH /api/v1/projects/{id}/stock-adjustments/{adjustmentId}` — `stock_adjustments.update`
- `POST /api/v1/projects/{id}/stock-adjustments/{adjustmentId}/post` — `stock_adjustments.post`
- `POST /api/v1/projects/{id}/stock-adjustments/{adjustmentId}/cancel` — `stock_adjustments.cancel`
- `GET /api/v1/projects/{id}/stock-adjustments/history` — `stock_adjustments.view`
- `GET /api/v1/projects/{id}/stock-adjustments/summary` — `stock_adjustments.view`

Company admins receive all permissions. Site supervisors receive view/create defaults but need separately assigned update, post, or cancel permissions. Clients receive none. Permissions never bypass company ownership or project membership. Cross-tenant, wrong-project, and unassigned-project resources return `404`; missing permissions return `403`.

Example draft:

```json
{
  "adjustment_date": "2026-10-24",
  "reason": "Physical site count",
  "notes": "Monthly reconciliation",
  "items": [
    {
      "project_inventory_id": 10,
      "adjustment_type": "material_wastage",
      "quantity": "2.5000",
      "reason_code": "damage",
      "notes": "Water-damaged cement"
    }
  ]
}
```

Correction example:

```json
{
  "project_inventory_id": 10,
  "adjustment_type": "inventory_correction_in",
  "quantity": "3.0000",
  "reason_code": "recording_error",
  "correction_explanation": "Original deduction was 10; physical evidence confirms 7.",
  "reference_inventory_transaction_id": 125
}
```

Lists support number search, status/date/creator filters, safe sorting, and pagination capped at 100. History returns posted documents with source ledger IDs and correction references. Summary groups posted quantities by adjustment type, material, and measurement unit; incompatible units are never combined.

## Atomic stock integrity

Posting locks the document, rechecks draft status, locks all affected inventory rows in ascending ID order, revalidates material and correction references, calculates balances from the immutable ledger, validates every outgoing effect, inserts one source-linked transaction per item, and marks the document posted in one database transaction. Any failure rolls back the entire post.

Duplicate inventory assignments within one document are rejected. This deliberately prevents ambiguous mixed incoming/outgoing netting for the same material. Insufficient outgoing stock returns `422`, leaves the document draft, creates no rows, and cannot produce negative stock. Repeated posting returns `409`; the unique source constraint prevents duplicate movement even if application idempotency is bypassed.

Quantities remain decimal strings at API boundaries and `DECIMAL(18,4)` in storage. Unit precision is enforced. Comparisons and aggregation use the shared fixed-scale integer utility; PHP floating-point arithmetic is not used.

## Activity events

- `material_stock_adjustment.created`
- `material_stock_adjustment.updated`
- `material_stock_adjustment.posted`
- `material_stock_adjustment.cancelled`

Posted metadata includes tenant/project/document context, materials, quantities, directions, reason codes, transaction IDs, actor, and timestamp. Authentication credentials are excluded.

## Migration and tests

```bash
php artisan migrate
php artisan db:seed --class=CompanyRolesPermissionsSeeder
APP_ENV=testing DB_CONNECTION=sqlite DB_DATABASE=:memory: DB_URL= vendor/bin/phpunit
```

Tests must run after clearing cached configuration. In-memory SQLite covers lifecycle, all five mappings, decimal behavior, validation, tenant/project security, negative-stock rollback, corrections, idempotency, summaries, history, logging, and regressions. No disposable MariaDB test database is configured, so actual simultaneous row-lock behavior is not verified; never use the application database for concurrency testing.

## Database safety and limitations

Before implementation, local MySQL database `construction_management` contained 2 companies, 2 users, 6 roles, 66 permissions, 12 tokens, and zero projects, materials, inventories, deliveries, consumptions, or ledger rows. Backup `storage/app/private/backups/construction_management_pre_step_2_4_5_20261024.sql` has SHA-256 `23dfca08f96b5df348d530900f28eeb09c020565eb1646aabbf54508585fd0f4` and 33 table definitions.

The backup includes tables, data, and triggers but cannot be certified complete for routines/events: the local MariaDB system table `mysql.proc` requires `mysql_upgrade`, and the event scheduler is disabled. A fresh complete backup is required before production deployment.

Known limitations: no reversal workflow, approvals, transfers, returns, costing, valuation, warehouse support, or real MariaDB concurrency test. Step 2.4.6 is not included.
