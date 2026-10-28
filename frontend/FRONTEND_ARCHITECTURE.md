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

The application currently has active features for auth, platform administration, dashboard, company, users, roles, departments, projects, daily reports, profile, and settings. Projects and daily reports own feature API exports, identity-scoped query hooks, runtime schemas, components, and lazy route pages. Empty feature placeholders are not created.

## Routing and authorization

`src/routes/AppRoutes.jsx` is the route registry and preserves all established URLs. Business pages remain lazy-loaded. `ProtectedRoute`, `GuestRoute`, and `PlatformRoute` provide presentation gating; backend authorization remains authoritative. Navigation uses effective permission names returned by `/me`, never role-name-only checks.

## State and API boundaries

`src/api/axiosClient.js` is the only active Axios instance. It attaches the session token and normalizes failures. `src/api/endpoints.js` defines shared resource calls without changing existing payloads or response handling. Auth and company identity live in `features/auth/context/AuthContext.jsx`; identity changes clear protected query cache.

Project list/detail/team query keys include the authenticated company and user IDs. `/projects`, `/projects/new`, `/projects/:id`, and `/projects/:id/edit` are lazy company-workspace routes gated by their corresponding effective permission; Laravel policies additionally enforce assignment and tenant access.

Daily report query keys add the project ID because Laravel exposes only project-nested report APIs. `/daily-reports` requires a selected authorized project; details and edit URLs retain that project as a query parameter so the nested resource can be addressed without inventing a global endpoint. Create, update, submit, review, visibility, and delete mutations invalidate identity-scoped report, project, and dashboard caches.

## Legacy boundary

The old unrouted WowDash demo pages, layers, helpers, charts, duplicate services, and `MasterLayout` are preserved in `legacy-template/` outside `src`. Nothing in the maintained application imports that archive. It is excluded from active linting and bundling and can be inspected or selectively migrated later.

## Verification boundary

The maintained Vitest command covers authentication, route authorization, platform company editing and administrator setup, company dashboard behavior, company profile permissions and refresh, users/roles/departments presentation authorization, profile/password flows, settings persistence, permission selection, and shared modal/form accessibility. Laravel feature tests remain the authoritative verification for tenant isolation and mutation authorization.
