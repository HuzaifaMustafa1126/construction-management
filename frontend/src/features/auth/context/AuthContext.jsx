/* eslint-disable react/prop-types, react-refresh/only-export-components */
import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useState,
} from "react";
import { authApi, companyApi } from "../../../api/endpoints";

const AuthContext = createContext(null);
const CompanyContext = createContext(null);
const tokenKey = "auth_token";
function permissionsOf(user) {
  const direct = user?.permissions || user?.effective_permissions || [];
  const viaRoles = (user?.roles || []).flatMap((r) => r.permissions || []);
  return new Set(
    [...direct, ...viaRoles]
      .map((x) => (typeof x === "string" ? x : x?.name))
      .filter(Boolean),
  );
}
export function AuthProvider({ children }) {
  const [user, setUser] = useState(null);
  const [company, setCompany] = useState(null);
  const [companyError, setCompanyError] = useState(null);
  const [loading, setLoading] = useState(true);
  const clearSession = useCallback(() => {
    sessionStorage.removeItem(tokenKey);
    setUser(null);
    setCompany(null);
    setCompanyError(null);
    window.dispatchEvent(new Event("tenant-cache-clear"));
  }, []);
  const refreshUser = useCallback(async () => {
    const next = await authApi.me();
    setUser(next);
    return next;
  }, []);
  const refreshCompany = useCallback(async () => {
    setCompanyError(null);
    try {
      const next = await companyApi.get();
      setCompany(next);
      return next;
    } catch (error) {
      setCompanyError(error);
      throw error;
    }
  }, []);
  const establishCompany = useCallback(
    async (identity) => {
      try {
        return await refreshCompany();
      } catch (error) {
        if (error?.status === 403 && identity?.company) {
          setCompany(identity.company);
          return identity.company;
        }
        throw error;
      }
    },
    [refreshCompany],
  );
  useEffect(() => {
    const init = async () => {
      if (!sessionStorage.getItem(tokenKey)) {
        setLoading(false);
        return;
      }
      try {
        const identity = await refreshUser();
        if (!identity?.is_platform_admin) await establishCompany(identity);
      } catch {
        clearSession();
      } finally {
        setLoading(false);
      }
    };
    const expired = () => clearSession();
    window.addEventListener("auth-expired", expired);
    init();
    return () => window.removeEventListener("auth-expired", expired);
  }, [clearSession, establishCompany, refreshUser]);
  const login = async (email, password) => {
    clearSession();
    const result = await authApi.login({ email, password });
    const token = result?.token || result?.access_token;
    if (!token)
      throw new Error("The login response did not include an access token.");
    sessionStorage.setItem(tokenKey, token);
    try {
      const identity = await refreshUser();
      if (!identity?.is_platform_admin) await establishCompany(identity);
      return identity;
    } catch (e) {
      clearSession();
      throw e;
    }
  };
  const logout = async () => {
    try {
      await authApi.logout();
    } finally {
      clearSession();
    }
  };
  const permissions = useMemo(() => permissionsOf(user), [user]);
  const can = useCallback(
    (p) => !p || permissions.has("*") || permissions.has(p),
    [permissions],
  );
  return (
    <AuthContext.Provider
      value={{
        user,
        loading,
        login,
        logout,
        refreshUser,
        isAuthenticated: !!user,
        permissions,
        can,
      }}
    >
      <CompanyContext.Provider
        value={{ company, companyError, isLoading: loading, refreshCompany }}
      >
        {children}
      </CompanyContext.Provider>
    </AuthContext.Provider>
  );
}
export function useAuth() {
  const v = useContext(AuthContext);
  if (!v) throw new Error("useAuth must be used inside AuthProvider");
  return v;
}
export function useCompany() {
  const v = useContext(CompanyContext);
  if (!v) throw new Error("useCompany must be used inside AuthProvider");
  return v;
}
