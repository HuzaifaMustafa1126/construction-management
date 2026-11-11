/* eslint-disable react/prop-types */
import { useState } from "react";
import { AppIcon } from "../../../components/ui/AppUI";
import { useProtectedPhoto } from "../hooks/useProtectedPhoto";
export default function ProtectedPhoto({ projectId, photoId, alt, className = "", onClick, fallbackOnError = false }) { const image = useProtectedPhoto(projectId, photoId); const [failedUrl, setFailedUrl] = useState(null); if (image.loading) return <div className={`photo-placeholder ${className}`} role="status">Loading image…</div>; if (image.error || !image.url || (fallbackOnError && failedUrl === image.url)) return <div className={`photo-placeholder broken ${className}`}><AppIcon name="warning"/><span>Image unavailable</span></div>; return <img className={className} src={image.url} alt={alt} loading="lazy" onError={fallbackOnError ? () => setFailedUrl(image.url) : undefined} onClick={onClick}/>; }
