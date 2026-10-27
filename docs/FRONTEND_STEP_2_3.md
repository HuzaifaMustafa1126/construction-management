# Frontend Step 2.3

Implemented routes are `/company`, `/users`, `/roles`, and `/departments`. They use the shared design system and company-scoped TanStack Query keys.

User management supports server search/filter/pagination, CRUD, exact `Active|Inactive|Suspended` status changes, role-name assignment, and department assignment. Creation sends only backend-supported identity, password, and role fields; optional department assignment uses its dedicated endpoint and reports partial failure explicitly.

Role create/update sends `{ name, permissions: string[] }`. Permission groups and names come exclusively from `/permissions`. Department updates exclude status; activation uses its dedicated endpoint. Deletion waits for server confirmation, and assigned department users are loaded from the real department-users endpoint.

No company request accepts a browser-selected company ID. Effective permissions control presentation while Laravel tenant middleware remains authoritative. Platform fields never appear in company forms.

The global DataTable sorts within the current server page; each feature owns server search, filters, and pagination. Legacy template lint and legacy user-schema Node tests remain outside this step.
