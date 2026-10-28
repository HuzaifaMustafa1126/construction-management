/* eslint-disable react/prop-types */
import { useFieldArray } from "react-hook-form";
import { AppButton, AppInput, AppSelect } from "../../../components/ui/AppUI";
import { WORKER_TYPES } from "../schemas/dailyReportSchema";
import { humanize } from "../utils/reportFormatters";

export default function WorkersPresentField({ control, register, errors }) {
  const { fields, append, remove } = useFieldArray({ control, name: "workers" });
  const used = new Set(fields.map((x) => x.worker_type));
  const next = WORKER_TYPES.find((x) => !used.has(x));
  return <div className="full report-workers"><div className="section-heading"><div><h3>Workers present</h3><p>Add only workforce categories present or explicitly recorded as zero.</p></div>{next && <AppButton type="button" variant="secondary" onClick={() => append({ worker_type: next, workers_present: 0 })}>Add category</AppButton>}</div>
    {!fields.length && <p className="muted-copy">No workforce categories recorded.</p>}
    {fields.map((field, index) => <div className="worker-row" key={field.id}>
      <AppSelect label="Worker type" {...register(`workers.${index}.worker_type`)} error={errors?.[index]?.worker_type?.message}>{WORKER_TYPES.map((type) => <option key={type} value={type} disabled={type !== field.worker_type && used.has(type)}>{humanize(type)}</option>)}</AppSelect>
      <AppInput label="Workers present" type="number" min="0" step="1" {...register(`workers.${index}.workers_present`)} error={errors?.[index]?.workers_present?.message}/>
      <AppButton type="button" variant="danger" onClick={() => remove(index)}>Remove</AppButton>
    </div>)}
  </div>;
}
