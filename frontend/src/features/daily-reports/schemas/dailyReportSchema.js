import { z } from "zod";

export const REPORT_STATUSES = ["draft", "submitted", "approved", "changes_required"];
export const WORKER_TYPES = ["mason", "helper", "electrician", "plumber", "carpenter", "painter", "steel_fixer", "operator", "other"];
const optionalText = (max) => z.string().max(max).optional();

export const dailyReportSchema = z.object({
  project_id: z.string().min(1, "Project is required."),
  report_date: z.string().min(1, "Report date is required."),
  title: z.string().trim().min(1, "Title is required.").max(255),
  work_completed: z.string().trim().min(1, "Completed work is required.").max(10000),
  work_in_progress: optionalText(10000), issues: optionalText(10000), delays: optionalText(10000),
  delay_reason: optionalText(5000), weather_conditions: optionalText(255), general_notes: optionalText(10000),
  progress_percentage: z.union([z.literal(""), z.coerce.number().int().min(0).max(100)]).optional(),
  workers: z.array(z.object({ worker_type: z.enum(WORKER_TYPES), workers_present: z.coerce.number().int().min(0, "Worker count cannot be negative.") })).max(50),
});

export function reportPayload(values) {
  const payload = { ...values, progress_percentage: values.progress_percentage === "" ? null : Number(values.progress_percentage), workers: values.workers.map((x) => ({ worker_type: x.worker_type, workers_present: Number(x.workers_present) })) };
  delete payload.project_id;
  return payload;
}
