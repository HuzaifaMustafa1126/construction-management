import { useQuery } from "@tanstack/react-query";
import { useAuth } from "../../auth/context/AuthContext";
import { projectsApi } from "../../projects/api/projectsApi";
import { useProjectKeys } from "../../projects/hooks/useProjects";

export function useDashboardActiveProjects(projectId, page) {
  const { user, can } = useAuth();
  const keys = useProjectKeys();
  const allowed = can("projects.view");
  const query = useQuery({
    queryKey: [...keys.root, "dashboard-active", String(projectId || "all"), page],
    queryFn: async ({ signal }) => {
      if (projectId) {
        const project = await projectsApi.get(projectId, signal);
        const active = project.status === "active";
        return {
          data: active ? [project] : [],
          selectedInactive: !active,
          meta: { current_page: 1, last_page: 1, per_page: 5, total: active ? 1 : 0 },
        };
      }
      return projectsApi.list({ status: "active", page, per_page: 5, sort: "code", direction: "asc" }, signal);
    },
    enabled: allowed && !!user?.id && !!user?.company?.id,
    retry: false,
  });
  return { ...query, allowed };
}
