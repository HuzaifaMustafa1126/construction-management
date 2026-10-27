# Construction Project Management Frontend

React/Vite frontend for the multi-tenant construction management SaaS.

## Commands

```bash
npm install
npm run dev
npm test
npm run lint
npm run build
```

## Architecture

Application source is feature-based under `src/features`. The maintained runtime is JavaScript/JSX only: non-React modules use `.js`, and React components use `.jsx`. Shared API transport, UI primitives, layouts, routes, and theme infrastructure live in their corresponding top-level `src` directories. See `FRONTEND_ARCHITECTURE.md`, `DESIGN_SYSTEM.md`, `API_INTEGRATION.md`, and `../docs/FRONTEND_STRUCTURE_REFACTOR.md`.

The unrouted original WowDash examples are retained under `legacy-template/` for reference and are not part of the application build or active lint scope.
