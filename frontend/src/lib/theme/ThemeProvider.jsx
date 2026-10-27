import { useEffect } from "react";
import { useThemeStore } from "./themeStore";
export function ThemeProvider({ children }) {
  const { mode, accent, density } = useThemeStore();
  useEffect(() => {
    const root = document.documentElement;
    root.dataset.mode = mode;
    root.dataset.accent = accent;
    root.dataset.density = density;
    root.style.colorScheme = mode;
  }, [mode, accent, density]);
  return children;
}
