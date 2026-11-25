import { tasksApi } from "../../../api/endpoints";

export const createTask = tasksApi.create;
export const getTask = tasksApi.get;
export const updateTask = tasksApi.update;
export const updateTaskStatus = tasksApi.status;
export const deleteTask = tasksApi.remove;

export function listTasks(projectId, params, signal) {
  return projectId
    ? tasksApi.projectList(projectId, params, signal)
    : tasksApi.list(params, signal);
}
