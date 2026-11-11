/* eslint-disable react/prop-types */
import { AppIcon } from "../../../components/ui/AppUI";

const stateCopy = {
  denied: "Not available for your access level",
  error: "Metric could not be loaded",
  loading: "Loading metric",
  unavailable: "Data source unavailable",
};

export default function DashboardMetricCard({
  icon,
  label,
  metric,
  support,
  tone = "primary",
}) {
  const ready = metric?.state === "ready";

  return (
    <article className={`dashboard-metric-card ${tone}`}>
      <span className="dashboard-metric-icon" aria-hidden="true">
        <AppIcon name={icon} size={30} />
      </span>
      <div className="dashboard-metric-copy">
        <span className="dashboard-metric-label">{label}</span>
        {metric?.state === "loading" ? (
          <span className="dashboard-metric-skeleton" aria-label="Loading metric" />
        ) : (
          <strong className={ready ? "" : "metric-unavailable"}>
            {ready ? metric.value : "—"}
          </strong>
        )}
        <small>
          {ready
            ? metric.support || support
            : metric?.message || stateCopy[metric?.state] || stateCopy.unavailable}
        </small>
      </div>
    </article>
  );
}
