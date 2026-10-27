import { useMemo, useState, type ReactNode } from "react";
import {
  flexRender,
  getCoreRowModel,
  getFilteredRowModel,
  getPaginationRowModel,
  getSortedRowModel,
  useReactTable,
  type ColumnDef,
  type RowData,
} from "@tanstack/react-table";
import { AppIcon } from "./AppIcon";
type Props<T extends RowData> = {
  columns: ColumnDef<T, unknown>[];
  data: T[];
  loading?: boolean;
  emptyTitle?: string;
  searchable?: boolean;
  actions?: (row: T) => ReactNode;
};
export function DataTable<T extends RowData>({
  columns,
  data,
  loading = false,
  emptyTitle = "No records found",
  searchable = true,
  actions,
}: Props<T>) {
  const [filter, setFilter] = useState("");
  const tableColumns = useMemo<ColumnDef<T, unknown>[]>(
    () =>
      actions
        ? [
            ...columns,
            {
              id: "actions",
              header: "Actions",
              cell: ({ row }) => actions(row.original),
            },
          ]
        : columns,
    [columns, actions],
  );
  const table = useReactTable({
    data,
    columns: tableColumns,
    state: { globalFilter: filter },
    onGlobalFilterChange: setFilter,
    getCoreRowModel: getCoreRowModel(),
    getFilteredRowModel: getFilteredRowModel(),
    getSortedRowModel: getSortedRowModel(),
    getPaginationRowModel: getPaginationRowModel(),
  });
  return (
    <div className="data-table">
      {searchable && (
        <label className="table-search">
          <AppIcon name="search" />
          <span className="sr-only">Search table</span>
          <input
            value={filter}
            onChange={(e) => setFilter(e.target.value)}
            placeholder="Search…"
          />
        </label>
      )}
      <div className="app-table-wrap">
        <table className="app-table">
          <thead>
            {table.getHeaderGroups().map((g) => (
              <tr key={g.id}>
                {g.headers.map((h) => (
                  <th key={h.id}>
                    <button
                      type="button"
                      onClick={h.column.getToggleSortingHandler()}
                    >
                      {flexRender(h.column.columnDef.header, h.getContext())}
                    </button>
                  </th>
                ))}
              </tr>
            ))}
          </thead>
          <tbody>
            {!loading &&
              table.getRowModel().rows.map((row) => (
                <tr key={row.id}>
                  {row.getVisibleCells().map((cell) => (
                    <td key={cell.id}>
                      {flexRender(
                        cell.column.columnDef.cell,
                        cell.getContext(),
                      )}
                    </td>
                  ))}
                </tr>
              ))}
          </tbody>
        </table>
        {loading && (
          <div className="table-loading" role="status">
            Loading…
          </div>
        )}
        {!loading && !table.getRowModel().rows.length && (
          <div className="empty-state">
            <AppIcon name="empty" size="xl" />
            <strong>{emptyTitle}</strong>
          </div>
        )}
      </div>
      <footer className="table-pagination">
        <span>
          Page {table.getState().pagination.pageIndex + 1} of{" "}
          {table.getPageCount() || 1}
        </span>
        <div>
          <button
            aria-label="Previous page"
            disabled={!table.getCanPreviousPage()}
            onClick={() => table.previousPage()}
          >
            <AppIcon name="back" />
          </button>
          <button
            aria-label="Next page"
            disabled={!table.getCanNextPage()}
            onClick={() => table.nextPage()}
          >
            <AppIcon name="next" />
          </button>
        </div>
      </footer>
    </div>
  );
}
