import type { ReactNode } from "react";

// Line icons drawn on a 16x16 grid. They take the text colour and are sized by the `size` prop.
const PATHS = {
  menu: <path d="M2 4h12M2 8h12M2 12h12" />,
  gauge: (
    <>
      <path d="M2.2 11.8A6.2 6.2 0 1 1 13.8 11.8" />
      <path d="M8 9.6l2.6-3.4" />
      <circle cx="8" cy="9.6" r="1" />
    </>
  ),
  sun: (
    <>
      <circle cx="8" cy="8" r="2.8" />
      <path d="M8 1.5v1.6M8 12.9v1.6M1.5 8h1.6M12.9 8h1.6M3.4 3.4l1.1 1.1M11.5 11.5l1.1 1.1M3.4 12.6l1.1-1.1M11.5 4.5l1.1-1.1" />
    </>
  ),
  moon: <path d="M13.5 9.8A6 6 0 0 1 6.2 2.5a6 6 0 1 0 7.3 7.3z" />,
  refresh: (
    <>
      <path d="M13 8a5 5 0 1 1-1.5-3.5" />
      <path d="M13 2.3v3h-3" />
    </>
  ),
  logout: (
    <>
      <path d="M6 2.5H3.5a1 1 0 0 0-1 1v9a1 1 0 0 0 1 1H6" />
      <path d="M6.5 8h7M11 5.5 13.5 8 11 10.5" />
    </>
  ),
  external: (
    <>
      <path d="M9 2.5h4.5V7M13.5 2.5l-6 6" />
      <path d="M11.5 9.5v3a1 1 0 0 1-1 1h-7a1 1 0 0 1-1-1v-7a1 1 0 0 1 1-1h3" />
    </>
  ),
  chevronUp: <path d="M3.5 10 8 5.5l4.5 4.5" />,
  people: (
    <>
      <circle cx="6" cy="5.5" r="2.3" />
      <path d="M1.8 13c.4-2.4 2-3.6 4.2-3.6s3.8 1.2 4.2 3.6" />
      <circle cx="11.6" cy="6" r="1.8" />
      <path d="M12.1 9.6c1.4.3 2.2 1.4 2.4 3.1" />
    </>
  ),
  calendar: (
    <>
      <rect x="2.5" y="3.5" width="11" height="10" rx="1.2" />
      <path d="M2.5 6.5h11M5.5 2v3M10.5 2v3M5.8 10l1.6 1.6 3-3.2" />
    </>
  ),
  hospital: (
    <>
      <path d="M2.5 13.5v-7l5.5-4 5.5 4v7z" />
      <path d="M8 7v4M6 9h4" />
    </>
  ),
  alert: (
    <>
      <path d="M8 2.2 14.3 13H1.7z" />
      <path d="M8 6.5v3.2M8 11.3v.2" />
    </>
  ),
  lock: (
    <>
      <rect x="3.5" y="7" width="9" height="6.5" rx="1.2" />
      <path d="M5.5 7V5a2.5 2.5 0 0 1 5 0v2" />
    </>
  ),
} satisfies Record<string, ReactNode>;

export type IconName = keyof typeof PATHS;

/** Decorative by default (hidden from assistive tech): the control around it carries the label. */
export default function Icon({ name, size = 14, className }: { name: IconName; size?: number; className?: string }) {
  return (
    <svg
      className={className ? `icon ${className}` : "icon"}
      width={size}
      height={size}
      viewBox="0 0 16 16"
      fill="none"
      stroke="currentColor"
      strokeWidth="1.3"
      strokeLinecap="round"
      strokeLinejoin="round"
      aria-hidden="true"
      focusable="false"
    >
      {PATHS[name]}
    </svg>
  );
}
