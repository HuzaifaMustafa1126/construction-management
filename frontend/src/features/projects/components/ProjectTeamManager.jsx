/* eslint-disable react/prop-types */
import { useEffect, useMemo, useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { toast } from "react-toastify";
import { usersApi, apiMessage } from "../../../api/endpoints";
import { AppButton, AppSelect, EmptyState } from "../../../components/ui/AppUI";
import { AppAlert } from "../../../components/ui/Controls";
import { useProjectMutation, useProjectTeam } from "../hooks/useProjects";
import { projectsApi } from "../api/projectsApi";

export default function ProjectTeamManager({ projectId, canManage }) {
  const team = useProjectTeam(projectId, canManage); const users = useQuery({ queryKey: ["project-eligible-users"], queryFn: () => usersApi.list({ status: "Active", per_page: 100 }), enabled: canManage });
  const [members, setMembers] = useState([]); const [userId, setUserId] = useState(""); const [type, setType] = useState("member");
  useEffect(() => { if (team.data) setMembers(team.data); }, [team.data]);
  const userRows = useMemo(() => users.data?.users || users.data?.data || [], [users.data]);
  const available = useMemo(() => userRows.filter((user) => {
    if (members.some((member) => Number(member.id) === Number(user.id))) return false;
    const roles = (user.roles || [user.role]).map((role) => typeof role === "string" ? role : role?.name);
    return type === "client" ? roles.includes("client") : type === "supervisor" ? roles.includes("site_supervisor") : roles.some((role) => ["company_admin", "site_supervisor"].includes(role));
  }), [userRows, members, type]);
  const save = useProjectMutation((payload) => projectsApi.updateTeam(projectId, payload));
  if (!canManage) return <AppAlert>Team membership is restricted to authorized project administrators.</AppAlert>;
  if (team.isLoading || users.isLoading) return <div role="status">Loading project team…</div>;
  if (team.error) return <AppAlert tone="danger">{apiMessage(team.error, "The project team could not be loaded.")}</AppAlert>;
  const persist = async (next) => { try { const result = await save.mutateAsync(next.map((m) => ({ user_id: Number(m.id), assignment_type: m.assignment_type }))); setMembers(result); toast.success("Project team updated."); } catch (error) { toast.error(apiMessage(error)); } };
  const add = () => { const user = available.find((u) => Number(u.id) === Number(userId)); if (user) persist([...members, { ...user, assignment_type: type }]); };
  return <div className="project-team">
    {!members.length && <EmptyState title="No team members" description="Assign active company users to this project."/>}
    {members.map((member) => <div className="project-team-row" key={member.id}><span><strong>{member.name}</strong><small>{member.email} · {member.assignment_type}</small></span><AppButton variant="secondary" busy={save.isPending} onClick={() => persist(members.filter((m) => m.id !== member.id))}>Remove</AppButton></div>)}
    <div className="project-team-add"><AppSelect label="Company user" value={userId} onChange={(e) => setUserId(e.target.value)}><option value="">Select a user</option>{available.map((u) => <option key={u.id} value={u.id}>{u.name || `${u.first_name} ${u.last_name}`}</option>)}</AppSelect><AppSelect label="Assignment type" value={type} onChange={(e) => setType(e.target.value)}><option value="member">Member</option><option value="supervisor">Supervisor</option><option value="client">Client</option></AppSelect><AppButton type="button" disabled={!userId} busy={save.isPending} onClick={add}>Assign</AppButton></div>
  </div>;
}
