import {
  forwardRef,
  type ButtonHTMLAttributes,
  type InputHTMLAttributes,
  type PropsWithChildren,
  type TextareaHTMLAttributes,
} from "react";
import { AppIcon } from "./AppIcon";
import type { AppIconName } from "../icons/iconRegistry";
export const IconButton = ({
  icon,
  label,
  ...props
}: {
  icon: AppIconName;
  label: string;
} & ButtonHTMLAttributes<HTMLButtonElement>) => (
  <button className="icon-button" aria-label={label} title={label} {...props}>
    <AppIcon name={icon} />
  </button>
);
export const ButtonGroup = ({ children }: PropsWithChildren) => (
  <div className="button-group" role="group">
    {children}
  </div>
);
export const AppTextarea = forwardRef<
  HTMLTextAreaElement,
  {
    label: string;
    error?: string;
  } & TextareaHTMLAttributes<HTMLTextAreaElement>
>(({ label, error, ...props }, ref) => (
  <label className="app-field">
    <span>{label}</span>
    <textarea ref={ref} aria-invalid={!!error} {...props} />
    {error && <small role="alert">{error}</small>}
  </label>
));
AppTextarea.displayName = "AppTextarea";
export const AppCheckbox = forwardRef<
  HTMLInputElement,
  { label: string } & InputHTMLAttributes<HTMLInputElement>
>(({ label, ...props }, ref) => (
  <label className="check-control">
    <input ref={ref} type="checkbox" {...props} />
    <span>{label}</span>
  </label>
));
AppCheckbox.displayName = "AppCheckbox";
export const AppRadio = forwardRef<
  HTMLInputElement,
  { label: string } & InputHTMLAttributes<HTMLInputElement>
>(({ label, ...props }, ref) => (
  <label className="check-control">
    <input ref={ref} type="radio" {...props} />
    <span>{label}</span>
  </label>
));
AppRadio.displayName = "AppRadio";
export const AppSwitch = forwardRef<
  HTMLInputElement,
  { label: string } & InputHTMLAttributes<HTMLInputElement>
>(({ label, ...props }, ref) => (
  <label className="switch-control">
    <input ref={ref} type="checkbox" role="switch" {...props} />
    <span>{label}</span>
  </label>
));
AppSwitch.displayName = "AppSwitch";
export const AppDatePicker = forwardRef<
  HTMLInputElement,
  { label: string } & InputHTMLAttributes<HTMLInputElement>
>(({ label, ...props }, ref) => (
  <label className="app-field">
    <span>{label}</span>
    <input ref={ref} type="date" {...props} />
  </label>
));
AppDatePicker.displayName = "AppDatePicker";
export function FormField({
  label,
  error,
  children,
}: PropsWithChildren<{ label: string; error?: string }>) {
  return (
    <label className="app-field">
      <span>{label}</span>
      {children}
      {error && <FormError>{error}</FormError>}
    </label>
  );
}
export const FormError = ({ children }: PropsWithChildren) => (
  <small className="form-error" role="alert">
    {children}
  </small>
);
export function AppDropdown({
  label,
  children,
}: PropsWithChildren<{ label: string }>) {
  return (
    <details className="app-dropdown">
      <summary>
        {label}
        <AppIcon name="dropdown" size="xs" />
      </summary>
      <div>{children}</div>
    </details>
  );
}
export const AppTooltip = ({
  text,
  children,
}: PropsWithChildren<{ text: string }>) => (
  <span className="app-tooltip" data-tooltip={text}>
    {children}
  </span>
);
export const AppSpinner = () => (
  <span className="app-spinner" role="status" aria-label="Loading" />
);
export const AppSkeleton = () => (
  <span className="app-skeleton" aria-hidden="true" />
);
export function AppAlert({
  tone = "info",
  children,
}: PropsWithChildren<{ tone?: "info" | "success" | "warning" | "danger" }>) {
  return (
    <div
      className={`app-alert ${tone}`}
      role={tone === "danger" ? "alert" : "status"}
    >
      {children}
    </div>
  );
}
export const PageContainer = ({ children }: PropsWithChildren) => (
  <div className="page-container">{children}</div>
);
export const FilterBar = ({ children }: PropsWithChildren) => (
  <div className="filter-bar">{children}</div>
);
export const Breadcrumbs = ({ items }: { items: string[] }) => (
  <nav className="breadcrumbs" aria-label="Breadcrumb">
    {items.map((x, i) => (
      <span key={`${x}-${i}`}>{x}</span>
    ))}
  </nav>
);
export const StatCard = ({
  label,
  value,
}: {
  label: string;
  value: string | number;
}) => (
  <section className="stat-card">
    <small>{label}</small>
    <strong>{value}</strong>
  </section>
);
export const TabNavigation = ({
  items,
  active,
  onChange,
}: {
  items: string[];
  active: string;
  onChange: (v: string) => void;
}) => (
  <div className="tabs" role="tablist">
    {items.map((x) => (
      <button
        type="button"
        role="tab"
        aria-selected={x === active}
        onClick={() => onChange(x)}
        key={x}
      >
        {x}
      </button>
    ))}
  </div>
);
export function AppDrawer({
  open,
  title,
  onClose,
  children,
}: PropsWithChildren<{ open: boolean; title: string; onClose: () => void }>) {
  if (!open) return null;
  return (
    <div
      className="drawer-backdrop"
      onMouseDown={(e) => e.target === e.currentTarget && onClose()}
    >
      <aside className="app-drawer" role="dialog" aria-modal="true">
        <header>
          <h2>{title}</h2>
          <IconButton icon="close" label="Close" onClick={onClose} />
        </header>
        {children}
      </aside>
    </div>
  );
}
