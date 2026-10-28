/* @vitest-environment jsdom */
import { afterEach, describe, expect, it, vi } from "vitest";
import { cleanup, render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { MemoryRouter } from "react-router-dom";
const state = vi.hoisted(() => ({
  user: {
    email: "platform@example.test",
    status: "Active",
    is_platform_admin: true,
  },
}));
vi.mock("../../auth/context/AuthContext", () => ({
  useAuth: () => ({ user: state.user }),
}));
import SettingsPage from "./SettingsPage";
afterEach(() => cleanup());
describe("global UI settings", () => {
  it("persists appearance and navigation preferences and uses the platform security route", async () => {
    render(
      <MemoryRouter>
        <SettingsPage />
      </MemoryRouter>,
    );
    await userEvent.click(screen.getByLabelText("Dark"));
    await userEvent.click(screen.getByLabelText("Green"));
    await userEvent.click(screen.getByLabelText("Compact"));
    await userEvent.click(screen.getByLabelText("Collapse desktop sidebar"));
    expect(localStorage.getItem("ui.mode")).toBe('"dark"');
    expect(localStorage.getItem("ui.accent")).toBe('"green"');
    expect(localStorage.getItem("ui.density")).toBe('"compact"');
    expect(localStorage.getItem("ui.sidebarCollapsed")).toBe("true");
    expect(
      screen
        .getByRole("link", { name: /change password/i })
        .getAttribute("href"),
    ).toBe("/platform/profile/security");
  });
});
