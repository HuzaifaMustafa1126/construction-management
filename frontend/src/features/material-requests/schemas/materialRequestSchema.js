import { z } from "zod";

const quantity = z.string().trim().regex(/^\d+(\.\d{1,4})?$/, "Use a positive quantity with up to 4 decimal places").refine((value) => Number(value) > 0, "Quantity must be greater than zero");

export const materialRequestSchema = z.object({
  project_id: z.coerce.number().int().positive(),
  required_date: z.string().min(1, "Required date is required"),
  reason: z.string().max(5000).optional(),
  items: z.array(z.object({ project_inventory_id: z.coerce.number().int().positive(), quantity_requested: quantity, notes: z.string().max(5000).optional() })).min(1),
});

export const stockQuantitySchema = z.object({ quantity });
