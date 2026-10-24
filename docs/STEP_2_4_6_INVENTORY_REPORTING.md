# Step 2.4.6 — Inventory Reports, Stock Alerts & Movement Analytics

This backend-only phase provides real-time and historical inventory reporting from the immutable `inventory_transactions` ledger. Reports never create movements, update balances, or persist alerts.

## Architecture

`InventoryReportingService` owns tenant/project scoping, SQL ledger aggregation, historical balance calculation, movement filtering, and stock classification. `InventoryReportController` handles authorization and response composition. Dedicated requests validate filters, while stock and movement resources normalize decimal strings and source metadata.

No reporting table or cached authoritative balance was added. Existing project/date and inventory/type ledger indexes support the report queries, so Step 2.4.6 requires no migration.

## Project endpoints

- `GET /api/v1/projects/{id}/inventory-reports/overview`
- `GET /api/v1/projects/{id}/inventory-reports/current-stock`
- `GET /api/v1/projects/{id}/inventory-reports/alerts`
- `GET /api/v1/projects/{id}/inventory-reports/movements`
- `GET /api/v1/projects/{id}/inventory-reports/received-vs-consumed`
- `GET /api/v1/projects/{id}/inventory-reports/wastage`

## Company endpoints

- `GET /api/v1/inventory-reports/overview`
- `GET /api/v1/inventory-reports/projects`
- `GET /api/v1/inventory-reports/alerts`

Company reports are always tenant-scoped. Company admins see their company's projects. A non-admin with an explicitly granted company-report permission remains restricted to assigned projects. Clients have no default access.

## Filters

Supported where applicable:

- `start_date`, `end_date` as inclusive `YYYY-MM-DD` business movement dates
- `material_id`, `material_category_id`
- `transaction_type`, `direction`
- `source_document`: `opening_stock`, `material_delivery`, `material_consumption`, or `stock_adjustment`
- `actor_id`, `reason_code`
- safe sorting and pagination up to 100 rows

`company_id` is prohibited. Invalid ranges, types, directions, sources, and sorting return `422`.

`transaction_date` is the business movement date and drives historical reports. `created_at` remains available in movement output as the posting timestamp. Backdated entries therefore affect the historical business-date period in which their transaction date falls.

## Stock calculations

For each project inventory assignment, one grouped SQL query calculates:

```text
current stock = sum(in quantities) - sum(out quantities)

opening balance = all signed movements before start_date
period in       = incoming movements from start_date through end_date
period out      = outgoing movements from start_date through end_date
closing balance = opening balance + period in - period out
```

When no date filter is supplied, current stock is the balance as of now. With `end_date`, future movements are excluded. Transactions are aggregated directly from the ledger once, so posted source documents are not counted again and drafts/cancellations cannot leak into totals.

The current-stock report separately returns opening stock, receipts, consumption, adjustment-in/out, wastage, and correction-in/out. Received-versus-consumed explicitly excludes opening stock and corrections from receipts. Its difference is informational and is not presented as current stock.

Quantities are serialized using measurement-unit precision. SQL `DECIMAL` aggregation and the shared signed fixed-scale utility avoid PHP floating-point arithmetic. Quantities from incompatible units are never combined; overview period totals and wastage breakdowns remain material/unit-specific.

## Classification and alerts

- `negative_stock_anomaly`: current stock below zero
- `out_of_stock`: current stock equals zero
- `low_stock`: positive stock at or below a positive minimum threshold
- `healthy`: positive stock above the threshold, or positive stock when the threshold is zero/unconfigured under existing semantics

Low stock severity is `warning`; out-of-stock and negative anomalies are `critical`. Alerts are calculated dynamically and are never stored or sent externally.

## Movement and wastage reporting

Movement history includes opening stock, receipts, consumption, adjustments, wastage, and corrections, with deterministic newest-first ordering. It returns ledger identity, material/unit, direction, quantity, business date, source document identity/number, actor, reason, notes, and creation timestamp. Soft-deleted materials remain readable through historical relationships.

The wastage endpoint counts only posted `material_wastage` ledger entries whose adjustment source is posted. Entries are paginated; breakdowns group by reason, material, and unit to prevent cross-unit totals.

## Permissions and security

- `inventory_reports.view`
- `inventory_reports.view_company`
- `inventory_reports.view_movements`
- `inventory_reports.view_alerts`

Company admins receive all four. Site supervisors receive project view, movement, and alert permissions by default, but only for assigned projects. They do not receive company-wide access. Clients receive none. Cross-company and unassigned projects return `404`; missing permissions return `403`; unauthenticated requests return `401`.

## Performance

- Stock and period totals use grouped SQL aggregation instead of per-inventory ledger queries.
- Movement history and wastage entries use database pagination.
- Source relationships are eager-loaded.
- Recent movement lists are limited to ten rows.
- Company health summaries aggregate already grouped inventory rows, not raw ledger history.
- Existing `inventory_tx_company_project_date_idx` and `inventory_tx_inventory_type_idx` indexes match the principal filters; no speculative index migration was added.

The service avoids N+1 balance queries. Very large company-wide reports still build project-health summaries from one aggregated row per inventory assignment in application memory; further streaming or asynchronous export is outside this phase.

## Testing

```bash
php artisan config:clear
APP_ENV=testing DB_CONNECTION=sqlite DB_DATABASE=:memory: DB_URL= vendor/bin/phpunit
```

Focused coverage verifies complete type aggregation, historical opening/period/closing balances, date cutoffs, decimal precision, stock statuses, dynamic alerts, all movement filters, deterministic pagination, source documents, archived materials, received-versus-consumed semantics, wastage, company scoping, project assignment, client denial, protected scope, and regressions.

Tests use isolated in-memory SQLite. No disposable MariaDB reporting/concurrency database is configured; no destructive tests run against the application database.

## Database preservation and limitations

Before implementation, local MySQL database `construction_management` contained 2 companies, 2 users, 6 roles, 71 permissions, 12 tokens, and zero projects, materials, inventories, or ledger movements. The verified backup is `storage/app/private/backups/construction_management_pre_step_2_4_6_20261024.sql`, SHA-256 `ac9decc4b512765c7461887f152c8fcffc6af3bea494dad1ef2d739f28a523c8`, with 35 table definitions.

The backup includes tables, data, and triggers but cannot be certified complete for routines/events because the MariaDB `mysql.proc` schema requires `mysql_upgrade` and its event scheduler is disabled. No Step 2.4.6 migration is required or applied; only the additive permission seeder is needed after verification.

PDF/Excel exports, charts, persistent notifications, email/SMS/push alerts, valuation, costing, purchasing, transfers, returns, and Step 2.4.7 are out of scope.
