# Client role review

Current workflow update: provisioning source has resumed under the explicitly approved linked-client workflow. Existing grants remain unchanged. A fresh read-only recheck now confirms the relationship migration is recorded as applied, with nullable/unique user_id and restrictive foreign key. The agent did not execute it. Provisioning is in product scope; the earlier missing-migration result was a deployment state, not a product deferral. The earlier review and deferral below are historical evidence. See CLIENT_ACCOUNT_PROVISIONING.md for current behavior.

Reviewed 2026-11-13. Active database inspection used only schema reads and SELECT queries bootstrapped through Laravel. No records, grants, role assignments, memberships or verification flags were changed. Anonymous tenant labels below follow role ordering; they are not tenant identifiers.

## Findings and evidence

| Item | Finding | Evidence |
| --- | --- | --- |
| Role / guard | Four existing roles named client, all guard web. | Active database |
| Tenant scope | All four roles have company_id; Spatie teams enabled with company_id as team key. No assigned user's company differed from its client role's company in the checked rows. | Active database/configuration |
| Existing assignments | Two assigned users total, both active, neither email-verified. Neither has another role or direct permission. One has one project membership; the other has none. | Active database, aggregate results only |
| Client/User relationship | Neither clients nor users has a linking column. Client and User models expose no mutual relationship. No active business client rows exist in the four inspected role tenants. Matching email does not establish a link. | Active schema/data and source |
| Project access | Same-company explicit project_user membership for non-admins. assignment_type=client requires an active same-company client-role user; replacement is controlled by projects.assign plus company-admin team policy. projects.client_id is a business relationship and does not grant user access. | Source: Project, ProjectPolicy, ProjectTeamController |
| Financial access | None of these client roles grants projects.budget.view, expenses.*, payments.* or inventory access. Budget fields require viewBudget; inventory policies explicitly exclude clients. | Active grants and source |
| Login | Valid password, active non-deleted user, existing operational company; verified email when company.email_verification_required is true. All four inspected companies are active with verification requirement disabled. No passwords or actual login attempts were used. | Source and active company flags |
| User provisioning authority | Existing UserController requires users.create AND roles.assign; assignable role must be tenant-scoped and every granted permission must be held by the actor. Current contact-only creation requires clients.create. Future combined creation would require all three permissions plus this grant ceiling. | Source only |
| Missing prerequisites | Role-grant review decision; explicit nullable unique clients.user_id relationship and its migration; atomic provisioning and authorized linked-account metadata. | Source/schema review |

Proposed relationship: nullable unique clients.user_id referencing users.id, with no cascading user deletion. Legacy clients stay unlinked; no email matching, backfill or automatic project membership. This is a proposal only: no migration or provisioning code was added or applied in this phase, following the user's deferral.

## Actual grants

Common to all four roles: dashboard.view, projects.view, daily_reports.view, documents.view, site_photos.view, photos.view. These match the current PermissionCatalog client template and are the source-defined read-only intent. documents.view and site_photos.view have no active document/legacy-photo API handler in routes/api.php; current private-photo APIs use photos.view. Their grants alone do not create a destination or bypass a policy.

Two roles additionally store site_reports.view, a backwards-compatible alias of daily_reports.view. It is redundant read access, not an additional write grant.

One role additionally stores the following write/approval permissions. No source evidence establishes these as an intentional client exception. They conflict with the current client template and policy design; do not silently remove or propagate them.

| Stored grant | Actual effect in maintained source | Assessment |
| --- | --- | --- |
| daily_reports.create | Controller also requires DailySiteReportPolicy.create, which excludes client-role users. Create UI excludes clients. | Conflicts; grant alone cannot create reports. |
| site_reports.update | Update requires policy.update, which excludes clients. Submit requires creator/admin and an accessible report; client report lookup only permits approved, visible reports, while submission requires draft/changes_required. | Conflicts; current workflow cannot be reached by a client-only identity. |
| daily_reports.approve and site_reports.approve | Alias normalizes to daily_reports.approve. Review policy excludes clients; visibility requires company_admin. Review also uses report lookup that hides unapproved reports from clients. | Conflicts; grant alone cannot approve or publish reports. |
| photos.create | Store/batch require SitePhotoPolicy.create, which excludes clients. Upload UI excludes clients. | Conflicts; grant alone cannot upload. |
| photos.update | Update policy excludes clients. | Conflicts; grant alone cannot edit photo metadata. |
| photos.approve | Review and visibility require company_admin. | Conflicts; grant alone cannot approve or publish photos. |
| photos.delete | Delete policy requires company_admin. | Conflicts; grant alone cannot delete photos. |
| site_photos.upload and site_photos.delete | No maintained controller checks these legacy names; they are not aliases of photos.create/photos.delete in PermissionCatalog. | Outside client baseline; no active legacy endpoint effect found. |

No write/approval grant is intentional under the current source-defined client role. Historical operator intent cannot be inferred from stored grants, and their origin was not established. Initializer/seeder intentionally preserve permissions of existing roles; they only sync newly created roles, so current source defaults do not repair historic differences. No seeders were run.

These are controller/policy source conclusions, not mutation runtime passes. Additional roles could affect policies, particularly company_admin checks; neither existing assigned client user has an additional role in the inspected database.

## Visibility and preserved boundaries

Clients see only explicitly assigned projects, approved AND client-visible reports/photos, and authenticated private photo files permitted by the same policy. Creating a business Client or assigning client role alone does not grant project membership. A client account with no project membership has no project access.

Contact editing remains contact-only and does not update user email, password, role or status. Existing deletion refuses clients with non-deleted linked projects and soft-deletes only Client. A future linked account would require an explicit lifecycle decision: deleting the business contact must not delete or silently disable its User. Email verification, if required, remains a login prerequisite; this phase provides no invitations, verification delivery or bypass.

## Independent UI scope

Clients directory and contact form reuse PageHeader, SectionCard, DataTable and shared controls. Create/Edit have compact business/contact and address/notes sections, shared responsive two-column fields, wrapping actions and a 75rem maximum workspace. No Login Account fields or combined-account promise are exposed while provisioning is deferred. Create remains the existing contact-only operation.

Directory preserves submitted server search, 15-row pagination and existing actions. Permission denial disables list requests; superseded list requests receive AbortSignal; errors no longer also show empty results. Out-of-range pages reset to the server's last page. Scrolling is contained, and pagination disables while fetching. Linked/No account badges are deferred because the current resource has no authoritative relationship metadata; no account association is inferred.

Browser verification is pending: tool discovery found no connected Codex document sessions. No framework was installed and no browser pass is claimed. No client/account was created for verification.

## Verification results

- Targeted ESLint: passed for ClientsPage, ClientFormPages, ClientForm, useClients and endpoints adapter.
- Full frontend lint: failed with nine pre-existing no-unused-vars errors (AppButton, setPage, meta in DashboardActiveProjects, DashboardMaterialStock and DashboardRecentSitePhotos). Those files were not changed.
- Production build: passed; existing Zod annotation warnings remain non-blocking.
- git diff --check: passed.
- Backend syntax/style checks: not applicable; no backend source changed.
- Account/client creation, rollback, duplicate handling and migration runtime checks: not performed; provisioning is deferred and no live records were created.
- Browser/mobile/theme/error interaction checks: pending, no connected document session. Existing dashboard verification limitations remain unchanged.

## Follow-up: client-role directory visibility

Added read-only GET /clients/accounts and separate Client Accounts / Business Contacts tabs. Membership in the current company's web-guard client role is sufficient for inclusion without a business Client record; additional roles are allowed and deleted users excluded. Existing account statuses remain unchanged. Viewing requires clients.view plus users.view, retaining the existing user-directory authorization boundary. No role grants, records, memberships, migrations, relationships or provisioning were changed.

Read-only HTTP-kernel checks using existing authorized identities returned the two existing client-role accounts in their own tenants and an empty collection in another accessible tenant. A fourth tenant lacked a suitable existing administrator for runtime checks. Other-tenant login search and non-client login search returned zero; exact own-client login search returned the expected account. No identifying fields were included in the verification output. Disabled/multi-role/multi-page-populated scenarios were not available in existing data; their inclusion is source-reviewed, not a runtime pass.
