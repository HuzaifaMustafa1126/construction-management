/* eslint-disable react/prop-types */
import { useRef } from "react";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { apiMessage, applyServerErrors } from "../../../api/endpoints";
import { AppButton, AppInput, AppModal, AppSelect } from "../../../components/ui/AppUI";
import { AppAlert, AppTextarea } from "../../../components/ui/Controls";
import {
  PHASE_STATUS_LABELS,
  projectPhasePayload,
  projectPhaseSchema,
} from "../schemas/projectPhaseSchema";

export default function PhaseFormModal({ phase, onSave, onClose }) {
  const saving = useRef(false);
  const {
    register,
    handleSubmit,
    setError,
    formState: { dirtyFields, errors, isDirty, isSubmitting },
  } = useForm({
    resolver: zodResolver(projectPhaseSchema),
    defaultValues: {
      name: phase?.name ?? "",
      description: phase?.description ?? "",
      start_date: phase?.start_date ?? "",
      end_date: phase?.end_date ?? "",
      status: phase?.status ?? "pending",
    },
  });

  const submit = handleSubmit(async (values) => {
    if (saving.current || (phase && !isDirty)) return;
    saving.current = true;
    try {
      await onSave(projectPhasePayload(values, phase ? dirtyFields : undefined));
      onClose();
    } catch (error) {
      applyServerErrors(error, setError);
      setError("root", { message: apiMessage(error, "The phase could not be saved.") });
    } finally {
      saving.current = false;
    }
  });

  const dismiss = () => {
    if (!saving.current) onClose();
  };

  return (
    <AppModal open title={phase ? "Edit phase" : "Add phase"} onClose={dismiss}>
      <form className="form-grid" noValidate onSubmit={submit}>
        {errors.root && (
          <div className="full"><AppAlert tone="danger">{errors.root.message}</AppAlert></div>
        )}
        <AppInput label="Phase name" required error={errors.name?.message} {...register("name")} />
        <AppSelect label="Status" error={errors.status?.message} {...register("status")}>
          {Object.entries(PHASE_STATUS_LABELS).map(([value, label]) => (
            <option key={value} value={value}>{label}</option>
          ))}
        </AppSelect>
        <div className="full">
          <AppTextarea label="Description" error={errors.description?.message} {...register("description")} />
        </div>
        <AppInput label="Start date" type="date" error={errors.start_date?.message} {...register("start_date")} />
        <AppInput label="End date" type="date" error={errors.end_date?.message} {...register("end_date")} />
        <div className="modal-actions full">
          <AppButton type="button" variant="secondary" disabled={isSubmitting} onClick={dismiss}>
            Cancel
          </AppButton>
          <AppButton type="submit" busy={isSubmitting} disabled={Boolean(phase && !isDirty)}>
            {phase ? "Save changes" : "Add phase"}
          </AppButton>
        </div>
      </form>
    </AppModal>
  );
}
