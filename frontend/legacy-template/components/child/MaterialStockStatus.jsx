import { Icon } from "@iconify/react/dist/iconify.js";
import { Link } from "react-router-dom";

// Replace this sample inventory with material stock returned by the project API.
const materialStock = [
  {
    id: 1,
    name: "Cement",
    remaining: "450 bags",
    remainingPercent: 75,
    icon: "game-icons:powder-bag",
    iconColor: "text-warning-600",
    progressColor: "bg-success-main",
  },
  {
    id: 2,
    name: "Steel",
    remaining: "2.5 Ton",
    remainingPercent: 60,
    icon: "game-icons:steel-claws",
    iconColor: "text-secondary-light",
    progressColor: "bg-primary-600",
  },
  {
    id: 3,
    name: "Bricks",
    remaining: "1,200 pcs",
    remainingPercent: 40,
    icon: "game-icons:brick-wall",
    iconColor: "text-danger-600",
    progressColor: "bg-warning-600",
  },
  {
    id: 4,
    name: "Sand",
    remaining: "10.5 Ton",
    remainingPercent: 70,
    icon: "game-icons:sand-castle",
    iconColor: "text-warning-600",
    progressColor: "bg-purple",
  },
];

const MaterialStockStatus = () => {
  return (
    <div className="card h-100 radius-8 border">
      <div className="card-body p-16">
        <div className="d-flex align-items-center justify-content-between gap-2 mb-8">
          <h6 className="mb-0 fw-bold text-lg">Material Stock Status</h6>
          <Link
            to="#"
            className="text-primary-600 hover-text-primary text-sm fw-medium"
          >
            View All
          </Link>
        </div>

        <div>
          {materialStock.map((material) => (
            <div
              className="d-flex align-items-center gap-8 py-6 border-bottom"
              key={material.id}
            >
              <span
                className={`w-32-px h-32-px flex-shrink-0 d-flex align-items-center justify-content-center text-2xl ${material.iconColor}`}
              >
                <Icon icon={material.icon} />
              </span>

              <h6 className="text-sm fw-semibold mb-0 flex-grow-1">
                {material.name}
              </h6>

              <div className="w-72-px flex-shrink-0">
                <span className="text-sm text-primary-light fw-medium d-block text-nowrap">
                  {material.remaining}
                </span>
                <span className="text-xs text-secondary-light d-block">
                  Remaining
                </span>
              </div>

              <div className="d-flex align-items-center gap-8 flex-shrink-0">
                <div
                  className="progress w-64-px h-12-px bg-neutral-200 rounded-pill"
                  role="progressbar"
                  aria-label={`${material.name} stock remaining`}
                  aria-valuenow={material.remainingPercent}
                  aria-valuemin={0}
                  aria-valuemax={100}
                >
                  <div
                    className={`progress-bar ${material.progressColor} rounded-pill`}
                    style={{ width: `${material.remainingPercent}%` }}
                  />
                </div>
                <span className="text-sm fw-medium text-primary-light w-40-px text-nowrap">
                  {material.remainingPercent}%
                </span>
              </div>
            </div>
          ))}
        </div>
      </div>
    </div>
  );
};

export default MaterialStockStatus;
