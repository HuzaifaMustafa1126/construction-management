import test from "node:test";
import assert from "node:assert/strict";
import { filterUsers, paginateUsers, userStatistics } from "./userListModel.js";
const users = [
  {
    firstName: "Ayesha",
    lastName: "Khan",
    email: "ayesha@example.com",
    role: "Admin",
    status: "Active",
    department: "Management",
  },
  {
    firstName: "Hamza",
    lastName: "Ali",
    email: "hamza@example.com",
    role: "Supervisor",
    status: "Inactive",
    department: "Projects",
  },
];
test("search and all three filters combine", () => {
  assert.deepEqual(
    filterUsers(users, {
      search: " AYESHA ",
      role: "Admin",
      status: "Active",
      department: "Management",
    }),
    [users[0]],
  );
  assert.equal(
    filterUsers(users, { search: "example.com", status: "Inactive" }).length,
    1,
  );
  assert.equal(
    filterUsers(users, { role: "Admin", department: "Projects" }).length,
    0,
  );
});
test("stats count records, status and distinct roles", () => {
  assert.deepEqual(userStatistics(users), {
    total: 2,
    active: 1,
    inactive: 1,
    roles: 2,
  });
});
test("pagination clamps pages after filtering/deletion and handles empty results", () => {
  assert.deepEqual(paginateUsers(users, 3, 1), {
    rows: [users[1]],
    page: 2,
    pages: 2,
    start: 2,
    end: 2,
  });
  assert.deepEqual(paginateUsers([], 2), {
    rows: [],
    page: 1,
    pages: 1,
    start: 0,
    end: 0,
  });
});
