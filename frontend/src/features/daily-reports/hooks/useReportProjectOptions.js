import { useEffect, useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { useAuth } from "../../auth/context/AuthContext";
import { useProjectKeys } from "../../projects/hooks/useProjects";
import { projectsApi } from "../../projects/api/projectsApi";

export function useReportProjectOptions(projectId) {
  const { user, can } = useAuth();
  const keys = useProjectKeys();
  const [search, setSearch] = useState("");
  const [debounced, setDebounced] = useState("");
  const term = search.trim();
  useEffect(() => { const timer = setTimeout(() => setDebounced(term), 250); return () => clearTimeout(timer); }, [term]);
  const enabled = can("projects.view") && !!user?.id && !!user?.company?.id;
  const query = useQuery({
    queryKey: [...keys.root, "report-filter-options", debounced],
    queryFn: ({ signal }) => projectsApi.list({ search: debounced, page: 1, per_page: 50, sort: "name", direction: "asc" }, signal),
    enabled, retry: false,
  });
  const selected = useQuery({
    queryKey: keys.detail(projectId),
    queryFn: ({ signal }) => projectsApi.get(projectId, signal),
    enabled: enabled && !!projectId, retry: false,
  });
  return { query, selected, enabled, setSearch, pending: term !== debounced };
}
