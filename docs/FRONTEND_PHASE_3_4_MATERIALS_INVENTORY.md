# Frontend Phase 3.4 — Materials and Inventory

Date: 2026-10-28

The `frontend/src/features/materials` module adds lazy routes for `/materials`, `/materials/new`, `/materials/:id`, `/materials/:id/edit`, and `/projects/:id/inventory` using the centralized client, identity-scoped TanStack Query keys, shared UI/DataTable, React Hook Form, and Zod.

The catalog implements backend search/filter/pagination, create, details, edit, and soft deletion. Fields are name, code, category ID, unit ID, description, specification, brand, and active status. Project inventory renders Laravel-calculated balances, unit precision, minimum levels, reliable overview metrics, low-stock filtering, assignment, one-time atomic opening stock, and immutable transaction history. Project details links to the scoped inventory.

Laravel also supports suppliers, draft/receive deliveries, draft/post consumptions, draft/post/cancel adjustments, and inventory reporting. Those multi-document UIs remain **BLOCKED** in this implementation slice; no direct stock edit or simulated transfer was introduced. Laravel has no transfer endpoint.

Security remains project- and tenant-scoped. Clients are denied the catalog; inventory requires project policy plus effective permission. Browser payloads never include company IDs or balances.

Verification: Vitest passed 18 files/49 tests; Laravel catalog, project-inventory, and reporting tests passed 23 tests/213 assertions; Vite built 650 modules with main JS 469.35 kB raw/149.45 kB gzip and CSS 32.93 kB raw/6.73 kB gzip. Manual browser/device/accessibility QA was not tested.
