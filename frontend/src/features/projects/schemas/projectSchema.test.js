import { describe, expect, it } from "vitest";
import { projectPayload, projectSchema, STATUS_TRANSITIONS } from "./projectSchema";

describe("project contract", () => {
  it("accepts Laravel-supported fields and strips empty values to null", () => { const result = projectSchema.parse({ name: " Alpha ", code: "PRJ-1", start_date: "2026-01-01", expected_end_date: "2026-02-01", budget: "" }); expect(result.name).toBe("Alpha"); expect(projectPayload(result).budget).toBeNull(); expect(projectPayload(result)).not.toHaveProperty("company_id"); });
  it("rejects invalid codes and end dates before the start date", () => { const result = projectSchema.safeParse({ name: "Alpha", code: "bad code", start_date: "2026-04-02", expected_end_date: "2026-04-01" }); expect(result.success).toBe(false); expect(result.error.issues.map((issue) => issue.path[0])).toEqual(expect.arrayContaining(["code", "expected_end_date"])); });
  it("matches the backend transition graph", () => { expect(STATUS_TRANSITIONS.planning).toEqual(["active", "cancelled"]); expect(STATUS_TRANSITIONS.completed).toEqual([]); });
});
