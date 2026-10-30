/* eslint-disable react/prop-types */
import { useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { apiMessage, staffInvitationsApi } from "../../../api/endpoints";
import { AppAlert, AppBadge, AppButton, ConfirmDialog, SectionCard } from "../../../components/ui/AppUI";
import { toast } from "react-toastify";

const date = (value) => value ? new Date(value).toLocaleString() : "—";

export default function PendingInvitations({ companyId, onInvite }) {
  const queryClient = useQueryClient();
  const [cancelling, setCancelling] = useState(null);
  const key = ["company", companyId, "staff-invitations"];
  const query = useQuery({ queryKey: key, queryFn: staffInvitationsApi.list, enabled: !!companyId });
  const refresh = () => queryClient.invalidateQueries({ queryKey: key });
  const resend = useMutation({ mutationFn: staffInvitationsApi.resend, onSuccess: () => { toast.success("Invitation resent."); refresh(); }, onError: (error) => toast.error(apiMessage(error)) });
  const cancel = useMutation({ mutationFn: staffInvitationsApi.cancel, onSuccess: () => { toast.success("Invitation cancelled."); setCancelling(null); refresh(); }, onError: (error) => toast.error(apiMessage(error)) });
  const rows = Array.isArray(query.data) ? query.data : query.data?.data || [];

  return <SectionCard title="Staff invitations" action={<div className="button-group"><AppButton onClick={onInvite}>Invite Staff</AppButton><AppButton variant="secondary" onClick={() => query.refetch()}>Refresh</AppButton></div>}>
    {query.error && <AppAlert tone="danger">{apiMessage(query.error, "Invitations could not be loaded.")}</AppAlert>}
    {query.isLoading ? <div role="status">Loading invitations…</div> : rows.length === 0 ? <p>No staff invitations yet.</p> :
      <div className="table-container"><table className="data-table"><thead><tr><th>Email</th><th>Roles</th><th>Invited by</th><th>Sent</th><th>Expires</th><th>Status</th><th>Actions</th></tr></thead><tbody>
        {rows.map((invitation) => <tr key={invitation.id}><td>{invitation.email}</td><td>{(invitation.roles || []).join(", ")}</td><td>{invitation.invited_by?.name || "Former user"}</td><td>{date(invitation.last_sent_at || invitation.created_at)}</td><td>{date(invitation.expires_at)}</td><td><AppBadge tone={invitation.status === "pending" ? "warning" : invitation.status === "accepted" ? "success" : "neutral"}>{invitation.status}</AppBadge></td><td><div className="table-actions">
          {["pending", "expired"].includes(invitation.status) && <AppButton variant="ghost" busy={resend.isPending} onClick={() => resend.mutate(invitation.id)}>Resend</AppButton>}
          {invitation.status === "pending" && <AppButton variant="danger" onClick={() => setCancelling(invitation)}>Cancel</AppButton>}
        </div></td></tr>)}
      </tbody></table></div>}
    <ConfirmDialog open={!!cancelling} title="Cancel invitation?" message={`Cancel the invitation for ${cancelling?.email || "this recipient"}? Its link will stop working.`} confirmLabel="Cancel invitation" busy={cancel.isPending} onClose={() => setCancelling(null)} onConfirm={() => cancel.mutate(cancelling.id)} />
  </SectionCard>;
}
