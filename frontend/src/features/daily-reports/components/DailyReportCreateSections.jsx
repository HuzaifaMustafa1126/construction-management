/* eslint-disable react/prop-types */
import { AppButton, AppInput, SectionCard } from "../../../components/ui/AppUI";
import { AppTextarea } from "../../../components/ui/Controls";
import ReportProjectField from "./ReportProjectField";
import WorkersPresentField from "./WorkersPresentField";

export default function DailyReportCreateSections({ form, fixedProjectId, submitLabel, onCancel }) {
  const { register, control, formState: { errors, isSubmitting } } = form;
  return <>
    <SectionCard title="Report Information">
      <div className="daily-report-fields">
        <ReportProjectField control={control} fixedProjectId={fixedProjectId} error={errors.project_id?.message} />
        <AppInput label="Report date" type="date" required {...register("report_date")} error={errors.report_date?.message} />
        <div className="daily-report-span"><AppInput label="Report title" required {...register("title")} error={errors.title?.message} /></div>
        <AppInput label="Progress percentage" type="number" min="0" max="100" step="1" {...register("progress_percentage")} error={errors.progress_percentage?.message} />
        <AppInput label="Weather conditions" {...register("weather_conditions")} error={errors.weather_conditions?.message} />
      </div>
    </SectionCard>
    <SectionCard title="Work Updates">
      <div className="daily-report-fields daily-report-textareas">
        <AppTextarea label="Completed work *" rows="5" required placeholder="Describe the work completed today." {...register("work_completed")} error={errors.work_completed?.message} />
        <AppTextarea label="Work in progress" rows="5" placeholder="Describe work that is still underway." {...register("work_in_progress")} error={errors.work_in_progress?.message} />
      </div>
    </SectionCard>
    <SectionCard title="Issues and Delays">
      <div className="daily-report-fields daily-report-textareas">
        <AppTextarea label="Issues" rows="5" placeholder="Record any site issues." {...register("issues")} error={errors.issues?.message} />
        <AppTextarea label="Delays" rows="5" placeholder="Describe any delays." {...register("delays")} error={errors.delays?.message} />
        <div className="daily-report-span"><AppTextarea label="Delay reason" rows="5" placeholder="Explain the reasons for any delays." {...register("delay_reason")} error={errors.delay_reason?.message} /></div>
      </div>
    </SectionCard>
    <WorkersPresentField sectioned control={control} register={register} errors={errors.workers} />
    <SectionCard title="General Notes">
      <AppTextarea label="General notes" rows="4" placeholder="Add any other relevant site notes." {...register("general_notes")} error={errors.general_notes?.message} />
    </SectionCard>
    <footer className="daily-report-create-actions">
      <AppButton type="button" variant="secondary" onClick={onCancel}>Cancel</AppButton>
      <AppButton type="submit" busy={isSubmitting}>{submitLabel}</AppButton>
    </footer>
  </>;
}
