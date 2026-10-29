export function ownsMaterialRequestOrIsCompanyAdmin(user, request) {
  const roles = Array.isArray(user?.roles) ? user.roles : [];

  return (
    roles.includes("company_admin") ||
    (request?.requested_by != null &&
      Number(request.requested_by) === Number(user?.id))
  );
}
