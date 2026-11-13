/* eslint-disable react/prop-types */
import { useState } from "react";
import { Link } from "react-router-dom";
import { AppButton, SectionCard } from "../../../components/ui/AppUI";
import { AppAlert } from "../../../components/ui/Controls";
import { useAuth } from "../../auth/context/AuthContext";
import { useSitePhotoCollection } from "../../site-photos/hooks/useSitePhotos";
import ProtectedPhoto from "../../site-photos/components/ProtectedPhoto";
import {
  formatPhotoDate,
  humanizePhotoValue,
} from "../../site-photos/utils/photoFormatters";
import "./dashboardPhotos.css";

export default function DashboardRecentSitePhotos({
  projectId,
  projectName,
  period,
  dateFrom,
  dateTo,
}) {
  const { user } = useAuth();
  const scope = JSON.stringify([
    user?.company?.id,
    user?.id,
    projectId,
    period,
    dateFrom,
    dateTo,
  ]);
  return (
    <PhotoCollectionPage
      key={scope}
      projectId={projectId}
      projectName={projectName}
      period={period}
      dateFrom={dateFrom}
      dateTo={dateTo}
    />
  );
}

function PhotoCollectionPage({
  projectId,
  projectName,
  period,
  dateFrom,
  dateTo,
}) {
  const { user, can } = useAuth();
  const allowed = can("photos.view");
  const [page, setPage] = useState(1);
  const allTime = period === "all";
  const validRange = allTime || (!!dateFrom && !!dateTo && dateFrom <= dateTo);
  const photos = useSitePhotoCollection(
    {
      ...(projectId ? { project_id: projectId } : {}),
      ...(!allTime ? { date_from: dateFrom, date_to: dateTo } : {}),
      page,
      per_page: 3,
    },
    allowed && !!user?.id && !!user?.company?.id && validRange,
  );
  const denied = !allowed || photos.error?.status === 403;
  const rows = photos.data?.data || [];
  const meta = photos.data?.meta;
  const periodLabel = allTime
    ? "All time"
    : period === "month"
      ? "This month"
      : validRange
        ? `${formatPhotoDate(dateFrom)} – ${formatPhotoDate(dateTo)}`
        : "Custom period";
  let content;

  if (denied) {
    content = (
      <AppAlert tone="info">
        You do not have access to these site photos.
      </AppAlert>
    );
  } else if (!validRange) {
    content = (
      <p className="dashboard-photos-message">
        Choose a valid start and end date to view site photos.
      </p>
    );
  } else if (photos.isError) {
    content = (
      <AppAlert tone="danger">
        Site photos could not be loaded. Please try again later.
      </AppAlert>
    );
  } else if (!photos.isSuccess) {
    content = (
      <p className="dashboard-photos-message" role="status">
        Loading site photos...
      </p>
    );
  } else {
    content = (
      <>
        {!rows.length ? (
          <p className="dashboard-photos-message">
            No site photos match the selected filters.
          </p>
        ) : (
          <div className="dashboard-photos-grid">
            {rows.map((photo) => {
              const caption =
                [
                  photo.time_category
                    ? humanizePhotoValue(photo.time_category)
                    : "",
                  photo.photo_date ? formatPhotoDate(photo.photo_date) : "",
                ]
                  .filter(Boolean)
                  .join(" · ") ||
                photo.caption ||
                "Site photo";
              const photoProjectId = photo.project.id;
              return (
                <figure
                  className="dashboard-photos-item"
                  key={`${user.company.id}:${user.id}:${photoProjectId}:${photo.id}`}
                >
                  <Link
                    className="dashboard-photo-link"
                    to={`/site-photos/${photo.id}?project=${encodeURIComponent(photoProjectId)}`}
                    aria-label={`View ${photo.caption || "site photo"}: ${photo.project.name}, ${caption}`}
                  >
                    <span className="dashboard-photo-frame">
                      <ProtectedPhoto
                        fallbackOnError
                        projectId={photoProjectId}
                        photoId={photo.id}
                        alt={photo.caption || "Construction site photo"}
                      />
                    </span>
                    <span className="dashboard-photo-project">
                      {photo.project.name}
                    </span>
                    <span className="dashboard-photo-caption">{caption}</span>
                  </Link>
                </figure>
              );
            })}
          </div>
        )}
        {/* {meta && (
          <nav
            className="dashboard-report-pagination"
            aria-label="Site photos pagination"
          >
            <span aria-live="polite">
              {meta.total} photos · Page {meta.current_page} of {meta.last_page}
            </span>
            <div className="button-group">
              <AppButton
                type="button"
                variant="secondary"
                disabled={photos.isFetching || meta.current_page <= 1}
                onClick={() => setPage((current) => Math.max(1, current - 1))}
              >
                Previous
              </AppButton>
              <AppButton
                type="button"
                variant="secondary"
                disabled={
                  photos.isFetching || meta.current_page >= meta.last_page
                }
                onClick={() => setPage((current) => current + 1)}
              >
                Next
              </AppButton>
            </div>
          </nav>
        )} */}
      </>
    );
  }

  return (
    <SectionCard
      title="Recent Site Photos"
      action={
        allowed && projectId && !denied ? (
          <Link
            className="dashboard-photos-link"
            to={`/site-photos?project=${encodeURIComponent(projectId)}`}
          >
            View all
          </Link>
        ) : null
      }
    >
      <div className="dashboard-photos">
        {allowed && !denied && (
          <p className="dashboard-photos-scope">
            {projectId ? projectName || "Selected project" : "All projects"} ·{" "}
            {periodLabel}
          </p>
        )}
        {content}
      </div>
    </SectionCard>
  );
}
