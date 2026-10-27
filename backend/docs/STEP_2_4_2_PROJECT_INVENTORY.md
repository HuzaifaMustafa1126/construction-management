# Step 2.4.2 — Project Inventory and Opening Stock

This phase assigns catalog materials to projects and records immutable opening-stock ledger entries. It does not implement receiving, consumption, wastage, adjustments, valuation, or editable stock balances.

## Schema

- `project_inventories`: tenant, project, material, minimum threshold, creator, timestamps, and soft deletion. A generated active-material key enforces one active assignment per project/material.
- `inventory_transactions`: immutable tenant/project/inventory/material ledger entries using `DECIMAL(18,4)`. A generated opening-stock key enforces one opening entry per inventory.

Current stock is always calculated as incoming quantities minus outgoing quantities. No `current_stock` column exists.

## Relationships

Company, Project, and Material expose inventories and transactions. ProjectInventory belongs to its company, project, material, and creator and has many transactions. InventoryTransaction belongs to the same entities. Deleted or inactive materials remain resolvable for audit history.

## Permissions

- `inventory.view`
- `inventory.create`
- `inventory.update`
- `inventory.opening_stock`

Company admins receive all four. Supervisors receive view access by default and require explicit create/update/opening-stock permissions plus project assignment. Clients have no inventory access. Permission alone never bypasses ProjectPolicy or ProjectInventoryPolicy.

## Endpoints

| Method | Endpoint | Purpose |
|---|---|---|
| POST | `/api/v1/projects/{id}/inventory` | Assign an active material |
| GET | `/api/v1/projects/{id}/inventory` | Filtered, paginated inventory |
| GET | `/api/v1/projects/{id}/inventory/overview` | Project inventory counts |
| GET | `/api/v1/projects/{id}/inventory/{inventoryId}` | Inventory details and balance |
| PATCH | `/api/v1/projects/{id}/inventory/{inventoryId}` | Update minimum stock only |
| POST | `/api/v1/projects/{id}/inventory/{inventoryId}/opening-stock` | Record opening stock once |
| GET | `/api/v1/projects/{id}/inventory/{inventoryId}/transactions` | Immutable ledger history |

The overview route is registered before the dynamic inventory-ID route.

## Requests

Assign a material:

```json
{"material_id":15,"minimum_stock_level":"50.0000"}
```

Record opening stock:

```json
{"quantity":"500.0000","transaction_date":"2026-10-21","notes":"Initial project stock count"}
```

Update threshold:

```json
{"minimum_stock_level":"100.0000"}
```

Quantities are accepted and returned as decimal strings, validated against the material unit's precision, and never converted to PHP floating point. Zero opening stock is represented by no transaction. A repeated opening-stock request returns `409` and never overwrites the original.

## Listing and history

Inventory supports `search`, `material_category_id`, `material_status`, `low_stock`, `sort` (`material_name` or `created_at`), `direction`, `page`, and `per_page` up to 100. Transaction history supports date range, transaction type, safe sorting, and pagination.

Balances and overview counts are derived from aggregate ledger queries. Responses include the material category and measurement unit without exposing tenant internals.

## Authorization and Postman tests

Use `Authorization: Bearer <TOKEN>`. Test with disposable projects and catalog records. Verify assigned supervisors can view, unassigned supervisors receive `404`, missing permissions receive `403` on otherwise accessible collections, clients cannot access records, and cross-company or wrong-project IDs return `404`. Protected ownership, type, direction, and stock fields must return `422`.

## Concurrency and corrections

Opening creation runs in a database transaction, locks the inventory row where supported, and is backed by a unique generated-column index. Duplicate-key races are returned as `409`. SQLite tests verify uniqueness and retry behavior, but SQLite in-memory cannot prove MariaDB row-lock timing. No disposable MariaDB test credentials are configured, so concurrent MariaDB execution remains unverified.

Opening stock is immutable: there are no transaction update or delete routes. Corrections will use compensating ledger transactions in Step 2.4.5.

## Known limitations

Only `opening_stock` transactions are supported. Receiving, consumption, adjustments, returns, wastage, negative-stock prevention for future outgoing transactions, and inventory valuation are deferred.
