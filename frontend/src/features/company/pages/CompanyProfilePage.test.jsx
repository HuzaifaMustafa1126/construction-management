/* @vitest-environment jsdom */
import { afterEach, describe, expect, it, vi } from "vitest";
import { cleanup, render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";

const state = vi.hoisted(() => ({ allowed: false, update: vi.fn(), refresh: vi.fn(), company: { id: 1, name: "Company One", email: "one@example.test", phone: "1234567", address: "", city: "", state: "", country: "Pakistan", postal_code: "", website: "", currency: "PKR", timezone: "Asia/Karachi" } }));
vi.mock("../../auth/context/AuthContext", () => ({ useAuth: () => ({ can: () => state.allowed }), useCompany: () => ({ company: state.company, isLoading: false, refreshCompany: state.refresh }) }));
vi.mock("../../../api/endpoints", () => ({ companyApi: { update: state.update }, apiMessage: () => "Update failed", applyServerErrors: () => {} }));
vi.mock("react-toastify", () => ({ toast: { success: vi.fn() } }));
import CompanyProfilePage from "./CompanyProfilePage";
afterEach(() => { cleanup(); vi.clearAllMocks(); });
const draw = () => render(<QueryClientProvider client={new QueryClient()}><CompanyProfilePage /></QueryClientProvider>);
describe("company profile authorization", () => {
  it("shows company data but no editor without company.update", () => { state.allowed = false; draw(); expect(screen.getByText("Company One")).toBeTruthy(); expect(screen.queryByRole("button", { name: "Edit company" })).toBeNull(); });
  it("updates allowed fields and refreshes global company identity", async () => { state.allowed = true; state.update.mockResolvedValue({}); state.refresh.mockResolvedValue(state.company); draw(); await userEvent.click(screen.getByRole("button", { name: "Edit company" })); await userEvent.clear(screen.getByLabelText("Company name")); await userEvent.type(screen.getByLabelText("Company name"), "Updated Company"); await userEvent.click(screen.getByRole("button", { name: "Save changes" })); await waitFor(() => expect(state.update).toHaveBeenCalledWith(expect.objectContaining({ name: "Updated Company", currency: "PKR" }))); expect(state.refresh).toHaveBeenCalled(); });
});
