import { create } from "zustand";
export type ColorMode = "light" | "dark";
export type Accent = "blue" | "green" | "orange";
export type Density = "comfortable" | "compact";
type Settings = {
  mode: ColorMode;
  accent: Accent;
  density: Density;
  sidebarCollapsed: boolean;
  setMode: (v: ColorMode) => void;
  setAccent: (v: Accent) => void;
  setDensity: (v: Density) => void;
  setSidebarCollapsed: (v: boolean) => void;
};
const read = <T>(key: string, fallback: T): T => {
  try {
    return (JSON.parse(localStorage.getItem(key) || "null") ?? fallback) as T;
  } catch {
    return fallback;
  }
};
export const useThemeStore = create<Settings>((set) => ({
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
