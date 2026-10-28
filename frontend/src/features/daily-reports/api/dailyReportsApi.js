import api from "../../../api/axiosClient";

const base = (projectId) => `/v1/projects/${projectId}/daily-reports`;
const unwrap = (response) => response?.data?.data ?? response?.data;

export const dailyReportsApi = {
  list: (projectId, params) => api.get(base(projectId), { params }).then((r) => r.data),
  get: (projectId, reportId) => api.get(`${base(projectId)}/${reportId}`).then(unwrap),
  create: (projectId, payload) => api.post(base(projectId), payload).then(unwrap),
  update: (projectId, reportId, payload) => api.patch(`${base(projectId)}/${reportId}`, payload).then(unwrap),
  remove: (projectId, reportId) => api.delete(`${base(projectId)}/${reportId}`).then((r) => r.data),
  submit: (projectId, reportId) => api.post(`${base(projectId)}/${reportId}/submit`).then(unwrap),
  review: (projectId, reportId, payload) => api.post(`${base(projectId)}/${reportId}/review`, payload).then(unwrap),
  visibility: (projectId, reportId, is_client_visible) => api.patch(`${base(projectId)}/${reportId}/visibility`, { is_client_visible }).then(unwrap),
};
