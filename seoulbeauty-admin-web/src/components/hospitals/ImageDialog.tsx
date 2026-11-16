"use client";

import { useEffect, useRef } from "react";
import Icon from "../Icon";
import { useT } from "@/lib/locale";

export type ZoomImage = { src: string; alt: string };

/** A picture at full size. Native <dialog>: Esc, focus trap and return of focus come with it. */
export default function ImageDialog({ image, onClose }: { image: ZoomImage | null; onClose: () => void }) {
  const t = useT();
  const ref = useRef<HTMLDialogElement>(null);

  useEffect(() => {
    const dialog = ref.current;
    if (!dialog) return;
    if (image && !dialog.open) {
      dialog.showModal();
      dialog.querySelector<HTMLElement>("[data-autofocus]")?.focus();
    }
    if (!image && dialog.open) dialog.close();
  }, [image]);

  return (
    <dialog
      ref={ref}
      className="dialog dialog--image"
      aria-labelledby="image-title"
      onClose={onClose}
      onClick={(e) => {
        // A click on the backdrop lands on the <dialog> element itself.
        if (e.target === e.currentTarget) onClose();
      }}
    >
      {image ? (
        <div className="dialog__box">
          <div className="dialog__head">
            <h2 id="image-title" className="dialog__title">{image.alt}</h2>
            <button type="button" className="ibtn dialog__close" onClick={onClose} aria-label={t("m.close")}>
              <Icon name="close" />
            </button>
          </div>
          {/* eslint-disable-next-line @next/next/no-img-element -- stored upload from the old panel or an outside host: shown as it is, no optimizer */}
          <img className="dialog__image" src={image.src} alt={image.alt} />
          <div className="dialog__foot">
            <a className="btn btn--ghost" href={image.src} target="_blank" rel="noopener noreferrer">{t("h.img.open")}</a>
            <button type="button" className="btn btn--primary" data-autofocus onClick={onClose}>{t("m.close")}</button>
          </div>
        </div>
      ) : null}
    </dialog>
  );
}
