import { useInfiniteQuery } from "@tanstack/react-query";
import { useAuth } from "../../auth/context/AuthContext";
import { listTasks } from "../api/tasksApi";

const pageSize = 100;
const pageLimit = 3;

export function nextCalendarPage(lastPage, pages) {
  const { current_page, last_page } = lastPage.meta;
  if (pages.length < pageLimit && current_page < last_page)
    return current_page + 1;
}

export function summarizeCalendarPages(pages) {
  const tasks = [
    ...new Map(
      pages.flatMap(({ data }) => data.map((task) => [task.id, task])),
    ).values(),
  ];
  const { total, current_page, last_page } = pages.at(-1).meta;
  const more = current_page < last_page;
  return {
    tasks,
    total,
    complete:
      !more &&
      tasks.length === total &&
      pages.every(({ meta }) => meta.total === total),
    capped: more && pages.length >= pageLimit,
  };
}

export function useTaskCalendar(projectId, from, to, filters, enabled) {
  const { user, can } = useAuth();
  const params = {
    date_from: from,
    date_to: to,
    sort: "due_date",
    direction: "asc",
    per_page: pageSize,
  };
  for (const field of ["search", "status", "priority"]) {
    if (filters[field]) params[field] = filters[field];
  }
  if (!projectId && filters.project_id) params.project_id = filters.project_id;

  return useInfiniteQuery({
    queryKey: [
      "tasks",
      user?.company?.id,
      user?.id,
      "list",
      projectId ? ["project", String(projectId)] : ["global"],
      "calendar",
      params,
    ],
    queryFn: ({ pageParam, signal }) =>
      listTasks(projectId, { ...params, page: pageParam }, signal),
    initialPageParam: 1,
    getNextPageParam: nextCalendarPage,
    enabled: Boolean(
      enabled &&
      user?.company?.id &&
      user?.id &&
      can("tasks.view") &&
      (!projectId || can("projects.view")),
    ),
    networkMode: "always",
    retry: false,
  });
}
