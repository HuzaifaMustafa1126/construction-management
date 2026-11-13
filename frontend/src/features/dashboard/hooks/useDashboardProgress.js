import { useQuery } from "@tanstack/react-query";
import { projectsApi } from "../../../api/endpoints";
import { useAuth, useCompany } from "../../auth/context/AuthContext";

export function useDashboardProgress(projectId) {
  const { user, can } = useAuth();
  const { company } = useCompany();
  const companyId = company?.id || user?.company?.id;
  const allowed = can("projects.view");
  const query = useQuery({
    queryKey: ["dashboard", companyId, user?.id, "progress-preview", String(projectId || "all")],
    queryFn: async () => {
      if (projectId) {
        const project = await projectsApi.get(projectId);
        return { data: [project], meta: { total: 1 } };
      }
      return projectsApi.list({ page: 1, per_page: 6, sort: "created_at", direction: "desc" });
    },
    enabled: !!companyId && !!user?.id && allowed,
    retry: false,
  });
  return { ...query, allowed };
}
