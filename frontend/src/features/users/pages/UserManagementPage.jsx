import { useState } from "react";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { rolesApi } from "../../../api/endpoints";
import { useAuth, useCompany } from "../../auth/context/AuthContext";
import InviteStaffModal from "../components/InviteStaffModal";
import PendingInvitations from "../components/PendingInvitations";
import UsersPage from "./UsersPage";

export default function UserManagementPage() {
  const { can } = useAuth();
  const { company } = useCompany();
  const queryClient = useQueryClient();
  const [inviting, setInviting] = useState(false);
  const allowed = can("users.create") && can("roles.assign");
  const roles = useQuery({
    queryKey: ["company", company?.id, "roles", "invitation-options"],
    queryFn: () => rolesApi.list(),
    enabled: !!company && allowed,
  });
  const roleRows = Array.isArray(roles.data) ? roles.data : roles.data?.data || [];
  const invitationKey = ["company", company?.id, "staff-invitations"];

  return <>
    <UsersPage />
    {allowed && <>
      <PendingInvitations companyId={company?.id} onInvite={() => setInviting(true)} />
      <InviteStaffModal open={inviting} roles={roleRows} onClose={() => setInviting(false)} onCreated={() => {
        setInviting(false);
        queryClient.invalidateQueries({ queryKey: invitationKey });
      }} />
    </>}
  </>;
}
