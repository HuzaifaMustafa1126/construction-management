/* eslint-disable react/prop-types */
import { Navigate, Outlet, useLocation } from "react-router-dom";
import { useAuth } from "../features/auth/context/AuthContext";
export default function ProtectedRoute({ permission }) {
  const { isAuthenticated, loading, can, user } = useAuth();
  const location = useLocation();
  if (loading) return <div className="empty-state">Loading workspace…</div>;
  if (!isAuthenticated)
    return <Navigate to="/login" state={{ from: location }} replace />;
  if (user?.is_platform_admin)
    return <Navigate to="/platform/dashboard" replace />;
  if (!can(permission)) return <Navigate to="/dashboard" replace />;
  return <Outlet />;
}
export function GuestRoute() {
  const { isAuthenticated, loading, user } = useAuth();
  if (loading) return null;
  return isAuthenticated ? (
    <Navigate
      to={user?.is_platform_admin ? "/platform/dashboard" : "/dashboard"}
      replace
    />
  ) : (
    <Outlet />
  );
}
export function PlatformRoute() {
  const { isAuthenticated, loading, user } = useAuth();
  const location = useLocation();
  if (loading)
    return <div className="empty-state">Loading platform workspace…</div>;
  if (!isAuthenticated)
    return <Navigate to="/login" state={{ from: location }} replace />;
  return user?.is_platform_admin ? (
    <Outlet />
  ) : (
    <Navigate to="/dashboard" replace />
  );
}
export function PermissionGuard({
  permission,
  children,
  fallback = null,
  loadingFallback = null,
}) {
  const { can, loading } = useAuth();
  if (loading) return loadingFallback;
  return can(permission) ? children : fallback;
}
