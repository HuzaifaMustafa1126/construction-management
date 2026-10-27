/* @vitest-environment jsdom */
import { afterEach, describe, expect, it, vi } from "vitest";
import { cleanup, fireEvent, render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { MemoryRouter } from "react-router-dom";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";

const state = vi.hoisted(() => ({ updateMe: vi.fn(), updatePassword: vi.fn(), refreshUser: vi.fn(), user: null }));
vi.mock("../../auth/context/AuthContext", () => ({ useAuth: () => ({ user: state.user, refreshUser: state.refreshUser }), useCompany: () => ({ company: { name: "Safe Builders" } }) }));
vi.mock("../../../api/endpoints", () => ({ authApi: { updateMe: state.updateMe, updatePassword: state.updatePassword }, apiMessage: () => "Request failed", applyServerErrors: () => {} }));
vi.mock("react-toastify", () => ({ toast: { success: vi.fn() } }));
import { ProfilePage, ProfileSecurityPage } from "./ProfilePages";

afterEach(() => { cleanup(); vi.clearAllMocks(); });
function draw(component) { const client = new QueryClient({ defaultOptions: { queries: { retry: false } } }); return render(<QueryClientProvider client={client}><MemoryRouter>{component}</MemoryRouter></QueryClientProvider>); }

describe("personal profile", () => {
  it("prefills and updates only supported personal fields", async () => {
    state.user = { id: 7, first_name: "Ayesha", last_name: "Khan", email: "ayesha@example.test", phone: "+923001234567", status: "Active", company_id: 1, is_platform_admin: false, roles: ["company_admin"] };
    state.updateMe.mockResolvedValue({}); state.refreshUser.mockResolvedValue(state.user);
    draw(<ProfilePage />); await userEvent.click(screen.getByRole("button", { name: /edit profile/i }));
    const firstName = screen.getByLabelText("First name"); expect(firstName.value).toBe("Ayesha"); fireEvent.change(firstName, { target: { value: "Aisha" } });
    await userEvent.click(screen.getByRole("button", { name: "Save changes" }));
    await waitFor(() => expect(state.updateMe).toHaveBeenCalledWith({ first_name: "Aisha", last_name: "Khan", email: "ayesha@example.test", phone: "+923001234567" }));
    expect(state.refreshUser).toHaveBeenCalled();
  });
});

describe("password security", () => {
  it("validates confirmation and clears fields after a successful change", async () => {
    state.user = { id: 7, is_platform_admin: false }; state.updatePassword.mockResolvedValue({}); draw(<ProfileSecurityPage />);
    await userEvent.type(screen.getByLabelText("Current password"), "CurrentPassword1!"); await userEvent.type(screen.getByLabelText("New password"), "NewPassword1!"); await userEvent.type(screen.getByLabelText("Confirm new password"), "DifferentPassword1!");
    await userEvent.click(screen.getByRole("button", { name: "Change password" })); expect(await screen.findByText("Passwords do not match")).toBeTruthy(); expect(state.updatePassword).not.toHaveBeenCalled();
    await userEvent.clear(screen.getByLabelText(/^Confirm new password/)); await userEvent.type(screen.getByLabelText(/^Confirm new password/), "NewPassword1!"); await userEvent.click(screen.getByRole("button", { name: "Change password" }));
    await waitFor(() => expect(state.updatePassword).toHaveBeenCalled()); await waitFor(() => expect(screen.getByLabelText("Current password").value).toBe(""));
  });
});
