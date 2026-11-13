import type { IconName } from "@/components/Icon";

export type MenuItem = {
  id: string;
  label: string;
  /** Route inside this app (without basePath). */
  href: string;
  icon: IconName;
};

export type MenuGroup = {
  id: string;
  label: string;
  icon: IconName;
  items: MenuItem[];
};

/** The whole sidebar. A new page is added here and nowhere else. */
export const MENU: MenuGroup[] = [
  {
    id: "dashboard",
    label: "대시보드",
    icon: "gauge",
    items: [{ id: "dashboard", label: "대시보드", href: "/", icon: "gauge" }],
  },
];
