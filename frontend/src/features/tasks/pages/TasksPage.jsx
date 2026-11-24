import { PageHeader } from "../../../components/ui/AppUI";
import TasksList from "../components/TasksList";

export default function TasksPage() {
  return (
    <div className="phase2-page">
      <PageHeader title="Tasks" description="Browse tasks across your projects." />
      <TasksList />
    </div>
  );
}
