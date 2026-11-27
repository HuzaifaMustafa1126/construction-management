/* eslint-disable react/prop-types */
import { useId } from "react";
import { AppBadge, EmptyState } from "../../../components/ui/AppUI";
import { useProjectTeam } from "../../projects/hooks/useProjects";
import { taskPriorities, taskStatuses } from "../taskLabels";
import { calendarDateLabel } from "../utils/calendarDates";

export default function CalendarDayTasks({
  date,
  tasks,
  complete,
  projectId,
  selectedProject,
  canLookup,
  renderActions,
  headingRef,
}) {
  const headingId = useId();
  const lookupProject = projectId || selectedProject?.value;
  const needsNames =
    canLookup &&
    lookupProject &&
    tasks.some(
      (task) =>
        task.assigned_to != null &&
        String(task.project_id) === String(lookupProject),
    );
  const team = useProjectTeam(needsNames ? lookupProject : undefined);
  const assignees = new Map(
    (team.isSuccess ? team.data : []).map(({ id, name }) => [String(id), name]),
  );

  return (
    <section
      className="section-card tasks-calendar-day"
      aria-labelledby={headingId}
    >
      <header>
        <h3 id={headingId} ref={headingRef} tabIndex={-1}>
          {calendarDateLabel(date)}
        </h3>
      </header>
      <div role="status">
        <p>
          {tasks.length} {tasks.length === 1 ? "task" : "tasks"} loaded for this
          date.
        </p>
        {!complete && <p>Additional matching tasks may not be loaded.</p>}
      </div>
      {tasks.length === 0 ? (
        <EmptyState
          title={
            complete
              ? "No tasks due on this date."
              : "No tasks loaded for this date yet."
          }
        />
      ) : (
        <ul className="tasks-calendar-day-list">
          {tasks.map((task) => {
            const [priority, priorityTone] = taskPriorities[task.priority] ?? [
              "Unknown priority",
              "neutral",
            ];
            const [status, statusTone] = taskStatuses[task.status] ?? [
              "Unknown status",
              "neutral",
            ];
            const rowProject = String(task.project_id);
            const projectName =
              rowProject === selectedProject?.value
                ? selectedProject.label
                : `Project #${rowProject}`;
            const name =
              rowProject === String(lookupProject) &&
              assignees.get(String(task.assigned_to));
            const assignee =
              task.assigned_to == null
                ? "Unassigned"
                : name || `User #${task.assigned_to}`;
            return (
              <li key={task.id}>
                <h4>{task.title}</h4>
                <div className="button-group">
                  <AppBadge tone={priorityTone}>{priority}</AppBadge>
                  <AppBadge tone={statusTone}>{status}</AppBadge>
                </div>
                <dl>
                  {!projectId && (
                    <div>
                      <dt>Project</dt>
                      <dd>{projectName}</dd>
                    </div>
                  )}
                  <div>
                    <dt>Due date</dt>
                    <dd>
                      <time dateTime={task.due_date}>{task.due_date}</time>
                    </dd>
                  </div>
                  <div>
                    <dt>Assigned to</dt>
                    <dd>{assignee}</dd>
                  </div>
                  <div>
                    <dt>Progress</dt>
                    <dd>{task.progress_percentage}%</dd>
                  </div>
                </dl>
                {(!projectId || String(projectId) === rowProject) &&
                  renderActions?.(task)}
              </li>
            );
          })}
        </ul>
      )}
    </section>
  );
}
