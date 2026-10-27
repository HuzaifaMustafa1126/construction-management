# Frontend Architecture

## Runtime

React 18 and Vite provide the application shell. TypeScript is strict for new foundation files while `allowJs` permits incremental migration of the existing JavaScript template. React Router owns public, guest, protected, and permission-gated routes. TanStack Query is the server-state boundary; Zustand stores only local presentation preferences. React Hook Form and Zod own forms and client validation.

## Structure

- `src/design-system/icons`: the only application icon registry.
- `src/design-system/theme`: tokens, themes, provider, and persistent UI settings.
- `src/design-system/components`: reusable typed primitives and TanStack `DataTable`.
- `src/services`: central transport and normalized API errors.
- `src/context`: authenticated user and company identity until auth is migrated to query hooks.
- `src/pages`: current route composition. New business work should move incrementally into `src/features/<module>`.
- `src/components/WorkspaceLayout.jsx`: permission-aware dashboard shell.

## State boundaries

Backend resources belong in TanStack Query and use keys scoped by authenticated user/company. Mutations invalidate relevant keys. Query cache must clear when identity changes or on logout. Theme, density, and sidebar preferences are local UI state and never affect authorization or business data.

## Migration boundary

The repository contains a large legacy WowDash JavaScript component library using several icon/UI dependencies. It is outside the new routed foundation. New and migrated application code must use this documented system; legacy modules should be removed or migrated module-by-module rather than mechanically rewritten without product coverage.
