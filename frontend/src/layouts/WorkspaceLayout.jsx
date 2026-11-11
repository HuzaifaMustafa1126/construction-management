import { NavLink, Outlet, useLocation, useNavigate } from "react-router-dom";
import { useEffect, useState } from "react";
import { useAuth, useCompany } from "../features/auth/context/AuthContext";
import { AppButton, AppIcon } from "../components/ui/AppUI";
import { AppDropdown } from "../components/ui/Controls";
import { useTheme } from "../lib/theme/useTheme";
import { companyNavigation } from "../components/layout/navigation";

const displayName = (user) =>
  user?.name ||
  [user?.first_name, user?.last_name].filter(Boolean).join(" ") ||
  "User";

const roleName = (user) => {
  const role = user?.roles?.[0];
  return typeof role === "string"
    ? role
    : role?.name || user?.role || "Company user";
};

export default function WorkspaceLayout() {
  const { user, logout, can } = useAuth();
  const { company } = useCompany();
  const { sidebarCollapsed, setSidebarCollapsed } = useTheme();
  const navigate = useNavigate();
  const location = useLocation();
  const [busy, setBusy] = useState(false);
  const [open, setOpen] = useState(false);
  const visibleNavigation = companyNavigation
    .map((group) => ({
      ...group,
      items: group.items.filter((item) => can(item.permission)),
    }))
    .filter((group) => group.items.length);
  const activeItem = companyNavigation
    .flatMap((group) => group.items)
    .sort((a, b) => b.route.length - a.route.length)
    .find(
      (item) =>
        location.pathname === item.route ||
        location.pathname.startsWith(`${item.route}/`),
    );
  const current = activeItem?.label || "Workspace";
  const initials = displayName(user)
    .split(/\s+/)
    .map((part) => part[0])
    .join("")
    .slice(0, 2)
    .toUpperCase();
  const mobilePrimary = companyNavigation
    .flatMap((group) => group.items)
    .filter((item) => item.mobile && can(item.permission));

  useEffect(() => {
    setOpen(false);
  }, [location.pathname]);

  useEffect(() => {
    if (!open) return undefined;
    const close = (event) => event.key === "Escape" && setOpen(false);
    document.addEventListener("keydown", close);
    return () => document.removeEventListener("keydown", close);
  }, [open]);

  const signOut = async () => {
    setBusy(true);
    try {
      await logout();
    } finally {
      navigate("/login", { replace: true });
    }
  };

  return (
    <div
      className={`dashboard-wrapper ${open ? "sidebar-open" : ""} ${sidebarCollapsed ? "sidebar-collapsed" : ""}`}
    >
      <button
        type="button"
        className="workspace-backdrop"
        aria-label="Close navigation"
        onClick={() => setOpen(false)}
      />
      <aside className="sidebar" aria-label="Company navigation">
        <div className="sidebar-logo">
          <span className="sidebar-brand-mark" aria-hidden="true">
            <AppIcon name="company" size={28} />
          </span>
          <span className="sidebar-brand-copy">
            <strong>Buildwise</strong>
            <small>{company?.name || "Construction ERP"}</small>
          </span>
          <button
            type="button"
            className="sidebar-close"
            aria-label="Close navigation"
            onClick={() => setOpen(false)}
          >
            <AppIcon name="close" />
          </button>
        </div>
        <nav className="sidebar-menu">
          {visibleNavigation.map((group) => (
            <section className="nav-group" key={group.label}>
              <small>{group.label}</small>
              {group.items.map((item) => (
                <NavLink
                  key={item.route}
                  to={item.route}
                  className={activeItem?.route === item.route ? "active-page" : ""}
                  title={sidebarCollapsed ? item.label : undefined}
                >
                  <AppIcon name={item.icon} />
                  <span>{item.label}</span>
                </NavLink>
              ))}
            </section>
          ))}
        </nav>
        <button
          type="button"
          className="sidebar-collapse"
          aria-label={sidebarCollapsed ? "Expand sidebar" : "Collapse sidebar"}
          onClick={() => setSidebarCollapsed(!sidebarCollapsed)}
        >
          <AppIcon name={sidebarCollapsed ? "next" : "back"} />
          <span>Collapse sidebar</span>
        </button>
      </aside>
      <main className="dashboard-main">
        <header className="navbar-header">
          <div className="workspace-heading">
            <AppButton
              variant="ghost"
              aria-label="Toggle navigation"
              aria-expanded={open}
              onClick={() => setOpen((value) => !value)}
            >
              <AppIcon name="menu" />
            </AppButton>
            <div>
              <small>{company?.name || "Company workspace"}</small>
              <strong>{current}</strong>
            </div>
          </div>
          <div className="workspace-user">
            <AppDropdown
              label={
                <span className="user-menu-label">
                  <span className="user-avatar" aria-hidden="true">
                    {initials}
                  </span>
                  <span className="user-menu-copy">
                    <strong>{displayName(user)}</strong>
                    <small>{roleName(user).replaceAll("_", " ")}</small>
                  </span>
                </span>
              }
            >
              <div className="profile-menu-identity">
                <strong>{displayName(user)}</strong>
                <small>{user?.email}</small>
                <small>{roleName(user)}</small>
              </div>
              <NavLink to="/profile">
                <AppIcon name="profile" /> My profile
              </NavLink>
              <NavLink to="/settings">
                <AppIcon name="settings" /> Settings
              </NavLink>
              <AppButton variant="ghost" busy={busy} onClick={signOut}>
                <AppIcon name="logout" /> Log out
              </AppButton>
            </AppDropdown>
          </div>
        </header>
        <div className="dashboard-main-body phase2-page">
          <Outlet />
        </div>
      </main>
      {can("daily_reports.create") && (
        <NavLink className="mobile-report-action" to="/daily-reports/new">
          <AppIcon name="add" />
          <span>Daily Report</span>
        </NavLink>
      )}
      <nav className="mobile-navigation" aria-label="Mobile navigation">
        {mobilePrimary.map((item) => (
          <NavLink
            key={item.route}
            to={item.route}
            className={activeItem?.route === item.route ? "active-page" : ""}
          >
            <AppIcon name={item.icon} />
            <span>{item.mobileLabel || item.label}</span>
          </NavLink>
        ))}
        <button
          type="button"
          className={open ? "active-page" : ""}
          aria-expanded={open}
          onClick={() => setOpen((value) => !value)}
        >
          <AppIcon name="menu" />
          <span>More</span>
        </button>
      </nav>
    </div>
  );
}
