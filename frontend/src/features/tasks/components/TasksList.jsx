/* eslint-disable react/prop-types */
import { useEffect, useRef, useState } from "react";
import { DataTable } from "../../../components/tables/DataTable";
import {
  AppBadge,
  AppButton,
  EmptyState,
  SectionCard,
} from "../../../components/ui/AppUI";
import { AppAlert, IconButton } from "../../../components/ui/Controls";
import { useAuth } from "../../auth/context/AuthContext";
import { useProjectPhases } from "../../projects/hooks/useProjectSchedule";
import { useProjectTeam } from "../../projects/hooks/useProjects";
import { useTasks } from "../hooks/useTasks";
import TaskFilters from "./TaskFilters";
import TaskFormModal from "./TaskFormModal";
import TaskActionDialog from "./TaskActionDialog";
import TasksKanbanBoard from "./TasksKanbanBoard";
import TasksCalendar from "./TasksCalendar";
import { taskPriorities, taskStatuses } from "../taskLabels";
import { localToday } from "../utils/calendarDates";
import "./tasksList.css";

const defaults = {
  search: "",
  status: "",
  priority: "",
  date_from: "",
  date_to: "",
  page: 1,
  sort: "position",
  direction: "asc",
};

export default function TasksList({ projectId, projectName }) {
  const { user, can } = useAuth();
  if (!can("tasks.view") || (projectId && !can("projects.view"))) {
    return (
      <AppAlert tone="info">You do not have permission to view tasks.</AppAlert>
    );
  }
  if (!user?.id || !user?.company?.id) {
    return (
      <AppAlert tone="info">Company access is required to view tasks.</AppAlert>
    );
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
  const canEdit = can("projects.edit");
  const canChangeStatus = can("tasks.complete");
  const [creating, setCreating] = useState(false);
  const [taskAction, setTaskAction] = useState(null);
  const calendarActionOrigin = useRef(null);
  const [view, setView] = useState("list");
  const [{ month: calendarMonth, selectedDate }, setCalendarDate] = useState(
    () => {
      const today = localToday();
      return { month: today.slice(0, 7), selectedDate: today };
    },
  );
  const [boardPages, setBoardPages] = useState({});
  const [filters, setFilters] = useState(defaults);
  const [selected, setSelected] = useState(null);
  const [search, setSearch] = useState("");
  const term = filters.search.trim();
  useEffect(() => {
    const timer = setTimeout(() => setSearch(term), 250);
    return () => clearTimeout(timer);
  }, [term]);

  const dates = [filters.date_from, filters.date_to].filter(Boolean);
  const dateError = dates.some(
    (date) => !/^\d{4}-\d{2}-\d{2}$/.test(date) || date < "1000-01-01",
  )
    ? "Enter dates between 1000-01-01 and 9999-12-31."
    : filters.date_from &&
        filters.date_to &&
        filters.date_from > filters.date_to
      ? "The end date must be on or after the start date."
      : "";
  const debouncing = term !== search;
  const { priority, date_from, date_to, sort, direction } = filters;
  const boardFilters = {
    search,
    priority,
    date_from,
    date_to,
    sort,
    direction,
    project_id: selected?.value,
  };
  const boardKey = JSON.stringify([
    term,
    priority,
    date_from,
    date_to,
    sort,
    direction,
    selected?.value,
  ]);
  const goToPage = (page) => setFilters((current) => ({ ...current, page }));
  const isList = view === "list";
  const isCalendar = view === "calendar";
  function openTaskAction(action, row, event) {
    if (isCalendar) calendarActionOrigin.current = event.currentTarget;
    setTaskAction({ action, row });
  }
  function changeFilter(field, value) {
    setFilters((current) => ({ ...current, [field]: value, page: 1 }));
    if (field !== "status") setBoardPages({});
  }
  const rowActions =
    canEdit || canChangeStatus
      ? (row) => (
          <div className="table-actions">
            {canEdit && (
              <IconButton
                type="button"
                icon="edit"
                label={`Edit ${row.title}`}
                onClick={(event) => openTaskAction("edit", row, event)}
              />
            )}
            {canChangeStatus && (
              <IconButton
                type="button"
                icon="check"
                label={`Change status for ${row.title}`}
                onClick={(event) => openTaskAction("status", row, event)}
              />
            )}
            {canEdit && (
              <IconButton
                type="button"
                icon="delete"
                className="icon-button tasks-list-delete"
                label={`Delete ${row.title}`}
                onClick={(event) => openTaskAction("delete", row, event)}
              />
            )}
          </div>
        )
      : undefined;

  return (
    <SectionCard
      title={projectId ? "Project tasks" : "All tasks"}
      action={
        <div className="button-group">
          <div className="button-group" role="group" aria-label="Tasks view">
            <AppButton
              type="button"
              variant={isList ? "primary" : "secondary"}
              aria-pressed={isList}
              onClick={() => setView("list")}
            >
              List
            </AppButton>
            <AppButton
              type="button"
              variant={view === "kanban" ? "primary" : "secondary"}
              aria-pressed={view === "kanban"}
              onClick={() => setView("kanban")}
            >
              Kanban
            </AppButton>
            <AppButton
              type="button"
              variant={isCalendar ? "primary" : "secondary"}
              aria-pressed={isCalendar}
              onClick={() => setView("calendar")}
            >
              Calendar
            </AppButton>
          </div>
          {canCreate && (
            <AppButton type="button" onClick={() => setCreating(true)}>
              New Task
            </AppButton>
          )}
        </div>
      }
    >
      {canCreate && creating && (
        <TaskFormModal
          projectId={projectId}
          projectName={projectName}
          initialProject={selected}
          initialDueDate={isCalendar ? selectedDate : undefined}
          onClose={() => setCreating(false)}
        />
      )}
      {taskAction &&
        (taskAction.action === "status" ? canChangeStatus : canEdit) && (
          <TaskActionDialog
            key={`${taskAction.action}:${taskAction.row.id}`}
            {...taskAction}
            projectName={
              projectId
                ? projectName
                : String(taskAction.row.project_id) === selected?.value
                  ? selected.label
                  : undefined
            }
            onClose={() => setTaskAction(null)}
          />
        )}
      <div className="tasks-list">
        <TaskFilters
          filters={filters}
          onChange={changeFilter}
          canPickProject={canLookup && !projectId}
          selected={selected}
          onSelect={(project) => {
            setSelected(project);
            goToPage(1);
            setBoardPages({});
          }}
          dateError={dateError}
          showStatus={view !== "kanban"}
          showDateAndSort={!isCalendar}
        />
        {isList ? (
          <TaskTableResults
            projectId={projectId}
            projectName={projectName}
            canLookup={canLookup}
            filters={filters}
            selected={selected}
            search={search}
            debouncing={debouncing}
            dateError={dateError}
            goToPage={goToPage}
            rowActions={rowActions}
          />
        ) : isCalendar ? (
          <TasksCalendar
            month={calendarMonth}
            selectedDate={selectedDate}
            onDateChange={setCalendarDate}
            projectId={projectId}
            canLookup={canLookup}
            selectedProject={selected}
            renderActions={rowActions}
            actionOriginRef={calendarActionOrigin}
            actionOpen={Boolean(taskAction)}
            filters={{
              search,
              priority,
              status: filters.status,
              project_id: selected?.value,
            }}
            debouncing={debouncing}
          />
        ) : (
          !dateError && (
            <TasksKanbanBoard
              key={boardKey}
              projectId={projectId}
              selected={selected}
              canLookup={canLookup}
              filters={boardFilters}
              debouncing={debouncing}
              renderActions={rowActions}
              pages={boardPages}
              onPageChange={(status, page) =>
                setBoardPages((current) =>
                  current[status] === page
                    ? current
                    : { ...current, [status]: page },
                )
              }
            />
          )
        )}
      </div>
    </SectionCard>
  );
}

function TaskTableResults({
  projectId,
  projectName,
  canLookup,
  filters,
  selected,
  search,
  debouncing,
  dateError,
  goToPage,
  rowActions,
}) {
  const query = useTasks(
    projectId,
    {
      ...filters,
      search,
      project_id: selected?.value,
    },
    !dateError && !debouncing,
  );
  const lookupId =
    canLookup && query.isSuccess && !dateError && !debouncing
      ? projectId || selected?.value
      : undefined;
  const phases = useProjectPhases(lookupId);
  const team = useProjectTeam(lookupId);
  const phaseNames = new Map(
    (phases.isSuccess ? phases.data : []).map(({ id, name }) => [
      String(id),
      name,
    ]),
  );
  const assigneeNames = new Map(
    (team.isSuccess ? team.data : []).map(({ id, name }) => [String(id), name]),
  );
  const rows = query.data?.data ?? [];
  const meta = query.data?.meta;
  const waiting = debouncing || query.isPending;
  const outOfRange = meta?.current_page > meta?.last_page;
  const filtered = Boolean(
    search ||
    selected ||
    filters.status ||
    filters.priority ||
    filters.date_from ||
    filters.date_to,
  );
  const columns = [
    {
      accessorKey: "title",
      header: "Task",
      cell: ({ getValue }) => <strong>{getValue()}</strong>,
    },
    {
      id: "project",
      header: "Project",
      cell: ({ row }) => {
        const id = String(row.original.project_id);
        if (projectId && id === String(projectId) && projectName)
          return projectName;
        if (id === selected?.value) return selected.label;
        return `Project #${id}`;
      },
    },
    {
      id: "phase",
      header: "Phase",
      cell: ({ row }) => {
        const { phase_id: id, project_id: rowProject } = row.original;
        if (id == null) return "No phase";
        const name =
          String(rowProject) === String(lookupId) && phaseNames.get(String(id));
        return name || `Phase #${id}`;
      },
    },
    {
      id: "assignee",
      header: "Assigned To",
      cell: ({ row }) => {
        const { assigned_to: id, project_id: rowProject } = row.original;
        if (id == null) return "Unassigned";
        const name =
          String(rowProject) === String(lookupId) &&
          assigneeNames.get(String(id));
        return name || `User #${id}`;
      },
    },
    {
      accessorKey: "priority",
      header: "Priority",
      cell: ({ getValue }) => taskBadge(taskPriorities, getValue()),
    },
    {
      accessorKey: "status",
      header: "Status",
      cell: ({ getValue }) => taskBadge(taskStatuses, getValue()),
    },
    {
      accessorKey: "due_date",
      header: "Due Date",
      cell: ({ getValue }) => getValue() || "—",
    },
    {
      accessorKey: "progress_percentage",
      header: "Progress",
      cell: ({ row, getValue }) => (
        <span className="tasks-list-progress">
          <progress
            max={100}
            value={Number(getValue())}
            aria-label={`${row.original.title} progress`}
          />
          <span>{getValue()}%</span>
        </span>
      ),
    },
  ];
  const errorMessage =
    {
      unauthenticated: "Your session has expired.",
      forbidden: "You do not have permission to view these tasks.",
      "not-found":
        "This project could not be found or is no longer accessible.",
      network: "Could not connect to the server.",
    }[query.error?.kind] || "Tasks could not be loaded.";
  if (dateError) return null;
  if (!debouncing && query.isError)
    return (
      <AppAlert tone="danger">
        {errorMessage}{" "}
        <AppButton
          variant="secondary"
          disabled={query.isFetching}
          onClick={() => query.refetch()}
        >
          Retry
        </AppButton>
      </AppAlert>
    );
  return (
    <>
      {(waiting || query.isFetching) && (
        <p role="status">
          {waiting && !filtered && filters.page === 1
            ? "Loading tasks…"
            : "Updating task results…"}
        </p>
      )}
      {!waiting && !rows.length ? (
        <EmptyState
          title={
            outOfRange
              ? "This page is no longer available."
              : filtered
                ? "No tasks match the selected filters."
                : "No tasks to display."
          }
        />
      ) : (
        !waiting && (
          <div
            className="tasks-list-table"
            role="region"
            aria-label="Tasks table"
            tabIndex={0}
          >
            <DataTable
              columns={columns}
              data={rows}
              actions={rowActions}
              searchable={false}
              sortable={false}
              paginated={false}
            />
          </div>
        )
      )}
      {!waiting && meta && (meta.total > 0 || outOfRange) && (
        <nav className="table-pagination" aria-label="Tasks pagination">
          <span aria-live="polite">
            {meta.total} tasks ·{" "}
            {outOfRange
              ? "Page unavailable"
              : `Page ${meta.current_page} of ${meta.last_page}`}
          </span>
          <div className="button-group">
            <AppButton
              variant="secondary"
              disabled={query.isFetching || meta.current_page <= 1}
              onClick={() =>
                goToPage(Math.min(meta.current_page - 1, meta.last_page))
              }
            >
              Previous
            </AppButton>
            <AppButton
              variant="secondary"
              disabled={query.isFetching || meta.current_page >= meta.last_page}
              onClick={() => goToPage(meta.current_page + 1)}
            >
              Next
            </AppButton>
          </div>
        </nav>
      )}
    </>
  );
}

function taskBadge(options, value) {
  const [label, tone] = options[value] ?? ["Unknown", "neutral"];
  return <AppBadge tone={tone}>{label}</AppBadge>;
}
