# Theme Architecture

The active theme implementation lives in `src/lib/theme` and has one source of truth:

- `themeStore.js` persists mode, accent, density, and sidebar-collapse preferences.
- `ThemeProvider.jsx` applies those preferences to the document root.
- `tokens.css` defines spacing, typography, radii, shadows, and focus tokens.
- `themes.css` defines semantic light/dark colors and accent variants.
- `src/components/ui/app.css` styles the maintained shared components and layouts using those tokens.

The application supports light/dark modes and blue/green/orange accents. Settings remain browser-local and have no authorization or backend-data effect. The archived WowDash theme files under `legacy-template/` are not part of the runtime theme system.

Phase 2 verification confirms that mode, accent, density, and sidebar settings persist through the Zustand store and that active status/overlay styles consume semantic variables rather than fixed light-theme colors.
