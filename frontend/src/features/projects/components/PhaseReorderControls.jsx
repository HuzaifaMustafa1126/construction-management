/* eslint-disable react/prop-types */
import { useRef, useState } from "react";
import { apiMessage } from "../../../api/endpoints";
import { AppButton } from "../../../components/ui/AppUI";
import { AppAlert } from "../../../components/ui/Controls";
import { useReorderProjectPhases } from "../hooks/useProjectSchedule";

const sameOrder = (left, right) =>
  left.length === right.length && left.every((id, index) => id === right[index]);

const phaseIds = (rows) => rows.map(({ id }) => id);
const staleMessage = "The project’s phases or their order changed. Reload phases before saving.";

export default function PhaseReorderControls({ projectId, query, onCancel, onSaved }) {
  const reorder = useReorderProjectPhases(projectId);
  const [baseline, setBaseline] = useState(() => query.data);
  const [draft, setDraft] = useState(() => phaseIds(query.data));
  const [error, setError] = useState("");
  const [requiresReload, setRequiresReload] = useState(false);
  const [busyAction, setBusyAction] = useState(null);
  const running = useRef(false);
  const current = Array.isArray(query.data) ? query.data : [];
  const baselineIds = phaseIds(baseline);
  const stale = requiresReload || !sameOrder(baselineIds, phaseIds(current));
  const changed = !sameOrder(draft, baselineIds);
  const names = new Map([...baseline, ...current].map(({ id, name }) => [id, name]));

  const move = (index, direction) => {
    setDraft((previous) => {
      const next = [...previous];
      const destination = index + direction;
      [next[index], next[destination]] = [next[destination], next[index]];
      return next;
    });
  };

  const refresh = async () => {
    const result = await query.refetch({ throwOnError: true });
    if (!Array.isArray(result.data)) throw new Error("Invalid phase list response");
    return result.data;
  };

  const reload = async () => {
    if (running.current) return;
    running.current = true;
    setBusyAction("reload");
    setError("");
    try {
      const rows = await refresh();
      setBaseline(rows);
      setDraft(phaseIds(rows));
      setRequiresReload(false);
    } catch (failure) {
      setError(apiMessage(failure, "Could not reload the project phases."));
    } finally {
      running.current = false;
      setBusyAction(null);
    }
  };

  const save = async () => {
    if (running.current || stale || !changed) return;
    running.current = true;
    setBusyAction("save");
    setError("");
    try {
      const rows = await refresh();
      if (!sameOrder(baselineIds, phaseIds(rows))) {
        setRequiresReload(true);
        return;
      }
      await reorder.mutateAsync(draft);
      onSaved();
    } catch (failure) {
      if (failure?.status === 422) {
        setRequiresReload(true);
        setError("The server rejected this order. Reload phases before trying again.");
        await query.refetch();
      } else {
        setError(apiMessage(failure, "Could not save the phase order."));
      }
    } finally {
      running.current = false;
      setBusyAction(null);
    }
  };

  return (
    <div className="schedule-phase-editor">
      {(error || stale) && <AppAlert tone="danger">{error || staleMessage}</AppAlert>}
      <ol className="schedule-phase-list" role="list">
        {draft.map((id, index) => (
          <li key={id} className="schedule-phase-order-row">
            <span>{names.get(id)}</span>
            <div className="button-group">
              <AppButton
                type="button"
                variant="secondary"
                aria-label={`Move ${names.get(id)} up`}
                disabled={Boolean(busyAction) || stale || index === 0}
                onClick={() => move(index, -1)}
              >
                Move up
              </AppButton>
              <AppButton
                type="button"
                variant="secondary"
                aria-label={`Move ${names.get(id)} down`}
                disabled={Boolean(busyAction) || stale || index === draft.length - 1}
                onClick={() => move(index, 1)}
              >
                Move down
              </AppButton>
            </div>
          </li>
        ))}
      </ol>
      <div className="button-group">
        <AppButton type="button" onClick={save} busy={busyAction === "save"} disabled={!changed || stale || Boolean(busyAction)}>
          Save order
        </AppButton>
        {(stale || error) && (
          <AppButton type="button" variant="secondary" onClick={reload} busy={busyAction === "reload"} disabled={Boolean(busyAction)}>
            Reload phases
          </AppButton>
        )}
        <AppButton type="button" variant="secondary" onClick={onCancel} disabled={Boolean(busyAction)}>
          Cancel
        </AppButton>
      </div>
    </div>
  );
}
