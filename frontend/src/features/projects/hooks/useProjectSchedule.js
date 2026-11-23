import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useAuth } from "../../auth/context/AuthContext";
import { projectsApi } from "../api/projectsApi";
import { useProjectKeys } from "./useProjects";

const phaseListKey = (root, projectId) => [...root, "phases", String(projectId)];
const milestoneKey = (root, projectId) => [...root, "milestones", String(projectId)];

export function useProjectSchedule(projectId) {
  const { user, can } = useAuth();
  const { root } = useProjectKeys();
  const enabled = Boolean(
    projectId && user?.company?.id && user?.id && can("projects.view"),
  );

  const phases = useQuery({
    queryKey: phaseListKey(root, projectId),
    queryFn: ({ signal }) => projectsApi.phases(projectId, signal),
    enabled,
    retry: false,
  });
  const milestones = useQuery({
    queryKey: [...milestoneKey(root, projectId), "list"],
    queryFn: ({ signal }) => projectsApi.milestones(projectId, signal),
    enabled,
    retry: false,
  });

  return { phases, milestones };
}

function useProjectPhaseMutation(projectId, mutatePhase, options) {
  const { root } = useProjectKeys();
  const client = useQueryClient();

  return useMutation({
    ...options,
    mutationFn: (variables) => mutatePhase(projectId, variables),
    onSuccess: () =>
      client.invalidateQueries({ queryKey: phaseListKey(root, projectId), exact: true }),
  });
}

export function useCreateProjectPhase(projectId) {
  return useProjectPhaseMutation(projectId, projectsApi.createPhase);
}

export function useUpdateProjectPhase(projectId) {
  return useProjectPhaseMutation(projectId, (id, { phaseId, payload }) =>
    projectsApi.updatePhase(id, phaseId, payload),
  );
}

export function useDeleteProjectPhase(projectId) {
  return useProjectPhaseMutation(projectId, projectsApi.deletePhase);
}

export function useApplyDefaultProjectPhases(projectId) {
  return useProjectPhaseMutation(projectId, projectsApi.applyDefaultPhases, { networkMode: "always" });
}

export function useReorderProjectPhases(projectId) {
  return useProjectPhaseMutation(projectId, projectsApi.reorderPhases, { networkMode: "always" });
}

export function useProjectMilestone(projectId, milestoneId) {
  const { root } = useProjectKeys();
  const { can, user } = useAuth();
  return useQuery({
    queryKey: [...milestoneKey(root, projectId), "detail", String(milestoneId)],
    queryFn: ({ signal }) => projectsApi.milestone(projectId, milestoneId, signal),
    enabled: Boolean(projectId && milestoneId && user?.company?.id && user?.id && can("projects.view")),
    retry: false,
    staleTime: 0,
  });
}

export function useProjectMilestoneMutations(projectId) {
  const { root } = useProjectKeys();
  const client = useQueryClient();
  const key = milestoneKey(root, projectId);
  const refreshLists = () => client.invalidateQueries({ queryKey: [...key, "list"] });

  const create = useMutation({
    networkMode: "always",
    mutationFn: (payload) => projectsApi.createMilestone(projectId, payload),
    onSuccess: refreshLists,
  });
  const update = useMutation({
    networkMode: "always",
    mutationFn: ({ id, payload }) => projectsApi.updateMilestone(projectId, id, payload),
    onSuccess: (_, { id }) => Promise.all([
      refreshLists(),
      client.invalidateQueries({ queryKey: [...key, "detail", String(id)], exact: true }),
    ]),
  });
  const remove = useMutation({
    networkMode: "always",
    mutationFn: (id) => projectsApi.deleteMilestone(projectId, id),
    onSuccess: (_, id) => {
      client.removeQueries({ queryKey: [...key, "detail", String(id)], exact: true });
      return refreshLists();
    },
  });

  return { create, update, remove };
}
