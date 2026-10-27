/* eslint-disable react/prop-types */
import { forwardRef } from "react";
import { AppIcon } from "./AppIcon";
export const IconButton = ({
  icon,
  label,
  ...props
}) => (
  <button className="icon-button" aria-label={label} title={label} {...props}>
    <AppIcon name={icon} />
  </button>
);
export const ButtonGroup = ({ children }) => (
  <div className="button-group" role="group">
    {children}
  </div>
);
export const AppTextarea = forwardRef(({ label, error, ...props }, ref) => {
  const id = props.id || props.name;
  return <label className="app-field" htmlFor={id}>
    <span>{label}</span>
    <textarea ref={ref} id={id} aria-invalid={!!error} aria-describedby={error ? `${id}-error` : undefined} {...props} />
    {error && <small id={`${id}-error`} role="alert">{error}</small>}
  </label>
});
AppTextarea.displayName = "AppTextarea";
export const AppCheckbox = forwardRef(({ label, ...props }, ref) => (
  <label className="check-control">
    <input ref={ref} type="checkbox" {...props} />
    <span>{label}</span>
  </label>
));
AppCheckbox.displayName = "AppCheckbox";
export const AppRadio = forwardRef(({ label, ...props }, ref) => (
  <label className="check-control">
    <input ref={ref} type="radio" {...props} />
    <span>{label}</span>
  </label>
));
AppRadio.displayName = "AppRadio";
export const AppSwitch = forwardRef(({ label, ...props }, ref) => (
  <label className="switch-control">
    <input ref={ref} type="checkbox" role="switch" {...props} />
    <span>{label}</span>
  </label>
));
AppSwitch.displayName = "AppSwitch";
export const AppDatePicker = forwardRef(({ label, ...props }, ref) => (
  <label className="app-field">
    <span>{label}</span>
    <input ref={ref} type="date" {...props} />
  </label>
));
AppDatePicker.displayName = "AppDatePicker";
export function FormField({ label, error, children }) {
  return (
    <label className="app-field">
      <span>{label}</span>
      {children}
      {error && <FormError>{error}</FormError>}
    </label>
  );
}
export const FormError = ({ children }) => (
  <small className="form-error" role="alert">
    {children}
  </small>
);
export function AppDropdown({ label, children }) {
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
export const AppTooltip = ({ text, children }) => (
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
export function AppAlert({ tone = "info", children }) {
  return (
    <div
      className={`app-alert ${tone}`}
      role={tone === "danger" ? "alert" : "status"}
    >
      {children}
    </div>
  );
}
export const PageContainer = ({ children }) => (
  <div className="page-container">{children}</div>
);
export const FilterBar = ({ children }) => (
  <div className="filter-bar">{children}</div>
);
export const Breadcrumbs = ({ items }) => (
  <nav className="breadcrumbs" aria-label="Breadcrumb">
    {items.map((x, i) => (
      <span key={`${x}-${i}`}>{x}</span>
    ))}
  </nav>
);
export const StatCard = ({ label, value }) => (
  <section className="stat-card">
    <small>{label}</small>
    <strong>{value}</strong>
  </section>
);
export const TabNavigation = ({ items, active, onChange }) => (
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
export function AppDrawer({ open, title, onClose, children }) {
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
