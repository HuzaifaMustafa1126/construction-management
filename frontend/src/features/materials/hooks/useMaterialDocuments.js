import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useAuth } from "../../auth/context/AuthContext";

export function useMaterialDocumentKeys(kind) {
  const { user } = useAuth();
  const root = ["material-documents", user?.company?.id, user?.id, kind];
  return { root, list: (projectId, filters) => [...root, "project", String(projectId), "list", filters], detail: (projectId, id) => [...root, "project", String(projectId), "detail", String(id)] };
}
export function useMaterialDocuments(kind, api, projectId, filters) { const keys = useMaterialDocumentKeys(kind); return useQuery({ queryKey: keys.list(projectId, filters), queryFn: () => api.list(projectId, filters), enabled: Boolean(projectId), retry: false }); }
export function useMaterialDocument(kind, api, projectId, id) { const keys = useMaterialDocumentKeys(kind); return useQuery({ queryKey: keys.detail(projectId, id), queryFn: () => api.get(projectId, id), enabled: Boolean(projectId && id), retry: false }); }
export function useMaterialDocumentMutation(kind, action) { const client = useQueryClient(); const keys = useMaterialDocumentKeys(kind); return useMutation({ mutationFn: action, onSuccess: () => { client.invalidateQueries({ queryKey: keys.root }); client.invalidateQueries({ queryKey: ["inventory"] }); client.invalidateQueries({ queryKey: ["inventory-ledger"] }); client.invalidateQueries({ queryKey: ["projects"] }); client.invalidateQueries({ queryKey: ["dashboard"] }); } }); }
export function useLedger(projectId, filters) { const { user } = useAuth(); return useQuery({ queryKey: ["inventory-ledger", user?.company?.id, user?.id, String(projectId), filters], queryFn: async () => (await import("../api/ledgerApi")).ledgerApi.movements(projectId, filters), enabled: Boolean(projectId), retry: false }); }
export function useReconciliation(projectId, page = 1) { const { user } = useAuth(); return useQuery({ queryKey: ["inventory-reconciliation", user?.company?.id, user?.id, String(projectId), page], queryFn: async () => (await import("../api/ledgerApi")).ledgerApi.reconciliation(projectId, { page, per_page: 100 }), enabled: Boolean(projectId), retry: false }); }
