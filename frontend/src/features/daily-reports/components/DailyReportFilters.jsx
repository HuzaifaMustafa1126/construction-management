/* eslint-disable react/prop-types */
import { AppButton, AppInput, AppSelect } from "../../../components/ui/AppUI";
import { FilterBar } from "../../../components/ui/Controls";
import { REPORT_STATUSES } from "../schemas/dailyReportSchema";
import { humanize } from "../utils/reportFormatters";

export default function DailyReportFilters({ projects, filters, draft, onDraft, onChange, onSearch }) {
  return <form onSubmit={onSearch}><FilterBar>
    <AppSelect label="Project" value={filters.project_id} onChange={(e) => onChange("project_id", e.target.value)}><option value="">Select a project</option>{projects.map((p) => <option value={p.id} key={p.id}>{p.name} ({p.code})</option>)}</AppSelect>
    <AppInput label="Search title" value={draft} onChange={(e) => onDraft(e.target.value)} placeholder="Report title"/>
    <AppSelect label="Status" value={filters.status} onChange={(e) => onChange("status", e.target.value)}><option value="">All statuses</option>{REPORT_STATUSES.map((x) => <option key={x} value={x}>{humanize(x)}</option>)}</AppSelect>
    <AppInput label="From" type="date" value={filters.date_from} onChange={(e) => onChange("date_from", e.target.value)}/>
    <AppInput label="To" type="date" value={filters.date_to} onChange={(e) => onChange("date_to", e.target.value)}/>
    <AppButton type="submit">Search</AppButton>
  </FilterBar></form>;
}
