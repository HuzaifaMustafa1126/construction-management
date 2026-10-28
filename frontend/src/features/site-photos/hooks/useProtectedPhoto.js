import { useEffect, useState } from "react";
import { sitePhotosApi } from "../api/sitePhotosApi";

export function useProtectedPhoto(projectId, photoId) {
  const [state, setState] = useState({ url: "", loading: true, error: null });
  useEffect(() => { if (!projectId || !photoId) return undefined; const controller = new AbortController(); let objectUrl = ""; setState({ url: "", loading: true, error: null }); sitePhotosApi.file(projectId, photoId, controller.signal).then((blob) => { objectUrl = URL.createObjectURL(blob); setState({ url: objectUrl, loading: false, error: null }); }).catch((error) => { if (error?.name !== "CanceledError") setState({ url: "", loading: false, error }); }); return () => { controller.abort(); if (objectUrl) URL.revokeObjectURL(objectUrl); }; }, [projectId, photoId]);
  return state;
}
