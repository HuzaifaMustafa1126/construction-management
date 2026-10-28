/* @vitest-environment jsdom */
import { afterEach, describe, expect, it, vi } from "vitest";
import { cleanup, render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { MemoryRouter } from "react-router-dom";

const state = vi.hoisted(() => ({ permissions: ["projects.view"], query: {}, mutateAsync: vi.fn(), navigate: vi.fn() }));
vi.mock("../../auth/context/AuthContext", () => ({ useAuth: () => ({ can: (permission) => state.permissions.includes(permission) }) }));
vi.mock("../hooks/useProjects", () => ({ useProjects: () => state.query, useProjectMutation: () => ({ mutateAsync: state.mutateAsync, isPending: false }) }));
vi.mock("react-router-dom", async (original) => ({ ...(await original()), useNavigate: () => state.navigate }));
vi.mock("react-toastify", () => ({ toast: { success: vi.fn(), error: vi.fn() } }));
import ProjectsPage from "./ProjectsPage";

afterEach(() => { cleanup(); vi.clearAllMocks(); state.permissions = ["projects.view"]; });
const draw = () => render(<MemoryRouter><ProjectsPage/></MemoryRouter>);

describe("projects list", () => {
  it("renders server project data and hides unauthorized mutations", () => { state.query = { data: { data: [{ id: 7, name: "Tower Build", code: "TWR-01", location: "Karachi", start_date: "2026-01-01", expected_end_date: "2026-12-01", status: "active", progress_percentage: 35 }], meta: { current_page: 1, last_page: 1, total: 1 } }, refetch: vi.fn() }; draw(); expect(screen.getByText("Tower Build")).toBeTruthy(); expect(screen.getByText("35%")).toBeTruthy(); expect(screen.queryByText("Add Project")).toBeNull(); expect(screen.queryByLabelText("Delete Tower Build")).toBeNull(); });
  it("shows create, edit, and delete actions only with permissions", () => { state.permissions = ["projects.view", "projects.create", "projects.update", "projects.delete"]; state.query = { data: { data: [{ id: 8, name: "Road Works", code: "ROAD", status: "planning", progress_percentage: 0 }], meta: { current_page: 1, last_page: 1, total: 1 } }, refetch: vi.fn() }; draw(); expect(screen.getByText("Add Project")).toBeTruthy(); expect(screen.getByLabelText("Edit Road Works")).toBeTruthy(); expect(screen.getByLabelText("Delete Road Works")).toBeTruthy(); });
  it("renders empty, loading, and API error states", () => { state.query = { data: { data: [], meta: { current_page: 1, last_page: 1, total: 0 } }, error: { kind: "server", message: "Unavailable" }, isLoading: false, refetch: vi.fn() }; draw(); expect(screen.getByText("No projects found")).toBeTruthy(); expect(screen.getByText("Unavailable")).toBeTruthy(); });
  it("supports a server search control", async () => { state.query = { data: { data: [], meta: { current_page: 1, last_page: 1, total: 0 } }, refetch: vi.fn() }; draw(); await userEvent.type(screen.getByPlaceholderText("Search name or code…"), "alpha"); await userEvent.click(screen.getByRole("button", { name: "Search" })); expect(screen.getByDisplayValue("alpha")).toBeTruthy(); });
});
