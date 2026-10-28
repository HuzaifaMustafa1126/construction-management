# S7 Materials, Requests & Stock Ledger

Date: 2026-10-28

## Accounting model

`inventory_transactions` remains the authoritative ledger. Quantities are positive decimals and `direction` is `in` or `out`; balances are derived with `SUM(CASE direction WHEN 'in' THEN quantity ELSE -quantity END)`. No historical transaction is rewritten or backfilled.

The additive migration creates `material_requests`, `material_request_items`, and `inventory_transfers`. It adds nullable request-item, paired-transaction, and company-scoped idempotency references to the ledger.

- Creating, submitting, and approving a request does not change stock.
- Issuing an approved quantity creates `material_issued`/`out` ledger rows under inventory row locks.
- Returns create `material_returned`/`in` rows and cannot exceed issued quantity less earlier returns.
- Transfers lock both inventory rows in stable ID order and create linked, equal `transfer_out` and `transfer_in` rows in one database transaction.
- Existing direct consumption remains separate and uses its existing consumption-item source reference.

## API contracts

- `GET|POST /api/v1/material-requests`
- `GET|PATCH /api/v1/material-requests/{requestId}`
- `POST /api/v1/material-requests/{requestId}/submit`
- `POST /api/v1/material-requests/{requestId}/approve|reject|cancel`
- `POST /api/v1/material-requests/{requestId}/issue|return`
- `POST /api/v1/inventory-transfers`

Issue and transfer calls require a UUID `idempotency_key`. Quantities allow at most four decimal places. Transfers require the same material to be assigned to both authorized projects. New permissions are `material_requests.view|create|update|submit|approve|issue|return` and `inventory.transfer`.

## React workflows

The frontend provides `/material-requests`, `/material-requests/new`, `/material-requests/:id`, and `/inventory/transfers/new`. Routes and controls use effective permissions. TanStack Query keys contain company and user identity, and successful stock mutations invalidate request, inventory, project, and dashboard data.

## Verification

The isolated Laravel suite verifies that approval has no stock effect, partial issue deducts exactly once, repeat issue/return/transfer calls are idempotent, returns increase stock within the issued limit, and transfers preserve total stock with linked ledger entries. Catalog, project inventory, and inventory reporting regressions remain green.

Frontend ESLint, the configured Vitest regression suite, S7 schema tests, and the Vite production build pass. The build transforms 657 modules; the main JavaScript bundle is 470.75 kB raw and 149.75 kB gzip.

## Known limitation

Legacy records do not share a reliable physical-movement identifier. The application cannot prove that an old direct-consumption record and an old request-related physical movement were not duplicates, so it does not claim automatic legacy duplicate detection and does not rewrite history. New postings carry workflow-specific source references and document/idempotency guards. Existing delivery, direct-consumption, and adjustment APIs are preserved; dedicated React document-management screens for those pre-S7 workflows are not part of this request/issue/return/transfer slice.
