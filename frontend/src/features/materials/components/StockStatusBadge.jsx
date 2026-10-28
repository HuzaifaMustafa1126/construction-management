/* eslint-disable react/prop-types, react-refresh/only-export-components */
import { AppBadge } from "../../../components/ui/AppUI";
import { compareDecimal } from "../utils/materialFormatters";
export function stockStatus(stock, minimum) { if (compareDecimal(stock, "0") === 0) return "out_of_stock"; if (compareDecimal(stock, "0") > 0 && compareDecimal(stock, minimum) <= 0) return "low_stock"; return "good"; }
export default function StockStatusBadge({ stock, minimum }) { const status = stockStatus(stock, minimum); const labels = { good: "GOOD", low_stock: "LOW STOCK", out_of_stock: "OUT OF STOCK" }; return <AppBadge tone={status === "good" ? "success" : status === "low_stock" ? "warning" : "danger"}>{labels[status]}</AppBadge>; }
