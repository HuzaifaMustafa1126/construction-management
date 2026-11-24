import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useAuth } from "../../auth/context/AuthContext";
import { createTask, listTasks } from "../api/tasksApi";
import { useProjectKeys } from "../../projects/hooks/useProjects";

export function useTasks(projectId, filters, enabled) {
  const { user, can } = useAuth();
  const params = {
    page: filters.page,
    per_page: 15,
    sort: filters.sort,
    direction: filters.direction,
  };
  for (const field of ["search", "status", "priority", "date_from", "date_to"]) {
    if (filters[field]) params[field] = filters[field];
  }
  if (!projectId && filters.project_id) params.project_id = filters.project_id;

  return useQuery({
    queryKey: [
      "tasks", user?.company?.id, user?.id, "list",
      projectId ? ["project", String(projectId)] : ["global"], params,
    ],
    queryFn: ({ signal }) => listTasks(projectId, params, signal),
    enabled: Boolean(enabled && user?.company?.id && user?.id && can("tasks.view")),
    networkMode: "always",
    retry: false,
  });
}

export function useCreateTask() {
  const { user } = useAuth();
  const client = useQueryClient();
  const projects = useProjectKeys();
  return useMutation({
    mutationFn: ({ projectId, payload }) => createTask(projectId, payload),
    networkMode: "always",
    retry: false,
    onSuccess: (_, { projectId }) => {
      const id = String(projectId);
      const tasks = ["tasks", user.company.id, user.id, "list"];
      const dashboard = ["dashboard", user.company.id, user.id, "progress-preview"];
      const keys = [
        [...tasks, ["global"]], [...tasks, ["project", id]],
        projects.detail(id), [...projects.root, "phases", id],
        [...projects.root, "list"],
        [...projects.root, "dashboard-active", id],
        [...projects.root, "dashboard-active", "all"],
        [...dashboard, id], [...dashboard, "all"],
      ];
      for (const queryKey of keys) void client.invalidateQueries({ queryKey });
    },
  });
}
