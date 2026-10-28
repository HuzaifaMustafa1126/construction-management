import { describe, expect, it } from "vitest";
import { materialRequestSchema, stockQuantitySchema } from "./materialRequestSchema";

describe("material request validation", () => {
  it("accepts four-decimal positive quantities", () => expect(stockQuantitySchema.safeParse({ quantity: "1.2345" }).success).toBe(true));
  it("rejects negative and over-precision quantities", () => {
    expect(stockQuantitySchema.safeParse({ quantity: "-1" }).success).toBe(false);
    expect(stockQuantitySchema.safeParse({ quantity: "1.23456" }).success).toBe(false);
  });
  it("requires at least one inventory item", () => expect(materialRequestSchema.safeParse({ project_id: 1, required_date: "2026-10-28", items: [] }).success).toBe(false));
});
