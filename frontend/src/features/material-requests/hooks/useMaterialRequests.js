import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useAuth } from "../../auth/context/AuthContext";
import { materialRequestsApi } from "../api/materialRequestsApi";

export function useMaterialRequestKeys() {
  const { user } = useAuth();
  const root = ["material-requests", user?.company?.id, user?.id];
  return { root, list: (filters) => [...root, "list", filters], detail: (id) => [...root, "detail", String(id)] };
}

export function useMaterialRequests(filters) {
  const keys = useMaterialRequestKeys();
  return useQuery({ queryKey: keys.list(filters), queryFn: () => materialRequestsApi.list(filters), retry: false });
}

export function useMaterialRequest(id) {
  const keys = useMaterialRequestKeys();
  return useQuery({ queryKey: keys.detail(id), queryFn: () => materialRequestsApi.get(id), enabled: Boolean(id), retry: false });
}

export function useMaterialRequestMutation(action) {
  const client = useQueryClient();
  const keys = useMaterialRequestKeys();
  return useMutation({
    mutationFn: action,
    onSuccess: () => {
      client.invalidateQueries({ queryKey: keys.root });
      client.invalidateQueries({ queryKey: ["inventory"] });
      client.invalidateQueries({ queryKey: ["projects"] });
      client.invalidateQueries({ queryKey: ["dashboard"] });
    },
  });
}
