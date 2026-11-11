import { useMemo, useState } from "react";
import { Link } from "react-router-dom";
import { AppAlert, AppSkeleton } from "../../../components/ui/Controls";
import { AppBadge, EmptyState, SectionCard } from "../../../components/ui/AppUI";
import { useAuth, useCompany } from "../../auth/context/AuthContext";
import DashboardHeader from "../components/DashboardHeader";
import DashboardMetricCard from "../components/DashboardMetricCard";
import { useDashboardSummary } from "../hooks/useDashboardSummary";

const personName = (user) =>
  user?.name ||
  [user?.first_name, user?.last_name].filter(Boolean).join(" ") ||
  "there";

const roleNames = (user) =>
  (user?.roles || [])
    .map((role) => (typeof role === "string" ? role : role?.name))
    .filter(Boolean);

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
  const [period, setPeriod] = useState("month");
  const [customFrom, setCustomFrom] = useState(initialRange.from);
  const [customTo, setCustomTo] = useState(initialRange.to);
  const dateFrom = period === "month" ? initialRange.from : customFrom;
  const dateTo = period === "month" ? initialRange.to : customTo;
  const dateError =
    dateFrom && dateTo && dateFrom > dateTo
      ? "Start date must be on or before the end date."
      : "";
  const summary = useDashboardSummary({
    projectId,
    dateFrom: dateError ? "" : dateFrom,
    dateTo: dateError ? "" : dateTo,
  });
  const projects = summary.projects;
  const rows = projects.data?.slice(0, 6) || [];
  const roles = roleNames(user);

  return (
    <>
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

      <section className="dashboard-metric-grid" aria-label="Dashboard metrics">
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
      </section>

      {can("projects.view") && (
        <SectionCard
          title={
            roles.includes("company_admin") ? "Recent projects" : "Your projects"
          }
        >
          {projects.isLoading ? (
            <div className="dashboard-project-grid">
              <AppSkeleton />
              <AppSkeleton />
            </div>
          ) : projects.isError ? (
            <AppAlert tone="danger">
              Projects could not be loaded. Please try again.
            </AppAlert>
          ) : rows.length ? (
            <div className="dashboard-project-grid">
              {rows.map((project) => (
                <article className="profile-item" key={project.id}>
                  <div className="button-group">
                    <strong>{project.name}</strong>
                    <AppBadge
                      tone={project.status === "active" ? "success" : "neutral"}
                    >
                      {project.status}
                    </AppBadge>
                  </div>
                  <small>{project.code}</small>
                  <p>Progress: {project.progress_percentage ?? 0}%</p>
                </article>
              ))}
            </div>
          ) : (
            <EmptyState
              title="No authorized projects"
              description="Projects assigned or shared with you will appear here."
            />
          )}
        </SectionCard>
      )}

      <SectionCard title="Quick actions">
        <div className="button-group">
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
    </>
  );
}
