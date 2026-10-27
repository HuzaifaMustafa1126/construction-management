import { create } from "zustand";
const read = (key, fallback) => {
  try {
    return JSON.parse(localStorage.getItem(key) || "null") ?? fallback;
  } catch {
    return fallback;
  }
};
export const useThemeStore = create((set) => ({
  mode: read("ui.mode", "light"),
  accent: read("ui.accent", "blue"),
  density: read("ui.density", "comfortable"),
  sidebarCollapsed: read("ui.sidebarCollapsed", false),
  setMode: (v) => {
    localStorage.setItem("ui.mode", JSON.stringify(v));
    set({ mode: v });
  },
  setAccent: (v) => {
    localStorage.setItem("ui.accent", JSON.stringify(v));
    set({ accent: v });
  },
  setDensity: (v) => {
    localStorage.setItem("ui.density", JSON.stringify(v));
    set({ density: v });
  },
  setSidebarCollapsed: (v) => {
    localStorage.setItem("ui.sidebarCollapsed", JSON.stringify(v));
    set({ sidebarCollapsed: v });
  },
}));
