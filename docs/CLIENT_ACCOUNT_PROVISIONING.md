# Linked client account creation

Source implementation completed and aligned with the confirmed combined workflow on 2026-11-13. Current read-only recheck: migration 2026_11_13_120000_add_user_id_to_clients_table.php is recorded as applied, with nullable unique clients.user_id and a restrictive foreign key to users.id. The agent did not execute any migration. Account-options now reports schema_ready/can_create/can_link=true for the checked authorized administrator. No live users, clients, grants or memberships were created/changed for verification. Earlier unapplied-migration observations below describe the earlier state, not the current deployment.

## Relationship and authorization

Nullable unique clients.user_id references users.id with restrictOnDelete, preserving legacy unlinked contacts and preventing cascading account deletion. Client.user and User.client expose the explicit link. The service derives company IDs, checks actor company and permission-team context and creates same-company Users/Clients only. No email matching, backfill or project assignment is performed.

New-client provisioning requires clients.create + users.create + roles.assign. Existing-contact provisioning requires clients.update + users.create + roles.assign, the existing Client update policy, and an unlinked same-company contact. Both resolve the existing role by company_id, name client and guard web and enforce the existing rule that the actor may not grant permissions they lack. The role row is locked within the write transaction and its actual permissions checked again. Existing grants are never synced/normalized or seeded.

In one transaction the service hashes a new password through the existing Laravel mechanism, creates an Active/unverified User, assigns the existing role, saves Client.user_id and writes safe existing-style user/client audit events. Existing contacts are locked/rechecked before creating User; already-linked contacts return 409. A new Client uses email for contact and login; linking an old Client permits a new account name/email without changing the contact. Duplicate emails, including emails on deleted Users, return generic email field validation and never relink/reset/update an existing account. Database unique conflicts are translated after transaction rollback.

Email-verification, company eligibility and login restrictions are unchanged. No email, invitation, token or membership is created. Verification-required companies still prevent unverified users logging in. Contact edits cannot mutate credentials/roles/status; contact deletion preserves the account and its memberships. Because the FK restricts deletion, a linked user cannot be permanently deleted while referenced even by a soft-deleted Client; existing user deletion returns its conflict. A separate explicit lifecycle policy remains outside this phase.

## Directory and UI

One Clients table contains typed business_client and account_only rows with server search and pagination; only users.view exposes account-only records or login metadata. Account-only entries retain tenant/web-role/pivot scope and never use Client routes. Explicit links prevent a linked User appearing twice; equal emails do not merge unlinked entries. Account-only rows point to existing Users management. Linked metadata includes soft-deleted account state for authorized viewers, while deleted Users are excluded from account-only entries.

The Add Client form has required name/email/password/confirmation, optional existing business fields, shared sections and 44px controls, visibility buttons and Create Client & Account. Unlinked contacts expose an explicit permission-gated Create login account dialog on directory/detail. Failed saves preserve non-sensitive values and clear credentials. Synchronous submission guards and disabled busy actions prevent duplicate submission. No credentials are stored in browser persistence. Account availability is checked server-side before credential fields/saving are enabled, including a clear schema prerequisite. The Add Client entry and combined form remain visible to actors with the required base permissions while unavailable; no contact-only fallback is offered. Existing-contact provisioning actions remain visible but disabled when deployment is unavailable. Backend remains authoritative on every write.

## Files for this workflow

Backend:
- database/migrations/2026_11_13_120000_add_user_id_to_clients_table.php
- app/Models/Client.php and app/Models/User.php
- app/Services/ClientService.php
- app/Http/Controllers/Api/ClientController.php and ClientDirectoryController.php
- app/Http/Requests/Api/V1/StoreClientRequest.php, StoreClientLoginAccountRequest.php and UpdateClientRequest.php
- app/Http/Resources/Api/V1/ClientResource.php
- routes/api.php

Frontend:
- src/features/clients/components/ClientForm.jsx, ClientAccountFields.jsx and ClientLoginAccountDialog.jsx
- src/features/clients/pages/ClientsPage.jsx, ClientDirectory.jsx, ClientFormPages.jsx and ClientDetailsPage.jsx
- src/features/clients/pages/clients.css
- src/features/clients/schemas/clientSchema.js
- src/features/clients/hooks/useClients.js and src/api/endpoints.js

Documentation: frontend/API_INTEGRATION.md, FRONTEND_ARCHITECTURE.md, DESIGN_SYSTEM.md; docs/CLIENT_ROLE_REVIEW.md, CLIENT_ACCOUNT_PROVISIONING.md and DESIGN_FOUNDATION_VERIFICATION.md. Prior unrelated edits were preserved. No test/spec/E2E/browser-automation files were created or changed.

## Verification evidence

Disposable runtime checks used an explicitly guarded :memory: SQLite connection, manually mirrored relevant schema and synthetic fixture records with a separate array permission cache. The application migration was NOT executed. All fixture state disappeared with the PHP process. Checked:

- New User/Client link, same-company client role, password hash, Active/unverified defaults and no memberships.
- Generic duplicate email field errors and no partial Client/User/role/audit writes.
- A forced Client-save failure after User/role/audit creation rolled the entire operation back.
- Explicit existing-contact linking with unchanged contact name/email; request/controller returned 201.
- Already-linked request returned 409 before duplicate validation, and a second service attempt created no additional User.
- Grant-ceiling denial, cross-company denial and missing web-role denial even where another-guard client role existed.
- Safe account response and credential-free audit payloads; password/confirmation validation and prohibited role fields.
- Independent contact-email edits and contact deletion leaving the account unchanged.
- Typed account-only rows with additional roles and Inactive status, no email-based merge, no linked-user duplication, and no account metadata without users.view.

The isolated check found an ambiguous company_id in the new joined directory query; it was qualified and the checks passed afterward. True simultaneous requests, MySQL lock scheduling and concurrent email races remain runtime-unverified; transaction locks, uniqueness constraints and exception handling are source-reviewed. Migration execution/rollback remains unverified because no migration was run.

Read-only live HTTP-kernel GET checks with an existing authorized identity: account-options returned 200 with schema_ready=false/can_create=false/can_link=false; unified directory returned 200 with two explicitly typed entries; the existing contact list and project-client lookup returned 200 with one contact each. No live POST or mutation was used.

PHP syntax checks and Pint --test passed for changed backend source/migration/routes. Targeted Clients/adapter ESLint passed. Production build passed with existing non-blocking Zod annotation warnings. Full lint still fails on nine pre-existing dashboard no-unused-vars errors (AppButton, setPage, meta in DashboardActiveProjects, DashboardMaterialStock and DashboardRecentSitePhotos); those files were preserved. git diff --check passed.

No connected Codex document session was available. Browser desktop/360px, themes, focus/visibility controls, modal interactions and end-to-end UI navigation remain pending. Existing dashboard verification limitations remain unchanged.

## Confirmed-workflow alignment follow-up

One primary Clients directory remains. The unused separate-account frontend component and hook were removed; the useful read-only account endpoint is retained for compatibility. ClientDirectory now has a matching module name. Add Client always describes atomic Client + User creation and includes login email/password/confirmation. Required base permissions allow entry to the form; a deployment or role-grant prerequisite disables fields/saving with a reason instead of hiding the confirmed workflow. Ordinary contact viewers retain clients.view-only access and are not shown unrelated provisioning warnings.

Account data remains users.view-gated in the API and UI. List/detail/directory query keys distinguish that capability to prevent cached account metadata surviving a frontend permission change. users.view is not added to new/existing-contact provisioning authority. Existing role grants, project policies, transaction logic, Users data source and no-membership behavior are preserved.

Fresh read-only checks: migration history, nullable column, unique index and restrictive FK all pass; account-options, directory, contact list, project-client lookup and Users endpoints return 200. Checked administrator availability is enabled. No existing clients.view-only identity without users.view was available for a fresh live interaction; that boundary is source-reviewed and covered by the previously recorded disposable-directory check, not claimed as a new live pass. No live creation was exercised. Targeted Clients lint and the production build pass; full lint retains the same nine unrelated dashboard errors. Backend was unchanged in this alignment, so previous PHP/Pint and isolated workflow evidence is retained without claiming a rerun. Browser and true simultaneous-request verification remain pending. No migration, account, contact, grant or membership was modified for this follow-up verification.
