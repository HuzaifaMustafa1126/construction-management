/* eslint-disable react/prop-types */
import { forwardRef, useId, useLayoutEffect, useRef } from "react";
import { createPortal } from "react-dom";
import { useAuth } from "../../features/auth/context/AuthContext";
import { AppIcon } from "./AppIcon";
import { ModalScope, registerModal, topModal } from "./modalScope";

export { AppIcon };
export { SearchableSelect } from "./SearchableSelect";
export function AppButton({ variant = "primary", busy, disabled, className = "", children, ...props }) {
  return <button {...props} className={`app-button ${variant} ${className}`} disabled={busy || disabled} aria-busy={busy || undefined}>
    {busy ? "Please wait…" : children}
  </button>;
}
export const AppInput = forwardRef(function AppInput(
  { label, error, description, required, ...props },
  ref,
) {
  const generatedId = useId();
  const id = props.id || props.name || generatedId;
  return (
    <label className="app-field" htmlFor={id}>
      <span>
        {label}
        {required && " *"}
      </span>
      <input
        {...props}
        ref={ref}
        id={id}
        aria-invalid={!!error}
        aria-describedby={[props["aria-describedby"], description && `${id}-description`, error && `${id}-error`].filter(Boolean).join(" ") || undefined}
        required={required}
      />
      {description && <small id={`${id}-description`} className="field-description">{description}</small>}
      {error && (
        <small id={`${id}-error`} role="alert">
          {error}
        </small>
      )}
    </label>
  );
});
export const AppSelect = forwardRef(function AppSelect({ label, error, description, children, ...props }, ref) {
  const generatedId = useId();
  const id = props.id || props.name || generatedId;
  return (
    <label className="app-field" htmlFor={id}>
      <span>{label}</span>
      <select {...props} ref={ref} id={id} aria-invalid={!!error} aria-describedby={[props["aria-describedby"], description && `${id}-description`, error && `${id}-error`].filter(Boolean).join(" ") || undefined}>
        {children}
      </select>
      {description && <small id={`${id}-description`} className="field-description">{description}</small>}
      {error && <small id={`${id}-error`} role="alert">{error}</small>}
    </label>
  );
});
export function AppModal({ open, title, onClose, children }) {
  const dialogRef = useRef(null);
  const onCloseRef = useRef(onClose);
  const titleId = useId();
  useLayoutEffect(() => { onCloseRef.current = onClose; }, [onClose]);
  useLayoutEffect(() => {
    if (!open) return undefined;
    const dialog = dialogRef.current;
    const previous = document.activeElement;
    const unregister = registerModal(dialog);
    const onKeyDown = (event) => {
      if (topModal() !== dialog || event.defaultPrevented) return;
      if (event.key === "Escape") {
        event.preventDefault();
        onCloseRef.current();
      } else if (event.key === "Tab") {
        const targets = [...dialog.querySelectorAll('button:not(:disabled), a[href], input:not(:disabled):not([type="hidden"]), select:not(:disabled), textarea:not(:disabled), [tabindex]:not([tabindex="-1"])')]
          .filter((element) => element.tabIndex >= 0 && !element.matches(":disabled") && !element.closest('[hidden], [inert], [aria-hidden="true"]') && getComputedStyle(element).display !== "none" && getComputedStyle(element).visibility !== "hidden");
        const first = targets[0];
        const last = targets[targets.length - 1];
        if (!first) { event.preventDefault(); dialog.focus(); }
        else if (event.shiftKey && (document.activeElement === first || document.activeElement === dialog)) {
          event.preventDefault(); last.focus();
        } else if (!event.shiftKey && (document.activeElement === last || document.activeElement === dialog)) {
          event.preventDefault(); first.focus();
        }
      }
    };
    const keepFocus = (event) => {
      if (topModal() === dialog && !dialog.contains(event.target)) dialog.focus();
    };
    document.addEventListener("keydown", onKeyDown);
    document.addEventListener("focusin", keepFocus);
    dialog.focus();
    return () => {
      const wasTop = topModal() === dialog;
      document.removeEventListener("keydown", onKeyDown);
      document.removeEventListener("focusin", keepFocus);
      unregister();
      if (wasTop) {
        const next = topModal();
        if (previous?.isConnected && (!next || next.contains(previous))) previous.focus?.();
        else next?.focus();
      }
    };
  }, [open]);
  if (!open) return null;
  return createPortal(
    <ModalScope.Provider value={dialogRef}>
      <div className="app-modal-backdrop" role="presentation"
        onMouseDown={(event) => event.target === event.currentTarget && topModal() === dialogRef.current && onCloseRef.current()}>
        <section ref={dialogRef} tabIndex={-1} className="app-modal" role="dialog" aria-modal="true" aria-labelledby={titleId}>
          <header>
            <h2 id={titleId}>{title}</h2>
            <button className="icon-button" type="button" onClick={onClose} aria-label="Close"><AppIcon name="close" /></button>
          </header>
          {children}
        </section>
      </div>
    </ModalScope.Provider>, document.body,
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
