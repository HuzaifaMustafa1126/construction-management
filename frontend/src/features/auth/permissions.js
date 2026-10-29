export const legacyPermissionAliases = Object.freeze({
  "projects.update": "projects.edit",
  "site_reports.view": "daily_reports.view",
  "site_reports.create": "daily_reports.create",
  "site_reports.approve": "daily_reports.approve",
});

export function canonicalPermission(permission) {
  return legacyPermissionAliases[permission] || permission;
}

export function normalizePermissions(permissions) {
  return new Set(
    [...permissions].map(canonicalPermission).filter(Boolean),
  );
}
