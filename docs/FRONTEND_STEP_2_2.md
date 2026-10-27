# Frontend Step 2.2

## Authentication and redirects

`AuthProvider` is the single authentication source. Login posts to `/api/v1/login`, stores only the Sanctum bearer token in session storage, refreshes `/api/v1/me`, and loads company context for company users. Platform users redirect to `/platform/dashboard`; all company roles redirect to `/dashboard`. Logout clears user/company state and dispatches `tenant-cache-clear` so TanStack Query removes protected data.

The Axios interceptor attaches the token and clears the session on protected 401 responses. A 403 remains an authorization response and does not force logout.

## Company context

`/me` returns backend-evaluated company roles, effective permissions, and minimal company identity. Users with `company.view` additionally load `/api/v1/company`. Restricted users safely retain the minimal identity if that endpoint returns 403. No company selector or client-controlled company ID exists.

## Navigation and dashboard

`src/components/layout/navigation.js` is the centralized implemented-route navigation contract. Items use semantic `AppIcon` names and verified Laravel permission catalog strings. Role names are shown to the user but do not grant access.

The shared `/dashboard` adapts to effective permissions. When `projects.view` is granted, it calls `/api/v1/projects`; Laravel applies tenant and assignment visibility. Pagination metadata supplies the real total, while the first six authorized projects provide the displayed list. No totals are inferred from a partial page. Organization quick actions appear only when their exact backend permission exists.

The workspace uses the global sidebar/layout, semantic theme tokens, responsive drawer behavior, profile/settings controls, and shared feedback primitives.

## Testing and limitations

Frontend unit coverage includes login form/ref regression, platform edit/password setup regression, supervisor project rendering, and custom-role permission behavior. Backend coverage verifies `/me` company/role/permission serialization and no cross-company identity leakage.

Projects currently have dashboard summaries but no routed project-management frontend, so no project navigation link is exposed. Daily reports, photos, and material modules remain future frontend phases. The login API deliberately uses a generic unavailable-account response, so the UI cannot distinguish incorrect credentials from inactive users or suspended companies without weakening enumeration resistance.
