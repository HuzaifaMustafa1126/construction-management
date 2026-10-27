import { z } from "zod";
import { getUserDuplicateErrors } from "./userDuplicates.js";

export function createUserSchema(roles, users, editingId = null) {
  return z
    .object({
      firstName: z.string().trim().min(1, "First name is required."),
      lastName: z.string().trim().min(1, "Last name is required."),
      email: z
        .string()
        .trim()
        .toLowerCase()
        .email("Enter a valid email address."),
      phone: z
        .string()
        .trim()
        .refine(
          (value) =>
            !value ||
            (/^\+?[\d\s().-]+$/.test(value) &&
              value.replace(/\D/g, "").length >= 7 &&
              value.replace(/\D/g, "").length <= 15),
          "Enter a valid phone number with 7-15 digits.",
        ),
      password: z
        .string()
        .refine(
          (value) =>
            (editingId && value === "") ||
            (value.length >= 8 && !!value.trim()),
          "Password must contain at least 8 characters.",
        ),
      role: z
        .string()
        .refine((value) => roles.includes(value), "Select a valid role."),
      department: z
        .string()
        .trim()
        .max(255, "Department must not exceed 255 characters."),
    })
    .superRefine((form, context) => {
      const errors = getUserDuplicateErrors(form, users, editingId);
      for (const [path, message] of Object.entries(errors))
        context.addIssue({ code: "custom", path: [path], message });
    });
}
