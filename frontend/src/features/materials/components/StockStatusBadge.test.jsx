import { render, screen } from "@testing-library/react";
import { describe, expect, it } from "vitest";
import StockStatusBadge, { stockStatus } from "./StockStatusBadge";
describe("inventory stock status", () => {
  it("uses decimal-string comparisons for all status boundaries", () => { expect(stockStatus("0.0000", "10.0000")).toBe("out_of_stock"); expect(stockStatus("10.0000", "10.0000")).toBe("low_stock"); expect(stockStatus("10.0001", "10.0000")).toBe("good"); });
  it("renders the semantic status label", () => { render(<StockStatusBadge stock="2.50" minimum="2.50"/>); expect(screen.getByText("LOW STOCK")).toBeInTheDocument(); });
});
