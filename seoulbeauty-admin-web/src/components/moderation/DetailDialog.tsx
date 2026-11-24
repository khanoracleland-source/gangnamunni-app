"use client";

import { useEffect, useId, useRef, type ReactNode } from "react";
import Icon from "../Icon";
import { useT } from "@/lib/locale";
import { imageHref } from "@/lib/moderation";

/** A read-only window with everything of one item. Native <dialog>: Esc, focus trap and return of focus come with it. Mount it only while open. */
export default function DetailDialog({ title, onClose, children }: { title: string; onClose: () => void; children: ReactNode }) {
  const t = useT();
  const ref = useRef<HTMLDialogElement>(null);
  const titleId = useId();

  useEffect(() => {
    const dialog = ref.current;
    if (dialog && !dialog.open) dialog.showModal();
  }, []);

  const close = () => ref.current?.close();

  return (
    <dialog
      ref={ref}
      className="dialog mod-detail"
      aria-labelledby={titleId}
      onClose={onClose}
      onClick={(e) => {
        // A click on the backdrop lands on the <dialog> element itself.
        if (e.target === e.currentTarget) close();
      }}
    >
      <div className="dialog__box">
        <div className="dialog__head">
          <h2 id={titleId} className="dialog__title">{title}</h2>
          <button type="button" className="ibtn dialog__close" onClick={close} aria-label={t("m.close")} title={t("m.close")}>
            <Icon name="close" />
          </button>
        </div>
        <div className="mod-detail__body" tabIndex={0} role="region" aria-labelledby={titleId}>{children}</div>
        <div className="dialog__foot">
          <button type="button" className="btn btn--ghost" onClick={close}>{t("m.close")}</button>
        </div>
      </div>
    </dialog>
  );
}

/** The pictures of an item at a readable size; each opens full size in a new tab. */
export function Gallery({ images }: { images: string[] }) {
  const t = useT();
  const urls = images.map(imageHref).filter((u) => u !== "");
  if (urls.length === 0) return null;
  return (
    <section className="mod-gallery" aria-label={t("mod.detail.photos")}>
      <h3 className="mod-detail__h">{t("mod.detail.photos")}</h3>
      <ul className="mod-gallery__list">
        {urls.map((url, i) => (
          <li key={`${i}-${url}`}>
            <a href={url} target="_blank" rel="noopener noreferrer" aria-label={t("mod.img.open", { n: i + 1 })}>
              {/* eslint-disable-next-line @next/next/no-img-element -- stored pictures come from the panel's media host, not the Next image optimizer */}
              <img src={url} alt="" loading="lazy" referrerPolicy="no-referrer" />
            </a>
          </li>
        ))}
      </ul>
    </section>
  );
}
