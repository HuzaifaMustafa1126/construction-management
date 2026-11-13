/* eslint-disable react/prop-types */
import { useId, useState } from "react";
import { Link } from "react-router-dom";
import { EmptyState, SectionCard } from "../../../components/ui/AppUI";
import { AppAlert } from "../../../components/ui/Controls";
import { useDashboardProgress } from "../hooks/useDashboardProgress";
import "./dashboardProgress.css";

const progressValue = (value) => {
  if (typeof value !== "number" && typeof value !== "string") return null;
  if (typeof value === "string" && !value.trim()) return null;
  const number = Number(value);
  return Number.isFinite(number) && number >= 0 && number <= 100
    ? number
    : null;
};

export default function DashboardProgressOverview({ projectId }) {
  const query = useDashboardProgress(projectId);
  const id = useId();
  const [dismissedTooltip, setDismissedTooltip] = useState(null);
  const rows = query.data?.data || [];
  const total = query.data?.meta?.total;
  const denied = !query.allowed || query.error?.status === 403;
  let content;

  if (denied) {
    content = (
      <AppAlert tone="info">
        You do not have permission to view project progress.
      </AppAlert>
    );
  } else if (query.isError) {
    content = (
      <AppAlert tone="danger">
        Project progress could not be loaded. Please try again later.
      </AppAlert>
    );
  } else if (!query.isSuccess) {
    content = <p role="status">Loading project progress...</p>;
  } else if (!rows.length) {
    content = (
      <EmptyState
        title="No authorized projects"
        description="Project progress will appear when projects are available to you."
      />
    );
  } else {
    content = (
      <>
        <p className="dashboard-progress-scope" id={`${id}-scope`}>
          {projectId
            ? "Selected project."
            : `Preview: showing ${rows.length}${typeof total === "number" ? ` of ${total}` : ""} authorized projects, newest created first.`}{" "}
          Current progress snapshot; period filters do not apply.
        </p>
        <div
          className="dashboard-progress-scroll"
          role="region"
          aria-label="Project progress bar chart"
          aria-describedby={`${id}-scope`}
          tabIndex={0}
        >
          <div
            className="dashboard-progress-chart"
            style={{ "--project-count": rows.length }}
          >
            <div className="dashboard-progress-axis" aria-hidden="true">
              {[100, 75, 50, 25, 0].map((tick) => (
                <span key={tick} style={{ top: `${100 - tick}%` }}>
                  {tick}%
                </span>
              ))}
            </div>
            <div className="dashboard-progress-plot">
              <div className="dashboard-progress-grid" aria-hidden="true">
                {[100, 75, 50, 25, 0].map((tick) => (
                  <span key={tick} style={{ top: `${100 - tick}%` }} />
                ))}
              </div>
              {rows.map((project) => {
                const progress = progressValue(project.progress_percentage);
                const value =
                  progress === null ? "Unavailable" : `${progress}%`;
                return (
                  <div className="dashboard-progress-column" key={project.id}>
                    <div
                      className="dashboard-progress-target"
                      role="img"
                      tabIndex={0}
                      aria-label={`${project.name}: ${value}`}
                      style={{ "--progress": `${progress ?? 0}%` }}
                      onFocus={() => setDismissedTooltip(null)}
                      onMouseEnter={() => setDismissedTooltip(null)}
                      onKeyDown={(event) => {
                        if (event.key === "Escape")
                          setDismissedTooltip(project.id);
                      }}
                    >
                      {progress !== null && (
                        <span
                          className="dashboard-progress-bar"
                          aria-hidden="true"
                        />
                      )}
                      <span
                        className="dashboard-progress-value"
                        aria-hidden="true"
                      >
                        {progress === null ? "N/A" : value}
                      </span>
                      {dismissedTooltip !== project.id && (
                        <span
                          className="dashboard-progress-tooltip"
                          aria-hidden="true"
                        >
                          {project.name}: {value}
                        </span>
                      )}
                    </div>
                    <span
                      className="dashboard-progress-name"
                      aria-hidden="true"
                    >
                      {project.name}
                    </span>
                  </div>
                );
              })}
            </div>
          </div>
        </div>
        <details className="dashboard-progress-data">
          <summary>Project progress values and full names</summary>
          <ul>
            {rows.map((project) => {
              const progress = progressValue(project.progress_percentage);
              return (
                <li key={project.id}>
                  {project.name}:{" "}
                  <strong>
                    {progress === null
                      ? "Progress unavailable"
                      : `${progress}%`}
                  </strong>
                </li>
              );
            })}
          </ul>
        </details>
      </>
    );
  }

  return (
    <SectionCard
      title="Project Progress Overview"
      action={
        query.allowed ? (
          <Link className="dashboard-progress-link" to="/projects">
            View projects
          </Link>
        ) : null
      }
    >
      <div className="dashboard-progress">{content}</div>
    </SectionCard>
  );
}
