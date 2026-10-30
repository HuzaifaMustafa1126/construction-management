/* eslint-disable react/prop-types */
import { useEffect, useMemo, useState } from "react";
import {
  Link,
  useLocation,
  useNavigate,
  useParams,
  useSearchParams,
} from "react-router-dom";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { z } from "zod";
import { apiMessage, applyServerErrors, authApi } from "../../../api/endpoints";
import { useAuth } from "../context/AuthContext";
import { AppButton, AppInput } from "../../../components/ui/AppUI";
const schema = z
  .object({
    company: z.object({
      name: z.string().trim().min(1, "Company name is required").max(255),
      email: z.string().trim().email("Enter a valid company email").max(255),
      phone: z
        .string()
        .trim()
        .regex(/^\+?[0-9][0-9\- ]{6,28}$/, "Enter a valid company phone"),
      address: z.string().trim().max(2000).optional(),
    }),
    admin: z.object({
      first_name: z.string().trim().min(1, "First name is required").max(100),
      last_name: z.string().trim().min(1, "Last name is required").max(100),
      email: z
        .string()
        .trim()
        .email("Enter a valid administrator email")
        .max(255),
      phone: z
        .string()
        .trim()
        .refine(
          (value) => !value || /^\+?[0-9][0-9\- ]{6,28}$/.test(value),
          "Enter a valid administrator phone",
        )
        .optional(),
      password: z
        .string()
        .min(12, "Use at least 12 characters")
        .regex(/[a-z]/, "Include a lowercase letter")
        .regex(/[A-Z]/, "Include an uppercase letter")
        .regex(/[0-9]/, "Include a number")
        .regex(/[^A-Za-z0-9]/, "Include a symbol"),
      password_confirmation: z.string(),
    }),
  })
  .refine((v) => v.admin.password === v.admin.password_confirmation, {
    path: ["admin", "password_confirmation"],
    message: "Passwords do not match",
  });
function Shell({ title, subtitle, children }) {
  return (
    <main className="auth-shell">
      <aside className="auth-brand">
        <strong>BUILDWISE</strong>
        <h1>Build with clarity. Manage with confidence.</h1>
        <p>One secure workspace for your company and construction teams.</p>
      </aside>
      <section className="auth-panel">
        <h2>{title}</h2>
        <p>{subtitle}</p>
        {children}
      </section>
    </main>
  );
}
export function RegisterCompanyPage() {
  const [success, setSuccess] = useState();
  const [problem, setProblem] = useState("");
  const [show, setShow] = useState(false);
  const {
    register,
    handleSubmit,
    setError,
    formState: { errors, isSubmitting },
  } = useForm({ resolver: zodResolver(schema) });
  const submit = async (data) => {
    setProblem("");
    try {
      const r = await authApi.registerCompany(data);
      setSuccess(r?.admin?.email || data.admin.email);
    } catch (e) {
      applyServerErrors(e, setError);
      setProblem(apiMessage(e, "Registration failed."));
    }
  };
  const f = (n, l, p = {}) => {
    const fieldError = n
      .split(".")
      .reduce((value, key) => value?.[key], errors);
    return (
      <AppInput label={l} error={fieldError?.message} {...register(n)} {...p} />
    );
  };
  if (success)
    return (
      <Shell
        title="Check your email"
        subtitle="Your company registration was received."
      >
        <div className="success-banner">
          A verification link was sent to <strong>{success}</strong>. Verify
          this email before signing in.
        </div>
        <AppButton
          variant="secondary"
          onClick={async () => {
            try {
              await authApi.resendVerification({ email: success });
            } catch (e) {
              setProblem(apiMessage(e));
            }
          }}
        >
          Resend verification email
        </AppButton>
        {problem && <div className="error-banner">{problem}</div>}
        <div className="auth-links">
          <Link to="/login">Continue to login</Link>
        </div>
      </Shell>
    );
  return (
    <Shell
      title="Register your company"
      subtitle="Create the company workspace and first administrator."
    >
      {problem && <div className="error-banner">{problem}</div>}
      <form className="form-grid" onSubmit={handleSubmit(submit)} noValidate>
        <h3 className="auth-section-title">Company information</h3>
        {f("company.name", "Company name", { required: true })}
        {f("company.email", "Company email", { type: "email", required: true })}
        {f("company.phone", "Company phone", { required: true })}
        <div className="full">{f("company.address", "Company address")}</div>
        <h3 className="auth-section-title">Administrator information</h3>
        {f("admin.first_name", "First name", { required: true })}
        {f("admin.last_name", "Last name", { required: true })}
        {f("admin.email", "Administrator email", {
          type: "email",
          required: true,
        })}
        {f("admin.phone", "Phone")}
        {f("admin.password", "Password", {
          type: show ? "text" : "password",
          autoComplete: "new-password",
          required: true,
        })}
        {f("admin.password_confirmation", "Confirm password", {
          type: show ? "text" : "password",
          autoComplete: "new-password",
          required: true,
        })}
        <p className="full auth-help">
          Use 12 or more characters with uppercase, lowercase, a number, and a
          symbol.
        </p>
        <label className="full permission-check">
          <input
            type="checkbox"
            checked={show}
            onChange={(e) => setShow(e.target.checked)}
          />
          Show passwords
        </label>
        <div className="full">
          <AppButton type="submit" busy={isSubmitting}>
            Create company
          </AppButton>
        </div>
      </form>
      <div className="auth-links">
        Already registered? <Link to="/login">Sign in</Link>
      </div>
    </Shell>
  );
}
export function LoginPage() {
  const { login, restrictionMessage } = useAuth();
  const nav = useNavigate(),
    location = useLocation();
  const [problem, setProblem] = useState("");
  const [show, setShow] = useState(false);
  const {
    register,
    handleSubmit,
    formState: { errors, isSubmitting },
  } = useForm({
    resolver: zodResolver(
      z.object({
        email: z.string().email("Enter a valid email"),
        password: z.string().min(1, "Password is required"),
      }),
    ),
  });
  const submit = async (d) => {
    setProblem("");
    try {
      const user = await login(d.email.trim(), d.password);
      nav(
        location.state?.from?.pathname ||
          (user.is_platform_admin ? "/platform/dashboard" : "/dashboard"),
        { replace: true },
      );
    } catch (e) {
      setProblem(
        apiMessage(
          e,
          e.response?.status === 401
            ? "Incorrect email or password."
            : "Unable to sign in.",
        ),
      );
    }
  };
  return (
    <Shell title="Welcome back" subtitle="Sign in to your secure workspace.">
      {restrictionMessage && (
        <div className="error-banner" role="alert">
          {restrictionMessage} Contact your administrator if you believe this
          is an error.
        </div>
      )}
      <form className="form-grid" onSubmit={handleSubmit(submit)}>
        <div className="full">
          <AppInput
            label="Email"
            type="email"
            autoComplete="username"
            error={errors.email?.message}
            {...register("email")}
          />
        </div>
        <div className="full">
          <AppInput
            label="Password"
            type={show ? "text" : "password"}
            autoComplete="current-password"
            error={errors.password?.message}
            {...register("password")}
          />
        </div>
        <label className="full permission-check">
          <input
            type="checkbox"
            checked={show}
            onChange={(e) => setShow(e.target.checked)}
          />
          Show password
        </label>
        {problem && <div className="error-banner full">{problem}</div>}
        <div className="full">
          <AppButton busy={isSubmitting}>Sign in</AppButton>
        </div>
      </form>
      <div className="auth-links">
        <Link to="/forgot-password">Forgot your password?</Link>
      </div>
    </Shell>
  );
}

export function ForgotPasswordPage() {
  const [confirmation, setConfirmation] = useState("");
  const [problem, setProblem] = useState("");
  const {
    register,
    handleSubmit,
    setError,
    formState: { errors, isSubmitting },
  } = useForm({
    resolver: zodResolver(
      z.object({ email: z.string().trim().email("Enter a valid email").max(255) }),
    ),
  });
  const submit = async (data) => {
    setProblem("");
    setConfirmation("");
    try {
      const response = await authApi.forgotPassword({ email: data.email.trim() });
      setConfirmation(
        response?.message ||
          "If an eligible account matches that email, a password reset link has been sent.",
      );
    } catch (error) {
      applyServerErrors(error, setError);
      setProblem(apiMessage(error, "Unable to request a password reset."));
    }
  };
  return (
    <Shell title="Forgot password" subtitle="Request a secure link to choose a new password.">
      {confirmation && <div className="success-banner" role="status">{confirmation}</div>}
      {problem && <div className="error-banner" role="alert">{problem}</div>}
      <form className="form-grid" onSubmit={handleSubmit(submit)} noValidate>
        <div className="full">
          <AppInput
            label="Email"
            type="email"
            autoComplete="email"
            required
            error={errors.email?.message}
            {...register("email")}
          />
        </div>
        <div className="full">
          <AppButton type="submit" busy={isSubmitting}>Send reset link</AppButton>
        </div>
      </form>
      <div className="auth-links"><Link to="/login">Back to login</Link></div>
    </Shell>
  );
}

const resetPasswordSchema = z
  .object({
    email: z.string().trim().email("Enter a valid email").max(255),
    token: z.string().min(1, "Reset token is required"),
    password: z.string().min(8, "Use at least 8 characters").regex(/[A-Za-z]/, "Include a letter").regex(/[0-9]/, "Include a number"),
    password_confirmation: z.string(),
  })
  .refine((value) => value.password === value.password_confirmation, {
    path: ["password_confirmation"],
    message: "Passwords do not match",
  });

export function ResetPasswordPage() {
  const [params] = useSearchParams();
  const [problem, setProblem] = useState("");
  const [success, setSuccess] = useState("");
  const [show, setShow] = useState(false);
  const {
    register,
    handleSubmit,
    setError,
    formState: { errors, isSubmitting },
  } = useForm({
    resolver: zodResolver(resetPasswordSchema),
    defaultValues: {
      email: params.get("email") || "",
      token: params.get("token") || "",
      password: "",
      password_confirmation: "",
    },
  });
  const submit = async (data) => {
    setProblem("");
    setSuccess("");
    try {
      const response = await authApi.resetPassword({ ...data, email: data.email.trim() });
      setSuccess(response?.message || "Password reset successfully. You can now sign in.");
    } catch (error) {
      applyServerErrors(error, setError);
      setProblem(apiMessage(error, "Unable to reset the password."));
    }
  };
  return (
    <Shell title="Reset password" subtitle="Enter the reset details and choose a new password.">
      {success && <div className="success-banner" role="status">{success}</div>}
      {problem && <div className="error-banner" role="alert">{problem}</div>}
      {!success && (
        <form className="form-grid" onSubmit={handleSubmit(submit)} noValidate>
          <div className="full"><AppInput label="Email" type="email" autoComplete="email" required error={errors.email?.message} {...register("email")} /></div>
          <div className="full"><AppInput label="Reset token" autoComplete="off" required error={errors.token?.message} {...register("token")} /></div>
          <div className="full"><AppInput label="New password" type={show ? "text" : "password"} autoComplete="new-password" required error={errors.password?.message} {...register("password")} /></div>
          <div className="full"><AppInput label="Confirm password" type={show ? "text" : "password"} autoComplete="new-password" required error={errors.password_confirmation?.message} {...register("password_confirmation")} /></div>
          <p className="full auth-help">Use 8 or more characters with at least one letter and one number.</p>
          <label className="full permission-check"><input type="checkbox" checked={show} onChange={(event) => setShow(event.target.checked)} />Show passwords</label>
          <div className="full"><AppButton type="submit" busy={isSubmitting}>Reset password</AppButton></div>
        </form>
      )}
      <div className="auth-links"><Link to="/login">{success ? "Return to login" : "Back to login"}</Link></div>
    </Shell>
  );
}
export function VerifyEmailPage() {
  return (
    <Shell
      title="Verify your email"
      subtitle="Open the signed link sent to your administrator email."
    >
      <p>
        The complete server-issued link, including its signature, will be
        preserved.
      </p>
      <Link to="/login">Return to login</Link>
    </Shell>
  );
}
export function VerifyEmailResultPage() {
  const route = useParams();
  const location = useLocation();
  const [p] = useSearchParams();
  const [state, setState] = useState({ loading: true, message: "Verifying…" });
  const path = useMemo(() => {
    const id = route.id || p.get("id"),
      hash = route.hash || p.get("hash");
    if (!id || !hash) return null;
    if (route.id && route.hash) {
      return `/v1/verify-email/${encodeURIComponent(id)}/${encodeURIComponent(hash)}${location.search}`;
    }
    const q = new URLSearchParams(p);
    q.delete("id");
    q.delete("hash");
    return `/v1/verify-email/${encodeURIComponent(id)}/${encodeURIComponent(hash)}${q.size ? `?${q}` : ""}`;
  }, [location.search, p, route.hash, route.id]);
  useEffect(() => {
    let live = true;
    if (!path) {
      setState({
        loading: false,
        ok: false,
        message: "This verification link is incomplete.",
      });
      return;
    }
    authApi
      .verifyEmail(path)
      .then(
        (r) =>
          live &&
          setState({
            loading: false,
            ok: true,
            message: r?.message || "Email verified successfully.",
          }),
      )
      .catch(
        (e) =>
          live &&
          setState({
            loading: false,
            ok: false,
            message: apiMessage(e, "This link is invalid or expired."),
          }),
      );
    return () => {
      live = false;
    };
  }, [path]);
  return (
    <Shell
      title={
        state.loading
          ? "Verifying email"
          : state.ok
            ? "Email verified"
            : "Verification failed"
      }
      subtitle={state.message}
    >
      {!state.loading && (
        <div className={state.ok ? "success-banner" : "error-banner"}>
          {state.message}
        </div>
      )}
      <Link to="/login">Continue to login</Link>
    </Shell>
  );
}
