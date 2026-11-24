import { tasksApi } from "../../../api/endpoints";

export const createTask = tasksApi.create;

export function listTasks(projectId, params, signal) {
  return projectId
    ? tasksApi.projectList(projectId, params, signal)
    : tasksApi.list(params, signal);
}
