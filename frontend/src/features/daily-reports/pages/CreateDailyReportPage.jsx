import { useNavigate, useSearchParams } from "react-router-dom";
import { toast } from "react-toastify";
import { PageHeader } from "../../../components/ui/AppUI";
import { AppAlert } from "../../../components/ui/Controls";
import { useAuth } from "../../auth/context/AuthContext";
import { dailyReportsApi } from "../api/dailyReportsApi";
import DailyReportForm from "../components/DailyReportForm";
import { useDailyReportMutation } from "../hooks/useDailyReports";
import "./createDailyReport.css";

export default function CreateDailyReportPage() {
  const { user } = useAuth();
  const navigate = useNavigate();
  const [params] = useSearchParams();
  const preselected = params.get("project") || "";
  const mutation = useDailyReportMutation(({ projectId, payload }) => dailyReportsApi.create(projectId, payload));
  const isClient = (user?.roles || []).some((role) => (typeof role === "string" ? role : role.name) === "client");
  if (isClient) return <AppAlert tone="danger">Clients have read-only access to approved, client-visible reports.</AppAlert>;
  const save = async (projectId, payload) => {
    const report = await mutation.mutateAsync({ projectId, payload });
    toast.success("Daily report created as a draft.");
    navigate(`/daily-reports/${report.id}?project=${projectId}`);
  };
  return <div className="phase2-page create-daily-report-page">
    <PageHeader title="Create Daily Report" description="Record daily work, workforce, issues, and delays." />
    <DailyReportForm sectioned projects={[]} fixedProjectId={preselected || undefined} onSubmit={save} onCancel={() => navigate(preselected ? `/daily-reports?project=${preselected}` : "/daily-reports")} />
  </div>;
}
