/* eslint-disable react/prop-types */
import { AppBadge } from "../../../components/ui/AppUI";
import { humanize } from "../utils/reportFormatters";

const tones = { draft: "neutral", submitted: "info", approved: "success", changes_required: "warning" };
export default function ReportStatusBadge({ status }) { return <AppBadge tone={tones[status] || "neutral"}>{humanize(status)}</AppBadge>; }
