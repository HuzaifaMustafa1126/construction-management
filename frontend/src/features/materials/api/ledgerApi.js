import api from "../../../api/axiosClient";
export const ledgerApi = {
  movements: (projectId, params) => api.get(`/v1/projects/${projectId}/inventory-reports/movements`, { params }).then((response) => response.data),
  overview: (projectId) => api.get(`/v1/projects/${projectId}/inventory-reports/overview`).then((response) => response.data.data),
  reconciliation: (projectId, params) => api.get(`/v1/projects/${projectId}/inventory-reports/reconciliation`, { params }).then((response) => response.data),
};
