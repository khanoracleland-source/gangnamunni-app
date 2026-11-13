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
  help: (
    <>
      <circle cx="8" cy="8" r="6" />
      <path d="M6.2 6.3a1.9 1.9 0 0 1 3.7.6c0 1.3-1.9 1.5-1.9 2.7M8 11.5v.2" />
    </>
  ),
  info: (
    <>
      <circle cx="8" cy="8" r="6" />
      <path d="M8 7.2v4M8 4.9v.2" />
    </>
  ),
  close: <path d="M3.5 3.5l9 9M12.5 3.5l-9 9" />,
  globe: (
    <>
      <circle cx="8" cy="8" r="6" />
      <path d="M2 8h12M8 2c2 1.8 2.8 3.8 2.8 6S10 12.2 8 14C6 12.2 5.2 10.2 5.2 8S6 3.8 8 2z" />
    </>
  ),
  building: (
    <>
      <rect x="3" y="2.5" width="10" height="11" rx="1" />
      <path d="M6 5.5h1M9 5.5h1M6 8h1M9 8h1M6.8 13.5v-2.2h2.4v2.2" />
    </>
  ),
  collection: (
    <>
      <rect x="2.5" y="4.5" width="11" height="8.5" rx="1.2" />
      <path d="M4.5 2.5h7" />
    </>
  ),
  megaphone: (
    <>
      <path d="M2.5 6.5v3h2.2L10 12.5v-9L4.7 6.5z" />
      <path d="M12.2 6.2c.8.6.8 2.9 0 3.6" />
    </>
  ),
  clipboard: (
    <>
      <rect x="3.5" y="3" width="9" height="10.5" rx="1.2" />
      <path d="M6 3V2h4v1M5.8 7.5h4.4M5.8 10h3" />
    </>
  ),
  userPlus: (
    <>
      <circle cx="6.5" cy="5.5" r="2.3" />
      <path d="M2.3 13c.4-2.4 2-3.6 4.2-3.6s3.8 1.2 4.2 3.6M12.5 5v4M10.5 7h4" />
    </>
  ),
  heart: <path d="M8 13.5S2.5 10 2.5 6.2A2.9 2.9 0 0 1 8 5a2.9 2.9 0 0 1 5.5 1.2C13.5 10 8 13.5 8 13.5z" />,
  sheet: (
    <>
      <path d="M4 2.5h5.5l2.5 2.5v8.5H4z" />
      <path d="M9.5 2.5V5H12M6 8h4M6 10.5h4" />
    </>
  ),
  shield: (
    <>
      <path d="M8 2 3 4v4c0 3 2 5 5 6 3-1 5-3 5-6V4z" />
      <path d="M6 8l1.5 1.5L10.2 6.7" />
    </>
  ),
  headset: (
    <>
      <path d="M3 9V8a5 5 0 0 1 10 0v1" />
      <rect x="2.2" y="8.5" width="2.6" height="3.8" rx="1" />
      <rect x="11.2" y="8.5" width="2.6" height="3.8" rx="1" />
    </>
  ),
  mail: (
    <>
      <rect x="2" y="3.5" width="12" height="9" rx="1.2" />
      <path d="M2.5 4.5 8 9l5.5-4.5" />
    </>
  ),
  gear: (
    <>
      <circle cx="8" cy="8" r="2" />
      <path d="M8 1.8v1.7M8 12.5v1.7M1.8 8h1.7M12.5 8h1.7M3.6 3.6l1.2 1.2M11.2 11.2l1.2 1.2M3.6 12.4l1.2-1.2M11.2 4.8l1.2-1.2" />
    </>
  ),
  card: (
    <>
      <rect x="2" y="3.5" width="12" height="9" rx="1.4" />
      <path d="M2 6.8h12M4.5 10h2.5" />
    </>
  ),
  phone: (
    <>
      <rect x="4.5" y="1.8" width="7" height="12.4" rx="1.5" />
      <path d="M7.2 12.2h1.6" />
    </>
  ),
  coin: (
    <>
      <circle cx="8" cy="8" r="5.8" />
      <path d="M8 5v6M6.2 6.6c.3-.8 1-1.1 1.8-1.1s1.8.4 1.8 1.2c0 1.7-3.8.7-3.8 2.5 0 .8.9 1.3 2 1.3.8 0 1.5-.3 1.8-1" />
    </>
  ),
  star: <path d="M8 2.2l1.8 3.7 4 .6-2.9 2.8.7 4L8 11.4 4.4 13.3l.7-4L2.2 6.5l4-.6z" />,
  tag: (
    <>
      <path d="M2.5 8.2V2.5h5.7l5.3 5.3-5.7 5.7z" />
      <circle cx="5.4" cy="5.4" r=".6" />
    </>
  ),
  check: <path d="M3 8.4l3.2 3.1L13 4.6" />,
  image: (
    <>
      <rect x="2" y="3" width="12" height="10" rx="1.2" />
      <circle cx="5.7" cy="6.3" r="1" />
      <path d="M2.5 12l3.6-3.4 2.6 2.4 2-1.8 3.3 2.8" />
    </>
  ),
  chart: (
    <>
      <path d="M2.5 13.5h11M4 11V8M7 11V4.5M10 11V6.5M13 11V3" />
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
