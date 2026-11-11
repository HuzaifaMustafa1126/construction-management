import { useQuery } from "@tanstack/react-query";
import { projectsApi } from "../../../api/endpoints";
import { consumptionsApi } from "../../materials/api/consumptionsApi";
import { useAuth, useCompany } from "../../auth/context/AuthContext";

async function loadAuthorizedProjects() {
  const params = {
    per_page: 100,
    sort: "created_at",
    direction: "desc",
  };
  const first = await projectsApi.list({ ...params, page: 1 });
  const projects = [...(first.data || [])];

  for (let page = 2; page <= (first.meta?.last_page || 1); page += 1) {
    const response = await projectsApi.list({ ...params, page });
    projects.push(...(response.data || []));
  }

  return projects;
}

const countMetric = (query) => {
  if (query.isLoading) return { state: "loading" };
  if (query.isError) return { state: "error" };
  return { state: "ready", value: query.data?.meta?.total ?? 0 };
};

const consumptionMetric = (query, disabledMessage) => {
  if (!query.isEnabled) {
    return {
      state: "unavailable",
      message: disabledMessage,
    };
  }
  if (query.isLoading) return { state: "loading" };
  if (query.isError) return { state: "error" };

  const usage = query.data?.consumed_by_material || [];
  if (!usage.length) {
    return {
      state: "ready",
      value: "0",
      support: "No posted consumption in this period",
    };
  }

  const byUnit = usage.reduce((totals, row) => {
    const symbol = row.measurement_unit?.symbol || "unit";
    totals.set(symbol, (totals.get(symbol) || 0) + Number(row.quantity || 0));
    return totals;
  }, new Map());

  if (byUnit.size > 1) {
    return {
      state: "ready",
      value: "Mixed units",
      support: `${byUnit.size} measurement units in this period`,
    };
  }

  const [[symbol, quantity]] = byUnit.entries();
  return {
    state: "ready",
    value: `${new Intl.NumberFormat(undefined, { maximumFractionDigits: 4 }).format(quantity)} ${symbol}`,
    support: "Posted consumption in this period",
  };
};

export function useDashboardSummary({ projectId, dateFrom, dateTo }) {
  const { user, can } = useAuth();
  const { company } = useCompany();
  const companyId = company?.id || user?.company?.id;
  const identity = [companyId, user?.id];
  const canViewProjects = can("projects.view");
  const canViewConsumption = can("material_consumptions.view");

  const projects = useQuery({
    queryKey: ["dashboard", ...identity, "project-options"],
    queryFn: loadAuthorizedProjects,
    enabled: !!companyId && canViewProjects,
  });

  const selectedProject = projectId
    ? projects.data?.find((project) => String(project.id) === String(projectId))
    : null;

  const totalProjects = useQuery({
    queryKey: ["dashboard", ...identity, "total-projects"],
    queryFn: () => projectsApi.list({ page: 1, per_page: 1 }),
    enabled: !!companyId && canViewProjects && !projectId,
  });

  const activeProjects = useQuery({
    queryKey: ["dashboard", ...identity, "active-projects"],
    queryFn: () =>
      projectsApi.list({ status: "active", page: 1, per_page: 1 }),
    enabled: !!companyId && canViewProjects && !projectId,
  });

  const consumption = useQuery({
    queryKey: [
      "dashboard",
      ...identity,
      "material-consumption",
      String(projectId || "none"),
      dateFrom,
      dateTo,
    ],
    queryFn: () =>
      consumptionsApi.summary(projectId, {
        date_from: dateFrom,
        date_to: dateTo,
      }),
    enabled:
      !!companyId &&
      !!projectId &&
      !!dateFrom &&
      !!dateTo &&
      canViewConsumption,
    retry: false,
  });

  const totalValue = projectId
    ? projects.isLoading
      ? { state: "loading" }
      : selectedProject
        ? { state: "ready", value: 1 }
        : { state: "error" }
    : countMetric(totalProjects);
  const activeValue = projectId
    ? projects.isLoading
      ? { state: "loading" }
      : selectedProject
        ? { state: "ready", value: selectedProject.status === "active" ? 1 : 0 }
        : { state: "error" }
    : countMetric(activeProjects);
  const totalCount = totalValue.state === "ready" ? Number(totalValue.value) : 0;
  const activeCount = activeValue.state === "ready" ? Number(activeValue.value) : 0;

  return {
    projects,
    selectedProject,
    totalValue,
    activeValue,
    activeSupport:
      totalCount > 0
        ? `${Math.round((activeCount / totalCount) * 100)}% of selected scope`
        : "Current active status",
    consumptionValue: consumptionMetric(
      {
        ...consumption,
        isEnabled: !!projectId && canViewConsumption && !!dateFrom && !!dateTo,
      },
      projectId
        ? "Choose a valid period"
        : "Select a project to view consumption",
    ),
  };
}
