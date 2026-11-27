const monthFormatter = new Intl.DateTimeFormat(undefined, {
  month: "long",
  year: "numeric",
  timeZone: "UTC",
});
const dateFormatter = new Intl.DateTimeFormat(undefined, {
  dateStyle: "full",
  timeZone: "UTC",
});
const weekdayFormatter = new Intl.DateTimeFormat(undefined, {
  weekday: "short",
  timeZone: "UTC",
});

const formatDate = (date) => date.toISOString().split("T")[0];

export function localToday(now = new Date()) {
  const month = String(now.getMonth() + 1).padStart(2, "0");
  const day = String(now.getDate()).padStart(2, "0");
  return `${now.getFullYear()}-${month}-${day}`;
}

export function shiftMonth(month, delta) {
  const [year, number] = month.split("-").map(Number);
  return formatDate(new Date(Date.UTC(year, number - 1 + delta, 1))).slice(
    0,
    7,
  );
}

export function shiftDate(date, delta) {
  const [year, month, day] = date.split("-").map(Number);
  return formatDate(new Date(Date.UTC(year, month - 1, day + delta)));
}

export function defaultCalendarDate(month, today) {
  return today.slice(0, 7) === month ? today : `${month}-01`;
}

export function calendarDateLabel(date) {
  return dateFormatter.format(new Date(`${date}T00:00:00Z`));
}

export function calendarMonth(month) {
  const [year, number] = month.split("-").map(Number);
  const first = new Date(Date.UTC(year, number - 1, 1));
  const last = new Date(Date.UTC(year, number, 0));
  const offset = (first.getUTCDay() + 6) % 7;
  const weeks = Array.from(
    { length: Math.ceil((offset + last.getUTCDate()) / 7) },
    (_, week) =>
      Array.from({ length: 7 }, (_, weekday) => {
        const date = new Date(
          Date.UTC(year, number - 1, week * 7 + weekday - offset + 1),
        );
        return {
          value: formatDate(date),
          day: date.getUTCDate(),
          label: dateFormatter.format(date),
          inMonth: date.getUTCMonth() === first.getUTCMonth(),
        };
      }),
  );
  return {
    from: formatDate(first),
    to: formatDate(last),
    label: monthFormatter.format(first),
    weeks,
  };
}

export const weekdays = Array.from({ length: 7 }, (_, day) =>
  weekdayFormatter.format(new Date(Date.UTC(2024, 0, day + 1))),
);
