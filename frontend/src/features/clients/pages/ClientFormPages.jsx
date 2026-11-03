import { useNavigate, useParams } from "react-router-dom";
import { toast } from "react-toastify";
import { PageHeader, SectionCard } from "../../../components/ui/AppUI";
import { AppAlert } from "../../../components/ui/Controls";
import { apiMessage } from "../../../api/endpoints";
import ClientForm from "../components/ClientForm";
import { clientsApi } from "../api/clientsApi";
import { useClient, useClientMutation } from "../hooks/useClients";

export function CreateClientPage() {
  const navigate = useNavigate();
  const mutation = useClientMutation(clientsApi.create);
  return (
    <div className="phase2-page">
      <PageHeader
        title="Create client"
        description="Add a business client without creating a login account."
      />
      <SectionCard title="Client information">
        <ClientForm
          submitLabel="Create client"
          onCancel={() => navigate("/clients")}
          onSubmit={async (payload) => {
            const client = await mutation.mutateAsync(payload);
            toast.success("Client created.");
            navigate(`/clients/${client.id}`);
          }}
        />
      </SectionCard>
    </div>
  );
}

export function EditClientPage() {
  const { id } = useParams();
  const navigate = useNavigate();
  const query = useClient(id);
  const mutation = useClientMutation((payload) =>
    clientsApi.update(id, payload),
  );
  if (query.isLoading) return <div role="status">Loading client…</div>;
  if (query.error)
    return (
      <AppAlert tone="danger">
        {apiMessage(query.error, "Client not found.")}
      </AppAlert>
    );
  return (
    <div className="phase2-page">
      <PageHeader
        title={`Edit ${query.data.name}`}
        description="Update client contact and business details."
      />
      <SectionCard title="Client information">
        <ClientForm
          initialValues={query.data}
          submitLabel="Save changes"
          onCancel={() => navigate(`/clients/${id}`)}
          onSubmit={async (payload) => {
            await mutation.mutateAsync(payload);
            toast.success("Client updated.");
            navigate(`/clients/${id}`);
          }}
        />
      </SectionCard>
    </div>
  );
}
