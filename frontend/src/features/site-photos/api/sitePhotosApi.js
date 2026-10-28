import api from "../../../api/axiosClient";

const base = (projectId) => `/v1/projects/${projectId}/photos`;
const unwrap = (response) => response?.data?.data ?? response?.data;
export const sitePhotosApi = {
  list: (projectId, params) => api.get(base(projectId), { params }).then((r) => r.data),
  get: (projectId, photoId) => api.get(`${base(projectId)}/${photoId}`).then(unwrap),
  file: (projectId, photoId, signal) => api.get(`${base(projectId)}/${photoId}/file`, { responseType: "blob", signal }).then((r) => r.data),
  upload: (projectId, formData, onUploadProgress) => api.post(base(projectId), formData, { onUploadProgress }).then(unwrap),
  uploadBatch: (projectId, formData, onUploadProgress) => api.post(`${base(projectId)}/batch`, formData, { onUploadProgress }).then(unwrap),
  update: (projectId, photoId, payload) => api.patch(`${base(projectId)}/${photoId}`, payload).then(unwrap),
  review: (projectId, photoId, payload) => api.post(`${base(projectId)}/${photoId}/review`, payload).then(unwrap),
  visibility: (projectId, photoId, is_client_visible) => api.patch(`${base(projectId)}/${photoId}/visibility`, { is_client_visible }).then(unwrap),
  remove: (projectId, photoId) => api.delete(`${base(projectId)}/${photoId}`).then((r) => r.data),
  reportPhotos: (projectId, reportId) => api.get(`/v1/projects/${projectId}/daily-reports/${reportId}/photos`).then(unwrap),
};
