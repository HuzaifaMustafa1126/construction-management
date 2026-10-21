# Step 2.4.3 — Material Receiving

This phase adds tenant suppliers and project delivery workflows backed by the immutable inventory ledger.

## Lifecycle

```text
Draft delivery → Confirm receipt → Post one incoming ledger row per item → Calculated stock increases
```

Drafts can be edited or cancelled and never affect stock. Received deliveries, items, and their ledger rows are immutable. Received deliveries cannot be cancelled or deleted.

## Schema

- `suppliers`: tenant-owned contact directory with active status and soft deletion.
- `material_deliveries`: tenant/project/supplier delivery header, generated unique number, lifecycle status, receiver and posting timestamps.
- `material_delivery_items`: exact `DECIMAL(18,4)` quantities linked to an inventory assignment and matching material.
- `inventory_transactions.delivery_item_id`: nullable source foreign key with a unique index. Existing opening-stock rows remain valid.

Soft-deleted suppliers and materials remain resolvable from historical deliveries.

## Permissions

- `suppliers.view`, `suppliers.manage`
- `material_deliveries.view`, `material_deliveries.create`, `material_deliveries.update`
- `material_deliveries.receive`, `material_deliveries.cancel`

Company admins receive full access. Supervisors receive supplier and delivery view access by default; mutations require explicit permission and project assignment. Clients have no access.

## APIs

Supplier endpoints provide list, create, details, PUT/PATCH, status, and soft deletion under `/api/v1/suppliers`.

Delivery endpoints:

- `GET|POST /api/v1/projects/{id}/material-deliveries`
- `GET|PATCH /api/v1/projects/{id}/material-deliveries/{deliveryId}`
- `POST /api/v1/projects/{id}/material-deliveries/{deliveryId}/receive`
- `POST /api/v1/projects/{id}/material-deliveries/{deliveryId}/cancel`

Example draft:

```json
{
  "supplier_id": 4,
  "delivery_date": "2026-10-21",
  "supplier_invoice_number": "INV-2026-001",
  "vehicle_number": "ABC-123",
  "delivery_note": "First site delivery",
  "items": [
    {"project_inventory_id": 10, "quantity": "200.0000", "notes": "Cement bags"},
    {"project_inventory_id": 11, "quantity": "500.0000", "notes": "Steel"}
  ]
}
```

Example receipt:

```json
{"received_at":"2026-10-21T14:30:00+05:00","notes":"Quantities verified"}
```

Lists support pagination up to 100, search, supplier/status filters, date ranges, and safe date/creation sorting.

## Validation and security

Company, project, delivery number, status, posting fields, and ledger fields are server-controlled. New deliveries require an active same-company supplier and at least one unique inventory assignment from the requested project. Quantities must be positive decimal strings within measurement-unit precision. Materials are never automatically assigned.

Cross-company, wrong-project, and unassigned-project resources return `404`. Missing permission on accessible modules returns `403`.

## Atomic receipt and double-post protection

Receipt confirmation locks the delivery, rechecks draft status, locks project inventories in deterministic order, creates all `material_received`/`in` ledger rows, and marks the delivery received in one transaction. Any failure rolls everything back. A unique `delivery_item_id` index prevents double posting at the database level; retries return `409`.

Delivery numbers use a date plus ULID generated server-side and are protected by a company-scoped unique index.

## Activity events

Supplier create/update/status/delete and delivery create/update/receive/cancel events include safe tenant/resource metadata and receipt transaction IDs. Authentication secrets are excluded.

## Postman checks

Use disposable suppliers, projects, materials, and inventories with bearer authentication. Verify tenant and project hiding, protected-field rejection, draft stock neutrality, unit precision, cancellation neutrality, receipt balance increases, retry conflicts, historical deleted-supplier visibility, and ledger immutability.

## Concurrency and limitations

SQLite verifies atomic rollback and database source uniqueness. No disposable MariaDB test credentials are configured, so real simultaneous receipt timing is not proven. Production MariaDB constraints are verified, but concurrency tests were not run against production.

Purchase orders, supplier payments, consumption, wastage, returns, reversals, stock adjustments, and valuation remain out of scope.
