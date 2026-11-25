/* eslint-disable react/prop-types */
import { useRef, useState } from "react";
import { Controller, useForm, useWatch } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { toast } from "react-toastify";
import { apiMessage, applyServerErrors } from "../../../api/endpoints";
import { AppButton, AppInput, AppModal, AppSelect, SearchableSelect } from "../../../components/ui/AppUI";
import { AppAlert, AppTextarea } from "../../../components/ui/Controls";
import { useAuth } from "../../auth/context/AuthContext";
import { useProjectSchedule } from "../../projects/hooks/useProjectSchedule";
import { useProjectTeam } from "../../projects/hooks/useProjects";
import { useCreateTask, useUpdateTask } from "../hooks/useTasks";
import { createTaskPayload, taskSchema, updateTaskPayload } from "../schemas/taskSchema";
import { taskPriorities, taskStatuses } from "../taskLabels";
import { TaskProjectPicker } from "./TaskFilters";
import "./taskFormModal.css";

const resolveTask = zodResolver(taskSchema);
const relationshipOptions = (rows, emptyLabel) => [
  { value: null, label: emptyLabel },
  ...rows.map(({ id, name }) => ({ value: String(id), label: name })),
];
const compatible = (milestone, phaseId) =>
  !phaseId || milestone.phase_id == null || String(milestone.phase_id) === phaseId;

export default function TaskFormModal({ projectId, projectName, initialProject, task, onClose }) {
  const { can } = useAuth();
  const canAssign = can("tasks.assign");
  const canComplete = can("tasks.complete");
  const [project, setProject] = useState(initialProject);
  const creating = useRef(false);
  const create = useCreateTask();
  const update = useUpdateTask();
  const dismiss = () => { if (!creating.current) onClose(); };
  const { control, register, handleSubmit, setValue, setError, clearErrors, getValues,
    formState: { errors, isSubmitting, dirtyFields, isDirty },
  } = useForm({
    resolver: (values, context, options) => {
      const status = canComplete ? values.status : task?.status ?? "todo";
      return resolveTask({
        ...values, status,
        assigned_to: canAssign ? values.assigned_to : null,
        progress_percentage: status === "completed" ? "" : values.progress_percentage,
      }, context, options);
    },
    defaultValues: {
      project_id: String(projectId ?? initialProject?.value ?? ""),
      title: task?.title ?? "", description: task?.description ?? "",
      phase_id: task?.phase_id == null ? null : String(task.phase_id),
      milestone_id: task?.milestone_id == null ? null : String(task.milestone_id),
      assigned_to: task?.assigned_to == null ? null : String(task.assigned_to),
      priority: task?.priority ?? "medium", status: task?.status ?? "todo",
      start_date: task?.start_date ?? "", due_date: task?.due_date ?? "",
      estimated_hours: String(task?.estimated_hours ?? ""),
      actual_hours: String(task?.actual_hours ?? ""),
      progress_percentage: String(task?.progress_percentage ?? 0),
    },
  });
  const selectedId = useWatch({ control, name: "project_id" });
  const phaseId = useWatch({ control, name: "phase_id" });
  const status = useWatch({ control, name: "status" });
  const { phases, milestones } = useProjectSchedule(selectedId);
  const team = useProjectTeam(selectedId, Boolean(canAssign && can("projects.view")));
  const availablePhases = phases.isSuccess ? phases.data : [];
  const availableMilestones = milestones.isSuccess
    ? milestones.data.filter((milestone) => compatible(milestone, phaseId))
    : [];
  const assignees = team.isSuccess
    ? team.data.filter(({ status, assignment_type }) =>
      status === "Active" && ["member", "supervisor"].includes(assignment_type))
    : [];

  function changeProject(next) {
    if (next?.value === selectedId) return;
    setProject(next);
    setValue("project_id", next?.value ?? "", { shouldValidate: true });
    const fields = ["phase_id", "milestone_id", "assigned_to"];
    for (const field of fields) setValue(field, null);
    clearErrors([...fields, "root"]);
  }

  const submit = handleSubmit(async (values) => {
    if (creating.current || !can(task ? "projects.edit" : "tasks.create")) return;
    const permissions = { canAssign, canComplete };
    const payload = task
      ? updateTaskPayload(values, task, dirtyFields, permissions)
      : createTaskPayload(values, permissions);
    if (task && !Object.keys(payload).length) {
      setError("root", { message: "There are no changes to save." });
      return;
    }
    const relationships = [
      ["phase_id", availablePhases, "Select an available phase from this project."],
      ["milestone_id", availableMilestones, "Select a milestone compatible with this project and phase."],
      ["assigned_to", assignees, "Select an available member of this project."],
    ];
    for (const [field, options, message] of relationships) {
      if (payload[field] && !options.some(({ id }) => String(id) === payload[field])) {
        setError(field, { message });
        return;
      }
    }
    creating.current = true;
    try {
      await (task ? update : create).mutateAsync({
        projectId: values.project_id, taskId: task?.id, payload,
      });
      toast.success(task ? "Task updated." : "Task created.");
      onClose();
    } catch (error) {
      applyServerErrors(error, setError);
      const message = error.status === 403
        ? task ? "You do not have permission to edit this task." : "You do not have permission to create this task in the selected project."
        : error.status === 404
          ? task ? "This task is no longer available." : "The selected project is no longer available."
          : apiMessage(error, "The task could not be saved.");
      setError("root", { message });
    } finally {
      creating.current = false;
    }
  });

  function relationship(name, label, emptyLabel, query, options, onPick) {
    return (
      <Controller name={name} control={control} render={({ field }) => (
        <SearchableSelect
          key={selectedId}
          {...field}
          label={label}
          options={relationshipOptions(options, emptyLabel)}
          selectedOption={field.value && !options.some(({ id }) => String(id) === field.value)
            ? { value: field.value, label: `Unavailable ${label.toLowerCase()} #${field.value}` }
            : undefined}
          disabled={isSubmitting || !query.isSuccess}
          description={query.isError ? `${label} options could not be loaded.`
            : selectedId && query.isPending ? "Loading options…" : undefined}
          error={errors[name]?.message}
          onChange={(value) => { field.onChange(value); onPick?.(value); }}
        />
      )} />
    );
  }

  return (
    <AppModal open title={task ? "Edit Task" : "New Task"} onClose={dismiss} className="task-form-modal">
      <form className="form-grid" noValidate onSubmit={submit}>
        {errors.root && <div className="full"><AppAlert tone="danger">{errors.root.message}</AppAlert></div>}
        <div className="full">
          <AppInput label="Task title" required error={errors.title?.message} {...register("title")} />
        </div>
        <div className="full">
          <AppTextarea label="Description" error={errors.description?.message} {...register("description")} />
        </div>
        <div className="full">
          {projectId
            ? <AppInput label="Project" value={projectName || `Project #${projectId}`} readOnly />
            : <TaskProjectPicker selected={project} onSelect={changeProject} required disabled={isSubmitting} error={errors.project_id?.message} />}
        </div>
        {relationship("phase_id", "Phase", "No Phase", phases, availablePhases, (value) => {
          const current = milestones.data?.find(({ id }) => String(id) === getValues("milestone_id"));
          if (current && !compatible(current, value)) {
            setValue("milestone_id", null, { shouldDirty: true });
            clearErrors("milestone_id");
          }
        })}
        {relationship("milestone_id", "Milestone", "No Milestone", milestones, availableMilestones)}
        {canAssign && relationship("assigned_to", "Assigned To", "Unassigned", team, assignees)}
        <AppSelect label="Priority" error={errors.priority?.message} {...register("priority")}>
          {Object.entries(taskPriorities).map(([value, [label]]) => <option key={value} value={value}>{label}</option>)}
        </AppSelect>
        {canComplete && (
          <AppSelect label="Status" error={errors.status?.message} {...register("status")}>
            {Object.entries(taskStatuses).map(([value, [label]]) => <option key={value} value={value}>{label}</option>)}
          </AppSelect>
        )}
        {status !== "completed" && (
          <AppInput label="Progress (%)" type="number" min="0" max="100" step="0.01"
            description={task?.status === "completed" ? "Reopening resets progress to zero unless you enter a different value." : undefined}
            error={errors.progress_percentage?.message} {...register("progress_percentage")} />
        )}
        <AppInput label="Start date" type="date" min="1000-01-01" error={errors.start_date?.message} {...register("start_date")} />
        <AppInput label="Due date" type="date" min="1000-01-01" error={errors.due_date?.message} {...register("due_date")} />
        <AppInput label="Estimated hours" type="number" min="0" max="999999.99" step="0.01"
          error={errors.estimated_hours?.message} {...register("estimated_hours")} />
        {task && <AppInput label="Actual hours" type="number" min="0" max="999999.99" step="0.01"
          error={errors.actual_hours?.message} {...register("actual_hours")} />}
        <div className="modal-actions full">
          <AppButton type="button" variant="secondary" disabled={isSubmitting} onClick={dismiss}>Cancel</AppButton>
          <AppButton type="submit" busy={isSubmitting} disabled={Boolean(task && !isDirty)}>
            {task ? "Save changes" : "Create Task"}
          </AppButton>
        </div>
      </form>
    </AppModal>
  );
}
