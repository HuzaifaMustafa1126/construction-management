import { useNavigate, useParams } from "react-router-dom";
import { toast } from "react-toastify";
import { PageHeader } from "../../../components/ui/AppUI";
import { AppAlert } from "../../../components/ui/Controls";
import { apiMessage } from "../../../api/endpoints";
import { useAuth } from "../../auth/context/AuthContext";
import ClientForm from "../components/ClientForm";
import { clientsApi } from "../api/clientsApi";
import { useClient, useClientMutation, useClientAccountOptions } from "../hooks/useClients";

import "./clients.css";

export function CreateClientPage() {
  const navigate = useNavigate();
  const { can } = useAuth();
  const mutation = useClientMutation(clientsApi.create);
  const options = useClientAccountOptions();
  const authorized = can("clients.create") && can("users.create") && can("roles.assign");
  return (
    <div className="phase2-page clients-workspace clients-form-page">
      <PageHeader title="Add Client" description="Create a business client and linked login account together." />
      {!authorized ? <AppAlert tone="danger">Creating a client and account requires clients.create, users.create and roles.assign, plus authority to grant the company client role.</AppAlert> : <>
        {options.isLoading && <div role="status">Checking account creation availability...</div>}
        {options.error && <AppAlert tone="danger">{apiMessage(options.error, "Account creation availability could not be checked.")}</AppAlert>}
        {options.data?.reason && <AppAlert tone="warning">{options.data.reason} Saving is unavailable until this prerequisite is resolved.</AppAlert>}
        <ClientForm
          createAccount
          disabled={!options.data?.can_create || options.isFetching || !!options.error}
          submitLabel="Create Client & Account"
          onCancel={() => navigate("/clients")}
          onSubmit={async (payload) => {
            const client = await mutation.mutateAsync(payload);
            toast.success("Client and login account created.");
            navigate(`/clients/${client.id}`);
          }}
        />
      </>}
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
    <div className="phase2-page clients-workspace clients-form-page">
      <PageHeader
        title={`Edit ${query.data.name}`}
        description="Update client contact and business details."
      />
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
    </div>
  );
}
