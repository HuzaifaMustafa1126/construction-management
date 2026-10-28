export const companyNavigation = [
  {
    label: "Overview",
    items: [{ label: "Dashboard", route: "/dashboard", icon: "dashboard" }],
  },
  {
    label: "Work",
    items: [
      {
        label: "Projects",
        route: "/projects",
        icon: "projects",
        permission: "projects.view",
      },
      { label: "Daily Site Reports", route: "/daily-reports", icon: "reports", permission: "site_reports.view" },
      { label: "Site Photos", route: "/site-photos", icon: "photos", permission: "photos.view" },
      { label: "Materials", route: "/materials", icon: "materials", permission: "materials.view" },
      { label: "Material Requests", route: "/material-requests", icon: "reports", permission: "material_requests.view" },
      { label: "Stock Ledger", route: "/materials/transactions", icon: "database", permission: "inventory_reports.view_movements" },
      { label: "Material Deliveries", route: "/materials/deliveries", icon: "materials", permission: "material_deliveries.view" },
      { label: "Direct Consumption", route: "/materials/consumptions", icon: "materials", permission: "material_consumptions.view" },
      { label: "Stock Adjustments", route: "/materials/adjustments", icon: "settings", permission: "stock_adjustments.view" },
    ],
  },
  {
    label: "Organization",
    items: [
      {
        label: "Users",
        route: "/users",
        icon: "users",
        permission: "users.view",
      },
      {
        label: "Roles & Permissions",
        route: "/roles",
        icon: "roles",
        permission: "roles.view",
      },
      {
        label: "Departments",
        route: "/departments",
        icon: "database",
        permission: "departments.view",
      },
      {
        label: "Company Profile",
        route: "/company",
        icon: "company",
        permission: "company.view",
      },
    ],
  },
  {
    label: "Account",
    items: [
      { label: "My Profile", route: "/profile", icon: "user" },
      { label: "Settings", route: "/settings", icon: "settings" },
    ],
  },
];
