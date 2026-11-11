/* eslint-disable react/prop-types */
import { zodResolver } from "@hookform/resolvers/zod";
import { Controller, useForm } from "react-hook-form";
import { AppButton, AppInput } from "../../../components/ui/AppUI";
import { AppTextarea } from "../../../components/ui/Controls";
import { applyServerErrors, apiMessage } from "../../../api/endpoints";
import { projectPayload, projectSchema } from "../schemas/projectSchema";
import ClientSelector from "../../clients/components/ClientSelector";

const defaults = {
  name: "",
  code: "",
  client_id: null,
  description: "",
  location: "",
  address: "",
  city: "",
  state: "",
  country: "",
  postal_code: "",
  start_date: "",
  expected_end_date: "",
  actual_end_date: "",
  budget: "",
};
export default function ProjectForm({
  initialValues,
  onSubmit,
  onCancel,
  submitLabel,
}) {
  const form = useForm({
    resolver: zodResolver(projectSchema),
    values: {
      ...defaults,
      ...initialValues,
      client_id: initialValues?.client?.id ?? initialValues?.client_id ?? null,
      budget: initialValues?.budget ?? "",
    },
  });
  const submit = form.handleSubmit(async (values) => {
    try {
      await onSubmit(projectPayload(values));
    } catch (error) {
      applyServerErrors(error, form.setError);
      form.setError("root", {
        message: apiMessage(error, "The project could not be saved."),
      });
    }
  });
  return (
    <form onSubmit={submit} className="form-grid" noValidate>
      {form.formState.errors.root && (
        <div className="error-banner full" role="alert">
          {form.formState.errors.root.message}
        </div>
      )}
      <AppInput
        label="Project name"
        required
        {...form.register("name")}
        error={form.formState.errors.name?.message}
      />
      <AppInput
        label="Project code"
        placeholder="Generated automatically when blank"
        {...form.register("code")}
        error={form.formState.errors.code?.message}
      />
      <Controller
        name="client_id"
        control={form.control}
        render={({ field, fieldState }) => (
          <ClientSelector
            ref={field.ref}
            name={field.name}
            onBlur={field.onBlur}
            selectedClient={initialValues?.client}
            value={field.value}
            onChange={field.onChange}
            error={fieldState.error?.message}
          />
        )}
      />
      <div className="full">
        <AppTextarea
          label="Description"
          rows="4"
          {...form.register("description")}
          error={form.formState.errors.description?.message}
        />
      </div>
      <AppInput
        label="Location"
        {...form.register("location")}
        error={form.formState.errors.location?.message}
      />
      <AppInput
        label="Address"
        {...form.register("address")}
        error={form.formState.errors.address?.message}
      />
      <AppInput
        label="City"
        {...form.register("city")}
        error={form.formState.errors.city?.message}
      />
      <AppInput
        label="State"
        {...form.register("state")}
        error={form.formState.errors.state?.message}
      />
      <AppInput
        label="Country"
        {...form.register("country")}
        error={form.formState.errors.country?.message}
      />
      <AppInput
        label="Postal code"
        {...form.register("postal_code")}
        error={form.formState.errors.postal_code?.message}
      />
      <AppInput
        label="Start date"
        type="date"
        {...form.register("start_date")}
        error={form.formState.errors.start_date?.message}
      />
      <AppInput
        label="Expected end date"
        type="date"
        {...form.register("expected_end_date")}
        error={form.formState.errors.expected_end_date?.message}
      />
      <AppInput
        label="Actual end date"
        type="date"
        {...form.register("actual_end_date")}
        error={form.formState.errors.actual_end_date?.message}
      />
      <AppInput
        label="Budget"
        type="number"
        min="0"
        step="0.01"
        {...form.register("budget")}
        error={form.formState.errors.budget?.message}
      />
      <div className="form-actions full">
        <AppButton type="button" variant="secondary" onClick={onCancel}>
          Cancel
        </AppButton>
        <AppButton type="submit" busy={form.formState.isSubmitting}>
          {submitLabel}
        </AppButton>
      </div>
    </form>
  );
}
