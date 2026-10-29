import { useMemo, useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { z } from "zod";
import { toast } from "react-toastify";
import {
  apiMessage,
  applyServerErrors,
  permissionsApi,
  rolesApi,
} from "../../../api/endpoints";
import { useAuth, useCompany } from "../../auth/context/AuthContext";
import {
  AppButton,
  AppInput,
  AppModal,
  ConfirmDialog,
  PageHeader,
} from "../../../components/ui/AppUI";
import { AppAlert } from "../../../components/ui/Controls";
import { DataTable } from "../../../components/tables/DataTable";
import PermissionSelector from "../components/PermissionSelector";
const schema = z.object({
  name: z
    .string()
    .regex(
      /^[a-z][a-z0-9_]*$/,
      "Use lowercase letters, numbers, and underscores",
    ),
  permissions: z.array(z.string()),
});
export default function RolesPage() {
  const { can, refreshUser } = useAuth();
  const { company } = useCompany();
  const qc = useQueryClient();
  const [modal, setModal] = useState(null),
    [deleting, setDeleting] = useState(null),
    [problem, setProblem] = useState("");
  const form = useForm({
    resolver: zodResolver(schema),
    defaultValues: { name: "", permissions: [] },
  });
  const roles = useQuery({
    queryKey: ["company", company?.id, "roles"],
    queryFn: () => rolesApi.list(),
    enabled: !!company,
  });
  const permissions = useQuery({
    queryKey: ["company", company?.id, "permissions"],
    queryFn: permissionsApi.list,
    enabled: !!company,
  });
  const rows = Array.isArray(roles.data) ? roles.data : [];
  const columns = useMemo(
    () => [
      { accessorKey: "name", header: "Role name" },
      {
        id: "count",
        header: "Permissions",
        cell: ({ row }) => row.original.permissions?.length || 0,
      },
    ],
    [],
  );
  const invalidate = () =>
    qc.invalidateQueries({ queryKey: ["company", company?.id, "roles"] });
  const remove = useMutation({
    mutationFn: (id) => rolesApi.remove(id),
    onSuccess: () => {
      setDeleting(null);
      invalidate();
      toast.success("Role deleted.");
    },
    onError: (e) => setProblem(apiMessage(e)),
  });
  const open = (role) => {
    setModal(role || {});
    form.reset({
      name: role?.name || "",
      permissions: role?.permissions || [],
    });
  };
  const save = form.handleSubmit(async (values) => {
    setProblem("");
    try {
      modal.id
        ? await rolesApi.update(modal.id, values)
        : await rolesApi.create(values);
      await refreshUser();
      setModal(null);
      await invalidate();
      toast.success("Role saved.");
    } catch (error) {
      applyServerErrors(error, form.setError);
      setProblem(apiMessage(error));
    }
  });
  return (
    <>
      <PageHeader
        title="Roles & permissions"
        description="Company-scoped roles and authorized permissions."
        action={
          can("roles.create") ? (
            <AppButton onClick={() => open()}>Add role</AppButton>
          ) : null
        }
      />
      {problem && <AppAlert tone="danger">{problem}</AppAlert>}
      <DataTable
        columns={columns}
        data={rows}
        loading={roles.isLoading}
        actions={(role) => (
          <div className="table-actions">
            {can("roles.update") && (
              <AppButton
                variant="ghost"
                disabled={role.name === "company_admin"}
                onClick={() => open(role)}
              >
                Edit
              </AppButton>
            )}
            {can("roles.delete") && (
              <AppButton
                variant="danger"
                disabled={role.name === "company_admin"}
                onClick={() => setDeleting(role)}
              >
                Delete
              </AppButton>
            )}
          </div>
        )}
      />
      <AppModal
        open={!!modal}
        title={modal?.id ? "Edit role" : "Add role"}
        onClose={() => setModal(null)}
      >
        <form onSubmit={save}>
          <AppInput
            label="Role name"
            error={form.formState.errors.name?.message}
            {...form.register("name")}
          />
          <PermissionSelector
            groups={permissions.data || {}}
            value={form.watch("permissions")}
            onChange={(value) =>
              form.setValue("permissions", value, { shouldValidate: true })
            }
          />
          {!!modal?.preserved_permissions?.length && (
            <AppAlert>
              Reserved or unrecognized grants are preserved by the server and
              are not selectable here: {modal.preserved_permissions.join(", ")}
            </AppAlert>
          )}
          {form.formState.errors.permissions && (
            <small role="alert">
              {form.formState.errors.permissions.message}
            </small>
          )}
          <div className="modal-actions">
            <AppButton
              type="button"
              variant="secondary"
              onClick={() => setModal(null)}
            >
              Cancel
            </AppButton>
            <AppButton busy={form.formState.isSubmitting}>Save role</AppButton>
          </div>
        </form>
      </AppModal>
      <ConfirmDialog
        open={!!deleting}
        title="Delete role"
        message={`Delete ${deleting?.name}? Assigned users and protected-role rules are enforced by the server.`}
        busy={remove.isPending}
        onClose={() => setDeleting(null)}
        onConfirm={() => remove.mutate(deleting.id)}
      />
    </>
  );
}
