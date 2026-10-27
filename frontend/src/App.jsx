import { BrowserRouter, Navigate, Route, Routes } from "react-router-dom";
import { ToastContainer } from "react-toastify";
import { AuthProvider } from "./context/AuthContext";
import ProtectedRoute, { GuestRoute } from "./components/ProtectedRoute";
import WorkspaceLayout from "./components/WorkspaceLayout";
import {
  LoginPage,
  RegisterCompanyPage,
  VerifyEmailPage,
  VerifyEmailResultPage,
} from "./pages/AuthPhase2";
import {
  CompanyPage,
  DashboardPage,
  ProfilePage,
  ResourcePage,
} from "./pages/WorkspacePages";
import SettingsPage from "./pages/SettingsPage";
export default function App() {
  return (
    <AuthProvider>
      <BrowserRouter>
        <ToastContainer position="top-right" />
        <Routes>
          <Route element={<GuestRoute />}>
            <Route path="/login" element={<LoginPage />} />
            <Route path="/sign-in" element={<Navigate to="/login" replace />} />
            <Route path="/register-company" element={<RegisterCompanyPage />} />
            <Route
              path="/sign-up"
              element={<Navigate to="/register-company" replace />}
            />
          </Route>
          <Route path="/verify-email" element={<VerifyEmailPage />} />
          <Route
            path="/verify-email/:id/:hash"
            element={<VerifyEmailResultPage />}
          />
          <Route
            path="/verify-email/result"
            element={<VerifyEmailResultPage />}
          />
          <Route element={<ProtectedRoute />}>
            <Route element={<WorkspaceLayout />}>
              <Route path="/dashboard" element={<DashboardPage />} />
              <Route path="/profile" element={<ProfilePage />} />
              <Route path="/settings" element={<SettingsPage />} />
            </Route>
          </Route>
          <Route element={<ProtectedRoute permission="company.view" />}>
            <Route element={<WorkspaceLayout />}>
              <Route path="/company" element={<CompanyPage />} />
            </Route>
          </Route>
          <Route element={<ProtectedRoute permission="users.view" />}>
            <Route element={<WorkspaceLayout />}>
              <Route path="/users" element={<ResourcePage kind="users" />} />
            </Route>
          </Route>
          <Route element={<ProtectedRoute permission="roles.view" />}>
            <Route element={<WorkspaceLayout />}>
              <Route path="/roles" element={<ResourcePage kind="roles" />} />
            </Route>
          </Route>
          <Route element={<ProtectedRoute permission="departments.view" />}>
            <Route element={<WorkspaceLayout />}>
              <Route
                path="/departments"
                element={<ResourcePage kind="departments" />}
              />
            </Route>
          </Route>
          <Route path="/" element={<Navigate to="/dashboard" replace />} />
          <Route path="*" element={<Navigate to="/dashboard" replace />} />
        </Routes>
      </BrowserRouter>
    </AuthProvider>
  );
}
