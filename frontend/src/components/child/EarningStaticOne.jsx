import ReactApexChart from "react-apexcharts";

const projectProgress = [
  { name: "Villa Emerald", progress: 85 },
  { name: "Sunshine Apartments", progress: 60 },
  { name: "Green Valley", progress: 40 },
  { name: "City Plaza", progress: 75 },
  { name: "Lakeview Residence", progress: 25 },
  { name: "Tech Park", progress: 90 },
];

const EarningStaticOne = () => {
  const chartSeries = [
    {
      name: "Project Progress",
      data: projectProgress.map((project) => project.progress),
    },
  ];

  const chartOptions = {
    chart: {
      type: "bar",
      toolbar: { show: false },
      zoom: { enabled: false },
      fontFamily: "inherit",
    },
    colors: ["#3385F5", "#45B369", "#FF9F29", "#8252E9", "#F45B69", "#22B8C7"],
    plotOptions: {
      bar: {
        distributed: true,
        columnWidth: "55%",
        borderRadius: 1,
        dataLabels: { position: "top" },
      },
    },
    dataLabels: {
      enabled: true,
      formatter: (value) => `${value}%`,
      offsetY: -20,
      style: {
        fontSize: "12px",
        fontWeight: 600,
        colors: ["#111827"],
      },
      background: { enabled: false },
    },
    grid: {
      show: true,
      borderColor: "#E5E7EB",
      strokeDashArray: 0,
      padding: { top: 16, right: 6, bottom: 0, left: 4 },
    },
    xaxis: {
      categories: [
        ["Villa", "Emerald"],
        ["Sunshine", "Apartments"],
        "Green Valley",
        "City Plaza",
        ["Lakeview", "Residence"],
        "Tech Park",
      ],
      axisBorder: { show: true, color: "#D1D5DB" },
      axisTicks: { show: false },
      labels: {
        trim: false,
        rotate: 0,
        style: { fontSize: "11px", colors: "#374151" },
      },
    },
    yaxis: {
      min: 0,
      max: 100,
      tickAmount: 5,
      labels: {
        formatter: (value) => `${Math.round(value)}%`,
        style: { fontSize: "11px", colors: ["#6B7280"] },
      },
    },
    legend: { show: false },
    tooltip: {
      y: { formatter: (value) => `${value}% complete` },
    },
  };

  return (
    <div className="col-xxl-7">
      <div className="card h-100 radius-8 border">
        <div className="card-body p-20">
          <div className="d-flex align-items-center justify-content-between gap-3 mb-8">
            <h6 className="mb-0 fw-bold text-lg">Project Progress Overview</h6>
            <select
              className="form-select form-select-sm w-auto bg-base border text-primary-light"
              defaultValue="Project Progress"
              aria-label="Choose project chart metric"
            >
              <option value="Project Progress">Project Progress</option>
              <option value="Budget Utilization">Budget Utilization</option>
              <option value="Time Utilization">Time Utilization</option>
            </select>
          </div>

          <ReactApexChart
            options={chartOptions}
            series={chartSeries}
            type="bar"
            height={210}
          />
        </div>
      </div>
    </div>
  );
};

export default EarningStaticOne;
