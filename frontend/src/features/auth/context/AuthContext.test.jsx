/* @vitest-environment jsdom */
import { afterEach, describe, expect, it, vi } from "vitest";
import { cleanup, fireEvent, render, screen, waitFor } from "@testing-library/react";

const api=vi.hoisted(()=>({me:vi.fn(),logout:vi.fn(),company:vi.fn()}));
vi.mock("../../../api/endpoints",()=>({authApi:{me:api.me,logout:api.logout},companyApi:{get:api.company}}));
import { AuthProvider, useAuth, useCompany } from "./AuthContext";

function Probe(){const auth=useAuth();const company=useCompany();return <><span>{auth.loading?"loading":auth.user?.email||"guest"}</span><span>{company.company?.name||"no-company"}</span><button onClick={auth.logout}>logout</button></>}
afterEach(()=>{cleanup();sessionStorage.clear();vi.clearAllMocks()});
describe("authentication session state",()=>{
  it("restores user and company from the backend when a session token exists",async()=>{sessionStorage.setItem("auth_token","opaque-token");api.me.mockResolvedValue({id:1,email:"admin@example.test",company:{id:7,name:"Fallback"}});api.company.mockResolvedValue({id:7,name:"Company One"});render(<AuthProvider><Probe/></AuthProvider>);expect(await screen.findByText("admin@example.test")).toBeTruthy();expect(screen.getByText("Company One")).toBeTruthy()});
  it("clears identity, company, token, and protected caches on logout",async()=>{sessionStorage.setItem("auth_token","opaque-token");api.me.mockResolvedValue({id:1,email:"admin@example.test",company:{id:7,name:"Fallback"}});api.company.mockResolvedValue({id:7,name:"Company One"});api.logout.mockResolvedValue({});const cleared=vi.fn();window.addEventListener("tenant-cache-clear",cleared);render(<AuthProvider><Probe/></AuthProvider>);await screen.findByText("admin@example.test");fireEvent.click(screen.getByRole("button",{name:"logout"}));await waitFor(()=>expect(screen.getByText("guest")).toBeTruthy());expect(sessionStorage.getItem("auth_token")).toBeNull();expect(screen.getByText("no-company")).toBeTruthy();expect(cleared).toHaveBeenCalled();window.removeEventListener("tenant-cache-clear",cleared)});
  it("clears an expired session after the API client event",async()=>{sessionStorage.setItem("auth_token","expired-token");api.me.mockResolvedValue({id:1,email:"user@example.test",company:{id:1,name:"Company"}});api.company.mockResolvedValue({id:1,name:"Company"});render(<AuthProvider><Probe/></AuthProvider>);await screen.findByText("user@example.test");window.dispatchEvent(new Event("auth-expired"));expect(await screen.findByText("guest")).toBeTruthy();expect(sessionStorage.getItem("auth_token")).toBeNull()});
});
