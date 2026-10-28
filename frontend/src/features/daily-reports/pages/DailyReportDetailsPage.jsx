/* eslint-disable react/prop-types */
import { useState } from "react";
import { Link, useNavigate, useParams, useSearchParams } from "react-router-dom";
import { toast } from "react-toastify";
import { AppButton, AppModal, ConfirmDialog, PageHeader, SectionCard } from "../../../components/ui/AppUI";
import { AppAlert, AppSwitch, AppTextarea } from "../../../components/ui/Controls";
import { apiMessage } from "../../../api/endpoints";
import { useAuth } from "../../auth/context/AuthContext";
import { dailyReportsApi } from "../api/dailyReportsApi";
import ReportStatusBadge from "../components/ReportStatusBadge";
import { useDailyReportDetails } from "../hooks/useDailyReportDetails";
import { useDailyReportMutation } from "../hooks/useDailyReports";
import { formatDateTime, formatReportDate, humanize } from "../utils/reportFormatters";

const hasRole = (user, name) => (user?.roles || []).some((r) => (typeof r === "string" ? r : r.name) === name);
const Field = ({ label, children }) => <div><dt>{label}</dt><dd>{children || "—"}</dd></div>;
export default function DailyReportDetailsPage() {
  const { id } = useParams(); const [params] = useSearchParams(); const projectId = params.get("project"); const navigate = useNavigate(); const { can, user } = useAuth();
  const query = useDailyReportDetails(projectId, id); const mutate = useDailyReportMutation(({ action, payload }) => action(payload));
  const [deleting, setDeleting] = useState(false); const [reviewing, setReviewing] = useState(false); const [reviewAction, setReviewAction] = useState("approve"); const [notes, setNotes] = useState("");
  if (!projectId) return <AppAlert tone="danger">A project reference is required to load this report.</AppAlert>;
  if (query.isLoading) return <div role="status">Loading daily report…</div>;
  if (query.error) return <AppAlert tone="danger">{apiMessage(query.error, query.error?.status === 404 ? "Report not found or unavailable." : "Daily report could not be loaded.")}</AppAlert>;
  const r = query.data; const admin = hasRole(user, "company_admin"); const client = hasRole(user, "client"); const creator = r.creator?.id === user?.id;
  const editable = can("site_reports.update") && ["draft", "changes_required"].includes(r.status) && (admin || creator);
  const run = async (action, success, payload) => { try { await mutate.mutateAsync({ action, payload }); toast.success(success); } catch (error) { toast.error(apiMessage(error)); throw error; } };
  const submit = () => run(() => dailyReportsApi.submit(projectId, id), "Report submitted for review.");
  const review = async () => { if (reviewAction === "request_changes" && !notes.trim()) { toast.error("Review notes are required when requesting changes."); return; } await run((payload) => dailyReportsApi.review(projectId, id, payload), reviewAction === "approve" ? "Report approved." : "Changes requested.", { action: reviewAction, review_notes: notes || null }); setReviewing(false); setNotes(""); };
  const remove = async () => { await run(() => dailyReportsApi.remove(projectId, id), "Daily report deleted."); navigate(`/daily-reports?project=${projectId}`); };
  return <div className="phase2-page"><PageHeader title={r.title} description={`${r.project?.name || "Project"} · ${formatReportDate(r.report_date)}`} action={<div className="button-group"><Link className="app-button secondary" to={`/daily-reports?project=${projectId}`}>Back</Link>{editable && <Link className="app-button secondary" to={`/daily-reports/${id}/edit?project=${projectId}`}>Edit</Link>}{can("site_reports.update") && ["draft", "changes_required"].includes(r.status) && (admin || creator) && <AppButton onClick={submit} busy={mutate.isPending}>Submit</AppButton>}{can("site_reports.approve") && !client && r.status === "submitted" && !creator && <AppButton onClick={() => setReviewing(true)}>Review</AppButton>}{admin && can("site_reports.delete") && <AppButton variant="danger" onClick={() => setDeleting(true)}>Delete</AppButton>}</div>}/>
    <div className="project-stats"><section className="stat-card"><small>Status</small><strong><ReportStatusBadge status={r.status}/></strong></section><section className="stat-card"><small>Workers present</small><strong>{r.total_workers_present}</strong></section><section className="stat-card"><small>Progress</small><strong>{r.progress_percentage == null ? "Not recorded" : `${r.progress_percentage}%`}</strong></section><section className="stat-card"><small>Client visibility</small><strong>{r.is_client_visible ? "Visible" : "Internal"}</strong></section></div>
    {r.status === "changes_required" && r.review_notes && <AppAlert tone="warning">Changes requested: {r.review_notes}</AppAlert>}
    <SectionCard title="Site overview"><dl className="project-overview"><Field label="Project">{r.project?.name} ({r.project?.code})</Field><Field label="Report date">{formatReportDate(r.report_date)}</Field><Field label="Submitted by">{r.creator?.name}</Field><Field label="Weather">{r.weather_conditions}</Field></dl></SectionCard>
    <SectionCard title="Workforce">{r.workers?.length ? <dl className="report-worker-summary">{r.workers.map((worker) => <Field key={worker.worker_type} label={humanize(worker.worker_type)}>{worker.workers_present}</Field>)}</dl> : <p>No workforce categories recorded.</p>}</SectionCard>
    <SectionCard title="Completed work"><div className="report-copy">{r.work_completed}</div>{r.work_in_progress && <><h3>Work in progress</h3><div className="report-copy">{r.work_in_progress}</div></>}</SectionCard>
    <SectionCard title="Issues and delays"><dl className="project-overview"><Field label="Issues">{r.issues}</Field><Field label="Delays">{r.delays}</Field><Field label="Delay reason">{r.delay_reason}</Field><Field label="General notes">{r.general_notes}</Field></dl></SectionCard>
    <SectionCard title="Report metadata"><dl className="project-overview"><Field label="Created">{formatDateTime(r.created_at)}</Field><Field label="Last updated">{formatDateTime(r.updated_at)}</Field><Field label="Submitted">{formatDateTime(r.submitted_at)}</Field>{!client && <><Field label="Reviewed">{formatDateTime(r.reviewed_at)}</Field><Field label="Review notes">{r.review_notes}</Field></>}</dl>{admin && can("site_reports.approve") && <div className="visibility-control"><AppSwitch label="Visible to assigned clients" checked={!!r.is_client_visible} disabled={r.status !== "approved" || mutate.isPending} onChange={(e) => run((value) => dailyReportsApi.visibility(projectId, id, value), e.target.checked ? "Report is now client-visible." : "Report is now internal.", e.target.checked)}/>{r.status !== "approved" && <small>Only approved reports can be client-visible.</small>}</div>}</SectionCard>
    <ConfirmDialog open={deleting} title="Delete daily report" message="Delete this active daily report?" busy={mutate.isPending} onClose={() => setDeleting(false)} onConfirm={remove}/>
    <AppModal open={reviewing} title="Review daily report" onClose={() => setReviewing(false)}><div className="review-actions"><label className="check-control"><input type="radio" name="review" checked={reviewAction === "approve"} onChange={() => setReviewAction("approve")}/><span>Approve report</span></label><label className="check-control"><input type="radio" name="review" checked={reviewAction === "request_changes"} onChange={() => setReviewAction("request_changes")}/><span>Request changes</span></label><AppTextarea label="Review notes" value={notes} onChange={(e) => setNotes(e.target.value)} rows="4"/><div className="modal-actions"><AppButton variant="secondary" onClick={() => setReviewing(false)}>Cancel</AppButton><AppButton busy={mutate.isPending} onClick={review}>Save review</AppButton></div></div></AppModal>
  </div>;
}
