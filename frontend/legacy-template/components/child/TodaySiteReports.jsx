import { Icon } from "@iconify/react/dist/iconify.js";
import { Link } from "react-router-dom";

// Replace this sample list with daily reports returned by the project API.
const siteReports = [
  {
    id: 1,
    project: "Villa Emerald",
    supervisor: "Rajesh",
    time: "9:30 AM",
    status: "Completed",
    iconBackground: "bg-success-600",
  },
  {
    id: 2,
    project: "Sunshine Apartments",
    supervisor: "Suresh",
    time: "11:15 AM",
    status: "Completed",
    iconBackground: "bg-primary-600",
  },
  {
    id: 3,
    project: "Green Valley",
    supervisor: "Mani",
    time: "2:45 PM",
    status: "In Progress",
    iconBackground: "bg-warning-600",
  },
  {
    id: 4,
    project: "City Plaza",
    supervisor: "Karthik",
    time: "4:20 PM",
    status: "Completed",
    iconBackground: "bg-purple",
  },
];

const TodaySiteReports = () => {
  return (
    <div className="card h-100 radius-8 border">
      <div className="card-body p-16">
        <div className="d-flex align-items-center justify-content-between gap-2 mb-8">
          <h6 className="mb-0 fw-bold text-lg">Today&apos;s Site Reports</h6>
          <Link
            to="#"
            className="text-primary-600 hover-text-primary text-sm fw-medium"
          >
            View All
          </Link>
        </div>

        <div>
          {siteReports.map((report) => {
            const isCompleted = report.status === "Completed";

            return (
              <div
                className="d-flex align-items-center gap-12 py-10 border-bottom"
                key={report.id}
              >
                <span
                  className={`${report.iconBackground} w-36-px h-36-px radius-8 flex-shrink-0 d-flex align-items-center justify-content-center text-white text-xl`}
                >
                  <Icon icon="solar:document-text-bold-duotone" />
                </span>

                <div className="flex-grow-1 min-w-0">
                  <h6 className="text-sm fw-semibold mb-1 text-truncate">
                    {report.project}
                  </h6>
                  <span className="text-xs text-secondary-light d-block text-truncate">
                    Site supervisor: {report.supervisor}
                  </span>
                </div>

                <time className="text-xs text-secondary-light text-nowrap">
                  {report.time}
                </time>

                <span
                  className={`px-12 py-4 radius-8 text-xs fw-medium text-nowrap ${
                    isCompleted
                      ? "bg-success-focus text-success-main"
                      : "bg-warning-focus text-warning-main"
                  }`}
                >
                  {report.status}
                </span>
              </div>
            );
          })}
        </div>
      </div>
    </div>
  );
};

export default TodaySiteReports;
