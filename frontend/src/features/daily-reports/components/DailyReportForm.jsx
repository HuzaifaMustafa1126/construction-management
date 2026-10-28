/* eslint-disable react/prop-types */
import { zodResolver } from "@hookform/resolvers/zod";
import { useEffect } from "react";
import { useForm } from "react-hook-form";
import { AppButton, AppInput, AppSelect } from "../../../components/ui/AppUI";
import { AppAlert, AppTextarea } from "../../../components/ui/Controls";
import { applyServerErrors } from "../../../api/endpoints";
import { dailyReportSchema, reportPayload } from "../schemas/dailyReportSchema";
import WorkersPresentField from "./WorkersPresentField";

const defaults = { project_id: "", report_date: "", title: "", work_completed: "", work_in_progress: "", issues: "", delays: "", delay_reason: "", weather_conditions: "", general_notes: "", progress_percentage: "", workers: [] };
export default function DailyReportForm({ initialValues, projects, fixedProjectId, submitLabel = "Save Report", onSubmit, onCancel }) {
  const form = useForm({ resolver: zodResolver(dailyReportSchema), defaultValues: { ...defaults, ...initialValues, project_id: String(fixedProjectId || initialValues?.project?.id || initialValues?.project_id || ""), progress_percentage: initialValues?.progress_percentage ?? "", workers: initialValues?.workers || [] } });
  useEffect(() => { if (fixedProjectId) form.setValue("project_id", String(fixedProjectId)); }, [fixedProjectId, form]);
  const submit = form.handleSubmit(async (values) => { try { await onSubmit(values.project_id, reportPayload(values)); } catch (error) { applyServerErrors(error, form.setError); form.setError("root", { message: error.message || "The report could not be saved." }); } });
  return <form className="app-form project-form" onSubmit={submit} noValidate>
    {form.formState.errors.root && <div className="full"><AppAlert tone="danger">{form.formState.errors.root.message}</AppAlert></div>}
    {fixedProjectId ? <><AppSelect label="Project" value={String(fixedProjectId)} disabled><option value={fixedProjectId}>{projects.find((p) => String(p.id) === String(fixedProjectId))?.name || initialValues?.project?.name || "Selected project"}</option></AppSelect><input type="hidden" {...form.register("project_id")}/></> : <AppSelect label="Project" required {...form.register("project_id")} error={form.formState.errors.project_id?.message}><option value="">Select a project</option>{projects.map((p) => <option key={p.id} value={p.id}>{p.name} ({p.code})</option>)}</AppSelect>}
    <AppInput label="Report date" type="date" required {...form.register("report_date")} error={form.formState.errors.report_date?.message}/>
    <AppInput label="Report title" required {...form.register("title")} error={form.formState.errors.title?.message}/>
    <AppInput label="Progress percentage" type="number" min="0" max="100" step="1" {...form.register("progress_percentage")} error={form.formState.errors.progress_percentage?.message}/>
    <AppInput label="Weather conditions" {...form.register("weather_conditions")} error={form.formState.errors.weather_conditions?.message}/>
    <div className="full"><AppTextarea label="Completed work *" rows="5" required {...form.register("work_completed")} error={form.formState.errors.work_completed?.message}/></div>
    <div className="full"><AppTextarea label="Work in progress" rows="4" {...form.register("work_in_progress")} error={form.formState.errors.work_in_progress?.message}/></div>
    <div className="full"><AppTextarea label="Issues" rows="4" {...form.register("issues")} error={form.formState.errors.issues?.message}/></div>
    <div className="full"><AppTextarea label="Delays" rows="4" {...form.register("delays")} error={form.formState.errors.delays?.message}/></div>
    <div className="full"><AppTextarea label="Delay reason" rows="3" {...form.register("delay_reason")} error={form.formState.errors.delay_reason?.message}/></div>
    <div className="full"><AppTextarea label="General notes" rows="4" {...form.register("general_notes")} error={form.formState.errors.general_notes?.message}/></div>
    <WorkersPresentField control={form.control} register={form.register} errors={form.formState.errors.workers}/>
    <div className="form-actions full"><AppButton type="button" variant="secondary" onClick={onCancel}>Cancel</AppButton><AppButton type="submit" busy={form.formState.isSubmitting}>{submitLabel}</AppButton></div>
  </form>;
}
