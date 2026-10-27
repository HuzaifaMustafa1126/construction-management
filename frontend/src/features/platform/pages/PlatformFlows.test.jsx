/* @vitest-environment jsdom */
import { afterEach, describe, expect, it, vi } from "vitest";
import { cleanup, fireEvent, render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { MemoryRouter, Route, Routes } from "react-router-dom";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";

const {company,updateCompany,setupAdministratorPassword}=vi.hoisted(()=>({company:vi.fn(),updateCompany:vi.fn(),setupAdministratorPassword:vi.fn()}));
vi.mock("../../../api/endpoints",()=>({platformApi:{company,updateCompany},authApi:{setupAdministratorPassword},apiMessage:(_e,f)=>f||"Request failed",applyServerErrors:()=>{}}));
import { PlatformCompanyEdit } from "./PlatformPages";
import AdministratorPasswordSetupPage from "../../auth/pages/AdministratorPasswordSetupPage";

afterEach(()=>{cleanup();vi.clearAllMocks()});
function withQuery(ui,path){const client=new QueryClient({defaultOptions:{queries:{retry:false}}});return render(<QueryClientProvider client={client}><MemoryRouter initialEntries={[path]}>{ui}</MemoryRouter></QueryClientProvider>)}

describe("platform company editing",()=>{
  it("prefills and submits supported fields",async()=>{const old={id:4,name:"Old Builders",email:"old@example.test",phone:"+923001234567",address:"Old address"};company.mockResolvedValue(old);updateCompany.mockImplementation(async(_id,values)=>{company.mockResolvedValue({...old,...values});return{id:4,...values}});withQuery(<Routes><Route path="/platform/companies/:id/edit" element={<PlatformCompanyEdit/>}/></Routes>,"/platform/companies/4/edit");const name=await screen.findByLabelText("Company name");await waitFor(()=>expect(name.value).toBe("Old Builders"));fireEvent.change(name,{target:{value:"New Builders"}});fireEvent.submit(screen.getByRole("button",{name:"Save changes"}).closest("form"));await waitFor(()=>expect(updateCompany).toHaveBeenCalledWith("4",expect.objectContaining({name:"New Builders",email:"old@example.test"})));expect(await screen.findByText("Company updated successfully.")).toBeTruthy()});
});

describe("administrator password setup",()=>{
  it("submits token, email, and confirmed password",async()=>{setupAdministratorPassword.mockResolvedValue({});withQuery(<AdministratorPasswordSetupPage/>,"/administrator/password/setup?token=one-time-token&email=admin%40example.test");await userEvent.type(screen.getByLabelText("New password"),"NewStrongPassword1!");await userEvent.type(screen.getByLabelText("Confirm password"),"NewStrongPassword1!");await userEvent.click(screen.getByRole("button",{name:"Set password"}));await waitFor(()=>expect(setupAdministratorPassword).toHaveBeenCalledWith(expect.objectContaining({token:"one-time-token",email:"admin@example.test",password:"NewStrongPassword1!"})));expect(await screen.findByText("Your password was set successfully.")).toBeTruthy()});
});
