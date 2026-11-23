import { z } from "zod";

export const MILESTONE_STATUS_LABELS = {
  pending: "Pending", in_progress: "In progress", completed: "Completed", delayed: "Delayed",
};

const date = z.iso.date().refine((value) => value >= "1000-01-01", "Use a date on or after January 1, 1000.");

export const milestoneSchema = z.object({
  name: z.string().trim().min(1, "Milestone name is required.").max(200),
  description: z.string().trim().max(16000).nullish(),
  due_date: date,
  phase_id: z.number().int().positive().nullish(),
  status: z.enum(Object.keys(MILESTONE_STATUS_LABELS)),
  completed_date: z.union([date, z.literal("")]).nullish(),
}).refine(
  ({ status, completed_date }) => status === "completed" || !completed_date,
  { path: ["completed_date"], message: "A completion date requires completed status." },
);

export function milestonePayload(values, dirtyFields) {
  const payload = Object.fromEntries(
    Object.entries(values)
      .filter(([field, value]) =>
        value !== undefined && field !== "completed_date" && (!dirtyFields || dirtyFields[field]),
      )
      .map(([field, value]) => [field, value === "" ? null : value]),
  );
  if (values.status === "completed" && values.completed_date && (!dirtyFields || dirtyFields.completed_date)) {
    payload.completed_date = values.completed_date;
  } else if (dirtyFields?.status && values.status !== "completed") {
    payload.completed_date = null;
  }
  return payload;
}
