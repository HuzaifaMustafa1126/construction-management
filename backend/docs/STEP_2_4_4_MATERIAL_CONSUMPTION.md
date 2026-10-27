# Step 2.4.4 — Material Consumption, Site Usage & Stock Deduction

This phase adds project-scoped material usage documents and posts deductions to the existing immutable inventory ledger. No frontend or valuation workflow is included.

## Schema

- `material_consumptions`: tenant/project header, company-unique server-generated number, date, draft/posted/cancelled status, purpose, work location, notes, creator, recorder, posting timestamp, and soft deletion.
- `material_consumption_items`: same-tenant inventory assignment, historical material reference, `DECIMAL(18,4)` requested quantity, notes, and one unique inventory assignment per document.
- `inventory_transactions.consumption_item_id`: nullable source foreign key with a unique index, enforcing at most one ledger entry for each consumption item.

The migration is additive. Existing `opening_stock` and `material_received` rows are unchanged. `material_consumed` uses direction `out`.

## Lifecycle

```text
Draft → validate every quantity and current balance → post all outgoing rows atomically → calculated stock decreases
  └── cancel (draft only, no ledger effect)
```

Drafts can be edited and do not affect inventory. Posting is irreversible in this phase. Posted and cancelled documents cannot be edited; posted documents cannot be cancelled. Corrections require a future compensating workflow.

## Permissions and authorization

- `material_consumptions.view`
- `material_consumptions.create`
- `material_consumptions.update`
- `material_consumptions.post`
- `material_consumptions.cancel`

Company admins receive all permissions. Site supervisors receive view access by default; mutations require explicit permissions and project assignment. Clients receive no access. Tenant and project access are always checked in addition to permissions. Cross-company, wrong-project, and unassigned-project resources return `404`; missing permission on an accessible project returns `403`.

## API

- `GET|POST /api/v1/projects/{id}/material-consumptions`
- `GET /api/v1/projects/{id}/material-consumptions/summary`
- `GET|PATCH /api/v1/projects/{id}/material-consumptions/{consumptionId}`
- `POST /api/v1/projects/{id}/material-consumptions/{consumptionId}/post`
- `POST /api/v1/projects/{id}/material-consumptions/{consumptionId}/cancel`
- `GET /api/v1/projects/{id}/material-consumptions/{consumptionId}/transactions`

Create example:

```json
{
  "consumption_date": "2026-10-23",
  "purpose": "Concrete foundation work",
  "work_location": "Foundation section A",
  "notes": "Morning shift material usage",
  "items": [
    {"project_inventory_id": 10, "quantity": "25.0000", "notes": "Cement"},
    {"project_inventory_id": 11, "quantity": "150.0000", "notes": "Steel"}
  ]
}
```

Creation returns `201` with a server-generated `CON-{date}-{ULID}` number. Lists support number search, status/date/recorder filters, safe sorting, and pagination capped at 100. Summary counts all lifecycle states and groups only posted usage by material and measurement unit.

## Posting and stock safety

Posting runs in one database transaction. It locks the document, confirms draft status, loads items, locks inventory rows in ascending ID order, validates tenant/project/material consistency, calculates each balance from the ledger, validates all requirements, inserts one `material_consumed`/`out` row per item, then marks the document posted. Any failure rolls back every insertion and the status change.

Stock remains calculated as:

```text
sum(incoming quantities) - sum(outgoing quantities)
```

Opening stock and received deliveries are therefore included automatically. Insufficient stock returns `422` with available and required quantities, and nothing is posted. A repeated post returns `409`. The unique `consumption_item_id` constraint is the final idempotency guard.

## Decimal rules

API quantities are decimal strings and database quantities use `DECIMAL(18,4)`. Validation enforces the material unit's precision and positive values. Balance comparisons and aggregation use scaled integer arithmetic; PHP floats are not used. Serialized quantities are normalized to the unit precision.

## Activity log

Events are `material_consumption.created`, `.updated`, `.posted`, and `.cancelled`. Metadata contains actor, company, project, document/number, timestamp, item information, and transaction IDs where applicable. Tokens and secrets are excluded.

## Testing

```bash
APP_ENV=testing DB_CONNECTION=sqlite DB_DATABASE=:memory: DB_URL= vendor/bin/phpunit
```

The test configuration explicitly uses in-memory SQLite and an empty `DB_URL`; configuration cache must be cleared before tests. Feature coverage includes authorization, ownership protection, precision, draft neutrality, exact stock deduction, opening/receiving aggregation, atomic insufficient-stock rollback, idempotency, source uniqueness, terminal immutability, filters, summary grouping, activity logs, and deleted-material history.

No disposable MariaDB test credentials are configured, so simultaneous-connection row-lock timing is not verified automatically. The production design uses transactions, deterministic `FOR UPDATE` row locks, document state rechecks, and source uniqueness, but SQLite does not prove MariaDB lock behavior.

## Production preservation and known limitations

The configured environment was `local` on MySQL database `construction_management`; no production migration or seed was run. Before implementation it contained 2 companies, 2 users, 6 roles, 43 permissions, 12 Sanctum tokens, and no projects, materials, project inventories, ledger rows, or deliveries. The repository baseline was 180 tests / 1007 assertions.

A table-and-trigger SQL backup was created at `storage/app/private/backups/construction_management_pre_step_2_4_4_20261024.sql`, verified non-empty with 31 table definitions, and recorded with SHA-256 `018e29bb751d512d7ef77213e0129ef5c2424637bd41ca9995dd85fd203d6af9`. Stored routines could not be included because the local MariaDB `mysql.proc` schema needs `mysql_upgrade`; the application database currently has no application-managed routine dependency. Event definitions were unavailable because the event scheduler is disabled.

Existing companies, users, roles, permissions, tokens, projects, materials, inventories, opening stock, receipts, reports, photos, and ledger history are preserved. A fresh verified backup is still required immediately before any later production deployment.

Out of scope: wastage, returns, adjustments, reversals, valuation, purchase orders, supplier payments, stored editable balances, frontend screens, and Step 2.4.5.
