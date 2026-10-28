import { z } from "zod";
export const PHOTO_PERIODS = ["morning", "afternoon", "evening"];
export const PHOTO_STATUSES = ["pending", "approved", "rejected"];
export const MAX_PHOTO_BYTES = 10 * 1024 * 1024;
export const ACCEPTED_PHOTO_TYPES = ["image/jpeg", "image/png", "image/webp"];
export const sitePhotoMetadataSchema = z.object({ project_id: z.string().min(1, "Project is required."), caption: z.string().max(255).optional(), description: z.string().max(5000).optional(), photo_date: z.string().min(1, "Photo date is required."), time_category: z.enum(PHOTO_PERIODS), daily_site_report_id: z.string().optional() });
export const sitePhotoEditSchema = sitePhotoMetadataSchema.omit({ project_id: true });
export function validatePhotoFiles(files) { if (!files.length) return "Select at least one photo."; if (files.length > 10) return "Select no more than 10 photos."; if (files.some((file) => !ACCEPTED_PHOTO_TYPES.includes(file.type))) return "Only JPEG, PNG, and WebP images are supported."; if (files.some((file) => file.size > MAX_PHOTO_BYTES)) return "Each photo must be 10 MB or smaller."; return ""; }
export function metadataPayload(values) { const { project_id: ignored, ...payload } = values; void ignored; return { ...payload, daily_site_report_id: payload.daily_site_report_id ? Number(payload.daily_site_report_id) : null }; }
export function photoFormData(values, files) { const data = new FormData(); const metadata = metadataPayload(values); Object.entries(metadata).forEach(([key, value]) => { if (value !== null && value !== "") data.append(key, value); }); if (files.length === 1) data.append("photo", files[0]); else files.forEach((file) => data.append("photos[]", file)); return data; }
