import { useEffect, useState } from "react";
import { useQueryClient } from "@tanstack/react-query";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { z } from "zod";
import { toast } from "react-toastify";
import {
  companyApi,
  apiMessage,
  applyServerErrors,
} from "../../../api/endpoints";
import { useAuth, useCompany } from "../../auth/context/AuthContext";
import {
  AppButton,
  AppInput,
  PageHeader,
  SectionCard,
} from "../../../components/ui/AppUI";
const schema = z.object({
  name: z.string().min(1).max(255),
  email: z.union([z.string().email(), z.literal("")]).optional(),
  phone: z.string().max(30).optional(),
  address: z.string().max(2000).optional(),
  city: z.string().max(100).optional(),
  state: z.string().max(100).optional(),
  country: z.string().max(100).optional(),
  postal_code: z.string().max(30).optional(),
  website: z.union([z.string().url(), z.literal("")]).optional(),
  currency: z.string().length(3),
  timezone: z.string().min(1),
});
export default function CompanyProfilePage() {
  const { can } = useAuth();
  const { company, isLoading, refreshCompany } = useCompany();
  const qc = useQueryClient();
  const [edit, setEdit] = useState(false),
    [problem, setProblem] = useState("");
  const {
    register,
    handleSubmit,
    reset,
    setError,
    formState: { errors, isSubmitting },
  } = useForm({ resolver: zodResolver(schema) });
  useEffect(() => {
    if (company) reset(company);
  }, [company, reset]);
  if (isLoading) return <div role="status">Loading company…</div>;
  const fields = [
    ["name", "Company name"],
    ["email", "Email", "email"],
    ["phone", "Phone"],
    ["address", "Address"],
    ["city", "City"],
    ["state", "State"],
    ["country", "Country"],
    ["postal_code", "Postal code"],
    ["website", "Website", "url"],
    ["currency", "Currency"],
    ["timezone", "Timezone"],
  ];
  return (
    <>
      <PageHeader
        title="Company profile"
        description="Authenticated company information."
        action={
          can("company.update") ? (
            <AppButton
              onClick={() => {
                setEdit((v) => !v);
                reset(company);
              }}
            >
              {edit ? "Cancel" : "Edit company"}
            </AppButton>
          ) : null
        }
      />
      {problem && <div className="error-banner">{problem}</div>}
      <SectionCard title="Company information">
        {edit ? (
          <form
            className="form-grid"
            onSubmit={handleSubmit(async (values) => {
              setProblem("");
              try {
                await companyApi.update(values);
                await refreshCompany();
                await qc.invalidateQueries({ queryKey: ["company"] });
                setEdit(false);
                toast.success("Company profile updated.");
              } catch (error) {
                applyServerErrors(error, setError);
                setProblem(apiMessage(error));
              }
            })}
          >
            {fields.map(([name, label, type]) => (
              <AppInput
                key={name}
                label={label}
                type={type || "text"}
                error={errors[name]?.message}
                {...register(name)}
              />
            ))}
            <div className="button-group full">
              <AppButton busy={isSubmitting}>Save changes</AppButton>
              <AppButton
                type="button"
                variant="secondary"
                onClick={() => {
                  reset(company);
                  setEdit(false);
                }}
              >
                Cancel
              </AppButton>
            </div>
          </form>
        ) : (
          <div className="profile-grid">
            {fields.map(([name, label]) => (
              <div className="profile-item" key={name}>
                <small>{label}</small>
                <strong>{company?.[name] || "—"}</strong>
              </div>
            ))}
          </div>
        )}
      </SectionCard>
    </>
  );
}
