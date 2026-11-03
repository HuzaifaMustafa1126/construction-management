/* eslint-disable react/prop-types */
import { useEffect, useState } from "react";
import { AppInput, AppSelect } from "../../../components/ui/AppUI";
import { AppAlert } from "../../../components/ui/Controls";
import { useClientLookup } from "../hooks/useClients";

export default function ClientSelector({ value, onChange, error }) {
  const [search, setSearch] = useState("");
  const [querySearch, setQuerySearch] = useState("");
  useEffect(() => {
    const timer = setTimeout(() => setQuerySearch(search.trim()), 250);
    return () => clearTimeout(timer);
  }, [search]);
  const query = useClientLookup({
    search: querySearch || undefined,
    selected_id: value || undefined,
    limit: 30,
  });
  const rows = Array.isArray(query.data) ? query.data : [];

  return (
    <div className="full form-grid">
      <AppInput
        label="Search clients"
        value={search}
        onChange={(event) => setSearch(event.target.value)}
        placeholder="Search by client or company name"
      />
      <AppSelect
        label="Client"
        value={value || ""}
        onChange={(event) =>
          onChange(event.target.value ? Number(event.target.value) : null)
        }
        error={error}
      >
        <option value="">No client</option>
        {rows.map((client) => (
          <option key={client.id} value={client.id}>
            {client.name}
            {client.company_name ? ` — ${client.company_name}` : ""}
          </option>
        ))}
      </AppSelect>
      {query.isLoading && <small role="status">Loading clients…</small>}
      {query.isError && (
        <AppAlert tone="danger">
          Clients could not be loaded for selection.
        </AppAlert>
      )}
    </div>
  );
}
