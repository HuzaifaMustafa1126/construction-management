/* eslint-disable react/prop-types */
import { useEffect, useState } from "react";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { z } from "zod";
import { apiMessage, applyServerErrors, staffInvitationsApi } from "../../../api/endpoints";
import { AppAlert, AppButton, AppInput, AppModal } from "../../../components/ui/AppUI";

const schema = z.object({
  email: z.string().trim().email("Enter a valid email address").max(255),
  roles: z.array(z.string()).min(1, "Select at least one role"),
});

export default function InviteStaffModal({ open, roles, onClose, onCreated }) {
  const [problem, setProblem] = useState("");
  const { register, handleSubmit, reset, setError, formState: { errors, isSubmitting } } = useForm({
    resolver: zodResolver(schema), defaultValues: { email: "", roles: [] },
  });
  useEffect(() => {
    if (open) { reset({ email: "", roles: [] }); setProblem(""); }
  }, [open, reset]);

  const submit = handleSubmit(async (values) => {
    setProblem("");
    try {
      await staffInvitationsApi.create(values);
      reset({ email: "", roles: [] });
      onCreated();
    } catch (error) {
      applyServerErrors(error, setError);
      setProblem(apiMessage(error, "Unable to send the invitation."));
    }
  });

  return <AppModal open={open} title="Invite Staff" onClose={onClose}>
    <form onSubmit={submit} className="form-grid">
      {problem && <AppAlert tone="danger">{problem}</AppAlert>}
      <div className="full"><AppInput label="Email" type="email" autoComplete="email" required error={errors.email?.message} {...register("email")} /></div>
      <fieldset className="full"><legend>Roles</legend>
        {roles.map((role) => <label className="permission-check" key={role.id}>
          <input type="checkbox" value={role.name} {...register("roles")} />{role.name}
        </label>)}
        {errors.roles?.message && <small role="alert">{errors.roles.message}</small>}
      </fieldset>
      <div className="modal-actions full"><AppButton type="button" variant="secondary" onClick={onClose}>Cancel</AppButton><AppButton busy={isSubmitting}>Invite</AppButton></div>
    </form>
  </AppModal>;
}
