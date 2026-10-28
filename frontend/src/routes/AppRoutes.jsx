import { lazy, Suspense } from "react";
import { Navigate, Route, Routes } from "react-router-dom";
import ProtectedRoute, { GuestRoute, PlatformRoute } from "./ProtectedRoute";
import WorkspaceLayout from "../layouts/WorkspaceLayout";
import PlatformLayout from "../layouts/PlatformLayout";
import {
  LoginPage,
  VerifyEmailPage,
  VerifyEmailResultPage,
} from "../features/auth/pages/AuthPhase2";
import AdministratorPasswordSetupPage from "../features/auth/pages/AdministratorPasswordSetupPage";
import { AppSkeleton } from "../components/ui/Controls";

const platformPage = (name) =>
  lazy(() =>
    import("../features/platform/pages/PlatformPages").then((module) => ({
      default: module[name],
    })),
  );
const PlatformDashboard = platformPage("PlatformDashboard");
const PlatformCompanies = platformPage("PlatformCompanies");
const PlatformCompanyCreate = platformPage("PlatformCompanyCreate");
const PlatformCompanyDetail = platformPage("PlatformCompanyDetail");
const PlatformCompanyEdit = platformPage("PlatformCompanyEdit");
const ProfilePage = lazy(() =>
  import("../features/profile/pages/ProfilePages").then((module) => ({
    default: module.ProfilePage,
  })),
);
const ProfileSecurityPage = lazy(() =>
  import("../features/profile/pages/ProfilePages").then((module) => ({
    default: module.ProfileSecurityPage,
  })),
);
const SettingsPage = lazy(
  () => import("../features/settings/pages/SettingsPage"),
);
const CompanyDashboard = lazy(
  () => import("../features/dashboard/pages/CompanyDashboard"),
);
const CompanyProfilePage = lazy(
  () => import("../features/company/pages/CompanyProfilePage"),
);
const UsersPage = lazy(() => import("../features/users/pages/UsersPage"));
const RolesPage = lazy(() => import("../features/roles/pages/RolesPage"));
const DepartmentsPage = lazy(
  () => import("../features/departments/pages/DepartmentsPage"),
);
const ProjectsPage = lazy(() => import("../features/projects/pages/ProjectsPage"));
const CreateProjectPage = lazy(() => import("../features/projects/pages/CreateProjectPage"));
const ProjectDetailsPage = lazy(() => import("../features/projects/pages/ProjectDetailsPage"));
const EditProjectPage = lazy(() => import("../features/projects/pages/EditProjectPage"));
const LoadingPage = () => (
  <div className="page-container" role="status">
    <AppSkeleton /> Loading page…
  </div>
);

export default function AppRoutes() {
  return (
    <Suspense fallback={<LoadingPage />}>
      <Routes>
        <Route element={<GuestRoute />}>
          <Route path="/login" element={<LoginPage />} />
          <Route path="/sign-in" element={<Navigate to="/login" replace />} />
        </Route>
        <Route element={<PlatformRoute />}>
          <Route element={<PlatformLayout />}>
            <Route path="/platform/dashboard" element={<PlatformDashboard />} />
            <Route path="/platform/companies" element={<PlatformCompanies />} />
            <Route
              path="/platform/companies/new"
              element={<PlatformCompanyCreate />}
            />
            <Route
              path="/platform/companies/:id"
              element={<PlatformCompanyDetail />}
            />
            <Route
              path="/platform/companies/:id/edit"
              element={<PlatformCompanyEdit />}
            />
            <Route path="/platform/profile" element={<ProfilePage />} />
            <Route
              path="/platform/profile/security"
              element={<ProfileSecurityPage />}
            />
            <Route path="/platform/settings" element={<SettingsPage />} />
          </Route>
        </Route>
        <Route path="/verify-email" element={<VerifyEmailPage />} />
        <Route
          path="/administrator/password/setup"
          element={<AdministratorPasswordSetupPage />}
        />
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
            <Route path="/dashboard" element={<CompanyDashboard />} />
            <Route path="/profile" element={<ProfilePage />} />
            <Route path="/profile/security" element={<ProfileSecurityPage />} />
            <Route path="/settings" element={<SettingsPage />} />
          </Route>
        </Route>
        <Route element={<ProtectedRoute permission="company.view" />}>
          <Route element={<WorkspaceLayout />}>
            <Route path="/company" element={<CompanyProfilePage />} />
          </Route>
        </Route>
        <Route element={<ProtectedRoute permission="users.view" />}>
          <Route element={<WorkspaceLayout />}>
            <Route path="/users" element={<UsersPage />} />
          </Route>
        </Route>
        <Route element={<ProtectedRoute permission="roles.view" />}>
          <Route element={<WorkspaceLayout />}>
            <Route path="/roles" element={<RolesPage />} />
          </Route>
        </Route>
        <Route element={<ProtectedRoute permission="departments.view" />}>
          <Route element={<WorkspaceLayout />}>
            <Route path="/departments" element={<DepartmentsPage />} />
          </Route>
        </Route>
        <Route element={<ProtectedRoute permission="projects.view" />}>
          <Route element={<WorkspaceLayout />}>
            <Route path="/projects" element={<ProjectsPage />} />
            <Route path="/projects/:id" element={<ProjectDetailsPage />} />
          </Route>
        </Route>
        <Route element={<ProtectedRoute permission="projects.create" />}>
          <Route element={<WorkspaceLayout />}>
            <Route path="/projects/new" element={<CreateProjectPage />} />
          </Route>
        </Route>
        <Route element={<ProtectedRoute permission="projects.update" />}>
          <Route element={<WorkspaceLayout />}>
            <Route path="/projects/:id/edit" element={<EditProjectPage />} />
          </Route>
        </Route>
        <Route path="/" element={<Navigate to="/dashboard" replace />} />
        <Route path="*" element={<Navigate to="/dashboard" replace />} />
      </Routes>
    </Suspense>
  );
}
