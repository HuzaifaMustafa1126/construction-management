/* @vitest-environment jsdom */
import { afterEach, describe, expect, it, vi } from "vitest";
import { cleanup, fireEvent, render, screen } from "@testing-library/react";
vi.mock("../../features/auth/context/AuthContext", () => ({ useAuth: () => ({ can: () => true, loading: false }) }));
import { AppModal, AppSelect } from "./AppUI";

afterEach(cleanup);
describe("shared accessible controls", () => {
  it("announces select errors", () => { render(<AppSelect name="role" label="Role" error="Choose a role"><option /></AppSelect>); const select = screen.getByLabelText(/^Role/); expect(select.getAttribute("aria-describedby")).toBe("role-error"); expect(screen.getByRole("alert").textContent).toBe("Choose a role"); });
  it("focuses dialogs and closes them with Escape", () => { const close = vi.fn(); render(<AppModal open title="Confirm action" onClose={close}><button>Inside</button></AppModal>); const dialog = screen.getByRole("dialog"); expect(document.activeElement).toBe(dialog); fireEvent.keyDown(document, { key: "Escape" }); expect(close).toHaveBeenCalledOnce(); });
});
