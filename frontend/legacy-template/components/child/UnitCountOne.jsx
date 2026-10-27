import { Icon } from "@iconify/react";

const projectStats = [
  {
    title: "Total Projects",
    value: "12",
    note: "+2 this month",
    icon: "solar:buildings-2-bold-duotone",
    iconBackground: "bg-primary-600",
    noteColor: "text-success-main",
  },
  {
    title: "Active Projects",
    value: "8",
    note: "67% of total",
    icon: "game-icons:crane",
    iconBackground: "bg-success-main",
    noteColor: "text-secondary-light",
  },
  {
    title: "Today's Labor Count",
    value: "46",
    note: "+12 from yesterday",
    icon: "fluent:people-20-filled",
    iconBackground: "bg-warning-main",
    noteColor: "text-success-main",
  },
  {
    title: "Material Consumption",
    value: "125.6 Ton",
    note: "+8% this week",
    icon: "solar:layers-bold-duotone",
    iconBackground: "bg-purple",
    noteColor: "text-success-main",
  },
  {
    title: "Pending Client Payments",
    value: "₹ 28,50,000",
    note: "+3 invoices",
    icon: "solar:wallet-money-bold-duotone",
    iconBackground: "bg-danger-600",
    noteColor: "text-danger-main",
  },
  {
    title: "Total Project Value",
    value: "₹ 4,92,00,000",
    note: "Expected Profit: ₹ 78,30,000",
    icon: "solar:chart-square-bold-duotone",
    iconBackground: "bg-cyan",
    noteColor: "text-success-main",
  },
];

const UnitCountOne = () => {
  return (
    <div className="row row-cols-xxl-6 row-cols-xl-3 row-cols-md-2 row-cols-1 gy-3 construction-unit-count">
      {projectStats.map((stat, index) => (
        <div className="col" key={stat.title}>
          <div
            className={`card shadow-none border bg-gradient-start-${(index % 5) + 1} h-100 construction-unit-card`}
          >
            <div className="card-body p-16">
              <div className="d-flex align-items-center gap-12">
                <div
                  className={`w-50-px h-50-px ${stat.iconBackground} radius-8 d-flex flex-shrink-0 justify-content-center align-items-center`}
                >
                  <Icon icon={stat.icon} className="text-white text-2xl mb-0" />
                </div>
                <div className="min-w-0">
                  <p className="construction-unit-title fw-medium text-primary-light mb-1 text-nowrap">
                    {stat.title}
                  </p>
                  <h6 className="construction-unit-value mb-1 text-nowrap">
                    {stat.value}
                  </h6>
                  <p
                    className={`construction-unit-note fw-medium mb-0 text-nowrap ${stat.noteColor}`}
                  >
                    {stat.note}
                  </p>
                </div>
              </div>
            </div>
          </div>
        </div>
      ))}
    </div>
  );
};

export default UnitCountOne;
