import { useMemo, useState } from "react";
import { Link } from "react-router-dom";
import { AppAlert } from "../../../components/ui/Controls";
import { SectionCard } from "../../../components/ui/AppUI";
import { useAuth, useCompany } from "../../auth/context/AuthContext";
import DashboardHeader from "../components/DashboardHeader";
import DashboardMetricCard from "../components/DashboardMetricCard";
import DashboardProgressOverview from "../components/DashboardProgressOverview";
import DashboardFinancialSummary from "../components/DashboardFinancialSummary";
import DashboardRecentSitePhotos from "../components/DashboardRecentSitePhotos";
import DashboardSiteReports from "../components/DashboardTodaySiteReports";
import DashboardMaterialStock from "../components/DashboardMaterialStock";
import DashboardActiveProjects from "../components/DashboardActiveProjects";
import { useDashboardSummary } from "../hooks/useDashboardSummary";
import "../components/dashboardLayout.css";

const personName = (user) =>
  user?.name ||
  [user?.first_name, user?.last_name].filter(Boolean).join(" ") ||
  "there";

const actions = [
  ["Manage users", "/users", "users.view"],
  ["Manage roles", "/roles", "roles.view"],
  ["Manage departments", "/departments", "departments.view"],
  ["Company profile", "/company", "company.view"],
];

const monthRange = () => {
  const now = new Date();
  const year = now.getFullYear();
  const month = String(now.getMonth() + 1).padStart(2, "0");
  const lastDay = new Date(year, now.getMonth() + 1, 0).getDate();
  return {
    from: `${year}-${month}-01`,
    to: `${year}-${month}-${String(lastDay).padStart(2, "0")}`,
  };
};

export default function CompanyDashboard() {
  const { user, can } = useAuth();
  const { companyError } = useCompany();
  const initialRange = useMemo(monthRange, []);
  const [projectId, setProjectId] = useState("");
  const [period, setPeriod] = useState("all");
  const [customFrom, setCustomFrom] = useState(initialRange.from);
  const [customTo, setCustomTo] = useState(initialRange.to);
  const dateFrom = period === "all" ? "" : period === "month" ? initialRange.from : customFrom;
  const dateTo = period === "all" ? "" : period === "month" ? initialRange.to : customTo;
  const dateError =
    dateFrom && dateTo && dateFrom > dateTo
      ? "Start date must be on or before the end date."
      : "";
  const summary = useDashboardSummary({
    allTime: period === "all",
    projectId,
    dateFrom: dateError ? "" : dateFrom,
    dateTo: dateError ? "" : dateTo,
  });
  const projects = summary.projects;

  return (
    <div className="company-dashboard">
      <DashboardHeader
        name={personName(user)}
        projects={projects}
        projectId={projectId}
        onProjectChange={setProjectId}
        period={period}
        onPeriodChange={setPeriod}
        dateFrom={customFrom}
        dateTo={customTo}
        onDateFromChange={setCustomFrom}
        onDateToChange={setCustomTo}
        dateError={dateError}
      />
      {companyError && (
        <AppAlert tone="warning">
          Some company profile details are restricted for your account.
        </AppAlert>
      )}
      {dateError && <AppAlert tone="warning">{dateError}</AppAlert>}

      <section className="dashboard-metrics" aria-label="Dashboard metrics">
        <div className="dashboard-metric-grid">
          {can("projects.view") && (
            <DashboardMetricCard
              icon="company"
              label="Total projects"
              metric={summary.totalValue}
              support={projectId ? "Selected project" : "Authorized projects"}
              tone="blue"
            />
          )}
          {can("projects.view") && (
            <DashboardMetricCard
              icon="projects"
              label="Active projects"
              metric={summary.activeValue}
              support={summary.activeSupport}
              tone="green"
            />
          )}
          {can("workers.view") && (
            <DashboardMetricCard
              icon="users"
              label="Today's labour count"
              metric={{
                state: "unavailable",
                message: "Attendance data unavailable",
              }}
              tone="orange"
            />
          )}
          {can("material_consumptions.view") && (
            <DashboardMetricCard
              icon="materials"
              label="Material consumption"
              metric={summary.consumptionValue}
              tone="purple"
            />
          )}
          {can("payments.view") && (
            <DashboardMetricCard
              icon="budget"
              label="Pending client payments"
              metric={{
                state: "unavailable",
                message: "Receivables data unavailable",
              }}
              tone="coral"
            />
          )}
          {can("projects.budget.view") && (
            <DashboardMetricCard
              icon="chart"
              label="Total project value"
              metric={{
                state: "unavailable",
                message: "Contract value unavailable",
              }}
              tone="cyan"
            />
          )}
        </div>
      </section>

      <div className="dashboard-chart-layout">
        <div className="dashboard-chart-row">
          <DashboardProgressOverview projectId={projectId} />
          <DashboardFinancialSummary />
        </div>
      </div>

      {(can("photos.view") || can("daily_reports.view")) && (
        <div className="dashboard-site-widget-layout">
          <div className="dashboard-site-widget-row">
            {can("photos.view") && <DashboardRecentSitePhotos projectId={projectId} projectName={summary.selectedProject?.name} period={period} dateFrom={dateError ? "" : dateFrom} dateTo={dateError ? "" : dateTo} />}
            {can("daily_reports.view") && <DashboardSiteReports projectName={summary.selectedProject?.name} projectId={projectId} dateFrom={dateError ? "" : dateFrom} dateTo={dateError ? "" : dateTo} period={period} />}
          </div>
        </div>
      )}

      {can("inventory.view") && <DashboardMaterialStock projectId={projectId} projectName={summary.selectedProject?.name} />}

      {can("projects.view") && <DashboardActiveProjects projectId={projectId} />}

      <SectionCard title="Quick actions">
        <div className="button-group dashboard-quick-actions">
          {actions
            .filter(([, , permission]) => can(permission))
            .map(([label, to]) => (
              <Link className="app-button secondary" to={to} key={to}>
                {label}
              </Link>
            ))}
          <Link className="app-button secondary" to="/profile">
            My profile
          </Link>
        </div>
      </SectionCard>
    </div>
  );
}
