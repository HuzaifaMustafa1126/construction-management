import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useAuth } from "../../auth/context/AuthContext";
import {
  createTask,
  deleteTask,
  getTask,
  listTasks,
  updateTask,
  updateTaskStatus,
} from "../api/tasksApi";
import { useProjectKeys } from "../../projects/hooks/useProjects";

export function useTasks(projectId, filters, enabled) {
  const { user, can } = useAuth();
  const params = {
    page: filters.page,
    per_page: 15,
    sort: filters.sort,
    direction: filters.direction,
  };
  for (const field of [
    "search",
    "status",
    "priority",
    "date_from",
    "date_to",
  ]) {
    if (filters[field]) params[field] = filters[field];
  }
  if (!projectId && filters.project_id) params.project_id = filters.project_id;

  return useQuery({
    queryKey: [
      "tasks",
      user?.company?.id,
      user?.id,
      "list",
      projectId ? ["project", String(projectId)] : ["global"],
      params,
    ],
    queryFn: ({ signal }) => listTasks(projectId, params, signal),
    enabled: Boolean(
      enabled && user?.company?.id && user?.id && can("tasks.view"),
    ),
    networkMode: "always",
    retry: false,
  });
}

const detailKey = (user, taskId) => [
  "tasks",
  user?.company?.id,
  user?.id,
  "detail",
  String(taskId),
];

export function useTaskDetail(taskId) {
  const { user, can } = useAuth();
  return useQuery({
    queryKey: detailKey(user, taskId),
    queryFn: ({ signal }) => getTask(taskId, signal),
    enabled: Boolean(
      taskId && user?.company?.id && user?.id && can("tasks.view"),
    ),
    refetchOnMount: "always",
    refetchOnWindowFocus: false,
    refetchOnReconnect: false,
    networkMode: "always",
    retry: false,
  });
}

function useTaskMutation(mutationFn, { remove = false } = {}) {
  const { user } = useAuth();
  const client = useQueryClient();
  const projects = useProjectKeys();
  return useMutation({
    mutationFn,
    networkMode: "always",
    retry: false,
    onSuccess: (_, { projectId, taskId, payload }) => {
      const id = String(projectId);
      const tasks = ["tasks", user.company.id, user.id, "list"];
      const dashboard = [
        "dashboard",
        user.company.id,
        user.id,
        "progress-preview",
      ];
      const keys = [
        [...tasks, ["global"]],
        [...tasks, ["project", id]],
      ];
      if (taskId) {
        const queryKey = detailKey(user, taskId);
        if (remove) client.removeQueries({ queryKey, exact: true });
        else keys.push(queryKey);
      }
      if (
        !taskId ||
        remove ||
        ["phase_id", "status", "progress_percentage"].some((key) =>
          Object.hasOwn(payload, key),
        )
      ) {
        keys.push(
          projects.detail(id),
          [...projects.root, "phases", id],
          [...projects.root, "list"],
          [...projects.root, "dashboard-active", id],
          [...projects.root, "dashboard-active", "all"],
          [...dashboard, id],
          [...dashboard, "all"],
        );
      }
      for (const queryKey of keys) void client.invalidateQueries({ queryKey });
    },
  });
}

export function useCreateTask() {
  return useTaskMutation(({ projectId, payload }) =>
    createTask(projectId, payload),
  );
}

export function useUpdateTask() {
  return useTaskMutation(({ taskId, payload }) => updateTask(taskId, payload));
}

export function useUpdateTaskStatus() {
  return useTaskMutation(({ taskId, payload }) =>
    updateTaskStatus(taskId, payload.status),
  );
}

export function useDeleteTask() {
  return useTaskMutation(({ taskId }) => deleteTask(taskId), { remove: true });
}
