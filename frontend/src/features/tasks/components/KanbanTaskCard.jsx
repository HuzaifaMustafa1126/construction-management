/* eslint-disable react/prop-types */
import { AppBadge } from "../../../components/ui/AppUI";
import { taskPriorities } from "../taskLabels";

export default function KanbanTaskCard({
  task,
  projectId,
  selected,
  lookupId,
  assigneeNames,
  renderActions,
}) {
  const rowProjectId = String(task.project_id);
  const projectName =
    rowProjectId === selected?.value
      ? selected.label
      : `Project #${rowProjectId}`;
  const assigneeName =
    rowProjectId === String(lookupId) &&
    assigneeNames.get(String(task.assigned_to));
  const assignee =
    task.assigned_to == null
      ? "Unassigned"
      : assigneeName || `User #${task.assigned_to}`;
  const [priority, tone] = taskPriorities[task.priority] ?? [
    "Unknown",
    "neutral",
  ];

  return (
    <article className="tasks-kanban-card">
      <h4>{task.title}</h4>
      {!projectId && <p>{projectName}</p>}
      <div className="tasks-kanban-card-actions">
        <AppBadge tone={tone}>{priority}</AppBadge>
        {(!projectId || String(projectId) === rowProjectId) &&
          renderActions?.(task)}
      </div>
      <dl>
        <div>
          <dt>Assigned to</dt>
          <dd>{assignee}</dd>
        </div>
        <div>
          <dt>Due date</dt>
          <dd>
            {task.due_date ? (
              <time dateTime={task.due_date}>{task.due_date}</time>
            ) : (
              "—"
            )}
          </dd>
        </div>
      </dl>
      <span className="tasks-list-progress">
        <progress
          max={100}
          value={Number(task.progress_percentage)}
          aria-label={`${task.title} progress`}
        />
        <span>{task.progress_percentage}%</span>
      </span>
    </article>
  );
}
