const normalizeEmail = (value) =>
  String(value ?? "")
    .trim()
    .toLowerCase();
// Compare formatting variants without guessing a country for local numbers.
const normalizePhone = (value) =>
  String(value ?? "")
    .replace(/\D/g, "")
    .replace(/^00/, "");

export function getUserDuplicateErrors(form, users, editingId = null) {
  const errors = {};
  const email = normalizeEmail(form.email);
  const phone = normalizePhone(form.phone);
  for (const user of users) {
    if (user.id === editingId) continue;
    if (email && normalizeEmail(user.email) === email)
      errors.email = "This email address is already registered.";
    if (phone && normalizePhone(user.phone) === phone)
      errors.phone = "This phone number is already registered.";
  }
  return errors;
}
