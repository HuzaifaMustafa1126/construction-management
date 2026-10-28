import { useMemo, useState } from "react";
import { Link, useNavigate } from "react-router-dom";
import { DataTable } from "../../../components/tables/DataTable";
import { AppBadge, AppButton, AppSelect, PageHeader, SectionCard } from "../../../components/ui/AppUI";
import { AppAlert, FilterBar, IconButton } from "../../../components/ui/Controls";
import { apiMessage } from "../../../api/endpoints";
import { useAuth } from "../../auth/context/AuthContext";
import { useProjects } from "../../projects/hooks/useProjects";
import { useMaterialRequests } from "../hooks/useMaterialRequests";

const tone = (status) => ({ approved: "success", issued: "success", rejected: "danger", cancelled: "neutral", partially_issued: "warning", submitted: "info" })[status] || "neutral";

export default function MaterialRequestsPage() {
  const { can } = useAuth();
  const navigate = useNavigate();
  const [filters, setFilters] = useState({ project_id: "", status: "", page: 1, per_page: 15 });
  const query = useMaterialRequests(filters);
  const projects = useProjects({ page: 1, per_page: 100, sort: "name", direction: "asc" });
  const columns = useMemo(() => [
    { accessorKey: "request_number", header: "Request" },
    { accessorKey: "project", header: "Project", cell: ({ getValue }) => getValue()?.name || "—" },
    { accessorKey: "required_date", header: "Required date" },
    { accessorKey: "items_count", header: "Items" },
    { accessorKey: "status", header: "Status", cell: ({ getValue }) => <AppBadge tone={tone(getValue())}>{getValue().replaceAll("_", " ")}</AppBadge> },
    { accessorKey: "requester", header: "Requested by", cell: ({ getValue }) => getValue()?.name || "—" },
  ], []);
  return <div className="phase2-page">
    <PageHeader title="Material Requests" description="Request, approve, issue, and return project materials without changing stock before issue." action={<div className="button-group">{can("inventory.transfer") && <Link className="app-button secondary" to="/inventory/transfers/new">Transfer Stock</Link>}{can("material_requests.create") && <Link className="app-button primary" to="/material-requests/new">New Request</Link>}</div>}/>
    <SectionCard title="Requests" action={<AppButton variant="secondary" onClick={() => query.refetch()}>Refresh</AppButton>}>
      <FilterBar><AppSelect label="Project" value={filters.project_id} onChange={(event) => setFilters((value) => ({ ...value, project_id: event.target.value, page: 1 }))}><option value="">All projects</option>{(projects.data?.data || []).map((project) => <option key={project.id} value={project.id}>{project.name}</option>)}</AppSelect><AppSelect label="Status" value={filters.status} onChange={(event) => setFilters((value) => ({ ...value, status: event.target.value, page: 1 }))}><option value="">All statuses</option>{["draft", "submitted", "approved", "partially_issued", "issued", "rejected", "cancelled"].map((status) => <option key={status} value={status}>{status.replaceAll("_", " ")}</option>)}</AppSelect></FilterBar>
      {query.error && <AppAlert tone="danger">{apiMessage(query.error)}</AppAlert>}
      <DataTable data={query.data?.data || []} columns={columns} actions={(request) => <IconButton icon="view" label={`View ${request.request_number}`} onClick={() => navigate(`/material-requests/${request.id}`)}/>} loading={query.isLoading} searchable={false} sortable={false} paginated={false} emptyTitle="No material requests found"/>
      {query.data?.meta && <div className="server-pagination"><span>Page {query.data.meta.current_page} of {query.data.meta.last_page}</span><div><AppButton variant="secondary" disabled={query.data.meta.current_page <= 1} onClick={() => setFilters((value) => ({ ...value, page: value.page - 1 }))}>Previous</AppButton><AppButton variant="secondary" disabled={query.data.meta.current_page >= query.data.meta.last_page} onClick={() => setFilters((value) => ({ ...value, page: value.page + 1 }))}>Next</AppButton></div></div>}
    </SectionCard>
  </div>;
}
