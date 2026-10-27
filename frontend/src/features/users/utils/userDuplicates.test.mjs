import test from "node:test";
import assert from "node:assert/strict";
import { getUserDuplicateErrors } from "./userDuplicates.js";
const users = [
  { id: 1, email: "ayesha@example.com", phone: "+92 300 1234567" },
];
test("blocks duplicate email regardless of capitalization and whitespace", () => {
  assert.ok(
    getUserDuplicateErrors({ email: " AYESHA@example.com ", phone: "" }, users)
      .email,
  );
});
test("blocks formatted and international-prefix variants of the same phone", () => {
  for (const phone of ["+92-300-1234567", "0092 (300) 1234567"]) {
    assert.ok(
      getUserDuplicateErrors({ email: "new@example.com", phone }, users).phone,
    );
  }
});
test("allows a user to retain their own email and phone while editing", () => {
  assert.deepEqual(getUserDuplicateErrors(users[0], users, 1), {});
});
test("allows distinct email and phone; empty phones do not collide", () => {
  assert.deepEqual(
    getUserDuplicateErrors(
      { email: "other@example.com", phone: "+92 301 7654321" },
      users,
    ),
    {},
  );
  assert.deepEqual(
    getUserDuplicateErrors({ email: "other@example.com", phone: "" }, [
      { id: 2, email: "third@example.com", phone: "" },
    ]),
    {},
  );
});
