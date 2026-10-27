/* @vitest-environment jsdom */
import { afterEach, describe, expect, it, vi } from "vitest";
import { cleanup, render, screen } from "@testing-library/react";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";

const api = vi.hoisted(() => ({ users: vi.fn(), roles: vi.fn(), permissions: vi.fn(), departments: vi.fn(), departmentUsers: vi.fn() }));
vi.mock("../../auth/context/AuthContext", () => ({ useAuth: () => ({ can: (permission) => ["users.view", "roles.view", "departments.view"].includes(permission) }), useCompany: () => ({ company: { id: 1, name: "Company One" } }) }));
vi.mock("../../../api/endpoints", () => ({
  usersApi: { list: api.users, create: vi.fn(), update: vi.fn(), remove: vi.fn(), status: vi.fn(), roles: vi.fn(), department: vi.fn() },
  rolesApi: { list: api.roles, create: vi.fn(), update: vi.fn(), remove: vi.fn() },
  permissionsApi: { list: api.permissions },
  departmentsApi: { list: api.departments, create: vi.fn(), update: vi.fn(), remove: vi.fn(), status: vi.fn(), users: api.departmentUsers },
  apiMessage: () => "Request failed", applyServerErrors: () => {},
}));
vi.mock("react-toastify", () => ({ toast: { success: vi.fn(), warning: vi.fn() } }));
import UsersPage from "./UsersPage";
import RolesPage from "../../roles/pages/RolesPage";
import DepartmentsPage from "../../departments/pages/DepartmentsPage";

afterEach(() => { cleanup(); vi.clearAllMocks(); });
const draw = (page) => render(<QueryClientProvider client={new QueryClient({ defaultOptions: { queries: { retry: false } } })}>{page}</QueryClientProvider>);

describe("company management presentation authorization", () => {
  it("lists server users while hiding mutations without permissions", async () => { api.users.mockResolvedValue({ users: [{ id: 2, first_name: "Site", last_name: "Viewer", email: "viewer@example.test", status: "Active", roles: ["client"] }], meta: { current_page: 1, last_page: 1 } }); api.roles.mockResolvedValue([]); api.departments.mockResolvedValue({ data: [] }); draw(<UsersPage />); expect(await screen.findByText("viewer@example.test")).toBeTruthy(); expect(screen.queryByRole("button", { name: "Add New User" })).toBeNull(); expect(screen.queryByRole("button", { name: "Delete" })).toBeNull(); });
  it("protects the company_admin role from edit and deletion", async () => { api.roles.mockResolvedValue([{ id: 1, name: "company_admin", permissions: ["users.view"] }]); api.permissions.mockResolvedValue({ users: ["users.view"] }); draw(<RolesPage />); expect(await screen.findByText("company_admin")).toBeTruthy(); expect(screen.queryByRole("button", { name: "Add role" })).toBeNull(); });
  it("lists departments but hides mutating actions", async () => { api.departments.mockResolvedValue({ data: [{ id: 4, name: "Operations", code: "OPS", status: "Active", employee_count: 2 }], meta: { current_page: 1, last_page: 1 } }); draw(<DepartmentsPage />); expect(await screen.findByText("Operations")).toBeTruthy(); expect(screen.queryByRole("button", { name: "Add department" })).toBeNull(); expect(screen.queryByRole("button", { name: "Delete" })).toBeNull(); });
});
