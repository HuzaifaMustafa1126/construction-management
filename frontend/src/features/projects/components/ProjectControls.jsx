/* eslint-disable react/prop-types */
import { useState } from "react";
import { toast } from "react-toastify";
import { AppButton, AppInput, AppSelect } from "../../../components/ui/AppUI";
import { apiMessage } from "../../../api/endpoints";
import { projectsApi } from "../api/projectsApi";
import { STATUS_TRANSITIONS } from "../schemas/projectSchema";
import { useProjectMutation } from "../hooks/useProjects";

export function ProjectStatusControl({ project }) { const options = STATUS_TRANSITIONS[project.status] || []; const [value, setValue] = useState(options[0] || ""); const mutation = useProjectMutation(() => projectsApi.status(project.id, value)); const submit = async (e) => { e.preventDefault(); try { await mutation.mutateAsync(); toast.success("Project status updated."); } catch (error) { toast.error(apiMessage(error)); } }; if (!options.length) return <p>No further status transitions are available.</p>; return <form className="inline-control" onSubmit={submit}><AppSelect label="Next status" value={value} onChange={(e) => setValue(e.target.value)}>{options.map((x) => <option key={x} value={x}>{x.replaceAll("_", " ")}</option>)}</AppSelect><AppButton busy={mutation.isPending}>Update status</AppButton></form>; }
export function ProjectProgressControl({ project }) { const [value, setValue] = useState(project.progress_percentage); const mutation = useProjectMutation(() => projectsApi.progress(project.id, Number(value))); const submit = async (e) => { e.preventDefault(); try { await mutation.mutateAsync(); toast.success("Project progress updated."); } catch (error) { toast.error(apiMessage(error)); } }; return <form className="inline-control" onSubmit={submit}><AppInput label="Progress percentage" type="number" min="0" max="100" required value={value} onChange={(e) => setValue(e.target.value)}/><AppButton busy={mutation.isPending}>Update progress</AppButton></form>; }
