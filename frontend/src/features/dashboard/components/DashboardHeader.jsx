/* eslint-disable react/prop-types */
import { AppInput, AppSelect } from "../../../components/ui/AppUI";

export default function DashboardHeader({
  name,
  projects,
  projectId,
  onProjectChange,
  period,
  onPeriodChange,
  dateFrom,
  dateTo,
  onDateFromChange,
  onDateToChange,
  dateError,
}) {
  const projectState = projects.isLoading
    ? "Loading projects…"
    : projects.isError
      ? "Projects unavailable"
      : projects.data?.length
        ? "All projects"
        : "No authorized projects";

  return (
    <header className="dashboard-heading">
      <div className="dashboard-heading-copy">
        <h1>Dashboard</h1>
        <p>
          Welcome back, {name}. Here&apos;s what&apos;s happening with your
          projects.
        </p>
      </div>
      <div className="dashboard-filters" aria-label="Dashboard filters">
        <AppSelect
          label="Project"
          value={projectId}
          disabled={
            projects.isLoading || projects.isError || !projects.data?.length
          }
          onChange={(event) => onProjectChange(event.target.value)}
        >
          <option value="">{projectState}</option>
          {(projects.data || []).map((project) => (
            <option key={project.id} value={project.id}>
              {project.name}
            </option>
          ))}
        </AppSelect>
        <AppSelect
          label="Period"
          value={period}
          onChange={(event) => onPeriodChange(event.target.value)}
        >
          <option value="month">This month</option>
          <option value="custom">Custom period</option>
        </AppSelect>
        {period === "custom" && (
          <div className="dashboard-custom-period">
            <AppInput
              label="Start date"
              type="date"
              value={dateFrom}
              max={dateTo || undefined}
              error={dateError}
              onChange={(event) => onDateFromChange(event.target.value)}
            />
            <AppInput
              label="End date"
              type="date"
              value={dateTo}
              min={dateFrom || undefined}
              onChange={(event) => onDateToChange(event.target.value)}
            />
          </div>
        )}
        {/* <small className="dashboard-filter-scope">
          Project applies to project metrics. Period applies to material
          consumption when a project is selected.
        </small> */}
      </div>
    </header>
  );
}
