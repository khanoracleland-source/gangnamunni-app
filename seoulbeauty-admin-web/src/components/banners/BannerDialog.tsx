"use client";

import { useEffect, useId, useMemo, useRef, useState, type ChangeEvent, type FormEvent } from "react";
import Icon from "../Icon";
import HospitalCombo from "../payments/HospitalCombo";
import { saveBanner, type BannerInput, type BannerItem, type DoctorHospitalOption } from "@/lib/api";
import { BANNER_IMAGE_ACCEPT, BANNER_LINK_MAX, BANNER_ORDER_MAX, BANNER_TEXT_MAX, bannerImageHref, checkBannerInput } from "@/lib/banners";
import type { MessageKey } from "@/lib/i18n";
import { useT } from "@/lib/locale";

/** Add (item null) or edit one banner. Native <dialog>; while a save runs it cannot be closed. `onSaved(created)` after the server accepted it. */
export default function BannerDialog({ item, hospitals, onClose, onSaved, onStale }: { item: BannerItem | null; hospitals: DoctorHospitalOption[]; onClose: () => void; onSaved: (created: boolean) => void; onStale: () => void }) {
  const t = useT();
  const uid = useId();
  const ref = useRef<HTMLDialogElement>(null);
  const [title, setTitle] = useState(item?.title ?? "");
  const [subtitle, setSubtitle] = useState(item?.subtitle ?? "");
  const [linkUrl, setLinkUrl] = useState(item?.linkUrl ?? "");
  const [order, setOrder] = useState(String(item?.displayOrder ?? 0));
  const [hospitalId, setHospitalId] = useState(item?.hospitalId ?? 0);
  const [homeKept, setHomeKept] = useState(item?.image ?? "");
  const [myKept, setMyKept] = useState(item?.mypageImage ?? "");
  const [homeFile, setHomeFile] = useState<File | null>(null);
  const [myFile, setMyFile] = useState<File | null>(null);
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [summary, setSummary] = useState<string | null>(null);
  const [saving, setSaving] = useState(false);
  const lock = useRef(false);

  useEffect(() => {
    const dialog = ref.current;
    if (dialog && !dialog.open) dialog.showModal();
  }, []);

  const homePreview = usePreview(homeFile, homeKept);
  const myPreview = usePreview(myFile, myKept);

  const submit = async (e: FormEvent) => {
    e.preventDefault();
    if (lock.current) return;
    const input: BannerInput = { title, subtitle, linkUrl, displayOrder: order, hospitalId, imageUrl: homeFile ? "" : homeKept, mypageImageUrl: myFile ? "" : myKept, image: homeFile, mypageImage: myFile };
    const found = checkBannerInput(input);
    setErrors(found);
    setSummary(null);
    if (Object.keys(found).length > 0) {
      setSummary(t("ban.dlg.errors"));
      return;
    }
    lock.current = true;
    setSaving(true);
    try {
      const res = await saveBanner(input, item ? { id: item.id, revision: item.revision } : null);
      if (res.ok) {
        onSaved(item === null);
        ref.current?.close();
        return;
      }
      if (res.status === 401) {
        window.location.reload();
        return;
      }
      if (res.error === "changed") {
        onStale();
        ref.current?.close();
        return;
      }
      if (res.status === 404) {
        onStale();
        ref.current?.close();
        return;
      }
      if (res.status === 400 && Object.keys(res.fields).length > 0) {
        const f = { ...res.fields };
        if (f.revision) delete f.revision;
        setErrors(f);
        setSummary(t("ban.dlg.errors"));
        return;
      }
      if (res.error === "too_large") setErrors({ image: "too_large" });
      else if (res.error === "upload_failed") setErrors({ image: "invalid_image" });
      setSummary(t(res.status === 403 ? "ban.toast.forbidden" : res.status === 503 ? "ban.toast.unavailable" : res.error === "too_large" || res.error === "upload_failed" ? "ban.dlg.errors" : "ban.toast.failed"));
    } finally {
      lock.current = false;
      setSaving(false);
    }
  };

  const close = () => {
    if (!saving) ref.current?.close();
  };
  const err = (k: string): MessageKey | null => (errors[k] ? (`ban.er.${errors[k]}` in ERR ? (`ban.er.${errors[k]}` as MessageKey) : "ban.er.invalid") : null);
  const fid = (k: string) => `${uid}-${k}`;
  const hint = (k: string) => (errors[k] ? `${fid(k)}-e` : undefined);

  return (
    <dialog
      ref={ref}
      className="dialog ban-dlg"
      aria-labelledby={fid("t")}
      onClose={onClose}
      onCancel={(e) => {
        if (saving) e.preventDefault();
      }}
    >
      <form className="ban-dlg__box" onSubmit={(e) => void submit(e)} noValidate>
        <div className="ban-dlg__head">
          <h2 id={fid("t")} className="dialog__title">{t(item ? "ban.dlg.edit" : "ban.dlg.add")}</h2>
          <button type="button" className="ibtn dialog__close" onClick={close} disabled={saving} aria-label={t("m.close")} title={t("m.close")}>
            <Icon name="close" />
          </button>
        </div>
        <div className="ban-dlg__body">
          {summary ? <p className="ban-dlg__sum" role="alert"><Icon name="alert" size={16} />{summary}</p> : null}
          <div className="ban-dlg__grid">
            <div className="ban-pics">
              <Picture label={t("ban.im.home")} preview={homePreview} file={homeFile} error={err("image")} errorId={fid("image-e")} disabled={saving} onPick={(f) => { setHomeFile(f); setErrors((x) => ({ ...x, image: "" })); }} onRemove={() => { setHomeFile(null); setHomeKept(""); }} />
              <Picture
                label={t("ban.im.my")}
                preview={myPreview}
                fallback={homePreview}
                file={myFile}
                error={err("mypageImage")}
                errorId={fid("mypageImage-e")}
                disabled={saving}
                note={t("ban.im.myHint")}
                onPick={(f) => { setMyFile(f); setErrors((x) => ({ ...x, mypageImage: "" })); }}
                onRemove={() => { setMyFile(null); setMyKept(""); }}
              />
              <p className="ban-pics__hint">{t("ban.im.hint")}</p>
            </div>
            <div className="ban-form">
              <Text id={fid("title")} label={t("ban.fd.title")} value={title} max={BANNER_TEXT_MAX} error={err("title")} errorId={hint("title")} disabled={saving} required onChange={setTitle} autoFocus />
              <Text id={fid("subtitle")} label={t("ban.fd.subtitle")} value={subtitle} max={BANNER_TEXT_MAX} error={err("subtitle")} errorId={hint("subtitle")} disabled={saving} onChange={setSubtitle} />
              <div className="ban-form__row">
                <Text id={fid("linkUrl")} label={t("ban.fd.link")} value={linkUrl} max={BANNER_LINK_MAX} placeholder={t("ban.fd.linkHint")} error={err("linkUrl")} errorId={hint("linkUrl")} disabled={saving} onChange={setLinkUrl} type="text" />
                <div className="field ban-form__order">
                  <label className="field__label" htmlFor={fid("displayOrder")}>{t("ban.fd.order")}</label>
                  <input id={fid("displayOrder")} className="input" type="number" inputMode="numeric" min={0} max={BANNER_ORDER_MAX} step={1} value={order} disabled={saving} aria-invalid={errors.displayOrder ? true : undefined} aria-describedby={hint("displayOrder")} onChange={(e) => setOrder(e.target.value)} />
                  <FieldError id={hint("displayOrder")} message={err("displayOrder") ? t(err("displayOrder") as MessageKey) : null} />
                </div>
              </div>
              <p className="ban-form__note">{t("ban.fd.orderHint")}</p>
              <div className="field ban-form__hosp">
                <HospitalCombo
                  hospitals={hospitals}
                  value={hospitalId}
                  onChange={setHospitalId}
                  label={t("ban.fd.hospital")}
                  placeholder={t("ban.fd.hospitalNone")}
                  clearLabel={t("d.hc.clear")}
                  noneLabel={t("d.hc.none")}
                  allLabel={t("ban.fd.hospitalNone")}
                />
                <FieldError id={hint("hospitalId")} message={err("hospitalId") ? t(err("hospitalId") as MessageKey) : null} />
              </div>
            </div>
          </div>
        </div>
        <div className="ban-dlg__foot">
          <button type="button" className="btn btn--ghost" onClick={close} disabled={saving}>{t("ban.dlg.cancel")}</button>
          <button type="submit" className="btn btn--primary" disabled={saving} aria-busy={saving || undefined}>{t(saving ? "ban.dlg.saving" : "ban.dlg.save")}</button>
        </div>
      </form>
    </dialog>
  );
}

// The error codes that have a text; anything else is shown as "not a valid value".
const ERR: Record<string, true> = { "ban.er.required": true, "ban.er.too_long": true, "ban.er.bad_url": true, "ban.er.range": true, "ban.er.unknown": true, "ban.er.invalid_image": true, "ban.er.too_large": true, "ban.er.invalid": true };

/** An address to draw the chosen file (a temporary object address, released when it changes) or the kept stored picture. */
function usePreview(file: File | null, kept: string): string {
  const url = useMemo(() => (file ? URL.createObjectURL(file) : ""), [file]);
  useEffect(() => () => { if (url) URL.revokeObjectURL(url); }, [url]);
  return file ? url : bannerImageHref(kept);
}

function FieldError({ id, message }: { id: string | undefined; message: string | null }) {
  if (!message) return null;
  return (
    <p id={id} className="ban-err" role="alert">
      <Icon name="alert" size={14} />
      {message}
    </p>
  );
}

function Text({ id, label, value, max, placeholder, error, errorId, disabled, required, autoFocus, type = "text", onChange }: { id: string; label: string; value: string; max: number; placeholder?: string; error: MessageKey | null; errorId: string | undefined; disabled: boolean; required?: boolean; autoFocus?: boolean; type?: string; onChange: (v: string) => void }) {
  const t = useT();
  return (
    <div className="field ban-form__f">
      <label className="field__label" htmlFor={id}>{label}</label>
      <input id={id} className="input" type={type} value={value} maxLength={max} placeholder={placeholder} required={required} autoFocus={autoFocus} autoComplete="off" disabled={disabled} aria-invalid={error ? true : undefined} aria-describedby={errorId} onChange={(e: ChangeEvent<HTMLInputElement>) => onChange(e.target.value)} />
      <FieldError id={errorId} message={error ? t(error) : null} />
    </div>
  );
}

function Picture({ label, preview, fallback, file, error, errorId, disabled, note, onPick, onRemove }: { label: string; preview: string; fallback?: string; file: File | null; error: MessageKey | null; errorId: string; disabled: boolean; note?: string; onPick: (f: File | null) => void; onRemove: () => void }) {
  const t = useT();
  const input = useRef<HTMLInputElement>(null);
  const shown = preview || fallback || "";
  const usingFallback = preview === "" && fallback !== undefined && fallback !== "";
  return (
    <div className="ban-pic">
      <span className="field__label">{label}</span>
      <div className={error ? "ban-pic__zone is-err" : preview ? "ban-pic__zone" : "ban-pic__zone is-empty"}>
        {shown ? (
          // eslint-disable-next-line @next/next/no-img-element -- a chosen file or a stored picture, not an optimizable asset
          <img src={shown} alt="" className={usingFallback ? "is-dim" : undefined} referrerPolicy="no-referrer" />
        ) : (
          <Icon name="image" size={22} />
        )}
        {usingFallback ? <span className="ban-pic__using">{t("ban.im.usingHome")}</span> : null}
      </div>
      {note ? <p className="ban-pic__note">{note}</p> : null}
      <div className="ban-pic__acts">
        <input ref={input} type="file" accept={BANNER_IMAGE_ACCEPT} hidden disabled={disabled} onChange={(e) => { onPick(e.target.files?.[0] ?? null); e.target.value = ""; }} />
        <button type="button" className="btn btn--ghost btn--sm" onClick={() => input.current?.click()} disabled={disabled}>{t(preview ? "ban.im.replace" : "ban.im.pick")}</button>
        {preview || file ? <button type="button" className="btn btn--ghost btn--sm" onClick={onRemove} disabled={disabled}>{t("ban.im.remove")}</button> : null}
      </div>
      <FieldError id={errorId} message={error ? t(error) : null} />
    </div>
  );
}
