/* eslint-disable react/prop-types */
import { forwardRef, useEffect, useRef } from "react";
import { createPortal } from "react-dom";
import { useAuth } from "../../features/auth/context/AuthContext";
import { AppIcon } from "./AppIcon";

export { AppIcon };
export function AppButton({ variant = "primary", busy, children, ...props }) {
  return (
    <button
      className={`app-button ${variant}`}
      disabled={busy || props.disabled}
      {...props}
    >
      {busy ? "Please wait…" : children}
    </button>
  );
}
export const AppInput = forwardRef(function AppInput(
  { label, error, required, ...props },
  ref,
) {
  const id = props.id || props.name;
  return (
    <label className="app-field" htmlFor={id}>
      <span>
        {label}
        {required && " *"}
      </span>
      <input
        ref={ref}
        id={id}
        aria-invalid={!!error}
        aria-describedby={error ? `${id}-error` : undefined}
        required={required}
        {...props}
      />
      {error && (
        <small id={`${id}-error`} role="alert">
          {error}
        </small>
      )}
    </label>
  );
});
export const AppSelect = forwardRef(function AppSelect({ label, error, children, ...props }, ref) {
  const id = props.id || props.name;
  return (
    <label className="app-field" htmlFor={id}>
      <span>{label}</span>
      <select ref={ref} id={id} aria-invalid={!!error} aria-describedby={error ? `${id}-error` : undefined} {...props}>
        {children}
      </select>
      {error && <small id={`${id}-error`} role="alert">{error}</small>}
    </label>
  );
});
export function AppModal({ open, title, onClose, children }) {
  const dialogRef = useRef(null);
  useEffect(() => {
    if (!open) return undefined;
    const previous = document.activeElement;
    const onKeyDown = (event) => {
      if (event.key === "Escape") onClose();
    };
    document.addEventListener("keydown", onKeyDown);
    dialogRef.current?.focus();
    return () => {
      document.removeEventListener("keydown", onKeyDown);
      previous?.focus?.();
    };
  }, [open, onClose]);
  if (!open) return null;
  return createPortal(
    <div
      className="app-modal-backdrop"
      role="presentation"
      onMouseDown={(e) => e.target === e.currentTarget && onClose()}
    >
      <section
        ref={dialogRef}
        tabIndex={-1}
        className="app-modal"
        role="dialog"
        aria-modal="true"
        aria-labelledby="modal-title"
      >
        <header>
          <h2 id="modal-title">{title}</h2>
          <button type="button" onClick={onClose} aria-label="Close">
            <AppIcon name="close" />
          </button>
        </header>
        {children}
      </section>
    </div>,
    document.body,
  );
}
export function SectionCard({ title, action = null, children }) {
  return (
    <section className="section-card">
      <header>
        <h2>{title}</h2>
        {action}
      </header>
      {children}
    </section>
  );
}
export function PageHeader({ title, description, action = null }) {
  return (
    <header className="page-header">
      <div>
        <h1>{title}</h1>
        <p>{description}</p>
      </div>
      {action}
    </header>
  );
}
export function EmptyState({ title = "Nothing here yet", description }) {
  return (
    <div className="empty-state">
      <AppIcon name="empty" size={34} />
      <strong>{title}</strong>
      {description && <p>{description}</p>}
    </div>
  );
}
export function AppBadge({ children, tone = "neutral" }) {
  return <span className={`app-badge ${tone}`}>{children}</span>;
}
export function ConfirmDialog({
  open,
  title,
  message,
  busy,
  confirmLabel = "Confirm",
  onConfirm,
  onClose,
}) {
  return (
    <AppModal open={open} title={title} onClose={onClose}>
      <p>{message}</p>
      <div className="modal-actions">
        <AppButton variant="secondary" onClick={onClose}>
          Cancel
        </AppButton>
        <AppButton variant="danger" busy={busy} onClick={onConfirm}>
          {confirmLabel}
        </AppButton>
      </div>
    </AppModal>
  );
}
export function PermissionGuard({ permission, children, fallback = null, loadingFallback = null }) {
  const { can, loading } = useAuth();
  if (loading) return loadingFallback;
  return can(permission) ? children : fallback;
}
