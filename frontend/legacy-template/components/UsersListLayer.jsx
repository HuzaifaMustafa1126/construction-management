import {
  useCallback,
  useEffect,
  useRef,
  useState,
  useSyncExternalStore,
} from "react";
import { createPortal } from "react-dom";

import AddUserLayer from "./AddUserLayer";
import { Link, useLocation } from "react-router-dom";
import {
  Users,
  UserCheck,
  UserX,
  ShieldCheck,
  Search,
  ChevronDown,
  ChevronLeft,
  ChevronRight,
  MoreHorizontal,
  Plus,
} from "lucide-react";
import Dropdown from "react-bootstrap/Dropdown";
import Modal from "react-bootstrap/Modal";
import {
  deleteUser,
  getUsers as fetchUsers,
} from "../services/userService";
import {
  getUsers,
  subscribeUsers,
  roles,
  replaceUsers,
  deleteUser as removeUserFromTable,
  toggleUserStatus,
} from "./users/userStore";
import {
  filterUsers,
  userStatistics,
  paginateUsers,
} from "./users/userListModel";

import "./users/UsersManagement.css";

const emptyFilters = { search: "", role: "", status: "", department: "" };
const roleColors = {
  admin: "gray",
  supervisor: "orange",
  client: "blue",
  "Project Manager": "blue",
  "Site Engineer": "purple",
  Supervisor: "orange",
  "Site Supervisor": "orange",
  Accountant: "red",
  Client: "blue",
  "Labor Manager": "purple",
  Admin: "gray",
  Procurement: "cyan",
};
const departmentColors = {
  Management: "gray",
  Engineering: "blue",
  Finance: "green",
  "Site Operations": "purple",
  Procurement: "cyan",
  Projects: "orange",
};
const badgeColors = ["blue", "purple", "orange", "cyan", "green", "red"];

function getDepartmentColor(department) {
  if (departmentColors[department]) return departmentColors[department];

  const colorIndex = [...department].reduce(
    (total, character) => total + character.charCodeAt(0),
    0,
  );
  return badgeColors[colorIndex % badgeColors.length];
}

function normalizeUser(user) {
  const fullName = String(user.name || "").trim();
  const [fallbackFirstName = "", ...remainingNames] = fullName.split(/\s+/);

  return {
    ...user,
    firstName: user.firstName ?? user.first_name ?? fallbackFirstName,
    lastName: user.lastName ?? user.last_name ?? remainingNames.join(" "),
    lastLogin: user.lastLogin ?? user.last_login ?? null,
    department: user.department ?? user.department_name ?? "",
    status: user.status ?? "Active",
  };
}

export default function UsersListLayer() {
  const users = useSyncExternalStore(subscribeUsers, getUsers);
  const [loadingUsers, setLoadingUsers] = useState(true);
  const [usersError, setUsersError] = useState("");
  const [filters, setFilters] = useState(emptyFilters);
  const [page, setPage] = useState(1);
  const [selected, setSelected] = useState(new Set());
  const [creating, setCreating] = useState(false);
  const [creatingBusy, setCreatingBusy] = useState(false);
  const [viewing, setViewing] = useState(null);
  const location = useLocation();
  const [notice, setNotice] = useState(location.state?.userNotice || "");
  const selectAllRef = useRef(null);
  const visible = filterUsers(users, filters);
  const pagination = paginateUsers(visible, page);
  const stats = userStatistics(users);
  const departments = [
    ...new Set(users.map((user) => user.department).filter(Boolean)),
  ];
  const allSelected =
    pagination.rows.length > 0 &&
    pagination.rows.every((user) => selected.has(user.id));
  const loadUsers = useCallback(async () => {
    setLoadingUsers(true);
    setUsersError("");

    try {
      const apiUsers = await fetchUsers();
      replaceUsers(apiUsers.map(normalizeUser));
    } catch (error) {
      setUsersError(
        error.response?.data?.message || "Failed to load users.",
      );
    } finally {
      setLoadingUsers(false);
    }
  }, []);
  useEffect(() => {
    loadUsers();
  }, [loadUsers]);
  useEffect(() => {
    if (selectAllRef.current)
      selectAllRef.current.indeterminate =
        !allSelected && pagination.rows.some((user) => selected.has(user.id));
  }, [allSelected, pagination.rows, selected]);
  useEffect(() => {
    if (!notice) return;
    const timer = setTimeout(() => setNotice(""), 4000);
    return () => clearTimeout(timer);
  }, [notice]);
  const updateFilter = (name, value) => {
    setFilters((current) => ({ ...current, [name]: value }));
    setPage(1);
  };
  const toggleSelected = (id) =>
    setSelected((current) => {
      const next = new Set(current);
      if (next.has(id)) next.delete(id);
      else next.add(id);
      return next;
    });
  const handleDeleteUser = async (userId) => {
    const confirmed = window.confirm(
      "Are you sure you want to delete this user?",
    );

    if (!confirmed) return;

    try {
      await deleteUser(userId);

      // Remove the deleted user from the table.
      removeUserFromTable(userId);

      // Remove the deleted user from the selected rows too.
      setSelected((previousSelected) => {
        const nextSelected = new Set(previousSelected);
        nextSelected.delete(userId);
        return nextSelected;
      });

      alert("User deleted successfully!");
    } catch (error) {
      alert(error.response?.data?.message || "Failed to delete user.");
    }
  };
  return (
    <section className="users-management" aria-labelledby="users-title">
      <header className="um-heading">
        <div>
          <h1 id="users-title">Users</h1>
          <p>Manage all system users, roles and permissions</p>
        </div>
        <button
          type="button"
          className="um-add-user"
          onClick={() => setCreating(true)}
        >
          <Plus size={17} /> Add User
        </button>
      </header>
      <section className="um-stats" aria-label="User statistics">
        {[
          [Users, stats.total, "Total Users", "blue"],
          [UserCheck, stats.active, "Active Users", "green"],
          [UserX, stats.inactive, "Inactive Users", "red"],
          [ShieldCheck, stats.roles, "User Roles", "purple"],
        ].map(([StatIcon, value, label, color]) => (
          <div className="um-stat" key={label}>
            <span className={`um-stat-icon ${color}`}>
              <StatIcon />
            </span>
            <div>
              <strong>{value}</strong>
              <p>{label}</p>
            </div>
          </div>
        ))}
      </section>
      <section className="um-panel" aria-label="Users table">
        <div className="um-filters">
          <div className="um-search">
            <Search />
            <input
              aria-label="Search users"
              placeholder="Search users by name, email or role..."
              value={filters.search}
              onChange={(event) => updateFilter("search", event.target.value)}
            />
          </div>
          {[
            ["role", "All Roles", roles],
            ["status", "All Status", ["Active", "Inactive"]],
            ["department", "All Departments", departments],
          ].map(([field, label, options]) => (
            <div className="um-select" key={field}>
              <select
                aria-label={label}
                value={filters[field]}
                onChange={(event) => updateFilter(field, event.target.value)}
              >
                <option value="">{label}</option>
                {options.map((option) => (
                  <option key={option}>{option}</option>
                ))}
              </select>
              <ChevronDown />
            </div>
          ))}
          <button
            className="um-reset"
            onClick={() => {
              setFilters(emptyFilters);
              setPage(1);
            }}
          >
            Reset
          </button>
        </div>
        <div className="um-table-scroll">
          <table className="um-table">
            <colgroup>
              {[4, 4, 17, 17, 13, 12.5, 10, 15, 7.5].map((width, index) => (
                <col key={index} style={{ width: `${width}%` }} />
              ))}
            </colgroup>
            <thead>
              <tr>
                <th scope="col">
                  <input
                    ref={selectAllRef}
                    type="checkbox"
                    aria-label="Select all users on this page"
                    checked={allSelected}
                    onChange={() =>
                      setSelected((current) => {
                        const next = new Set(current);
                        pagination.rows.forEach((user) =>
                          allSelected
                            ? next.delete(user.id)
                            : next.add(user.id),
                        );
                        return next;
                      })
                    }
                  />
                </th>
                {[
                  "#",
                  "User",
                  "Email",
                  "Role",
                  "Department",
                  "Status",
                  "Last Login",
                  "Actions",
                ].map((label) => (
                  <th key={label} scope="col">
                    {label}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody>
              {pagination.rows.map((user, index) => (
                <tr key={user.id}>
                  <td>
                    <input
                      type="checkbox"
                      aria-label={`Select ${user.firstName} ${user.lastName}`}
                      checked={selected.has(user.id)}
                      onChange={() => toggleSelected(user.id)}
                    />
                  </td>
                  <td>{pagination.start + index}</td>
                  <td>
                    <div className="um-user">
                      {user.avatar ? (
                        <img src={user.avatar} alt="" />
                      ) : (
                        <span className="um-avatar">
                          {(user.firstName?.[0] || user.email?.[0] || "U")
                            .toUpperCase()}
                          {user.lastName?.[0]?.toUpperCase() || ""}
                        </span>
                      )}
                      <div>
                        <strong>
                          {user.firstName} {user.lastName}
                        </strong>
                        <small>{user.role}</small>
                      </div>
                    </div>
                  </td>
                  <td>{user.email}</td>
                  <td>
                    <span
                      className={`um-badge ${roleColors[user.role] || "gray"}`}
                    >
                      {user.role}
                    </span>
                  </td>
                  <td>
                    {user.department ? (
                      <span
                        className={`um-badge ${getDepartmentColor(user.department)}`}
                      >
                        {user.department}
                      </span>
                    ) : (
                      <span className="um-muted">{"\u2014"}</span>
                    )}
                  </td>
                  <td>
                    <span
                      className={`um-badge ${user.status === "Active" ? "green" : "red"}`}
                    >
                      {user.status}
                    </span>
                  </td>
                  <td>
                    {user.lastLogin ? (
                      <>
                        <span>
                          {new Date(user.lastLogin).toLocaleDateString(
                            "en-US",
                            { month: "short", day: "numeric", year: "numeric" },
                          )}
                        </span>
                        <small className="um-login-time">
                          {new Date(user.lastLogin).toLocaleTimeString(
                            "en-US",
                            { hour: "2-digit", minute: "2-digit" },
                          )}
                        </small>
                      </>
                    ) : (
                      <span className="um-muted">Never</span>
                    )}
                  </td>
                  <td>
                    <Dropdown align="end">
                      <Dropdown.Toggle
                        className="um-row-actions"
                        aria-label={`Actions for ${user.firstName} ${user.lastName}`}
                      >
                        <MoreHorizontal />
                      </Dropdown.Toggle>
                      {createPortal(
                        <Dropdown.Menu className="um-action-menu">
                          <Dropdown.Item onClick={() => setViewing(user)}>
                            View User
                          </Dropdown.Item>
                          <Dropdown.Item
                            as={Link}
                            to={`/users/${user.id}/edit`}
                          >
                            Edit User
                          </Dropdown.Item>
                          <Dropdown.Item
                            onClick={() => toggleUserStatus(user.id)}
                          >
                            Set{" "}
                            {user.status === "Active" ? "Inactive" : "Active"}
                          </Dropdown.Item>
                          <Dropdown.Divider />
                          <Dropdown.Item
                            className="text-danger"
                            onClick={() => handleDeleteUser(user.id)}
                          >
                            Delete User
                          </Dropdown.Item>
                        </Dropdown.Menu>,
                        document.body,
                      )}
                    </Dropdown>
                  </td>
                </tr>
              ))}
              {!pagination.rows.length && (
                <tr>
                  <td colSpan={9} className="um-empty">
                    {loadingUsers
                      ? "Loading users..."
                      : usersError || "No users match your filters."}
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
        <footer className="um-footer">
          <span>
            Showing {pagination.start} to {pagination.end} of {visible.length}{" "}
            users
          </span>
          <nav aria-label="User pages">
            <button
              aria-label="Previous page"
              disabled={pagination.page === 1}
              onClick={() => setPage(pagination.page - 1)}
            >
              <ChevronLeft />
            </button>
            {Array.from(
              { length: pagination.pages },
              (_, index) => index + 1,
            ).map((number) => (
              <button
                key={number}
                className={pagination.page === number ? "active" : ""}
                aria-current={pagination.page === number ? "page" : undefined}
                onClick={() => setPage(number)}
              >
                {number}
              </button>
            ))}
            <button
              aria-label="Next page"
              disabled={pagination.page === pagination.pages}
              onClick={() => setPage(pagination.page + 1)}
            >
              <ChevronRight />
            </button>
          </nav>
        </footer>
      </section>
      {notice && (
        <div className="um-notice" role="status">
          {notice}
        </div>
      )}
      <Modal
        show={creating}
        onHide={() => {
          if (!creatingBusy) setCreating(false);
        }}
        centered
        className="user-create-dialog"
        backdropClassName="user-create-backdrop"
        aria-labelledby="add-user-title"
        unmountOnExit
      >
        <AddUserLayer
          onCancel={() => setCreating(false)}
          onSaved={async (message) => {
            setCreating(false);
            setNotice(message);
            await loadUsers();
          }}
          onBusyChange={setCreatingBusy}
        />
      </Modal>
      <Modal
        show={!!viewing}
        onHide={() => setViewing(null)}
        centered
        aria-labelledby="user-view-title"
      >
        <Modal.Header closeButton>
          <Modal.Title id="user-view-title" className="fs-5">
            User Details
          </Modal.Title>
        </Modal.Header>
        <Modal.Body>
          {viewing && (
            <dl>
              <dt>Name</dt>
              <dd>
                {viewing.firstName} {viewing.lastName}
              </dd>
              <dt>Email</dt>
              <dd>{viewing.email}</dd>
              <dt>Phone</dt>
              <dd>{viewing.phone || "Not provided"}</dd>
              <dt>Role</dt>
              <dd>{viewing.role}</dd>
              <dt>Department</dt>
              <dd>{viewing.department}</dd>
              <dt>Status</dt>
              <dd>{viewing.status}</dd>
            </dl>
          )}
        </Modal.Body>
      </Modal>
    </section>
  );
}
