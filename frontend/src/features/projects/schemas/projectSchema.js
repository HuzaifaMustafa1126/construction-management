import { z } from "zod";

const optionalText = (max) => z.string().trim().max(max).optional();
const optionalDate = z.string().optional();

export const projectSchema = z.object({
  name: z.string().trim().min(1, "Project name is required.").max(255),
  code: z.string().trim().min(1, "Project code is required.").max(75).regex(/^[A-Za-z0-9_-]+$/, "Use letters, numbers, hyphens, or underscores."),
  description: optionalText(5000),
  location: optionalText(255),
  address: optionalText(2000),
  city: optionalText(100),
  state: optionalText(100),
  country: optionalText(100),
  postal_code: optionalText(30),
  start_date: optionalDate,
  expected_end_date: optionalDate,
  actual_end_date: optionalDate,
  budget: z.union([z.literal(""), z.coerce.number().min(0).max(9999999999999.99)]).optional(),
}).superRefine((value, context) => {
  if (value.start_date && value.expected_end_date && value.expected_end_date < value.start_date) context.addIssue({ code: "custom", path: ["expected_end_date"], message: "Expected end date cannot be before the start date." });
  if (value.start_date && value.actual_end_date && value.actual_end_date < value.start_date) context.addIssue({ code: "custom", path: ["actual_end_date"], message: "Actual end date cannot be before the start date." });
});

export function projectPayload(values) {
  return Object.fromEntries(Object.entries(values).map(([key, value]) => [key, value === "" ? null : value]));
}

export const PROJECT_STATUSES = ["planning", "active", "on_hold", "completed", "cancelled"];
export const STATUS_TRANSITIONS = { planning: ["active", "cancelled"], active: ["on_hold", "completed", "cancelled"], on_hold: ["active", "cancelled"], completed: [], cancelled: [] };
