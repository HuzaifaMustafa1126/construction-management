# Design Foundation — Phase 1: Shared Design System

Component placement and reuse contracts are maintained in [FRONTEND_ARCHITECTURE.md](FRONTEND_ARCHITECTURE.md). Theme state and preference flow are maintained in [THEME_ARCHITECTURE.md](THEME_ARCHITECTURE.md). Phase 1.1 review evidence and earlier audit history live in [the verification report](../docs/DESIGN_FOUNDATION_VERIFICATION.md).

## Theme

Supported modes are light and dark. Supported accents are blue, green, and orange. Density is comfortable or compact, and desktop sidebar collapse is a persisted presentation preference. `ThemeProvider` applies `data-mode`, `data-accent`, and `data-density` to the root document element.

`tokens.css` owns typography, spacing, radii, shadows, breakpoints, and focus treatment. `themes.css` owns semantic colors. Application CSS consumes semantic custom properties rather than palette values.

## Components and icons

`AppUI.jsx` exports the shared application vocabulary, while shared controls live in `Controls.jsx`. `DataTable.jsx` remains the only global table primitive. `AppIcon` accepts registry names and standardizes Heroicons Outline size and stroke. All maintained design-system code follows the JavaScript/JSX-only project rule.

Authenticated company and platform headers share identity dropdown behavior. Forms retain programmatic labels, field errors use alert semantics, focus remains visible, and responsive layouts preserve the mobile navigation drawer.

Shared modals receive focus when opened, close with Escape, restore prior focus when removed, and expose dialog semantics. Shared inputs, selects, and textareas connect field errors with `aria-describedby`. Status badges and overlays use semantic theme tokens in every supported mode/accent.

Projects reuse `PageHeader`, `SectionCard`, shared form controls, `ConfirmDialog`, `AppBadge`, `AppAlert`, and the global `DataTable`. The table accepts optional `sortable` and `paginated` flags so server-owned project sorting and pagination are not misrepresented as whole-dataset client operations. Project-specific responsive grids consume semantic tokens from the existing theme only.

The project portfolio card view is a responsive alternative presentation of the same server-owned page and filters as the list view. Cards use semantic surface, border, text, spacing, and radius tokens; action visibility uses exact effective permissions. Project detail navigation uses the shared tab control for Overview and Team without placeholder phase/task tabs.

Daily reports reuse the same primitives and global table. The only shared icon addition is the Heroicons Outline `ClipboardDocumentListIcon` registered as `reports`. Workforce rows, report metadata, review controls, and project report cards use semantic tokens and collapse to single-column controls on small screens; no alternate component or theme system was introduced.

Site photos add only the Heroicons Outline `CameraIcon` under the shared `photos` registry name. Feature-local gallery cards, protected-image placeholders, upload previews/progress, and the viewer use existing controls and semantic tokens. Grids preserve image aspect ratio and collapse to one column on small screens.

Materials add the Heroicons Outline `CubeIcon` under the shared `materials` registry name and reuse global tables, forms, badges, dialogs, and semantic status tones.

## Shared visual standard

These rules are the new shared standard, established from this phase's brief and source inspection. They were not previously complete project requirements. Earlier docs described ownership, semantic colours, preferences and accessibility intentions. Dimensions/type hierarchy existed mostly in code; searchable-select behaviour was missing.

### Typography

Use Inter, then the existing system sans stack. Sizes are rem-based at normal browser root size; no root scaling, zoom or transforms.

| Role | Desktop | At <=640px | Weight | Line height |
|---|---|---|---|---|
| Page h1 | 1.75rem (28px) | 1.5rem (24px) | 700 | 1.3 |
| Section h2 | 1.125rem (18px) | same | 600 | 1.3 |
| Card h3 / lower headings | 1rem (16px) | same | 600 | 1.3 |
| Body / button | .875rem (14px) | same | 400 / 600 | 1.5 |
| Field label | .8125rem (13px) | same | 500 | 1.5 |
| Input / select / textarea | .875rem (14px) | 1rem (16px) | 400 | 1.5 |
| Helper / metadata | .75rem (12px) | same | 400 | 1.5 |

PageHeader, SectionCard and project cards preserve h1/h2/h3 semantics. Section header sizing is scoped to its direct header so nested headings retain their role. Specific retained component styles and migration status are listed below.

### Spacing and alignment

Use 4/8/12/16/20/24/32px spacing tokens. Page padding is 24px desktop, 16px mobile; section separation is 20-24px. Section and project cards use 20px padding, 16px mobile. Label-to-control gap is 8px; related controls use 12px gaps. FilterBar with layout="grid" provides search/selector/selector/action columns, two columns at <=1100px and one at <=640px. Fields and submit actions align at the bottom without stretching buttons over labels. Header actions wrap. Project names and metadata wrap without fixed card heights. Table overflow stays inside its scroll container.

### Controls and states

Text/search/number/date inputs, native selects, searchable select inputs and buttons share a 2.75rem (44px) minimum height. Fields use 12px horizontal/8px vertical padding, 8px radius, 1px semantic border, theme input background, normal text weight and no resting shadow. Buttons use 16px horizontal padding and 600 weight. Primary, secondary, outline, ghost and danger remain shared variants. Textareas resize vertically, respect rows, and use a 7rem minimum height.

Placeholder/helper colours are semantic. Hover strengthens the border; focus has a visible 2px accent outline plus soft ring. Invalid fields use danger borders and linked alert messages; descriptions and errors can coexist. Disabled controls use readable semantic text on muted surfaces without opacity loss. Readonly fields use muted backgrounds. Busy buttons stay disabled and expose aria-busy. Loading statuses, empty messages and request-error alerts remain explicit. Selection/error states must not rely on colour alone.

### Colours and surfaces

Existing light/dark background, surface, text, border and success/warning/danger/info pairs remain in themes.css. Blue/green/orange accents control page actions and focus. Navy navigation and orange shell actions are intentional exceptions across accents. New placeholder, disabled, hover and selected/active option colours are semantic variables. Option tints blend accent with the current surface in either mode. Danger action colours are separate from danger text so filled buttons retain a readable foreground. --color-accent-text supplies accent-coloured links and outline labels: it follows the selected primary in light mode and uses readable blue/green/orange text shades in dark mode. Filled button backgrounds and their foregrounds keep their existing separate tokens.

| Surface | Border | Radius | Shadow |
|---|---|---|---|
| Main SectionCard | 1px semantic | 12px | --shadow-section (restrained --shadow-sm) |
| Nested project card | 1px semantic | 8px | none |
| Dropdown popover | 1px semantic | 8px | --shadow-popover (--shadow-md) |
| Input control | 1px semantic | 8px | none at rest |

Use role tokens instead of per-page shadows. Density changes table padding only. Retained surfaces are limited to the named component styles below; this is not a blanket exemption for feature pages.

### SearchableSelect

The shared controlled combobox is exported through AppUI.jsx. Supply options as { value, label, disabled? }, controlled value, and onChange(value). It supports label, description, error, loading, requestError, disabled/required states, optional hidden name, forwarded input ref, and onBlur. React Hook Form integrations use Controller rather than register because selection callbacks return values, not DOM change events.

Focus/click opens; typing filters case-insensitive partial labels after trimming whitespace. Search text is separate from selection: typing, opening, blur and Escape never commit arbitrary text. Select an explicit empty-valued option to clear optional fields (Projects uses All statuses / All managers); required fields reject that option. Numeric/string values preserve their type. Previously seen selected labels remain available if options change; provide selectedOption for an initial selection absent from loaded options.

Arrow keys navigate enabled options, Enter selects, Escape closes and Tab moves normally. Selection closes and restores the selected label. Outside pointer clicks/blur close the menu. Combobox/listbox semantics, expanded/selected states, active descendant, checkmarks and keyboard scrolling are provided. The menu matches trigger width, scrolls independently to at most 16rem (256px at normal root size), flips above if space requires, and repositions on scrolling or viewport changes. Keyboard navigation scrolls only the menu, not ancestor pages. Page menus portal to document.body at --z-select (60), below --z-modal (9999). Inside AppModal, menus portal into the owning dialog and stay within its isolated backdrop stacking context and focus boundary. Opening a newer AppModal dismisses older menus; only its dialog handles focus trapping and Escape. Options use aria-activedescendant: DOM focus stays in the input and option rows are not Tab stops. Escape closes a menu first; a subsequent Escape can close its dialog. Tab closes the menu and uses normal navigation within the active modal boundary. Menus do not lock page scrolling. These contracts apply to shared AppModal; third-party focus traps/native dialog elements need separate integration and are not claimed as supported. Portal typography consumes shared theme tokens.

Projects searches its static statuses and complete authorized meta.project_managers set locally. Laravel visibleManagers scopes this set to active tenant project managers on accessible projects and returns it without pagination. Project name/code search, filters and pagination remain server-owned. Future paginated remote lookups must use supported debounced server search, identity-scoped query keys and stale-response protection; filtering a loaded page is not directory-wide search. No dependency or remote lookup was added here.

### Specific component styles and migration status

| Component / selector | Intended retained style | Status |
|---|---|---|
| DashboardHeader / .dashboard-heading h1 | Shared page-title tokens: 28px desktop, 24px mobile, line-height 1.3 | Migrated to shared typography. Greeting uses muted 12px metadata; authorized project selection uses SearchableSelect over the existing complete loaded set. |
| DashboardMetricCard / .dashboard-metric-copy strong | --text-xl (24px), line-height 1.15; unavailable values use the same size | Compact metric hierarchy; values and supporting text wrap without fixed heights. |
| DashboardMetricCard surface/icon | 16px padding, 12px radius, restrained shadow; 54px coloured icon | Six semantic icon colours retained independently of accent. Available content width sets six/three/two/one columns; equal-height cards within each row. Rendered review pending. |
| Auth marketing / .auth-brand h1 | clamp(34px, 5vw, 64px), 32px at <=800px | Marketing hierarchy retained; rem/token migration pending auth review. |
| Auth form / .auth-panel h2 | 30px | Existing feature-specific rule; migration to shared hierarchy pending auth review. |
| Workforce summary / .report-worker-summary dd | --text-xl (1.5rem), weight 700 | Intentional numeric summary retained; no migration in this phase. |
| StatCard / .stat-card strong | --text-xl (1.5rem), bordered surface with --radius-lg | Existing Project Detail summaries retained; suitability for date/status values awaits visual review. |
| Workspace brand / .sidebar-brand-copy strong and .workspace-heading strong | 1.125rem brand, 1rem workspace label; navy/orange shell | Intentional navigation hierarchy retained; no migration in this phase. |

Projects list is the reference screen for this standard. Card/list switching, archives, server pagination, finance visibility and effective permissions remain business contracts. Page-specific layouts outside Projects list are not restructured. Legacy templates are reference material only; do not import their global styles or behaviours.

### Remote client selection

Project create/edit share one Client combobox through ClientSelector. It uses the same dimensions, bounded menu and modal/focus rules as other SearchableSelect consumers. Its placeholder is Search or select a client; No client is the explicit optional choice. The assigned label comes from authorized project data and survives lookup loading, errors and absent result rows. Typing or clearing search text never changes the selected client.

SearchableSelect accepts optional onSearchChange and filterOptions=false for server-owned matching; local filtering remains the default. menuMessage supplies remote empty-result or limit notices, and searchPlaceholder/maxLength customize the editable search input. Remote callers own debounce, query scoping and request cancellation. ClientSelector distinguishes loading, no clients, no matching clients and request errors. Its lookup is capped at 50 results with a refine-search notice at the cap; it does not claim exhaustive browsing or offer unsupported pagination.
