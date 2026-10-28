/* eslint-disable react/prop-types */
import { useNavigate } from "react-router-dom";
import ProtectedPhoto from "./ProtectedPhoto";
import PhotoPeriodBadge from "./PhotoPeriodBadge";
import { formatPhotoDate } from "../utils/photoFormatters";
export default function SitePhotoCard({ photo, projectId }) { const navigate = useNavigate(); const open = () => navigate(`/site-photos/${photo.id}?project=${projectId}`); return <article className="site-photo-card"><button type="button" className="photo-card-image" onClick={open} aria-label={`View ${photo.caption || "site photo"}`}><ProtectedPhoto projectId={projectId} photoId={photo.id} alt={photo.caption || "Construction site photo"}/></button><div className="photo-card-body"><div className="photo-card-heading"><strong>{photo.caption || "Untitled site photo"}</strong><PhotoPeriodBadge period={photo.time_category}/></div><small>{formatPhotoDate(photo.photo_date)} · {photo.uploader?.name || "—"}</small>{photo.description && <p>{photo.description.length > 120 ? `${photo.description.slice(0, 120)}…` : photo.description}</p>}<button type="button" className="text-action" onClick={open}>View details</button></div></article>; }
