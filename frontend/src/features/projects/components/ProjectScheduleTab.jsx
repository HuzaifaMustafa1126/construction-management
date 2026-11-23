/* eslint-disable react/prop-types */
import { useRef, useState } from "react";
import { apiMessage } from "../../../api/endpoints";
import { AppBadge, AppButton, ConfirmDialog, EmptyState, SectionCard } from "../../../components/ui/AppUI";
import { AppAlert } from "../../../components/ui/Controls";
import { useAuth } from "../../auth/context/AuthContext";
import { formatReportDate } from "../../daily-reports/utils/reportFormatters";
import {
  useApplyDefaultProjectPhases,
  useCreateProjectPhase,
  useDeleteProjectPhase,
  useProjectMilestoneMutations,
  useProjectSchedule,
  useUpdateProjectPhase,
} from "../hooks/useProjectSchedule";
import MilestoneFormModal from "./MilestoneFormModal";
import PhaseFormModal from "./PhaseFormModal";
import PhaseReorderControls from "./PhaseReorderControls";
import "./projectSchedule.css";

const statuses = {
  pending: { label: "Pending", tone: "neutral" },
  active: { label: "Active", tone: "info" },
  in_progress: { label: "In progress", tone: "info" },
  completed: { label: "Completed", tone: "success" },
  delayed: { label: "Delayed", tone: "warning" },
};

function ScheduleStatus({ status }) {
  const entry = statuses[status];

  return <AppBadge tone={entry?.tone}>{entry?.label ?? "Unknown status"}</AppBadge>;
}

function ScheduleCollection({ query, label, children }) {
  if (query.isError) {
    const accessMessage = {
      401: "Sign in again to view this project’s schedule.",
      403: `You do not have access to this project's ${label}.`,
      404: "This project was not found or is no longer available.",
    }[query.error?.status];

    return (
      <AppAlert tone="danger">
        {accessMessage ?? apiMessage(query.error, `Could not load ${label}.`)}
      </AppAlert>
    );
  }

  if (query.isPending) return <p role="status">Loading {label}…</p>;
  if (!Array.isArray(query.data)) {
    return <AppAlert tone="danger">Could not read the {label} response.</AppAlert>;
  }
  if (query.data.length === 0) return <EmptyState title={`No ${label} yet`} />;

  return children(query.data);
}

function ScheduleDate({ label, value }) {
  return (
    <div>
      <dt>{label}</dt>
      <dd>{value ? <time dateTime={value}>{formatReportDate(value)}</time> : "Not set"}</dd>
    </div>
  );
}

function PhaseCard({ phase, onEdit, onDelete }) {
  return (
    <li className="schedule-phase">
      <div className="schedule-item-heading">
        <h3>{phase.name}</h3>
        <ScheduleStatus status={phase.status} />
      </div>
      {phase.description && <p className="schedule-description">{phase.description}</p>}
      <dl className="schedule-dates">
        <ScheduleDate label="Start date" value={phase.start_date} />
        <ScheduleDate label="End date" value={phase.end_date} />
      </dl>
      <div className="schedule-progress">
        <progress
          aria-label={`${phase.name} progress`}
          max={100}
          value={phase.progress_percentage}
        />
        <span>{phase.progress_percentage}%</span>
      </div>
      {onEdit && (
        <div className="schedule-phase-actions">
          <AppButton type="button" variant="secondary" aria-label={`Edit ${phase.name}`} onClick={() => onEdit(phase)}>
            Edit
          </AppButton>
          <AppButton type="button" variant="danger" aria-label={`Delete ${phase.name}`} onClick={() => onDelete(phase)}>
            Delete
          </AppButton>
        </div>
      )}
    </li>
  );
}

function MilestoneEntry({ milestone, phaseName, actions }) {
  return (
    <li className="schedule-milestone">
      <div className="schedule-item-heading">
        <h3>{milestone.name}</h3>
        <ScheduleStatus status={milestone.status} />
      </div>
      {phaseName && <p className="schedule-phase-name">{phaseName}</p>}
      {milestone.description && (
        <p className="schedule-description">{milestone.description}</p>
      )}
      <dl className="schedule-dates">
        <ScheduleDate label="Due date" value={milestone.due_date} />
        {milestone.completed_date && (
          <ScheduleDate label="Completed date" value={milestone.completed_date} />
        )}
      </dl>
      {actions && <div className="schedule-milestone-actions">{actions}</div>}
    </li>
  );
}

export default function ProjectScheduleTab({ projectId }) {
  const { user, can } = useAuth();
  const canEdit = can("projects.edit");

  return (
    <ProjectScheduleContent
      key={JSON.stringify([user?.company?.id, user?.id, projectId, canEdit])}
      projectId={projectId}
      canEdit={canEdit}
    />
  );
}

function ProjectScheduleContent({ projectId, canEdit }) {
  const { phases, milestones } = useProjectSchedule(projectId);
  const createPhase = useCreateProjectPhase(projectId);
  const updatePhase = useUpdateProjectPhase(projectId);
  const deletePhase = useDeleteProjectPhase(projectId);
  const defaults = useApplyDefaultProjectPhases(projectId);
  const milestoneMutations = useProjectMilestoneMutations(projectId);
  const [milestoneForm, setMilestoneForm] = useState(null);
  const [milestoneToDelete, setMilestoneToDelete] = useState(null);
  const deletingMilestone = useRef(false);
  const [form, setForm] = useState(null);
  const [phaseToDelete, setPhaseToDelete] = useState(null);
  const [confirmDefaults, setConfirmDefaults] = useState(false);
  const [reordering, setReordering] = useState(false);
  const [phaseNotice, setPhaseNotice] = useState(null);
  const deleting = useRef(false);
  const applyingDefaults = useRef(false);
  const loadedPhases = phases.isSuccess && Array.isArray(phases.data);
  const availablePhases = loadedPhases ? phases.data : [];
  const phaseNames = new Map(
    (phases.isSuccess && Array.isArray(phases.data) ? phases.data : []).map(
      ({ id, name }) => [id, name],
    ),
  );

  const openDelete = (phase) => {
    deletePhase.reset();
    setPhaseToDelete(phase);
  };

  const confirmDelete = async () => {
    if (!canEdit || !phaseToDelete || deleting.current) return;
    deleting.current = true;
    try {
      await deletePhase.mutateAsync(phaseToDelete.id);
      setPhaseToDelete(null);
    } catch {
      // The mutation error is rendered in the confirmation dialog.
    } finally {
      deleting.current = false;
    }
  };

  const applyDefaults = async () => {
    if (!canEdit || applyingDefaults.current) return;
    applyingDefaults.current = true;
    try {
      await defaults.mutateAsync();
      setConfirmDefaults(false);
      setPhaseNotice({ tone: "success", message: "Default phases added." });
    } catch (error) {
      if (error?.status === 409) {
        await phases.refetch();
        setConfirmDefaults(false);
        setPhaseNotice({ tone: "danger", message: "This project already contains phases." });
      }
    } finally {
      applyingDefaults.current = false;
    }
  };

  const confirmMilestoneDelete = async () => {
    if (!canEdit || !milestoneToDelete || deletingMilestone.current) return;
    deletingMilestone.current = true;
    try {
      await milestoneMutations.remove.mutateAsync(milestoneToDelete.id);
      setMilestoneToDelete(null);
    } catch {
      // Keep the dialog open and display the mutation error.
    } finally {
      deletingMilestone.current = false;
    }
  };

  return (
    <div className="project-schedule">
      <div className="project-schedule-sections">
        <SectionCard
          title="Project phases"
          action={canEdit && loadedPhases && !reordering && (
            <div className="button-group">
              <AppButton type="button" onClick={() => setForm({ phase: null })}>
                Add phase
              </AppButton>
              {phases.data.length === 0 && !defaults.isPending && (
                <AppButton type="button" variant="secondary" onClick={() => {
                  defaults.reset();
                  setPhaseNotice(null);
                  setConfirmDefaults(true);
                }}>
                  Apply default phases
                </AppButton>
              )}
              {phases.data.length >= 2 && (
                <AppButton type="button" variant="secondary" onClick={() => {
                  setPhaseNotice(null);
                  setReordering(true);
                }}>
                  Reorder phases
                </AppButton>
              )}
            </div>
          )}
        >
          {phaseNotice && <AppAlert tone={phaseNotice.tone}>{phaseNotice.message}</AppAlert>}
          {reordering ? (
            <PhaseReorderControls
              projectId={projectId}
              query={phases}
              onCancel={() => setReordering(false)}
              onSaved={() => {
                setReordering(false);
                setPhaseNotice({ tone: "success", message: "Phase order saved." });
              }}
            />
          ) : (
            <ScheduleCollection query={phases} label="phases">
              {(rows) => (
                <ol className="schedule-phase-list" role="list">
                  {rows.map((phase) => (
                    <PhaseCard
                      key={phase.id}
                      phase={phase}
                      onEdit={canEdit ? (selected) => setForm({ phase: selected }) : undefined}
                      onDelete={canEdit ? openDelete : undefined}
                    />
                  ))}
                </ol>
              )}
            </ScheduleCollection>
          )}
        </SectionCard>
        <SectionCard
          title="Milestones timeline"
          action={canEdit && milestones.isSuccess && Array.isArray(milestones.data) && (
            <AppButton type="button" onClick={() => setMilestoneForm({})}>Add milestone</AppButton>
          )}
        >
          <ScheduleCollection query={milestones} label="milestones">
            {(rows) => (
              <ol className="schedule-milestone-list" role="list">
                {rows.map((milestone) => (
                  <MilestoneEntry
                    key={milestone.id}
                    milestone={milestone}
                    phaseName={phaseNames.get(milestone.phase_id)}
                    actions={canEdit && (
                      <>
                        <AppButton type="button" variant="secondary" aria-label={`Edit ${milestone.name}`}
                          onClick={() => setMilestoneForm({ id: milestone.id })}>Edit</AppButton>
                        <AppButton type="button" variant="secondary" aria-label={`Change status for ${milestone.name}`}
                          onClick={() => setMilestoneForm({ id: milestone.id, statusOnly: true })}>Change status</AppButton>
                        <AppButton type="button" variant="danger" aria-label={`Delete ${milestone.name}`}
                          onClick={() => {
                            milestoneMutations.remove.reset();
                            setMilestoneToDelete(milestone);
                          }}>Delete</AppButton>
                      </>
                    )}
                  />
                ))}
              </ol>
            )}
          </ScheduleCollection>
        </SectionCard>
      </div>
      {canEdit && milestoneForm && (
        <MilestoneFormModal
          projectId={projectId}
          milestoneId={milestoneForm.id}
          statusOnly={milestoneForm.statusOnly}
          phases={availablePhases}
          onSave={(payload) => milestoneForm.id
            ? milestoneMutations.update.mutateAsync({ id: milestoneForm.id, payload })
            : milestoneMutations.create.mutateAsync(payload)}
          onClose={() => setMilestoneForm(null)}
        />
      )}
      {canEdit && milestoneToDelete && (
        <ConfirmDialog
          open
          title="Delete milestone?"
          message={
            <span className="schedule-milestone-confirm">
              Delete “{milestoneToDelete.name}” from this project?
              {milestoneMutations.remove.error && (
                <span className="schedule-confirm-error" role="alert">
                  {milestoneMutations.remove.error.status === 409
                    ? "This milestone cannot be deleted while tasks depend on it."
                    : apiMessage(milestoneMutations.remove.error, "The milestone could not be deleted.")}
                </span>
              )}
            </span>
          }
          busy={milestoneMutations.remove.isPending}
          confirmLabel="Delete milestone"
          onConfirm={confirmMilestoneDelete}
          onClose={() => {
            if (!deletingMilestone.current) setMilestoneToDelete(null);
          }}
        />
      )}
      {canEdit && confirmDefaults && (
        <ConfirmDialog
          open
          title="Apply default phases?"
          message={
            <>
              This will add 10 standard construction phases to this project. You can edit their names and dates afterward.
              {defaults.error && defaults.error.status !== 409 && (
                <span className="schedule-confirm-error" role="alert">
                  {apiMessage(defaults.error, "Could not apply the default phases.")}
                </span>
              )}
            </>
          }
          busy={defaults.isPending || (defaults.error?.status === 409 && phases.isFetching)}
          confirmLabel="Apply default phases"
          onConfirm={applyDefaults}
          onClose={() => {
            if (!applyingDefaults.current) setConfirmDefaults(false);
          }}
        />
      )}
      {canEdit && form && (
        <PhaseFormModal
          phase={form.phase}
          onSave={(payload) => form.phase
            ? updatePhase.mutateAsync({ phaseId: form.phase.id, payload })
            : createPhase.mutateAsync(payload)}
          onClose={() => setForm(null)}
        />
      )}
      {canEdit && phaseToDelete && (
        <ConfirmDialog
          open
          title="Delete phase?"
          message={
            <span className="schedule-delete-message">
              Delete “{phaseToDelete.name}” from this project?
              {deletePhase.error && (
                <span className="schedule-confirm-error" role="alert">
                  {deletePhase.error.status === 409
                    ? "This phase cannot be deleted while milestones or tasks depend on it."
                    : apiMessage(deletePhase.error, "The phase could not be deleted.")}
                </span>
              )}
            </span>
          }
          busy={deletePhase.isPending}
          confirmLabel="Delete phase"
          onConfirm={confirmDelete}
          onClose={() => {
            if (!deleting.current) setPhaseToDelete(null);
          }}
        />
      )}
    </div>
  );
}
