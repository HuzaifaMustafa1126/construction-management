# Frontend Structure Refactor

## Scope and outcome

The frontend was reorganized from a mixed WowDash-template/application tree into a feature-based SaaS source tree. No route URL, API payload, permission rule, business workflow, theme option, or backend file was changed.

## Final maintained source tree

```text
frontend/src/
|-- api/{axiosClient.js,endpoints.js}
|-- components/
|   |-- layout/navigation.js
|   |-- tables/{DataTable.jsx,index.js}
|   `-- ui/{AppIcon.jsx,AppUI.jsx,Controls.jsx,app.css,index.js,icons/}
|-- features/
|   |-- auth/{context/,pages/}
|   |-- company/pages/
|   |-- dashboard/pages/
|   |-- departments/pages/
|   |-- platform/pages/
|   |-- profile/pages/
|   |-- roles/{components/,pages/}
|   |-- settings/pages/
|   `-- users/{legacy/,pages/,utils/}
|-- layouts/{PlatformLayout.jsx,WorkspaceLayout.jsx}
|-- lib/theme/
|-- routes/{AppRoutes.jsx,ProtectedRoute.jsx}
|-- App.jsx
`-- main.jsx
```

No empty `charts`, `forms`, `hooks`, or unimplemented business-feature folders were created.

## Ownership rules

- A capability owns its pages, feature-only components, context, tests, schemas, and utilities under `features/<name>`.
- Components used across capabilities belong under `components`, grouped by UI, table, or layout responsibility.
- Global theme infrastructure belongs in `lib/theme`; the Heroicons Outline registry remains singular under `components/ui/icons`.
- `layouts` owns application shells. `routes` owns guards and route composition. `App.jsx` only composes providers and the router.
- `api/axiosClient.js` is the sole active transport. New feature-only API adapters should be placed inside their feature when `endpoints.js` is decomposed incrementally.

## Files moved

- The former service client moved to `api/axiosClient.js`; `api/resources.js` moved to `api/endpoints.js`.
- Authentication context and pages moved to `features/auth`.
- Platform, settings, company, dashboard, users, roles, departments, and profile pages moved to their feature-owned `pages` directories.
- `WorkspaceLayout` and `PlatformLayout` moved to `layouts`.
- Route guards moved to `routes`; route declarations moved to `routes/AppRoutes.jsx`.
- Design-system controls and icons moved to `components/ui`; `DataTable` moved to `components/tables`; theme files moved to `lib/theme`.
- Maintained legacy Node user tests/utilities moved to `features/users/utils` and `features/users/legacy` so no tests were deleted.

## JavaScript-only runtime

The maintained application uses `.js` for non-JSX modules and `.jsx` for React components. TypeScript syntax, declarations, compiler configuration, and direct TypeScript-only dependencies were removed during Phase 2.5. Historical TypeScript files may remain under `legacy-template/`, which is outside the active import, build, lint, and test graph.

## Archived and removed from the runtime graph

The 461-file unrouted WowDash demo tree was moved, not destroyed, to `frontend/legacy-template/`. This includes demo pages and layers, child widgets/charts, `MasterLayout`, obsolete helpers/hooks/data, the duplicate Axios client, and legacy services. The archive is excluded from Vite entry imports and ESLint. Empty former source directories were removed.

No active functionality or test was deleted. The unused React logo asset was archived with the template assets.

## Import and routing conventions

Imports use explicit relative paths; no alias was introduced. Feature pages may import shared API, UI, layout, and theme infrastructure but must not import `legacy-template`.

Preserved routes include `/login`, password setup and verification routes, all `/platform/*` administration routes, `/dashboard`, `/company`, `/users`, `/roles`, `/departments`, `/profile`, `/profile/security`, and `/settings`. Platform and company route guards, permission gates, and route-level lazy loading remain intact.

## Verification

The initial structural refactor passed active-source lint, Vitest, and the production build. Phase 2.5 later expanded maintained coverage and converted the active runtime to JavaScript/JSX only. Current verification results are recorded in `FRONTEND_PHASE_2_FINAL_VERIFICATION.md`.

The separate retained Node user-management suite has five known historical schema/store failures. It remains outside the maintained `npm test` command and was not changed to conceal failures.
