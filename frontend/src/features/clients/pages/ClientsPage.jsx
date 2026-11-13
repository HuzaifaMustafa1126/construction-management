import { useNavigate } from "react-router-dom";
import { AppButton, AppIcon, PageHeader } from "../../../components/ui/AppUI";
import { AppAlert } from "../../../components/ui/Controls";
import { apiMessage } from "../../../api/endpoints";
import { useAuth } from "../../auth/context/AuthContext";
import { useClientAccountOptions } from "../hooks/useClients";
import ClientDirectory from "./ClientDirectory";
import "./clients.css";

export default function ClientsPage() {
  const { can } = useAuth();
  const navigate = useNavigate();
  const options = useClientAccountOptions();
  const basicCreate = can("clients.create") && can("users.create") && can("roles.assign");
  const provisioningActor = basicCreate || (can("clients.update") && can("users.create") && can("roles.assign"));
  if (!can("clients.view")) return <AppAlert tone="danger">You do not have permission to view clients.</AppAlert>;
  return <div className="phase2-page clients-workspace clients-directory-page">
    <PageHeader title="Clients" description="Manage clients and their login accounts." action={basicCreate ? <AppButton onClick={() => navigate("/clients/new")}><AppIcon name="add" />Add Client</AppButton> : null} />
    {provisioningActor && options.error && <AppAlert tone="danger">{apiMessage(options.error, "Account creation availability could not be checked.")}</AppAlert>}
    {provisioningActor && options.data?.reason && <AppAlert tone="warning">{options.data.reason}</AppAlert>}
    <ClientDirectory />
  </div>;
}
