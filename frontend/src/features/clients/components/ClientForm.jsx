/* eslint-disable react/prop-types */
import { useRef } from "react";
import { zodResolver } from "@hookform/resolvers/zod";
import { useForm } from "react-hook-form";
import { AppButton, AppInput, SectionCard } from "../../../components/ui/AppUI";
import { AppTextarea } from "../../../components/ui/Controls";
import { apiMessage, applyServerErrors } from "../../../api/endpoints";
import {
  clientPayload,
  clientSchema,
  createClientSchema,
} from "../schemas/clientSchema";
import ClientAccountFields from "./ClientAccountFields";

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
  createAccount = false,
  disabled = false,
}) {
  const submitting = useRef(false);
  const form = useForm({
    resolver: zodResolver(createAccount ? createClientSchema : clientSchema),
    defaultValues: {
      ...defaults,
      ...initialValues,
      ...(createAccount ? { password: "", password_confirmation: "" } : {}),
    },
  });
  const submit = form.handleSubmit(async (values) => {
    if (disabled || submitting.current) return;
    submitting.current = true;
    try {
      await onSubmit(clientPayload(values));
    } catch (error) {
      if (createAccount) {
        form.setValue("password", "");
        form.setValue("password_confirmation", "");
      }
      applyServerErrors(error, form.setError);
      form.setError("root", {
        message: apiMessage(error, "The client could not be saved."),
      });
    } finally {
      submitting.current = false;
    }
  });
  return (
    <form className="clients-form" onSubmit={submit} noValidate>
      {form.formState.errors.root && (
        <div className="error-banner" role="alert">
          {form.formState.errors.root.message}
        </div>
      )}
      <fieldset className="client-form-fields" disabled={disabled} aria-label="Client and account details">
      <SectionCard title="Client information">
        <div className="form-grid">
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
            label="Phone"
            {...form.register("phone")}
            error={form.formState.errors.phone?.message}
          />
          {!createAccount && (
            <AppInput
              label="Contact email"
              type="email"
              description="Changing contact email does not change login email. Manage account changes in Users."
              {...form.register("email")}
              error={form.formState.errors.email?.message}
            />
          )}
        </div>
      </SectionCard>
      <SectionCard title="Address and notes">
        <div className="form-grid">
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
        </div>
      </SectionCard>
      {createAccount && (
        <SectionCard title="Login account">
          <p className="field-description">
            A linked client login account will be created. Project access
            requires a separate authorized assignment. Existing
            email-verification requirements still apply; no email is sent.
          </p>
          <div className="form-grid">
            <ClientAccountFields
              register={form.register}
              errors={form.formState.errors}
              emailDescription="Used as both contact and login email when this client is created."
            />
          </div>
        </SectionCard>
      )}
      </fieldset>
      <div className="form-actions">
        <AppButton
          type="button"
          variant="secondary"
          disabled={form.formState.isSubmitting}
          onClick={onCancel}
        >
          Cancel
        </AppButton>
        <AppButton type="submit" disabled={disabled} busy={form.formState.isSubmitting}>
          {submitLabel}
        </AppButton>
      </div>
    </form>
  );
}
