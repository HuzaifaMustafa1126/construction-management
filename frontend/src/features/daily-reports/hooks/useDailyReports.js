import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useAuth } from "../../auth/context/AuthContext";
import { dailyReportsApi } from "../api/dailyReportsApi";

export function useDailyReportKeys() {
  const { user } = useAuth();
  const root = ["daily-reports", user?.company?.id, user?.id];
  return {
    root,
    project: (projectId) => [...root, "project", String(projectId)],
    list: (projectId, params) => [...root, "project", String(projectId), "list", params],
    detail: (projectId, reportId) => [...root, "project", String(projectId), "detail", String(reportId)],
  };
}

export function useDailyReports(projectId, params, enabled = true) {
  const keys = useDailyReportKeys();
  return useQuery({ queryKey: keys.list(projectId, params), queryFn: () => dailyReportsApi.list(projectId, params), enabled: !!projectId && enabled, retry: false });
}

export function useDailyReportMutation(action) {
  const client = useQueryClient();
  const keys = useDailyReportKeys();
  return useMutation({ mutationFn: action, onSuccess: (report) => {
    client.invalidateQueries({ queryKey: keys.root });
    client.invalidateQueries({ queryKey: ["projects"] });
    client.invalidateQueries({ queryKey: ["dashboard"] });
    if (report?.id && report?.project?.id) client.setQueryData(keys.detail(report.project.id, report.id), report);
  } });
}
