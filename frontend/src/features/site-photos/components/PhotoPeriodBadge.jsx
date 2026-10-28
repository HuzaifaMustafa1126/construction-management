/* eslint-disable react/prop-types */
import { AppBadge } from "../../../components/ui/AppUI";
import { humanizePhotoValue } from "../utils/photoFormatters";
export default function PhotoPeriodBadge({ period }) { return <AppBadge tone={period === "morning" ? "warning" : period === "evening" ? "info" : "neutral"}>{humanizePhotoValue(period)}</AppBadge>; }
