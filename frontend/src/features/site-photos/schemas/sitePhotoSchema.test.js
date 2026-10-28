import { describe, expect, it } from "vitest";
import { MAX_PHOTO_BYTES, PHOTO_PERIODS, metadataPayload, sitePhotoMetadataSchema, validatePhotoFiles } from "./sitePhotoSchema";
const file = (type, size = 100) => ({ type, size });
describe("site photo contract", () => {
  it("matches backend categories and removes the route-only project field", () => { expect(PHOTO_PERIODS).toEqual(["morning", "afternoon", "evening"]); const parsed = sitePhotoMetadataSchema.parse({ project_id: "7", photo_date: "2026-10-28", time_category: "morning", daily_site_report_id: "12" }); expect(metadataPayload(parsed)).toEqual(expect.objectContaining({ daily_site_report_id: 12 })); expect(metadataPayload(parsed)).not.toHaveProperty("project_id"); });
  it("accepts JPEG PNG and WebP and rejects unsafe types", () => { expect(validatePhotoFiles([file("image/jpeg"), file("image/png"), file("image/webp")])).toBe(""); expect(validatePhotoFiles([file("image/svg+xml")])).toMatch(/JPEG/); });
  it("enforces batch and per-file limits", () => { expect(validatePhotoFiles([])).toMatch(/at least one/); expect(validatePhotoFiles(Array.from({ length: 11 }, () => file("image/png")))).toMatch(/10 photos/); expect(validatePhotoFiles([file("image/png", MAX_PHOTO_BYTES + 1)])).toMatch(/10 MB/); });
});
