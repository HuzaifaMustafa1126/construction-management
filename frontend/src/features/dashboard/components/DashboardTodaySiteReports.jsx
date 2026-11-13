/* eslint-disable react/prop-types */
import { useState } from "react";
import { Link } from "react-router-dom";
import { AppButton, AppIcon, SectionCard } from "../../../components/ui/AppUI";
import { AppAlert } from "../../../components/ui/Controls";
import { useAuth } from "../../auth/context/AuthContext";
import { useDailyReportCollection } from "../../daily-reports/hooks/useDailyReports";
import ReportStatusBadge from "../../daily-reports/components/ReportStatusBadge";
import { formatReportDate } from "../../daily-reports/utils/reportFormatters";
import { buildReportListSearch } from "../../daily-reports/utils/reportListFilters";
import "./dashboardReports.css";

export default function DashboardSiteReports({
  projectId,
  projectName,
  dateFrom,
  dateTo,
  period,
}) {
  const { user } = useAuth();
  // Reset pagination synchronously with scope; never request an old page for new filters.
  const scope = JSON.stringify([
    user?.company?.id,
    user?.id,
    projectId,
    dateFrom,
    dateTo,
    period,
  ]);
  return (
    <SiteReportsPage
      key={scope}
      projectId={projectId}
      projectName={projectName}
      dateFrom={dateFrom}
      dateTo={dateTo}
      period={period}
    />
  );
}

function SiteReportsPage({ projectId, projectName, dateFrom, dateTo, period }) {
  const { user, can } = useAuth();
  const allowed = can("daily_reports.view");
  const [page, setPage] = useState(1);
  const allTime = period === "all";
  const validRange = allTime || (!!dateFrom && !!dateTo && dateFrom <= dateTo);
  const periodLabel = allTime
    ? "All time"
    : period === "month"
      ? "This month"
      : validRange
        ? `${formatReportDate(dateFrom)} – ${formatReportDate(dateTo)}`
        : "Custom period";
  const reports = useDailyReportCollection(
    {
      ...(projectId ? { project_id: projectId } : {}),
      ...(!allTime ? { date_from: dateFrom, date_to: dateTo } : {}),
      page,
      per_page: 4,
    },
    allowed && !!user?.id && !!user?.company?.id && validRange,
  );
  const denied = !allowed || reports.error?.status === 403;
  const rows = reports.data?.data || [];
  const meta = reports.data?.meta;
  const pageUnavailable =
    reports.isSuccess && meta?.current_page > meta?.last_page;
  let content;

  if (denied) {
    content = (
      <AppAlert tone="info">
        You do not have permission to view these site reports.
      </AppAlert>
    );
  } else if (!validRange) {
    content = (
      <p className="dashboard-reports-message">
        Choose a valid start and end date to view site reports.
      </p>
    );
  } else if (reports.isError) {
    content = (
      <AppAlert tone="danger">
        Site reports could not be loaded. Please try again later.
      </AppAlert>
    );
  } else if (!reports.isSuccess) {
    content = (
      <p className="dashboard-reports-message" role="status">
        Loading site reports...
      </p>
    );
  } else {
    content = (
      <>
        {!rows.length ? (
          <p className="dashboard-reports-message">
            {pageUnavailable
              ? "This report page is no longer available. Use Previous to return to available reports."
              : "No site reports match the selected filters."}
          </p>
        ) : (
          <ul className="dashboard-report-list">
            {rows.map((report) => (
              <li key={report.id}>
                <span
                  className="dashboard-report-icon"
                  data-status={report.status}
                  aria-hidden="true"
                >
                  <AppIcon name="reports" />
                </span>
                <div className="dashboard-report-copy">
                  <Link
                    className="dashboard-report-title"
                    aria-label={`View report: ${report.title} (${report.project.name})`}
                    to={`/daily-reports/${report.id}?project=${encodeURIComponent(report.project.id)}`}
                  >
                    {report.title}
                  </Link>
                  <span className="dashboard-report-project">
                    {report.project.name}
                  </span>
                  {report.creator?.name && (
                    <span>By {report.creator.name}</span>
                  )}
                  {report.summary && (
                    <p className="dashboard-report-summary">{report.summary}</p>
                  )}
                </div>
                <time
                  className="dashboard-report-date"
                  dateTime={report.report_date}
                >
                  {formatReportDate(report.report_date)}
                </time>
                <ReportStatusBadge status={report.status} />
              </li>
            ))}
          </ul>
        )}
        {meta && (
          <nav
            className="dashboard-report-pagination"
            aria-label="Site reports pagination"
          >
            <span aria-live="polite">
              {rows.length && meta.total
                ? `${meta.from ?? (meta.current_page - 1) * meta.per_page + 1}–${meta.to ?? Math.min(meta.current_page * meta.per_page, meta.total)} of ${meta.total} reports`
                : `${meta.total} reports`}
              {pageUnavailable
                ? " · Page no longer available"
                : ` · Page ${meta.current_page} of ${meta.last_page}`}
            </span>
            <div className="button-group">
              <AppButton
                type="button"
                variant="secondary"
                disabled={reports.isFetching || meta.current_page <= 1}
                onClick={() =>
                  setPage((current) =>
                    Math.max(1, Math.min(current - 1, meta.last_page)),
                  )
                }
              >
                Previous
              </AppButton>
              <AppButton
                type="button"
                variant="secondary"
                disabled={
                  reports.isFetching || meta.current_page >= meta.last_page
                }
                onClick={() => setPage((current) => current + 1)}
              >
                Next
              </AppButton>
            </div>
          </nav>
        )}
      </>
    );
  }

  return (
    <SectionCard
      title="Site Reports"
      action={
        allowed && validRange && !denied ? (
          <Link
            className="dashboard-reports-link"
            to={`/daily-reports?${buildReportListSearch({ project_id: projectId, period, date_from: dateFrom, date_to: dateTo })}`}
          >
            View all
          </Link>
        ) : null
      }
    >
      {allowed && validRange && !denied && (
        <p className="dashboard-reports-scope">
          {projectId ? projectName || "Selected project" : "All projects"} ·{" "}
          {periodLabel}
        </p>
      )}
      {content}
    </SectionCard>
  );
}
