/* eslint-disable react/prop-types */
import { useEffect, useState } from "react";
import { AppButton, AppInput, AppSelect, SearchableSelect } from "../../../components/ui/AppUI";
import { FilterBar } from "../../../components/ui/Controls";
import { useProjects } from "../../projects/hooks/useProjects";
import { taskPriorities, taskStatuses } from "../taskLabels";

export function TaskProjectPicker({ selected, onSelect, required = false, error, disabled }) {
  const [search, setSearch] = useState("");
  const [term, setTerm] = useState("");
  const [page, setPage] = useState(1);
  useEffect(() => {
    const timer = setTimeout(() => {
      setTerm(search.trim());
      setPage(1);
    }, 250);
    return () => clearTimeout(timer);
  }, [search]);

  const query = useProjects({ search: term, page, per_page: 15, sort: "name", direction: "asc" });
  const pending = search.trim() !== term || query.isFetching;
  const projects = pending || query.isError ? [] : query.data?.data ?? [];
  const meta = query.data?.meta;
  return (
    <div>
      <SearchableSelect
        label="Project"
        required={required}
        error={error}
        disabled={disabled}
        value={selected?.value ?? ""}
        selectedOption={selected}
        options={[...(required ? [] : [{ value: "", label: "All projects" }]), ...projects.map((project) => ({
          value: String(project.id), label: project.name,
        }))]}
        onChange={(value) => {
          if (!value) onSelect(null);
          else {
            const project = projects.find((entry) => String(entry.id) === value);
            if (project) onSelect({ value, label: project.name });
          }
        }}
        onSearchChange={setSearch}
        filterOptions={false}
        maxLength={255}
        loading={pending}
        requestError={query.isError ? "Project options could not be loaded." : undefined}
        menuMessage={term && meta?.last_page > 1 ? "Refine your search to narrow the project results." : undefined}
      />
      {!term && (page > 1 || meta?.last_page > 1) && (
        <div className="button-group" role="group" aria-label="Project options pagination">
          <AppButton type="button" variant="secondary" disabled={disabled || pending || page <= 1} onClick={() => setPage(page - 1)}>Previous projects</AppButton>
          <AppButton type="button" variant="secondary" disabled={disabled || pending || !meta || page >= meta.last_page} onClick={() => setPage(page + 1)}>Next projects</AppButton>
        </div>
      )}
    </div>
  );
}

export default function TaskFilters({ filters, onChange, canPickProject, selected, onSelect, dateError, showStatus = true }) {
  return (
    <FilterBar layout="grid">
      <AppInput label="Search tasks" type="search" value={filters.search} maxLength={255}
        onChange={(event) => onChange("search", event.target.value)} />
      {canPickProject && <TaskProjectPicker selected={selected} onSelect={onSelect} />}
      {showStatus && <AppSelect label="Status" value={filters.status} onChange={(event) => onChange("status", event.target.value)}>
        <option value="">All statuses</option>
        {Object.entries(taskStatuses).map(([value, [label]]) => <option key={value} value={value}>{label}</option>)}
      </AppSelect>}
      <AppSelect label="Priority" value={filters.priority} onChange={(event) => onChange("priority", event.target.value)}>
        <option value="">All priorities</option>
        {Object.entries(taskPriorities).map(([value, [label]]) => <option key={value} value={value}>{label}</option>)}
      </AppSelect>
      <AppInput label="Due date from" type="date" min="1000-01-01" max={filters.date_to || "9999-12-31"}
        value={filters.date_from} onChange={(event) => onChange("date_from", event.target.value)} />
      <AppInput label="Due date to" type="date" min={filters.date_from || "1000-01-01"} max="9999-12-31"
        value={filters.date_to} error={dateError} onChange={(event) => onChange("date_to", event.target.value)} />
      <AppSelect label="Sort by" value={filters.sort} onChange={(event) => onChange("sort", event.target.value)}>
        <option value="position">Position</option>
        <option value="due_date">Due date</option>
        <option value="start_date">Start date</option>
        <option value="created_at">Created date</option>
        <option value="title">Title</option>
      </AppSelect>
      <AppSelect label="Sort direction" value={filters.direction} onChange={(event) => onChange("direction", event.target.value)}>
        <option value="asc">Ascending</option>
        <option value="desc">Descending</option>
      </AppSelect>
    </FilterBar>
  );
}
