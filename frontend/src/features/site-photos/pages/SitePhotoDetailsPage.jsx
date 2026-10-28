/* eslint-disable react/prop-types */
import { useState } from "react";
import { Link, useNavigate, useParams, useSearchParams } from "react-router-dom";
import { toast } from "react-toastify";
import { AppButton, AppModal, ConfirmDialog, PageHeader, SectionCard } from "../../../components/ui/AppUI";
import { AppAlert, AppSwitch, AppTextarea } from "../../../components/ui/Controls";
import { apiMessage } from "../../../api/endpoints";
import { useAuth } from "../../auth/context/AuthContext";
import { useProject } from "../../projects/hooks/useProjects";
import { sitePhotosApi } from "../api/sitePhotosApi";
import PhotoPeriodBadge from "../components/PhotoPeriodBadge";
import ProtectedPhoto from "../components/ProtectedPhoto";
import SitePhotoMetadataForm from "../components/SitePhotoMetadataForm";
import { useSitePhoto, useSitePhotoMutation } from "../hooks/useSitePhotos";
import { formatFileSize, formatPhotoDate, formatPhotoDateTime, humanizePhotoValue } from "../utils/photoFormatters";
const hasRole = (user, role) => (user?.roles || []).some((x) => (typeof x === "string" ? x : x.name) === role);
const Field = ({ label, children }) => <div><dt>{label}</dt><dd>{children || "—"}</dd></div>;

export default function SitePhotoDetailsPage() {
  const { id } = useParams(); const [params] = useSearchParams(); const projectId = params.get("project"); const navigate = useNavigate(); const { can, user } = useAuth();
  const photoQuery = useSitePhoto(projectId, id); const projectQuery = useProject(projectId); const mutation = useSitePhotoMutation(({ action, payload }) => action(payload));
  const [viewer, setViewer] = useState(false); const [editing, setEditing] = useState(false); const [deleting, setDeleting] = useState(false); const [reviewing, setReviewing] = useState(false); const [reviewAction, setReviewAction] = useState("approve"); const [reviewNotes, setReviewNotes] = useState("");
  if (!projectId) return <AppAlert tone="danger">A project reference is required to load this photo.</AppAlert>;
  if (photoQuery.isLoading) return <div role="status">Loading site photo…</div>;
  if (photoQuery.error) return <AppAlert tone="danger">{apiMessage(photoQuery.error, photoQuery.error?.status === 404 ? "Photo not found or unavailable." : "Site photo could not be loaded.")}</AppAlert>;
  const photo = photoQuery.data; const admin = hasRole(user, "company_admin"); const client = hasRole(user, "client"); const own = photo.uploader?.id === user?.id; const editable = can("photos.update") && !client && (admin || (own && ["pending", "rejected"].includes(photo.approval_status)));
  const run = async (action, success, payload) => { try { await mutation.mutateAsync({ action, payload }); toast.success(success); } catch (error) { toast.error(apiMessage(error)); throw error; } };
  const save = async (payload) => { await run((values) => sitePhotosApi.update(projectId, id, values), "Photo metadata updated.", payload); setEditing(false); };
  const review = async () => { if (reviewAction === "reject" && !reviewNotes.trim()) { toast.error("Review notes are required when rejecting a photo."); return; } await run((payload) => sitePhotosApi.review(projectId, id, payload), reviewAction === "approve" ? "Photo approved." : "Photo rejected.", { action: reviewAction, review_notes: reviewNotes || null }); setReviewing(false); };
  const remove = async () => { await run(() => sitePhotosApi.remove(projectId, id), "Site photo deleted."); navigate(`/site-photos?project=${projectId}`); };
  return <div className="phase2-page"><PageHeader title={photo.caption || "Site photo"} description={`${projectQuery.data?.name || "Project"} · ${formatPhotoDate(photo.photo_date)}`} action={<div className="button-group"><Link className="app-button secondary" to={`/site-photos?project=${projectId}`}>Back</Link>{editable && <AppButton variant="secondary" onClick={() => setEditing(true)}>Edit metadata</AppButton>}{admin && can("photos.approve") && photo.approval_status === "pending" && <AppButton onClick={() => setReviewing(true)}>Review</AppButton>}{admin && can("photos.delete") && <AppButton variant="danger" onClick={() => setDeleting(true)}>Delete</AppButton>}</div>}/>
    <SectionCard title="Protected photo"><button className="photo-detail-image" type="button" onClick={() => setViewer(true)} aria-label="Open large photo viewer"><ProtectedPhoto projectId={projectId} photoId={id} alt={photo.caption || "Construction site photo"}/></button></SectionCard>
    <SectionCard title="Photo details"><dl className="project-overview"><Field label="Project">{projectQuery.data?.name}</Field><Field label="Photo date">{formatPhotoDate(photo.photo_date)}</Field><Field label="Period"><PhotoPeriodBadge period={photo.time_category}/></Field><Field label="Uploaded by">{photo.uploader?.name}</Field><Field label="Caption">{photo.caption}</Field><Field label="Description">{photo.description}</Field><Field label="File">{photo.mime_type} · {formatFileSize(photo.file_size)}</Field><Field label="Daily report">{photo.daily_site_report_id ? `Report #${photo.daily_site_report_id}` : "Not linked"}</Field>{!client && <><Field label="Approval">{humanizePhotoValue(photo.approval_status)}</Field><Field label="Review notes">{photo.review_notes}</Field></>}<Field label="Created">{formatPhotoDateTime(photo.created_at)}</Field><Field label="Updated">{formatPhotoDateTime(photo.updated_at)}</Field></dl>{admin && can("photos.approve") && <div className="visibility-control"><AppSwitch label="Visible to assigned clients" checked={!!photo.is_client_visible} disabled={photo.approval_status !== "approved" || mutation.isPending} onChange={(event) => run((visible) => sitePhotosApi.visibility(projectId, id, visible), event.target.checked ? "Photo is now client-visible." : "Photo is now internal.", event.target.checked)}/>{photo.approval_status !== "approved" && <small>Only approved photos can be client-visible.</small>}</div>}</SectionCard>
    <AppModal open={viewer} title={photo.caption || "Site photo"} onClose={() => setViewer(false)}><div className="photo-viewer"><ProtectedPhoto projectId={projectId} photoId={id} alt={photo.caption || "Construction site photo"}/></div></AppModal>
    <AppModal open={editing} title="Edit photo metadata" onClose={() => setEditing(false)}><SitePhotoMetadataForm photo={photo} onSave={save} onCancel={() => setEditing(false)}/></AppModal>
    <AppModal open={reviewing} title="Review site photo" onClose={() => setReviewing(false)}><div className="review-actions"><label className="check-control"><input type="radio" name="photo-review" checked={reviewAction === "approve"} onChange={() => setReviewAction("approve")}/><span>Approve</span></label><label className="check-control"><input type="radio" name="photo-review" checked={reviewAction === "reject"} onChange={() => setReviewAction("reject")}/><span>Reject</span></label><AppTextarea label="Review notes" rows="4" value={reviewNotes} onChange={(e) => setReviewNotes(e.target.value)}/><div className="modal-actions"><AppButton variant="secondary" onClick={() => setReviewing(false)}>Cancel</AppButton><AppButton busy={mutation.isPending} onClick={review}>Save review</AppButton></div></div></AppModal>
    <ConfirmDialog open={deleting} title="Delete Site Photo?" message="This removes the photo from normal access. Laravel retains the private file according to its backend deletion policy." busy={mutation.isPending} onClose={() => setDeleting(false)} onConfirm={remove}/>
  </div>;
}
