/* eslint-disable react/prop-types */
import { useRef, useState } from "react";
import { toast } from "react-toastify";
import { apiMessage } from "../../../api/endpoints";
import {
  AppButton,
  AppModal,
  AppSelect,
  ConfirmDialog,
} from "../../../components/ui/AppUI";
import { AppAlert } from "../../../components/ui/Controls";
import {
  useDeleteTask,
  useTaskDetail,
  useUpdateTaskStatus,
} from "../hooks/useTasks";
import { taskStatuses } from "../taskLabels";
import TaskFormModal from "./TaskFormModal";

function actionError(error, fallback) {
  return (
    {
      401: "Your session has expired.",
      403: "You do not have permission to perform this action on this task.",
      404: "This task is no longer available.",
    }[error?.status] || apiMessage(error, fallback)
  );
}

export default function TaskActionDialog({
  action,
  row,
  projectName,
  onClose,
}) {
  const detail = useTaskDetail(action === "delete" ? undefined : row.id);
  if (action === "delete")
    return <TaskDeleteDialog task={row} onClose={onClose} />;

  const title = action === "edit" ? "Edit Task" : "Change Task Status";
  if (
    detail.isFetchedAfterMount &&
    !detail.isError &&
    detail.data &&
    String(detail.data.id) === String(row.id) &&
    String(detail.data.project_id) === String(row.project_id)
  ) {
    return action === "edit" ? (
      <TaskFormModal
        task={detail.data}
        projectId={detail.data.project_id}
        projectName={projectName}
        onClose={onClose}
      />
    ) : (
      <TaskStatusDialog task={detail.data} onClose={onClose} />
    );
  }

  return (
    <AppModal open title={title} onClose={onClose}>
      {detail.isFetching || !detail.isFetchedAfterMount ? (
        <p role="status">Loading task…</p>
      ) : (
        <>
          <AppAlert tone="danger">
            {actionError(detail.error, "The task could not be loaded.")}
          </AppAlert>
          <div className="modal-actions">
            <AppButton variant="secondary" onClick={() => detail.refetch()}>
              Retry
            </AppButton>
          </div>
        </>
      )}
    </AppModal>
  );
}

function TaskStatusDialog({ task, onClose }) {
  const [status, setStatus] = useState(task.status);
  const [error, setError] = useState(null);
  const saving = useRef(false);
  const mutation = useUpdateTaskStatus();
  const close = () => {
    if (!saving.current) onClose();
  };
  async function submit(event) {
    event.preventDefault();
    if (
      saving.current ||
      status === task.status ||
      !Object.hasOwn(taskStatuses, status)
    )
      return;
    saving.current = true;
    setError(null);
    try {
      await mutation.mutateAsync({
        taskId: task.id,
        projectId: task.project_id,
        payload: { status },
      });
      toast.success("Task status updated.");
      onClose();
    } catch (failure) {
      setError(failure);
    } finally {
      saving.current = false;
    }
  }

  return (
    <AppModal open title="Change Task Status" onClose={close}>
      <p>{task.title}</p>
      <form onSubmit={submit}>
        {error && (
          <AppAlert tone="danger">
            {actionError(error, "Task status could not be updated.")}
          </AppAlert>
        )}
        <AppSelect
          label="Status"
          value={status}
          disabled={mutation.isPending}
          error={error?.fieldErrors?.status?.[0]}
          onChange={(event) => {
            setStatus(event.target.value);
            setError(null);
          }}
        >
          {Object.entries(taskStatuses).map(([value, [label]]) => (
            <option key={value} value={value}>
              {label}
            </option>
          ))}
        </AppSelect>
        <div className="modal-actions">
          <AppButton
            type="button"
            variant="secondary"
            disabled={mutation.isPending}
            onClick={close}
          >
            Cancel
          </AppButton>
          <AppButton
            type="submit"
            busy={mutation.isPending}
            disabled={status === task.status}
          >
            Save status
          </AppButton>
        </div>
      </form>
    </AppModal>
  );
}

function TaskDeleteDialog({ task, onClose }) {
  const mutation = useDeleteTask();
  const saving = useRef(false);
  async function confirm() {
    if (saving.current) return;
    saving.current = true;
    try {
      await mutation.mutateAsync({
        taskId: task.id,
        projectId: task.project_id,
      });
      toast.success("Task deleted.");
      onClose();
    } catch (error) {
      toast.error(actionError(error, "The task could not be deleted."));
    } finally {
      saving.current = false;
    }
  }
  return (
    <ConfirmDialog
      open
      title="Delete Task"
      message={`Delete “${task.title}”? This removes it from active views.`}
      confirmLabel="Delete Task"
      busy={mutation.isPending}
      onConfirm={confirm}
      onClose={() => {
        if (!saving.current) onClose();
      }}
    />
  );
}
