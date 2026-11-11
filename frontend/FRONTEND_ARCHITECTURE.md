# Frontend Architecture

## Runtime

React 18 and Vite provide the SPA shell. React Router owns public, guest, platform, authenticated, and permission-aware routes. TanStack Query owns server state, React Hook Form and Zod own forms, and Zustand stores browser-only theme preferences.

The active application is JavaScript/JSX only. Runtime modules use `.js`, React components use `.jsx`, and the project has no TypeScript compiler configuration or direct TypeScript tooling dependencies. Historical files under `legacy-template/` are outside this maintained-source rule and are never imported.

## Source organization

- `src/api/`: the single Axios client and shared endpoint adapters.
- `src/components/`: reusable UI, table, and layout building blocks.
- `src/features/<feature>/`: pages, components, context, tests, and utilities owned by a business capability.
- `src/layouts/`: company and platform application shells.
- `src/routes/`: route guards and the complete lazy route registry.
- `src/lib/theme/`: shared theme state, provider, and CSS tokens.
- `src/App.jsx`: provider and router composition only.
- `src/main.jsx`: application bootstrap and global stylesheet imports.

The application currently has active features for auth, platform administration, dashboard, company, users, roles, departments, clients, projects, daily reports, site photos, profile, and settings. Business features own API exports, identity-scoped query hooks, runtime schemas, components, and lazy route pages. Empty feature placeholders are not created.

## Routing and authorization

`src/routes/AppRoutes.jsx` is the route registry and preserves all established URLs. Business pages remain lazy-loaded. `ProtectedRoute`, `GuestRoute`, and `PlatformRoute` provide presentation gating; backend authorization remains authoritative. Navigation uses effective permission names returned by `/me`, never role-name-only checks.

## State and API boundaries

`src/api/axiosClient.js` is the only active Axios instance. It attaches the session token and normalizes failures. `src/api/endpoints.js` defines shared resource calls without changing existing payloads or response handling. Auth and company identity live in `features/auth/context/AuthContext.jsx`; identity changes clear protected query cache.

Project list/detail/team query keys include the authenticated company and user IDs. `/projects`, `/projects/new`, `/projects/:id`, and `/projects/:id/edit` are lazy company-workspace routes gated by their corresponding effective permission; Laravel policies additionally enforce assignment and tenant access. The portfolio supports card/list presentation over the same server-filtered page, including project-manager filtering. Archived project listing and restoration require `projects.restore`; no permanent-delete UI exists. Project details retain Overview integrations and expose the authorized Team view as a separate tab.

Client list/detail query keys are company/user scoped. `/clients` CRUD routes use exact `clients.*` presentation permissions. Project create/edit uses the separate server-authorized client lookup endpoint so authorized project editors can search the complete tenant client directory without receiving general `clients.view` access.

Daily report query keys add the project ID because Laravel exposes only project-nested report APIs. `/daily-reports` requires a selected authorized project; details and edit URLs retain that project as a query parameter so the nested resource can be addressed without inventing a global endpoint. Create, update, submit, review, visibility, and delete mutations invalidate identity-scoped report, project, and dashboard caches.

Site photo query keys likewise include company, user, and project identity. `/site-photos`, `/site-photos/upload`, and `/site-photos/:id` are lazy workspace routes. Private image bytes are requested through Laravel's authenticated file endpoint, converted to short-lived object URLs, and revoked on replacement/unmount. Project and report integrations query only their nested photo resources.

## Legacy boundary

Materials use lazy catalog and project-inventory routes. Their query keys include company/user identity plus project and inventory scope; Laravel ledger balances remain authoritative.


The old unrouted WowDash demo pages, layers, helpers, charts, duplicate services, and `MasterLayout` are preserved in `legacy-template/` outside `src`. Nothing in the maintained application imports that archive. It is excluded from active linting and bundling and can be inspected or selectively migrated later.

The maintained workspace shell uses a fixed desktop sidebar and top bar, a permission-filtered mobile bottom navigation, and an authorized Daily Report shortcut. Shell structure was adapted from the archived `MasterLayout` patterns, while its mock navigation, data, service layer, icon system, and theme implementation remain excluded. Shared shell sizing and colors are semantic theme tokens; page content remains feature-owned.

The company dashboard top section owns its heading, authorized project/period filters, and six-card KPI presentation under `src/features/dashboard`. Dashboard query keys include company, user, project, and period scope. Total and active project counts use server pagination metadata and are current snapshots; project scope applies to those counts. Period scope applies to the project-nested material-consumption summary. Unsupported labour attendance, client receivables, and contract-value metrics remain explicit unavailable states rather than client-derived estimates, and their cards follow the corresponding effective permissions.

## Verification boundary

The maintained Vitest command covers authentication, route authorization, platform company editing and administrator setup, company dashboard behavior, company profile permissions and refresh, users/roles/departments presentation authorization, profile/password flows, settings persistence, permission selection, and shared modal/form accessibility. Laravel feature tests remain the authoritative verification for tenant isolation and mutation authorization.

## Design Foundation — Phase 1: placement and Phase 1.1 review

SearchableSelect.jsx in src/components/ui owns the controlled combobox, keyboard handling and portal; AppUI.jsx re-exports it. modalScope.js supplies the shared modal context and stack used by AppModal and SearchableSelect. The top AppModal owns focus containment and Escape; its selects portal within the dialog subtree. Page menus use the lower page-dropdown layer and are dismissed when a modal opens. FilterBar owns its optional responsive grid layout. Projects list composes these shared primitives without a feature-local control system or new library. Existing native AppSelect remains available. Form integrations use Controller when adopting the controlled combobox.

index.html no longer loads legacy demo styles or Bootstrap; it retains Inter as an explicit font link. main.jsx imports tokens, theme definitions, then maintained component CSS. React Toastify retains its package stylesheet. Maintained CSS owns base box sizing, body typography, control inheritance and heading defaults. Archived components remain outside the runtime graph. Only Projects list gets page-specific composition changes in this phase. Visual rules belong in DESIGN_SYSTEM.md; preference logic is unchanged.

Phase 1.1 changes shared dropdown/modal integration and overflow containment only; Projects list remains the reference screen. No Dashboard or Project Detail layout restructuring is included. Source, automated and browser evidence are recorded separately in [DESIGN_FOUNDATION_VERIFICATION.md](../docs/DESIGN_FOUNDATION_VERIFICATION.md).
