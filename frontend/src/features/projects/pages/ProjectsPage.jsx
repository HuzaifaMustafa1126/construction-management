import { useMemo, useState } from "react";
import { Link, useNavigate } from "react-router-dom";
import { toast } from "react-toastify";
import { DataTable } from "../../../components/tables/DataTable";
import { AppButton, AppIcon, AppSelect, ConfirmDialog, PageHeader, SectionCard } from "../../../components/ui/AppUI";
import { AppAlert, FilterBar, IconButton } from "../../../components/ui/Controls";
import { useAuth } from "../../auth/context/AuthContext";
import { apiMessage } from "../../../api/endpoints";
import { projectsApi } from "../api/projectsApi";
import { useProjectMutation, useProjects } from "../hooks/useProjects";
import { PROJECT_STATUSES } from "../schemas/projectSchema";
import ProjectStatusBadge from "../components/ProjectStatusBadge";

export default function ProjectsPage() {
  const { can } = useAuth(); const navigate = useNavigate();
  const [filters, setFilters] = useState({ search: "", status: "", sort: "created_at", direction: "desc", page: 1, per_page: 15 }); const [draft, setDraft] = useState(""); const [deleting, setDeleting] = useState(null);
  const query = useProjects(filters); const remove = useProjectMutation((id) => projectsApi.remove(id)); const rows = query.data?.data || []; const meta = query.data?.meta;
  const columns = useMemo(() => [{ accessorKey: "name", header: "Project", cell: ({ row }) => <span><strong>{row.original.name}</strong><small className="table-subtitle">{row.original.code}</small></span> }, { accessorKey: "location", header: "Location", cell: ({ getValue }) => getValue() || "—" }, { accessorKey: "start_date", header: "Start date", cell: ({ getValue }) => getValue() || "—" }, { accessorKey: "expected_end_date", header: "Expected end", cell: ({ getValue }) => getValue() || "—" }, { accessorKey: "status", header: "Status", cell: ({ getValue }) => <ProjectStatusBadge status={getValue()}/> }, { accessorKey: "progress_percentage", header: "Progress", cell: ({ getValue }) => `${getValue()}%` }], []);
  const confirmDelete = async () => { try { await remove.mutateAsync(deleting.id); toast.success("Project archived."); setDeleting(null); } catch (error) { toast.error(apiMessage(error)); } };
  const actions = (project) => <div className="table-actions"><IconButton icon="view" label={`View ${project.name}`} onClick={() => navigate(`/projects/${project.id}`)}/>{can("projects.update") && <IconButton icon="edit" label={`Edit ${project.name}`} onClick={() => navigate(`/projects/${project.id}/edit`)}/>} {can("projects.delete") && <IconButton icon="delete" label={`Delete ${project.name}`} onClick={() => setDeleting(project)}/>}</div>;
  return <div className="phase2-page"><PageHeader title="Projects" description="Manage and track your construction projects." action={can("projects.create") ? <Link className="app-button primary" to="/projects/new"><AppIcon name="add"/>Add Project</Link> : null}/>
    <SectionCard title="Project portfolio" action={<AppButton variant="secondary" onClick={() => query.refetch()}>Refresh</AppButton>}>
      <form onSubmit={(e) => { e.preventDefault(); setFilters((x) => ({ ...x, search: draft, page: 1 })); }}><FilterBar><label className="table-search"><AppIcon name="search"/><span className="sr-only">Search projects</span><input value={draft} onChange={(e) => setDraft(e.target.value)} placeholder="Search name or code…"/></label><AppSelect label="Status" value={filters.status} onChange={(e) => setFilters((x) => ({ ...x, status: e.target.value, page: 1 }))}><option value="">All statuses</option>{PROJECT_STATUSES.map((x) => <option key={x} value={x}>{x.replaceAll("_", " ")}</option>)}</AppSelect><AppButton type="submit">Search</AppButton></FilterBar></form>
      {query.error && <AppAlert tone="danger">{apiMessage(query.error, "Projects could not be loaded.")}</AppAlert>}
      <DataTable data={rows} columns={columns} actions={actions} loading={query.isLoading} searchable={false} sortable={false} paginated={false} emptyTitle="No projects found"/>
      {meta && <div className="server-pagination"><span>Page {meta.current_page} of {meta.last_page} · {meta.total} projects</span><div><AppButton variant="secondary" disabled={meta.current_page <= 1} onClick={() => setFilters((x) => ({ ...x, page: x.page - 1 }))}>Previous</AppButton><AppButton variant="secondary" disabled={meta.current_page >= meta.last_page} onClick={() => setFilters((x) => ({ ...x, page: x.page + 1 }))}>Next</AppButton></div></div>}
    </SectionCard><ConfirmDialog open={!!deleting} title="Archive project" message={`Archive ${deleting?.name}?`} busy={remove.isPending} onClose={() => setDeleting(null)} onConfirm={confirmDelete}/></div>;
}
