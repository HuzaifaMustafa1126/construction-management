import api from "../services/apiClient";

const unwrap = (response, key) => {
  const body = response?.data ?? response;
  const value =
    key && body?.[key] !== undefined ? body[key] : (body?.data ?? body);
  return value;
};

export const authApi = {
  registerCompany: (payload) =>
    api.post("/v1/register-company", payload).then((r) => unwrap(r)),
  verifyEmail: (pathAndQuery) => api.get(pathAndQuery).then((r) => unwrap(r)),
  resendVerification: (payload) =>
    api.post("/v1/resend-verification", payload).then((r) => unwrap(r)),
  login: (payload) => api.post("/v1/login", payload).then((r) => unwrap(r)),
  logout: () => api.post("/v1/logout").then((r) => unwrap(r)),
  me: () => api.get("/v1/me").then((r) => unwrap(r, "user")),
  updateMe: (payload) =>
    api.put("/v1/me", payload).then((r) => unwrap(r, "user")),
  updatePassword: (payload) =>
    api.put("/v1/me/password", payload).then((r) => unwrap(r)),
};

export const companyApi = {
  get: () => api.get("/v1/company").then((r) => unwrap(r, "company")),
  update: (payload) =>
    api.put("/v1/company", payload).then((r) => unwrap(r, "company")),
};

const crud = (path, key) => ({
  list: (params) => api.get(path, { params }).then((r) => unwrap(r, key)),
  get: (id) =>
    api.get(`${path}/${id}`).then((r) => unwrap(r, key?.replace(/s$/, ""))),
  create: (payload) =>
    api.post(path, payload).then((r) => unwrap(r, key?.replace(/s$/, ""))),
  update: (id, payload) =>
    api
      .put(`${path}/${id}`, payload)
      .then((r) => unwrap(r, key?.replace(/s$/, ""))),
  remove: (id) => api.delete(`${path}/${id}`).then((r) => unwrap(r)),
});

export const usersApi = {
  ...crud("/v1/users", "users"),
  status: (id, status) =>
    api.patch(`/v1/users/${id}/status`, { status }).then((r) => unwrap(r)),
  roles: (id, roles) =>
    api.put(`/v1/users/${id}/roles`, { roles }).then((r) => unwrap(r)),
  department: (id, department_id) =>
    api
      .put(`/v1/users/${id}/department`, { department_id })
      .then((r) => unwrap(r)),
};
export const rolesApi = crud("/v1/roles", "roles");
export const permissionsApi = {
  list: () => api.get("/v1/permissions").then((r) => unwrap(r, "permissions")),
};
export const departmentsApi = {
  ...crud("/v1/departments", "departments"),
  status: (id, status) =>
    api
      .patch(`/v1/departments/${id}/status`, { status })
      .then((r) => unwrap(r)),
  users: (id) =>
    api.get(`/v1/departments/${id}/users`).then((r) => unwrap(r, "users")),
};

export function pageData(value) {
  if (Array.isArray(value)) return { rows: value, meta: null };
  if (Array.isArray(value?.data)) return { rows: value.data, meta: value };
  return { rows: [], meta: null };
}

export function apiMessage(
  error,
  fallback = "Something went wrong. Please try again.",
) {
  if (error?.message && error?.kind) return error.message;
  if (!error?.response)
    return error?.request
      ? "Cannot connect to the server. Please try again."
      : fallback;
  const data = error.response.data;
  if (typeof data === "string") return fallback;
  return (
    data?.message || Object.values(data?.errors || {}).flat()[0] || fallback
  );
}

export function applyServerErrors(error, setError) {
  const errors = error?.status === 422
    ? error.fieldErrors
    : error?.response?.status === 422
      ? error.response.data?.errors
      : null;
  Object.entries(errors || {}).forEach(([field, messages]) =>
    setError(field, { type: "server", message: [].concat(messages)[0] }),
  );
}
