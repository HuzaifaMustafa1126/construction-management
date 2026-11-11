# Design Foundation — Phase 1: verification report

## Phase 1 historical evidence (2026-11-11)

The following records describe the earlier implementation turn, not a rerun in Phase 1.1.

### Audit and cascade

The old index.html loaded Bootstrap, legacy style.css, extra.css and unused demo libraries. Legacy style.css defines --h1 up to 4.5rem and --h2 up to 3.75rem, forcing them onto headings with !important and defeating maintained 28px/18px rules. These runtime links are removed, rather than countered with overrides. Inter remains explicitly loaded with system fallbacks. Shared CSS previously mixed fixed 9px/14px radii, 24px padding, 650 button weight and inherited control typography. Projects search lacked a visible label; its flex filter bar did not align labelled fields with the Search button. No screenshots accompanied the supplied text attachment; rendered verification is still required.


### Legacy references and phase boundary

Reviewed WowDash DefaultInputs, DefaultCard and CustomDropdown. Adapted label-above-field grouping, restrained bordered cards and padded option rows as presentation patterns. No legacy implementation, icon package, Bootstrap behaviour or stylesheet was imported. Legacy dropdowns did not provide an editable accessible combobox. The new numeric scale comes from this phase's brief, not a claim that legacy typography was correct.

Projects list is the reference screen: labelled search, searchable status/manager filters, aligned controls, compact section actions, h3 names, muted codes, balanced metadata and existing permitted actions. Card/list switching, archives, server pagination, finance visibility and effective permission checks remain intact. No other page-specific layout is redesigned. Lint/build cannot establish rendered clipping, actual contrast, wrapping or touch/keyboard behaviour; those require browser review.


### Phase 1 verification record (2026-11-11)

The existing suite produced 45 passing and 4 failing tests. All four failures were reproduced using committed source loaded in memory: Projects edit permission fixture mismatch, Daily Reports permission fixture mismatch, Dashboard's ambiguous Tower Site text query, and Settings localStorage availability under the current Node environment. Permission rules and theme persistence were not changed to silence these failures.

An in-memory DOM check passed combobox opening, trimmed partial matching, numeric value integrity, selection, disabled-option skipping, retained labels, optional clearing, required clearing rejection, Escape/outside/Tab closing, loading and request-error states. No test or browser automation files were created. Browser tooling and supplied screenshots were unavailable, so desktop/tablet/360px rendering, real menu scrolling/positioning, light/dark and accent combinations, and representative Dashboard/Project Detail appearance remain unverified. Existing feature-specific styles outside the reference screen remain intentional exceptions; review Dashboard shared-style effects next, without starting that redesign in this phase.


## Phase 1.1 review (2026-11-11)

Outcome: PARTIAL pending browser verification.

### Source checks

- Frontend responds with HTTP 200 at http://localhost:5173/projects; listeners also exist on API port 8000. HTTP availability does not demonstrate an authenticated route rendering.
- No browser-control tool or connected document session is available. No browser debugging listener was found on port 9222. The agent cannot inspect computed styles, perform manual interactions, capture screenshots, or use an authenticated authorized option set. No accounts were reset and no business records were changed.
- Existing shared type sizes, filter grids, page/card padding, borders and surface rules were inspected. This does not establish actual appearance at 1440px, 1024px or 360px.
- Fixed a globally high body portal that could cover newer dialogs. Page menus now use a layer below modals; modal menus portal inside their owning dialog with shared stack/focus handling. Older menus dismiss when a newer AppModal mounts. Keyboard option navigation now changes only menu scrolling. Disabled selects dismiss rather than reopening stale search state when re-enabled.
- Source colour calculation found accent text on the dark surface at 3.43:1 (blue), 3.54:1 (green), and 3.43:1 (orange). Added a separate dark-mode accent text token for links/outline labels without changing filled action backgrounds. These are colour-value calculations, not browser contrast measurements.
- Added containment for long project codes, shared page-heading flex items and table scroll wrappers. Existing rules were changed directly rather than layered with duplicate overrides.
- Dashboard and Project Detail consumers were reviewed in source only. Their pages were not edited or restructured; concrete retained component values and migration status are now documented. Existing user changes in project pages/components/hooks and .vscode were preserved.

### Automated checks

- npm run lint: PASS.
- npm run build: PASS; dependency comment-annotation warnings from Zod were non-blocking.
- git diff --check: PASS.
- Existing shared-control suite (AppUI.test.jsx): 2 tests PASS. The full suite was not rerun in Phase 1.1; earlier Phase 1 counts above remain historical.
- In-memory synthetic DOM checks: PASS for trimmed matching, numeric ID integrity, selection/clearing, disabled-option skipping, Escape/outside/Tab closing, dismissal on disable, page-menu dismissal when a dialog opens, portal containment inside the owning modal, Tab/Shift+Tab wrapping, top-dialog-only Escape/focus, unique dialog title IDs, and focus restoration. These used synthetic component fixtures, not authorized application records. No test/automation files were created.
- CSS parsing and declared typography/layer token checks: PASS. This is not computed-style verification. Calculated new dark accent-text contrast against #111827: blue 6.98:1, green 10.18:1, orange 7.84:1; actual rendered themes remain unverified.

### Browser checks

None completed. All requested viewport widths (1440px, 1024px, 360px), card/list rendering, pagination presentation, computed typography, real dropdown positioning/scrolling, actual authorized options, modal interaction and screenshots remain pending. Light/dark with blue/green/orange accents also remain pending. No READY claim is made from lint/build or synthetic DOM checks.

### Scope

No backend/database, routes, permissions, API payloads, tenant scope or business actions were modified. No dependencies, skills, tests, specs, E2E or browser-automation files were installed, created or modified. Stop after this Projects review; no next-page redesign is authorized by this phase.

### Files changed by Phase 1.1

- frontend/src/components/ui/AppUI.jsx and SearchableSelect.jsx; new modalScope.js.
- frontend/src/components/ui/app.css and frontend/src/lib/theme/{tokens.css,themes.css}.
- frontend/{DESIGN_SYSTEM.md,FRONTEND_ARCHITECTURE.md,THEME_ARCHITECTURE.md}.
- This report. Other pre-existing working-tree changes were preserved.
