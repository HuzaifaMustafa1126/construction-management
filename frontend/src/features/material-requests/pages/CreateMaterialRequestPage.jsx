import { useState } from "react";
import { useNavigate, useSearchParams } from "react-router-dom";
import { toast } from "react-toastify";
import { AppButton, AppInput, AppSelect, PageHeader, SectionCard } from "../../../components/ui/AppUI";
import { AppAlert } from "../../../components/ui/Controls";
import { apiMessage } from "../../../api/endpoints";
import { useProjects } from "../../projects/hooks/useProjects";
import { useProjectInventory } from "../../materials/hooks/useProjectInventory";
import { materialRequestsApi } from "../api/materialRequestsApi";
import { useMaterialRequestMutation } from "../hooks/useMaterialRequests";
import { materialRequestSchema } from "../schemas/materialRequestSchema";

export default function CreateMaterialRequestPage() {
  const navigate = useNavigate();
  const [params] = useSearchParams();
  const [projectId, setProjectId] = useState(params.get("project") || "");
  const [requiredDate, setRequiredDate] = useState("");
  const [reason, setReason] = useState("");
  const [items, setItems] = useState([{ project_inventory_id: "", quantity_requested: "", notes: "" }]);
  const [error, setError] = useState("");
  const projects = useProjects({ page: 1, per_page: 100, sort: "name", direction: "asc" });
  const inventory = useProjectInventory(projectId, { page: 1, per_page: 100, sort: "material_name", direction: "asc" });
  const mutation = useMaterialRequestMutation(materialRequestsApi.create);
  const update = (index, field, value) => setItems((rows) => rows.map((row, rowIndex) => rowIndex === index ? { ...row, [field]: value } : row));
  const save = async (event) => {
    event.preventDefault();
    const parsed = materialRequestSchema.safeParse({ project_id: projectId, required_date: requiredDate, reason, items });
    if (!parsed.success) return setError(parsed.error.issues[0]?.message || "Check the request fields.");
    try { const request = await mutation.mutateAsync(parsed.data); toast.success("Material request created as a draft."); navigate(`/material-requests/${request.id}`); } catch (requestError) { setError(apiMessage(requestError)); }
  };
  return <div className="phase2-page"><PageHeader title="New Material Request" description="Approval records intent only; stock changes when approved quantities are issued."/><SectionCard title="Request details"><form className="photo-edit-form" onSubmit={save}>{error && <AppAlert tone="danger">{error}</AppAlert>}<AppSelect label="Project" value={projectId} onChange={(event) => { setProjectId(event.target.value); setItems([{ project_inventory_id: "", quantity_requested: "", notes: "" }]); }} required><option value="">Select project</option>{(projects.data?.data || []).map((project) => <option key={project.id} value={project.id}>{project.name}</option>)}</AppSelect><AppInput label="Required date" type="date" value={requiredDate} onChange={(event) => setRequiredDate(event.target.value)} required/><AppInput label="Reason" value={reason} onChange={(event) => setReason(event.target.value)}/>{items.map((item, index) => <div className="filter-bar" key={index}><AppSelect label={`Material ${index + 1}`} value={item.project_inventory_id} onChange={(event) => update(index, "project_inventory_id", event.target.value)} required><option value="">Select assigned material</option>{(inventory.data?.data || []).map((row) => <option key={row.id} value={row.id}>{row.material.name} ({row.current_stock} {row.material.measurement_unit?.symbol})</option>)}</AppSelect><AppInput label="Quantity" inputMode="decimal" value={item.quantity_requested} onChange={(event) => update(index, "quantity_requested", event.target.value)} required/><AppInput label="Item notes" value={item.notes} onChange={(event) => update(index, "notes", event.target.value)}/>{items.length > 1 && <AppButton type="button" variant="danger" onClick={() => setItems((rows) => rows.filter((_, rowIndex) => rowIndex !== index))}>Remove</AppButton>}</div>)}<div className="button-group"><AppButton type="button" variant="secondary" onClick={() => setItems((rows) => [...rows, { project_inventory_id: "", quantity_requested: "", notes: "" }])}>Add Item</AppButton><AppButton type="button" variant="secondary" onClick={() => navigate("/material-requests")}>Cancel</AppButton><AppButton busy={mutation.isPending}>Save Draft</AppButton></div></form></SectionCard></div>;
}
