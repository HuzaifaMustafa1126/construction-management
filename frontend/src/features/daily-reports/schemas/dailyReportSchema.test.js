import { describe, expect, it } from "vitest";
import { dailyReportSchema, reportPayload, WORKER_TYPES } from "./dailyReportSchema";

const valid = { project_id: "4", report_date: "2026-10-28", title: "Foundation", work_completed: "Rebar installed.", progress_percentage: "35", workers: [{ worker_type: "mason", workers_present: "8" }] };
describe("daily report contract", () => {
  it("accepts only backend-supported content and normalizes numbers", () => { const parsed = dailyReportSchema.parse(valid); const payload = reportPayload(parsed); expect(payload.progress_percentage).toBe(35); expect(payload.workers[0].workers_present).toBe(8); expect(payload).not.toHaveProperty("company_id"); expect(payload).not.toHaveProperty("project_id"); });
  it("rejects missing required content and negative workers", () => { const result = dailyReportSchema.safeParse({ ...valid, title: "", work_completed: "", workers: [{ worker_type: "helper", workers_present: -1 }] }); expect(result.success).toBe(false); expect(result.error.issues.map((x) => x.path.join("."))).toEqual(expect.arrayContaining(["title", "work_completed", "workers.0.workers_present"])); });
  it("matches Laravel workforce categories", () => { expect(WORKER_TYPES).toEqual(["mason", "helper", "electrician", "plumber", "carpenter", "painter", "steel_fixer", "operator", "other"]); });
});
