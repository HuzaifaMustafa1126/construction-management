/* eslint-disable react/prop-types */
import { AppButton, AppInput, AppSelect, SearchableSelect } from "../../../components/ui/AppUI";
import { FilterBar } from "../../../components/ui/Controls";
import { REPORT_STATUSES } from "../schemas/dailyReportSchema";
import { humanize } from "../utils/reportFormatters";
import { useReportProjectOptions } from "../hooks/useReportProjectOptions";

export default function DailyReportFilters({ filters, draft, onDraft, onChange, onSearch, dateError, selectedProject }) {
  const lookup = useReportProjectOptions(filters.project_id);
  const rows = lookup.pending || lookup.query.isError ? [] : lookup.query.data?.data || [];
  const selected = lookup.selected.data || selectedProject;
  const options = [{ value: "", label: "All projects" }, ...rows.map((project) => ({ value: String(project.id), label: project.code ? `${project.name} (${project.code})` : project.name }))];
  return <form onSubmit={onSearch}>
    <FilterBar layout="grid">
      <SearchableSelect
        label="Project" value={filters.project_id} options={options}
        selectedOption={filters.project_id ? { value: filters.project_id, label: selected?.name || "Selected project" } : undefined}
        onChange={(value) => onChange("project_id", value)}
        onSearchChange={lookup.setSearch} filterOptions={false} maxLength={255}
        loading={lookup.enabled && (lookup.pending || lookup.query.isLoading)}
        description={!lookup.enabled ? "Project search is unavailable for your access." : lookup.query.isError ? "Project search could not be loaded. Current selection is retained." : undefined}
        menuMessage={lookup.enabled && lookup.query.data?.meta?.total > rows.length ? "More projects are available. Type to refine your search." : undefined}
      />
      <AppInput label="Search title" value={draft} onChange={(event) => onDraft(event.target.value)} placeholder="Report title" maxLength={255} />
      <AppSelect label="Status" value={filters.status} onChange={(event) => onChange("status", event.target.value)}>
        <option value="">All statuses</option>{REPORT_STATUSES.map((status) => <option key={status} value={status}>{humanize(status)}</option>)}
      </AppSelect>
      <AppSelect label="Period" value={filters.period} onChange={(event) => onChange("period", event.target.value)}>
        <option value="all">All time</option><option value="month">This month</option><option value="custom">Custom period</option>
      </AppSelect>
      {filters.period === "custom" && <div className="daily-report-custom-range">
        <AppInput label="Start date" type="date" value={filters.date_from} max={filters.date_to || undefined} error={dateError} onChange={(event) => onChange("date_from", event.target.value)} />
        <AppInput label="End date" type="date" value={filters.date_to} min={filters.date_from || undefined} onChange={(event) => onChange("date_to", event.target.value)} />
      </div>}
      <AppButton type="submit" disabled={!!dateError}>Search</AppButton>
    </FilterBar>
  </form>;
}
