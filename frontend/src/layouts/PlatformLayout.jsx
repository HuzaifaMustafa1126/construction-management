import { Link, Outlet, useNavigate } from "react-router-dom";
import { useState } from "react";
import { useAuth } from "../features/auth/context/AuthContext";
import { AppButton, AppIcon } from "../components/ui/AppUI";
import { AppDropdown } from "../components/ui/Controls";

const displayName = (user) =>
  user?.name ||
  [user?.first_name, user?.last_name].filter(Boolean).join(" ") ||
  "Platform administrator";

export default function PlatformLayout() {
  const { user, logout } = useAuth();
  const navigate = useNavigate();
  const [busy, setBusy] = useState(false);
  const signOut = async () => {
    setBusy(true);
    try {
      await logout();
    } finally {
      navigate("/login", { replace: true });
    }
  };
  return (
    <>
      <header className="page-header platform-header">
        <strong>BUILDWISE PLATFORM</strong>
        <nav className="button-group">
          <Link to="/platform/dashboard">Dashboard</Link>
          <Link to="/platform/companies">Companies</Link>
        </nav>
        <AppDropdown label={displayName(user)}>
          <div className="profile-menu-identity">
            <strong>{displayName(user)}</strong>
            <small>{user?.email}</small>
            <small>Platform Super Admin</small>
          </div>
          <Link to="/platform/profile">
            <AppIcon name="profile" /> My profile
          </Link>
          <Link to="/platform/settings">
            <AppIcon name="settings" /> Settings
          </Link>
          <AppButton variant="ghost" busy={busy} onClick={signOut}>
            <AppIcon name="logout" /> Log out
          </AppButton>
        </AppDropdown>
      </header>
      <main className="page-container phase2-page">
        <Outlet />
      </main>
    </>
  );
}
