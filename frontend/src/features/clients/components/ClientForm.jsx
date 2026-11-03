/* eslint-disable react/prop-types */
import { zodResolver } from "@hookform/resolvers/zod";
import { useForm } from "react-hook-form";
import { AppButton, AppInput } from "../../../components/ui/AppUI";
import { AppTextarea } from "../../../components/ui/Controls";
import { apiMessage, applyServerErrors } from "../../../api/endpoints";
import { clientPayload, clientSchema } from "../schemas/clientSchema";

const defaults = {
  name: "",
  company_name: "",
  email: "",
  phone: "",
  address: "",
  city: "",
  country: "",
  notes: "",
};

export default function ClientForm({
  initialValues,
  onSubmit,
  onCancel,
  submitLabel,
}) {
  const form = useForm({
    resolver: zodResolver(clientSchema),
    values: { ...defaults, ...initialValues },
  });
  const submit = form.handleSubmit(async (values) => {
    try {
      await onSubmit(clientPayload(values));
    } catch (error) {
      applyServerErrors(error, form.setError);
      form.setError("root", {
        message: apiMessage(error, "The client could not be saved."),
      });
    }
  });

  return (
    <form className="form-grid" onSubmit={submit} noValidate>
      {form.formState.errors.root && (
        <div className="error-banner full" role="alert">
          {form.formState.errors.root.message}
        </div>
      )}
      <AppInput
        label="Client name"
        required
        {...form.register("name")}
        error={form.formState.errors.name?.message}
      />
      <AppInput
        label="Company name"
        {...form.register("company_name")}
        error={form.formState.errors.company_name?.message}
      />
      <AppInput
        label="Email"
        type="email"
        {...form.register("email")}
        error={form.formState.errors.email?.message}
      />
      <AppInput
        label="Phone"
        {...form.register("phone")}
        error={form.formState.errors.phone?.message}
      />
      <div className="full">
        <AppTextarea
          label="Address"
          rows="3"
          {...form.register("address")}
          error={form.formState.errors.address?.message}
        />
      </div>
      <AppInput
        label="City"
        {...form.register("city")}
        error={form.formState.errors.city?.message}
      />
      <AppInput
        label="Country"
        {...form.register("country")}
        error={form.formState.errors.country?.message}
      />
      <div className="full">
        <AppTextarea
          label="Notes"
          rows="4"
          {...form.register("notes")}
          error={form.formState.errors.notes?.message}
        />
      </div>
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
