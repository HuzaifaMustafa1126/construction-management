import api from "../../../api/axiosClient";

const unwrap = (response) => response?.data?.data ?? response?.data;
const base = "/v1/material-requests";

export const materialRequestsApi = {
  list: (params) => api.get(base, { params }).then((response) => response.data),
  get: (id) => api.get(`${base}/${id}`).then(unwrap),
  create: (payload) => api.post(base, payload).then(unwrap),
  update: (id, payload) => api.patch(`${base}/${id}`, payload).then(unwrap),
  submit: (id) => api.post(`${base}/${id}/submit`).then(unwrap),
  approve: (id, payload) => api.post(`${base}/${id}/approve`, payload).then(unwrap),
  reject: (id, payload) => api.post(`${base}/${id}/reject`, payload).then(unwrap),
  cancel: (id) => api.post(`${base}/${id}/cancel`).then(unwrap),
  issue: (id, payload) => api.post(`${base}/${id}/issue`, payload).then(unwrap),
  returnStock: (id, payload) => api.post(`${base}/${id}/return`, payload).then(unwrap),
  transfer: (payload) => api.post("/v1/inventory-transfers", payload).then(unwrap),
};
