"use client";

import { useT } from "@/lib/locale";
import { imageHref } from "@/lib/moderation";

const SHOWN = 3;

/** Up to three small pictures (each opens full size in a new tab) and "+N" for the rest. */
export default function Thumbs({ images }: { images: string[] }) {
  const t = useT();
  const urls = images.map(imageHref).filter((u) => u !== "");
  if (urls.length === 0) return null;
  const more = urls.length - SHOWN;
  return (
    <ul className="mod-thumbs">
      {urls.slice(0, SHOWN).map((url, i) => (
        <li key={`${i}-${url}`}>
          <a className="mod-thumb" href={url} target="_blank" rel="noopener noreferrer" aria-label={t("mod.img.open", { n: i + 1 })}>
            {/* eslint-disable-next-line @next/next/no-img-element -- stored pictures come from the panel's media host, not the Next image optimizer */}
            <img src={url} alt="" loading="lazy" referrerPolicy="no-referrer" />
          </a>
        </li>
      ))}
      {more > 0 ? <li className="mod-thumb mod-thumb--more" aria-label={t("mod.img.more", { n: more })}>+{more}</li> : null}
    </ul>
  );
}
