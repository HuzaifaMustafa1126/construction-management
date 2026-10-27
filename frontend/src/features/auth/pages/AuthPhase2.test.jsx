/* @vitest-environment jsdom */
import { cleanup, render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { MemoryRouter } from "react-router-dom";
import { useForm } from "react-hook-form";
import { afterEach, describe, expect, it, vi } from "vitest";
import { AppInput } from "../../../components/ui/AppUI";

const login = vi.fn();
vi.mock("../context/AuthContext", () => ({ useAuth: () => ({ login }) }));
import { LoginPage } from "./AuthPhase2";
afterEach(() => {
  cleanup();
  vi.clearAllMocks();
});

describe("AppInput React Hook Form integration", () => {
  it("forwards registration props and ref to the native input", async () => {
    const submitted = vi.fn();
    function Form() {
      const { register, handleSubmit } = useForm({
        defaultValues: { email: "" },
      });
      return (
        <form onSubmit={handleSubmit(submitted)}>
          <AppInput label="Email" {...register("email")} />
          <button>Submit</button>
        </form>
      );
    }
    render(<Form />);
    await userEvent.type(screen.getByLabelText("Email"), "admin@example.com");
    await userEvent.click(screen.getByRole("button", { name: "Submit" }));
    await waitFor(() =>
      expect(submitted).toHaveBeenCalledWith(
        { email: "admin@example.com" },
        expect.anything(),
      ),
    );
  });
});

describe("LoginPage", () => {
  it("validates strings and submits the entered credentials", async () => {
    login.mockResolvedValueOnce({ is_platform_admin: false });
    render(
      <MemoryRouter>
        <LoginPage />
      </MemoryRouter>,
    );
    await userEvent.type(screen.getByLabelText("Email"), "user@example.com");
    await userEvent.type(screen.getByLabelText("Password"), "secret-password");
    await userEvent.click(screen.getByRole("button", { name: "Sign in" }));
    await waitFor(() =>
      expect(login).toHaveBeenCalledWith("user@example.com", "secret-password"),
    );
    expect(screen.queryByText(/expected string/i)).toBeNull();
  });
});
