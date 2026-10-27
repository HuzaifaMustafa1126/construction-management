import axios from "axios";
const apiClient = axios.create({
  baseURL:
    import.meta.env.VITE_API_BASE_URL ||
    import.meta.env.VITE_API_URL ||
    "http://127.0.0.1:8000/api/v1",
  headers: { Accept: "application/json", "Content-Type": "application/json" },
  timeout: 20000,
});
apiClient.interceptors.request.use((config) => {
  if (
    String(config.baseURL).endsWith("/v1") &&
    String(config.url).startsWith("/v1/")
  )
    config.url = String(config.url).slice(3);
  const token = sessionStorage.getItem("auth_token");
  if (token) config.headers.Authorization = `Bearer ${token}`;
  return config;
});
apiClient.interceptors.response.use(
  (r) => r,
  (error) => {
    const e = error;
    if (
      e.response?.status === 401 &&
      !String(e.config?.url).endsWith("/login")
    ) {
      sessionStorage.removeItem("auth_token");
      window.dispatchEvent(new Event("auth-expired"));
    }
    return Promise.reject(normalizeApiError(e));
  },
);
export function normalizeApiError(error) {
  const status = error.response?.status ?? null;
  const data = error.response?.data;
  const kind =
    status === 422
      ? "validation"
      : status === 401
        ? "unauthenticated"
        : status === 403
          ? "forbidden"
          : status === 404
            ? "not-found"
            : status === 409
              ? "conflict"
              : !error.response
                ? "network"
                : "server";
  return {
    status,
    message:
      typeof data?.message === "string"
        ? data.message
        : kind === "network"
          ? "Cannot connect to the server."
          : "The request could not be completed.",
    fieldErrors: data?.errors || {},
    kind,
  };
}
export function cancellableConfig(signal) {
  return { signal };
}
export default apiClient;
