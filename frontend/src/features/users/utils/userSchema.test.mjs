import test from "node:test";
import assert from "node:assert/strict";
import { createUserSchema } from "./userSchema.js";
const roles = ["Admin", "Site Engineer"];
const users = [
  {
    id: 1,
    firstName: "Ayesha",
    lastName: "Khan",
    email: "ayesha@example.com",
    phone: "+92 300 1234567",
    role: "Admin",
  },
];
const valid = {
  firstName: " New ",
  lastName: " User ",
  email: " NEW@example.com ",
  phone: "",
  password: "Password123",
  role: "Site Engineer",
};
test("trims names, normalizes email, and permits an empty phone", () => {
  const result = createUserSchema(roles, users).parse(valid);
  assert.equal(result.firstName, "New");
  assert.equal(result.email, "new@example.com");
});
test("rejects blank names, invalid email, short password, phone and role", () => {
  const result = createUserSchema(roles, users).safeParse({
    ...valid,
    firstName: " ",
    lastName: "",
    email: "bad",
    password: "short",
    phone: "abc",
    role: "Other",
  });
  assert.equal(result.success, false);
  assert.deepEqual(
    new Set(result.error.issues.map((issue) => issue.path[0])),
    new Set(["firstName", "lastName", "email", "password", "phone", "role"]),
  );
});
test("rejects duplicate email and formatted duplicate phone", () => {
  const result = createUserSchema(roles, users).safeParse({
    ...valid,
    email: "AYESHA@example.com",
    phone: "0092-300-1234567",
  });
  assert.equal(result.success, false);
  assert.deepEqual(
    result.error.issues.map((issue) => issue.path[0]),
    ["email", "phone"],
  );
});
test("editing allows unchanged identifiers and blank password", () => {
  assert.equal(
    createUserSchema(roles, users, 1).safeParse({ ...users[0], password: "" })
      .success,
    true,
  );
});
