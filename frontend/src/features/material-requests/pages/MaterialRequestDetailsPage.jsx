/* eslint-disable react/prop-types */
import { useEffect, useState } from "react";
import { Link, useParams } from "react-router-dom";
import { toast } from "react-toastify";
import { AppBadge, AppButton, AppInput, AppModal, AppSelect, ConfirmDialog, PageHeader, SectionCard } from "../../../components/ui/AppUI";
import { AppAlert } from "../../../components/ui/Controls";
import { apiMessage } from "../../../api/endpoints";
import { useAuth } from "../../auth/context/AuthContext";
import { materialRequestsApi } from "../api/materialRequestsApi";
import { ownsMaterialRequestOrIsCompanyAdmin } from "../authorization";
import { useMaterialRequest, useMaterialRequestMutation } from "../hooks/useMaterialRequests";

export default function MaterialRequestDetailsPage() {
  const { id } = useParams();
  const { can, user } = useAuth();
  const query = useMaterialRequest(id);
  const mutation = useMaterialRequestMutation(({ action, payload }) => materialRequestsApi[action](id, payload));
  const [action, setAction] = useState("");
  const [cancelOpen, setCancelOpen] = useState(false);
  const run = async (name, payload, message) => { try { await mutation.mutateAsync({ action: name, payload }); toast.success(message); setAction(""); setCancelOpen(false); } catch (error) { toast.error(apiMessage(error)); } };
  if (query.isLoading) return <div role="status">Loading material request…</div>;
  if (query.error) return <AppAlert tone="danger">{apiMessage(query.error)}</AppAlert>;
  const request = query.data;
  const canManageOwnRequest = ownsMaterialRequestOrIsCompanyAdmin(user, request);
  return <div className="phase2-page">
    <PageHeader title={request.request_number} description={`${request.project?.name || "Project"} · ${request.status.replaceAll("_", " ")}`} action={<div className="button-group"><Link className="app-button secondary" to="/material-requests">Back</Link>{canManageOwnRequest && can("material_requests.submit") && request.status === "draft" && <AppButton busy={mutation.isPending} onClick={() => run("submit", undefined, "Request submitted for approval.")}>Submit</AppButton>}{can("material_requests.approve") && request.status === "submitted" && <><AppButton onClick={() => setAction("approve")}>Approve</AppButton><AppButton variant="danger" onClick={() => setAction("reject")}>Reject</AppButton></>}{can("material_requests.issue") && ["approved", "partially_issued"].includes(request.status) && <AppButton onClick={() => setAction("issue")}>Issue Stock</AppButton>}{can("material_requests.return") && ["partially_issued", "issued"].includes(request.status) && <AppButton variant="secondary" onClick={() => setAction("returnStock")}>Return Unused</AppButton>}{canManageOwnRequest && can("material_requests.update") && ["draft", "submitted"].includes(request.status) && <AppButton variant="danger" busy={mutation.isPending} onClick={() => setCancelOpen(true)}>Cancel Request</AppButton>}</div>}/>
    <SectionCard title="Request"><dl className="project-overview"><div><dt>Status</dt><dd><AppBadge>{request.status.replaceAll("_", " ")}</AppBadge></dd></div><div><dt>Required date</dt><dd>{request.required_date}</dd></div><div><dt>Requested by</dt><dd>{request.requester?.name || "—"}</dd></div><div><dt>Approved by</dt><dd>{request.approver?.name || "—"}</dd></div><div><dt>Reason</dt><dd>{request.reason || "—"}</dd></div></dl></SectionCard>
    <SectionCard title="Items"><div className="table-responsive"><table className="app-table"><thead><tr><th>Material</th><th>Requested</th><th>Approved</th><th>Issued</th><th>Returned</th></tr></thead><tbody>{request.items.map((item) => <tr key={item.id}><td>{item.material?.name}</td><td>{item.quantity_requested}</td><td>{item.quantity_approved}</td><td>{item.quantity_issued}</td><td>{item.quantity_returned}</td></tr>)}</tbody></table></div></SectionCard>
    {action && <RequestActionDialog action={action} request={request} busy={mutation.isPending} onClose={() => setAction("")} onRun={run}/>} 
    <ConfirmDialog open={cancelOpen} title="Cancel material request" message="Cancel this material request? This action cannot be undone." confirmLabel="Cancel Request" busy={mutation.isPending} onClose={() => setCancelOpen(false)} onConfirm={() => run("cancel", undefined, "Material request cancelled.")}/>
  </div>;
}

function RequestActionDialog({ action, request, busy, onClose, onRun }) {
  const [rows, setRows] = useState([]);
  const [date, setDate] = useState(new Date().toISOString().slice(0, 10));
  const [reason, setReason] = useState("");
  const [itemId, setItemId] = useState("");
  const [quantity, setQuantity] = useState("");
  useEffect(() => { setRows(request.items.map((item) => ({ id: item.id, quantity: action === "approve" ? item.quantity_requested : "" }))); setItemId(String(request.items[0]?.id || "")); }, [action, request.items]);
  const submit = (event) => {
    event.preventDefault();
    if (action === "reject") return onRun("reject", { reason }, "Request rejected.");
    if (action === "approve") return onRun("approve", { items: rows.map((row) => ({ id: row.id, quantity_approved: row.quantity })) }, "Request approved without changing stock.");
    if (action === "issue") return onRun("issue", { idempotency_key: crypto.randomUUID(), issue_date: date, items: rows.filter((row) => Number(row.quantity) > 0) }, "Approved materials issued and stock updated.");
    return onRun("returnStock", { idempotency_key: crypto.randomUUID(), return_date: date, item_id: Number(itemId), quantity }, "Unused material returned to stock.");
  };
  return <AppModal open title={{ approve: "Approve request", reject: "Reject request", issue: "Issue approved stock", returnStock: "Return unused stock" }[action]} onClose={onClose}><form className="photo-edit-form" onSubmit={submit}>{action === "reject" ? <AppInput label="Rejection reason" value={reason} onChange={(event) => setReason(event.target.value)} required/> : action === "returnStock" ? <><AppSelect label="Material" value={itemId} onChange={(event) => setItemId(event.target.value)}>{request.items.filter((item) => Number(item.quantity_issued) > Number(item.quantity_returned)).map((item) => <option key={item.id} value={item.id}>{item.material?.name}</option>)}</AppSelect><AppInput label="Return date" type="date" value={date} onChange={(event) => setDate(event.target.value)} required/><AppInput label="Quantity" inputMode="decimal" value={quantity} onChange={(event) => setQuantity(event.target.value)} required/></> : <>{action === "issue" && <AppInput label="Issue date" type="date" value={date} onChange={(event) => setDate(event.target.value)} required/>}{rows.map((row, index) => <AppInput key={row.id} label={`${request.items[index].material?.name} quantity`} inputMode="decimal" value={row.quantity} onChange={(event) => setRows((values) => values.map((value) => value.id === row.id ? { ...value, quantity: event.target.value } : value))} required={action === "approve"}/>)}</>}<div className="modal-actions"><AppButton type="button" variant="secondary" onClick={onClose}>Cancel</AppButton><AppButton busy={busy}>Confirm</AppButton></div></form></AppModal>;
}
