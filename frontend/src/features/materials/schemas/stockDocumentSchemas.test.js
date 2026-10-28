import { describe, expect, it } from "vitest";
import { adjustmentSchema, consumptionSchema, deliverySchema } from "./stockDocumentSchemas";
const item = { project_inventory_id: 1, quantity: "2.5000", notes: "" };
describe("stock document schemas", () => {
  it("accepts verified delivery and consumption payloads", () => { expect(deliverySchema.safeParse({ supplier_id: 1, delivery_date: "2026-10-28", items: [item] }).success).toBe(true); expect(consumptionSchema.safeParse({ consumption_date: "2026-10-28", items: [item] }).success).toBe(true); });
  it("rejects non-positive and over-precision quantities", () => { expect(consumptionSchema.safeParse({ consumption_date: "2026-10-28", items: [{ ...item, quantity: "0" }] }).success).toBe(false); expect(consumptionSchema.safeParse({ consumption_date: "2026-10-28", items: [{ ...item, quantity: "1.00001" }] }).success).toBe(false); });
  it("accepts supported adjustment types and reason codes", () => expect(adjustmentSchema.safeParse({ adjustment_date: "2026-10-28", items: [{ ...item, adjustment_type: "adjustment_out", reason_code: "damage" }] }).success).toBe(true));
});
