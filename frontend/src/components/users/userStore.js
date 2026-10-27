import { getUserDuplicateErrors } from "../../utils/userDuplicates.js";

export const roles = [
  "Admin",
  "Project Manager",
  "Site Engineer",
  "Site Supervisor",
  "Accountant",
  "Client",
  "Labor Manager",
  "Procurement",
];
// Existing local sample data. Replace this store with the user API when available.
let users = [];
const listeners = new Set();
export const getUsers = () => users;
export const subscribeUsers = (listener) => {
  listeners.add(listener);
  return () => listeners.delete(listener);
};
function publish(next) {
  users = next;
  listeners.forEach((listener) => listener());
}
export function replaceUsers(nextUsers) {
  publish(nextUsers);
}
export function saveUser(data, editingId = null) {
  const errors = getUserDuplicateErrors(data, users, editingId);
  if (Object.keys(errors).length) {
    const error = new Error("Duplicate user details.");
    error.fields = errors;
    throw error;
  }
  const { password, ...details } = data;
  void password;
  if (editingId) {
    if (!users.some((user) => user.id === editingId))
      throw new Error("User not found.");
    publish(
      users.map((user) =>
        user.id === editingId
          ? {
              ...user,
              ...details,
              department: details.department ?? user.department,
            }
          : user,
      ),
    );
  } else
    publish([
      ...users,
      {
        ...details,
        id: Date.now(),
        department: details.department || "",
        status: "Active",
      },
    ]);
}
export function deleteUser(id) {
  publish(users.filter((user) => user.id !== id));
}
export function toggleUserStatus(id) {
  publish(
    users.map((user) =>
      user.id === id
        ? { ...user, status: user.status === "Active" ? "Inactive" : "Active" }
        : user,
    ),
  );
}
