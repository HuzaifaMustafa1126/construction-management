import { useMemo, useState } from "react";
import { Link, useNavigate } from "react-router-dom";
import { toast } from "react-toastify";
import { DataTable } from "../../../components/tables/DataTable";
import {
  AppButton,
  AppIcon,
  ConfirmDialog,
  PageHeader,
  SectionCard,
} from "../../../components/ui/AppUI";
import {
  AppAlert,
  FilterBar,
  IconButton,
} from "../../../components/ui/Controls";
import { apiMessage } from "../../../api/endpoints";
import { useAuth } from "../../auth/context/AuthContext";
import { clientsApi } from "../api/clientsApi";
import { useClientMutation, useClients } from "../hooks/useClients";

export default function ClientsPage() {
  const { can } = useAuth();
  const navigate = useNavigate();
  const [filters, setFilters] = useState({
    search: "",
    page: 1,
    per_page: 15,
    sort: "name",
    direction: "asc",
  });
  const [draft, setDraft] = useState("");
  const [deleting, setDeleting] = useState(null);
  const query = useClients(filters);
  const remove = useClientMutation((id) => clientsApi.remove(id));
  const columns = useMemo(
    () => [
      {
        accessorKey: "name",
        header: "Client",
        cell: ({ row }) => (
          <span>
            <strong>{row.original.name}</strong>
            <small className="table-subtitle">
              {row.original.company_name || "—"}
            </small>
          </span>
        ),
      },
      {
        accessorKey: "email",
        header: "Email",
        cell: ({ getValue }) => getValue() || "—",
      },
      {
        accessorKey: "phone",
        header: "Phone",
        cell: ({ getValue }) => getValue() || "—",
      },
    ],
    [],
  );
  const confirmDelete = async () => {
    try {
      await remove.mutateAsync(deleting.id);
      toast.success("Client deleted.");
      setDeleting(null);
    } catch (error) {
      toast.error(apiMessage(error, "The client could not be deleted."));
    }
  };
  const actions = (client) => (
    <div className="table-actions">
      <IconButton
        icon="view"
        label={`View ${client.name}`}
        onClick={() => navigate(`/clients/${client.id}`)}
      />
      {can("clients.update") && (
        <IconButton
          icon="edit"
          label={`Edit ${client.name}`}
          onClick={() => navigate(`/clients/${client.id}/edit`)}
        />
      )}
      {can("clients.delete") && (
        <IconButton
          icon="delete"
          label={`Delete ${client.name}`}
          onClick={() => setDeleting(client)}
        />
      )}
    </div>
  );
  const rows = query.data?.data || [];
  const meta = query.data?.meta;

  return (
    <div className="phase2-page">
      <PageHeader
        title="Clients"
        description="Manage the business clients linked to construction projects."
        action={
          can("clients.create") ? (
            <Link className="app-button primary" to="/clients/new">
              <AppIcon name="add" />
              Add client
            </Link>
          ) : null
        }
      />
      <SectionCard
        title="Client directory"
        action={
          <AppButton variant="secondary" onClick={() => query.refetch()}>
            Refresh
          </AppButton>
        }
      >
        <form
          onSubmit={(event) => {
            event.preventDefault();
            setFilters((current) => ({ ...current, search: draft, page: 1 }));
          }}
        >
          <FilterBar>
            <label className="table-search">
              <AppIcon name="search" />
              <span className="sr-only">Search clients</span>
              <input
                value={draft}
                onChange={(event) => setDraft(event.target.value)}
                placeholder="Search clients…"
              />
            </label>
            <AppButton type="submit">Search</AppButton>
          </FilterBar>
        </form>
        {query.error && (
          <AppAlert tone="danger">
            {apiMessage(query.error, "Clients could not be loaded.")}
          </AppAlert>
        )}
        <DataTable
          data={rows}
          columns={columns}
          actions={actions}
          loading={query.isLoading}
          searchable={false}
          sortable={false}
          paginated={false}
          emptyTitle="No clients found"
        />
        {meta && (
          <div className="server-pagination">
            <span>
              Page {meta.current_page} of {meta.last_page} · {meta.total}{" "}
              clients
            </span>
            <div>
              <AppButton
                variant="secondary"
                disabled={meta.current_page <= 1}
                onClick={() =>
                  setFilters((current) => ({
                    ...current,
                    page: current.page - 1,
                  }))
                }
              >
                Previous
              </AppButton>
              <AppButton
                variant="secondary"
                disabled={meta.current_page >= meta.last_page}
                onClick={() =>
                  setFilters((current) => ({
                    ...current,
                    page: current.page + 1,
                  }))
                }
              >
                Next
              </AppButton>
            </div>
          </div>
        )}
      </SectionCard>
      <ConfirmDialog
        open={!!deleting}
        title="Delete client"
        message={`Delete ${deleting?.name}? This is only allowed when no active project is linked.`}
        busy={remove.isPending}
        onClose={() => setDeleting(null)}
        onConfirm={confirmDelete}
      />
    </div>
  );
}
