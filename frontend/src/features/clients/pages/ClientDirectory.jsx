import { useEffect, useMemo, useState } from "react";
import { Link, useNavigate } from "react-router-dom";
import { toast } from "react-toastify";
import { DataTable } from "../../../components/tables/DataTable";
import { AppBadge, AppButton, AppInput, ConfirmDialog, SectionCard } from "../../../components/ui/AppUI";
import { AppAlert, FilterBar, IconButton } from "../../../components/ui/Controls";
import { apiMessage } from "../../../api/endpoints";
import { useAuth } from "../../auth/context/AuthContext";
import { clientsApi } from "../api/clientsApi";
import { useClientAccountOptions, useClientDirectory, useClientMutation } from "../hooks/useClients";
import ClientLoginAccountDialog from "../components/ClientLoginAccountDialog";

const missing = "\u2014";
const initials = (name) => {
  const words = String(name || "").trim().split(/\s+/);
  const first = Array.from(words[0]);
  return (words.length > 1
    ? (first[0] || "") + (Array.from(words[words.length - 1])[0] || "")
    : first.slice(0, 2).join("")).toLocaleUpperCase();
};
const statusTone = (account) => account?.deleted || account?.status === "Suspended"
  ? "danger" : account?.status === "Active" ? "success" : account?.status === "Inactive" ? "warning" : "neutral";

export default function ClientDirectory() {
  const { can } = useAuth();
  const navigate = useNavigate();
  const [filters, setFilters] = useState({ search: "", page: 1, per_page: 15, sort: "name", direction: "asc" });
  const [draft, setDraft] = useState("");
  const [deleting, setDeleting] = useState(null);
  const [linking, setLinking] = useState(null);
  const query = useClientDirectory(filters);
  const options = useClientAccountOptions();
  const remove = useClientMutation((id) => clientsApi.remove(id));
  const canViewAccounts = can("users.view");
  const columns = useMemo(() => [
    {
      accessorKey: "name", header: "Client",
      cell: ({ row }) => {
        const item = row.original;
        return <div className="client-directory-identity">
          <span className="client-directory-avatar" aria-hidden="true">{initials(item.name)}</span>
          <div className="client-directory-name">
            <strong>{item.name}</strong>
            {item.company_name && <small className="client-directory-secondary">{item.company_name}</small>}
            {item.type === "account_only" && <small className="client-directory-secondary">Account only</small>}
          </div>
        </div>;
      },
    },
    {
      accessorKey: "email", header: "Email",
      cell: ({ row }) => {
        const item = row.original;
        const loginEmail = canViewAccounts && item.type === "business_client" ? item.account?.email : null;
        return <div className="client-directory-email">
          <span>{item.email || missing}</span>
          {loginEmail && loginEmail !== item.email && <small className="client-directory-secondary">Login: {loginEmail}</small>}
        </div>;
      },
    },
    { accessorKey: "phone", header: "Phone", cell: ({ getValue }) => getValue() || missing },
    {
      id: "account", header: "Account",
      cell: ({ row }) => {
        const item = row.original;
        const account = canViewAccounts ? item.account : null;
        const status = account?.deleted ? "Deleted account" : account?.status || "Status unavailable";
        if (item.type === "account_only") return <div className="client-directory-account">
          <AppBadge tone={statusTone(account)}>{canViewAccounts ? status : "Account details restricted"}</AppBadge>
          <small className="client-directory-secondary">Contact not linked</small>
        </div>;
        if (item.has_login_account === false) return <AppBadge>No login account</AppBadge>;
        if (item.has_login_account !== true) return <AppBadge>Link status unavailable</AppBadge>;
        return <div className="client-directory-account">
          <span>Linked account</span>
          {account ? <AppBadge tone={statusTone(account)}>{status}</AppBadge>
            : <small className="client-directory-secondary">{canViewAccounts ? "Account details unavailable" : "Account details restricted"}</small>}
        </div>;
      },
    },
  ], [canViewAccounts]);
  const meta = query.data?.meta;
  useEffect(() => {
    if (meta && filters.page > meta.last_page) setFilters((current) => ({ ...current, page: Math.max(1, meta.last_page) }));
  }, [meta, filters.page]);
  const actions = (item) => item.type === "account_only" ? (canViewAccounts ? <Link className="app-button ghost client-directory-text-action" to="/users" aria-label={"Manage account for " + item.name}>Manage account</Link> : null) : <div className="client-directory-actions" role="group" aria-label={"Actions for " + item.name}>
    <IconButton type="button" icon="view" label={"View " + item.name} onClick={() => navigate("/clients/" + item.id)} />
    {can("clients.update") && <IconButton type="button" icon="edit" label={"Edit " + item.name} onClick={() => navigate("/clients/" + item.id + "/edit")} />}
    {can("clients.delete") && <IconButton type="button" className="icon-button client-directory-delete" icon="delete" label={"Delete " + item.name} onClick={() => setDeleting(item)} />}
    {item.has_login_account !== true && can("clients.update") && can("users.create") && can("roles.assign") && <AppButton variant="ghost" className="client-directory-text-action" aria-label={"Create login account for " + item.name} disabled={!options.data?.can_link} onClick={() => setLinking(item)}>Create login account</AppButton>}
  </div>;
  const confirmDelete = async () => {
    try { await remove.mutateAsync(deleting.id); toast.success("Client deleted. Its login account was not deleted or disabled."); setDeleting(null); }
    catch (error) { toast.error(apiMessage(error, "The client could not be deleted.")); }
  };
  return <div className="client-directory">
    <SectionCard title="Client directory" action={<AppButton variant="secondary" disabled={query.isFetching} onClick={() => query.refetch()}>Refresh</AppButton>}>
      <form className="client-directory-toolbar" onSubmit={(event) => { event.preventDefault(); setFilters((current) => ({ ...current, search: draft.trim(), page: 1 })); }}>
        <FilterBar>
          <AppInput id="client-directory-search" label="Search clients" type="search" value={draft} maxLength={255} onChange={(event) => setDraft(event.target.value)} placeholder="Name, company, email or phone" />
          <AppButton type="submit">Search</AppButton>
        </FilterBar>
      </form>
      {query.error ? <AppAlert tone="danger">{apiMessage(query.error, "Clients could not be loaded.")}</AppAlert> : <div className="clients-table-scroll" tabIndex={0} role="region" aria-label="Client directory table">
        <DataTable data={query.data?.data || []} columns={columns} actions={actions} loading={query.isLoading} searchable={false} sortable={false} paginated={false} emptyTitle={filters.search ? "No clients match your search." : "No clients yet."} />
      </div>}
      {!query.error && meta && filters.page <= meta.last_page && <div className="server-pagination client-directory-pagination"><span>{meta.from == null ? "0 entries" : meta.from + "\u2013" + meta.to + " of " + meta.total + " entries"} | Page {meta.current_page} of {meta.last_page}</span><div>
        <AppButton variant="secondary" disabled={query.isFetching || meta.current_page <= 1} onClick={() => setFilters((current) => ({ ...current, page: current.page - 1 }))}>Previous</AppButton>
        <AppButton variant="secondary" disabled={query.isFetching || meta.current_page >= meta.last_page} onClick={() => setFilters((current) => ({ ...current, page: current.page + 1 }))}>Next</AppButton>
      </div></div>}
    </SectionCard>
    <ConfirmDialog open={!!deleting} title="Delete client" message={"Delete " + (deleting?.name || "this client") + "? Linked projects prevent deletion. The login account will remain unchanged."} busy={remove.isPending} onClose={() => setDeleting(null)} onConfirm={confirmDelete} />
    {linking && <ClientLoginAccountDialog key={linking.id} client={linking} onClose={() => setLinking(null)} />}
  </div>;
}
