/* eslint-disable react/prop-types */
import { AppInput, AppSelect, SearchableSelect } from "../../../components/ui/AppUI";

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
        <SearchableSelect
          label="Project"
          value={projectId}
          options={[
            { value: "", label: projectState },
            ...(projects.data || []).map((project) => ({
              value: String(project.id),
              label: project.name,
            })),
          ]}
          loading={projects.isLoading}
          requestError={projects.isError ? "Projects could not be loaded." : undefined}
          disabled={projects.isLoading || projects.isError || !projects.data?.length}
          onChange={onProjectChange}
        />
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
        <small className="dashboard-filter-scope">
          Project counts are current snapshots. Period applies to material
          consumption when a project is selected.
        </small>
      </div>
    </header>
  );
}
