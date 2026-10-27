/* eslint-disable react/prop-types */
import ReactApexChart from "react-apexcharts";

const financialBreakdown = [
  { label: "Materials", percentage: 38, amount: "\u20B9 1,31,20,000", color: "#3385F5" },
  { label: "Labor", percentage: 25, amount: "\u20B9 86,30,000", color: "#45B369" },
  { label: "Subcontractors", percentage: 15, amount: "\u20B9 51,75,000", color: "#FF9F29" },
  { label: "Machinery", percentage: 10, amount: "\u20B9 34,52,000", color: "#8252E9" },
  { label: "Transportation", percentage: 7, amount: "\u20B9 24,15,000", color: "#F45B69" },
  { label: "Miscellaneous", percentage: 5, amount: "\u20B9 17,28,000", color: "#9CA3AF" },
];

const TotalIncome = ({ columnClassName = "col-xxl-12 col-xl-6" }) => {
  const chartOptions = {
    chart: { type: "donut", fontFamily: "inherit" },
    labels: financialBreakdown.map((item) => item.label),
    colors: financialBreakdown.map((item) => item.color),
    stroke: { width: 2, colors: ["#FFFFFF"] },
    dataLabels: { enabled: false },
    legend: { show: false },
    plotOptions: {
      pie: {
        expandOnClick: false,
        donut: { size: "64%" },
      },
    },
    tooltip: {
      y: {
        formatter: (value, { seriesIndex }) =>
          `${value}% - ${financialBreakdown[seriesIndex].amount}`,
      },
    },
  };

  return (
    <div className={columnClassName}>
      <div className="card h-100 radius-8 border financial-summary-card">
        <div className="card-body p-16 d-flex flex-column">
          <div className="d-flex align-items-center justify-content-between gap-3 mb-12">
            <h6 className="mb-0 fw-bold text-lg">Financial Summary</h6>
            <select
              className="form-select form-select-sm w-auto bg-base border text-primary-light"
              defaultValue="This Month"
              aria-label="Financial summary period"
            >
              <option value="This Month">This Month</option>
              <option value="This Week">This Week</option>
              <option value="This Year">This Year</option>
            </select>
          </div>

          <div className="financial-summary-content">
            <div className="financial-summary-chart">
              <ReactApexChart
                options={chartOptions}
                series={financialBreakdown.map((item) => item.percentage)}
                type="donut"
                height={205}
              />
              <div className="financial-summary-total">
                <strong>{"\u20B9 3,45,20,000"}</strong>
                <span>Total Cost</span>
              </div>
            </div>

            <div className="financial-summary-legend">
              {financialBreakdown.map((item) => (
                <div className="financial-summary-row" key={item.label}>
                  <span
                    className="financial-summary-dot"
                    style={{ backgroundColor: item.color }}
                  />
                  <span className="financial-summary-label">{item.label}</span>
                  <span className="financial-summary-percent">
                    {item.percentage}%
                  </span>
                  <strong>{item.amount}</strong>
                </div>
              ))}
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};

export default TotalIncome;
