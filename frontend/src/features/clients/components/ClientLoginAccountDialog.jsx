/* eslint-disable react/prop-types */
import { useRef } from "react";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { toast } from "react-toastify";
import { AppButton, AppInput, AppModal } from "../../../components/ui/AppUI";
import { apiMessage, applyServerErrors } from "../../../api/endpoints";
import { clientsApi } from "../api/clientsApi";
import { useClientMutation } from "../hooks/useClients";
import { clientLoginSchema } from "../schemas/clientSchema";
import ClientAccountFields from "./ClientAccountFields";

export default function ClientLoginAccountDialog({ client, onClose }) {
  const submitting = useRef(false);
  const form = useForm({
    resolver: zodResolver(clientLoginSchema),
    defaultValues: {
      name: client.name,
      email: client.email || "",
      password: "",
      password_confirmation: "",
    },
  });
  const mutation = useClientMutation((payload) =>
    clientsApi.createAccount(client.id, payload),
  );
  const close = () => {
    if (!submitting.current) onClose();
  };
  const submit = form.handleSubmit(async (values) => {
    if (submitting.current) return;
    submitting.current = true;
    try {
      await mutation.mutateAsync(values);
      toast.success("Client login account created.");
      onClose();
    } catch (error) {
      form.setValue("password", "");
      form.setValue("password_confirmation", "");
      applyServerErrors(error, form.setError);
      form.setError("root", {
        message: apiMessage(error, "The login account could not be created."),
      });
    } finally {
      submitting.current = false;
    }
  });
  return (
    <AppModal open title="Create login account" onClose={close}>
      <p className="field-description">
        The email must be available for a new account. No existing account will
        be linked. Contact details stay unchanged. Project membership and email
        verification remain separate; no email is sent.
      </p>
      <form className="form-grid" onSubmit={submit} noValidate>
        {form.formState.errors.root && (
          <div className="error-banner full" role="alert">
            {form.formState.errors.root.message}
          </div>
        )}
        <div className="full">
          <AppInput
            label="Name"
            required
            {...form.register("name")}
            error={form.formState.errors.name?.message}
          />
        </div>
        <ClientAccountFields
          register={form.register}
          errors={form.formState.errors}
          emailDescription="Creates a new login; it does not change the business contact email."
        />
        <div className="modal-actions full">
          <AppButton
            type="button"
            variant="secondary"
            disabled={form.formState.isSubmitting}
            onClick={close}
          >
            Cancel
          </AppButton>
          <AppButton type="submit" busy={form.formState.isSubmitting}>
            Create login account
          </AppButton>
        </div>
      </form>
    </AppModal>
  );
}
