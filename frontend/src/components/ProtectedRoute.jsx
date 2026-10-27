/* eslint-disable react/prop-types */
import { Navigate, Outlet, useLocation } from "react-router-dom";
import { useAuth } from "../context/AuthContext";
export default function ProtectedRoute({ permission }) {
  const { isAuthenticated, loading, can } = useAuth();
  const location = useLocation();
  if (loading) return <div className="empty-state">Loading workspace…</div>;
  if (!isAuthenticated)
    return <Navigate to="/login" state={{ from: location }} replace />;
  if (!can(permission)) return <Navigate to="/dashboard" replace />;
  return <Outlet />;
}
export function GuestRoute() {
  const { isAuthenticated, loading } = useAuth();
  if (loading) return null;
  return isAuthenticated ? <Navigate to="/dashboard" replace /> : <Outlet />;
}
export function PermissionGuard({ permission, children, fallback = null }) {
  return useAuth().can(permission) ? children : fallback;
}
