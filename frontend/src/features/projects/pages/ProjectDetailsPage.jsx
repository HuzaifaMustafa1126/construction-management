import { Link, useParams } from "react-router-dom";
import { PageHeader, SectionCard } from "../../../components/ui/AppUI";
import { AppAlert, StatCard } from "../../../components/ui/Controls";
import { apiMessage } from "../../../api/endpoints";
import { useAuth } from "../../auth/context/AuthContext";
import ProjectStatusBadge from "../components/ProjectStatusBadge";
import ProjectTeamManager from "../components/ProjectTeamManager";
import { ProjectProgressControl, ProjectStatusControl } from "../components/ProjectControls";
import { useProject } from "../hooks/useProjects";
export default function ProjectDetailsPage() { const { id } = useParams(); const { can } = useAuth(); const query = useProject(id); if (query.isLoading) return <div role="status">Loading project…</div>; if (query.error) return <AppAlert tone="danger">{apiMessage(query.error, query.error?.status === 404 ? "Project not found or unavailable." : "Project could not be loaded.")}</AppAlert>; const p = query.data; return <div className="phase2-page"><PageHeader title={p.name} description={`${p.code}${p.location ? ` · ${p.location}` : ""}`} action={<div className="button-group"><Link className="app-button secondary" to="/projects">Back</Link>{can("projects.update") && <Link className="app-button primary" to={`/projects/${id}/edit`}>Edit project</Link>}</div>}/>
  <div className="project-stats"><StatCard label="Status" value={<ProjectStatusBadge status={p.status}/>}/><StatCard label="Progress" value={`${p.progress_percentage}%`}/><StatCard label="Start date" value={p.start_date || "Not set"}/><StatCard label="Expected end" value={p.expected_end_date || "Not set"}/></div>
  <SectionCard title="Overview"><dl className="project-overview"><div><dt>Description</dt><dd>{p.description || "No description"}</dd></div><div><dt>Address</dt><dd>{[p.address,p.city,p.state,p.country,p.postal_code].filter(Boolean).join(", ") || "Not set"}</dd></div>{p.budget !== undefined && <div><dt>Budget</dt><dd>{p.currency} {p.budget}</dd></div>}<div><dt>Actual end date</dt><dd>{p.actual_end_date || "Not set"}</dd></div></dl></SectionCard>
  {can("projects.update") && <SectionCard title="Status and progress"><div className="project-controls"><ProjectStatusControl project={p}/><ProjectProgressControl project={p}/></div></SectionCard>}
  <SectionCard title="Project team"><ProjectTeamManager projectId={id} canManage={can("projects.assign")}/></SectionCard>
  <SectionCard title="Related modules"><AppAlert>Daily reports, site photos, materials, and expenses are separate modules and are not implemented in Phase 3.1.</AppAlert></SectionCard>
  </div>; }
