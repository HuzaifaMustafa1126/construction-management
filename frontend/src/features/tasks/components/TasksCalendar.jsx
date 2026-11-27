/* eslint-disable react/prop-types */
import { useLayoutEffect, useRef, useState } from "react";
import { AppBadge, AppButton, EmptyState } from "../../../components/ui/AppUI";
import { AppAlert } from "../../../components/ui/Controls";
import {
  summarizeCalendarPages,
  useTaskCalendar,
} from "../hooks/useTaskCalendar";
import { taskPriorities, taskStatuses } from "../taskLabels";
import {
  calendarMonth,
  defaultCalendarDate,
  localToday,
  shiftDate,
  shiftMonth,
  weekdays,
} from "../utils/calendarDates";
import CalendarDayTasks from "./CalendarDayTasks";
import "./tasksCalendar.css";

const errors = {
  unauthenticated: "Your session has expired.",
  forbidden: "You do not have permission to view these tasks.",
  "not-found": "This project could not be found or is no longer accessible.",
  network: "Could not connect to the server.",
};

const arrowOffsets = {
  ArrowLeft: -1,
  ArrowRight: 1,
  ArrowUp: -7,
  ArrowDown: 7,
};
const supportedDate = (date) =>
  date.length === 10 && date >= "1000-01-01" && date <= "9999-12-31";

export default function TasksCalendar({
  month,
  selectedDate,
  onDateChange,
  projectId,
  selectedProject,
  canLookup,
  filters,
  debouncing,
  renderActions,
  actionOriginRef,
  actionOpen,
}) {
  const [focusedDate, setFocusedDate] = useState(selectedDate);
  const gridRef = useRef(null);
  const focusRequested = useRef(false);
  const dayHeadingRef = useRef(null);
  const today = localToday();
  const { from, to, label, weeks } = calendarMonth(month);
  const query = useTaskCalendar(projectId, from, to, filters, !debouncing);
  const accessError = [401, 403, 404].includes(query.error?.status);
  const pages = debouncing || accessError ? undefined : query.data?.pages;
  const summary = pages ? summarizeCalendarPages(pages) : null;
  const complete = Boolean(
    summary?.complete && !query.isFetching && !query.isStale && !query.isError,
  );
  const byDate = new Map();
  for (const task of summary?.tasks ?? []) {
    if (!task.due_date || task.due_date < from || task.due_date > to) continue;
    if (!byDate.has(task.due_date)) byDate.set(task.due_date, []);
    byDate.get(task.due_date).push(task);
  }
  const filtered = Boolean(
    filters.search || filters.project_id || filters.status || filters.priority,
  );
  const retryNext = query.isFetchNextPageError;
  const showGrid = !query.isError || Boolean(summary);

  useLayoutEffect(() => {
    if (!focusRequested.current || !showGrid) return;
    const button = gridRef.current?.querySelector(
      `[data-calendar-date="${focusedDate}"]`,
    );
    if (button) {
      focusRequested.current = false;
      button.focus();
    }
  }, [focusedDate, month, showGrid]);

  useLayoutEffect(() => {
    const origin = actionOriginRef.current;
    if (actionOpen || !origin || document.activeElement !== document.body)
      return;
    if (origin.isConnected) {
      origin.focus();
    } else {
      const fallback =
        dayHeadingRef.current ??
        gridRef.current?.querySelector(
          `[data-calendar-date="${selectedDate}"]`,
        );
      fallback?.focus();
      if (fallback) actionOriginRef.current = null;
    }
  }, [actionOpen, actionOriginRef, selectedDate, summary]);

  function changeMonth(nextMonth) {
    const nextDate = defaultCalendarDate(nextMonth, today);
    focusRequested.current = false;
    actionOriginRef.current = null;
    setFocusedDate(nextDate);
    onDateChange({ month: nextMonth, selectedDate: nextDate });
  }

  function selectDate(date) {
    const nextMonth = date.slice(0, 7);
    focusRequested.current = nextMonth !== month;
    actionOriginRef.current = null;
    setFocusedDate(date);
    onDateChange({ month: nextMonth, selectedDate: date });
  }

  function moveFocus(event, date) {
    const offset = arrowOffsets[event.key];
    if (!offset) return;
    event.preventDefault();
    const nextDate = shiftDate(date, offset);
    if (!supportedDate(nextDate)) return;
    const nextMonth = nextDate.slice(0, 7);
    focusRequested.current = true;
    setFocusedDate(nextDate);
    if (nextMonth !== month) {
      onDateChange({
        month: nextMonth,
        selectedDate: defaultCalendarDate(nextMonth, today),
      });
    }
  }

  return (
    <section className="tasks-calendar" aria-label="Tasks calendar">
      <header className="tasks-calendar-toolbar">
        <h3 aria-live="polite">{label}</h3>
        <div
          className="button-group"
          role="group"
          aria-label="Calendar month navigation"
        >
          <AppButton
            variant="secondary"
            type="button"
            disabled={month === "1000-01"}
            onClick={() => changeMonth(shiftMonth(month, -1))}
          >
            Previous month
          </AppButton>
          <AppButton
            variant="secondary"
            type="button"
            onClick={() => changeMonth(today.slice(0, 7))}
          >
            Today
          </AppButton>
          <AppButton
            variant="secondary"
            type="button"
            disabled={month === "9999-12"}
            onClick={() => changeMonth(shiftMonth(month, 1))}
          >
            Next month
          </AppButton>
        </div>
      </header>
      {(debouncing || query.isPending) && (
        <p role="status">Loading calendar tasks…</p>
      )}
      {!debouncing && query.isError && (
        <AppAlert tone="danger">
          {errors[query.error?.kind] ||
            (retryNext
              ? "More calendar tasks could not be loaded."
              : "Calendar tasks could not be loaded.")}{" "}
          <AppButton
            variant="secondary"
            type="button"
            disabled={query.isFetching}
            onClick={() =>
              retryNext
                ? query.fetchNextPage({ cancelRefetch: false })
                : query.refetch()
            }
          >
            Retry
          </AppButton>
        </AppAlert>
      )}
      {summary && (
        <div className="tasks-calendar-summary" role="status">
          <p>
            {summary.tasks.length} of {summary.total} matching tasks loaded
          </p>
          {query.isFetching && !query.isFetchingNextPage ? (
            <p>
              Refreshing calendar results. Day counts show previously loaded
              tasks.
            </p>
          ) : complete ? (
            <p>All matching tasks are loaded.</p>
          ) : summary.capped ? (
            <p>
              Displaying the first {summary.tasks.length} matching tasks. Narrow
              your filters to see a smaller result set.
            </p>
          ) : (
            <p>Results are partial. Day counts include loaded tasks only.</p>
          )}
        </div>
      )}
      {complete && summary.total === 0 && (
        <EmptyState
          title={
            filtered
              ? "No tasks match these filters this month."
              : "No tasks are due this month."
          }
        />
      )}
      {showGrid && (
        <div className="tasks-calendar-content">
          <div className="tasks-calendar-grid" ref={gridRef}>
            <table aria-label={`Tasks due in ${label}`}>
              <thead>
                <tr>
                  {weekdays.map((weekday, index) => (
                    <th key={index} scope="col">
                      {weekday}
                    </th>
                  ))}
                </tr>
              </thead>
              <tbody>
                {weeks.map((week) => (
                  <tr key={week[0].value}>
                    {week.map((day) => (
                      <CalendarCell
                        key={day.value}
                        day={day}
                        today={today}
                        selected={day.value === selectedDate}
                        focused={day.value === focusedDate}
                        onFocus={setFocusedDate}
                        onSelect={selectDate}
                        onMove={moveFocus}
                        tasks={day.inMonth ? (byDate.get(day.value) ?? []) : []}
                      />
                    ))}
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          {summary && (
            <CalendarDayTasks
              date={selectedDate}
              tasks={byDate.get(selectedDate) ?? []}
              complete={complete}
              projectId={projectId}
              selectedProject={selectedProject}
              canLookup={canLookup}
              renderActions={renderActions}
              headingRef={dayHeadingRef}
            />
          )}
        </div>
      )}
      {summary && !query.isError && query.hasNextPage && (
        <AppButton
          variant="secondary"
          type="button"
          busy={query.isFetchingNextPage}
          disabled={query.isFetching}
          onClick={() => query.fetchNextPage({ cancelRefetch: false })}
        >
          Load more
        </AppButton>
      )}
      {summary &&
        !complete &&
        !summary.capped &&
        !query.hasNextPage &&
        !query.isError && (
          <AppButton
            variant="secondary"
            type="button"
            busy={query.isFetching}
            onClick={() => query.refetch()}
          >
            Refresh results
          </AppButton>
        )}
    </section>
  );
}

function CalendarCell({
  day,
  today,
  tasks,
  selected,
  focused,
  onFocus,
  onSelect,
  onMove,
}) {
  const unit = tasks.length === 1 ? "task" : "tasks";
  const taskCountLabel = tasks.length ? `, ${tasks.length} ${unit} loaded` : "";
  return (
    <td
      className={day.inMonth ? undefined : "tasks-calendar-adjacent"}
      data-has-tasks={tasks.length > 0 || undefined}
    >
      <button
        className="tasks-calendar-date"
        type="button"
        data-calendar-date={day.value}
        aria-label={day.label + taskCountLabel}
        aria-current={day.value === today ? "date" : undefined}
        aria-pressed={selected}
        tabIndex={focused ? 0 : -1}
        disabled={!supportedDate(day.value)}
        onFocus={() => onFocus(day.value)}
        onClick={() => onSelect(day.value)}
        onKeyDown={(event) => onMove(event, day.value)}
      >
        <time dateTime={day.value}>{day.day}</time>
        {tasks.length > 0 && (
          <span className="tasks-calendar-count" aria-hidden="true">
            {tasks.length}
            <span className="tasks-calendar-count-label"> loaded {unit}</span>
          </span>
        )}
      </button>
      {tasks.length > 0 && (
        <ul className="tasks-calendar-indicators">
          {tasks.slice(0, 2).map((task) => {
            const [status, statusTone] = taskStatuses[task.status] ?? [
              "Unknown status",
              "neutral",
            ];
            const [priority, priorityTone] = taskPriorities[task.priority] ?? [
              "Unknown priority",
              "neutral",
            ];
            return (
              <li key={task.id}>
                <span title={task.title}>{task.title}</span>
                <div>
                  <AppBadge tone={priorityTone}>{priority}</AppBadge>
                  <AppBadge tone={statusTone}>{status}</AppBadge>
                </div>
              </li>
            );
          })}
          {tasks.length > 2 && <li>+{tasks.length - 2} more loaded</li>}
        </ul>
      )}
    </td>
  );
}
