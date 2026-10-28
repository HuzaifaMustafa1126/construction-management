import { useNavigate, useSearchParams } from "react-router-dom";
import { toast } from "react-toastify";
import { PageHeader, SectionCard } from "../../../components/ui/AppUI";
import { AppAlert } from "../../../components/ui/Controls";
import { apiMessage } from "../../../api/endpoints";
import { useProjects } from "../../projects/hooks/useProjects";
import { useAuth } from "../../auth/context/AuthContext";
import { dailyReportsApi } from "../api/dailyReportsApi";
import DailyReportForm from "../components/DailyReportForm";
import { useDailyReportMutation } from "../hooks/useDailyReports";

export default function CreateDailyReportPage() {
  const { user } = useAuth();
  const navigate = useNavigate(); const [params] = useSearchParams(); const preselected = params.get("project") || "";
  const projectsQuery = useProjects({ page: 1, per_page: 100, sort: "name", direction: "asc" });
  const mutation = useDailyReportMutation(({ projectId, payload }) => dailyReportsApi.create(projectId, payload));
  const projects = projectsQuery.data?.data || [];
  const isClient = (user?.roles || []).some((role) => (typeof role === "string" ? role : role.name) === "client");
  if (isClient) return <AppAlert tone="danger">Clients have read-only access to approved, client-visible reports.</AppAlert>;
  const save = async (projectId, payload) => { const report = await mutation.mutateAsync({ projectId, payload }); toast.success("Daily report created as a draft."); navigate(`/daily-reports/${report.id}?project=${projectId}`); };
  return <div className="phase2-page"><PageHeader title="Create Daily Report" description="Record authorized project work, workforce, issues, and delays."/><SectionCard title="Report details">{projectsQuery.error && <AppAlert tone="danger">{apiMessage(projectsQuery.error, "Authorized projects could not be loaded.")}</AppAlert>}{projectsQuery.isLoading ? <div role="status">Loading projects…</div> : <DailyReportForm projects={projects} fixedProjectId={preselected || undefined} onSubmit={save} onCancel={() => navigate(preselected ? `/daily-reports?project=${preselected}` : "/daily-reports")}/>}</SectionCard></div>;
}
