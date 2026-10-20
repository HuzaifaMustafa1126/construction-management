# Step 2.4.1 — Material Catalog

All endpoints use `/api/v1`, Sanctum bearer authentication, account checks, and company context middleware. No stock quantities or project inventory records are stored by this phase.

## Schema and relationships

- `measurement_units`: shared system units (`company_id = null`) and tenant-owned custom units, precision/type/status, soft deletion.
- `material_categories`: tenant-owned category definitions, creator, status, soft deletion.
- `materials`: tenant-owned definitions linked to one category and one measurement unit, creator, status, soft deletion.
- Company has many categories, materials, and custom measurement units.
- Categories and units retain historical material relationships after soft deletion.

Generated normalized columns enforce case-insensitive uniqueness for active custom unit symbols per scope, category names per company, and material codes per company. Soft deletion makes a normalized key null, allowing reuse without deleting history.

## Permissions

- `materials.view`: list and view units, categories, and materials.
- `materials.create`, `materials.update`, `materials.delete`: material mutations.
- `material_categories.manage`: category mutations.
- `measurement_units.manage`: custom-unit mutations.

Company admins receive full access. Site supervisors are view-only at the catalog API boundary. Clients have no catalog access by default. Custom roles use their assigned permissions, subject to tenant isolation.

## Endpoints

| Method | Endpoint | Purpose |
|---|---|---|
| GET/POST | `/measurement-units` | List or create custom units |
| GET/PUT/PATCH/DELETE | `/measurement-units/{id}` | View, update, or soft-delete a unit |
| GET/POST | `/material-categories` | List or create categories |
| GET/PUT/PATCH/DELETE | `/material-categories/{id}` | View, update, or soft-delete a category |
| PATCH | `/material-categories/{id}/status` | Activate or deactivate a category |
| GET/POST | `/materials` | Filtered catalog or material creation |
| GET/PUT/PATCH/DELETE | `/materials/{id}` | View, update, or soft-delete a material |
| PATCH | `/materials/{id}/status` | Activate or deactivate a material |

List endpoints accept `search`, `is_active`, `page`, and `per_page` (maximum 100). Materials additionally accept `material_category_id`, `measurement_unit_id`, `sort` (`name` or `created_at`), and `direction`.

## Request examples

Custom unit:

```json
{"name":"Pallet","symbol":"plt","unit_type":"count","decimal_precision":0}
```

Category:

```json
{"name":"Cement","description":"Cement and related construction materials"}
```

Material:

```json
{
  "name":"Ordinary Portland Cement",
  "code":"OPC-001",
  "material_category_id":1,
  "measurement_unit_id":1,
  "description":"General construction cement",
  "specification":"50kg bag",
  "brand":"Example Brand"
}
```

Successful creation returns `201` with `data` and a message. Lists return `data` plus pagination `meta`. Validation errors return `422`; insufficient permission returns `403`; missing and cross-company resources return `404`.

## Validation and tenant isolation

Names, codes, and symbols are trimmed. Material codes accept letters, numbers, dots, underscores, and hyphens. Unit types are `count`, `weight`, `volume`, `area`, or `length`; precision is 0–6. New materials require an active category from the current company and an active system unit or current-company custom unit. Ownership and creator fields are assigned server-side and protected fields are rejected.

System units are visible to every tenant and cannot be changed or deleted by company users. Cross-company custom units, categories, and materials are hidden.

## Postman checks

Use `Authorization: Bearer <TOKEN>` and disposable users/data. Verify both company admins can see system units, tenant records return `404` across companies, supervisors can list but not mutate, clients are denied, inactive relationships are rejected, and case-only duplicates return `422`. Do not alter production administrators 5 or 11.

## Activity and limitations

Create, update, status, and deletion events contain actor/resource/company identifiers and safe changed fields. Authentication secrets are excluded.

This phase defines catalog metadata only. Receiving, consumption, adjustments, stock balances, valuation, and project-specific inventory are intentionally deferred to Step 2.4.2 and later work.
