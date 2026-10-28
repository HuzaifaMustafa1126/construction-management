import { useQuery } from "@tanstack/react-query";
import { dailyReportsApi } from "../api/dailyReportsApi";
import { useDailyReportKeys } from "./useDailyReports";

export function useDailyReportDetails(projectId, reportId) {
  const keys = useDailyReportKeys();
  return useQuery({ queryKey: keys.detail(projectId, reportId), queryFn: () => dailyReportsApi.get(projectId, reportId), enabled: !!projectId && !!reportId, retry: false });
}
