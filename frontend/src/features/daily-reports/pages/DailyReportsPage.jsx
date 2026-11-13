import { useEffect, useMemo, useRef, useState } from "react";
import { Link, useNavigate, useSearchParams } from "react-router-dom";
import { toast } from "react-toastify";
import { DataTable } from "../../../components/tables/DataTable";
import { AppButton, AppIcon, ConfirmDialog, PageHeader, SectionCard } from "../../../components/ui/AppUI";
import { AppAlert, IconButton } from "../../../components/ui/Controls";
import { apiMessage } from "../../../api/endpoints";
import { useAuth } from "../../auth/context/AuthContext";
import { dailyReportsApi } from "../api/dailyReportsApi";
import DailyReportFilters from "../components/DailyReportFilters";
import ReportStatusBadge from "../components/ReportStatusBadge";
import { useDailyReportMutation, useDailyReportCollection } from "../hooks/useDailyReports";
import { formatReportDate } from "../utils/reportFormatters";
import { buildReportListSearch, currentReportMonth, readReportListFilters, reportRangeError } from "../utils/reportListFilters";
import "./dailyReportsList.css";

const hasRole = (user, name) => (user?.roles || []).some((role) => (typeof role === "string" ? role : role.name) === name);

export default function DailyReportsPage() {
  const { can, user } = useAuth();
  const navigate = useNavigate();
  const [searchParams, setSearchParams] = useSearchParams();
  const [filters, setFilters] = useState(() => readReportListFilters(searchParams));
  const [draft, setDraft] = useState(filters.search);
  const [deleting, setDeleting] = useState(null);
  const knownUrl = useRef(searchParams.toString());
  useEffect(() => {
    const url = searchParams.toString();
    if (url !== knownUrl.current) {
      const restored = readReportListFilters(searchParams);
      knownUrl.current = url;
      setFilters(restored);
      setDraft(restored.search);
    }
  }, [searchParams]);
  const commit = (next) => {
    const params = buildReportListSearch(next);
    knownUrl.current = params.toString();
    setFilters(next);
    setSearchParams(params);
  };
  const change = (key, value) => {
    const next = { ...filters, [key]: value, page: 1 };
    if (key === "period") {
      if (value === "all") Object.assign(next, { date_from: "", date_to: "" });
      else if (value === "month" || !filters.date_from || !filters.date_to) Object.assign(next, currentReportMonth());
    }
    commit(next);
  };
  const dateError = reportRangeError(filters);
  const allowed = can("daily_reports.view");
  const params = useMemo(() => ({
    ...(filters.project_id ? { project_id: filters.project_id } : {}),
    ...(filters.search ? { search: filters.search } : {}),
    ...(filters.status ? { status: filters.status } : {}),
    ...(filters.period !== "all" ? { date_from: filters.date_from, date_to: filters.date_to } : {}),
    page: filters.page, per_page: filters.per_page,
  }), [filters]);
  const query = useDailyReportCollection(params, allowed && !!user?.id && !!user?.company?.id && !dateError);
  const remove = useDailyReportMutation(({ projectId, reportId }) => dailyReportsApi.remove(projectId, reportId));
  const rows = query.data?.data || [];
  const meta = query.data?.meta;
  const isAdmin = hasRole(user, "company_admin");
  const isClient = hasRole(user, "client");
  const denied = !allowed || query.error?.status === 403;
  const pageUnavailable = query.isSuccess && meta?.current_page > meta?.last_page;
  const selectedProject = rows.find((row) => String(row.project.id) === filters.project_id)?.project;
  const columns = useMemo(() => [
    { id: "report", header: "Report", cell: ({ row }) => <div className="daily-report-title"><strong>{row.original.title}</strong>{row.original.summary && <small className="daily-report-summary">{row.original.summary}</small>}</div> },
    { id: "project", header: "Project", cell: ({ row }) => row.original.project.name },
    { accessorKey: "report_date", header: "Report date", cell: ({ getValue }) => formatReportDate(getValue()) },
    { id: "author", header: "Author / supervisor", cell: ({ row }) => row.original.creator?.name || "Unavailable" },
    { accessorKey: "total_workers_present", header: "Workers", cell: ({ getValue }) => getValue() ?? "Unavailable" },
    { accessorKey: "status", header: "Status", cell: ({ getValue }) => <ReportStatusBadge status={getValue()} /> },
  ], []);
  const actions = (report) => {
    const path = `?project=${encodeURIComponent(report.project.id)}`;
    const editable = can("site_reports.update") && ["draft", "changes_required"].includes(report.status) && (isAdmin || report.creator?.id === user?.id);
    return <div className="table-actions">
      <IconButton icon="view" label={`View ${report.title}`} onClick={() => navigate(`/daily-reports/${report.id}${path}`)} />
      {editable && <IconButton icon="edit" label={`Edit ${report.title}`} onClick={() => navigate(`/daily-reports/${report.id}/edit${path}`)} />}
      {isAdmin && can("site_reports.delete") && <IconButton icon="delete" label={`Delete ${report.title}`} onClick={() => setDeleting(report)} />}
    </div>;
  };
  const confirmDelete = async () => {
    try {
      await remove.mutateAsync({ projectId: deleting.project.id, reportId: deleting.id });
      toast.success("Daily report deleted."); setDeleting(null);
    } catch (error) { toast.error(apiMessage(error)); }
  };
  return <div className="phase2-page daily-reports-list">
    <PageHeader title="Daily Site Reports" description="Browse authorized site reports by project, status and report date." action={<div className="button-group">
      {can("daily_reports.create") && !isClient && <Link className="app-button primary" to={`/daily-reports/new${filters.project_id ? `?project=${filters.project_id}` : ""}`}><AppIcon name="add" />Create Report</Link>}
      <AppButton variant="secondary" disabled={!allowed || !!dateError || query.isFetching} onClick={() => query.refetch()}>Refresh</AppButton>
    </div>} />
    <SectionCard title="Site reports">
      <DailyReportFilters filters={filters} selectedProject={selectedProject} draft={draft} onDraft={setDraft} onChange={change} dateError={dateError} onSearch={(event) => { event.preventDefault(); if (!dateError) commit({ ...filters, search: draft.trim(), page: 1 }); }} />
      {dateError ? <AppAlert tone="warning">{dateError}</AppAlert> : denied ? <AppAlert tone="info">You do not have permission to view these reports.</AppAlert> : query.isError ? <AppAlert tone="danger">{apiMessage(query.error, "Daily reports could not be loaded.")}</AppAlert> : <>
        <div className="daily-reports-scroll" role="region" aria-label="Daily site reports table" tabIndex={0}>
          <DataTable data={rows} columns={columns} actions={actions} loading={!query.isSuccess} searchable={false} sortable={false} paginated={false} emptyTitle={pageUnavailable ? "This page is no longer available. Use Previous to return to available reports." : "No reports match the selected filters."} />
        </div>
        {query.isSuccess && meta && <nav className="daily-reports-pagination" aria-label="Daily reports pagination">
          <span aria-live="polite">{meta.total} reports · {pageUnavailable ? "Page no longer available" : `Page ${meta.current_page} of ${meta.last_page}`}</span>
          <div className="button-group">
            <AppButton variant="secondary" disabled={query.isFetching || meta.current_page <= 1} onClick={() => commit({ ...filters, page: Math.max(1, Math.min(meta.current_page - 1, meta.last_page)) })}>Previous</AppButton>
            <AppButton variant="secondary" disabled={query.isFetching || meta.current_page >= meta.last_page} onClick={() => commit({ ...filters, page: meta.current_page + 1 })}>Next</AppButton>
          </div>
        </nav>}
      </>}
    </SectionCard>
    <ConfirmDialog open={!!deleting} title="Delete daily report" message={`Delete ${deleting?.title}? This removes the active report from normal access.`} busy={remove.isPending} onClose={() => setDeleting(null)} onConfirm={confirmDelete} />
  </div>;
}
