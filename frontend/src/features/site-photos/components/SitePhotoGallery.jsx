/* eslint-disable react/prop-types */
import { AppIcon } from "../../../components/ui/AppUI";
import SitePhotoCard from "./SitePhotoCard";
export default function SitePhotoGallery({ photos, projectId, loading }) { if (loading) return <div className="photo-gallery" role="status">{Array.from({ length: 6 }, (_, i) => <div className="photo-gallery-skeleton" key={i}/>)}</div>; if (!photos.length) return <div className="empty-state"><AppIcon name="photos" size="xl"/><strong>No site photos found</strong><p>No photos match the selected project and filters.</p></div>; return <div className="photo-gallery">{photos.map((photo) => <SitePhotoCard key={photo.id} photo={photo} projectId={projectId}/>)}</div>; }
