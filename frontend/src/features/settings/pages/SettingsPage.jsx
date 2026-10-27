import { Link } from "react-router-dom";
import { PageHeader, SectionCard, AppIcon } from "../../../components/ui/AppUI";
import { AppRadio, AppSwitch } from "../../../components/ui/Controls";
import { useTheme } from "../../../lib/theme/useTheme";
import { useAuth } from "../../auth/context/AuthContext";

export default function SettingsPage() {
  const settings = useTheme();
  const { user: contextUser } = useAuth();
  const user = contextUser;
  const roles = user?.roles?.map((role) => typeof role === "string" ? role : role.name).filter(Boolean).join(", ");

  return <>
    <PageHeader title="Settings" description="Account information and browser presentation preferences." />
    <SectionCard title="Appearance">
      <div className="settings-grid">
        <fieldset><legend><AppIcon name="appearance" /> Color mode</legend><AppRadio name="mode" label="Light" checked={settings.mode === "light"} onChange={() => settings.setMode("light")} /><AppRadio name="mode" label="Dark" checked={settings.mode === "dark"} onChange={() => settings.setMode("dark")} /></fieldset>
        <fieldset><legend>Primary accent</legend>{["blue", "green", "orange"].map((accent) => <AppRadio key={accent} name="accent" label={accent[0].toUpperCase() + accent.slice(1)} checked={settings.accent === accent} onChange={() => settings.setAccent(accent)} />)}</fieldset>
        <fieldset><legend>Table density</legend><AppRadio name="density" label="Comfortable" checked={settings.density === "comfortable"} onChange={() => settings.setDensity("comfortable")} /><AppRadio name="density" label="Compact" checked={settings.density === "compact"} onChange={() => settings.setDensity("compact")} /></fieldset>
        <fieldset><legend>Navigation</legend><AppSwitch label="Collapse desktop sidebar" checked={settings.sidebarCollapsed} onChange={(event) => settings.setSidebarCollapsed(event.target.checked)} /></fieldset>
      </div>
    </SectionCard>
    <SectionCard title="Account information"><div className="profile-grid"><div className="profile-item"><small>Email</small><strong>{user?.email || "—"}</strong></div><div className="profile-item"><small>Status</small><strong>{user?.status || "—"}</strong></div><div className="profile-item"><small>Identity</small><strong>{user?.is_platform_admin ? "Platform Super Admin" : roles || user?.role || "Company user"}</strong></div></div></SectionCard>
    <SectionCard title="Security"><Link className="app-button secondary" to={user?.is_platform_admin ? "/platform/profile/security" : "/profile/security"}><AppIcon name="security" /> Change password</Link></SectionCard>
  </>;
}
