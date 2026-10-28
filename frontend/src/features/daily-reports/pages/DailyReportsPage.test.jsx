/* @vitest-environment jsdom */
import { afterEach, describe, expect, it, vi } from "vitest";
import { cleanup, render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { MemoryRouter } from "react-router-dom";

const state = vi.hoisted(() => ({ permissions: ["site_reports.view"], user: { id: 2, company: { id: 1 }, roles: [{ name: "site_supervisor" }] }, projects: { data: { data: [{ id: 10, name: "Tower", code: "T-1" }] } }, reports: {}, mutateAsync: vi.fn(), navigate: vi.fn() }));
vi.mock("../../auth/context/AuthContext", () => ({ useAuth: () => ({ user: state.user, can: (permission) => state.permissions.includes(permission) }) }));
vi.mock("../../projects/hooks/useProjects", () => ({ useProjects: () => state.projects }));
vi.mock("../hooks/useDailyReports", () => ({ useDailyReports: () => state.reports, useDailyReportMutation: () => ({ mutateAsync: state.mutateAsync, isPending: false }) }));
vi.mock("react-router-dom", async (original) => ({ ...(await original()), useNavigate: () => state.navigate }));
vi.mock("react-toastify", () => ({ toast: { success: vi.fn(), error: vi.fn() } }));
import DailyReportsPage from "./DailyReportsPage";

afterEach(() => { cleanup(); vi.clearAllMocks(); state.permissions = ["site_reports.view"]; state.user = { id: 2, company: { id: 1 }, roles: [{ name: "site_supervisor" }] }; });
const draw = (route = "/daily-reports?project=10") => render(<MemoryRouter initialEntries={[route]}><DailyReportsPage/></MemoryRouter>);
describe("daily reports list", () => {
  it("renders project-scoped server data and hides mutation controls", () => { state.reports = { data: { data: [{ id: 8, report_date: "2026-10-28", title: "Foundation", creator: { id: 2, name: "Supervisor" }, total_workers_present: 12, work_completed: "Rebar installed", status: "draft" }], meta: { current_page: 1, last_page: 1, total: 1 } }, refetch: vi.fn() }; draw(); expect(screen.getByText("Foundation")).toBeTruthy(); expect(screen.queryByText("Create Daily Report")).toBeNull(); expect(screen.queryByLabelText("Delete Foundation")).toBeNull(); });
  it("shows create/edit/delete only under effective permission and policy hints", () => { state.permissions = ["site_reports.view", "site_reports.create", "site_reports.update", "site_reports.delete"]; state.user = { ...state.user, roles: [{ name: "company_admin" }] }; state.reports = { data: { data: [{ id: 9, report_date: "2026-10-28", title: "Admin report", creator: { id: 3, name: "Other" }, total_workers_present: 0, work_completed: "Done", status: "draft" }], meta: { current_page: 1, last_page: 1, total: 1 } }, refetch: vi.fn() }; draw(); expect(screen.getByText("Create Daily Report")).toBeTruthy(); expect(screen.getByLabelText("Edit Admin report")).toBeTruthy(); expect(screen.getByLabelText("Delete Admin report")).toBeTruthy(); });
  it("renders empty and API error states", () => { state.reports = { data: { data: [], meta: { current_page: 1, last_page: 1, total: 0 } }, error: { kind: "server", message: "Unavailable" }, refetch: vi.fn() }; draw(); expect(screen.getByText("No daily reports found")).toBeTruthy(); expect(screen.getByText("Unavailable")).toBeTruthy(); });
  it("supports project, status, date, and server search controls", async () => { state.reports = { data: { data: [], meta: { current_page: 1, last_page: 1, total: 0 } }, refetch: vi.fn() }; draw(); await userEvent.type(screen.getByLabelText("Search title"), "concrete"); await userEvent.selectOptions(screen.getByLabelText("Status"), "submitted"); await userEvent.click(screen.getByRole("button", { name: "Search" })); expect(screen.getByDisplayValue("concrete")).toBeTruthy(); expect(screen.getByDisplayValue("Submitted")).toBeTruthy(); });
});
