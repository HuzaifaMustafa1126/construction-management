import { useState } from "react";
import { Link, useSearchParams } from "react-router-dom";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { z } from "zod";
import { apiMessage, applyServerErrors, authApi } from "../../../api/endpoints";
import { AppButton, AppInput } from "../../../components/ui/AppUI";

const schema = z
  .object({
    password: z
      .string()
      .min(12, "Use at least 12 characters")
      .regex(/[a-z]/, "Include a lowercase letter")
      .regex(/[A-Z]/, "Include an uppercase letter")
      .regex(/[0-9]/, "Include a number")
      .regex(/[^A-Za-z0-9]/, "Include a symbol"),
    password_confirmation: z.string(),
  })
  .refine((v) => v.password === v.password_confirmation, {
    path: ["password_confirmation"],
    message: "Passwords do not match",
  });
export default function AdministratorPasswordSetupPage() {
  const [params] = useSearchParams();
  const token = params.get("token") || "",
    email = params.get("email") || "";
  const [done, setDone] = useState(false);
  const [problem, setProblem] = useState("");
  const {
    register,
    handleSubmit,
    setError,
    formState: { errors, isSubmitting },
  } = useForm({
    resolver: zodResolver(schema),
    defaultValues: { password: "", password_confirmation: "" },
  });
  if (done)
    return (
      <main className="auth-shell">
        <section className="auth-panel">
          <h2>Password set</h2>
          <div className="success-banner">
            Your password was set successfully.
          </div>
          <Link to="/login">Continue to login</Link>
        </section>
      </main>
    );
  return (
    <main className="auth-shell">
      <section className="auth-panel">
        <h2>Set administrator password</h2>
        <p>
          Create a secure password for {email || "your administrator account"}.
        </p>
        {(!token || !email) && (
          <div className="error-banner">
            This password setup link is incomplete.
          </div>
        )}
        <form
          className="form-grid"
          onSubmit={handleSubmit(async (values) => {
            setProblem("");
            try {
              await authApi.setupAdministratorPassword({
                ...values,
                token,
                email,
              });
              setDone(true);
            } catch (error) {
              applyServerErrors(error, setError);
              setProblem(
                apiMessage(
                  error,
                  "This password setup link is invalid or expired.",
                ),
              );
            }
          })}
        >
          <div className="full">
            <AppInput
              label="New password"
              type="password"
              autoComplete="new-password"
              error={errors.password?.message}
              {...register("password")}
            />
          </div>
          <div className="full">
            <AppInput
              label="Confirm password"
              type="password"
              autoComplete="new-password"
              error={errors.password_confirmation?.message}
              {...register("password_confirmation")}
            />
          </div>
          {problem && <div className="error-banner full">{problem}</div>}
          <AppButton busy={isSubmitting} disabled={!token || !email}>
            Set password
          </AppButton>
        </form>
      </section>
    </main>
  );
}
