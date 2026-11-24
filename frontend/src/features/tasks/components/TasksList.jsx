/* eslint-disable react/prop-types */
import { useEffect, useState } from "react";
import { DataTable } from "../../../components/tables/DataTable";
import { AppBadge, AppButton, EmptyState, SectionCard } from "../../../components/ui/AppUI";
import { AppAlert } from "../../../components/ui/Controls";
import { useAuth } from "../../auth/context/AuthContext";
import { useProjectPhases } from "../../projects/hooks/useProjectSchedule";
import { useProjectTeam } from "../../projects/hooks/useProjects";
import { useTasks } from "../hooks/useTasks";
import TaskFilters from "./TaskFilters";
import TaskFormModal from "./TaskFormModal";
import { taskPriorities, taskStatuses } from "../taskLabels";
import "./tasksList.css";

const defaults = {
  search: "", status: "", priority: "", date_from: "", date_to: "",
  page: 1, sort: "position", direction: "asc",
};

export default function TasksList({ projectId, projectName }) {
  const { user, can } = useAuth();
  if (!can("tasks.view") || (projectId && !can("projects.view"))) {
    return <AppAlert tone="info">You do not have permission to view tasks.</AppAlert>;
  }
  if (!user?.id || !user?.company?.id) {
    return <AppAlert tone="info">Company access is required to view tasks.</AppAlert>;
  }
  const canLookup = can("projects.view");
  return (
    <TaskCollection
      key={JSON.stringify([user.company.id, user.id, projectId, canLookup])}
      projectId={projectId}
      projectName={projectName}
      canLookup={canLookup}
    />
  );
}

function TaskCollection({ projectId, projectName, canLookup }) {
  const { can } = useAuth();
  const canCreate = can("tasks.create") && canLookup;
  const [creating, setCreating] = useState(false);
  const [filters, setFilters] = useState(defaults);
  const [selected, setSelected] = useState(null);
  const [search, setSearch] = useState("");
  const term = filters.search.trim();
  useEffect(() => {
    const timer = setTimeout(() => setSearch(term), 250);
    return () => clearTimeout(timer);
  }, [term]);

  const dates = [filters.date_from, filters.date_to].filter(Boolean);
  const dateError = dates.some((date) => !/^\d{4}-\d{2}-\d{2}$/.test(date) || date < "1000-01-01")
    ? "Enter dates between 1000-01-01 and 9999-12-31."
    : filters.date_from && filters.date_to && filters.date_from > filters.date_to
      ? "The end date must be on or after the start date."
      : "";
  const debouncing = term !== search;
  const query = useTasks(projectId, {
    ...filters, search, project_id: selected?.value,
  }, !dateError && !debouncing);
  const lookupId = canLookup && query.isSuccess && !dateError && !debouncing
    ? projectId || selected?.value
    : undefined;
  const phases = useProjectPhases(lookupId);
  const team = useProjectTeam(lookupId);
  const phaseNames = new Map((phases.isSuccess ? phases.data : []).map(({ id, name }) => [String(id), name]));
  const assigneeNames = new Map((team.isSuccess ? team.data : []).map(({ id, name }) => [String(id), name]));
  const rows = query.data?.data ?? [];
  const meta = query.data?.meta;
  const waiting = debouncing || query.isPending;
  const outOfRange = meta?.current_page > meta?.last_page;
  const filtered = Boolean(search || selected || filters.status || filters.priority || dates.length);
  const columns = [
    { accessorKey: "title", header: "Task", cell: ({ getValue }) => <strong>{getValue()}</strong> },
    { id: "project", header: "Project", cell: ({ row }) => {
      const id = String(row.original.project_id);
      if (projectId && id === String(projectId) && projectName) return projectName;
      if (id === selected?.value) return selected.label;
      return `Project #${id}`;
    } },
    { id: "phase", header: "Phase", cell: ({ row }) => {
      const { phase_id: id, project_id: rowProject } = row.original;
      if (id == null) return "No phase";
      const name = String(rowProject) === String(lookupId) && phaseNames.get(String(id));
      return name || `Phase #${id}`;
    } },
    { id: "assignee", header: "Assigned To", cell: ({ row }) => {
      const { assigned_to: id, project_id: rowProject } = row.original;
      if (id == null) return "Unassigned";
      const name = String(rowProject) === String(lookupId) && assigneeNames.get(String(id));
      return name || `User #${id}`;
    } },
    { accessorKey: "priority", header: "Priority", cell: ({ getValue }) => taskBadge(taskPriorities, getValue()) },
    { accessorKey: "status", header: "Status", cell: ({ getValue }) => taskBadge(taskStatuses, getValue()) },
    { accessorKey: "due_date", header: "Due Date", cell: ({ getValue }) => getValue() || "—" },
    { accessorKey: "progress_percentage", header: "Progress", cell: ({ row, getValue }) => (
      <span className="tasks-list-progress">
        <progress max={100} value={Number(getValue())} aria-label={`${row.original.title} progress`} />
        <span>{getValue()}%</span>
      </span>
    ) },
  ];
  const goToPage = (page) => setFilters((current) => ({ ...current, page }));
  const errorMessage = {
    unauthenticated: "Your session has expired.",
    forbidden: "You do not have permission to view these tasks.",
    "not-found": "This project could not be found or is no longer accessible.",
    network: "Could not connect to the server.",
  }[query.error?.kind] || "Tasks could not be loaded.";

  return (
    <SectionCard title={projectId ? "Project tasks" : "All tasks"}
      action={canCreate && <AppButton type="button" onClick={() => setCreating(true)}>New Task</AppButton>}
    >
      {canCreate && creating && (
        <TaskFormModal projectId={projectId} projectName={projectName} initialProject={selected}
          onClose={() => setCreating(false)} />
      )}
      <div className="tasks-list">
        <TaskFilters
          filters={filters}
          onChange={(field, value) => setFilters((current) => ({ ...current, [field]: value, page: 1 }))}
          canPickProject={canLookup && !projectId}
          selected={selected}
          onSelect={(project) => { setSelected(project); goToPage(1); }}
          dateError={dateError}
        />
        {dateError ? null : !debouncing && query.isError ? (
          <AppAlert tone="danger">
            {errorMessage} <AppButton variant="secondary" disabled={query.isFetching} onClick={() => query.refetch()}>Retry</AppButton>
          </AppAlert>
        ) : (
          <>
            {(waiting || query.isFetching) && (
              <p role="status">{waiting && !filtered && filters.page === 1 ? "Loading tasks…" : "Updating task results…"}</p>
            )}
            {!waiting && !rows.length ? (
              <EmptyState title={outOfRange ? "This page is no longer available." : filtered ? "No tasks match the selected filters." : "No tasks to display."} />
            ) : !waiting && (
              <div className="tasks-list-table" role="region" aria-label="Tasks table" tabIndex={0}>
                <DataTable columns={columns} data={rows} searchable={false} sortable={false} paginated={false} />
              </div>
            )}
            {!waiting && meta && (meta.total > 0 || outOfRange) && (
              <nav className="table-pagination" aria-label="Tasks pagination">
                <span aria-live="polite">{meta.total} tasks · {outOfRange ? "Page unavailable" : `Page ${meta.current_page} of ${meta.last_page}`}</span>
                <div className="button-group">
                  <AppButton variant="secondary" disabled={query.isFetching || meta.current_page <= 1}
                    onClick={() => goToPage(Math.min(meta.current_page - 1, meta.last_page))}>Previous</AppButton>
                  <AppButton variant="secondary" disabled={query.isFetching || meta.current_page >= meta.last_page}
                    onClick={() => goToPage(meta.current_page + 1)}>Next</AppButton>
                </div>
              </nav>
            )}
          </>
        )}
      </div>
    </SectionCard>
  );
}

function taskBadge(options, value) {
  const [label, tone] = options[value] ?? ["Unknown", "neutral"];
  return <AppBadge tone={tone}>{label}</AppBadge>;
}
