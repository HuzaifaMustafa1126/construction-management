/* @vitest-environment jsdom */
import { afterEach, describe, expect, it, vi } from "vitest";
import { cleanup, render, screen } from "@testing-library/react";
import { MemoryRouter } from "react-router-dom";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";

const state = vi.hoisted(() => ({
  user: null,
  company: null,
  permissions: new Set(),
  list: vi.fn(),
}));
vi.mock("../../auth/context/AuthContext", () => ({
  useAuth: () => ({
    user: state.user,
    can: (p) => !p || state.permissions.has(p),
  }),
  useCompany: () => ({ company: state.company, companyError: null }),
}));
vi.mock("../../../api/endpoints", () => ({
  projectsApi: { list: state.list },
}));
import CompanyDashboard from "./CompanyDashboard";

afterEach(() => {
  cleanup();
  vi.clearAllMocks();
});
function draw() {
  const client = new QueryClient({
    defaultOptions: { queries: { retry: false } },
  });
  return render(
    <QueryClientProvider client={client}>
      <MemoryRouter>
        <CompanyDashboard />
      </MemoryRouter>
    </QueryClientProvider>,
  );
}
describe("role-aware company dashboard", () => {
  it("renders assigned backend projects for a supervisor", async () => {
    state.user = { id: 2, name: "Sam Supervisor", roles: ["site_supervisor"] };
    state.company = { id: 1, name: "Company One" };
    state.permissions = new Set(["projects.view"]);
    state.list.mockResolvedValue({
      data: [
        {
          id: 8,
          name: "Tower Site",
          code: "TS-1",
          status: "active",
          progress_percentage: 45,
        },
      ],
      meta: { total: 1 },
    });
    draw();
    expect(await screen.findByText("Tower Site")).toBeTruthy();
    expect(screen.getByText("Authorized projects")).toBeTruthy();
    expect(screen.queryByText("Manage users")).toBeNull();
  });
  it("shows custom-role actions from permissions rather than role names", () => {
    state.user = { id: 3, name: "Custom User", roles: ["operations_lead"] };
    state.company = { id: 2, name: "Company Two" };
    state.permissions = new Set(["users.view"]);
    draw();
    expect(screen.getByText("Manage users")).toBeTruthy();
    expect(screen.queryByText("Manage roles")).toBeNull();
    expect(screen.queryByText("Total projects")).toBeNull();
  });
});
