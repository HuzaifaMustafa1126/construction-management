import { z } from "zod";

export const PHASE_STATUS_LABELS = {
  pending: "Pending",
  active: "Active",
  completed: "Completed",
  delayed: "Delayed",
};

const optionalDate = z.union([z.literal(""), z.iso.date()]).optional();

export const projectPhaseSchema = z.object({
  name: z.string().trim().min(1, "Phase name is required.").max(200),
  description: z.string().trim().max(16000).optional(),
  start_date: optionalDate,
  end_date: optionalDate,
  status: z.enum(Object.keys(PHASE_STATUS_LABELS)),
}).refine(
  ({ start_date, end_date }) => !start_date || !end_date || end_date >= start_date,
  { path: ["end_date"], message: "End date must be on or after the start date." },
);

export function projectPhasePayload(values, dirtyFields) {
  return Object.fromEntries(
    Object.entries(values)
      .filter(([field]) => !dirtyFields || dirtyFields[field])
      .map(([field, value]) => [field, value === "" ? null : value]),
  );
}
