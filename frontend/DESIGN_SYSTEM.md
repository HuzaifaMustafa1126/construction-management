# Global Design System

## Ownership

The maintained UI system is split by responsibility without duplication:

- `src/components/ui/`: buttons, inputs, selects, dialogs, badges, feedback, dropdowns, skeletons, and `AppIcon`.
- `src/components/tables/`: the global TanStack `DataTable`.
- `src/components/layout/`: shared navigation declarations and future layout-only elements.
- `src/lib/theme/`: `ThemeProvider`, Zustand presentation settings, semantic tokens, and theme definitions.
- `src/components/ui/icons/iconRegistry.js`: the only Heroicons Outline registry.

Feature pages compose these primitives. They must not import Heroicons directly, create competing form/table/modal systems, or hardcode theme colors.

## Theme

Supported modes are light and dark. Supported accents are blue, green, and orange. Density is comfortable or compact, and desktop sidebar collapse is a persisted presentation preference. `ThemeProvider` applies `data-mode`, `data-accent`, and `data-density` to the root document element.

`tokens.css` owns typography, spacing, radii, shadows, breakpoints, and focus treatment. `themes.css` owns semantic colors. Application CSS consumes semantic custom properties rather than palette values.

## Components and icons

`AppUI.jsx` exports the shared application vocabulary, while shared controls live in `Controls.jsx`. `DataTable.jsx` remains the only global table primitive. `AppIcon` accepts registry names and standardizes Heroicons Outline size and stroke. All maintained design-system code follows the JavaScript/JSX-only project rule.

Authenticated company and platform headers share identity dropdown behavior. Forms retain programmatic labels, field errors use alert semantics, focus remains visible, and responsive layouts preserve the mobile navigation drawer.

Shared modals receive focus when opened, close with Escape, restore prior focus when removed, and expose dialog semantics. Shared inputs, selects, and textareas connect field errors with `aria-describedby`. Status badges and overlays use semantic theme tokens in every supported mode/accent.
