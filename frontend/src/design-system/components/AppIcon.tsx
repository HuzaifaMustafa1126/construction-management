import type { ComponentProps } from "react";
import { iconRegistry, type AppIconName } from "../icons/iconRegistry";

const sizes = { xs: 14, sm: 16, md: 20, lg: 24, xl: 32 } as const;
type Props = Omit<ComponentProps<"svg">, "name"> & {
  name: AppIconName;
  size?: keyof typeof sizes | number;
  title?: string;
};
export function AppIcon({ name, size = "md", title, ...props }: Props) {
  const Icon = iconRegistry[name];
  const pixels = typeof size === "number" ? size : sizes[size];
  return (
    <Icon
      width={pixels}
      height={pixels}
      strokeWidth={1.75}
      aria-hidden={title ? undefined : true}
      aria-label={title}
      {...props}
    />
  );
}
