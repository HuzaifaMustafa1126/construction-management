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
const DailyReportsPage = lazy(() => import("../features/daily-reports/pages/DailyReportsPage"));
const CreateDailyReportPage = lazy(() => import("../features/daily-reports/pages/CreateDailyReportPage"));
const DailyReportDetailsPage = lazy(() => import("../features/daily-reports/pages/DailyReportDetailsPage"));
const EditDailyReportPage = lazy(() => import("../features/daily-reports/pages/EditDailyReportPage"));
const SitePhotosPage = lazy(() => import("../features/site-photos/pages/SitePhotosPage"));
const UploadSitePhotosPage = lazy(() => import("../features/site-photos/pages/UploadSitePhotosPage"));
const SitePhotoDetailsPage = lazy(() => import("../features/site-photos/pages/SitePhotoDetailsPage"));
const MaterialsPage = lazy(() => import("../features/materials/pages/MaterialsPage"));
const MaterialDetailsPage = lazy(() => import("../features/materials/pages/MaterialDetailsPage"));
const CreateMaterialPage = lazy(() => import("../features/materials/pages/MaterialFormPages").then((m) => ({ default: m.CreateMaterialPage })));
const EditMaterialPage = lazy(() => import("../features/materials/pages/MaterialFormPages").then((m) => ({ default: m.EditMaterialPage })));
const ProjectInventoryPage = lazy(() => import("../features/materials/pages/ProjectInventoryPage"));
const MaterialRequestsPage = lazy(() => import("../features/material-requests/pages/MaterialRequestsPage"));
const CreateMaterialRequestPage = lazy(() => import("../features/material-requests/pages/CreateMaterialRequestPage"));
const MaterialRequestDetailsPage = lazy(() => import("../features/material-requests/pages/MaterialRequestDetailsPage"));
const InventoryTransferPage = lazy(() => import("../features/material-requests/pages/InventoryTransferPage"));
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
        <Route element={<ProtectedRoute permission="site_reports.view" />}>
          <Route element={<WorkspaceLayout />}>
            <Route path="/daily-reports" element={<DailyReportsPage />} />
            <Route path="/daily-reports/:id" element={<DailyReportDetailsPage />} />
          </Route>
        </Route>
        <Route element={<ProtectedRoute permission="site_reports.create" />}>
          <Route element={<WorkspaceLayout />}><Route path="/daily-reports/new" element={<CreateDailyReportPage />} /></Route>
        </Route>
        <Route element={<ProtectedRoute permission="site_reports.update" />}>
          <Route element={<WorkspaceLayout />}><Route path="/daily-reports/:id/edit" element={<EditDailyReportPage />} /></Route>
        </Route>
        <Route element={<ProtectedRoute permission="photos.view" />}>
          <Route element={<WorkspaceLayout />}>
            <Route path="/site-photos" element={<SitePhotosPage />} />
            <Route path="/site-photos/:id" element={<SitePhotoDetailsPage />} />
          </Route>
        </Route>
        <Route element={<ProtectedRoute permission="photos.create" />}>
          <Route element={<WorkspaceLayout />}><Route path="/site-photos/upload" element={<UploadSitePhotosPage />} /></Route>
        </Route>
        <Route element={<ProtectedRoute permission="materials.view" />}><Route element={<WorkspaceLayout />}><Route path="/materials" element={<MaterialsPage/>}/><Route path="/materials/:id" element={<MaterialDetailsPage/>}/></Route></Route>
        <Route element={<ProtectedRoute permission="materials.create" />}><Route element={<WorkspaceLayout />}><Route path="/materials/new" element={<CreateMaterialPage/>}/></Route></Route>
        <Route element={<ProtectedRoute permission="materials.update" />}><Route element={<WorkspaceLayout />}><Route path="/materials/:id/edit" element={<EditMaterialPage/>}/></Route></Route>
        <Route element={<ProtectedRoute permission="inventory.view" />}><Route element={<WorkspaceLayout />}><Route path="/projects/:id/inventory" element={<ProjectInventoryPage/>}/></Route></Route>
        <Route element={<ProtectedRoute permission="material_requests.view" />}><Route element={<WorkspaceLayout />}><Route path="/material-requests" element={<MaterialRequestsPage/>}/><Route path="/material-requests/:id" element={<MaterialRequestDetailsPage/>}/></Route></Route>
        <Route element={<ProtectedRoute permission="material_requests.create" />}><Route element={<WorkspaceLayout />}><Route path="/material-requests/new" element={<CreateMaterialRequestPage/>}/></Route></Route>
        <Route element={<ProtectedRoute permission="inventory.transfer" />}><Route element={<WorkspaceLayout />}><Route path="/inventory/transfers/new" element={<InventoryTransferPage/>}/></Route></Route>
        <Route path="/" element={<Navigate to="/dashboard" replace />} />
        <Route path="*" element={<Navigate to="/dashboard" replace />} />
      </Routes>
    </Suspense>
  );
}
