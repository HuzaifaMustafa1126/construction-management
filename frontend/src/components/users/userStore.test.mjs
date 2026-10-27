import test from "node:test";
import assert from "node:assert/strict";
import {
  getUsers,
  saveUser,
  deleteUser,
  toggleUserStatus,
  subscribeUsers,
} from "./userStore.js";
test("creation, updates, uniqueness and removal share state without storing passwords", () => {
  const before = getUsers().length;
  let notifications = 0;
  const unsubscribe = subscribeUsers(() => notifications++);
  saveUser({
    firstName: "New",
    lastName: "User",
    email: "new@example.com",
    phone: "+44 1234 567890",
    role: "Admin",
    password: "secret123",
  });
  const user = getUsers().find((entry) => entry.email === "new@example.com");
  assert.equal(getUsers().length, before + 1);
  assert.equal("password" in user, false);
  assert.throws(
    () => saveUser({ ...user, email: "NEW@example.com" }),
    /Duplicate/,
  );
  assert.throws(
    () =>
      saveUser({
        ...user,
        email: "other@example.com",
        phone: "+44-1234-567890",
      }),
    /Duplicate/,
  );
  saveUser(
    { ...user, firstName: "Edited", role: "Accountant", password: "" },
    user.id,
  );
  assert.equal(
    getUsers().find((entry) => entry.id === user.id).department,
    "Finance",
  );
  toggleUserStatus(user.id);
  assert.equal(
    getUsers().find((entry) => entry.id === user.id).status,
    "Inactive",
  );
  deleteUser(user.id);
  assert.equal(getUsers().length, before);
  assert.equal(notifications, 4);
  unsubscribe();
});
