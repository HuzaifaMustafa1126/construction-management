/* eslint-disable react/prop-types */
import { useEffect, useId, useRef } from "react";
import { AppButton, EmptyState } from "../../../components/ui/AppUI";
import { AppAlert } from "../../../components/ui/Controls";
import { useTasks } from "../hooks/useTasks";
import KanbanTaskCard from "./KanbanTaskCard";

const errors = {
  unauthenticated: "Your session has expired.",
  forbidden: "You do not have permission to view these tasks.",
  "not-found": "This project could not be found or is no longer accessible.",
  network: "Could not connect to the server.",
};

export default function KanbanColumn({
  status,
  title,
  projectId,
  selected,
  lookupId,
  assigneeNames,
  filters,
  filtered,
  debouncing,
  renderActions,
  page,
  onPageChange,
}) {
  const headingId = useId();
  const headingRef = useRef(null);
  const query = useTasks(projectId, { ...filters, status, page }, !debouncing);
  const meta = !debouncing && query.isSuccess ? query.data.meta : null;
  const lastPage = meta?.last_page;
  const outOfRange = page > lastPage;
  // An invalidated page can still report success while its cached metadata is visible.
  const confirmedOutOfRange =
    outOfRange &&
    query.fetchStatus === "idle" &&
    !query.isStale &&
    !query.isPlaceholderData;
  useEffect(() => {
    if (confirmedOutOfRange) onPageChange(status, lastPage);
  }, [lastPage, confirmedOutOfRange, onPageChange, status]);

  function goToPage(event, nextPage) {
    if (event.detail === 0) headingRef.current?.focus();
    onPageChange(status, nextPage);
  }

  return (
    <section className="tasks-kanban-column" aria-labelledby={headingId}>
      <header className="tasks-kanban-heading">
        <h3 id={headingId} ref={headingRef} tabIndex={-1}>
          {title}
        </h3>
        {meta && (
          <span>
            {meta.total} {meta.total === 1 ? "task" : "tasks"}
          </span>
        )}
      </header>
      {debouncing || query.isPending || outOfRange ? (
        <p role="status">Loading tasks…</p>
      ) : query.isError ? (
        <AppAlert tone="danger">
          {errors[query.error?.kind] || "Tasks could not be loaded."}{" "}
          <AppButton
            type="button"
            variant="secondary"
            disabled={query.isFetching}
            onClick={() => query.refetch()}
          >
            Retry
          </AppButton>
        </AppAlert>
      ) : query.data.data.length === 0 ? (
        <EmptyState
          title={
            filtered
              ? "No tasks match the selected filters."
              : "No tasks in this status."
          }
        />
      ) : (
        <ul className="tasks-kanban-cards">
          {query.data.data.map((task) => (
            <li key={task.id}>
              <KanbanTaskCard
                task={task}
                projectId={projectId}
                selected={selected}
                lookupId={lookupId}
                assigneeNames={assigneeNames}
                renderActions={renderActions}
              />
            </li>
          ))}
        </ul>
      )}
      {meta && !outOfRange && (
        <nav
          className="tasks-kanban-pagination"
          aria-label={`${title} pagination`}
        >
          <span aria-live="polite">
            Page {meta.current_page} of {lastPage}
          </span>
          <div className="button-group">
            <AppButton
              type="button"
              variant="secondary"
              disabled={query.isFetching || page <= 1}
              onClick={(event) => goToPage(event, page - 1)}
            >
              Previous
            </AppButton>
            <AppButton
              type="button"
              variant="secondary"
              disabled={query.isFetching || page >= lastPage}
              onClick={(event) => goToPage(event, page + 1)}
            >
              Next
            </AppButton>
          </div>
        </nav>
      )}
    </section>
  );
}
