/* eslint-disable react/prop-types */
import { useMemo, useState } from "react";
import { Link, useNavigate } from "react-router-dom";
import { toast } from "react-toastify";
import { DataTable } from "../../../components/tables/DataTable";
import {
  AppButton,
  AppIcon,
  AppInput,
  SearchableSelect,
  ConfirmDialog,
  PageHeader,
  SectionCard,
} from "../../../components/ui/AppUI";
import {
  AppAlert,
  FilterBar,
  IconButton,
} from "../../../components/ui/Controls";
import { useAuth } from "../../auth/context/AuthContext";
import { apiMessage } from "../../../api/endpoints";
import { projectsApi } from "../api/projectsApi";
import { useProjectMutation, useProjects } from "../hooks/useProjects";
import { PROJECT_STATUSES } from "../schemas/projectSchema";
import ProjectStatusBadge from "../components/ProjectStatusBadge";

const money = (value, currency) => {
  if (value === undefined || !currency) return "Restricted";
  try {
    return new Intl.NumberFormat(undefined, {
      style: "currency",
      currency,
      maximumFractionDigits: 2,
    }).format(Number(value || 0));
  } catch {
    return `${currency} ${Number(value || 0).toFixed(2)}`;
  }
};

export default function ProjectsPage() {
  const { can } = useAuth();
  const navigate = useNavigate();
  const [filters, setFilters] = useState({
    search: "",
    status: "",
    project_manager_id: "",
    sort: "created_at",
    direction: "desc",
    page: 1,
    per_page: 15,
  });
  const [draft, setDraft] = useState("");
  const [view, setView] = useState("cards");
  const [archiveMode, setArchiveMode] = useState(false);
  const [deleting, setDeleting] = useState(null);
  const [restoring, setRestoring] = useState(null);
  const activeQuery = useProjects(filters);
  const archivedQuery = useProjects(filters, {
    archived: true,
    enabled: archiveMode && can("projects.restore"),
  });
  const query = archiveMode ? archivedQuery : activeQuery;
  const mutation = useProjectMutation(({ action, id }) =>
    action === "restore" ? projectsApi.restore(id) : projectsApi.remove(id),
  );
  const rows = query.data?.data || [];
  const meta = query.data?.meta;
  const statusOptions = useMemo(
    () => [
      { value: "", label: "All statuses" },
      ...PROJECT_STATUSES.map((status) => ({
        value: status,
        label: status.replaceAll("_", " "),
      })),
    ],
    [],
  );
  const managerOptions = useMemo(
    () => [
      { value: "", label: "All managers" },
      ...(meta?.project_managers || []).map((manager) => ({
        value: String(manager.id),
        label: manager.name,
      })),
    ],
    [meta?.project_managers],
  );
  const columns = useMemo(
    () => [
      {
        accessorKey: "name",
        header: "Project",
        cell: ({ row }) => (
          <span>
            <strong>{row.original.name}</strong>
            <small className="table-subtitle">{row.original.code}</small>
          </span>
        ),
      },
      {
        id: "client",
        header: "Client",
        cell: ({ row }) => row.original.client?.name || "—",
      },
      {
        id: "manager",
        header: "Project manager",
        cell: ({ row }) => row.original.project_manager?.name || "Unassigned",
      },
      {
        accessorKey: "expected_end_date",
        header: "Expected completion",
        cell: ({ getValue }) => getValue() || "—",
      },
      {
        accessorKey: "status",
        header: "Status",
        cell: ({ getValue }) => <ProjectStatusBadge status={getValue()} />,
      },
      {
        accessorKey: "progress_percentage",
        header: "Progress",
        cell: ({ getValue }) => `${getValue()}%`,
      },
    ],
    [],
  );
  const confirmArchive = async () => {
    try {
      await mutation.mutateAsync({ action: "archive", id: deleting.id });
      toast.success("Project archived.");
      setDeleting(null);
    } catch (error) {
      toast.error(apiMessage(error));
    }
  };
  const confirmRestore = async () => {
    try {
      await mutation.mutateAsync({ action: "restore", id: restoring.id });
      toast.success("Project restored.");
      setRestoring(null);
    } catch (error) {
      toast.error(apiMessage(error));
    }
  };
  const actions = (project) => (
    <div className="table-actions">
      {archiveMode ? (
        can("projects.restore") && (
          <IconButton
            icon="back"
            label={`Restore ${project.name}`}
            onClick={() => setRestoring(project)}
          />
        )
      ) : (
        <>
          <IconButton
            icon="view"
            label={`View ${project.name}`}
            onClick={() => navigate(`/projects/${project.id}`)}
          />
          {can("projects.edit") && (
            <IconButton
              icon="edit"
              label={`Edit ${project.name}`}
              onClick={() => navigate(`/projects/${project.id}/edit`)}
            />
          )}{" "}
          {can("projects.delete") && (
            <IconButton
              icon="delete"
              label={`Archive ${project.name}`}
              onClick={() => setDeleting(project)}
            />
          )}
        </>
      )}
    </div>
  );

  return (
    <div className="phase2-page">
      <PageHeader
        title={archiveMode ? "Archived projects" : "Projects"}
        description={
          archiveMode
            ? "Restore archived projects without losing their history."
            : "Manage and track your construction projects."
        }
        action={
          <div className="button-group">
            {can("projects.restore") && (
              <AppButton
                variant="secondary"
                onClick={() => setArchiveMode((current) => !current)}
              >
                {archiveMode ? "Active projects" : "Archived projects"}
              </AppButton>
            )}
            {!archiveMode && can("projects.create") && (
              <Link className="app-button primary" to="/projects/new">
                <AppIcon name="add" />
                Add Project
              </Link>
            )}
          </div>
        }
      />
      <SectionCard
        title="Project portfolio"
        action={
          <div className="button-group">
            <AppButton
              variant={view === "cards" ? "primary" : "secondary"}
              onClick={() => setView("cards")}
            >
              Cards
            </AppButton>
            <AppButton
              variant={view === "list" ? "primary" : "secondary"}
              onClick={() => setView("list")}
            >
              List
            </AppButton>
            <AppButton variant="secondary" onClick={() => query.refetch()}>
              Refresh
            </AppButton>
          </div>
        }
      >
        <form
          onSubmit={(event) => {
            event.preventDefault();
            setFilters((current) => ({ ...current, search: draft, page: 1 }));
          }}
        >
          <FilterBar layout="grid">
            <AppInput
              id="project-search"
              label="Search projects"
              type="search"
              value={draft}
              onChange={(event) => setDraft(event.target.value)}
              placeholder="Search name or code…"
            />
            <SearchableSelect
              label="Status"
              value={filters.status}
              options={statusOptions}
              onChange={(status) =>
                setFilters((current) => ({ ...current, status, page: 1 }))
              }
            />
            <SearchableSelect
              label="Project manager"
              value={filters.project_manager_id}
              options={managerOptions}
              loading={query.isLoading}
              requestError={
                query.error
                  ? "Manager options could not be loaded. Use Refresh to retry."
                  : undefined
              }
              onChange={(project_manager_id) =>
                setFilters((current) => ({
                  ...current,
                  project_manager_id,
                  page: 1,
                }))
              }
            />
            <AppButton type="submit">Search</AppButton>
          </FilterBar>
        </form>
        {query.error && (
          <AppAlert tone="danger">
            {apiMessage(query.error, "Projects could not be loaded.")}
          </AppAlert>
        )}
        {view === "list" ? (
          <DataTable
            data={rows}
            columns={columns}
            actions={actions}
            loading={query.isLoading}
            searchable={false}
            sortable={false}
            paginated={false}
            emptyTitle={
              archiveMode ? "No archived projects" : "No projects found"
            }
          />
        ) : (
          <ProjectCards
            rows={rows}
            loading={query.isLoading}
            archiveMode={archiveMode}
            can={can}
            onView={(project) => navigate(`/projects/${project.id}`)}
            onEdit={(project) => navigate(`/projects/${project.id}/edit`)}
            onArchive={setDeleting}
            onRestore={setRestoring}
          />
        )}
        {meta && (
          <div className="server-pagination">
            <span>
              Page {meta.current_page} of {meta.last_page} · {meta.total}{" "}
              projects
            </span>
            <div>
              <AppButton
                variant="secondary"
                disabled={meta.current_page <= 1}
                onClick={() =>
                  setFilters((current) => ({
                    ...current,
                    page: current.page - 1,
                  }))
                }
              >
                Previous
              </AppButton>
              <AppButton
                variant="secondary"
                disabled={meta.current_page >= meta.last_page}
                onClick={() =>
                  setFilters((current) => ({
                    ...current,
                    page: current.page + 1,
                  }))
                }
              >
                Next
              </AppButton>
            </div>
          </div>
        )}
      </SectionCard>
      <ConfirmDialog
        open={!!deleting}
        title="Archive project"
        message={`Archive ${deleting?.name}? Project history and dependent records will be preserved.`}
        busy={mutation.isPending}
        onClose={() => setDeleting(null)}
        onConfirm={confirmArchive}
      />
      <ConfirmDialog
        open={!!restoring}
        title="Restore project"
        message={`Restore ${restoring?.name} to the active project portfolio?`}
        busy={mutation.isPending}
        onClose={() => setRestoring(null)}
        onConfirm={confirmRestore}
      />
    </div>
  );
}

function ProjectCards({
  rows,
  loading,
  archiveMode,
  can,
  onView,
  onEdit,
  onArchive,
  onRestore,
}) {
  if (loading)
    return (
      <div className="project-card-grid" role="status">
        <article className="project-card">Loading projects…</article>
      </div>
    );
  if (!rows.length)
    return (
      <div className="empty-state">
        <AppIcon name="empty" size="xl" />
        <strong>
          {archiveMode ? "No archived projects" : "No projects found"}
        </strong>
      </div>
    );
  return (
    <div className="project-card-grid">
      {rows.map((project) => (
        <article className="project-card" key={project.id}>
          <header>
            <div>
              <h3>{project.name}</h3>
              <small>{project.code}</small>
            </div>
            <ProjectStatusBadge status={project.status} />
          </header>
          <dl>
            <div>
              <dt>Client</dt>
              <dd>{project.client?.name || "Not assigned"}</dd>
            </div>
            <div>
              <dt>Project manager</dt>
              <dd>{project.project_manager?.name || "Unassigned"}</dd>
            </div>
            <div>
              <dt>Progress</dt>
              <dd>{project.progress_percentage}%</dd>
            </div>
            <div>
              <dt>Expected completion</dt>
              <dd>{project.expected_end_date || "Not set"}</dd>
            </div>
            <div>
              <dt>Estimated budget</dt>
              <dd>{money(project.budget, project.currency)}</dd>
            </div>
            <div>
              <dt>Spent</dt>
              <dd>{money(project.spent, project.currency)}</dd>
            </div>
          </dl>
          <footer>
            {archiveMode ? (
              can("projects.restore") && (
                <AppButton
                  variant="secondary"
                  aria-label={`Restore ${project.name}`}
                  onClick={() => onRestore(project)}
                >
                  Restore
                </AppButton>
              )
            ) : (
              <>
                <AppButton
                  variant="secondary"
                  aria-label={`View ${project.name}`}
                  onClick={() => onView(project)}
                >
                  View
                </AppButton>
                {can("projects.edit") && (
                  <AppButton
                    variant="secondary"
                    aria-label={`Edit ${project.name}`}
                    onClick={() => onEdit(project)}
                  >
                    Edit
                  </AppButton>
                )}
                {can("projects.delete") && (
                  <AppButton
                    variant="secondary"
                    aria-label={`Delete ${project.name}`}
                    onClick={() => onArchive(project)}
                  >
                    Archive
                  </AppButton>
                )}
              </>
            )}
          </footer>
        </article>
      ))}
    </div>
  );
}
