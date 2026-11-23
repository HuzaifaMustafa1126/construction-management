/* eslint-disable react/prop-types */
import { Controller, useWatch } from "react-hook-form";
import { SearchableSelect } from "../../../components/ui/AppUI";
import { AppTextarea } from "../../../components/ui/Controls";
import "./dailyReportConditionsFields.css";

const NO_DELAY = "No Delay";
const optionsFor = (labels) => [
  { value: "", label: "Not specified" },
  ...labels.map((label) => ({ value: label, label })),
];

const weatherOptions = optionsFor([
  "Sunny", "Partly Cloudy", "Cloudy", "Overcast", "Windy", "Rainy", "Heavy Rain",
  "Thunderstorm", "Foggy", "Hazy", "Hot", "Cold", "Humid", "Dusty", "Snowy",
]);

const delayOptions = optionsFor([
  NO_DELAY, "Weather Conditions", "Material Shortage", "Labor Shortage",
  "Equipment Breakdown", "Equipment Unavailable", "Site Access Issue", "Design Changes",
  "Client Approval Pending", "Inspection Delay", "Safety Issue", "Utility Interruption",
  "Subcontractor Delay", "Other",
]);

function ConditionSelect({ control, name, label, options, error, placeholder = "", onSelect }) {
  return (
    <Controller
      control={control}
      name={name}
      render={({ field: { value, onChange, onBlur, ref } }) => (
        <SearchableSelect
          ref={ref}
          label={label}
          value={value || null}
          selectedOption={value ? { value, label: value } : undefined}
          options={options}
          placeholder={placeholder}
          error={error}
          onBlur={onBlur}
          onChange={(selected) => {
            onChange(selected);
            onSelect?.(selected);
          }}
        />
      )}
    />
  );
}

export function WeatherConditionsField({ control, error }) {
  return (
    <ConditionSelect
      control={control}
      name="weather_conditions"
      label="Weather conditions"
      options={weatherOptions}
      error={error}
    />
  );
}

export function ReportDelayFields({ form, sectioned = false }) {
  const { control, register, setValue, formState: { errors } } = form;
  const noDelay = useWatch({ control, name: "delays" }) === NO_DELAY;

  return (
    <>
      <div className={sectioned ? "daily-report-delay-select" : "daily-report-delay-select full"}>
        <ConditionSelect
          control={control}
          name="delays"
          label="Delays"
          options={delayOptions}
          placeholder={sectioned ? "Select a delay." : ""}
          error={errors.delays?.message}
          onSelect={(selected) => {
            if (selected === NO_DELAY) {
              setValue("delay_reason", "", { shouldDirty: true, shouldValidate: true });
            }
          }}
        />
      </div>
      <div className={sectioned ? "daily-report-span" : "full"}>
        <AppTextarea
          label="Delay reason"
          rows={sectioned ? "5" : "3"}
          placeholder={sectioned ? "Explain the reasons for any delays." : undefined}
          {...register("delay_reason")}
          disabled={noDelay}
          error={errors.delay_reason?.message}
        />
      </div>
    </>
  );
}
