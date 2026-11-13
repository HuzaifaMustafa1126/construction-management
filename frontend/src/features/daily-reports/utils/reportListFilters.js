import { REPORT_STATUSES } from "../schemas/dailyReportSchema";

export const currentReportMonth = () => {
  const now = new Date();
  const prefix = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, "0")}`;
  return { date_from: `${prefix}-01`, date_to: `${prefix}-${new Date(now.getFullYear(), now.getMonth() + 1, 0).getDate()}` };
};
export const validReportDate = (value) => {
  if (!/^\d{4}-\d{2}-\d{2}$/.test(value || "")) return false;
  const date = new Date(`${value}T00:00:00`);
  return Number.isFinite(date.getTime()) && `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, "0")}-${String(date.getDate()).padStart(2, "0")}` === value;
};
export const reportRangeError = (filters) => {
  if (filters.period !== "custom") return "";
  if (!validReportDate(filters.date_from) || !validReportDate(filters.date_to)) return "Select a valid start and end date.";
  return filters.date_from > filters.date_to ? "Start date must be on or before the end date." : "";
};
export function readReportListFilters(params) {
  const project = params.get("project") || "";
  const from = params.get("date_from") || "";
  const to = params.get("date_to") || "";
  const rangeValid = validReportDate(from) && validReportDate(to) && from <= to;
  let period = params.get("period") || (rangeValid ? "custom" : "all");
  if (!["all", "month", "custom"].includes(period) || (period === "custom" && !rangeValid)) period = "all";
  const dates = period === "month" ? currentReportMonth() : period === "custom" ? { date_from: from, date_to: to } : { date_from: "", date_to: "" };
  const page = Number(params.get("page") || 1);
  return {
    project_id: /^[1-9]\d*$/.test(project) ? project : "",
    search: (params.get("search") || "").trim().slice(0, 255),
    status: REPORT_STATUSES.includes(params.get("status")) ? params.get("status") : "",
    period, ...dates, page: Number.isSafeInteger(page) && page > 0 ? page : 1, per_page: 15,
  };
}
export function buildReportListSearch(filters) {
  const params = new URLSearchParams();
  if (filters.project_id) params.set("project", String(filters.project_id));
  const period = filters.period || "all";
  params.set("period", period);
  if (period !== "all") {
    if (filters.date_from) params.set("date_from", filters.date_from);
    if (filters.date_to) params.set("date_to", filters.date_to);
  }
  if (filters.search) params.set("search", filters.search);
  if (filters.status) params.set("status", filters.status);
  if (filters.page > 1) params.set("page", String(filters.page));
  return params;
}
