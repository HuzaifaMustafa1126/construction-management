import { useNavigate, useParams, useSearchParams } from "react-router-dom";
import { toast } from "react-toastify";
import { PageHeader, SectionCard } from "../../../components/ui/AppUI";
import { AppAlert } from "../../../components/ui/Controls";
import { apiMessage } from "../../../api/endpoints";
import { dailyReportsApi } from "../api/dailyReportsApi";
import DailyReportForm from "../components/DailyReportForm";
import { useDailyReportDetails } from "../hooks/useDailyReportDetails";
import { useDailyReportMutation } from "../hooks/useDailyReports";

export default function EditDailyReportPage() {
  const { id } = useParams(); const [params] = useSearchParams(); const projectId = params.get("project"); const navigate = useNavigate();
  const query = useDailyReportDetails(projectId, id); const mutation = useDailyReportMutation(({ payload }) => dailyReportsApi.update(projectId, id, payload));
  if (!projectId) return <AppAlert tone="danger">A project reference is required to load this report.</AppAlert>;
  if (query.isLoading) return <div role="status">Loading daily report…</div>;
  if (query.error) return <AppAlert tone="danger">{apiMessage(query.error, query.error?.status === 403 ? "You are not allowed to edit this report." : "Daily report could not be loaded.")}</AppAlert>;
  const save = async (_projectId, payload) => { const report = await mutation.mutateAsync({ payload }); toast.success("Daily report updated."); navigate(`/daily-reports/${report.id}?project=${projectId}`); };
  return <div className="phase2-page"><PageHeader title="Edit Daily Report" description={`${query.data.project?.name || "Project"} · ${query.data.report_date}`}/><SectionCard title="Editable report fields"><DailyReportForm initialValues={query.data} projects={[query.data.project]} fixedProjectId={projectId} submitLabel="Update Report" onSubmit={save} onCancel={() => navigate(`/daily-reports/${id}?project=${projectId}`)}/></SectionCard></div>;
}
