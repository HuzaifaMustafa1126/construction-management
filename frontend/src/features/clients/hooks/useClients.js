import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useAuth } from "../../auth/context/AuthContext";
import { clientsApi } from "../api/clientsApi";

export function useClientKeys() {
  const { user, can } = useAuth();
  const root = ["clients", user?.company?.id, user?.id];
  return {
    root,
    list: (params) => [...root, "list", can("users.view"), params],
    detail: (id) => [...root, "detail", String(id), can("users.view")],
    lookup: (params) => [...root, "lookup", params],
  };
}

export function useClients(params) {
  const keys = useClientKeys();
  const { can } = useAuth();
  return useQuery({
    queryKey: keys.list(params),
    queryFn: ({ signal }) => clientsApi.list(params, { signal }),
    enabled: can("clients.view"),
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
    queryFn: ({ signal }) => clientsApi.lookup(params, { signal }),
    enabled,
    staleTime: 30000,
  });
}

export function useClientMutation(action) {
  const queryClient = useQueryClient();
  const keys = useClientKeys();
  const { user } = useAuth();
  return useMutation({
    mutationFn: action,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: keys.root });
      queryClient.invalidateQueries({ queryKey: ["company", user?.company?.id, "users"] });
      queryClient.invalidateQueries({ queryKey: ["projects"] });
      queryClient.invalidateQueries({ queryKey: ["dashboard"] });
    },
  });
}

export function useClientDirectory(params) {
  const { can, user } = useAuth();
  return useQuery({ queryKey: ["company", user?.company?.id, "users", "client-directory", user?.id, can("users.view"), params], queryFn: ({ signal }) => clientsApi.directory(params, { signal }), enabled: can("clients.view") });
}
export function useClientAccountOptions() {
  const keys = useClientKeys();
  const { can } = useAuth();
  return useQuery({ queryKey: [...keys.root, "account-options"], queryFn: ({ signal }) => clientsApi.accountOptions({ signal }), enabled: ["clients.view", "clients.create", "clients.update"].some((permission) => can(permission)) });
}
