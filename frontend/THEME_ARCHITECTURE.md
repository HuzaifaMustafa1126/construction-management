# Theme Architecture

The active theme implementation lives in `src/lib/theme` and has one source of truth:

- `themeStore.js` persists mode, accent, density, and sidebar-collapse preferences.
- `ThemeProvider.jsx` applies those preferences to the document root.
- `tokens.css` defines spacing, typography, radii, shadows, and focus tokens.
- `themes.css` defines semantic light/dark colors and accent variants.
- `src/components/ui/app.css` styles the maintained shared components and layouts using those tokens.

The application supports light/dark modes and blue/green/orange accents. Settings remain browser-local and have no authorization or backend-data effect. The archived WowDash theme files under `legacy-template/` are not part of the runtime theme system.

The workspace shell adds semantic sidebar, shell-accent, top-bar, and content-width tokens. The fixed navy navigation and restrained orange active/action treatment are consistent across accent choices, while page controls continue to honor the user's selected accent and light/dark mode.

The store reads and writes browser-local presentation preferences; the provider applies mode, accent and density attributes. This describes the source contract, not a claim of browser verification or a passing persistence test run. Historical frontend architecture verification is separate from Design Foundation work; current evidence is recorded in [DESIGN_FOUNDATION_VERIFICATION.md](../docs/DESIGN_FOUNDATION_VERIFICATION.md).

## Design Foundation — Phase 1: token consumption

Semantic typography, line-height, control-height, card-padding, surface-radius/shadow, select-height and overlay-layer tokens live in tokens.css. At <=640px, root token overrides reduce title size and page/card padding while raising control text to 1rem. Breakpoint custom properties are reference aliases, not native media-query substitutions; responsive rules use explicit widths.

themes.css additionally owns placeholder, disabled-control, hover, danger-action, accent-text and option-state colours. Option tints derive from accent and current surface in light/dark modes. Visual values and intentional exceptions belong in DESIGN_SYSTEM.md.

Runtime stylesheet order is tokens, theme definitions, then maintained CSS. Global legacy styles are no longer loaded by index.html. The store, localStorage keys, provider attributes, table density and sidebar preference flow are unchanged. Phase 1.1 assigns --z-select (60) below --z-modal (9999). A page combobox portals to the body; a modal combobox portals into its owning dialog. The isolated modal backdrop bounds its descendants below newer sibling dialogs. Shared modal registration dismisses older menus and scopes focus/Escape to the top dialog. Portals consume root theme variables and typography without changing preference state. These are implemented source contracts; rendered theme and modal behavior still require browser verification.
