/* eslint-disable react/prop-types */
import { Link, useParams } from "react-router-dom";
import { PageHeader, SectionCard } from "../../../components/ui/AppUI";
import { AppAlert, StatCard } from "../../../components/ui/Controls";
import { apiMessage } from "../../../api/endpoints";
import { useAuth } from "../../auth/context/AuthContext";
import ProjectStatusBadge from "../components/ProjectStatusBadge";
import ProjectTeamManager from "../components/ProjectTeamManager";
import { ProjectProgressControl, ProjectStatusControl } from "../components/ProjectControls";
import { useProject } from "../hooks/useProjects";
import { useDailyReports } from "../../daily-reports/hooks/useDailyReports";
import ReportStatusBadge from "../../daily-reports/components/ReportStatusBadge";
import { excerpt, formatReportDate } from "../../daily-reports/utils/reportFormatters";
import { useSitePhotos } from "../../site-photos/hooks/useSitePhotos";
import SitePhotoCard from "../../site-photos/components/SitePhotoCard";
export default function ProjectDetailsPage() { const { id } = useParams(); const { can } = useAuth(); const query = useProject(id); if (query.isLoading) return <div role="status">Loading project…</div>; if (query.error) return <AppAlert tone="danger">{apiMessage(query.error, query.error?.status === 404 ? "Project not found or unavailable." : "Project could not be loaded.")}</AppAlert>; const p = query.data; return <div className="phase2-page"><PageHeader title={p.name} description={`${p.code}${p.location ? ` · ${p.location}` : ""}`} action={<div className="button-group"><Link className="app-button secondary" to="/projects">Back</Link>{can("projects.edit") && <Link className="app-button primary" to={`/projects/${id}/edit`}>Edit project</Link>}</div>}/>
  <div className="project-stats"><StatCard label="Status" value={<ProjectStatusBadge status={p.status}/>}/><StatCard label="Progress" value={`${p.progress_percentage}%`}/><StatCard label="Start date" value={p.start_date || "Not set"}/><StatCard label="Expected end" value={p.expected_end_date || "Not set"}/></div>
  <SectionCard title="Overview"><dl className="project-overview"><div><dt>Description</dt><dd>{p.description || "No description"}</dd></div><div><dt>Address</dt><dd>{[p.address,p.city,p.state,p.country,p.postal_code].filter(Boolean).join(", ") || "Not set"}</dd></div>{p.budget !== undefined && <div><dt>Budget</dt><dd>{p.currency} {p.budget}</dd></div>}<div><dt>Actual end date</dt><dd>{p.actual_end_date || "Not set"}</dd></div></dl></SectionCard>
  {can("projects.edit") && <SectionCard title="Status and progress"><div className="project-controls"><ProjectStatusControl project={p}/><ProjectProgressControl project={p}/></div></SectionCard>}
  <SectionCard title="Project team"><ProjectTeamManager projectId={id} canManage={can("projects.assign")}/></SectionCard>
  {can("daily_reports.view") && <ProjectDailyReports projectId={id} canCreate={can("daily_reports.create")}/>}
  {can("photos.view") && <ProjectSitePhotos projectId={id} canUpload={can("photos.create")}/>}
  {can("inventory.view") && <SectionCard title="Materials and inventory" action={<Link className="app-button primary" to={`/projects/${id}/inventory`}>View full inventory</Link>}><AppAlert>View backend-calculated stock balances, low-stock warnings, opening stock, and material movement history for this project.</AppAlert></SectionCard>}
  <SectionCard title="Related modules"><AppAlert>Expenses remain a separate module.</AppAlert></SectionCard>
  </div>; }

function ProjectDailyReports({ projectId, canCreate }) { const reports = useDailyReports(projectId, { page: 1, per_page: 5, sort: "report_date", direction: "desc" }); return <SectionCard title="Daily site reports" action={<div className="button-group">{canCreate && <Link className="app-button primary" to={`/daily-reports/new?project=${projectId}`}>Create report</Link>}<Link className="app-button secondary" to={`/daily-reports?project=${projectId}`}>View all</Link></div>}>{reports.error && <AppAlert tone="danger">{apiMessage(reports.error, "Daily reports could not be loaded.")}</AppAlert>}{reports.isLoading ? <div role="status">Loading reports…</div> : !reports.data?.data?.length ? <p>No daily reports have been recorded for this project.</p> : <div className="project-report-list">{reports.data.data.map((report) => <article key={report.id}><div><strong>{report.title}</strong><small>{formatReportDate(report.report_date)} · {report.creator?.name || "—"}</small><p>{excerpt(report.work_completed, 120)}</p></div><div><ReportStatusBadge status={report.status}/><Link to={`/daily-reports/${report.id}?project=${projectId}`}>View report</Link></div></article>)}</div>}</SectionCard>; }

function ProjectSitePhotos({ projectId, canUpload }) { const photos = useSitePhotos(projectId, { page: 1, per_page: 4, sort: "photo_date", direction: "desc" }); return <SectionCard title="Recent site photos" action={<div className="button-group">{canUpload && <Link className="app-button primary" to={`/site-photos/upload?project=${projectId}`}>Upload photos</Link>}<Link className="app-button secondary" to={`/site-photos?project=${projectId}`}>View all</Link></div>}>{photos.error && <AppAlert tone="danger">{apiMessage(photos.error, "Site photos could not be loaded.")}</AppAlert>}{photos.isLoading ? <div role="status">Loading photos…</div> : !photos.data?.data?.length ? <p>No site photos have been uploaded for this project.</p> : <div className="photo-gallery compact">{photos.data.data.map((photo) => <SitePhotoCard key={photo.id} photo={photo} projectId={projectId}/>)}</div>}</SectionCard>; }
