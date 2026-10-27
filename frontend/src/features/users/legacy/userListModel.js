export function filterUsers(
  users,
  { search = "", role = "", status = "", department = "" },
) {
  const query = search.trim().toLowerCase();
  return users.filter(
    (user) =>
      (!query ||
        `${user.firstName} ${user.lastName} ${user.email} ${user.role}`
          .toLowerCase()
          .includes(query)) &&
      (!role || user.role === role) &&
      (!status || user.status === status) &&
      (!department || user.department === department),
  );
}
export function userStatistics(users) {
  return {
    total: users.length,
    active: users.filter((user) => user.status === "Active").length,
    inactive: users.filter((user) => user.status === "Inactive").length,
    roles: new Set(users.map((user) => user.role)).size,
  };
}
export function paginateUsers(users, page, size = 8) {
  const pages = Math.max(1, Math.ceil(users.length / size));
  const current = Math.max(1, Math.min(page, pages));
  const start = (current - 1) * size;
  return {
    rows: users.slice(start, start + size),
    page: current,
    pages,
    start: users.length ? start + 1 : 0,
    end: Math.min(start + size, users.length),
  };
}
