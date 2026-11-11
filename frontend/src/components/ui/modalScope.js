import { createContext } from "react";

// Each select uses its owning dialog as the portal target and focus boundary.
export const ModalScope = createContext(null);
const stack = [];
export const topModal = () => stack[stack.length - 1] || null;
export function registerModal(dialog) {
  stack.push(dialog);
  document.dispatchEvent(new Event("app-modal-stack-change"));
  return () => {
    const index = stack.indexOf(dialog);
    if (index >= 0) stack.splice(index, 1);
    document.dispatchEvent(new Event("app-modal-stack-change"));
  };
}
