import { SectionCard } from "../../../components/ui/AppUI";
import { useAuth } from "../../auth/context/AuthContext";
import "./dashboardFinancial.css";

export default function DashboardFinancialSummary() {
  const { can } = useAuth();
  if (!can("expenses.view")) return null;

  return (
    <SectionCard title="Financial Summary">
      <p className="dashboard-financial-unavailable">Financial summary is not available yet.</p>
      <p className="dashboard-financial-scope">Project and period filters do not apply.</p>
    </SectionCard>
  );
}
