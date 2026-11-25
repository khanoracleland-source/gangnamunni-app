"use client";

import { useEffect, useId, useRef, useState, type ReactNode } from "react";
import { createPortal } from "react-dom";
import Icon from "../Icon";
import { Notice } from "../hospitals/parts";
import { showUnitToast } from "../treatment-units/UnitToast";
import { consultationPhotoUrl, fetchConsultationDetail, type ApiResult, type ConsultationDetail } from "@/lib/api";
import { TYPE_KEYS, isConsultationStatus } from "@/lib/consultations";
import type { MessageKey } from "@/lib/i18n";
import { useT } from "@/lib/locale";

const TAG = /^\[([^\]]+)\]\s*(.*)$/u;
const EVENT_KEYS: Record<string, MessageKey> = { completed: "cs.d.ev.completed", no_answer: "cs.d.ev.no_answer", rerequest: "cs.d.ev.rerequest" };

/** "2026-11-24 10:00:00" -> "2026.11.24 10:00" (the stored time, not converted). */
function fullTime(value: string): string {
  const m = /^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/.exec(value);
  return m ? `${m[1]}.${m[2]}.${m[3]} ${m[4]}:${m[5]}` : "—";
}

/** The request as the admin reads it; `foot` is the action bar below it. */
export function DetailView({ id, own, mode, onClose, foot, reloadKey = 0 }: {
  id: number; own: boolean; mode: "panel" | "sheet"; onClose: () => void; foot?: (d: ConsultationDetail) => ReactNode; reloadKey?: number;
}) {
  const t = useT();
  const [got, setGot] = useState<{ key: string; res: ApiResult<ConsultationDetail> } | null>(null);
  const [retry, setRetry] = useState(0);
  const [photoAt, setPhotoAt] = useState<{ id: number; index: number } | null>(null);
  // The viewer belongs to the request it was opened on.
  const photo = photoAt && photoAt.id === id ? photoAt.index : null;
  const setPhoto = (index: number | null) => setPhotoAt(index === null ? null : { id, index });
  const key = `${id}#${reloadKey}#${retry}`;

  useEffect(() => {
    const c = new AbortController();
    void fetchConsultationDetail(id, c.signal).then((res) => {
      if (!c.signal.aborted) setGot({ key, res });
    });
    return () => c.abort();
    // `key` stands for id, reloadKey and retry.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [key]);

  // A fresh read of the same request replaces the old one in place; another request shows the loading state.
  const current = got && got.res.ok && got.res.data.id === id ? got.res.data : null;
  const settled = got?.key === key;
  const failed = settled && got && !got.res.ok ? got.res : null;

  let body: ReactNode;
  if (current) {
    body = <DetailBody d={current} own={own} onPhoto={setPhoto} />;
  } else if (failed) {
    body = (
      <div className="cs-d__state">
        {failed.status === 404 ? <Notice tone="warn" icon="alert">{t("cs.d.gone")}</Notice> : (
          <Notice tone="warn" icon="alert" action={<button type="button" className="btn btn--ghost btn--sm" onClick={() => setRetry((n) => n + 1)}>{t("state.retry")}</button>}>{t("cs.d.err")}</Notice>
        )}
      </div>
    );
  } else {
    body = (
      <div className="cs-d__state" role="status" aria-label={t("cs.d.loading")}>
        <span className="cs-sk cs-sk--l" /><span className="cs-sk cs-sk--m" /><span className="cs-sk cs-sk--l" />
      </div>
    );
  }

  return (
    <div className={`cs-d cs-d--${mode}`} key={id}>
      <div className="cs-d__scroll">
        <button type="button" className="ibtn cs-d__close" onClick={onClose} aria-label={t("cs.d.close")} title={t("cs.d.close")} data-autofocus=""><Icon name="close" size={18} /></button>
        {body}
      </div>
      {current && foot ? <div className="cs-d__foot">{foot(current)}</div> : null}
      {current && photo !== null && photo < current.photoCount ? <Lightbox id={current.id} count={current.photoCount} index={photo} onIndex={setPhoto} onClose={() => setPhoto(null)} /> : null}
    </div>
  );
}

function DetailBody({ d, own, onPhoto }: { d: ConsultationDetail; own: boolean; onPhoto: (i: number) => void }) {
  const t = useT();
  const typeKey = TYPE_KEYS[d.type];
  const dialable = d.phone.replace(/[^0-9+]/g, "");
  // The form lines "[label] value" become rows; a line without a label stays in the text.
  const rows: { label: string; value: string }[] = [];
  const loose: string[] = [];
  for (const line of d.tags) {
    const m = TAG.exec(line);
    if (m && m[2].trim()) rows.push({ label: m[1], value: m[2].trim() });
    else loose.push(line);
  }
  const message = [...loose, d.text].filter((x) => x.trim()).join("\n");

  const copy = async () => {
    try {
      await navigator.clipboard.writeText(d.phone);
      showUnitToast({ tone: "ok", message: t("cs.d.copied"), closeLabel: t("cs.d.close") });
    } catch {
      showUnitToast({ tone: "error", message: t("cs.d.copyFail"), closeLabel: t("cs.d.close") });
    }
  };

  return (
    <>
      <header className="cs-d__head">
        <h2 className="cs-d__name">{d.name || "—"}</h2>
        <p className="cs-d__sub">
          #{d.id} · {t("cs.d.applied", { time: fullTime(d.createdAt) })}
          {" "}<span className={`cs-st cs-st--${isConsultationStatus(d.status) ? d.status : "unknown"}`}>{isConsultationStatus(d.status) ? t(`cs.st.${d.status}` as MessageKey) : d.status}</span>
        </p>
      </header>

      <div className="cs-d__phone">
        {dialable ? <a className="cs-d__tel" href={`tel:${dialable}`} aria-label={`${t("cs.d.call")} ${d.phone}`}>{d.phone}</a> : <span className="cs-d__tel">{d.phone || "—"}</span>}
        {d.phone ? <button type="button" className="btn btn--ghost btn--sm" onClick={() => void copy()}><Icon name="clipboard" size={14} />{t("cs.d.copy")}</button> : null}
      </div>

      <dl className="cs-d__dl">
        {!own && d.hospitalName ? <><dt>{t("cs.d.hospital")}</dt><dd>{d.hospitalName}</dd></> : null}
        {typeKey ? <><dt>{t("cs.d.type")}</dt><dd>{t(typeKey)}</dd></> : null}
        {rows.map((r, i) => <div className="cs-d__row" key={`${r.label}${i}`}><dt>{r.label}</dt><dd>{r.value}</dd></div>)}
        {d.callbackSlot ? <><dt>{t("cs.d.callback")}</dt><dd>{d.callbackSlot}</dd></> : null}
      </dl>

      <section className="cs-d__sec">
        <h3>{t("cs.d.message")}</h3>
        {message ? <p className="cs-d__msg">{message}</p> : <p className="cs-d__none">{t("cs.d.noMessage")}</p>}
      </section>

      {d.photoCount > 0 ? (
        <section className="cs-d__sec">
          <h3>{t("cs.d.photos")}<small>{t("cs.d.photoCount", { n: String(d.photoCount) })}</small></h3>
          <div className="cs-d__ph">
            {Array.from({ length: d.photoCount }, (_, i) => (
              <button key={i} type="button" className="cs-ph" onClick={() => onPhoto(i)} aria-label={t("cs.d.photoOpen", { n: String(i + 1) })}>
                {/* eslint-disable-next-line @next/next/no-img-element */}
                <img src={consultationPhotoUrl(d.id, i)} alt="" loading="lazy" decoding="async" onError={(e) => { e.currentTarget.style.visibility = "hidden"; }} />
              </button>
            ))}
          </div>
        </section>
      ) : null}

      {d.historySupported ? (
        <>
          <section className="cs-d__sec">
            <h3>{t("cs.d.hmsg")}<small>{t("cs.d.hmsgSeen")}</small></h3>
            {d.hospitalMessage ? (
              <>
                <p className="cs-d__hm">{d.hospitalMessage}</p>
                {d.hospitalMessageAt ? <p className="cs-d__when">{t("cs.d.hmsgAt", { time: fullTime(d.hospitalMessageAt) })}</p> : null}
              </>
            ) : <p className="cs-d__none">{t("cs.d.hmsgNone")}</p>}
          </section>
          <section className="cs-d__sec">
            <h3>{t("cs.d.progress")}</h3>
            {d.events.length ? (
              <ol className="cs-d__events">
                {d.events.map((e, i) => (
                  <li key={`${e.kind}${e.createdAt}${i}`}><span className={`cs-d__dot cs-d__dot--${e.kind}`} aria-hidden="true" /><span>{EVENT_KEYS[e.kind] ? t(EVENT_KEYS[e.kind]) : e.kind}</span><time>{fullTime(e.createdAt)}</time></li>
                ))}
              </ol>
            ) : <p className="cs-d__none">{t("cs.d.progressNone")}</p>}
          </section>
        </>
      ) : <Notice tone="info">{t("cs.d.noHistory")}</Notice>}
    </>
  );
}

/** The attached photos one by one: arrow keys, Esc, thumbnails. */
function Lightbox({ id, count, index, onIndex, onClose }: { id: number; count: number; index: number; onIndex: (i: number) => void; onClose: () => void }) {
  const t = useT();
  const ref = useRef<HTMLDialogElement>(null);
  const titleId = useId();
  const [broken, setBroken] = useState<number[]>([]);
  const go = (i: number) => onIndex((i + count) % count);

  useEffect(() => {
    const dialog = ref.current;
    if (!dialog) return;
    const returnTo = document.activeElement instanceof HTMLElement ? document.activeElement : null;
    if (!dialog.open) dialog.showModal();
    dialog.querySelector<HTMLElement>("[data-autofocus]")?.focus({ preventScroll: true });
    return () => {
      if (dialog.open) dialog.close();
      if (returnTo?.isConnected) returnTo.focus();
    };
  }, []);

  return createPortal(
    <dialog
      ref={ref}
      className="dialog cs-lb"
      aria-labelledby={titleId}
      onClose={onClose}
      onClick={(e) => { if (e.target === e.currentTarget) onClose(); }}
      onKeyDown={(e) => {
        if (e.key === "ArrowLeft") { e.preventDefault(); go(index - 1); }
        else if (e.key === "ArrowRight") { e.preventDefault(); go(index + 1); }
      }}
    >
      <div className="cs-lb__box">
        <div className="cs-lb__head">
          <h2 id={titleId} className="dialog__title">{t("cs.lb.title", { n: String(index + 1), total: String(count) })}</h2>
          <button type="button" className="ibtn dialog__close" onClick={onClose} aria-label={t("cs.lb.close")} title={t("cs.lb.close")} data-autofocus=""><Icon name="close" size={18} /></button>
        </div>
        <div className="cs-lb__stage">
          {count > 1 ? <button type="button" className="ibtn cs-lb__nav cs-lb__nav--prev" onClick={() => go(index - 1)} aria-label={t("cs.lb.prev")}><Icon name="chevron" size={20} /></button> : null}
          {broken.includes(index) ? <p className="cs-d__none">{t("cs.d.photoGone")}</p> : (
            // eslint-disable-next-line @next/next/no-img-element
            <img key={index} className="cs-lb__img" src={consultationPhotoUrl(id, index)} alt={t("cs.lb.thumb", { n: String(index + 1) })} onError={() => setBroken((b) => [...b, index])} />
          )}
          {count > 1 ? <button type="button" className="ibtn cs-lb__nav cs-lb__nav--next" onClick={() => go(index + 1)} aria-label={t("cs.lb.next")}><Icon name="chevron" size={20} /></button> : null}
        </div>
        {count > 1 ? (
          <div className="cs-lb__thumbs">
            {Array.from({ length: count }, (_, i) => (
              <button key={i} type="button" className="cs-lb__thumb" aria-current={i === index ? "true" : undefined} aria-label={t("cs.lb.thumb", { n: String(i + 1) })} onClick={() => onIndex(i)}>
                {/* eslint-disable-next-line @next/next/no-img-element */}
                <img src={consultationPhotoUrl(id, i)} alt="" loading="lazy" decoding="async" />
              </button>
            ))}
          </div>
        ) : null}
      </div>
    </dialog>,
    document.body,
  );
}

/** The request in a modal sheet (below 1280px): a dialog on tablets, a bottom sheet on phones. */
export function DetailSheet({ id, own, onClose, foot, reloadKey }: { id: number; own: boolean; onClose: () => void; foot?: (d: ConsultationDetail) => ReactNode; reloadKey?: number }) {
  const ref = useRef<HTMLDialogElement>(null);
  const t = useT();

  useEffect(() => {
    const dialog = ref.current;
    if (!dialog) return;
    const returnTo = document.activeElement instanceof HTMLElement ? document.activeElement : null;
    if (!dialog.open) dialog.showModal();
    dialog.querySelector<HTMLElement>("[data-autofocus]")?.focus({ preventScroll: true });
    return () => {
      if (dialog.open) dialog.close();
      if (returnTo?.isConnected) returnTo.focus();
    };
  }, []);

  return createPortal(
    <dialog
      ref={ref}
      className="dialog cs-sheet"
      aria-label={t("cs.d.title")}
      onClose={onClose}
      onClick={(e) => { if (e.target === e.currentTarget) onClose(); }}
    >
      <span className="cs-sheet__handle" aria-hidden="true" />
      <DetailView id={id} own={own} mode="sheet" onClose={onClose} foot={foot} reloadKey={reloadKey} />
    </dialog>,
    document.body,
  );
}
