import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useAuth } from "../../auth/context/AuthContext";
import { clientsApi } from "../api/clientsApi";

export function useClientKeys() {
  const { user } = useAuth();
  const root = ["clients", user?.company?.id, user?.id];
  return {
    root,
    list: (params) => [...root, "list", params],
    detail: (id) => [...root, "detail", String(id)],
    lookup: (params) => [...root, "lookup", params],
  };
}

export function useClients(params) {
  const keys = useClientKeys();
  return useQuery({
    queryKey: keys.list(params),
    queryFn: () => clientsApi.list(params),
  });
}

export function useClient(id) {
  const keys = useClientKeys();
  return useQuery({
    queryKey: keys.detail(id),
    queryFn: () => clientsApi.get(id),
    enabled: !!id,
  });
}

export function useClientLookup(params, enabled = true) {
  const keys = useClientKeys();
  return useQuery({
    queryKey: keys.lookup(params),
    queryFn: () => clientsApi.lookup(params),
    enabled,
    staleTime: 30000,
  });
}

export function useClientMutation(action) {
  const queryClient = useQueryClient();
  const keys = useClientKeys();
  return useMutation({
    mutationFn: action,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: keys.root });
      queryClient.invalidateQueries({ queryKey: ["projects"] });
      queryClient.invalidateQueries({ queryKey: ["dashboard"] });
    },
  });
}
