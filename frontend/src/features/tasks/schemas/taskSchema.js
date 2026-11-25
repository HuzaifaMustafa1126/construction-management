import { z } from "zod";
import { taskPriorities, taskStatuses } from "../taskLabels.js";

const projectId = z.string().regex(/^[1-9][0-9]*$/, "Select a project.");
const optionalId = z.string().regex(/^[1-9][0-9]*$/, "Select a valid option.").nullable();
const date = z.union([z.literal(""), z.iso.date().refine((value) => value >= "1000-01-01", {
  message: "Enter a date on or after 1000-01-01.",
})]);

const optionalDecimal = (max) => z.string().trim().refine(
  (value) => value === "" || (/^(?:\d+(?:\.\d{0,2})?|\.\d{1,2})$/.test(value) && Number(value) <= max),
  { message: `Enter a value from 0 to ${max} with up to two decimal places.` },
);

export const taskSchema = z.object({
  project_id: projectId,
  title: z.string().trim().min(1, "Enter a task title.")
    .refine((value) => Array.from(value).length <= 255, "Use at most 255 characters."),
  description: z.string().refine((value) => Array.from(value).length <= 16000, "Use at most 16,000 characters."),
  phase_id: optionalId,
  milestone_id: optionalId,
  assigned_to: optionalId,
  priority: z.enum(Object.keys(taskPriorities)),
  status: z.enum(Object.keys(taskStatuses)),
  start_date: date,
  due_date: date,
  estimated_hours: optionalDecimal(999999.99),
  actual_hours: optionalDecimal(999999.99).optional(),
  progress_percentage: optionalDecimal(100),
}).refine(
  ({ start_date, due_date }) => !start_date || !due_date || due_date >= start_date,
  { path: ["due_date"], message: "The due date must be on or after the start date." },
);

export function createTaskPayload(values, { canAssign, canComplete }) {
  const payload = {
    title: values.title,
    description: values.description || null,
    phase_id: values.phase_id,
    milestone_id: values.milestone_id,
    priority: values.priority,
    start_date: values.start_date || null,
    due_date: values.due_date || null,
    estimated_hours: values.estimated_hours === "" ? null : Number(values.estimated_hours),
  };
  if (canAssign && values.assigned_to) payload.assigned_to = values.assigned_to;
  if (canComplete && values.status !== "todo") payload.status = values.status;
  if (payload.status !== "completed" && values.progress_percentage !== "") {
    payload.progress_percentage = Number(values.progress_percentage);
  }
  return payload;
}

export function updateTaskPayload(values, original, dirtyFields, permissions) {
  const payload = createTaskPayload(values, permissions);
  if (permissions.canAssign) payload.assigned_to = values.assigned_to;
  if (permissions.canComplete) payload.status = values.status;
  if (values.status === "completed") delete payload.progress_percentage;
  if (values.actual_hours !== undefined) {
    payload.actual_hours = values.actual_hours === "" ? null : Number(values.actual_hours);
  }

  return Object.fromEntries(Object.entries(payload).filter(([field, value]) => {
    if (!dirtyFields[field]) return false;
    const previous = original[field];
    if (field === "description") return (value?.trim() || null) !== (previous?.trim() || null);
    if (field === "title") return value !== previous.trim();
    if (field.endsWith("_id") || field === "assigned_to") {
      return value !== (previous == null ? null : String(previous));
    }
    return value !== previous;
  }));
}
