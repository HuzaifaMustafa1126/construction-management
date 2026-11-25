/* eslint-disable react/prop-types */
import { useProjectTeam } from "../../projects/hooks/useProjects";
import KanbanColumn from "./KanbanColumn";
import "./tasksKanban.css";

const statuses = ["todo", "in_progress", "blocked", "completed"];

export default function TasksKanbanBoard({ projectId, selected, canLookup, filters, debouncing, onChangeStatus }) {
  const lookupId = canLookup ? projectId || selected?.value : undefined;
  const team = useProjectTeam(lookupId);
  const assigneeNames = new Map((team.isSuccess ? team.data : []).map(({ id, name }) => [String(id), name]));
  const filtered = Boolean(filters.search || selected || filters.priority || filters.date_from || filters.date_to);

  return (
    <div className="tasks-kanban" role="region" aria-label="Tasks Kanban board" tabIndex={0}>
      {statuses.map((status) => (
        <KanbanColumn key={status} status={status} projectId={projectId} selected={selected}
          lookupId={lookupId} assigneeNames={assigneeNames} filters={filters}
          filtered={filtered} debouncing={debouncing} onChangeStatus={onChangeStatus} />
      ))}
    </div>
  );
}
