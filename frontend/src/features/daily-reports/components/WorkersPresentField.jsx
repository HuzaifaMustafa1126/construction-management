/* eslint-disable react/prop-types */
import { Controller, useFieldArray, useWatch } from "react-hook-form";
import { AppButton, AppInput, AppSelect, SearchableSelect, SectionCard } from "../../../components/ui/AppUI";
import { WORKER_TYPES } from "../schemas/dailyReportSchema";
import { humanize } from "../utils/reportFormatters";

export default function WorkersPresentField({ control, register, errors, sectioned = false }) {
  const { fields, append, remove } = useFieldArray({ control, name: "workers" });
  const values = useWatch({ control, name: "workers" }) || [];
  const used = new Set((sectioned ? values : fields).map((row) => row.worker_type));
  const next = WORKER_TYPES.find((x) => !used.has(x));
  if (sectioned) return <SectionCard title="Workers present" action={next ? <AppButton type="button" variant="secondary" onClick={() => append({ worker_type: next, workers_present: 0 })}>Add category</AppButton> : null}>
    <p className="daily-report-workers-help">Add only workforce categories present or explicitly recorded as zero.</p>
    {(errors?.message || errors?.root?.message) && <p className="daily-report-workers-error" role="alert">{errors.message || errors.root.message}</p>}
    {!fields.length && <p className="daily-report-workers-empty">No workforce categories recorded.</p>}
    <div className="daily-report-worker-list">
      {fields.map((field, index) => {
        const currentType = values[index]?.worker_type || field.worker_type;
        return <div className="daily-report-workforce-row" key={field.id}>
          <Controller name={`workers.${index}.worker_type`} control={control} render={({ field: category }) => <SearchableSelect
            label="Worker type" required name={category.name} ref={category.ref} value={category.value} onChange={category.onChange} onBlur={category.onBlur}
            options={WORKER_TYPES.map((type) => ({ value: type, label: humanize(type), disabled: type !== currentType && used.has(type) }))}
            error={errors?.[index]?.worker_type?.message}
          />} />
          <AppInput label="Workers present" type="number" min="0" step="1" {...register(`workers.${index}.workers_present`)} error={errors?.[index]?.workers_present?.message} />
          <AppButton type="button" variant="danger" aria-label={`Remove ${humanize(currentType)} category`} onClick={() => remove(index)}>Remove</AppButton>
        </div>;
      })}
    </div>
  </SectionCard>;
  return <div className="full report-workers"><div className="section-heading"><div><h3>Workers present</h3><p>Add only workforce categories present or explicitly recorded as zero.</p></div>{next && <AppButton type="button" variant="secondary" onClick={() => append({ worker_type: next, workers_present: 0 })}>Add category</AppButton>}</div>
    {!fields.length && <p className="muted-copy">No workforce categories recorded.</p>}
    {fields.map((field, index) => <div className="worker-row" key={field.id}>
      <AppSelect label="Worker type" {...register(`workers.${index}.worker_type`)} error={errors?.[index]?.worker_type?.message}>{WORKER_TYPES.map((type) => <option key={type} value={type} disabled={type !== field.worker_type && used.has(type)}>{humanize(type)}</option>)}</AppSelect>
      <AppInput label="Workers present" type="number" min="0" step="1" {...register(`workers.${index}.workers_present`)} error={errors?.[index]?.workers_present?.message}/>
      <AppButton type="button" variant="danger" onClick={() => remove(index)}>Remove</AppButton>
    </div>)}
  </div>;
}
