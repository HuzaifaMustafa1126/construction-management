/* eslint-disable react/prop-types */
import { Controller, useWatch } from "react-hook-form";
import { SearchableSelect } from "../../../components/ui/AppUI";
import { useReportProjectOptions } from "../hooks/useReportProjectOptions";

export default function ReportProjectField({ control, fixedProjectId, error }) {
  const value = useWatch({ control, name: "project_id" });
  const lookup = useReportProjectOptions(value);
  const rows = lookup.pending || lookup.query.isError ? [] : lookup.query.data?.data || [];
  const selected = lookup.selected.data || rows.find((project) => String(project.id) === value);
  return <Controller control={control} name="project_id" render={({ field }) => <SearchableSelect
    label="Project" required name={field.name} ref={field.ref} value={field.value}
    onChange={field.onChange} onBlur={field.onBlur} disabled={!!fixedProjectId}
    options={[{ value: "", label: "Select a project", disabled: true }, ...rows.map((project) => ({ value: String(project.id), label: project.code ? `${project.name} (${project.code})` : project.name }))]}
    selectedOption={value ? { value, label: selected?.name || "Selected project" } : undefined}
    loading={!fixedProjectId && lookup.enabled && (lookup.pending || lookup.query.isLoading)}
    requestError={lookup.query.isError && !fixedProjectId ? "Projects could not be loaded. Please try again later." : undefined}
    onSearchChange={lookup.setSearch} filterOptions={false} maxLength={255}
    description={fixedProjectId ? lookup.selected.isError ? "Selected project details could not be loaded. The project reference is retained." : lookup.selected.isLoading ? "Loading selected project details..." : "Project is fixed by the current report context." : !lookup.enabled ? "Project lookup is unavailable for your access." : undefined}
    menuMessage={lookup.query.data?.meta?.total > rows.length ? "More projects are available. Type to refine your search." : undefined}
    error={error}
  />} />;
}
