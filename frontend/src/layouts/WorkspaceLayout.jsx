import { NavLink, Outlet, useLocation, useNavigate } from "react-router-dom";
import { useState } from "react";
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
  const { sidebarCollapsed } = useTheme();
  const navigate = useNavigate();
  const location = useLocation();
  const [busy, setBusy] = useState(false);
  const [open, setOpen] = useState(false);
  const current =
    companyNavigation
      .flatMap((group) => group.items)
      .find((item) => item.route === location.pathname)?.label || "Workspace";

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
        className="workspace-backdrop"
        aria-label="Close navigation"
        onClick={() => setOpen(false)}
      />
      <aside className="sidebar" aria-label="Company navigation">
        <div className="sidebar-logo px-24 py-20">
          <strong>{company?.name || "Company workspace"}</strong>
        </div>
        <nav className="sidebar-menu px-12">
          {companyNavigation.map((group) => (
            <section className="nav-group" key={group.label}>
              <small>{group.label}</small>
              {group.items
                .filter((item) => can(item.permission))
                .map((item) => (
                  <NavLink
                    key={item.route}
                    to={item.route}
                    onClick={() => setOpen(false)}
                    className={({ isActive }) =>
                      isActive ? "active-page" : ""
                    }
                    title={item.label}
                  >
                    <AppIcon name={item.icon} />
                    <span>{item.label}</span>
                  </NavLink>
                ))}
            </section>
          ))}
        </nav>
      </aside>
      <main className="dashboard-main">
        <header className="navbar-header">
          <div className="workspace-heading">
            <AppButton
              variant="ghost"
              aria-label="Toggle navigation"
              onClick={() => setOpen((value) => !value)}
            >
              <AppIcon name="menu" />
            </AppButton>
            <div>
              <small>Workspace / {current}</small>
              <strong className="d-block">
                {company?.name || "Workspace"}
              </strong>
            </div>
          </div>
          <div className="workspace-user">
            <AppDropdown label={displayName(user)}>
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
    </div>
  );
}
