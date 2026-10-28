import { useState } from "react";
import { useNavigate } from "react-router-dom";
import { toast } from "react-toastify";
import { AppButton, AppInput, AppSelect, PageHeader, SectionCard } from "../../../components/ui/AppUI";
import { AppAlert } from "../../../components/ui/Controls";
import { apiMessage } from "../../../api/endpoints";
import { useProjectInventory } from "../../materials/hooks/useProjectInventory";
import { useProjects } from "../../projects/hooks/useProjects";
import { materialRequestsApi } from "../api/materialRequestsApi";
import { useMaterialRequestMutation } from "../hooks/useMaterialRequests";

export default function InventoryTransferPage() {
  const navigate = useNavigate();
  const [sourceProject, setSourceProject] = useState("");
  const [destinationProject, setDestinationProject] = useState("");
  const [sourceInventory, setSourceInventory] = useState("");
  const [destinationInventory, setDestinationInventory] = useState("");
  const [quantity, setQuantity] = useState("");
  const [date, setDate] = useState(new Date().toISOString().slice(0, 10));
  const [notes, setNotes] = useState("");
  const [error, setError] = useState("");
  const projects = useProjects({ page: 1, per_page: 100, sort: "name", direction: "asc" });
  const source = useProjectInventory(sourceProject, { page: 1, per_page: 100 });
  const destination = useProjectInventory(destinationProject, { page: 1, per_page: 100 });
  const selected = (source.data?.data || []).find((item) => String(item.id) === sourceInventory);
  const destinations = (destination.data?.data || []).filter((item) => !selected || item.material_id === selected.material_id || item.material?.id === selected.material?.id);
  const mutation = useMaterialRequestMutation(materialRequestsApi.transfer);
  const submit = async (event) => {
    event.preventDefault(); setError("");
    try {
      await mutation.mutateAsync({ idempotency_key: crypto.randomUUID(), source_project_id: Number(sourceProject), destination_project_id: Number(destinationProject), source_inventory_id: Number(sourceInventory), destination_inventory_id: Number(destinationInventory), quantity, transfer_date: date, notes: notes || null });
      toast.success("Stock transferred atomically between projects."); navigate(`/projects/${sourceProject}/inventory`);
    } catch (requestError) { setError(apiMessage(requestError)); }
  };
  return <div className="phase2-page"><PageHeader title="Transfer Project Stock" description="Creates matching outgoing and incoming ledger entries in one atomic operation."/><SectionCard title="Transfer details"><form className="photo-edit-form" onSubmit={submit}>{error && <AppAlert tone="danger">{error}</AppAlert>}<AppSelect label="Source project" value={sourceProject} onChange={(event) => { setSourceProject(event.target.value); setSourceInventory(""); }} required><option value="">Select project</option>{(projects.data?.data || []).map((project) => <option key={project.id} value={project.id}>{project.name}</option>)}</AppSelect><AppSelect label="Source material" value={sourceInventory} onChange={(event) => { setSourceInventory(event.target.value); setDestinationInventory(""); }} required><option value="">Select material</option>{(source.data?.data || []).map((item) => <option key={item.id} value={item.id}>{item.material.name} — available {item.current_stock} {item.material.measurement_unit?.symbol}</option>)}</AppSelect><AppSelect label="Destination project" value={destinationProject} onChange={(event) => { setDestinationProject(event.target.value); setDestinationInventory(""); }} required><option value="">Select project</option>{(projects.data?.data || []).filter((project) => String(project.id) !== sourceProject).map((project) => <option key={project.id} value={project.id}>{project.name}</option>)}</AppSelect><AppSelect label="Destination material assignment" value={destinationInventory} onChange={(event) => setDestinationInventory(event.target.value)} required><option value="">Select matching material</option>{destinations.map((item) => <option key={item.id} value={item.id}>{item.material.name}</option>)}</AppSelect><AppInput label="Quantity" inputMode="decimal" value={quantity} onChange={(event) => setQuantity(event.target.value)} required/><AppInput label="Transfer date" type="date" value={date} onChange={(event) => setDate(event.target.value)} required/><AppInput label="Notes" value={notes} onChange={(event) => setNotes(event.target.value)}/><div className="button-group"><AppButton type="button" variant="secondary" onClick={() => navigate("/material-requests")}>Cancel</AppButton><AppButton busy={mutation.isPending}>Transfer Stock</AppButton></div></form></SectionCard></div>;
}
