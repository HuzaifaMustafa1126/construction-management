# Frontend Step 2.4 — Profile, Settings, and Global UI

## Delivered

- Editable personal profile with server validation, safe field allow-listing, refreshed identity state, loading/submission feedback, and cancel behavior.
- Password security page with current/new/confirmation fields, visibility control, client and backend validation, cleared secrets after success, and clear session-revocation behavior.
- Shared appearance settings for light/dark mode, blue/green/orange accents, table density, and desktop sidebar collapse. Preferences remain browser-local and never affect authorization.
- Company and platform identity dropdowns with profile, settings, and logout actions using the global Heroicons Outline registry.
- Platform-specific profile, password, and settings routes that work without a company assignment.
- Route-level lazy loading for authenticated business modules.

## Backend and data impact

`PUT /api/v1/me` and `PUT /api/v1/me/password` now run after active-account validation but outside company-context middleware. Password changes are limited to five requests per minute and retain only the current token. No migrations or data transformations are required, and no existing company, user, password, assignment, project, or business record is modified by deployment.

## Verification scope

Frontend coverage exercises profile submission, password confirmation and successful clearing, persisted appearance/navigation preferences, and the platform security route. Backend coverage verifies company-less Platform Super Admin profile and password changes plus token revocation. Existing platform invitation, tenant isolation, permissions, and suspended-company tests remain in the full Laravel suite.
