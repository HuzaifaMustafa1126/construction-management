import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { projectsApi } from "../api/projectsApi";
import { useAuth } from "../../auth/context/AuthContext";

export function useProjectKeys() {
  const { user } = useAuth();
  return { root: ["projects", user?.company?.id, user?.id], list: (params) => ["projects", user?.company?.id, user?.id, "list", params], detail: (id) => ["projects", user?.company?.id, user?.id, "detail", String(id)], team: (id) => ["projects", user?.company?.id, user?.id, "team", String(id)] };
}
export function useProjects(params) { const keys = useProjectKeys(); return useQuery({ queryKey: keys.list(params), queryFn: () => projectsApi.list(params) }); }
export function useProject(id) { const keys = useProjectKeys(); return useQuery({ queryKey: keys.detail(id), queryFn: () => projectsApi.get(id), enabled: !!id }); }
export function useProjectTeam(id, enabled = true) { const keys = useProjectKeys(); return useQuery({ queryKey: keys.team(id), queryFn: () => projectsApi.team(id), enabled: !!id && enabled, retry: false }); }
export function useProjectMutation(action) {
  const client = useQueryClient(); const keys = useProjectKeys();
  return useMutation({ mutationFn: action, onSuccess: (project) => { client.invalidateQueries({ queryKey: keys.root }); client.invalidateQueries({ queryKey: ["dashboard"] }); if (project?.id) client.setQueryData(keys.detail(project.id), project); } });
}
