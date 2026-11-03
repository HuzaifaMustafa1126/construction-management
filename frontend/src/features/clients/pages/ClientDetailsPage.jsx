import { Link, useParams } from "react-router-dom";
import { PageHeader, SectionCard } from "../../../components/ui/AppUI";
import { AppAlert, StatCard } from "../../../components/ui/Controls";
import { apiMessage } from "../../../api/endpoints";
import { useAuth } from "../../auth/context/AuthContext";
import { useClient } from "../hooks/useClients";

export default function ClientDetailsPage() {
  const { id } = useParams();
  const { can } = useAuth();
  const query = useClient(id);
  if (query.isLoading) return <div role="status">Loading client…</div>;
  if (query.error)
    return (
      <AppAlert tone="danger">
        {apiMessage(query.error, "Client not found or unavailable.")}
      </AppAlert>
    );
  const client = query.data;
  return (
    <div className="phase2-page">
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
