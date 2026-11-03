import { z } from "zod";

const optionalText = (max) => z.string().trim().max(max).optional();

export const clientSchema = z.object({
  name: z.string().trim().min(1, "Client name is required.").max(200),
  company_name: optionalText(200),
  email: z
    .union([
      z.literal(""),
      z.string().trim().email("Enter a valid email address.").max(255),
    ])
    .optional(),
  phone: optionalText(30),
  address: optionalText(2000),
  city: optionalText(100),
  country: optionalText(100),
  notes: optionalText(5000),
});

export function clientPayload(values) {
  return Object.fromEntries(
    Object.entries(values).map(([key, value]) => [
      key,
      value === "" ? null : value,
    ]),
  );
}
