import { useMemo, useState } from "react";
import { Link, useNavigate, useSearchParams } from "react-router-dom";
import { toast } from "react-toastify";
import { DataTable } from "../../../components/tables/DataTable";
import { AppButton, AppIcon, ConfirmDialog, PageHeader, SectionCard } from "../../../components/ui/AppUI";
import { AppAlert, IconButton } from "../../../components/ui/Controls";
import { apiMessage } from "../../../api/endpoints";
import { useAuth } from "../../auth/context/AuthContext";
import { useProjects } from "../../projects/hooks/useProjects";
import { dailyReportsApi } from "../api/dailyReportsApi";
import DailyReportFilters from "../components/DailyReportFilters";
import ReportStatusBadge from "../components/ReportStatusBadge";
import { useDailyReportMutation, useDailyReports } from "../hooks/useDailyReports";
import { excerpt, formatReportDate } from "../utils/reportFormatters";

const hasRole = (user, name) => (user?.roles || []).some((r) => (typeof r === "string" ? r : r.name) === name);
export default function DailyReportsPage() {
  const { can, user } = useAuth(); const navigate = useNavigate(); const [searchParams, setSearchParams] = useSearchParams();
  const [filters, setFilters] = useState({ project_id: searchParams.get("project") || "", search: "", status: "", date_from: "", date_to: "", page: 1, per_page: 15, sort: "report_date", direction: "desc" });
  const [draft, setDraft] = useState(""); const [deleting, setDeleting] = useState(null);
  const projectQuery = useProjects({ page: 1, per_page: 100, sort: "name", direction: "asc" });
  const params = useMemo(() => { const rest = { ...filters }; delete rest.project_id; return Object.fromEntries(Object.entries(rest).filter(([, value]) => value !== "")); }, [filters]);
  const query = useDailyReports(filters.project_id, params); const remove = useDailyReportMutation(({ projectId, reportId }) => dailyReportsApi.remove(projectId, reportId));
  const projects = projectQuery.data?.data || []; const rows = query.data?.data || []; const meta = query.data?.meta;
  const isAdmin = hasRole(user, "company_admin"); const isClient = hasRole(user, "client");
  const change = (key, value) => { setFilters((x) => ({ ...x, [key]: value, page: 1 })); if (key === "project_id") setSearchParams(value ? { project: value } : {}); };
  const columns = useMemo(() => [
    { accessorKey: "report_date", header: "Report date", cell: ({ getValue }) => formatReportDate(getValue()) },
    { accessorKey: "title", header: "Report", cell: ({ row }) => <span><strong>{row.original.title}</strong><small className="table-subtitle">{row.original.creator?.name || "—"}</small></span> },
    { accessorKey: "total_workers_present", header: "Workers" },
    { accessorKey: "work_completed", header: "Completed work", cell: ({ getValue }) => excerpt(getValue()) },
    { accessorKey: "issues", header: "Issues / delays", cell: ({ row }) => excerpt(row.original.issues || row.original.delays) },
    { accessorKey: "status", header: "Status", cell: ({ getValue }) => <ReportStatusBadge status={getValue()}/> },
  ], []);
  const actions = (report) => { const path = `?project=${filters.project_id}`; const editable = can("site_reports.update") && ["draft", "changes_required"].includes(report.status) && (isAdmin || report.creator?.id === user?.id); return <div className="table-actions"><IconButton icon="view" label={`View ${report.title}`} onClick={() => navigate(`/daily-reports/${report.id}${path}`)}/>{editable && <IconButton icon="edit" label={`Edit ${report.title}`} onClick={() => navigate(`/daily-reports/${report.id}/edit${path}`)}/>} {isAdmin && can("site_reports.delete") && <IconButton icon="delete" label={`Delete ${report.title}`} onClick={() => setDeleting(report)}/>}</div>; };
  const confirmDelete = async () => { try { await remove.mutateAsync({ projectId: filters.project_id, reportId: deleting.id }); toast.success("Daily report deleted."); setDeleting(null); } catch (error) { toast.error(apiMessage(error)); } };
  return <div className="phase2-page"><PageHeader title="Daily Site Reports" description="Track daily construction activities, workforce presence, completed work, and site issues." action={<div className="button-group">{can("site_reports.create") && !isClient && <Link className="app-button primary" to={`/daily-reports/new${filters.project_id ? `?project=${filters.project_id}` : ""}`}><AppIcon name="add"/>Create Daily Report</Link>}<AppButton variant="secondary" onClick={() => query.refetch()} disabled={!filters.project_id}>Refresh</AppButton></div>}/>
    <SectionCard title="Project report history"><AppAlert>Reports are project-scoped by the backend. Select one authorized project to view its report history.</AppAlert>
      {projectQuery.error && <AppAlert tone="danger">{apiMessage(projectQuery.error, "Projects could not be loaded.")}</AppAlert>}
      <DailyReportFilters projects={projects} filters={filters} draft={draft} onDraft={setDraft} onChange={change} onSearch={(e) => { e.preventDefault(); setFilters((x) => ({ ...x, search: draft, page: 1 })); }}/>
      {!filters.project_id ? <div className="empty-state"><AppIcon name="projects" size="xl"/><strong>Select a project</strong><p>Choose a project to load its daily reports.</p></div> : <>{query.error && <AppAlert tone="danger">{apiMessage(query.error, query.error?.status === 403 ? "You do not have permission to view these reports." : "Daily reports could not be loaded.")}</AppAlert>}<DataTable data={rows} columns={columns} actions={actions} loading={query.isLoading} searchable={false} sortable={false} paginated={false} emptyTitle="No daily reports found"/>{meta && <div className="server-pagination"><span>Page {meta.current_page} of {meta.last_page} · {meta.total} reports</span><div><AppButton variant="secondary" disabled={meta.current_page <= 1} onClick={() => setFilters((x) => ({ ...x, page: x.page - 1 }))}>Previous</AppButton><AppButton variant="secondary" disabled={meta.current_page >= meta.last_page} onClick={() => setFilters((x) => ({ ...x, page: x.page + 1 }))}>Next</AppButton></div></div>}</>}
    </SectionCard><ConfirmDialog open={!!deleting} title="Delete daily report" message={`Delete ${deleting?.title}? This removes the active report from normal access.`} busy={remove.isPending} onClose={() => setDeleting(null)} onConfirm={confirmDelete}/></div>;
}
