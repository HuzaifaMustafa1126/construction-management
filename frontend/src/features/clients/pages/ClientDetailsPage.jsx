import { useState } from "react";
import { Link, useParams } from "react-router-dom";
import { AppButton, AppBadge, PageHeader, SectionCard } from "../../../components/ui/AppUI";
import { AppAlert, StatCard } from "../../../components/ui/Controls";
import { apiMessage } from "../../../api/endpoints";
import { useAuth } from "../../auth/context/AuthContext";
import { useClient, useClientAccountOptions } from "../hooks/useClients";

import ClientLoginAccountDialog from "../components/ClientLoginAccountDialog";
import "./clients.css";

export default function ClientDetailsPage() {
  const { id } = useParams();
  const { can } = useAuth();
  const query = useClient(id);
  const options = useClientAccountOptions();
  const [linking, setLinking] = useState(false);
  if (query.isLoading) return <div role="status">Loading client…</div>;
  if (query.error)
    return (
      <AppAlert tone="danger">
        {apiMessage(query.error, "Client not found or unavailable.")}
      </AppAlert>
    );
  const client = query.data;
  return (
    <div className="phase2-page clients-workspace">
      <PageHeader
        title={client.name}
        description={client.company_name || "Client record"}
        action={
          <div className="button-group">
            <Link className="app-button secondary" to="/clients">
              Back
            </Link>
            {can("clients.update") && (
              <Link className="app-button primary" to={`/clients/${id}/edit`}>
                Edit client
              </Link>
            )}
          </div>
        }
      />
      <div className="project-stats">
        <StatCard label="Active projects" value={client.projects_count ?? 0} />
        <StatCard label="Email" value={client.email || "Not set"} />
        <StatCard label="Phone" value={client.phone || "Not set"} />
      </div>
      <SectionCard title="Login account">
        <AppBadge>{client.has_login_account == null ? "Link status unavailable" : client.has_login_account ? "Linked account" : "No login account"}</AppBadge>
        {can("users.view") && client.account && <p>{client.account.email} | {client.account.deleted ? "Deleted account" : client.account.status}</p>}
        <p className="field-description">Contact email changes do not change login email. Manage account changes in Users. Project membership remains separate.</p>
        {can("clients.update") && can("users.create") && can("roles.assign") && options.data?.reason && <AppAlert tone="warning">{options.data.reason}</AppAlert>}
        {client.has_login_account !== true && can("clients.update") && can("users.create") && can("roles.assign") && <AppButton disabled={!options.data?.can_link} onClick={() => setLinking(true)}>Create login account</AppButton>}
        {client.has_login_account && can("users.view") && <Link className="app-button secondary" to="/users">Manage in Users</Link>}
      </SectionCard>
      {linking && <ClientLoginAccountDialog client={client} onClose={() => setLinking(false)} />}
      <SectionCard title="Client details">
        <dl className="project-overview">
          <div>
            <dt>Company</dt>
            <dd>{client.company_name || "Not set"}</dd>
          </div>
          <div>
            <dt>Address</dt>
            <dd>
              {[client.address, client.city, client.country]
                .filter(Boolean)
                .join(", ") || "Not set"}
            </dd>
          </div>
          <div>
            <dt>Notes</dt>
            <dd>{client.notes || "No notes"}</dd>
          </div>
        </dl>
      </SectionCard>
    </div>
  );
}
