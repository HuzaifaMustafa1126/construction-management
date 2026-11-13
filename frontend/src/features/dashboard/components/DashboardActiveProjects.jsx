/* eslint-disable react/prop-types */
import { useMemo, useState } from "react";
import { Link } from "react-router-dom";
import { DataTable } from "../../../components/tables/DataTable";
import { AppButton, SectionCard } from "../../../components/ui/AppUI";
import { AppAlert } from "../../../components/ui/Controls";
import { useAuth } from "../../auth/context/AuthContext";
import ProjectStatusBadge from "../../projects/components/ProjectStatusBadge";
import { formatReportDate } from "../../daily-reports/utils/reportFormatters";
import { useDashboardActiveProjects } from "../hooks/useDashboardActiveProjects";
import "./dashboardActiveProjects.css";

// Match the existing project budget display convention without using placeholder spending.
const budgetText = (project) => {
  if (project.budget === undefined || !project.currency) return "Restricted";
  if (project.budget === null || project.budget === "") return "Unavailable";
  try {
    return new Intl.NumberFormat(undefined, {
      style: "currency",
      currency: project.currency,
      maximumFractionDigits: 2,
    }).format(Number(project.budget));
  } catch {
    return `${project.currency} ${Number(project.budget).toFixed(2)}`;
  }
};

function ProjectProgress({ project }) {
  const raw = project.progress_percentage;
  const value =
    typeof raw === "number" || (typeof raw === "string" && raw.trim())
      ? Number(raw)
      : NaN;
  if (!Number.isFinite(value) || value < 0 || value > 100)
    return <span className="dashboard-active-muted">Unavailable</span>;
  return (
    <span className="dashboard-active-progress">
      <progress
        max={100}
        value={value}
        aria-label={`${project.name} progress`}
      />
      <span>{value}%</span>
    </span>
  );
}

export default function DashboardActiveProjects({ projectId }) {
  const { user } = useAuth();
  return (
    <ActiveProjectsPage
      key={JSON.stringify([user?.company?.id, user?.id, projectId])}
      projectId={projectId}
    />
  );
}

function ActiveProjectsPage({ projectId }) {
  const { can } = useAuth();
  const [page, setPage] = useState(1);
  const query = useDashboardActiveProjects(projectId, page);
  const showBudget = can("projects.budget.view");
  const columns = useMemo(
    () => [
      {
        id: "project",
        header: "Project",
        cell: ({ row }) => (
          <span className="dashboard-active-name">
            <strong>{row.original.name}</strong>
            {row.original.code && <small>{row.original.code}</small>}
          </span>
        ),
      },
      {
        id: "client",
        header: "Client",
        cell: ({ row }) => row.original.client?.name || "Unassigned",
      },
      {
        id: "manager",
        header: "Project manager",
        cell: ({ row }) => row.original.project_manager?.name || "Unassigned",
      },
      {
        id: "progress",
        header: "Progress",
        cell: ({ row }) => <ProjectProgress project={row.original} />,
      },
      {
        id: "expected",
        header: "Expected completion",
        cell: ({ row }) => formatReportDate(row.original.expected_end_date),
      },
      ...(showBudget
        ? [
            {
              id: "budget",
              header: "Budget",
              cell: ({ row }) => budgetText(row.original),
            },
          ]
        : []),
      {
        id: "status",
        header: "Status",
        cell: ({ row }) => <ProjectStatusBadge status={row.original.status} />,
      },
      {
        id: "view",
        header: "View",
        cell: ({ row }) => (
          <Link
            className="dashboard-active-link"
            aria-label={`View project: ${row.original.name}`}
            to={`/projects/${row.original.id}`}
          >
            View
          </Link>
        ),
      },
    ],
    [showBudget],
  );
  const denied = !query.allowed || query.error?.status === 403;
  const meta = query.data?.meta;
  const emptyTitle = query.data?.selectedInactive
    ? "The selected project is not active."
    : "No accessible active projects.";

  return (
    <SectionCard title="Active Projects">
      <div className="dashboard-active-projects">
        <p className="dashboard-active-scope">
          {projectId ? "Selected project" : "All projects"} · Current
          active-project snapshots. Period filters do not apply.
        </p>
        {denied ? (
          <AppAlert tone="info">
            You do not have permission to view these projects.
          </AppAlert>
        ) : query.isError ? (
          <AppAlert tone="danger">
            Active projects could not be loaded. Please try again later.
          </AppAlert>
        ) : (
          <>
            <div
              className="dashboard-active-scroll"
              role="region"
              aria-label="Active projects table"
              tabIndex={0}
            >
              <DataTable
                columns={columns}
                data={query.data?.data || []}
                loading={!query.isSuccess}
                emptyTitle={emptyTitle}
                searchable={false}
                sortable={false}
                paginated={false}
              />
            </div>
            {/* {query.isSuccess && !projectId && meta && meta.total > 0 && (
              <nav
                className="dashboard-active-pagination"
                aria-label="Active projects pagination"
              >
                <span aria-live="polite">
                  {meta.total} active projects · Page {meta.current_page} of{" "}
                  {meta.last_page}
                </span>
                <div className="button-group">
                  <AppButton
                    type="button"
                    variant="secondary"
                    disabled={query.isFetching || meta.current_page <= 1}
                    onClick={() =>
                      setPage((current) => Math.max(1, current - 1))
                    }
                  >
                    Previous
                  </AppButton>
                  <AppButton
                    type="button"
                    variant="secondary"
                    disabled={
                      query.isFetching || meta.current_page >= meta.last_page
                    }
                    onClick={() => setPage((current) => current + 1)}
                  >
                    Next
                  </AppButton>
                </div>
              </nav>
            )} */}
          </>
        )}
      </div>
    </SectionCard>
  );
}
