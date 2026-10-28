import { useNavigate, useParams } from "react-router-dom";
import { toast } from "react-toastify";
import { PageHeader, SectionCard } from "../../../components/ui/AppUI";
import { AppAlert } from "../../../components/ui/Controls";
import { apiMessage } from "../../../api/endpoints";
import ProjectForm from "../components/ProjectForm";
import { projectsApi } from "../api/projectsApi";
import { useProject, useProjectMutation } from "../hooks/useProjects";
export default function EditProjectPage() { const { id } = useParams(); const navigate = useNavigate(); const query = useProject(id); const mutation = useProjectMutation((payload) => projectsApi.update(id, payload)); if (query.isLoading) return <div role="status">Loading project…</div>; if (query.error) return <AppAlert tone="danger">{apiMessage(query.error, "Project not found.")}</AppAlert>; return <div className="phase2-page"><PageHeader title={`Edit ${query.data.name}`} description="Update supported project information."/><SectionCard title="Project information"><ProjectForm initialValues={query.data} submitLabel="Save changes" onCancel={() => navigate(`/projects/${id}`)} onSubmit={async (payload) => { await mutation.mutateAsync(payload); toast.success("Project updated."); navigate(`/projects/${id}`); }}/></SectionCard></div>; }
