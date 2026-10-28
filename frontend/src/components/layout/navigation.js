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
