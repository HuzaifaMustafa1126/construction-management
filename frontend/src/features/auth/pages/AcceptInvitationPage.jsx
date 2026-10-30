import { useEffect, useRef, useState } from "react";
import { Link } from "react-router-dom";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { z } from "zod";
import { apiMessage, applyServerErrors, staffInvitationsApi } from "../../../api/endpoints";
import { AppButton, AppInput } from "../../../components/ui/AppUI";

const schema = z.object({
  first_name: z.string().trim().min(1, "First name is required").max(100),
  last_name: z.string().trim().min(1, "Last name is required").max(100),
  phone: z.string().trim().refine((value) => !value || /^\+?[0-9][0-9\- ]{6,28}$/.test(value), "Enter a valid phone").optional(),
  password: z.string().min(12, "Use at least 12 characters").regex(/[a-z]/, "Include a lowercase letter").regex(/[A-Z]/, "Include an uppercase letter").regex(/[0-9]/, "Include a number").regex(/[^A-Za-z0-9]/, "Include a symbol"),
  password_confirmation: z.string(),
}).refine((value) => value.password === value.password_confirmation, {
  path: ["password_confirmation"], message: "Passwords do not match",
});

export default function AcceptInvitationPage() {
  const processed = useRef(false);
  const [token, setToken] = useState("");
  const [invitation, setInvitation] = useState(null);
  const [loading, setLoading] = useState(true);
  const [problem, setProblem] = useState("");
  const [success, setSuccess] = useState(false);
  const [show, setShow] = useState(false);
  const { register, handleSubmit, reset, setError, formState: { errors, isSubmitting } } = useForm({
    resolver: zodResolver(schema), defaultValues: { first_name: "", last_name: "", phone: "", password: "", password_confirmation: "" },
  });

  useEffect(() => {
    if (processed.current) return;
    processed.current = true;
    const raw = new URLSearchParams(window.location.hash.slice(1)).get("token") || "";
    window.history.replaceState(null, "", `${window.location.pathname}${window.location.search}`);
    setToken(raw);
    if (!raw) { setProblem("This invitation is invalid or no longer available."); setLoading(false); return; }
    staffInvitationsApi.inspect(raw).then(setInvitation).catch((error) => setProblem(apiMessage(error, "This invitation is invalid or no longer available."))).finally(() => setLoading(false));
  }, []);

  const submit = handleSubmit(async (values) => {
    setProblem("");
    try {
      await staffInvitationsApi.accept({ token, ...values });
      reset();
      setToken("");
      setSuccess(true);
    } catch (error) {
      applyServerErrors(error, setError);
      setProblem(apiMessage(error, "This invitation is invalid or no longer available."));
    }
  });

  return <main className="auth-shell"><aside className="auth-brand"><strong>BUILDWISE</strong><h1>Build with clarity. Manage with confidence.</h1><p>One secure workspace for your company and construction teams.</p></aside><section className="auth-panel">
    <h2>Accept staff invitation</h2>
    <p>{invitation ? `Join ${invitation.company?.name || "your company"} as ${invitation.roles?.join(", ")}.` : "Set up your staff account."}</p>
    {loading && <div role="status">Checking invitation…</div>}
    {problem && <div className="error-banner">{problem}</div>}
    {success ? <><div className="success-banner">Your account was created successfully. You can now sign in.</div><Link className="app-button primary" to="/login">Return to Login</Link></> : !loading && invitation && <form className="form-grid" onSubmit={submit}>
      <AppInput label="First name" required error={errors.first_name?.message} {...register("first_name")} />
      <AppInput label="Last name" required error={errors.last_name?.message} {...register("last_name")} />
      <AppInput label="Phone" error={errors.phone?.message} {...register("phone")} />
      <div />
      <AppInput label="Password" type={show ? "text" : "password"} autoComplete="new-password" required error={errors.password?.message} {...register("password")} />
      <AppInput label="Confirm password" type={show ? "text" : "password"} autoComplete="new-password" required error={errors.password_confirmation?.message} {...register("password_confirmation")} />
      <label className="full permission-check"><input type="checkbox" checked={show} onChange={(event) => setShow(event.target.checked)} />Show passwords</label>
      <div className="full"><AppButton busy={isSubmitting}>Accept invitation</AppButton></div>
    </form>}
    {!success && <Link to="/login">Back to Login</Link>}
  </section></main>;
}
