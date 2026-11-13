/* eslint-disable react/prop-types */
import { useState } from "react";
import { AppButton, AppInput } from "../../../components/ui/AppUI";

export default function ClientAccountFields({
  register,
  errors,
  emailDescription,
}) {
  const [visible, setVisible] = useState({});
  return (
    <>
      <div className="full">
        <AppInput
          label="Login email"
          type="email"
          required
          autoComplete="email"
          description={emailDescription}
          {...register("email")}
          error={errors.email?.message}
        />
      </div>
      {[
        ["password", "Password"],
        ["password_confirmation", "Confirm password"],
      ].map(([name, label]) => (
        <div className="client-password-field" key={name}>
          <AppInput
            label={label}
            id={name}
            type={visible[name] ? "text" : "password"}
            autoComplete="new-password"
            required
            {...register(name)}
            error={errors[name]?.message}
          />
          <AppButton
            type="button"
            variant="ghost"
            aria-controls={name}
            aria-pressed={!!visible[name]}
            onClick={() =>
              setVisible((current) => ({ ...current, [name]: !current[name] }))
            }
          >
            {visible[name] ? "Hide" : "Show"} {label.toLowerCase()}
          </AppButton>
        </div>
      ))}
    </>
  );
}
