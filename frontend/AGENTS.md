# Frontend agent rules

Before changing UI, read `DESIGN_SYSTEM.md`, `FRONTEND_ARCHITECTURE.md`, and `API_INTEGRATION.md`.

- Keep route pages inside `src/features/<feature>/pages`, feature-only components beside their feature, shared layouts in `src/layouts`, and route composition in `src/routes`.
- The maintained frontend is JavaScript/JSX only. Use `.js` for non-JSX modules and `.jsx` for React components; do not add `.ts`, `.tsx`, TypeScript syntax, declarations, compiler configuration, or TypeScript-only dependencies.
- Shared HTTP infrastructure lives in `src/api`; do not recreate the archived legacy Axios client or service layer.
- Shared UI belongs in `src/components/ui`, tables in `src/components/tables`, navigation elements in `src/components/layout`, and theme state/tokens in `src/lib/theme`.
- `legacy-template/` is an unrouted, recoverable archive. Do not import it into `src`; migrate an artifact deliberately if a future feature genuinely needs it.

- Use only `@heroicons/react/24/outline`, through `AppIcon` and `iconRegistry.js`. Never import icons in feature pages.
- Use semantic CSS variables. Never hardcode theme colors in React page components.
- Reuse global buttons, form controls, `DataTable`, modal, drawer, feedback, and layout components.
- Do not create page-specific spacing, button, form, modal, or table systems.
- Preserve light/dark mode, all supported accents, keyboard access, focus visibility, and responsive behavior.
- Keep presentation permission checks aligned with exact backend permissions; backend authorization is authoritative.
- Verify endpoints against the API inventory and Laravel request/resource code. Never invent endpoints or payloads.
- Follow feature-based naming and keep server state in TanStack Query. Clear company-scoped cache on identity changes.
- Update the documentation whenever shared UI, theme, API, or architecture contracts change.
- Do not weaken tenant isolation, store credentials, or place private server credentials in Vite variables.
- Platform routes must use the shared design system and `PlatformRoute`, while every platform API remains protected by backend `platform.admin` middleware. Never infer platform access from a company role string.
- Company navigation and dashboard visibility must use effective permissions from `/me`, never role-name-only authorization. Scope company query keys by company and user, and preserve cache clearing on identity changes.
- Company management mutations invalidate company-scoped query keys. Role, department, and status changes use their dedicated endpoints; company forms never send platform fields or company IDs.
- Personal profile, password security, and presentation settings are shared by company and platform identities. Platform self-service routes must not require company context, and password changes must preserve only the current API token.
- Keep authenticated feature pages route-lazy. The workspace sidebar collapse preference is presentation-only and must remain responsive and permission-aware.
- Phase 2 regression coverage includes route guards, identity/cache clearing, platform flows, company management presentation permissions, profile/security, theme persistence, and shared control accessibility. Keep these tests in the maintained `npm test` command.
- Project pages live under `src/features/projects`, use the centralized Axios client, and scope TanStack Query keys by company and user identity. Project forms never send `company_id`, status, progress, currency, ownership, or team fields through generic create/update payloads.
- Project list sorting, filtering, and pagination are server-owned. Team changes use the backend's full replacement contract and only offer active company users eligible for the selected assignment type.
- Daily report pages live under `src/features/daily-reports`. Every report request is project-nested; never aggregate company history by fetching every project. Report query keys include company, user, and project IDs. Workflow transitions use the dedicated submit, review, and visibility endpoints.
- Site photo pages live under `src/features/site-photos`. Photo APIs and private file delivery are project-nested. Fetch image bytes with the authenticated Axios client and temporary object URLs; never expose storage paths or convert the private disk to public access. Uploads use backend-supported multipart fields and limits.
- The bearer token intentionally remains in `sessionStorage`; never log it or move it to persistent storage. Any future HttpOnly-cookie migration requires an explicit backend/frontend security project.
- Materials and inventory live under `src/features/materials`. Render Laravel balances and decimal quantities exactly; never derive authoritative balances from transaction pages or simulate transfers.
