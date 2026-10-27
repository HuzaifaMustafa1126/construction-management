import { NavLink, Outlet, useNavigate } from "react-router-dom";
import { useState } from "react";
import { useAuth, useCompany } from "../context/AuthContext";
import { AppButton, AppIcon } from "../design-system/AppUI";
const links = [
  ["/dashboard", "dashboard", "Dashboard", null],
  ["/company", "company", "Company", "company.view"],
  ["/users", "users", "Users", "users.view"],
  ["/roles", "roles", "Roles", "roles.view"],
  ["/departments", "database", "Departments", "departments.view"],
  ["/profile", "user", "My profile", null],
  ["/settings", "settings", "Settings", null],
];
export default function WorkspaceLayout() {
  const { user, logout, can } = useAuth();
  const { company } = useCompany();
  const nav = useNavigate();
  const [busy, setBusy] = useState(false);
  return (
    <div className="dashboard-wrapper">
      <aside className="sidebar">
        <div className="sidebar-logo px-24 py-20">
          <strong>{company?.name || "Company workspace"}</strong>
        </div>
        <nav className="sidebar-menu px-12">
          {links
            .filter((x) => can(x[3]))
            .map(([to, icon, label]) => (
              <NavLink
                key={to}
                to={to}
                className={({ isActive }) => (isActive ? "active-page" : "")}
              >
                <AppIcon name={icon} />
                <span>{label}</span>
              </NavLink>
            ))}
        </nav>
      </aside>
      <main className="dashboard-main">
        <header className="navbar-header">
          <div>
            <strong>{company?.name || "Workspace"}</strong>
            <small className="d-block text-secondary-light">
              {user?.name ||
                [user?.first_name, user?.last_name].filter(Boolean).join(" ")}
            </small>
          </div>
          <div className="d-flex gap-2">
            <AppButton variant="secondary" onClick={() => nav("/settings")}>
              <AppIcon name="settings" /> Appearance
            </AppButton>
            <AppButton
              variant="secondary"
              busy={busy}
              onClick={async () => {
                setBusy(true);
                try {
                  await logout();
                } finally {
                  nav("/login", { replace: true });
                }
              }}
            >
              <AppIcon name="logout" />
              Logout
            </AppButton>
          </div>
        </header>
        <div className="dashboard-main-body phase2-page">
          <Outlet />
        </div>
      </main>
    </div>
  );
}
