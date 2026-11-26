import type { ReactNode } from "react";

// Line icons drawn on a 20x20 grid (1.5 stroke). They take the text colour and are sized by `size`.
const NEW = {
  menu: <path d="M3 6h14M3 10h14M3 14h14" />,
  sun: (
    <>
      <circle cx="10" cy="10" r="3.5" />
      <path d="M10 2.5v1.8M10 15.7v1.8M2.5 10h1.8M15.7 10h1.8M4.7 4.7l1.3 1.3M14 14l1.3 1.3M4.7 15.3 6 14M14 6l1.3-1.3" />
    </>
  ),
  moon: <path d="M16.5 12A7 7 0 0 1 8 3.5 7 7 0 1 0 16.5 12z" />,
  logout: <path d="M8 3.5H5A1.5 1.5 0 0 0 3.5 5v10A1.5 1.5 0 0 0 5 16.5h3M12.5 6.5 16 10l-3.5 3.5M16 10H8" />,
  external: <path d="M8 4.5H5.5A1.5 1.5 0 0 0 4 6v8.5A1.5 1.5 0 0 0 5.5 16H14a1.5 1.5 0 0 0 1.5-1.5V12M11 4h5v5M16 4l-7 7" />,
  chevron: <path d="M7.4 4.4 13 10l-5.6 5.6" />,
  eye: (
    <>
      <path d="M2 10s3-5.5 8-5.5S18 10 18 10s-3 5.5-8 5.5S2 10 2 10z" />
      <circle cx="10" cy="10" r="2.5" />
    </>
  ),
  eyeOff: (
    <>
      <path d="M8.2 5a8 8 0 0 1 1.8-.5c5 0 8 5.5 8 5.5a14 14 0 0 1-2.2 2.9M5.6 6.4A14 14 0 0 0 2 10s3 5.5 8 5.5a7.6 7.6 0 0 0 3.4-.8" />
      <path d="M8.5 8.5a2.5 2.5 0 0 0 3 3M3 3l14 14" />
    </>
  ),
  help: (
    <>
      <circle cx="10" cy="10" r="7.5" />
      <path d="M7.8 8a2.3 2.3 0 1 1 3.4 2c-.8.5-1.2 1-1.2 1.8M10 14.2v.01" />
    </>
  ),
  close: <path d="M5 5l10 10M15 5 5 15" />,
  plus: <path d="M10 4v12M4 10h12" />,
  minus: <path d="M4 10h12" />,
  trash: <path d="M3.5 5.5h13M7.5 5.5V3.8h5v1.7M5.5 5.5l.8 10.7h7.4l.8-10.7M8.5 9v4.5M11.5 9v4.5" />,
  edit: <path d="M3.5 16.5l.8-3.6L13 4.2a1.5 1.5 0 0 1 2.1 0l.7.7a1.5 1.5 0 0 1 0 2.1L7.1 15.7zM11.5 5.7l2.8 2.8" />,
  refresh: <path d="M16 10a6 6 0 1 1-1.8-4.3M16 3.5v3.2h-3.2" />,
  gauge: <path d="M3.5 14a6.5 6.5 0 1 1 13 0M10 14l3-4.5" />,
  hospital: <path d="M4 16.5V4.5A1 1 0 0 1 5 3.5h10a1 1 0 0 1 1 1v12M2.5 16.5h15M10 6.5v5M7.5 9h5" />,
  grid: (
    <>
      <rect x="3.5" y="3.5" width="5" height="5" rx="1" />
      <rect x="11.5" y="3.5" width="5" height="5" rx="1" />
      <rect x="3.5" y="11.5" width="5" height="5" rx="1" />
      <rect x="11.5" y="11.5" width="5" height="5" rx="1" />
    </>
  ),
  people: (
    <>
      <circle cx="10" cy="7" r="3" />
      <path d="M4 16.5c.6-3 3-4.5 6-4.5s5.4 1.5 6 4.5" />
    </>
  ),
  calendar: (
    <>
      <rect x="3.5" y="4.5" width="13" height="12" rx="1.5" />
      <path d="M3.5 8.5h13M7 3v3M13 3v3" />
    </>
  ),
  card: (
    <>
      <rect x="2.5" y="5" width="15" height="10" rx="1.5" />
      <path d="M2.5 8.5h15M5.5 12h3" />
    </>
  ),
  chat: <path d="M4 4.5h12a1 1 0 0 1 1 1v7a1 1 0 0 1-1 1H9l-3.5 3v-3H4a1 1 0 0 1-1-1v-7a1 1 0 0 1 1-1z" />,
  lock: (
    <>
      <rect x="4.5" y="9" width="11" height="8" rx="1.5" />
      <path d="M7 9V6.5a3 3 0 0 1 6 0V9" />
    </>
  ),
  alert: <path d="M10 3.5l7 12.5H3L10 3.5zM10 8.5v3.5M10 14v.01" />,
  clock: (
    <>
      <circle cx="10" cy="10" r="7" />
      <path d="M10 6v4l2.8 1.8" />
    </>
  ),
  pin: (
    <>
      <path d="M10 17.5s5.5-4.7 5.5-9A5.5 5.5 0 0 0 4.5 8.5c0 4.3 5.5 9 5.5 9z" />
      <circle cx="10" cy="8.5" r="2" />
    </>
  ),
  sparkle: <path d="M9 3.5l1.6 4.4 4.4 1.6-4.4 1.6L9 15.5l-1.6-4.4L3 9.5l4.4-1.6zM15.5 3v3M14 4.5h3" />,
  unplug: <path d="M3 10h3.5M13.5 10H17M6.5 7v6l3.5-1.5v-3L6.5 7zM13.5 8v4" />,
  search: (
    <>
      <circle cx="9" cy="9" r="5.5" />
      <path d="M13.2 13.2 17 17" />
    </>
  ),
  ban: (
    <>
      <circle cx="10" cy="10" r="6.5" />
      <path d="M5.4 5.4l9.2 9.2" />
    </>
  ),
  sort: <path d="M7 4.5v11M4.5 13 7 15.5 9.5 13M13 15.5v-11M10.5 7 13 4.5 15.5 7" />,
} satisfies Record<string, ReactNode>;

// Older icons, drawn on a 16x16 grid. Only the sidebar group headings still use them; they are
// enlarged to the same footprint as the 20x20 set.
const OLD = {
  chevronUp: <path d="M3.5 10 8 5.5l4.5 4.5" />,
  info: (
    <>
      <circle cx="8" cy="8" r="6" />
      <path d="M8 7.2v4M8 4.9v.2" />
    </>
  ),
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

type NewName = keyof typeof NEW;
type OldName = keyof typeof OLD;
export type IconName = NewName | OldName;

const isNew = (name: IconName): name is NewName => name in NEW;

const OLD_SCALE = 1.125;

/** Decorative by default (hidden from assistive tech): the control around it carries the label. The stroke stays 1.5px at every size. */
export default function Icon({ name, size = 20, className }: { name: IconName; size?: number; className?: string }) {
  const common = {
    className: className ? `icon ${className}` : "icon",
    width: size,
    height: size,
    viewBox: "0 0 20 20",
    fill: "none",
    stroke: "currentColor",
    strokeLinecap: "round" as const,
    strokeLinejoin: "round" as const,
    "aria-hidden": true as const,
    focusable: "false" as const,
  };
  if (isNew(name)) {
    return (
      <svg {...common} strokeWidth={(1.5 * 20) / size}>
        {NEW[name]}
      </svg>
    );
  }
  return (
    <svg {...common}>
      <g transform={`translate(10 10) scale(${OLD_SCALE}) translate(-8 -8)`} strokeWidth={(1.5 * 20) / (OLD_SCALE * size)}>
        {OLD[name as OldName]}
      </g>
    </svg>
  );
}
