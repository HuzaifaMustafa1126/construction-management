/* eslint-disable react/prop-types */
import { useState } from "react";
import { Link, useParams } from "react-router-dom";
import { PageHeader, SectionCard } from "../../../components/ui/AppUI";
import {
  AppAlert,
  StatCard,
  TabNavigation,
} from "../../../components/ui/Controls";
import { apiMessage } from "../../../api/endpoints";
import { useAuth } from "../../auth/context/AuthContext";
import ProjectStatusBadge from "../components/ProjectStatusBadge";
import ProjectTeamManager from "../components/ProjectTeamManager";
import {
  ProjectProgressControl,
  ProjectStatusControl,
} from "../components/ProjectControls";
import { useProject } from "../hooks/useProjects";
import { useDailyReports } from "../../daily-reports/hooks/useDailyReports";
import ReportStatusBadge from "../../daily-reports/components/ReportStatusBadge";
import {
  formatReportDate,
} from "../../daily-reports/utils/reportFormatters";
import { useSitePhotos } from "../../site-photos/hooks/useSitePhotos";
import SitePhotoCard from "../../site-photos/components/SitePhotoCard";

export default function ProjectDetailsPage() {
  const { id } = useParams();
  const { can } = useAuth();
  const [tab, setTab] = useState("Overview");
  const query = useProject(id);
  if (query.isLoading) return <div role="status">Loading project…</div>;
  if (query.error)
    return (
      <AppAlert tone="danger">
        {apiMessage(
          query.error,
          query.error?.status === 404
            ? "Project not found or unavailable."
            : "Project could not be loaded.",
        )}
      </AppAlert>
    );
  const project = query.data;

  return (
    <div className="phase2-page">
      <PageHeader
        title={project.name}
        description={`${project.code}${project.location ? ` · ${project.location}` : ""}`}
        action={
          <div className="button-group">
            <Link className="app-button secondary" to="/projects">
              Back
            </Link>
            {can("projects.edit") && (
              <Link className="app-button primary" to={`/projects/${id}/edit`}>
                Edit project
              </Link>
            )}
          </div>
        }
      />
      <div style={{ marginBottom: "10px" }}>
        <TabNavigation
          items={["Overview", "Team"]}
          active={tab}
          onChange={setTab}
        />
      </div>

      {tab === "Team" ? (
        <SectionCard title="Project team">
          <ProjectTeamManager
            projectId={id}
            canManage={can("projects.assign")}
          />
        </SectionCard>
      ) : (
        <ProjectOverview project={project} projectId={id} can={can} />
      )}
    </div>
  );
}

function ProjectOverview({ project, projectId, can }) {
  return (
    <>
      <div className="project-stats">
        <StatCard
          label="Status"
          value={<ProjectStatusBadge status={project.status} />}
        />
        <StatCard label="Progress" value={`${project.progress_percentage}%`} />
        <StatCard label="Start date" value={project.start_date || "Not set"} />
        <StatCard
          label="Expected end"
          value={project.expected_end_date || "Not set"}
        />
      </div>
      <SectionCard title="Overview">
        <dl className="project-overview">
          <div>
            <dt>Client</dt>
            <dd>
              {project.client
                ? `${project.client.name}${project.client.company_name ? ` — ${project.client.company_name}` : ""}`
                : "Not assigned"}
            </dd>
          </div>
          <div>
            <dt>Project manager</dt>
            <dd>{project.project_manager?.name || "Unassigned"}</dd>
          </div>
          <div>
            <dt>Description</dt>
            <dd>{project.description || "No description"}</dd>
          </div>
          <div>
            <dt>Address</dt>
            <dd>
              {[
                project.address,
                project.city,
                project.state,
                project.country,
                project.postal_code,
              ]
                .filter(Boolean)
                .join(", ") || "Not set"}
            </dd>
          </div>
          {project.budget !== undefined && (
            <div>
              <dt>Budget</dt>
              <dd>
                {project.currency} {project.budget}
              </dd>
            </div>
          )}
          <div>
            <dt>Actual end date</dt>
            <dd>{project.actual_end_date || "Not set"}</dd>
          </div>
        </dl>
      </SectionCard>
      {can("projects.edit") && (
        <SectionCard title="Status and progress">
          <div className="project-controls">
            <ProjectStatusControl project={project} />
            <ProjectProgressControl project={project} />
          </div>
        </SectionCard>
      )}
      {can("daily_reports.view") && (
        <ProjectDailyReports
          projectId={projectId}
          canCreate={can("daily_reports.create")}
        />
      )}
      {can("photos.view") && (
        <ProjectSitePhotos
          projectId={projectId}
          canUpload={can("photos.create")}
        />
      )}
      {can("inventory.view") && (
        <SectionCard
          title="Materials and inventory"
          action={
            <Link
              className="app-button primary"
              to={`/projects/${projectId}/inventory`}
            >
              View full inventory
            </Link>
          }
        >
          <AppAlert>
            View backend-calculated stock balances, low-stock warnings, opening
            stock, and material movement history for this project.
          </AppAlert>
        </SectionCard>
      )}
      <SectionCard title="Related modules">
        <AppAlert>Expenses remain a separate module.</AppAlert>
      </SectionCard>
    </>
  );
}

function ProjectDailyReports({ projectId, canCreate }) {
  const reports = useDailyReports(projectId, {
    page: 1,
    per_page: 5,
    sort: "report_date",
    direction: "desc",
  });
  return (
    <SectionCard
      title="Daily site reports"
      action={
        <div className="button-group project-report-header-actions">
          {canCreate && (
            <Link
              className="app-button primary"
              to={`/daily-reports/new?project=${projectId}`}
            >
              Create report
            </Link>
          )}
          <Link
            className="app-button secondary"
            to={`/daily-reports?project=${projectId}`}
          >
            View all
          </Link>
        </div>
      }
    >
      {reports.error && (
        <AppAlert tone="danger">
          {apiMessage(reports.error, "Daily reports could not be loaded.")}
        </AppAlert>
      )}
      {reports.isLoading ? (
        <div role="status">Loading reports…</div>
      ) : reports.error ? null : !reports.data?.data?.length ? (
        <p>No daily reports have been recorded for this project.</p>
      ) : (
        <div className="project-report-list">
          {reports.data.data.map((report) => (
            <article key={report.id}>
              <div className="project-report-content">
                <h3>{report.title}</h3>
                <small>
                  {formatReportDate(report.report_date)} ·{" "}
                  {report.creator?.name || "—"}
                </small>
                <p>{report.work_completed || "—"}</p>
              </div>
              <div className="project-report-actions">
                <ReportStatusBadge status={report.status} />
                <Link className="app-button secondary" to={`/daily-reports/${report.id}?project=${projectId}`}>
                  View report
                </Link>
              </div>
            </article>
          ))}
        </div>
      )}
    </SectionCard>
  );
}

function ProjectSitePhotos({ projectId, canUpload }) {
  const photos = useSitePhotos(projectId, {
    page: 1,
    per_page: 4,
    sort: "photo_date",
    direction: "desc",
  });
  return (
    <SectionCard
      title="Recent site photos"
      action={
        <div className="button-group project-photo-header-actions">
          {canUpload && (
            <Link
              className="app-button primary"
              to={`/site-photos/upload?project=${projectId}`}
            >
              Upload photos
            </Link>
          )}
          <Link
            className="app-button secondary"
            to={`/site-photos?project=${projectId}`}
          >
            View all
          </Link>
        </div>
      }
    >
      {photos.error && (
        <AppAlert tone="danger">
          {apiMessage(photos.error, photos.error?.status === 403 ? "You do not have access to these site photos." : "Site photos could not be loaded.")}
        </AppAlert>
      )}
      {photos.isLoading ? (
        <div role="status">Loading photos…</div>
      ) : photos.error ? null : !photos.data?.data?.length ? (
        <p>No site photos have been uploaded for this project.</p>
      ) : (
        <div className="project-photo-preview">
          {photos.data.data.map((photo) => (
            <SitePhotoCard preview key={photo.id} photo={photo} projectId={projectId} />
          ))}
        </div>
      )}
    </SectionCard>
  );
}
