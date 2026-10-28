import api from "../../../api/axiosClient";
const unwrap = (response) => response?.data?.data ?? response?.data;
const base = (projectId) => `/v1/projects/${projectId}/stock-adjustments`;
export const adjustmentsApi = {
  list: (projectId, params) => api.get(base(projectId), { params }).then((response) => response.data),
  get: (projectId, id) => api.get(`${base(projectId)}/${id}`).then(unwrap),
  create: (projectId, payload) => api.post(base(projectId), payload).then(unwrap),
  update: (projectId, id, payload) => api.patch(`${base(projectId)}/${id}`, payload).then(unwrap),
  post: (projectId, id, payload = {}) => api.post(`${base(projectId)}/${id}/post`, payload).then(unwrap),
  cancel: (projectId, id) => api.post(`${base(projectId)}/${id}/cancel`).then(unwrap),
};
