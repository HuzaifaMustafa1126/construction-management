import EarningStaticOne from "./child/EarningStaticOne";
import TopCountries from "./child/TopCountries";
import GeneratedContent from "./child/GeneratedContent";
import TotalIncome from "./child/TotalIncome";
import UnitCountOne from "./child/UnitCountOne";
import RecentSitePhotos from "./child/RecentSitePhotos";
import TodaySiteReports from "./child/TodaySiteReports";
import MaterialStockStatus from "./child/MaterialStockStatus";
import ActiveProjects from "./child/ActiveProjects";

const DashBoardLayerOne = () => {
  return (
    <>
      {/* UnitCountOne */}
      <UnitCountOne />

      <section className="row gy-4 mt-1">
        {/* EarningStaticOne */}
        <EarningStaticOne />

        {/* Financial Summary */}
        <TotalIncome columnClassName="col-xxl-5" />

        {/* RecentSitePhotos */}
        <div className="col-xl-4">
          <RecentSitePhotos />
        </div>

        {/* TodaySiteReports */}
        <div className="col-xl-4">
          <TodaySiteReports />
        </div>

        {/* MaterialStockStatus */}
        <div className="col-xl-4">
          <MaterialStockStatus />
        </div>
        {/* Active Projects */}
        <ActiveProjects />
      </section>
    </>
  );
};

export default DashBoardLayerOne;
