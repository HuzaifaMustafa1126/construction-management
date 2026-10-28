export const humanize = (value = "") => value.replaceAll("_", " ").replace(/\b\w/g, (x) => x.toUpperCase());
export const formatReportDate = (value) => value ? new Intl.DateTimeFormat(undefined, { dateStyle: "medium" }).format(new Date(`${value}T00:00:00`)) : "—";
export const formatDateTime = (value) => value ? new Intl.DateTimeFormat(undefined, { dateStyle: "medium", timeStyle: "short" }).format(new Date(value)) : "—";
export const excerpt = (value, length = 80) => !value ? "—" : value.length > length ? `${value.slice(0, length)}…` : value;
