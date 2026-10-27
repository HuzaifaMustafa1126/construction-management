# WowDash React Theme: Architecture and Routing Guide

This document explains how this React admin theme starts, routes between screens, builds pages, renders the shared dashboard layout, and switches between light and dark mode. It describes the current code, including features that are only UI demos and are not connected to a backend.

## 1. Technology overview

The project is a client-side single-page application (SPA) built with:

- React 18 for components and state
- Vite for development and production builds
- React Router for client-side routing
- Bootstrap and the theme's utility CSS for layout and styling
- Iconify for icons
- ApexCharts for charts
- Several page-specific libraries for calendars, tables, editors, galleries, drag-and-drop, and date pickers

The main npm commands are:

```bash
npm run dev      # Start the Vite development server
npm run build    # Create the production build in dist/
npm run preview  # Preview the production build
npm run lint     # Run ESLint
```

## 2. Application startup flow

The application starts through this chain:

```text
index.html
  -> src/main.jsx
      -> src/App.jsx
          -> BrowserRouter
              -> route-matched page
                  -> MasterLayout (for dashboard pages)
                      -> Breadcrumb
                      -> page-specific Layer component
```

### `index.html`

`index.html` contains the root element:

```html
<div id="root"></div>
<script type="module" src="/src/main.jsx"></script>
```

It also loads the global styles from `public/assets/css/`, including Bootstrap, icon styles, plugin styles, `style.css`, and `extra.css`. Files inside `public/` are served from the site root, so `public/assets/css/style.css` is referenced as `assets/css/style.css`.

### `src/main.jsx`

`main.jsx` imports additional third-party styles and Bootstrap JavaScript, then mounts `<App />` into `#root`:

```jsx
createRoot(document.getElementById("root")).render(<App />);
```

### `src/App.jsx`

`App.jsx` is the route registry. It wraps the application in `BrowserRouter`, places `RouteScrollToTop` inside the router, and uses `Routes` to choose one page component for the current URL.

Example:

```jsx
<Route path='/invoice-list' element={<InvoiceListPage />} />
```

When the browser location is `/invoice-list`, React Router renders `InvoiceListPage` without doing a full browser reload.

## 3. Routing behavior

### Route categories

The routes currently registered in `src/App.jsx` are:

| Area | URLs |
| --- | --- |
| Dashboards | `/`, `/index-2` through `/index-11` |
| Users and roles | `/add-user`, `/users-grid`, `/users-list`, `/view-profile`, `/role-access`, `/assign-role` |
| Authentication and status | `/sign-in`, `/sign-up`, `/forgot-password`, `/access-denied`, `/coming-soon`, `/maintenance` |
| Communication | `/email`, `/chat-message`, `/chat-profile`, `/notification`, `/notification-alert` |
| Content | `/blog`, `/blog-details`, `/add-blog`, `/portfolio`, `/testimonials`, `/faq`, `/terms-condition` |
| AI/generator screens | `/code-generator`, `/code-generator-new`, `/text-generator`, `/text-generator-new`, `/image-generator`, `/video-generator`, `/voice-generator` |
| Commerce and finance | `/marketplace`, `/marketplace-details`, `/wallet`, `/payment-gateway`, `/currencies` |
| Invoices | `/invoice-add`, `/invoice-edit`, `/invoice-list`, `/invoice-preview` |
| Apps and media | `/calendar`, `/calendar-main`, `/kanban`, `/gallery`, `/gallery-grid`, `/gallery-masonry`, `/gallery-hover`, `/image-upload`, `/videos` |
| Forms and tables | `/form`, `/form-layout`, `/form-validation`, `/wizard`, `/table-basic`, `/table-data` |
| Charts | `/line-chart`, `/column-chart`, `/pie-chart` |
| UI elements | `/alert`, `/avatar`, `/badges`, `/button`, `/card`, `/carousel`, `/colors`, `/dropdown`, `/list`, `/pagination`, `/progress`, `/radio`, `/star-rating`, `/switch`, `/tabs`, `/tags`, `/tooltip`, `/typography` |
| Settings and other | `/company`, `/language`, `/theme`, `/widgets`, `/blank-page`, `/view-details`, `/starred`, `/pricing` |

Any unmatched URL reaches the final wildcard route:

```jsx
<Route path='*' element={<ErrorPage />} />
```

In this project, `ErrorPage` still uses `MasterLayout`, so a missing route displays the 404 content inside the dashboard shell.

### Navigation links

The layout uses two React Router link types:

- `Link` performs normal SPA navigation without refreshing the document.
- `NavLink` also provides an active state. The sidebar uses it so the current menu item can receive the `active-page` class.

The sidebar menu is written directly in `MasterLayout.jsx`; it is not generated from the route declarations. Therefore, adding a route to `App.jsx` does **not** automatically add it to the sidebar. Both places must be updated when a new navigable page is introduced.

### Scroll restoration

`RouteScrollToTop.jsx` watches the current location and runs `window.scrollTo(0, 0)` after navigation. This makes each routed page start at the top.

It also globally replaces `console.warn` with an empty function. That is unrelated to scrolling and hides every warning in the application; it should normally be removed or narrowed during future cleanup.

### BrowserRouter server requirement

Because this app uses `BrowserRouter`, production hosting must return `index.html` for unknown application paths. For example, directly opening `/invoice-list` must be rewritten to `index.html`, after which React Router handles the URL. Without an SPA fallback/rewrite rule, refreshing a nested route can produce a server 404.

## 4. Page structure

Most dashboard page files are deliberately thin composition components. For example, `HomePageOne.jsx` follows this structure:

```jsx
<MasterLayout>
  <Breadcrumb title='AI' />
  <DashBoardLayerOne />
</MasterLayout>
```

The responsibilities are separated as follows:

| Layer | Location | Responsibility |
| --- | --- | --- |
| Route/page | `src/pages/` | Connects a URL-level screen to its layout and feature component |
| Shared layout | `src/masterLayout/MasterLayout.jsx` | Sidebar, top navigation, responsive menu, content container, and footer |
| Feature layer | `src/components/*Layer.jsx` | Main content and behavior for one screen |
| Child component | `src/components/child/` | Smaller cards, charts, forms, tables, and reusable screen sections |
| Helper | `src/helper/` | Cross-cutting UI behavior such as theme toggle and scroll-to-top |
| Hook | `src/hook/` | Shared hooks/configuration, mainly ApexCharts settings |

This means a route does not normally contain the detailed screen UI itself. It selects a page; the page composes the shell; and the layer/child components implement the content.

### Pages outside `MasterLayout`

These screens intentionally render without the dashboard sidebar, header, breadcrumb, and footer:

- `SignInPage.jsx`
- `SignUpPage.jsx`
- `ForgotPasswordPage.jsx`
- `AccessDeniedPage.jsx`
- `ComingSoonPage.jsx`
- `MaintenancePage.jsx`

They directly render their corresponding layer component. This is the correct pattern for full-screen authentication or system-status pages.

## 5. How `MasterLayout` works

`MasterLayout.jsx` receives page content through its `children` prop:

```jsx
<div className='dashboard-main-body'>{children}</div>
```

It renders this overall structure:

```text
section.overlay
  aside.sidebar
    logos
    sidebar navigation
  main.dashboard-main
    header/navbar
      mobile menu button
      sidebar collapse button
      search, language, messages, notifications, profile
      ThemeToggleButton
    div.dashboard-main-body
      page children
    footer
```

### Desktop sidebar

`sidebarActive` controls the compact/expanded desktop sidebar. Clicking the sidebar control toggles the `active` class on both the sidebar and main content area. CSS uses this class to change their widths and positions.

### Mobile sidebar

`mobileMenu` controls the responsive drawer. When true:

- the sidebar receives `sidebar-open`;
- the outer overlay receives `active`;
- the close button or mobile menu button toggles it again.

The actual mobile behavior is implemented directly inside `MasterLayout`. `src/helper/MobileMenuToggle.jsx` is a separate helper but is not imported by `MasterLayout`, so it is currently unused.

### Dropdown menus and active routes

The layout has a `useEffect` that runs whenever `location.pathname` changes. It:

1. Adds click listeners to sidebar dropdown triggers.
2. Closes other dropdowns when one dropdown is opened.
3. Expands the selected submenu by setting its `maxHeight`.
4. Searches submenu links for the current path and opens the containing dropdown.
5. Removes the listeners when the effect reruns or the layout unmounts.

This part uses direct DOM queries and class manipulation rather than React state. Consequently, sidebar submenu markup and class names such as `.dropdown` and `.sidebar-submenu` must remain consistent for the behavior to work.

The top-bar language, message, notification, and profile dropdowns use Bootstrap's `data-bs-toggle='dropdown'`. Bootstrap's bundled JavaScript is loaded in `main.jsx`, which is what makes those controls interactive.

## 6. Light and dark theme behavior

The working theme switch is `src/helper/ThemeToggleButton.jsx`, rendered in the dashboard header.

### Theme flow

```text
User clicks ThemeToggleButton
  -> light changes to dark, or dark changes to light
  -> React state is updated
  -> localStorage["theme"] is updated
  -> data-theme is set on the <html> element
  -> CSS variables and dark selectors change the appearance
```

On initialization, the component reads:

```js
localStorage.getItem("theme") || "light"
```

It then applies the value like this:

```js
document.documentElement.setAttribute("data-theme", theme);
```

The resulting HTML is either:

```html
<html data-theme="light">
```

or:

```html
<html data-theme="dark">
```

### CSS variable switching

`public/assets/css/style.css` defines the normal color tokens first, including:

```css
--base: #fff;
--black: var(--dark-2);
--bg-color: var(--neutral-50);
--text-primary-light: var(--neutral-900);
--text-secondary-light: var(--neutral-600);
```

The `[data-theme="dark"]` block overrides those variables:

```css
[data-theme="dark"] {
  --black: var(--base);
  --white: var(--dark-2);
  --bg-color: var(--dark-1);
  --text-primary-light: var(--base);
  --text-secondary-light: #d1d5db;
  /* neutral palette is also reversed */
}
```

Components use utility classes backed by these variables. Changing one attribute on `<html>` therefore updates most of the interface without rerendering every component. Additional selectors such as `html[data-theme="dark"] ...` handle special cases. The sidebar includes separate `.light-logo` and `.dark-logo` images, and CSS decides which one is visible.

The selected mode remains after reload because it is saved in browser `localStorage`. It is local to that browser and is not saved to a user account or server.

### What `/theme` currently does

`ThemePage.jsx` renders `ThemeLayer.jsx` inside `MasterLayout`. Despite its name, this settings screen currently only provides a UI mockup:

- uploaded logo files are read with `FileReader` and previewed in component state;
- the color choices are plain radio inputs;
- Reset uses normal HTML form reset behavior;
- Save Change submits to `#`;
- no chosen color or logo is applied globally;
- no settings are saved to localStorage, an API, or a database.

Therefore, `/theme` and `ThemeToggleButton` are separate features. Only `ThemeToggleButton` currently changes the application's live light/dark mode.

## 7. Styling system

Styling comes from several levels:

1. `index.html` loads the theme's compiled CSS and plugin styles from `public/assets/css/`.
2. `main.jsx` imports CSS shipped by npm packages.
3. Components compose Bootstrap classes and theme utility classes such as `text-primary-600`, `bg-neutral-200`, `radius-8`, and `w-40-px`.
4. `style.css` maps those utility classes to CSS custom properties, allowing dark mode to change the palette centrally.
5. `extra.css` contains project-level overrides/additional styling.

Most visual changes should be made through variables or reusable theme classes instead of hard-coded inline colors. That preserves dark-mode behavior.

Static images live under `public/assets/images/` and are referenced with URLs such as `assets/images/logo.png`.

## 8. Charts and other feature logic

`src/hook/useReactApexChart.jsx` is a large configuration hook. It builds and returns the `series` and `options` objects used by chart components. Individual child components call the hook, select the configuration they need, and pass it to `react-apexcharts`.

Many feature screens currently contain local/demo data. The template does not provide a shared API client, authentication provider, global state store, or backend integration. Links such as Log Out and buttons on several demo pages may therefore be visual placeholders until application logic is connected.

## 9. Adding a new dashboard page

Use this sequence:

1. Create the main feature component, for example `src/components/ProjectsLayer.jsx`.
2. Create a thin page wrapper at `src/pages/ProjectsPage.jsx`:

```jsx
import Breadcrumb from "../components/Breadcrumb";
import ProjectsLayer from "../components/ProjectsLayer";
import MasterLayout from "../masterLayout/MasterLayout";

const ProjectsPage = () => (
  <MasterLayout>
    <Breadcrumb title='Projects' />
    <ProjectsLayer />
  </MasterLayout>
);

export default ProjectsPage;
```

3. Import it in `src/App.jsx` and register the route:

```jsx
import ProjectsPage from "./pages/ProjectsPage";

<Route path='/projects' element={<ProjectsPage />} />
```

4. Add a `NavLink` in `MasterLayout.jsx` if it should appear in the sidebar:

```jsx
<NavLink
  to='/projects'
  className={({ isActive }) => (isActive ? "active-page" : "")}
>
  Projects
</NavLink>
```

5. Confirm that direct navigation, browser refresh, active menu highlighting, mobile navigation, and both theme modes work.

For a full-screen page, omit `MasterLayout` and render its layer directly, following `SignInPage.jsx`.

## 10. Important current limitations and quirks

- There is no route guard. Typing any registered dashboard URL opens it even when the user is not signed in.
- Sign-in and sign-out are UI-only until authentication logic is implemented.
- Sidebar links and routes are maintained separately and can get out of sync.
- `Breadcrumb` links to `/index`, but `App.jsx` does not register `/index`; that click falls through to the 404 route. It should link to `/` or an `/index` route should be added.
- `/calendar` and `/calendar-main` intentionally point to the same component.
- The `exact` prop in `App.jsx` is unnecessary with modern `Routes` matching (React Router v6+ behavior).
- The theme button displays the text `Toggle Theme`; there is no accessible label describing the current/next mode and no system-theme detection.
- Theme is applied after the React component mounts, so a saved dark theme may briefly show light colors during initial loading.
- The `/theme` form is not connected to the working light/dark toggle or global brand colors.
- Chart colors are mostly hard-coded in JavaScript and may not automatically adapt when dark mode changes.
- `RouteScrollToTop` disables all `console.warn` messages globally.
- Much of the displayed content is template/demo data rather than data loaded from an API.

## 11. Quick file map

| File/folder | Purpose |
| --- | --- |
| `index.html` | HTML shell and global stylesheet links |
| `src/main.jsx` | React mount point and npm stylesheet/script imports |
| `src/App.jsx` | Complete URL route registry |
| `src/masterLayout/MasterLayout.jsx` | Shared authenticated/dashboard shell and sidebar navigation |
| `src/pages/` | URL-level page wrappers |
| `src/components/` | Screen-level feature components |
| `src/components/child/` | Smaller reusable feature/UI sections |
| `src/helper/ThemeToggleButton.jsx` | Working light/dark toggle and persistence |
| `src/helper/RouteScrollToTop.jsx` | Scroll reset after route changes |
| `src/hook/useReactApexChart.jsx` | Central chart options and series |
| `public/assets/css/style.css` | Main theme, utility classes, and dark-mode variables |
| `public/assets/css/extra.css` | Extra project styles/overrides |
| `public/assets/images/` | Public image assets |

