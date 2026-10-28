import { useNavigate } from "react-router-dom";
import { toast } from "react-toastify";
import { PageHeader, SectionCard } from "../../../components/ui/AppUI";
import ProjectForm from "../components/ProjectForm";
import { projectsApi } from "../api/projectsApi";
import { useProjectMutation } from "../hooks/useProjects";
export default function CreateProjectPage() { const navigate = useNavigate(); const mutation = useProjectMutation(projectsApi.create); return <div className="phase2-page"><PageHeader title="Create project" description="Add a construction project to your company workspace."/><SectionCard title="Project information"><ProjectForm submitLabel="Create project" onCancel={() => navigate("/projects")} onSubmit={async (payload) => { const project = await mutation.mutateAsync(payload); toast.success("Project created."); navigate(`/projects/${project.id}`); }}/></SectionCard></div>; }
