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
| DashboardMetricCard surface/icon | 16px padding, 12px radius, restrained shadow; 54px coloured icon | Six semantic icon colours retained independently of accent. Named content-width container sets six columns above 90rem, three at <=90rem, two at <=54rem, and one at <=34rem; hidden cards reflow without column spans. Labels use the shared 13px field-label token, support uses 12px metadata, and cards stretch equally within each row without fixed heights. Rendered review pending. |
| Auth marketing / .auth-brand h1 | clamp(34px, 5vw, 64px), 32px at <=800px | Marketing hierarchy retained; rem/token migration pending auth review. |
| Auth form / .auth-panel h2 | 30px | Existing feature-specific rule; migration to shared hierarchy pending auth review. |
| Workforce summary / .report-worker-summary dd | --text-xl (1.5rem), weight 700 | Intentional numeric summary retained; no migration in this phase. |
| StatCard / .stat-card strong | --text-xl (1.5rem), bordered surface with --radius-lg | Existing Project Detail summaries retained; suitability for date/status values awaits visual review. |
| Workspace brand / .sidebar-brand-copy strong and .workspace-heading strong | 1.125rem brand, 1rem workspace label; navy/orange shell | Intentional navigation hierarchy retained; no migration in this phase. |

Projects list is the reference screen for this standard. Card/list switching, archives, server pagination, finance visibility and effective permissions remain business contracts. Page-specific layouts outside Projects list are not restructured. Legacy templates are reference material only; do not import their global styles or behaviours.

### Remote client selection

Project create/edit share one Client combobox through ClientSelector. It uses the same dimensions, bounded menu and modal/focus rules as other SearchableSelect consumers. Its placeholder is Search or select a client; No client is the explicit optional choice. The assigned label comes from authorized project data and survives lookup loading, errors and absent result rows. Typing or clearing search text never changes the selected client.

SearchableSelect accepts optional onSearchChange and filterOptions=false for server-owned matching; local filtering remains the default. menuMessage supplies remote empty-result or limit notices, and searchPlaceholder/maxLength customize the editable search input. Remote callers own debounce, query scoping and request cancellation. ClientSelector distinguishes loading, no clients, no matching clients and request errors. Its lookup is capped at 50 results with a refine-search notice at the cap; it does not claim exhaustive browsing or offer unsupported pagination.

### Dashboard project progress overview

Project Progress Overview uses the shared SectionCard and 18px section title. Its feature-scoped static HTML/CSS vertical chart uses semantic accent, border, surface and text tokens, a 0–100% axis, narrow rounded bars capped at 48px, and a 300px desktop / 280px mobile chart height. Labels wrap to two lines; hover/focus tooltips and an expandable text list retain full names and actual values. Crowded charts scroll within the card, with keyboard focus and no animation. Zero remains 0%; missing or invalid progress is unavailable. Rendered desktop, mobile and theme review remains pending.

### Dashboard financial summary

Financial Summary uses the shared SectionCard and a concise unavailable message, with no chart, total, legend or reserved chart height while an authoritative expense summary is absent. The dashboard chart row uses a named content-width container: at >=64rem it has a 3:2 progress/financial split; below that it stacks. Cards align at the top and retain natural heights; progress expands to full width when financial access is absent. Existing progress scrolling and accessibility remain unchanged. Rendered responsive and theme checks remain pending.

### Dashboard recent site photos

The dashboard uses a local linked-thumbnail composition with ProtectedPhoto, leaving SitePhotoCard and Project Detail unchanged. Up to three equal 4:5 portrait thumbnails use object-fit: cover, shared 8px corners and 12px gaps. The grid is capped at 36rem and adapts to two columns below 22rem widget content width and one below 14rem. An authorized project name appears below each image, followed by compact available period/photo-date metadata, wrapping as needed; the current API exposes no capture timestamp, so upload timestamps are omitted. Descriptions, uploader blocks, card-body padding and separate detail buttons are absent. Protected loading/error states and identity-based remounting remain. Rendered desktop/mobile and theme checks remain pending.

### Dashboard site widget row

Recent Site Photos and Site Reports share equal columns at >=56rem content width, with a 16px gap, and stack below it. Shared SectionCard surfaces, padding and 18px headings remain; header spacing is compact and supported View all links align right. Desktop cards stretch to the taller card through flex-column structure and a stretched grid row; pagination aligns at the bottom. Stacked cards retain independent natural heights. Reports use small semantic status-toned icon tiles, linked semibold titles, visible project names, muted authors and a single-line summary. Report date occupies a separate column beside the unchanged status badge; narrow card widths reflow metadata and badges and allow summary wrapping. Shared Previous/Next controls retain server pagination and counts. No sample capture times, report completion times or workflow statuses are introduced. Reference-image/source review completed; rendered responsive/theme/navigation comparison remains pending.

Both site widgets default to All projects / All time and show concise project/period scope text. Photo and report pagination remain separate and server-backed. Bounded period filters apply to photo_date and report_date; All time sends no date limits. Rendered equal-height, theme and 360px checks remain pending.

### Dashboard material stock status

Material Stock Status uses shared SectionCard, AppBadge/StockStatusBadge, semantic text/status colours and compact rows separated by theme borders. Every row shows material, project, exact quantity/unit and available minimum. Confirmed zero, missing minimum, unavailable quantity and negative-stock anomaly remain distinct. There is no percentage bar without a supported denominator. Scoped container rules wrap quantity and badges below metadata on narrow screens; pagination uses shared controls. Height follows content and the Photos/Reports row is unchanged. Rendered desktop/360px, theme and navigation review remains pending.

### Dashboard active projects table

Active Projects uses shared SectionCard and DataTable, replacing Recent projects. It retains shared 18px headings, table typography/borders and project status badges. Project names wrap above muted codes; client/manager, date-only expected completion, native accessible progress plus percentage, permitted budget and a read-only View link complete the row. Missing progress is unavailable; zero remains 0%. Scoped CSS contains table width in a keyboard-focusable horizontal scroll region and wraps server-pagination controls. No fixed card height, global table changes, new financial calculation or status classification is introduced. Rendered desktop/360px, sidebar and theme review remains pending.

### Dashboard cleanup and layout polish

The company-dashboard composition uses a single 20px vertical section gap, replacing accumulated direct-child margins. Card pairs use 16px horizontal gaps; shared SectionCard padding, 18px headings, surface/border/radius/shadow tokens are unchanged. Card header actions align right with compact 12px header spacing, and scope text uses consistent metadata typography. Footer spacing is 12px while Photos/Reports retain automatic bottom alignment and equal desktop heights; stacked cards remain naturally sized. Financial Summary keeps its compact unavailable state and progress retains its unchanged 0–100% scale. Quick actions retain their existing permissions/destinations with 12px wrapping gaps and shared control dimensions.

Active Projects server pagination is restored in All projects mode when results exist, including count/page metadata and Previous/Next disabled at response boundaries or while fetching. Selected-project mode omits pagination because it addresses one complete resource. Project changes still reset page through the existing keyed component. No commented-out pagination implementation or lint suppression is retained. Rendered 1440px/1024px/360px, themes, sidebar states, dropdowns and overflow checks remain pending.

### Standalone Daily Reports list

Daily Site Reports uses PageHeader, SectionCard, shared 44px controls and DataTable. The aligned filter toolbar contains a remote SearchableSelect for Project (All projects), submitted title search, existing statuses and All time/This month/Custom period. Custom dates use labelled shared controls and connected validation. A scoped content-width container moves from five aligned filter/action columns to two and one as space narrows. Titles wrap above two-line muted summaries; project/date/author/authoritative workers/status remain readable through contained keyboard-accessible table scrolling. Server pagination wraps using shared buttons and accurate counts. No report forms or detail presentation are redesigned. Rendered desktop/360px, themes, dropdown interaction and navigation checks remain pending.

### Create Daily Report - sectioned form

The create page uses normal workspace width capped at 75rem (1200px), shared PageHeader typography and one form containing five full-width SectionCards: Report Information, Work Updates, Issues and Delays, Workers present, and General Notes. Report Information places Project/date, full-width title, then progress/weather in two desktop columns. Work updates and issues/delays use paired fields with delay reason spanning the row. Shared textareas resize vertically; work/issue textareas have a token-based 128px minimum and notes keep the shared compact textarea sizing. Cards are separated by 20px, related fields by 16px, and workforce controls by 12px.

The Project and create-only workforce category controls use shared SearchableSelect with existing labels/errors/focus/theme behavior. Workforce instructions and the empty state remain explicit; no category is prepopulated. Add/remove buttons have explicit non-submit types. Cancel and Save Report use a wrapping right-aligned footer, with full-width actions in narrow content. Container rules stack field/category rows below 40rem without changing root sizing. Legacy vertical-form grouping was reviewed for presentation only; no legacy stylesheet or component was imported. The shared form's legacy Edit layout remains the default and receives no redesign. Browser desktop/360px and theme/keyboard checks remain pending.

## Clients and linked login accounts

Clients uses one shared DataTable with explicit Business client / Account only entry types, linked-account badges and authorized account status. Server search, sorting/counts/pagination and labelled keyboard-focusable contained scrolling remain. Only business-client rows expose contact routes/actions. Existing User management remains the destination for account-only entries and login-account changes.

Add Client uses a 75rem maximum workspace, shared PageHeader and SectionCards for Client information, Address and notes, and Login account. Shared 44px controls, two-column fields/mobile stacking, accessible password visibility buttons and wrapping Cancel / Create Client & Account actions are retained. Existing contact editing omits credentials and explains independent login email. An explicit shared modal provisions only a chosen unlinked contact. The combined form remains visible to actors with the required base permissions; backend availability disables its shared fieldset and saving with a readable migration/permission/role reason. Add Client is not a contact-only action. Ordinary contact viewers are not shown unrelated provisioning warnings. No false provisioning success is implied before deployment is ready. No theme preferences, root scaling or unrelated pages are changed. Browser desktop/360px/theme/keyboard review remains pending.

### Clients directory visual presentation

The directory uses the shared PageHeader with Manage clients and their login accounts, an aligned Add Client action and mobile stacking. Its SectionCard contains only Client directory / Refresh, then a visibly labelled AppInput and Search button: a 22.5rem desktop input column, 12px gap, shared 44px controls, and a single-column toolbar below 480px. AppInput replaces the old table-search wrapper whose 16px bottom margin displaced the adjacent button; no positional compensation is used.

The shared DataTable presents Client, Email, Phone, Account and Actions. A decorative 36px initials tile accompanies semibold names, optional companies and subtle Account only labels. Real account statuses and link relationships are separate; restricted metadata is labelled explicitly, and different authorized login emails appear as concise secondary text. Scoped header tint, 12px labels, 14px body text, balanced column widths, restrained hover, wrapping 44px actions and semantic danger Delete styling use existing tokens. Shared density still controls cell padding. The last body-row border is removed so the one server-pagination footer supplies a single divider; counts and Previous/Next wrap naturally. The 60rem table scrolls within its labelled container. No global table/theme behavior or business workflow changed. Rendered checks remain pending.
