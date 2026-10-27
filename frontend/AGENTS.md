# Frontend agent rules

Before changing UI, read `DESIGN_SYSTEM.md`, `FRONTEND_ARCHITECTURE.md`, and `API_INTEGRATION.md`.

- Use only `@heroicons/react/24/outline`, through `AppIcon` and `iconRegistry.ts`. Never import icons in feature pages.
- Use semantic CSS variables. Never hardcode theme colors in React page components.
- Reuse global buttons, form controls, `DataTable`, modal, drawer, feedback, and layout components.
- Do not create page-specific spacing, button, form, modal, or table systems.
- Preserve light/dark mode, all supported accents, keyboard access, focus visibility, and responsive behavior.
- Keep presentation permission checks aligned with exact backend permissions; backend authorization is authoritative.
- Verify endpoints against the API inventory and Laravel request/resource code. Never invent endpoints or payloads.
- Follow feature-based naming and keep server state in TanStack Query. Clear company-scoped cache on identity changes.
- Update the documentation whenever shared UI, theme, API, or architecture contracts change.
- Do not weaken tenant isolation, store credentials, or place private server credentials in Vite variables.
