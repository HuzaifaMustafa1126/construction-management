/* eslint-disable react/prop-types */
import { AppBadge } from "../../../components/ui/AppUI";
export default function ProjectStatusBadge({ status }) {
  const tone = status === "completed" || status === "active" ? "success" : status === "cancelled" ? "danger" : "neutral";
  return <AppBadge tone={tone}>{String(status || "unknown").replaceAll("_", " ")}</AppBadge>;
}
