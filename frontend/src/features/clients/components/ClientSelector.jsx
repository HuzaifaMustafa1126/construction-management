/* eslint-disable react/prop-types */
import { forwardRef, useEffect, useMemo, useState } from "react";
import { SearchableSelect } from "../../../components/ui/AppUI";
import { useClientLookup } from "../hooks/useClients";

const LOOKUP_LIMIT = 50;
const clientOption = (client) => ({
  value: Number(client.id),
  label: client.company_name
    ? `${client.name} \u2014 ${client.company_name}`
    : client.name,
});

const ClientSelector = forwardRef(function ClientSelector(
  { value, onChange, onBlur, name, error, selectedClient },
  ref,
) {
  const [search, setSearch] = useState("");
  const [querySearch, setQuerySearch] = useState("");
  const term = search.trim();
  useEffect(() => {
    const timer = setTimeout(() => setQuerySearch(term), 250);
    return () => clearTimeout(timer);
  }, [term]);
  // Do not send selected_id: on empty search the existing API otherwise returns only that client.
  // Project data supplies the selected label independently of the bounded search results.
  const query = useClientLookup({
    search: querySearch || undefined,
    limit: LOOKUP_LIMIT,
  });
  const rows = Array.isArray(query.data) ? query.data : [];
  const options = useMemo(
    () => [
      { value: "", label: "No client" },
      ...(Array.isArray(query.data) ? query.data : []).map(clientOption),
    ],
    [query.data],
  );
  const selectedOption = selectedClient
    ? clientOption(selectedClient)
    : undefined;
  const waiting = term !== querySearch || query.isFetching;
  const menuMessage = !rows.length
    ? querySearch
      ? "No matching clients."
      : "No clients available."
    : rows.length >= LOOKUP_LIMIT
      ? "Showing up to 50 clients. Refine your search to find others; this lookup does not support pagination."
      : undefined;

  return (
    <div className="full">
      <SearchableSelect
        ref={ref}
        name={name}
        label="Client"
        placeholder="Search or select a client"
        searchPlaceholder="Search or select a client"
        value={value ?? ""}
        selectedOption={selectedOption}
        options={options}
        onChange={(next) => onChange(next === "" ? null : next)}
        onBlur={onBlur}
        onSearchChange={setSearch}
        filterOptions={false}
        maxLength={255}
        error={error}
        loading={waiting || query.isLoading}
        requestError={
          !waiting && query.isError
            ? "Clients could not be loaded. Change your search to retry."
            : undefined
        }
        menuMessage={menuMessage}
      />
    </div>
  );
});

export default ClientSelector;
