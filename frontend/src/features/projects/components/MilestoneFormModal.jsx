/* eslint-disable react/prop-types */
import { useRef } from "react";
import { Controller, useForm, useWatch } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { apiMessage, applyServerErrors } from "../../../api/endpoints";
import { AppButton, AppInput, AppModal, AppSelect, SearchableSelect } from "../../../components/ui/AppUI";
import { AppAlert, AppTextarea } from "../../../components/ui/Controls";
import { useProjectMilestone } from "../hooks/useProjectSchedule";
import { MILESTONE_STATUS_LABELS, milestonePayload, milestoneSchema } from "../schemas/milestoneSchema";

const resolveMilestone = zodResolver(milestoneSchema);

export default function MilestoneFormModal({ projectId, milestoneId, statusOnly, phases, onSave, onClose }) {
  const detail = useProjectMilestone(projectId, milestoneId);
  const saving = useRef(false);
  const dismiss = () => {
    if (!saving.current) onClose();
  };
  const title = statusOnly ? "Change milestone status" : milestoneId ? "Edit milestone" : "Add milestone";

  return (
    <AppModal open title={title} onClose={dismiss}>
      {milestoneId && !detail.isFetchedAfterMount && detail.isPaused ? (
        <AppAlert tone="danger">Reconnect to load the current milestone.</AppAlert>
      ) : milestoneId && (detail.isPending || (!detail.isFetchedAfterMount && detail.isFetching)) ? (
        <p role="status">Loading milestone…</p>
      ) : milestoneId && detail.isError ? (
        <AppAlert tone="danger">{apiMessage(detail.error, "The milestone could not be loaded.")}</AppAlert>
      ) : (
        <MilestoneForm
          milestone={detail.data}
          statusOnly={statusOnly}
          phases={phases}
          saving={saving}
          onSave={onSave}
          onClose={onClose}
          dismiss={dismiss}
        />
      )}
    </AppModal>
  );
}

function MilestoneForm({ milestone, statusOnly, phases, saving, onSave, onClose, dismiss }) {
  const { control, register, handleSubmit, setError, formState: { dirtyFields, errors, isDirty, isSubmitting } } = useForm({
    resolver: (values, context, options) => resolveMilestone({
      ...values,
      completed_date: values.status === "completed" ? values.completed_date : null,
    }, context, options),
    defaultValues: {
      name: milestone?.name ?? "",
      description: milestone?.description ?? "",
      due_date: milestone?.due_date ?? "",
      phase_id: milestone?.phase_id == null ? null : Number(milestone.phase_id),
      status: milestone?.status ?? "pending",
      completed_date: milestone?.completed_date ?? "",
    },
  });
  const status = useWatch({ control, name: "status" });
  const phaseOptions = [{ value: null, label: "No Phase" }, ...phases.map(({ id, name }) => ({
    value: Number(id), label: name,
  }))];

  const submit = handleSubmit(async (values) => {
    if (saving.current || (milestone && !isDirty)) return;
    const payload = milestonePayload(values, milestone ? dirtyFields : undefined);
    if (Object.keys(payload).length === 0) return;
    if (payload.phase_id != null && !phaseOptions.some(({ value }) => value === payload.phase_id)) {
      setError("phase_id", { message: "Select a phase from this project." });
      return;
    }

    saving.current = true;
    try {
      await onSave(payload);
      onClose();
    } catch (error) {
      applyServerErrors(error, setError);
      setError("root", { message: apiMessage(error, "The milestone could not be saved.") });
    } finally {
      saving.current = false;
    }
  });

  return (
    <form className="form-grid schedule-milestone-form" noValidate onSubmit={submit}>
      {errors.root && <div className="full"><AppAlert tone="danger">{errors.root.message}</AppAlert></div>}
      {statusOnly ? (
        <p className="full">{milestone?.name}</p>
      ) : (
        <>
          <AppInput label="Milestone name" required error={errors.name?.message} {...register("name")} />
          <AppInput label="Due date" type="date" required error={errors.due_date?.message} {...register("due_date")} />
          <div className="full">
            <AppTextarea label="Description" error={errors.description?.message} {...register("description")} />
          </div>
          <Controller
            name="phase_id"
            control={control}
            render={({ field }) => (
              <SearchableSelect
                {...field}
                label="Associated phase"
                options={phaseOptions}
                placeholder="Select a phase"
                error={errors.phase_id?.message}
                selectedOption={field.value != null && !phaseOptions.some(({ value }) => value === field.value)
                  ? { value: field.value, label: "Current phase unavailable" }
                  : undefined}
              />
            )}
          />
        </>
      )}
      <AppSelect label="Status" error={errors.status?.message} {...register("status")}>
        {Object.entries(MILESTONE_STATUS_LABELS).map(([value, label]) => (
          <option key={value} value={value}>{label}</option>
        ))}
      </AppSelect>
      {status === "completed" && (
        <AppInput label="Completed date" type="date" error={errors.completed_date?.message} {...register("completed_date")} />
      )}
      <div className="modal-actions full">
        <AppButton type="button" variant="secondary" disabled={isSubmitting} onClick={dismiss}>Cancel</AppButton>
        <AppButton type="submit" busy={isSubmitting} disabled={Boolean(milestone && !isDirty)}>
          {milestone ? "Save changes" : "Add milestone"}
        </AppButton>
      </div>
    </form>
  );
}
