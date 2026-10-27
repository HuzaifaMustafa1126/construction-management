/* @vitest-environment jsdom */
import { expect,it,vi } from "vitest";
import { render,screen,fireEvent } from "@testing-library/react";
import PermissionSelector from "./PermissionSelector";
it("selects and clears backend-provided permission groups",()=>{const change=vi.fn();const{rerender}=render(<PermissionSelector groups={{users:["users.view","users.create"]}} value={[]} onChange={change}/>);fireEvent.click(screen.getByRole("button",{name:"Select group"}));expect(change).toHaveBeenCalledWith(["users.view","users.create"]);rerender(<PermissionSelector groups={{users:["users.view","users.create"]}} value={["users.view","users.create"]} onChange={change}/>);fireEvent.click(screen.getByRole("button",{name:"Clear"}));expect(change).toHaveBeenLastCalledWith([])});
