/* eslint-disable react/prop-types */
import { useProjectTeam } from "../../projects/hooks/useProjects";
import KanbanColumn from "./KanbanColumn";
import "./tasksKanban.css";

const columns = [
  ["todo", "To Do"],
  ["in_progress", "In Progress"],
  ["blocked", "Blocked"],
  ["completed", "Completed"],
];

export default function TasksKanbanBoard({
  projectId,
  selected,
  canLookup,
  filters,
  debouncing,
  renderActions,
  pages,
  onPageChange,
}) {
  const lookupId = canLookup ? projectId || selected?.value : undefined;
  const team = useProjectTeam(lookupId);
  const assigneeNames = new Map(
    (team.isSuccess ? team.data : []).map(({ id, name }) => [String(id), name]),
  );
  const filtered = Boolean(
    filters.search ||
    selected ||
    filters.priority ||
    filters.date_from ||
    filters.date_to,
  );

  return (
    <div
      className="tasks-kanban"
      role="region"
      aria-label="Tasks Kanban board"
      tabIndex={0}
    >
      {columns.map(([status, title]) => (
        <KanbanColumn
          key={status}
          status={status}
          title={title}
          projectId={projectId}
          selected={selected}
          lookupId={lookupId}
          assigneeNames={assigneeNames}
          filters={filters}
          filtered={filtered}
          debouncing={debouncing}
          renderActions={renderActions}
          page={pages[status] ?? 1}
          onPageChange={onPageChange}
        />
      ))}
    </div>
  );
}
