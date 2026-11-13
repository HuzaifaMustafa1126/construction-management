/* eslint-disable react/prop-types */
import { useState } from "react";
import { Link } from "react-router-dom";
import { AppBadge, AppButton, SectionCard } from "../../../components/ui/AppUI";
import { AppAlert } from "../../../components/ui/Controls";
import { useAuth } from "../../auth/context/AuthContext";
import { useInventoryCollection } from "../../materials/hooks/useProjectInventory";
import StockStatusBadge from "../../materials/components/StockStatusBadge";
import "./dashboardStock.css";

const quantityText = (value, unit) =>
  value === null || value === undefined || value === ""
    ? "Unavailable"
    : `${value}${unit ? ` ${unit}` : " (unit unavailable)"}`;

export default function DashboardMaterialStock({ projectId, projectName }) {
  const { user } = useAuth();
  return (
    <MaterialStockPage
      key={JSON.stringify([user?.company?.id, user?.id, projectId])}
      projectId={projectId}
      projectName={projectName}
    />
  );
}

function MaterialStockPage({ projectId, projectName }) {
  const { user, can } = useAuth();
  const allowed = can("inventory.view");
  const [page, setPage] = useState(1);
  const inventory = useInventoryCollection(
    { ...(projectId ? { project_id: projectId } : {}), page, per_page: 4 },
    allowed && !!user?.id && !!user?.company?.id,
  );
  const denied = !allowed || inventory.error?.status === 403;
  const rows = inventory.data?.data || [];
  const meta = inventory.data?.meta;
  let content;
  if (denied) {
    content = (
      <AppAlert tone="info">
        You do not have permission to view this inventory.
      </AppAlert>
    );
  } else if (inventory.isError) {
    content = (
      <AppAlert tone="danger">
        Material stock could not be loaded. Please try again later.
      </AppAlert>
    );
  } else if (!inventory.isSuccess) {
    content = (
      <p className="dashboard-stock-message" role="status">
        Loading material stock...
      </p>
    );
  } else {
    content = (
      <>
        {!rows.length ? (
          <p className="dashboard-stock-message">
            No inventory matches the selected project scope.
          </p>
        ) : (
          <ul className="dashboard-stock-list">
            {rows.map((row) => {
              const unit =
                row.measurement_unit?.symbol || row.measurement_unit?.name;
              const hasQuantity =
                row.current_stock !== null && row.current_stock !== undefined;
              const hasMinimum =
                row.minimum_stock !== null && row.minimum_stock !== undefined;
              const standardStatus = [
                "healthy",
                "low_stock",
                "out_of_stock",
              ].includes(row.stock_status);
              return (
                <li key={row.inventory_id}>
                  <div className="dashboard-stock-copy">
                    <Link
                      to={`/projects/${row.project.id}/inventory`}
                      className="dashboard-stock-name"
                    >
                      {row.material.name}
                    </Link>
                    <span className="dashboard-stock-project">
                      {row.project.name}
                    </span>
                    <small>
                      {hasMinimum
                        ? `Minimum ${quantityText(row.minimum_stock, unit)}`
                        : "Minimum stock not set"}
                    </small>
                  </div>
                  <strong className="dashboard-stock-quantity">
                    {quantityText(row.current_stock, unit)}
                  </strong>
                  <span className="dashboard-stock-status">
                    {standardStatus && hasQuantity && hasMinimum ? (
                      <StockStatusBadge
                        stock={row.current_stock}
                        minimum={row.minimum_stock}
                      />
                    ) : (
                      <AppBadge
                        tone={
                          row.stock_status === "negative_stock_anomaly"
                            ? "danger"
                            : "neutral"
                        }
                      >
                        {row.stock_status === "negative_stock_anomaly"
                          ? "Negative stock anomaly"
                          : "Status unavailable"}
                      </AppBadge>
                    )}
                  </span>
                </li>
              );
            })}
          </ul>
        )}
        {/* {meta && (
          <nav
            className="dashboard-stock-pagination"
            aria-label="Material stock pagination"
          >
            <span aria-live="polite">
              {meta.total} inventory rows · Page {meta.current_page} of{" "}
              {meta.last_page}
            </span>
            <div className="button-group">
              <AppButton
                type="button"
                variant="secondary"
                disabled={inventory.isFetching || meta.current_page <= 1}
                onClick={() => setPage((current) => Math.max(1, current - 1))}
              >
                Previous
              </AppButton>
              <AppButton
                type="button"
                variant="secondary"
                disabled={
                  inventory.isFetching || meta.current_page >= meta.last_page
                }
                onClick={() => setPage((current) => current + 1)}
              >
                Next
              </AppButton>
            </div>
          </nav>
        )} */}
      </>
    );
  }
  return (
    <SectionCard
      title="Material Stock Status"
      action={
        allowed && projectId && !denied ? (
          <Link
            className="dashboard-stock-link"
            to={`/projects/${projectId}/inventory`}
          >
            View all
          </Link>
        ) : null
      }
    >
      {allowed && !denied && (
        <p className="dashboard-stock-scope">
          {projectId ? projectName || "Selected project" : "All projects"} ·
          Current stock snapshot. Period filters do not apply.
        </p>
      )}
      <div className="dashboard-stock">{content}</div>
    </SectionCard>
  );
}
